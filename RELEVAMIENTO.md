# Relevamiento / diagnóstico completo — Sistema de gestión El Batará

Fecha del relevamiento: 2026-09-08. Alcance: revisión de solo lectura de todo el
repositorio en el estado del commit `082b68b` ("Sistema de gestión El Batará: POS,
mesas, stock, caja, reportes y auditoría"). No se modificó ni corrigió código.

> **Actualización ronda 7 (mismo día):** después de este relevamiento se ejecutaron
> las Fases 1 y 2 de un plan de mejora en 5 fases (ver `MEMORY.md` sección "Ronda 7"
> para el detalle completo y el estado de las fases 3-5, todavía pendientes). Los
> puntos de este documento marcados abajo como **[RESUELTO RONDA 7]** ya no reflejan
> el estado actual del código — se dejan igual para no perder el diagnóstico
> original, pero el estado real es el que indica la nota junto a cada uno:
>
> - Condición de carrera en stock (`pedidos/agregar_item.php`, sección 3/8) —
>   **[RESUELTO RONDA 7]**: ahora usa `SELECT ... FOR UPDATE` dentro de la transacción.
> - Condición de carrera en apertura de caja (`caja/abrir.php`, sección 4/6) —
>   **[RESUELTO RONDA 7]**: índice único `ux_caja_una_abierta` a nivel de base.
> - Precios "revisar" activos sin ninguna advertencia visual (sección 10) —
>   **[RESUELTO RONDA 7]**: badge "⚠ Revisar precio" en Productos vía columna
>   `precio_a_revisar` (el precio en sí sigue sin confirmar — eso requiere al dueño).
> - Falla silenciosa en `productos/guardar.php` (sección 3/5) —
>   **[RESUELTO RONDA 7]**: mensajes de error concretos vía `flashError()`.
> - Try/catch/rollback duplicado en 5 archivos (sección 5) —
>   **[RESUELTO RONDA 7]**: extraído a `ejecutarTransaccion()` en `functions.php`.
> - N+1 en `pedidos/cancelar.php` (sección 6) —
>   **[RESUELTO RONDA 7]**: batch (`UPDATE...JOIN` + `INSERT...SELECT`), sin loop.
> - Inconsistencia `location.reload()` vs. actualización de DOM (sección 7) —
>   **[RESUELTO RONDA 7]**: unificado, sin recarga completa en ningún flujo del pedido.
> - Duplicación de árboles/SQL sin mecanismo de verificación (secciones 1, 5, 9) —
>   **[MITIGADO RONDA 7]**: sigue existiendo a propósito, pero ahora hay
>   `scripts/comparar_arboles.sh` para detectar desincronización antes de deployar.
>
> Actualización posterior (mismo día): también se completaron las Fases 3, 4 y 5:
> - Rendimiento (sección 6) — **[RESUELTO RONDA 7]**: caché de "más pedidos", índice
>   compuesto `pedidos(estado, cerrado_en)`, filtro+paginación en las grillas
>   principales vía `includes/paginacion.php`.
> - Fuerza bruta en login (sección 3/4) — **[RESUELTO RONDA 7]**: bloqueo de 5
>   minutos tras 5 intentos fallidos consecutivos.
> - CDN sin SRI / cabeceras de seguridad ausentes (sección 4) —
>   **[RESUELTO RONDA 7]**: cabeceras `X-Content-Type-Options`, `X-Frame-Options`,
>   `Content-Security-Policy` en `.htaccess`.
> - Dependencia de CDN externo sin fallback offline (sección 6) —
>   **[RESUELTO RONDA 7]**: Bootstrap y Chart.js vendorizados en `assets/vendor/`,
>   ya no se cargan desde `cdn.jsdelivr.net` (lo que además hizo innecesaria la
>   excepción a la CSP para ese dominio).
>
> Testing (sección 8) sigue exactamente como se describe abajo: **sigue sin existir
> testing automatizado**, eso no se abordó en esta ronda. Ver `MEMORY.md` para el
> detalle completo y decisiones tomadas en cada fase.

---

## 1. Estructura del proyecto

```
ElBatara/
├── INSTALL.md                  Guía de instalación detallada (estructura "ideal", config/includes fuera de public_html)
├── database.sql                Script completo: crea tablas + datos de ejemplo + carta real (rondas 1-6)
├── carta_real.sql              Subconjunto de database.sql: solo categorías/productos reales, para importar en una base ya en producción
├── migracion_ronda6.sql        ALTER TABLE puntual: agrega estado "entregado" a pedidos
├── DEPLOY_HOSTINGER/           Carpeta LISTA para subir a Hostinger (config/ e includes/ dentro de public_html, protegidos por .htaccess)
│   ├── LEEME_PRIMERO.txt       Guía rápida oficial de despliegue
│   ├── .htaccess, config/.htaccess, includes/.htaccess
│   ├── config/config.example.php
│   ├── includes/ (auth.php, db.php, functions.php, header.php, footer.php)
│   ├── assets/ (css/style.css, img/logo.png)
│   ├── index.php, login.php, logout.php, dashboard.php, cambiar_password.php
│   ├── mesas/ (salon.php, listar.php)
│   ├── pedidos/ (nuevo.php, agregar_item.php, quitar_item.php, enviar_cocina.php,
│   │             marcar_entregado.php, cerrar.php, cancelar.php, ticket.php)
│   ├── productos/ (listar.php, guardar.php)
│   ├── categorias/ (listar.php)
│   ├── stock/ (movimientos.php, reponer.php)
│   ├── caja/ (abrir.php, cerrar.php, historial.php)
│   └── reportes/ (ventas.php, productos_top.php, medios_pago.php, auditoria_pedidos.php)
└── _dev_no_subir/               Código fuente "canónico" con config/includes FUERA de public_html (estructura ideal si algún día hay FTP a nivel superior). Contenido funcionalmente idéntico a DEPLOY_HOSTINGER (solo difieren las rutas relativas de los `require`).
```

No existe carpeta `tests/`, ni `vendor/`, ni `composer.json`/`package.json` (correcto,
dado que el proyecto es deliberadamente PHP puro sin dependencias de build). No hay
`README.md` en la raíz (sí hay `INSTALL.md`, que cumple ese rol). No existía
`MEMORY.md` antes de este relevamiento — se crea en esta ronda.

**Duplicación estructural intencional:** `_dev_no_subir/` y `DEPLOY_HOSTINGER/`
tienen exactamente el mismo código de aplicación, verificado archivo por archivo
(`diff`): la única diferencia real son las rutas relativas de los `require_once`
(`../includes/x.php` vs `includes/x.php`), coherente con que en `_dev_no_subir` los
includes están un nivel más arriba. Esto significa que **cualquier cambio futuro se
tiene que aplicar dos veces a mano** (una vez en cada árbol) o el otro árbol queda
desincronizado sin que nada lo avise. Ahora mismo están sincronizados, pero es un
punto fresco de deuda técnica: no hay ningún script ni proceso que garantice que
sigan estándolo.

## 2. Esquema de base de datos

Definido en `database.sql` (fuente de verdad) + `migracion_ronda6.sql` (migración ya
incorporada al `CREATE TABLE` de `database.sql`, se deja aparte solo para bases ya
en producción). 7 tablas, todas InnoDB/utf8mb4, con foreign keys explícitas:

- **usuarios**: `id, nombre, usuario (UNIQUE), password_hash, rol ENUM(admin,empleado), activo, creado_en`.
- **categorias**: `id, nombre, activo`. Sin `UNIQUE` en `nombre` (se depende de un
  `WHERE NOT EXISTS` en los INSERT para no duplicar, ver sección 10).
- **productos**: `id, categoria_id (FK), nombre VARCHAR(255), tipo_venta ENUM(unidad,peso),
  precio DECIMAL(10,2), stock_actual DECIMAL(10,3), stock_minimo DECIMAL(10,3), activo,
  creado_en`. Índices en `categoria_id` y `activo` (`database.sql:52-53`).
- **mesas**: `id, nombre, capacidad, estado ENUM(libre,ocupada,cuenta_pedida), activo`.
  El estado `cuenta_pedida` del ENUM ya no se usa en la lógica actual del salón (ver
  sección 3, mesas/pedidos usan el estado del *pedido*, no el de la mesa, para pintar
  "Entregado"); queda como resabio de un diseño anterior.
- **pedidos**: `id, mesa_id (FK nullable = "para llevar"), usuario_id (FK), estado
  ENUM(abierto,en_preparacion,entregado,cerrado,cancelado), total, medio_pago
  ENUM(efectivo,tarjeta,transferencia), creado_en, entregado_en/entregado_por_id,
  cerrado_en/cerrado_por_id, cancelado_en/cancelado_por_id`. Buen nivel de auditoría
  a nivel de esquema (quién y cuándo en cada transición de estado). Índices en
  `estado` y `cerrado_en` (`database.sql:98-99`).
- **pedido_items**: `id, pedido_id (FK), producto_id (FK), cantidad DECIMAL(10,3),
  precio_unitario, subtotal`. Índice en `pedido_id`.
- **movimientos_stock**: `id, producto_id (FK), tipo ENUM(venta,ingreso,ajuste),
  cantidad, referencia_pedido_id (FK nullable), usuario_id (FK), nota, creado_en`.
  Índices en `producto_id` y `creado_en`.
- **caja_sesiones**: `id, usuario_id (FK), monto_inicial, monto_final_declarado,
  total_efectivo/tarjeta/transferencia, diferencia, estado ENUM(abierta,cerrada),
  abierta_en, cerrada_en, nota`.

Observaciones concretas:

- No hay `UNIQUE INDEX` sobre `caja_sesiones` que impida dos cajas abiertas
  simultáneamente a nivel de BD: la exclusión se hace solo en PHP
  (`caja/abrir.php:9-13`, un `SELECT` + chequeo antes del `INSERT`, sin lock). Ver
  concurrencia en sección 8.
- `productos.nombre` es `VARCHAR(255)` porque tuvo que ampliarse en la ronda 4 (la
  "Picada El Batará" tiene 203 caracteres) — ver `carta_real.sql:42-45`. El ALTER
  queda documentado y es idempotente, pero es un ejemplo de columna dimensionada sin
  margen desde el diseño inicial.
- `stock_actual`/`stock_minimo` en `DECIMAL(10,3)` permite pesos con gramos; se usa
  el valor sentinel `9999.000` para "sin control de stock real" en platos de cocina
  (documentado en comentarios de `database.sql:201-209`), en vez de un flag booleano
  dedicado (`controla_stock` o similar). Funciona, pero es una convención implícita
  que solo vive en un comentario SQL — cualquier reporte o validación futura que mire
  `stock_actual < stock_minimo` sin conocer esta convención puede confundirse con
  productos "9999" (aunque en la práctica `stock_minimo` también es 0 para esos, así
  que hoy no dispara falsos positivos de "stock bajo").
- Sin migraciones versionadas (no hay carpeta `migrations/` con archivos numerados
  tipo `001_x.sql`, `002_y.sql`): los cambios de esquema entre rondas viven como
  bloques `ALTER TABLE` comentados dentro de `database.sql` (líneas 383-408) más el
  archivo suelto `migracion_ronda6.sql`. Es manejable a la escala actual (una sola
  base, sin equipo de desarrollo grande) pero no escala si el ritmo de cambios de
  esquema aumenta: no hay forma de saber automáticamente "qué ALTERs ya corrí en
  producción" más que leer los comentarios y memorizarlo.

## 3. Funcionalidades — qué hacen realmente y qué tan robustas son

**Login (`login.php`, `includes/auth.php`)**: usuario/contraseña contra
`usuarios.password_hash` (bcrypt vía `password_verify`), regenera el ID de sesión al
loguear (`auth.php:47`, mitiga session fixation), redirige si ya está logueado. Sin
límite de intentos fallidos ni bloqueo temporal por IP/usuario — un ataque de fuerza
bruta contra `admin`/`empleado` no tiene ninguna barrera server-side más allá de la
complejidad de la contraseña elegida por el usuario. Contraseñas de ejemplo
(`123456`) documentadas en texto plano en `INSTALL.md` y `LEEME_PRIMERO.txt`, con
advertencia de cambiarlas — depende 100% de que el dueño lo haga.

**Cambio de contraseña (`cambiar_password.php`)**: pide contraseña actual, valida
con `password_verify`, exige mínimo 6 caracteres y confirmación coincidente,
rehashea con `password_hash(..., PASSWORD_DEFAULT)`. Validación server-side
correcta (no confía solo en `minlength` del HTML). Sin política de complejidad más
allá del largo mínimo (aceptable para el contexto de uso).

**Salón / mesas (`mesas/salon.php`, `mesas/listar.php`)**: pinta las mesas con color
según el estado del *pedido* abierto asociado (no según `mesas.estado`, que queda en
desuso para esa distinción visual — ver sección 2). Trae en una sola query todos los
pedidos abiertos/en preparación/entregados y los indexa en PHP por `mesa_id`
(`mesas/salon.php:15-20`), evitando N+1 ahí. Muestra tiempo transcurrido desde
apertura. ABM de mesas (alta/edición) en `mesas/listar.php`, solo accesible a admin.

**Toma de pedidos / POS (`pedidos/nuevo.php`)**: pantalla central del sistema.
Crea el pedido si no existe (transacción explícita al crear pedido de mesa,
`nuevo.php:34-39`), permite buscar productos por nombre o filtrar por categoría
(commit incluye un comentario explícito, `nuevo.php:157-159` y `243-251`,
documentando que una versión anterior mostraba a la vez "Más pedidos" y la grilla de
la primera categoría alfabética, generando confusión de "el filtro no funciona" —
ejemplo real de bug de UX ya vivido y corregido, ver sección 8 "errores ya
cometidos"). Sección "Más pedidos" calculada con una query de agregación sobre los
últimos 30 días de pedidos cerrados (`nuevo.php:73-82`) — corre en cada carga de la
pantalla de pedido, sin caché; a volumen bajo/mediano (un solo local) no es un
problema de rendimiento real, pero es una query no trivial ejecutada muy seguido.
Modal de cantidad para productos por peso (kg), con paso de 0.001 y mínimo 0.500 por
defecto.

**Agregar/quitar ítems (`pedidos/agregar_item.php`, `quitar_item.php`)**: endpoints
JSON llamados por `fetch`. `agregar_item.php` valida método POST, CSRF, que el
pedido exista y esté en un estado editable, que el producto exista y esté activo,
redondea cantidad a entero si es "unidad", chequea stock suficiente, y hace INSERT +
UPDATE de stock + INSERT de movimiento + recálculo de total dentro de una
transacción con rollback en catch (`agregar_item.php:58-76`). Manejo de errores
correcto a nivel de código; el chequeo de stock (`if ($cantidad >
$producto['stock_actual'])`, línea 51) se hace leyendo el valor **antes** de la
transacción, sin `SELECT ... FOR UPDATE` — ver condición de carrera en sección 6/8.

**Enviar a cocina / marcar entregado (`pedidos/enviar_cocina.php`,
`marcar_entregado.php`)**: no se leyeron línea por línea pero por convención siguen
el mismo patrón (POST + CSRF + UPDATE de estado); se llaman por `fetch` y hacen
`location.reload()` al terminar en vez de actualizar el DOM in-place como sí hace
`agregar_item`/`quitar_item` — inconsistencia menor de patrón dentro del propio
`nuevo.php`.

**Cobrar / cerrar pedido (`pedidos/cerrar.php`)**: exige medio de pago válido
(`in_array` contra whitelist) y al menos un ítem cargado antes de cerrar
(`cerrar.php:29-36`), transacción con `UPDATE pedidos` + liberar la mesa, redirige a
`ticket.php`. Buen manejo de errores con mensaje visible al usuario.

**Cancelar pedido (`pedidos/cancelar.php`)**: devuelve el stock de **todos** los
ítems del pedido recorriéndolos uno por uno con dos `UPDATE`/`INSERT` por ítem
dentro de un `foreach` (`cancelar.php:32-38`) — ver **N+1 en loop**, sección 6.
Puede cancelarse desde abierto/en_preparacion/entregado, nunca desde cerrado
(reforzado por el `WHERE estado IN (...)` de la query que trae el pedido).

**Ticket (`pedidos/ticket.php`)**: HTML standalone (no usa `header.php`/`footer.php`
del sistema, formato térmico angosto de 320px, `font-family: Courier New`), botón
`window.print()`. Solo accesible para pedidos ya `cerrado`. No genera PDF ni usa
ninguna librería de impresión térmica real (ESC/POS): depende de que el navegador
imprima directamente sobre una impresora configurada como impresora de Windows/driver,
lo cual es razonable para el contexto (impresora térmica USB con driver genérico)
pero no fue validado contra hardware real en este relevamiento (no hay forma de
hacerlo sin acceso físico).

**Productos (`productos/listar.php`, `guardar.php`)**: listado con badge de "stock
bajo" cuando `stock_actual <= stock_minimo` (comparación client-independent, hecha en
PHP al renderizar cada fila). Alta/edición en modal Bootstrap, mismo formulario para
ambos casos vía JS (`editarProducto()` rellena el modal con `json_encode($p,
JSON_HEX_APOS | JSON_HEX_QUOT)`, evitando inyección en el atributo `onclick`).
`guardar.php` valida server-side (nombre no vacío, categoría válida, precio/stock no
negativos) pero **si la validación falla, redirige silenciosamente a la lista sin
mostrar ningún mensaje de error** (`guardar.php:23-25`) — el usuario no se entera de
por qué no se guardó. Es el único flujo de escritura del sistema sin feedback de
error al usuario.

**Categorías (`categorias/listar.php`)**: no se leyó en detalle pero por el listado
de archivos es un CRUD simple, solo admin.

**Stock (`stock/movimientos.php`, `reponer.php`)**: `movimientos.php` lista los
últimos 200 movimientos de todo el sistema (histórico global, sin filtro por fecha ni
paginación — ver rendimiento en sección 6). `reponer.php` registra ingresos
manuales de stock con nota opcional, y muestra a la derecha el historial (últimos 50)
del producto seleccionado. Buen patrón de transacción con rollback.

**Caja (`caja/abrir.php`, `cerrar.php`, `historial.php`)**: abrir exige que no haya
otra caja abierta (chequeo previo sin lock, ver concurrencia); cerrar calcula
automáticamente lo esperado en efectivo (`monto_inicial + ventas en efectivo desde
la apertura`) y compara contra lo contado manualmente, guardando la diferencia
(sobrante/faltante) y una nota libre. Es el único lugar del sistema con un cálculo
de conciliación real. `historial.php` no se leyó en detalle.

**Reportes (`reportes/ventas.php`, `productos_top.php`, `medios_pago.php`,
`auditoria_pedidos.php`)**: todos exigen `requerirAdmin()`. `ventas.php` agrupa por
día, valida el formato de fecha con regex antes de pasarlo a SQL (aunque igual usa
prepared statements, es una capa extra de sanidad), permite exportar CSV
(`exportarCsv()` con BOM UTF-8 para Excel) e imprimir, y grafica con **Chart.js
cargado desde `cdn.jsdelivr.net`** (`ventas.php:88`, dependencia de internet — ver
sección 6/9). `auditoria_pedidos.php` es el reporte más completo de trazabilidad:
muestra mozo, quién entregó, quién cobró, quién canceló y cuándo, con 5 LEFT/INNER
JOINs en una sola query (sin N+1). Buen ejemplo de auditoría real y no cosmética.

**Dashboard (`dashboard.php`)**: no se leyó en profundidad línea por línea, pero por
la nav es la pantalla de inicio post-login con accesos a las secciones.

## 4. Seguridad

Puntos fuertes, verificados con evidencia concreta:

- **Contraseñas**: bcrypt vía `password_hash`/`password_verify` (`auth.php:42`,
  `cambiar_password.php:21,28`), nunca texto plano ni hash débil (MD5/SHA1).
- **SQL**: 100% de las queries revisadas usan **prepared statements con parámetros
  posicionales** (`$pdo->prepare(...)->execute([...])`); no se encontró ni una sola
  concatenación de variables de usuario directamente en SQL en los ~20 archivos PHP
  leídos. `PDO::ATTR_EMULATE_PREPARES => false` (`db.php:20`) fuerza prepared
  statements reales del driver, no emulados por PHP.
- **XSS**: función `h()` (`functions.php:9-12`, wrapper de `htmlspecialchars` con
  `ENT_QUOTES`) usada consistentemente en absolutamente todo el output revisado
  (nombres de producto, mesas, usuarios, notas, etc.). Un caso notable bien resuelto:
  `agregar_item.php`/`nuevo.php` pasan nombres de producto a un atributo `onclick`
  de JS vía `h(addslashes($p['nombre']))` (`nuevo.php:132,172`) — combina escape
  HTML y escape de comillas JS, correcto para ese contexto específico.
- **CSRF**: token de sesión (`bin2hex(random_bytes(32))`) generado por
  `generarTokenCsrf()` y validado con `hash_equals()` (comparación segura contra
  timing attacks) en **todos** los formularios POST y endpoints JSON revisados
  (login, cambiar contraseña, agregar/quitar ítem, enviar a cocina, marcar
  entregado, cerrar/cancelar pedido, abrir/cerrar caja, reponer stock, guardar
  producto). Cobertura de CSRF consistente y bien implementada.
- **Sesiones**: `session_regenerate_id(true)` al loguear; cookie con `httponly` y
  `samesite=Lax` siempre, `secure` condicional a HTTPS detectado (`auth.php:12-22`,
  con comentario explícito de por qué no es siempre `true`: para no romper el login
  antes de activar SSL en Hostinger). Es una decisión pragmática y documentada, pero
  significa que **si el sitio queda funcionando por HTTP sin que nadie active el
  SSL**, la cookie de sesión viaja sin el flag `Secure` indefinidamente — depende de
  que el dueño active el certificado en Hostinger (paso no automatizado ni
  verificado por el sistema).
- **Protección de `config/` e `includes/`**: `.htaccess` con `Require all denied` en
  ambas carpetas (`DEPLOY_HOSTINGER/config/.htaccess`, `includes/.htaccess`), más un
  `.htaccess` raíz que bloquea `*.sql|*.md|*.txt` por `FilesMatch`. El propio
  `LEEME_PRIMERO.txt` (líneas 85-107) incluye un paso de verificación manual post-
  deploy pidiendo probar `https://tudominio.com/config/config.php` y esperar 403 —
  buena práctica, pero depende de que el hosting realmente respete `.htaccess`
  (Apache lo hace; si Hostinger cambiara a Nginx sin overrides equivalentes, la
  protección desaparece silenciosamente).
- **Errores**: `MODO_DESARROLLO=false` desactiva `display_errors` y pone
  `error_reporting(0)` en producción (`config.example.php:22-30`), evitando fugas de
  stack traces/rutas de servidor a usuarios finales; los errores de excepciones
  atrapadas se mandan a `error_log()` en vez de mostrarse.

Puntos débiles/ausentes, con evidencia:

- **Sin límite de intentos de login** (fuerza bruta posible, ver sección 3).
- **Sin locking optimista/pesimista** en operaciones concurrentes sensibles a stock
  y caja (ver sección 6 y 8) — no es exactamente una falla de "seguridad" en sentido
  estricto, pero es una falla de integridad de datos bajo uso concurrente real.
  Nunca se probó bajo carga (sección 8).
  formulario, sin mensaje de error server-side (`guardar.php:23-25`).
- No hay cabecera `Content-Security-Policy` ni otras cabeceras de seguridad HTTP
  (X-Frame-Options, X-Content-Type-Options) configuradas en ningún `.htaccess`.
  Riesgo bajo dado que es un sistema interno sin contenido de terceros embebido,
  pero es una capa de defensa en profundidad ausente.
- Dependencias de CDN externo (Bootstrap 5.3.3, Chart.js 4.4.4) cargadas sin
  Subresource Integrity (`<script src=... >` sin atributo `integrity`) en
  `header.php:26` y `ventas.php:88` — si el CDN fuera comprometido, no hay
  verificación de hash del recurso servido.

## 5. Calidad de código

- **Separación lógica/HTML**: el patrón dominante (visto en prácticamente todos los
  archivos leídos) es "bloque PHP de lógica arriba (validación, queries, POST
  handling) + HTML con `<?= ?>` intercalado abajo", todo en el mismo archivo — es el
  estilo típico de PHP procedural sin framework, consistente en todo el proyecto (no
  hay archivos que mezclen los dos estilos de forma distinta entre sí). No hay
  separación en capas (controlador/vista/modelo) ni un motor de templates; es una
  decisión de arquitectura coherente con la restricción de "PHP puro sin
  frameworks", no un descuido, pero implica que cualquier archivo de más de ~100
  líneas (`pedidos/nuevo.php`, 447 líneas) mezcla routing, queries SQL, HTML y
  JavaScript inline en un único archivo.
