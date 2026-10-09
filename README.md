# Revisión de Catálogos — Ogilvy × Cencosud

Plataforma para revisar los catálogos impresos de Jumbo y Metro (octavillas, cuartillas y volantes) antes de imprenta. Ogilvy sube las páginas del arte final, el cliente comenta marcando regiones sobre la página misma, los ajustes bajan por la cadena hasta Ogilvy con un cronómetro en minutos laborales y la aprobación sube por Marketing, Planificación y Comercial. Incluye además una gestión de proyectos interna de Ogilvy donde cada catálogo es una tarjeta que avanza sola según la revisión.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | PHP 8.4, Laravel 12, Inertia 2, spatie/laravel-permission, Intervention Image, Laravel Reverb, Ziggy, Sentry (opcional) |
| Frontend | React 19, TypeScript, Tailwind CSS v4, shadcn/ui (Radix), lucide-react, dnd-kit, Laravel Echo, sonner |
| Base de datos | PostgreSQL |
| Correo local | Mailpit (SMTP 1025, interfaz 8025) |
| Pruebas | Pest, Playwright, Pint, ESLint, Prettier, `tsc` |
| CI | GitHub Actions: PHP 8.4 + Node 24 + Postgres |

Toda la interfaz, los mensajes y las rutas están en español. Zona horaria `America/Bogota`.

## Puesta en marcha

```bash
brew install postgresql@17 mailpit && brew services start postgresql@17 && brew services start mailpit
createdb catalogo_app && createdb catalogo_app_test && createdb catalogo_app_e2e
composer install && npm install
cp .env.example .env && php artisan key:generate   # ajusta DB_PORT y DB_USERNAME a tu Postgres
php artisan migrate --seed                         # roles, sectores, marcas, usuarios y catálogos de ejemplo
composer run dev                                   # servidor en :8000, cola, logs, Vite y Reverb
```

Usuarios de ejemplo, todos con contraseña `password`: `admin@ogilvy.example` (administrador), `daniela.pardo@ogilvy.example` (Ogilvy), `valeria.ortiz@cencosud.example` (Marketing), `tomas.rincon@cencosud.example` (Planificación) y `hernan.castro@cencosud.example` (Comercial · Food), además de otros comerciales por sector. Son solo para desarrollo: en producción los usuarios los crea el administrador con `php artisan app:make-admin` y desde *Administración › Usuarios*.

## Roles

| Rol | Lado | Qué hace |
| --- | --- | --- |
| admin | Ogilvy | Todo: usuarios, clientes, tiempos, horario, catálogos y proyectos |
| agencia | Ogilvy | Carga catálogos, los envía a Marketing, ejecuta ajustes, sube versiones y usa proyectos |
| lectura | Ogilvy | Ve catálogos, páginas, reportes, notificaciones y calendario, sin ninguna acción |
| marketing | Cencosud | Primera validación, última aprobación a imprenta y defectos post-producción |
| planificacion | Cencosud | Segunda validación y segunda aprobación |
| comercial | Cencosud | Tercera validación y primera aprobación, solo en las páginas de sus sectores |

Sectores: Food, Líquidos, DPH, Bazar, Textil y Electro. El acceso es con correo y contraseña asignada por el administrador; no hay autorregistro y el primer ingreso obliga a cambiar la contraseña.

## Revisión de una página

- **Preparación**: Ogilvy carga el catálogo en tres pasos y envía las páginas a Marketing. Hasta entonces no hay cronómetro y el cliente no ve acciones.
- **Validación**: sube por Marketing → Planificación → Comercial. Solo valida el rol en turno, sin ajustes abiertos y dentro del horario laboral.
- **Imprenta**: baja en orden inverso, Comercial → Planificación → Marketing. Marketing cierra la página.
- **Versiones**: cada versión nueva reinicia la cadena, resuelve los ajustes que marca y se puede comparar con la anterior con una cortina.
- **Defecto post-producción**: solo Marketing y con la página cerrada; la reabre con un ajuste que va directo a Ogilvy.

## Ajustes

- El cliente dibuja una región sobre la página y elige el tipo: precio, descripción, imagen, diseño u otro.
- El ajuste baja por los roles que están por debajo de quien lo creó hasta llegar a Ogilvy. Por ejemplo, uno de Comercial pasa por Planificación y Marketing; cada uno lo valida con una nota opcional.
- Ogilvy lo resuelve subiendo una versión nueva. Mientras haya ajustes abiertos, el cronómetro de aprobación de la página queda en pausa.
- El autor puede editar o retirar su ajuste mientras nadie lo haya validado. Todos los que ven la página pueden responder.

## Tiempos y horario

- Todo se mide en minutos laborales. Horario por defecto: lunes a viernes de 08:30 a 18:00, editable en *Configuración › Horario*, con excepciones por fecha (ampliar el horario, cerrar el día o habilitar a personas concretas).
- Tiempos por defecto, editables en *Configuración › Tiempos*: ejecución de Ogilvy por tipo de ajuste (precio 30 min, descripción 45, imagen 120, diseño 240, otro 60), validación de cada eslabón 120 y aprobación de página por rol (Marketing 240, Planificación 240, Comercial 480).
- Cada cronómetro pasa a urgente en su último 25 % y luego a vencido. `review:check-deadlines` corre cada minuto y avisa una sola vez por etapa.

