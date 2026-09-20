<?php
/**
 * Funciones auxiliares usadas en todo el sistema.
 */

/**
 * Error esperado de negocio dentro de una transacción (ej: "stock
 * insuficiente"), a diferencia de un error técnico inesperado. Su
 * mensaje es seguro para mostrar tal cual al usuario.
 */
class ValidacionException extends Exception
{
}

/**
 * Ejecuta $operacion dentro de una transacción, con el mismo patrón de
 * begin/commit/rollback repetido antes en agregar_item.php, cerrar.php,
 * cancelar.php y reponer.php. $operacion puede lanzar ValidacionException
 * para errores esperados de negocio (el mensaje se devuelve tal cual al
 * caller) o dejar pasar cualquier otra excepción para errores técnicos
 * inesperados (se loguean con $contextoLog y se devuelve
 * $mensajeErrorGenerico en su lugar, sin exponer detalles internos).
 *
 * Devuelve ['ok' => true, 'datos' => <lo que devuelva $operacion>] o
 * ['ok' => false, 'error' => <mensaje para mostrar al usuario>].
 */
function ejecutarTransaccion(PDO $pdo, callable $operacion, string $contextoLog, string $mensajeErrorGenerico): array
{
    $pdo->beginTransaction();
    try {
        $datos = $operacion($pdo);
        $pdo->commit();
        return ['ok' => true, 'datos' => $datos];
    } catch (ValidacionException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al $contextoLog: " . $e->getMessage());
        return ['ok' => false, 'error' => $mensajeErrorGenerico];
    }
}

/**
 * Escapa texto para mostrarlo de forma segura en HTML (evita XSS).
 */
function h(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Formatea un número como moneda (ej: 1234.5 -> "$ 1.234,50").
 */
function formatearMoneda(float $numero): string
{
    return '$ ' . number_format($numero, 2, ',', '.');
}

/**
 * Formatea una cantidad según el tipo de venta del producto.
 * Unidad -> número entero. Peso -> kg con 3 decimales.
 */
function formatearCantidad(float $cantidad, string $tipoVenta): string
{
    if ($tipoVenta === 'peso') {
        return number_format($cantidad, 3, ',', '.') . ' kg';
    }
    return number_format($cantidad, 0, ',', '.') . ' u.';
}

/**
 * Genera (o reutiliza) un token CSRF para el formulario actual.
 */
function generarTokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF recibido por POST. Corta la ejecución si no es válido.
 */
function validarTokenCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        mostrarErrorCsrf();
        exit;
    }
}

/**
 * Página mínima para cuando el token CSRF vence (sesión vieja, doble
 * pestaña, formulario abierto mucho tiempo). Antes era un die() en texto
 * plano sin salida (ronda 13): dejaba a la persona en una pantalla en
 * blanco sin ningún link de regreso. Reutiliza header.php/footer.php para
 * que se vea como el resto del sistema (sidebar si sigue logueada, paleta
 * de marca) en vez de un error crudo del navegador.
 */
function mostrarErrorCsrf(): void
{
    $tituloPagina = 'Sesión vencida';
    require __DIR__ . '/header.php';
    ?>
    <div class="alert alert-danger">
      <h4 class="alert-heading">Tu sesión para este formulario venció</h4>
      <p>Puede pasar si dejaste la página abierta mucho tiempo o la abriste en otra pestaña. No se guardó nada por seguridad.</p>
      <a href="<?= $base ?>dashboard.php" class="btn btn-danger">Volver al inicio</a>
    </div>
    <?php
    require __DIR__ . '/footer.php';
}

/**
 * Redirige a una URL relativa a la raíz del sitio y corta la ejecución.
 */
function redirigir(string $rutaRelativa): void
{
    header('Location: ' . rutaBase() . $rutaRelativa);
    exit;
}

/**
 * Guarda un mensaje de error para mostrarlo en la página a la que se
 * redirige después (patrón "flash message"). Usar antes de redirigir()
 * cuando una validación falla en un endpoint que no renderiza HTML
 * propio (por ejemplo guardar.php), en vez de redirigir en silencio.
 */
function flashError(string $mensaje): void
{
    $_SESSION['flash_error'] = $mensaje;
}

/**
 * Imprime (y limpia) el mensaje de error guardado con flashError(), si
 * hay alguno pendiente. Se llama una sola vez desde header.php para que
 * cualquier página del sistema pueda mostrar sus errores de redirección
 * con el mismo estilo que ya usan los formularios que no redirigen
 * (login, abrir caja, etc.).
 */
function mostrarFlashError(): void
{
    if (empty($_SESSION['flash_error'])) {
        return;
    }
    echo '<div class="alert alert-danger">' . h($_SESSION['flash_error']) . '</div>';
    unset($_SESSION['flash_error']);
}

/**
 * Igual que flashError() pero para confirmaciones de éxito (ej: "Guardado
 * correctamente.") en endpoints que no renderizan HTML propio y solo
 * redirigen, como los ABMs simples (mesas, categorías, medios de pago).
 */
function flashExito(string $mensaje): void
{
    $_SESSION['flash_exito'] = $mensaje;
}

/**
 * Imprime (y limpia) el mensaje de éxito guardado con flashExito(), si hay
 * alguno pendiente. Se llama junto a mostrarFlashError() desde header.php.
 */
function mostrarFlashExito(): void
{
    if (empty($_SESSION['flash_exito'])) {
        return;
    }
    echo '<div class="alert alert-success">' . h($_SESSION['flash_exito']) . '</div>';
    unset($_SESSION['flash_exito']);
}

