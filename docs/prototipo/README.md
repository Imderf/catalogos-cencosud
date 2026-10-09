# Plataforma de Revisión de Catálogos — prototipo funcional

Front de planificación para la plataforma de revisión de catálogos (Ogilvy × Cencosud).
No tiene backend: todo el estado vive en memoria y se reinicia al recargar. Su función es
**definir el comportamiento que después hay que construir en el backend**, con la lógica
ya jugada en pantalla en vez de descrita en un documento.

## Cómo correrlo

```bash
python3 -m http.server 8777
```

Y abrir `http://localhost:8777`. Tiene que ser por servidor, no con doble clic sobre
`index.html`: el navegador bloquea la lectura de píxeles del canvas (la comparación de
versiones) cuando la página se abre como `file://`.

Para el archivo que se le comparte al cliente:

```bash
python3 build.py
```

Genera `dist/Revisión de Catálogos - Ogilvy × Cencosud.html` (~8 MB), autocontenido:
abre con doble clic, sin servidor y sin internet. Ese sí funciona como `file://` porque
las imágenes quedan embebidas como `data:` URI.

## De dónde salen las páginas

Las 16 páginas son las octavillas reales del 18 al 20 de septiembre de 2026, tomadas del
arte final en Box:

- `Catálogos Cencosud / 01.JUMBO / 2026 / 1.OCTAVILLAS / 09. SEPTIEMBRE / 05. OCTAVILLA DEL 18 AL 20 DE SEPT / 07 ARTE FINAL / 01 JPG`
- `Catálogos Cencosud / 02. METRO / 2026 / 01. OCTAVILLAS / 09 Septiembre / 03 Octavilla Metro 18 al 20 de septiembre / 07-ARTE FINAL / 01. JPG`

Los JPG de imprenta (3213×6260 px, hasta 5 MB) se reescalaron a 760 px de ancho para el
prototipo. **Una página puede pertenecer a varios sectores comerciales a la vez**, que es
como funciona de verdad: páginas 1 a 5 **Food**, página 6 **Líquidos · DPH**, página 7
**DPH**, página 8 **Bazar · Textil · Electro**. Un comercial desbloquea la página si
comparte al menos un sector con ella.

`jumbo-p2-v2.jpg` y `metro-p6-v2.jpg` son variantes generadas: la misma página con dos
bloques sustituidos, para que la comparación de versiones tenga cambios reales que
detectar.

## Usuarios de prueba

El login es simulado: se elige el rol en "Acceso de prueba" y se entra. Cada rol ve una
plataforma distinta.

| Usuario | Rol | Categorías |
|---|---|---|
| Daniela Pardo | Agencia (Ogilvy) | todas |
| Valeria Ortiz | Marketing | todas |
| Tomás Rincón | Planificación | todas |
| Hernán Castro | Sector / Comercial | Food |
| Lucía Mora | Sector / Comercial | Líquidos |
| Felipe Arango | Sector / Comercial | DPH |
| Gabriela Soto | Sector / Comercial | Textil |
| Esteban Duque | Sector / Comercial | Electro |
| Mónica Lara | Sector / Comercial | Bazar · Textil · Electro |

Un comercial por sector: Food no es DPH ni Electro, cada uno entra solo a lo suyo.
Mónica Lara es el único caso multi-sector, para mostrar que el modelo lo soporta aunque
no sea lo habitual.

## El modelo, que es lo que hay que llevar al backend

### 1. Cadena de aprobación — sube

`Ogilvy carga → Marketing → Planificación → Sector/Comercial → cerrada`

Cada rol solo ve el botón *Aprobar página* cuando le toca a él. Al aprobar, la página
queda bloqueada para su equipo y avanza al siguiente. Las aprobaciones se guardan **por
versión**: al cargar una versión nueva la cadena vuelve a arrancar desde Marketing.

**Una página nunca está en dos estados a la vez.** Mientras haya un ajuste abierto la
cadena queda *suspendida*: no corre el reloj de aprobación, desaparece *Aprobar página* y
tanto el cronómetro de la izquierda como el distintivo de la tarjeta de aprobación dicen
lo mismo — dónde está la pelota (*En Ogilvy*, *En Planificación*, *En varios equipos*).
La aprobación se reanuda cuando Ogilvy cierra los ajustes.

**Quien ya aprobó, no comenta.** Registrado tu visto bueno sobre una versión, pierdes
*Comentar región* y tu único camino es el Post Production Defect — lo mismo que ocurre
cuando la página ya pasó toda la cadena.

