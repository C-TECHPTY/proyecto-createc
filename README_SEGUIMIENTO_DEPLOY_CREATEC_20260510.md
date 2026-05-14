# Seguimiento deploy CREATEC - 2026-05-10

Este reporte deja documentado el estado actual para continuar manana desde otro computador.

## Estado general

Se trabajo en staging, sin reemplazar la raiz productiva principal.

Ruta usada en hosting:

```text
/home/pandqgxl/createcpty.com/createc_staging/
```

URL staging web CREATEC:

```text
https://createcpty.com/createc_staging/
```

Subdominio creado para rifas:

```text
https://rifas.createcpty.com/
```

## Lo que ya funciona

- Web principal CREATEC en staging carga correctamente.
- Imagenes y logo de la web principal cargan correctamente.
- Modulo de rifas carga desde staging.
- Subdominio `rifas.createcpty.com` fue creado en cPanel.
- DNS ya resolvio para `rifas.createcpty.com`.
- SSL para `rifas.createcpty.com` quedo instalado.
- `https://rifas.createcpty.com/` cargo correctamente en ventana incognito.
- Admin de rifas carga.
- Login de admin de rifas funciona con un usuario personalizado creado en la tabla `admins`.

## Importante sobre seguridad

No guardar contrasenas reales en este repositorio ni en este reporte.

Se creo un archivo temporal en hosting para generar/admin insertar usuario:

```text
/home/pandqgxl/createcpty.com/createc_staging/projects/rifas/crear_admin.php
```

Accion pendiente critica:

```text
Borrar crear_admin.php si aun existe.
```

## Bases de datos

Base SaaS nueva creada/probada:

```text
pandqgxl_createc_saas
```

Base de rifa usada por el modulo actual:

```text
pandqgxl_rifa_panama
```

La rifa se probo conectando al entorno actual/original y funciono. No se debe hacer migracion destructiva.

## Configuracion de rifas en hosting

Archivo:

```text
/home/pandqgxl/createcpty.com/createc_staging/projects/rifas/config/config.php
```

Debe contener:

```php
'APP_URL' => 'https://rifas.createcpty.com/public',
```

Y los datos reales de MySQL solo deben vivir en ese `config.php` del hosting, no en Git ni en documentos.

## Subdominio de rifas

En cPanel > Domains:

```text
Domain: rifas.createcpty.com
Document Root: /home/pandqgxl/createcpty.com/createc_staging/projects/rifas
```

SSL:

```text
Instalado para rifas.createcpty.com y www.rifas.createcpty.com
Vence: 2026-11-26 segun cPanel
```

Pendiente:

```text
Activar Force HTTPS Redirect para rifas.createcpty.com si aun no esta activo.
```

Pruebas:

```text
https://rifas.createcpty.com/
https://rifas.createcpty.com/public/
https://rifas.createcpty.com/admin/login.php
```

## Ajustes realizados en codigo local

Se agrego:

```text
CREATECGROUP/super_admin/index.php
```

Objetivo:

```text
Redirigir /super_admin/ hacia login.php para evitar 403.
```

Se modifico:

```text
CREATECGROUP/projects/rifas/index.php
```

Objetivo:

```text
Redirigir /projects/rifas/ hacia public/ para que CSS y JS carguen correctamente.
```

Se modifico:

```text
CREATECGROUP/projects/rifas/admin/login.php
```

Objetivo:

```text
Eliminar el texto visible del usuario demo.
```

## Archivos/carpetas que no deben quedar publicos

Si aun existen dentro de:

```text
/home/pandqgxl/createcpty.com/createc_staging/
```

retirar o mover fuera del area publica:

```text
createc-public-html-ready-20260510-182644.zip
DEPLOY_PACKAGE_README.txt
MIGRATION_SQL_DELETE_AFTER_IMPORT/
```

La carpeta SQL se puede mover a:

```text
/home/pandqgxl/MIGRATION_SQL_DELETE_AFTER_IMPORT/
```

## Pendientes para manana

1. Confirmar que `crear_admin.php` fue borrado.
2. Confirmar que `Force HTTPS Redirect` esta activo en `rifas.createcpty.com`.
3. Probar `http://rifas.createcpty.com/` y verificar que redirige a HTTPS.
4. Probar login admin:

```text
https://rifas.createcpty.com/admin/login.php
```

5. Revisar que no queden ZIP, SQL o carpetas de migracion publicas dentro de staging.
6. Decidir siguiente paso:

```text
A) crear catalogos.createcpty.com
B) terminar Super Admin SaaS
C) mover web CREATEC de staging a la raiz createcpty.com
```

## Recomendacion

Antes de mover la web principal a raiz, dejar `rifas.createcpty.com` estable al menos una noche y validar:

- login admin
- visualizacion publica
- SSL
- redireccion HTTPS
- carga de imagenes/assets
- que la rifa actual no haya sido afectada

## Correccion local posterior: Super Admin y PWA rifas

Se ajusto la estructura local para evitar que la PWA de rifas responda fuera de su propio modulo si el service worker queda registrado con un scope amplio.

Archivos modificados:

```text
CREATECGROUP/projects/rifas/service-worker.js
CREATECGROUP/projects/rifas/public/service-worker.js
```

Objetivo:

```text
Evitar que rutas como /createc_staging/super_admin/login.php muestren la pantalla offline de RifaGrid.
```

Se agrego tambien una ruta puente para entrar al Super Admin desde el contexto de catalogos:

```text
CREATECGROUP/projects/catalogos/super_admin/index.php
CREATECGROUP/projects/catalogos/super_admin/login.php
```

Ambas redirigen a:

```text
../../../super_admin/login.php
```

Despues de subir estos cambios al hosting, probar:

```text
https://createcpty.com/createc_staging/super_admin/login.php
https://createcpty.com/createc_staging/projects/catalogos/super_admin/login.php
```

Si el navegador aun muestra `RifaGridGIBEL Rifas - Sin conexion`, borrar datos del sitio/service workers para `createcpty.com` y `rifas.createcpty.com`, o probar desde una ventana incognito nueva.
