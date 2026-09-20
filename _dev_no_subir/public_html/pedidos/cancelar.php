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
$motivo = trim($_POST['motivo'] ?? '');

if (!$pedidoId) {
    redirigir('mesas/salon.php');
}

if ($motivo === '') {
    flashError('Tenés que indicar un motivo para cancelar el pedido.');
    redirigir('mesas/salon.php');
}

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND estado IN ('abierto', 'cuenta_pedida')");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    redirigir('mesas/salon.php');
}

$resultado = ejecutarTransaccion($pdo, function (PDO $pdo) use ($pedidoId, $pedido, $motivo) {
    // Devolvemos al stock todos los productos que estaban cargados en el
    // pedido en dos operaciones batch (UPDATE con JOIN + INSERT...SELECT)
    // en vez de una consulta por ítem: mismo resultado, sin loop de
    // round-trips a la base.
    $pdo->prepare('UPDATE productos p
                    JOIN pedido_items pi ON pi.producto_id = p.id
                    SET p.stock_actual = p.stock_actual + pi.cantidad
                    WHERE pi.pedido_id = ?')
        ->execute([$pedidoId]);

    $pdo->prepare("INSERT INTO movimientos_stock (producto_id, tipo, cantidad, referencia_pedido_id, usuario_id, nota)
                    SELECT producto_id, 'ajuste', cantidad, pedido_id, ?, 'Reversión por cancelación de pedido'
                    FROM pedido_items WHERE pedido_id = ?")
        ->execute([$_SESSION['usuario_id'], $pedidoId]);

    $pdo->prepare("UPDATE pedidos SET estado = 'cancelado', cancelado_en = NOW(), cancelado_por_id = ?, motivo_cancelacion = ? WHERE id = ?")
        ->execute([$_SESSION['usuario_id'], $motivo, $pedidoId]);

    if ($pedido['mesa_id']) {
        $pdo->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?")->execute([$pedido['mesa_id']]);
    }
}, 'cancelar pedido', 'No se pudo cancelar el pedido. Intentá nuevamente.');

if (!$resultado['ok']) {
    flashError($resultado['error']);
}

redirigir('mesas/salon.php');
