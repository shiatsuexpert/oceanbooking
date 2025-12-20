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
		pll_register_string( 'select_service_title', 'Wähle Deine Behandlung', $context );
		pll_register_string( 'duration_label', 'Dauer', $context );
		pll_register_string( 'minutes', 'Minuten', $context );

		// Date/Time selection
		pll_register_string( 'select_date_title', 'Wähle Deinen Wunschtermin', $context );
		pll_register_string( 'available', 'Verfügbar', $context );
		pll_register_string( 'fully_booked', 'Ausgebucht', $context );
		pll_register_string( 'waitlist', 'Warteliste', $context );
		pll_register_string( 'select_time', 'Zeit auswählen', $context );
		pll_register_string( 'join_waitlist', 'Auf Warteliste setzen', $context );
		pll_register_string( 'time_range_from', 'Von', $context );
		pll_register_string( 'time_range_to', 'Bis', $context );

		// Contact form
		pll_register_string( 'your_details', 'Deine Daten', $context );
		pll_register_string( 'salutation', 'Anrede', $context );
		pll_register_string( 'salutation_mr', 'Herr', $context );
		pll_register_string( 'salutation_mrs', 'Frau', $context );
		pll_register_string( 'salutation_none', 'Keine Angabe', $context );
		pll_register_string( 'first_name', 'Vorname', $context );
		pll_register_string( 'last_name', 'Nachname', $context );
		pll_register_string( 'email', 'E-Mail', $context );
		pll_register_string( 'phone', 'Telefon', $context );
		pll_register_string( 'notes', 'Anmerkungen', $context );
		pll_register_string( 'notes_placeholder', 'Gibt es etwas, das ich vorab wissen sollte?', $context );

		// Reminder preference
		pll_register_string( 'reminder_preference', 'Terminerinnerung', $context );
		pll_register_string( 'reminder_none', 'Keine Erinnerung', $context );
		pll_register_string( 'reminder_24h', '24 Stunden vorher', $context );
		pll_register_string( 'reminder_48h', '48 Stunden vorher', $context );

		// Newsletter
		pll_register_string( 'newsletter_opt_in', 'Ich möchte über Neuigkeiten informiert werden', $context );

		// Buttons
		pll_register_string( 'btn_next', 'Weiter', $context );
		pll_register_string( 'btn_back', 'Zurück', $context );
		pll_register_string( 'btn_submit', 'Termin anfragen', $context );
		pll_register_string( 'btn_submit_waitlist', 'Auf Warteliste setzen', $context );

		// Confirmation
		pll_register_string( 'confirmation_title', 'Vielen Dank!', $context );
		pll_register_string( 'confirmation_message', 'Deine Terminanfrage wurde erfolgreich übermittelt.', $context );
		pll_register_string( 'waitlist_confirmation', 'Du wurdest auf die Warteliste gesetzt.', $context );

		// Errors
		pll_register_string( 'error_required', 'Bitte fülle alle Pflichtfelder aus.', $context );
		pll_register_string( 'error_email', 'Bitte gib eine gültige E-Mail-Adresse ein.', $context );
		pll_register_string( 'error_slot_taken', 'Dieser Termin ist leider nicht mehr verfügbar.', $context );
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
			'select_service_title' => self::get_string( 'select_service_title', 'Wähle Deine Behandlung' ),
			'duration_label'       => self::get_string( 'duration_label', 'Dauer' ),
			'minutes'              => self::get_string( 'minutes', 'Minuten' ),
			
			// Date/Time
			'select_date_title'    => self::get_string( 'select_date_title', 'Wähle Deinen Wunschtermin' ),
			'available'            => self::get_string( 'available', 'Verfügbar' ),
			'fully_booked'         => self::get_string( 'fully_booked', 'Ausgebucht' ),
			'waitlist'             => self::get_string( 'waitlist', 'Warteliste' ),
			'select_time'          => self::get_string( 'select_time', 'Zeit auswählen' ),
			'join_waitlist'        => self::get_string( 'join_waitlist', 'Auf Warteliste setzen' ),
			'time_range_from'      => self::get_string( 'time_range_from', 'Von' ),
			'time_range_to'        => self::get_string( 'time_range_to', 'Bis' ),
			
			// Contact form
			'your_details'         => self::get_string( 'your_details', 'Deine Daten' ),
			'salutation'           => self::get_string( 'salutation', 'Anrede' ),
			'salutation_mr'        => self::get_string( 'salutation_mr', 'Herr' ),
			'salutation_mrs'       => self::get_string( 'salutation_mrs', 'Frau' ),
			'salutation_none'      => self::get_string( 'salutation_none', 'Keine Angabe' ),
			'first_name'           => self::get_string( 'first_name', 'Vorname' ),
			'last_name'            => self::get_string( 'last_name', 'Nachname' ),
			'email'                => self::get_string( 'email', 'E-Mail' ),
			'phone'                => self::get_string( 'phone', 'Telefon' ),
			'notes'                => self::get_string( 'notes', 'Anmerkungen' ),
			'notes_placeholder'    => self::get_string( 'notes_placeholder', 'Gibt es etwas, das ich vorab wissen sollte?' ),
			
			// Reminder
			'reminder_preference'  => self::get_string( 'reminder_preference', 'Terminerinnerung' ),
			'reminder_none'        => self::get_string( 'reminder_none', 'Keine Erinnerung' ),
			'reminder_24h'         => self::get_string( 'reminder_24h', '24 Stunden vorher' ),
			'reminder_48h'         => self::get_string( 'reminder_48h', '48 Stunden vorher' ),
			
			// Newsletter
			'newsletter_opt_in'    => self::get_string( 'newsletter_opt_in', 'Ich möchte über Neuigkeiten informiert werden' ),
			
			// Buttons
			'btn_next'             => self::get_string( 'btn_next', 'Weiter' ),
			'btn_back'             => self::get_string( 'btn_back', 'Zurück' ),
			'btn_submit'           => self::get_string( 'btn_submit', 'Termin anfragen' ),
			'btn_submit_waitlist'  => self::get_string( 'btn_submit_waitlist', 'Auf Warteliste setzen' ),
			
			// Confirmation
			'confirmation_title'   => self::get_string( 'confirmation_title', 'Vielen Dank!' ),
			'confirmation_message' => self::get_string( 'confirmation_message', 'Deine Terminanfrage wurde erfolgreich übermittelt.' ),
			'waitlist_confirmation'=> self::get_string( 'waitlist_confirmation', 'Du wurdest auf die Warteliste gesetzt.' ),
			
			// Errors
			'error_required'       => self::get_string( 'error_required', 'Bitte fülle alle Pflichtfelder aus.' ),
			'error_email'          => self::get_string( 'error_email', 'Bitte gib eine gültige E-Mail-Adresse ein.' ),
			'error_slot_taken'     => self::get_string( 'error_slot_taken', 'Dieser Termin ist leider nicht mehr verfügbar.' ),
		);

		return $labels;
	}
}
