<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$categorias = $pdo->query('SELECT * FROM categorias_egreso WHERE activo = 1 ORDER BY nombre')->fetchAll();
$mediosPago = $pdo->query('SELECT * FROM medios_pago WHERE activo = 1 ORDER BY nombre')->fetchAll();

$stmt = $pdo->query("SELECT id FROM caja_sesiones WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
$cajaAbiertaId = $stmt->fetchColumn();
$cajaAbiertaId = $cajaAbiertaId ? (int)$cajaAbiertaId : null;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarTokenCsrf();

    $categoriaId = intPositivoONull($_POST['categoria_id'] ?? null);
    $medioPagoId = intPositivoONull($_POST['medio_pago_id'] ?? null);
    $monto = filter_var($_POST['monto'] ?? '', FILTER_VALIDATE_FLOAT);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nota = trim($_POST['nota'] ?? '');

    if (!$categoriaId) {
        $error = 'Elegí una categoría.';
    } elseif (!$medioPagoId) {
        $error = 'Elegí un medio de pago.';
    } elseif ($monto === false || $monto <= 0) {
        $error = 'Ingresá un monto válido.';
    } elseif ($descripcion === '') {
        $error = 'Ingresá una descripción corta.';
    } else {
        // Validamos que la categoría y el medio de pago elegidos sigan activos
        // (evita que un envío con datos viejos guarde un egreso con una
        // categoría o medio ya desactivado).
        $stmtCat = $pdo->prepare('SELECT id FROM categorias_egreso WHERE id = ? AND activo = 1');
        $stmtCat->execute([$categoriaId]);
        $stmtMp = $pdo->prepare('SELECT id FROM medios_pago WHERE id = ? AND activo = 1');
        $stmtMp->execute([$medioPagoId]);

        if (!$stmtCat->fetch()) {
            $error = 'La categoría elegida ya no está activa.';
        } elseif (!$stmtMp->fetch()) {
            $error = 'El medio de pago elegido ya no está activo.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO egresos (categoria_id, descripcion, monto, medio_pago_id, caja_sesion_id, usuario_id)
                                    VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$categoriaId, $descripcion, $monto, $medioPagoId, $cajaAbiertaId, $_SESSION['usuario_id']]);
            redirigir('egresos/listar.php?guardado=1');
        }
    }
}

$tituloPagina = 'Nuevo egreso';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">Nuevo egreso</h2>
  <a href="listar.php" class="btn btn-outline-secondary">Ver egresos</a>
</div>

<?php if (!$cajaAbiertaId): ?>
  <div class="alert alert-warning">No hay una caja abierta ahora mismo. Este egreso se va a guardar igual, pero no se va a descontar de ningún cierre de caja.</div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="nuevo.php" class="row g-3">
  <input type="hidden" name="categoria_id" id="categoriaIdInput" value="<?= h($_POST['categoria_id'] ?? '') ?>">
  <input type="hidden" name="medio_pago_id" id="medioPagoIdInput" value="<?= h($_POST['medio_pago_id'] ?? '') ?>">
  <input type="hidden" name="csrf_token" value="<?= h(generarTokenCsrf()) ?>">

  <div class="col-12">
    <label class="form-label fw-bold">Categoría</label>
    <div class="row g-2" id="grillaCategorias">
      <?php foreach ($categorias as $c): ?>
        <div class="col-6 col-md-3">
          <button type="button" class="btn producto-btn chip-egreso" data-tipo="categoria" data-id="<?= (int)$c['id'] ?>">
            <div class="fw-bold text-center"><?= h($c['nombre']) ?></div>
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-12">
    <label class="form-label fw-bold">Medio de pago</label>
    <div class="row g-2" id="grillaMedios">
      <?php foreach ($mediosPago as $mp): ?>
        <div class="col-6 col-md-3">
          <button type="button" class="btn producto-btn chip-egreso" data-tipo="medio" data-id="<?= (int)$mp['id'] ?>">
            <div class="fw-bold text-center"><?= h($mp['nombre']) ?></div>
          </button>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-md-4">
    <label class="form-label">Monto</label>
    <input type="number" step="0.01" min="0.01" name="monto" class="form-control form-control-lg" value="<?= h($_POST['monto'] ?? '') ?>" required autofocus>
  </div>
  <div class="col-md-8">
    <label class="form-label">Descripción</label>
    <input type="text" name="descripcion" class="form-control form-control-lg" placeholder="Ej: pago proveedor de fiambres" value="<?= h($_POST['descripcion'] ?? '') ?>" required>
  </div>
  <div class="col-12">
    <label class="form-label">Nota (opcional)</label>
    <textarea name="nota" class="form-control" rows="2"><?= h($_POST['nota'] ?? '') ?></textarea>
  </div>

  <div class="col-12">
    <button type="submit" class="btn btn-danger btn-lg-touch w-100">Registrar egreso</button>
  </div>
</form>

<style>
.chip-egreso.chip-seleccionado {
  background-color: rgba(139, 46, 46, 0.18);
  border-color: var(--marca-principal);
}
</style>
<script>
function seleccionarChip(tipo, id, boton) {
  const grilla = tipo === 'categoria' ? 'grillaCategorias' : 'grillaMedios';
  document.querySelectorAll('#' + grilla + ' .chip-egreso').forEach(b => b.classList.remove('chip-seleccionado'));
  boton.classList.add('chip-seleccionado');
  document.getElementById(tipo === 'categoria' ? 'categoriaIdInput' : 'medioPagoIdInput').value = id;
}

document.querySelectorAll('.chip-egreso').forEach(boton => {
  boton.addEventListener('click', function () {
    seleccionarChip(this.dataset.tipo, this.dataset.id, this);
  });
});

// Si el formulario vuelve con un error, re-marcamos los chips que ya
// estaban elegidos (para no perder la selección del usuario).
(function () {
  const catId = document.getElementById('categoriaIdInput').value;
  const mpId = document.getElementById('medioPagoIdInput').value;
  if (catId) {
    const b = document.querySelector('#grillaCategorias .chip-egreso[data-id="' + catId + '"]');
    if (b) b.classList.add('chip-seleccionado');
  }
  if (mpId) {
    const b = document.querySelector('#grillaMedios .chip-egreso[data-id="' + mpId + '"]');
    if (b) b.classList.add('chip-seleccionado');
  }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
