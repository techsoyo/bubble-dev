-- Migración para el sistema de Decision Tree Chatbot
-- Archivo: backend/database/migrations/create_chatbot_system.sql
-- Prefijo de tablas: bt_

-- Tabla principal de nodos del chatbot
CREATE TABLE IF NOT EXISTS bt_chatbot_nodes (
    id VARCHAR(50) PRIMARY KEY,
    type ENUM('message', 'options', 'form', 'redirect') NOT NULL DEFAULT 'message',
    content TEXT NOT NULL,
    metadata JSON DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by VARCHAR(100) DEFAULT 'system',
    
    INDEX idx_type (type),
    INDEX idx_active (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de opciones/botones del chatbot
CREATE TABLE IF NOT EXISTS bt_chatbot_options (
    id VARCHAR(50) PRIMARY KEY,
    node_id VARCHAR(50) NOT NULL,
    text VARCHAR(255) NOT NULL,
    next_node_id VARCHAR(50) DEFAULT NULL,
    action_type ENUM('navigate', 'submit', 'restart', 'end') NOT NULL DEFAULT 'navigate',
    action_data JSON DEFAULT NULL,
    order_position INT NOT NULL DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (node_id) REFERENCES bt_chatbot_nodes(id) ON DELETE CASCADE,
    FOREIGN KEY (next_node_id) REFERENCES bt_chatbot_nodes(id) ON DELETE SET NULL,
    
    INDEX idx_node_id (node_id),
    INDEX idx_next_node_id (next_node_id),
    INDEX idx_order_position (order_position),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de analytics del chatbot
CREATE TABLE IF NOT EXISTS bt_chatbot_analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id VARCHAR(100) NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    event_data JSON DEFAULT NULL,
    node_id VARCHAR(50) DEFAULT NULL,
    option_id VARCHAR(50) DEFAULT NULL,
    user_ip VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (node_id) REFERENCES bt_chatbot_nodes(id) ON DELETE SET NULL,
    FOREIGN KEY (option_id) REFERENCES bt_chatbot_options(id) ON DELETE SET NULL,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_event_name (event_name),
    INDEX idx_node_id (node_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar datos iniciales del chatbot
INSERT INTO bt_chatbot_nodes (id, type, content, metadata) VALUES
('welcome', 'message', '¡Hola! 👋 Soy el asistente virtual de Bubble of Talents. ¿En qué puedo ayudarte hoy?', 
 JSON_OBJECT('delay', 1000, 'typing_indicator', true, 'analytics_event', 'chatbot_welcome_shown')),

('job_search', 'message', 'Perfecto! Te ayudo con la búsqueda de empleo. ¿Qué tipo de información necesitas?', 
 JSON_OBJECT('delay', 800, 'typing_indicator', true)),

('company_info', 'message', 'Me alegra que quieras conocer más sobre nosotros. ¿Qué aspecto de la empresa te interesa?', 
 JSON_OBJECT('delay', 800, 'typing_indicator', true)),

('application_help', 'message', 'Te guío paso a paso en el proceso de aplicación. ¿En qué necesitas ayuda específicamente?', 
 JSON_OBJECT('delay', 800, 'typing_indicator', true)),

('contact_info', 'message', 'Aquí tienes nuestros datos de contacto:\n\n📧 Email: rrhh@bubbleoftalents.com\n📞 Teléfono: +34 900 123 456\n🏢 Dirección: Calle Talento, 123, Madrid\n\n¿Necesitas algo más?', 
 JSON_OBJECT('delay', 1200, 'typing_indicator', true));

-- Insertar opciones del chatbot
INSERT INTO bt_chatbot_options (id, node_id, text, next_node_id, action_type, action_data, order_position) VALUES
-- Opciones del nodo welcome
('opt_1', 'welcome', '🔍 Buscar empleo', 'job_search', 'navigate', 
 JSON_OBJECT('analytics_event', 'chatbot_job_search_selected'), 1),

('opt_2', 'welcome', '🏢 Información de la empresa', 'company_info', 'navigate', 
 JSON_OBJECT('analytics_event', 'chatbot_company_info_selected'), 2),

('opt_3', 'welcome', '📝 Ayuda con aplicaciones', 'application_help', 'navigate', 
 JSON_OBJECT('analytics_event', 'chatbot_application_help_selected'), 3),

('opt_4', 'welcome', '📞 Contacto', 'contact_info', 'navigate', 
 JSON_OBJECT('analytics_event', 'chatbot_contact_selected'), 4),

-- Opciones del nodo job_search
('opt_5', 'job_search', '💼 Ver ofertas disponibles', NULL, 'navigate', 
 JSON_OBJECT('url', '/jobs', 'analytics_event', 'chatbot_jobs_redirect'), 1),

('opt_6', 'job_search', '📋 Crear perfil de candidato', NULL, 'navigate', 
 JSON_OBJECT('url', '/register', 'analytics_event', 'chatbot_register_redirect'), 2),

('opt_7', 'job_search', '🔙 Volver al inicio', 'welcome', 'restart', 
 JSON_OBJECT('analytics_event', 'chatbot_restart_from_jobs'), 3),

('opt_8', 'company_info', '📰 Noticias y blog', NULL, 'navigate', 
 JSON_OBJECT('url', '/blog', 'analytics_event', 'chatbot_blog_redirect'), 2),

('opt_9', 'company_info', '🔙 Volver al inicio', 'welcome', 'restart', 
 JSON_OBJECT('analytics_event', 'chatbot_restart_from_company'), 3),

-- Opciones del nodo application_help
('opt_10', 'application_help', '📝 Crear CV perfecto', NULL, 'navigate', 
 JSON_OBJECT('url', '/cv-tips', 'analytics_event', 'chatbot_cv_tips_redirect'), 1),

('opt_11', 'application_help', '💡 Consejos de entrevista', NULL, 'navigate', 
 JSON_OBJECT('url', '/interview-tips', 'analytics_event', 'chatbot_interview_tips_redirect'), 2),

('opt_12', 'application_help', '🔙 Volver al inicio', 'welcome', 'restart', 
 JSON_OBJECT('analytics_event', 'chatbot_restart_from_help'), 3),

-- Opciones del nodo contact_info
('opt_13', 'contact_info', '🔙 Volver al inicio', 'welcome', 'restart', 
 JSON_OBJECT('analytics_event', 'chatbot_restart_from_contact'), 1);

-- Verificar la estructura creada
SELECT 'Nodos creados:' as info, COUNT(*) as count FROM bt_chatbot_nodes
UNION ALL
SELECT 'Opciones creadas:', COUNT(*) FROM bt_chatbot_options;
