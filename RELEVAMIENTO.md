# Relevamiento del estado real — Sistema de gestión "El Batará"

> Generado el 2026-09-15 leyendo el repo tal como está en disco, ejecutando el
> sistema real (PHP 8.3 + MariaDB de XAMPP) y revisando `git log`/`git status`.
> No se da nada por hecho de conversaciones anteriores: todo lo que sigue está
> verificado contra el código y la base de datos reales en este momento.

## 0. Estado de git (importante para lo que sigue)

- Un solo commit en `main`: `082b68b` ("Sistema de gestión El Batará: POS, mesas,
  stock, caja, reportes y auditoría"), del 2026-09-07.
- **Todo el trabajo de la "ronda 8" (medios de pago dinámicos, canal
  mostrador/mesa, módulo de egresos, reporte de ingresos y egresos) está
  aplicado en el working tree pero SIN COMMITEAR todavía.** `git status`
  muestra 20 archivos modificados y 8 nuevas rutas/carpetas sin agregar,
  repetidos en `_dev_no_subir/` y `DEPLOY_HOSTINGER/`, más
  `migracion_ronda8.sql` y los cambios en `database.sql`.
- No hay otras ramas locales con trabajo distinto (`git branch -a` solo
  muestra `main` y `origin/main`).

## 1. Estructura del proyecto

Estructura real (confirmada con `Glob`, no supuesta):

```
ElBataraSistema/
├── database.sql              # schema completo + datos de ejemplo/reales, versionado con comentarios de migración inline
├── migracion_ronda8.sql       # migración suelta para bases ya en producción (ronda 8)
├── migracion_ronda6.sql       # migración suelta previa (ronda 6, estado "entregado")
├── carta_real.sql             # solo el bloque de categorías/productos reales, para importar aparte
├── INSTALL.md                 # guía de instalación en Hostinger (existe, es real y detallada)
├── _dev_no_subir/              # árbol "canónico" de desarrollo — EXISTE
│   ├── README.txt              # explica por qué existen dos árboles
│   ├── config/                 # config.php (real, gitignored) + config.example.php + .htaccess
│   ├── includes/                # auth.php, db.php, functions.php, header.php, footer.php + .htaccess
│   └── public_html/             # todas las pantallas, una carpeta por módulo
└── DEPLOY_HOSTINGER/            # árbol de deploy — EXISTE, espejo de public_html con config/includes ADENTRO
    └── LEEME_PRIMERO.txt        # guía rápida de subida
```

- **`_dev_no_subir/` existe**: es el árbol de desarrollo "ideal", con
  `config/` e `includes/` fuera de `public_html/`.
- **`DEPLOY_HOSTINGER/` existe**: mismo contenido de `public_html/` más
  `config/` e `includes/` reacomodados un nivel más arriba de profundidad
  (porque en Hostinger, sin acceso fuera de `public_html`, todo va junto).
  La única diferencia real de contenido entre archivos equivalentes de
  ambos árboles es la profundidad de los `require __DIR__ . '/../includes/...'`
  (uno o dos `../` según el árbol).
- **No existe ningún script de comparación entre árboles** (`comparar_arboles.sh`
  u otro nombre). La sincronización se hizo a mano en la ronda 8 y se verificó
  con un diff ad-hoc en PowerShell, no con una herramienta versionada en el
  repo. Es una tarea pendiente real (ver sección 10).
- `INSTALL.md` existe y es sustancial: pasos de creación de base, importación
  de `database.sql`, configuración de `config.php`, subida de archivos (con
  ambas variantes, FTP y Administrador de Archivos), verificación de versión
  de PHP, y hasta instrucciones de backup manual/automático con `mysqldump`.
  `DEPLOY_HOSTINGER/LEEME_PRIMERO.txt` es la guía corta equivalente pensada
  para ese árbol específico.

## 2. Base de datos

### Archivos `.sql` y orden de aplicación

| Archivo | Para qué sirve | Cuándo correrlo |
|---|---|---|
| `database.sql` | Schema completo desde cero + datos de ejemplo + carta real de El Batará + medios de pago/categorías de egreso iniciales | Instalación **nueva**, una sola vez |
| `carta_real.sql` | Solo categorías/productos reales (sin el schema) | Si ya tenías datos de ejemplo cargados y solo querés sumar la carta real |
| `migracion_ronda6.sql` | Agrega estado `entregado` + columnas de auditoría de entrega | Bases que ya existían **antes** de la ronda 6 |
| `migracion_ronda8.sql` | Medios de pago dinámicos, canal, egresos, desglose de caja | Bases que ya existían **antes** de la ronda 8 |

`database.sql` también trae, comentados al final, los `ALTER TABLE` sueltos
equivalentes a cada migración (para consulta rápida sin abrir el archivo
suelto), pero las migraciones reales para producción son los archivos
`migracion_rondaN.sql` dedicados.

**Importante sobre la base de Hostinger real**: no hay forma de verificar
desde este entorno si la base de producción (`u269501740_elbatara`, según
`DEPLOY_HOSTINGER/config/config.php`) ya tiene aplicada la ronda 6, la ronda 8,
ninguna, o alguna combinación intermedia. Antes de subir el código de
`DEPLOY_HOSTINGER/` a producción hay que confirmar a mano contra esa base qué
migraciones ya corrieron.

### Tablas (según `database.sql`, verificado importándolo en una base MariaDB real)

- **`usuarios`**: `id, nombre, usuario (UNIQUE), password_hash, rol ENUM('admin','empleado'), activo, creado_en`.
- **`categorias`**: `id, nombre, activo`.
- **`productos`**: `id, categoria_id FK, nombre, tipo_venta ENUM('unidad','peso'), precio, stock_actual DECIMAL(10,3), stock_minimo, activo, creado_en`. Índices en `categoria_id` y `activo`.
- **`mesas`**: `id, nombre, capacidad, estado ENUM('libre','ocupada','cuenta_pedida'), activo`.
- **`medios_pago`** *(ronda 8)*: `id, nombre VARCHAR(50) UNIQUE, activo`.
- **`pedidos`**: `id, mesa_id FK NULL, canal ENUM('mostrador','mesa')` *(ronda 8)*`, usuario_id FK, estado ENUM('abierto','en_preparacion','entregado','cerrado','cancelado'), total, medio_pago_id FK NULL` *(ronda 8, antes era ENUM)*`, creado_en, entregado_en, entregado_por_id FK, cerrado_en, cerrado_por_id FK, cancelado_en, cancelado_por_id FK`. Índices en `estado`, `cerrado_en`, `canal`.
- **`pedido_items`**: `id, pedido_id FK, producto_id FK, cantidad DECIMAL(10,3), precio_unitario, subtotal`. Índice en `pedido_id`.
- **`movimientos_stock`**: `id, producto_id FK, tipo ENUM('venta','ingreso','ajuste'), cantidad, referencia_pedido_id FK NULL, usuario_id FK, nota, creado_en`. Índices en `producto_id`, `creado_en`.
- **`caja_sesiones`**: `id, usuario_id FK, monto_inicial, monto_final_declarado, total_efectivo, total_egresos_efectivo` *(ronda 8)*`, total_tarjeta, total_transferencia` *(columnas heredadas: ya no se completan en cierres nuevos, se mantienen solo para no perder historial viejo)*`, diferencia, estado ENUM('abierta','cerrada'), abierta_en, cerrada_en, nota`.
- **`caja_sesion_medios`** *(ronda 8, nueva)*: `id, caja_sesion_id FK, medio_pago_id FK, total_ventas, total_egresos`, UNIQUE en `(caja_sesion_id, medio_pago_id)`. Reemplaza a las columnas fijas de `caja_sesiones` para el desglose por medio, de forma dinámica.
- **`categorias_egreso`** *(ronda 8, nueva)*: `id, nombre, activo`.
- **`egresos`** *(ronda 8, nueva)*: `id, categoria_id FK, descripcion, monto, medio_pago_id FK, caja_sesion_id FK NULL, usuario_id FK, creado_en, nota`. Índices en `creado_en`, `caja_sesion_id`.

Todas las FK están declaradas explícitamente con `CONSTRAINT ... FOREIGN KEY`.
No hay `ON DELETE CASCADE` en ninguna: borrar una fila referenciada (por
ejemplo un usuario con pedidos) fallaría por integridad referencial en vez de
arrastrar borrados en cascada — es una decisión de diseño razonable para este
tipo de sistema (no se pierden ventas por accidente), pero no está documentada
explícitamente en ningún lado como decisión consciente.

## 3. Funcionalidades implementadas — inventario completo

| Funcionalidad | Dónde vive | Qué tan robusta es / limitaciones |
|---|---|---|
| **Login / sesión** | `login.php`, `includes/auth.php` | Hash bcrypt (`password_hash`/`password_verify`), regenera ID de sesión al loguear (anti session-fixation), cookie `httponly` + `samesite=Lax` + `secure` condicional a HTTPS. **Sin límite de intentos de login** (ver sección 4). |
| **Cambio de contraseña** | `cambiar_password.php` | Pide contraseña actual, valida largo mínimo (6), hashea con `PASSWORD_DEFAULT`. Sin política de complejidad más allá del largo. |
| **POS / toma de pedidos** | `pedidos/nuevo.php`, `agregar_item.php`, `quitar_item.php`, `enviar_cocina.php`, `marcar_entregado.php`, `cancelar.php`, `cerrar.php`, `ticket.php` | Flujo de estados completo (abierto → en_preparación → entregado → cerrado, con cancelación en cualquier punto antes de cerrado). Grilla de productos con buscador y categorías tipo chip, "más vendidos" de los últimos 30 días. Cancelar devuelve stock. **Riesgo de condición de carrera real**: `agregar_item.php` lee `stock_actual` y recién después hace el `UPDATE ... SET stock_actual = stock_actual - ?` dentro de una transacción, pero sin `SELECT ... FOR UPDATE`; dos mozos agregando el mismo producto al mismo tiempo desde dos pedidos distintos pueden hacer que el chequeo de "stock suficiente" quede desactualizado entre la lectura y la escritura (`agregar_item.php:38-63`). |
| **Salón / mesas** | `mesas/salon.php`, `mesas/listar.php` (ABM) | 2 consultas (mesas + pedidos abiertos agrupados), sin N+1. Pinta el estado real según el pedido asociado, no solo el campo `mesas.estado`. |
| **Canal mostrador/mesa** *(ronda 8)* | `pedidos.canal`, seteado en `pedidos/nuevo.php` al crear, mostrado como badge, filtrable en Auditoría de pedidos y desglosado en el reporte de Ingresos y egresos | Funciona correctamente (probado end-to-end), pero **el dashboard (`dashboard.php`) no se actualizó** para mostrar un resumen por canal ni un acceso directo a Egresos/Ingresos y egresos — solo aparece en el menú de navegación. |
| **Medios de pago dinámicos** *(ronda 8)* | tabla `medios_pago`, ABM en `medios_pago/listar.php`, usado en `pedidos/cerrar.php`, `caja/cerrar.php`, todos los reportes | Reemplaza al ENUM fijo viejo. Migración de datos históricos documentada y probada (`migracion_ronda8.sql`). El medio "Efectivo" se identifica en código por **nombre** (`mb_strtolower($nombre) === 'efectivo'`, en `caja/cerrar.php` y `caja/historial.php`), no por un flag o ID fijo: si un admin renombra "Efectivo" a otra cosa desde el ABM, el cálculo de efectivo esperado en caja se rompe silenciosamente. Es una fragilidad real, no cubierta por validación. |
| **Stock** | `stock/movimientos.php` (listado, sin filtro ni paginación, `LIMIT 200` fijo), `stock/reponer.php` (carga de ingresos + historial por producto, `LIMIT 50`) | Movimientos por venta/ingreso/ajuste bien registrados con transacciones donde corresponde. Sin ABM de ajustes manuales de stock fuera de "reponer" (no hay forma de *bajar* stock manualmente salvo cancelando pedidos). |
| **Caja** | `caja/abrir.php`, `caja/cerrar.php`, `caja/historial.php` | Cierre recalculado en ronda 8: efectivo esperado = inicial + ventas efectivo − egresos efectivo, desglose completo por medio de pago vía `caja_sesion_medios`. Probado end-to-end con diferencia $0 exacta. Las columnas legadas `total_tarjeta`/`total_transferencia` quedan en `NULL` para cierres nuevos (correcto y documentado, pero un reporte viejo que las lea directamente de la tabla mostraría `-` para cualquier cierre posterior a la ronda 8). |
| **Egresos** *(ronda 8, nueva)* | `egresos/nuevo.php` (carga rápida con chips), `egresos/listar.php` (filtro + paginación real), `egresos/categorias.php` (ABM) | Única pantalla de listado del sistema con paginación real (ver sección 6). Asocia automáticamente `caja_sesion_id` si hay una caja abierta. |
| **Productos** | `productos/listar.php` + `guardar.php` | ABM vía modal. **Sin filtro ni paginación** (carga las 81 filas actuales enteras). `guardar.php` valida y, si la validación falla, redirige silenciosamente a `listar.php` sin mostrar ningún error al usuario (`productos/guardar.php:23-25`). |
| **Categorías** / **Categorías de egreso** / **Medios de pago** (ABM) | `categorias/listar.php`, `egresos/categorias.php`, `medios_pago/listar.php` | Mismo patrón simple los tres (tabla editable inline + form de alta), sin paginación (listas cortas). |
| **Reportes** | `reportes/ventas.php`, `productos_top.php`, `medios_pago.php`, `auditoria_pedidos.php`, `ingresos_egresos.php` *(nuevo, ronda 8)* | Todos con filtro de fecha + CSV + impresión. `ventas.php` y `medios_pago.php` usan Chart.js (vendorizado desde ronda 8, antes por CDN). `auditoria_pedidos.php` ahora también filtra por canal y muestra medio de pago (ronda 8). `ingresos_egresos.php` es el más completo: canal, medio de pago, categoría de egreso, balance neto, 2 gráficos de torta. |
| **Auditoría de pedidos** | `reportes/auditoria_pedidos.php` | Solo admin. Muestra quién cobró/canceló/entregó y cuándo. Ahora incluye canal y medio de pago (ronda 8). |

## 4. Seguridad — estado real

- **Sesiones**: `session_start()` con cookie `httponly`, `samesite=Lax`,
  `secure` solo si la conexión ya es HTTPS (`includes/auth.php:8-25`).
  `session_regenerate_id(true)` al loguear. Correcto.
- **Contraseñas**: `password_hash`/`password_verify` con `PASSWORD_DEFAULT`
  (bcrypt en PHP 8.3). Correcto. Sin política de complejidad, solo largo
  mínimo 6.
- **CSRF**: token de sesión (`bin2hex(random_bytes(32))`), verificado con
  `hash_equals()` en **todos** los POST que revisé (ABM, cierre de pedido,
  cierre de caja, egresos). Los endpoints AJAX (`agregar_item.php`,
  `quitar_item.php`, `enviar_cocina.php`, `marcar_entregado.php`) **no
  reutilizan `validarTokenCsrf()`** de `functions.php`: reimplementan la
  misma comprobación a mano (`if (empty($_SESSION['csrf_token']) || !hash_equals(...))`)
  porque necesitan responder JSON en vez de `die()` con texto plano. Es
  duplicación real, documentada en la sección 5, pero la protección en sí
  funciona igual en los cuatro archivos (verificado: un token falso devuelve
  403 tanto en el flujo con `validarTokenCsrf()` como en estos).
- **SQL injection**: no encontré ni un solo `query()`/`exec()` con variables
  interpoladas directamente en el SQL. Todo pasa por `prepare()` +
  `execute([...])` con placeholders `?`. Los pocos lugares que arman SQL con
  concatenación (`egresos/listar.php`, `caja/cerrar.php`) concatenan
  únicamente fragmentos de sintaxis fijos (columnas, `WHERE`, `LIMIT`), nunca
  valores de usuario — los valores siempre van en el array de `execute()`.
- **XSS**: `h()` (htmlspecialchars con ENT_QUOTES) se usa consistentemente en
  las 40+ vistas que revisé para cualquier dato que venga de la base o del
  usuario. Un solo lugar con `json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT)`
  para pasar un producto entero a un atributo `onclick` (`productos/listar.php:50`) —
  correcto, usa las flags necesarias para contexto HTML-atributo.
- **Protección de `config/` e `includes/`**: `.htaccess` con
  `Require all denied` en ambas carpetas, en ambos árboles. Además, el
  `.htaccess` de `public_html` bloquea la descarga directa de `.sql`, `.md`,
  `.txt` sueltos. Razonable para hosting compartido con Apache.
- **Límite de intentos de login**: **no existe**. `login.php` no lleva
  contador de intentos fallidos ni bloqueo temporal ni delay artificial. Un
  atacante puede probar contraseñas sin limitación (aparte de lo que el propio
  hosting compartido pueda limitar a nivel de red, que no es controlable desde
  la app).
- **Cabeceras HTTP de seguridad**: no hay ninguna cabecera explícita
  (`X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`,
  `Strict-Transport-Security`) seteada en PHP ni en `.htaccess`. Solo el
  comentario en `public_html/.htaccess` para forzar HTTPS vía `RewriteRule`,
  **comentado por defecto** (hay que descomentarlo a mano si el hosting tiene
  SSL).
- **Dependencias externas**: Bootstrap 5.3.3 (CSS y JS) sigue cargándose por
  CDN (`jsdelivr`) en **todas** las páginas vía `includes/header.php` y
  `includes/footer.php` — no vendorizado. Chart.js **sí** se vendorizó en la
  ronda 8 (`assets/js/chart.umd.min.js`, presente en ambos árboles) y los tres
  reportes que lo usan (`ventas.php`, `medios_pago.php`, `ingresos_egresos.php`)
  ya apuntan al archivo local en vez del CDN.

## 5. Calidad de código

- **Duplicación real y verificable**:
  - El bloque de validación de fecha `if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', ...))`
    está repetido, línea por línea idéntico salvo el default, en **6 archivos**:
    `egresos/listar.php:10-11`, `reportes/auditoria_pedidos.php:11-12`,
    `reportes/ingresos_egresos.php:10-11`, `reportes/medios_pago.php:11-12`,
    `reportes/productos_top.php:11-12`, `reportes/ventas.php:13-14`. Candidato
    obvio a una función `normalizarRangoFechas()` en `functions.php`.
  - La verificación de CSRF para JSON está reimplementada igual en 4 archivos
    (`pedidos/agregar_item.php:13`, `quitar_item.php`, `enviar_cocina.php`,
    `marcar_entregado.php`) en vez de una variante de `validarTokenCsrf()` que
    acepte devolver JSON.
  - El patrón de ABM simple (tabla editable + form de alta) está triplicado
    casi textual entre `categorias/listar.php`, `medios_pago/listar.php` y
    `egresos/categorias.php` — intencional por simplicidad, pero es la misma
    lógica copiada tres veces.
  - La selección de "chips" por JS (categoría/producto) está reimplementada
    de forma distinta en `pedidos/nuevo.php` (categorías + búsqueda) y
    `egresos/nuevo.php` (categoría + medio de pago), sin compartir código.
- **Funciones largas**: `pedidos/nuevo.php` (447 líneas, mezcla de PHP + HTML
  + JS inline) es el archivo más largo del sistema con diferencia. No es una
  "función" en sentido estricto (PHP procedural por archivo), pero concentra
  demasiadas responsabilidades en un solo script: resolución de pedido,
  grilla de productos, buscador, modal de cantidad, y todo el JS de
  interacción.
- **Manejo de errores**: dos patrones conviven sin unificar — (a) `try/catch`
  con `$pdo->rollBack()` manual y `error_log()` (la mayoría de los endpoints
  con transacción), y (b) el helper nuevo `ejecutarTransaccion()` (agregado en
  ronda 8, en `includes/functions.php`), usado hoy solo en `pedidos/cerrar.php`
  y `caja/cerrar.php`. El resto de los endpoints con transacción
  (`agregar_item.php`, `stock/reponer.php`) siguen con el patrón manual viejo:
  no se migraron a `ejecutarTransaccion()` en la ronda 8 porque no era parte
  del alcance pedido, pero queda como inconsistencia real en el código.
- **Nombres**: consistentes en español en todo el proyecto (`obtenerConexion`,
  `requerirLogin`, `formatearMoneda`, etc.), sin mezcla de idiomas salvo
  términos técnicos estándar (`FILTER_VALIDATE_FLOAT`, nombres de columnas SQL).

## 6. Rendimiento

- **N+1**: no encontré ningún N+1 real en las pantallas revisadas.
  `mesas/salon.php` resuelve mesas + pedidos abiertos en 2 consultas para
  cualquier cantidad de mesas. Los reportes agregan con `GROUP BY` en SQL en
  vez de traer todo y sumar en PHP.
- **Filtros y paginación por pantalla** (relevado uno por uno):
  - **Con paginación real**: solo `egresos/listar.php` (30 por página, con
    `COUNT(*)` separado y `LIMIT`/`OFFSET`).
  - **Con filtro pero sin paginación** (traen todo el rango elegido de una):
    `reportes/ventas.php`, `reportes/productos_top.php`,
    `reportes/medios_pago.php`, `reportes/auditoria_pedidos.php`,
    `reportes/ingresos_egresos.php`. Aceptable mientras el volumen de pedidos
    por rango de fechas sea manejable; sin límite superior si alguien elige
    un rango de meses con mucho movimiento.
  - **Sin filtro ni paginación, traen todo siempre**: `productos/listar.php`
    (81 productos hoy), `categorias/listar.php`, `mesas/listar.php`,
    `medios_pago/listar.php`, `egresos/categorias.php` (listas chicas, no es
    un problema real todavía pero no escala).
  - **Con `LIMIT` fijo sin paginación** (no hay forma de ver más allá):
    `caja/historial.php` (`LIMIT 100`), `stock/movimientos.php` (`LIMIT 200`),
    `stock/reponer.php` historial por producto (`LIMIT 50`).
- **Índices vs. consultas reales**: los índices existentes
  (`idx_pedidos_estado`, `idx_pedidos_cerrado_en`, `idx_pedidos_canal`,
  `idx_productos_categoria`, `idx_productos_activo`, `idx_mov_producto`,
  `idx_mov_creado`, `idx_egresos_creado`, `idx_egresos_caja`) cubren las
  columnas que efectivamente se filtran en `WHERE`/`GROUP BY` en los reportes
  y listados revisados. No detecté un `WHERE` sobre una columna sin índice en
  una tabla grande. `pedidos.medio_pago_id` y `egresos.medio_pago_id`/`categoria_id`
  **no tienen índice propio** (solo el implícito de la FK, que MySQL/MariaDB sí
  indexa automáticamente) — no es un problema con el volumen actual.

## 7. UI/UX

- Bootstrap 5.3.3 en toda la app, paleta de marca aplicada vía CSS custom
  (`assets/css/style.css`) sobre `#8B2E2E` (marrón-rojizo) y tonos crema,
  visible en botones, chips de categoría y estados de mesa.
- Responsive: hay un bloque `@media` en `style.css` (línea ~450 en adelante)
  que ajusta tamaño de chips de producto, tabs de categoría y botones grandes
  para pantallas chicas — pensado para que mozos usen esto desde un celular o
  tablet en el salón.
- Botones grandes táctiles (`btn-lg-touch`) usados consistentemente en
  formularios de una sola acción importante (abrir caja, cerrar caja, cobrar,
  registrar egreso).
- **Inconsistencia real**: `dashboard.php` no tiene accesos directos a
  Egresos ni al reporte de Ingresos y egresos (solo están en el menú de
  navegación superior), a pesar de que sí tiene accesos directos a Salón,
  Stock, Caja y Productos. Los mismos criterios de "acceso rápido desde
  Inicio" que se aplicaron a esas cuatro pantallas no se replicaron para las
  funcionalidades nuevas de la ronda 8.
- `productos/guardar.php` no muestra ningún mensaje de error al usuario si la
  validación falla (ver sección 3): la experiencia es "no pasó nada, se
  recargó la lista", sin explicación.

## 8. Testing

- **No hay ningún test automatizado** (no hay PHPUnit, no hay carpeta
  `tests/`, no hay ningún archivo `*Test.php`).
- **No hay ningún script de pruebas versionado** en el repo (ni end-to-end ni
  de humo).
- La evidencia real de testing que existe está en la memoria de esta sesión
  de trabajo (ronda 8), no en el repo: se corrió manualmente, contra una base
  MariaDB real levantada para la ocasión (XAMPP, puerto 3308, datos de prueba
  descartables), un flujo completo con `Invoke-WebRequest` de PowerShell:
  login → abrir caja → pedido mostrador cobrado con un medio → pedido de mesa
  cobrado con otro medio → carga de un egreso en efectivo → cierre de caja
  (diferencia $0 verificada) → reporte de ingresos/egresos → exportación CSV →
  ABM de medios de pago → rechazo de CSRF inválido (403). Nada de esto quedó
  grabado como script reutilizable en el repo: si se pierde el historial de
  esta conversación, se pierde también el detalle de qué se probó (por eso
  esto queda documentado acá y en `MEMORY.md`).
- **Nunca se probó** (ni en esta ronda ni evidencia de que se haya probado
  antes): comportamiento bajo carga concurrente real (el riesgo de
  `agregar_item.php` de la sección 3 es un riesgo teórico identificado por
  lectura de código, no reproducido con un test de concurrencia real),
  reponer stock con cantidades que crucen a negativo, ni ningún flujo de
  cancelación de pedido combinado con caja cerrada.

## 9. Estado de deploy

- `DEPLOY_HOSTINGER/` está **sincronizado** con `_dev_no_subir/public_html/`
  para todo lo tocado en la ronda 8 (verificado archivo por archivo con un
  diff que tiene en cuenta la única diferencia esperada: la profundidad de
  los `require __DIR__ . '/../includes/...'`). No hay script versionado que
  automatice esta verificación a futuro (ver sección 1).
- `DEPLOY_HOSTINGER/config/config.php` tiene **credenciales reales de
  producción** de Hostinger (host, base, usuario y contraseña), confirmado al
  leer el archivo — está correctamente listado en `.gitignore`
  (`**/config/config.php`), así que no está en el historial de git, pero
  existe en disco tal cual con esos datos reales.
- **Migraciones pendientes de aplicar en la base de producción real**: no se
  puede verificar desde este entorno si la base de Hostinger
  (`u269501740_elbatara`) ya tiene aplicada la ronda 6, la ronda 8, ninguna, o
  parte de alguna. Antes de subir el código nuevo de `DEPLOY_HOSTINGER/` a
  producción hay que:
  1. Confirmar contra esa base real qué migraciones ya corrieron (por
     ejemplo, `SHOW COLUMNS FROM pedidos` para ver si existe `medio_pago_id`
     o todavía el `medio_pago` ENUM viejo).
  2. Correr `migracion_ronda6.sql` y/o `migracion_ronda8.sql` en el orden
     que corresponda, **solo** si faltan.
  3. Hacer un backup manual desde phpMyAdmin antes de correr cualquier
     migración en producción (documentado en `INSTALL.md`).

## 10. Deuda técnica — TODOs, pendientes y placeholders explícitos

Buscado explícitamente en todo el repo (`TODO`, `FIXME`, `pendiente`,
`revisar`, `confirmar`, más los marcadores propios del negocio):

- **`database.sql:346`**: precio de "Costeletas de ternera con papas fritas"
  marcado `-- revisar: precio de version anterior de la carta`.
- **`database.sql:416`**: "Papas fritas (bastón) c/cheddar" a $3.000, marcado
  `-- revisar: precio parece inconsistente (menor que la version sin cheddar)`.
- **`database.sql:446`**: "Cerro Callejero tinto" marcado
  `-- revisar: nombre parcialmente tapado en la foto`.
- **`database.sql:465-471`**: cuatro productos de cerveza en lata (Andes Roja,
  Stella Artois, Stella Artois Negra, Andes Roja de Litro) cargados con
  `precio = 0.00` y `activo = 0`, marcados
  `-- PENDIENTE: falta precio, inactivo hasta confirmar`. No aparecen en el
  POS hasta que se activen a mano desde Productos.
  - **`database.sql:~312`** (comentario en el bloque "CARTA REAL"): falta
    cargar la categoría "Embutidos curados en grasa de cerdo" completa (foto
    de la carta no legible al momento de cargar los datos) — pendiente real,
    sin tabla ni productos creados para ella todavía.
- **Sin TODO/FIXME de código** propiamente dichos: no encontré comentarios de
  ese estilo marcando deuda técnica de implementación (aparte de lo ya
  documentado en las secciones 3-7 de este relevamiento, que sí es deuda real
  pero no estaba señalada con esos marcadores en el código).
- **Deuda no marcada en comentarios pero real** (para no repetirla, resumen
  de lo ya detallado arriba): sin límite de intentos de login, sin cabeceras
  de seguridad HTTP, sin índice de sincronización automatizado entre árboles,
  sin tests automatizados, duplicación de validación de fechas en 6 reportes,
  posible condición de carrera en `agregar_item.php`, dependencia de
  `mb_strtolower($nombre) === 'efectivo'` para identificar el medio de pago
  efectivo en los cálculos de caja.
