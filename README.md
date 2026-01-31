🚀 Rental Management System

A full-stack web application that enables businesses to manage product rentals efficiently by handling quotations, rental orders, inventory reservation, invoicing, payments, pickups, returns, and reports.

This project models a real-world rental business workflow similar to ERP systems used in industry.

📌 Features

🔐 Role-based authentication (Admin, Vendor, Customer)

🛒 Rentable product management

⏱️ Time-based pricing (Hourly / Daily / Weekly / Custom)

📅 Inventory reservation to prevent double booking

📝 Rental quotations → confirmed rental orders

🚚 Pickup & return management with late fee handling

💰 Invoice generation with partial/full payments

🌐 Customer portal with order & invoice tracking

📊 Dashboards and business reports

👥 User Roles
Customer

Browse rental products

Create and confirm rental quotations

Make payments

View invoices and order history

Vendor

Manage rental products

Process rental orders

Track pickups and returns

Generate invoices and view earnings

Admin

Manage users and vendors

Configure rental rules and pricing

Monitor system-wide analytics and reports

🔁 Rental Workflow
Product Browsing
      ↓
Rental Quotation
      ↓
Rental Order Confirmation
      ↓
Inventory Reservation
      ↓
Pickup / Delivery
      ↓
Return
      ↓
Invoice & Payment
      ↓
Reports & Analytics

🧠 Key Concepts Implemented

ERP-style end-to-end workflow

Inventory reservation logic

Time-based rental pricing

Role-based access control

Invoice and payment state tracking

Real-world business rule modeling

🛠 Tech Stack

Frontend: HTML, CSS, JavaScript

Backend: PHP

Database: MySQL

Server: Apache (XAMPP)

Tools: VS Code, Git, GitHub

⚙️ Installation & Setup

Clone the repository

git clone https://github.com/your-username/rental-management-system.git


Move the project to htdocs (XAMPP)

Import the database:

Open phpMyAdmin

Create a database

Import the provided .sql file

Update database credentials in:

config/db.php


Start Apache & MySQL and open:

http://localhost/rental-management-system

📈 Future Enhancements

Mobile app integration

Multi-warehouse rental support

Automated email/SMS reminders

Advanced analytics dashboard

Online contract & document signing

🎯 Learning Outcomes

Practical understanding of ERP workflows

Real-world business logic implementation

Full-stack development experience

Clean system design and role separation

📄 License

This project is developed for educational and hackathon purposes.
