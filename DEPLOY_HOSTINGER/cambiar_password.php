<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $actual = $_POST['password_actual'] ?? '';
    $nueva = $_POST['password_nueva'] ?? '';
    $repetir = $_POST['password_repetir'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $stmt->execute([$_SESSION['usuario_id']]);
    $hashActual = $stmt->fetchColumn();

    if (!password_verify($actual, $hashActual)) {
        $error = 'La contraseña actual no es correcta.';
    } elseif (strlen($nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($nueva !== $repetir) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } else {
        $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([$nuevoHash, $_SESSION['usuario_id']]);
        $exito = 'Contraseña actualizada correctamente.';
    }
}

$tituloPagina = 'Cambiar contraseña';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-sm-8 col-md-5">
    <div class="card shadow-sm mt-4">
      <div class="card-body">
        <h4 class="card-title mb-3">Cambiar contraseña</h4>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
          <div class="alert alert-success"><?= h($exito) ?></div>
        <?php endif; ?>

        <form method="post" action="cambiar_password.php">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Contraseña actual</label>
            <input type="password" name="password_actual" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña nueva</label>
            <input type="password" name="password_nueva" class="form-control" minlength="6" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Repetir contraseña nueva</label>
            <input type="password" name="password_repetir" class="form-control" minlength="6" required>
          </div>
          <button type="submit" class="btn btn-primary btn-lg-touch w-100">Guardar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
