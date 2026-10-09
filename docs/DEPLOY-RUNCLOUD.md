# Despliegue en RunCloud

Guía para el primer despliegue y las actualizaciones. Asume un servidor RunCloud con Nginx + PHP-FPM
y una base PostgreSQL (en el mismo servidor o gestionada).

## 1. Servidor

- PHP 8.4 (o 8.5) con extensiones `pdo_pgsql`, `pgsql`, `gd`, `mbstring`, `intl`, `bcmath`, `zip`.
- Node 24 solo para compilar el front (`npm ci && npm run build`); no corre en producción.
- Composer 2.
- Web Application en RunCloud con **Web Application Root** apuntando a `public/`, tipo Laravel.

## 2. Base de datos

```sql
CREATE ROLE catalogo_app WITH LOGIN PASSWORD '<secreta>';
CREATE DATABASE catalogo_app OWNER catalogo_app;
```

## 3. Código y entorno

```bash
git clone git@github.com:Imderf/catalogos-cencosud.git
cd catalogo-app
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Variables que deben cambiar en `.env` de producción:

| Variable | Valor |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://catalogos.tu-dominio.com` (con https, sin barra final) |
| `DB_*` | credenciales del paso 2, `DB_PORT=5432` |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `database` |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION` | SMTP real (por ejemplo el de Ogilvy o Amazon SES) |
| `MAIL_FROM_ADDRESS` | un remitente autorizado en ese SMTP |

Luego:

```bash
php artisan migrate --force
php artisan app:make-admin admin@ogilvy.example "Nombre del administrador"
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`app:make-admin` siembra roles, sectores, marcas y tiempos, crea el administrador y muestra su contraseña
inicial una sola vez. Ese administrador crea a todos los demás desde **Usuarios**.

No corras `db:seed` en producción: carga los usuarios de demo con contraseña `password`.

## 4. Procesos de fondo (obligatorios)

**Cron** (RunCloud → Cron Jobs), cada minuto, como el usuario de la app:

```bash
cd /home/<usuario>/webapps/catalogo-app && php artisan schedule:run >> /dev/null 2>&1
```

Ejecuta `review:check-deadlines`, que avisa plazos urgentes y vencidos.

**Supervisor** (RunCloud → Supervisor Jobs), un proceso, autorestart:

```bash
php /home/<usuario>/webapps/catalogo-app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Envía los correos y genera las copias de pantalla y miniaturas de cada página. Sin él, la bandeja funciona pero no llega ningún correo y las imágenes se sirven en tamaño original.

## 5. Almacenamiento

Las imágenes del arte viven en `storage/app/private/catalogos/` y se sirven solo con sesión a través de
`/imagenes/...`. No hay `storage:link` ni URLs públicas. Incluye `storage/` en los respaldos del servidor
junto con la base de datos.

Tamaño máximo de imagen: 15 MB por archivo. Ajusta en Nginx y PHP-FPM:
`client_max_body_size 64M;`, `upload_max_filesize = 32M`, `post_max_size = 64M`.

## 6. Actualizaciones

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

`queue:restart` hace que Supervisor levante el worker con el código nuevo.

## 7. Verificación después de desplegar

1. `https://.../login` carga y el administrador entra.
2. Crear un usuario de prueba y comprobar que llega el correo de bienvenida.
3. Abrir una página de catálogo: la imagen carga (ruta `/imagenes/...`, 200 con sesión, 302 sin ella).
4. `php artisan schedule:list` muestra `review:check-deadlines` cada minuto.
5. `php artisan queue:monitor database:default` sin trabajos atascados.

## 8. Monitoreo

- **Errores**: crea un proyecto en Sentry y pon su DSN en `SENTRY_LARAVEL_DSN`. Con la variable vacía
  no se envía nada. `SENTRY_TRACES_SAMPLE_RATE=0` deja solo errores, sin trazas de rendimiento.
- **Disponibilidad**: apunta un monitor externo (UptimeRobot, Better Stack o el de RunCloud) a
  `https://.../salud` cada 5 minutos. Responde 200 mientras la base contesta, el cron del scheduler
  reportó en los últimos 10 minutos y el worker de colas en los últimos 15. Si cualquiera falla
  responde 503 con el detalle en JSON, y el monitor avisa. Ese endpoint es público a propósito y no
  expone datos.
- **Logs**: `storage/logs/laravel.log` con `LOG_LEVEL=warning` en producción.

## 9. Tiempo real (opcional)

La app refresca sola cada minuto. Con Laravel Reverb el cambio se ve al instante en todos los
navegadores abiertos. Para activarlo:

