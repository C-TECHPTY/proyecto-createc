# Subdominios CREATEC en cPanel / Namecheap

Fase 6: documentacion operativa para preparar subdominios SaaS sin modificar produccion directamente.

Dominio principal:

```text
createcpty.com
```

Estructura objetivo en hosting:

```text
public_html/
├── index.php
├── .htaccess
├── assets/
├── super_admin/
├── catalogos_api/
├── includes/
└── projects/
    ├── catalogos/
    ├── rifas/
    ├── barber/
    ├── turismo/
    ├── spa/
    └── ecommerce/
```

## 1. Mapa de subdominios

| Subdominio | Carpeta / Document Root en cPanel | Estado |
| --- | --- | --- |
| `catalogos.createcpty.com` | `public_html/projects/catalogos/` | Modulo copiado |
| `rifas.createcpty.com` | `public_html/projects/rifas/` | Modulo copiado |
| `barber.createcpty.com` | `public_html/projects/barber/` | Placeholder |
| `turismo.createcpty.com` | `public_html/projects/turismo/` | Placeholder |
| `spa.createcpty.com` | `public_html/projects/spa/` | Placeholder |

## 2. Como crear un subdominio en cPanel

1. Entrar a cPanel desde Namecheap.
2. Abrir **Domains** o **Subdomains**, segun la version de cPanel.
3. Crear el subdominio, por ejemplo:

```text
catalogos
```

4. Seleccionar el dominio:

```text
createcpty.com
```

5. Asignar document root exacto:

```text
public_html/projects/catalogos/
```

6. Guardar.
7. Repetir el proceso para cada subdominio.

## 3. Carpetas exactas por subdominio

### Catalogos

```text
Subdominio: catalogos.createcpty.com
Document root: public_html/projects/catalogos/
```

URLs de prueba:

```text
https://catalogos.createcpty.com/
https://catalogos.createcpty.com/catalogos_admin/login.php
https://catalogos.createcpty.com/catalogos_vendedor/index.php
```

### Rifas

```text
Subdominio: rifas.createcpty.com
Document root: public_html/projects/rifas/
```

URLs de prueba:

```text
https://rifas.createcpty.com/
https://rifas.createcpty.com/public/index.php
https://rifas.createcpty.com/admin/login.php
```

### Barber

```text
Subdominio: barber.createcpty.com
Document root: public_html/projects/barber/
```

### Turismo

```text
Subdominio: turismo.createcpty.com
Document root: public_html/projects/turismo/
```

### Spa

```text
Subdominio: spa.createcpty.com
Document root: public_html/projects/spa/
```

## 4. Namecheap DNS

Si el dominio usa los nameservers del hosting/cPanel, normalmente cPanel crea los registros DNS automaticamente.

Si el DNS se administra manualmente en Namecheap:

1. Entrar a Namecheap.
2. Ir a **Domain List**.
3. Seleccionar `createcpty.com`.
4. Abrir **Advanced DNS**.
5. Crear un registro por subdominio:

```text
Type: A Record
Host: catalogos
Value: IP_DEL_HOSTING
TTL: Automatic
```

Repetir para:

```text
rifas
barber
turismo
spa
```

Alternativa si el hosting recomienda CNAME:

```text
Type: CNAME
Host: catalogos
Value: createcpty.com
TTL: Automatic
```

Usar A Record si Namecheap/cPanel lo recomienda para el servidor compartido.

## 5. Como activar SSL

En cPanel:

1. Abrir **SSL/TLS Status**.
2. Buscar los subdominios:

```text
catalogos.createcpty.com
rifas.createcpty.com
barber.createcpty.com
turismo.createcpty.com
spa.createcpty.com
```

3. Marcar los subdominios.
4. Presionar **Run AutoSSL**.
5. Esperar a que el estado aparezca como activo.

Si AutoSSL no activa:

- Verificar que el DNS ya apunte al hosting.
- Esperar propagacion.
- Confirmar que el document root existe.
- Volver a ejecutar AutoSSL.

## 6. Como probar

### DNS

Abrir en navegador:

```text
https://catalogos.createcpty.com/
https://rifas.createcpty.com/
```

Si no carga:

- Probar con `http://` temporalmente.
- Revisar si el DNS ya propago.
- Revisar document root en cPanel.

### SSL

Debe cargar con candado HTTPS:

```text
https://catalogos.createcpty.com/
```

Si aparece error de certificado:

- Ejecutar AutoSSL.
- Confirmar que el subdominio apunta al hosting correcto.

### Modulo catalogos

Probar:

```text
https://catalogos.createcpty.com/catalogos_admin/login.php
```

Debe mostrar el login del sistema de catalogos.

### Modulo rifas

Probar:

```text
https://rifas.createcpty.com/
https://rifas.createcpty.com/admin/login.php
```

Debe mostrar la vista publica de rifas y el login admin.

## 7. Registrar subdominio en el Super Admin

Entrar a:

```text
https://createcpty.com/super_admin/
```

Luego:

1. Crear o editar una empresa.
2. Ir a **Dominios**.
3. Seleccionar empresa.
4. Registrar dominio:

```text
catalogos.createcpty.com
```

5. Tipo:

```text
subdomain
```

6. Estado:

```text
active
```

7. SSL:

```text
active
```

8. Marcar como primario si aplica.

## 8. Relacionar subdominio con empresa

En el Super Admin:

1. Ir a **Instancias**.
2. Seleccionar empresa.
3. Seleccionar modulo.
4. Definir clave de instancia.

Ejemplo:

```text
Empresa: Cliente 1
Modulo: Catalogos digitales B2B
Clave instancia: cliente1-catalogos
Ruta proyecto: projects/catalogos/
Subdominio: catalogos.createcpty.com
Base/instancia DB: createc_saas
Estado: active
```

Para rifas:

```text
Empresa: Cliente 1
Modulo: Sistema de rifas
Clave instancia: cliente1-rifas
Ruta proyecto: projects/rifas/
Subdominio: rifas.createcpty.com
Base/instancia DB: createc_saas
Estado: active
```

## 9. Reglas de seguridad

- No dejar `database/` ni `sql/` publicos despues de ejecutar migraciones.
- No subir `config.php` reales a Git.
- No subir `.env`.
- No exponer dumps `.sql`.
- No dejar listado de directorios activo.
- Mantener `.htaccess` en cada modulo.
- Activar HTTPS antes de entregar a clientes.
- Probar logins despues de mover carpetas.

## 10. Checklist por subdominio

Para cada subdominio:

```text
[ ] Carpeta existe en public_html/projects/
[ ] Subdominio creado en cPanel
[ ] Document root correcto
[ ] DNS apunta al hosting
[ ] AutoSSL activo
[ ] URL carga por HTTPS
[ ] Login o pantalla publica funciona
[ ] Empresa registrada en Super Admin
[ ] Dominio registrado en Super Admin
[ ] Instancia relacionada con modulo
[ ] Config real creado solo en hosting
[ ] SQL/database protegidos o retirados de public_html
```

## 11. Orden recomendado

1. Subir estructura principal a `public_html/`.
2. Confirmar que `https://createcpty.com/` carga.
3. Confirmar que `https://createcpty.com/super_admin/` carga.
4. Crear base y ejecutar migraciones necesarias en staging/produccion con respaldo.
5. Crear empresas, modulos e instancias.
6. Crear subdominios en cPanel.
7. Activar AutoSSL.
8. Probar cada subdominio.
9. Retirar/proteger carpetas SQL o database publicas.
