# Seguridad CREATEC SaaS

Fase 8: revision y propuesta de seguridad para `CREATECGROUP`, Super Admin, catalogos, rifas y futuros subdominios.

Estado: documentacion y recomendaciones. No se modificaron credenciales, bases ni produccion.

## 1. Resumen ejecutivo

CREATEC ya tiene varias practicas correctas:

- Uso de PDO con `PDO::ATTR_EMULATE_PREPARES => false`.
- Passwords con `password_hash()` y `password_verify()`.
- CSRF en Super Admin, catalogos y rifas.
- Sesiones con `httponly` y `samesite`.
- API key para endpoints privados de catalogos.
- `.htaccess` para desactivar listado de directorios y bloquear archivos sensibles.
- Logs/auditoria inicial en Super Admin, catalogos y rifas.

Riesgos principales antes de produccion:

- No dejar carpetas `database/` ni `sql/` publicas.
- No subir `config.php`, `.env`, dumps, backups ni zips.
- Crear configs reales solo en hosting.
- Reforzar roles/permisos antes de multiempresa real.
- Validar aislamiento por `company_id` antes de migrar datos.
- Activar HTTPS en dominio principal y subdominios.

## 2. Hallazgos por area

### Web principal CREATEC

Archivos relevantes:

- `CREATECGROUP/index.php`
- `CREATECGROUP/index.html`
- `CREATECGROUP/.htaccess`
- `CREATECGROUP/assets/`

Seguridad actual:

- `index.php` solo carga `index.html`.
- `.htaccess` define `Options -Indexes`.
- `.htaccess` bloquea archivos como README, `.md`, `.sql`, `.env` y `config.php`.
- Cabeceras basicas: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.

Recomendado:

- Mantener la web principal sin credenciales.
- No ubicar SQL, dumps o backups en raiz publica.
- Agregar Content Security Policy cuando se estabilicen scripts/CDN.

### Super Admin

Archivos relevantes:

- `CREATECGROUP/super_admin/`
- `CREATECGROUP/super_admin/includes/auth.php`
- `CREATECGROUP/super_admin/includes/helpers.php`
- `CREATECGROUP/includes/db.php`
- `CREATECGROUP/includes/config.example.php`

Seguridad actual:

- Login con `password_verify()`.
- Rehash con `password_needs_rehash()`.
- CSRF en formularios.
- Sesion con `httponly`, `secure` segun HTTPS, `samesite=Lax`.
- `session_regenerate_id(true)` despues de login.
- Auditoria via `sa_activity_logs`.

Riesgos:

- La autorizacion actual es principalmente "usuario logueado"; falta matriz fina por permiso.
- No hay rate limit de login.
- No hay bloqueo temporal por intentos fallidos.
- `sa_admin_users` todavia no esta unificado con `sa_users`/roles/permisos.

Recomendado:

- Agregar roles y permisos por accion: empresas, planes, licencias, dominios, modulos, instancias.
- Registrar intentos fallidos de login.
- Bloquear login por IP/email tras varios intentos.
- Forzar HTTPS.
- Usar emails unicos y passwords fuertes.
- Crear primer Super Admin con hash seguro, nunca con password plano en SQL.

### API central de licencias

Archivos relevantes:

- `CREATECGROUP/catalogos_api/validate_license.php`
- `CREATECGROUP/catalogos_api/saas_license_helpers.php`
- `CREATECGROUP/catalogos_api/helpers.php`

Seguridad actual:

- Respuestas JSON.
- Validacion por `license_key`.
- Registro de IP/user-agent en logs de publicacion cuando aplica.
- Conexion central por `includes/db.php`.

Riesgos:

- `license_key` por si solo puede filtrarse si se guarda en clientes de escritorio.
- No hay firma HMAC por request.
- No hay rate limit por IP/licencia.
- No hay versionado de API.

Recomendado:

- Enviar `license_key` sobre HTTPS solamente.
- Agregar `device_id`, `app_version` y firma HMAC con timestamp.
- Rechazar timestamps viejos para evitar replay.
- Registrar validaciones sospechosas.
- Rotar licencias comprometidas.
- Mantener endpoint de licencia separado de APIs publicas de catalogo.

### Catalogos

Archivos relevantes:

- `CREATECGROUP/projects/catalogos/catalogos_admin/`
- `CREATECGROUP/projects/catalogos/catalogos_vendedor/`
- `CREATECGROUP/projects/catalogos/catalogos_api/`
- `CREATECGROUP/projects/catalogos/.htaccess`

Seguridad actual:

- Login con `password_verify()`.
- CSRF en formularios.
- API key para publicar/exportar.
- Prepared statements.
- `.htaccess` bloquea `sql/` e `includes/` si Apache permite overrides.

Riesgos:

- `projects/catalogos/sql/` existe dentro del modulo copiado.
- `catalogos_api/config.php` no debe existir en Git, solo en hosting.
- Algunas pantallas muestran mensajes que mencionan rutas `hosting/sql`; no es critico, pero conviene limpiar en fases futuras.
- Publicacion de ZIP debe limitar tipos, tamano y rutas internas.

