<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/paginacion.php';
requerirPermiso('ver_caja');

$pdo = obtenerConexion();

$desde = obtenerFechaGet('desde', date('Y-m-d', strtotime('-30 days')));
$hasta = obtenerFechaGet('hasta', date('Y-m-d'));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM caja_sesiones WHERE DATE(abierta_en) BETWEEN ? AND ?");
$stmt->execute([$desde, $hasta]);
$totalFilas = (int)$stmt->fetchColumn();

$pagina = obtenerPaginaActual();
$offset = calcularOffset($pagina);
$stmt = $pdo->prepare("SELECT c.*, u.nombre AS usuario_nombre
                        FROM caja_sesiones c
                        JOIN usuarios u ON u.id = c.usuario_id
                        WHERE DATE(c.abierta_en) BETWEEN ? AND ?
                        ORDER BY c.id DESC
                        LIMIT $offset, " . FILAS_POR_PAGINA);
$stmt->execute([$desde, $hasta]);
$sesiones = $stmt->fetchAll();

// Desglose por medio de pago de cada cierre (ronda 8 en adelante), para
// mostrar el detalle completo sin depender de columnas fijas por medio.
$detallePorSesion = [];
if (!empty($sesiones)) {
    $ids = array_column($sesiones, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmtDetalle = $pdo->prepare("SELECT csm.caja_sesion_id, mp.nombre, mp.es_efectivo, csm.total_ventas, csm.total_egresos
                                   FROM caja_sesion_medios csm
                                   JOIN medios_pago mp ON mp.id = csm.medio_pago_id
                                   WHERE csm.caja_sesion_id IN ($marcadores)
                                   ORDER BY mp.nombre");
    $stmtDetalle->execute($ids);
    foreach ($stmtDetalle->fetchAll() as $fila) {
        $detallePorSesion[$fila['caja_sesion_id']][] = $fila;
    }
}

$stmt = $pdo->query("SELECT * FROM caja_sesiones WHERE estado = 'abierta' LIMIT 1");
$cajaAbierta = $stmt->fetch();

$tituloPagina = 'Historial de caja';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Historial de caja</h2>
  <?php if ($cajaAbierta): ?>
    <a href="cerrar.php" class="btn btn-danger">Cerrar caja actual</a>
  <?php else: ?>
    <a href="abrir.php" class="btn btn-primary">Abrir caja</a>
  <?php endif; ?>
</div>

<form method="get" action="historial.php" class="row g-2 align-items-end mb-3">
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

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm">
  <thead>
    <tr>
      <th>Apertura</th><th>Cierre</th><th>Usuario</th><th>Monto inicial</th>
      <th>Efectivo</th><th>Otros medios</th><th>Egresos efectivo</th><th>Diferencia</th><th>Estado</th><th>Nota</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($sesiones)): ?>
      <tr><td colspan="10" class="text-muted">Sin cajas en el rango filtrado.</td></tr>
    <?php endif; ?>
    <?php foreach ($sesiones as $s):
      $detalle = $detallePorSesion[$s['id']] ?? [];
      $otrosMedios = 0;
      $detalleTexto = [];
      foreach ($detalle as $d) {
          if (!$d['es_efectivo']) {
              $otrosMedios += (float)$d['total_ventas'];
          }
          if ((float)$d['total_ventas'] > 0 || (float)$d['total_egresos'] > 0) {
              $detalleTexto[] = h($d['nombre']) . ': ' . formatearMoneda((float)$d['total_ventas'])
                  . ((float)$d['total_egresos'] > 0 ? ' (egresos ' . formatearMoneda((float)$d['total_egresos']) . ')' : '');
          }
      }
      // Cierres de antes de la ronda 8 no tienen filas en caja_sesion_medios;
      // para esos usamos las columnas viejas como respaldo.
      if (empty($detalle) && $s['estado'] === 'cerrada') {
          $otrosMedios = (float)($s['total_tarjeta'] ?? 0) + (float)($s['total_transferencia'] ?? 0);
      }
    ?>
    <tr>
      <td><?= h(date('d/m/Y H:i', strtotime($s['abierta_en']))) ?></td>
      <td><?= $s['cerrada_en'] ? h(date('d/m/Y H:i', strtotime($s['cerrada_en']))) : '-' ?></td>
      <td><?= h($s['usuario_nombre']) ?></td>
      <td><?= formatearMoneda((float)$s['monto_inicial']) ?></td>
      <td><?= $s['total_efectivo'] !== null ? formatearMoneda((float)$s['total_efectivo']) : '-' ?></td>
      <td>
        <?= $s['estado'] === 'cerrada' ? formatearMoneda($otrosMedios) : '-' ?>
        <?php if (!empty($detalleTexto)): ?>
          <br><small class="text-muted"><?= implode(' · ', $detalleTexto) ?></small>
        <?php endif; ?>
      </td>
      <td><?= $s['total_egresos_efectivo'] !== null ? formatearMoneda((float)$s['total_egresos_efectivo']) : '-' ?></td>
      <td>
        <?php if ($s['diferencia'] === null): ?>
          <span class="text-muted">-</span>
        <?php elseif (abs((float)$s['diferencia']) < 0.005): ?>
          <span class="badge bg-secondary">Exacto</span>
        <?php elseif ((float)$s['diferencia'] > 0): ?>
          <span class="badge bg-success">+<?= formatearMoneda((float)$s['diferencia']) ?> sobra</span>
        <?php else: ?>
          <span class="badge bg-danger">−<?= formatearMoneda(abs((float)$s['diferencia'])) ?> falta</span>
        <?php endif; ?>
      </td>
      <td>
        <span class="badge <?= $s['estado'] === 'abierta' ? 'bg-success' : 'bg-secondary' ?>">
          <?= $s['estado'] === 'abierta' ? 'Abierta' : 'Cerrada' ?>
        </span>
      </td>
      <td><?= h($s['nota'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= renderPaginacion($pagina, $totalFilas) ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
