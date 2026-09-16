<?php
declare(strict_types=1);

final class ResultadoExterno
{
    public function __construct(
        public bool $disponible,
        public string $proveedor,
        public array $datos = [],
        public string $origen = 'servicio',
        public ?string $aviso = null,
        public int $duracionMs = 0
    ) {}
}

interface ProveedorExterno
{
    public function nombre(): string;
    public function consultar(array $contexto): ResultadoExterno;
}

final class AdaptadorMeteorologia implements ProveedorExterno
{
    public function nombre(): string { return 'meteorología'; }
    public function consultar(array $contexto): ResultadoExterno
    {
        require_once __DIR__ . '/../servicios/meteorologia.php';
        $inicio = hrtime(true);
        $r = obtenerTiempoResiliente((float) $contexto['latitud'], (float) $contexto['longitud']);
        return new ResultadoExterno($r['disponible'], $this->nombre(), $r['datos'], $r['origen'],
            $r['aviso'] ?? null, (int) ((hrtime(true) - $inicio) / 1000000));
    }
}

final class AdaptadorTransporteSimulado implements ProveedorExterno
{
    public function nombre(): string { return 'transporte'; }
    public function consultar(array $contexto): ResultadoExterno
    {
        $minutos = max(12, (int) round(((float) $contexto['distancia_km']) * 4));
        return new ResultadoExterno(true, $this->nombre(), [
            'duracion_minutos' => $minutos,
            'incidencias' => [],
        ], 'simulación local');
    }
}

final class AdaptadorDistanciaLocal implements ProveedorExterno
{
    public function nombre(): string { return 'distancia'; }
    public function consultar(array $contexto): ResultadoExterno
    {
        return new ResultadoExterno(true, $this->nombre(), [
            'distancia_km' => (float) $contexto['distancia_km'],
        ], 'base de datos');
    }
}

final class AdaptadorDistanciaSoap implements ProveedorExterno
{
    public function __construct(private string $wsdl) {}
    public function nombre(): string { return 'distancia SOAP'; }
    public function consultar(array $contexto): ResultadoExterno
    {
        if (!extension_loaded('soap') || $this->wsdl === '') {
            throw new RuntimeException('SOAP no está configurado.');
        }
        $inicio = hrtime(true);
        $anterior = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '4');
        try {
            $cliente = new SoapClient($this->wsdl, [
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 3,
            ]);
            $respuesta = $cliente->__soapCall('CalcularDistancia', [[
                'distanciaKm' => (float) $contexto['distancia_km'],
            ]]);
            return new ResultadoExterno(true, $this->nombre(), (array) $respuesta, 'servicio SOAP', null,
                (int) ((hrtime(true) - $inicio) / 1000000));
        } catch (SoapFault $e) {
            throw new RuntimeException('El servicio SOAP no está disponible.', 0, $e);
        } finally {
            ini_set('default_socket_timeout', (string) $anterior);
        }
    }
}

final class PlanificadorRuta
{
    public function __construct(private array $proveedores, private int $presupuestoMs = 7000) {}
    public function preparar(array $ruta): array
    {
        $inicio = hrtime(true);
        $resultados = [];
        foreach ($this->proveedores as $proveedor) {
            $transcurrido = (int) ((hrtime(true) - $inicio) / 1000000);
            if ($transcurrido >= $this->presupuestoMs) {
                $resultados[$proveedor->nombre()] = new ResultadoExterno(
                    false, $proveedor->nombre(), [], 'omitido', 'No se consultó por límite de tiempo.'
                );
                continue;
            }
            try {
                $resultados[$proveedor->nombre()] = $proveedor->consultar($ruta);
            } catch (Throwable $e) {
                error_log('[integracion] proveedor=' . $proveedor->nombre() . ' ' . $e->getMessage());
                $resultados[$proveedor->nombre()] = new ResultadoExterno(
                    false, $proveedor->nombre(), [], 'sin datos', 'Información temporalmente no disponible.'
                );
            }
        }
        return $resultados;
    }
}
