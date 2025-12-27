<?php

class Ocean_Shiatsu_Booking_API {

	public function register_routes() {
		register_rest_route( 'osb/v1', '/availability', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_availability' ),
			'permission_callback' => '__return_true', // Public endpoint
		) );

		register_rest_route( 'osb/v1', '/booking', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'create_booking' ),
			'permission_callback' => array( $this, 'verify_nonce' ),
		) );

		register_rest_route( 'osb/v1', '/action', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'handle_action' ),
			'permission_callback' => '__return_true', // Token protected
		) );

		register_rest_route( 'osb/v1', '/booking-by-token', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_booking_by_token' ),
			'permission_callback' => '__return_true', // Token protected
		) );

		register_rest_route( 'osb/v1', '/reschedule', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'request_reschedule' ),
			'permission_callback' => array( $this, 'verify_nonce' ),
		) );

		register_rest_route( 'osb/v1', '/cancel', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'cancel_booking' ),
			'permission_callback' => array( $this, 'verify_nonce' ),
		) );

		register_rest_route( 'osb/v1', '/respond-proposal', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'respond_proposal' ),
			'permission_callback' => array( $this, 'verify_nonce' ),
		) );

		register_rest_route( 'osb/v1', '/gcal-webhook', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'handle_webhook' ),
			'permission_callback' => '__return_true', // Verified by X-Goog headers
		) );

		// v2.4.1: ICS Calendar Download Endpoint
		register_rest_route( 'osb/v1', '/calendar/(?P<token>[a-f0-9]+)', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'serve_ics_calendar' ),
			'permission_callback' => '__return_true', // Token protected
		) );

		register_rest_route( 'osb/v1', '/availability/month', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_monthly_availability' ),
			'permission_callback' => '__return_true', // Public
		) );

		register_rest_route( 'osb/v1', '/validate-slot', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'validate_slot' ),
			'permission_callback' => '__return_true', // Public
		) );

		register_rest_route( 'osb/v1', '/config', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_config' ),
			'permission_callback' => '__return_true', // Public
		) );
	}

	public function verify_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public function respond_proposal( $request ) {
		$params = $request->get_json_params();
		$token = $params['token'];
		$response = $params['response']; // 'accept' or 'decline'

		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE token = %s", $token ) );

		if ( ! $booking ) return new WP_Error( 'invalid_token', 'Invalid token', array( 'status' => 403 ) );

		if ( $response === 'accept' ) {
			// v2.3.0 SECURITY: Availability Check (Race Condition Guard)
			// Ensure the proposed slot is still free before confirming
			
			$date = date( 'Y-m-d', strtotime( $booking->proposed_start_time ) );
			$time = date( 'H:i', strtotime( $booking->proposed_start_time ) );
			$service_id = $booking->service_id;

			$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
			$clustering = new Ocean_Shiatsu_Booking_Clustering();
			
			// Force Live Fetch (true for skip_cache) to ensure real-time accuracy
			$day_events = $gcal->get_events_for_date( $date, true ); 
			$available_slots = $clustering->get_available_slots( $date, $service_id, $day_events );

			if ( ! in_array( $time, $available_slots ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'API', 'Proposal Acceptance Blocked: Slot taken', ['time' => $time] );
				return new WP_Error( 'conflict', 'Dieser Termin ist leider nicht mehr verfügbar (wurde zwischenzeitlich vergeben).', array( 'status' => 409 ) );
			}

			// Proceed with Update
			$wpdb->update( 
				$table_name, 
				[
					'start_time' => $booking->proposed_start_time,
					'end_time' => $booking->proposed_end_time,
					'status' => 'confirmed',
					'proposed_start_time' => NULL,
					'proposed_end_time' => NULL
				], 
				['id' => $booking->id] 
			);
			
			// Updates GCal Event if exists
			if ( $booking->gcal_event_id ) {
				// Calculate Duration
				$start_ts = strtotime( $booking->proposed_start_time );
				$end_ts = strtotime( $booking->proposed_end_time );
				$duration = ( $end_ts - $start_ts ) / 60;

				$new_date = date( 'Y-m-d', $start_ts );
				$new_time = date( 'H:i', $start_ts );

				// LOOP PREVENTION: Set ignore transient before updating GCal
				set_transient( 'osb_ignore_sync_' . $booking->id, true, 60 );

				$gcal->update_event_time( $booking->gcal_event_id, $new_date, $new_time, $duration );
				// Also explicit status confirm in case it was pending
				$gcal->update_event_status( $booking->gcal_event_id, 'confirmed' );
			}

			// Notify Admin
			$emails = new Ocean_Shiatsu_Booking_Emails();
			$emails->send_admin_proposal_accepted( $booking->id );
			
			// Notify Client (Confirmation)
			$emails->send_client_confirmation( $booking->id );

		} else {
			// Decline
			// Just clear the proposed times and notify admin. 
			// Status remains as is (likely pending/reschedule_requested) or reverts to pending?
			// Ideally logic flows to Reschedule UI, so backend just acknowledges the rejection of the proposal.
			$wpdb->update( 
				$table_name, 
				[
					'status' => 'pending', 
					'proposed_start_time' => NULL,
					'proposed_end_time' => NULL
				], 
				['id' => $booking->id] 
			);
			// Notify Admin
			$emails = new Ocean_Shiatsu_Booking_Emails();
			$emails->send_admin_proposal_declined( $booking->id );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_availability( $request ) {
		$date = $request->get_param( 'date' );
		$start_date = $request->get_param( 'start_date' );
		$end_date = $request->get_param( 'end_date' );
		$service_id = $request->get_param( 'service_id' );

		if ( ( ! $date && ( ! $start_date || ! $end_date ) ) || ! $service_id ) {
			return new WP_Error( 'missing_params', 'Date (or start/end date) and Service ID are required', array( 'status' => 400 ) );
		}

		$start_time = microtime( true );
		$is_debug = Ocean_Shiatsu_Booking_Logger::is_debug_enabled();
		$debug_metrics = [ 'db' => 0, 'gcal' => 0, 'logic' => 0 ];
		$source = 'cache_hit';

		// Get duration from DB
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_services';
		$duration = $wpdb->get_var( $wpdb->prepare( "SELECT duration_minutes FROM $table_name WHERE id = %d", $service_id ) );

		if ( ! $duration ) {
			return new WP_Error( 'invalid_service', 'Service not found', array( 'status' => 404 ) );
		}

		$clustering = new Ocean_Shiatsu_Booking_Clustering();
		
		// Handle Range Request - NEW: Returns structured { date: { status, slots } }
		if ( $start_date && $end_date ) {
			// Cache-salting: Include version for instant invalidation
			$cache_version = get_option( 'osb_cache_version', '0' );
			$cache_key = 'osb_avail_range_' . md5( $start_date . $end_date . $service_id ) . '_' . $cache_version;
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return rest_ensure_response( $cached );
			}

			$result = [];
			$only_cache = false;
			$pre_fetched_range = null;

			// Try live GCal fetch with 3-second timeout
			try {
				$t0 = microtime(true);
				$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
				$pre_fetched_range = $gcal->get_events_range( $start_date, $end_date, ['timeout' => 3] );
				$debug_metrics['gcal'] = microtime(true) - $t0;
				$source = 'live_gcal';
			} catch ( Exception $e ) {
				// Timeout or error - switch to cache-only mode (fail-safe)
				Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'API', 'GCal timeout, using cached events', ['error' => $e->getMessage()] );
				$only_cache = true;
				$source = 'cache_fallback';
			}

			$current = strtotime( $start_date );
			$end = strtotime( $end_date );

			while ( $current <= $end ) {
				$d = date( 'Y-m-d', $current );
				
				// Calculate availability with status
				$day_result = $this->calculate_day_availability( $d, $service_id, $pre_fetched_range, $only_cache, $clustering );
				$result[ $d ] = $day_result;
				
				$current = strtotime( '+1 day', $current );
			}

			// Cache result - use shorter TTL if fallback mode (prevents caching degraded data too long)
			$cache_ttl = $only_cache ? 60 : 30 * 60; // 1 min for fallback, 30 min normal
			set_transient( $cache_key, $result, $cache_ttl );
			
			$response = rest_ensure_response( $result );
			if ( $is_debug ) {
				$total_dur = microtime(true) - $start_time;
				$debug_metrics['logic'] = $total_dur - ($debug_metrics['gcal'] ?? 0);
				$response->header( 'Server-Timing', "total;dur=" . ($total_dur*1000) . ", gcal;dur=" . (($debug_metrics['gcal'] ?? 0)*1000) );
				
				$data = $response->get_data();
				$data['debug'] = [
					'source' => $source,
					'only_cache' => $only_cache,
					'timestamp' => current_time('mysql'),
					'metrics' => $debug_metrics,
					'logs' => $clustering->get_last_debug_log()
				];
				$response->set_data($data);
			}
			return $response;
		}

		// Handle Single Date Request
		$slots = $clustering->get_available_slots( $date, $service_id );
		
		// Null safety: ensure array response
		if ( $slots === null || $slots === false ) {
			$slots = [];
		}
		
		$response = rest_ensure_response( $slots );
		if ( $is_debug ) {
			$total_dur = microtime(true) - $start_time;
			$response->header( 'Server-Timing', "total;dur=" . ($total_dur*1000) );
			
			// For single date, transform response to object to attach debug
			// Note: This changes response structure from Array to Object!
			// Frontend must handle if structure changes OR we inject into headers/meta?
			// PROPOSAL: If debug, wrap. Booking app assumes array of strings.
			// WRAPPER: { slots: [...], debug: {...} } OR attach to response object but client receives body.
			// Client expects ARRAY. Changing to Object breaks existing JS.
			// But JS plan says: "Update fetchSlots ... to check for response.debug"
			// JS 'fetchSlots' does `data = await response.json()`. If it's array, `data.debug` is undefined.
			// If I change to `{ slots: [], debug: {} }`, I break compatibility unless JS handles it.
			// "Update booking-app.js to parse this metadata"
			// I WILL WRITE THE JS CHANGE NEXT. So I can change the structure here.
			
			$response_data = [
				'slots' => $slots,
				'debug' => [
					'source' => 'single_day_fetch', // Could be cache hit or not inside clustering
					'timestamp' => current_time('mysql'),
					'calculation_log' => $clustering->get_last_debug_log(),
					'server_config' => [
						'working_days' => $clustering->get_working_days(), // Expose this!
						'day_of_week' => date('N', strtotime($date))
					]
				]
			];
			$response->set_data( $response_data );
		}
		return $response;
	}

	public function create_booking( $request ) {
		// Rate Limit Check
		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'API', 'Rate Limit Exceeded', ['ip' => $_SERVER['REMOTE_ADDR']] );
			return $rate_limit;
		}

		$params = $request->get_json_params();
		$log_params = $params;
		// Mask PII
		if ( isset( $log_params['client_email'] ) ) $log_params['client_email'] = '***@***.com';
		if ( isset( $log_params['client_phone'] ) ) $log_params['client_phone'] = '***-***-****';
		if ( isset( $log_params['client_name'] ) ) $log_params['client_name'] = '*** ***';
		Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'API', 'Create Booking Request', $log_params );

		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';

		// 1. Input Validation
		if ( empty( $params['service_id'] ) || empty( $params['date'] ) || empty( $params['time'] ) ) {
			return new WP_Error( 'missing_params', 'Missing required fields', array( 'status' => 400 ) );
		}

		// Validate Service & Duration
		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $params['service_id'] ) );
		if ( ! $service ) {
			return new WP_Error( 'invalid_service', 'Invalid Service ID', array( 'status' => 400 ) );
		}
		
		// Security: Use authoritative duration from service, NOT user input
		$duration = intval( $service->duration_minutes );
		if ( $duration <= 0 ) {
			return new WP_Error( 'invalid_service', 'Service has invalid duration', array( 'status' => 400 ) );
		}

		// Validate Date
		if ( strtotime( $params['date'] ) < strtotime( date('Y-m-d') ) ) {
			return new WP_Error( 'invalid_date', 'Cannot book in the past', array( 'status' => 400 ) );
		}

		// PLUGIN 2.0: Determine booking type (default to 'booking' for V2 compatibility)
		$booking_type = isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : 'booking';
		$is_waitlist = ( $booking_type === 'waitlist' );

		// PLUGIN 2.0: Waitlist-specific validation
		if ( $is_waitlist ) {
			// Validate wait time range
			$wait_from = isset( $params['wait_time_from'] ) ? sanitize_text_field( $params['wait_time_from'] ) : null;
			$wait_to = isset( $params['wait_time_to'] ) ? sanitize_text_field( $params['wait_time_to'] ) : null;
			
			if ( ! $wait_from || ! $wait_to ) {
				return new WP_Error( 'missing_wait_time', 'Waitlist requires time range', array( 'status' => 400 ) );
			}
			
			// Strict time format validation (H:i)
			$wait_from_dt = DateTime::createFromFormat( 'H:i', $wait_from );
			$wait_to_dt = DateTime::createFromFormat( 'H:i', $wait_to );
			if ( ! $wait_from_dt || ! $wait_to_dt ) {
				return new WP_Error( 'invalid_time_format', 'Time must be in HH:MM format', array( 'status' => 400 ) );
			}
			
			if ( $wait_from_dt >= $wait_to_dt ) {
				return new WP_Error( 'invalid_wait_time', 'Start time must be before end time', array( 'status' => 400 ) );
			}

			// Anti-spam: Check for duplicate waitlist entry
			$existing_waitlist = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table_name 
				 WHERE client_email = %s 
				 AND DATE(start_time) = %s 
				 AND status = 'waitlist'",
				sanitize_email( $params['client_email'] ),
				$params['date']
			) );

			if ( $existing_waitlist ) {
				return new WP_Error( 'duplicate_waitlist', 'Sie sind bereits auf der Warteliste für diesen Tag.', array( 'status' => 409 ) );
			}
		}

		// 1.5 CRITICAL: Live GCal Availability Check (Race Condition Guard)
		// SKIP for waitlist requests - they are for UNAVAILABLE slots by definition
		if ( ! $is_waitlist ) {
			$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
			$clustering = new Ocean_Shiatsu_Booking_Clustering();
			
			// Force Live Fetch (true for skip_cache)
			$day_events = $gcal->get_events_for_date( $params['date'], true ); 
			$available_slots = $clustering->get_available_slots( $params['date'], $params['service_id'], $day_events );

			if ( ! in_array( $params['time'], $available_slots ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'API', 'Race Condition Prevented: Slot taken in GCal', ['time' => $params['time']] );
				return new WP_Error( 'conflict', 'Dieser Termin ist leider nicht mehr verfügbar (wurde gerade vergeben).', array( 'status' => 409 ) );
			}
		}

		// PLUGIN 2.0: Prepare client_id (will be set after successful insert)
		$client_id = null;

		// 2. Concurrency Check (Locking) - SKIP for waitlist
		if ( ! $is_waitlist ) {
			$wpdb->query( "LOCK TABLES $table_name WRITE" );
		}

		$start_time = $params['date'] . ' ' . $params['time'];
		$end_time = date('Y-m-d H:i:s', strtotime($start_time) + ($duration * 60));

		// Check for Local Overlaps - SKIP for waitlist
		if ( ! $is_waitlist ) {
			$overlap = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table_name 
				 WHERE status NOT IN ('cancelled', 'rejected', 'waitlist')
				 AND start_time < %s AND end_time > %s",
				$end_time,
				$start_time
			) );

			if ( $overlap ) {
				$wpdb->query( "UNLOCK TABLES" );
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'API', 'Double Booking Prevented (Local Lock)', ['start' => $start_time] );
				return new WP_Error( 'conflict', 'Dieser Termin ist bereits vergeben.', array( 'status' => 409 ) );
			}
		}

		// 3. Insert Appointment
		$token = bin2hex( random_bytes( 32 ) );
		$admin_token = bin2hex( random_bytes( 32 ) );

		// Determine status based on type
		$initial_status = $is_waitlist ? 'waitlist' : 'pending';

		// PLUGIN 2.0: Get language and reminder preference
		$language = isset( $params['language'] ) ? sanitize_text_field( $params['language'] ) : 'de';
		$reminder_preference = isset( $params['reminder_preference'] ) ? sanitize_text_field( $params['reminder_preference'] ) : 'none';

		$insert_data = array(
			'client_id' => $client_id,
			'service_id' => $params['service_id'],
			'client_name' => sanitize_text_field( $params['client_name'] ),
			'client_salutation' => isset($params['client_salutation']) ? sanitize_text_field( $params['client_salutation'] ) : '',
			'client_first_name' => isset($params['client_first_name']) ? sanitize_text_field( $params['client_first_name'] ) : '',
			'client_last_name' => isset($params['client_last_name']) ? sanitize_text_field( $params['client_last_name'] ) : '',
			'client_email' => sanitize_email( $params['client_email'] ),
			'client_phone' => sanitize_text_field( $params['client_phone'] ),
			'client_notes' => isset($params['client_notes']) ? sanitize_textarea_field( $params['client_notes'] ) : '',
			'start_time' => $start_time,
			'end_time' => $end_time,
			'status' => $initial_status,
			'token' => $token,
			'admin_token' => $admin_token,
			'language' => $language,
			'reminder_preference' => $reminder_preference,
		);

		// Add waitlist-specific fields
		if ( $is_waitlist ) {
			$insert_data['wait_time_from'] = $wait_from;
			$insert_data['wait_time_to'] = $wait_to;
		}

		$inserted = $wpdb->insert( $table_name, $insert_data );

		if ( ! $is_waitlist ) {
			$wpdb->query( "UNLOCK TABLES" );
		}

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', 'Could not save booking', array( 'status' => 500 ) );
		}

		$booking_id = $wpdb->insert_id;

		// PLUGIN 2.0: Upsert Client AFTER successful insert (prevents zombie records)
		// Only increment stats for non-waitlist bookings (waitlist shouldn't count as booking)
		$should_increment_stats = ! $is_waitlist;
		$client_id = $this->upsert_client(
			sanitize_email( $params['client_email'] ),
			isset( $params['client_salutation'] ) ? sanitize_text_field( $params['client_salutation'] ) : 'n',
			isset( $params['client_first_name'] ) ? sanitize_text_field( $params['client_first_name'] ) : '',
			isset( $params['client_last_name'] ) ? sanitize_text_field( $params['client_last_name'] ) : '',
			sanitize_text_field( $params['client_phone'] ),
			isset( $params['newsletter'] ) ? 1 : 0,
			$should_increment_stats
		);

		// Update appointment with client_id
		if ( $client_id ) {
			$wpdb->update( $table_name, array( 'client_id' => $client_id ), array( 'id' => $booking_id ) );
		}

		// PLUGIN 2.0: Handle Waitlist separately (no GCal, different email)
		if ( $is_waitlist ) {
			$emails = new Ocean_Shiatsu_Booking_Emails();
			
			// Send waitlist notification to admin
			if ( method_exists( $emails, 'send_admin_waitlist' ) ) {
				$emails->send_admin_waitlist( $booking_id, $params );
			} else {
				// Fallback: Use standard admin request
				$params['type'] = 'waitlist';
				$params['service_name'] = $service->name;
				$params['admin_token'] = $admin_token;
				$emails->send_admin_request( $booking_id, $params );
			}

			return rest_ensure_response( array( 
				'success' => true, 
				'id' => $booking_id,
				'confirmation_type' => 'waitlist_submitted',
				'booking_summary' => array(
					'service_name' => $service->name,
					'date' => $params['date'],
					'wait_time_from' => $wait_from,
					'wait_time_to' => $wait_to,
					'client_name' => $params['client_name']
				)
			) );
		}

		// 4. Auto-Confirm Logic & Sync (normal bookings only)
		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		$auto_confirm = $wpdb->get_var( "SELECT setting_value FROM {$wpdb->prefix}osb_settings WHERE setting_key = 'osb_auto_confirm_bookings'" ) === '1';
		$params['service_name'] = $service->name;
		$params['admin_token'] = $admin_token;
		
		$confirmation_type = 'request_submitted';
		$emails = new Ocean_Shiatsu_Booking_Emails();

		if ( $auto_confirm ) {
			// Try to Create GCal Event IMMEDIATELY
			$event_id = $gcal->create_event( $params );

			if ( $event_id ) {
				// SUCCESS: Confirm it
				$wpdb->update( $table_name, [ 'status' => 'confirmed', 'gcal_event_id' => $event_id ], [ 'id' => $booking_id ] );
				$confirmation_type = 'booking_confirmed';

				// Emails: Client Confirmation + Admin Notification
				$emails->send_client_confirmation( $booking_id );
				if ( method_exists( $emails, 'send_admin_notification_confirmed' ) ) {
					$emails->send_admin_notification_confirmed( $booking_id );
				} else {
					$emails->send_admin_request( $booking_id, $params );
				}

			} else {
				// FAILURE (Option B): Fallback to Pending
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'API', 'Auto-Confirm GCal Sync Failed. Reverting to pending.', ['id' => $booking_id] );
				$confirmation_type = 'gcal_sync_failed';

				// Emails: Send Admin Request (Manual intervention needed)
				$emails->send_admin_request( $booking_id, $params );
			}
		} else {
			// Manual Mode
			$event_id = $gcal->create_event( $params );
			if ( $event_id ) {
				$wpdb->update( $table_name, ['gcal_event_id' => $event_id], ['id' => $booking_id] );
			}
			
			$emails->send_admin_request( $booking_id, $params );
		}

		return rest_ensure_response( array( 
			'success' => true, 
			'id' => $booking_id,
			'confirmation_type' => $confirmation_type,
			'booking_summary' => array(
				'service_name' => $service->name,
				'date' => $params['date'],
				'time' => $params['time'],
				'client_name' => $params['client_name']
			)
		) );
	}

	public function get_booking_by_token( $request ) {
		$token = $request->get_param( 'token' );
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';
		
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE token = %s", $token ) );
		
		if ( ! $booking ) {
			return new WP_Error( 'invalid_token', 'Booking not found', array( 'status' => 404 ) );
		}

		// Fetch service name and duration
		// v2.5.0: Localize Service Name
		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $booking->service_id ) );
		$lang = ! empty( $booking->language ) ? $booking->language : 'de';
		$service_name = $service ? $this->get_localized_service_field( $service, 'name', $lang ) : '';

		return rest_ensure_response( [
			'id' => $booking->id,
			'start_time' => $booking->start_time,
			'service_name' => $service_name,
			'duration' => $service ? $service->duration_minutes : 60,
			'status' => $booking->status
		] );
	}

	public function request_reschedule( $request ) {
		// Rate Limit Check
		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) return $rate_limit;

		$params = $request->get_json_params();
		$token = $params['token'];
		
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE token = %s", $token ) );

		if ( ! $booking ) return new WP_Error( 'invalid_token', 'Invalid token', array( 'status' => 403 ) );

		$new_start = $params['date'] . ' ' . $params['time'];
		$new_end = date('Y-m-d H:i:s', strtotime($new_start) + ($params['duration'] * 60));

		$wpdb->update( 
			$table_name, 
			[
				'status' => 'reschedule_requested',
				'proposed_start_time' => $new_start,
				'proposed_end_time' => $new_end
			], 
			['id' => $booking->id] 
		);

		// Notify Admin
		$emails = new Ocean_Shiatsu_Booking_Emails();
		$emails->send_admin_reschedule_request( $booking->id, $new_start );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function cancel_booking( $request ) {
		$params = $request->get_json_params();
		$token = $params['token'];

		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE token = %s", $token ) );

		if ( ! $booking ) return new WP_Error( 'invalid_token', 'Invalid token', array( 'status' => 403 ) );

		$wpdb->update( $table_name, ['status' => 'cancelled'], ['id' => $booking->id] );

		// Delete from GCal
		if ( $booking->gcal_event_id ) {
			$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
			$gcal->delete_event( $booking->gcal_event_id );
		}

		// Notify Admin
		$emails = new Ocean_Shiatsu_Booking_Emails();
		$emails->send_admin_cancellation( $booking->id );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function handle_action( $request ) {
		$token = $request->get_param( 'token' );
		$action = $request->get_param( 'action' );
		$booking_id = $request->get_param( 'id' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';

		$booking = null;

		if ( $booking_id ) {
			// Admin Access (Requires ID Match + Admin Token)
			$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $booking_id ) );
			
			if ( ! $booking ) {
				return new WP_Error( 'not_found', 'Booking not found', array( 'status' => 404 ) );
			}
			
			// Verify Admin Token
			if ( ! hash_equals( $booking->admin_token, $token ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'API', 'Invalid Admin Token Attempt', ['id' => $booking_id] );
				return new WP_Error( 'forbidden', 'Invalid security token', array( 'status' => 403 ) );
			}
		} else {
			// Client Access (Requires Token Match)
			// Used for 'cancel' action from email link
			$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE token = %s", $token ) );
			
			if ( ! $booking ) {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'API', 'Invalid Client Token Attempt', ['token' => $token] );
				return new WP_Error( 'not_found', 'Invalid token', array( 'status' => 404 ) );
			}
			
			$booking_id = $booking->id;
		}

		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		$emails = new Ocean_Shiatsu_Booking_Emails();
		
		// SECURITY FIX: Track if this is admin-authenticated request
		// Only admin_token (with booking_id param) should access accept/reject
		$is_admin_auth = (bool) $request->get_param( 'id' );

		if ( $action === 'cancel' ) {
			// Client Cancellation
			if ( $booking->status === 'cancelled' ) {
				return rest_ensure_response( array( 'success' => true, 'message' => 'Bereits storniert' ) );
			}

			// 1. Update DB
			$wpdb->update( $table_name, ['status' => 'cancelled'], ['id' => $booking_id] );
			
			// 2. Remove from GCal
			if ( $booking->gcal_event_id ) {
				$gcal->delete_event( $booking->gcal_event_id );
			}

			// 3. Notify Admin
			$emails->send_admin_cancellation( $booking_id );

			// 4. Return Summary for UI
			// v2.5.0 Localized Service Name
			$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $booking->service_id ) );
			$lang = ! empty( $booking->language ) ? $booking->language : 'de';
			$service_name = $service ? $this->get_localized_service_field( $service, 'name', $lang ) : 'Service';
			
			return rest_ensure_response( array(
				'success' => true,
				'booking_summary' => array(
					'service_name' => $service_name,
					'date' => date('Y-m-d', strtotime($booking->start_time)),
					'time' => date('H:i', strtotime($booking->start_time)),
				)
			));

		} elseif ( $action === 'accept' ) {
			// SECURITY FIX: Only admin-authenticated requests can accept
			if ( ! $is_admin_auth ) {
				return new WP_Error( 'forbidden', 'Admin-only action', array( 'status' => 403 ) );
			}
			
			// Admin Accept
			$wpdb->update( $table_name, ['status' => 'confirmed'], ['id' => $booking_id] );
			
			// Update GCal to remove [PENDING] (if event exists)
			$event_id = $booking->gcal_event_id;
			if ( $event_id ) {
				$gcal->update_event_status( $event_id, 'confirmed' );
			} else {
				// If no event (manual mode?), create one now?
				// Existing code implies update only. Let's stick to update.
			}
			
			// Send Client Confirmation
			$emails->send_client_confirmation( $booking_id );

		} elseif ( $action === 'reject' ) {
			// SECURITY FIX: Only admin-authenticated requests can reject
			if ( ! $is_admin_auth ) {
				return new WP_Error( 'forbidden', 'Admin-only action', array( 'status' => 403 ) );
			}
			
			// Admin Reject
			$wpdb->update( $table_name, ['status' => 'rejected'], ['id' => $booking_id] );
			
			// Delete from GCal
			$event_id = $booking->gcal_event_id;
			if ( $event_id ) {
				$gcal->delete_event( $event_id );
			}

			// Send Client Rejection
			$emails->send_client_rejection( $booking_id );
		}

		return rest_ensure_response( array( 'success' => true, 'action' => $action ) );
	}

	public function handle_webhook( $request ) {
	$channel_id = $request->get_header( 'X-Goog-Channel-ID' );
	$resource_id = $request->get_header( 'X-Goog-Resource-ID' );
	$resource_state = $request->get_header( 'X-Goog-Resource-State' );
	$token = $request->get_header( 'X-Goog-Channel-Token' );
	$resource_uri = $request->get_header( 'X-Goog-Resource-URI' );
	$message_number = $request->get_header( 'X-Goog-Message-Number' );

	// Log EVERY webhook with full data
	Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Webhook', 'Webhook Received', [
		'state'          => $resource_state, 
		'channel_id'     => $channel_id,
		'resource_id'    => $resource_id,
		'resource_uri'   => $resource_uri,
		'message_number' => $message_number,
		'timestamp'      => current_time( 'mysql' )
	] );

		// 1. Verify Token
		$stored_token = get_option( 'osb_webhook_token' );
		if ( ! $stored_token || ! hash_equals( $stored_token, $token ) ) {
			Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'API', 'Webhook Invalid Token' );
			return new WP_Error( 'forbidden', 'Invalid token', array( 'status' => 403 ) );
		}

		// 2. Acknowledge Sync
		if ( $resource_state === 'sync' ) {
			return rest_ensure_response( array( 'status' => 'ok' ) );
		}

		// 3. Handle Updates
		if ( $resource_state === 'exists' ) {
			// Extract Calendar ID from URI or Channel ID mapping
			$channels = get_option( 'osb_watch_channels', [] );
			$calendar_id = null;

			foreach ( $channels as $cal_id => $channel_data ) {
				if ( $channel_data['channel_id'] === $channel_id && $channel_data['resource_id'] === $resource_id ) {
					$calendar_id = $cal_id;
					break;
				}
			}

			if ( $calendar_id ) {
				Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'API', 'Webhook Triggering Sync', ['calendar' => $calendar_id] );
				// Trigger Sync
				$sync = new Ocean_Shiatsu_Booking_Sync();
				$sync->sync_events(); 
				
				// Update monthly availability index
				$first_of_month = strtotime( date('Y-m-01') );
				$current_month = date('Y-m', $first_of_month);
				$next_month = date('Y-m', strtotime('+1 month', $first_of_month));
				$sync->calculate_monthly_availability( $current_month );
				$sync->calculate_monthly_availability( $next_month );
				
				// Invalidate range cache via cache-salting
				update_option( 'osb_cache_version', time() );
			} else {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'API', 'Webhook Ignored: Unknown Channel', ['channel' => $channel_id] );
			}
		}

		return rest_ensure_response( array( 'status' => 'ok' ) );
	}

	public function get_monthly_availability( $request ) {
		// DEPRECATION WARNING: Use /availability?start_date&end_date instead
		Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'API', 'DEPRECATED: /availability/month - use /availability?start_date&end_date with structured response instead' );
		
		$service_id = $request->get_param( 'service_id' );
		$month = $request->get_param( 'month' ); // YYYY-MM

		if ( ! $service_id || ! $month ) {
			return new WP_Error( 'missing_params', 'Service ID and Month required', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_availability_index';
		
		// Validate month format
		$start_date = $month . '-01';
		$end_date = date( 'Y-m-t', strtotime( $start_date ) );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT date, status FROM $table_name 
			 WHERE service_id = %d AND date BETWEEN %s AND %s",
			$service_id, $start_date, $end_date
		) );

		$availability = [];
		foreach ( $results as $row ) {
			$availability[ $row->date ] = $row->status ?: 'available';
		}

		return rest_ensure_response( $availability );
	}

	private function check_rate_limit() {
		$ip = $_SERVER['REMOTE_ADDR'];
		$key = 'osb_rate_limit_' . md5( $ip );
		$limit = 50; // Requests per hour
		$window = 3600;

		$current = get_transient( $key );
		if ( false === $current ) {
			set_transient( $key, 1, $window );
		} else {
			if ( $current >= $limit ) {
				return new WP_Error( 'rate_limit', 'Too many requests.', array( 'status' => 429 ) );
			}
			set_transient( $key, $current + 1, $window );
		}
		return true;
	}
	public function validate_slot( $request ) {
		$params = $request->get_json_params();
		$date = isset( $params['date'] ) ? $params['date'] : '';
		$time = isset( $params['time'] ) ? $params['time'] : '';
		$service_id = isset( $params['service_id'] ) ? intval( $params['service_id'] ) : 0;

		if ( empty( $date ) || empty( $time ) || empty( $service_id ) ) {
			return new WP_Error( 'missing_params', 'Missing required parameters', array( 'status' => 400 ) );
		}

		// Security: Rate Limit + Nonce?
		// Nonce is not required for public validation if we want it fast?
		// But let's check rate limit at least.
		$limit_check = $this->check_rate_limit();
		if ( is_wp_error( $limit_check ) ) {
			return $limit_check;
		}

		// Check Google Calendar Live (Skip Cache)
		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		$clustering = new Ocean_Shiatsu_Booking_Clustering();

		// Force fresh fetch from Google (Gap 1/5 Safety: Validation must use live data)
		// We use true for skip_cache
		$day_events = $gcal->get_events_for_date( $date, true );

		// Recalculate available slots with fresh data
		$slots = $clustering->get_available_slots( $date, $service_id, $day_events );

		// Check if requested time is still available
		if ( in_array( $time, $slots ) ) {
			return rest_ensure_response( array( 'valid' => true ) );
		} else {
			$error_data = array( 'status' => 409 );
			if ( Ocean_Shiatsu_Booking_Logger::is_debug_enabled() ) {
				$error_data['debug'] = $clustering->get_last_debug_log();
			}
			return new WP_Error( 'conflict', 'Dieser Termin ist leider bereits vergeben. Bitte wähle einen anderen.', $error_data );
		}
	}
	public function get_config( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		
		// Get Write Calendar ID
		$write_calendar = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_write_calendar'" );
		
		// Get Provider Data
		$provider_data_json = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'osb_calendar_providers'" );
		$provider_data = json_decode( $provider_data_json, true ) ?: [];
		
		$provider_info = [
			'name' => '',
			'image' => ''
		];

		if ( $write_calendar && isset( $provider_data[ $write_calendar ] ) ) {
			// SECURITY FIX: Whitelist only public-safe fields (prevent credential leak)
			$p = $provider_data[ $write_calendar ];
			$provider_info = [
				'name'  => isset( $p['name'] ) ? $p['name'] : '',
				'image' => isset( $p['image'] ) ? $p['image'] : '',
			];
		}

		return rest_ensure_response( [
			'provider' => $provider_info,
			'working_start' => $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'working_start'" ),
			'working_end' => $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'working_end'" ),
			'version' => OSB_VERSION
		] );
	}

	/**
	 * PLUGIN 2.0: Upsert Client - Get or Create client record.
	 * Security: Only updates operational fields for existing clients, NOT personal data.
	 * 
	 * @param string $email Client email (unique identifier)
	 * @param string $salutation 'm', 'w', or 'n'
	 * @param string $first First name
	 * @param string $last Last name
	 * @param string $phone Phone number
	 * @param int $newsletter Newsletter opt-in (0 or 1)
	 * @param bool $increment_stats Whether to increment booking_count (false for waitlist)
	 * @return int|null Client ID or null on failure
	 */
	private function upsert_client( $email, $salutation, $first, $last, $phone, $newsletter, $increment_stats = true ) {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_clients';
		
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE email = %s", $email
		) );
		
		if ( $existing ) {
			// UPDATE existing client - ONLY operational fields (security fix from review)
			// Do NOT overwrite name/phone from unauthenticated form
			$update_data = array();

			// Only increment stats for actual bookings, not waitlist
			if ( $increment_stats ) {
				$update_data['booking_count'] = $existing->booking_count + 1;
				$update_data['last_booking_date'] = current_time( 'Y-m-d' );
			}

			// Only update newsletter if it changed from 0 to 1 (opt-in)
			if ( $newsletter && ! $existing->newsletter_opt_in ) {
				$update_data['newsletter_opt_in'] = 1;
				$update_data['newsletter_opt_in_at'] = current_time( 'mysql' );
				$update_data['newsletter_opt_in_ip'] = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
			}

			if ( ! empty( $update_data ) ) {
				$wpdb->update( $table, $update_data, array( 'id' => $existing->id ) );
			}
			
			return $existing->id;
		} else {
			// INSERT new client
			$insert_data = array(
				'email' => $email,
				'salutation' => $salutation,
				'first_name' => $first,
				'last_name' => $last,
				'phone' => $phone,
				'newsletter_opt_in' => $newsletter,
				'booking_count' => $increment_stats ? 1 : 0,
				'last_booking_date' => $increment_stats ? current_time( 'Y-m-d' ) : null,
			);

			// GDPR: Record opt-in details if consented
			if ( $newsletter ) {
				$insert_data['newsletter_opt_in_at'] = current_time( 'mysql' );
				$insert_data['newsletter_opt_in_ip'] = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
			}

			$wpdb->insert( $table, $insert_data );
			return $wpdb->insert_id;
		}
	}

	// ========================================================================
	// HELPER METHODS FOR STRUCTURED RANGE RESPONSE (Tasks 1.5, 1.6, 1.8)
	// ========================================================================

	/**
	 * Calculate availability for a single day with status and slots.
	 * 
	 * @param string $date 'YYYY-MM-DD'
	 * @param int $service_id
	 * @param array|null $pre_fetched_range Pre-fetched GCal events for range
	 * @param bool $only_cache If true, only use cached data (no API calls)
	 * @param Ocean_Shiatsu_Booking_Clustering $clustering Clustering instance
	 * @return array { 'status' => string, 'slots' => array }
	 */
	private function calculate_day_availability( $date, $service_id, $pre_fetched_range, $only_cache, $clustering ) {
		// 1. Check working days
		$working_days = $this->get_working_days();
		$day_of_week = date( 'N', strtotime( $date ) );
		if ( ! in_array( (string)$day_of_week, $working_days ) ) {
			return [ 'status' => 'closed', 'slots' => [] ];
		}
		
		// 2. Get events for this day
		$day_events = null;
		if ( is_array( $pre_fetched_range ) ) {
			$day_events = $this->filter_events_for_day( $date, $pre_fetched_range );
			// Warm cache for future single-day requests
			set_transient( 'osb_gcal_' . $date, $day_events, 3600 );
		} elseif ( $only_cache ) {
			$cached = get_transient( 'osb_gcal_' . $date );
			if ( $cached === false ) {
				// Cache miss in only_cache mode = unavailable (fail-safe)
				return [ 'status' => 'unavailable', 'slots' => [] ];
			}
			$day_events = $cached;
		}
		
		// 3. Check for holidays
		if ( $this->is_holiday( $date, $day_events ) ) {
			return [ 'status' => 'holiday', 'slots' => [] ];
		}
		
		// 4. Calculate slots (pass only_cache to prevent cascade)
		$slots = $clustering->get_available_slots( $date, $service_id, $day_events, $only_cache );
		
		// Handle cache miss from clustering
		if ( $slots === null ) {
			return [ 'status' => 'unavailable', 'slots' => [] ];
		}
		
		// 5. Determine status
		if ( ! empty( $slots ) ) {
			return [ 'status' => 'available', 'slots' => $slots ];
		} else {
			return [ 'status' => 'booked', 'slots' => [] ];
		}
	}

	/**
	 * Check if a day is a holiday based on events.
	 */
	private function is_holiday( $date, $day_events ) {
		if ( ! is_array( $day_events ) ) return false;
		
		$config = $this->get_all_settings();
		$all_day_is_holiday = isset( $config['all_day_is_holiday'] ) ? ( $config['all_day_is_holiday'] !== '0' ) : true;
		$holiday_keywords_raw = isset( $config['holiday_keywords'] ) ? $config['holiday_keywords'] : 'Holiday,Urlaub,Closed';
		$holiday_keywords = array_map( 'trim', explode( ',', $holiday_keywords_raw ) );
		
		foreach ( $day_events as $event ) {
			// Check all-day
			if ( ! empty( $event['is_all_day'] ) && $all_day_is_holiday ) {
				return true;
			}
			// Check keywords
			$summary = isset( $event['summary'] ) ? $event['summary'] : '';
			foreach ( $holiday_keywords as $keyword ) {
				if ( ! empty( $keyword ) && stripos( $summary, $keyword ) !== false ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Filter range events for a specific day.
	 */
	private function filter_events_for_day( $date, $events ) {
		$day_events = [];
		$day_start = strtotime( $date . ' 00:00:00' );
		$day_end = strtotime( $date . ' 23:59:59' );

		foreach ( $events as $event ) {
			$start_arr = isset( $event['start'] ) ? $event['start'] : [];
			$end_arr = isset( $event['end'] ) ? $event['end'] : [];
			
			$start = isset($start_arr['dateTime']) 
				? strtotime($start_arr['dateTime']) 
				: ( isset($start_arr['date']) ? strtotime($start_arr['date']) : 0 );
			$end = isset($end_arr['dateTime']) 
				? strtotime($end_arr['dateTime']) 
				: ( isset($end_arr['date']) ? strtotime($end_arr['date']) : 0 );

			if ( $start <= $day_end && $end >= $day_start ) {
				$day_events[] = $event;
			}
		}
		return $day_events;
	}

	/**
	 * Get working days from settings.
	 */
	private function get_working_days() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		$json = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'working_days'" );
		$days = json_decode( $json, true ) ?: ['1','2','3','4','5'];
		return array_map( 'strval', $days );
	}

	/**
	 * Get all settings as array.
	 */
	private function get_all_settings() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		$results = $wpdb->get_results( "SELECT setting_key, setting_value FROM $table" );
		$settings = [];
		foreach ( $results as $row ) {
			$settings[$row->setting_key] = $row->setting_value;
		}
		return $settings;
	}

	/**
	 * v2.4.1: Serve ICS calendar file for appointment.
	 * Endpoint: GET /osb/v1/calendar/{token}
	 */
	public function serve_ics_calendar( $request ) {
		global $wpdb;
		$token = sanitize_text_field( $request->get_param( 'token' ) );
		
		if ( empty( $token ) ) {
			return new WP_Error( 'invalid_token', 'Token is required', array( 'status' => 400 ) );
		}
		
		$booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}osb_appointments WHERE token = %s",
			$token
		) );
		
		if ( ! $booking ) {
			return new WP_Error( 'not_found', 'Booking not found', array( 'status' => 404 ) );
		}
		
		// Get service name
		$service = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d",
			$booking->service_id
		) );
		$service_name = $service ? $this->get_localized_service_field( $service, 'name', $request['lang'] ?? 'de' ) : 'Shiatsu Session';
		
		// Generate ICS content
		$start_ts = strtotime( $booking->start_time );
		$end_ts = strtotime( $booking->end_time );
		
		$ics_content = "BEGIN:VCALENDAR\r\n";
		$ics_content .= "VERSION:2.0\r\n";
		$ics_content .= "PRODID:-//Ocean Shiatsu//Booking System//DE\r\n";
		$ics_content .= "CALSCALE:GREGORIAN\r\n";
		$ics_content .= "METHOD:PUBLISH\r\n";
		$ics_content .= "BEGIN:VEVENT\r\n";
		$ics_content .= "UID:" . $booking->token . "@oceanshiatsu.at\r\n";
		$ics_content .= "DTSTART:" . date( 'Ymd\THis', $start_ts ) . "\r\n";
		$ics_content .= "DTEND:" . date( 'Ymd\THis', $end_ts ) . "\r\n";
		$ics_content .= "SUMMARY:Shiatsu - " . $service_name . "\r\n";
		$ics_content .= "LOCATION:Wasagasse 3, 1090 Wien\r\n";
		$ics_content .= "DESCRIPTION:Dein Termin bei Ocean Shiatsu. Bitte bring bequeme Kleidung mit.\r\n";
		$ics_content .= "STATUS:CONFIRMED\r\n";
		$ics_content .= "END:VEVENT\r\n";
		$ics_content .= "END:VCALENDAR\r\n";
		
		// Set headers for ICS download
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="termin-ocean-shiatsu.ics"' );
		header( 'Content-Length: ' . strlen( $ics_content ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		
		echo $ics_content;
		exit;
	}
	/*
	 * Helper: Resolve localized service field with fallback
	 */
	private function get_localized_service_field( $service, $field, $lang ) {
		$en_field = $field . '_en';
		if ( $lang === 'en' && ! empty( $service->$en_field ) ) {
			return $service->$en_field;
		}
		return $service->$field ?? '';
	}
}
