<?php
require_once __DIR__ . '/conexion.php';

$consulta = $pdo->query(
    'SELECT id_ciudad, nombre, pais
     FROM ciudades
     WHERE activa = 1
     ORDER BY nombre'
);

$ciudades = $consulta->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ruta360</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel">
    <header class="panel-cabecera">
        <p class="marca"><span class="marca-ruta">RUTA</span><span class="marca-360">360</span></p>
        <p class="panel-rol">Consulta de condiciones actuales</p>
    </header>

    <h1>Consulta el tiempo de un destino</h1>

    <form action="tiempo.php" method="get">
        <label for="id_ciudad">Ciudad</label>
        <select name="id_ciudad" id="id_ciudad" required>
            <option value="">Selecciona una ciudad</option>
            <?php foreach ($ciudades as $ciudad): ?>
                <option value="<?= (int)$ciudad['id_ciudad'] ?>">
                    <?= htmlspecialchars($ciudad['nombre']) ?>
                    (<?= htmlspecialchars($ciudad['pais']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Consultar tiempo</button>
    </form>
</main>
</body>
</html>
