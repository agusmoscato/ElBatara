<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirAdmin();

$pdo = obtenerConexion();

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-29 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = date('Y-m-d', strtotime('-29 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = date('Y-m-d'); }

// --- Ingresos por canal ---
$stmt = $pdo->prepare("SELECT canal, COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
                        FROM pedidos
                        WHERE estado = 'cerrado' AND DATE(cerrado_en) BETWEEN ? AND ?
                        GROUP BY canal");
$stmt->execute([$desde, $hasta]);
$ingresosPorCanal = ['mostrador' => 0.0, 'mesa' => 0.0];
$cantidadPorCanal = ['mostrador' => 0, 'mesa' => 0];
foreach ($stmt->fetchAll() as $f) {
    $ingresosPorCanal[$f['canal']] = (float)$f['total'];
    $cantidadPorCanal[$f['canal']] = (int)$f['cantidad'];
}
$totalIngresos = array_sum($ingresosPorCanal);

// --- Ingresos por medio de pago ---
$stmt = $pdo->prepare("SELECT mp.nombre, COUNT(p.id) AS cantidad, COALESCE(SUM(p.total), 0) AS total
                        FROM pedidos p
                        JOIN medios_pago mp ON mp.id = p.medio_pago_id
                        WHERE p.estado = 'cerrado' AND DATE(p.cerrado_en) BETWEEN ? AND ?
                        GROUP BY mp.id, mp.nombre
                        ORDER BY total DESC");
$stmt->execute([$desde, $hasta]);
$ingresosPorMedio = $stmt->fetchAll();

// --- Egresos por categoría ---
$stmt = $pdo->prepare("SELECT ce.nombre, COUNT(e.id) AS cantidad, COALESCE(SUM(e.monto), 0) AS total
                        FROM egresos e
                        JOIN categorias_egreso ce ON ce.id = e.categoria_id
                        WHERE DATE(e.creado_en) BETWEEN ? AND ?
                        GROUP BY ce.id, ce.nombre
                        ORDER BY total DESC");
$stmt->execute([$desde, $hasta]);
$egresosPorCategoria = $stmt->fetchAll();
$totalEgresos = array_sum(array_column($egresosPorCategoria, 'total'));

// --- Egresos por medio de pago ---
$stmt = $pdo->prepare("SELECT mp.nombre, COUNT(e.id) AS cantidad, COALESCE(SUM(e.monto), 0) AS total
                        FROM egresos e
                        JOIN medios_pago mp ON mp.id = e.medio_pago_id
                        WHERE DATE(e.creado_en) BETWEEN ? AND ?
                        GROUP BY mp.id, mp.nombre
                        ORDER BY total DESC");
$stmt->execute([$desde, $hasta]);
$egresosPorMedio = $stmt->fetchAll();

$balanceNeto = $totalIngresos - $totalEgresos;

if (($_GET['exportar'] ?? '') === 'csv') {
    $filas = [];
    $filas[] = ['INGRESOS POR CANAL'];
    $filas[] = ['Canal', 'Cantidad', 'Total'];
    $filas[] = ['Mostrador', $cantidadPorCanal['mostrador'], number_format($ingresosPorCanal['mostrador'], 2, ',', '')];
    $filas[] = ['Mesas', $cantidadPorCanal['mesa'], number_format($ingresosPorCanal['mesa'], 2, ',', '')];
    $filas[] = [];
    $filas[] = ['INGRESOS POR MEDIO DE PAGO'];
    $filas[] = ['Medio de pago', 'Cantidad', 'Total'];
    foreach ($ingresosPorMedio as $f) {
        $filas[] = [$f['nombre'], $f['cantidad'], number_format((float)$f['total'], 2, ',', '')];
    }
    $filas[] = [];
    $filas[] = ['EGRESOS POR CATEGORÍA'];
    $filas[] = ['Categoría', 'Cantidad', 'Total'];
    foreach ($egresosPorCategoria as $f) {
        $filas[] = [$f['nombre'], $f['cantidad'], number_format((float)$f['total'], 2, ',', '')];
    }
    $filas[] = [];
    $filas[] = ['EGRESOS POR MEDIO DE PAGO'];
    $filas[] = ['Medio de pago', 'Cantidad', 'Total'];
    foreach ($egresosPorMedio as $f) {
        $filas[] = [$f['nombre'], $f['cantidad'], number_format((float)$f['total'], 2, ',', '')];
    }
    $filas[] = [];
    $filas[] = ['RESUMEN'];
    $filas[] = ['Total ingresos', '', number_format($totalIngresos, 2, ',', '')];
    $filas[] = ['Total egresos', '', number_format($totalEgresos, 2, ',', '')];
    $filas[] = ['Balance neto', '', number_format($balanceNeto, 2, ',', '')];
    exportarCsv('ingresos_egresos_' . $desde . '_a_' . $hasta . '.csv', ['Concepto', 'Cantidad', 'Total'], $filas);
}

$tituloPagina = 'Ingresos y egresos';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">Ingresos y egresos</h2>
  <div class="no-imprimir">
    <a class="btn btn-outline-secondary" href="?desde=<?= h($desde) ?>&hasta=<?= h($hasta) ?>&exportar=csv">⬇ Exportar CSV</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>

<form method="get" action="ingresos_egresos.php" class="row g-2 align-items-end mb-4 no-imprimir">
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

<div class="row mb-4">
  <div class="col-md-4">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <div class="text-muted">Total ingresos</div>
        <div class="fs-3 text-success"><?= formatearMoneda($totalIngresos) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <div class="text-muted">Total egresos</div>
        <div class="fs-3 text-danger"><?= formatearMoneda($totalEgresos) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <div class="text-muted">Balance neto del período</div>
        <div class="fs-3 <?= $balanceNeto < 0 ? 'text-danger' : 'text-success' ?>"><?= formatearMoneda($balanceNeto) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-6">
    <h5>Ingresos por canal</h5>
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Canal</th><th>Cantidad</th><th>Total</th></tr></thead>
      <tbody>
        <tr><td>Mostrador</td><td><?= $cantidadPorCanal['mostrador'] ?></td><td><?= formatearMoneda($ingresosPorCanal['mostrador']) ?></td></tr>
        <tr><td>Mesas</td><td><?= $cantidadPorCanal['mesa'] ?></td><td><?= formatearMoneda($ingresosPorCanal['mesa']) ?></td></tr>
      </tbody>
    </table>
    <canvas id="graficoCanal" height="180"></canvas>
  </div>
  <div class="col-md-6">
    <h5>Ingresos por medio de pago</h5>
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Medio de pago</th><th>Cantidad</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($ingresosPorMedio as $f): ?>
          <tr><td><?= h($f['nombre']) ?></td><td><?= (int)$f['cantidad'] ?></td><td><?= formatearMoneda((float)$f['total']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($ingresosPorMedio)): ?>
          <tr><td colspan="3" class="text-center text-muted">Sin ingresos en el período.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <canvas id="graficoMedioIngresos" height="180"></canvas>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-6">
    <h5>Egresos por categoría</h5>
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Categoría</th><th>Cantidad</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($egresosPorCategoria as $f): ?>
          <tr><td><?= h($f['nombre']) ?></td><td><?= (int)$f['cantidad'] ?></td><td><?= formatearMoneda((float)$f['total']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($egresosPorCategoria)): ?>
          <tr><td colspan="3" class="text-center text-muted">Sin egresos en el período.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="col-md-6">
    <h5>Egresos por medio de pago</h5>
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Medio de pago</th><th>Cantidad</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($egresosPorMedio as $f): ?>
          <tr><td><?= h($f['nombre']) ?></td><td><?= (int)$f['cantidad'] ?></td><td><?= formatearMoneda((float)$f['total']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($egresosPorMedio)): ?>
          <tr><td colspan="3" class="text-center text-muted">Sin egresos en el período.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= $base ?>assets/vendor/chartjs-4.4.4/chart.umd.min.js"></script>
<script>
const PALETA_GRAFICOS = ['#8B2E2E', '#c9a24b', '#4f7a6b', '#7a6a58', '#b85c5c', '#3f6b8a', '#a8763e', '#6f4e7c'];

new Chart(document.getElementById('graficoCanal'), {
  type: 'pie',
  data: {
    labels: ['Mostrador', 'Mesas'],
    datasets: [{ data: [<?= (float)$ingresosPorCanal['mostrador'] ?>, <?= (float)$ingresosPorCanal['mesa'] ?>], backgroundColor: PALETA_GRAFICOS }]
  },
  options: { responsive: true }
});

new Chart(document.getElementById('graficoMedioIngresos'), {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_map(fn($f) => $f['nombre'], $ingresosPorMedio)) ?>,
    datasets: [{ data: <?= json_encode(array_map(fn($f) => (float)$f['total'], $ingresosPorMedio)) ?>, backgroundColor: PALETA_GRAFICOS }]
  },
  options: { responsive: true }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
