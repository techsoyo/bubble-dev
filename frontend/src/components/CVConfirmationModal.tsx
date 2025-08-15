import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { suggestDepartmentFromSkills } from '../lib/category-department-mapping';

interface CVConfirmationModalProps {
  isOpen: boolean;
  onClose: () => void;
  extractedData: any;
  onAccept: (data: any) => void;
}

const CVConfirmationModal: React.FC<CVConfirmationModalProps> = ({
  isOpen,
  onClose,
  extractedData,
  onAccept,
}) => {
  // Estado para almacenar los datos editables
  const [editedData, setEditedData] = useState(() => {
    console.log('Inicializando estado del modal con datos:', extractedData);
    return extractedData || {};
  });

  // Efecto para actualizar el estado cuando cambian los datos externos
  React.useEffect(() => {
    console.log('Datos del modal actualizados:', extractedData);
    if (extractedData) {
      setEditedData(extractedData);
    }
  }, [extractedData]);

  // Log cuando el modal se abre o cierra
  React.useEffect(() => {
    console.log('Estado de apertura del modal cambiado:', isOpen);

    // Forzar una actualización del DOM cuando se cambia isOpen
    if (isOpen) {
      // Asegurar que el modal es visible en el DOM
      document.body.classList.add('modal-open');

      // Mostrar notificación adicional para confirmación visual
      const notifyModalOpen = () => {
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 left-4 bg-green-500 text-white p-2 rounded-md shadow-lg z-[9999]';
        toast.textContent = 'Modal abierto - Por favor confirma los datos';
        document.body.appendChild(toast);

        setTimeout(() => {
          document.body.removeChild(toast);
        }, 3000);
      };

      notifyModalOpen();
    } else {
      document.body.classList.remove('modal-open');
    }
  }, [isOpen]);

  // Manejar cambios en los campos de texto
  const handleChange = (e: React.ChangeEvent<HTMLInputElement>, section: string, field: string) => {
    setEditedData({
      ...editedData,
      [section]: {
        ...editedData[section],
        [field]: e.target.value,
      },
    });
  };

  // Manejar cambios en elementos de arrays (como habilidades)
  const handleArrayItemChange = (e: React.ChangeEvent<HTMLInputElement>, section: string, index: number) => {
    const newArray = [...editedData[section]];
    if (typeof newArray[index] === 'object') {
      newArray[index] = {
        ...newArray[index],
        name: e.target.value,
      };
    } else {
      newArray[index] = e.target.value;
    }

    setEditedData({
      ...editedData,
      [section]: newArray,
    });
  };

  // Manejar cambios en objetos anidados (como experiencia o habilidades con más detalles)
  const handleNestedChange = (e: React.ChangeEvent<HTMLInputElement>, section: string, index: number, field: string) => {
    const newArray = [...editedData[section]];
    if (typeof newArray[index] === 'object') {
      newArray[index] = {
        ...newArray[index],
        [field]: e.target.value,
      };
    }

    setEditedData({
      ...editedData,
      [section]: newArray,
    });
  };

  // Función para descargar los datos en formato JSON
  const handleDownloadJSON = () => {
    const dataStr = JSON.stringify(editedData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);

    const a = document.createElement('a');
    a.href = url;
    a.download = `datos_cv_${editedData.personal_info?.name?.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();

    // Limpiar
    setTimeout(() => {
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }, 100);
  };

  // Manejar la aceptación de los datos
  const handleAccept = () => {
    console.log('Datos aceptados para guardar:', editedData);

    // Asignar categoría y departamento automáticamente basado en las habilidades y experiencia
    const processedData = assignCategoryAndDepartment(editedData);
    console.log('Datos procesados con categoría y departamento asignados:', processedData);

    // Mostrar toast de confirmación
    const showSuccessToast = () => {
      const toast = document.createElement('div');
      toast.className = 'fixed top-4 right-4 bg-green-500 text-white p-4 rounded-md shadow-lg z-50 transition-opacity duration-500';
      toast.textContent = '¡Datos confirmados! Asignando categoría y departamento...';
      document.body.appendChild(toast);

      setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => document.body.removeChild(toast), 500);
      }, 3000);
    };

    showSuccessToast();
    onAccept(processedData);
    onClose();
  };

  // Función para asignar categoría y departamento basado en las habilidades y experiencia
  const assignCategoryAndDepartment = (data: any) => {
    try {
      // Usamos las funciones importadas en la parte superior del archivo

      // Extraer habilidades para el análisis
      const hardSkills = data.hard_skills || [];
      const skillsList = hardSkills.map((skill: any) =>
        typeof skill === 'string' ? skill : (skill.name || '')
      );

      // Obtener el puesto más reciente para análisis basado en título
      const latestPosition = data.experience && data.experience.length > 0
        ? data.experience[0].position || ''
        : '';

      // Asignar departamento basado en habilidades
      let departmentAssignment = null;
      if (skillsList.length > 0) {
        // Usar el método de asignación por habilidades
        const departmentSuggestions = suggestDepartmentFromSkills(skillsList);
        if (departmentSuggestions.length > 0) {
          const topDepartment = departmentSuggestions[0];
          departmentAssignment = {
            departmentId: topDepartment.toLowerCase().replace(/\s+/g, '_'),
            departmentName: topDepartment,
            confidence: 80 // Alta confianza para el departamento más coincidente
          };
        }
      } else if (latestPosition) {
        // Si no hay habilidades, intentar asignar por título del trabajo
        departmentAssignment = assignDepartmentByJobTitle(latestPosition);
        departmentAssignment.confidence = 60; // Confianza media
      } else {
        // Asignación por defecto
        departmentAssignment = {
          departmentId: 'hr',
          departmentName: 'Human Resources',
          confidence: 0
        };
      }

      // Asignar categoría basada en el departamento
      const assignedCategory = getCategoryForDepartment(departmentAssignment.departmentId);

      // Añadir los campos necesarios al objeto de datos
      return {
        ...data,
        categoria: assignedCategory,
        departmentId: departmentAssignment.departmentId,
        departmentName: departmentAssignment.departmentName,
        matchConfidence: departmentAssignment.confidence
      };
    } catch (error) {
      console.error('Error al asignar categoría y departamento:', error);
      // En caso de error, devolvemos los datos originales sin cambios
      return data;
    }
  };

  // Determina el departamento basado en el título del trabajo
  function assignDepartmentByJobTitle(jobTitle: string): { departmentId: string, departmentName: string, confidence: number } {
    const titleLower = jobTitle.toLowerCase();

    // Mapeo de palabras clave a departamentos
    if (titleLower.includes('develop') || titleLower.includes('frontend') ||
      titleLower.includes('backend') || titleLower.includes('full stack') ||
      titleLower.includes('engineer') || titleLower.includes('software')) {
      return { departmentId: 'digital', departmentName: 'Digital & Technology', confidence: 70 };
    }

    if (titleLower.includes('design') || titleLower.includes('ux') ||
      titleLower.includes('ui') || titleLower.includes('art director') ||
      titleLower.includes('creative')) {
      return { departmentId: 'creative', departmentName: 'Creative', confidence: 70 };
    }

    if (titleLower.includes('market') || titleLower.includes('seo') ||
      titleLower.includes('sem') || titleLower.includes('social media') ||
      titleLower.includes('digital market') || titleLower.includes('content')) {
      return { departmentId: 'mediaPerformance', departmentName: 'Media & Performance', confidence: 70 };
    }

    if (titleLower.includes('account') || titleLower.includes('manager') ||
      titleLower.includes('director') || titleLower.includes('chief')) {
      return { departmentId: 'accounts', departmentName: 'Account Management', confidence: 70 };
    }

    if (titleLower.includes('pr') || titleLower.includes('public relation') ||
      titleLower.includes('communication') || titleLower.includes('press')) {
      return { departmentId: 'comms', departmentName: 'Communications & PR', confidence: 70 };
    }

    if (titleLower.includes('video') || titleLower.includes('production') ||
      titleLower.includes('photo') || titleLower.includes('event')) {
      return { departmentId: 'production', departmentName: 'Production', confidence: 70 };
    }

    if (titleLower.includes('strategist') || titleLower.includes('planning') ||
      titleLower.includes('strategy') || titleLower.includes('brand')) {
      return { departmentId: 'strategy', departmentName: 'Strategy & Planning', confidence: 70 };
    }

    // Si no hay coincidencia clara, devolvemos HR por defecto
    return { departmentId: 'hr', departmentName: 'Human Resources', confidence: 50 };
  }

  // Obtiene una categoría adecuada para un departamento
  function getCategoryForDepartment(departmentId: string): string {
    // Mapeo simple de departamentos a categorías funcionales
    const deptToCategory: Record<string, string> = {
      'digital': 'Digital & Technology',
      'creative': 'Creativity (Art & Design)',
      'mediaPerformance': 'Paid Media & Performance',
      'accounts': 'Management',
      'strategy': 'Management',
      'comms': 'Communication & PR',
      'production': 'Audiovisual & Production',
      'hr': 'Human Resources',
      'finance': 'Finance & Operations'
    };

    return deptToCategory[departmentId] || 'Human Resources';
  }

  if (!extractedData) {
    console.log('No hay datos para mostrar en el modal');
    return null;
  }

  console.log('Renderizando modal con isOpen:', isOpen);

  // Asegurarnos de que el modal esté correctamente enlazado con los datos
  console.log('Renderizando modal con los siguientes parámetros:', {
    isOpen,
    extractedDataExists: !!extractedData,
    dataKeys: extractedData ? Object.keys(extractedData) : []
  });

  return (
    <>
      <div className="fixed bottom-4 right-4 bg-blue-500 text-white p-2 rounded z-50">
        Estado del modal: {isOpen ? 'Abierto' : 'Cerrado'}
      </div>
      {/* Forzar el modal a estar visible cuando isOpen es true */}
      <Dialog
        open={isOpen}
        modal={true}
        onOpenChange={(open) => {
          console.log('Dialog onOpenChange:', open);
          if (!open) onClose();
        }}
        defaultOpen={isOpen}
      >
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto"
          onEscapeKeyDown={() => {
            console.log('Escape key pressed');
            onClose();
          }}
          onPointerDownOutside={() => {
            console.log('Click outside dialog');
            onClose();
          }}
        >
          <DialogHeader>
            <DialogTitle>Confirma la información extraída de tu CV</DialogTitle>
            <DialogDescription>
              Revisa los datos extraídos de tu CV. Puedes modificarlos si es necesario antes de continuar.
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-6 py-4">
            {/* Información personal */}
            <div className="space-y-4">
              <h3 className="text-lg font-semibold">Información personal</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Nombre completo</Label>
                  <Input
                    id="name"
                    value={editedData.personal_info?.name || ''}
                    onChange={(e) => handleChange(e, 'personal_info', 'name')}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="email">Correo electrónico</Label>
                  <Input
                    id="email"
                    value={editedData.personal_info?.email || ''}
                    onChange={(e) => handleChange(e, 'personal_info', 'email')}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="phone">Teléfono</Label>
                  <Input
                    id="phone"
                    value={editedData.personal_info?.phone || ''}
                    onChange={(e) => handleChange(e, 'personal_info', 'phone')}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="location">Ubicación</Label>
                  <Input
                    id="location"
                    value={editedData.personal_info?.location || ''}
                    onChange={(e) => handleChange(e, 'personal_info', 'location')}
                  />
                </div>
              </div>
            </div>

            {/* Habilidades técnicas */}
            {editedData.hard_skills && editedData.hard_skills.length > 0 && (
              <div className="space-y-4">
                <h3 className="text-lg font-semibold">Habilidades técnicas</h3>
                <div className="grid grid-cols-1 gap-2">
                  {editedData.hard_skills.map((skill: any, index: number) => (
                    <div key={index} className="flex items-center gap-2">
                      <Input
                        value={typeof skill === 'string' ? skill : (skill.name || '')}
                        onChange={(e) => handleArrayItemChange(e, 'hard_skills', index)}
                      />
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Habilidades blandas */}
            {editedData.soft_skills && editedData.soft_skills.length > 0 && (
              <div className="space-y-4">
                <h3 className="text-lg font-semibold">Habilidades blandas</h3>
                <div className="grid grid-cols-1 gap-2">
                  {editedData.soft_skills.map((skill: string, index: number) => (
                    <div key={index} className="flex items-center gap-2">
                      <Input
                        value={skill}
                        onChange={(e) => handleArrayItemChange(e, 'soft_skills', index)}
                      />
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Área de interés */}
            {editedData.area_of_interest && (
              <div className="space-y-2">
                <Label htmlFor="area_of_interest">Área de interés</Label>
                <Input
                  id="area_of_interest"
                  value={editedData.area_of_interest || ''}
                  onChange={(e) => setEditedData({ ...editedData, area_of_interest: e.target.value })}
                />
              </div>
            )}

            {/* Experiencia laboral */}
            {editedData.experience && editedData.experience.length > 0 && (
              <div className="space-y-4">
                <h3 className="text-lg font-semibold">Experiencia laboral</h3>
                <div className="space-y-4">
                  {editedData.experience.map((exp: any, index: number) => (
                    <div key={index} className="border p-4 rounded-md">
                      <h4 className="font-medium mb-2">Experiencia {index + 1}</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                          <Label>Puesto</Label>
                          <Input
                            value={exp.position || ''}
                            onChange={(e) => handleNestedChange(e, 'experience', index, 'position')}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>Empresa</Label>
                          <Input
                            value={exp.company || ''}
                            onChange={(e) => handleNestedChange(e, 'experience', index, 'company')}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>Fecha inicio</Label>
                          <Input
                            value={exp.start_date || ''}
                            onChange={(e) => handleNestedChange(e, 'experience', index, 'start_date')}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>Fecha fin</Label>
                          <Input
                            value={exp.end_date || ''}
                            onChange={(e) => handleNestedChange(e, 'experience', index, 'end_date')}
                          />
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Educación */}
            {editedData.education && editedData.education.length > 0 && (
              <div className="space-y-4">
                <h3 className="text-lg font-semibold">Educación</h3>
                <div className="space-y-4">
                  {editedData.education.map((edu: any, index: number) => (
                    <div key={index} className="border p-4 rounded-md">
                      <h4 className="font-medium mb-2">Formación {index + 1}</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                          <Label>Título</Label>
                          <Input
                            value={edu.degree || ''}
                            onChange={(e) => handleNestedChange(e, 'education', index, 'degree')}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>Institución</Label>
                          <Input
                            value={edu.institution || ''}
                            onChange={(e) => handleNestedChange(e, 'education', index, 'institution')}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>Fecha inicio</Label>
                          <Input
                            value={edu.start_date || ''}
                            onChange={(e) => handleNestedChange(e, 'education', index, 'start_date')}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>Fecha fin</Label>
                          <Input
                            value={edu.end_date || ''}
                            onChange={(e) => handleNestedChange(e, 'education', index, 'end_date')}
                          />
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          <DialogFooter className="flex justify-between items-center flex-wrap gap-2">
            <div>
              <Button
                variant="outline"
                onClick={handleDownloadJSON}
                className="mr-2"
              >
                Descargar como JSON
              </Button>
              <Button variant="outline" onClick={onClose}>Cancelar</Button>
            </div>
            <Button onClick={handleAccept} className="bg-green-600 hover:bg-green-700">Confirmar y guardar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
};

export default CVConfirmationModal;
