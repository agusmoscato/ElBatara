<?php
/**
 * Conexión a la base de datos usando PDO.
 * Se usa una función que devuelve siempre la misma conexión (patrón singleton)
 * para no abrir una conexión nueva en cada archivo que la necesite.
 */

require_once __DIR__ . '/../config/config.php';

function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $puerto = defined('DB_PORT') ? DB_PORT : '3306';
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . $puerto . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);

            // MySQL guarda NOW()/CURRENT_TIMESTAMP en la zona horaria del propio
            // servidor de base de datos (en hosting compartido suele ser UTC),
            // mientras que PHP calcula "hoy" con date_default_timezone_set().
            // Si no igualamos ambas, los reportes por fecha (ventas de hoy,
            // auditoría, etc.) quedan mal calculados cerca de la medianoche.
            // Se usa un offset numérico fijo (no un nombre de zona) porque
            // no todos los hostings tienen cargadas las tablas de zonas horarias,
            // y Argentina no aplica horario de verano desde 2009.
            $pdo->exec("SET time_zone = '-03:00'");
        } catch (PDOException $e) {
            // No exponemos el detalle del error de conexión al usuario final.
            error_log('Error de conexión a la base de datos: ' . $e->getMessage());
            die('No se pudo conectar a la base de datos. Verificá la configuración en config/config.php.');
        }
    }

    return $pdo;
}
