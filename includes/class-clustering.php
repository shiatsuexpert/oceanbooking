<?php

class Ocean_Shiatsu_Booking_Clustering {

	private $gcal;
	private $last_debug_log = [];

	public function __construct() {
		$this->gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
	}

	public function get_last_debug_log() {
		return $this->last_debug_log;
	}

	/**
	 * Get available start times for a specific date and service duration.
	 * 
	 * NEW ALGORITHM (v1.3.16): Free Window + Sequential Fill with Adjacent Clustering
	 * 
	 * @param string $date 'YYYY-MM-DD'
	 * @param int $service_id
	 * @return array List of available start times (e.g., ['09:00', '10:15'])
	 */
	public function get_available_slots( $date, $service_id, $pre_fetched_events = null ) {
		global $wpdb;
		$is_debug = Ocean_Shiatsu_Booking_Logger::is_debug_enabled();
		
		if ( $is_debug ) {
			$this->last_debug_log = [
				'date' => $date,
				'steps' => [],
				'blockers' => [],
				'windows' => [],
				'candidates' => [],
				'selected' => []
			];
		}

		// 1. Fetch Service Details (Duration + Prep)
		$service = $wpdb->get_row( $wpdb->prepare( "SELECT duration_minutes, preparation_minutes FROM {$wpdb->prefix}osb_services WHERE id = %d", $service_id ) );
		if ( ! $service ) return [];

		$duration = intval( $service->duration_minutes );
		$prep = intval( $service->preparation_minutes );
		$block_seconds = ( $duration + $prep ) * 60;
		$duration_seconds = $duration * 60;
		$prep_seconds = $prep * 60;

		// 2. Check if Date is a Working Day
		$working_days = $this->get_working_days();
		$day_of_week = date( 'N', strtotime( $date ) ); // 1 (Mon) - 7 (Sun)
		if ( ! in_array( (string)$day_of_week, $working_days ) ) {
			if ( $is_debug ) $this->last_debug_log['steps'][] = "Date $date is Closed (Not a working day).";
			return []; // Closed
		}

		// 3. Get Working Hours (with timezone awareness)
		$working_hours = $this->get_working_hours();
		$tz = wp_timezone();
		$biz_start_ts = ( new DateTime( $date . ' ' . $working_hours['start'], $tz ) )->getTimestamp();
		$biz_end_ts = ( new DateTime( $date . ' ' . $working_hours['end'], $tz ) )->getTimestamp();

		// 4. Fetch Busy Slots (Local + GCal) - already includes all-day handling
		// Pass pre_fetched_events to optimize N+1
		$busy_slots = $this->get_busy_slots( $date, $pre_fetched_events );
		if ( $is_debug ) {
			$this->last_debug_log['blockers'] = $busy_slots;
			$this->last_debug_log['steps'][] = "Found " . count($busy_slots) . " blockers.";
		}

		// 4b. Max Bookings Check (v1.4.2)
		// Logic: Count only events from the "Write Calendar" (Working Calendar) or Local Bookings.
		$max_bookings = intval( $this->get_setting( 'max_bookings_per_day' ) );
		if ( $max_bookings > 0 ) {
			$daily_bookings = 0;
			foreach ($busy_slots as $slot) {
				if ( ! empty( $slot['is_booking'] ) ) {
					$daily_bookings++;
				}
			}
			
			if ( $daily_bookings >= $max_bookings ) {
				if ( $is_debug ) $this->last_debug_log['steps'][] = "Max Bookings Reached ($daily_bookings >= $max_bookings). Day Closed.";
				return []; // Day is fully booked
			}
		}

		// 5. Calculate Free Windows (with bidirectional prep buffers)
		// 5. Calculate Free Windows (with bidirectional prep buffers)
		$free_windows = $this->calculate_free_windows( $date, $biz_start_ts, $biz_end_ts, $busy_slots, $prep_seconds );
		if ( $is_debug ) {
			$formatted_windows = array_map(function($w){ 
				return ['start' => date('H:i', $w['start']), 'end' => date('H:i', $w['end'])]; 
			}, $free_windows);
			$this->last_debug_log['windows'] = $formatted_windows;
			$this->last_debug_log['steps'][] = "Calculated " . count($free_windows) . " free windows.";
		}

		// 6. Generate slots for each window (with fill direction based on window type)
		$all_slots = [];
		$window_count = count( $free_windows );

		foreach ( $free_windows as $index => $window ) {
			$is_before_event = isset( $window['is_before_event'] ) && $window['is_before_event'];
			$is_last_window = ( $index === $window_count - 1 ) && ( $window['end'] === $biz_end_ts );

			$window_slots = $this->fill_window_with_slots(
				$window['start'],
				$window['end'],
				$duration_seconds,
				$block_seconds,
				$is_before_event,
				$is_last_window
			);
			$all_slots = array_merge( $all_slots, $window_slots );
		}

		// Convert timestamps to H:i format
		$formatted_slots = array_map( function( $ts ) {
			return wp_date( 'H:i', $ts );
		}, $all_slots );

		if ( $is_debug ) {
			$this->last_debug_log['candidates'] = $formatted_slots;
			$this->last_debug_log['steps'][] = "Generated " . count($formatted_slots) . " candidates.";
		}

		// 7. Apply Smart Slot Presentation (filter for client display)
		// 7. Apply Smart Slot Presentation (filter for client display)
		$has_events = ! empty( $busy_slots );
		$presented_slots = $this->present_slots( array_values( array_unique( $formatted_slots ) ), $date, $has_events );

		if ( $is_debug ) {
			$this->last_debug_log['selected'] = $presented_slots;
			$this->last_debug_log['steps'][] = "Final Selection: " . count($presented_slots) . " slots.";
		}

		return $presented_slots;
	}

