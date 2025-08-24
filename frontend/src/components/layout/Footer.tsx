
import { memo, useMemo } from 'react';
import { Link } from 'react-router-dom';

export const Footer = memo(() => {
  const currentYear = useMemo(() => new Date().getFullYear(), []);
  return (
    <footer
      className="fixed left-0 right-0 text-pink-400 py-2 z-30"
      style={{
        background: 'rgba(47, 47, 47, 0.6)',
        backdropFilter: 'blur(15px) saturate(180%)',
        borderTopLeftRadius: '25px',
        borderTopRightRadius: '25px',
        width: '100%',
        minHeight: '8vh',
        boxShadow: '0 -8px 32px rgba(0, 0, 0, 0.1)',
        border: '1px solid rgba(255, 255, 255, 0.15)',
        borderBottom: 'none',
        bottom: '-5%',
      }}
    >
      <div className="max-w-6xl mx-auto flex flex-col items-center justify-center h-full">
        <img src="/images/Vector1.png" alt="bubblegum logo" className="h-6 mb-1" style={{ filter: 'drop-shadow(0 0 2px #ff4f9a)' }} />
        <nav className="w-full flex flex-wrap justify-center gap-x-6 gap-y-1 mb-1 text-xs font-medium">
          <Link to="/terms" className="hover:text-white transition-colors">Terms & Conditions</Link>
          <Link to="/" className="hover:text-white transition-colors">Home</Link>
          <Link to="#services" className="hover:text-white transition-colors">Services</Link>
          <Link to="#project" className="hover:text-white transition-colors">Project</Link>
          <Link to="#about" className="hover:text-white transition-colors">About Us</Link>
          <Link to="#contact" className="hover:text-white transition-colors">Contact</Link>
          <Link to="/privacy" className="hover:text-white transition-colors">Privacy Policy</Link>
        </nav>
        <p className="text-xs text-center text-pink-400/80">
          © {currentYear} Bubblegum.agency. All rights reserved.
        </p>
      </div>
    </footer>
  );
});

Footer.displayName = 'Footer';
