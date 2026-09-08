<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (estaLogueado()) {
    redirigir('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = 'Ingresá usuario y contraseña.';
    } elseif (iniciarSesion($usuario, $password)) {
        redirigir('dashboard.php');
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$tituloPagina = 'Iniciar sesión';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center mt-5">
  <div class="col-12 col-sm-8 col-md-5 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-center mb-3">
          <img src="assets/img/logo.png" alt="<?= h(NOMBRE_NEGOCIO) ?>" class="logo-login"
               onload="document.getElementById('marcaTexto').style.display='none';"
               onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
          <span class="logo-texto mx-auto" style="width:80px;height:80px;font-size:1.8rem;"><?= h(mb_substr(NOMBRE_NEGOCIO, 0, 2)) ?></span>
        </div>
        <h4 class="card-title text-center mb-4" id="marcaTexto"><?= h(NOMBRE_NEGOCIO) ?></h4>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Usuario</label>
            <input type="text" name="usuario" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100 btn-lg-touch">Ingresar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
