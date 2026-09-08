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
├── conexion.php           Conexión PDO a MySQL
├── estilos.css            Estilos (panel de instrumentos)
├── api\
│   └── ruta.php           Recurso JSON: ruta + ciudad + puntos de interés
├── comprobar_curl.php     Comprobación de la extensión cURL
├── prueba_api.php         Prueba mínima de comunicación con la API
├── sql\
│   └── ruta360.sql        Script de creación de la base de datos
└── servicios\
    └── meteorologia.php   Comunicación con Open-Meteo (cURL + JSON)
```

**Responsabilidad de cada archivo**

| Archivo                      | Sí conoce                      | No necesita conocer       |
| ---------------------------- | ------------------------------ | ------------------------- |
| `servicios/meteorologia.php` | URL, cURL, HTTP y JSON         | HTML de la página         |
| `tiempo.php`                 | Resultado `ok`/`datos`/`error` | Opciones internas de cURL |
| `index.php`                  | Ciudades activas de MySQL      | Detalles de la API        |
| `api/ruta.php`               | Contrato JSON del recurso      | HTML de las páginas       |
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

## Tecnologías

- **PHP 8** con cURL y PDO (`pdo_mysql`)
- **MySQL** (charset `utf8mb4`)
- **HTML/CSS** sin frameworks
- API externa: [Open-Meteo Forecast API](https://open-meteo.com/) (no requiere clave)
