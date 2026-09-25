<!--
  ESTE ARCHIVO SE ACTUALIZA, NO SE REGENERA DESDE CERO.
  Cada ronda de cambios nueva debe EDITAR este archivo (sumar/corregir lo que
  cambió), no reescribirlo entero ni borrar el historial de "errores ya
  cometidos" de más abajo. Si una sesión nueva no está segura de si algo acá
  sigue vigente, tiene que verificarlo contra el código real antes de confiar
  en lo que dice este archivo (ver la sección de errores ya cometidos: por
  eso existe esta regla).
-->

# MEMORY — Sistema de gestión "El Batará"

## Qué es el proyecto

Sistema de gestión (POS + stock + caja + reportes) para **"El Batará"**, un
almacén de campo + resto en San Antonio de Areco, Buenos Aires, Argentina.
Lo usan mozos/empleados de mostrador (con poco tiempo, en celular/tablet) y
el dueño (que necesita reportes y control de caja).

## Stack y restricciones no negociables

- **PHP puro, sin frameworks** (procedural por archivo, sin router, sin ORM).
- **MySQL / MariaDB** (compatible con MySQL 5.7/8.0 según `database.sql`;
  probado en la práctica contra MariaDB 10.4 de XAMPP).
- **Sin Composer, sin npm, sin build step.** Todo el JS/CSS es archivo suelto
  servido directo. Bootstrap 5.3.3 y Chart.js están vendorizados
  (`assets/vendor/bootstrap-5.3.3/`, `assets/js/chart.umd.min.js` desde la
  ronda 8) — nota corregida en ronda 14, esta línea decía antes que
  Bootstrap seguía por CDN "sin vendorizar todavía" y ya no era cierto.
  La única dependencia externa real hoy es la tipografía (Google Fonts,
  Fraunces + Work Sans, sumada en la ronda 14 vía `<link>` en
  `includes/header.php`) — deliberadamente no vendorizada por tiempo; si
  el local tiene internet inestable, el fallback de la cadena de fuentes
  en el CSS deja todo legible y funcional igual, sólo cambia la tipografía.
- **Pensado para hosting compartido de Hostinger sin acceso SSH.** Por eso
  existen dos árboles de código (ver abajo) y por eso las migraciones se
  corren pegando SQL en phpMyAdmin, no con una herramienta de migraciones.
- PDO con `ERRMODE_EXCEPTION` + `EMULATE_PREPARES => false`, siempre prepared
  statements con placeholders `?`.

## Estructura de carpetas (resumen — detalle completo en `RELEVAMIENTO.md`)

```
_dev_no_subir/          # árbol canónico de desarrollo (config/includes FUERA de public_html)
DEPLOY_HOSTINGER/       # árbol de deploy (config/includes DENTRO, mismo nivel que las pantallas)
database.sql            # schema completo + seeds, para instalación nueva
migracion_ronda6.sql     # migración suelta para bases ya en producción (ronda 6)
migracion_ronda8.sql     # migración suelta para bases ya en producción (ronda 8)
INSTALL.md               # guía de instalación detallada
```

Los dos árboles deben mantenerse en contenido idéntico salvo la profundidad
de los `require __DIR__ . '/../includes/...'` (uno o dos `../` según el
árbol). **No existe ningún script automatizado que verifique esto** — hay que
sincronizar a mano y volver a diffear cada vez que se toca algo en
`public_html/`.

## Convenciones del proyecto (verificadas en el código real, no supuestas)

- Paleta de marca: `#8B2E2E` (marrón-rojizo) + tonos crema, en
  `assets/css/style.css`.
- Nombres de funciones/variables en español (`obtenerConexion`,
  `requerirLogin`, `formatearMoneda`, `generarTokenCsrf`, etc.).
- `h($valor)` para **todo** output que venga de datos (htmlspecialchars).
- `validarTokenCsrf()` en todo POST que renderiza HTML; los endpoints AJAX
  que devuelven JSON (`pedidos/agregar_item.php` y similares) reimplementan la
  misma verificación a mano porque necesitan responder JSON en vez de morir
  con texto plano — es duplicación conocida, no un bug.
- `ejecutarTransaccion(PDO $pdo, callable $fn)` (agregado en `includes/functions.php`
  en la ronda 8): begin/commit/rollback automático. **Todavía no es el
  patrón usado en todos lados** — hoy solo lo usan `pedidos/cerrar.php` y
  `caja/cerrar.php`. El resto de los endpoints con transacción
  (`pedidos/agregar_item.php`, `stock/reponer.php`) siguen con
  `beginTransaction()`/`commit()`/`rollBack()` manual. Si tocás alguno de esos
  archivos, es un buen momento para migrarlo a `ejecutarTransaccion()`, pero
  no asumas que ya está migrado.
- `intPositivoONull()`, `exportarCsv()`, `formatearMoneda()`,
  `formatearCantidad()` en `includes/functions.php`, reutilizadas en todo el
  sistema.
- Patrón de reporte estándar: filtro `desde`/`hasta` por GET, validación con
  `preg_match('/^\d{4}-\d{2}-\d{2}$/', ...)` (duplicada en 6 archivos, ver
  `RELEVAMIENTO.md` sección 5), botón CSV (`exportarCsv()`) y botón imprimir
  (`window.print()` + clase `no-imprimir`).
- Patrón ABM simple (categorías, medios de pago, categorías de egreso): tabla
  editable inline + form de alta, sin paginación (listas cortas).

## Estado actual, una línea por funcionalidad

- **Login/sesión**: funciona, bcrypt + CSRF + cookies seguras. Límite de 5
  intentos fallidos por CUENTA (bloqueo 5 min, `includes/auth.php`) — nota
  corregida en ronda 13, esta línea decía antes "sin límite de intentos" y ya
  no era cierto. Sigue sin límite global por IP (fuera de alcance).
- **Usuarios (ABM)**: pantalla desde la ronda 16 (`usuarios/listar.php`,
  requiere el permiso `gestionar_usuarios`) — alta/edición de nombre,
  usuario, **perfil** (desde la ronda 17, ver abajo — ya no "rol") y
  activo, más cambio de contraseña por quien gestiona usuarios sin pedir
  la actual. Antes de la ronda 16 no había forma de crear un usuario nuevo
  sin tocar la base a mano.
- **Perfiles (ABM)**: pantalla nueva desde la ronda 17
  (`perfiles/listar.php`, requiere el permiso `gestionar_perfiles`) —
  reemplaza el ENUM fijo `rol` ('admin'/'empleado') por perfiles armados a
  mano con 9 permisos independientes (`ver_caja`, `ver_reportes`, y un
  `gestionar_*` por cada ABM de Configuración). Cada usuario tiene un
  `perfil_id`; `esAdmin()`/`requerirAdmin()` ya no existen, todo el
  sistema chequea `tienePermiso('<permiso>')`. Salón/POS, Stock y Egresos
  siguen sin gate de permiso (decisión explícita, no un permiso
  faltante).
- **POS / pedidos**: flujo completo abierto→cuenta_pedida (opcional)→cerrado
  con cancelación, probado. La condición de carrera en stock de
  `agregar_item.php` (nota vieja de esta sección) ya estaba resuelta con
  `SELECT ... FOR UPDATE` antes de la ronda 13 — corregido acá también,
  esta línea quedó desactualizada. Desde ronda 13: tocar el mismo producto
  varias veces suma cantidad en la misma línea en vez de duplicarla, el
  carrito tiene stepper +/- para productos por unidad, cancelar pide motivo
  obligatorio, y no se puede crear ni cobrar un pedido sin una caja abierta.
  Desde ronda 20, el paso intermedio es "Pedir la cuenta" (`estado =
  'cuenta_pedida'`), no "Enviar a cocina"/"Marcar entregado" — ver "Ronda 20"
  más abajo, ese flujo viejo de dos pasos se eliminó del todo (el dueño no
  lo usaba).
- **Salón/mesas**: funciona, sin N+1, pinta estado real del pedido asociado
  (Libre/Ocupada/Cuenta pedida, este último un estado real y alcanzable
  desde ronda 20 — antes de esa ronda el badge "Cuenta pedida" existía
  visualmente pero ningún código lo poblaba, ver bug corregido en "Ronda 20").
  Desde ronda 9 la mesa placeholder "Para Llevar" (capacidad 0) no se
  muestra en la grilla — ver bug corregido más abajo.
- **Canal mostrador/mesa**: funciona (ronda 8). Acceso rápido a Egresos/Ingresos y egresos sumado al dashboard en ronda 9.
- **Medios de pago dinámicos**: funciona (ronda 8), ABM completo. Desde
  ronda 13 el medio "efectivo físico de caja" se identifica con la columna
  `medios_pago.es_efectivo` (elegible desde una tarjeta nueva en
  Medios de pago), no por el texto del nombre — la nota vieja de esta
  sección decía que esto era frágil, ya no lo es.
- **Stock**: ingresos + historial por producto funcionan. Sin ABM de ajustes manuales negativos.
- **Caja**: abrir/cerrar/historial funcionan, cálculo recalculado en ronda 8
  (probado con diferencia $0 real) y ahora basado en `es_efectivo` (ronda
  13). Desde ronda 13, cerrar caja pide confirmación y muestra la
  diferencia en vivo mientras se tipea el monto contado, y
  `caja/historial.php` (y su acceso del sidebar) pasó a ser solo-admin —
  cualquier empleado sigue pudiendo abrir/cerrar SU turno desde accesos
  directos en el dashboard, pero ya no ve el historial financiero completo.
  Columnas legadas `total_tarjeta`/`total_transferencia` quedan NULL en
  cierres nuevos, a propósito.
- **Egresos**: módulo nuevo completo (ronda 8) — carga rápida, listado con
  filtro y paginación propia (`egresos/listar.php` implementa la suya, no
  usa los helpers de `includes/paginacion.php`).
- **Reportes** (ventas, productos top, medios de pago, auditoría, ingresos y egresos): todos funcionan, con filtro de fecha + CSV + impresión. Ingresos y egresos es el más completo (canal + medio + categoría + balance neto + 2 gráficos). Desde ronda 13, ventas/productos top/medios de pago muestran un estado "sin datos" en vez de un gráfico Chart.js en blanco cuando el rango filtrado no tiene resultados.
- **Productos/categorías (ABM)**: funcionan. `productos/listar.php` **ya
  tenía paginación real** (`COUNT` + `LIMIT/OFFSET` vía
  `includes/paginacion.php`) y filtro por categoría/texto antes de esta
  ronda — la nota vieja de esta sección decía "sin paginación ni filtro" y
  estaba desactualizada, corregida acá.
- **Navegación (`includes/header.php`)**: **reemplazada en la ronda 11** —
  ya NO es una navbar horizontal con dropdown. Ver la sección "Ronda 11" más
  abajo para el detalle completo. (Nota histórica: en ronda 9 se había
  agrupado Categorías/Mesas/Medios de pago/Categorías de egreso en un
  dropdown "Configuración" dentro de esa navbar horizontal — ese dropdown ya
  no existe como tal, pero el mismo agrupamiento de ABMs sigue vivo como
  sección "Configuración" del sidebar nuevo, ahora también con Productos
  adentro.)

## Deuda técnica y pendientes conocidos (resumen — detalle en `RELEVAMIENTO.md` sección 10)

- Sin tests automatizados, sin script de pruebas versionado.
- Sin límite de intentos de login por IP (sí hay por cuenta, ver arriba), sin cabeceras HTTP de seguridad (CSP, X-Frame-Options, etc.).
- Bootstrap sigue por CDN (Chart.js ya se vendorizó en ronda 8).
- CSRF-para-JSON duplicado en `pedidos/agregar_item.php` y `pedidos/quitar_item.php` (reimplementan la verificación a mano en vez de usar `validarTokenCsrf()`, porque necesitan responder JSON en vez de la página de error de la ronda 13). Ya NO hay validación de fecha duplicada: los 8 archivos que la usaban centralizaron en `obtenerFechaGet()` en la ronda 13.
- Precios/productos marcados `revisar`/`PENDIENTE` en `database.sql` (líneas ~350/420/450, buscar `-- revisar` y `-- PENDIENTE`) y falta la categoría "Embutidos curados en grasa de cerdo" (foto ilegible al cargar datos).
- Migraciones pendientes de verificar contra la base real de Hostinger antes de cada deploy (no se puede chequear desde este entorno) — sumar `migracion_ronda13.sql` a la lista de migraciones a correr si faltan.
- **Ronda 13 (2026-09-20) no se validó con ejecución real**: esta sesión no tenía PHP ni MySQL/MariaDB disponibles en el entorno (a diferencia de rondas 8-12b) — se buscó `php.exe` en las rutas conocidas, en todo `C:\`, y Docker/WSL como alternativa, sin encontrar nada utilizable. Todos los cambios de esa ronda se hicieron con revisión manual exhaustiva del código (lectura completa de cada archivo tocado, balance de llaves/paréntesis por script, sin `php -l` real) pero **sin levantar el servidor de prueba ni correr el flujo end-to-end**. Antes de subir a producción, correr la checklist completa de la sección de abajo contra un entorno real.
- Deuda ya identificada en `RELEVAMIENTO.md` (ronda 8) que la ronda 9 **no tocó a propósito** por no ser el foco pedido (UX/copy/lógica de UI, no seguridad ni arquitectura): Bootstrap sigue por CDN. (Las otras 3 cosas que esta línea mencionaba — límite de login, `productos/guardar.php` silencioso, condición de carrera en `agregar_item.php` — ya estaban resueltas antes de la ronda 13, ver arriba.)

## Cómo se prueba este proyecto

- **Entorno real usado**: XAMPP instalado en la máquina de desarrollo —
  PHP 8.3 CLI (`C:\php2\php.exe`, ojo que NO es el PHP de XAMPP sino uno
  aparte) y MariaDB 10.4 de XAMPP (`C:\xampp\mysql\bin\`). Docker está
  instalado pero **no hay `docker-compose.yml` en este repo** — no asumas que
  existe un entorno Docker armado salvo que lo veas en disco.
- **Cómo se levantó la prueba en la ronda 8** (repetible):
  1. Arrancar un `mysqld` de XAMPP con un datadir descartable propio (no el
     datadir real de XAMPP, para no tocar otras bases locales), en un puerto
     libre (se usó 3308 porque 3306 estaba ocupado por un contenedor Docker
     de otro proyecto).
  2. Crear una base nueva e importar `database.sql` entero.
  3. Levantar `php -S 127.0.0.1:PUERTO -t _dev_no_subir/public_html` (server
     built-in de PHP).
  4. Editar temporalmente `_dev_no_subir/config/config.php` (después de
     respaldar el original) para apuntar a esa base de prueba — **nunca
     tocar `DEPLOY_HOSTINGER/config/config.php` sin respaldarlo primero: tiene
     credenciales reales de producción de Hostinger.**
  5. Simular el flujo con `Invoke-WebRequest` de PowerShell manteniendo una
     sola sesión (**ojo**: las variables de PowerShell no persisten entre
     llamadas separadas de la herramienta de shell — todo el flujo de un
     mismo login/sesión tiene que ir en un solo bloque de comandos).
  6. Verificar los números resultantes directo contra la base con `mysql.exe`.
  7. Restaurar `config.php` al original y matar los procesos de prueba
     (`mysqld`, `php -S`) al terminar.
- **Checklist end-to-end mínima antes de dar una ronda por terminada**:
  - [ ] Login con usuario admin y con usuario empleado.
  - [ ] Abrir caja con un monto inicial.
  - [ ] Crear un pedido de mostrador ("para llevar"), agregar un ítem, cobrarlo con un medio de pago.
  - [ ] Crear un pedido de mesa, agregar un ítem, cobrarlo con OTRO medio de pago.
  - [ ] Si se tocó egresos: cargar un egreso en efectivo con la caja abierta.
  - [ ] Cerrar la caja y verificar a mano que "efectivo esperado" = inicial + ventas efectivo − egresos efectivo, comparando contra lo que quedó en la base.
  - [ ] Revisar cualquier reporte tocado con datos reales del período de prueba (números, no solo que la página cargue con 200).
  - [ ] Probar un POST con CSRF token inválido y confirmar que da 403.
  - [ ] `php -l` sobre todos los archivos tocados, en AMBOS árboles.
  - [ ] Diff manual entre `_dev_no_subir/public_html` y `DEPLOY_HOSTINGER` (no hay script todavía) para confirmar que quedaron sincronizados.
- **Capturas de pantalla reales (agregado en ronda 9)**: no hay herramienta de
  browser/screenshot dedicada en este entorno, pero Edge sí soporta el
  protocolo DevTools (CDP) en modo headless. Con la app levantada (`php -S`)
  y una sesión logueada por `Invoke-WebRequest` (de ahí se saca la cookie
  `PHPSESSID`), se puede: 1) levantar
  `msedge.exe --headless=new --remote-debugging-port=PUERTO --user-data-dir=TEMP`,
  2) crear una pestaña vía `PUT http://127.0.0.1:PUERTO/json/new`, 3) conectar
  por WebSocket a su `webSocketDebuggerUrl`, 4) `Network.setCookie` con el
  PHPSESSID ya autenticado (evita tener que automatizar el formulario de
  login), 5) `Page.navigate` + esperar `Page.loadEventFired` + `Page.captureScreenshot`
  por cada pantalla. Da PNGs reales que se pueden leer con la herramienta
  `Read` y mostrar en la conversación. Ojo: `ReceiveAsync` de
  `ClientWebSocket` hay que loopearlo hasta `EndOfMessage` (los mensajes de
  CDP superan un solo frame) y conviene NO habilitar `Network.enable` si no
  hace falta (satura la cola de eventos con requests de CDN irrelevantes).

## Ronda 9 — skills externas evaluadas y usadas

El usuario pidió instalar y usar 7 skills externas de GitHub para pulir UX/UI
y lógica. Se clonaron y leyeron las 7 antes de tocar código (ninguna se
ejecutó a ciegas). Resultado:

- **Usadas (aplicadas a mano, sin instalar nada)**: `humanizer` y `stop-slop`
  (github.com/blader/humanizer, github.com/hardikpandya/stop-slop) — ambas
  son un único `SKILL.md` de texto plano, sin scripts ni dependencias, con
  reglas para sacar "tics de IA" de un texto. Se usaron como checklist manual
  al revisar copy visible del sistema.
