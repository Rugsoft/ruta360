<?php
declare(strict_types=1);

require_once __DIR__ . '/servicios/meteorologia.php';
require_once __DIR__ . '/conexion.php';

echo "====================================================\n";
echo "PRUEBAS DE RESILIENCIA Y CACHÉ METEOROLÓGICA (Ruta360)\n";
echo "====================================================\n\n";

$total = 0;
$errores = 0;

function probar(string $descripcion, bool $condicion, string $detalle = ''): void
{
    global $errores, $total;
    $total++;
    if ($condicion) {
        echo " [OK] $descripcion\n";
    } else {
        $errores++;
        echo " [FALLO] $descripcion - Detalle: $detalle\n";
    }
}

// 1. Clasificación de errores transitorios vs no transitorios
$ex503 = new ServicioExternoException('http', 'Service Unavailable', 503);
$ex401 = new ServicioExternoException('http', 'Unauthorized', 401);
$exTimeout = new ServicioExternoException('transporte', 'Timeout', 0, CURLE_OPERATION_TIMEDOUT);
$exJson = new ServicioExternoException('json', 'JSON Invalido');

probar("1. Identificación de HTTP 503 como error transitorio", esTransitorio($ex503) === true);
probar("2. Identificación de HTTP 401 como error definitivo (no transitorio)", esTransitorio($ex401) === false);
probar("3. Identificación de timeout cURL como error transitorio", esTransitorio($exTimeout) === true);
probar("4. Identificación de JSON inválido como no transitorio para reintento de red", esTransitorio($exJson) === false);

// 2. Comprobación de generación de ruta de caché segura
$latTest = 41.3874;
$lonTest = 2.1686;
$archivoCache = rutaCache($latTest, $lonTest);
probar("5. Nombre seguro y normalizado para archivo de caché", str_contains($archivoCache, 'meteo_41_39_2_17.json'), "Ruta: $archivoCache");

// Limpiar caché previa de prueba si existe
if (file_exists($archivoCache)) {
    unlink($archivoCache);
}

// 3. Consulta de servicio real / inicial -> guarda en caché y devuelve origen 'servicio' o 'cache_reciente'
$res1 = obtenerTiempoResiliente($latTest, $lonTest);
probar("6. Petición meteorológica inicial disponible", $res1['disponible'] === true && isset($res1['datos']['temperatura']), "Resultado: " . json_encode($res1));
probar("7. Archivo de caché creado en disco", file_exists($archivoCache));

// 4. Consulta subsiguiente -> debe usar 'cache_reciente'
$res2 = obtenerTiempoResiliente($latTest, $lonTest);
probar("8. Estrategia Cache-First: segunda llamada usa 'cache_reciente'", $res2['origen'] === 'cache_reciente', "Origen: {$res2['origen']}");

// 5. Simulación de caché antigua (< 6 horas, ej: 2 horas atrás = 7200s)
$datosSimulados = [
    'guardado_en' => time() - 7200,
    'datos' => [
        'temperatura' => 18.5,
        'codigo_tiempo' => 1,
        'viento' => 12.0,
        'direccion_viento' => 180,
        'obtenido_en' => date(DATE_ATOM, time() - 7200),
        'tiempo_conexion' => 0.05,
        'tiempo_total' => 0.15
    ]
];
file_put_contents($archivoCache, json_encode($datosSimulados));
$estadoAntiguo = clasificarCache(leerCache($archivoCache));
probar("9. Clasificación de caché de 2 horas como 'antigua'", $estadoAntiguo === 'antigua', "Estado: $estadoAntiguo");

// 6. Simulación de caché caducada (> 6 horas, ej: 8 horas atrás = 28800s)
$datosCaducados = [
    'guardado_en' => time() - 28800,
    'datos' => $datosSimulados['datos']
];
file_put_contents($archivoCache, json_encode($datosCaducados));
$estadoCaducado = clasificarCache(leerCache($archivoCache));
probar("10. Clasificación de caché de 8 horas como 'caducada'", $estadoCaducado === 'caducada', "Estado: $estadoCaducado");

// 7. Liberación garantizada de archivos con fopen y finally (Manual 11.33)
$archivoTempLog = __DIR__ . '/storage/cache/test_finally.log';
$fh = fopen($archivoTempLog, 'wb');
$liberado = false;
try {
    fwrite($fh, "Línea de prueba\n");
    throw new RuntimeException("Fallo simulado para probar finally");
} catch (Throwable) {
    // Excepción esperada
} finally {
    fclose($fh);
    $liberado = true;
}
probar("11. Liberación de manejador de archivo garantizada en bloque finally", $liberado === true);
// Comprobar que podemos volver a abrir o borrar el archivo
$fh2 = fopen($archivoTempLog, 'rb');
$puedeReabrir = ($fh2 !== false);
if ($fh2) {
    fclose($fh2);
}
@unlink($archivoTempLog);
probar("12. Archivo desbloqueado correctamente tras ejecución de finally", $puedeReabrir === true);

// 8. Verificación de respuesta degradada en ver_ruta.php
// La ficha debe responder 200 y mostrar datos de la ruta aunque no haya meteorología o el servicio externo falle
$ch = curl_init('http://localhost/curso-soc-php/Ruta360/ver_ruta.php?id_ruta=1');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5
]);
$htmlRuta = (string) curl_exec($ch);
$codigoHttpRuta = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

probar("13. La página ver_ruta.php responde 200 de forma resiliente", $codigoHttpRuta === 200 && str_contains($htmlRuta, 'Barcelona modernista'), "Código: $codigoHttpRuta");

echo "\n----------------------------------------------------\n";
echo "RESULTADO: " . ($total - $errores) . " de $total pruebas superadas.\n";
if ($errores === 0) {
    echo "¡Todas las pruebas de resiliencia y caché pasaron con éxito!\n";
} else {
    echo "Hubo $errores fallos.\n";
}
echo "====================================================\n";
