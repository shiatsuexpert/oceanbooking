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

		add_submenu_page( 
			'ocean-shiatsu-booking', 
			'Logs', 
			'Logs', 
			'manage_options', 
			'osb-logs', 
			array( $this, 'display_logs_page' ) 
		);

		add_submenu_page( 
			'ocean-shiatsu-booking', 
			'Cache Inspector', 
			'Cache Inspector', 
			'manage_options', 
			'osb-cache', 
			array( $this, 'display_cache_inspector' ) 
		);
	}

	public function display_logs_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_logs';

		// Handle Clear Logs
		if ( isset( $_POST['osb_clear_logs'] ) ) {
			check_admin_referer( 'osb_clear_logs_verify' );
			Ocean_Shiatsu_Booking_Logger::clear_logs();
			echo '<div class="notice notice-success"><p>Logs cleared.</p></div>';
		}

		// Pagination
		$per_page = 50;
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $page - 1 ) * $per_page;

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT $per_page OFFSET $offset" );
		$total_pages = ceil( $total / $per_page );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">System Logs</h1>
			<form method="post" style="display:inline-block; margin-left: 10px;">
				<?php wp_nonce_field( 'osb_clear_logs_verify', 'osb_clear_logs' ); ?>
				<button type="submit" class="button button-secondary" onclick="return confirm('Clear all logs?')">Clear Logs</button>
			</form>
			<hr class="wp-header-end">

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 160px;">Date</th>
						<th style="width: 80px;">Level</th>
						<th style="width: 100px;">Source</th>
						<th>Message</th>
						<th>Context</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="5">No logs found.</td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo $log->created_at; ?></td>
								<td>
									<span class="badge badge-<?php echo strtolower( $log->level ); ?>" style="
										padding: 2px 6px; border-radius: 3px; font-weight: bold;
										<?php 
										if($log->level=='ERROR') echo 'background:#dc3232; color:#fff;';
										elseif($log->level=='WARNING') echo 'background:#ffb900; color:#000;';
										elseif($log->level=='DEBUG') echo 'background:#f0f0f1; color:#000;';
										else echo 'background:#00a0d2; color:#fff;';
										?>
									"><?php echo $log->level; ?></span>
								</td>
								<td><?php echo $log->source; ?></td>
								<td><?php echo esc_html( $log->message ); ?></td>
								<td>
									<?php if ( $log->context ) : ?>
										<details>
											<summary>View Data</summary>
											<pre style="font-size: 10px; overflow: auto; max-height: 100px;"><?php echo esc_html( print_r( json_decode( $log->context, true ), true ) ); ?></pre>
										</details>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="pagination-links">
							<?php
							echo paginate_links( array(
								'base' => add_query_arg( 'paged', '%#%' ),
								'format' => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total' => $total_pages,
								'current' => $page
							) );
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function display_cache_inspector() {
		global $wpdb;

		// Handle Wipe All Cache
		if ( isset( $_POST['osb_wipe_all_cache'] ) ) {
			check_admin_referer( 'osb_wipe_all_cache_verify' );
			
			// 1. Delete ALL osb_gcal transients
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_osb_gcal_%'" );
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_osb_gcal_%'" );
			
			// 2. Truncate availability index
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}osb_availability_index" );
			
			// 3. Trigger immediate rebuild
			$sync = new Ocean_Shiatsu_Booking_Sync();
			$first_of_month = strtotime( date('Y-m-01') );
			$current_month = date('Y-m', $first_of_month);
			$next_month = date('Y-m', strtotime('+1 month', $first_of_month));
			$sync->calculate_monthly_availability( $current_month );
			$sync->calculate_monthly_availability( $next_month );
			
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'Cache Wiped & Rebuilt via Cache Inspector' );
			echo '<div class="notice notice-success"><p>✅ All cache wiped and availability rebuilt for current + next month.</p></div>';
		}

		// Fetch all transients related to OSB (This is tricky in WP as transients are in options table with timeout prefix)
		// We'll look for `_transient_osb_gcal_%`
		
		$transients = $wpdb->get_results( 
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_osb_gcal_%'" 
		);

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Cache Inspector</h1>
			<form method="post" style="display:inline-block; margin-left: 10px;">
				<?php wp_nonce_field( 'osb_wipe_all_cache_verify' ); ?>
				<button type="submit" name="osb_wipe_all_cache" class="button button-primary" style="background: #dc3232; border-color: #dc3232;" onclick="return confirm('This will wipe ALL cached availability data and rebuild from Google Calendar. Continue?')">🗑 Wipe All Cache & Rebuild</button>
			</form>
			<hr class="wp-header-end">
			<p>Active Google Calendar caching keys (Raw Events per Day).</p>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Key</th>
						<th>Date</th>
						<th>Event Count</th>
						<th>Day Status</th>
						<th>Expires In</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $transients ) ) : ?>
						<tr><td colspan="6">No active cache keys found.</td></tr>
					<?php else : ?>
						<?php foreach ( $transients as $t ) : ?>
							<?php
							$key = str_replace( '_transient_', '', $t->option_name );
							$date = str_replace( 'osb_gcal_', '', $key );
							$value = get_transient( $key ); // Ensure we get the unserialized value
							$count = is_array( $value ) ? count( $value ) : 'N/A';
							
							// Fetch day status from availability index
							$day_status = $wpdb->get_var( $wpdb->prepare(
								"SELECT status FROM {$wpdb->prefix}osb_availability_index WHERE date = %s LIMIT 1",
								$date
							) );
							$status_label = $day_status ? ucfirst( $day_status ) : '—';
							$status_color = '';
							if ( $day_status === 'available' ) $status_color = 'color: green;';
							elseif ( $day_status === 'booked' ) $status_color = 'color: orange;';
							elseif ( $day_status === 'closed' ) $status_color = 'color: gray;';
							elseif ( $day_status === 'holiday' ) $status_color = 'color: red;';
							
							// Calculate expiry
							$timeout = get_option( '_transient_timeout_' . $key );
							$files = 'N/A';
							if ( $timeout ) {
								$remaining = $timeout - time();
								if ( $remaining > 0 ) {
									$files = number_format( $remaining ) . 's (' . number_format( $remaining / 60, 1 ) . 'm)';
								} else {
									$files = 'Expired';
								}
							}
							?>
							<tr>
								<td><?php echo esc_html( $key ); ?></td>
								<td><?php echo esc_html( $date ); ?></td>
								<td><?php echo $count; ?></td>
								<td style="<?php echo $status_color; ?> font-weight: bold;"><?php echo esc_html( $status_label ); ?></td>
								<td><?php echo $files; ?></td>
								<td>
									<form method="post" style="display:inline;">
										<?php wp_nonce_field( 'osb_clear_cache_verify' ); ?>
										<input type="hidden" name="osb_clear_cache_key" value="<?php echo esc_attr( $key ); ?>">
										<button type="submit" class="button button-small">Clear</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}


	public function display_services_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osb_services';

		// Handle Delete
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'osb_delete_service_' . $_GET['id'] ) ) {
				wp_die( 'Security check failed' );
			}
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
			$edit_service = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", intval( $_GET['id'] ) ) );
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
										<?php $del_url = wp_nonce_url( "?page=osb-services&action=delete&id={$s->id}", 'osb_delete_service_' . $s->id ); ?>
										<a href="<?php echo $del_url; ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure?')">Delete</a>
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
			check_admin_referer( 'osb_propose_verify', 'osb_propose_submit' );
			$this->handle_proposal_submission();
		}

		// Handle Sync
		if ( isset( $_POST['osb_sync_gcal'] ) ) {
			check_admin_referer( 'osb_sync_verify', 'osb_sync_gcal' );
			$this->handle_gcal_sync();
		}

		// Handle Accept Reschedule
		if ( isset( $_POST['osb_accept_reschedule'] ) ) {
			check_admin_referer( 'osb_accept_reschedule_verify', 'osb_accept_reschedule' );
			$this->handle_accept_reschedule();
		}

		// Handle Revoke Proposal
		if ( isset( $_POST['osb_revoke_proposal'] ) ) {
			check_admin_referer( 'osb_revoke_proposal_verify', 'osb_revoke_proposal' );
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
								<?php 
								if ( ! empty( $appt->client_first_name ) ) {
									echo esc_html( trim( $appt->client_salutation . ' ' . $appt->client_first_name . ' ' . $appt->client_last_name ) );
								} else {
									echo esc_html( $appt->client_name ); 
								}
								?>
								<?php if ( $appt->service_id == 0 ) echo ' <span class="dashicons dashicons-google" title="Imported from Google"></span>'; ?>
								<br><small><?php echo esc_html( $appt->client_email ); ?></small>
							</td>
							<td><?php echo $appt->service_id == 0 ? 'External' : $appt->service_id; ?></td>
							<td><?php echo $appt->start_time; ?></td>
							<td><?php echo $appt->status; ?></td>
							<td>
								<?php if ( $appt->status === 'pending' ) : ?>
									<a href="<?php echo site_url( "wp-json/osb/v1/action?action=accept&id={$appt->id}&token={$appt->admin_token}" ); ?>" class="button button-primary">Accept</a>
									<a href="<?php echo site_url( "wp-json/osb/v1/action?action=reject&id={$appt->id}&token={$appt->admin_token}" ); ?>" class="button">Reject</a>
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
		Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'Manual Sync Started' );
		
		// Rate Limit Manual Sync
		$key = 'osb_manual_sync_limit';
		if ( get_transient( $key ) ) {
			echo '<div class="notice notice-warning"><p>Sync limit reached. Please wait a moment.</p></div>';
			return;
		}
		set_transient( $key, 1, 60 ); // 1 minute limit

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

			// Fix: Handle normalized array structure from get_events_range
			$start_str = $event['start']['dateTime'] ?: $event['start']['date'];
			$end_str   = $event['end']['dateTime'] ?: $event['end']['date'];
			
			$start_time = date( 'Y-m-d H:i:s', strtotime( $start_str ) );
			$end_time = date( 'Y-m-d H:i:s', strtotime( $end_str ) );
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
				$token = bin2hex( random_bytes( 32 ) );
				$admin_token = bin2hex( random_bytes( 32 ) );

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
						'gcal_event_id' => $event['id'],
						'token' => $token,
						'admin_token' => $admin_token
					)
				);
				$count_new++;
			}
		}

		echo "<div class='notice notice-success'><p>Sync complete. Imported $count_new new, updated $count_updated events.</p></div>";
		Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'Sync Complete', ['new' => $count_new, 'updated' => $count_updated] );
	}

	private function handle_accept_reschedule() {
		global $wpdb;
		$id = intval( $_POST['booking_id'] );
		$table_name = $wpdb->prefix . 'osb_appointments';
		
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
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
			
			// Calculate Duration from PROPOSED times
			$start_ts = strtotime( $appt->proposed_start_time );
			$end_ts = strtotime( $appt->proposed_end_time );
			$duration = ( $end_ts - $start_ts ) / 60;

			$new_date = date( 'Y-m-d', $start_ts );
			$new_time = date( 'H:i', $start_ts );

			// Try to update time
			set_transient( 'osb_ignore_sync_' . $appt->id, true, 60 ); // Prevent sync notification loop
			$updated = $gcal->update_event_time( $appt->gcal_event_id, $new_date, $new_time, $duration );
			
			if ( ! $updated ) {
				Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'Admin', 'Failed to update GCal event time', ['event_id' => $appt->gcal_event_id] );
				echo '<div class="error"><p>Failed to update Google Calendar event. Reschedule NOT confirmed.</p></div>';
				return; // Do not confirm locally if GCal fails
			}
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
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $id ) );
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
		$appt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}osb_appointments WHERE id = %d", $id ) );
		$service_duration = $wpdb->get_var( $wpdb->prepare( "SELECT duration_minutes FROM {$wpdb->prefix}osb_services WHERE id = %d", $appt->service_id ) );
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
		// Handle Settings Save
		if ( isset( $_POST['osb_save_settings'] ) ) {
			check_admin_referer( 'osb_save_settings_verify', 'osb_save_settings' );
			
			// Save Booking Page
			if ( isset( $_POST['booking_page_id'] ) ) {
				$this->update_setting( 'booking_page_id', intval( $_POST['booking_page_id'] ) );
			}

			$this->save_settings();
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'Settings Saved' );
			
			// Trigger immediate sync to reflect settings changes
			$sync = new Ocean_Shiatsu_Booking_Sync();
			$first_of_month = strtotime( date('Y-m-01') );
			$current_month = date('Y-m', $first_of_month);
			$next_month = date('Y-m', strtotime('+1 month', $first_of_month));
			$sync->calculate_monthly_availability( $current_month );
			$sync->calculate_monthly_availability( $next_month );
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'Settings Change Triggered Sync' );
			
			echo '<div class="notice notice-success"><p>Settings saved. Availability recalculated.</p></div>';
		}

		// Handle Disconnect
		if ( isset( $_POST['osb_disconnect'] ) ) {
			check_admin_referer( 'osb_disconnect_verify', 'osb_disconnect' );
			$this->handle_disconnect();
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'GCal Disconnected' );
			echo '<div class="notice notice-success"><p>Google Calendar disconnected.</p></div>';
		}

		// Handle Calendar Selection Save
		if ( isset( $_POST['osb_save_calendars'] ) ) {
			check_admin_referer( 'osb_save_calendars_verify', 'osb_save_calendars' );
			$selected_calendars = isset( $_POST['gcal_calendars'] ) ? array_map( 'sanitize_text_field', $_POST['gcal_calendars'] ) : [];
			$this->update_setting( 'gcal_selected_calendars', json_encode( $selected_calendars ) );
			
			// Save write calendar
			if ( isset( $_POST['gcal_write_calendar'] ) ) {
				$write_calendar = sanitize_text_field( $_POST['gcal_write_calendar'] );
				$this->update_setting( 'gcal_write_calendar', $write_calendar );
				
				// Warn if write calendar is not in selected list
				if ( ! in_array( $write_calendar, $selected_calendars ) ) {
					echo '<div class="notice notice-warning"><p>⚠️ The write calendar is NOT in your selected calendars! Booking writes will be blocked until you fix this.</p></div>';
				} else {
					// AUTO-SYNC TIMEZONE
					$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
					if ( $gcal->is_connected() ) {
						$all_calendars = $gcal->get_calendar_list();
						foreach ( $all_calendars as $cal ) {
							if ( $cal['id'] === $write_calendar && ! empty( $cal['timeZone'] ) ) {
								$this->update_setting( 'timezone', $cal['timeZone'] );
								echo '<div class="notice notice-info"><p>Timezone updated to match Google Calendar: <strong>' . esc_html( $cal['timeZone'] ) . '</strong></p></div>';
								break;
							}
						}
					}
				}
			}
			
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'Calendars Selected', $selected_calendars );
			
			// Trigger immediate sync to reflect calendar changes
			$sync = new Ocean_Shiatsu_Booking_Sync();
			$first_of_month = strtotime( date('Y-m-01') );
			$current_month = date('Y-m', $first_of_month);
			$next_month = date('Y-m', strtotime('+1 month', $first_of_month));
			$sync->calculate_monthly_availability( $current_month );
			$sync->calculate_monthly_availability( $next_month );
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'Calendar Change Triggered Sync' );
			
			echo '<div class="notice notice-success"><p>Calendar selection saved. Availability recalculated.</p></div>';
		}

		// Handle Cache Clear (Inspector)
		if ( isset( $_POST['osb_clear_cache_key'] ) ) {
			check_admin_referer( 'osb_clear_cache_verify' );
			$key = sanitize_text_field( $_POST['osb_clear_cache_key'] );
			delete_transient( $key );
			echo '<div class="notice notice-success"><p>Cache key cleared: ' . esc_html( $key ) . '</p></div>';
		}

		// Handle OAuth Callback
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'oauth_callback' && isset( $_GET['code'] ) ) {
			Ocean_Shiatsu_Booking_Logger::log( 'INFO', 'Admin', 'OAuth Callback Received' );
			$this->handle_oauth_callback( $_GET['code'] );
		}	


		$working_start = $this->get_setting( 'working_start' ) ?: '09:00';
		$working_end = $this->get_setting( 'working_end' ) ?: '18:00';
		$working_days = json_decode( $this->get_setting( 'working_days' ), true ) ?: ['1','2','3','4','5'];
		
		$client_id = $this->get_setting( 'gcal_client_id' );
		$client_secret = $this->get_setting( 'gcal_client_secret' );
		$access_token = $this->get_setting( 'gcal_access_token' );

		?>
		<div class="wrap">
			<h1>Settings</h1>

			<?php
			// === SYSTEM STATUS BOX ===
			$next_sync = wp_next_scheduled( 'osb_cron_sync_events' );
			$last_sync = $this->get_setting( 'gcal_last_sync_token' );
			$next_watch_renewal = wp_next_scheduled( 'osb_cron_renew_watches' );
			?>
			<div style="background: #f0f0f1; border-left: 4px solid #2271b1; padding: 12px 15px; margin-bottom: 20px;">
				<h3 style="margin-top: 0;">🔧 System Status</h3>
				<table style="font-size: 13px;">
					<tr>
						<td><strong>15-Min Sync Cron:</strong></td>
						<td>
							<?php if ( $next_sync ) : ?>
								✅ Scheduled — Next run: <code><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $next_sync ) ); ?></code>
							<?php else : ?>
								❌ <span style="color: red;">NOT SCHEDULED</span> — Try deactivating and reactivating the plugin.
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong>Last Sync:</strong></td>
						<td>
							<?php if ( $last_sync ) : ?>
							<?php $last_sync_local = wp_date( 'Y-m-d H:i:s', strtotime( $last_sync ) ); ?>
							<code><?php echo esc_html( $last_sync_local ); ?></code>
							<?php else : ?>
								<em>Never synced</em>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong>Webhook Watch Renewal:</strong></td>
						<td>
							<?php if ( $next_watch_renewal ) : ?>
								✅ Scheduled — Next run: <code><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $next_watch_renewal ) ); ?></code>
							<?php else : ?>
								⚠️ Not scheduled (webhooks may expire)
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'osb_save_settings_verify', 'osb_save_settings' ); ?>
				
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Booking Page</th>
						<td>
							<?php
							$booking_page_id = $this->get_setting( 'booking_page_id' );
							wp_dropdown_pages( array(
								'name' => 'booking_page_id',
								'selected' => $booking_page_id,
								'show_option_none' => 'Select Page',
								'option_none_value' => 0,
								'post_status' => array( 'publish', 'private', 'draft' ),
							) );
							?>
							<p class="description">Select the page where the <code>[ocean_shiatsu_booking]</code> shortcode is placed.</p>
						</td>
					</tr>
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
						<th scope="row"><label for="osb_enable_debug">Enable Debug Mode</label></th>
						<td>
							<?php $debug_enabled = $this->get_setting( 'osb_enable_debug' ); ?>
							<input type="checkbox" name="osb_enable_debug" id="osb_enable_debug" value="1" 
								<?php checked( $debug_enabled !== '0' && $debug_enabled !== null && $debug_enabled !== '' ? $debug_enabled : 0 ); ?>>
							<label for="osb_enable_debug">Enable verbose logging and frontend developer tracing.</label>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">Timezone</th>
						<td>
							<?php
							$timezone = $this->get_setting( 'timezone' ) ?: 'Europe/Berlin';
							?>
							<select name="timezone" class="regular-text">
								<?php
								$timezones = timezone_identifiers_list();
								foreach ( $timezones as $tz ) {
									$selected = ( $tz === $timezone ) ? 'selected' : '';
									echo "<option value='" . esc_attr($tz) . "' $selected>" . esc_html($tz) . "</option>";
								}
								?>
							</select>
							<p class="description">Timezone for Google Calendar events. Defaults to Europe/Berlin.</p>
						</td>
					</tr>
				</table>

				<h2>Availability & Holiday Settings</h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="max_bookings_per_day">Max Bookings Per Day</label></th>
						<td>
							<input type="number" name="max_bookings_per_day" id="max_bookings_per_day" 
								value="<?php echo esc_attr( $this->get_setting( 'max_bookings_per_day' ) ?: '0' ); ?>" 
								class="small-text" min="0">
							<p class="description">Maximum number of bookings allowed per day. 0 = unlimited.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="all_day_is_holiday">All-Day Events = Holiday</label></th>
						<td>
							<?php $all_day_is_holiday = $this->get_setting( 'all_day_is_holiday' ); ?>
							<input type="checkbox" name="all_day_is_holiday" id="all_day_is_holiday" value="1" 
								<?php checked( $all_day_is_holiday !== '0' ); ?>>
							<label for="all_day_is_holiday">Treat all-day Google Calendar events as holidays (no bookings allowed).</label>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="holiday_keywords">Holiday Keywords</label></th>
						<td>
							<textarea name="holiday_keywords" id="holiday_keywords" rows="2" class="regular-text"><?php 
								echo esc_textarea( $this->get_setting( 'holiday_keywords' ) ?: 'Holiday,Urlaub,Closed' ); 
							?></textarea>
							<p class="description">Comma-separated keywords. If any event title contains these words, the day is marked as a holiday.</p>
						</td>
					</tr>
				</table>

				<h2>Slot Presentation</h2>
				<p class="description" style="margin-bottom: 15px;">Control how time slots are displayed to clients. The algorithm filters slots to create an illusion of scarcity while maintaining variety.</p>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="slot_min_show">Minimum Slots to Show</label></th>
						<td>
							<input type="number" name="slot_min_show" id="slot_min_show" 
								value="<?php echo esc_attr( $this->get_setting( 'slot_min_show' ) ?: '3' ); ?>" 
								class="small-text" min="1" max="20">
							<p class="description">Always show at least this many slots. If available slots are below this, show all.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="slot_max_show">Maximum Slots to Show</label></th>
						<td>
							<input type="number" name="slot_max_show" id="slot_max_show" 
								value="<?php echo esc_attr( $this->get_setting( 'slot_max_show' ) ?: '8' ); ?>" 
								class="small-text" min="1" max="50">
							<p class="description">Never show more than this many slots to clients.</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="slot_show_percentage">Empty Day Variety (%)</label></th>
						<td>
							<input type="range" name="slot_show_percentage" id="slot_show_percentage" 
								value="<?php echo esc_attr( $this->get_setting( 'slot_show_percentage' ) ?: '50' ); ?>" 
								min="10" max="100" step="10" oninput="document.getElementById('slot_show_percentage_val').textContent = this.value + '%'">
							<span id="slot_show_percentage_val"><?php echo esc_html( $this->get_setting( 'slot_show_percentage' ) ?: '50' ); ?>%</span>
							<p class="description">Percentage of slots to sample on days without existing events (before min/max bounds).</p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="slot_edge_probability">First/Last Slot Probability (%)</label></th>
						<td>
							<input type="range" name="slot_edge_probability" id="slot_edge_probability" 
								value="<?php echo esc_attr( $this->get_setting( 'slot_edge_probability' ) ?: '70' ); ?>" 
								min="0" max="100" step="10" oninput="document.getElementById('slot_edge_probability_val').textContent = this.value + '%'">
							<span id="slot_edge_probability_val"><?php echo esc_html( $this->get_setting( 'slot_edge_probability' ) ?: '70' ); ?>%</span>
							<p class="description">Probability that earliest/latest slots appear in the sample (ensures variety across days).</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( 'Save Settings' ); ?>
			</form>

			<?php
			// Google Calendar Settings
			echo '<h2>Google Calendar Integration (OAuth 2.0)</h2>';
			echo '<form method="post" action="" autocomplete="off">';
			wp_nonce_field( 'osb_save_settings_verify', 'osb_save_settings' );
			
			echo '<table class="form-table">';
			echo '<tr><th scope="row"><label for="gcal_client_id">Client ID</label></th>';
			echo '<td><input type="text" name="gcal_client_id" id="gcal_client_id" value="' . esc_attr( $client_id ) . '" class="regular-text" autocomplete="off"></td></tr>';
			
			echo '<tr><th scope="row"><label for="gcal_client_secret">Client Secret</label></th>';
			echo '<td><input type="text" name="gcal_client_secret" id="gcal_client_secret" value="' . esc_attr( $client_secret ) . '" class="regular-text" autocomplete="off"></td></tr>';
			
			echo '<tr><th scope="row">Redirect URI</th>';
			echo '<td><code>' . admin_url( 'admin.php?page=osb-settings&action=oauth_callback' ) . '</code><br><small>Add this to your Google Cloud Console "Authorized redirect URIs".</small></td></tr>';
			
			echo '</table>';
			echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings"></p>';
			echo '</form>';

			// Connect / Disconnect
			if ( $client_id && $client_secret ) {
				if ( $access_token ) {
					echo '<div class="notice notice-success inline"><p>Status: <strong>Connected</strong></p></div>';
					echo '<form method="post" action="">';
					wp_nonce_field( 'osb_disconnect_verify', 'osb_disconnect' );
					echo '<input type="submit" class="button" value="Disconnect Google Calendar">';
					echo '</form>';
					
					// Calendar Picker will go here
					$this->render_calendar_picker();
					echo '<p class="description"><strong>Note:</strong> New bookings are always saved to your <strong>Primary</strong> calendar. Check other calendars to block their busy times from your availability.</p>';

				} else {
					$auth_url = $this->get_oauth_url( $client_id );
					echo '<a href="' . esc_url( $auth_url ) . '" class="button button-primary">Connect Google Calendar</a>';
				}
			} else {
				echo '<p><em>Enter Client ID and Secret to connect.</em></p>';
			}
			?>
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

	private function save_settings() {
		if ( isset( $_POST['working_start'] ) ) $this->update_setting( 'working_start', sanitize_text_field( $_POST['working_start'] ) );
		if ( isset( $_POST['working_end'] ) ) $this->update_setting( 'working_end', sanitize_text_field( $_POST['working_end'] ) );
		
		if ( isset( $_POST['working_days'] ) ) {
			$days = array_map( 'sanitize_text_field', $_POST['working_days'] );
			$this->update_setting( 'working_days', json_encode( $days ) );
		}



		if ( isset( $_POST['timezone'] ) ) {
			$this->update_setting( 'timezone', sanitize_text_field( $_POST['timezone'] ) );
		}

		// Holiday Settings
		if ( isset( $_POST['max_bookings_per_day'] ) ) {
			$this->update_setting( 'max_bookings_per_day', intval( $_POST['max_bookings_per_day'] ) );
		}
		$this->update_setting( 'all_day_is_holiday', isset( $_POST['all_day_is_holiday'] ) ? '1' : '0' );
		
		// Debug Mode
		$this->update_setting( 'osb_enable_debug', isset( $_POST['osb_enable_debug'] ) ? '1' : '0' );

		if ( isset( $_POST['holiday_keywords'] ) ) {
			$this->update_setting( 'holiday_keywords', sanitize_textarea_field( $_POST['holiday_keywords'] ) );
		}

		// Slot Presentation Settings
		if ( isset( $_POST['slot_min_show'] ) ) {
			$this->update_setting( 'slot_min_show', intval( $_POST['slot_min_show'] ) );
		}
		if ( isset( $_POST['slot_max_show'] ) ) {
			$this->update_setting( 'slot_max_show', intval( $_POST['slot_max_show'] ) );
		}
		if ( isset( $_POST['slot_show_percentage'] ) ) {
			$this->update_setting( 'slot_show_percentage', intval( $_POST['slot_show_percentage'] ) );
		}
		if ( isset( $_POST['slot_edge_probability'] ) ) {
			$this->update_setting( 'slot_edge_probability', intval( $_POST['slot_edge_probability'] ) );
		}

		if ( isset( $_POST['gcal_client_id'] ) ) $this->update_setting( 'gcal_client_id', sanitize_text_field( $_POST['gcal_client_id'] ) );
		if ( isset( $_POST['gcal_client_secret'] ) ) $this->update_setting( 'gcal_client_secret', sanitize_text_field( $_POST['gcal_client_secret'] ) );
	}

	private function handle_disconnect() {
		$this->update_setting( 'gcal_access_token', '' );
		$this->update_setting( 'gcal_refresh_token', '' );
	}

	private function get_oauth_url( $client_id ) {
		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		return $gcal->get_oauth_url( $client_id );
	}

	private function handle_oauth_callback( $code ) {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		
		$client_id = $this->get_setting( 'gcal_client_id' );
		$client_secret = $this->get_setting( 'gcal_client_secret' );
		$redirect_uri = admin_url( 'admin.php?page=osb-settings&action=oauth_callback' );

		if ( ! $client_id || ! $client_secret ) {
			echo '<div class="error"><p>Client ID or Secret missing.</p></div>';
			return;
		}

		// Exchange code for token
		$url = 'https://oauth2.googleapis.com/token';
		$body = array(
			'code' => $code,
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'redirect_uri' => $redirect_uri,
			'grant_type' => 'authorization_code'
		);

		$response = wp_remote_post( $url, array( 'body' => $body ) );
		
		if ( is_wp_error( $response ) ) {
			echo '<div class="error"><p>OAuth Error: ' . $response->get_error_message() . '</p></div>';
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( ! $data || isset( $data['error'] ) ) {
			$error_msg = isset( $data['error_description'] ) ? $data['error_description'] : 'Unknown Error';
			echo '<div class="error"><p>OAuth Error: ' . $error_msg . '</p></div>';
			return;
		}

		// Save Tokens
		$this->update_setting( 'gcal_access_token', $data['access_token'] );
		if ( isset( $data['refresh_token'] ) ) {
			$this->update_setting( 'gcal_refresh_token', $data['refresh_token'] );
		}

		// Redirect to Settings
		wp_redirect( admin_url( 'admin.php?page=osb-settings&status=connected' ) );
		exit;
	}

	private function render_calendar_picker() {
		$gcal = new Ocean_Shiatsu_Booking_Google_Calendar();
		$calendars = $gcal->get_calendar_list();
		
		if ( empty( $calendars ) ) {
			echo '<p>No calendars found or error fetching list.</p>';
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		$json = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_selected_calendars'" );
		$selected = json_decode( $json, true ) ?: ['primary'];
		
		// Get current write calendar (empty = not configured)
		$write_calendar = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_write_calendar'" );

		// Show critical warning if no write calendar is configured
		if ( empty( $write_calendar ) ) {
			echo '<div class="notice notice-error inline" style="margin: 10px 0;"><p><strong>⛔ CRITICAL:</strong> No Write Calendar configured. All booking write operations (create, update, delete) are currently <strong>BLOCKED</strong>. You must select a Write Calendar below to enable bookings.</p></div>';
		}

		echo '<h3>Select Calendars to Sync (Busy Times)</h3>';
		echo '<p class="description">☑ Check the calendars to read for availability. Only busy times from these calendars will be considered.</p>';
		echo '<form method="post" action="">';
		wp_nonce_field( 'osb_save_calendars_verify', 'osb_save_calendars' );
		
		foreach ( $calendars as $cal ) {
			$checked = in_array( $cal['id'], $selected ) ? 'checked' : '';
			echo '<p><label>';
			echo '<input type="checkbox" name="gcal_calendars[]" value="' . esc_attr( $cal['id'] ) . '" ' . $checked . '> ';
			echo esc_html( $cal['summary'] ) . ( $cal['primary'] ? ' (Primary)' : '' );
			echo '</label></p>';
		}
		
		// Write Calendar Selector
		echo '<h3 style="margin-top: 20px;">📝 Write Calendar (Create Bookings)</h3>';
		echo '<p class="description" style="color: #d63384;"><strong>⚠️ REQUIRED:</strong> You MUST select a calendar here. Bookings will NOT work until you do!</p>';
		
		echo '<select name="gcal_write_calendar" style="min-width: 300px;" required>';
		// Add explicit "not selected" option
		echo '<option value=""' . ( empty( $write_calendar ) ? ' selected' : '' ) . '>-- Select a Write Calendar (REQUIRED) --</option>';
		foreach ( $calendars as $cal ) {
			$is_selected = ( $cal['id'] === $write_calendar ) ? 'selected' : '';
			$in_sync_list = in_array( $cal['id'], $selected );
			$warning = $in_sync_list ? '' : ' ⚠️ (not in sync list - will be blocked!)';
			echo '<option value="' . esc_attr( $cal['id'] ) . '" ' . $is_selected . '>';
			echo esc_html( $cal['summary'] ) . ( $cal['primary'] ? ' (Primary)' : '' ) . $warning;
			echo '</option>';
		}
		echo '</select>';
		echo '<p class="description">Bookings will only be created/modified/deleted in this calendar. It must also be checked above!</p>';
		
		echo '<p style="margin-top: 15px;"><input type="submit" class="button button-primary" value="Save Calendar Selection"></p>';
		echo '</form>';
	}
}
