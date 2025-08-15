import React, { useState, useEffect } from 'react';
import { Plus, Edit2, Trash2, Save, X, ArrowRight, MessageCircle, List, Settings, BarChart3 } from 'lucide-react';

const ChatbotAdmin = () => {
  type NodeType = {
    id: string;
    type: string;
    content: string;
    metadata: any;
    is_active: boolean;
    created_by: string;
  };
  type OptionType = {
    id: string;
    node_id: string;
    text: string;
    next_node_id: string | null;
    action_type: string;
    action_data: any;
    order_position: number;
    is_active: boolean;
  };
  const [activeTab, setActiveTab] = useState<string>('nodes');
  const [nodes, setNodes] = useState<NodeType[]>([]);
  const [options, setOptions] = useState<OptionType[]>([]);
  const [editingItem, setEditingItem] = useState<any>(null);
  const [showForm, setShowForm] = useState<boolean>(false);

  // Estado para diálogo de confirmación personalizado
  const [showDeleteDialog, setShowDeleteDialog] = useState<boolean>(false);
  const [deleteTarget, setDeleteTarget] = useState<{ type: 'node' | 'option', id: string } | null>(null);

  // Cargar nodos y opciones desde la API
  useEffect(() => {
    loadChatbotData();
  }, []);

  interface NodeFormProps {
    node?: NodeType;
    onSave: (node: NodeType) => void;
    onCancel: () => void;
  }
  const NodeForm: React.FC<NodeFormProps> = ({ node, onSave, onCancel }) => {
    const [formData, setFormData] = useState(node || {
      id: '',
      type: 'message',
      content: '',
      metadata: {},
      is_active: true,
      created_by: 'admin'
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
      e.preventDefault();
      onSave(formData);
    };

    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-semibold">
              {node ? 'Editar Nodo' : 'Crear Nuevo Nodo'}
            </h3>
            <button onClick={onCancel} className="text-gray-500 hover:text-gray-700">
              <X size={20} />
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">ID del Nodo</label>
              <input
                type="text"
                value={formData.id}
                onChange={(e) => setFormData({ ...formData, id: e.target.value })}
                className={`w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 ${node ? 'bg-gray-100 cursor-not-allowed' : ''
                  }`}
                disabled={!!node}
                readOnly={!!node}
                required
                title={node ? 'El ID no se puede modificar en nodos existentes' : ''}
              />
              {node && (
                <p className="text-xs text-gray-500 mt-1">
                  ⚠️ El ID no se puede modificar en nodos existentes por motivos de integridad de datos
                </p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
              <select
                value={formData.type}
                onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="message">Mensaje</option>
                <option value="options">Opciones</option>
                <option value="form">Formulario</option>
                <option value="redirect">Redirección</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Contenido</label>
              <textarea
                value={formData.content}
                onChange={(e) => setFormData({ ...formData, content: e.target.value })}
                rows={4}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Metadata (JSON)</label>
              <textarea
                value={JSON.stringify(formData.metadata, null, 2)}
                onChange={(e) => {
                  try {
                    setFormData({ ...formData, metadata: JSON.parse(e.target.value) });
                  } catch (err) {
                    // Handle invalid JSON gracefully
                  }
                }}
                rows={3}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
              />
            </div>

            <div className="flex items-center">
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                className="mr-2"
              />
              <label className="text-sm font-medium text-gray-700">Activo</label>
            </div>

            <div className="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                onClick={onCancel}
                className="px-4 py-2 text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                <Save size={16} className="inline mr-1" />
                Guardar
              </button>
            </div>
          </form>
        </div>
      </div>
    );
  };

  interface OptionFormProps {
    option?: OptionType;
    onSave: (option: OptionType) => void;
    onCancel: () => void;
  }
  const OptionForm: React.FC<OptionFormProps> = ({ option, onSave, onCancel }) => {
    const [formData, setFormData] = useState(option || {
      id: '',
      node_id: '',
      text: '',
      next_node_id: '',
      action_type: 'navigate',
      action_data: {},
      order_position: 1,
      is_active: true
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
      e.preventDefault();
      onSave(formData);
    };

    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div className="flex justify-between items-center mb-4">
            <h3 className="text-lg font-semibold">
              {option ? 'Editar Opción' : 'Crear Nueva Opción'}
            </h3>
            <button onClick={onCancel} className="text-gray-500 hover:text-gray-700">
              <X size={20} />
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">ID de la Opción</label>
              <input
                type="text"
                value={formData.id}
                onChange={(e) => setFormData({ ...formData, id: e.target.value })}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Nodo Padre</label>
              <select
                value={formData.node_id}
                onChange={(e) => setFormData({ ...formData, node_id: e.target.value })}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="">Seleccionar nodo...</option>
                {nodes.map(node => (
                  <option key={node.id} value={node.id}>{node.id}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Texto de la Opción</label>
              <input
                type="text"
                value={formData.text}
                onChange={(e) => setFormData({ ...formData, text: e.target.value })}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Siguiente Nodo</label>
              <select
                value={formData.next_node_id ?? ''}
                onChange={(e) => setFormData({ ...formData, next_node_id: e.target.value })}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Ninguno (fin o redirección)</option>
                {nodes.map(node => (
                  <option key={node.id} value={node.id}>{node.id}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Tipo de Acción</label>
              <select
                value={formData.action_type}
                onChange={(e) => setFormData({ ...formData, action_type: e.target.value })}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="navigate">Navegar</option>
                <option value="submit">Enviar</option>
                <option value="restart">Reiniciar</option>
                <option value="end">Finalizar</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Datos de Acción (JSON)</label>
              <textarea
                value={JSON.stringify(formData.action_data, null, 2)}
                onChange={(e) => {
                  try {
                    setFormData({ ...formData, action_data: JSON.parse(e.target.value) });
                  } catch (err) {
                    // Handle invalid JSON gracefully
                  }
                }}
                rows={3}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Posición</label>
              <input
                type="number"
                value={formData.order_position}
                onChange={(e) => setFormData({ ...formData, order_position: parseInt(e.target.value) })}
                className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                min="1"
              />
            </div>

            <div className="flex items-center">
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                className="mr-2"
              />
              <label className="text-sm font-medium text-gray-700">Activo</label>
            </div>

            <div className="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                onClick={onCancel}
                className="px-4 py-2 text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
              >
                Cancelar
              </button>
              <button
                type="submit"
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                <Save size={16} className="inline mr-1" />
                Guardar
              </button>
            </div>
          </form>
        </div>
      </div>
    );
  };

  // CRUD NODOS
  const handleSaveNode = async (nodeData: NodeType) => {
    try {
      if (editingItem) {
        // Update
        const response = await fetch('http://localhost:8000/test_chatbot_crud.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: 'node',
            id: nodeData.id,
            node_type: nodeData.type,
            content: nodeData.content,
            metadata: nodeData.metadata,
            is_active: nodeData.is_active
          })
        });

        const result = await response.json();
        if (result.success) {
          alert('Nodo actualizado correctamente!');
          loadChatbotData();
        } else {
          alert('Error: ' + result.error);
        }
      } else {
        // Create
        const response = await fetch('http://localhost:8000/test_chatbot_crud.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: 'node',
            id: nodeData.id,
            node_type: nodeData.type,
            content: nodeData.content,
            metadata: nodeData.metadata,
            is_active: nodeData.is_active
          })
        });

        const result = await response.json();
        if (result.success) {
          alert('Nodo creado correctamente!');
          loadChatbotData();
        } else {
          alert('Error: ' + result.error);
        }
      }
      setEditingItem(null);
      setShowForm(false);
    } catch (error) {
      console.error('Error saving node:', error);
      alert('Error al guardar el nodo');
    }
  };

  // Función para recargar datos
  const loadChatbotData = async () => {
    try {
      const response = await fetch('http://localhost:8000/test_chatbot_crud.php');
      const data = await response.json();
      if (data.success) {
        setNodes(data.data.nodes || []);
        setOptions(data.data.options || []);
      }
    } catch (error) {
      console.error('Error loading chatbot data:', error);
    }
  };

  // CRUD OPCIONES
  const handleSaveOption = async (optionData: OptionType) => {
    try {
      if (editingItem) {
        // Update
        const response = await fetch('http://localhost:8000/test_chatbot_crud.php', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: 'option',
            id: optionData.id,
            node_id: optionData.node_id,
            text: optionData.text,
            next_node_id: optionData.next_node_id,
            action_type: optionData.action_type,
            action_data: optionData.action_data,
            order_position: optionData.order_position,
            is_active: optionData.is_active
          })
        });

        const result = await response.json();
        if (result.success) {
          alert('Opción actualizada correctamente!');
          loadChatbotData();
        } else {
          alert('Error: ' + result.error);
        }
      } else {
        // Create
        const response = await fetch('http://localhost:8000/test_chatbot_crud.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            type: 'option',
            id: optionData.id,
            node_id: optionData.node_id,
            text: optionData.text,
            next_node_id: optionData.next_node_id,
            action_type: optionData.action_type,
            action_data: optionData.action_data,
            order_position: optionData.order_position,
            is_active: optionData.is_active
          })
        });

        const result = await response.json();
        if (result.success) {
          alert('Opción creada correctamente!');
          loadChatbotData();
        } else {
          alert('Error: ' + result.error);
        }
      }
      setEditingItem(null);
      setShowForm(false);
    } catch (error) {
      console.error('Error saving option:', error);
      alert('Error al guardar la opción');
    }
  };

  // DELETE NODO
  const handleDeleteNode = async (nodeId: string) => {
    setDeleteTarget({ type: 'node', id: nodeId });
    setShowDeleteDialog(true);
  };

  // Función para confirmar eliminación
  const confirmDelete = async () => {
    if (!deleteTarget) return;

    try {
      if (deleteTarget.type === 'node') {
        const response = await fetch(`http://localhost:8000/test_chatbot_crud.php?node_id=${encodeURIComponent(deleteTarget.id)}`, {
          method: 'DELETE'
        });

        const result = await response.json();
        if (result.success) {
          alert('Nodo eliminado correctamente!');
          loadChatbotData();
        } else {
          alert('Error: ' + result.error);
        }
      } else if (deleteTarget.type === 'option') {
        const response = await fetch(`http://localhost:8000/test_chatbot_crud.php?option_id=${encodeURIComponent(deleteTarget.id)}`, {
          method: 'DELETE'
        });

        const result = await response.json();
        if (result.success) {
          alert('Opción eliminada correctamente!');
          loadChatbotData();
        } else {
          alert('Error: ' + result.error);
        }
      }
    } catch (error) {
      console.error('Error deleting:', error);
      alert('Error al eliminar');
    } finally {
      setShowDeleteDialog(false);
      setDeleteTarget(null);
    }
  };

  // DELETE OPCION
  const handleDeleteOption = async (optionId: string) => {
    setDeleteTarget({ type: 'option', id: optionId });
    setShowDeleteDialog(true);
  };

  const getNodeOptions = (nodeId: string) => {
    return options.filter(option => option.node_id === nodeId).sort((a, b) => a.order_position - b.order_position);
  };

  return (
    <div className="max-w-7xl mx-auto p-6 bg-gray-50 min-h-screen">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-gray-800 mb-2">Administrador de Chatbot</h1>
        <p className="text-gray-600">Gestiona los nodos, opciones y analíticas de tu chatbot decision tree</p>
      </div>

      {/* Navegación por pestañas */}
      <div className="flex border-b border-gray-200 mb-6">
        <button
          onClick={() => setActiveTab('nodes')}
          className={`px-4 py-2 font-medium text-sm border-b-2 transition-colors ${activeTab === 'nodes'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
        >
          <MessageCircle size={16} className="inline mr-1" />
          Nodos ({nodes.length})
        </button>
        <button
          onClick={() => setActiveTab('options')}
          className={`px-4 py-2 font-medium text-sm border-b-2 transition-colors ${activeTab === 'options'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
        >
          <List size={16} className="inline mr-1" />
          Opciones ({options.length})
        </button>
        <button
          onClick={() => setActiveTab('flow')}
          className={`px-4 py-2 font-medium text-sm border-b-2 transition-colors ${activeTab === 'flow'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
        >
          <Settings size={16} className="inline mr-1" />
          Flujo Visual
        </button>
        <button
          onClick={() => setActiveTab('analytics')}
          className={`px-4 py-2 font-medium text-sm border-b-2 transition-colors ${activeTab === 'analytics'
            ? 'border-blue-500 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
        >
          <BarChart3 size={16} className="inline mr-1" />
          Analíticas
        </button>
      </div>

      {/* Contenido de las pestañas */}
      {activeTab === 'nodes' && (
        <div>
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-xl font-semibold text-gray-800">Gestión de Nodos</h2>
            <button
              onClick={() => {
                setEditingItem(null);
                setShowForm(true);
              }}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              <Plus size={16} className="inline mr-1" />
              Nuevo Nodo
            </button>
          </div>

          <div className="grid gap-4">
            {nodes.map(node => (
              <div key={node.id} className="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
                <div className="flex justify-between items-start">
                  <div className="flex-1">
                    <div className="flex items-center space-x-2 mb-2">
                      <span className="font-mono text-sm bg-gray-100 px-2 py-1 rounded">{node.id}</span>
                      <span className={`text-xs px-2 py-1 rounded-full ${node.type === 'message' ? 'bg-green-100 text-green-800' :
                        node.type === 'options' ? 'bg-blue-100 text-blue-800' :
                          node.type === 'form' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-purple-100 text-purple-800'
                        }`}>
                        {node.type}
                      </span>
                      {!node.is_active && <span className="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">Inactivo</span>}
                    </div>
                    <p className="text-gray-700 mb-2 whitespace-pre-line">{node.content}</p>
                    <div className="text-xs text-gray-500">
                      Opciones disponibles: {getNodeOptions(node.id).length}
                    </div>
                  </div>
                  <div className="flex space-x-2 ml-4">
                    <button
                      onClick={() => {
                        setEditingItem(node);
                        setShowForm(true);
                      }}
                      className="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded"
                    >
                      <Edit2 size={16} />
                    </button>
                    <button
                      onClick={() => handleDeleteNode(node.id)}
                      className="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>

                {/* Mostrar opciones del nodo */}
                {getNodeOptions(node.id).length > 0 && (
                  <div className="mt-3 pt-3 border-t border-gray-200">
                    <h4 className="text-sm font-medium text-gray-700 mb-2">Opciones:</h4>
                    <div className="space-y-1">
                      {getNodeOptions(node.id).map(option => (
                        <div key={option.id} className="flex items-center text-sm text-gray-600">
                          <span className="mr-2">{option.order_position}.</span>
                          <span className="flex-1">{option.text}</span>
                          {option.next_node_id && (
                            <div className="flex items-center text-xs text-blue-600">
                              <ArrowRight size={12} className="mr-1" />
                              {option.next_node_id}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {activeTab === 'options' && (
        <div>
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-xl font-semibold text-gray-800">Gestión de Opciones</h2>
            <button
              onClick={() => {
                setEditingItem(null);
                setShowForm(true);
              }}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
              <Plus size={16} className="inline mr-1" />
              Nueva Opción
            </button>
          </div>

          <div className="bg-white rounded-lg shadow-md overflow-hidden">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nodo Padre</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Texto</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Siguiente</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orden</th>
                  <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {options.map(option => (
                  <tr key={option.id} className={!option.is_active ? 'opacity-50' : ''}>
                    <td className="px-4 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{option.id}</td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-700">{option.node_id}</td>
                    <td className="px-4 py-4 text-sm text-gray-700 max-w-xs truncate">{option.text}</td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-700">{option.next_node_id || '-'}</td>
                    <td className="px-4 py-4 whitespace-nowrap">
                      <span className={`inline-flex px-2 py-1 text-xs rounded-full ${option.action_type === 'navigate' ? 'bg-blue-100 text-blue-800' :
                        option.action_type === 'submit' ? 'bg-green-100 text-green-800' :
                          option.action_type === 'restart' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-red-100 text-red-800'
                        }`}>
                        {option.action_type}
                      </span>
                    </td>
                    <td className="px-4 py-4 whitespace-nowrap text-sm text-gray-700">{option.order_position}</td>
                    <td className="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex justify-end space-x-2">
                        <button
                          onClick={() => {
                            setEditingItem(option);
                            setShowForm(true);
                          }}
                          className="text-gray-600 hover:text-blue-600"
                        >
                          <Edit2 size={16} />
                        </button>
                        <button
                          onClick={() => handleDeleteOption(option.id)}
                          className="text-gray-600 hover:text-red-600"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeTab === 'flow' && (
        <div>
          <h2 className="text-xl font-semibold text-gray-800 mb-4">Visualización del Flujo</h2>
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="space-y-6">
              {nodes.map(node => (
                <div key={node.id} className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center space-x-2 mb-3">
                    <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                    <span className="font-mono text-sm font-medium">{node.id}</span>
                    <span className="text-xs text-gray-500">({node.type})</span>
                  </div>
                  <p className="text-gray-700 mb-3 text-sm">{node.content}</p>

                  {getNodeOptions(node.id).length > 0 && (
                    <div className="ml-4 space-y-2">
                      {getNodeOptions(node.id).map(option => (
                        <div key={option.id} className="flex items-center space-x-3 p-2 bg-gray-50 rounded">
                          <span className="w-6 h-6 bg-gray-300 text-gray-700 rounded-full text-xs flex items-center justify-center">
                            {option.order_position}
                          </span>
                          <span className="flex-1 text-sm">{option.text}</span>
                          {option.next_node_id && (
                            <div className="flex items-center text-xs text-blue-600">
                              <ArrowRight size={14} className="mr-1" />
                              <span className="bg-blue-100 px-2 py-1 rounded">{option.next_node_id}</span>
                            </div>
                          )}
                          {option.action_data?.url && (
                            <div className="text-xs text-green-600">
                              → {option.action_data.url}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {activeTab === 'analytics' && (
        <div>
          <h2 className="text-xl font-semibold text-gray-800 mb-4">Panel de Analíticas</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div className="bg-white rounded-lg shadow-md p-4">
              <div className="flex items-center">
                <div className="p-2 bg-blue-100 rounded-lg">
                  <MessageCircle className="text-blue-600" size={20} />
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-gray-600">Total Nodos</p>
                  <p className="text-2xl font-semibold text-gray-900">{nodes.length}</p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow-md p-4">
              <div className="flex items-center">
                <div className="p-2 bg-green-100 rounded-lg">
                  <List className="text-green-600" size={20} />
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-gray-600">Total Opciones</p>
                  <p className="text-2xl font-semibold text-gray-900">{options.length}</p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow-md p-4">
              <div className="flex items-center">
                <div className="p-2 bg-yellow-100 rounded-lg">
                  <Settings className="text-yellow-600" size={20} />
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-gray-600">Nodos Activos</p>
                  <p className="text-2xl font-semibold text-gray-900">{nodes.filter(n => n.is_active).length}</p>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow-md p-4">
              <div className="flex items-center">
                <div className="p-2 bg-purple-100 rounded-lg">
                  <BarChart3 className="text-purple-600" size={20} />
                </div>
                <div className="ml-3">
                  <p className="text-sm font-medium text-gray-600">Opciones Activas</p>
                  <p className="text-2xl font-semibold text-gray-900">{options.filter(o => o.is_active).length}</p>
                </div>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-lg font-semibold text-gray-800 mb-4">Resumen de Configuración</h3>
            <div className="space-y-4">
              <div>
                <h4 className="font-medium text-gray-700 mb-2">Distribución por Tipo de Nodo</h4>
                <div className="flex flex-wrap gap-2">
                  {['message', 'options', 'form', 'redirect'].map(type => {
                    const count = nodes.filter(n => n.type === type).length;
                    return (
                      <span key={type} className={`px-3 py-1 text-sm rounded-full ${type === 'message' ? 'bg-green-100 text-green-800' :
                        type === 'options' ? 'bg-blue-100 text-blue-800' :
                          type === 'form' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-purple-100 text-purple-800'
                        }`}>
                        {type}: {count}
                      </span>
                    );
                  })}
                </div>
              </div>

              <div>
                <h4 className="font-medium text-gray-700 mb-2">Distribución por Tipo de Acción</h4>
                <div className="flex flex-wrap gap-2">
                  {['navigate', 'submit', 'restart', 'end'].map(actionType => {
                    const count = options.filter(o => o.action_type === actionType).length;
                    return (
                      <span key={actionType} className={`px-3 py-1 text-sm rounded-full ${actionType === 'navigate' ? 'bg-blue-100 text-blue-800' :
                        actionType === 'submit' ? 'bg-green-100 text-green-800' :
                          actionType === 'restart' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-red-100 text-red-800'
                        }`}>
                        {actionType}: {count}
                      </span>
                    );
                  })}
                </div>
              </div>

              <div>
                <h4 className="font-medium text-gray-700 mb-2">Nodos con Más Opciones</h4>
                <div className="space-y-2">
                  {nodes
                    .map(node => ({
                      ...node,
                      optionCount: getNodeOptions(node.id).length
                    }))
                    .sort((a, b) => b.optionCount - a.optionCount)
                    .slice(0, 5)
                    .map(node => (
                      <div key={node.id} className="flex justify-between items-center p-2 bg-gray-50 rounded">
                        <span className="font-mono text-sm">{node.id}</span>
                        <span className="text-sm text-gray-600">{node.optionCount} opciones</span>
                      </div>
                    ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Formularios modales */}
      {showForm && activeTab === 'nodes' && (
        <NodeForm
          node={editingItem}
          onSave={handleSaveNode}
          onCancel={() => {
            setShowForm(false);
            setEditingItem(null);
          }}
        />
      )}

      {showForm && activeTab === 'options' && (
        <OptionForm
          option={editingItem}
          onSave={handleSaveOption}
          onCancel={() => {
            setShowForm(false);
            setEditingItem(null);
          }}
        />
      )}

      {/* Diálogo de confirmación de eliminación personalizado */}
      {showDeleteDialog && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 className="text-lg font-semibold text-gray-800 mb-4">
              Confirmar Eliminación
            </h3>
            <p className="text-gray-600 mb-6">
              {deleteTarget?.type === 'node'
                ? '¿Estás seguro de que quieres eliminar este nodo? También se eliminarán sus opciones asociadas.'
                : '¿Estás seguro de que quieres eliminar esta opción?'
              }
            </p>
            <div className="flex justify-end space-x-3">
              <button
                onClick={() => {
                  setShowDeleteDialog(false);
                  setDeleteTarget(null);
                }}
                className="px-4 py-2 text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
              >
                Cancelar
              </button>
              <button
                onClick={confirmDelete}
                className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ChatbotAdmin;
