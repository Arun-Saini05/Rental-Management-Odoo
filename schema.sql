CREATE DATABASE IF NOT EXISTS rental_management;
USE rental_management;

-- Rental Management System Database Schema
-- Created for PHP-based rental management system

-- Users table for authentication and role management
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    company_name VARCHAR(255),
    gstin VARCHAR(15),
    role ENUM('admin', 'vendor', 'customer') NOT NULL DEFAULT 'customer',
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories for product organization
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Product attributes (like Brand, Color, Size, etc.)
CREATE TABLE IF NOT EXISTS attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    attribute_type ENUM('text', 'number', 'select', 'boolean') DEFAULT 'text',
    is_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Attribute values
CREATE TABLE IF NOT EXISTS attribute_values (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attribute_id INT NOT NULL,
    value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    sku VARCHAR(100) UNIQUE,
    cost_price DECIMAL(10,2),
    sales_price DECIMAL(10,2),
    quantity_on_hand INT DEFAULT 0,
    quantity_reserved INT DEFAULT 0,
    quantity_available INT GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED,
    is_rentable BOOLEAN DEFAULT TRUE,
    is_published BOOLEAN DEFAULT TRUE,
    vendor_id INT,
    images JSON,
    specifications JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (vendor_id) REFERENCES users(id)
);

-- Product variants (for different sizes, colors, etc.)
CREATE TABLE IF NOT EXISTS product_variants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE,
    cost_price DECIMAL(10,2),
    sales_price DECIMAL(10,2),
    quantity_on_hand INT DEFAULT 0,
    quantity_reserved INT DEFAULT 0,
    quantity_available INT GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED,
    attributes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Rental pricing for different periods
CREATE TABLE IF NOT EXISTS rental_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    variant_id INT NULL,
    period_type ENUM('hour', 'day', 'week', 'month', 'custom') NOT NULL,
    period_duration INT DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
);

-- Product attribute assignments
CREATE TABLE IF NOT EXISTS product_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    attribute_id INT NOT NULL,
    attribute_value_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE,
    FOREIGN KEY (attribute_value_id) REFERENCES attribute_values(id) ON DELETE CASCADE
);

-- Customers table (extends users for customer-specific info)
CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    default_address_id INT NULL,
    credit_limit DECIMAL(10,2) DEFAULT 0,
    total_orders INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Customer addresses
CREATE TABLE IF NOT EXISTS customer_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    address_type ENUM('billing', 'shipping', 'both') DEFAULT 'both',
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10) NOT NULL,
    country VARCHAR(100) DEFAULT 'India',
    phone VARCHAR(20),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Rental quotations
CREATE TABLE IF NOT EXISTS rental_quotations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quotation_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    vendor_id INT,
    status ENUM('draft', 'sent', 'confirmed', 'cancelled') DEFAULT 'draft',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    security_deposit_total DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    valid_until DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (vendor_id) REFERENCES users(id)
);

-- Rental quotation line items
CREATE TABLE IF NOT EXISTS rental_quotation_lines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quotation_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    rental_start_date DATE NOT NULL,
    rental_end_date DATE NOT NULL,
    rental_days INT GENERATED ALWAYS AS (DATEDIFF(rental_end_date, rental_start_date)) STORED,
    line_total DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES rental_quotations(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);

-- Rental orders
CREATE TABLE IF NOT EXISTS rental_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    quotation_id INT UNIQUE,
    customer_id INT NOT NULL,
    vendor_id INT,
    status ENUM('draft', 'sent', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    security_deposit_total DECIMAL(10,2) DEFAULT 0,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    pickup_date DATE,
    expected_return_date DATE,
    actual_return_date DATE,
    delivery_address_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES rental_quotations(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (vendor_id) REFERENCES users(id),
    FOREIGN KEY (delivery_address_id) REFERENCES customer_addresses(id)
);

-- Rental order line items
CREATE TABLE IF NOT EXISTS rental_order_lines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    rental_start_date DATE NOT NULL,
    rental_end_date DATE NOT NULL,
    rental_days INT GENERATED ALWAYS AS (DATEDIFF(rental_end_date, rental_start_date)) STORED,
    line_total DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) DEFAULT 0,
    quantity_delivered INT DEFAULT 0,
    quantity_returned INT DEFAULT 0,
    condition_on_delivery TEXT,
    condition_on_return TEXT,
    late_fee_applied DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES rental_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);

-- Invoices
CREATE TABLE IF NOT EXISTS invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    vendor_id INT,
    status ENUM('draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled') DEFAULT 'draft',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    security_deposit_amount DECIMAL(10,2) DEFAULT 0,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    due_date DATE,
    paid_date DATE,
    payment_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES rental_orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (vendor_id) REFERENCES users(id)
);

-- Invoice line items
CREATE TABLE IF NOT EXISTS invoice_lines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    order_line_id INT,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (order_line_id) REFERENCES rental_order_lines(id)
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_id INT NOT NULL,
    customer_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'upi', 'bank_transfer', 'online') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    payment_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Pickup and delivery tracking
CREATE TABLE IF NOT EXISTS pickup_deliveries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    type ENUM('pickup', 'delivery', 'return') NOT NULL,
    status ENUM('pending', 'scheduled', 'in_transit', 'completed', 'failed') DEFAULT 'pending',
    scheduled_date DATE,
    scheduled_time TIME,
    completed_date DATETIME,
    driver_name VARCHAR(255),
    driver_phone VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES rental_orders(id) ON DELETE CASCADE
);

-- Stock movements for inventory tracking
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    variant_id INT NULL,
    movement_type ENUM('in', 'out', 'adjustment', 'reserved', 'released') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    reference_type VARCHAR(50),
    reference_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Coupons for discounts
CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) DEFAULT 0,
    usage_limit INT,
    usage_count INT DEFAULT 0,
    valid_from DATE,
    valid_until DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'RentoMojo', 'string', 'Company name'),
('company_email', 'info@rentomojo.com', 'string', 'Company email'),
('company_phone', '+91 98765 43210', 'string', 'Company phone'),
('company_address', 'Bangalore, India', 'string', 'Company address'),
('gst_rate', '18', 'number', 'Default GST rate'),
('late_fee_per_day', '100', 'number', 'Late fee per day'),
('security_deposit_percentage', '20', 'number', 'Security deposit percentage'),
('order_prefix', 'RO', 'string', 'Order number prefix'),
('quotation_prefix', 'RQ', 'string', 'Quotation number prefix'),
('invoice_prefix', 'RI', 'string', 'Invoice number prefix');

-- Insert default attributes
INSERT INTO attributes (name, attribute_type, is_required) VALUES
('Brand', 'select', FALSE),
('Color', 'select', FALSE),
('Size', 'select', FALSE),
('Material', 'select', FALSE),
('Weight', 'number', FALSE),
('Dimensions', 'text', FALSE);

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Furniture', 'Office and home furniture'),
('Electronics', 'Electronic appliances and gadgets'),
('Appliances', 'Home and kitchen appliances'),
('Vehicles', 'Cars, bikes and other vehicles'),
('Equipment', 'Tools and equipment'),
('Event', 'Event and party supplies');