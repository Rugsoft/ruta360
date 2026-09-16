# 01 · Informe de auditoría inicial de Ruta360 (Manual 1.35)

## 1. Identificación

| Campo | Valor |
|---|---|
| Proyecto | Ruta360 — aplicación PHP consumidora y proveedora de servicios web |
| Versión | No versionada en la aplicación (sin archivo de versión); rama `main` del repositorio |
| Fecha de auditoría | 14/09/2026 |
| Responsable | Equipo de implantación (auditoría inicial, sin modificaciones) |
| Entorno auditado | XAMPP local: Apache + PHP 8.2.12 + MariaDB 10.4.32, Windows |
| Ubicación actual | `htdocs/curso-soc-php/Ruta360/` |

**Alcance:** fotografía del estado recibido **antes de intervenir**. No se ha corregido nada durante la observación (regla del Manual 1.2): solo un script temporal de requisitos, creado, ejecutado y eliminado.

## 2. Estado funcional (línea base)

Toda la función principal está operativa (detalle en `05_linea_base.md`):

- ✅ Portada, listado y ficha de rutas (con error controlado para IDs inválidos o inexistentes).
- ✅ API JSON completa: lectura pública (200/404 correctos) y CRUD con Bearer + RBAC.
- ✅ Meteorología resiliente (caché reciente/antigua, reintentos, respuesta degradada).
- ✅ Login/logout con CSRF; zonas privadas redirigen a login sin sesión (302 verificado).
- ✅ Pruebas automatizadas del proyecto (`prueba_resiliencia.php`, `prueba_seguridad.php`) presentes y ejecutables.

## 3. Entorno actual

| Componente | Valor | Observación |
|---|---|---|
| Apache | SAPI `apache2handler` (XAMPP) | Sirve toda la carpeta del proyecto |
| PHP | 8.2.12, mismo `php.ini` en CLI y Apache | Requisitos completos (ver `03_ficha_requisitos.md`) |
| Base de datos | MariaDB 10.4.32, esquema `ruta360`, charset `utf8mb4` | Usuario `root` sin contraseña (XAMPP) |
| Extensiones | `pdo_mysql`, `curl`, `json`, `openssl`, `mbstring`, `soap`, `session` | Todas OK |
| Red | Acceso a Internet operativo (Open-Meteo responde) | Dependencia externa remota |

## 4. Inventario (resumen)

Detalle completo en `02_inventario_archivos.md`. Estructura observada (no se reorganiza):

```
Ruta360/
├── api/               3 endpoints JSON (ruta, rutas, ciudades)
├── servicios/         meteorologia.php, cliente_rutas.php
├── sql/               ruta360.sql
├── storage/cache/     caché meteorológica
├── *.php (raíz)       páginas web + seguridad + conexión + 4 scripts auxiliares
├── estilos.css, readme.md
```

Dependencias: internas (código, SQL, CSS), externas locales (Apache, PHP, extensiones, MariaDB) y externa remota (API Open-Meteo).

## 5. Accesos / zonas

Detalle en `04_clasificacion_zonas.md`: 7 recursos de **Internet**, 5 de **extranet** (más los verbos de escritura de la API con Bearer+RBAC) y 7 elementos de **intranet** que hoy están publicados por estar en la raíz de Apache.

## 6. Riesgos detectados (hechos)

| # | Riesgo | Gravedad | Evidencia |
|---|---|---|---|
| R1 | `prueba_seguridad.php` ejecutable públicamente: crea/elimina rutas reales y **contiene tokens de API en claro** | **Alta** | HTTP 200 desde el navegador; 6 tokens literales en el código |
| R2 | `sql/ruta360.sql` descargable públicamente | **Alta** | HTTP 200 al solicitarlo |
| R3 | Secretos en el repositorio: `API_TOKEN` en `servicios/cliente_rutas.php`, credenciales MySQL en `conexion.php` | Alta | Constantes en claro en el código |
| R4 | Scripts auxiliares públicos (`comprobar_curl.php`, `prueba_api.php`, `prueba_resiliencia.php`) | Media | HTTP 200; el de resiliencia toca la caché |
| R5 | Sin separación de directorio público: código interno, SQL y caché comparten raíz con las páginas | Media | Estructura sin `public/` ni denegaciones |
| R6 | Sin carpeta de logs propia ni política de rotación | Baja | `error_log()` al log de PHP/Apache |
| R7 | Sin versión registrada de la aplicación ni procedimiento documentado de copia/retorno | Baja | No existe CHANGELOG ni mecanismo de versionado |

## 7. Decisiones pendientes (para los siguientes manuales)

1. **Estructura:** crear directorio público único y mover/denegar `sql/`, `storage/`, `servicios/`, `conexion.php` y scripts auxiliares.
2. **Configuración y secretos:** externalizar `API_TOKEN`, credenciales MySQL y parámetros por entorno (dirección propuesta: `config/` con `$_ENV`, como prepara el Manual 12).
3. **Entornos:** definir desarrollo / pruebas / producción con copias separadas de archivos y datos.
4. **Base de datos:** usuario MySQL de aplicación con privilegios mínimos (no `root`), scripts reproducibles y procedimiento de copia/retorno.
5. **Operación:** carpeta de logs protegida, política de rotación y registro de cambios versionado.
6. **Verificación:** plan de pruebas de aceptación (los `prueba_*.php` existentes son un buen punto de partida, pero deben dejar de ser públicos).

## 8. Modelo de conclusión

> Ruta360 funciona actualmente en el entorno local indicado y permite completar las pruebas básicas registradas: catálogo, fichas, API de lectura, meteorología resiliente, autenticación web y API de escritura con RBAC. La línea base funcional es sana; las incidencias detectadas son de exposición de archivos auxiliares, del script SQL y de secretos en el código, no de lógica de negocio. Antes de desplegarla debemos separar el directorio público del resto del proyecto, externalizar la configuración y los secretos, asignar un usuario de base de datos con privilegios mínimos, definir los entornos y documentar versiones, copias y retorno. Las incidencias se han anotado sin modificar todavía la copia recibida.

## 9. Checklist de entrega (Manual 1.38)

- [x] He abierto la copia de Ruta360 sin modificarla.
- [x] He probado la página inicial, el listado, una ficha y la API.
- [x] He identificado las zonas Internet, extranet e intranet.
- [x] He registrado las versiones de PHP y MySQL/MariaDB.
- [x] He comprobado las extensiones principales.
- [x] He inventariado carpetas, archivos, base de datos y servicios externos.
- [x] He detectado qué elementos no deberían ser públicos.
- [x] No he copiado contraseñas ni tokens en el informe (solo su ubicación).
- [x] He separado hechos, riesgos y decisiones pendientes.
- [x] He entregado la auditoría inicial con evidencias.

## Documentos complementarios

| Documento | Contenido |
|---|---|
| `02_inventario_archivos.md` | Inventario de archivos y ubicación de secretos |
| `03_ficha_requisitos.md` | Ficha técnica de versiones y extensiones |
| `04_clasificacion_zonas.md` | Clasificación Internet / extranet / intranet |
| `05_linea_base.md` | Recorrido y pruebas de la línea base |
