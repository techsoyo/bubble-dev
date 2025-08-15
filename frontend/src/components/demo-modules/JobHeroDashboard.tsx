// src/components/demo-modules/JobHeroDashboard.tsx
import React, { useState, useEffect } from 'react';
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

// Componente de gráfico animado
const AnimatedChart = ({ data, color, delay = 0 }: {
  data: number[],
  color: string,
  delay?: number
}) => {
  const [animatedData, setAnimatedData] = useState(data.map(() => 0));

  useEffect(() => {
    const timer = setTimeout(() => {
      const interval = setInterval(() => {
        setAnimatedData(prev =>
          prev.map((val, i) => {
            const target = data[i];
            const diff = target - val;
            return val + diff * 0.1;
          })
        );
      }, 50);

      return () => clearInterval(interval);
    }, delay);

    return () => clearTimeout(timer);
  }, [data, delay]);

  const maxValue = Math.max(...data);

  return (
    <div className="flex items-end space-x-2 h-32">
      {animatedData.map((value, index) => (
        <div
          key={index}
          className={`w-6 bg-gradient-to-t ${color} rounded-t`}
          style={{
            height: `${(value / maxValue) * 100}%`,
            transition: 'height 0.3s ease-out',
            minHeight: '4px'
          }}
        />
      ))}
    </div>
  );
};

// Componente de métrica animada
const AnimatedMetric = ({
  value,
  label,
  icon,
  color,
  delay = 0
}: {
  value: number,
  label: string,
  icon: string,
  color: string,
  delay?: number
}) => {
  const [animatedValue, setAnimatedValue] = useState(0);

  useEffect(() => {
    const timer = setTimeout(() => {
      const interval = setInterval(() => {
        setAnimatedValue(prev => {
          const diff = value - prev;
          if (Math.abs(diff) < 1) return value;
          return prev + diff * 0.1;
        });
      }, 50);

      return () => clearInterval(interval);
    }, delay);

    return () => clearTimeout(timer);
  }, [value, delay]);

  return (
    <div className={`bg-gradient-to-br ${color} p-6 rounded-2xl text-white shadow-lg transform transition-all duration-300 hover:scale-105`}>
      <div className="flex items-center justify-between mb-4">
        <span className="text-3xl">{icon}</span>
        <div className="text-right">
          <div className="text-3xl font-bold">
            {Math.round(animatedValue).toLocaleString()}
          </div>
          <div className="text-white/80 text-sm">{label}</div>
        </div>
      </div>
      <div className="w-full h-1 bg-white/20 rounded-full overflow-hidden">
        <div
          className="h-full bg-white/60 rounded-full transition-all duration-1000"
          style={{ width: `${(animatedValue / value) * 100}%` }}
        />
      </div>
    </div>
  );
};

// Componente de progreso circular
const CircularProgress = ({
  percentage,
  label,
  color,
  delay = 0
}: {
  percentage: number,
  label: string,
  color: string,
  delay?: number
}) => {
  const [animatedPercentage, setAnimatedPercentage] = useState(0);

  useEffect(() => {
    const timer = setTimeout(() => {
      const interval = setInterval(() => {
        setAnimatedPercentage(prev => {
          const diff = percentage - prev;
          if (Math.abs(diff) < 1) return percentage;
          return prev + diff * 0.05;
        });
      }, 50);

      return () => clearInterval(interval);
    }, delay);

    return () => clearTimeout(timer);
  }, [percentage, delay]);

  const circumference = 2 * Math.PI * 45;
  const strokeDasharray = circumference;
  const strokeDashoffset = circumference - (animatedPercentage / 100) * circumference;

  return (
    <div className="relative w-32 h-32">
      <svg className="w-32 h-32 transform -rotate-90" viewBox="0 0 100 100">
        {/* Círculo de fondo */}
        <circle
          cx="50"
          cy="50"
          r="45"
          stroke="rgba(255,255,255,0.1)"
          strokeWidth="8"
          fill="none"
        />
        {/* Círculo de progreso */}
        <circle
          cx="50"
          cy="50"
          r="45"
          stroke={color}
          strokeWidth="8"
          fill="none"
          strokeLinecap="round"
          strokeDasharray={strokeDasharray}
          strokeDashoffset={strokeDashoffset}
          className="transition-all duration-500"
        />
      </svg>
      <div className="absolute inset-0 flex items-center justify-center">
        <div className="text-center">
          <div className="text-2xl font-bold text-white">
            {Math.round(animatedPercentage)}%
          </div>
          <div className="text-xs text-white/70">{label}</div>
        </div>
      </div>
    </div>
  );
};

