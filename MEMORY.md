# MEMORY — El Batará (sistema de gestión)

> Este archivo se actualiza en cada ronda futura de trabajo, NO se reemplaza desde
> cero. Si estás por editarlo: leé todo lo que ya tiene primero, y sumá/ajustá en vez
> de borrar historia que sigue siendo válida. Última actualización: 2026-09-08
> (ronda 7 — Fases 1 y 2 de mejora: integridad de datos y consistencia de código,
> ver detalle en `RELEVAMIENTO.md` y en el pie de este archivo).

## Qué es el proyecto

Sistema de gestión para "El Batará", un almacén de campo + restaurante en San
Antonio de Areco, Argentina (negocio real, en uso). Cubre: toma de pedidos por mesa
o para llevar (POS), control de stock, apertura/cierre de caja con conciliación de
efectivo, y reportes de ventas/auditoría para el dueño.

## Stack y restricciones no negociables

- **PHP puro** (procedural, sin framework), **MySQL** vía PDO con prepared
  statements. **Sin Composer, sin npm, sin build step.** Bootstrap 5.3.3 y
  Chart.js 4.4.4 están vendorizados en `assets/vendor/` (desde la ronda 7) — no se
  cargan por CDN, así el sistema funciona sin internet (solo necesita la base de
  datos). Actualizar de versión requiere reemplazar esos archivos a mano.
- Destino: **Hostinger plan compartido, sin acceso SSH.** Deploy es subir archivos
  por Administrador de Archivos o FTP y correr un `.sql` desde phpMyAdmin. Cualquier
  cambio tiene que seguir siendo deployable así — nada que necesite terminal en el
  servidor.
- Hay **dos árboles de código funcionalmente idénticos**: `_dev_no_subir/` (estructura
  ideal, `config/`+`includes/` fuera de `public_html`, NO se sube nunca) y
  `DEPLOY_HOSTINGER/` (config/includes dentro de `public_html`, protegidos por
  `.htaccess`, esto sí se sube). Difieren solo en rutas relativas de `require`.
  **Si tocás un archivo de lógica/UI, tenés que replicar el cambio en ambos árboles**
  a mano — no hay nada que lo haga automático. Corré
  `bash scripts/comparar_arboles.sh` desde la raíz antes de dar por terminado
  cualquier cambio y antes de cada deploy: compara ambos árboles (normalizando la
  diferencia esperada de rutas `require` y de fin de línea CRLF/LF) y también el
  bloque de productos/categorías entre `database.sql` y `carta_real.sql` (delimitado
  por los comentarios `INICIO_CARTA_COMPARABLE`/`FIN_CARTA_COMPARABLE` en ambos
  archivos — no borrar esos marcadores). Sale con código 0 si todo está sincronizado.

## Estructura de carpetas (resumen — detalle completo en `RELEVAMIENTO.md` sección 1)

```
DEPLOY_HOSTINGER/   <- esto se sube a Hostinger (incluye LEEME_PRIMERO.txt)
_dev_no_subir/      <- mismo código, estructura "ideal", NO se sube
database.sql        <- schema completo + datos de ejemplo + carta real (fuente de verdad del esquema)
carta_real.sql       <- subconjunto de arriba, para importar en una base ya en producción sin repetir todo
migracion_ronda6.sql <- ALTER puntual (estado "entregado"), ya incorporado a database.sql
migracion_ronda7.sql <- ALTER puntual (precio_a_revisar + índice único de caja), ya incorporado a database.sql
scripts/comparar_arboles.sh <- chequeo de sincronización entre árboles/SQL duplicados, correr antes de deployar
INSTALL.md            <- guía de instalación detallada
RELEVAMIENTO.md        <- diagnóstico completo (leer antes de tocar nada grande)
```

## Convenciones del proyecto a respetar

- **Nombres de funciones/variables de dominio en español** (`obtenerConexion`,
  `iniciarSesion`, `requerirLogin`, `formatearMoneda`, `recalcularTotalPedido`).
  Inglés solo para lo genérico del lenguaje (PDO, fetch, clases Bootstrap). No mezclar.
