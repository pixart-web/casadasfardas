// SEO técnico: títulos, descrições, canonical, robots, sitemap, dados estruturados e redirecionamentos.
import { test, expect } from '@playwright/test';
import { jsonLd } from '../helpers.mjs';

test('metadados únicos e canonical coerente', async ({ page }) => {
  const seen = new Set();
  for (const path of ['/', '/loja/', '/categoria/horeca/', '/produto/t-shirt-senhora-modelo-cancun/', '/personalizacao/', '/contactos/']) {
    await page.goto(path);
    const title = await page.title();
    const desc = await page.locator('meta[name="description"]').getAttribute('content');
    expect(title.length, path).toBeGreaterThan(10);
    expect(desc && desc.length, path).toBeGreaterThan(50);
    expect(seen.has(title), `título duplicado ${title}`).toBeFalsy();
    seen.add(title);
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
    expect(await page.locator('meta[property="og:title"]').count()).toBe(1);
  }
});

test('variante por parâmetro aponta para a página do produto', async ({ page }) => {
  await page.goto('/produto/t-shirt-senhora-modelo-cancun/?attribute_pa_cor=preto');
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/produto\/t-shirt-senhora-modelo-cancun\/$/);
});

test('loja física em dados estruturados na página inicial', async ({ page }) => {
  await page.goto('/');
  const store = (await jsonLd(page)).find((d) => [].concat(d['@type']).includes('ClothingStore'));
  expect(store.address.postalCode).toBe('8365-306');
  expect(store.telephone).toBe('+351964395979');
  expect(store.aggregateRating).toBeUndefined();
});

test('páginas de sessão e pesquisa fora do índice', async ({ page }) => {
  for (const path of ['/carrinho/', '/finalizar-compra/', '/minha-conta/', '/?s=bota&post_type=product']) {
    await page.goto(path);
    await expect(page.locator('meta[name="robots"]'), path).toHaveAttribute('content', /noindex/);
  }
});

test('sitemap só com URLs canónicas', async ({ request }) => {
  const index = await (await request.get('/wp-sitemap.xml')).text();
  expect(index).toContain('wp-sitemap-posts-product-1.xml');
  expect(index).toContain('wp-sitemap-taxonomies-product_cat-1.xml');
  expect(index).not.toContain('users');
  const pages = await (await request.get('/wp-sitemap-posts-page-1.xml')).text();
  expect(pages).not.toContain('/carrinho/');
  expect(pages).not.toContain('/politica-de-privacidade/');
  const robots = await (await request.get('/robots.txt')).text();
  expect(robots).toContain('Sitemap:');
  expect(robots).not.toMatch(/Disallow: \/\s*$/m);
});

test('redirecionamentos 301 diretos das URLs antigas', async ({ request }) => {
  const cases = [
    ['/info/BOTA-VOUGA-S3-COMPOSITO-PU-SRC-4590.html', /\/produto\/bota-vouga-s3-composito-pu-src\/$/],
    ['/m/info/T-shirt-senhora-modelo-CANCUN-4102.html', /\/produto\/t-shirt-senhora-modelo-cancun\/$/],
    ['/info/Preto-4103.html', /\/produto\/t-shirt-senhora-modelo-cancun\/\?attribute_pa_cor=preto$/],
    ['/info/Batas-Saude-e-Servicos-Vestuario-22-subcategoria-lpc', /\/categoria\/batas-e-tunicas-servicos-e-saude\/$/],
    ['/info/Lonas-Equipamentos-de-protecao-individual-18-categoria-lpc', /\/categoria\/outros-artigos\/$/],
    ['/info/listar_categoria.php?nivelm=subcategoria&id=22', /\/categoria\/batas-e-tunicas-servicos-e-saude\/$/],
    ['/info/contactos.php', /\/contactos\/$/],
    ['/index.php', /\/$/],
  ];
  for (const [from, to] of cases) {
    const res = await request.get(from, { maxRedirects: 0 });
    expect(res.status(), from).toBe(301);
    expect(res.headers().location, from).toMatch(to);
    const dest = await request.get(res.headers().location, { maxRedirects: 0 });
    expect(dest.status(), `destino de ${from}`).toBe(200);
  }
  const gone = await request.get('/info/T-shirt-Marlo-2739.html', { maxRedirects: 0 });
  expect(gone.status()).toBe(410);
});
