<?php
require_once __DIR__ . '/seguridad_web.php';
require_once __DIR__ . '/servicios/cliente_rutas.php';

$usuario = usuarioAutenticado();

$idRuta = filter_input(
    INPUT_GET,
    'id_ruta',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idRuta === false || $idRuta === null) {
    $resultado = [
        'ok' => false,
        'estado' => 400,
        'error' => 'Selecciona una ruta válida.'
    ];
} else {
    $resultado = obtenerRutaApi($idRuta);
}

$ruta = null;
$puntos = [];

if ($resultado['ok']) {
    $ruta = $resultado['datos'];
    $puntos = $ruta['puntos_interes'] ?? [];
}

$actualizada = filter_input(INPUT_GET, 'actualizada', FILTER_VALIDATE_INT) === 1;
$errorPatch = '';

// Actividad guiada 9.30 · PATCH de duración desde la interfaz
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['cambiar_duracion']) && $idRuta) {
    exigirRolWeb(['editor', 'admin']);
    validarCsrf();
    $nuevaDuracion = trim((string) ($_POST['duracion_minutos'] ?? ''));
    $resultadoPatch = modificarRuta($idRuta, ['duracion_minutos' => $nuevaDuracion]);
    if ($resultadoPatch['ok'] ?? false) {
        header('Location: ver_ruta.php?id_ruta=' . $idRuta . '&actualizada=1');
        exit;
    }
    $errorPatch = $resultadoPatch['mensaje'] ?? 'No se ha podido actualizar la duración.';
}

// La dificultad llega como texto del contrato; la convertimos en una clase
// CSS segura para colorear la etiqueta. Un valor desconocido no rompe la página.
$claseDificultad = match ($ruta['dificultad'] ?? null) {
    'fácil' => 'facil',
    'media' => 'media',
    'alta' => 'alta',
    default => null,
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de ruta | Ruta360</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel">
    <header class="panel-cabecera">
        <a class="volver" href="rutas.php">← Todas las rutas</a>
        <p class="panel-rol">Detalle de ruta</p>
    </header>

    <?php if ($actualizada): ?>
        <div class="aviso aviso-exito">
            Ruta actualizada correctamente.
        </div>
    <?php endif; ?>

    <?php if (!$resultado['ok']): ?>
        <h1>No se ha podido cargar la ruta</h1>
        <p class="error"><?= htmlspecialchars($resultado['error']) ?></p>
    <?php else: ?>
        <h1><?= htmlspecialchars($ruta['titulo']) ?></h1>
        <p>
            <?= htmlspecialchars($ruta['ciudad']['nombre']) ?>,
            <?= htmlspecialchars($ruta['ciudad']['pais']) ?>
        </p>
        <p><?= htmlspecialchars($ruta['descripcion']) ?></p>

        <div class="acciones-ruta">
            <?php if ($usuario && in_array($usuario['rol'], ['editor', 'admin'], true)): ?>
                <a class="boton-accion" href="editar_ruta.php?id_ruta=<?= (int) $ruta['id_ruta'] ?>">Editar ruta</a>
            <?php endif; ?>
            <?php if ($usuario && $usuario['rol'] === 'admin'): ?>
                <form method="post" action="eliminar_ruta.php" onsubmit="return confirm('¿Eliminar esta ruta?');" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">
                    <input type="hidden" name="id_ruta" value="<?= (int) $ruta['id_ruta'] ?>">
                    <button type="submit" class="peligro">Eliminar ruta</button>
                </form>
            <?php endif; ?>
            <?php if (!$usuario): ?>
                <a class="boton-accion" href="login.php">Iniciar sesión para gestionar</a>
            <?php endif; ?>
        </div>

        <div class="lecturas">
            <div class="lectura">
                <p class="lectura-etiqueta">Duración</p>
                <p class="lectura-valor"><?= (int) $ruta['duracion_minutos'] ?><span class="unidad">min</span></p>
            </div>
            <div class="lectura">
                <p class="lectura-etiqueta">Distancia</p>
                <p class="lectura-valor"><?= (float) $ruta['distancia_km'] ?><span class="unidad">km</span></p>
            </div>
            <div class="lectura">
                <p class="lectura-etiqueta">Dificultad</p>
                <p class="lectura-valor">
                    <?php if ($claseDificultad === null): ?>
                        <span class="etiqueta etiqueta-neutra"><?= htmlspecialchars($ruta['dificultad'] ?? '—') ?></span>
                    <?php else: ?>
                        <span class="etiqueta etiqueta-<?= $claseDificultad ?>"><?= htmlspecialchars($ruta['dificultad']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (isset($ruta['numero_puntos'])): ?>
            <p>Lugares incluidos: <?= (int) $ruta['numero_puntos'] ?></p>
        <?php endif; ?>

        <h2>Puntos de interés</h2>
        <?php if (!$puntos): ?>
            <p>Esta ruta todavía no tiene puntos de interés.</p>
        <?php else: ?>
            <ol>
                <?php foreach ($puntos as $punto): ?>
                    <li>
                        <strong><?= htmlspecialchars($punto['nombre']) ?></strong>
                        — <?= htmlspecialchars($punto['descripcion']) ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($usuario && in_array($usuario['rol'], ['editor', 'admin'], true)): ?>
        <details class="detalle-patch">
            <summary>Ajustar solo la duración (PATCH)</summary>
            <form method="post" class="formulario-patch">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">
                <input type="hidden" name="cambiar_duracion" value="1">
                <label for="duracion_rapida">Nueva duración (minutos):</label>
                <input
                    type="number"
                    id="duracion_rapida"
                    name="duracion_minutos"
                    min="15"
                    max="1440"
                    required
                    value="<?= (int) $ruta['duracion_minutos'] ?>">
                <button type="submit">Actualizar duración</button>
            </form>
            <?php if ($errorPatch !== ''): ?>
                <p class="error"><?= htmlspecialchars($errorPatch) ?></p>
            <?php endif; ?>
        </details>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
