// src/components/demo-modules/JobHeroMinimalist.tsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Button } from '../ui/button';
import { useLanguage } from '../../lib/i18n/LanguageContext';

const IconUser = (p: React.SVGProps<SVGSVGElement>) => (
  <svg viewBox="0 0 24 24" fill="none" {...p} aria-hidden="true">
    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" strokeWidth="1.5" />
    <circle cx="12" cy="7" r="4" stroke="currentColor" strokeWidth="1.5" />
  </svg>
);

const IconBriefcase = (p: React.SVGProps<SVGSVGElement>) => (
  <svg viewBox="0 0 24 24" fill="none" {...p} aria-hidden="true">
    <path d="M3 7h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" stroke="currentColor" strokeWidth="1.5" />
    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" stroke="currentColor" strokeWidth="1.5" />
  </svg>
);

// Componente de línea animada
const AnimatedLine = ({ delay = 0 }: { delay?: number }) => {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setVisible(true), delay);
    return () => clearTimeout(timer);
  }, [delay]);

  return (
    <div
      className={`h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent transition-all duration-1000 ${visible ? 'w-full opacity-100' : 'w-0 opacity-0'
        }`}
    />
  );
};

// Hook para typing effect
const useTypewriter = (text: string, speed = 50) => {
  const [displayText, setDisplayText] = useState('');
  const [isComplete, setIsComplete] = useState(false);

  useEffect(() => {
    let index = 0;
    const timer = setInterval(() => {
      if (index < text.length) {
        setDisplayText(text.slice(0, index + 1));
        index++;
      } else {
        setIsComplete(true);
        clearInterval(timer);
      }
    }, speed);

    return () => clearInterval(timer);
  }, [text, speed]);

  return { displayText, isComplete };
};

