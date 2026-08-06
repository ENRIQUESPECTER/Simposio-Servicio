-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 06-08-2026 a las 23:01:56
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
-- Base de datos: `constancias`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_constancias`
--

CREATE TABLE `solicitudes_constancias` (
  `id` int(11) NOT NULL,
  `nombre_completo` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `rol` enum('ponente','staff','asistente') NOT NULL,
  `nombre_conferencia` varchar(255) DEFAULT NULL,
  `rol_staff` varchar(100) DEFAULT NULL,
  `fecha_participacion` date NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_id` int(11) DEFAULT NULL,
  `fecha_aprobacion` timestamp NULL DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `solicitudes_constancias`
--

INSERT INTO `solicitudes_constancias` (`id`, `nombre_completo`, `email`, `rol`, `nombre_conferencia`, `rol_staff`, `fecha_participacion`, `estado`, `fecha_solicitud`, `admin_id`, `fecha_aprobacion`, `observaciones`) VALUES
(1, 'Luis Enrique Padilla Salmoran', 'guarnizoluis918@gmail.com', 'ponente', 'JUJUTSU KAISEN', '', '2026-05-27', 'aprobada', '2026-05-24 18:38:41', 1, '2026-08-06 17:33:07', 'FALTA CUARTILLA'),
(2, 'Daniel Farfan Simon', 'karyrap@gmail.com', 'staff', '', 'Seguridad', '2026-05-29', 'pendiente', '2026-05-24 19:03:20', NULL, NULL, NULL),
(5, 'Satoru Gojo', 'jujutsu@gmail.com', '', 'Jujutsu Kaisen', '', '2026-12-24', 'aprobada', '2026-06-02 05:55:06', 1, '2026-06-03 02:52:03', NULL),
(6, 'Satoru Gojo', 'jujutsu@gmail.com', '', 'Jujutsu Kaisen', '', '2026-12-24', 'rechazada', '2026-08-06 18:30:07', 1, '2026-08-06 18:31:03', '');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `solicitudes_constancias`
--
ALTER TABLE `solicitudes_constancias`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `solicitudes_constancias`
--
ALTER TABLE `solicitudes_constancias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
