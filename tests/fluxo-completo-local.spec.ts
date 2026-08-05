/**
 * Fluxo E2E local: master (smoke) → admin escola → professor → aluno.
 *
 * Pré-requisitos: `./scripts/init-local.sh` + Docker rodando.
 * URLs: master.localhost · colag.localhost
 *
 * Login automatizado injeta PHPSESSID (Chromium rejeita cookie Domain=.localhost).
 */
import { test, expect, type Page } from '@playwright/test';
import { CREDENCIAIS, URLS } from './helpers/credenciais';
import { loginTenant, logoutTenant } from './helpers/login';

/** Data local (YYYY-MM-DD) — evita toISOString() em UTC gravar o dia errado no Brasil. */
function dataLocalIso(timeZone = 'America/Sao_Paulo'): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone }).format(new Date());
}

const suffix = `${Date.now()}`;
let turmaId = 0;
const dados = {
  serie: `Série E2E ${suffix}`,
  materia: `Matéria E2E ${suffix}`,
  turma: `Turma E2E ${suffix}`,
  professor: {
    nome: `Prof E2E ${suffix}`,
    email: `prof.e2e.${suffix}@colag.local`,
  },
  jornada: `Jornada E2E ${suffix}`,
  alunos: [
    {
      nome: `Aluno Um E2E ${suffix}`,
      codigo: `RA1-${suffix}`,
      nickname: `aluno1.${suffix}`,
    },
    {
      nome: `Aluno Dois E2E ${suffix}`,
      codigo: `RA2-${suffix}`,
      nickname: `aluno2.${suffix}`,
    },
  ],
};

async function smokeMaster(page: Page) {
  const loginPage = await page.request.get(`${URLS.master}/master`);
  expect(loginPage.status()).toBe(200);
  await expect(loginPage.text()).resolves.toContain('Painel Admin Master');

  // Escola Colag foi criada pelo init-local.sh
  const escolasRes = await page.request.get(`${URLS.master}/master/escolas`);
  expect([200, 302]).toContain(escolasRes.status());
}

async function loginAdmin(page: Page) {
  await loginTenant(page, {
    loginPage: '/admin',
    form: {
      tipo: 'admin_escola',
      login: CREDENCIAIS.admin.email,
      senha: CREDENCIAIS.admin.senha,
    },
    destino: /\/admin\/dashboard/,
  });
}

async function loginProfessor(page: Page, email: string) {
  await loginTenant(page, {
    loginPage: '/professor',
    form: {
      tipo: 'professor',
      login: email,
      senha: CREDENCIAIS.professor.senhaPadrao,
    },
    destino: /\/professor\/(dashboard|alterar-senha-obrigatoria)/,
  });

  if (page.url().includes('alterar-senha-obrigatoria')) {
    await page.fill('#nova_senha', CREDENCIAIS.professor.senhaNova);
    await page.fill('#confirmar_senha', CREDENCIAIS.professor.senhaNova);
    await page.locator('#passwordForm button[type="submit"]').click();
    await page.waitForURL(/\/professor\/dashboard/);
  }
}

async function loginAluno(page: Page, nickname: string) {
  await loginTenant(page, {
    loginPage: '/',
    form: {
      tipo: 'aluno',
      login: nickname,
      senha: CREDENCIAIS.aluno.senha,
    },
    destino: /\/(dashboard|aluno\/alterar-senha-obrigatoria)/,
  });
}

async function cadastrarSerie(page: Page) {
  await page.goto('/admin/serie/create');
  await page.selectOption('#curso_id', { index: 1 });
  await page.fill('#nome', dados.serie);
  await page.getByRole('button', { name: 'Cadastrar Série' }).click();
  await page.waitForURL('**/admin/serie**');
  await expect(page.getByText(/cadastrada com sucesso/i)).toBeVisible();
}

async function cadastrarMateria(page: Page) {
  await page.goto('/admin/subjects/create');
  await page.fill('#nome', dados.materia);
  await page.locator('#subjectForm button[type="submit"]').click();
  await page.waitForURL('**/admin/subjects**', { timeout: 30_000 });
  await expect(page.getByText(/cadastrad[ao] com sucesso/i)).toBeVisible();
}

async function cadastrarTurma(page: Page) {
  await page.goto('/admin/turmas/create');
  await page.fill('#nome', dados.turma);

  const cursoSelect = page.locator('#curso_novo_id');
  if (await cursoSelect.count()) {
    await cursoSelect.selectOption({ index: 1 });
    await page.waitForTimeout(500);
    const serieSelect = page.locator('#serie_id option');
    const options = await serieSelect.count();
    if (options > 1) {
      await page.selectOption('#serie_id', { index: 1 });
    }
  } else {
    await page.selectOption('#curso_id, #serie', { index: 1 });
  }

  await page.locator('#turmaForm button[type="submit"]').click();
  await page.waitForURL('**/admin/turmas**', { timeout: 30_000 });
  await expect(page.getByText(/cadastrad[ao] com sucesso|turma criada/i)).toBeVisible();

  const row = page.getByRole('row').filter({ hasText: dados.turma }).first();
  const hrefTurma =
    (await row.locator('a[href*="/admin/turmas/"]').first().getAttribute('href').catch(() => null)) ??
    (await page.locator(`a[href*="/admin/turmas/"]`).filter({ hasText: dados.turma }).first().getAttribute('href').catch(() => null));
  turmaId = Number(hrefTurma?.match(/\/admin\/turmas\/(\d+)/)?.[1] ?? 0);

  if (turmaId <= 0) {
    const res = await page.request.get('/admin/turmas/by-ano-letivo?ano_letivo=2026');
    const json = await res.json();
    const turma = (json.turmas ?? []).find((t: { nome?: string }) => t.nome === dados.turma);
    turmaId = Number(turma?.id ?? 0);
  }

  expect(turmaId, 'ID da turma criada').toBeGreaterThan(0);
}

