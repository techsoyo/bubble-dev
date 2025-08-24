// src/pages/HomePage.tsx

import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
// import { Layout } from '../components/layout/Layout';
import { getJobs } from '../services/ApiService';
import JobCard from './JobCard';
import { CultureCard } from '../components/CultureCard';
import { BlogCard } from '../components/BlogCard';
import ChatbotDecisionTree from '../components/ChatbotDecisionTree';
import { useLanguage } from '../lib/i18n/LanguageContext';
import { en, es } from '../lib/i18n/translations';
import { Button } from '../components/ui/button'; // ⬅ añadido para los CTA del hero
import { usePerformance } from '../contexts/PerformanceContext';
import useOptimizedData from '../hooks/useOptimizedData';
// import LazyImage from '../components/ui/LazyImage';
import JobHero from '../components/Jobhero';

// Definir interfaces para los tipos
interface Job {
  id: string;
  title: string;
  description: string;
  location: string;
  employment_type?: string;
  type: string;
  category: string;
  created_at?: string;
  datePosted: string;
  company_name?: string;
}

// El backend de cultura devuelve campos id, title y description.
// En el frontend utilizamos `desc` en lugar de `description` y un icono opcional.
interface CultureItem {
  id: string;
  title: string;
  desc: string;
  iconName?: string | null;
}

// Para las entradas de blog/noticias solo necesitamos id, title, date, excerpt y to
interface BlogPost {
  id: string;
  title: string;
  date: string;
  excerpt: string;
  to: string;
  image?: string; // Nueva prop para imagen
}

type BackendJob = Job & {
  created_at?: string;
  posted_date?: string;
};

