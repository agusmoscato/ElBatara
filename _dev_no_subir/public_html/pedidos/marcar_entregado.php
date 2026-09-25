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

// Ronda 21: "entregado" se marca por MESA/PEDIDO completo (todos los
// ítems 'listo' de una vez), no ítem por ítem — es la acción del mozo al
// llevar la bandeja a la mesa, y en la práctica lleva todo lo que está
// listo junto. Ver la decisión documentada en MEMORY.md, Ronda 21.
$stmt = $pdo->prepare("UPDATE pedido_items SET estado_cocina = 'entregado' WHERE pedido_id = ? AND estado_cocina = 'listo'");
$stmt->execute([$pedidoId]);
$cantidadEntregados = $stmt->rowCount();

if ($cantidadEntregados === 0) {
    echo json_encode(['error' => 'No hay ítems listos para entregar todavía.']);
    exit;
}

echo json_encode(array_merge(['ok' => true], obtenerEstadoPedido($pdo, $pedidoId)));
