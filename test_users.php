<?php
include 'config.php';

echo "<h2>Testing Users in Database</h2>";

// Test 1: Check if users table exists and has data
echo "<h3>1. Users Table:</h3>";
$users_query = "SELECT COUNT(*) as count FROM users";
$result = mysqli_query($conn, $users_query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Total users in database: " . $row['count'] . "<br>";
    
    if ($row['count'] > 0) {
        echo "<h4>First 5 users:</h4>";
        $users_list_query = "SELECT id, name, email, role, created_at FROM users LIMIT 5";
        $users_list_result = mysqli_query($conn, $users_list_query);
        while ($user = mysqli_fetch_assoc($users_list_result)) {
            echo "ID: " . $user['id'] . " - Name: " . htmlspecialchars($user['name']) . " - Email: " . htmlspecialchars($user['email']) . " - Role: " . $user['role'] . "<br>";
        }
    }
} else {
    echo "Error checking users table: " . mysqli_error($conn) . "<br>";
}

// Test 2: Check customers table
echo "<h3>2. Customers Table:</h3>";
$customers_query = "SELECT COUNT(*) as count FROM customers";
$result = mysqli_query($conn, $customers_query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Total customers in database: " . $row['count'] . "<br>";
    
    if ($row['count'] > 0) {
        echo "<h4>First 5 customers:</h4>";
        $customers_list_query = "SELECT c.id, u.name, u.email, c.created_at FROM customers c LEFT JOIN users u ON c.user_id = u.id LIMIT 5";
        $customers_list_result = mysqli_query($conn, $customers_list_query);
        while ($customer = mysqli_fetch_assoc($customers_list_result)) {
            echo "ID: " . $customer['id'] . " - Name: " . htmlspecialchars($customer['name']) . " - Email: " . htmlspecialchars($customer['email']) . "<br>";
        }
    }
} else {
    echo "Error checking customers table: " . mysqli_error($conn) . "<br>";
}

// Test 3: Check rental_orders table
echo "<h3>3. Rental Orders Table:</h3>";
$orders_query = "SELECT COUNT(*) as count FROM rental_orders";
$result = mysqli_query($conn, $orders_query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "Total rental orders in database: " . $row['count'] . "<br>";
} else {
    echo "Error checking rental_orders table: " . mysqli_error($conn) . "<br>";
}

// Test 4: Try the exact query from dashboard
echo "<h3>4. Dashboard Query Test:</h3>";
$dashboard_query = "SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
               COUNT(DISTINCT ro.id) as total_orders,
               SUM(CASE WHEN ro.status = 'completed' THEN 1 ELSE 0 END) as sale_orders,
               SUM(CASE WHEN ro.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
               SUM(CASE WHEN ro.status = 'sent' THEN 1 ELSE 0 END) as invoiced_orders,
               SUM(CASE WHEN ro.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
               SUM(CASE WHEN ro.status = 'draft' THEN 1 ELSE 0 END) as quotation_orders
               FROM users u 
               LEFT JOIN customers c ON u.id = c.user_id 
               LEFT JOIN rental_orders ro ON c.id = ro.customer_id
               WHERE u.role = 'customer' OR u.role IS NULL
               GROUP BY u.id, u.name, u.email, u.phone, u.role, u.created_at
               ORDER BY u.created_at DESC";
$dashboard_result = mysqli_query($conn, $dashboard_query);
if ($dashboard_result) {
    $count = mysqli_num_rows($dashboard_result);
    echo "Dashboard query returned: " . $count . " users<br>";
    
    if ($count > 0) {
        echo "<h4>First 3 users from dashboard query:</h4>";
        $counter = 0;
        while ($user = mysqli_fetch_assoc($dashboard_result) && $counter < 3) {
            echo "User: " . htmlspecialchars($user['name']) . " - Total Orders: " . $user['total_orders'] . " - Sale: " . $user['sale_orders'] . "<br>";
            $counter++;
        }
    }
} else {
    echo "Error with dashboard query: " . mysqli_error($conn) . "<br>";
}

echo "<br><a href='dashboard.php'>Go to Dashboard</a>";
?>
