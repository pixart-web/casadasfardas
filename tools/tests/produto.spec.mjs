// Fichas: tamanho obrigatório, variações de cor, artigo só por orçamento, dados estruturados.
import { test, expect } from '@playwright/test';
import { jsonLd } from '../helpers.mjs';

test('artigo simples exige tamanho válido', async ({ page }) => {
  await page.goto('/produto/sweatshirt-combinada-com-capuz/');
  await expect(page.locator('.cdf-price--single')).toContainText('+ IVA');
  await expect(page.locator('.cdf-price--single')).toContainText('c/ IVA');
  // Sem tamanho: o browser bloqueia (required); forçamos o envio para testar a validação do servidor.
  await page.evaluate(() => document.querySelector('select[name="cdf_size"]').removeAttribute('required'));
  await page.locator('.single_add_to_cart_button').click();
  await expect(page.locator('.woocommerce-error')).toContainText('Escolha um tamanho');
  await page.selectOption('select[name="cdf_size"]', 'L');
  await page.locator('.single_add_to_cart_button').click();
  await expect(page.locator('.woocommerce-message')).toBeVisible();
  await expect(page.locator('.cdf-action--cart .cdf-badge')).toHaveText('1');
});

test('variação de cor muda referência e imagem; combinação inválida bloqueada', async ({ page }) => {
  await page.goto('/produto/t-shirt-senhora-modelo-cancun/');
  const btn = page.locator('.single_add_to_cart_button');
  await expect(btn).toHaveClass(/disabled/);
  await page.locator('.cdf-swatch-btn', { hasText: 'Preto' }).click();
  await expect(page.locator('[data-cdf-sku]')).toHaveText('CANCUN.1');
  await page.selectOption('select[name="cdf_size"]', 'M');
  await expect(btn).not.toHaveClass(/disabled/);
  await btn.click();
  await expect(page.locator('.woocommerce-message')).toBeVisible();
  await page.goto('/carrinho/');
  const row = page.locator('tr.cart_item', { hasText: 'CANCUN' });
  await expect(row).toContainText('Preto');
  await expect(row).toContainText('M');
});

test('artigo sem preço: nunca vendido por zero, segue para orçamento', async ({ page }) => {
  await page.goto('/produto/protetor-auditivo-snr-28db-0401007/');
  await expect(page.locator('.cdf-price--quote')).toHaveText('Preço sob orçamento');
  await expect(page.locator('.single_add_to_cart_button')).toHaveCount(0);
  await page.locator('.cdf-quote-add-form input[name="quantity"]').fill('25');
  await page.locator('.cdf-quote-add-form .cdf-quote-add').click();
  await expect(page.locator('.woocommerce-message')).toContainText('pedido de orçamento');
  await expect(page.locator('.cdf-action--quote .cdf-badge')).toHaveText('1');
  // Sem preço não há oferta: o WooCommerce não publica Product (exige offers/review) — nada é inventado.
  const ld = await jsonLd(page);
  expect(ld.some((d) => d['@type'] === 'Product' && d.offers)).toBeFalsy();
  expect(ld.some((d) => d['@type'] === 'BreadcrumbList')).toBeTruthy();
});

test('dados estruturados de produto com preço sem IVA e moeda', async ({ page }) => {
  await page.goto('/produto/t-shirt-senhora-modelo-cancun/');
  const ld = await jsonLd(page);
  const product = ld.find((d) => d['@type'] === 'Product');
  expect(product.brand.name).toBe('Valento');
  const offers = [].concat(product.offers);
  expect(offers[0].priceCurrency || offers[0].priceSpecification?.[0]?.priceCurrency).toBe('EUR');
  expect(product.aggregateRating).toBeUndefined();
  expect(ld.some((d) => d['@type'] === 'BreadcrumbList')).toBeTruthy();
});
