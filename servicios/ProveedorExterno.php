<?php
// servicios/ProveedorExterno.php · Interfaz común para todos los proveedores externos (Manual 12.3)
declare(strict_types=1);

require_once __DIR__ . '/ResultadoExterno.php';

interface ProveedorExterno
{
    public function nombre(): string;
    public function consultar(array $contexto): ResultadoExterno;
}
