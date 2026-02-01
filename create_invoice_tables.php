<?php
include 'config.php';

echo "<h2>Creating Invoice Tables for Rental Management System</h2>";

// Create invoices table (without dropping existing tables first)
$create_invoices = "CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    invoice_address TEXT,
    delivery_address TEXT,
    start_date DATE,
    end_date DATE,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(5,2) DEFAULT 0.00,
    shipping DECIMAL(10,2) DEFAULT 0.00,
    tax DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('draft', 'posted') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT
)";

if (mysqli_query($conn, $create_invoices)) {
    echo "<p style='color: green;'>✓ Invoices table created successfully</p>";
} else {
    echo "<p style='color: orange;'>⚠ Invoices table already exists or error: " . mysqli_error($conn) . "</p>";
}

// Create invoice_items table
$create_invoice_items = "CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT,
    description TEXT,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    tax DECIMAL(5,2) DEFAULT 0.00,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $create_invoice_items)) {
    echo "<p style='color: green;'>✓ Invoice_items table created successfully</p>";
} else {
    echo "<p style='color: orange;'>⚠ Invoice_items table already exists or error: " . mysqli_error($conn) . "</p>";
}

// Check customers table structure
echo "<h3>Checking Customers Table Structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE customers");
if ($result) {
    echo "<h4>Customers Table Columns:</h4>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
}

// Check if we have sample customers
echo "<h3>Sample Customers:</h3>";
$result = mysqli_query($conn, "SELECT * FROM customers LIMIT 3");
if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    $first_row = mysqli_fetch_assoc($result);
    echo "<tr style='background: #f0f0f0;'>";
    foreach ($first_row as $key => $value) {
        echo "<th>$key</th>";
    }
    echo "</tr>";
    
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No customers found. You may need to create customers first.</p>";
}

// Show final table structures
echo "<h3>Final Invoice Table Structures:</h3>";

// Show invoices structure
$result = mysqli_query($conn, "DESCRIBE invoices");
if ($result) {
    echo "<h4>Invoices Table:</h4>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
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
}

echo "<p style='color: green; font-weight: bold;'>Invoice tables setup completed!</p>";
echo "<a href='create_invoice.php' style='display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;'>Go to Invoice Page</a>";
?>
