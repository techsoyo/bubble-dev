import * as React from 'react';
import { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  getCandidateApplications,
  submitApplication,
  updateCandidateProfile,
} from '../../lib/apiService';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { useAuth } from '../../contexts/AuthContext';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Switch } from '../../components/ui/switch';
import {
  Tabs,
  TabsList,
  TabsTrigger,
} from '../../components/ui/tabs';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '../../components/ui/card';
import UploadCV from '../../components/UploadCV';
import ChangePassword from '../../components/profile/ChangePassword';

// Types
interface NotificationPreferences {
  applicationUpdates: boolean;
  newJobs: boolean;
  reminders: boolean;
}

interface CandidateExperience {
  position: string;
  company: string;
  start_date: string;
  end_date?: string;
  current?: boolean;
  location?: string;
  description?: string;
}

interface CandidateNotification {
  message: string;
  created_at?: string;
}

// Helper function to translate application status
const translateStatus = (status: string, t: any): string => {
  const statusKey = status.toLowerCase().replace(/\s+/g, '');
  return t(`dashboard.applicationStatuses.${statusKey}`) || status;
};

interface Application {
  application_id: string;
  job_title: string;
  status: string;
  applied_date: string;
  score?: string;
  job_location: string;
  job_type: string;
  job_category: string;
  job_description: string;
  salary_range: string;
  company_name: string;
}

