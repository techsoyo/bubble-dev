-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: db:3306
-- Tiempo de generación: 09-08-2025 a las 16:02:40
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
-- Estructura de tabla para la tabla `bt_chatbot_nodes`
--

CREATE TABLE `bt_chatbot_nodes` (
  `id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('message','options','form','redirect') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'message',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'system'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bt_chatbot_nodes`
--

INSERT INTO `bt_chatbot_nodes` (`id`, `type`, `content`, `metadata`, `is_active`, `created_at`, `updated_at`, `created_by`) VALUES
('application_help', 'message', 'Te guío paso a paso en el proceso de aplicación. ¿En qué necesitas ayuda específicamente?', '{\"delay\": 800, \"typing_indicator\": true}', 1, '2025-08-09 15:46:17', '2025-08-09 15:46:17', 'system'),
('company_info', 'message', 'Me alegra que quieras conocer más sobre nosotros. ¿Qué aspecto de la empresa te interesa?', '{\"delay\": 800, \"typing_indicator\": true}', 1, '2025-08-09 15:46:17', '2025-08-09 15:46:17', 'system'),
('contact_info', 'message', 'Aquí tienes nuestros datos de contacto:\n\n📧 Email: rrhh@bubbleoftalents.com\n📞 Teléfono: +34 900 123 456\n🏢 Dirección: Calle Talento, 123, Madrid\n\n¿Necesitas algo más?', '{\"delay\": 1200, \"typing_indicator\": true}', 1, '2025-08-09 15:46:17', '2025-08-09 15:46:17', 'system'),
('job_search', 'message', 'Perfecto! Te ayudo con la búsqueda de empleo. ¿Qué tipo de información necesitas?', '{\"delay\": 800, \"typing_indicator\": true}', 1, '2025-08-09 15:46:17', '2025-08-09 15:46:17', 'system'),
('welcome', 'message', '¡Hola! 👋 Soy el asistente virtual de Bubble of Talents. ¿En qué puedo ayudarte hoy?', '{\"delay\": 1000, \"analytics_event\": \"chatbot_welcome_shown\", \"typing_indicator\": true}', 1, '2025-08-09 15:46:17', '2025-08-09 15:46:17', 'system');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bt_chatbot_nodes`
--
ALTER TABLE `bt_chatbot_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
