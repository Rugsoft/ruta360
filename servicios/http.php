<?php
declare(strict_types=1);

function solicitarJson(string $url, array $headers = [], int $connectTimeout = 2, int $timeout = 5): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'tipo' => 'entorno', 'mensaje' => 'cURL no está disponible.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ch = null;

    if ($body === false || $errno !== 0) {
        return ['ok' => false, 'tipo' => 'red', 'mensaje' => $error ?: 'Fallo de red.'];
    }
    try {
        $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return ['ok' => false, 'tipo' => 'json', 'status' => $status, 'mensaje' => 'Respuesta JSON no válida.'];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'tipo' => 'http', 'status' => $status,
            'mensaje' => (string) ($json['mensaje'] ?? 'El servicio devolvió un error.'), 'datos' => $json];
    }
    return ['ok' => true, 'status' => $status, 'datos' => $json];
}

function enviarJson(string $url, string $metodo, array $datos, string $token): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'mensaje' => 'cURL no está disponible.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_POSTFIELDS => json_encode($datos, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    $ch = null;
    if ($body === false) return ['ok' => false, 'status' => 0, 'mensaje' => $error];
    $json = json_decode($body, true);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status,
        'mensaje' => (string) ($json['mensaje'] ?? ''), 'datos' => $json];
}
