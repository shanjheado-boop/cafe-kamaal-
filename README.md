# 🏨 Cafe Kamaal Hotel Bill Calculator

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![PWA](https://img.shields.io/badge/PWA-Installable_on_Mobile-ea580c?logo=pwa&logoColor=white)](MOBILE_APP.md)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A web-based monthly hotel meal bill calculation platform designed for hostel residents, built with **PHP 8** and **MySQL**. It features an automated single-alphabet-code divider system, full audit trail transparency, administrative member management with reset capabilities, and **Progressive Web App (PWA)** mobile installability.

---

## 📱 Mobile App (PWA & APK)

Cafe Kamaal is fully installable on mobile phones (Android & iOS) with an app icon on the home screen, full-screen standalone display, and offline support.

- **Direct Install**: Open in mobile browser &rarr; tap **"Install App"** banner or Chrome menu &rarr; **Install App**.
- **On iPhone (Safari)**: Tap **Share** &rarr; **Add to Home Screen**.
- **Generate Standalone Android `.apk`**: See the step-by-step instructions in [`MOBILE_APP.md`](MOBILE_APP.md) to generate a downloadable APK using PWABuilder in 2 minutes.

---

## 🌟 Core System Features

1. **Divider Formula Calculation ($Price \div N$)**:
   - Hostellers or Admin add a meal price and select the single alphabet codes of hostellers who ate.
   - The system automatically calculates:
     $$\text{Per Person Share} = \frac{\text{Total Meal Price}}{\text{Count of Selected Hosteller Codes}}$$
   - Assigns that exact share to each hosteller's monthly balance in real time.

2. **Single Alphabet Code Rule**:
   - Every hosteller is strictly assigned a unique single letter (**A to Z**) as their identifier.
   - Used for quick divider tagging and mess roster records.

3. **Last Edited Bill Banner**:
   - An illuminated banner at the top of the dashboard displays the meal modified by the last person upon login (who edited, timestamp, cost, dividers, and edit notes).

4. **Multi-User Private Authentication**:
   - Clean, secure login portal with no publicly exposed member details.
   - Unique credentials for every code holder to ensure accountable audit logging.

5. **Real-Time Group Bill & Individual Expenses**:
   - Real-time monthly mess total and percentage share for each resident.
   - Dedicated **My Expenses** view (`my_expenses.php`) showing personal meal attendance and category breakdowns (Breakfast, Lunch, Dinner, Special Mess).

6. **Administer Control Center (`admin.php`)**:
   - **Register & Assign Codes**: Create hostellers with automatic suggestions for unused letters (A–Z).
   - **Remove Members**: Permanent deletion with automatic meal split recalculation, or temporary account deactivation.
   - **Reset All & Start New Calculation**: Reset current cycle to Rs. 0.00, start a fresh monthly cycle, or clear historical meals.
   - **WhatsApp Credential Sharing**: One-click formatted message ready to send to the hostel WhatsApp group.
   - **Printable Mess Invoices (`print_bill.php`)**: Formatted printable summary for the mess noticeboard.

7. **Full Audit Trail Transparency**:
   - Every meal created, edited, or removed records user details, timestamp, and modification summaries.

---

## 🚀 Quick Start / Local Installation

### Prerequisites
- **PHP 8.0+**
- **MySQL / MariaDB** (via XAMPP, Laragon, WampServer, or native)

### Step 1: Clone Repository
```bash
git clone https://github.com/your-username/cafe-kamaal.git
cd cafe-kamaal
```

### Step 2: Database Setup
1. Create a database named `cafe_kamaal_db` in MySQL:
   ```sql
   CREATE DATABASE cafe_kamaal_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the initial schema and seed dataset:
   ```bash
   mysql -u root -p cafe_kamaal_db < database.sql
   ```
   *(Alternatively, use phpMyAdmin &rarr; Import &rarr; choose `database.sql`).*

### Step 3: Configure Environment
Copy `.env.example` to `.env` and update your database credentials if different from default:
```bash
cp .env.example .env
```

### Step 4: Run Application
Start the built-in PHP development server:
```bash
php -S localhost:8000
```
Open **[http://localhost:8000](http://localhost:8000)** in your browser or mobile phone on the same Wi-Fi network!

---

## 🔑 Default Login Credentials

| Role | Name | Code | Username | Password |
| :--- | :--- | :---: | :--- | :--- |
| **Main Admin** | Cafe Kamaal Manager | *None* | `admin` | `admin123` |
| **Hosteller** | Zubair Ahmed | **A** | `hosteller_a` | `password123` |
| **Hosteller** | Bilal Khan | **B** | `hosteller_b` | `password123` |
| **Hosteller** | Hamza Ali | **C** | `hosteller_c` | `password123` |
| **Hosteller** | Danish Raza | **D** | `hosteller_d` | `password123` |
| **Hosteller** | Ehtisham Ul Haq | **E** | `hosteller_e` | `password123` |
| **Hosteller** | Farhan Saeed | **F** | `hosteller_f` | `password123` |

> *Note: Passwords can be updated anytime by the Admin or residents.*

---

## ☁️ Cloud Deployment (GitHub & Free Hosting)

This project is ready to deploy directly from GitHub to modern cloud providers:

### Deploying to Railway / Render / Heroku
1. Push this repository to your GitHub account.
2. In **Railway** or **Render**, create a **New Project** from GitHub Repo.
3. Provision a **MySQL Database** service and link it.
4. Set the environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`).
5. Your application will be live with an automatic HTTPS URL, ready to be installed as a mobile PWA!

---

## 📁 Repository Structure

```
cafe-kamaal/
├── config/
│   ├── auth.php            # Session & authentication management
│   ├── db.php              # PDO MySQL connection & auto-migration
│   └── helpers.php         # Formatting, audit logger, and validators
├── includes/
│   ├── header.php          # Top navigation & PWA install banner
│   └── footer.php          # Centralized interactive Add/Edit meal modal
├── assets/
│   ├── css/style.css       # Custom styles & print layout
│   ├── js/app.js           # Live calculator & PWA service worker logic
│   └── icons/              # Mobile app icons (192x192, 512x512, SVG)
├── admin.php               # Administer control center, member removal & reset
├── dashboard.php           # Real-time bill dashboard & last-edited banner
├── index.php               # Private login portal
├── logout.php              # Session sign-out handler
├── meal_actions.php        # Backend API for meal CRUD & recalculation
├── my_expenses.php         # Personal expense statement for residents
├── print_bill.php          # Printable mess invoice & group roster
├── register.php            # New resident registration with code picker
├── database.sql            # Database schema & sample dataset
├── manifest.json           # Web App Manifest for mobile installation
├── sw.js                   # Service Worker for PWA & offline support
├── offline.html            # Branded offline fallback screen
├── MOBILE_APP.md           # Mobile installation & APK generation guide
├── .env.example            # Sample environment variables
├── .gitignore              # Git ignore rules
└── .gitattributes         # Consistent cross-platform line endings
```

---

## 📄 License
This project is open-source under the [MIT License](LICENSE).
