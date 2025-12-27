<?php
/**
 * PLUGIN 2.0: Internationalization (i18n) with Polylang Support
 * 
 * Registers translatable strings with Polylang and provides
 * localization for frontend JavaScript.
 */

class Ocean_Shiatsu_Booking_i18n {

	/**
	 * Initialize i18n hooks.
	 */
	public function init() {
		// Register Polylang strings on admin_init (for Polylang String Translations)
		add_action( 'admin_init', array( $this, 'register_polylang_strings' ) );
	}

	/**
	 * Register all translatable strings with Polylang.
	 * 
	 * These strings appear in Polylang → String Translations
	 */
	public function register_polylang_strings() {
		// Safety check: Only register if Polylang is active
		if ( ! function_exists( 'pll_register_string' ) ) {
			return;
		}

		$context = 'Ocean Shiatsu Booking';

		// Step labels
		pll_register_string( 'step_service', 'Behandlung', $context );
		pll_register_string( 'step_date', 'Termin', $context );
		pll_register_string( 'step_contact', 'Kontakt', $context );
		pll_register_string( 'step_confirmation', 'Bestätigung', $context );

		// Service selection
		pll_register_string( 'select_service_title', 'Leistung wählen', $context );
		pll_register_string( 'duration_label', 'Dauer', $context );
		pll_register_string( 'minutes', 'Min.', $context );

		// Date/Time selection
		pll_register_string( 'select_date_title', 'Termin wählen', $context );
		pll_register_string( 'available', 'Verfügbar', $context );
		pll_register_string( 'fully_booked', 'Ausgebucht', $context );
		pll_register_string( 'waitlist', 'Ausgebucht - Warteliste verfügbar', $context );
		pll_register_string( 'select_time', 'Verfügbare Zeiten', $context );
		pll_register_string( 'join_waitlist', 'Auf Warteliste setzen', $context );
		pll_register_string( 'time_range_from', 'Zeitraum von', $context );
		pll_register_string( 'time_range_to', 'Zeitraum bis', $context );

		// Contact form
		pll_register_string( 'your_details', 'Deine Daten', $context );
		pll_register_string( 'salutation', 'Anrede', $context );
		pll_register_string( 'salutation_mr', 'Herr', $context );
		pll_register_string( 'salutation_mrs', 'Frau', $context );
		pll_register_string( 'salutation_none', 'Keine Angabe', $context );
		pll_register_string( 'first_name', 'Vorname', $context );
		pll_register_string( 'last_name', 'Nachname', $context );
		pll_register_string( 'email', 'Email Adresse', $context );
		pll_register_string( 'phone', 'Telefonnummer', $context );
		pll_register_string( 'notes', 'Anmerkungen', $context );
		pll_register_string( 'notes_placeholder', 'Gibt es etwas, das ich vorab wissen sollte?', $context );

		// Reminder preference
		pll_register_string( 'reminder_preference', 'Terminerinnerung', $context );
		pll_register_string( 'reminder_none', 'Keine', $context );
		pll_register_string( 'reminder_24h', '24h vorher per Email', $context );
		pll_register_string( 'reminder_48h', '48h vorher per Email', $context );

		// Newsletter
		pll_register_string( 'newsletter_opt_in', 'Dürfen wir dir Angebote per E-Mail zusenden?', $context );

		// Buttons
		pll_register_string( 'btn_next', 'Weiter', $context );
		pll_register_string( 'btn_back', 'Zurück', $context );
		pll_register_string( 'btn_submit', 'Terminanfrage senden', $context );
		pll_register_string( 'btn_submit_waitlist', 'Warteliste eintragen', $context );
		pll_register_string( 'btn_new_booking', 'Neue Buchung', $context );
		pll_register_string( 'btn_back_home', 'Zurück zur Startseite', $context );
		
		// v2.3.0 Interactive Proposal
		pll_register_string( 'btn_submit_reschedule', 'Änderung anfragen', $context );
		pll_register_string( 'btn_submit_proposal', 'Termin bestätigen', $context );
		pll_register_string( 'proposal_title', 'Terminvorschlag bestätigen', $context );

		// Confirmation
		pll_register_string( 'confirmation_title', 'Vielen Dank!', $context );
		pll_register_string( 'confirmation_message', 'Deine Terminanfrage wurde erfolgreich gesendet.', $context );
		pll_register_string( 'confirmation_subtext', 'Du erhältst in Kürze eine Bestätigung per E-Mail (bitte prüfe auch deinen Spam-Ordner).', $context );
		pll_register_string( 'waitlist_confirmation', 'Auf Warteliste gesetzt', $context );
		pll_register_string( 'waitlist_success_subtext', 'Sollte ein Termin im gewünschten Zeitraum frei werden, melden wir uns umgehend bei dir.', $context );

		// Summary Labels
		pll_register_string( 'summary_label_service', 'Leistung:', $context );
		pll_register_string( 'summary_label_time', 'Uhrzeit:', $context );
		pll_register_string( 'summary_label_waitlist', 'Warteliste:', $context );

		// Legal
		pll_register_string( 'agb_notice', 'Mit dem Klick auf den Button bist du mit den AGB und Datenschutzbestimmungen einverstanden.', $context );

		// Errors
		pll_register_string( 'error_required', 'Bitte füllen Sie alle Pflichtfelder aus.', $context );
		pll_register_string( 'error_email', 'Bitte gib eine gültige E-Mail-Adresse ein.', $context );
		pll_register_string( 'error_slot_taken', 'Dieser Termin ist leider nicht mehr verfügbar. Bitte wähle einen anderen.', $context );
	}

