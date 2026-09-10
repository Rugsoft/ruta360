<?php
// servicios/PlanificadorRuta.php · Orquestador de integraciones con presupuesto global (Manual 12.19 - 12.26)
declare(strict_types=1);

require_once __DIR__ . '/RepositorioRuta.php';
require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/ResultadoExterno.php';

final class PlanificadorRuta
{
    /**
     * @param RepositorioRuta $rutas Repositorio de acceso a datos locales
     * @param array<ProveedorExterno> $proveedores Lista de proveedores ordenados por prioridad
     * @param int $presupuestoMs Presupuesto máximo total en milisegundos (por defecto 7000ms = 7s)
     */
    public function __construct(
        private RepositorioRuta $rutas,
        private array $proveedores,
        private int $presupuestoMs = 7000
    ) {}

    /**
     * Prepara la ficha completa combinando datos internos y llamadas a adaptadores externos
     *
     * @return array{ruta: array, externos: array<string, ResultadoExterno>}
     */
    public function preparar(int $idRuta): array
    {
        $ruta = $this->rutas->buscarPorId($idRuta);
        if ($ruta === null) {
            throw new RuntimeException('Ruta no encontrada.');
        }

        $contexto = $this->crearContexto($ruta);
        $externos = [];
        $inicioGlobal = hrtime(true);

        foreach ($this->proveedores as $proveedor) {
            $transcurridoMs = (int) ((hrtime(true) - $inicioGlobal) / 1_000_000);

            if ($transcurridoMs >= $this->presupuestoMs) {
                $externos[$proveedor->nombre()] = new ResultadoExterno(
                    false,
                    $proveedor->nombre(),
                    [],
                    'omitido',
                    'No se consultó por límite de tiempo'
                );
                continue;
            }

            $externos[$proveedor->nombre()] = $this->consultarSeguro($proveedor, $contexto);
        }

        return [
            'ruta' => $ruta,
            'externos' => $externos
        ];
    }

    /**
     * 12.19 Construye el contexto común para todos los proveedores
     */
    public function crearContexto(array $ruta): array
    {
        $puntos = $ruta['puntos_interes'] ?? [];
        $origen = $puntos[0]['nombre'] ?? ($ruta['ciudad']['nombre'] ?? 'Inicio');
        $destino = count($puntos) > 1
            ? $puntos[count($puntos) - 1]['nombre']
            : ($ruta['titulo'] ?? 'Fin');

        return [
            'id_ruta' => (int) $ruta['id_ruta'],
            'titulo' => (string) $ruta['titulo'],
            'id_ciudad' => (int) ($ruta['ciudad']['id_ciudad'] ?? 0),
            'ciudad' => (string) ($ruta['ciudad']['nombre'] ?? ''),
            'latitud' => (float) ($ruta['ciudad']['latitud'] ?? 0),
            'longitud' => (float) ($ruta['ciudad']['longitud'] ?? 0),
            'origen' => (string) $origen,
            'destino' => (string) $destino,
            'puntos' => $puntos
        ];
    }

    /**
     * 12.20 y 12.26 Aísla los fallos y registra métricas comparables
     */
    private function consultarSeguro(
        ProveedorExterno $proveedor,
        array $contexto
    ): ResultadoExterno {
        try {
            $resultado = $proveedor->consultar($contexto);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[integracion] proveedor=%s tipo=%s mensaje=%s',
                $proveedor->nombre(),
                $e::class,
                $e->getMessage()
            ));

            $resultado = new ResultadoExterno(
                false,
                $proveedor->nombre(),
                [],
                'sin_datos',
                'Información temporalmente no disponible'
            );
        }

        // Registro de métricas estructuradas (Manual 12.26)
        error_log(sprintf(
            '[integracion] proveedor=%s disponible=%s origen=%s duracion_ms=%d',
            $resultado->proveedor,
            $resultado->disponible ? 'si' : 'no',
            $resultado->origen,
            $resultado->duracionMs
        ));

        return $resultado;
    }
}
