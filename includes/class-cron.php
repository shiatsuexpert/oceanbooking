<?php
/**
 * PLUGIN 2.0: Cron Job Handlers
 * 
 * Handles scheduled tasks like sending appointment reminders.
 */

class Ocean_Shiatsu_Booking_Cron {

	/**
	 * Initialize cron handlers.
	 */
	public function init() {
		add_action( 'osb_cron_send_reminders', array( $this, 'process_reminders' ) );
	}

	/**
	 * Process reminders for upcoming appointments.
	 * 
	 * Query Logic: Uses "catch-up" approach to never miss reminders:
	 * - Find appointments where start_time is within 0-25h (for 24h preference)
	 * - Find appointments where start_time is within 0-49h (for 48h preference)
	 * - reminder_sent = 0 (not yet sent)
	 * - status = 'confirmed' (only confirmed appointments)
	 * 
	 * This ensures reminders are sent even if WP-Cron was delayed.
	 */
	public function process_reminders() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_appointments';
		
		// Get current time
		$now = current_time( 'mysql' );
		$now_ts = strtotime( $now );

		// Calculate time windows with catch-up logic
		// 24h: reminder should be sent if appointment is within 0-25 hours
		$window_24h_max = date( 'Y-m-d H:i:s', $now_ts + ( 25 * 3600 ) );
		
		// 48h: reminder should be sent if appointment is within 0-49 hours
		$window_48h_max = date( 'Y-m-d H:i:s', $now_ts + ( 49 * 3600 ) );

		// Query for 24h reminders (catch-up: from now to 25h from now)
		$reminders_24h = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM $table 
			 WHERE status = 'confirmed' 
			 AND reminder_preference = '24h' 
			 AND reminder_sent = 0 
			 AND start_time > %s
			 AND start_time < %s",
			$now,
			$window_24h_max
		) );

		// Query for 48h reminders (catch-up: from now to 49h from now)
		$reminders_48h = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM $table 
			 WHERE status = 'confirmed' 
			 AND reminder_preference = '48h' 
			 AND reminder_sent = 0 
			 AND start_time > %s
			 AND start_time < %s",
			$now,
			$window_48h_max
		) );

		// Merge and deduplicate
		$all_reminders = array_merge( $reminders_24h, $reminders_48h );

		if ( empty( $all_reminders ) ) {
			return; // Nothing to do
		}

		$emails = new Ocean_Shiatsu_Booking_Emails();
		$sent_count = 0;
		$failed_count = 0;

		foreach ( $all_reminders as $reminder ) {
			// FIX: Race condition guard - double-check status before sending
			$current_status = $wpdb->get_var( $wpdb->prepare( 
				"SELECT reminder_sent FROM $table WHERE id = %d", 
				$reminder->id 
			) );
			if ( $current_status == 1 ) {
				continue; // Already sent by another process
			}

			// Send reminder email
			$sent = $emails->send_reminder( $reminder->id );

			// FIX: Only mark as sent if email actually succeeded
			if ( $sent ) {
				$wpdb->update( 
					$table, 
					array( 'reminder_sent' => 1 ), 
					array( 'id' => $reminder->id ) 
				);
				$sent_count++;
			} else {
				$failed_count++;
				if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
					Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'Cron', "wp_mail failed for reminder ID {$reminder->id}" );
				}
			}
		}

		// Log for debugging
		if ( $sent_count > 0 || $failed_count > 0 ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Cron', "Reminders: $sent_count sent, $failed_count failed." );
			}
		}
	}
}
