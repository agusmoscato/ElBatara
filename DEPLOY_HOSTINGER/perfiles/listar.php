<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso('gestionar_perfiles');

$pdo = obtenerConexion();

// Lista de permisos disponibles: clave => etiqueta visible. Un solo lugar
// para agregar un permiso nuevo el día de mañana (acá + la columna en la
// tabla `perfiles` + el requerirPermiso() de la pantalla que corresponda).
$PERMISOS_DISPONIBLES = [
    'ver_caja' => 'Ver caja (historial de turnos)',
    'ver_reportes' => 'Ver reportes',
    'gestionar_productos' => 'Gestionar productos',
    'gestionar_categorias' => 'Gestionar categorías',
    'gestionar_mesas' => 'Gestionar mesas',
    'gestionar_medios_pago' => 'Gestionar medios de pago',
    'gestionar_egresos_categorias' => 'Gestionar categorías de egreso',
    'gestionar_usuarios' => 'Gestionar usuarios',
    'gestionar_perfiles' => 'Gestionar perfiles',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    validarTokenCsrf();

    $id = intPositivoONull($_POST['id'] ?? null);
    $nombre = trim($_POST['nombre'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    $valores = [];
    foreach ($PERMISOS_DISPONIBLES as $clave => $etiqueta) {
        $valores[$clave] = isset($_POST[$clave]) ? 1 : 0;
    }

    // Si se está editando el perfil que es el propio del usuario logueado,
    // no se puede desmarcar "Gestionar perfiles": se bloquearía a sí
    // mismo el acceso a esta misma pantalla, sin otra forma de volver a
    // entrar salvo tocar la base a mano (mismo criterio que el
    // auto-bloqueo de usuarios/listar.php).
    $esPropioPerfil = $id && $id === (int)($_SESSION['perfil_id'] ?? 0);

    if ($esPropioPerfil && !$valores['gestionar_perfiles']) {
        flashError('No podés sacarle el permiso "Gestionar perfiles" a tu propio perfil.');
    } elseif ($nombre === '') {
        flashError('El nombre no puede estar vacío.');
    } else {
        try {
            if ($id) {
                $sets = ['nombre = ?', 'activo = ?'];
                $params = [$nombre, $activo];
                foreach ($valores as $clave => $valor) {
                    $sets[] = "$clave = ?";
                    $params[] = $valor;
                }
                $params[] = $id;
                $stmt = $pdo->prepare('UPDATE perfiles SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $stmt->execute($params);
            } else {
                $columnas = array_merge(['nombre', 'activo'], array_keys($valores));
                $marcadores = implode(', ', array_fill(0, count($columnas), '?'));
                $params = array_merge([$nombre, $activo], array_values($valores));
                $stmt = $pdo->prepare('INSERT INTO perfiles (' . implode(', ', $columnas) . ') VALUES (' . $marcadores . ')');
                $stmt->execute($params);
            }
            flashExito('Guardado correctamente.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flashError('Ya existe un perfil con ese nombre.');
            } else {
                flashError('No se pudo guardar. Revisá los datos e intentá de nuevo.');
            }
        }
    }
    redirigir('perfiles/listar.php');
}

$perfiles = $pdo->query('SELECT * FROM perfiles ORDER BY nombre')->fetchAll();

$tituloPagina = 'Perfiles';
require __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-3">Perfiles</h2>
<p class="text-muted">Cada perfil define qué partes del sistema puede usar un usuario. Salón/POS, Stock y Egresos siguen disponibles para cualquier usuario logueado, sin depender del perfil — estos permisos solo cubren Caja, Reportes y las pantallas de Configuración.</p>

<div class="row">
  <div class="col-lg-4 mb-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Nuevo perfil</h5>
        <form method="post" action="listar.php">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Mozo, Cajero, Encargado">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Permisos</label>
            <?php foreach ($PERMISOS_DISPONIBLES as $clave => $etiqueta): ?>
              <div class="form-check">
                <input type="checkbox" name="<?= h($clave) ?>" class="form-check-input" id="nuevo_<?= h($clave) ?>">
                <label class="form-check-label" for="nuevo_<?= h($clave) ?>"><?= h($etiqueta) ?></label>
              </div>
            <?php endforeach; ?>
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

  <div class="col-lg-8">
    <?php foreach ($perfiles as $p): $formId = 'form_perfil_' . (int)$p['id']; $esPropioPerfil = (int)$p['id'] === (int)($_SESSION['perfil_id'] ?? 0); ?>
      <div class="card mb-3">
        <div class="card-body">
          <form id="<?= $formId ?>" method="post" action="listar.php">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">

            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
              <input type="text" name="nombre" value="<?= h($p['nombre']) ?>" class="form-control form-control-sm fw-bold" style="max-width: 260px;">
              <?php if ($esPropioPerfil): ?><span class="badge bg-secondary">Tu perfil</span><?php endif; ?>
              <div class="form-check form-switch ms-auto">
                <input type="checkbox" name="activo" class="form-check-input" <?= $p['activo'] ? 'checked' : '' ?>>
                <label class="form-check-label small">Activo</label>
              </div>
            </div>

            <div class="row row-cols-1 row-cols-md-2 g-1 mb-2">
              <?php foreach ($PERMISOS_DISPONIBLES as $clave => $etiqueta): ?>
                <div class="col">
                  <div class="form-check">
                    <input type="checkbox" name="<?= h($clave) ?>" class="form-check-input" id="<?= $formId . '_' . h($clave) ?>"
                           <?= $p[$clave] ? 'checked' : '' ?>
                           <?= ($esPropioPerfil && $clave === 'gestionar_perfiles') ? 'disabled' : '' ?>>
                    <label class="form-check-label small" for="<?= $formId . '_' . h($clave) ?>"><?= h($etiqueta) ?></label>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if ($esPropioPerfil): ?>
                <input type="hidden" name="gestionar_perfiles" value="1">
              <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
