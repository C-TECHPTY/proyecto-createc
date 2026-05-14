# Analisis CREATEC SaaS - Fase 1

Fecha: 2026-05-10  
Alcance: `CREATECGROUP/`, `catalogo_rodeo/`, `sistema_rifa/` y carpeta de bases detectada.

## 1. Resumen por proyecto

### CREATECGROUP

Web corporativa estatica actual de CREATEC.

- Ruta local: `CREATECGROUP/`
- Repo Git detectado: si, dentro de `CREATECGROUP/.git`
- Entrada principal detectada: `index.html`
- No se detecto `index.php`, `public/`, `includes/`, `config.php`, `db.php` ni `.htaccess`.
- Assets principales: `assets/img/logo.png`, `favicon.png`, `icono.png`, `createc-icon.webp` e imagenes de industrias/proyectos/fotografia.
- Documentacion de hosting: `assets/README-HOSTING.txt`
- Estado: buena base visual para web principal, pero aun no esta estructurada como `public_html/index.php`.

### catalogo_rodeo

Aplicacion Electron/JS para generar catalogos B2B, mas paquete PHP de hosting con panel admin, API publica y Super Admin SaaS inicial.

- Ruta local real: `catalogo_rodeo/catalogo_rodeo-main/`
- Repo Git detectado: no en la carpeta extraida.
- Entradas principales escritorio: `index.html`, `main.js`, `script.js`, `styles.css`, `preload.js`
- Dependencias Electron: `electron`, `@electron/packager`
- Bot WhatsApp: `whatsapp_bot/` con `dotenv`, `mysql2`, `qrcode-terminal`, `whatsapp-web.js`
- Hosting PHP:
  - `hosting/catalogos_admin/`: admin de catalogos
  - `hosting/catalogos_vendedor/`: panel de vendedor
  - `hosting/catalogos_api/`: endpoints API
  - `hosting/super_admin/`: Super Admin SaaS
  - `hosting/includes/company_context.php`: resolucion multiempresa por dominio/subdominio
  - `hosting/sql/`: schema base y migraciones
- Entrada publica de catalogos: `hosting/catalogos/promo.php`
- Config sensible no real: `hosting/catalogos_api/config.example.php`
- Archivo `.env.example` para Backblaze/CDN. No se detecto `.env` real.

### sistema_rifa

Aplicacion PHP/MySQL para rifas con area publica, panel admin, API, PWA, notificaciones push y WhatsApp.

- Ruta local: `sistema_rifa/`
- Repo Git detectado: si, dentro de `sistema_rifa/.git`
- Entrada publica: `public/index.php`
- Vista de rifa: `public/rifa.php`
- Panel admin: `admin/login.php`, `admin/dashboard.php`, `admin/rifas.php`, `admin/reservas.php`, `admin/pagos.php`, `admin/ganadores.php`
- API: `api/reserve_numbers.php`, `api/upload_receipt.php`, `api/confirm_payment.php`, `api/mark_winner.php`, `api/whatsapp_webhook.php`, etc.
- Configuracion:
  - `config/app.php`
  - `config/database.php`
  - `config/config.example.php`
  - `config/config.hosting.createcpty.example.php`
  - No se detecto `config/config.php` real en el analisis.
- Seguridad ya presente: sesiones con `httponly`, `samesite=Lax`, `secure` segun HTTPS, CSRF, `password_hash`, `password_verify`, PDO con prepared statements.
- SQL:
  - Modelo moderno: `database/schema.sql`
  - Seeds: `database/seed.sql`, `database/seed.hosting_safe.sql`
  - Migraciones: push, WhatsApp y diseno
  - Dump alterno/hosting: `pandqgxl_rifa_panama.sql`

### Bases de datos

La carpeta solicitada `databases/` no existe con ese nombre. Se detecto una carpeta `basedatos/`, pero esta vacia al momento del analisis.

Archivos SQL reales detectados:

- `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/catalog_platform.sql`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/20260418_b2b_upgrade.sql`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/20260419_b2b_schema_compat.sql`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/20260425_campaigns_module.sql`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/20260505_super_admin_base.sql`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/20260508_super_admin_connect_companies.sql`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/20260509_saas_publish_logs.sql`
- `sistema_rifa/database/schema.sql`
- `sistema_rifa/pandqgxl_rifa_panama.sql`

