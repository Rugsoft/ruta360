<?php
// servicios/meteorologia.php · Cliente meteorológico con timeouts, caché y resiliencia (Manual 11)
declare(strict_types=1);

/**
 * 11.7 Excepción propia para fallos de servicios externos
 */
final class ServicioExternoException extends RuntimeException
{
    public function __construct(
        public readonly string $categoria,
        string $mensaje,
        public readonly int $codigoHttp = 0,
        public readonly int $codigoCurl = 0
    ) {
        parent::__construct($mensaje);
    }
}

/**
 * Construye la URL de consulta hacia Open-Meteo
 */
function construirUrlMeteo(float $latitud, float $longitud): string
{
    $base = 'https://api.open-meteo.com/v1/forecast';
    return $base . '?' . http_build_query([
        'latitude' => $latitud,
        'longitude' => $longitud,
        'current' => 'temperature_2m,weather_code,wind_speed_10m,wind_direction_10m',
        'timezone' => 'auto'
    ]);
}

/**
 * 11.9 Ejecutar la petición meteorológica con timeouts y liberación garantizada en finally
 */
function solicitarTiempo(string $url): array
{
    $ch = null;
    try {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ServicioExternoException(
                'inicio',
                'No se ha podido iniciar cURL.'
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $cuerpo = curl_exec($ch);
        $codigoCurl = curl_errno($ch);
        $errorCurl = curl_error($ch);
        $info = curl_getinfo($ch);

        if ($cuerpo === false || $codigoCurl !== 0) {
            throw new ServicioExternoException(
                'transporte',
                'Fallo de comunicación: ' . $errorCurl,
                0,
                $codigoCurl
            );
        }

        return validarRespuestaTiempo((string) $cuerpo, $info);
    } finally {
        $ch = null;
    }
}

/**
 * 11.10 Validar el código HTTP y formato JSON
 */
function validarRespuestaTiempo(string $cuerpo, array $info): array
{
    $codigo = (int) ($info['http_code'] ?? 0);
    if ($codigo < 200 || $codigo >= 300) {
        throw new ServicioExternoException(
            'http',
            'El proveedor ha respondido con HTTP ' . $codigo,
            $codigo
        );
    }

    try {
        $datos = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ServicioExternoException(
            'json',
            'El proveedor no ha enviado JSON válido.'
        );
    }

    if (!is_array($datos)) {
        throw new ServicioExternoException(
            'json',
            'El cuerpo decodificado no es un array válido.'
        );
    }

    return normalizarTiempo($datos, $info);
}

/**
 * 11.11 Validar y normalizar los datos meteorológicos
 */
function normalizarTiempo(array $datos, array $info): array
{
    $actual = $datos['current'] ?? null;
    if (!is_array($actual) || !isset($actual['temperature_2m'])) {
        throw new ServicioExternoException(
            'contenido',
            'Faltan datos meteorológicos obligatorios.'
        );
    }

    return [
        'temperatura' => (float) $actual['temperature_2m'],
        'codigo_tiempo' => (int) ($actual['weather_code'] ?? 0),
        'viento' => isset($actual['wind_speed_10m']) ? (float) $actual['wind_speed_10m'] : null,
        'direccion_viento' => isset($actual['wind_direction_10m']) ? (int) $actual['wind_direction_10m'] : null,
        'obtenido_en' => date(DATE_ATOM),
        'tiempo_conexion' => (float) ($info['connect_time'] ?? 0),
        'tiempo_total' => (float) ($info['total_time'] ?? 0)
    ];
}

/**
 * 11.14 Detectar un fallo transitorio para reintentar
 */
function esTransitorio(ServicioExternoException $e): bool
{
    if ($e->categoria === 'transporte') {
        return in_array($e->codigoCurl, [
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_OPERATION_TIMEDOUT
        ], true);
    }

    return $e->categoria === 'http' &&
        in_array($e->codigoHttp, [429, 502, 503, 504], true);
}

/**
 * 11.15 Reintento limitado (máximo 2 intentos con pausa de 250ms)
 */
function solicitarConReintento(string $url, int $maxIntentos = 2): array
{
    for ($intento = 1; $intento <= $maxIntentos; $intento++) {
        try {
            return solicitarTiempo($url);
        } catch (ServicioExternoException $e) {
            if ($intento === $maxIntentos || !esTransitorio($e)) {
                throw $e;
            }
            usleep(250000); // 250 milisegundos
        }
    }
    throw new LogicException('Flujo de reintentos inesperado.');
}

/**
 * 11.17 Diseñar la ruta del archivo de caché por coordenadas
 */
function rutaCache(float $latitud, float $longitud): string
{
    $clave = number_format($latitud, 2, '.', '') . '_' .
        number_format($longitud, 2, '.', '');
    $clave = str_replace(['-', '.'], ['m', '_'], $clave);
    return __DIR__ . '/../storage/cache/meteo_' . $clave . '.json';
}

/**
 * 11.18 Guardar la caché de forma atómica y segura
 */
function guardarCache(string $archivo, array $datos): void
{
    $dir = dirname($archivo);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $json = json_encode(
        ['guardado_en' => time(), 'datos' => $datos],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $temporal = $archivo . '.tmp';
    if (file_put_contents($temporal, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se ha podido escribir la caché.');
    }

    if (!rename($temporal, $archivo)) {
        throw new RuntimeException('No se ha podido publicar la caché.');
    }
}

/**
 * 11.19 Leer la caché y liberar el archivo con fopen y finally
 */
function leerCache(string $archivo): ?array
{
    if (!is_file($archivo)) {
        return null;
    }

    $fh = fopen($archivo, 'rb');
    if ($fh === false) {
        return null;
    }

    try {
        $contenido = stream_get_contents($fh);
        if ($contenido === false) {
            return null;
        }
        $cache = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        return is_array($cache) ? $cache : null;
    } catch (JsonException) {
        error_log('[cache] JSON no válido: ' . $archivo);
        return null;
    } finally {
        fclose($fh);
    }
}

/**
 * 11.20 Clasificar la antigüedad de la caché
 */
function clasificarCache(?array $cache): string
{
    if ($cache === null || !isset($cache['guardado_en'], $cache['datos'])) {
        return 'ausente';
    }

    $edad = time() - (int) $cache['guardado_en'];
    if ($edad <= 900) { // 15 minutos
        return 'reciente';
    }
    if ($edad <= 21600) { // 6 horas
        return 'antigua';
    }
    return 'caducada';
}

/**
 * 11.21 Estrategia cache-first y respuesta degradada
 *
 * @return array{disponible: bool, origen: string, datos: array|null}
 */
function obtenerTiempoResiliente(float $lat, float $lon): array
{
    $archivo = rutaCache($lat, $lon);
    $cache = leerCache($archivo);
    $estado = clasificarCache($cache);

    if ($estado === 'reciente') {
        return [
            'disponible' => true,
            'origen' => 'cache_reciente',
            'datos' => $cache['datos']
        ];
    }

    try {
        $url = construirUrlMeteo($lat, $lon);
        $datos = solicitarConReintento($url);
        guardarCache($archivo, $datos);
        return [
            'disponible' => true,
            'origen' => 'servicio',
            'datos' => $datos
        ];
    } catch (Throwable $e) {
        error_log('[meteo] ' . $e->getMessage());
        if ($estado === 'antigua') {
            return [
                'disponible' => true,
                'origen' => 'cache_antigua',
                'datos' => $cache['datos']
            ];
        }
        return [
            'disponible' => false,
            'origen' => 'sin_datos',
            'datos' => null
        ];
    }
}

/**
 * Adaptador de compatibilidad para clientes existentes
 */
function obtenerTiempoActual(float $latitud, float $longitud): array
{
    $res = obtenerTiempoResiliente($latitud, $longitud);
    if ($res['disponible']) {
        return [
            'ok' => true,
            'origen' => $res['origen'],
            'datos' => [
                'current' => [
                    'temperature_2m' => $res['datos']['temperatura'],
                    'weather_code' => $res['datos']['codigo_tiempo'],
                    'wind_speed_10m' => $res['datos']['viento'] ?? null,
                    'wind_direction_10m' => $res['datos']['direccion_viento'] ?? null,
                    'time' => $res['datos']['obtenido_en']
                ]
            ],
            'error' => null
        ];
    }

    return [
        'ok' => false,
        'origen' => $res['origen'],
        'datos' => null,
        'error' => 'La información meteorológica no está disponible temporalmente.'
    ];
}
