CREATE DATABASE simposio;
USE simposio;

CREATE TABLE usuario (
 id_usuario INT AUTO_INCREMENT PRIMARY KEY,
 correo VARCHAR(100) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 nombre VARCHAR(50) NOT NULL,
 apellidos VARCHAR(50),
 direccion VARCHAR(150),
 tipo_usuario ENUM('alumno','docente','empresa') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE administrador (
  id_admin int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Estructura de tabla para la tabla `alumno`
CREATE TABLE alumno (
  id_alumno int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  id_usuario int(11) DEFAULT NULL,
  matricula varchar(20) DEFAULT NULL,
  carrera varchar(100) DEFAULT NULL,
  semestre int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Estructura de tabla para la tabla `apoyo_docente`
CREATE TABLE apoyo_docente (
  id_alumno int(11) NOT NULL,
  id_docente int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Estructura de tabla para la tabla `apoyo_empresa`
CREATE TABLE apoyo_empresa (
  id_alumno int(11) NOT NULL,
  id_empresa int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Estructura de tabla para la tabla `articulo`
CREATE TABLE articulo (
  id_articulo int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  id_evento int(11) NOT NULL,
  titulo varchar(150) NOT NULL,
  resumen text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Estructura de tabla para la tabla `docente`
CREATE TABLE docente (
  id_docente int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  id_usuario int(11) DEFAULT NULL,
  especialidad varchar(100) DEFAULT NULL,
  grado_academico varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `empresa`
CREATE TABLE empresa (
  id_empresa int(11) AUTO_INCREMENT NOT NULL PRIMARY KEY,
  id_usuario int(11) DEFAULT NULL,
  nombre_empresa varchar(150) DEFAULT NULL,
  sector varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE proyecto_alumno (
  id_proyecto int(11) NOT NULL,
  id_alumno int(11) NOT NULL,
  rol enum('autor','coautor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Estructura de tabla para la tabla `proyecto_docente`
CREATE TABLE proyecto_docente (
  id_proyecto int(11) NOT NULL,
  id_docente int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evento (
 id_evento INT AUTO_INCREMENT PRIMARY KEY,
 id_admin INT NOT NULL,
 titulo VARCHAR(150) NOT NULL,
 descripcion TEXT,
 fecha DATE NOT NULL,
 hora_inicio TIME NOT NULL,
 hora_fin TIME NOT NULL,
 anio INT NOT NULL,
 creado_por INT NOT NULL,
 FOREIGN KEY (creado_por) REFERENCES administrador(id_admin)
);

CREATE TABLE tipo_actividad (
 id_tipo INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(50) NOT NULL,
 duracion_minutos INT NOT NULL
);

CREATE TABLE actividad_evento (
 id_actividad INT AUTO_INCREMENT PRIMARY KEY,
 id_evento INT NOT NULL,
 id_usuario INT NOT NULL,
 id_tipo INT NOT NULL,

 titulo VARCHAR(150) NOT NULL,
 descripcion TEXT,
 resumen TEXT,
 referencias TEXT,
 archivo_pdf VARCHAR(255),

 fecha DATE NOT NULL,
 hora_inicio TIME NOT NULL,
 hora_fin TIME NOT NULL,

 FOREIGN KEY (id_evento) REFERENCES evento(id_evento) ON DELETE CASCADE,
 FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
 FOREIGN KEY (id_tipo) REFERENCES tipo_actividad(id_tipo)
);

CREATE TABLE proyecto (
 id_proyecto INT AUTO_INCREMENT PRIMARY KEY,
 titulo VARCHAR(150) NOT NULL,
 descripcion TEXT,
 tipo VARCHAR(50),
 categoria VARCHAR(50),

 estado ENUM('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
 aprobado_por INT NULL,
 fecha_aprobacion DATETIME NULL,

 FOREIGN KEY (aprobado_por) REFERENCES administrador(id_admin)
);

CREATE TABLE plantilla_impresion (
 id_plantilla INT AUTO_INCREMENT PRIMARY KEY,
 anio INT NOT NULL,
 nombre VARCHAR(100),
 archivo_css VARCHAR(255),
 activa BOOLEAN DEFAULT 1
);