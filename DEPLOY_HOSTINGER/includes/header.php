<?php
/**
 * Encabezado HTML común a todas las páginas protegidas.
 * Espera que la página que lo incluye ya haya llamado a requerirLogin()
 * y definido opcionalmente $tituloPagina.
 */
$titulo = isset($tituloPagina) ? $tituloPagina . ' - ' . NOMBRE_NEGOCIO : NOMBRE_NEGOCIO;
$base = rutaBase();

// "Cache busting" del CSS propio: el navegador (o el celular/tablet de
// un empleado) puede guardar assets/css/style.css en caché durante
// mucho tiempo y no volver a pedirlo aunque el archivo cambie en el
// servidor, mostrando la página con estilos viejos (o sin la lógica de
// ocultar productos por categoría) sin ningún error visible. Agregar
// "?v=XXXX" a la URL del CSS obliga al navegador a tratarlo como un
// archivo distinto cada vez que se sube una versión nueva.
// IMPORTANTE: subir este número cada vez que se edite style.css.
$versionCss = '20260907-r6';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($titulo) ?></title>
<link href="<?= $base ?>assets/vendor/bootstrap-5.3.3/bootstrap.min.css" rel="stylesheet">
<link href="<?= $base ?>assets/css/style.css?v=<?= h($versionCss) ?>" rel="stylesheet">
</head>
<body>
<?php if (estaLogueado()): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3 no-imprimir">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>dashboard.php">
      <img src="<?= $base ?>assets/img/logo.png" alt="<?= h(NOMBRE_NEGOCIO) ?>" class="logo-imagen"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <span class="logo-texto"><?= h(mb_substr(NOMBRE_NEGOCIO, 0, 2)) ?></span>
      <?= h(NOMBRE_NEGOCIO) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>mesas/salon.php">Salón</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>productos/listar.php">Productos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>stock/movimientos.php">Stock</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>caja/historial.php">Caja</a></li>
        <?php if (esAdmin()): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Reportes</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?= $base ?>reportes/ventas.php">Ventas por día</a></li>
            <li><a class="dropdown-item" href="<?= $base ?>reportes/productos_top.php">Productos más vendidos</a></li>
            <li><a class="dropdown-item" href="<?= $base ?>reportes/medios_pago.php">Por medio de pago</a></li>
            <li><a class="dropdown-item" href="<?= $base ?>reportes/auditoria_pedidos.php">Auditoría de pedidos</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>categorias/listar.php">Categorías</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>mesas/listar.php">Mesas (ABM)</a></li>
        <?php endif; ?>
      </ul>
      <span class="navbar-text text-light me-3">
        <?= h($_SESSION['usuario_nombre']) ?> (<?= h($_SESSION['usuario_rol']) ?>)
      </span>
      <a href="<?= $base ?>cambiar_password.php" class="btn btn-outline-light btn-sm me-2">Cambiar contraseña</a>
      <a href="<?= $base ?>logout.php" class="btn btn-outline-light btn-sm">Salir</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<div class="container-fluid px-3">
<?php mostrarFlashError(); ?>
