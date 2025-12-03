<?php
// Fetch services for initial render
global $wpdb;
$services = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}osb_services" );
?>

<div id="osb-booking-wizard" class="container my-5" style="max-width: 800px;">
    
    <!-- Progress Bar -->
    <div class="progress mb-4" style="height: 5px;">
        <div id="osb-progress" class="progress-bar bg-primary" role="progressbar" style="width: 25%;"></div>
    </div>

    <!-- Step 1: Service Selection -->
    <div id="step-1" class="osb-step">
        <h3 class="mb-4 text-center">Wählen Sie eine Behandlung</h3>
        <div class="row g-4">
            <?php foreach ( $services as $service ) : ?>
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm osb-service-card" onclick="osbApp.selectService(<?php echo $service->id; ?>, <?php echo $service->duration_minutes; ?>, '<?php echo esc_js($service->name); ?>')">
                        <?php if ( ! empty( $service->image_url ) ) : ?>
                            <img src="<?php echo esc_url( $service->image_url ); ?>" class="card-img-top" alt="<?php echo esc_attr( $service->name ); ?>" style="height: 200px; object-fit: cover;">
                        <?php endif; ?>
                        <div class="card-body text-center p-4">
                            <h5 class="card-title"><?php echo esc_html( $service->name ); ?></h5>
                            <?php if ( ! empty( $service->description ) ) : ?>
                                <p class="card-text small"><?php echo esc_html( $service->description ); ?></p>
                            <?php endif; ?>
                            <p class="card-text text-muted">
                                <i class="bi bi-clock"></i> <?php echo esc_html( $service->duration_minutes ); ?> Min.
                                <?php if ( $service->preparation_minutes > 0 ) : ?>
                                    <span class="text-muted small">(+<?php echo $service->preparation_minutes; ?> Prep)</span>
                                <?php endif; ?>
                            </p>
                            <h4 class="text-primary"><?php echo number_format( $service->price, 2, ',', '.' ); ?> €</h4>
                            <button class="btn btn-outline-primary mt-3">Auswählen</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Step 2: Date & Time -->
    <div id="step-2" class="osb-step d-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-link text-decoration-none" onclick="osbApp.prevStep()">← Zurück</button>
            <h3 class="m-0">Termin wählen</h3>
            <div style="width: 80px;"></div> <!-- Spacer -->
        </div>

        <div class="row">
            <div class="col-md-6">
                <label class="form-label">Datum</label>
                <!-- Hidden input to store selected date -->
                <input type="hidden" id="osb-date-picker">
                <!-- Custom Calendar Container -->
                <div id="osb-calendar-container" class="osb-calendar"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Uhrzeit</label>
                <div id="osb-time-slots" class="d-grid gap-2" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-muted text-center mt-3">Bitte wählen Sie zuerst ein Datum.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3: Client Details -->
    <div id="step-3" class="osb-step d-none">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-link text-decoration-none" onclick="osbApp.prevStep()">← Zurück</button>
            <h3 class="m-0">Ihre Daten</h3>
            <div style="width: 80px;"></div>
        </div>

        <form id="osb-booking-form" onsubmit="osbApp.submitBooking(event)">
            <div class="mb-3">
                <label for="client_name" class="form-label">Name *</label>
                <input type="text" class="form-control" id="client_name" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="client_email" class="form-label">E-Mail *</label>
                    <input type="email" class="form-control" id="client_email" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="client_phone" class="form-label">Telefon *</label>
                    <input type="tel" class="form-control" id="client_phone" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="client_notes" class="form-label">Nachricht (Optional)</label>
                <textarea class="form-control" id="client_notes" rows="3"></textarea>
            </div>
            
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Termin anfragen</button>
            </div>
        </form>
    </div>

    <!-- Step 4: Success -->
    <div id="step-4" class="osb-step d-none text-center py-5">
        <div class="mb-4 text-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
            </svg>
        </div>
        <h3>Vielen Dank!</h3>
        <p class="lead">Ihre Terminanfrage wurde erfolgreich gesendet.</p>
        <p>Sie erhalten in Kürze eine Bestätigung per E-Mail.</p>
        <button class="btn btn-outline-primary mt-3" onclick="location.reload()">Neue Buchung</button>
    </div>

    <!-- Loading Overlay -->
    <div id="osb-loading" class="position-absolute top-0 start-0 w-100 h-100 bg-white d-none justify-content-center align-items-center" style="opacity: 0.8; z-index: 1000;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>
