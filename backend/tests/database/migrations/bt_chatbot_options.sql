-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: db:3306
-- Tiempo de generación: 09-08-2025 a las 16:02:47
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
-- Estructura de tabla para la tabla `bt_chatbot_options`
--

CREATE TABLE `bt_chatbot_options` (
  `id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `node_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `next_node_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_type` enum('navigate','submit','restart','end') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'navigate',
  `action_data` json DEFAULT NULL,
  `order_position` int NOT NULL DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bt_chatbot_options`
--

INSERT INTO `bt_chatbot_options` (`id`, `node_id`, `text`, `next_node_id`, `action_type`, `action_data`, `order_position`, `is_active`, `created_at`, `updated_at`) VALUES
('opt_1', 'welcome', '🔍 Buscar empleo', 'job_search', 'navigate', '{\"analytics_event\": \"chatbot_job_search_selected\"}', 1, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_10', 'company_info', '🔙 Volver al inicio', 'welcome', 'restart', '{\"analytics_event\": \"chatbot_restart_from_company\"}', 3, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_11', 'application_help', '📝 Crear CV perfecto', NULL, 'navigate', '{\"url\": \"/cv-tips\", \"analytics_event\": \"chatbot_cv_tips_redirect\"}', 1, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_12', 'application_help', '💡 Consejos de entrevista', NULL, 'navigate', '{\"url\": \"/interview-tips\", \"analytics_event\": \"chatbot_interview_tips_redirect\"}', 2, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_13', 'application_help', '🔙 Volver al inicio', 'welcome', 'restart', '{\"analytics_event\": \"chatbot_restart_from_help\"}', 3, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_14', 'contact_info', '🔙 Volver al inicio', 'welcome', 'restart', '{\"analytics_event\": \"chatbot_restart_from_contact\"}', 1, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_2', 'welcome', '🏢 Información de la empresa', 'company_info', 'navigate', '{\"analytics_event\": \"chatbot_company_info_selected\"}', 2, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_3', 'welcome', '📝 Ayuda con aplicaciones', 'application_help', 'navigate', '{\"analytics_event\": \"chatbot_application_help_selected\"}', 3, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_4', 'welcome', '📞 Contacto', 'contact_info', 'navigate', '{\"analytics_event\": \"chatbot_contact_selected\"}', 4, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_5', 'job_search', '💼 Ver ofertas disponibles', NULL, 'navigate', '{\"url\": \"/jobs\", \"analytics_event\": \"chatbot_jobs_redirect\"}', 1, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_6', 'job_search', '📋 Crear perfil de candidato', NULL, 'navigate', '{\"url\": \"/register\", \"analytics_event\": \"chatbot_register_redirect\"}', 2, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_7', 'job_search', '🔙 Volver al inicio', 'welcome', 'restart', '{\"analytics_event\": \"chatbot_restart_from_jobs\"}', 3, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_8', 'company_info', '🌟 Cultura empresarial', NULL, 'navigate', '{\"url\": \"/#culture-heading\", \"analytics_event\": \"chatbot_culture_redirect\"}', 1, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44'),
('opt_9', 'company_info', '📰 Noticias y blog', NULL, 'navigate', '{\"url\": \"/blog\", \"analytics_event\": \"chatbot_blog_redirect\"}', 2, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bt_chatbot_options`
--
ALTER TABLE `bt_chatbot_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_node_id` (`node_id`),
  ADD KEY `idx_next_node_id` (`next_node_id`),
  ADD KEY `idx_order_position` (`order_position`),
  ADD KEY `idx_active` (`is_active`);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bt_chatbot_options`
--
ALTER TABLE `bt_chatbot_options`
  ADD CONSTRAINT `bt_chatbot_options_ibfk_1` FOREIGN KEY (`node_id`) REFERENCES `bt_chatbot_nodes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bt_chatbot_options_ibfk_2` FOREIGN KEY (`next_node_id`) REFERENCES `bt_chatbot_nodes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
