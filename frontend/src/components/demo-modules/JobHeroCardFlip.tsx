// src/components/demo-modules/JobHeroCardFlip.tsx
import React, { useState, useRef, useEffect } from 'react';
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

// Componente de carta con flip 3D
const FlipCard = ({
  frontContent,
  backContent,
  className = "",
  autoFlip = false,
  delay = 0
}: {
  frontContent: React.ReactNode;
  backContent: React.ReactNode;
  className?: string;
  autoFlip?: boolean;
  delay?: number;
}) => {
  const [isFlipped, setIsFlipped] = useState(false);
  const cardRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (autoFlip) {
      const timer = setTimeout(() => {
        setIsFlipped(true);
        setTimeout(() => setIsFlipped(false), 3000);
      }, delay);

      const interval = setInterval(() => {
        setIsFlipped(prev => !prev);
        setTimeout(() => setIsFlipped(false), 3000);
      }, 6000 + delay);

      return () => {
        clearTimeout(timer);
        clearInterval(interval);
      };
    }
    return undefined;
  }, [autoFlip, delay]);

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!cardRef.current) return;

    const rect = cardRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;

    const rotateX = (y - centerY) / 10;
    const rotateY = (centerX - x) / 10;

    cardRef.current.style.transform = `
      perspective(1000px) 
      rotateX(${rotateX}deg) 
      rotateY(${rotateY}deg)
      ${isFlipped ? 'rotateY(180deg)' : ''}
    `;
  };

  const handleMouseLeave = () => {
    if (!cardRef.current) return;
    cardRef.current.style.transform = `
      perspective(1000px) 
      ${isFlipped ? 'rotateY(180deg)' : ''}
    `;
  };

  return (
    <div
      ref={cardRef}
      className={`relative w-full h-80 cursor-pointer transition-all duration-700 transform-gpu ${className}`}
      style={{
        perspective: '1000px',
        transform: isFlipped ? 'rotateY(180deg)' : 'rotateY(0deg)'
      }}
      onClick={() => setIsFlipped(!isFlipped)}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
    >
      {/* Cara frontal */}
      <div className="absolute inset-0 w-full h-full rounded-2xl shadow-2xl backface-hidden">
        {frontContent}
      </div>

      {/* Cara trasera */}
      <div
        className="absolute inset-0 w-full h-full rounded-2xl shadow-2xl backface-hidden"
        style={{ transform: 'rotateY(180deg)' }}
      >
        {backContent}
      </div>
    </div>
  );
};

// Componente de partículas flotantes
const FloatingParticles = () => {
  const particles = Array.from({ length: 15 }, (_, i) => ({
    id: i,
    size: Math.random() * 4 + 2,
    left: Math.random() * 100,
    animationDelay: Math.random() * 5,
    duration: Math.random() * 3 + 2,
  }));

  return (
    <div className="absolute inset-0 overflow-hidden pointer-events-none">
      {particles.map((particle) => (
        <div
          key={particle.id}
          className="absolute w-2 h-2 bg-white/20 rounded-full animate-bounce"
          style={{
            left: `${particle.left}%`,
            width: `${particle.size}px`,
            height: `${particle.size}px`,
            animationDelay: `${particle.animationDelay}s`,
            animationDuration: `${particle.duration}s`,
          }}
        />
      ))}
    </div>
  );
};

