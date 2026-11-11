import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const raiz = path.resolve(__dirname, '..');
let html: string;
let pdf: Buffer;
let modoPdf: 'pdf' | 'html' | 'erro' = 'pdf';
let ultimoSalvo: any;
let falharSalvar = false;
let pedidosPdf: string[];

// O HTML de produção é exercitado sem gravar documentos ou notas de uma escola.
test.beforeAll(() => {
  pdf = execFileSync('php', [path.join(__dirname, 'fixtures/editor-documento.php'), '--pdf'], { cwd: raiz });
  html = execFileSync('php', [path.join(__dirname, 'fixtures/editor-documento.php')], { cwd: raiz, encoding: 'utf8' });
});
test.beforeEach(async ({ page }) => {
  ultimoSalvo = null;
  falharSalvar = false;
  pedidosPdf = [];
  modoPdf = 'pdf';
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'editor.localhost') return route.abort();
    if (url.pathname.startsWith('/static/')) return route.fulfill({ path: path.join(raiz, 'backend/public', url.pathname) });
    if (url.pathname.endsWith('/editor')) {
      const atual = ultimoSalvo ? html.replace(/^  estrutura: .*,$/m, '  estrutura: ' + JSON.stringify(ultimoSalvo.estrutura) + ',') : html;
      return route.fulfill({ contentType: 'text/html', body: atual });
    }
    if (url.pathname.endsWith('/demonstracao')) return route.fulfill({ json: { ok: true, turmas: [], boletins: [] } });
    if (url.pathname.endsWith('/estrutura')) {
      if (falharSalvar) return route.fulfill({ json: { ok: false, error: 'Falha de teste ao salvar' } });
      ultimoSalvo = route.request().postDataJSON();
      return route.fulfill({ json: { ok: true, id: 21 } });
    }
    if (url.pathname.endsWith('/preview')) {
      pedidosPdf.push(route.request().url());
      // O conteúdo simulado serve só para testar o transporte do PDF; não simula o canvas.
      if (modoPdf === 'html') return route.fulfill({ contentType: 'text/html', body: '<h1>Sessão expirada</h1>' });
      if (modoPdf === 'erro') return route.fulfill({ status: 500, contentType: 'application/pdf', body: pdf });
      return route.fulfill({ contentType: 'application/pdf', body: pdf });
    }
    return route.abort();
  });
  await page.goto('http://editor.localhost/admin/modelos-documentos/21/editor');
  await expect(page.locator('#edoc-paper')).toBeVisible();
});

