
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

SET time_zone = "+00:00";
--
-- Base de datos: `bubble_talents_DB`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`user`@`%` PROCEDURE `CleanupExpiredTokens` ()   BEGIN
        DECLARE deleted_count INT DEFAULT 0;
        
        -- Eliminar tokens expirados o usados de más de 24 horas
        DELETE FROM bt_password_reset_tokens 
        WHERE (expires_at < NOW() OR used_at IS NOT NULL) 
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
        
        SET deleted_count = ROW_COUNT();
        
        -- Log de la limpieza
        INSERT INTO bt_notifications (
            candidate_id, 
            type, 
            status, 
            data, 
            created_at
        ) VALUES (
            NULL,
            'system_cleanup',
            'completed',
            JSON_OBJECT('deleted_tokens', deleted_count, 'cleanup_time', NOW()),
            NOW()
        );
    END$$

DELIMITER;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_applications`
--

CREATE TABLE `bt_applications` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `job_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `score` decimal(5, 2) DEFAULT NULL,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resume` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `cover_letter` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `insights` json DEFAULT NULL,
    `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_application_notes`
--

CREATE TABLE `bt_application_notes` (
    `application_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `note_idx` smallint NOT NULL,
    `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidates`
--

CREATE TABLE `bt_candidates` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `password_hash` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `linkedin_url` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `portfolio_url` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `date_of_birth` date DEFAULT NULL,
    `nationality` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `profile_image` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `available_from` date DEFAULT NULL,
    `desired_salary` decimal(12, 2) DEFAULT NULL,
    `desired_contract_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `department_id` int DEFAULT NULL,
    `department_category_id` int DEFAULT NULL,
    `referred_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `cv_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `professional_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `soft_skills` json DEFAULT NULL,
    `hard_skills` json DEFAULT NULL,
    `languages` json DEFAULT NULL,
    `interests` json DEFAULT NULL,
    `references` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `availability` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `certifications` json DEFAULT NULL,
    `cv_original_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `cv_text_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `cv_json_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `data_source` enum(
        'ai_processing',
        'manual_entry',
        'hybrid'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'manual_entry',
    `gdpr_consent_given` tinyint(1) NOT NULL DEFAULT '0',
    `gdpr_consent_date` datetime DEFAULT NULL,
    `IA_processing_consent` tinyint(1) NOT NULL DEFAULT '0',
    `IA_consent_date` datetime DEFAULT NULL,
    `data_processing_purposes` json DEFAULT NULL,
    `consent_version` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '1.0',
    `ip_address_consent` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent_consent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `consent_withdrawn_date` datetime DEFAULT NULL,
    `data_retention_until` datetime DEFAULT NULL,
    `provider_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del usuario en el proveedor OAuth',
    `provider_type` enum(
        'google',
        'linkedin',
        'facebook',
        'github'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tipo de proveedor OAuth',
    `avatar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'URL del avatar del usuario'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_certifications`
--

CREATE TABLE `bt_candidate_certifications` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `certification_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `issuer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `issue_date` date DEFAULT NULL,
    `expiry_date` date DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_education`
--

CREATE TABLE `bt_candidate_education` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `degree` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `field_of_study` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `start_date` date DEFAULT NULL,
    `end_date` date DEFAULT NULL,
    `education_level` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_experiences`
--

CREATE TABLE `bt_candidate_experiences` (
    `id` int NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `company` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `position` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date DEFAULT NULL,
    `current` tinyint(1) DEFAULT '0',
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_languages`
--

CREATE TABLE `bt_candidate_languages` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `language` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `proficiency_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_projects`
--

CREATE TABLE `bt_candidate_projects` (
    `id` int NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `tecnologies` json DEFAULT NULL,
    `start_date` date DEFAULT NULL,
    `end_date` date DEFAULT NULL,
    `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_references`
--

CREATE TABLE `bt_candidate_references` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `ref_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `ref_company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ref_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ref_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_routing`
--

CREATE TABLE `bt_candidate_routing` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `department_category_id` int NOT NULL,
    `department_id` int NOT NULL,
    `recruiter_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `source` enum('ai', 'manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ai',
    `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_skills`
--

CREATE TABLE `bt_candidate_skills` (
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `skill` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_candidate_skill_map`
--

CREATE TABLE `bt_candidate_skill_map` (
    `id` int NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `skill_id` int NOT NULL,
    `proficiency_level` enum(
        'basic',
        'intermediate',
        'advanced',
        'expert'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `years_experience` decimal(3, 1) DEFAULT NULL,
    `is_certified` tinyint(1) DEFAULT '0',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_chatbot_analytics`
--

CREATE TABLE `bt_chatbot_analytics` (
    `id` bigint UNSIGNED NOT NULL,
    `conversation_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `event_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `event_data` json DEFAULT NULL,
    `node_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `option_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_chatbot_nodes`
--

CREATE TABLE `bt_chatbot_nodes` (
    `id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `type` enum(
        'message',
        'options',
        'form',
        'redirect'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'message',
    `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `metadata` json DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT '1',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'system'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_chatbot_options`
--

CREATE TABLE `bt_chatbot_options` (
    `id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `node_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `next_node_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `action_type` enum(
        'navigate',
        'submit',
        'restart',
        'end'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'navigate',
    `action_data` json DEFAULT NULL,
    `order_position` int NOT NULL DEFAULT '1',
    `is_active` tinyint(1) DEFAULT '1',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_culture`
--

CREATE TABLE `bt_culture` (
    `id` int NOT NULL,
    `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `image` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `sort_order` int NOT NULL DEFAULT '0'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_departments`
--

CREATE TABLE `bt_departments` (
    `id` int NOT NULL,
    `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_department_categories`
--

CREATE TABLE `bt_department_categories` (
    `id` int NOT NULL,
    `department_id` int NOT NULL,
    `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_interviews`
--

CREATE TABLE `bt_interviews` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `application_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `recruiter_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `scheduled_at` datetime NOT NULL,
    `duration_minutes` int NOT NULL,
    `interview_type` enum('online', 'onsite', 'phone') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
    `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `meeting_link` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_jobs`
--

CREATE TABLE `bt_jobs` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `company_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `salary_min` decimal(12, 2) DEFAULT NULL,
    `salary_max` decimal(12, 2) DEFAULT NULL,
    `salary_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `salary_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `posted_at` datetime NOT NULL,
    `expires_at` datetime DEFAULT NULL,
    `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_featured` tinyint(1) NOT NULL DEFAULT '0',
    `department_id` int DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_job_benefits`
--

CREATE TABLE `bt_job_benefits` (
    `id` int NOT NULL,
    `job_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `benefit` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_job_requirements`
--

CREATE TABLE `bt_job_requirements` (
    `job_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `requirement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_job_skills`
--

CREATE TABLE `bt_job_skills` (
    `job_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `skill` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_job_skill_map`
--

CREATE TABLE `bt_job_skill_map` (
    `id` int NOT NULL,
    `job_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `skill_id` int NOT NULL,
    `weight` tinyint NOT NULL DEFAULT '1',
    `is_required` tinyint(1) DEFAULT '1',
    `proficiency_level` enum(
        'basic',
        'intermediate',
        'advanced',
        'expert'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_news`
--

CREATE TABLE `bt_news` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `title` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `image` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `date_published` datetime NOT NULL,
    `slug` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_notifications`
--

CREATE TABLE `bt_notifications` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime NOT NULL,
    `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
    `email_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` enum('pending', 'sent', 'failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
    `sent_at` datetime DEFAULT NULL,
    `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_notification_preferences`
--

CREATE TABLE `bt_notification_preferences` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `application_updates` tinyint(1) DEFAULT '1',
    `new_jobs` tinyint(1) DEFAULT '0',
    `reminders` tinyint(1) DEFAULT '1',
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_password_reset_tokens`
--

CREATE TABLE `bt_password_reset_tokens` (
    `id` int NOT NULL,
    `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_type` enum('staff', 'candidate') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `token_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `expires_at` timestamp NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` timestamp NULL DEFAULT NULL,
    `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `attempts` int DEFAULT '0'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_skills`
--

CREATE TABLE `bt_skills` (
    `id` int NOT NULL,
    `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `skill_type` enum('hard', 'soft') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT '1',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `normalized_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (
        lower(
            trim(
                replace (
                        replace (
                                replace (
                                        replace (
                                                `name`,
                                                _utf8mb4 '.',
                                                _utf8mb4 ''
                                            ),
                                            _utf8mb4 '_',
                                            _utf8mb4 ' '
                                    ),
                                    _utf8mb4 '-',
                                    _utf8mb4 ' '
                            ),
                            _utf8mb4 '  ',
                            _utf8mb4 ' '
                    )
            )
        )
    ) STORED
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_skill_aliases`
--

CREATE TABLE `bt_skill_aliases` (
    `id` int NOT NULL,
    `skill_id` int NOT NULL,
    `alias` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `normalized_alias` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_skill_department_map`
--

CREATE TABLE `bt_skill_department_map` (
    `id` int NOT NULL,
    `skill` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `department_category_id` int NOT NULL,
    `department_id` int NOT NULL,
    `skill_id` int DEFAULT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_social_logins`
--

CREATE TABLE `bt_social_logins` (
    `id` int NOT NULL,
    `candidate_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `provider` enum(
        'google',
        'linkedin',
        'facebook'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `provider_user_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_staff_profiles`
--

CREATE TABLE `bt_staff_profiles` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `staff_user_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `active` tinyint(1) NOT NULL DEFAULT '1',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `avatar` varchar(2083) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `department_id` int DEFAULT NULL,
    `department_category_id` int DEFAULT NULL,
    `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `department_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `hire_date` date DEFAULT NULL,
    `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `time_to_fill` int DEFAULT NULL,
    `time_to_hire` int DEFAULT NULL,
    `cost_per_hire` decimal(12, 2) DEFAULT NULL,
    `quality_of_hire` smallint DEFAULT NULL,
    `offer_accept_rate` smallint DEFAULT NULL,
    `candidate_satisfaction` smallint DEFAULT NULL,
    `manager_satisfaction` smallint DEFAULT NULL,
    `password_hash` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bt_staff_sessions`
--

CREATE TABLE `bt_staff_sessions` (
    `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `staff_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `token` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `expires_at` datetime NOT NULL,
    `created_at` datetime NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_applications_extended`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_applications_extended` (
    `application_id` char(36),
    `candidate_email` varchar(200),
    `candidate_id` char(36),
    `candidate_name` varchar(200),
    `cover_letter` text,
    `created_at` datetime,
    `job_company` varchar(200),
    `job_id` char(36),
    `job_location` varchar(255),
    `job_title` varchar(200),
    `resume` varchar(2083),
    `score` decimal(5, 2),
    `source` varchar(100),
    `status` varchar(50),
    `updated_at` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_candidates_dpt_categ`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_candidates_dpt_categ` (
    `created_at` datetime,
    `department_category_id` int,
    `department_category_name` varchar(100),
    `department_id` int,
    `department_name` varchar(100),
    `email` varchar(200),
    `id` char(36),
    `location` varchar(255),
    `name` varchar(200),
    `phone` varchar(50),
    `status` varchar(50)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_candidates_list`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_candidates_list` (
    `department_category_id` bigint,
    `department_category_name` varchar(100),
    `department_id` bigint,
    `department_name` varchar(100),
    `email` varchar(200),
    `first_name` varchar(100),
    `id` char(36),
    `last_name` varchar(100),
    `location` varchar(255),
    `name` varchar(200),
    `phone` varchar(50),
    `skills_text` text,
    `status` varchar(50)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_candidate_profile_full`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_candidate_profile_full` (
    `certifications_summary` text,
    `education_summary` text,
    `email` varchar(200),
    `experience_summary` text,
    `id` char(36),
    `languages_summary` text,
    `location` varchar(255),
    `name` varchar(200),
    `phone` varchar(50),
    `status` varchar(50)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_candidate_skills_flat`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_candidate_skills_flat` (
    `candidate_id` char(36),
    `json_kind` varchar(4),
    `skill_name` varchar(100),
    `source` varchar(7)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_interviews_schedule`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_interviews_schedule` (
    `application_id` char(36),
    `candidate_email` varchar(200),
    `candidate_id` char(36),
    `candidate_name` varchar(200),
    `duration_minutes` int,
    `interview_id` char(36),
    `interview_kind` varchar(50),
    `interview_status` varchar(50),
    `interview_type` enum('online', 'onsite', 'phone'),
    `job_id` char(36),
    `job_title` varchar(200),
    `location` varchar(255),
    `meeting_link` varchar(2083),
    `recruiter_id` char(36),
    `scheduled_at` datetime
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_jobs_applications_summary`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_jobs_applications_summary` (
    `applications_total` bigint,
    `avg_score` decimal(9, 6),
    `cnt_applied` decimal(23, 0),
    `cnt_interview` decimal(23, 0),
    `cnt_rejected` decimal(23, 0),
    `company_name` varchar(200),
    `id` char(36),
    `last_update` datetime,
    `title` varchar(200)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_jobs_with_meta`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_jobs_with_meta` (
    `benefits` text,
    `company_name` varchar(200),
    `id` char(36),
    `location` varchar(255),
    `requirements` text,
    `skills_text` text,
    `title` varchar(200),
    `type` varchar(50)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_match_candidates_jobs`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_match_candidates_jobs` (
    `candidate_id` char(36),
    `job_id` char(36),
    `matched_skills` bigint,
    `skills_overlap` text
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_pipeline_department`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_pipeline_department` (
    `applications_count` bigint,
    `candidates_count` bigint,
    `department_category_id` int,
    `department_category_name` varchar(100),
    `department_id` int,
    `department_name` varchar(100),
    `recruiter_id` char(36)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_recruiter_load`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_recruiter_load` (
    `active_candidates` bigint,
    `department_id` int,
    `name` varchar(200),
    `recruiter_id` char(36)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_skill_demand`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_skill_demand` (
    `jobs_count` bigint,
    `skill_name_norm` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_skill_supply`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_skill_supply` (
    `candidates_count` bigint,
    `skill_name_norm` varchar(100)
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
ADD KEY `fk_bt_app_candidate` (`candidate_id`),
ADD KEY `idx_apps_job` (`job_id`),
ADD KEY `idx_apps_cand` (`candidate_id`),
ADD KEY `idx_apps_stat` (`status`),
ADD KEY `idx_apps_upd` (`updated_at`);

--
-- Indices de la tabla `bt_application_notes`
--
ALTER TABLE `bt_application_notes`
ADD PRIMARY KEY (`application_id`, `note_idx`),
ADD KEY `fk_bt_note_app` (`application_id`);

--
-- Indices de la tabla `bt_candidates`
--
ALTER TABLE `bt_candidates`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `uq_bt_candidates_email` (`email`),
ADD KEY `fk_bt_candidates_referred_by` (`referred_by`),
ADD KEY `idx_bt_candidates_provider` (
    `provider_type`,
    `provider_id`
),
ADD KEY `idx_bt_candidates_email_provider` (`email`, `provider_type`),
ADD KEY `idx_department` (`department_id`),
ADD KEY `idx_department_category` (`department_category_id`);

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
-- Indices de la tabla `bt_candidate_projects`
--
ALTER TABLE `bt_candidate_projects`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_bt_projects_candidate` (`candidate_id`);

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
ADD PRIMARY KEY (`candidate_id`, `skill`),
ADD KEY `fk_bt_skill_candidate` (`candidate_id`);

--
-- Indices de la tabla `bt_candidate_skill_map`
--
ALTER TABLE `bt_candidate_skill_map`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `unique_candidate_skill` (`candidate_id`, `skill_id`),
ADD KEY `idx_candidate_id` (`candidate_id`),
ADD KEY `idx_skill_id` (`skill_id`),
ADD KEY `idx_proficiency` (`proficiency_level`),
ADD KEY `idx_csm_cand` (`candidate_id`, `skill_id`);

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
-- Indices de la tabla `bt_culture`
--
ALTER TABLE `bt_culture` ADD PRIMARY KEY (`id`);

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
ADD UNIQUE KEY `department_id` (`department_id`, `name`);

--
-- Indices de la tabla `bt_interviews`
--
ALTER TABLE `bt_interviews`
ADD PRIMARY KEY (`id`),
ADD KEY `recruiter_id` (`recruiter_id`),
ADD KEY `idx_interviews_app` (`application_id`);

--
-- Indices de la tabla `bt_jobs`
--
ALTER TABLE `bt_jobs`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_department_id` (`department_id`);

--
-- Indices de la tabla `bt_job_benefits`
--
ALTER TABLE `bt_job_benefits`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_jobbenefits_job` (`job_id`);

--
-- Indices de la tabla `bt_job_requirements`
--
ALTER TABLE `bt_job_requirements`
ADD PRIMARY KEY (`job_id`, `requirement`),
ADD KEY `fk_bt_job_req` (`job_id`),
ADD KEY `idx_jobrequires_job` (`job_id`);

--
-- Indices de la tabla `bt_job_skills`
--
ALTER TABLE `bt_job_skills`
ADD PRIMARY KEY (`job_id`, `skill`),
ADD KEY `fk_bt_job_skill` (`job_id`),
ADD KEY `idx_jobskills_job` (`job_id`);

--
-- Indices de la tabla `bt_job_skill_map`
--
ALTER TABLE `bt_job_skill_map`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `unique_job_skill` (`job_id`, `skill_id`),
ADD KEY `idx_job_id` (`job_id`),
ADD KEY `idx_skill_id` (`skill_id`),
ADD KEY `idx_required` (`is_required`),
ADD KEY `idx_jsm_job` (`job_id`, `skill_id`);

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
ADD KEY `candidate_id` (`candidate_id`),
ADD KEY `idx_notif_created` (`created_at`);

--
-- Indices de la tabla `bt_notification_preferences`
--
ALTER TABLE `bt_notification_preferences`
ADD PRIMARY KEY (`id`),
ADD KEY `candidate_id` (`candidate_id`);

--
-- Indices de la tabla `bt_password_reset_tokens`
--
ALTER TABLE `bt_password_reset_tokens`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `token_hash` (`token_hash`),
ADD KEY `idx_email` (`email`),
ADD KEY `idx_token_hash` (`token_hash`),
ADD KEY `idx_expires_at` (`expires_at`),
ADD KEY `idx_user_type` (`user_type`),
ADD KEY `idx_created_at` (`created_at`),
ADD KEY `idx_email_user_type` (`email`, `user_type`),
ADD KEY `idx_token_active` (
    `token_hash`,
    `expires_at`,
    `used_at`
);

--
-- Indices de la tabla `bt_skills`
--
ALTER TABLE `bt_skills`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `name` (`name`),
ADD UNIQUE KEY `uq_bt_skills_norm` (`normalized_name`),
ADD KEY `idx_name` (`name`),
ADD KEY `idx_category` (`category`),
ADD KEY `idx_active` (`is_active`),
ADD KEY `idx_skills_type` (`skill_type`);

--
-- Indices de la tabla `bt_skill_aliases`
--
ALTER TABLE `bt_skill_aliases`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `alias` (`alias`),
ADD UNIQUE KEY `uq_skill_norm_alias` (
    `skill_id`,
    `normalized_alias`
),
ADD KEY `idx_alias` (`alias`),
ADD KEY `idx_normalized_alias` (`normalized_alias`),
ADD KEY `idx_skill_id` (`skill_id`);

--
-- Indices de la tabla `bt_skill_department_map`
--
ALTER TABLE `bt_skill_department_map`
ADD PRIMARY KEY (`id`),
ADD KEY `idx_skill` (`skill`),
ADD KEY `fk_sdm_department_category` (`department_category_id`),
ADD KEY `fk_sdm_department` (`department_id`),
ADD KEY `idx_sdm_skill_id` (`skill_id`);

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
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 14;

--
-- AUTO_INCREMENT de la tabla `bt_candidate_projects`
--
ALTER TABLE `bt_candidate_projects`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 15;

--
-- AUTO_INCREMENT de la tabla `bt_candidate_skill_map`
--
ALTER TABLE `bt_candidate_skill_map`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 9;

--
-- AUTO_INCREMENT de la tabla `bt_chatbot_analytics`
--
ALTER TABLE `bt_chatbot_analytics`
MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 9;

--
-- AUTO_INCREMENT de la tabla `bt_culture`
--
ALTER TABLE `bt_culture`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 4;

--
-- AUTO_INCREMENT de la tabla `bt_departments`
--
ALTER TABLE `bt_departments`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 8;

--
-- AUTO_INCREMENT de la tabla `bt_department_categories`
--
ALTER TABLE `bt_department_categories`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 10;

--
-- AUTO_INCREMENT de la tabla `bt_job_benefits`
--
ALTER TABLE `bt_job_benefits`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 7;

--
-- AUTO_INCREMENT de la tabla `bt_job_skill_map`
--
ALTER TABLE `bt_job_skill_map`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 17;

--
-- AUTO_INCREMENT de la tabla `bt_password_reset_tokens`
--
ALTER TABLE `bt_password_reset_tokens`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 20;

--
-- AUTO_INCREMENT de la tabla `bt_skills`
--
ALTER TABLE `bt_skills`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 13;

--
-- AUTO_INCREMENT de la tabla `bt_skill_aliases`
--
ALTER TABLE `bt_skill_aliases`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 10;

--
-- AUTO_INCREMENT de la tabla `bt_skill_department_map`
--
ALTER TABLE `bt_skill_department_map`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 7;

--
-- AUTO_INCREMENT de la tabla `bt_social_logins`
--
ALTER TABLE `bt_social_logins`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 18;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_applications_extended`
--
DROP TABLE IF EXISTS `vw_applications_extended`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_applications_extended` AS
SELECT
    `a`.`id` AS `application_id`,
    `a`.`created_at` AS `created_at`,
    `a`.`updated_at` AS `updated_at`,
    `a`.`status` AS `status`,
    `a`.`score` AS `score`,
    `a`.`source` AS `source`,
    `a`.`job_id` AS `job_id`,
    `j`.`title` AS `job_title`,
    `j`.`company_name` AS `job_company`,
    `j`.`location` AS `job_location`,
    `a`.`candidate_id` AS `candidate_id`,
    any_value(`c`.`name`) AS `candidate_name`,
    any_value(`c`.`email`) AS `candidate_email`,
    `a`.`resume` AS `resume`,
    `a`.`cover_letter` AS `cover_letter`
FROM (
        (
            `bt_applications` `a`
            join `bt_jobs` `j` on ((`j`.`id` = `a`.`job_id`))
        )
        join `bt_candidates` `c` on (
            (`c`.`id` = `a`.`candidate_id`)
        )
    )
GROUP BY
    `a`.`id`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_candidates_dpt_categ`
--
DROP TABLE IF EXISTS `vw_candidates_dpt_categ`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_candidates_dpt_categ` AS
SELECT
    `c`.`id` AS `id`,
    `c`.`name` AS `name`,
    `c`.`email` AS `email`,
    `c`.`phone` AS `phone`,
    `c`.`location` AS `location`,
    `c`.`status` AS `status`,
    `c`.`created_at` AS `created_at`,
    `c`.`department_id` AS `department_id`,
    `d`.`name` AS `department_name`,
    `c`.`department_category_id` AS `department_category_id`,
    `dc`.`name` AS `department_category_name`
FROM (
        (
            `bt_candidates` `c`
            left join `bt_departments` `d` on (
                (
                    `d`.`id` = `c`.`department_id`
                )
            )
        )
        left join `bt_department_categories` `dc` on (
            (
                `dc`.`id` = `c`.`department_category_id`
            )
        )
    );

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_candidates_list`
--
DROP TABLE IF EXISTS `vw_candidates_list`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_candidates_list` AS
SELECT
    `c`.`id` AS `id`,
    any_value(`c`.`name`) AS `name`,
    any_value(`c`.`first_name`) AS `first_name`,
    any_value(`c`.`last_name`) AS `last_name`,
    any_value(`c`.`email`) AS `email`,
    any_value(`c`.`phone`) AS `phone`,
    any_value(`c`.`location`) AS `location`,
    any_value(`c`.`status`) AS `status`,
    any_value(`c`.`department_id`) AS `department_id`,
    any_value(`c`.`department_category_id`) AS `department_category_id`,
    any_value(`d`.`name`) AS `department_name`,
    any_value(`dc`.`name`) AS `department_category_name`,
    group_concat(
        distinct `s`.`name`
        order by `s`.`name` ASC separator ', '
    ) AS `skills_text`
FROM (
        (
            (
                (
                    `bt_candidates` `c`
                    left join `bt_departments` `d` on (
                        (
                            `d`.`id` = `c`.`department_id`
                        )
                    )
                )
                left join `bt_department_categories` `dc` on (
                    (
                        `dc`.`id` = `c`.`department_category_id`
                    )
                )
            )
            left join `bt_candidate_skill_map` `m` on (
                (`m`.`candidate_id` = `c`.`id`)
            )
        )
        left join `bt_skills` `s` on ((`s`.`id` = `m`.`skill_id`))
    )
GROUP BY
    `c`.`id`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_candidate_profile_full`
--
DROP TABLE IF EXISTS `vw_candidate_profile_full`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_candidate_profile_full` AS
SELECT
    `c`.`id` AS `id`,
    any_value(`c`.`name`) AS `name`,
    any_value(`c`.`email`) AS `email`,
    any_value(`c`.`phone`) AS `phone`,
    any_value(`c`.`location`) AS `location`,
    any_value(`c`.`status`) AS `status`,
    group_concat(
        distinct concat_ws(
            ' / ',
            `edu`.`degree`,
            `edu`.`institution`
        )
        order by coalesce(
                `edu`.`end_date`, `edu`.`start_date`
            ) DESC separator ' | '
    ) AS `education_summary`,
    group_concat(
        distinct concat_ws(
            ' @ ',
            `exp`.`position`,
            `exp`.`company`
        )
        order by coalesce(
                `exp`.`end_date`, `exp`.`start_date`
            ) DESC separator ' | '
    ) AS `experience_summary`,
    group_concat(
        distinct concat(
            `lang`.`language`,
            ' (',
            coalesce(
                `lang`.`proficiency_level`,
                ''
            ),
            ')'
        ) separator ', '
    ) AS `languages_summary`,
    group_concat(
        distinct `cert`.`certification_name` separator ', '
    ) AS `certifications_summary`
FROM (
        (
            (
                (
                    `bt_candidates` `c`
                    left join `bt_candidate_education` `edu` on (
                        (
                            `edu`.`candidate_id` = `c`.`id`
                        )
                    )
                )
                left join `bt_candidate_experiences` `exp` on (
                    (
                        `exp`.`candidate_id` = `c`.`id`
                    )
                )
            )
            left join `bt_candidate_languages` `lang` on (
                (
                    `lang`.`candidate_id` = `c`.`id`
                )
            )
        )
        left join `bt_candidate_certifications` `cert` on (
            (
                `cert`.`candidate_id` = `c`.`id`
            )
        )
    )
GROUP BY
    `c`.`id`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_candidate_skills_flat`
--
DROP TABLE IF EXISTS `vw_candidate_skills_flat`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_candidate_skills_flat` AS
SELECT
    `csm`.`candidate_id` AS `candidate_id`,
    `s`.`name` AS `skill_name`,
    'catalog' AS `source`,
    NULL AS `json_kind`
FROM (
        `bt_candidate_skill_map` `csm`
        join `bt_skills` `s` on ((`s`.`id` = `csm`.`skill_id`))
    )
union all
select
    `c`.`id` AS `candidate_id`,
    `jt`.`value` AS `skill_name`,
    'json' AS `source`,
    'hard' AS `json_kind`
from (
        `bt_candidates` `c`
        join json_table(
            coalesce(
                `c`.`hard_skills`, json_array()
            ), '$[*]' columns (
                `value` varchar(100) character set utf8mb4 collate utf8mb4_unicode_ci path '$'
            )
        ) `jt`
    )
union all
select
    `c`.`id` AS `candidate_id`,
    `jt`.`value` AS `skill_name`,
    'json' AS `source`,
    'soft' AS `json_kind`
from (
        `bt_candidates` `c`
        join json_table(
            coalesce(
                `c`.`soft_skills`, json_array()
            ), '$[*]' columns (
                `value` varchar(100) character set utf8mb4 collate utf8mb4_unicode_ci path '$'
            )
        ) `jt`
    );

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_interviews_schedule`
--
DROP TABLE IF EXISTS `vw_interviews_schedule`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_interviews_schedule` AS
SELECT
    `i`.`id` AS `interview_id`,
    `i`.`scheduled_at` AS `scheduled_at`,
    `i`.`duration_minutes` AS `duration_minutes`,
    `i`.`interview_type` AS `interview_type`,
    `i`.`location` AS `location`,
    `i`.`type` AS `interview_kind`,
    `i`.`status` AS `interview_status`,
    `i`.`meeting_link` AS `meeting_link`,
    `a`.`id` AS `application_id`,
    `a`.`job_id` AS `job_id`,
    `j`.`title` AS `job_title`,
    `a`.`candidate_id` AS `candidate_id`,
    `c`.`name` AS `candidate_name`,
    `c`.`email` AS `candidate_email`,
    `i`.`recruiter_id` AS `recruiter_id`
FROM (
        (
            (
                `bt_interviews` `i`
                join `bt_applications` `a` on (
                    (
                        `a`.`id` = `i`.`application_id`
                    )
                )
            )
            join `bt_candidates` `c` on (
                (`c`.`id` = `a`.`candidate_id`)
            )
        )
        join `bt_jobs` `j` on ((`j`.`id` = `a`.`job_id`))
    );

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_jobs_applications_summary`
--
DROP TABLE IF EXISTS `vw_jobs_applications_summary`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_jobs_applications_summary` AS
SELECT
    `j`.`id` AS `id`,
    `j`.`title` AS `title`,
    `j`.`company_name` AS `company_name`,
    count(`a`.`id`) AS `applications_total`,
    sum(
        (
            case
                when (`a`.`status` = 'applied') then 1
                else 0
            end
        )
    ) AS `cnt_applied`,
    sum(
        (
            case
                when (`a`.`status` = 'interview') then 1
                else 0
            end
        )
    ) AS `cnt_interview`,
    sum(
        (
            case
                when (`a`.`status` = 'rejected') then 1
                else 0
            end
        )
    ) AS `cnt_rejected`,
    avg(`a`.`score`) AS `avg_score`,
    max(`a`.`updated_at`) AS `last_update`
FROM (
        `bt_jobs` `j`
        left join `bt_applications` `a` on ((`a`.`job_id` = `j`.`id`))
    )
GROUP BY
    `j`.`id`,
    `j`.`title`,
    `j`.`company_name`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_jobs_with_meta`
--
DROP TABLE IF EXISTS `vw_jobs_with_meta`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_jobs_with_meta` AS
SELECT
    `j`.`id` AS `id`,
    `j`.`title` AS `title`,
    `j`.`company_name` AS `company_name`,
    `j`.`location` AS `location`,
    `j`.`type` AS `type`,
    group_concat(
        distinct `js`.`skill`
        order by `js`.`skill` ASC separator ', '
    ) AS `skills_text`,
    group_concat(
        distinct `jr`.`requirement`
        order by `jr`.`requirement` ASC separator ' • '
    ) AS `requirements`,
    group_concat(
        distinct `jb`.`benefit`
        order by `jb`.`benefit` ASC separator ' • '
    ) AS `benefits`
FROM (
        (
            (
                `bt_jobs` `j`
                left join `bt_job_skills` `js` on ((`js`.`job_id` = `j`.`id`))
            )
            left join `bt_job_requirements` `jr` on ((`jr`.`job_id` = `j`.`id`))
        )
        left join `bt_job_benefits` `jb` on ((`jb`.`job_id` = `j`.`id`))
    )
GROUP BY
    `j`.`id`,
    `j`.`title`,
    `j`.`company_name`,
    `j`.`location`,
    `j`.`type`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_match_candidates_jobs`
--
DROP TABLE IF EXISTS `vw_match_candidates_jobs`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_match_candidates_jobs` AS
SELECT
    `csm`.`candidate_id` AS `candidate_id`,
    `jsm`.`job_id` AS `job_id`,
    count(0) AS `matched_skills`,
    group_concat(
        distinct `s`.`name`
        order by `s`.`name` ASC separator ','
    ) AS `skills_overlap`
FROM (
        (
            (
                select distinct
                    `bt_candidate_skill_map`.`candidate_id` AS `candidate_id`, `bt_candidate_skill_map`.`skill_id` AS `skill_id`
                from `bt_candidate_skill_map`
            ) `csm`
            join (
                select distinct
                    `bt_job_skill_map`.`job_id` AS `job_id`, `bt_job_skill_map`.`skill_id` AS `skill_id`
                from `bt_job_skill_map`
            ) `jsm` on (
                (
                    `jsm`.`skill_id` = `csm`.`skill_id`
                )
            )
        )
        join `bt_skills` `s` on ((`s`.`id` = `csm`.`skill_id`))
    )
GROUP BY
    `csm`.`candidate_id`,
    `jsm`.`job_id`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_pipeline_department`
--
DROP TABLE IF EXISTS `vw_pipeline_department`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_pipeline_department` AS
SELECT
    `d`.`id` AS `department_id`,
    `d`.`name` AS `department_name`,
    `dc`.`id` AS `department_category_id`,
    `dc`.`name` AS `department_category_name`,
    `r`.`recruiter_id` AS `recruiter_id`,
    count(distinct `a`.`id`) AS `applications_count`,
    count(distinct `a`.`candidate_id`) AS `candidates_count`
FROM (
        (
            (
                (
                    `bt_candidate_routing` `r`
                    join `bt_candidates` `c` on (
                        (`c`.`id` = `r`.`candidate_id`)
                    )
                )
                left join `bt_departments` `d` on (
                    (
                        `d`.`id` = `r`.`department_id`
                    )
                )
            )
            left join `bt_department_categories` `dc` on (
                (
                    `dc`.`id` = `r`.`department_category_id`
                )
            )
        )
        left join `bt_applications` `a` on (
            (`a`.`candidate_id` = `c`.`id`)
        )
    )
GROUP BY
    `d`.`id`,
    `d`.`name`,
    `dc`.`id`,
    `dc`.`name`,
    `r`.`recruiter_id`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_recruiter_load`
--
DROP TABLE IF EXISTS `vw_recruiter_load`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_recruiter_load` AS
SELECT
    `sp`.`id` AS `recruiter_id`,
    `sp`.`name` AS `name`,
    `sp`.`department_id` AS `department_id`,
    count(`r`.`id`) AS `active_candidates`
FROM (
        `bt_staff_profiles` `sp`
        left join `bt_candidate_routing` `r` on (
            (
                (
                    `r`.`recruiter_id` = `sp`.`id`
                )
                and (
                    `r`.`assigned_at` >= (now() - interval 180 day)
                )
            )
        )
    )
WHERE (
        (`sp`.`role` = 'recruiter')
        AND (`sp`.`active` = 1)
    )
GROUP BY
    `sp`.`id`,
    `sp`.`name`,
    `sp`.`department_id`;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_skill_demand`
--
DROP TABLE IF EXISTS `vw_skill_demand`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_skill_demand` AS
SELECT
    lower(trim(`js`.`skill`)) AS `skill_name_norm`,
    count(distinct `js`.`job_id`) AS `jobs_count`
FROM `bt_job_skills` AS `js`
GROUP BY
    lower(trim(`js`.`skill`));

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_skill_supply`
--
DROP TABLE IF EXISTS `vw_skill_supply`;

CREATE ALGORITHM = UNDEFINED DEFINER = `user` @`%` SQL SECURITY INVOKER VIEW `vw_skill_supply` AS
SELECT
    lower(trim(`s`.`name`)) AS `skill_name_norm`,
    count(distinct `m`.`candidate_id`) AS `candidates_count`
FROM (
        `bt_candidate_skill_map` `m`
        join `bt_skills` `s` on ((`s`.`id` = `m`.`skill_id`))
    )
GROUP BY
    lower(trim(`s`.`name`));

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
ADD CONSTRAINT `bt_candidates_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `bt_departments` (`id`) ON DELETE SET NULL,
ADD CONSTRAINT `bt_candidates_ibfk_2` FOREIGN KEY (`department_category_id`) REFERENCES `bt_department_categories` (`id`) ON DELETE SET NULL,
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
-- Filtros para la tabla `bt_candidate_projects`
--
ALTER TABLE `bt_candidate_projects`
ADD CONSTRAINT `fk_bt_candidate_projects__candidate_id` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Filtros para la tabla `bt_candidate_skill_map`
--
ALTER TABLE `bt_candidate_skill_map`
ADD CONSTRAINT `bt_candidate_skill_map_ibfk_1` FOREIGN KEY (`skill_id`) REFERENCES `bt_skills` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_csm_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_csm_skill` FOREIGN KEY (`skill_id`) REFERENCES `bt_skills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Filtros para la tabla `bt_jobs`
--
ALTER TABLE `bt_jobs`
ADD CONSTRAINT `fk_jobs_department` FOREIGN KEY (`department_id`) REFERENCES `bt_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Filtros para la tabla `bt_job_skill_map`
--
ALTER TABLE `bt_job_skill_map`
ADD CONSTRAINT `bt_job_skill_map_ibfk_1` FOREIGN KEY (`skill_id`) REFERENCES `bt_skills` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_job_skill_map_job` FOREIGN KEY (`job_id`) REFERENCES `bt_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `bt_notifications`
--
ALTER TABLE `bt_notifications`
ADD CONSTRAINT `bt_notifications_ibfk_1` FOREIGN KEY (`candidate_id`) REFERENCES `bt_candidates` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_skill_aliases`
--
ALTER TABLE `bt_skill_aliases`
ADD CONSTRAINT `bt_skill_aliases_ibfk_1` FOREIGN KEY (`skill_id`) REFERENCES `bt_skills` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `bt_skill_department_map`
--
ALTER TABLE `bt_skill_department_map`
ADD CONSTRAINT `fk_sdm_department` FOREIGN KEY (`department_id`) REFERENCES `bt_departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_sdm_department_category` FOREIGN KEY (`department_category_id`) REFERENCES `bt_department_categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `fk_sdm_skill` FOREIGN KEY (`skill_id`) REFERENCES `bt_skills` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

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

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`user`@`%` EVENT `cleanup_password_tokens` ON SCHEDULE EVERY 1 HOUR STARTS '2025-08-16 23:56:10' ON COMPLETION NOT PRESERVE ENABLE DO CALL CleanupExpiredTokens()$$

DELIMITER;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;