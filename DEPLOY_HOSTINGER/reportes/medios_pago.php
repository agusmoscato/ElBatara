<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirAdmin();

$pdo = obtenerConexion();

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-29 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = date('Y-m-d', strtotime('-29 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = date('Y-m-d'); }

$stmt = $pdo->prepare("SELECT mp.nombre AS medio_pago, COUNT(p.id) AS cantidad, COALESCE(SUM(p.total), 0) AS total
                        FROM pedidos p
                        JOIN medios_pago mp ON mp.id = p.medio_pago_id
                        WHERE p.estado = 'cerrado' AND DATE(p.cerrado_en) BETWEEN ? AND ?
                        GROUP BY mp.id, mp.nombre
                        ORDER BY mp.nombre");
$stmt->execute([$desde, $hasta]);
$filas = $stmt->fetchAll();

$totalGeneral = array_sum(array_column($filas, 'total'));

if (($_GET['exportar'] ?? '') === 'csv') {
    $filasCsv = array_map(fn($f) => [
        $f['medio_pago'],
        $f['cantidad'],
        number_format((float)$f['total'], 2, ',', ''),
    ], $filas);
    $filasCsv[] = ['Total', '', number_format($totalGeneral, 2, ',', '')];
    exportarCsv('medios_pago_' . $desde . '_a_' . $hasta . '.csv', ['Medio de pago', 'Cantidad de pedidos', 'Total'], $filasCsv);
}

$tituloPagina = 'Ventas por medio de pago';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">Total facturado por medio de pago</h2>
  <div class="no-imprimir">
    <a class="btn btn-outline-secondary" href="?desde=<?= h($desde) ?>&hasta=<?= h($hasta) ?>&exportar=csv">⬇ Exportar CSV</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>

<form method="get" action="medios_pago.php" class="row g-2 align-items-end mb-4 no-imprimir">
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

<div class="row">
  <div class="col-md-6">
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Medio de pago</th><th>Cantidad de pedidos</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($filas as $f): ?>
          <tr>
            <td><?= h($f['medio_pago']) ?></td>
            <td><?= (int)$f['cantidad'] ?></td>
            <td><?= formatearMoneda((float)$f['total']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
          <th>Total</th><th></th><th><?= formatearMoneda((float)$totalGeneral) ?></th>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="col-md-6">
    <canvas id="graficoMedios"></canvas>
  </div>
</div>

<script src="<?= $base ?>assets/js/chart.umd.min.js"></script>
<script>
const etiquetas = <?= json_encode(array_map(fn($f) => $f['medio_pago'], $filas)) ?>;
const totales = <?= json_encode(array_map(fn($f) => (float)$f['total'], $filas)) ?>;
const PALETA_GRAFICOS = ['#8B2E2E', '#c9a24b', '#4f7a6b', '#7a6a58', '#b85c5c', '#3f6b8a', '#a8763e', '#6f4e7c'];

new Chart(document.getElementById('graficoMedios'), {
  type: 'doughnut',
  data: {
    labels: etiquetas,
    datasets: [{ data: totales, backgroundColor: PALETA_GRAFICOS }]
  },
  options: { responsive: true }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
