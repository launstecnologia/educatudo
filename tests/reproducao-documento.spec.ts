import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const raiz = path.resolve(__dirname, '..');
const fixture = path.join(__dirname, 'fixtures/editor-documento.php');
const imagem = { name: 'referencia.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=', 'base64') };

test('reprodução revisa o HTML editável antes de inserir na folha', async ({ page }) => {
  const html = execFileSync('php', [fixture], { encoding: 'utf8', cwd: raiz });
  const estrutura = JSON.parse(execFileSync('php', [fixture, '--reproducao'], {
    encoding: 'utf8', cwd: raiz, input: JSON.stringify({ orientacao: 'retrato', html: '<table style="width:320mm;min-width:320mm"><colgroup><col style="width:160mm"><col style="width:160mm"></colgroup><tr><th colspan="2">Histórico escolar da imagem</th></tr><tr><td rowspan="2" class="edoc-vert">Área</td><td>Disciplina</td></tr><tr><td>Matemática</td></tr></table>' }),
  }));
  let salvamentos = 0;
  let salvo: any;
  let envios = 0;
  await page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'editor.localhost') return route.abort();
    if (url.pathname.startsWith('/static/')) return route.fulfill({ path: path.join(raiz, 'backend/public', url.pathname) });
    if (url.pathname.endsWith('/editor')) return route.fulfill({ contentType: 'text/html', body: html });
    if (url.pathname.endsWith('/reproduzir-imagem')) { envios++; return route.fulfill({ json: { success: true, job_id: 7 } }); }
    if (url.pathname.endsWith('/ai-job/7/status')) return route.fulfill({ json: { status: 'done', result: { estrutura } } });
    if (url.pathname.endsWith('/estrutura')) { salvamentos++; salvo = route.request().postDataJSON(); return route.fulfill({ json: { ok: true, id: 21 } }); }
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
  expect(await page.frameLocator('#edoc-ia-previa').locator('table').evaluate(tabela => tabela.getBoundingClientRect().right <= document.documentElement.clientWidth)).toBe(true);
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
  await page.locator('#edoc-props [data-f="size"]').selectOption('A5');
  await page.locator('#edoc-props [data-f="orientation"]').selectOption('portrait');
  expect(await page.locator('#edoc-paper .edoc-ajuste-folha table').evaluate(tabela => {
    const limite = tabela.closest('.edoc-ajuste-folha')!.getBoundingClientRect().right;
    return tabela.getBoundingClientRect().right <= limite + 1;
  })).toBe(true);
  await page.getByLabel('Ocultar cabeçalho', { exact: true }).check();
  await page.getByLabel('Ocultar rodapé', { exact: true }).check();
  await expect(page.locator('#edoc-paper [data-role="header"], #edoc-paper [data-role="footer"]')).toHaveCount(0);
  await page.getByLabel('Ocultar cabeçalho', { exact: true }).uncheck();
  await expect(page.locator('#edoc-paper [data-role="header"]')).toBeVisible();
  await page.getByLabel('Ocultar cabeçalho', { exact: true }).check();
  await expect.poll(() => salvo?.estrutura?.page?.ocultarCabecalho).toBe(true);
  expect(salvo.estrutura.page.ocultarRodape).toBe(true);
  const impressao = execFileSync('php', [fixture, '--impressao-ajustada'], { encoding: 'utf8', cwd: raiz, input: JSON.stringify(salvo.estrutura) });
  await page.setContent(impressao);
  await page.pdf({ path: test.info().outputPath('folha-ajustada.pdf'), preferCSSPageSize: true });
});
