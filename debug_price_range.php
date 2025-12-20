<?php
/**
 * Debug script to check if price_range column exists in osb_services table
 * Upload to WordPress root and run via browser: yoursite.com/debug_price_range.php
 * DELETE AFTER USE
 */

require_once('./wp-load.php');

global $wpdb;

$table = $wpdb->prefix . 'osb_services';

// Check columns
$columns = $wpdb->get_results("DESCRIBE $table");

echo "<h2>Columns in $table:</h2>";
echo "<pre>";
foreach ($columns as $col) {
    echo $col->Field . " (" . $col->Type . ")\n";
}
echo "</pre>";

// Check if price_range exists
$has_price_range = false;
foreach ($columns as $col) {
    if ($col->Field === 'price_range') {
        $has_price_range = true;
        break;
    }
}

if ($has_price_range) {
    echo "<p style='color:green;'>✅ price_range column EXISTS</p>";
    
    // Show current values
    $services = $wpdb->get_results("SELECT id, name, price, price_range FROM $table");
    echo "<h3>Current Services:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Price Range</th></tr>";
    foreach ($services as $s) {
        echo "<tr><td>{$s->id}</td><td>{$s->name}</td><td>{$s->price}</td><td>" . ($s->price_range ?: '<em>empty</em>') . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>❌ price_range column DOES NOT EXIST</p>";
    echo "<p>Run this SQL to add it:</p>";
    echo "<pre>ALTER TABLE $table ADD price_range varchar(50) DEFAULT '' AFTER price;</pre>";
}

echo "<hr><p><strong>DELETE THIS FILE AFTER USE!</strong></p>";
