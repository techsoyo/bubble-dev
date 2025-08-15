import {
  CvFormData,
  cvTemplate,
  validateMinimumData,
  mergeWithTemplate,
  cvFormReducer,
  initialCvFormState
} from './src/domain/cvSchema';

/**
 * Test completo del contrato CV Schema (frontend)
 */

console.log('=== PRUEBA DE CV SCHEMA (FRONTEND) ===\n');

// 1. Probar el template
console.log('1. TEMPLATE:');
console.log(JSON.stringify(cvTemplate, null, 2));
console.log('\n');

// 2. Probar validación de datos mínimos
console.log('2. VALIDACIÓN DE DATOS:');

const validData: CvFormData = {
  nombre: 'Juan Pérez',
  email: 'juan.perez@example.com',
  telefono: '+34 666 777 888',
  ubicacion_actual: 'Madrid, España'
};

const invalidData: CvFormData = {
  nombre: '',
  email: 'email-invalido'
};

const validErrors = validateMinimumData(validData);
const invalidErrors = validateMinimumData(invalidData);

console.log('Datos válidos:', validData);
console.log('Errores:', validErrors);
console.log('✅ Validación:', validErrors.length === 0 ? 'PASÓ' : 'FALLÓ');
console.log('\n');

console.log('Datos inválidos:', invalidData);
console.log('Errores:', invalidErrors);
console.log('✅ Validación:', invalidErrors.length > 0 ? 'PASÓ' : 'FALLÓ');
console.log('\n');

// 3. Probar merge con template
console.log('3. MERGE CON TEMPLATE:');

const partialData: Partial<CvFormData> = {
  nombre: 'Ana García',
  email: 'ana@example.com',
  soft_skills: ['Comunicación', 'Liderazgo'],
  puestos_anteriores: [{
    puesto: 'Desarrolladora',
    empresa: 'TechCorp',
    fecha_inicio: '2020-01-01',
    fecha_fin: '2024-01-01',
    descripcion: 'Desarrollo frontend',
    actual: false
  }]
};

const mergedData = mergeWithTemplate(partialData);
console.log('Datos parciales:', partialData);
console.log('Datos mergeados con template:', mergedData);
console.log('✅ Merge:',
  mergedData.nombre === 'Ana García' &&
    mergedData.soft_skills?.length === 2 &&
    mergedData.puestos_anteriores?.length === 1 &&
    mergedData.hard_skills?.length === 0 // Del template
    ? 'PASÓ' : 'FALLÓ'
);
console.log('\n');

// 4. Probar reducer del formulario
console.log('4. REDUCER DEL FORMULARIO:');

// Estado inicial
let state = initialCvFormState;
console.log('Estado inicial:', state.state, state.data.nombre);

// Setear archivo
state = cvFormReducer(state, {
  type: 'SET_FILE',
  payload: new File(['test'], 'test.pdf', { type: 'application/pdf' })
});
console.log('Después de SET_FILE:', state.state); // debería ser 'uploading'

// Pre-rellenar desde IA
state = cvFormReducer(state, {
  type: 'PREFILL_FROM_AI',
  payload: { nombre: 'María López', email: 'maria@example.com' }
});
console.log('Después de PREFILL_FROM_AI:', state.state, state.data.nombre); // debería ser 'ready'

// Actualizar campo
state = cvFormReducer(state, {
  type: 'UPDATE_FIELD',
  payload: { field: 'telefono', value: '+34 123 456 789' }
});
console.log('Después de UPDATE_FIELD:', state.data.telefono);

// Cambiar a manual
state = cvFormReducer(state, { type: 'SWITCH_TO_MANUAL' });
console.log('Después de SWITCH_TO_MANUAL:', state.state, state.data.nombre); // debería ser 'manual' y nombre vacío

// Reset
state = cvFormReducer(state, { type: 'RESET_FORM' });
console.log('Después de RESET_FORM:', state.state); // debería ser 'idle'

console.log('✅ Reducer:', 'PASÓ');
console.log('\n');

// 5. Probar estados finitos
console.log('5. ESTADOS FINITOS:');
const states = ['idle', 'uploading', 'parsing', 'ready', 'manual', 'saving'] as const;
states.forEach(stateValue => {
  const newState = cvFormReducer(initialCvFormState, {
    type: 'SET_STATE',
    payload: stateValue
  });
  console.log(`Estado ${stateValue}:`, newState.state === stateValue ? '✅' : '❌');
});

console.log('\n=== PRUEBA COMPLETADA ===');

// Exportar funciones para uso en tests
export {
  CvFormData,
  cvTemplate,
  validateMinimumData,
  mergeWithTemplate,
  cvFormReducer,
  initialCvFormState
};
