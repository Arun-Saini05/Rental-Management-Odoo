<?php
// Database setup script
$host = 'localhost';
$username = 'root';
$password = '';

// Create connection without database
$conn = mysqli_connect($host, $username, $password);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS rental_management";
if (mysqli_query($conn, $sql)) {
    echo "Database created successfully or already exists\n";
} else {
    echo "Error creating database: " . mysqli_error($conn) . "\n";
}

// Select database
mysqli_select_db($conn, 'rental_management');

// Create tables
$create_users_table = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'vendor', 'customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$create_customers_table = "
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$create_products_table = "
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    rental_price DECIMAL(10,2) NOT NULL,
    category VARCHAR(100),
    stock_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$create_rental_orders_table = "
CREATE TABLE IF NOT EXISTS rental_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    product_id INT,
    rental_duration VARCHAR(50),
    start_date DATE,
    end_date DATE,
    status ENUM('sale_order', 'quotation', 'invoiced', 'confirmed', 'cancelled') DEFAULT 'quotation',
    total_amount DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
)";

// Execute table creation
$tables = [$create_users_table, $create_customers_table, $create_products_table, $create_rental_orders_table];

foreach ($tables as $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "Table created successfully or already exists\n";
    } else {
        echo "Error creating table: " . mysqli_error($conn) . "\n";
    }
}

// Insert sample data
// Check if data already exists
$check_customers = "SELECT COUNT(*) as count FROM customers";
$result = mysqli_query($conn, $check_customers);
$row = mysqli_fetch_assoc($result);

if ($row['count'] == 0) {
    // Insert sample customers
    $customers = [
        ["John", "Smith", "john.smith@email.com", "555-0101", "123 Main St, City, State"],
        ["Mark", "Wood", "mark.wood@email.com", "555-0102", "456 Oak Ave, City, State"],
        ["Alex", "Johnson", "alex.johnson@email.com", "555-0103", "789 Pine Rd, City, State"],
        ["Sarah", "Williams", "sarah.williams@email.com", "555-0104", "321 Elm St, City, State"],
        ["Mike", "Brown", "mike.brown@email.com", "555-0105", "654 Maple Dr, City, State"]
    ];

    foreach ($customers as $customer) {
        $sql = "INSERT INTO customers (first_name, last_name, email, phone, address) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssss", $customer[0], $customer[1], $customer[2], $customer[3], $customer[4]);
        mysqli_stmt_execute($stmt);
    }

    // Insert sample products
    $products = [
        ["TV", "55-inch Smart TV", 1450.00, "Electronics", 10],
        ["Printer", "Laser Printer", 50.00, "Office", 15],
        ["Car", "Sedan Rental", 775.00, "Vehicle", 5],
        ["Projector", "HD Projector", 14.50, "Electronics", 8],
        ["Games", "Video Game Console", 50.00, "Entertainment", 12]
    ];

    foreach ($products as $product) {
        $sql = "INSERT INTO products (name, description, rental_price, category, stock_quantity) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssdsi", $product[0], $product[1], $product[2], $product[3], $product[4]);
        mysqli_stmt_execute($stmt);
    }

    // Insert sample rental orders
    $orders = [
        [1, 1, "7 days", "2024-01-15", "2024-01-22", "sale_order", 1450.00],
        [2, 2, "3 days", "2024-01-16", "2024-01-19", "quotation", 50.00],
        [3, 3, "2 days", "2024-01-17", "2024-01-19", "invoiced", 775.00],
        [4, 4, "1 day", "2024-01-18", "2024-01-19", "confirmed", 14.50],
        [5, 5, "5 days", "2024-01-19", "2024-01-24", "cancelled", 50.00],
        [1, 3, "3 days", "2024-01-20", "2024-01-23", "confirmed", 775.00],
        [2, 1, "1 day", "2024-01-21", "2024-01-22", "sale_order", 1450.00]
    ];

    foreach ($orders as $order) {
        $sql = "INSERT INTO rental_orders (customer_id, product_id, rental_duration, start_date, end_date, status, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iisssd", $order[0], $order[1], $order[2], $order[3], $order[4], $order[5], $order[6]);
        mysqli_stmt_execute($stmt);
    }

    echo "Sample data inserted successfully\n";
} else {
    echo "Sample data already exists\n";
}

mysqli_close($conn);
echo "Setup completed successfully!\n";
echo "<br><a href='login.php'>Go to Login</a>";
?>
