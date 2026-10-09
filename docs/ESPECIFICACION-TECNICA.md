# Especificación técnica · Revisión de Catálogos

Escrita para que otra persona o una IA pueda entender y recrear el sistema sin ver el código.
Contexto de negocio y estado: [CONTEXTO.md](CONTEXTO.md). Actualizada el 23 sep 2026.

## 1. Stack y herramientas

| Capa | Tecnología |
|------|------------|
| Backend | PHP 8.4 (8.5 en local), Laravel 12, Inertia 2 (server side), spatie/laravel-permission 8, intervention/image 4, laravel/reverb 1 (WebSockets), sentry/sentry-laravel (opcional), tightenco/ziggy (rutas en JS) |
| Frontend | React 19, TypeScript 5, Inertia React 2, Tailwind 4, shadcn/ui (Radix), lucide-react, @dnd-kit (arrastre), @laravel/echo-react + pusher-js (tiempo real), sonner (toasts), Vite 6 |
| Base de datos | PostgreSQL (local: Homebrew postgresql@17 en el puerto 5433). Bases: `catalogo_app`, `catalogo_app_test`, `catalogo_app_e2e` |
| Correo local | Mailpit (SMTP 1025, UI 8025) |
| Pruebas | Pest 3 (Feature + Unit), Playwright (E2E, Chromium), Pint, ESLint, Prettier, `tsc` |
| CI | GitHub Actions `ci.yml`: PHP 8.4 + Node 24 + Postgres; `pint --test`, `pest --ci`, `format:check`, `eslint`, `tsc`, build |
| Idioma | Toda la interfaz, mensajes, rutas y commits en español. Zona horaria `America/Bogota`. |

Comandos: `composer run dev` (servidor 8000 + cola + pail + Vite + Reverb), `php artisan test`,
`npx playwright test` (levanta `artisan serve` en 8001 con `.env.e2e` y ejecuta `migrate:fresh --seed
--seeder=E2ESeeder`), `npm run build`, `vendor/bin/pint`, `npm run types`.

## 2. Arquitectura

- Monolito Laravel con páginas Inertia (`resources/js/pages/**`) renderizadas por controladores.
  Cada respuesta lleva props tipadas (`resources/js/types/index.ts` y `types/projects.ts`).
- Capas en `app/`:
  - `Actions/Review/*`: casos de uso de la revisión (uno por verbo). Reciben el usuario, autorizan con
    políticas, escriben en transacción y despachan eventos.
  - `Domain/Review/*`: reglas puras (estado de página, enrutamiento de ajustes, cronómetros, bandeja).
    `Domain/Time/*`: horario laboral. `Domain/Sla/*`: tiempos. `Domain/Settings/SettingsRepository`
    (tabla `settings` clave/valor jsonb). `Domain/Projects/*`: tableros.
  - `Http/Presenters/*`: convierten modelos en arreglos para las props (ReviewPresenter, ProjectPresenter).
  - `Policies/*`: PagePolicy, AdjustmentPolicy, ProjectPolicy con mensajes de denegación en español.
  - `Events/*` + `Listeners/*`: notificaciones y sincronización catálogo ↔ tablero.
  - `Http/Controllers/Review|Projects|Admin|Auth|Settings`.
- Convención de errores: el trait `HandlesDomainErrors::attempt(fn, mensaje)` convierte
  `AuthorizationException` e `InvalidArgumentException` en un `back()->with('error', …)`; el éxito
  hace `back()->with('success', …)`; con mensaje vacío no hay flash.
- Props compartidas (`HandleInertiaRequests`): `auth.user {id, name, email, organization, role,
  role_label, sectors[], must_change_password, notification_mode, acts_as_agency, can {manage_users,
  manage_catalogs, manage_sla, projects, manage_clients}}`, `workday {open, label}`, `inbox {total,
  approvals, adjustments}`, `unread`, `round {approved, pages}`, `flash {success, error, key}`.
  `flash.key` es un UUID nuevo en cada respuesta con mensaje, para que los toasts se disparen aunque
  el texto se repita.

