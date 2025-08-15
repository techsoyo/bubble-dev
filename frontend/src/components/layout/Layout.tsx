// src/components/layout/Layout.tsx
import React, { memo } from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Footer } from './Footer';

interface LayoutProps {
  children?: React.ReactNode;
}

// Aplicar memo para evitar re-renderizaciones innecesarias
export const Layout = memo(function Layout({ children }: LayoutProps) {
  return (
    <div className="flex flex-col min-h-screen" style={{ background: 'transparent' }}>
      <Header />
      <main className="flex-1 py-5 mx-auto w-[97%]" style={{ background: 'transparent' }}>
        {/* Renderizamos children o <Outlet />, pero nunca el header dentro del contenido */}
        {children ?? <Outlet />}
      </main>
      {/* El Footer solo se renderiza aquí, no debe estar en el contenido de las páginas */}
      <Footer />
    </div>
  );
});
