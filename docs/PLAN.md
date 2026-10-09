# Plan de producto — Revisión de Catálogos (Ogilvy × Cencosud)

Fecha: 17 sep 2026. Estado: las cinco fases hechas. Listo para desplegar (ver DEPLOY-RUNCLOUD.md).

## Punto de partida

El prototipo en [`docs/prototipo`](prototipo/README.md) define el comportamiento: cadena de
aprobación ascendente (Ogilvy carga → Marketing → Planificación → Comercial), ruta descendente de
ajustes hasta Ogilvy, SLAs en minutos laborales con cronómetros que se congelan fuera de horario,
Post Production Defect, comparación de versiones y bloqueo por sector. Esa lógica se conserva
íntegra y pasa al servidor; el front queda como reflejo.

## Decisiones cerradas

| Tema | Decisión |
|---|---|
| Stack | Laravel 12 + Inertia 2 + React 19 + TypeScript + Tailwind 4 + shadcn/ui |
| Base de datos | PostgreSQL (local en puerto 5433 con Homebrew; `catalogo_app` y `catalogo_app_test`) |
| Hosting | RunCloud (Nginx + PHP-FPM, Supervisor para colas, cron para scheduler). Por ahora solo local |
| Login | Contraseña asignada por un administrador al crear el usuario. Sin SSO ni registro público. Cambio obligatorio en el primer ingreso |
| Clientes | Solo Cencosud, marcas Jumbo y Metro. Sin multi-tenant |
| Alertas | Correo (Mailpit en local) y bandeja dentro de la app. Tiempo real por polling; websockets después si hace falta |
| Roles | `admin`, `agencia`, `marketing`, `planificacion`, `comercial`. Sectores: food, liquidos, dph, bazar, textil, electro |
| Arte real | Las páginas JPG de Cencosud no se versionan; viven en `storage/app/seed/paginas/` |

## Fases

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Proyecto, Postgres, Mailpit, prototipo en `docs/`, CI, git | hecha |
| 1 | Auth, sesiones, usuarios, roles, sectores, administración, cambio de contraseña obligatorio | hecha |
| 2 | Dominio: catálogos, páginas, versiones, ajustes, cadena, aprobaciones, PPD, SLA, con Pest | hecha |
| 3 | Pantallas shadcn: lista, asistente, rejilla, lienzo de revisión, comparar, reporte, tiempos de ajuste | hecha |
| 4 | Alertas: correo + bandeja, scheduler de vencimientos | hecha |
| 5 | Playwright, revisión de seguridad, guía de despliegue RunCloud | hecha |

## Arquitectura

- **Dominio en el servidor**: `app/Domain/` con servicios puros (`PageStatus`, `AdjustmentRouter`,
  `BusinessTime`) y Actions por transición (`ApprovePage`, `CreateAdjustment`, `ValidateAdjustment`,
  `UploadVersion`, `ReopenAsDefect`). Cada regla del README es un test de Pest.
- **Autorización**: roles con `spatie/laravel-permission`; reglas de dominio (sector, eslabón que
  le toca, horario laboral) en Policies.
- **Modelo de datos** (fase 2): `brands`, `catalogs`, `pages`, `page_sector`, `page_versions`,
  `adjustments`, `adjustment_validations`, `adjustment_replies`, `approvals`, `defects`,
  `sla_settings`, `business_hours`, `notifications`, `activity_log`.
- **Cronómetros**: el servidor entrega timestamp de inicio y plazo; el front anima el reloj.
  Un job por minuto marca vencidos y dispara alertas.
- **Imágenes**: originales en storage, derivados a 760 px y miniaturas en cola. Comparación por
  píxeles en el cliente (ya funciona en el prototipo).

## Desarrollo local

```bash
brew install postgresql@17 mailpit
brew services start postgresql@17   # configurado en puerto 5433
brew services start mailpit          # http://127.0.0.1:8025
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev
```

Pruebas: `vendor/bin/pest`, `npm run lint`, `npm run types`.

## Usuarios de prueba (seeder)

`php artisan db:seed` crea los usuarios del prototipo con contraseña `password`: `admin@ogilvy.example`
(administrador), `daniela.pardo@ogilvy.example` (agencia), `valeria.ortiz@cencosud.example` (marketing),
`tomas.rincon@cencosud.example` (planificación) y seis comerciales por sector. Solo para local y demo.

## Dominio (fase 2)

