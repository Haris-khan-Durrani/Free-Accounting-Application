-- OneSol International Multi-Tenant Accounting Database Schema

CREATE TABLE IF NOT EXISTS tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  code VARCHAR(60) NOT NULL UNIQUE,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  country_code VARCHAR(10) NOT NULL DEFAULT 'US',
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','admin','accountant','sales','viewer') NOT NULL DEFAULT 'owner',
  phone VARCHAR(60) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_tenants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  role ENUM('owner','admin','accountant','sales','viewer') NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_user_tenant (user_id, tenant_id),
  CONSTRAINT fk_ut_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ut_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branding_settings (
  tenant_id INT UNSIGNED PRIMARY KEY,
  company_name VARCHAR(190) NOT NULL DEFAULT 'OneSol Solutions',
  company_tagline VARCHAR(255) NULL DEFAULT 'Enterprise Technology & Software',
  company_website VARCHAR(190) NULL DEFAULT 'www.onesol.ae',
  company_email VARCHAR(190) NULL DEFAULT 'info@onesol.ae',
  company_phone VARCHAR(60) NULL DEFAULT '+971 4 000 0000',
  tax_number_label VARCHAR(60) NOT NULL DEFAULT 'TRN / Tax ID',
  tax_number VARCHAR(100) NULL,
  registration_number VARCHAR(100) NULL,
  address TEXT NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  country VARCHAR(100) NOT NULL DEFAULT 'United Arab Emirates',
  zip_code VARCHAR(30) NULL,
  bank_name VARCHAR(190) NULL,
  bank_account_name VARCHAR(190) NULL,
  bank_account_number VARCHAR(100) NULL,
  bank_iban VARCHAR(100) NULL,
  bank_swift VARCHAR(60) NULL,
  primary_color VARCHAR(30) NOT NULL DEFAULT '#0f172a',
  secondary_color VARCHAR(30) NOT NULL DEFAULT '#2563eb',
  accent_color VARCHAR(30) NOT NULL DEFAULT '#d97706',
  font_family VARCHAR(100) NOT NULL DEFAULT 'Inter',
  logo_url VARCHAR(255) NULL DEFAULT 'assets/img/onesol-logo.png',
  dark_logo_url VARCHAR(255) NULL,
  signature_url VARCHAR(255) NULL,
  stamp_url VARCHAR(255) NULL,
  default_invoice_template VARCHAR(60) NOT NULL DEFAULT 'modern_minimal',
  invoice_footer_notes TEXT NULL,
  payment_terms_days INT NOT NULL DEFAULT 14,
  watermark_enabled TINYINT(1) NOT NULL DEFAULT 1,
  show_qr_code TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_branding_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS currencies (
  code VARCHAR(10) PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  symbol VARCHAR(10) NOT NULL,
  symbol_position ENUM('before','after') NOT NULL DEFAULT 'before',
  decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 2,
  exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1.000000
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tax_rates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  rate_percent DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  tax_type ENUM('vat','sales_tax','gst','zero_rated','exempt') NOT NULL DEFAULT 'vat',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_tax_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
  company_name VARCHAR(190) NOT NULL,
  contact_name VARCHAR(150) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  tax_number VARCHAR(100) NULL,
  address TEXT NULL,
  city VARCHAR(100) NULL,
  country VARCHAR(100) NULL DEFAULT 'United Arab Emirates',
  currency VARCHAR(10) NOT NULL DEFAULT 'AED',
  payment_terms_days INT NOT NULL DEFAULT 14,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
  invoice_number VARCHAR(60) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  quote_id INT UNSIGNED NULL,
  invoice_date DATE NOT NULL,
  valid_until DATE NULL,
  status ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  currency VARCHAR(10) NOT NULL DEFAULT 'AED',
  exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_type ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_rate_id INT UNSIGNED NULL,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  terms_public TEXT NULL,
  template_id VARCHAR(60) NOT NULL DEFAULT 'modern_minimal',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tenant_inv_num (tenant_id, invoice_number),
  CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  details TEXT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
  quote_number VARCHAR(60) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  quote_date DATE NOT NULL,
  valid_until DATE NULL,
  status ENUM('draft','sent','accepted','rejected','converted') NOT NULL DEFAULT 'draft',
  currency VARCHAR(10) NOT NULL DEFAULT 'AED',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_type ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tenant_quote_num (tenant_id, quote_number),
  CONSTRAINT fk_quotes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_quote_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quote_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  details TEXT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expcat_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
  category_id INT UNSIGNED NULL,
  vendor_name VARCHAR(190) NOT NULL,
  expense_date DATE NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'AED',
  payment_method VARCHAR(60) NULL DEFAULT 'Bank Transfer',
  receipt_url VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expenses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_expenses_cat FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chart_of_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  account_code VARCHAR(30) NOT NULL,
  account_name VARCHAR(150) NOT NULL,
  account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  parent_id INT UNSIGNED NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_tenant_account_code (tenant_id, account_code),
  CONSTRAINT fk_coa_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  entry_number VARCHAR(60) NOT NULL,
  entry_date DATE NOT NULL,
  reference VARCHAR(100) NULL,
  description TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_je_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS journal_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  journal_id INT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  debit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  credit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  memo VARCHAR(255) NULL,
  CONSTRAINT fk_ji_journal FOREIGN KEY (journal_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_ji_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recurring_invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  frequency ENUM('weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  next_issue_date DATE NOT NULL,
  last_issued_date DATE NULL,
  status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
  template_json TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recur_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_keys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  api_key VARCHAR(64) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_used_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_apikeys_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhooks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  target_url VARCHAR(255) NOT NULL,
  secret VARCHAR(64) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_webhooks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB;
