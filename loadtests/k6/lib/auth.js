import http from 'k6/http';
import { check, fail } from 'k6';
import { config, headersComuns, nicknameCarga } from './config.js';
import { extrairCsrf, formUrlEncoded } from './html.js';

const PAGINAS_LOGIN = {
  aluno: '/',
  admin_escola: '/admin',
  professor: '/professor',
};

/**
 * Login web (sessão PHP + CSRF). Cada VU tem cookie jar próprio.
 * tipo: aluno | admin_escola | professor
 */
export function login(tipo, usuario, senha) {
  const cfg = config();
  const pagina = PAGINAS_LOGIN[tipo] || '/';
  const headers = headersComuns();

  const tela = http.get(cfg.baseUrl + pagina, { headers, redirects: 4 });
  const csrf = extrairCsrf(tela.body);

  if (!check(tela, { 'login: tela CSRF': () => csrf.length === 64 })) {
    return { ok: false, motivo: 'csrf_ausente', status: tela.status };
  }

  const corpo = formUrlEncoded({
    _token: csrf,
    tipo,
    login: usuario,
    senha,
    site_url: '',
  });

  const resp = http.post(cfg.baseUrl + '/login', corpo, {
    headers: headersComuns({
      'Content-Type': 'application/x-www-form-urlencoded',
    }),
    redirects: 4,
  });

  const html = String(resp.body || '');
  const forcouTrocaSenha = html.indexOf('alterar-senha-obrigatoria') !== -1
    || String(resp.url || '').indexOf('alterar-senha-obrigatoria') !== -1;
  const primeiroAcesso = html.indexOf('Primeiro acesso') !== -1
    || html.indexOf('primeiro acesso') !== -1;
  const falhou = resp.status >= 400
    || html.indexOf('Credenciais inválidas') !== -1
    || html.indexOf('Sessão expirada') !== -1
    || html.indexOf('Muitas tentativas') !== -1
    || primeiroAcesso;

  const ok = !falhou && !forcouTrocaSenha && resp.status < 400;

  check(resp, {
    'login: autenticou': () => ok,
  });

  if (forcouTrocaSenha) {
    console.error('Login de ' + usuario + ' caiu em troca obrigatória de senha (não use 123456).');
  }
  if (primeiroAcesso) {
    console.error('Login de ' + usuario + ' bloqueado: primeiro acesso pendente.');
  }

  return {
    ok,
    forcouTrocaSenha,
    status: resp.status,
    csrf: extrairCsrf(html) || csrf,
  };
}

export function loginAluno(usuario, senha) {
  return login('aluno', usuario, senha);
}

export function loginAdmin(usuario, senha) {
  const cfg = config();
  const u = usuario || cfg.adminLogin;
  const s = senha || cfg.adminSenha;
  if (!u || !s) {
    fail('Defina ADMIN_LOGIN e ADMIN_SENHA para o cenário de cadastro.');
  }
  return login('admin_escola', u, s);
}

export function carregarAlunos() {
  try {
    const bruto = open('../data/alunos.json');
    const lista = JSON.parse(bruto);
    if (Array.isArray(lista) && lista.length > 0) {
      return lista;
    }
  } catch (e) {
    // sem arquivo: usa carga00001... gerados
  }
  const cfg = config();
  const lista = [];
  const total = Math.min(cfg.totalAlunos, 5000);
  for (let i = 1; i <= total; i++) {
    lista.push({ login: nicknameCarga(i), senha: cfg.alunoSenhaPadrao });
  }
  return lista;
}

export function alunoDesteVu(alunos) {
  return alunos[(__VU - 1) % alunos.length];
}

export function alunoPorIndice(indice) {
  const cfg = config();
  return { login: nicknameCarga(indice), senha: cfg.alunoSenhaPadrao };
}
