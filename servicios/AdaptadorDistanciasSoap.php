<?php
// servicios/AdaptadorDistanciasSoap.php · Adaptador SOAP para distancias oficiales (Manual 12.11 - 12.15)
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/meteorologia.php';

function crearClienteSoap(string $wsdl, int $connectionTimeout = 2): SoapClient
{
    if (!extension_loaded('soap')) {
        throw new RuntimeException('La extensión SOAP no está activada.');
    }

    return new SoapClient($wsdl, [
        'exceptions' => true,
        'connection_timeout' => $connectionTimeout,
        'cache_wsdl' => WSDL_CACHE_DISK,
        'trace' => false,
        'keep_alive' => false
    ]);
}

final class AdaptadorDistanciasSoap implements ProveedorExterno
{
    public function __construct(private string $wsdl) {}

    public function nombre(): string
    {
        return 'distancias';
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        $cliente = null;
        $anterior = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '5');
        $inicio = hrtime(true);

        try {
            $cliente = crearClienteSoap($this->wsdl);
            $r = $cliente->__soapCall('CalcularDistancia', [[
                'Origen' => $contexto['origen'] ?? '',
                'Destino' => $contexto['destino'] ?? '',
                'LatitudOrigen' => $contexto['latitud_origen'] ?? $contexto['latitud'] ?? null,
                'LongitudOrigen' => $contexto['longitud_origen'] ?? $contexto['longitud'] ?? null,
                'LatitudDestino' => $contexto['latitud_destino'] ?? null,
                'LongitudDestino' => $contexto['longitud_destino'] ?? null,
            ]]);

            $km = $r->CalcularDistanciaResult->DistanceKm ?? null;
            if (!is_numeric($km) || (float) $km < 0) {
                throw new ServicioExternoException(
                    'contenido',
                    'Distancia SOAP no válida.'
                );
            }

            $duracionMs = (int) ((hrtime(true) - $inicio) / 1_000_000);

            return new ResultadoExterno(
                true,
                $this->nombre(),
                ['distancia_km' => (float) $km],
                'servicio',
                null,
                $duracionMs
            );
        } catch (SoapFault $e) {
            error_log(sprintf(
                '[soap] faultcode=%s mensaje=%s',
                (string) $e->faultcode,
                $e->getMessage()
            ));
            throw new ServicioExternoException(
                'soap',
                'Fallo del servicio SOAP.'
            );
        } finally {
            if ($anterior !== false) {
                ini_set('default_socket_timeout', (string) $anterior);
            }
            $cliente = null;
        }
    }
}
