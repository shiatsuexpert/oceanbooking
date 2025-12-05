<?php

class Ocean_Shiatsu_Booking_Emails {

	public function send_admin_request( $booking_id, $data ) {
		$to = get_option( 'admin_email' );
		$subject = 'New Appointment Request: ' . $data['client_name'];
		
		$admin_token = $data['admin_token'];
		
		$accept_link = site_url( "wp-json/osb/v1/action?action=accept&id=$booking_id&token=$admin_token" );
		$reject_link = site_url( "wp-json/osb/v1/action?action=reject&id=$booking_id&token=$admin_token" );
		$propose_link = admin_url( "admin.php?page=ocean-shiatsu-booking&action=propose&id=$booking_id" );

		$headers = array('Content-Type: text/html; charset=UTF-8');

		$message = '<html><body>';
		$message .= "<h2>New Appointment Request</h2>";
		$message .= "<p><strong>Client:</strong> {$data['client_name']} ({$data['client_email']})</p>";
		$message .= "<p><strong>Service:</strong> {$data['service_name']}</p>";
		$message .= "<p><strong>Time:</strong> {$data['date']} at {$data['time']}</p>";
		if ( ! empty( $data['client_notes'] ) ) {
			$message .= "<p><strong>Notes:</strong> " . nl2br( esc_html( $data['client_notes'] ) ) . "</p>";
		}
		$message .= "<br>";
		
		// Buttons
		$btn_style = "display: inline-block; padding: 10px 20px; color: #fff; text-decoration: none; border-radius: 5px; margin-right: 10px;";
		
		$message .= "<a href='$accept_link' style='$btn_style background-color: #28a745;'>Accept</a>";
		$message .= "<a href='$reject_link' style='$btn_style background-color: #dc3545;'>Reject</a>";
		$message .= "<a href='$propose_link' style='$btn_style background-color: #007bff;'>Propose New Time</a>";
		
		$message .= "<p><small>Clicking 'Propose New Time' will open the admin interface to select a new date.</small></p>";
		$message .= '</body></html>';

		wp_mail( $to, $subject, $message, $headers );
	}

	public function send_client_confirmation( $booking_id ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		
		// Links
		// Assuming the booking page is where the shortcode is. We need to know the URL.
		// For now, we can use the home_url() + '?page_id=X' or assume a slug.
		// Better: Use a setting for "Booking Page URL" or try to guess.
		// Let's assume the user puts the shortcode on a page. We'll link to home_url('/booking') as a placeholder or use a query param on home.
		// Actually, we can just link to site_url() and append params, assuming the shortcode is on the front page or user configures it.
		// Get Booking Page URL
		$booking_page_id = $this->get_setting( 'booking_page_id' );
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : site_url( '/booking' );
		
		$reschedule_link = add_query_arg( ['action' => 'reschedule', 'token' => $appt->token], $base_url );
		$cancel_link = add_query_arg( ['action' => 'cancel', 'token' => $appt->token], $base_url );

		$to = $appt->client_email;
		$subject = 'Terminbestätigung: ' . date( 'd.m.Y H:i', strtotime( $appt->start_time ) );
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$message = "<html><body>";
		$message .= "<h2>Termin bestätigt</h2>";
		
		$greeting = "Hallo {$appt->client_name},";
		if ( ! empty( $appt->client_last_name ) ) {
			$greeting = "Hallo " . trim( $appt->client_salutation . ' ' . $appt->client_last_name ) . ",";
		}
		
		$message .= "<p>$greeting</p>";
		$message .= "<p>Ihr Termin wurde bestätigt.</p>";
		$message .= "<p><strong>Wann:</strong> " . date( 'd.m.Y H:i', strtotime( $appt->start_time ) ) . "</p>";
		$message .= "<br>";
		$message .= "<p>Falls Sie den Termin verschieben oder absagen müssen:</p>";
		$message .= "<p><a href='$reschedule_link'>Termin verschieben</a></p>";
		$message .= "<p><a href='$cancel_link'>Termin absagen</a></p>";
		$message .= "</body></html>";

		wp_mail( $to, $subject, $message, $headers );
	}

