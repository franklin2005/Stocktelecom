# ENGLISH VERSION 

---

# Stocktelecom
Stocktelecom is an SSR web application for managing inventory, technical material, and work orders in telecommunications companies, especially those focused on fiber-optic installations.

Built with **Laravel 12**, **Vite**, **PHP 8.2**, **MySQL**, **Bootstrap 5**, and **TailwindCSS 4**.

---

## 🚀 Main Features

- Complete warehouse inventory management (serialized and non-serialized)  
- Transfers between warehouse ⇄ technicians  
- Returns and historical movement tracking  
- Personnel and role management  
- Work order management  
- Password reset via email (full workflow)  
- Inventory export to CSV  
- Fully separated role system:
  - Technician  
  - Logistics  
  - Administrator  
  - Super Administrator  

---

## 📦 Technologies Used

### Backend
- PHP 8.2  
- Laravel 12  
- MySQL  

### Frontend
- Vite 7  
- TailwindCSS 4  
- Bootstrap 5  
- Javascript  

### Development Tools
- Laravel Tinker  
- PHPUnit  
- Concurrency Dev Script (`composer run dev`)  

---

## 🛠 Installation

### 1. Clone the repository
```bash
git clone https://github.com/youruser/stocktelecom.git
cd stocktelecom
2. Install PHP dependencies
composer install

3. Install Node dependencies
npm install

4. Configure environment
cp .env.example .env
php artisan key:generate
Set MySQL connection in .env:
DB_DATABASE=stocktelecom
DB_USERNAME=root
DB_PASSWORD=

5. Run migrations + seeders
php artisan migrate --seed
Seeders create test users with the general password: "password"

6. Start development environment
composer run dev
This runs:
PHP server (php artisan serve)
Vite (npm run dev)
job queue (php artisan queue:listen)
live logging

👤 Roles and functionalities
1. Technician
view my stock
accept / reject transfers
transfer history
return history
assigned work orders
(handles one at a time)

2. Logistics
register warehouse entries and exits
create materials
manage serialized and non-serialized inventory
send transfers to technicians
view histories:
warehouse
returns
transfers
manage work orders

3. Administrator
view materials
view personnel
view work orders
view histories
(cannot create materials, send transfers, or register returns)

4. Super Administrator
Includes all functions of previous roles, plus:
create/edit/delete materials
manage users and personnel
access all global histories
full control of warehouse and transfers

📤 CSV Export
The system allows exporting warehouse inventory to a .csv file compatible with Excel.
The option is located at:
Materials → Export CSV

🔐 Password Recovery System
The application includes Laravel’s complete native flow:
“forgot your password?” link
email sending through SMTP
form to enter email
secure link with expiration
form to set a new password

📄 License
This project is licensed under MIT.
You may use, modify, and distribute it freely.

👤 Author
Project created by Franklin Tenias Guevara (github/franklin2005) as a complete solution for telecommunications material management.

## 📧 Email Configuration (required for password recovery)

The “Forgot your password?” feature uses Laravel’s mail notification system.  
**SMTP must be configured in `.env` for it to work.**

Example using Gmail:

MAIL_MAILER=smtp  
MAIL_HOST=smtp.gmail.com  
MAIL_PORT=587  
MAIL_USERNAME=your_email@gmail.com  
MAIL_PASSWORD=your_app_password  
MAIL_ENCRYPTION=tls  
MAIL_FROM_ADDRESS=your_email@gmail.com  
MAIL_FROM_NAME="${APP_NAME}"  

### ⚠️ Important
- Gmail strictly requires:
  - Two-step verification enabled  
  - App password  
- Without these credentials, **emails cannot be sent**, including password reset.

If you do not configure email, the application will still work, but **password recovery will not**.









