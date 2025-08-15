// src/components/ui/dashboard-header.tsx
import React from 'react';
import { Button } from './button';

interface TabConfig {
  key: string;
  label: string;
  shortLabel?: string;
  icon?: string;
}

interface DashboardHeaderProps {
  title: string;
  subtitle?: string;
  activeTab?: string;
  tabs?: TabConfig[];
  onTabChange?: (tab: string) => void;
  actions?: React.ReactNode;
  breadcrumbs?: Array<{
    label: string;
    href?: string;
  }>;
}

export const DashboardHeader = React.memo(({
  title,
  subtitle,
  activeTab,
  tabs = [],
  onTabChange,
  actions,
  breadcrumbs = []
}: DashboardHeaderProps) => {
  return (
    <div className="space-y-4">
      {/* Breadcrumbs */}
      {breadcrumbs.length > 0 && (
        <nav className="flex items-center space-x-2 text-sm text-gray-600" aria-label="Breadcrumb">
          {breadcrumbs.map((breadcrumb, index) => (
            <React.Fragment key={index}>
              {index > 0 && <span>/</span>}
              <span className={index === breadcrumbs.length - 1 ? "text-[#FF4785] font-medium" : ""}>
                {breadcrumb.label}
              </span>
            </React.Fragment>
          ))}
        </nav>
      )}

      {/* Header principal */}
      <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center bg-[#FF4785] rounded-md p-4 lg:p-5 font-poppins">
        <div className="mb-4 lg:mb-0">
          <h1 className="text-xl md:text-2xl font-bold text-[#2F2F2F]">{title}</h1>
          {subtitle && (
            <p className="text-white text-sm md:text-base">{subtitle}</p>
          )}
        </div>

        {/* Navegación por tabs o acciones personalizadas */}
        {tabs.length > 0 && onTabChange ? (
          <div className="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
            {tabs.map(tab => (
              <Button
                key={tab.key}
                className={`flex-1 sm:flex-initial ${activeTab === tab.key
                    ? 'bg-[#2F2F2F] text-white'
                    : 'bg-white text-[#FF4785]'
                  } transition-colors`}
                onClick={() => onTabChange(tab.key)}
              >
                {tab.icon && <span className="mr-2">{tab.icon}</span>}
                <span className="hidden sm:inline">{tab.label}</span>
                <span className="sm:hidden">{tab.shortLabel || tab.label}</span>
              </Button>
            ))}
          </div>
        ) : actions ? (
          <div className="flex gap-2 mt-4 lg:mt-0">
            {actions}
          </div>
        ) : null}
      </div>
    </div>
  );
});

DashboardHeader.displayName = 'DashboardHeader';
