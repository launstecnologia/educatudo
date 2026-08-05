const CACHE_VERSION = 'v6';
// '/' fica de fora: pode redirecionar internamente (login, tenant) e o Safari
// recusa servir do cache uma resposta com redirect em navegação.
const CORE_ASSETS = [
  '/professor',
  '/admin',
  '/pais',
];

function getProfileFromScope(scope) {
  if (scope.includes('/professor')) return 'professor';
  if (scope.includes('/admin')) return 'admin';
  if (scope.includes('/pais')) return 'pais';
  return 'aluno';
}

self.addEventListener('install', (event) => {
  const profile = getProfileFromScope(self.registration.scope || '');
  const cacheName = `educatudo-${profile}-${CACHE_VERSION}`;
  event.waitUntil(
    caches.open(cacheName).then((cache) => cache.addAll(CORE_ASSETS)).catch(() => null)
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      const allowList = [
        `educatudo-aluno-${CACHE_VERSION}`,
        `educatudo-professor-${CACHE_VERSION}`,
        `educatudo-admin-${CACHE_VERSION}`,
        `educatudo-pais-${CACHE_VERSION}`
      ];
      return Promise.all(keys.map((key) => {
        if (!allowList.includes(key)) {
          return caches.delete(key);
        }
        return null;
      }));
    })
  );
  self.clients.claim();
});

// Logout / troca de aluno: limpa caches de HTML/assets privados
self.addEventListener('message', (event) => {
  if (!event.data || event.data.type !== 'CLEAR_AUTH_CACHES') return;
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
  );
});

// Fallback de rede offline/falha: garante um Response real. '/' nunca está
// em cache (ver comentário acima) e outras rotas também podem não estar,
// então caches.match pode resolver pra undefined — nesse caso o
// event.respondWith() recebe undefined e o Safari trava com
// "FetchEvent.respondWith received an error: Returned response is null."
function offlineFallback(request) {
  return caches.match(request).then((cached) => cached || new Response(
    '<!doctype html><meta charset="utf-8"><title>Sem conexão</title>' +
    '<body style="font-family:sans-serif;text-align:center;padding:40px">' +
    '<h1>Sem conexão</h1><p>Não foi possível carregar esta página. Verifique sua internet e tente novamente.</p>' +
    '</body>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
  ));
}

// URLs da área logada: nunca servir do cache (sempre rede primeiro).
// Evita exibir dados de outro usuário e garante que listagens mostrem dados recém-cadastrados após reload.
function isPrivatePage(url) {
  const path = (url.pathname || '/').toLowerCase();
  // Qualquer página autenticada do aluno (sem prefixo /aluno em várias rotas)
  const alunoPaths = [
    '/dashboard', '/avatar', '/perfil', '/carteira', '/agenda', '/provas',
    '/jornada', '/jornadas', '/redacao', '/redacoes', '/exercicios', '/forum',
    '/chat', '/notificacoes', '/tickets', '/suporte', '/simulados', '/jogos',
    '/games', '/cursos', '/drive', '/arquivos', '/boletim', '/notas',
    '/flashcard', '/ingles', '/apostila', '/material', '/recuperacao',
    '/educahits', '/educashop', '/minha-carteira', '/presenca'
  ];
  if (alunoPaths.some((p) => path === p || path.startsWith(p + '/'))) {
    return true;
  }
  return path.startsWith('/aluno') || path.startsWith('/professor') ||
    path.startsWith('/admin') || path.startsWith('/pais') ||
    path.startsWith('/forum') || path.includes('/dashboard') ||
    path.includes('/jornada') || path.includes('/redacao') || path.includes('/exercicios') ||
    path.includes('/avatar') || path.includes('/carteira') || path.includes('/provas');
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Área logada: sempre buscar da rede (evita exibir dados do usuário anterior após troca de conta)
  if (isPrivatePage(url)) {
    event.respondWith(
      fetch(request).catch(() => offlineFallback(request))
    );
    return;
  }

  // Navegação (troca de página/reload): sempre rede direta, nunca cache.
  // Safari recusa servir do cache uma resposta que passou por redirect interno
  // ("Response served by service worker has redirections"), e páginas de
  // navegação são justamente onde redirects (login, troca de tenant) acontecem.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => offlineFallback(request)));
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request)
        .then((response) => {
          // Nunca cachear respostas redirecionadas nem marcadas como no-store
          // (evita o bug do Safari ao reservir do cache depois).
          const cacheControl = response.headers.get('cache-control') || '';
          if (response.redirected || cacheControl.toLowerCase().includes('no-store')) {
            return response;
          }
          const responseClone = response.clone();
          const profile = getProfileFromScope(self.registration.scope || '');
          const cacheName = `educatudo-${profile}-${CACHE_VERSION}`;
          caches.open(cacheName).then((cache) => cache.put(request, responseClone)).catch(() => null);
          return response;
        })
        .catch(() => offlineFallback(request));
    })
  );
});

// --- Notificações Push (OneSignal) - Tracking ---
function getBaseUrl() {
  const scope = self.registration.scope || self.location.origin + '/';
  return scope.replace(/\/$/, '');
}

self.addEventListener('push', (event) => {
  if (!event.data) return;
  let payload = {};
  try {
    payload = event.data.json();
  } catch (_) {
    payload = { title: 'EducaTUDO', body: event.data.text() || 'Nova notificação' };
  }
  const title = payload.headings?.en || payload.title || 'EducaTUDO';
  const body = payload.contents?.en || payload.body || payload.message || '';
  const data = payload.data || {};
  const options = {
    body: body,
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    data: { url: payload.url || data.url || '', tracking_token: data.tracking_token || '' }
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationdisplay', (event) => {
  const token = event.notification?.data?.tracking_token;
  if (!token) return;
  const base = getBaseUrl();
  event.waitUntil(
    fetch(base + '/api/notificacoes/visualizado', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: token })
    }).catch(() => null)
  );
});

function resolveOpenUrl(url) {
  if (!url || url.trim() === '') return getBaseUrl() + '/';
  if (url.startsWith('http')) return url;
  const base = getBaseUrl();
  return url.startsWith('/') ? base + url : base + '/' + url;
}

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const token = event.notification?.data?.tracking_token;
  const fallbackUrl = event.notification?.data?.url || '/';
  if (!token) {
    event.waitUntil(self.clients.openWindow(resolveOpenUrl(fallbackUrl)));
    return;
  }
  event.waitUntil(
    fetch(getBaseUrl() + '/api/notificacoes/clicado', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: token })
    })
      .then((res) => res.json())
      .then((data) => {
        const url = data.url && data.url.trim() !== '' ? data.url : fallbackUrl;
        const openUrl = resolveOpenUrl(url);
        return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
          for (const c of clientList) {
            if (c.url && 'focus' in c) {
              c.navigate(openUrl);
              return c.focus();
            }
          }
          if (self.clients.openWindow) return self.clients.openWindow(openUrl);
        });
      })
      .catch(() => self.clients.openWindow(resolveOpenUrl(fallbackUrl)))
  );
});
