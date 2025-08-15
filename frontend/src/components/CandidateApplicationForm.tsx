
import React, { useState } from "react";
import { submitApplication } from "../lib/apiService";
import DOMPurify from "dompurify";

// Funciones de validación y sanitización
const sanitizeInput = (value: string) => DOMPurify.sanitize(value.trim());
const isValidEmail = (email: string) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
const isValidUrl = (url: string) => {
    if (!url) return true; // Opcional
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
};

const getErrorMessage = (field: string, type: string) => {
    switch (type) {
        case 'required':
            return `El campo ${field} es obligatorio.`;
        case 'email':
            return 'Introduce un email válido.';
        case 'url':
            return 'Introduce una URL válida (incluye https://).';
        default:
            return 'Dato inválido. Revisa el campo.';
    }
};

const CandidateApplicationForm: React.FC = () => {
    const [nombre, setNombre] = useState("");
    const [email, setEmail] = useState("");
    const [jobId, setJobId] = useState("");
    const [cvUrl, setCvUrl] = useState("");
    const [result, setResult] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<{ [key: string]: string }>({});

    // Validación y sanitización de campos
    const validate = () => {
        const newErrors: { [key: string]: string } = {};
        if (!nombre || nombre.length < 2) newErrors.nombre = getErrorMessage('Nombre', 'required');
        if (!email || !isValidEmail(email)) newErrors.email = getErrorMessage('Email', 'email');
        if (!jobId) newErrors.jobId = getErrorMessage('ID del Puesto', 'required');
        if (cvUrl && !isValidUrl(cvUrl)) newErrors.cvUrl = getErrorMessage('URL del CV', 'url');
        return newErrors;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setResult(null);
        // Sanitiza entradas antes de enviar
        const safeNombre = sanitizeInput(nombre);
        const safeEmail = sanitizeInput(email);
        const safeJobId = sanitizeInput(jobId);
        const safeCvUrl = sanitizeInput(cvUrl);
        const validationErrors = validate();
        setErrors(validationErrors);
        if (Object.keys(validationErrors).length > 0) {
            setResult('Corrige los errores antes de enviar.');
            return;
        }
        setLoading(true);
        try {
            // Envía solo datos sanitizados
            const res = await submitApplication({ nombre: safeNombre, email: safeEmail, job_id: safeJobId, cv_url: safeCvUrl });
            setResult(res.message || "Postulación enviada correctamente.");
        } catch (err) {
            setResult("Error al enviar la postulación. Intenta de nuevo más tarde.");
        }
        setLoading(false);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4" autoComplete="off" noValidate>
            {/* Validación y sanitización en cada campo. Los errores se muestran de forma segura. */}
            <input
                type="text"
                placeholder="Nombre"
                value={nombre}
                onChange={e => setNombre(sanitizeInput(e.target.value))}
                required
                className="input"
                minLength={2}
                maxLength={50}
            />
            {errors.nombre && <span className="error">{errors.nombre}</span>}
            <input
                type="email"
                placeholder="Email"
                value={email}
                onChange={e => setEmail(sanitizeInput(e.target.value))}
                required
                className="input"
            />
            {errors.email && <span className="error">{errors.email}</span>}
            <input
                type="text"
                placeholder="ID del Puesto"
                value={jobId}
                onChange={e => setJobId(sanitizeInput(e.target.value))}
                required
                className="input"
                minLength={1}
                maxLength={30}
            />
            {errors.jobId && <span className="error">{errors.jobId}</span>}
            <input
                type="text"
                placeholder="URL del CV (opcional)"
                value={cvUrl}
                onChange={e => setCvUrl(sanitizeInput(e.target.value))}
                className="input"
            />
            {errors.cvUrl && <span className="error">{errors.cvUrl}</span>}
            <button type="submit" disabled={loading} className="button">{loading ? "Enviando..." : "Postular"}</button>
            {result && <div className="mt-2 text-sm submit-msg">{result}</div>}
            {/*
                Sugerencias de mejora:
                - Implementar reCAPTCHA para evitar envíos automáticos.
                - Limitar tamaño y tipo de archivo si se permite subir CV.
                - Internacionalizar mensajes de error.
                - Validar en backend si se conecta a una API real.
            */}
        </form>
    );
};

export default CandidateApplicationForm;
