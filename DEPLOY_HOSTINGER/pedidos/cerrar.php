<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$pedidoId = intPositivoONull($_GET['pedido_id'] ?? $_POST['pedido_id'] ?? null);
if (!$pedidoId) {
    redirigir('mesas/salon.php');
}

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND estado IN ('abierto', 'en_preparacion', 'entregado')");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();
if (!$pedido) {
    redirigir('mesas/salon.php');
}

$mediosPago = $pdo->query('SELECT * FROM medios_pago WHERE activo = 1 ORDER BY nombre')->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $medioPagoId = intPositivoONull($_POST['medio_pago_id'] ?? null);

    // El pedido tiene que tener al menos un producto para poder cobrarse.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pedido_items WHERE pedido_id = ?');
    $stmt->execute([$pedidoId]);
    $cantidadItems = (int)$stmt->fetchColumn();

    $stmtMp = $pdo->prepare('SELECT id FROM medios_pago WHERE id = ? AND activo = 1');
    $stmtMp->execute([$medioPagoId]);
    $medioPagoValido = $medioPagoId && $stmtMp->fetch();

    if (!$medioPagoValido) {
        $error = 'Seleccioná un medio de pago válido.';
    } elseif ($cantidadItems === 0) {
        $error = 'El pedido no tiene productos cargados.';
    } else {
        try {
            ejecutarTransaccion($pdo, function (PDO $pdo) use ($pedido, $pedidoId, $medioPagoId) {
                $pdo->prepare("UPDATE pedidos SET estado = 'cerrado', medio_pago_id = ?, cerrado_en = NOW(), cerrado_por_id = ? WHERE id = ?")
                    ->execute([$medioPagoId, $_SESSION['usuario_id'], $pedidoId]);

                if ($pedido['mesa_id']) {
                    $pdo->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?")->execute([$pedido['mesa_id']]);
                }
            });
            redirigir('pedidos/ticket.php?pedido_id=' . $pedidoId);
        } catch (Exception $e) {
            error_log('Error al cerrar pedido: ' . $e->getMessage());
            $error = 'No se pudo cerrar el pedido. Intentá nuevamente.';
        }
    }
}

$stmt = $pdo->prepare("SELECT pi.*, p.nombre AS producto_nombre, p.tipo_venta
                        FROM pedido_items pi
                        JOIN productos p ON p.id = pi.producto_id
                        WHERE pi.pedido_id = ?
                        ORDER BY pi.id");
$stmt->execute([$pedidoId]);
$items = $stmt->fetchAll();

$mesa = null;
if ($pedido['mesa_id']) {
    $stmt = $pdo->prepare('SELECT * FROM mesas WHERE id = ?');
    $stmt->execute([$pedido['mesa_id']]);
    $mesa = $stmt->fetch();
}

$tituloPagina = 'Cobrar pedido';
require __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-3">Cobrar pedido - <?= $mesa ? h($mesa['nombre']) : 'Para llevar' ?></h2>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-md-6">
    <table class="table bg-white shadow-sm tabla-items-pedido">
      <thead><tr><th class="col-producto">Producto</th><th class="col-cantidad">Cant.</th><th class="col-subtotal">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td class="col-producto">
              <span class="nombre-producto-item" title="<?= h($it['producto_nombre']) ?>"><?= h($it['producto_nombre']) ?></span>
            </td>
            <td class="col-cantidad"><?= formatearCantidad((float)$it['cantidad'], $it['tipo_venta']) ?></td>
            <td class="col-subtotal"><?= formatearMoneda((float)$it['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <h4 class="text-end">Total a cobrar: <?= formatearMoneda((float)$pedido['total']) ?></h4>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Medio de pago</h5>
        <form method="post" action="cerrar.php?pedido_id=<?= $pedidoId ?>">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <?php foreach ($mediosPago as $i => $mp): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="medio_pago_id" value="<?= (int)$mp['id'] ?>" id="mp_<?= (int)$mp['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <label class="form-check-label" for="mp_<?= (int)$mp['id'] ?>"><?= h($mp['nombre']) ?></label>
            </div>
            <?php endforeach; ?>
            <?php if (empty($mediosPago)): ?>
              <div class="text-danger">No hay medios de pago activos. Activá al menos uno desde Medios de pago.</div>
            <?php endif; ?>
          </div>
          <button type="submit" class="btn btn-success btn-lg-touch w-100">Confirmar cobro</button>
        </form>
        <a href="nuevo.php?pedido_id=<?= $pedidoId ?>" class="btn btn-outline-secondary w-100 mt-2">Volver al pedido</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
