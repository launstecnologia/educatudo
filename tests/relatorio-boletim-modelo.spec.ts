import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const raiz = path.resolve(__dirname, '..');
let html: string;
let pdf: Buffer;
let pedidos: string[];
let falharPdf: boolean;
test.beforeAll(() => {
  html = execFileSync('php', [path.join(__dirname, 'fixtures/relatorio-boletim.php')], { encoding: 'utf8', cwd: raiz });
  pdf = execFileSync('php', [path.join(__dirname, 'fixtures/editor-documento.php'), '--pdf'], { cwd: raiz });
});
test.beforeEach(async ({ page }) => {
  pedidos = [];
  falharPdf = false;
  await page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'editor.localhost') return route.abort();
    if (url.pathname.startsWith('/static/')) return route.fulfill({ path: path.join(raiz, 'backend/public', url.pathname) });
    if (url.pathname.endsWith('/vida-escolar/pdf')) {
      pedidos.push(url.toString());
      return route.fulfill(falharPdf ? { contentType: 'text/html', body: 'Sessão expirada' } : { contentType: 'application/pdf', body: pdf });
    }
    return route.fulfill({ contentType: 'text/html', body: html });
  });
  await page.goto('http://editor.localhost/admin/reports/boletim-coordenacao');
});
test('modelo selecionado acompanha PDF individual, ZIP e filtros', async ({ page }) => {
  await expect(page.locator('#modelo-boletim-documento')).toHaveValue('21');
  await expect(page.getByRole('link', { name: 'PDF deste boletim' })).toHaveAttribute('href', /ficha_id=17&modelo_documento_id=21/);
  await expect(page.getByRole('link', { name: 'Baixar boletins (ZIP)', exact: true })).toHaveAttribute('href', /modelo_documento_id=21/);
  await page.locator('#modelo-boletim-documento').selectOption('22');
  expect(await page.locator('#form-boletim-coordenacao').evaluate(form => new FormData(form as HTMLFormElement).get('modelo_documento_id'))).toBe('22');
  await page.locator('#fonte-boletim-coord').selectOption('demonstrativo');
  await expect(page.locator('#campo-modelo-boletim-documento')).not.toBeVisible();
  await page.locator('#fonte-boletim-coord').selectOption('vida_escolar');
  await expect(page.locator('#campo-modelo-boletim-documento')).toBeVisible();
});
test('prévia carrega o PDF do mesmo modelo ao abrir e reutiliza ao reabrir', async ({ page }) => {
  expect(pedidos).toHaveLength(0);
  await page.locator('[data-boletim-previa] summary').click();
  await expect(page.locator('[data-boletim-previa] iframe')).toBeVisible();
  expect(pedidos).toHaveLength(1);
  expect(pedidos[0]).toContain('modelo_documento_id=21&previa=1');
  await page.locator('[data-boletim-previa] summary').click();
  await page.locator('[data-boletim-previa] summary').click();
  expect(pedidos).toHaveLength(1);
});
test('prévia não apresenta HTML de erro como PDF e permite nova tentativa', async ({ page }) => {
  falharPdf = true;
  await page.locator('[data-boletim-previa] summary').click();
  await expect(page.locator('[data-boletim-mensagem]')).toContainText('Não foi possível gerar');
  await expect(page.locator('[data-boletim-previa] iframe')).not.toBeVisible();
  falharPdf = false;
  await page.locator('[data-boletim-previa] summary').click();
  await page.locator('[data-boletim-previa] summary').click();
  await expect(page.locator('[data-boletim-previa] iframe')).toBeVisible();
  expect(pedidos).toHaveLength(2);
});
