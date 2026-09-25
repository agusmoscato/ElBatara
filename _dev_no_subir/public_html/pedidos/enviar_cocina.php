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

if (!$pedidoId) {
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

// Manda a cocina TODOS los ítems que estén 'pendiente' de este pedido de
// una sola vez, aunque se hayan ido agregando en más de una ronda sin que
// el mozo tocara este botón antes (ronda 21 — está permitido, es
// exactamente el caso de uso "el mozo se olvidó y agregó dos cosas más").
$stmt = $pdo->prepare("UPDATE pedido_items SET estado_cocina = 'enviado', enviado_cocina_en = NOW() WHERE pedido_id = ? AND estado_cocina = 'pendiente'");
$stmt->execute([$pedidoId]);
$cantidadEnviados = $stmt->rowCount();

if ($cantidadEnviados === 0) {
    echo json_encode(['error' => 'No hay ítems nuevos para enviar a cocina.']);
    exit;
}

echo json_encode(array_merge(['ok' => true], obtenerEstadoPedido($pdo, $pedidoId)));
