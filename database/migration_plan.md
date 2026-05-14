# Plan de migracion hacia `createc_saas`

Este documento es una guia. No ejecuta cambios y no reemplaza respaldos, pruebas ni revision manual.

Actualizacion Fase 7: `CREATECGROUP/projects/` ya existe con `catalogos/` y `rifas/` copiados como modulos. Eso no cambia el principio central: los datos siguen en sus modelos actuales hasta que se creen migraciones probadas en staging.

## 1. Fuentes esperadas y fuentes reales

Fuentes esperadas por el objetivo:

- `databases/createcgroup.sql`
- `databases/catalogo_rodeo.sql`
- `databases/sistema_rifa.sql`

Hallazgo actual:

- `databases/` no existe.
- `basedatos/` existe pero esta vacia.
- SQL real de catalogos esta en `catalogo_rodeo/catalogo_rodeo-main/hosting/sql/`.
- SQL real de rifas esta en `sistema_rifa/database/` y `sistema_rifa/pandqgxl_rifa_panama.sql`.
- No se detecto SQL propio para `CREATECGROUP`.

## 2. Principios

- No ejecutar cambios sobre produccion hasta tener respaldo y staging.
- No hacer operaciones destructivas.
- No renombrar tablas actuales en produccion.
- No agregar `company_id` a sistemas activos sin adaptar codigo.
- Mantener compatibilidad por vistas, adaptadores o doble escritura durante transicion.
- Cada migracion debe ser idempotente cuando sea posible.
- Cada modulo debe poder probarse aislado.

## 3. Preparacion

1. Crear respaldo completo de cada base actual desde cPanel/phpMyAdmin.
2. Descargar respaldo y guardar copia fuera de `public_html`.
3. Crear base staging `createc_saas_staging`.
4. Crear usuario MySQL staging con permisos limitados.
5. Ejecutar `database/createc_saas_schema_proposed.sql` en staging.
6. Crear empresa inicial:
   - `commercial_name`: CREATEC
   - `slug`: `createc`
   - `primary_domain`: `createcpty.com`
7. Crear modulos:
   - `catalogos`
   - `rifas`
   - `barber`
   - `turismo`
   - `spa`
   - `ecommerce`
8. Registrar instancias iniciales en `sa_project_instances` solo como metadata:
   - `projects/catalogos/`
   - `projects/rifas/`

## 4. Migracion CREATECGROUP

Actualmente no hay tablas.

Acciones:

1. Mantener archivos estaticos sin cambios.
2. Si se activa formulario de contacto, guardar nuevos registros en `web_contacts`.
3. Si se publica portfolio dinamico, migrar contenido manualmente a `web_portfolio_projects`.

Pruebas:

- Web principal carga desde raiz.
- Logo, favicon e imagenes cargan.
- Formularios no exponen errores ni credenciales.

## 5. Migracion catalogo_rodeo

Fuente principal propuesta: `catalog_platform.sql` mas migraciones aplicadas.

Orden:

1. Identificar base real en hosting.
2. Confirmar tablas actuales y columnas con `SHOW TABLES` y `DESCRIBE`.
3. Crear una fila `sa_companies` para la empresa propietaria de esos catalogos.
4. Migrar vendedores:
   - `sellers` -> `cat_sellers`
   - asignar `company_id`
5. Migrar clientes:
   - `clients` -> `cat_clients`
   - mapear `seller_id` si existe.
6. Migrar catalogos:
   - `catalogs` -> `cat_catalogs`
   - guardar ID original en `source_catalog_id`.
   - conservar JSON/metadatos en `metadata_json` si no hay tabla de productos normalizada.
7. Migrar productos solo si existe fuente confiable:
   - Si estan en JSON dentro de `catalogs`, crear extractor revisado.
   - Si estan en archivos generados, definir parser aparte.
8. Migrar tokens:
   - `catalog_share_links` -> `cat_catalog_tokens`
9. Migrar pedidos:
   - `orders` -> `cat_orders`
   - `order_items` -> `cat_order_items`
10. Migrar campanas:
   - `campaigns` -> `cat_campaigns`
   - tablas relacionadas despues.
11. Migrar logs importantes a `sa_audit_logs` o tablas `cat_*_logs`.

Columnas/relaciones que se deben revisar antes del script final:

- `catalogs.slug` debe ser unico por empresa, no global.
- `orders.order_number` debe ser unico por empresa si existe.
- `catalog_users.role` debe mapearse contra roles SaaS.
- `sellers.public_token` debe conservarse como token de vendedor.
- `catalog_share_links.token` debe conservarse y no regenerarse sin necesidad.
- `app_settings` debe separarse por empresa para branding/correos.

Compatibilidad recomendada:

