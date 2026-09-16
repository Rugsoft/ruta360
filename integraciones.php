<?php
declare(strict_types=1);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/clases/Integraciones.php';
$config = require __DIR__ . '/config/config.php';
$id = filter_input(INPUT_GET, 'id_ruta', FILTER_VALIDATE_INT) ?: 1;
$s = $pdo->prepare('SELECT r.*,c.nombre ciudad,c.latitud,c.longitud FROM rutas r INNER JOIN ciudades c ON c.id_ciudad=r.id_ciudad WHERE r.id_ruta=:id AND r.activa=1');
$s->execute(['id'=>$id]); $ruta=$s->fetch();
$rutas=$pdo->query('SELECT id_ruta,titulo FROM rutas WHERE activa=1 ORDER BY titulo')->fetchAll();
$proveedorDistancia = $config['services']['soap_wsdl'] !== '' && extension_loaded('soap')
    ? new AdaptadorDistanciaSoap($config['services']['soap_wsdl'])
    : new AdaptadorDistanciaLocal();
$resultados=$ruta ? (new PlanificadorRuta([$proveedorDistancia,new AdaptadorTransporteSimulado(),new AdaptadorMeteorologia()]))->preparar($ruta) : [];
$titulo='Integraciones'; require __DIR__ . '/partials/inicio.php';
?>
<h1>Integraciones y respuesta degradada</h1><form method="get"><label>Ruta<select name="id_ruta"><?php foreach($rutas as $r): ?><option value="<?= (int)$r['id_ruta'] ?>" <?= $id===(int)$r['id_ruta']?'selected':'' ?>><?= htmlspecialchars($r['titulo']) ?></option><?php endforeach; ?></select></label><button>Planificar</button></form>
<?php if(!$ruta): ?><p class="error">Ruta no encontrada.</p><?php else: ?><h2><?= htmlspecialchars($ruta['titulo']) ?></h2><section class="rejilla"><?php foreach($resultados as $r): ?><article class="tarjeta"><h3><?= htmlspecialchars(ucfirst($r->proveedor)) ?></h3><?php if($r->disponible): ?><pre><?= htmlspecialchars(json_encode($r->datos,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre><small>Origen: <?= htmlspecialchars($r->origen) ?> · <?= $r->duracionMs ?> ms</small><?php else: ?><p class="aviso"><?= htmlspecialchars($r->aviso ?? 'No disponible') ?></p><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?>
<?php require __DIR__ . '/partials/fin.php'; ?>
