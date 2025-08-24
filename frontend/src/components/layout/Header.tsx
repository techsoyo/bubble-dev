import { useCallback, memo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '../ui/button';
import { Sheet, SheetContent, SheetTrigger } from '../ui/sheet';
import { Menu } from 'lucide-react';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { LanguageSwitcher } from '../ui/language-switcher';
import { useAuth } from '../../contexts/AuthContext';

// Definir las claves de navegación para traducciones
const NAV_LINKS = [
  { key: 'nav.home', href: '#inicio' },
  { key: 'nav.jobs', href: '#empleos' },
  { key: 'nav.culture', href: '#culture' },
  { key: 'nav.blog', href: '#noticias' }
];

export const Header = memo(() => {
  const { user, isLoggedIn, logout, isLoading } = useAuth();
  const navigate = useNavigate();
  const { t } = useLanguage();

  // Función para navegación suave a secciones o rutas
  const handleNavigation = useCallback((href: string) => {
    // Si es una ruta (empieza con /), navegar directamente
    if (href.startsWith('/')) {
      navigate(href);
      return;
    }

    // Si es un anchor (empieza con #), hacer scroll
    const sectionId = href.replace('#', '');

    // Si no estamos en la homepage, navegar allí primero
    const currentPath = window.location.pathname;
    if (currentPath !== '/') {
      navigate('/', { state: { scrollTo: sectionId } });
      return;
    }

    // Si ya estamos en la homepage, hacer scroll directo
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  }, [navigate]);

  const handleSignout = useCallback(async () => {
    try {
      await logout();
      navigate('/');
    } catch (error) {
      console.error('Error cerrando sesión', error);
    }
  }, [logout, navigate]);

  if (isLoading) return null; // O un loader si prefieres

  return (
    <header className="sticky top-0 z-40 w-full flex justify-center items-start">
      <div
        className="flex h-16 items-center shadow-lg w-[97%]"
        style={{
          background: 'rgba(47, 47, 47, 0.6)',
          backdropFilter: 'blur(15px) saturate(180%)',
          borderRadius: '50px',
          marginTop: '8px',
          marginBottom: '18px',
          border: '1px solid rgba(255, 255, 255, 0.15)',
          boxShadow: '0 8px 32px rgba(0, 0, 0, 0.1)',
        }}
      >
        {/* Logo y links desplazados a la derecha */}
        <div className="flex items-center" style={{ marginLeft: '5%' }}>
          <Link to="/" className="flex items-center">
            <img src="/images/Vector2.png" alt="Logo" className="h-10 w-auto mr-4" />
          </Link>
          {/* Links */}
          <nav className="hidden md:flex gap-6">
            {NAV_LINKS.map((link) => (
              <button
                key={link.key}
                onClick={() => handleNavigation(link.href)}
                className="text-xs font-medium text-white hover:text-[#FF4785] transition-colors cursor-pointer"
              >
                {t(link.key)}
              </button>
            ))}
          </nav>
        </div>

        {/* A la derecha: solo idioma y, si está logueado, logout.
            Eliminado: botones "Iniciar sesión" y "Registrarse". */}
        <div className="hidden md:flex items-center gap-3 ml-auto mr-6">
          {isLoggedIn ? (
            <div className="flex items-center gap-3">
              <span className="text-white text-sm">
                {user?.email || 'Usuario'}
              </span>
              <Button
                className="bg-[#FF4785] hover:bg-[#FF3575] text-white border-white"
                onClick={handleSignout}
              >
                {t('auth.logout')}
              </Button>
            </div>
          ) : null}
          <LanguageSwitcher />
        </div>

        {/* Mobile: menú hamburguesa sin login/registro */}
        <div className="md:hidden ml-auto">
          <Sheet>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon">
                <Menu className="h-5 w-5 text-white" />
                <span className="sr-only">Toggle menu</span>
              </Button>
            </SheetTrigger>
            <SheetContent side="right">
              <div className="flex flex-col space-y-4 mt-8">
                {NAV_LINKS.map((link) => (
                  <button
                    key={link.key}
                    onClick={() => handleNavigation(link.href)}
                    className="text-base font-medium transition-colors hover:text-primary text-left cursor-pointer"
                  >
                    {t(link.key)}
                  </button>
                ))}
                <div className="flex flex-col space-y-2 mt-4">
                  {isLoggedIn ? (
                    <>
                      <div className="px-4 py-2 text-sm">
                        {user?.email || 'Usuario'}
                      </div>
                      <Button variant="ghost" className="w-full justify-start" onClick={handleSignout}>
                        {t('auth.logout')}
                      </Button>
                    </>
                  ) : null}
                  <LanguageSwitcher />
                </div>
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>
    </header>
  );
});

Header.displayName = 'Header';