export default function JobHeroMinimalist() {
  const { t } = useLanguage();
  const [currentSection, setCurrentSection] = useState(0);
  const { displayText: typedTitle } = useTypewriter("Oportunidades que transforman carreras", 60);

  const sections = [
    {
      title: "Requisitos",
      icon: "●",
      items: [
        "5+ años de experiencia profesional",
        "Dominio de React y ecosistema moderno",
        "Pasión por la excelencia técnica"
      ]
    },
    {
      title: "Beneficios",
      icon: "○",
      items: [
        "Compensación competitiva y transparente",
        "Flexibilidad total de horarios",
        "Crecimiento profesional continuo"
      ]
    },
    {
      title: "Cultura",
      icon: "◆",
      items: [
        "Ambiente colaborativo y inclusivo",
        "Innovación como parte del ADN",
        "Balance vida-trabajo real"
      ]
    }
  ];

  // Cambio automático de secciones
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentSection(prev => (prev + 1) % sections.length);
    }, 4000);
    return () => clearInterval(interval);
  }, []);

  return (
    <section className="min-h-screen flex items-center justify-center bg-white text-gray-900 relative overflow-hidden">
      {/* Fondo minimalista con formas geométricas */}
      <div className="absolute inset-0">
        {/* Círculo grande */}
        <div className="absolute top-20 right-20 w-96 h-96 border border-gray-100 rounded-full opacity-50" />
        <div className="absolute top-32 right-32 w-64 h-64 border border-gray-200 rounded-full opacity-30" />

        {/* Líneas geométricas */}
        <div className="absolute top-1/4 left-0 w-32 h-px bg-gradient-to-r from-gray-200 to-transparent" />
        <div className="absolute top-1/2 right-0 w-24 h-px bg-gradient-to-l from-gray-200 to-transparent" />
        <div className="absolute bottom-1/3 left-1/4 w-16 h-px bg-gradient-to-r from-gray-200 to-transparent" />
      </div>

      <div className="container mx-auto px-8 py-8 relative z-10">
        <div className="max-w-5xl mx-auto">

          {/* Header ultra minimalista */}
          <div className="text-center mb-24">
            <div className="mb-8">
              <div className="text-xs uppercase tracking-widest text-gray-400 mb-4">
                Bubble of Talents
              </div>
              <AnimatedLine delay={500} />
            </div>

            <h1 className="text-5xl md:text-6xl lg:text-7xl font-extralight leading-tight mb-8 tracking-tight">
              {typedTitle}
              <span className="animate-pulse">|</span>
            </h1>

            <p className="text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed font-light">
              Conectamos el talento excepcional con oportunidades que redefinen el futuro profesional
            </p>
          </div>

          {/* Sección de información con transiciones */}
          <div className="mb-20">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
              {/* Navegación de secciones */}
              <div className="md:col-span-1">
                <div className="space-y-4">
                  {sections.map((section, index) => (
                    <button
                      key={index}
                      onClick={() => setCurrentSection(index)}
                      className={`w-full text-left p-4 rounded-none border-l-2 transition-all duration-300 ${index === currentSection
                        ? 'border-gray-900 bg-gray-50 text-gray-900'
                        : 'border-gray-200 hover:border-gray-400 text-gray-600'
                        }`}
                    >
                      <div className="flex items-center space-x-3">
                        <span className="text-2xl">{section.icon}</span>
                        <span className="font-medium">{section.title}</span>
                      </div>
                    </button>
                  ))}
                </div>
              </div>

              {/* Contenido dinámico */}
              <div className="md:col-span-2">
                <div className="bg-gray-50 p-8 h-64 flex items-center justify-center relative overflow-hidden">
                  <div className="absolute inset-0 bg-gradient-to-br from-white to-gray-100" />
                  <div className="relative z-10 w-full">
                    <h3 className="text-2xl font-light mb-6 text-gray-900">
                      {sections[currentSection].title}
                    </h3>
                    <div className="space-y-4">
                      {sections[currentSection].items.map((item, index) => (
                        <div
                          key={index}
                          className="flex items-start space-x-3 transform transition-all duration-500"
                          style={{
                            transitionDelay: `${index * 100}ms`,
                          }}
                        >
                          <div className="w-1 h-1 bg-gray-400 rounded-full mt-3 flex-shrink-0" />
                          <p className="text-gray-700 leading-relaxed">{item}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Botones ultra minimalistas */}
          <div className="text-center">
            <div className="flex flex-col md:flex-row justify-center items-center space-y-6 md:space-y-0 md:space-x-12">
              <Link to="/auth/register" className="group">
                <Button
                  variant="ghost"
                  className="w-64 h-14 bg-white border border-gray-900 text-gray-900 rounded-none font-light text-base uppercase tracking-wider transition-all duration-300 hover:bg-gray-900 hover:text-white relative overflow-hidden"
                >
                  <span className="relative z-10 flex items-center justify-center space-x-3">
                    <IconUser className="w-5 h-5" />
                    <span>Soy Candidato/a</span>
                  </span>
                  {/* Efecto de deslizamiento */}
                  <div className="absolute inset-0 bg-gray-900 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300" />
                </Button>
              </Link>

              <div className="hidden md:block w-px h-14 bg-gray-300" />

              <Link to="/talent/login" className="group">
                <Button
                  variant="ghost"
                  className="w-64 h-14 bg-gray-900 border border-gray-900 text-white rounded-none font-light text-base uppercase tracking-wider transition-all duration-300 hover:bg-white hover:text-gray-900 relative overflow-hidden"
                >
                  <span className="relative z-10 flex items-center justify-center space-x-3">
                    <IconBriefcase className="w-5 h-5" />
                    <span>Soy de Bubble</span>
                  </span>
                  {/* Efecto de deslizamiento inverso */}
                  <div className="absolute inset-0 bg-white transform translate-x-full group-hover:translate-x-0 transition-transform duration-300" />
                </Button>
              </Link>
            </div>
          </div>

          {/* Footer minimalista */}
          <div className="mt-24 text-center">
            <AnimatedLine delay={3000} />
            <div className="mt-8 text-xs uppercase tracking-widest text-gray-400">
              Donde el talento encuentra su lugar
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
