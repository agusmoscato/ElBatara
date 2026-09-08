<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirAdmin();

$pdo = obtenerConexion();

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-6 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = date('Y-m-d', strtotime('-6 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = date('Y-m-d'); }

$stmt = $pdo->prepare("SELECT p.id, p.estado, p.total, p.medio_pago, p.creado_en,
                               p.entregado_en, p.cerrado_en, p.cancelado_en,
                               m.nombre AS mesa_nombre,
                               u.nombre AS mozo_nombre,
                               ue.nombre AS entregado_por_nombre,
                               uc.nombre AS cerrado_por_nombre,
                               ua.nombre AS cancelado_por_nombre
                        FROM pedidos p
                        LEFT JOIN mesas m ON m.id = p.mesa_id
                        JOIN usuarios u ON u.id = p.usuario_id
                        LEFT JOIN usuarios ue ON ue.id = p.entregado_por_id
                        LEFT JOIN usuarios uc ON uc.id = p.cerrado_por_id
                        LEFT JOIN usuarios ua ON ua.id = p.cancelado_por_id
                        WHERE p.estado IN ('cerrado', 'cancelado')
                          AND DATE(COALESCE(p.cerrado_en, p.cancelado_en)) BETWEEN ? AND ?
                        ORDER BY COALESCE(p.cerrado_en, p.cancelado_en) DESC");
$stmt->execute([$desde, $hasta]);
$pedidos = $stmt->fetchAll();

$tituloPagina = 'Auditoría de pedidos';
require __DIR__ . '/../../includes/header.php';
?>

<h2 class="mb-3">Auditoría de pedidos (cobros y cancelaciones)</h2>

<form method="get" action="auditoria_pedidos.php" class="row g-2 align-items-end mb-4">
  <div class="col-auto">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" value="<?= h($desde) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" value="<?= h($hasta) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm">
  <thead>
    <tr>
      <th>#</th><th>Mesa</th><th>Mozo</th><th>Total</th><th>Estado</th>
      <th>Entregado por</th><th>Fecha entrega</th>
      <th>Cobrado por</th><th>Fecha cobro</th>
      <th>Cancelado por</th><th>Fecha cancelación</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pedidos as $p): ?>
    <tr>
      <td><?= (int)$p['id'] ?></td>
      <td><?= $p['mesa_nombre'] ? h($p['mesa_nombre']) : 'Para llevar' ?></td>
      <td><?= h($p['mozo_nombre']) ?></td>
      <td><?= formatearMoneda((float)$p['total']) ?></td>
      <td>
        <span class="badge <?= $p['estado'] === 'cerrado' ? 'bg-success' : 'bg-danger' ?>">
          <?= $p['estado'] === 'cerrado' ? 'Cobrado' : 'Cancelado' ?>
        </span>
      </td>
      <td><?= $p['entregado_por_nombre'] ? h($p['entregado_por_nombre']) : '-' ?></td>
      <td><?= $p['entregado_en'] ? h(date('d/m/Y H:i', strtotime($p['entregado_en']))) : '-' ?></td>
      <td><?= $p['cerrado_por_nombre'] ? h($p['cerrado_por_nombre']) : '-' ?></td>
      <td><?= $p['cerrado_en'] ? h(date('d/m/Y H:i', strtotime($p['cerrado_en']))) : '-' ?></td>
      <td><?= $p['cancelado_por_nombre'] ? h($p['cancelado_por_nombre']) : '-' ?></td>
      <td><?= $p['cancelado_en'] ? h(date('d/m/Y H:i', strtotime($p['cancelado_en']))) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