export default function CDDashboard(): JSX.Element {
  const navigate = useNavigate();
  const { t } = useLanguage();
  const { user, isLoggedIn, isLoading, isInitialized } = useAuth();

  // UI State
  const [tab, setTab] = useState<string>('profile');
  const [loading, setLoading] = useState<boolean>(false);
  const [successMsg, setSuccessMsg] = useState<string>('');

  // Form State
  const [coverLetter, setCoverLetter] = useState<string>('');
  const [salary, setSalary] = useState<string>('');

  // Data State
  const [applicationsWithDetails, setApplicationsWithDetails] = useState<Application[]>([]);
  const [experiences, setExperiences] = useState<CandidateExperience[]>([]);
  const [notificationsList, setNotificationsList] = useState<CandidateNotification[]>([]);
  const [notificationPreferences, setNotificationPreferences] = useState<NotificationPreferences>({
    applicationUpdates: true,
    newJobs: false,
    reminders: true
  });
  const [candidateData, setCandidateData] = useState<any>(null);

  // Success message auto-clear
  useEffect(() => {
    if (successMsg) {
      const timer = setTimeout(() => setSuccessMsg(''), 5000);
      return () => clearTimeout(timer);
    }
    return () => {
      // No cleanup needed when successMsg is empty
    };
  }, [successMsg]);

  // API call functions
  const loadApplications = useCallback(async (userId: string) => {
    try {
      const applications = await getCandidateApplications(userId);
      console.log('Applications loaded:', applications);
      setApplicationsWithDetails(applications || []);
    } catch (err) {
      console.error('Error loading candidate applications:', err);
      setApplicationsWithDetails([]);
    }
  }, []);

  const loadNotificationPreferences = useCallback(async () => {
    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/api/get-notification-preferences.php`, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data) {
          setNotificationPreferences({
            applicationUpdates: result.data.application_updates || false,
            newJobs: result.data.new_jobs || false,
            reminders: result.data.reminders || false
          });
        }
      }
    } catch (error) {
      console.error('Error loading notification preferences:', error);
    }
  }, []);

  const loadExperiences = useCallback(async () => {
    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/api/candidate-experiences.php`, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data) {
          console.log('Experiences loaded:', result.data);
          setExperiences(result.data);
        }
      } else {
        console.error('Failed to load experiences:', response.status);
      }
    } catch (err) {
      console.error('Error loading candidate experiences:', err);
      setExperiences([]);
    }
  }, []);

  const loadNotifications = useCallback(async () => {
    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/api/candidate-notifications.php`, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data) {
          console.log('Notifications loaded:', result.data);
          setNotificationsList(result.data);
        }
      } else {
        console.error('Failed to load notifications:', response.status);
      }
    } catch (err) {
      console.error('Error loading candidate notifications:', err);
      setNotificationsList([]);
    }
  }, []);

  // Main data loading effect
  useEffect(() => {
    // Función async interna para manejar la carga de datos
    const initializeData = async () => {
      // Esperar a que la autenticación se inicialice
      if (isLoading || !isInitialized) {
        return;
      }

      // Si no hay usuario autenticado después de la inicialización, redirigir al login
      if (!isLoggedIn || !user) {
        navigate('/candidates/login');
        return;
      }

      try {
        setLoading(true);

        // Usar únicamente los datos del usuario autenticado
        setCandidateData(user);

        // Cargar datos en paralelo para mejor performance
        await Promise.allSettled([
          loadApplications(user.id),
          loadExperiences(),
          loadNotifications(),
          loadNotificationPreferences()
        ]);

      } catch (error) {
        console.error('Error loading candidate data:', error);
        setSuccessMsg('Error al cargar datos del candidato');
      } finally {
        setLoading(false);
      }
    };

    initializeData();
  }, [user, isLoggedIn, isLoading, isInitialized, navigate, loadApplications, loadExperiences, loadNotifications]);

  const handleProfileUpdate = useCallback(async () => {
    if (!coverLetter.trim() && !salary.trim()) {
      setSuccessMsg('Por favor, completa al menos un campo para actualizar');
      return;
    }

    setLoading(true);
    try {
      const updateData: any = {};

      if (coverLetter.trim()) {
        updateData.professional_summary = coverLetter;
      }

      if (salary.trim()) {
        updateData.availability = `Salario esperado: €${salary}`;
      }

      const result = await updateCandidateProfile(updateData);

      if (result.success) {
        setSuccessMsg('¡Tu perfil fue actualizado exitosamente!');
        setCoverLetter('');
        setSalary('');
      } else {
        throw new Error(result.message || 'Error al actualizar perfil');
      }
    } catch (error: any) {
      console.error('Error updating profile:', error);
      setSuccessMsg(`Error: ${error.message || 'No se pudo actualizar el perfil'}`);
    } finally {
      setLoading(false);
    }
  }, [coverLetter, salary]);

  const handleSaveNotificationPreferences = useCallback(async () => {
    try {
      const response = await fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/api/save-notification-preferences.php`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(notificationPreferences)
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          setSuccessMsg('Preferencias de notificación guardadas correctamente');
        } else {
          setSuccessMsg('Error al guardar las preferencias');
        }
      } else {
        setSuccessMsg('Error al guardar las preferencias');
      }
    } catch (error) {
      console.error('Error saving preferences:', error);
      setSuccessMsg('Error al guardar las preferencias');
    }
  }, [notificationPreferences]);

  // Mostrar indicador de carga mientras se inicializa la autenticación
  if (isLoading || !isInitialized) {
    return (
      <section className="py-8">
        <div className="container mx-auto px-4 flex justify-center items-center min-h-[400px]">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#FF4785] mx-auto mb-4"></div>
            <p className="text-gray-600">Cargando dashboard...</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="py-8">
      <div className="container mx-auto px-4">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-[#FF4785] rounded-md p-5 font-poppins">
          <div>
            <h1 className="text-2xl font-bold text-[#2F2F2F]">{t('dashboard.candidateDashboard')}</h1>
            <p className="text-white">
              {t('dashboard.welcome')}, {user?.name || user?.email || 'Usuario'}
            </p>
          </div>
          <div className="flex items-center gap-4">
            <Tabs value={tab} onValueChange={setTab} className="flex">
              <TabsList className="bg-white/20 backdrop-blur-sm border-white/30">
                <TabsTrigger value="applications" className="text-white data-[state=active]:bg-white data-[state=active]:text-[#FF4785] hover:bg-white/10">
                  {t('dashboard.applicationsTab')}
                </TabsTrigger>
                <TabsTrigger value="profile" className="text-white data-[state=active]:bg-white data-[state=active]:text-[#FF4785] hover:bg-white/10">
                  {t('dashboard.profileTab')}
                </TabsTrigger>
                <TabsTrigger value="notifications" className="text-white data-[state=active]:bg-white data-[state=active]:text-[#FF4785] hover:bg-white/10">
                  {t('dashboard.notificationsTab')}
                </TabsTrigger>
                <TabsTrigger value="security" className="text-white data-[state=active]:bg-white data-[state=active]:text-[#FF4785] hover:bg-white/10">
                  {t('dashboard.securityTab')}
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </div>
        </div>

        <div className="py-8">
          <div className="w-full">
            {/* Applications */}
            <div className={tab === 'applications' ? 'space-y-6' : 'hidden'}>
              <h2 className="header-small">{t('dashboard.recentApplications')}</h2>
              {applicationsWithDetails.length ? (
                <div className="grid md:grid-cols-2 gap-4">
                  {applicationsWithDetails.map((app) => (
                    <Card key={app.application_id}>
                      <CardHeader>
                        <CardTitle>{app.job_title}</CardTitle>
                        <CardDescription>{translateStatus(app.status, t)}</CardDescription>
                      </CardHeader>
                      <CardContent>
                        <div className="space-y-2 text-sm">
                          <div>{t('dashboard.applicationDate')}: {app.applied_date}</div>
                          {app.score && <div>{t('dashboard.score')}: <span className="font-bold text-[#FF4785]">{app.score}%</span></div>}
                          <div>{t('dashboard.jobLocation')}: {app.job_location}</div>
                          <div>{t('dashboard.jobType')}: {app.job_type}</div>
                          <div>{t('dashboard.jobCategory')}: {app.job_category}</div>
                          <div>{t('dashboard.jobDescription')}: {app.job_description}</div>
                          <div>{t('dashboard.salary')}: {app.salary_range}</div>
                          <div>{t('dashboard.company')}: {app.company_name}</div>
                        </div>
                      </CardContent>
                      <CardFooter>
                        <Button variant="outline" size="sm" className="text-[#2F2F2F] border-[#FF4785]">
                          {t('dashboard.viewDetails')}
                        </Button>
                      </CardFooter>
                    </Card>
                  ))}
                </div>
              ) : (
                <Card>
                  <CardContent className="py-12 text-center">
                    <h3 className="text-lg font-bold mb-2">{t('dashboard.noApplicationsYet')}</h3>
                    <p className="text-sm mb-6">{t('dashboard.exploreOffers')}</p>
                    <Button className="bg-[#FF4785] hover:bg-[#FF3575]">{t('dashboard.viewAvailableJobs')}</Button>
                  </CardContent>
                </Card>
              )}
            </div>

            {/* Profile */}
            <div className={tab === 'profile' ? 'block' : 'hidden'}>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card className="md:col-span-2">
                  <CardHeader>
                    <CardTitle>{t('dashboard.personalInformation')}</CardTitle>
                    <CardDescription>{t('dashboard.updatePersonalData')}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="firstName">{t('dashboard.firstName')}</Label>
                        <Input id="firstName" defaultValue="User" />
                      </div>
                      <div>
                        <Label htmlFor="lastName">{t('dashboard.lastName')}</Label>
                        <Input id="lastName" defaultValue="Example" />
                      </div>
                    </div>
                    <div>
                      <Label htmlFor="email">Email</Label>
                      <Input
                        id="email"
                        value={user?.email || candidateData?.email || ''}
                        readOnly
                      />
                    </div>
                    <div>
                      <Label htmlFor="phone">{t('dashboard.phone')}</Label>
                      <Input id="phone" placeholder={t('dashboard.phoneNumberPlaceholder')} />
                    </div>
                  </CardContent>
                  <CardFooter>
                    <Button className="ml-auto bg-[#FF4785] hover:bg-[#FF3575]">
                      {t('dashboard.saveChanges')}
                    </Button>
                  </CardFooter>
                </Card>
                <div className="space-y-6">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('dashboard.cvAndDocuments')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <UploadCV onSuccess={(candidate) => {
                        console.log('Candidato actualizado:', candidate);
                        setSuccessMsg('¡Tu CV fue actualizado exitosamente!');
                      }} />
                      <div className="mt-8 space-y-4">
                        <label className="block font-semibold text-[#FF4785]" htmlFor="coverLetter">{t('dashboard.coverLetter')}</label>
                        <textarea
                          id="coverLetter"
                          name="coverLetter"
                          rows={4}
                          className="w-full border rounded px-3 py-2"
                          placeholder={t('dashboard.coverLetterPlaceholder')}
                          value={coverLetter}
                          onChange={e => setCoverLetter(e.target.value)}
                        />
                        <label className="block font-semibold text-[#FF4785]" htmlFor="salary">{t('dashboard.expectedSalary')} (€)</label>
                        <input
                          id="salary"
                          name="salary"
                          type="number"
                          className="w-full border rounded px-3 py-2"
                          placeholder="Ej: 1200"
                          min="0"
                          value={salary}
                          onChange={e => setSalary(e.target.value)}
                        />
                        <button
                          type="button"
                          className="w-full bg-[#FF4785] hover:bg-[#FF3575] text-white font-bold py-2 rounded"
                          onClick={handleProfileUpdate}
                          disabled={loading}
                        >
                          {loading ? t('dashboard.updatingProfile') : t('dashboard.updateProfile')}
                        </button>
                        {successMsg && (
                          <div className="text-green-600 font-semibold text-center mt-2">{successMsg}</div>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>

              {/* Experiencias profesionales */}
              <div className="mt-6">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('dashboard.professionalExperience')}</CardTitle>
                    <CardDescription>{t('dashboard.candidateWorkHistory')}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {experiences && experiences.length > 0 ? (
                      experiences.map((exp: any, idx: number) => (
                        <div key={idx} className="border-b pb-2 last:border-b-0 last:pb-0">
                          <div className="font-medium text-[#2F2F2F]">{exp.position} @ {exp.company}</div>
                          <div className="text-xs text-gray-500">
                            {exp.start_date} – {exp.end_date || (exp.current ? 'Present' : '')}
                            {exp.location ? ` • ${exp.location}` : ''}
                          </div>
                          {exp.description && (
                            <p className="text-sm text-gray-700 mt-1">{exp.description}</p>
                          )}
                        </div>
                      ))
                    ) : (
                      <p className="text-sm text-gray-600">No hay experiencias laborales registradas.</p>
                    )}
                  </CardContent>
                </Card>
              </div>
            </div>

            {/* Notifications */}
            <div className={tab === 'notifications' ? 'space-y-6' : 'hidden'}>
              <div className="space-y-6">
                {/* Lista de notificaciones reales */}
                <Card>
                  <CardHeader>
                    <CardTitle>{t('dashboard.yourNotifications')}</CardTitle>
                    <CardDescription>{t('dashboard.receivedNotifications')}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {notificationsList && notificationsList.length > 0 ? (
                      notificationsList.map((notif: any, idx: number) => (
                        <div key={idx} className="border-b pb-2 last:border-b-0 last:pb-0">
                          <div className="font-medium text-[#2F2F2F]">{notif.message}</div>
                          {notif.created_at && (
                            <div className="text-xs text-gray-500">{new Date(notif.created_at).toLocaleString()}</div>
                          )}
                        </div>
                      ))
                    ) : (
                      <p className="text-sm text-gray-600">No tienes notificaciones recientes.</p>
                    )}
                  </CardContent>
                </Card>

                {/* Preferencias de notificación */}
                <Card>
                  <CardHeader>
                    <CardTitle>{t('dashboard.notificationPreferences')}</CardTitle>
                    <CardDescription>{t('dashboard.adjustNotificationAlerts')}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center justify-between border-b pb-2">
                      <div>
                        <div className="font-semibold">{t('dashboard.applicationUpdates')}</div>
                        <div className="text-sm text-muted-foreground">
                          {t('dashboard.applicationUpdatesDesc')}
                        </div>
                      </div>
                      <Switch
                        checked={notificationPreferences.applicationUpdates}
                        onChange={(checked) => setNotificationPreferences(prev => ({ ...prev, applicationUpdates: checked }))}
                      />
                    </div>
                    <div className="flex items-center justify-between border-b pb-2">
                      <div>
                        <div className="font-semibold">{t('dashboard.newJobs')}</div>
                        <div className="text-sm text-muted-foreground">
                          {t('dashboard.newJobsDesc')}
                        </div>
                      </div>
                      <Switch
                        checked={notificationPreferences.newJobs}
                        onChange={(checked) => setNotificationPreferences(prev => ({ ...prev, newJobs: checked }))}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <div>
                        <div className="font-semibold">{t('dashboard.reminders')}</div>
                        <div className="text-sm text-muted-foreground">
                          {t('dashboard.remindersDesc')}
                        </div>
                      </div>
                      <Switch
                        checked={notificationPreferences.reminders}
                        onChange={(checked) => setNotificationPreferences(prev => ({ ...prev, reminders: checked }))}
                      />
                    </div>
                  </CardContent>
                  <CardFooter>
                    <Button
                      className="ml-auto bg-[#FF4785] hover:bg-[#FF3575]"
                      onClick={handleSaveNotificationPreferences}
                    >
                      {t('dashboard.savePreferences')}
                    </Button>
                  </CardFooter>
                </Card>
              </div>
            </div>

            {/* Security */}
            <div className={tab === 'security' ? 'space-y-6' : 'hidden'}>
              <div className="max-w-2xl mx-auto">
                <div className="text-center mb-6">
                  <h2 className="text-2xl font-bold text-[#2F2F2F] mb-2">{t('dashboard.securityConfiguration')}</h2>
                  <p className="text-gray-600">{t('dashboard.manageAccountSecurity')}</p>
                </div>

                {/* Cambio de contraseña */}
                <div className="mb-8">
                  <ChangePassword
                    candidateId={user?.id || ''}
                    onPasswordChanged={(success, message) => {
                      if (success) {
                        setSuccessMsg(t('dashboard.passwordUpdatedSuccessfully'));
                        setTimeout(() => setSuccessMsg(''), 5000);
                      } else {
                        setSuccessMsg(`Error: ${message}`);
                        setTimeout(() => setSuccessMsg(''), 5000);
                      }
                    }}
                  />
                </div>

                {/* Información de seguridad adicional */}
                <Card>
                  <CardHeader>
                    <CardTitle className="text-[#FF4785]">{t('dashboard.securityInformation')}</CardTitle>
                    <CardDescription>{t('dashboard.reviewRecentActivity')}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div className="p-4 border rounded-lg bg-gray-50">
                        <h4 className="font-semibold text-gray-800 mb-2">{t('dashboard.lastAccess')}</h4>
                        <p className="text-sm text-gray-600">{t('dashboard.todayAt')}</p>
                        <p className="text-xs text-gray-500">{t('dashboard.locationMadrid')}</p>
                      </div>
                      <div className="p-4 border rounded-lg bg-gray-50">
                        <h4 className="font-semibold text-gray-800 mb-2">{t('dashboard.passwordChange')}</h4>
                        <p className="text-sm text-gray-600">{t('dashboard.never')}</p>
                        <p className="text-xs text-gray-500">{t('dashboard.passwordChangeRecommendation')}</p>
                      </div>
                    </div>

                    <div className="border-t pt-4">
                      <h4 className="font-semibold text-gray-800 mb-3">{t('dashboard.activeSecurityMeasures')}</h4>
                      <div className="space-y-2">
                        <div className="flex items-center justify-between py-2">
                          <span className="text-sm">{t('dashboard.secureAuthentication')}</span>
                          <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">{t('dashboard.active')}</span>
                        </div>
                        <div className="flex items-center justify-between py-2">
                          <span className="text-sm">{t('dashboard.emailVerification')}</span>
                          <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">{t('dashboard.verified')}</span>
                        </div>
                        <div className="flex items-center justify-between py-2">
                          <span className="text-sm">{t('dashboard.secureHttpsConnection')}</span>
                          <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">{t('dashboard.active')}</span>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                {/* Mensaje de éxito global */}
                {successMsg && (
                  <div className="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                    <div className="text-green-800 text-center font-medium">{successMsg}</div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
