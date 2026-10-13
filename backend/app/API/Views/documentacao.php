<?php
/** @var string $openapiUrl */
$openapiUrl = $openapiUrl ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API do responsável — EducaTudo</title>
    <style>
        :root {
            --azul: #075CE5;
            --azul-escuro: #0B3F8C;
            --fundo: #F4F7FB;
            --card: #FFFFFF;
            --texto: #1E293B;
            --suave: #64748B;
            --linha: #E2E8F0;
            --ok: #047857;
            --get: #0F766E;
            --post: #075CE5;
            --put: #B45309;
            --del: #B91C1C;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; color: var(--texto); background: var(--fundo); }
        .app { display: flex; min-height: 100vh; }
        .menu {
            width: 300px; flex: 0 0 300px; background: #0F2744; color: #E2E8F0;
            display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh;
        }
        .marca { padding: 22px 20px 12px; }
        .marca strong { display: block; color: #fff; font-size: 18px; }
        .marca span { color: #93C5FD; font-size: 13px; }
        .token { margin: 8px 16px 12px; background: #17365F; border-radius: 12px; padding: 12px; }
        .token label { display: block; font-size: 12px; color: #BFDBFE; margin-bottom: 6px; }
        .token input {
            width: 100%; border: 0; border-radius: 8px; padding: 8px 10px; font-size: 13px;
            background: #fff; color: var(--texto);
        }
        .token .acoes { display: flex; gap: 8px; margin-top: 8px; }
        .token button, .buscar {
            border: 0; border-radius: 8px; padding: 7px 10px; font-size: 12px; cursor: pointer;
        }
        .btn-azul { background: var(--azul); color: #fff; }
        .btn-claro { background: transparent; color: #BFDBFE; border: 1px solid #3B5B86 !important; }
        .estado { margin-top: 8px; font-size: 12px; color: #86EFAC; }
        .estado.vazio { color: #FDE68A; }
        .busca { margin: 0 16px 10px; }
        .busca input {
            width: 100%; border: 0; border-radius: 8px; padding: 8px 10px; background: #17365F; color: #fff;
        }
        .lista { overflow: auto; padding: 4px 10px 20px; flex: 1; }
        .grupo { margin-top: 14px; }
        .grupo h2 { margin: 0 8px 6px; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #93C5FD; }
        .item {
            display: flex; gap: 8px; align-items: center; width: 100%; text-align: left;
            background: transparent; color: #E2E8F0; border: 0; border-radius: 8px;
            padding: 8px; cursor: pointer; font-size: 13px;
        }
        .item:hover, .item.ativo { background: #1D4F86; }
        .metodo { font-size: 10px; font-weight: 700; width: 42px; text-align: center; border-radius: 4px; padding: 2px 0; color: #fff; }
        .GET { background: var(--get); } .POST { background: var(--post); } .PUT { background: var(--put); } .DELETE { background: var(--del); }
        .conteudo { flex: 1; padding: 28px 32px 48px; max-width: 920px; }
        .topo-movel { display: none; }
        .cartao { background: var(--card); border: 1px solid var(--linha); border-radius: 16px; padding: 22px; margin-bottom: 16px; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .subtitulo { color: var(--suave); margin: 0 0 18px; line-height: 1.5; }
        .passos { margin: 0; padding-left: 20px; }
        .passos li { margin: 8px 0; line-height: 1.45; }
        .atalhos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .atalho {
            text-align: left; background: #fff; border: 1px solid var(--linha); border-radius: 14px;
            padding: 14px; cursor: pointer;
        }
        .atalho strong { display: block; margin-bottom: 4px; }
        .atalho span { color: var(--suave); font-size: 13px; }
        .faixa { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 8px; }
        .caminho { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #EFF6FF; color: var(--azul-escuro); padding: 6px 10px; border-radius: 8px; }
        label.campo { display: block; font-size: 13px; margin: 12px 0 4px; }
        input.campo, textarea.campo {
            width: 100%; border: 1px solid var(--linha); border-radius: 10px; padding: 10px; font-size: 14px;
        }
        textarea.campo { min-height: 110px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .enviar { margin-top: 14px; background: var(--azul); color: #fff; border: 0; border-radius: 10px; padding: 10px 16px; cursor: pointer; }
        pre.resposta { background: #0F172A; color: #E2E8F0; border-radius: 12px; padding: 14px; overflow: auto; white-space: pre-wrap; }
        .aviso { background: #FEF3C7; color: #92400E; border-radius: 10px; padding: 10px 12px; font-size: 13px; }
        @media (max-width: 860px) {
            .menu { position: fixed; z-index: 20; transform: translateX(-105%); transition: transform .2s ease; }
            .menu.aberto { transform: none; }
            .conteudo { padding: 72px 16px 32px; }
            .topo-movel { display: block; position: fixed; top: 0; left: 0; right: 0; background: #0F2744; color: #fff; padding: 12px 16px; z-index: 10; }
            .atalhos { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app">
    <button class="topo-movel" id="abrir-menu" type="button">Menu da API</button>
    <aside class="menu" id="menu">
        <div class="marca">
            <strong>EducaTudo</strong>
            <span>API do responsável</span>
        </div>
        <div class="token">
            <label for="token">Token da sessão</label>
            <input id="token" type="password" placeholder="Cole o access_token" autocomplete="off">
            <div class="acoes">
                <button class="btn-azul" id="guardar-token" type="button">Guardar</button>
                <button class="btn-claro" id="limpar-token" type="button">Limpar</button>
            </div>
            <div class="estado vazio" id="estado-token">Nenhuma sessão guardada.</div>
        </div>
        <div class="busca"><input id="busca" type="search" placeholder="Buscar rota"></div>
        <nav class="lista" id="lista"></nav>
    </aside>
    <main class="conteudo" id="conteudo"></main>
</div>
<script>
const OPENAPI_URL = <?= json_encode($openapiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const CHAVE = 'educatudo.api.token';
let spec = null;
let operacoes = [];
let atual = null;

function el(tag, attrs, texto) {
    const node = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => {
        if (k === 'class') node.className = v;
        else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
        else node.setAttribute(k, v);
    });
    if (texto !== undefined) node.textContent = texto;
    return node;
}

function tokenAtual() {
    return sessionStorage.getItem(CHAVE) || '';
}

function pintarToken() {
    const campo = document.getElementById('token');
    const estado = document.getElementById('estado-token');
    const valor = tokenAtual();
    campo.value = valor;
    estado.textContent = valor ? 'Sessão guardada neste navegador.' : 'Nenhuma sessão guardada.';
    estado.className = valor ? 'estado' : 'estado vazio';
}

function linhasDe(texto) {
    return String(texto || '').split('\n').map((l) => l.trim()).filter(Boolean);
}

function blocoTexto(texto) {
    const caixa = el('div');
    let lista = null;
    linhasDe(texto).forEach((linha) => {
        if (linha.toLowerCase() === 'passo a passo') return;
        const passo = linha.match(/^\d+\.\s+(.*)$/);
        if (passo) {
            if (!lista) {
                lista = el('ol', { class: 'passos' });
                caixa.appendChild(lista);
            }
            lista.appendChild(el('li', {}, passo[1]));
            return;
        }
        lista = null;
        caixa.appendChild(el('p', { class: 'subtitulo' }, linha));
    });
    return caixa;
}

function indice(filtro) {
    const nav = document.getElementById('lista');
    nav.textContent = '';
    const inicio = el('button', { class: 'item' + (atual ? '' : ' ativo'), onclick: () => abrir(null) }, 'Boas-vindas');
    nav.appendChild(inicio);
    const termo = (filtro || '').toLowerCase();
    (spec.tags || []).forEach((tag) => {
        const itens = operacoes.filter((op) => op.tag === tag.name && (
            !termo || (op.summary + ' ' + op.path).toLowerCase().includes(termo)
        ));
        if (!itens.length) return;
        const grupo = el('section', { class: 'grupo' });
        grupo.appendChild(el('h2', {}, tag.name.replace(/^\d+\.\s*/, '')));
        itens.forEach((op) => {
            const botao = el('button', {
                class: 'item' + (atual && atual.id === op.id ? ' ativo' : ''),
                onclick: () => abrir(op.id)
            });
            botao.appendChild(el('span', { class: 'metodo ' + op.method }, op.method));
            botao.appendChild(el('span', {}, op.summary));
            grupo.appendChild(botao);
        });
        nav.appendChild(grupo);
    });
}

function boasVindas() {
    const main = document.getElementById('conteudo');
    main.textContent = '';
    const cartao = el('section', { class: 'cartao' });
    cartao.appendChild(el('h1', {}, 'Bem-vindo'));
    cartao.appendChild(blocoTexto(spec.info && spec.info.description));
    main.appendChild(cartao);

    const atalhos = el('div', { class: 'atalhos' });
    [
        ['Entrar', 'CPF e senha de um responsável.', 'post /api/autenticacao/entrar'],
        ['Ver os filhos', 'A lista traz o id de cada aluno.', 'get /api/alunos'],
        ['Abrir o painel', 'Resumo do aluno escolhido.', 'get /api/alunos/{id}/painel']
    ].forEach(([titulo, texto, id]) => {
        const card = el('button', { class: 'atalho', onclick: () => abrir(id) });
        card.appendChild(el('strong', {}, titulo));
        card.appendChild(el('span', {}, texto));
        atalhos.appendChild(card);
    });
    main.appendChild(atalhos);
}

function campoDe(nome, descricao, exemplo) {
    const wrap = el('div');
    wrap.appendChild(el('label', { class: 'campo', for: 'p-' + nome }, nome));
    if (descricao) wrap.appendChild(el('div', { class: 'subtitulo' }, descricao));
    const input = el('input', { class: 'campo', id: 'p-' + nome, name: nome });
    if (exemplo !== undefined && exemplo !== null && nome !== 'id') input.value = String(exemplo);
    wrap.appendChild(input);
    return wrap;
}

function detalhe(op) {
    const main = document.getElementById('conteudo');
    main.textContent = '';
    const cartao = el('article', { class: 'cartao' });
    const faixa = el('div', { class: 'faixa' });
    faixa.appendChild(el('span', { class: 'metodo ' + op.method }, op.method));
    faixa.appendChild(el('code', { class: 'caminho' }, op.path));
    cartao.appendChild(faixa);
    cartao.appendChild(el('h1', {}, op.summary));
    cartao.appendChild(blocoTexto(op.description));
    if (op.auth && !tokenAtual()) {
        cartao.appendChild(el('p', { class: 'aviso' }, 'Esta rota pede o token. Entre primeiro e guarde o access_token no menu lateral.'));
    }
    main.appendChild(cartao);

    const form = el('form', { class: 'cartao' });
    form.appendChild(el('h2', {}, 'Experimentar'));
    (op.parameters || []).forEach((p) => form.appendChild(campoDe(p.name, p.description || '', p.example)));
    if (op.body) {
        form.appendChild(el('label', { class: 'campo', for: 'corpo' }, 'Corpo JSON'));
        const area = el('textarea', { class: 'campo', id: 'corpo' });
        area.value = JSON.stringify(exemploCorpo(op.body), null, 2);
        form.appendChild(area);
    }
    const enviar = el('button', { class: 'enviar', type: 'submit' }, 'Enviar para a escola');
    form.appendChild(enviar);
    const resposta = el('pre', { class: 'resposta' }, 'A resposta aparece aqui.');
    form.appendChild(resposta);
    form.addEventListener('submit', (evento) => executar(evento, op, resposta));
    main.appendChild(form);
}

function exemploCorpo(body) {
    const schema = (((body.content || {})['application/json'] || {}).schema) || {};
    const props = schema.properties || {};
    const saida = {};
    Object.keys(props).forEach((nome) => { saida[nome] = ''; });
    return saida;
}

function abrir(id) {
    atual = operacoes.find((op) => op.id === id) || null;
    document.getElementById('menu').classList.remove('aberto');
    if (id) location.hash = encodeURIComponent(id);
    else history.replaceState(null, '', location.pathname);
    indice(document.getElementById('busca').value);
    if (!atual) boasVindas();
    else detalhe(atual);
}

async function executar(evento, op, destino) {
    evento.preventDefault();
    const dados = new FormData(evento.target);
    let caminho = op.path;
    const query = new URLSearchParams();
    (op.parameters || []).forEach((p) => {
        const valor = String(dados.get(p.name) || '').trim();
        if (p.in === 'path') caminho = caminho.replace('{' + p.name + '}', encodeURIComponent(valor));
        else if (valor) query.set(p.name, valor);
    });
    const base = (spec.servers && spec.servers[0] && spec.servers[0].url) || '';
    let url = base + caminho;
    const qs = query.toString();
    if (qs) url += '?' + qs;
    const headers = { 'Accept': 'application/json' };
    if (op.auth && tokenAtual()) headers.Authorization = 'Bearer ' + tokenAtual();
    const init = { method: op.method, headers };
    if (op.body) {
        headers['Content-Type'] = 'application/json';
        init.body = document.getElementById('corpo').value || '{}';
    }
    destino.textContent = 'Enviando…';
    try {
        const resp = await fetch(url, init);
        const texto = await resp.text();
        let bonito = texto;
        try { bonito = JSON.stringify(JSON.parse(texto), null, 2); } catch (e) {}
        destino.textContent = resp.status + ' ' + resp.statusText + '\n\n' + bonito;
        if (op.id === 'post /api/autenticacao/entrar' && resp.ok) {
            const json = JSON.parse(texto);
            const token = json && json.data && json.data.access_token;
            if (token) {
                sessionStorage.setItem(CHAVE, token);
                pintarToken();
                destino.textContent += '\n\nToken guardado. Você já pode abrir os filhos.';
            }
        }
    } catch (erro) {
        destino.textContent = 'Não foi possível chamar a API a partir desta página.';
    }
}

function montar(json) {
    spec = json;
    operacoes = [];
    Object.entries(json.paths || {}).forEach(([path, metodos]) => {
        Object.entries(metodos).forEach(([method, op]) => {
            operacoes.push({
                id: method + ' ' + path,
                method: method.toUpperCase(),
                path,
                summary: op.summary || path,
                description: op.description || '',
                tag: (op.tags && op.tags[0]) || 'Outros',
                parameters: op.parameters || [],
                body: op.requestBody || null,
                auth: Array.isArray(op.security) && op.security.length > 0
            });
        });
    });
    pintarToken();
    const hash = decodeURIComponent(location.hash.replace(/^#/, ''));
    abrir(operacoes.some((op) => op.id === hash) ? hash : null);
}

document.getElementById('guardar-token').onclick = () => {
    sessionStorage.setItem(CHAVE, document.getElementById('token').value.trim());
    pintarToken();
};
document.getElementById('limpar-token').onclick = () => {
    sessionStorage.removeItem(CHAVE);
    pintarToken();
};
document.getElementById('busca').addEventListener('input', (e) => indice(e.target.value));
document.getElementById('abrir-menu').onclick = () => document.getElementById('menu').classList.toggle('aberto');

fetch(OPENAPI_URL).then((r) => r.json()).then(montar).catch(() => {
    document.getElementById('conteudo').textContent = 'Não foi possível carregar a documentação.';
});
</script>
</body>
</html>
