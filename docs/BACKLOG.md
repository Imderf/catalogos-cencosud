# Backlog · Proyectos (a partir de "Catalogoapp-ajustes.docx", 22 sep 2026)

Origen: documento del dueño del producto con 3 pedidos y 6 capturas. Cada épica trae historias, criterios de
aceptación y una talla (S = medio día, M = 1–2 días, L = 3+ días). Las "suposiciones" son decisiones
que tomé donde el documento no lo dice; se pueden cambiar antes de empezar.

## Épica 1 · Subtareas en las tarjetas (L) — HECHA (22 sep 2026)

Toda tarjeta puede tener subtareas con vida propia, como en la captura de ClickUp.

| # | Historia | Criterios de aceptación | Talla |
|---|----------|-------------------------|-------|
| 1.1 | Como miembro, agrego subtareas a una tarjeta desde el modal | Tabla "Subtareas · N abiertas · N para mí" con nombre, responsables, prioridad, fecha límite y estado; "+ Agregar" crea en línea con Enter; arrastre para ordenar | M |
| 1.2 | Como miembro, abro una subtarea y la trabajo | Detalle propio (modal anidado): título, descripción, responsables, prioridad, fecha, estado, comentarios con menciones y adjuntos, historial | M |
| 1.3 | Como miembro, cambio el estado de una subtarea | Selector con búsqueda: **Pendiente**, **En proceso**, **Ajustes** (abiertos) y **Completado** (cerrado); el cambio queda en el historial y avisa a los responsables | S |
| 1.4 | Como equipo, veo el avance en la tarjeta y el tablero | Chip "3/7" en la tarjeta del tablero; barra de progreso en el modal; "Ocultar completadas" y "Mis tarjetas" tienen en cuenta subtareas asignadas a mí | S |
| 1.5 | Como equipo, las subtareas llegan al calendario y al panel | Subtareas con fecha aparecen en el calendario (con el color del proyecto y "↳ tarjeta padre"); "Mis tarjetas" del panel incluye mis subtareas | S |

Suposiciones: las subtareas no tienen sub-subtareas; heredan proyecto y etiquetas de la tarjeta; el
"Tipo de tarea" de la captura se resuelve con etiquetas del proyecto (no creo un campo nuevo).

## Épica 2 · Proyectos por cliente (S) — HECHA (22 sep 2026)

| # | Historia | Criterios de aceptación | Talla |
|---|----------|-------------------------|-------|
| 2.1 | Como Ogilvy, veo los proyectos agrupados por cliente | En Proyectos: filtro por cliente y agrupación por cliente (Cencosud, Sin cliente…); cada tarjeta de proyecto muestra chip o logo del cliente | S |
| 2.2 | Como Ogilvy, filtro el calendario y el panel por cliente | Selector de cliente en Calendario; el panel muestra el cliente junto a cada proyecto en "Flujo de trabajo" | S |
| 2.3 | Al crear un catálogo, solo se ofrecen proyectos del cliente de la marca | El selector "Proyecto (tablero interno)" filtra por el cliente de la marca elegida (Jumbo/Metro → Cencosud) y muestra primero los que ya tienen catálogos de esa marca | S |

## Épica 3 · Backlog y subtareas por página del catálogo (L) — HECHA (22 sep 2026)

El catálogo ya crea una tarjeta en el tablero y avanza sola. Ahora esa tarjeta se convierte en el
backlog de trabajo de Ogilvy sobre el catálogo, con una subtarea por página.

