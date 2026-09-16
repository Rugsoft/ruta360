<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function usuarioActual(): ?array
{
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])
        ? $_SESSION['usuario']
        : null;
}

function exigirLogin(): array
{
    $usuario = usuarioActual();
    if ($usuario === null) {
        header('Location: login.php');
        exit;
    }
    return $usuario;
}

function exigirRolWeb(array $roles): array
{
    $usuario = exigirLogin();
    if (!in_array($usuario['rol'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta página.');
    }
    return $usuario;
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(): void
{
    $recibido = (string) ($_POST['csrf_token'] ?? '');
    $guardado = (string) ($_SESSION['csrf_token'] ?? '');
    if ($guardado === '' || !hash_equals($guardado, $recibido)) {
        http_response_code(403);
        exit('La petición no es válida. Recarga la página.');
    }
}

