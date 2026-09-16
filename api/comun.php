<?php
declare(strict_types=1);

function responderJson(int $codigo, array $datos): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function leerJson(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        responderJson(400, ['ok' => false, 'mensaje' => 'El cuerpo JSON está vacío.']);
    }
    try {
        $datos = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        responderJson(400, ['ok' => false, 'mensaje' => 'El JSON no es válido.']);
    }
    if (!is_array($datos)) {
        responderJson(400, ['ok' => false, 'mensaje' => 'Se esperaba un objeto JSON.']);
    }
    return $datos;
}

function obtenerIdRuta(): int
{
    $id = filter_input(INPUT_GET, 'id_ruta', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($id === false || $id === null) {
        responderJson(400, ['ok' => false, 'mensaje' => 'El identificador no es válido.']);
    }
    return $id;
}