| # | Historia | Criterios de aceptación | Talla |
|---|----------|-------------------------|-------|
| 3.1 | Como Ogilvy, cada proyecto tiene una lista **Backlog** | Nueva lista por defecto con tipo `backlog` (antes de "Por hacer"); los catálogos recién vinculados caen ahí mientras están en preparación; los proyectos existentes pueden marcar una lista como Backlog desde Configuración | S |
| 3.2 | Al vincular un catálogo, se crea una subtarea por página | "Página 01 · Food", una por página, ordenadas; si se agregan páginas al catálogo, se agregan subtareas; el estado de cada subtarea sigue la revisión: Pendiente (preparación) → En proceso (validación) → Ajustes (ajustes abiertos) → Completado (aprobada a imprenta) | M |
| 3.3 | Los comentarios del cliente llegan a la subtarea de su página como **bandeja de entrada** | Cada ajuste del cliente (comentario en la revisión) aparece en la subtarea con autor, rol, tipo, texto, región y miniatura; queda "Por revisar" hasta que un moderador decida | M |
| 3.4 | Como moderador, apruebo o descarto el ingreso de cada comentario | Botones "Aprobar ingreso" / "Descartar" con motivo; solo moderadores del proyecto (admin, dueño o rol moderador); queda registrado quién y cuándo; el cliente no ve nada de esto | M |
| 3.5 | Un comentario aprobado se convierte en **entregable** de la subtarea | Ítem con responsable, fecha, estado (Pendiente / En proceso / Listo) y comentarios internos; la subtarea pasa a "Ajustes" mientras haya entregables abiertos; al subir la nueva versión del catálogo que resuelve el ajuste, el entregable se marca "Listo" solo | M |
| 3.6 | Trazabilidad en ambos sentidos | Desde la subtarea se abre la página del catálogo en la región del comentario; desde la revisión, Ogilvy ve un enlace "Entregable en el tablero" en el ajuste | S |
| 3.7 | Avisos | Moderadores reciben aviso (app + correo) cuando llega un comentario por revisar; responsables cuando se les asigna un entregable | S |

Suposiciones: "rol de management" = moderador del proyecto (no creo un rol global nuevo); la aprobación
del ingreso NO cambia el flujo de validación del catálogo (eso sigue como está, con su cronómetro);
solo decide qué entra al trabajo interno de Ogilvy.

Nota de cierre: los entregables no tienen hilo propio de comentarios; la conversación vive en la subtarea de la página (3.5).

## Fuera de alcance (no está en el documento, lo anoto para no perderlo)

- Dependencias entre tareas ("Relacionar elementos o agregar dependencias" aparece en la captura).
- "Sugerir" con IA de la captura de ClickUp: excluido, como se decidió antes.
- Vista de lista/tabla del tablero (además del kanban).

## Orden propuesto

1. Épica 1 (subtareas genéricas): es la base de la 3.
2. Épica 2 (cliente): pequeña e independiente.
3. Épica 3 (backlog + intake de comentarios): reutiliza 1 y 2.

## Épica 4 — Cola de ejecución por prioridad (HECHA · 24 sep 2026)

Origen: audio de la dirección. Cuando el cliente comenta varios proyectos a la vez, Ogilvy no puede
tener todos los cronómetros corriendo. Regla implementada:

- Un ajuste que llega a Ogilvy **no arranca su cronómetro automáticamente**. `AgencyQueue::refresh()`
  mira todo lo pendiente de Ogilvy en catálogos activos y sólo arranca los que pertenecen a la tarea
  de **mayor prioridad** (urgente › alta › media › baja, tomada de la tarjeta del tablero ligada al
  catálogo; sin tarjeta cuenta como media). Los demás quedan **"En cola · Ogilvy"** (estado `espera`,
  sin SLA corriendo, sin alertas).
- Cuando se resuelven los de arriba (nueva versión), la cola vuelve a evaluarse y los siguientes arrancan
  **desde ese momento** (`agency_started_at` = `stage_since` = ahora). Un cronómetro que ya arrancó nunca
  se pausa. Cambiar la prioridad de la tarjeta también reevalúa la cola.
- Se reevalúa en: ajuste creado, validado, resuelto, versión subida, página reabierta como PPD, cambio
  de prioridad de la tarjeta, y cada minuto en `review:check-deadlines`.
- El informe mensual mide la ejecución desde `agency_started_at` (no desde que llegó a la cola).

## Épica 5 — Enlaces en comentarios (HECHA · 24 sep 2026)

Las URLs escritas en comentarios de tarjetas y subtareas se muestran como enlaces (nueva pestaña,
`rel="noopener noreferrer nofollow"`), conviviendo con las menciones `@Nombre`.

## Tiempos: 5 h para todo (24 sep 2026)

Decisión del dueño del producto: todos los plazos (ejecución de Ogilvy por tipo de ajuste, validación de cada eslabón
del cliente y aprobación de página por rol) quedan en **300 minutos hábiles**. Son los nuevos valores por
defecto de `SlaSettings` y también se guardaron en la configuración de producción. Siguen siendo editables
en *Configuración › Tiempos de ajuste*. Los tests de cronómetros fijan los valores antiguos con `prototypeSla()`.

## Épica 6 — Adjuntos en los ajustes del cliente (HECHA · 24 sep 2026)

