<?php

class Ocean_Shiatsu_Booking_Google_Calendar {

	private $client;
	private $service;
	private $calendar_id = 'primary';
	private $is_connected = false;

	public function __construct() {
		$this->init_client();
	}

	private function init_client() {
		global $wpdb;
		$table = $wpdb->prefix . 'osb_settings';
		$json_key = $wpdb->get_var( "SELECT setting_value FROM $table WHERE setting_key = 'gcal_credentials'" );

		if ( $json_key && class_exists( 'Google_Client' ) ) {
			try {
				$decoded = json_decode( $json_key, true );
				if ( ! $decoded ) return;

				$this->client = new Google_Client();
				$this->client->setAuthConfig( $decoded );
				$this->client->addScope( Google_Service_Calendar::CALENDAR );
				$this->client->setSubject( $decoded['client_email'] ); // Service Account Email
				
				$this->service = new Google_Service_Calendar( $this->client );
				$this->is_connected = true;
			} catch ( Exception $e ) {
				error_log( 'OSB GCal Error: ' . $e->getMessage() );
			}
		}
	}

	public function get_events_for_date( $date ) {
		if ( ! $this->is_connected ) return [];

		$cache_key = 'osb_gcal_' . $date;
		$cached_events = get_transient( $cache_key );
		if ( false !== $cached_events ) {
			return $cached_events;
		}

		$start = $date . 'T00:00:00Z';
		$end = $date . 'T23:59:59Z';

		try {
			$optParams = array(
				'orderBy' => 'startTime',
				'singleEvents' => true,
				'timeMin' => $start,
				'timeMax' => $end,
			);
			$results = $this->service->events->listEvents( $this->calendar_id, $optParams );
			
			$events = [];
			foreach ( $results->getItems() as $event ) {
				$events[] = [
					'id' => $event->getId(),
					'start' => $event->start,
					'end' => $event->end,
					'summary' => $event->getSummary()
				];
			}

			// Cache for 1 minute (60 seconds) to ensure freshness while preventing spam
			set_transient( $cache_key, $events, 60 );

			return $events;
		} catch ( Exception $e ) {
			error_log( 'OSB GCal List Error: ' . $e->getMessage() );
			return false; // Return false to indicate error vs empty
		}
	}

	public function get_events_range( $start_date, $end_date ) {
		if ( ! $this->is_connected ) return [];

		$start = $start_date . 'T00:00:00Z';
		$end = $end_date . 'T23:59:59Z';

		try {
			$optParams = array(
				'orderBy' => 'startTime',
				'singleEvents' => true,
				'timeMin' => $start,
				'timeMax' => $end,
				'maxResults' => 250,
			);
			$results = $this->service->events->listEvents( $this->calendar_id, $optParams );
			
			$events = [];
			foreach ( $results->getItems() as $event ) {
				$events[] = [
					'id' => $event->getId(),
					'start' => $event->start->dateTime ?: $event->start->date, // Handle all-day events
					'end' => $event->end->dateTime ?: $event->end->date,
					'summary' => $event->getSummary()
				];
			}
			return $events;
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Range Error: ' . $e->getMessage() );
			return [];
		}
	}

	public function create_event( $appointment_data ) {
		if ( ! $this->is_connected ) return '';

		try {
			$event = new Google_Service_Calendar_Event( array(
				'summary' => '[PENDING] ' . $appointment_data['client_name'] . ' - ' . $appointment_data['service_name'],
				'description' => 'Phone: ' . $appointment_data['client_phone'] . "\nNotes: " . $appointment_data['client_notes'],
				'start' => array(
					'dateTime' => $appointment_data['date'] . 'T' . $appointment_data['time'] . ':00',
					'timeZone' => 'Europe/Berlin', // Should be configurable
				),
				'end' => array(
					'dateTime' => date( 'Y-m-d\TH:i:s', strtotime( $appointment_data['date'] . ' ' . $appointment_data['time'] ) + ( $appointment_data['duration'] * 60 ) ),
					'timeZone' => 'Europe/Berlin',
				),
			) );

			$calendarId = 'primary';
			$event = $this->service->events->insert( $calendarId, $event );
			
			// Clear cache for this date so the new event is seen immediately if re-fetched
			delete_transient( 'osb_gcal_' . $appointment_data['date'] );

			return $event->getId();
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Insert Error: ' . $e->getMessage() );
			return '';
		}
	}

	public function delete_event( $event_id ) {
		if ( ! $this->is_connected || ! $event_id ) return;

		try {
			$this->service->events->delete( 'primary', $event_id );
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Delete Error: ' . $e->getMessage() );
		}
	}

	public function update_event_status( $event_id, $status ) {
		if ( ! $this->is_connected || ! $event_id ) return;

		try {
			$event = $this->service->events->get( 'primary', $event_id );
			$summary = $event->getSummary();
			
			if ( $status === 'confirmed' ) {
				$summary = str_replace( '[PENDING] ', '', $summary );
				$event->setSummary( $summary );
				// Could change colorId here if desired
			}

			$this->service->events->update( 'primary', $event->getId(), $event );
		} catch ( Exception $e ) {
			error_log( 'OSB GCal Update Error: ' . $e->getMessage() );
		}
	}
}