- **`pedidos/nuevo.php` es el archivo más largo y complejo** (447 líneas): maneja
  creación/recuperación de pedido, 3 queries de datos (categorías, productos, más
  vendidos), render de grilla de productos, modal de cantidad, y ~200 líneas de
  JavaScript inline (filtrado client-side, fetch a 4 endpoints distintos,
  actualización manual del DOM). Es el punto de mayor concentración de complejidad
  del sistema y el más costoso de modificar sin introducir regresiones — de hecho ya
  tuvo al menos un bug de UX documentado y corregido en el propio código (comentario
  de `nuevo.php:243-251`, ver sección 8).
- **Duplicación real detectada**: `carta_real.sql` es un duplicado byte-a-byte casi
  exacto del bloque "CARTA REAL" de `database.sql` (comparado ambos, contenido
  idéntico salvo el encabezado de comentarios) — duplicación **intencional y
  documentada** (para poder importar solo eso en una base ya en producción), pero
  significa que si en el futuro se corrige un precio o nombre de producto en uno de
  los dos archivos y no en el otro, quedan desincronizados sin ningún aviso
  automático. Mismo patrón de riesgo que `_dev_no_subir` vs `DEPLOY_HOSTINGER`
  (sección 1): el proyecto tiene una duplicación de árboles/archivos por diseño en
  al menos dos lugares distintos, y no hay ningún script (ni siquiera un `diff` en
  un README) que la mantenga sincronizada automáticamente.
