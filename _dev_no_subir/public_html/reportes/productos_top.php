<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirAdmin();

$pdo = obtenerConexion();

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-29 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $desde = date('Y-m-d', strtotime('-29 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $hasta = date('Y-m-d'); }

$stmt = $pdo->prepare("SELECT p.nombre, p.tipo_venta, SUM(pi.cantidad) AS cantidad_vendida, SUM(pi.subtotal) AS total_vendido
                        FROM pedido_items pi
                        JOIN pedidos ped ON ped.id = pi.pedido_id
                        JOIN productos p ON p.id = pi.producto_id
                        WHERE ped.estado = 'cerrado' AND DATE(ped.cerrado_en) BETWEEN ? AND ?
                        GROUP BY p.id, p.nombre, p.tipo_venta
                        ORDER BY total_vendido DESC
                        LIMIT 15");
$stmt->execute([$desde, $hasta]);
$filas = $stmt->fetchAll();

if (($_GET['exportar'] ?? '') === 'csv') {
    $filasCsv = array_map(fn($f) => [
        $f['nombre'],
        formatearCantidad((float)$f['cantidad_vendida'], $f['tipo_venta']),
        number_format((float)$f['total_vendido'], 2, ',', ''),
    ], $filas);
    exportarCsv('productos_top_' . $desde . '_a_' . $hasta . '.csv', ['Producto', 'Cantidad vendida', 'Total vendido'], $filasCsv);
}

$tituloPagina = 'Productos más vendidos';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">Productos más vendidos</h2>
  <div class="no-imprimir">
    <a class="btn btn-outline-secondary" href="?desde=<?= h($desde) ?>&hasta=<?= h($hasta) ?>&exportar=csv">⬇ Exportar CSV</a>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>

<form method="get" action="productos_top.php" class="row g-2 align-items-end mb-4 no-imprimir">
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
  <div class="col-md-7">
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Producto</th><th>Cantidad vendida</th><th>Total vendido</th></tr></thead>
      <tbody>
        <?php foreach ($filas as $f): ?>
          <tr>
            <td><?= h($f['nombre']) ?></td>
            <td><?= formatearCantidad((float)$f['cantidad_vendida'], $f['tipo_venta']) ?></td>
            <td><?= formatearMoneda((float)$f['total_vendido']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-md-5">
    <canvas id="graficoTop"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const etiquetas = <?= json_encode(array_map(fn($f) => $f['nombre'], $filas)) ?>;
const totales = <?= json_encode(array_map(fn($f) => (float)$f['total_vendido'], $filas)) ?>;

new Chart(document.getElementById('graficoTop'), {
  type: 'pie',
  data: {
    labels: etiquetas,
    datasets: [{ data: totales }]
  },
  options: { responsive: true }
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
