<?php
// servicios/AdaptadorTransporte.php · Adaptador de transporte público REST (Manual 12.6)
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/meteorologia.php';

final class AdaptadorTransporte implements ProveedorExterno
{
    public function __construct(private array $config = []) {}

    public function nombre(): string
    {
        return 'transporte';
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        $origen = (string) ($contexto['origen'] ?? 'Origen');
        $destino = (string) ($contexto['destino'] ?? 'Destino');

        $inicio = hrtime(true);
        $json = $this->solicitarJsonTransporte($origen, $destino);

        if (!isset($json['estimated_minutes'])) {
            throw new ServicioExternoException(
                'contenido',
                'Falta estimated_minutes en la respuesta de transporte.'
            );
        }

        $duracionMs = (int) ((hrtime(true) - $inicio) / 1_000_000);

        return new ResultadoExterno(
            true,
            $this->nombre(),
            [
                'duracion_minutos' => (int) $json['estimated_minutes'],
                'incidencias' => array_values($json['alerts'] ?? [])
            ],
            'servicio',
            null,
            $duracionMs
        );
    }

    private function solicitarJsonTransporte(string $origen, string $destino): array
    {
        $baseUrl = $this->config['url'] ?? 'http://localhost/curso-soc-php/Ruta360/api/transporte.php';
        $connectTimeout = (int) ($this->config['connect_timeout'] ?? 2);
        $timeout = (int) ($this->config['timeout'] ?? 4);

        $url = $baseUrl . '?' . http_build_query([
            'origen' => $origen,
            'destino' => $destino
        ]);

        $ch = null;
        try {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new ServicioExternoException('inicio', 'No se ha podido iniciar cURL.');
            }

            $headers = ['Accept: application/json'];
            if (!empty($this->config['token'])) {
                $headers[] = 'Authorization: Bearer ' . $this->config['token'];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $headers
            ]);

            $cuerpo = curl_exec($ch);
            $codigoCurl = curl_errno($ch);
            $errorCurl = curl_error($ch);
            $info = curl_getinfo($ch);

            if ($cuerpo === false || $codigoCurl !== 0) {
                throw new ServicioExternoException(
                    'transporte',
                    'Fallo de comunicación en transporte: ' . $errorCurl,
                    0,
                    $codigoCurl
                );
            }

            $codigoHttp = (int) ($info['http_code'] ?? 0);
            if ($codigoHttp < 200 || $codigoHttp >= 300) {
                throw new ServicioExternoException(
                    'http',
                    'El proveedor de transporte respondió con HTTP ' . $codigoHttp,
                    $codigoHttp
                );
            }

            $datos = json_decode((string) $cuerpo, true);
            if (!is_array($datos)) {
                throw new ServicioExternoException('json', 'Respuesta de transporte no es un JSON válido.');
            }

            return $datos;
        } finally {
            $ch = null;
        }
    }
}