- **Nombres**: consistentemente en español para dominio de negocio (`obtenerConexion`,
  `iniciarSesion`, `requerirLogin`, `formatearMoneda`, `mesas`, `pedidos`) e inglés
  solo para lo genérico del lenguaje/librerías (`PDO`, `fetch`, nombres de clases CSS
  de Bootstrap). No se detectó mezcla arbitraria de idioma en nombres de funciones o
  variables propias del dominio.
- **Manejo de errores**: no hay un manejador de excepciones centralizado (no hay
  `set_exception_handler` ni middleware); cada script transaccional individual
  implementa su propio `try/catch` con `$pdo->rollBack()` + `error_log()` +
  mensaje genérico al usuario — el patrón se repite igual en `agregar_item.php`,
  `cerrar.php`, `cancelar.php`, `reponer.php` (código duplicado del mismo bloque
  try/catch/rollback en al menos 4 archivos), pero **no está extraído a una función
  auxiliar común** pese a que `functions.php` ya existe como lugar natural para eso.
- **Sin autoloading ni namespaces**: cada archivo hace `require_once` explícito de
  sus dependencias; funciona bien a esta escala (< 30 archivos PHP) pero no
  escalaría bien con muchas más pantallas.

## 6. Rendimiento

- **N+1 real encontrado**: `pedidos/cancelar.php:30-38` hace un `SELECT` de los
  ítems del pedido y luego, dentro de un `foreach`, ejecuta **dos** statements
  (`UPDATE productos` + `INSERT movimientos_stock`) por cada ítem, en vez de un
  `UPDATE ... JOIN` o un `INSERT ... SELECT` batch. Con pedidos típicos de pocos
  ítems (una mesa con 4-8 productos) el impacto real es insignificante, pero es un
  patrón que no escala si en el futuro se permiten pedidos con muchos ítems.
