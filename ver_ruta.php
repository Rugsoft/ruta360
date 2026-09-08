<?php
require_once __DIR__ . '/servicios/cliente_rutas.php';

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
                <p class="lectura-valor"><?= htmlspecialchars($ruta['dificultad'] ?? '—') ?></p>
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
    <?php endif; ?>
</main>
</body>
</html>