## 3. Modelo de datos

Tipos: `id` bigint autoincrement; FK con `constrained()`; timestamps salvo que se indique.

### 3.1 Acceso
- `users`: name, email, password, organization (texto libre, p. ej. "Cencosud · Marketing"),
  is_active, must_change_password, notification_mode (`instant|daily`), remember_token.
- `sectors`: slug, name, abbr, position. Sembrados: food/Food/FOOD, liquidos/Líquidos/LIQ, dph/DPH/DPH,
  bazar/Bazar/BAZ, textil/Textil/TEX, electro/Electro/ELE. Pivote `sector_user`.
- Tablas de spatie/permission. Roles: admin, agencia, marketing, planificacion, comercial.
  Permisos: `users.manage` (admin), `catalogs.manage` y `sla.manage` (admin, agencia),
  `projects.access` (admin, agencia), `clients.manage` (admin).
- `notifications` (Laravel), `jobs`, `cache`, `sessions` según config.

### 3.2 Revisión de catálogos
- `settings`: key (PK), value jsonb. Claves: `sla` (execution por tipo, validation, approval por rol),
  `business_hours` (ventanas por día ISO 1–7 en minutos, timezone), `actions_outside_hours` (bool).
- `clients`: name (único), color (hex), logo_path, is_active.
- `brands`: slug, name, client_id nullable. Sembradas jumbo y metro bajo Cencosud.
- `catalogs`: brand_id, slug (único), name, kind (`octavilla|cuartilla|volante`), created_by,
  published_at, archived_at.
- `pages`: catalog_id, number, current_version, submitted_at (null = en preparación en Ogilvy),
  waiting_since (inicio del cronómetro de la etapa actual), approval_alert_level. Único (catalog_id, number).
  Pivote `page_sector`.
- `page_versions`: page_id, number, image_path (original), display_path (1400 px), thumb_path (360 px),
  uploaded_by. Único (page_id, number). Archivos en el disco `local` (`storage/app/private`) bajo
  `catalogos/{slug}/pNN-vN.jpg`, servidos por `/imagenes/{path}` con sesión.
- `adjustments` (comentarios del cliente sobre una región): page_id, version, author_id,
  origin_role (rol del autor), type (`precio|descripcion|imagen|diseno|otro`), text, box jsonb
  `{x,y,w,h}` en fracciones 0–1, status (`open|resolved`), resolved_in_version, resolved_at,
  route jsonb (lista de roles que faltan por validar, terminando en `agencia`), is_defect,
  stage_since, received_by_agency_at, alert_level (`urgente|vencido`).
- `adjustment_validations`: adjustment_id, user_id, role, note. Cada rol intermedio "valida" el ajuste.
- `adjustment_replies`: adjustment_id, user_id, text.
- `approvals`: page_id, version, user_id, role, phase (`validation|print`), delay_minutes.
  Único (page_id, version, role, phase).
- `defects`: page_id, version, user_id, role, type, reason, adjustment_id (el ajuste creado al reabrir).
- `schedule_exceptions`: date, kind (`extend|closed|people`), from_minutes, to_minutes, note,
  created_by. Pivote `schedule_exception_user` (personas habilitadas ese día).

### 3.3 Proyectos (solo Ogilvy)
- `projects`: client_id nullable, name, description, color, owner_id, archived_at.
- `project_members`: project_id, user_id, role (`manager|member`). Único (project_id, user_id).
- `board_lists`: project_id, name, position (float), kind nullable
  (`backlog|preparation|review|approved`, una lista por tipo).
- `cards`: project_id, board_list_id, title, description, position (float), priority
  (`low|medium|high|urgent`), due_date, completed_at, created_by, catalog_id nullable (tarjeta de catálogo).
