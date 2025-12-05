<?php

/**
 * Fired during cron execution
 *
 * @link       https://oceanshiatsu.com
 * @since      1.3.0
 *
 * @package    Ocean_Shiatsu_Booking
 * @subpackage Ocean_Shiatsu_Booking/includes
 */

/**
 * Handles Two-Way Sync logic via WP Cron.
 *
 * @since      1.3.0
 * @package    Ocean_Shiatsu_Booking
 * @subpackage Ocean_Shiatsu_Booking/includes
 * @author     Ocean Shiatsu <info@oceanshiatsu.com>
 */
class Ocean_Shiatsu_Booking_Sync {

	public function init() {
		add_action( 'osb_cron_sync_events', array( $this, 'run_sync' ) );
		
		// Add custom cron schedule if not exists (every 15 mins)
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
	}

	public function add_cron_interval( $schedules ) {
		$schedules['every_15_mins'] = array(
			'interval' => 900,
			'display'  => esc_html__( 'Every 15 Minutes' ),
		);
		return $schedules;
	}

	public function run_sync() {
		Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Sync', 'Two-Way Sync Started' );

		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		if ( ! $gcal->is_connected() ) {
			Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'Sync', 'GCal not connected. Aborting.' );
			return;
		}

		global $wpdb;
		$settings_table = $wpdb->prefix . 'osb_settings';
		
		// 1. Get Last Sync Token (Time)
		$last_sync = $wpdb->get_var( "SELECT setting_value FROM $settings_table WHERE setting_key = 'gcal_last_sync_token'" );
		
		// If never synced, default to 30 days ago to catch recent changes
		if ( ! $last_sync ) {
			$last_sync = date( 'Y-m-d\TH:i:s\Z', strtotime( '-30 days' ) );
		}

		// 2. Fetch Modified Events from GCal
		$modified_events = $gcal->get_modified_events( $last_sync );
		
		if ( empty( $modified_events ) ) {
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Sync', 'No changes found in GCal.' );
			// Update token anyway to now
			$this->update_sync_token();
			return;
		}

		Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Sync', 'Found modified events', ['count' => count($modified_events)] );

		// 3. Process Changes
		foreach ( $modified_events as $event ) {
			$this->process_event( $event );
		}

