
import React, { useState } from 'react';
// Importa DOMPurify para sanitizar entradas de texto y prevenir XSS
import DOMPurify from 'dompurify';

// Función utilitaria para validar y sanitizar campos
const sanitizeInput = (value: string) => DOMPurify.sanitize(value.trim());
const isValidEmail = (email: string) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
const isValidUrl = (url: string) => {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
};
const isValidPhone = (phone: string) => /^[\d\s\-\+\(\)]{7,20}$/.test(phone);

// Mensajes de error personalizados y seguros
const getErrorMessage = (field: string, type: string) => {
    switch (type) {
        case 'required':
            return `El campo ${field} es obligatorio.`;
        case 'email':
            return 'Introduce un email válido.';
        case 'url':
            return 'Introduce una URL válida (incluye https://).';
        case 'phone':
            return 'Introduce un teléfono válido.';
        case 'minlength':
            return `El campo ${field} es demasiado corto.`;
        case 'maxlength':
            return `El campo ${field} es demasiado largo.`;
        default:
            return 'Dato inválido. Revisa el campo.';
    }
};

// Componente de formulario completo para registro de candidatos

const initialState = {
    name: '', email: '', password: '', username: '', phone: '', country: '', city: '', gender: '', consentimiento_gdpr: false,
    jobPosition: '', yearsOfExperience: '', lastPosition: '', lastCompany: '',
    cv: null as File | null, linkedin: '', website: '', github: '', introVideo: '',
    educationLevel: '', institutionName: '', degree: '', educationStart: '', educationEnd: '',
    workHistory: '', skills: '', softSkills: '', skillsProficiency: '',
    languages: '', languagesProficiency: '', workModality: '', desiredSchedule: '', desiredSalaryMin: '', desiredSalaryMax: '', availabilityDate: '',
    portfolio: '', importLinkedin: false, keywords: '', residencePermit: '', areaOfInterest: '', toolsMastered: '', healthcareExperience: '', jobSearchType: '', motivation: ''
};

