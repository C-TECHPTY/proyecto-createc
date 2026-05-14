# Subir RifaGrid a createcpty.com usando la base existente

Tu respaldo anterior indica que la base se llama:

```text
pandqgxl_rifa_panama
```

Ese archivo SQL no trae la contraseña MySQL del hosting. Esa contraseña se ve o se cambia desde cPanel en `MySQL Databases`.

## Opción recomendada

Usa la misma base `pandqgxl_rifa_panama`, pero importa las tablas nuevas de RifaGrid. Las tablas viejas pueden quedarse; la app nueva usa tablas nuevas como `raffles`, `raffle_numbers`, `reservations`, etc.

Orden de importación si la base no tiene las tablas nuevas:

```text
1. database/schema.sql
2. database/seed.sql
```

Si ya habías importado fases sueltas antes, importa solo lo que falte:

```text
database/phase4_push_subscriptions.sql
database/phase7_whatsapp.sql
database/phase8_design_settings.sql
```

## Configuración del hosting

En el hosting crea este archivo:

```text
config/config.php
```

Puedes usar como plantilla:

```text
config/config.hosting.createcpty.example.php
```

Copia su contenido y cambia:

```text
DB_USER
DB_PASS
```

por los datos reales de cPanel.

## URLs esperadas

Si subes el proyecto a `public_html/rifa`, las URLs serán:

```text
https://createcpty.com/rifa/public/index.php
https://createcpty.com/rifa/admin/login.php
```

## Importante

No reemplaces tu `config/config.php` local de XAMPP. Mantén uno distinto en el hosting.

XAMPP:

```text
APP_URL = http://localhost/rifa/public
DB_NAME = rifagrid
```

Hosting:

```text
APP_URL = https://createcpty.com/rifa/public
DB_NAME = pandqgxl_rifa_panama
```
