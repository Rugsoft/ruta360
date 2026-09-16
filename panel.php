<?php
declare(strict_types=1);
require_once __DIR__ . '/config/seguridad_web.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/servicios/cliente_rutas.php';
$usuario = exigirRolWeb(['editor','admin']);
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
    $accion = (string) ($_POST['accion'] ?? '');
    if ($accion === 'crear') {
        $datos = [
            'id_ciudad' => (int)($_POST['id_ciudad'] ?? 0), 'titulo' => trim((string)($_POST['titulo'] ?? '')),
            'descripcion' => trim((string)($_POST['descripcion'] ?? '')), 'duracion_minutos' => (int)($_POST['duracion_minutos'] ?? 0),
            'distancia_km' => (float)($_POST['distancia_km'] ?? 0), 'dificultad' => (string)($_POST['dificultad'] ?? ''),
        ];
        $r = guardarRuta($datos);
        if ($r['ok']) {
            $mensaje = $r['mensaje'] ?: 'Ruta creada.';
        } else {
            $error = $r['mensaje'] ?: 'No se pudo crear.';
        }
    } elseif ($accion === 'eliminar' && $usuario['rol'] === 'admin') {
        $r = eliminarRuta((int)($_POST['id_ruta'] ?? 0));
        if ($r['ok']) {
            $mensaje = $r['mensaje'];
        } else {
            $error = $r['mensaje'];
        }
    }
}
$ciudades = $pdo->query('SELECT id_ciudad,nombre FROM ciudades WHERE activa=1 ORDER BY nombre')->fetchAll();
$rutas = $pdo->query('SELECT r.id_ruta,r.titulo,r.dificultad,c.nombre ciudad FROM rutas r INNER JOIN ciudades c ON c.id_ciudad=r.id_ciudad WHERE r.activa=1 ORDER BY r.id_ruta DESC')->fetchAll();
$titulo = 'Panel'; require __DIR__ . '/partials/inicio.php';
?>
<h1>Panel de gestión</h1><p>Sesión: <strong><?= htmlspecialchars($usuario['nombre']) ?></strong> · <?= htmlspecialchars($usuario['rol']) ?></p>
<?php if($mensaje): ?><p class="ok"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?><?php if($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<h2>Nueva ruta</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="accion" value="crear">
<label>Ciudad<select name="id_ciudad"><?php foreach($ciudades as $c): ?><option value="<?= (int)$c['id_ciudad'] ?>"><?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Título<input name="titulo" required maxlength="150"></label><label>Descripción<textarea name="descripcion" required></textarea></label>
<div class="rejilla"><label>Duración<input type="number" name="duracion_minutos" value="90" min="10" required></label><label>Distancia<input type="number" name="distancia_km" value="3" min="0.1" step="0.1" required></label><label>Dificultad<select name="dificultad"><option>fácil</option><option>media</option><option>difícil</option></select></label></div><button>Crear mediante API</button></form>
<h2>Rutas activas</h2><table><thead><tr><th>ID</th><th>Ruta</th><th>Ciudad</th><th>Dificultad</th><th>Acciones</th></tr></thead><tbody><?php foreach($rutas as $r): ?><tr><td><?= (int)$r['id_ruta'] ?></td><td><?= htmlspecialchars($r['titulo']) ?></td><td><?= htmlspecialchars($r['ciudad']) ?></td><td><?= htmlspecialchars($r['dificultad']) ?></td><td><a class="boton" href="editar_ruta.php?id_ruta=<?= (int)$r['id_ruta'] ?>">Editar</a><?php if($usuario['rol']==='admin'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id_ruta" value="<?= (int)$r['id_ruta'] ?>"><button class="peligro">Desactivar</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<?php require __DIR__ . '/partials/fin.php'; ?>
