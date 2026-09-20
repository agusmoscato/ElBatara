<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/paginacion.php';
requerirLogin();

$pdo = obtenerConexion();

$mensaje = '';
$productoSeleccionadoId = intPositivoONull($_GET['producto_id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $productoId = intPositivoONull($_POST['producto_id'] ?? null);
    $cantidad = filter_var($_POST['cantidad'] ?? 0, FILTER_VALIDATE_FLOAT);
    $nota = trim($_POST['nota'] ?? '');

    if ($productoId && $cantidad !== false && $cantidad > 0) {
        $resultado = ejecutarTransaccion($pdo, function (PDO $pdo) use ($productoId, $cantidad, $nota) {
            $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?')
                ->execute([$cantidad, $productoId]);

            $pdo->prepare('INSERT INTO movimientos_stock (producto_id, tipo, cantidad, usuario_id, nota) VALUES (?, ?, ?, ?, ?)')
                ->execute([$productoId, 'ingreso', $cantidad, $_SESSION['usuario_id'], $nota !== '' ? $nota : null]);
        }, 'reponer stock', 'No se pudo registrar el ingreso.');

        if ($resultado['ok']) {
            redirigir('stock/reponer.php?producto_id=' . $productoId);
        }
        $mensaje = $resultado['error'];
    } else {
        $mensaje = 'Completá el producto y una cantidad válida.';
    }
}

$productos = $pdo->query('SELECT * FROM productos WHERE activo = 1 ORDER BY nombre')->fetchAll();

// Historial del producto seleccionado (ventas + reposiciones + ajustes)
$productoSeleccionado = null;
$historial = [];
$totalHistorial = 0;
$paginaHistorial = obtenerPaginaActual();
if ($productoSeleccionadoId) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([$productoSeleccionadoId]);
    $productoSeleccionado = $stmt->fetch();

    if ($productoSeleccionado) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM movimientos_stock WHERE producto_id = ?');
        $stmt->execute([$productoSeleccionadoId]);
        $totalHistorial = (int)$stmt->fetchColumn();

        $offset = calcularOffset($paginaHistorial);
        $stmt = $pdo->prepare("SELECT m.*, u.nombre AS usuario_nombre
                                FROM movimientos_stock m
                                JOIN usuarios u ON u.id = m.usuario_id
                                WHERE m.producto_id = ?
                                ORDER BY m.creado_en DESC
                                LIMIT $offset, " . FILAS_POR_PAGINA);
        $stmt->execute([$productoSeleccionadoId]);
        $historial = $stmt->fetchAll();
    }
}

$tituloPagina = 'Reponer stock';
require __DIR__ . '/../../includes/header.php';
?>

<h2 class="mb-3">Reponer stock</h2>

<?php if ($mensaje): ?>
  <div class="alert alert-warning"><?= h($mensaje) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-md-6 mb-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" action="reponer.php">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Producto</label>
            <select name="producto_id" id="selectProducto" class="form-select" required
                    onchange="if(this.value){ window.location = 'reponer.php?producto_id=' + this.value; }">
              <option value="">Seleccionar...</option>
              <?php foreach ($productos as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $productoSeleccionadoId === (int)$p['id'] ? 'selected' : '' ?>>
                  <?= h($p['nombre']) ?> (stock actual: <?= formatearCantidad((float)$p['stock_actual'], $p['tipo_venta']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Al elegir un producto se muestra su historial de movimientos a la derecha.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Cantidad a ingresar</label>
            <input type="number" name="cantidad" step="0.001" min="0.001" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nota (opcional)</label>
            <input type="text" name="nota" class="form-control" placeholder="Ej: compra a proveedor X">
          </div>
          <button type="submit" class="btn btn-primary btn-lg-touch w-100">Registrar ingreso</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <?php if ($productoSeleccionado): ?>
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title"><?= h($productoSeleccionado['nombre']) ?></h5>
          <p class="mb-3">
            Stock actual: <strong><?= formatearCantidad((float)$productoSeleccionado['stock_actual'], $productoSeleccionado['tipo_venta']) ?></strong>
            &nbsp;|&nbsp;
            Mínimo: <?= formatearCantidad((float)$productoSeleccionado['stock_minimo'], $productoSeleccionado['tipo_venta']) ?>
          </p>
          <h6>Historial de movimientos</h6>
          <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
            <table class="table table-sm table-striped">
              <thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Usuario</th></tr></thead>
              <tbody>
                <?php if (empty($historial)): ?>
                  <tr><td colspan="4" class="text-muted">Sin movimientos registrados todavía.</td></tr>
                <?php endif; ?>
                <?php
                  $etiquetas = ['venta' => 'Venta', 'ingreso' => 'Ingreso', 'ajuste' => 'Ajuste'];
                  $clases = ['venta' => 'bg-danger', 'ingreso' => 'bg-success', 'ajuste' => 'bg-secondary'];
                ?>
                <?php foreach ($historial as $m): ?>
                  <tr>
                    <td><?= h(date('d/m/Y H:i', strtotime($m['creado_en']))) ?></td>
                    <td><span class="badge <?= $clases[$m['tipo']] ?>"><?= $etiquetas[$m['tipo']] ?></span></td>
                    <td><?= formatearCantidad((float)$m['cantidad'], $productoSeleccionado['tipo_venta']) ?></td>
                    <td><?= h($m['usuario_nombre']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= renderPaginacion($paginaHistorial, $totalHistorial) ?>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-secondary">Elegí un producto a la izquierda para ver su historial de ventas y reposiciones.</div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
