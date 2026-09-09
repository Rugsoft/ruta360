<?php
// Listado de rutas · Consumidor del recurso colección de la API.
// La página no consulta las tablas: pide la colección a la API y
// presenta el resultado. El filtro se envía como parámetros GET.

require_once __DIR__ . '/servicios/cliente_rutas.php';

// ------------------------------------------------------------------
// Leemos los filtros del usuario (todas las validaciones de formato
// las repite la API; aquí preparamos la petición).
// ------------------------------------------------------------------

$idCiudad = filter_input(
    INPUT_GET,
    'id_ciudad',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($idCiudad === false) {
    $idCiudad = null;
}

$duracionMaxima = filter_input(
    INPUT_GET,
    'duracion_maxima',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($duracionMaxima === false) {
    $duracionMaxima = null;
}

$dificultad = filter_input(INPUT_GET, 'dificultad');
if ($dificultad !== null) {
    $dificultad = strtolower(trim($dificultad));
    $dificultad = str_replace('facil', 'fácil', $dificultad);
    if (!in_array($dificultad, ['fácil', 'media', 'alta'], true)) {
        $dificultad = null;
    }
}

$orden = filter_input(INPUT_GET, 'orden');
if (!in_array($orden, ['titulo', 'duracion'], true)) {
    $orden = null;
}

// ------------------------------------------------------------------
// Pedimos la colección a la API propia (nunca a MySQL).
// ------------------------------------------------------------------

$resultado = obtenerColeccionRutasApi([
    'id_ciudad' => $idCiudad,
    'duracion_maxima' => $duracionMaxima,
    'dificultad' => $dificultad,
    'orden' => $orden,
]);

$rutas = $resultado['datos'] ?? [];

// ------------------------------------------------------------------
// Opciones del selector de ciudades: recurso propio
// api/ciudades.php en lugar de derivarlas de la colección de rutas.
// Si no carga, la página sigue funcionando con solo "Todas".
// ------------------------------------------------------------------

$ciudades = [];
$ciudadesApi = obtenerCiudades();
if ($ciudadesApi['ok']) {
    foreach ($ciudadesApi['datos'] as $ciudad) {
        if (isset($ciudad['id_ciudad'], $ciudad['nombre'])) {
            $ciudades[(int) $ciudad['id_ciudad']] = $ciudad['nombre'];
        }
    }
}
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

    <?php if (!$resultado['ok']): ?>
        <p class="error"><?= htmlspecialchars($resultado['error']) ?></p>
    <?php else: ?>
        <form method="get" action="rutas.php" class="filtros">
            <label for="f-ciudad">Ciudad</label>
            <select id="f-ciudad" name="id_ciudad">
                <option value="">Todas las ciudades</option>
                <?php foreach ($ciudades as $id => $nombre): ?>
                    <option value="<?= $id ?>"<?= $id === $idCiudad ? ' selected' : '' ?>>
                        <?= htmlspecialchars($nombre) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="f-duracion">Duración máxima (minutos)</label>
            <input
                id="f-duracion"
                name="duracion_maxima"
                type="number"
                min="1"
                inputmode="numeric"
                placeholder="Sin límite"
                value="<?= $duracionMaxima !== null ? (int) $duracionMaxima : '' ?>">

            <label for="f-dificultad">Dificultad</label>
            <select id="f-dificultad" name="dificultad">
                <option value="">Cualquiera</option>
                <?php foreach (['fácil' => 'facil', 'media' => 'media', 'alta' => 'alta'] as $texto => $valor): ?>
                    <option value="<?= $valor ?>"<?= $dificultad === $texto ? ' selected' : '' ?>>
                        <?= $texto ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="f-orden">Ordenar por</label>
            <select id="f-orden" name="orden">
                <option value="">Ciudad y título</option>
                <option value="titulo"<?= $orden === 'titulo' ? ' selected' : '' ?>>Título</option>
                <option value="duracion"<?= $orden === 'duracion' ? ' selected' : '' ?>>Duración</option>
            </select>

            <button type="submit">Aplicar filtros</button>
            <?php if ($idCiudad !== null || $duracionMaxima !== null || $dificultad !== null || $orden !== null): ?>
                <a class="limpiar" href="rutas.php">Quitar filtros</a>
            <?php endif; ?>
        </form>

        <?php if ($rutas === []): ?>
            <p class="sin-resultados">
                No hay rutas que coincidan con los filtros aplicados.
            </p>
        <?php else: ?>
            <p class="contador-rutas">
                <?= count($rutas) ?> ruta<?= count($rutas) === 1 ? '' : 's' ?> encontrada<?= count($rutas) === 1 ? '' : 's' ?>.
            </p>
            <ul class="lista-rutas">
                <?php foreach ($rutas as $ruta): ?>
                    <li>
                        <a href="ver_ruta.php?id_ruta=<?= (int) $ruta['id_ruta'] ?>">
                            <?= htmlspecialchars($ruta['titulo']) ?>
                            <span class="meta-ruta">
                                <?= htmlspecialchars($ruta['ciudad']['nombre'] ?? '') ?>
                                · <?= (int) $ruta['duracion_minutos'] ?> min
                                · <?= (float) $ruta['distancia_km'] ?> km
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <p class="fuente">Cada enlace abre el detalle obtenido desde la API de Ruta360.</p>
</main>
</body>
</html>
