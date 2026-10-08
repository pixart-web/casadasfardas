// Pedido de orçamento: validação, ficheiro inválido, ficheiro válido, confirmação real, email e armazenamento privado.
import { test, expect } from '@playwright/test';
import { mailpitMessages, resetRateLimits } from '../helpers.mjs';

test.beforeAll(() => resetRateLimits());

// PNG 1×1 válido.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');

async function fill(page, email) {
  await page.fill('#cdf-nome', 'Pessoa de Teste');
  await page.fill('#cdf-empresa', 'Empresa Fictícia Lda');
  await page.fill('#cdf-email', email);
  await page.fill('#cdf-artigos', '20 pólos azul-marinho, 10 calças cinzentas');
  await page.locator('input[name="personalizacao"][value="bordado"]').check();
  await page.locator('input[name="cdf_consent"]').check();
}

test('campos obrigatórios em falta: erros acessíveis', async ({ page }) => {
  await page.goto('/pedido-de-orcamento/');
  await page.waitForTimeout(3200);
  await page.evaluate(() => document.querySelector('[data-cdf-form]').setAttribute('novalidate', ''));
  await page.evaluate(() => document.querySelectorAll('[data-cdf-form] [required]').forEach((e) => e.removeAttribute('required')));
  await page.locator('[data-cdf-submit]').click();
  await expect(page.locator('.cdf-form-errors')).toBeVisible();
  await expect(page.locator('.cdf-form-errors')).toContainText('Indique o seu nome');
  await expect(page.locator('#cdf-email')).toHaveAttribute('aria-invalid', 'true');
});

test('ficheiro com formato falso é recusado', async ({ page }) => {
  await page.goto('/pedido-de-orcamento/');
  await fill(page, 'falso@casadasfardas.test');
  await page.setInputFiles('#cdf-logotipo', { name: 'logotipo.png', mimeType: 'image/png', buffer: Buffer.from('<?php echo 1; ?>') });
  await page.evaluate(() => document.querySelector('#cdf-logotipo').setCustomValidity(''));
  await page.waitForTimeout(3200);
  await page.locator('[data-cdf-submit]').click();
  await expect(page.locator('.cdf-form-errors')).toContainText('não corresponde ao formato');
});

test('pedido válido com logótipo: confirmação com referência, email e anexo privado', async ({ page, request }) => {
  const email = `orc-${Date.now()}@casadasfardas.test`;
  await page.goto('/produto/protetor-auditivo-snr-28db-0401007/');
  await page.locator('.cdf-quote-add-form input[name="quantity"]').fill('40');
  await page.locator('.cdf-quote-add-form .cdf-quote-add').click();
  await page.goto('/pedido-de-orcamento/');
  await expect(page.locator('.cdf-quote-item')).toContainText('PROTETOR AUDITIVO');
  await fill(page, email);
  await page.setInputFiles('#cdf-logotipo', { name: 'logotipo.png', mimeType: 'image/png', buffer: PNG });
  await page.waitForTimeout(3200);
  await page.locator('[data-cdf-submit]').click();
  await expect(page.locator('.cdf-form-success')).toContainText(/ORC-\d{4}-\d{4}/);
  await expect.poll(async () => (await mailpitMessages(request, `to:${email}`)).length, { timeout: 20000 }).toBeGreaterThan(0);
  const team = await mailpitMessages(request, 'to:orcamentos@casadasfardas.test');
  expect(team.length).toBeGreaterThan(0);
  expect(team[0].Attachments).toBeGreaterThan(0);
  // A lista de orçamento é esvaziada e a confirmação não se repete ao recarregar sem token.
  await page.goto('/pedido-de-orcamento/');
  await expect(page.locator('.cdf-form-success')).toHaveCount(0);
  await expect(page.locator('.cdf-quote-item')).toHaveCount(0);
});

test('envio sem nonce nem token é recusado sem guardar', async ({ request }) => {
  // Sem nonce/token válidos o servidor recusa sem guardar nada.
  const res = await request.post('/pedido-de-orcamento/', { form: { cdf_form: 'quote', nome: 'x', email: 'x@x.pt' } });
  expect(res.status()).toBe(422);
});

test('limite de 6 envios por hora a partir da mesma ligação', async ({ page }) => {
  resetRateLimits();
  let last = '';
  for (let i = 0; i < 7; i++) {
    await page.goto('/contactos/');
    await page.fill('#cdf-nome', 'Teste limite');
    await page.fill('#cdf-email', 'limite@casadasfardas.test');
    await page.fill('#cdf-mensagem', `Mensagem de teste ${i}`);
    await page.locator('input[name="cdf_consent"]').check();
    await page.waitForTimeout(3100);
    await page.locator('[data-cdf-submit]').click();
    await page.waitForLoadState('domcontentloaded');
    last = await page.locator('#cdf-form').innerText();
  }
  expect(last).toContain('Recebemos vários pedidos seguidos');
  resetRateLimits();
});
