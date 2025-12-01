<?php

class Ocean_Shiatsu_Booking_Clustering {

	private $gcal;

	public function __construct() {
		$this->gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
	}

	/**
	 * Get available start times for a specific date and service duration.
	 * 
	 * @param string $date 'YYYY-MM-DD'
	 * @param int $service_id
	 * @return array List of available start times (e.g., ['09:00', '10:15'])
	 */
	public function get_available_slots( $date, $service_id ) {
		global $wpdb;

		// 1. Fetch Service Details (Duration + Prep)
		$service = $wpdb->get_row( "SELECT duration_minutes, preparation_minutes FROM {$wpdb->prefix}osb_services WHERE id = $service_id" );
		if ( ! $service ) return [];

		$duration = intval( $service->duration_minutes );
		$prep = intval( $service->preparation_minutes );
		$total_duration = $duration + $prep;

		// 2. Fetch Settings (Business Hours & Days)
		$settings_table = $wpdb->prefix . 'osb_settings';
		$working_start = $this->get_setting( 'working_start' ) ?: '09:00';
		$working_end = $this->get_setting( 'working_end' ) ?: '18:00';
		$working_days = json_decode( $this->get_setting( 'working_days' ), true ) ?: ['1','2','3','4','5'];

		// Check if Date is a Working Day
		$day_of_week = date( 'N', strtotime( $date ) ); // 1 (Mon) - 7 (Sun)
		if ( ! in_array( (string)$day_of_week, $working_days ) ) {
			return []; // Closed
		}

		// 3. Fetch Busy Slots (Local + GCal)
		$busy_slots = $this->get_busy_slots( $date );

		// 4. Fetch Anchor Times from DB
		$anchor_times = $this->get_anchor_times();

		// 5. Calculate Potential Start Times
		$potential_starts = [];

		// Rule A: Anchor Times
		foreach ( $anchor_times as $anchor ) {
			$potential_starts[] = $anchor;
		}

		// Rule B: Adjacent to Existing Bookings (Start immediately after an end time + prep of previous?)
		// Actually, we just need to fit AFTER the previous booking.
		// If previous booking ends at 10:00, we can start at 10:00.
		foreach ( $busy_slots as $slot ) {
			// $slot['end'] is 'HH:MM'
			$potential_starts[] = date( 'H:i', strtotime( $slot['end'] ) );
		}

		// Rule C: Adjacent to Existing Bookings (End immediately before a start time)
		// We want our (Duration + Prep) to end exactly at $slot['start']
		// So Start = SlotStart - TotalDuration
		foreach ( $busy_slots as $slot ) {
			$start_timestamp = strtotime( $date . ' ' . $slot['start'] );
			$potential_start_timestamp = $start_timestamp - ( $total_duration * 60 );
			$potential_starts[] = date( 'H:i', $potential_start_timestamp );
		}

		// Remove duplicates and sort
		$potential_starts = array_unique( $potential_starts );
		sort( $potential_starts );

		// 6. Validate each potential start time
		$valid_slots = [];
		foreach ( $potential_starts as $start_time ) {
			if ( $this->is_slot_valid( $start_time, $total_duration, $date, $working_start, $working_end, $busy_slots ) ) {
				$valid_slots[] = $start_time;
			}
		}

		return array_values( $valid_slots );
	}

	private function get_busy_slots( $date ) {
		global $wpdb;
		$busy = [];

		// 1. Fetch Google Calendar Events (The "Source of Truth")
		$gcal_events = $this->gcal->get_events_for_date( $date );
		$gcal_active = ( $gcal_events !== false );

		if ( $gcal_active ) {
			// GCal is active: Use its events
			foreach ( $gcal_events as $event ) {
				$busy[] = [
					'start' => date( 'H:i', strtotime( $event['start']['dateTime'] ) ),
					'end'   => date( 'H:i', strtotime( $event['end']['dateTime'] ) ),
				];
			}
		}

		// 2. Fetch Local Appointments
		$table_name = $wpdb->prefix . 'osb_appointments';
		// We need to fetch proposed times too if status is admin_proposal
		$local_appts = $wpdb->get_results( $wpdb->prepare(
			"SELECT start_time, end_time, proposed_start_time, proposed_end_time, status, gcal_event_id FROM $table_name 
			WHERE (start_time >= %s AND start_time <= %s)
			OR (proposed_start_time >= %s AND proposed_start_time <= %s AND status = 'admin_proposal')
			AND status NOT IN ('cancelled', 'rejected')",
			$date . ' 00:00:00',
			$date . ' 23:59:59',
			$date . ' 00:00:00',
			$date . ' 23:59:59'
		) );

		foreach ( $local_appts as $appt ) {
			// LOGIC: GCal Always Wins
			if ( $gcal_active && ! empty( $appt->gcal_event_id ) ) {
				continue;
			}

			// Block Original Time (if not cancelled/rejected - already filtered by SQL)
			if ( $appt->start_time ) {
				$busy[] = [
					'start' => date( 'H:i', strtotime( $appt->start_time ) ),
					'end'   => date( 'H:i', strtotime( $appt->end_time ) ),
				];
			}

			// Block Proposed Time (if admin_proposal)
			if ( $appt->status === 'admin_proposal' && $appt->proposed_start_time ) {
				$busy[] = [
					'start' => date( 'H:i', strtotime( $appt->proposed_start_time ) ),
					'end'   => date( 'H:i', strtotime( $appt->proposed_end_time ) ),
				];
			}
		}

		return $busy;
	}

	private function get_anchor_times() {
		$json = $this->get_setting( 'anchor_times' );
		return $json ? json_decode( $json, true ) : ['09:00', '14:00'];
	}

	private function get_setting( $key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_settings';
		return $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_key = %s", $key ) );
	}

	private function is_slot_valid( $start_time, $duration, $date, $biz_start, $biz_end, $busy_slots ) {
		$start_ts = strtotime( $date . ' ' . $start_time );
		$end_ts = $start_ts + ( $duration * 60 );
		$end_time = date( 'H:i', $end_ts );

		// Check Business Hours
		if ( $start_time < $biz_start || $end_time > $biz_end ) {
			return false;
		}

		// Check Overlaps
		foreach ( $busy_slots as $slot ) {
			$busy_start_ts = strtotime( $date . ' ' . $slot['start'] );
			$busy_end_ts = strtotime( $date . ' ' . $slot['end'] );

			// Overlap logic: (StartA < EndB) and (EndA > StartB)
			if ( $start_ts < $busy_end_ts && $end_ts > $busy_start_ts ) {
				return false;
			}
		}

		return true;
	}
}