- `card_user` (responsables), `labels` (project_id, name, color), `card_label`.
- `custom_fields`: project_id, name, type (`text|textarea|image|number|date|select|multiselect|
  checkbox|url|user`), options jsonb, required, position. `card_field_values`: card_id,
  custom_field_id, value jsonb (para `image`, el id de un adjunto).
- `card_comments`: card_id, subtask_id nullable, user_id, body (menciones como `@[Nombre](id)`).
- `card_attachments`: card_id, subtask_id, card_comment_id, custom_field_id (todos nullable), user_id,
  kind (`file|link`), name, path, url, mime, size. Archivos en `proyectos/{proyecto}/tarjetas/{tarjeta}/…`,
  servidos por `/adjuntos/{id}` con autorización de vista del proyecto.
- `card_activities`: card_id, subtask_id, project_id, user_id, action, meta jsonb, created_at.
- `subtasks`: card_id, page_id nullable (subtarea automática de una página), project_id, title,
  description, status (`pending|in_progress|adjustments|completed`), priority, due_date, completed_at,
  position, created_by. `subtask_user` responsables.
- `adjustment_intakes`: subtask_id, adjustment_id (único), status (`pending|approved|dismissed`),
  decided_by, decided_at, reason.
- `deliverables`: subtask_id, intake_id nullable, title, status (`pending|in_progress|done`),
  assignee_id, due_date, done_at, position, created_by.

## 4. Reglas del dominio de revisión

### 4.1 Roles y cadena
`Role::chain()` = [marketing, planificacion, comercial]. Comercial `usesSectors()`: solo actúa en páginas
cuyo sector coincide con alguno suyo (`User::canActOn(Page)`). Agencia y admin actúan como Ogilvy
(`actsAsAgency()`); admin puede todo.

### 4.2 Fases de una página (`PageStatus`)
- `preparation`: `submitted_at` nulo. Sin cronómetro; el cliente no puede comentar ni validar.
  Ogilvy envía con "Enviar a Marketing" (`SubmitPages`): fija `submitted_at` y `waiting_since` y avisa.
- `validation`: falta alguna aprobación con `phase=validation` para la versión actual, en el orden
  marketing → planificacion → comercial. `validationStage()` devuelve el primer rol que falta.
- `print`: validación completa; faltan aprobaciones `phase=print` en el orden inverso
  comercial → planificacion → marketing. `printStage()` devuelve el que falta.
- `closed`: todas las aprobaciones de ambas fases para la versión actual. `isApproved()`.
- Una **nueva versión** (`UploadVersion`) sube `current_version`, reinicia la cadena (las aprobaciones
  son por versión), resuelve los ajustes marcados y reinicia `waiting_since`.
- **Validar** (`ValidatePage`) solo puede hacerlo el rol en turno, con la página sin ajustes abiertos,
  dentro de horario (o con permiso de actuar fuera de él). Registra `delay_minutes` respecto al SLA.
- **Aprobar a imprenta** (`ApproveForPrint`) igual, en la fase `print`.
- **Defecto post-producción** (`ReopenAsDefect`): solo marketing y solo si `closed`. Crea un `defect`
  y un ajuste `is_defect=true` cuya ruta va directo a agencia; la página vuelve a estar abierta.

### 4.3 Ajustes (`CreateAdjustment`, `AdjustmentRouter`, `ValidateAdjustment`, `ResolveAdjustment`)
- Solo roles del cliente comentan, sobre la versión actual, en fase `validation`, en páginas de su sector,
  y solo si su propio rol aún no validó esa versión (PagePolicy::comment).
- Ruta = roles por debajo del origen en la cadena, en orden descendente, más `agencia`:
  origen comercial → [planificacion, marketing, agencia]; planificacion → [marketing, agencia];
  marketing → [agencia]. `AdjustmentRouter::routeFrom(origin)`.
- Cada rol en turno **valida el ajuste** (con nota opcional) y la ruta avanza; al llegar a agencia se fija
  `received_by_agency_at`. Ogilvy **resuelve** subiendo una versión que lo marque, o desde el ajuste.
