import React, { useState, useRef, useCallback, useEffect } from 'react';
import { Upload, X, FileText, User, Briefcase, Heart, CheckCircle } from 'lucide-react';
import { registerCandidate, uploadCV, saveCandidateFromAI } from '../services/ApiService';
import { useLanguage } from '../lib/i18n/LanguageContext';

// Lazy loading de PDF.js para evitar cargar 680KB al inicio
let pdfJsModule: any = null;
const loadPdfJs = async () => {
    if (!pdfJsModule) {
        const module = await import('pdfjs-dist');
        module.GlobalWorkerOptions.workerSrc = `//cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js`;
        pdfJsModule = module;
    }
    return pdfJsModule;
};

interface PersonalInfo {
    name: string;
    email: string;
    phone: string;
    address: string;
    linkedinUrl?: string;
    portfolio?: string;
}

interface Education {
    degree: string;
    institution: string;
    year: string;
    skills?: string[];
}

interface Experience {
    position: string;
    company: string;
    startDate: string;
    endDate: string;
    description: string;
}

interface ParsedCVData {
    personalInfo: PersonalInfo;
    education: Education[];
    experience: Experience[];
    skills: string[];
    languages: string[];
    keywords: string;
    categoria: string;
    motivation?: string;
}

export interface Candidate {
    id: string;
    name: string;
    email: string;
    phone: string;
    address: string;
    linkedinUrl?: string;
    portfolio?: string;
    education: string;
    experience: string;
    skills: string;
    languages: string;
    cvFile?: string;
    coverLetter?: string;
    dateOfBirth?: string;
    nationality?: string;
    workLocation?: string;
    expectedSalary?: string;
    availability?: string;
    jobPosition?: string;
    keywords: string;
    categoria: string;
    residencePermit?: string;
    consentimiento_gdpr?: boolean;
    status?: string;
    appliedJobId?: string;
    dateApplied?: string;
    motivation?: string;
}

interface UploadCVProps {
    onSuccess?: (candidate: Candidate) => void;
    jobId?: string;
    parsedCvData?: Record<string, unknown>; // Datos ya procesados del backend
}

// Interfaces expandidas para incluir todos los campos del backend
interface PersonalInfoExpanded {
    name: string;
    email: string;
    phone: string;
    address: string;
    linkedinUrl?: string;
    portfolio?: string;
    dateOfBirth?: string;
    otherNetworks?: string[];
}

interface EducationExpanded {
    degree: string;
    institution: string;
    fieldOfStudy?: string;
    startDate?: string;
    endDate?: string;
    educationLevel?: string;
    description?: string;
}

interface ExperienceExpanded {
    position: string;
    company: string;
    startDate: string;
    endDate: string;
    description: string;
    responsibilities?: string[];
    location?: string;
    current?: boolean;
}


interface CertificationData {
    name: string;
    issuer: string;
    issueDate?: string;
    expirationDate?: string;
}

interface ReferenceData {
    name: string;
    company: string;
    email: string;
    phone: string;
    notes?: string;
}

interface LanguageData {
    language: string;
    level: string;
}

interface ParsedCVDataExpanded {
    personalInfo: PersonalInfoExpanded;
    education: EducationExpanded[];
    experience: ExperienceExpanded[];
    skills: string[];
    softSkills: string[];
    languages: LanguageData[];
    certifications: CertificationData[];
    references: ReferenceData[];
    interests: string[];
    additionalSkills: string[];
    professionalSummary: string;
    availability?: string;
    keywords: string;
    categoria: string;
    motivation?: string;
}

/**
 * Mapea los datos del backend (de IA Groq) al formato expandido para mostrar TODOS los campos
 */
