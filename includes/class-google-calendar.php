<?php

class Ocean_Shiatsu_Booking_Google_Calendar {

	private $client;
	private $service;
	private $is_connected = false;

	public function __construct() {
		$this->init_client();
	}

	private function init_client() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		
		$client_id = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_client_id'" );
		$client_secret = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_client_secret'" );
		$access_token = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_access_token'" );
		$refresh_token = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_refresh_token'" );

		if ( $client_id && $client_secret && $access_token && class_exists( 'Google_Client' ) ) {
			try {
				$this->client = new Google_Client();
				$this->client->setClientId( $client_id );
				$this->client->setClientSecret( $client_secret );
				$this->client->setAccessToken( $access_token );
				
				// Auto Refresh
				if ( $this->client->isAccessTokenExpired() ) {
					if ( $refresh_token ) {
						$new_token = $this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
						if ( ! isset( $new_token['error'] ) ) {
							$this->client->setAccessToken( $new_token );
							// Update DB
							$wpdb->update( $table, ['setting_value' => $new_token['access_token']], ['setting_key' => 'gcal_access_token'] );
							if ( isset( $new_token['refresh_token'] ) ) {
								$wpdb->update( $table, ['setting_value' => $new_token['refresh_token']], ['setting_key' => 'gcal_refresh_token'] );
							}
						} else {
							error_log( 'OSB GCal Refresh Error: ' . json_encode( $new_token ) );
							// Token is invalid (revoked?), disconnect locally so user sees "Connect" button
							$wpdb->update( $table, ['setting_value' => ''], ['setting_key' => 'gcal_access_token'] );
							$wpdb->update( $table, ['setting_value' => ''], ['setting_key' => 'gcal_refresh_token'] );
							return;
						}
					} else {
						return; // Expired and no refresh token
					}
				}

				$this->service = new Google_Service_Calendar( $this->client );
				$this->is_connected = true;
			} catch ( Exception $e ) {
				error_log( 'OSB GCal Error: ' . $e->getMessage() );
			}
		}
	}

	public function get_calendar_list() {
		if ( ! $this->is_connected ) return [];
		try {
			$list = $this->service->calendarList->listCalendarList();
			$calendars = [];
			foreach ( $list->getItems() as $cal ) {
				$calendars[] = [
					'id' => $cal->getId(),
					'summary' => $cal->getSummary(),
					'primary' => $cal->getPrimary(),
					'timeZone' => $cal->getTimeZone()
				];
			}
			return $calendars;
		} catch ( Exception $e ) {
			error_log( 'OSB GCal List Error: ' . $e->getMessage() );
			return [];
		}
	}

	private function get_selected_calendars() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		$json = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_selected_calendars'" );
		$selected = json_decode( $json, true );
		// Fix: Check is_array to allow empty array (user unchecked all) to be valid. 
		// Only default to ['primary'] if setting is missing (null).
		return is_array( $selected ) ? $selected : ['primary'];
	}

	/**
	 * Get the calendar ID to use for write operations (create/update/delete).
	 * SAFETY: Returns false if:
	 *   1. No write calendar is explicitly configured (no default!)
	 *   2. The write calendar is not in the user's selected calendars list
	 */
	private function get_write_calendar() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		
		// Get the configured write calendar
		$write_calendar = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_write_calendar'" );
		
		// SAFETY: If not explicitly set, BLOCK all writes (no default!)
		if ( empty( $write_calendar ) ) {
			Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'GCal', 'Write calendar not configured - blocking write operation. Please select a Write Calendar in Settings.' );
			return false;
		}
		
		// SAFETY CHECK: Ensure the write calendar is in the selected calendars list
		$selected = $this->get_selected_calendars();
		if ( ! in_array( $write_calendar, $selected ) ) {
			Ocean_Shiatsu_Booking_Logger::log( 'ERROR', 'GCal', 'Write calendar not in selected list - blocking write operation', [
				'write_calendar' => $write_calendar,
				'selected_calendars' => $selected
			]);
			return false;
		}
		
		return $write_calendar;
	}

	public function get_events_for_date( $date, $skip_cache = false ) {
		if ( ! $this->is_connected ) return [];

		$cache_key = 'osb_gcal_' . $date;
		
		if ( ! $skip_cache ) {
			$cached_events = get_transient( $cache_key );
			if ( false !== $cached_events ) {
				return $cached_events;
			}
		}

		$start = $date . 'T00:00:00Z';
		$end = $date . 'T23:59:59Z';
		$calendars = $this->get_selected_calendars();
		$all_events = [];

		foreach ( $calendars as $cal_id ) {
			try {
				$optParams = array(
					'orderBy' => 'startTime',
					'singleEvents' => true,
					'timeMin' => $start,
					'timeMax' => $end,
				);
				$results = $this->service->events->listEvents( $cal_id, $optParams );
				
				foreach ( $results->getItems() as $event ) {
					// Detect All-Day events or events spanning the entire day
					$is_all_day = false;
					if ( isset( $event->start->date ) && ! isset( $event->start->dateTime ) ) {
						// Google Calendar "All-Day" checkbox event
						$is_all_day = true;
					} else if ( isset( $event->start->dateTime ) ) {
						// Check if timed event spans entire day (e.g., 8am Day1 to 8pm Day3)
						$event_start = strtotime( $event->start->dateTime );
						$event_end = strtotime( $event->end->dateTime );
						$day_start = strtotime( $date . ' 00:00:00' );
						$day_end = strtotime( $date . ' 23:59:59' );
						if ( $event_start <= $day_start && $event_end >= $day_end ) {
							$is_all_day = true;
						}
					}

					// Normalize Start/End to arrays to ensure compatibility with array syntax downstream
					// and avoid dependency on Google_Model ArrayAccess or serialization issues (Gap 2 Fix)
					$start_data = [
						'date'     => $event->start->date,
						'dateTime' => $event->start->dateTime,
						'timeZone' => $event->start->timeZone,
					];
					$end_data = [
						'date'     => $event->end->date,
						'dateTime' => $event->end->dateTime,
						'timeZone' => $event->end->timeZone,
					];

					$all_events[] = [
						'id' => $event->getId(),
						'start' => $start_data,
						'end' => $end_data,
						'summary' => $event->getSummary(),
						'is_all_day' => $is_all_day
					];
				}
			} catch ( Exception $e ) {
				error_log( "OSB GCal Error ($cal_id): " . $e->getMessage() );
			}
		}

		set_transient( $cache_key, $all_events, 3600 );
		return $all_events;
	}

	public function get_events_range( $start_date, $end_date ) {
		if ( ! $this->is_connected ) return [];

		// Check Cache (Optional: Cache the *entire* range? It might be large.
		// For now, let's rely on short API transients handled by the caller or no cache for range.)
		
		$start = $start_date . 'T00:00:00Z';
		$end = $end_date . 'T23:59:59Z';
		$calendars = $this->get_selected_calendars();
		$all_events = [];

		foreach ( $calendars as $cal_id ) {
			try {
				$optParams = array(
					'orderBy' => 'startTime',
					'singleEvents' => true,
					'timeMin' => $start,
					'timeMax' => $end,
					'maxResults' => 2500, // Fetch more for a range
				);
				
				// Handle pagination if needed, but 2500 events/month is a lot
				$results = $this->service->events->listEvents( $cal_id, $optParams );
				
				$cal_events = [];
				do {
					foreach ( $results->getItems() as $event ) {
						// Detect All-Day events or events spanning the entire day
						$is_all_day = false;
						if ( isset( $event->start->date ) && ! isset( $event->start->dateTime ) ) {
							$is_all_day = true;
						} else if ( isset( $event->start->dateTime ) ) {
							// For range queries, "spanning the entire day" is complex because we are looking at multiple days.
							// But we store the property on the event.
							// The consumer (Clustering or Sync) will check overlap with specific days.
							// However, let's keep the flag if it spans > 24 hours
							$ts_start = strtotime( $event->start->dateTime );
							$ts_end = strtotime( $event->end->dateTime );
							if ( ( $ts_end - $ts_start ) >= 86400 ) {
								$is_all_day = true;
							}
						}

						// Normalize Start/End to arrays to ensure compatibility with array syntax downstream
						// and avoid dependency on Google_Model ArrayAccess
						$start_data = [
							'date'     => $event->start->date,
							'dateTime' => $event->start->dateTime,
							'timeZone' => $event->start->timeZone,
						];
						$end_data = [
							'date'     => $event->end->date,
							'dateTime' => $event->end->dateTime,
							'timeZone' => $event->end->timeZone,
						];

						$cal_events[] = [
							'id' => $event->getId(),
							'start' => $start_data,
							'end' => $end_data,
							'summary' => $event->getSummary(),
							'is_all_day' => $is_all_day,
							'calendar_id' => $cal_id // Debug info
						];
					}
					
					// Get next page
					$pageToken = $results->getNextPageToken();
					if ( $pageToken ) {
						$optParams['pageToken'] = $pageToken;
						$results = $this->service->events->listEvents( $cal_id, $optParams );
					} else {
						break;
					}
				} while ( $pageToken );

				$all_events = array_merge( $all_events, $cal_events );

			} catch ( Exception $e ) {
				error_log( "OSB GCal Range Error ($cal_id): " . $e->getMessage() );
			}
		}

		return $all_events;
	}

	public function get_modified_events( $since_timestamp ) {
		if ( ! $this->is_connected ) return [];

		$calendars = $this->get_selected_calendars();
		$all_events = [];
		$primary_id = 'primary';

		try {
			$optParams = array(
				'orderBy' => 'updated',
				'singleEvents' => true,
				'updatedMin' => $since_timestamp,
				'showDeleted' => true,
			);
			
			$results = $this->service->events->listEvents( $primary_id, $optParams );
			
			foreach ( $results->getItems() as $event ) {
				$status = $event->getStatus();
				$start = null;
				$end = null;

				if ( $status !== 'cancelled' ) {
					$start = $event->start->dateTime ?: $event->start->date;
					$end = $event->end->dateTime ?: $event->end->date;
				}

				$all_events[] = [
					'id' => $event->getId(),
					'status' => $status,
					'start' => $start,
					'end' => $end,
					'summary' => $event->getSummary()
				];
			}
		} catch ( Exception $e ) {
			error_log( "OSB GCal Sync Error: " . $e->getMessage() );
		}

		return $all_events;
	}

	public function get_oauth_url( $client_id ) {
		$redirect_uri = admin_url( 'admin.php?page=osb-settings&action=oauth_callback' );
		$scope = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly';
		
		$params = array(
			'response_type' => 'code',
			'client_id' => $client_id,
			'redirect_uri' => $redirect_uri,
			'scope' => $scope,
			'access_type' => 'offline',
			'prompt' => 'consent'
		);
		
		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}



	public function is_connected() {
		return $this->is_connected;
	}



	public function create_event( $appointment_data ) {
		if ( ! $this->is_connected ) return '';

		// SAFETY: Get validated write calendar
		$calendarId = $this->get_write_calendar();
		if ( ! $calendarId ) {
			error_log( 'OSB GCal: Create blocked - no valid write calendar configured' );
			return '';
		}

		try {
			$event = new Google_Service_Calendar_Event( array(
				'summary' => '[PENDING] ' . $appointment_data['client_name'] . ' - ' . $appointment_data['service_name'],
				'description' => 'Phone: ' . $appointment_data['client_phone'] . "\nNotes: " . $appointment_data['client_notes'],
				'start' => array(
					'dateTime' => $appointment_data['date'] . 'T' . $appointment_data['time'] . ':00',
					'timeZone' => $this->get_timezone(),
				),
				'end' => array(
					'dateTime' => date( 'Y-m-d\TH:i:s', strtotime( $appointment_data['date'] . ' ' . $appointment_data['time'] ) + ( $appointment_data['duration'] * 60 ) ),
					'timeZone' => $this->get_timezone(),
				),
			) );

			$event = $this->service->events->insert( $calendarId, $event );
			
			delete_transient( 'osb_gcal_' . $appointment_data['date'] );

			return $event->getId();
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Insert Error: ' . $e->getMessage() );
			return '';
		}
	}

	public function delete_event( $event_id ) {
		if ( ! $this->is_connected || ! $event_id ) return;

		// SAFETY: Get validated write calendar
		$calendarId = $this->get_write_calendar();
		if ( ! $calendarId ) {
			error_log( 'OSB GCal: Delete blocked - no valid write calendar configured' );
			return;
		}

		try {
			$this->service->events->delete( $calendarId, $event_id );
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Delete Error: ' . $e->getMessage() );
		}
	}

	public function update_event_time( $event_id, $new_date, $new_time, $duration ) {
		if ( ! $this->is_connected || ! $event_id ) return false;

		// SAFETY: Get validated write calendar
		$calendarId = $this->get_write_calendar();
		if ( ! $calendarId ) {
			error_log( 'OSB GCal: Update time blocked - no valid write calendar configured' );
			return false;
		}

		try {
			$event = $this->service->events->get( $calendarId, $event_id );
			
			$start_dt = $new_date . 'T' . $new_time . ':00';
			$end_dt = date( 'Y-m-d\TH:i:s', strtotime( $new_date . ' ' . $new_time ) + ( $duration * 60 ) );

			$start = new Google_Service_Calendar_EventDateTime();
			$start->setDateTime( $start_dt );
			$start->setTimeZone( $this->get_timezone() );
			$event->setStart( $start );

			$end = new Google_Service_Calendar_EventDateTime();
			$end->setDateTime( $end_dt );
			$end->setTimeZone( $this->get_timezone() );
			$event->setEnd( $end );

			$this->service->events->update( $calendarId, $event->getId(), $event );
			
			delete_transient( 'osb_gcal_' . $new_date );
			
			return true;
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Update Time Error: ' . $e->getMessage() );
			return false;
		}
	}

	public function update_event_status( $event_id, $status ) {
		if ( ! $this->is_connected || ! $event_id ) return;

		// SAFETY: Get validated write calendar
		$calendarId = $this->get_write_calendar();
		if ( ! $calendarId ) {
			error_log( 'OSB GCal: Update status blocked - no valid write calendar configured' );
			return;
		}

		try {
			$event = $this->service->events->get( $calendarId, $event_id );
			$summary = $event->getSummary();
			
			if ( $status === 'confirmed' ) {
				$summary = str_replace( '[PENDING] ', '', $summary );
				$event->setSummary( $summary );
			}

			$this->service->events->update( $calendarId, $event->getId(), $event );
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Update Error: ' . $e->getMessage() );
		}
	}
	public function watch_calendar( $calendar_id ) {
		if ( ! $this->is_connected ) return false;

		$channel_id = 'osb_watch_' . md5( $calendar_id . uniqid() );
		$token = get_option( 'osb_webhook_token' );
		if ( ! $token ) {
			$token = wp_generate_password( 32, false );
			update_option( 'osb_webhook_token', $token );
		}

		$channel = new Google_Service_Calendar_Channel();
		$channel->setId( $channel_id );
		$channel->setType( 'web_hook' );
		$channel->setAddress( get_rest_url( null, 'osb/v1/gcal-webhook' ) );
		$channel->setToken( $token );
		// $channel->setExpiration( (time() + 604800) * 1000 ); // 7 days in ms (optional, Google sets default)

		try {
			$result = $this->service->events->watch( $calendar_id, $channel );
			
			// Store Channel Info
			$channels = get_option( 'osb_watch_channels', [] );
			$channels[ $calendar_id ] = [
				'channel_id' => $result->getId(),
				'resource_id' => $result->getResourceId(),
				// 'expiration' => ( time() + 600000 ), // Removed confusing hardcoded value
				'expiration_ts' => $result->getExpiration() / 1000 // Convert ms to seconds
			];
			update_option( 'osb_watch_channels', $channels );
			
			return true;
		} catch ( Exception $e ) {
			error_log( "OSB GCal Watch Error ($calendar_id): " . $e->getMessage() );
			return false;
		}
	}

	public function stop_watch( $channel_id, $resource_id ) {
		if ( ! $this->is_connected ) return;

		$channel = new Google_Service_Calendar_Channel();
		$channel->setId( $channel_id );
		$channel->setResourceId( $resource_id );

		try {
			$this->service->channels->stop( $channel );
		} catch ( Exception $e ) {
			error_log( "OSB GCal Stop Watch Error: " . $e->getMessage() );
		}
	}

	public function renew_watches() {
		if ( ! $this->is_connected ) return;

		$channels = get_option( 'osb_watch_channels', [] );
		$now = time();

		foreach ( $channels as $cal_id => $data ) {
			// Renew if expiring in less than 24 hours
			if ( isset( $data['expiration_ts'] ) && ( $data['expiration_ts'] - $now ) < 86400 ) {
				$this->stop_watch( $data['channel_id'], $data['resource_id'] );
				$this->watch_calendar( $cal_id );
			}
		}
	}

	public static function renew_watches_static() {
		$instance = new self();
		$instance->renew_watches();
	}

	private function get_timezone() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		$timezone = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'timezone'" );
		return $timezone ?: 'Europe/Berlin';
	}
}
