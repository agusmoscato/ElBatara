<?php
/**
 * Funciones auxiliares usadas en todo el sistema.
 */

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
        die('Token de seguridad inválido. Recargá la página e intentá de nuevo.');
    }
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
    fputcsv($salida, $encabezados, ';');
    foreach ($filas as $fila) {
        fputcsv($salida, $fila, ';');
    }
    fclose($salida);
    exit;
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
 * Ejecuta $funcion dentro de una transacción: begin, la corre, y hace
 * commit si no lanzó excepción o rollback si lanzó alguna. Devuelve lo
 * que haya devuelto $funcion. Se usa para operaciones sensibles con
 * varios pasos (cierre de caja, cierre de pedido) donde un paso a mitad
 * de camino no puede quedar aplicado si otro paso falla.
 */
function ejecutarTransaccion(PDO $pdo, callable $funcion)
{
    $pdo->beginTransaction();
    try {
        $resultado = $funcion($pdo);
        $pdo->commit();
        return $resultado;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
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
    $stmt = $pdo->prepare("SELECT pi.id, pi.cantidad, pi.subtotal, p.nombre AS producto_nombre, p.tipo_venta
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
            'producto_nombre' => $it['producto_nombre'],
            'cantidad_texto' => formatearCantidad((float)$it['cantidad'], $it['tipo_venta']),
            'subtotal_texto' => formatearMoneda((float)$it['subtotal']),
        ];
    }

    return [
        'items' => $itemsSalida,
        'total_texto' => formatearMoneda($total),
    ];
}
