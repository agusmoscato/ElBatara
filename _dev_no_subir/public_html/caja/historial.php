<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$stmt = $pdo->query("SELECT c.*, u.nombre AS usuario_nombre
                      FROM caja_sesiones c
                      JOIN usuarios u ON u.id = c.usuario_id
                      ORDER BY c.id DESC
                      LIMIT 100");
$sesiones = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM caja_sesiones WHERE estado = 'abierta' LIMIT 1");
$cajaAbierta = $stmt->fetch();

$tituloPagina = 'Historial de caja';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Historial de caja</h2>
  <?php if ($cajaAbierta): ?>
    <a href="cerrar.php" class="btn btn-danger">Cerrar caja actual</a>
  <?php else: ?>
    <a href="abrir.php" class="btn btn-primary">Abrir caja</a>
  <?php endif; ?>
</div>

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm">
  <thead>
    <tr>
      <th>Apertura</th><th>Cierre</th><th>Usuario</th><th>Monto inicial</th>
      <th>Efectivo</th><th>Tarjeta</th><th>Transferencia</th><th>Diferencia</th><th>Estado</th><th>Nota</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($sesiones as $s): ?>
    <tr>
      <td><?= h(date('d/m/Y H:i', strtotime($s['abierta_en']))) ?></td>
      <td><?= $s['cerrada_en'] ? h(date('d/m/Y H:i', strtotime($s['cerrada_en']))) : '-' ?></td>
      <td><?= h($s['usuario_nombre']) ?></td>
      <td><?= formatearMoneda((float)$s['monto_inicial']) ?></td>
      <td><?= $s['total_efectivo'] !== null ? formatearMoneda((float)$s['total_efectivo']) : '-' ?></td>
      <td><?= $s['total_tarjeta'] !== null ? formatearMoneda((float)$s['total_tarjeta']) : '-' ?></td>
      <td><?= $s['total_transferencia'] !== null ? formatearMoneda((float)$s['total_transferencia']) : '-' ?></td>
      <td class="<?= $s['diferencia'] !== null && (float)$s['diferencia'] < 0 ? 'text-danger' : '' ?>">
        <?= $s['diferencia'] !== null ? formatearMoneda((float)$s['diferencia']) : '-' ?>
      </td>
      <td>
        <span class="badge <?= $s['estado'] === 'abierta' ? 'bg-success' : 'bg-secondary' ?>">
          <?= $s['estado'] === 'abierta' ? 'Abierta' : 'Cerrada' ?>
        </span>
      </td>
      <td><?= h($s['nota'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
