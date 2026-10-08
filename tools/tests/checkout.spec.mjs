// Carrinho, IVA, portes, checkout com pagamento de teste (aprovado/recusado), email e área de cliente.
import { test, expect } from '@playwright/test';
import { credentials, mailpitMessages } from '../helpers.mjs';

async function addSimple(page, qty = 2) {
  await page.goto('/produto/bermuda-de-tecido-elastico-bidireccional-por-construcao/');
  await page.locator('input.qty').fill(String(qty));
  await page.locator('.single_add_to_cart_button').click();
  await expect(page.locator('.woocommerce-message')).toBeVisible();
}

async function fillCheckout(page, email) {
  await page.fill('#billing_first_name', 'Teste');
  await page.fill('#billing_last_name', 'Demonstração');
  await page.fill('#billing_company', 'Empresa Fictícia Lda');
  await page.fill('#billing_address_1', 'Rua de Teste, 1');
  await page.fill('#billing_postcode', '8365-306');
  await page.fill('#billing_city', 'Alcantarilha');
  await page.fill('#billing_phone', '912345678');
  await page.fill('#billing_email', email);
}

test('carrinho: quantidades, IVA 23% e totais', async ({ page }) => {
  await addSimple(page, 2);
  await page.goto('/carrinho/');
  const unit = 23.89;
  const subtotal = await page.locator('.cart-subtotal .amount').innerText();
  expect(subtotal.replace(/\s/g, '')).toContain((unit * 2).toFixed(2).replace('.', ','));
  const vat = await page.locator('tr.tax-rate .amount, tr.tax-total .amount').first().innerText();
  expect(parseFloat(vat.replace(/[^\d,]/g, '').replace(',', '.'))).toBeCloseTo(unit * 2 * 0.23, 1);
  await expect(page.locator('.cdf-vat-note')).toBeVisible();
  await expect(page.locator('#shipping_method')).toContainText('portes confirmados antes do envio');
  await page.locator('input.qty').first().fill('3');
  await page.locator('button[name="update_cart"]').click();
  await expect(page.locator('.cart-subtotal .amount')).toContainText((unit * 3).toFixed(2).replace('.', ','));
});

test('checkout como convidado: pagamento recusado e depois aprovado, com email', async ({ page, request }) => {
  const email = `teste-${Date.now()}@casadasfardas.test`;
  await addSimple(page, 1);
  await page.goto('/finalizar-compra/');
  await fillCheckout(page, email);
  // NIF inválido é recusado; NIF fictício válido (dígito de controlo correto) é aceite.
  await page.fill('#billing_nif', '123456780');
  await page.locator('#payment_method_cdf_test').check();
  await page.locator('#place_order').click();
  await expect(page.locator('.woocommerce-NoticeGroup-checkout')).toContainText('NIF indicado não é válido');
  await page.fill('#billing_nif', '123456789');
  await page.locator('input[name="cdf_test_outcome"][value="decline"]').check();
  await page.locator('#place_order').click();
  await expect(page.locator('.woocommerce-NoticeGroup-checkout')).toContainText('recusado');
  await page.locator('input[name="cdf_test_outcome"][value="approve"]').check();
  await page.locator('#place_order').click();
  await page.waitForURL(/order-received|encomenda-recebida/);
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();
  await expect(page.locator('.woocommerce-table--order-details')).toContainText('IVA');
  await expect(page.locator('.cdf-order-nif')).toContainText('123456789');
  await expect.poll(async () => (await mailpitMessages(request, `to:${email}`)).length, { timeout: 20000 }).toBeGreaterThan(0);
});

test('área de cliente: login, encomendas próprias e isolamento entre clientes', async ({ page }) => {
  const c = credentials();
  test.skip(!c.CLIENTE_A_PASS, 'Correr bin/test-fixtures.sh primeiro');
  await page.goto('/minha-conta/');
  await page.fill('#username', c.CLIENTE_A_USER);
  await page.fill('#password', c.CLIENTE_A_PASS);
  await page.locator('button[name="login"]').click();
  await expect(page.locator('.woocommerce-MyAccount-navigation')).toBeVisible();
  await page.goto(`/minha-conta/view-order/${c.CLIENTE_B_ORDER}/`);
  await expect(page.locator('.woocommerce-error, .woocommerce-info')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Cliente B');
});

test('recuperação de acesso envia email', async ({ page, request }) => {
  const c = credentials();
  test.skip(!c.CLIENTE_A_USER, 'Correr bin/test-fixtures.sh primeiro');
  await page.goto('/minha-conta/lost-password/');
  await page.fill('#user_login', c.CLIENTE_A_EMAIL);
  await page.locator('.woocommerce-ResetPassword button[type="submit"]').click();
  await expect(page.locator('.woocommerce-message')).toBeVisible();
  await expect.poll(async () => (await mailpitMessages(request, `to:${c.CLIENTE_A_EMAIL}`)).length, { timeout: 20000 }).toBeGreaterThan(0);
});
