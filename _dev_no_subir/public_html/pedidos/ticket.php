<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$pedidoId = intPositivoONull($_GET['pedido_id'] ?? null);
if (!$pedidoId) {
    redirigir('mesas/salon.php');
}

$stmt = $pdo->prepare("SELECT p.*, m.nombre AS mesa_nombre, u.nombre AS mozo_nombre, uc.nombre AS cobrador_nombre
                        FROM pedidos p
                        LEFT JOIN mesas m ON m.id = p.mesa_id
                        JOIN usuarios u ON u.id = p.usuario_id
                        LEFT JOIN usuarios uc ON uc.id = p.cerrado_por_id
                        WHERE p.id = ? AND p.estado = 'cerrado'");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    redirigir('mesas/salon.php');
}

$stmt = $pdo->prepare("SELECT pi.*, prod.nombre AS producto_nombre, prod.tipo_venta
                        FROM pedido_items pi
                        JOIN productos prod ON prod.id = pi.producto_id
                        WHERE pi.pedido_id = ?
                        ORDER BY pi.id");
$stmt->execute([$pedidoId]);
$items = $stmt->fetchAll();

$etiquetasMedios = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia / QR'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket #<?= (int)$pedido['id'] ?> - <?= h(NOMBRE_NEGOCIO) ?></title>
<style>
  body {
    font-family: 'Courier New', monospace;
    max-width: 320px;
    margin: 20px auto;
    color: #111;
    font-size: 14px;
  }
  h1 { font-size: 18px; text-align: center; margin: 0 0 4px; }
  .centro { text-align: center; }
  .linea { border-top: 1px dashed #333; margin: 8px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 2px 0; vertical-align: top; }
  .cant { white-space: nowrap; }
  .total { font-size: 16px; font-weight: bold; text-align: right; margin-top: 8px; }
  .btns { text-align: center; margin-top: 20px; }
  .btns button { padding: 10px 20px; font-size: 14px; margin: 0 4px; }

  @media print {
    .btns { display: none; }
    body { margin: 0; max-width: 100%; }
  }
</style>
</head>
<body>
  <h1><?= h(NOMBRE_NEGOCIO) ?></h1>
  <p class="centro">
    <?= h(date('d/m/Y H:i', strtotime($pedido['cerrado_en']))) ?><br>
    <?= $pedido['mesa_nombre'] ? h($pedido['mesa_nombre']) : 'Para llevar' ?><br>
    Pedido #<?= (int)$pedido['id'] ?>
  </p>

  <div class="linea"></div>

  <table>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= h($it['producto_nombre']) ?><br>
          <small class="cant"><?= formatearCantidad((float)$it['cantidad'], $it['tipo_venta']) ?> x <?= formatearMoneda((float)$it['precio_unitario']) ?></small>
        </td>
        <td style="text-align:right; white-space:nowrap;"><?= formatearMoneda((float)$it['subtotal']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="linea"></div>

  <div class="total">Total: <?= formatearMoneda((float)$pedido['total']) ?></div>
  <p class="centro">Medio de pago: <?= h($etiquetasMedios[$pedido['medio_pago']] ?? $pedido['medio_pago']) ?></p>

  <div class="linea"></div>
  <p class="centro">¡Gracias por su visita!</p>

  <div class="btns">
    <button onclick="window.print()">Imprimir</button>
    <button onclick="window.location.href='../mesas/salon.php'">Volver al salón</button>
  </div>
</body>
</html>