- `app/Domain/Time/BusinessTime`: minutos laborales, horario abierto, próxima apertura.
- `app/Domain/Sla/SlaSettings` y `app/Domain/Settings/SettingsRepository`: plazos editables (tabla `settings`).
- `app/Domain/Review/AdjustmentRouter`: ruta descendente de un ajuste hasta Ogilvy.
- `app/Domain/Review/PageStatus`: etapa de aprobación por versión, suspensión, ubicación de la página.
- `app/Domain/Review/Clocks`: reloj único por página (ajustes o aprobación), semáforo y vencidos.
- `app/Actions/Review/*`: PublishCatalog, CreateAdjustment, ValidateAdjustment, ResolveAdjustment,
  ReplyToAdjustment, UploadVersion, ApprovePage, ReopenAsDefect. Cada una autoriza con
  `app/Policies/PagePolicy` o `AdjustmentPolicy` (mensajes en español) y emite un evento de dominio
  en `app/Events/Review` para las alertas de la fase 4.
- Seeder `CatalogSeeder`: las dos octavillas del prototipo con sus ajustes, versiones y aprobaciones.

## Pantallas (fase 3)

| Ruta | Pantalla |
|---|---|
| `/catalogos` | Lista de catálogos con progreso, pendientes y link para compartir |
| `/catalogos/nuevo` | Asistente de carga (detalles, subir páginas, categorizar por sector) |
| `/catalogos/{slug}` | Rejilla de páginas con cronómetro, ubicación y acceso por sector |
| `/catalogos/{slug}/paginas/{n}` | Lienzo de revisión: regiones, ajustes, cadena, aprobar, PPD, nueva versión, comparar versiones |
| `/catalogos/{slug}/reporte` | KPIs, ajustes por sector, tabla, bitácora y CSV |
| `/tiempos` | Tiempos de ajuste y horario laboral (Ogilvy) |

Los cronómetros se calculan en el servidor y el navegador los anima; cada minuto la pantalla recarga
solo las props de la página. Fuera de horario los botones quedan deshabilitados con la razón del
servidor como tooltip. La detección de cambios entre versiones corre en el navegador (`resources/js/lib/diff.ts`).

## Alertas (fase 4)

- Cada evento de dominio (`app/Events/Review`) llega a `app/Listeners/Review/ReviewNotificationSubscriber`,
  que decide a qué rol avisar según el README del prototipo: ajuste nuevo al siguiente eslabón, validación
  al que sigue, ajustes resueltos al rol que debe aprobar, versión nueva a Marketing, aprobación al siguiente
  rol (o a Ogilvy y Marketing al cerrar la cadena), PPD a Ogilvy, catálogo publicado a Marketing.
- Destinatarios (`app/Domain/Review/Recipients`): usuarios activos del rol; los comerciales solo si
  comparten sector con la página.
- Dos canales por alerta: `ReviewAlert` (bandeja, síncrona) y `ReviewAlertMail` (correo, en cola).
- `php artisan review:check-deadlines` corre cada minuto en horario laboral y avisa una vez cuando un
  plazo entra en el último 25 % y otra cuando vence (los vencidos también llegan a Ogilvy). El nivel
  se guarda en `adjustments.alert_level` y `pages.approval_alert_level` y se reinicia en cada salto.
- Pantalla `/notificaciones`: "Lo que espera por ti" con cronómetros y "Actividad reciente"; el
  contador de no leídas va en el sidebar y se limpia al abrir la bandeja.
- En RunCloud hacen falta un cron `* * * * * php artisan schedule:run` y un proceso Supervisor
  `php artisan queue:work --tries=3`.

## Pruebas de extremo a extremo (fase 5)

`npm run test:e2e` levanta la app en el puerto 8001 contra la base `catalogo_app_e2e` (`.env.e2e`,
creado a partir de `.env.e2e.example`), la reinicia con los datos de demo y el horario abierto 24/7, y
recorre con Playwright: login, comentar región, validar en cadena, resolver, aprobar, nueva versión,
cierre de cadena con Post Production Defect, alta de usuario con cambio de contraseña y bloqueo por
sector. Corre también en GitHub Actions (job `e2e`).

## Revisión de seguridad (fase 5)

Revisado y resuelto:

- **Arte del cliente privado**: las imágenes salen del disco público y se sirven por `/imagenes/{ruta}`
  solo con sesión; sin sesión responde 302 al login. La ruta rechaza `..` y solo lee `catalogos/`.
