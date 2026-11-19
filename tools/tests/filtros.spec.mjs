// Filtros combinados, ordenação, paginação, botão «retroceder» e painel móvel.
import { test, expect } from '@playwright/test';

const count = async (page) => Number((await page.locator('.cdf-toolbar__count').innerText()).match(/\d+/)[0]);

test('filtros combinados e remoção', async ({ page }) => {
  await page.goto('/categoria/alta-visibilidade/');
  const total = await count(page);
  await page.locator('.cdf-facet__opt', { hasText: 'Workteam' }).click();
  await expect(page).toHaveURL(/marca=workteam/);
  const byBrand = await count(page);
  expect(byBrand).toBeLessThanOrEqual(total);
  const sizes = page.locator('details.cdf-facet', { hasText: 'Tamanho' });
  await sizes.evaluate((d) => { d.open = true; });
  await sizes.locator('.cdf-facet__opt').filter({ has: page.locator('.cdf-facet__name', { hasText: /^XL$/ }) }).first().click();
  await expect(page).toHaveURL(/filter_tamanho=xl/);
  const combined = await count(page);
  expect(combined).toBeLessThanOrEqual(byBrand);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
  await page.locator('.cdf-active-filters .cdf-chip').first().click();
  await expect(page).not.toHaveURL(/marca=workteam/);
  await page.goBack();
  await expect(page).toHaveURL(/marca=workteam/);
});

test('ordenação por preço e por nome', async ({ page }) => {
  await page.goto('/categoria/calcado/?orderby=price');
  const prices = await page.locator('.cdf-card__price .cdf-price > .amount, .cdf-card__price .cdf-price > .woocommerce-Price-amount').evaluateAll((els) => els.map((e) => parseFloat(e.innerText.replace(/[^\d,]/g, '').replace(',', '.'))));
  expect(prices.length).toBeGreaterThan(3);
  expect([...prices].sort((a, b) => a - b)).toEqual(prices);
  await page.selectOption('#cdf-orderby', 'title');
  await expect(page).toHaveURL(/orderby=title/);
});

test('paginação com URLs próprias e canonical por página', async ({ page }) => {
  await page.goto('/categoria/vestuario/');
  await page.locator('.woocommerce-pagination a.next').click();
  await expect(page).toHaveURL(/\/page\/2\/$/);
  expect(await page.locator('ul.products > li').count()).toBeGreaterThan(0);
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/categoria\/vestuario\/page\/2\/$/);
  await page.goBack();
  await expect(page).toHaveURL(/categoria\/vestuario\/$/);
});

test('filtros num painel no telemóvel', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/categoria/horeca/');
  const btn = page.locator('[data-cdf-filters-open]');
  await btn.click();
  await expect(btn).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#cdf-filters')).toBeInViewport();
  await page.keyboard.press('Escape');
  await expect(btn).toHaveAttribute('aria-expanded', 'false');
});