	public function send_admin_reschedule_request( $booking_id, $new_start ) {
		$to = get_option( 'admin_email' );
		$subject = 'Reschedule Request: Booking #' . $booking_id;
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$link = admin_url( "admin.php?page=ocean-shiatsu-booking" ); // Go to dashboard

		$message = "<html><body>";
		$message .= "<h2>Reschedule Request</h2>";
		$message .= "<p>Client requested a new time: <strong>" . date( 'd.m.Y H:i', strtotime( $new_start ) ) . "</strong></p>";
		$message .= "<p><a href='$link'>Manage in Dashboard</a></p>";
		$message .= "</body></html>";

		wp_mail( $to, $subject, $message, $headers );
	}

	public function send_admin_cancellation( $booking_id ) {
		$to = get_option( 'admin_email' );
		$subject = 'Cancellation: Booking #' . $booking_id;
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$message = "<html><body>";
		$message .= "<h2>Booking Cancelled</h2>";
		$message .= "<p>The booking #$booking_id has been cancelled by the client.</p>";
		$message .= "</body></html>";

		wp_mail( $to, $subject, $message, $headers );
	}

	public function send_proposal( $booking_id, $new_start_time ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		
		$to = $appt->client_email;
		$subject = 'Deine Terminanfrage - Neue Terminzeit vorgeschlagen';
		
		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		$formatted_time = date( 'd.m.Y H:i', strtotime( $new_start_time ) );
		
		$booking_page_id = $this->get_setting( 'booking_page_id' );
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : site_url( '/booking' );

		$accept_link = add_query_arg( ['action' => 'accept_proposal', 'token' => $appt->token], $base_url );
		$decline_link = add_query_arg( ['action' => 'decline_proposal', 'token' => $appt->token], $base_url );

		$message = '<html><body>';
		$message .= "<h2>Neue Terminzeit vorgeschlagen</h2>";
		$message .= "<p>Hallo {$appt->client_name},</p>";
		$message .= "<p>Leider klappt der ursprünglich angefragte Termin nicht.</p>";
		$message .= "<p>Ich schlage stattdessen folgenden Termin vor:</p>";
		$message .= "<h3>$formatted_time Uhr</h3>";
		$message .= "<p>Bitte bestätigen Sie diesen neuen Termin:</p>";
		$message .= "<p><a href='$accept_link' style='color: green; font-weight: bold;'>Vorschlag annehmen</a></p>";
		$message .= "<p><a href='$decline_link' style='color: red;'>Ablehnen</a></p>";
		$message .= '</body></html>';

		wp_mail( $to, $subject, $message, $headers );
	}

	public function send_admin_proposal_accepted( $booking_id ) {
		$to = get_option( 'admin_email' );
		$subject = 'Proposal Accepted: Booking #' . $booking_id;
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$link = admin_url( "admin.php?page=ocean-shiatsu-booking" );

		$message = "<html><body>";
		$message .= "<h2>Proposal Accepted</h2>";
		$message .= "<p>The client has accepted the proposed time for booking #$booking_id.</p>";
		$message .= "<p><a href='$link'>View in Dashboard</a></p>";
		$message .= "</body></html>";

		wp_mail( $to, $subject, $message, $headers );
	}

	public function send_admin_proposal_declined( $booking_id ) {
		$to = get_option( 'admin_email' );
		$subject = 'Proposal Declined: Booking #' . $booking_id;
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$link = admin_url( "admin.php?page=ocean-shiatsu-booking" );

		$message = "<html><body>";
		$message .= "<h2>Proposal Declined</h2>";
		$message .= "<p>The client has declined the proposed time for booking #$booking_id.</p>";
		$message .= "<p>Please check the dashboard to propose another time or contact the client.</p>";
		$message .= "<p><a href='$link'>View in Dashboard</a></p>";
		$message .= "</body></html>";

		wp_mail( $to, $subject, $message, $headers );
	}

	private function get_setting( $key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_settings';
		return $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_key = %s", $key ) );
	}
}
