import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Label } from '../../components/ui/label';
import { Input } from '../../components/ui/input';
import { Button } from '../../components/ui/button';
import { CardForm, CardHeader, CardTitle, CardDescription, CardContent } from '../../components/ui/card';
import { Alert, AlertDescription } from '../../components/ui/alert';
import { toast } from '../../components/ui/use-toast';
import { useFormErrors } from '../../hooks/useFormErrors';
import { useAuth } from '../../contexts/AuthContext';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { saveCandidateFromAI } from '../../lib/apiService';

// Definir configuración de API internamente
const API_CONFIG = {
  BASE_URL: 'http://localhost:8000', // URL completa del backend
  ENDPOINTS: {
    login: '/auth/login',
    register: '/candidates/login',
    forgotPassword: '/auth/forgot-password',
    resetPassword: '/auth/reset-password',
    userProfile: '/user/profile',
    updateProfile: '/user/update-profile',
    PDF_PARSE: '/api/analyze_cv.php' // Endpoint correcto
  },
  timeout: 10000
};

export default function RegisterPage() {
  const { t } = useLanguage();
  const [searchParams] = useSearchParams();
  const jobId = searchParams.get('job'); // Obtener job ID si viene desde JobDetails

  const [form, setForm] = useState({
    email: '',
    username: '',
    password: '',
    confirmPassword: '',
    cv: null as File | null,
  });
  const [isProcessing, setIsProcessing] = useState(false);
  const [isRegistering, setIsRegistering] = useState(false);
  const [success, setSuccess] = useState(false);
  const [processedCVData, setProcessedCVData] = useState<Record<string, unknown> | null>(null);
  const { errors, setError, clearError, clearAllErrors, hasError } = useFormErrors();
  // Usamos solo el refreshSession del nuevo contexto de autenticación
  const { refreshSession } = useAuth();

  // Mostrar información del job si viene desde JobDetails
  useEffect(() => {
    if (jobId) {
      toast({
        title: 'Aplicando para un trabajo específico',
        description: `Te estás registrando para aplicar al trabajo ID: ${jobId}`,
        variant: 'default'
      });
    }
  }, [jobId]);

  const handleChange = (field: string, value: string) => {
    setForm(prev => ({ ...prev, [field]: value }));
    // Limpiar errores cuando el usuario comience a escribir
    if (hasError(field)) {
      clearError(field);
    }
  };

  const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      if (file.type !== 'application/pdf') {
        setError('cv', 'Solo se permiten archivos PDF');
        return;
      }
      if (file.size > 10 * 1024 * 1024) { // 10MB limit
        setError('cv', 'El archivo no puede superar los 10MB');
        return;
      }
      setForm((prev: typeof form) => ({ ...prev, cv: file }));
      clearError('cv');
    }
  };

  const validateForm = (): boolean => {
    clearAllErrors();
    let isValid = true;

    if (!form.email.trim()) {
      setError('email', 'El correo electrónico es obligatorio');
      isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      setError('email', 'Ingresa un correo electrónico válido');
      isValid = false;
    }

    if (!form.password.trim()) {
      setError('password', 'La contraseña es obligatoria');
      isValid = false;
    } else if (form.password.length < 8) {
      setError('password', 'La contraseña debe tener al menos 8 caracteres');
      isValid = false;
    }

    if (!form.confirmPassword.trim()) {
      setError('confirmPassword', 'Confirma tu contraseña');
      isValid = false;
    } else if (form.password !== form.confirmPassword) {
      setError('confirmPassword', 'Las contraseñas no coinciden');
      isValid = false;
    }

    if (!form.cv) {
      setError('cv', 'Debes subir tu CV en formato PDF');
      isValid = false;
    } else if (!processedCVData) {
      setError('cv', 'Debes procesar el CV antes de completar el registro');
      isValid = false;
    }

    return isValid;
  };

  const handleProcessCV = async () => {
    if (!form.cv) {
      setError('cv', 'Debes subir un archivo PDF antes de procesar.');
      return;
    }

    setIsProcessing(true);
    clearError('cv');

    try {
      const formData = new FormData();
      formData.append('cv_file', form.cv); // Cambiar por cv_file para coincidir con backend

      // Mostrar un mensaje de que se está procesando el CV
      toast({
        title: 'Procesando CV...',
        description: 'Estamos analizando tu CV, esto puede tardar unos momentos.',
        variant: 'default'
      });

      const response = await fetch(`${API_CONFIG.BASE_URL}${API_CONFIG.ENDPOINTS.PDF_PARSE}`, {
        method: 'POST',
        body: formData,
      });

      let data: { success?: boolean; message?: string; structured_data?: Record<string, unknown> } | null = null;
      try {
        data = await response.json();
      } catch (jsonErr) {
        setError('cv', 'Respuesta inesperada del servidor');
        return;
      }

      if (response.ok && data && data.success) {
        console.log('[Register] CV procesado exitosamente. Datos recibidos:', data);
        console.log('[Register] structured_data:', data.structured_data);

        // Guardar los datos procesados para usar en el registro
        setProcessedCVData(data.structured_data || {});

        toast({
          title: 'CV procesado correctamente',
          description: 'El archivo se ha subido y analizado con éxito. Ahora puedes proceder con el registro.',
          variant: 'default'
        });

        // Llenar automáticamente algunos campos del formulario si están disponibles
        if (data.structured_data) {
          const cvData = data.structured_data as any;
          if (cvData.email && !form.email) {
            setForm(prev => ({ ...prev, email: cvData.email }));
          }
          if (cvData.nombre && !form.username) {
            setForm(prev => ({ ...prev, username: cvData.nombre }));
          }
        }
      } else if (data && data.message) {
        setError('cv', data.message);
      } else {
        setError('cv', 'Error procesando el CV');
      }
    } catch (err) {
      setError('cv', 'Error de conexión con el servidor. Verífica que el backend esté ejecutándose.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) {
      return;
    }

    setIsRegistering(true);
    clearAllErrors();

    try {
      // Si tenemos datos del CV procesado, usarlos para crear el candidato
      if (processedCVData) {
        console.log('[Register] Guardando candidato con datos del CV:', processedCVData);

        // Enviar los datos del CV procesado directamente al endpoint save_v2.php
        const result = await saveCandidateFromAI(processedCVData);

        console.log('[Register] Respuesta del guardado:', result);

        if (result.success) {
          toast({
            title: 'Registro completado',
            description: `Tu cuenta ha sido creada exitosamente. ID: ${result.data?.candidate_id || 'N/A'}`,
            variant: 'default'
          });

          // Para compatibilidad temporal durante la migración
          localStorage.setItem('isLoggedIn', 'true');
          localStorage.setItem('userEmail', form.email);
          localStorage.setItem('userRole', 'candidate');
          localStorage.setItem('candidateId', result.data?.candidate_id || '');

          // Refrescar el estado de autenticación
          await refreshSession();
          setSuccess(true);
        } else {
          throw new Error(result.message || 'Error al guardar los datos del candidato');
        }
      } else {
        // Si no hay datos del CV, mostrar error
        setError('general', 'Debes procesar tu CV antes de completar el registro.');
        return;
      }
    } catch (error) {
      console.error('[Register] Error:', error);
      setError('general', error instanceof Error ? error.message : 'Error en el registro. Inténtalo nuevamente.');
    } finally {
      setIsRegistering(false);
    }
  };

  return (
    <div
      style={{
        position: 'fixed',
        top: 0,
        left: 0,
        width: '100vw',
        height: '100vh',
        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000
      }}
    >
      <div style={{ width: '100%', maxWidth: '28rem', padding: '1rem' }}>
        <CardForm style={{ background: 'white', borderRadius: '12px', boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)' }}>
          <CardHeader className="text-center border-b pb-6 bg-gradient-to-r from-[#FF4785] to-[#FF3575] text-white">
            <CardTitle className="text-2xl font-bold">Registro de Candidato</CardTitle>
            <CardDescription className="text-white/90">
              Completa los campos y sube tu CV para unirte a nuestra comunidad
            </CardDescription>
          </CardHeader>
          <CardContent className="pt-6 pb-4 px-6">
            {hasError('general') && (
              <Alert variant="destructive" className="mb-6">
                <AlertDescription>{errors.general}</AlertDescription>
              </Alert>
            )}
            {success ? (
              <div className="text-center py-8">
                <div className="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-6">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <h3 className="text-2xl font-semibold mb-2 text-[#FF4785]">¡Registro completado!</h3>
                <p className="text-gray-600 mb-6">Tu perfil ha sido registrado exitosamente. Pronto recibirás un correo de confirmación.</p>
                <Button
                  onClick={() => window.location.href = '/auth/login'}
                  className="bg-[#FF4785] hover:bg-[#FF3575]"
                >
                  Ir al Login
                </Button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-6" noValidate>
                <div>
                  <Label htmlFor="auth-register-email" className="text-gray-700">Correo electrónico *</Label>
                  <Input
                    id="auth-register-email"
                    name="email"
                    type="email"
                    value={form.email}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleChange('email', e.target.value)}
                    placeholder="tu@email.com"
                    required
                    aria-required="true"
                    aria-invalid={hasError('email')}
                    aria-describedby={hasError('email') ? 'email-error' : undefined}
                    autoComplete="email"
                    className="mt-1 text-black"
                  />
                  {hasError('email') && (
                    <p id="email-error" className="text-sm text-red-600 mt-1" role="alert">
                      {errors.email}
                    </p>
                  )}
                </div>
                <div>
                  <Label htmlFor="username" className="text-gray-700">Nombre de usuario</Label>
                  <p className="text-xs text-gray-500 mb-1">Opcional - se usará para mostrar tu perfil público</p>
                  <Input
                    id="username"
                    name="username"
                    value={form.username}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleChange('username', e.target.value)}
                    placeholder="Nombre de usuario"
                    autoComplete="username"
                    className="mt-1 text-black"
                  />
                </div>
                <div>
                  <Label htmlFor="auth-register-password" className="text-gray-700">Contraseña *</Label>
                  <Input
                    id="auth-register-password"
                    name="password"
                    type="password"
                    value={form.password}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleChange('password', e.target.value)}
                    required
                    aria-required="true"
                    aria-invalid={hasError('password')}
                    aria-describedby={hasError('password') ? 'password-error password-help' : 'password-help'}
                    placeholder="Mínimo 8 caracteres"
                    autoComplete="new-password"
                    className="mt-1 text-black"
                  />
                  <p id="password-help" className="text-xs text-gray-500 mt-1">
                    Debe tener al menos 8 caracteres
                  </p>
                  {hasError('password') && (
                    <p id="password-error" className="text-sm text-red-600 mt-1" role="alert">
                      {errors.password}
                    </p>
                  )}
                </div>
                <div>
                  <Label htmlFor="confirmPassword" className="text-gray-700">Confirmar contraseña *</Label>
                  <Input
                    id="confirmPassword"
                    name="confirmPassword"
                    type="password"
                    value={form.confirmPassword}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleChange('confirmPassword', e.target.value)}
                    required
                    aria-required="true"
                    aria-invalid={hasError('confirmPassword')}
                    aria-describedby={hasError('confirmPassword') ? 'confirm-password-error' : undefined}
                    placeholder="Repite tu contraseña"
                    autoComplete="new-password"
                    className="mt-1 text-black"
                  />
                  {hasError('confirmPassword') && (
                    <p id="confirm-password-error" className="text-sm text-red-600 mt-1" role="alert">
                      {errors.confirmPassword}
                    </p>
                  )}
                </div>
                <div>
                  <Label htmlFor="cv" className="text-gray-700">Curriculum Vitae (PDF) *</Label>
                  <Input
                    id="cv"
                    name="cv"
                    type="file"
                    accept="application/pdf"
                    onChange={handleFile}
                    required
                    aria-required="true"
                    aria-invalid={hasError('cv')}
                    aria-describedby={hasError('cv') ? 'cv-error cv-help' : 'cv-help'}
                    className="mt-1 text-black"
                  />
                  <p id="cv-help" className="text-xs text-gray-500 mt-1">
                    {t('dashboard.onlyPdfFilesRegister')}
                  </p>
                  {hasError('cv') && (
                    <p id="cv-error" className="text-sm text-red-600 mt-1" role="alert">
                      {errors.cv}
                    </p>
                  )}
                </div>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    onClick={handleProcessCV}
                    disabled={isProcessing || !form.cv}
                    className="bg-blue-500 hover:bg-blue-600 text-white"
                    aria-describedby={isProcessing ? 'processing-message' : undefined}
                  >
                    {isProcessing ? (
                      <>
                        <span className="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                        Procesando CV...
                      </>
                    ) : (
                      'Procesar CV'
                    )}
                  </Button>
                  <Button
                    type="submit"
                    disabled={isRegistering}
                    className="bg-[#FF4785] hover:bg-[#FF3575] text-white flex-1"
                    aria-describedby={isRegistering ? 'registering-message' : undefined}
                  >
                    {isRegistering ? (
                      <>
                        <span className="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                        Registrando...
                      </>
                    ) : (
                      'Registrar'
                    )}
                  </Button>
                </div>
                {isProcessing && (
                  <p id="processing-message" className="sr-only" aria-live="polite">
                    Procesando archivo CV, por favor espera
                  </p>
                )}
                {isRegistering && (
                  <p id="registering-message" className="sr-only" aria-live="polite">
                    Creando tu cuenta, por favor espera
                  </p>
                )}
              </form>
            )}
          </CardContent>
        </CardForm>
      </div>
    </div>
  );
}
