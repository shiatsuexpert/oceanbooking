<?php

class Ocean_Shiatsu_Booking_API {

	public function register_routes() {
		register_rest_route( 'osb/v1', '/availability', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_availability' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'osb/v1', '/booking', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'create_booking' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'osb/v1', '/action', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'handle_action' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'osb/v1', '/booking-by-token', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_booking_by_token' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'osb/v1', '/reschedule', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'request_reschedule' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'osb/v1', '/cancel', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'cancel_booking' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'osb/v1', '/respond-proposal', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'respond_proposal' ),
			'permission_callback' => '__return_true',
		) );
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
			
			// Update GCal? (Delete old, create new logic or just let sync handle it)
			// For robustness, let's delete the old event ID so sync creates a new one or we create one.
			if ( $booking->gcal_event_id ) {
				$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
				$gcal->delete_event( $booking->gcal_event_id );
				// We should ideally create a new one immediately.
				// But sync will catch it if we don't.
			}

			// Notify Admin
			// $emails->send_admin_proposal_accepted( $booking->id ); // TODO
		} else {
			// Decline
			$wpdb->update( 
				$table_name, 
				[
					'status' => 'pending', // Revert to pending? Or 'rejected'? Let's say pending so Admin sees it again.
					'proposed_start_time' => NULL,
					'proposed_end_time' => NULL
				], 
				['id' => $booking->id] 
			);
			// Notify Admin
			// $emails->send_admin_proposal_declined( $booking->id ); // TODO
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_availability( $request ) {
		$date = $request->get_param( 'date' );
		$service_id = $request->get_param( 'service_id' );

		if ( ! $date || ! $service_id ) {
			return new WP_Error( 'missing_params', 'Date and Service ID are required', array( 'status' => 400 ) );
		}

		// Get duration from DB
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_services';
		$duration = $wpdb->get_var( $wpdb->prepare( "SELECT duration_minutes FROM $table_name WHERE id = %d", $service_id ) );

		if ( ! $duration ) {
			return new WP_Error( 'invalid_service', 'Service not found', array( 'status' => 404 ) );
		}

		$clustering = new Ocean_Shiatsu_Booking_Clustering();
		$slots = $clustering->get_available_slots( $date, $service_id );

		return rest_ensure_response( $slots );
	}

	public function create_booking( $request ) {
		// Rate Limit Check
		$rate_limit = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) return $rate_limit;

		$params = $request->get_json_params();
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
		
		$duration = intval( $params['duration'] );
		if ( $duration <= 0 ) {
			return new WP_Error( 'invalid_duration', 'Duration must be positive', array( 'status' => 400 ) );
		}

		// Validate Date (Allow today, but not past dates? Let's allow today.)
		if ( strtotime( $params['date'] ) < strtotime( date('Y-m-d') ) ) {
			return new WP_Error( 'invalid_date', 'Cannot book in the past', array( 'status' => 400 ) );
		}

		// 2. Concurrency Check (Locking)
		$wpdb->query( "LOCK TABLES $table_name WRITE" );

		$start_time = $params['date'] . ' ' . $params['time'];
		$end_time = date('Y-m-d H:i:s', strtotime($start_time) + ($duration * 60));

		// Check for Overlaps (Local DB only - GCal is checked by frontend, but we double check local here)
		// Overlap: (StartA < EndB) and (EndA > StartB)
		$overlap = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table_name 
			 WHERE status NOT IN ('cancelled', 'rejected')
			 AND start_time < %s AND end_time > %s",
			$end_time,
			$start_time
		) );

		if ( $overlap ) {
			$wpdb->query( "UNLOCK TABLES" );
			return new WP_Error( 'conflict', 'This slot is already booked.', array( 'status' => 409 ) );
		}

		// 3. Insert
		$token = bin2hex( random_bytes( 32 ) );
		$admin_token = bin2hex( random_bytes( 32 ) );

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'service_id' => $params['service_id'],
				'client_name' => $params['client_name'],
				'client_email' => $params['client_email'],
				'client_phone' => $params['client_phone'],
				'client_notes' => isset($params['client_notes']) ? $params['client_notes'] : '',
				'start_time' => $start_time,
				'end_time' => $end_time,
				'status' => 'pending',
				'token' => $token,
				'admin_token' => $admin_token
			)
		);

		$wpdb->query( "UNLOCK TABLES" );

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', 'Could not save booking', array( 'status' => 500 ) );
		}

		$booking_id = $wpdb->insert_id;

		// 4. Sync to GCal (Pending)
		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		$event_id = $gcal->create_event( $params ); // Pass necessary data
		$wpdb->update( $table_name, ['gcal_event_id' => $event_id], ['id' => $booking_id] );

		// 5. Send Emails
		$emails = new Ocean_Shiatsu_Booking_Emails();
		$params['admin_token'] = $admin_token; 
		$emails->send_admin_request( $booking_id, $params );

		return rest_ensure_response( array( 'success' => true, 'id' => $booking_id ) );
	}

	public function get_booking_by_token( $request ) {
		$token = $request->get_param( 'token' );
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';
		
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE token = %s", $token ) );
		
		if ( ! $booking ) {
			return new WP_Error( 'invalid_token', 'Booking not found', array( 'status' => 404 ) );
		}

		// Fetch service name
		$service_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}osb_services WHERE id = %d", $booking->service_id ) );
		$booking->service_name = $service_name;

		return rest_ensure_response( $booking );
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
		$token = $request->get_param( 'token' ); // This is now the ADMIN token
		$action = $request->get_param( 'action' );
		$booking_id = $request->get_param( 'id' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';

		// Verify Admin Token
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $booking_id ) );
		
		if ( ! $booking ) {
			return new WP_Error( 'not_found', 'Booking not found', array( 'status' => 404 ) );
		}

		// Check if token matches admin_token
		if ( ! hash_equals( $booking->admin_token, $token ) ) {
			return new WP_Error( 'forbidden', 'Invalid security token', array( 'status' => 403 ) );
		}

		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();

		if ( $action === 'accept' ) {
			$wpdb->update( $table_name, ['status' => 'confirmed'], ['id' => $booking_id] );
			
			// Update GCal to remove [PENDING]
			$event_id = $booking->gcal_event_id;
			if ( $event_id ) {
				$gcal->update_event_status( $event_id, 'confirmed' );
			}
			
			// Send Client Confirmation
			$emails = new Ocean_Shiatsu_Booking_Emails();
			$emails->send_client_confirmation( $booking_id );
		} elseif ( $action === 'reject' ) {
			$wpdb->update( $table_name, ['status' => 'rejected'], ['id' => $booking_id] );
			
			// Delete from GCal
			$event_id = $booking->gcal_event_id;
			if ( $event_id ) {
				$gcal->delete_event( $event_id );
			}

			// Send Client Rejection
		}

		return rest_ensure_response( array( 'success' => true, 'action' => $action ) );
	}
}