- **Estilo de manejo de errores existente**: desde la ronda 7 hay un helper común,
  `ejecutarTransaccion(PDO $pdo, callable $operacion, string $contextoLog, string
  $mensajeErrorGenerico)` en `includes/functions.php`. Reemplaza el
  begin/commit/rollback repetido a mano en cada script transaccional. Dentro de
  `$operacion`, lanzar `new ValidacionException('mensaje para el usuario')` para
  errores esperados de negocio (stock insuficiente, etc. — el mensaje se devuelve
  tal cual); cualquier otra `Exception` se trata como error técnico inesperado (se
  loguea con `$contextoLog` y se devuelve `$mensajeErrorGenerico` sin exponer
  detalles internos). Devuelve `['ok' => bool, 'error' => string, 'datos' => mixed]`.
  Usalo para cualquier flujo transaccional nuevo — no repitas el try/catch a mano.
- **Filtro + paginación de grillas largas**: usar `includes/paginacion.php`
  (`obtenerPaginaActual()`, `calcularOffset()`, `renderPaginacion()`,
  `obtenerFechaGet()`, constante `FILAS_POR_PAGINA` = 30). Mismos nombres de
  parámetro GET en toda pantalla que lo use: `pagina`, `desde`/`hasta` para rango de
  fechas, `q` para texto libre. No inventar un patrón de paginación distinto por
  pantalla — ver los listados ya migrados (`productos/listar.php`,
  `stock/movimientos.php`, `caja/historial.php`, `reportes/auditoria_pedidos.php`,
  `stock/reponer.php`) como referencia.
- **Mensajes de error al redirigir**: usar `flashError('mensaje')` (guarda en sesión)
  antes de `redirigir()` cuando un endpoint no renderiza su propio HTML (como
  `productos/guardar.php`). Se muestra automáticamente como alert rojo en la
  siguiente página vía `mostrarFlashError()`, llamado una sola vez desde
  `includes/header.php`. No inventar un mecanismo de error nuevo — reusar este.
- **Toda salida a HTML pasa por `h()`** (`includes/functions.php`, wrapper de
  `htmlspecialchars`). Nunca hacer `echo $variable` directo si viene de la base o de
  input de usuario.
- **Toda query usa prepared statements** (`$pdo->prepare(...)->execute([...])`).
  Nunca concatenar variables directamente en SQL.
- **Todo formulario POST lleva `csrf_token`** generado con `generarTokenCsrf()` y
  validado con `validarTokenCsrf()` o el patrón manual equivalente
  (`hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')`).
- **Paleta de marca**: variables CSS custom en `assets/css/style.css` (ej.
  `var(--marca-principal-oscuro)`), usada en vez de colores hardcodeados en el HTML.
  No se confirmó el valor exacto de `#8B2E2E` mencionado como posible color de marca
  en instrucciones previas — **antes de asumir un color de marca, revisar
  `assets/css/style.css` directamente** para confirmar los valores reales definidos
  ahí, no asumirlos de memoria.
- **Cache-busting de CSS**: `includes/header.php` tiene una variable `$versionCss`
  que se agrega como `?v=` a la URL de `style.css`. Si editás `style.css`, **subí
  ese número** (está documentado con un comentario explícito en el propio
  `header.php` y en `LEEME_PRIMERO.txt`) o los navegadores de tablets/celulares en
  uso van a seguir mostrando el CSS viejo desde caché sin ningún error visible.

## Estado actual por funcionalidad (una línea cada una)

- **Login**: funcional, bcrypt + CSRF + regeneración de sesión; desde la ronda 7 bloquea temporalmente (5 min) después de 5 intentos fallidos consecutivos por usuario.
- **Cambiar contraseña**: funcional, validación server-side correcta.
- **Salón / mesas**: funcional, pinta color según estado del pedido asociado, sin N+1.
- **Toma de pedidos (POS)**: funcional y es la pantalla más compleja (447 líneas, `pedidos/nuevo.php`); ya tuvo y resolvió un bug real de UX de filtrado (ver abajo).
- **Agregar/quitar ítems**: funcional vía fetch+JSON, transaccional; desde la ronda 7 usa `SELECT ... FOR UPDATE` dentro de la transacción, así que dos ventas simultáneas del mismo producto ya no pueden dejar el stock en negativo.
- **Enviar a cocina / marcar entregado**: funcional; desde la ronda 7 actualiza el botón de estado in-place (sin `location.reload()`), igual que agregar/quitar ítem.
- **Cobrar/cerrar pedido**: funcional, exige medio de pago y al menos un ítem, genera ticket.
- **Cancelar pedido**: funcional; desde la ronda 7 devuelve el stock en dos operaciones batch (UPDATE con JOIN + INSERT...SELECT) en vez de un loop ítem por ítem.
- **Ticket**: funcional, HTML standalone para impresión térmica vía `window.print()`; nunca verificado contra hardware real de impresora térmica.
- **Productos (ABM)**: funcional; desde la ronda 7 muestra mensajes de error concretos si la validación falla (antes redirigía en silencio), y tiene badge "⚠ Revisar precio" para los productos con precio dudoso.
- **Categorías (ABM)**: presente, no auditado en profundidad en esta ronda.
- **Stock (movimientos + reponer)**: funcional; el listado global de movimientos todavía no tiene filtro por fecha ni paginación (solo `LIMIT 200`) — queda para la Fase 3 de la ronda 7 (no hecha todavía).
- **Caja (abrir/cerrar/historial)**: funcional, calcula conciliación de efectivo automática; desde la ronda 7 tiene un índice único a nivel de base (`ux_caja_una_abierta`) que impide dos cajas abiertas a la vez, incluso ante dos aperturas simultáneas.
- **Reportes (ventas, top productos, medios de pago, auditoría)**: funcionales, solo admin; auditoría de pedidos es el más completo (trazabilidad de quién hizo qué y cuándo).

