<?php

class Ocean_Shiatsu_Booking_Public {

	public function enqueue_styles() {
		// Enqueue Bootstrap 5 (CDN for Phase 1 simplicity, or local)
		wp_enqueue_style( 'osb-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0' );
		
		// Custom Styles
		wp_enqueue_style( 'osb-style', OSB_PLUGIN_URL . 'assets/css/style.css', array('osb-bootstrap'), OSB_VERSION );
	}

	public function enqueue_scripts() {
		// Enqueue Bootstrap JS
		wp_enqueue_script( 'osb-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true );

		// Custom App JS
		wp_enqueue_script( 'osb-app', OSB_PLUGIN_URL . 'assets/js/booking-app.js', array('jquery', 'osb-bootstrap'), OSB_VERSION, true );

		// Localize script for API URL
		wp_localize_script( 'osb-app', 'osbData', array(
			'apiUrl' => rest_url( 'osb/v1/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
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