	/**
	 * Calculate free time windows between busy slots with bidirectional prep buffers.
	 * 
	 * @param string $date 'YYYY-MM-DD'
	 * @param int $biz_start_ts Business start timestamp
	 * @param int $biz_end_ts Business end timestamp
	 * @param array $busy_slots Array of ['start' => 'HH:MM', 'end' => 'HH:MM']
	 * @param int $prep_seconds Prep time in seconds
	 * @return array Free windows with start/end timestamps and is_before_event flag
	 */
	private function calculate_free_windows( $date, $biz_start_ts, $biz_end_ts, $busy_slots, $prep_seconds ) {
		$tz = wp_timezone();
		$windows = [];
		$current_start = $biz_start_ts;

		// Sort busy slots by start time
		usort( $busy_slots, function( $a, $b ) use ( $date, $tz ) {
			$start_a = ( new DateTime( $date . ' ' . $a['start'], $tz ) )->getTimestamp();
			$start_b = ( new DateTime( $date . ' ' . $b['start'], $tz ) )->getTimestamp();
			return $start_a - $start_b;
		});

		foreach ( $busy_slots as $busy ) {
			$busy_start_ts = ( new DateTime( $date . ' ' . $busy['start'], $tz ) )->getTimestamp();
			$busy_end_ts = ( new DateTime( $date . ' ' . $busy['end'], $tz ) )->getTimestamp();

			// Window BEFORE this event (subtract prep buffer)
			$window_end = $busy_start_ts - $prep_seconds;

			if ( $window_end > $current_start ) {
				$windows[] = [
					'start' => $current_start,
					'end' => $window_end,
					'is_before_event' => true
				];
			}

			// Next window starts AFTER event + prep buffer
			$next_start = $busy_end_ts + $prep_seconds;
			if ( $next_start > $current_start ) {
				$current_start = $next_start;
			}
		}

		// Final window (no prep needed at end of day)
		if ( $current_start < $biz_end_ts ) {
			$windows[] = [
				'start' => $current_start,
				'end' => $biz_end_ts,
				'is_before_event' => false
			];
		}

		return $windows;
	}

	/**
	 * Fill a free window with slots using appropriate fill direction.
	 * 
	 * - Before event: Fill from END (cluster toward event)
	 * - Last window of day: Fill from END (ensure late slots)
	 * - Otherwise: Fill from START
	 * 
	 * @param int $start_ts Window start timestamp
	 * @param int $end_ts Window end timestamp  
	 * @param int $duration_seconds Service duration in seconds
	 * @param int $block_seconds Service + prep time in seconds
	 * @param bool $is_before_event Whether this window is before a busy event
	 * @param bool $is_last_window Whether this is the last window of the day
	 * @return array Slot start timestamps
	 */
	private function fill_window_with_slots( $start_ts, $end_ts, $duration_seconds, $block_seconds, $is_before_event, $is_last_window ) {
		$slots = [];

		// Fill from END for:
		// 1. Windows before events (adjacent clustering)
		// 2. Last window of day (ensure late slots like 17:00)
		if ( $is_before_event || $is_last_window ) {
			// Fill from END
			$current = $end_ts - $duration_seconds;
			while ( $current >= $start_ts ) {
				$slots[] = $current;
				$current -= $block_seconds;
			}
			$slots = array_reverse( $slots ); // Keep chronological order
		} else {
			// Fill from START
			$current = $start_ts;
			while ( ( $current + $duration_seconds ) <= $end_ts ) {
				$slots[] = $current;
				$current += $block_seconds;
			}
		}

		return $slots;
	}