No se encontraron `databases/createcgroup.sql`, `databases/catalogo_rodeo.sql` ni `databases/sistema_rifa.sql` en la ruta indicada.

## 2. Estructura detectada

```text
CREATEC_WORKSPACE/
├── CREATECGROUP/
│   ├── .git/
│   ├── index.html
│   ├── README.md
│   └── assets/
│       ├── README-HOSTING.txt
│       └── img/
├── catalogo_rodeo/
│   └── catalogo_rodeo-main/
│       ├── index.html
│       ├── main.js
│       ├── script.js
│       ├── styles.css
│       ├── package.json
│       ├── .env.example
│       ├── hosting/
│       │   ├── catalogos/
│       │   ├── catalogos_admin/
│       │   ├── catalogos_api/
│       │   ├── catalogos_vendedor/
│       │   ├── includes/
│       │   ├── sql/
│       │   └── super_admin/
│       ├── docs/
│       ├── fonts/
│       ├── openclaw_integration/
│       └── whatsapp_bot/
├── sistema_rifa/
│   ├── .git/
│   ├── admin/
│   ├── api/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── storage/
│   ├── tools/
│   └── pandqgxl_rifa_panama.sql
├── basedatos/
└── database/
```

## 3. Archivos importantes

### CREATECGROUP

- `CREATECGROUP/index.html`: web principal actual.
- `CREATECGROUP/assets/img/logo.png`: logo CREATEC.
- `CREATECGROUP/assets/img/favicon.png`: favicon actual.
- `CREATECGROUP/assets/img/icono.png`: icono touch/app.
- `CREATECGROUP/assets/README-HOSTING.txt`: instrucciones actuales de hosting.

### catalogo_rodeo

- `hosting/catalogos_api/config.example.php`: plantilla de DB/API/SMTP.
- `hosting/catalogos_api/helpers.php`: conexion PDO, sesiones admin, login, CSRF, API key, pedidos y correo.
- `hosting/catalogos_api/validate_license.php`: validacion SaaS de licencia.
- `hosting/catalogos_api/saas_license_helpers.php`: reglas de licencia y logs.
- `hosting/includes/company_context.php`: resolucion de empresa por host.
- `hosting/super_admin/login.php`: login Super Admin.
- `hosting/super_admin/dashboard.php`: tablero central.
- `hosting/super_admin/companies.php`: gestion empresas.
- `hosting/super_admin/company_domains.php`: dominios/subdominios.
- `hosting/super_admin/plans.php`: planes.
- `hosting/super_admin/subscriptions.php`: suscripciones.
- `hosting/super_admin/licenses.php`: licencias.
- `hosting/sql/catalog_platform.sql`: base catalogos.
- `hosting/sql/20260505_super_admin_base.sql`: tablas `sa_`.
- `hosting/sql/20260508_super_admin_connect_companies.sql`: planes/dominios y ampliacion de empresas.

### sistema_rifa

- `config/app.php`: configuracion base, sesiones, CSRF, helpers y audit log.
- `config/database.php`: conexion PDO.
- `config/config.example.php`: plantilla local.
- `config/config.hosting.createcpty.example.php`: plantilla cPanel.
- `public/index.php`: pagina publica.
- `public/rifa.php`: detalle/reserva de rifa.
- `admin/login.php`: login.
- `admin/includes/auth.php`: proteccion admin.
- `database/schema.sql`: schema moderno.
- `pandqgxl_rifa_panama.sql`: dump alterno con datos y nombres antiguos.

## 4. Configuraciones sensibles detectadas

No se detectaron archivos reales `config.php` ni `.env` con credenciales productivas en los proyectos analizados.

Plantillas y campos sensibles encontrados:

- `catalogo_rodeo/.../.env.example`: Backblaze B2 (`B2_BUCKET_NAME`, `B2_KEY_ID`, `B2_APPLICATION_KEY`, `B2_ENDPOINT`).
- `catalogo_rodeo/.../hosting/catalogos_api/config.example.php`: DB, `api_key`, SMTP, correo.
- `sistema_rifa/config/config.example.php`: DB local y tokens WhatsApp vacios.
- `sistema_rifa/config/config.hosting.createcpty.example.php`: DB cPanel con placeholders.
- `sistema_rifa/database/schema.sql`: columnas para `api_token`, `webhook_verify_token`, `auth_token`.
- `sistema_rifa/pandqgxl_rifa_panama.sql`: contiene hashes/passwords de usuarios insertados; tratar como sensible.

