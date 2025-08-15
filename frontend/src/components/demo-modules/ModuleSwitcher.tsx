// src/components/demo-modules/ModuleSwitcher.tsx
import React, { useState } from 'react';
import {
  JobHeroGlassmorphism,
  JobHeroNeomorphism,
  JobHeroCyberpunk,
  JobHeroMinimalist,
  JobHeroCardFlip,
  JobHeroDashboard
} from './index';

const modules = [
  {
    id: 'glassmorphism',
    name: '✨ Glassmorphism',
    description: 'Efectos de cristal con partículas flotantes',
    component: JobHeroGlassmorphism,
    preview: '🌟 Elegante • Moderno • Interactivo'
  },
  {
    id: 'neomorphism',
    name: '🎨 Neomorphism',
    description: 'Diseño suave con micro-interacciones',
    component: JobHeroNeomorphism,
    preview: '🔮 3D Tilt • Tabs • Animaciones'
  },
  {
    id: 'cyberpunk',
    name: '🌀 Cyberpunk',
    description: 'Estilo futurista con efectos Matrix',
    component: JobHeroCyberpunk,
    preview: '⚡ Glitch • Terminal • Neon'
  },
  {
    id: 'minimalist',
    name: '✨ Minimalist',
    description: 'Elegancia en la simplicidad',
    component: JobHeroMinimalist,
    preview: '🎯 Clean • Typewriter • Geometric'
  },
  {
    id: 'cardflip',
    name: '🃏 Card Flip',
    description: 'Tarjetas interactivas con flip 3D',
    component: JobHeroCardFlip,
    preview: '🎪 3D Flip • Hologram • Interactive'
  },
  {
    id: 'dashboard',
    name: '📊 Dashboard',
    description: 'Analytics en tiempo real',
    component: JobHeroDashboard,
    preview: '📈 Charts • Metrics • Real-time'
  }
];

export default function ModuleSwitcher() {
  const [activeModule, setActiveModule] = useState('glassmorphism');
  const [showSwitcher, setShowSwitcher] = useState(true);

  const ActiveComponent = modules.find(m => m.id === activeModule)?.component || JobHeroGlassmorphism;

  return (
    <div className="relative">
      {/* Componente activo */}
      <ActiveComponent />

      {/* Panel de control flotante */}
      <div className={`fixed top-4 right-4 z-50 transition-all duration-300 ${showSwitcher ? 'translate-x-0' : 'translate-x-full'}`}>
        <div className="bg-black/90 backdrop-blur-md border border-white/20 rounded-2xl p-4 shadow-2xl max-w-xs">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-white font-bold text-sm">🎨 Cambiar Diseño</h3>
            <button
              onClick={() => setShowSwitcher(!showSwitcher)}
              className="text-white/60 hover:text-white text-xl"
            >
              {showSwitcher ? '×' : '☰'}
            </button>
          </div>

          <div className="space-y-2 max-h-96 overflow-y-auto">
            {modules.map((module) => (
              <button
                key={module.id}
                onClick={() => setActiveModule(module.id)}
                className={`w-full text-left p-3 rounded-lg transition-all duration-200 ${activeModule === module.id
                    ? 'bg-blue-600 text-white'
                    : 'bg-white/10 text-white/80 hover:bg-white/20'
                  }`}
              >
                <div className="font-medium text-sm">{module.name}</div>
                <div className="text-xs opacity-70 mt-1">{module.preview}</div>
              </button>
            ))}
          </div>

          <div className="mt-4 pt-4 border-t border-white/20">
            <div className="text-xs text-white/60 text-center">
              Haz click para cambiar entre diseños
            </div>
          </div>
        </div>
      </div>

      {/* Toggle button cuando el panel está oculto */}
      {!showSwitcher && (
        <button
          onClick={() => setShowSwitcher(true)}
          className="fixed top-4 right-4 z-50 bg-black/90 backdrop-blur-md border border-white/20 rounded-full w-12 h-12 flex items-center justify-center text-white hover:bg-white/20 transition-all duration-300"
        >
          🎨
        </button>
      )}
    </div>
  );
}
