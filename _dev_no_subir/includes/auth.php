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

/** Intentos fallidos consecutivos que disparan el bloqueo temporal. */
const LOGIN_LIMITE_INTENTOS = 5;
/** Minutos que dura el bloqueo una vez alcanzado el límite. */
const LOGIN_MINUTOS_BLOQUEO = 5;

/**
 * Intenta iniciar sesión con usuario y contraseña.
 * Devuelve true si el login fue exitoso, o un mensaje de error (string)
 * si no — nunca details específicos de por qué (usuario inexistente vs.
 * contraseña incorrecta se tratan igual, salvo el caso de bloqueo, para no
 * ayudar a un atacante a confirmar qué usuarios existen).
 */
function iniciarSesion(string $usuario, string $password)
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare('SELECT id, nombre, usuario, password_hash, rol, activo, intentos_fallidos, bloqueado_hasta FROM usuarios WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila || !$fila['activo']) {
        return 'Usuario o contraseña incorrectos.';
    }

    if ($fila['bloqueado_hasta'] !== null && strtotime($fila['bloqueado_hasta']) > time()) {
        $minutosRestantes = (int)ceil((strtotime($fila['bloqueado_hasta']) - time()) / 60);
        return "Demasiados intentos fallidos. Probá de nuevo en $minutosRestantes minuto(s).";
    }

    if (!password_verify($password, $fila['password_hash'])) {
        registrarIntentoFallido($pdo, (int)$fila['id'], (int)$fila['intentos_fallidos']);
        return 'Usuario o contraseña incorrectos.';
    }

    $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')
        ->execute([$fila['id']]);

    // Regeneramos el ID de sesión para evitar fijación de sesión (session fixation).
    session_regenerate_id(true);

    $_SESSION['usuario_id']     = $fila['id'];
    $_SESSION['usuario_nombre'] = $fila['nombre'];
    $_SESSION['usuario_rol']    = $fila['rol'];

    return true;
}

/**
 * Suma un intento fallido al usuario y, si llega al límite, lo bloquea
 * temporalmente (ver LOGIN_LIMITE_INTENTOS/LOGIN_MINUTOS_BLOQUEO arriba).
 */
function registrarIntentoFallido(PDO $pdo, int $usuarioId, int $intentosPrevios): void
{
    $intentos = $intentosPrevios + 1;
    if ($intentos >= LOGIN_LIMITE_INTENTOS) {
        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
            ->execute([LOGIN_MINUTOS_BLOQUEO, $usuarioId]);
    } else {
        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?')
            ->execute([$intentos, $usuarioId]);
    }
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