- El autor puede editar o retirar su ajuste mientras nadie lo haya validado (AdjustmentPolicy).
- Respuestas (`AdjustmentReply`) en cualquier momento por quien puede ver la página.
- Un ajuste abierto **suspende** el cronómetro de aprobación de la página (`approvalSuspended`).

### 4.4 Tiempo laboral y SLA
- `BusinessHours`: ventanas por día ISO (por defecto lunes–viernes 08:30–18:00, `America/Bogota`),
  editables en Configuración → Horario. `ScheduleException` por fecha: `extend` (amplía el horario de
  todos), `closed` (día cerrado) y `people` (habilita a personas concretas fuera de horario).
- `BusinessTime::workingMinutesBetween(a, b)` suma solo minutos dentro de ventanas.
  `actionsAllowedFor(user)`: true si está abierto, o si `actions_outside_hours` global está activo, o si
  la persona tiene una excepción `people` hoy.
- `SlaSettings` (minutos laborales), por defecto: ejecución por tipo precio 30, descripcion 45, imagen
  120, diseno 240, otro 60; validación de un ajuste por cada rol intermedio 120; aprobación de página
  marketing 240, planificacion 240, comercial 480. Editables en Configuración → Tiempos.
- `Clocks`: por ajuste, consumido = minutos laborales desde `stage_since` (o `received_by_agency_at`),
  SLA según la etapa (validación o ejecución por tipo), estado `corriendo|urgente|vencido|espera`
  (urgente = último 25 %). Por página, cronómetro de aprobación del rol en turno desde `waiting_since`.
- `review:check-deadlines` (cada minuto) avisa una sola vez por etapa al pasar a urgente y al vencer
  (`alert_level`), a los roles en turno y a Ogilvy cuando vence.

### 4.5 Bandeja e inbox
`Inbox::items(user)`: páginas por validar/aprobar para su rol (y sector) y ajustes por validar o por
ejecutar (Ogilvy). `counts()` alimenta la insignia del menú.

## 5. Alertas y correo

- `Alerts::toRoles(roles, AlertPayload, page)` → `Recipients::forRoles` (usuarios activos del rol,
  filtrados por sector si aplica) → `ReviewAlert` (base de datos, síncrono) y `ReviewAlertMail`
  (cola) salvo que el usuario prefiera resumen diario.
- `ReviewNotificationSubscriber` mapea eventos → avisos: CatalogPublished (carga), PagesSubmitted,
  AdjustmentCreated/Validated/Resolved, VersionUploaded, PageApproved (validación e imprenta),
  PageReopenedAsDefect. `AlertPayload {kind, text, actor, catalog_slug, catalog_name, page_number}`
  se guarda tal cual; la bandeja arma la URL.
- Proyectos: `ProjectAlerts` envía `ProjectAlert` (base de datos + broadcast al canal privado
  `App.Models.User.{id}`) y `ProjectAlertMail` con `{kind, text, actor, project_name, card_title,
  excerpt, url}` para mención, asignación, comentario, cambio de estado de subtarea, ingreso por revisar
  (a moderadores) y entregable asignado.
- `review:send-digest` (días hábiles 08:35) envía `DailyDigestMail` con pendientes y actividad de 24 h a
  quienes eligieron resumen diario. `AccountCreated` al crear usuarios.

## 6. Tiempo real

- Reverb (`BROADCAST_CONNECTION=reverb`). Canales (`routes/channels.php`): `review` (privado, cualquier
  usuario activo; evento `.review.changed` con `{kind, catalog, page}` → el layout recarga props);
  `project.{id}` (presencia; devuelve `{id, name}`; eventos `.board.changed` → recarga y
  `.activity.new` → toast "X hizo Y" a los demás); `App.Models.User.{id}` (notificaciones personales).
