<?php
// servicios/AdaptadorMeteorologia.php · Adaptador de meteorología REST (Manual 12.5)
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/meteorologia.php';

final class AdaptadorMeteorologia implements ProveedorExterno
{
    public function __construct(private array $config = []) {}

    public function nombre(): string
    {
        return 'meteorologia';
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        if (!isset($contexto['latitud'], $contexto['longitud'])) {
            return new ResultadoExterno(
                false,
                $this->nombre(),
                [],
                'sin_datos',
                'Faltan coordenadas en el contexto',
                0
            );
        }

        $inicio = hrtime(true);
        $r = obtenerTiempoResiliente(
            (float) $contexto['latitud'],
            (float) $contexto['longitud']
        );
        $duracionMs = (int) ((hrtime(true) - $inicio) / 1_000_000);

        return new ResultadoExterno(
            $r['disponible'],
            $this->nombre(),
            $r['datos'] ?? [],
            $r['origen'],
            $r['disponible'] ? null : 'Meteorología no disponible',
            $duracionMs
        );
    }
}
