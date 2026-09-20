<?php
/**
 * Encabezado HTML común a todas las páginas protegidas.
 * Espera que la página que lo incluye ya haya llamado a requerirLogin()
 * y definido opcionalmente $tituloPagina.
 */
$titulo = isset($tituloPagina) ? $tituloPagina . ' - ' . NOMBRE_NEGOCIO : NOMBRE_NEGOCIO;
$base = rutaBase();

// Detección de la pantalla activa para resaltarla en el sidebar. Se arma
// a partir de SCRIPT_NAME (ej: "/mesas/salon.php" -> "mesas/salon.php")
// para poder compararlo contra las rutas relativas usadas en los href del
// menú, sin importar la profundidad de "../" de cada árbol.
$dirActualSidebar = basename(dirname($_SERVER['SCRIPT_NAME']));
$archivoActualSidebar = basename($_SERVER['SCRIPT_NAME']);
$scriptActualSidebar = ($dirActualSidebar === '' || $dirActualSidebar === '.')
    ? $archivoActualSidebar
    : $dirActualSidebar . '/' . $archivoActualSidebar;
$esActivoSidebar = function (string $ruta) use ($scriptActualSidebar): string {
    return $ruta === $scriptActualSidebar ? ' active' : '';
};
$rutasReportesSidebar = [
    'reportes/ventas.php', 'reportes/productos_top.php', 'reportes/medios_pago.php',
    'reportes/ingresos_egresos.php', 'reportes/auditoria_pedidos.php',
];
$rutasConfigSidebar = [
    'productos/listar.php', 'categorias/listar.php', 'mesas/listar.php',
    'medios_pago/listar.php', 'egresos/categorias.php', 'usuarios/listar.php',
    'perfiles/listar.php',
];
$grupoReportesActivo = in_array($scriptActualSidebar, $rutasReportesSidebar, true);
$grupoConfigActivo = in_array($scriptActualSidebar, $rutasConfigSidebar, true);

// Ronda 17: cada ítem de Caja/Reportes/Configuración se muestra según su
// propio permiso (ver includes/auth.php, tienePermiso()), no atrás de un
// único esAdmin() como antes. El grupo "Configuración" entero solo se
// muestra si hay al menos un sub-ítem habilitado.
$mostrarConfigProductos = tienePermiso('gestionar_productos');
$mostrarConfigCategorias = tienePermiso('gestionar_categorias');
$mostrarConfigMesas = tienePermiso('gestionar_mesas');
$mostrarConfigMediosPago = tienePermiso('gestionar_medios_pago');
$mostrarConfigEgresosCategorias = tienePermiso('gestionar_egresos_categorias');
$mostrarConfigUsuarios = tienePermiso('gestionar_usuarios');
$mostrarConfigPerfiles = tienePermiso('gestionar_perfiles');
$mostrarGrupoConfig = $mostrarConfigProductos || $mostrarConfigCategorias || $mostrarConfigMesas
    || $mostrarConfigMediosPago || $mostrarConfigEgresosCategorias || $mostrarConfigUsuarios || $mostrarConfigPerfiles;

// "Cache busting" del CSS propio: el navegador (o el celular/tablet de
// un empleado) puede guardar assets/css/style.css en caché durante
// mucho tiempo y no volver a pedirlo aunque el archivo cambie en el
// servidor, mostrando la página con estilos viejos (o sin la lógica de
// ocultar productos por categoría) sin ningún error visible. Agregar
// "?v=XXXX" a la URL del CSS obliga al navegador a tratarlo como un
// archivo distinto cada vez que se sube una versión nueva.
// IMPORTANTE: subir este número cada vez que se edite style.css.
$versionCss = '20260920-r19';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($titulo) ?></title>
<link rel="icon" type="image/png" href="<?= $base ?>assets/img/logo.png">
<link href="<?= $base ?>assets/vendor/bootstrap-5.3.3/bootstrap.min.css" rel="stylesheet">
<!-- Tipografía de marca (ronda 14): única dependencia externa no vendorizada
     del sistema, a diferencia de Bootstrap/Chart.js. Se eligió no vendorizarla
     por tiempo; si el local tiene internet inestable, el fallback del CSS
     (sans-serif / serif del sistema) sigue dejando todo legible y funcional,
     solo cambia la tipografía. -->
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= $base ?>assets/css/style.css?v=<?= h($versionCss) ?>" rel="stylesheet">
</head>
<body>
<?php if (estaLogueado()): ?>
<!-- Barra superior, solo visible en celular/tablet angosta (< 768px):
     hamburguesa que abre el sidebar como offcanvas. -->
