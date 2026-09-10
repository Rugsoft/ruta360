<?php
declare(strict_types=1);

require_once __DIR__ . '/seguridad_web.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/servicios/cliente_rutas.php';

$baseUrl = 'http://localhost/curso-soc-php/Ruta360/api/rutas.php';

$tokenAdmin = 'a000000000000000000000000000000000000000000000000000000000000001';
$tokenEditor = 'e000000000000000000000000000000000000000000000000000000000000002';
$tokenLector = 'c000000000000000000000000000000000000000000000000000000000000003';
$tokenCaducado = 'f000000000000000000000000000000000000000000000000000000000000004';
$tokenRevocado = 'd000000000000000000000000000000000000000000000000000000000000005';
$tokenInventado = '1111111111111111111111111111111111111111111111111111111111111111';

function ejecutarPeticion(string $metodo, string $url, ?string $token = null, ?array $datos = null): array
{
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $opciones = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HEADER => true,
    ];

    if ($datos !== null) {
        $opciones[CURLOPT_POSTFIELDS] = json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $opciones);
    $respuesta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr((string)$respuesta, 0, $headerSize);
    $body = substr((string)$respuesta, $headerSize);
    curl_close($ch);

    $json = json_decode($body, true);
    return [
        'codigo' => $codigo,
        'headers' => $rawHeaders,
        'datos' => is_array($json) ? $json : [],
        'raw_body' => $body
    ];
}

$errores = 0;
$total = 0;

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

echo "====================================================\n";
echo "PRUEBAS DE SEGURIDAD Y PERMISOS RBAC (Ruta360)\n";
echo "====================================================\n\n";

// 1. GET sin token -> 200
$r = ejecutarPeticion('GET', $baseUrl);
probar("1. GET público responde 200 sin token", $r['codigo'] === 200 && ($r['datos']['ok'] ?? false) === true, "Código: {$r['codigo']}");

// 2. POST sin token -> 401 y WWW-Authenticate: Bearer
$cuerpoRuta = [
    'id_ciudad' => 1,
    'titulo' => 'Ruta Prueba Seguridad ' . time(),
    'descripcion' => 'Descripción suficientemente larga para pasar la validación.',
    'duracion_minutos' => 90,
    'distancia_km' => 3.5,
    'dificultad' => 'media'
];
$r = ejecutarPeticion('POST', $baseUrl, null, $cuerpoRuta);
probar("2. POST sin Authorization responde 401", $r['codigo'] === 401 && stripos($r['headers'], 'WWW-Authenticate: Bearer') !== false, "Código: {$r['codigo']}");

// 3. POST con token inventado -> 401
$r = ejecutarPeticion('POST', $baseUrl, $tokenInventado, $cuerpoRuta);
probar("3. POST con token inventado responde 401", $r['codigo'] === 401, "Código: {$r['codigo']}");

// 4. POST con token caducado -> 401
$r = ejecutarPeticion('POST', $baseUrl, $tokenCaducado, $cuerpoRuta);
probar("4. POST con token caducado responde 401", $r['codigo'] === 401, "Código: {$r['codigo']}");

// 5. POST con token revocado -> 401
$r = ejecutarPeticion('POST', $baseUrl, $tokenRevocado, $cuerpoRuta);
probar("5. POST con token revocado responde 401", $r['codigo'] === 401, "Código: {$r['codigo']}");

// 6. POST con rol lector -> 403
$r = ejecutarPeticion('POST', $baseUrl, $tokenLector, $cuerpoRuta);
probar("6. POST con token lector responde 403", $r['codigo'] === 403, "Código: {$r['codigo']}");

// 7. POST con rol editor -> 201
$r = ejecutarPeticion('POST', $baseUrl, $tokenEditor, $cuerpoRuta);
$idRutaCreada = (int) ($r['datos']['datos']['id_ruta'] ?? 0);
probar("7. POST con token editor responde 201 y crea ruta", $r['codigo'] === 201 && $idRutaCreada > 0, "Código: {$r['codigo']}, id: $idRutaCreada");