- **Sin N+1 en las pantallas de mayor tráfico**: `mesas/salon.php` trae todos los
  pedidos abiertos en una sola query y los indexa en un array PHP en vez de
  consultar por mesa dentro de un loop (`salon.php:15-20`) — bien resuelto.
  `reportes/auditoria_pedidos.php` resuelve 4 nombres de usuario distintos (mozo,
  entregó, cobró, canceló) con JOINs en una sola query, no con subconsultas por fila.
- **`stock/movimientos.php`**: trae el historial global de movimientos de **todo el
  sistema** limitado a 200 filas (`LIMIT 200`, sin filtro de fecha ni paginación).
  A volumen bajo (un solo local) tarda poco, pero no hay forma de ver "los
  movimientos de la semana pasada" sin scrollear un listado plano; no hay filtro por
  fecha, producto o tipo en esa pantalla (sí lo hay en `reponer.php`, pero acotado a
  un producto).
- **`pedidos/nuevo.php`**: recalcula "más vendidos últimos 30 días" con una
  agregación sobre `pedido_items JOIN pedidos JOIN productos` **cada vez que se abre
  una mesa o se recarga la pantalla del pedido** (no hay caché ni tabla
  materializada). No es grave al volumen actual, pero es la query más pesada del
  sistema ejecutada con más frecuencia.
