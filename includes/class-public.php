<?php

class Ocean_Shiatsu_Booking_Public {

	public function enqueue_styles() {
		// NOTE: Bootstrap CSS is provided by the theme (Picostrap). Loading it twice causes
		// CSS specificity conflicts that break theme elements like the footer SVG.
		// Commenting out to prevent conflicts - theme's Bootstrap 5 will be used.
		// wp_enqueue_style( 'osb-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0' );
		
		// Get frontend version setting
		global $wpdb;
		$version_setting = $wpdb->get_var( "SELECT setting_value FROM {$wpdb->prefix}osb_settings WHERE setting_key = 'osb_frontend_version'" ) ?: 'v2';

		if ( $version_setting === 'v3' ) {
			wp_enqueue_style( 'osb-style', OSB_PLUGIN_URL . 'assets/css/style-v3.css', array(), OSB_VERSION );
		} elseif ( $version_setting === 'v2' ) {
			wp_enqueue_style( 'osb-style', OSB_PLUGIN_URL . 'assets/css/style-v2.css', array(), OSB_VERSION );
		} else {
			wp_enqueue_style( 'osb-style', OSB_PLUGIN_URL . 'assets/css/style.css', array(), OSB_VERSION );
		}

		// Enqueue Google Fonts (Cormorant, Oxygen for V3)
		wp_enqueue_style( 'osb-google-fonts', 'https://fonts.googleapis.com/css2?family=Cormorant:wght@400;600&family=Oxygen:wght@300;400;700&display=swap', array(), null );
	}

	public function enqueue_scripts() {
		// NOTE: Bootstrap JS is provided by the theme. Loading it twice causes conflicts.
		
		global $wpdb;
		$version_setting = $wpdb->get_var( "SELECT setting_value FROM {$wpdb->prefix}osb_settings WHERE setting_key = 'osb_frontend_version'" ) ?: 'v2';

		if ( $version_setting === 'v3' ) {
			wp_enqueue_script( 'osb-app', OSB_PLUGIN_URL . 'assets/js/booking-app-v3.js', array(), OSB_VERSION, true );
		} elseif ( $version_setting === 'v2' ) {
			wp_enqueue_script( 'osb-app', OSB_PLUGIN_URL . 'assets/js/booking-app-v2.js', array('jquery'), OSB_VERSION, true );
		} else {
			wp_enqueue_script( 'osb-app', OSB_PLUGIN_URL . 'assets/js/booking-app.js', array('jquery'), OSB_VERSION, true );
		}

		// Localize script for API URL
		$booking_page_id = $wpdb->get_var( "SELECT setting_value FROM {$wpdb->prefix}osb_settings WHERE setting_key = 'booking_page_id'" );
		$booking_page_url = $booking_page_id ? get_permalink( $booking_page_id ) : get_home_url();

		// V3: Enhanced config with i18n labels
		if ( $version_setting === 'v3' ) {
			wp_localize_script( 'osb-app', 'osbConfig', array(
				'apiUrl'         => rest_url( 'osb/v1/' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'bookingPageUrl' => $booking_page_url,
				'language'       => Ocean_Shiatsu_Booking_i18n::get_current_language(),
				'labels'         => Ocean_Shiatsu_Booking_i18n::get_frontend_labels(),
				'version'        => OSB_VERSION,
			) );
		} else {
			// V1/V2 compatibility
			wp_localize_script( 'osb-app', 'osbData', array(
				'apiUrl'         => rest_url( 'osb/v1/' ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'bookingPageUrl' => $booking_page_url,
			) );
		}
	}

	public function render_booking_wizard( $atts ) {
		// Enqueue assets only when shortcode is used
		$this->enqueue_styles();
		$this->enqueue_scripts();

		ob_start();
		include OSB_PLUGIN_DIR . 'templates/booking-wizard.php';
		return ob_get_clean();
	}
}

