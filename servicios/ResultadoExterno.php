<?php
// servicios/ResultadoExterno.php · Contrato de respuesta uniforme para integraciones externas (Manual 12.4)
declare(strict_types=1);

final class ResultadoExterno
{
    public function __construct(
        public readonly bool $disponible,
        public readonly string $proveedor,
        public readonly array $datos = [],
        public readonly string $origen = 'servicio',
        public readonly ?string $aviso = null,
        public readonly int $duracionMs = 0
    ) {}
}
