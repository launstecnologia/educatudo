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
  const assistente = page.getByRole('button', { name: 'Abrir assistente de documentos' });
  const reproduzir = page.getByRole('button', { name: 'Reproduzir de imagem' });
  await assistente.click();
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
  await assistente.click();
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

test('o chat explica variáveis e deixa revisar o HTML de uma proposta', async ({ page }) => {
  const html = execFileSync('php', [fixture], { encoding: 'utf8', cwd: raiz });
  const proposta = JSON.parse(execFileSync('php', [fixture, '--assistente'], {
    encoding: 'utf8', cwd: raiz, input: JSON.stringify({
      mensagem: 'Preparei uma autorização com o nome do aluno.',
      html: '<h1>Autorização de passeio</h1><p>Aluno: {{aluno_nome}}</p><script>alert(1)</script>',
    }),
  }));
  const pedidos: { role: string; content: string }[][] = [];
  let consultasDaProposta = 0;
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'editor.localhost') return route.abort();
    if (url.pathname.startsWith('/static/')) return route.fulfill({ path: path.join(raiz, 'backend/public', url.pathname) });
    if (url.pathname.endsWith('/editor')) return route.fulfill({ contentType: 'text/html', body: html });
    if (url.pathname.endsWith('/assistente')) {
      const corpo = await new Response(route.request().postDataBuffer(), {
        headers: { 'content-type': route.request().headers()['content-type'] },
      }).formData();
      expect(corpo.get('_token')).toBe('csrf-teste-local');
      pedidos.push(JSON.parse(String(corpo.get('mensagens'))));
      return route.fulfill({ json: { success: true, job_id: pedidos.length } });
    }
    if (url.pathname.endsWith('/ai-job/1/status')) {
      return route.fulfill({ json: { status: 'done', result: { mensagem: 'Use {{aluno_foto_html}}.' } } });
    }
    if (url.pathname.endsWith('/ai-job/2/status')) {
      if (++consultasDaProposta === 1) return route.fulfill({ status: 503 });
      return route.fulfill({ json: { status: 'done', result: proposta } });
    }
    if (url.pathname.endsWith('/ai-job/3/status')) {
      return route.fulfill({ json: { status: 'failed', error: 'Não foi possível gerar a revisão.' } });
    }
    return route.fulfill({ json: { ok: true, turmas: [], boletins: [] } });
  });
  await page.goto('http://editor.localhost/admin/modelos-documentos/21/editor');
  const assistente = page.getByRole('button', { name: 'Abrir assistente de documentos' });
  await assistente.click();
  await expect(page.getByRole('button', { name: 'Reproduzir de imagem' })).toBeVisible();
  const chat = page.getByRole('button', { name: /^Chat/ });
  await chat.click();
  const campo = page.getByLabel('Sua mensagem');
  const enviar = page.getByRole('button', { name: 'Enviar', exact: true });
  await page.getByRole('button', { name: 'Qual variável insere a foto do aluno?' }).click();
  await enviar.click();
  await expect(page.getByText('Use {{aluno_foto_html}}.')).toBeVisible();
  await campo.fill('Monte uma autorização de passeio.');
  await enviar.click();
  const revisar = page.getByRole('button', { name: 'Revisar layout' });
  await expect(revisar).toBeVisible();
  expect(consultasDaProposta).toBe(2);
  expect(pedidos).toHaveLength(2);
  expect(pedidos[1].map(mensagem => mensagem.role)).toEqual(['user', 'assistant', 'user']);

  const folha = page.locator('#edoc-paper');
  await revisar.click();
  const dialogo = page.getByRole('dialog', { name: 'Proposta de layout' });
  await expect(dialogo).toBeVisible();
  await expect(folha).not.toContainText('Autorização de passeio');
  const codigo = dialogo.getByLabel('HTML gerado');
  const original = await codigo.inputValue();
  expect(original).toContain('{{aluno_nome}}');
  expect(original).not.toContain('<script');
  await dialogo.getByRole('button', { name: 'Descartar' }).click();
  await expect(folha).not.toContainText('Autorização de passeio');
  await revisar.click();
  await codigo.fill(original.replace('Autorização de passeio', 'Autorização de visita'));
  await expect(page.frameLocator('#edoc-ia-previa').getByText('Autorização de visita')).toBeVisible();
  await dialogo.getByRole('button', { name: 'Inserir na folha' }).click();
  await expect(folha).toContainText('Autorização de visita');

  await campo.fill('Mude o título da proposta.');
  await enviar.click();
  await expect(page.getByText('Não foi possível gerar a revisão.')).toBeVisible();
  await expect(campo).toHaveValue('Mude o título da proposta.');
  expect(pedidos[2][3].content).toContain('<h1>Autorização de passeio</h1>');
  await campo.press('Escape');
  await expect(assistente).toBeFocused();
  await expect(assistente).toHaveAttribute('aria-expanded', 'false');
  await assistente.click();
  await chat.click();
  await expect(revisar).toBeVisible();
});

test('o chat aparece e consulta variáveis com a IA desativada', async ({ page }) => {
  const html = execFileSync('php', [fixture, '--sem-ia-chat'], { encoding: 'utf8', cwd: raiz });
  let pedidosIa = 0;
  await page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'editor.localhost') return route.abort();
    if (url.pathname.startsWith('/static/')) return route.fulfill({ path: path.join(raiz, 'backend/public', url.pathname) });
    if (url.pathname.endsWith('/editor')) return route.fulfill({ contentType: 'text/html', body: html });
    if (url.pathname.endsWith('/assistente')) pedidosIa++;
    return route.fulfill({ json: { ok: true, turmas: [], boletins: [] } });
  });
  await page.goto('http://editor.localhost/admin/modelos-documentos/21/editor');
  await page.getByRole('button', { name: 'Abrir assistente de documentos' }).click();
  await expect(page.getByRole('button', { name: 'Reproduzir de imagem' })).toBeVisible();
  await page.getByRole('button', { name: /^Chat/ }).click();
  const conversa = page.getByRole('log', { name: 'Conversa do assistente' });
  await expect(conversa).toContainText('a escola precisa ter TudiCoins ativo');
  await page.getByRole('button', { name: 'Qual variável insere a foto do aluno?' }).click();
  await page.getByRole('button', { name: 'Enviar', exact: true }).click();
  await expect(conversa).toContainText('Foto do aluno — {{aluno_foto_html}}');
  await page.getByLabel('Sua mensagem').fill('Qual variável insere o RA do aluno?');
  await page.getByRole('button', { name: 'Enviar', exact: true }).click();
  await expect(conversa).toContainText('RA do aluno — {{aluno_ra}}');
  expect(pedidosIa).toBe(0);
});
