<?php
/**
 * Configuración central del sistema.
 * Editar estos valores al subir el sistema a Hostinger.
 */

// --- Datos de conexión a la base de datos (hPanel > Bases de datos MySQL) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'nombre_de_tu_base');
define('DB_USER', 'usuario_de_tu_base');
define('DB_PASS', 'password_de_tu_base');
define('DB_CHARSET', 'utf8mb4');

// --- Configuración general ---
// Nombre del negocio, se muestra en el título y encabezados
define('NOMBRE_NEGOCIO', 'El Batará');

// Zona horaria (ajustar según el país donde funcione el local)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Mostrar errores solo en desarrollo. En producción, poner ambas en 0/false.
define('MODO_DESARROLLO', false);

if (MODO_DESARROLLO) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