- **Reemplazada por una skill nativa equivalente**: `ui-ux-pro-max-skill`
  (github.com/nextlevelbuilder/ui-ux-pro-max-skill) coincide con la skill
  `ui-ux-pro-max` ya disponible de forma nativa en este entorno — se usó esa
  en vez de clonar/instalar la de GitHub.
- **Descartadas, no compatibles con este proyecto**:
  - `anbeime/skill`: no es una skill, es un marketplace de ~80 skills sin
    relación (generación de video, PPT, un "compañero virtual" que manda
    mensajes por WhatsApp/Telegram con Node.js). Nada aplica.
  - `Leonxlnx/taste-skill`: su propio `SKILL.md` dice explícitamente que es
    para "landing pages, portfolios, redesigns — not dashboards, not data
    tables, not multi-step product UI". El Batará es justamente lo que
    excluye, y asume stack React/Next.js/Tailwind v4/GSAP que este proyecto
    no tiene ni va a sumar (viola la restricción "sin build tools").
  - `pbakaus/impeccable`: instala descargando un binario compilado propio a
    `~/.impeccable/bin/` y corre hooks automáticos en cada edición. No se
    ejecutó un binario de terceros no verificado contra este proyecto.
  - `Egonex-AI/Understand-Anything`: plugin pesado de Claude Code (pipeline
    multi-agente, se instala vía marketplace, genera un grafo, puede bajar un
    visor con `npx`). No hacía falta: `RELEVAMIENTO.md` (ronda 8) ya cubre el
    mapeo completo del sistema real.
- Si una sesión futura ve alguno de estos 7 nombres mencionado otra vez,
  este es el veredicto ya evaluado — no hace falta re-clonar y releer todo
  de cero salvo que el usuario pida específicamente reevaluar alguna.
- **Adenda ronda 11**: el usuario volvió a pegar la misma lista de 7 repos
  "por si la necesitaba a mano", sin pedir reevaluar ninguna en particular.
  Se aplicó el mismo veredicto de arriba sin reclonar ni releer nada
  (humanizer + stop-slop como checklist manual de copy, `ui-ux-pro-max`
  para criterio de UI, las otras 4 siguen descartadas por los motivos ya
  documentados). Se deja esta línea por ronda porque el usuario pidió
  explícitamente que quede registrado cada vez que se repite el criterio,
  no solo la primera.

### Cambios aplicados en la ronda 9

- **Nav (`includes/header.php` + `style.css`)**: se agrupó Categorías/Mesas/Medios
  de pago/Categorías de egreso en un dropdown "Configuración" (antes eran 4
  ítems sueltos que hacían desbordar la barra) y se subió el contraste de los
  links inactivos (`rgba(255,255,255,.55)` default de Bootstrap era casi
  ilegible sobre `#8B2E2E`).
- **Dashboard**: se agregaron accesos directos a "Cargar egreso" e "Ingresos y
  egresos" (existían en el menú pero no en los accesos rápidos de Inicio,
  a diferencia de Salón/Stock/Caja/Productos).
- **Bug real corregido (confirmado con el usuario en el resumen de esta
  ronda, no aplicado en silencio)**: `mesas/salon.php` mostraba la mesa
  placeholder "Para Llevar" (capacidad 0, creada en rondas anteriores como
  workaround) como una tarjeta más del salón. Si alguien la tocaba en vez de
  usar el botón "Para llevar" de arriba, el pedido se creaba con `mesa_id`
  seteado → `canal` quedaba en `'mesa'` aunque conceptualmente era para
  llevar, ensuciando el reporte de canal de la ronda 8. Se excluyó esa mesa
  de la grilla (`WHERE capacidad > 0`); el botón "Para llevar" sigue siendo
  el único camino y ya arma el canal correcto. Verificado con un pedido real
  de punta a punta: `canal = 'mostrador'`.
- Validado con capturas de pantalla reales (Edge headless vía CDP,
  `Page.captureScreenshot`) antes/después de Dashboard y Salón, más flujo
  HTTP completo end-to-end (login, ABM nuevas rutas, pedido para llevar →
  cobro → verificación de `canal` en la base, CSRF inválido → 403).

## Ronda 10 — pulido visual de caja, reportes, ABMs, egresos y ticket

Continuación de la ronda 9 con el mismo criterio (skill nativa `ui-ux-pro-max`
+ checklist manual humanizer/stop-slop para copy visible). Entorno de prueba
levantado igual que en rondas anteriores (mysqld descartable puerto 3308 +
`php -S` + Edge headless vía CDP), con datos reales de prueba: caja abierta,
un pedido mostrador cobrado en efectivo, un pedido de mesa cobrado con QR, un
egreso en efectivo, y luego dos cierres de caja de prueba (uno con sobrante,
uno con faltante) para validar visualmente ambos casos del cambio de abajo.

### Cambios visuales aplicados (ambos árboles)

- **`caja/historial.php`**: la columna "Diferencia" solo pintaba de rojo
  (`text-danger`) el texto cuando había faltante, y un sobrante o un cierre
  exacto se veían como texto plano indistinguible — justo el punto que pidió
  esta ronda ("buen contraste de color, no solo texto"). Ahora es un badge:
  verde con `+$X sobra` para sobrante, rojo con `−$X falta` para faltante, y
  gris `Exacto` cuando la diferencia es $0. Verificado con captura real de
  los tres casos.
- **Paleta de marca en los gráficos Chart.js**: `reportes/ventas.php` usaba
  azul de Bootstrap (`#0d6efd`) para la barra; `medios_pago.php`,
  `productos_top.php` e `ingresos_egresos.php` (sus 3 gráficos de torta) no
  fijaban colores y quedaban con la paleta default de Chart.js (azul/rosa
  genéricos), desentonando con el resto de la app. Se agregó una constante
  `PALETA_GRAFICOS` (mismos 8 colores en los 4 archivos, en tonos de la
  marca: `#8B2E2E`, `#c9a24b`, `#4f7a6b`, `#7a6a58`, `#b85c5c`, `#3f6b8a`,
  `#a8763e`, `#6f4e7c`) y se aplicó a los `backgroundColor` de cada dataset.
  El gráfico de barras de `ventas.php` (una sola serie) usa un solo color
  (`#8B2E2E`), no la paleta completa, porque no es categórico.
- **`reportes/auditoria_pedidos.php` sin CSV ni imprimir**: de los 5 reportes,
  este era el único sin el bloque estándar de acciones (`⬇ Exportar CSV` /
  `🖨 Imprimir`) ni la clase `no-imprimir` en su formulario de filtro — quedó
  afuera cuando se agregó el patrón en la ronda 8. Se sumó el export CSV
  (mismas columnas que la tabla en pantalla) y el botón imprimir, con el
  mismo layout `d-flex` que los otros 4 reportes.
- **`reportes/productos_top.php` seguía cargando Chart.js por CDN**
  (`cdn.jsdelivr.net/npm/chart.js@4.4.4`) en vez del archivo vendorizado
  (`assets/js/chart.umd.min.js`) que ya usan los otros 3 reportes con
  gráfico desde la ronda 8 — MEMORY.md decía que "los tres reportes que lo
  usan" ya apuntaban al local, pero en realidad son 4 reportes con gráfico y
  a este se le pasó por alto. Corregido para que los 4 sean consistentes.
