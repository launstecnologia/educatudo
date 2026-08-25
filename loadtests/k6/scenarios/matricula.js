import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { config, headersComuns } from '../lib/config.js';
import { loginAdmin } from '../lib/auth.js';
import { extrairCsrf, formUrlEncoded } from '../lib/html.js';
import { idsDaTelaMatricula, payloadNovaMatricula, idDoProcesso } from '../lib/matriculaForm.js';
import { handleSummary } from '../lib/relatorio.js';

export { handleSummary };

const matriculasOk = new Counter('matriculas_ok');
const matriculasErro = new Counter('matriculas_erro');
const anexosOk = new Counter('anexos_ok');

const ANEXO_PDF = open('../data/anexo-carga.pdf', 'b');
const TIPOS_DOC = ['rg', 'cpf', 'comprovante_residencia'];

const cfgInit = config();

export const options = {
  scenarios: {
    criar: {
      executor: 'shared-iterations',
      vus: Math.min(cfgInit.vus, 80),
      iterations: cfgInit.totalAlunos,
      maxDuration: cfgInit.duration === '2m' ? '40m' : cfgInit.duration,
    },
  },
  thresholds: {
    matriculas_ok: [`count>=${Math.floor(cfgInit.totalAlunos * 0.85)}`],
    http_req_failed: ['rate<0.25'],
  },
};

export function setup() {
  const sessao = loginAdmin();
  if (!sessao.ok) {
    throw new Error('Admin não autenticou. Confira ADMIN_LOGIN e ADMIN_SENHA no .env');
  }
  return { adminOk: true };
}

function urlComCacheBust(base, path) {
  const sep = path.indexOf('?') === -1 ? '?' : '&';
  return base + path + sep + '_k6=' + Date.now() + '-' + __VU + '-' + __ITER;
}

function getAdmin(path) {
  const cfg = config();
  return http.get(urlComCacheBust(cfg.baseUrl, path), {
    headers: headersComuns(),
    redirects: 8,
  });
}

function ehTelaNovaMatricula(resp) {
  const html = String(resp.body || '');
  return resp.status === 200
    && extrairCsrf(html).length === 64
    && (html.indexOf('aluno_nome') !== -1 || html.indexOf('Nova Matrícula') !== -1);
}

function ehLogin(resp) {
  const html = String(resp.body || '');
  const url = String(resp.url || '');
  return url.indexOf('/login') !== -1
    || html.indexOf('name="senha"') !== -1 && html.indexOf('name="tipo"') !== -1;
}

function abrirNovaMatricula() {
  let tela = getAdmin('/admin/enrollment/create');
  if (ehTelaNovaMatricula(tela)) {
    return tela;
  }
  tela = getAdmin('/admin/matricula/create');
  if (ehTelaNovaMatricula(tela)) {
    return tela;
  }
  if (ehLogin(tela) || tela.status !== 200) {
    const sessao = loginAdmin();
    if (!sessao.ok) {
      return tela;
    }
    tela = getAdmin('/admin/enrollment/create');
    if (!ehTelaNovaMatricula(tela)) {
      tela = getAdmin('/admin/matricula/create');
    }
  }
  return tela;
}

function anexarDocumento(baseUrl, processoId, csrf, tipo) {
  const resp = http.post(
    baseUrl + '/admin/enrollment/' + processoId + '/documentos',
    {
      _token: csrf,
      tipo_documento: tipo,
      documento: http.file(ANEXO_PDF, tipo + '-carga.pdf', 'application/pdf'),
    },
    {
      headers: headersComuns(),
      redirects: 8,
    }
  );
  const ok = resp.status === 200 || resp.status === 302;
  check(resp, { ['matricula: anexou ' + tipo]: () => ok });
  if (ok) {
    anexosOk.add(1);
  }
  return ok;
}

export default function (data) {
  if (!data.adminOk) {
    return;
  }

  const cfg = config();
  const indice = exec.scenario.iterationInTest + 1;

  const sessao = loginAdmin();
  if (!sessao.ok) {
    matriculasErro.add(1);
    console.error(`matricula ${indice}: login falhou`);
    return;
  }

  const lista = getAdmin('/admin/enrollment');
  check(lista, { 'matricula: abriu lista': (r) => r.status === 200 || r.status === 304 });

  const tela = abrirNovaMatricula();
  const csrf = extrairCsrf(tela.body);
  const ids = idsDaTelaMatricula(tela.body);
  if (!check(tela, { 'matricula: abriu Nova Matrícula': () => ehTelaNovaMatricula(tela) })) {
    matriculasErro.add(1);
    console.error(`matricula ${indice}: create status=${tela.status} url=${tela.url} csrf=${csrf.length}`);
    return;
  }

  const payload = payloadNovaMatricula(indice, csrf, ids);
  const criar = http.post(cfg.baseUrl + '/admin/enrollment', formUrlEncoded(payload), {
    headers: headersComuns({
      'Content-Type': 'application/x-www-form-urlencoded',
    }),
    redirects: 8,
  });

  const processoId = idDoProcesso(criar);
  if (!check(criar, { 'matricula: processo criado': () => processoId > 0 })) {
    matriculasErro.add(1);
    console.error(`matricula ${indice}: POST status=${criar.status} url=${criar.url}`);
    return;
  }

  const ficha = getAdmin('/admin/enrollment/' + processoId);
  const csrfFicha = extrairCsrf(ficha.body) || csrf;

  let docs = 0;
  for (let i = 0; i < TIPOS_DOC.length; i++) {
    if (anexarDocumento(cfg.baseUrl, processoId, csrfFicha, TIPOS_DOC[i])) {
      docs += 1;
    }
    sleep(0.1);
  }

  if (docs > 0) {
    matriculasOk.add(1);
  } else {
    matriculasErro.add(1);
  }

  if (indice % 50 === 0) {
    console.log(`matrículas até #${processoId} (${indice}/${cfg.totalAlunos})`);
  }
  sleep(0.1);
}
