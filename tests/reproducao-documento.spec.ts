import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const raiz = path.resolve(__dirname, '..');
const fixture = path.join(__dirname, 'fixtures/editor-documento.php');
const imagem = { name: 'referencia.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=', 'base64') };

test('reprodução revisa o HTML editável antes de inserir na folha', async ({ page }) => {
  const html = execFileSync('php', [fixture], { encoding: 'utf8', cwd: raiz });
  const estrutura = JSON.parse(execFileSync('php', [fixture, '--reproducao'], {
    encoding: 'utf8', cwd: raiz, input: JSON.stringify({ orientacao: 'retrato', html: '<table><tr><th colspan="2">Histórico escolar da imagem</th></tr><tr><td rowspan="2" class="edoc-vert">Área</td><td>Disciplina</td></tr><tr><td>Matemática</td></tr></table>' }),
  }));
  let salvamentos = 0;
  let envios = 0;
  await page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'editor.localhost') return route.abort();
    if (url.pathname.startsWith('/static/')) return route.fulfill({ path: path.join(raiz, 'backend/public', url.pathname) });
    if (url.pathname.endsWith('/editor')) return route.fulfill({ contentType: 'text/html', body: html });
    if (url.pathname.endsWith('/reproduzir-imagem')) { envios++; return route.fulfill({ json: { success: true, job_id: 7 } }); }
    if (url.pathname.endsWith('/ai-job/7/status')) return route.fulfill({ json: { status: 'done', result: { estrutura } } });
    if (url.pathname.endsWith('/estrutura')) { salvamentos++; return route.fulfill({ json: { ok: true, id: 21 } }); }
    return route.fulfill({ json: { ok: true, turmas: [], boletins: [] } });
  });
  await page.goto('http://editor.localhost/admin/modelos-documentos/21/editor');
  const reproduzir = page.getByRole('button', { name: 'Reproduzir de imagem' });
  await reproduzir.click();
  await page.locator('#edoc-ia-cola').evaluate((zona, dados) => {
    const clipboardData = new DataTransfer();
    clipboardData.items.add(new File([Uint8Array.from(dados)], 'referencia.png', { type: 'image/png' }));
    zona.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData }));
  }, Array.from(imagem.buffer));
  const dialogo = page.getByRole('dialog', { name: 'Reprodução da imagem' });
  await expect(dialogo).toBeVisible();
  await expect(page.locator('#edoc-paper')).not.toContainText('Histórico escolar da imagem');
  await expect(page.frameLocator('#edoc-ia-previa').locator('th')).toHaveAttribute('colspan', '2');
  expect(salvamentos).toBe(0);
  await dialogo.getByRole('button', { name: 'Descartar' }).click();
  await expect(dialogo).not.toBeVisible();
  await expect(page.locator('#edoc-paper')).not.toContainText('Histórico escolar da imagem');
  const texto = page.locator('#pane-elementos [data-drag-type="texto"]');
  if (!await texto.isVisible()) await texto.locator('xpath=ancestor::details[1]/summary').click();
  await texto.click();
  await expect(page.locator('#edoc-paper .edoc-el[data-type="texto"]')).toHaveCount(1);
  await reproduzir.click();
  await page.locator('#edoc-ia-file').setInputFiles(imagem);
  const codigo = dialogo.getByLabel('HTML gerado');
  await codigo.fill((await codigo.inputValue()).replace('Matemática', `<span style="background-image:image-set('https://externo.test/pixel' 1x)">Matemática ajustada</span>`));
  await expect(page.frameLocator('#edoc-ia-previa').getByText('Matemática ajustada')).toBeVisible();
  await page.screenshot({ path: test.info().outputPath('revisao-html-imagem.png'), fullPage: true });
  await dialogo.getByRole('button', { name: 'Inserir na folha' }).click();
  await expect(page.locator('#edoc-paper')).toContainText('Matemática ajustada');
  await expect(page.locator('#edoc-paper td[rowspan="2"]')).toHaveCSS('writing-mode', 'vertical-rl');
  await expect(page.locator('#edoc-paper .edoc-el[data-type="texto"]')).toHaveCount(1);
  await expect(page.locator('#edoc-paper [style*="image-set"]')).toHaveCount(0);
  expect(envios).toBe(2);
});
