import React from 'react';
import { FaUsers, FaLightbulb, FaHandsHelping, FaHeart } from 'react-icons/fa';

interface CultureCardProps {
  iconName?: string | null;
  title: string;
  desc: string;
}

const iconComponents: Record<string, React.ComponentType<any>> = {
  FaUsers,
  FaLightbulb,
  FaHandsHelping,
  FaHeart,
};

export const CultureCard: React.FC<CultureCardProps> = ({ iconName, title, desc }) => {
  const [mousePos, setMousePos] = React.useState({ x: 50, y: 50 });
  const [isHover, setIsHover] = React.useState(false);

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    setMousePos({ x, y });
    setIsHover(true);
  };

  const handleMouseLeave = () => {
    setMousePos({ x: 50, y: 50 });
    setIsHover(false);
  };

  return (
    <div
      className="text-center p-6 rounded-lg shadow-sm transition-shadow border border-gray-100 relative overflow-visible cursor-pointer"
      style={{
        background: 'rgba(255,71,133,0.65)',
        boxShadow: isHover
          ? '0 8px 32px 0 rgba(31,38,135,0.18), 0 8px 24px 8px rgba(255,71,133,0.25), 0 16px 40px 0 rgba(47,47,47,0.18)'
          : '0 8px 32px 0 rgba(31, 38, 135, 0.18), 0 4px 16px 0 rgba(255,71,133,0.10)',
        backdropFilter: 'blur(16px) saturate(180%)',
        WebkitBackdropFilter: 'blur(16px) saturate(180%)',
        borderBottom: '8px solid rgba(255,71,133,0.25)',
        borderRight: '4px solid rgba(255,71,133,0.18)',
        transform: isHover ? 'translateY(-6px) scale3d(1.03,1.03,1.03)' : 'none',
        transition: 'box-shadow 0.3s, transform 0.3s',
      }}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
    >
      <div
        className="pointer-events-none absolute left-0 top-0 w-full h-full z-10 rounded-lg"
        style={{
          background: `radial-gradient(circle at ${mousePos.x}% ${mousePos.y}%, rgba(0,0,0,0.18) 0%, rgba(0,0,0,0.10) 40%, transparent 80%)`,
          opacity: 0.7,
          transition: 'opacity 0.3s',
        }}
      />
      {iconName && iconComponents[iconName] &&
        React.createElement(iconComponents[iconName], {
          size: 32,
          className: 'mx-auto mb-4 text-white'
        })
      }
      <h3 className="text-xl font-semibold mb-3 text-white">{title}</h3>
      <p className="text-white">{desc}</p>
    </div>
  );
};
