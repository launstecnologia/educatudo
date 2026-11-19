import { test, expect, Page } from '@playwright/test';

/**
 * E2E EducaSync — monta um cenário pequeno, gera grade e confere versão.
 * Pré-requisitos locais:
 * - docker compose up (override EDUCASYNC_*)
 * - solver em :8091
 * - php scripts/habilitar_educasync_local.php
 * - admin@colag.local / 123456
 */

const ADMIN_EMAIL = process.env.QA_ADMIN_EMAIL ?? 'admin@colag.local';
const ADMIN_SENHA = process.env.QA_ADMIN_SENHA ?? '123456';

async function loginAdmin(page: Page): Promise<void> {
  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });
  const login = page.locator('#loginForm');
  const sidebar = page.locator('#sidebar');
  await Promise.race([
    sidebar.waitFor({ state: 'visible', timeout: 15_000 }),
    login.waitFor({ state: 'visible', timeout: 15_000 }),
  ]).catch(() => undefined);

  if (await login.isVisible().catch(() => false)) {
    await page.locator('#login').fill(ADMIN_EMAIL);
    await page.locator('#senha').fill(ADMIN_SENHA);
    await Promise.all([
      page.waitForURL(/\/admin\/dashboard/, { timeout: 30_000 }),
      page.getByRole('button', { name: 'Acessar Painel Administrativo' }).click(),
    ]);
  }
  await expect(page).toHaveURL(/\/admin\/dashboard/);
  await expect(sidebar).toBeVisible({ timeout: 20_000 });
}

test.describe.configure({ mode: 'serial' });

test('EducaSync: criar cenário, slots, demanda, gerar e ver versão', async ({ page }) => {
  test.setTimeout(300_000);
  await loginAdmin(page);

  await page.goto('/admin/educasync', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('h2').filter({ hasText: /EducaSync/i }).first()).toBeVisible({ timeout: 20_000 });

  const avisoMigration = page.getByText(/Execute a migration do EducaSync/i);
  if (await avisoMigration.isVisible().catch(() => false)) {
    throw new Error('Migration EducaSync não aplicada. Rode habilitar_educasync_local.php');
  }

  await page.getByRole('link', { name: /Novo cenário|Novo/i }).first().click();
  await page.waitForURL(/\/admin\/educasync\/novo/);

  const nome = `E2E EducaSync ${Date.now()}`;
  await page.locator('input[name="nome"]').fill(nome);
  const ano = page.locator('input[name="ano_letivo"], select[name="ano_letivo"]').first();
  if (await ano.evaluate((el) => el.tagName.toLowerCase()) === 'select') {
    await ano.selectOption('2026');
  } else {
    await ano.fill('2026');
  }
  const periodo = page.locator('select[name="periodo"]');
  if (await periodo.count()) {
    await periodo.selectOption('manha');
  }
  await page.getByRole('button', { name: /Salvar|Criar/i }).first().click();
  await page.waitForURL(/\/admin\/educasync\/\d+/);

  const formSlots = page.locator('form[action*="/slots"]').first();
  await expect(formSlots).toBeVisible();
  await formSlots.locator('input[name="hora_inicio"]').fill('07:00');
  await formSlots.locator('input[name="duracao_min"]').fill('50');
  await formSlots.locator('input[name="aulas_por_dia"]').fill('3');
  await formSlots.locator('input[name="intervalo_hora"]').fill('09:40');
  await formSlots.locator('input[name="intervalo_min"]').fill('20');
  await formSlots.getByRole('button', { name: /Gerar horários/i }).click();
  await expect(page.locator('body')).toContainText(/horário/i, { timeout: 15_000 });

  const abaTurma = page.locator('.educasync-demanda-aba').first();
  const materia = page.locator('#materia_id');
  const professor = page.locator('#professor_id');
  await expect(abaTurma).toBeVisible();
  await abaTurma.click();

  const materiasOpts = await materia.locator('option').count();
  const profsOpts = await professor.locator('option').count();
  test.skip(materiasOpts < 2 || profsOpts < 2, 'Sem matéria/professor ativos em 2026');

  await materia.selectOption({ index: 1 });
  await professor.selectOption({ index: 1 });
  await page.locator('#aulas_semana').fill('2');
  await page.getByRole('button', { name: /Salvar demanda/i }).click();
  await expect(page.locator('.educasync-demanda-painel:not(.hidden) table tbody tr').first()).toBeVisible({ timeout: 15_000 });

  page.once('dialog', async (d) => d.accept());
  await page.getByRole('button', { name: /Gerar grade/i }).click();

  const linkVersao = page.locator('a[href*="/versoes/"]').first();
  await expect(linkVersao).toBeVisible({ timeout: 180_000 });
  await linkVersao.click();
  await page.waitForURL(/\/versoes\/\d+/);

  await expect(page.locator('h2').filter({ hasText: /Versão #/i })).toBeVisible();
  await expect(page.getByText(/aula\(s\) alocada|Grade da versão/i).first()).toBeVisible();
  await expect(page.locator('.educasync-versao-turma-painel:not(.hidden) table').first()).toBeVisible();
  await expect(page.getByRole('button', { name: /Reotimizar/i })).toBeVisible();
});
