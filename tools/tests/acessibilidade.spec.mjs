// Verificação automática WCAG 2.2 A/AA (axe) nos percursos principais. Não substitui testes manuais.
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const PAGES = ['/', '/categoria/hotelaria/', '/produto/t-shirt-senhora-modelo-cancun/', '/produto/protetor-auditivo-snr-28db-0401007/',
  '/pedido-de-orcamento/', '/contactos/', '/personalizacao/', '/setores/industria/', '/?s=luvas&post_type=product', '/carrinho/', '/minha-conta/'];

for (const path of PAGES) {
  test(`axe ${path}`, async ({ page }) => {
    await page.goto(path);
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
    const serious = results.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
    expect(serious.map((v) => `${v.id}: ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);
  });
}

test('navegação por teclado: saltar para o conteúdo e foco visível', async ({ page }) => {
  await page.goto('/');
  await page.keyboard.press('Tab');
  const skip = page.locator('.cdf-skip');
  await expect(skip).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#conteudo')).toBeFocused();
});
