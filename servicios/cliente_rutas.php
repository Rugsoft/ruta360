<?php
// Cliente HTTP de la API propia de Ruta360.
// Las páginas no consultan MySQL: piden los datos por HTTP e
// interpretan el contrato JSON del proveedor.

// Ubicación real del proyecto en este equipo.
const API_BASE_URL = 'http://localhost/curso-soc-php/Ruta360/api';

// Token Bearer para peticiones de escritura autenticadas (Manual 10)
const API_TOKEN = 'a000000000000000000000000000000000000000000000000000000000000001';

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
    $url = API_BASE_URL . '/ruta.php' . '?' . http_build_query(['id_ruta' => $idRuta]);

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
 * Colección de rutas con filtros opcionales (api/rutas.php).
 * Los filtros admitidos son id_ciudad, duracion_maxima, dificultad y
 * orden; los vacíos o nulos no se envían a la API.
 *
 * @return array{ok: bool, estado: int, total?: int, filtros?: array, datos?: array, error?: string}
 */
function obtenerColeccionRutasApi(array $filtros = []): array
{
    $base = API_BASE_URL . '/rutas.php';
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

/**
 * Colección de ciudades activas (api/ciudades.php).
 * Alimenta el selector de rutas.php: si falla, la página debe
 * seguir funcionando sin opciones de ciudad.
 *
 * @return array{ok: bool, estado: int, total?: int, datos?: array, error?: string}
 */
function obtenerCiudades(): array
{
    $base = API_BASE_URL . '/ciudades.php';

    $respuesta = solicitarJson($base);

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
        'datos' => $contenido['datos']
    ];
}

/**
 * Crea una ruta enviando JSON por POST a la colección (Manual 8 y 10).
 *
 * @return array{ok: bool, estado: int, codigo: int, mensaje: string, datos: array, errores: array}
 */
function crearRuta(array $datos): array
{
    $url = API_BASE_URL . '/rutas.php';
    $resultado = enviarJson('POST', $url, $datos);

    return [
        'ok' => ($resultado['codigo'] ?? 0) === 201 && ($resultado['ok'] ?? false) === true,
        'estado' => $resultado['codigo'] ?? 0,
        'codigo' => $resultado['codigo'] ?? 0,
        'mensaje' => (string) ($resultado['mensaje'] ?? $resultado['error'] ?? 'Respuesta sin mensaje.'),
        'datos' => is_array($resultado['datos'] ?? null) ? $resultado['datos'] : [],
        'errores' => is_array($resultado['errores'] ?? null) ? $resultado['errores'] : []
    ];
}

// ==================================================================
// Operaciones PUT, PATCH y DELETE (Manual 9 y 10)
// ==================================================================

/**
 * 9.20 & 10.16 Un cliente cURL reutilizable para POST, PUT, PATCH y DELETE
 */
function enviarJson(
    string $metodo, string $url, ?array $datos = null
): array {
    $ch = curl_init($url);
    $opciones = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . API_TOKEN
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8
    ];
    if ($datos !== null) {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return [
                'ok' => false,
                'codigo' => 0,
                'mensaje' => 'No se han podido preparar los datos.',
                'error' => 'No se han podido preparar los datos.'
            ];
        }
        $opciones[CURLOPT_POSTFIELDS] = $json;
    }
    curl_setopt_array($ch, $opciones);
    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false || $error !== '') {
        return [
            'ok' => false,
            'codigo' => 0,
            'mensaje' => 'No se ha podido conectar con la API.',
            'error' => 'No se ha podido conectar con la API.'
        ];
    }

    $contenido = json_decode($respuesta, true);
    return is_array($contenido)
        ? $contenido + ['codigo' => $codigo]
        : [
            'ok' => false,
            'codigo' => $codigo,
            'mensaje' => 'La API ha enviado una respuesta no válida.',
            'error' => 'La API ha enviado una respuesta no válida.'
        ];
}

/**
 * 9.21 Funciones actualizarRuta, modificarRuta y eliminarRutaCliente
 */
function actualizarRuta(int $idRuta, array $datos): array
{
    $url = API_BASE_URL . '/rutas.php?id_ruta=' . $idRuta;
    return enviarJson('PUT', $url, $datos);
}

function modificarRuta(int $idRuta, array $cambios): array
{
    $url = API_BASE_URL . '/rutas.php?id_ruta=' . $idRuta;
    return enviarJson('PATCH', $url, $cambios);
}

function eliminarRutaCliente(int $idRuta): array
{
    $url = API_BASE_URL . '/rutas.php?id_ruta=' . $idRuta;
    return enviarJson('DELETE', $url);
}

/**
 * Adaptador de obtenerRutaApi con el formato de Manual 9
 */
function obtenerRuta(int $idRuta): array
{
    $res = obtenerRutaApi($idRuta);
    return [
        'ok' => $res['ok'],
        'codigo' => $res['estado'] ?? 200,
        'estado' => $res['estado'] ?? 200,
        'mensaje' => $res['error'] ?? '',
        'error' => $res['error'] ?? '',
        'datos' => $res['datos'] ?? []
    ];
}

