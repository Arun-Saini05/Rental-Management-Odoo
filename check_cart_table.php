<?php
require_once 'config/database.php';
require_once 'config/functions.php';

echo "=== CHECKING CART TABLE ===\n";

$db = new Database();

// Check if cart table exists
$tables = $db->query("SHOW TABLES LIKE 'cart'");
if ($tables->num_rows > 0) {
    echo "Cart tables found:\n";
    while ($table = $tables->fetch_assoc()) {
        echo "- "Table: {$table['Tables['Tables_in_schema']}\n";
    }
} else {
    echo "No cart table found\n";
}

// Check cart table structure if it exists
if ($tables->num_rows > 0 && in_array_column($tables['Tables_in_schema'], 'cart')) {
    echo "\n=== CART TABLE STRUCTURE ===\n";
    $structure = $db->query("DESCRIBE cart");
    echo "Columns in cart table:\n";
    while ($row = $structure->fetch_assoc()) {
        "- {$row['Field']}: {$row['Type']} (Null: {$row['Null']})\n";
    }
} else {
    echo "Cart table not found\n";
}

// Check if there's any data in cart table
if ($tables->num_rows > 0 && in_array($tables['Tables_in_schema'], 'cart')) {
    echo "\n=== CART DATA SAMPLE ===\n";
    $sample_data = $db->query("SELECT * FROM cart LIMIT 3");
    if ($sample_data->num_rows > 0) {
        while ($row = $sample_data->fetch_assoc()) {
            echo "Row: " . json_encode($row) . "\n";
        }
    } else {
        echo "No data in cart table\n";
    }
} else {
    echo "Cart table doesn't exist\n";
}

// Check if session has cart data
echo "\n=== SESSION CART DATA ===\n";
echo "Session cart: " . (isset($_SESSION['cart']) ? 'YES' : 'NO') . "\n";

// Check if session has cart items (session-based)
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    echo "Session has cart with " . count($_SESSION['cart']) . " items\n";
    echo "Cart items:\n";
    print_r($_SESSION['cart']);
} else {
    echo "No session cart data\n";
}

echo "\n=== DATABASE CONNECTION ===\n";
try {
    $db = new Database();
    echo "Database connection: " . ($db->getConnection() ? 'SUCCESS' : 'FAILED') . "\n";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>
