// Capturas desktop (1440), tablet (768) e telemóvel (390) das páginas principais → docs/capturas/.
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { chromiumPath } from './browser.mjs';

const BASE = process.env.CDF_URL || 'http://casadasfardas.localhost:8092';
const OUT = fileURLToPath(new URL('../docs/capturas/', import.meta.url));
const pages = {
  inicio: '/',
  loja: '/loja/',
  categoria: '/categoria/horeca/',
  'categoria-filtrada': '/categoria/alta-visibilidade/?marca=workteam&filter_tom=amarelo',
  produto: '/produto/t-shirt-senhora-modelo-cancun/',
  'produto-orcamento': '/produto/protetor-auditivo-snr-28db-0401007/',
  pesquisa: '/?s=luvas&post_type=product',
  personalizacao: '/personalizacao/',
  setor: '/setores/hotelaria-e-restauracao/',
  orcamento: '/pedido-de-orcamento/',
  contactos: '/contactos/',
  404: '/pagina-que-nao-existe/',
};
const sizes = { desktop: [1440, 900], tablet: [768, 1024], mobile: [390, 844] };
const only = process.argv[2];
mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch({ executablePath: chromiumPath() });
const errors = [];
for (const [size, [w, h]] of Object.entries(sizes)) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => errors.push(`${page.url()} ${e.message}`));
  for (const [slug, path] of Object.entries(pages)) {
    if (only && !slug.includes(only)) continue;
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 350) { window.scrollTo({ top: y, behavior: 'instant' }); await new Promise((r) => setTimeout(r, 140)); } window.scrollTo({ top: 0, behavior: 'instant' }); });
    await page.waitForLoadState('networkidle');
    await page.evaluate(async () => { await Promise.all([...document.images].map((i) => (i.complete ? 0 : new Promise((r) => { i.onload = i.onerror = r; setTimeout(r, 4000); })))); });
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}${slug}-${size}.png`, fullPage: true });
  }
  if (!only || 'checkout'.includes(only)) {
    // Carrinho e checkout com um artigo (sessão própria).
    await page.goto(`${BASE}/produto/t-shirt-senhora-modelo-cancun/`);
    await page.locator('.cdf-swatch-btn', { hasText: 'Preto' }).click();
    await page.selectOption('select[name="cdf_size"]', 'M');
    await page.locator('.single_add_to_cart_button').click();
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `${OUT}produto-adicionado-${size}.png` });
    await page.goto(`${BASE}/carrinho/`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: `${OUT}carrinho-${size}.png`, fullPage: true });
    await page.goto(`${BASE}/finalizar-compra/`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${OUT}checkout-${size}.png`, fullPage: true });
  }
  if (!only || 'menu'.includes(only)) {
    await page.goto(BASE + '/', { waitUntil: 'networkidle' });
    if (w >= 1100) {
      await page.locator('.cdf-nav__trigger').first().click();
    } else {
      await page.getByRole('button', { name: 'Abrir menu' }).click();
      await page.locator('dialog.cdf-drawer summary').first().click();
    }
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${OUT}menu-${size}.png` });
  }
  await ctx.close();
}
await browser.close();
console.log(errors.length ? errors.join('\n') : 'Sem erros de JavaScript.');
