<?php

/**
 * Fired during plugin activation
 *
 * @link       https://oceanshiatsu.com
 * @since      1.0.0
 *
 * @package    Ocean_Shiatsu_Booking
 * @subpackage Ocean_Shiatsu_Booking/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Ocean_Shiatsu_Booking
 * @subpackage Ocean_Shiatsu_Booking/includes
 * @author     Ocean Shiatsu <info@oceanshiatsu.com>
 */
class Ocean_Shiatsu_Booking_Logger {

	/**
	 * Log an event to the database.
	 *
	 * @param string $level   INFO, WARNING, ERROR, DEBUG
	 * @param string $source  Frontend, API, Admin, GCal, System
	 * @param string $message The log message
	 * @param array  $context Optional context data (array)
	 */
	private static $debug_cache = null;

	public static function is_debug_enabled() {
		if ( self::$debug_cache !== null ) {
			return self::$debug_cache;
		}

		global $wpdb;
		$settings = $wpdb->get_var( "SELECT setting_value FROM {$wpdb->prefix}osb_settings WHERE setting_key = 'osb_enable_debug'" );
		self::$debug_cache = ( $settings === '1' );
		return self::$debug_cache;
	}

	public static function log( $level, $source, $message, $context = [] ) {
		// Filter DEBUG logs if debug is disabled
		if ( $level === 'DEBUG' && ! self::is_debug_enabled() ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_logs';
		
		// Ensure table exists (sanity check, though activator should handle it)
		// We skip this check for performance in production, assuming activation ran.

		$wpdb->insert(
			$table_name,
			array(
				'created_at' => current_time( 'mysql' ),
				'level'      => $level,
				'source'     => $source,
				'message'    => $message,
				'context'    => ! empty( $context ) ? json_encode( $context ) : null,
			)
		);
	}

	/**
	 * Clear all logs.
	 */
	public static function clear_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_logs';
		$wpdb->query( "TRUNCATE TABLE $table_name" );
	}
}
