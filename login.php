<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/seguridad_web.php';

// Si ya tiene sesión, redirigir al listado
if (usuarioAutenticado() !== null) {
    header('Location: rutas.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare(
        'SELECT id_usuario, nombre, email, password_hash, rol
        FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'rol' => $usuario['rol']
        ];
        header('Location: rutas.php');
        exit;
    }
    $error = 'Email o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | Ruta360</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel">
    <header class="panel-cabecera">
        <a class="volver" href="rutas.php">← Rutas públicas</a>
        <p class="panel-rol">Panel de administración</p>
    </header>

    <h1>Acceso a Ruta360</h1>

    <?php if ($error !== ''): ?>
        <div class="aviso aviso-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" class="formulario">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">

        <label for="f-email">Email</label>
        <input
            type="email"
            id="f-email"
            name="email"
            required
            autofocus
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label for="f-password">Contraseña</label>
        <input type="password" id="f-password" name="password" required>

        <button type="submit">Entrar</button>
    </form>
</main>
</body>
</html>