/**
 * Indica si hay una caja de turno abierta en este momento. Se usa antes de
 * permitir crear un pedido nuevo o cobrarlo, para que ninguna venta quede
 * "fantasma" fuera de la conciliación de un cierre de caja.
 */
function hayCajaAbierta(PDO $pdo): bool
{
    return (bool)$pdo->query("SELECT id FROM caja_sesiones WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1")->fetchColumn();
}

/**
 * Formatea el tiempo transcurrido desde una fecha/hora hasta ahora,
 * en un formato corto ("5 min", "1h 20min"). Se usa para mostrar hace
 * cuánto está abierta una mesa ocupada.
 */
function formatearDuracionDesde(string $fechaHoraInicio): string
{
    $inicio = strtotime($fechaHoraInicio);
    $segundos = max(0, time() - $inicio);
    $minutos = intdiv($segundos, 60);

    if ($minutos < 60) {
        return $minutos . ' min';
    }

    $horas = intdiv($minutos, 60);
    $minutosRestantes = $minutos % 60;
    return $horas . 'h ' . $minutosRestantes . 'min';
}

/**
 * Genera y envía un archivo CSV para descargar, y corta la ejecución.
 * $encabezados: nombres de columna. $filas: array de arrays con los
 * valores de cada columna, en el mismo orden que $encabezados.
 */
function exportarCsv(string $nombreArchivo, array $encabezados, array $filas): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

    // BOM UTF-8 para que Excel abra bien los acentos.
    echo "\xEF\xBB\xBF";

    $salida = fopen('php://output', 'w');
    fputcsv($salida, array_map('sanitizarCampoCsv', $encabezados), ';');
    foreach ($filas as $fila) {
        fputcsv($salida, array_map('sanitizarCampoCsv', $fila), ';');
    }
    fclose($salida);
    exit;
}

/**
 * Antepone un apóstrofo a los campos que empiezan con =, +, -, @, tab o CR
 * antes de escribirlos en un CSV. Sin esto, Excel/Sheets puede interpretar
 * texto libre cargado por un usuario (ej. la descripción de un egreso) como
 * una fórmula al abrir el archivo ("CSV formula injection").
 */
function sanitizarCampoCsv($valor)
{
    $texto = (string)$valor;
    if ($texto !== '' && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $texto;
    }
    return $valor;
}

/**
 * Devuelve un entero positivo desde $_GET/$_POST, o null si no es válido.
 * Útil para validar IDs recibidos por parámetro.
 */
function intPositivoONull($valor): ?int
{
    if ($valor === null || $valor === '') {
        return null;
    }
    $filtrado = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $filtrado === false ? null : $filtrado;
}

/**
 * Devuelve el HTML del botón de acción según el estado del pedido
 * (abierto -> "Pedir la cuenta", cuenta_pedida -> badge). Se usa tanto en
 * el render inicial de pedidos/nuevo.php como, con el mismo formato
 * replicado en JS, para actualizar la pantalla sin recargar toda la
 * página al cambiar de estado (ver actualizarBotonEstadoPedido() en
 * nuevo.php). Ronda 20: antes tenía un paso intermedio de cocina
 * (en_preparacion) que el dueño no usa — se sacó, queda un solo paso.
 */
function renderBotonEstadoPedido(string $estado): string
{
    if ($estado === 'abierto') {
        return '<button class="btn btn-info btn-lg-touch" onclick="pedirCuenta()">🧾 Pedir la cuenta</button>';
    }
    if ($estado === 'cuenta_pedida') {
        return '<span class="badge bg-warning align-self-center fs-6">🧾 Cuenta pedida</span>';
    }
    return '';
}

/**
 * Recalcula el total de un pedido sumando sus items y lo guarda en la tabla pedidos.
 */
function recalcularTotalPedido(PDO $pdo, int $pedidoId): void
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(subtotal), 0) AS total FROM pedido_items WHERE pedido_id = ?');
    $stmt->execute([$pedidoId]);
    $total = $stmt->fetchColumn();
    $pdo->prepare('UPDATE pedidos SET total = ? WHERE id = ?')->execute([$total, $pedidoId]);
}

/**
 * Devuelve los items actuales de un pedido y su total, listo para convertir a JSON
 * y actualizar la pantalla de toma de pedidos sin recargar la página.
 */
function obtenerEstadoPedido(PDO $pdo, int $pedidoId): array
{
    $stmt = $pdo->prepare("SELECT pi.id, pi.producto_id, pi.cantidad, pi.subtotal, p.nombre AS producto_nombre, p.tipo_venta
                            FROM pedido_items pi
                            JOIN productos p ON p.id = pi.producto_id
                            WHERE pi.pedido_id = ?
                            ORDER BY pi.id");
    $stmt->execute([$pedidoId]);
    $items = $stmt->fetchAll();

    $total = 0;
    $itemsSalida = [];
    foreach ($items as $it) {
        $total += (float)$it['subtotal'];
        $itemsSalida[] = [
            'id' => (int)$it['id'],
            'producto_id' => (int)$it['producto_id'],
            'producto_nombre' => $it['producto_nombre'],
            'tipo_venta' => $it['tipo_venta'],
            'cantidad' => (float)$it['cantidad'],
            'cantidad_texto' => formatearCantidad((float)$it['cantidad'], $it['tipo_venta']),
            'subtotal_texto' => formatearMoneda((float)$it['subtotal']),
        ];
    }

    return [
        'items' => $itemsSalida,
        'total_texto' => formatearMoneda($total),
    ];
}
