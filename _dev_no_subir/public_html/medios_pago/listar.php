<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirAdmin();

$pdo = obtenerConexion();

// --- Guardar (crear o editar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    validarTokenCsrf();

    $id = intPositivoONull($_POST['id'] ?? null);
    $nombre = trim($_POST['nombre'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre !== '') {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE medios_pago SET nombre = ?, activo = ? WHERE id = ?');
            $stmt->execute([$nombre, $activo, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO medios_pago (nombre, activo) VALUES (?, ?)');
            $stmt->execute([$nombre, $activo]);
        }
    }
    redirigir('medios_pago/listar.php');
}

$mediosPago = $pdo->query('SELECT * FROM medios_pago ORDER BY nombre')->fetchAll();

$tituloPagina = 'Medios de pago';
require __DIR__ . '/../../includes/header.php';
?>

<h2 class="mb-3">Medios de pago</h2>
<p class="text-muted">Los medios que estén "Activo" son los que van a aparecer para elegir al cobrar un pedido o cargar un egreso. Desactivar un medio no borra el historial de ventas o egresos que ya se cargaron con él.</p>

<div class="row">
  <div class="col-md-5 mb-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Nuevo medio de pago</h5>
        <form method="post" action="listar.php">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" name="activo" class="form-check-input" id="activoNuevo" checked>
            <label class="form-check-label" for="activoNuevo">Activo</label>
          </div>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Nombre</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($mediosPago as $mp): $formId = 'form_mp_' . (int)$mp['id']; ?>
        <tr>
          <td>
            <input form="<?= $formId ?>" type="text" name="nombre" value="<?= h($mp['nombre']) ?>" class="form-control form-control-sm">
          </td>
          <td>
            <div class="form-check form-switch">
              <input form="<?= $formId ?>" type="checkbox" name="activo" class="form-check-input" <?= $mp['activo'] ? 'checked' : '' ?>>
            </div>
          </td>
          <td>
            <form id="<?= $formId ?>" method="post" action="listar.php" class="d-none">
              <input type="hidden" name="accion" value="guardar">
              <input type="hidden" name="id" value="<?= (int)$mp['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
            </form>
            <button type="submit" form="<?= $formId ?>" class="btn btn-sm btn-outline-primary">Guardar</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
