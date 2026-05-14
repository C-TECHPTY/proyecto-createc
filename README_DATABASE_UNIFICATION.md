# Propuesta de unificacion de base de datos CREATEC SaaS

Base objetivo propuesta: `createc_saas`  
Estado: propuesta tecnica. No ejecutar en produccion sin revision y respaldo.

Actualizacion Fase 7: despues de preparar `CREATECGROUP/projects/`, se confirma que la unificacion debe seguir siendo una propuesta de staging. Los proyectos ya estan copiados como modulos, pero sus tablas operativas aun deben mantenerse compatibles hasta adaptar codigo y consultas con `company_id`.

## 1. Tablas actuales por base/proyecto

### CREATECGROUP

No se detecto SQL propio original en `CREATECGROUP/`. Para la web corporativa se proponen tablas nuevas con prefijo `web_`.

Tablas propuestas:

- `web_contacts`
- `web_leads`
- `web_services`
- `web_portfolio_projects`

### catalogo_rodeo

Base declarada por el SQL: `catalog_platform`

Tablas base detectadas:

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

Tablas por migraciones:

- `campaigns`
- `campaign_products`
- `campaign_promo_orders`
- `campaign_promo_order_items`
- `campaign_recipients`
- `campaign_logs`
- `vendor_client_profiles`
- `vendor_client_notes`
- `catalog_product_update_logs`
- `catalog_seller_email_logs`
- `orders_backup_20260428_phase1`
- `orders_backup_20260428_phase2`
- `orders_backup_20260428_phase3`
- `sellers_backup_20260428_phase3`

Super Admin SaaS ya existente:

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

Nota: `sa_modules`, `sa_company_modules` y `sa_project_instances` fueron formalizadas en Fase 4 con `CREATECGROUP/database/20260510_createc_saas_core_structure.sql`.

### sistema_rifa - modelo moderno

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

### sistema_rifa - dump alterno/hosting

- `usuarios`
- `rifas`
- `numeros_rifa`
- `transacciones`
- `configuracion`
- `logs_sistema`

## 2. Conflictos detectados

- Nombres genericos repetidos: `settings`, `audit_logs`, `notifications`, `users/admins`.
- Catalogos usa nombres sin prefijo (`orders`, `clients`, `sellers`) que chocaran con ecommerce/CRM futuros.
- Rifas tiene dos modelos de datos diferentes: ingles moderno y espanol legacy.
- `catalog_platform.sql` incluye `CREATE DATABASE` y `USE`, lo cual no siempre conviene en cPanel.
- `catalogs` no equivale directamente a `cat_products`: puede contener archivo/JSON del catalogo generado, por lo que productos individuales pueden requerir extraccion.
- `catalog_users`, `admins` y `usuarios` representan identidades distintas; no deben mezclarse de golpe.
- `app_settings`, `settings` y `configuracion` pueden mapearse a settings por modulo, pero requieren normalizacion cuidadosa.
- No todas las tablas actuales tienen `company_id`.
- Existen columnas/estados con idiomas diferentes entre catalogos, rifas moderno y rifas legacy.
- `sa_admin_users` y la propuesta `sa_users` se solapan; conviene mantener `sa_admin_users` hasta migrar login del Super Admin.
- El modulo de rifas copiado tenia un config no-example local; fue retirado del modulo SaaS, pero confirma que las carpetas copiadas deben auditarse antes de subir.

## 3. Tabla equivalente nueva propuesta

| Actual | Propuesta | Nota |
| --- | --- | --- |
| `sa_admin_users` | `sa_users` | Centralizar usuarios SaaS, mantener alias/migracion desde tabla actual. |
| `sa_companies` | `sa_companies` | Tabla principal multiempresa. |
| `sa_plans` | `sa_plans` | Planes comerciales. |
| `sa_subscriptions` | `sa_subscriptions` | Suscripcion por empresa. |
| `sa_licenses` | `sa_licenses` | Licencias por empresa/proyecto. |
| `sa_company_domains` | `sa_company_domains` | Dominios/subdominios. |
| `sa_activity_logs` | `sa_audit_logs` | Auditoria central. |
| `saas_publish_logs` | `sa_audit_logs` o `sa_publish_logs` | Puede mantenerse como log especializado. |
| `sa_modules` | `sa_modules` | Catalogos, rifas y modulos futuros. |
| `sa_company_modules` | `sa_company_modules` | Modulos activos por empresa. |
| `sa_project_instances` | `sa_project_instances` | Ruta/subdominio/DB asignada por empresa. |
| `catalogs` | `cat_catalogs` | Agregar `company_id`. |
| `sellers` | `cat_sellers` | Agregar `company_id`. |
| `clients` | `cat_clients` | Agregar `company_id`. |
| `catalog_users` | `cat_users` o `sa_users` | Definir si son usuarios globales o de modulo. |
| `orders` | `cat_orders` | Agregar `company_id`. |
| `order_items` | `cat_order_items` | Referencia a `cat_orders`. |
| `campaigns` | `cat_campaigns` | Agregar `company_id`. |
| `catalog_share_links` | `cat_catalog_tokens` | Tokens publicos. |
| `catalog_access_logs` | `cat_access_logs` | Analitica catalogos. |
| `catalog_behavior_events` | `cat_behavior_events` | Eventos. |
| `app_settings` | `cat_settings` | Configuracion por empresa/modulo. |
| `vendor_client_profiles` | `cat_client_profiles` | CRM vendedor. |
| `vendor_client_notes` | `cat_client_notes` | Notas de CRM vendedor. |
| `admins` | `rifa_admins` o `sa_users` | Recomendada migracion progresiva a `sa_users`. |
| `raffles` / `rifas` | `rifa_raffles` | Agregar `company_id`. |
| `raffle_numbers` / `numeros_rifa` | `rifa_numbers` | Agregar `company_id` via rifa. |
| `customers` / `usuarios` participantes | `rifa_customers` | No mezclar con usuarios admin. |
| `reservations` | `rifa_reservations` | Agregar `company_id`. |
| `reservation_numbers` | `rifa_reservation_numbers` | Relacion N:M. |
| `payments` / `transacciones` | `rifa_payments` | Agregar `company_id`. |
| `payment_receipts` | `rifa_payment_receipts` | Comprobantes. |
| `winners` | `rifa_winners` | Ganadores. |
| `notifications` | `rifa_notifications` | Notificaciones por modulo. |
| `settings` / `configuracion` | `rifa_settings` | Configuracion por empresa. |
| `audit_logs` / `logs_sistema` | `sa_audit_logs` o `rifa_audit_logs` | Centralizar eventos criticos. |

