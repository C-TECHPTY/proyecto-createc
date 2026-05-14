# RifaGrid / GIBEL Rifas

WebApp/PWA en PHP 8+, MySQL, HTML, CSS y JavaScript vanilla para crear rifas online, publicar una grilla interactiva y reservar numeros con pago manual por Yappy/WhatsApp.

## Estado

Sistema base implementado con:

- Configuracion base sin credenciales reales.
- Login admin con `password_hash`.
- CRUD de rifas.
- Grilla configurable del 00 al 99, 00 al 100 o rango personalizado.
- Pagina publica responsive.
- Reserva transaccional de numeros.
- Pagos manuales y comprobantes.
- Confirmacion/cancelacion admin.
- Notificaciones internas.
- PWA instalable con Web Push real para administradores.
- Ganadores por modo manual.
- Transparencia publica con iniciales.
- Puntos de fidelidad y reportes CSV.
- Preparacion WhatsApp API / bot futuro.
- Diseno configurable por rifa con flyer completo y grilla interactiva.

El sistema sigue funcionando en modo manual con enlaces `wa.me` aunque no configures ninguna API externa.

## Accesos demo

- URL admin local: `http://localhost/rifa/admin/login.php`
- Usuario: `admin@rifagrid.local`
- Contrasena: `Admin123!`

## Carpetas

- `public/`: sitio publico, PWA, assets y uploads.
- `admin/`: panel administrativo.
- `api/`: endpoints AJAX y futuras integraciones.
- `config/`: configuracion y conexion PDO.
- `database/`: schema, seeds y migraciones.
- `storage/logs/`: espacio para logs operativos.
- `tools/generate_vapid_keys.php`: genera claves VAPID para notificaciones push.

## Seguridad

No subas archivos con credenciales reales. Usa `config/config.example.php` o `config/config.hosting.createcpty.example.php` como plantilla y crea `config/config.php` solo en el servidor.
