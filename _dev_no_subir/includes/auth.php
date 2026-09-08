<?php
/**
 * Manejo de sesión, login y control de acceso por rol.
 */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // La cookie de sesión solo se marca "Secure" si la conexión ya es HTTPS.
    // Si se marcara siempre, el navegador no enviaría la cookie por HTTP
    // y nadie podría loguearse hasta activar el certificado SSL en Hostinger.
    $conexionSegura = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $conexionSegura,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Intenta iniciar sesión con usuario y contraseña.
 * Devuelve true si el login fue exitoso, false si no.
 */
function iniciarSesion(string $usuario, string $password): bool
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare('SELECT id, nombre, usuario, password_hash, rol, activo FROM usuarios WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila || !$fila['activo']) {
        return false;
    }

    if (!password_verify($password, $fila['password_hash'])) {
        return false;
    }

    // Regeneramos el ID de sesión para evitar fijación de sesión (session fixation).
    session_regenerate_id(true);

    $_SESSION['usuario_id']     = $fila['id'];
    $_SESSION['usuario_nombre'] = $fila['nombre'];
    $_SESSION['usuario_rol']    = $fila['rol'];

    return true;
}

function cerrarSesion(): void
{
    $_SESSION = [];
    session_destroy();
}

function estaLogueado(): bool
{
    return isset($_SESSION['usuario_id']);
}

function esAdmin(): bool
{
    return estaLogueado() && $_SESSION['usuario_rol'] === 'admin';
}

/**
 * Corta la ejecución y redirige a login si no hay sesión activa.
 * Debe llamarse al principio de toda página protegida.
 */
function requerirLogin(): void
{
    if (!estaLogueado()) {
        header('Location: ' . rutaBase() . 'login.php');
        exit;
    }
}

/**
 * Corta la ejecución si el usuario logueado no es administrador.
 */
function requerirAdmin(): void
{
    requerirLogin();
    if (!esAdmin()) {
        header('Location: ' . rutaBase() . 'dashboard.php');
        exit;
    }
}

/**
 * Calcula la ruta relativa hacia la raíz del sitio (public_html)
 * en base a la profundidad de carpetas del script actual.
 * Permite usar enlaces tipo rutaBase() . 'login.php' desde cualquier subcarpeta.
 */
function rutaBase(): string
{
    // SCRIPT_NAME siempre es relativo a la raíz del dominio.
    $partes = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    // Restamos 1 porque el último elemento es el archivo .php, no una carpeta.
    $profundidad = count($partes) - 1;

    // Si el script está directamente en la raíz (ej: /login.php), no hace falta subir.
    if ($profundidad <= 0) {
        return './';
    }

    return str_repeat('../', $profundidad);
}
