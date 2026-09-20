<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paginacion.php';
requerirPermiso('ver_reportes');

$pdo = obtenerConexion();

// Rango de fechas por defecto: últimos 7 días.
$desde = obtenerFechaGet('desde', date('Y-m-d', strtotime('-6 days')));
$hasta = obtenerFechaGet('hasta', date('Y-m-d'));

$stmt = $pdo->prepare("SELECT DATE(cerrado_en) AS fecha, COUNT(*) AS cantidad_pedidos, SUM(total) AS total_vendido
                        FROM pedidos
                        WHERE estado = 'cerrado' AND DATE(cerrado_en) BETWEEN ? AND ?
                        GROUP BY DATE(cerrado_en)
                        ORDER BY fecha");
$stmt->execute([$desde, $hasta]);
$filas = $stmt->fetchAll();

$totalPeriodo = array_sum(array_column($filas, 'total_vendido'));

if (($_GET['exportar'] ?? '') === 'csv') {
    $filasCsv = array_map(fn($f) => [
        date('d/m/Y', strtotime($f['fecha'])),
        $f['cantidad_pedidos'],
        number_format((float)$f['total_vendido'], 2, ',', ''),
    ], $filas);
    exportarCsv('ventas_' . $desde . '_a_' . $hasta . '.csv', ['Fecha', 'Pedidos', 'Total vendido'], $filasCsv);
}

$tituloPagina = 'Ventas por día';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">Ventas por día</h2>
  <div class="no-imprimir">
    <a class="btn btn-outline-secondary" href="?desde=<?= h($desde) ?>&hasta=<?= h($hasta) ?>&exportar=csv">⬇ Exportar CSV</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>

<form method="get" action="ventas.php" class="row g-2 align-items-end mb-4 no-imprimir">
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

<?php if (empty($filas)): ?>
  <p class="text-muted">Sin ventas en el rango filtrado.</p>
<?php else: ?>
<div class="row mb-4">
  <div class="col-md-8">
    <canvas id="graficoVentas" height="120"></canvas>
  </div>
  <div class="col-md-4">
    <div class="card text-center shadow-sm">
      <div class="card-body">
        <div class="text-muted">Total del período</div>
        <div class="fs-3"><?= formatearMoneda((float)$totalPeriodo) ?></div>
      </div>
    </div>
  </div>
</div>

<table class="table table-striped bg-white shadow-sm">
  <thead><tr><th>Fecha</th><th>Pedidos</th><th>Total vendido</th></tr></thead>
  <tbody>
    <?php foreach ($filas as $f): ?>
      <tr>
        <td><?= h(date('d/m/Y', strtotime($f['fecha']))) ?></td>
        <td><?= (int)$f['cantidad_pedidos'] ?></td>
        <td><?= formatearMoneda((float)$f['total_vendido']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if (!empty($filas)): ?>
<script src="<?= $base ?>assets/vendor/chartjs-4.4.4/chart.umd.min.js"></script>
<script>
const etiquetas = <?= json_encode(array_map(fn($f) => date('d/m', strtotime($f['fecha'])), $filas)) ?>;
const totales = <?= json_encode(array_map(fn($f) => (float)$f['total_vendido'], $filas)) ?>;

new Chart(document.getElementById('graficoVentas'), {
  type: 'bar',
  data: {
    labels: etiquetas,
    datasets: [{ label: 'Total vendido', data: totales, backgroundColor: '#8B2E2E' }]
  },
  options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
