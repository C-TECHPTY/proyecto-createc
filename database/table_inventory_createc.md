# Inventario de tablas CREATEC

Fase 7 - analisis de bases actuales y propuesta `createc_saas`.

No ejecutar como SQL. Este archivo documenta lo encontrado.

## Fuentes revisadas

- `CREATECGROUP/database/catalog_platform.sql`
- `CREATECGROUP/database/202604*.sql`
- `CREATECGROUP/database/202605*.sql`
- `sistema_rifa/database/schema.sql`
- `sistema_rifa/database/phase*.sql`
- `sistema_rifa/pandqgxl_rifa_panama.sql`

La carpeta esperada `databases/` no existe. La carpeta `basedatos/` existe, pero esta vacia.

## CREATECGROUP / Web principal

No tiene tablas actuales propias.

Tablas propuestas:

- `web_contacts`
- `web_leads`
- `web_services`
- `web_portfolio_projects`

## Super Admin SaaS actual

Tablas ya copiadas/preparadas en migraciones:

- `sa_admin_users`
- `sa_companies`
- `sa_subscriptions`
- `sa_licenses`
- `sa_activity_logs`
- `sa_plans`
- `sa_company_domains`
- `saas_publish_logs`
- `sa_modules`
- `sa_company_modules`
- `sa_project_instances`

Tablas propuestas para madurez SaaS:

- `sa_users`
- `sa_roles`
- `sa_permissions`
- `sa_role_permissions`
- `sa_audit_logs`

Nota: `sa_admin_users` puede mantenerse al inicio para no romper el Super Admin actual. `sa_users` debe introducirse en una fase posterior con compatibilidad o migracion controlada.

## Catalogos B2B actuales

Tablas base:

- `sellers`
- `clients`
- `catalog_users`
- `catalogs`
- `catalog_share_links`
- `catalog_access_logs`
- `catalog_behavior_events`
- `orders`
- `order_items`
- `order_status_history`
- `notifications_log`
- `activity_logs`
- `app_settings`

Tablas por campanas:

- `campaigns`
- `campaign_products`
- `campaign_promo_orders`
- `campaign_promo_order_items`
- `campaign_recipients`
- `campaign_logs`

Tablas por CRM/logs:

- `vendor_client_profiles`
- `vendor_client_notes`
- `catalog_product_update_logs`
- `catalog_seller_email_logs`

Tablas backup generadas por migraciones:

- `orders_backup_20260428_phase1`
- `orders_backup_20260428_phase2`
- `orders_backup_20260428_phase3`
- `sellers_backup_20260428_phase3`

Riesgos:

- `catalog_platform.sql` incluye `CREATE DATABASE` y `USE catalog_platform`.
- Varias tablas no tienen `company_id`.
- `orders`, `clients`, `activity_logs`, `app_settings` son nombres genericos.
- `catalogs` puede contener estructura/metadatos del catalogo, no necesariamente productos normalizados.

## Sistema de rifas moderno

Tablas base:

- `admins`
- `raffles`
- `raffle_numbers`
- `customers`
- `reservations`
- `reservation_numbers`
- `payments`
- `payment_receipts`
- `notifications`
- `push_subscriptions`
- `whatsapp_configs`
- `whatsapp_messages`
- `winners`
- `loyalty_points`
- `settings`
- `audit_logs`

Migraciones:

- `phase4_push_subscriptions.sql`
- `phase7_whatsapp.sql`
- `phase8_design_settings.sql`
- `phase8_design_settings_compatible.sql`
- `phase9_real_web_push.sql`

Riesgos:

- `admins`, `settings`, `notifications` y `audit_logs` son nombres genericos.
- No tiene `company_id`.
- Tiene PWA/push/WhatsApp; moverlo a multiempresa requiere adaptar configuracion por empresa.

## Sistema de rifas legacy / dump hosting

Tablas:

- `configuracion`
- `logs_sistema`
- `numeros_rifa`
- `rifas`
- `transacciones`
- `usuarios`

Riesgos:

- Incluye `INSERT INTO` con datos.
- Incluye hashes/passwords y posibles datos de clientes.
- Tiene nombres y modelo diferentes al sistema moderno.
- Debe migrarse solo si esos datos historicos son necesarios.

## Mapeo corto hacia `createc_saas`

| Origen | Destino propuesto |
| --- | --- |
| `sa_admin_users` | `sa_users` o conservar temporalmente |
| `sa_activity_logs` | `sa_audit_logs` |
| `sellers` | `cat_sellers` |
| `clients` | `cat_clients` |
| `catalog_users` | `cat_users` o `sa_users` |
| `catalogs` | `cat_catalogs` |
| `catalog_share_links` | `cat_catalog_tokens` |
| `catalog_access_logs` | `cat_access_logs` |
| `catalog_behavior_events` | `cat_behavior_events` |
| `orders` | `cat_orders` |
| `order_items` | `cat_order_items` |
| `campaigns` | `cat_campaigns` |
| `app_settings` | `cat_settings` |
| `admins` | `sa_users` o `rifa_admins` temporal |
| `raffles` / `rifas` | `rifa_raffles` |
| `raffle_numbers` / `numeros_rifa` | `rifa_numbers` |
| `customers` | `rifa_customers` |
| `reservations` | `rifa_reservations` |
| `payments` / `transacciones` | `rifa_payments` |
| `winners` | `rifa_winners` |
| `settings` / `configuracion` | `rifa_settings` |
| `audit_logs` / `logs_sistema` | `sa_audit_logs` o `rifa_audit_logs` |

## Tablas que requieren `company_id`

Directo obligatorio:

- `cat_catalogs`
- `cat_products`
- `cat_orders`
- `cat_sellers`
- `cat_clients`
- `cat_campaigns`
- `cat_catalog_tokens`
- `rifa_raffles`
- `rifa_reservations`
- `rifa_payments`
- `rifa_notifications`
- `rifa_settings`

Heredado por relacion:

- `cat_order_items`
- `cat_product_images`
- `rifa_numbers`
- `rifa_reservation_numbers`
- `rifa_payment_receipts`
- `rifa_winners`

## No ejecutar todavia

- No importar dumps.
- No fusionar bases.
- No renombrar tablas.
- No agregar columnas en sistemas productivos.
- No ejecutar `DROP`, `DELETE` ni `TRUNCATE`.
- No mover datos sin respaldo y prueba de conteos.
