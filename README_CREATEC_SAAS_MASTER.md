# CREATEC SaaS Master

Documento maestro de arquitectura, despliegue y siguientes fases para CREATEC como empresa SaaS.

Dominio principal:

```text
https://createcpty.com/
```

Objetivo:

Unificar la web principal, Super Admin, API de licencias, catalogos B2B, sistema de rifas y futuros modulos SaaS bajo una estructura escalable en cPanel/Namecheap, sin romper los proyectos existentes.

## 1. Vision general

CREATEC queda organizado como plataforma SaaS modular:

```text
CREATEC Web Principal
↓
Super Admin central
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

La web principal vende servicios y dirige a cotizacion. El Super Admin controla la operacion SaaS. Los proyectos viven como modulos dentro de `projects/`.

## 2. Estructura local actual

```text
CREATEC_WORKSPACE/
├── CREATECGROUP/
│   ├── index.php
│   ├── index.html
│   ├── .htaccess
│   ├── assets/
│   ├── super_admin/
│   ├── catalogos_api/
│   ├── includes/
│   ├── projects/
│   │   ├── catalogos/
│   │   ├── rifas/
│   │   ├── barber/
│   │   ├── turismo/
│   │   ├── spa/
│   │   └── ecommerce/
│   └── database/
├── catalogo_rodeo/
├── sistema_rifa/
├── database/
└── basedatos/
```

Nota: `basedatos/` existe pero esta vacia; la carpeta esperada `databases/` no existe.

## 3. Estructura objetivo en hosting

```text
public_html/
├── index.php
├── index.html
├── .htaccess
├── assets/
├── super_admin/
├── catalogos_api/
├── includes/
├── projects/
│   ├── catalogos/
│   ├── rifas/
│   ├── barber/
│   ├── turismo/
│   ├── spa/
│   └── ecommerce/
└── database/   temporal, retirar despues de migrar
```

URLs objetivo:

```text
https://createcpty.com/                  Web principal CREATEC
https://createcpty.com/super_admin/      Super Admin central SaaS
https://createcpty.com/catalogos_api/    API central de licencias
```

Subdominios futuros:

```text
https://catalogos.createcpty.com/
https://rifas.createcpty.com/
https://barber.createcpty.com/
https://turismo.createcpty.com/
https://spa.createcpty.com/
https://cliente1.createcpty.com/
```

## 4. Web principal

Proyecto base:

```text
CREATECGROUP/
```

Entradas:

```text
index.php
index.html
.htaccess
assets/
```

La web principal incluye:

- logo CREATEC
- favicon
- paleta azul corporativa
- servicios
- proyectos
- SaaS
- fotografia comercial
- contacto
- llamadas a cotizar

El archivo `index.php` carga `index.html` para permitir entrada limpia desde:

```text
https://createcpty.com/
```

## 5. Super Admin

Ruta:

```text
CREATECGROUP/super_admin/
```

Funciones preparadas:

- dashboard
- empresas
- dominios
- planes SaaS
- suscripciones
- licencias
- modulos
- instancias/proyectos asignados
- publicaciones SaaS
- actividad

Tablas principales:

```text
sa_admin_users
sa_companies
sa_plans
sa_subscriptions
sa_licenses
sa_company_domains
sa_modules
sa_company_modules
sa_project_instances
sa_activity_logs
saas_publish_logs
```

Config central:

```text
CREATECGROUP/includes/config.example.php
CREATECGROUP/includes/config.php   solo en hosting
```

No se debe subir `config.php` real a Git.

## 6. API de licencias

Ruta:

```text
CREATECGROUP/catalogos_api/
```

Endpoint:

```text
catalogos_api/validate_license.php
```

Uso:

- validar `license_key`
- devolver empresa
- devolver estado
- confirmar si puede publicar
- registrar contexto SaaS

Debe probarse solo por HTTPS.

## 7. Modulos SaaS

### Catalogos

Ruta:

```text
CREATECGROUP/projects/catalogos/
```

Origen:

```text
catalogo_rodeo/catalogo_rodeo-main/hosting/
```

Entradas:

```text
catalogos_admin/login.php
catalogos_vendedor/index.php
catalogos/<slug>/
catalogos_api/
```

Estado:

- copiado como modulo SaaS
- no se movio ni borro el proyecto original
- no se copio `super_admin/` dentro del modulo
- no se copio `hosting.zip`

### Rifas

Ruta:

```text
CREATECGROUP/projects/rifas/
```

Origen:

```text
sistema_rifa/
```

Entradas:

```text
index.php
public/index.php
admin/login.php
api/
```

Estado:

- copiado como modulo SaaS
- no se movio ni borro el proyecto original
- se retiro config no-example de la copia
- no se copio dump legacy `pandqgxl_rifa_panama.sql`

### Modulos futuros

```text
projects/barber/
projects/turismo/
projects/spa/
projects/ecommerce/
```

Actualmente son placeholders.

## 8. Base de datos

Base central propuesta:

```text
createc_saas
```

Archivo principal:

```text
database/createc_saas_schema_proposed.sql
```

Inventario:

```text
database/table_inventory_createc.md
```

Plan de migracion:

```text
database/migration_plan.md
```

Prefijos:

```text
sa_      Super Admin SaaS
web_     Web CREATEC
cat_     Catalogos
rifa_    Rifas
barber_  Barberias futuro
tour_    Turismo futuro
store_   Ecommerce futuro
```

Regla multiempresa:

Las tablas de negocio deben tener `company_id` directo o heredado por relacion clara.

Ejemplos directos:

```text
cat_catalogs.company_id
cat_orders.company_id
cat_products.company_id
rifa_raffles.company_id
rifa_reservations.company_id
rifa_payments.company_id
```

## 9. Flujo de empresas

1. Crear empresa en Super Admin.
2. Asignar datos:
   - nombre comercial
   - razon social
   - slug
   - email
   - telefono
   - dominio
   - subdominio
   - estado
3. Asignar plan.
4. Crear suscripcion.
5. Crear licencia.
6. Activar modulos.
7. Crear instancia de proyecto.
8. Relacionar dominio/subdominio.

## 10. Flujo de licencias

1. Super Admin crea licencia para empresa.
2. Licencia se guarda en `sa_licenses`.
3. Cliente o app envia `license_key` a:

```text
catalogos_api/validate_license.php
```

4. API valida:
   - licencia existe
   - empresa activa
   - licencia activa
   - fecha de vencimiento
   - modulo permitido
5. API devuelve estado.
6. Se registra intento/publicacion si aplica.

Mejora futura:

- HMAC por request.
- timestamp anti-replay.
- rate limit.
- rotacion de licencias.

## 11. Flujo de suscripciones

1. Crear plan en `sa_plans`.
2. Crear suscripcion en `sa_subscriptions`.
3. Asociar empresa.
4. Definir fechas.
5. Definir estado:

```text
active
pending
expired
cancelled
```

6. Renovar o suspender segun pago.
7. Licencia debe respetar vencimiento.

## 12. Subdominios

Documentacion:

```text
README_SUBDOMINIOS_CPANEL.md
```

Mapa:

```text
catalogos.createcpty.com -> public_html/projects/catalogos/
rifas.createcpty.com     -> public_html/projects/rifas/
barber.createcpty.com    -> public_html/projects/barber/
turismo.createcpty.com   -> public_html/projects/turismo/
spa.createcpty.com       -> public_html/projects/spa/
```

Despues de crear subdominio:

1. Activar AutoSSL.
2. Probar URL.
3. Registrar dominio en Super Admin.
4. Registrar instancia en Super Admin.

## 13. Seguridad

Documentacion:

```text
README_SECURITY_CREATEC.md
```

Principios:

- no subir configs reales
- no subir `.env`
- no subir dumps productivos
- no dejar `database/` ni `sql/` publicos
- usar HTTPS
- CSRF en formularios
- `password_hash`
- PDO/prepared statements
- roles y permisos
- logs de auditoria

Riesgos actuales conocidos:

- `projects/catalogos/sql/` existe para preparacion local.
- `projects/rifas/database/` existe para preparacion local.
- Deben retirarse o moverse fuera de `public_html` despues de migrar.

## 14. Deploy

Documentacion:

```text
README_DEPLOY_CREATEC.md
```

Orden recomendado:

1. Backup de hosting actual.
2. Backup de bases actuales.
3. Subir `CREATECGROUP/` a `public_html/`.
4. Crear `includes/config.php` en hosting.
5. Crear base `createc_saas`.
6. Ejecutar SQL Super Admin.
7. Crear primer Super Admin.
8. Probar web.
9. Probar Super Admin.
10. Probar API.
11. Probar proyectos.
12. Crear subdominios.
13. Activar AutoSSL.
14. Registrar dominios e instancias.
15. Retirar/proteger SQL y database.

## 15. Pruebas

### Web principal

```text
https://createcpty.com/
```

Validar:

- logo
- favicon
- servicios
- proyectos
- SaaS
- fotografia comercial
- contacto

### Super Admin

```text
https://createcpty.com/super_admin/login.php
```

Validar:

- login
- dashboard
- empresas
- planes
- suscripciones
- licencias
- dominios
- modulos
- instancias

### API de licencias

```text
https://createcpty.com/catalogos_api/validate_license.php
```

Validar:

- respuesta JSON
- falta `license_key`
- licencia valida
- licencia vencida
- empresa suspendida

### Catalogos

```text
https://createcpty.com/projects/catalogos/catalogos_admin/login.php
https://catalogos.createcpty.com/catalogos_admin/login.php
```

Validar:

- login
- catalogos
- pedidos
- vendedores
- links

### Rifas

```text
https://createcpty.com/projects/rifas/
https://createcpty.com/projects/rifas/admin/login.php
https://rifas.createcpty.com/
```

Validar:

- vista publica
- login admin
- crear rifa
- reservar numero
- subir comprobante
- confirmar pago
- ganador

## 16. Documentos creados

Raiz del workspace:

```text
README_ANALISIS_CREATEC.md
README_DATABASE_UNIFICATION.md
README_SUBDOMINIOS_CPANEL.md
README_SECURITY_CREATEC.md
README_DEPLOY_CREATEC.md
README_CREATEC_SAAS_MASTER.md
```

Carpeta `database/`:

```text
database/createc_saas_schema_proposed.sql
database/migration_plan.md
database/table_inventory_createc.md
```

Dentro de `CREATECGROUP/`:

```text
README_SAAS_STRUCTURE_CREATEC.md
projects/README_PROJECTS_CREATEC.md
projects/catalogos/README_CREATEC_MODULE.md
projects/rifas/README_CREATEC_MODULE.md
```

## 17. Que no se ha hecho todavia

- No se ejecutaron migraciones en produccion.
- No se importaron bases.
- No se fusionaron datos.
- No se agrego `company_id` a tablas productivas.
- No se tocaron configs reales.
- No se tocaron SMTP, FTP ni API keys reales.
- No se borraron datos.
- No se movieron destructivamente proyectos originales.
- No se probaron pantallas con PHP local porque PHP no esta disponible en el entorno.

## 18. Siguientes fases recomendadas

### Staging

1. Subir a subcarpeta o subdominio de staging.
2. Crear base `createc_saas_staging`.
3. Crear `includes/config.php` staging.
4. Ejecutar SQL Super Admin.
5. Crear Super Admin.
6. Probar todo el flujo.

### Hardening

1. Rate limit de login.
2. Roles/permisos finos.
3. HMAC para API de licencia.
4. Bloqueo PHP en uploads.
5. Retirar SQL/database del webroot.
6. CSP y HSTS cuando todos los subdominios tengan HTTPS.

### Migracion de datos

1. Confirmar modelo oficial de rifas.
2. Exportar bases actuales.
3. Crear scripts idempotentes.
4. Probar migracion en staging.
5. Validar conteos.
6. Validar dinero/pedidos/reservas.
7. Hacer piloto con una empresa.

### Codigo multiempresa

1. Adaptar catalogos a `company_id`.
2. Adaptar rifas a `company_id`.
3. Resolver empresa por dominio/subdominio.
4. Agregar filtros por empresa.
5. Probar aislamiento de datos.

## 19. Reglas permanentes

- No hacer `DROP TABLE`.
- No borrar datos.
- No sobrescribir `config.php` productivo.
- No tocar SMTP real.
- No tocar FTP real.
- No tocar API keys reales.
- No borrar usuarios.
- No borrar pedidos.
- No borrar rifas.
- No borrar catalogos.
- No romper login.
- No romper rutas existentes.
- Trabajar por fases.
- Probar en staging antes de produccion.