- **Autorización en el servidor**: toda acción de revisión pasa por `PagePolicy` o `AdjustmentPolicy`;
  administración por permiso `users.manage`; catálogos y tiempos por `catalogs.manage` y `sla.manage`.
  El front solo refleja `can.*`.
- **Subidas**: solo `jpg/jpeg/png/webp`, máximo 15 MB, validado con `image`. Los nombres de archivo
  los genera el servidor (`p01-v2.jpg`), nunca el cliente.
- **Contraseñas**: hash bcrypt; en producción mínimo 10 caracteres con mayúsculas, minúsculas y
  números, y rechazo de contraseñas filtradas (Have I Been Pwned). Cambio obligatorio en el primer
  ingreso. La contraseña inicial nunca viaja por correo.
- **Sesiones**: login con límite de 5 intentos por minuto, regeneración de sesión al entrar,
  invalidación al salir. Cuentas desactivadas no pueden entrar.
- **Entradas**: validación con Form Requests; cajas de ajuste acotadas a 0..1; búsqueda con
  parámetros enlazados; sin HTML crudo en React. Escritura de ajustes limitada a 60 por minuto.
- **Secretos**: `.env`, `.env.e2e` y `storage/app` fuera de git. Sin credenciales en el código.

Pendiente para el despliegue (ver `DEPLOY-RUNCLOUD.md`): HTTPS con `SESSION_SECURE_COOKIE=true`,
`APP_DEBUG=false`, SMTP real, respaldos de base y de `storage/app/private`.

## Imágenes (post fase 5)

Al subir una página o una versión, `app/Jobs/ProcessPageVersionImage` genera en cola una copia de
pantalla de 1400 px (`*-display.jpg`) y una miniatura de 360 px (`*-thumb.jpg`) junto al original,
que se conserva intacto. Las pantallas usan los derivados y, mientras el worker no los produce, caen
al original. Por eso el worker de colas también es necesario para que las imágenes se vean livianas.

## Operación (post fase 5)

- Páginas de error 401, 403, 404, 419, 429, 500 y 503 en español con el diseño de la plataforma.
- Sentry integrado (`SENTRY_LARAVEL_DSN`); sin DSN queda apagado.
- `/salud`: 200 u 503 según base de datos, latido del scheduler (cada minuto) y latido del worker de
  colas (cada cinco). Es lo que vigila un monitor externo.
- Tema claro por defecto, el del prototipo; cada usuario puede cambiarlo en Ajustes › Apariencia.

## Decisiones de producto implementadas (18 sep 2026)

1. **Gestión de catálogos**: Ogilvy edita nombre y marca, agrega páginas al final, reordena, cambia
   sectores por página y archiva o reactiva. Un catálogo archivado sale de la lista, las bandejas y las
   alertas, y no admite acciones.
2. **Ajustes propios**: el autor edita tipo y texto, o retira el ajuste, hasta que alguien lo valide.
   Un defecto post-producción se puede reescribir pero no retirar.
3. **Reporte mensual** (`/reportes?mes=AAAA-MM`): catálogos publicados en el mes, KPIs consolidados,
   ejecución media de Ogilvy, demora media del cliente al aprobar, ajustes por sector y CSV.
4. **Actuar fuera de horario**: interruptor en Tiempos de ajuste. Los cronómetros siguen en pausa.
5. **Táctil**: el lienzo y la cortina usan eventos de puntero; funciona con dedo y lápiz.
6. **Tiempo real**: evento `ReviewChanged` en el canal privado `review` con Laravel Reverb; el layout
   recarga las props al recibirlo. Sin Reverb queda el refresco por minuto.
7. **Resumen diario**: cada usuario elige en Mi cuenta un correo por movimiento o un resumen cada
   mañana laboral (`review:send-digest`, 08:35 L–V). La bandeja siempre es inmediata.

## Horario semanal y excepciones (18 sep 2026)

- Pantalla `/horario` (Ogilvy): una ventana de apertura y cierre por día de la semana, o día cerrado.
- Excepciones por fecha: **ampliar horario para todos** (los cronómetros corren en la ventana ampliada),
  **día cerrado** (festivo: nada corre ni se puede actuar) y **habilitar personas** (solo las personas
  elegidas, de Ogilvy o de Cencosud, pueden actuar en la ventana; los cronómetros siguen en pausa).
- El interruptor global "actuar fuera de horario" vive ahora en esta pantalla; Tiempos de ajuste queda
  solo con los plazos.
