<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/servicios/RepositorioRuta.php';
require_once __DIR__ . '/servicios/ProveedorExterno.php';
require_once __DIR__ . '/servicios/ResultadoExterno.php';
require_once __DIR__ . '/servicios/AdaptadorMeteorologia.php';
require_once __DIR__ . '/servicios/AdaptadorTransporte.php';
require_once __DIR__ . '/servicios/AdaptadorDistanciasSoap.php';
require_once __DIR__ . '/servicios/PlanificadorRuta.php';

echo "====================================================\n";
echo "PRUEBAS DE ORQUESTACIÓN Y ADAPTADORES (Ruta360)\n";
echo "====================================================\n\n";

$total = 0;
$errores = 0;

function probar(string $descripcion, bool $condicion, string $detalle = ''): void
{
    global $errores, $total;
    $total++;
    if ($condicion) {
        echo " [OK] $descripcion\n";
    } else {
        $errores++;
        echo " [FALLO] $descripcion - Detalle: $detalle\n";
    }
}

// 12.27 Doble de prueba para simular proveedores externos
final class ProveedorSimulado implements ProveedorExterno
{
    public function __construct(
        private string $id,
        private ResultadoExterno|Throwable $respuesta,
        private int $retrasoMs = 0
    ) {}

    public function nombre(): string
    {
        return $this->id;
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        if ($this->retrasoMs > 0) {
            usleep($this->retrasoMs * 1000);
        }
        if ($this->respuesta instanceof Throwable) {
            throw $this->respuesta;
        }
        return $this->respuesta;
    }
}

$repositorio = new RepositorioRuta($pdo);

// 1. Repositorio local carga ruta existente
$ruta1 = $repositorio->buscarPorId(1);
probar("1. RepositorioRuta recupera ruta local 1", $ruta1 !== null && $ruta1['id_ruta'] === 1, "Ruta: " . var_export($ruta1, true));

// 2. Todos los proveedores simulados correctos
$pSoap = new ProveedorSimulado('distancias', new ResultadoExterno(true, 'distancias', ['distancia_km' => 4.80], 'soap_oficial', null, 15));
$pTrans = new ProveedorSimulado('transporte', new ResultadoExterno(true, 'transporte', ['duracion_minutos' => 25, 'incidencias' => []], 'servicio', null, 20));
$pMeteo = new ProveedorSimulado('meteorologia', new ResultadoExterno(true, 'meteorologia', ['temperatura' => 21.5], 'cache_reciente', null, 5));

$planificador = new PlanificadorRuta($repositorio, [$pSoap, $pTrans, $pMeteo], 7000);
$ficha = $planificador->preparar(1);

probar("2. Orquestador reúne las 3 integraciones cuando todas responden",
    isset($ficha['externos']['distancias'], $ficha['externos']['transporte'], $ficha['externos']['meteorologia']) &&
    $ficha['externos']['distancias']->disponible &&
    $ficha['externos']['transporte']->disponible &&
    $ficha['externos']['meteorologia']->disponible
);

// 3. Fallo SOAP (SoapFault): La ficha continúa y distancias queda no disponible
$pSoapFallo = new ProveedorSimulado('distancias', new SoapFault('Server', 'Fallo simulado en servidor SOAP'));
$planificadorSoapFallo = new PlanificadorRuta($repositorio, [$pSoapFallo, $pTrans, $pMeteo], 7000);
$fichaSoapFallo = $planificadorSoapFallo->preparar(1);

probar("3. Aislamiento de fallos: SoapFault no interrumpe y transporte/meteo siguen disponibles",
    $fichaSoapFallo['externos']['distancias']->disponible === false &&
    $fichaSoapFallo['externos']['transporte']->disponible === true &&
    $fichaSoapFallo['externos']['meteorologia']->disponible === true
);

// 4. Fallo Transporte: La ficha continúa sin transporte
$pTransFallo = new ProveedorSimulado('transporte', new RuntimeException('Transporte Timeout'));
$planificadorTransFallo = new PlanificadorRuta($repositorio, [$pSoap, $pTransFallo, $pMeteo], 7000);
$fichaTransFallo = $planificadorTransFallo->preparar(1);

probar("4. Fallo en transporte: ficha continúa con SOAP y meteo",
    $fichaTransFallo['externos']['transporte']->disponible === false &&
    $fichaTransFallo['externos']['distancias']->disponible === true &&
    $fichaTransFallo['externos']['meteorologia']->disponible === true
);

// 5. Meteorología usa aviso de caché antigua
$pMeteoAntiguo = new ProveedorSimulado('meteorologia', new ResultadoExterno(true, 'meteorologia', ['temperatura' => 19.0], 'cache_antigua', 'Dato anterior', 10));
$planificadorMeteoAntiguo = new PlanificadorRuta($repositorio, [$pSoap, $pTrans, $pMeteoAntiguo], 7000);
$fichaMeteoAntiguo = $planificadorMeteoAntiguo->preparar(1);

probar("5. Meteorología con caché antigua preserva datos y origen 'cache_antigua'",
    $fichaMeteoAntiguo['externos']['meteorologia']->origen === 'cache_antigua' &&
    $fichaMeteoAntiguo['externos']['meteorologia']->disponible === true
);

