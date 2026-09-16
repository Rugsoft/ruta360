# 02 · Inventario de archivos (Actividad guiada 1.31)

**Criterio:** relacionar cada archivo con su responsabilidad y decidir si un navegador debería poder solicitarlo directamente. **No se copian contraseñas ni tokens**: solo se indica dónde residen.

**Fecha:** 14/09/2026 · Raíz del proyecto: `htdocs/curso-soc-php/Ruta360/` (todo el árbol está publicado por Apache)

## Inventario general

| Elemento | Ubicación | Función | ¿Debería ser público? |
|---|---|---|---|
| Página inicial | `index.php` | Portada con selector de ciudades | ✅ Sí |
| Listado de rutas | `rutas.php` | Listado de rutas activas con filtros | ✅ Sí |
| Ficha de ruta | `ver_ruta.php` | Detalle de ruta + meteorología | ✅ Sí |
| Página de tiempo | `tiempo.php` | Consulta meteorológica por ciudad (versión Manual 8) | ✅ Sí |
| Crear ruta (web) | `nueva_ruta.php` | Formulario de creación (extranet, sesión + CSRF) | Con sesión |
| Editar ruta (web) | `editar_ruta.php` | Formulario de edición (extranet, sesión + CSRF) | Con sesión |
| Eliminar ruta (web) | `eliminar_ruta.php` | Borrado lógico (extranet, admin) | Con sesión admin |
| Login | `login.php` | Autenticación de usuarios | ✅ Sí |
| Logout | `logout.php` | Cierre de sesión | Con sesión |
| Conexión MySQL | `conexion.php` | Conexión PDO; **contiene credenciales en claro** | ❌ No (necesario en raíz por diseño actual) |
| Seguridad web | `seguridad_web.php` | Sesiones, CSRF, roles de la parte web | ❌ No (se carga con `require`) |
| Estilos | `estilos.css` | Presentación | ✅ Sí |
| Recurso de ruta | `api/ruta.php` | JSON de una ruta (GET) | ✅ Sí (solo GET) |
| Colección de rutas | `api/rutas.php` | JSON CRUD completo (GET/POST/PUT/PATCH/DELETE) | ✅ Sí (escritura con Bearer) |
| Colección de ciudades | `api/ciudades.php` | JSON de ciudades activas (GET) | ✅ Sí |
| Cliente meteorológico | `servicios/meteorologia.php` | cURL, reintentos y caché (Manual 11) | ❌ No |
| Cliente de la API propia | `servicios/cliente_rutas.php` | Cliente cURL; **contiene API_BASE_URL y API_TOKEN como constantes en claro** | ❌ No |
| Script SQL | `sql/ruta360.sql` | Estructura + datos de la base | ❌ **No** — hoy es descargable (HTTP 200) |
| Caché meteorológica | `storage/cache/` | JSON con respuestas del proveedor | ❌ No |
| Documentación | `readme.md` | Instalación, contratos y pruebas | Aceptable, valorar |

## Scripts auxiliares (deberían estar fuera de la raíz pública)

| Elemento | Ubicación | Función | Riesgo actual |
|---|---|---|---|
| Comprobación de cURL | `comprobar_curl.php` | Diagnóstico de extensión | Bajo: revela capacidades del servidor |
| Prueba de API externa | `prueba_api.php` | Llamada directa a Open-Meteo | Bajo: consume red desde el servidor |
| Pruebas de resiliencia | `prueba_resiliencia.php` | Suite del Manual 11; escribe y borra caché | Medio: ejecutable por cualquiera |
| **Pruebas de seguridad** | `prueba_seguridad.php` | Suite del Manual 10; **crea y elimina rutas reales**, contiene **6 tokens de API en claro** | **Alto**: cualquier visitante puede ejecutar operaciones de escritura contra la API |

## Código interno de `api/` (común a los tres endpoints)

| Elemento | Función |
|---|---|
| `conexion.php` (requerido por la API) | Conexión PDO a `ruta360` (root sin contraseña) |
| `PERMISOS_ROL` en `api/rutas.php` | Matriz RBAC: lector / editor / admin |
| `autenticarToken()` | Validación Bearer contra `api_tokens` (hash SHA-256) |

## Logs

No existe carpeta de logs en el proyecto: los errores van al log de PHP/Apache (`error_log()`). Es un **pendiente de implantación** (ubicación de logs, rotación y protección frente a lectura web).

## Dónde residen los secretos (sin copiar valores)

| Secreto | Ubicación | Protección actual |
|---|---|---|
| Contraseña MySQL | `conexion.php` (root, sin contraseña) | En claro; típico de XAMPP local |
| Token de API para escritura | `servicios/cliente_rutas.php` (constante `API_TOKEN`) | En claro en el repositorio |
| Tokens de prueba (6) | `prueba_seguridad.php` | En claro en el repositorio, además en archivo ejecutable públicamente |
| Contraseñas de usuarios web | `usuarios` (BD, hasheadas) | Correcto (hash) |

## Conclusión del inventario

El proyecto mezcla en la misma carpeta publicada por Apache: código de negocio, scripts auxiliares de prueba, el script SQL de la base y la caché. La estructura no distingue **directorio público** del resto, y los secretos están en el código. Ambos puntos son las decisiones de estructuración principales para los próximos manuales (separar `public/`, mover secretos a configuración por entorno, proteger `sql/`, `storage/` y los `prueba_*.php`).
