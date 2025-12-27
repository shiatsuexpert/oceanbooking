<?php
/**
 * Booking Wizard Template
 * 
 * This template supports V1, V2, and V3 frontends.
 * V3 uses a minimal HTML shell that JavaScript populates dynamically.
 */

global $wpdb;
$services = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}osb_services" );
$version_setting = $wpdb->get_var( "SELECT setting_value FROM {$wpdb->prefix}osb_settings WHERE setting_key = 'osb_frontend_version'" ) ?: 'v2';

// v2.5.0: Get current language for localized service data
$current_lang = Ocean_Shiatsu_Booking_i18n::get_current_language();

// Helper: Get localized field with fallback to German
function osb_get_localized_field( $service, $field, $lang ) {
    $en_field = $field . '_en';
    if ( $lang === 'en' && ! empty( $service->$en_field ) ) {
        return $service->$en_field;
    }
    return $service->$field ?? '';
}
?>

<?php if ( $version_setting === 'v3' ) : ?>
<!-- V3 Frontend (JavaScript-rendered) -->
<div class="booking-widget">
    
    <!-- Loading Overlay -->
    <div class="loading-overlay hidden">
        <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-3 text-muted">Wird geladen...</p>
    </div>

    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="step-indicator active" data-action="go-to-step" data-step="1">
            <svg aria-hidden="true" viewBox="0 0 576 512" fill="currentColor"><path d="M183.1 235.3c33.7 20.7 62.9 48.1 85.8 80.5c7 9.9 13.4 20.3 19.1 31c5.7-10.8 12.1-21.1 19.1-31c22.9-32.4 52.1-59.8 85.8-80.5C437.6 207.8 490.1 192 546 192l9.9 0c11.1 0 20.1 9 20.1 20.1C576 360.1 456.1 480 308.1 480L288 480l-20.1 0C119.9 480 0 360.1 0 212.1C0 201 9 192 20.1 192l9.9 0c55.9 0 108.4 15.8 153.1 43.3zM301.5 37.6c15.7 16.9 61.1 71.8 84.4 164.6c-38 21.6-71.4 50.8-97.9 85.6c-26.5-34.8-59.9-63.9-97.9-85.6c23.2-92.8 68.6-147.7 84.4-164.6C278 33.9 282.9 32 288 32s10 1.9 13.5 5.6z"/></svg>
        </div>
        <div class="step-indicator" data-action="go-to-step" data-step="2">
            <svg aria-hidden="true" viewBox="0 0 448 512" fill="currentColor"><path d="M152 24c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 40L64 64C28.7 64 0 92.7 0 128l0 16 0 48L0 448c0 35.3 28.7 64 64 64l320 0c35.3 0 64-28.7 64-64l0-256 0-48 0-16c0-35.3-28.7-64-64-64l-40 0 0-40c0-13.3-10.7-24-24-24s-24 10.7-24 24l0 40L152 64l0-40zM48 192l80 0 0 56-80 0 0-56zm0 104l80 0 0 64-80 0 0-64zm128 0l96 0 0 64-96 0 0-64zm144 0l80 0 0 64-80 0 0-64zm80-48l-80 0 0-56 80 0 0 56zm0 160l0 40c0 8.8-7.2 16-16 16l-64 0 0-56 80 0zm-128 0l0 56-96 0 0-56 96 0zm-144 0l0 56-64 0c-8.8 0-16-7.2-16-16l0-40 80 0zM272 248l-96 0 0-56 96 0 0 56z"/></svg>
        </div>
        <div class="step-indicator" data-action="go-to-step" data-step="3">
            <svg aria-hidden="true" viewBox="0 0 448 512" fill="currentColor"><path d="M304 128a80 80 0 1 0 -160 0 80 80 0 1 0 160 0zM96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM49.3 464l349.5 0c-8.9-63.3-63.3-112-129-112l-91.4 0c-65.7 0-120.1 48.7-129 112zM0 482.3C0 383.8 79.8 304 178.3 304l91.4 0C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7L29.7 512C13.3 512 0 498.7 0 482.3z"/></svg>
        </div>
        <div class="step-indicator" data-action="go-to-step" data-step="4">
            <svg aria-hidden="true" viewBox="0 0 448 512" fill="currentColor"><path d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"/></svg>
        </div>
    </div>

    <!-- Step Content Area (populated by JavaScript) -->
    <div class="step-content active step-content-area" tabindex="-1">
        <!-- Service cards with data attributes for V3 JS to parse -->
        <?php foreach ( $services as $service ) : 
            $name = osb_get_localized_field( $service, 'name', $current_lang );
            $desc = osb_get_localized_field( $service, 'description', $current_lang );
            $price_display = osb_get_localized_field( $service, 'price_range', $current_lang );
            if ( empty( $price_display ) ) {
                $price_display = number_format( $service->price, 0 ) . ' €';
            }
        ?>
        <div class="service-card"
             data-service-id="<?php echo esc_attr( $service->id ); ?>"
             data-service-name="<?php echo esc_attr( $name ); ?>"
             data-service-duration="<?php echo esc_attr( $service->duration_minutes ); ?>"
             data-service-price="<?php echo esc_attr( $price_display ); ?>"
             data-service-image="<?php echo esc_url( $service->image_url ?? '' ); ?>"
             data-service-description="<?php echo esc_attr( $desc ); ?>"
             data-action="select-service">
            <?php if ( ! empty( $service->image_url ) ) : ?>
                <img src="<?php echo esc_url( $service->image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="service-img">
            <?php endif; ?>
            <div class="service-info">
                <h5 class="mb-2"><?php echo esc_html( $name ); ?></h5>
                <?php if ( ! empty( $desc ) ) : ?>
                    <p class="service-desc mb-0"><?php echo esc_html( $desc ); ?></p>
                <?php endif; ?>
            </div>
            <div class="service-meta-block">
                <div class="service-duration-main"><?php echo esc_html( $service->duration_minutes ); ?> Min</div>
                <div class="service-cost-sub"><?php echo esc_html( $price_display ); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Footer with navigation buttons -->
    <div class="booking-footer">
        <div></div>
        <button class="btn btn-nav btn-primary-os" data-action="next-step" disabled>Weiter</button>
    </div>