## Deuda técnica y pendientes conocidos (resumen — detalle completo en `RELEVAMIENTO.md` sección 10)

- 4 productos con precio pendiente de confirmar (latas de cerveza), cargados inactivos a propósito.
- 3 precios/nombres marcados "revisar" — **el dueño todavía no confirmó el precio real**:
  Costeletas de ternera, Papas c/cheddar, Cerro Callejero tinto. Desde la ronda 7 quedan
  marcados con `productos.precio_a_revisar = 1` y muestran un badge de advertencia en
  Productos; siguen activos y vendibles hasta que se desmarquen a mano desde ahí.
- Falta cargar la categoría "Embutidos curados en grasa de cerdo" (foto de carta ilegible).
- Stock de bebidas cargado con estimación, no con conteo real de depósito — no se sabe si ya se corrigió en producción.
- Sin testing automatizado de ningún tipo; sin checklist previo (se agrega uno abajo).
- Sin protección contra fuerza bruta en login (Fase 4 de la ronda 7, no hecha todavía).
- (Fases 4 y 5 de la ronda 7 ya hechas — SRI, cabeceras de seguridad, límite de
  intentos de login, Bootstrap/Chart.js vendorizados sin dependencia de CDN. Ver
  arriba. Con esto se completaron las 5 fases planeadas de esta ronda.)
- (Fase 3 de la ronda 7 ya hecha — ver arriba: filtro+paginación en las grillas
  principales vía `includes/paginacion.php`.)
- Duplicación estructural en dos lugares (árboles de deploy, y `database.sql`/`carta_real.sql`):
  sigue existiendo a propósito (restricción de Hostinger sin SSH), pero desde la ronda 7
  hay un script (`scripts/comparar_arboles.sh`) que la detecta — correrlo antes de cada
  deploy en vez de confiar en la memoria.

## Checklist de pruebas end-to-end reutilizable

Usar esto en cada ronda antes de dar por buena una funcionalidad tocada. No asumas
que "compila" significa "funciona": seguí el flujo real con datos de prueba.

1. **Login**: usuario/contraseña correctos entra; incorrectos muestra error sin
   detalle técnico; usuario inactivo no puede entrar aunque la contraseña sea correcta.
2. **Cambiar contraseña**: contraseña actual incorrecta rechaza; nueva < 6
   caracteres rechaza; confirmación distinta rechaza; caso exitoso permite loguear
   de nuevo con la nueva contraseña.
3. **Abrir mesa → pedido nuevo**: click en mesa libre crea pedido y la pinta como
   ocupada; volver a clickear la misma mesa lleva al mismo pedido (no crea uno
   nuevo).
4. **Buscar y filtrar productos en el POS**: escribir en el buscador filtra por
   nombre; click en una categoría muestra solo esa categoría; "Todos" muestra todo
   agrupado por categoría; ninguno de los dos filtros queda "pegado" mostrando cosas
   de más (este fue un bug real ya corregido, no lo reintroduzcas).
5. **Agregar producto por unidad**: se agrega con cantidad 1, aparece en la tabla de
   la derecha, el total se actualiza sin recargar la página.
