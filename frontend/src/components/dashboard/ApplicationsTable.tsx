import { useEffect, useState } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '../../components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '../../components/ui/select';
import { Button } from '../../components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '../../components/ui/dialog';
import { Label } from '../../components/ui/label';
import { getApplications, getJobs, getCandidates } from '../../services/ApiService';
import { useLanguage } from '../../lib/i18n/LanguageContext';

// Define application status options
const APPLICATION_STATUS = [
    'Received',
    'Under Review',
    'Interview',
    'Technical Test',
    'Final Interview',
    'Offer',
    'Rejected',
    'Hired',
];

// Definición de los tipos de datos
interface Candidate {
    id: string;
    name: string;
    email: string;
    phone?: string;
    location?: string;
}

interface Job {
    id: string;
    title: string;
    description?: string;
    location?: string;
    type?: string;
    category?: string;
    datePosted?: string;
    skills?: string[];
}

interface Application {
    id: string;
    candidate: Candidate;
    job: Job;
    appliedDate: string;
    status: string;
    score: number;
    insights?: Record<string, unknown>;
}

interface ApplicationsTableProps {
    applications?: Application[];
    onStatusChange: (id: string, status: string) => void;
    onViewDetails: (application: Application) => void;
}

interface ApplicationDetailsDialogProps {
    app: Application | null;
    open: boolean;
    onClose: () => void;
    onStatusChange: (id: string, status: string) => void;
}

