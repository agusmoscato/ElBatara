<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requerirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('egresos/listar.php');
}

validarTokenCsrf();

$pdo = obtenerConexion();

$id = intPositivoONull($_POST['id'] ?? null);
$categoriaId = intPositivoONull($_POST['categoria_id'] ?? null);
$medioPagoId = intPositivoONull($_POST['medio_pago_id'] ?? null);
$monto = filter_var($_POST['monto'] ?? '', FILTER_VALIDATE_FLOAT);
$descripcion = trim($_POST['descripcion'] ?? '');
$nota = trim($_POST['nota'] ?? '');

if (!$id) {
    redirigir('egresos/listar.php');
}

// Edición de un egreso ya cargado (antes solo se podía crear, nunca
// corregir un error de tipeo). A diferencia de egresos/nuevo.php, acá NO
// se exige que la categoría/medio de pago sigan activos: un egreso viejo
// puede tener una categoría que después se desactivó, y forzar a
// cambiarla para poder editar cualquier otro campo sería un obstáculo
// que nadie pidió.
if (!$categoriaId) {
    flashError('Elegí una categoría.');
} elseif (!$medioPagoId) {
    flashError('Elegí un medio de pago.');
} elseif ($monto === false || $monto <= 0) {
    flashError('Ingresá un monto válido.');
} elseif ($descripcion === '') {
    flashError('Ingresá una descripción corta.');
} else {
    try {
        $stmt = $pdo->prepare('UPDATE egresos SET categoria_id = ?, descripcion = ?, monto = ?, medio_pago_id = ?, nota = ? WHERE id = ?');
        $stmt->execute([$categoriaId, $descripcion, $monto, $medioPagoId, $nota !== '' ? $nota : null, $id]);
        flashExito('Egreso actualizado correctamente.');
    } catch (PDOException $e) {
        flashError('No se pudo guardar. Revisá los datos e intentá de nuevo.');
    }
}

redirigir('egresos/listar.php');