</div>

<?php else : ?>
<!-- V1/V2 Frontend (Legacy) -->
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
                    <div class="card h-100 shadow-sm osb-service-card" data-id="<?php echo $service->id; ?>" data-duration="<?php echo $service->duration_minutes; ?>" data-price="<?php echo number_format( $service->price, 2, ',', '.' ); ?> €" onclick="osbApp.selectService(<?php echo $service->id; ?>, <?php echo $service->duration_minutes; ?>, '<?php echo esc_js($service->name); ?>')">
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
            <div style="width: 80px;"></div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <label class="form-label">Datum</label>
                <input type="hidden" id="osb-date-picker">
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

        <div class="row">
            <div class="col-md-8">
                <form id="osb-booking-form" onsubmit="osbApp.submitBooking(event)">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="client_salutation" class="form-label">Anrede</label>
                            <select class="form-select" id="client_salutation">
                                <option value="Frau">Frau</option>
                                <option value="Herr">Herr</option>
                                <option value="Divers">Divers</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="client_first_name" class="form-label">Vorname *</label>
                            <input type="text" class="form-control" id="client_first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="client_last_name" class="form-label">Nachname *</label>
                            <input type="text" class="form-control" id="client_last_name" required>
                        </div>
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

            <div class="col-md-4 mt-4 mt-md-0">
                <div class="osb-summary-card sticky-top" style="top: 20px; z-index: 1;">
                    <h5>Deine Buchung</h5>
                    <div class="osb-summary-item">
                        <span class="osb-summary-label">Behandlung</span>
                        <span class="osb-summary-value text-end" id="summary-service">-</span>
                    </div>
                    <div class="osb-summary-item">
                        <span class="osb-summary-label">Datum</span>
                        <span class="osb-summary-value" id="summary-date">-</span>
                    </div>
                    <div class="osb-summary-item">
                        <span class="osb-summary-label">Uhrzeit</span>
                        <span class="osb-summary-value" id="summary-time">-</span>
                    </div>
                    <div class="osb-summary-item">
                        <span class="osb-summary-label">Dauer</span>
                        <span class="osb-summary-value" id="summary-duration">-</span>
                    </div>
                </div>
            </div>
        </div>
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
<?php endif; ?>

