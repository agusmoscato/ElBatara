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
            $stmt = $pdo->prepare('UPDATE categorias SET nombre = ?, activo = ? WHERE id = ?');
            $stmt->execute([$nombre, $activo, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO categorias (nombre, activo) VALUES (?, ?)');
            $stmt->execute([$nombre, $activo]);
        }
    }
    redirigir('categorias/listar.php');
}

$texto = trim($_GET['q'] ?? '');
if ($texto !== '') {
    $stmt = $pdo->prepare('SELECT * FROM categorias WHERE nombre LIKE ? ORDER BY nombre');
    $stmt->execute(['%' . $texto . '%']);
    $categorias = $stmt->fetchAll();
} else {
    $categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
}

// Sin paginación: la cantidad de categorías de un solo local es chica.
// Si algún día crece mucho, el mismo patrón de productos/listar.php
// (COUNT + LIMIT/OFFSET + renderPaginacion) se puede aplicar acá igual.

$tituloPagina = 'Categorías';
require __DIR__ . '/../../includes/header.php';
?>

<h2 class="mb-3">Categorías</h2>

<div class="row">
  <div class="col-md-5 mb-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Nueva categoría</h5>
        <form method="post" action="listar.php">
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
    <form method="get" action="listar.php" class="row g-2 align-items-end mb-3">
      <div class="col-auto">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" value="<?= h($texto) ?>" class="form-control" placeholder="Nombre de categoría...">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Nombre</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($categorias)): ?>
          <tr><td colspan="3" class="text-muted">Sin categorías que coincidan con el filtro.</td></tr>
        <?php endif; ?>
        <?php foreach ($categorias as $c): $formId = 'form_cat_' . (int)$c['id']; ?>
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
            <form id="<?= $formId ?>" method="post" action="listar.php" class="d-none">
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
