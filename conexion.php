<?php
$host = 'localhost';
$baseDatos = 'ruta360';
$usuario = 'root';
$contrasena = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$baseDatos;charset=utf8mb4",
        $usuario,
        $contrasena,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    exit('No se ha podido conectar con la base de datos.');
}
?>
