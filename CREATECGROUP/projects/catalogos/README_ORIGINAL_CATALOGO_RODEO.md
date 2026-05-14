# Catalogo Rodeo B2B

Bundle web listo para hosting con:

- API PHP segura en `catalogos_api/`
- panel admin en `catalogos_admin/`
- panel vendedor en `catalogos_vendedor/`
- assets compartidos en `assets/`
- SQL base en `sql/catalog_platform.sql`
- migracion evolutiva en `sql/20260418_b2b_upgrade.sql`
- migracion de inteligencia comercial en `sql/20260423_intelligence_events.sql`
- migracion de imagen de vendedor y branding en `sql/20260424_seller_photo_branding.sql`
- migraciones de pedidos confirmados, tokens por vendedor y correos administrativos en `sql/20260428_phase*.sql`
- migracion de logs para envio de catalogos a vendedores en `sql/20260505_catalog_seller_email_logs.sql`

## Cambios recientes

- El panel admin agrega **Enviar a vendedores** en catalogos publicados para mandar correos individuales con link seguro `?token=TOKEN`.
- La pantalla de envio confirma catalogo, cantidad de vendedores activos con correo, opcion de crear link si falta y resumen de enviados/omitidos/errores.
- Los envios quedan registrados en `catalog_seller_email_logs`.
- El catalogo publico muestra **Disp:** en tarjetas cuando el producto tiene disponible.
- La app usa `#2c4695` como color corporativo por defecto y permite editar el texto del hero B2B.
- El catalogo publico ahora usa un paso de **Revisar pedido** antes de enviar; el cliente debe confirmar que reviso productos, cantidades y datos.
- Los pedidos pueden guardar confirmacion del cliente, fecha, IP, token de vendedor y nuevos estados operativos.
- Cada vendedor puede tener un token publico para compartir catalogos con `?t=TOKEN` y atribuir pedidos sin crear un link seguro por cliente.
- El panel admin permite anular, archivar, marcar pedidos como prueba y limpiar solo pruebas con doble confirmacion.
- Los correos de pedidos incluyen vendedor, cliente, confirmacion, enlace directo al admin y destinatarios administrativos configurables.

## Estructura sugerida

```text
public_html/
  catalogos/
  catalogos_api/
  catalogos_admin/
  catalogos_vendedor/
  assets/
```

## Instalacion rapida

1. Importa `sql/catalog_platform.sql` si es instalacion nueva.
2. Si ya existe una base previa, ejecuta `sql/20260418_b2b_upgrade.sql`.
   Para activar tracking e inteligencia comercial, ejecuta tambien `sql/20260423_intelligence_events.sql`.
   Para fotos de vendedor y branding visual, ejecuta tambien `sql/20260424_seller_photo_branding.sql`.
3. Para activar la fase de pedidos seguros, ejecuta en orden:
   - `sql/20260428_phase1_order_safety.sql`
   - `sql/20260428_phase2_customer_confirmation.sql`
   - `sql/20260428_phase3_seller_tokens.sql`
   - `sql/20260428_phase4_order_email_settings.sql`
4. Para usar **Enviar a vendedores**, ejecuta `sql/20260505_catalog_seller_email_logs.sql`.
5. Copia `catalogos_api/config.example.php` como `catalogos_api/config.php`.
6. Ajusta credenciales MySQL, `api_key`, correo remitente y zona horaria.
7. Sube `catalogos_api/`, `catalogos_admin/`, `catalogos_vendedor/`, `assets/` y permite escritura en `uploads/`.
8. Manten `sql/` fuera del acceso publico si el hosting lo permite.
9. Publica los catalogos generados por Electron dentro de `catalogos/<slug>/`.

## Migraciones del 2026-04-28

- `20260428_phase1_order_safety.sql`: agrega respaldo de `orders`, marca `is_test`, `deleted_at`, `deleted_by`, estados nuevos e indices para limpieza segura.
- `20260428_phase2_customer_confirmation.sql`: agrega `customer_confirmed`, `confirmed_at` y `customer_ip`.
- `20260428_phase3_seller_tokens.sql`: agrega `sellers.public_token`, `orders.seller_token` e indices para atribucion por vendedor.
- `20260428_phase4_order_email_settings.sql`: agrega `order_admin_emails` en `app_settings`.

## Migracion del 2026-05-05

- `20260505_catalog_seller_email_logs.sql`: crea `catalog_seller_email_logs` para registrar cada envio de catalogo publicado a vendedores, con catalogo, vendedor, link seguro, token, email, estado, error y fecha.

## Enviar catalogos a vendedores

1. Publica el catalogo desde la app Electron.
2. Ejecuta `sql/20260505_catalog_seller_email_logs.sql` si aun no existe la tabla.
3. Entra al admin en `catalogos_admin/catalogos.php`.
4. Presiona **Enviar a vendedores** en un catalogo activo.
5. Confirma el envio y deja marcada la opcion de crear link seguro si no existe.

El sistema envia un correo individual por vendedor activo con email valido. No usa CC/BCC masivo. Si ya existe link seguro activo para ese catalogo y vendedor, reutiliza el token; si no existe, crea uno nuevo cuando la opcion esta marcada.

## Usuario inicial

- usuario: `admin`
- clave temporal: `AdminRodeo2026!`
- cambia el hash de `catalog_users.password_hash` antes de produccion

## Recomendaciones de produccion

- PHP 8.1 o superior
- MySQL 8.0+ o MariaDB 10.6+
- `ZipArchive` habilitado
- `mail()` funcional o relay SMTP del hosting
- HTTPS obligatorio
- cambia `api_key` y hash admin antes de exponer el sistema

## Correo SMTP

Si cPanel bloquea `mail()` con errores como `550 5.7.1 EASender blocked`,
configura SMTP autenticado en `catalogos_api/config.php`:

```php
'mail' => [
    'from_name' => 'Rodeo Import',
    'from_email' => 'catalogos@rodeoimportzl.com',
    'smtp' => [
        'enabled' => true,
        'host' => 'mail.rodeoimportzl.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'catalogos@rodeoimportzl.com',
        'password' => 'CLAVE_DEL_CORREO',
        'timeout' => 20,
    ],
],
```