	/**
	 * Present slots to client with smart filtering.
	 * 
	 * - If slots are scarce (<= min_show): Show all
	 * - If day has events: Show up to max_show (already clustered)
	 * - If empty day: Apply variety sampling with edge probability
	 * 
	 * @param array $slots All available slot times (H:i format)
	 * @param string $date 'YYYY-MM-DD'
	 * @param bool $has_events Whether the day has any busy events
	 * @return array Filtered slots to display
	 */
	public function present_slots( $slots, $date, $has_events = false ) {
		$min_show = intval( $this->get_setting( 'slot_min_show' ) ) ?: 3;
		$max_show = intval( $this->get_setting( 'slot_max_show' ) ) ?: 8;
		$percentage = intval( $this->get_setting( 'slot_show_percentage' ) ) ?: 50;
		$edge_prob = intval( $this->get_setting( 'slot_edge_probability' ) ) ?: 70;

		$total = count( $slots );

		// If scarce, show all (don't hide when availability is limited)
		if ( $total <= $min_show ) {
			return $slots;
		}

		if ( $has_events ) {
			// Day with events: Slots are already clustered, take first max_show
			return array_slice( $slots, 0, $max_show );
		} else {
			// Empty day: Apply variety sampling
			$sample_count = max( $min_show, min( $max_show, round( $total * $percentage / 100 ) ) );
			return $this->sample_slots_with_variety( $slots, $date, $sample_count, $edge_prob );
		}
	}

	/**
	 * Sample slots with variety for empty days.
	 * Uses deterministic random (same day = same result) with weighted edge probability.
	 * 
	 * @param array $slots All available slot times (H:i format)
	 * @param string $date 'YYYY-MM-DD' (used as seed for deterministic random)
	 * @param int $show_count Number of slots to return
	 * @param int $edge_prob Probability (0-100) to include first/last slot
	 * @return array Selected slots
	 */
	private function sample_slots_with_variety( $slots, $date, $show_count, $edge_prob ) {
		// Deterministic randomness without global side effects (using hash of date)
		
		// Handle edge cases
		if ( count( $slots ) <= 1 ) return $slots;
		if ( count( $slots ) <= $show_count ) return $slots;

		$first_slot = reset( $slots );
		$last_slot = end( $slots );
		$middle_slots = array_slice( $slots, 1, -1 );

		$result = [];

		// Weighted probability for first slot (deterministic)
		// Use specific hash suffix to ensure different results for different checks
		$first_hash = hexdec( substr( md5( $date . 'first' ), 0, 4 ) ); // 0-65535
		$first_prob = ( $first_hash / 65535 ) * 100;
		if ( $first_prob <= $edge_prob ) {
			$result[] = $first_slot;
		}

		// Weighted probability for last slot
		$last_hash = hexdec( substr( md5( $date . 'last' ), 0, 4 ) );
		$last_prob = ( $last_hash / 65535 ) * 100;
		if ( $last_prob <= $edge_prob && ! in_array( $last_slot, $result ) ) {
			$result[] = $last_slot;
		}

		// Fill remaining from middle + any unused edge slots
		$remaining_count = $show_count - count( $result );
		$pool = $middle_slots;
		if ( ! in_array( $first_slot, $result ) ) $pool[] = $first_slot;
		if ( ! in_array( $last_slot, $result ) ) $pool[] = $last_slot;

		// Deterministic shuffle
		$seed = $date;
		usort( $pool, function( $a, $b ) use ( $seed ) {
			return strcmp( md5( $seed . $a ), md5( $seed . $b ) );
		} );

		$result = array_merge( $result, array_slice( $pool, 0, $remaining_count ) );

		// Sort chronologically and return
		sort( $result );
		return array_slice( $result, 0, $show_count );
	}

	private function get_busy_slots( $date, $pre_fetched_events = null ) {
		global $wpdb;
		$busy = [];
		$is_debug = is_user_logged_in();

		if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "Fetching busy slots for $date" );

		// 1. Fetch Google Calendar Events (The "Source of Truth")
		// OPTIMIZATION: Use pre-fetched events if available (Sync Job N+1 Fix)
		if ( is_array( $pre_fetched_events ) ) {
			$gcal_events = $pre_fetched_events;
			$gcal_active = true;
		} else {
			// Fallback to daily fetch (Frontend click)
			$gcal_events = $this->gcal->get_events_for_date( $date );
			$gcal_active = ( $gcal_events !== false );
		}

		if ( $gcal_active ) {
			if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "Google Calendar is active. Processing GCal events." );
			
			// Get Write Calendar ID for "Booking" classification
			$write_calendar_id = $this->get_setting('gcal_write_calendar');