## Alertas y correo

- Cada movimiento (carga, envío, ajuste, validación, versión, aprobación, defecto) llega en la app y por correo a los roles en turno, filtrado por sector cuando aplica.
- Cada persona elige recibirlas al instante o en un resumen diario, que `review:send-digest` envía los días hábiles a las 08:35.
- La bandeja muestra lo que le toca a cada uno: páginas por validar o aprobar y ajustes por validar o ejecutar.

## Proyectos (solo Ogilvy)

- Tableros tipo Trello con tarjetas, subtareas, etiquetas, campos personalizados, adjuntos, comentarios con menciones, calendario y panel.
- Roles por proyecto: moderador (edita proyecto, listas, miembros, etiquetas y campos, y aprueba ingresos) y miembro (trabaja tarjetas).
- Al crear un catálogo se elige su proyecto: la tarjeta avanza sola por Backlog → En revisión → Aprobados y cada página es una subtarea automática.
- Cada comentario del cliente llega como ingreso por revisar; el moderador lo aprueba como entregable o lo descarta con un motivo.

## Tiempo real

Reverb transmite los cambios: el canal privado `review` recarga la revisión, `project.{id}` es un canal de presencia con los cambios del tablero y la actividad de los demás, y `App.Models.User.{id}` lleva las notificaciones personales. Sin clave de Reverb, la app recarga cada 60 segundos.

## Seguridad

- Permisos con spatie/laravel-permission y políticas por acción (página, ajuste, proyecto) con mensajes de denegación en español.
- Inicio de sesión limitado a 5 intentos por correo e IP. Cambio de contraseña obligatorio en el primer ingreso.
- Las imágenes de las páginas viven fuera de `public/` y se sirven por `/imagenes/{path}` solo con sesión. Los adjuntos de proyectos se sirven por `/adjuntos/{id}` solo a quien puede ver el proyecto.
- Límite de 60 peticiones por minuto en las acciones sobre ajustes (validar, resolver, responder).
- Los canales de tiempo real exigen un usuario activo y, en los proyectos, ser miembro.

## Pruebas

```bash
vendor/bin/pest          # dominio, políticas y HTTP (156 pruebas, base catalogo_app_test)
npm run test:e2e         # Playwright: login, flujo completo de revisión, usuarios y proyectos
npm run lint && npm run types && vendor/bin/pint --test
```

## Producción

1. PHP 8.4 y PostgreSQL. Define `APP_ENV=production`, `APP_URL` con tu dominio HTTPS, `DB_*`, `MAIL_*`, `QUEUE_CONNECTION=database` y, para tiempo real, `REVERB_*` y `VITE_REVERB_*`.
2. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build` y `php artisan migrate --force`.
3. Supervisor para `queue:work` y `reverb:start`, y un cron con `schedule:run` cada minuto.
4. Disco persistente en `storage/app/private`, donde quedan las páginas y los adjuntos.
5. `/salud` responde con el estado de la base de datos, el programador y la cola (503 si algo falla). Sentry se activa con `SENTRY_LARAVEL_DSN`.

La guía paso a paso para RunCloud está en [`docs/DEPLOY-RUNCLOUD.md`](docs/DEPLOY-RUNCLOUD.md).

## Documentación

- [`docs/CONTEXTO.md`](docs/CONTEXTO.md): qué se planeó, qué se hizo y cómo está.
- [`docs/ESPECIFICACION-TECNICA.md`](docs/ESPECIFICACION-TECNICA.md): modelo de datos, reglas y rutas, con el detalle para recrearlo.
- [`docs/PLAN.md`](docs/PLAN.md): bitácora de fases y decisiones.
- [`docs/BACKLOG.md`](docs/BACKLOG.md): pedidos pendientes.
- [`docs/prototipo/README.md`](docs/prototipo/README.md): el prototipo HTML original, que sirvió de especificación funcional.

## Estructura

```
app/
  Actions/Review/         casos de uso de la revisión, uno por verbo
  Domain/                 reglas puras: estado de página, enrutamiento de ajustes, cronómetros, horario, tableros
  Http/Controllers/       Review, Projects, Admin, Auth, Settings
  Http/Presenters/        modelos convertidos en props para las páginas
  Policies/               PagePolicy, AdjustmentPolicy, ProjectPolicy
  Events/ Listeners/      alertas y sincronización catálogo ↔ tablero
resources/js/
  pages/                  catálogos, revisión, reportes, notificaciones, configuración, administración, proyectos, calendario, panel
  components/review/      lienzo con zoom y dibujo de regiones, cadena, panel de ajustes, comparación de versiones
  components/projects/    tablero, tarjeta, subtareas, menciones, campos personalizados
database/                 migraciones, factories y seeders de ejemplo
tests/                    Pest (Feature, Unit) y Playwright (e2e)
docs/                     contexto, especificación, plan, backlog, despliegue y prototipo
```
