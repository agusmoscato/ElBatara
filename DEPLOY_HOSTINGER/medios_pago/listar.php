<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso('gestionar_medios_pago');

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
                $stmt = $pdo->prepare('UPDATE medios_pago SET nombre = ?, activo = ? WHERE id = ?');
                $stmt->execute([$nombre, $activo, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO medios_pago (nombre, activo) VALUES (?, ?)');
                $stmt->execute([$nombre, $activo]);
            }
            flashExito('Guardado correctamente.');
        } catch (PDOException $e) {
            flashError('No se pudo guardar. Revisá los datos e intentá de nuevo.');
        }
    }
    redirigir('medios_pago/listar.php');
}

// --- Elegir cuál medio de pago representa el efectivo físico de caja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar_efectivo') {
    validarTokenCsrf();

    $idEfectivo = intPositivoONull($_POST['id_efectivo'] ?? null);
    if (!$idEfectivo) {
        flashError('Elegí un medio de pago válido.');
    } else {
        $resultado = ejecutarTransaccion($pdo, function (PDO $pdo) use ($idEfectivo) {
            $pdo->exec('UPDATE medios_pago SET es_efectivo = 0');
            $filas = $pdo->prepare('UPDATE medios_pago SET es_efectivo = 1 WHERE id = ?');
            $filas->execute([$idEfectivo]);
            if ($filas->rowCount() === 0) {
                throw new ValidacionException('El medio de pago elegido no existe.');
            }
        }, 'marcar medio de pago como efectivo', 'No se pudo guardar. Intentá nuevamente.');

        if ($resultado['ok']) {
            flashExito('Medio de pago "efectivo de caja" actualizado.');
        } else {
            flashError($resultado['error']);
        }
    }
    redirigir('medios_pago/listar.php');
}

$mediosPago = $pdo->query('SELECT * FROM medios_pago ORDER BY nombre')->fetchAll();
$cantidadEfectivo = 0;
foreach ($mediosPago as $mp) {
    if ($mp['es_efectivo']) {
        $cantidadEfectivo++;
    }
}

$tituloPagina = 'Medios de pago';
require __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-3">Medios de pago</h2>
<p class="text-muted">Los medios que estén "Activo" son los que van a aparecer para elegir al cobrar un pedido o cargar un egreso. Desactivar un medio no borra el historial de ventas o egresos que ya se cargaron con él.</p>

<?php if ($cantidadEfectivo !== 1): ?>
  <div class="alert alert-warning">No hay ningún medio de pago marcado como "efectivo de caja" (o hay más de uno). El cálculo de "efectivo esperado" al cerrar caja no va a funcionar bien hasta que elijas uno abajo.</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-body">
    <h5 class="card-title">¿Cuál medio de pago es el efectivo físico de la caja?</h5>
    <p class="text-muted small">Se usa para calcular "efectivo esperado" al cerrar caja. No depende del nombre: podés renombrar el medio sin que se rompa el cálculo.</p>
    <form method="post" action="listar.php" class="d-flex gap-2 align-items-center flex-wrap">
      <input type="hidden" name="accion" value="marcar_efectivo">
      <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
      <select name="id_efectivo" class="form-select" style="max-width: 280px">
        <?php foreach ($mediosPago as $mp): ?>
          <option value="<?= (int)$mp['id'] ?>" <?= $mp['es_efectivo'] ? 'selected' : '' ?>><?= h($mp['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline-primary">Guardar</button>
    </form>
  </div>
</div>

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
            <?php if ($mp['es_efectivo']): ?><span class="badge bg-secondary mt-1">Efectivo de caja</span><?php endif; ?>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
