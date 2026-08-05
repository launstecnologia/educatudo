export const CREDENCIAIS = {
  master: {
    email: process.env.E2E_MASTER_EMAIL ?? 'admin@local.educatudo',
    senha: process.env.E2E_MASTER_SENHA ?? 'Teste@123',
  },
  admin: {
    email: process.env.E2E_ADMIN_EMAIL ?? 'admin@colag.local',
    senha: process.env.E2E_ADMIN_SENHA ?? 'Teste@123',
  },
  professor: {
    senhaPadrao: '123456',
    senhaNova: process.env.E2E_PROF_SENHA ?? 'Teste@1234',
  },
  aluno: {
    senha: process.env.E2E_ALUNO_SENHA ?? 'Teste@123',
  },
} as const;

export const URLS = {
  master: process.env.MASTER_URL ?? 'http://master.localhost',
  colag: process.env.COLAG_URL ?? 'http://colag.localhost',
} as const;
