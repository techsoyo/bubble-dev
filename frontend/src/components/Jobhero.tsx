// src/components/JobHero.tsx - Minimalist con Color y Parallax
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Button } from './ui/button';
import { useLanguage } from '../lib/i18n/LanguageContext';

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

// Componente de línea animada con color
const AnimatedLine = ({ delay = 0, color = 'from-blue-500 to-purple-600' }: { delay?: number; color?: string }) => {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setVisible(true), delay);
    return () => clearTimeout(timer);
  }, [delay]);

  return (
    <div
      className={`h-px bg-gradient-to-r ${color} transition-all duration-1000 ${visible ? 'w-full opacity-100' : 'w-0 opacity-0'
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

// Hook para parallax effect
const useParallax = (speed = 0.5) => {
  const [offsetY, setOffsetY] = useState(0);

  useEffect(() => {
    const handleScroll = () => {
      setOffsetY(window.pageYOffset * speed);
    };

    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, [speed]);

  return offsetY;
};

export default function JobHeroLite() {
  const { t } = useLanguage();
  const [currentSection, setCurrentSection] = useState(0);
  const { displayText: typedTitle } = useTypewriter(t('hero.title'), 60);
  const parallaxOffset = useParallax(0.3);

  // Helper function to get array items from translations
  const getTranslationArray = (key: string): string[] => {
    const result = t(key);
    return Array.isArray(result) ? result : [];
  };

  const sections = [
    {
      title: t('hero.sections.requirements.title'),
      icon: "●",
      backgroundColor: "rgba(242, 68, 149, 0.5)", // #f24495 50%
      textColor: "text-white",
      iconColor: "text-white",
      items: getTranslationArray('hero.sections.requirements.items')
    },
    {
      title: t('hero.sections.benefits.title'),
      icon: "○",
      backgroundColor: "rgba(47, 47, 47, 0.5)", // #2f2f2f 50%
      textColor: "text-white",
      iconColor: "text-white",
      items: getTranslationArray('hero.sections.benefits.items')
    },
    {
      title: t('hero.sections.culture.title'),
      icon: "◆",
      backgroundColor: "rgba(255, 255, 255, 0.5)", // blanco 50%
      textColor: "text-gray-800", // #2f2f2f
      iconColor: "text-gray-800",
      items: getTranslationArray('hero.sections.culture.items')
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
    <section className="min-h-screen flex items-center justify-center relative overflow-hidden font-['Poppins'] py-8">
      {/* Parallax Background Image */}
      <div
        className="absolute inset-0 bg-cover bg-center bg-no-repeat parallax-bg"
        style={{
          backgroundImage: 'url(/images/team-collaboration.jpg)',
          // Invertir el offset para que el fondo se desplace más lento en sentido contrario
          // al scroll y así crear el efecto parallax esperado.
          transform: `translateY(${-parallaxOffset}px)`,
        }}
      />
     
      {/* Elementos decorativos existentes */}
      <div className="absolute inset-0">
        {/* Círculos con gradientes corporativos */}
        <div className="absolute top-16 right-16 w-80 h-80 border border-white/10 rounded-full opacity-40" />
        <div className="absolute top-24 right-24 w-56 h-56 bg-gradient-to-br from-pink-500/20 to-gray-600/20 rounded-full opacity-30 blur-sm" />

        {/* Líneas geométricas corporativas */}
        <div className="absolute top-1/4 left-0 w-28 h-px bg-gradient-to-r from-pink-500 to-transparent opacity-60" />
        <div className="absolute top-1/2 right-0 w-20 h-px bg-gradient-to-l from-white to-transparent opacity-40" />
        <div className="absolute bottom-1/3 left-1/4 w-14 h-px bg-gradient-to-r from-pink-500 to-transparent opacity-50" />
      </div>

      <div
        className="container mx-auto px-4 sm:px-6 md:px-8 max-w-5xl relative z-10 text-white"
        style={{
          borderRadius: '5%',
          backgroundColor: 'rgba(47, 47, 47, 0.85)',
          border: '1px solid rgba(255, 255, 255, 0.1)',
          backdropFilter: 'blur(10px)'
        }}
      >
        <div className="max-w-5xl mx-auto py-8 sm:py-12">

          {/* Header con colores corporativos */}
          <div className="text-center mb-6 sm:mb-8">
            <div className="mb-4">
              <AnimatedLine delay={500} color="from-pink-500 via-white to-pink-500" />
            </div>

            <h1 className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-medium leading-tight mb-4 tracking-tight">
              <span className="bg-gradient-to-r from-white via-pink-300 to-pink-500 bg-clip-text text-transparent">
                {typedTitle}
              </span>
              <span className="animate-pulse text-pink-400">|</span>
            </h1>

            <p className="text-sm sm:text-base text-white/90 max-w-2xl mx-auto leading-relaxed font-normal">
              {t('hero.subtitle')}
            </p>
          </div>          {/* Sección de información con colores */}
          <div className="mb-6 sm:mb-8">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
              {/* Navegación de secciones */}
              <div className="md:col-span-1">
                <div className="space-y-3">
                  {sections.map((section, index) => (
                    <button
                      key={index}
                      onClick={() => setCurrentSection(index)}
                      className={`w-full text-left p-3 rounded-lg border-l-4 transition-all duration-300 ${index === currentSection
                        ? `border-pink-500 shadow-md ${section.textColor}`
                        : 'border-white/20 hover:border-pink-400/50 text-white/70 hover:bg-white/5'
                        }`}
                      style={{
                        backgroundColor: index === currentSection ? section.backgroundColor : 'transparent'
                      }}
                    >
                      <div className="flex items-center space-x-3">
                        <span className={`text-xl ${index === currentSection ? section.iconColor : 'text-white/40'}`}>
                          {section.icon}
                        </span>
                        <span className="font-medium text-sm sm:text-base">{section.title}</span>
                      </div>
                    </button>
                  ))}
                </div>
              </div>

              {/* Contenido dinámico con colores */}
              <div className="md:col-span-2">
                <div
                  className="backdrop-blur-sm p-4 sm:p-6 h-40 sm:h-48 flex items-center justify-center relative overflow-hidden rounded-lg border border-white/10 shadow-lg"
                  style={{
                    backgroundColor: currentSection === 0 ? 'rgba(242, 68, 149, 0.5)' : 'rgba(55, 65, 81, 0.5)'
                  }}
                >
                  <div className="absolute inset-0 bg-gradient-to-br from-pink-500/10 to-transparent" />
                  <div className="relative z-10 w-full">
                    <h3 className="text-base sm:text-lg font-light mb-3 sm:mb-4 text-white">
                      {sections[currentSection].title}
                    </h3>
                    <div className="space-y-3">
                      {sections[currentSection].items.map((item, index) => (
                        <div
                          key={index}
                          className="flex items-start space-x-3 transform transition-all duration-500"
                          style={{
                            transitionDelay: `${index * 100}ms`,
                          }}
                        >
                          <div className="w-1.5 h-1.5 bg-white rounded-full mt-1.5 flex-shrink-0" />
                          <p className="text-xs sm:text-sm text-white leading-relaxed">{item}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Botones con gradientes coloridos */}
          <div className="text-center">
            <div className="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-8">
              <Link to="/auth/register" className="group">
                <Button
                  variant="ghost"
                  className="w-56 h-12 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg font-medium text-sm uppercase tracking-wider transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/25 relative overflow-hidden border-0"
                >
                  <span className="relative z-10 flex items-center justify-center space-x-2">
                    <IconUser className="w-4 h-4" />
                    <span>{t('hero.buttons.candidate')}</span>
                  </span>
                  {/* Efecto de brillo corporativo */}
                  <div className="absolute inset-0 bg-gradient-to-r from-pink-500 to-pink-600 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300" />
                </Button>
              </Link>

              <div className="hidden sm:block w-px h-12 bg-gradient-to-b from-white/30 to-pink-400/30" />

              <Link to="/talent/login" className="group">
                <Button
                  variant="ghost"
                  className="w-56 h-12 bg-white/10 backdrop-blur-sm border-2 border-white/20 text-white rounded-lg font-medium text-sm uppercase tracking-wider transition-all duration-300 hover:border-pink-400/50 hover:shadow-lg hover:shadow-pink-500/25 relative overflow-hidden"
                >
                  <span className="relative z-10 flex items-center justify-center space-x-2 group-hover:text-pink-300">
                    <IconBriefcase className="w-4 h-4" />
                    <span>{t('hero.buttons.company')}</span>
                  </span>
                  {/* Efecto de degradado corporativo */}
                  <div className="absolute inset-0 bg-gradient-to-r from-pink-500/10 to-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
                </Button>
              </Link>
            </div>
          </div>

          {/* Footer con línea corporativa */}
          <div className="mt-6 sm:mt-8 text-center">
            <AnimatedLine delay={3000} color="from-pink-500 via-white to-pink-500" />
            <div className="mt-3 sm:mt-4 text-xs uppercase tracking-widest text-white/70">
              {t('hero.tagline')}
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
