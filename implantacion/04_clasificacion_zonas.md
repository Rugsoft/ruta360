# 04 · Clasificación de zonas: Internet, extranet e intranet (Actividad guiada 1.33)

**Criterio:** una página puede ser de *Internet* por estar publicada y de *extranet* por exigir identificación. La clasificación usa las URLs reales del proyecto, no las propuestas del manual (aún no existe `public/` ni panel de administración separado).

**Fecha:** 14/09/2026

## Clasificación de los recursos reales

| Recurso | Internet | Extranet | Intranet | Justificación |
|---|---|---|---|---|
| `index.php` (portada) | ✅ | — | — | Consulta pública sin autenticación |
| `rutas.php` (listado) | ✅ | — | — | Catálogo público de rutas activas |
| `ver_ruta.php` (ficha) | ✅ | — | — | Detalle público; edición solo con sesión |
| `tiempo.php` (meteo) | ✅ | — | — | Consulta pública meteorológica |
| `api/ruta.php` (GET) | ✅ | — | — | API pública de lectura |
| `api/rutas.php` (GET) | ✅ | — | — | API pública de consulta |
| `api/ciudades.php` (GET) | ✅ | — | — | Alimenta el selector público |
| `api/rutas.php` (POST/PUT/PATCH/DELETE) | — | ✅ | — | Cliente autenticado con token Bearer + RBAC |
| `login.php` | ✅* | ✅ | — | Puerta de la extranet: pública para identificarse |
| `logout.php` | — | ✅ | — | Requiere sesión activa |
| `nueva_ruta.php` | — | ✅ | — | Editor/admin con sesión + CSRF |
| `editar_ruta.php` | — | ✅ | — | Editor/admin con sesión + CSRF |
| `eliminar_ruta.php` | — | ✅ | — | Solo admin con sesión + CSRF |
| `conexion.php` | — | — | ✅ | Credenciales de base de datos; solo para el código |
| `seguridad_web.php` | — | — | ✅ | Librería interna cargada por `require` |
| `servicios/*.php` | — | — | ✅ | Lógica de negocio interna |
| `sql/ruta360.sql` | — | — | ✅ | Esquema y datos: solo operación técnica (**hoy expuesto**) |
| `storage/cache/` | — | — | ✅ | Datos de caché internos |
| `prueba_*.php`, `comprobar_*.php` | — | — | ✅ | Herramientas técnicas (**hoy ejecutables por cualquiera**) |
| `api_tokens` (tabla) | — | — | ✅ | Credenciales de la API; solo BD |
| `phpinfo` / diagnóstico | — | — | ✅ | Solo durante depuración controlada y eliminado después |

\* `login.php` es de acceso público por necesidad (si no, nadie podría autenticarse), pero su función pertenece a la extranet.

## Lectura de la clasificación

- **Internet (7 recursos):** portada, listado, ficha, página de tiempo y los tres endpoints GET de la API. Ninguno expone información privada y todos validan la entrada (`filter_input` + consultas preparadas).
- **Extranet (5 recursos + verbos de escritura):** la parte autenticada del proyecto. Combina dos mecanismos: **sesión web + CSRF + roles** para las páginas y **token Bearer + RBAC** (`lector/editor/admin`) para la API de escritura. Verificado en la línea base: `nueva_ruta.php` sin sesión responde 302 a `login.php`.
- **Intranet (7 elementos):** archivos de configuración, librerías, datos técnicos y herramientas de prueba. **Ninguno debería ser solicitable desde el navegador**, pero al compartir todos la raíz publicada de Apache, hoy lo son (hallazgos H1–H5 de la línea base).

## Riesgo estructural (decisión pendiente de implantación)

La clasificación ideal requiere una estructura que el proyecto aún no tiene: un **directorio público único** (document root de Apache) con el resto del código fuera de él o protegido por `.htaccess`/`Require all denied`. Los elementos marcados como "intranet" son los candidatos claros a salir de la zona pública en los siguientes manuales:

1. `sql/` y `storage/` → fuera del document root o denegados por configuración.
2. `prueba_*.php`, `comprobar_curl.php` → a una carpeta de herramientas no pública.
3. `conexion.php`, `seguridad_web.php`, `servicios/` → cargables por `require`, no ejecutables directamente.
4. Secretos (`API_TOKEN`, credenciales MySQL) → a configuración por entorno (la dirección que ya propone `config/servicios.php` del Manual 12 con `$_ENV`).
