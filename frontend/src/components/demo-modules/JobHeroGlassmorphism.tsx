// src/components/demo-modules/JobHeroGlassmorphism.tsx
import React from 'react';
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

// Componente de partícula flotante
const FloatingParticle = ({ delay, size }: { delay: number; size: number }) => (
  <div
    className="absolute animate-pulse opacity-60"
    style={{
      left: `${Math.random() * 100}%`,
      top: `${Math.random() * 100}%`,
      animationDelay: `${delay}s`,
      animationDuration: `${3 + Math.random() * 2}s`,
    }}
  >
    <div
      className="bg-white/20 rounded-full blur-sm"
      style={{
        width: `${size}px`,
        height: `${size}px`,
      }}
    />
  </div>
);

export default function JobHeroGlassmorphism() {
  const { t } = useLanguage();

  return (
    <section className="relative min-h-screen flex items-center justify-center overflow-hidden text-white" aria-label="Job Details Glassmorphism">
      {/* Fondo con imagen */}
      <img
        src="/images/team-collaboration.jpg"
        alt=""
        className="absolute inset-0 h-full w-full object-cover object-right"
      />

      {/* Overlay con gradiente */}
      <div className="absolute inset-0 bg-gradient-to-br from-purple-900/40 via-blue-900/40 to-pink-900/40" />

      {/* Partículas flotantes */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        {Array.from({ length: 15 }, (_, i) => (
          <FloatingParticle
            key={i}
            delay={i * 0.3}
            size={4 + Math.random() * 8}
          />
        ))}
      </div>

      {/* Contenido principal */}
      <div className="relative z-10 container mx-auto px-4 py-8">
        <div className="max-w-6xl mx-auto">
          {/* Contenedor principal con glassmorphism */}
          <div
            className="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl p-8 shadow-2xl"
            style={{
              backdropFilter: 'blur(20px) saturate(150%)',
              background: 'linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%)',
              boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.1)',
            }}
          >
            {/* Título con efecto gradient */}
            <div className="text-center mb-12">
              <h1 className="text-6xl md:text-7xl font-black mb-4">
                <span className="bg-gradient-to-r from-pink-400 via-purple-400 to-cyan-400 bg-clip-text text-transparent animate-pulse">
                  Detalles del Empleo
                </span>
              </h1>

              {/* Subtítulos con animación escalonada */}
              <div className="space-y-4">
                <h2 className="text-3xl md:text-4xl font-bold text-white/90 transform transition-all duration-700 hover:scale-105">
                  ✨ Requisitos
                </h2>
                <h3 className="text-3xl md:text-4xl font-bold text-pink-300 transform transition-all duration-700 hover:scale-105">
                  💎 Beneficios
                </h3>
              </div>
            </div>

            {/* Información adicional */}
            <div className="text-center mb-12">
              <p className="text-xl text-white/80 max-w-2xl mx-auto leading-relaxed">
                Descubre una oportunidad única para crecer profesionalmente
              </p>
            </div>

            {/* Botones con efectos avanzados */}
            <div className="flex flex-col md:flex-row gap-6 justify-center items-center">
              <Link to="/auth/register" className="group relative">
                <Button className="relative w-64 h-16 rounded-2xl bg-gradient-to-r from-pink-500 to-purple-600 text-white overflow-hidden transform transition-all duration-300 hover:scale-105 hover:shadow-2xl border-0">
                  <span className="relative z-10 flex items-center justify-center gap-3 font-semibold text-lg">
                    <IconUser className="w-6 h-6" />
                    Soy un Candidato/a
                  </span>
                  {/* Efecto shimmer */}
                  <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-700" />
                  {/* Efecto de resplandor */}
                  <div className="absolute inset-0 bg-gradient-to-r from-pink-600 to-purple-700 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </Button>
              </Link>

              <Link to="/talent/login" className="group relative">
                <Button className="relative w-64 h-16 rounded-2xl bg-gray-800/50 backdrop-blur-sm border-2 border-white/30 text-white transform transition-all duration-300 hover:scale-105 hover:bg-gray-700/60 overflow-hidden">
                  <span className="relative z-10 flex items-center justify-center gap-3 font-semibold text-lg group-hover:text-cyan-300">
                    <IconBriefcase className="w-6 h-6" />
                    Soy de Bubble
                  </span>
                  {/* Borde animado */}
                  <div className="absolute inset-0 rounded-2xl border-2 border-transparent group-hover:border-cyan-400/50 transition-all duration-300" />
                  {/* Efecto de partículas */}
                  <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                    {Array.from({ length: 3 }, (_, i) => (
                      <div
                        key={i}
                        className="absolute w-1 h-1 bg-cyan-400 rounded-full animate-ping"
                        style={{
                          left: `${20 + i * 30}%`,
                          top: `${30 + i * 20}%`,
                          animationDelay: `${i * 0.2}s`,
                        }}
                      />
                    ))}
                  </div>
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
