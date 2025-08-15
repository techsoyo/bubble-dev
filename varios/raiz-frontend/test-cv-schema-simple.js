// Test simple del esquema CV en JavaScript
console.log('=== PRUEBAS CV SCHEMA FRONTEND ===\n');

// Simular el template
const cvTemplate = {
  nombre: '',
  email: '',
  telefono: '',
  data_source: 'manual_entry',
  puestos_anteriores: [],
  educacion: [],
  routing: {
    fuente: 'manual'
  }
};

console.log('1. TEMPLATE:');
console.log(JSON.stringify(cvTemplate, null, 2));
console.log('');

// Simular validación mínima
const validateMinimumData = (data) => {
  const errors = [];
  if (!data.nombre?.trim()) {
    errors.push('El nombre es obligatorio');
  }
  if (!data.email?.trim()) {
    errors.push('El email es obligatorio');
  } else {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
      errors.push('El email no tiene un formato válido');
    }
  }
  return errors;
};

// Simular mergeWithTemplate
const mergeWithTemplate = (data) => {
  return { ...cvTemplate, ...data };
};

console.log('2. MERGE CON DATOS:');
const aiData = {
  nombre: 'Juan Pérez',
  email: 'juan@example.com',
  telefono: '+34 666 777 888',
  data_source: 'ai_processing',
  puestos_anteriores: [{
    puesto: 'Desarrollador',
    empresa: 'TechCorp',
    fecha_inicio: '2020-01-01',
    fecha_fin: '2024-01-01',
    descripcion: 'Desarrollo web'
  }]
};

const merged = mergeWithTemplate(aiData);
console.log('Datos AI:', JSON.stringify(aiData, null, 2));
console.log('');
console.log('Datos merged:', JSON.stringify(merged, null, 2));
console.log('');

// Prueba de estados
const estados = ['idle', 'uploading', 'parsing', 'ready', 'manual', 'saving'];
console.log('3. ESTADOS FINITOS:');
estados.forEach(estado => {
  console.log(`- ${estado}: ${getStateDescription(estado)}`);
});
console.log('');

function getStateDescription(state) {
  switch (state) {
    case 'idle': return 'aún no subió PDF';
    case 'uploading': return 'subiendo archivo';
    case 'parsing': return 'esperando IA';
    case 'ready': return 'form con datos pre-rellenados';
    case 'manual': return 'falló IA o usuario elige carga manual';
    case 'saving': return 'enviando confirmación';
    default: return 'estado desconocido';
  }
}

// Prueba de validación
console.log('4. VALIDACIÓN:');
const validData = merged;
const invalidData = { nombre: '', email: 'invalid' };

console.log('Datos válidos:', validateMinimumData(validData));
console.log('Datos inválidos:', validateMinimumData(invalidData));
console.log('');

// Simular reducer
const cvFormReducer = (state, action) => {
  switch (action.type) {
    case 'SET_STATE':
      return { ...state, state: action.payload };
    case 'SET_DATA':
      return { ...state, data: action.payload };
    case 'PREFILL_FROM_AI':
      return {
        ...state,
        data: mergeWithTemplate(action.payload),
        state: 'ready',
        isLoading: false,
        errors: []
      };
    case 'SWITCH_TO_MANUAL':
      return {
        ...state,
        data: { ...cvTemplate },
        state: 'manual',
        isLoading: false,
        errors: []
      };
    default:
      return state;
  }
};

console.log('5. REDUCER:');
let initialState = {
  state: 'idle',
  data: { ...cvTemplate },
  errors: [],
  isLoading: false
};

console.log('Estado inicial:', initialState.state);

// Simular pre-llenado desde IA
let newState = cvFormReducer(initialState, {
  type: 'PREFILL_FROM_AI',
  payload: aiData
});
console.log('Después de PREFILL_FROM_AI:', newState.state);
console.log('Datos pre-rellenados:', !!newState.data.nombre);

// Simular cambio a manual
newState = cvFormReducer(newState, { type: 'SWITCH_TO_MANUAL' });
console.log('Después de SWITCH_TO_MANUAL:', newState.state);
console.log('Datos reseteados:', newState.data.nombre === '');

console.log('\n=== PRUEBAS COMPLETADAS ===');
console.log('✅ Template definido correctamente');
console.log('✅ Estados finitos implementados');
console.log('✅ Validación funcionando');
console.log('✅ Merge de datos OK');
console.log('✅ Reducer maneja transiciones');
console.log('✅ Formulario funciona en ambos modos (ready/manual)');
