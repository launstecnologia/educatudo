import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { config, headersComuns, opcoesUmaVezPorVu } from '../lib/config.js';
import { carregarAlunos, alunoDesteVu, loginAluno } from '../lib/auth.js';
import { extrairIds } from '../lib/html.js';

const provasFinalizadas = new Counter('provas_finalizadas');
const provasErro = new Counter('provas_erro');
const alunos = carregarAlunos();

export const options = opcoesUmaVezPorVu({
  scenarios: {
    default: {
      executor: 'per-vu-iterations',
      vus: Math.min(Number(__ENV.VUS || 10), alunos.length) || 1,
      iterations: 1,
      maxDuration: __ENV.DURATION || '2m',
    },
  },
});

function descobrirProvaId(html, preferida) {
  if (preferida) {
    return preferida;
  }
  const ids = extrairIds(html, /\/aluno\/provas\/realizar\/(\d+)/g);
  return ids[0] || 0;
}

function paresQuestaoAlternativa(html) {
  const pares = [];
  const vistos = {};
  const re = /data-questao-id="(\d+)"[^>]*data-alternativa-id="(\d+)"/g;
  let m;
  while ((m = re.exec(String(html))) !== null) {
    const qid = Number(m[1]);
    if (!vistos[qid]) {
      vistos[qid] = true;
      pares.push({ questaoId: qid, alternativaId: Number(m[2]) });
    }
  }
  return pares;
}

export default function () {
  const cfg = config();
  const aluno = alunoDesteVu(alunos);
  const sessao = loginAluno(aluno.login, aluno.senha);
  if (!sessao.ok) {
    provasErro.add(1);
    return;
  }

  const lista = http.get(cfg.baseUrl + '/aluno/provas', {
    headers: headersComuns(),
    redirects: 4,
  });
  const provaId = descobrirProvaId(lista.body, cfg.provaId);
  if (!check(lista, { 'prova: achou prova': () => provaId > 0 })) {
    provasErro.add(1);
    return;
  }

  http.post(cfg.baseUrl + '/aluno/provas/iniciar/' + provaId, null, {
    headers: headersComuns({ Accept: 'application/json' }),
  });

  const pagina = http.get(cfg.baseUrl + '/aluno/provas/realizar/' + provaId, {
    headers: headersComuns(),
    redirects: 4,
  });
  const pares = paresQuestaoAlternativa(pagina.body);
  if (!check(pagina, { 'prova: carregou questões': (r) => r.status === 200 && pares.length > 0 })) {
    provasErro.add(1);
    return;
  }

  for (let i = 0; i < pares.length; i++) {
    const par = pares[i];
    const resp = http.post(
      cfg.baseUrl + '/aluno/provas/salvar-resposta/' + provaId,
      JSON.stringify({
        questao_id: par.questaoId,
        alternativa_id: par.alternativaId,
        resposta_texto: null,
        prova_id_original: null,
      }),
      {
        headers: headersComuns({
          'Content-Type': 'application/json',
          Accept: 'application/json',
        }),
      }
    );
    check(resp, { 'prova: salvou resposta': (r) => r.status === 200 });
    sleep(0.2);
  }

  const fim = http.post(cfg.baseUrl + '/aluno/provas/finalizar/' + provaId, '{}', {
    headers: headersComuns({
      'Content-Type': 'application/json',
      Accept: 'application/json',
    }),
  });

  let json = {};
  try {
    json = fim.json();
  } catch (e) {
    json = {};
  }

  const ok = fim.status === 200 && json && (json.success || json.redirect || !json.error);
  check(fim, { 'prova: finalizou': () => ok });
  if (ok) {
    provasFinalizadas.add(1);
  } else {
    provasErro.add(1);
  }

  sleep(1);
}
