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

$itemId = intPositivoONull($_POST['item_id'] ?? null);
$pedidoId = intPositivoONull($_POST['pedido_id'] ?? null);

if (!$itemId && !$pedidoId) {
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

if ($itemId) {
    // Marcar un ítem puntual como listo.
    $stmt = $pdo->prepare("UPDATE pedido_items SET estado_cocina = 'listo' WHERE id = ? AND estado_cocina = 'enviado'");
    $stmt->execute([$itemId]);
} else {
    // Marcar todos los ítems 'enviado' de un pedido (botón "Marcar todo
    // listo" del grupo de la mesa en el panel).
    $stmt = $pdo->prepare("UPDATE pedido_items SET estado_cocina = 'listo' WHERE pedido_id = ? AND estado_cocina = 'enviado'");
    $stmt->execute([$pedidoId]);
}

if ($stmt->rowCount() === 0) {
    echo json_encode(['error' => 'No había nada para marcar como listo. Puede que otra persona ya lo haya hecho.']);
    exit;
}

echo json_encode(['ok' => true]);
