<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso('gestionar_usuarios');

$pdo = obtenerConexion();

// --- Crear o editar datos básicos (nombre, usuario, perfil, activo) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    validarTokenCsrf();

    $id = intPositivoONull($_POST['id'] ?? null);
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $perfilId = intPositivoONull($_POST['perfil_id'] ?? null);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    // Nadie puede desactivarse a sí mismo, ni asignarse a sí mismo un
    // perfil que no tenga "gestionar_usuarios": es la forma más común de
    // quedar bloqueado de esta misma pantalla por accidente (no hay
    // recuperación de contraseña por mail, ni otra forma de volver a
    // entrar salvo tocar la base a mano). Se resuelve el permiso del
    // perfil NUEVO que se está por guardar, no el actual.
    $esUnoMismo = $id && $id === (int)$_SESSION['usuario_id'];
    $perfilNuevoGestionaUsuarios = false;
    if ($perfilId) {
        $stmtPerfil = $pdo->prepare('SELECT gestionar_usuarios FROM perfiles WHERE id = ?');
        $stmtPerfil->execute([$perfilId]);
        $perfilNuevoGestionaUsuarios = (bool)$stmtPerfil->fetchColumn();
    }

    if ($esUnoMismo && !$activo) {
        flashError('No podés desactivar tu propio usuario.');
    } elseif ($esUnoMismo && !$perfilNuevoGestionaUsuarios) {
        flashError('No podés asignarte a vos mismo un perfil sin el permiso "Gestionar usuarios".');
    } elseif ($nombre === '') {
        flashError('El nombre no puede estar vacío.');
    } elseif ($usuario === '') {
        flashError('El usuario (login) no puede estar vacío.');
    } elseif (!$perfilId) {
        flashError('Elegí un perfil.');
    } elseif (!$id && strlen($password) < 6) {
        flashError('La contraseña tiene que tener al menos 6 caracteres.');
    } else {
        try {
            if ($id) {
                // Editar: no toca la contraseña (eso es "Cambiar contraseña"
                // aparte). Se resetea el bloqueo por intentos fallidos de
                // paso: si el admin está tocando este usuario, es un buen
                // momento para destrabarlo si estaba bloqueado.
                $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, perfil_id = ?, activo = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
                $stmt->execute([$nombre, $usuario, $perfilId, $activo, $id]);
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, perfil_id, activo) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$nombre, $usuario, $hash, $perfilId, $activo]);
            }
            flashExito('Guardado correctamente.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flashError('Ese nombre de usuario ya existe.');
            } else {
                flashError('No se pudo guardar. Revisá los datos e intentá de nuevo.');
            }
        }
    }
    redirigir('usuarios/listar.php');
}

// --- Cambiar contraseña de un usuario (admin, sin pedir la actual) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_password') {
    validarTokenCsrf();

    $id = intPositivoONull($_POST['id'] ?? null);
    $nueva = $_POST['password_nueva'] ?? '';
    $repetir = $_POST['password_repetir'] ?? '';

    if (!$id) {
        flashError('Usuario inválido.');
    } elseif (strlen($nueva) < 6) {
        flashError('La contraseña tiene que tener al menos 6 caracteres.');
    } elseif ($nueva !== $repetir) {
        flashError('La confirmación no coincide con la contraseña nueva.');
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = ?, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
        $stmt->execute([$hash, $id]);
        flashExito('Contraseña actualizada correctamente.');
    }
    redirigir('usuarios/listar.php');
}

