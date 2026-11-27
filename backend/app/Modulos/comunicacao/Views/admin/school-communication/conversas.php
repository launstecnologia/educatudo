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

$renderAvatares = static function (string $fotoResp, string $fotoAluno, string $nomeResp, string $nomeAluno, bool $expandirAluno = false) use ($iniciais): void {
    $podeExpandir = $expandirAluno && $fotoAluno !== '';
    ?>
    <div class="fc-avatars" title="<?= htmlspecialchars($nomeResp . ' · aluno ' . $nomeAluno, ENT_QUOTES, 'UTF-8') ?>">
        <div class="fc-avatar">
            <?php if ($fotoResp !== ''): ?>
                <img src="<?= htmlspecialchars($fotoResp) ?>" alt="" onerror="this.remove()">
            <?php else: ?>
                <?= htmlspecialchars($iniciais($nomeResp)) ?>
            <?php endif; ?>
        </div>
        <div class="fc-avatar fc-avatar-aluno<?= $podeExpandir ? ' is-expandable' : '' ?>"
            <?php if ($podeExpandir): ?>
                role="button"
                tabindex="0"
                title="Ver foto do aluno"
                aria-label="Ver foto de <?= htmlspecialchars($nomeAluno, ENT_QUOTES, 'UTF-8') ?>"
                data-fc-expand-foto="<?= htmlspecialchars($fotoAluno, ENT_QUOTES, 'UTF-8') ?>"
                data-fc-expand-nome="<?= htmlspecialchars($nomeAluno, ENT_QUOTES, 'UTF-8') ?>"
            <?php endif; ?>>
            <?php if ($fotoAluno !== ''): ?>
                <img src="<?= htmlspecialchars($fotoAluno) ?>" alt="" onerror="this.remove()">
            <?php else: ?>
                <?= htmlspecialchars($iniciais($nomeAluno)) ?>
            <?php endif; ?>
            <?php if ($podeExpandir): ?>
                <span class="fc-avatar-zoom" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
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
.fc-avatar-aluno.is-expandable {
  cursor:zoom-in; position:relative;
}
.fc-avatar-aluno.is-expandable:hover,
.fc-avatar-aluno.is-expandable:focus {
  outline:2px solid #2f6fed; outline-offset:1px;
}
.fc-avatar-zoom {
  position:absolute; inset:0; display:none; align-items:center; justify-content:center;
  background:rgba(15,23,42,.45); color:#fff; font-size:9px; border-radius:999px;
}
.fc-head .fc-avatar-aluno.is-expandable:hover .fc-avatar-zoom,
.fc-head .fc-avatar-aluno.is-expandable:focus .fc-avatar-zoom { display:flex; }
.fc-head-meta { min-width:0; }
.fc-head-turma {
  display:inline-flex; align-items:center; gap:5px; margin-top:3px;
  font-size:11px; font-weight:600; color:#475569;
  background:#eef2f7; border-radius:999px; padding:2px 8px; max-width:100%;
}
.fc-head-turma i { color:#64748b; font-size:10px; }
.fc-lightbox {
  display:none; position:fixed; inset:0; z-index:80;
  align-items:center; justify-content:center; padding:24px;
  background:rgba(15,23,42,.72);
}
.fc-lightbox.is-open { display:flex; }
.fc-lightbox-card {
  position:relative; max-width:min(92vw,420px); width:100%;
  background:#fff; border-radius:18px; overflow:hidden;
  box-shadow:0 24px 48px rgba(15,23,42,.28);
}
.fc-lightbox-card img {
  display:block; width:100%; max-height:min(70vh,520px); object-fit:cover; background:#e2e8f0;
}
.fc-lightbox-caption {
  padding:12px 16px; font-size:14px; font-weight:700; color:#0f172a;
  border-top:1px solid #e6ebf2;
}
.fc-lightbox-close {
  position:absolute; top:10px; right:10px; width:36px; height:36px;
  border:0; border-radius:999px; background:rgba(15,23,42,.55); color:#fff;
  cursor:pointer; display:inline-flex; align-items:center; justify-content:center;
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
.fc-checks { color:#94a3b8; font-size:13px; letter-spacing:-1px; }
.fc-checks.is-read { color:#53bdeb; }
.fc-checks i { font-size:13px; }
.fc-react {
  display:inline-flex; align-items:center; gap:4px; margin-top:6px;
  background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:2px 8px; font-size:11px;
}
.fc-like {
  border:0; background:transparent; color:#64748b; cursor:pointer; padding:0; font-size:12px;
}
.fc-like.is-on { color:#0d9488; }
.fc-quote {
  display:block; width:100%; text-align:left; border:0; cursor:pointer;
  margin:0 0 8px; padding:8px 10px; border-radius:10px;
  border-left:3px solid #64748b; background:rgba(15,23,42,.06);
}
.fc-bubble.is-escola .fc-quote { border-left-color:#15803d; background:rgba(22,163,74,.12); }
.fc-bubble.is-familia .fc-quote { border-left-color:#2563eb; background:rgba(37,99,235,.08); }
.fc-quote-who { display:block; font-size:11px; font-weight:700; color:#334155; margin-bottom:2px; }
.fc-quote-text {
  display:block; font-size:12px; color:#475569; line-height:1.35;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%;
}
.fc-reply {
  border:0; background:transparent; color:#94a3b8; cursor:pointer; padding:0 2px;
  font-size:11px; display:inline-flex; align-items:center; gap:4px;
}
.fc-reply:hover { color:#1e3a5f; }
.fc-reply-bar {
  display:none; margin:0 14px 8px; align-items:stretch; gap:10px;
  background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:8px 10px;
  border-left:3px solid #1e3a5f;
}
.fc-reply-bar.is-on { display:flex; }
.fc-reply-bar-main { min-width:0; flex:1; }
.fc-reply-bar-label { font-size:11px; font-weight:700; color:#1e3a5f; }
.fc-reply-bar-text {
  font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;
}
.fc-reply-bar-close {
  border:0; background:transparent; color:#94a3b8; cursor:pointer; padding:4px 6px; font-size:14px;
}

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
.fc-send:disabled, .fc-mic:disabled { opacity:.55; cursor:wait; }
.fc-send.is-loading { pointer-events:none; }
.fc-composer.is-sending { opacity:.92; }
.fc-composer.is-sending .fc-input { background:#eef2f7; }
.fc-composer.is-sending .fc-tool,
.fc-composer.is-sending .fc-plus { opacity:.45; pointer-events:none; }
@keyframes fc-spin { to { transform: rotate(360deg); } }
.fc-spin { animation: fc-spin .7s linear infinite; }
.fc-row.is-pending .fc-bubble { opacity:.88; }
.fc-row.is-pending .fc-checks { color:#94a3b8; }
.fc-row.is-failed .fc-bubble { outline:1px solid #fecaca; }
.fc-send-status {
  display:none; margin:0 14px 8px; align-items:center; gap:8px;
  background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:8px 12px;
  font-size:13px; color:#1d4ed8; font-weight:600;
}
.fc-send-status.is-on { display:flex; }
.fc-send-fail {
  margin-top:6px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;
  font-size:12px; color:#b91c1c;
}
.fc-send-fail button {
  border:0; background:#fee2e2; color:#b91c1c; border-radius:999px;
  padding:3px 10px; font-size:11px; font-weight:700; cursor:pointer;
}
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
                        $turmaNome = trim((string) ($item['turma_nome'] ?? ''));
                        $fotoResp = $urlMidia((string) ($item['responsavel_foto_url'] ?? ''));
                        $fotoAluno = $urlMidia((string) ($item['aluno_foto_url'] ?? ($item['foto_url'] ?? '')));
                        $busca = mb_strtolower($nomeAluno . ' ' . $nomeResp . ' ' . $turmaNome . ' ' . (string) ($item['ultima'] ?? ''));
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
                                <div class="fc-item-sub">Aluno: <?= htmlspecialchars($nomeAluno) ?><?= $turmaNome !== '' ? ' · ' . htmlspecialchars($turmaNome) : '' ?></div>
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
                $turmaNome = trim((string) ($conversa['turma_nome'] ?? ''));
                $fotoResp = $urlMidia((string) ($conversa['responsavel_foto_url'] ?? ''));
                $fotoAluno = $urlMidia((string) ($conversa['aluno_foto_url'] ?? ($conversa['foto_url'] ?? '')));
                ?>
                <div class="fc-head">
                    <div class="fc-head-left">
                        <?php $renderAvatares($fotoResp, $fotoAluno, $nomeResp, $nomeAluno, true); ?>
                        <div class="fc-head-meta">
                            <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($nomeResp) ?></p>
                            <p class="text-xs text-slate-500 truncate">Aluno: <?= htmlspecialchars($nomeAluno) ?></p>
                            <?php if ($turmaNome !== ''): ?>
                                <span class="fc-head-turma" title="Turma do aluno">
                                    <i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i>
                                    <span class="truncate"><?= htmlspecialchars($turmaNome) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="fc-head-turma" title="Sem turma vinculada" style="color:#94a3b8;font-weight:500;">
                                    <i class="fa-regular fa-circle-question" aria-hidden="true"></i>
                                    <span>Sem turma</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="fc-head-actions">
                        <?php if ($fotoAluno !== ''): ?>
                            <button type="button" class="fc-round" id="fc-btn-foto-aluno" title="Ver foto do aluno"
                                    data-fc-expand-foto="<?= htmlspecialchars($fotoAluno, ENT_QUOTES, 'UTF-8') ?>"
                                    data-fc-expand-nome="<?= htmlspecialchars($nomeAluno, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-regular fa-image"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" id="fc-btn-info" class="fc-round" title="Informações"
                                data-aluno="<?= htmlspecialchars($nomeAluno, ENT_QUOTES, 'UTF-8') ?>"
                                data-responsavel="<?= htmlspecialchars($nomeResp, ENT_QUOTES, 'UTF-8') ?>"
                                data-turma="<?= htmlspecialchars($turmaNome, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fa-solid fa-circle-info"></i>
                        </button>
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
                        $respostaId = (int) ($mensagem['resposta_a_mensagem_id'] ?? 0);
                        $respostaAutor = (string) ($mensagem['resposta_autor_tipo'] ?? '');
                        $respostaTexto = trim((string) ($mensagem['resposta_mensagem'] ?? ''));
                        $respostaAnexo = (string) ($mensagem['resposta_anexo_tipo'] ?? (!empty($mensagem['resposta_imagem']) ? 'imagem' : ''));
                        $respostaNome = (string) ($mensagem['resposta_imagem_nome'] ?? 'Anexo');
                        if ($respostaTexto === '' && $respostaAnexo !== '') {
                            $respostaTexto = match ($respostaAnexo) {
                                'audio' => 'Áudio',
                                'imagem' => 'Foto',
                                'video' => 'Vídeo',
                                default => $respostaNome !== '' ? $respostaNome : 'Anexo',
                            };
                        }
                        $previewResponder = trim((string) ($mensagem['mensagem'] ?? ''));
                        if ($previewResponder === '') {
                            $previewResponder = match ($anexoTipo) {
                                'audio' => 'Áudio',
                                'imagem' => 'Foto',
                                'video' => 'Vídeo',
                                default => $anexoPath !== '' ? ($anexoNome !== '' ? $anexoNome : 'Anexo') : '',
                            };
                        }
                        ?>
                        <?php if ($dia !== '' && $dia !== $diaAtual): ?>
                            <?php $diaAtual = $dia; ?>
                            <div class="fc-day" data-fc-dia="<?= htmlspecialchars($dia, ENT_QUOTES, 'UTF-8') ?>"><span><?= htmlspecialchars($fmtDiaDivisor($created)) ?></span></div>
                        <?php endif; ?>

                        <div id="msg-<?= $msgId ?>" class="fc-row <?= $daEscola ? 'is-escola' : 'is-familia' ?>"
                             data-fc-msg-id="<?= $msgId ?>"
                             data-fc-autor="<?= $daEscola ? 'escola' : 'familia' ?>"
                             data-fc-preview="<?= htmlspecialchars($previewResponder, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="fc-bubble <?= $daEscola ? 'is-escola' : 'is-familia' ?>">
                                <span class="fc-tag <?= $daEscola ? 'is-escola' : 'is-familia' ?>"><?= $daEscola ? 'Escola' : 'Família' ?></span>
                                <?php if ($respostaId > 0): ?>
                                    <button type="button" class="fc-quote" data-fc-goto="<?= $respostaId ?>" title="Ir para mensagem">
                                        <span class="fc-quote-who"><?= $respostaAutor === 'escola' ? 'Escola' : 'Família' ?></span>
                                        <span class="fc-quote-text"><?= htmlspecialchars($respostaTexto !== '' ? $respostaTexto : 'Mensagem') ?></span>
                                    </button>
                                <?php endif; ?>
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
                                    <?php
                                    $audioMimeUi = $anexoMime !== '' ? $anexoMime : 'audio/webm';
                                    if (str_starts_with(strtolower($audioMimeUi), 'video/')) {
                                        $audioMimeUi = preg_replace('#^video/#', 'audio/', strtolower($audioMimeUi)) ?: 'audio/webm';
                                    }
                                    ?>
                                    <div class="fc-audio" data-fc-audio data-src="<?= htmlspecialchars($anexoUrl, ENT_QUOTES, 'UTF-8') ?>" data-mime="<?= htmlspecialchars($audioMimeUi, ENT_QUOTES, 'UTF-8') ?>">
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
                                        <audio preload="none" playsinline src="<?= htmlspecialchars($anexoUrl) ?>"></audio>
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
                                    <?php if ($msgId > 0): ?>
                                        <button type="button" class="fc-reply" data-fc-reply="<?= $msgId ?>"
                                                data-fc-reply-autor="<?= $daEscola ? 'escola' : 'familia' ?>"
                                                data-fc-reply-preview="<?= htmlspecialchars($previewResponder, ENT_QUOTES, 'UTF-8') ?>"
                                                title="Responder">
                                            <i class="fa-solid fa-reply"></i> Responder
                                        </button>
                                    <?php endif; ?>
                                    <span class="fc-time"><?= $created !== '' ? date('H:i', strtotime($created)) : '' ?></span>
                                    <?php if ($daEscola): ?>
                                        <span class="fc-checks<?= $lidaFamilia ? ' is-read' : '' ?>"
                                              title="<?= $lidaFamilia ? 'Visualizada pela família' : 'Enviada' ?>">
                                            <i class="fa-solid fa-check-double"></i>
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
                <div id="fale-enviando" class="fc-send-status" aria-live="polite">
                    <i class="fa-solid fa-spinner fc-spin" aria-hidden="true"></i>
                    <span id="fale-enviando-texto">Enviando mensagem…</span>
                </div>
                <div id="fale-reply-bar" class="fc-reply-bar" aria-live="polite">
                    <div class="fc-reply-bar-main">
                        <div class="fc-reply-bar-label" id="fale-reply-label">Respondendo</div>
                        <div class="fc-reply-bar-text" id="fale-reply-text"></div>
                    </div>
                    <button type="button" id="fale-reply-cancelar" class="fc-reply-bar-close" title="Cancelar resposta" aria-label="Cancelar resposta">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form id="fale-chat-form" method="post" enctype="multipart/form-data" action="<?= $baseUrl ?>/<?= $conversaId ?>/responder" class="fc-composer-wrap">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <input type="hidden" name="resposta_a_mensagem_id" id="fale-reply-id" value="">
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

<?php if (is_array($conversa) && $conversa !== []): ?>
<?php
$infoAlunoNome = (string) ($conversa['aluno_nome'] ?? 'Aluno');
$infoRespNome = (string) ($conversa['responsavel_nome'] ?? 'Responsável');
$infoTurma = trim((string) ($conversa['turma_nome'] ?? ''));
$infoAlunoId = (int) ($conversa['aluno_id'] ?? 0);
$infoAlunoRa = trim((string) ($conversa['aluno_ra'] ?? ''));
$infoAlunoEmail = trim((string) ($conversa['aluno_email'] ?? ''));
$infoAlunoTel = trim((string) ($conversa['aluno_telefone'] ?? ''));
$infoAlunoCel = trim((string) ($conversa['aluno_celular'] ?? ''));
$infoFotoAluno = $urlMidia((string) ($conversa['aluno_foto_url'] ?? ($conversa['foto_url'] ?? '')));
$infoFotoResp = $urlMidia((string) ($conversa['responsavel_foto_url'] ?? ''));
$infoRespEmail = trim((string) ($conversa['responsavel_email'] ?? ''));
$infoRespTel = trim((string) ($conversa['responsavel_telefone'] ?? ''));
$infoRespCel = trim((string) ($conversa['responsavel_celular'] ?? ''));
$infoParentesco = trim((string) ($conversa['responsavel_parentesco'] ?? ''));
$infoIniciais = $iniciais;
$digitosFone = static function (string $raw): string {
    return preg_replace('/\D+/', '', $raw) ?: '';
};
$linkWhats = static function (string $raw) use ($digitosFone): string {
    $d = $digitosFone($raw);
    if ($d === '') {
        return '';
    }
    if (strlen($d) <= 11) {
        $d = '55' . $d;
    }
    return 'https://wa.me/' . $d;
};
$fonePrincipalResp = $infoRespCel !== '' ? $infoRespCel : $infoRespTel;
$waResp = $fonePrincipalResp !== '' ? $linkWhats($fonePrincipalResp) : '';
ob_start();
?>
<div class="flex-1 overflow-y-auto px-6 sm:px-8 py-5 space-y-6">
    <section>
        <div class="flex flex-col items-center text-center">
            <?php if ($infoFotoAluno !== ''): ?>
                <button type="button"
                        class="relative group rounded-2xl overflow-hidden w-40 h-40 shadow-md border border-slate-200 bg-slate-100"
                        data-fc-expand-foto="<?= htmlspecialchars($infoFotoAluno, ENT_QUOTES, 'UTF-8') ?>"
                        data-fc-expand-nome="<?= htmlspecialchars($infoAlunoNome, ENT_QUOTES, 'UTF-8') ?>"
                        title="Ampliar foto">
                    <img src="<?= htmlspecialchars($infoFotoAluno) ?>" alt="Foto de <?= htmlspecialchars($infoAlunoNome) ?>" class="w-full h-full object-cover">
                    <span class="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/35 transition flex items-center justify-center text-white opacity-0 group-hover:opacity-100">
                        <i class="fa-solid fa-magnifying-glass-plus text-lg"></i>
                    </span>
                </button>
            <?php else: ?>
                <div class="w-40 h-40 rounded-2xl bg-blue-100 text-blue-800 flex items-center justify-center text-3xl font-bold border border-blue-200">
                    <?= htmlspecialchars($infoIniciais($infoAlunoNome)) ?>
                </div>
            <?php endif; ?>
            <h3 class="mt-4 text-lg font-bold text-slate-900"><?= htmlspecialchars($infoAlunoNome) ?></h3>
            <?php if ($infoTurma !== ''): ?>
                <p class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 bg-slate-100 rounded-full px-3 py-1">
                    <i class="fa-solid fa-chalkboard-user text-slate-500"></i>
                    Turma <?= htmlspecialchars($infoTurma) ?>
                </p>
            <?php endif; ?>
        </div>

        <dl class="mt-5 space-y-3 text-sm">
            <?php if ($infoAlunoRa !== ''): ?>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">RA</dt>
                    <dd class="font-medium text-slate-900 text-right"><?= htmlspecialchars($infoAlunoRa) ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($infoAlunoEmail !== ''): ?>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">E-mail</dt>
                    <dd class="font-medium text-slate-900 text-right break-all">
                        <a href="mailto:<?= htmlspecialchars($infoAlunoEmail) ?>" class="text-blue-700 hover:underline"><?= htmlspecialchars($infoAlunoEmail) ?></a>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if ($infoAlunoCel !== '' || $infoAlunoTel !== ''): ?>
                <div class="flex justify-between gap-3 border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Telefone</dt>
                    <dd class="font-medium text-slate-900 text-right">
                        <?php $telAluno = $infoAlunoCel !== '' ? $infoAlunoCel : $infoAlunoTel; ?>
                        <a href="tel:<?= htmlspecialchars($digitosFone($telAluno)) ?>" class="hover:underline"><?= htmlspecialchars($telAluno) ?></a>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>

        <?php if ($infoAlunoId > 0): ?>
            <a href="<?= URL ?>/admin/students/<?= $infoAlunoId ?>"
               class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <i class="fa-regular fa-id-card"></i> Abrir ficha do aluno
            </a>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <h4 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-3">Responsável</h4>
        <div class="flex items-start gap-3">
            <div class="w-12 h-12 rounded-full overflow-hidden bg-blue-100 text-blue-800 flex items-center justify-center text-sm font-bold flex-shrink-0 border border-white shadow">
                <?php if ($infoFotoResp !== ''): ?>
                    <img src="<?= htmlspecialchars($infoFotoResp) ?>" alt="" class="w-full h-full object-cover" onerror="this.remove()">
                <?php else: ?>
                    <?= htmlspecialchars($infoIniciais($infoRespNome)) ?>
                <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($infoRespNome) ?></p>
                <?php if ($infoParentesco !== ''): ?>
                    <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($infoParentesco) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <dl class="mt-4 space-y-3 text-sm">
            <?php if ($infoRespCel !== ''): ?>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Celular / WhatsApp</dt>
                    <dd class="font-medium text-slate-900 text-right">
                        <a href="tel:<?= htmlspecialchars($digitosFone($infoRespCel)) ?>" class="hover:underline"><?= htmlspecialchars($infoRespCel) ?></a>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if ($infoRespTel !== '' && $infoRespTel !== $infoRespCel): ?>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Telefone</dt>
                    <dd class="font-medium text-slate-900 text-right">
                        <a href="tel:<?= htmlspecialchars($digitosFone($infoRespTel)) ?>" class="hover:underline"><?= htmlspecialchars($infoRespTel) ?></a>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if ($infoRespEmail !== ''): ?>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">E-mail</dt>
                    <dd class="font-medium text-slate-900 text-right break-all">
                        <a href="mailto:<?= htmlspecialchars($infoRespEmail) ?>" class="text-blue-700 hover:underline"><?= htmlspecialchars($infoRespEmail) ?></a>
                    </dd>
                </div>
            <?php endif; ?>
            <?php if ($infoRespCel === '' && $infoRespTel === '' && $infoRespEmail === ''): ?>
                <p class="text-slate-500 text-sm">Nenhum contato cadastrado para este responsável.</p>
            <?php endif; ?>
        </dl>

        <?php if ($waResp !== '' || $fonePrincipalResp !== '' || $infoRespEmail !== ''): ?>
            <div class="mt-4 flex flex-wrap gap-2">
                <?php if ($fonePrincipalResp !== ''): ?>
                    <a href="tel:<?= htmlspecialchars($digitosFone($fonePrincipalResp)) ?>"
                       class="inline-flex items-center gap-2 rounded-full bg-white border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                        <i class="fa-solid fa-phone text-emerald-600"></i> Ligar
                    </a>
                <?php endif; ?>
                <?php if ($waResp !== ''): ?>
                    <a href="<?= htmlspecialchars($waResp) ?>" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100">
                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                    </a>
                <?php endif; ?>
                <?php if ($infoRespEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($infoRespEmail) ?>"
                       class="inline-flex items-center gap-2 rounded-full bg-white border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                        <i class="fa-regular fa-envelope text-blue-600"></i> E-mail
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'fcInfo';
$ui_offcanvas_titulo = 'Informações da conversa';
$ui_offcanvas_max_w = 'max-w-md';
include dirname(__DIR__, 5) . '/Views/admin/_partials/ui/offcanvas.php';
?>
<?php endif; ?>

<div id="fc-lightbox" class="fc-lightbox" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Foto do aluno">
    <div class="fc-lightbox-card">
        <button type="button" class="fc-lightbox-close" id="fc-lightbox-close" title="Fechar" aria-label="Fechar">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="fc-lightbox-img" src="" alt="">
        <div class="fc-lightbox-caption" id="fc-lightbox-caption"></div>
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
        // MediaRecorder/webm às vezes chega como video/webm — trata como áudio
        if ((anexoTipo === 'video' || anexoTipo === '') && extAudio && (ext === 'webm' || ext === 'weba' || ext === 'm4a' || ext === 'ogg')) {
            anexoTipo = 'audio';
        }
        if (anexoTipo === 'audio') {
            if (anexoMime === '' || anexoMime.indexOf('video/') === 0) {
                var mimeMap = { m4a: 'audio/mp4', mp4: 'audio/mp4', mp3: 'audio/mpeg', ogg: 'audio/ogg', wav: 'audio/wav', aac: 'audio/aac', weba: 'audio/webm', webm: 'audio/webm' };
                anexoMime = mimeMap[ext] || (anexoMime.indexOf('video/') === 0 ? anexoMime.replace('video/', 'audio/') : 'audio/webm');
            }
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
    function mimeAudioBlob(mime, nome) {
        var m = String(mime || '').toLowerCase().split(';')[0];
        var n = String(nome || '').toLowerCase();
        if (m === 'video/webm' || m === 'application/octet-stream' || m === '') {
            if (n.indexOf('.m4a') !== -1 || n.indexOf('.mp4') !== -1) return 'audio/mp4';
            if (n.indexOf('.mp3') !== -1) return 'audio/mpeg';
            if (n.indexOf('.ogg') !== -1) return 'audio/ogg';
            if (n.indexOf('.wav') !== -1) return 'audio/wav';
            return 'audio/webm';
        }
        if (m.indexOf('video/') === 0 && (n.indexOf('.webm') !== -1 || n.indexOf('.ogg') !== -1)) {
            return m.replace('video/', 'audio/');
        }
        return m || 'audio/webm';
    }
    function garantirSrcAudio(wrap, audio) {
        var src = wrap.getAttribute('data-src') || '';
        if (!src) return;
        // Remove <source type="..."> errado que impede o decode
        var sources = audio.querySelectorAll('source');
        sources.forEach(function (s) { s.remove(); });
        if (!audio.getAttribute('src') || audio.getAttribute('src') !== src) {
            audio.setAttribute('src', src);
            audio.load();
        }
    }
    function carregarAudioBlob(wrap, audio) {
        var src = wrap.getAttribute('data-src') || '';
        var mimeHint = wrap.getAttribute('data-mime') || '';
        if (!src) return Promise.reject(new Error('sem src'));
        if (wrap._fcBlobUrl) {
            audio.src = wrap._fcBlobUrl;
            return audio.play();
        }
        return fetch(src, { credentials: 'same-origin', headers: { 'Accept': 'audio/*,*/*' } })
            .then(function (res) {
                if (!res.ok) throw new Error('http ' + res.status);
                var ct = res.headers.get('Content-Type') || mimeHint;
                return res.blob().then(function (blob) {
                    var tipo = mimeAudioBlob(ct || blob.type, src);
                    var audioBlob = new Blob([blob], { type: tipo });
                    if (wrap._fcBlobUrl) URL.revokeObjectURL(wrap._fcBlobUrl);
                    wrap._fcBlobUrl = URL.createObjectURL(audioBlob);
                    audio.src = wrap._fcBlobUrl;
                    return audio.play();
                });
            });
    }
    function initAudioPlayer(wrap) {
        if (!wrap || wrap.getAttribute('data-fc-audio-init') === '1') return;
        wrap.setAttribute('data-fc-audio-init', '1');
        var audio = wrap.querySelector('audio');
        var btn = wrap.querySelector('.fc-audio-play');
        var track = wrap.querySelector('[data-fc-seek]');
        if (!audio || !btn) return;
        audio.setAttribute('preload', 'none');
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
        // Não marca "indisponível" no preload — só após tentativa real de play

        btn.addEventListener('click', function () {
            var timeEl = wrap.querySelector('.fc-audio-time');
            if (timeEl) {
                timeEl.removeAttribute('data-fc-indisp');
                timeEl.textContent = '…';
            }
            if (audio.paused) {
                pararAudioTodos(wrap);
                btn.disabled = true;
                var iniciar = function () {
                    return audio.play().then(function () {
                        audioAtual = audio;
                        btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
                        atualizarPlayerAudio(wrap);
                    });
                };
                Promise.resolve()
                    .then(function () {
                        garantirSrcAudio(wrap, audio);
                        return iniciar();
                    })
                    .catch(function () {
                        return carregarAudioBlob(wrap, audio).then(function () {
                            audioAtual = audio;
                            btn.innerHTML = '<i class="fa-solid fa-pause"></i>';
                            atualizarPlayerAudio(wrap);
                        });
                    })
                    .catch(function () {
                        btn.innerHTML = '<i class="fa-solid fa-play"></i>';
                        if (timeEl) {
                            timeEl.textContent = 'indisponível';
                            timeEl.setAttribute('data-fc-indisp', '1');
                        }
                    })
                    .finally(function () { btn.disabled = false; });
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
        var mime = mimeAudioBlob(anexoMime || 'audio/webm', anexoUrl);
        return '<div class="fc-audio" data-fc-audio data-src="' + escHtml(anexoUrl) + '" data-mime="' + escHtml(mime) + '">' +
            '<button type="button" class="fc-audio-play" aria-label="Reproduzir áudio"><i class="fa-solid fa-play"></i></button>' +
            '<div class="fc-audio-body">' +
            '<div class="fc-audio-track" data-fc-seek>' +
            '<div class="fc-audio-bars">' + bars + '</div>' +
            '<div class="fc-audio-scrub" style="left:0%"></div></div>' +
            '<span class="fc-audio-time">0:00</span></div>' +
            '<audio preload="none" playsinline src="' + escHtml(anexoUrl) + '"></audio></div>';
    }

    function previewTextoMensagem(m) {
        var txt = ((m && m.mensagem) || '').toString().trim();
        if (txt) return txt;
        var tipo = ((m && m.anexo_tipo) || '').toString();
        if (tipo === 'audio') return 'Áudio';
        if (tipo === 'imagem') return 'Foto';
        if (tipo === 'video') return 'Vídeo';
        if ((m && (m.imagem || m.url))) return ((m.imagem_nome || 'Anexo') + '');
        return 'Mensagem';
    }

    function htmlCitacao(m) {
        var resp = m && m.resposta ? m.resposta : null;
        var respId = parseInt((m && m.resposta_a_mensagem_id) || (resp && resp.id) || 0, 10) || 0;
        if (respId <= 0) return '';
        var autor = ((resp && resp.autor_tipo) || '') === 'escola' ? 'Escola' : 'Família';
        var txt = previewTextoMensagem(resp || {});
        return '<button type="button" class="fc-quote" data-fc-goto="' + respId + '" title="Ir para mensagem">' +
            '<span class="fc-quote-who">' + escHtml(autor) + '</span>' +
            '<span class="fc-quote-text">' + escHtml(txt) + '</span></button>';
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
        var preview = previewTextoMensagem(m);

        var html = '<div id="msg-' + msgId + '" class="fc-row ' + (daEscola ? 'is-escola' : 'is-familia') + '"' +
            ' data-fc-msg-id="' + msgId + '"' +
            ' data-fc-autor="' + (daEscola ? 'escola' : 'familia') + '"' +
            ' data-fc-preview="' + escHtml(preview) + '">' +
            '<div class="fc-bubble ' + (daEscola ? 'is-escola' : 'is-familia') + '">' +
            '<span class="fc-tag ' + (daEscola ? 'is-escola' : 'is-familia') + '">' + (daEscola ? 'Escola' : 'Família') + '</span>' +
            htmlCitacao(m);

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

        html += '<div class="fc-meta">';
        if (msgId > 0) {
            html += '<button type="button" class="fc-reply" data-fc-reply="' + msgId + '"' +
                ' data-fc-reply-autor="' + (daEscola ? 'escola' : 'familia') + '"' +
                ' data-fc-reply-preview="' + escHtml(preview) + '" title="Responder">' +
                '<i class="fa-solid fa-reply"></i> Responder</button>';
        }
        html += '<span class="fc-time">' + escHtml(hora) + '</span>';
        if (daEscola) {
            html += '<span class="fc-checks' + (lidaFamilia ? ' is-read' : '') + '"' +
                ' title="' + (lidaFamilia ? 'Visualizada pela família' : 'Enviada') + '">' +
                '<i class="fa-solid fa-check-double"></i></span>';
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
        var wrap = row.querySelector('.fc-checks');
        if (!wrap) return;
        var icon = wrap.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-check-double';
        wrap.classList.toggle('is-read', !!lido);
        wrap.title = lido ? 'Visualizada pela família' : 'Enviada';
    }

    function horaAgora() {
        var d = new Date();
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function setEstadoEnvio(ativo, texto) {
        enviando = !!ativo;
        var formEl = document.getElementById('fale-chat-form');
        var composer = formEl ? formEl.querySelector('.fc-composer') : null;
        var chip = document.getElementById('fale-enviando');
        var chipTxt = document.getElementById('fale-enviando-texto');
        var btn = document.getElementById('fale-btn-enviar');
        var mic = document.getElementById('fale-btn-mic');
        var msg = document.getElementById('fale-mensagem');
        if (composer) composer.classList.toggle('is-sending', enviando);
        if (chip) chip.classList.toggle('is-on', enviando);
        if (chipTxt && texto) chipTxt.textContent = texto;
        if (msg) msg.disabled = enviando;
        ['fale-btn-mais', 'fale-btn-clip', 'fale-btn-galeria', 'fale-btn-emoji', 'fale-anexo-remover'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.disabled = enviando;
        });
        if (mic) {
            mic.disabled = enviando;
            if (enviando) mic.style.display = 'none';
        }
        if (btn) {
            btn.disabled = enviando;
            btn.classList.toggle('is-loading', enviando);
            btn.innerHTML = enviando
                ? '<i class="fa-solid fa-spinner fc-spin" aria-hidden="true"></i>'
                : '<i class="fa-solid fa-paper-plane"></i>';
            if (enviando) {
                btn.style.display = 'inline-flex';
                btn.setAttribute('aria-label', 'Enviando');
                btn.title = 'Enviando…';
            } else {
                btn.setAttribute('aria-label', 'Enviar');
                btn.title = 'Enviar';
                if (typeof atualizarAcoes === 'function') atualizarAcoes();
            }
        }
    }

    function criarBolhaPendente(texto, anexoNome) {
        if (!box) return null;
        removerVazio();
        var pid = 'pending-' + Date.now();
        var row = document.createElement('div');
        row.id = pid;
        row.className = 'fc-row is-escola is-pending';
        row.setAttribute('data-fc-pending', '1');
        var replyIdEl = document.getElementById('fale-reply-id');
        var replyTextEl = document.getElementById('fale-reply-text');
        var replyLabelEl = document.getElementById('fale-reply-label');
        var replyId = parseInt((replyIdEl && replyIdEl.value) || '0', 10) || 0;
        var citacao = '';
        if (replyId > 0) {
            citacao = '<button type="button" class="fc-quote" data-fc-goto="' + replyId + '" title="Ir para mensagem">' +
                '<span class="fc-quote-who">' + escHtml((replyLabelEl && replyLabelEl.textContent) || 'Respondendo') + '</span>' +
                '<span class="fc-quote-text">' + escHtml((replyTextEl && replyTextEl.textContent) || 'Mensagem') + '</span></button>';
        }
        var corpo = '';
        if (texto) corpo += '<div class="fc-text">' + textoHtml(texto) + '</div>';
        if (anexoNome) {
            corpo += '<div style="margin-top:8px;display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;background:rgba(15,23,42,.06);font-size:13px;color:#1e293b;">' +
                '<i class="fa-solid fa-paperclip"></i> ' + escHtml(anexoNome) + '</div>';
        }
        if (!corpo) corpo = '<div class="fc-text" style="color:#64748b;font-style:italic;">Enviando…</div>';
        row.innerHTML =
            '<div class="fc-bubble is-escola">' +
            '<span class="fc-tag is-escola">Escola</span>' +
            citacao +
            corpo +
            '<div class="fc-meta"><span class="fc-time">' + escHtml(horaAgora()) + '</span>' +
            '<span class="fc-checks" title="Enviando"><i class="fa-solid fa-clock"></i></span></div>' +
            '</div>';
        box.appendChild(row);
        scrollBottom();
        return row;
    }

    function definirResposta(msgId, autor, preview) {
        var idEl = document.getElementById('fale-reply-id');
        var bar = document.getElementById('fale-reply-bar');
        var label = document.getElementById('fale-reply-label');
        var text = document.getElementById('fale-reply-text');
        var msg = document.getElementById('fale-mensagem');
        if (!idEl || !bar) return;
        idEl.value = String(msgId || '');
        if (label) label.textContent = 'Respondendo a ' + (autor === 'escola' ? 'Escola' : 'Família');
        if (text) text.textContent = preview || 'Mensagem';
        bar.classList.add('is-on');
        if (msg) msg.focus();
    }
    function limparResposta() {
        var idEl = document.getElementById('fale-reply-id');
        var bar = document.getElementById('fale-reply-bar');
        if (idEl) idEl.value = '';
        if (bar) bar.classList.remove('is-on');
    }

    function marcarFalhaPendente(row, erro, restaurar) {
        if (!row) return;
        row.classList.remove('is-pending');
        row.classList.add('is-failed');
        var bubble = row.querySelector('.fc-bubble');
        var checks = row.querySelector('.fc-checks');
        if (checks) {
            checks.title = 'Falha no envio';
            checks.innerHTML = '<i class="fa-solid fa-exclamation-circle" style="color:#dc2626;"></i>';
        }
        if (!bubble || bubble.querySelector('.fc-send-fail')) return;
        var fail = document.createElement('div');
        fail.className = 'fc-send-fail';
        fail.innerHTML = '<span>' + escHtml(erro || 'Não foi possível enviar.') + '</span>' +
            '<button type="button" data-fc-retry>Tentar de novo</button>' +
            '<button type="button" data-fc-descartar style="background:transparent;color:#64748b;">Descartar</button>';
        bubble.appendChild(fail);
        fail.querySelector('[data-fc-retry]').addEventListener('click', function () {
            row.remove();
            if (typeof restaurar === 'function') restaurar();
        });
        fail.querySelector('[data-fc-descartar]').addEventListener('click', function () {
            row.remove();
        });
    }

    function enviarMensagemAjax() {
        var form = document.getElementById('fale-chat-form');
        var msgEl = document.getElementById('fale-mensagem');
        var fileEl = document.getElementById('fale-anexo-input');
        if (!form || enviando) return Promise.resolve();
        var texto = ((msgEl && msgEl.value) || '').trim();
        var arquivo = (fileEl && fileEl.files && fileEl.files[0]) ? fileEl.files[0] : null;
        if (!texto && !arquivo) return Promise.resolve();

        var fd = new FormData(form);
        fd.append('ajax', '1');
        var anexoNome = arquivo ? arquivo.name : '';
        var labelEnvio = arquivo
            ? (arquivo.type && arquivo.type.indexOf('audio/') === 0 ? 'Enviando áudio…' : 'Enviando anexo…')
            : 'Enviando mensagem…';

        var pending = criarBolhaPendente(texto, anexoNome);
        previewListaUltima({
            mensagem: texto || (anexoNome ? 'Anexo' : ''),
            created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
        });

        if (msgEl) {
            msgEl.value = '';
            if (typeof autoResize === 'function') autoResize();
        }
        if (typeof limparAnexo === 'function') limparAnexo();
        setEstadoEnvio(true, labelEnvio);

        function restaurarConteudo() {
            if (msgEl && texto) {
                msgEl.value = texto;
                if (typeof autoResize === 'function') autoResize();
            }
            if (arquivo && fileEl) {
                try {
                    var dt = new DataTransfer();
                    dt.items.add(arquivo);
                    fileEl.files = dt.files;
                    if (typeof mostrarPreview === 'function') mostrarPreview(arquivo.name);
                } catch (e) { /* alguns browsers bloqueiam DataTransfer */ }
            }
            if (typeof atualizarAcoes === 'function') atualizarAcoes();
            if (msgEl) msgEl.focus();
        }

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
                setEstadoEnvio(false);
                if (!data || !data.ok) {
                    marcarFalhaPendente(pending, (data && data.erro) ? data.erro : 'Não foi possível enviar a mensagem.', function () {
                        restaurarConteudo();
                        enviarMensagemAjax();
                    });
                    return;
                }
                if (pending) pending.remove();
                limparResposta();
                if (data.mensagem) {
                    appendMensagem(data.mensagem, { highlightFamilia: false });
                    previewListaUltima(data.mensagem);
                }
                scrollBottom();
            })
            .catch(function () {
                setEstadoEnvio(false);
                marcarFalhaPendente(pending, 'Falha de rede ao enviar.', function () {
                    restaurarConteudo();
                    enviarMensagemAjax();
                });
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
                if (!data || !data.ok) return;
                if (Array.isArray(data.lidas_escola)) {
                    data.lidas_escola.forEach(function (id) {
                        atualizarChecks(parseInt(id, 10) || 0, true);
                    });
                }
                if (!Array.isArray(data.mensagens)) return;
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
        box.addEventListener('click', function (e) {
            var replyBtn = e.target && e.target.closest ? e.target.closest('[data-fc-reply]') : null;
            if (replyBtn) {
                e.preventDefault();
                definirResposta(
                    parseInt(replyBtn.getAttribute('data-fc-reply'), 10) || 0,
                    replyBtn.getAttribute('data-fc-reply-autor') || 'familia',
                    replyBtn.getAttribute('data-fc-reply-preview') || 'Mensagem'
                );
                return;
            }
            var quoteBtn = e.target && e.target.closest ? e.target.closest('[data-fc-goto]') : null;
            if (quoteBtn) {
                e.preventDefault();
                var gotoId = parseInt(quoteBtn.getAttribute('data-fc-goto'), 10) || 0;
                var alvo = gotoId > 0 ? document.getElementById('msg-' + gotoId) : null;
                if (alvo) {
                    alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    alvo.classList.add('fc-new-in');
                    setTimeout(function () { alvo.classList.remove('fc-new-in'); }, 1400);
                }
            }
        });
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

    var lightbox = document.getElementById('fc-lightbox');
    var lightboxImg = document.getElementById('fc-lightbox-img');
    var lightboxCaption = document.getElementById('fc-lightbox-caption');
    var lightboxClose = document.getElementById('fc-lightbox-close');

    function abrirFotoAluno(src, nome) {
        if (!lightbox || !lightboxImg || !src) return;
        lightboxImg.src = src;
        lightboxImg.alt = nome ? ('Foto de ' + nome) : 'Foto do aluno';
        if (lightboxCaption) lightboxCaption.textContent = nome || 'Aluno';
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
    }
    function fecharFotoAluno() {
        if (!lightbox) return;
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        if (lightboxImg) lightboxImg.removeAttribute('src');
    }
    document.addEventListener('click', function (e) {
        var trigger = e.target && e.target.closest ? e.target.closest('[data-fc-expand-foto]') : null;
        if (!trigger) return;
        e.preventDefault();
        e.stopPropagation();
        abrirFotoAluno(trigger.getAttribute('data-fc-expand-foto'), trigger.getAttribute('data-fc-expand-nome') || '');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (lightbox && lightbox.classList.contains('is-open')) {
                fecharFotoAluno();
            } else if (typeof window.closeFcInfoDrawer === 'function') {
                window.closeFcInfoDrawer();
            }
        }
        var el = e.target && e.target.closest ? e.target.closest('[data-fc-expand-foto]') : null;
        if (el && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            abrirFotoAluno(el.getAttribute('data-fc-expand-foto'), el.getAttribute('data-fc-expand-nome') || '');
        }
    });
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) fecharFotoAluno();
        });
    }
    if (lightboxClose) lightboxClose.addEventListener('click', fecharFotoAluno);

    var btnInfo = document.getElementById('fc-btn-info');
    if (btnInfo) {
        btnInfo.addEventListener('click', function () {
            if (typeof window.openFcInfoDrawer === 'function') {
                window.openFcInfoDrawer();
                return;
            }
            var aluno = btnInfo.getAttribute('data-aluno') || 'Aluno';
            var resp = btnInfo.getAttribute('data-responsavel') || 'Responsável';
            var turma = (btnInfo.getAttribute('data-turma') || '').trim();
            alert('Conversa com ' + resp + ' — aluno ' + aluno + (turma ? ' · turma ' + turma : ''));
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

    var btnCancelarResposta = document.getElementById('fale-reply-cancelar');
    if (btnCancelarResposta) {
        btnCancelarResposta.addEventListener('click', limparResposta);
    }

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
        if (enviando) {
            btnMic.style.display = 'none';
            btnEnviar.style.display = 'inline-flex';
            btnEnviar.disabled = true;
            return;
        }
        if (gravando) { btnMic.style.display = 'none'; btnEnviar.style.display = 'none'; return; }
        if (temConteudo()) { btnMic.style.display = 'none'; btnEnviar.style.display = 'inline-flex'; btnEnviar.disabled = false; }
        else { btnEnviar.style.display = 'none'; btnMic.style.display = 'inline-flex'; btnMic.disabled = false; }
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
