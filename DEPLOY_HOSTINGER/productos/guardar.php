<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('productos/listar.php');
}

validarTokenCsrf();

$pdo = obtenerConexion();

$id = intPositivoONull($_POST['id'] ?? null);
$nombre = trim($_POST['nombre'] ?? '');
$categoriaId = intPositivoONull($_POST['categoria_id'] ?? null);
$tipoVenta = ($_POST['tipo_venta'] ?? '') === 'peso' ? 'peso' : 'unidad';
$precio = filter_var($_POST['precio'] ?? 0, FILTER_VALIDATE_FLOAT);
$stockActual = filter_var($_POST['stock_actual'] ?? 0, FILTER_VALIDATE_FLOAT);
$stockMinimo = filter_var($_POST['stock_minimo'] ?? 0, FILTER_VALIDATE_FLOAT);
$activo = isset($_POST['activo']) ? 1 : 0;

if ($nombre === '' || !$categoriaId || $precio === false || $precio < 0 || $stockActual === false || $stockMinimo === false) {
    redirigir('productos/listar.php');
}

if ($id) {
    $stmt = $pdo->prepare('UPDATE productos SET categoria_id = ?, nombre = ?, tipo_venta = ?, precio = ?, stock_actual = ?, stock_minimo = ?, activo = ? WHERE id = ?');
    $stmt->execute([$categoriaId, $nombre, $tipoVenta, $precio, $stockActual, $stockMinimo, $activo, $id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$categoriaId, $nombre, $tipoVenta, $precio, $stockActual, $stockMinimo, $activo]);
}

redirigir('productos/listar.php');
