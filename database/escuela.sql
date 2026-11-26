-- database/escuela.sql: esquema de la base y datos de prueba (todos ficticios)
-- Se carga desde bash con:   sudo mysql < database/escuela.sql
-- Ojo: si la base escuela ya existe, la borra y la vuelve a crear.

-- El archivo está en UTF-8: que el cliente de mysql lo lea así (tildes y ñ bien guardadas)
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS escuela;
-- utf8mb4: guarda bien tildes, ñ y cualquier carácter
-- _ai_ci: al comparar no distingue tildes ni mayúsculas ('gutierrez' = 'Gutiérrez')
CREATE DATABASE escuela CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE escuela;

-- Usuarios del sistema (se usan para el login); se crean con scripts/crear_usuario.php
CREATE TABLE usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario       VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,                    -- el HASH, nunca la contraseña
  rol           ENUM('admin','preceptor','docente') NOT NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario)                -- no hay dos usuarios iguales
) ENGINE=InnoDB;

-- Cursos de la especialidad (4.º a 7.º año)
CREATE TABLE cursos (
  id        TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  anio      TINYINT UNSIGNED NOT NULL,                    -- 4, 5, 6 o 7
  division  TINYINT UNSIGNED NOT NULL,                    -- 1 = 1.ª división
  nombre    VARCHAR(20) NOT NULL,                         -- como se muestra: 4° 1ª
  PRIMARY KEY (id),
  UNIQUE KEY uq_cursos_anio_division (anio, division),
  CONSTRAINT chk_cursos_anio CHECK (anio BETWEEN 4 AND 7)
) ENGINE=InnoDB;

-- Alumnos: cada uno está en un curso
CREATE TABLE alumnos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  dni       VARCHAR(10)  NOT NULL,                        -- texto: con el DNI no se hacen cuentas
  apellido  VARCHAR(60)  NOT NULL,
  nombre    VARCHAR(60)  NOT NULL,
  curso_id  TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alumnos_dni (dni),                        -- el DNI no se repite
  CONSTRAINT fk_alumnos_curso FOREIGN KEY (curso_id)
    REFERENCES cursos(id) ON DELETE RESTRICT              -- no se borra un curso con alumnos
) ENGINE=InnoDB;

-- Materias de la especialidad, por año
CREATE TABLE materias (
  id      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(60) NOT NULL,
  anio    TINYINT UNSIGNED NOT NULL,                      -- en qué año se cursa
  PRIMARY KEY (id),
  UNIQUE KEY uq_materias_nombre (nombre)
) ENGINE=InnoDB;

