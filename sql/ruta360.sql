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
