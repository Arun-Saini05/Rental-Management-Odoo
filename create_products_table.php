<?php
include 'config.php';

// Create products table exactly as specified in the prompt
$sql = "CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  type ENUM('goods','service'),
  quantity INT DEFAULT 0,
  sales_price DECIMAL(10,2),
  cost_price DECIMAL(10,2),
  unit VARCHAR(50),
  category VARCHAR(100),
  vendor_id INT,
  image VARCHAR(255),
  attributes JSON,
  is_published TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Products table created successfully or already exists.<br><br>";
    
    // Show table structure
    echo "<h3>📋 Products Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0; background-color: white;'>";
    echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $check_sql = "DESCRIBE products";
    $result = mysqli_query($conn, $check_sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td style='padding: 8px;'><strong>" . $row['Field'] . "</strong></td>";
        echo "<td style='padding: 8px;'>" . $row['Type'] . "</td>";
        echo "<td style='padding: 8px;'>" . $row['Null'] . "</td>";
        echo "<td style='padding: 8px;'>" . $row['Key'] . "</td>";
        echo "<td style='padding: 8px;'>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Insert sample products if table is empty
    $count_sql = "SELECT COUNT(*) as count FROM products";
    $count_result = mysqli_query($conn, $count_sql);
    $count = mysqli_fetch_assoc($count_result)['count'];
    
    if ($count == 0) {
        echo "<h3>📦 Inserting Sample Products...</h3>";
        
        $sample_products = [
            [
                'name' => 'Laptop Rental',
                'type' => 'goods',
                'quantity' => 10,
                'sales_price' => 50.00,
                'cost_price' => 30.00,
                'unit' => 'Days',
                'category' => 'Electronics',
                'vendor_id' => 1,
                'is_published' => 1,
                'attributes' => json_encode([
                    ['name' => 'Brand', 'values' => ['Dell', 'HP', 'Lenovo']],
                    ['name' => 'RAM', 'values' => ['8GB', '16GB', '32GB']]
                ])
            ],
            [
                'name' => 'Office Furniture Rental',
                'type' => 'goods',
                'quantity' => 5,
                'sales_price' => 25.00,
                'cost_price' => 15.00,
                'unit' => 'Weeks',
                'category' => 'Furniture',
                'vendor_id' => 1,
                'is_published' => 1,
                'attributes' => json_encode([
                    ['name' => 'Type', 'values' => ['Desk', 'Chair', 'Table']],
                    ['name' => 'Material', 'values' => ['Wood', 'Metal', 'Plastic']]
                ])
            ],
            [
                'name' => 'Insurance Service',
                'type' => 'service',
                'quantity' => 0,
                'sales_price' => 100.00,
                'cost_price' => 0.00,
                'unit' => 'Months',
                'category' => 'Service',
                'vendor_id' => 1,
                'is_published' => 1,
                'attributes' => json_encode([
                    ['name' => 'Coverage', 'values' => ['Basic', 'Premium', 'Comprehensive']]
                ])
            ],
            [
                'name' => 'Deposit Service',
                'type' => 'service',
                'quantity' => 0,
                'sales_price' => 200.00,
                'cost_price' => 0.00,
                'unit' => 'Units',
                'category' => 'Service',
                'vendor_id' => 1,
                'is_published' => 1,
                'attributes' => json_encode([
                    ['name' => 'Type', 'values' => ['Security', 'Damage', 'Equipment']]
                ])
            ]
        ];
        
        foreach ($sample_products as $product) {
            $insert_sql = "INSERT INTO products (name, type, quantity, sales_price, cost_price, unit, category, vendor_id, is_published, attributes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt, "ssididsssis", 
                $product['name'], 
                $product['type'], 
                $product['quantity'], 
                $product['sales_price'], 
                $product['cost_price'], 
                $product['unit'], 
                $product['category'], 
                $product['vendor_id'], 
                $product['is_published'],
                $product['attributes']
            );
            
            if (mysqli_stmt_execute($stmt)) {
                echo "✅ Inserted: <strong>" . htmlspecialchars($product['name']) . "</strong> (" . $product['type'] . ")<br>";
            }
        }
        
        echo "<br><em>Sample products include both Goods and Service types with attributes</em>";
    }
    
    echo "<br><br>";
    echo "<div style='background-color: #f0f0f0; padding: 15px; border-radius: 5px;'>";
    echo "<h3>🚀 Next Steps:</h3>";
    echo "1. <a href='products.php' style='color: #10b981; font-weight: bold;'>→ View Products Page</a><br>";
    echo "2. <a href='new_product.php' style='color: #10b981; font-weight: bold;'>→ Create New Product</a><br>";
    echo "3. Test the form with Goods and Service types<br>";
    echo "4. Verify vendor auto-fill and publish permissions";
    echo "</div>";
    
} else {
    echo "❌ Error creating products table: " . mysqli_error($conn);
}
?>