Recomendacion: no subir dumps, `.env`, `config.php`, SQL ni backups a carpetas publicas de hosting.

## 5. Tablas criticas detectadas

### Super Admin / SaaS en catalogo_rodeo

- `sa_admin_users`
- `sa_companies`
- `sa_plans`
- `sa_subscriptions`
- `sa_licenses`
- `sa_company_domains`
- `sa_activity_logs`
- `saas_publish_logs`

### Catalogos

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

### Rifas moderno

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

### Rifas dump alterno

- `usuarios`
- `rifas`
- `numeros_rifa`
- `transacciones`
- `configuracion`
- `logs_sistema`

## 6. Riesgos detectados

- La ruta `databases/` indicada no existe; `basedatos/` esta vacia.
- `CREATECGROUP` es estatico y usa `index.html`; para raiz limpia `https://createcpty.com/` se debera decidir entre renombrar/copiar a `index.php` o usar rewrite/DirectoryIndex.
- `catalogo_rodeo` trae `CREATE DATABASE` y `USE catalog_platform` en `catalog_platform.sql`; en cPanel puede convenir remover `USE` y ejecutar sobre la base seleccionada.
- Algunas migraciones de catalogos crean tablas backup con `CREATE TABLE ... AS SELECT`. No son destructivas, pero pueden exponer datos si quedan publicas.
- Existen dos modelos de rifas: moderno en ingles y dump anterior en espanol. No se deben fusionar sin definir fuente oficial.
- Hay tablas genericas repetidas entre modulos: `settings`, `audit_logs`, `notifications`, `users/admins`. Requieren prefijos para base unica.
- `catalogs` parece almacenar productos en JSON/estructura de catalogo, mientras la propuesta SaaS espera `cat_products`; se necesita adaptador/migracion progresiva.
- SMTP/API keys/tokens deben quedarse fuera de Git y de `public_html`.
- Carpetas `database/`, `sql/`, `storage/logs/` y dumps no deben quedar navegables publicamente.

## 7. Recomendacion de integracion

1. Mantener `CREATECGROUP` como raiz corporativa y preparar una capa de hosting sin alterar la web actual.
2. Copiar en fases, no mover destructivamente:
   - `catalogo_rodeo/.../hosting/super_admin/` hacia `CREATECGROUP/super_admin/`
   - `hosting/catalogos_api/validate_license.php` y helpers necesarios hacia `CREATECGROUP/catalogos_api/`
   - `hosting/includes/company_context.php` hacia `CREATECGROUP/includes/`
3. Crear `CREATECGROUP/projects/` y copiar modulos como instancias SaaS en fases posteriores:
   - `projects/catalogos/`
   - `projects/rifas/`
4. Mantener configs por ambiente:
   - `config.example.php` en repositorio
   - `config.php` solo en servidor
5. Usar `createc_saas` como base nueva propuesta, con prefijos `sa_`, `web_`, `cat_`, `rifa_`, etc.
6. Agregar `company_id` solo en migraciones controladas y despues de adaptar consultas.
7. Probar primero en staging/subdominio antes de tocar produccion.

## 8. Rutas que no deben romperse

- `CREATECGROUP/index.html`
- `CREATECGROUP/assets/img/*`
- `catalogo_rodeo/catalogo_rodeo-main/index.html`
- `catalogo_rodeo/catalogo_rodeo-main/main.js`
- `catalogo_rodeo/catalogo_rodeo-main/script.js`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/catalogos_admin/login.php`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/catalogos_admin/index.php`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/catalogos_api/*`
- `catalogo_rodeo/catalogo_rodeo-main/hosting/super_admin/*`
- `sistema_rifa/public/index.php`
- `sistema_rifa/public/rifa.php`
- `sistema_rifa/admin/login.php`
- `sistema_rifa/admin/dashboard.php`
- `sistema_rifa/api/*`
- `sistema_rifa/config/app.php`
- `sistema_rifa/config/database.php`

## 9. Estado de esta fase

Cambios realizados en Fase 1:

- Se creo documentacion de analisis.
- Se creo carpeta raiz `database/` para propuestas nuevas.
- No se modificaron proyectos existentes.
- No se modificaron configs.
- No se ejecuto SQL.
- No se importaron ni fusionaron bases.
