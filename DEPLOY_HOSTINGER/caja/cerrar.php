<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$stmt = $pdo->query("SELECT * FROM caja_sesiones WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
$caja = $stmt->fetch();
if (!$caja) {
    redirigir('dashboard.php');
}

// Ventas cerradas desde que se abrió esta caja, agrupadas por medio de pago.
$stmt = $pdo->prepare("SELECT medio_pago, COALESCE(SUM(total), 0) AS total
                        FROM pedidos
                        WHERE estado = 'cerrado' AND cerrado_en >= ?
                        GROUP BY medio_pago");
$stmt->execute([$caja['abierta_en']]);
$totalesPorMedio = ['efectivo' => 0, 'tarjeta' => 0, 'transferencia' => 0];
foreach ($stmt->fetchAll() as $fila) {
    if (isset($totalesPorMedio[$fila['medio_pago']])) {
        $totalesPorMedio[$fila['medio_pago']] = (float)$fila['total'];
    }
}
$totalVendido = array_sum($totalesPorMedio);
$efectivoEsperado = (float)$caja['monto_inicial'] + $totalesPorMedio['efectivo'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $montoFinal = filter_var($_POST['monto_final_declarado'] ?? '', FILTER_VALIDATE_FLOAT);
    $nota = trim($_POST['nota'] ?? '');

    if ($montoFinal === false || $montoFinal < 0) {
        $error = 'Ingresá el monto final contado en caja.';
    } else {
        $diferencia = round($montoFinal - $efectivoEsperado, 2);

        $stmt = $pdo->prepare("UPDATE caja_sesiones SET
                                estado = 'cerrada',
                                monto_final_declarado = ?,
                                total_efectivo = ?,
                                total_tarjeta = ?,
                                total_transferencia = ?,
                                diferencia = ?,
                                nota = ?,
                                cerrada_en = NOW()
                                WHERE id = ?");
        $stmt->execute([
            $montoFinal,
            $totalesPorMedio['efectivo'],
            $totalesPorMedio['tarjeta'],
            $totalesPorMedio['transferencia'],
            $diferencia,
            $nota !== '' ? $nota : null,
            $caja['id'],
        ]);

        redirigir('caja/historial.php');
    }
}

$tituloPagina = 'Cerrar caja';
require __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-3">Cerrar caja</h2>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-md-6">
    <table class="table bg-white shadow-sm">
      <tbody>
        <tr><th>Abierta desde</th><td><?= h(date('d/m/Y H:i', strtotime($caja['abierta_en']))) ?></td></tr>
        <tr><th>Monto inicial</th><td><?= formatearMoneda((float)$caja['monto_inicial']) ?></td></tr>
        <tr><th>Ventas en efectivo</th><td><?= formatearMoneda($totalesPorMedio['efectivo']) ?></td></tr>
        <tr><th>Ventas con tarjeta</th><td><?= formatearMoneda($totalesPorMedio['tarjeta']) ?></td></tr>
        <tr><th>Ventas por transferencia</th><td><?= formatearMoneda($totalesPorMedio['transferencia']) ?></td></tr>
        <tr class="table-secondary"><th>Total vendido</th><td><?= formatearMoneda($totalVendido) ?></td></tr>
        <tr class="table-info"><th>Efectivo esperado en caja</th><td><?= formatearMoneda($efectivoEsperado) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Contar caja</h5>
        <form method="post" action="cerrar.php">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Monto final contado (efectivo)</label>
            <input type="number" step="0.01" min="0" name="monto_final_declarado" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Nota / observación (opcional)</label>
            <textarea name="nota" class="form-control" rows="2" placeholder="Ej: faltante por vuelto mal dado"></textarea>
          </div>
          <button type="submit" class="btn btn-danger btn-lg-touch w-100">Cerrar caja</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
