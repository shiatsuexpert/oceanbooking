<?php
/**
 * Email Template: Appointment Reminder (English)
 * 
 * Available variables:
 * $booking - The booking object
 * $service - The service object
 * $reschedule_link - URL to reschedule
 * $cancel_link - URL to cancel
 */

// Construct greeting
$greeting = "Hello {$booking->client_name},";
if ( ! empty( $booking->client_last_name ) ) {
    $salutation_display = '';
    if ( $booking->client_salutation === 'm' || $booking->client_salutation === 'Herr' ) {
        $salutation_display = 'Mr.';
    } elseif ( $booking->client_salutation === 'w' || $booking->client_salutation === 'Frau' ) {
        $salutation_display = 'Ms.';
    }
    $greeting = "Hello " . trim( $salutation_display . ' ' . $booking->client_last_name ) . ",";
}

$formatted_date = date( 'd/m/Y', strtotime( $booking->start_time ) );
$formatted_time = date( 'H:i', strtotime( $booking->start_time ) );
?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #2a9d8f;">⏰ Appointment Reminder</h2>
    
    <p><?php echo esc_html( $greeting ); ?></p>
    
    <p>This is a friendly reminder about your upcoming appointment.</p>
    
    <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
        <p style="margin: 0; font-size: 18px;"><strong><?php echo esc_html( $service->name ); ?></strong></p>
        <p style="margin: 10px 0 0; font-size: 20px;">
            <strong><?php echo esc_html( $formatted_date ); ?> at <?php echo esc_html( $formatted_time ); ?></strong>
        </p>
    </div>
    
    <p>If you need to reschedule or cancel, please let us know in advance:</p>
    
    <p>
        <a href="<?php echo esc_url( $reschedule_link ); ?>" style="color: #2a9d8f;">Reschedule Appointment</a>
        &nbsp;|&nbsp;
        <a href="<?php echo esc_url( $cancel_link ); ?>" style="color: #e76f51;">Cancel Appointment</a>
    </p>
    
    <p style="margin-top: 30px;">We look forward to seeing you!<br>Ocean Shiatsu Team</p>
</body>
</html>
