# 🚀 Rental Management System

A full-stack web application that helps businesses manage product rentals by handling quotations, rental orders, inventory reservations, invoicing, payments, pickups, returns, and reports.

This project follows a real-world ERP-style rental workflow.

---

## 📌 Features

- Role-based authentication (Admin, Vendor, Customer)
- Rentable product management
- Time-based pricing (Hourly / Daily / Weekly / Custom)
- Inventory reservation to prevent double booking
- Rental quotations and confirmed rental orders
- Pickup and return management with late fee handling
- Invoice generation with partial and full payments
- Customer portal for order and invoice tracking
- Dashboards and business reports

---

## 👥 User Roles

### Customer
- Browse rental products
- Create and confirm rental quotations
- Make online payments
- View invoices and order history

### Vendor
- Manage rental products
- Process rental orders
- Track pickups and returns
- Generate invoices
- View earnings and performance

### Admin
- Manage users and vendors
- Configure system settings
- Monitor global analytics and reports

---

## 🔁 Rental Workflow

Product Browsing  
→ Rental Quotation  
→ Rental Order Confirmation  
→ Inventory Reservation  
→ Pickup / Delivery  
→ Return  
→ Invoice & Payment  
→ Reports & Analytics  

---

## 🧠 Key Concepts Implemented

- ERP-style end-to-end workflow
- Inventory reservation logic
- Time-based rental pricing
- Role-based access control
- Invoice and payment status tracking
- Real-world business rule modeling

---

## 🛠 Tech Stack

- Frontend: HTML, CSS, JavaScript  
- Backend: PHP  
- Database: MySQL  
- Server: Apache (XAMPP)  
- Tools: VS Code, Git, GitHub  

---

## ⚙️ Installation & Setup

1. Clone the repository:
      ```bash
   git clone https://github.com/your-username/rental-management-system.git
2. Move the project folder to:
      xampp/htdocs/

3. Create a database in phpMyAdmin and import the .sql file.
   
4. Update database credentials in:
      config/db.php

5. Start Apache and MySQL.

6. Open the application in a browser:
   http://localhost/rental-management-system
