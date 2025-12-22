<?php

/**
 * Fired during plugin activation.
 */
class Ocean_Shiatsu_Booking_Activator {

	/**
	 * Run database migrations (can be called on activation or version update).
	 */
	public static function run_migrations() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Appointments Table
		$table_name_appointments = $wpdb->prefix . 'osb_appointments';
		$sql_appointments = "CREATE TABLE $table_name_appointments (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			client_id mediumint(9) DEFAULT NULL,
			service_id mediumint(9) NOT NULL,
			client_name tinytext NOT NULL,
			client_salutation varchar(20) DEFAULT '' NOT NULL,
			client_first_name tinytext NOT NULL,
			client_last_name tinytext NOT NULL,
			client_email VARCHAR(100) NOT NULL,
			client_phone VARCHAR(50) NOT NULL,
			client_notes text DEFAULT '',
			start_time datetime DEFAULT NULL,
			end_time datetime DEFAULT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			gcal_event_id varchar(255) DEFAULT '' NOT NULL,
			token varchar(64) DEFAULT '' NOT NULL,
			admin_token varchar(64) DEFAULT '' NOT NULL,
			proposed_start_time datetime DEFAULT NULL,
			proposed_end_time datetime DEFAULT NULL,
			language varchar(5) DEFAULT 'de' NOT NULL,
			wait_time_from time DEFAULT NULL,
			wait_time_to time DEFAULT NULL,
			reminder_preference varchar(10) DEFAULT 'none' NOT NULL,
			reminder_sent tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY client_email (client_email),
			KEY start_time (start_time),
			KEY status (status),
			KEY token (token),
			KEY admin_token (admin_token)
		) $charset_collate;";

		// Logs Table
		$table_name_logs = $wpdb->prefix . 'osb_logs';
		$sql_logs = "CREATE TABLE $table_name_logs (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			level varchar(10) NOT NULL,
			source varchar(20) NOT NULL,
			message text NOT NULL,
			context text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY level (level)
		) $charset_collate;";

		// Services Table
		$table_name_services = $wpdb->prefix . 'osb_services';
		$sql_services = "CREATE TABLE $table_name_services (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name tinytext NOT NULL,
			duration_minutes int(11) NOT NULL,
			preparation_minutes int(11) DEFAULT 0 NOT NULL,
			price decimal(10,2) NOT NULL,
			price_range varchar(50) DEFAULT '',
			description text DEFAULT '',
			image_url varchar(255) DEFAULT '',
			PRIMARY KEY  (id)
		) $charset_collate;";

		// Settings Table (for Anchor Times, etc.)
		$table_name_settings = $wpdb->prefix . 'osb_settings';
		$sql_settings = "CREATE TABLE $table_name_settings (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			setting_key varchar(50) NOT NULL UNIQUE,
			setting_value text NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// Availability Index Table (for Monthly View)
		$table_name_availability = $wpdb->prefix . 'osb_availability_index';
		$sql_availability = "CREATE TABLE $table_name_availability (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			service_id mediumint(9) NOT NULL,
			status varchar(20) DEFAULT 'available' NOT NULL,
			is_fully_booked boolean DEFAULT 0 NOT NULL,
			last_updated datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY date_service (date, service_id),
			KEY date (date)
		) $charset_collate;";

		// NEW: Clients Table (Plugin 2.0)
		$table_name_clients = $wpdb->prefix . 'osb_clients';
		$sql_clients = "CREATE TABLE $table_name_clients (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			email varchar(255) NOT NULL,
			salutation char(1) DEFAULT 'n' NOT NULL,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) NOT NULL,
			phone varchar(50) DEFAULT '' NOT NULL,
			newsletter_opt_in tinyint(1) DEFAULT 0 NOT NULL,
			newsletter_opt_in_at datetime DEFAULT NULL,
			newsletter_opt_in_ip varchar(45) DEFAULT '' NOT NULL,
			language varchar(5) DEFAULT 'de' NOT NULL,
			booking_count int(11) DEFAULT 0 NOT NULL,
			last_booking_date date DEFAULT NULL,
			notes text DEFAULT '',
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY newsletter_opt_in (newsletter_opt_in)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_appointments );
		dbDelta( $sql_logs );
		dbDelta( $sql_services );
		dbDelta( $sql_settings );
		dbDelta( $sql_availability );
		dbDelta( $sql_clients );

		// Migration: Add 'status' column if missing (for existing installs)
		$row = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$table_name_availability` LIKE %s", 'status' ) );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE $table_name_availability ADD status varchar(20) DEFAULT 'available' NOT NULL AFTER service_id" );
		}

		// Migration: Add split name columns if missing
		$row_appt = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `$table_name_appointments` LIKE %s", 'client_first_name' ) );
		if ( empty( $row_appt ) ) {
			$wpdb->query( "ALTER TABLE $table_name_appointments ADD client_salutation varchar(20) DEFAULT '' NOT NULL AFTER client_name" );
			$wpdb->query( "ALTER TABLE $table_name_appointments ADD client_first_name tinytext NOT NULL AFTER client_salutation" );
			$wpdb->query( "ALTER TABLE $table_name_appointments ADD client_last_name tinytext NOT NULL AFTER client_first_name" );
		}

		// Migration 2.0: Add new columns to appointments if missing
		self::add_column_if_missing( $table_name_appointments, 'client_id', 'mediumint(9) DEFAULT NULL AFTER id' );
		self::add_column_if_missing( $table_name_appointments, 'language', "varchar(5) DEFAULT 'de' NOT NULL AFTER admin_token" );
		self::add_column_if_missing( $table_name_appointments, 'wait_time_from', 'time DEFAULT NULL AFTER language' );
		self::add_column_if_missing( $table_name_appointments, 'wait_time_to', 'time DEFAULT NULL AFTER wait_time_from' );
		self::add_column_if_missing( $table_name_appointments, 'reminder_preference', "varchar(10) DEFAULT 'none' NOT NULL AFTER wait_time_to" );
		self::add_column_if_missing( $table_name_appointments, 'reminder_sent', 'tinyint(1) DEFAULT 0 NOT NULL AFTER reminder_preference' );

		// Migration 2.0: One-time client migration from appointments (SQL-based for performance)
		// Uses CASE statement for safe salutation mapping: 'm' for male, 'w' for female, 'n' otherwise
		$db_version = get_option( 'osb_db_version', '0' );
		if ( version_compare( $db_version, '2.0.0', '<' ) ) {
			$wpdb->query( "
				INSERT IGNORE INTO $table_name_clients (email, salutation, first_name, last_name, phone, created_at)
				SELECT 
					client_email,
					CASE 
						WHEN client_salutation LIKE 'Herr%' OR client_salutation = 'm' THEN 'm'
						WHEN client_salutation LIKE 'Frau%' OR client_salutation = 'w' THEN 'w'
						ELSE 'n'
					END,
					client_first_name,
					client_last_name,
					client_phone,
					MIN(created_at)
				FROM $table_name_appointments
				WHERE client_email != ''
				GROUP BY client_email
			" );
		}

		// Migration: Add price_range column if missing (for updates without reactivation)
		self::add_column_if_missing( $table_name_services, 'price_range', "varchar(50) DEFAULT '' AFTER price" );

		// Initialize cache version for cache-salting (if not exists)
		if ( get_option( 'osb_cache_version' ) === false ) {
			add_option( 'osb_cache_version', time(), '', 'no' ); // 'no' = don't autoload
		}

		// Insert default services if table is empty
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name_services" );
		if ( $count == 0 ) {
			$wpdb->insert(
				$table_name_services,
				array(
					'name' => 'Shiatsu Behandlung (60 min)',
					'duration_minutes' => 60,
					'preparation_minutes' => 15,
					'price' => 80.00,
				)
			);
			$wpdb->insert(
				$table_name_services,
				array(
					'name' => 'Shiatsu Erstbehandlung (90 min)',
					'duration_minutes' => 90,
					'preparation_minutes' => 15,
					'price' => 110.00,
				)
			);
		}

		// Insert default anchor times if empty
		$count_settings = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name_settings WHERE setting_key = 'anchor_times'" );
		if ( $count_settings == 0 ) {
			$wpdb->insert(
				$table_name_settings,
				array(
					'setting_key' => 'anchor_times',
					'setting_value' => json_encode(['09:00', '14:00']), // Default anchors
				)
			);
		}

		// Schedule Cron: Sync Events (existing)
		if ( ! wp_next_scheduled( 'osb_cron_sync_events' ) ) {
			wp_schedule_event( time(), 'every_15_mins', 'osb_cron_sync_events' );
		}

		// Schedule Cron: Reminders (NEW for 2.0)
		if ( ! wp_next_scheduled( 'osb_cron_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'osb_cron_send_reminders' );
		}
	}

	/**
	 * Helper: Add a column to a table if it doesn't exist.
	 * Uses error suppression instead of check-then-act to avoid race conditions.
	 */
	private static function add_column_if_missing( $table, $column, $definition ) {
		global $wpdb;
		// Suppress errors and just attempt the ALTER - if column exists, it will fail silently
		$wpdb->suppress_errors( true );
		$wpdb->query( "ALTER TABLE $table ADD $column $definition" );
		$wpdb->suppress_errors( false );
	}

	/**
	 * Activate the plugin (wrapper that calls migrations + sets version).
	 */
	public static function activate() {
		self::run_migrations();
		
		// Set DB version on activation
		update_option( 'osb_db_version', OCEAN_SHIATSU_BOOKING_VERSION );
	}
}
