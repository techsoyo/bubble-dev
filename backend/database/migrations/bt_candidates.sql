-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: db:3306
-- Tiempo de generación: 10-08-2025 a las 00:35:38
-- Versión del servidor: 8.0.43
-- Versión de PHP: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bubble_talents_DB`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidates`
--

CREATE TABLE `bt_candidates` (
  `id` char(36) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` text NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `linkedin_url` varchar(2083) DEFAULT NULL,
  `portfolio_url` varchar(2083) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `profile_image` varchar(2083) DEFAULT NULL,
  `available_from` date DEFAULT NULL,
  `desired_salary` decimal(12,2) DEFAULT NULL,
  `desired_contract_type` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `registration_source` varchar(100) NOT NULL,
  `referred_by` char(36) DEFAULT NULL,
  `cv_filename` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidates`
--

INSERT INTO `bt_candidates` (`id`, `first_name`, `last_name`, `name`, `email`, `password_hash`, `phone`, `linkedin_url`, `portfolio_url`, `date_of_birth`, `nationality`, `location`, `profile_image`, `available_from`, `desired_salary`, `desired_contract_type`, `status`, `registration_source`, `referred_by`, `cv_filename`, `created_at`) VALUES
('cnd-201', 'Diego', 'Santos', 'Diego Santos', 'diego.santos@example.com', 'passA!2025', '+34 600200201', 'https://linkedin.com/in/diegosantos', 'https://diegosantos.dev', '1990-02-11', 'España', 'Madrid', NULL, '2025-08-20', 36000.00, 'full-time', 'active', 'import', NULL, 'cv_diego.pdf', '2025-08-08 10:10:00'),
('cnd-202', 'Elena', 'Marín', 'Elena Marín', 'elena.marin@example.com', 'passB!2025', '+34 600200202', 'https://linkedin.com/in/elenamarin', 'https://elenamarin.design', '1993-07-05', 'España', 'Barcelona', NULL, '2025-08-18', 32000.00, 'part-time', 'active', 'import', NULL, 'cv_elena.pdf', '2025-08-08 10:12:00'),
('cnd-203', 'Raúl', 'Campos', 'Raúl Campos', 'raul.campos@example.com', 'passC!2025', '+34 600200203', 'https://linkedin.com/in/raulcampos', 'https://raulcampos.dev', '1988-11-22', 'España', 'Valencia', NULL, '2025-08-25', 42000.00, 'full-time', 'active', 'import', NULL, 'cv_raul.pdf', '2025-08-08 10:14:00'),
('cnd-204', 'Lucía', 'Prieto', 'Lucía Prieto', 'lucia.prieto@example.com', 'passD!2025', '+34 600200204', 'https://linkedin.com/in/luciaprieto', 'https://luciaprieto.io', '1995-03-30', 'España', 'Sevilla', NULL, '2025-08-22', 30000.00, 'full-time', 'active', 'import', NULL, 'cv_lucia.pdf', '2025-08-08 10:16:00'),
('cnd-205', 'Hugo', 'Navas', 'Hugo Navas', 'hugo.navas@example.com', 'passE!2025', '+34 600200205', 'https://linkedin.com/in/hugonavas', 'https://hugonavas.dev', '1991-05-19', 'España', 'Bilbao', NULL, '2025-08-28', 38000.00, 'full-time', 'active', 'import', NULL, 'cv_hugo.pdf', '2025-08-08 10:18:00'),
('cnd-206', 'Nuria', 'Gallego', 'Nuria Gallego', 'nuria.gallego@example.com', 'passF!2025', '+34 600200206', 'https://linkedin.com/in/nuriagallego', 'https://nuriagallego.dev', '1992-09-09', 'España', 'Zaragoza', NULL, '2025-08-26', 29000.00, 'part-time', 'active', 'import', NULL, 'cv_nuria.pdf', '2025-08-08 10:20:00'),
('cnd-207', 'Sergio', 'Iglesias', 'Sergio Iglesias', 'sergio.iglesias@example.com', 'passG!2025', '+34 600200207', 'https://linkedin.com/in/sergioiglesias', 'https://sergioiglesias.dev', '1989-02-05', 'España', 'Granada', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:22:00'),
('cnd-208', 'Beatriz', 'Vega', 'Beatriz Vega', 'beatriz.vega@example.com', 'passH!2025', '+34 600200208', 'https://linkedin.com/in/beatrizvega', 'https://beatrizvega.dev', '1994-09-14', 'España', 'Alicante', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:24:00'),
('cnd-209', 'Alberto', 'Prieto', 'Alberto Prieto', 'alberto.prieto@example.com', 'passI!2025', '+34 600200209', 'https://linkedin.com/in/albertoprieto', 'https://albertoprieto.dev', '1996-12-03', 'España', 'Santander', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:26:00'),
('cnd-210', 'Isabel', 'Moreno', 'Isabel Moreno', 'isabel.moreno@example.com', 'passJ!2025', '+34 600200210', 'https://linkedin.com/in/isabelmoreno', 'https://isabelmoreno.dev', '1990-04-27', 'España', 'Córdoba', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:28:00');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bt_candidates`
--
ALTER TABLE `bt_candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bt_candidates_email` (`email`),
  ADD KEY `fk_bt_candidates_referred_by` (`referred_by`);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bt_candidates`
--
ALTER TABLE `bt_candidates`
  ADD CONSTRAINT `fk_bt_candidates_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `bt_candidates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
