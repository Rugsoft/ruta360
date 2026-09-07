<?php
function obtenerTiempoActual(float $latitud, float $longitud): array
{
    $base = 'https://api.open-meteo.com/v1/forecast';
    $parametros = http_build_query([
        'latitude' => $latitud,
        'longitude' => $longitud,
        'current' => 'temperature_2m,wind_speed_10m,wind_direction_10m',
        'timezone' => 'Europe/Berlin'
    ]);

    $curl = curl_init($base . '?' . $parametros);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10
    ]);

    $respuesta = curl_exec($curl);
    $errorCurl = curl_error($curl);
    $codigoHttp = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($respuesta === false) {
        return ['ok' => false, 'datos' => null,
            'error' => 'No se pudo conectar: ' . $errorCurl];
    }

    if ($codigoHttp !== 200) {
        return ['ok' => false, 'datos' => null,
            'error' => 'La API respondió con HTTP ' . $codigoHttp];
    }

    $datos = json_decode($respuesta, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['ok' => false, 'datos' => null,
            'error' => 'La respuesta no es un JSON válido.'];
    }

    return ['ok' => true, 'datos' => $datos, 'error' => null];
}
?>
