/**
 * Lê configuração do ambiente (k6 -e VAR=... ou ./run.sh).
 */
export function config() {
  const baseUrl = String(__ENV.BASE_URL || '').replace(/\/+$/, '');
  if (!baseUrl) {
    throw new Error('Defina BASE_URL (ex.: https://escola.homolog.seudominio.com.br)');
  }

  return {
    baseUrl,
    tenantSlug: String(__ENV.TENANT_SLUG || '').trim(),
    adminLogin: String(__ENV.ADMIN_LOGIN || ''),
    adminSenha: String(__ENV.ADMIN_SENHA || ''),
    alunoSenhaPadrao: String(__ENV.ALUNO_SENHA_PADRAO || 'Carga@2026'),
    prefixoAluno: String(__ENV.PREFIXO_ALUNO || 'carga'),
    totalAlunos: Number(__ENV.TOTAL_ALUNOS || 5000) || 5000,
    provaId: Number(__ENV.PROVA_ID || 0) || 0,
    jornadaId: Number(__ENV.JORNADA_ID || 0) || 0,
    vus: Number(__ENV.VUS || 10) || 10,
    duration: String(__ENV.DURATION || '2m'),
  };
}

/** carga00001, carga00002... — login = nickname na tela do aluno. */
export function nicknameCarga(indice) {
  const cfg = config();
  const n = Number(indice);
  return cfg.prefixoAluno + String(n).padStart(5, '0');
}

export function headersComuns(extra) {
  const cfg = config();
  const headers = {
    'User-Agent': 'EducaTudo-k6/1.0',
    Accept: 'text/html,application/xhtml+xml',
    'Cache-Control': 'no-cache',
    Pragma: 'no-cache',
  };
  if (cfg.tenantSlug) {
    headers['X-Tenant'] = cfg.tenantSlug;
  }
  return Object.assign(headers, extra || {});
}

export function opcoesCarga(extra) {
  const cfg = config();
  return Object.assign(
    {
      vus: cfg.vus,
      duration: cfg.duration,
      thresholds: {
        http_req_failed: ['rate<0.1'],
        http_req_duration: ['p(95)<8000'],
      },
    },
    extra || {}
  );
}

/** Um ciclo por VU — certo para prova/jornada (cada aluno só faz uma vez). */
export function opcoesUmaVezPorVu(extra) {
  const cfg = config();
  return Object.assign(
    {
      scenarios: {
        default: {
          executor: 'per-vu-iterations',
          vus: cfg.vus,
          iterations: 1,
          maxDuration: cfg.duration,
        },
      },
      thresholds: {
        http_req_failed: ['rate<0.1'],
        http_req_duration: ['p(95)<8000'],
      },
    },
    extra || {}
  );
}
