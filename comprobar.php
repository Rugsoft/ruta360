<?php
$titulo = 'Comprobar entorno';
$config = require __DIR__ . '/config/config.php';
$pruebas = [
    'PHP 8.1 o superior' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Extensión PDO MySQL' => extension_loaded('pdo_mysql'),
    'Extensión cURL' => extension_loaded('curl'),
    'Extensión JSON' => extension_loaded('json'),
    'Extensión OpenSSL' => extension_loaded('openssl'),
    'Carpeta cache escribible' => is_dir(__DIR__ . '/cache') && is_writable(__DIR__ . '/cache'),
    'Carpeta logs escribible' => is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs'),
];
$dbOk = false;
try { require __DIR__ . '/config/conexion.php'; $pdo->query('SELECT 1'); $dbOk = true; } catch (Throwable $e) { $dbError = $e->getMessage(); }
$pruebas['Conexión con MySQL y base ruta360'] = $dbOk;
require __DIR__ . '/partials/inicio.php';
?>
<h1>Comprobación del entorno</h1>
<table><thead><tr><th>Requisito</th><th>Resultado</th></tr></thead><tbody>
<?php foreach ($pruebas as $nombre => $ok): ?>
<tr><td><?= htmlspecialchars($nombre) ?></td><td><?= $ok ? 'OK' : 'FALTA' ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p>PHP detectado: <strong><?= htmlspecialchars(PHP_VERSION) ?></strong></p>
<?php if (!$dbOk): ?><p class="error">Revisa MySQL, la importación de database/ruta360.sql y config/config.local.php.</p><?php endif; ?>
<?php require __DIR__ . '/partials/fin.php'; ?>

