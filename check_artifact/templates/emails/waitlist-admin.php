<?php
/**
 * Email Template: Waitlist Admin Notification
 * 
 * Available variables:
 * $booking - The booking object
 * $service - The service object
 * $admin_link - URL to admin dashboard
 */

$formatted_date = date( 'd.m.Y', strtotime( $booking->start_time ) );
$wait_from = $booking->wait_time_from ? date( 'H:i', strtotime( $booking->wait_time_from ) ) : 'N/A';
$wait_to = $booking->wait_time_to ? date( 'H:i', strtotime( $booking->wait_time_to ) ) : 'N/A';
?>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #6c757d;">📋 New Waitlist Entry</h2>
    
    <p>A client has joined the waitlist for a date that was fully booked.</p>
    
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #6c757d;">
        <p style="margin: 0;"><strong>Client:</strong> <?php echo esc_html( $booking->client_name ); ?></p>
        <p style="margin: 10px 0 0;"><strong>Email:</strong> <?php echo esc_html( $booking->client_email ); ?></p>
        <p style="margin: 10px 0 0;"><strong>Phone:</strong> <?php echo esc_html( $booking->client_phone ); ?></p>
        <p style="margin: 10px 0 0;"><strong>Service:</strong> <?php echo esc_html( $service->name ); ?></p>
        <p style="margin: 10px 0 0;"><strong>Preferred Date:</strong> <?php echo esc_html( $formatted_date ); ?></p>
        <p style="margin: 10px 0 0;"><strong>Time Range:</strong> <?php echo esc_html( $wait_from ); ?> - <?php echo esc_html( $wait_to ); ?></p>
        <?php if ( ! empty( $booking->client_notes ) ) : ?>
        <p style="margin: 10px 0 0;"><strong>Notes:</strong> <?php echo nl2br( esc_html( $booking->client_notes ) ); ?></p>
        <?php endif; ?>
    </div>
    
    <p>If a slot becomes available, consider contacting this client to offer the appointment.</p>
    
    <p>
        <a href="<?php echo esc_url( $admin_link ); ?>" style="display: inline-block; padding: 12px 24px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 5px;">View in Dashboard</a>
    </p>
</body>
</html>
