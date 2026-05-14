# Modulo SaaS: Catalogos

Este modulo fue copiado desde `catalogo_rodeo/catalogo_rodeo-main/hosting/`.

Entrada sugerida:

- Admin: `catalogos_admin/login.php`
- Vendedor: `catalogos_vendedor/index.php`
- Catalogos publicados: `catalogos/<slug>/`

Notas:

- Mantiene `catalogos_api/` interno para compatibilidad con rutas actuales.
- El Super Admin no se duplica aqui; vive en `CREATECGROUP/super_admin/`.
- No colocar credenciales reales en `catalogos_api/config.php` dentro del repositorio.
- Mover/proteger `sql/` despues de ejecutar migraciones en hosting.
