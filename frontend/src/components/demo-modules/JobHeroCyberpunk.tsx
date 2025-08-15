// src/components/demo-modules/JobHeroCyberpunk.tsx
import React, { useEffect, useRef, useState } from 'react';
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

// Hook para efectos de glitch
const useGlitchText = (text: string, isActive: boolean) => {
  const [glitchedText, setGlitchedText] = useState(text);

  useEffect(() => {
    if (!isActive) {
      setGlitchedText(text);
      return undefined;
    }

    const glitchChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    let frame = 0;

    const interval = setInterval(() => {
      if (frame % 30 === 0) {
        setGlitchedText(
          text.split('').map((char, i) =>
            Math.random() < 0.1 ? glitchChars[Math.floor(Math.random() * glitchChars.length)] : char
          ).join('')
        );

        setTimeout(() => setGlitchedText(text), 50);
      }
      frame++;
    }, 50);

    return () => clearInterval(interval);
  }, [text, isActive]);

  return glitchedText;
};

// Componente de líneas de código animadas
const CodeLines = () => {
  const lines = [
    "function initializeJobSystem() {",
    "  const candidates = await fetchTalents();",
    "  return processMatching(candidates);",
    "}",
    "",
    "// Bubble of Talents System v2.0",
    "const status = 'ONLINE';",
  ];

  return (
    <div className="absolute top-0 right-0 w-96 h-full bg-black/50 backdrop-blur-sm border-l-2 border-cyan-400/50 p-4 overflow-hidden">
      <div className="text-cyan-400 font-mono text-sm">
        <div className="text-green-400 mb-2">&gt; system_status: ACTIVE</div>
        {lines.map((line, i) => (
          <div
            key={i}
            className="mb-1 opacity-0 animate-pulse"
            style={{
              animationDelay: `${i * 0.2}s`,
              animationFillMode: 'forwards',
              animation: `fadeInUp 0.5s ease-out ${i * 0.2}s forwards`,
            }}
          >
            <span className="text-green-400 mr-2">{i + 1}</span>
            <span className="text-cyan-300">{line}</span>
          </div>
        ))}
        <div className="mt-4 text-yellow-400 animate-pulse">
          &gt; awaiting_user_input...
        </div>
      </div>
    </div>
  );
};

