<?php
$items = is_array($items ?? null) ? $items : [];
$conversa = is_array($conversa ?? null) ? $conversa : null;
$mensagens = is_array($mensagens ?? null) ? $mensagens : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$conversaId = (int) ($conversa['id'] ?? 0);
$baseUrl = URL . '/admin/comunicacao-escolar/fale-com-a-escola';
$escolaAvatar = trim((string) ($escola_avatar ?? ''));
$reacoesProntas = !empty($reacoes_prontas);
$ultimoMsgId = 0;

$iniciais = static function (string $nome): string {
    $nome = trim($nome);
    if ($nome === '') {
        return '?';
    }
    $partes = preg_split('/\s+/', $nome) ?: [];
    $a = mb_substr($partes[0] ?? '', 0, 1);
    $b = mb_substr($partes[count($partes) > 1 ? count($partes) - 1 : 0] ?? '', 0, 1);
    return mb_strtoupper($a . $b);
};

$urlAnexo = static function (string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim((string) URL, '/') . '/' . ltrim($path, '/');
};

$urlMidia = static function (string $path) use ($urlAnexo): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    // Fotos de perfil: normaliza caminho legado; anexos de chat NÃO passam por aqui
    if (class_exists(\App\Helpers\AvatarUrlHelper::class) && !str_contains($path, 'storage/uploads/fale-com-a-escola') && !str_contains($path, 'storage/uploads/school-communications')) {
        $normalizado = \App\Helpers\AvatarUrlHelper::normalizeAdminAvatarUrl($path);
        if (is_string($normalizado) && $normalizado !== '') {
            return $normalizado;
        }
    }
    return $urlAnexo($path);
};

$renderAvatares = static function (string $fotoResp, string $fotoAluno, string $nomeResp, string $nomeAluno) use ($iniciais): void {
    ?>
    <div class="fc-avatars" title="<?= htmlspecialchars($nomeResp . ' · aluno ' . $nomeAluno, ENT_QUOTES, 'UTF-8') ?>">
        <div class="fc-avatar">
            <?php if ($fotoResp !== ''): ?>
                <img src="<?= htmlspecialchars($fotoResp) ?>" alt="" onerror="this.remove()">
            <?php else: ?>
                <?= htmlspecialchars($iniciais($nomeResp)) ?>
            <?php endif; ?>
        </div>
        <div class="fc-avatar fc-avatar-aluno">
            <?php if ($fotoAluno !== ''): ?>
                <img src="<?= htmlspecialchars($fotoAluno) ?>" alt="" onerror="this.remove()">
            <?php else: ?>
                <?= htmlspecialchars($iniciais($nomeAluno)) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
};

$fmtHoraLista = static function (?string $dt): string {
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return '';
    }
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return date('H:i', $ts);
    }
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Ontem';
    }
    return date('d/m', $ts);
};

$fmtDiaDivisor = static function (string $dt): string {
    $ts = strtotime($dt);
    if ($ts === false) {
        return '';
    }
    $meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return 'Hoje, ' . (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts)];
    }
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Ontem, ' . (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts)];
    }
    return (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
};
?>
<style>
.fc-shell { display:flex; flex-direction:column; gap:1rem; width:100%; }
.fc-card {
  display:grid; grid-template-columns:1fr; width:100%;
  height:calc(100vh - 10.5rem); min-height:560px; max-height:820px;
  background:#fff; border:1px solid #e6ebf2; border-radius:18px;
  box-shadow:0 8px 24px rgba(15,23,42,.06); overflow:hidden;
}
@media (min-width:1024px) { .fc-card { grid-template-columns: 340px 1fr; } }

