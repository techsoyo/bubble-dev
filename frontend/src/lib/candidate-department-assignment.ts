// src/lib/candidate-department-assignment.ts
// Implementación para obtener la asignación de departamento de un candidato

/**
 * Obtiene la asignación de departamento para un candidato usando los datos ya disponibles.
 * @param candidateId string
 * @param candidates Array de candidatos (opcional, si no se proporciona hará una llamada API)
 * @returns Promise<{ departmentId: string, departmentName: string } | null>
 */
export async function getCandidateDepartmentAssignment(
  candidateId: string,
  candidates?: any[]
): Promise<{ departmentId: string, departmentName: string } | null> {
  try {
    // Si se proporcionan candidatos, buscar en el array
    if (candidates && candidates.length > 0) {
      const candidate = candidates.find(c => c.id === candidateId);

      if (candidate && candidate.department_id && candidate.department_name) {
        const result = {
          departmentId: candidate.department_id.toString(),
          departmentName: candidate.department_name
        };
        return result;
      }
    }    // Si no se encuentran en el array, hacer llamada API para obtener los datos
    const response = await fetch(`/api/candidates`);
    if (response.ok) {
      const data = await response.json();
      if (data.success && data.data) {
        const candidate = data.data.find((c: any) => c.id === candidateId);
        if (candidate && candidate.department_id && candidate.department_name) {
          return {
            departmentId: candidate.department_id.toString(),
            departmentName: candidate.department_name
          };
        }
      }
    }

    return null;
  } catch (error) {
    console.error('Error obteniendo información de departamento del candidato:', error);
    return null;
  }
}
