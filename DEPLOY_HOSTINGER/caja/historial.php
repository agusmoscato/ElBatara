<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paginacion.php';
requerirLogin();

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

$stmt = $pdo->query("SELECT * FROM caja_sesiones WHERE estado = 'abierta' LIMIT 1");
$cajaAbierta = $stmt->fetch();

$tituloPagina = 'Historial de caja';
require __DIR__ . '/../includes/header.php';
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
      <th>Efectivo</th><th>Tarjeta</th><th>Transferencia</th><th>Diferencia</th><th>Estado</th><th>Nota</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($sesiones)): ?>
      <tr><td colspan="10" class="text-muted">Sin cajas en el rango filtrado.</td></tr>
    <?php endif; ?>
    <?php foreach ($sesiones as $s): ?>
    <tr>
      <td><?= h(date('d/m/Y H:i', strtotime($s['abierta_en']))) ?></td>
      <td><?= $s['cerrada_en'] ? h(date('d/m/Y H:i', strtotime($s['cerrada_en']))) : '-' ?></td>
      <td><?= h($s['usuario_nombre']) ?></td>
      <td><?= formatearMoneda((float)$s['monto_inicial']) ?></td>
      <td><?= $s['total_efectivo'] !== null ? formatearMoneda((float)$s['total_efectivo']) : '-' ?></td>
      <td><?= $s['total_tarjeta'] !== null ? formatearMoneda((float)$s['total_tarjeta']) : '-' ?></td>
      <td><?= $s['total_transferencia'] !== null ? formatearMoneda((float)$s['total_transferencia']) : '-' ?></td>
      <td class="<?= $s['diferencia'] !== null && (float)$s['diferencia'] < 0 ? 'text-danger' : '' ?>">
        <?= $s['diferencia'] !== null ? formatearMoneda((float)$s['diferencia']) : '-' ?>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
