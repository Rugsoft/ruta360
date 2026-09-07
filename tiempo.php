<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/servicios/meteorologia.php';

$idCiudad = filter_input(INPUT_GET, 'id_ciudad', FILTER_VALIDATE_INT);
if (!$idCiudad || $idCiudad < 1) {
    exit('La ciudad seleccionada no es válida.');
}

$sql = 'SELECT id_ciudad, nombre, pais, latitud, longitud
        FROM ciudades
        WHERE id_ciudad = :id_ciudad AND activa = 1';

$consulta = $pdo->prepare($sql);
$consulta->execute(['id_ciudad' => $idCiudad]);
$ciudad = $consulta->fetch();

if (!$ciudad) {
    exit('La ciudad no existe o no está disponible.');
}

$resultado = obtenerTiempoActual(
    (float)$ciudad['latitud'],
    (float)$ciudad['longitud']
);

$temperatura = null;
$viento = null;
$direccionViento = null;

if ($resultado['ok']) {
    $actual = $resultado['datos']['current'] ?? [];
    $temperatura = $actual['temperature_2m'] ?? null;
    $viento = $actual['wind_speed_10m'] ?? null;
    $direccionViento = $actual['wind_direction_10m'] ?? null;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tiempo en <?= htmlspecialchars($ciudad['nombre']) ?></title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel">
    <header class="panel-cabecera">
        <a class="volver" href="index.php">← Elegir otra ciudad</a>
        <p class="coordenadas"><?= htmlspecialchars((string)$ciudad['latitud']) ?>, <?= htmlspecialchars((string)$ciudad['longitud']) ?></p>
    </header>

    <h1>
        Tiempo en <?= htmlspecialchars($ciudad['nombre']) ?>,
        <?= htmlspecialchars($ciudad['pais']) ?>
    </h1>

    <?php if (!$resultado['ok']): ?>
        <p class="error"><?= htmlspecialchars($resultado['error']) ?></p>
    <?php else: ?>
        <div class="lecturas">
            <div class="lectura">
                <p class="lectura-etiqueta">Temperatura</p>
                <p class="lectura-valor"><?= htmlspecialchars((string)$temperatura) ?><span class="unidad">°C</span></p>
            </div>
            <div class="lectura">
                <p class="lectura-etiqueta">Viento</p>
                <p class="lectura-valor"><?= htmlspecialchars((string)$viento) ?><span class="unidad">km/h</span></p>
            </div>
            <div class="lectura lectura-brujula">
                <p class="lectura-etiqueta">Dirección del viento</p>
                <div class="brujula" role="img" aria-label="Dirección del viento: <?= htmlspecialchars((string)$direccionViento) ?> grados">
                    <div class="brujula-aguja" style="--rumbo: <?= (int)$direccionViento ?>deg"></div>
                </div>
                <p class="brujula-grados"><?= htmlspecialchars((string)$direccionViento) ?>°</p>
            </div>
        </div>
        <p class="fuente">Datos: Open-Meteo · <?= htmlspecialchars((string)($resultado['datos']['current']['time'] ?? '')) ?></p>
    <?php endif; ?>
</main>
</body>
</html>
