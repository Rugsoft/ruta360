<?php
// Colección de rutas.
// - GET    api/rutas.php (opcionalmente ?id_ciudad=, ?duracion_maxima=,
//   ?dificultad= y ?orden=): devuelve la colección filtrada. Una
//   colección sin coincidencias responde 200 con una lista vacía.
// - POST   api/rutas.php: crea una ruta a partir de un cuerpo JSON y
//   responde 201 con el identificador generado.
// - PUT    api/rutas.php?id_ruta=: sustituye la representación completa.
// - PATCH  api/rutas.php?id_ruta=: actualiza parcialmente los campos enviados.
// - DELETE api/rutas.php?id_ruta=: retira la ruta mediante borrado lógico.
// - Cualquier otro método responde 405 con la cabecera Allow.

require_once __DIR__ . '/../conexion.php';

header('Content-Type: application/json; charset=utf-8');

function responderJson(int $estado, array $contenido): never
{
    http_response_code($estado);
    echo json_encode(
        $contenido,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
    exit;
}

// ------------------------------------------------------------------
// Despacho según el método HTTP. La URL representa el recurso
// "rutas"; el verbo expresa qué queremos hacer con él.
// ------------------------------------------------------------------

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    listarRutas($pdo);
}

if ($metodo === 'POST') {
    crearRuta($pdo);
}

if ($metodo === 'PUT') {
    actualizarRutaCompleta($pdo);
}

if ($metodo === 'PATCH') {
    actualizarRutaParcial($pdo);
}

if ($metodo === 'DELETE') {
    eliminarRuta($pdo);
}

header('Allow: GET, POST, PUT, PATCH, DELETE');
responderJson(405, [
    'ok' => false,
    'mensaje' => 'Método no permitido.',
    'error' => 'Método no permitido.'
]);

// ==================================================================
// GET · Colección con filtros opcionales.
// ==================================================================

