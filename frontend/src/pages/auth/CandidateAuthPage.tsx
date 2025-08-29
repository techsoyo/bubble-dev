import React, { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Label } from '../../components/ui/label';
import { Input } from '../../components/ui/input';
import { Button } from '../../components/ui/button';
import { Checkbox } from '../../components/ui/checkbox';
import { CardForm, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../../components/ui/card';
import { Alert, AlertDescription } from '../../components/ui/alert';
import { Separator } from '../../components/ui/separator';
import { toast } from '../../components/ui/use-toast';
import { useFormErrors } from '../../hooks/useFormErrors';
import { useAuth } from '../../contexts/AuthContext';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { X } from 'lucide-react';
import { CvFormProvider } from '../../contexts/CvFormContext';
import { CvFormData } from '../../domain/cvSchema';
import { CvFormWrapper } from '../../components/wrappers/CvFormWrapper';
import { formSubmissionLimiter, generateClientFingerprint } from '../../security/xss';

const API_CONFIG = {
  BASE_URL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000',
  ENDPOINTS: {
    login: import.meta.env.VITE_LOGIN_ENDPOINT || '/auth/register',
    register: import.meta.env.VITE_REGISTER_ENDPOINT || '/api/save-candidate.php',
    PDF_PARSE: import.meta.env.VITE_PDF_PARSE_ENDPOINT || '/api/analyze_cv.php'
  }
};

export default function CandidateAuthPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { t } = useLanguage();
  const jobId = searchParams.get('job');
  const [isLogin, setIsLogin] = useState(true); // Empezamos mostrando LOGIN
  const [isLoading, setIsLoading] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [isRegistering, setIsRegistering] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [success, setSuccess] = useState(false);
  const { errors, setError, clearError, clearAllErrors, hasError } = useFormErrors();
  const { candidateLogin, refreshSession } = useAuth();

  // Estados específicos para CV
  const [showValidationModal, setShowValidationModal] = useState(false);
  const [showManualForm, setShowManualForm] = useState(false);
  const [cvProcessingFailed, setCvProcessingFailed] = useState(false);

  // Estados GDPR
  const [gdprConsent, setGdprConsent] = useState(false);
  const [dataProcessingConsent, setDataProcessingConsent] = useState(false);

  // Estados de formularios
  const [loginForm, setLoginForm] = useState({
    email: '',
    password: ''
  });

  const [registerForm, setRegisterForm] = useState({
    email: '',
    username: '',
    password: '',
    confirmPassword: '',
    cv: null as File | null,
    cvData: null as any, // Datos extraídos por OpenAI
  });

  // Mostrar mensaje sobre empleo específico si hay jobId
  useEffect(() => {
    if (jobId) {
      toast({
        title: t('jobs.applyingForPosition'),
        description: t('auth.completeRegistrationToApply'),
        variant: 'default'
      });
    }
  }, [jobId, t]);

  const handleLoginSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    clearAllErrors();

    if (!loginForm.email.trim()) {
      setError('email', t('errors.emailRequired'));
      setIsLoading(false);
      return;
    }
    if (!loginForm.password.trim()) {
      setError('password', t('errors.passwordRequired'));
      setIsLoading(false);
      return;
    }

    // Rate limiting protection
    const fingerprint = generateClientFingerprint();
    if (!formSubmissionLimiter.isAllowed(fingerprint)) {
      setError('general', 'Demasiados intentos de inicio de sesión. Espera 1 minuto antes de intentarlo de nuevo.');
      setIsLoading(false);
      return;
    }

    try {
      const result = await candidateLogin(loginForm.email, loginForm.password);
      if (result) {
        if (jobId) {
          navigate(`/jobs/${jobId}/apply`);
        } else {
          navigate('/dashboard/cddashboard');
        }
      } else {
        setError('general', t('errors.invalidCredentials'));
      }
    } catch (error) {
      setError('general', t('errors.connectionErrorGeneral'));
    } finally {
      setIsLoading(false);
    }
  };

  const handleRegisterChange = (field: string, value: string) => {
    setRegisterForm(prev => ({ ...prev, [field]: value }));
    if (hasError(field)) {
      clearError(field);
    }
  };

  const handleFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.type !== 'application/pdf') {
        setError('cv', t('errors.onlyPdfAllowed'));
        return;
      }

      if (file.size > 5 * 1024 * 1024) { // 5MB
        setError('cv', t('errors.fileTooLarge'));
        return;
      }

      setRegisterForm(prev => ({ ...prev, cv: file }));
      clearError('cv');
    }
  };

  const validateRegisterForm = (): boolean => {
    clearAllErrors();
    let isValid = true;

    if (!registerForm.email.trim()) {
      setError('email', t('errors.emailRequiredRegister'));
      isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(registerForm.email)) {
      setError('email', t('errors.emailInvalid'));
      isValid = false;
    }

    if (!registerForm.password.trim()) {
      setError('password', t('errors.passwordRequiredRegister'));
      isValid = false;
    } else if (registerForm.password.length < 8) {
      setError('password', t('errors.passwordMinLength'));
      isValid = false;
    }

    if (!registerForm.confirmPassword.trim()) {
      setError('confirmPassword', t('errors.confirmPasswordRequired'));
      isValid = false;
    } else if (registerForm.password !== registerForm.confirmPassword) {
      setError('confirmPassword', t('errors.passwordsDoNotMatch'));
      isValid = false;
    }

    return isValid;
  };

  const handleProcessCV = async () => {
    if (!registerForm.cv) {
      setError('cv', t('errors.cvRequired'));
      return;
    }

    if (!gdprConsent || !dataProcessingConsent) {
      toast({
        title: 'GDPR',
        description: t('register.gdprTooltipProcessCv'),
        variant: 'destructive'
      });
      return;
    }

    setIsProcessing(true);
    clearError('cv');

    try {
      const formData = new FormData();
      formData.append('cv_file', registerForm.cv); // Campo correcto que espera el backend
      formData.append('user_email', registerForm.email);

      toast({
        title: t('success.processingCv'),
        description: t('success.analyzingCv'),
        variant: 'default'
      });

      const apiUrl = `${API_CONFIG.BASE_URL}${API_CONFIG.ENDPOINTS.PDF_PARSE}`;
      const response = await fetch(apiUrl, { method: 'POST', body: formData });
      const text = await response.text();
      let json: any = null;
      try { json = JSON.parse(text); } catch (e) {
        console.error('[CV_PARSE] Respuesta no JSON', text.slice(0, 400));
        setError('cv', t('errors.invalidServerResponse').replace('{status}', response.status.toString()));
        setCvProcessingFailed(true);
        setShowManualForm(true);
        return;
      }

      if (response.ok && json?.success && json?.data?.structured_data) {

        toast({
          title: t('success.cvProcessed'),
          description: t('success.cvDataReady'),
          variant: 'default'
        });
        setRegisterForm(prev => ({ ...prev, cvData: json.data.structured_data }));
        setShowValidationModal(true);
        setCvProcessingFailed(false);
      } else {
        const errMsg = json?.error?.message || json?.error?.code || 'Fallo en el parseo';
        setError('cv', t('errors.cvProcessingError').replace('{error}', errMsg));
        if (json?.error?.code === 'PARSE_FAILED') {
          console.warn('Hints:', json?.error?.hints);
        }
        setCvProcessingFailed(true);
        setShowManualForm(true);
        setShowValidationModal(true); // Mostrar modal con formulario vacío
      }
    } catch (err) {
      console.error('[CV_PARSE] Error fetch', err);
      setError('cv', t('errors.parsingServiceError'));
      setCvProcessingFailed(true);
      setShowManualForm(true);
      setShowValidationModal(true); // Mostrar modal con formulario vacío
    } finally {
      setIsProcessing(false);
    }
  };

  const handleRegisterSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateRegisterForm()) return;

    setIsRegistering(true);
    // Aquí iría la lógica de registro...
    setIsRegistering(false);
  };

  const handleSocialLogin = async (provider: 'google' | 'linkedin') => {
    try {
      // Construir URL de OAuth con job ID si existe
      const jobParam = jobId ? `&job=${jobId}` : '';
      const oauthUrl = `http://localhost:8000/auth/oauth/start.php?provider=${provider}${jobParam}`;

      // Mostrar mensaje de redirección
      toast({
        title: `Redirigiendo a ${provider === 'google' ? 'Google' : 'LinkedIn'}`,
        description: 'Te estamos redirigiendo para iniciar sesión...',
        variant: 'default'
      });

      // Redirigir al endpoint OAuth
      window.location.href = oauthUrl;

    } catch (error) {
      setError('general', t('errors.socialLoginError').replace('{provider}', provider));
    }
  };

  const toggleToLogin = () => {
    setIsLogin(true);
    setSuccess(false);
    clearAllErrors();
  };

  const toggleToRegister = () => {
    setIsLogin(false);
    setSuccess(false);
    clearAllErrors();
  };

  return (
    <div className="min-h-screen bg-transparent flex items-center justify-center p-4 pt-24">
      <div className="w-full max-w-md relative">

        {/* CARD DE LOGIN - Se muestra por defecto */}
        <div className={`transition-all duration-500 ease-out ${isLogin ? 'opacity-100 scale-100' : 'opacity-0 scale-95 pointer-events-none absolute inset-0'}`}>
          <CardForm className="shadow-lg" style={{ background: '#2f2f2f' }}>
            <CardHeader className="text-center border-b border-gray-600 pb-6">
              <CardTitle className="text-2xl text-white">{t('auth.login')}</CardTitle>
              <CardDescription className="text-gray-300">
                {t('auth.loginDescription')}
              </CardDescription>
            </CardHeader>

            <CardContent className="pt-6 pb-4">
              {hasError('general') && (
                <Alert variant="destructive" className="mb-6">
                  <AlertDescription>{errors.general}</AlertDescription>
                </Alert>
              )}

              <form onSubmit={handleLoginSubmit} className="space-y-6">
                <div>
                  <Label htmlFor="login-email" className="text-white">{t('auth.email')}</Label>
                  <Input
                    id="login-email"
                    type="email"
                    value={loginForm.email}
                    onChange={(e) => setLoginForm({ ...loginForm, email: e.target.value })}
                    placeholder="tu@email.com"
                    required
                    className="mt-1 text-black"
                  />
                  {hasError('email') && (
                    <p className="text-sm text-red-400 mt-1">{errors.email}</p>
                  )}
                </div>

                <div>
                  <Label htmlFor="login-password" className="text-white">{t('auth.password')}</Label>
                  <Input
                    id="login-password"
                    type="password"
                    value={loginForm.password}
                    onChange={(e) => setLoginForm({ ...loginForm, password: e.target.value })}
                    required
                    className="mt-1 text-black"
                  />
                  {hasError('password') && (
                    <p className="text-sm text-red-400 mt-1">{errors.password}</p>
                  )}
                </div>

                <Button
                  type="submit"
                  disabled={isLoading}
                  className="w-full bg-primary hover:bg-primary-hover text-white"
                >
                  {isLoading ? t('auth.loggingIn') : t('auth.loginButton')}
                </Button>
              </form>

              <div className="relative my-6">
                <div className="absolute inset-0 flex items-center">
                  <Separator className="w-full" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                  <span className="bg-[#2f2f2f] px-2 text-gray-400">{t('auth.orContinueWith')}</span>
                </div>
              </div>

              <div className="space-y-3">
                <Button
                  type="button"
                  variant="outline"
                  className="w-full bg-white hover:bg-gray-50 text-gray-900 border-gray-300"
                  onClick={() => handleSocialLogin('google')}
                  disabled={isLoading}
                >
                  <svg className="w-5 h-5 mr-3" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                  </svg>
                  Iniciar sesión con Google
                </Button>

                <Button
                  type="button"
                  variant="outline"
                  className="w-full bg-white hover:bg-gray-50 text-gray-900 border-gray-300"
                  onClick={() => handleSocialLogin('linkedin')}
                  disabled={isLoading}
                >
                  <svg className="w-5 h-5 mr-3" viewBox="0 0 24 24">
                    <path fill="#0077B5" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                  </svg>
                  Iniciar sesión con LinkedIn
                </Button>
              </div>
            </CardContent>

            <CardFooter className="text-center pt-4 border-t border-gray-600">
              <div className="w-full">
                <span className="text-sm text-gray-300">{t('auth.dontHaveAccount')} </span>
                <button
                  type="button"
                  onClick={toggleToRegister}
                  className="text-sm font-medium hover:underline"
                  style={{ color: 'var(--color-primary)' }}
                >
                  {t('auth.register')}
                </button>
              </div>
            </CardFooter>
          </CardForm>
        </div>

        {/* CARD DE REGISTRO - Se muestra cuando se hace click en "Ir al registro" */}
        <div className={`transition-all duration-500 ease-out ${!isLogin ? 'opacity-100 scale-100' : 'opacity-0 scale-95 pointer-events-none absolute inset-0'}`}>
          <CardForm className="shadow-lg" style={{ background: '#2f2f2f' }}>
            <CardHeader className="text-center border-b border-gray-600 pb-6">
              <CardTitle className="text-2xl text-white">{t('register.title')}</CardTitle>
              <CardDescription className="text-gray-300">
                {t('register.description')}
              </CardDescription>
            </CardHeader>

            <CardContent className="pt-6 pb-4">
              {hasError('general') && (
                <Alert variant="destructive" className="mb-6">
                  <AlertDescription>{errors.general}</AlertDescription>
                </Alert>
              )}

              {success ? (
                <div className="text-center py-8">
                  <div className="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <h3 className="text-2xl font-semibold mb-2 text-primary">{t('register.registrationCompleted')}</h3>
                  <p className="text-gray-300 mb-6">{t('register.profileRegisteredSuccessfully')}</p>
                  <Button
                    onClick={toggleToLogin}
                    className="bg-primary hover:bg-primary-hover"
                  >
                    {t('register.goToLogin')}
                  </Button>
                </div>
              ) : (
                <form onSubmit={handleRegisterSubmit} className="space-y-6">
                  <div>
                    <Label htmlFor="register-email" className="text-white">{t('register.email')}</Label>
                    <Input
                      id="register-email"
                      type="email"
                      value={registerForm.email}
                      onChange={(e) => handleRegisterChange('email', e.target.value)}
                      placeholder="tu@email.com"
                      required
                      className="mt-1 text-black"
                    />
                    {hasError('email') && (
                      <p className="text-sm text-red-400 mt-1">{errors.email}</p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="register-username" className="text-white">{t('register.username')}</Label>
                    <p className="text-xs text-gray-400 mb-1">{t('register.usernameOptional')}</p>
                    <Input
                      id="register-username"
                      value={registerForm.username}
                      onChange={(e) => handleRegisterChange('username', e.target.value)}
                      placeholder={t('register.usernamePlaceholder')}
                      className="mt-1 text-black"
                    />
                  </div>

                  <div>
                    <Label htmlFor="register-password" className="text-white">{t('register.password')}</Label>
                    <Input
                      id="register-password"
                      type="password"
                      value={registerForm.password}
                      onChange={(e) => handleRegisterChange('password', e.target.value)}
                      required
                      placeholder={t('register.passwordPlaceholder')}
                      className="mt-1 text-black"
                    />
                    <p className="text-xs text-gray-400 mt-1">{t('register.passwordHint')}</p>
                    {hasError('password') && (
                      <p className="text-sm text-red-400 mt-1">{errors.password}</p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="confirmPassword" className="text-white">{t('register.confirmPassword')}</Label>
                    <Input
                      id="confirmPassword"
                      type="password"
                      value={registerForm.confirmPassword}
                      onChange={(e) => handleRegisterChange('confirmPassword', e.target.value)}
                      required
                      placeholder={t('register.confirmPasswordPlaceholder')}
                      className="mt-1 text-black"
                    />
                    {hasError('confirmPassword') && (
                      <p className="text-sm text-red-400 mt-1">{errors.confirmPassword}</p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="cv" className="text-white">{t('register.cv')}</Label>
                    <Input
                      id="cv"
                      type="file"
                      accept="application/pdf"
                      onChange={handleFile}
                      className="mt-1 text-black"
                    />
                    <p className="text-xs text-gray-400 mt-1">{t('register.cvHint')}</p>
                    {hasError('cv') && (
                      <p className="text-sm text-red-400 mt-1">{errors.cv}</p>
                    )}
                  </div>

                  {/* Consentimiento GDPR - CRÍTICO PARA CUMPLIMIENTO UE */}
                  <div className="space-y-3 border border-gray-600 rounded-md p-4 bg-gray-800/50">
                    <div className="flex items-start space-x-3">
                      <Checkbox
                        id="gdpr-consent"
                        checked={gdprConsent}
                        onCheckedChange={(checked) => setGdprConsent(checked === true)}
                        className="mt-1"
                      />
                      <Label htmlFor="gdpr-consent" className="text-sm text-gray-300 leading-relaxed">
                        <span className="text-white font-medium">{t('register.agreeGdprData')}</span> {t('register.gdprText')}{' '}
                        <a
                          href="https://bubblugum.agency/politica-privacidad"
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-blue-400 hover:text-blue-300 underline"
                        >
                          {t('register.privacyPolicyLink')}
                        </a>.
                      </Label>
                    </div>

                    <div className="flex items-start space-x-3">
                      <Checkbox
                        id="data-processing-consent"
                        checked={dataProcessingConsent}
                        onCheckedChange={(checked) => setDataProcessingConsent(checked === true)}
                        className="mt-1"
                      />
                      <Label htmlFor="data-processing-consent" className="text-sm text-gray-300 leading-relaxed">
                        <span className="text-white font-medium">{t('register.acceptCvProcessing')}</span> {t('register.cvProcessingText')}
                        <span className="text-yellow-400 font-medium">{t('register.rightsText')}</span>
                      </Label>
                    </div>

                    <div className="text-xs text-gray-400 mt-2 p-2 bg-gray-700/30 rounded">
                      <strong>{t('register.gdprInfo')}</strong><br />
                      <strong>{t('register.responsible')}</strong> {t('register.responsibleText')} | <strong>{t('register.purposes')}</strong> {t('register.purposesText')}<br />
                      <strong>{t('register.thirdParties')}</strong> {t('register.thirdPartiesText')} | <strong>{t('register.retention')}</strong> {t('register.retentionText')}<br />
                      <strong>{t('register.gdprRights')}</strong> {t('register.gdprRightsText')} |
                      <strong>{t('register.dpoContact')}</strong> <span className="text-blue-400">privacy@bubbleoftalents.com</span>
                    </div>
                  </div>

                  <div className="flex gap-2">
                    <Button
                      type="button"
                      onClick={handleProcessCV}
                      disabled={isProcessing || !registerForm.cv || !gdprConsent || !dataProcessingConsent}
                      className="bg-blue-500 hover:bg-blue-600 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                      title={!gdprConsent || !dataProcessingConsent ? t('register.gdprTooltipProcessCv') : ""}
                    >
                      {isProcessing ? t('register.processingCv') : t('register.processCv')}
                    </Button>
                    <Button
                      type="submit"
                      disabled={isRegistering || !gdprConsent}
                      className="bg-primary hover:bg-primary-hover text-white flex-1 disabled:opacity-50 disabled:cursor-not-allowed"
                      title={!gdprConsent ? t('register.gdprTooltipRegister') : ""}
                    >
                      {isRegistering ? t('register.registering') : t('register.registerButton')}
                    </Button>
                  </div>
                </form>
              )}

              {/* Separador y botones de autenticación social para registro - solo visible si no es success */}
              {!success && (
                <div>
                  <div className="relative my-6">
                    <div className="absolute inset-0 flex items-center">
                      <Separator className="w-full" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                      <span className="bg-[#2f2f2f] px-2 text-gray-400">{t('register.orRegisterWith')}</span>
                    </div>
                  </div>

                  {/* Botones de autenticación social para registro */}
                  <div className="space-y-3">
                    <Button
                      type="button"
                      variant="outline"
                      className="w-full bg-white hover:bg-gray-50 text-gray-900 border-gray-300"
                      onClick={() => handleSocialLogin('google')}
                      disabled={isLoading || isRegistering}
                    >
                      <svg className="w-5 h-5 mr-3" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                      </svg>
                      {t('register.registerWithGoogle')}
                    </Button>

                    <Button
                      type="button"
                      variant="outline"
                      className="w-full bg-white hover:bg-gray-50 text-gray-900 border-gray-300"
                      onClick={() => handleSocialLogin('linkedin')}
                      disabled={isLoading || isRegistering}
                    >
                      <svg className="w-5 h-5 mr-3" viewBox="0 0 24 24">
                        <path fill="#0077B5" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                      </svg>
                      Registrarse con LinkedIn
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </CardForm>
        </div>
      </div>

      {/* Modal CV - Se muestra cuando showValidationModal es true */}
      {showValidationModal && (
        <CvFormProvider>
          <CvFormWrapper
            mode={cvProcessingFailed ? 'manual' : 'validation'}
            initialData={registerForm.cvData || {}}
            isOpen={showValidationModal}
            onClose={() => {
              setShowValidationModal(false);
              setCvProcessingFailed(false);
              setShowManualForm(false);
            }}
            onSave={(cvData) => {
              // Aquí guardarías los datos en la BD
              setShowValidationModal(false);
              setSuccess(true);
            }}
          />
        </CvFormProvider>
      )}
    </div>
  );
}
