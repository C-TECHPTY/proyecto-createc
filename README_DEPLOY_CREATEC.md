# Deploy CREATEC SaaS en cPanel / Namecheap

Fase 9: guia exacta para subir `CREATECGROUP` a `public_html`.

Dominio principal:

```text
https://createcpty.com/
```

Objetivo final en hosting:

```text
public_html/
├── index.php
├── .htaccess
├── assets/
├── public/
├── super_admin/
├── catalogos_api/
├── includes/
├── projects/
└── database/
```

Nota critica: `database/` y carpetas `sql/` solo deben estar temporalmente en hosting para migracion. Despues de ejecutar SQL, deben retirarse de `public_html` o quedar fuera del acceso publico.

## 1. Preparacion local antes de subir

Verificar que existen:

```text
CREATECGROUP/index.php
CREATECGROUP/.htaccess
CREATECGROUP/index.html
CREATECGROUP/assets/
CREATECGROUP/super_admin/
CREATECGROUP/catalogos_api/
CREATECGROUP/includes/
CREATECGROUP/projects/
CREATECGROUP/database/
```

Verificar que NO existen dentro de `CREATECGROUP`:

```text
config.php
.env
*.zip
backups
dumps productivos
credenciales reales
```

En esta preparacion, solo deben existir plantillas:

```text
includes/config.example.php
catalogos_api/config.example.php
projects/catalogos/catalogos_api/config.example.php
projects/rifas/config/config.example.php
projects/rifas/config/config.hosting.createcpty.example.php
```

## 2. Que carpetas subir a `public_html`

Subir desde:

```text
CREATECGROUP/
```

hacia:

```text
public_html/
```

Subir:

```text
index.php
index.html
.htaccess
assets/
super_admin/
catalogos_api/
includes/
projects/
database/   solo temporal para migracion
README*.md  opcional, preferible no subir a produccion
```

No subir:

```text
.git/
node_modules/
.env
config.php local
*.zip
backups/
databases productivas exportadas con datos
```

## 3. Donde copiar la web principal

Copiar a raiz:

```text
public_html/index.php
public_html/index.html
public_html/.htaccess
public_html/assets/
```

Prueba esperada:

```text
https://createcpty.com/
```

Debe cargar la web corporativa CREATEC.

## 4. Donde copiar Super Admin

Copiar:

```text
CREATECGROUP/super_admin/
```

a:

```text
public_html/super_admin/
```

URL:

```text
https://createcpty.com/super_admin/
https://createcpty.com/super_admin/login.php
```

## 5. Donde copiar `catalogos_api`

Copiar:

```text
CREATECGROUP/catalogos_api/
```

a:

```text
public_html/catalogos_api/
```

Este API central se usa para validacion SaaS/licencias.

URL de prueba:

```text
https://createcpty.com/catalogos_api/validate_license.php
```

Debe responder JSON. Si falta licencia, debe indicar que falta `license_key`.

## 6. Donde copiar `includes`

Copiar:

```text
CREATECGROUP/includes/
```

a:

```text
public_html/includes/
```

Despues de subir, crear manualmente en el hosting:

```text
public_html/includes/config.php
```

usando como base:

```text
public_html/includes/config.example.php
```

Completar solo en hosting:

```text
DB_HOST
DB_NAME
DB_USER
DB_PASS
api_key
timezone
```

No sobrescribir `includes/config.php` si ya existe con credenciales reales.

## 7. Donde copiar `projects`

Copiar:

```text
CREATECGROUP/projects/
```

a:

```text
public_html/projects/
```

Debe quedar:

```text
public_html/projects/catalogos/
public_html/projects/rifas/
public_html/projects/barber/
public_html/projects/turismo/
public_html/projects/spa/
public_html/projects/ecommerce/
```

### Catalogos

Ruta:

```text
public_html/projects/catalogos/
```

URLs:

```text
https://createcpty.com/projects/catalogos/
https://createcpty.com/projects/catalogos/catalogos_admin/login.php
```

Subdominio futuro:

