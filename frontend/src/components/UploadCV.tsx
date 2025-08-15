import React, { useState, useRef, useCallback } from 'react';
import { Upload, X, FileText, User, Briefcase, Heart, CheckCircle } from 'lucide-react';
import { registerCandidate, uploadCV } from '../lib/apiService';
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist';
import { useLanguage } from '../lib/i18n/LanguageContext';

// Configurar el worker para PDF
GlobalWorkerOptions.workerSrc = `//cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js`;

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
}

const UploadCV: React.FC<UploadCVProps> = ({ onSuccess, jobId }) => {
    const { t } = useLanguage();
    const [file, setFile] = useState<File | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [parsedData, setParsedData] = useState<ParsedCVData | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [confirmedData, setConfirmedData] = useState<ParsedCVData | null>(null);
    const [savedCandidate, setSavedCandidate] = useState<Candidate | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const dropRef = useRef<HTMLDivElement>(null);

    const [isDragOver, setIsDragOver] = useState(false);

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

    // Función para extraer texto de PDF
    const extractTextFromPDF = async (file: File): Promise<string> => {
        try {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await getDocument({ data: arrayBuffer }).promise;
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
    const parseCV = (text: string): ParsedCVData => {
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
        const foundLanguages = languageKeywords.filter(lang =>
            text.toLowerCase().includes(lang.toLowerCase())
        );

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
                year: '2020'
            }],
            experience: [{
                position: 'Posición detectada automáticamente',
                company: 'Empresa detectada automáticamente',
                startDate: '2020',
                endDate: '2023',
                description: 'Descripción extraída automáticamente del CV'
            }],
            skills: foundSkills,
            languages: foundLanguages,
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
            // Preparar los datos del candidato para el registro
            const candidateData = {
                name: confirmedData.personalInfo.name,
                email: confirmedData.personalInfo.email,
                phone: confirmedData.personalInfo.phone,
                linkedin: confirmedData.personalInfo.linkedinUrl || '',
                website: confirmedData.personalInfo.portfolio || '',
                skills: confirmedData.skills, // Array de strings según el tipo
                languages: confirmedData.languages, // Array de strings según el tipo
                motivation: confirmedData.motivation || '',
                consentimiento_gdpr: true,
                areaOfInterest: confirmedData.categoria,
                // Campos adicionales basados en el CV parseado
                educationLevel: confirmedData.education[0]?.degree || 'No especificado',
                lastPosition: confirmedData.experience[0]?.position || 'No especificado',
                lastCompany: confirmedData.experience[0]?.company || 'No especificado',
                residencePermit: 'Sí'
            };

            // Registrar el candidato usando la API real
            const result = await registerCandidate(candidateData) as { success: boolean; data?: { id: string }; message?: string };

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
                    toast.className = 'fixed bottom-4 left-4 bg-green-500 text-white p-4 rounded-md shadow-lg z-50 transition-opacity duration-500';
                    toast.innerHTML = `
                        <div class="flex flex-col">
                            <div class="font-bold mb-1">¡Candidato guardado correctamente!</div>
                            <div>Nombre: ${candidateData.name}</div>
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
                        name: candidateData.name,
                        email: candidateData.email,
                        phone: candidateData.phone || '',
                        address: confirmedData.personalInfo.address,
                        linkedinUrl: candidateData.linkedin,
                        portfolio: candidateData.website,
                        education: confirmedData.education.map(edu =>
                            `${edu.degree} en ${edu.institution} (${edu.year})`
                        ).join('; '),
                        experience: confirmedData.experience.map(exp =>
                            `${exp.position} en ${exp.company} (${exp.startDate} - ${exp.endDate}): ${exp.description}`
                        ).join('; '),
                        skills: candidateData.skills.join(', '),
                        languages: candidateData.languages.join(', '),
                        cvFile: file?.name || '',
                        motivation: candidateData.motivation,
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
        if (droppedFiles.length > 0) {
            if (droppedFiles[0]) {
                handleFileUpload(droppedFiles[0]);
            }
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

            {/* Modal de confirmación */}
            {isModalOpen && parsedData && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-xl font-semibold text-gray-900">
                                    Confirma los datos extraídos
                                </h3>
                                <button
                                    onClick={() => setIsModalOpen(false)}
                                    className="text-gray-400 hover:text-gray-600"
                                >
                                    <X className="h-6 w-6" />
                                </button>
                            </div>

                            <div className="space-y-6">
                                {/* Información Personal */}
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <User className="h-5 w-5 text-blue-500" />
                                        <h4 className="font-medium text-gray-900">Información Personal</h4>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Nombre
                                            </label>
                                            <input
                                                type="text"
                                                value={parsedData.personalInfo.name}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, name: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Email
                                            </label>
                                            <input
                                                type="email"
                                                value={parsedData.personalInfo.email}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, email: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Teléfono
                                            </label>
                                            <input
                                                type="tel"
                                                value={parsedData.personalInfo.phone}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, phone: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Dirección
                                            </label>
                                            <input
                                                type="text"
                                                value={parsedData.personalInfo.address}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    personalInfo: { ...parsedData.personalInfo, address: e.target.value }
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Skills y Categoría */}
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <Briefcase className="h-5 w-5 text-blue-500" />
                                        <h4 className="font-medium text-gray-900">Habilidades y Categoría</h4>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Habilidades
                                            </label>
                                            <input
                                                type="text"
                                                value={parsedData.skills.join(', ')}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    skills: e.target.value.split(', ').filter(s => s.trim())
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                                Categoría
                                            </label>
                                            <select
                                                value={parsedData.categoria}
                                                onChange={(e) => setParsedData({
                                                    ...parsedData,
                                                    categoria: e.target.value
                                                })}
                                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            >
                                                <option value="Frontend Developer">Frontend Developer</option>
                                                <option value="Backend Developer">Backend Developer</option>
                                                <option value="Full Stack Developer">Full Stack Developer</option>
                                                <option value="DevOps">DevOps</option>
                                                <option value="Data Scientist">Data Scientist</option>
                                                <option value="Mobile Developer">Mobile Developer</option>
                                                <option value="QA Engineer">QA Engineer</option>
                                                <option value="Tecnología">Tecnología</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {/* Motivación */}
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <div className="flex items-center space-x-2 mb-3">
                                        <Heart className="h-5 w-5 text-blue-500" />
                                        <h4 className="font-medium text-gray-900">Motivación (Opcional)</h4>
                                    </div>
                                    <textarea
                                        value={parsedData.motivation || ''}
                                        onChange={(e) => setParsedData({
                                            ...parsedData,
                                            motivation: e.target.value
                                        })}
                                        placeholder="¿Qué te motiva a aplicar a esta posición?"
                                        rows={3}
                                        className="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end space-x-4 mt-6">
                                <button
                                    onClick={() => setIsModalOpen(false)}
                                    className="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={() => {
                                        setConfirmedData(parsedData);
                                        handleConfirmData();
                                    }}
                                    disabled={isLoading}
                                    className="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors disabled:opacity-50"
                                >
                                    {isLoading ? 'Guardando...' : 'Confirmar y Guardar'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default UploadCV;
