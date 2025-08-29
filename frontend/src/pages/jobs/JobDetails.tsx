import { useParams, Link } from 'react-router-dom';
import { HashLink } from '@xzar90/react-router-hash-link';
import { useState, useEffect } from 'react';
import { Button } from '../../components/ui/button';
import { Separator } from '../../components/ui/separator';
import { getJob, getJobRequirements, getJobBenefits, getJobSkills } from '../../services/ApiService';
import { useLanguage } from '../../lib/i18n/LanguageContext';
import { translateJobData } from '../../lib/googleTranslation';
import {
  MapPin,
  Calendar,
  Briefcase,
  CheckCircle2,
  ChevronRight,
} from 'lucide-react';

// ✅ Card component correctamente definido
function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return (
    <div className={`rounded-lg shadow-sm border border-gray-100 ${className}`}>
      {children}
    </div>
  );
}

export default function JobDetailsPage() {
  const { id } = useParams<{ id: string }>();
  const { language, t } = useLanguage(); // ✅ Usar hook de idioma

  interface Job {
    id: string;
    title: string;
    description?: string;
    location?: string;
    employment_type?: string;
    type?: string;
    category?: string;
    created_at?: string;
    requirements?: string[];
    benefits?: string[];
    skills?: string[];
  }

  const [job, setJob] = useState<Job | null>(null);
  const [translatedJob, setTranslatedJob] = useState<Job | null>(null);
  const [loading, setLoading] = useState(true);
  const [isTranslating, setIsTranslating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadJob = async () => {
      if (!id) return;

      try {
        setLoading(true);
        const response = await getJob(id);
        if (response.success && response.data) {
          const jobData = response.data;
          // Obtener requisitos, beneficios y habilidades desde los nuevos endpoints
          try {
            const [reqs, bens, sks] = await Promise.all([
              getJobRequirements(id),
              getJobBenefits(id),
              getJobSkills(id)
            ]);
            const requirements = reqs.map((r: any) => r.requirement || r.requirements || r.req || r);
            const benefits = bens.map((b: any) => b.benefit || b.benefits || b);
            const skills = sks.map((s: any) => s.skill || s.skills || s);
            setJob({
              ...jobData,
              requirements,
              benefits,
              skills,
            });
          } catch (e) {
            console.error('Error loading job details extras:', e);
            // En caso de error, asignar los datos base sin extras
            setJob(jobData);
          }
        } else {
          setError('Job not found');
        }
      } catch (error) {
        console.error('Error loading job:', error);
        setError('Error loading job details');
      } finally {
        setLoading(false);
      }
    };

    loadJob();
  }, [id]);

  // ✅ Efecto para traducir contenido dinámico cuando cambia el idioma
  useEffect(() => {
    const translateJobContent = async () => {
      if (!job || language === 'es') {
        setTranslatedJob(job); // Español es el idioma original
        return;
      }

      setIsTranslating(true);
      try {
        const translated = await translateJobData(
          {
            title: job.title,
            description: job.description || '',
            category: job.category || '',
            type: job.type || ''
          },
          language as 'en'
        );

        setTranslatedJob({
          ...job,
          title: translated.title || job.title,
          description: translated.description || job.description,
          category: translated.category || job.category,
          type: translated.type || job.type
        });
      } catch (error) {
        console.error('Error translating job content:', error);
        setTranslatedJob(job); // Fallback al contenido original
      } finally {
        setIsTranslating(false);
      }
    };

    translateJobContent();
  }, [job, language]);

  // Usar el trabajo traducido si está disponible, sino el original
  const displayJob = translatedJob || job;

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-16 text-center">
        <h1 className="text-2xl font-bold mb-4">{t('loading')}</h1>
        {isTranslating && (
          <p className="text-sm text-gray-600 animate-pulse">
            {t('jobs.translating')}
          </p>
        )}
      </div>
    );
  }

  if (error || !displayJob) {
    return (
      <div className="container mx-auto px-4 py-16 text-center">
        <h1 className="text-2xl font-bold mb-4">{t('jobs.notFound')}</h1>
        <p className="mb-6">
          {t('jobs.notFoundMessage')}
        </p>
        <Link to="/">
          <Button>{t('jobs.backToHome')}</Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      {/* Back to Offers (top) */}
      <div className="mb-6 flex justify-end">
        <HashLink
          smooth
          to="/#open-positions"
          className="flex items-center gap-2 text-[#2F2F2F] hover:font-bold"
        >
          <span>{t('jobs.backToOffers')}</span>
          <ChevronRight size={20} />
        </HashLink>
      </div>

      {/* Job Card */}
      <div className="rounded-xl shadow-lg p-8 bg-white border border-gray-100">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-3xl md:text-4xl font-extrabold mb-3 text-[#2F2F2F]">
            {displayJob.title}
            {isTranslating && <span className="ml-2 text-sm animate-pulse">🌐</span>}
          </h1>
          <div className="flex flex-wrap gap-4 text-[#2F2F2F]">
            <div className="flex items-center text-gray-600">
              <MapPin size={16} className="mr-2" />
              <span>{displayJob.location || t('jobs.remote')}</span>
            </div>
            <div className="flex items-center">
              <Briefcase size={16} className="mr-2" />
              <span>{displayJob.type || t('jobs.fullTime')}</span>
            </div>
            <div className="flex items-center">
              <Calendar size={16} className="mr-2" />
              <span>{t('jobs.posted')}: {new Date(displayJob.created_at || new Date()).toLocaleDateString()}</span>
            </div>
            {displayJob.category && (
              <div className="flex items-center">
                <span className="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 font-semibold">
                  {displayJob.category}
                </span>
              </div>
            )}
          </div>
        </div>

        <Separator className="my-6" />

        {/* Job Description */}
        <section className="mb-8">
          <h2 className="text-xl font-bold mb-4 text-[#FF4785]">{t('jobs.description')}</h2>
          <p className="text-[#2F2F2F] mb-6">
            {displayJob.description || t('jobs.defaultDescription')}
          </p>
          <p className="text-[#2F2F2F]">
            {t('jobs.additionalDescription')}
          </p>
        </section>

        {/* Requirements */}
        <section className="mb-8">
          <h2 className="text-xl font-bold mb-4 text-[#FF4785]">{t('jobs.requirements')}</h2>
          <ul className="space-y-3">
            {displayJob.requirements && displayJob.requirements.length > 0 ? (
              displayJob.requirements.map((req: string, index: number) => (
                <li key={index} className="flex items-start">
                  <CheckCircle2 size={18} className="text-green-500 mr-2 mt-1 shrink-0" />
                  <span>{req}</span>
                </li>
              ))
            ) : (
              // Fallback con traducciones
              [
                t('jobs.defaultRequirements.experience'),
                t('jobs.defaultRequirements.teamWork'),
                t('jobs.defaultRequirements.communication'),
                t('jobs.defaultRequirements.problemSolving'),
                t('jobs.defaultRequirements.passion')
              ].map((req, index) => (
                <li key={index} className="flex items-start">
                  <CheckCircle2 size={18} className="text-green-500 mr-2 mt-1 shrink-0" />
                  <span>{req}</span>
                </li>
              ))
            )}
          </ul>
        </section>

        {/* Offer */}
        <section className="mb-8">
          <h2 className="text-xl font-bold mb-4 text-[#FF4785]">{t('jobs.whatWeOffer')}</h2>
          <ul className="space-y-3">
            {displayJob.benefits && displayJob.benefits.length > 0 ? (
              displayJob.benefits.map((benefit: string, index: number) => (
                <li key={index} className="flex items-start">
                  <CheckCircle2 size={18} className="text-[#FF4785] mr-2 mt-1 shrink-0" />
                  <span>{benefit}</span>
                </li>
              ))
            ) : (
              // Fallback con traducciones
              [
                t('jobs.defaultBenefits.salary'),
                t('jobs.defaultBenefits.flexibility'),
                t('jobs.defaultBenefits.careerPlan'),
                t('jobs.defaultBenefits.training'),
                t('jobs.defaultBenefits.environment'),
              ].map((offer, index) => (
                <li key={index} className="flex items-start">
                  <CheckCircle2 size={18} className="text-[#FF4785] mr-2 mt-1 shrink-0" />
                  <span>{offer}</span>
                </li>
              ))
            )}
          </ul>
        </section>

        {/* CTA */}
        <div className="rounded-xl p-8 text-center bg-[#2f2f2f]">
          <h2 className="text-xl font-semibold mb-2 text-white">{t('jobs.readyToApply')}</h2>
          <p className="mb-6 text-white">
            {t('jobs.joinPlatform')}
          </p>
          <div className="flex flex-col sm:flex-row justify-center gap-4">
            <Link to={`/auth/register?job=${id}`}>
              <Button size="lg" className="bg-[#FF4785] hover:bg-[#FF3575] font-bold shadow-md">
                {t('jobs.registerAndApply')}
              </Button>
            </Link>
            <Link to={`/auth/register?job=${id}`}>
              <Button size="lg" variant="outline" className="font-bold">
                {t('jobs.loginAndApply')}
              </Button>
            </Link>
          </div>
        </div>
      </div>

      {/* Spontaneous Application */}
      <div className="rounded-xl p-8 text-center bg-[#2f2f2f] border border-gray-100 mt-12">
        <h2 className="text-2xl font-bold mb-4 text-white">{t('jobs.noDreamJob')}</h2>
        <p className="mb-6 text-white">
          {t('jobs.spontaneousMessage')}
        </p>
        <HashLink smooth to="/auth/register">
          <Button size="lg" className="bg-[#FF4785] hover:bg-[#FF3575] font-bold shadow-md">
            {t('jobs.submitSpontaneous')}
          </Button>
        </HashLink>
      </div>

      {/* Back to bottom */}
      <div className="mt-6 flex justify-end">
        <HashLink
          smooth
          to="/#open-positions"
          className="flex items-center gap-2 text-[#2F2F2F] hover:font-bold"
        >
          <span>{t('jobs.backToOffers')}</span>
          <ChevronRight size={20} />
        </HashLink>
      </div>

      {/* Similar Jobs - simplified for now 
      <section className="mt-12">
        <h2 className="text-xl font-bold mb-6 text-[#FF4785]">{t('jobs.moreOpportunities')}</h2>
        <div className="text-center py-8">
          <p className="text-[#2F2F2F] mb-4">{t('jobs.discoverMore')}</p>
          <HashLink smooth to="/#open-positions">
            <Button className="bg-[#FF4785] hover:bg-[#FF3575]">{t('jobs.viewAll')}</Button>
          </HashLink>
        </div>
      </section>*/}
    </div>
  );
}
