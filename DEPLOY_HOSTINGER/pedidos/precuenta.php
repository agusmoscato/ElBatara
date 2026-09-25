<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$pedidoId = intPositivoONull($_GET['pedido_id'] ?? null);
if (!$pedidoId) {
    redirigir('mesas/salon.php');
}

// La pre-cuenta se puede pedir en cualquier momento mientras el pedido
// sigue vivo (abierto o con la cuenta ya pedida) — a diferencia de
// pedir_cuenta.php, esto NO cambia ningún estado ni bloquea nada: es solo
// una vista previa imprimible/descargable del ticket con lo cargado hasta
// ahora. El pedido sigue editable después de verla (ver decisión de
// nombre de botón en MEMORY.md, Ronda 21: "Vista previa de cuenta" para no
// confundirla con "Pedir la cuenta").
$stmt = $pdo->prepare("SELECT p.*, m.nombre AS mesa_nombre, u.nombre AS mozo_nombre
                        FROM pedidos p
                        LEFT JOIN mesas m ON m.id = p.mesa_id
                        JOIN usuarios u ON u.id = p.usuario_id
                        WHERE p.id = ? AND p.estado IN ('abierto', 'cuenta_pedida')");
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

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pre-cuenta #<?= (int)$pedido['id'] ?> - <?= h(NOMBRE_NEGOCIO) ?></title>
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
  .aviso-precuenta {
    background-color: #fff3cd;
    border: 2px solid #c9a24b;
    color: #6e5417;
    font-weight: bold;
    text-align: center;
    padding: 8px;
    margin: 10px 0;
    font-family: Arial, sans-serif;
    font-size: 13px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  .btns { text-align: center; margin-top: 20px; }
  .btns button {
    padding: 10px 20px;
    font-size: 14px;
    margin: 0 4px;
    font-family: Arial, sans-serif;
    border-radius: 6px;
    border: 2px solid #8B2E2E;
    cursor: pointer;
  }
  .btns button.primario {
    background-color: #8B2E2E;
    color: #fff;
  }
  .btns button.secundario {
    background-color: #fff;
    color: #8B2E2E;
  }

  @media print {
    .btns { display: none; }
    body { margin: 0; max-width: 100%; }
  }
</style>
</head>
<body>
  <h1><?= h(NOMBRE_NEGOCIO) ?></h1>
  <div class="aviso-precuenta">⚠ Pre-cuenta — no es comprobante de pago</div>
  <p class="centro">
    <?= h(date('d/m/Y H:i')) ?><br>
    <?= $pedido['mesa_nombre'] ? h($pedido['mesa_nombre']) : 'Para llevar' ?><br>
    Pedido #<?= (int)$pedido['id'] ?>
  </p>

  <div class="linea"></div>

  <?php if (empty($items)): ?>
    <p class="centro">Todavía no se cargó ningún producto.</p>
  <?php else: ?>
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
  <?php endif; ?>

  <div class="linea"></div>

  <div class="total">Total actual: <?= formatearMoneda((float)$pedido['total']) ?></div>

  <div class="linea"></div>
  <div class="aviso-precuenta">⚠ Pre-cuenta — no es comprobante de pago</div>
  <p class="centro">El pedido sigue abierto: esto es solo una vista previa, todavía se puede seguir agregando o quitando productos.</p>

  <div class="btns">
    <button class="primario" onclick="window.print()">Imprimir / Descargar</button>
    <button class="secundario" onclick="window.location.href='nuevo.php?pedido_id=<?= (int)$pedido['id'] ?>'">Volver al pedido</button>
  </div>
</body>
</html>
