// Abre todas as páginas no editor de blocos e confirma que não há blocos inválidos nem em falta.
// Credenciais locais: ../.credentials.local (geradas pelo bin/setup.sh).
import { chromium } from '@playwright/test';
import { chromiumPath } from './browser.mjs';
import { credentials } from './helpers.mjs';

const cred = credentials();
const BASE = process.env.CDF_URL || 'http://casadasfardas.localhost:8092';
const browser = await chromium.launch({ executablePath: chromiumPath() });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
await page.goto(`${BASE}/wp-login.php`);
await page.fill('#user_login', cred.USER);
await page.fill('#user_pass', cred.PASS);
await page.click('#wp-submit');
await page.waitForURL(/wp-admin/);

const pages = await page.evaluate(async (base) => {
  const nonce = await (await fetch(`${base}/wp-admin/admin-ajax.php?action=rest-nonce`, { credentials: 'include' })).text();
  const res = await fetch(`${base}/wp-json/wp/v2/pages?per_page=100&status=publish,pending&_fields=id,slug,status`, { credentials: 'include', headers: { 'X-WP-Nonce': nonce } });
  return res.json();
}, BASE);

let problems = 0;
for (const p of pages) {
  await page.goto(`${BASE}/wp-admin/post.php?post=${p.id}&action=edit`);
  await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 60000 });
  await page.waitForTimeout(1500);
  const res = await page.evaluate(() => {
    const walk = (blocks) => blocks.flatMap((b) => [b, ...walk(b.innerBlocks)]);
    const all = walk(wp.data.select('core/block-editor').getBlocks());
    return { count: all.length, invalid: all.filter((b) => !b.isValid).map((b) => b.name), missing: all.filter((b) => b.name === 'core/missing').length };
  });
  const bad = res.invalid.length || res.missing;
  if (bad) problems++;
  console.log(`${bad ? '✗' : '✓'} ${p.slug || p.id} (${p.status}) ${res.count} blocos${bad ? ` — inválidos: ${res.invalid.join(', ')}` : ''}`);
}
console.log(`\n${pages.length} páginas verificadas, ${problems} com problemas.`);
await browser.close();
process.exit(problems ? 1 : 0);