- Sin clave de Reverb (`VITE_REVERB_APP_KEY` vacío) la app cae a recarga cada 60 s.

## 7. Módulo de proyectos (dominio)

- Visibilidad: `Project::visibleTo(user)` = admin ve todo; otros, los proyectos donde son dueño o miembro.
  `roleOf(user)`: admin y dueño son siempre `manager`.
- ProjectPolicy: `view` (miembro), `work` (miembro y no archivado), `manage` (moderador y no archivado),
  `delete` (dueño o admin).
- Orden por posición flotante (`Positions`): nuevo = máximo + 1024; al arrastrar el cliente manda el
  punto medio entre vecinos; el servidor renumera cuando dos vecinos quedan a menos de 0.001.
- `Activity::log(card, by, action, meta, subtask?)` escribe el historial y emite `ActivityLogged`.
  `Activity::describe()` traduce cada acción a una frase.
- Duplicar tarjeta: título, proyecto y lista destino, copiar responsables/fecha/adjuntos, 1–10 copias
  numeradas; entre proyectos, etiquetas y campos se emparejan por nombre y lo que no existe se omite.
- Campos personalizados: valores tipados y saneados en `CardController::cleanValue`; `image` se sube por
  `POST tarjetas/{card}/campos/{field}/imagen` y crea un adjunto ligado al campo.
- **Subtareas**: estado propio (Pendiente, En proceso, Ajustes, Completado), responsables, prioridad,
  fecha, descripción, comentarios con archivos, historial. Cuentan para "Mis tarjetas", calendario y panel.
- **Catálogo ↔ tarjeta** (`CatalogCards`):
  - `link(catalog, project, by)`: crea la tarjeta (o la mueve de proyecto; null la elimina) en la lista
    de la etapa; `stage(catalog)` = `preparation` si ninguna página fue enviada, `approved` si todas están
    cerradas, si no `review`. Etapa → lista por `kind` (`preparation` prefiere `backlog`); sin tipos, la
    primera lista.
  - `sync(catalog)`: se llama desde `SyncCatalogCard` en CatalogPublished (páginas agregadas),
    PagesSubmitted, PageApproved, VersionUploaded, PageReopenedAsDefect, AdjustmentCreated y
    AdjustmentResolved. Mueve la tarjeta, la completa al aprobarse y ejecuta `syncPages`.
  - `syncPages`: una subtarea automática por página ("Página 03 · Food, DPH"), estado desde la fase:
    Pendiente (preparación), En proceso (en revisión), Ajustes (ajustes abiertos o entregables abiertos),
    Completado (aprobada a imprenta). Cada ajuste de un rol del cliente genera un `adjustment_intake`
    pendiente; cuando el ajuste se resuelve, su entregable pasa a `done`. Las subtareas de página no se
    renombran, ni cambian de estado ni se borran a mano.
  - Ingreso: `POST ingresos/{intake}/aprobar` (moderador) crea un `deliverable` con el tipo y texto del
    comentario y pone la subtarea en Ajustes; `descartar` guarda el motivo. Los moderadores reciben aviso
    al llegar un comentario.
  - Trazabilidad: la subtarea enlaza a `/catalogos/{slug}/paginas/{n}?ajuste={id}` (resalta el ajuste y
    abre el panel); `ReviewPresenter` añade `board {status, url}` a cada ajuste solo para Ogilvy.

## 8. Rutas (116, todas bajo `auth` + `password.changed` salvo autenticación y `/salud`)

- Autenticación: `login`, `logout`, `forgot-password`, `reset-password/{token}`, `confirm-password`,
  `password/force` (cambio obligatorio). Ajustes de cuenta: `settings/profile|password|appearance`.
- Catálogos: `GET catalogos` (filtros q, archivados, tipo), `GET catalogos/nuevo`, `POST catalogos`,
  `GET catalogos/{slug}`, `GET catalogos/{slug}/editar`, `PUT catalogos/{slug}`,
  `POST …/paginas` (agregar), `PUT …/orden`, `PUT …/paginas/{n}/sectores`, `POST …/enviar`,
  `POST …/paginas/{n}/enviar`, `POST …/archivar|reactivar`, `GET …/reporte(.csv)`.
