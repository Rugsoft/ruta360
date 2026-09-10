<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad_web.php';
require_once __DIR__ . '/servicios/cliente_rutas.php';

// Manual 10.20: Exigir rol editor o admin
exigirRolWeb(['editor', 'admin']);

$idRuta = filter_input(INPUT_GET, 'id_ruta', FILTER_VALIDATE_INT);
if ($idRuta === false || $idRuta === null || $idRuta < 1) {
    http_response_code(400);
    exit('Identificador de ruta no válido.');
}

$resultadoConsulta = obtenerRuta($idRuta);
if (!($resultadoConsulta['ok'] ?? false)) {
    http_response_code($resultadoConsulta['codigo'] ?? 500);
    exit(htmlspecialchars($resultadoConsulta['mensaje'] ?? 'No se ha podido cargar la ruta.'));
}

$valores = $resultadoConsulta['datos'];
if (isset($valores['ciudad']['id_ciudad']) && !isset($valores['id_ciudad'])) {
    $valores['id_ciudad'] = (string) $valores['ciudad']['id_ciudad'];
}

$errores = [];
$mensaje = '';

// Ciudades para el selector
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $campos = [
        'id_ciudad', 'titulo', 'descripcion',
        'duracion_minutos', 'distancia_km', 'dificultad'
    ];
    foreach ($campos as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $resultado = actualizarRuta($idRuta, $valores);

    if ($resultado['ok'] ?? false) {
        header('Location: ver_ruta.php?id_ruta=' . $idRuta . '&actualizada=1');
        exit;
    }

    $mensaje = $resultado['mensaje'] ?? 'No se ha podido actualizar.';
    $errores = $resultado['errores'] ?? [];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar ruta | Ruta360</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel panel-amplio">
    <header class="panel-cabecera">
        <a class="volver" href="ver_ruta.php?id_ruta=<?= $idRuta ?>">← Volver a la ruta</a>
        <p class="panel-rol">Edición de ruta</p>
    </header>

    <h1>Editar ruta</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="aviso aviso-error">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="formulario">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">
        <label for="r-ciudad">Ciudad</label>
        <select id="r-ciudad" name="id_ciudad" required>
            <option value="">Selecciona una ciudad</option>
            <?php foreach ($ciudades as $id => $nombre): ?>
                <option value="<?= $id ?>"<?= (string) $id === (string) ($valores['id_ciudad'] ?? '') ? ' selected' : '' ?>>
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
            value="<?= htmlspecialchars((string) ($valores['titulo'] ?? '')) ?>">
        <small><?= htmlspecialchars($errores['titulo'] ?? '') ?></small>

        <label for="r-descripcion">Descripción</label>
        <textarea
            id="r-descripcion"
            name="descripcion"
            minlength="10"
            maxlength="1000"
            required><?= htmlspecialchars((string) ($valores['descripcion'] ?? '')) ?></textarea>
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
                    value="<?= htmlspecialchars((string) ($valores['duracion_minutos'] ?? '')) ?>">
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
                    value="<?= htmlspecialchars((string) ($valores['distancia_km'] ?? '')) ?>">
                <small><?= htmlspecialchars($errores['distancia_km'] ?? '') ?></small>
            </div>
        </div>

        <label for="r-dificultad">Dificultad</label>
        <select id="r-dificultad" name="dificultad">
            <?php foreach ($dificultades as $valor => $texto): ?>
                <option value="<?= $valor ?>"<?= ($valores['dificultad'] ?? '') === $valor ? ' selected' : '' ?>>
                    <?= $texto ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small><?= htmlspecialchars($errores['dificultad'] ?? '') ?></small>

        <button type="submit">Guardar cambios</button>
    </form>
</main>
</body>
</html>