const CandidateForm = () => {
    const [form, setForm] = useState(initialState);
    const [errors, setErrors] = useState<{ [key: string]: string }>({});
    const [submitMsg, setSubmitMsg] = useState<string | null>(null);

    // Validación y sanitización de todos los campos
    const validate = () => {
        const newErrors: { [key: string]: string } = {};
        if (!form.name || form.name.length < 2) newErrors.name = getErrorMessage('Nombre', 'minlength');
        if (!form.email || !isValidEmail(form.email)) newErrors.email = getErrorMessage('Email', 'email');
        if (!form.password || form.password.length < 6) newErrors.password = getErrorMessage('Contraseña', 'minlength');
        if (form.phone && !isValidPhone(form.phone)) newErrors.phone = getErrorMessage('Teléfono', 'phone');
        if (form.linkedin && !isValidUrl(form.linkedin)) newErrors.linkedin = getErrorMessage('LinkedIn', 'url');
        if (form.website && !isValidUrl(form.website)) newErrors.website = getErrorMessage('Website', 'url');
        if (form.github && !isValidUrl(form.github)) newErrors.github = getErrorMessage('GitHub', 'url');
        if (form.introVideo && !isValidUrl(form.introVideo)) newErrors.introVideo = getErrorMessage('Video', 'url');
        if (form.portfolio && !isValidUrl(form.portfolio)) newErrors.portfolio = getErrorMessage('Portfolio', 'url');
        // ...agrega más validaciones según necesidad
        return newErrors;
    };

    // Maneja cambios en los campos, sanitizando texto
    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const target = e.target as HTMLInputElement | HTMLTextAreaElement;
        const { name, value, type } = target;
        const checked = (target as HTMLInputElement).checked;
        const files = (target as HTMLInputElement).files;
        let val: string = value;
        if (type === 'checkbox') val = checked ? 'true' : 'false';
        if (type === 'file') val = files && files[0] ? files[0].name : '';
        if (type === 'text' || type === 'textarea' || type === 'email' || type === 'url') val = sanitizeInput(value);
        setForm(prev => ({ ...prev, [name]: val }));
    };

    // Maneja el submit, validando y mostrando errores seguros
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitMsg(null);
        const validationErrors = validate();
        setErrors(validationErrors);
        if (Object.keys(validationErrors).length > 0) {
            setSubmitMsg('Corrige los errores antes de enviar.');
            return;
        }
        // Aquí podrías enviar los datos a una API segura o mostrar un mensaje de éxito
        setSubmitMsg('Formulario enviado correctamente.');
        // Limpia el formulario si lo deseas
        // setForm(initialState);
    };

    return (
        <div className="form-container" style={{
            background: '#fff',
            borderRadius: '10px',
            boxShadow: '0 2px 8px rgba(0, 0, 0, 0.07)',
            padding: '30px 24px',
            maxWidth: '1200px',
            margin: 'auto',
            fontFamily: "'Poppins', Arial, Helvetica, sans-serif"
        }}>
            <h1 style={{ color: '#f24495', marginBottom: 24 }}>Formulario Dinámico de Candidato</h1>
            <form id="formDinamico" onSubmit={handleSubmit} autoComplete="off" noValidate>
                {/* Validación y sanitización en cada campo. Los errores se muestran de forma segura. */}
                <div className="grupo">
                    <strong>Datos personales</strong>
                    <div className="campos-grupo">
                        <div className="campo">
                            <label>Nombre</label>
                            <input name="name" type="text" value={form.name} onChange={handleChange} minLength={2} maxLength={50} required />
                            {errors.name && <span className="error">{errors.name}</span>}
                        </div>
                        <div className="campo">
                            <label>Email</label>
                            <input name="email" type="email" value={form.email} onChange={handleChange} required />
                            {errors.email && <span className="error">{errors.email}</span>}
                        </div>
                        <div className="campo">
                            <label>Contraseña</label>
                            <input name="password" type="password" value={form.password} onChange={handleChange} minLength={6} required />
                            {errors.password && <span className="error">{errors.password}</span>}
                        </div>
                        <div className="campo">
                            <label>Usuario</label>
                            <input name="username" type="text" value={form.username} onChange={handleChange} maxLength={30} />
                        </div>
                        <div className="campo">
                            <label>Teléfono</label>
                            <input name="phone" type="text" value={form.phone} onChange={handleChange} maxLength={20} />
                            {errors.phone && <span className="error">{errors.phone}</span>}
                        </div>
                        <div className="campo">
                            <label>País</label>
                            <input name="country" type="text" value={form.country} onChange={handleChange} maxLength={40} />
                        </div>
                        <div className="campo">
                            <label>Ciudad</label>
                            <input name="city" type="text" value={form.city} onChange={handleChange} maxLength={40} />
                        </div>
                        <div className="campo">
                            <label>Género</label>
                            <input name="gender" type="text" value={form.gender} onChange={handleChange} maxLength={20} />
                        </div>
                        <div className="campo">
                            <label>Consentimiento GDPR</label>
                            <input name="consentimiento_gdpr" type="checkbox" checked={form.consentimiento_gdpr} onChange={handleChange} />
                        </div>
                    </div>
                </div>
                {/* ...repite la estructura para los demás grupos, aplicando validación y sanitización... */}
                <div className="grupo">
                    <strong>Documentos y enlaces</strong>
                    <div className="campos-grupo">
                        <div className="campo">
                            <label>CV (PDF)</label>
                            <input name="cv" type="file" accept=".pdf" onChange={handleChange} />
                        </div>
                        <div className="campo">
                            <label>LinkedIn</label>
                            <input name="linkedin" type="url" value={form.linkedin} onChange={handleChange} />
                            {errors.linkedin && <span className="error">{errors.linkedin}</span>}
                        </div>
                        <div className="campo">
                            <label>Website</label>
                            <input name="website" type="url" value={form.website} onChange={handleChange} />
                            {errors.website && <span className="error">{errors.website}</span>}
                        </div>
                        <div className="campo">
                            <label>GitHub</label>
                            <input name="github" type="url" value={form.github} onChange={handleChange} />
                            {errors.github && <span className="error">{errors.github}</span>}
                        </div>
                        <div className="campo">
                            <label>Video de presentación</label>
                            <input name="introVideo" type="url" value={form.introVideo} onChange={handleChange} />
                            {errors.introVideo && <span className="error">{errors.introVideo}</span>}
                        </div>
                    </div>
                </div>
                {/* ...continúa con los demás grupos, aplicando el mismo patrón... */}
                <div style={{ textAlign: 'center', marginTop: 24 }}>
                    <button type="submit">Enviar</button>
                </div>
                {submitMsg && <div className="submit-msg">{submitMsg}</div>}
            </form>
            {/*
                Sugerencias de mejora:
                - Implementar reCAPTCHA para evitar envíos automáticos.
                - Limitar tamaño de archivos subidos y validar tipo MIME.
                - Internacionalizar mensajes de error.
                - Usar un gestor de formularios como react-hook-form para mayor robustez.
                - Validar en backend si se conecta a una API real.
            */}
        </div>
    );
};

export default CandidateForm;
