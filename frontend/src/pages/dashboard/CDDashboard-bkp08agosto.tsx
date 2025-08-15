import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  getCandidates,
  getJobs,
  getCandidateSkills,
  getCandidateExperiences,
  getNotifications,
} from '../../lib/apiService';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import {
  Tabs,
  TabsContent,
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

// Mini componente Switch
function Switch({ checked }: { checked: boolean }) {
  return (
    <div
      className={`w-10 h-5 rounded-full p-1 transition-colors ${checked ? 'bg-[#FF4785]' : 'bg-gray-300'}`}
    >
      <div
        className={`w-3 h-3 rounded-full bg-white transform transition-transform ${checked ? 'translate-x-5' : ''}`}
      />
    </div>
  );
}

function Label({ htmlFor, children }: { htmlFor: string; children: React.ReactNode }) {
  return (
    <label htmlFor={htmlFor} className="block font-semibold text-[#FF4785] mb-1">
      {children}
    </label>
  );
}

export default function CDDashboard() {
  const navigate = useNavigate();
  const [tab, setTab] = useState('profile');
  const [userEmail, setUserEmail] = useState('');

  const [applicationsWithDetails, setApplicationsWithDetails] = useState<any[]>([]);
  const [skillsCandidato, setSkillsCandidato] = useState<string[]>([]);
  // Lista de experiencias profesionales del candidato
  const [experiences, setExperiences] = useState<any[]>([]);
  // Notificaciones recibidas por el candidato
  const [notificationsList, setNotificationsList] = useState<any[]>([]);
  const [coverLetter, setCoverLetter] = useState('');
  const [salary, setSalary] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [loading, setLoading] = useState(false);
  const [candidateData, setCandidateData] = useState<any>(null);

  useEffect(() => {
    const email = localStorage.getItem('userEmail') || 'usuario@ejemplo.com';
    setUserEmail(email);

    const loadCandidateData = async () => {
      try {
        setLoading(true);
        // Obtener candidatos y buscar el actual por email
        const candidatesResponse = await getCandidates();

        if (candidatesResponse.success && candidatesResponse.data) {
          const candidato = candidatesResponse.data.find((c: any) => c.email === email);
          if (candidato) {
            setCandidateData(candidato);
            // Obtener habilidades desde el nuevo endpoint de candidate_skills
            try {
              const skills = await getCandidateSkills(candidato.id);
              // Mapear al arreglo de strings, fallback si el endpoint devuelve claves distintas
              const mappedSkills = skills.map((s: any) => s.skill || s.skills || s.name || s);
              setSkillsCandidato(mappedSkills);
            } catch (err) {
              console.error('Error loading candidate skills:', err);
              // Fallback a skills del candidato si están disponibles en el registro base
              setSkillsCandidato(candidato.skills ? candidato.skills.split(', ') : []);
            }
            // Obtener experiencias profesionales desde el nuevo endpoint
            try {
              const exps = await getCandidateExperiences(candidato.id);
              setExperiences(exps || []);
            } catch (err) {
              console.error('Error loading candidate experiences:', err);
              setExperiences([]);
            }
            // Obtener notificaciones del candidato
            try {
              const notifs = await getNotifications(candidato.id);
              setNotificationsList(notifs || []);
            } catch (err) {
              console.error('Error loading candidate notifications:', err);
              setNotificationsList([]);
            }
            // Si tiene trabajo aplicado, obtener detalles
            if (candidato.appliedJobId) {
              try {
                const jobsResponse = await getJobs();
                if (jobsResponse && Array.isArray(jobsResponse)) {
                  const job = jobsResponse.find((j: any) => j.id === candidato.appliedJobId);
                  if (job) {
                    setApplicationsWithDetails([
                      {
                        id: job.id,
                        job,
                        status: candidato.status || 'Received',
                        appliedDate: candidato.dateApplied || new Date().toISOString().split('T')[0],
                        score: candidato.score || '',
                        jobLocation: job.location,
                        jobType: job.type,
                        jobCategory: job.category,
                        jobDescription: job.description,
                      }
                    ]);
                  }
                }
              } catch (jobError) {
                console.error('Error loading job details:', jobError);
              }
            }
          }
        }
      } catch (error) {
        console.error('Error loading candidate data:', error);
        setSuccessMsg('Error al cargar datos del candidato');
      } finally {
        setLoading(false);
      }
    };

    loadCandidateData();
  }, []);

  const handleSimulateSubmit = () => {
    setLoading(true);
    setTimeout(() => {
      setSuccessMsg('¡Tu postulación fue enviada exitosamente (simulado)!');
      setCoverLetter('');
      setSalary('');
      setLoading(false);
    }, 1200);
  };

  return (
    <section className="py-8">
      <div className="container mx-auto px-4">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center bg-[#FF4785] rounded-md p-5 font-poppins">
          <div>
            <h1 className="text-2xl font-bold text-[#2F2F2F]">Candidate Dashboard</h1>
            <p className="text-white">Bienvenido, {userEmail}</p>
          </div>
          <div className="flex gap-2 mt-4 md:mt-0">
            {tab === 'profile' && (
              <Button
                className="bg-[#2F2F2F] text-white"
                onClick={() => {
                  setTab('applications');
                  navigate('/dashboard#applications');
                }}
              >
                Go to Dashboard
              </Button>
            )}
            <Button
              className="bg-white text-[#FF4785]"
              onClick={() => {
                setTab('profile');
                navigate('/dashboard#profile');
              }}
            >
              My Profile
            </Button>
            <Button
              className="bg-white text-[#FF4785]"
              onClick={() => {
                setTab('notifications');
                navigate('/dashboard#notifications');
              }}
            >
              Notifications
            </Button>
          </div>
        </div>

        <div className="py-8">
          <Tabs value={tab} onValueChange={setTab} className="w-full">
            <TabsList className="flex gap-2 mb-6">
              <TabsTrigger value="applications">Applications</TabsTrigger>
              <TabsTrigger value="profile">Profile</TabsTrigger>
              <TabsTrigger value="notifications">Notifications</TabsTrigger>
            </TabsList>

            {/* Applications */}
            <TabsContent value="applications" className="space-y-6">
              <h2 className="header-small">Solicitudes recientes</h2>
              {applicationsWithDetails.length ? (
                <div className="grid md:grid-cols-2 gap-4">
                  {applicationsWithDetails.map((app) => (
                    <Card key={app.id}>
                      <CardHeader>
                        <CardTitle>{app.job?.title}</CardTitle>
                        <CardDescription>{app.status}</CardDescription>
                      </CardHeader>
                      <CardContent>
                        <div className="space-y-2 text-sm">
                          <div>Fecha de postulación: {app.appliedDate}</div>
                          <div>Score: <span className="font-bold text-[#FF4785]">{app.score}%</span></div>
                          <div>Ubicación: {app.jobLocation}</div>
                          <div>Tipo: {app.jobType}</div>
                          <div>Categoría: {app.jobCategory}</div>
                          <div>Descripción: {app.jobDescription}</div>
                          <div>Skills requeridas: {app.job?.skills?.join(', ')}</div>
                          <div>Tus skills: {skillsCandidato.join(', ')}</div>
                        </div>
                      </CardContent>
                      <CardFooter>
                        <Button variant="outline" size="sm" className="text-[#2F2F2F] border-[#FF4785]">
                          Ver detalles
                        </Button>
                      </CardFooter>
                    </Card>
                  ))}
                </div>
              ) : (
                <Card>
                  <CardContent className="py-12 text-center">
                    <h3 className="text-lg font-bold mb-2">Aún no tienes solicitudes</h3>
                    <p className="text-sm mb-6">Explora nuestras ofertas y postúlate</p>
                    <Button className="bg-[#FF4785] hover:bg-[#FF3575]">Ver empleos disponibles</Button>
                  </CardContent>
                </Card>
              )}
            </TabsContent>

            {/* Profile */}
            <TabsContent value="profile">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card className="md:col-span-2">
                  <CardHeader>
                    <CardTitle>Personal Information</CardTitle>
                    <CardDescription>Update your personal data</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="firstName">First Name</Label>
                        <Input id="firstName" defaultValue="User" />
                      </div>
                      <div>
                        <Label htmlFor="lastName">Last Name</Label>
                        <Input id="lastName" defaultValue="Example" />
                      </div>
                    </div>
                    <div>
                      <Label htmlFor="email">Email</Label>
                      <Input id="email" value={userEmail || ''} readOnly />
                    </div>
                    <div>
                      <Label htmlFor="phone">Phone</Label>
                      <Input id="phone" placeholder="Add your phone number" />
                    </div>
                  </CardContent>
                  <CardFooter>
                    <Button className="ml-auto bg-[#FF4785] hover:bg-[#FF3575]">
                      Save changes
                    </Button>
                  </CardFooter>
                </Card>
                <div className="space-y-6">
                  <Card>
                    <CardHeader>
                      <CardTitle>CV and Documents</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <UploadCV onSuccess={(candidate) => {
                        console.log('Candidato actualizado:', candidate);
                        setSuccessMsg('¡Tu CV fue actualizado exitosamente!');
                      }} />
                      <div className="mt-8 space-y-4">
                        <label className="block font-semibold text-[#FF4785]" htmlFor="coverLetter">Carta de presentación</label>
                        <textarea
                          id="coverLetter"
                          name="coverLetter"
                          rows={4}
                          className="w-full border rounded px-3 py-2"
                          placeholder="Escribe una breve carta de presentación..."
                          value={coverLetter}
                          onChange={e => setCoverLetter(e.target.value)}
                        />
                        <label className="block font-semibold text-[#FF4785]" htmlFor="salary">Pretensión salarial (USD)</label>
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
                          onClick={handleSimulateSubmit}
                          disabled={loading}
                        >
                          {loading ? 'Enviando...' : 'Enviar postulación'}
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
                    <CardTitle>Professional Experience</CardTitle>
                    <CardDescription>Historial laboral del candidato</CardDescription>
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
            </TabsContent>

            {/* Notifications */}
            <TabsContent value="notifications">
              <div className="space-y-6">
                {/* Lista de notificaciones reales */}
                <Card>
                  <CardHeader>
                    <CardTitle>Your Notifications</CardTitle>
                    <CardDescription>Últimas notificaciones recibidas</CardDescription>
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

                {/* Preferencias de notificación (estático por ahora) */}
                <Card>
                  <CardHeader>
                    <CardTitle>Notification Preferences</CardTitle>
                    <CardDescription>Ajusta cómo deseas recibir alertas</CardDescription>
                  </CardHeader>
                  <CardContent >
                    {[
                      {
                        title: 'Application Updates',
                        description: 'Receive notifications when the status of your applications changes',
                        checked: true,
                      },
                      {
                        title: 'New Jobs',
                        description: 'Get alerts about new jobs that match your profile',
                        checked: false,
                      },
                      {
                        title: 'Reminders',
                        description: 'Receive reminders about interviews or events',
                        checked: true,
                      },
                    ].map((item, idx) => (
                      <div key={idx} className="flex items-center justify-between border-b pb-2 last:border-b-0 last:pb-0">
                        <div>
                          <div className="font-semibold">{item.title}</div>
                          <div className="text-sm text-muted-foreground">
                            {item.description}
                          </div>
                        </div>
                        <Switch checked={item.checked} />
                      </div>
                    ))}
                  </CardContent>
                  <CardFooter>
                    <Button className="ml-auto bg-[#FF4785] hover:bg-[#FF3575]">
                      Save preferences
                    </Button>
                  </CardFooter>
                </Card>
              </div>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </section>
  );
}