		// 4. Update Sync Token
		$this->update_sync_token();
		
		Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Sync', 'Two-Way Sync Completed' );
	}

	private function process_event( $event ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';
		$event_id = $event['id'];
		
		// Find local booking
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE gcal_event_id = %s", $event_id ) );

		if ( ! $booking ) {
			// Event exists in GCal but not in WP.
			// Could be a manual event created in GCal.
			// We don't import random GCal events as bookings (unless we want to block slots, which is handled by availability check).
			// So we ignore unknown events.
			return;
		}

		// Handle Cancellation
		if ( $event['status'] === 'cancelled' ) {
			if ( $booking->status !== 'cancelled' ) {
				$wpdb->update( $table_name, ['status' => 'cancelled'], ['id' => $booking->id] );
				Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Sync', "Booking #{$booking->id} cancelled via GCal." );
				// TODO: Notify Admin/Client?
			}
			return;
		}

		// Handle Updates (Time Change)
		// Normalize to UTC for comparison if possible, or just compare timestamps
		$gcal_start_ts = strtotime( $event['start'] );
		$gcal_end_ts = strtotime( $event['end'] );
		$wp_start_ts = strtotime( $booking->start_time );
		$wp_end_ts = strtotime( $booking->end_time );

		if ( $wp_start_ts !== $gcal_start_ts || $wp_end_ts !== $gcal_end_ts ) {
			$gcal_start = date( 'Y-m-d H:i:s', $gcal_start_ts );
			$gcal_end = date( 'Y-m-d H:i:s', $gcal_end_ts );

			$wpdb->update( 
				$table_name, 
				[
					'start_time' => $gcal_start,
					'end_time' => $gcal_end,
					// If it was pending, maybe confirm it? Or keep pending?
					// If moved in GCal, it's likely confirmed/accepted.
					// Let's not change status for now, just time.
				], 
				['id' => $booking->id] 
			);
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Sync', "Booking #{$booking->id} moved via GCal to $gcal_start." );
			// TODO: Notify Admin/Client?
		}
	}

	/**
	 * Alias for run_sync() - Called by Webhook handler.
	 */
	public function sync_events() {
		$this->run_sync();
	}

	public function calculate_monthly_availability( $month ) {
		// $month format: YYYY-MM
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_availability_index';
		$settings_table = $wpdb->prefix . 'osb_settings';
		
		$start_date = $month . '-01';
		$end_date = date( 'Y-m-t', strtotime( $start_date ) );
		
		$services = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}osb_services" );
		$clustering = new Ocean_Shiatsu_Booking_Clustering();
		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();

		// Get Settings
		$max_bookings = intval( $this->get_setting( 'max_bookings_per_day' ) ) ?: 0; // 0 = unlimited
		$all_day_is_holiday = $this->get_setting( 'all_day_is_holiday' ) !== '0'; // Default true
		$holiday_keywords_raw = $this->get_setting( 'holiday_keywords' ) ?: 'Holiday,Urlaub,Closed';
		$holiday_keywords = array_map( 'trim', explode( ',', $holiday_keywords_raw ) );
		
		// Get Working Days
		$working_days_json = $this->get_setting( 'working_days' );
		$working_days = $working_days_json ? json_decode( $working_days_json, true ) : ['1','2','3','4','5'];

		$current = strtotime( $start_date );
		$end = strtotime( $end_date );

		while ( $current <= $end ) {
			$date = date( 'Y-m-d', $current );
			$day_of_week = date( 'N', $current ); // 1 (Mon) - 7 (Sun)
			
			// 1. Check if it's a Working Day
			if ( ! in_array( (string)$day_of_week, $working_days ) ) {
				// Closed Day
				foreach ( $services as $service ) {
					$this->upsert_availability( $table_name, $date, $service->id, 'closed' );
				}
				$current = strtotime( '+1 day', $current );
				continue;
			}

			// 2. Check for Holiday (All-Day or Spanning Event)
			$gcal_events = $gcal->get_events_for_date( $date );
			$is_holiday = false;
			
			if ( is_array( $gcal_events ) ) {
				foreach ( $gcal_events as $event ) {
					// Check All-Day flag (from updated GCal class)
					if ( ! empty( $event['is_all_day'] ) && $all_day_is_holiday ) {
						$is_holiday = true;
						break;
					}
					// Check Keyword Match
					$summary = isset( $event['summary'] ) ? $event['summary'] : '';
					foreach ( $holiday_keywords as $keyword ) {
						if ( ! empty( $keyword ) && stripos( $summary, $keyword ) !== false ) {
							$is_holiday = true;
							break 2;
						}
					}
				}
			}

			if ( $is_holiday ) {
				foreach ( $services as $service ) {
					$this->upsert_availability( $table_name, $date, $service->id, 'holiday' );
				}
				$current = strtotime( '+1 day', $current );
				continue;
			}

			// 3. Check Availability per Service
			foreach ( $services as $service ) {
				$slots = $clustering->get_available_slots( $date, $service->id );
				
				// Check Max Bookings
				$booking_count = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}osb_appointments 
					 WHERE DATE(start_time) = %s AND status NOT IN ('cancelled', 'rejected')",
					$date
				) );

				$status = 'available';
				if ( $max_bookings > 0 && $booking_count >= $max_bookings ) {
					$status = 'booked';
				} elseif ( empty( $slots ) ) {
					$status = 'booked';
				}

				$this->upsert_availability( $table_name, $date, $service->id, $status );
			}
			$current = strtotime( '+1 day', $current );
		}
	}

	private function upsert_availability( $table_name, $date, $service_id, $status ) {
		global $wpdb;
		$is_fully_booked = ( $status === 'booked' || $status === 'holiday' || $status === 'closed' ) ? 1 : 0;
		
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $table_name (date, service_id, status, is_fully_booked, last_updated) 
			 VALUES (%s, %d, %s, %d, NOW()) 
			 ON DUPLICATE KEY UPDATE status = VALUES(status), is_fully_booked = VALUES(is_fully_booked), last_updated = NOW()",
			$date, $service_id, $status, $is_fully_booked
		) );
	}

	private function get_setting( $key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_settings';
		return $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_key = %s", $key ) );
	}

	private function update_sync_token() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_settings';
		$now = date( 'Y-m-d\TH:i:s\Z' ); // UTC
		
		// Check if key exists
		$exists = $wpdb->get_var( "SELECT id FROM $table_name WHERE setting_key = 'gcal_last_sync_token'" );
		
		if ( $exists ) {
			$wpdb->update( $table_name, ['setting_value' => $now], ['setting_key' => 'gcal_last_sync_token'] );
		} else {
			$wpdb->insert( $table_name, ['setting_key' => 'gcal_last_sync_token', 'setting_value' => $now] );
		}
	}
}