En el formulario "Nuevo ajuste" de la revisión el cliente puede adjuntar hasta 5 archivos (PDF o imagen,
25 MB c/u) y/o un enlace a recursos. Se guardan en `adjustment_attachments` (archivos en el disco privado
`catalogos/{slug}/ajustes/{id}/`, servidos por `adjustments.attachments.show` a quien pueda ver la página).
Se muestran en el panel de ajustes (miniaturas, chips de PDF y enlaces). Al aprobar el ingreso en el
tablero, los archivos se copian como adjuntos del comentario del cliente en la subtarea y los enlaces van
en el texto (clicables). Al retirar el ajuste se borran sus archivos. Test Pest `AdjustmentAttachmentsTest`
y e2e `adjustment-attachments.spec.ts`.

## Épica 7 — Calendario para el cliente, solo lectura (HECHA · 24 sep 2026)

Los usuarios del cliente (Marketing, Planificación, Comercial) ven *Calendario* en el grupo Catálogos del
menú. Es la misma vista mensual pero: solo proyectos **con cliente asignado**, sin filtro de personas, sin
"Carga del mes por persona", sin enlaces al tablero (una tarjeta ligada a un catálogo lleva al catálogo).
Los tableros, el panel y la lista de proyectos siguen siendo solo de Ogilvy.

## Sectores en dos familias y copy de aprobación (HECHOS · 24 sep 2026)

- **Sectores**: FOOD = Líquidos, DPH, Rancho, PLS, PFT; NON FOOD = Bazar, Electro, Textil. "Food" dejó de ser
  un sector: lo que estaba marcado Food (páginas y usuarios comerciales) pasó a todos los sectores Food. Los
  selectores de sector (nuevo catálogo, editar catálogo, usuario) se muestran por familia. Columna `sectors.group`.
- **Copy**: en la aprobación a imprenta (Comercial → Planificación → Marketing) los dos primeros eslabones ven
  "Aprobar"; solo Marketing, el último, ve "Aprobar a imprenta". Aplica a botones, diálogo, bandeja, alertas y reloj.

## Tipo de ajuste múltiple (HECHO · 24 sep 2026)

Al comentar una región se pueden marcar varios tipos a la vez (chips: Precio, Descripción, Imagen, Ajustes de
diseño, Otro). Columna `adjustments.types` (json); `type` sigue siendo el primero. El SLA de ejecución toma el
mayor de los tipos elegidos; la etiqueta se muestra "Precio + Imagen"; el filtro por tipo encuentra el ajuste
si incluye ese tipo. La API sigue aceptando `type` (uno) o `types` (varios).

## Rol "Ogilvy · Solo lectura" (HECHO · 24 sep 2026)

Pedido de la dirección: gente de Ogilvy que no modera. Nuevo rol `lectura`: ve catálogos, páginas con sus
imágenes, reportes, notificaciones y el calendario (versión de solo lectura), pero no carga catálogos ni
versiones, no envía a revisión, no comenta, valida, aprueba ni responde ajustes, y no entra a Panel ni
Proyectos. Se crea desde Administración › Usuarios como cualquier otro rol. En la página se muestra
"Solo lectura" donde irían los botones de acción.

## Tipos de ajuste de Cencosud (HECHO · 24 sep 2026)

A la tipología (selección múltiple) se sumaron Dinámica, Ingresa Dinámica, Compaginada y Pata. Tiempo por defecto 5 h como el resto; editables en Configuración › Tiempos.

## Notificaciones: sonido y sondeo sin websockets (HECHO · 29 sep 2026)

- **Sonido**: campanita de dos notas generada con Web Audio (sin archivo) al llegar una notificación. Preferencia
  por persona en Mi cuenta (`users.notification_sound`, activada por defecto) con botón "Probar". El navegador
  solo permite sonido tras el primer clic en la pestaña.
- **Sondeo**: cuando no hay Reverb, el navegador consulta `GET /notificaciones/pulso` cada 45 s (solo con la
  pestaña visible y al volver a ella). Si hay una notificación nueva: toast con "Abrir", sonido y actualización
  de la campana y la bandeja. Con Reverb activo se usa el canal en vivo y el mismo sonido.

## Comentarios: responder y editar (HECHO · 30 sep 2026)

En tarjetas y subtareas: **Responder** cuelga la respuesta bajo el comentario (un solo nivel; responder a
una respuesta la deja bajo el original) y avisa al autor del comentario respondido. **Editar** solo en los
propios (no en los que vienen del cliente por la revisión); queda marcado "editado". Si se borra el
original, las respuestas quedan sueltas. Columna `card_comments.parent_id`.