function mapBackendDataToFrontend(backendData: Record<string, unknown>): ParsedCVDataExpanded {
    console.log('[mapBackendDataToFrontend] Datos recibidos:', backendData);

    // Mapear educación con todos los campos
    const education: EducationExpanded[] = Array.isArray(backendData.educacion) ?
        (backendData.educacion as Array<any>).map(e => ({
            degree: e.titulo || '',
            institution: e.institucion || '',
            fieldOfStudy: e.campo_estudio || '',
            startDate: e.fecha_inicio || '',
            endDate: e.fecha_fin || '',
            educationLevel: e.nivel_educativo || '',
            description: e.descripcion || ''
        })) : [];

    // Mapear experiencia con todos los campos
    const experience: ExperienceExpanded[] = Array.isArray(backendData.puestos_anteriores) ?
        (backendData.puestos_anteriores as Array<any>).map(exp => ({
            position: exp.puesto || '',
            company: exp.empresa || '',
            startDate: exp.fecha_inicio || '',
            endDate: exp.fecha_fin || (exp.actual ? 'Presente' : ''),
            description: exp.descripcion || '',
            responsibilities: Array.isArray(exp.responsabilidades) ? exp.responsabilidades : [],
            location: exp.ubicacion || '',
            current: exp.actual || false
        })) : [];

    // Mapear certificaciones
    const certifications: CertificationData[] = Array.isArray(backendData.certificaciones_detalle) ?
        (backendData.certificaciones_detalle as Array<any>).map(cert => ({
            name: cert.nombre_certificacion || '',
            issuer: cert.emisor || '',
            issueDate: cert.fecha_emision || '',
            expirationDate: cert.fecha_expiracion || ''
        })) : [];

    // Mapear referencias
    const references: ReferenceData[] = Array.isArray(backendData.referencias_detalle) ?
        (backendData.referencias_detalle as Array<any>).map(ref => ({
            name: ref.nombre_referencia || '',
            company: ref.empresa_referencia || '',
            email: ref.email_referencia || '',
            phone: ref.telefono_referencia || '',
            notes: ref.notas || ''
        })) : [];

    // Mapear idiomas con niveles
    const languages: LanguageData[] = Array.isArray(backendData.idiomas) ?
        (backendData.idiomas as Array<any>).map(lang => ({
            language: lang.idioma || '',
            level: lang.nivel || ''
        })) : [];

    // Skills técnicas y blandas
    const skills: string[] = Array.isArray(backendData.hard_skills) ? (backendData.hard_skills as string[]) : [];
    const softSkills: string[] = Array.isArray(backendData.soft_skills) ? (backendData.soft_skills as string[]) : [];
    const additionalSkills: string[] = Array.isArray(backendData.habilidades_adicionales) ? (backendData.habilidades_adicionales as string[]) : [];
    const interests: string[] = Array.isArray(backendData.intereses) ? (backendData.intereses as string[]) : [];

    // Otras redes sociales
    const otherNetworks: string[] = Array.isArray(backendData.otras_redes) ? (backendData.otras_redes as string[]) : [];

    return {
        personalInfo: {
            name: (backendData.nombre as string) || '',
            email: (backendData.email as string) || '',
            phone: (backendData.telefono as string) || '',
            address: (backendData.ubicacion_actual as string) || '',
            linkedinUrl: (backendData.linkedin as string) || '',
            portfolio: (backendData.portfolio as string) || '',
            dateOfBirth: (backendData.fecha_nacimiento as string) || '',
            otherNetworks
        },
        education,
        experience,
        skills,
        softSkills,
        languages,
        certifications,
        references,
        interests,
        additionalSkills,
        professionalSummary: (backendData.resumen_profesional as string) || '',
        availability: (backendData.disponibilidad as string) || '',
        keywords: [...skills, ...softSkills].join(', '),
        categoria: (backendData.categoria as string) || 'Sin categoría',
        motivation: (backendData.resumen_profesional as string) || ''
    };
}

