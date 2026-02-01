<?php
include 'config.php';

echo "<h3>Rental Management System - Database Migration</h3>";

try {
    // Add status column if it doesn't exist
    $alter_status = "ALTER TABLE rental_orders ADD COLUMN IF NOT EXISTS status ENUM('quotation', 'quotation_sent', 'sale_order') DEFAULT 'quotation' AFTER customer_id";
    if (mysqli_query($conn, $alter_status)) {
        echo "<p style='color: green;'>✅ Status column added/verified successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Status column may already exist</p>";
    }

    // Add is_locked column if it doesn't exist
    $alter_locked = "ALTER TABLE rental_orders ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) DEFAULT 0 AFTER status";
    if (mysqli_query($conn, $alter_locked)) {
        echo "<p style='color: green;'>✅ Is_locked column added/verified successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Is_locked column may already exist</p>";
    }

    // Add updated_at column if it doesn't exist
    $alter_updated = "ALTER TABLE rental_orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    if (mysqli_query($conn, $alter_updated)) {
        echo "<p style='color: green;'>✅ Updated_at column added/verified successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Updated_at column may already exist</p>";
    }

    // Update existing orders to have default status
    $update_existing = "UPDATE rental_orders SET status = 'quotation' WHERE status IS NULL OR status = ''";
    mysqli_query($conn, $update_existing);

    // Show current table structure
    echo "<h4>Current rental_orders table structure:</h4>";
    $describe = "DESCRIBE rental_orders";
    $result = mysqli_query($conn, $describe);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p style='color: green; font-weight: bold;'>🎉 Migration completed successfully!</p>";
    echo "<p><a href='new_order.php'>Go to New Order Page</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Migration failed: " . $e->getMessage() . "</p>";
}
?>
