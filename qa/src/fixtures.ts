import { test as base, expect, Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { ItemCatalogo } from './catalogo';
import { auditarAcessibilidade } from './a11y';
import { Monitor, prepararPagina } from './monitor';
import { registrar } from './registro';
import { Fluxo, tempoDaAplicacao } from './ux/fluxo';
import { detectarObstaculos } from './ux/obstaculos';

type Fixtures = { monitor: Monitor };

const arquivoSessao = path.join(__dirname, '..', '.auth', 'admin.json');

export const test = base.extend<Fixtures>({
  monitor: async ({ page }, use, testInfo) => {
    const monitor = await prepararPagina(page, testInfo);
    if (testInfo.project.name === 'chromium') {
      await garantirAdmin(page);
    }
    await use(monitor);
    if (testInfo.project.name === 'chromium' && !/logout/i.test(testInfo.title)) {
      const deslogado = await page.locator('#loginForm').isVisible().catch(() => false);
      if (!deslogado) {
        await page.context().storageState({ path: arquivoSessao }).catch(() => undefined);
      }
    }
    monitor.publicar(page.url());
  },
});

async function garantirAdmin(page: Page): Promise<void> {
  const login = page.locator('#loginForm');
  const sidebar = page.locator('#sidebar');
  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
  await Promise.race([
    sidebar.waitFor({ state: 'visible', timeout: 15_000 }),
    login.waitFor({ state: 'visible', timeout: 15_000 }),
  ]).catch(() => undefined);

  if (await login.isVisible().catch(() => false)) {
    const email = process.env.QA_ADMIN_EMAIL ?? '';
    const senha = process.env.QA_ADMIN_SENHA ?? '';
    if (!email || !senha) {
      throw new Error('A sessão de admin não estava válida e QA_ADMIN_EMAIL/QA_ADMIN_SENHA não foram definidos.');
    }
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha', { exact: true }).fill(senha);
    await page.getByRole('button', { name: 'Acessar Painel Administrativo' }).click();
    await page.waitForURL(/\/admin\/dashboard/, { timeout: 30_000 });
  }

  await sidebar.waitFor({ state: 'visible', timeout: 15_000 });
  fs.mkdirSync(path.dirname(arquivoSessao), { recursive: true });
  await page.context().storageState({ path: arquivoSessao });
}

export { expect };

export async function tituloVisivel(page: Page, titulo: string | RegExp): Promise<boolean> {
  const alvo = page.locator('h1, h2, h3').filter({ hasText: titulo }).first();
  return alvo.isVisible().catch(() => false);
}

export async function abrirCatalogo(page: Page, monitor: Monitor, item: ItemCatalogo): Promise<'ok' | 'pular'> {
  const fluxo = new Fluxo(item.fluxo, item.modulo, item.limiteCliques, item.limiteMs);
  const t0 = Date.now();
  const resposta = await page.goto(item.rota, { waitUntil: 'domcontentloaded' });
  const parede = Date.now() - t0;
  fluxo.navegacoes = 1;
  fluxo.etapa(`Abrir ${item.rota}`);
  fluxo.tempoAplicacaoMs = await tempoDaAplicacao(page);
  fluxo.tempoParedeMs = parede;

  if (await page.locator('#loginForm').isVisible().catch(() => false)) {
    throw new Error(`A rota ${item.rota} voltou para o login. A sessão de admin não foi aceita.`);
  }

  const status = resposta?.status() ?? 0;
  if (status >= 500) {
    registrar({
      tipo: 'funcional',
      modulo: item.modulo,
      fluxo: item.fluxo,
      titulo: `HTTP ${status} em ${item.rota}`,
      detalhe: 'A rota respondeu erro de servidor.',
      evidencia: `HTTP ${status}`,
      pagina: item.rota,
      gravidade: 'Crítico',
      defeitoConfirmado: true,
    });
    fluxo.publicar(item.rota);
    throw new Error(`HTTP ${status} em ${item.rota}`);
  }

  const texto = await page.locator('body').innerText().catch(() => '');
  if (/Estrutura ainda não|Rode a migration|não habilitado|módulo desativado|Acesso negado|sem permissão/i.test(texto)
    && !(await tituloVisivel(page, item.titulo))) {
    fluxo.publicar(item.rota);
    return 'pular';
  }

  const ok = item.seletor
    ? await page.locator(item.seletor).first().isVisible().catch(() => false)
    : await tituloVisivel(page, item.titulo);
  if (!ok) {
    registrar({
      tipo: 'funcional',
      modulo: item.modulo,
      fluxo: item.fluxo,
      titulo: 'Título esperado ausente',
      detalhe: `Não apareceu o título da rota ${item.rota}. Trecho da página: ${texto.slice(0, 280)}`,
      pagina: item.rota,
      gravidade: 'Alto',
      evidencia: texto.slice(0, 280),
      defeitoConfirmado: true,
    });
    fluxo.publicar(item.rota);
    throw new Error(`Título ausente em ${item.rota}`);
  }

  await fluxo.contarCampos(page);
  await detectarObstaculos(page, item.modulo, item.fluxo);
  if (status >= 400 && status < 500) {
    registrar({
      tipo: 'erro',
      modulo: item.modulo,
      fluxo: item.fluxo,
      titulo: `HTTP ${status} com conteúdo renderizado`,
      detalhe: `A rota ${item.rota} devolveu ${status}, mas a tela esperada apareceu. O status não confirma sucesso da operação.`,
      evidencia: `HTTP ${status} ${item.rota}`,
      pagina: item.rota,
      gravidade: 'Médio',
      defeitoConfirmado: true,
    });
    monitor.ignorarStatus(status, item.rota);
  }
  if (item.axe) await auditarAcessibilidade(page, item.modulo, item.fluxo);
  fluxo.publicar(item.rota);
  return 'ok';
}
