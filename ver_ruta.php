<?php
// ver_ruta.php · Ficha de ruta enriquecida mediante adaptadores y orquestador (Manual 12)
declare(strict_types=1);

require_once __DIR__ . '/seguridad_web.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/servicios/cliente_rutas.php';
require_once __DIR__ . '/servicios/RepositorioRuta.php';
require_once __DIR__ . '/servicios/AdaptadorDistanciasSoap.php';
require_once __DIR__ . '/servicios/AdaptadorTransporte.php';
require_once __DIR__ . '/servicios/AdaptadorMeteorologia.php';
require_once __DIR__ . '/servicios/PlanificadorRuta.php';

$usuario = usuarioAutenticado();

$idRuta = filter_input(
    INPUT_GET,
    'id_ruta',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$ruta = null;
$puntos = [];
$externos = [];
$errorCarga = null;

if ($idRuta === false || $idRuta === null) {
    $errorCarga = 'Selecciona una ruta válida.';
} else {
    $configServicios = require __DIR__ . '/config/servicios.php';
    $repositorio = new RepositorioRuta($pdo);

    $proveedores = [
        new AdaptadorDistanciasSoap($configServicios['distancias_soap']['wsdl']),
        new AdaptadorTransporte($configServicios['transporte']),
        new AdaptadorMeteorologia($configServicios['meteorologia']),
    ];

    $planificador = new PlanificadorRuta($repositorio, $proveedores, 7000);

    try {
        $ficha = $planificador->preparar($idRuta);
        $ruta = $ficha['ruta'];
        $puntos = $ruta['puntos_interes'] ?? [];
        $externos = $ficha['externos'] ?? [];
    } catch (Throwable $e) {
        $errorCarga = $e->getMessage();
    }
}

$actualizada = filter_input(INPUT_GET, 'actualizada', FILTER_VALIDATE_INT) === 1;
$errorPatch = '';

// Actividad guiada 9.30 · PATCH de duración desde la interfaz
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['cambiar_duracion']) && $idRuta) {
    exigirRolWeb(['editor', 'admin']);
    validarCsrf();
    $nuevaDuracion = trim((string) ($_POST['duracion_minutos'] ?? ''));
    $resultadoPatch = modificarRuta($idRuta, ['duracion_minutos' => $nuevaDuracion]);
    if ($resultadoPatch['ok'] ?? false) {
        header('Location: ver_ruta.php?id_ruta=' . $idRuta . '&actualizada=1');
        exit;
    }
    $errorPatch = $resultadoPatch['mensaje'] ?? 'No se ha podido actualizar la duración.';
}

