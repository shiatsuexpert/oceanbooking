<?php
/**
 * Email Template: Booking Confirmation (German)
 * 
 * Available variables:
 * $booking - The booking object
 * $service - The service object
 * $reschedule_link - URL to reschedule
 * $cancel_link - URL to cancel
 */

// Construct greeting
$greeting = "Hallo {$booking->client_name},";
if ( ! empty( $booking->client_last_name ) ) {
    $salutation_display = '';
    if ( $booking->client_salutation === 'm' || $booking->client_salutation === 'Herr' ) {
        $salutation_display = 'Herr';
    } elseif ( $booking->client_salutation === 'w' || $booking->client_salutation === 'Frau' ) {
        $salutation_display = 'Frau';
    }
    $greeting = "Hallo " . trim( $salutation_display . ' ' . $booking->client_last_name ) . ",";
}

$formatted_date = date( 'd.m.Y', strtotime( $booking->start_time ) );
$formatted_time = date( 'H:i', strtotime( $booking->start_time ) );
?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2a9d8f;">Termin bestätigt</h2>
    
    <p><?php echo esc_html( $greeting ); ?></p>
    
    <p>Vielen Dank für Ihre Buchung! Ihr Termin wurde bestätigt.</p>
    
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <p style="margin: 0;"><strong>Behandlung:</strong> <?php echo esc_html( $service->name ); ?></p>
        <p style="margin: 10px 0 0;"><strong>Datum:</strong> <?php echo esc_html( $formatted_date ); ?></p>
        <p style="margin: 10px 0 0;"><strong>Uhrzeit:</strong> <?php echo esc_html( $formatted_time ); ?> Uhr</p>
    </div>
    
    <p>Falls Sie den Termin verschieben oder absagen müssen:</p>
    
    <p>
        <a href="<?php echo esc_url( $reschedule_link ); ?>" style="color: #2a9d8f;">Termin verschieben</a>
        &nbsp;|&nbsp;
        <a href="<?php echo esc_url( $cancel_link ); ?>" style="color: #e76f51;">Termin absagen</a>
    </p>
    
    <p style="margin-top: 30px;">Herzliche Grüße,<br>Ihr Ocean Shiatsu Team</p>
</body>
</html>