- Mantener tablas actuales durante una fase.
- Adaptar codigo para resolver `company_id` por `company_context.php`.
- Agregar filtros multiempresa a lecturas antes de activar escritura multiempresa.
- Probar con una empresa piloto.

## 6. Migracion sistema_rifa moderno

Fuente principal propuesta: `sistema_rifa/database/schema.sql`.

Orden:

1. Confirmar si la app activa usa modelo moderno (`raffles`, `reservations`, etc.).
2. Crear empresa en `sa_companies`.
3. Migrar admins:
   - `admins` -> `sa_users`
   - asignar rol company admin.
4. Migrar clientes:
   - `customers` -> `rifa_customers`
5. Migrar rifas:
   - `raffles` -> `rifa_raffles`
   - asignar `company_id`.
6. Migrar numeros:
   - `raffle_numbers` -> `rifa_numbers`
7. Migrar reservas:
   - `reservations` -> `rifa_reservations`
8. Migrar relacion numeros/reservas:
   - `reservation_numbers` -> `rifa_reservation_numbers`
9. Migrar pagos:
   - `payments` -> `rifa_payments`
10. Migrar comprobantes:
   - `payment_receipts` -> `rifa_payment_receipts`
11. Migrar ganadores:
   - `winners` -> `rifa_winners`
12. Migrar settings:
   - `settings` -> `rifa_settings`
13. Migrar logs:
   - `audit_logs` -> `sa_audit_logs` o `rifa_audit_logs` si se decide crear tabla separada.

Columnas/relaciones que se deben revisar antes del script final:

- `raffles.slug` debe ser unico por empresa.
- `customers.whatsapp` debe ser unico por empresa, no global.
- `raffle_numbers` hereda empresa desde `raffle_id`.
- `reservations` debe guardar `company_id` directo para consultas rapidas.
- `payments` debe guardar `company_id` directo para reportes financieros.
- `settings` debe migrarse a `rifa_settings` por empresa.

Pruebas:

- Login admin.
- Crear rifa.
- Reservar numeros.
- Subir comprobante.
- Confirmar pago.
- Marcar ganador.
- Revisar PWA/push/WhatsApp si estan activos.

## 7. Migracion sistema_rifa legacy

Fuente: `sistema_rifa/pandqgxl_rifa_panama.sql`

Usar solo si esos datos son historicos necesarios.

Mapeo preliminar:

- `usuarios` -> `sa_users` o `rifa_customers`, segun `rol`.
- `rifas` -> `rifa_raffles`.
- `numeros_rifa` -> `rifa_numbers` y/o `rifa_reservations`.
- `transacciones` -> `rifa_payments`.
- `configuracion` -> `rifa_settings`.
- `logs_sistema` -> `sa_audit_logs`.

Precauciones:

- Revisar hashes de password.
- Separar admins de participantes.
- Validar estados legacy contra estados nuevos.
- No mezclar con modelo moderno sin deduplicar.

## 8. Adaptacion de codigo

1. Crear una capa central `includes/db.php` para `createc_saas`.
2. Crear `includes/company_context.php` para resolver empresa por host/subdominio.
3. Adaptar cada modulo para cargar `company_id`.
4. Agregar filtros `WHERE company_id = ?` en consultas de negocio.
5. Mantener archivos `config.example.php`; crear `config.php` solo en hosting.
6. Registrar licencias en `sa_licenses`.
7. Probar endpoint `catalogos_api/validate_license.php`.
8. Probar `super_admin/modules.php` e `super_admin/project_instances.php`.
9. Bloquear acceso publico a `projects/catalogos/sql/` y `projects/rifas/database/` si el document root apunta a esos modulos.

## 9. Validaciones antes de produccion

- Conteo origen vs destino por tabla.
- Conteo de pedidos por estado.
- Conteo de reservas por estado.
- Conteo de pagos confirmados.
- Total monetario por modulo.
- Usuarios activos.
- Subdominios asignados.
- Licencias activas/vencidas.
- Logs de error PHP.

## 10. Rollback

1. No borrar bases originales.
2. Mantener proyectos actuales intactos.
3. Si staging falla, descartar base staging y corregir scripts.
4. Si produccion falla durante piloto, apuntar subdominio al document root anterior.
5. Restaurar solo desde respaldos verificados.

## 11. Siguiente fase recomendada

Antes de migrar datos reales:

1. Confirmar si `basedatos/` debe renombrarse a `databases/` o si faltan los SQL exportados.
2. Confirmar cual modelo de rifas es el oficial: moderno (`database/schema.sql`) o legacy (`pandqgxl_rifa_panama.sql`).
3. Confirmar ambiente de pruebas en cPanel/Namecheap.
4. Definir si `sa_admin_users` se mantiene como tabla del Super Admin o se migra a `sa_users`.
5. Crear scripts de migracion de datos solo despues de validar conteos de origen.
