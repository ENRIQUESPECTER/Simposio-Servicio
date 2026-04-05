-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-04-2026 a las 23:49:59
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
  `id_articulo` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `resumen` text DEFAULT NULL,
  `referencias` text DEFAULT NULL,
  `archivo_pdf` varchar(255) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `id_salon` int(11) DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `actividad_evento`
--

INSERT INTO `actividad_evento` (`id_actividad`, `id_evento`, `id_usuario`, `id_tipo`, `id_articulo`, `titulo`, `descripcion`, `resumen`, `referencias`, `archivo_pdf`, `fecha`, `hora_inicio`, `hora_fin`, `id_salon`, `visible`) VALUES
(3, 1, 2, 3, NULL, 'Taller de Desarrollo Web Seguro', 'Taller práctico sobre seguridad en aplicaciones web.', 'Prácticas OWASP.', 'OWASP Top 10', 'taller_web.pdf', '2026-02-26', '11:00:00', '13:00:00', NULL, 1),
(4, 1, 10, 2, NULL, 'Sistemas de software', 'sfsdfsdg', 'sdgsdgsdg', 'dsgsdgsdg', 'uploads/actividades/1773373968_actividad web.pdf', '2026-03-24', '14:00:00', '15:00:00', NULL, 1),
(8, 2, 11, 3, 5, 'PRUEBA GOD', '', 'ESTO SI', '', NULL, '2026-12-24', '17:00:00', '19:00:00', 3, 1),
(28, 2, 11, 4, 28, 'INFINITO', 'nada', 'am', 'nada', 'uploads/actividades/69c0a56bb4731_1774232939_Gantt.pdf', '2026-12-24', '11:30:00', '12:00:00', 3, 1),
(33, 3, 11, 5, 33, 'ROBOT', 'malditoshumanosmonos', 'Prueba de coautores externos', 'odiolavida', 'uploads/actividades/69c20f4ea8796_1774325582_Gantt.pdf', '2026-12-24', '13:30:00', '14:30:00', 4, 1),
(35, 2, 11, 4, 35, 'PRUEBA', 'ejffew', 'djfhusdhhsdi', 'jsfhiesdf', 'uploads/actividades/69c57df443b04_1774550516_Gantt.pdf', '2026-12-24', '11:00:00', '11:30:00', 1, 1),
(37, 3, 11, 5, 36, 'NEW PRUEBA APROBACION', 'nada', 'checar que sirva aprobar y rechazar articulos', 'nada', NULL, '2026-12-24', '10:00:00', '11:00:00', 1, 1),
(38, 3, 11, 1, 37, 'Programación en C#', 'juegos en c#', 'Aprender codigo de C#', 'Fortnite', NULL, '2026-12-24', '11:00:00', '11:30:00', 3, 1);

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
(5, 8, '423080645', 'Informatica', 8),
(6, 11, '999080645', 'Hechiceria', 8);

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
  `id_usuario` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `resumen` text DEFAULT NULL,
  `tipo_trabajo` enum('cartel','ponencia','taller','prototipo') NOT NULL DEFAULT 'ponencia',
  `categoria` varchar(100) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `aprobado_por` int(11) DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `articulo`
--