const UploadCV: React.FC<UploadCVProps> = ({ onSuccess, jobId, parsedCvData }) => {
    const { t } = useLanguage();
    const [file, setFile] = useState<File | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [parsedData, setParsedData] = useState<ParsedCVDataExpanded | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [confirmedData, setConfirmedData] = useState<ParsedCVDataExpanded | null>(null);
    const [savedCandidate, setSavedCandidate] = useState<Candidate | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const dropRef = useRef<HTMLDivElement>(null);

    const [isDragOver, setIsDragOver] = useState(false);

    // Si recibimos datos pre-procesados, mostrar el modal inmediatamente
    useEffect(() => {
        console.log('[UploadCV] parsedCvData recibido:', parsedCvData);
        if (parsedCvData) {
            console.log('[UploadCV] Mapeando datos del backend...');
            const mappedData = mapBackendDataToFrontend(parsedCvData);
            console.log('[UploadCV] Datos mapeados:', mappedData);
            setParsedData(mappedData);
            setIsModalOpen(true);
        }
    }, [parsedCvData]);

    // Función para simular el progreso de carga
    const simulateProgress = useCallback(() => {
        setProgress(0);
        const interval = setInterval(() => {
            setProgress(prev => {
                if (prev >= 90) {
                    clearInterval(interval);
                    return 90;
                }
                return prev + Math.random() * 10;
            });
        }, 200);
        return interval;
    }, []);

    // Función para extraer texto de PDF con lazy loading
    const extractTextFromPDF = async (file: File): Promise<string> => {
        try {
            const arrayBuffer = await file.arrayBuffer();
            const pdfModule = await loadPdfJs();
            const pdf = await pdfModule.getDocument({ data: arrayBuffer }).promise;
            let fullText = '';

            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                const pageText = textContent.items
                    .map((item) => 'str' in item && typeof item.str === 'string' ? item.str : '')
                    .join(' ');
                fullText += pageText + ' ';
            }

            return fullText;
        } catch (error) {
            console.error('Error al extraer texto del PDF:', error);
            throw new Error('No se pudo procesar el archivo PDF');
        }
    };

    // Función para parsear el CV (simulada con patrones básicos)
    const parseCV = (text: string): ParsedCVDataExpanded => {
        // Patrones para extraer información
        const emailPattern = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
        const phonePattern = /(?:\+34|0034|34)?[6789]\d{8}|\d{3}[-.\s]?\d{3}[-.\s]?\d{3}/g;
        const namePattern = /^([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)/m;

        // Extraer información básica
        const emails = text.match(emailPattern) || [];
        const phones = text.match(phonePattern) || [];
        const nameMatch = text.match(namePattern);

        // Buscar palabras clave para skills
        const skillKeywords = [
            'React', 'JavaScript', 'TypeScript', 'Node.js', 'Python', 'Java', 'PHP',
            'HTML', 'CSS', 'SQL', 'MongoDB', 'PostgreSQL', 'Git', 'Docker',
            'AWS', 'Azure', 'Kubernetes', 'Angular', 'Vue.js', 'Express',
            'Spring', 'Django', 'Flask', 'Laravel', 'Redux', 'GraphQL', 'REST API'
        ];

        const foundSkills = skillKeywords.filter(skill =>
            text.toLowerCase().includes(skill.toLowerCase())
        );

        // Buscar idiomas
        const languageKeywords = ['Español', 'Inglés', 'Francés', 'Alemán', 'Italiano', 'Portugués'];
        const foundLanguages: LanguageData[] = languageKeywords
            .filter(lang => text.toLowerCase().includes(lang.toLowerCase()))
            .map(lang => ({ language: lang, level: 'Intermedio' }));

        // Determinar categoría basada en las skills
        let categoria = 'Tecnología';
        if (foundSkills.some(skill => ['React', 'JavaScript', 'TypeScript', 'Angular', 'Vue.js'].includes(skill))) {
            categoria = 'Frontend Developer';
        } else if (foundSkills.some(skill => ['Node.js', 'Python', 'Java', 'PHP', 'Express', 'Django'].includes(skill))) {
            categoria = 'Backend Developer';
        } else if (foundSkills.some(skill => ['AWS', 'Azure', 'Docker', 'Kubernetes'].includes(skill))) {
            categoria = 'DevOps';
        }

        return {
            personalInfo: {
                name: nameMatch && typeof nameMatch[1] === 'string' ? nameMatch[1] : 'Nombre no detectado',
                email: emails[0] || 'Email no detectado',
                phone: phones[0] || 'Teléfono no detectado',
                address: 'Dirección no detectada'
            },
            education: [{
                degree: 'Grado detectado automáticamente',
                institution: 'Institución detectada automáticamente',
                startDate: '2020',
                endDate: '2023'
            }],
            experience: [{
                position: 'Posición detectada automáticamente',
                company: 'Empresa detectada automáticamente',
                startDate: '2020',
                endDate: '2023',
                description: 'Descripción extraída automáticamente del CV'
            }],
            skills: foundSkills,
            softSkills: ['Comunicación', 'Trabajo en equipo'],
            languages: foundLanguages,
            certifications: [],
            references: [],
            interests: [],
            additionalSkills: [],
            professionalSummary: 'Resumen profesional extraído automáticamente',
            keywords: foundSkills.join(', '),
            categoria
        };
    };

    // Función para manejar la subida de archivo
    const handleFileUpload = async (selectedFile: File) => {
        if (!selectedFile) return;

        if (selectedFile.type !== 'application/pdf') {
            alert('Por favor, sube un archivo PDF');
            return;
        }

        if (selectedFile.size > 5 * 1024 * 1024) {
            alert('El archivo es demasiado grande. Máximo 5MB');
            return;
        }

        setFile(selectedFile);
        setIsLoading(true);
        const progressInterval = simulateProgress();

        try {
            const text = await extractTextFromPDF(selectedFile);
            const parsed = parseCV(text);

            clearInterval(progressInterval);
            setProgress(100);

            setTimeout(() => {
                setParsedData(parsed);
                setIsModalOpen(true);
                setIsLoading(false);
                setProgress(0);
            }, 500);

        } catch (error) {
            clearInterval(progressInterval);
            setIsLoading(false);
            setProgress(0);
            console.error('Error al procesar el archivo:', error);
            alert('Error al procesar el archivo. Inténtalo de nuevo.');
        }
    };

    // Manejar la confirmación de datos
    const handleConfirmData = async () => {
        if (!confirmedData) return;

        setIsLoading(true);

        try {
            // Preparar los datos en el formato que espera save_v2.php (campos de Groq)
            const candidateData = {
                // Campos básicos requeridos
                nombre: confirmedData.personalInfo.name,
                email: confirmedData.personalInfo.email,
                telefono: confirmedData.personalInfo.phone,
                ubicacion_actual: confirmedData.personalInfo.address,
                fecha_nacimiento: confirmedData.personalInfo.dateOfBirth || null,
                linkedin: confirmedData.personalInfo.linkedinUrl || null,
                portfolio: confirmedData.personalInfo.portfolio || null,
                otras_redes: confirmedData.personalInfo.otherNetworks || [],

                // Resumen y habilidades
                resumen_profesional: confirmedData.professionalSummary,
                soft_skills: confirmedData.softSkills,
                hard_skills: confirmedData.skills,
                habilidades_adicionales: confirmedData.additionalSkills,

                // Idiomas en formato correcto
                idiomas: confirmedData.languages.map(lang => ({
                    idioma: lang.language,
                    nivel: lang.level
                })),

                // Experiencia laboral
                puestos_anteriores: confirmedData.experience.map(exp => ({
                    puesto: exp.position,
                    empresa: exp.company,
                    fecha_inicio: exp.startDate,
                    fecha_fin: exp.endDate,
                    descripcion: exp.description,
                    responsabilidades: exp.responsibilities || [],
                    ubicacion: exp.location || null,
                    actual: exp.current || false
                })),

                // Educación
                educacion: confirmedData.education.map(edu => ({
                    titulo: edu.degree,
                    institucion: edu.institution,
                    campo_estudio: edu.fieldOfStudy || '',
                    fecha_inicio: edu.startDate || null,
                    fecha_fin: edu.endDate || null,
                    nivel_educativo: edu.educationLevel || '',
                    descripcion: edu.description || null
                })),

                // Certificaciones
                certificaciones_detalle: confirmedData.certifications.map(cert => ({
                    nombre_certificacion: cert.name,
                    emisor: cert.issuer,
                    fecha_emision: cert.issueDate || null,
                    fecha_expiracion: cert.expirationDate || null
                })),

                // Referencias
                referencias_detalle: confirmedData.references.map(ref => ({
                    nombre_referencia: ref.name,
                    empresa_referencia: ref.company,
                    email_referencia: ref.email,
                    telefono_referencia: ref.phone,
                    notas: ref.notes || ''
                })),

                // Información adicional
                intereses: confirmedData.interests,
                disponibilidad: confirmedData.availability || null,
                categoria: confirmedData.categoria,

                // Metadatos
                data_source: 'ai_processing',
                routing: {
                    fuente: 'ai',
                    razon: 'Procesado automáticamente por Groq/Llama3',
                    fecha_asignacion: new Date().toISOString()
                }
            };

            console.log('[UploadCV] Enviando datos al backend:', candidateData);

            // Registrar el candidato usando el endpoint específico para IA
            const result = await saveCandidateFromAI(candidateData) as { success: boolean; data?: { id: string }; message?: string };

            console.log('[UploadCV] Respuesta del backend:', result);

            if (result.success) {
                // Si hay un archivo, subirlo también
                if (file && result.data?.id) {
                    try {
                        await uploadCV(file, result.data.id);
                    } catch (uploadError) {
                        console.warn('Error al subir el archivo CV:', uploadError);
                        // No fallamos completamente si no se puede subir el archivo
                    }
                }

                // Mostrar mensaje de éxito
                const Toast = () => {
                    const toast = document.createElement('div');
                    toast.className = 'fixed bottom-4 left-4 bg-green-500 text-white p-4 rounded-md shadow-lg transition-opacity duration-500';
                    toast.style.zIndex = 'var(--z-toast)';
                    toast.innerHTML = `
                        <div class="flex flex-col">
                            <div class="font-bold mb-1">¡Candidato guardado correctamente!</div>
                            <div>Nombre: ${candidateData.nombre}</div>
                            <div>Email: ${candidateData.email}</div>
                            <div>ID asignado: ${result.data ? result.data.id : 'N/A'}</div>
                        </div>
                    `;
                    document.body.appendChild(toast);

                    setTimeout(() => {
                        toast.style.opacity = '0';
                        setTimeout(() => document.body.removeChild(toast), 500);
                    }, 5000);
                };

                Toast();

                // Crear objeto candidato para el estado local
                let newCandidate: Candidate | undefined = undefined;
                if (result.data) {
                    newCandidate = {
                        id: result.data.id,
                        name: candidateData.nombre,
                        email: candidateData.email,
                        phone: candidateData.telefono || '',
                        address: confirmedData.personalInfo.address,
                        linkedinUrl: candidateData.linkedin,
                        portfolio: candidateData.portfolio,
                        education: confirmedData.education.map(edu =>
                            `${edu.degree} en ${edu.institution} (${edu.endDate || edu.startDate || 'Sin fecha'})`
                        ).join('; '),
                        experience: confirmedData.experience.map(exp =>
                            `${exp.position} en ${exp.company} (${exp.startDate} - ${exp.endDate}): ${exp.description}`
                        ).join('; '),
                        skills: candidateData.hard_skills.join(', '),
                        languages: candidateData.idiomas.map((lang: any) => `${lang.idioma} (${lang.nivel})`).join(', '),
                        cvFile: file?.name || '',
                        motivation: candidateData.resumen_profesional,
                        keywords: confirmedData.keywords,
                        categoria: confirmedData.categoria,
                        appliedJobId: jobId || '',
                        dateApplied: new Date().toISOString().split('T')[0] || '',
                        status: 'Received',
                        consentimiento_gdpr: true,
                        residencePermit: 'Sí'
                    };
                }
                if (!newCandidate) return;
                setSavedCandidate(newCandidate);
                setIsModalOpen(false);
                if (onSuccess) {
                    onSuccess(newCandidate);
                }

                setSavedCandidate(newCandidate);
                setIsModalOpen(false);

                // Llamar al callback si existe
                if (onSuccess) {
                    onSuccess(newCandidate);
                }

                console.log('Candidato registrado exitosamente:', result);
            } else {
                throw new Error(result.message || 'Error al registrar el candidato');
            }
        } catch (error) {
            console.error('Error al guardar candidato:', error);
            alert('Error al guardar el candidato. Inténtalo de nuevo.');
        } finally {
            setIsLoading(false);
        }
    };

    // Función para manejar el drag and drop
    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOver(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOver(false);
    }, []);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        setIsDragOver(false);

        const droppedFiles = Array.from(e.dataTransfer.files);
        if (droppedFiles.length > 0 && droppedFiles[0] instanceof File) {
            handleFileUpload(droppedFiles[0]);
        }
    }, []);

    // Función para abrir el selector de archivos
    const handleClick = () => {
        fileInputRef.current?.click();
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile) {
            handleFileUpload(selectedFile);
        }
    };

    return (
        <div>
            <div className="max-w-2xl mx-auto p-6">
                <div className="bg-white rounded-lg shadow-lg p-8">
                    <h2 className="text-2xl font-bold text-center mb-6 text-gray-800">
                        {t('dashboard.uploadCvTitle')}
                    </h2>

                {!savedCandidate ? (
                    <>
                        {/* Área de subida */}
                        <div
                            ref={dropRef}
                            onClick={handleClick}
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onDrop={handleDrop}
                            className={`
                                border-2 border-dashed rounded-lg p-8 text-center cursor-pointer
                                transition-all duration-300 ease-in-out
                                ${isDragOver
                                    ? 'border-blue-500 bg-blue-50'
                                    : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'
                                }
                                ${isLoading ? 'pointer-events-none opacity-50' : ''}
                            `}
                        >
                            {isLoading ? (
                                <div className="space-y-4">
                                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                                    <p className="text-gray-600">{t('dashboard.processingCv')}</p>
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div
                                            className="bg-blue-500 h-2 rounded-full transition-all duration-300"
                                            style={{ width: `${progress}%` }}
                                        ></div>
                                    </div>
                                    <p className="text-sm text-gray-500">{progress.toFixed(0)}%</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <Upload className="h-12 w-12 text-gray-400 mx-auto" />
                                    <div>
                                        <p className="text-lg font-medium text-gray-900">
                                            {t('dashboard.dragAndDropCv')}
                                        </p>
                                        <p className="text-gray-500">
                                            {t('dashboard.clickToSelectFile')}
                                        </p>
                                    </div>
                                    <p className="text-sm text-gray-400">
                                        {t('dashboard.onlyPdfFiles')}
                                    </p>
                                </div>
                            )}
                        </div>

                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".pdf"
                            onChange={handleFileSelect}
                            className="hidden"
                        />

                        {file && !isLoading && (
                            <div className="mt-4 p-4 bg-gray-50 rounded-lg border">
                                <div className="flex items-center space-x-3">
                                    <FileText className="h-6 w-6 text-blue-500" />
                                    <div className="flex-1">
                                        <p className="font-medium text-gray-900">{file.name}</p>
                                        <p className="text-sm text-gray-500">
                                            {(file.size / 1024 / 1024).toFixed(2)} MB
                                        </p>
                                    </div>
                                    <button
                                        onClick={() => setFile(null)}
                                        className="text-gray-400 hover:text-gray-600"
                                    >
                                        <X className="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        )}
                    </>
                ) : (
                    /* Mensaje de éxito */
                    <div className="text-center space-y-4">
                        <CheckCircle className="h-16 w-16 text-green-500 mx-auto" />
                        <h3 className="text-xl font-semibold text-gray-900">
                            ¡CV procesado exitosamente!
                        </h3>
                        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                            <p className="text-green-800">
                                <span className="font-medium">Candidato:</span> {savedCandidate.name}
                            </p>
                            <p className="text-green-800">
                                <span className="font-medium">Email:</span> {savedCandidate.email}
                            </p>
                            <p className="text-green-800">
                                <span className="font-medium">ID:</span> {savedCandidate.id}
                            </p>
                        </div>
                        <button
                            onClick={() => {
                                setFile(null);
                                setParsedData(null);
                                setSavedCandidate(null);
                                setConfirmedData(null);
                            }}
                            className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors"
                        >
                            Subir otro CV
                        </button>
                    </div>
                )}
            </div>

            {/* Modal de confirmación EXPANDIDO - Todos los campos del backend */}
            {isModalOpen && parsedData && (
                <div
                    className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4"
                    style={{ zIndex: 'var(--z-modal-backdrop)' }}
                >
                    <div className="bg-white rounded-lg max-w-6xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-xl font-semibold text-gray-900">
                                    Confirma TODOS los datos extraídos del CV
                                </h3>
                                <button
                                    onClick={() => setIsModalOpen(false)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <X className="h-6 w-6" />
                                </button>
                            </div>

                            <div className="space-y-6">
                                {/* Información Personal EXPANDIDA */}
                                <div className="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <User className="h-5 w-5 text-blue-600" />
                                        <h4 className="font-medium text-gray-900">Información Personal</h4>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Nombre Completo</label>
                                            <input
                                                type="text"
                                                value={parsedData.personalInfo.name}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, name: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input
                                                type="email"
                                                value={parsedData.personalInfo.email}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, email: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                            <input
                                                type="tel"
                                                value={parsedData.personalInfo.phone}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, phone: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Ubicación Actual</label>
                                            <input
                                                type="text"
                                                value={parsedData.personalInfo.address}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, address: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Fecha Nacimiento</label>
                                            <input
                                                type="date"
                                                value={parsedData.personalInfo.dateOfBirth || ''}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, dateOfBirth: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">LinkedIn</label>
                                            <input
                                                type="url"
                                                value={parsedData.personalInfo.linkedinUrl || ''}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, linkedinUrl: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div className="col-span-full">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Portfolio/Website</label>
                                            <input
                                                type="url"
                                                value={parsedData.personalInfo.portfolio || ''}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, portfolio: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div className="col-span-full">
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Otras Redes Sociales</label>
                                            <input
                                                type="text"
                                                value={parsedData.personalInfo.otherNetworks?.join(', ') || ''}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: {
                                                        ...parsedData.personalInfo,
                                                        otherNetworks: e.target.value.split(', ').filter(s => s.trim())
                                                    }
                                                })}
                                                placeholder="Twitter, GitHub, otros (separados por coma)"
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Resumen Profesional */}
                                <div className="bg-green-50 p-4 rounded-lg border border-green-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <FileText className="h-5 w-5 text-green-600" />
                                        <h4 className="font-medium text-gray-900">Resumen Profesional</h4>
                                    </div>
                                    <textarea
                                        value={parsedData.professionalSummary}
                                        onChange={(e) => setParsedData({
                                            ...parsedData,
                                            professionalSummary: e.target.value
                                        })}
                                        rows={4}
                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 resize-none"
                                        placeholder="Resumen profesional extraído del CV"
                                    />
                                </div>

                                {/* Habilidades EXPANDIDAS */}
                                <div className="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <Briefcase className="h-5 w-5 text-yellow-600" />
                                        <h4 className="font-medium text-gray-900">Habilidades y Competencias</h4>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Hard Skills (Técnicas)</label>
                                            <textarea
                                                value={parsedData.skills.join(', ')}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    skills: e.target.value.split(', ').filter(s => s.trim())
                                                })}
                                                rows={3}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-yellow-500 resize-none"
                                                placeholder="React, JavaScript, Python, etc."
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Soft Skills</label>
                                            <textarea
                                                value={parsedData.softSkills.join(', ')}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    softSkills: e.target.value.split(', ').filter(s => s.trim())
                                                })}
                                                rows={3}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-yellow-500 resize-none"
                                                placeholder="Liderazgo, Comunicación, Trabajo en equipo, etc."
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Habilidades Adicionales</label>
                                            <textarea
                                                value={parsedData.additionalSkills.join(', ')}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    additionalSkills: e.target.value.split(', ').filter(s => s.trim())
                                                })}
                                                rows={2}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-yellow-500 resize-none"
                                                placeholder="Otras habilidades relevantes"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Categoría Profesional</label>
                                            <select
                                                value={parsedData.categoria}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    categoria: e.target.value
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-yellow-500"
                                            >
                                                <option value="Frontend Developer">Frontend Developer</option>
                                                <option value="Backend Developer">Backend Developer</option>
                                                <option value="Full Stack Developer">Full Stack Developer</option>
                                                <option value="DevOps">DevOps</option>
                                                <option value="Data Scientist">Data Scientist</option>
                                                <option value="Mobile Developer">Mobile Developer</option>
                                                <option value="QA Engineer">QA Engineer</option>
                                                <option value="UX/UI Designer">UX/UI Designer</option>
                                                <option value="Marketing">Marketing</option>
                                                <option value="Administración">Administración</option>
                                                <option value="Tecnología">Tecnología</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {/* Idiomas */}
                                <div className="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <User className="h-5 w-5 text-purple-600" />
                                        <h4 className="font-medium text-gray-900">Idiomas</h4>
                                    </div>
                                    <div className="space-y-3">
                                        {parsedData.languages.map((lang, index) => (
                                            <div key={index} className="grid grid-cols-2 gap-3">
                                                <input
                                                    type="text"
                                                    value={lang.language}
                                                    onChange={(e) => {
                                                        const newLanguages = [...parsedData.languages];
                                                        newLanguages[index] = { ...lang, language: e.target.value };
                                                        setParsedData({
                                                            ...parsedData,
                                                            languages: newLanguages
                                                        });
                                                    }}
                                                    placeholder="Idioma"
                                                    className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500"
                                                />
                                                <select
                                                    value={lang.level}
                                                    onChange={(e) => {
                                                        const newLanguages = [...parsedData.languages];
                                                        newLanguages[index] = { ...lang, level: e.target.value };
                                                        setParsedData({
                                                            ...parsedData,
                                                            languages: newLanguages
                                                        });
                                                    }}
                                                    className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500"
                                                >
                                                    <option value="Básico">Básico</option>
                                                    <option value="Intermedio">Intermedio</option>
                                                    <option value="Avanzado">Avanzado</option>
                                                    <option value="Nativo">Nativo</option>
                                                </select>
                                            </div>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={() => setParsedData({
                                                ...parsedData,
                                                languages: [...parsedData.languages, { language: '', level: 'Intermedio' }]
                                            })}
                                            className="text-purple-600 hover:text-purple-800 text-sm font-medium"
                                        >
                                            + Agregar idioma
                                        </button>
                                    </div>
                                </div>

                                {/* Experiencia Laboral */}
                                <div className="bg-orange-50 p-4 rounded-lg border border-orange-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <Briefcase className="h-5 w-5 text-orange-600" />
                                        <h4 className="font-medium text-gray-900">Experiencia Laboral</h4>
                                    </div>
                                    <div className="space-y-4">
                                        {parsedData.experience.map((exp, index) => (
                                            <div key={index} className="p-3 bg-white rounded border">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                                    <input
                                                        type="text"
                                                        value={exp.position}
                                                        onChange={(e) => {
                                                            const newExp = [...parsedData.experience];
                                                            newExp[index] = { ...exp, position: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                experience: newExp
                                                            });
                                                        }}
                                                        placeholder="Puesto"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500"
                                                    />
                                                    <input
                                                        type="text"
                                                        value={exp.company}
                                                        onChange={(e) => {
                                                            const newExp = [...parsedData.experience];
                                                            newExp[index] = { ...exp, company: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                experience: newExp
                                                            });
                                                        }}
                                                        placeholder="Empresa"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500"
                                                    />
                                                    <input
                                                        type="text"
                                                        value={exp.startDate}
                                                        onChange={(e) => {
                                                            const newExp = [...parsedData.experience];
                                                            newExp[index] = { ...exp, startDate: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                experience: newExp
                                                            });
                                                        }}
                                                        placeholder="Fecha inicio"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500"
                                                    />
                                                    <input
                                                        type="text"
                                                        value={exp.endDate}
                                                        onChange={(e) => {
                                                            const newExp = [...parsedData.experience];
                                                            newExp[index] = { ...exp, endDate: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                experience: newExp
                                                            });
                                                        }}
                                                        placeholder="Fecha fin"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500"
                                                    />
                                                </div>
                                                <textarea
                                                    value={exp.description}
                                                    onChange={(e) => {
                                                        const newExp = [...parsedData.experience];
                                                        newExp[index] = { ...exp, description: e.target.value };
                                                        setParsedData({
                                                            ...parsedData,
                                                            experience: newExp
                                                        });
                                                    }}
                                                    placeholder="Descripción del puesto"
                                                    rows={2}
                                                    className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 resize-none"
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Educación */}
                                <div className="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <FileText className="h-5 w-5 text-indigo-600" />
                                        <h4 className="font-medium text-gray-900">Educación</h4>
                                    </div>
                                    <div className="space-y-4">
                                        {parsedData.education.map((edu, index) => (
                                            <div key={index} className="p-3 bg-white rounded border">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <input
                                                        type="text"
                                                        value={edu.degree}
                                                        onChange={(e) => {
                                                            const newEdu = [...parsedData.education];
                                                            newEdu[index] = { ...edu, degree: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                education: newEdu
                                                            });
                                                        }}
                                                        placeholder="Título/Grado"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                                                    />
                                                    <input
                                                        type="text"
                                                        value={edu.institution}
                                                        onChange={(e) => {
                                                            const newEdu = [...parsedData.education];
                                                            newEdu[index] = { ...edu, institution: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                education: newEdu
                                                            });
                                                        }}
                                                        placeholder="Institución"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                                                    />
                                                    <input
                                                        type="text"
                                                        value={edu.fieldOfStudy || ''}
                                                        onChange={(e) => {
                                                            const newEdu = [...parsedData.education];
                                                            newEdu[index] = { ...edu, fieldOfStudy: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                education: newEdu
                                                            });
                                                        }}
                                                        placeholder="Campo de estudio"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                                                    />
                                                    <input
                                                        type="text"
                                                        value={edu.endDate || edu.startDate || ''}
                                                        onChange={(e) => {
                                                            const newEdu = [...parsedData.education];
                                                            newEdu[index] = { ...edu, endDate: e.target.value };
                                                            setParsedData({
                                                                ...parsedData,
                                                                education: newEdu
                                                            });
                                                        }}
                                                        placeholder="Año de finalización"
                                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500"
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>


                                {/* Certificaciones */}
                                {parsedData.certifications.length > 0 && (
                                    <div className="bg-pink-50 p-4 rounded-lg border border-pink-200">
                                        <div className="flex items-center space-x-2 mb-3">
                                            <CheckCircle className="h-5 w-5 text-pink-600" />
                                            <h4 className="font-medium text-gray-900">Certificaciones</h4>
                                        </div>
                                        <div className="space-y-3">
                                            {parsedData.certifications.map((cert, index) => (
                                                <div key={index} className="p-3 bg-white rounded border">
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                        <input
                                                            type="text"
                                                            value={cert.name}
                                                            placeholder="Nombre de la certificación"
                                                            className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-pink-500"
                                                            readOnly
                                                        />
                                                        <input
                                                            type="text"
                                                            value={cert.issuer}
                                                            placeholder="Emisor"
                                                            className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-pink-500"
                                                            readOnly
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Intereses y Disponibilidad */}
                                <div className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <Heart className="h-5 w-5 text-gray-600" />
                                        <h4 className="font-medium text-gray-900">Información Adicional</h4>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Intereses</label>
                                            <textarea
                                                value={parsedData.interests.join(', ')}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    interests: e.target.value.split(', ').filter(s => s.trim())
                                                })}
                                                rows={2}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500 resize-none"
                                                placeholder="Intereses profesionales y personales"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Disponibilidad</label>
                                            <input
                                                type="text"
                                                value={parsedData.availability || ''}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    availability: e.target.value
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-gray-500"
                                                placeholder="Inmediata, 2 semanas, etc."
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Motivación/Carta de Presentación */}
                                <div className="bg-green-50 p-4 rounded-lg border border-green-200">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <Heart className="h-5 w-5 text-green-600" />
                                        <h4 className="font-medium text-gray-900">Motivación (Opcional)</h4>
                                    </div>
                                    <textarea
                                        value={parsedData.motivation || ''}
                                        onChange={(e) => setParsedData({
                                            ...parsedData,
                                            motivation: e.target.value
                                        })}
                                        placeholder="¿Qué te motiva a aplicar a esta posición? ¿Por qué deberíamos considerarte?"
                                        rows={4}
                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 resize-none"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end space-x-4 mt-8 pt-6 border-t">
                                <button
                                    onClick={() => setIsModalOpen(false)}
                                    className="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors border border-gray-300 rounded-lg hover:bg-gray-50"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={() => {
                                        setConfirmedData(parsedData);
                                        handleConfirmData();
                                    }}
                                    disabled={isLoading}
                                    className="bg-blue-500 hover:bg-blue-600 text-white px-8 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {isLoading ? 'Guardando...' : 'Confirmar y Guardar Todos los Datos'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            </div>
        </div>
    );
};

export default UploadCV;