- Modelo: `schedule_exceptions` + `schedule_exception_user`; `BusinessTime::windowFor()` resuelve la
  ventana efectiva de cada día y `actionsAllowedFor(User)` decide si alguien puede actuar ahora.

## Flujo de validación e imprenta (18 sep 2026)

Reemplaza la cadena única de aprobación del prototipo por dos pasadas sobre cada versión:

1. **Ogilvy** (paso visual): la página está "En ajustes" mientras haya ajustes abiertos; "Listo" si no.
2. **Validación, sube**: Marketing → Planificación → Comercial pulsan **Validar**. Quien validó ve
   "Validado" y ya no puede comentar esa versión.
3. **Aprobación a imprenta, baja**: el botón **Aprobar a imprenta** está siempre visible pero
   deshabilitado hasta que las tres validaciones existan; entonces se habilita para Comercial, luego
   Planificación y por último Marketing, cuya aprobación **cierra** la página.
4. **Post Production Defect**: solo Marketing, solo con la página cerrada. Anula validaciones y
   aprobaciones de la versión, la devuelve a Ogilvy con un ajuste marcado como defecto y reinicia la
   validación en Marketing.

Los ajustes abiertos suspenden ambas pasadas. Una versión nueva reinicia las dos. Los plazos de
aprobación por rol aplican a cada turno, sea de validación o de imprenta. En datos: `approvals.phase`
(`validation` | `print`); `PageStatus::phase()` devuelve `validation`, `print` o `closed`.

## Pantalla de revisión unificada (21 sep 2026)

Un solo diseño, sin scroll de ventana: cabecera del catálogo compacta con sus pestañas; barra de la
página con navegación, sectores, estado ("Validación · Marketing", "En Ogilvy · 1 ajuste en curso"),
cronómetro en vivo y los botones del momento (Comentar región, Validar, Aprobar a imprenta, Post
Production Defect o Cargar nueva versión); segunda línea con versiones, Revisión/Comparar y zoom
(alto, ancho, botones y Ctrl+rueda). La página ocupa el resto, completa a la altura por defecto.
A la derecha, un riel de iconos: ajustes (con conteo), cadena, cronómetro, comparar, información y
el alternador anclado/flotante. En pantallas anchas el panel queda anclado junto a la página; en
angostas flota. El menú lateral se contrae a iconos en esta pantalla. Atajos: C comentar región,
A ajustes, Esc, flechas. Preferencias (panel anclado, último panel) se recuerdan por navegador.

## Tipo de entregable, preparación en Ogilvy y vista general (21 sep 2026)

- Cada catálogo tiene un **tipo de entregable**: octavilla, cuartilla o volante. Se elige al crearlo,
  se edita en Administrar, se muestra en la cabecera y la lista filtra por tipo.
- **Preparación en Ogilvy**: al cargar un catálogo o agregar páginas, las páginas quedan "En Ogilvy ·
  preparación": sin cronómetro, sin validación posible y fuera de la bandeja de Marketing. Ogilvy las
  envía con "Enviar a Marketing" (por página desde la revisión, o todas desde Administrar); ahí arranca
  la validación, el cronómetro y el aviso a Marketing. Datos: `pages.submitted_at`.
- La revisión muestra un **panel de vista general** con scroll: Estado (cronómetro + cadena), Ajustes
  e Información en una sola columna. Los iconos del riel filtran a una sección; el icono activo la
  cierra. Se recuerda por navegador.

## Proyectos, fase A: clientes y tableros (21 sep 2026)

Se trae de `copytrello` la gestión de proyectos, sin IA ni wikis, como herramienta **interna de Ogilvy**:
el cliente nunca la ve (permiso `projects.access` solo para admin y agencia; `clients.manage` para admin).

- **Clientes** (`clients`): directorio en Configuración → Clientes, con color y estado. Las marcas
  Jumbo y Metro cuelgan de Cencosud (`brands.client_id`).
- **Proyectos** (`projects`): nombre, cliente, color, descripción, dueño y archivado. Miembros con rol
  `manager` (moderador: edita proyecto, listas, miembros y etiquetas) o `member` (trabaja tarjetas).
  Los admin y el dueño siempre son moderadores. Cada quien ve solo los proyectos donde participa.
- **Tablero**: listas (`board_lists`, con `kind` opcional para la futura sincronización con catálogos)
  y tarjetas (`cards`: título, descripción, prioridad, fecha límite, completada, responsables,
  etiquetas, `catalog_id` para la fase C). Orden por posición flotante; el servidor renumera cuando
  los huecos se agotan. Arrastre con dnd-kit; cada soltada es una petición y el tablero se refresca.
