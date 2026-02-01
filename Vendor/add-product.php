<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

if (!isVendor()) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$vendor_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get categories for dropdown
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// Get attributes for product specifications
$attributes = $db->query("SELECT * FROM attributes ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $category_id = sanitizeInput($_POST['category_id']);
    $sku = sanitizeInput($_POST['sku']);
    $cost_price = sanitizeInput($_POST['cost_price']);
    $sales_price = sanitizeInput($_POST['sales_price']);
    $quantity_on_hand = sanitizeInput($_POST['quantity_on_hand']);
    $is_rentable = isset($_POST['is_rentable']) ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Handle images
    $images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === 0) {
                $filename = time() . '_' . $_FILES['images']['name'][$key];
                $upload_path = '../assets/products/' . $filename;
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    $images[] = 'assets/products/' . $filename;
                }
            }
        }
    }
    
    // Handle specifications
    $specifications = [];
    if (isset($_POST['specifications'])) {
        foreach ($_POST['specifications'] as $spec_key => $spec_value) {
            if (!empty($spec_value)) {
                $specifications[$spec_key] = $spec_value;
            }
        }
    }
    
    // Handle custom attributes
    $custom_attributes = [];
    if (isset($_POST['custom_attribute_names']) && isset($_POST['custom_attribute_values'])) {
        foreach ($_POST['custom_attribute_names'] as $index => $attr_name) {
            $attr_value = $_POST['custom_attribute_values'][$index] ?? '';
            if (!empty($attr_name) && !empty($attr_value)) {
                $custom_attributes[] = [
                    'name' => sanitizeInput($attr_name),
                    'value' => sanitizeInput($attr_value)
                ];
            }
        }
    }
    
    // Handle rental pricing
    $rental_pricing = [];
    if (isset($_POST['rental_period']) && isset($_POST['rental_price'])) {
        foreach ($_POST['rental_period'] as $index => $period) {
            if (!empty($period) && !empty($_POST['rental_price'][$index])) {
                $rental_pricing[] = [
                    'period_type' => $period,
                    'period_duration' => $_POST['rental_duration'][$index] ?? 1,
                    'price' => $_POST['rental_price'][$index],
                    'security_deposit' => $_POST['security_deposit'][$index] ?? 0
                ];
            }
        }
    }
    
    // Validation
    if (empty($name) || empty($description) || empty($category_id) || empty($quantity_on_hand)) {
        $error = 'Please fill all required fields';
    } elseif (empty($rental_pricing)) {
        $error = 'Please add at least one rental pricing option';
    } elseif (!is_numeric($cost_price) || !is_numeric($sales_price) || !is_numeric($quantity_on_hand)) {
        $error = 'Price and quantity must be numbers';
    } else {
        
        // Insert product
        $sql = "INSERT INTO products (name, description, category_id, sku, cost_price, sales_price, 
                quantity_on_hand, quantity_reserved, is_rentable, is_published, vendor_id, 
                images, specifications) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $images_json = json_encode($images);
        
        // Merge specifications and custom attributes
        $all_specs = $specifications;
        foreach ($custom_attributes as $attr) {
            $all_specs[$attr['name']] = $attr['value'];
        }
        $specs_json = json_encode($all_specs);
        $stmt->bind_param("sssiddsiisss", $name, $description, $category_id, $sku, 
                          $cost_price, $sales_price, $quantity_on_hand, $is_rentable, 
                          $is_published, $vendor_id, $images_json, $specs_json);
        
        if ($stmt->execute()) {
            $product_id = $db->getLastId();
            
            // Set SKU: use user provided or generate as PRD + product_id
            if (!empty($user_provided_sku)) {
                $final_sku = $user_provided_sku;
            } else {
                $final_sku = 'PRD-' . $product_id;
            }
            
            // Update product with final SKU
            $update_sql = "UPDATE products SET sku = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("si", $final_sku, $product_id);
            $update_stmt->execute();
            
            // Insert rental pricing
            $has_daily_pricing = false;
            foreach ($rental_pricing as $pricing) {
                if ($pricing['period_type'] === 'day') {
                    $has_daily_pricing = true;
                }
                
                $price_sql = "INSERT INTO rental_pricing (product_id, period_type, period_duration, 
                              price, security_deposit) VALUES (?, ?, ?, ?, ?)";
                $price_stmt = $db->prepare($price_sql);
                $price_stmt->bind_param("isidd", $product_id, $pricing['period_type'], 
                                       $pricing['period_duration'], $pricing['price'], 
                                       $pricing['security_deposit']);
                $price_stmt->execute();
            }
            
            // Auto-add daily pricing if not provided
            if (!$has_daily_pricing) {
                // Calculate daily price from existing pricing
                $daily_price = null;
                
                // Find the first pricing to calculate daily price
                foreach ($rental_pricing as $pricing) {
                    if ($pricing['period_type'] === 'hour') {
                        $daily_price = $pricing['price'] * 8; // 8 hours workday
                        break;
                    } elseif ($pricing['period_type'] === 'week') {
                        $daily_price = $pricing['price'] / 7; // Weekly / 7 days
                        break;
                    } elseif ($pricing['period_type'] === 'month') {
                        $daily_price = $pricing['price'] / 30; // Monthly / 30 days
                        break;
                    }
                }
                
                // If no pricing found, use sales price
                if ($daily_price === null && $sales_price > 0) {
                    $daily_price = $sales_price;
                }
                
                // Default price if still null
                if ($daily_price === null) {
                    $daily_price = 100.00;
                }
                
                // Insert daily pricing
                $daily_sql = "INSERT INTO rental_pricing (product_id, period_type, period_duration, 
                              price, security_deposit) VALUES (?, 'day', 1, ?, ?)";
                $daily_stmt = $db->prepare($daily_sql);
                $security_deposit = $daily_price * 0.5; // 50% of daily price
                $daily_stmt->bind_param("idd", $product_id, $daily_price, $security_deposit);
                $daily_stmt->execute();
            }
            
            // Insert product attributes
            if (isset($_POST['product_attributes'])) {
                foreach ($_POST['product_attributes'] as $attr_id => $value_id) {
                    if (!empty($value_id)) {
                        $attr_sql = "INSERT INTO product_attributes (product_id, attribute_id, attribute_value_id) 
                                    VALUES (?, ?, ?)";
                        $attr_stmt = $db->prepare($attr_sql);
                        $attr_stmt->bind_param("iii", $product_id, $attr_id, $value_id);
                        $attr_stmt->execute();
                    }
                }
            }
            
            $success = 'Product added successfully!';
            header('refresh:2;url=Dashboard.php');
        } else {
            $error = 'Failed to add product. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Add New Product</h1>
                <p class="text-gray-600">List your rental product with detailed information</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6">
                <!-- Basic Information -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Product Name *
                            </label>
                            <input type="text" name="name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter product name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                SKU (Optional)
                            </label>
                            <input type="text" name="sku" id="skuInput"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Auto-generated if empty">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Description *
                        </label>
                        <textarea name="description" rows="4" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Describe your product in detail"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Category *
                            </label>
                            <select name="category_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select category</option>
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Quantity Available *
                            </label>
                            <input type="number" name="quantity_on_hand" required min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Available quantity">
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Pricing</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Cost Price
                            </label>
                            <input type="number" name="cost_price" step="0.01" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Your cost price">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Sales Price
                            </label>
                            <input type="number" name="sales_price" step="0.01" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Selling price">
                        </div>
                    </div>
                </div>

                <!-- Rental Pricing -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Rental Pricing</h2>
                    <div id="rentalPricingContainer">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 rental-pricing-row">
                            <select name="rental_period[]" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="hour">Hourly</option>
                                <option value="day">Daily</option>
                                <option value="week">Weekly</option>
                                <option value="month">Monthly</option>
                            </select>
                            <input type="number" name="rental_duration[]" placeholder="Duration" min="1" value="1"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <input type="number" name="rental_price[]" placeholder="Price" step="0.01" min="0"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <input type="number" name="security_deposit[]" placeholder="Security Deposit" step="0.01" min="0"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <button type="button" onclick="addRentalPricing()" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Add More Pricing
                    </button>
                </div>

                <!-- Product Attributes -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Product Attributes</h2>
                    
                    <!-- Existing Attributes from Database -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-700 mb-3">Standard Attributes</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php while ($attribute = $attributes->fetch_assoc()): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <?php echo htmlspecialchars($attribute['name']); ?>
                                    </label>
                                    <select name="product_attributes[<?php echo $attribute['id']; ?>]"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select <?php echo htmlspecialchars($attribute['name']); ?></option>
                                        <?php
                                        $values = $db->query("SELECT * FROM attribute_values WHERE attribute_id = {$attribute['id']}");
                                        while ($value = $values->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $value['id']; ?>">
                                                <?php echo htmlspecialchars($value['value']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <!-- Custom Attributes -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-700 mb-3">Custom Attributes</h3>
                        <div id="customAttributesContainer">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 custom-attribute-row">
                                <input type="text" name="custom_attribute_names[]" placeholder="Attribute name (e.g., Color, Size, Material)"
                                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <input type="text" name="custom_attribute_values[]" placeholder="Attribute value (e.g., Red, Large, Metal)"
                                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <div class="flex items-end">
                                    <button type="button" onclick="this.parentElement.parentElement.remove()" 
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" onclick="addCustomAttribute()" 
                                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-2"></i>Add Custom Attribute
                        </button>
                    </div>
                </div>

                <!-- Specifications -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Specifications</h2>
                    <div id="specificationsContainer">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 specification-row">
                            <input type="text" name="specifications[]" placeholder="Specification name"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <input type="text" name="specifications[]" placeholder="Specification value"
                                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <button type="button" onclick="addSpecification()" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Add More Specifications
                    </button>
                </div>

                <!-- Images -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Product Images</h2>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center bg-gray-50 hover:bg-gray-100 transition-colors">
                        <input type="file" name="images[]" multiple accept="image/*" 
                               class="hidden" id="imageUpload" onchange="previewImages(event)">
                        <label for="imageUpload" class="cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-6xl text-gray-400 mb-6"></i>
                            <p class="text-xl text-gray-600 mb-2">Click to upload images or drag and drop</p>
                            <p class="text-gray-500">PNG, JPG, GIF up to 10MB (Multiple files allowed)</p>
                        </label>
                    </div>
                    <div id="imagePreview" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6"></div>
                </div>

                <!-- Options -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Options</h2>
                    <div class="space-y-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_rentable" checked
                                   class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <span class="text-gray-700">Available for rental</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_published" checked
                                   class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <span class="text-gray-700">Publish immediately</span>
                        </label>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-4">
                    <a href="Dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                        <i class="fas fa-save mr-2"></i>Add Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addRentalPricing() {
            const container = document.getElementById('rentalPricingContainer');
            const newRow = document.createElement('div');
            newRow.className = 'grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 rental-pricing-row';
            newRow.innerHTML = `
                <select name="rental_period[]" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="" disabled selected>Select period</option>
                    <option value="hour">Hourly</option>
                    <option value="day">Daily</option>
                    <option value="week">Weekly</option>
                    <option value="month">Monthly</option>
                </select>
                <input type="number" name="rental_duration[]" placeholder="Duration" min="1" value="1"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <input type="number" name="rental_price[]" placeholder="Price" step="0.01" min="0"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <input type="number" name="security_deposit[]" placeholder="Security Deposit" step="0.01" min="0"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            `;
            container.appendChild(newRow);
        }

        function addCustomAttribute() {
            const container = document.getElementById('customAttributesContainer');
            const newRow = document.createElement('div');
            newRow.className = 'grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 custom-attribute-row';
            newRow.innerHTML = `
                <input type="text" name="custom_attribute_names[]" placeholder="Attribute name (e.g., Color, Size, Material)"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <input type="text" name="custom_attribute_values[]" placeholder="Attribute value (e.g., Red, Large, Metal)"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <div class="flex items-end">
                    <button type="button" onclick="this.parentElement.parentElement.remove()" 
                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        }

        function addSpecification() {
            const container = document.getElementById('specificationsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 specification-row';
            newRow.innerHTML = `
                <input type="text" name="specifications[]" placeholder="Specification name"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <input type="text" name="specifications[]" placeholder="Specification value"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            `;
            container.appendChild(newRow);
        }

        function previewImages(event) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            const files = event.target.files;
            for (let file of files) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative';
                    div.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-48 object-cover rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <button type="button" onclick="this.parentElement.remove()" 
                                class="absolute top-3 right-3 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-red-600 transition-colors">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Auto-generate SKU when product name is entered
        document.querySelector('input[name="name"]').addEventListener('input', function() {
            const productName = this.value;
            const skuInput = document.getElementById('skuInput');
            
            // Don't auto-fill SKU field - keep it empty for user to fill if they want
            // If empty, it will be generated as PRD-{id} in database
        });
        
        // Allow manual SKU override
        document.getElementById('skuInput').addEventListener('input', function() {
            // User can manually edit SKU
        });
    </script>
    
    


</body>
</html>
