<?php
// api/transporte.php · Endpoint REST simulado de transporte público (Manual 12.6)
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$origen = filter_input(INPUT_GET, 'origen') ?? 'Punto A';
$destino = filter_input(INPUT_GET, 'destino') ?? 'Punto B';

$minutos = 25;
$alertas = [];

if (stripos($origen, 'sagrada') !== false || stripos($destino, 'sagrada') !== false) {
    $minutos = 35;
    $alertas[] = 'Obras menores en la línea L2 de metro.';
} elseif (stripos($origen, 'sol') !== false || stripos($destino, 'sol') !== false) {
    $minutos = 20;
} else {
    $minutos = 28;
}

echo json_encode([
    'ok' => true,
    'origin' => $origen,
    'destination' => $destino,
    'estimated_minutes' => $minutos,
    'alerts' => $alertas,
    'timestamp' => date(DATE_ATOM)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
