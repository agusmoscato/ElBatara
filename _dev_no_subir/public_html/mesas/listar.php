<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirPermiso('gestionar_mesas');

$pdo = obtenerConexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    validarTokenCsrf();

    $id = intPositivoONull($_POST['id'] ?? null);
    $nombre = trim($_POST['nombre'] ?? '');
    $capacidad = filter_var($_POST['capacidad'] ?? 0, FILTER_VALIDATE_INT);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        flashError('El nombre no puede estar vacío.');
    } elseif ($capacidad === false || $capacidad < 0) {
        flashError('La capacidad tiene que ser un número mayor o igual a cero.');
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE mesas SET nombre = ?, capacidad = ?, activo = ? WHERE id = ?');
                $stmt->execute([$nombre, $capacidad, $activo, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO mesas (nombre, capacidad, activo) VALUES (?, ?, ?)');
                $stmt->execute([$nombre, $capacidad, $activo]);
            }
            flashExito('Guardado correctamente.');
        } catch (PDOException $e) {
            flashError('No se pudo guardar. Revisá los datos e intentá de nuevo.');
        }
    }
    redirigir('mesas/listar.php');
}

$estadoFiltro = in_array($_GET['estado'] ?? '', ['libre', 'ocupada', 'cuenta_pedida'], true) ? $_GET['estado'] : '';

if ($estadoFiltro) {
    $stmt = $pdo->prepare('SELECT * FROM mesas WHERE estado = ? ORDER BY nombre');
    $stmt->execute([$estadoFiltro]);
    $mesas = $stmt->fetchAll();
} else {
    $mesas = $pdo->query('SELECT * FROM mesas ORDER BY nombre')->fetchAll();
}

// No se pagina: la cantidad de mesas de un local físico es chica (unas
// pocas decenas como mucho), no tiene sentido la complejidad extra acá.

$tituloPagina = 'Mesas';
require __DIR__ . '/../../includes/header.php';
?>

<h2 class="mb-3">Mesas</h2>

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
    <form method="get" action="listar.php" class="row g-2 align-items-end mb-3">
      <div class="col-auto">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-select">
          <option value="">Todas</option>
          <option value="libre" <?= $estadoFiltro === 'libre' ? 'selected' : '' ?>>Libre</option>
          <option value="ocupada" <?= $estadoFiltro === 'ocupada' ? 'selected' : '' ?>>Ocupada</option>
          <option value="cuenta_pedida" <?= $estadoFiltro === 'cuenta_pedida' ? 'selected' : '' ?>>Cuenta pedida</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">Filtrar</button>
      </div>
    </form>
    <table class="table table-striped bg-white shadow-sm">
      <thead><tr><th>Nombre</th><th>Capacidad</th><th>Estado actual</th><th>Activa</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($mesas)): ?>
          <tr><td colspan="5" class="text-muted">Sin mesas que coincidan con el filtro.</td></tr>
        <?php endif; ?>
        <?php foreach ($mesas as $m): $formId = 'form_mesa_' . (int)$m['id']; ?>
        <tr>
            <td><input form="<?= $formId ?>" type="text" name="nombre" value="<?= h($m['nombre']) ?>" class="form-control form-control-sm"></td>
            <td><input form="<?= $formId ?>" type="number" name="capacidad" value="<?= (int)$m['capacidad'] ?>" class="form-control form-control-sm" style="width:80px"></td>
            <td><span class="badge bg-info text-dark"><?= h($m['estado']) ?></span></td>
            <td>
              <div class="form-check form-switch">
                <input form="<?= $formId ?>" type="checkbox" name="activo" class="form-check-input" <?= $m['activo'] ? 'checked' : '' ?>>
              </div>
            </td>
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

<?php require __DIR__ . '/../../includes/footer.php'; ?>
