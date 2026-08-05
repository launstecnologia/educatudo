<?php
$educahits_master_dashboard = trim((string) ($educahits_master_dashboard ?? ''));
$educahits_receive_url = trim((string) ($educahits_receive_url ?? ''));
$educahits_delete_song_url = trim((string) ($educahits_delete_song_url ?? ''));
$educahits_portal_login = trim((string) ($educahits_portal_login ?? ''));
$educahits_portal_request = trim((string) ($educahits_portal_request ?? ''));
$educahits_requests_api = trim((string) ($educahits_requests_api ?? ''));
$educahits_songs_api = trim((string) ($educahits_songs_api ?? ''));

$endpoints = [
    [
        'titulo' => 'Portal EducaHits',
        'descricao' => 'Atalho opcional para o painel web do produto EducaHits.',
        'env' => 'EDUCAHITS_MASTER_DASHBOARD_URL',
        'valor' => $educahits_master_dashboard,
        'acao' => $educahits_master_dashboard !== '' ? [
            'label' => 'Abrir portal',
            'href' => $educahits_master_dashboard,
        ] : null,
    ],
    [
        'titulo' => 'API de entrega',
        'descricao' => 'Endpoint usado no cadastro/entrega de músicas (receive-song).',
        'env' => 'EDUCAHITS_RECEIVE_SONG_URL',
        'valor' => $educahits_receive_url,
        'acao' => null,
    ],
    [
        'titulo' => 'Login do portal (aluno/admin)',
        'descricao' => 'URL de acesso ao EducaHits com token de entrada.',
        'env' => 'EDUCAHITS_PORTAL_LOGIN_URL',
        'valor' => $educahits_portal_login,
        'acao' => $educahits_portal_login !== '' ? [
            'label' => 'Abrir login',
            'href' => $educahits_portal_login,
        ] : null,
    ],
    [
        'titulo' => 'Solicitar música (portal)',
        'descricao' => 'URL do fluxo de pedido de música no portal externo.',
        'env' => 'EDUCAHITS_PORTAL_REQUEST_URL',
        'valor' => $educahits_portal_request,
        'acao' => null,
    ],
    [
        'titulo' => 'API de pedidos (Master)',
        'descricao' => 'Pré-visualização opcional de pedidos externos.',
        'env' => 'EDUCAHITS_MASTER_REQUESTS_API',
        'valor' => $educahits_requests_api,
        'acao' => null,
    ],
    [
        'titulo' => 'API de músicas (Master)',
        'descricao' => 'Lista opcional de músicas do catálogo externo.',
        'env' => 'EDUCAHITS_MASTER_SONGS_API',
        'valor' => $educahits_songs_api,
        'acao' => null,
    ],
    [
        'titulo' => 'API de exclusão de música',
        'descricao' => 'Endpoint para apagar música no EducaHits.',
        'env' => 'EDUCAHITS_DELETE_SONG_API',
        'valor' => $educahits_delete_song_url,
        'acao' => null,
    ],
];

require __DIR__ . '/_nav.php';
?>

<div class="mb-4">
    <h2 class="text-lg font-semibold text-slate-900">Endpoints e integrações</h2>
    <p class="text-sm text-slate-500 mt-0.5">Valores lidos do <code class="text-xs bg-slate-100 px-1 rounded">.env</code>. Para alterar, edite as variáveis e recarregue a página.</p>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    <?php foreach ($endpoints as $ep):
        $valor = trim((string) ($ep['valor'] ?? ''));
        $configurado = $valor !== '';
    ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col">
        <div class="flex items-start justify-between gap-3 mb-2">
            <h3 class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($ep['titulo']) ?></h3>
            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium <?= $configurado ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                <?= $configurado ? 'Configurado' : 'Não definido' ?>
            </span>
        </div>
        <p class="text-sm text-slate-500 mb-3"><?= htmlspecialchars($ep['descricao']) ?></p>
        <p class="text-xs text-slate-400 mb-2">
            Env: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-700"><?= htmlspecialchars($ep['env']) ?></code>
        </p>
        <div class="mt-auto rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-xs font-mono text-slate-800 break-all"><?= htmlspecialchars($configurado ? $valor : '(não configurado)') ?></p>
        </div>
        <?php if (!empty($ep['acao']['href'])): ?>
        <div class="mt-3">
            <a href="<?= htmlspecialchars($ep['acao']['href']) ?>" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i>
                <?= htmlspecialchars($ep['acao']['label'] ?? 'Abrir') ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
