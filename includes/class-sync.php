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
