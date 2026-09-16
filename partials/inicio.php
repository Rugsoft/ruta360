<?php
declare(strict_types=1);
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/seguridad_web.php';
$titulo = $titulo ?? 'Ruta360';
$usuario = usuarioActual();
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($titulo) ?> · Ruta360</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($config['app']['base_url']) ?>/assets/estilos.css">
</head>
<body>
<header class="cabecera">
  <a class="marca" href="<?= htmlspecialchars($config['app']['base_url']) ?>/">Ruta360</a>
  <nav>
    <a href="<?= htmlspecialchars($config['app']['base_url']) ?>/rutas.php">Rutas</a>
    <a href="<?= htmlspecialchars($config['app']['base_url']) ?>/tiempo.php">Tiempo</a>
    <a href="<?= htmlspecialchars($config['app']['base_url']) ?>/integraciones.php">Integraciones</a>
    <?php if ($usuario): ?>
      <a href="<?= htmlspecialchars($config['app']['base_url']) ?>/panel.php">Panel</a>
      <a href="<?= htmlspecialchars($config['app']['base_url']) ?>/logout.php">Salir</a>
    <?php else: ?>
      <a href="<?= htmlspecialchars($config['app']['base_url']) ?>/login.php">Entrar</a>
    <?php endif; ?>
  </nav>
</header>
<main class="contenedor">

