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
