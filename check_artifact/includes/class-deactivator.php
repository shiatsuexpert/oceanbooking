<?php

/**
 * Fired during plugin deactivation.
 *
 * @link       https://oceanshiatsu.com
 * @since      1.3.9
 *
 * @package    Ocean_Shiatsu_Booking
 * @subpackage Ocean_Shiatsu_Booking/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.3.9
 * @package    Ocean_Shiatsu_Booking
 * @subpackage Ocean_Shiatsu_Booking/includes
 * @author     Ocean Shiatsu <info@oceanshiatsu.com>
 */
class Ocean_Shiatsu_Booking_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.3.9
	 */
	public static function deactivate() {
		// Clear Scheduled Hooks
		wp_clear_scheduled_hook( 'osb_cron_sync_events' );
		wp_clear_scheduled_hook( 'osb_cron_renew_watches' );
	}

}
