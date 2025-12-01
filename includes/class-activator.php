<?php

/**
 * Fired during plugin activation.
 */
class Ocean_Shiatsu_Booking_Activator {

	/**
	 * Create the necessary database tables.
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Appointments Table
		$table_name_appointments = $wpdb->prefix . 'osb_appointments';
		$sql_appointments = "CREATE TABLE $table_name_appointments (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			service_id mediumint(9) NOT NULL,
			client_name tinytext NOT NULL,
			client_email VARCHAR(100) NOT NULL,
			client_phone VARCHAR(50) NOT NULL,
			client_notes text DEFAULT '',
			start_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			end_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			gcal_event_id varchar(255) DEFAULT '' NOT NULL,
			token varchar(64) DEFAULT '' NOT NULL,
			admin_token varchar(64) DEFAULT '' NOT NULL,
			proposed_start_time datetime DEFAULT NULL,
			proposed_end_time datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY start_time (start_time),
			KEY status (status),
			KEY token (token),
			KEY admin_token (admin_token)
		) $charset_collate;";

		// Logs Table
		$table_name_logs = $wpdb->prefix . 'osb_logs';
		$sql_logs = "CREATE TABLE $table_name_logs (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			level varchar(10) NOT NULL,
			source varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Services Table
		$table_name_services = $wpdb->prefix . 'osb_services';
		$sql_services = "CREATE TABLE $table_name_services (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name tinytext NOT NULL,
			duration_minutes int(11) NOT NULL,
			preparation_minutes int(11) DEFAULT 0 NOT NULL,
			price decimal(10,2) NOT NULL,
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

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_appointments );
		dbDelta( $sql_logs );
		dbDelta( $sql_services );
		dbDelta( $sql_settings );

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
	}
}
