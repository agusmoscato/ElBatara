<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
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

if (!$pedidoId || !$itemId) {
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

$stmt = $pdo->prepare('SELECT * FROM pedido_items WHERE id = ? AND pedido_id = ?');
$stmt->execute([$itemId, $pedidoId]);
$item = $stmt->fetch();
if (!$item) {
    echo json_encode(['error' => 'El item no existe']);
    exit;
}

$pdo->beginTransaction();
try {
    // Devolvemos el stock que se había descontado al agregar el producto.
    $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?')
        ->execute([$item['cantidad'], $item['producto_id']]);

    $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad, referencia_pedido_id, usuario_id, nota) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$item['producto_id'], 'ajuste', $item['cantidad'], $pedidoId, $_SESSION['usuario_id'], 'Reversión por eliminación de item del pedido']);

    $pdo->prepare('DELETE FROM pedido_items WHERE id = ?')->execute([$itemId]);

    recalcularTotalPedido($pdo, $pedidoId);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error al quitar item: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo quitar el producto']);
    exit;
}

echo json_encode(obtenerEstadoPedido($pdo, $pedidoId));
