<?php
// config/servicios.php · Configuración centralizada de integraciones externas (Manual 12.17)
declare(strict_types=1);

return [
    'meteorologia' => [
        'url' => $_ENV['METEO_URL'] ?? 'https://api.open-meteo.com/v1/forecast',
        'connect_timeout' => 2,
        'timeout' => 5
    ],
    'transporte' => [
        'url' => $_ENV['TRANS_URL'] ?? 'http://localhost/curso-soc-php/Ruta360/api/transporte.php',
        'token' => $_ENV['TRANS_TOKEN'] ?? '',
        'connect_timeout' => 2,
        'timeout' => 4
    ],
    'distancias_soap' => [
        'wsdl' => $_ENV['DISTANCIAS_WSDL'] ?? __DIR__ . '/../servicios/distancias.wsdl',
        'connect_timeout' => 2,
        'timeout' => 5
    ]
];