### 2. Ruta del ajuste — baja

El ajuste nace en cualquier nivel y desciende hasta Ogilvy, que es quien lo ejecuta:

- Comercial → Planificación → Marketing → Ogilvy
- Planificación → Marketing → Ogilvy
- Marketing → Ogilvy

Cada eslabón intermedio ve *Validar y enviar* y puede complementar el ajuste con una nota
antes de pasarlo. La ruta completa y quién validó quedan visibles en el ajuste.

### 3. Cronómetros

**Todo el tiempo se mide en minutos laborales.** El horario es L–V de 08:30 a 18:00
(configurable). Fuera de él nadie puede comentar, validar ni aprobar — los botones
desaparecen y aparece el aviso de cuándo se reanuda — y **los cronómetros se congelan**,
porque no tiene sentido cobrarle a nadie un tiempo en el que no estaba trabajando.

Cada página tiene **un solo reloj, y es de quien tenga la pelota**:

| Situación | Reloj | Plazo por defecto |
|---|---|---|
| Ajuste parado en un eslabón del cliente | Validación | 2 h |
| Ajuste ya en Ogilvy | Ejecución, según el tipo | Precio 30 m · Descripción 45 m · Imagen 2 h · Diseño 4 h · Otro 1 h |
| Página sin ajustes, esperando visto bueno | Aprobación del cliente | Marketing 4 h · Planificación 4 h · Comercial 8 h |

Todos los plazos se editan en **Tiempos de ajuste** (solo Ogilvy). La barra muestra el
reloj en vivo y dice de quién es el tiempo ("Marketing valida", "Ogilvy ejecuta",
"Planificación debe aprobar").

**Si el cliente no aprueba dentro de su plazo** la página no se aprueba sola: el reloj
pasa a contar hacia arriba (`+00:15:39`, "Aprobación vencida hace"), la página se marca en
rojo y suma al indicador *Aprobaciones vencidas del cliente* del reporte. Es la métrica
que sostiene la conversación de "el catálogo salió tarde y esta es la razón". Si en la
operación real prefieren aprobación automática por silencio, es un cambio de una línea —
pero para catálogo impreso lo prudente es que nada se apruebe sin que alguien lo mire.

En pantalla es una sola barra sobre el lienzo, sin desglose por ajuste: semáforo, reloj
`HH:MM:SS` en vivo y de quién es el tiempo. El color es el que manda:

- **verde** — queda holgura
- **rojo** — queda menos del 25% del plazo, o ya se pasó (el reloj cuenta hacia arriba)
- **gris** — fuera de horario, todo en pausa

Los mismos contadores corren en vivo en las miniaturas de la rejilla y en cada ajuste de
la lista lateral, todos sobre el mismo reloj.

**Velocidad de demostración (`×1 ×10 ×60`)**, dentro de *Tiempos de ajuste*: el prototipo
corre sobre un reloj virtual (`ahora()`, `this.reloj`, `this.velocidad`), no sobre la hora
del sistema. A ×60 un minuto de SLA pasa en un segundo real, así que en una demo se ve la
barra avanzar, ponerse roja y vencer sin esperar dos horas. Es una ayuda de demostración,
no parte del producto: en el sistema real el tiempo corre ×1 contra el reloj del servidor.

Los tiempos de la tabla son supuestos de arranque: hay que reemplazarlos por los reales
de producción.

### 4. Post Production Defect (PPD)

Una página aprobada por toda la cadena **queda cerrada a comentarios**: desaparece
*Comentar región* y el único camino es el botón *Post Production Defect*. Reabrirla:

- anula las aprobaciones de esa versión y devuelve la página a Marketing,
- crea un ajuste marcado como defecto que va directo a Ogilvy,
- queda contado aparte en el reporte (*Defectos post-producción*) y en el CSV.

Es la métrica de errores de cliente sobre proyecto ya aprobado.

### 5. Comparar versiones

Con dos o más versiones, el lienzo cambia de modo con la pestaña *Comparar* — no abre
ninguna ventana encima, la comparación pasa en el mismo lugar donde se revisa. Dos vistas:

- **Lado a lado**: versión anterior a la izquierda, nueva a la derecha con los cambios
  marcados y numerados.
- **Cortina**: las dos versiones superpuestas con un tirador que se arrastra para revelar
  una sobre otra — la lectura más rápida de "qué se movió aquí".

