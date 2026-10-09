# Contexto del proyecto · Revisión de Catálogos (Ogilvy × Cencosud)

Última actualización: 23 sep 2026. Repositorio: `git@github.com:Imderf/catalogos-cencosud.git`, rama `main`.
Documento hermano con el detalle técnico: [ESPECIFICACION-TECNICA.md](ESPECIFICACION-TECNICA.md).
Bitácora cronológica de cada fase: [PLAN.md](PLAN.md). Backlog vivo: [BACKLOG.md](BACKLOG.md).

## 1. Qué es

Plataforma web con la que Ogilvy Colombia y su cliente Cencosud (marcas Jumbo y Metro) revisan los
catálogos impresos (octavillas, cuartillas, volantes) antes de imprenta. Ogilvy sube las páginas del arte
final; el cliente comenta directamente sobre la página marcando regiones; los comentarios ("ajustes")
bajan por la cadena hasta Ogilvy con un cronómetro en minutos laborales; Ogilvy sube versiones corregidas;
el cliente valida y luego aprueba a imprenta. Todo con roles, permisos por sector, alertas por correo y en
la app, reportes y tiempo real.

Desde septiembre de 2026 incluye además una **gestión de proyectos interna de Ogilvy** (tableros tipo
Trello con tarjetas, subtareas, calendario y panel) en la que cada catálogo aparece como una tarjeta que
avanza sola según la revisión, y donde los comentarios del cliente se convierten en trabajo interno
(entregables) previa aprobación de un moderador.

## 2. De dónde viene

- Punto de partida (sep 2026): un prototipo HTML estático `plataforma-catalogos.zip` hecho por Ogilvy con
  la experiencia deseada (revisión sobre la página, cronómetros, cadena de aprobación). Se conserva en
  `docs/prototipo/` como referencia; no tiene backend.
- Segundo insumo (21 sep 2026): `copytrello`, una app Node/Prisma/React de gestión de proyectos de la
  misma agencia. Se pidió traer su funcionalidad (dashboard, proyectos, calendario, clientes) a esta app,
  excluyendo IA, wikis y exportación PPTX, y con el comportamiento de su interfaz (modales, popups,
  animaciones) igual.
- Tercer insumo (22 sep 2026): `Catalogoapp-ajustes.docx`, con tres pedidos: subtareas, ver proyectos por
  cliente y un backlog donde los comentarios del cliente se vuelvan subtareas/entregables con aprobación.

## 3. Decisiones cerradas por el dueño del producto

| Tema | Decisión |
|------|----------|
| Stack | Laravel 12 + Inertia 2 + React 19 + TypeScript + Tailwind 4 + shadcn/ui. PostgreSQL. |
| Hosting | RunCloud en el futuro (sin acceso aún). Hoy solo local y repositorio. Guía en `docs/DEPLOY-RUNCLOUD.md`. |
| Acceso | Login con correo y contraseña asignada por el administrador. Sin SSO ni autorregistro. |
| Clientes y marcas | Un solo cliente, Cencosud, con marcas Jumbo y Metro. |
| Alertas | Por correo y dentro de la app; cada usuario elige "al instante" o "resumen diario". |
| Cadena | Validación ascendente Marketing → Planificación → Comercial; aprobación a imprenta descendente Comercial → Planificación → Marketing. Marketing cierra la página. |
| Post-producción | "Defecto post-producción" solo Marketing y solo con la página cerrada. |
| Ogilvy | Es el primer actor: prepara y envía a Marketing; ejecuta los ajustes. |
| Proyectos | Solo visibles para gente de Ogilvy (admin y agencia). Con campos personalizados. Arrancan vacíos, sin migrar datos de copytrello. |
| Roles en proyectos | Moderador (edita proyecto, listas, miembros, etiquetas, campos, aprueba ingresos) y miembro (trabaja tarjetas). "Management" del documento = moderador. |

## 4. Qué se planeó y qué se hizo

Todo lo planeado está hecho y en `main`. Orden real de construcción:

1. **Fase 0 · Base**: proyecto Laravel + React, Postgres, Mailpit, CI en GitHub Actions, prototipo guardado.
2. **Fase 1 · Acceso**: sesiones, usuarios, roles (admin, agencia, marketing, planificación, comercial),
   sectores (Food, Líquidos, DPH, Bazar, Textil, Electro), administración de usuarios con cambio de
   contraseña obligatorio al primer ingreso.
3. **Fase 2 · Dominio**: catálogos, páginas, versiones, ajustes con región, cadena de aprobación,
   enrutamiento de ajustes, cronómetros en minutos laborales, SLA configurables.
4. **Fase 3 · Pantallas**: lista de catálogos, carga en tres pasos, revisión sobre la página (dibujar
   región, zoom, comparar versiones), panel de ajustes, reportes por catálogo.
5. **Fase 4 · Alertas**: notificaciones en app y correo por cada movimiento, plazos urgentes y vencidos,
   resumen diario, bandeja de pendientes.
