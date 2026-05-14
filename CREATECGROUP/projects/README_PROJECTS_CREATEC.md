# CREATEC SaaS Projects

Fase 5: estructura de proyectos preparada por copia, sin mover ni borrar los proyectos originales.

## Estructura

```text
projects/
├── catalogos/
├── rifas/
├── barber/
├── turismo/
├── spa/
└── ecommerce/
```

## Modulos copiados

### catalogos

Origen:

```text
catalogo_rodeo/catalogo_rodeo-main/hosting/
```

Destino:

```text
CREATECGROUP/projects/catalogos/
```

Se copio como modulo web autocontenido para preservar rutas internas entre:

- `catalogos_admin/`
- `catalogos_vendedor/`
- `catalogos_api/`
- `catalogos/`
- `assets/`
- `includes/`
- `sql/`

No se copio `hosting.zip` ni `super_admin/` porque el Super Admin central ya vive en `CREATECGROUP/super_admin/`.

### rifas

Origen:

```text
sistema_rifa/
```

Destino:

```text
CREATECGROUP/projects/rifas/
```

Se copiaron carpetas runtime/documentacion:

- `admin/`
- `api/`
- `config/`
- `database/`
- `public/`
- `storage/`
- `tools/`
- documentacion principal

No se copio `.git`, `.rnd`, `tree_elements.json`, `usuario database.txt` ni el dump legacy `pandqgxl_rifa_panama.sql` para evitar arrastrar material sensible/no runtime a una futura carpeta publica.

Tambien se retiro de la copia modular cualquier archivo de configuracion no-example detectado, incluyendo `config/config.hosting.createcpty.php`. En el modulo solo deben quedar plantillas como `config.example.php` o `config.hosting.createcpty.example.php`.

## Modulos futuros

Las carpetas `barber/`, `turismo/`, `spa/` y `ecommerce/` quedan como placeholders para siguientes fases.

## Produccion

Antes de publicar:

- Crear `config.php` solo en hosting cuando aplique.
- Mantener `database/` y `sql/` fuera del acceso publico despues de migrar.
- Revisar document root de cada subdominio.
- Probar cada modulo en staging.