// 6. Presupuesto global agotado (ej. 50ms presupuesto, con un proveedor previo de 80ms)
$pLento = new ProveedorSimulado('distancias', new ResultadoExterno(true, 'distancias', ['distancia_km' => 4.8], 'servicio', null, 80), 80);
$pSiguiente = new ProveedorSimulado('transporte', new ResultadoExterno(true, 'transporte', ['duracion_minutos' => 30], 'servicio', null, 10));

$planificadorPresupuesto = new PlanificadorRuta($repositorio, [$pLento, $pSiguiente], 50); // Presupuesto de 50ms
$fichaPresupuesto = $planificadorPresupuesto->preparar(1);

probar("6. Presupuesto global agotado: omite proveedores complementarios con origen 'omitido'",
    $fichaPresupuesto['externos']['transporte']->origen === 'omitido' &&
    $fichaPresupuesto['externos']['transporte']->disponible === false,
    "Origen: " . ($fichaPresupuesto['externos']['transporte']->origen ?? '')
);

// 7. Todos los proveedores fallan -> la ruta local sigue disponible
$pTodosFallan1 = new ProveedorSimulado('distancias', new RuntimeException('Error dist'));
$pTodosFallan2 = new ProveedorSimulado('transporte', new RuntimeException('Error trans'));
$pTodosFallan3 = new ProveedorSimulado('meteorologia', new RuntimeException('Error meteo'));

$planificadorTodosFallan = new PlanificadorRuta($repositorio, [$pTodosFallan1, $pTodosFallan2, $pTodosFallan3], 7000);
$fichaTodosFallan = $planificadorTodosFallan->preparar(1);

probar("7. Fallo total de externos: la ruta local permanece intacta y accesible",
    $fichaTodosFallan['ruta']['id_ruta'] === 1 &&
    $fichaTodosFallan['externos']['distancias']->disponible === false &&
    $fichaTodosFallan['externos']['transporte']->disponible === false &&
    $fichaTodosFallan['externos']['meteorologia']->disponible === false
);

// 8. Prueba del Adaptador SOAP real con el WSDL local
$configServicios = require __DIR__ . '/config/servicios.php';
$adaptadorSoapReal = new AdaptadorDistanciasSoap($configServicios['distancias_soap']['wsdl']);
$resSoapReal = $adaptadorSoapReal->consultar([
    'origen' => 'Sagrada Família',
    'destino' => 'Casa Batlló',
    'latitud_origen' => 41.3874,
    'longitud_origen' => 2.1686,
    'latitud_destino' => 41.3916,
    'longitud_destino' => 2.1649
]);

probar("8. AdaptadorDistanciasSoap ejecuta llamada SOAP local y normaliza distancia_km",
    $resSoapReal->disponible === true && isset($resSoapReal->datos['distancia_km']) && $resSoapReal->datos['distancia_km'] > 0,
    "Datos: " . json_encode($resSoapReal->datos)
);

// 9. Prueba del Adaptador Transporte REST real
$adaptadorTransReal = new AdaptadorTransporte($configServicios['transporte']);
$resTransReal = $adaptadorTransReal->consultar([
    'origen' => 'Sagrada Família',
    'destino' => 'Casa Batlló'
]);

probar("9. AdaptadorTransporte ejecuta llamada REST y normaliza duracion_minutos e incidencias",
    $resTransReal->disponible === true && isset($resTransReal->datos['duracion_minutos']) && is_array($resTransReal->datos['incidencias']),
    "Datos: " . json_encode($resTransReal->datos)
);

// 10. Prueba del Adaptador Meteorología REST real
$adaptadorMeteoReal = new AdaptadorMeteorologia($configServicios['meteorologia']);
$resMeteoReal = $adaptadorMeteoReal->consultar([
    'latitud' => 41.3874,
    'longitud' => 2.1686
]);

probar("10. AdaptadorMeteorologia ejecuta consulta resiliente con caché y normaliza",
    $resMeteoReal->disponible === true && isset($resMeteoReal->datos['temperatura']),
    "Datos: " . json_encode($resMeteoReal->datos)
);

// 11. Integración completa con PlanificadorRuta real
$planificadorReal = new PlanificadorRuta($repositorio, [
    $adaptadorSoapReal,
    $adaptadorTransReal,
    $adaptadorMeteoReal
], 7000);

$fichaReal = $planificadorReal->preparar(1);
probar("11. PlanificadorRuta orquesta los 3 adaptadores reales de forma exitosa",
    $fichaReal['externos']['distancias']->disponible &&
    $fichaReal['externos']['transporte']->disponible &&
    $fichaReal['externos']['meteorologia']->disponible
);

// 12. Acceso web a ver_ruta.php reúne todos los datos coordinados
$ch = curl_init('http://localhost/curso-soc-php/Ruta360/ver_ruta.php?id_ruta=1');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8
]);
$htmlFicha = (string) curl_exec($ch);
$codigoFicha = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

probar("12. Vista ver_ruta.php renderiza bloques de meteorología y transporte coordinados",
    $codigoFicha === 200 && str_contains($htmlFicha, 'Transporte y movilidad') && str_contains($htmlFicha, 'Meteorología actual'),
    "Código HTTP: $codigoFicha"
);

echo "\n----------------------------------------------------\n";
echo "RESULTADO: " . ($total - $errores) . " de $total pruebas superadas.\n";
if ($errores === 0) {
    echo "¡Todas las pruebas de orquestación y adaptadores pasaron con éxito!\n";
} else {
    echo "Hubo $errores fallos.\n";
}
echo "====================================================\n";
