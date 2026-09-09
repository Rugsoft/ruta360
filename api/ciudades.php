<?php
// Reto 7.35 · Colección de ciudades activas.
// Publica los campos mínimos que necesita el selector de la página
// cliente: id_ciudad, nombre y pais. Las coordenadas y demás datos
// no viajan porque el selector no los necesita.

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

try {
    $consulta = $pdo->prepare(
        'SELECT id_ciudad, nombre, pais
         FROM ciudades
         WHERE activa = 1
         ORDER BY nombre'
    );
    $consulta->execute();
    $filas = $consulta->fetchAll();

    $ciudades = array_map(
        static fn(array $fila): array => [
            'id_ciudad' => (int) $fila['id_ciudad'],
            'nombre' => $fila['nombre'],
            'pais' => $fila['pais']
        ],
        $filas
    );

    // Una colección vacía es una respuesta correcta, no un error.
    responderJson(200, [
        'ok' => true,
        'total' => count($ciudades),
        'datos' => $ciudades
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responderJson(500, [
        'ok' => false,
        'error' => 'No se ha podido consultar la colección de ciudades.'
    ]);
}
