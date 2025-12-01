<?php
/**
 * Plugin Name: Ocean Shiatsu Booking
 * Plugin URI:  https://oceanshiatsu.com
 * Description: A premium appointment booking system with Google Calendar sync and email workflow.
 * Version:     1.0.0
 * Author:      Antigravity
 * Text Domain: ocean-shiatsu-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'OSB_VERSION', '1.0.0' );
define( 'OSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer Autoloader
if ( file_exists( OSB_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OSB_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize Plugin Update Checker
if ( class_exists( 'Puc_v4_Factory' ) ) {
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/shiatsuexpert/oceanbooking',
		__FILE__,
		'ocean-shiatsu-booking'
	);
}


/**
 * The code that runs during plugin activation.
 */
function activate_ocean_shiatsu_booking() {
	require_once OSB_PLUGIN_DIR . 'includes/class-activator.php';
	Ocean_Shiatsu_Booking_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_ocean_shiatsu_booking' );

/**
 * The core plugin class.
 */
require_once OSB_PLUGIN_DIR . 'includes/class-core.php';

/**
 * Begins execution of the plugin.
 */
function run_ocean_shiatsu_booking() {
	$plugin = new Ocean_Shiatsu_Booking_Core();
	$plugin->run();
}
run_ocean_shiatsu_booking();