```text
https://catalogos.createcpty.com/
```

### Rifas

Ruta:

```text
public_html/projects/rifas/
```

URLs:

```text
https://createcpty.com/projects/rifas/
https://createcpty.com/projects/rifas/admin/login.php
```

Subdominio futuro:

```text
https://rifas.createcpty.com/
```

## 8. Donde copiar `database`

Copiar temporalmente:

```text
CREATECGROUP/database/
```

a:

```text
public_html/database/
```

Solo mientras se ejecutan migraciones desde cPanel/phpMyAdmin.

Despues de migrar:

```text
Mover fuera de public_html
o eliminar del hosting publico
o proteger con .htaccess
```

Recomendado:

```text
/home/USUARIO_CPANEL/createc_private/database/
```

## 9. Donde ejecutar SQL

En cPanel:

1. Abrir **MySQL Databases**.
2. Crear base:

```text
createc_saas
```

En cPanel normalmente el nombre final incluye prefijo:

```text
usuario_createc_saas
```

3. Crear usuario MySQL.
4. Asignar usuario a base con permisos necesarios.
5. Abrir **phpMyAdmin**.
6. Seleccionar la base.
7. Ejecutar SQL por orden.

Orden recomendado para Super Admin actual:

```text
database/20260505_super_admin_base.sql
database/20260508_super_admin_connect_companies.sql
database/20260509_saas_publish_logs.sql
database/20260510_createc_saas_core_structure.sql
```

Para propuesta completa/staging:

```text
database/createc_saas_schema_proposed.sql
```

No ejecutar SQL de catalogos/rifas productivos sin definir migracion:

```text
database/catalog_platform.sql
projects/catalogos/sql/*.sql
projects/rifas/database/*.sql
```

## 10. Crear primer Super Admin

Generar hash en un entorno seguro con PHP:

```bash
php -r "echo password_hash('CAMBIA_ESTA_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"
```

Luego insertar manualmente en phpMyAdmin:

```sql
INSERT INTO sa_admin_users (name, email, password_hash, role, status)
VALUES ('Super Admin', 'admin@createcpty.com', 'HASH_GENERADO_AQUI', 'super_admin', 'active');
```

No guardar la clave plana en archivos.

## 11. Que carpetas borrar o retirar luego de migrar

Retirar de `public_html`:

```text
public_html/database/
public_html/projects/catalogos/sql/
public_html/projects/rifas/database/
```

Opciones:

1. Moverlas fuera de `public_html`.
2. Eliminarlas del hosting despues de confirmar backup.
3. Mantenerlas solo si `.htaccess` las bloquea y no hay alternativa.

Preferido: mover fuera de webroot.

## 12. Archivos que NO subir

No subir nunca:

```text
.git/
.env
config.php local
config.hosting.createcpty.php real
*.zip
backups
dumps con datos reales
.rnd
tree_elements.json
usuario database.txt
credenciales FTP
credenciales SMTP
API keys reales
```

## 13. Archivos que NO sobrescribir

No sobrescribir en hosting si ya existen:

```text
public_html/includes/config.php
public_html/catalogos_api/config.php
public_html/projects/catalogos/catalogos_api/config.php
public_html/projects/rifas/config/config.php
```

Estos archivos contienen credenciales reales de ambiente.

## 14. Configs de hosting

### Config central CREATEC

Crear:

```text
public_html/includes/config.php
```

desde:

```text
public_html/includes/config.example.php
```

### Config catalogos como modulo

Si el modulo `projects/catalogos` usa su API interna, crear:

```text
public_html/projects/catalogos/catalogos_api/config.php
```

desde:

```text
public_html/projects/catalogos/catalogos_api/config.example.php
```

### Config rifas

Crear:

```text
public_html/projects/rifas/config/config.php
```

desde:

```text
public_html/projects/rifas/config/config.hosting.createcpty.example.php
```

No subir configs reales desde local.

## 15. Como probar la web

Abrir:

```text
https://createcpty.com/
```

Debe verse:

