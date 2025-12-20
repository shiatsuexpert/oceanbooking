<?php

/**
 * The core plugin class.
 */
class Ocean_Shiatsu_Booking_Core {

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->check_version_and_migrate(); // Auto-migrate on version change
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once OSB_PLUGIN_DIR . 'includes/class-api.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-google-calendar.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-clustering.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-emails.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-public.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-admin.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-logger.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-sync.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-cron.php';
		require_once OSB_PLUGIN_DIR . 'includes/class-i18n.php';
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Ocean_Shiatsu_Booking_Admin();
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
	}

	/**
	 * Check if plugin version changed and run migrations if needed.
	 */
	private function check_version_and_migrate() {
		$stored_version = get_option( 'osb_db_version', '0.0.0' );
		$current_version = OCEAN_SHIATSU_BOOKING_VERSION;

		if ( version_compare( $stored_version, $current_version, '<' ) ) {
			// Version changed - run migrations
			require_once OSB_PLUGIN_DIR . 'includes/class-activator.php';
			Ocean_Shiatsu_Booking_Activator::run_migrations();
			
			// Update stored version
			update_option( 'osb_db_version', $current_version );
			
			error_log( "OSB: Auto-migration completed from v{$stored_version} to v{$current_version}" );
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new Ocean_Shiatsu_Booking_Public();
		add_shortcode( 'ocean_shiatsu_booking', array( $plugin_public, 'render_booking_wizard' ) );
		
		$api = new Ocean_Shiatsu_Booking_API();
		add_action( 'rest_api_init', array( $api, 'register_routes' ) );

		$sync = new Ocean_Shiatsu_Booking_Sync();
		$sync->init();

		// Schedule Watch Renewal
		if ( ! wp_next_scheduled( 'osb_cron_renew_watches' ) ) {
			wp_schedule_event( time(), 'daily', 'osb_cron_renew_watches' );
		}
		add_action( 'osb_cron_renew_watches', array( 'Ocean_Shiatsu_Booking_Google_Calendar', 'renew_watches_static' ) );

		// PLUGIN 2.0: Initialize cron handlers
		$cron = new Ocean_Shiatsu_Booking_Cron();
		$cron->init();

		// PLUGIN 2.0: Initialize i18n (Polylang integration)
		$i18n = new Ocean_Shiatsu_Booking_i18n();
		$i18n->init();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		// Loader removed in favor of direct hooks
	}
}
