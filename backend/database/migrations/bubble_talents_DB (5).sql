-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: db:3306
-- Tiempo de generación: 10-08-2025 a las 08:51:49
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
-- Estructura de tabla para la tabla `bt_applications`
--

CREATE TABLE `bt_applications` (
  `id` char(36) NOT NULL,
  `job_id` char(36) NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `status` varchar(50) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resume` varchar(2083) DEFAULT NULL,
  `cover_letter` text,
  `insights` json DEFAULT NULL,
  `source` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_applications`
--

INSERT INTO `bt_applications` (`id`, `job_id`, `candidate_id`, `status`, `score`, `updated_at`, `resume`, `cover_letter`, `insights`, `source`, `created_at`) VALUES
('app-301', 'job-100', 'cnd-201', 'applied', NULL, '2025-08-06 09:00:00', 'https://files.example.com/cv_diego.pdf', 'Interesado en backend', NULL, 'website', '2025-08-08 20:31:46'),
('app-302', 'job-101', 'cnd-201', 'interview', NULL, '2025-08-08 11:00:00', 'https://files.example.com/cv_diego.pdf', 'Experiencia en React/Node', NULL, 'website', '2025-08-08 20:31:46'),
('app-303', 'job-106', 'cnd-202', 'applied', NULL, '2025-08-06 12:00:00', 'https://files.example.com/cv_elena.pdf', 'Portfolio adjunto', NULL, 'website', '2025-08-08 20:31:46'),
('app-304', 'job-105', 'cnd-203', 'interview', NULL, '2025-08-07 15:30:00', 'https://files.example.com/cv_raul.pdf', 'Fuerte en datos', NULL, 'website', '2025-08-08 20:31:46'),
('app-305', 'job-104', 'cnd-203', 'applied', NULL, '2025-08-08 09:30:00', 'https://files.example.com/cv_raul.pdf', 'DevOps/ETL', NULL, 'website', '2025-08-08 20:31:46'),
('app-306', 'job-102', 'cnd-204', 'hired', NULL, '2025-08-08 10:00:00', 'https://files.example.com/cv_lucia.pdf', 'Experiencia UI', NULL, 'website', '2025-08-08 20:31:46'),
('app-307', 'job-103', 'cnd-205', 'applied', NULL, '2025-08-06 16:00:00', 'https://files.example.com/cv_hugo.pdf', 'DevOps en cloud', NULL, 'website', '2025-08-08 20:31:46'),
('app-308', 'job-104', 'cnd-205', 'interview', NULL, '2025-08-08 12:20:00', 'https://files.example.com/cv_hugo.pdf', 'K8s/CI-CD', NULL, 'website', '2025-08-08 20:31:46'),
('app-309', 'job-101', 'cnd-206', 'applied', NULL, '2025-08-08 12:00:00', 'https://files.example.com/cv_nuria.pdf', 'Analítica digital', NULL, 'website', '2025-08-08 20:31:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_application_notes`
--

CREATE TABLE `bt_application_notes` (
  `application_id` char(36) NOT NULL,
  `note_idx` smallint NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_application_notes`
--

INSERT INTO `bt_application_notes` (`application_id`, `note_idx`, `note`, `created_at`, `updated_at`) VALUES
('app-301', 0, 'Enviar prueba técnica', '2025-08-08 20:30:22', '2025-08-08 20:30:22'),
('app-302', 0, 'Feedback positivo inicial', '2025-08-08 20:30:22', '2025-08-08 20:30:22'),
('app-304', 0, 'Programar entrevista técnica', '2025-08-08 20:30:22', '2025-08-08 20:30:22'),
('app-306', 0, 'Oferta aceptada', '2025-08-08 20:30:22', '2025-08-08 20:30:22'),
('app-308', 0, 'Pendiente de feedback del hiring manager', '2025-08-08 20:30:22', '2025-08-08 20:30:22');

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
('cnd-201', 'Diego', 'Santos', 'Diego Santos', 'diego.santos@example.com', '$2y$12$kNBH6mQXuvEOZ/4o.JDb4.Z0ZIR8KCYy4eHYWxkXc27hv5CwCwbm2', '+34 600200201', 'https://linkedin.com/in/diegosantos', 'https://diegosantos.dev', '1990-02-11', 'España', 'Madrid', NULL, '2025-08-20', 36000.00, 'full-time', 'active', 'import', NULL, 'cv_diego.pdf', '2025-08-08 10:10:00'),
('cnd-202', 'Elena', 'Marín', 'Elena Marín', 'elena.marin@example.com', '$2y$12$rRna1vzblJAB.un1vJ8ty.XwFJFrPmEsJD.vmoIIK8bfu7B2WI8ya', '+34 600200202', 'https://linkedin.com/in/elenamarin', 'https://elenamarin.design', '1993-07-05', 'España', 'Barcelona', NULL, '2025-08-18', 32000.00, 'part-time', 'active', 'import', NULL, 'cv_elena.pdf', '2025-08-08 10:12:00'),
('cnd-203', 'Raúl', 'Campos', 'Raúl Campos', 'raul.campos@example.com', '$2y$12$iOvg1eNUcq8GS9W5IGivYOdcoqTOjU5aSgCILRMo3qdmotJh2PIU2', '+34 600200203', 'https://linkedin.com/in/raulcampos', 'https://raulcampos.dev', '1988-11-22', 'España', 'Valencia', NULL, '2025-08-25', 42000.00, 'full-time', 'active', 'import', NULL, 'cv_raul.pdf', '2025-08-08 10:14:00'),
('cnd-204', 'Lucía', 'Prieto', 'Lucía Prieto', 'lucia.prieto@example.com', '$2y$12$ZuTnQDM.ldb9brYrFhI3xOaIrlNbWiu3TehXHgtt42tPXgPmzbyS6', '+34 600200204', 'https://linkedin.com/in/luciaprieto', 'https://luciaprieto.io', '1995-03-30', 'España', 'Sevilla', NULL, '2025-08-22', 30000.00, 'full-time', 'active', 'import', NULL, 'cv_lucia.pdf', '2025-08-08 10:16:00'),
('cnd-205', 'Hugo', 'Navas', 'Hugo Navas', 'hugo.navas@example.com', '$2y$12$pXpCid428HrJNCGZCsjfsuY0rW6.lnxZjYiqZ3RHe/Mg/bdMrvlH.', '+34 600200205', 'https://linkedin.com/in/hugonavas', 'https://hugonavas.dev', '1991-05-19', 'España', 'Bilbao', NULL, '2025-08-28', 38000.00, 'full-time', 'active', 'import', NULL, 'cv_hugo.pdf', '2025-08-08 10:18:00'),
('cnd-206', 'Nuria', 'Gallego', 'Nuria Gallego', 'nuria.gallego@example.com', '$2y$12$fvZmIoPXfWQlCVKZjUemfO65AQL37X39aDwoIYvH0w19jYSIHlXY2', '+34 600200206', 'https://linkedin.com/in/nuriagallego', 'https://nuriagallego.dev', '1992-09-09', 'España', 'Zaragoza', NULL, '2025-08-26', 29000.00, 'part-time', 'active', 'import', NULL, 'cv_nuria.pdf', '2025-08-08 10:20:00'),
('cnd-207', 'Sergio', 'Iglesias', 'Sergio Iglesias', 'sergio.iglesias@example.com', '$2y$12$soyjefI72zAwV8QryqGsi.HeQQzwdKXOPgh9atBIUi5BmBscM/vsm', '+34 600200207', 'https://linkedin.com/in/sergioiglesias', 'https://sergioiglesias.dev', '1989-02-05', 'España', 'Granada', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:22:00'),
('cnd-208', 'Beatriz', 'Vega', 'Beatriz Vega', 'beatriz.vega@example.com', '$2y$12$WHiUotCT5GSr/x5STCR1KeWvYKnm0T2tJQ3LUCrNACi5tDtL.K4g2', '+34 600200208', 'https://linkedin.com/in/beatrizvega', 'https://beatrizvega.dev', '1994-09-14', 'España', 'Alicante', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:24:00'),
('cnd-209', 'Alberto', 'Prieto', 'Alberto Prieto', 'alberto.prieto@example.com', '$2y$12$P5KKSGtvvWZLGlALthZudeolxLZtSPPXeDxFHHp.lZb7GbYUfw0je', '+34 600200209', 'https://linkedin.com/in/albertoprieto', 'https://albertoprieto.dev', '1996-12-03', 'España', 'Santander', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:26:00'),
('cnd-210', 'Isabel', 'Moreno', 'Isabel Moreno', 'isabel.moreno@example.com', '$2y$12$dChKpG0SR7uAWRLBO64Xo.zlY1BHPJO06R2S1UCrK3eU2qqgrboNO', '+34 600200210', 'https://linkedin.com/in/isabelmoreno', 'https://isabelmoreno.dev', '1990-04-27', 'España', 'Córdoba', NULL, NULL, NULL, NULL, 'active', 'manual', NULL, NULL, '2025-08-08 10:28:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_certifications`
--

CREATE TABLE `bt_candidate_certifications` (
  `id` char(36) NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `certification_name` varchar(255) NOT NULL,
  `issuer` varchar(255) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidate_certifications`
--

INSERT INTO `bt_candidate_certifications` (`id`, `candidate_id`, `certification_name`, `issuer`, `issue_date`, `expiry_date`) VALUES
('cer-201', 'cnd-201', 'AWS Solutions Architect', 'AWS', '2023-05-01', '2026-05-01'),
('cer-202', 'cnd-203', 'Scrum Master', 'Scrum.org', '2022-03-10', NULL),
('cer-203', 'cnd-205', 'Google Data Analytics', 'Google', '2024-01-15', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_education`
--

CREATE TABLE `bt_candidate_education` (
  `id` char(36) NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `degree` varchar(255) NOT NULL,
  `field_of_study` varchar(255) DEFAULT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidate_education`
--

INSERT INTO `bt_candidate_education` (`id`, `candidate_id`, `degree`, `field_of_study`, `institution`, `start_date`, `end_date`, `education_level`) VALUES
('edu-201', 'cnd-201', 'Grado en Ingeniería Informática', 'Informática', 'UCM', '2008-09-01', '2012-06-30', 'Grado'),
('edu-202', 'cnd-202', 'Grado en Diseño', 'Diseño', 'UB', '2013-09-01', '2017-06-30', 'Grado'),
('edu-203', 'cnd-203', 'Grado en Matemáticas', 'Matemáticas', 'UV', '2007-09-01', '2011-06-30', 'Grado'),
('edu-204', 'cnd-204', 'Máster en UX/UI', 'Diseño', 'US', '2018-10-01', '2019-07-15', 'Máster'),
('edu-205', 'cnd-205', 'Grado en Informática', 'Informática', 'UPV/EHU', '2009-09-01', '2013-06-30', 'Grado'),
('edu-206', 'cnd-206', 'Grado en ADE', 'Administración', 'UZ', '2010-09-01', '2014-06-30', 'Grado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_experiences`
--

CREATE TABLE `bt_candidate_experiences` (
  `id` int NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `company` varchar(200) NOT NULL,
  `position` varchar(200) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `current` tinyint(1) DEFAULT '0',
  `description` text,
  `location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidate_experiences`
--

INSERT INTO `bt_candidate_experiences` (`id`, `candidate_id`, `company`, `position`, `start_date`, `end_date`, `current`, `description`, `location`) VALUES
(7, 'cnd-201', 'TechCorp', 'Backend Developer', '2022-02-01', '2024-06-30', 0, 'APIs y microservicios', 'Madrid'),
(8, 'cnd-201', 'StartupX', 'Full Stack Dev', '2024-07-01', NULL, 1, 'Producto SaaS React/Node', 'Remoto'),
(9, 'cnd-202', 'Creativa', 'UX/UI Designer', '2021-04-01', '2024-03-31', 0, 'Componentes y design system', 'Barcelona'),
(10, 'cnd-203', 'DataWorks', 'Data Engineer', '2020-01-01', '2024-05-31', 0, 'ETL y pipelines', 'Valencia'),
(11, 'cnd-204', 'AppLab', 'Frontend Engineer', '2023-02-01', NULL, 1, 'SPA React + testing', 'Sevilla'),
(12, 'cnd-205', 'SecureOps', 'DevOps Engineer', '2019-01-01', '2023-12-31', 0, 'CI/CD y Kubernetes', 'Bilbao'),
(13, 'cnd-206', 'Marketly', 'Marketing Analyst', '2022-06-01', NULL, 1, 'Analítica y CRO', 'Zaragoza');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_languages`
--

CREATE TABLE `bt_candidate_languages` (
  `id` char(36) NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `language` varchar(100) NOT NULL,
  `proficiency_level` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidate_languages`
--

INSERT INTO `bt_candidate_languages` (`id`, `candidate_id`, `language`, `proficiency_level`) VALUES
('lan-201', 'cnd-201', 'Inglés', 'C1'),
('lan-202', 'cnd-201', 'Francés', 'B1'),
('lan-203', 'cnd-202', 'Inglés', 'B2'),
('lan-204', 'cnd-203', 'Inglés', 'C1'),
('lan-205', 'cnd-204', 'Inglés', 'B2'),
('lan-206', 'cnd-205', 'Inglés', 'C1'),
('lan-207', 'cnd-206', 'Inglés', 'B2');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_references`
--

CREATE TABLE `bt_candidate_references` (
  `id` char(36) NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `ref_name` varchar(255) NOT NULL,
  `ref_company` varchar(255) DEFAULT NULL,
  `ref_email` varchar(255) DEFAULT NULL,
  `ref_phone` varchar(50) DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidate_references`
--

INSERT INTO `bt_candidate_references` (`id`, `candidate_id`, `ref_name`, `ref_company`, `ref_email`, `ref_phone`, `notes`) VALUES
('ref-201', 'cnd-201', 'Luis López', 'TechCorp', 'luis.lopez@techcorp.com', '+34 600555666', 'Supervisor directo'),
('ref-202', 'cnd-202', 'Marta Sánchez', 'Creativa', 'marta.sanchez@creativa.com', '+34 600777888', 'Lideró su equipo'),
('ref-203', 'cnd-203', 'Ana Torres', 'Innovatech', 'ana.torres@innovatech.com', '+34 600999000', 'Proyecto crítico'),
('ref-204', 'cnd-204', 'Carlos Vega', 'UXStudio', 'carlos.vega@uxstudio.com', '+34 600333222', 'Diseño mobile');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_routing`
--

CREATE TABLE `bt_candidate_routing` (
  `id` char(36) NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `department_category_id` int NOT NULL,
  `department_id` int NOT NULL,
  `recruiter_id` char(36) DEFAULT NULL,
  `source` enum('ai','manual') NOT NULL DEFAULT 'ai',
  `reason` varchar(255) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidate_routing`
--

INSERT INTO `bt_candidate_routing` (`id`, `candidate_id`, `department_category_id`, `department_id`, `recruiter_id`, `source`, `reason`, `assigned_at`) VALUES
('339e0a60-749b-11f0-9dc0-a660c08da2b5', 'cnd-201', 3, 2, NULL, 'ai', 'skills: Node.js, APIs', '2025-08-08 21:03:58'),
('339e1faa-749b-11f0-9dc0-a660c08da2b5', 'cnd-202', 4, 2, NULL, 'ai', 'skills: React, UI', '2025-08-08 21:03:58'),
('339e25f8-749b-11f0-9dc0-a660c08da2b5', 'cnd-203', 5, 3, 'rec-502', 'ai', 'skills: CRM, ventas', '2025-08-08 21:03:58'),
('339e29d7-749b-11f0-9dc0-a660c08da2b5', 'cnd-204', 7, 5, 'rec-504', 'ai', 'skills: contabilidad, ERP', '2025-08-08 21:03:58'),
('339e2b26-749b-11f0-9dc0-a660c08da2b5', 'cnd-205', 8, 6, 'rec-505', 'ai', 'skills: entrevistas, sourcing', '2025-08-08 21:03:58'),
('339e2fc8-749b-11f0-9dc0-a660c08da2b5', 'cnd-206', 1, 1, NULL, 'ai', 'perfil ops; pendiente de recruiter', '2025-08-08 21:03:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_skills`
--

CREATE TABLE `bt_candidate_skills` (
  `candidate_id` char(36) NOT NULL,
  `skill` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_candidate_skills`
--

INSERT INTO `bt_candidate_skills` (`candidate_id`, `skill`) VALUES
('cnd-201', 'Node.js'),
('cnd-201', 'React'),
('cnd-202', 'Figma'),
('cnd-202', 'UX Research'),
('cnd-203', 'Airflow'),
('cnd-203', 'Python'),
('cnd-204', 'TypeScript'),
('cnd-205', 'Kubernetes'),
('cnd-206', 'Google Analytics');

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
('opt_8', 'company_info', '📰 Noticias y blog', NULL, 'navigate', '{\"url\": \"/blog\", \"analytics_event\": \"chatbot_blog_redirect\"}', 2, 1, '2025-08-09 15:46:44', '2025-08-09 15:46:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_departments`
--

CREATE TABLE `bt_departments` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_departments`
--

INSERT INTO `bt_departments` (`id`, `name`) VALUES
(1, 'Administration'),
(2, 'Engineering'),
(5, 'Finance'),
(6, 'HR'),
(4, 'Marketing'),
(3, 'Sales');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_department_categories`
--

CREATE TABLE `bt_department_categories` (
  `id` int NOT NULL,
  `department_id` int NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_department_categories`
--

INSERT INTO `bt_department_categories` (`id`, `department_id`, `name`) VALUES
(2, 1, 'Finance'),
(1, 1, 'Operations'),
(3, 2, 'Backend'),
(4, 2, 'Digital'),
(5, 3, 'Business'),
(6, 4, 'Digital'),
(7, 5, 'Accounting'),
(8, 6, 'Talent Acquisition');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_interviews`
--

CREATE TABLE `bt_interviews` (
  `id` char(36) NOT NULL,
  `application_id` char(36) NOT NULL,
  `recruiter_id` char(36) DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `duration_minutes` int NOT NULL,
  `interview_type` enum('online','onsite','phone') NOT NULL DEFAULT 'online',
  `location` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `notes` text,
  `meeting_link` varchar(2083) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_interviews`
--

INSERT INTO `bt_interviews` (`id`, `application_id`, `recruiter_id`, `scheduled_at`, `duration_minutes`, `interview_type`, `location`, `type`, `status`, `notes`, `meeting_link`, `created_at`, `updated_at`) VALUES
('int-401', 'app-302', 'rec-502', '2025-08-12 10:00:00', 60, 'online', 'https://meet.example.com/diego_backend', 'technical', 'scheduled', 'Entrevista técnica con equipo de backend', 'https://meet.example.com/diego_backend', '2025-08-08 20:30:53', '2025-08-08 20:30:53'),
('int-402', 'app-304', 'rec-503', '2025-08-13 15:30:00', 45, 'onsite', 'Oficina Innovatech, Valencia', 'practical', 'scheduled', 'Prueba práctica de ETL y SQL', NULL, '2025-08-08 20:30:53', '2025-08-08 20:30:53'),
('int-403', 'app-308', 'rec-504', '2025-08-14 09:00:00', 30, 'phone', '+34 600123456', 'screening', 'scheduled', 'Revisión de experiencia en Kubernetes y CI/CD', NULL, '2025-08-08 20:30:53', '2025-08-08 20:30:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_jobs`
--

CREATE TABLE `bt_jobs` (
  `id` char(36) NOT NULL,
  `title` varchar(200) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `location` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `level` varchar(50) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `salary_currency` varchar(10) DEFAULT NULL,
  `salary_period` varchar(20) DEFAULT NULL,
  `posted_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_jobs`
--

INSERT INTO `bt_jobs` (`id`, `title`, `company_name`, `location`, `type`, `level`, `category`, `description`, `salary_min`, `salary_max`, `salary_currency`, `salary_period`, `posted_at`, `expires_at`, `status`, `created_at`, `is_featured`) VALUES
('job-100', 'Backend Developer', 'Innovatech', 'Barcelona', 'full-time', 'mid', 'Engineering', 'Desarrollo de APIs REST y microservicios.', 40000.00, 55000.00, 'EUR', 'year', '2025-07-15 10:30:00', '2025-09-15 10:30:00', 'open', '2025-08-04 14:18:08', 0),
('job-101', 'Full Stack Developer', 'Bubblegum.agency', 'Madrid', 'full-time', 'mid', 'Engineering', 'Desarrollo de aplicaciones web end-to-end', 40000.00, 55000.00, 'EUR', 'year', '2025-08-01 09:00:00', '2025-10-01 09:00:00', 'open', '2025-08-04 14:20:46', 1),
('job-102', 'Frontend Developer', 'Bubblegum.agency', 'Barcelona', 'full-time', 'senior', 'Engineering', 'Crear interfaces de usuario dinámicas', 45000.00, 60000.00, 'EUR', 'year', '2025-08-02 10:00:00', '2025-10-02 10:00:00', 'open', '2025-08-04 14:20:46', 1),
('job-103', 'Backend Developer', 'Bubblegum.agency', 'Valencia', 'full-time', 'mid', 'Engineering', 'Diseño y construcción de APIs REST', 42000.00, 58000.00, 'EUR', 'year', '2025-08-03 11:00:00', '2025-10-03 11:00:00', 'open', '2025-08-04 14:20:46', 1),
('job-104', 'DevOps Engineer', 'Bubblegum.agency', 'Sevilla', 'full-time', 'senior', 'Operations', 'Automatización de infraestructuras y CI/CD', 47000.00, 65000.00, 'EUR', 'year', '2025-08-04 12:00:00', '2025-10-04 12:00:00', 'open', '2025-08-04 14:20:46', 0),
('job-105', 'Data Scientist', 'Bubblegum.agency', 'Bilbao', 'full-time', 'mid', 'Data', 'Análisis de datos y modelado predictivo', 43000.00, 61000.00, 'EUR', 'year', '2025-08-05 13:00:00', '2025-10-05 13:00:00', 'open', '2025-08-04 14:20:46', 0),
('job-106', 'UX/UI Designer', 'Bubblegum.agency', 'Madrid', 'part-time', 'junior', 'Design', 'Diseño de experiencias de usuario intuitivas', 30000.00, 40000.00, 'EUR', 'year', '2025-08-06 14:00:00', '2025-10-06 14:00:00', 'open', '2025-08-04 14:20:46', 0),
('job-107', 'Product Manager', 'Bubblegum.agency', 'Barcelona', 'full-time', 'senior', 'Management', 'Gestión de roadmap y coordinación de equipos', 50000.00, 70000.00, 'EUR', 'year', '2025-08-07 15:00:00', '2025-10-07 15:00:00', 'open', '2025-08-04 14:20:46', 0),
('job-108', 'QA Engineer', 'Bubblegum.agency', 'Valencia', 'full-time', 'mid', 'Quality Assurance', 'Pruebas automatizadas y manuales de software', 38000.00, 52000.00, 'EUR', 'year', '2025-08-08 16:00:00', '2025-10-08 16:00:00', 'open', '2025-08-04 14:20:46', 0),
('job-109', 'Mobile Developer', 'Bubblegum.agency', 'Sevilla', 'full-time', 'mid', 'Engineering', 'Desarrollo de apps nativas iOS y Android', 44000.00, 60000.00, 'EUR', 'year', '2025-08-09 17:00:00', '2025-10-09 17:00:00', 'open', '2025-08-04 14:20:46', 0),
('job-110', 'Marketing Specialist', 'Bubblegum.agency', 'Bilbao', 'full-time', 'junior', 'Marketing', 'Estrategias de marketing digital y SEO', 32000.00, 45000.00, 'EUR', 'year', '2025-08-10 18:00:00', '2025-10-10 18:00:00', 'open', '2025-08-04 14:20:46', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_job_benefits`
--

CREATE TABLE `bt_job_benefits` (
  `id` int NOT NULL,
  `job_id` char(36) NOT NULL,
  `benefit` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_job_benefits`
--

INSERT INTO `bt_job_benefits` (`id`, `job_id`, `benefit`) VALUES
(1, 'job-101', 'Seguro médico privado'),
(2, 'job-101', 'Flexibilidad horaria'),
(3, 'job-102', 'Plan de formación anual'),
(4, 'job-103', 'Ticket restaurante'),
(5, 'job-104', 'Días de teletrabajo ilimitados'),
(6, 'job-105', 'Bono por resultados');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_job_requirements`
--

CREATE TABLE `bt_job_requirements` (
  `job_id` char(36) NOT NULL,
  `requirement` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_job_requirements`
--

INSERT INTO `bt_job_requirements` (`job_id`, `requirement`) VALUES
('job-100', '3+ years backend experience'),
('job-100', 'Inglés fluido'),
('job-101', '3+ years full stack experience'),
('job-101', 'Familiarity with React and Node.js'),
('job-102', 'Experience with modern frameworks (Vue/React)'),
('job-102', 'Expertise in HTML, CSS, JavaScript'),
('job-103', 'Experience with relational databases (MySQL/PostgreSQL)'),
('job-103', 'Proficiency in REST API design'),
('job-104', 'Experience with Docker and Kubernetes'),
('job-104', 'Knowledge of CI/CD pipelines'),
('job-105', 'Experience with Python and data libraries (pandas, scikit-learn)'),
('job-105', 'Strong background in statistics and ML'),
('job-106', 'Familiarity with design tools (Figma, Sketch)'),
('job-106', 'Portfolio showcasing UX/UI work'),
('job-107', 'Experience leading cross-functional teams'),
('job-107', 'Strong communication and roadmap planning skills'),
('job-108', 'Attention to detail and bug-tracking tools'),
('job-108', 'Experience with automated testing frameworks'),
('job-109', 'Experience with native iOS/Android development'),
('job-109', 'Knowledge of mobile UI/UX best practices'),
('job-110', 'Experience in SEO and SEM strategies'),
('job-110', 'Strong analytical and content creation skills');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_job_skills`
--

CREATE TABLE `bt_job_skills` (
  `job_id` char(36) NOT NULL,
  `skill` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_job_skills`
--

INSERT INTO `bt_job_skills` (`job_id`, `skill`) VALUES
('job-100', 'MySQL'),
('job-100', 'Node.js'),
('job-101', 'React'),
('job-102', 'Vue.js'),
('job-103', 'Node.js'),
('job-104', 'Kubernetes'),
('job-105', 'Python'),
('job-106', 'Figma'),
('job-107', 'Agile Methodologies'),
('job-108', 'Selenium'),
('job-109', 'Swift'),
('job-110', 'SEO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_news`
--

CREATE TABLE `bt_news` (
  `id` char(36) NOT NULL,
  `title` varchar(300) NOT NULL,
  `summary` text NOT NULL,
  `content` longtext NOT NULL,
  `image` varchar(2083) DEFAULT NULL,
  `date_published` datetime NOT NULL,
  `slug` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_news`
--

INSERT INTO `bt_news` (`id`, `title`, `summary`, `content`, `image`, `date_published`, `slug`) VALUES
('news-001', 'Lanzamiento de nuestra nueva plataforma', 'Presentamos Bubbletalents 2.0 con funcionalidades mejoradas.', 'Hoy lanzamos la versión 2.0 de Bubbletalents, que incluye ...', 'https://example.com/news/launch.jpg', '2025-07-20 08:00:00', 'lanzamiento-plataforma'),
('news-002', 'Evento de Networking en Madrid', 'Únete a nuestro próximo evento para conocer reclutadores.', 'Estamos organizando un networking el 15 de septiembre en ...', 'https://example.com/news/networking.jpg', '2025-07-25 12:00:00', 'evento-networking'),
('news-003', 'Consejos para tu entrevista técnica', 'Aprende a prepararte con éxito en tus próximas entrevistas.', 'Te compartimos 10 consejos prácticos para ...', 'https://example.com/news/entrevista.jpg', '2025-07-30 09:30:00', 'consejos-entrevista');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_notifications`
--

CREATE TABLE `bt_notifications` (
  `id` char(36) NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_skill_department_map`
--

CREATE TABLE `bt_skill_department_map` (
  `id` int NOT NULL,
  `skill` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_category_id` int NOT NULL,
  `department_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `bt_skill_department_map`
--

INSERT INTO `bt_skill_department_map` (`id`, `skill`, `department_category_id`, `department_id`) VALUES
(1, 'php', 3, 2),
(2, 'node.js', 3, 2),
(3, 'javascript', 4, 2),
(4, 'react', 4, 2),
(5, 'kubernetes', 5, 2),
(6, 'google analytics', 8, 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_social_logins`
--

CREATE TABLE `bt_social_logins` (
  `id` int NOT NULL,
  `candidate_id` char(36) NOT NULL,
  `provider` enum('google','linkedin','facebook') NOT NULL,
  `provider_user_id` varchar(200) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_staff_profiles`
--

CREATE TABLE `bt_staff_profiles` (
  `id` char(36) NOT NULL,
  `staff_user_id` char(36) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `avatar` varchar(2083) DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `department_category_id` int DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `department_category` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `time_to_fill` int DEFAULT NULL,
  `time_to_hire` int DEFAULT NULL,
  `cost_per_hire` decimal(12,2) DEFAULT NULL,
  `quality_of_hire` smallint DEFAULT NULL,
  `offer_accept_rate` smallint DEFAULT NULL,
  `candidate_satisfaction` smallint DEFAULT NULL,
  `manager_satisfaction` smallint DEFAULT NULL,
  `password_hash` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bt_staff_profiles`
--

INSERT INTO `bt_staff_profiles` (`id`, `staff_user_id`, `name`, `email`, `role`, `active`, `created_at`, `avatar`, `department_id`, `department_category_id`, `phone`, `department`, `department_category`, `hire_date`, `status`, `time_to_fill`, `time_to_hire`, `cost_per_hire`, `quality_of_hire`, `offer_accept_rate`, `candidate_satisfaction`, `manager_satisfaction`, `password_hash`) VALUES
('adm-001', NULL, 'Ana Torres', 'ana.torres@bubblegum.agency', 'admin', 1, '2025-08-08 17:37:58', 'https://example.com/avatars/ana.jpg', NULL, NULL, '+34 600111222', 'Administration', 'Operations', '2022-01-15', 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$12$SlNQvrAboFo92G12iABVOejpkx34WNat84WU6Y7dloGLRXwnVCXMW'),
('rec-502', NULL, 'Miguel Ruiz', 'miguel.ruiz@bubblegum.agency', 'recruiter', 1, '2025-08-08 17:37:58', 'https://example.com/avatars/miguel.jpg', NULL, NULL, '+34 611777888', 'Sales', 'Business', '2022-10-05', 'active', 32, 27, 1450.00, 78, 85, 88, 84, '$2y$12$SlNQvrAboFo92G12iABVOejpkx34WNat84WU6Y7dloGLRXwnVCXMW'),
('rec-503', NULL, 'Sofía Navarro', 'sofia.navarro@bubblegum.agency', 'recruiter', 1, '2025-08-08 17:37:58', 'https://example.com/avatars/sofia.jpg', NULL, NULL, '+34 611999000', 'Marketing', 'Digital', '2023-06-15', 'active', 24, 18, 1250.00, 88, 92, 93, 91, '$2y$12$SlNQvrAboFo92G12iABVOejpkx34WNat84WU6Y7dloGLRXwnVCXMW'),
('rec-504', NULL, 'Carlos Vega', 'carlos.vega@bubblegum.agency', 'recruiter', 1, '2025-08-08 17:37:58', 'https://example.com/avatars/carlos.jpg', NULL, NULL, '+34 612111222', 'Finance', 'Accounting', '2022-08-30', 'active', 30, 25, 1400.00, 80, 87, 89, 86, '$2y$12$SlNQvrAboFo92G12iABVOejpkx34WNat84WU6Y7dloGLRXwnVCXMW'),
('rec-505', NULL, 'Elena Ruiz', 'elena.ruiz@bubblegum.agency', 'recruiter', 1, '2025-08-08 17:37:58', 'https://example.com/avatars/elena.jpg', NULL, NULL, '+34 612333444', 'HR', 'Talent Acquisition', '2023-01-10', 'active', 26, 21, 1350.00, 85, 90, 92, 89, '$2y$12$SlNQvrAboFo92G12iABVOejpkx34WNat84WU6Y7dloGLRXwnVCXMW');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_staff_sessions`
--

CREATE TABLE `bt_staff_sessions` (
  `id` char(36) NOT NULL,
  `staff_id` char(36) NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_recruiter_load`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_recruiter_load` (
`active_candidates` bigint
,`department_id` int
,`name` varchar(200)
,`recruiter_id` char(36)
);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bt_applications`
--
ALTER TABLE `bt_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bt_app_job` (`job_id`),
  ADD KEY `fk_bt_app_candidate` (`candidate_id`);

--
-- Indices de la tabla `bt_application_notes`
--
ALTER TABLE `bt_application_notes`
  ADD PRIMARY KEY (`application_id`,`note_idx`),
  ADD KEY `fk_bt_note_app` (`application_id`);

--
-- Indices de la tabla `bt_candidates`
--
ALTER TABLE `bt_candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bt_candidates_email` (`email`),
  ADD KEY `fk_bt_candidates_referred_by` (`referred_by`);

--
-- Indices de la tabla `bt_candidate_certifications`
--
ALTER TABLE `bt_candidate_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cand_cert` (`candidate_id`);

--
-- Indices de la tabla `bt_candidate_education`
--
ALTER TABLE `bt_candidate_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cand_edu` (`candidate_id`);

--
-- Indices de la tabla `bt_candidate_experiences`
--
ALTER TABLE `bt_candidate_experiences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bt_exp_candidate` (`candidate_id`);

--
-- Indices de la tabla `bt_candidate_languages`
--
ALTER TABLE `bt_candidate_languages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cand_lang` (`candidate_id`);

--
-- Indices de la tabla `bt_candidate_references`
--
ALTER TABLE `bt_candidate_references`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cand_ref` (`candidate_id`);

--
-- Indices de la tabla `bt_candidate_routing`
--
ALTER TABLE `bt_candidate_routing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rt_candidate` (`candidate_id`),
  ADD KEY `fk_rt_deptcat` (`department_category_id`),
  ADD KEY `fk_rt_dept` (`department_id`),
  ADD KEY `fk_rt_recruiter` (`recruiter_id`);

--
-- Indices de la tabla `bt_candidate_skills`
--
ALTER TABLE `bt_candidate_skills`
  ADD PRIMARY KEY (`candidate_id`,`skill`),
  ADD KEY `fk_bt_skill_candidate` (`candidate_id`);

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
-- Indices de la tabla `bt_chatbot_nodes`
--
ALTER TABLE `bt_chatbot_nodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

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

-- Indices de la tabla `bt_departments`
--
ALTER TABLE `bt_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `bt_department_categories`
--
ALTER TABLE `bt_department_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_id` (`department_id`,`name`);

--
-- Indices de la tabla `bt_interviews`
--
ALTER TABLE `bt_interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `recruiter_id` (`recruiter_id`);

--
-- Indices de la tabla `bt_jobs`
--
ALTER TABLE `bt_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `bt_job_benefits`
--
ALTER TABLE `bt_job_benefits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indices de la tabla `bt_job_requirements`
--
ALTER TABLE `bt_job_requirements`
  ADD PRIMARY KEY (`job_id`,`requirement`),
  ADD KEY `fk_bt_job_req` (`job_id`);

--
-- Indices de la tabla `bt_job_skills`
--
ALTER TABLE `bt_job_skills`
  ADD PRIMARY KEY (`job_id`,`skill`),
  ADD KEY `fk_bt_job_skill` (`job_id`);

--
-- Indices de la tabla `bt_news`
--
ALTER TABLE `bt_news`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `bt_notifications`
--
ALTER TABLE `bt_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indices de la tabla `bt_skill_department_map`
--
ALTER TABLE `bt_skill_department_map`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_skill` (`skill`),
  ADD KEY `fk_sdm_department_category` (`department_category_id`),
  ADD KEY `fk_sdm_department` (`department_id`);

--
-- Indices de la tabla `bt_social_logins`
--
ALTER TABLE `bt_social_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indices de la tabla `bt_staff_profiles`
--
ALTER TABLE `bt_staff_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bt_recruiters_email` (`email`),
  ADD KEY `fk_recr_department` (`department_id`),
  ADD KEY `fk_recr_deptcat` (`department_category_id`);

--
-- Indices de la tabla `bt_staff_sessions`
--
ALTER TABLE `bt_staff_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `bt_candidate_experiences`
--
ALTER TABLE `bt_candidate_experiences`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `bt_chatbot_analytics`
--
ALTER TABLE `bt_chatbot_analytics`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
--
-- AUTO_INCREMENT de la tabla `bt_departments`
--
ALTER TABLE `bt_departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `bt_department_categories`
--
ALTER TABLE `bt_department_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `bt_job_benefits`
--
ALTER TABLE `bt_job_benefits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `bt_skill_department_map`
--
ALTER TABLE `bt_skill_department_map`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `bt_social_logins`
--
ALTER TABLE `bt_social_logins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_recruiter_load`
--
DROP TABLE IF EXISTS `vw_recruiter_load`;

CREATE ALGORITHM=UNDEFINED DEFINER=`user`@`%` SQL SECURITY DEFINER VIEW `vw_recruiter_load`  AS SELECT `sp`.`id` AS `recruiter_id`, `sp`.`name` AS `name`, `sp`.`department_id` AS `department_id`, count(`r`.`id`) AS `active_candidates` FROM (`bt_staff_profiles` `sp` left join `bt_candidate_routing` `r` on(((`r`.`recruiter_id` = `sp`.`id`) and (`r`.`assigned_at` >= (now() - interval 180 day))))) WHERE ((`sp`.`role` = 'recruiter') AND (`sp`.`active` = 1)) GROUP BY `sp`.`id`, `sp`.`name`, `sp`.`department_id` ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bt_applications`
--
ALTER TABLE `bt_applications`
  ADD CONSTRAINT `fk_bt_app_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bt_app_job` FOREIGN KEY (`job_id`) REFERENCES `bt_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_application_notes`
--
ALTER TABLE `bt_application_notes`
  ADD CONSTRAINT `fk_bt_note_app` FOREIGN KEY (`application_id`) REFERENCES `bt_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_candidates`
--
ALTER TABLE `bt_candidates`
  ADD CONSTRAINT `fk_bt_candidates_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `bt_candidates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_candidate_certifications`
--
ALTER TABLE `bt_candidate_certifications`
  ADD CONSTRAINT `fk_cand_cert` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_candidate_education`
--
ALTER TABLE `bt_candidate_education`
  ADD CONSTRAINT `fk_cand_edu` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_candidate_experiences`
--
ALTER TABLE `bt_candidate_experiences`
  ADD CONSTRAINT `fk_bt_exp_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_candidate_languages`
--
ALTER TABLE `bt_candidate_languages`
  ADD CONSTRAINT `fk_cand_lang` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_candidate_references`
--
ALTER TABLE `bt_candidate_references`
  ADD CONSTRAINT `fk_cand_ref` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_candidate_routing`
--
ALTER TABLE `bt_candidate_routing`
  ADD CONSTRAINT `fk_rt_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rt_dept` FOREIGN KEY (`department_id`) REFERENCES `bt_departments` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rt_deptcat` FOREIGN KEY (`department_category_id`) REFERENCES `bt_department_categories` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rt_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `bt_staff_profiles` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `bt_candidate_skills`
--
ALTER TABLE `bt_candidate_skills`
  ADD CONSTRAINT `fk_bt_skill_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_chatbot_analytics`
--
ALTER TABLE `bt_chatbot_analytics`
  ADD CONSTRAINT `bt_chatbot_analytics_ibfk_1` FOREIGN KEY (`node_id`) REFERENCES `bt_chatbot_nodes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bt_chatbot_analytics_ibfk_2` FOREIGN KEY (`option_id`) REFERENCES `bt_chatbot_options` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `bt_chatbot_options`
--
ALTER TABLE `bt_chatbot_options`
  ADD CONSTRAINT `bt_chatbot_options_ibfk_1` FOREIGN KEY (`node_id`) REFERENCES `bt_chatbot_nodes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bt_chatbot_options_ibfk_2` FOREIGN KEY (`next_node_id`) REFERENCES `bt_chatbot_nodes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `bt_department_categories`
--
ALTER TABLE `bt_department_categories`
  ADD CONSTRAINT `fk_deptcat_department` FOREIGN KEY (`department_id`) REFERENCES `bt_departments` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_interviews`
--
ALTER TABLE `bt_interviews`
  ADD CONSTRAINT `bt_interviews_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `bt_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bt_interviews_ibfk_2` FOREIGN KEY (`recruiter_id`) REFERENCES `bt_staff_profiles` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `bt_job_benefits`
--
ALTER TABLE `bt_job_benefits`
  ADD CONSTRAINT `bt_job_benefits_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `bt_jobs` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_job_requirements`
--
ALTER TABLE `bt_job_requirements`
  ADD CONSTRAINT `fk_bt_job_req` FOREIGN KEY (`job_id`) REFERENCES `bt_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_job_skills`
--
ALTER TABLE `bt_job_skills`
  ADD CONSTRAINT `fk_bt_job_skill` FOREIGN KEY (`job_id`) REFERENCES `bt_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_notifications`
--
ALTER TABLE `bt_notifications`
  ADD CONSTRAINT `bt_notifications_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_skill_department_map`
--
ALTER TABLE `bt_skill_department_map`
  ADD CONSTRAINT `fk_sdm_department` FOREIGN KEY (`department_id`) REFERENCES `bt_departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sdm_department_category` FOREIGN KEY (`department_category_id`) REFERENCES `bt_department_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_social_logins`
--
ALTER TABLE `bt_social_logins`
  ADD CONSTRAINT `bt_social_logins_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_staff_profiles`
--
ALTER TABLE `bt_staff_profiles`
  ADD CONSTRAINT `fk_recr_department` FOREIGN KEY (`department_id`) REFERENCES `bt_departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_recr_deptcat` FOREIGN KEY (`department_category_id`) REFERENCES `bt_department_categories` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `bt_staff_sessions`
--
ALTER TABLE `bt_staff_sessions`
  ADD CONSTRAINT `bt_staff_sessions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `bt_staff_profiles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