export default function JobHeroCyberpunk() {
  const { t } = useLanguage();
  const [glitchMode, setGlitchMode] = useState(false);
  const [scanLine, setScanLine] = useState(0);
  const glitchedTitle = useGlitchText("BUBBLE TALENTS SISTEMA", glitchMode);

  // Efecto de línea de escaneo
  useEffect(() => {
    const interval = setInterval(() => {
      setScanLine(prev => (prev + 1) % 100);
    }, 100);
    return () => clearInterval(interval);
  }, []);

  return (
    <section className="min-h-screen flex items-center justify-center bg-black text-green-400 relative overflow-hidden font-mono">
      {/* Efecto Matrix rain */}
      <div className="absolute inset-0 opacity-20">
        {Array.from({ length: 20 }).map((_, i) => (
          <div
            key={i}
            className="absolute w-1 h-full bg-gradient-to-b from-transparent via-green-400 to-transparent animate-pulse"
            style={{
              left: `${i * 5}%`,
              animationDelay: `${i * 0.5}s`,
              animationDuration: '3s',
              background: `linear-gradient(to bottom, transparent, #00ff41, transparent)`,
            }}
          />
        ))}
      </div>

      {/* Línea de escaneo */}
      <div
        className="absolute w-full h-0.5 bg-gradient-to-r from-transparent via-cyan-400 to-transparent z-20 opacity-70"
        style={{ top: `${scanLine}%`, transition: 'top 0.1s linear' }}
      />

      {/* Grid cyberpunk */}
      <div
        className="absolute inset-0 opacity-10"
        style={{
          backgroundImage: `
            linear-gradient(rgba(0,255,65,0.3) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0,255,65,0.3) 1px, transparent 1px)
          `,
          backgroundSize: '40px 40px',
        }}
      />

      {/* Código en el lateral */}
      <CodeLines />

      <div className="container mx-auto px-4 py-8 relative z-10">
        <div className="max-w-4xl mx-auto">

          {/* Header con glitch effect */}
          <div
            className="text-center mb-16 cursor-pointer"
            onClick={() => setGlitchMode(!glitchMode)}
          >
            {/* Mensaje del sistema */}
            <div className="bg-black/80 border border-cyan-400 rounded-lg p-4 mb-8 text-left max-w-2xl mx-auto">
              <div className="flex items-center mb-2">
                <div className="w-3 h-3 bg-green-400 rounded-full animate-pulse mr-2" />
                <span className="text-cyan-400">[SYSTEM] BUBBLE_TALENTS_AI</span>
              </div>
              <div className="text-green-300">
                &gt; Iniciando protocolo de matching de talentos...
                <br />
                &gt; Estado: <span className="text-yellow-400">ACTIVO</span>
                <br />
                &gt; Candidatos disponibles: <span className="text-cyan-400">1,337</span>
              </div>
            </div>

            <h1
              className="text-6xl md:text-7xl font-black mb-8 relative"
              style={{
                textShadow: '0 0 10px #00ff41, 0 0 20px #00ff41, 0 0 30px #00ff41',
                color: glitchMode ? '#ff0040' : '#00ff41',
                transition: 'color 0.1s',
              }}
            >
              {glitchedTitle}
            </h1>

            <div className="text-cyan-400 text-xl mb-4">
              [ CLASIFICACIÓN: ULTRA-SECRETO ]
            </div>
            <div className="text-yellow-400 animate-pulse">
              &gt; Presiona ENTER para continuar_
            </div>
          </div>

          {/* Paneles de información con terminal style */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
            <div className="bg-black/90 border-2 border-green-400 rounded-lg p-6 shadow-2xl shadow-green-400/20 relative overflow-hidden">
              <div className="flex items-center mb-4">
                <div className="text-green-400 mr-2">[REQUISITOS]</div>
                <div className="w-2 h-2 bg-red-500 rounded-full animate-ping" />
              </div>
              <div className="space-y-3">
                <div className="flex items-center">
                  <span className="text-cyan-400 mr-2">&gt;</span>
                  <span className="text-green-300">Experiencia: 5+ años</span>
                </div>
                <div className="flex items-center">
                  <span className="text-cyan-400 mr-2">&gt;</span>
                  <span className="text-green-300">Stack: React.js + Node.js</span>
                </div>
                <div className="flex items-center">
                  <span className="text-cyan-400 mr-2">&gt;</span>
                  <span className="text-green-300">Nivel: FULL-STACK NINJA</span>
                </div>
              </div>
              {/* Efecto de parpadeo */}
              <div className="absolute top-2 right-2 w-4 h-4 border border-green-400 animate-ping" />
            </div>

            <div className="bg-black/90 border-2 border-cyan-400 rounded-lg p-6 shadow-2xl shadow-cyan-400/20 relative overflow-hidden">
              <div className="flex items-center mb-4">
                <div className="text-cyan-400 mr-2">[BENEFICIOS]</div>
                <div className="w-2 h-2 bg-green-500 rounded-full animate-ping" />
              </div>
              <div className="space-y-3">
                <div className="flex items-center">
                  <span className="text-green-400 mr-2">&gt;</span>
                  <span className="text-cyan-300">Salario: ALTO_NIVEL</span>
                </div>
                <div className="flex items-center">
                  <span className="text-green-400 mr-2">&gt;</span>
                  <span className="text-cyan-300">Modalidad: MATRIX_REMOTE</span>
                </div>
                <div className="flex items-center">
                  <span className="text-green-400 mr-2">&gt;</span>
                  <span className="text-cyan-300">Growth: EXPONENCIAL</span>
                </div>
              </div>
              {/* Efecto de parpadeo */}
              <div className="absolute top-2 right-2 w-4 h-4 border border-cyan-400 animate-ping" />
            </div>
          </div>

          {/* Botones futuristas */}
          <div className="flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-8">
            <Link to="/auth/register" className="group">
              <Button
                className="relative w-80 h-16 bg-black border-2 border-green-400 text-green-400 font-bold text-lg uppercase tracking-wider overflow-hidden transform transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-green-400/50"
                style={{
                  clipPath: 'polygon(0 0, calc(100% - 20px) 0, 100% 20px, 100% 100%, 20px 100%, 0 calc(100% - 20px))',
                }}
              >
                <span className="relative z-10 flex items-center justify-center gap-3">
                  <IconUser className="w-6 h-6" />
                  [ CANDIDATO_LOGIN ]
                </span>
                {/* Efecto de barrido */}
                <div className="absolute inset-0 bg-gradient-to-r from-transparent via-green-400/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000" />
                {/* Bordes animados */}
                <div className="absolute top-0 left-0 w-full h-0.5 bg-green-400 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500" />
                <div className="absolute bottom-0 right-0 w-full h-0.5 bg-green-400 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 delay-200" />
              </Button>
            </Link>

            <Link to="/talent/login" className="group">
              <Button
                className="relative w-80 h-16 bg-black border-2 border-cyan-400 text-cyan-400 font-bold text-lg uppercase tracking-wider overflow-hidden transform transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-cyan-400/50"
                style={{
                  clipPath: 'polygon(20px 0, 100% 0, 100% calc(100% - 20px), calc(100% - 20px) 100%, 0 100%, 0 20px)',
                }}
              >
                <span className="relative z-10 flex items-center justify-center gap-3">
                  <IconBriefcase className="w-6 h-6" />
                  [ BUBBLE_ACCESS ]
                </span>
                {/* Efecto de barrido */}
                <div className="absolute inset-0 bg-gradient-to-r from-transparent via-cyan-400/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000" />
                {/* Bordes animados */}
                <div className="absolute top-0 right-0 w-0.5 h-full bg-cyan-400 transform scale-y-0 group-hover:scale-y-100 transition-transform duration-500" />
                <div className="absolute bottom-0 left-0 w-0.5 h-full bg-cyan-400 transform scale-y-0 group-hover:scale-y-100 transition-transform duration-500 delay-200" />
              </Button>
            </Link>
          </div>

          {/* Footer terminal */}
          <div className="mt-16 text-center">
            <div className="text-yellow-400 text-sm">
              &gt; bubble_of_talents_system v2.0.0 | uptime: 99.9% | status: OPERATIONAL
            </div>
          </div>

        </div>
      </div>

      {/* CSS personalizado para animaciones */}
      <style>{`
        @keyframes fadeInUp {
          from {
            opacity: 0;
            transform: translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      `}</style>
    </section>
  );
}
