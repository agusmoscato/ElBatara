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

if (!$pedidoId) {
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// Se puede marcar como entregado desde "abierto" o "en_preparacion"
// (por si el mozo sirve el pedido sin haber tocado "Enviar a cocina",
// por ejemplo un pedido de solo bebidas).
$stmt = $pdo->prepare("UPDATE pedidos SET estado = 'entregado', entregado_en = NOW(), entregado_por_id = ?
                        WHERE id = ? AND estado IN ('abierto', 'en_preparacion')");
$stmt->execute([$_SESSION['usuario_id'], $pedidoId]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['error' => 'El pedido ya no está pendiente de entrega. Recargá la página.']);
    exit;
}

echo json_encode(['ok' => true, 'estado' => 'entregado']);
