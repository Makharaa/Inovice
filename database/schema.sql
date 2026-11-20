CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_users_status (is_active, deleted_at),
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issuers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('company', 'individual_entrepreneur', 'individual') NOT NULL,
    name VARCHAR(190) NOT NULL,
    identification_number VARCHAR(30) NOT NULL,
    address VARCHAR(255) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    bank_name VARCHAR(190) NULL,
    bank_code VARCHAR(80) NULL,
    bank_account VARCHAR(80) NULL,
    is_vat_payer TINYINT(1) NOT NULL DEFAULT 0,
    invoice_prefix VARCHAR(20) NOT NULL DEFAULT 'INV',
    next_invoice_number INT UNSIGNED NOT NULL DEFAULT 1,
    numbering_year SMALLINT UNSIGNED NULL,
    default_language ENUM('ka', 'en', 'ru') NOT NULL DEFAULT 'ka',
    default_template ENUM('standard', 'modern', 'compact') NOT NULL DEFAULT 'standard',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_issuers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_issuers_user (user_id, deleted_at, is_active),
    INDEX idx_issuers_identification (identification_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('company', 'individual_entrepreneur', 'individual') NOT NULL,
    name VARCHAR(190) NOT NULL,
    identification_number VARCHAR(30) NOT NULL,
    address VARCHAR(255) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_clients_user (user_id, deleted_at),
    INDEX idx_clients_name (name),
    INDEX idx_clients_identification (identification_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    issuer_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(60) NOT NULL,
    language ENUM('ka', 'en', 'ru') NOT NULL DEFAULT 'ka',
    template_key ENUM('standard', 'modern', 'compact') NOT NULL DEFAULT 'standard',
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    currency ENUM('GEL', 'USD', 'EUR') NOT NULL DEFAULT 'GEL',
    prices_include_vat TINYINT(1) NOT NULL DEFAULT 0,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft', 'issued') NOT NULL DEFAULT 'draft',
    issuer_snapshot JSON NOT NULL,
    client_snapshot JSON NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_issuer FOREIGN KEY (issuer_id) REFERENCES issuers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_invoice_number (issuer_id, invoice_number),
    INDEX idx_invoices_user_date (user_id, issue_date, deleted_at),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    description TEXT NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
    quantity DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(14,4) NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    net_total DECIMAL(14,2) NOT NULL,
    vat_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_items_invoice (invoice_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_lookup (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