	/**
	 * Get a translated string using Polylang with fallback.
	 * 
	 * @param string $string_name The registered string name
	 * @param string $default Default value if Polylang unavailable
	 * @return string Translated string
	 */
	public static function get_string( $string_name, $default = '' ) {
		if ( function_exists( 'pll__' ) ) {
			// Use Polylang translation
			$translated = pll__( $default );
			return ! empty( $translated ) ? $translated : $default;
		}
		return $default;
	}

	/**
	 * Get the current language code.
	 * 
	 * @return string Language code (e.g., 'de', 'en') or 'de' as default
	 */
	public static function get_current_language() {
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language( 'slug' );
			return ! empty( $lang ) ? $lang : 'de';
		}
		return 'de';
	}

	/**
	 * Get all frontend labels as an associative array for JavaScript.
	 * 
	 * @return array Labels array
	 */
	public static function get_frontend_labels() {
		$labels = array(
			// Steps
			'step_service'         => self::get_string( 'step_service', 'Behandlung' ),
			'step_date'            => self::get_string( 'step_date', 'Termin' ),
			'step_contact'         => self::get_string( 'step_contact', 'Kontakt' ),
			'step_confirmation'    => self::get_string( 'step_confirmation', 'Bestätigung' ),
			
			// Service selection
			'select_service_title' => self::get_string( 'select_service_title', 'Leistung wählen' ),
			'duration_label'       => self::get_string( 'duration_label', 'Dauer' ),
			'minutes'              => self::get_string( 'minutes', 'Min.' ),
			
			// Date/Time
			'select_date_title'    => self::get_string( 'select_date_title', 'Termin wählen' ),
			'available'            => self::get_string( 'available', 'Verfügbar' ),
			'fully_booked'         => self::get_string( 'fully_booked', 'Ausgebucht' ),
			'waitlist'             => self::get_string( 'waitlist', 'Ausgebucht - Warteliste verfügbar' ),
			'select_time'          => self::get_string( 'select_time', 'Verfügbare Zeiten' ),
			'join_waitlist'        => self::get_string( 'join_waitlist', 'Auf Warteliste setzen' ),
			'time_range_from'      => self::get_string( 'time_range_from', 'Zeitraum von' ),
			'time_range_to'        => self::get_string( 'time_range_to', 'Zeitraum bis' ),
			
			// Contact form
			'your_details'         => self::get_string( 'your_details', 'Deine Daten' ),
			'salutation'           => self::get_string( 'salutation', 'Anrede' ),
			'salutation_mr'        => self::get_string( 'salutation_mr', 'Herr' ),
			'salutation_mrs'       => self::get_string( 'salutation_mrs', 'Frau' ),
			'salutation_none'      => self::get_string( 'salutation_none', 'Keine Angabe' ),
			'first_name'           => self::get_string( 'first_name', 'Vorname' ),
			'last_name'            => self::get_string( 'last_name', 'Nachname' ),
			'email'                => self::get_string( 'email', 'Email Adresse' ),
			'phone'                => self::get_string( 'phone', 'Telefonnummer' ),
			'notes'                => self::get_string( 'notes', 'Anmerkungen' ),
			'notes_placeholder'    => self::get_string( 'notes_placeholder', 'Gibt es etwas, das ich vorab wissen sollte?' ),
			
			// Reminder
			'reminder_preference'  => self::get_string( 'reminder_preference', 'Terminerinnerung' ),
			'reminder_none'        => self::get_string( 'reminder_none', 'Keine' ),
			'reminder_24h'         => self::get_string( 'reminder_24h', '24h vorher per Email' ),
			'reminder_48h'         => self::get_string( 'reminder_48h', '48h vorher per Email' ),
			
			// Newsletter
			'newsletter_opt_in'    => self::get_string( 'newsletter_opt_in', 'Dürfen wir dir Angebote per E-Mail zusenden?' ),
			
			// Buttons
			'btn_next'             => self::get_string( 'btn_next', 'Weiter' ),
			'btn_back'             => self::get_string( 'btn_back', 'Zurück' ),
			'btn_submit'           => self::get_string( 'btn_submit', 'Terminanfrage senden' ),
			'btn_submit_waitlist'  => self::get_string( 'btn_submit_waitlist', 'Warteliste eintragen' ),
			'btn_new_booking'      => self::get_string( 'btn_new_booking', 'Neue Buchung' ),
			'btn_back_home'        => self::get_string( 'btn_back_home', 'Zurück zur Startseite' ),
			'btn_submit_reschedule'=> self::get_string( 'btn_submit_reschedule', 'Änderung anfragen' ),
			'btn_submit_proposal'  => self::get_string( 'btn_submit_proposal', 'Termin bestätigen' ),
			'proposal_title'       => self::get_string( 'proposal_title', 'Terminvorschlag bestätigen' ),
			
			// Confirmation
			'confirmation_title'   => self::get_string( 'confirmation_title', 'Vielen Dank!' ),
			'confirmation_message' => self::get_string( 'confirmation_message', 'Deine Terminanfrage wurde erfolgreich gesendet.' ),
			'confirmation_subtext' => self::get_string( 'confirmation_subtext', 'Du erhältst in Kürze eine Bestätigung per E-Mail (bitte prüfe auch deinen Spam-Ordner).' ),
			'waitlist_confirmation'=> self::get_string( 'waitlist_confirmation', 'Auf Warteliste gesetzt' ),
			'waitlist_success_subtext'=> self::get_string( 'waitlist_success_subtext', 'Sollte ein Termin im gewünschten Zeitraum frei werden, melden wir uns umgehend bei dir.' ),

			// Summary Labels
			'summary_label_service' => self::get_string( 'summary_label_service', 'Leistung:' ),
			'summary_label_time'    => self::get_string( 'summary_label_time', 'Uhrzeit:' ),
			'summary_label_waitlist'=> self::get_string( 'summary_label_waitlist', 'Warteliste:' ),

			// Legal
			'agb_notice'            => self::get_string( 'agb_notice', 'Mit dem Klick auf den Button bist du mit den AGB und Datenschutzbestimmungen einverstanden.' ),
			
			// Errors
			'error_required'       => self::get_string( 'error_required', 'Bitte füllen Sie alle Pflichtfelder aus.' ),
			'error_email'          => self::get_string( 'error_email', 'Bitte gib eine gültige E-Mail-Adresse ein.' ),
			'error_slot_taken'     => self::get_string( 'error_slot_taken', 'Dieser Termin ist leider nicht mehr verfügbar. Bitte wähle einen anderen.' ),
		);

		return $labels;
	}
}
