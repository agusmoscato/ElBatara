<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirAdmin();

$pdo = obtenerConexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    validarTokenCsrf();

    $id = intPositivoONull($_POST['id'] ?? null);
    $nombre = trim($_POST['nombre'] ?? '');
    $capacidad = filter_var($_POST['capacidad'] ?? 0, FILTER_VALIDATE_INT);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre !== '' && $capacidad !== false) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE mesas SET nombre = ?, capacidad = ?, activo = ? WHERE id = ?');
            $stmt->execute([$nombre, $capacidad, $activo, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO mesas (nombre, capacidad, activo) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $capacidad, $activo]);
        }
    }
    redirigir('mesas/listar.php');
}

$mesas = $pdo->query('SELECT * FROM mesas ORDER BY nombre')->fetchAll();

$tituloPagina = 'Mesas';
require __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-3">Mesas (ABM)</h2>

<div class="row">
  <div class="col-md-4 mb-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Nueva mesa</h5>
        <form method="post" action="listar.php">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Mesa 6">
          </div>
          <div class="mb-3">
            <label class="form-label">Capacidad</label>
            <input type="number" name="capacidad" class="form-control" min="0" value="4" required>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" name="activo" class="form-check-input" id="activoNueva" checked>
            <label class="form-check-label" for="activoNueva">Activa</label>
          </div>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Nombre</th><th>Capacidad</th><th>Estado actual</th><th>Activa</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($mesas as $m): $formId = 'form_mesa_' . (int)$m['id']; ?>
        <tr>
            <td><input form="<?= $formId ?>" type="text" name="nombre" value="<?= h($m['nombre']) ?>" class="form-control form-control-sm"></td>
            <td><input form="<?= $formId ?>" type="number" name="capacidad" value="<?= (int)$m['capacidad'] ?>" class="form-control form-control-sm" style="width:80px"></td>
            <td><span class="badge bg-info text-dark"><?= h($m['estado']) ?></span></td>
            <td><input form="<?= $formId ?>" type="checkbox" name="activo" class="form-check-input" <?= $m['activo'] ? 'checked' : '' ?>></td>
            <td>
              <form id="<?= $formId ?>" method="post" action="listar.php" class="d-inline">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
