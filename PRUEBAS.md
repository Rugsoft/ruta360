# Plan de pruebas rápido

## Entorno

- Abrir `comprobar.php` y verificar todos los requisitos.
- Confirmar que la versión de PHP es 8.1 o superior.
- Confirmar que PDO MySQL, cURL, JSON y OpenSSL están activos.

## Web

- Abrir la portada.
- Listar rutas sin filtros.
- Filtrar por ciudad y dificultad.
- Buscar una palabra del título.
- Abrir una ficha y comprobar sus puntos de interés.
- Consultar el tiempo. Si no hay Internet, debe aparecer un aviso controlado.

## API GET

- `GET /ruta360/api/rutas.php` devuelve 200 y una colección.
- `GET /ruta360/api/rutas.php?id_ruta=1` devuelve 200 y una ruta.
- `GET /ruta360/api/rutas.php?id_ruta=9999` devuelve 404.
- `GET /ruta360/api/rutas.php?id_ruta=abc` devuelve 400.

## Seguridad y escritura

- Entrar con `admin@ruta360.local` y `password`.
- Crear una ruta desde el panel. Debe responder 201.
- Editar la ruta desde el panel. La petición interna debe usar PUT y devolver 200.
- Entrar como editor y comprobar que no puede desactivar rutas.
- Entrar como administrador y desactivar la ruta creada.
- Llamar a POST sin token. Debe responder 401.
- Llamar a DELETE con token de editor. Debe responder 403.

## Integraciones

- Abrir `integraciones.php`.
- Deben aparecer distancia local y transporte simulado.
- Meteorología muestra datos, caché o un aviso sin romper la ficha.

## Resultado

La prueba se considera correcta cuando un fallo meteorológico no oculta la ruta, las lecturas públicas funcionan sin token y las escrituras respetan identidad y rol.
