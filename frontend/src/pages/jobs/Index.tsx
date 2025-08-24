import React, { useEffect, useState } from 'react';

import { ApiService } from '@/services/ApiService';

type Job = {
  id: string;
  title: string;
  company_name: string;
  location: string;
  status?: string;
};

export default function JobsIndex() {
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    let abort = false;
    (async () => {
      try {
        const data = await ApiService.publicGet<{ data?: Job[]; success?: boolean }>('jobs.php');
        const rows = Array.isArray((data as any)?.data) ? (data as any).data : (Array.isArray(data) ? (data as any) : []);
        if (!abort) setJobs(rows);
      } catch (e: any) {
        if (!abort) setErr(e?.message ?? 'Error cargando empleos');
      } finally {
        if (!abort) setLoading(false);
      }
    })();
    return () => { abort = true; };
  }, []);

  if (loading) return <div>Cargando empleos…</div>;
  if (err) return <div style={{ color: 'crimson' }}>{err}</div>;

  return (
    <div>
      <h1>Ofertas</h1>
      <ul>
        {jobs.map(j => (
          <li key={j.id}>
            + <a href={`/jobs/${j.id}`}>{j.title}</a> — {j.company_name} · {j.location}
          </li>
        ))}
      </ul>
    </div>
  );
}