.fc-side { display:flex; flex-direction:column; min-height:0; background:#f7f9fc; border-bottom:1px solid #e6ebf2; }
@media (min-width:1024px) { .fc-side { border-bottom:0; border-right:1px solid #e6ebf2; } }
.fc-side-top { padding:14px 14px 10px; background:#f7f9fc; }
.fc-search-row { display:flex; gap:8px; align-items:center; }
.fc-search {
  flex:1; display:flex; align-items:center; gap:8px; background:#fff;
  border:1px solid #e2e8f0; border-radius:12px; padding:10px 12px;
}
.fc-search input {
  border:0; outline:none; background:transparent; width:100%; font-size:13px; color:#0f172a;
}
.fc-icon-btn {
  width:40px; height:40px; border-radius:12px; border:1px solid #e2e8f0; background:#fff;
  color:#64748b; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
}
.fc-pills { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
.fc-pill {
  border:0; border-radius:999px; padding:7px 12px; font-size:12px; font-weight:600; cursor:pointer;
  background:#eef2f7; color:#475569;
}
.fc-pill.is-on { background:#1e3a5f; color:#fff; }
.fc-pill:disabled { opacity:.45; cursor:not-allowed; }

.fc-list { flex:1; min-height:0; overflow:auto; padding:4px 8px 12px; }
.fc-item {
  display:flex; gap:12px; align-items:center; padding:12px; border-radius:14px;
  text-decoration:none; color:inherit; border:1px solid transparent; position:relative;
}
.fc-item:hover { background:#fff; }
.fc-item.is-on { background:#eaf2ff; border-color:#dbe7fb; }
.fc-item.is-on::before {
  content:""; position:absolute; left:0; top:12px; bottom:12px; width:3px;
  border-radius:0 4px 4px 0; background:#2f6fed;
}
.fc-avatar {
  width:44px; height:44px; border-radius:999px; overflow:hidden; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;
  background:#dbeafe; color:#1e3a8a;
}
.fc-avatar img { width:100%; height:100%; object-fit:cover; }
.fc-avatars { position:relative; width:48px; height:44px; flex-shrink:0; }
.fc-avatars .fc-avatar { width:40px; height:40px; font-size:11px; }
.fc-avatars .fc-avatar-aluno {
  position:absolute; right:0; bottom:0; width:22px; height:22px; font-size:8px;
  border:2px solid #f7f9fc; box-shadow:0 0 0 1px rgba(15,23,42,.06);
  background:#e0e7ff; color:#3730a3;
}
.fc-head .fc-avatars { width:54px; height:46px; }
.fc-head .fc-avatars .fc-avatar { width:46px; height:46px; }
.fc-head .fc-avatars .fc-avatar-aluno {
  width:24px; height:24px; border-color:#fff;
}
.fc-item-main { min-width:0; flex:1; }
.fc-item-top { display:flex; justify-content:space-between; gap:8px; align-items:center; }
.fc-item-name { font-size:14px; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fc-item-time { font-size:11px; color:#94a3b8; flex-shrink:0; }
.fc-item-sub { font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
.fc-item-bottom { display:flex; justify-content:space-between; gap:8px; align-items:center; margin-top:2px; }
.fc-item-last { font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fc-badge {
  min-width:18px; height:18px; padding:0 6px; border-radius:999px; background:#22c55e; color:#fff;
  font-size:10px; font-weight:700; display:inline-flex; align-items:center; justify-content:center;
}

.fc-thread { display:flex; flex-direction:column; min-height:0; background:#fbfcfe; }
.fc-head {
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  padding:14px 18px; background:#fff; border-bottom:1px solid #e6ebf2; flex-shrink:0;
}
.fc-head-left { display:flex; align-items:center; gap:12px; min-width:0; }
.fc-head-actions { display:flex; gap:8px; flex-shrink:0; }
.fc-round {
  width:38px; height:38px; border-radius:999px; border:1px solid #e2e8f0; background:#fff;
  color:#64748b; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
}
.fc-round:disabled { opacity:.4; cursor:not-allowed; }

.fc-msgs {
  flex:1; min-height:0; overflow:auto; padding:18px 20px 12px;
  background-color:#f4f7fb;
  background-image:
    radial-gradient(rgba(30,58,95,.035) 1.2px, transparent 1.2px);
  background-size:22px 22px;
}
.fc-day {
  display:flex; justify-content:center; margin:10px 0 16px;
}
.fc-day span {
  background:#e8eef6; color:#64748b; font-size:12px; font-weight:600;
  padding:6px 12px; border-radius:999px;
}
.fc-row { display:flex; margin-bottom:14px; }
.fc-row.is-escola { justify-content:flex-end; }
.fc-row.is-familia { justify-content:flex-start; }
.fc-bubble {
  max-width:min(72%, 420px); border-radius:16px; padding:10px 12px 8px;
  box-shadow:0 1px 2px rgba(15,23,42,.06); position:relative;
}
.fc-bubble.is-familia { background:#fff; border-bottom-left-radius:6px; }
.fc-bubble.is-escola { background:#dcfce7; border-bottom-right-radius:6px; }
.fc-tag {
  display:inline-block; font-size:10px; font-weight:800; letter-spacing:.04em;
  text-transform:uppercase; padding:2px 7px; border-radius:999px; margin-bottom:6px;
}
.fc-tag.is-familia { background:#dbeafe; color:#1d4ed8; }
.fc-tag.is-escola { background:#bbf7d0; color:#15803d; }
.fc-text { font-size:14px; line-height:1.45; color:#0f172a; white-space:pre-wrap; word-break:break-word; }
.fc-meta { display:flex; align-items:center; justify-content:flex-end; gap:6px; margin-top:6px; }
.fc-time { font-size:11px; color:#94a3b8; }
.fc-checks { color:#16a34a; font-size:12px; }
.fc-react {
  display:inline-flex; align-items:center; gap:4px; margin-top:6px;
  background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:2px 8px; font-size:11px;
}
.fc-like {
  border:0; background:transparent; color:#64748b; cursor:pointer; padding:0; font-size:12px;
}
.fc-like.is-on { color:#0d9488; }

.fc-composer-wrap {
  flex-shrink:0; padding:12px 14px 14px; background:transparent;
}
.fc-composer {
  display:flex; align-items:flex-end; gap:8px; background:#fff; border:1px solid #e2e8f0;
  border-radius:18px; padding:8px 10px; box-shadow:0 4px 14px rgba(15,23,42,.04);
}
.fc-tools { display:flex; align-items:center; gap:4px; flex-shrink:0; }
.fc-tool {
  width:36px; height:36px; border:0; border-radius:999px; background:transparent;
  color:#64748b; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
}
.fc-tool:hover { background:#f1f5f9; color:#0f172a; }
.fc-plus {
  width:36px; height:36px; border:0; border-radius:999px; background:#eff6ff; color:#1d4ed8;
  cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
}
.fc-input {
  flex:1; min-width:0; border:0; outline:none; resize:none; max-height:110px;
  background:#f8fbff; border-radius:14px; padding:10px 12px; font-size:14px; line-height:1.35;
}
.fc-send, .fc-mic {
  width:40px; height:40px; border:0; border-radius:999px; background:#1e3a5f; color:#fff;
  cursor:pointer; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
}
.fc-send:hover, .fc-mic:hover { background:#16304f; }
.fc-menu {
  display:none; position:absolute; bottom:48px; left:0; z-index:40; width:190px;
  background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden;
  box-shadow:0 12px 28px rgba(15,23,42,.12);
}
.fc-menu.is-open { display:block; }
.fc-menu button {
  width:100%; border:0; background:#fff; text-align:left; padding:11px 12px;
  font-size:13px; color:#334155; cursor:pointer; display:flex; gap:8px; align-items:center;
}
.fc-menu button:hover { background:#f8fafc; }
.fc-chip {
  display:none; margin:0 14px 8px; align-items:center; justify-content:space-between; gap:8px;
  background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:8px 10px; font-size:13px;
}
.fc-chip.is-on { display:flex; }
.fc-empty {
  flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
  text-align:center; color:#64748b; padding:2rem; background:#f8fafc;
}
.fc-emoji {
  display:none; position:absolute; bottom:48px; left:40px; z-index:40; width:220px;
  background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:8px;
  box-shadow:0 12px 28px rgba(15,23,42,.12); grid-template-columns:repeat(6,1fr); gap:4px;
}
.fc-emoji.is-open { display:grid; }
.fc-emoji button {
  border:0; background:transparent; font-size:18px; cursor:pointer; border-radius:8px; padding:4px;
}
.fc-emoji button:hover { background:#f1f5f9; }

.fc-audio {
  display:flex; align-items:center; gap:10px; margin-top:8px;
  min-width:220px; max-width:280px; padding:8px 10px; border-radius:14px;
  background:rgba(15,23,42,.04);
}
.fc-bubble.is-escola .fc-audio { background:rgba(22,163,74,.10); }
.fc-audio-play {
  width:36px; height:36px; border:0; border-radius:999px; flex-shrink:0;
  background:#1e3a5f; color:#fff; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center;
}
.fc-bubble.is-escola .fc-audio-play { background:#15803d; }
.fc-audio-play:disabled { opacity:.45; cursor:not-allowed; }
.fc-audio-body { flex:1; min-width:0; display:flex; flex-direction:column; gap:4px; }
.fc-audio-track {
  position:relative; height:22px; display:flex; align-items:center; cursor:pointer;
}
.fc-audio-bars {
  display:flex; align-items:center; gap:2px; width:100%; height:100%;
}
.fc-audio-bars span {
  flex:1; border-radius:2px; background:#94a3b8; min-width:2px;
  transition:background .12s ease;
}
.fc-bubble.is-escola .fc-audio-bars span { background:#86efac; }
.fc-audio-bars span.is-done { background:#1e3a5f; }
.fc-bubble.is-escola .fc-audio-bars span.is-done { background:#166534; }
.fc-audio-scrub {
  position:absolute; left:0; top:50%; width:10px; height:10px; margin-top:-5px;
  border-radius:999px; background:#1e3a5f; box-shadow:0 0 0 2px #fff;
  pointer-events:none; transform:translateX(-50%);
}
.fc-bubble.is-escola .fc-audio-scrub { background:#166534; }
.fc-audio-time { font-size:11px; color:#64748b; font-variant-numeric:tabular-nums; }
.fc-audio audio { display:none; }
@keyframes fc-flash-familia {
  0% { box-shadow:0 0 0 2px rgba(37,99,235,.35); }
  100% { box-shadow:0 1px 2px rgba(15,23,42,.06); }
}
.fc-row.fc-new-in .fc-bubble.is-familia { animation:fc-flash-familia 1.4s ease; }
</style>

<div class="fc-shell">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Fale com a Escola</h2>
            <p class="text-gray-600 mt-1">Inbox de conversas com as famílias.</p>
        </div>
        <a href="<?= URL ?>/admin/comunicacao-escolar" class="text-gray-600 hover:text-gray-900">← Comunicação escolar</a>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash_type ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars((string) $flash_message) ?>
        </div>
    <?php endif; ?>

    <div class="fc-card">
        <aside class="fc-side">
            <div class="fc-side-top">
                <div class="fc-search-row">
                    <label class="fc-search">
                        <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm"></i>
                        <input type="search" id="fc-busca" placeholder="Buscar conversas, pessoas ou grupos..." autocomplete="off">
                    </label>
                    <button type="button" class="fc-icon-btn" title="Filtros" aria-label="Filtros"><i class="fa-solid fa-sliders"></i></button>
                </div>
                <div class="fc-pills">
                    <button type="button" class="fc-pill is-on" data-fc-filtro="todas">Todas</button>
                    <button type="button" class="fc-pill" data-fc-filtro="familias">Famílias</button>
                    <button type="button" class="fc-pill" disabled title="Em breve">Alunos</button>
                    <button type="button" class="fc-pill" disabled title="Em breve">Equipe</button>
                </div>
            </div>
            <div class="fc-list" id="fc-lista">
                <?php if ($items === []): ?>
                    <p class="px-4 py-8 text-sm text-slate-500">Nenhuma família falou com a escola ainda.</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $id = (int) ($item['id'] ?? 0);
                        $ativa = $id === $conversaId;
                        $naoLidas = (int) ($item['nao_lidas'] ?? 0);
                        $nomeAluno = (string) ($item['aluno_nome'] ?? 'Aluno');
                        $nomeResp = (string) ($item['responsavel_nome'] ?? 'Responsável');
                        $fotoResp = $urlMidia((string) ($item['responsavel_foto_url'] ?? ''));
                        $fotoAluno = $urlMidia((string) ($item['aluno_foto_url'] ?? ($item['foto_url'] ?? '')));
                        $busca = mb_strtolower($nomeAluno . ' ' . $nomeResp . ' ' . (string) ($item['ultima'] ?? ''));
                        ?>
                        <a href="<?= $baseUrl ?>/<?= $id ?>"
                           class="fc-item <?= $ativa ? 'is-on' : '' ?>"
                           data-fc-item
                           data-fc-busca="<?= htmlspecialchars($busca) ?>">
                            <?php $renderAvatares($fotoResp, $fotoAluno, $nomeResp, $nomeAluno); ?>
                            <div class="fc-item-main">
                                <div class="fc-item-top">
                                    <div class="fc-item-name"><?= htmlspecialchars($nomeResp) ?></div>
                                    <div class="fc-item-time"><?= htmlspecialchars($fmtHoraLista($item['updated_at'] ?? null)) ?></div>
                                </div>
                                <div class="fc-item-sub">Aluno: <?= htmlspecialchars($nomeAluno) ?></div>
                                <div class="fc-item-bottom">
                                    <div class="fc-item-last"><?= htmlspecialchars((string) (($item['ultima'] ?? '') !== '' ? $item['ultima'] : 'Anexo')) ?></div>
                                    <?php if ($naoLidas > 0): ?>
                                        <span class="fc-badge"><?= $naoLidas ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section class="fc-thread">
            <?php if ($conversa === null): ?>
                <div class="fc-empty">
                    <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center mb-3">
                        <i class="fa-regular fa-comments text-blue-700 text-xl"></i>
                    </div>
                    <p class="text-base font-semibold text-slate-900">Selecione uma conversa</p>
                    <p class="text-sm text-slate-500 mt-1 max-w-sm">Escolha uma família à esquerda para ver o histórico e responder.</p>
                </div>
            <?php else: ?>
                <?php
                $nomeAluno = (string) ($conversa['aluno_nome'] ?? 'Aluno');
                $nomeResp = (string) ($conversa['responsavel_nome'] ?? 'Responsável');
                $fotoResp = $urlMidia((string) ($conversa['responsavel_foto_url'] ?? ''));
                $fotoAluno = $urlMidia((string) ($conversa['aluno_foto_url'] ?? ($conversa['foto_url'] ?? '')));
                ?>
                <div class="fc-head">
                    <div class="fc-head-left">
                        <?php $renderAvatares($fotoResp, $fotoAluno, $nomeResp, $nomeAluno); ?>
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($nomeResp) ?></p>
                            <p class="text-xs text-slate-500 truncate">Aluno: <?= htmlspecialchars($nomeAluno) ?></p>
                        </div>
                    </div>
                    <div class="fc-head-actions">
                        <button type="button" class="fc-round" disabled title="Em breve"><i class="fa-solid fa-video"></i></button>
                        <button type="button" class="fc-round" disabled title="Em breve"><i class="fa-solid fa-phone"></i></button>
                        <button type="button" id="fc-btn-info" class="fc-round" title="Informações"
                                data-aluno="<?= htmlspecialchars($nomeAluno, ENT_QUOTES, 'UTF-8') ?>"
                                data-responsavel="<?= htmlspecialchars($nomeResp, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-circle-info"></i>
                        </button>
                        <button type="button" class="fc-round" disabled title="Mais opções"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    </div>
                </div>

                <div id="fale-chat-mensagens" class="fc-msgs">
                    <?php
                    $diaAtual = '';
                    if ($mensagens === []):
                    ?>
                        <p id="fale-chat-vazio" class="text-sm text-slate-500 text-center py-10">Nenhuma mensagem nesta conversa.</p>
                    <?php endif; ?>
                    <?php foreach ($mensagens as $mensagem): ?>
                        <?php
                        $daEscola = ($mensagem['autor_tipo'] ?? '') === 'escola';
                        $msgId = (int) ($mensagem['id'] ?? 0);
                        $created = (string) ($mensagem['created_at'] ?? '');
                        $dia = $created !== '' ? date('Y-m-d', strtotime($created)) : '';
                        $anexoTipo = (string) ($mensagem['anexo_tipo'] ?? (!empty($mensagem['imagem']) ? 'imagem' : ''));
                        $anexoMime = (string) ($mensagem['anexo_mime'] ?? '');
                        $anexoPath = (string) ($mensagem['imagem'] ?? '');
                        $anexoNome = (string) ($mensagem['imagem_nome'] ?? 'arquivo');
                        $anexoExt = strtolower(pathinfo($anexoPath !== '' ? $anexoPath : $anexoNome, PATHINFO_EXTENSION));
                        $extAudio = in_array($anexoExt, ['webm', 'weba', 'ogg', 'mp3', 'm4a', 'aac', 'wav'], true);
                        if ($anexoTipo === '' && (str_starts_with($anexoMime, 'audio/') || $extAudio)) {
                            $anexoTipo = 'audio';
                        }
                        if ($anexoTipo === 'arquivo' && (str_starts_with($anexoMime, 'audio/') || $extAudio)) {
                            $anexoTipo = 'audio';
                        }
                        if ($anexoTipo === 'audio' && $anexoMime === '') {
                            $anexoMime = match ($anexoExt) {
                                'm4a', 'mp4' => 'audio/mp4',
                                'mp3' => 'audio/mpeg',
                                'ogg' => 'audio/ogg',
                                'wav' => 'audio/wav',
                                'aac' => 'audio/aac',
                                'weba', 'webm' => 'audio/webm',
                                default => 'audio/webm',
                            };
                        }
                        $anexoUrl = $urlAnexo($anexoPath);
                        $curtidas = (int) ($mensagem['curtidas'] ?? 0);
                        $euCurti = !empty($mensagem['eu_curti']);
                        $lidaFamilia = !empty($mensagem['lido_familia_em']);
                        ?>
                        <?php if ($dia !== '' && $dia !== $diaAtual): ?>
                            <?php $diaAtual = $dia; ?>
                            <div class="fc-day" data-fc-dia="<?= htmlspecialchars($dia, ENT_QUOTES, 'UTF-8') ?>"><span><?= htmlspecialchars($fmtDiaDivisor($created)) ?></span></div>
                        <?php endif; ?>

                        <div id="msg-<?= $msgId ?>" class="fc-row <?= $daEscola ? 'is-escola' : 'is-familia' ?>">
                            <div class="fc-bubble <?= $daEscola ? 'is-escola' : 'is-familia' ?>">
                                <span class="fc-tag <?= $daEscola ? 'is-escola' : 'is-familia' ?>"><?= $daEscola ? 'Escola' : 'Família' ?></span>
                                <?php if (trim((string) ($mensagem['mensagem'] ?? '')) !== ''): ?>
                                    <div class="fc-text"><?= nl2br(htmlspecialchars((string) $mensagem['mensagem'])) ?></div>
                                <?php endif; ?>

                                <?php if ($anexoUrl !== '' && $anexoTipo === 'imagem'): ?>
                                    <a href="<?= htmlspecialchars($anexoUrl) ?>" target="_blank" rel="noopener" class="block mt-2">
                                        <img src="<?= htmlspecialchars($anexoUrl) ?>" alt="Imagem" style="max-height:220px;border-radius:10px;display:block;">
                                    </a>
                                <?php elseif ($anexoUrl !== '' && $anexoTipo === 'video'): ?>
                                    <video controls style="margin-top:8px;max-height:220px;width:100%;border-radius:10px;background:#000;" preload="metadata">
                                        <source src="<?= htmlspecialchars($anexoUrl) ?>" type="<?= htmlspecialchars($anexoMime !== '' ? $anexoMime : 'video/mp4') ?>">
                                    </video>
                                <?php elseif ($anexoUrl !== '' && $anexoTipo === 'audio'): ?>
                                    <?php
                                    $barras = [8,14,10,18,12,16,9,15,11,17,13,10,16,12,14,9,18,11,15,10,13,17,12,16,9,14,11,15];
                                    ?>
                                    <div class="fc-audio" data-fc-audio data-src="<?= htmlspecialchars($anexoUrl, ENT_QUOTES, 'UTF-8') ?>" data-mime="<?= htmlspecialchars($anexoMime !== '' ? $anexoMime : 'audio/webm', ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="button" class="fc-audio-play" aria-label="Reproduzir áudio"><i class="fa-solid fa-play"></i></button>
                                        <div class="fc-audio-body">
                                            <div class="fc-audio-track" data-fc-seek>
                                                <div class="fc-audio-bars">
                                                    <?php foreach ($barras as $h): ?>
                                                        <span style="height:<?= (int) $h ?>px"></span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="fc-audio-scrub" style="left:0%"></div>
                                            </div>
                                            <span class="fc-audio-time">0:00</span>
                                        </div>
                                        <audio preload="metadata" playsinline>
                                            <source src="<?= htmlspecialchars($anexoUrl) ?>" type="<?= htmlspecialchars($anexoMime !== '' ? $anexoMime : 'audio/webm') ?>">
                                        </audio>
                                    </div>
                                <?php elseif ($anexoUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($anexoUrl) ?>" target="_blank" rel="noopener"
                                       style="margin-top:8px;display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;color:#1e293b;text-decoration:none;">
                                        <i class="fa-regular fa-file"></i> <?= htmlspecialchars($anexoNome) ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($curtidas > 0 || ($reacoesProntas && $msgId > 0)): ?>
                                    <div class="fc-react">
                                        <?php if ($curtidas > 0): ?><span class="fc-like-count">👍 <?= $curtidas ?></span><?php endif; ?>
                                        <?php if ($reacoesProntas && $msgId > 0): ?>
                                            <form method="post" class="fc-like-form" action="<?= $baseUrl ?>/<?= $conversaId ?>/mensagens/<?= $msgId ?>/curtir" style="display:inline;margin:0;">
                                                <input type="hidden" name="_token" value="<?= $csrf ?>">
                                                <button type="submit" class="fc-like <?= $euCurti ? 'is-on' : '' ?>" title="<?= $euCurti ? 'Remover curtida' : 'Curtir' ?>">
                                                    <i class="fa-<?= $euCurti ? 'solid' : 'regular' ?> fa-thumbs-up"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="fc-meta">
                                    <span class="fc-time"><?= $created !== '' ? date('H:i', strtotime($created)) : '' ?></span>
                                    <?php if ($daEscola): ?>
                                        <span class="fc-checks" title="<?= $lidaFamilia ? 'Lida pela família' : 'Enviada' ?>">
                                            <i class="fa-solid fa-check<?= $lidaFamilia ? '-double' : '' ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="fale-anexo-preview" class="fc-chip">
                    <span id="fale-anexo-nome" class="truncate"></span>
                    <button type="button" id="fale-anexo-remover" class="text-slate-500 hover:text-red-600" title="Remover"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div id="fale-gravando" class="fc-chip" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;">
                    <span>Gravando <strong id="fale-gravando-timer">0:00</strong></span>
                    <span style="display:inline-flex;gap:8px;align-items:center;">
                        <button type="button" id="fale-gravando-cancelar" style="border:0;background:transparent;color:#b91c1c;font-size:12px;font-weight:600;cursor:pointer;">Cancelar</button>
                        <button type="button" id="fale-gravando-parar" class="fc-send" style="width:auto;height:auto;padding:5px 10px;font-size:12px;border-radius:999px;">
                            <i class="fa-solid fa-check"></i> Pronto
                        </button>
                    </span>
                </div>

                <form id="fale-chat-form" method="post" enctype="multipart/form-data" action="<?= $baseUrl ?>/<?= $conversaId ?>/responder" class="fc-composer-wrap">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <input type="file" id="fale-anexo-input" name="anexo" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf,audio/*" class="hidden">

                    <div class="fc-composer">
                        <div class="fc-tools" style="position:relative;">
                            <button type="button" id="fale-btn-mais" class="fc-plus" title="Mais" aria-label="Mais"><i class="fa-solid fa-plus"></i></button>
                            <div id="fale-menu-anexo" class="fc-menu">
                                <button type="button" data-fale-attach="image/*"><i class="fa-regular fa-image" style="color:#0d9488;width:16px;"></i> Foto</button>
                                <button type="button" data-fale-attach="video/mp4,video/webm"><i class="fa-solid fa-video" style="color:#7c3aed;width:16px;"></i> Vídeo</button>
                                <button type="button" data-fale-attach="application/pdf"><i class="fa-regular fa-file-pdf" style="color:#dc2626;width:16px;"></i> Documento</button>
                            </div>
                            <button type="button" id="fale-btn-clip" class="fc-tool" title="Anexo"><i class="fa-solid fa-paperclip"></i></button>
                            <button type="button" id="fale-btn-galeria" class="fc-tool" title="Galeria"><i class="fa-regular fa-image"></i></button>
                            <button type="button" id="fale-btn-emoji" class="fc-tool" title="Emoji"><i class="fa-regular fa-face-smile"></i></button>
                            <div id="fale-emoji" class="fc-emoji">
                                <?php foreach (['😀','😂','❤️','👍','🎉','🙏','👏','🔥','✅','😊','😮','😢'] as $emoji): ?>
                                    <button type="button" data-emoji="<?= $emoji ?>"><?= $emoji ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <textarea id="fale-mensagem" name="mensagem" rows="1" class="fc-input" placeholder="Digite uma mensagem..."></textarea>

                        <button type="button" id="fale-btn-mic" class="fc-mic" title="Gravar áudio" aria-label="Gravar áudio"><i class="fa-solid fa-microphone"></i></button>
                        <button type="submit" id="fale-btn-enviar" class="fc-send" title="Enviar" aria-label="Enviar" style="display:none;"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </form>
            <?php
            $ultimoMsgId = 0;
            foreach ($mensagens as $m) {
                $ultimoMsgId = max($ultimoMsgId, (int) ($m['id'] ?? 0));
            }
            ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
(function () {
    var FC = {
        conversaId: <?= (int) $conversaId ?>,
        baseUrl: <?= json_encode($baseUrl, JSON_UNESCAPED_UNICODE) ?>,
        csrf: <?= json_encode((string) ($csrf_token ?? ''), JSON_UNESCAPED_UNICODE) ?>,
        reacoesProntas: <?= $reacoesProntas ? 'true' : 'false' ?>,
        urlBase: <?= json_encode(rtrim((string) URL, '/'), JSON_UNESCAPED_UNICODE) ?>,
        ultimoMsgId: <?= (int) ($ultimoMsgId ?? 0) ?>,
        ultimoDia: <?= json_encode($diaAtual ?? '', JSON_UNESCAPED_UNICODE) ?>
    };

    var AUDIO_BARS = [8,14,10,18,12,16,9,15,11,17,13,10,16,12,14,9,18,11,15,10,13,17,12,16,9,14,11,15];
    var EXT_AUDIO = ['webm','weba','ogg','mp3','m4a','aac','wav'];

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function textoHtml(s) {
        return escHtml(s).replace(/\n/g, '<br>');
    }
    function urlAnexoJs(path) {
        path = (path || '').trim();
        if (path === '') return '';
        if (/^https?:\/\//i.test(path)) return path;
        return FC.urlBase + '/' + path.replace(/^\//, '');
    }
    function parseDataIso(created) {
        if (!created) return { dia: '', hora: '', ts: NaN };
        var ts = Date.parse(String(created).replace(' ', 'T'));
        if (isNaN(ts)) return { dia: '', hora: '', ts: NaN };
        var d = new Date(ts);
        var dia = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        var hora = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        return { dia: dia, hora: hora, ts: ts, date: d };
    }
    function fmtDiaDivisorJs(created) {
        var p = parseDataIso(created);
        if (!p.date) return '';
        var meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        var hoje = new Date();
        var ontem = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() - 1);
        var d = p.date;
        var diaChave = p.dia;
        var hojeChave = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-' + String(hoje.getDate()).padStart(2, '0');
        var ontemChave = ontem.getFullYear() + '-' + String(ontem.getMonth() + 1).padStart(2, '0') + '-' + String(ontem.getDate()).padStart(2, '0');
        var j = d.getDate();
        var mes = meses[d.getMonth()];
        if (diaChave === hojeChave) return 'Hoje, ' + j + ' de ' + mes;
        if (diaChave === ontemChave) return 'Ontem, ' + j + ' de ' + mes;
        return j + ' de ' + mes + ' de ' + d.getFullYear();
    }
    function fmtHoraListaJs(created) {
        var p = parseDataIso(created);
        if (!p.date) return '';
        var hoje = new Date();
        var hojeChave = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-' + String(hoje.getDate()).padStart(2, '0');
        if (p.dia === hojeChave) return p.hora;
        var ontem = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() - 1);
        var ontemChave = ontem.getFullYear() + '-' + String(ontem.getMonth() + 1).padStart(2, '0') + '-' + String(ontem.getDate()).padStart(2, '0');
        if (p.dia === ontemChave) return 'Ontem';
        return String(p.date.getDate()).padStart(2, '0') + '/' + String(p.date.getMonth() + 1).padStart(2, '0');
    }
    function resolverAnexo(m) {
        var anexoTipo = (m.anexo_tipo || '').toString();
        var anexoMime = (m.anexo_mime || '').toString();
        var path = (m.imagem || m.url || '').toString();
        var anexoNome = (m.imagem_nome || 'arquivo').toString();
        if (anexoTipo === '' && path !== '') {
            anexoTipo = 'imagem';
        }
        var ext = '';
        var base = path || anexoNome;
        var dot = base.lastIndexOf('.');
        if (dot !== -1) ext = base.slice(dot + 1).toLowerCase();
        var extAudio = EXT_AUDIO.indexOf(ext) !== -1;
        if (anexoTipo === '' && (path !== '' || anexoMime.indexOf('image/') === 0)) {
            if (path && !anexoTipo) anexoTipo = m.imagem ? (anexoMime.indexOf('image/') === 0 ? 'imagem' : '') : '';
        }
        if (anexoTipo === '' && (anexoMime.indexOf('audio/') === 0 || extAudio)) anexoTipo = 'audio';
        if (anexoTipo === 'arquivo' && (anexoMime.indexOf('audio/') === 0 || extAudio)) anexoTipo = 'audio';
        if (anexoTipo === 'audio' && anexoMime === '') {
            var mimeMap = { m4a: 'audio/mp4', mp4: 'audio/mp4', mp3: 'audio/mpeg', ogg: 'audio/ogg', wav: 'audio/wav', aac: 'audio/aac', weba: 'audio/webm', webm: 'audio/webm' };
            anexoMime = mimeMap[ext] || 'audio/webm';
        }
        var anexoUrl = urlAnexoJs(path);
        if (!anexoTipo && anexoUrl && anexoMime.indexOf('image/') === 0) anexoTipo = 'imagem';
        if (!anexoTipo && anexoUrl && anexoMime.indexOf('video/') === 0) anexoTipo = 'video';
        return { anexoTipo: anexoTipo, anexoMime: anexoMime, anexoUrl: anexoUrl, anexoNome: anexoNome };
    }

    function fmtAudio(s) {
        s = Math.max(0, Math.floor(s || 0));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }
    var audioAtual = null;
    function pararAudioTodos(exceto) {
        document.querySelectorAll('[data-fc-audio]').forEach(function (wrap) {
            if (exceto && wrap === exceto) return;
            var a = wrap.querySelector('audio');
            var btn = wrap.querySelector('.fc-audio-play');
            if (a && !a.paused) a.pause();
            if (btn) btn.innerHTML = '<i class="fa-solid fa-play"></i>';
        });
    }
    function atualizarPlayerAudio(wrap) {
        var audio = wrap.querySelector('audio');
        var timeEl = wrap.querySelector('.fc-audio-time');
        var scrub = wrap.querySelector('.fc-audio-scrub');
        var bars = wrap.querySelectorAll('.fc-audio-bars span');
        if (!audio || !timeEl) return;
        var dur = isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 0;
        var cur = audio.currentTime || 0;
        var pct = dur > 0 ? Math.min(100, (cur / dur) * 100) : 0;
        if (scrub) scrub.style.left = pct + '%';
        var done = bars.length ? Math.round((pct / 100) * bars.length) : 0;
        bars.forEach(function (bar, i) {
            if (i < done) bar.classList.add('is-done');
            else bar.classList.remove('is-done');
        });
        if (timeEl.getAttribute('data-fc-indisp') === '1') return;
        if (dur > 0 && !audio.paused) timeEl.textContent = fmtAudio(cur);
        else if (dur > 0) timeEl.textContent = fmtAudio(dur);
        else timeEl.textContent = fmtAudio(cur);
    }
    function garantirSrcAudio(wrap, audio) {
        var src = wrap.getAttribute('data-src') || '';
        if (src && (!audio.src || audio.src.indexOf(src) === -1)) {
            audio.src = src;
        }
    }
    function initAudioPlayer(wrap) {
        if (!wrap || wrap.getAttribute('data-fc-audio-init') === '1') return;
        wrap.setAttribute('data-fc-audio-init', '1');
        var audio = wrap.querySelector('audio');
        var btn = wrap.querySelector('.fc-audio-play');
        var track = wrap.querySelector('[data-fc-seek]');
        if (!audio || !btn) return;
        garantirSrcAudio(wrap, audio);

        audio.addEventListener('loadedmetadata', function () { atualizarPlayerAudio(wrap); });
        audio.addEventListener('durationchange', function () { atualizarPlayerAudio(wrap); });
        audio.addEventListener('timeupdate', function () { atualizarPlayerAudio(wrap); });
        audio.addEventListener('ended', function () {
            btn.innerHTML = '<i class="fa-solid fa-play"></i>';
            audio.currentTime = 0;
            atualizarPlayerAudio(wrap);
            if (audioAtual === audio) audioAtual = null;
        });
        audio.addEventListener('error', function () {
            var timeEl = wrap.querySelector('.fc-audio-time');
            if (timeEl) {
                timeEl.textContent = 'indisponível';
                timeEl.setAttribute('data-fc-indisp', '1');
            }
        });

        btn.addEventListener('click', function () {
            garantirSrcAudio(wrap, audio);
            var timeEl = wrap.querySelector('.fc-audio-time');
            if (timeEl) timeEl.removeAttribute('data-fc-indisp');
            if (audio.paused) {
                pararAudioTodos(wrap);
                var playPromise = audio.play();
                if (playPromise && typeof playPromise.then === 'function') {
                    playPromise.then(function () {
                        audioAtual = audio;
                        btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
                        atualizarPlayerAudio(wrap);
                    }).catch(function () {
                        btn.innerHTML = '<i class="fa-solid fa-play"></i>';
                        if (timeEl) {
                            timeEl.textContent = 'indisponível';
                            timeEl.setAttribute('data-fc-indisp', '1');
                        }
                    });
                } else {
                    audioAtual = audio;
                    btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
                }
            } else {
                audio.pause();
                btn.innerHTML = '<i class="fa-solid fa-play"></i>';
                atualizarPlayerAudio(wrap);
            }
        });

        if (track) {
            track.addEventListener('click', function (e) {
                garantirSrcAudio(wrap, audio);
                if (!isFinite(audio.duration) || audio.duration <= 0) return;
                var rect = track.getBoundingClientRect();
                var ratio = rect.width > 0 ? (e.clientX - rect.left) / rect.width : 0;
                ratio = Math.max(0, Math.min(1, ratio));
                audio.currentTime = audio.duration * ratio;
                atualizarPlayerAudio(wrap);
            });
        }
    }

    function htmlAudioPlayer(anexoUrl, anexoMime) {
        var bars = AUDIO_BARS.map(function (h) {
            return '<span style="height:' + h + 'px"></span>';
        }).join('');
        var mime = anexoMime || 'audio/webm';
        return '<div class="fc-audio" data-fc-audio data-src="' + escHtml(anexoUrl) + '" data-mime="' + escHtml(mime) + '">' +
            '<button type="button" class="fc-audio-play" aria-label="Reproduzir áudio"><i class="fa-solid fa-play"></i></button>' +
            '<div class="fc-audio-body">' +
            '<div class="fc-audio-track" data-fc-seek>' +
            '<div class="fc-audio-bars">' + bars + '</div>' +
            '<div class="fc-audio-scrub" style="left:0%"></div></div>' +
            '<span class="fc-audio-time">0:00</span></div>' +
            '<audio preload="metadata" playsinline src="' + escHtml(anexoUrl) + '"></audio></div>';
    }

    function renderMensagem(m) {
        var daEscola = (m.autor_tipo || '') === 'escola';
        var msgId = parseInt(m.id, 10) || 0;
        var created = (m.created_at || '').toString();
        var hora = parseDataIso(created).hora;
        var an = resolverAnexo(m);
        var curtidas = parseInt(m.curtidas, 10) || 0;
        var euCurti = !!m.eu_curti;
        var lidaFamilia = !!(m.lido_familia_em);

        var html = '<div id="msg-' + msgId + '" class="fc-row ' + (daEscola ? 'is-escola' : 'is-familia') + '">' +
            '<div class="fc-bubble ' + (daEscola ? 'is-escola' : 'is-familia') + '">' +
            '<span class="fc-tag ' + (daEscola ? 'is-escola' : 'is-familia') + '">' + (daEscola ? 'Escola' : 'Família') + '</span>';

        if ((m.mensagem || '').toString().trim() !== '') {
            html += '<div class="fc-text">' + textoHtml(m.mensagem) + '</div>';
        }
        if (an.anexoUrl && an.anexoTipo === 'imagem') {
            html += '<a href="' + escHtml(an.anexoUrl) + '" target="_blank" rel="noopener" class="block mt-2">' +
                '<img src="' + escHtml(an.anexoUrl) + '" alt="Imagem" style="max-height:220px;border-radius:10px;display:block;"></a>';
        } else if (an.anexoUrl && an.anexoTipo === 'video') {
            var vMime = an.anexoMime || 'video/mp4';
            html += '<video controls style="margin-top:8px;max-height:220px;width:100%;border-radius:10px;background:#000;" preload="metadata">' +
                '<source src="' + escHtml(an.anexoUrl) + '" type="' + escHtml(vMime) + '"></video>';
        } else if (an.anexoUrl && an.anexoTipo === 'audio') {
            html += htmlAudioPlayer(an.anexoUrl, an.anexoMime);
        } else if (an.anexoUrl) {
            html += '<a href="' + escHtml(an.anexoUrl) + '" target="_blank" rel="noopener" ' +
                'style="margin-top:8px;display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;color:#1e293b;text-decoration:none;">' +
                '<i class="fa-regular fa-file"></i> ' + escHtml(an.anexoNome) + '</a>';
        }

        if (curtidas > 0 || (FC.reacoesProntas && msgId > 0)) {
            html += '<div class="fc-react">';
            if (curtidas > 0) html += '<span class="fc-like-count">👍 ' + curtidas + '</span>';
            if (FC.reacoesProntas && msgId > 0) {
                html += '<form method="post" action="' + escHtml(FC.baseUrl + '/' + FC.conversaId + '/mensagens/' + msgId + '/curtir') + '" class="fc-like-form" style="display:inline;margin:0;">' +
                    '<input type="hidden" name="_token" value="' + escHtml(FC.csrf) + '">' +
                    '<button type="submit" class="fc-like ' + (euCurti ? 'is-on' : '') + '" title="' + (euCurti ? 'Remover curtida' : 'Curtir') + '">' +
                    '<i class="fa-' + (euCurti ? 'solid' : 'regular') + ' fa-thumbs-up"></i></button></form>';
            }
            html += '</div>';
        }

        html += '<div class="fc-meta"><span class="fc-time">' + escHtml(hora) + '</span>';
        if (daEscola) {
            html += '<span class="fc-checks" title="' + (lidaFamilia ? 'Lida pela família' : 'Enviada') + '">' +
                '<i class="fa-solid fa-check' + (lidaFamilia ? '-double' : '') + '"></i></span>';
        }
        html += '</div></div></div>';
        return html;
    }

    var box = document.getElementById('fale-chat-mensagens');
    var renderedIds = new Set();
    var ultimoDia = FC.ultimoDia || '';
    var lastMsgId = FC.ultimoMsgId || 0;
    var enviando = false;
    var pendingSendAposGravacao = false;
    var autoEnviarGravacao = false;
    var pollTimer = null;

    if (box) {
        box.querySelectorAll('.fc-row[id^="msg-"]').forEach(function (row) {
            var id = parseInt(row.id.replace('msg-', ''), 10);
            if (id > 0) renderedIds.add(id);
        });
        var ultimoDiaEl = box.querySelector('.fc-day:last-of-type');
        if (ultimoDiaEl && ultimoDiaEl.getAttribute('data-fc-dia')) {
            ultimoDia = ultimoDiaEl.getAttribute('data-fc-dia');
        }
        box.querySelectorAll('[data-fc-audio]').forEach(function (w) { initAudioPlayer(w); });
        var hash = window.location.hash;
        if (hash && document.querySelector(hash)) document.querySelector(hash).scrollIntoView({ block: 'center' });
        else box.scrollTop = box.scrollHeight;
    }

    function removerVazio() {
        var v = document.getElementById('fale-chat-vazio');
        if (v) v.remove();
    }
    function scrollBottom() {
        if (!box) return;
        requestAnimationFrame(function () { box.scrollTop = box.scrollHeight; });
    }
    function inserirDiaSePreciso(created) {
        var dia = parseDataIso(created).dia;
        if (!dia || dia === ultimoDia) return;
        ultimoDia = dia;
        var div = document.createElement('div');
        div.className = 'fc-day';
        div.setAttribute('data-fc-dia', dia);
        div.innerHTML = '<span>' + escHtml(fmtDiaDivisorJs(created)) + '</span>';
        box.appendChild(div);
    }
    function previewListaUltima(m) {
        var item = document.querySelector('.fc-item.is-on');
        if (!item) return;
        var last = item.querySelector('.fc-item-last');
        var time = item.querySelector('.fc-item-time');
        if (last) {
            var txt = (m.mensagem || '').toString().trim();
            if (!txt) txt = 'Anexo';
            last.textContent = txt.length > 80 ? txt.slice(0, 80) + '…' : txt;
        }
        if (time && m.created_at) time.textContent = fmtHoraListaJs(m.created_at);
    }
    function appendMensagem(m, opts) {
        opts = opts || {};
        var msgId = parseInt(m.id, 10) || 0;
        if (msgId <= 0 || renderedIds.has(msgId)) return false;
        renderedIds.add(msgId);
        if (msgId > lastMsgId) lastMsgId = msgId;
        removerVazio();
        inserirDiaSePreciso(m.created_at);
        var tmp = document.createElement('div');
        tmp.innerHTML = renderMensagem(m);
        var row = tmp.firstElementChild;
        if (!row) return false;
        if (opts.highlightFamilia && (m.autor_tipo || '') === 'responsavel') {
            row.classList.add('fc-new-in');
            setTimeout(function () { row.classList.remove('fc-new-in'); }, 1600);
        }
        box.appendChild(row);
        var audioWrap = row.querySelector('[data-fc-audio]');
        if (audioWrap) initAudioPlayer(audioWrap);
        return true;
    }

    function atualizarChecks(msgId, lido) {
        var row = document.getElementById('msg-' + msgId);
        if (!row) return;
        var checks = row.querySelector('.fc-checks i');
        if (!checks) return;
        checks.className = 'fa-solid fa-check' + (lido ? '-double' : '');
        row.querySelector('.fc-checks').title = lido ? 'Lida pela família' : 'Enviada';
    }

    function enviarMensagemAjax() {
        var form = document.getElementById('fale-chat-form');
        if (!form || enviando) return Promise.resolve();
        if (!temConteudo()) return Promise.resolve();
        enviando = true;
        if (btnEnviar) btnEnviar.disabled = true;

        var fd = new FormData(form);
        fd.append('ajax', '1');
        return fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
            .then(function (data) {
                enviando = false;
                if (btnEnviar) btnEnviar.disabled = false;
                if (!data || !data.ok) {
                    alert((data && data.erro) ? data.erro : 'Não foi possível enviar a mensagem.');
                    return;
                }
                if (data.mensagem) {
                    appendMensagem(data.mensagem, { highlightFamilia: false });
                    previewListaUltima(data.mensagem);
                }
                mensagem.value = '';
                autoResize();
                limparAnexo();
                scrollBottom();
            })
            .catch(function () {
                enviando = false;
                if (btnEnviar) btnEnviar.disabled = false;
                alert('Falha de rede ao enviar. Tente novamente.');
            });
    }

    function pollMensagens() {
        if (!box || !FC.conversaId || document.hidden) return;
        var url = FC.baseUrl + '/' + FC.conversaId + '/mensagens?depois_de=' + encodeURIComponent(String(lastMsgId));
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok || !Array.isArray(data.mensagens)) return;
                var added = false;
                var nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
                data.mensagens.forEach(function (m) {
                    if (appendMensagem(m, { highlightFamilia: true })) added = true;
                    if ((m.autor_tipo || '') === 'escola' && m.lido_familia_em) {
                        atualizarChecks(parseInt(m.id, 10), true);
                    }
                });
                if (added && nearBottom) scrollBottom();
            })
            .catch(function () { /* silencioso */ });
    }

    function iniciarPolling() {
        if (!FC.conversaId || !box) return;
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(pollMensagens, 3000);
    }
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollMensagens();
    });

    if (box && FC.conversaId) {
        box.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.classList || !form.classList.contains('fc-like-form')) return;
            e.preventDefault();
            var react = form.closest('.fc-react');
            var fd = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    var btn = form.querySelector('.fc-like');
                    var liked = !!data.liked;
                    var count = parseInt(data.curtidas, 10) || 0;
                    if (btn) {
                        btn.classList.toggle('is-on', liked);
                        btn.title = liked ? 'Remover curtida' : 'Curtir';
                        var icon = btn.querySelector('i');
                        if (icon) icon.className = 'fa-' + (liked ? 'solid' : 'regular') + ' fa-thumbs-up';
                    }
                    if (!react) return;
                    var span = react.querySelector('.fc-like-count');
                    if (count > 0) {
                        if (!span) {
                            span = document.createElement('span');
                            span.className = 'fc-like-count';
                            react.insertBefore(span, form);
                        }
                        span.textContent = '👍 ' + count;
                    } else if (span) {
                        span.remove();
                    }
                })
                .catch(function () { /* silencioso */ });
        });
        iniciarPolling();
    }

    var btnInfo = document.getElementById('fc-btn-info');
    if (btnInfo) {
        btnInfo.addEventListener('click', function () {
            var aluno = btnInfo.getAttribute('data-aluno') || 'Aluno';
            var resp = btnInfo.getAttribute('data-responsavel') || 'Responsável';
            alert('Conversa com ' + resp + ' — aluno ' + aluno);
        });
    }

    var busca = document.getElementById('fc-busca');
    if (busca) {
        busca.addEventListener('input', function () {
            var q = (busca.value || '').toLowerCase().trim();
            document.querySelectorAll('[data-fc-item]').forEach(function (el) {
                var hay = el.getAttribute('data-fc-busca') || '';
                el.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
    document.querySelectorAll('[data-fc-filtro]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-fc-filtro]').forEach(function (b) { b.classList.remove('is-on'); });
            btn.classList.add('is-on');
        });
    });

    var form = document.getElementById('fale-chat-form');
    if (!form) return;

    var input = document.getElementById('fale-anexo-input');
    var preview = document.getElementById('fale-anexo-preview');
    var previewNome = document.getElementById('fale-anexo-nome');
    var btnRemover = document.getElementById('fale-anexo-remover');
    var btnMais = document.getElementById('fale-btn-mais');
    var menuAnexo = document.getElementById('fale-menu-anexo');
    var btnClip = document.getElementById('fale-btn-clip');
    var btnGaleria = document.getElementById('fale-btn-galeria');
    var btnEmoji = document.getElementById('fale-btn-emoji');
    var emojiBox = document.getElementById('fale-emoji');
    var mensagem = document.getElementById('fale-mensagem');
    var btnMic = document.getElementById('fale-btn-mic');
    var btnEnviar = document.getElementById('fale-btn-enviar');
    var gravandoBox = document.getElementById('fale-gravando');
    var gravandoTimer = document.getElementById('fale-gravando-timer');
    var btnCancelarGravacao = document.getElementById('fale-gravando-cancelar');
    var btnPararGravacao = document.getElementById('fale-gravando-parar');

    var mediaRecorder = null, chunks = [], gravando = false, cancelarProximo = false, timerId = null, segundos = 0;

    function temConteudo() {
        return (mensagem.value || '').trim() !== '' || (input.files && input.files.length > 0);
    }
    function atualizarAcoes() {
        if (gravando) { btnMic.style.display = 'none'; btnEnviar.style.display = 'none'; return; }
        if (temConteudo()) { btnMic.style.display = 'none'; btnEnviar.style.display = 'inline-flex'; }
        else { btnEnviar.style.display = 'none'; btnMic.style.display = 'inline-flex'; }
    }
    function mostrarPreview(nome) {
        previewNome.textContent = nome || 'Anexo selecionado';
        preview.classList.add('is-on');
        atualizarAcoes();
    }
    function limparAnexo() {
        input.value = '';
        preview.classList.remove('is-on');
        atualizarAcoes();
    }
    function autoResize() {
        mensagem.style.height = 'auto';
        mensagem.style.height = Math.min(mensagem.scrollHeight, 110) + 'px';
    }
    function formatarTempo(s) {
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }
    function pararTimer() { if (timerId) clearInterval(timerId); timerId = null; segundos = 0; }

    function abrirAnexo(accept) {
        input.accept = accept || 'image/*,video/*,application/pdf,audio/*';
        menuAnexo.classList.remove('is-open');
        emojiBox.classList.remove('is-open');
        input.click();
    }

    btnMais.addEventListener('click', function (e) {
        e.stopPropagation();
        emojiBox.classList.remove('is-open');
        menuAnexo.classList.toggle('is-open');
    });
    btnClip.addEventListener('click', function () { abrirAnexo('image/*,video/mp4,video/webm,application/pdf'); });
    btnGaleria.addEventListener('click', function () { abrirAnexo('image/*'); });
    btnEmoji.addEventListener('click', function (e) {
        e.stopPropagation();
        menuAnexo.classList.remove('is-open');
        emojiBox.classList.toggle('is-open');
    });
    document.addEventListener('click', function () {
        menuAnexo.classList.remove('is-open');
        emojiBox.classList.remove('is-open');
    });
    menuAnexo.addEventListener('click', function (e) { e.stopPropagation(); });
    emojiBox.addEventListener('click', function (e) { e.stopPropagation(); });
    menuAnexo.querySelectorAll('[data-fale-attach]').forEach(function (btn) {
        btn.addEventListener('click', function () { abrirAnexo(btn.getAttribute('data-fale-attach')); });
    });
    emojiBox.querySelectorAll('[data-emoji]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            mensagem.value += btn.getAttribute('data-emoji');
            emojiBox.classList.remove('is-open');
            autoResize();
            atualizarAcoes();
            mensagem.focus();
        });
    });

    input.addEventListener('change', function () {
        if (input.files && input.files[0]) mostrarPreview(input.files[0].name);
        else limparAnexo();
    });
    btnRemover.addEventListener('click', limparAnexo);
    mensagem.addEventListener('input', function () { autoResize(); atualizarAcoes(); });

    function extensaoAudio(mimeType) {
        var m = (mimeType || '').toLowerCase();
        if (m.indexOf('mp4') !== -1 || m.indexOf('m4a') !== -1 || m.indexOf('aac') !== -1) return 'm4a';
        if (m.indexOf('ogg') !== -1) return 'ogg';
        if (m.indexOf('mpeg') !== -1 || m.indexOf('mp3') !== -1) return 'mp3';
        return 'webm';
    }
    function setArquivoAudio(blob) {
        var tipo = blob.type || 'audio/webm';
        var file = new File([blob], 'audio-' + Date.now() + '.' + extensaoAudio(tipo), { type: tipo });
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        mostrarPreview('Áudio gravado (' + formatarTempo(Math.max(segundos, 1)) + ')');
    }
    async function iniciarGravacao() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Seu navegador não permite gravar áudio.');
            return;
        }
        try {
            var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            chunks = []; cancelarProximo = false;
            var mime = '';
            if (window.MediaRecorder && MediaRecorder.isTypeSupported) {
                if (MediaRecorder.isTypeSupported('audio/mp4')) mime = 'audio/mp4';
                else if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) mime = 'audio/webm;codecs=opus';
                else if (MediaRecorder.isTypeSupported('audio/webm')) mime = 'audio/webm';
                else if (MediaRecorder.isTypeSupported('audio/ogg')) mime = 'audio/ogg';
            }
            mediaRecorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
            mediaRecorder.ondataavailable = function (ev) { if (ev.data && ev.data.size > 0) chunks.push(ev.data); };
            mediaRecorder.onstop = function () {
                stream.getTracks().forEach(function (t) { t.stop(); });
                gravando = false;
                gravandoBox.classList.remove('is-on');
                btnMais.disabled = false;
                var blobType = (mediaRecorder.mimeType || mime || 'audio/webm').split(';')[0];
                if (!cancelarProximo && chunks.length) setArquivoAudio(new Blob(chunks, { type: blobType }));
                else limparAnexo();
                pararTimer();
                atualizarAcoes();
                if (autoEnviarGravacao && !cancelarProximo && chunks.length) {
                    autoEnviarGravacao = false;
                    enviarMensagemAjax();
                } else if (pendingSendAposGravacao && !cancelarProximo && chunks.length) {
                    pendingSendAposGravacao = false;
                    enviarMensagemAjax();
                }
            };
            // timeslice evita blob vazio/mudo em alguns browsers
            mediaRecorder.start(250);
            gravando = true;
            gravandoBox.classList.add('is-on');
            btnMais.disabled = true;
            menuAnexo.classList.remove('is-open');
            segundos = 0;
            gravandoTimer.textContent = '0:00';
            timerId = setInterval(function () { segundos += 1; gravandoTimer.textContent = formatarTempo(segundos); }, 1000);
            atualizarAcoes();
        } catch (err) {
            alert('Não foi possível acessar o microfone. Verifique a permissão do navegador.');
        }
    }
    function pararGravacao(cancelar) {
        cancelarProximo = !!cancelar;
        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    }

    btnMic.addEventListener('click', function () { if (!gravando) iniciarGravacao(); });
    btnCancelarGravacao.addEventListener('click', function () { pararGravacao(true); });
    btnPararGravacao.addEventListener('click', function () {
        autoEnviarGravacao = true;
        pararGravacao(false);
    });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (gravando) {
            pendingSendAposGravacao = true;
            pararGravacao(false);
            return;
        }
        if (!temConteudo()) return;
        enviarMensagemAjax();
    });

    atualizarAcoes();
    autoResize();
})();
</script>