-- Notas: una por alumno, materia y trimestre
CREATE TABLE notas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  alumno_id   INT UNSIGNED NOT NULL,
  materia_id  SMALLINT UNSIGNED NOT NULL,
  trimestre   TINYINT UNSIGNED NOT NULL,
  nota        TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notas_alumno_materia_trimestre (alumno_id, materia_id, trimestre),
  CONSTRAINT chk_notas_trimestre CHECK (trimestre BETWEEN 1 AND 3),
  CONSTRAINT chk_notas_nota CHECK (nota BETWEEN 1 AND 10),   -- la última barrera: 11 no entra
  CONSTRAINT fk_notas_alumno FOREIGN KEY (alumno_id)
    REFERENCES alumnos(id) ON DELETE CASCADE,             -- si se borra el alumno, se borran sus notas
  CONSTRAINT fk_notas_materia FOREIGN KEY (materia_id)
    REFERENCES materias(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ===== Datos de prueba (ficticios) =====

INSERT INTO cursos (id, anio, division, nombre) VALUES
  (1, 4, 1, '4° 1ª'),
  (2, 5, 1, '5° 1ª'),
  (4, 6, 1, '6° 1ª'),
  (3, 7, 1, '7° 1ª');

INSERT INTO materias (id, nombre, anio) VALUES
  (1, 'Programación I', 4),
  (2, 'Software I', 4),
  (3, 'Hardware I', 4),
  (4, 'Programación II', 5),
  (5, 'Base de Datos I', 5),
  (6, 'Redes I', 5),
  (7, 'Programación III', 6),
  (8, 'Base de Datos II', 6),
  (9, 'Redes II', 6),
  (10, 'Programación IV', 7),
  (11, 'Taller de Ingeniería', 7),
  (12, 'Práctica Profesionalizante', 7);

-- Dieciséis alumnos ficticios, cuatro por curso
INSERT INTO alumnos (id, dni, apellido, nombre, curso_id) VALUES
  (1, '50123456', 'Acosta', 'Valentina', 1),
  (2, '50234567', 'Domínguez', 'Tomás', 1),
  (3, '50345678', 'Ferreyra', 'Milagros', 1),
  (4, '50456789', 'Ojeda', 'Bautista', 1),
  (5, '49123456', 'Aguirre', 'Lucas', 2),
  (6, '49234567', 'Castro', 'Agustina', 2),
  (7, '49345678', 'Muñoz', 'Joaquín', 2),
  (8, '49456789', 'Vera', 'Camila', 2),
  (9, '47123456', 'Cabrera', 'Martina', 3),
  (10, '47234567', 'Gutiérrez', 'Facundo', 3),
  (11, '47345678', 'Pereyra', 'Abril', 3),
  (12, '47456789', 'Rojas', 'Thiago', 3),
  (13, '48123456', 'Herrera', 'Lautaro', 4),
  (14, '48234567', 'Ibáñez', 'Delfina', 4),
  (15, '48345678', 'Luna', 'Santino', 4),
  (16, '48456789', 'Sánchez', 'Morena', 4);

-- Notas del 1.er y 2.º trimestre: (alumno, materia, trimestre, nota)
-- Los de 6° 1ª no tienen: sus notas las carga el docente desde el sistema
INSERT INTO notas (alumno_id, materia_id, trimestre, nota) VALUES
  (1, 1, 1, 8), (1, 2, 1, 9), (1, 3, 1, 7), (1, 1, 2, 9), (1, 2, 2, 9), (1, 3, 2, 8),
  (2, 1, 1, 6), (2, 2, 1, 5), (2, 3, 1, 7), (2, 1, 2, 7), (2, 2, 2, 6), (2, 3, 2, 7),
  (3, 1, 1, 10), (3, 2, 1, 9), (3, 3, 1, 9), (3, 1, 2, 9), (3, 2, 2, 10), (3, 3, 2, 10),
  (4, 1, 1, 4), (4, 2, 1, 6), (4, 3, 1, 5), (4, 1, 2, 6), (4, 2, 2, 7), (4, 3, 2, 6),
  (5, 4, 1, 7), (5, 5, 1, 8), (5, 6, 1, 6), (5, 4, 2, 8), (5, 5, 2, 8), (5, 6, 2, 7),
  (6, 4, 1, 9), (6, 5, 1, 10), (6, 6, 1, 8), (6, 4, 2, 10), (6, 5, 2, 9), (6, 6, 2, 9),
  (7, 4, 1, 5), (7, 5, 1, 4), (7, 6, 1, 6), (7, 4, 2, 6), (7, 5, 2, 6), (7, 6, 2, 7),
  (8, 4, 1, 8), (8, 5, 1, 7), (8, 6, 1, 9), (8, 4, 2, 8), (8, 5, 2, 8), (8, 6, 2, 9),
  (9, 10, 1, 9), (9, 11, 1, 8), (9, 12, 1, 10), (9, 10, 2, 10), (9, 11, 2, 9), (9, 12, 2, 10),
  (10, 10, 1, 6), (10, 11, 1, 7), (10, 12, 1, 7), (10, 10, 2, 7), (10, 11, 2, 7), (10, 12, 2, 8),
  (11, 10, 1, 8), (11, 11, 1, 8), (11, 12, 1, 7), (11, 10, 2, 9), (11, 11, 2, 8), (11, 12, 2, 8),
  (12, 10, 1, 5), (12, 11, 1, 6), (12, 12, 1, 4), (12, 10, 2, 7), (12, 11, 2, 6), (12, 12, 2, 6);

-- Usuario de la aplicación (se crea una sola vez, como root, con la contraseña que elijas):
--   CREATE USER 'app_escuela'@'localhost' IDENTIFIED BY 'una-contraseña-segura';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON escuela.* TO 'app_escuela'@'localhost';
