<?php
include 'config.php';

echo "Checking invoice_items table structure:<br>";
$result = mysqli_query($conn, "DESCRIBE invoice_items");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "invoice_items table does not exist<br>";
    echo "Error: " . mysqli_error($conn) . "<br>";
}

echo "<br>Checking invoice_lines table structure:<br>";
$result = mysqli_query($conn, "DESCRIBE invoice_lines");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "invoice_lines table does not exist<br>";
    echo "Error: " . mysqli_error($conn) . "<br>";
}
?>
