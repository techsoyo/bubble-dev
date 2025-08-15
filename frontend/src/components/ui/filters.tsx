// src/components/ui/filters.tsx
import React from 'react';
import { Button } from './button';
import { useLanguage } from '../../lib/i18n/LanguageContext';

interface FilterOption {
  value: string;
  label: string;
  icon?: string;
}

interface FiltersProps {
  searchTerm: string;
  onSearchChange: (value: string) => void;
  searchPlaceholder?: string;

  statusFilter?: string;
  onStatusChange?: (value: string) => void;
  statusOptions?: FilterOption[];

  departmentFilter?: string;
  onDepartmentChange?: (value: string) => void;
  departmentOptions?: FilterOption[];

  customFilters?: Array<{
    value: string;
    onChange: (value: string) => void;
    options: FilterOption[];
    placeholder: string;
  }>;

  onClearFilters: () => void;
  showClearButton?: boolean;
}

export const Filters = React.memo(({
  searchTerm,
  onSearchChange,
  searchPlaceholder = "🔍 Buscar...",

  statusFilter,
  onStatusChange,
  statusOptions,

  departmentFilter,
  onDepartmentChange,
  departmentOptions,

  customFilters = [],
  onClearFilters,
  showClearButton = true
}: FiltersProps) => {
  const { t } = useLanguage();

  // Opciones por defecto con traducciones
  const defaultStatusOptions = [
    { value: '', label: t('recruiterDashboard.allStatuses') },
    { value: 'Under Review', label: `👀 ${t('recruiterDashboard.underReview')}`, icon: '👀' },
    { value: 'Interview', label: '💬 Entrevista', icon: '💬' },
    { value: 'Technical Test', label: '🧪 Prueba técnica', icon: '🧪' },
    { value: 'Offer Extended', label: '🎉 Oferta extendida', icon: '🎉' },
    { value: 'Rejected', label: '🚫 No seleccionado', icon: '🚫' }
  ];

  const defaultDepartmentOptions = [
    { value: '', label: t('recruiterDashboard.allDepartments') },
    { value: 'Engineering', label: '👨‍💻 Engineering', icon: '👨‍💻' },
    { value: 'Marketing', label: '📈 Marketing', icon: '📈' },
    { value: 'Sales', label: '💼 Sales', icon: '💼' },
    { value: 'Design', label: '🎨 Design', icon: '🎨' },
    { value: 'HR', label: '👥 HR', icon: '👥' }
  ];

  // Usar las opciones proporcionadas o las por defecto con traducciones
  const finalStatusOptions = statusOptions || defaultStatusOptions;
  const finalDepartmentOptions = departmentOptions || defaultDepartmentOptions;
  const hasActiveFilters = searchTerm || statusFilter || departmentFilter ||
    customFilters.some(filter => filter.value);

  return (
    <div className="bg-white rounded-lg shadow-sm border p-4 mb-6">
      <div className="flex flex-wrap gap-4">
        {/* Búsqueda */}
        <div className="flex-1 min-w-[250px]">
          <input
            type="text"
            placeholder={searchPlaceholder}
            value={searchTerm}
            onChange={(e) => onSearchChange(e.target.value)}
            className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#FF4785] focus:border-transparent"
          />
        </div>

        {/* Filtro por estado */}
        {onStatusChange && (
          <div className="min-w-[180px]">
            <select
              value={statusFilter}
              onChange={(e) => onStatusChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#FF4785] focus:border-transparent"
            >
              {statusOptions?.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        )}

        {/* Filtro por departamento */}
        {onDepartmentChange && (
          <div className="min-w-[180px]">
            <select
              value={departmentFilter}
              onChange={(e) => onDepartmentChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#FF4785] focus:border-transparent"
            >
              {departmentOptions?.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        )}

        {/* Filtros personalizados */}
        {customFilters?.map((filter, index) => (
          <div key={index} className="min-w-[180px]">
            <select
              value={filter.value}
              onChange={(e) => filter.onChange(e.target.value)}
              className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#FF4785] focus:border-transparent"
            >
              {filter.options.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        ))}

        {/* Botón limpiar filtros */}
        {showClearButton && hasActiveFilters && (
          <Button
            variant="outline"
            size="sm"
            onClick={onClearFilters}
            className="text-gray-600 border-gray-300 hover:bg-gray-50"
          >
            ✕ Limpiar
          </Button>
        )}
      </div>
    </div>
  );
});

Filters.displayName = 'Filters';
