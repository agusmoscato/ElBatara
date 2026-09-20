<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirPermiso('gestionar_egresos_categorias');

$pdo = obtenerConexion();

// --- Guardar (crear o editar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    validarTokenCsrf();

    $id = intPositivoONull($_POST['id'] ?? null);
    $nombre = trim($_POST['nombre'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        flashError('El nombre no puede estar vacío.');
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE categorias_egreso SET nombre = ?, activo = ? WHERE id = ?');
                $stmt->execute([$nombre, $activo, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO categorias_egreso (nombre, activo) VALUES (?, ?)');
                $stmt->execute([$nombre, $activo]);
            }
            flashExito('Guardado correctamente.');
        } catch (PDOException $e) {
            flashError('No se pudo guardar. Revisá los datos e intentá de nuevo.');
        }
    }
    redirigir('egresos/categorias.php');
}

$categorias = $pdo->query('SELECT * FROM categorias_egreso ORDER BY nombre')->fetchAll();

$tituloPagina = 'Categorías de egreso';
require __DIR__ . '/../../includes/header.php';
?>

<h2 class="mb-3">Categorías de egreso</h2>

<div class="row">
  <div class="col-md-5 mb-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Nueva categoría de egreso</h5>
        <form method="post" action="categorias.php">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
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

  <div class="col-md-7">
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Nombre</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($categorias as $c): $formId = 'form_cate_' . (int)$c['id']; ?>
        <tr>
          <td>
            <input form="<?= $formId ?>" type="text" name="nombre" value="<?= h($c['nombre']) ?>" class="form-control form-control-sm">
          </td>
          <td>
            <div class="form-check form-switch">
              <input form="<?= $formId ?>" type="checkbox" name="activo" class="form-check-input" <?= $c['activo'] ? 'checked' : '' ?>>
            </div>
          </td>
          <td>
            <form id="<?= $formId ?>" method="post" action="categorias.php" class="d-none">
              <input type="hidden" name="accion" value="guardar">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
