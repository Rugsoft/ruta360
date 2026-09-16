<?php
declare(strict_types=1);

const PERMISOS_ROL = [
    'lector' => [],
    'editor' => ['crear_ruta', 'editar_ruta'],
    'admin' => ['crear_ruta', 'editar_ruta', 'eliminar_ruta'],
];

function cabeceraAuthorization(): string
{
    $valor = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($valor === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $valor = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }
    return trim($valor);
}

function autenticarToken(PDO $pdo): array
{
    $cabecera = cabeceraAuthorization();
    if (!preg_match('/^Bearer\s+([A-Za-z0-9._-]{20,200})$/', $cabecera, $m)) {
        header('WWW-Authenticate: Bearer');
        responderJson(401, ['ok' => false, 'mensaje' => 'Se necesita un token Bearer válido.']);
    }
    $hash = hash('sha256', $m[1]);
    $sql = 'SELECT t.id_token, u.id_usuario, u.nombre, u.email, u.rol
            FROM api_tokens t
            INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
            WHERE t.token_hash = :hash
              AND t.revocado_en IS NULL
              AND (t.expira_en IS NULL OR t.expira_en > NOW())
              AND u.activo = 1
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['hash' => $hash]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        header('WWW-Authenticate: Bearer');
        responderJson(401, ['ok' => false, 'mensaje' => 'El token no es válido o ha caducado.']);
    }
    $pdo->prepare('UPDATE api_tokens SET ultimo_uso = NOW() WHERE id_token = :id')
        ->execute(['id' => $usuario['id_token']]);
    return $usuario;
}

function exigirPermiso(PDO $pdo, string $permiso): array
{
    $usuario = autenticarToken($pdo);
    if (!in_array($permiso, PERMISOS_ROL[$usuario['rol']] ?? [], true)) {
        responderJson(403, ['ok' => false, 'mensaje' => 'No tienes permiso para esta operación.']);
    }
    return $usuario;
}

