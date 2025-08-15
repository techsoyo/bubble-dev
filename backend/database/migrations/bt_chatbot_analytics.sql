-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: db:3306
-- Tiempo de generación: 09-08-2025 a las 16:02:34
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
-- Estructura de tabla para la tabla `bt_chatbot_analytics`
--

CREATE TABLE `bt_chatbot_analytics` (
  `id` bigint UNSIGNED NOT NULL,
  `conversation_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_data` json DEFAULT NULL,
  `node_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bt_chatbot_analytics`
--
ALTER TABLE `bt_chatbot_analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `option_id` (`option_id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_event_name` (`event_name`),
  ADD KEY `idx_node_id` (`node_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `bt_chatbot_analytics`
--
ALTER TABLE `bt_chatbot_analytics`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bt_chatbot_analytics`
--
ALTER TABLE `bt_chatbot_analytics`
  ADD CONSTRAINT `bt_chatbot_analytics_ibfk_1` FOREIGN KEY (`node_id`) REFERENCES `bt_chatbot_nodes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bt_chatbot_analytics_ibfk_2` FOREIGN KEY (`option_id`) REFERENCES `bt_chatbot_options` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
