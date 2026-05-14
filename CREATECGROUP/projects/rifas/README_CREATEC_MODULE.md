# Modulo SaaS: Rifas

Este modulo fue copiado desde `sistema_rifa/`.

Entrada sugerida:

- Publico: `index.php` o `public/index.php`
- Admin: `admin/login.php`
- API: `api/`

Notas:

- `index.php` carga `public/index.php` para que el subdominio pueda apuntar a `projects/rifas/`.
- No se copio `pandqgxl_rifa_panama.sql`; permanece en el proyecto original para analisis/migracion legacy.
- No colocar `config/config.php` real dentro del repositorio.
- Mover/proteger `database/` despues de ejecutar migraciones en hosting.
