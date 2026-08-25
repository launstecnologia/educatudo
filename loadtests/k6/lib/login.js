// Login helper reutilizado pelos cenários — replica o fluxo real do EducaTudo:
//   1) GET /login/csrf-token (cookie de sessão + token double-submit)
//   2) POST /login com tipo/login/senha/_token
//
// IMPORTANTE (rate limit): o EducaTudo bloqueia por IP após MAX_LOGIN_ATTEMPTS_IP
// tentativas (padrão: 50) dentro de LOCKOUT_DURATION segundos (padrão: 900 = 15min).
// Como o k6 roda de UMA máquina (um IP só), NÃO faça login em todo VU/iteração —
// autentique uma vez por "slot" do pool em setup() (que roda uma única vez, fora
// da rampa de VUs) e reaproveite a sessão. Ver README.md para os detalhes.

export function loginOnce(http, baseUrl, loginField, senha, tipo = 'aluno') {
    const jar = new http.CookieJar();

    const loginPage = http.get(`${baseUrl}/login`, { jar, tags: { name: 'login_page' } });
    if (loginPage.status !== 200) {
        throw new Error(`GET /login falhou: HTTP ${loginPage.status}`);
    }

    const tokenResp = http.get(`${baseUrl}/login/csrf-token`, { jar, tags: { name: 'csrf_token' } });
    if (tokenResp.status !== 200) {
        throw new Error(`GET /login/csrf-token falhou: HTTP ${tokenResp.status}`);
    }
    const token = JSON.parse(tokenResp.body).csrf_token;

    const loginResp = http.post(
        `${baseUrl}/login`,
        { tipo, login: loginField, senha, _token: token },
        { jar, tags: { name: 'login_post' }, redirects: 0 }
    );

    const ok = loginResp.status === 302 && /\/dashboard/.test(loginResp.headers['Location'] || '');
    if (!ok) {
        throw new Error(
            `Login falhou pra "${loginField}": HTTP ${loginResp.status} Location=${loginResp.headers['Location'] || ''}. ` +
            `Se for 302 pra "/?t=..." em vez de "/dashboard", confira usuário/senha. ` +
            `Se vier vazio/erro de conexão, confira BASE_URL e rate limit de login.`
        );
    }

    // Extrai o PHPSESSID já autenticado do jar, pra poder "plantar" nos VUs depois.
    const cookies = jar.cookiesForURL(`${baseUrl}/`);
    const sessionId = cookies.PHPSESSID ? cookies.PHPSESSID[0] : null;
    if (!sessionId) {
        throw new Error('Login OK mas não encontrei PHPSESSID no cookie jar.');
    }
    return sessionId;
}

/**
 * Planta um PHPSESSID já autenticado num jar novo, pra um VU/iteração reaproveitar
 * sem precisar logar de novo (evita bater no rate limit de login).
 */
export function jarWithSession(http, baseUrl, sessionId) {
    const jar = new http.CookieJar();
    // Sem o global `URL` (o runtime do k6/Goja não tem o do browser) — não precisa:
    // passando baseUrl como referência, o jar já casa o cookie com esse domínio/path
    // nas próximas requisições feitas com esse mesmo jar para esse mesmo baseUrl.
    jar.set(baseUrl, 'PHPSESSID', sessionId);
    return jar;
}
