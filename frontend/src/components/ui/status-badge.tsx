// src/components/ui/status-badge.tsx
import React, { useMemo } from 'react';

interface StatusBadgeProps {
  status: string;
  size?: 'sm' | 'md' | 'lg';
  variant?: 'default' | 'compact';
}

export const StatusBadge = React.memo(({
  status,
  size = 'md',
  variant = 'default'
}: StatusBadgeProps) => {
  const config = useMemo(() => {
    const configs: Record<string, { color: string; icon: string; label: string }> = {
      'Under Review': { color: 'bg-yellow-100 text-yellow-800 border-yellow-200', icon: '👀', label: 'En Revisión' },
      'Interview': { color: 'bg-blue-100 text-blue-800 border-blue-200', icon: '💬', label: 'Entrevista' },
      'Technical Test': { color: 'bg-purple-100 text-purple-800 border-purple-200', icon: '🧪', label: 'Prueba Técnica' },
      'Second Interview': { color: 'bg-indigo-100 text-indigo-800 border-indigo-200', icon: '🔄', label: 'Segunda Entrevista' },
      'Final Interview': { color: 'bg-orange-100 text-orange-800 border-orange-200', icon: '🎯', label: 'Entrevista Final' },
      'Reference Check': { color: 'bg-cyan-100 text-cyan-800 border-cyan-200', icon: '📞', label: 'Verificación' },
      'Offer Extended': { color: 'bg-green-100 text-green-800 border-green-200', icon: '🎉', label: 'Oferta Extendida' },
      'Offer Accepted': { color: 'bg-emerald-100 text-emerald-800 border-emerald-200', icon: '✅', label: 'Oferta Aceptada' },
      'Offer Declined': { color: 'bg-red-100 text-red-800 border-red-200', icon: '❌', label: 'Oferta Rechazada' },
      'Rejected': { color: 'bg-red-100 text-red-800 border-red-200', icon: '🚫', label: 'No Seleccionado' },
      'On Hold': { color: 'bg-gray-100 text-gray-800 border-gray-200', icon: '⏸️', label: 'En Espera' },
      'Withdrawn': { color: 'bg-slate-100 text-slate-800 border-slate-200', icon: '🔙', label: 'Retirado' },
      'Interview Scheduled': { color: 'bg-blue-100 text-blue-800 border-blue-200', icon: '📅', label: 'Entrevista Programada' },
      'Active': { color: 'bg-green-100 text-green-800 border-green-200', icon: '✅', label: 'Activo' },
      'Inactive': { color: 'bg-gray-100 text-gray-800 border-gray-200', icon: '⏸️', label: 'Inactivo' },
      'Pending': { color: 'bg-yellow-100 text-yellow-800 border-yellow-200', icon: '⏳', label: 'Pendiente' },
      'Completed': { color: 'bg-green-100 text-green-800 border-green-200', icon: '✅', label: 'Completado' },
      'In Progress': { color: 'bg-blue-100 text-blue-800 border-blue-200', icon: '🔄', label: 'En Progreso' },
      'Cancelled': { color: 'bg-red-100 text-red-800 border-red-200', icon: '❌', label: 'Cancelado' }
    };
    return configs[status] || { color: 'bg-gray-100 text-gray-800 border-gray-200', icon: '📋', label: status };
  }, [status]);

  const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-3 py-1 text-xs',
    lg: 'px-4 py-1.5 text-sm'
  };

  const iconSizes = {
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-base'
  };

  return (
    <span className={`inline-flex items-center gap-1 rounded-full font-medium border ${config.color} ${sizeClasses[size]}`}>
      <span className={iconSizes[size]}>{config.icon}</span>
      {variant === 'default' && config.label}
      {variant === 'compact' && status}
    </span>
  );
});

StatusBadge.displayName = 'StatusBadge';
