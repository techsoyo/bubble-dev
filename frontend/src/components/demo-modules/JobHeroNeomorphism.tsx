// src/components/demo-modules/JobHeroNeomorphism.tsx
import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Button } from '../ui/button';
import { useLanguage } from '../../lib/i18n/LanguageContext';

const IconUser = (p: React.SVGProps<SVGSVGElement>) => (
  <svg viewBox="0 0 24 24" fill="none" {...p} aria-hidden="true">
    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" strokeWidth="2" />
    <circle cx="12" cy="7" r="4" stroke="currentColor" strokeWidth="2" />
  </svg>
);

const IconBriefcase = (p: React.SVGProps<SVGSVGElement>) => (
  <svg viewBox="0 0 24 24" fill="none" {...p} aria-hidden="true">
    <path d="M3 7h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke="currentColor" strokeWidth="2" />
    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" strokeWidth="2" />
  </svg>
);

// Componente de tarjeta con efecto tilt
const TiltCard = ({ children, className }: { children: React.ReactNode; className?: string }) => {
  const [transform, setTransform] = useState('');

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    const card = e.currentTarget;
    const rect = card.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const rotateX = (y - centerY) / 10;
    const rotateY = (centerX - x) / 10;

    setTransform(`perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`);
  };

  const handleMouseLeave = () => {
    setTransform('');
  };

  return (
    <div
      className={className}
      style={{ transform, transition: transform ? 'none' : 'transform 0.3s ease-out' }}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
    >
      {children}
    </div>
  );
};