6. **Agregar producto por peso**: abre el modal, pide cantidad en kg, rechaza 0 o
   negativo, agrega con el decimal correcto.
7. **Intentar agregar más cantidad que el stock disponible**: debe rechazar con
   mensaje claro de "stock insuficiente", sin descontar nada.
8. **Quitar ítem**: desaparece de la tabla, el total se recalcula, el stock se
   devuelve (verificar en Stock → Movimientos que aparece el ajuste).
9. **Enviar a cocina / marcar entregado**: cambia el estado visible del pedido y de
   la mesa en el salón (color).
10. **Cobrar pedido**: sin medio de pago seleccionado rechaza; sin ítems cargados
    rechaza; caso exitoso libera la mesa, genera el ticket, y aparece en Reportes →
    Ventas del día correspondiente.
11. **Cancelar pedido**: en cualquier estado antes de cerrado, cancela, libera la
    mesa, y devuelve TODO el stock cargado (verificar cada ítem en el historial de
    stock del producto).
12. **Intentar cancelar un pedido ya cerrado**: no debe ser posible (ni por URL
    directa a `cancelar.php` con ese `pedido_id`).
13. **Abrir caja**: rechaza monto negativo; si ya hay una caja abierta, no permite
    abrir otra.
14. **Cerrar caja**: el efectivo esperado calculado (inicial + ventas efectivo) debe
    coincidir con lo esperado manualmente; probar con diferencia positiva y
    negativa y confirmar que queda guardada correctamente.
15. **Productos (ABM) — admin**: alta y edición de producto guardan correctamente;
    probar un caso de error (nombre vacío o categoría inválida) y confirmar
    QUÉ pasa hoy (silencioso — es deuda conocida, no lo des por sorpresa).
16. **Reportes — admin**: filtrar por rango de fechas devuelve los datos correctos;
    exportar CSV abre bien en Excel (acentos correctos, gracias al BOM UTF-8);
    auditoría de pedidos muestra correctamente quién cobró/canceló cada pedido.
17. **Permisos**: un usuario con rol `empleado` no puede acceder a
    Productos/Categorías/Mesas(ABM)/Reportes ni por menú ni por URL directa.
18. **Cache de CSS**: si tocaste `assets/css/style.css`, subiste el número de
    `$versionCss` en `includes/header.php`, y confirmaste visualmente el cambio en
    un navegador con caché (forzar recarga si hace falta para probar).
19. **Sincronización de árboles**: si tocaste código en `_dev_no_subir/` o en
    `database.sql`/`carta_real.sql`, replicaste el cambio en el otro lado y corriste
    `bash scripts/comparar_arboles.sh` (sale 0 = todo sincronizado).
20. **Badge "Revisar precio"**: tildar/destildar el checkbox en el modal de edición
    de un producto y confirmar que el badge amarillo aparece/desaparece en el listado.
21. **Error visible al fallar la validación**: crear/editar un producto con nombre
    vacío, categoría inválida, o precio/stock negativo, y confirmar que aparece un
    mensaje de error concreto (rojo) en vez de un redirect silencioso.
22. **Concurrencia de caja** (si tenés dos dispositivos/pestañas a mano): intentar
    abrir caja casi al mismo tiempo desde ambos — solo uno debe lograrlo, el otro debe
    mostrar "Ya se abrió una caja justo ahora desde otro dispositivo."
23. **Filtros y paginación**: en Productos, Stock → Movimientos, Caja → Historial y
    Reportes → Auditoría, aplicar un filtro, cambiar de página, y confirmar que el
    filtro sigue aplicado (no se resetea) y que la URL refleja los parámetros
    (se puede recargar o compartir el link sin perder el estado).
24. **"Más pedidos" con caché**: agregar una venta nueva de un producto que no
    estaba en "Más pedidos", y confirmar que aparece ahí recién después de ~3
    minutos (o de loguear de nuevo) — no es un bug, es el caché de sesión
    documentado arriba, pero conviene confirmar que el comportamiento es el
    esperado y no una demora mayor.
25. **Bloqueo de login por fuerza bruta**: fallar el login 5 veces seguidas con un
    usuario de prueba y confirmar que el 6º intento (aunque la contraseña sea
    correcta) muestra "Demasiados intentos fallidos..." en vez de dejar entrar;
    esperar el tiempo indicado (o resetear a mano `intentos_fallidos`/
    `bloqueado_hasta` en la base) y confirmar que después sí puede loguear normal.
