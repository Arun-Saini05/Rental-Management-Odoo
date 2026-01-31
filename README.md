# Rental Management System

A comprehensive rental management system built for a 24-hour hackathon. This system enables businesses to rent products online while managing quotations, rental orders, inventory, invoicing, and returns.

## Features

### Core Functionality
- **End-to-end rental flow**: Quotation → Order → Invoice → Return
- **User role management**: Customer, Vendor, Admin
- **Product management**: With variants and flexible rental pricing
- **Inventory management**: Real-time stock tracking and reservation logic
- **Payment processing**: Multiple payment methods with security deposits
- **Order tracking**: Complete rental lifecycle management
- **Reporting & Analytics**: Business insights and performance metrics

### User Roles

#### Customer (End User)
- Browse rentable products with filters
- Create rental quotations
- Confirm orders and make payments
- View invoices and order history
- Track rental status

#### Vendor (Internal User)
- Manage rental products
- Process rental orders
- Create invoices
- Track pickups, returns, and earnings

#### Admin (System Administrator)
- Full system access and configuration
- Manage vendors, products, and users
- View global analytics and reports
- System settings and configurations

## Tech Stack

- **Frontend**: HTML5, CSS3, Tailwind CSS
- **Backend**: PHP 8.x
- **Database**: MySQL
- **Authentication**: Session-based authentication
- **UI Framework**: Tailwind CSS with Font Awesome icons

## Installation

### Prerequisites
- PHP 8.x or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/MAMP (for local development)

### Setup Instructions

1. **Clone/Download the project**
   ```bash
   git clone <repository-url>
   cd Rental-Odoo
   ```

2. **Database Setup**
   - Create a new database named `rental_management`
   - Import the database schema from `database/schema.sql`
   ```sql
   mysql -u root -p rental_management < database/schema.sql
   ```

3. **Configuration**
   - Update database credentials in `config/database.php` if needed
   - Ensure the `uploads` directory is writable (for product images)

4. **Web Server Configuration**
   - Point your web server to the project root
   - For XAMPP: Place the project in `htdocs/`
   - Ensure `mod_rewrite` is enabled for clean URLs

5. **Access the Application**
   - Open your browser and navigate to `http://localhost/Rental-Odoo`
   - Default admin login:
     - Email: `admin@rental.com`
     - Password: `password`

## Project Structure

```
Rental-Odoo/
├── admin/                  # Admin panel (to be implemented)
├── api/                    # API endpoints
│   └── process-payment.php # Payment processing
├── auth/                   # Authentication pages
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── config/                 # Configuration files
│   ├── database.php        # Database connection
│   └── functions.php       # Helper functions
├── Customer/               # Customer panel
│   └── Dashboard.php       # Customer dashboard
├── database/               # Database files
│   └── schema.sql          # Database schema
├── vendor/                 # Vendor panel (to be implemented)
├── index.php               # Homepage
├── products.php            # Product listing
├── product-detail.php      # Product details
├── checkout.php            # Checkout process
├── payment.php             # Payment page
├── order-confirmation.php  # Order confirmation
└── README.md               # This file
```

## Database Schema

The system uses the following main tables:

- **users**: Customer, Vendor, and Admin accounts
- **products**: Rentable products with pricing
- **categories**: Product categories
- **product_variants**: Product variants with different pricing
- **rental_pricing**: Time-based rental pricing (Hour/Day/Week)
- **quotations**: Customer rental quotations
- **rental_orders**: Confirmed rental orders
- **invoices**: Billing documents
- **payments**: Payment records
- **addresses**: Customer delivery and billing addresses

## Key Features Implemented

### 1. User Authentication
- Registration with GSTIN support
- Role-based login (Customer/Vendor/Admin)
- Session management
- Password hashing

### 2. Product Management
- Product listing with filters
- Product detail pages
- Variant support with selection dialog
- Rental pricing (per hour/day/week)
- Stock management

### 3. Rental Flow
- Cart functionality
- Quotation creation
- Order confirmation
- Payment processing
- Invoice generation

### 4. Customer Portal
- Dashboard with statistics
- Order history
- Profile management
- Quick actions

### 5. Responsive Design
- Mobile-friendly interface
- Modern UI with Tailwind CSS
- Interactive elements
- Print-friendly invoices

## API Endpoints

### POST /api/process-payment.php
Processes payment and creates rental orders.

**Request Body:**
```json
{
  "quotation_id": 123,
  "payment_method": "card"
}
```

**Response:**
```json
{
  "success": true,
  "order_id": 456,
  "order_no": "SO123456",
  "invoice_no": "INV123456"
}
```

## Security Features

- Password hashing with bcrypt
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- Session-based authentication
- Role-based access control

## Future Enhancements

- Admin dashboard with comprehensive reports
- Vendor panel for product management
- Real-time notifications
- Email integration for order updates
- Advanced reporting and analytics
- Mobile app development
- Integration with payment gateways
- Multi-language support

## Contributing

This project was developed for a 24-hour hackathon. Contributions are welcome for:

- Bug fixes and improvements
- Additional features
- Documentation updates
- Code optimization

## License

This project is open-source and available under the MIT License.

## Support

For support and questions:
- Email: info@rentalhub.com
- Documentation: Check the inline code comments
- FAQ: Available in the customer dashboard

---

**Developed for Hackathon 2024**
**Duration: 24 hours**
**Tech Stack: PHP, MySQL, Tailwind CSS**
