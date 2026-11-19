// Navegação: páginas principais, mega menu, menu móvel, 404 e páginas pendentes.
import { test, expect } from '@playwright/test';

const PAGES = ['/', '/loja/', '/categoria/vestuario/', '/categoria/calcado/', '/setores/', '/setores/hotelaria-e-restauracao/',
  '/setores/saude-e-servicos/', '/setores/industria/', '/setores/construcao-e-obras/', '/setores/desporto/', '/personalizacao/', '/sobre/', '/contactos/',
  '/pedido-de-orcamento/', '/carrinho/', '/minha-conta/', '/produto/t-shirt-senhora-modelo-cancun/'];

test('páginas principais respondem 200 com um único H1', async ({ page }) => {
  for (const path of PAGES) {
    const res = await page.goto(path);
    expect(res.status(), path).toBe(200);
    expect(await page.locator('h1').count(), `${path} H1`).toBe(1);
  }
});

test('404 útil com código HTTP correto', async ({ page }) => {
  const res = await page.goto('/esta-pagina-nao-existe/');
  expect(res.status()).toBe(404);
  await expect(page.locator('h1')).toContainText('Não encontrámos');
  await expect(page.locator('.cdf-empty-page__search input[type="search"]')).toBeVisible();
});

test('páginas legais pendentes não estão públicas', async ({ page }) => {
  for (const path of ['/politica-de-privacidade/', '/termos-e-condicoes/', '/entregas/']) {
    const res = await page.goto(path);
    expect(res.status(), path).toBe(404);
  }
  await page.goto('/');
  const footer = await page.locator('footer').innerText();
  expect(footer).not.toContain('A VALIDAR');
});

test('mega menu abre por clique, fecha com Escape', async ({ page }) => {
  await page.goto('/');
  const trigger = page.locator('.cdf-nav__trigger').first();
  await trigger.click();
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  const panel = page.locator('.cdf-mega').first();
  await expect(panel).toBeVisible();
  expect(await panel.locator('a').count()).toBeGreaterThan(10);
  await page.keyboard.press('Escape');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expect(panel).toBeHidden();
});

test('menu móvel em acordeão', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');
  await page.getByRole('button', { name: 'Abrir menu' }).click();
  const drawer = page.locator('dialog.cdf-drawer');
  await expect(drawer).toBeVisible();
  await drawer.locator('summary', { hasText: 'Vestuário' }).first().click();
  await drawer.locator('summary', { hasText: 'Horeca' }).click();
  await expect(drawer.getByRole('link', { name: 'Aventais' })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(drawer).toBeHidden();
});