Recomendado:

- Retirar `projects/catalogos/sql/` de `public_html` despues de migrar.
- Crear `catalogos_api/config.php` manualmente solo en hosting.
- Cambiar API key inicial.
- Cambiar hash/password admin inicial.
- Validar subidas ZIP contra path traversal.
- Separar permisos `admin`, `sales`, `vendor`, `billing`, `operator`.

### Rifas

Archivos relevantes:

- `CREATECGROUP/projects/rifas/admin/`
- `CREATECGROUP/projects/rifas/api/`
- `CREATECGROUP/projects/rifas/config/`
- `CREATECGROUP/projects/rifas/public/`
- `CREATECGROUP/projects/rifas/.htaccess`

Seguridad actual:

- Login con `password_hash`.
- PDO.
- CSRF.
- Sesion con `httponly`, `secure` segun HTTPS y `samesite=Lax`.
- `.htaccess` bloquea `database/`, `storage/logs/` y `tools/`.
- Se retiro de la copia modular un config no-example con credenciales.

Riesgos:

- `projects/rifas/database/` existe dentro del modulo copiado.
- `storage/` debe protegerse contra ejecucion de PHP si recibe uploads.
- Subida de comprobantes requiere validacion estricta de MIME/tamano/extension.
- WhatsApp/web push pueden incluir tokens en config.

Recomendado:

- Retirar `projects/rifas/database/` de `public_html` despues de migrar.
- Crear `config/config.php` solo en hosting.
- Guardar uploads fuera de webroot si cPanel lo permite.
- Bloquear ejecucion PHP en carpetas de uploads.
- Validar comprobantes por extension, MIME real, tamano y nombre generado.
- Separar permisos admin/operador.

## 3. Sesiones seguras

Estado actual:

- Super Admin y rifas usan `session_set_cookie_params`.
- Cookies usan `httponly`.
- `secure` se activa cuando hay HTTPS.
- `samesite` esta en `Lax`.

Recomendaciones:

- Forzar HTTPS en produccion para que `secure` siempre este activo.
- Usar nombres de sesion separados por modulo:
  - `createc_super_admin_session`
  - `catalog_admin_session`
  - `rifagrid_session`
- Regenerar ID en cada login.
- Destruir sesion completa en logout.
- Considerar expiracion por inactividad.
- No compartir sesiones entre subdominios salvo necesidad real.

## 4. Login protegido

Recomendaciones:

- Registrar intentos fallidos con email, IP, user-agent y fecha.
- Bloquear temporalmente tras 5 intentos fallidos.
- Usar mensajes genericos: "Credenciales invalidas".
- Agregar CAPTCHA solo si hay abuso real.
- Enviar alerta al Super Admin si hay muchos fallos.
- No exponer si un email existe.

Tabla futura sugerida:

```text
sa_login_attempts
```

Campos:

```text
id, email, ip_address, user_agent, success, reason, created_at
```

## 5. Sanitizacion y validacion

Estado actual:

- Se usa `htmlspecialchars`/helpers `e`, `sa_e`, `html_escape`.
- Se usan prepared statements.

Recomendaciones:

- Validar todos los IDs como enteros positivos.
- Validar emails con `filter_var`.
- Normalizar telefonos.
- Validar slugs con whitelist.
- Nunca concatenar SQL con input del usuario.
- En respuestas JSON, usar `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.

## 6. CSRF

Estado actual:

- Super Admin tiene `sa_csrf_token`, `sa_csrf_field`, `sa_verify_csrf`.
- Catalogos tiene `csrf_token`, `csrf_field`, `verify_csrf_or_abort`.
- Rifas tiene `csrf_token` y `verify_csrf`.

Recomendaciones:

- Mantener CSRF en todo POST/PUT/DELETE.
- No exigir CSRF en webhooks externos, pero si validar firma/token del proveedor.
- Rotar token tras login si se desea mayor aislamiento.

## 7. Roles y permisos

Estado actual:

- Catalogos ya maneja roles operativos.
- Super Admin maneja rol basico en `sa_admin_users`.
- Propuesta SQL incluye `sa_roles`, `sa_permissions`, `sa_role_permissions`.

Recomendado para Super Admin:

- `super_admin`: acceso total.
- `ops_admin`: empresas, dominios, instancias.
- `billing_admin`: planes, suscripciones, licencias.
- `support`: lectura y soporte.
- `auditor`: solo lectura/logs.

Acciones a proteger:

- Crear/editar empresas.
- Suspender empresas.
- Crear licencias.
- Revocar licencias.
- Cambiar dominios.
- Asignar modulos.
- Cambiar document root/instancias.
- Ver logs.

## 8. Proteccion contra acceso directo

Ya existe:

- `.htaccess` raiz con `Options -Indexes`.
- `.htaccess` en `projects/catalogos/`.
- `.htaccess` en `projects/rifas/`.

Recomendado:

- Mantener `AllowOverride` activo en cPanel para que `.htaccess` aplique.
- Bloquear:

```text
*.sql
*.zip
*.env
config.php
database/
sql/
storage/logs/
tools/
```

- Mover `database/` y `sql/` fuera de `public_html` cuando termine la migracion.

## 9. Archivos que NO deben subirse publicamente

No subir a `public_html`:

- `.env`
- `config.php` locales
- dumps `.sql`
- backups `.zip`
- `.rnd`
- `tree_elements.json`
- archivos con usuarios/passwords
- logs
- claves privadas
- credenciales SMTP/FTP/API

Permitidos como plantilla:

- `config.example.php`
- `config.hosting.createcpty.example.php`
- README de instalacion sin secretos.

## 10. SQL y base de datos

Reglas:

- No ejecutar `DROP TABLE`.
- No ejecutar `DELETE` masivo.
- No ejecutar `TRUNCATE`.
- No modificar productivo sin respaldo.
- Ejecutar migraciones primero en staging.
- Verificar conteos antes/despues.
- Usar usuario MySQL con permisos necesarios, no mas.

Para `createc_saas`:

- Ejecutar por secciones.
- Confirmar compatibilidad con MySQL/MariaDB de cPanel.
- Mantener `company_id` en tablas de negocio.
- Probar aislamiento multiempresa.

## 11. Auditoria y logs

Tablas actuales/propuestas:

- `sa_activity_logs`
- `sa_audit_logs`
- `activity_logs`
- `audit_logs`
- `logs_sistema`
- `saas_publish_logs`

Recomendado registrar:

- Login exitoso/fallido.
- Logout.
- Cambios de empresa.
- Cambios de plan/suscripcion.
- Creacion/revocacion de licencias.
- Cambios de dominio/subdominio.
- Publicaciones SaaS.
- Cambios de configuracion.
- Confirmaciones de pago.
- Marcado de ganadores.

No registrar:

- Passwords.
- Tokens completos.
- API keys completas.
- Datos bancarios sensibles.

## 12. Uploads

Riesgos:

- Comprobantes de rifas.
- Flyers.
- ZIPs de catalogos.
- Imagenes de productos/logos.

Recomendaciones:

- Generar nombres aleatorios.
- Validar extension y MIME.
- Limitar tamano.
- Guardar fuera de webroot si es posible.
- Bloquear ejecucion PHP en uploads.
- No confiar en nombre original.
- Revisar ZIPs contra path traversal.

`.htaccess` recomendado dentro de uploads si quedan publicos:

```apache
Options -Indexes
<FilesMatch "\.(php|phtml|phar|cgi|pl|sh)$">
  Require all denied
</FilesMatch>
```

## 13. HTTPS y cabeceras

Recomendado en produccion:

- AutoSSL activo para `createcpty.com`.
- AutoSSL activo para todos los subdominios.
- Redireccion HTTP -> HTTPS.
- `X-Content-Type-Options: nosniff`.
- `X-Frame-Options: SAMEORIGIN`.
- `Referrer-Policy: strict-origin-when-cross-origin`.
- `Content-Security-Policy` en una fase posterior.
- `Strict-Transport-Security` solo cuando todos los subdominios esten listos con HTTPS.

## 14. Checklist antes de produccion

```text
[ ] No hay config.php real en Git
[ ] No hay .env en Git
[ ] No hay dumps .sql publicos despues de migrar
[ ] No hay zips/backups publicos
[ ] AutoSSL activo en dominio y subdominios
[ ] Login Super Admin probado
[ ] Login catalogos probado
[ ] Login rifas probado
[ ] CSRF probado en formularios criticos
[ ] API de licencia probada por HTTPS
[ ] API key de catalogos cambiada
[ ] Password admin inicial cambiado
[ ] Primer Super Admin creado con hash seguro
[ ] database/ y sql/ fuera de public_html o bloqueados
[ ] uploads sin ejecucion PHP
[ ] Logs no muestran secretos
[ ] Backups guardados fuera de public_html
[ ] Aislamiento company_id probado
```

## 15. Prioridad de mejoras

Alta:

- Retirar/proteger `database/` y `sql/` en produccion.
- Crear configs reales solo en hosting.
- Cambiar passwords/API keys iniciales.
- Activar HTTPS y AutoSSL.
- Bloquear intentos de login repetidos.

Media:

- Roles/permisos finos en Super Admin.
- HMAC para API de licencia.
- Logs centralizados en `sa_audit_logs`.
- Expiracion de sesiones por inactividad.

Baja:

- CSP completa.
- Panel de seguridad/auditoria visual.
- Alertas por email/Slack.

## 16. Que no se debe tocar todavia

- No tocar SMTP real.
- No tocar FTP real.
- No tocar API keys reales.
- No borrar datos.
- No borrar usuarios.
- No borrar pedidos.
- No borrar rifas.
- No borrar catalogos.
- No fusionar bases sin migracion probada.
