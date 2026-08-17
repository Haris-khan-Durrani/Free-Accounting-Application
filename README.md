# ⚡ OneSol Enterprise Invoice & Accounting Manager

![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7.0-DC382D?style=for-the-badge&logo=redis&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Supported-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![UAE FTA Compliant](https://img.shields.io/badge/UAE_FTA_VAT-201_Compliant-007A3D?style=for-the-badge)

**OneSol Invoice Manager** is a high-performance, multi-tenant SaaS billing, accounting, and financial management application built for modern enterprises. Packed with 20+ feature modules, 11 PDF invoice templates, UAE FTA VAT 201 tax compliance, whitelabel custom domains, REST API automation, and real-time calculation engines.

---

## 🌟 Key Features & Capabilities

### 🇦🇪 UAE FTA Tax & Corporate Tax Compliance
- **Official FTA VAT 201 Declaration Return**: Formatted to match UAE Federal Tax Authority (FTA) Form 201 layout with Box 1a–1g breakdowns across all 7 Emirates (Dubai, Abu Dhabi, Sharjah, Ajman, UAQ, RAK, Fujairah).
- **Official FTA Audit File (.faf) Generator**: 1-Click export of the pipe-delimited `.faf` text audit file required by FTA auditors containing Header, Sales, and Purchase ledgers.
- **UAE Corporate Tax (9%) & Threshold Estimator**: Compliant with Federal Decree-Law No. 47 of 2022 — tracks the AED 375,000 Small Business Relief (SBR) threshold & 9% Corporate Tax liability.
- **Tax Invoice & TRN Validation**: Supports 15-digit Seller & Buyer TRN numbers, 5% standard VAT rate, zero-rated exports (0%), and exempt items.
- **Dual AED Currency Engine**: Native support for AED (UAE Dirham) with automatic CBUAE conversion rates for foreign currencies (USD, EUR, GBP, SAR).

### 🌐 Enterprise Whitelabel Custom Domains
- **Custom Subdomain Binding**: Bind custom domains (e.g. `billing.yourcompany.com`) to host public client payment portals.
- **⚡ Real-Time DNS Testing**: Interactive AJAX DNS verification engine that performs live CNAME and IP resolution checks with SSL indicators.
- **Watermark Control**: Option to remove "Powered by OneSol" branding across client portals.

### 📊 Comprehensive Financial Reporting Suite (8 Reports)
- **Profit & Loss Statement (P&L)**: Gross Revenue vs Business Expenses with Net Profit calculation.
- **Balance Sheet (Statement of Financial Position)**: Cash & Receivables, Net VAT Obligations, and Retained Earnings satisfying $\text{Assets} = \text{Liabilities} + \text{Equity}$.
- **Cash Flow Statement**: Operating inflows and outflows categorized by date.
- **Accounts Receivable (A/R) Aging Report**: 30 / 60 / 90+ day overdue buckets.
- **UAE FTA VAT 201 Filing Return**: 7-Emirate Box 1–14 tax return.
- **General VAT Summary Report**: Tax collected vs input VAT paid.
- **Client Revenue & Sales Analysis**: Total sales per client account with statement links.
- **Expenses by Category**: Spending breakdown across operational expense categories.
- **Universal CSV Exports**: 1-click CSV file downloads across all reporting pages.

### 🎨 11 Dynamic Invoice Templates & Drag & Drop Builder
- **11 Curated Designs**: Modern Minimal, Corporate Executive, Creative Vibrant, Tech Glassmorphism, Sleek Dark, Compact Thermal Receipt, Dual Column, Bold Gold, Slate Clean, Architectural Blue, and Classic Legal.
- **Drag & Drop Invoice Builder**: Reorder columns, customize brand colors, logos, digital signatures, and seal stamps.

### 💼 Client Ledger, Self-Service Hub & Automation
- **Client Self-Service Portal**: Passwordless client login hub (`client_portal.php`) where clients view invoice history, download statements of account, and pay online.
- **Auto-Recurring Invoices Cron Engine**: Automated background worker (`cron_recurring.php`) generating subscription billing invoices on weekly, monthly, or yearly schedules.
- **WhatsApp Cloud API Integration**: Direct Meta WhatsApp Cloud API gateway (`whatsapp_settings.php`) for automated PDF invoice & payment link dispatches.
- **Statement of Account**: Full financial ledger per client detailing invoices, payments received, running balance, and PDF print formatting.
- **1-Click SMTP Email Dispatch**: Deliver HTML invoices with online payment buttons using tenant custom SMTP settings (Gmail, Office 365, cPanel, SES).

### 🛡️ Security, Rate Limiting & Backups
- **Security Throttling Engine**: IP-based rate limiting that locks out brute-force bot attempts after 5 failed logins for 15 minutes.
- **Two-Factor Authentication (2FA)**: 6-digit OTP security verification via email.
- **1-Click SQL Database Backup**: Export raw `.sql` database dumps for archiving.
- **CBUAE Live Exchange Rate Sync**: Daily automated fetch (`cron_exchange_rates.php`) updating AED exchange rates for foreign currencies.

### ⚡ Live Dynamic Calculation Engine
- Sticky summary cards on invoice and proposal creation forms that calculate Subtotal, Discounts (Fixed or %), VAT Tax, and Grand Total live as line items change.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.3 (Object-Oriented, PDO MySQL, CSRF protection, PSR-4 structure)
- **Database**: MySQL 8.0 / MariaDB
- **Frontend**: HTML5, Vanilla JavaScript (ES6+), Tailwind CSS 3.4, FontAwesome 6 Pro
- **Caching**: Redis 7.0 (with fallback to file-based TTL cache)
- **Deployment**: Docker, Docker Compose, Laragon, XAMPP, Nginx, Apache

---

## 🚀 Quick Start Guide

### Option 1: Docker (1-Click Deployment)

Clone the repository and launch the full stack (PHP 8.3, Nginx, MySQL 8, Redis 7):

```bash
git clone https://github.com/Haris-khan-Durrani/invoice.git
cd invoice
```

**Windows:**
```cmd
docker-start.bat
```

**Linux / macOS:**
```bash
chmod +x docker-start.sh
./docker-start.sh
```

Access the application at: **`http://localhost:8080`**

---

### Option 2: Local Web Server (Laragon / XAMPP / WAMP)

1. **Clone the repository into your web root (`www` or `htdocs`):**
   ```bash
   git clone https://github.com/Haris-khan-Durrani/invoice.git
   ```

2. **Database Setup:**
   - Create a MySQL database named `onesol_invoices`.
   - Import the database schema from **`database.sql`**.

3. **Database Configuration (`config.php`):**
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_NAME', 'onesol_invoices');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Access the application:**
   Open `http://localhost/invoice` in your browser.

---

## 🔑 Default Login Credentials

| Account Role | Email Address | Default Password |
| :--- | :--- | :--- |
| **Super Admin / Owner** | `admin@onesol.ae` | `admin123` |

---

## 🔌 REST API Endpoints

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/v1/invoices` | List invoices with status & date filters |
| `POST` | `/api/v1/invoices` | Create a new tax invoice programmatically |
| `GET` | `/api/v1/clients` | List client accounts |
| `POST` | `/api/v1/clients` | Register a new client account |
| `POST` | `/api/v1/payments` | Record an invoice payment |
| `GET` | `/api/v1/reports/summary` | Fetch real-time revenue & expense summary |

Interactive API testing console available at: **`/api_playground.php`**

---

## 📄 License

This project is licensed under the **MIT License**.

Designed & Developed by **OneSol Solutions**.
