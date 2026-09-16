# 03 · Ficha de requisitos técnicos (Actividad guiada 1.32)

**Método:** script temporal de texto plano (sin `phpinfo()`) ejecutado **dos veces**: por CLI y a través de Apache, para comparar ambos entornos. El script se **eliminó** tras registrar los resultados. Versiones complementarias obtenidas con `php -v` y una consulta `SELECT VERSION()` vía PDO.

**Fecha:** 14/09/2026

## Ficha de requisitos

| Requisito | Valor detectado | ¿Cumple? | Evidencia |
|---|---|---|---|
| Sistema operativo | Windows (PHP_OS_FAMILY: `Windows`) | ✅ | Script temporal |
| Servidor web | Apache (XAMPP), SAPI `apache2handler` | ✅ | Script vía HTTP |
| PHP | **8.2.12** (ZTS, VC19 x64) | ✅ | `php -v` + script |
| php.ini cargado | `C:\xampp\php\php.ini` (mismo en CLI y Apache) | ✅ | `php_ini_loaded_file()` |
| `pdo_mysql` | OK | ✅ | `php -m` + script |
| `curl` | OK | ✅ | `php -m` + script |
| `json` | OK | ✅ | `php -m` + script |
| `openssl` | OK | ✅ | `php -m` + script |
| `mbstring` | OK | ✅ | `php -m` + script |
| `soap` | OK (activa) | ✅ | `php -m` + script |
| `session` | OK | ✅ | `php -m` + script |
| MySQL | **MariaDB 10.4.32** (protocolo MySQL) | ✅ | `SELECT VERSION()` vía PDO |
| Acceso a Internet | ✅ | La API meteorológica respondió en la línea base | Pruebas de la línea base |
| Base de datos `ruta360` | Importada (la API devuelve datos) | ✅ | `api/ruta.php?id_ruta=1` → 200 |

## Versiones por comando (Manual 1.20)

```
php -v        → PHP 8.2.12 (cli) (built: Oct 24 2023) ZTS Visual C++ 2019 x64
php -m        → curl, json, mbstring, openssl, pdo_mysql, session, soap (entre otras)
mysql --version / SELECT VERSION() → 10.4.32-MariaDB
```

## Comprobación de extensiones requeridas (Manual 1.21)

| Extensión | Uso en Ruta360 | Estado |
|---|---|---|
| `pdo_mysql` | Conexión PDO con MySQL (`conexion.php`, API) | ✅ OK |
| `curl` | Consumo de Open-Meteo y de la API propia | ✅ OK |
| `json` | Contratos JSON de `api/` y clientes | ✅ OK |
| `openssl` | Funciones criptográficas (hash de tokens SHA-256 usa `hash()`, núcleo) | ✅ OK |
| `mbstring` | `mb_strlen()` en validación de rutas (Manual 8/9) | ✅ OK |
| `soap` | Integraciones SOAP del cierre de la UF anterior (Manual 12) | ✅ OK (activa) |

## Observaciones

1. **CLI y Apache cargan el mismo `php.ini`** en esta instalación: una sola comprobación era suficiente, pero se verificaron ambos SAPI por rigor (el Manual 12.10 advierte que pueden diferir).
2. La extensión `soap` ya está activada, lo que deja preparado el terreno para las integraciones del Manual 12 (adaptador SOAP de distancias).
3. MariaDB 10.4 es compatible con todo el SQL del proyecto (charset `utf8mb4`, consultas preparadas, `NOW()`, `GROUP BY` con `COUNT`).
4. No se dejó ninguna página con `phpinfo()` ni ningún archivo de diagnóstico en el proyecto: el script temporal se creó, se ejecutó y se borró en la misma sesión.
