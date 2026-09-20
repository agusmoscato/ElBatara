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
  servido directo; las únicas dependencias externas son Bootstrap 5.3.3 (por
  CDN, sin vendorizar todavía) y Chart.js (vendorizado en `assets/js/chart.umd.min.js`
  desde la ronda 8).
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

- **Login/sesión**: funciona, bcrypt + CSRF + cookies seguras. Sin límite de intentos.
- **POS / pedidos**: flujo completo abierto→cerrado con cancelación, probado. Riesgo de carrera en stock (ver abajo).
- **Salón/mesas**: funciona, sin N+1, pinta estado real del pedido asociado. Desde ronda 9 la mesa placeholder "Para Llevar" (capacidad 0) no se muestra en la grilla — ver bug corregido más abajo.
- **Canal mostrador/mesa**: funciona (ronda 8). Acceso rápido a Egresos/Ingresos y egresos sumado al dashboard en ronda 9.
- **Medios de pago dinámicos**: funciona (ronda 8), ABM completo. Frágil: "Efectivo" se identifica por nombre en `caja/cerrar.php` y `caja/historial.php`, no por ID fijo.
- **Stock**: ingresos + historial por producto funcionan. Sin ABM de ajustes manuales negativos.
- **Caja**: abrir/cerrar/historial funcionan, cálculo recalculado en ronda 8 (probado con diferencia $0 real). Columnas legadas `total_tarjeta`/`total_transferencia` quedan NULL en cierres nuevos, a propósito.
- **Egresos**: módulo nuevo completo (ronda 8) — carga rápida, listado con filtro y paginación (la única pantalla con paginación real hoy).
- **Reportes** (ventas, productos top, medios de pago, auditoría, ingresos y egresos): todos funcionan, con filtro de fecha + CSV + impresión. Ingresos y egresos es el más completo (canal + medio + categoría + balance neto + 2 gráficos).
- **Productos/categorías (ABM)**: funcionan, sin paginación ni filtro (81 productos hoy, no es problema todavía).
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
- Sin límite de intentos de login, sin cabeceras HTTP de seguridad (CSP, X-Frame-Options, etc.).
- Bootstrap sigue por CDN (Chart.js ya se vendorizó en ronda 8).
- Validación de rango de fechas duplicada en 6 reportes; CSRF-para-JSON duplicado en 4 endpoints AJAX.
- Condición de carrera teórica (no reproducida con test real) en `pedidos/agregar_item.php`: lee `stock_actual` y actualiza sin `SELECT ... FOR UPDATE`.
- Precios/productos marcados `revisar`/`PENDIENTE` en `database.sql` (líneas 346, 416, 446, 465-471) y falta la categoría "Embutidos curados en grasa de cerdo" (foto ilegible al cargar datos).
- Migraciones pendientes de verificar contra la base real de Hostinger antes de cada deploy (no se puede chequear desde este entorno).
- **Todo el trabajo de las rondas 8 a 11 está sin commitear** al momento de escribir esto (2026-09-15/16) — `git status` muestra los archivos modificados/nuevos en working tree, un solo commit total en el repo.
- Deuda ya identificada en `RELEVAMIENTO.md` (ronda 8) que la ronda 9 **no tocó a propósito** por no ser el foco pedido (UX/copy/lógica de UI, no seguridad ni arquitectura): sin límite de intentos de login, `productos/guardar.php` falla validaciones en silencio sin avisar al usuario, condición de carrera teórica en `agregar_item.php`, Bootstrap sigue por CDN.

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