- Tiempo real: evento `BoardChanged` en el canal privado `project.{id}`; sin Reverb, refresco cada minuto.
- Pendiente: fase B (comentarios, adjuntos, campos personalizados, actividad, notificaciones) y fase C
  (calendario, dashboard, catálogo como tarjeta del proyecto).

## Proyectos, fase B: la tarjeta completa (21 sep 2026)

- La tarjeta abierta viaja en la URL (`?tarjeta=ID`): recargas y redirecciones mantienen el panel.
- **Comentarios** con menciones `@[Nombre](id)` (selector al escribir `@`); el autor edita los suyos,
  el autor o un moderador los borra.
- **Adjuntos**: archivos en el disco privado (`proyectos/{proyecto}/tarjetas/{tarjeta}/`, hasta 25 MB,
  servidos por sesión en `/adjuntos/{id}`) y enlaces.
- **Campos personalizados** por proyecto (texto, número, fecha, lista, selección múltiple, casilla,
  enlace, persona), ordenables desde Configuración → Campos; los valores se guardan por tarjeta y viajan
  al duplicar.
- **Actividad**: historial por tarjeta (creación, movimientos, cambios, asignaciones, comentarios, adjuntos).
- **Avisos** (app + correo, respetando el resumen diario): mención, asignación y comentario a quien
  creó la tarjeta o está asignado. Se guardan con `url` lista; la bandeja y el resumen la usan.

## Proyectos, fase C: catálogo como tarjeta, calendario y panel (21 sep 2026)

- Al crear un catálogo (o desde Administrar) se elige un **proyecto**: el catálogo aparece como tarjeta
  con su progreso (páginas aprobadas, ajustes abiertos, estado) y la cabecera del catálogo enlaza a la
  tarjeta. La tarjeta se mueve sola: lista con `kind` preparation → review → approved (o la primera si
  no hay etiquetas) al enviar a Marketing, aprobar, subir versión o reabrir por defecto; se completa
  cuando todas las páginas están aprobadas a imprenta (`SyncCatalogCard`, `CatalogCards`).
- **Calendario** (`/calendario`): mes con las tarjetas por fecha límite de los proyectos visibles,
  filtro por proyecto; color del proyecto, vencidas en rojo, completadas tachadas, las propias en negrita.
- **Panel** (`/panel`): inicio de Ogilvy con contadores, mis tarjetas, lo que vence esta semana, salud
  de proyectos, catálogos en proceso y actividad reciente. `/dashboard` lleva al panel a quien tiene
  proyectos y a Catálogos al cliente.

## Proyectos: paridad de interfaz con copytrello (22 sep 2026)

Revisión pantalla por pantalla contra `copytrello/web` y ajuste a su funcionamiento:

- **Tarjeta como modal ancho** (dos columnas, 90 % de alto): descripción, campos, adjuntos y comentarios
  a la izquierda; completar, duplicar, estado/lista, prioridad, fecha, asignados y etiquetas a la derecha.
  Cada campo se guarda solo (blur o cambio), sin botón "Guardar".
- **Popups**: confirmaciones en modal (`ConfirmDialog`, sin `confirm()` del navegador), duplicar con
  opciones (título, proyecto y lista destino, qué se copia, cantidad), selector con búsqueda y chips
  (`MultiSelect`) para asignados y etiquetas, sugerencias al escribir `@` encima del cuadro (Enter envía,
  Shift+Enter salta línea), sugerencias al agregar miembros.
- **Toasts** (sonner, abajo a la derecha) para mensajes del servidor, errores de validación, actividad de
  otros miembros en el tablero en vivo y notificaciones personales (canal `App.Models.User.{id}`).
- **Tablero**: cabecera con inicial de color o logo del cliente, miembros conectados (canal de presencia),
  panel lateral de actividad, barra de filtros (búsqueda, mis tarjetas, responsable, etiqueta, ocultar
  completadas); columna arrastrable desde toda la cabecera; portada de imagen, icono de descripción,
  prioridad siempre visible, "hoy" en ámbar; "Suelta tarjetas aquí".
- **Comentarios con archivos** (subir o pegar), campos de tipo imagen y texto largo, campos obligatorios y
  reordenables arrastrando, logo por cliente.
- **Panel** con gráfico de completadas por día, abiertas por prioridad, flujo por lista y carga por persona.
  **Calendario** con filtro por persona, carga del mes y leyenda.
