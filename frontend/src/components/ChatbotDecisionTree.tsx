// src/components/ChatbotDecisionTree.tsx

import React, { useState, useEffect, useRef } from 'react';
import { MessageCircle, X, RotateCcw } from 'lucide-react';
import { env } from '../config/env';

// Interfaces para el sistema de Decision Tree
interface ChatbotNode {
  id: string;
  type: 'message' | 'options' | 'form' | 'redirect';
  content: string;
  metadata?: {
    delay?: number;
    typing_indicator?: boolean;
    analytics_event?: string;
  };
}

interface ChatbotOption {
  id: string;
  node_id: string;
  text: string;
  next_node_id: string | null;
  action_type: 'navigate' | 'submit' | 'restart' | 'end';
  action_data?: {
    url?: string;
    form_id?: string;
    analytics_event?: string;
  };
  order_position: number;
}

interface Message {
  id: string;
  type: 'bot' | 'user';
  content: string;
  timestamp: Date;
  isTyping?: boolean;
}

interface ChatbotState {
  isOpen: boolean;
  currentNodeId: string;
  messages: Message[];
  isLoading: boolean;
  conversationId: string;
}


const ChatbotDecisionTree: React.FC = () => {
  // Estado principal del chatbot
  const [state, setState] = useState<ChatbotState>({
    isOpen: false,
    currentNodeId: 'welcome',
    messages: [],
    isLoading: false,
    conversationId: ''
  });

  // Datos del chatbot (desde la API)
  const [chatbotData, setChatbotData] = useState<{ nodes: ChatbotNode[]; options: ChatbotOption[] }>({ nodes: [], options: [] });

  // Cargar nodos y opciones desde el endpoint PHP
  useEffect(() => {
    fetch(`${env.API_BASE_URL}/chatbot_decision_tree.php`)
      .then(res => res.json())
      .then(data => {
        setChatbotData({
          nodes: data.nodes || [],
          options: data.options || []
        });
      })
      .catch(error => {
        console.error('Error loading chatbot data:', error);
      });
  }, []);

  // Estado de carga de datos
  //

  // Referencias
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const chatContainerRef = useRef<HTMLDivElement>(null);

  // URL base de la API
  //

  // Generar ID único para la conversación
  const generateConversationId = () => {
    return `conv_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  };

  // Obtener opciones para el nodo actual
  const getCurrentOptions = (): ChatbotOption[] => {
    return chatbotData.options
      .filter(option => option.node_id === state.currentNodeId)
      .sort((a, b) => a.order_position - b.order_position);
  };

  // Scroll automático al final de los mensajes
  const scrollToBottom = () => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({
        behavior: 'smooth',
        block: 'end'
      });
    }, 100);
  };

  // Efecto para scroll automático
  useEffect(() => {
    if (state.isOpen) {
      scrollToBottom();
    }
  }, [state.messages, state.isOpen]);

  // Inicializar conversación
  const initializeConversation = async () => {
    const conversationId = generateConversationId();

    setState(prev => ({
      ...prev,
      conversationId,
      messages: [],
      currentNodeId: 'welcome',
      isLoading: true
    }));

    // Simular carga y mostrar mensaje de bienvenida
    setTimeout(() => {
      const welcomeNode = chatbotData.nodes.find(node => node.id === 'welcome');
      if (welcomeNode) {
        addBotMessage(welcomeNode.content, welcomeNode.metadata);
      }

      setState(prev => ({
        ...prev,
        isLoading: false
      }));
    }, 500);

    // Analytics del inicio de conversación
    trackAnalyticsEvent('chatbot_conversation_started', {
      conversation_id: conversationId,
      timestamp: new Date().toISOString()
    });
  };

  // Agregar mensaje del bot con efectos de typing
  const addBotMessage = (content: string, metadata?: ChatbotNode['metadata']) => {
    const messageId = `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    // Mostrar indicador de typing si está configurado
    if (metadata?.typing_indicator) {
      setState(prev => ({
        ...prev,
        messages: [...prev.messages, {
          id: `typing_${messageId}`,
          type: 'bot' as const,
          content: '',
          timestamp: new Date(),
          isTyping: true
        }]
      }));
    }

    // Agregar mensaje real después del delay
    setTimeout(() => {
      setState(prev => ({
        ...prev,
        messages: [
          ...prev.messages.filter(msg => !msg.isTyping),
          {
            id: messageId,
            type: 'bot' as const,
            content,
            timestamp: new Date()
          }
        ]
      }));

      // Track analytics si está configurado
      if (metadata?.analytics_event) {
        trackAnalyticsEvent(metadata.analytics_event, {
          node_id: state.currentNodeId,
          conversation_id: state.conversationId
        });
      }
    }, metadata?.delay || 0);
  };

  // Agregar mensaje del usuario
  const addUserMessage = (content: string) => {
    const messageId = `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

    setState(prev => ({
      ...prev,
      messages: [...prev.messages, {
        id: messageId,
        type: 'user' as const,
        content,
        timestamp: new Date()
      }]
    }));
  };

  // Manejar selección de opción
  const handleOptionSelect = async (option: ChatbotOption) => {
    // Agregar respuesta del usuario
    addUserMessage(option.text);

    // Track analytics
    if (option.action_data?.analytics_event) {
      trackAnalyticsEvent(option.action_data.analytics_event, {
        option_id: option.id,
        option_text: option.text,
        current_node: state.currentNodeId,
        conversation_id: state.conversationId
      });
    }

    // Procesar acción
    switch (option.action_type) {
      case 'navigate':
        if (option.action_data?.url) {
          // Redirección externa
          setTimeout(() => {
            window.location.href = option.action_data!.url!;
          }, 1000);

          addBotMessage('Te estoy redirigiendo... 🚀', { delay: 500 });
        } else if (option.next_node_id) {
          // Navegación interna
          await navigateToNode(option.next_node_id);
        }
        break;

      case 'restart':
        await restartConversation();
        break;

      case 'end':
        addBotMessage('¡Gracias por usar nuestro chatbot! Si necesitas más ayuda, no dudes en contactarnos. 😊', { delay: 800 });
        break;

      default:
        if (option.next_node_id) {
          await navigateToNode(option.next_node_id);
        }
        break;
    }
  };

  // Navegar a un nodo específico
  const navigateToNode = async (nodeId: string) => {
    const targetNode = chatbotData.nodes.find(node => node.id === nodeId);

    if (targetNode) {
      setState(prev => ({
        ...prev,
        currentNodeId: nodeId
      }));

      // Mostrar mensaje del nodo
      addBotMessage(targetNode.content, targetNode.metadata);
    }
  };

  // Reiniciar conversación
  const restartConversation = async () => {
    setState(prev => ({
      ...prev,
      messages: [],
      currentNodeId: 'welcome',
      isLoading: true
    }));

    setTimeout(() => {
      const welcomeNode = chatbotData.nodes.find(node => node.id === 'welcome');
      if (welcomeNode) {
        addBotMessage(welcomeNode.content, welcomeNode.metadata);
      }

      setState(prev => ({
        ...prev,
        isLoading: false
      }));
    }, 800);
  };

  // Track de eventos de analytics
  const trackAnalyticsEvent = async (eventName: string, eventData: Record<string, any>) => {
    try {
      await fetch(`${env.API_BASE_URL}/chatbot_analytics.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_name: eventName,
          event_data: eventData,
          conversation_id: state.conversationId
        })
      });
    } catch (e) {
      // Silenciar error en frontend
    }
  };

  // Abrir/cerrar chatbot
  const toggleChatbot = () => {
    if (!state.isOpen) {
      setState(prev => ({ ...prev, isOpen: true }));
      if (state.messages.length === 0) {
        initializeConversation();
      }
    } else {
      setState(prev => ({ ...prev, isOpen: false }));
    }
  };

  // Renderizar mensaje con formato
  const renderMessageContent = (content: string) => {
    return content.split('\n').map((line, index) => (
      <span key={index}>
        {line}
        {index < content.split('\n').length - 1 && <br />}
      </span>
    ));
  };

  return (
    <div className="fixed bottom-4 right-4 z-50 chatbot-fixed">
      {/* Botón del chatbot */}
      {!state.isOpen && (
        <button
          onClick={toggleChatbot}
          className="bg-[#FF4785] hover:bg-[#FF4785]/90 text-white rounded-full p-4 shadow-lg transition-all duration-300 hover:scale-110 group"
          aria-label="Abrir chatbot"
        >
          <MessageCircle size={24} className="group-hover:scale-110 transition-transform" />

          {/* Indicador de mensaje nuevo (opcional) */}
          <div className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center animate-pulse">
            💬
          </div>

          {/* Mensaje de error si lo hay */}

        </button>
      )}

      {/* Ventana del chatbot */}
      {state.isOpen && (
        <div className="bg-white rounded-lg shadow-2xl w-80 h-96 flex flex-col border border-gray-200">
          {/* Header */}
          <div className="bg-[#FF4785] text-white p-4 rounded-t-lg flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <div className="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                💼
              </div>
              <div>
                <h3 className="font-semibold text-sm">Asistente Virtual</h3>
                <p className="text-xs opacity-90">
                  Bubble of Talents
                </p>
              </div>
            </div>

            <div className="flex items-center space-x-2">
              <button
                onClick={restartConversation}
                className="p-1 hover:bg-white/20 rounded-full transition-colors"
                title="Reiniciar conversación"
              >
                <RotateCcw size={16} />
              </button>
              <button
                onClick={toggleChatbot}
                className="p-1 hover:bg-white/20 rounded-full transition-colors"
                aria-label="Cerrar chatbot"
              >
                <X size={16} />
              </button>
            </div>
          </div>

          {/* Mensajes */}
          <div
            ref={chatContainerRef}
            className="flex-1 overflow-y-auto p-4 space-y-3"
          >
            {state.messages.map((message) => (
              <div
                key={message.id}
                className={`flex ${message.type === 'user' ? 'justify-end' : 'justify-start'}`}
              >
                <div
                  className={`max-w-[80%] p-3 rounded-lg text-sm ${message.type === 'user'
                    ? 'bg-[#FF4785] text-white rounded-br-none'
                    : 'bg-gray-100 text-gray-800 rounded-bl-none'
                    }`}
                >
                  {message.isTyping ? (
                    <div className="flex space-x-1">
                      <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                      <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.1s' }}></div>
                      <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></div>
                    </div>
                  ) : (
                    renderMessageContent(message.content)
                  )}
                </div>
              </div>
            ))}

            {/* Opciones del nodo actual */}
            {!state.isLoading && !state.messages.some(m => m.isTyping) && (
              <div className="space-y-2 mt-4">
                {getCurrentOptions().map((option) => (
                  <button
                    key={option.id}
                    onClick={() => handleOptionSelect(option)}
                    className="w-full text-left p-3 border border-gray-200 rounded-lg hover:border-[#FF4785] hover:bg-[#FF4785]/5 transition-all duration-200 text-sm group"
                  >
                    <span className="group-hover:text-[#FF4785] transition-colors">
                      {option.text}
                    </span>
                  </button>
                ))}
              </div>
            )}

            <div ref={messagesEndRef} />
          </div>

          {/* Footer con info */}
          <div className="border-t border-gray-200 p-2 text-center">
            <p className="text-xs text-gray-500">
              Powered by Bubble of Talents
            </p>
          </div>
        </div>
      )}
    </div>
  );
};

export default ChatbotDecisionTree;