## 4. Prefijos recomendados

- `sa_`: Super Admin SaaS central.
- `web_`: web corporativa CREATEC.
- `cat_`: catalogos B2B.
- `rifa_`: sistema de rifas.
- `barber_`: barberias futuro.
- `tour_`: turismo futuro.
- `store_`: ecommerce futuro.

## 5. Multiempresa

Toda tabla de negocio debe tener `company_id` directo o heredado por relacion clara.

Obligatorio directo:

- `cat_catalogs.company_id`
- `cat_products.company_id`
- `cat_orders.company_id`
- `cat_sellers.company_id`
- `cat_clients.company_id`
- `cat_campaigns.company_id`
- `rifa_raffles.company_id`
- `rifa_reservations.company_id`
- `rifa_payments.company_id`
- `rifa_notifications.company_id`
- `rifa_settings.company_id`

Puede ser heredado:

- `cat_order_items` por `order_id`
- `cat_product_images` por `product_id`
- `rifa_numbers` por `raffle_id`
- `rifa_reservation_numbers` por `reservation_id`
- `rifa_payment_receipts` por `reservation_id`

## 6. Riesgos

- Agregar `company_id` a sistemas activos sin adaptar consultas puede ocultar datos o romper listados.
- Cambiar nombres de tablas en produccion romperia includes y consultas existentes.
- Unificar usuarios sin mapear roles puede dar acceso indebido.
- Los dumps con datos deben tratarse como informacion sensible.
- Las llaves foraneas pueden fallar si hay datos historicos huerfanos.
- `CREATE TABLE ... AS SELECT` de backups en migraciones existentes puede crear tablas voluminosas.
- El modelo legacy de rifas debe migrarse aparte del modelo moderno.
- Los subdominios pueden apuntar a `projects/*`; por eso `database/` y `sql/` deben retirarse o bloquearse despues de migrar.
- Las migraciones de catalogos existentes usan procedimientos temporales con `DROP PROCEDURE IF EXISTS`; no borran tablas, pero deben revisarse antes de ejecutarlas en hosting compartido.

## 7. Plan de migracion recomendado

1. Respaldar bases actuales completas.
2. Crear base vacia `createc_saas` en staging.
3. Ejecutar `database/createc_saas_schema_proposed.sql` solo en staging o ejecutar por secciones equivalentes en cPanel.
4. Crear empresa inicial CREATEC en `sa_companies`.
5. Crear planes, modulos y dominios.
6. Migrar datos de catalogos a tablas `cat_` con scripts idempotentes.
7. Migrar datos de rifas modelo moderno a tablas `rifa_`.
8. Si se necesita el dump legacy `pandqgxl_rifa_panama.sql`, migrarlo despues, con mapeo separado.
9. Adaptar codigo por modulo para leer `company_id`.
10. Probar login, catalogos, pedidos, rifas, reservas, pagos y API de licencia.
11. Hacer piloto con una empresa antes de produccion.

Inventario ampliado:

- `database/table_inventory_createc.md`

## 8. Orden recomendado

1. `sa_companies`
2. `sa_modules`
3. `sa_plans`
4. `sa_company_modules`
5. `sa_subscriptions`
6. `sa_licenses`
7. `sa_company_domains`
8. `sa_project_instances`
9. `sa_users`, `sa_roles`, `sa_permissions`
10. `web_*`
11. `cat_*`
12. `rifa_*`
13. logs/auditoria

## 9. Pruebas necesarias

- Login Super Admin.
- CRUD empresas, planes, suscripciones, licencias y dominios.
- Validacion de licencia por API.
- Resolucion de empresa por subdominio/dominio.
- Login admin catalogos.
- Crear/editar catalogo.
- Crear pedido publico.
- Panel vendedor.
- Login admin rifas.
- Crear rifa, generar numeros, reservar, confirmar pago y publicar ganador.
- Aislamiento multiempresa: una empresa no debe ver datos de otra.
- Pruebas en HTTPS con cookies seguras.

## 10. Que no se debe tocar todavia

- Bases productivas.
- `config.php` reales.
- SMTP real.
- FTP real.
- API keys reales.
- Tablas con pedidos/rifas/clientes.
- Dumps originales.
- Nombres de rutas criticas.
- Logins actuales.
- SQL legacy sin respaldo.