- **`mesas/listar.php`**: título decía "Mesas (ABM)", único de los 4 ABMs con
  ese sufijo (los otros dicen "Categorías", "Medios de pago", "Categorías de
  egreso" a secas) — se sacó el "(ABM)". La columna "Activa" usaba un
  checkbox suelto en vez del `form-check form-switch` que usan los otros 3
  ABMs (categorías, medios de pago, categorías de egreso) para el mismo
  campo — ahora es un switch visual consistente.
- **`pedidos/ticket.php`**: los botones "Imprimir"/"Volver al salón" eran
  `<button>` sin ningún estilo (grises, default del navegador), únicos en
  todo el sistema sin la paleta de marca, a pesar de que el ticket es la
  pantalla cara al cliente. Se les dio un estilo simple en línea (sin
  Bootstrap, porque `ticket.php` es HTML standalone) con los colores de
  marca: "Imprimir" sólido `#8B2E2E`, "Volver al salón" outline.
- **No se tocó `pedidos/nuevo.php` (POS) más allá de sincronizar su
  contenido entre árboles** (ver bug de encoding abajo): se revisó a fondo
  (grilla de productos, chips de categoría, buscador, panel de pedido,
  botones superiores) tanto en desktop como en viewport de tablet (820px) y
  no se encontraron problemas de jerarquía visual o legibilidad que
  ameritaran cambios — la pantalla ya está bien resuelta de rondas
  anteriores. Se decide explícitamente no forzar cambios cosméticos sin una
  razón concreta encontrada, tal como permitía el pedido de esta ronda.

### Bug real encontrado y corregido (separado de lo anterior, no es estilo)

**Corrupción de encoding en 13 archivos PHP de `DEPLOY_HOSTINGER`**, todos de
la "ronda 8" (`caja/cerrar.php`, `caja/historial.php`, `egresos/categorias.php`,
`egresos/listar.php`, `egresos/nuevo.php`, `medios_pago/listar.php`,
`pedidos/cerrar.php`, `pedidos/nuevo.php`, `pedidos/ticket.php`,
`reportes/auditoria_pedidos.php`, `reportes/ingresos_egresos.php`,
`reportes/medios_pago.php`, `reportes/ventas.php`): tenían BOM UTF-8 al
principio del archivo y, en 11 de los 13, texto visible doblemente codificado
("categoría" → "categorÃ­a", "período" → "perÃ­odo", etc.), mientras que sus
equivalentes en `_dev_no_subir/public_html` estaban limpios. Es exactamente
el mecanismo de corrupción ya documentado en la lección de "Get-Content sin
-Encoding UTF8" de la ronda 9, pero afectando archivos que en su momento no
se habían revisado con una captura de pantalla real del árbol de deploy —
solo se había verificado la sincronización de contenido, no el encoding
byte a byte. Se corrigió reescribiendo esos 13 archivos en `DEPLOY_HOSTINGER`
a partir del contenido limpio de `_dev_no_subir/public_html` (copia byte-exacta
+ ajuste de la profundidad de `require`, sin pasar por ningún pipeline de
texto de PowerShell), y se barrió el resto de ambos árboles completos en
busca de BOM o el patrón "Ã"/"Â" — no quedó ninguno. **Confirmado con el
usuario en este resumen, no aplicado ni reportado en silencio.**

### Validación de esta ronda

- Capturas antes/después reales (Edge headless vía CDP) en
  `_test_env/shots/` (`before_*.png` / `after_*.png`): `caja_historial` (los
  3 casos de diferencia), `rep_auditoria`, `rep_productos_top`,
  `rep_medios_pago`, `rep_ingresos_egresos`, `rep_ventas`, `abm_mesas`,
  `ticket`, más capturas de referencia sin cambios (`dashboard`, `login`,
  `abm_productos`, `abm_categorias`, `abm_egreso_cats`, `egresos_nuevo`,
  `egresos_listar`, `cambiar_password`, `caja_abrir`, `pos_desktop`,
  `pos_tablet`, `salon`). La carpeta `_test_env/` es descartable (no
  versionada) y queda en el working tree para que el usuario pueda revisar
  las capturas; no afecta al repo.
- `php -l` corrido sobre los 15 archivos tocados en ambos árboles, sin
  errores, y además sobre el árbol `DEPLOY_HOSTINGER` completo (todas las
  rutas) para descartar que el fix de encoding rompiera algo.
- Diff normalizado (ignorando solo la profundidad de `../` en los
  `require`) entre los 15 archivos tocados en `_dev_no_subir/public_html` y
  `DEPLOY_HOSTINGER`: contenido idéntico. Barrido completo de ambos árboles
  sin BOM ni mojibake restante.
- Checklist end-to-end: login admin y empleado con `123456` (ambos OK),
  abrir caja, pedido mostrador cobrado en efectivo, pedido de mesa cobrado
  con QR/Mercado Pago, egreso en efectivo, dos cierres de caja (sobrante y
  faltante, ambos verificados visualmente con el badge nuevo), reportes
  revisados con estos datos reales (no solo carga de página), POST con
  CSRF inválido → 403 confirmado.
- `config.php` restaurado al placeholder original al terminar, procesos de
  prueba (`mysqld`, `php -S`, Edge headless) confirmados terminados.

## Ronda 11 — navbar horizontal reemplazada por sidebar lateral fijo

Mismo criterio de rondas anteriores para copy/UI (checklist manual
humanizer/stop-slop + skill nativa `ui-ux-pro-max`; ver "Ronda 9" para el
veredicto completo de las 7 skills externas evaluadas — esta ronda no
reevaluó ninguna, se reusó ese mismo veredicto tal como pidió el usuario).

### Qué se hizo

Se reemplazó la navbar horizontal de `includes/header.php` (con el dropdown
"Configuración" de la ronda 9) por un **sidebar lateral**, en ambos árboles.
Cambios en `includes/header.php` y `assets/css/style.css` únicamente (no
hizo falta tocar `footer.php`: el `</div>` que cierra el `container-fluid`
ya alcanzaba).

- **Estructura del sidebar**: logo arriba, accesos principales (Inicio,
  Salón, Stock, Caja, Egresos) sueltos, y dos grupos plegables (solo admin):
  "Reportes" (los 5 reportes de siempre) y "Configuración" (Productos,
  Categorías, Mesas, Medios de pago, Categorías de egreso — **Productos se
  movió acá desde la navbar vieja**, donde vivía suelto; ahora las 5
  pantallas de ABM quedan juntas, que es lo que pidió esta ronda). Card de
  usuario + "Cambiar contraseña"/"Salir" al pie.
- **Ítem activo**: antes la navbar vieja no marcaba ninguna pantalla activa
  (ni con la navbar de Bootstrap por defecto). El sidebar nuevo sí: compara
  `SCRIPT_NAME` contra la ruta relativa de cada link (`$scriptActualSidebar`
  en `header.php`) y pinta el link activo con fondo blanco translúcido +
  barra lateral blanca (`.sidebar-nav .nav-link.active`). Los grupos
  "Reportes"/"Configuración" se auto-expanden si la pantalla activa está
  adentro (`$grupoReportesActivo` / `$grupoConfigActivo`).
- **Breakpoint elegido: 768px (`md` de Bootstrap)**, no 992px (`lg`). Con
  `lg` el sidebar fijo solo aparecería desde ~992px, y una tablet en
  vertical (820px, el tamaño de prueba pedido) hubiera caído del lado
  "mobile" (offcanvas) — pero el pedido es justo que la tablet tenga el
  sidebar fijo (con opción de colapsar), no que se comporte como celular.
  Con `md`, 820px ya cae del lado "sidebar fijo".
- **Mecanismo**: `#sidebar` usa la clase responsive de Bootstrap
  `offcanvas-md` (no un componente custom) — por debajo de 768px es un
  offcanvas real (oculto, capa completa al abrirse, con backdrop, cerrado
  con hamburguesa propia en `.mobile-topbar`); desde 768px, Bootstrap lo
  resetea a `width:auto!important` y estático, así que `style.css` le pone
  `position:fixed` + ancho propio con `!important` sobre un selector con ID
  (`#sidebar.offcanvas-md`) para poder ganarle a esa regla nativa de
  Bootstrap (ver comentario en el CSS). El botón "☰" de `.mobile-topbar` es
  un ícono propio (3 `<span>`) en vez del `.navbar-toggler-icon` de
  Bootstrap, para no depender de la variable de color que trae ese ícono
  por defecto.
- **Colapsable a solo íconos desde 768px (decisión de trade-off para
  tablet/POS, ver más abajo)**: botón "«" en el header del sidebar, clase
  `sidebar-collapsed` en `<body>` que reduce el ancho de
  `--sidebar-width` (226px) a `--sidebar-width-collapsed` (64px), oculta
  etiquetas de texto y deja los `title="..."` de cada link como tooltip
  nativo. Estado persistido en `localStorage` (`elbatara_sidebar_colapsado`)
  y aplicado con un `<script>` síncrono al arranque del `<body>` para que no
  parpadee. Los submenús "Reportes"/"Configuración" se ocultan en modo
  colapsado (no hay forma clara de mostrar varios ítems bajo un solo ícono
  de grupo sin volver a abrir el sidebar) — se accede a ellos expandiendo de
  nuevo con el mismo botón.
- **Paleta**: sidebar con `background-color: var(--marca-principal)`
  (`#8B2E2E`) y texto `rgba(255,255,255,.88)` normal / `#fff` en
  hover-activo — mismo nivel de contraste que ya se había subido en la
  ronda 9 para la navbar vieja (no se repitió el error de dejar el default
  apagado de Bootstrap).

### El trade-off del POS en tablet (caso pedido explícitamente)

Con el sidebar fijo de 226px en un viewport de 820px (tablet en vertical,
el tamaño de prueba pedido), el panel "Pedido actual" de `pedidos/nuevo.php`
queda tan angosto que el encabezado "Producto" de la tabla se parte letra
por letra en una columna vertical (`P/r/o/d/u/c/t/o`) — probado y
fotografiado (`_test_env/shots/after_pos_tablet_expanded.png`). Es
exactamente el escenario que pedía evaluar esta ronda. Con el sidebar
colapsado a 64px (mismo viewport, botón "«"), el mismo encabezado pasa a
partirse en dos líneas ("Produ/cto") en vez de una por letra — sigue
ajustado pero perfectamente usable
(`_test_env/shots/after_pos_tablet_collapsed.png`). **Decisión: se deja el
sidebar en modo expandible/colapsable (no colapsado por defecto en
tablet)**, porque:
1. No hay forma de saber desde el servidor si un viewport de 820px es una
   tablet en mostrador (donde importa el ancho) o una ventana de escritorio
   angosta (donde no tanto) — obligar el colapso por ancho de pantalla fija
   sería adivinar.
2. El estado se persiste por dispositivo/navegador (`localStorage`): el
   empleado que use una tablet fija en el mostrador lo colapsa una vez y
   queda así para siempre en ese dispositivo, sin fricción repetida.
3. Colapsado, todos los accesos principales siguen disponibles como
   íconos con tooltip — no se pierde ninguna funcionalidad, solo la
   etiqueta de texto.

### Bug real encontrado y corregido durante la validación (separado del cambio de sidebar)

Al probar el dashboard en desktop (1440px) apareció una barra de scroll
horizontal fantasma que cortaba el último botón de accesos rápidos
("Historial de caja"). Causa: `.main-content` (el `container-fluid` que
envuelve todo el contenido, que ya traía `width:100%` de Bootstrap) recibió
`margin-left: var(--sidebar-width)` para correrse a la derecha del sidebar,
pero sin restarle ese mismo ancho al `width` — el resultado es que el
elemento se corre 226px a la derecha SIN achicarse, y su borde derecho
termina 226px afuera del viewport. Se corrigió agregando
`width: calc(100% - var(--sidebar-width))` (y su equivalente para el estado
colapsado) junto al `margin-left` en la media query de `style.css`.
Verificado con captura antes/después
(`_test_env/shots/after_dashboard_desktop.png`, la primera tenía el
scroll/corte, la segunda ya no). **Introducido y corregido dentro de esta
misma ronda, nunca llegó a quedar como estado final** — se documenta acá
porque MEMORY.md pide reportar cualquier bug real encontrado por separado,
y aunque no vino del código preexistente sino de mi propio cambio, vale la
misma lección: verificar con captura real en el viewport más ancho, no solo
en el que "debería" romperse.

### Validación de esta ronda

- Entorno igual que rondas anteriores: `mysqld` descartable (puerto 3308,
  datadir propio) + `database.sql` importado limpio + `php -S` sobre
  `_dev_no_subir/public_html` + Edge headless vía CDP (puerto 9333) con dos
  scripts PowerShell (`_test_env/shot.ps1` / `shot2.ps1`, este último acepta
  un `EvalJs` para simular clicks — ej. abrir el sidebar colapsado o la
  hamburguesa mobile — antes de la captura) usando
  `Emulation.setDeviceMetricsOverride` para los 3 viewports pedidos:
  desktop 1440×900, tablet 820×1180, celular 390×844.
- Capturas reales en `_test_env/shots/`: `after_dashboard_desktop.png`
  (antes y después del fix de overflow), `after_salon_tablet.png`,
  `after_pos_tablet_expanded.png` / `after_pos_tablet_collapsed.png` (el
  trade-off de arriba), `after_pos_mobile_closed.png` (celular, sidebar
  oculto, POS a ancho completo), `after_dashboard_mobile_menu_open.png`
  (hamburguesa abierta, offcanvas full-height), `after_productos_active.png`
  / `after_productos_expanded.png` (ítem activo + auto-expansión del grupo
  "Configuración"). Tildes verificadas visualmente en todas ("Salón",
  "Configuración", "Categorías", "Cambiar contraseña", "Auditoría de
  pedidos") — sin mojibake.
- Navegación directa (`curl` con sesión autenticada) a los 16 links del
  sidebar (Inicio, Salón, Stock, Caja, Egresos, 5 reportes, 5 ABMs +
  Cambiar contraseña): las 16 devuelven 200. `logout.php` no se probó por
  HTTP (mataría la sesión de prueba en uso) pero su `href` no cambió
  respecto a la navbar vieja.
- Checklist end-to-end completo con datos reales: login admin y empleado
  (`123456` ambos), abrir caja ($5.000 inicial), pedido mostrador (2×
  Costeletas, $28.000) cobrado en Efectivo, pedido de mesa (1×
  Sorrentinos, $20.000) cobrado con QR/Mercado Pago, egreso de $1.500 en
  efectivo, cierre de caja declarando $31.600 — "Efectivo esperado"
  calculado por el sistema dio $31.500 (= 5.000 + 28.000 − 1.500, verificado
  a mano) y la diferencia quedó +$100 (sobrante), confirmado contra
  `caja_sesiones` en la base. POST con `csrf_token` inválido a
  `egresos/nuevo.php` → 403 confirmado.
- `php -l` sobre **todos** los `.php` de ambos árboles (no solo los
  tocados), sin errores. Barrido de BOM (`head -c3` buscando `EF BB BF`) y
  del patrón de mojibake en todo `_dev_no_subir` y `DEPLOY_HOSTINGER`: no se
  encontró ninguno.
- Diff exacto (`diff`, no solo lectura visual) entre
  `_dev_no_subir/includes/header.php` y `DEPLOY_HOSTINGER/includes/header.php`,
  y entre `_dev_no_subir/public_html/assets/css/style.css` y
  `DEPLOY_HOSTINGER/assets/css/style.css`: idénticos byte a byte (se copiaron
  con `cp`, no con un pipeline de texto de PowerShell, evitando el bug de
  encoding de la ronda 9/10).
- `config.php` restaurado al placeholder original al terminar (confirmado
  con `diff` contra el backup), procesos de prueba (`mysqld`, `php -S`,
  Edge headless) confirmados terminados (`ps aux` sin resultados).

## Ronda 12 — bug real: sidebar transparente desde 768px

El usuario reportó con una captura de pantalla real (`egresos/listar.php` en
desktop) que el sidebar de la ronda 11 se veía roto: fondo color crema del
body en vez del bordó de marca, con todo el texto de los links (excepto el
ítem activo) casi invisible por bajo contraste.

**Causa raíz** (confirmada reproduciendo el bug con entorno real — mysqld
descartable puerto 3308 + `php -S` + Edge headless vía CDP, e inspeccionando
las reglas CSS que ganan con `CSS.getMatchedStylesForNode` del protocolo
DevTools, no adivinada): Bootstrap 5.3, en su propio reset de `.offcanvas-md`
a partir de `min-width: 768px` (el que lo saca del modo "modal offcanvas" y
lo deja como bloque normal), fuerza `background-color: transparent
!important`. La regla `.app-sidebar { background-color: var(--marca-principal)
}` agregada en la ronda 11 no tenía `!important`, así que Bootstrap le ganaba
en el empate de especificidad (ambas son un único selector de clase) por
cascada. El comentario de la ronda 11 en `style.css` ya documentaba que
Bootstrap fuerza `width`/`position` con `!important` en ese mismo reset (por
eso esas dos propiedades sí tenían `!important`), pero no se cayó en la
cuenta de que `background-color` recibe el mismo tratamiento.

**Corrección**: se agregó `background-color: var(--marca-principal)
!important` al mismo bloque `@media (min-width: 768px) { #sidebar.offcanvas-md
{ ... } }` en `assets/css/style.css`, en ambos árboles. Se subió
`$versionCss` a `20260916-r12` en `includes/header.php` (ambos árboles) para
invalidar el caché del navegador. No se tocó el comportamiento del sidebar
en mobile (por debajo de 768px el bug no existía, porque ahí Bootstrap no
aplica ese reset — el offcanvas overlay ya se veía bien).

**Validación real**: mismo entorno descartable de siempre. Capturas
antes/después de `egresos/listar.php` en desktop (1400px) confirmando el
fondo bordó restaurado; capturas adicionales de `dashboard.php` en desktop
(1400px) y mobile (390px) y de `egresos/listar.php` en tablet (820px)
confirmando que el resto de los tamaños seguía bien y que la corrección no
rompió nada. `php -l` limpio en los 2 `header.php` tocados (ninguno cambió
en realidad, solo el número de versión) y verificación de balance de llaves
en `style.css`. `config.php` restaurado al placeholder, procesos de prueba
(`mysqld`, Edge headless) terminados.

**Lección para próximas rondas que toquen el sidebar/offcanvas**: cualquier
propiedad CSS que Bootstrap resetee con `!important` en el breakpoint
`.offcanvas-md`/`.offcanvas-lg`/etc. (no solo `width`/`position`, verificar
también `background-color`, y si se llega a tocar `border`/`color`/`box-shadow`
del contenedor raíz del offcanvas, revisar con `CSS.getMatchedStylesForNode`
si Bootstrap también las fuerza) necesita el mismo `!important` de nuestro
lado. La forma más rápida de diagnosticar este tipo de bug es exactamente la
que se usó acá: reproducir en un navegador real (headless alcanza) e
inspeccionar qué regla gana con el protocolo CSS de DevTools, en vez de leer
el CSS de forma estática y asumir que "debería" andar.

## Ronda 12b — bug real: contorno de foco del botón de colapsar se sale del sidebar

El usuario reportó con dos capturas de pantalla reales (una del sidebar
expandido, otra colapsado, ambas en `egresos/categorias.php`) que
`#btnColapsarSidebar` (el botón "«" para colapsar/expandir el sidebar a solo
íconos, agregado en la ronda 11) se veía con un recuadro negro grueso que se
salía del sidebar hacia el contenido principal — más notorio en el estado
colapsado (64px de ancho), donde el botón queda pegado al borde derecho.

**Causa raíz** (confirmada reproduciendo el bug real, no adivinada a la
primera): el botón no tenía ningún estilo propio de foco (`:focus`/
`:focus-visible`), así que al recibir foco (`DOM.focus` vía CDP replicó
exactamente el bug) el navegador dibuja su contorno de foco por defecto — un
`outline` que por especificación CSS se dibuja hacia AFUERA de la caja del
elemento, no hacia adentro. Con el botón pegado al borde derecho del sidebar
(sobre todo en el estado colapsado de 64px), ese contorno por defecto queda
visualmente montado sobre el contenido principal. Un clic normal de mouse NO
dispara `:focus-visible` en Chromium/Edge modernos (por eso las primeras
pruebas con clic sintético vía CDP no mostraban nada raro) — hacía falta
forzar el foco explícitamente para reproducirlo, tal como ocurre con
navegación por teclado o ciertos lectores de pantalla.

**Corrección**: se agregó `.sidebar-collapse-btn:focus-visible { outline:
2px solid rgba(255,255,255,.85); outline-offset: -3px; }` en
`assets/css/style.css` (ambos árboles) — el `outline-offset` negativo mete
el contorno hacia adentro del botón en vez de dejarlo salir. Se subió
`$versionCss` a `20260916-r13` en `includes/header.php` (ambos árboles).

**Validación real**: mismo entorno descartable de siempre (mysqld puerto
3308 + `php -S` + Edge headless). Se reprodujo el bug forzando foco
programático (`DOM.focus` del protocolo CDP) sobre el botón antes y después
del fix, con capturas confirmando que el contorno queda contenido dentro del
botón. Se verificó además que un clic de mouse normal (real, vía
`Input.dispatchMouseEvent`, y sintético vía `.click()`) sigue sin mostrar
ningún contorno — comportamiento esperado y sin cambios para el uso táctil/
mouse cotidiano del sistema. `php -l` limpio en los 2 `header.php`, `diff`
confirma `style.css` idéntico byte a byte entre árboles, `config.php`
restaurado, procesos de prueba terminados.

**Lección**: un botón sin estilo de foco propio hereda el default del
navegador, que se dibuja hacia afuera de la caja — si el elemento está
pegado al borde de un contenedor angosto (como este sidebar colapsado de
64px), ese contorno por defecto invade visualmente el contenido vecino. Para
reproducir este tipo de bug con headless hace falta forzar el foco de forma
explícita (`DOM.focus` en CDP, o simular Tab): un clic de mouse normal no
alcanza porque `:focus-visible` lo filtra a propósito.

## Ronda 13 — mejoras de UX/UI y funcionalidad, sistema completo (SIN validar con ejecución real)

El usuario pidió una pasada general de UX/UI y funcionalidad sobre todo el
sistema, no una pantalla puntual ("mejorá todo el ux ui y lo que se pueda
mejorar de las funcionalidades"). Se relevó el estado real con dos
exploraciones en paralelo (UI/UX de las ~20 pantallas + verificación de la
deuda funcional que `MEMORY.md`/`RELEVAMIENTO.md` daban por pendiente o por
resuelta — varias notas de ambos documentos estaban desactualizadas en las
dos direcciones, ver las correcciones más arriba en este archivo) y se armó
un plan con el usuario antes de tocar código, confirmando explícitamente 4
decisiones de negocio (no eran bugs, eran elección del dueño):

1. Bloquear la venta sin caja abierta (antes se podía cobrar sin caja
   abierta y esa plata quedaba fuera de todo cierre).
2. Pedir motivo obligatorio para cancelar cualquier pedido.
3. Restringir `caja/historial.php` a admin (antes cualquier empleado veía
   el historial financiero completo de todos los turnos).
4. Identificar el medio de pago "Efectivo" por una columna fija en la base
   en vez de por el texto del nombre (con migración).

**⚠ Esta ronda NO se validó con ejecución real.** El entorno de esta sesión
no tenía PHP ni MySQL/MariaDB disponibles (se buscó `php.exe` en las rutas
conocidas de rondas anteriores, en todo `C:\`, y Docker/WSL como
alternativa — nada utilizable). El usuario, avisado explícitamente de esto
antes de seguir, pidió continuar solo con revisión manual de código. Se hizo
la revisión más rigurosa posible sin intérprete: lectura completa de cada
archivo tocado (no solo el diff), conteo de balance de `{}`/`()` por
archivo, trazado a mano de cada rama de lógica nueva contra el esquema de
la base. **Ninguna de estas verificaciones reemplaza `php -l` ni una
corrida real** — antes de tocar producción hay que levantar el entorno de
prueba de siempre (ver "Cómo se prueba este proyecto") y correr la
checklist end-to-end completa, con especial atención a los casos nuevos
listados en "Validación pendiente" más abajo.

### Cambios de negocio (las 4 decisiones confirmadas)

- **Bloqueo de venta sin caja abierta**: nuevo helper `hayCajaAbierta(PDO $pdo): bool`
  en `includes/functions.php`. Se usa en `pedidos/nuevo.php` (las dos ramas
  que CREAN un pedido nuevo — mesa sin pedido previo y "para llevar" — no
  en la rama que reabre un pedido ya existente, para no dejar a un mozo
  varado si la caja se cierra mientras carga un pedido) y en
  `pedidos/cerrar.php` (antes de aceptar el POST de cobro). Si no hay caja
  abierta, redirige a `caja/abrir.php` con un `flashError()` explicando por
  qué.
- **Motivo obligatorio para cancelar**: columna nueva `pedidos.motivo_cancelacion`
  (`VARCHAR(255) NULL`). `pedidos/nuevo.php` cambió el `confirm()` de
  `cancelarPedido()` por un `prompt()` que no deja seguir si el motivo
  queda vacío; `pedidos/cancelar.php` valida de nuevo en el servidor (por
  si alguien postea directo sin pasar por el JS) y lo guarda.
  `reportes/auditoria_pedidos.php` suma la columna "Motivo cancelación" a
  la tabla y al CSV.
- **`caja/historial.php` solo-admin**: se agregó `requerirAdmin()` (antes
  solo `requerirLogin()`). Esto rompía el único camino que tenía un
  empleado no-admin para cerrar SU turno (el botón "Cerrar caja actual"
  vivía arriba de esa misma página) — se detectó este efecto colateral
  durante la implementación, no estaba en el plan original, y se corrigió
  sumando un botón "Cerrar caja" directo en el alert de caja abierta de
  `dashboard.php` (visible para cualquier usuario logueado, apunta a
  `caja/cerrar.php` sin pasar por el historial) y sacando el acceso rápido
  "Historial de caja" del dashboard para no-admin. El link del sidebar
  "Caja" se movió adentro del bloque `if (esAdmin())`. Abrir caja
  (`caja/abrir.php`) no se tocó, nunca requirió admin.
- **Medio de pago "Efectivo" por columna fija**: `medios_pago.es_efectivo`
  (`TINYINT(1)`, nueva migración `migracion_ronda13.sql`, que marca con `1`
  el medio que hoy se llama "Efectivo" para no perder el estado actual). En
  `medios_pago/listar.php` se sumó una tarjeta "¿Cuál medio de pago es el
  efectivo físico de la caja?" con un `<select>` + botón que hace un swap
  atómico (`UPDATE ... SET es_efectivo = 0` seguido de
  `SET es_efectivo = 1 WHERE id = ?`, dentro de `ejecutarTransaccion()`)
  para garantizar que siempre haya exactamente uno marcado, más un aviso
  visible si por algún motivo no hay ninguno (o hay más de uno) marcado.
  `caja/cerrar.php` y `caja/historial.php` dejaron de comparar
  `mb_strtolower($nombre) === 'efectivo'` y ahora usan la columna.

### POS (`pedidos/nuevo.php` y sus endpoints) — pantalla más usada por los mozos

- **Las cantidades se suman en vez de duplicar la línea**: `pedidos/agregar_item.php`
  ahora busca (con `SELECT ... FOR UPDATE`, misma protección de carrera que
  ya tenía el producto) si el producto ya está en el carrito antes de
  insertar; si existe, hace `UPDATE cantidad = cantidad + ?` en vez de un
  `INSERT` nuevo. Antes, tocar 3 veces "Empanada de carne" dejaba 3 líneas
  de 1 unidad en vez de una línea de 3.
- **Stepper +/- en el carrito** para productos `tipo_venta = 'unidad'`:
  `pedidos/quitar_item.php` acepta un `cantidad_a_quitar` opcional — si
  viene y es menor a la cantidad actual del ítem, resta esa porción
  (`UPDATE`) en vez de borrar la línea entera (`DELETE`, comportamiento
  original, se sigue usando cuando no se manda el parámetro o cubre toda la
  cantidad). El botón "+" del carrito llama de nuevo a `agregar_item.php`
  (ya queda mergeado por el punto anterior). Los productos por peso NO
  tienen stepper (no tiene sentido un "-1" en kg), siguen con el botón "×"
  de quitar todo. `obtenerEstadoPedido()` en `includes/functions.php` ahora
  devuelve también `producto_id`, `tipo_venta` y `cantidad` (numérica, no
  solo el texto formateado) por ítem, para que el JS decida qué controles
  mostrar.
- **Quitar un ítem (botón "×") ya no es una acción sin red de seguridad**:
  en vez de agregar un `confirm()` bloqueante (se descartó a propósito por
  ser la acción más frecuente del carrito, un confirm en cada toque
  frenaría el flujo), se agregó un toast con acción "Deshacer" por 4
  segundos que vuelve a agregar el mismo producto/cantidad si se toca.
- **`alert()` nativo reemplazado por el mismo toast que ya usaba el caso de
  éxito**: las ~6 llamadas a `alert()` de `pedidos/nuevo.php` (stock
  insuficiente, fallos de red al agregar/quitar/enviar a cocina/marcar
  entregado) pasan a `mostrarToast(msg, { error: true })`, que agrega la
  clase `.toast-error` (fondo rojo en vez del bordó de marca) al mismo
  componente `#toastAgregado` — ya no hay dos idiomas visuales distintos
  para éxito y error en la misma pantalla. `mostrarToast()` ahora acepta un
  segundo parámetro de opciones (`error`, `duracionMs`, `accionTexto`/
  `accionFn` para el caso "Deshacer").

### Caja

- **Confirmación antes de cerrar** (`caja/cerrar.php`): el form de "Cerrar
  caja" suma un `confirm()` con el monto declarado y la diferencia
  calculada, mismo patrón que `cancelarPedido()` del POS.
- **Preview en vivo de la diferencia**: un listener `input` sobre el campo
  de monto contado calcula y muestra un badge (mismo estilo verde/rojo/gris
  de sobra/falta/exacto que ya usa `caja/historial.php`) sin recargar la
  página, reusando `$efectivoEsperado` (ya calculado en PHP) expuesto como
  constante JS vía `json_encode()`.

### Reportes

- **Estado "sin datos"** en `reportes/ventas.php`, `reportes/productos_top.php`
  y `reportes/medios_pago.php` (los únicos 3 que no lo tenían de los 5
  reportes con gráfico/tabla): mensaje `text-muted` en vez de mostrar una
  tabla vacía y un `<canvas>` de Chart.js en blanco cuando el rango
  filtrado no tiene resultados — el bloque entero de tabla+gráfico+`<script>`
  de Chart.js queda condicionado a `!empty($filas)`.
- **Validación de fecha centralizada**: los 5 archivos que todavía repetían
  `preg_match('/^\d{4}-\d{2}-\d{2}$/', ...)` a mano (`egresos/listar.php`,
  `reportes/ventas.php`, `reportes/productos_top.php`,
  `reportes/medios_pago.php`, `reportes/ingresos_egresos.php`) pasaron a
  usar `obtenerFechaGet()` de `includes/paginacion.php` (cada uno sumó el
  `require_once` correspondiente), igual que ya hacían los otros 3
  reportes/pantallas con filtro de fecha. Reutilización pura, sin cambio de
  comportamiento.

### ABMs de configuración (mesas, categorías, medios de pago, categorías de egreso)

- **Feedback de error y de éxito al guardar**: nuevo helper `flashExito()`
  (mismo mecanismo que `flashError()` ya existente, sesión + impresión una
  vez desde `includes/header.php` vía `mostrarFlashExito()`). Los 4 ABMs
  simples sumaron un `else { flashError('El nombre no puede estar vacío.'); }`
  al `if ($nombre !== '')` que antes fallaba en silencio, y
  `flashExito('Guardado correctamente.')` en la rama de éxito. `mesas/listar.php`
  también suma validación de servidor para `capacidad >= 0` (antes solo la
  validaba el `min="0"` del HTML).
- **Errores de base de datos ya no revientan la página en blanco**: los 4
  ABMs simples + `productos/guardar.php` envuelven el `execute()` en
  `try/catch (PDOException $e)` con un `flashError()` genérico.

### Productos

- **`productos/guardar.php` ya no pierde lo tipeado en un error de
  validación**: guarda `$_POST` (sin `csrf_token`) en
  `$_SESSION['flash_form_producto']` antes de cada `flashError()` +
  `redirigir()`. `productos/listar.php` lee esa variable de sesión (y la
  limpia), y si existe reabre el modal correspondiente (nuevo o editar,
  según si había `id`) prellenado con esos datos vía JS al cargar la
  página, reusando el mismo bootstrap.Modal.

### Seguridad / robustez transversal

- **CSRF vencido ya no deja una pantalla en blanco**: `validarTokenCsrf()`
  en `includes/functions.php` ya no hace `die('texto plano')` — llama a
  `mostrarErrorCsrf()`, una función nueva que hace `require __DIR__ . '/header.php'`
  (mismo archivo, sin ajuste de ruta porque `__DIR__` es relativo a
  `includes/` en los dos árboles) para renderizar una página mínima con el
  sidebar (si la sesión sigue viva) y un botón "Volver al inicio", en vez
  de texto crudo del navegador.
- **CSV formula injection**: `exportarCsv()` en `includes/functions.php`
  ahora antepone un apóstrofo a cualquier campo que empiece con `=`, `+`,
  `-`, `@`, tab o retorno de carro (`sanitizarCampoCsv()`), para que texto
  libre cargado por un usuario (ej. la descripción de un egreso) no se
  interprete como fórmula al abrir el CSV en Excel/Sheets.
- **`pedidos/nuevo.php`**: la rama que crea un pedido de mesa (dentro de
  `elseif ($mesaId)`) migró de `beginTransaction()`/`commit()` manual a
  `ejecutarTransaccion()`, el único lugar del sistema con transacción que
  todavía no usaba el helper.

### Versión de CSS

`$versionCss` subido a `20260920-r14` en `includes/header.php` (ambos
árboles) por los cambios nuevos en `assets/css/style.css` (`.toast-error`,
`.toast-accion`, `.stepper-cantidad`).

### Validación pendiente (para la próxima sesión con PHP/MySQL disponibles)

Checklist mínima antes de dar esta ronda por realmente terminada (además de
la checklist end-to-end estándar de la sección "Cómo se prueba este
proyecto"):

- [ ] `php -l` sobre los 21 archivos PHP tocados en ambos árboles (nunca se
      corrió esta ronda — solo revisión manual + conteo de llaves/paréntesis).
- [ ] Importar `database.sql` limpio Y por separado probar
      `migracion_ronda13.sql` sobre una copia de una base ronda-8 (sin las
      columnas nuevas) para confirmar que agrega `es_efectivo` y
      `motivo_cancelacion` sin error.
- [ ] POS: tocar el mismo producto por unidad 3 veces seguidas y confirmar
      que el carrito queda con UNA línea de cantidad 3 (no 3 líneas), que el
      stock descontado es igual a antes, y que el "-" del stepper resta de a
      1 sin borrar la línea hasta llegar a 0.
- [ ] Cancelar un pedido sin escribir motivo (tocar "Cancelar" en el
      `prompt()` vacío o cancelarlo) y confirmar que NO cancela; cancelar con
      motivo y confirmar que aparece en `reportes/auditoria_pedidos.php`.
- [ ] Intentar crear un pedido nuevo y cobrar uno existente con la caja
      cerrada, confirmar que ambos bloquean con mensaje claro y redirigen a
      abrir caja.
- [ ] Renombrar el medio "Efectivo" desde Medios de pago y confirmar que el
      cálculo de caja sigue funcionando idéntico (ya no depende del nombre);
      probar el swap de "efectivo de caja" a otro medio y de vuelta.
- [ ] Confirmar con un usuario NO admin que `caja/historial.php` redirige
      (no debería poder verlo) pero que SÍ puede abrir y cerrar su propia
      caja desde los accesos del dashboard.
- [ ] Cerrar caja: confirmar que el preview de diferencia en vivo coincide
      con el que calcula el servidor, y que el `confirm()` aparece antes de
      enviar.
- [ ] Exportar un CSV de egresos con una descripción que empiece con `=` y
      confirmar (abriendo el archivo) que no se interpreta como fórmula.
- [ ] Forzar un CSRF inválido en un formulario HTML (no un endpoint JSON) y
      confirmar que la página de error nueva se ve bien y el link "Volver al
      inicio" funciona.
- [ ] `diff` real (no visual) de los 21 archivos + `style.css` entre
      `_dev_no_subir/public_html` y `DEPLOY_HOSTINGER`, y barrido de BOM/mojibake
      en ambos árboles completos.

### Lección de esta ronda (entorno sin PHP/MySQL)

Ver la entrada nueva en "Errores ya cometidos" más abajo sobre `sed`
convirtiendo CRLF a LF en este entorno — relevante si una sesión futura
también se queda sin intérprete PHP disponible y necesita sincronizar los
árboles con herramientas de texto en vez de copiar y editar con
`Read`/`Edit`.

## Ronda 14 — reskin visual del sistema real para que coincida con un mockup aprobado por el cliente (SIN validar con ejecución real)

El usuario pidió primero un mockup clickeable (sin backend, datos de
ejemplo) para mostrarle al dueño del negocio cómo podía verse/usarse el
sistema, y después de aprobarlo pidió explícitamente "usá eso para que el
sistema quede igual a eso" — aplicar esa identidad visual al sistema PHP
real (no al revés).

**El mockup se armó como un Artifact de Claude (tipo "Design", canvas
clickeable), no como parte de este repo.** Vive en
`https://claude.ai/artifact/74eaTDLruwNAcMCSBHFNWW` — privado, solo lo abre
quien tenga el link o a quien se lo compartan desde ahí. Son 7 pantallas
estáticas con datos de ejemplo (Inicio, Salón, POS, Caja, Reportes,
Productos, Egresos) que **no tocan la base de datos ni el código real**:
sirven como referencia de diseño, no como código a copiar literal. Si se
pierde el link, se puede volver a armar pidiendo lo mismo, pero el criterio
de diseño que se usó queda documentado en esta sección para no tener que
inferirlo de nuevo mirando capturas.

### Qué significa "igual" acá (alcance real de esta ronda)

Rehacer el HTML de cada pantalla real para calcar pixel a pixel el mockup
hubiera sido un cambio enorme y de altísimo riesgo sin poder renderizar
nada (ver el problema de entorno de la ronda 13, que seguía vigente en
esta). En cambio, se hizo un **reskin a nivel de sistema de diseño**: los
mismos tokens (tipografía, paleta, radios, sombras, forma de las badges)
que el mockup usa, aplicados como reglas globales de CSS + un ajuste chico
de `header.php`, para que TODAS las pantallas cambien de aspecto a la vez
sin tocar el HTML/PHP de cada una. Esto cubre la mayor parte de la
diferencia visual real (el sistema real ya comparte la misma paleta bordó
de marca y la misma estructura de sidebar/cards/tablas que el mockup,
recién de la ronda 11 en adelante) con el mínimo de riesgo posible.
**No** se tocó el layout/contenido de ninguna pantalla — highlights de
diseño muy específicos del mockup (el logo circular "EB", el gráfico de
torta con conic-gradient, el card de "cuál medio es efectivo") no tienen
equivalente 1 a 1 en el sistema real y no se intentó forzarlos.

### Cambios aplicados (ambos árboles)

- **Tipografía**: `includes/header.php` suma un `<link>` a Google Fonts
  (Fraunces + Work Sans, las mismas dos fuentes del mockup) antes del
  `<link>` de `style.css`. En `style.css`, `body` pasa a `font-family:
  'Work Sans'...` y los títulos (`h1`-`h6`, `.card-title`,
  `.sidebar-brand-text`, `.mesa-card`, `.fs-3` — esta última es la clase
  que ya usaban los números grandes de "Pedidos cerrados hoy"/"Total
  vendido hoy" en el dashboard) pasan a `font-family: 'Fraunces'...`.
  **Única dependencia externa no vendorizada del sistema** (ver nota
  corregida más arriba, sección "Stack y restricciones") — decisión
  consciente, no descuido: vendorizar tipografías (bajar los `.woff2`,
  hostearlos en `assets/fonts/`, armar el `@font-face`) es más trabajo y
  no había forma de probarlo en este entorno sin PHP; el fallback de la
  cadena de fuentes (`Georgia, serif` / `sans-serif`) deja todo legible
  igual si el link no carga. Si el cliente pide que no haya NINGUNA
  dependencia externa (ej. el local tiene internet muy inestable), es un
  cambio puntual a futuro: bajar las dos fuentes y vendorizarlas.
- **Tarjetas, botones, badges e inputs más redondeados y con sombra
  suave**: nuevo bloque al final de `style.css` (marcado "RONDA 14") que
  agrega `border-radius`/`box-shadow` globales a `.card` (14px + sombra en
  vez del borde plano de Bootstrap), `.badge` (999px, ahora sí una
  píldora completa), `.btn` (10px), `.alert` (12px) y
  `.form-control`/`.form-select` (9px). Los colores semánticos de los
  badges (verde/rojo/gris de estado) **no se tocaron a propósito** — la
  nota que ya existía en el archivo sobre esto sigue vigente, este reskin
  solo cambió la forma, no el significado de los colores.
- **Tablas**: texto en tono secundario marrón-grisáceo (`--texto-secundario:
  #7c6c5d`, nuevo token) en vez de negro puro, y encabezados en mayúsculas
  chicas con tracking — mismo aire "editorial" que las tablas del mockup.
  El fondo/color de encabezado de marca que ya existía (ronda 9) no se
  tocó.
- **Sidebar**: `includes/header.php` suma un subtítulo "Panel de gestión"
  debajo de "El Batará" en el bloque de marca (mismo patrón que el
  logo/subtítulo del mockup), envuelto en un `<span class="sidebar-brand-textos">`
  nuevo (columna: nombre + subtítulo). Se oculta junto con el resto de las
  etiquetas cuando el sidebar está colapsado a solo íconos (mismo
  mecanismo que ya ocultaba `.sidebar-brand-text`, ronda 11).
- **Nuevos tokens en `:root` de `style.css`**: `--borde-suave`,
  `--texto-secundario`, `--sombra-tarjeta`. Se sumaron sin tocar ni
  renombrar ninguno de los `--marca-*` ya existentes (`--marca-principal`
  sigue siendo el mismo `#8B2E2E` de siempre, es el mismo tono que usa el
  mockup) para no arriesgar romper las reglas del sidebar/POS de rondas
  anteriores que ya los usan.
- `$versionCss` subido a `20260920-r15` en `includes/header.php` (ambos
  árboles) para invalidar el caché del navegador.

### Lo que NO se tocó (a propósito, para no ampliar el riesgo sin poder validar)

`PALETA_GRAFICOS` de los 4 reportes con gráfico (ya bastante alineada con
la paleta del mockup, ver ronda 10), el layout/HTML de cualquier pantalla,
el logo real (`assets/img/logo.png`, si existe) ni su fallback circular
(`.logo-texto`, usado también en `login.php` sobre un fondo distinto al
del sidebar — cambiarlo sin poder ver el resultado real era más riesgo que
beneficio).

### Validación — MISMO problema de entorno que la ronda 13

Esta ronda tampoco se pudo validar con ejecución real: seguía sin haber
PHP/MySQL/navegador disponibles en esta sesión (ver la entrada de la ronda
13 y la de "Errores ya cometidos" sobre esto). Se revisó a mano el HTML
final de `header.php` completo y se contó el balance de `{`/`}` de
`style.css` (140/140), además del barrido habitual de BOM/mojibake en los
dos archivos tocados en ambos árboles (ninguno encontrado) y un `diff`
byte a byte confirmando que quedaron idénticos entre `_dev_no_subir` y
`DEPLOY_HOSTINGER`. **Ninguna de estas verificaciones reemplaza abrir el
sistema real en un navegador.** Antes de mostrárselo al cliente como
versión final (no solo el mockup), hay que:

- [ ] Abrir al menos Inicio, Salón, POS, Caja y un reporte con gráfico en
      un navegador real y confirmar que Fraunces/Work Sans cargan (si no
      hay internet en el momento, confirmar que el fallback se ve
      prolijo igual, no roto).
- [ ] Confirmar que ninguna tarjeta/tabla angosta quedó con texto
      desbordado por el cambio de tipografía (Fraunces es más ancha que
      la fuente de sistema que usaba Bootstrap antes) — el punto más
      probable son los `<h2>` largos de reportes ("Auditoría de pedidos
      (cobros y cancelaciones)") y las tarjetas del dashboard en celular.
- [ ] Confirmar visualmente el subtítulo "Panel de gestión" del sidebar en
      los 3 tamaños de pantalla (desktop, tablet, celular con offcanvas).
      **Nota ronda 15**: el "modo colapsado a íconos" que mencionaba este
      punto originalmente ya no existe — se sacó en la ronda 15, ver esa
      sección más abajo.
- [ ] Comparar de cerca contra el mockup (`https://claude.ai/artifact/74eaTDLruwNAcMCSBHFNWW`)
      y decidir con el usuario si algún detalle puntual más (ej. el
      degradé cónico del gráfico de torta, el logo circular) vale la pena
      llevarlo al sistema real en una próxima ronda, ahora que la base
      tipográfica/de tarjetas ya está alineada.

## Ronda 15 — editar egresos, favicon con el logo, sidebar ya no se puede colapsar (SIN validar con ejecución real)

Pedido corto y directo del usuario, tres puntos:

### 1. Egresos: ahora se pueden editar (antes solo se podían crear)

`egresos/listar.php` no tenía forma de corregir un egreso ya cargado (error
de tipeo en la descripción, monto mal puesto, categoría equivocada) — solo
`nuevo.php` (crear) y el listado (ver). Se agregó:

- **`egresos/guardar.php`** (archivo nuevo): recibe el POST del modal de
  edición, valida los mismos campos que `nuevo.php` (categoría, medio de
  pago, monto > 0, descripción no vacía) y hace el `UPDATE`. A propósito
  **no** exige que la categoría/medio de pago elegidos sigan activos (a
  diferencia de `nuevo.php`, que sí lo exige para altas nuevas) — un
  egreso viejo puede tener una categoría que se desactivó después, y
  forzar a cambiarla para poder corregir otro campo cualquiera sería un
  obstáculo que nadie pidió. Sigue el mismo patrón de `productos/guardar.php`
  (`flashError`/`flashExito`, `try/catch` con mensaje genérico).
- **`egresos/listar.php`**: cada fila suma un botón "Editar" (columna
  nueva, `no-imprimir`) que abre un modal (`#modalEgreso`, mismo patrón
  que el modal de `productos/listar.php`) prellenado vía JS
  (`editarEgreso(e)`) con los datos de esa fila, con `<select>` de
  categoría/medio de pago (a diferencia del grillado de chips táctiles de
  `nuevo.php`, pensado para carga rápida de mozo — acá es una corrección
  puntual, un select alcanza) que muestra también las categorías/medios
  inactivos marcados `(inactiva)`/`(inactivo)` para no perder de vista
  cuál era el valor real si ya no está en la lista activa.
- **Nota para quien lo use**: editar un egreso que pertenece a una caja YA
  CERRADA no recalcula retroactivamente los totales de ese cierre
  (`caja_sesiones`/`caja_sesion_medios` quedan con la foto tomada al
  momento de cerrar, no se recomputan solos) — mismo comportamiento que ya
  tiene el resto del sistema con los cierres de caja (son un snapshot, no
  un valor vivo). No se restringió la edición por esto porque no era parte
  de lo pedido; si hace falta bloquear editar egresos de cajas cerradas,
  es un cambio puntual a futuro.
- **Permisos**: igual que crear/ver egresos, cualquier usuario logueado
  puede editar cualquier egreso (`requerirLogin()`, no `requerirAdmin()`)
  — no se agregó una restricción extra porque no se pidió.

### 2. Favicon con el logo real

`includes/header.php` suma `<link rel="icon" type="image/png" href="<?= $base ?>assets/img/logo.png">`
en el `<head>`, antes del CSS de Bootstrap. Como `login.php` también
`require`ea este mismo `header.php`, la pantalla de login también queda
con el favicon — un solo lugar para las dos. Se confirmó que
`assets/img/logo.png` existe de verdad en disco en los dos árboles (no es
el caso del placeholder "logo no subido todavía" que documentan otras
partes del sistema) antes de agregar el link.

### 3. El sidebar ya NO se puede colapsar a solo íconos (se sacó la ronda 11)

El usuario reportó que "esconder la navbar" no andaba bien y pidió
directamente que no se pudiera esconder, punto — no pidió que se
arreglara el bug, pidió sacar la función. Se interpretó "esconder la
navbar" como el botón "«" de colapsar a solo íconos agregado en la ronda
11 (pensado para tablet/POS), **no** como el offcanvas de celular
(hamburguesa que abre/cierra el sidebar en pantallas <768px) — ese
mecanismo es necesario para que el sistema se pueda usar en celular en
absoluto (sin él no hay forma de navegar en una pantalla chica) y no se
tocó.

Se sacó por completo:
- El botón `#btnColapsarSidebar` y su ícono `«` de `includes/header.php`.
- Los dos `<script>` asociados: el que aplicaba la clase `sidebar-collapsed`
  al `<body>` ANTES de pintar (para evitar parpadeo) leyendo
  `localStorage`, y el que escuchaba el click del botón y guardaba el
  estado en `localStorage` (`elbatara_sidebar_colapsado`).
- En `assets/css/style.css`: la clase `.sidebar-collapse-btn` (con su
  `:hover`/`:focus-visible`, este último agregado recién en la ronda 12b
  para el bug del contorno de foco — ya no aplica, no hay botón), 
  `.sidebar-toggle-icon`, la variable `--sidebar-width-collapsed`, y las
  ~10 reglas `body.sidebar-collapsed ...` dentro del
  `@media (min-width: 768px)` (ancho colapsado del sidebar, ocultar
  etiquetas/chevrons/subtítulo, centrar íconos, ocultar submenús). El
  sidebar ahora es **siempre** de `--sidebar-width` (226px) completo desde
  768px en adelante, sin ninguna forma de angostarlo.
- Se buscó explícitamente en todo el árbol cualquier referencia residual
  a `sidebar-collapsed`/`sidebar-collapse-btn`/`sidebar-toggle-icon`/
  `elbatara_sidebar_colapsado`/`btnColapsarSidebar`/`sidebar-width-collapsed`
  (`grep -rn` sobre `public_html` e `includes`) — no quedó ninguna.
- **Efecto en el trade-off de la ronda 11**: esa ronda documentaba que en
  tablet (820px) el panel "Pedido actual" del POS quedaba muy angosto con
  el sidebar expandido, y que colapsarlo a íconos lo aliviaba. Esa válvula
  de escape ya no existe — si el POS en tablet vuelve a sentirse apretado,
  hay que resolverlo de otra forma (por ejemplo angostando el sidebar fijo
  en general, o ajustando el layout del POS en ese rango de ancho), no
  reintroduciendo el botón de colapsar sin que el usuario lo pida de
  nuevo.
- `$versionCss` subido a `20260920-r16` en `includes/header.php` (ambos
  árboles) — dos bumps en esta misma sesión: `r15` después del favicon,
  `r16` después de sacar el sidebar colapsable, para que el navegador
  invalide el caché con el contenido final.

### Validación — mismo problema de entorno que las rondas 13 y 14

Otra vez sin PHP/MySQL/navegador en esta sesión. Revisión manual completa
de los 4 archivos tocados (`header.php`, `style.css`, `egresos/listar.php`,
`egresos/guardar.php` nuevo) más balance de `{`/`}`/`(`/`)` por archivo,
`grep` de verificación de que no quedó ninguna referencia rota a la
función de colapsar, barrido de BOM/mojibake en ambos árboles (ninguno
encontrado) y `diff` de contenido ignorando únicamente la profundidad
esperada de `../` y el fin de línea CRLF/LF (`egresos/guardar.php` se
creó con la herramienta de escritura en LF y se pasó a CRLF con
`unix2dos` para que coincida con la convención del resto del árbol — ver
la lección de la ronda 13 sobre por qué esto importa). **Sigue sin
reemplazar abrir el sistema real en un navegador.** Antes de dar esto por
terminado:

- [ ] Editar un egreso real desde el modal nuevo y confirmar que el
      `UPDATE` se ve reflejado en el listado y (si corresponde) en el CSV
      exportado.
- [ ] Confirmar que el favicon con el logo aparece en la pestaña del
      navegador, en login y ya logueado.
- [ ] Confirmar en desktop/tablet que el sidebar ya no tiene ningún botón
      ni forma de colapsarse, y que en celular la hamburguesa sigue
      abriendo/cerrando el offcanvas normalmente (eso NO se tocó, pero
      conviene reconfirmar después de sacar los otros scripts del mismo
      archivo).

## Ronda 16 — ABM de usuarios (alta, rol, activo, cambio de contraseña por admin) (SIN validar con ejecución real)

El usuario preguntó cómo crear usuarios nuevos con rol. Antes de esta
ronda **no existía ninguna pantalla para eso**: la tabla `usuarios` (con
`rol` ENUM admin/empleado, `activo`, `intentos_fallidos`/`bloqueado_hasta`
ya en `database.sql` desde antes) solo se podía tocar a mano por SQL, y ni
siquiera `INSTALL.md` documentaba cómo generar el hash bcrypt sin PHP. Se
confirmó la ausencia con `find`/`grep` antes de asumir que faltaba. El
usuario confirmó explícitamente que quería que se construyera la pantalla
(no solo la instrucción de SQL a mano).

### Qué se agregó

**Pantalla nueva `usuarios/listar.php`** (solo admin, `requerirAdmin()`),
sumada al grupo "Configuración" del sidebar (`includes/header.php`,
después de "Categorías de egreso"). Sigue el mismo patrón self-posting con
múltiples `accion` que ya usa `medios_pago/listar.php` desde la ronda 13:

- **`accion=guardar`** (alta o edición de nombre/usuario/rol/activo):
  - Alta: pide nombre, usuario (login), contraseña (mínimo 6 caracteres,
    mismo mínimo que ya exigía `cambiar_password.php`) y rol, con
    `password_hash(..., PASSWORD_DEFAULT)` — la única forma correcta de
    generarlo, que era justamente lo que faltaba para poder hacerlo a mano
    sin PHP.
  - Edición: fila inline por usuario (mismo patrón visual que
    mesas/categorías/medios de pago — inputs con `form="..."` apuntando a
    un `<form>` oculto por fila) para nombre/usuario/rol/activo. **No**
    toca la contraseña (eso es la acción de abajo). De paso resetea
    `intentos_fallidos`/`bloqueado_hasta` a cada guardado — si el admin
    está tocando ese usuario, es un buen momento para destrabarlo si
    estaba bloqueado por intentos fallidos.
  - Choque de `usuario` UNIQUE (constraint ya existía en `database.sql`):
    capturado como `PDOException` código `23000` con mensaje específico
    "Ese nombre de usuario ya existe." en vez del genérico, mismo patrón
    que ya usa `caja/abrir.php` para su propio índice único.
- **`accion=cambiar_password`**: modal aparte (`#modalPassword`, botón
  "Contraseña" por fila) que le pone una contraseña nueva a cualquier
  usuario **sin pedir la actual** (a diferencia de `cambiar_password.php`,
  que sigue existiendo tal cual para que cada uno cambie la propia
  pidiendo la actual) — mismo mínimo de 6 caracteres + confirmación.
- **Protección contra auto-bloqueo** (agregada sin que se pidiera
  explícitamente, pero es un riesgo real y evidente en cuanto se construye
  esta pantalla): un admin no puede, desde acá, desactivar su propio
  usuario ni sacarse a sí mismo el rol admin — no hay recuperación de
  contraseña por mail en este sistema, así que esa sería una forma fácil
  de quedar afuera del sistema sin ninguna otra puerta de entrada salvo
  tocar la base a mano. Doble capa: el `<select>`/checkbox de la propia
  fila queda `disabled` en el HTML (con un input oculto que manda el valor
  actual igual, porque un campo `disabled` no se envía en el POST) **y**
  la validación del lado del servidor en `guardar.php`... en este caso
  dentro del mismo `listar.php` vuelve a chequear
  `$id === $_SESSION['usuario_id']` — el chequeo real es el del servidor,
  el `disabled` es solo para no invitar a hacer clic en algo que va a
  fallar. No se agregó protección equivalente para "no dejar sin ningún
  admin activo en todo el sistema" (un admin sí puede desactivar a OTRO
  admin, incluso si es el último) — no se pidió y hubiera significado
  contar admins activos en cada guardado; queda como límite conocido.
- Columna "Estado" con badge "Bloqueado" (si `bloqueado_hasta` sigue en el
  futuro) o "Vos" (fila del usuario logueado), para que el admin vea de un
  vistazo si alguien quedó trabado por el límite de intentos de
  `includes/auth.php` sin tener que ir a la base a mirar.
- No hay borrado de usuarios (mismo criterio que el resto de los ABMs:
  desactivar, no borrar, para no perder la referencia en el historial de
  pedidos/cierres/egresos que ya tienen ese `usuario_id` cargado).

### Validación — mismo problema de entorno que las rondas 13, 14 y 15

Sin PHP/MySQL/navegador en esta sesión, otra vez. Revisión manual completa
del archivo nuevo (216 líneas) y del `header.php` tocado, balance de
`{`/`}`/`(`/`)` en ambos, `diff` de contenido entre árboles ignorando la
profundidad de `../` y el fin de línea (mismo `unix2dos` de la ronda 13
para que el archivo nuevo quede en CRLF como el resto del árbol), barrido
de BOM/mojibake (ninguno). **No se probó el flujo real.** Antes de usar
esto en producción:

- [ ] Crear un usuario nuevo empleado y confirmar que puede loguearse con
      la contraseña puesta.
- [ ] Crear un usuario admin nuevo y confirmar que ve las pantallas de
      admin (Reportes, Configuración, Caja).
- [ ] Con la cuenta propia, confirmar que el select de rol y el switch de
      activo de la propia fila aparecen deshabilitados, y que un POST
      directo forzando `activo=0` o `rol=empleado` sobre el propio id
      igual lo rechaza el servidor (no confiar solo en el `disabled` del
      HTML).
- [ ] Usar "Contraseña" para resetear la de otro usuario y confirmar que
      ese usuario ya no puede entrar con la vieja pero sí con la nueva.
- [ ] Provocar un bloqueo por intentos fallidos (5 intentos con contraseña
      mal puesta) y confirmar que el badge "Bloqueado" aparece, y que
      guardar esa fila (o cambiarle la contraseña) lo destraba.
- [ ] Intentar crear dos usuarios con el mismo `usuario` (login) y
      confirmar el mensaje específico "Ese nombre de usuario ya existe."

## Ronda 17 — perfiles de acceso configurables, reemplaza el ENUM admin/empleado (SIN validar con ejecución real)

El usuario pidió no seguir atado a solo "admin"/"empleado": quería armar
perfiles a mano (ej. "Mozo", "Cajero") eligiendo qué partes del sistema
tiene habilitadas cada uno. Se acordó el alcance explícitamente antes de
tocar código (ver pregunta hecha al usuario): los permisos nuevos cubren
**solo** las 9 áreas que ya estaban restringidas a "admin" antes de esta
ronda (Caja/historial, Reportes, y los ABMs de Configuración, incluido el
nuevo Perfiles). Salón/POS, Stock y Egresos siguen disponibles para
cualquier usuario logueado, sin permiso propio — decisión explícita del
usuario, no un olvido.

### Modelo de datos

**Tabla nueva `perfiles`**: un booleano por permiso (no una tabla de join
— más simple de armar como ABM de checkboxes, mismo criterio que
`medios_pago.es_efectivo` de la ronda 13). Columnas: `nombre`, `activo`, y
9 permisos: `ver_caja`, `ver_reportes`, `gestionar_productos`,
`gestionar_categorias`, `gestionar_mesas`, `gestionar_medios_pago`,
`gestionar_egresos_categorias`, `gestionar_usuarios`, `gestionar_perfiles`.

`usuarios` suma `perfil_id INT UNSIGNED NULL` (FK a `perfiles`). **La
columna `usuarios.rol` (ENUM admin/empleado) NO se borró** — se dejó a
propósito para no forzar un `DROP COLUMN` en la base real de producción,
pero **desde esta ronda no se lee en ningún lado del código**. Si una
sesión futura ve `rol` en una fila de `usuarios`, es dato histórico
inerte; la fuente de verdad de permisos es `perfil_id` + la tabla
`perfiles`.

`database.sql` (instalación nueva) siembra dos perfiles que reproducen
exactamente el alcance viejo — "Administrador" (los 9 permisos en 1) y
"Empleado" (los 9 en 0) — y asigna `perfil_id` a los 2 usuarios de
ejemplo. `migracion_ronda17.sql` (para la base real ya en producción) hace
lo mismo en 3 pasos: crea `perfiles` + siembra los dos perfiles, agrega
`usuarios.perfil_id` (sin la FK todavía) y lo completa según el `rol`
actual de cada usuario existente, y recién al final agrega la FK — en ese
orden para que la constraint no falle contra filas todavía no pobladas.

### `includes/auth.php` — `tienePermiso()`/`requerirPermiso()` reemplazan a `esAdmin()`/`requerirAdmin()`

- `iniciarSesion()`: el `SELECT` ahora hace `LEFT JOIN perfiles` (no
  `INNER JOIN` — si un usuario quedara sin `perfil_id`, sigue pudiendo
  loguearse, pero con la sesión armada con todos los permisos en `false`,
  fail-closed, en vez de que el login se rompa). Guarda en sesión
  `$_SESSION['perfil_id']`, `$_SESSION['perfil_nombre']` y
  `$_SESSION['permisos']` (array asociativo de los 9 booleanos).
- `tienePermiso(string $permiso): bool` y `requerirPermiso(string $permiso): void`
  (esta última llama a `requerirLogin()` primero, igual que hacía
  `requerirAdmin()`) son las funciones nuevas. Los 13 archivos que antes
  llamaban `requerirAdmin()` pasan cada uno a `requerirPermiso('<permiso
  que corresponde>')` — cambio mecánico, un `grep` confirmó al final que
  no quedó ningún `esAdmin()`/`requerirAdmin()`/`usuario_rol` real en todo
  el árbol (solo 2 menciones en comentarios explicando el reemplazo).

### `includes/header.php` — sidebar ítem por ítem, no por bloque

Antes todo el bloque Caja+Reportes+Configuración estaba atrás de un solo
`<?php if (esAdmin()): ?>`. Ahora cada ítem se muestra según su propio
`tienePermiso()`: el link "Caja" atrás de `ver_caja`, el grupo "Reportes"
atrás de `ver_reportes` (adentro no cambió, los 5 reportes comparten un
solo permiso), y dentro de "Configuración" cada sub-ítem (Productos,
Categorías, Mesas, Medios de pago, Categorías de egreso, Usuarios,
**Perfiles nuevo**) se arma según su propio permiso — el grupo
"Configuración" entero (botón + submenú) solo aparece si al menos uno de
esos 7 está habilitado. El pie del sidebar muestra
`$_SESSION['perfil_nombre']` en vez de `$_SESSION['usuario_rol']`.

### `dashboard.php`

Los 5 usos de `esAdmin()` se repartieron por permiso específico en vez de
seguir todos juntos: alerta de stock bajo → `gestionar_productos`;
resumen del día (pedidos/total vendido hoy) → `ver_reportes`; los 3
accesos rápidos (Historial de caja / Gestionar productos / Ingresos y
egresos) → cada uno atrás de su propio permiso, ya no los tres juntos.

### Pantalla nueva `perfiles/listar.php`

Gateada con `requerirPermiso('gestionar_perfiles')`. Un card por perfil
(no la tabla inline de mesas/categorías — con 9 checkboxes por fila una
tabla se hubiera visto muy apretada) con nombre, activo, y los 9 permisos
como checkboxes, más un card "Nuevo perfil" arriba. **Protección contra
auto-bloqueo** (mismo criterio que `usuarios/listar.php` de la ronda 16):
si se está editando el perfil que es el propio del usuario logueado
(`$_SESSION['perfil_id']`), no se puede desmarcar "Gestionar perfiles" —
se bloquearía a sí mismo el acceso a esta misma pantalla, sin recuperación
por mail ni otra puerta de entrada salvo tocar la base a mano. El
checkbox queda `disabled` en esa fila (con un input oculto que manda `1`
igual, porque un campo `disabled` no viaja en el POST) y el servidor
vuelve a validar lo mismo — el `disabled` es solo para no invitar a
clickear algo que va a fallar.

### `usuarios/listar.php` (ronda 16) — el selector de "Rol" pasa a ser "Perfil"

El `<select name="rol">` (Admin/Empleado) se reemplazó por
`<select name="perfil_id">` con las opciones de `SELECT * FROM perfiles`
(los inactivos se listan igual, marcados `(inactivo)`, mismo criterio que
categorías/medios de pago inactivos). La protección de auto-bloqueo de la
ronda 16 (que comparaba `rol !== 'admin'`) se reescribió: ahora resuelve
`gestionar_usuarios` del perfil NUEVO que se está por guardar
(`SELECT gestionar_usuarios FROM perfiles WHERE id = ?`) y bloquea si el
usuario editado es uno mismo y ese perfil no lo tiene.

### Validación — mismo problema de entorno que las rondas 13 a 16

Sin PHP/MySQL/navegador en esta sesión, otra vez. Esta fue la ronda más
grande de las cinco en tocar lógica de acceso (17 archivos entre ambos
árboles: `database.sql`, `migracion_ronda17.sql` nuevo, `auth.php`,
`header.php`, `dashboard.php`, `usuarios/listar.php` reescrito,
`perfiles/listar.php` nuevo, y 12 archivos con el cambio mecánico de
`requerirAdmin()` a `requerirPermiso()`), así que la revisión manual fue
más exhaustiva que de costumbre: lectura completa de cada archivo,
balance de `{`/`}`/`(`/`)` en los 17, `grep` de que no quedó ningún
`esAdmin()`/`requerirAdmin()`/`usuario_rol` real (solo 2 menciones en
comentarios), `diff` de contenido entre árboles ignorando la profundidad
de `../` y CRLF/LF (`perfiles/listar.php` se creó en LF y se pasó a CRLF
con `unix2dos`), y barrido de BOM/mojibake en los dos árboles completos
más los `.sql`. **Nada de esto reemplaza levantar el sistema real.** Antes
de subir esto a producción:

- [ ] Correr `migracion_ronda17.sql` sobre una copia de la base real y
      confirmar que los usuarios existentes quedan con el `perfil_id`
      correcto según su `rol` viejo (un `SELECT u.usuario, u.rol, p.nombre
      FROM usuarios u JOIN perfiles p ON p.id = u.perfil_id` tiene que dar
      admin→Administrador, empleado→Empleado).
- [ ] Loguearse con el usuario admin de siempre y confirmar que ve
      exactamente lo mismo que veía antes de esta ronda (nada más y nada
      menos) — es el caso que más fácil se rompe con un permiso mal
      mapeado.
- [ ] Crear un perfil de prueba bien restringido (por ejemplo, solo
      `ver_caja` en 1 y el resto en 0), asignárselo a un usuario de
      prueba, loguearse con ese usuario y confirmar: (a) el sidebar
      muestra solo "Caja" del lado restringido, ni el grupo Reportes ni
      Configuración aparecen; (b) entrando por URL directa a
      `productos/listar.php` (sin pasar por el menú) redirige a
      `dashboard.php` en vez de mostrar la pantalla — probar esto es más
      importante que probar que el link no aparece, porque un permiso mal
      puesto en el `requerirPermiso()` de la pantalla es el fallo real de
      seguridad, no un link visible de más en el menú.
- [ ] Con ese mismo usuario de prueba, confirmar que Salón/POS, Stock y
      Egresos siguen andando normalmente (sin gate de permiso, como se
      definió a propósito en esta ronda).
- [ ] Editar el perfil propio (el que usa la cuenta con la que se está
      logueado) desde `perfiles/listar.php` y confirmar que el checkbox
      "Gestionar perfiles" aparece deshabilitado y que un POST directo
      forzando que ese permiso quede en 0 igual lo rechaza el servidor.
- [ ] Confirmar que `caja/cerrar.php`/`caja/abrir.php` (que NO se tocaron
      esta ronda) siguen sin pedir ningún permiso especial, solo login —
      el botón "Cerrar caja" del dashboard tiene que seguir viéndose para
      cualquier usuario con la caja abierta, sin importar su perfil.

## Ronda 18 — el reskin de la ronda 14 no alcanzaba: Salón reconstruido a mano igual que el mockup (SIN validar con ejecución real)

El usuario mandó una captura del mockup (pantalla Salón) y dijo
explícitamente que el sistema real tiene que quedar **igual**, no
parecido. La ronda 14 había hecho un reskin a nivel de tokens globales
(tipografía, radios, sombras) pero **nunca tocó el HTML/CSS específico de
cada pantalla** — por eso `mesas/salon.php` seguía con el diseño viejo de
tarjetas sólidas de color (fondo verde/rojo/ámbar pleno + texto blanco),
completamente distinto del mockup (tarjeta clara con borde de color +
badge de estado en píldora). Esta ronda corrige eso, pantalla por pantalla
empezando por la que mandó el usuario, en vez de asumir que los tokens
globales alcanzaban.

### Salón (`mesas/salon.php` + CSS) — reconstruido para calcar el mockup

- **Tarjeta de mesa**: antes `background-color` sólido por estado (`#1e7e34`/
  `#c0392b`/`#d68910`) con texto blanco centrado. Ahora: fondo claro
  (blanco para libre, tinte suave del color de marca/alerta para
  ocupada/cuenta pedida), borde de 1.5px del color de estado, nombre de
  la mesa en Fraunces a la izquierda + badge de estado en píldora a la
  derecha (mismo layout que el mockup), línea de capacidad en gris, y
  para mesas ocupadas una línea con el tiempo transcurrido **y el total
  del pedido** (dato nuevo: la query de `mesas/salon.php` ahora trae
  `total` de `pedidos`, antes no lo pedía — coincide con lo que mostraba
  el mockup y es información real y útil, no solo estética).
- **Botón "Para llevar"**: era `btn-dark` (gris/negro de Bootstrap, no
  tenía nada que ver con la marca). Pasa a `btn-primary` (ya estilizado
  con el bordó de marca desde antes) + ícono 🛍️.
- **Leyenda de colores** al pie de la grilla (Libre/Ocupada/Cuenta
  pedida con su punto de color), que no existía antes — está en el
  mockup y ayuda a entender el código de color sin tener que aprenderlo.
  **Corrección agregada en ronda 20**: esta leyenda calcó el texto del
  mockup sin verificar que `estado = 'cuenta_pedida'` fuera alcanzable en
  el sistema real — no lo era, quedó como un valor de ENUM muerto que
  ningún código seteaba nunca (el paso real en ese momento era "Entregado",
  vía "Enviar a cocina"/"Marcar entregado"). El usuario lo detectó
  ("me agregaste el Cuenta pedida pero no puedo mandar a ese estado la
  orden"). Ver "Ronda 20" para la corrección: ahora es un estado real.

### Tokens de color nuevos en `style.css` (para el resto de las pantallas)

La ronda 14 había sumado `--borde-suave`/`--texto-secundario`/`--sombra-tarjeta`
pero **nunca los tokens semánticos con variante suave** que el mockup usa
en todos lados (badges de estado, tarjetas de mesa, etc.) — ese fue el
hueco real detrás de por qué "no se ajustaba todo el sistema al mockup".
Se sumaron: `--exito`/`--exito-suave`, `--peligro`/`--peligro-suave`,
`--alerta`/`--alerta-suave`, `--info`/`--info-suave`, y `--marca-suave`
(tinte rosado del bordó de marca, para fondos "ocupado"). Mismos valores
hexadecimales que usa el mockup.

### Badges de estado en TODO el sistema, sin tocar HTML de cada pantalla

En vez de reescribir cada pantalla con badges (caja/historial.php,
egresos, usuarios, mesas/listar.php, productos/listar.php...) se agregó un
override global en `style.css` de las clases de color planas de Bootstrap
(`.badge.bg-success`, `.badge.bg-danger`, `.badge.bg-warning`,
`.badge.bg-info`, `.badge.bg-secondary`) a la versión "suave" (fondo tenue
+ texto del color fuerte) — mismo aspecto que los badges del mockup en
todos lados de una sola vez. **Única excepción a propósito**: el badge de
"Stock bajo" del dashboard se dejó sólido/fuerte (clase nueva
`.badge-alerta-fuerte` sumada a `dashboard.php`) porque busca llamar la
atención con urgencia, no describir un estado tranquilo como "Ocupada" o
"Exacto" — mismo criterio que ya usaba el mockup (ahí también el stock
bajo era un badge sólido, distinto de los badges de estado en píldora
suave).

### Sidebar — ajustes de detalle (no la estructura de ítems, ver más abajo)

- `--sidebar-width` de 226px a 250px, más cerca del ancho del mockup.
- El ítem activo del menú perdió la barra blanca vertical
  (`box-shadow: inset 3px 0 0 #fff`) que tenía desde la ronda 11 — el
  mockup marca el activo solo con el fondo más claro, sin barra lateral.

### Lo que NO se igualó a propósito, y por qué

**La estructura de ítems del sidebar no se aplanó.** El mockup muestra
6 ítems sueltos (Inicio, Salón, Egresos, Caja, Reportes, Productos) sin
ningún grupo desplegable, porque el mockup era una demo simplificada con
7 pantallas en total. El sistema real tiene 5 reportes y 7 ABMs de
Configuración (12 pantallas reales solo ahí) — aplanar todo eso en una
lista sin agrupar haría un menú larguísimo e inmanejable, muy distinto
del mockup limpio. Se mantienen los grupos plegables "Reportes" y
"Configuración" de la ronda 11 con el mismo criterio visual del resto
(colores, radios, tipografía), pero la estructura de navegación no es
1 a 1 con el mockup por una razón real de cantidad de contenido, no por
falta de esfuerzo. Si esto no es lo que el usuario esperaba, vale la pena
que lo diga explícitamente para revisar el criterio.

**El resto de las pantallas (Inicio más allá de los badges, POS, Caja
cerrar/abrir, los 5 Reportes con gráfico, Productos, Egresos) todavía no
se reconstruyeron a mano contra su pantalla equivalente del mockup** —
esta ronda se enfocó en Salón (la que mandó el usuario) más los cambios
globales (tokens de color + badges) que ya mejoran la consistencia en
todos lados. Falta el mismo trabajo puntual que se hizo acá para cada
pantalla restante si el pedido es "igual" en el sentido más estricto.

### Validación — mismo problema de entorno que las rondas 13 a 17

Sin PHP/MySQL/navegador en esta sesión. Revisión manual de los 4 archivos
tocados (`style.css`, `header.php`, `mesas/salon.php`, `dashboard.php`),
balance de `{`/`}`/`(`/`)`, `diff` de contenido entre árboles ignorando
`../` y CRLF/LF, barrido de BOM/mojibake. **No se pudo confirmar
visualmente que Salón haya quedado igual al mockup** — antes de asumir que
sí:

- [ ] Abrir `mesas/salon.php` en un navegador real en desktop y comparar
      lado a lado con el mockup (`https://claude.ai/artifact/74eaTDLruwNAcMCSBHFNWW`,
      pantalla Salón).
- [ ] Confirmar que el total del pedido se ve bien en la línea de tiempo
      de una mesa ocupada real (con un pedido real cargado), no solo que
      el código compila.
- [ ] Revisar de cerca los badges de `caja/historial.php` (sobra/falta/
      exacto) y `usuarios/listar.php` (Bloqueado) con el nuevo estilo
      suave — son los que más cambian de aspecto con el override global.
- [ ] Confirmar que el badge de "Stock bajo" del dashboard sigue viéndose
      sólido/rojo fuerte (no se coló en el override suave).

## Ronda 18b — bug real: `dashboard.php` daba 500 en producción por una ruta `require` rota

El usuario reportó error 500 al abrir el dashboard y pidió específicamente
revisar las rutas — diagnóstico correcto al toque.

**Causa raíz**: al sincronizar `dashboard.php` a `DEPLOY_HOSTINGER` en la
segunda mitad de la ronda 18 (el cambio del badge `badge-alerta-fuerte`),
se hizo un `cp` directo desde `_dev_no_subir/public_html/dashboard.php`
**sin reaplicar el ajuste de profundidad de `../`** que sí se había hecho
correctamente en la ronda 17. `_dev_no_subir/public_html/dashboard.php`
usa `require_once __DIR__ . '/../includes/auth.php'` (sube un nivel,
porque `public_html/` está anidado un nivel por debajo de `includes/` en
ese árbol), pero en `DEPLOY_HOSTINGER/` **`includes/` está al mismo nivel
que `dashboard.php`**, no un nivel arriba — ahí la ruta correcta es
`__DIR__ . '/includes/auth.php'`, sin `../`. El `cp` pisó la versión ya
corregida con la versión sin corregir, y el `../` de más apuntaba a una
carpeta `includes/` que no existe un nivel arriba de `DEPLOY_HOSTINGER/`
en el servidor real → `require` fallaba con "failed to open stream" →
fatal error de PHP → 500.

**Por qué no se detectó en la revisión de esta sesión**: la verificación
posterior al `cp` fue un `diff` **sin normalizar** la diferencia de
profundidad esperada entre árboles (`diff _dev_no_subir/.../dashboard.php
DEPLOY_HOSTINGER/dashboard.php`, en vez del patrón ya usado en rondas
anteriores de `sed 's|\.\./includes/|includes/|g'` antes de comparar). Un
`diff` plano entre los dos árboles para un archivo con esta particularidad
**siempre va a decir "MATCH" tanto si los dos están bien (con su
profundidad correcta cada uno) como si los dos quedaron mal por igual**
(como pasó acá) — el `diff` sin normalizar no puede distinguir esos dos
casos. Ronda 17 sí había usado el patrón normalizado correctamente para
este mismo archivo; ronda 18 se saltó ese paso al hacer un `cp` de
"último momento" para un cambio chico (una sola clase CSS) y no se
tomó con el mismo cuidado que los cambios grandes.

**Corrección**: se rehicieron los 3 `require`/`require_once` de
`DEPLOY_HOSTINGER/dashboard.php` (`auth.php`, `functions.php`,
`header.php`, `footer.php`) sacando el `../` de más. Además, se corrió
una auditoría automática sobre **todo** `DEPLOY_HOSTINGER` (y también
`_dev_no_subir`, por las dudas): un script que extrae cada
`require(_once) __DIR__ . '/...'` de cada `.php`, resuelve la ruta final
y confirma que el archivo de destino existe en disco. Salió limpio en los
dos árboles salvo el único caso esperado
(`includes/db.php -> ../config/config.php`, que no existe en el repo
a propósito — tiene credenciales reales, está en `.gitignore`, lo crea
cada instalación a partir de `config.example.php`). Esto confirma que
`dashboard.php` era el único archivo con esta rotura, no había otros
escondidos.

**Lección para toda sesión futura que sincronice `DEPLOY_HOSTINGER` con
`_dev_no_subir`**: para CUALQUIER archivo raíz de `public_html/`
(`dashboard.php`, `login.php`, `logout.php`, `cambiar_password.php`,
`index.php`) o de una subcarpeta, la verificación después de un `cp`
**tiene que ser siempre con la ruta normalizada** (`sed` sacando/poniendo
el `../` de más según corresponda, como ya documentaba MEMORY.md desde
rondas anteriores para archivos de subcarpeta) — nunca un `diff` plano
entre los dos árboles para estos archivos, ni siquiera "solo para un
cambio chico". Si en algún momento sobra tiempo, valdría la pena escribir
el `scripts/comparar_arboles.sh` que este archivo menciona como
inexistente desde hace varias rondas, con esta misma auditoría de rutas
`require` incluida — hubiera detectado este bug antes de que llegara a
producción.

## Ronda 19 — resto del sistema contra el mockup: POS reconstruido, badges cubiertos del todo (SIN validar con ejecución real)

El usuario pidió seguir con el resto de las pantallas del mockup y
preguntó si hacía falta que mande una imagen de cada una — no hace falta:
el mockup lo armé yo mismo (los `.dc.html`), así que tengo los valores
exactos de cada pantalla sin necesidad de mirar capturas.

**Antes de tocar nada** se relevó qué pantallas tenían CSS propio sin
cubrir por los overrides globales de las rondas 14/18 (el mismo problema
que tenía Salón) vs. cuáles ya se arman enteramente con clases de
Bootstrap (`.card`, `.table`, `.badge`, `.btn`, `.alert`, `.form-control`)
que **ya** heredan el reskin global sin tocar nada más. Se hizo con un
`grep` de todas las clases usadas en Caja, Reportes (los 5), Productos,
Egresos y los ABMs (mesas/categorías/medios de pago/usuarios/perfiles):
ninguna tiene CSS propio relevante sin cubrir — todas ya se ven alineadas
al mockup solo por los cambios globales que ya existían. La única
pantalla con una cantidad importante de CSS propio sin tocar todavía era
el **POS** (`pedidos/nuevo.php`), reconstruido acá.

### POS (`assets/css/style.css`, sin tocar el PHP/JS de la pantalla)

- **`.producto-btn`** (tarjeta de producto en la grilla): borde de 2px
  gris oscuro (`#d8cfc4`) sin sombra pasa a borde de 1.5px `--borde-suave`
  + `box-shadow: var(--sombra-tarjeta)` (mismo lenguaje de tarjeta que el
  resto del reskin). El precio, que antes arrancaba gris y recién se
  ponía color de marca al tocar/pasar el mouse, ahora es **siempre**
  bordó y negrita (`font-weight:700; color:var(--marca-principal)`) —
  es el dato que un mozo apurado necesita leer más rápido, no tiene
  sentido que empiece apagado.
- **Chips de categoría** (`#tabsCategorias .nav-link`): antes TODOS los
  chips (activos o no) tenían borde rojo de 2px, compitiendo entre sí por
  atención. Ahora solo el chip **activo** lleva color de marca (relleno
  bordó + texto blanco); los demás quedan neutros (borde/fondo suaves,
  texto gris) — mismo criterio que el mockup, donde un solo chip resalta
  y el resto son referencia de fondo.
- **Cobertura de `.badge.bg-primary`** sumada al override global de
  badges (se había cubierto success/danger/warning/info/secondary en la
  ronda 18, pero el badge "Mesa" del encabezado del POS usa `bg-primary`
  y se había quedado con el azul de Bootstrap sin tocar — ahora usa
  `--marca-suave`/`--marca-principal-oscuro`, mismo criterio suave que el
  resto).
- `egresos/nuevo.php` reusa la clase `.producto-btn` para sus chips de
  categoría/medio de pago (carga rápida de mozo) — se beneficia del mismo
  cambio sin tocar ese archivo.

### Lo que se decidió NO tocar (a propósito, mismo criterio que la ronda 18)

El mockup mostraba el "Nuevo egreso" con selects simples de
categoría/medio de pago; el sistema real usa una grilla de chips táctiles
para carga rápida (pensada para un mozo apurado, no para un demo de
escritorio) — se mantiene la grilla de chips, ya beneficiada por el
cambio de `.producto-btn` de arriba, en vez de forzarla a selects simples
solo por calcar el mockup literal.

### Validación — mismo problema de entorno que las rondas 13 a 18

Sin PHP/MySQL/navegador otra vez. Se aplicó la lección de la ronda 18b:
`diff` **normalizado** (no plano) para verificar la sincronización entre
árboles, y se corrió de nuevo la auditoría de rutas `require` sobre todo
`DEPLOY_HOSTINGER` después de sincronizar (limpia, salvo el
`config/config.php` esperado). Balance de `{`/`}` de `style.css`
verificado (145/145), barrido de BOM/mojibake sin encontrar nada.
**Nada de esto reemplaza abrir el POS real y tocarlo.** Antes de darlo por
bueno:

- [ ] Abrir el POS en un celular/tablet real (no solo desktop) y confirmar
      que la sombra nueva de `.producto-btn` no hace que la grilla se vea
      "pesada" o lenta al scrollear en un equipo de gama baja — es la
      pantalla más usada del sistema, vale la pena el chequeo extra.
      Si se siente pesada, la sombra se puede sacar solo para mobile con
      un media query sin perder el resto del cambio.
- [ ] Confirmar que los chips de categoría siguen siendo fáciles de leer
      con el nuevo estilo neutro-por-defecto (el objetivo era que el
      activo resalte más, no que los inactivos se vuelvan difíciles de
      encontrar).
- [ ] Repasar con el usuario si Caja/Reportes/Productos/Egresos, que no
      se tocaron esta ronda por heredar ya el reskin global, efectivamente
      se ven alineadas al mockup en la práctica — el relevamiento fue por
      `grep` de clases CSS, no por comparación visual real.

## Ronda 20 — "Cuenta pedida" pasa a ser el único paso intermedio real, se elimina "Enviar a cocina"/"Entregado" (SIN validar con ejecución real)

### Contexto

El usuario reportó que el botón/badge "Cuenta pedida" agregado en la ronda
18 (copiado literal del texto del mockup) no llevaba a ningún lado: no
había forma de mandar un pedido a ese estado. Diagnóstico con `grep -rn
"cuenta_pedida"` en todo el árbol: el único uso real era la leyenda visual
de `mesas/salon.php` y un valor de ENUM (`mesas.estado`) que ningún código
seteaba — un estado muerto. El flujo real en ese momento era de **dos**
pasos intermedios (`abierto` → `en_preparacion` vía "Enviar a cocina" →
`entregado` vía "Marcar entregado" → `cerrado`), heredado de un diseño
pensado para cocina, que el dueño de un almacén de campo + resto chico no
usa así.

Se preguntó explícitamente al usuario qué prefería (arreglar solo el texto
de la leyenda vs. eliminar el flujo de dos pasos y hacer de "Cuenta
pedida" el único paso real) — eligió la segunda opción: **"no necesito el
estado entregado ni enviado a la cocina, cambialo a cuenta pedida"**.

### Cambios

- **`database.sql`**: `pedidos.estado` ENUM reducido de
  `('abierto','en_preparacion','entregado','cerrado','cancelado')` a
  `('abierto','cuenta_pedida','cerrado','cancelado')`. Columnas nuevas
  `cuenta_pedida_en DATETIME NULL` / `cuenta_pedida_por_id INT UNSIGNED
  NULL` (+ FK `fk_pedidos_cuenta_pedida_por`), mismo lugar donde estaban
  `entregado_en`/`entregado_por_id`. Esas dos columnas viejas **no se
  borraron** (mismo criterio que `usuarios.rol` en la ronda 17: quedan
  como dato histórico inerte, comentario explícito en el schema) — evita
  un `DROP COLUMN` innecesario en producción.
- **`migracion_ronda20.sql`** (nuevo, para la base real de Hostinger):
  agrega las 2 columnas nuevas (sin FK todavía), copia
  `entregado_en`/`entregado_por_id` a las columnas nuevas para cualquier
  pedido que ya esté en `'entregado'` (no se pierde el dato de cuándo se
  entregó), convierte cualquier pedido en `('en_preparacion','entregado')`
  a `'cuenta_pedida'`, angosta el ENUM con `ALTER TABLE ... MODIFY COLUMN`,
  y recién al final agrega la FK (después de poblar todas las filas, para
  no fallar contra filas ya existentes).
- **`includes/functions.php`** — `renderBotonEstadoPedido()` reescrita:
  antes tenía 3 ramas (abierto → "Enviar a cocina", en_preparacion →
  "Marcar entregado", entregado → nada); ahora 2 (abierto → botón "🧾
  Pedir la cuenta", cuenta_pedida → badge "🧾 Cuenta pedida").
- **`pedidos/agregar_item.php`, `quitar_item.php`, `cancelar.php`,
  `cerrar.php`**: el `WHERE estado IN ('abierto', 'en_preparacion',
  'entregado')` de cada uno (qué pedidos se pueden seguir tocando) pasa a
  `IN ('abierto', 'cuenta_pedida')`.
- **`pedidos/nuevo.php`**: mismo cambio de `WHERE` (2 ocurrencias, por
  `pedido_id` y por `mesa_id`). En el JS, `enviarCocina()` y
  `marcarEntregado()` (dos funciones, dos `fetch()` a dos endpoints
  distintos) se reemplazan por una sola `pedirCuenta()` que llama a
  `pedir_cuenta.php`. `actualizarBotonEstadoPedido()` se simplifica: ya no
  tiene rama para `en_preparacion`.
- **`pedidos/pedir_cuenta.php`** (nuevo, reemplaza a los dos archivos de
  abajo): mismo patrón que los demás endpoints AJAX (`requerirLogin()`,
  chequeo de método POST, CSRF a mano porque responde JSON). Hace
  `UPDATE pedidos SET estado = 'cuenta_pedida', cuenta_pedida_en = NOW(),
  cuenta_pedida_por_id = ? WHERE id = ? AND estado = 'abierto'` — el
  `WHERE estado = 'abierto'` en la misma query evita una carrera si dos
  mozos tocan el botón casi a la vez (el segundo `UPDATE` afecta 0 filas,
  se detecta con `rowCount() === 0` y devuelve error "recargá la
  página").
- **`pedidos/enviar_cocina.php`, `pedidos/marcar_entregado.php`**:
  **eliminados** (ambos árboles) — ya no hay ningún camino que los llame.
- **`mesas/salon.php`**: el `WHERE estado IN (...)` de la query de
  pedidos abiertos, igual que arriba. La lógica de qué clase/texto pintar
  por mesa pasa de comparar `'entregado'` a comparar `'cuenta_pedida'`
  (`mesa-cuenta_pedida` / "Cuenta pedida" en vez de `mesa-ocupada` /
  "Ocupada" cuando corresponde) — la leyenda que ya existía desde la
  ronda 18 (ver corrección agregada ahí arriba) queda **correcta** por
  primera vez.
- **`reportes/auditoria_pedidos.php`**: todo el archivo (WHERE, JOIN,
  columnas del `SELECT`, export CSV, encabezados de tabla HTML, celdas)
  renombra `entregado_por_id`/`entregado_en`/`entregado_por_nombre` a
  `cuenta_pedida_por_id`/`cuenta_pedida_en`/`cuenta_pedida_por_nombre`
  ("Entregado por"/"Fecha entrega" → "Cuenta pedida por"/"Fecha cuenta
  pedida").

### Sincronización entre árboles

Los 9 archivos tocados (`functions.php`, los 4 `pedidos/*.php` de un
`WHERE` de una línea, `nuevo.php`, `pedir_cuenta.php` nuevo,
`salon.php`, `auditoria_pedidos.php`) se copiaron/editaron en
`DEPLOY_HOSTINGER` con el ajuste de profundidad de `require` (`../../`
→ `../` para los archivos de subcarpeta) y se verificaron con `diff`
**normalizado** (`sed` ajustando la profundidad + `tr -d '\r'` de ambos
lados antes de comparar), no un `diff` plano — lección de la ronda 18b
aplicada desde el principio esta vez.

**Se repitió el bug de `sed` documentado en la ronda 13** ("`sed` en Git
Bash de Windows convierte CRLF a LF en silencio", ver "Errores ya
cometidos" más abajo): el `sed -i 's|\.\./\.\./includes/|../includes/|g'`
usado para ajustar la profundidad de `require` en `mesas/salon.php`,
`pedidos/nuevo.php` y `reportes/auditoria_pedidos.php` dentro de
`DEPLOY_HOSTINGER` dejó esos 3 archivos en LF-only, mientras el resto del
árbol sigue en CRLF. Se detectó con `grep -qU $'\r'` sobre cada archivo
(mismo chequeo que ronda 13) y se corrigió con `unix2dos -q` sobre los 3
— re-verificado que el contenido normalizado seguía siendo idéntico
después del fix. El archivo nuevo `pedir_cuenta.php` (creado con `Write`,
por lo tanto LF-only desde el vamos, sin pasar por `sed`) se normalizó a
CRLF de la misma forma en ambos árboles. Ya son dos rondas (13 y 20) donde
este mismo mecanismo de `sed -i` con reemplazo de ruta se olvida de
preservar CRLF — vale la pena que una sesión futura escriba un wrapper
chico (`sed ... | unix2dos` en una sola línea, o directamente evitar
`sed -i` para estos ajustes y usar la herramienta `Edit` cuando el
entorno lo permita) en vez de acordarse cada vez de memoria.

### Validación — mismo problema de entorno que las rondas 13 a 19

Sin PHP/MySQL/navegador en esta sesión (ver "Regla de trabajo activa" al
final del archivo — no se pudo cumplir el estándar real esta ronda
tampoco). Revisión manual de los 9 archivos: lectura completa, balance de
`{`/`}`/`(`/`)` por archivo (todos calzaron — ver detalle en el historial
de comandos si hace falta el desglose exacto), barrido de BOM (`head -c3`)
y mojibake (`grep -c $'\xc3\x83'`) sin encontrar nada en ninguno de los 9.
`grep` de `enviar_cocina`/`marcar_entregado` en todo el árbol confirma que
no queda ninguna referencia viva (solo un comentario explicativo en
`functions.php` que menciona el nombre viejo a propósito, para contexto).
Auditoría de rutas `require` (script bash propio, resolviendo cada
`require __DIR__ . '/...'` con `realpath -m` y confirmando que el destino
existe) corrida sobre `DEPLOY_HOSTINGER` y `_dev_no_subir` completos: sin
roturas, salvo el único falso positivo ya conocido de
`includes/db.php -> ../config/config.php` (gitignoreado a propósito).

**Nada de esto reemplaza correr el flujo real.** Antes de subir esto a
producción:

- [ ] Correr `migracion_ronda20.sql` contra una copia de la base real (o
      una importación fresca de `database.sql` + datos de prueba) y
      confirmar que no falla, y que pedidos viejos en `'entregado'`
      terminan en `'cuenta_pedida'` con `cuenta_pedida_en`/
      `cuenta_pedida_por_id` poblados desde los valores viejos.
- [ ] Abrir un pedido de mesa, tocar "🧾 Pedir la cuenta", confirmar que
      el botón cambia a badge y que la mesa en `mesas/salon.php` pinta
      como "Cuenta pedida" (ámbar) en vez de "Ocupada".
- [ ] Confirmar que se puede seguir agregando/quitando ítems con el
      pedido en `cuenta_pedida` (el `WHERE` lo permite a propósito, por
      si el mozo agrega algo de último momento) y que se puede cobrar
      directo desde `abierto` sin pasar por `cuenta_pedida` (es un paso
      opcional, no obligatorio).
- [ ] Tocar "Pedir la cuenta" dos veces rápido (o simular la carrera) y
      confirmar que la segunda no rompe nada (el `WHERE estado =
      'abierto'` del UPDATE debería hacer que la segunda devuelva el
      error "recargá la página" en vez de un 500 o un estado raro).
- [ ] Revisar `reportes/auditoria_pedidos.php` con un pedido real que
      pasó por `cuenta_pedida` antes de cobrarse: confirmar que la
      columna/CSV "Cuenta pedida por"/"Fecha cuenta pedida" muestra el
      dato correcto.
- [ ] `php -l` real sobre los 9 archivos en ambos árboles (esta sesión
      solo pudo contar llaves/paréntesis a mano, no reemplaza el linter
      real).

## Errores ya cometidos y su lección

- **Trabajo previo reportado que no estaba en el repo.** Al retomar este
  proyecto se mencionó una "ronda anterior" (fases de mejora integral:
  concurrencia, seguridad, vendorizado de Chart.js, `ejecutarTransaccion()`
  como convención ya existente, `includes/paginacion.php`, `scripts/comparar_arboles.sh`,
  `MEMORY.md`/`RELEVAMIENTO.md` ya existentes, un entorno Docker ya armado)
  que en los hechos **no existía en disco**: `git log` mostraba un solo
  commit, no había `docker-compose.yml`, `ejecutarTransaccion()` no existía
  todavía en `functions.php`, Chart.js se cargaba por CDN, y ninguno de esos
  archivos de documentación existía. Nunca se confirmó si ese trabajo se
  perdió, si vivía en otra carpeta/máquina, o si nunca se aplicó realmente.
  **Lección**: toda sesión nueva tiene que verificar el estado real del repo
  (`git log --oneline`, `git status`, listar archivos con `Glob`/`Grep`) ANTES
  de asumir que un trabajo previo mencionado en una conversación de chat
  efectivamente está aplicado en disco. Si algo mencionado no aparece,
  hay que decirlo explícitamente y preguntar cómo seguir, no fabricarlo desde
  cero como si siempre hubiera estado ahí ni tampoco ignorarlo en silencio.
- **Docker instalado pero no necesariamente disponible/relevante.** En esa
  misma ronda, Docker Desktop estaba instalado pero con el servicio detenido,
  y el único contenedor que corría al arrancarlo era de un proyecto distinto
  ("ecosense_db") ocupando el puerto 3306. Lección: no asumir que un entorno
  de ejecución "debería" estar disponible solo porque el software está
  instalado; verificar que además esté corriendo y libre de conflictos con
  otros proyectos de la misma máquina antes de apoyarse en él, y preferir un
  datadir/puerto descartable propio en vez de reusar lo que ya esté corriendo.
- **Credenciales reales en `DEPLOY_HOSTINGER/config/config.php`.** Ese
  archivo tiene host/base/usuario/contraseña reales de la base de producción
  de Hostinger. Está correctamente en `.gitignore`, pero como archivo en
  disco es real y sensible. Lección: si hace falta usarlo para una prueba
  (por ejemplo, para validar que el árbol de deploy funciona con las rutas
  correctas), respaldarlo primero (copia `.bak`), nunca commitearlo ni
  mostrarlo completo innecesariamente en la conversación más de una vez, y
  restaurarlo apenas termine la prueba — nunca dejar la app apuntando a una
  base de prueba con ese archivo como si fuera el estado final.
- **`Get-Content` sin `-Encoding UTF8` corrompe acentos (ronda 9).** Al editar
  `config.php` con un pipeline de PowerShell (`Get-Content -Raw | -replace | Set-Content`,
  sin especificar encoding) y al importar `database.sql` con
  `Get-Content -Raw | mysql.exe`, Windows PowerShell 5.1 leyó los archivos con
  el codepage por defecto de la consola en vez de UTF-8, y el resultado quedó
  doblemente codificado ("Configuración" → "ConfiguraciÃ³n", visible incluso
  en los datos ya insertados en la base). No se notó hasta ver una captura de
  pantalla real del navegador — la salida de `mysql.exe` en consola ya venía
  rara antes y se había asumido (mal, en la ronda 8) que era solo cosmético
  de la consola. Lección: para archivos con tildes/ñ, usar `Get-Content -Encoding UTF8`
  explícito, o mejor, evitar el pipeline de texto de PowerShell del todo y
  usar `mysql.exe -e "source ruta/al/archivo.sql"` (el cliente de MySQL lee
  el archivo directo de disco, sin pasar por la capa de texto de
  PowerShell) — y para archivos PHP, usar las herramientas `Read`/`Edit` en
  vez de pipelines de PowerShell, que ya manejan UTF-8 correctamente.
  **Adenda ronda 10**: esta corrupción había pasado a `DEPLOY_HOSTINGER` en
  13 archivos de la ronda 8 y no se había detectado porque la verificación
  de sincronización entre árboles se hizo mirando que el contenido "dijera
  lo mismo", no con una búsqueda explícita de BOM/mojibake ni con captura de
  pantalla real de esas pantallas en el árbol de deploy. Lección agregada:
  al diffear los árboles, sumar siempre un chequeo explícito de BOM
  (`head -c3 archivo` buscando `EF BB BF`) y del patrón "Ã"/"Â" en todo el
  árbol, no solo comparar que el texto de ambos lados "se vea igual" en la
  salida de una herramienta que podría estar normalizando ella misma el
  encoding al mostrarlo.
- **`sed` en Git Bash de Windows convierte CRLF a LF en silencio (ronda 13).**
  Sin PHP disponible para copiar/editar archivos con las herramientas
  `Read`/`Edit` de siempre (ver "Ronda 13" arriba), se usó `sed -i 's|\.\./\.\./includes/|../includes/|g'`
  para ajustar la profundidad de los `require` al copiar 19 archivos de
  `_dev_no_subir/public_html` a `DEPLOY_HOSTINGER`. El contenido de texto
  (acentos/ñ) quedó bien — no es el mismo bug que el de `Get-Content` de
  arriba — pero `sed` (tanto en `-i` como en redirección a un archivo
  nuevo) silenciosamente eliminó los `\r` de las líneas, dejando esos 19
  archivos en LF mientras el resto del árbol (y los archivos originales)
  seguían en CRLF. `file archivo.php` lo mostró claro: "with CRLF line
  terminators" vs. sin esa frase. No rompe nada funcionalmente (PHP no
  distingue CRLF de LF), pero rompe la consistencia del árbol y hace que
  `diff` normal marque TODAS las líneas como distintas aunque el contenido
  sea idéntico (falso positivo que casi se interpreta como una
  sincronización rota). Se detectó comparando con `diff <(tr -d '\r' < A) <(tr -d '\r' < B)`
  y se corrigió con `unix2dos -q archivo.php` (disponible en este Git Bash)
  sobre los 19 archivos. Lección: si hay que tocar archivos de texto con
  `sed`/sustituciones de shell en vez de `Read`/`Edit`, verificar
  `file archivo` (o `cat -A | head`) antes y después en ambos lados de la
  copia — no asumir que un cambio "solo de texto ASCII" preserva el resto
  del archivo byte a byte. Si `php.exe`/`mysql.exe` no están disponibles en
  una sesión, avisar al usuario explícitamente ANTES de improvisar con
  herramientas de shell que no se usaron en rondas anteriores, en vez de
  descubrir sus efectos secundarios sobre la marcha.

## Ronda 21 — mesas adentro/afuera, pre-cuenta imprimible y circuito de cocina por ítem

El usuario pidió 4 funcionalidades nuevas sobre el POS/Salón: ubicación de
mesas (adentro/afuera), una pre-cuenta imprimible que NO cobra ni cambia
estados, un circuito de cocina por ítem con rondas repetibles, y un panel de
cocina en tiempo real con polling + sonido. El pedido original describía un
estado del código desactualizado (mencionaba `en_preparacion`/`entregado`
como pasos del pedido completo) — se relevó el estado real primero
(`pedidos.estado` es `abierto/cuenta_pedida/cerrado/cancelado` desde la
ronda 20, sin pasos de cocina a nivel pedido) y se construyó sobre eso, sin
romper el flujo existente de "Pedir la cuenta" (`pedidos/pedir_cuenta.php`,
que SÍ cambia `pedidos.estado` a `cuenta_pedida`).

### Cambio de modelo de datos importante: `pedido_items.estado_cocina`

Se agrega un circuito de cocina **por ítem**, independiente del estado del
pedido: `pedido_items.estado_cocina ENUM('pendiente','enviado','listo','entregado')
NOT NULL DEFAULT 'pendiente'` + `pedido_items.enviado_cocina_en DATETIME NULL`
(para mostrar "hace cuánto" por ítem en el panel de cocina, no por pedido
completo — un pedido puede tener ítems de más de una ronda enviados en
momentos distintos). `pedidos.estado` NO gana ningún paso nuevo: sigue
alcanzando con `abierto/cuenta_pedida/cerrado/cancelado`, confirmado con la
prueba end-to-end de abajo (un pedido puede seguir "abierto" mientras sus
ítems van y vienen de cocina, y "cuenta_pedida" convive sin problema con
ítems todavía en cocina — el Salón combina ambas señales, ver más abajo).

Migración de datos (`migracion_ronda21.sql`): los `pedido_items` de pedidos
ya `cerrado`/`cancelado` pasan a `estado_cocina = 'entregado'` (no tiene
sentido que el panel de cocina los muestre como pendientes de un pedido que
ya terminó). Los de pedidos `abierto`/`cuenta_pedida` quedan en el default
`'pendiente'` — se documenta explícitamente que esto asume que la migración
se corre SIN pedidos a mitad de cocinar (recomendado correrla entre turnos o
con el local cerrado), porque el modelo viejo no tenía forma de saber si un
ítem ya se había enviado a cocina o no.

### Decisiones de diseño con margen propio (documentadas como pide el pedido)

1. **Nombre del botón de pre-cuenta: "Vista previa de cuenta"**, no "Pedir
   la cuenta" (ese nombre ya lo usa el botón existente de
   `pedir_cuenta.php`, que SÍ cambia `pedidos.estado`). Los dos botones
   conviven en `pedidos/nuevo.php`: "🧾 Pedir la cuenta" (cambia estado) y
   "🧾 Vista previa de cuenta" (abre `pedidos/precuenta.php` en pestaña
   nueva, sin tocar nada). `precuenta.php` es una vista standalone calcada
   del formato angosto tipo ticket de `ticket.php`, con un aviso "⚠
   PRE-CUENTA — NO ES COMPROBANTE DE PAGO" repetido arriba y abajo del
   detalle, y accesible mientras el pedido esté `abierto` o
   `cuenta_pedida` (no cambia ningún estado, se puede abrir las veces que
   haga falta, el pedido sigue editable después).
2. **"Marcar entregado" es por PEDIDO completo, no por ítem individual**:
   un solo botón en `pedidos/nuevo.php` (visible solo si hay algún ítem
   `listo`) marca TODOS los ítems `listo` de ese pedido como `entregado` de
   una vez (`pedidos/marcar_entregado.php`). Es la acción real del mozo
   (llevar la bandeja a la mesa con todo lo que está listo junto), y evita
   una fila de botones por ítem en un panel que ya tiene bastante
   información. Mismo criterio simétrico para "Enviar a cocina": un botón
   manda TODOS los `pendiente` de ese pedido a `enviado` de una vez
   (`pedidos/enviar_cocina.php`), puede juntar ítems agregados en más de
   una llamada al botón si el mozo no lo había tocado antes.
   `cocina/marcar_listo.php` sí soporta las dos granularidades (`item_id`
   puntual desde el botón de un ítem individual, o `pedido_id` desde el
   botón "Marcar todo listo" del grupo/mesa) porque en cocina sí tiene
   sentido terminar un plato antes que otro dentro del mismo pedido.
3. **Pedir el mismo producto otra vez cuando la línea anterior ya está
   `listo`/`entregado` crea una FILA NUEVA, no suma cantidad a la
   existente**: `agregar_item.php` solo mergea con una línea existente si
   su `estado_cocina` es `'pendiente'` o `'enviado'` (todavía no se sirvió).
   Si ya está `listo`/`entregado`, nace una línea nueva en `'pendiente'`.
   Es la pieza clave para que "pedir de nuevo en la misma mesa" (rondas
   repetibles, como pide el punto 3 del pedido) se vea como una ronda de
   cocina distinta y no haga crecer en silencio una línea que cocina o el
   mozo ya dieron por terminada. Probado explícitamente más abajo.
4. **Colores de mesa en el Salón: "listo" gana sobre "en cocina"**. Se
   agregan dos clases nuevas (`mesa-en-cocina` azul/`--info`,
   `mesa-para-retirar` verde) que se superponen al color de
   Ocupada/Cuenta pedida existente cuando algún ítem activo del pedido
   está `enviado` o `listo` — si hay de las dos cosas a la vez, gana
   "listo" (más urgente para el mozo) y el texto combina ambas señales
   ("Cuenta pedida · Retirar", "Ocupada · En cocina"). Si todos los ítems
   activos ya están `entregado` (o no hay ninguno), no se toca nada del
   comportamiento viejo. Bug real encontrado y corregido en el proceso: el
   texto combinado más largo desbordaba y se superponía con el nombre de
   la mesa (`.mesa-card-fila` no tenía `flex-wrap`) — se agregó
   `flex-wrap: wrap` y quedó prolijo, verificado con captura antes/después.
5. **Panel de cocina sin gate de permiso**: `cocina/panel.php` y
   `cocina/api.php` usan `requerirLogin()` solo (cualquier empleado o admin
   logueado puede abrirlo), mismo criterio ya documentado para Salón/POS y
   Stock desde la ronda 17 ("sin gate de permiso, decisión explícita") —
   es una pantalla operativa para dejar abierta en una tablet de cocina,
   no un ABM.
6. **Cancelar un pedido con ítems en distintos estados de cocina no
   necesita tocar `estado_cocina` para nada**: `pedidos/cancelar.php` no se
   modificó. Alcanza con que `cocina/api.php` filtre
   `ped.estado IN ('abierto','cuenta_pedida')` — apenas el pedido pasa a
   `cancelado`, sus ítems (aunque queden con `estado_cocina = 'enviado'`/
   `'listo'` sin actualizar) desaparecen solos de la cola de cocina y del
   color del Salón. Probado explícitamente con un pedido con un ítem
   `listo` y otro `enviado` a la vez: cancelar devolvió el stock, liberó la
   mesa, y `cocina/api.php` quedó vacío para ese pedido de inmediato.
7. **Sonido del panel de cocina**: WAV corto generado con un script PHP
   propio (`assets/sonidos/alerta_cocina.wav`, dos tonos ascendentes tipo
   "ding-dong", ~0.35s, sin dependencias externas ni CDN) en vez de buscar
   un archivo de stock. Botón "🔔 Activar sonido" hace un
   `play()`+`pause()` inmediato disparado por el click real del usuario
   para desbloquear el autoplay de reproducciones futuras sin interacción
   (política estándar de los navegadores). El JS detecta ítems `enviado`
   NUEVOS comparando el `Set` de `item_id` del ciclo anterior contra el
   actual y suena UNA sola vez por ciclo aunque hayan llegado varios ítems
   juntos (`cocina/panel.php`, función `cargarPedidos()`).

### Endpoints/pantallas nuevas

- `pedidos/precuenta.php` (vista previa imprimible, no cambia estado).
- `pedidos/enviar_cocina.php` (POST JSON, manda todos los `pendiente` de un
  pedido a `enviado`).
- `pedidos/marcar_entregado.php` (POST JSON, manda todos los `listo` de un
  pedido a `entregado`).
- `cocina/panel.php` (pantalla, polling cada 6s a `cocina/api.php`).
- `cocina/api.php` (JSON de solo lectura: ítems `enviado`/`listo` de
  pedidos activos, agrupados por pedido/mesa).
- `cocina/marcar_listo.php` (POST JSON, marca un `item_id` puntual o todos
  los `enviado` de un `pedido_id`).
- Todos los POST reimplementan la verificación de CSRF a mano para
  responder JSON (mismo patrón ya documentado como deuda conocida para
  `agregar_item.php`/`quitar_item.php`, no es nuevo de esta ronda).
- `obtenerEstadoPedido()` (`includes/functions.php`) ahora devuelve también
  `estado_cocina`/`estado_cocina_texto` por ítem y
  `hay_pendientes_cocina`/`hay_listos_cocina` a nivel pedido — el JS de
  `pedidos/nuevo.php` usa esos dos flags para mostrar/ocultar los botones
  "Enviar a cocina"/"Marcar entregado" sin recargar la página.
- Sidebar (`includes/header.php`): ítem nuevo "🍳 Cocina" entre Egresos y
  Caja, visible para cualquier usuario logueado (no está adentro de ningún
  `if (tienePermiso(...))`, coherente con el punto 5 de arriba).
  `$versionCss` subido a `20260923-r21`.

### Mesas adentro/afuera

`mesas.ubicacion ENUM('adentro','afuera') NOT NULL DEFAULT 'adentro'`.
`mesas/listar.php` (ABM) suma el campo al alta y a cada fila editable.
`mesas/salon.php` separa la grilla en dos secciones con encabezado propio
(`.salon-seccion-titulo`) — "🏠 Salón interno" y "🌿 Patio / exterior" —
usando una función local (`$renderTarjetaMesa`) para no duplicar el bloque
de tarjeta. Si una sección queda vacía (todas las mesas activas del mismo
lado), no se muestra el encabezado vacío.

### Validación de esta ronda (ejecución real, no revisión de código)

Entorno igual al de rondas anteriores: `mysqld` descartable de XAMPP
(datadir propio, puerto 3308) + `database.sql` actualizado importado +
`php -S 127.0.0.1:8921 -t _dev_no_subir/public_html` + Edge headless vía
CDP (puerto 9333, script `_test_env/shot.ps1` nuevo, con
`Emulation.setDeviceMetricsOverride` y `Network.setCookie` para reusar la
sesión ya logueada por `curl`).

- **Flujo completo de dos rondas de cocina en la misma mesa, verificado
  contra la base real en cada paso** (no solo "debería andar"):
  1. Login admin, abrir caja $5.000.
  2. Mesa 1: pedido nuevo, 2× Costeletas + 1× Sorrentinos (ronda 1) →
     `agregar_item.php` responde `estado_cocina: "pendiente"` en los dos.
  3. "Enviar a cocina" → `enviar_cocina.php` pasa los 2 ítems a `enviado`;
     `cocina/api.php` los lista agrupados bajo "Mesa 1"; `mesas/salon.php`
     devuelve la clase `mesa-en-cocina` para esa mesa (confirmado con
     `grep` sobre el HTML real).
  4. "Marcar todo listo" desde `cocina/marcar_listo.php` (`pedido_id`) →
     los 2 pasan a `listo`; salón pasa a `mesa-para-retirar`;
     `cocina/api.php` los sigue mostrando pero como `listo`.
  5. "Marcar entregado" desde `pedidos/marcar_entregado.php` → los 2 pasan
     a `entregado`; `cocina/api.php` queda `"pedidos":[]` para esa mesa;
     salón vuelve a `mesa-ocupada` normal.
  6. **Ronda 2 en la MISMA mesa**: se pide de nuevo 1× Costeletas (mismo
     producto que ya estaba `entregado`). Confirmado con la respuesta JSON
     que se creó una fila NUEVA (`id: 3`, `estado_cocina: "pendiente"`) en
     vez de sumarle cantidad a la fila `id: 1` que ya estaba `entregado`
     (que se mantuvo en `cantidad: 2, estado_cocina: entregado`).
  7. Se repite enviar a cocina → listo → entregado para la ronda 2. Total
     final del pedido: `$62.000,00` (`28.000 + 20.000 + 14.000`, verificado
     contra `pedidos.total` en la base con `mysql.exe` directo, no solo la
     respuesta JSON).
  8. `pedidos/precuenta.php` consultado a mitad de la ronda 2 (pedido
     todavía `abierto`, total parcial `$62.000,00`) — confirmado con
     `SELECT estado, total FROM pedidos WHERE id = 1` que la vista NO tocó
     ni el estado ni el total.
  9. "Cobrar / Cerrar" con medio de pago Efectivo → `pedidos.estado =
     'cerrado'`, `total = 62000.00`, `medio_pago_id = 1`, mesa vuelve a
     `libre` — los 3 ítems de las dos rondas quedaron en la misma cuenta,
     como pedía el flujo de prueba.
- **Caso borde "cancelar con ítems mixtos en cocina"**: pedido en Mesa 2
  con un ítem `listo` y otro `enviado` a la vez, cancelado con motivo
  obligatorio → `pedidos.estado = 'cancelado'`, stock de los dos productos
  devuelto (verificado contra `productos.stock_actual` antes/después),
  mesa liberada, y tanto `cocina/api.php` como el color de `mesas/salon.php`
  reflejaron la desaparición del pedido de la cola de cocina de inmediato
  (sin tocar `pedido_items.estado_cocina`, que quedó "congelado" en
  `listo`/`enviado` pero es irrelevante una vez cancelado).
- **CSRF inválido → 403** confirmado en los 2 endpoints nuevos más
  sensibles (`pedidos/enviar_cocina.php`, `cocina/marcar_listo.php`).
- **Capturas reales** (`_test_env/shots/`): `r21_salon_dividido_fix.png`
  (las 4 mesas con sus 4 estados distintos: en cocina/para
  retirar/cuenta pedida/libre, secciones adentro/afuera separadas),
  `r21_precuenta.png` (aviso de pre-cuenta bien visible arriba y abajo),
  `r21_cocina_panel.png` (un ítem "EN COCINA" con botón "Marcar listo" y
  otro "LISTO" ya sin botón, botón "Marcar todo listo" del grupo,
  "Activar sonido" arriba a la derecha), `r21_pos_botones.png` (los 4
  botones de `pedidos/nuevo.php` conviviendo: Pedir la cuenta / Marcar
  entregado / Vista previa de cuenta / Cobrar-Cerrar, badge "LISTO" junto
  al ítem del carrito).
- **Bug real encontrado y corregido durante esta misma ronda** (no
  preexistente): el texto combinado del badge de mesa ("Ocupada · En
  cocina") se salía de la tarjeta y tapaba el nombre de la mesa en la
  primera captura — `.mesa-card-fila` no tenía `flex-wrap`. Corregido
  agregando `flex-wrap: wrap` y re-verificado con una segunda captura
  limpia (`r21_salon_dividido_fix.png` ya incluye el fix).
- **Lo que NO se verificó con ejecución real, solo por revisión de
  código**: la reproducción real del sonido del navegador (headless no
  tiene salida de audio ni dispara interacción de usuario real) — se
  verificó que el WAV generado es un archivo válido (reproducible,
  29 KB), que el flujo `play()+pause()` en el click de "Activar sonido"
  sigue el patrón estándar documentado para desbloquear autoplay, y que la
  lógica de "detectar ítems `enviado` nuevos vs. el ciclo anterior → una
  sola reproducción por ciclo" es correcta por inspección directa del
  código (`itemsEnviadosVistos` como `Set`, comparado antes de
  reemplazarlo en cada `cargarPedidos()`). El polling en sí (el `fetch`
  cada 6s a `cocina/api.php` y el diffing contra el DOM) sí se probó con
  ejecución real disparando cambios "desde otro lado" (los mismos POST de
  `curl` que la mesa 1 recibió) y confirmando con capturas que el HTML
  devuelto por `cocina/api.php` reflejaba esos cambios — no se instrumentó
  un segundo navegador headless en paralelo para ver la actualización
  automática en vivo dentro de los 6 segundos, pero el endpoint que la
  alimenta está verificado end-to-end.
- `php -l` sobre los 13 archivos tocados/nuevos en ambos árboles, y además
  sobre AMBOS árboles completos (`find ... -iname "*.php"`), sin errores.
- `database.sql` reimportado desde cero con los cambios de esta ronda
  (`mesas.ubicacion`, `pedido_items.estado_cocina`/`enviado_cocina_en`) y
  usado para toda la validación de arriba — confirma que una instalación
  nueva ya incluye el modelo nuevo sin necesitar la migración suelta.
- Sincronización a `DEPLOY_HOSTINGER`: `cp` para archivos sin cambio de
  profundidad de `require` (`includes/*`, `assets/css/style.css`,
  `assets/sonidos/*.wav`) + `cp` seguido de `sed -i` (ajuste de
  `../../includes/` → `../includes/`) + `unix2dos -q` inmediato para los
  archivos de subcarpeta, tal como quedó documentado como lección en las
  rondas 13/18b/20 — verificado que los 10 archivos de subcarpeta
  quedaron en CRLF después del `sed` (no se repitió el bug). `diff`
  **normalizado** (`tr -d '\r'` + el mismo `sed` de ajuste de ruta antes de
  comparar) entre los dos árboles para los 13 archivos: idénticos. Barrido
  de BOM: ninguno. `scripts/comparar_arboles.sh` corrido al final:
  "OK: los árboles están sincronizados."
- `config.php` restaurado al placeholder original (confirmado con `diff`
  contra el backup), procesos de prueba (`mysqld`, `php -S`, Edge
  headless) confirmados terminados (`Get-Process` sin resultados para los
  3 después de matarlos).

## Regla de trabajo activa

Cada ronda de cambios se valida con **ejecución real** (levantar PHP + MySQL/MariaDB
real, simular el flujo HTTP con datos reales, verificar los números resultantes
contra la base) antes de darse por terminada — nunca alcanza con revisión
estática de código ni con "esto debería funcionar". El resumen que se le
reporta al usuario al final de una ronda tiene que reflejar evidencia real
(comandos corridos, valores obtenidos, capturas de las respuestas), no una
descripción de lo que "debería" pasar si el código es correcto.

---

*Nota: este archivo se actualiza en cada ronda futura, no se regenera desde
cero. Sumá lo nuevo, corregí lo que quedó desactualizado, y no borres la
sección de "errores ya cometidos" salvo que el error ya no sea relevante para
nada de lo que queda en el repo.*
