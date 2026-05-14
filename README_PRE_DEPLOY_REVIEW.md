# Revision pre-deploy CREATEC

Fecha: 2026-05-10

Este documento resume la revision previa a subir `CREATECGROUP/` a `public_html/`.
No ejecuta migraciones, no borra archivos y no modifica credenciales reales.

## 1. Estado general

Carpeta base revisada:

```text
CREATEC_WORKSPACE/CREATECGROUP/
```

Estructura lista para staging/deploy:

```text
CREATECGROUP/
├── index.php
├── index.html
├── .htaccess
├── assets/
├── catalogos_api/
├── database/
├── includes/
├── projects/
└── super_admin/
```

La carpeta tiene la forma esperada para copiar su contenido hacia:

```text
public_html/
```

## 2. Archivos sensibles

Revision realizada:

- No se detecto `CREATECGROUP/includes/config.php`.
- No se detectaron archivos `.env` dentro de `CREATECGROUP/`.
- No se detecto `config.hosting.createcpty.php` dentro de `CREATECGROUP/projects/rifas/`.
- Solo existen plantillas tipo `config.example.php` o `config.hosting.createcpty.example.php`.

Esto es correcto: las credenciales reales deben crearse solo en el hosting.

## 3. Archivos que no deben quedar publicos

Aunque pueden subirse temporalmente para migracion controlada, no deben quedar accesibles publicamente:

```text
public_html/database/
public_html/projects/catalogos/sql/
public_html/projects/rifas/database/
README*.md
*.sql
*.zip
*.bak
*.backup
*.env
config.php
```

Los `.htaccess` actuales bloquean varios de estos accesos, pero la recomendacion profesional es retirar `database/` y archivos SQL del area publica despues de ejecutar las migraciones.

## 4. .htaccess revisados

Archivos revisados:

```text
CREATECGROUP/.htaccess
CREATECGROUP/projects/catalogos/.htaccess
CREATECGROUP/projects/rifas/.htaccess
```

Protecciones presentes:

- `Options -Indexes`
- bloqueo de `config.php`
- bloqueo de `.env`
- bloqueo de `.sql`
- bloqueo de `.zip`
- bloqueo de README/documentacion
- bloqueo de carpetas internas de SQL/configuracion en modulos

## 5. Hallazgos a validar antes de subir

### Archivo de carga en rifas

Existe este archivo dentro del modulo copiado:

```text
CREATECGROUP/projects/rifas/public/assets/uploads/flyers/flyer-20260510003306-283791f7.png
```

Puede ser una imagen de prueba o una carga real. No se elimino.

Decision recomendada antes del deploy:

- Si es parte de una rifa real que debe conservarse, subirla.
- Si es contenido de prueba, limpiar manualmente antes de generar el paquete final.

### SQL dentro de modulos

Hay SQL en:

```text
CREATECGROUP/database/
CREATECGROUP/projects/catalogos/sql/
CREATECGROUP/projects/rifas/database/
```

Esto ayuda para migracion y trazabilidad, pero no debe quedar expuesto en produccion.

## 6. Orden recomendado para deploy controlado

1. Hacer backup de `public_html/` actual.
2. Hacer backup de las bases actuales.
3. Subir contenido de `CREATECGROUP/` a `public_html/`.
4. Crear manualmente `public_html/includes/config.php` basado en `includes/config.example.php`.
5. Crear manualmente cualquier `config.php` requerido por modulos, basado solo en plantillas `.example.php`.
6. Ejecutar SQL en una base staging primero.
7. Probar web principal.
8. Probar Super Admin.
9. Probar API de licencia.
10. Probar modulos.
11. Probar subdominios.
12. Retirar `database/` y SQL del area publica o moverlos fuera de `public_html/`.

## 7. Checklist antes de generar paquete final

- [ ] Confirmar si se sube o no `projects/rifas/public/assets/uploads/flyers/flyer-20260510003306-283791f7.png`.
- [ ] Confirmar que no se subira ningun backup viejo `.zip` desde la raiz del workspace.
- [ ] Confirmar que `includes/config.php` se creara solo en hosting.
- [ ] Confirmar usuario, base y permisos MySQL en cPanel.
- [ ] Confirmar que SSL esta activo para `createcpty.com`.
- [ ] Confirmar que AutoSSL esta activo para subdominios.
- [ ] Confirmar que `database/` se retirara o bloqueara despues de migrar.
- [ ] Confirmar que la prueba de login de Super Admin se hara con un usuario creado manualmente con `password_hash`.

## 8. Comando sugerido para revisar antes de subir

En PowerShell, desde `CREATEC_WORKSPACE/`:

```powershell
rg --files CREATECGROUP | rg "(config\.php|\.env|\.zip|\.bak|\.backup|config\.hosting\.createcpty\.php)$"
```

Resultado esperado:

```text
Sin resultados criticos, salvo plantillas .example.php si se ajusta el filtro.
```

## 9. Decision pendiente

Antes de empaquetar para hosting, confirmar si quieres:

1. Crear un paquete limpio `createc-public-html-ready.zip`.
2. Solo dejar la estructura preparada sin zip.
3. Crear primero un manifiesto exacto de archivos a subir y excluir.