26. **CDN con SRI**: (ítem histórico de cuando se usaba CDN — ya no aplica desde que
    Bootstrap/Chart.js se vendorizaron en la Fase 5, dejar solo como referencia).
27. **Bootstrap/Chart.js vendorizados**: abrir cualquier pantalla y confirmar que los
    estilos y los gráficos cargan igual que antes; revisar la pestaña Red (Network)
    del navegador y confirmar que NINGÚN request sale hacia `cdn.jsdelivr.net` — todo
    tiene que venir de `assets/vendor/` del propio dominio. Si es posible, probar con
    el wifi desconectado (dejando la conexión al servidor local intacta) y confirmar
    que la página sigue viéndose con estilos correctos.

## Errores ya cometidos y su lección

Evidencia real encontrada en el código y la documentación existente (no inventada):

- **Filtro de categorías confuso en el POS**: una versión anterior de
  `pedidos/nuevo.php` preseleccionaba la primera categoría alfabética ("Agua") sin
  que el mozo la tocara, y a la vez mostraba "Más pedidos" — dos bloques de
  productos visibles simultáneamente, lo que se percibía como "el filtro no
  funciona". **Lección**: no preseleccionar ningún filtro por defecto en pantallas
  de selección múltiple; mostrar un estado explícito de "no hay nada elegido
  todavía" en vez de una elección implícita. (Ver comentario en
  `pedidos/nuevo.php:157-159` y `243-251`.)
- **CSS viejo cacheado en tablets/celulares tras actualizar el servidor**: antes de
  la ronda 6, subir una versión nueva de `style.css` no garantizaba que los
  dispositivos del local la vieran — el navegador seguía sirviendo la copia vieja de
  caché sin ningún error visible, dando la falsa impresión de que el cambio "no se
  subió". **Lección**: cache-busting con `?v=` obligatorio en assets estáticos que
  cambian; ya está resuelto vía `$versionCss` en `includes/header.php`, pero
  **hay que acordarse de subir ese número a mano en cada edición de CSS** — no es
  automático.
- Fuera de estos dos casos documentados en el propio código, **no se encontró
  evidencia en el repo de otros errores de rondas anteriores** (no hay changelog,
  notas de retro, ni issues guardados). Si en una ronda futura se identifica otro
  error real ya cometido, agregarlo acá con la misma referencia a archivo/línea —
  no hay que inventar entradas genéricas solo para llenar esta sección.

## Reglas de la iteración actual

El pedido activo es **mejorar lo que ya existe** (robustez, errores silenciosos,
deuda técnica documentada en `RELEVAMIENTO.md` sección 10, consistencia), **no
agregar funcionalidades nuevas** salvo que el usuario lo pida explícitamente en esa
ronda. Antes de tocar código: leer `RELEVAMIENTO.md` para entender el estado real
(no asumir), y este archivo para no repetir errores ya vividos. Cualquier cambio de
lógica se replica en `_dev_no_subir/` y `DEPLOY_HOSTINGER/` (correr
`scripts/comparar_arboles.sh` para confirmarlo).

### Ronda 7 — estado de las 5 fases de mejora planeadas

Plan original de 5 fases (integridad de datos, consistencia de código, rendimiento,
seguridad, resiliencia offline). Estado al cierre de esta sesión:

- ✅ **Fase 1 (integridad de datos)**: hecha. Lock `SELECT ... FOR UPDATE` en stock,
  índice único en caja, badge de precio a revisar, mensajes de error en Productos.
- ✅ **Fase 2 (consistencia de código)**: hecha. Helper `ejecutarTransaccion()` +
  `ValidacionException` reemplaza el try/catch/rollback repetido; `cancelar.php` sin
  N+1; `enviar_cocina`/`marcar_entregado` sin `location.reload()`; script
  `comparar_arboles.sh` para chequear sincronización de árboles y SQL duplicados.
- ✅ **Fase 3 (rendimiento)**: hecha. Caché en sesión (180s) para "más pedidos" en
  `pedidos/nuevo.php`; índice compuesto `idx_pedidos_estado_cerrado`; nuevo include
  `includes/paginacion.php` (mismo patrón GET `pagina`/`desde`/`hasta`/`q` en todas
  partes) aplicado a `stock/movimientos.php` (+ filtro fecha/producto/tipo),
  `stock/reponer.php` (paginación del historial), `productos/listar.php` (+ filtro
  categoría/texto), `categorias/listar.php` (+ filtro texto, sin paginación por
  volumen bajo), `mesas/listar.php` (+ filtro por estado, sin paginación),
  `caja/historial.php` (+ filtro de fecha) y `reportes/auditoria_pedidos.php`
  (+ filtro usuario/estado). `reportes/productos_top.php` y `medios_pago.php` no se
  tocaron: ya están acotados por diseño (top 15 / agrupado por 3 medios de pago), no
  necesitan paginación.