export default function JobHeroCardFlip() {
  const { t } = useLanguage();
  const [selectedCard, setSelectedCard] = useState<string | null>(null);

  const jobCards = [
    {
      id: 'frontend',
      title: 'Frontend Developer',
      gradient: 'from-blue-500 to-purple-600',
      icon: '⚛️',
      skills: ['React', 'TypeScript', 'Tailwind CSS'],
      description: 'Crea interfaces excepcionales que encanten a los usuarios'
    },
    {
      id: 'backend',
      title: 'Backend Developer',
      gradient: 'from-green-500 to-teal-600',
      icon: '🔧',
      skills: ['Node.js', 'PostgreSQL', 'API Design'],
      description: 'Construye la arquitectura que soporta experiencias increíbles'
    },
    {
      id: 'fullstack',
      title: 'Full Stack Developer',
      gradient: 'from-purple-500 to-pink-600',
      icon: '🚀',
      skills: ['React', 'Node.js', 'DevOps'],
      description: 'Domina el stack completo y lleva proyectos de inicio a fin'
    }
  ];

  return (
    <section className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900 text-white relative overflow-hidden">
      {/* Partículas flotantes */}
      <FloatingParticles />

      {/* Fondo con patrones */}
      <div
        className="absolute inset-0 opacity-5"
        style={{
          backgroundImage: `radial-gradient(circle at 25% 25%, white 1px, transparent 1px),
                           radial-gradient(circle at 75% 75%, white 1px, transparent 1px)`,
          backgroundSize: '50px 50px',
        }}
      />

      <div className="container mx-auto px-4 py-8 relative z-10">
        <div className="max-w-7xl mx-auto">

          {/* Header */}
          <div className="text-center mb-20">
            <div className="inline-block p-4 bg-white/10 backdrop-blur-sm rounded-full mb-8">
              <span className="text-4xl">🃏</span>
            </div>

            <h1 className="text-6xl md:text-7xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-purple-500 to-pink-500 mb-6">
              Descubre tu Match
            </h1>

            <p className="text-xl text-gray-300 max-w-3xl mx-auto leading-relaxed">
              Explora oportunidades únicas a través de nuestras cartas interactivas.
              Cada flip revela una nueva posibilidad profesional.
            </p>
          </div>

          {/* Grid de cartas con flip */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-20">
            {jobCards.map((card, index) => (
              <FlipCard
                key={card.id}
                autoFlip={true}
                delay={index * 1000}
                frontContent={
                  <div className={`w-full h-full bg-gradient-to-br ${card.gradient} rounded-2xl p-8 flex flex-col justify-between relative overflow-hidden`}>
                    {/* Efecto de brillo */}
                    <div className="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl" />

                    <div>
                      <div className="text-4xl mb-4">{card.icon}</div>
                      <h3 className="text-2xl font-bold mb-2">{card.title}</h3>
                      <p className="text-white/80 text-sm">Haz click para descubrir más</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                      {card.skills.map((skill, i) => (
                        <span
                          key={i}
                          className="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-medium"
                        >
                          {skill}
                        </span>
                      ))}
                    </div>
                  </div>
                }
                backContent={
                  <div className="w-full h-full bg-slate-800 rounded-2xl p-8 flex flex-col justify-between relative overflow-hidden border border-gray-700">
                    {/* Patrón de fondo */}
                    <div
                      className="absolute inset-0 opacity-5"
                      style={{
                        backgroundImage: `repeating-linear-gradient(
                          45deg,
                          transparent,
                          transparent 10px,
                          white 10px,
                          white 11px
                        )`
                      }}
                    />

                    <div className="relative z-10">
                      <div className="text-3xl mb-4">💼</div>
                      <h3 className="text-xl font-bold mb-4 text-white">{card.title}</h3>
                      <p className="text-gray-300 text-sm leading-relaxed mb-6">
                        {card.description}
                      </p>

                      <div className="space-y-2">
                        <div className="text-sm text-gray-400">Requisitos principales:</div>
                        <ul className="space-y-1">
                          {card.skills.map((skill, i) => (
                            <li key={i} className="text-sm text-gray-300 flex items-center">
                              <span className="w-1.5 h-1.5 bg-blue-400 rounded-full mr-2" />
                              {skill}
                            </li>
                          ))}
                        </ul>
                      </div>
                    </div>

                    <button
                      className="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:from-blue-600 hover:to-purple-700 transition-all duration-300 transform hover:scale-105"
                      onClick={(e) => {
                        e.stopPropagation();
                        setSelectedCard(card.id);
                      }}
                    >
                      Ver detalles completos
                    </button>
                  </div>
                }
              />
            ))}
          </div>

          {/* Tarjeta principal con efecto holográfico */}
          <div className="max-w-4xl mx-auto mb-16">
            <FlipCard
              frontContent={
                <div className="w-full h-full bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-600 rounded-2xl p-12 flex items-center justify-center relative overflow-hidden">
                  {/* Efectos holográficos */}
                  <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent skew-x-12 transform -translate-x-full animate-pulse"
                    style={{ animation: 'shimmer 3s ease-in-out infinite' }} />

                  <div className="text-center relative z-10">
                    <div className="text-6xl mb-6">🌟</div>
                    <h2 className="text-4xl font-black text-white mb-4">
                      Tu Próxima Oportunidad
                    </h2>
                    <p className="text-white/90 text-lg">
                      Haz flip para descubrir lo que te espera
                    </p>
                  </div>
                </div>
              }
              backContent={
                <div className="w-full h-full bg-slate-900 rounded-2xl p-12 flex flex-col justify-center items-center border border-gray-700 relative overflow-hidden">
                  <div className="text-center">
                    <div className="text-5xl mb-6">🎯</div>
                    <h2 className="text-3xl font-bold text-white mb-6">
                      ¿Listo para dar el salto?
                    </h2>

                    {/* Botones de acción con efectos */}
                    <div className="flex flex-col md:flex-row gap-6 justify-center">
                      <Link to="/auth/register" className="group">
                        <Button
                          className="w-60 h-14 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-semibold text-lg rounded-xl overflow-hidden relative transform transition-all duration-300 hover:scale-105 border-0"
                        >
                          <span className="relative z-10 flex items-center justify-center gap-3">
                            <IconUser className="w-6 h-6" />
                            Soy Candidato/a
                          </span>
                          {/* Efecto de ondas */}
                          <div className="absolute inset-0 bg-gradient-to-r from-purple-600 to-pink-600 transform scale-0 group-hover:scale-100 rounded-xl transition-transform duration-500 origin-center" />
                        </Button>
                      </Link>

                      <Link to="/talent/login" className="group">
                        <Button
                          className="w-60 h-14 bg-slate-800 text-white font-semibold text-lg rounded-xl border border-gray-600 overflow-hidden relative transform transition-all duration-300 hover:scale-105"
                        >
                          <span className="relative z-10 flex items-center justify-center gap-3 group-hover:text-cyan-300">
                            <IconBriefcase className="w-6 h-6" />
                            Soy de Bubble
                          </span>
                          {/* Efecto glow */}
                          <div className="absolute inset-0 bg-gradient-to-r from-cyan-500/20 to-blue-500/20 opacity-0 group-hover:opacity-100 rounded-xl transition-opacity duration-300" />
                        </Button>
                      </Link>
                    </div>
                  </div>
                </div>
              }
            />
          </div>

        </div>
      </div>

      {/* CSS personalizado para animaciones */}
      <style>{`
        .backface-hidden {
          backface-visibility: hidden;
        }
        
        @keyframes shimmer {
          0% { transform: translateX(-100%) skewX(-12deg); }
          100% { transform: translateX(200%) skewX(-12deg); }
        }
      `}</style>
    </section>
  );
}
