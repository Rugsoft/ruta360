<?php
// seguridad_web.php · Control de sesión, roles web y CSRF (Manual 10)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * 10.17 Exigir sesión activa
 */
function exigirLogin(): void
{
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 10.17 Exigir uno de los roles permitidos en el panel web
 */
function exigirRolWeb(array $roles): void
{
    exigirLogin();
    $rol = $_SESSION['usuario']['rol'] ?? '';
    if (!in_array($rol, $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta página.');
    }
}

/**
 * Devuelve el usuario en sesión o null si es anónimo
 */
function usuarioAutenticado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/**
 * 10.23 Generar o recuperar el token CSRF de la sesión
 */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 10.23 Validar el token CSRF enviado por POST
 */
function validarCsrf(): void
{
    $recibido = (string) ($_POST['csrf_token'] ?? '');
    $guardado = (string) ($_SESSION['csrf_token'] ?? '');
    if ($guardado === '' || !hash_equals($guardado, $recibido)) {
        http_response_code(403);
        exit('La petición no es válida. Recarga la página.');
    }
}
