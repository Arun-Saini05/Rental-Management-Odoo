<?php
include 'config.php';

echo "<h2>Creating Invoice Tables</h2>";

// Create invoices table
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

echo "<p style='color: green; font-weight: bold;'>Invoice tables setup completed!</p>";
echo "<a href='invoice.php' style='display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;'>Go to Invoice Page</a>";
?>