- Páginas: `GET catalogos/{slug}/paginas/{n}`, `POST …/validar`, `…/imprenta`, `…/defecto`,
  `…/versiones`, `…/ajustes`. Ajustes: `POST ajustes/{id}/validar|resolver|respuestas`,
  `PUT|DELETE ajustes/{id}` (throttle 60/min).
- Otros: `notificaciones`, `reportes(.csv)?mes=`, `configuracion` (redirige), `tiempos`, `horario`
  (+ excepciones), `admin/usuarios` (resource), `admin/clientes` (+ logo), `imagenes/{path}`,
  `clientes/{id}/logo`, `salud`, `dashboard` (redirige a `panel` u `catalogos`).
- Proyectos (`can:projects.access`): `panel`, `calendario` (mes, proyecto, persona, cliente),
  `proyectos` (index, store, show con `?tarjeta=&subtarea=`, update, destroy, archivar, reactivar),
  miembros, etiquetas, listas (store, update con name/kind, mover, destroy), tarjetas (store, update,
  mover, completar, duplicar, destroy, campos, campos/{field}/imagen, comentarios, adjuntos, subtareas),
  comentarios, adjuntos, campos del proyecto (store, orden, update, destroy), subtareas (update, mover,
  comentarios, entregables, destroy), ingresos (aprobar, descartar), entregables (update, destroy).
- La prop `activity` de `projects.show` es `Inertia::optional`: solo viaja en recargas parciales.

## 9. Frontend