1. En `.env`: `BROADCAST_CONNECTION=reverb`, y valores propios en `REVERB_APP_ID`, `REVERB_APP_KEY`,
   `REVERB_APP_SECRET`. Para el navegador: `REVERB_HOST=catalogos.tu-dominio.com`, `REVERB_PORT=443`,
   `REVERB_SCHEME=https`. Vuelve a compilar el front (`npm run build`) porque las `VITE_REVERB_*` se
   incrustan en el bundle.
2. **Supervisor**: un proceso más, `php artisan reverb:start --host=127.0.0.1 --port=8080`.
3. **Nginx** (RunCloud → Nginx config, bloque `location`):

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_pass http://127.0.0.1:8080;
}
location /apps {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_pass http://127.0.0.1:8080;
}
```

Si Reverb no está disponible, el navegador no se conecta y la app sigue refrescando cada minuto.
Para dejarlo apagado: `BROADCAST_CONNECTION=log` y `REVERB_APP_KEY` vacío.

## 10. Cómo quedó el primer despliegue (23 sep 2026)

Servidor RunCloud `<IP_DEL_SERVIDOR>` (Ubuntu 24.04, 1 CPU, 2 GB), usuario del sistema `catalogos`, web app
"Catalogos" en `/home/catalogos/webapps/Catalogos` con root en `public/`, PHP 8.4, dominio
`catalogos.example.com` detrás de Cloudflare (DNS en Cloudflare, proxy activo).

Diferencias con la guía de arriba, por cómo es este servidor:

- **PostgreSQL 16** se instaló con `apt` (no desde el panel, que solo ofrece MariaDB). Base y rol
  `catalogo_app`; la contraseña vive en `/home/catalogos/.catalogo_db.env` (modo 600) y en el `.env`.
- **Los PHP de RunCloud no traen la extensión de Postgres.** Se compilaron `pgsql` y `pdo_pgsql` desde el
  código fuente de la misma versión (8.4.25) con el `phpize` de `/RunCloud/Packages/php84rc/bin` y se
  habilitaron en `/etc/php84rc/conf.d/60-pgsql.ini`. **Si RunCloud actualiza PHP 8.4, hay que
  recompilarlas** (mismo procedimiento: bajar `php-<versión>.tar.gz`, `phpize`, `configure
  --with-php-config=… --with-pgsql|--with-pdo-pgsql`, `make`, `make install`, reiniciar `php84rc-fpm`).
  `/salud` avisará porque la base dejará de responder.
- El código se clona por SSH con una *deploy key* de solo lectura (`~/.ssh/github_deploy`) y el frontend
  se compila en la máquina de desarrollo y se sube con `rsync` a `public/build` (el servidor tiene poca
  memoria para Vite). Al compilar para producción, vaciar `VITE_REVERB_*` mientras no haya Reverb.
- `/home/catalogos/logs` es de root: el cron y la cola escriben en `storage/logs/schedule.log` y
  `storage/logs/queue.log`. El cron está en `/etc/cron.d/catalogos-schedule`; la cola en
  `/etc/supervisor/conf.d/catalogos-queue.conf` (`supervisorctl status catalogos-queue`).
- Cloudflare llega al origen por http y nginx reescribe `X-Forwarded-Proto`, así que la app fuerza
  https en las URL generadas cuando `APP_URL` es https (`AppServiceProvider`) y confía en el proxy
  (`bootstrap/app.php`). Pendiente recomendado: certificado Let's Encrypt en el panel y Cloudflare en
  modo SSL "Full (strict)".
- Pendientes: SMTP real (hoy `MAIL_MAILER=log`) y Reverb (hoy `BROADCAST_CONNECTION=log`, la app
  recarga cada minuto).

Actualizar después de cada cambio en `main`:

```bash
# en la máquina de desarrollo
VITE_REVERB_APP_KEY= VITE_REVERB_HOST= npm run build && rsync -az --delete public/build/ catalogos@<IP_DEL_SERVIDOR>:~/webapps/Catalogos/public/build/
# en el servidor
ssh catalogos@<IP_DEL_SERVIDOR> 'cd ~/webapps/Catalogos && git pull -q origin main && P=/RunCloud/Packages/php84rc/bin/php && $P /usr/sbin/composer install --no-dev --optimize-autoloader -q && $P artisan migrate --force && $P artisan config:cache -q && $P artisan route:cache -q && $P artisan view:cache -q && sudo supervisorctl restart catalogos-queue'
```
