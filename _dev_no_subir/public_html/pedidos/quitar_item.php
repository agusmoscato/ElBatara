<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

$pdo = obtenerConexion();

$pedidoId = intPositivoONull($_POST['pedido_id'] ?? null);
$itemId = intPositivoONull($_POST['item_id'] ?? null);
$cantidadAQuitar = filter_var($_POST['cantidad_a_quitar'] ?? null, FILTER_VALIDATE_FLOAT);

if (!$pedidoId || !$itemId) {
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND estado IN ('abierto', 'cuenta_pedida')");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();
if (!$pedido) {
    echo json_encode(['error' => 'El pedido no existe o ya está cerrado']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM pedido_items WHERE id = ? AND pedido_id = ?');
$stmt->execute([$itemId, $pedidoId]);
$item = $stmt->fetch();
if (!$item) {
    echo json_encode(['error' => 'El item no existe']);
    exit;
}

$resultado = ejecutarTransaccion($pdo, function (PDO $pdo) use ($item, $pedidoId, $itemId, $cantidadAQuitar) {
    // Quitar solo una porción de la cantidad (stepper "-" del carrito, ronda
    // 13) en vez de siempre eliminar la línea entera, cuando se pide un
    // $cantidadAQuitar válido y menor a la cantidad actual del ítem.
    $cantidadActual = (float)$item['cantidad'];
    $quitarCompleto = $cantidadAQuitar === false || $cantidadAQuitar <= 0 || $cantidadAQuitar >= $cantidadActual;
    $cantidadADevolver = $quitarCompleto ? $cantidadActual : $cantidadAQuitar;

    // Devolvemos el stock que se había descontado al agregar el producto.
    $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?')
        ->execute([$cantidadADevolver, $item['producto_id']]);

    $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad, referencia_pedido_id, usuario_id, nota) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$item['producto_id'], 'ajuste', $cantidadADevolver, $pedidoId, $_SESSION['usuario_id'], 'Reversión por eliminación de item del pedido']);

    if ($quitarCompleto) {
        $pdo->prepare('DELETE FROM pedido_items WHERE id = ?')->execute([$itemId]);
    } else {
        $cantidadRestante = $cantidadActual - $cantidadAQuitar;
        $subtotalRestante = round($cantidadRestante * (float)$item['precio_unitario'], 2);
        $pdo->prepare('UPDATE pedido_items SET cantidad = ?, subtotal = ? WHERE id = ?')
            ->execute([$cantidadRestante, $subtotalRestante, $itemId]);
    }

    recalcularTotalPedido($pdo, $pedidoId);
}, 'quitar item', 'No se pudo quitar el producto');

if (!$resultado['ok']) {
    echo json_encode(['error' => $resultado['error']]);
    exit;
}

echo json_encode(obtenerEstadoPedido($pdo, $pedidoId));
