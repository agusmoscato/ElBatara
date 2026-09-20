<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/paginacion.php';
requerirPermiso('ver_reportes');

$pdo = obtenerConexion();

$desde = obtenerFechaGet('desde', date('Y-m-d', strtotime('-6 days')));
$hasta = obtenerFechaGet('hasta', date('Y-m-d'));
$usuarioId = intPositivoONull($_GET['usuario_id'] ?? null);
$estadoFiltro = in_array($_GET['estado'] ?? '', ['cerrado', 'cancelado'], true) ? $_GET['estado'] : '';
$canal = $_GET['canal'] ?? '';
if (!in_array($canal, ['mostrador', 'mesa'], true)) { $canal = ''; }

$where = ["p.estado IN ('cerrado', 'cancelado')", 'DATE(COALESCE(p.cerrado_en, p.cancelado_en)) BETWEEN ? AND ?'];
$params = [$desde, $hasta];
if ($estadoFiltro) {
    $where = ['p.estado = ?', 'DATE(COALESCE(p.cerrado_en, p.cancelado_en)) BETWEEN ? AND ?'];
    $params = [$estadoFiltro, $desde, $hasta];
}
if ($usuarioId) {
    $where[] = '(p.usuario_id = ? OR p.cuenta_pedida_por_id = ? OR p.cerrado_por_id = ? OR p.cancelado_por_id = ?)';
    array_push($params, $usuarioId, $usuarioId, $usuarioId, $usuarioId);
}
if ($canal !== '') {
    $where[] = 'p.canal = ?';
    $params[] = $canal;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos p WHERE $whereSql");
$stmt->execute($params);
$totalFilas = (int)$stmt->fetchColumn();

$pagina = obtenerPaginaActual();
$offset = calcularOffset($pagina);
$sqlListado = "SELECT p.id, p.estado, p.total, p.canal, mp.nombre AS medio_pago_nombre, p.creado_en,
                               p.cuenta_pedida_en, p.cerrado_en, p.cancelado_en,
                               m.nombre AS mesa_nombre,
                               u.nombre AS mozo_nombre,
                               ue.nombre AS cuenta_pedida_por_nombre,
                               uc.nombre AS cerrado_por_nombre,
                               ua.nombre AS cancelado_por_nombre,
                               p.motivo_cancelacion
                        FROM pedidos p
                        LEFT JOIN mesas m ON m.id = p.mesa_id
                        LEFT JOIN medios_pago mp ON mp.id = p.medio_pago_id
                        JOIN usuarios u ON u.id = p.usuario_id
                        LEFT JOIN usuarios ue ON ue.id = p.cuenta_pedida_por_id
                        LEFT JOIN usuarios uc ON uc.id = p.cerrado_por_id
                        LEFT JOIN usuarios ua ON ua.id = p.cancelado_por_id
                        WHERE $whereSql
                        ORDER BY COALESCE(p.cerrado_en, p.cancelado_en) DESC";

$stmt = $pdo->prepare($sqlListado . " LIMIT $offset, " . FILAS_POR_PAGINA);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

$usuarios = $pdo->query('SELECT id, nombre FROM usuarios ORDER BY nombre')->fetchAll();

if (($_GET['exportar'] ?? '') === 'csv') {
    $stmtExport = $pdo->prepare($sqlListado);
    $stmtExport->execute($params);
    $pedidosExport = $stmtExport->fetchAll();
    $filasCsv = array_map(fn($p) => [
        $p['id'],
        $p['mesa_nombre'] ?? '-',
        $p['canal'] === 'mesa' ? 'Mesa' : 'Mostrador',
        $p['mozo_nombre'],
        number_format((float)$p['total'], 2, ',', ''),
        $p['medio_pago_nombre'] ?? '-',
        $p['estado'] === 'cerrado' ? 'Cobrado' : 'Cancelado',
        $p['cuenta_pedida_por_nombre'] ?? '-',
        $p['cuenta_pedida_en'] ? date('d/m/Y H:i', strtotime($p['cuenta_pedida_en'])) : '-',
        $p['cerrado_por_nombre'] ?? '-',
        $p['cerrado_en'] ? date('d/m/Y H:i', strtotime($p['cerrado_en'])) : '-',
        $p['cancelado_por_nombre'] ?? '-',
        $p['cancelado_en'] ? date('d/m/Y H:i', strtotime($p['cancelado_en'])) : '-',
        $p['motivo_cancelacion'] ?? '-',
    ], $pedidosExport);
    exportarCsv('auditoria_pedidos_' . $desde . '_a_' . $hasta . '.csv',
        ['#', 'Mesa', 'Canal', 'Mozo', 'Total', 'Medio de pago', 'Estado', 'Cuenta pedida por', 'Fecha cuenta pedida', 'Cobrado por', 'Fecha cobro', 'Cancelado por', 'Fecha cancelación', 'Motivo cancelación'],
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
    <label class="form-label">Usuario</label>
    <select name="usuario_id" class="form-select">
      <option value="">Todos</option>
      <?php foreach ($usuarios as $u): ?>
        <option value="<?= (int)$u['id'] ?>" <?= $usuarioId === (int)$u['id'] ? 'selected' : '' ?>><?= h($u['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Estado</label>
    <select name="estado" class="form-select">
      <option value="">Todos</option>
      <option value="cerrado" <?= $estadoFiltro === 'cerrado' ? 'selected' : '' ?>>Cobrado</option>
      <option value="cancelado" <?= $estadoFiltro === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
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
      <th>Cuenta pedida por</th><th>Fecha cuenta pedida</th>
      <th>Cobrado por</th><th>Fecha cobro</th>
      <th>Cancelado por</th><th>Fecha cancelación</th><th>Motivo cancelación</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($pedidos)): ?>
      <tr><td colspan="14" class="text-muted">Sin pedidos que coincidan con el filtro.</td></tr>
    <?php endif; ?>
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
      <td><?= $p['cuenta_pedida_por_nombre'] ? h($p['cuenta_pedida_por_nombre']) : '-' ?></td>
      <td><?= $p['cuenta_pedida_en'] ? h(date('d/m/Y H:i', strtotime($p['cuenta_pedida_en']))) : '-' ?></td>
      <td><?= $p['cerrado_por_nombre'] ? h($p['cerrado_por_nombre']) : '-' ?></td>
      <td><?= $p['cerrado_en'] ? h(date('d/m/Y H:i', strtotime($p['cerrado_en']))) : '-' ?></td>
      <td><?= $p['cancelado_por_nombre'] ? h($p['cancelado_por_nombre']) : '-' ?></td>
      <td><?= $p['cancelado_en'] ? h(date('d/m/Y H:i', strtotime($p['cancelado_en']))) : '-' ?></td>
      <td><?= $p['motivo_cancelacion'] ? h($p['motivo_cancelacion']) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= renderPaginacion($pagina, $totalFilas) ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
