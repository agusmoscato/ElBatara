<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$stmt = $pdo->query("SELECT m.*, p.id AS producto_id, p.nombre AS producto_nombre, p.tipo_venta, u.nombre AS usuario_nombre
                      FROM movimientos_stock m
                      JOIN productos p ON p.id = m.producto_id
                      JOIN usuarios u ON u.id = m.usuario_id
                      ORDER BY m.creado_en DESC
                      LIMIT 200");
$movimientos = $stmt->fetchAll();

$tituloPagina = 'Movimientos de stock';
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Movimientos de stock</h2>
  <a href="reponer.php" class="btn btn-primary">+ Reponer stock</a>
</div>

<div class="table-responsive">
<table class="table table-striped bg-white shadow-sm">
  <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Usuario</th><th>Nota</th></tr></thead>
  <tbody>
    <?php foreach ($movimientos as $m): ?>
    <tr>
      <td><?= h(date('d/m/Y H:i', strtotime($m['creado_en']))) ?></td>
      <td><a href="reponer.php?producto_id=<?= (int)$m['producto_id'] ?>"><?= h($m['producto_nombre']) ?></a></td>
      <td>
        <?php
          $etiquetas = ['venta' => 'Venta', 'ingreso' => 'Ingreso', 'ajuste' => 'Ajuste'];
          $clases = ['venta' => 'bg-danger', 'ingreso' => 'bg-success', 'ajuste' => 'bg-secondary'];
        ?>
        <span class="badge <?= $clases[$m['tipo']] ?>"><?= $etiquetas[$m['tipo']] ?></span>
      </td>
      <td><?= formatearCantidad((float)$m['cantidad'], $m['tipo_venta']) ?></td>
      <td><?= h($m['usuario_nombre']) ?></td>
      <td><?= h($m['nota'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