$claseDificultad = match ($ruta['dificultad'] ?? null) {
    'fácil' => 'facil',
    'media' => 'media',
    'alta' => 'alta',
    default => null,
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de ruta | Ruta360</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<main class="panel panel-amplio">
    <header class="panel-cabecera">
        <a class="volver" href="rutas.php">← Todas las rutas</a>
        <p class="panel-rol">Detalle de ruta</p>
    </header>

    <?php if ($actualizada): ?>
        <div class="aviso aviso-exito">
            Ruta actualizada correctamente.
        </div>
    <?php endif; ?>

    <?php if ($errorCarga !== null || $ruta === null): ?>
        <h1>No se ha podido cargar la ruta</h1>
        <p class="error"><?= htmlspecialchars($errorCarga ?? 'Ruta no encontrada.') ?></p>
    <?php else: ?>
        <h1><?= htmlspecialchars($ruta['titulo']) ?></h1>
        <p>
            <?= htmlspecialchars($ruta['ciudad']['nombre']) ?>,
            <?= htmlspecialchars($ruta['ciudad']['pais']) ?>
        </p>
        <p><?= htmlspecialchars($ruta['descripcion']) ?></p>

        <div class="acciones-ruta">
            <?php if ($usuario && in_array($usuario['rol'], ['editor', 'admin'], true)): ?>
                <a class="boton-accion" href="editar_ruta.php?id_ruta=<?= (int) $ruta['id_ruta'] ?>">Editar ruta</a>
            <?php endif; ?>
            <?php if ($usuario && $usuario['rol'] === 'admin'): ?>
                <form method="post" action="eliminar_ruta.php" onsubmit="return confirm('¿Eliminar esta ruta?');" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">
                    <input type="hidden" name="id_ruta" value="<?= (int) $ruta['id_ruta'] ?>">
                    <button type="submit" class="peligro">Eliminar ruta</button>
                </form>
            <?php endif; ?>
            <?php if (!$usuario): ?>
                <a class="boton-accion" href="login.php">Iniciar sesión para gestionar</a>
            <?php endif; ?>
        </div>

        <div class="lecturas">
            <div class="lectura">
                <p class="lectura-etiqueta">Duración</p>
                <p class="lectura-valor"><?= (int) $ruta['duracion_minutos'] ?><span class="unidad">min</span></p>
            </div>
            <div class="lectura">
                <p class="lectura-etiqueta">Distancia</p>
                <?php
                $distanciaMostrar = (float) $ruta['distancia_km'];
                $origenDistancia = 'local';
                if (isset($externos['distancias']) && $externos['distancias']->disponible && isset($externos['distancias']->datos['distancia_km'])) {
                    $distanciaMostrar = (float) $externos['distancias']->datos['distancia_km'];
                    $origenDistancia = 'soap_oficial';
                }
                ?>
                <p class="lectura-valor"><?= $distanciaMostrar ?><span class="unidad">km</span></p>
                <?php if ($origenDistancia === 'soap_oficial'): ?>
                    <small class="meta-ruta">Validada por SOAP (<?= (int) $externos['distancias']->duracionMs ?>ms)</small>
                <?php endif; ?>
            </div>
            <div class="lectura">
                <p class="lectura-etiqueta">Dificultad</p>
                <p class="lectura-valor">
                    <?php if ($claseDificultad === null): ?>
                        <span class="etiqueta etiqueta-neutra"><?= htmlspecialchars($ruta['dificultad'] ?? '—') ?></span>
                    <?php else: ?>
                        <span class="etiqueta etiqueta-<?= $claseDificultad ?>"><?= htmlspecialchars($ruta['dificultad']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (isset($ruta['numero_puntos'])): ?>
            <p>Lugares incluidos: <?= (int) $ruta['numero_puntos'] ?></p>
        <?php endif; ?>

        <!-- Sección de integraciones coordinadas por el Planificador (Manual 12.25) -->
        <div class="bloque-servicios">
            <!-- 1. Meteorología REST -->
            <?php if (isset($externos['meteorologia'])): $m = $externos['meteorologia']; ?>
                <section class="servicio-integracion">
                    <div class="servicio-cabecera">
                        <p class="servicio-titulo">Meteorología actual</p>
                        <span class="servicio-meta">Origen: <?= htmlspecialchars($m->origen) ?> · <?= (int) $m->duracionMs ?>ms</span>
                    </div>
                    <?php if ($m->disponible): ?>
                        <p class="servicio-valor-destacado"><?= htmlspecialchars((string) ($m->datos['temperatura'] ?? '—')) ?><span class="unidad">°C</span></p>
                        <?php if ($m->origen === 'cache_antigua'): ?>
                            <div class="aviso" style="margin-top: 0.5rem; margin-bottom: 0;">Dato anterior. El servicio no responde ahora.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="aviso" style="margin-bottom: 0;"><?= htmlspecialchars($m->aviso ?? 'Meteorología no disponible.') ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- 2. Transporte público REST -->
            <?php if (isset($externos['transporte'])): $t = $externos['transporte']; ?>
                <section class="servicio-integracion">
                    <div class="servicio-cabecera">
                        <p class="servicio-titulo">Transporte y movilidad</p>
                        <span class="servicio-meta">Origen: <?= htmlspecialchars($t->origen) ?> · <?= (int) $t->duracionMs ?>ms</span>
                    </div>
                    <?php if ($t->disponible): ?>
                        <p class="servicio-cuerpo">
                            Tiempo estimado en transporte: <strong><?= (int) ($t->datos['duracion_minutos'] ?? 0) ?> min</strong>
                        </p>
                        <?php if (!empty($t->datos['incidencias'])): ?>
                            <div class="aviso" style="margin-top: 0.5rem; margin-bottom: 0;">
                                <?php foreach ($t->datos['incidencias'] as $incidencia): ?>
                                    <p style="margin: 0;"><?= htmlspecialchars((string) $incidencia) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="aviso" style="margin-bottom: 0;"><?= htmlspecialchars($t->aviso ?? 'Información de transporte no disponible.') ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>

        <h2>Puntos de interés</h2>
        <?php if (!$puntos): ?>
            <p>Esta ruta todavía no tiene puntos de interés.</p>
        <?php else: ?>
            <ol>
                <?php foreach ($puntos as $punto): ?>
                    <li>
                        <strong><?= htmlspecialchars($punto['nombre']) ?></strong>
                        — <?= htmlspecialchars($punto['descripcion']) ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($usuario && in_array($usuario['rol'], ['editor', 'admin'], true)): ?>
        <details class="detalle-patch">
            <summary>Ajustar solo la duración (PATCH)</summary>
            <form method="post" class="formulario-patch">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">
                <input type="hidden" name="cambiar_duracion" value="1">
                <label for="duracion_rapida">Nueva duración (minutos):</label>
                <input
                    type="number"
                    id="duracion_rapida"
                    name="duracion_minutos"
                    min="15"
                    max="1440"
                    required
                    value="<?= (int) $ruta['duracion_minutos'] ?>">
                <button type="submit">Actualizar duración</button>
            </form>
            <?php if ($errorPatch !== ''): ?>
                <p class="error"><?= htmlspecialchars($errorPatch) ?></p>
            <?php endif; ?>
        </details>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
