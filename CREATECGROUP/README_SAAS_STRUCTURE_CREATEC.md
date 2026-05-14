# CREATEC SaaS - Estructura central

Fase: 4  
Estado: preparada sin mover proyectos ni tocar bases productivas.

## Flujo central

```text
CREATEC principal
↓
Super Admin
↓
Empresas / Clientes
↓
Planes / Suscripciones
↓
Licencias
↓
Modulos SaaS
↓
Instancias de proyecto
↓
Dominios / Subdominios
```

## Tablas usadas

- `sa_companies`: empresas/clientes.
- `sa_plans`: limites comerciales por plan.
- `sa_subscriptions`: ciclo y estado de suscripcion.
- `sa_licenses`: licencia por empresa/modulo.
- `sa_company_domains`: dominios y subdominios.
- `sa_modules`: catalogos, rifas, barber, turismo, spa, ecommerce.
- `sa_company_modules`: modulos activos por empresa.
- `sa_project_instances`: instancia asignada a una empresa.

## Migracion agregada

Archivo:

```text
database/20260510_createc_saas_core_structure.sql
```

Ejecutar despues de:

```text
database/20260505_super_admin_base.sql
database/20260508_super_admin_connect_companies.sql
```

## Datos esperados por empresa

Cada empresa debe poder tener:

- nombre comercial
- razon social
- slug
- email
- telefono
- dominio
- subdominio
- plan
- estado
- fecha de vencimiento
- proyecto asignado
- modulo activo
- base de datos o instancia asignada

## Pantallas agregadas al Super Admin

- `super_admin/modules.php`: administra catalogos/rifas/futuros modulos.
- `super_admin/project_instances.php`: asigna una instancia/proyecto a una empresa.

## Reglas importantes

- No mover proyectos todavia.
- No fusionar bases todavia.
- No agregar `company_id` a tablas operativas sin adaptar codigo.
- No ejecutar SQL en produccion sin respaldo.
- No dejar `database/` publico despues del despliegue real.