async function cadastrarProfessor(page: Page) {
  await page.goto('/admin/teachers');
  await page.getByRole('button', { name: /Novo Professor/i }).first().click();
  await expect(page.locator('#teacherDrawer')).toBeVisible();

  await page.fill('#teacher_nome', dados.professor.nome);
  await page.fill('#teacher_email', dados.professor.email);
  await page.locator(`#teacher-materias-grid input[value="${dados.materia}"]`).check();
  await page.getByRole('checkbox', { name: dados.turma }).check();
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.locator('#teacher-form button[type="submit"]').click(),
  ]);
  await expect(page.getByText(dados.professor.nome)).toBeVisible();
}

async function matricularAluno(page: Page, alunoId: number) {
  await page.goto(`/admin/students/${alunoId}/edit`);
  const token = await page.locator('#studentForm input[name="_token"]').inputValue();
  const response = await page.request.post(`/admin/students/${alunoId}/matricula`, {
    form: {
      _token: token,
      turma_id: String(turmaId),
      ano_letivo_id: '1',
      data_entrada: dataLocalIso(),
      definir_turma_principal: '1',
    },
  });
  const body = await response.json();
  expect(response.ok(), JSON.stringify(body)).toBeTruthy();
  expect(body.success).toBe(true);
}

async function cadastrarAlunoComMatricula(page: Page, aluno: (typeof dados.alunos)[0]) {
  await page.goto('/admin/students/create');
  await page.fill('#nome', aluno.nome);
  await page.fill('#codigo_aluno', aluno.codigo);
  await page.fill('#nickname', aluno.nickname);
  await page.fill('#senha', CREDENCIAIS.aluno.senha);
  await page.locator('#studentForm button[type="submit"]').click();
  await page.waitForURL('**/admin/students/*/edit**', { timeout: 45_000 });

  const alunoId = Number(page.url().match(/students\/(\d+)/)?.[1] ?? 0);
  expect(alunoId, 'ID do aluno criado').toBeGreaterThan(0);

  await page.locator('input[name="primeiro_acesso"][value="0"]').check();
  const [updateResponse] = await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes(`/admin/students/${alunoId}`) && r.request().method() === 'POST',
    ),
    page.locator('#studentForm button[type="submit"]').click(),
  ]);
  expect(updateResponse.ok(), await updateResponse.text()).toBeTruthy();

  await matricularAluno(page, alunoId);
}

async function verJornadaNaListaAluno(page: Page) {
  await Promise.all([
    page.waitForResponse(
      (r) => r.url().includes('/jornadas/api/montar') && r.ok(),
      { timeout: 90_000 },
    ),
    page.goto('/jornadas'),
  ]);
  await expect(page.locator('#jornadasGrid').getByRole('heading', { name: dados.jornada })).toBeVisible({
    timeout: 30_000,
  });
}

async function criarJornadaProfessor(page: Page) {
  await page.goto('/professor/jornadas/criar');
  await page.fill('#titulo', dados.jornada);
  await page.getByRole('checkbox', { name: dados.turma }).check();
  await page.selectOption('#materia_id', { label: dados.materia });

  const hoje = dataLocalIso();
  await page.fill('#data_inicio', hoje);
  await page.fill('#data_fim', hoje);
  await page.selectOption('#bimestre', '2');

  await Promise.all([
    page.waitForURL(/\/professor\/jornadas\/?(\?.*)?$/),
    page.locator('#jornadaForm button[type="submit"]').click(),
  ]);
  await expect(page.getByText(dados.jornada)).toBeVisible({ timeout: 30_000 });
}

test.describe('Fluxo completo local — master, admin, professor e aluno', () => {
  test('cadastra estrutura escolar, cria jornada e aluno acessa', async ({ page }) => {
    await test.step('Master — painel acessível (escola Colag via init-local)', async () => {
      await smokeMaster(page);
    });

    await test.step('Admin — login', async () => {
      await loginAdmin(page);
    });

    await test.step('Admin — série, matéria, turma e professor', async () => {
      await cadastrarSerie(page);
      await cadastrarMateria(page);
      await cadastrarTurma(page);
      await cadastrarProfessor(page);
    });

    await test.step('Admin — cadastra 2 alunos com matrícula', async () => {
      for (const aluno of dados.alunos) {
        await cadastrarAlunoComMatricula(page, aluno);
      }
    });

    await test.step('Professor — login, cria jornada', async () => {
      await logoutTenant(page);
      await loginProfessor(page, dados.professor.email);
      await criarJornadaProfessor(page);
    });

    await test.step('Aluno — login e vê jornada', async () => {
      await logoutTenant(page);
      await loginAluno(page, dados.alunos[0].nickname);
      await page.goto('/dashboard');
      await expect(page.getByRole('heading', { name: /Dashboard/i })).toBeVisible();
      await verJornadaNaListaAluno(page);
    });

    await test.step('Segundo aluno — login e vê a mesma jornada', async () => {
      await logoutTenant(page);
      await loginAluno(page, dados.alunos[1].nickname);
      await verJornadaNaListaAluno(page);
    });
  });
});
