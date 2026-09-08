-- Ruta360 · Manual 3 · Base de datos de ciudades internas
-- Importar desde phpMyAdmin (pestaña SQL o Importar) o mediante el cliente mysql.
-- Nota: el manual imprimía "CREATE DATABASE ruta" y después "USE ruta360";
-- aquí se usa ruta360 de forma consistente.

CREATE DATABASE IF NOT EXISTS ruta360
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE ruta360;

CREATE TABLE ciudades (
    id_ciudad INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    pais VARCHAR(100) NOT NULL,
    latitud DECIMAL(9,6) NOT NULL,
    longitud DECIMAL(9,6) NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ciudad_pais (nombre, pais)
);

INSERT INTO ciudades
    (nombre, pais, latitud, longitud)
VALUES
    ('Barcelona', 'España', 41.387400, 2.168600),
    ('Madrid', 'España', 40.416800, -3.703800),
    ('Valencia', 'España', 39.469900, -0.376300),
    ('París', 'Francia', 48.856600, 2.352200),
    ('Roma', 'Italia', 41.902800, 12.496400);

-- ----------------------------------------------------------------
-- Manual 4 · Rutas y puntos de interés
-- ----------------------------------------------------------------

CREATE TABLE rutas (
    id_ruta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ciudad INT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    duracion_minutos SMALLINT UNSIGNED NOT NULL,
    distancia_km DECIMAL(5,2) NOT NULL,
    dificultad ENUM('fácil', 'media', 'alta') NOT NULL DEFAULT 'media',
    activa TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_rutas_ciudad
        FOREIGN KEY (id_ciudad) REFERENCES ciudades(id_ciudad)
);

CREATE TABLE puntos_interes (
    id_punto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ruta INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_puntos_ruta
        FOREIGN KEY (id_ruta) REFERENCES rutas(id_ruta),
    UNIQUE KEY uq_ruta_orden (id_ruta, orden)
);

-- Datos de ejemplo (Manual 4, apartado 4.8).
-- Los valores 1 presuponen una importación nueva del script;
-- si la base de datos ya contiene otros datos, usa los identificadores reales.

INSERT INTO rutas
    (id_ciudad, titulo, descripcion, duracion_minutos, distancia_km, dificultad)
VALUES
    (1, 'Barcelona modernista',
    'Un recorrido por algunos espacios esenciales del modernismo.',
    180, 4.80, 'media');

INSERT INTO puntos_interes
    (id_ruta, nombre, descripcion, orden)
VALUES
    (1, 'Sagrada Família', 'Inicio del recorrido.', 1),
    (1, 'Casa Milà', 'Arquitectura de Antoni Gaudí.', 2),
    (1, 'Casa Batlló', 'Fachada y formas inspiradas en la naturaleza.', 3);
