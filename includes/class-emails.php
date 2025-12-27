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
			$service = (object) array( 'name' => __( '(Unknown Service)', 'ocean-shiatsu-booking' ), 'price_range' => '' );
		}
		
		// Get booking page for links
		$booking_page_id = $this->get_setting( 'booking_page_id' );
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url();
		
		$reschedule_link = add_query_arg( ['action' => 'reschedule', 'token' => $booking->token], $base_url );
		$cancel_link = add_query_arg( ['action' => 'cancel', 'token' => $booking->token], $base_url );
		
		// v2.4.1: Generate TRANSFERLINK (ICS download endpoint)
		$transfer_link = get_rest_url( null, 'osb/v1/calendar/' . $booking->token );

		// Prepare data for Refined HTML Template (v2.4)
		$data = array(
			'service_name'    => esc_html( $service->name ),
			'pricing_info'    => isset( $service->price_range ) ? esc_html( $service->price_range ) : '',
			'reschedule_link' => $reschedule_link,
			'cancel_link'     => $cancel_link,
			'transfer_link'   => $transfer_link,
		);

		// Render Template
		$message = $this->render_html_template( 'client-confirmation', $booking, $data );

		$to = $booking->client_email;
		
		// Subject (Localized)
		$lang = ! empty( $booking->language ) ? $booking->language : 'de';
		$subject = ( $lang === 'en' ) 
			? 'Appointment Confirmed: ' . date( 'd/m/Y H:i', strtotime( $booking->start_time ) )
			: 'Terminbestätigung: ' . date( 'd.m.Y H:i', strtotime( $booking->start_time ) );
			
		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		// v2.4.1: Generate ICS file for attachment
		$ics_content = $this->generate_ics_content( $booking, $service );
		$ics_path = sys_get_temp_dir() . '/osb_appointment_' . $booking->id . '.ics';
		file_put_contents( $ics_path, $ics_content );
		
		// Send with attachment
		$sent = wp_mail( $to, $subject, $message, $headers, array( $ics_path ) );
		
		// Cleanup temp file
		if ( file_exists( $ics_path ) ) {
			unlink( $ics_path );
		}
		
		return $sent;
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
		if ( ! $appt ) return;
		
		// Fetch Service for Template
		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $appt->service_id ) );
		$service_name = $service ? esc_html( $service->name ) : '';

		$booking_page_id = $this->get_setting( 'booking_page_id' );
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url();

		$accept_link = add_query_arg( ['action' => 'accept_proposal', 'token' => $appt->token], $base_url );
		$decline_link = add_query_arg( ['action' => 'decline_proposal', 'token' => $appt->token], $base_url );

		$lang = ! empty( $appt->language ) ? $appt->language : 'de';
		
		$date_fmt = ( $lang === 'en' ) ? 'd/m/Y H:i' : 'd.m.Y H:i';
		$formatted_time = date( $date_fmt, strtotime( $new_start_time ) );

		// Prepare Data
		$data = array(
			'proposed_date' => $formatted_time,
			'service_name'  => $service_name,
			'accept_link'   => $accept_link,
			'reject_link'   => $decline_link,
		);

		$message = $this->render_html_template( 'client-proposal', $appt, $data );

		$to = $appt->client_email;
		$subject = ( $lang === 'en' ) 
			? 'Your Appointment Request - New Time Proposed'
			: 'Deine Terminanfrage - Neue Terminzeit vorgeschlagen';
		
		$headers = array('Content-Type: text/html; charset=UTF-8');

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

	public function send_sync_cancellation_notice( $booking_id, $reason = '' ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		if ( ! $appt ) return;
		
		$to = $appt->client_email;
		
		// Load Service Name
		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $appt->service_id ) );
		$service_name = $service ? esc_html( $service->name ) : '';
		
		$data = array(
			'service_name' => $service_name,
			// Template client-cancellation does NOT support comment/reason
		);
		
		$message = $this->render_html_template( 'client-cancellation', $appt, $data );
		
		$lang = ! empty( $appt->language ) ? $appt->language : 'de';
		$subject = ( $lang === 'en' ) ? 'Cancellation - ' . date( 'd/m/Y', strtotime( $appt->start_time ) ) : 'Terminstornierung - ' . date( 'd.m.Y', strtotime( $appt->start_time ) );
		
		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		wp_mail( $to, $subject, $message, $headers );
		
		// Notify Admin (Keep existing simple notification)
		$admin_email = get_option('admin_email');
		$admin_msg = "Termin abgesagt (GCal Sync): {$appt->client_name}. Grund: $reason";
		wp_mail( $admin_email, "Termin abgesagt (GCal Sync): {$appt->client_name}", $admin_msg, $headers );
	}

	public function send_sync_time_change_notice( $booking_id, $new_time, $old_start_time = '' ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		if ( ! $appt ) return;
		
		$to = $appt->client_email;
		
		// Fetch Service
		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $appt->service_id ) );
		$service_name = $service ? esc_html( $service->name ) : '';
		
		$booking_page_id = $this->get_setting( 'booking_page_id' );
		$base_url = $booking_page_id ? get_permalink( $booking_page_id ) : home_url();
		$reschedule_link = add_query_arg( ['action' => 'reschedule', 'token' => $appt->token], $base_url );
		$cancel_link = add_query_arg( ['action' => 'cancel', 'token' => $appt->token], $base_url );

		$lang = ! empty( $appt->language ) ? $appt->language : 'de';
		$date_fmt = ( $lang === 'en' ) ? 'd/m/Y H:i' : 'd.m.Y H:i';

		// Format previous start date if provided
		$formatted_old_date = '';
		if ( ! empty( $old_start_time ) ) {
			$formatted_old_date = date( $date_fmt, strtotime( $old_start_time ) );
		}

		$data = array(
			'service_name'        => $service_name,
			'reschedule_link'     => $reschedule_link,
			'cancel_link'         => $cancel_link,
			'previous_start_date' => $formatted_old_date,
		);

		$message = $this->render_html_template( 'client-reschedule', $appt, $data );

		$subject = ( $lang === 'en' ) 
			? 'Rescheduled Appointment - ' . date( 'd/m/Y H:i', strtotime( $new_time ) )
			: 'Terminverschiebung - ' . date( 'd.m.Y H:i', strtotime( $new_time ) );
			
		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		wp_mail( $to, $subject, $message, $headers );
		
		// Notify Admin
		$admin_email = get_option('admin_email');
		$admin_msg = "Terminverschiebung (GCal Sync): {$appt->client_name} auf " . date('d.m.Y H:i', strtotime($new_time));
		wp_mail( $admin_email, "Terminverschiebung (GCal Sync): {$appt->client_name}", $admin_msg, $headers );
	}

	public function send_client_rejection( $booking_id, $comment = '' ) {
		global $wpdb;
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $booking_id ) );
		if ( ! $appt ) return;

		$service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_services WHERE id = %d", $appt->service_id ) );
		$service_name = $service ? esc_html( $service->name ) : '';

		$data = array(
			'service_name' => $service_name,
			'comment'      => $comment,
		);
		$message = $this->render_html_template( 'client-rejection', $appt, $data );

		$to = $appt->client_email;
		$lang = ! empty( $appt->language ) ? $appt->language : 'de';
		$subject = ( $lang === 'en' ) 
			? 'Your appointment could not be confirmed'
			: 'Ihr Termin konnte leider nicht bestätigt werden';
			
		$headers = array('Content-Type: text/html; charset=UTF-8');

		wp_mail( $to, $subject, $message, $headers );
	}

	private function get_setting( $key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_settings';
		return $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM $table_name WHERE setting_key = %s", $key ) );
	}

	/**
	 * v2.4.1: Generate ICS calendar content for email attachment.
	 * 
	 * @param object $booking Booking object
	 * @param object $service Service object
	 * @return string ICS file content
	 */
	private function generate_ics_content( $booking, $service ) {
		$start_ts = strtotime( $booking->start_time );
		$end_ts = strtotime( $booking->end_time );
		$service_name = $service ? $service->name : 'Shiatsu Session';
		
		$ics = "BEGIN:VCALENDAR\r\n";
		$ics .= "VERSION:2.0\r\n";
		$ics .= "PRODID:-//Ocean Shiatsu//Booking System//DE\r\n";
		$ics .= "CALSCALE:GREGORIAN\r\n";
		$ics .= "METHOD:PUBLISH\r\n";
		$ics .= "BEGIN:VEVENT\r\n";
		$ics .= "UID:" . $booking->token . "@oceanshiatsu.at\r\n";
		$ics .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
		$ics .= "DTSTART:" . date( 'Ymd\THis', $start_ts ) . "\r\n";
		$ics .= "DTEND:" . date( 'Ymd\THis', $end_ts ) . "\r\n";
		$ics .= "SUMMARY:Shiatsu - " . $service_name . "\r\n";
		$ics .= "LOCATION:Wasagasse 3, 1090 Wien\r\n";
		$ics .= "DESCRIPTION:Dein Termin bei Ocean Shiatsu. Bitte bring bequeme Kleidung mit.\r\n";
		$ics .= "STATUS:CONFIRMED\r\n";
		$ics .= "END:VEVENT\r\n";
		$ics .= "END:VCALENDAR\r\n";
		
		return $ics;
	}