<nav class="mobile-topbar d-md-none no-imprimir">
  <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
          aria-controls="sidebar" aria-label="Abrir menú">
    <span></span><span></span><span></span>
  </button>
  <a class="mobile-topbar-brand" href="<?= $base ?>dashboard.php">
    <img src="<?= $base ?>assets/img/logo.png" alt="<?= h(NOMBRE_NEGOCIO) ?>" class="logo-imagen"
         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
    <span class="logo-texto"><?= h(mb_substr(NOMBRE_NEGOCIO, 0, 2)) ?></span>
    <?= h(NOMBRE_NEGOCIO) ?>
  </a>
</nav>

<!-- Sidebar: offcanvas-md = por debajo de 768px se comporta como un panel
     off-canvas (oculto, se abre con la hamburguesa de arriba); desde 768px
     en adelante queda fijo a la izquierda como sidebar normal (ver reglas
     "@media (min-width: 768px) #sidebar.offcanvas-md" en style.css). -->
<div class="offcanvas-md offcanvas-start app-sidebar no-imprimir" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
  <div class="offcanvas-header d-md-none">
    <h5 class="offcanvas-title text-white" id="sidebarLabel"><?= h(NOMBRE_NEGOCIO) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body sidebar-body d-flex flex-column p-0">
    <div class="sidebar-brand d-none d-md-flex align-items-center justify-content-between">
      <a class="d-flex align-items-center gap-2 text-decoration-none" href="<?= $base ?>dashboard.php">
        <img src="<?= $base ?>assets/img/logo.png" alt="<?= h(NOMBRE_NEGOCIO) ?>" class="logo-imagen"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <span class="logo-texto"><?= h(mb_substr(NOMBRE_NEGOCIO, 0, 2)) ?></span>
        <span class="sidebar-brand-textos">
          <span class="sidebar-brand-text"><?= h(NOMBRE_NEGOCIO) ?></span>
          <span class="sidebar-brand-subtitulo">Panel de gestión</span>
        </span>
      </a>
    </div>

    <ul class="nav flex-column sidebar-nav flex-grow-1">
      <li class="nav-item">
        <a class="nav-link<?= $esActivoSidebar('dashboard.php') ?>" href="<?= $base ?>dashboard.php" title="Inicio">
          <span class="sidebar-icon">🏠</span><span class="sidebar-label">Inicio</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link<?= $esActivoSidebar('mesas/salon.php') ?>" href="<?= $base ?>mesas/salon.php" title="Salón">
          <span class="sidebar-icon">🍽️</span><span class="sidebar-label">Salón</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link<?= $esActivoSidebar('stock/movimientos.php') ?>" href="<?= $base ?>stock/movimientos.php" title="Stock">
          <span class="sidebar-icon">📦</span><span class="sidebar-label">Stock</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link<?= $esActivoSidebar('egresos/listar.php') ?>" href="<?= $base ?>egresos/listar.php" title="Egresos">
          <span class="sidebar-icon">💸</span><span class="sidebar-label">Egresos</span>
        </a>
      </li>
      <?php if (tienePermiso('ver_caja')): ?>
      <li class="nav-item">
        <a class="nav-link<?= $esActivoSidebar('caja/historial.php') ?>" href="<?= $base ?>caja/historial.php" title="Caja">
          <span class="sidebar-icon">💰</span><span class="sidebar-label">Caja</span>
        </a>
      </li>
      <?php endif; ?>
      <?php if (tienePermiso('ver_reportes')): ?>
      <li class="nav-item">
        <button class="nav-link sidebar-group-toggle<?= $grupoReportesActivo ? '' : ' collapsed' ?>" type="button"
                data-bs-toggle="collapse" data-bs-target="#navReportes"
                aria-expanded="<?= $grupoReportesActivo ? 'true' : 'false' ?>" aria-controls="navReportes" title="Reportes">
          <span class="sidebar-icon">📊</span><span class="sidebar-label">Reportes</span>
          <span class="sidebar-chevron">&#9662;</span>
        </button>
        <ul class="nav flex-column sidebar-subnav collapse<?= $grupoReportesActivo ? ' show' : '' ?>" id="navReportes">
          <li><a class="nav-link<?= $esActivoSidebar('reportes/ventas.php') ?>" href="<?= $base ?>reportes/ventas.php"><span class="sidebar-label">Ventas por día</span></a></li>
          <li><a class="nav-link<?= $esActivoSidebar('reportes/productos_top.php') ?>" href="<?= $base ?>reportes/productos_top.php"><span class="sidebar-label">Productos más vendidos</span></a></li>
          <li><a class="nav-link<?= $esActivoSidebar('reportes/medios_pago.php') ?>" href="<?= $base ?>reportes/medios_pago.php"><span class="sidebar-label">Por medio de pago</span></a></li>
          <li><a class="nav-link<?= $esActivoSidebar('reportes/ingresos_egresos.php') ?>" href="<?= $base ?>reportes/ingresos_egresos.php"><span class="sidebar-label">Ingresos y egresos</span></a></li>
          <li><a class="nav-link<?= $esActivoSidebar('reportes/auditoria_pedidos.php') ?>" href="<?= $base ?>reportes/auditoria_pedidos.php"><span class="sidebar-label">Auditoría de pedidos</span></a></li>
        </ul>
      </li>
      <?php endif; ?>
      <?php if ($mostrarGrupoConfig): ?>
      <li class="nav-item">
        <button class="nav-link sidebar-group-toggle<?= $grupoConfigActivo ? '' : ' collapsed' ?>" type="button"
                data-bs-toggle="collapse" data-bs-target="#navConfig"
                aria-expanded="<?= $grupoConfigActivo ? 'true' : 'false' ?>" aria-controls="navConfig" title="Configuración">
          <span class="sidebar-icon">⚙️</span><span class="sidebar-label">Configuración</span>
          <span class="sidebar-chevron">&#9662;</span>
        </button>
        <ul class="nav flex-column sidebar-subnav collapse<?= $grupoConfigActivo ? ' show' : '' ?>" id="navConfig">
          <?php if ($mostrarConfigProductos): ?><li><a class="nav-link<?= $esActivoSidebar('productos/listar.php') ?>" href="<?= $base ?>productos/listar.php"><span class="sidebar-label">Productos</span></a></li><?php endif; ?>
          <?php if ($mostrarConfigCategorias): ?><li><a class="nav-link<?= $esActivoSidebar('categorias/listar.php') ?>" href="<?= $base ?>categorias/listar.php"><span class="sidebar-label">Categorías</span></a></li><?php endif; ?>
          <?php if ($mostrarConfigMesas): ?><li><a class="nav-link<?= $esActivoSidebar('mesas/listar.php') ?>" href="<?= $base ?>mesas/listar.php"><span class="sidebar-label">Mesas</span></a></li><?php endif; ?>
          <?php if ($mostrarConfigMediosPago): ?><li><a class="nav-link<?= $esActivoSidebar('medios_pago/listar.php') ?>" href="<?= $base ?>medios_pago/listar.php"><span class="sidebar-label">Medios de pago</span></a></li><?php endif; ?>
          <?php if ($mostrarConfigEgresosCategorias): ?><li><a class="nav-link<?= $esActivoSidebar('egresos/categorias.php') ?>" href="<?= $base ?>egresos/categorias.php"><span class="sidebar-label">Categorías de egreso</span></a></li><?php endif; ?>
          <?php if ($mostrarConfigUsuarios): ?><li><a class="nav-link<?= $esActivoSidebar('usuarios/listar.php') ?>" href="<?= $base ?>usuarios/listar.php"><span class="sidebar-label">Usuarios</span></a></li><?php endif; ?>
          <?php if ($mostrarConfigPerfiles): ?><li><a class="nav-link<?= $esActivoSidebar('perfiles/listar.php') ?>" href="<?= $base ?>perfiles/listar.php"><span class="sidebar-label">Perfiles</span></a></li><?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
      <div class="sidebar-user-info">
        <?= h($_SESSION['usuario_nombre']) ?> (<?= h($_SESSION['perfil_nombre'] ?? 'Sin perfil') ?>)
      </div>
      <a href="<?= $base ?>cambiar_password.php" class="sidebar-footer-link" title="Cambiar contraseña">
        <span class="sidebar-icon">🔑</span><span class="sidebar-label">Cambiar contraseña</span>
      </a>
      <a href="<?= $base ?>logout.php" class="sidebar-footer-link" title="Salir">
        <span class="sidebar-icon">🚪</span><span class="sidebar-label">Salir</span>
      </a>
    </div>
  </div>
</div>

<?php endif; ?>
<div class="container-fluid px-3 main-content">
<?php mostrarFlashError(); mostrarFlashExito(); ?>
