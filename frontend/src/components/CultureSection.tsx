import * as React from 'react';

export type CultureCard = {
  title: string;
  description: string;
  emoji?: string;
  cta?: { label: string; href: string };
};

const DEFAULT_CARDS: CultureCard[] = [
  {
    title: 'Aprendizaje continuo',
    description: 'Compartimos conocimiento y crecemos en equipo. Formación interna y feedback real.',
    emoji: '📚',
    cta: { label: 'Conoce más', href: '/about#learning' },
  },
  {
    title: 'Transparencia',
    description: 'Comunicación abierta y decisiones claras. Aquí se habla con datos.',
    emoji: '🔍',
    cta: { label: 'Nuestra filosofía', href: '/about#transparency' },
  },
  {
    title: 'Impacto',
    description: 'Construimos producto con foco en valor real para usuarios y negocio.',
    emoji: '🚀',
    cta: { label: 'Cómo trabajamos', href: '/about#impact' },
  },
];

type Props = {
  id?: string;                  // para anclar desde el header (#cultura, etc.)
  title?: string;               // título de la sección
  cards?: CultureCard[];        // permite sobreescribir tarjetas si quieres
  style?: React.CSSProperties;  // estilos extra opcionales
};

export default function CultureSection({
  id = 'cultura',
  title = 'Nuestra cultura',
  cards = DEFAULT_CARDS,
  style,
}: Props) {
  const headingId = `${id || 'culture'}-heading`;

  const grid: React.CSSProperties = {
    display: 'grid',
    gap: 16,
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', // responsive sin window
    alignItems: 'stretch',
  };

  const card: React.CSSProperties = {
    border: '1px solid #eee',
    borderRadius: 12,
    padding: 16,
    boxShadow: '0 1px 3px rgba(0,0,0,.06)',
    background: '#fff',
  };

  const h2: React.CSSProperties = { fontSize: 22, fontWeight: 700, margin: '8px 0 16px' };
  const h3: React.CSSProperties = { fontSize: 18, fontWeight: 600, margin: '8px 0' };
  const p: React.CSSProperties = { color: '#444', lineHeight: 1.6, margin: '0 0 8px' };
  const cta: React.CSSProperties = { display: 'inline-block', marginTop: 8, textDecoration: 'none' };

  return (
    <section id={id} aria-labelledby={headingId} style={{ marginTop: 32, marginBottom: 32, ...style }}>
      <h2 id={headingId} style={h2}>{title}</h2>
      <div style={grid}>
        {cards.map((c, i) => (
          <article key={i} style={card}>
            <div style={{ fontSize: 32 }}>{c.emoji ?? '✨'}</div>
            <h3 style={h3}>{c.title}</h3>
            <p style={p}>{c.description}</p>
            {c.cta && (
              <a href={c.cta.href} style={cta} aria-label={c.cta.label}>
                {c.cta.label} →
              </a>
            )}
          </article>
        ))}
      </div>
    </section>
  );
}
