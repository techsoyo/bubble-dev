
import React, { useState } from "react";
import { changeCandidateStatus } from "../services/ApiService";
import DOMPurify from "dompurify";

// Funciones de validación y sanitización
const sanitizeInput = (value: string) => DOMPurify.sanitize(value.trim());
const isValidId = (id: string) => /^[a-zA-Z0-9_-]{1,30}$/.test(id);
const isValidStatus = (status: string) => /^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s_-]{2,30}$/.test(status);

const getErrorMessage = (field: string, type: string) => {
    switch (type) {
        case 'required':
            return `El campo ${field} es obligatorio.`;
        case 'id':
            return 'ID inválido.';
        case 'status':
            return 'Estado inválido.';
        default:
            return 'Dato inválido. Revisa el campo.';
    }
};

const StatusChangeForm: React.FC = () => {
    const [candidatoId, setCandidatoId] = useState("");
    const [nuevoEstado, setNuevoEstado] = useState("");
    const [motivo, setMotivo] = useState("");
    const [result, setResult] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<{ [key: string]: string }>({});

    // Validación y sanitización de campos
    const validate = () => {
        const newErrors: { [key: string]: string } = {};
        if (!candidatoId || !isValidId(candidatoId)) newErrors.candidatoId = getErrorMessage('ID del Candidato', 'id');
        if (!nuevoEstado || !isValidStatus(nuevoEstado)) newErrors.nuevoEstado = getErrorMessage('Nuevo Estado', 'status');
        // Motivo es opcional, pero se sanitiza
        return newErrors;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setResult(null);
        // Sanitiza entradas antes de enviar
        const safeId = sanitizeInput(candidatoId);
        const safeEstado = sanitizeInput(nuevoEstado);
        const safeMotivo = sanitizeInput(motivo);
        const validationErrors = validate();
        setErrors(validationErrors);
        if (Object.keys(validationErrors).length > 0) {
            setResult('Corrige los errores antes de enviar.');
            return;
        }
        setLoading(true);
        try {
            // Envía solo datos sanitizados
            const res = await changeCandidateStatus({ candidato_id: safeId, nuevo_estado: safeEstado, motivo: safeMotivo });
            setResult(res.message || "Estado actualizado correctamente.");
        } catch (err) {
            setResult("Error al cambiar el estado. Intenta de nuevo más tarde.");
        }
        setLoading(false);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4" autoComplete="off" noValidate>
            {/* Validación y sanitización en cada campo. Los errores se muestran de forma segura. */}
            <input
                type="text"
                placeholder="ID del Candidato"
                value={candidatoId}
                onChange={e => setCandidatoId(sanitizeInput(e.target.value))}
                required
                className="input"
                minLength={1}
                maxLength={30}
            />
            {errors.candidatoId && <span className="error">{errors.candidatoId}</span>}
            <input
                type="text"
                placeholder="Nuevo Estado"
                value={nuevoEstado}
                onChange={e => setNuevoEstado(sanitizeInput(e.target.value))}
                required
                className="input"
                minLength={2}
                maxLength={30}
            />
            {errors.nuevoEstado && <span className="error">{errors.nuevoEstado}</span>}
            <input
                type="text"
                placeholder="Motivo (opcional)"
                value={motivo}
                onChange={e => setMotivo(sanitizeInput(e.target.value))}
                className="input"
                maxLength={100}
            />
            <button type="submit" disabled={loading} className="button">{loading ? "Actualizando..." : "Cambiar Estado"}</button>
            {result && <div className="mt-2 text-sm submit-msg">{result}</div>}
            {/*
                Sugerencias de mejora:
                - Implementar reCAPTCHA para evitar envíos automáticos.
                - Internacionalizar mensajes de error.
                - Validar en backend si se conecta a una API real.
            */}
        </form>
    );
};

export default StatusChangeForm;