export default function JobHeroNeomorphism() {
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState('requisitos');

  return (
    <section className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 text-white relative overflow-hidden">
      {/* Grid de fondo */}
      <div
        className="absolute inset-0 opacity-10"
        style={{
          backgroundImage: `linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px),
                           linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px)`,
          backgroundSize: '50px 50px',
        }}
      />

      <div className="container mx-auto px-4 py-8 relative z-10">
        <div className="max-w-6xl mx-auto">

          {/* Header con efecto de onda */}
          <div className="text-center mb-16 relative">
            <div className="absolute inset-0 bg-gradient-to-r from-cyan-400/20 via-purple-500/20 to-pink-500/20 blur-3xl rounded-full" />
            <h1 className="relative text-7xl md:text-8xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-purple-500 to-pink-500 mb-8">
              Detalles del Empleo
            </h1>

            {/* Tabs navegación */}
            <div className="flex justify-center space-x-4 mb-8">
              {['requisitos', 'beneficios', 'info'].map((tab) => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`px-8 py-3 rounded-full text-sm font-semibold transition-all duration-300 ${activeTab === tab
                    ? 'bg-white/20 backdrop-blur-sm border border-white/30 text-white shadow-lg'
                    : 'text-white/60 hover:text-white/80 hover:bg-white/10'
                    }`}
                  style={{
                    boxShadow: activeTab === tab ? 'inset 2px 2px 4px rgba(0,0,0,0.2), inset -2px -2px 4px rgba(255,255,255,0.1)' : 'none'
                  }}
                >
                  {tab.charAt(0).toUpperCase() + tab.slice(1)}
                </button>
              ))}
            </div>
          </div>

          {/* Cards con hover tilt */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
            <TiltCard className="bg-white/5 backdrop-blur-md border border-white/20 rounded-2xl p-8 shadow-2xl hover:shadow-purple-500/20 transition-all duration-300">
              <div className="flex items-center mb-6">
                <div className="w-3 h-3 bg-purple-500 rounded-full animate-ping mr-3" />
                <h3 className="text-2xl font-bold bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
                  Requisitos Técnicos
                </h3>
              </div>
              <ul className="space-y-4 text-gray-300">
                <li className="flex items-center space-x-3 hover:text-white transition-colors duration-200 hover:translate-x-2">
                  <span className="w-2 h-2 bg-purple-500 rounded-full animate-pulse" />
                  <span>5+ años de experiencia</span>
                </li>
                <li className="flex items-center space-x-3 hover:text-white transition-colors duration-200 hover:translate-x-2">
                  <span className="w-2 h-2 bg-purple-500 rounded-full animate-pulse" />
                  <span>React + Node.js</span>
                </li>
                <li className="flex items-center space-x-3 hover:text-white transition-colors duration-200 hover:translate-x-2">
                  <span className="w-2 h-2 bg-purple-500 rounded-full animate-pulse" />
                  <span>Full-stack development</span>
                </li>
              </ul>
            </TiltCard>

            <TiltCard className="bg-white/5 backdrop-blur-md border border-white/20 rounded-2xl p-8 shadow-2xl hover:shadow-pink-500/20 transition-all duration-300">
              <div className="flex items-center mb-6">
                <div className="w-3 h-3 bg-pink-500 rounded-full animate-ping mr-3" />
                <h3 className="text-2xl font-bold bg-gradient-to-r from-pink-400 to-cyan-400 bg-clip-text text-transparent">
                  Beneficios Premium
                </h3>
              </div>
              <ul className="space-y-4 text-gray-300">
                <li className="flex items-center space-x-3 hover:text-white transition-colors duration-200 hover:translate-x-2">
                  <span className="w-2 h-2 bg-pink-500 rounded-full animate-pulse" />
                  <span>Salario competitivo</span>
                </li>
                <li className="flex items-center space-x-3 hover:text-white transition-colors duration-200 hover:translate-x-2">
                  <span className="w-2 h-2 bg-pink-500 rounded-full animate-pulse" />
                  <span>100% Trabajo remoto</span>
                </li>
                <li className="flex items-center space-x-3 hover:text-white transition-colors duration-200 hover:translate-x-2">
                  <span className="w-2 h-2 bg-pink-500 rounded-full animate-pulse" />
                  <span>Crecimiento profesional</span>
                </li>
              </ul>
            </TiltCard>
          </div>

          {/* Botones con morfismo */}
          <div className="flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-8">
            <Link to="/auth/register" className="group">
              <Button
                className="relative w-72 h-16 rounded-2xl bg-gradient-to-r from-purple-600 to-pink-600 text-white font-semibold text-lg overflow-hidden transform transition-all duration-300 hover:scale-105 border-0"
                style={{
                  boxShadow: '8px 8px 16px rgba(0,0,0,0.3), -8px -8px 16px rgba(255,255,255,0.1), inset 2px 2px 4px rgba(255,255,255,0.1)',
                }}
              >
                <span className="relative z-10 flex items-center justify-center gap-3">
                  <IconUser className="w-6 h-6" />
                  🚀 Soy un Candidato/a
                </span>
                {/* Efecto ripple */}
                <div className="absolute inset-0 bg-gradient-to-r from-pink-600 to-purple-700 transform scale-0 group-hover:scale-100 rounded-2xl transition-transform duration-500 origin-center" />
              </Button>
            </Link>

            <Link to="/talent/login" className="group">
              <Button
                className="relative w-72 h-16 rounded-2xl bg-slate-800 text-white font-semibold text-lg overflow-hidden transform transition-all duration-300 hover:scale-105 border border-white/20"
                style={{
                  boxShadow: 'inset 4px 4px 8px rgba(0,0,0,0.3), inset -4px -4px 8px rgba(255,255,255,0.1)',
                }}
              >
                <span className="relative z-10 flex items-center justify-center gap-3 group-hover:text-cyan-300">
                  <IconBriefcase className="w-6 h-6" />
                  💼 Soy de Bubble
                </span>
                {/* Efecto glow */}
                <div className="absolute inset-0 bg-gradient-to-r from-cyan-500/20 to-blue-500/20 opacity-0 group-hover:opacity-100 rounded-2xl transition-opacity duration-300" />
              </Button>
            </Link>
          </div>

        </div>
      </div>
    </section>
  );
}
