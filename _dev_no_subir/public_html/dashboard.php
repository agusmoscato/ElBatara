<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

// Caja abierta actualmente (si hay una)
$stmt = $pdo->prepare("SELECT * FROM caja_sesiones WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$cajaAbierta = $stmt->fetch();

// Productos con stock bajo (solo se muestra el aviso a admin)
$productosStockBajo = [];
if (esAdmin()) {
    $stmt = $pdo->query("SELECT nombre, stock_actual, stock_minimo, tipo_venta FROM productos
                          WHERE activo = 1 AND stock_actual <= stock_minimo
                          ORDER BY nombre");
    $productosStockBajo = $stmt->fetchAll();
}

// Resumen rápido del día (solo admin)
$resumenHoy = null;
if (esAdmin()) {
    $stmt = $pdo->query("SELECT COUNT(*) AS cantidad_pedidos, COALESCE(SUM(total), 0) AS total_vendido
                          FROM pedidos
                          WHERE estado = 'cerrado' AND DATE(cerrado_en) = CURDATE()");
    $resumenHoy = $stmt->fetch();
}

$tituloPagina = 'Inicio';
require __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-4">Bienvenido, <?= h($_SESSION['usuario_nombre']) ?></h2>

<?php if (!$cajaAbierta): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center">
    <span>No hay una caja abierta en este momento. Abrí la caja antes de empezar a tomar pedidos.</span>
    <a href="caja/abrir.php" class="btn btn-warning btn-sm">Abrir caja</a>
  </div>
<?php else: ?>
  <div class="alert alert-success">
    Caja abierta desde <?= h(date('d/m/Y H:i', strtotime($cajaAbierta['abierta_en']))) ?>
    con un monto inicial de <?= formatearMoneda((float)$cajaAbierta['monto_inicial']) ?>.
  </div>
<?php endif; ?>

<?php if (esAdmin() && !empty($productosStockBajo)): ?>
  <div class="alert alert-danger">
    <strong>Stock bajo:</strong>
    <?php foreach ($productosStockBajo as $p): ?>
      <span class="badge bg-danger me-1">
        <?= h($p['nombre']) ?> (<?= formatearCantidad((float)$p['stock_actual'], $p['tipo_venta']) ?>)
      </span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (esAdmin() && $resumenHoy): ?>
  <div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <div class="text-muted">Pedidos cerrados hoy</div>
          <div class="fs-3"><?= (int)$resumenHoy['cantidad_pedidos'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
      <div class="card text-center shadow-sm">
        <div class="card-body">
          <div class="text-muted">Total vendido hoy</div>
          <div class="fs-3"><?= formatearMoneda((float)$resumenHoy['total_vendido']) ?></div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-6 col-md-3">
    <a href="mesas/salon.php" class="btn btn-primary w-100 py-4 btn-lg-touch">Ir al salón</a>
  </div>
  <div class="col-6 col-md-3">
    <a href="egresos/nuevo.php" class="btn btn-secondary w-100 py-4 btn-lg-touch">Cargar egreso</a>
  </div>
  <div class="col-6 col-md-3">
    <a href="stock/movimientos.php" class="btn btn-secondary w-100 py-4 btn-lg-touch">Ver stock</a>
  </div>
  <div class="col-6 col-md-3">
    <a href="caja/historial.php" class="btn btn-secondary w-100 py-4 btn-lg-touch">Historial de caja</a>
  </div>
  <?php if (esAdmin()): ?>
  <div class="col-6 col-md-3">
    <a href="productos/listar.php" class="btn btn-secondary w-100 py-4 btn-lg-touch">Gestionar productos</a>
  </div>
  <div class="col-6 col-md-3">
    <a href="reportes/ingresos_egresos.php" class="btn btn-secondary w-100 py-4 btn-lg-touch">Ingresos y egresos</a>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
