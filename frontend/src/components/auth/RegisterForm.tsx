// src/components/auth/RegisterFormComplete.tsx
import React, { useState } from 'react';
import SocialLoginButtons from './SocialLoginButtons';
import { registerCandidate } from '../../lib/apiService';

// Definir categorías y funciones internamente
const JOB_CATEGORIES = [
  "Desarrollo de Software",
  "Diseño UX/UI",
  "Marketing Digital",
  "Ventas",
  "Soporte Técnico",
  "Recursos Humanos",
  "Finanzas",
  "Administración",
  "Operaciones",
  "Producto"
];

const FUNC_CATEGORIES = [
  {
    categoria: "Desarrollo",
    subcategorias: ["frontend", "backend", "fullstack", "desarrollo web", "web development", "programación", "desarrollador"]
  },
  {
    categoria: "Diseño",
    subcategorias: ["diseño gráfico", "ux", "ui", "diseño web", "illustrator", "photoshop", "figma"]
  },
  {
    categoria: "Marketing",
    subcategorias: ["seo", "sem", "marketing digital", "redes sociales", "growth", "campañas"]
  },
  {
    categoria: "Finanzas",
    subcategorias: ["contabilidad", "finanzas", "contable", "financiero", "presupuesto", "tesorería"]
  },
  {
    categoria: "Recursos Humanos",
    subcategorias: ["rrhh", "recursos humanos", "selección", "reclutamiento", "gestión", "talento"]
  }
];

// Definir interfaces para los datos de autenticación
interface AuthSuccessData {
  id?: string;
  email?: string;
  name?: string;
  token?: string;
  role?: string;
  [key: string]: unknown;
}

interface APIResponse {
  success: boolean;
  data?: AuthSuccessData;
  error?: string;
  message?: string;
}

interface RegisterFormCompleteProps {
  onRegisterSuccess?: (data: AuthSuccessData) => void;
  onSocialLoginSuccess?: (data: AuthSuccessData) => void;
  className?: string;
}