export default function JobHeroDashboard() {
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState('overview');

  // Datos simulados para el dashboard
  const metrics = {
    totalCandidates: 1247,
    activeJobs: 89,
    successfulMatches: 342,
    companyRating: 4.8
  };

  const chartData = {
    applications: [45, 52, 38, 61, 42, 73, 69, 45, 52, 61],
    interviews: [12, 18, 15, 22, 19, 28, 24, 16, 20, 25],
    hires: [3, 5, 4, 7, 6, 9, 8, 4, 6, 8]
  };

  const tabs = [
    { id: 'overview', label: 'Overview', icon: '📊' },
    { id: 'candidates', label: 'Candidatos', icon: '👥' },
    { id: 'analytics', label: 'Analytics', icon: '📈' },
    { id: 'settings', label: 'Config', icon: '⚙️' }
  ];

  return (
    <section className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-gray-900 to-black text-white relative overflow-hidden">
      {/* Fondo con grid */}
      <div
        className="absolute inset-0 opacity-5"
        style={{
          backgroundImage: `linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px),
                           linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px)`,
          backgroundSize: '30px 30px',
        }}
      />

      <div className="container mx-auto px-4 py-8 relative z-10">
        <div className="max-w-7xl mx-auto">

          {/* Header del Dashboard */}
          <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-12">
            <div>
              <h1 className="text-4xl md:text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500 mb-2">
                Talent Analytics
              </h1>
              <p className="text-gray-400 text-lg">Dashboard de gestión de talentos en tiempo real</p>
            </div>

            {/* Indicadores de estado */}
            <div className="flex items-center space-x-4 mt-4 md:mt-0">
              <div className="flex items-center space-x-2">
                <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse" />
                <span className="text-sm text-gray-300">Sistema Online</span>
              </div>
              <div className="text-sm text-gray-400">
                Actualizado: {new Date().toLocaleTimeString()}
              </div>
            </div>
          </div>

          {/* Navegación por tabs */}
          <div className="flex space-x-4 mb-8 overflow-x-auto">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center space-x-2 px-6 py-3 rounded-lg font-medium transition-all duration-300 whitespace-nowrap ${activeTab === tab.id
                  ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30'
                  : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white'
                  }`}
              >
                <span>{tab.icon}</span>
                <span>{tab.label}</span>
              </button>
            ))}
          </div>

          {/* Métricas principales */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <AnimatedMetric
              value={metrics.totalCandidates}
              label="Total Candidatos"
              icon="👥"
              color="from-blue-500 to-cyan-600"
              delay={200}
            />
            <AnimatedMetric
              value={metrics.activeJobs}
              label="Empleos Activos"
              icon="💼"
              color="from-purple-500 to-pink-600"
              delay={400}
            />
            <AnimatedMetric
              value={metrics.successfulMatches}
              label="Matches Exitosos"
              icon="🎯"
              color="from-green-500 to-teal-600"
              delay={600}
            />
            <AnimatedMetric
              value={metrics.companyRating * 20}
              label="Rating Promedio"
              icon="⭐"
              color="from-yellow-500 to-orange-600"
              delay={800}
            />
          </div>

          {/* Gráficos y Analytics */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            {/* Gráfico de aplicaciones */}
            <div className="lg:col-span-2 bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-6">
              <h3 className="text-xl font-bold mb-6 text-white">Tendencia de Aplicaciones</h3>
              <div className="space-y-4">
                <div>
                  <div className="flex justify-between text-sm mb-2">
                    <span className="text-gray-400">Aplicaciones</span>
                    <span className="text-blue-400">+23% vs mes anterior</span>
                  </div>
                  <AnimatedChart
                    data={chartData.applications}
                    color="from-blue-500 to-blue-600"
                    delay={1000}
                  />
                </div>

                <div>
                  <div className="flex justify-between text-sm mb-2">
                    <span className="text-gray-400">Entrevistas</span>
                    <span className="text-green-400">+15% vs mes anterior</span>
                  </div>
                  <AnimatedChart
                    data={chartData.interviews}
                    color="from-green-500 to-green-600"
                    delay={1200}
                  />
                </div>
              </div>
            </div>

            {/* Progreso circular */}
            <div className="bg-gray-800/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-6">
              <h3 className="text-xl font-bold mb-6 text-white">Performance</h3>
              <div className="space-y-6">
                <CircularProgress
                  percentage={78}
                  label="Tasa de Éxito"
                  color="#10b981"
                  delay={1400}
                />
                <div className="space-y-3">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Calidad de Matches</span>
                    <span className="text-green-400">Excelente</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Tiempo Promedio</span>
                    <span className="text-blue-400">12 días</span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-400">Satisfacción</span>
                    <span className="text-purple-400">4.8/5.0</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Panel de acciones */}
          <div className="bg-gradient-to-r from-gray-800/50 to-gray-900/50 backdrop-blur-sm border border-gray-700 rounded-2xl p-8">
            <div className="text-center mb-8">
              <h2 className="text-3xl font-bold text-white mb-4">
                ¿Listo para potenciar tu talento?
              </h2>
              <p className="text-gray-300 text-lg max-w-2xl mx-auto">
                Únete a nuestra plataforma y forma parte del ecosistema de talentos más dinámico del mercado
              </p>
            </div>

            <div className="flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-8">
              <Link to="/auth/register" className="group">
                <Button
                  className="w-64 h-14 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold text-lg rounded-xl overflow-hidden relative transform transition-all duration-300 hover:scale-105 border-0"
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
                  className="w-64 h-14 bg-gray-800 text-white font-semibold text-lg rounded-xl border border-gray-600 overflow-hidden relative transform transition-all duration-300 hover:scale-105"
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
      </div>
    </section>
  );
}
