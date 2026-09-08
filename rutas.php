<?php
require_once __DIR__ . '/conexion.php';

$consulta = $pdo->query(
    'SELECT id_ruta, titulo
     FROM rutas
     WHERE activa = 1
     ORDER BY id_ruta'
);

$rutas = $consulta->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rutas | Ruta360</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel">
    <header class="panel-cabecera">
        <a class="volver" href="index.php">← Elegir otra ciudad</a>
        <p class="panel-rol">Rutas recomendadas</p>
    </header>

    <h1>Rutas de Ruta360</h1>

    <ul class="lista-rutas">
        <?php foreach ($rutas as $ruta): ?>
            <li>
                <a href="ver_ruta.php?id_ruta=<?= (int) $ruta['id_ruta'] ?>">
                    <?= htmlspecialchars($ruta['titulo']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <p class="fuente">Cada enlace abre el detalle obtenido desde la API de Ruta360.</p>
</main>
</body>
</html>
