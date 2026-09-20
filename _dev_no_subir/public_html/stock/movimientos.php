<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/paginacion.php';
requerirLogin();

$pdo = obtenerConexion();

$desde = obtenerFechaGet('desde', date('Y-m-d', strtotime('-30 days')));
$hasta = obtenerFechaGet('hasta', date('Y-m-d'));
$productoId = intPositivoONull($_GET['producto_id'] ?? null);
$tipo = in_array($_GET['tipo'] ?? '', ['venta', 'ingreso', 'ajuste'], true) ? $_GET['tipo'] : '';

$where = ['DATE(m.creado_en) BETWEEN ? AND ?'];
$params = [$desde, $hasta];
if ($productoId) {
    $where[] = 'm.producto_id = ?';
    $params[] = $productoId;
}
if ($tipo) {
    $where[] = 'm.tipo = ?';
    $params[] = $tipo;
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM movimientos_stock m WHERE $whereSql");
$stmt->execute($params);
$totalFilas = (int)$stmt->fetchColumn();

$pagina = obtenerPaginaActual();
$offset = calcularOffset($pagina);
$stmt = $pdo->prepare("SELECT m.*, p.id AS producto_id, p.nombre AS producto_nombre, p.tipo_venta, u.nombre AS usuario_nombre
                        FROM movimientos_stock m
                        JOIN productos p ON p.id = m.producto_id
                        JOIN usuarios u ON u.id = m.usuario_id
                        WHERE $whereSql
                        ORDER BY m.creado_en DESC
                        LIMIT $offset, " . FILAS_POR_PAGINA);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

$productos = $pdo->query('SELECT id, nombre FROM productos ORDER BY nombre')->fetchAll();

$tituloPagina = 'Movimientos de stock';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Movimientos de stock</h2>
  <a href="reponer.php" class="btn btn-primary">+ Reponer stock</a>
</div>

<form method="get" action="movimientos.php" class="row g-2 align-items-end mb-3 no-imprimir">
  <div class="col-auto">
    <label class="form-label">Desde</label>
    <input type="date" name="desde" value="<?= h($desde) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <label class="form-label">Hasta</label>
    <input type="date" name="hasta" value="<?= h($hasta) ?>" class="form-control">
  </div>
  <div class="col-auto">
    <label class="form-label">Producto</label>
    <select name="producto_id" class="form-select">
      <option value="">Todos</option>
      <?php foreach ($productos as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $productoId === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Tipo</label>
    <select name="tipo" class="form-select">
      <option value="">Todos</option>
      <option value="venta" <?= $tipo === 'venta' ? 'selected' : '' ?>>Venta</option>
      <option value="ingreso" <?= $tipo === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
      <option value="ajuste" <?= $tipo === 'ajuste' ? 'selected' : '' ?>>Ajuste</option>
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm">
  <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Usuario</th><th>Nota</th></tr></thead>
  <tbody>
    <?php if (empty($movimientos)): ?>
      <tr><td colspan="6" class="text-muted">Sin movimientos en el rango filtrado.</td></tr>
    <?php endif; ?>
    <?php foreach ($movimientos as $m): ?>
    <tr>
      <td><?= h(date('d/m/Y H:i', strtotime($m['creado_en']))) ?></td>
      <td><a href="reponer.php?producto_id=<?= (int)$m['producto_id'] ?>"><?= h($m['producto_nombre']) ?></a></td>
      <td>
        <?php
          $etiquetas = ['venta' => 'Venta', 'ingreso' => 'Ingreso', 'ajuste' => 'Ajuste'];
          $clases = ['venta' => 'bg-danger', 'ingreso' => 'bg-success', 'ajuste' => 'bg-secondary'];
        ?>
        <span class="badge <?= $clases[$m['tipo']] ?>"><?= $etiquetas[$m['tipo']] ?></span>
      </td>
      <td><?= formatearCantidad((float)$m['cantidad'], $m['tipo_venta']) ?></td>
      <td><?= h($m['usuario_nombre']) ?></td>
      <td><?= h($m['nota'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= renderPaginacion($pagina, $totalFilas) ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