- Logo CREATEC.
- Favicon.
- Servicios.
- Proyectos.
- SaaS.
- Fotografia comercial.
- Contacto.

Probar redirecciones:

```text
https://createcpty.com/index.html
https://createcpty.com/public/index.php
```

Deben redirigir o cargar limpio segun `.htaccess`.

## 16. Como probar Super Admin

Abrir:

```text
https://createcpty.com/super_admin/login.php
```

Probar:

- Login.
- Dashboard.
- Empresas.
- Dominios.
- Planes SaaS.
- Suscripciones.
- Licencias.
- Modulos.
- Instancias.
- Publicaciones SaaS.

Si aparece error de config:

```text
No existe includes/config.php
```

crear config real en hosting.

## 17. Como probar API de licencia

Sin licencia:

```text
https://createcpty.com/catalogos_api/validate_license.php
```

Debe responder JSON indicando falta `license_key`.

Con POST JSON:

```json
{
  "license_key": "LICENCIA_DE_PRUEBA",
  "company_slug": "createc",
  "device_id": "staging-device",
  "app_version": "1.0.0"
}
```

Validar:

- Respuesta JSON.
- Estado de licencia.
- `company_id`.
- `allowed_publish`.

## 18. Como probar subdominios

Crear subdominios como indica `README_SUBDOMINIOS_CPANEL.md`.

Pruebas:

```text
https://catalogos.createcpty.com/
https://catalogos.createcpty.com/catalogos_admin/login.php
https://rifas.createcpty.com/
https://rifas.createcpty.com/admin/login.php
```

Verificar:

- DNS apunta al hosting.
- AutoSSL activo.
- Document root correcto.
- `.htaccess` activo.

## 19. Como probar proyectos

### Catalogos

```text
https://createcpty.com/projects/catalogos/catalogos_admin/login.php
```

Probar:

- Login.
- Dashboard.
- Catalogos.
- Pedidos.
- Vendedores.
- Links.

### Rifas

```text
https://createcpty.com/projects/rifas/
https://createcpty.com/projects/rifas/admin/login.php
```

Probar:

- Vista publica.
- Login admin.
- Crear rifa.
- Reservar numero.
- Subir comprobante.
- Confirmar pago.

## 20. Checklist de deploy

```text
[ ] Backup del hosting actual
[ ] Backup de bases actuales
[ ] Subir index.php
[ ] Subir .htaccess
[ ] Subir index.html
[ ] Subir assets/
[ ] Subir super_admin/
[ ] Subir catalogos_api/
[ ] Subir includes/
[ ] Subir projects/
[ ] Subir database/ solo temporal
[ ] Crear includes/config.php en hosting
[ ] Crear base createc_saas
[ ] Ejecutar SQL Super Admin
[ ] Crear usuario Super Admin
[ ] Probar web principal
[ ] Probar Super Admin
[ ] Probar API licencia
[ ] Probar projects/catalogos
[ ] Probar projects/rifas
[ ] Crear subdominios
[ ] Activar AutoSSL
[ ] Registrar dominios en Super Admin
[ ] Registrar instancias en Super Admin
[ ] Retirar/proteger database/
[ ] Retirar/proteger sql/
[ ] Confirmar que no hay configs reales en Git
```

## 21. Rollback

Antes de cambiar produccion:

1. Descargar copia completa de `public_html`.
2. Exportar bases actuales.
3. Guardar configs reales fuera de webroot.

Si algo falla:

1. Restaurar archivos anteriores.
2. Restaurar base si se ejecuto migracion erronea.
3. Desactivar subdominio o apuntarlo a carpeta anterior.
4. Revisar logs de PHP/cPanel.

## 22. Prohibido durante deploy

- No hacer `DROP TABLE`.
- No borrar datos.
- No sobrescribir configs reales.
- No subir credenciales.
- No borrar usuarios.
- No borrar pedidos.
- No borrar rifas.
- No borrar catalogos.
- No activar subdominios sin SSL.
- No dejar SQL publico.