Un selector permite comparar contra cualquier versión anterior, no solo la inmediata.
La detección es real, por diferencia de píxeles sobre una malla, agrupando celdas
contiguas en regiones (`detectarCambios`, umbral 14 sobre 0–255). Debajo lista los
ajustes que esa versión cerró.

### 6. Categorías y permisos

Un usuario ve **todo el proyecto**, pero solo actúa sobre su categoría asignada. Las demás
páginas salen en gris con el sello "Otra categoría" y sin botones de comentar ni aprobar.

### 7. Carga de un catálogo

El asistente de Ogilvy sube las páginas y les marca los sectores. **Cada página admite
varios**: el mismo pliego suele llevar Bazar, Textil y Electro juntos. No se publica hasta
que toda página tenga al menos uno, porque de ahí salen el conteo del reporte y el permiso
de cada comercial.

### 8. Notificaciones

El menú lateral tiene **Notificaciones** con el contador de lo no leído para el rol de la
sesión. Adentro, dos bloques:

- **Lo que espera por ti**: páginas por aprobar y ajustes por validar o ejecutar,
  ordenados por urgencia y con su cronómetro; un clic lleva directo a la página.
- **Actividad reciente**: qué pasó, quién lo hizo y hace cuánto.

Cada transición de estado emite el aviso al rol que tiene que actuar: un ajuste nuevo
avisa al siguiente eslabón, una aprobación avisa a quien sigue en la cadena, una versión
nueva devuelve la página a Marketing, un PPD avisa a Ogilvy.

## Estructura

```
index.html        toda la aplicación: estilos, plantilla y lógica
assets/           páginas del catálogo, logos y fuentes
vendor/           react, react-dom y el runtime de plantillas (dc-runtime)
build.py          empaqueta todo en un HTML único para el cliente
dist/             salida de build.py
```

## Sistema de diseño

La interfaz sigue el lenguaje de **shadcn/ui + Tailwind** — superficie neutra, borde de
1px, radios suaves, sombras mínimas, tipografía en dos tamaños base — con la paleta de
marca Ogilvy como único color (rojo, negro, grises, blanco) y numeración en JetBrains
Mono. Está implementado con tokens CSS y clases propias en el bloque `<style>` de
`index.html`, no con Tailwind cargado: el prototipo tiene que abrir sin build ni CDN.

Los tokens (`--primary`, `--muted`, `--border`, `--r`, `--sh-sm`…) viven en `:root` y las
clases de componente (`.card`, `.btn`, `.badge`, `.tabs`, `.bar`, `.step`, `.kpi`,
`.dlg`…) justo debajo. La lógica no escribe estilos: arma nombres de clase
(`tabClase`, `chipClase`, `pista`, `ancho`), así que cambiar el aspecto de un componente
es tocar una regla CSS, no sesenta cadenas de estilo.

`index.html` tiene dos mitades: la **plantilla** (HTML con `sc-if`, `sc-for` y `{{ }}`) y
la **lógica** (`class Component extends DCLogic`, al final del archivo). La lógica no toca
el DOM: arma en `renderVals()` un objeto con todo lo que la plantilla necesita — textos,
estilos y handlers — y la plantilla solo lo pinta. Para agregar algo a la pantalla van
las dos partes: el dato en `renderVals`/`catalogoVals`/`modalVals` y el bloque en la
plantilla.

Métodos que concentran el modelo: `rutaAjuste`, `etapaAprob`, `validarAjuste`,
`nuevoAjuste`, `cronoPagina`, `restanteMin`, `tieneCategoria`, `detectarCambios`.

## Qué falta antes de que esto sea producto

- **Los permisos son de pantalla, no de seguridad.** Hoy el rol y la categoría solo
  esconden botones; cualquiera con la consola abierta se los salta. Cuando exista backend,
  la cadena de aprobación y el bloqueo por categoría se validan en el servidor en cada
  operación, y el front queda solo como reflejo.
- **Notificaciones**: hoy el paso de un eslabón a otro se ve en la bandeja del sidebar.
  Falta definir el canal real (correo, Teams) y quién recibe qué.
- **Persistencia**: no hay. Recargar borra todo.
- **Tiempos reales de producción** para reemplazar los sugeridos.
- **Imágenes de versiones**: `assets/p7-v2.jpg`, `p7-v3.jpg` y `p2-v2.jpg` son variantes
  generadas para demostrar la comparación, no material real de Cencosud.
