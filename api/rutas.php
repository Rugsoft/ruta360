<?php
// Manual 6 · Colección de rutas con filtros opcionales.
// Recurso: api/rutas.php (opcionalmente ?id_ciudad=, ?duracion_maxima=,
// ?dificultad= y ?orden=). A diferencia del recurso individual, una
// colección sin coincidencias responde 200 con una lista vacía.

require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson(int $estado, array $contenido): never
{
    http_response_code($estado);
    echo json_encode(
        $contenido,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit;
}

// ------------------------------------------------------------------
// Validación de filtros opcionales.
// ------------------------------------------------------------------

// id_ciudad (opcional): ausente o vacío significa "sin filtro".
$idCiudadTexto = filter_input(INPUT_GET, 'id_ciudad');
$idCiudad = null;
if ($idCiudadTexto !== null && $idCiudadTexto !== '') {
    $idCiudad = filter_var(
        $idCiudadTexto,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($idCiudad === false) {
        responderJson(400, [
            'ok' => false,
            'error' => 'El filtro id_ciudad no es válido.'
        ]);
    }
}

// duracion_maxima (opcional, actividad 6.24): entero positivo.
$duracionMaximaTexto = filter_input(INPUT_GET, 'duracion_maxima');
$duracionMaxima = null;
if ($duracionMaximaTexto !== null && $duracionMaximaTexto !== '') {
    $duracionMaxima = filter_var(
        $duracionMaximaTexto,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($duracionMaxima === false) {
        responderJson(400, [
            'ok' => false,
            'error' => 'El filtro duracion_maxima no es válido.'
        ]);
    }
}

// dificultad (opcional, actividad 6.27): solo facil, media o alta.
$dificultadTexto = filter_input(INPUT_GET, 'dificultad');
$dificultad = null;
if ($dificultadTexto !== null && $dificultadTexto !== '') {
    $dificultadTexto = strtolower(trim($dificultadTexto));
    // Normalizamos el acento: aceptamos tanto "facil" como "fácil".
    $dificultadTexto = str_replace('facil', 'fácil', $dificultadTexto);
    if (!in_array($dificultadTexto, ['fácil', 'media', 'alta'], true)) {
        responderJson(400, [
            'ok' => false,
            'error' => 'El filtro dificultad no es válido.'
        ]);
    }
    $dificultad = $dificultadTexto;
}

// orden (opcional, reto 6.29): solo titulo o duracion.
// Nunca se concatena el texto recibido: cada valor permitido elige
// un fragmento ORDER BY fijo escrito por la aplicación.
$ordenTexto = filter_input(INPUT_GET, 'orden');
$ordenSql = 'c.nombre, r.titulo'; // Orden por defecto.
if ($ordenTexto !== null && $ordenTexto !== '') {
    $ordenSql = match ($ordenTexto) {
        'titulo' => 'r.titulo, c.nombre',
        'duracion' => 'r.duracion_minutos ASC, r.titulo',
        default => null,
    };
    if ($ordenSql === null) {
        responderJson(400, [
            'ok' => false,
            'error' => 'El filtro orden no es válido.'
        ]);
    }
}

// ------------------------------------------------------------------
// Consulta con SQL dinámico controlado: solo fragmentos fijos; los
// valores del usuario viajan siempre en marcadores preparados.
// ------------------------------------------------------------------

try {
    $sql = 'SELECT
        r.id_ruta,
        r.titulo,
        r.duracion_minutos,
        r.distancia_km,
        c.id_ciudad,
        c.nombre AS ciudad,
        c.pais,
        COUNT(p.id_punto) AS numero_puntos
    FROM rutas r
    INNER JOIN ciudades c
        ON c.id_ciudad = r.id_ciudad
    LEFT JOIN puntos_interes p
        ON p.id_ruta = r.id_ruta
    WHERE r.activa = 1
        AND c.activa = 1';

    $parametros = [];

    if ($idCiudad !== null) {
        $sql .= ' AND r.id_ciudad = :id_ciudad';
        $parametros['id_ciudad'] = $idCiudad;
    }

    if ($duracionMaxima !== null) {
        $sql .= ' AND r.duracion_minutos <= :duracion_maxima';
        $parametros['duracion_maxima'] = $duracionMaxima;
    }

    if ($dificultad !== null) {
        $sql .= ' AND r.dificultad = :dificultad';
        $parametros['dificultad'] = $dificultad;
    }

    $sql .= ' GROUP BY r.id_ruta, r.titulo, r.duracion_minutos,
        r.distancia_km, c.id_ciudad, c.nombre, c.pais
    ORDER BY ' . $ordenSql;

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);
    $filas = $consulta->fetchAll();

    // ------------------------------------------------------------------
    // Construcción del contrato: cada elemento es un resumen; el detalle
    // completo sigue estando en api/ruta.php?id_ruta=...
    // ------------------------------------------------------------------
    $rutas = array_map(
        static fn(array $fila): array => [
            'id_ruta' => (int) $fila['id_ruta'],
            'titulo' => $fila['titulo'],
            'duracion_minutos' => (int) $fila['duracion_minutos'],
            'distancia_km' => (float) $fila['distancia_km'],
            'ciudad' => [
                'id_ciudad' => (int) $fila['id_ciudad'],
                'nombre' => $fila['ciudad'],
                'pais' => $fila['pais']
            ],
            'numero_puntos' => (int) $fila['numero_puntos']
        ],
        $filas
    );

    // Cero coincidencias no es un error: la colección existe y la
    // petición se ha procesado correctamente.
    responderJson(200, [
        'ok' => true,
        'filtros' => [
            'id_ciudad' => $idCiudad,
            'duracion_maxima' => $duracionMaxima,
            'dificultad' => $dificultad,
            'orden' => $ordenTexto ?? null
        ],
        'total' => count($rutas),
        'datos' => $rutas
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responderJson(500, [
        'ok' => false,
        'error' => 'No se ha podido consultar la colección.'
    ]);
}
