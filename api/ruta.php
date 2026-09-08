<?php
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

$idRuta = filter_input(
    INPUT_GET,
    'id_ruta',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idRuta === false || $idRuta === null) {
    responderJson(400, [
        'ok' => false,
        'error' => 'El identificador de la ruta no es válido.'
    ]);
}

try {
    $sqlRuta = 'SELECT
            r.id_ruta,
            r.titulo,
            r.descripcion,
            r.duracion_minutos,
            r.distancia_km,
            r.dificultad,
            c.id_ciudad,
            c.nombre AS ciudad,
            c.pais
        FROM rutas r
        INNER JOIN ciudades c
            ON c.id_ciudad = r.id_ciudad
        WHERE r.id_ruta = :id_ruta
            AND r.activa = 1
            AND c.activa = 1';

    $consultaRuta = $pdo->prepare($sqlRuta);
    $consultaRuta->execute(['id_ruta' => $idRuta]);
    $ruta = $consultaRuta->fetch();

    if (!$ruta) {
        responderJson(404, [
            'ok' => false,
            'error' => 'La ruta no existe o no está disponible.'
        ]);
    }

    $consultaPuntos = $pdo->prepare(
        'SELECT id_punto, nombre, descripcion, orden
        FROM puntos_interes
        WHERE id_ruta = :id_ruta
        ORDER BY orden'
    );
    $consultaPuntos->execute(['id_ruta' => $idRuta]);
    $puntos = $consultaPuntos->fetchAll();

    $respuesta = [
        'ok' => true,
        'datos' => [
            'id_ruta' => (int) $ruta['id_ruta'],
            'titulo' => $ruta['titulo'],
            'descripcion' => $ruta['descripcion'],
            'duracion_minutos' => (int) $ruta['duracion_minutos'],
            'distancia_km' => (float) $ruta['distancia_km'],
            'dificultad' => $ruta['dificultad'],
            'numero_puntos' => count($puntos),
            'ciudad' => [
                'id_ciudad' => (int) $ruta['id_ciudad'],
                'nombre' => $ruta['ciudad'],
                'pais' => $ruta['pais']
            ],
            'puntos_interes' => array_map(
                static fn(array $punto): array => [
                    'id_punto' => (int) $punto['id_punto'],
                    'nombre' => $punto['nombre'],
                    'descripcion' => $punto['descripcion'],
                    'orden' => (int) $punto['orden']
                ],
                $puntos
            )
        ]
    ];

    responderJson(200, $respuesta);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responderJson(500, [
        'ok' => false,
        'error' => 'No se ha podido completar la consulta.'
    ]);
}
