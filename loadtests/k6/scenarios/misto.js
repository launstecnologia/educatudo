import { config } from '../lib/config.js';
import { carregarAlunos } from '../lib/auth.js';
import { default as cenarioCadastro } from './cadastro.js';
import { default as cenarioProva } from './prova.js';
import { default as cenarioJornada } from './jornada.js';

const alunos = carregarAlunos();
const cfg = config();
const vusAluno = Math.max(1, Math.min(cfg.vus, alunos.length));

export const options = {
  scenarios: {
    cadastros: {
      executor: 'constant-vus',
      exec: 'cadastro',
      vus: Math.max(1, Math.floor(cfg.vus / 5) || 1),
      duration: cfg.duration,
    },
    provas: {
      executor: 'per-vu-iterations',
      exec: 'prova',
      vus: vusAluno,
      iterations: 1,
      maxDuration: cfg.duration,
    },
    jornadas: {
      executor: 'per-vu-iterations',
      exec: 'jornada',
      vus: vusAluno,
      iterations: 1,
      maxDuration: cfg.duration,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.15'],
    http_req_duration: ['p(95)<10000'],
  },
};

export function cadastro() {
  cenarioCadastro({ adminOk: true });
}

export function prova() {
  cenarioProva();
}

export function jornada() {
  cenarioJornada();
}
