import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.QA_BASE_URL ?? 'http://educa.localhost';
const ADMIN_EMAIL = process.env.QA_ADMIN_EMAIL ?? 'admin@educa.local';
const ADMIN_SENHA = process.env.QA_ADMIN_SENHA ?? '123456';

async function loginAdmin(page: Page): Promise<void> {
  await page.goto(`${BASE}/admin`, { waitUntil: 'domcontentloaded' });
  const login = page.locator('#loginForm');
  await login.waitFor({ state: 'visible', timeout: 15_000 });
  await page.locator('#login').fill(ADMIN_EMAIL);
  await page.locator('#senha').fill(ADMIN_SENHA);
  await Promise.all([
    login.waitFor({ state: 'hidden', timeout: 20_000 }),
    page.getByRole('button', { name: /Acessar Painel/i }).click(),
  ]);
}

async function novaConversaAssistente(page: Page): Promise<void> {
  await page.goto(`${BASE}/admin/assistente`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#assistente-input')).toBeEnabled({
    timeout: 20_000,
  });
  const nova = page.locator('#assistente-nova-conversa');
  const criada = page.waitForResponse(res => res.url().endsWith('/conversas/criar') && res.ok());
  await nova.click();
  await criada;
  await expect(page.locator('#assistente-titulo-ativo')).toHaveText('Nova conversa');
}

async function enviar(page: Page, texto: string): Promise<void> {
  const input = page.locator('#assistente-input');
  await input.fill(texto);
  await page.getByRole('button', { name: 'Enviar', exact: true }).click();
}

test.describe('Tudinha ocorrência', () => {
  test.setTimeout(180_000);

  test('fluxo por RA até Confirmar', async ({ page }) => {
    await loginAdmin(page);
    await novaConversaAssistente(page);

    await enviar(page, 'criar ocorrência RA 24254');
    const mensagens = page.locator('#assistente-mensagens');
    await mensagens.getByRole('button', { name: 'Comportamento / convivência', exact: true }).click();
    await expect(mensagens.getByText('Descreva o que aconteceu.', { exact: true })).toBeVisible();
    await enviar(page, 'A aluna conversou várias vezes e não parou quando o professor pediu.');
    await mensagens.getByRole('button', { name: 'Leve', exact: true }).click();
    await mensagens.getByRole('button', { name: 'Não, só essa pessoa', exact: true }).click();
    await expect(mensagens.getByRole('button', { name: 'Confirmar', exact: true })).toHaveCount(0);
    await mensagens.getByRole('button', { name: 'Sim, notificar pais', exact: true }).click();
    await expect(mensagens.getByRole('button', { name: 'Confirmar', exact: true })).toBeVisible({ timeout: 60_000 });
    await expect(mensagens.getByText(/RASCUNHO/i).first()).toBeVisible();
  });

  test('fluxo por nome completo pede categoria sem rascunho vazio', async ({ page }) => {
    await loginAdmin(page);
    await novaConversaAssistente(page);

    await enviar(page, 'quero fazer uma ocorrência da aluna Ana Luiza Oliveira De Souza');
    await expect(page.locator('#assistente-mensagens').getByText(/Qual a.*categoria|Confirme o aluno|mais de um aluno/i).first()).toBeVisible({
      timeout: 60_000,
    });
    await expect(page.getByText(/para Ana Luiza»/i)).toHaveCount(0);
    await expect(page.getByText(/Selecione uma categoria/i)).toHaveCount(0);
  });

  test('consulta de ocorrências mantém o ano informado', async ({ page }) => {
    await loginAdmin(page);
    await novaConversaAssistente(page);
    await enviar(page, 'Quais foram as ocorrências do aluno RA 24254 em 2099?');
    const mensagens = page.locator('#assistente-mensagens');
    await expect(mensagens.getByText(/não tem ocorrências registradas no filtro atual/i)).toBeVisible({ timeout: 60_000 });
    await expect(mensagens.getByRole('button', { name: 'Confirmar', exact: true })).toHaveCount(0);
  });

  test('frequência informa que o ano solicitado está indisponível', async ({ page }) => {
    await loginAdmin(page);
    await novaConversaAssistente(page);
    await enviar(page, 'Quantas faltas tem a aluna RA 24254 em 2099?');
    await expect(page.locator('#assistente-mensagens').getByText('Ano letivo 2099 não encontrado.', { exact: true }))
      .toBeVisible({ timeout: 60_000 });
  });

  test('grade mantém a data da turma solicitada', async ({ page }) => {
    await loginAdmin(page);
    await novaConversaAssistente(page);
    await enviar(page, 'Qual é a grade da turma 2ª B em 14/11/2026?');
    const mensagens = page.locator('#assistente-mensagens');
    await expect(mensagens.getByText('Há mais de uma turma. Escolha uma:', { exact: true }).first()).toBeVisible();
    await expect(mensagens.getByRole('row', { name: /^2A Confirmar$/ })).toHaveCount(0);
    await mensagens.getByRole('row', { name: /^2B Confirmar$/ }).first().getByRole('button', { name: 'Confirmar' }).click();
    await expect(mensagens.getByText(/\(14\/11\/2026\)/).first())
      .toBeVisible({ timeout: 60_000 });
  });

  test('portaria prepara saída no dia e horário informados', async ({ page }) => {
    await loginAdmin(page);
    await novaConversaAssistente(page);
    await enviar(page, 'Registrar saída da aluna RA 24254 em 12/11/2026 às 17h25.');
    const mensagens = page.locator('#assistente-mensagens');
    await expect(mensagens.getByRole('button', { name: 'Confirmar', exact: true })).toBeVisible({ timeout: 60_000 });
    await expect(mensagens.getByText('12/11/2026 17:25', { exact: true })).toBeVisible();
  });

  for (const caso of [
    { nome: 'lista provas pelo RA', pedido: 'Quais provas o RA 24254 fez?', tool: 'listar_provas_aluno', paineis: ['lista'] },
    { nome: 'consulta jornadas pelo RA', pedido: 'Como estão as jornadas da aluna RA 24254?', tool: 'resumo_jornadas_aluno', paineis: ['resumo_jornadas'] },
    { nome: 'consulta evento pela sigla', pedido: 'Qual foi a nota na PB de Matemática do RA 24254 em 2024?', tool: 'nota_evento_sigla', paineis: ['eventos_avaliativos_disponiveis', 'candidatos_eventos_sigla'] },
    { nome: 'consulta diários em atraso', pedido: 'Quais diários estão em atraso?', tool: 'diario_pendencias', paineis: ['diario_pendencias'] },
  ]) {
    test(caso.nome, async ({ page }) => {
      await loginAdmin(page);
      await novaConversaAssistente(page);
      const resposta = page.waitForResponse(res => res.url().endsWith('/mensagem-stream') && res.ok());
      await enviar(page, caso.pedido);
      const stream = await (await resposta).text();
      const eventoFinal = stream.match(/^event: done\r?\ndata: (.+)$/m);
      expect(eventoFinal).not.toBeNull();
      const turno = JSON.parse(eventoFinal![1]);
      expect(turno.consultas).toEqual(expect.arrayContaining([expect.objectContaining({ tool: caso.tool })]));
      expect(caso.paineis).toContain(turno.painel.tipo);
    });
  }
});
