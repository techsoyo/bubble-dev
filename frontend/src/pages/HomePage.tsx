// src/pages/HomePage.tsx
import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { getJobs } from '../services/ApiService';
import JobCard from './JobCard';
// ⬇️ Quitamos CultureCard porque usaremos la sección mock
// import { CultureCard } from '../components/CultureCard';
import { BlogCard } from '../components/BlogCard';
import ChatbotDecisionTree from '../components/ChatbotDecisionTree';
import { useLanguage } from '../lib/i18n/LanguageContext';
import { Button } from '../components/ui/button';
import { usePerformance } from '../contexts/PerformanceContext';
import useOptimizedData from '../hooks/useOptimizedData';
import JobHero from '../components/Jobhero';
import CultureSection from '@/components/CultureSection';

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

interface BlogPost {
  id: string;
  title: string;
  date: string;
  excerpt: string;
  to: string;
  image?: string;
}

type BackendJob = Job & {
  created_at?: string;
  posted_date?: string;
};

export default function HomePage() {
  const { t } = useLanguage();
  const { prefetch } = usePerformance();

  const {
    data: jobsResponse,
    loading: jobsLoading
  } = useOptimizedData<{ success: boolean; data: { jobs: BackendJob[] } }>({
    fetchFunction: getJobs,
    cacheKey: 'homepage_jobs',
    cacheDuration: 5 * 60 * 1000,
    timeout: 3000
  });

  // 🔹 Noticias desde traducciones (mantenemos esto tal cual)
  const getNewsData = () => {
    try {
      const data = t('homepage.newsData');
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  };
  const newsDataRaw = getNewsData();

  const jobs = React.useMemo(() => {
    if (!jobsResponse?.success || !Array.isArray(jobsResponse.data?.jobs)) return [];
    return jobsResponse.data.jobs.map(job => ({
      ...job,
      datePosted: job.created_at ?? job.posted_date ?? '',
    }));
  }, [jobsResponse]);

  const blogPosts: BlogPost[] = newsDataRaw.map((item: any) => ({
    id: item.id,
    title: item.title,
    date: new Date(item.date).toLocaleDateString(),
    excerpt: item.excerpt,
    to: `/blog/${item.slug}`
  }));

  const newsImagesByIndex = [
    '/images/job-interview.jpg',
    '/images/networking.jpg',
    '/images/web-launch.jpg'
  ];
  const newsImages: Record<number, string> = {
    1: '/images/job-interview.jpg',
    2: '/images/networking.jpg',
    3: '/images/web-launch.jpg'
  };

  useEffect(() => {
    window.scrollTo(0, 0);
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }
    return () => {
      if ('scrollRestoration' in history) {
        history.scrollRestoration = 'auto';
      }
    };
  }, []);

  useEffect(() => {
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
            <h2 id="jobs-heading" className="text-2xl md:text-3xl font-bold text-[#2F2F2F]">
              {t('homepage.featuredJobs')}
            </h2>
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

      {/* ✅ Sección Culture mock (sin API, sin traducciones) */}
      <CultureSection id="culture" />

      {/* Sección Noticias */}
      <section id="noticias" className="py-16 mx-auto w-[97%]" aria-labelledby="news-heading">
        <div className="container mx-auto px-4">
          <div className="flex justify-between items-center mb-8">
            <h2 id="news-heading" className="text-2xl md:text-3xl font-bold text-[#2F2F2F]">
              {t('homepage.news')}
            </h2>
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
                      image={
                        newsImagesByIndex[index] ||
                        newsImages[(blog.id as unknown) as number] ||
                        '/images/job-interview.jpg'
                      }
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
