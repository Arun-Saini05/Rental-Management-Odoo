<?php
include 'config.php';

echo "<h2>Database Check</h2>";

// Check if tables exist
$tables = ['users', 'customers', 'products', 'rental_orders'];

foreach ($tables as $table) {
    $check = "SHOW TABLES LIKE '$table'";
    $result = mysqli_query($conn, $check);
    
    if (mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        
        // Show structure
        $structure = mysqli_query($conn, "DESCRIBE $table");
        echo "<pre>";
        echo "Structure of $table:\n";
        while ($row = mysqli_fetch_assoc($structure)) {
            echo "- {$row['Field']} ({$row['Type']})\n";
        }
        echo "</pre>";
        
        // Show data count
        $count = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
        $count_row = mysqli_fetch_assoc($count);
        echo "<p>Records in $table: {$count_row['count']}</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
    }
    echo "<hr>";
}

// Test the query from dashboard
echo "<h3>Testing Dashboard Query</h3>";
$test_query = "SELECT ro.id, c.first_name, c.last_name, p.name as product_name, p.rental_price, ro.rental_duration, ro.status
                FROM rental_orders ro 
                LEFT JOIN customers c ON ro.customer_id = c.id 
                LEFT JOIN products p ON ro.product_id = p.id 
                ORDER BY ro.created_at DESC";

if ($result = mysqli_query($conn, $test_query)) {
    echo "<p style='color: green;'>✓ Query executed successfully</p>";
    echo "<pre>";
    while ($row = mysqli_fetch_assoc($result)) {
        print_r($row);
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>✗ Query failed: " . mysqli_error($conn) . "</p>";
}

mysqli_close($conn);
?>
