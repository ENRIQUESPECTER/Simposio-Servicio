-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-03-2026 a las 21:29:58
-- Versión del servidor: 10.4.16-MariaDB
-- Versión de PHP: 7.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `simposio`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividad_evento`
--

CREATE TABLE `actividad_evento` (
  `id_actividad` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_tipo` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `resumen` text DEFAULT NULL,
  `referencias` text DEFAULT NULL,
  `archivo_pdf` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `actividad_evento`
--

INSERT INTO `actividad_evento` (`id_actividad`, `id_evento`, `id_usuario`, `id_tipo`, `titulo`, `descripcion`, `resumen`, `referencias`, `archivo_pdf`, `fecha`, `hora_inicio`, `hora_fin`) VALUES
(1, 1, 3, 2, 'Inteligencia Artificial en 2026', 'Conferencia sobre avances recientes en IA.', 'Se abordarán modelos de lenguaje y automatización.', 'Referencia IEEE 2025', 'ia2026.pdf', '2026-02-26', '09:00:00', '10:00:00'),
(2, 1, 3, 1, 'Seguridad en Redes', 'Ponencia sobre vulnerabilidades actuales.', 'Análisis de ataques recientes.', 'NIST 2025', 'seguridad.pdf', '2026-02-26', '10:00:00', '10:30:00'),
(3, 1, 2, 3, 'Taller de Desarrollo Web Seguro', 'Taller práctico sobre seguridad en aplicaciones web.', 'Prácticas OWASP.', 'OWASP Top 10', 'taller_web.pdf', '2026-02-26', '11:00:00', '13:00:00'),
(4, 1, 10, 2, 'Sistemas de software', 'sfsdfsdg', 'sdgsdgsdg', 'dsgsdgsdg', 'uploads/actividades/1773373968_actividad web.pdf', '2026-03-24', '14:00:00', '15:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administrador`
--

CREATE TABLE `administrador` (
  `id_admin` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `administrador`
--

INSERT INTO `administrador` (`id_admin`, `usuario`, `password`) VALUES
(1, 'Luigi', '$2y$10$.IWWFaXOAKcm4m2W1ecOGuV.Laj0oXvX4.88Mq2awXsCs6z9zNGcG'),
(2, 'Goten', '$2y$10$qIXHb0g9KRxfYUHwsR.0Q.2YyvblCvuyklu6QoDYGcitwvo4pNZM6');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno`
--

CREATE TABLE `alumno` (
  `id_alumno` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `matricula` varchar(20) DEFAULT NULL,
  `carrera` varchar(100) DEFAULT NULL,
  `semestre` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `alumno`
--

INSERT INTO `alumno` (`id_alumno`, `id_usuario`, `matricula`, `carrera`, `semestre`) VALUES
(1, 1, '2023123456', 'Ingeniería en Informática', 6),
(2, 4, '423080645', 'Informatica', 8),
(5, 8, '423080645', 'Informatica', 8);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apoyo_docente`
--

CREATE TABLE `apoyo_docente` (
  `id_alumno` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apoyo_empresa`
--

CREATE TABLE `apoyo_empresa` (
  `id_alumno` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo`
--

CREATE TABLE `articulo` (
  `id_articulo` int(11) NOT NULL,
  `id_evento` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `resumen` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente`
--

CREATE TABLE `docente` (
  `id_docente` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `grado_academico` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `docente`
--

INSERT INTO `docente` (`id_docente`, `id_usuario`, `especialidad`, `grado_academico`) VALUES
(1, 2, 'Ciberseguridad', 'Doctorado'),
(2, 10, 'Mineria de Datos', 'Doctorado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresa`
--

CREATE TABLE `empresa` (
  `id_empresa` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombre_empresa` varchar(150) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `empresa`
--

INSERT INTO `empresa` (`id_empresa`, `id_usuario`, `nombre_empresa`, `sector`) VALUES
(1, 3, 'TechCorp S.A.', 'Tecnología');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evento`
--

CREATE TABLE `evento` (
  `id_evento` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `anio` int(11) NOT NULL,
  `creado_por` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `evento`
--

INSERT INTO `evento` (`id_evento`, `titulo`, `descripcion`, `fecha`, `hora_inicio`, `hora_fin`, `anio`, `creado_por`) VALUES
(1, 'Simposio de Tecnología 2026', 'Evento anual enfocado en innovación tecnológica.', '2026-03-24', '09:00:00', '19:00:00', 2026, 1),
(2, 'Jujutsu Kaisen', 'OPENINGS Y ENDINGS', '2026-12-24', '09:00:00', '19:00:00', 2026, 1),
(3, 'Sistemas de software', 'sdgfdsfgsdgsd', '2026-03-19', '10:00:00', '19:00:00', 2026, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantilla_impresion`
--

CREATE TABLE `plantilla_impresion` (
  `id_plantilla` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `archivo_css` varchar(255) DEFAULT NULL,
  `activa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `plantilla_impresion`
--

INSERT INTO `plantilla_impresion` (`id_plantilla`, `anio`, `nombre`, `archivo_css`, `activa`) VALUES
(1, 2026, 'Plantilla Oficial 2026', 'plantilla2026.css', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto`
--

CREATE TABLE `proyecto` (
  `id_proyecto` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `estado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  `aprobado_por` int(11) DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `proyecto`
--

INSERT INTO `proyecto` (`id_proyecto`, `titulo`, `descripcion`, `tipo`, `categoria`, `estado`, `aprobado_por`, `fecha_aprobacion`) VALUES
(1, 'Sistema de Detección de Intrusos', 'Proyecto enfocado en seguridad de redes.', 'Investigación', 'Ciberseguridad', 'pendiente', NULL, NULL),
(2, 'Aplicación Móvil Educativa', 'App para aprendizaje interactivo.', 'Desarrollo', 'Educación', 'aprobado', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto_alumno`
--

CREATE TABLE `proyecto_alumno` (
  `id_proyecto` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `rol` enum('autor','coautor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `proyecto_alumno`
--

INSERT INTO `proyecto_alumno` (`id_proyecto`, `id_alumno`, `rol`) VALUES
(1, 1, 'autor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyecto_docente`
--

CREATE TABLE `proyecto_docente` (
  `id_proyecto` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `proyecto_docente`
--

INSERT INTO `proyecto_docente` (`id_proyecto`, `id_docente`) VALUES
(1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_actividad`
--

CREATE TABLE `tipo_actividad` (
  `id_tipo` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `duracion_minutos` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `tipo_actividad`
--

INSERT INTO `tipo_actividad` (`id_tipo`, `nombre`, `duracion_minutos`) VALUES
(1, 'Ponencia', 30),
(2, 'Conferencia', 60),
(3, 'Taller', 120);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellidos` varchar(50) DEFAULT NULL,
  `direccion` varchar(150) DEFAULT NULL,
  `tipo_usuario` enum('alumno','docente','empresa') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `correo`, `password`, `nombre`, `apellidos`, `direccion`, `tipo_usuario`) VALUES
(1, 'alumno1@simposio.com', '$2y$10$pPgBPKk9aAL2Oy5SSevhpOFJUEfvxHmcTQ3.E6HDLFE6rridyOTwC', 'Luis', 'Martinez', 'Estado de México', 'alumno'),
(2, 'docente1@simposio.com', '$2y$10$vhL2lzYS.YMtzEV49jW1ZuM/HP2k6HiF91VvHzqbCgRsbUpIQPtNy', 'Dra. Ana', 'Lopez', 'CDMX', 'docente'),
(3, 'empresa1@simposio.com', '$2y$10$EjemploHashEmpresa', 'TechCorp', 'S.A.', 'Monterrey', 'empresa'),
(4, 'luigi@gmail.com', '$2y$10$7bJbzqKgtG5in1IhzGK1r.iGnDwMlrGWvzGp02XnnRXFYJPltfRWe', 'Luigi', 'Padilla', 'Villa de las Flores', 'alumno'),
(8, 'luigienrique04@gmail.com', '$2y$10$gycHz9xfs8fVjA/9f6HUM.dExr6aTx8EEkMiFY96OgX0AlJaelHWi', 'Luis Enrique', 'Padilla Salmoran', 'Coacalco de Berriozabal', 'alumno'),
(10, 'cynthia@gmail.com', '$2y$10$dvOvsyy2Xm7Sbj0scD9eS.hhdf/uEclKKO2SwwTT0TtNZ9pmFMIby', 'Cynthia', 'Leticia Otero', 'Polanco CDMX', 'docente');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividad_evento`
--
ALTER TABLE `actividad_evento`
  ADD PRIMARY KEY (`id_actividad`),
  ADD KEY `id_evento` (`id_evento`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_tipo` (`id_tipo`);

--
-- Indices de la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD PRIMARY KEY (`id_admin`);

--
-- Indices de la tabla `alumno`
--
ALTER TABLE `alumno`
  ADD PRIMARY KEY (`id_alumno`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD PRIMARY KEY (`id_articulo`);

--
-- Indices de la tabla `docente`
--
ALTER TABLE `docente`
  ADD PRIMARY KEY (`id_docente`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `empresa`
--
ALTER TABLE `empresa`
  ADD PRIMARY KEY (`id_empresa`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `evento`
--
ALTER TABLE `evento`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `plantilla_impresion`
--
ALTER TABLE `plantilla_impresion`
  ADD PRIMARY KEY (`id_plantilla`);

--
-- Indices de la tabla `proyecto`
--
ALTER TABLE `proyecto`
  ADD PRIMARY KEY (`id_proyecto`),
  ADD KEY `aprobado_por` (`aprobado_por`);

--
-- Indices de la tabla `tipo_actividad`
--
ALTER TABLE `tipo_actividad`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividad_evento`
--
ALTER TABLE `actividad_evento`
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `administrador`
--
ALTER TABLE `administrador`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `alumno`
--
ALTER TABLE `alumno`
  MODIFY `id_alumno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `articulo`
--
ALTER TABLE `articulo`
  MODIFY `id_articulo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `docente`
--
ALTER TABLE `docente`
  MODIFY `id_docente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `empresa`
--
ALTER TABLE `empresa`
  MODIFY `id_empresa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `evento`
--
ALTER TABLE `evento`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `plantilla_impresion`
--
ALTER TABLE `plantilla_impresion`
  MODIFY `id_plantilla` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `proyecto`
--
ALTER TABLE `proyecto`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tipo_actividad`
--
ALTER TABLE `tipo_actividad`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividad_evento`
--
ALTER TABLE `actividad_evento`
  ADD CONSTRAINT `actividad_evento_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE,
  ADD CONSTRAINT `actividad_evento_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `actividad_evento_ibfk_3` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_actividad` (`id_tipo`);

--
-- Filtros para la tabla `alumno`
--
ALTER TABLE `alumno`
  ADD CONSTRAINT `alumno_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `docente`
--
ALTER TABLE `docente`
  ADD CONSTRAINT `docente_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `empresa`
--
ALTER TABLE `empresa`
  ADD CONSTRAINT `empresa_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `evento`
--
ALTER TABLE `evento`
  ADD CONSTRAINT `evento_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `administrador` (`id_admin`);

--
-- Filtros para la tabla `proyecto`
--
ALTER TABLE `proyecto`
  ADD CONSTRAINT `proyecto_ibfk_1` FOREIGN KEY (`aprobado_por`) REFERENCES `administrador` (`id_admin`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
