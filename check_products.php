<?php
include 'config.php';

echo "<h2>Checking Products Table</h2>";

// Show products table structure
$result = mysqli_query($conn, "DESCRIBE products");
if ($result) {
    echo "<h4>Products Table Columns:</h4>";
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

// Show sample products
echo "<h3>Sample Products:</h3>";
$result = mysqli_query($conn, "SELECT * FROM products LIMIT 5");
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
    echo "<p style='color: orange;'>No products found</p>";
}

// Check invoice_items table structure
echo "<h3>Invoice Items Table Structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE invoice_items");
if ($result) {
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
?>
