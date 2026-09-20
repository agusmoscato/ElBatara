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

// Ronda 20: reemplaza el paso de "Enviar a cocina"/"Marcar entregado" (dos
// pasos, el dueño no los usa) por un único paso "Pedir la cuenta".
$stmt = $pdo->prepare("UPDATE pedidos SET estado = 'cuenta_pedida', cuenta_pedida_en = NOW(), cuenta_pedida_por_id = ?
                        WHERE id = ? AND estado = 'abierto'");
$stmt->execute([$_SESSION['usuario_id'], $pedidoId]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['error' => 'El pedido ya no está abierto. Recargá la página.']);
    exit;
}

echo json_encode(['ok' => true, 'estado' => 'cuenta_pedida']);
