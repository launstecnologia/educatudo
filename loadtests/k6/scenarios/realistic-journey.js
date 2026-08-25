// EducaTudo — cenário de carga "usuário real navegando" (não é flood de GET).
//
// Fluxo por iteração de VU:
//   dashboard -> pausa -> 2 a 3 páginas escolhidas ao acaso (com pausa entre elas,
//   simulando o tempo de leitura de uma pessoa) -> pausa -> repete.
//
// Login: feito uma única vez por "slot" do pool, em setup() (fora da rampa de
// VUs), pra não bater no rate limit de login (ver README.md). Cada VU reaproveita
// uma das sessões do pool (round-robin por __VU).
//
// Rodar (depois de instalar k6 — ver README.md):
//   k6 run -e BASE_URL=http://colag.localhost -e LOGIN=aluno.teste -e PASSWORD='Teste@123' realistic-journey.js
//
// Ajustar o teto de VUs sem editar o arquivo:
//   k6 run -e MAX_VUS=50 realistic-journey.js      # roda só até 50 VUs (pula 100/200)
//   k6 run -e STAGE_HOLD=10 realistic-journey.js   # patamares mais curtos (smoke test rápido)

import http from 'k6/http';
import { check, sleep } from 'k6';
import { loginOnce, jarWithSession } from '../lib/login.js';

const BASE_URL = (__ENV.BASE_URL || 'http://colag.localhost').replace(/\/$/, '');
const LOGIN = __ENV.LOGIN || 'aluno.teste';
const PASSWORD = __ENV.PASSWORD || 'Teste@123';
const TIPO = __ENV.TIPO || 'aluno';
const POOL_SIZE = parseInt(__ENV.POOL_SIZE || '5', 10);
const MAX_VUS = parseInt(__ENV.MAX_VUS || '200', 10);
const STAGE_RAMP = __ENV.STAGE_RAMP || '15s';
const STAGE_HOLD = __ENV.STAGE_HOLD || '30s';

// Páginas de navegação "de leitura" do portal do aluno (todas GET, sem CSRF).
const BROWSE_PAGES = [
    '/dashboard',
    '/caderno',
    '/redacoes',
    '/redacoes/historico',
    '/flashcards',
    '/forum',
];

function buildStages() {
    const tiers = [1, 5, 20, 50, 100, 200].filter((v) => v <= MAX_VUS);
    const stages = [];
    for (const target of tiers) {
        stages.push({ duration: STAGE_RAMP, target });
        stages.push({ duration: STAGE_HOLD, target });
    }
    // Descida suave no final em vez de cortar todo mundo de uma vez.
    stages.push({ duration: STAGE_RAMP, target: 0 });
    return stages;
}

export const options = {
    scenarios: {
        navegacao_realista: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: buildStages(),
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        // Não é teste de "quantos aguenta" — é diagnóstico. Falha o run só se algo
        // estiver flagrantemente quebrado (muitos erros ou extremamente lento).
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<5000'],
    },
};

export function setup() {
    console.log(`[setup] autenticando pool de ${POOL_SIZE} sessão(ões) em ${BASE_URL} ...`);
    const sessions = [];
    for (let i = 0; i < POOL_SIZE; i++) {
        const sid = loginOnce(http, BASE_URL, LOGIN, PASSWORD, TIPO);
        sessions.push(sid);
    }
    console.log(`[setup] pool pronto (${sessions.length} sessão(ões) autenticada(s)).`);
    return { sessions };
}

export default function (data) {
    const sessions = data.sessions;
    const sid = sessions[__VU % sessions.length];
    const jar = jarWithSession(http, BASE_URL, sid);

    // 1) Dashboard — página inicial de qualquer sessão real.
    let res = http.get(`${BASE_URL}/dashboard`, { jar, tags: { name: 'dashboard' } });
    check(res, { 'dashboard OK': (r) => r.status === 200 });
    sleep(rand(1, 3));

    // 2) 2 a 3 páginas aleatórias, com "tempo de leitura" entre elas.
    const hits = 2 + Math.floor(Math.random() * 2); // 2 ou 3
    for (let i = 0; i < hits; i++) {
        const page = BROWSE_PAGES[Math.floor(Math.random() * BROWSE_PAGES.length)];
        res = http.get(`${BASE_URL}${page}`, { jar, tags: { name: page } });
        check(res, { [`${page} sem erro 5xx`]: (r) => r.status < 500 });
        sleep(rand(1, 4));
    }

    // Pausa entre "sessões" de uso do mesmo VU (evita martelar sem parar).
    sleep(rand(2, 5));
}

function rand(min, max) {
    return Math.random() * (max - min) + min;
}
