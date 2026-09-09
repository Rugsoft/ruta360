<?php
// Cliente HTTP de la API propia de Ruta360 (Manuales 5 y 6).
// La página no consulta MySQL: pide los datos por HTTP e interpreta
// el contrato JSON del proveedor.

/**
 * Realiza una petición GET y aplica las dos primeras capas de error:
 * transporte (cURL) y JSON válido. Devuelve siempre la misma
 * estructura para que las funciones de negocio sean sencillas.
 *
 * @return array{ok: bool, estado: int, contenido: array|null, error: string|null}
 */
function solicitarJson(string $url): array
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $cuerpo = curl_exec($curl);
    $errorCurl = curl_error($curl);
    $estadoHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    // 1. Fallo de transporte: no hubo ninguna respuesta HTTP.
    if ($cuerpo === false) {
        error_log($errorCurl);
        return [
            'ok' => false,
            'estado' => 0,
            'contenido' => null,
            'error' => 'No se ha podido contactar con el servicio.'
        ];
    }

    // 2. El cuerpo debe ser JSON válido.
    $contenido = json_decode($cuerpo, true);
    if (!is_array($contenido)) {
        return [
            'ok' => false,
            'estado' => $estadoHttp,
            'contenido' => null,
            'error' => 'El servicio ha devuelto una respuesta no válida.'
        ];
    }

    return [
        'ok' => true,
        'estado' => $estadoHttp,
        'contenido' => $contenido,
        'error' => null
    ];
}

/**
 * Detalle de una ruta (recurso individual, api/ruta.php).
 *
 * @return array{ok: bool, estado: int, datos?: array, error?: string}
 */
function obtenerRutaApi(int $idRuta): array
{
    // La URL base apunta a la ubicación real del proyecto en este equipo.
    $base = 'http://localhost/curso-soc-php/Ruta360/api/ruta.php';
    $url = $base . '?' . http_build_query(['id_ruta' => $idRuta]);

    $respuesta = solicitarJson($url);

    // Capas 1 y 2: transporte y JSON.
    if (!$respuesta['ok']) {
        return [
            'ok' => false,
            'estado' => $respuesta['estado'],
            'error' => $respuesta['error']
        ];
    }

    $contenido = $respuesta['contenido'];

    // 3. Contrato: estado 200 y campo ok verdadero.
    if ($respuesta['estado'] !== 200 || ($contenido['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'estado' => $respuesta['estado'],
            'error' => $contenido['error']
                ?? 'El servicio no ha podido completar la petición.'
        ];
    }

    // 4. Contrato: los datos deben existir y ser un array.
    if (!isset($contenido['datos']) || !is_array($contenido['datos'])) {
        return [
            'ok' => false,
            'estado' => $respuesta['estado'],
            'error' => 'La respuesta no contiene los datos esperados.'
        ];
    }

    return [
        'ok' => true,
        'estado' => $respuesta['estado'],
        'datos' => $contenido['datos']
    ];
}

/**
 * Colección de rutas con filtros opcionales (api/rutas.php, Manual 6).
 * Los filtros admitidos son id_ciudad, duracion_maxima, dificultad y
 * orden; los vacíos o nulos no se envían a la API.
 *
 * @return array{ok: bool, estado: int, total?: int, filtros?: array, datos?: array, error?: string}
 */
function obtenerColeccionRutasApi(array $filtros = []): array
{
    $base = 'http://localhost/curso-soc-php/Ruta360/api/rutas.php';
    $parametros = array_filter(
        $filtros,
        static fn($valor): bool => $valor !== null && $valor !== ''
    );
    $url = $base . ($parametros === [] ? '' : '?' . http_build_query($parametros));

    $respuesta = solicitarJson($url);

    // Capas 1 y 2: transporte y JSON.
    if (!$respuesta['ok']) {
        return [
            'ok' => false,
            'estado' => $respuesta['estado'],
            'error' => $respuesta['error']
        ];
    }

    $contenido = $respuesta['contenido'];

    // 3. Contrato: estado 200 y campo ok verdadero.
    if ($respuesta['estado'] !== 200 || ($contenido['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'estado' => $respuesta['estado'],
            'error' => $contenido['error']
                ?? 'El servicio no ha podido completar la petición.'
        ];
    }

    // 4. Contrato: total y datos deben existir (datos puede ser []).
    if (!isset($contenido['datos']) || !is_array($contenido['datos'])
        || !isset($contenido['total'])) {
        return [
            'ok' => false,
            'estado' => $respuesta['estado'],
            'error' => 'La respuesta no contiene los datos esperados.'
        ];
    }

    return [
        'ok' => true,
        'estado' => $respuesta['estado'],
        'total' => (int) $contenido['total'],
        'filtros' => is_array($contenido['filtros'] ?? null)
            ? $contenido['filtros']
            : [],
        'datos' => $contenido['datos']
    ];
}
