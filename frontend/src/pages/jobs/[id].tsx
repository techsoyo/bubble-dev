import React from 'react';
import { getJob /*, getJobRequirements, getJobBenefits, getJobSkills */ } from '@/services/ApiService';

export default function JobDetailsPage() {
  const [job, setJob] = React.useState<any>(null);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState<string | null>(null);

  // Obtener el id desde la URL
  const [jobId, setJobId] = React.useState<string>('');
  React.useEffect((): void | (() => void) => {
    const parts = window.location.pathname.split('/');
    const id = parts[parts.length - 1] || '';
    setJobId(id);

    // cleanup para contentar a TS
    return undefined;
  }, []);

  // Cargar datos
  React.useEffect((): void | (() => void) => {
    if (!jobId) return undefined; // ⬅️ evita "not all code paths return a value"

    let cancelled = false;
    (async () => {
      try {
        const res = await getJob(jobId);
        if (!cancelled) setJob((res as any)?.data ?? res);
      } catch (e: any) {
        if (!cancelled) setError(e?.message || 'Error cargando la oferta');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    // cleanup del effect (TS contento)
    return () => { cancelled = true; };
  }, [jobId]);

  if (loading) return <div>Cargando...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!job) return <div>No encontrada</div>;

  return (
    <div>
      <h1>{job.title}</h1>
      <p>{job.company_name} · {job.location}</p>
      {/* aquí tu resto de UI */}
    </div>
  );
}