INSERT INTO `articulo` (`id_articulo`, `id_evento`, `id_usuario`, `titulo`, `resumen`, `tipo_trabajo`, `categoria`, `fecha_registro`, `estado`, `aprobado_por`, `fecha_aprobacion`, `asignado_a`) VALUES
(5, 2, NULL, 'PRUEBA GOD', 'ESTO SI', 'taller', 'ENSEÑANZA DE LAS MATEMÁTICAS', '2026-03-16 22:57:09', 'aprobado', 1, '2026-03-31 16:53:46', NULL),
(10, 3, 8, 'PRUEBA 8', '0', 'ponencia', 'MATEMÁTICAS APLICADAS', '2026-03-17 16:58:29', 'rechazado', 1, '2026-03-26 17:03:13', NULL),
(28, 2, 11, 'INFINITO', 'am', 'cartel', 'MATEMÁTICAS PURAS', '2026-03-23 02:28:59', 'aprobado', 1, '2026-03-26 12:50:14', NULL),
(33, 3, 11, 'ROBOT', 'Prueba de coautores externos', 'prototipo', 'INGENIERÍA', '2026-03-24 04:13:02', 'aprobado', 1, '2026-03-26 11:20:16', NULL),
(35, 2, 11, 'PRUEBA', 'djfhusdhhsdi', 'cartel', 'FÍSICA', '2026-03-26 18:41:56', 'pendiente', NULL, NULL, NULL),
(36, 3, 11, 'NEW PRUEBA APROBACION', 'checar que sirva aprobar y rechazar articulos', 'prototipo', 'FÍSICA', '2026-03-31 22:57:07', 'pendiente', 1, '2026-04-02 13:32:31', NULL),
(37, 3, 11, 'Programación en C#', 'Aprender codigo de C#', 'ponencia', 'COMPUTACIÓN', '2026-04-03 03:02:09', 'pendiente', 1, '2026-04-02 23:21:43', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo_alumno`
--

CREATE TABLE `articulo_alumno` (
  `id_articulo` int(11) NOT NULL,
  `id_alumno` int(11) NOT NULL,
  `rol` enum('autor','coautor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `articulo_alumno`
--

INSERT INTO `articulo_alumno` (`id_articulo`, `id_alumno`, `rol`) VALUES
(5, 6, 'autor'),
(10, 5, 'autor'),
(28, 6, 'autor'),
(33, 6, 'autor'),
(35, 6, 'autor'),
(36, 6, 'autor'),
(37, 6, 'autor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo_docente`
--

CREATE TABLE `articulo_docente` (
  `id_articulo` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `coautor_externo`
--

CREATE TABLE `coautor_externo` (
  `id_coautor` int(11) NOT NULL,
  `id_articulo` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `rfc` varchar(13) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `institucion` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `coautor_externo`
--

INSERT INTO `coautor_externo` (`id_coautor`, `id_articulo`, `nombre`, `rfc`, `email`, `institucion`) VALUES
(42, 33, 'Daniel Farfan', 'KARY040712T37', 'karyrap@gmail.com', 'UNAM'),
(45, 35, 'Daniel Farfan', 'KARY040712T37', 'karyrap@gmail.com', 'UNAM'),
(46, 5, 'Daniel Farfan', NULL, NULL, NULL),
(52, 28, 'Daniel Farfan', NULL, NULL, NULL);

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
(2, 12, 'Sony Japon', 'Tecnología');

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
(3, 'Sistemas de software', 'sdgfdsfgsdgsd', '2026-12-24', '10:00:00', '19:00:00', 2026, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horario_ponencia`
--

CREATE TABLE `horario_ponencia` (
  `id_horario` int(11) NOT NULL,
  `id_articulo` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `salon` varchar(50) DEFAULT NULL,
  `estado` enum('disponible','ocupado','cancelado') DEFAULT 'ocupado',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Estructura de tabla para la tabla `proyecto_imagen`
--

CREATE TABLE `proyecto_imagen` (
  `id_imagen` int(11) NOT NULL,
  `id_articulo` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `archivo_original` varchar(255) NOT NULL,
  `tipo_imagen` varchar(50) NOT NULL,
  `tamaño` int(11) NOT NULL,
  `es_principal` tinyint(1) DEFAULT 0,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `proyecto_imagen`
--

INSERT INTO `proyecto_imagen` (`id_imagen`, `id_articulo`, `nombre_archivo`, `archivo_original`, `tipo_imagen`, `tamaño`, `es_principal`, `fecha_subida`) VALUES
(10, 5, '69ba109a1b4e8_1773801626.jpg', 'AKAZA INFINITY CASTLE.jpg', '', 468999, 1, '2026-03-18 02:40:26'),
(38, 33, '69c20f4ea9550_1774325582.jpg', 'SATORU VS SUKUNA DOMAINS.jpg', '', 116891, 1, '2026-03-24 04:13:02'),
(39, 5, '69c3121317dad_1774391827.jpg', 'SATORU WALLPAPER.jpg', '', 236311, 0, '2026-03-24 22:37:07'),
(41, 35, '69c57df445774_1774550516.png', 'CSS.png', '', 38197, 1, '2026-03-26 18:41:56'),
(43, 36, '69cc51433fb53_1774997827.jpg', 'akaza_saludo.jpg', '', 8431, 1, '2026-03-31 22:57:07'),
(47, 28, '69cc56b62e056_1774999222.jpg', 'SATORU POSE COMBATE.jpg', '', 306046, 1, '2026-03-31 23:20:22'),
(48, 37, '69cf2db13ee3a_1775185329.jpg', 'TOJI.jpg', '', 73365, 1, '2026-04-03 03:02:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `salones`
--

CREATE TABLE `salones` (
  `id_salon` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `capacidad` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `salones`
--

INSERT INTO `salones` (`id_salon`, `nombre`, `ubicacion`, `capacidad`, `activo`) VALUES
(1, 'Auditorio Principal', 'Edificio A', 200, 1),
(2, 'Jaime Keller', 'Edificio B', 50, 1),
(3, 'Aula Magna', 'Edificio B', 50, 1),
(4, 'Sala de Conferencias', 'Edificio C', 100, 1);

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
(3, 'Taller', 120),
(4, 'Cartel', 30),
(5, 'Prototipo', 60);

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
(4, 'luigi@gmail.com', '$2y$10$7bJbzqKgtG5in1IhzGK1r.iGnDwMlrGWvzGp02XnnRXFYJPltfRWe', 'Luigi', 'Padilla', 'Villa de las Flores', 'alumno'),
(8, 'luigienrique04@gmail.com', '$2y$10$gycHz9xfs8fVjA/9f6HUM.dExr6aTx8EEkMiFY96OgX0AlJaelHWi', 'Luis Enrique', 'Padilla Salmoran', 'Coacalco de Berriozabal', 'alumno'),
(10, 'cynthia@gmail.com', '$2y$10$dvOvsyy2Xm7Sbj0scD9eS.hhdf/uEclKKO2SwwTT0TtNZ9pmFMIby', 'Cynthia', 'Leticia Otero', 'Polanco CDMX', 'docente'),
(11, 'jujutsu@gmail.com', '$2y$10$pHoXWSC0RazBX1JTzSzRQOl8Bteorn9mewO93VEaliQbOKGNG6ba6', 'Satoru', 'Gojo', 'Tokio, Japón', 'alumno'),
(12, 'suguru@gmail.com', '$2y$10$y0/MUI8rlr3GG55MCnsv4.RG.8pUJ0tpNpBOPy.iQzeloZ0FxRc2q', 'Suguru', 'Geto', 'Tokio, Japón', 'empresa');

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
  ADD KEY `id_tipo` (`id_tipo`),
  ADD KEY `actividad_evento_ibfk_4` (`id_articulo`),
  ADD KEY `id_salon` (`id_salon`);

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
  ADD PRIMARY KEY (`id_articulo`),
  ADD KEY `articulo_ibfk_usuario` (`id_usuario`),
  ADD KEY `articulo_ibfk_admin` (`aprobado_por`);

--
-- Indices de la tabla `articulo_alumno`
--
ALTER TABLE `articulo_alumno`
  ADD PRIMARY KEY (`id_articulo`,`id_alumno`),
  ADD KEY `id_alumno` (`id_alumno`);

--
-- Indices de la tabla `articulo_docente`
--
ALTER TABLE `articulo_docente`
  ADD PRIMARY KEY (`id_articulo`,`id_docente`),
  ADD KEY `id_docente` (`id_docente`);

--
-- Indices de la tabla `coautor_externo`
--
ALTER TABLE `coautor_externo`
  ADD PRIMARY KEY (`id_coautor`),
  ADD KEY `id_articulo` (`id_articulo`);

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
-- Indices de la tabla `horario_ponencia`
--
ALTER TABLE `horario_ponencia`
  ADD PRIMARY KEY (`id_horario`),
  ADD UNIQUE KEY `horario_unico` (`fecha`,`hora_inicio`,`hora_fin`),
  ADD KEY `id_articulo` (`id_articulo`);

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
-- Indices de la tabla `proyecto_imagen`
--
ALTER TABLE `proyecto_imagen`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `id_articulo` (`id_articulo`);

--
-- Indices de la tabla `salones`
--
ALTER TABLE `salones`
  ADD PRIMARY KEY (`id_salon`),
  ADD UNIQUE KEY `nombre` (`nombre`);

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
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `administrador`
--
ALTER TABLE `administrador`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `alumno`
--
ALTER TABLE `alumno`
  MODIFY `id_alumno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `articulo`
--
ALTER TABLE `articulo`
  MODIFY `id_articulo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `coautor_externo`
--
ALTER TABLE `coautor_externo`
  MODIFY `id_coautor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de la tabla `docente`
--
ALTER TABLE `docente`
  MODIFY `id_docente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `empresa`
--
ALTER TABLE `empresa`
  MODIFY `id_empresa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `evento`
--
ALTER TABLE `evento`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `horario_ponencia`
--
ALTER TABLE `horario_ponencia`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT de la tabla `proyecto_imagen`
--
ALTER TABLE `proyecto_imagen`
  MODIFY `id_imagen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de la tabla `salones`
--
ALTER TABLE `salones`
  MODIFY `id_salon` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tipo_actividad`
--
ALTER TABLE `tipo_actividad`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividad_evento`
--
ALTER TABLE `actividad_evento`
  ADD CONSTRAINT `actividad_evento_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE,
  ADD CONSTRAINT `actividad_evento_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `actividad_evento_ibfk_3` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_actividad` (`id_tipo`),
  ADD CONSTRAINT `actividad_evento_ibfk_4` FOREIGN KEY (`id_articulo`) REFERENCES `articulo` (`id_articulo`) ON DELETE SET NULL,
  ADD CONSTRAINT `actividad_evento_ibfk_5` FOREIGN KEY (`id_salon`) REFERENCES `salones` (`id_salon`) ON DELETE SET NULL;

--
-- Filtros para la tabla `alumno`
--
ALTER TABLE `alumno`
  ADD CONSTRAINT `alumno_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `articulo`
--
ALTER TABLE `articulo`
  ADD CONSTRAINT `articulo_ibfk_1` FOREIGN KEY (`asignado_a`) REFERENCES `docente` (`id_docente`) ON DELETE SET NULL,
  ADD CONSTRAINT `articulo_ibfk_admin` FOREIGN KEY (`aprobado_por`) REFERENCES `administrador` (`id_admin`) ON DELETE SET NULL,
  ADD CONSTRAINT `articulo_ibfk_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `articulo_alumno`
--
ALTER TABLE `articulo_alumno`
  ADD CONSTRAINT `articulo_alumno_ibfk_1` FOREIGN KEY (`id_articulo`) REFERENCES `articulo` (`id_articulo`) ON DELETE CASCADE,
  ADD CONSTRAINT `articulo_alumno_ibfk_2` FOREIGN KEY (`id_alumno`) REFERENCES `alumno` (`id_alumno`) ON DELETE CASCADE;

--
-- Filtros para la tabla `articulo_docente`
--
ALTER TABLE `articulo_docente`
  ADD CONSTRAINT `articulo_docente_ibfk_1` FOREIGN KEY (`id_articulo`) REFERENCES `articulo` (`id_articulo`) ON DELETE CASCADE,
  ADD CONSTRAINT `articulo_docente_ibfk_2` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id_docente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `coautor_externo`
--
ALTER TABLE `coautor_externo`
  ADD CONSTRAINT `coautor_externo_ibfk_1` FOREIGN KEY (`id_articulo`) REFERENCES `articulo` (`id_articulo`) ON DELETE CASCADE;

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
-- Filtros para la tabla `horario_ponencia`
--
ALTER TABLE `horario_ponencia`
  ADD CONSTRAINT `horario_ponencia_ibfk_1` FOREIGN KEY (`id_articulo`) REFERENCES `articulo` (`id_articulo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `proyecto`
--
ALTER TABLE `proyecto`
  ADD CONSTRAINT `proyecto_ibfk_1` FOREIGN KEY (`aprobado_por`) REFERENCES `administrador` (`id_admin`);

--
-- Filtros para la tabla `proyecto_imagen`
--
ALTER TABLE `proyecto_imagen`
  ADD CONSTRAINT `proyecto_imagen_ibfk_1` FOREIGN KEY (`id_articulo`) REFERENCES `articulo` (`id_articulo`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
