<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

// Si ya hay una caja abierta, no se puede abrir otra.
$stmt = $pdo->query("SELECT * FROM caja_sesiones WHERE estado = 'abierta' LIMIT 1");
$cajaAbierta = $stmt->fetch();
if ($cajaAbierta) {
    redirigir('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $montoInicial = filter_var($_POST['monto_inicial'] ?? '', FILTER_VALIDATE_FLOAT);

    if ($montoInicial === false || $montoInicial < 0) {
        $error = 'Ingresá un monto inicial válido.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO caja_sesiones (usuario_id, monto_inicial, estado) VALUES (?, ?, ?)');
        $stmt->execute([$_SESSION['usuario_id'], $montoInicial, 'abierta']);
        redirigir('dashboard.php');
    }
}

$tituloPagina = 'Abrir caja';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm mt-4">
      <div class="card-body">
        <h4 class="card-title mb-3">Abrir caja</h4>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="abrir.php">
          <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">
          <div class="mb-3">
            <label class="form-label">Monto inicial (efectivo en caja)</label>
            <input type="number" step="0.01" min="0" name="monto_inicial" class="form-control" required autofocus>
          </div>
          <button type="submit" class="btn btn-primary w-100 btn-lg-touch">Abrir caja</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
