SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
DROP DATABASE IF EXISTS ruta360;
CREATE DATABASE ruta360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ruta360;

CREATE TABLE ciudades (
  id_ciudad INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  pais VARCHAR(100) NOT NULL,
  latitud DECIMAL(9,6) NOT NULL,
  longitud DECIMAL(9,6) NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_ciudad_pais (nombre,pais)
) ENGINE=InnoDB;

CREATE TABLE rutas (
  id_ruta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_ciudad INT UNSIGNED NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  descripcion VARCHAR(1000) NOT NULL,
  duracion_minutos SMALLINT UNSIGNED NOT NULL,
  distancia_km DECIMAL(7,2) NOT NULL,
  dificultad ENUM('fácil','media','difícil') NOT NULL DEFAULT 'media',
  activa TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_ruta_ciudad FOREIGN KEY (id_ciudad) REFERENCES ciudades(id_ciudad)
) ENGINE=InnoDB;

CREATE TABLE puntos_interes (
  id_punto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_ciudad INT UNSIGNED NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  descripcion VARCHAR(500) NOT NULL,
  CONSTRAINT fk_punto_ciudad FOREIGN KEY (id_ciudad) REFERENCES ciudades(id_ciudad)
) ENGINE=InnoDB;

CREATE TABLE ruta_punto (
  id_ruta INT UNSIGNED NOT NULL,
  id_punto INT UNSIGNED NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id_ruta,id_punto),
  UNIQUE KEY uq_ruta_orden (id_ruta,orden),
  CONSTRAINT fk_rp_ruta FOREIGN KEY (id_ruta) REFERENCES rutas(id_ruta) ON DELETE CASCADE,
  CONSTRAINT fk_rp_punto FOREIGN KEY (id_punto) REFERENCES puntos_interes(id_punto)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('lector','editor','admin') NOT NULL DEFAULT 'lector',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE api_tokens (
  id_token INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expira_en DATETIME NULL,
  revocado_en DATETIME NULL,
  ultimo_uso DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_token_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

INSERT INTO ciudades (nombre,pais,latitud,longitud) VALUES
('Barcelona','España',41.387400,2.168600),
('Madrid','España',40.416800,-3.703800),
('Valencia','España',39.469900,-0.376300),
('Sevilla','España',37.389100,-5.984500);

INSERT INTO rutas (id_ciudad,titulo,descripcion,duracion_minutos,distancia_km,dificultad) VALUES
(1,'Barcelona modernista','Recorrido por edificios y espacios esenciales del modernismo barcelonés.',150,4.20,'media'),
(1,'Barcelona junto al mar','Paseo desde el Port Vell hasta las playas de la ciudad.',120,5.10,'fácil'),
(2,'Madrid de los Austrias','Historia y plazas del Madrid más antiguo.',110,3.40,'fácil'),
(3,'Valencia histórica','Ruta desde las Torres de Serranos hasta el Mercado Central.',100,3.10,'fácil'),
(4,'Sevilla monumental','Un recorrido por el centro histórico y sus grandes monumentos.',180,6.30,'media');

INSERT INTO puntos_interes (id_ciudad,nombre,descripcion) VALUES
(1,'Sagrada Família','Basílica diseñada por Antoni Gaudí.'),(1,'Casa Batlló','Edificio modernista del paseo de Gràcia.'),(1,'La Pedrera','Casa Milà, una de las obras más reconocibles de Gaudí.'),
(1,'Port Vell','Puerto histórico y zona de paseo.'),(1,'Barceloneta','Barrio marítimo y playa urbana.'),
(2,'Plaza Mayor','Plaza porticada en el centro histórico.'),(2,'Palacio Real','Residencia oficial de la monarquía española.'),
(3,'Torres de Serranos','Antigua puerta de la muralla.'),(3,'Mercado Central','Edificio modernista dedicado al comercio.'),
(4,'Catedral de Sevilla','Catedral gótica y entorno de la Giralda.'),(4,'Real Alcázar','Conjunto palaciego histórico.');

INSERT INTO ruta_punto VALUES (1,1,1),(1,2,2),(1,3,3),(2,4,1),(2,5,2),(3,6,1),(3,7,2),(4,8,1),(4,9,2),(5,10,1),(5,11,2);

-- Hash bcrypt estándar compatible con password_verify para la contraseña: password
INSERT INTO usuarios (nombre,email,password_hash,rol) VALUES
('Administración Ruta360','admin@ruta360.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.','admin'),
('Edición Ruta360','editor@ruta360.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.','editor');

INSERT INTO api_tokens (id_usuario,nombre,token_hash,expira_en) VALUES
(2,'Cliente web editor','6e7f65d6237e33d753cd91070ffcc9c0d473775d3ac904cbe860c898fe227599','2030-12-31 23:59:59'),
(1,'Cliente web administrador','ac737e028f1761550c141e19a63df1eca69272b7f095a6a067f04955c9bb2a67','2030-12-31 23:59:59');

SET FOREIGN_KEY_CHECKS=1;

