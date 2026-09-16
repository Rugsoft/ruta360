# 05 · Línea base de la aplicación (Actividad guiada 1.30)

**Regla aplicada:** se ha recorrido la aplicación **sin editar ni un solo archivo existente**. El único fichero temporal creado (`comprobar_requisitos.php`, para la actividad 1.32) se eliminó tras registrar los resultados.

**Fecha:** 14/09/2026 · **Entorno:** XAMPP local (Apache + MariaDB en el mismo equipo)

## 1. Recorrido manual previsto

| Prueba | URL o acción | Resultado | Estado |
|---|---|---|---|
| Inicio | `index.php` | Portada con selector de ciudades activas | Pendiente |
| Listado | `rutas.php` | Listado de rutas con filtros y enlaces a la ficha | Pendiente |
| Ficha | `ver_ruta.php?id_ruta=1` | Detalle completo + meteorología | Pendiente |
| API | `api/ruta.php?id_ruta=1` | JSON de la ruta | Pendiente |
| Zona privada | `nueva_ruta.php` sin sesión | Acceso denegado / redirección | Pendiente |

## 2. Resultados observados (evidencias reales)

Comprobación automatizada con `curl` sobre `http://localhost/curso-soc-php/Ruta360/`:

| # | URL | Código HTTP | Observación | Estado |
|---|---|---|---|---|
| 1 | `index.php` | 200 | Portada operativa | ✅ |
| 2 | `rutas.php` | 200 | Listado de rutas activas | ✅ |
| 3 | `ver_ruta.php?id_ruta=1` | 200 | Ficha completa con meteorología y puntos de interés | ✅ |
| 4 | `ver_ruta.php?id_ruta=999` | 200 | Error controlado: "No se ha podido cargar la ruta" (no expone detalles internos) | ✅ |
| 5 | `ver_ruta.php?id_ruta=abc` | 200 | Error controlado: "Selecciona una ruta válida." (validación de entrada) | ✅ |
| 6 | `api/ruta.php?id_ruta=1` | 200 | JSON con `ok: true` y datos de la ruta | ✅ |
| 7 | `api/ruta.php?id_ruta=999` | 404 | Contrato correcto: recurso inexistente | ✅ |
| 8 | `api/rutas.php` | 200 | Colección con `ok`, `filtros`, `total`, `datos` | ✅ |
| 9 | `api/ciudades.php` | 200 | Ciudades activas para el selector | ✅ |
| 10 | `tiempo.php?id_ciudad=1` | 200 | Página meteorológica clásica operativa | ✅ |
| 11 | `login.php` | 200 | Formulario de acceso (extranet) | ✅ |
| 12 | `nueva_ruta.php` (sin sesión) | 302 | Redirección a `login.php`: zona protegida | ✅ |
| 13 | `estilos.css` | 200 | Recurso estático servido | ✅ |

## 3. Hallazgos del recorrido (riesgos detectados)

| # | URL | Código HTTP | Hallazgo |
|---|---|---|---|
| H1 | `prueba_seguridad.php` | **200** | Script de pruebas **ejecutable por cualquier visitante**; ejecuta operaciones reales de escritura (creación/eliminación de rutas) contra la API |
| H2 | `comprobar_curl.php` | **200** | Script auxiliar ejecutable públicamente (bajo riesgo, pero no debería ser público) |
| H3 | `sql/ruta360.sql` | **200** | El script SQL completo (estructura + datos + esquema de la base) **es descargable desde el navegador** |
| H4 | `conexion.php` | 200 (página en blanco) | Se ejecuta sin error; su contenido no se muestra (PHP lo interpreta), pero su sola presencia en la raíz pública es una exposición a revisar |
| H5 | `storage/cache/` | — | Directorio de caché accesible si se adivina la ruta; sin listado de directorio activo |

## 4. Comportamiento ante fallos externos

- `ver_ruta.php?id_ruta=1` muestra la ficha completa aunque la API meteorológica no responda (respuesta degradada del Manual 11), mostrando aviso en lugar de romper la página.
- El listado `rutas.php` consume la API propia por HTTP: si la API cae, el listado muestra el error de contrato sin detalles internos.

## 5. Conclusión de la línea base

**Todas las funciones principales están operativas en el entorno local**: catálogo, fichas, API de lectura, meteorología resiliente, login y protecciones de sesión. La línea base es sana en lo funcional; las incidencias detectadas (H1–H5) son de **exposición de archivos auxiliares y de configuración**, no de lógica de negocio, y quedan registradas como riesgos en el informe de auditoría sin haberse corregido todavía (regla del manual: primero observar y registrar; modificar llegará en manuales posteriores).

## 6. Pruebas manuales restantes (requieren navegador)

- Iniciar sesión con una cuenta de prueba y verificar el rol asignado en `nueva_ruta.php` / `editar_ruta.php`.
- Comprobar visualmente el estilo "panel de instrumentos" en el navegador.
- Verificar el flujo completo de creación de ruta con token CSRF en formulario.