6. **Fase 5 · Calidad**: pruebas E2E con Playwright, revisión de seguridad, guía de despliegue.
7. **Post fase 5**: derivados de imagen (1400 px y miniatura), página de salud `/salud` con latidos,
   Sentry opcional, tema claro/oscuro, páginas de error.
8. **Decisiones de producto (18 sep)**: gestión y archivo de catálogos, editar/retirar ajustes propios,
   reporte mensual, actuar fuera de horario (global y por persona), eventos táctiles, resumen diario,
   tiempo real con Reverb, horario semanal con excepciones, configuración unificada.
9. **Flujo nuevo (18–21 sep)**: validar (ida) + aprobar a imprenta (vuelta), preparación en Ogilvy,
   tipo de entregable, pantalla de revisión unificada con riel de iconos y panel de vista general.
10. **Proyectos A/B/C (21 sep)**: clientes, proyectos, tableros con arrastre, tarjeta completa
    (comentarios con menciones, adjuntos, campos personalizados, actividad), catálogo como tarjeta,
    calendario, panel.
11. **Paridad con copytrello (22 sep)**: modal ancho de tarjeta con guardado automático, confirmaciones
    en modal, duplicar con opciones, selector con chips, toasts, filtros del tablero, presencia,
    panel de actividad, comentarios con archivos, campos imagen, logo de cliente.
12. **Épicas del documento de ajustes (22 sep)**: subtareas con estado propio; proyectos por cliente;
    lista Backlog, subtarea por página, bandeja de comentarios del cliente con aprobación y entregables.

## 5. Cómo está hoy

**Funciona y está probado** (156 pruebas Pest, 11 escenarios Playwright, todo en verde):

- Revisión completa de catálogos con dos pasadas por versión, cronómetros, alertas, reportes, tiempo real.
- Administración: usuarios, clientes (con logo), tiempos de ajuste (SLA), horario semanal y excepciones.
- Proyectos: tableros, tarjetas, subtareas, calendario, panel, todo en tiempo real y solo para Ogilvy.
- Catálogo ↔ proyecto: al crear un catálogo se elige el proyecto; la tarjeta avanza sola por Backlog →
  En revisión → Aprobados; cada página es una subtarea automática; cada comentario del cliente llega
  "por revisar" y el moderador lo aprueba como entregable o lo descarta.

**Estado de los datos**: solo hay datos de demostración sembrados (`php artisan migrate:fresh --seed`):
10 usuarios con contraseña `password`, dos catálogos de ejemplo con ajustes, un proyecto de prueba.
Las imágenes de ejemplo viven en `storage/app/seed/paginas/` (ignoradas por git).

**Entorno local del equipo**: Postgres 17 de Homebrew en el puerto 5433 (el 5432 lo ocupa Postgres.app),
Mailpit en 8025, Reverb en 8080, `composer run dev` levanta servidor, cola, logs, Vite y Reverb. Ver README.

**Pendiente o abierto**:

- Despliegue real en RunCloud: no se ha hecho; la guía está lista.
- Las imágenes v2 de demostración traen "retazos" pegados de otra página (están así en el JPG, no es un
  error de la app). Se ofreció regenerarlas; sin decisión.
- El horario de trabajo en la base local quedó abierto 24/7 para pruebas; en producción se configura
  desde Configuración → Horario.
- Fuera de alcance por decisión: invitaciones por correo a proyectos (los usuarios los crea el admin),
  IA, wiki, PPTX, dependencias entre tareas, hilo de comentarios por entregable (la conversación vive
  en la subtarea de la página).

## 6. Quién es quién en la app

| Rol | Organización | Qué hace |
|-----|--------------|----------|
| admin | Ogilvy | Todo: usuarios, clientes, SLA, horario, catálogos, proyectos. |
| agencia | Ogilvy | Carga catálogos, los envía a Marketing, ejecuta ajustes, sube versiones, usa proyectos. |
| marketing | Cencosud | Primera validación, última aprobación a imprenta, defectos post-producción. |
| planificacion | Cencosud | Segunda validación y segunda aprobación. |
| comercial | Cencosud | Tercera validación y primera aprobación, solo en las páginas de sus sectores. |

Usuarios de demostración: `admin@ogilvy.example`, `daniela.pardo@ogilvy.example` (agencia),
`valeria.ortiz@cencosud.example` (marketing), `tomas.rincon@cencosud.example` (planificación),
`hernan.castro@cencosud.example` y otros comerciales por sector. Contraseña: `password`.

## 7. Cómo seguir

- Antes de tocar código: leer la especificación técnica y correr `php artisan test` y `npx playwright test`.
- Cada cambio de producto se anota al final de `docs/PLAN.md`; las ideas nuevas van a `docs/BACKLOG.md`.
- Convención de trabajo: propuesta + aprobación del dueño del producto, luego código con pruebas, verificación en el
  navegador, commit en español y push a `main`.
