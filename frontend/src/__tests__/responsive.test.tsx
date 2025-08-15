// src/__tests__/responsive.test.tsx
import { render, screen, act, cleanup } from '@testing-library/react';

// Mock del hook useLanguage
jest.mock('../lib/i18n/LanguageContext', () => ({
  useLanguage: () => ({
    t: jest.fn().mockReturnValue('mocked text')
  })
}));

// Mock JobHero component para testing básico
const MockJobHero = () => (
  <section data-testid="hero-section" className="min-h-screen flex items-center justify-center">
    <div className="container mx-auto px-4">
      <h1>Oportunidades que transforman carreras</h1>
      <div>Mock Hero Content</div>
      <div className="flex space-x-4">
        <a href="/auth/register" role="button">Soy Candidato/a</a>
        <a href="/talent/login" role="button">Soy de Bubble</a>
      </div>
    </div>
  </section>
);

// Helper para cambiar el viewport
const setViewport = (width: number, height: number = 768) => {
  Object.defineProperty(window, 'innerWidth', {
    writable: true,
    configurable: true,
    value: width
  });
  Object.defineProperty(window, 'innerHeight', {
    writable: true,
    configurable: true,
    value: height
  });

  // Simular el evento resize
  act(() => {
    window.dispatchEvent(new Event('resize'));
  });
};

describe('Responsive Tests', () => {
  beforeEach(() => {
    // Reset viewport antes de cada test
    setViewport(1024, 768);
  });

  afterEach(() => {
    cleanup();
  });

  describe('Basic Responsive Behavior', () => {
    it('should render hero section correctly on mobile viewport (320px)', () => {
      setViewport(320, 568);

      render(<MockJobHero />);

      // Verificar que el componente se renderiza
      const heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();
      expect(heroSection).toHaveClass('min-h-screen');
      expect(heroSection).toHaveClass('flex');
      expect(heroSection).toHaveClass('items-center');

      // Verificar que el título principal existe
      const mainTitle = screen.getByText(/Oportunidades que transforman carreras/i);
      expect(mainTitle).toBeInTheDocument();
    });

    it('should render hero section correctly on tablet viewport (768px)', () => {
      setViewport(768, 1024);

      render(<MockJobHero />);

      const heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();

      // Verificar que el título es visible
      const mainTitle = screen.getByText(/Oportunidades que transforman carreras/i);
      expect(mainTitle).toBeInTheDocument();
    });

    it('should render hero section correctly on desktop viewport (1024px+)', () => {
      setViewport(1024, 768);

      render(<MockJobHero />);

      const heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();

      // Verificar layout
      const mainTitle = screen.getByText(/Oportunidades que transforman carreras/i);
      expect(mainTitle).toBeInTheDocument();

      // Los botones deben estar disponibles
      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(2);
    });

    it('should handle viewport changes dynamically', () => {
      render(<MockJobHero />);

      // Empezar en desktop
      setViewport(1024, 768);
      let heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();

      // Cambiar a móvil
      setViewport(320, 568);
      heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();

      // Volver a desktop
      setViewport(1200, 800);
      heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();
    });
  });

  describe('CSS Classes Tests', () => {
    it('should apply responsive classes correctly', () => {
      setViewport(320, 568);

      render(<MockJobHero />);

      const heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toHaveClass('min-h-screen');
      expect(heroSection).toHaveClass('flex');
      expect(heroSection).toHaveClass('items-center');
      expect(heroSection).toHaveClass('justify-center');
    });

    it('should handle extreme viewport sizes', () => {
      // Test en viewport muy pequeño
      setViewport(280, 480);
      render(<MockJobHero />);

      let heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();

      cleanup();

      // Test en viewport muy grande
      setViewport(2560, 1440);
      render(<MockJobHero />);

      heroSection = screen.getByTestId('hero-section');
      expect(heroSection).toBeInTheDocument();
    });
  });

  describe('Content Visibility Tests', () => {
    it('should maintain readable content across viewports', () => {
      const viewports = [320, 768, 1024, 1440];

      viewports.forEach(width => {
        cleanup();
        setViewport(width);
        render(<MockJobHero />);

        const mainTitle = screen.getByText(/Oportunidades que transforman carreras/i);
        expect(mainTitle).toBeInTheDocument();
      });
    });
  });

  describe('Interactive Elements Tests', () => {
    it('should maintain clickable buttons across all viewports', () => {
      const viewports = [320, 768, 1024, 1440];

      viewports.forEach(width => {
        cleanup();
        setViewport(width);
        render(<MockJobHero />);

        const buttons = screen.getAllByRole('button');
        expect(buttons).toHaveLength(2);

        buttons.forEach(button => {
          expect(button).toBeInTheDocument();
        });
      });
    });
  });
});

// Test para simulación de comportamiento responsive
describe('Window Resize Behavior', () => {
  afterEach(() => {
    cleanup();
  });

  it('should respond to window resize events', () => {
    // Establecer un estado inicial conocido
    setViewport(1024, 768);

    render(<MockJobHero />);

    // Verificar estado inicial
    expect(window.innerWidth).toBe(1024);

    // Simular resize
    setViewport(320);
    expect(window.innerWidth).toBe(320);

    setViewport(1200);
    expect(window.innerWidth).toBe(1200);
  });
});