- Animaciones: copytrello no anima sus modales; aquí se conservan las de shadcn (fundido y escala) más las
  transiciones de arrastre de dnd-kit, hover en tarjetas y giros de carga en los botones.

## Proyectos · Épica 1: subtareas (22 sep 2026)

- Tabla `subtasks` (tarjeta, estado pendiente/en_proceso/ajustes/completado, prioridad, fecha, posición,
  responsables `subtask_user`); comentarios, adjuntos e historial cuelgan de la subtarea con `subtask_id`.
- En el modal de la tarjeta: sección "Subtareas · N abiertas · N para mí" con barra de progreso, tabla
  (estado clicable con selector buscable, nombre, responsables, prioridad, fecha), alta en línea con Enter y
  orden arrastrando. Cada subtarea abre su propio diálogo (`?subtarea=`) con estado, prioridad, fecha,
  responsables, descripción, comentarios con archivos e historial.
- La tarjeta del tablero muestra "2/7"; "Mis tarjetas" cuenta las subtareas asignadas; el calendario y el
  panel listan subtareas con fecha con su tarjeta padre. Avisos por asignación, cambio de estado y mención.

## Proyectos · Épica 2: por cliente (22 sep 2026)

- Proyectos: filtro por cliente (chips con logo o color) y agrupación por cliente con "Sin cliente" al final.
- Calendario: selector de cliente que acota los proyectos. Panel: chip del cliente en cada flujo de trabajo.
- Nuevo catálogo y Administrar: el selector de proyecto ofrece los tableros del cliente de la marca (más los
  que no tienen cliente), con los que ya llevan catálogos primero.

## Proyectos · Épica 3: backlog, páginas y comentarios del cliente (22 sep 2026)

- Lista **Backlog** (tipo `backlog`) por defecto en proyectos nuevos; en los existentes se marca desde el
  menú de la lista ("Tipo de lista": Backlog, Preparación, En revisión, Aprobados; una lista por tipo).
  Un catálogo vinculado cae en el Backlog mientras está en preparación.
- **Subtarea por página** (`subtasks.page_id`): "Página 03 · Food, DPH". Es automática: nombre y estado
  siguen la revisión (Pendiente → En proceso → Ajustes con comentarios o entregables abiertos →
  Completado al aprobarse a imprenta). Se sincroniza en cada evento de revisión y al agregar páginas.
- **Bandeja de comentarios del cliente** (`adjustment_intakes`): cada ajuste del cliente llega a la subtarea
  de su página como "por revisar"; los moderadores reciben aviso. Solo un moderador aprueba el ingreso
  (crea un entregable) o lo descarta con motivo. El cliente no ve nada de esto.
- **Entregables** (`deliverables`): título, responsable, fecha, estado (Pendiente / En proceso / Listo);
  también se agregan a mano. Los que vienen de un comentario se marcan listos solos cuando Ogilvy sube la
  versión que resuelve ese ajuste. Aviso al responsable al asignarlo.
- Trazabilidad: desde la subtarea, "Ver en la página" abre la revisión con el ajuste resaltado (`?ajuste=`);
  en la revisión, Ogilvy ve "Por revisar / Entregable / Descartado en el tablero" en cada ajuste.

## Proyectos · ajustes tras el primer despliegue (23 sep 2026)

- Un administrador puede **eliminar** un catálogo escribiendo su nombre (lista y Administrar).
- Al crear o administrar un catálogo se elige además la **tarea existente** del proyecto que lo adopta
  (`cards.catalog_auto=false`); sus subtareas manuales pasan a ser las páginas en orden.
- **Aprobar el ingreso** de un comentario del cliente ya no crea un entregable: publica el comentario en
  la conversación de la subtarea, firmado por el cliente (`card_comments.adjustment_id`) y mencionando a
  los responsables, que reciben el aviso. Los entregables siguen existiendo solo como lista manual.

- Nuevo proyecto puede **copiar la configuración** de otro tablero (listas con tipo, etiquetas, campos,
  miembros con rol; el cliente si no se elige otro). Nunca copia tarjetas ni subtareas.

- Las tarjetas tienen **fecha de inicio** además de la fecha límite (`cards.start_date`); el límite no
  puede ser anterior al inicio y el tablero muestra el rango.

- El **panel** filtra por proyecto, persona y rango de fechas (inicio o límite dentro del rango); la
  gráfica de completadas sigue el rango elegido (máximo 120 días) o los últimos 30 días.
