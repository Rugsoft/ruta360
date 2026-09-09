# Ruta360

Ruta360 es una pequeña aplicación PHP que actúa como **consumidora de un servicio externo** (el tiempo actual de [Open-Meteo](https://open-meteo.com/)) y, también como **proveedora de su propio servicio**: publica el recurso `api/ruta.php`, que devuelve en JSON una ruta recomendada con su ciudad y sus puntos de interés.

## Cómo funciona

El navegador nunca habla con la API externa. El recorrido de la información es:

```
USUARIO ELIGE UNA CIUDAD
│ envía id_ciudad por GET
▼
RUTA360 VALIDA EL IDENTIFICADOR
│ (filter_input + consulta preparada)
▼
MYSQL DEVUELVE NOMBRE Y COORDENADAS
│
▼
meteorologia.php CONSULTA LA API (cURL)
│ respuesta JSON
▼
RUTA360 INTERPRETA LOS DATOS Y GENERA HTML
▼
NAVEGADOR
```

La elección del usuario no viaja directamente hasta la API: primero pasa por las comprobaciones de Ruta360 y por los datos que la aplicación considera válidos.

## Estructura del proyecto

```
Ruta360\
├── index.php              Portada: selector de ciudades activas
├── tiempo.php             Página de resultados (validación + servicio)
├── ver_ruta.php           Detalle de una ruta consumiendo la API propia
├── rutas.php              Listado de rutas que enlaza a ver_ruta.php
├── conexion.php           Conexión PDO a MySQL
├── estilos.css            Estilos (panel de instrumentos)├── api\
│   ├── ruta.php          Recurso JSON: ruta + ciudad + puntos de interés
│   ├── rutas.php         Colección JSON de rutas con filtros opcionales
│   └── ciudades.php      Colección JSON de ciudades activas (selector)
├── comprobar_curl.php     Comprobación de la extensión cURL
├── prueba_api.php         Prueba mínima de comunicación con la API
├── sql\
│   └── ruta360.sql        Script de creación de la base de datos
└── servicios\
    ├── meteorologia.php   Comunicación con Open-Meteo (cURL + JSON)
    └── cliente_rutas.php  Cliente HTTP de la API propia de ruta
```

**Responsabilidad de cada archivo**

| Archivo                      | Sí conoce                      | No necesita conocer       |
| ---------------------------- | ------------------------------ | ------------------------- |
| `servicios/meteorologia.php` | URL, cURL, HTTP y JSON         | HTML de la página         |
| `tiempo.php`                 | Resultado `ok`/`datos`/`error` | Opciones internas de cURL |
| `index.php`                  | Ciudades activas de MySQL      | Detalles de la API        |
| `ver_ruta.php`               | Resultado `ok`/`estado`/`datos` | MySQL de la API          |
| `rutas.php`                  | Rutas activas para enlazar     | Contrato JSON del recurso |
| `servicios/cliente_rutas.php` | URL, cURL, HTTP y contrato JSON | HTML de la página          |
| `api/ruta.php`               | Contrato JSON del recurso      | HTML de las páginas       |
| `api/rutas.php`              | Contrato JSON de la colección  | HTML de las páginas       |
| `api/ciudades.php`           | Contrato JSON de ciudades      | HTML de las páginas       |
| `estilos.css`                | Colores y presentación         | API y datos               |

## Instalación (entorno XAMPP)

1. Copia la carpeta del proyecto en `C:\xampp\htdocs\`.
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Importa la base de datos: abre phpMyAdmin y ejecuta el contenido de `sql/ruta360.sql` (crea la base `ruta360`, la tabla `ciudades` y 5 ciudades de ejemplo).
   - Desde consola: `mysql -u root --default-character-set=utf8mb4 < sql/ruta360.sql` (el charset es importante para conservar acentos).
4. Revisa `conexion.php` si tu usuario o contraseña de MySQL son distintos (por defecto, `root` sin contraseña).
5. Comprueba que cURL está disponible abriendo `http://localhost/curso-soc-php/Ruta360/comprobar_curl.php`.
6. Abre la aplicación: `http://localhost/curso-soc-php/Ruta360/index.php`.

## Datos internos y datos externos

| Dato                            | Origen                   | Control        |
| ------------------------------- | ------------------------ | -------------- |
| Nombre de la ciudad             | MySQL de Ruta360         | Nuestro        |
| Latitud y longitud              | MySQL de Ruta360         | Nuestro        |
| Temperatura, viento y dirección | API externa (Open-Meteo) | Del proveedor  |
| `id_ciudad` elegido             | Navegador                | Debe validarse |

## Validación en dos capas

El navegador solo envía un **identificador** (`id_ciudad`), nunca coordenadas: así Ruta360 garantiza que las coordenadas enviadas al proveedor son las suyas propias.

1. **Formato** — `filter_input(INPUT_GET, 'id_ciudad', FILTER_VALIDATE_INT)` rechaza texto, negativos y ausencia de parámetro.
2. **Negocio** — una consulta preparada comprueba que el identificador existe y está activo (`activa = 1`). Una ciudad desactivada deja de aparecer en el selector _y_ su URL antigua deja de funcionar.

La consulta preparada mantiene separada la sentencia SQL del dato recibido (prevención de inyección SQL).

## Contrato del servicio meteorológico

`obtenerTiempoActual(float $latitud, float $longitud): array` devuelve siempre la misma estructura:

| Clave   | Contenido                                                       |
| ------- | --------------------------------------------------------------- |
| `ok`    | `true` si se pueden usar los datos; `false` si hubo un problema |
| `datos` | Array de la API, o `null` cuando no hay datos válidos           |
| `error` | `null` cuando todo va bien, o un mensaje comprensible           |

Comprobaciones en orden: fallo de `curl_exec` (con timeouts de 5 s de conexión y 10 s totales) → código HTTP distinto de 200 → JSON inválido (`json_last_error`). Solo después de las tres la página intenta leer los datos.

## Pruebas manuales

| URL                          | Resultado esperado                          |
| ---------------------------- | ------------------------------------------- |
| `index.php`                  | Selector con las ciudades activas           |
| `tiempo.php` (sin parámetro) | "La ciudad seleccionada no es válida."      |
| `tiempo.php?id_ciudad=abc`   | "La ciudad seleccionada no es válida."      |
| `tiempo.php?id_ciudad=-2`    | "La ciudad seleccionada no es válida."      |
| `tiempo.php?id_ciudad=99999` | "La ciudad no existe o no está disponible." |
| `tiempo.php?id_ciudad=1`     | Tiempo de Barcelona                         |

Para ocultar una ciudad sin borrarla:

```sql
UPDATE ciudades SET activa = 0 WHERE nombre = 'Roma';
```

## Recurso propio: `api/ruta.php`

Ruta360 publica un recurso de lectura propio. La misma aplicación consume un servicio (Open-Meteo) y proporciona otro (el detalle de una ruta).

**URL de prueba**

```
http://localhost/curso-soc-php/Ruta360/api/ruta.php?id_ruta=1
```

(La ruta exacta depende de dónde tengas copiado el proyecto dentro de `htdocs`; el manual genérico usa `localhost/ruta360/`.)

| Parte          | Función                              |
| -------------- | ------------------------------------ |
| `api/ruta.php` | Punto de acceso al recurso           |
| `?`            | Separa la ruta de los parámetros     |
| `id_ruta`      | Nombre del parámetro recibido        |
| `1`            | Identificador del recurso solicitado |

**Contrato de respuesta**

| Clave   | Contenido                                                                                                                                        |
| ------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `ok`    | `true` si la ruta se devuelve; `false` en cualquier error                                                                                        |
| `datos` | Objeto con `id_ruta`, `titulo`, `descripcion`, `duracion_minutos`, `distancia_km`, `dificultad`, `numero_puntos`, `ciudad` (objeto) y `puntos_interes` (lista) |
| `error` | Solo en errores: mensaje comprensible, nunca detalles internos                                                                                   |

Ejemplo aproximado de respuesta correcta (HTTP 200):

```json
{
  "ok": true,
  "datos": {
    "id_ruta": 1,
    "titulo": "Barcelona modernista",
    "descripcion": "Un recorrido por algunos espacios esenciales del modernismo.",
    "duracion_minutos": 180,
    "distancia_km": 4.8,
    "dificultad": "media",
    "numero_puntos": 3,
    "ciudad": {
      "id_ciudad": 1,
      "nombre": "Barcelona",
      "pais": "España"
    },
    "puntos_interes": [
      {
        "id_punto": 1,
        "nombre": "Sagrada Família",
        "descripcion": "Inicio del recorrido.",
        "orden": 1
      }
    ]
  }
}
```

**Pruebas del recurso**

| URL                            | Estado esperado | Qué demuestra                     |
| ------------------------------ | --------------- | --------------------------------- |
| `api/ruta.php?id_ruta=1`       | 200             | El recurso existe y se serializa  |
| `api/ruta.php?id_ruta=999`     | 404             | El dato es válido, pero no existe |
| `api/ruta.php?id_ruta=abc`     | 400             | La validación rechaza texto       |
| `api/ruta.php?id_ruta=0`       | 400             | La validación aplica el rango     |
| `api/ruta.php` (sin parámetro) | 400             | El parámetro es obligatorio       |

Una ruta sin puntos devuelve `puntos_interes: []` con HTTP 200: la lista vacía es una respuesta válida, no un error.

## Colección de rutas: `api/rutas.php` (Manual 6)

Segundo recurso de la API: la colección de rutas activas, filtrable por ciudad (y por otros criterios). A diferencia del recurso individual, **cero coincidencias es una respuesta correcta**: 200 con `total: 0` y `datos: []`.

| | Recurso individual (`api/ruta.php`) | Colección (`api/rutas.php`) |
|---|---|---|
| Devuelve | Una ruta (detalle completo) | Una lista de rutas (resumen) |
| Sin coincidencia | 404 | 200 + lista vacía |
| Parámetro | `id_ruta` obligatorio | Filtros opcionales |

**URL de prueba**

```
http://localhost/curso-soc-php/Ruta360/api/rutas.php
```

**Filtros opcionales (combinables entre sí)**

| Filtro | Valores | Sin él / vacío | Inválido |
|---|---|---|---|
| `id_ciudad` | Entero ≥ 1 | Sin filtro | 400 |
| `duracion_maxima` (6.24) | Entero ≥ 1 (minutos) | Sin filtro | 400 |
| `dificultad` (6.27) | `facil`, `media`, `alta` (acepta el acento) | Sin filtro | 400 |
| `orden` (reto 6.29) | `titulo`, `duracion` | Orden por defecto (ciudad, título) | 400 |

**Contrato de la colección**

| Clave | Contenido |
|---|---|
| `ok` | `true` en el éxito; `false` en errores |
| `filtros` | Criterio aplicado por el servidor (`id_ciudad`, `duracion_maxima`, `dificultad`, `orden`; `null` si no se aplicó) |
| `total` | `count($rutas)`: elementos de esta respuesta, no de toda la BD |
| `datos` | Lista de resúmenes: `id_ruta`, `titulo`, `duracion_minutos`, `distancia_km`, `ciudad` (objeto) y `numero_puntos` |
| `error` | Solo en errores: mensaje comprensible |

Ejemplo con filtro (`api/rutas.php?id_ciudad=1`):

```json
{
  "ok": true,
  "filtros": {
    "id_ciudad": 1,
    "duracion_maxima": null,
    "dificultad": null,
    "orden": null
  },
  "total": 2,
  "datos": [
    {
      "id_ruta": 1,
      "titulo": "Barcelona modernista",
      "duracion_minutos": 180,
      "distancia_km": 4.8,
      "ciudad": {
        "id_ciudad": 1,
        "nombre": "Barcelona",
        "pais": "España"
      },
      "numero_puntos": 3
    }
  ]
}
```

**SQL dinámico controlado** — la consulta base (`INNER JOIN ciudades` + `LEFT JOIN puntos_interes` + `COUNT`/`GROUP BY`) es fija; cada filtro válido añade una condición fija (`AND r.id_ciudad = :id_ciudad`, etc.) y su parámetro preparado. **Nunca se concatena el valor recibido en el SQL**: ni en las condiciones ni en el `ORDER BY` (el parámetro `orden` solo elige entre fragmentos escritos por la aplicación).

El `LEFT JOIN` con `puntos_interes` conserva las rutas que todavía no tienen puntos (con `INNER JOIN` desaparecerían del listado).

**Pruebas de la colección (6.22 ampliada)**

| URL | Estado | Resultado |
|---|---|---|
| `api/rutas.php` | 200 | Todas las rutas activas con `numero_puntos` |
| `api/rutas.php?id_ciudad=1` | 200 | Solo rutas de Barcelona |
| `api/rutas.php?id_ciudad=999` | 200 | `total: 0` y `datos: []` (no es 404) |
| `api/rutas.php?id_ciudad=abc` | 400 | Filtro no válido |
| `api/rutas.php?id_ciudad=0` | 400 | Filtro fuera de rango |
| `api/rutas.php?duracion_maxima=150` | 200 | Excluye las de 180 y 210 min |
| `api/rutas.php?id_ciudad=1&duracion_maxima=150` | 200 | Ambos criterios a la vez |
| `api/rutas.php?dificultad=alta` | 200 | Solo París junto al Sena |
| `api/rutas.php?dificultad=imposible` | 400 | Valor no admitido |
| `api/rutas.php?orden=titulo` | 200 | Orden alfabético por título |
| `api/rutas.php?orden=duracion` | 200 | De menor a mayor duración |
| `api/rutas.php?orden=xyz` | 400 | Orden no permitido |

## Colección de ciudades: `api/ciudades.php` (reto 7.35)

Tercer recurso de la API: la lista de **ciudades activas** con los campos mínimos que necesita el selector de `rutas.php` (`id_ciudad`, `nombre`, `pais` — sin coordenadas ni datos que el selector no consume).

**URL de prueba**

```
http://localhost/curso-soc-php/Ruta360/api/ciudades.php
```

**Contrato** — `{ ok, total, datos: [{id_ciudad, nombre, pais}] }`. Una lista vacía es una respuesta correcta (200), no un error.

El selector de `rutas.php` se alimenta de este recurso mediante `obtenerCiudades()` (que reutiliza `solicitarJson()`, el código común de cURL del cliente). Si el recurso no responde, la página sigue funcionando: el selector muestra solo "Todas las ciudades" y el listado no se ve afectado.

**Pruebas del recurso**

| URL | Estado | Resultado |
|---|---|---|
| `api/ciudades.php` | 200 | Ciudades activas, ordenadas por nombre |
| Ciudad desactivada (`UPDATE ciudades SET activa = 0 ...`) | 200 | Desaparece de la lista y del selector |

## Consumir la propia API (Manual 5)

**`ver_ruta.php`** es un cliente de nuestra propia API: no consulta MySQL. Pide la ruta por HTTP a `api/ruta.php`, interpreta su contrato JSON y genera HTML.

Se producen dos peticiones: el navegador pide `ver_ruta.php` (HTML) y `ver_ruta.php` pide `api/ruta.php` (JSON).

**Responsabilidades**

| Archivo | Responsabilidad |
|---|---|
| `api/ruta.php` | Validar el recurso, consultar datos y responder JSON |
| `servicios/cliente_rutas.php` | Realizar la petición HTTP e interpretar la respuesta |
| `ver_ruta.php` | Elegir qué HTML mostrar al usuario |
| `conexion.php` | Solo lo necesita la API para acceder a MySQL |

**Contrato interno del cliente**

`obtenerRutaApi(int $idRuta): array` devuelve siempre la misma estructura:

| Clave | Contenido |
|---|---|
| `ok` | `true` si la ruta se obtuvo y cumple el contrato; `false` si hubo un problema |
| `estado` | Código HTTP, o `0` cuando no hubo ninguna respuesta (fallo de transporte) |
| `datos` | La ruta (con ciudad y puntos de interés) cuando hay éxito |
| `error` | Solo en errores: mensaje comprensible, nunca detalles internos |

**Capas de error (en orden)**

1. **Transporte** — cURL no pudo conectarse → `estado: 0`, "No se ha podido contactar con el servicio."
2. **JSON** — el cuerpo no es un JSON válido → "El servicio ha devuelto una respuesta no válida."
3. **Contrato** — estado distinto de 200 o `ok` falso → el error de la API, o uno genérico
4. **Datos** — falta el campo `datos` → "La respuesta no contiene los datos esperados."

**Pruebas (apartado 5.25)**

| URL | Resultado esperado | Capa que responde |
|---|---|---|
| `ver_ruta.php?id_ruta=1` | Página completa | API + cliente |
| `ver_ruta.php?id_ruta=999` | "La ruta no existe o no está disponible." | API: 404 |
| `ver_ruta.php?id_ruta=abc` | "Selecciona una ruta válida." | Página cliente |
| API devuelve texto incorrecto | "El servicio ha devuelto una respuesta no válida." | Cliente HTTP |
| No hay conexión | "No se ha podido contactar con el servicio." | cURL / transporte |

`ver_ruta.php` y `rutas.php`: URLs con la ruta real del proyecto:

```
http://localhost/curso-soc-php/Ruta360/ver_ruta.php?id_ruta=1
http://localhost/curso-soc-php/Ruta360/rutas.php
```

## Tecnologías

- **PHP 8** con cURL y PDO (`pdo_mysql`)
- **MySQL** (charset `utf8mb4`)
- **HTML/CSS** sin frameworks
- API externa: [Open-Meteo Forecast API](https://open-meteo.com/) (no requiere clave)