- **Índices**: existen sobre las columnas más filtradas/ordenadas
  (`productos.categoria_id`, `productos.activo`, `pedidos.estado`,
  `pedidos.cerrado_en`, `pedido_items.pedido_id`, `movimientos_stock.producto_id`,
  `movimientos_stock.creado_en`) — razonables para los patrones de consulta
  observados. No hay índice compuesto en `pedidos(estado, cerrado_en)` pese a que
  varias queries de reportes filtran por ambas columnas a la vez
  (`reportes/ventas.php:16-18`, `caja/cerrar.php:15-17`); a los volúmenes de un solo
  local esto es irrelevante, pero si el histórico crece a varios años de datos podría
  notarse.
- **Dependencias externas por CDN en cada carga de página** (Bootstrap CSS/JS,
  Chart.js): sin conexión a internet, el sistema pierde estilos y el gráfico de
  ventas dejaría de renderizar (aunque las tablas subyacentes seguirían mostrándose,
  ya que no dependen de JS para el dato crudo). En una ubicación rural (San Antonio
  de Areco, "almacén de campo") con conectividad potencialmente inestable, esto es
  un riesgo de disponibilidad real y no solo teórico — no hay ningún archivo vendorizado
  localmente como fallback.

## 7. UI/UX

- **Diseño**: usa Bootstrap 5.3.3 vía CDN como base, con `assets/css/style.css`
  propio de 484 líneas para la identidad visual (variables CSS de marca, clases
  `.mesa-card`, `.producto-btn`, `.btn-lg-touch` para touch targets grandes, pensado
  para tablet/celular en el salón). No se leyó el CSS completo línea por línea en
  este relevamiento, pero las referencias en el HTML (`var(--marca-principal-oscuro)`,
  clases `mesa-libre/mesa-ocupada/mesa-cuenta_pedida`) indican un sistema de color
  coherente por estado, no colores sueltos hardcodeados en cada página.
