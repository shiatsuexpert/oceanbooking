// CLI Mode or Browser
if ( php_sapi_name() !== 'cli' ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		die( 'Access Denied' );
	}
}

// Just try to load WP from standard location relative to this file
// Project root: /Users/peter/Documents/AI_projects/antigravity_Ocean_Shiatsu_Booking
// This file: .../ocean-shiatsu-booking/debug_avail.php
// We likely cannot load standard WP core files because this looks like a standalone dev folder, not a full WP install.
// BUT the user instructions say "Code relating to the user's requests should be written in the locations listed above."
// And "You are not allowed to access files not in active workspaces."
// So I probably CANNOT load wp-load.php if it's outside the workspace.

// PLAN B: Mock the environment if WP is missing.
// But wait, the previous `browser_subagent` navigated to `oceanshiatsu.at/wp-admin`.
// This means the SITE exists on a remote server.
// The FILES I am editing are LOCAL.
// I cannot run PHP locally to test the remote site's DB.
// I must rely on the BROWSER to execute the script on the SERVER.
// BUT the browser got a 404 because I probably didn't UPLOAD the file.
// I am an AI editing LOCAL files. I don't have an "Upload to Server" tool.
// Only the "Sync from Google" logic runs on the server.

// WAIT. The user has "active workspaces".
// If this is a local development environment (e.g. MAMP/LocalWP), then the files ARE the server files.
// Check if wp-load.php exists in parent directories.

global $wpdb;

echo "<h2>Debug Availability: 2025-12-15 (Monday)</h2>";

$clustering = new Ocean_Shiatsu_Booking_Clustering();

// 1. Check Working Days
$working_days = json_decode( get_option( 'osb_settings' )['working_days'] ?? '[]', true );
echo "<strong>Working Days Setting:</strong> ";
var_dump( $working_days );
echo "<br>";

$day_of_week = date( 'N', strtotime( '2025-12-15' ) );
echo "<strong>Day of Week (1=Mon):</strong> $day_of_week <br>";
echo "<strong>In Array?</strong> " . ( in_array( (string)$day_of_week, $working_days ?: [] ) ? 'YES' : 'NO' ) . "<br>";

// 2. Check Busy Slots
echo "<h3>Busy Slots</h3>";
$busy = $clustering->get_busy_slots( '2025-12-15' );
echo "<pre>" . print_r( $busy, true ) . "</pre>";

// 3. Check Available Slots
echo "<h3>Available Slots</h3>";
$slots = $clustering->get_available_slots( '2025-12-15', 1 );
echo "<pre>" . print_r( $slots, true ) . "</pre>";

// 4. Check Availability Index
echo "<h3>Availability Index (DB)</h3>";
$index = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}osb_availability_index WHERE date = '2025-12-15'" );
echo "<pre>" . print_r( $index, true ) . "</pre>";

// 5. Force Re-Calculate for Dec 2025
echo "<h3>Force Re-Calculate Dec 2025</h3>";
$sync = new Ocean_Shiatsu_Booking_Sync();
$sync->calculate_monthly_availability( '2025-12' );
$index_after = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}osb_availability_index WHERE date = '2025-12-15'" );
echo "<pre>After: " . print_r( $index_after, true ) . "</pre>";
