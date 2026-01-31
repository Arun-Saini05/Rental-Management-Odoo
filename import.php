<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $file['tmp_name'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Check file extension
        if ($fileExtension === 'csv') {
            // Process CSV file
            if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                $header = fgetcsv($handle, 1000, ','); // Skip header row
                
                $importedCount = 0;
                $errorCount = 0;
                
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    try {
                        // Map CSV columns to database fields
                        $orderNumber = $data[1] ?? 'ORD-' . uniqid();
                        $customerName = $data[2] ?? '';
                        $productName = $data[3] ?? '';
                        $rentalPrice = floatval($data[4] ?? 0);
                        $rentalDuration = intval($data[5] ?? 1);
                        $status = $data[6] ?? 'draft';
                        
                        // Create or find customer
                        $customerId = null;
                        if (!empty($customerName)) {
                            // Check if customer exists
                            $customerCheck = "SELECT c.id FROM customers c LEFT JOIN users u ON c.user_id = u.id WHERE u.name = ?";
                            $stmt = mysqli_prepare($conn, $customerCheck);
                            mysqli_stmt_bind_param($stmt, "s", $customerName);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            
                            if ($row = mysqli_fetch_assoc($result)) {
                                $customerId = $row['id'];
                            } else {
                                // Create new customer and user
                                $email = strtolower(str_replace(' ', '.', $customerName)) . '@example.com';
                                
                                // Insert user
                                $insertUser = "INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, NOW())";
                                $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
                                $stmt = mysqli_prepare($conn, $insertUser);
                                mysqli_stmt_bind_param($stmt, "sss", $customerName, $email, $hashedPassword);
                                mysqli_stmt_execute($stmt);
                                $userId = mysqli_insert_id($conn);
                                
                                // Insert customer
                                $insertCustomer = "INSERT INTO customers (user_id, created_at) VALUES (?, NOW())";
                                $stmt = mysqli_prepare($conn, $insertCustomer);
                                mysqli_stmt_bind_param($stmt, "i", $userId);
                                mysqli_stmt_execute($stmt);
                                $customerId = mysqli_insert_id($conn);
                            }
                        }
                        
                        // Create rental order
                        $pickupDate = date('Y-m-d');
                        $returnDate = date('Y-m-d', strtotime($pickupDate . ' + ' . $rentalDuration . ' days'));
                        
                        $insertOrder = "INSERT INTO rental_orders (order_number, customer_id, vendor_id, pickup_date, expected_return_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = mysqli_prepare($conn, $insertOrder);
                        $vendorId = 1; // Default vendor ID
                        mysqli_stmt_bind_param($stmt, "siisss", $orderNumber, $customerId, $vendorId, $pickupDate, $returnDate, $status);
                        mysqli_stmt_execute($stmt);
                        
                        $importedCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        error_log("Import error: " . $e->getMessage());
                    }
                }
                
                fclose($handle);
                
                if ($importedCount > 0) {
                    $message = "Successfully imported {$importedCount} orders.";
                }
                if ($errorCount > 0) {
                    $error = "Failed to import {$errorCount} records.";
                }
            }
        } else {
            $error = "Please upload a CSV file.";
        }
    } else {
        $error = "Error uploading file. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Orders - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #1a1a1a;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }
        
        .import-container {
            background-color: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 24px;
            max-width: 600px;
            margin: 50px auto;
        }
        
        .file-upload {
            border: 2px dashed #404040;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .file-upload:hover {
            border-color: #6366f1;
            background-color: #333333;
        }
        
        .btn-primary {
            background-color: #6366f1;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #5555e5;
        }
        
        .btn-secondary {
            background-color: #404040;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background-color: #555555;
        }
        
        .alert-success {
            background-color: #10b981;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        
        .alert-error {
            background-color: #ef4444;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="import-container">
        <div class="mb-6">
            <h1 class="text-2xl font-bold mb-2">Import Rental Orders</h1>
            <p class="text-gray-400">Upload a CSV file to import rental orders into the system.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">CSV File</label>
                <div class="file-upload">
                    <input type="file" name="import_file" accept=".csv" class="hidden" id="fileInput" required>
                    <label for="fileInput" class="cursor-pointer">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-400 mb-2">Click to upload or drag and drop</p>
                        <p class="text-sm text-gray-500">CSV files only</p>
                    </label>
                </div>
                <div id="fileName" class="mt-2 text-sm text-gray-400"></div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-sm font-medium mb-2">CSV Format Requirements:</h3>
                <div class="bg-gray-800 p-4 rounded-lg text-sm text-gray-300">
                    <p class="mb-2">Your CSV file should include the following columns:</p>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>Order ID (can be empty)</li>
                        <li>Order Number</li>
                        <li>Customer Name</li>
                        <li>Product Name</li>
                        <li>Rental Price</li>
                        <li>Rental Duration (in days)</li>
                        <li>Status (draft, sent, confirmed, in_progress, completed, cancelled)</li>
                    </ol>
                </div>
            </div>
            
            <div class="flex gap-4">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-upload mr-2"></i> Import Orders
                </button>
                <a href="dashboard.php" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
    
    <script>
        // File input handling
        const fileInput = document.getElementById('fileInput');
        const fileName = document.getElementById('fileName');
        
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                fileName.textContent = 'Selected: ' + e.target.files[0].name;
            } else {
                fileName.textContent = '';
            }
        });
        
        // Drag and drop functionality
        const fileUpload = document.querySelector('.file-upload');
        
        fileUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#6366f1';
            this.style.backgroundColor = '#333333';
        });
        
        fileUpload.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#404040';
            this.style.backgroundColor = 'transparent';
        });
        
        fileUpload.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#404040';
            this.style.backgroundColor = 'transparent';
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileName.textContent = 'Selected: ' + e.dataTransfer.files[0].name;
            }
        });
    </script>
</body>
</html>