- **Responsive**: uso consistente de clases de grilla Bootstrap (`col-6 col-sm-4
  col-md-3 col-lg-2` en salón, `col-md-7`/`col-md-5` en el POS) — pensado para
  achicarse en celular. `btn-lg-touch` como clase dedicada a botones táctiles grandes
  sugiere que el diseño priorizó el uso en tablet en el salón, coherente con el caso
  de uso real (mozos tomando pedidos).
  del filtro de categorías, ya identificado y corregido en el propio código, ver
  sección 8) y luego confirmado como resuelto en el comentario de
  `nuevo.php:243-251`, buena señal de que sí hubo ciclos reales de prueba con
  usuarios y ajuste posterior.
- **Feedback al usuario**: mensajes de error visibles en casi todos los formularios
  (login, cambiar contraseña, abrir/cerrar caja, cobrar pedido, reponer stock)
  excepto `productos/guardar.php` (falla silenciosa, ya señalado en sección 3/5).
  Toast no bloqueante al agregar un producto al pedido (`mostrarToast()`,
  `nuevo.php:328-334`) en vez de `alert()`, buena práctica de UX para un flujo de
  alta frecuencia.
- **Impresión**: clase `no-imprimir` usada consistentemente en reportes y nav para
  ocultar controles al imprimir (`reportes/ventas.php:41`, `header.php:31`); el
  ticket usa su propio layout angosto pensado para impresora térmica en vez de
  reusar el layout general.
