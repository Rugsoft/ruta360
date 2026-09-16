<?php
declare(strict_types=1);

$config = [
    'app' => [
        'name' => 'Ruta360',
        'environment' => 'desarrollo',
        'base_url' => 'http://localhost/ruta360',
        'timezone' => 'Europe/Madrid',
        'show_errors' => true,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'ruta360',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'api' => [
        'editor_token' => 'ruta360-editor-demo-2026',
        'admin_token' => 'ruta360-admin-demo-2026',
        'connect_timeout' => 2,
        'timeout' => 5,
    ],
    'services' => [
        'weather_url' => 'https://api.open-meteo.com/v1/forecast',
        'soap_wsdl' => '',
        'cache_seconds' => 900,
        'stale_cache_seconds' => 86400,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

date_default_timezone_set($config['app']['timezone']);
$directorioLogs = dirname(__DIR__) . '/logs';
if (is_dir($directorioLogs) && is_writable($directorioLogs)) {
    ini_set('log_errors', '1');
    ini_set('error_log', $directorioLogs . '/ruta360.log');
}

if ($config['app']['show_errors']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

return $config;
