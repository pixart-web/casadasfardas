// Utilitários partilhados pelos testes.
import { readFileSync } from 'node:fs';

export const MAILPIT = process.env.CDF_MAILPIT || 'http://localhost:8028';

export function credentials() {
  try {
    return Object.fromEntries(readFileSync(new URL('../.credentials.local', import.meta.url), 'utf8').trim().split('\n').filter(Boolean).map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    }));
  } catch {
    return {};
  }
}

export async function mailpitMessages(request, query = '') {
  const res = await request.get(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(query)}&limit=50`);
  return (await res.json()).messages || [];
}

export async function mailpitClear(request) {
  await request.delete(`${MAILPIT}/api/v1/messages`);
}

export async function jsonLd(page) {
  return page.$$eval('script[type="application/ld+json"]', (els) => els.flatMap((e) => {
    try {
      const d = JSON.parse(e.textContent);
      return d['@graph'] ? d['@graph'] : [d];
    } catch {
      return [];
    }
  }));
}

/**
 * Repõe os limites anti-abuso (transients cdf_rl_*) — só para o ambiente de testes local.
 */
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
export function resetRateLimits() {
  const root = fileURLToPath(new URL('..', import.meta.url));
  execSync(`bin/wp eval 'global $wpdb; $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE \\"%transient%cdf_rl_%\\"");'`, { cwd: root, stdio: 'ignore' });
}