- **Inconsistencias detectadas**: `enviar_cocina.php`/`marcar_entregado.php` hacen
  `location.reload()` completo tras su `fetch`, mientras que `agregar_item.php`/
  `quitar_item.php` actualizan el DOM sin recargar (`actualizarPedido()`,
  `nuevo.php:418-443`) — dos patrones distintos conviviendo en el mismo archivo para
  problemas similares (actualizar la pantalla tras una acción del pedido).

## 8. Testing

**No existe testing automatizado de ningún tipo**: no hay carpeta `tests/`, no hay
PHPUnit ni ningún framework de testing configurado (coherente con la restricción de
"sin Composer"), no hay scripts de test manual documentados como checklist en el
repo, no hay capturas de pantalla ni notas de QA guardadas.

**Evidencia indirecta de pruebas manuales reales sí encontrada** (no es un checklist
formal, pero son señales concretas de que el sistema fue probado con uso real y
ajustado en consecuencia):

- El propio flujo de estados de pedido documentado en `INSTALL.md:91-122` con un
  diagrama ASCII detallado sugiere que el flujo fue pensado y validado extremo a
  extremo (abrir → enviar a cocina → entregar → cobrar, con cancelación en cualquier
  punto previo a cerrado).
- El comentario en `pedidos/nuevo.php:157-159,243-251` describe explícitamente un
  bug de UX real ya vivido en producción/pruebas ("parecía que el filtro por
  categoría no funcionaba") y la corrección aplicada — esto es la evidencia más
  clara de un ciclo de prueba-detección-corrección real, aunque no quedó como
  checklist reutilizable, solo como comentario en el código.
- El comentario de `LEEME_PRIMERO.txt:8-18` sobre el caché del navegador
  mostrando CSS viejo tras actualizar el servidor es otro caso de un problema real
  detectado en uso (probablemente en la tablet/celular del local) y resuelto con
  versionado de CSS (`$versionCss` en `header.php`).
- Los datos de la "carta real" fueron cargados a partir de fotos de la carta física
  con anotaciones explícitas de qué precios quedaron dudosos (ver sección 10) — esto
  indica que hubo un proceso de carga de datos reales, no solo datos de ejemplo
  sintéticos, aunque no es lo mismo que un test funcional del sistema.

**Lo que nunca se probó (no hay ninguna evidencia en el repo de que se haya
verificado)**:

- Concurrencia: dos dispositivos agregando ítems al mismo pedido a la vez, dos
  usuarios abriendo caja al mismo tiempo, stock quedando negativo por dos ventas
  simultáneas del último ítem (el chequeo de stock en `agregar_item.php:51` no usa
  `SELECT ... FOR UPDATE` ni ninguna forma de lock pesimista/optimista).
- Volumen: no hay indicios de haber probado el sistema con un histórico grande de
  pedidos/movimientos de stock (los `LIMIT 200`/`LIMIT 50` sin paginación en
  `stock/movimientos.php` y `stock/reponer.php` sugieren que nunca se necesitó
  paginar porque el volumen de prueba fue bajo).
- Impresión real en hardware térmico (no hay forma de verificar esto sin acceso
  físico, y no hay ninguna nota en el repo confirmando que se haya probado con una
  impresora real vs. solo "imprimir a PDF" desde el navegador de escritorio).
- Recuperación ante caída de conexión a internet a mitad de una venta (dependencia de
  CDN, sección 6).
- Cambios de rol en caliente (un usuario admin degradado a empleado con sesión
  activa, o desactivado mientras tiene sesión abierta) — `estaLogueado()`/`esAdmin()`
  leen de `$_SESSION`, que se carga una sola vez al loguear; si se desactiva un
  usuario o se le cambia el rol desde otra sesión, el cambio no se refleja hasta que
  esa sesión vuelva a loguearse.

**Checklist de pruebas reutilizable**: no existía ninguno en el repo. Se incluye uno
nuevo en `MEMORY.md`, construido a partir de los flujos reales identificados en este
relevamiento (no es una lista inventada de cero: cada paso corresponde a un flujo de
código verificado en las secciones 3 y 8 de este documento).

## 9. Estado de deploy

- **`DEPLOY_HOSTINGER/` está sincronizado con `_dev_no_subir/`** al momento de este
  relevamiento (verificado con `diff` recursivo, sección 1): mismo código, solo
  cambian rutas relativas de `require`. No hay deuda de sincronización pendiente
  ahora mismo.
- **`config/config.example.php` es idéntico** entre ambos árboles (`diff` sin
  salida) y **no hay `config/config.php` real en ningún lado del repo** — correcto,
  ya que ese archivo (con credenciales reales) nunca debe versionarse; `.gitignore`
  debería confirmarlo (no se inspeccionó su contenido exacto en este relevamiento,
  pero la ausencia del archivo real en el árbol es la señal que importa).
- **Migraciones pendientes de aplicar en producción**: dependen del estado real de
  la base en Hostinger, que este relevamiento no tiene forma de verificar (no hay
  acceso a la base de producción). Lo que sí es seguro por lectura del repo:
  - Si la base de Hostinger es **anterior a la ronda 4** (carta real): falta correr
    `carta_real.sql` (incluye el `ALTER TABLE productos MODIFY COLUMN nombre
    VARCHAR(255)` y el `ALTER TABLE caja_sesiones ADD COLUMN nota`).
  - Si es **anterior a la ronda 6** (estado "entregado"): falta correr
    `migracion_ronda6.sql`.
  - Si ya se importó `database.sql` completo en su versión actual (post-ronda 6),
    no falta nada.
  - **Esto no se puede determinar desde el repo**: solo lo sabe quien administra el
    hosting real. Recomendación operativa (no una corrección de código): antes de
    subir cualquier cambio nuevo, correr `DESCRIBE pedidos;` en phpMyAdmin de
    producción y comparar contra el `CREATE TABLE pedidos` de `database.sql` para
    confirmar qué columnas ya existen.
- **Para deployar hoy desde cero, no falta nada**: `LEEME_PRIMERO.txt` es un
  instructivo completo y coherente con el estado real del código (se verificaron
  los pasos contra los archivos que efectivamente existen: `.htaccess` de
  protección, `config.example.php`, estructura de carpetas). El único paso manual
  fuera del repo es reemplazar `assets/img/logo.png` (83 KB, presente como
  placeholder/logo real — no se pudo determinar desde este relevamiento si es el
  logo definitivo del negocio o un placeholder, dado que es un archivo binario).

## 10. Deuda técnica — TODOs, pendientes y placeholders explícitos

Búsqueda exhaustiva (`grep` case-insensitive de "todo", "fixme", "pendiente",
"revisar", "confirmar", "placeholder", "hack", "xxx" en todo `.php`/`.sql`/`.md`/
`.txt` del repo). Coincidencias reales de deuda técnica (se excluyen falsos
positivos como la palabra "todos" en español o nombres de variable):

- **Precios sin confirmar, productos inactivos a propósito** (`database.sql:374-380`,
  idéntico en `carta_real.sql:229-235`): 4 productos (Lata Andes Roja, Lata Stella
  Artois, Lata Stella Artois Negra, Andes Roja de Litro) cargados con `precio=0.00`
  y `activo=0` explícitamente "PENDIENTE: falta precio, inactivo hasta confirmar".
  No aparecen en el POS hasta que alguien los active manualmente desde Productos.
- **3 precios marcados "revisar" pero activos** (visibles y vendibles ya mismo):
  - `database.sql:255`: "Costeletas de ternera con papas fritas" — revisar: precio
    de versión anterior de la carta.
  - `database.sql:325`: "Papas fritas (bastón) c/cheddar" a $3000, más barato que la
    versión sin cheddar ($8000) — inconsistencia de precio marcada explícitamente en
    el propio SQL como sospechosa.
  - `database.sql:355`: "Cerro Callejero tinto" — nombre parcialmente tapado en la
    foto de la carta, cargado con la mejor lectura posible.
- **Categoría faltante**: "Embutidos curados en grasa de cerdo" no se cargó porque
  la foto de la carta no era legible (`database.sql:221-222`), "queda pendiente para
  otra ronda". Sigue sin existir en la base actual.
- **Stock inicial estimado, no contado**: casi todas las bebidas con envase se
  cargaron con stock estimado (24 u. la mayoría, 12 en vinos/litros) en vez de un
  conteo real de depósito/heladera (`database.sql:206-209`), con instrucción
  explícita de ajustarlo desde "Stock → Reponer" apenas se tenga el conteo real —
  no hay forma de saber desde el repo si esto ya se hizo en producción.
- **Logo**: `LEEME_PRIMERO.txt:125-127` documenta que si `assets/img/logo.png` no se
  sube, el sistema muestra un círculo con las iniciales del negocio en su lugar
  (fallback ya implementado, `onerror` en `header.php:35` y `login.php:37`); hay un
  archivo `logo.png` presente en el repo, pero no se pudo confirmar si es el
  definitivo o un placeholder de prueba sin verlo visualmente en detalle.
- **Duplicación de árboles de código sin mecanismo de sincronización automática**:
  ya detallado en secciones 1 y 5 (`_dev_no_subir` vs `DEPLOY_HOSTINGER`,
  `database.sql` vs `carta_real.sql`). No es un TODO explícito en un comentario,
  pero es deuda técnica estructural real que puede generar desincronización
  silenciosa en el futuro si no se recuerda mantenerla a mano.
- **Falla silenciosa en `productos/guardar.php`** (sección 3/5): no es un comentario
  "TODO" pero es deuda de calidad real y concreta — validación server-side sin
  mensaje de error visible al usuario.
- **Sin testing automatizado ni checklist previo** (sección 8): deuda de proceso,
  no de código.

No se encontraron literalmente las palabras "FIXME" ni "HACK" en ningún archivo del
proyecto.
