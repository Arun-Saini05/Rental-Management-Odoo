<?php
include 'config.php';

echo "<h2>Checking Actual Invoice Table Columns</h2>";

$result = mysqli_query($conn, "DESCRIBE invoices");
if ($result) {
    echo "<h4>Actual Invoices Table Columns:</h4>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
        $columns[] = $row['Field'];
    }
    echo "</table>";
    
    echo "<h4>Available Columns for INSERT:</h4>";
    echo "<p>" . implode(", ", $columns) . "</p>";
} else {
    echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
}

// Show sample data
echo "<h3>Sample Invoice Data:</h3>";
$result = mysqli_query($conn, "SELECT * FROM invoices LIMIT 1");
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
    echo "<p style='color: orange;'>No invoice data found</p>";
}
?>
