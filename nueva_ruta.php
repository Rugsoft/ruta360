<?php
// Formulario de creación de rutas (Manual 8).
// La página valida lo básico y delega en el cliente HTTP, que envía
// JSON a la API; la API valida de nuevo y guarda en MySQL.
// Nunca confiamos solo en el HTML: otros clientes pueden llamar
// directamente a la API.

declare(strict_types=1);

require_once __DIR__ . '/seguridad_web.php';
require_once __DIR__ . '/servicios/cliente_rutas.php';

// Manual 10.20: Exigir rol editor o admin
exigirRolWeb(['editor', 'admin']);

$valores = [
    'id_ciudad' => '',
    'titulo' => '',
    'descripcion' => '',
    'duracion_minutos' => '',
    'distancia_km' => '',
    'dificultad' => '',
];
$errores = [];
$mensaje = '';
$idRutaCreada = null;

// Ciudades para el selector: si el recurso no responde, el
// formulario sigue funcionando con el campo manual deshabilitado.
$ciudades = [];
$ciudadesApi = obtenerCiudades();
if ($ciudadesApi['ok']) {
    foreach ($ciudadesApi['datos'] as $ciudad) {
        if (isset($ciudad['id_ciudad'], $ciudad['nombre'])) {
            $ciudades[(int) $ciudad['id_ciudad']] = $ciudad['nombre'];
        }
    }
}

$dificultades = ['' => 'Sin especificar', 'facil' => 'Fácil', 'media' => 'Media', 'alta' => 'Alta'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCsrf();
    foreach ($valores as $campo => $valor) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $resultado = crearRuta($valores);

    if ($resultado['ok']) {
        $mensaje = $resultado['mensaje'];
        $idRutaCreada = (int) ($resultado['datos']['id_ruta'] ?? 0);
        $valores = array_fill_keys(array_keys($valores), '');
    } else {
        $mensaje = $resultado['mensaje'];
        $errores = $resultado['errores'];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva ruta | Ruta360</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel panel-amplio">
    <header class="panel-cabecera">
        <a class="volver" href="rutas.php">← Volver al listado</a>
        <p class="panel-rol">Alta de rutas</p>
    </header>

    <h1>Nueva ruta</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="aviso<?= $idRutaCreada === null ? ' aviso-error' : '' ?>">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($idRutaCreada !== null): ?>
        <p class="enlace-creada">
            <a href="ver_ruta.php?id_ruta=<?= $idRutaCreada ?>">Ver la ruta creada →</a>
        </p>
    <?php endif; ?>

    <form method="post" action="nueva_ruta.php" class="formulario">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">
        <label for="r-ciudad">Ciudad</label>
        <select id="r-ciudad" name="id_ciudad" required>
            <option value="">Selecciona una ciudad</option>
            <?php foreach ($ciudades as $id => $nombre): ?>
                <option value="<?= $id ?>"<?= (string) $id === $valores['id_ciudad'] ? ' selected' : '' ?>>
                    <?= htmlspecialchars($nombre) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small><?= htmlspecialchars($errores['id_ciudad'] ?? '') ?></small>

        <label for="r-titulo">Título</label>
        <input
            id="r-titulo"
            name="titulo"
            type="text"
            minlength="5"
            maxlength="120"
            required
            value="<?= htmlspecialchars($valores['titulo']) ?>">
        <small><?= htmlspecialchars($errores['titulo'] ?? '') ?></small>

        <label for="r-descripcion">Descripción</label>
        <textarea
            id="r-descripcion"
            name="descripcion"
            minlength="10"
            maxlength="1000"
            required><?= htmlspecialchars($valores['descripcion']) ?></textarea>
        <small><?= htmlspecialchars($errores['descripcion'] ?? '') ?></small>

        <div class="campos-pareja">
            <div>
                <label for="r-duracion">Duración (minutos)</label>
                <input
                    id="r-duracion"
                    name="duracion_minutos"
                    type="number"
                    min="15"
                    max="1440"
                    required
                    value="<?= htmlspecialchars($valores['duracion_minutos']) ?>">
                <small><?= htmlspecialchars($errores['duracion_minutos'] ?? '') ?></small>
            </div>
            <div>
                <label for="r-distancia">Distancia (km)</label>
                <input
                    id="r-distancia"
                    name="distancia_km"
                    type="number"
                    min="0.1"
                    max="1000"
                    step="0.1"
                    required
                    value="<?= htmlspecialchars($valores['distancia_km']) ?>">
                <small><?= htmlspecialchars($errores['distancia_km'] ?? '') ?></small>
            </div>
        </div>

        <label for="r-dificultad">Dificultad</label>
        <select id="r-dificultad" name="dificultad">
            <?php foreach ($dificultades as $valor => $texto): ?>
                <option value="<?= $valor ?>"<?= $valores['dificultad'] === $valor ? ' selected' : '' ?>>
                    <?= $texto ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small><?= htmlspecialchars($errores['dificultad'] ?? '') ?></small>

        <button type="submit">Crear ruta</button>
    </form>
</main>
</body>
</html>
