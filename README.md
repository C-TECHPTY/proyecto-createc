# Proyecto CREATEC

Repositorio principal para la estructura CREATEC SaaS.

## Contenido

- `CREATECGROUP/`: web principal CREATEC, Super Admin, APIs centrales y modulos SaaS.
- `database/`: inventario y propuesta de migracion/unificacion de base de datos.
- `README_*.md`: documentacion de analisis, deploy, seguridad, subdominios y seguimiento.

## Nota de seguridad

Las credenciales reales no deben guardarse aqui. Los archivos `config.php`, `.env`, dumps productivos y paquetes `.zip` quedan excluidos del repositorio.

En hosting, los archivos reales de configuracion deben crearse manualmente a partir de las plantillas `.example.php`.