/**
	 * PLUGIN 2.4: Render HTML email template with strict placeholder replacement.
	 * 
	 * @param string $template_name Base name of the template (e.g., 'client-confirmation').
	 * @param object $booking Booking object from DB.
	 * @param array $data Additional data for placeholders (e.g., specific links).
	 * @param string|null $override_lang Optional language code to force (e.g., 'en').
	 * @return string Rendered HTML.
	 */
	private function render_html_template( $template_name, $booking, $data = array(), $override_lang = null ) {
		$lang = $override_lang ? $override_lang : ( ! empty( $booking->language ) ? $booking->language : 'de' );
		
		// Validate language code
		$lang = preg_match( '/^[a-z]{2}$/', $lang ) ? $lang : 'de';

		// Construct filename: e.g., client-confirmation-en.html
		$filename = "{$template_name}-{$lang}.html";
		$path = OSB_PLUGIN_DIR . "templates/emails/{$filename}";
		
		if ( ! file_exists( $path ) ) {
			// Fallback to German (Default)
			$path = OSB_PLUGIN_DIR . "templates/emails/{$template_name}-de.html";
		}
		
		if ( ! file_exists( $path ) ) {
			if ( class_exists( 'Ocean_Shiatsu_Booking_Logger' ) ) {
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'Email', "HTML Template not found: {$template_name} (Lang: {$lang})" );
			}
			return '';
		}
		
		$template_content = file_get_contents( $path );
		
		// 1. Prepare Standard Placeholders
		$start_time = strtotime( $booking->start_time );
		$date_fmt = ( $lang === 'en' ) ? 'd/m/Y' : 'd.m.Y';
		$time_fmt = 'H:i';
		$datetime_fmt = ( $lang === 'en' ) ? 'd/m/Y H:i' : 'd.m.Y H:i';

		$replacements = array(
			'APPOINTMENTSTART' => date( $datetime_fmt, $start_time ),
			'APPOINTMENT'      => date( $datetime_fmt, $start_time ), // Alias often used
			'SELECTEDSERVICE'  => isset( $data['service_name'] ) ? $data['service_name'] : '',
			'REFERENCECODE'    => $booking->token, // Using Token as safe reference code
			'ADDITIONALCOMMENT' => isset( $data['comment'] ) && ! empty( $data['comment'] ) ? nl2br( esc_html( $data['comment'] ) ) : '',
			'PRICINGINFO'      => isset( $data['pricing_info'] ) ? $data['pricing_info'] : '',
			
			// Dynamic Dates (for Reschedule)
			'PREVIOUSSTARTDATE' => isset( $data['previous_start_date'] ) ? $data['previous_start_date'] : '',
			'PROPOSED_DATE'     => isset( $data['proposed_date'] ) ? $data['proposed_date'] : '',
			
			// Links
			'TRANSFERLINK'       => isset( $data['transfer_link'] ) ? $data['transfer_link'] : '',
			'CHANGELINKCALENDAR' => isset( $data['reschedule_link'] ) ? $data['reschedule_link'] : '',
			'CANCELLINK'         => isset( $data['cancel_link'] ) ? $data['cancel_link'] : '',
			'ACCEPT_LINK'        => isset( $data['accept_link'] ) ? $data['accept_link'] : '',
			'REJECT_LINK'        => isset( $data['reject_link'] ) ? $data['reject_link'] : '',
			
			// Current Date (for Title %DATE%)
			'%DATE%'             => date( $date_fmt, $start_time ),
		);

		// 2. Handle First Name Extraction for Greeting
		// Try to get just the first name from "First Last"
		$parts = explode( ' ', trim( $booking->client_name ) );
		$first_name = ( count( $parts ) > 0 ) ? $parts[0] : '';
		
		// 3. Conditional Greeting Replacement
		// Pattern: /Hallo FIRSTNAME IS EMPTY Hallo!/ or /Hello FIRSTNAME IS EMPTY Hello!/
		// We use a flexible regex to capture the greeting word used in the template (Hallo/Hello)
		// and the suffix punctuation.
		
		// Strict Regex for the provided placeholder structure:
		// Matches: "Hallo FIRSTNAME IS EMPTY Hallo!" or "Hello FIRSTNAME IS EMPTY Hello!"
		// We replace it with: "{Greeting} {FirstName}," OR "{Greeting}!"
		// Updates v2.4.1: Handle HTML non-breaking spaces (&nbsp;, &#160;) common in email templates
		
		$template_content = preg_replace_callback(
			'/(Hallo|Hello)(?:\s|&nbsp;|&#160;)+FIRSTNAME(?:\s|&nbsp;|&#160;)+IS(?:\s|&nbsp;|&#160;)+EMPTY(?:\s|&nbsp;|&#160;)+(Hallo|Hello)!/i',
			function( $matches ) use ( $first_name ) {
				$greeting_word = $matches[1]; // "Hallo" or "Hello"
				if ( ! empty( $first_name ) ) {
					return $greeting_word . ' ' . esc_html( $first_name ) . ',';
				} else {
					return $greeting_word . '!';
				}
			},
			$template_content
		);

		// 4. Standard Placeholder Replacement
		foreach ( $replacements as $placeholder => $value ) {
			// Strict string replace
			$template_content = str_replace( $placeholder, $value, $template_content );
		}

		// 5. Cleanup: Remove any remaining uppercase placeholders that weren't replaced (optional but clean)
		// Be careful not to remove valid text. Our placeholders are specific. 
		// For safety, we only remove known placeholders if they are empty.
		// The loop above already replaced them with '' if empty. Use a list of known keys?
		// Actually, let's leave this for now to avoid accidental deletions of text like "USA" or "GMT".

		return $template_content;
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

		// Prepare data for Refined HTML Template (v2.4)
		$data = array(
			'service_name'    => $service->name,
			'reschedule_link' => $reschedule_link,
			'cancel_link'     => $cancel_link,
		);

		// Render Template
		$message = $this->render_html_template( 'client-reminder', $booking, $data );

		$to = $booking->client_email;
		$lang = ! empty( $booking->language ) ? $booking->language : 'de';
		
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
	/**
	 * PLUGIN 2.4: Preview template for Admin testing.
	 * 
	 * @param string $template_name
	 * @param string $lang
	 * @return string HTML
	 */
	public function preview_template( $template_name, $lang = 'de' ) {
		// Mock Booking Object
		$booking = (object) array(
			'client_name' => 'Peter Podesva',
			'client_email' => 'peter@example.com',
			'start_time' => date('Y-m-d H:i:s', strtotime('+1 day 10:00')),
			'language' => $lang,
			'token' => 'PREVIEW-TOKEN-123',
			'service_id' => 1
		);
		
		// Mock Data
		$data = array(
			'service_name'      => 'Shiatsu Session (50 Min)',
			'price_range'       => '€ 60 - 80',
			'reschedule_link'   => '#',
			'cancel_link'       => '#',
			'accept_link'       => '#',
			'reject_link'       => '#',
			'transfer_link'     => '#',
			'comment'           => 'Dies ist ein Test-Kommentar für die Vorschau.',
			'previous_start_date' => date('d.m.Y H:i', strtotime('+1 day 14:00')),
			'proposed_date'     => date('d.m.Y H:i', strtotime('+2 days 10:00')),
			'pricing_info'      => '€ 60 - 80'
		);
		
		return $this->render_html_template( $template_name, $booking, $data, $lang );
	}
}
