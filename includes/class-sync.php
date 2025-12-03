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

	public function calculate_monthly_availability( $month ) {
		// $month format: YYYY-MM
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_availability_index';
		
		$start_date = $month . '-01';
		$end_date = date( 'Y-m-t', strtotime( $start_date ) );
		
		$services = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}osb_services" );
		$clustering = new Ocean_Shiatsu_Booking_Clustering();

		$current = strtotime( $start_date );
		$end = strtotime( $end_date );

		while ( $current <= $end ) {
			$date = date( 'Y-m-d', $current );
			
			foreach ( $services as $service ) {
				// This is heavy if done for 30 days * N services.
				// But it runs in background via Cron or Webhook.
				// Optimization: get_available_slots already caches GCal events for the day?
				// Actually get_available_slots calls get_events_for_date which caches for 60s.
				// But here we are looping 30 days.
				// We should pre-fetch GCal events for the whole month range ONCE.
				// But Clustering class doesn't support range injection yet.
				// For now, let's rely on the fact that get_available_slots works.
				// It might be slow (30 API calls if no cache).
				// Wait! get_available_slots calls $gcal->get_events_for_date($date).
				// We should optimize GCal class to support range caching or pre-fetching.
				// But for now, let's implement the logic.
				
				$slots = $clustering->get_available_slots( $date, $service->id );
				$is_fully_booked = empty( $slots ) ? 1 : 0;

				// Upsert
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO $table_name (date, service_id, is_fully_booked, last_updated) 
					 VALUES (%s, %d, %d, NOW()) 
					 ON DUPLICATE KEY UPDATE is_fully_booked = VALUES(is_fully_booked), last_updated = NOW()",
					$date, $service->id, $is_fully_booked
				) );
			}
			$current = strtotime( '+1 day', $current );
		}
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
