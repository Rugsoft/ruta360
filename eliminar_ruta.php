<?php
declare(strict_types=1);

require_once __DIR__ . '/servicios/cliente_rutas.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$idRuta = filter_input(INPUT_POST, 'id_ruta', FILTER_VALIDATE_INT);
if ($idRuta === false || $idRuta === null || $idRuta < 1) {
    http_response_code(400);
    exit('Identificador no válido.');
}

$resultado = eliminarRutaCliente($idRuta);
if (!($resultado['ok'] ?? false)) {
    http_response_code($resultado['codigo'] ?? 500);
    exit(htmlspecialchars($resultado['mensaje'] ?? 'No se ha podido eliminar la ruta.'));
}

header('Location: rutas.php?eliminada=1');
exit;