export default function HomePage() {
  const { t } = useLanguage();
  const { prefetch } = usePerformance();

  // Usar el hook optimizado para cargar datos
  const {
    data: jobsData,
    loading: jobsLoading
  } = useOptimizedData<{ success: boolean; data: BackendJob[] }>({
    fetchFunction: getJobs,
    cacheKey: 'homepage_jobs',
    cacheDuration: 5 * 60 * 1000, // 5 minutos
    timeout: 3000
  });

  // Obtener datos estáticos de cultura y noticias desde las traducciones
  // Usando funciones auxiliares para acceder a arrays anidados
  const getCultureData = () => {
    try {
      const data = t('homepage.cultureData');
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  };

  const getNewsData = () => {
    try {
      const data = t('homepage.newsData');
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  };

  const cultureDataRaw = getCultureData();
  const newsDataRaw = getNewsData();

  // Convertir los datos a los formatos esperados
  const jobs = React.useMemo(() => {
    if (!jobsData?.success || !Array.isArray(jobsData.data)) return [];

    return jobsData.data.map(job => ({
      ...job,
      datePosted: job.created_at ?? job.posted_date ?? '',
    }));
  }, [jobsData]);

  const cultureItems = cultureDataRaw.map((item: any) => ({
    id: item.id || Math.random().toString(),
    title: item.title,
    desc: item.description,
    iconName: item.icon
  }));

  const blogPosts = newsDataRaw.map((item: any) => ({
    id: item.id,
    title: item.title,
    date: new Date(item.date).toLocaleDateString(),
    excerpt: item.excerpt,
    to: `/blog/${item.slug}`
  }));

  // Mapeo de imágenes para las tarjetas de noticias (usando índices)
  const newsImagesByIndex = [
    '/images/job-interview.jpg',        // Índice 0 - Consejos para tu entrevista técnica
    '/images/networking.jpg',         // Índice 1 - Evento de Networking en Madrid  
    '/images/web-launch.jpg'            // Índice 2 - Lanzamiento de nuestra nueva plataforma
  ];

  // Mapeo de imágenes para las tarjetas de noticias (usando IDs)
  const newsImages = {
    1: '/images/job-interview.jpg',        // Consejos para tu entrevista técnica
    2: '/images/networking.jpg',         // Evento de Networking en Madrid  
    3: '/images/web-launch.jpg'            // Lanzamiento de nuestra nueva plataforma
  };

  // Asegurar que siempre se inicie desde el top al cargar/refrescar
  useEffect(() => {
    // Scroll inmediato al inicio
    window.scrollTo(0, 0);

    // También configurar el scroll restoration para esta página
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }

    // Cleanup: restaurar el comportamiento por defecto al desmontar
    return () => {
      if ('scrollRestoration' in history) {
        history.scrollRestoration = 'auto';
      }
    };
  }, []); // Solo al montar el componente

  // Prefetch de rutas importantes al cargar la homepage
  useEffect(() => {
    // Prefetch solo después de que la página esté completamente cargada
    if (!jobsLoading) {
      setTimeout(() => {
        prefetch('/jobs', 'document');
        prefetch('/login', 'document');
        prefetch('/register', 'document');
      }, 1000);
    }
  }, [jobsLoading, prefetch]);

  const featuredJobs = [...jobs]
    .sort((a, b) => {
      const dateA = new Date(a.datePosted || a.created_at || 0).getTime();
      const dateB = new Date(b.datePosted || b.created_at || 0).getTime();
      return dateB - dateA;
    })
    .slice(0, 12);

  return (
    <>
      {/* Sección Inicio */}
      <section id="inicio">
        <JobHero />
      </section>

      {/* Sección Empleos */}
      <section id="empleos" className="py-16 mx-auto w-[97%]" aria-labelledby="jobs-heading">
        <div className="container mx-auto px-4">
          <div className="flex justify-between items-center mb-8">
            <h2 id="jobs-heading" className="text-2xl md:text-3xl font-bold text-[#2F2F2F]">{t('homepage.featuredJobs')}</h2>
          </div>

          {jobsLoading ? (
            <div className="text-center py-8">
              <p>{t('homepage.loadingJobs')}</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 auto-rows-fr">
              {featuredJobs.slice(0, 6).map((job, index) => (
                <div
                  key={job.id}
                  className="aos-card rounded-lg p-4 min-h-[320px] flex flex-col justify-between"
                  data-aos="fade-up"
                  data-aos-delay={index * 200}
                  data-aos-anchor-placement="top-bottom"
                >
                  <JobCard
                    id={job.id}
                    title={job.title}
                    description={job.description}
                    location={job.location}
                    type={job.type}
                    category={job.category}
                    datePosted={job.posted_date || job.created_at}
                  />
                </div>
              ))}
            </div>
          )}

          {/* Botón Ver Más - Solo si hay más de 6 trabajos */}
          {!jobsLoading && featuredJobs.length > 6 && (
            <div
              className="text-center mt-8"
              data-aos="fade-up"
              data-aos-delay="1100"
              data-aos-anchor-placement="top-bottom"
            >
              <Link to="/jobs">
                <Button
                  variant="outline"
                  className="px-8 py-3 text-[#FF4785] border-[#FF4785] bg-gradient-to-br from-[#e0e4e5] to-[#f2f6f9] shadow-[inset_-2px_2px_white,_-15px_15px_30px_rgba(0,0,0,0.2)] hover:shadow-[inset_-2px_2px_white,_-20px_20px_35px_rgba(0,0,0,0.25)] hover:bg-[#FF4785] hover:text-white transition-all duration-300 font-medium"
                >
                  {t('homepage.viewMoreJobs')} →
                </Button>
              </Link>
            </div>
          )}

        </div>
      </section>

      {/* Sección Culture */}
      <section id="culture" className="py-16 mx-auto w-[97%]" aria-labelledby="culture-heading">
        <div className="container mx-auto px-4">
          <div className="flex justify-between items-center mb-8">
            <h2 id="culture-heading" className="text-2xl md:text-3xl font-bold text-[#2F2F2F]">{t('homepage.culture')}</h2>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {cultureItems.length > 0 ? (
              cultureItems.map((item, index) => (
                <div
                  key={item.id || index}
                  className="aos-card"
                  data-aos="zoom-in"
                  data-aos-delay={index * 200}
                  data-aos-anchor-placement="top-bottom"
                >
                  <CultureCard
                    iconName={item.iconName}
                    title={item.title}
                    desc={item.desc}
                  />
                </div>
              ))
            ) : (
              <div className="col-span-full text-center py-8">
                <p>{t('homepage.noCultureContent')}</p>
              </div>
            )}
          </div>
        </div>
      </section>

      {/* Sección Noticias */}
      <section id="noticias" className="py-16 mx-auto w-[97%]" aria-labelledby="news-heading">
        <div className="container mx-auto px-4">
          <div className="flex justify-between items-center mb-8">
            <h2 id="news-heading" className="text-2xl md:text-3xl font-bold text-[#2F2F2F]">{t('homepage.news')}</h2>
            {/* <Link to="/blog" className="text-[#FF4785] hover:underline" aria-label={t('general.more')}>
              {t('general.more')} →
            </Link> */}
          </div>
          <div className="flex justify-center">
            <div className="grid w-fit grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
              {blogPosts.length > 0 ? (
                blogPosts.map((blog, index) => (
                  <div
                    key={blog.id}
                    data-aos="slide-left"
                    data-aos-delay={index * 200}
                    data-aos-anchor-placement="top-bottom"
                  >
                    <BlogCard
                      id={blog.id}
                      title={blog.title}
                      date={blog.date}
                      excerpt={blog.excerpt}
                      to={blog.to}
                      image={newsImagesByIndex[index] || newsImages[blog.id] || '/images/job-interview.jpg'}
                    />
                  </div>
                ))
              ) : (
                <div className="col-span-full text-center py-8">
                  <p>{t('homepage.noArticlesAvailable')}</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* Chatbot flotante - siempre visible */}
      <ChatbotDecisionTree />
    </>
  );
}