- ✅ **Fase 4 (seguridad)**: hecha.
  - Login: bloqueo temporal por fuerza bruta. `usuarios.intentos_fallidos` +
    `usuarios.bloqueado_hasta` (nuevas columnas); a los 5 intentos fallidos
    consecutivos el usuario queda bloqueado 5 minutos (`LOGIN_LIMITE_INTENTOS` /
    `LOGIN_MINUTOS_BLOQUEO` en `includes/auth.php`). `iniciarSesion()` ahora
    devuelve `true` o un mensaje de error (string) en vez de solo `bool` — si tocás
    login en el futuro, respetá esa firma.
  - CDN con Subresource Integrity: `integrity` + `crossorigin="anonymous"` en
    Bootstrap (CSS y JS) y Chart.js (los 3 reportes que lo cargan). Hashes SHA-384
    verificados descargando los archivos reales y confirmando cruce con los hashes
    oficiales publicados en cdnjs para Bootstrap. **Si algún día se cambia la
    versión pineada de Bootstrap o Chart.js, hay que recalcular estos hashes** (no
    van a coincidir con la versión nueva y el navegador bloqueará el recurso).
  - Cabeceras de seguridad HTTP en `DEPLOY_HOSTINGER/.htaccess` (y replicado en
    `_dev_no_subir/public_html/.htaccess`): `X-Content-Type-Options`,
    `X-Frame-Options`, `Content-Security-Policy`. La CSP permite `'unsafe-inline'`
    en script-src y style-src a propósito — el sistema tiene JS/CSS inline en varias
    pantallas (`pedidos/nuevo.php`, los reportes con gráficos, `stock/reponer.php`)
    y migrar todo eso a nonces es un cambio de arquitectura grande que no entra en
    este endurecimiento puntual. Igual bloquea cargar recursos desde cualquier
    dominio que no sea el propio o `cdn.jsdelivr.net`.
  - `scripts/comparar_arboles.sh` ahora también compara los `.htaccess` (raíz y
    `config/`) entre árboles, no solo el código PHP.
- ✅ **Fase 5 (resiliencia offline)**: hecha. Bootstrap 5.3.3 y Chart.js 4.4.4 se
  descargaron una vez de jsDelivr (verificando el tamaño/hash contra los oficiales de
  cdnjs) y quedaron vendorizados en `assets/vendor/bootstrap-5.3.3/` y
  `assets/vendor/chartjs-4.4.4/` — se suben como cualquier otro archivo de
  `DEPLOY_HOSTINGER/`, sin pasos extra de instalación. `includes/header.php`,
  `includes/footer.php` y los 3 reportes con gráficos ahora referencian esas rutas
  locales en vez de `cdn.jsdelivr.net`. La CSP en `.htaccess` se pudo simplificar
  (ya no necesita permitir `cdn.jsdelivr.net`). **Si en el futuro se sube de versión
  Bootstrap o Chart.js, hay que reemplazar los archivos dentro de `assets/vendor/`
  a mano** — no hay ningún proceso de actualización automático, y
  `scripts/comparar_arboles.sh` (sección 4) verifica que ambos árboles tengan
  exactamente los mismos archivos vendorizados.

Con esto se completaron las 5 fases planeadas de esta ronda de mejora. No se pudo
correr la app real (no hay PHP/MySQL en el entorno de esta sesión): todos los
cambios se verificaron con revisión estática cuidadosa y, en el caso de los hashes
SRI/vendorizado, con descargas reales y comparación de tamaños/hashes — pero no con
ejecución real del sistema. **Antes de dar por terminada esta ronda, correr la
checklist de pruebas completa de arriba (ítems 1 a 26) en un entorno real
(XAMPP/Hostinger).**

---

*Nota final: este archivo se actualiza — no se reemplaza — en cada ronda futura de
trabajo sobre el proyecto. Antes de escribir en él, leelo completo primero y
conservá todo lo que siga siendo cierto.*
