<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirAdmin();

$pdo = obtenerConexion();

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-6 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = date('Y-m-d', strtotime('-6 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = date('Y-m-d'); }

$canal = $_GET['canal'] ?? '';
if (!in_array($canal, ['mostrador', 'mesa'], true)) { $canal = ''; }

$condiciones = ["p.estado IN ('cerrado', 'cancelado')", 'DATE(COALESCE(p.cerrado_en, p.cancelado_en)) BETWEEN ? AND ?'];
$parametros = [$desde, $hasta];
if ($canal !== '') {
    $condiciones[] = 'p.canal = ?';
    $parametros[] = $canal;
}
$whereSql = implode(' AND ', $condiciones);

$stmt = $pdo->prepare("SELECT p.id, p.estado, p.total, p.canal, mp.nombre AS medio_pago_nombre, p.creado_en,
                               p.entregado_en, p.cerrado_en, p.cancelado_en,
                               m.nombre AS mesa_nombre,
                               u.nombre AS mozo_nombre,
                               ue.nombre AS entregado_por_nombre,
                               uc.nombre AS cerrado_por_nombre,
                               ua.nombre AS cancelado_por_nombre
                        FROM pedidos p
                        LEFT JOIN mesas m ON m.id = p.mesa_id
                        LEFT JOIN medios_pago mp ON mp.id = p.medio_pago_id
                        JOIN usuarios u ON u.id = p.usuario_id
                        LEFT JOIN usuarios ue ON ue.id = p.entregado_por_id
                        LEFT JOIN usuarios uc ON uc.id = p.cerrado_por_id
                        LEFT JOIN usuarios ua ON ua.id = p.cancelado_por_id
                        WHERE $whereSql
                        ORDER BY COALESCE(p.cerrado_en, p.cancelado_en) DESC");
$stmt->execute($parametros);
$pedidos = $stmt->fetchAll();

if (($_GET['exportar'] ?? '') === 'csv') {
    $filasCsv = array_map(fn($p) => [
        $p['id'],
        $p['mesa_nombre'] ?? '-',
        $p['canal'] === 'mesa' ? 'Mesa' : 'Mostrador',
        $p['mozo_nombre'],
        number_format((float)$p['total'], 2, ',', ''),
        $p['medio_pago_nombre'] ?? '-',
        $p['estado'] === 'cerrado' ? 'Cobrado' : 'Cancelado',
        $p['entregado_por_nombre'] ?? '-',
        $p['entregado_en'] ? date('d/m/Y H:i', strtotime($p['entregado_en'])) : '-',
        $p['cerrado_por_nombre'] ?? '-',
        $p['cerrado_en'] ? date('d/m/Y H:i', strtotime($p['cerrado_en'])) : '-',
        $p['cancelado_por_nombre'] ?? '-',
        $p['cancelado_en'] ? date('d/m/Y H:i', strtotime($p['cancelado_en'])) : '-',
    ], $pedidos);
    exportarCsv('auditoria_pedidos_' . $desde . '_a_' . $hasta . '.csv',
        ['#', 'Mesa', 'Canal', 'Mozo', 'Total', 'Medio de pago', 'Estado', 'Entregado por', 'Fecha entrega', 'Cobrado por', 'Fecha cobro', 'Cancelado por', 'Fecha cancelación'],
        $filasCsv);
}

$tituloPagina = 'Auditoría de pedidos';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">Auditoría de pedidos (cobros y cancelaciones)</h2>
  <div class="no-imprimir">
    <a class="btn btn-outline-secondary" href="?<?= h(http_build_query(array_merge($_GET, ['exportar' => 'csv']))) ?>">⬇ Exportar CSV</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>

<form method="get" action="auditoria_pedidos.php" class="row g-2 align-items-end mb-4 no-imprimir">
  <div class="col-auto">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" value="<?= h($desde) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" value="<?= h($hasta) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <label class="form-label">Canal</label>
    <select name="canal" class="form-select">
      <option value="">Todos</option>
      <option value="mostrador" <?= $canal === 'mostrador' ? 'selected' : '' ?>>Mostrador</option>
      <option value="mesa" <?= $canal === 'mesa' ? 'selected' : '' ?>>Mesa</option>
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm">
  <thead>
    <tr>
      <th>#</th><th>Mesa</th><th>Canal</th><th>Mozo</th><th>Total</th><th>Medio de pago</th><th>Estado</th>
      <th>Entregado por</th><th>Fecha entrega</th>
      <th>Cobrado por</th><th>Fecha cobro</th>
      <th>Cancelado por</th><th>Fecha cancelación</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pedidos as $p): ?>
    <tr>
      <td><?= (int)$p['id'] ?></td>
      <td><?= $p['mesa_nombre'] ? h($p['mesa_nombre']) : '-' ?></td>
      <td><?= $p['canal'] === 'mesa' ? 'Mesa' : 'Mostrador' ?></td>
      <td><?= h($p['mozo_nombre']) ?></td>
      <td><?= formatearMoneda((float)$p['total']) ?></td>
      <td><?= $p['medio_pago_nombre'] ? h($p['medio_pago_nombre']) : '-' ?></td>
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
