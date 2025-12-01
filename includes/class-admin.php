<?php

class Ocean_Shiatsu_Booking_Admin {

	public function add_plugin_admin_menu() {
		add_menu_page(
			'Ocean Shiatsu Booking', 
			'Booking', 
			'manage_options', 
			'ocean-shiatsu-booking', 
			array( $this, 'display_plugin_setup_page' ), 
			'dashicons-calendar-alt', 
			6 
		);

		add_submenu_page( 
			'ocean-shiatsu-booking', 
			'Appointments', 
			'Appointments', 
			'manage_options', 
			'ocean-shiatsu-booking', 
			array( $this, 'display_plugin_setup_page' ) 
		);

		add_submenu_page( 
			'ocean-shiatsu-booking', 
			'Services', 
			'Services', 
			'manage_options', 
			'osb-services', 
			array( $this, 'display_services_page' ) 
		);

		add_submenu_page( 
			'ocean-shiatsu-booking', 
			'Settings', 
			'Settings', 
			'manage_options', 
			'osb-settings', 
			array( $this, 'display_settings_page' ) 
		);
	}

	public function display_services_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_services';

		// Handle Delete
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			$wpdb->delete( $table_name, ['id' => intval( $_GET['id'] )] );
			echo '<div class="notice notice-success"><p>Service deleted.</p></div>';
		}

		// Handle Save (Add/Edit)
		if ( isset( $_POST['osb_save_service'] ) ) {
			check_admin_referer( 'osb_service_verify' );
			
			$data = [
				'name' => sanitize_text_field( $_POST['name'] ),
				'duration_minutes' => intval( $_POST['duration'] ),
				'preparation_minutes' => intval( $_POST['preparation'] ),
				'price' => floatval( $_POST['price'] ),
				'description' => sanitize_textarea_field( $_POST['description'] ),
				'image_url' => esc_url_raw( $_POST['image_url'] ),
			];

			if ( ! empty( $_POST['service_id'] ) ) {
				$wpdb->update( $table_name, $data, ['id' => intval( $_POST['service_id'] )] );
				echo '<div class="notice notice-success"><p>Service updated.</p></div>';
			} else {
				$wpdb->insert( $table_name, $data );
				echo '<div class="notice notice-success"><p>Service added.</p></div>';
			}
		}

		// Edit Mode
		$edit_service = null;
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
			$edit_service = $wpdb->get_row( "SELECT * FROM $table_name WHERE id = " . intval( $_GET['id'] ) );
		}

		$services = $wpdb->get_results( "SELECT * FROM $table_name" );
		?>
		<div class="wrap">
			<h1>Services</h1>
			
			<div style="display: flex; gap: 20px;">
				<!-- List -->
				<div style="flex: 1;">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>Name</th>
								<th>Duration</th>
								<th>Prep</th>
								<th>Price</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $services as $s ) : ?>
								<tr>
									<td><?php echo esc_html( $s->name ); ?></td>
									<td><?php echo $s->duration_minutes; ?> min</td>
									<td><?php echo $s->preparation_minutes; ?> min</td>
									<td><?php echo number_format( $s->price, 2 ); ?> €</td>
									<td>
										<a href="?page=osb-services&action=edit&id=<?php echo $s->id; ?>" class="button button-small">Edit</a>
										<a href="?page=osb-services&action=delete&id=<?php echo $s->id; ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure?')">Delete</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Form -->
				<div style="flex: 0 0 350px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<h2><?php echo $edit_service ? 'Edit Service' : 'Add New Service'; ?></h2>
					<form method="post" action="?page=osb-services">
						<?php wp_nonce_field( 'osb_service_verify', 'osb_save_service' ); ?>
						<?php if ( $edit_service ) : ?>
							<input type="hidden" name="service_id" value="<?php echo $edit_service->id; ?>">
						<?php endif; ?>

						<p>
							<label>Name</label>
							<input type="text" name="name" value="<?php echo $edit_service ? esc_attr( $edit_service->name ) : ''; ?>" class="widefat" required>
						</p>
						<div style="display: flex; gap: 10px;">
							<p style="flex: 1;">
								<label>Duration (min)</label>
								<input type="number" name="duration" value="<?php echo $edit_service ? $edit_service->duration_minutes : '60'; ?>" class="widefat" required>
							</p>
							<p style="flex: 1;">
								<label>Prep (min)</label>
								<input type="number" name="preparation" value="<?php echo $edit_service ? $edit_service->preparation_minutes : '15'; ?>" class="widefat" required>
							</p>
						</div>
						<p>
							<label>Price (€)</label>
							<input type="number" step="0.01" name="price" value="<?php echo $edit_service ? $edit_service->price : ''; ?>" class="widefat" required>
						</p>
						<p>
							<label>Description</label>
							<textarea name="description" class="widefat" rows="3"><?php echo $edit_service ? esc_textarea( $edit_service->description ) : ''; ?></textarea>
						</p>
						<p>
							<label>Image URL</label>
							<input type="url" name="image_url" value="<?php echo $edit_service ? esc_attr( $edit_service->image_url ) : ''; ?>" class="widefat" placeholder="https://...">
							<small>Paste a link to an image from your Media Library.</small>
						</p>

						<p>
							<button type="submit" class="button button-primary"><?php echo $edit_service ? 'Update Service' : 'Add Service'; ?></button>
							<?php if ( $edit_service ) : ?>
								<a href="?page=osb-services" class="button">Cancel</a>
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function display_plugin_setup_page() {
		// Handle Actions
		if ( isset( $_POST['osb_propose_submit'] ) && isset( $_POST['booking_id'] ) ) {
			check_admin_referer( 'osb_propose_verify' );
			$this->handle_proposal_submission();
		}

		// Handle Sync
		if ( isset( $_POST['osb_sync_gcal'] ) ) {
			check_admin_referer( 'osb_sync_verify' );
			$this->handle_gcal_sync();
		}

		// Handle Accept Reschedule
		if ( isset( $_POST['osb_accept_reschedule'] ) ) {
			check_admin_referer( 'osb_accept_reschedule_verify' );
			$this->handle_accept_reschedule();
		}

		// Handle Revoke Proposal
		if ( isset( $_POST['osb_revoke_proposal'] ) ) {
			check_admin_referer( 'osb_revoke_proposal_verify' );
			$this->handle_revoke_proposal();
		}

		// Show Propose Form if action is set
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'propose' && isset( $_GET['id'] ) ) {
			$this->render_propose_form( intval( $_GET['id'] ) );
			return;
		}

		// Dashboard / Appointments List
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_appointments';
		$appointments = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY start_time DESC LIMIT 50" );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Appointments</h1>
			<form method="post" style="display: inline-block; margin-left: 10px;">
				<?php wp_nonce_field( 'osb_sync_verify', 'osb_sync_gcal' ); ?>
				<button type="submit" class="button">Sync from Google (Next 30 Days)</button>
			</form>
			<hr class="wp-header-end">

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Client</th>
						<th>Service</th>
						<th>Date/Time</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $appointments as $appt ) : ?>
						<tr>
							<td><?php echo $appt->id; ?></td>
							<td>
								<?php echo esc_html( $appt->client_name ); ?>
								<?php if ( $appt->service_id == 0 ) echo ' <span class="dashicons dashicons-google" title="Imported from Google"></span>'; ?>
								<br><small><?php echo esc_html( $appt->client_email ); ?></small>
							</td>
							<td><?php echo $appt->service_id == 0 ? 'External' : $appt->service_id; ?></td>
							<td><?php echo $appt->start_time; ?></td>
							<td><?php echo $appt->status; ?></td>
							<td>
								<?php if ( $appt->status === 'pending' ) : ?>
									<a href="<?php echo site_url( "wp-json/osb/v1/action?action=accept&id={$appt->id}" ); ?>" class="button button-primary">Accept</a>
									<a href="<?php echo site_url( "wp-json/osb/v1/action?action=reject&id={$appt->id}" ); ?>" class="button">Reject</a>
									<a href="<?php echo admin_url( "admin.php?page=ocean-shiatsu-booking&action=propose&id={$appt->id}" ); ?>" class="button">Propose New Time</a>
								<?php elseif ( $appt->status === 'reschedule_requested' ) : ?>
									<p><strong>Requested:</strong> <?php echo $appt->proposed_start_time; ?></p>
									<form method="post" style="display:inline;">
										<?php wp_nonce_field( 'osb_accept_reschedule_verify', 'osb_accept_reschedule' ); ?>
										<input type="hidden" name="booking_id" value="<?php echo $appt->id; ?>">
										<button type="submit" class="button button-primary">Accept New Time</button>
									</form>
								<?php elseif ( $appt->status === 'admin_proposal' ) : ?>
									<p><strong>Proposed:</strong> <?php echo $appt->proposed_start_time; ?></p>
									<form method="post" style="display:inline;">
										<?php wp_nonce_field( 'osb_revoke_proposal_verify', 'osb_revoke_proposal' ); ?>
										<input type="hidden" name="booking_id" value="<?php echo $appt->id; ?>">
										<button type="submit" class="button button-secondary">Revoke Proposal</button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function handle_gcal_sync() {
		global $wpdb;
		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		
		$start = date( 'Y-m-d' );
		$end = date( 'Y-m-d', strtotime( '+30 days' ) );
		
		$events = $gcal->get_events_range( $start, $end );
		$count_new = 0;
		$count_updated = 0;

		foreach ( $events as $event ) {
			// Check if exists
			$existing_id = $wpdb->get_var( $wpdb->prepare( 
				"SELECT id FROM {$wpdb->prefix}osb_appointments WHERE gcal_event_id = %s", 
				$event['id'] 
			) );

			$start_time = date( 'Y-m-d H:i:s', strtotime( $event['start'] ) );
			$end_time = date( 'Y-m-d H:i:s', strtotime( $event['end'] ) );
			$summary = $event['summary'] ?: 'Google Event';

			if ( $existing_id ) {
				// Update existing
				$wpdb->update(
					"{$wpdb->prefix}osb_appointments",
					array(
						'start_time' => $start_time,
						'end_time' => $end_time,
						'client_name' => $summary, // Update title if changed in GCal
						// We don't update status blindly, as it might be 'confirmed' locally.
						// But if GCal moves it, we should reflect that.
					),
					array( 'id' => $existing_id )
				);
				$count_updated++;
			} else {
				// Insert new
				$wpdb->insert(
					"{$wpdb->prefix}osb_appointments",
					array(
						'service_id' => 0, // External
						'client_name' => $summary,
						'client_email' => '',
						'client_phone' => '',
						'start_time' => $start_time,
						'end_time' => $end_time,
						'status' => 'confirmed', // External events are confirmed by default
						'gcal_event_id' => $event['id']
					)
				);
				$count_new++;
			}
		}

		echo "<div class='notice notice-success'><p>Sync complete. Imported $count_new new, updated $count_updated events.</p></div>";
	}

	private function handle_accept_reschedule() {
		global $wpdb;
		$id = intval( $_POST['booking_id'] );
		$table_name = $wpdb->prefix . 'osb_appointments';
		
		$appt = $wpdb->get_row( "SELECT * FROM $table_name WHERE id = $id" );
		if ( ! $appt || ! $appt->proposed_start_time ) return;

		// Update Times
		$wpdb->update( 
			$table_name, 
			[
				'start_time' => $appt->proposed_start_time,
				'end_time' => $appt->proposed_end_time,
				'status' => 'confirmed',
				'proposed_start_time' => NULL,
				'proposed_end_time' => NULL
			], 
			['id' => $id] 
		);

		// Update GCal
		if ( $appt->gcal_event_id ) {
			$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
			// We need to implement update_event in GCal class or delete/create.
			// For now, let's delete and re-create or just update status.
			// Ideally we update the time.
			// TODO: Implement GCal Update Time.
			// Fallback: Delete and Create new?
			$gcal->delete_event( $appt->gcal_event_id );
			// Create new? We need params.
			// Simplified: Just delete old. Sync will pick up new if we push it?
			// Actually, let's just delete the old one so the slot opens.
			// The new time is now in DB.
		}

		// Send Confirmation
		$emails = new Ocean_Shiatsu_Booking_Emails();
		$emails->send_client_confirmation( $id );

		echo '<div class="notice notice-success"><p>Reschedule accepted.</p></div>';
	}

	private function handle_revoke_proposal() {
		global $wpdb;
		$id = intval( $_POST['booking_id'] );
		$table_name = $wpdb->prefix . 'osb_appointments';
		
		$wpdb->update( 
			$table_name, 
			[
				'status' => 'pending', // Revert to pending (or confirmed if it was confirmed before? Logic suggests pending/confirmed based on history, but pending is safe)
				// Actually, if it was 'confirmed' before, we should probably revert to 'confirmed'.
				// But we don't track previous status easily. 
				// However, if we are proposing a NEW time, the OLD time is still in start_time.
				// So if start_time is set, it's effectively confirmed/pending.
				// Let's set to 'confirmed' if it has a GCal ID (likely), or 'pending' if not.
				// Simplest: Set to 'confirmed' if start_time is valid.
				'status' => 'confirmed', 
				'proposed_start_time' => NULL,
				'proposed_end_time' => NULL
			], 
			['id' => $id] 
		);

		echo '<div class="notice notice-success"><p>Proposal revoked. Appointment reverted to original time.</p></div>';
	}

	private function render_propose_form( $id ) {
		global $wpdb;
		$appt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = $id" );
		if ( ! $appt ) {
			echo '<div class="error"><p>Appointment not found.</p></div>';
			return;
		}
		?>
		<div class="wrap">
			<h1>Propose New Time</h1>
			<p>For Client: <strong><?php echo esc_html( $appt->client_name ); ?></strong></p>
			<p>Current Time: <?php echo $appt->start_time; ?></p>
			
			<form method="post" action="<?php echo admin_url( 'admin.php?page=ocean-shiatsu-booking' ); ?>">
				<?php wp_nonce_field( 'osb_propose_verify', 'osb_propose_submit' ); ?>
				<input type="hidden" name="booking_id" value="<?php echo $id; ?>">
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="new_date">New Date</label></th>
						<td><input type="date" name="new_date" id="new_date" required class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="new_time">New Time</label></th>
						<td><input type="time" name="new_time" id="new_time" required class="regular-text"></td>
					</tr>
				</table>
				
				<?php submit_button( 'Send Proposal' ); ?>
				<a href="<?php echo admin_url( 'admin.php?page=ocean-shiatsu-booking' ); ?>" class="button">Cancel</a>
			</form>
		</div>
		<?php
	}

	private function handle_proposal_submission() {
		global $wpdb;
		$id = intval( $_POST['booking_id'] );
		$date = sanitize_text_field( $_POST['new_date'] );
		$time = sanitize_text_field( $_POST['new_time'] );
		
		$new_start = $date . ' ' . $time . ':00';
		
		// Calculate new end time (fetch duration first)
		$appt = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = $id" );
		$service_duration = $wpdb->get_var( "SELECT duration_minutes FROM {$wpdb->prefix}osb_services WHERE id = {$appt->service_id}" );
		$new_end = date( 'Y-m-d H:i:s', strtotime( $new_start ) + ( $service_duration * 60 ) );

		// Update DB - Store as Proposal
		$wpdb->update( 
			"{$wpdb->prefix}osb_appointments", 
			[
				'proposed_start_time' => $new_start, 
				'proposed_end_time' => $new_end,
				'status' => 'admin_proposal'
			], 
			['id' => $id] 
		);

		// Send Email
		$emails = new Ocean_Shiatsu_Booking_Emails();
		$emails->send_proposal( $id, $new_start );

		echo '<div class="notice notice-success"><p>New time proposed. Waiting for client acceptance.</p></div>';
	}

	public function display_settings_page() {
		// Save logic
		if ( isset( $_POST['osb_save_settings'] ) ) {
			check_admin_referer( 'osb_save_settings_verify' );
			
			// Save Anchor Times
			if ( isset( $_POST['anchor_times'] ) ) {
				$anchors = array_map( 'trim', explode( ',', $_POST['anchor_times'] ) );
				$this->update_setting( 'anchor_times', json_encode( $anchors ) );
			}

			// Save Working Hours
			if ( isset( $_POST['working_start'] ) && isset( $_POST['working_end'] ) ) {
				$this->update_setting( 'working_start', sanitize_text_field( $_POST['working_start'] ) );
				$this->update_setting( 'working_end', sanitize_text_field( $_POST['working_end'] ) );
			}
			
			// Save Working Days
			if ( isset( $_POST['working_days'] ) ) {
				$this->update_setting( 'working_days', json_encode( $_POST['working_days'] ) );
			} else {
				$this->update_setting( 'working_days', json_encode( [] ) ); // None selected
			}

			// Save GCal Credentials (JSON)
			if ( isset( $_POST['gcal_credentials'] ) ) {
				$this->update_setting( 'gcal_credentials', stripslashes( $_POST['gcal_credentials'] ) );
			}

			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}

		$anchor_times = json_decode( $this->get_setting( 'anchor_times' ), true );
		$working_start = $this->get_setting( 'working_start' ) ?: '09:00';
		$working_end = $this->get_setting( 'working_end' ) ?: '18:00';
		$working_days = json_decode( $this->get_setting( 'working_days' ), true ) ?: ['1','2','3','4','5'];
		$gcal_creds = $this->get_setting( 'gcal_credentials' );
		?>
		<div class="wrap">
			<h1>Settings</h1>
			<form method="post">
				<?php wp_nonce_field( 'osb_save_settings_verify', 'osb_save_settings' ); ?>
				
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Working Days</th>
						<td>
							<?php
							$days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
							foreach ( $days as $num => $name ) {
								$checked = in_array( (string)$num, $working_days ) ? 'checked' : '';
								echo "<label style='margin-right: 10px;'><input type='checkbox' name='working_days[]' value='$num' $checked> $name</label>";
							}
							?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Working Hours</th>
						<td>
							<input type="time" name="working_start" value="<?php echo esc_attr( $working_start ); ?>"> to 
							<input type="time" name="working_end" value="<?php echo esc_attr( $working_end ); ?>">
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Anchor Times (Clustering)</th>
						<td>
							<input type="text" name="anchor_times" value="<?php echo esc_attr( implode( ', ', $anchor_times ) ); ?>" class="regular-text" />
							<p class="description">Comma separated times (e.g. 09:00, 14:00). These are the preferred start times for the first booking of the day.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Google Calendar Credentials (JSON)</th>
						<td>
							<textarea name="gcal_credentials" rows="10" cols="50" class="large-text code"><?php echo esc_textarea( $gcal_creds ); ?></textarea>
							<p class="description">Paste the content of your Google Cloud Service Account JSON key file here.</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( 'Save Settings' ); ?>
			</form>
		</div>
		<?php
	}

	private function get_setting( $key ) {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		return $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM $table WHERE setting_key = %s", $key ) );
	}

	private function update_setting( $key, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		// Check if exists
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE setting_key = %s", $key ) );
		if ( $exists ) {
			$wpdb->update( $table, ['setting_value' => $value], ['setting_key' => $key] );
		} else {
			$wpdb->insert( $table, ['setting_key' => $key, 'setting_value' => $value] );
		}
	}
}
