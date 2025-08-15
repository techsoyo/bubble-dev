import React from 'react';


export default function ProfileSummary({ habilidades = [], categoria = '' }) {
    return (
        <div className="container mx-auto px-4 py-8 max-w-2xl">
            <h1 className="text-2xl font-bold mb-6 text-[#FF4785]">Resumen de Perfil</h1>
            <div className="mb-4">
                <strong>Habilidades detectadas:</strong>
                <ul className="list-disc ml-6">
                    {habilidades.map(h => <li key={h}>{h}</li>)}
                </ul>
            </div>
            <div className="mb-4">
                <strong>Categoría profesional detectada:</strong>
                <span className="ml-2 font-semibold text-[#2F2F2F]">{categoria}</span>
            </div>
            {/* Aquí puedes mostrar otros datos del perfil */}
        </div>
    );
}
