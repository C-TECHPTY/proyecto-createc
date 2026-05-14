-- Base del modulo Super Admin para Rodeo Import.
-- Fase inicial SaaS/multiempresa: solo crea tablas nuevas con prefijo sa_.
-- No modifica tablas existentes ni integra empresas al flujo actual.

CREATE TABLE IF NOT EXISTS sa_admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'super_admin',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sa_admin_users_email (email),
    KEY idx_sa_admin_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sa_companies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(190) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    contact_name VARCHAR(160) NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(60) NULL,
    domain VARCHAR(190) NULL,
    subdomain VARCHAR(120) NULL,
    logo_url VARCHAR(500) NULL,
    primary_color VARCHAR(20) NULL,
    status ENUM('active', 'suspended', 'inactive') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sa_companies_slug (slug),
    KEY idx_sa_companies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sa_subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    plan_name VARCHAR(120) NOT NULL,
    billing_cycle ENUM('monthly', 'annual', 'manual') NOT NULL DEFAULT 'monthly',
    monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    annual_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('active', 'pending', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sa_subscriptions_company_id (company_id),
    KEY idx_sa_subscriptions_status (status),
    CONSTRAINT fk_sa_subscriptions_company
        FOREIGN KEY (company_id) REFERENCES sa_companies (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sa_licenses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    license_key VARCHAR(120) NOT NULL,
    status ENUM('active', 'inactive', 'expired', 'revoked') NOT NULL DEFAULT 'active',
    max_catalogs INT UNSIGNED NOT NULL DEFAULT 0,
    max_vendors INT UNSIGNED NOT NULL DEFAULT 0,
    max_products INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sa_licenses_license_key (license_key),
    KEY idx_sa_licenses_company_id (company_id),
    KEY idx_sa_licenses_status_expires (status, expires_at),
    CONSTRAINT fk_sa_licenses_company
        FOREIGN KEY (company_id) REFERENCES sa_companies (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sa_activity_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_user_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sa_activity_logs_admin_user_id (admin_user_id),
    KEY idx_sa_activity_logs_company_id (company_id),
    KEY idx_sa_activity_logs_created_at (created_at),
    CONSTRAINT fk_sa_activity_logs_admin_user
        FOREIGN KEY (admin_user_id) REFERENCES sa_admin_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_sa_activity_logs_company
        FOREIGN KEY (company_id) REFERENCES sa_companies (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primer usuario Super Admin:
-- 1. Genera un hash seguro en PHP:
--    php -r "echo password_hash('CAMBIA_ESTA_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"
-- 2. Sustituye el hash resultante en este INSERT y cambia nombre/email:
-- INSERT INTO sa_admin_users (name, email, password_hash, role, status)
-- VALUES ('Super Admin', 'admin@tuempresa.com', '$2y$10$REEMPLAZA_ESTE_HASH_GENERADO', 'super_admin', 'active');