async function criarTabela(page: Page, linhas = 3, colunas = 4) {
  await page.getByRole('button', { name: 'Tabela editável', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Criar tabela' })).toBeVisible();
  await page.getByLabel('Linhas', { exact: true }).fill(String(linhas));
  await page.getByLabel('Colunas', { exact: true }).fill(String(colunas));
  await page.screenshot({ path: test.info().outputPath('criar-tabela.png'), fullPage: true });
  await page.getByRole('dialog', { name: 'Criar tabela' }).getByRole('button', { name: 'Inserir tabela', exact: true }).click();
  return page.locator('#edoc-paper table.edoc-tabela-editavel');
}

test('busca encontra elementos sem acento e mantém as proporções avançadas recolhidas', async ({ page }) => {
  await page.screenshot({ path: test.info().outputPath('menu-inserir.png'), fullPage: true });
  await expect(page.locator('[data-drag-layout]')).toHaveCount(11);
  await expect(page.locator('.edoc-layout-mais')).not.toHaveAttribute('open');
  await page.getByLabel('O que você quer adicionar?').fill('titulo');
  await expect(page.locator('[data-drag-type]:visible')).toHaveCount(1);
  await expect(page.getByRole('button', { name: 'Título', exact: true })).toBeVisible();
  await page.getByLabel('O que você quer adicionar?').fill('semcorrespondencia');
  await expect(page.getByText('Nenhum elemento encontrado.', { exact: false })).toBeVisible();
  await page.getByLabel('O que você quer adicionar?').fill('');
  await expect(page.locator('.edoc-layout-mais')).not.toHaveAttribute('open');
  await expect(page.getByRole('button', { name: 'Tabela editável', exact: true })).toBeVisible();
});

test('cancelar ou informar uma dimensão inválida não insere tabela', async ({ page }) => {
  await page.getByRole('button', { name: 'Tabela editável', exact: true }).press('Enter');
  await page.getByLabel('Linhas', { exact: true }).fill('0');
  await page.getByRole('dialog', { name: 'Criar tabela' }).getByRole('button', { name: 'Inserir tabela', exact: true }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.locator('#edoc-paper table')).toHaveCount(0);
  await page.getByRole('button', { name: 'Cancelar', exact: true }).click();
  await expect(page.getByRole('dialog')).not.toBeVisible();
});

test('cria a dimensão escolhida, permite editar cabeçalho e avançar com Tab', async ({ page }) => {
  const tabela = await criarTabela(page);
  await expect(tabela.locator('tr')).toHaveCount(3);
  await expect(tabela.locator('th')).toHaveCount(4);
  await expect(page.locator('#edoc-props .edoc-rte')).toHaveCount(0);
  await tabela.locator('th').first().fill('Disciplina');
  await tabela.locator('th').first().press('Tab');
  await expect(tabela.locator('th').nth(1)).toBeFocused();
  await tabela.locator('th').nth(1).fill('Nota');
  await tabela.locator('th').nth(1).press('Shift+Tab');
  await expect(tabela.locator('th').first()).toBeFocused();
  await page.screenshot({ path: test.info().outputPath('tabela-na-folha.png'), fullPage: true });
});

test('adiciona linha e coluna por botões nomeados, salva e restaura conteúdo e grade', async ({ page }) => {
  let tabela = await criarTabela(page);
  await tabela.locator('th').first().fill('Componente');
  await page.getByRole('button', { name: 'Adicionar linha', exact: true }).click();
  await expect(tabela.locator('tr')).toHaveCount(4);
  await expect(tabela.locator('tr').nth(1).locator('td')).toHaveCount(4);
  await expect(tabela.locator('th')).toHaveCount(4);
  await tabela.locator('td').first().click();
  await page.getByRole('button', { name: 'Adicionar coluna', exact: true }).click();
  await expect(tabela.locator('tr').last().locator('td')).toHaveCount(5);
  await expect(tabela.locator('col')).toHaveCount(5);
  const dimensoes = await tabela.evaluate(t => ({ tabela: t.getBoundingClientRect().width, coluna: t.closest('.edoc-col')!.getBoundingClientRect().width }));
  expect(dimensoes.tabela).toBeLessThanOrEqual(dimensoes.coluna + 2);
  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  await page.reload();
  tabela = page.locator('#edoc-paper table.edoc-tabela-editavel');
  await expect(tabela.locator('tr')).toHaveCount(4);
  await expect(tabela.locator('col')).toHaveCount(5);
  await expect(tabela.locator('th').first()).toHaveText('Componente');
});

test('Tab na última célula cria uma linha e continua a edição', async ({ page }) => {
  const tabela = await criarTabela(page, 2, 2);
  await tabela.locator('td').last().click();
  await tabela.locator('td').last().fill('Última');
  await tabela.locator('td').last().press('Tab');
  await expect(tabela.locator('tr')).toHaveCount(3);
  await expect(tabela.locator('tr').last().locator('td').first()).toBeFocused();
});

test('Ver resultado salva e mostra o endpoint do PDF dentro do editor; voltar mantém a tabela', async ({ page }) => {
  await criarTabela(page, 2, 3);
  await page.getByRole('button', { name: 'Ver resultado', exact: true }).click();
  await expect(page.locator('#edoc-pdf-preview')).toBeVisible();
  await expect.poll(() => pedidosPdf.length).toBeGreaterThan(0);
  const origem = await page.locator('#edoc-pdf-frame').getAttribute('src');
  expect(origem).toMatch(/^blob:http:\/\/editor\.localhost\//);
  expect(pedidosPdf[0]).toContain('/admin/modelos-documentos/21/preview?t=');
  expect(ultimoSalvo.estrutura.body.sections[0].columns[0].elements[0].props.html).toContain('edoc-tabela-editavel');
  await expect(page.locator('.edoc-stage')).not.toBeVisible();
  await page.getByRole('button', { name: 'Voltar à edição', exact: true }).click();
  await expect(page.locator('.edoc-stage')).toBeVisible();
  await expect(page.locator('#edoc-paper table.edoc-tabela-editavel tr')).toHaveCount(2);
});

test('falha ao salvar não apresenta PDF antigo como se fosse a versão atual', async ({ page }) => {
  falharSalvar = true;
  await page.getByRole('button', { name: 'Ver resultado', exact: true }).click();
  await expect(page.locator('#edoc-pdf-message')).toContainText('Não foi possível salvar');
  await expect(page.locator('#edoc-pdf-frame')).not.toBeVisible();
  expect(pedidosPdf).toHaveLength(0);
  await expect(page.getByRole('button', { name: 'Atualizar prévia' })).toBeEnabled();
});


test('excluir coluna atualiza a grade e mantém a edição recuperável', async ({ page }) => {
  const tabela = await criarTabela(page, 3, 3);
  await tabela.locator('td').first().click();
  await page.getByText('Mais ações da tabela', { exact: true }).click();
  await page.getByRole('button', { name: 'Excluir coluna', exact: true }).click();
  await expect(tabela.locator('tr').last().locator('td')).toHaveCount(2);
  await expect(tabela.locator('col')).toHaveCount(2);
  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  await page.reload();
  await expect(page.locator('#edoc-paper table.edoc-tabela-editavel col')).toHaveCount(2);
});

test('HTML com parágrafos fora da tabela mantém o editor de conteúdo completo', async ({ page }) => {
  await criarTabela(page, 2, 2);
  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  const elemento = ultimoSalvo.estrutura.body.sections[0].columns[0].elements[0];
  elemento.props.html = '<p>Texto antes da tabela</p>' + elemento.props.html + '<p>Observação depois da tabela</p>';
  await page.reload();
  await page.locator('#edoc-paper table td').first().click();
  await expect(page.locator('#edoc-props .edoc-rte')).toBeVisible();
  await expect(page.locator('#edoc-props .edoc-rte')).toContainText('Texto antes da tabela');
  await expect(page.locator('#edoc-props .edoc-rte')).toContainText('Observação depois da tabela');
});

for (const falha of ['html', 'erro'] as const) {
  test('prévia rejeita resposta ' + falha + ' em vez de mostrar como PDF concluído', async ({ page }) => {
    modoPdf = falha;
    await page.getByRole('button', { name: 'Ver resultado', exact: true }).click();
    await expect(page.locator('#edoc-pdf-message')).toContainText('Não foi possível gerar o PDF');
    await expect(page.locator('#edoc-pdf-frame')).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Atualizar prévia' })).toBeEnabled();
    modoPdf = 'pdf';
    await page.getByRole('button', { name: 'Atualizar prévia' }).click();
    await expect(page.locator('#edoc-pdf-frame')).toBeVisible();
  });
}


test('a tabela cabe na coluna escolhida e Ajustar à tela mostra a folha inteira', async ({ page }) => {
  await page.getByRole('button', { name: '2 colunas', exact: true }).click();
  const coluna = page.locator('#edoc-paper .edoc-col').last();
  await coluna.click();
  const tabela = await criarTabela(page, 3, 3);
  const medidas = await tabela.evaluate(t => ({ tabela: t.getBoundingClientRect().width, coluna: t.closest('.edoc-col')!.getBoundingClientRect().width }));
  expect(medidas.tabela).toBeLessThanOrEqual(medidas.coluna + 1);
  await page.locator('#edoc-zoom-fit').click();
  const folha = await page.locator('#edoc-paper').boundingBox();
  const palco = await page.locator('.edoc-stage').boundingBox();
  expect(folha!.x).toBeGreaterThanOrEqual(palco!.x);
  expect(folha!.x + folha!.width).toBeLessThanOrEqual(palco!.x + palco!.width);
});


async function selecionarHtmlBoletim(page: Page, codigo: string) {
  const estrutura = await page.evaluate(() => (window as any).EDOC.estrutura);
  estrutura.body.sections[0].columns[0].elements = [{ id: 'html_boletim_teste', type: 'boletim', props: { html: codigo }, style: { fontSize: 7.5 } }];
  ultimoSalvo = { estrutura };
  await page.reload();
  await page.locator('.edoc-el[data-type="boletim"]').click();
  await expect(page.getByRole('button', { name: 'Editar em HTML' })).toBeVisible();
}

const codigoBoletim = '<style type="text/css">.exemplo{color:red;--imagem:url("data:image/svg+xml;a;b");content:"a;b{}";}</style><div class="quadro-notas-wrap"><p class="inline"><span>Nome</span><strong>Aluno</strong></p><div>{{boletim_html}}</div></div>';

test('HTML abre expandido e indentado sem mudar textos inline, CSS ou variáveis', async ({ page }) => {
  await selecionarHtmlBoletim(page, codigoBoletim);
  await expect(page.locator('#edoc-boletim-html')).toHaveValue(/\n  \.exemplo \{\n    color:red;/);
  await page.getByRole('button', { name: 'Editar em HTML' }).click();
  const dialogo = page.getByRole('dialog', { name: 'Editar HTML do boletim' });
  await expect(dialogo).toBeVisible();
  const campo = page.getByLabel('Código HTML', { exact: true });
  const formatado = await campo.inputValue();
  expect(formatado).toContain('<style type="text/css">\n');
  expect(formatado).toContain('content:"a;b{}";');
  expect(formatado).toContain('url("data:image/svg+xml;a;b")');
  expect(formatado).toContain('<span>Nome</span><strong>Aluno</strong>');
  expect(formatado).toContain('    {{boletim_html}}');
  await page.getByRole('button', { name: 'Indentar código', exact: true }).click();
  await expect(campo).toHaveValue(formatado);
  const dimensao = await dialogo.boundingBox();
  expect(dimensao!.width).toBeGreaterThan(600);
  expect(Math.round(dimensao!.x + dimensao!.width)).toBe(page.viewportSize()!.width);
  await expect(page.locator('#edoc-paper')).toBeVisible();
  await page.screenshot({ path: test.info().outputPath('html-expandido.png'), fullPage: true });
  await campo.press('Escape');
  await page.getByRole('button', { name: 'Ver resultado', exact: true }).click();
  await expect(page.locator('#edoc-pdf-frame')).toBeVisible();
  await expect(page.locator('#edoc-paper')).not.toBeVisible();
});

test('fechar o editor HTML pelo botão descarta o rascunho', async ({ page }) => {
  await selecionarHtmlBoletim(page, codigoBoletim);
  await page.getByRole('button', { name: 'Editar em HTML' }).click();
  await page.getByLabel('Código HTML', { exact: true }).fill('<p>Não aplicar</p>');
  await page.getByRole('button', { name: 'Fechar editor HTML', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Editar HTML do boletim' })).not.toBeVisible();
  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  expect(ultimoSalvo.estrutura.body.sections[0].columns[0].elements[0].props.html).toBe(codigoBoletim);
});

test('aplicar HTML expandido atualiza a folha e persiste o código editado', async ({ page }) => {
  await selecionarHtmlBoletim(page, codigoBoletim);
  await page.getByRole('button', { name: 'Editar em HTML' }).click();
  const campo = page.getByLabel('Código HTML', { exact: true });
  await campo.fill('<div class="quadro-notas-wrap"><p>Observação editada</p>{{boletim_html}}</div>');
  await page.getByRole('button', { name: 'Indentar código', exact: true }).click();
  await page.getByRole('button', { name: 'Aplicar na folha', exact: true }).click();
  await expect(page.locator('#edoc-paper')).toContainText('Observação editada');
  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  const salvo = ultimoSalvo.estrutura.body.sections[0].columns[0].elements[0].props.html;
  expect(salvo).toContain('Observação editada');
  expect(salvo).toContain('{{boletim_html}}');
  await page.reload();
  await expect(page.locator('#edoc-paper')).toContainText('Observação editada');
});


for (const codigo of ['<span>Nome</span><strong>Aluno</strong>', 'Texto &lt;b&gt;literal&lt;/b&gt;<div>{{boletim_html}}</div>']) {
  test('formatar e aplicar preserva fragmento raiz: ' + codigo.slice(0, 25), async ({ page }) => {
    await selecionarHtmlBoletim(page, codigo);
    await page.getByRole('button', { name: 'Editar em HTML' }).click();
    await expect(page.getByLabel('Código HTML', { exact: true })).toHaveValue(codigo);
    await page.getByRole('button', { name: 'Indentar código', exact: true }).click();
    await page.getByRole('button', { name: 'Aplicar na folha', exact: true }).click();
    await page.locator('#edoc-save').click();
    await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
    expect(ultimoSalvo.estrutura.body.sections[0].columns[0].elements[0].props.html).toBe(codigo);
  });
}

test('busca no painel lateral localiza variáveis e mantém o código intacto', async ({ page }) => {
  await selecionarHtmlBoletim(page, '<p>{{boletim_html}}</p>' + '<p>Uma linha</p>'.repeat(60) + '<p>{{BOLETIM_HTML}}</p>');
  await page.getByRole('button', { name: 'Editar em HTML' }).click();
  const painel = page.getByRole('dialog', { name: 'Editar HTML do boletim' });
  const campo = painel.getByLabel('Código HTML', { exact: true });
  const fonte = await campo.inputValue();
  const busca = painel.getByRole('searchbox', { name: 'Pesquisar no HTML' });
  await campo.press('Control+f');
  await expect(busca).toBeFocused();
  await busca.fill('{{boletim_html}}');
  await expect(painel.getByRole('status')).toHaveText('1 de 2');
  await busca.press('Enter');
  await expect(painel.getByRole('status')).toHaveText('2 de 2');
  expect(await campo.evaluate((el: HTMLTextAreaElement) => el.value.slice(el.selectionStart, el.selectionEnd))).toBe('{{BOLETIM_HTML}}');
  expect(await campo.evaluate(el => el.scrollTop)).toBeGreaterThan(0);
  await painel.getByRole('button', { name: 'Próxima ocorrência' }).click();
  await expect(painel.getByRole('status')).toHaveText('1 de 2');
  await busca.fill('inexistente');
  await expect(painel.getByRole('status')).toHaveText('Nenhuma ocorrência');
  await expect(painel.getByRole('button', { name: 'Ocorrência anterior' })).toBeDisabled();
  await expect(campo).toHaveValue(fonte);
  await busca.press('Escape');
  await expect(painel).not.toBeVisible();
});

test('Editar em HTML usa o mesmo painel para um bloco de texto', async ({ page }) => {
  const estrutura = await page.evaluate(() => (window as any).EDOC.estrutura);
  estrutura.body.sections[0].columns[0].elements = [{ id: 'texto_html_teste', type: 'texto', props: { text: 'Texto da autorização' }, style: {} }];
  ultimoSalvo = { estrutura };
  await page.reload();
  await page.getByText('Texto da autorização', { exact: true }).click();
  await page.getByRole('button', { name: 'Editar em HTML' }).click();
  const painel = page.locator('#edoc-html-stage');
  await expect(painel).toBeVisible();
  await expect(painel.getByRole('searchbox', { name: 'Pesquisar no HTML' })).toBeVisible();
  await expect(painel.getByLabel('Código HTML', { exact: true })).toHaveValue('Texto da autorização');
});

test('redimensiona foto dinâmica e centraliza apenas a assinatura em um bloco com tabela', async ({ page }) => {
  const estrutura = await page.evaluate(() => (window as any).EDOC.estrutura);
  const fonte = '<p>Aluno: {{aluno_nome}}</p><table><tr><td>{{aluno_foto_html}}</td><td>Dados da matrícula</td></tr></table>'
    + '<p>Atenciosamente,<br>_______________<br>Assinatura do Diretor</p>';
  estrutura.body.sections[0].columns[0].elements = [{ id: 'declaracao_foto', type: 'html', props: { html: fonte }, style: {} }];
  ultimoSalvo = { estrutura };
  await page.reload();

  const bloco = page.locator('#edoc-paper [data-id="declaracao_foto"]');
  const foto = bloco.getByRole('img', { name: 'Foto do aluno' });
  await foto.click();
  const largura = page.getByLabel('Largura da foto (mm)');
  await largura.fill('12');
  await largura.press('Tab');
  await expect(foto).toHaveCSS('width', /45\.3\d+px/);
  await largura.fill('36');
  await largura.press('Tab');
  await expect(foto).toHaveCSS('width', /136\.0\d+px/);
  await expect.poll(() => foto.evaluate(img => img.getBoundingClientRect().width / img.getBoundingClientRect().height)).toBeCloseTo(1);

  const paragrafo = bloco.locator('p').filter({ hasText: 'Atenciosamente,' });
  await paragrafo.click();
  await paragrafo.evaluate(p => {
    const range = document.createRange();
    range.setStartAfter(p.querySelector('br')!);
    range.setEndAfter(p.lastChild!);
    const selecao = window.getSelection()!;
    selecao.removeAllRanges();
    selecao.addRange(range);
  });
  await page.getByTitle('Centralizar', { exact: true }).click();
  const assinatura = bloco.getByText('Assinatura do Diretor', { exact: true });
  await expect(assinatura).toHaveCSS('text-align', 'center');
  await expect(bloco.getByText('_______________', { exact: true })).toHaveCSS('text-align', 'center');
  await expect(bloco.getByText('Atenciosamente,')).not.toHaveCSS('text-align', 'center');
  await expect(bloco.getByText('Dados da matrícula')).not.toHaveCSS('text-align', 'center');

  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  const salvo = ultimoSalvo.estrutura;
  const props = salvo.body.sections[0].columns[0].elements[0].props;
  expect(props.fotoAlunoLargura).toBe(36);
  expect(props.html).toContain('{{aluno_foto_html}}');
  expect(props.html).toContain('{{aluno_nome}}');
  await page.reload();
  await expect(assinatura).toHaveCSS('text-align', 'center');
  await expect(foto).toHaveCSS('width', /136\.0\d+px/);

  const impressao = execFileSync('php', [path.join(__dirname, 'fixtures/editor-documento.php'), '--impressao-ajustada'], {
    cwd: raiz, encoding: 'utf8', input: JSON.stringify(salvo),
  });
  await page.emulateMedia({ media: 'print' });
  await page.setContent(impressao);
  await expect(page.getByRole('img', { name: 'Foto do aluno' })).toHaveCSS('width', /136\.0\d+px/);
  await expect(page.getByText('Assinatura do Diretor', { exact: false })).toHaveCSS('text-align', 'center');
});

test('edita os rótulos e as bordas do boletim sem salvar as notas do aluno', async ({ page }) => {
  const quadro = '<style>.boletim-quadro-grupo-sep{border-left:3px solid #334155!important}</style>'
    + '<table class="boletim-quadro-tabela"><thead><tr><th rowspan="2">MATÉRIAS</th><th class="boletim-quadro-grupo-sep" colspan="2">1 SEMESTRE</th></tr>'
    + '<tr><th class="boletim-quadro-grupo-sep">REC 1º</th><th>MS</th></tr></thead>'
    + '<tbody><tr data-boletim-materia="17"><td>Matemática</td><td class="boletim-quadro-grupo-sep" data-boletim-coluna="s1_rec">7,5</td><td>8,0</td></tr></tbody></table>';
  const fixture = path.join(__dirname, 'fixtures/editor-documento.php');
  const editavel = JSON.parse(execFileSync('php', [fixture, '--boletim-editavel'], { cwd: raiz, encoding: 'utf8', input: quadro }));
  await page.route('**/demonstracao?*', route => route.fulfill({ json: {
    ok: true, vars: editavel.variaveis, html_boletim: quadro, modelo_boletim_html: editavel.html,
    modelo_quadro_html: '<table><tr><th>Demonstrativo</th></tr></table>',
  } }));
  await selecionarHtmlBoletim(page, '<div class="quadro-notas-wrap">{{boletim_html}}</div>');
  const real = page.getByLabel('Mostrar dados reais na folha');
  await real.evaluate((input: HTMLInputElement) => {
    input.checked = true;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });
  const folha = page.locator('#edoc-paper');
  const celula = folha.getByText('7,5', { exact: true });
  await expect(celula).toBeVisible();
  await page.getByLabel('Espessura da divisória (px)').fill('1');
  await page.getByLabel('Espessura da divisória (px)').press('Tab');
  await expect(celula).toHaveCSS('--doc-divisoria-espessura', '1px');
  await page.getByLabel('Espessura da grade (px)').fill('2');
  await page.getByLabel('Espessura da grade (px)').press('Tab');
  await expect(celula).toHaveCSS('--doc-grade-espessura', '2px');
  await expect(celula).toHaveCSS('--doc-divisoria-espessura', '1px');

  await page.getByRole('button', { name: 'Editar em HTML' }).click();
  const campo = page.getByLabel('Código HTML', { exact: true });
  await expect(campo).toHaveValue(/<th[^>]*>1 SEMESTRE<\/th>/);
  await expect(campo).toHaveValue(/REC 1º/);
  await expect(campo).toHaveValue(/\{\{boletim_celula_layout_t1_m17_s1_rec\}\}/);
  const painel = page.getByRole('dialog', { name: 'Editar HTML do boletim' });
  const busca = painel.getByRole('searchbox', { name: 'Pesquisar no HTML' });
  for (const rotulo of ['1 SEMESTRE', 'REC 1º']) {
    await busca.fill(rotulo);
    await expect(painel.getByRole('status')).toHaveText('1 de 1');
  }
  expect(await campo.inputValue()).not.toContain('>7,5<');
  await page.getByRole('button', { name: 'Voltar à folha', exact: true }).click();
  await expect(page.locator('#edoc-boletim-html')).toHaveValue(/\{\{boletim_html\}\}/);
  await page.getByRole('button', { name: 'Expor tabela completa', exact: true }).click();
  await campo.fill((await campo.inputValue()).replace('REC 1º', 'Recuperação 1º'));
  await page.getByRole('button', { name: 'Aplicar na folha', exact: true }).click();
  await expect(folha).toContainText('Recuperação 1º');
  await expect(celula).toBeVisible();
  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  const salvo = ultimoSalvo.estrutura;
  expect(salvo.body.sections[0].columns[0].elements[0].props.html).toContain('{{boletim_celula_layout_t1_m17_s1_rec}}');
  await page.reload();
  await expect(folha).toContainText('Recuperação 1º');

  const impressao = execFileSync('php', [fixture, '--impressao-ajustada'], {
    cwd: raiz, encoding: 'utf8', input: JSON.stringify({ ...salvo, _boletim_teste: quadro.replace('7,5', '9,0') }),
  });
  await page.setContent(impressao);
  await expect(page.getByText('Recuperação 1º', { exact: true })).toBeVisible();
  const notaImpressa = page.getByText('9,0', { exact: true });
  await expect(notaImpressa).toHaveCSS('border-left-width', '1px');
  await expect(notaImpressa).toHaveCSS('border-bottom-width', '2px');
});

test('cadastro organiza os campos individuais e a busca encontra foto e responsáveis', async ({ page }) => {
  await expect(page.getByText('Dados automáticos', { exact: true })).toHaveCount(0);
  await page.getByLabel('O que você quer adicionar?').fill('foto do aluno');
  await expect(page.locator('[data-drag-var="aluno_foto_html"]:visible')).toHaveCount(1);
  await expect(page.getByText('Cadastro', { exact: true })).toBeVisible();
  await page.getByLabel('O que você quer adicionar?').fill('');
  const cadastro = page.locator('.edoc-tool-group').filter({ has: page.locator('summary > span', { hasText: /^Cadastro$/ }) });
  await cadastro.locator(':scope > summary').click();
  for (const nome of ['Aluno', 'Pais e responsáveis', 'Escola', 'Turma', 'Série', 'Curso', 'Matrícula']) {
    await expect(cadastro.locator('summary').filter({ hasText: new RegExp('^' + nome + '$') })).toBeVisible();
  }
});

test('autorização adiciona campo de emissão obrigatório sem duplicar a definição', async ({ page }) => {
  await page.getByLabel('O que você quer adicionar?').fill('Local');
  const preset = page.locator('[data-emissao-preset]').filter({ hasText: /^Local/ });
  await preset.click();
  await expect(page.locator('#edoc-paper')).toContainText('Local');
  await preset.click();
  await page.locator('#edoc-save').click();
  await expect(page.locator('#edoc-status')).toHaveText('Salvo automaticamente');
  const campos = ultimoSalvo.estrutura.campos_variaveis;
  expect(campos.filter((c: any) => c.chave === 'cv_aut_local')).toHaveLength(1);
  expect(campos.find((c: any) => c.chave === 'cv_aut_local')).toMatchObject({ rotulo: 'Local', obrigatorio: true, mascara: 'texto' });
  await page.reload();
  expect(await page.evaluate(() => (window as any).EDOC.estrutura.campos_variaveis)).toContainEqual(expect.objectContaining({ chave: 'cv_aut_local' }));
});
