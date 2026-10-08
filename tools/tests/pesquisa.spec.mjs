// Pesquisa por nome, por referência (produto e cor), sugestões acessíveis e estado vazio.
import { test, expect } from '@playwright/test';

test('pesquisa por nome', async ({ page }) => {
  await page.goto('/?s=bota&post_type=product');
  await expect(page.locator('h1')).toContainText('bota');
  expect(await page.locator('ul.products > li').count()).toBeGreaterThan(5);
});

test('pesquisa por referência do produto e da cor (resultado único abre a ficha)', async ({ page }) => {
  await page.goto('/?s=0114080&post_type=product');
  await expect(page).toHaveURL(/\/produto\/bota-vouga-s3-composito-pu-src\/$/);
  await page.goto('/?s=CANCUN.1&post_type=product');
  await expect(page).toHaveURL(/\/produto\/t-shirt-senhora-modelo-cancun\/$/);
  await page.goto('/?s=CANCUN&post_type=product');
  await expect(page.locator('h1')).toContainText('CANCUN');
});

test('estado vazio útil', async ({ page }) => {
  await page.goto('/?s=xptoinexistente&post_type=product');
  await expect(page.locator('.cdf-empty')).toContainText('Não encontrámos artigos');
  await expect(page.locator('.cdf-empty a', { hasText: 'Pedir orçamento' })).toBeVisible();
});

test('sugestões com teclado (combobox)', async ({ page }) => {
  await page.goto('/');
  const input = page.locator('#cdf-search');
  await input.click();
  await input.pressSequentially('jaqueta', { delay: 40 });
  await expect(input).toHaveAttribute('aria-expanded', 'true');
  const options = page.locator('#cdf-search-list [role="option"]');
  await expect(options.first()).toBeVisible();
  await input.press('ArrowDown');
  await expect(input).toHaveAttribute('aria-activedescendant', /cdf-search-o0/);
  await input.press('Enter');
  await page.waitForLoadState('domcontentloaded');
  expect(page.url()).toMatch(/\/(categoria|produto)\//);
});

test('sugestão por referência', async ({ request }) => {
  const res = await request.get('/wp-json/cdf/v1/suggest?q=0114080');
  const data = await res.json();
  expect(data.products[0].sku).toBe('0114080');
});
