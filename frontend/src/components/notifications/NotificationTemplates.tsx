// src/components/notifications/NotificationTemplates.tsx
import React, { useState, useEffect } from 'react';
import { getNotificationTemplates, sendNotification } from '../../services/ApiService';

interface NotificationTemplate {
  id: string;
  name: string;
  description: string;
  subject: string;
  body: string;
  channels: Array<'email' | 'sms' | 'push' | 'in-app'>;
  variables: string[];
}

interface NotificationTemplatesProps {
  userType: 'candidate' | 'recruiter' | 'admin';
  onNotificationSent?: (success: boolean, templateId: string) => void;
  className?: string;
}

const NotificationTemplates: React.FC<NotificationTemplatesProps> = ({
  userType,
  onNotificationSent,
  className = '',
}) => {
  const [templates, setTemplates] = useState<NotificationTemplate[]>([]);
  const [selectedTemplate, setSelectedTemplate] = useState<NotificationTemplate | null>(null);
  const [recipientId, setRecipientId] = useState('');
  const [recipientType, setRecipientType] = useState<'candidate' | 'recruiter' | 'admin'>('candidate');
  const [variables, setVariables] = useState<Record<string, string>>({});
  const [scheduledFor, setScheduledFor] = useState('');
  const [channel, setChannel] = useState<'email' | 'sms' | 'push' | 'in-app'>('email');

  const [isLoading, setIsLoading] = useState(true);
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // Cargar las plantillas de notificaciones al inicializar el componente
  useEffect(() => {
    const loadTemplates = async () => {
      try {
        const response = await getNotificationTemplates();

        if (response.success) {
          setTemplates(response.data.templates || []);
        } else {
          setError(response.error || 'Error al cargar las plantillas');
        }
      } catch (error) {
        console.error('Error al cargar plantillas de notificación:', error);
        setError('Error de conexión, intenta más tarde');
      } finally {
        setIsLoading(false);
      }
    };

    loadTemplates();
  }, []);

  // Actualizar los campos de variables cuando se selecciona una plantilla
  useEffect(() => {
    if (selectedTemplate) {
      const newVariables: Record<string, string> = {};
      selectedTemplate.variables.forEach(variable => {
        newVariables[variable] = '';
      });
      setVariables(newVariables);

      // Establecer el canal por defecto si la plantilla solo tiene uno
      if (selectedTemplate.channels.length === 1) {
        setChannel(selectedTemplate.channels[0]);
      }
    }
  }, [selectedTemplate]);

  const handleSelectTemplate = (templateId: string) => {
    const template = templates.find(t => t.id === templateId);
    setSelectedTemplate(template || null);
    setError(null);
    setSuccess(null);
  };

  const handleVariableChange = (variable: string, value: string) => {
    setVariables(prev => ({
      ...prev,
      [variable]: value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedTemplate || !recipientId) {
      setError('Por favor, selecciona una plantilla y especifica un destinatario');
      return;
    }

    setIsSending(true);
    setError(null);
    setSuccess(null);

    try {
      const response = await sendNotification({
        recipient_id: recipientId,
        recipient_type: recipientType,
        template_id: selectedTemplate.id,
        variables,
        channel,
        scheduled_for: scheduledFor || undefined,
      });

      if (response.success) {
        setSuccess('Notificación enviada correctamente');
        onNotificationSent && onNotificationSent(true, selectedTemplate.id);
      } else {
        setError(response.error || 'Error al enviar la notificación');
        onNotificationSent && onNotificationSent(false, selectedTemplate.id);
      }
    } catch (error) {
      console.error('Error al enviar notificación:', error);
      setError('Error de conexión, intenta más tarde');
      onNotificationSent && onNotificationSent(false, selectedTemplate.id);
    } finally {
      setIsSending(false);
    }
  };

  if (isLoading) {
    return <div className={className}>Cargando plantillas...</div>;
  }

  return (
    <div className={`space-y-6 ${className}`}>
      <h3 className="text-lg font-medium text-gray-900">Comunicaciones automáticas</h3>

      {error && (
        <div className="bg-red-50 p-3 rounded-md text-red-600 text-sm mb-4">
          {error}
        </div>
      )}

      {success && (
        <div className="bg-green-50 p-3 rounded-md text-green-600 text-sm mb-4">
          {success}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        <div>
          <label htmlFor="templateSelect" className="block text-sm font-medium text-gray-700 mb-1">
            Plantilla de notificación
          </label>
          <select
            id="templateSelect"
            value={selectedTemplate?.id || ''}
            onChange={(e) => handleSelectTemplate(e.target.value)}
            className="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#F24495] focus:border-[#F24495]"
          >
            <option value="">Selecciona una plantilla</option>
            {templates.map(template => (
              <option key={template.id} value={template.id}>
                {template.name}
              </option>
            ))}
          </select>
        </div>

        {selectedTemplate && (
          <>
            <div className="bg-gray-50 p-3 rounded-md">
              <p className="text-sm text-gray-600">{selectedTemplate.description}</p>
            </div>

            <div>
              <label htmlFor="recipientType" className="block text-sm font-medium text-gray-700 mb-1">
                Tipo de destinatario
              </label>
              <select
                id="recipientType"
                value={recipientType}
                onChange={(e) => setRecipientType(e.target.value as 'candidate' | 'recruiter' | 'admin')}
                className="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#F24495] focus:border-[#F24495]"
              >
                <option value="candidate">Candidato</option>
                <option value="recruiter">Reclutador</option>
                <option value="admin">Administrador</option>
              </select>
            </div>

            <div>
              <label htmlFor="recipientId" className="block text-sm font-medium text-gray-700 mb-1">
                ID del destinatario
              </label>
              <input
                type="text"
                id="recipientId"
                value={recipientId}
                onChange={(e) => setRecipientId(e.target.value)}
                className="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#F24495] focus:border-[#F24495]"
                placeholder="ID del destinatario"
                required
              />
            </div>

            {selectedTemplate.channels.length > 1 && (
              <div>
                <label htmlFor="channel" className="block text-sm font-medium text-gray-700 mb-1">
                  Canal de notificación
                </label>
                <select
                  id="channel"
                  value={channel}
                  onChange={(e) => setChannel(e.target.value as 'email' | 'sms' | 'push' | 'in-app')}
                  className="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#F24495] focus:border-[#F24495]"
                >
                  {selectedTemplate.channels.map(ch => (
                    <option key={ch} value={ch}>
                      {ch === 'email' ? 'Email' :
                        ch === 'sms' ? 'SMS' :
                          ch === 'push' ? 'Notificación push' : 'Notificación en la app'}
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div>
              <label htmlFor="scheduledFor" className="block text-sm font-medium text-gray-700 mb-1">
                Programar para (opcional)
              </label>
              <input
                type="datetime-local"
                id="scheduledFor"
                value={scheduledFor}
                onChange={(e) => setScheduledFor(e.target.value)}
                className="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#F24495] focus:border-[#F24495]"
              />
              <p className="mt-1 text-xs text-gray-500">
                Deja en blanco para enviar inmediatamente
              </p>
            </div>

            {selectedTemplate.variables.length > 0 && (
              <div className="space-y-4">
                <h4 className="font-medium text-gray-800">Variables de la plantilla</h4>

                {selectedTemplate.variables.map(variable => (
                  <div key={variable}>
                    <label
                      htmlFor={`var_${variable}`}
                      className="block text-sm font-medium text-gray-700 mb-1"
                    >
                      {variable}
                    </label>
                    <input
                      type="text"
                      id={`var_${variable}`}
                      value={variables[variable] || ''}
                      onChange={(e) => handleVariableChange(variable, e.target.value)}
                      className="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#F24495] focus:border-[#F24495]"
                      placeholder={`Valor para ${variable}`}
                    />
                  </div>
                ))}
              </div>
            )}

            <div className="pt-4">
              <button
                type="submit"
                disabled={isSending}
                className="w-full py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#F24495] hover:bg-[#E13385] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#F24495]"
              >
                {isSending ? 'Enviando...' : 'Enviar notificación'}
              </button>
            </div>
          </>
        )}
      </form>
    </div>
  );
};

export default NotificationTemplates;
