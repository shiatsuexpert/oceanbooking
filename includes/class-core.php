<?php

/**
 * The core plugin class.
 */
class Ocean_Shiatsu_Booking_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
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
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Ocean_Shiatsu_Booking_Admin();
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new Ocean_Shiatsu_Booking_Public();
		add_shortcode( 'ocean_booking', array( $plugin_public, 'render_booking_wizard' ) );
		
		$api = new Ocean_Shiatsu_Booking_API();
		$this->loader->add_action( 'rest_api_init', $api, 'register_routes' );

		$sync = new Ocean_Shiatsu_Booking_Sync();
		$sync->init();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		// $this->loader->run();
	}
}
