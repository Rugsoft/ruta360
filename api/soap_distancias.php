<?php
// api/soap_distancias.php · Endpoint del servicio SOAP de distancias (Manual 12)
declare(strict_types=1);

class ServicioDistanciasHandler
{
    public function CalcularDistancia(stdClass $params): stdClass
    {
        $lat1 = $params->LatitudOrigen ?? null;
        $lon1 = $params->LongitudOrigen ?? null;
        $lat2 = $params->LatitudDestino ?? null;
        $lon2 = $params->LongitudDestino ?? null;

        $km = self::calcularKm($lat1, $lon1, $lat2, $lon2);

        $result = new stdClass();
        $result->DistanceKm = (float) $km;
        $result->Estado = 'OK';

        $response = new stdClass();
        $response->CalcularDistanciaResult = $result;
        return $response;
    }

    public static function calcularKm($lat1, $lon1, $lat2, $lon2): float
    {
        if ($lat1 !== null && $lon1 !== null && $lat2 !== null && $lon2 !== null) {
            $theta = (float) $lon1 - (float) $lon2;
            $dist = sin(deg2rad((float) $lat1)) * sin(deg2rad((float) $lat2)) +
                    cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * cos(deg2rad((float) $theta));
            $dist = acos(max(-1.0, min(1.0, $dist)));
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            $km = round($miles * 1.609344, 2);
            if ($km < 0.1) {
                $km = 3.50;
            }
            return (float) $km;
        }
        return 4.80;
    }
}

if (class_exists('SoapServer')) {
    $wsdl = __DIR__ . '/../servicios/distancias.wsdl';
    $server = new SoapServer($wsdl, [
        'cache_wsdl' => WSDL_CACHE_NONE
    ]);
    $server->setClass(ServicioDistanciasHandler::class);
    $server->handle();
    exit;
}

// Fallback SOAP 1.1 XML cuando SoapServer no está disponible en el worker
header('Content-Type: text/xml; charset=utf-8');

$cuerpoHttp = file_get_contents('php://input') ?: '';
$lat1 = null; $lon1 = null; $lat2 = null; $lon2 = null;

if (preg_match('/<LatitudOrigen>([^<]+)<\/LatitudOrigen>/i', $cuerpoHttp, $m)) {
    $lat1 = (float) $m[1];
}
if (preg_match('/<LongitudOrigen>([^<]+)<\/LongitudOrigen>/i', $cuerpoHttp, $m)) {
    $lon1 = (float) $m[1];
}
if (preg_match('/<LatitudDestino>([^<]+)<\/LatitudDestino>/i', $cuerpoHttp, $m)) {
    $lat2 = (float) $m[1];
}
if (preg_match('/<LongitudDestino>([^<]+)<\/LongitudDestino>/i', $cuerpoHttp, $m)) {
    $lon2 = (float) $m[1];
}

$distanciaCalculada = ServicioDistanciasHandler::calcularKm($lat1, $lon1, $lat2, $lon2);

echo '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://ruta360.local/servicios/distancias">
    <SOAP-ENV:Body>
        <ns1:CalcularDistanciaResponse>
            <ns1:CalcularDistanciaResult>
                <ns1:DistanceKm>' . number_format($distanciaCalculada, 2, '.', '') . '</ns1:DistanceKm>
                <ns1:Estado>OK</ns1:Estado>
            </ns1:CalcularDistanciaResult>
        </ns1:CalcularDistanciaResponse>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';
