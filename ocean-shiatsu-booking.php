<?php
/**
 * Plugin Name: Ocean Shiatsu Booking
 * Plugin URI:  https://oceanshiatsu.com
 * Description: A premium appointment booking system with Google Calendar sync and email workflow.
 * Version:           2.4.1
 * Author:            Ocean Shiatsu
 * Author URI:        https://oceanshiatsu.com
 * License:           GPL-2.0+
 * Text Domain:       ocean-shiatsu-booking
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'OCEAN_SHIATSU_BOOKING_VERSION', '2.4.1' );
define( 'OSB_VERSION', '2.4.1' ); // Alias for consistency
define( 'OSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer Autoloader
if ( file_exists( OSB_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OSB_PLUGIN_DIR . 'vendor/autoload.php';
}

// Initialize Plugin Update Checker (v5 API)
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/shiatsuexpert/oceanbooking/',
	__FILE__,
	'ocean-shiatsu-booking'
);

// Use GitHub Releases with the attached .zip asset
$myUpdateChecker->getVcsApi()->enableReleaseAssets();


/**
 * The code that runs during plugin activation.
 */
function activate_ocean_shiatsu_booking() {
	require_once OSB_PLUGIN_DIR . 'includes/class-activator.php';
	Ocean_Shiatsu_Booking_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_ocean_shiatsu_booking' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ocean_shiatsu_booking() {
	require_once OSB_PLUGIN_DIR . 'includes/class-deactivator.php';
	Ocean_Shiatsu_Booking_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_ocean_shiatsu_booking' );

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
add_action( 'plugins_loaded', 'run_ocean_shiatsu_booking' );
