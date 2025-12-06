🌐 Idiomas / Languages

🇪🇸 Español

🇬🇧 English

# 🇪🇸 Español
<details open> <summary><strong>Haz clic para mostrar/ocultar</strong></summary> <br>
Stocktelecom

Es una aplicación web SSR para la gestión de inventario, material técnico y órdenes de trabajo en empresas de telecomunicaciones, especialmente enfocada en instalaciones de fibra óptica.

Desarrollado en Laravel 12 y utilizando Vite, PHP 8.2, MySQL, Bootstrap 5 y TailwindCSS 4.

🚀 Características principales

Gestión completa de inventario en almacén (serializado y no serializado)

Transferencias entre almacén ⇄ técnicos

Devoluciones y movimientos históricos

Gestión de personal y roles

Gestión de órdenes de trabajo

Restablecimiento de contraseña por email (flujo completo)

Exportación de inventario a CSV

Sistema de roles totalmente diferenciado:

Técnico

Logística

Administrador

Superadministrador

📦 Tecnologías utilizadas
Backend

PHP 8.2

Laravel 12

MySQL

Frontend

Vite 7

TailwindCSS 4

Bootstrap 5

Javascript

Herramientas de desarrollo

Laravel Tinker

PHPUnit

Concurrency Dev Script (composer run dev)

🛠 Instalación
1. Clonar el repositorio
git clone https://github.com/tuusuario/stocktelecom.git
cd stocktelecom
2. Instalar dependencias PHP
composer install

3. Instalar dependencias Node
npm install

4. Configurar entorno
cp .env.example .env
php artisan key:generate
Configurar conexión MySQL en .env:
DB_DATABASE=stocktelecom
DB_USERNAME=root
DB_PASSWORD=

5. Ejecutar migraciones + seeders
php artisan migrate --seed
Los seeders crean usuarios de prueba con contraseña general: "password"

6. Iniciar el entorno de desarrollo
composer run dev
Esto ejecuta:
servidor PHP (php artisan serve)
Vite (npm run dev)
cola de trabajos (php artisan queue:listen)
logging en vivo

👤 Roles y funcionalidades
1. Técnico
ver mi stock
aceptar / rechazar transferencias
historial de transferencias
historial de devoluciones
órdenes de trabajo asignadas
(solo gestiona una a la vez)

2. Logística
registrar entradas y salidas de almacén
crear materiales
gestionar inventario serializado y no serializado
enviar transferencias a técnicos
ver históricos de:
almacén
devoluciones
transferencias
gestionar órdenes de trabajo

3. Administrador
ver materiales
ver personal
ver órdenes de trabajo
ver históricos
(no puede crear materiales, enviar transferencias ni registrar devoluciones)

4. Superadministrador
Incluye todas las funciones de los roles anteriores, más:
crear/editar/eliminar materiales
gestionar usuarios y personal
acceder a todos los históricos globales
control total del almacén y transferencias

📤 Exportación a CSV
El sistema permite exportar el inventario del almacén a un archivo .csv compatible con Excel.
La opción se encuentra en:
Materiales → Exportar CSV

🔐 Sistema de recuperación de contraseña
La aplicación incluye el flujo completo nativo de Laravel, traducido al español:
enlace “¿olvidaste tu contraseña?”
envío de email mediante SMTP
formulario para introducir correo
enlace seguro con caducidad
formulario para definir una nueva contraseña

📄 Licencia
Este proyecto está bajo la licencia MIT.
Puedes usarlo, modificarlo y distribuirlo libremente.

👤 Autor
Proyecto creado por Franklin Tenias Guevara github/franklin2005 como solución integral para la gestión de materiales en telecomunicaciones.

## 📧 Configuración del correo (requerido para recuperación de contraseña)

La función de “¿Olvidaste tu contraseña?” utiliza el sistema de notificaciones por correo de Laravel.  
**Para que funcione correctamente, es necesario configurar SMTP en el archivo `.env`.**

Ejemplo usando Gmail:

MAIL_MAILER=smtp  
MAIL_HOST=smtp.gmail.com  
MAIL_PORT=587  
MAIL_USERNAME=tu_correo@gmail.com  
MAIL_PASSWORD=tu_contraseña_de_aplicación  
MAIL_ENCRYPTION=tls  
MAIL_FROM_ADDRESS=tu_correo@gmail.com  
MAIL_FROM_NAME="${APP_NAME}"  

### ⚠️ Importante
- Gmail **exige obligatoriamente**:
  - Verificación en dos pasos activada  
  - Contraseña de aplicación  
- Sin configurar estas credenciales, **no será posible enviar correos**, incluyendo el enlace de restablecimiento de contraseña.

Si no deseas configurar correo, la aplicación funcionará igual, pero **no podrás usar la recuperación de contraseña**.

<br>

</details>

---

# 🇬🇧 English  
<details>
<summary><strong>Click to show/ hide</strong></summary>

<br>

# Stocktelecom
Stocktelecom is an SSR web application for managing inventory, technical material, and work orders in telecommunications companies, especially those focused on fiber-optic installations.

Developed using **Laravel 12**, **Vite**, **PHP 8.2**, **MySQL**, **Bootstrap 5**, and **TailwindCSS 4**.

---

## 🚀 Main Features

- Full warehouse inventory management (serialized and non-serialized)
- Transfers between warehouse ⇄ technicians
- Returns and historical movement tracking
- Personnel and role management
- Work order management
- Password reset via email (full workflow)
- Export inventory to CSV
- Fully differentiated role system:
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
Configure MySQL in .env:
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

👤 Roles and features
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
view staff
view work orders
view histories
(cannot create materials, send transfers, or record returns)

4. Super Administrator
Includes all previous functionalities, plus:
create/edit/delete materials
manage users and staff
access all global histories
full control of warehouse and transfers

📤 CSV Export  
The system allows exporting warehouse inventory into a .csv file compatible with Excel.  
Located at:  
Materials → Export CSV

🔐 Password Recovery System  
The application includes Laravel’s full native flow:
“forgot your password?” link  
SMTP email sending  
email input form  
secure expiring link  
new password form  

📄 License  
This project is under MIT license.  
You may use, modify, and distribute it freely.

👤 Author  
Project created by **Franklin Tenias Guevara (github/franklin2005)** as a full solution for telecom material management.

## 📧 Email Configuration (required for password recovery)

The “Forgot your password?” feature uses Laravel’s email notification system.  
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
  - Two-step verification  
  - App password  
- Without these credentials, **emails will NOT send**, including password resets.

If you do not configure email, the app will still work, but **password recovery will not function**.

<br>

</details>
