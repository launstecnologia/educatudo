import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { config, headersComuns, nicknameCarga } from '../lib/config.js';
import { loginAdmin } from '../lib/auth.js';
import { extrairCsrf, formUrlEncoded } from '../lib/html.js';
import { payloadCadastroCompleto } from '../lib/alunoForm.js';
import { handleSummary } from '../lib/relatorio.js';

export { handleSummary };

const cadastrosOk = new Counter('cadastros_ok');
const cadastrosErro = new Counter('cadastros_erro');

const cfgInit = config();

export const options = {
  scenarios: {
    criar: {
      executor: 'shared-iterations',
      vus: Math.min(cfgInit.vus, 20),
      iterations: cfgInit.totalAlunos,
      maxDuration: cfgInit.duration === '2m' ? '40m' : cfgInit.duration,
    },
  },
  thresholds: {
    cadastros_ok: [`count>=${Math.floor(cfgInit.totalAlunos * 0.9)}`],
    http_req_failed: ['rate<0.2'],
  },
};

export function setup() {
  const sessao = loginAdmin();
  if (!sessao.ok) {
    throw new Error('Admin não autenticou. Confira ADMIN_LOGIN e ADMIN_SENHA no .env');
  }
  return { adminOk: true };
}

function jaExiste(corpo) {
  const t = String(corpo || '');
  return t.indexOf('já cadastrado') !== -1
    || t.indexOf('já está em uso') !== -1
    || t.indexOf('já existe') !== -1;
}

export default function (data) {
  if (!data.adminOk) {
    return;
  }

  const cfg = config();
  if (__ITER === 0) {
    const sessao = loginAdmin();
    if (!sessao.ok) {
      cadastrosErro.add(1);
      return;
    }
  }

  const lista = http.get(cfg.baseUrl + '/admin/students', {
    headers: headersComuns(),
    redirects: 4,
  });
  check(lista, { 'cadastro: abriu lista de alunos': (r) => r.status === 200 });

  const tela = http.get(cfg.baseUrl + '/admin/students/create', {
    headers: headersComuns(),
    redirects: 4,
  });
  const csrf = extrairCsrf(tela.body);
  if (!check(tela, { 'cadastro: abriu Cadastrar Novo Aluno': (r) => r.status === 200 && csrf.length === 64 })) {
    cadastrosErro.add(1);
    return;
  }

  const indice = exec.scenario.iterationInTest + 1;
  const nickname = nicknameCarga(indice);
  const headersJson = headersComuns({
    'Content-Type': 'application/x-www-form-urlencoded',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  });

  const payload = payloadCadastroCompleto(indice, nickname, cfg.alunoSenhaPadrao, csrf, tela.body);

  const criar = http.post(
    cfg.baseUrl + '/admin/students',
    formUrlEncoded(payload),
    { headers: headersJson }
  );

  let json = {};
  try {
    json = criar.json();
  } catch (e) {
    json = {};
  }

  const criado = criar.status === 200 && json && json.success && json.id;
  const existe = jaExiste(criar.body);
  if (!criado && !existe) {
    cadastrosErro.add(1);
    if (indice <= 5 || indice % 500 === 0) {
      console.error(`criar ${nickname} falhou status=${criar.status} body=${String(criar.body).slice(0, 180)}`);
    }
    return;
  }

  const alunoId = criado ? json.id : 0;
  if (alunoId) {
    const liberar = http.post(
      cfg.baseUrl + '/admin/students/' + alunoId,
      formUrlEncoded(Object.assign({}, payload, {
        _method: 'PUT',
        primeiro_acesso: '0',
      })),
      { headers: headersJson }
    );
    check(liberar, { 'cadastro: liberou acesso': (r) => r.status === 200 });
  }

  cadastrosOk.add(1);
  if (indice % 200 === 0) {
    console.log(`criados até ${nickname} (${indice}/${cfg.totalAlunos})`);
  }
  sleep(0.05);
}