$usuarios = $pdo->query('SELECT u.*, p.nombre AS perfil_nombre
                          FROM usuarios u
                          LEFT JOIN perfiles p ON p.id = u.perfil_id
                          ORDER BY u.nombre')->fetchAll();
$perfiles = $pdo->query('SELECT * FROM perfiles ORDER BY nombre')->fetchAll();

$tituloPagina = 'Usuarios';
require __DIR__ . '/../includes/header.php';
?>

<h2 class="mb-3">Usuarios</h2>
<p class="text-muted">Alta y edición de usuarios que pueden entrar al sistema. Desactivar un usuario no borra su historial de pedidos/cierres ya hechos, solo le impide volver a iniciar sesión.</p>

<div class="row">
  <div class="col-md-4 mb-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Nuevo usuario</h5>
        <form method="post" action="listar.php">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Usuario (login)</label>
            <input type="text" name="usuario" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" minlength="6" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Perfil</label>
            <select name="perfil_id" class="form-select" required>
              <?php foreach ($perfiles as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?><?= $p['activo'] ? '' : ' (inactivo)' ?></option>
              <?php endforeach; ?>
            </select>
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

  <div class="col-md-8">
    <table class="table table-striped bg-white shadow-sm align-middle">
      <thead><tr><th>Nombre</th><th>Usuario</th><th>Perfil</th><th>Activo</th><th>Estado</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): $formId = 'form_usu_' . (int)$u['id']; $esUnoMismo = (int)$u['id'] === (int)$_SESSION['usuario_id']; ?>
        <tr>
          <td><input form="<?= $formId ?>" type="text" name="nombre" value="<?= h($u['nombre']) ?>" class="form-control form-control-sm"></td>
          <td><input form="<?= $formId ?>" type="text" name="usuario" value="<?= h($u['usuario']) ?>" class="form-control form-control-sm"></td>
          <td>
            <select form="<?= $formId ?>" name="perfil_id" class="form-select form-select-sm" <?= $esUnoMismo ? 'disabled' : '' ?>>
              <?php foreach ($perfiles as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)$u['perfil_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['nombre']) ?><?= $p['activo'] ? '' : ' (inactivo)' ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($esUnoMismo): ?><input type="hidden" form="<?= $formId ?>" name="perfil_id" value="<?= (int)$u['perfil_id'] ?>"><?php endif; ?>
          </td>
          <td>
            <div class="form-check form-switch">
              <input form="<?= $formId ?>" type="checkbox" name="activo" class="form-check-input" <?= $u['activo'] ? 'checked' : '' ?> <?= $esUnoMismo ? 'disabled' : '' ?>>
            </div>
            <?php if ($esUnoMismo): ?><input type="hidden" form="<?= $formId ?>" name="activo" value="1"><?php endif; ?>
          </td>
          <td>
            <?php if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()): ?>
              <span class="badge bg-danger" title="Bloqueado por intentos fallidos hasta <?= h(date('d/m/Y H:i', strtotime($u['bloqueado_hasta']))) ?>">Bloqueado</span>
            <?php elseif ($esUnoMismo): ?>
              <span class="badge bg-secondary">Vos</span>
            <?php endif; ?>
          </td>
          <td class="text-nowrap">
            <form id="<?= $formId ?>" method="post" action="listar.php" class="d-none">
              <input type="hidden" name="accion" value="guardar">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
            </form>
            <button type="submit" form="<?= $formId ?>" class="btn btn-sm btn-outline-primary">Guardar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="cambiarPasswordDe(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['nombre'])) ?>')"
                    data-bs-toggle="modal" data-bs-target="#modalPassword">
              Contraseña
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal cambiar contraseña -->
<div class="modal fade" id="modalPassword" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="listar.php">
        <input type="hidden" name="accion" value="cambiar_password">
        <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
        <input type="hidden" name="id" id="pw_id">
        <div class="modal-header">
          <h5 class="modal-title">Cambiar contraseña — <span id="pw_nombre"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">Esto reemplaza la contraseña del usuario sin pedirle la actual. Avisale la nueva contraseña por otro medio.</p>
          <div class="mb-3">
            <label class="form-label">Contraseña nueva</label>
            <input type="password" name="password_nueva" class="form-control" minlength="6" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Repetir contraseña nueva</label>
            <input type="password" name="password_repetir" class="form-control" minlength="6" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function cambiarPasswordDe(id, nombre) {
  document.getElementById('pw_id').value = id;
  document.getElementById('pw_nombre').textContent = nombre;
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
