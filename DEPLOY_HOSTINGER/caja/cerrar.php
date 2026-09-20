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

// --- Ventas cerradas desde que se abrió esta caja, por medio de pago ---
// Se arma la lista a partir de TODOS los medios de pago activos (LEFT JOIN),
// así un medio sin ventas/egresos igual aparece en $0 en vez de desaparecer
// de la conciliación.
$stmt = $pdo->prepare("SELECT mp.id, mp.nombre, COALESCE(v.total_ventas, 0) AS total_ventas
                        FROM medios_pago mp
                        LEFT JOIN (
                            SELECT medio_pago_id, SUM(total) AS total_ventas
                            FROM pedidos
                            WHERE estado = 'cerrado' AND cerrado_en >= ?
                            GROUP BY medio_pago_id
                        ) v ON v.medio_pago_id = mp.id
                        WHERE mp.activo = 1
                        ORDER BY mp.nombre");
$stmt->execute([$caja['abierta_en']]);
$ventasPorMedio = $stmt->fetchAll();

// --- Egresos cargados durante esta caja (por caja_sesion_id), por medio de pago ---
$stmt = $pdo->prepare("SELECT mp.id, mp.nombre, COALESCE(e.total_egresos, 0) AS total_egresos
                        FROM medios_pago mp
                        LEFT JOIN (
                            SELECT medio_pago_id, SUM(monto) AS total_egresos
                            FROM egresos
                            WHERE caja_sesion_id = ?
                            GROUP BY medio_pago_id
                        ) e ON e.medio_pago_id = mp.id
                        WHERE mp.activo = 1
                        ORDER BY mp.nombre");
$stmt->execute([$caja['id']]);
$egresosPorMedioTmp = $stmt->fetchAll();
$egresosPorMedio = [];
foreach ($egresosPorMedioTmp as $fila) {
    $egresosPorMedio[(int)$fila['id']] = (float)$fila['total_egresos'];
}

// --- Ventas por canal (mostrador / mesa), solo informativo en esta pantalla ---
$stmt = $pdo->prepare("SELECT canal, COALESCE(SUM(total), 0) AS total
                        FROM pedidos
                        WHERE estado = 'cerrado' AND cerrado_en >= ?
                        GROUP BY canal");
$stmt->execute([$caja['abierta_en']]);
$ventasPorCanal = ['mostrador' => 0, 'mesa' => 0];
foreach ($stmt->fetchAll() as $fila) {
    $ventasPorCanal[$fila['canal']] = (float)$fila['total'];
}

// --- Armamos el desglose final por medio de pago (ventas, egresos, neto) ---
$desglose = [];
$totalVendido = 0;
$totalEgresos = 0;
$totalEfectivoVentas = 0;
$totalEfectivoEgresos = 0;
foreach ($ventasPorMedio as $fila) {
    $medioId = (int)$fila['id'];
    $ventas = (float)$fila['total_ventas'];
    $egresos = $egresosPorMedio[$medioId] ?? 0;

    $desglose[] = [
        'nombre' => $fila['nombre'],
        'ventas' => $ventas,
        'egresos' => $egresos,
        'neto' => $ventas - $egresos,
    ];

    $totalVendido += $ventas;
    $totalEgresos += $egresos;

    // El efectivo esperado en caja solo se ve afectado por el medio
    // "Efectivo" (el resto de los medios no mueve el cajón físico).
    if (mb_strtolower($fila['nombre']) === 'efectivo') {
        $totalEfectivoVentas = $ventas;
        $totalEfectivoEgresos = $egresos;
    }
}

$efectivoEsperado = (float)$caja['monto_inicial'] + $totalEfectivoVentas - $totalEfectivoEgresos;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $montoFinal = filter_var($_POST['monto_final_declarado'] ?? '', FILTER_VALIDATE_FLOAT);
    $nota = trim($_POST['nota'] ?? '');

    if ($montoFinal === false || $montoFinal < 0) {
        $error = 'Ingresá el monto final contado en caja.';
    } else {
        $diferencia = round($montoFinal - $efectivoEsperado, 2);

        try {
            ejecutarTransaccion($pdo, function (PDO $pdo) use ($caja, $montoFinal, $totalEfectivoVentas, $totalEfectivoEgresos, $diferencia, $nota, $desglose, $ventasPorMedio) {
                $pdo->prepare("UPDATE caja_sesiones SET
                                estado = 'cerrada',
                                monto_final_declarado = ?,
                                total_efectivo = ?,
                                total_egresos_efectivo = ?,
                                diferencia = ?,
                                nota = ?,
                                cerrada_en = NOW()
                                WHERE id = ?")
                    ->execute([
                        $montoFinal,
                        $totalEfectivoVentas,
                        $totalEfectivoEgresos,
                        $diferencia,
                        $nota !== '' ? $nota : null,
                        $caja['id'],
                    ]);

                $stmtDetalle = $pdo->prepare('INSERT INTO caja_sesion_medios (caja_sesion_id, medio_pago_id, total_ventas, total_egresos) VALUES (?, ?, ?, ?)');
                foreach ($ventasPorMedio as $i => $fila) {
                    $medioId = (int)$fila['id'];
                    $ventas = (float)$fila['total_ventas'];
                    $egresos = $desglose[$i]['egresos'];
                    if ($ventas == 0 && $egresos == 0) {
                        continue;
                    }
                    $stmtDetalle->execute([$caja['id'], $medioId, $ventas, $egresos]);
                }
            });

            redirigir('caja/historial.php');
        } catch (Exception $e) {
            error_log('Error al cerrar caja: ' . $e->getMessage());
            $error = 'No se pudo cerrar la caja. Intentá nuevamente.';
        }
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
  <div class="col-md-7">
    <table class="table bg-white shadow-sm">
      <tbody>
        <tr><th>Abierta desde</th><td><?= h(date('d/m/Y H:i', strtotime($caja['abierta_en']))) ?></td></tr>
        <tr><th>Monto inicial</th><td><?= formatearMoneda((float)$caja['monto_inicial']) ?></td></tr>
      </tbody>
    </table>

    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Medio de pago</th><th class="text-end">Ventas</th><th class="text-end">Egresos</th><th class="text-end">Neto</th></tr></thead>
      <tbody>
        <?php foreach ($desglose as $fila): ?>
          <tr>
            <td><?= h($fila['nombre']) ?></td>
            <td class="text-end"><?= formatearMoneda($fila['ventas']) ?></td>
            <td class="text-end"><?= formatearMoneda($fila['egresos']) ?></td>
            <td class="text-end"><?= formatearMoneda($fila['neto']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="table-secondary">
          <th>Total</th>
          <th class="text-end"><?= formatearMoneda($totalVendido) ?></th>
          <th class="text-end"><?= formatearMoneda($totalEgresos) ?></th>
          <th class="text-end"><?= formatearMoneda($totalVendido - $totalEgresos) ?></th>
        </tr>
      </tbody>
    </table>

    <table class="table bg-white shadow-sm">
      <tbody>
        <tr><th>Ventas mostrador</th><td><?= formatearMoneda($ventasPorCanal['mostrador']) ?></td></tr>
        <tr><th>Ventas mesas</th><td><?= formatearMoneda($ventasPorCanal['mesa']) ?></td></tr>
        <tr class="table-info"><th>Efectivo esperado en caja</th><td><?= formatearMoneda($efectivoEsperado) ?></td></tr>
      </tbody>
    </table>
    <p class="text-muted">Efectivo esperado = monto inicial (<?= formatearMoneda((float)$caja['monto_inicial']) ?>) + ventas en efectivo (<?= formatearMoneda($totalEfectivoVentas) ?>) − egresos en efectivo (<?= formatearMoneda($totalEfectivoEgresos) ?>).</p>
  </div>

  <div class="col-md-5">
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
