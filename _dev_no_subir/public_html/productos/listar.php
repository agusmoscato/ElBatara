<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/paginacion.php';
requerirAdmin();

$pdo = obtenerConexion();

$categorias = $pdo->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre')->fetchAll();

$categoriaId = intPositivoONull($_GET['categoria_id'] ?? null);
$texto = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($categoriaId) {
    $where[] = 'p.categoria_id = ?';
    $params[] = $categoriaId;
}
if ($texto !== '') {
    $where[] = 'p.nombre LIKE ?';
    $params[] = '%' . $texto . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM productos p $whereSql");
$stmt->execute($params);
$totalFilas = (int)$stmt->fetchColumn();

$pagina = obtenerPaginaActual();
$offset = calcularOffset($pagina);
$stmt = $pdo->prepare("SELECT p.*, c.nombre AS categoria_nombre
                        FROM productos p
                        JOIN categorias c ON c.id = p.categoria_id
                        $whereSql
                        ORDER BY p.nombre
                        LIMIT $offset, " . FILAS_POR_PAGINA);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$tituloPagina = 'Productos';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Productos</h2>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="nuevoProducto()">
    + Nuevo producto
  </button>
</div>

<form method="get" action="listar.php" class="row g-2 align-items-end mb-3">
  <div class="col-auto">
    <label class="form-label">Buscar</label>
    <input type="text" name="q" value="<?= h($texto) ?>" class="form-control" placeholder="Nombre del producto...">
  </div>
  <div class="col-auto">
    <label class="form-label">Categoría</label>
    <select name="categoria_id" class="form-select">
      <option value="">Todas</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $categoriaId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </div>
</form>

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm align-middle">
  <thead>
    <tr>
      <th>Nombre</th><th>Categoría</th><th>Tipo</th><th>Precio</th>
      <th>Stock</th><th>Stock mín.</th><th>Estado</th><th></th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($productos)): ?>
      <tr><td colspan="8" class="text-muted">Sin productos que coincidan con el filtro.</td></tr>
    <?php endif; ?>
    <?php foreach ($productos as $p): ?>
    <tr class="<?= (float)$p['stock_actual'] <= (float)$p['stock_minimo'] ? 'stock-bajo' : '' ?>">
      <td><?= h($p['nombre']) ?></td>
      <td><?= h($p['categoria_nombre']) ?></td>
      <td><?= $p['tipo_venta'] === 'peso' ? 'Por peso (kg)' : 'Por unidad' ?></td>
      <td>
        <?= formatearMoneda((float)$p['precio']) ?>
        <?php if ($p['precio_a_revisar']): ?>
          <span class="badge bg-warning text-dark" title="Precio cargado desde una foto de la carta, a confirmar con el dueño">⚠ Revisar precio</span>
        <?php endif; ?>
      </td>
      <td><?= formatearCantidad((float)$p['stock_actual'], $p['tipo_venta']) ?></td>
      <td><?= formatearCantidad((float)$p['stock_minimo'], $p['tipo_venta']) ?></td>
      <td>
        <span class="badge <?= $p['activo'] ? 'bg-success' : 'bg-secondary' ?>">
          <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
        </span>
      </td>
      <td>
        <button class="btn btn-sm btn-outline-primary"
                onclick='editarProducto(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                data-bs-toggle="modal" data-bs-target="#modalProducto">
          Editar
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= renderPaginacion($pagina, $totalFilas) ?>

<!-- Modal alta/edición -->
<div class="modal fade" id="modalProducto" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="guardar.php">
        <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
        <input type="hidden" name="id" id="f_id">
        <div class="modal-header">
          <h5 class="modal-title" id="tituloModal">Nuevo producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" id="f_nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Categoría</label>
            <select name="categoria_id" id="f_categoria_id" class="form-select" required>
              <?php foreach ($categorias as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo de venta</label>
            <select name="tipo_venta" id="f_tipo_venta" class="form-select">
              <option value="unidad">Por unidad</option>
              <option value="peso">Por peso (kg)</option>
            </select>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Precio (por unidad o por kg)</label>
              <input type="number" step="0.01" min="0" name="precio" id="f_precio" class="form-control" required>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Stock actual</label>
              <input type="number" step="0.001" min="0" name="stock_actual" id="f_stock_actual" class="form-control" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Stock mínimo (para alertas)</label>
            <input type="number" step="0.001" min="0" name="stock_minimo" id="f_stock_minimo" class="form-control" required>
          </div>
          <div class="form-check form-switch">
            <input type="checkbox" name="activo" id="f_activo" class="form-check-input" checked>
            <label class="form-check-label" for="f_activo">Activo (visible en el menú)</label>
          </div>
          <div class="form-check form-switch">
            <input type="checkbox" name="precio_a_revisar" id="f_precio_a_revisar" class="form-check-input">
            <label class="form-check-label" for="f_precio_a_revisar">⚠ Precio a revisar (mostrar advertencia hasta confirmarlo)</label>
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
function nuevoProducto() {
  document.getElementById('tituloModal').textContent = 'Nuevo producto';
  document.getElementById('f_id').value = '';
  document.getElementById('f_nombre').value = '';
  document.getElementById('f_tipo_venta').value = 'unidad';
  document.getElementById('f_precio').value = '';
  document.getElementById('f_stock_actual').value = '';
  document.getElementById('f_stock_minimo').value = '';
  document.getElementById('f_activo').checked = true;
  document.getElementById('f_precio_a_revisar').checked = false;
}

function editarProducto(p) {
  document.getElementById('tituloModal').textContent = 'Editar producto';
  document.getElementById('f_id').value = p.id;
  document.getElementById('f_nombre').value = p.nombre;
  document.getElementById('f_categoria_id').value = p.categoria_id;
  document.getElementById('f_tipo_venta').value = p.tipo_venta;
  document.getElementById('f_precio').value = p.precio;
  document.getElementById('f_stock_actual').value = p.stock_actual;
  document.getElementById('f_stock_minimo').value = p.stock_minimo;
  document.getElementById('f_activo').checked = p.activo == 1;
  document.getElementById('f_precio_a_revisar').checked = p.precio_a_revisar == 1;
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