export function ApplicationsTable({ applications: propApplications, onStatusChange, onViewDetails }: ApplicationsTableProps) {
    const [localApplications, setLocalApplications] = useState<Application[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const { t } = useLanguage();

    useEffect(() => {
        // If applications prop is provided, use it
        if (propApplications && propApplications.length > 0) {
            setLocalApplications(propApplications);
            return;
        }

        // Otherwise, load applications from API
        loadApplicationsData();
    }, [propApplications]);

    const loadApplicationsData = async () => {
        try {
            setLoading(true);
            setError(null);

            // Get applications with embedded candidate and job data
            const applicationsResponse = await getApplications();

            if (!applicationsResponse.success) {
                throw new Error('Error al cargar los datos de aplicaciones');
            }

            const applications = applicationsResponse.data || [];

            // The applications already come with candidate and job data from the backend
            const apps: Application[] = (applications as any[]).map((app) => {
                return {
                    id: app.id || `${app.candidate_id}-${app.job_id}`,
                    candidate: {
                        id: app.candidate?.id || app.candidate_id || '',
                        name: app.candidate?.name || `Candidato ${app.candidate_id}`,
                        email: app.candidate?.email || app.candidate_email || '',
                        phone: app.candidate?.phone || '',
                        location: app.candidate?.location || ''
                    },
                    job: {
                        id: app.job?.id || app.job_id || '',
                        title: app.job?.title || `Puesto ${app.job_id}`,
                        description: app.job?.description || '',
                        location: app.job?.location || '',
                        type: app.job?.employment_type || '',
                        category: app.job?.department_name || '',
                        datePosted: app.job?.created_at || '',
                        skills: []
                    },
                    appliedDate: app.created_at || app.application_date || new Date().toISOString(),
                    status: app.status || 'Received',
                    score: app.match_score || app.score || Math.floor(Math.random() * 30) + 70,
                    insights: app.insights ? (typeof app.insights === 'string' ? JSON.parse(app.insights) : app.insights) : undefined
                } as Application;
            });

            setLocalApplications(apps);
        } catch (error) {
            console.error('Error cargando aplicaciones:', error);
            setError('Error al cargar las aplicaciones');
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="text-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#FF4785] mx-auto"></div>
                <p className="mt-2 text-gray-600">Cargando aplicaciones...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="text-center py-8">
                <p className="text-red-600">{error}</p>
                <Button onClick={loadApplicationsData} className="mt-2">
                    Reintentar
                </Button>
            </div>
        );
    }

    return (
        <Table className="bg-[#2f2f2f] text-[#FF4785]">
            <TableHeader>
                <TableRow>
                    <TableHead>{t('auth.candidate')}</TableHead>
                    <TableHead>{t('dashboard.position')}</TableHead>
                    <TableHead>{t('jobs.date')}</TableHead>
                    <TableHead>{t('dashboard.status')}</TableHead>
                    <TableHead>Department</TableHead>
                    <TableHead>{t('dashboard.matchScore')}</TableHead>
                    <TableHead className="text-right">{t('dashboard.action')}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {localApplications.length > 0 ? (
                    localApplications.map(app => (
                        <TableRow key={app.id}>
                            <TableCell>
                                <div>
                                    <p className="font-medium">{app.candidate?.name}</p>
                                    <p className="text-sm" style={{ color: '#2F2F2F' }}>{app.candidate?.email}</p>
                                </div>
                            </TableCell>
                            <TableCell>{app.job?.title}</TableCell>
                            <TableCell>{new Date(app.appliedDate).toLocaleDateString()}</TableCell>
                            <TableCell>
                                <Select
                                    defaultValue={app.status ?? APPLICATION_STATUS[0]}
                                    value={app.status ?? APPLICATION_STATUS[0]}
                                    onValueChange={(value) => onStatusChange(app.id, value)}
                                >
                                    <SelectTrigger className="w-36 border-[#F2f2f2f]">
                                        <span
                                            style={{
                                                color: '#fff',
                                                background:
                                                    app.status === 'Hired' ? '#22c55e' :
                                                        app.status === 'Rejected' ? '#ef4444' :
                                                            app.status === 'Interview' ? '#eab308' :
                                                                app.status === 'Under Review' ? '#3b82f6' :
                                                                    app.status === 'Technical Test' ? '#a855f7' :
                                                                        app.status === 'Offer' ? '#06b6d4' :
                                                                            '#2f2f2f',
                                                borderRadius: '0.375rem',
                                                padding: '0.25rem 0.5rem',
                                                fontWeight: 600,
                                            }}
                                        >
                                            {app.status || APPLICATION_STATUS[0]}
                                        </span>
                                    </SelectTrigger>
                                    <SelectContent className="text-[#FFFFFF] border-[#F2f2f2f]">
                                        {APPLICATION_STATUS.map(status => (
                                            <SelectItem key={status} value={status} className="bg-[#2f2f2f] text-[#ffffff] hover:bg-[#3a3a3a]">
                                                {status}
                                            </SelectItem >
                                        ))}
                                    </SelectContent>
                                </Select>
                            </TableCell>
                            <TableCell>{app.job?.category || '-'}</TableCell>
                            <TableCell>{app.score + '%'}</TableCell>
                            <TableCell className="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onViewDetails(app)}
                                >
                                    {t('dashboard.view')}
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell colSpan={5} className="text-center py-10">
                            <p style={{ color: '#2F2F2F' }}>{t('dashboard.noApplications')}</p>
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

export function ApplicationDetailsDialog({ app, open, onClose, onStatusChange }: ApplicationDetailsDialogProps) {
    const { t } = useLanguage();

    if (!app) return null;
    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{t('hrDashboard.candidateDetails')}</DialogTitle>
                    <DialogDescription>
                        {t('dashboard.applicationDetails')}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 py-4">
                    <div className="md:col-span-2">
                        <h3 className="text-lg font-semibold mb-4">{t('profile.personalInfo')}</h3>
                        <div className="space-y-4">
                            <div>
                                <Label>{t('register.name')}</Label>
                                <p>{app.candidate?.name}</p>
                            </div>
                            <div>
                                <Label>{t('register.email')}</Label>
                                <p>{app.candidate?.email}</p>
                            </div>
                            <div>
                                <Label>{t('register.phone')}</Label>
                                <p>{app.candidate?.phone}</p>
                            </div>
                            <div>
                                <Label>{t('profile.location')}</Label>
                                <p>{app.candidate?.location}</p>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-4">
                        <div>
                            <h3 className="text-lg font-semibold mb-4">{t('jobs.applicationForm')}</h3>
                            <div className="space-y-2">
                                <div>
                                    <Label>{t('dashboard.position')}</Label>
                                    <p>{app.job?.title}</p>
                                </div>
                                <div>
                                    <Label>{t('dashboard.dateApplied')}</Label>
                                    <p>{new Date(app.appliedDate).toLocaleDateString()}</p>
                                </div>
                                <div>
                                    <Label>{t('dashboard.applicationStatus')}</Label>
                                    <p className="font-medium">{app.status}</p>
                                </div>
                                <div>
                                    <Label>{t('dashboard.matchScore')}</Label>
                                    <p className="font-medium">{app.score + '%'}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold mb-2">{t('jobs.resume')}</h3>
                            <Button variant="outline" size="sm" className="w-full justify-start">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                {t('register.uploadCV')}
                            </Button>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <div className="w-full flex flex-col sm:flex-row justify-between gap-2">
                        <Select
                            value={app.status}
                            onValueChange={(value) => onStatusChange(app.id, value)}
                        >
                            <SelectTrigger className="w-full sm:w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {APPLICATION_STATUS.map(status => (
                                    <SelectItem key={status} value={status}>
                                        {status}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="flex gap-2">
                            <Button variant="outline" className="flex-1 sm:flex-none">
                                {t('hrDashboard.sendEmail')}
                            </Button>
                            <Button className="flex-1 sm:flex-none bg-[#FF4785] hover:bg-[#FF3575]">
                                {t('hrDashboard.changeStatus')}
                            </Button>
                        </div>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
