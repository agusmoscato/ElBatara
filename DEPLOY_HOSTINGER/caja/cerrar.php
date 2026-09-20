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
$stmt = $pdo->prepare("SELECT mp.id, mp.nombre, mp.es_efectivo, COALESCE(v.total_ventas, 0) AS total_ventas
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

    // El efectivo esperado en caja solo se ve afectado por el medio marcado
    // como es_efectivo (ronda 13: antes se comparaba por el texto del
    // nombre "Efectivo", que se rompía si alguien lo renombraba desde el
    // ABM de Medios de pago).
    if ($fila['es_efectivo']) {
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

        $resultado = ejecutarTransaccion($pdo, function (PDO $pdo) use ($caja, $montoFinal, $totalEfectivoVentas, $totalEfectivoEgresos, $diferencia, $nota, $desglose, $ventasPorMedio) {
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
        }, 'cerrar caja', 'No se pudo cerrar la caja. Intentá nuevamente.');

        if ($resultado['ok']) {
            redirigir('caja/historial.php');
        }
        $error = $resultado['error'];
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
        <form method="post" action="cerrar.php" id="formCerrarCaja">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Monto final contado (efectivo)</label>
            <input type="number" step="0.01" min="0" name="monto_final_declarado" id="montoFinalDeclarado" class="form-control" required autofocus>
            <div id="previewDiferencia" class="mt-2"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Nota / observación (opcional)</label>
            <textarea name="nota" class="form-control" rows="2" placeholder="Ej: faltante por vuelto mal dado"></textarea>
          </div>
          <button type="submit" class="btn btn-danger btn-lg-touch w-100">Cerrar caja</button>
        </form>
        <script>
        (function () {
          var EFECTIVO_ESPERADO = <?= json_encode($efectivoEsperado) ?>;
          var input = document.getElementById('montoFinalDeclarado');
          var preview = document.getElementById('previewDiferencia');
          var form = document.getElementById('formCerrarCaja');

          function formatearMoneda(n) {
            return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          }

          function calcularDiferencia() {
            var valor = parseFloat(input.value);
            if (isNaN(valor)) {
              preview.innerHTML = '';
              return null;
            }
            var diferencia = Math.round((valor - EFECTIVO_ESPERADO) * 100) / 100;
            var html;
            if (Math.abs(diferencia) < 0.005) {
              html = '<span class="badge bg-secondary">Exacto</span>';
            } else if (diferencia > 0) {
              html = '<span class="badge bg-success">+' + formatearMoneda(diferencia) + ' sobra</span>';
            } else {
              html = '<span class="badge bg-danger">−' + formatearMoneda(Math.abs(diferencia)) + ' falta</span>';
            }
            preview.innerHTML = html;
            return diferencia;
          }

          input.addEventListener('input', calcularDiferencia);

          form.addEventListener('submit', function (e) {
            var diferencia = calcularDiferencia();
            var mensaje = diferencia === null
              ? '¿Seguro que querés cerrar la caja?'
              : '¿Seguro que querés cerrar la caja? Diferencia: ' +
                (Math.abs(diferencia) < 0.005 ? 'exacto' : formatearMoneda(diferencia)) +
                '. Esta acción no se puede deshacer.';
            if (!confirm(mensaje)) {
              e.preventDefault();
            }
          });
        })();
        </script>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
