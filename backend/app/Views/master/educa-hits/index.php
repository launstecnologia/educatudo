<?php
$escolas = $escolas ?? [];
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
$educahits_master_dashboard = trim((string) ($educahits_master_dashboard ?? ''));
$educahits_receive_url = trim((string) ($educahits_receive_url ?? ''));
$educahits_delete_song_url = trim((string) ($educahits_delete_song_url ?? ''));
$requests_preview = $requests_preview ?? [];
$latest_songs = $latest_songs ?? [];
$local_requests = $local_requests ?? [];
$flash_meta = (isset($flash['meta']) && is_array($flash['meta'])) ? $flash['meta'] : null;
$show_escola_col = false;
foreach ($local_requests as $_lr) {
    if (!empty($_lr['escola_nome'])) {
        $show_escola_col = true;
        break;
    }
}
$eh_status_label = static function (string $s): string {
    return match ($s) {
        'pending' => 'Pendente',
        'in_progress' => 'Em andamento',
        'processing' => 'Em processamento',
        'approved' => 'Aprovado',
        'completed' => 'Concluído',
        'rejected' => 'Recusado',
        default => $s,
    };
};
$eh_status_styles = static function (string $s): string {
    return match ($s) {
        'pending' => 'bg-amber-50 text-amber-900 ring-1 ring-amber-200/80',
        'in_progress' => 'bg-sky-50 text-sky-800 ring-1 ring-sky-200/80',
        'processing' => 'bg-sky-50 text-sky-800 ring-1 ring-sky-200/80',
        'approved' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/80',
        'completed' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/80',
        'rejected' => 'bg-rose-50 text-rose-800 ring-1 ring-rose-200/80',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80',
    };
};
?>
<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>
<?php if (!empty($flash_meta) && ($flash_meta['source'] ?? '') === 'educahits_deliver'): ?>
<script>
(function () {
    var meta = <?= json_encode($flash_meta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var msg = <?= json_encode((string) ($flash['message'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var receiveHint = <?= json_encode(trim((string) ($educahits_receive_url ?? '')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    window.__EDUCAHITS_DELIVER_DEBUG = meta;

    var stepLabels = {
        curl_exec: 'Falha de rede (curl_exec)',
        curl_init: 'Falha ao iniciar cURL',
        curl_extension: 'Extensão cURL desativada',
        curl_file_create: 'curl_file_create indisponível',
        csrf: 'Token CSRF inválido',
        config: 'Falta URL ou token no .env',
        validation_audio: 'Ficheiro de áudio em falta',
        validation_meta: 'Título/artista em falta',
        validation_school: 'Escola ou slug inválido',
        post_too_large: 'POST excedeu limite do PHP',
        response_html: 'Resposta é HTML (URL/token?)',
        http_redirect: 'Redirecionamento HTTP 3xx',
        gateway_timeout: 'Timeout / gateway (502, 504, 524)',
        empty_body: 'Corpo da resposta vazio',
        empty_body_auth: 'Corpo vazio + 401/403',
        json_decode: 'Corpo não é JSON válido',
        api_error_field: 'API devolveu campo error',
        response_validation: 'JSON sem success/status ok'
    };

    var h = typeof meta.http_status === 'number' ? meta.http_status : null;
    var httpBadge = h == null ? '—' : String(h);
    var httpStyle = 'font-weight:800;padding:2px 8px;border-radius:4px';
    if (h >= 500) httpStyle += ';background:#fee2e2;color:#991b1b';
    else if (h >= 400) httpStyle += ';background:#ffedd5;color:#9a3412';
    else if (h >= 300) httpStyle += ';background:#fef9c3;color:#854d0e';
    else if (h >= 200) httpStyle += ';background:#dcfce7;color:#166534';
    else httpStyle += ';background:#e2e8f0;color:#334155';

    var title = stepLabels[meta.step] || meta.step || 'Erro na entrega';

    console.groupCollapsed(
        '%c EducaHits %c ' + title,
        'background:#4f46e5;color:#fff;padding:3px 10px;border-radius:6px;font-weight:700;letter-spacing:.02em',
        'color:#64748b;font-weight:600;margin-left:6px'
    );

    console.error('%cMensagem (igual ao banner)', 'font-weight:700;color:#b91c1c', '\n' + msg);
    console.log('%cHTTP %c' + httpBadge, 'color:#64748b;font-weight:600', httpStyle, h != null ? '' : '(sem código HTTP neste passo)');

    var endpoint = meta.endpoint || meta.endpoint_requested || receiveHint || '—';
    var summary = {
        passo: meta.step || '—',
        http_status: h != null ? h : '—',
        endpoint: endpoint,
        escola_slug: meta.school_slug || '—',
        bytes_corpo: meta.body_length != null ? meta.body_length : '—',
        timeout_cURL_s: meta.curl_timeout_used != null ? meta.curl_timeout_used : '—',
        redirect_url: meta.redirect_url || '',
        effective_url: meta.effective_url || '',
        ts_UTC: meta.ts || '—'
    };
    if (meta.escola_id != null && meta.escola_id !== '') {
        summary.escola_id = meta.escola_id;
    }
    if (meta.attempts_used != null) {
        summary.tentativas = meta.attempts_used;
    }
    if (meta.max_attempts != null) {
        summary.tentativas_max = meta.max_attempts;
    }
    Object.keys(summary).forEach(function (k) {
        if (summary[k] === '' || summary[k] == null) delete summary[k];
    });
    console.log('%cResumo', 'color:#4f46e5;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.08em');
    console.table(summary);

    if (meta.curl_error) console.warn('%cErro cURL', 'font-weight:700', meta.curl_error);
    if (meta.json_last_error) console.warn('%cjson_last_error', 'font-weight:700', meta.json_last_error);
    if (meta.redirect_url) console.info('%cRedirect', 'font-weight:700', meta.redirect_url);

    console.groupCollapsed('%cCorpo & resposta da API', 'color:#0f172a;font-weight:600');
    if (meta.body_preview != null && meta.body_preview !== '') {
        console.log('%cPrévia (texto)', 'font-weight:600;color:#475569', '\n' + meta.body_preview);
    } else {
        console.log('%cPrévia texto', 'font-weight:600;color:#94a3b8', '(não enviada neste erro)');
    }
    if (meta.response_json !== undefined && meta.response_json !== null) {
        console.log('%cJSON parseado', 'font-weight:600;color:#475569');
        console.dir(meta.response_json, { depth: 6 });
        try {
            console.log('%cJSON formatado (copiar)', 'font-weight:600;color:#64748b', '\n' + JSON.stringify(meta.response_json, null, 2));
        } catch (e) {}
    }
    console.groupEnd();

    console.log('%cObjeto bruto', 'font-weight:700;color:#334155', meta);
    console.log(
        '%cDica:%c na consola use %cwindow.__EDUCAHITS_DELIVER_DEBUG%c para voltar a inspecionar.',
        'font-weight:700;color:#0369a1',
        'color:#64748b',
        'font-family:monospace;background:#f1f5f9;padding:1px 6px;border-radius:4px',
        'color:#64748b'
    );

    console.groupEnd();
})();
</script>
<?php endif; ?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-900">EducaHits</h1>
    <p class="text-slate-600 mt-1">Pedidos criados pelo app EducaTudo aparecem na tabela abaixo. Opcionalmente use o portal externo e a API de entrega configurados no .env.</p>
</div>

<div class="mb-8 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 bg-white">
        <h2 class="text-lg font-semibold text-slate-900">Pedidos no EducaTudo</h2>
        <p class="text-sm text-slate-600 mt-1">União dos pedidos de <strong class="font-medium text-slate-800">todas as escolas ativas</strong> com banco configurado (<code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">educa_hits_requests</code>). Passe o mouse sobre observações para ver o texto completo.</p>
    </div>
    <?php if (empty($local_requests)): ?>
    <p class="px-6 py-8 text-sm text-slate-600">Nenhum pedido encontrado ou tabela ainda não existe neste ambiente.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-slate-50/90">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">ID</th>
                    <?php if ($show_escola_col): ?>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Escola</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Matéria</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Tema</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Observações</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Estilo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Criado</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Responder</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($local_requests as $row): ?>
                <?php
                $st = (string) ($row['status'] ?? '');
                $desc = trim((string) ($row['description'] ?? ''));
                ?>
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="px-4 py-3 text-slate-800 font-mono text-xs whitespace-nowrap align-top"><?= (int) ($row['id'] ?? 0) ?></td>
                    <?php if ($show_escola_col): ?>
                    <td class="px-4 py-3 text-slate-700 align-top">
                        <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($row['escola_nome'] ?? '')) ?></span>
                        <?php if (!empty($row['escola_slug'])): ?>
                        <span class="block text-[11px] text-slate-400 font-mono mt-0.5"><?= htmlspecialchars((string) $row['escola_slug']) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-slate-700 align-top"><?= htmlspecialchars((string) ($row['aluno_nome'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-slate-700 align-top"><?= htmlspecialchars((string) ($row['subject'] ?? '')) ?></td>
                    <td class="px-4 py-3 text-slate-800 align-top max-w-[10rem] sm:max-w-xs">
                        <span class="line-clamp-2" title="<?= htmlspecialchars((string) ($row['topic'] ?? '')) ?>"><?= htmlspecialchars((string) ($row['topic'] ?? '')) ?></span>
                    </td>
                    <td class="px-4 py-3 align-top max-w-[11rem] sm:max-w-[14rem]">
                        <?php if ($desc !== ''): ?>
                        <span class="block text-xs text-slate-600 leading-relaxed line-clamp-2 cursor-default border-l-2 border-blue-200 pl-2.5 py-0.5 bg-blue-50/40 rounded-r" title="<?= htmlspecialchars($desc) ?>"><?= htmlspecialchars($desc) ?></span>
                        <?php else: ?>
                        <span class="text-xs text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600 align-top whitespace-nowrap"><?= htmlspecialchars((string) ($row['music_style'] ?? '—')) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap align-top">
                        <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-md <?= htmlspecialchars($eh_status_styles($st)) ?>"><?= htmlspecialchars($eh_status_label($st)) ?></span>
                    </td>
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-xs align-top tabular-nums"><?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap align-top">
                        <form method="post" action="<?= URL ?>/master/educa-hits/request-status" class="flex items-center gap-2">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="request_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                            <input type="hidden" name="escola_id" value="<?= (int) ($row['escola_id'] ?? $row['school_id'] ?? 0) ?>">
                            <select name="status" class="border border-slate-300 rounded-md px-2 py-1 text-xs bg-white">
                                <option value="processing" <?= in_array($st, ['processing', 'in_progress'], true) ? 'selected' : '' ?>>Em processamento</option>
                                <option value="approved" <?= in_array($st, ['approved', 'completed'], true) ? 'selected' : '' ?>>Aprovado</option>
                                <option value="rejected" <?= $st === 'rejected' ? 'selected' : '' ?>>Recusado</option>
                            </select>
                            <button type="submit" class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md border border-blue-200 text-blue-700 hover:bg-blue-50">
                                Responder
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="grid gap-6 lg:grid-cols-2 mb-8">
    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-2">Portal EducaHits (externo)</h2>
        <p class="text-sm text-slate-600 mb-4">Atalho opcional para o painel web do produto EducaHits, se configurado.</p>
        <?php if ($educahits_master_dashboard !== ''): ?>
        <a href="<?= htmlspecialchars($educahits_master_dashboard) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            Abrir painel de pedidos
        </a>
        <?php else: ?>
        <p class="text-sm text-slate-500">Opcional: defina <code class="text-xs bg-slate-100 px-1 rounded">EDUCAHITS_MASTER_DASHBOARD_URL</code> no .env para exibir o link.</p>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-2">API de entrega</h2>
        <p class="text-sm text-slate-600 mb-1">Endpoint configurado:</p>
        <p class="text-xs font-mono text-slate-800 break-all bg-slate-50 border rounded px-2 py-1"><?= htmlspecialchars($educahits_receive_url !== '' ? $educahits_receive_url : '(EDUCAHITS_RECEIVE_SONG_URL)') ?></p>
    </div>
</div>

<?php if (!empty($requests_preview)): ?>
<div class="mb-8 bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-slate-900">Pré-visualização (EDUCAHITS_MASTER_REQUESTS_API)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Dados</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($requests_preview as $row): ?>
                <tr>
                    <td class="px-4 py-2 text-slate-700 font-mono text-xs whitespace-pre-wrap"><?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="mb-8 bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-slate-900">Últimas músicas</h2>
        <p class="text-sm text-slate-600 mt-1">
            Lista opcional via <code class="text-xs bg-slate-100 px-1 rounded">EDUCAHITS_MASTER_SONGS_API</code>.
        </p>
    </div>
    <?php if (empty($latest_songs)): ?>
    <p class="px-6 py-8 text-sm text-slate-600">
        Sem dados no momento. Configure <code class="text-xs bg-slate-100 px-1 rounded">EDUCAHITS_MASTER_SONGS_API</code> para carregar as últimas músicas.
    </p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Título</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Artista</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Álbum</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Links</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Detalhes</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($latest_songs as $song): ?>
                <?php
                $songId = trim((string) ($song['id'] ?? $song['song_id'] ?? $song['songId'] ?? ''));
                $title = (string) ($song['title'] ?? $song['name'] ?? '—');
                $artist = (string) ($song['artist'] ?? $song['author'] ?? '—');
                $album = (string) ($song['album'] ?? '—');
                $school = (string) ($song['school_slug'] ?? $song['school'] ?? '—');
                $created = (string) ($song['created_at'] ?? $song['createdAt'] ?? $song['date'] ?? '—');
                $audioUrl = trim((string) ($song['audio_url'] ?? $song['audioUrl'] ?? $song['url'] ?? ''));
                $coverUrl = trim((string) ($song['cover_url'] ?? $song['coverUrl'] ?? ''));
                $songJson = json_encode($song, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                ?>
                <tr class="align-top">
                    <td class="px-4 py-2 text-slate-800 max-w-[12rem]">
                        <span class="line-clamp-2" title="<?= htmlspecialchars($title) ?>"><?= htmlspecialchars($title) ?></span>
                    </td>
                    <td class="px-4 py-2 text-slate-700 max-w-[9rem]">
                        <span class="line-clamp-2" title="<?= htmlspecialchars($artist) ?>"><?= htmlspecialchars($artist) ?></span>
                    </td>
                    <td class="px-4 py-2 text-slate-700 max-w-[9rem]">
                        <span class="line-clamp-2" title="<?= htmlspecialchars($album) ?>"><?= htmlspecialchars($album) ?></span>
                    </td>
                    <td class="px-4 py-2 text-slate-700 max-w-[7rem]">
                        <span class="line-clamp-2" title="<?= htmlspecialchars($school) ?>"><?= htmlspecialchars($school) ?></span>
                    </td>
                    <td class="px-4 py-2 text-slate-500 whitespace-nowrap"><?= htmlspecialchars($created) ?></td>
                    <td class="px-4 py-2 text-xs text-slate-700 whitespace-nowrap">
                        <?php if ($audioUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($audioUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-2 py-1 rounded border border-blue-200 text-blue-700 hover:bg-blue-50">Áudio</a>
                        <?php endif; ?>
                        <?php if ($coverUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($coverUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-2 py-1 rounded border border-slate-200 text-slate-700 hover:bg-slate-50 ml-1">Capa</a>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2 text-xs text-slate-700 min-w-[16rem]">
                        <details>
                            <summary class="cursor-pointer text-blue-700 hover:text-slate-900">Ver JSON</summary>
                            <pre class="mt-2 p-2 bg-slate-50 border rounded text-[11px] leading-relaxed max-h-40 overflow-auto whitespace-pre-wrap break-all"><?= htmlspecialchars((string) ($songJson ?? '{}')) ?></pre>
                        </details>
                    </td>
                    <td class="px-4 py-2 text-xs text-slate-700 whitespace-nowrap">
                        <?php if ($educahits_delete_song_url !== '' && $songId !== ''): ?>
                        <form method="post" action="<?= URL ?>/master/educa-hits/delete-song" onsubmit="return confirm('Tem certeza que deseja apagar esta música?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="song_id" value="<?= htmlspecialchars($songId) ?>">
                            <input type="hidden" name="title" value="<?= htmlspecialchars($title) ?>">
                            <input type="hidden" name="artist" value="<?= htmlspecialchars($artist) ?>">
                            <input type="hidden" name="album" value="<?= htmlspecialchars($album) ?>">
                            <input type="hidden" name="school_slug" value="<?= htmlspecialchars($school) ?>">
                            <input type="hidden" name="audio_url" value="<?= htmlspecialchars($audioUrl) ?>">
                            <input type="hidden" name="cover_url" value="<?= htmlspecialchars($coverUrl) ?>">
                            <button type="submit" class="inline-flex items-center px-2 py-1 rounded border border-rose-200 text-rose-700 hover:bg-rose-50">Apagar</button>
                        </form>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow border border-slate-200 p-6">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Entregar música</h2>
    <form method="post" action="<?= URL ?>/master/educa-hits/deliver" enctype="multipart/form-data" class="space-y-4 max-w-2xl">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Escola (tenant) *</label>
            <select name="escola_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
                <option value="">Selecione…</option>
                <option value="all">Todas as escolas ativas</option>
                <?php foreach ($escolas as $e): ?>
                <option value="<?= (int) ($e['id'] ?? 0) ?>"><?= htmlspecialchars(($e['nome'] ?? '') . ' (' . ($e['slug'] ?? '') . ')') ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-500 mt-1">Ao selecionar "Todas as escolas ativas", o sistema tenta envio único global (sem duplicar). Se a API exigir slug, usa fallback por escola.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Título *</label>
            <input type="text" name="title" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Artista *</label>
            <input type="text" name="artist" value="EducaHits" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Álbum</label>
            <input type="text" name="album" class="w-full border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Matéria</label>
                <input type="text" name="subject" class="w-full border border-slate-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tema</label>
                <input type="text" name="topic" class="w-full border border-slate-300 rounded-lg px-3 py-2">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Observações / notas</label>
            <textarea name="notes" rows="2" class="w-full border border-slate-300 rounded-lg px-3 py-2"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Letra</label>
            <textarea name="lyrics" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="Opcional"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Duração (segundos)</label>
            <input type="number" name="duration" min="0" value="0" class="w-32 border border-slate-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Áudio *</label>
            <input type="file" name="audio" accept=".mp3,.m4a,.wav,.ogg,audio/*" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Capa</label>
            <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/*">
        </div>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">Enviar para o EducaHits</button>
    </form>
</div>
