// Sem overflow horizontal, imagens partidas, botões sem ação ou texto de rascunho público.
import { test, expect } from '@playwright/test';

const WIDTHS = [360, 390, 768, 1280, 1440];
const PAGES = ['/', '/loja/', '/categoria/calcado-de-seguranca/', '/produto/t-shirt-senhora-modelo-cancun/', '/pedido-de-orcamento/', '/setores/hotelaria-e-restauracao/', '/personalizacao/', '/carrinho/'];

for (const width of WIDTHS) {
  test(`sem overflow horizontal a ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    for (const path of PAGES) {
      await page.goto(path);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      expect(overflow, `${path} @${width}`).toBeLessThanOrEqual(0);
    }
  });
}

test('imagens carregam, ligações têm destino e não há texto de rascunho', async ({ page }) => {
  for (const path of PAGES) {
    await page.goto(path);
    await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); } });
    await page.waitForLoadState('networkidle');
    const broken = await page.$$eval('img', (imgs) => imgs.filter((i) => i.complete && i.naturalWidth === 0 && i.getAttribute('src')).map((i) => i.src));
    expect(broken, path).toEqual([]);
    const empty = await page.$$eval('a', (as) => as.filter((a) => !a.getAttribute('href') || a.getAttribute('href') === '#').map((a) => a.outerHTML.slice(0, 80)));
    expect(empty, path).toEqual([]);
    const text = await page.locator('body').innerText();
    expect(text, path).not.toMatch(/lorem ipsum|placeholder/i);
    expect(text, path).not.toMatch(/A VALIDAR|PENDENTE DE VALIDAÇÃO|\bTODO\b|\bFIXME\b/);
  }
});
