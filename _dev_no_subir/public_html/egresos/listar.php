<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/paginacion.php';
requerirLogin();

$pdo = obtenerConexion();

$desde = obtenerFechaGet('desde', date('Y-m-d', strtotime('-29 days')));
$hasta = obtenerFechaGet('hasta', date('Y-m-d'));

$categoriaId = intPositivoONull($_GET['categoria_id'] ?? null);
$medioPagoId = intPositivoONull($_GET['medio_pago_id'] ?? null);

$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 30;

$condiciones = ['DATE(e.creado_en) BETWEEN ? AND ?'];
$parametros = [$desde, $hasta];

if ($categoriaId) {
    $condiciones[] = 'e.categoria_id = ?';
    $parametros[] = $categoriaId;
}
if ($medioPagoId) {
    $condiciones[] = 'e.medio_pago_id = ?';
    $parametros[] = $medioPagoId;
}
$whereSql = implode(' AND ', $condiciones);

$stmtTotal = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(e.monto), 0) FROM egresos e WHERE $whereSql");
$stmtTotal->execute($parametros);
[$cantidadTotal, $montoTotal] = $stmtTotal->fetch(PDO::FETCH_NUM);
$cantidadTotal = (int)$cantidadTotal;
$montoTotal = (float)$montoTotal;
$totalPaginas = max(1, (int)ceil($cantidadTotal / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$sql = "SELECT e.*, ce.nombre AS categoria_nombre, mp.nombre AS medio_pago_nombre, u.nombre AS usuario_nombre
        FROM egresos e
        JOIN categorias_egreso ce ON ce.id = e.categoria_id
        JOIN medios_pago mp ON mp.id = e.medio_pago_id
        JOIN usuarios u ON u.id = e.usuario_id
        WHERE $whereSql
        ORDER BY e.creado_en DESC
        LIMIT $porPagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$egresos = $stmt->fetchAll();

$todasCategorias = $pdo->query('SELECT * FROM categorias_egreso ORDER BY nombre')->fetchAll();
$todosMedios = $pdo->query('SELECT * FROM medios_pago ORDER BY nombre')->fetchAll();

if (($_GET['exportar'] ?? '') === 'csv') {
    $stmtCsv = $pdo->prepare("SELECT e.creado_en, ce.nombre AS categoria_nombre, e.descripcion, e.monto, mp.nombre AS medio_pago_nombre, u.nombre AS usuario_nombre, e.nota
                               FROM egresos e
                               JOIN categorias_egreso ce ON ce.id = e.categoria_id
                               JOIN medios_pago mp ON mp.id = e.medio_pago_id
                               JOIN usuarios u ON u.id = e.usuario_id
                               WHERE $whereSql
                               ORDER BY e.creado_en DESC");
    $stmtCsv->execute($parametros);
    $filasCsv = array_map(fn($f) => [
        date('d/m/Y H:i', strtotime($f['creado_en'])),
        $f['categoria_nombre'],
        $f['descripcion'],
        number_format((float)$f['monto'], 2, ',', ''),
        $f['medio_pago_nombre'],
        $f['usuario_nombre'],
        $f['nota'] ?? '',
    ], $stmtCsv->fetchAll());
    exportarCsv('egresos_' . $desde . '_a_' . $hasta . '.csv',
        ['Fecha', 'Categoría', 'Descripción', 'Monto', 'Medio de pago', 'Usuario', 'Nota'], $filasCsv);
}

$tituloPagina = 'Egresos';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">Egresos</h2>
  <div class="d-flex gap-2 no-imprimir">
    <a href="nuevo.php" class="btn btn-primary">+ Nuevo egreso</a>
    <a class="btn btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET, ['exportar' => 'csv'])) ?>">⬇ Exportar CSV</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>

<?php if (isset($_GET['guardado'])): ?>
  <div class="alert alert-success">Egreso registrado correctamente.</div>
<?php endif; ?>

<form method="get" action="listar.php" class="row g-2 align-items-end mb-4 no-imprimir">
  <div class="col-auto">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" value="<?= h($desde) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" value="<?= h($hasta) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <label class="form-label">Categoría</label>
    <select name="categoria_id" class="form-select">
      <option value="">Todas</option>
      <?php foreach ($todasCategorias as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $categoriaId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Medio de pago</label>
    <select name="medio_pago_id" class="form-select">
      <option value="">Todos</option>
      <?php foreach ($todosMedios as $mp): ?>
        <option value="<?= (int)$mp['id'] ?>" <?= $medioPagoId === (int)$mp['id'] ? 'selected' : '' ?>><?= h($mp['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </div>
</form>

<div class="card text-center shadow-sm mb-3" style="max-width: 320px;">
  <div class="card-body">
    <div class="text-muted">Total del filtro (<?= $cantidadTotal ?> egresos)</div>
    <div class="fs-3"><?= formatearMoneda($montoTotal) ?></div>
  </div>
</div>

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm">
  <thead><tr><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Monto</th><th>Medio de pago</th><th>Usuario</th><th>Nota</th><th class="no-imprimir"></th></tr></thead>
  <tbody>
    <?php foreach ($egresos as $e): ?>
    <tr>
      <td><?= h(date('d/m/Y H:i', strtotime($e['creado_en']))) ?></td>
      <td><?= h($e['categoria_nombre']) ?></td>
      <td><?= h($e['descripcion']) ?></td>
      <td><?= formatearMoneda((float)$e['monto']) ?></td>
      <td><?= h($e['medio_pago_nombre']) ?></td>
      <td><?= h($e['usuario_nombre']) ?></td>
      <td><?= h($e['nota'] ?? '') ?></td>
      <td class="no-imprimir">
        <button type="button" class="btn btn-sm btn-outline-primary"
                onclick='editarEgreso(<?= json_encode([
                    'id' => (int)$e['id'],
                    'categoria_id' => (int)$e['categoria_id'],
                    'medio_pago_id' => (int)$e['medio_pago_id'],
                    'monto' => (float)$e['monto'],
                    'descripcion' => $e['descripcion'],
                    'nota' => $e['nota'] ?? '',
                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                data-bs-toggle="modal" data-bs-target="#modalEgreso">
          Editar
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($egresos)): ?>
    <tr><td colspan="8" class="text-center text-muted">No hay egresos que coincidan con el filtro.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Modal edición de egreso -->
<div class="modal fade" id="modalEgreso" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="guardar.php">
        <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
        <input type="hidden" name="id" id="eg_id">
        <div class="modal-header">
          <h5 class="modal-title">Editar egreso</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Categoría</label>
            <select name="categoria_id" id="eg_categoria_id" class="form-select" required>
              <?php foreach ($todasCategorias as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?><?= $c['activo'] ? '' : ' (inactiva)' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Medio de pago</label>
            <select name="medio_pago_id" id="eg_medio_pago_id" class="form-select" required>
              <?php foreach ($todosMedios as $mp): ?>
                <option value="<?= (int)$mp['id'] ?>"><?= h($mp['nombre']) ?><?= $mp['activo'] ? '' : ' (inactivo)' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Monto</label>
            <input type="number" step="0.01" min="0.01" name="monto" id="eg_monto" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descripción</label>
            <input type="text" name="descripcion" id="eg_descripcion" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nota (opcional)</label>
            <textarea name="nota" id="eg_nota" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editarEgreso(e) {
  document.getElementById('eg_id').value = e.id;
  document.getElementById('eg_categoria_id').value = e.categoria_id;
  document.getElementById('eg_medio_pago_id').value = e.medio_pago_id;
  document.getElementById('eg_monto').value = e.monto;
  document.getElementById('eg_descripcion').value = e.descripcion;
  document.getElementById('eg_nota').value = e.nota;
}
</script>

<?php if ($totalPaginas > 1): ?>
<nav class="no-imprimir">
  <ul class="pagination">
    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
      <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
