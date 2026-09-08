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
$productoId = intPositivoONull($_POST['producto_id'] ?? null);
$cantidad = filter_var($_POST['cantidad'] ?? 0, FILTER_VALIDATE_FLOAT);

if (!$pedidoId || !$productoId || $cantidad === false || $cantidad <= 0) {
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND estado IN ('abierto', 'en_preparacion', 'entregado')");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();
if (!$pedido) {
    echo json_encode(['error' => 'El pedido no existe o ya está cerrado']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ? AND activo = 1');
$stmt->execute([$productoId]);
$producto = $stmt->fetch();
if (!$producto) {
    echo json_encode(['error' => 'Producto no disponible']);
    exit;
}

// Los productos por unidad se cargan en cantidades enteras.
if ($producto['tipo_venta'] === 'unidad') {
    $cantidad = (float)round($cantidad);
}

if ($cantidad > (float)$producto['stock_actual']) {
    echo json_encode(['error' => 'Stock insuficiente. Disponible: ' . formatearCantidad((float)$producto['stock_actual'], $producto['tipo_venta'])]);
    exit;
}

$subtotal = round($cantidad * (float)$producto['precio'], 2);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$pedidoId, $productoId, $cantidad, $producto['precio'], $subtotal]);

    $pdo->prepare('UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?')->execute([$cantidad, $productoId]);

    $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad, referencia_pedido_id, usuario_id, nota) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$productoId, 'venta', -$cantidad, $pedidoId, $_SESSION['usuario_id'], null]);

    recalcularTotalPedido($pdo, $pedidoId);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error al agregar item: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo agregar el producto']);
    exit;
}

echo json_encode(obtenerEstadoPedido($pdo, $pedidoId));
