<?php
/**
 * Reschedule Wizard Template
 * 
 * This template displays a booking for rescheduling.
 * It loads the V3 widget in "reschedule mode" where:
 * - Service is pre-selected and locked
 * - Contact form is pre-filled and locked
 * - Only Step 2 (calendar/time) is editable
 */

global $wpdb;

// Get token from URL
$token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

if ( empty( $token ) ) {
    ?>
    <div class="booking-widget">
        <div class="alert alert-danger text-center">
            <h4>Ungültiger Link</h4>
            <p>Der Umbuchungslink ist ungültig oder abgelaufen.</p>
            <a href="/" class="btn btn-outline-os">Zur Startseite</a>
        </div>
    </div>
    <?php
    return;
}

// Fetch booking by token
$booking = $wpdb->get_row( $wpdb->prepare(
    "SELECT a.*, s.name as service_name, s.duration_minutes, s.price_range
     FROM {$wpdb->prefix}osb_appointments a
     LEFT JOIN {$wpdb->prefix}osb_services s ON a.service_id = s.id
     WHERE a.token = %s",
    $token
) );

if ( ! $booking ) {
    ?>
    <div class="booking-widget">
        <div class="alert alert-danger text-center">
            <h4>Termin nicht gefunden</h4>
            <p>Der angegebene Termin konnte nicht gefunden werden.</p>
            <a href="/" class="btn btn-outline-os">Zur Startseite</a>
        </div>
    </div>
    <?php
    return;
}

// Check if booking can be rescheduled
$can_reschedule = in_array( $booking->status, array( 'pending', 'confirmed' ), true );
if ( ! $can_reschedule ) {
    ?>
    <div class="booking-widget">
        <div class="alert alert-warning text-center">
            <h4>Umbuchung nicht möglich</h4>
            <p>Dieser Termin kann nicht mehr umgebucht werden.</p>
            <p class="small text-muted">Status: <?php echo esc_html( $booking->status ); ?></p>
            <a href="/" class="btn btn-outline-os">Zur Startseite</a>
        </div>
    </div>
    <?php
    return;
}

// Format booking date/time for display
$booking_date = date( 'd.m.Y', strtotime( $booking->start_time ) );
$booking_time = date( 'H:i', strtotime( $booking->start_time ) );

// Get version setting
$version_setting = $wpdb->get_var( "SELECT setting_value FROM {$wpdb->prefix}osb_settings WHERE setting_key = 'osb_frontend_version'" ) ?: 'v2';
?>

<?php if ( $version_setting === 'v3' ) : ?>
<!-- V3 Reschedule Mode (JavaScript-rendered) -->
<div class="booking-widget" data-mode="reschedule" data-token="<?php echo esc_attr( $token ); ?>">
    
    <!-- Loading Overlay -->
    <div class="loading-overlay hidden">
        <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-3 text-muted">Wird geladen...</p>
    </div>

    <!-- Current Booking Info Banner -->
    <div class="reschedule-banner mb-4">
        <h4 class="mb-3">Termin verschieben</h4>
        <div class="current-booking-info">
            <p class="mb-1"><strong>Behandlung:</strong> <?php echo esc_html( $booking->service_name ); ?></p>
            <p class="mb-1"><strong>Aktueller Termin:</strong> <?php echo esc_html( $booking_date . ' um ' . $booking_time ); ?></p>
            <p class="mb-0"><strong>Name:</strong> <?php echo esc_html( $booking->client_first_name . ' ' . $booking->client_last_name ); ?></p>
        </div>
        <p class="small text-muted mt-2">Wähle unten einen neuen Termin aus.</p>
    </div>

    <!-- Progress Steps (only Step 2 active for reschedule) -->
    <div class="progress-steps">
        <div class="step-indicator completed">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/><path d="M12 6c-2 0-4 2-4 4 0 3 4 6 4 6s4-3 4-6c0-2-2-4-4-4z"/></svg>
        </div>
        <div class="step-indicator active">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="step-indicator">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="step-indicator">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
    </div>

    <!-- Step Content Area (populated by JavaScript, starts at Step 2) -->
    <div class="step-content active step-content-area" tabindex="-1">
        <!-- JavaScript will render calendar here -->
    </div>

    <!-- Footer with navigation buttons -->
    <div class="booking-footer">
        <a href="/" class="btn btn-nav btn-outline-os">Abbrechen</a>
        <button class="btn btn-nav btn-primary-os" data-action="submit-reschedule" disabled>Termin verschieben</button>
    </div>

    <!-- Hidden data for JavaScript -->
    <script type="application/json" id="reschedule-booking-data">
    <?php echo wp_json_encode( array(
        'token'           => $token,
        'service_id'      => (int) $booking->service_id,
        'service_name'    => $booking->service_name,
        'service_duration'=> (int) $booking->duration_minutes,
        'original_date'   => date( 'Y-m-d', strtotime( $booking->start_time ) ),
        'original_time'   => date( 'H:i', strtotime( $booking->start_time ) ),
        'client' => array(
            'salutation'  => $booking->client_salutation,
            'first_name'  => $booking->client_first_name,
            'last_name'   => $booking->client_last_name,
            'email'       => $booking->client_email,
            'phone'       => $booking->client_phone,
        ),
    ) ); ?>
    </script>

</div>

<?php else : ?>
<!-- V1/V2 Reschedule (Legacy) -->
<div id="osb-reschedule-wizard" class="container my-5" style="max-width: 800px;">
    
    <div class="alert alert-info mb-4">
        <h4>Termin verschieben</h4>
        <p class="mb-1"><strong>Behandlung:</strong> <?php echo esc_html( $booking->service_name ); ?></p>
        <p class="mb-1"><strong>Aktueller Termin:</strong> <?php echo esc_html( $booking_date . ' um ' . $booking_time ); ?></p>
        <p class="mb-0"><strong>Name:</strong> <?php echo esc_html( $booking->client_first_name . ' ' . $booking->client_last_name ); ?></p>
    </div>

    <h5 class="mb-3">Neuen Termin wählen</h5>
    
    <div class="row">
        <div class="col-md-6">
            <label class="form-label">Datum</label>
            <div id="osb-calendar-container" class="osb-calendar"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Uhrzeit</label>
            <div id="osb-time-slots" class="d-grid gap-2" style="max-height: 400px; overflow-y: auto;">
                <div class="text-muted text-center mt-3">Bitte wählen Sie zuerst ein Datum.</div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a href="/" class="btn btn-outline-secondary">Abbrechen</a>
        <button id="osb-submit-reschedule" class="btn btn-primary" disabled>Termin verschieben</button>
    </div>

    <!-- Hidden fields for JS -->
    <input type="hidden" id="reschedule-token" value="<?php echo esc_attr( $token ); ?>">
    <input type="hidden" id="reschedule-service-id" value="<?php echo esc_attr( $booking->service_id ); ?>">
    <input type="hidden" id="reschedule-duration" value="<?php echo esc_attr( $booking->duration_minutes ); ?>">
</div>
<?php endif; ?>
