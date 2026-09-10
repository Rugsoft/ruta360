<?php
// servicios/RepositorioRuta.php · Consulta de información interna de rutas en MySQL (Manual 12.2, 12.19)
declare(strict_types=1);

final class RepositorioRuta
{
    public function __construct(private PDO $pdo) {}

    public function buscarPorId(int $idRuta): ?array
    {
        $stmtRuta = null;
        $stmtPuntos = null;

        try {
            $sqlRuta = 'SELECT
                    r.id_ruta,
                    r.titulo,
                    r.descripcion,
                    r.duracion_minutos,
                    r.distancia_km,
                    r.dificultad,
                    c.id_ciudad,
                    c.nombre AS ciudad,
                    c.pais,
                    c.latitud,
                    c.longitud
                FROM rutas r
                INNER JOIN ciudades c
                    ON c.id_ciudad = r.id_ciudad
                WHERE r.id_ruta = :id_ruta
                    AND r.activa = 1
                    AND c.activa = 1
                LIMIT 1';

            $stmtRuta = $this->pdo->prepare($sqlRuta);
            $stmtRuta->execute(['id_ruta' => $idRuta]);
            $ruta = $stmtRuta->fetch(PDO::FETCH_ASSOC);

            if ($ruta === false) {
                return null;
            }

            $sqlPuntos = 'SELECT id_punto, nombre, descripcion, orden
                FROM puntos_interes
                WHERE id_ruta = :id_ruta
                ORDER BY orden';
            $stmtPuntos = $this->pdo->prepare($sqlPuntos);
            $stmtPuntos->execute(['id_ruta' => $idRuta]);
            $puntos = $stmtPuntos->fetchAll(PDO::FETCH_ASSOC);

            return [
                'id_ruta' => (int) $ruta['id_ruta'],
                'id_ciudad' => (int) $ruta['id_ciudad'],
                'titulo' => (string) $ruta['titulo'],
                'descripcion' => (string) $ruta['descripcion'],
                'duracion_minutos' => (int) $ruta['duracion_minutos'],
                'distancia_km' => (float) $ruta['distancia_km'],
                'dificultad' => (string) $ruta['dificultad'],
                'numero_puntos' => count($puntos),
                'ciudad' => [
                    'id_ciudad' => (int) $ruta['id_ciudad'],
                    'nombre' => (string) $ruta['ciudad'],
                    'pais' => (string) $ruta['pais'],
                    'latitud' => (float) $ruta['latitud'],
                    'longitud' => (float) $ruta['longitud']
                ],
                'puntos_interes' => array_map(
                    static fn(array $p): array => [
                        'id_punto' => (int) $p['id_punto'],
                        'nombre' => (string) $p['nombre'],
                        'descripcion' => (string) $p['descripcion'],
                        'orden' => (int) $p['orden']
                    ],
                    $puntos
                )
            ];
        } finally {
            $stmtRuta = null;
            $stmtPuntos = null;
        }
    }
}
