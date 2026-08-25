import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { config, headersComuns, opcoesUmaVezPorVu } from '../lib/config.js';
import { carregarAlunos, alunoDesteVu, loginAluno } from '../lib/auth.js';
import { extrairIds, primeiroMatch, formUrlEncoded } from '../lib/html.js';

const jornadasOk = new Counter('jornadas_ok');
const jornadasErro = new Counter('jornadas_erro');
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

function descobrirJornadaId(html, preferida) {
  if (preferida) {
    return preferida;
  }
  const ids = extrairIds(html, /\/jornadas\/(\d+)(?:["'\s?]|$)/g);
  return ids[0] || 0;
}

function idsExercicios(html) {
  const bloco = primeiroMatch(html, /let exercicios = (\[[\s\S]*?\]);/);
  if (!bloco) {
    return extrairIds(html, /"id"\s*:\s*(\d+)/g);
  }
  return extrairIds(bloco, /"id"\s*:\s*(\d+)/g);
}

export default function () {
  const cfg = config();
  const aluno = alunoDesteVu(alunos);
  const sessao = loginAluno(aluno.login, aluno.senha);
  if (!sessao.ok) {
    jornadasErro.add(1);
    return;
  }

  const lista = http.get(cfg.baseUrl + '/jornadas', {
    headers: headersComuns(),
    redirects: 4,
  });
  const jornadaId = descobrirJornadaId(lista.body, cfg.jornadaId);
  if (!check(lista, { 'jornada: achou jornada': () => jornadaId > 0 })) {
    jornadasErro.add(1);
    return;
  }

  const show = http.get(cfg.baseUrl + '/jornadas/' + jornadaId, {
    headers: headersComuns(),
    redirects: 4,
  });
  const urlsExercicios = [];
  const reUrl = new RegExp('/jornadas/' + jornadaId + '/modulos/(\\d+)/exercicios', 'g');
  let m;
  while ((m = reUrl.exec(String(show.body))) !== null) {
    const url = '/jornadas/' + jornadaId + '/modulos/' + m[1] + '/exercicios';
    if (urlsExercicios.indexOf(url) === -1) {
      urlsExercicios.push(url);
    }
  }

  if (!check(show, { 'jornada: achou módulo': () => urlsExercicios.length > 0 })) {
    jornadasErro.add(1);
    return;
  }

  let respondeu = 0;
  for (let i = 0; i < urlsExercicios.length; i++) {
    const pagina = http.get(cfg.baseUrl + urlsExercicios[i], {
      headers: headersComuns(),
      redirects: 4,
    });
    const moduloId = Number(primeiroMatch(pagina.body, /let moduloId = (\d+)/) || 0);
    const exercicios = idsExercicios(pagina.body);
    if (!moduloId || exercicios.length === 0) {
      continue;
    }

    for (let j = 0; j < exercicios.length; j++) {
      const resp = http.post(
        cfg.baseUrl + '/jornadas/responder-exercicio-modulo',
        formUrlEncoded({
          exercicio_id: exercicios[j],
          modulo_id: moduloId,
          resposta: 'A',
        }),
        {
          headers: headersComuns({
            'Content-Type': 'application/x-www-form-urlencoded',
            Accept: 'application/json',
          }),
        }
      );
      check(resp, { 'jornada: respondeu exercício': (r) => r.status === 200 });
      respondeu += 1;
      sleep(0.2);
    }

    http.post(
      cfg.baseUrl + '/jornadas/finalizar-etapa',
      formUrlEncoded({
        modulo_id: moduloId,
        tipo: 'exercicios',
        tempo_gasto: 30,
      }),
      {
        headers: headersComuns({
          'Content-Type': 'application/x-www-form-urlencoded',
          Accept: 'application/json',
        }),
      }
    );
  }

  if (respondeu > 0) {
    jornadasOk.add(1);
  } else {
    jornadasErro.add(1);
  }

  sleep(1);
}
