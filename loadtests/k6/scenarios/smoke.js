import http from 'k6/http';
import { check, sleep } from 'k6';
import { config, headersComuns, opcoesCarga } from '../lib/config.js';
import { carregarAlunos, alunoDesteVu, loginAluno } from '../lib/auth.js';

const alunos = carregarAlunos();

export const options = opcoesCarga({
  vus: Math.min(Number(__ENV.VUS || 3), alunos.length) || 1,
  duration: __ENV.DURATION || '30s',
});

export default function () {
  const cfg = config();
  const aluno = alunoDesteVu(alunos);
  const sessao = loginAluno(aluno.login, aluno.senha);
  if (!sessao.ok) {
    return;
  }

  const dash = http.get(cfg.baseUrl + '/dashboard', { headers: headersComuns(), redirects: 4 });
  check(dash, { 'smoke: dashboard': (r) => r.status === 200 });
  sleep(1);
}