// Componente de formulario multi-paso optimizado para móviles
const RegisterFormComplete: React.FC<RegisterFormCompleteProps> = ({
  onRegisterSuccess,
  onSocialLoginSuccess,
  className = '',
}) => {
  const [step, setStep] = useState(1);
  const [formData, setFormData] = useState({
    // Paso 1: Información básica
    name: '',
    email: '',
    password: '',
    confirmPassword: '',
    consentimiento_gdpr: false,
    phone: '',

    // Paso 2: Información personal
    country: '',
    city: '',
    gender: '',
    areaOfInterest: '',
    jobSearchType: 'both', // 'remote', 'onsite', 'both'

    // Paso 3: Educación y experiencia
    educationLevel: '',
    lastPosition: '',
    lastCompany: '',
    yearsOfExperience: '',

    // Paso 4: Habilidades e idiomas
    skills: [] as string[],
    softSkills: [] as string[],
    languages: [] as string[],

    // Paso 5: Perfil profesional
    linkedin: '',
    website: '',
    github: '',
    motivation: '',

    // Datos adicionales
    residencePermit: 'Sí',
    availabilityDate: '',
    workModality: 'Remoto',
    desiredSchedule: 'Jornada completa',
    desiredSalaryMin: '',
    desiredSalaryMax: '',
    desiredSalaryCurrency: 'EUR',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isLoading, setIsLoading] = useState(false);

  // Opciones para los select
  const educationLevels = ['Primary', 'Secondary', 'Undergraduate', "Master's", 'Doctorate', 'Other'];
  const experienceOptions = ['0-1', '2-3', '4-5', '6-10', '10+'];
  const genderOptions = ['Male', 'Female', 'Non-binary', 'Prefer not to say'];
  const jobSearchTypeOptions = [
    { value: 'remote', label: 'Solo Remoto' },
    { value: 'onsite', label: 'Solo Presencial' },
    { value: 'both', label: 'Ambos' },
  ];
  const workModalityOptions = ['Remoto', 'Presencial', 'Híbrido'];
  const scheduleOptions = ['Jornada completa', 'Media jornada', 'Freelance', 'Por horas'];
  const languageOptions = ['Spanish', 'English', 'French', 'German', 'Portuguese', 'Italian', 'Other'];
  const softSkillOptions = [
    'Communication', 'Teamwork', 'Problem-solving', 'Adaptability',
    'Time management', 'Leadership', 'Creativity', 'Critical thinking',
    'Emotional intelligence', 'Work ethic', 'Attention to detail'
  ];

  // Función para obtener categorías funcionales planas para el select
  const getFlattenedCategories = () => {
    const flattened: string[] = [];
    FUNC_CATEGORIES.forEach(cat => {
      cat.subcategorias.forEach(subcat => {
        flattened.push(subcat);
      });
    });
    return flattened;
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;

    setFormData(prev => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }));

    // Limpiar error del campo cuando el usuario lo modifica
    if (errors[name]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[name];
        return newErrors;
      });
    }
  };

  // Manejo de campos multiselect (skills, languages, etc)
  const handleMultiSelectChange = (field: 'skills' | 'softSkills' | 'languages', value: string, checked: boolean) => {
    setFormData(prev => {
      const currentValues = [...prev[field]];

      if (checked && !currentValues.includes(value)) {
        return { ...prev, [field]: [...currentValues, value] };
      } else if (!checked && currentValues.includes(value)) {
        return { ...prev, [field]: currentValues.filter(v => v !== value) };
      }

      return prev;
    });
  };

  const validateCurrentStep = () => {
    const newErrors: Record<string, string> = {};

    switch (step) {
      case 1: // Información básica
        if (!formData.name.trim()) {
          newErrors.name = 'El nombre es obligatorio';
        }

        if (!formData.email.trim()) {
          newErrors.email = 'El email es obligatorio';
        } else if (!/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i.test(formData.email)) {
          newErrors.email = 'Email inválido';
        }

        if (!formData.password) {
          newErrors.password = 'La contraseña es obligatoria';
        } else if (formData.password.length < 6) {
          newErrors.password = 'La contraseña debe tener al menos 6 caracteres';
        }

        if (formData.password !== formData.confirmPassword) {
          newErrors.confirmPassword = 'Las contraseñas no coinciden';
        }

        if (!formData.consentimiento_gdpr) {
          newErrors.consentimiento_gdpr = 'Debes aceptar los términos y condiciones';
        }

        // Teléfono es opcional pero si se proporciona verificamos formato
        if (formData.phone && !/^\+?[0-9\s]{8,15}$/.test(formData.phone)) {
          newErrors.phone = 'Formato de teléfono inválido';
        }
        break;

      case 2: // Información personal
        if (!formData.country.trim()) {
          newErrors.country = 'El país es obligatorio';
        }

        if (!formData.city.trim()) {
          newErrors.city = 'La ciudad es obligatoria';
        }

        if (!formData.areaOfInterest.trim()) {
          newErrors.areaOfInterest = 'El área de interés es obligatoria';
        }
        break;

      case 3: // Educación y experiencia
        if (!formData.educationLevel) {
          newErrors.educationLevel = 'El nivel de educación es obligatorio';
        }

        if (!formData.yearsOfExperience) {
          newErrors.yearsOfExperience = 'Los años de experiencia son obligatorios';
        }
        break;

      case 4: // Habilidades e idiomas
        if ((formData.skills as string[]).length === 0) {
          newErrors.skills = 'Selecciona al menos una habilidad';
        }

        if ((formData.languages as string[]).length === 0) {
          newErrors.languages = 'Selecciona al menos un idioma';
        }
        break;

      case 5: // Perfil profesional
        // LinkedIn, website y GitHub son opcionales
        if (!formData.motivation.trim()) {
          newErrors.motivation = 'La motivación es obligatoria';
        }
        break;
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleNext = () => {
    if (validateCurrentStep()) {
      setStep(prevStep => prevStep + 1);
      window.scrollTo(0, 0);
    }
  };

  const handlePrev = () => {
    setStep(prevStep => prevStep - 1);
    window.scrollTo(0, 0);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateCurrentStep()) {
      return;
    }

    setIsLoading(true);

    try {
      // Preparar los datos para enviar al backend
      const candidateData = {
        name: formData.name,
        email: formData.email,
        password: formData.password,
        consentimiento_gdpr: formData.consentimiento_gdpr,

        // Campos adicionales
        phone: formData.phone,
        country: formData.country,
        city: formData.city,
        gender: formData.gender,
        areaOfInterest: formData.areaOfInterest,
        jobSearchType: formData.jobSearchType,
        educationLevel: formData.educationLevel,
        lastPosition: formData.lastPosition,
        lastCompany: formData.lastCompany,
        yearsOfExperience: formData.yearsOfExperience,
        skills: formData.skills,
        softSkills: formData.softSkills,
        languages: formData.languages,
        linkedin: formData.linkedin,
        website: formData.website,
        github: formData.github,
        motivation: formData.motivation,
        residencePermit: formData.residencePermit,
        availabilityDate: formData.availabilityDate,
        workModality: formData.workModality,
        desiredSchedule: formData.desiredSchedule,
        desiredSalary: {
          min: formData.desiredSalaryMin ? Number(formData.desiredSalaryMin) : 0,
          max: formData.desiredSalaryMax ? Number(formData.desiredSalaryMax) : 0,
          currency: formData.desiredSalaryCurrency
        }
      };

      // En el MVP, esto simularía una llamada API
      const response = await registerCandidate(candidateData) as APIResponse;

      // Aseguramos el tipo de response
      if (typeof response === 'object' && response !== null && 'success' in response) {
        if (response.success) {
          onRegisterSuccess && onRegisterSuccess(response.data || {});
        } else {
          setErrors({ form: response.error || 'Error al registrar usuario' });
        }
      } else {
        setErrors({ form: 'Respuesta inesperada del servidor' });
      }
    } catch (error) {
      console.error('Error de registro:', error);
      setErrors({ form: 'Error en el servidor, intenta más tarde' });
    } finally {
      setIsLoading(false);
    }
  };

  const renderStepIndicator = () => {
    return (
      <div className="flex items-center justify-between mb-8">
        {[1, 2, 3, 4, 5].map(stepNumber => (
          <div
            key={stepNumber}
            className={`w-8 h-8 rounded-full flex items-center justify-center ${stepNumber === step
              ? 'bg-[#F24495] text-white'
              : stepNumber < step
                ? 'bg-green-100 text-green-800'
                : 'bg-gray-200 text-gray-500'
              }`}
          >
            {stepNumber < step ? (
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            ) : (
              stepNumber
            )}
          </div>
        ))}
      </div>
    );
  };

  const renderStep1 = () => {
    return (
      <>
        <h3 className="text-lg font-medium mb-4">Información básica</h3>

        <div className="space-y-4">
          <div>
            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
              Nombre completo*
            </label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.name ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="Tu nombre completo"
            />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>

          <div>
            <label htmlFor="component-register-email" className="block text-sm font-medium text-gray-700 mb-1">
              Email*
            </label>
            <input
              type="email"
              id="component-register-email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.email ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="tu@email.com"
            />
            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
          </div>

          <div>
            <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
              Teléfono
            </label>
            <input
              type="tel"
              id="phone"
              name="phone"
              value={formData.phone}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.phone ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="+34 600123456"
            />
            {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
          </div>

          <div>
            <label htmlFor="component-register-password" className="block text-sm font-medium text-gray-700 mb-1">
              Contraseña*
            </label>
            <input
              type="password"
              id="component-register-password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.password ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="Mínimo 6 caracteres"
            />
            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
          </div>

          <div>
            <label htmlFor="confirmPassword" className="block text-sm font-medium text-gray-700 mb-1">
              Confirmar contraseña*
            </label>
            <input
              type="password"
              id="confirmPassword"
              name="confirmPassword"
              value={formData.confirmPassword}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.confirmPassword ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="Repite tu contraseña"
            />
            {errors.confirmPassword && (
              <p className="mt-1 text-xs text-red-600">{errors.confirmPassword}</p>
            )}
          </div>

          <div className="flex items-start pt-2">
            <div className="flex items-center h-5">
              <input
                id="consentimiento_gdpr"
                name="consentimiento_gdpr"
                type="checkbox"
                checked={formData.consentimiento_gdpr}
                onChange={handleChange}
                className="h-4 w-4 text-[#F24495] focus:ring-[#F24495] border-gray-300 rounded"
              />
            </div>
            <div className="ml-3 text-sm">
              <label htmlFor="consentimiento_gdpr" className="font-medium text-gray-700">
                Acepto los términos y condiciones y la política de privacidad*
              </label>
              {errors.consentimiento_gdpr && (
                <p className="mt-1 text-xs text-red-600">{errors.consentimiento_gdpr}</p>
              )}
            </div>
          </div>
        </div>
      </>
    );
  };

  const renderStep2 = () => {
    return (
      <>
        <h3 className="text-lg font-medium mb-4">Información personal</h3>

        <div className="space-y-4">
          <div>
            <label htmlFor="country" className="block text-sm font-medium text-gray-700 mb-1">
              País*
            </label>
            <input
              type="text"
              id="country"
              name="country"
              value={formData.country}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.country ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="España"
            />
            {errors.country && <p className="mt-1 text-xs text-red-600">{errors.country}</p>}
          </div>

          <div>
            <label htmlFor="city" className="block text-sm font-medium text-gray-700 mb-1">
              Ciudad*
            </label>
            <input
              type="text"
              id="city"
              name="city"
              value={formData.city}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.city ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="Madrid"
            />
            {errors.city && <p className="mt-1 text-xs text-red-600">{errors.city}</p>}
          </div>

          <div>
            <label htmlFor="gender" className="block text-sm font-medium text-gray-700 mb-1">
              Género
            </label>
            <select
              id="gender"
              name="gender"
              value={formData.gender}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              <option value="">Selecciona una opción</option>
              {genderOptions.map(option => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="jobSearchType" className="block text-sm font-medium text-gray-700 mb-1">
              Tipo de búsqueda de empleo*
            </label>
            <select
              id="jobSearchType"
              name="jobSearchType"
              value={formData.jobSearchType}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              {jobSearchTypeOptions.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="areaOfInterest" className="block text-sm font-medium text-gray-700 mb-1">
              Área de interés*
            </label>
            <select
              id="areaOfInterest"
              name="areaOfInterest"
              value={formData.areaOfInterest}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.areaOfInterest ? 'border-red-500' : 'border-gray-300'
                }`}
            >
              <option value="">Selecciona una opción</option>
              {getFlattenedCategories().map(category => (
                <option key={category} value={category}>
                  {category}
                </option>
              ))}
            </select>
            {errors.areaOfInterest && <p className="mt-1 text-xs text-red-600">{errors.areaOfInterest}</p>}
          </div>
        </div>
      </>
    );
  };

  const renderStep3 = () => {
    return (
      <>
        <h3 className="text-lg font-medium mb-4">Educación y experiencia</h3>

        <div className="space-y-4">
          <div>
            <label htmlFor="educationLevel" className="block text-sm font-medium text-gray-700 mb-1">
              Nivel de educación*
            </label>
            <select
              id="educationLevel"
              name="educationLevel"
              value={formData.educationLevel}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.educationLevel ? 'border-red-500' : 'border-gray-300'
                }`}
            >
              <option value="">Selecciona una opción</option>
              {educationLevels.map(level => (
                <option key={level} value={level}>
                  {level}
                </option>
              ))}
            </select>
            {errors.educationLevel && <p className="mt-1 text-xs text-red-600">{errors.educationLevel}</p>}
          </div>

          <div>
            <label htmlFor="lastPosition" className="block text-sm font-medium text-gray-700 mb-1">
              Último puesto
            </label>
            <input
              type="text"
              id="lastPosition"
              name="lastPosition"
              value={formData.lastPosition}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Desarrollador Frontend"
            />
          </div>

          <div>
            <label htmlFor="lastCompany" className="block text-sm font-medium text-gray-700 mb-1">
              Última empresa
            </label>
            <input
              type="text"
              id="lastCompany"
              name="lastCompany"
              value={formData.lastCompany}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Tech Solutions S.L."
            />
          </div>

          <div>
            <label htmlFor="yearsOfExperience" className="block text-sm font-medium text-gray-700 mb-1">
              Años de experiencia*
            </label>
            <select
              id="yearsOfExperience"
              name="yearsOfExperience"
              value={formData.yearsOfExperience}
              onChange={handleChange}
              className={`w-full px-3 py-2 border rounded-md ${errors.yearsOfExperience ? 'border-red-500' : 'border-gray-300'
                }`}
            >
              <option value="">Selecciona una opción</option>
              {experienceOptions.map(option => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
            {errors.yearsOfExperience && <p className="mt-1 text-xs text-red-600">{errors.yearsOfExperience}</p>}
          </div>

          <div>
            <label htmlFor="availabilityDate" className="block text-sm font-medium text-gray-700 mb-1">
              Fecha de disponibilidad
            </label>
            <input
              type="date"
              id="availabilityDate"
              name="availabilityDate"
              value={formData.availabilityDate}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            />
          </div>
        </div>
      </>
    );
  };

  const renderStep4 = () => {
    return (
      <>
        <h3 className="text-lg font-medium mb-4">Habilidades e idiomas</h3>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Habilidades técnicas*
            </label>
            <div className="grid grid-cols-2 gap-2">
              {JOB_CATEGORIES.map(category => (
                <div key={category} className="flex items-center">
                  <input
                    type="checkbox"
                    id={`skill_${category}`}
                    checked={(formData.skills as string[]).includes(category)}
                    onChange={(e) => handleMultiSelectChange('skills', category, e.target.checked)}
                    className="h-4 w-4 text-[#F24495] focus:ring-[#F24495] border-gray-300 rounded"
                  />
                  <label htmlFor={`skill_${category}`} className="ml-2 text-sm text-gray-700">
                    {category}
                  </label>
                </div>
              ))}
            </div>
            {errors.skills && <p className="mt-1 text-xs text-red-600">{errors.skills}</p>}

            <div className="mt-2">
              <input
                type="text"
                placeholder="Otras habilidades (separadas por comas)"
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
                onBlur={(e) => {
                  if (e.target.value) {
                    const newSkills = e.target.value.split(',').map(s => s.trim()).filter(Boolean);
                    setFormData(prev => ({
                      ...prev,
                      skills: [...prev.skills, ...newSkills]
                    }));
                    e.target.value = '';
                  }
                }}
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Habilidades blandas
            </label>
            <div className="grid grid-cols-2 gap-2">
              {softSkillOptions.map(skill => (
                <div key={skill} className="flex items-center">
                  <input
                    type="checkbox"
                    id={`softSkill_${skill}`}
                    checked={(formData.softSkills as string[]).includes(skill)}
                    onChange={(e) => handleMultiSelectChange('softSkills', skill, e.target.checked)}
                    className="h-4 w-4 text-[#F24495] focus:ring-[#F24495] border-gray-300 rounded"
                  />
                  <label htmlFor={`softSkill_${skill}`} className="ml-2 text-sm text-gray-700">
                    {skill}
                  </label>
                </div>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Idiomas*
            </label>
            <div className="grid grid-cols-2 gap-2">
              {languageOptions.map(language => (
                <div key={language} className="flex items-center">
                  <input
                    type="checkbox"
                    id={`language_${language}`}
                    checked={(formData.languages as string[]).includes(language)}
                    onChange={(e) => handleMultiSelectChange('languages', language, e.target.checked)}
                    className="h-4 w-4 text-[#F24495] focus:ring-[#F24495] border-gray-300 rounded"
                  />
                  <label htmlFor={`language_${language}`} className="ml-2 text-sm text-gray-700">
                    {language}
                  </label>
                </div>
              ))}
            </div>
            {errors.languages && <p className="mt-1 text-xs text-red-600">{errors.languages}</p>}
          </div>

          <div>
            <label htmlFor="workModality" className="block text-sm font-medium text-gray-700 mb-1">
              Modalidad de trabajo
            </label>
            <select
              id="workModality"
              name="workModality"
              value={formData.workModality}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              {workModalityOptions.map(option => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="desiredSchedule" className="block text-sm font-medium text-gray-700 mb-1">
              Horario deseado
            </label>
            <select
              id="desiredSchedule"
              name="desiredSchedule"
              value={formData.desiredSchedule}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
            >
              {scheduleOptions.map(option => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </div>
        </div>
      </>
    );
  };

  const renderStep5 = () => {
    return (
      <>
        <h3 className="text-lg font-medium mb-4">Perfil profesional</h3>

        <div className="space-y-4">
          <div>
            <label htmlFor="linkedin" className="block text-sm font-medium text-gray-700 mb-1">
              LinkedIn
            </label>
            <input
              type="url"
              id="linkedin"
              name="linkedin"
              value={formData.linkedin}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="https://linkedin.com/in/username"
            />
          </div>

          <div>
            <label htmlFor="website" className="block text-sm font-medium text-gray-700 mb-1">
              Sitio web / Portfolio
            </label>
            <input
              type="url"
              id="website"
              name="website"
              value={formData.website}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="https://tuportfolio.com"
            />
          </div>

          <div>
            <label htmlFor="github" className="block text-sm font-medium text-gray-700 mb-1">
              GitHub
            </label>
            <input
              type="url"
              id="github"
              name="github"
              value={formData.github}
              onChange={handleChange}
              className="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="https://github.com/username"
            />
          </div>

          <div>
            <label htmlFor="motivation" className="block text-sm font-medium text-gray-700 mb-1">
              Motivación / Sobre ti*
            </label>
            <textarea
              id="motivation"
              name="motivation"
              value={formData.motivation}
              onChange={handleChange}
              rows={4}
              className={`w-full px-3 py-2 border rounded-md ${errors.motivation ? 'border-red-500' : 'border-gray-300'
                }`}
              placeholder="Cuéntanos un poco sobre ti y por qué te interesa trabajar con nosotros..."
            ></textarea>
            {errors.motivation && <p className="mt-1 text-xs text-red-600">{errors.motivation}</p>}
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div className="col-span-1">
              <label htmlFor="desiredSalaryMin" className="block text-sm font-medium text-gray-700 mb-1">
                Salario mínimo
              </label>
              <input
                type="number"
                id="desiredSalaryMin"
                name="desiredSalaryMin"
                value={formData.desiredSalaryMin}
                onChange={handleChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="30000"
              />
            </div>

            <div className="col-span-1">
              <label htmlFor="desiredSalaryMax" className="block text-sm font-medium text-gray-700 mb-1">
                Salario máximo
              </label>
              <input
                type="number"
                id="desiredSalaryMax"
                name="desiredSalaryMax"
                value={formData.desiredSalaryMax}
                onChange={handleChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
                placeholder="45000"
              />
            </div>

            <div className="col-span-1">
              <label htmlFor="desiredSalaryCurrency" className="block text-sm font-medium text-gray-700 mb-1">
                Moneda
              </label>
              <select
                id="desiredSalaryCurrency"
                name="desiredSalaryCurrency"
                value={formData.desiredSalaryCurrency}
                onChange={handleChange}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              >
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
                <option value="GBP">GBP</option>
              </select>
            </div>
          </div>
        </div>
      </>
    );
  };

  const renderStepContent = () => {
    switch (step) {
      case 1:
        return renderStep1();
      case 2:
        return renderStep2();
      case 3:
        return renderStep3();
      case 4:
        return renderStep4();
      case 5:
        return renderStep5();
      default:
        return null;
    }
  };

  return (
    <div className={`w-full max-w-md mx-auto ${className}`}>
      <h2 className="text-2xl font-bold mb-6 text-center">Crea tu cuenta</h2>

      {step === 1 && (
        <SocialLoginButtons
          onLoginSuccess={onSocialLoginSuccess || ((data) => console.log('Social login success:', data))}
          onLoginError={(error) => setErrors({ form: error })}
          className="mb-6"
        />
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        {errors.form && (
          <div className="bg-red-50 p-3 rounded-md text-red-600 text-sm mb-4">
            {errors.form}
          </div>
        )}

        {renderStepIndicator()}

        {renderStepContent()}

        <div className="flex justify-between mt-8">
          {step > 1 && (
            <button
              type="button"
              onClick={handlePrev}
              className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
            >
              Atrás
            </button>
          )}

          {step < 5 ? (
            <button
              type="button"
              onClick={handleNext}
              className="ml-auto px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#F24495] hover:bg-[#E13385] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#F24495]"
            >
              Siguiente
            </button>
          ) : (
            <button
              type="submit"
              disabled={isLoading}
              className="ml-auto px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#F24495] hover:bg-[#E13385] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#F24495]"
            >
              {isLoading ? 'Registrando...' : 'Completar registro'}
            </button>
          )}
        </div>

        {step === 1 && (
          <div className="text-center mt-6">
            <p className="text-sm text-gray-600">
              ¿Ya tienes cuenta?{' '}
              <a href="/auth/login" className="text-[#F24495] hover:underline">
                Inicia sesión
              </a>
            </p>
          </div>
        )}
      </form>
    </div>
  );
};

export default RegisterFormComplete;
