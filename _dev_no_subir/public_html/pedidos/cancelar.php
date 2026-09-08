<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('mesas/salon.php');
}

validarTokenCsrf();

$pdo = obtenerConexion();
$pedidoId = intPositivoONull($_POST['pedido_id'] ?? null);

if (!$pedidoId) {
    redirigir('mesas/salon.php');
}

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND estado IN ('abierto', 'en_preparacion', 'entregado')");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    redirigir('mesas/salon.php');
}

$pdo->beginTransaction();
try {
    // Devolvemos al stock todos los productos que estaban cargados en el pedido.
    $stmt = $pdo->prepare('SELECT producto_id, cantidad FROM pedido_items WHERE pedido_id = ?');
    $stmt->execute([$pedidoId]);
    foreach ($stmt->fetchAll() as $item) {
        $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?')
            ->execute([$item['cantidad'], $item['producto_id']]);

        $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad, referencia_pedido_id, usuario_id, nota) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$item['producto_id'], 'ajuste', $item['cantidad'], $pedidoId, $_SESSION['usuario_id'], 'Reversión por cancelación de pedido']);
    }

    $pdo->prepare("UPDATE pedidos SET estado = 'cancelado', cancelado_en = NOW(), cancelado_por_id = ? WHERE id = ?")
        ->execute([$_SESSION['usuario_id'], $pedidoId]);

    if ($pedido['mesa_id']) {
        $pdo->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?")->execute([$pedido['mesa_id']]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error al cancelar pedido: ' . $e->getMessage());
}

redirigir('mesas/salon.php');
