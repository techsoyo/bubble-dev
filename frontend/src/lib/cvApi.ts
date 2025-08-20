import { env } from '../config/env';
import { CvFormData } from '../domain/cvSchema';

export async function parseCv(file: File) {
  const fd = new FormData();
  fd.append('file', file);
  const res = await fetch(new URL('/cv/parse.php', env.API_BASE_URL), { method: 'POST', body: fd });
  return { status: res.status, json: await res.json().catch(() => ({})) };
}

export async function confirmCv(payload: CvFormData) {
  const res = await fetch(new URL('/cv/confirm.php', env.API_BASE_URL), {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
  });
  return { status: res.status, json: await res.json().catch(() => ({})) };
}