// 8. PUT con rol editor -> 200
if ($idRutaCreada > 0) {
    $cuerpoPut = [
        'id_ciudad' => 1,
        'titulo' => 'Ruta Editada por Editor',
        'descripcion' => 'Descripción actualizada mediante PUT completo.',
        'duracion_minutos' => 100,
        'distancia_km' => 4.0,
        'dificultad' => 'alta'
    ];
    $r = ejecutarPeticion('PUT', $baseUrl . '?id_ruta=' . $idRutaCreada, $tokenEditor, $cuerpoPut);
    probar("8. PUT con token editor responde 200", $r['codigo'] === 200 && ($r['datos']['ok'] ?? false) === true, "Código: {$r['codigo']}");

    // 9. PATCH con rol editor -> 200
    $cuerpoPatch = ['duracion_minutos' => 110];
    $r = ejecutarPeticion('PATCH', $baseUrl . '?id_ruta=' . $idRutaCreada, $tokenEditor, $cuerpoPatch);
    probar("9. PATCH con token editor responde 200", $r['codigo'] === 200 && ($r['datos']['ok'] ?? false) === true, "Código: {$r['codigo']}");

    // 10. DELETE con rol editor -> 403
    $r = ejecutarPeticion('DELETE', $baseUrl . '?id_ruta=' . $idRutaCreada, $tokenEditor);
    probar("10. DELETE con token editor responde 403", $r['codigo'] === 403, "Código: {$r['codigo']}");

    // 11. DELETE con rol admin -> 200
    $r = ejecutarPeticion('DELETE', $baseUrl . '?id_ruta=' . $idRutaCreada, $tokenAdmin);
    probar("11. DELETE con token admin responde 200", $r['codigo'] === 200 && ($r['datos']['ok'] ?? false) === true, "Código: {$r['codigo']}");
}

// 12. Comprobar que ultimo_uso se actualizó en la BD
$stmt = $pdo->prepare('SELECT ultimo_uso FROM api_tokens WHERE token_hash = :hash');
$stmt->execute(['hash' => hash('sha256', $tokenAdmin)]);
$ultimoUso = $stmt->fetchColumn();
probar("12. Campo ultimo_uso registrado en la BD", !empty($ultimoUso), "ultimo_uso: " . var_export($ultimoUso, true));

// 13. Verificación de lógica CSRF y Seguridad Web
$token1 = tokenCsrf();
$token2 = tokenCsrf();
probar("13. Token CSRF generado es persistente en sesión", $token1 !== '' && $token1 === $token2, "Token: $token1");

// 14. Acceso a nueva_ruta.php sin sesión -> 302 Redirección a login.php
$ch = curl_init('http://localhost/curso-soc-php/Ruta360/nueva_ruta.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 5
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr((string)$resp, 0, $headerSize);
curl_close($ch);
probar("14. Acceso web a nueva_ruta.php sin sesión redirige a login.php", $code === 302 && stripos($headers, 'Location: login.php') !== false, "Código: $code");

// 15. Acceso a editar_ruta.php sin sesión -> 302 Redirección a login.php
$ch = curl_init('http://localhost/curso-soc-php/Ruta360/editar_ruta.php?id_ruta=1');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 5
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr((string)$resp, 0, $headerSize);
curl_close($ch);
probar("15. Acceso web a editar_ruta.php sin sesión redirige a login.php", $code === 302 && stripos($headers, 'Location: login.php') !== false, "Código: $code");

// 16. Login web con credenciales válidas
$cookieFile = tempnam(sys_get_temp_dir(), 'cookie_');
// Primero GET login.php para obtener token CSRF y cookie de sesión
$ch = curl_init('http://localhost/curso-soc-php/Ruta360/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_TIMEOUT => 5
]);
$htmlLogin = (string) curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', $htmlLogin, $matches);
$csrfExtraido = $matches[1] ?? '';

$ch = curl_init('http://localhost/curso-soc-php/Ruta360/login.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'csrf_token' => $csrfExtraido,
        'email' => 'admin@ruta360.local',
        'password' => 'AdminPassword123!'
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 5
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr((string)$resp, 0, $headerSize);
curl_close($ch);

probar("16. Login web con credenciales correctas redirige a rutas.php", $code === 302 && stripos($headers, 'Location: rutas.php') !== false, "Código: $code");

// 17. Acceso a nueva_ruta.php CON la sesión de admin iniciada -> 200 OK
$ch = curl_init('http://localhost/curso-soc-php/Ruta360/nueva_ruta.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_TIMEOUT => 5
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($cookieFile);

probar("17. Acceso a nueva_ruta.php con sesión autenticada responde 200", $code === 200, "Código: $code");

echo "\n----------------------------------------------------\n";
echo "RESULTADO: " . ($total - $errores) . " de $total pruebas superadas.\n";
if ($errores === 0) {
    echo "¡Todas las pruebas de seguridad del Manual 10 pasaron con éxito!\n";
} else {
    echo "Hubo $errores fallos.\n";
}
echo "====================================================\n";
