import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { config, headersComuns } from '../lib/config.js';
import { alunoPorIndice, loginAluno } from '../lib/auth.js';
import { handleSummary } from '../lib/relatorio.js';

export { handleSummary };

const acessosOk = new Counter('acessos_ok');
const acessosErro = new Counter('acessos_erro');

const PAGINAS_ALUNO = [
  '/dashboard',
  '/aluno/provas',
  '/jornadas',
  '/notas',
  '/notas-boletins',
  '/redacoes',
  '/jogos',
  '/mural-recados',
  '/livros',
  '/caderno',
  '/avatar',
  '/simulados',
  '/desempenho/provas',
  '/desempenho/jornadas',
  '/forum',
  '/flashcards',
];

const cfgInit = config();
const concorrentes = cfgInit.vus;

export const options = {
  scenarios: {
    acessar: {
      executor: 'per-vu-iterations',
      vus: concorrentes,
      iterations: Math.ceil(cfgInit.totalAlunos / concorrentes),
      maxDuration: cfgInit.duration === '2m' ? '40m' : cfgInit.duration,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.2'],
    http_req_duration: ['p(95)<8000'],
  },
};

function embaralhar(lista, semente) {
  const copia = lista.slice();
  let s = semente;
  for (let i = copia.length - 1; i > 0; i--) {
    s = (s * 16807 + 7) % 2147483647;
    const j = s % (i + 1);
    const tmp = copia[i];
    copia[i] = copia[j];
    copia[j] = tmp;
  }
  return copia;
}

export default function () {
  const cfg = config();
  const indice = (exec.scenario.iterationInTest % cfg.totalAlunos) + 1;
  const aluno = alunoPorIndice(indice);
  const sessao = loginAluno(aluno.login, aluno.senha);
  if (!sessao.ok) {
    acessosErro.add(1);
    return;
  }

  const paginas = embaralhar(PAGINAS_ALUNO, __VU * 1000 + indice);
  let ok = true;

  for (let i = 0; i < paginas.length; i++) {
    const resp = http.get(cfg.baseUrl + paginas[i], {
      headers: headersComuns(),
      redirects: 4,
    });
    const passou = check(resp, {
      ['navegar: ' + paginas[i]]: (r) => r.status === 200,
    });
    if (!passou) {
      ok = false;
    }
    sleep(0.8 + ((__VU + i) % 5) * 0.2);
  }

  http.get(cfg.baseUrl + '/logout?portal=aluno', {
    headers: headersComuns(),
    redirects: 4,
  });

  if (ok) {
    acessosOk.add(1);
  } else {
    acessosErro.add(1);
  }
}
