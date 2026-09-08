<?php
function obtenerRutaApi(int $idRuta): array
{
    // La URL base apunta a la ubicación real del proyecto en este equipo.
    $base = 'http://localhost/curso-soc-php/Ruta360/api/ruta.php';
    $url = $base . '?' . http_build_query(['id_ruta' => $idRuta]);

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
            'error' => 'No se ha podido contactar con el servicio.'
        ];
    }

    // 2. El cuerpo debe ser JSON válido.
    $contenido = json_decode($cuerpo, true);
    if (!is_array($contenido)) {
        return [
            'ok' => false,
            'estado' => $estadoHttp,
            'error' => 'El servicio ha devuelto una respuesta no válida.'
        ];
    }

    // 3. Contrato: estado 200 y campo ok verdadero.
    if ($estadoHttp !== 200 || ($contenido['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'estado' => $estadoHttp,
            'error' => $contenido['error']
                ?? 'El servicio no ha podido completar la petición.'
        ];
    }

    // 4. Contrato: los datos deben existir y ser un array.
    if (!isset($contenido['datos']) || !is_array($contenido['datos'])) {
        return [
            'ok' => false,
            'estado' => $estadoHttp,
            'error' => 'La respuesta no contiene los datos esperados.'
        ];
    }

    return [
        'ok' => true,
        'estado' => $estadoHttp,
        'datos' => $contenido['datos']
    ];
}
