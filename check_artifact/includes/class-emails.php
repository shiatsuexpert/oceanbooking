<?php

class Ocean_Shiatsu_Booking_Emails {

	public function send_admin_request( $booking_id, $data ) {
		$to = get_option( 'admin_email' );
		$subject = 'New Appointment Request: ' . $data['client_name'];
		
		$admin_token = $data['admin_token'];
		
		// FIX: Use get_rest_url for permalink-agnostic URLs
		$base_action_url = get_rest_url( null, 'osb/v1/action' );
		$accept_link = add_query_arg( array( 'action' => 'accept', 'id' => $booking_id, 'token' => $admin_token ), $base_action_url );
		$reject_link = add_query_arg( array( 'action' => 'reject', 'id' => $booking_id, 'token' => $admin_token ), $base_action_url );
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
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		if ( ! $booking ) return;

		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $booking->service_id ) );
		
		// FIX: Handle missing service to prevent fatal error
		if ( ! $service ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'Email', "Service missing for booking ID {$booking_id}" );
			}
			$service = (object) array( 'name' => __( '(Unknown Service)', 'ocean-shiatsu-booking' ) );
		}
		
		// Get booking page for links
		$booking_page_id = $this->get_setting( 'booking_page_id' );
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url();
		
		$reschedule_link = add_query_arg( ['action' => 'reschedule', 'token' => $booking->token], $base_url );
		$cancel_link = add_query_arg( ['action' => 'cancel', 'token' => $booking->token], $base_url );

		// Load template based on booking language (default to 'de')
		$lang = ! empty( $booking->language ) ? $booking->language : 'de';
		$message = $this->load_email_template( 'confirmation', $lang, compact( 'booking', 'service', 'reschedule_link', 'cancel_link' ) );

		// Fallback to German if template not found
		if ( empty( $message ) ) {
			$message = $this->load_email_template( 'confirmation', 'de', compact( 'booking', 'service', 'reschedule_link', 'cancel_link' ) );
		}

		$to = $booking->client_email;
		$subject = ( $lang === 'en' ) 
			? 'Appointment Confirmed: ' . date( 'd/m/Y H:i', strtotime( $booking->start_time ) )
			: 'Terminbestätigung: ' . date( 'd.m.Y H:i', strtotime( $booking->start_time ) );
		$headers = array('Content-Type: text/html; charset=UTF-8');

		wp_mail( $to, $subject, $message, $headers );
	}

	public function send_admin_notification_confirmed( $booking_id ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		
		$to = get_option( 'admin_email' );
		$subject = 'New Booking Confirmed: ' . $appt->client_name;
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$link = admin_url( "admin.php?page=ocean-shiatsu-booking" );

		$message = "<html><body>";
		$message .= "<h2>New Booking Confirmed (Auto)</h2>";
		$message .= "<p>A new booking has been automatically confirmed and synced to Google Calendar.</p>";
		$message .= "<p><strong>Client:</strong> {$appt->client_name}</p>";
		$message .= "<p><strong>Time:</strong> " . date( 'd.m.Y H:i', strtotime( $appt->start_time ) ) . "</p>";
		$message .= "<br>";
		$message .= "<p><a href='$link'>View in Dashboard</a></p>";
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
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url();

		$accept_link = add_query_arg( ['action' => 'accept_proposal', 'token' => $appt->token], $base_url );
		$decline_link = add_query_arg( ['action' => 'decline_proposal', 'token' => $appt->token], $base_url );

		$message = '<html><body>';
		$message .= "<h2>Neue Terminzeit vorgeschlagen</h2>";
		$greeting = "Hallo {$appt->client_name},";
		if ( ! empty( $appt->client_last_name ) ) {
			$greeting = "Hallo " . trim( $appt->client_salutation . ' ' . $appt->client_last_name ) . ",";
		}
		$message .= "<p>$greeting</p>";
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

	public function send_sync_cancellation_notice( $booking_id, $reason ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		
		$to = $appt->client_email;
		$subject = 'Terminänderung: Termin abgesagt';
		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		$message = "<html><body>";
		$message .= "<p>Hallo " . esc_html( $appt->client_name ) . ",</p>";
		$message .= "<p>Ihr Termin am " . date('d.m.Y H:i', strtotime($appt->start_time)) . " wurde abgesagt.</p>";
		$message .= "<p>Grund: " . esc_html( $reason ) . "</p>";
		$message .= "</body></html>";
		
		wp_mail( $to, $subject, $message, $headers );
		
		// Notify Admin
		$admin_email = get_option('admin_email');
		wp_mail( $admin_email, "Termin abgesagt (GCal Sync): {$appt->client_name}", $message, $headers );
	}

	public function send_sync_time_change_notice( $booking_id, $new_time ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		
		$to = $appt->client_email;
		$subject = 'Terminänderung: Neue Uhrzeit';
		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		$message = "<html><body>";
		$message .= "<p>Hallo " . esc_html( $appt->client_name ) . ",</p>";
		$message .= "<p>Ihr Termin wurde auf <strong>" . date('d.m.Y H:i', strtotime($new_time)) . "</strong> verlegt.</p>";
		$message .= "</body></html>";
		
		wp_mail( $to, $subject, $message, $headers );
		
		// Notify Admin
		$admin_email = get_option('admin_email');
		wp_mail( $admin_email, "Terminverschiebung (GCal Sync): {$appt->client_name}", $message, $headers );
	}

	public function send_client_rejection( $booking_id ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		if ( ! $appt ) return;

		$to = $appt->client_email;
		$subject = 'Ihr Termin konnte leider nicht bestätigt werden';
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$message = "<html><body>";
		$message .= "<p>Hallo " . esc_html( $appt->client_name ) . ",</p>";
		$message .= "<p>Wir müssen Ihnen leider mitteilen, dass wir Ihren angefragten Termin am <strong>" . date('d.m.Y H:i', strtotime($appt->start_time)) . "</strong> nicht bestätigen können.</p>";
		$message .= "<p>Bitte versuchen Sie eine Buchung zu einer anderen Zeit.</p>";
		$message .= "<p>Mit freundlichen Grüßen,<br>Ihr Ocean Shiatsu Team</p>";
		$message .= "</body></html>";

		wp_mail( $to, $subject, $message, $headers );
	}

	private function get_setting( $key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_settings';
		return $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_key = %s", $key ) );
	}

	/**
	 * PLUGIN 2.0: Load email template from file.
	 * 
	 * @param string $template_name Template name (e.g., 'confirmation', 'reminder')
	 * @param string $lang Language code (e.g., 'de', 'en')
	 * @param array $vars Variables to extract into template scope
	 * @return string Rendered HTML or empty string if not found
	 */
	private function load_email_template( $template_name, $lang, $vars = array() ) {
		// Security: Whitelist valid template names to prevent path traversal
		$valid_templates = array( 'confirmation', 'reminder', 'waitlist-admin', 'proposal', 'rejection' );
		if ( ! in_array( $template_name, $valid_templates, true ) ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'Email', 'Invalid template name attempted: ' . $template_name );
			}
			return '';
		}

		// Validate language code (only allow 2-char codes)
		$lang = preg_match( '/^[a-z]{2}$/', $lang ) ? $lang : 'de';

		$template_path = OSB_PLUGIN_DIR . "templates/emails/{$template_name}-{$lang}.php";
		
		if ( ! file_exists( $template_path ) ) {
			// Fallback to German
			$template_path = OSB_PLUGIN_DIR . "templates/emails/{$template_name}-de.php";
		}
		
		if ( ! file_exists( $template_path ) ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'Email', "Template not found: {$template_name} for lang {$lang}" );
			}
			return '';
		}
		
		// Extract variables into scope
		extract( $vars );
		
		// Buffer output
		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * PLUGIN 2.0: Send appointment reminder email.
	 * @return bool Whether email was sent successfully
	 */
	public function send_reminder( $booking_id ) {
		global $wpdb;
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		if ( ! $booking ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'Email', "send_reminder: Booking not found for ID {$booking_id}" );
			}
			return false;
		}

		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $booking->service_id ) );
		
		// FIX: Handle missing service to prevent fatal error
		if ( ! $service ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'Email', "Service missing for reminder booking ID {$booking_id}" );
			}
			$service = (object) array( 'name' => __( '(Unknown Service)', 'ocean-shiatsu-booking' ) );
		}
		
		// Get booking page for links
		$booking_page_id = $this->get_setting( 'booking_page_id' );
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url();
		
		$reschedule_link = add_query_arg( ['action' => 'reschedule', 'token' => $booking->token], $base_url );
		$cancel_link = add_query_arg( ['action' => 'cancel', 'token' => $booking->token], $base_url );

		// Load template based on booking language
		$lang = ! empty( $booking->language ) ? $booking->language : 'de';
		$message = $this->load_email_template( 'reminder', $lang, compact( 'booking', 'service', 'reschedule_link', 'cancel_link' ) );

		$to = $booking->client_email;
		$subject = ( $lang === 'en' ) 
			? 'Reminder: Your appointment on ' . date( 'd/m/Y', strtotime( $booking->start_time ) )
			: 'Erinnerung: Ihr Termin am ' . date( 'd.m.Y', strtotime( $booking->start_time ) );
		$headers = array('Content-Type: text/html; charset=UTF-8');

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * PLUGIN 2.0: Send admin notification for waitlist entry.
	 */
	public function send_admin_waitlist( $booking_id, $params = array() ) {
		global $wpdb;
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		if ( ! $booking ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'WARNING', 'Email', "send_admin_waitlist: Booking not found for ID {$booking_id}" );
			}
			return;
		}

		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $booking->service_id ) );
		
		// FIX: Handle missing service to prevent fatal error
		if ( ! $service ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'Email', "Service missing for waitlist booking ID {$booking_id}" );
			}
			$service = (object) array( 'name' => __( '(Unknown Service)', 'ocean-shiatsu-booking' ) );
		}
		
		$admin_link = admin_url( 'admin.php?page=ocean-shiatsu-booking' );

		// Load template (admin emails always in site default language, typically German)
		$message = $this->load_email_template( 'waitlist-admin', 'de', compact( 'booking', 'service', 'admin_link' ) );
		
		// Fallback if template missing
		if ( empty( $message ) ) {
			$message = "<p>New waitlist entry from {$booking->client_name} ({$booking->client_email}) for " . date( 'd.m.Y', strtotime( $booking->start_time ) ) . "</p>";
		}

		$to = get_option( 'admin_email' );
		$subject = 'Warteliste: ' . $booking->client_name . ' - ' . date( 'd.m.Y', strtotime( $booking->start_time ) );
		$headers = array('Content-Type: text/html; charset=UTF-8');

		wp_mail( $to, $subject, $message, $headers );
	}
}