function listarRutas(PDO $pdo): never
{
    // --------------------------------------------------------------
    // Validación de filtros opcionales.
    // --------------------------------------------------------------

    // id_ciudad (opcional): ausente o vacío significa "sin filtro".
    $idCiudadTexto = filter_input(INPUT_GET, 'id_ciudad');
    $idCiudad = null;
    if ($idCiudadTexto !== null && $idCiudadTexto !== '') {
        $idCiudad = filter_var(
            $idCiudadTexto,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($idCiudad === false) {
            responderJson(400, [
                'ok' => false,
                'error' => 'El filtro id_ciudad no es válido.'
            ]);
        }
    }

    // duracion_maxima (opcional): entero positivo.
    $duracionMaximaTexto = filter_input(INPUT_GET, 'duracion_maxima');
    $duracionMaxima = null;
    if ($duracionMaximaTexto !== null && $duracionMaximaTexto !== '') {
        $duracionMaxima = filter_var(
            $duracionMaximaTexto,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($duracionMaxima === false) {
            responderJson(400, [
                'ok' => false,
                'error' => 'El filtro duracion_maxima no es válido.'
            ]);
        }
    }

    // dificultad (opcional): solo facil, media o alta.
    $dificultadTexto = filter_input(INPUT_GET, 'dificultad');
    $dificultad = null;
    if ($dificultadTexto !== null && $dificultadTexto !== '') {
        $dificultadTexto = strtolower(trim($dificultadTexto));
        // Normalizamos el acento: aceptamos tanto "facil" como "fácil".
        $dificultadTexto = str_replace('facil', 'fácil', $dificultadTexto);
        if (!in_array($dificultadTexto, ['fácil', 'media', 'alta'], true)) {
            responderJson(400, [
                'ok' => false,
                'error' => 'El filtro dificultad no es válido.'
            ]);
        }
        $dificultad = $dificultadTexto;
    }

    // orden (opcional): solo titulo o duracion.
    // Nunca se concatena el texto recibido: cada valor permitido elige
    // un fragmento ORDER BY fijo escrito por la aplicación.
    $ordenTexto = filter_input(INPUT_GET, 'orden');
    $ordenSql = 'c.nombre, r.titulo'; // Orden por defecto.
    if ($ordenTexto !== null && $ordenTexto !== '') {
        $ordenSql = match ($ordenTexto) {
            'titulo' => 'r.titulo, c.nombre',
            'duracion' => 'r.duracion_minutos ASC, r.titulo',
            default => null,
        };
        if ($ordenSql === null) {
            responderJson(400, [
                'ok' => false,
                'error' => 'El filtro orden no es válido.'
            ]);
        }
    }

    // --------------------------------------------------------------
    // Consulta con SQL dinámico controlado: solo fragmentos fijos; los
    // valores del usuario viajan siempre en marcadores preparados.
    // --------------------------------------------------------------

    try {
        $sql = 'SELECT
            r.id_ruta,
            r.titulo,
            r.duracion_minutos,
            r.distancia_km,
            c.id_ciudad,
            c.nombre AS ciudad,
            c.pais,
            COUNT(p.id_punto) AS numero_puntos
        FROM rutas r
        INNER JOIN ciudades c
            ON c.id_ciudad = r.id_ciudad
        LEFT JOIN puntos_interes p
            ON p.id_ruta = r.id_ruta
        WHERE r.activa = 1
            AND c.activa = 1';

        $parametros = [];

        if ($idCiudad !== null) {
            $sql .= ' AND r.id_ciudad = :id_ciudad';
            $parametros['id_ciudad'] = $idCiudad;
        }

        if ($duracionMaxima !== null) {
            $sql .= ' AND r.duracion_minutos <= :duracion_maxima';
            $parametros['duracion_maxima'] = $duracionMaxima;
        }

        if ($dificultad !== null) {
            $sql .= ' AND r.dificultad = :dificultad';
            $parametros['dificultad'] = $dificultad;
        }

        $sql .= ' GROUP BY r.id_ruta, r.titulo, r.duracion_minutos,
            r.distancia_km, c.id_ciudad, c.nombre, c.pais
        ORDER BY ' . $ordenSql;

        $consulta = $pdo->prepare($sql);
        $consulta->execute($parametros);
        $filas = $consulta->fetchAll();

        // ----------------------------------------------------------
        // Construcción del contrato: cada elemento es un resumen; el
        // detalle completo sigue estando en api/ruta.php?id_ruta=...
        // ----------------------------------------------------------
        $rutas = array_map(
            static fn(array $fila): array => [
                'id_ruta' => (int) $fila['id_ruta'],
                'titulo' => $fila['titulo'],
                'duracion_minutos' => (int) $fila['duracion_minutos'],
                'distancia_km' => (float) $fila['distancia_km'],
                'ciudad' => [
                    'id_ciudad' => (int) $fila['id_ciudad'],
                    'nombre' => $fila['ciudad'],
                    'pais' => $fila['pais']
                ],
                'numero_puntos' => (int) $fila['numero_puntos']
            ],
            $filas
        );

        // Cero coincidencias no es un error: la colección existe y la
        // petición se ha procesado correctamente.
        responderJson(200, [
            'ok' => true,
            'filtros' => [
                'id_ciudad' => $idCiudad,
                'duracion_maxima' => $duracionMaxima,
                'dificultad' => $dificultad,
                'orden' => $ordenTexto ?? null
            ],
            'total' => count($rutas),
            'datos' => $rutas
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, [
            'ok' => false,
            'error' => 'No se ha podido consultar la colección.'
        ]);
    }
}

// ==================================================================
// POST · Creación de una ruta.
// ==================================================================

/**
 * Valida los datos del cuerpo JSON. Devuelve un array de errores
 * indexado por campo; si queda vacío, todo cumple el contrato.
 */
function validarRuta(array $datos): array
{
    $errores = [];

    $idCiudad = filter_var(
        $datos['id_ciudad'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($idCiudad === false) {
        $errores['id_ciudad'] = 'Selecciona una ciudad válida.';
    }

    $titulo = trim((string) ($datos['titulo'] ?? ''));
    $longitudTitulo = mb_strlen($titulo);
    if ($longitudTitulo < 5 || $longitudTitulo > 120) {
        $errores['titulo'] = 'El título debe tener entre 5 y 120 caracteres.';
    }

    $descripcion = trim((string) ($datos['descripcion'] ?? ''));
    $longitudDescripcion = mb_strlen($descripcion);
    if ($longitudDescripcion < 10 || $longitudDescripcion > 1000) {
        $errores['descripcion'] =
            'La descripción debe tener entre 10 y 1000 caracteres.';
    }

    $duracion = filter_var(
        $datos['duracion_minutos'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 15, 'max_range' => 1440]]
    );
    if ($duracion === false) {
        $errores['duracion_minutos'] =
            'La duración debe estar entre 15 y 1440 minutos.';
    }

    $distancia = filter_var($datos['distancia_km'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($distancia === false || $distancia <= 0 || $distancia > 1000) {
        $errores['distancia_km'] =
            'La distancia debe ser mayor que 0 y máximo 1000 km.';
    }

    // Dificultad opcional: si llega, solo facil, media o alta
    // (aceptamos también la forma con acento).
    $dificultad = trim((string) ($datos['dificultad'] ?? ''));
    if ($dificultad !== '') {
        $dificultad = str_replace('facil', 'fácil', strtolower($dificultad));
        if (!in_array($dificultad, ['fácil', 'media', 'alta'], true)) {
            $errores['dificultad'] =
                'La dificultad solo puede ser facil, media o alta.';
        }
    }

    return $errores;
}

/**
 * Comprueba que el identificador referencia una ciudad existente.
 * El tipo correcto no implica una referencia válida.
 */
function existeCiudad(PDO $pdo, int $idCiudad): bool
{
    $sql = 'SELECT 1
        FROM ciudades
        WHERE id_ciudad = :id_ciudad
        LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_ciudad' => $idCiudad]);
    return $stmt->fetchColumn() !== false;
}

/**
 * Crea una ruta a partir del cuerpo JSON de la petición.
 */
function crearRuta(PDO $pdo): never
{
    $datos = leerJson();

    // Todos los errores se devuelven juntos.
    $errores = validarRuta($datos);
    if ($errores !== []) {
        responderJson(422, [
            'ok' => false,
            'mensaje' => 'Revisa los datos enviados.',
            'error' => 'Revisa los datos enviados.',
            'errores' => $errores
        ]);
    }

    $idCiudad = (int) $datos['id_ciudad'];
    if (!existeCiudad($pdo, $idCiudad)) {
        responderJson(404, [
            'ok' => false,
            'mensaje' => 'La ciudad indicada no existe.',
            'error' => 'La ciudad indicada no existe.'
        ]);
    }

    try {
        $sql = 'INSERT INTO rutas
            (id_ciudad, titulo, descripcion,
            duracion_minutos, distancia_km, dificultad)
        VALUES
            (:id_ciudad, :titulo, :descripcion,
            :duracion_minutos, :distancia_km, :dificultad)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_ciudad' => $idCiudad,
            'titulo' => trim((string) $datos['titulo']),
            'descripcion' => trim((string) $datos['descripcion']),
            'duracion_minutos' => (int) $datos['duracion_minutos'],
            'distancia_km' => (float) $datos['distancia_km'],
            'dificultad' => trim((string) ($datos['dificultad'] ?? '')) !== ''
                ? str_replace(
                    'facil',
                    'fácil',
                    strtolower(trim((string) $datos['dificultad']))
                )
                : 'media'
        ]);

        $idRuta = (int) $pdo->lastInsertId();
        $url = 'ver_ruta.php?id_ruta=' . $idRuta;
        header('Location: ' . $url);
        responderJson(201, [
            'ok' => true,
            'mensaje' => 'Ruta creada correctamente.',
            'datos' => [
                'id_ruta' => $idRuta,
                'url' => $url
            ]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, [
            'ok' => false,
            'mensaje' => 'No se ha podido crear la ruta.',
            'error' => 'No se ha podido crear la ruta.'
        ]);
    }
}

// ==================================================================
// Funciones comunes de lectura y validación (Manual 9)
// ==================================================================

/**
 * 9.8 Validar el identificador de la URL
 */
function obtenerIdRuta(): int
{
    $idRuta = filter_input(
        INPUT_GET,
        'id_ruta',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($idRuta === false || $idRuta === null) {
        responderJson(400, [
            'ok' => false,
            'mensaje' => 'El identificador de la ruta no es válido.',
            'error' => 'El identificador de la ruta no es válido.'
        ]);
    }
    return $idRuta;
}

/**
 * 9.9 Leer JSON con una función común
 */
function leerJson(): array
{
    $cuerpo = file_get_contents('php://input');
    if ($cuerpo === false || trim($cuerpo) === '') {
        responderJson(400, [
            'ok' => false,
            'mensaje' => 'El cuerpo de la petición está vacío.',
            'error' => 'El cuerpo de la petición está vacío.'
        ]);
    }

    $datos = json_decode($cuerpo, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        responderJson(400, [
            'ok' => false,
            'mensaje' => 'El cuerpo no contiene JSON válido.',
            'error' => 'El cuerpo no contiene JSON válido.'
        ]);
    }

    return $datos;
}

/**
 * 9.10 Buscar una ruta
 */
function buscarRuta(PDO $pdo, int $idRuta): ?array
{
    $sql = 'SELECT id_ruta, id_ciudad, titulo, descripcion,
            duracion_minutos, distancia_km, dificultad, activa
        FROM rutas
        WHERE id_ruta = :id_ruta
        LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_ruta' => $idRuta]);
    $ruta = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ruta === false ? null : $ruta;
}

/**
 * 9.10 Comprobar que la ruta existe y está activa para edición
 */
function exigirRutaEditable(PDO $pdo, int $idRuta): array
{
    $ruta = buscarRuta($pdo, $idRuta);
    if ($ruta === null) {
        responderJson(404, [
            'ok' => false,
            'mensaje' => 'La ruta indicada no existe.',
            'error' => 'La ruta indicada no existe.'
        ]);
    }
    if (!(bool) $ruta['activa']) {
        responderJson(409, [
            'ok' => false,
            'mensaje' => 'La ruta está inactiva y no puede editarse.',
            'error' => 'La ruta está inactiva y no puede editarse.'
        ]);
    }
    return $ruta;
}

// ==================================================================
// PUT · Actualización completa (Manual 9)
// ==================================================================

/**
 * 9.11 Validar PUT como actualización completa
 */
function validarPut(array $datos): array
{
    $campos = [
        'id_ciudad', 'titulo', 'descripcion',
        'duracion_minutos', 'distancia_km'
    ];
    $errores = [];
    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $datos)) {
            $errores[$campo] = 'Este campo es obligatorio en PUT.';
        }
    }
    return $errores + validarRuta($datos);
}

/**
 * 9.12 Ejecutar el UPDATE completo
 */
function ejecutarUpdateCompleto(
    PDO $pdo, int $idRuta, array $datos
): void {
    $columnas = [
        'id_ciudad = :id_ciudad',
        'titulo = :titulo',
        'descripcion = :descripcion',
        'duracion_minutos = :duracion_minutos',
        'distancia_km = :distancia_km'
    ];
    $parametros = [
        'id_ciudad' => (int) $datos['id_ciudad'],
        'titulo' => trim((string) $datos['titulo']),
        'descripcion' => trim((string) $datos['descripcion']),
        'duracion_minutos' => (int) $datos['duracion_minutos'],
        'distancia_km' => (float) $datos['distancia_km'],
        'id_ruta' => $idRuta
    ];
    if (isset($datos['dificultad']) && trim((string) $datos['dificultad']) !== '') {
        $columnas[] = 'dificultad = :dificultad';
        $parametros['dificultad'] = str_replace(
            'facil',
            'fácil',
            strtolower(trim((string) $datos['dificultad']))
        );
    }
    $sql = 'UPDATE rutas
        SET ' . implode(', ', $columnas) . '
        WHERE id_ruta = :id_ruta AND activa = 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
}

/**
 * 9.13 La función PUT completa
 */
function actualizarRutaCompleta(PDO $pdo): never
{
    $idRuta = obtenerIdRuta();
    exigirRutaEditable($pdo, $idRuta);
    $datos = leerJson();
    $errores = validarPut($datos);
    if ($errores !== []) {
        responderJson(422, [
            'ok' => false,
            'mensaje' => 'Revisa los datos enviados.',
            'error' => 'Revisa los datos enviados.',
            'errores' => $errores
        ]);
    }
    if (!existeCiudad($pdo, (int) $datos['id_ciudad'])) {
        responderJson(404, [
            'ok' => false,
            'mensaje' => 'La ciudad indicada no existe.',
            'error' => 'La ciudad indicada no existe.'
        ]);
    }
    try {
        ejecutarUpdateCompleto($pdo, $idRuta, $datos);
        responderJson(200, [
            'ok' => true,
            'mensaje' => 'Ruta actualizada correctamente.',
            'datos' => buscarRuta($pdo, $idRuta)
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, [
            'ok' => false,
            'mensaje' => 'No se ha podido actualizar la ruta.',
            'error' => 'No se ha podido actualizar la ruta.'
        ]);
    }
}

// ==================================================================
// PATCH · Actualización parcial (Manual 9)
// ==================================================================

/**
 * 9.14 Validar PATCH campo por campo
 */
function validarPatch(array $datos): array
{
    $permitidos = [
        'id_ciudad', 'titulo', 'descripcion',
        'duracion_minutos', 'distancia_km', 'dificultad'
    ];
    $errores = [];
    if ($datos === []) {
        return ['general' => 'Envía al menos un campo para modificar.'];
    }
    foreach (array_keys($datos) as $campo) {
        if (!in_array($campo, $permitidos, true)) {
            $errores[$campo] = 'Este campo no se puede modificar.';
        }
    }
    $completos = array_intersect_key($datos, array_flip($permitidos));
    $base = [
        'id_ciudad' => 1,
        'titulo' => 'Título válido',
        'descripcion' => 'Descripción válida para comprobar el contrato.',
        'duracion_minutos' => 60,
        'distancia_km' => 1,
        'dificultad' => 'media'
    ];
    $erroresCompletos = validarRuta(array_replace($base, $completos));
    return $errores + array_intersect_key($erroresCompletos, $completos);
}

/**
 * 9.15 Construir un UPDATE dinámico seguro
 */
function ejecutarPatch(PDO $pdo, int $idRuta, array $datos): void
{
    $columnas = [
        'id_ciudad', 'titulo', 'descripcion',
        'duracion_minutos', 'distancia_km', 'dificultad'
    ];
    $set = [];
    $parametros = ['id_ruta' => $idRuta];
    foreach ($columnas as $columna) {
        if (array_key_exists($columna, $datos)) {
            $set[] = $columna . ' = :' . $columna;
            if ($columna === 'id_ciudad' || $columna === 'duracion_minutos') {
                $parametros[$columna] = (int) $datos[$columna];
            } elseif ($columna === 'distancia_km') {
                $parametros[$columna] = (float) $datos[$columna];
            } elseif ($columna === 'dificultad') {
                $parametros[$columna] = str_replace(
                    'facil',
                    'fácil',
                    strtolower(trim((string) $datos[$columna]))
                );
            } else {
                $parametros[$columna] = trim((string) $datos[$columna]);
            }
        }
    }
    $sql = 'UPDATE rutas SET ' . implode(', ', $set) .
        ' WHERE id_ruta = :id_ruta AND activa = 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
}

/**
 * 9.16 La función PATCH completa
 */
function actualizarRutaParcial(PDO $pdo): never
{
    $idRuta = obtenerIdRuta();
    exigirRutaEditable($pdo, $idRuta);
    $datos = leerJson();
    $errores = validarPatch($datos);
    if ($errores !== []) {
        responderJson(422, [
            'ok' => false,
            'mensaje' => 'Revisa los datos enviados.',
            'error' => 'Revisa los datos enviados.',
            'errores' => $errores
        ]);
    }
    if (array_key_exists('id_ciudad', $datos) &&
        !existeCiudad($pdo, (int) $datos['id_ciudad'])) {
        responderJson(404, [
            'ok' => false,
            'mensaje' => 'La ciudad indicada no existe.',
            'error' => 'La ciudad indicada no existe.'
        ]);
    }
    try {
        ejecutarPatch($pdo, $idRuta, $datos);
        responderJson(200, [
            'ok' => true,
            'mensaje' => 'Ruta modificada correctamente.',
            'datos' => buscarRuta($pdo, $idRuta)
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, [
            'ok' => false,
            'mensaje' => 'No se ha podido modificar la ruta.',
            'error' => 'No se ha podido modificar la ruta.'
        ]);
    }
}

// ==================================================================
// DELETE · Borrado lógico (Manual 9)
// ==================================================================

/**
 * 9.18 Implementar DELETE
 */
function eliminarRuta(PDO $pdo): never
{
    $idRuta = obtenerIdRuta();
    $ruta = buscarRuta($pdo, $idRuta);
    if ($ruta === null) {
        responderJson(404, [
            'ok' => false,
            'mensaje' => 'La ruta indicada no existe.',
            'error' => 'La ruta indicada no existe.'
        ]);
    }
    if (!(bool) $ruta['activa']) {
        responderJson(200, [
            'ok' => true,
            'mensaje' => 'La ruta ya estaba eliminada.'
        ]);
    }
    try {
        $stmt = $pdo->prepare(
            'UPDATE rutas SET activa = 0 WHERE id_ruta = :id_ruta'
        );
        $stmt->execute(['id_ruta' => $idRuta]);
        responderJson(200, [
            'ok' => true,
            'mensaje' => 'Ruta eliminada correctamente.',
            'datos' => ['id_ruta' => $idRuta]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, [
            'ok' => false,
            'mensaje' => 'No se ha podido eliminar la ruta.',
            'error' => 'No se ha podido eliminar la ruta.'
        ]);
    }
}