			// GCal is active: Use its events
			foreach ( $gcal_events as $event ) {
				$is_booking = false;

				// Check if event is from Write Calendar
				if ( $write_calendar_id && isset( $event['calendar_id'] ) && $event['calendar_id'] === $write_calendar_id ) {
					$is_booking = true;
				}

				// Check if it's an all-day event (no dateTime)
				if ( ! isset( $event['start']['dateTime'] ) ) {
					// All-day event: Block entire day? 
					// For now, let's assume all-day events block the whole day if they are marked as 'busy' in GCal (which we don't check here yet, but we should).
					// Or we can just skip them if they are just "Holidays" which are handled in calculate_monthly_availability.
					// But if it's a specific "Blocker" event, we should probably respect it.
					// Let's block 00:00 to 23:59 for now to be safe.
					$busy[] = [
						'start' => '00:00',
						'end'   => '23:59',
						'reason' => 'All-Day Event / Holiday',
						'is_booking' => $is_booking
					];
					if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "GCal All-Day Event: Blocking full day" );
					continue;
				}

				// 🔧 FIX: Use DateTime with wp_timezone() for accurate conversion
				$tz = wp_timezone();
				$event_start = new DateTime( $event['start']['dateTime'] );
				$event_start->setTimezone( $tz );
				$event_end = new DateTime( $event['end']['dateTime'] );
				$event_end->setTimezone( $tz );
				
				$start_gcal = $event_start->format( 'H:i' );
				$end_gcal = $event_end->format( 'H:i' );
				$busy[] = [
					'start' => $start_gcal,
					'end'   => $end_gcal,
					'reason' => 'GCal Event: ' . ( isset($event['summary']) ? $event['summary'] : 'Busy' ),
					'is_booking' => $is_booking
				];
				if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "GCal Event: $start_gcal - $end_gcal" );
			}
		} else {
			if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "Google Calendar is not active or no events found." );
		}

		// 2. Fetch Local Appointments
		$table_name = $wpdb->prefix . 'osb_appointments';
		// We need to fetch proposed times too if status is admin_proposal
		$local_appts = $wpdb->get_results( $wpdb->prepare(
			"SELECT start_time, end_time, proposed_start_time, proposed_end_time, status, gcal_event_id FROM $table_name 
			WHERE (
				(start_time >= %s AND start_time <= %s)
				OR (proposed_start_time >= %s AND proposed_start_time <= %s AND status = 'admin_proposal')
			)
			AND status NOT IN ('cancelled', 'rejected')",
			$date . ' 00:00:00',
			$date . ' 23:59:59',
			$date . ' 00:00:00',
			$date . ' 23:59:59'
		) );

		if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "Found " . count($local_appts) . " local appointments." );

		foreach ( $local_appts as $appt ) {
			// LOGIC: GCal Always Wins
			if ( $gcal_active && ! empty( $appt->gcal_event_id ) ) {
				if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "Skipping local appt (ID: {$appt->gcal_event_id}) because GCal is active and it has a GCal event ID." );
				continue;
			}

			// Block Original Time (if not cancelled/rejected - already filtered by SQL)
			if ( $appt->start_time ) {
				$start_local = date( 'H:i', strtotime( $appt->start_time ) );
				$end_local = date( 'H:i', strtotime( $appt->end_time ) );
				$busy[] = [
					'start' => $start_local,
					'end'   => $end_local,
					'reason' => 'Local Booking: ' . $appt->status,
					'is_booking' => true // Local bookings always count
				];
				if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "Local Appt (Original): $start_local - $end_local (Status: {$appt->status})" );
			}

			// Block Proposed Time (if admin_proposal)
			if ( $appt->status === 'admin_proposal' && $appt->proposed_start_time ) {
				$start_proposed = date( 'H:i', strtotime( $appt->proposed_start_time ) );
				$end_proposed = date( 'H:i', strtotime( $appt->proposed_end_time ) );
				$busy[] = [
					'start' => $start_proposed,
					'end'   => $end_proposed,
					'reason' => 'Admin Proposal',
					'is_booking' => true // Pending proposals count too
				];
				if ( $is_debug ) Ocean_Shiatsu_Booking_Logger::log( 'DEBUG', 'Clustering', "Local Appt (Proposed): $start_proposed - $end_proposed (Status: {$appt->status})" );
			}
		}

		return $busy;
	}



	private function get_working_days() {
		return json_decode( $this->get_setting( 'working_days' ), true ) ?: ['1','2','3','4','5'];
	}

	private function get_working_hours() {
		return [
			'start' => $this->get_setting( 'working_start' ) ?: '09:00',
			'end'   => $this->get_setting( 'working_end' ) ?: '18:00',
		];
	}

	private function get_setting( $key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_settings';
		return $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_key = %s", $key ) );
	}


}