- `resources/js/pages/`: auth/*, catalogs/{index,create,edit,show,report}, pages/show (revisión),
  notifications/index, reports/monthly, settings/{profile,password,appearance,sla,schedule},
  admin/users/{index,create,edit}, admin/clients/index, projects/{index,show}, calendar/index,
  dashboard/index.
- Layout `layouts/app-layout` → `app-sidebar-layout` (menú lateral en tres bloques: Catálogos,
  Proyectos solo con permiso, y Notificaciones/Configuración; `Toaster` de sonner; oyentes de Echo).
- Revisión (`components/review/*`): `canvas` (imagen con zoom, arrastre, dibujo de región con eventos
  pointer, resaltado), `review-bar`, `chain-card` (pasos y acciones), `adjustments-panel`, `compare`
  (cortina entre versiones), `rail` (iconos que filtran el panel), `dialogs` (validar, imprenta,
  defecto, versión), `catalog-header`, `page-info`, `clock-bar`.
- Proyectos (`components/projects/*`): `board` (dnd-kit, columnas y tarjetas, filtros, menú de lista con
  tipo), `card-modal` (dos columnas, guardado automático por campo, subtareas, adjuntos, comentarios con
  menciones y archivos, historial), `subtask-dialog` (estado, responsables, ingresos del cliente,
  entregables, comentarios), `duplicate-card-dialog`, `project-settings-dialog` (General, Miembros,
  Etiquetas, Campos con arrastre), `multi-select`, `status-picker`, `mentions`, `field-input`,
  `activity-panel`, `person-avatar`, `color-picker`.
- Hooks: `use-live-review` (canal review), `use-live-board` (tablero, presencia, actividad,
  notificaciones), `use-flash-toast` (flash → toast), `use-review-prefs` (panel anclado/filtro en
  localStorage), `use-clock` (cuenta regresiva), `use-appearance` (tema).
- Convenciones: `useForm` de Inertia con `type` (no `interface`) para los formularios; `router.get` con
  `only:[…]`, `preserveState` y `preserveScroll` para abrir tarjeta/subtarea por URL; recarga cada 60 s
  como respaldo del tiempo real; `data-testid="canvas"` en la revisión para E2E.

## 10. Imágenes, trabajos, salud

- `ProcessPageVersionImage` (cola) genera `-display.jpg` (1400 px, calidad 82) y `-thumb.jpg` (360 px,
  calidad 78) con Intervention Image; el presenter usa el derivado si existe.
- Programador (`routes/console.php`): `review:check-deadlines` cada minuto, `review:send-digest` días
  hábiles 08:35, latidos `heartbeat:scheduler` cada minuto y `QueueHeartbeat` cada 5 min.
- `/salud`: JSON con base de datos, latido del programador (máx. 10 min) y de la cola (máx. 15 min);
  503 si algo falla. Sentry se activa con `SENTRY_LARAVEL_DSN`.
- `php artisan app:make-admin {email} {name} --password=` crea o promueve un administrador.

## 11. Pruebas

- Pest (`tests/Feature/**`, `tests/Unit/**`), base `catalogo_app_test`, `QUEUE_CONNECTION=sync`,
  `BROADCAST_CONNECTION=log`, `MAIL_MAILER=array`. Helpers en `tests/Feature/Review/Helpers.php`:
  `openHours()`, `closedHours()`, `userWithRole(Role, sectors)`, `foodPage()`, `closeWholeChain(page)`.
  Cobertura: cadena de aprobación, enrutamiento, versiones y defectos, cronómetros, plazos, alertas,
  resumen, tiempo real, horario y excepciones, fuera de horario, gestión de catálogos, preparación,
  reporte mensual, derivados, usuarios, salud; proyectos (tableros, tarjeta, paridad, catálogo↔tarjeta,
  panel y calendario, subtareas, cliente, backlog e ingresos). 156 pruebas.
- Playwright (`tests/e2e/*.spec.ts`): login, flujo de revisión completo (serie), usuarios, proyectos.
  `E2ESeeder` abre el horario 24/7. 11 escenarios.

## 12. Configuración y despliegue

- `.env`: `DB_*` (pgsql, puerto 5433 en local), `MAIL_*` (Mailpit), `QUEUE_CONNECTION=database`,
  `BROADCAST_CONNECTION=reverb` + `REVERB_*` y `VITE_REVERB_*`, `SENTRY_LARAVEL_DSN` opcional,
  `APP_URL` con puerto en local (`http://localhost:8000`) para que las URLs de imágenes funcionen.
- Producción (RunCloud, guía en `DEPLOY-RUNCLOUD.md`): PHP 8.4, Postgres, supervisor para
  `queue:work` y `reverb:start`, cron `schedule:run` cada minuto, disco `local` persistente en
  `storage/app/private`, `php artisan migrate --force`, `npm run build`.

## 13. Cómo recrearlo desde cero (orden recomendado)

1. `laravel new` con starter React + Inertia; añadir Postgres, spatie/permission, Reverb, Intervention,
   Ziggy, shadcn/ui (base neutral, primario rojo Ogilvy `hsl(0 85% 58%)`), dnd-kit, echo-react, sonner.
2. Migraciones y modelos de §3 en ese orden; seeders de roles/permisos, sectores, marcas bajo Cencosud,
   usuarios demo, SLA y horario por defecto.
3. Dominio de revisión (§4): `PageStatus`, `AdjustmentRouter`, `BusinessHours/BusinessTime`,
   `SlaSettings`, `Clocks`, `Inbox`; políticas; acciones; eventos y suscriptor de alertas (§5).
4. Controladores y presenters de revisión; páginas React (lista, carga en tres pasos, revisión sobre la
   página con canvas, reporte, notificaciones, configuración).
5. Comandos programados, derivados de imagen, salud, tiempo real (§6, §10).
6. Módulo de proyectos (§7): modelos, políticas, controladores, presenter, tablero y modales; luego
   `CatalogCards` + `SyncCatalogCard` para ligar catálogos.
7. Pruebas Pest por cada regla de §4 y §7; Playwright para los flujos de §11; CI.
