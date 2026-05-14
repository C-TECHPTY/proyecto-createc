-- CREATEC SaaS - estructura central de modulos e instancias.
-- Fase 4: no borra datos, no altera tablas operativas de catalogos/rifas.
-- Ejecutar despues de 20260505_super_admin_base.sql y 20260508_super_admin_connect_companies.sql.

CREATE TABLE IF NOT EXISTS `sa_modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `base_path` VARCHAR(190) NULL,
  `status` ENUM('active','inactive','planned') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_modules_code` (`code`),
  KEY `idx_sa_modules_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_company_modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `settings_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_company_modules` (`company_id`, `module_id`),
  KEY `idx_sa_company_modules_module` (`module_id`),
  KEY `idx_sa_company_modules_status` (`status`),
  CONSTRAINT `fk_sa_company_modules_company`
    FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_sa_company_modules_module`
    FOREIGN KEY (`module_id`) REFERENCES `sa_modules` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_project_instances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NOT NULL,
  `instance_key` VARCHAR(120) NOT NULL,
  `project_path` VARCHAR(255) NOT NULL,
  `database_name` VARCHAR(190) NULL,
  `domain` VARCHAR(190) NULL,
  `subdomain` VARCHAR(190) NULL,
  `status` ENUM('active','maintenance','disabled') NOT NULL DEFAULT 'active',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_project_instances_key` (`instance_key`),
  KEY `idx_sa_project_instances_company` (`company_id`),
  KEY `idx_sa_project_instances_module` (`module_id`),
  KEY `idx_sa_project_instances_status` (`status`),
  CONSTRAINT `fk_sa_project_instances_company`
    FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_sa_project_instances_module`
    FOREIGN KEY (`module_id`) REFERENCES `sa_modules` (`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sa_modules` (`code`, `name`, `description`, `base_path`, `status`)
SELECT 'catalogos', 'Catalogos digitales B2B', 'Modulo SaaS para catalogos comerciales, vendedores, clientes y pedidos.', 'projects/catalogos/', 'active'
WHERE NOT EXISTS (SELECT 1 FROM `sa_modules` WHERE `code` = 'catalogos');

INSERT INTO `sa_modules` (`code`, `name`, `description`, `base_path`, `status`)
SELECT 'rifas', 'Sistema de rifas', 'Modulo SaaS para rifas, numeros, reservas, pagos, comprobantes y ganadores.', 'projects/rifas/', 'active'
WHERE NOT EXISTS (SELECT 1 FROM `sa_modules` WHERE `code` = 'rifas');

INSERT INTO `sa_modules` (`code`, `name`, `description`, `base_path`, `status`)
SELECT 'barber', 'Barberias', 'Modulo futuro para agenda, servicios, reservas y clientes recurrentes.', 'projects/barber/', 'planned'
WHERE NOT EXISTS (SELECT 1 FROM `sa_modules` WHERE `code` = 'barber');

INSERT INTO `sa_modules` (`code`, `name`, `description`, `base_path`, `status`)
SELECT 'turismo', 'Turismo', 'Modulo futuro para tours, paquetes, reservas y experiencias turisticas.', 'projects/turismo/', 'planned'
WHERE NOT EXISTS (SELECT 1 FROM `sa_modules` WHERE `code` = 'turismo');

INSERT INTO `sa_modules` (`code`, `name`, `description`, `base_path`, `status`)
SELECT 'spa', 'Belleza y spa', 'Modulo futuro para agenda, paquetes, servicios y membresias.', 'projects/spa/', 'planned'
WHERE NOT EXISTS (SELECT 1 FROM `sa_modules` WHERE `code` = 'spa');

INSERT INTO `sa_modules` (`code`, `name`, `description`, `base_path`, `status`)
SELECT 'ecommerce', 'E-commerce', 'Modulo futuro para tiendas, productos, pedidos y pagos.', 'projects/ecommerce/', 'planned'
WHERE NOT EXISTS (SELECT 1 FROM `sa_modules` WHERE `code` = 'ecommerce');
