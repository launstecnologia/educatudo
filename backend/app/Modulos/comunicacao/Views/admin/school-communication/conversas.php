<?php
$items = is_array($items ?? null) ? $items : [];
$conversa = is_array($conversa ?? null) ? $conversa : null;
$mensagens = is_array($mensagens ?? null) ? $mensagens : [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$conversaId = (int) ($conversa['id'] ?? 0);
$baseUrl = URL . '/admin/comunicacao-escolar/fale-com-a-escola';
$escolaAvatar = trim((string) ($escola_avatar ?? ''));
$reacoesProntas = !empty($reacoes_prontas);

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

$urlMidia = static function (string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim((string) URL, '/') . '/' . ltrim($path, '/');
};

$avatarHtml = static function (string $foto, string $iniciaisTxt, string $bg, string $fg, string $size = '40px') use ($urlMidia): string {
    $foto = $urlMidia($foto);
    $style = 'width:' . $size . ';height:' . $size . ';border-radius:9999px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:' . $bg . ';color:' . $fg . ';font-size:12px;font-weight:700;';
    if ($foto !== '') {
        return '<div style="' . $style . '"><img src="' . htmlspecialchars($foto) . '" alt="" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.textContent=\'' . htmlspecialchars($iniciaisTxt, ENT_QUOTES) . '\'"></div>';
    }
    return '<div style="' . $style . '">' . htmlspecialchars($iniciaisTxt) . '</div>';
};
?>
<style>
.fale-shell { display:flex; flex-direction:column; gap:1rem; }
.fale-card {
  display:grid; grid-template-columns:1fr; width:100%;
  height: calc(100vh - 11rem); min-height: 520px; max-height: 780px;
  background:#fff; border:1px solid #e5e7eb; border-radius:1rem;
  box-shadow:0 10px 15px -3px rgba(0,0,0,.08); overflow:hidden;
}
@media (min-width:1024px) {
  .fale-card { grid-template-columns: 320px 1fr; }
}
.fale-lista { display:flex; flex-direction:column; background:#f8fafc; border-bottom:1px solid #e5e7eb; min-height:0; }
@media (min-width:1024px) {
  .fale-lista { border-bottom:0; border-right:1px solid #e5e7eb; }
}
.fale-lista-head { padding:.875rem 1rem; background:#fff; border-bottom:1px solid #e5e7eb; }
.fale-lista-body { overflow:auto; flex:1; min-height:0; }
.fale-conv {
  display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem;
  text-decoration:none; color:inherit; border-left:3px solid transparent; border-bottom:1px solid #f1f5f9;
}
.fale-conv:hover { background:#fff; }
.fale-conv.is-ativa { background:#fff; border-left-color:#0d9488; }
.fale-thread { display:flex; flex-direction:column; min-height:0; background:#fff; }
.fale-thread-head {
  display:flex; align-items:center; gap:.75rem; padding:.875rem 1rem;
  background:#f8fafc; border-bottom:1px solid #e5e7eb; flex-shrink:0;
}
.fale-msgs {
  flex:1; min-height:0; overflow:auto; padding:1rem;
  background-color:#e5ddd5;
  background-image: radial-gradient(rgba(0,0,0,.03) 1px, transparent 1px);
  background-size: 18px 18px;
}
.fale-row { display:flex; align-items:flex-end; gap:.5rem; margin-bottom:.75rem; }
.fale-row.is-escola { justify-content:flex-end; }
.fale-row.is-familia { justify-content:flex-start; }
.fale-bolha {
  max-width:min(78%, 28rem); padding:.55rem .8rem .4rem;
  border-radius:12px; box-shadow:0 1px 1px rgba(0,0,0,.08); position:relative;
}
.fale-bolha.is-escola { background:#d9fdd3; border-bottom-right-radius:4px; }
.fale-bolha.is-familia { background:#fff; border-bottom-left-radius:4px; }
.fale-bolha-autor { font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#64748b; margin-bottom:.15rem; }
.fale-bolha-texto { font-size:14px; line-height:1.4; color:#111827; white-space:pre-wrap; word-break:break-word; }
.fale-bolha-meta { display:flex; align-items:center; justify-content:flex-end; gap:.4rem; margin-top:.25rem; }
.fale-bolha-hora { font-size:11px; color:#64748b; }
.fale-curtir {
  border:0; background:transparent; color:#64748b; cursor:pointer; padding:0;
  font-size:12px; display:inline-flex; align-items:center; gap:.2rem;
}
.fale-curtir.is-on { color:#0d9488; }
.fale-composer {
  flex-shrink:0; background:#f0f2f5; border-top:1px solid #e5e7eb;
  padding:.65rem .75rem; display:flex; flex-direction:column; gap:.5rem;
}
.fale-composer-row { display:flex; align-items:flex-end; gap:.5rem; }
.fale-btn-icon {
  width:42px; height:42px; border-radius:9999px; border:0; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;
}
.fale-btn-mais { background:#fff; color:#475569; border:1px solid #e2e8f0 !important; }
.fale-btn-acao { background:#0d9488; color:#fff; }
.fale-btn-acao:hover { background:#0f766e; }
.fale-input {
  flex:1; min-width:0; border:0; border-radius:1.5rem; background:#fff;
  padding:.7rem 1rem; font-size:14px; resize:none; max-height:110px; line-height:1.35;
  box-shadow: inset 0 0 0 1px #e2e8f0; outline:none;
}
.fale-input:focus { box-shadow: inset 0 0 0 2px #14b8a6; }
.fale-menu {
  display:none; position:absolute; bottom:52px; left:0; z-index:30;
  width:180px; background:#fff; border:1px solid #e5e7eb; border-radius:.75rem;
  box-shadow:0 10px 25px rgba(0,0,0,.12); overflow:hidden;
}
.fale-menu.is-open { display:block; }
.fale-menu button {
  width:100%; border:0; background:#fff; text-align:left; padding:.7rem .9rem;
  font-size:14px; color:#334155; cursor:pointer; display:flex; align-items:center; gap:.5rem;
}
.fale-menu button:hover { background:#f8fafc; }
.fale-chip {
  display:none; align-items:center; justify-content:space-between; gap:.5rem;
  background:#fff; border:1px solid #e5e7eb; border-radius:.75rem; padding:.5rem .75rem; font-size:13px;
}
.fale-chip.is-on { display:flex; }
.fale-empty {
  flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
  text-align:center; padding:2rem; color:#64748b; background:#f8fafc;
}
</style>

<div class="fale-shell">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Fale com a Escola</h2>
            <p class="text-gray-600 mt-1">Chat com as famílias — mensagens, anexos e áudio.</p>
        </div>
        <a href="<?= URL ?>/admin/comunicacao-escolar" class="text-gray-600 hover:text-gray-900">← Comunicação escolar</a>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash_type ?? '') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars((string) $flash_message) ?>
        </div>
    <?php endif; ?>

    <div class="fale-card">
        <aside class="fale-lista">
            <div class="fale-lista-head">
                <p class="text-sm font-semibold text-gray-900">Conversas</p>
                <p class="text-xs text-gray-500"><?= count($items) ?> família(s)</p>
            </div>
            <div class="fale-lista-body">
                <?php if ($items === []): ?>
                    <p class="px-4 py-8 text-sm text-gray-500">Nenhuma família falou com a escola ainda.</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $id = (int) ($item['id'] ?? 0);
                        $ativa = $id === $conversaId;
                        $naoLidas = (int) ($item['nao_lidas'] ?? 0);
                        $nomeAluno = (string) ($item['aluno_nome'] ?? 'Aluno');
                        ?>
                        <a href="<?= $baseUrl ?>/<?= $id ?>" class="fale-conv <?= $ativa ? 'is-ativa' : '' ?>">
                            <?= $avatarHtml((string) ($item['foto_url'] ?? ''), $iniciais($nomeAluno), '#ccfbf1', '#115e59', '44px') ?>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-semibold text-gray-900 truncate text-sm"><?= htmlspecialchars($nomeAluno) ?></p>
                                    <?php if ($naoLidas > 0): ?>
                                        <span class="shrink-0 inline-flex items-center rounded-full bg-red-500 text-white px-1.5 py-0.5 text-[10px] font-bold"><?= $naoLidas ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars((string) ($item['responsavel_nome'] ?? 'Responsável')) ?></p>
                                <p class="text-xs text-gray-600 truncate mt-0.5"><?= htmlspecialchars((string) (($item['ultima'] ?? '') !== '' ? $item['ultima'] : 'Anexo')) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section class="fale-thread">
            <?php if ($conversa === null): ?>
                <div class="fale-empty">
                    <div class="w-14 h-14 rounded-full bg-teal-50 flex items-center justify-center mb-3">
                        <i class="fa-regular fa-comments text-teal-600 text-xl"></i>
                    </div>
                    <p class="text-base font-semibold text-gray-900">Selecione uma conversa</p>
                    <p class="text-sm text-gray-500 mt-1 max-w-sm">Escolha uma família à esquerda para ver o histórico e responder.</p>
                </div>
            <?php else: ?>
                <?php
                $fotoConversa = (string) ($conversa['foto_url'] ?? '');
                $nomeConversa = (string) ($conversa['aluno_nome'] ?? 'Aluno');
                ?>
                <div class="fale-thread-head">
                    <?= $avatarHtml($fotoConversa, $iniciais($nomeConversa), '#ccfbf1', '#115e59', '44px') ?>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($nomeConversa) ?></p>
                        <p class="text-xs text-gray-500 truncate">Responsável: <?= htmlspecialchars((string) ($conversa['responsavel_nome'] ?? '')) ?></p>
                    </div>
                </div>

                <div id="fale-chat-mensagens" class="fale-msgs">
                    <?php if ($mensagens === []): ?>
                        <p class="text-sm text-gray-600 text-center py-10">Nenhuma mensagem nesta conversa.</p>
                    <?php endif; ?>
                    <?php foreach ($mensagens as $mensagem): ?>
                        <?php
                        $daEscola = ($mensagem['autor_tipo'] ?? '') === 'escola';
                        $msgId = (int) ($mensagem['id'] ?? 0);
                        $anexoTipo = (string) ($mensagem['anexo_tipo'] ?? (!empty($mensagem['imagem']) ? 'imagem' : ''));
                        $anexoMime = (string) ($mensagem['anexo_mime'] ?? '');
                        if ($anexoTipo === '' && str_starts_with($anexoMime, 'audio/')) {
                            $anexoTipo = 'audio';
                        }
                        $anexoUrl = $urlMidia((string) ($mensagem['imagem'] ?? ''));
                        $anexoNome = (string) ($mensagem['imagem_nome'] ?? 'arquivo');
                        $curtidas = (int) ($mensagem['curtidas'] ?? 0);
                        $euCurti = !empty($mensagem['eu_curti']);
                        $fotoBolha = $daEscola ? $escolaAvatar : $fotoConversa;
                        $iniciaisBolha = $daEscola ? 'ES' : $iniciais($nomeConversa);
                        ?>
                        <div id="msg-<?= $msgId ?>" class="fale-row <?= $daEscola ? 'is-escola' : 'is-familia' ?>">
                            <?php if (!$daEscola): ?>
                                <?= $avatarHtml($fotoBolha, $iniciaisBolha, '#fff', '#475569', '32px') ?>
                            <?php endif; ?>

                            <div class="fale-bolha <?= $daEscola ? 'is-escola' : 'is-familia' ?>">
                                <div class="fale-bolha-autor"><?= $daEscola ? 'Escola' : 'Família' ?></div>
                                <?php if (trim((string) ($mensagem['mensagem'] ?? '')) !== ''): ?>
                                    <div class="fale-bolha-texto"><?= nl2br(htmlspecialchars((string) $mensagem['mensagem'])) ?></div>
                                <?php endif; ?>

                                <?php if ($anexoUrl !== '' && $anexoTipo === 'imagem'): ?>
                                    <a href="<?= htmlspecialchars($anexoUrl) ?>" target="_blank" rel="noopener" class="block mt-2">
                                        <img src="<?= htmlspecialchars($anexoUrl) ?>" alt="Imagem" style="max-height:220px;border-radius:8px;display:block;">
                                    </a>
                                <?php elseif ($anexoUrl !== '' && $anexoTipo === 'video'): ?>
                                    <video controls style="margin-top:8px;max-height:220px;width:100%;border-radius:8px;background:#000;" preload="metadata">
                                        <source src="<?= htmlspecialchars($anexoUrl) ?>" type="<?= htmlspecialchars($anexoMime !== '' ? $anexoMime : 'video/mp4') ?>">
                                    </video>
                                <?php elseif ($anexoUrl !== '' && $anexoTipo === 'audio'): ?>
                                    <audio controls style="margin-top:8px;width:100%;max-width:240px;" preload="metadata">
                                        <source src="<?= htmlspecialchars($anexoUrl) ?>" type="<?= htmlspecialchars($anexoMime !== '' ? $anexoMime : 'audio/webm') ?>">
                                    </audio>
                                <?php elseif ($anexoUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($anexoUrl) ?>" target="_blank" rel="noopener"
                                       style="margin-top:8px;display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;font-size:13px;color:#1e293b;text-decoration:none;">
                                        <i class="fa-regular fa-file"></i>
                                        <?= htmlspecialchars($anexoNome) ?>
                                    </a>
                                <?php endif; ?>

                                <div class="fale-bolha-meta">
                                    <?php if ($curtidas > 0): ?>
                                        <span style="font-size:11px;">👍 <?= $curtidas ?></span>
                                    <?php endif; ?>
                                    <?php if ($reacoesProntas && $msgId > 0): ?>
                                        <form method="post" action="<?= $baseUrl ?>/<?= $conversaId ?>/mensagens/<?= $msgId ?>/curtir" style="display:inline;margin:0;">
                                            <input type="hidden" name="_token" value="<?= $csrf ?>">
                                            <button type="submit" class="fale-curtir <?= $euCurti ? 'is-on' : '' ?>" title="<?= $euCurti ? 'Remover curtida' : 'Curtir' ?>">
                                                <i class="fa-<?= $euCurti ? 'solid' : 'regular' ?> fa-thumbs-up"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <span class="fale-bolha-hora"><?= !empty($mensagem['created_at']) ? date('d/m H:i', strtotime((string) $mensagem['created_at'])) : '' ?></span>
                                </div>
                            </div>

                            <?php if ($daEscola): ?>
                                <?= $avatarHtml($fotoBolha, $iniciaisBolha, '#0d9488', '#fff', '32px') ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form id="fale-chat-form" method="post" enctype="multipart/form-data" action="<?= $baseUrl ?>/<?= $conversaId ?>/responder" class="fale-composer">
                    <input type="hidden" name="_token" value="<?= $csrf ?>">
                    <input type="file" id="fale-anexo-input" name="anexo" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf,audio/*" class="hidden">

                    <div id="fale-anexo-preview" class="fale-chip">
                        <span id="fale-anexo-nome" class="truncate"></span>
                        <button type="button" id="fale-anexo-remover" class="text-gray-500 hover:text-red-600" title="Remover" aria-label="Remover anexo"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div id="fale-gravando" class="fale-chip" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;">
                        <span><span style="display:inline-block;width:8px;height:8px;border-radius:9999px;background:#ef4444;margin-right:6px;"></span>Gravando <strong id="fale-gravando-timer">0:00</strong></span>
                        <span style="display:inline-flex;gap:8px;align-items:center;">
                            <button type="button" id="fale-gravando-cancelar" style="border:0;background:transparent;color:#b91c1c;font-size:12px;font-weight:600;cursor:pointer;">Cancelar</button>
                            <button type="button" id="fale-gravando-parar" class="fale-btn-acao" style="width:auto;height:auto;border-radius:9999px;padding:4px 10px;font-size:12px;">
                                <i class="fa-solid fa-check"></i> Pronto
                            </button>
                        </span>
                    </div>

                    <div class="fale-composer-row">
                        <div style="position:relative;flex-shrink:0;">
                            <button type="button" id="fale-btn-mais" class="fale-btn-icon fale-btn-mais" title="Anexar" aria-label="Anexar">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            <div id="fale-menu-anexo" class="fale-menu">
                                <button type="button" data-fale-attach="image/*"><i class="fa-regular fa-image" style="color:#0d9488;width:16px;"></i> Foto</button>
                                <button type="button" data-fale-attach="video/mp4,video/webm"><i class="fa-solid fa-video" style="color:#7c3aed;width:16px;"></i> Vídeo</button>
                                <button type="button" data-fale-attach="application/pdf"><i class="fa-regular fa-file-pdf" style="color:#dc2626;width:16px;"></i> Documento</button>
                            </div>
                        </div>

                        <textarea id="fale-mensagem" name="mensagem" rows="1" class="fale-input" placeholder="Mensagem"></textarea>

                        <button type="button" id="fale-btn-mic" class="fale-btn-icon fale-btn-acao" title="Gravar áudio" aria-label="Gravar áudio">
                            <i class="fa-solid fa-microphone"></i>
                        </button>
                        <button type="submit" id="fale-btn-enviar" class="fale-btn-icon fale-btn-acao" title="Enviar" aria-label="Enviar" style="display:none;">
                            <i class="fa-solid fa-paper-plane" style="font-size:13px;"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('fale-chat-mensagens');
    if (box) {
        var hash = window.location.hash;
        if (hash && document.querySelector(hash)) {
            document.querySelector(hash).scrollIntoView({ block: 'center' });
        } else {
            box.scrollTop = box.scrollHeight;
        }
    }

    var form = document.getElementById('fale-chat-form');
    if (!form) return;

    var input = document.getElementById('fale-anexo-input');
    var preview = document.getElementById('fale-anexo-preview');
    var previewNome = document.getElementById('fale-anexo-nome');
    var btnRemover = document.getElementById('fale-anexo-remover');
    var btnMais = document.getElementById('fale-btn-mais');
    var menuAnexo = document.getElementById('fale-menu-anexo');
    var mensagem = document.getElementById('fale-mensagem');
    var btnMic = document.getElementById('fale-btn-mic');
    var btnEnviar = document.getElementById('fale-btn-enviar');
    var gravandoBox = document.getElementById('fale-gravando');
    var gravandoTimer = document.getElementById('fale-gravando-timer');
    var btnCancelarGravacao = document.getElementById('fale-gravando-cancelar');
    var btnPararGravacao = document.getElementById('fale-gravando-parar');

    var mediaRecorder = null;
    var chunks = [];
    var gravando = false;
    var cancelarProximo = false;
    var timerId = null;
    var segundos = 0;

    function temConteudo() {
        return (mensagem.value || '').trim() !== '' || (input.files && input.files.length > 0);
    }

    function atualizarAcoes() {
        if (gravando) {
            btnMic.style.display = 'none';
            btnEnviar.style.display = 'none';
            return;
        }
        if (temConteudo()) {
            btnMic.style.display = 'none';
            btnEnviar.style.display = 'inline-flex';
        } else {
            btnEnviar.style.display = 'none';
            btnMic.style.display = 'inline-flex';
        }
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
        var m = Math.floor(s / 60);
        var r = s % 60;
        return m + ':' + String(r).padStart(2, '0');
    }

    function pararTimer() {
        if (timerId) clearInterval(timerId);
        timerId = null;
        segundos = 0;
    }

    btnMais.addEventListener('click', function (e) {
        e.stopPropagation();
        menuAnexo.classList.toggle('is-open');
    });
    document.addEventListener('click', function () {
        menuAnexo.classList.remove('is-open');
    });
    menuAnexo.addEventListener('click', function (e) { e.stopPropagation(); });

    menuAnexo.querySelectorAll('[data-fale-attach]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            input.accept = btn.getAttribute('data-fale-attach') || 'image/*,video/*,application/pdf';
            menuAnexo.classList.remove('is-open');
            input.click();
        });
    });

    input.addEventListener('change', function () {
        if (input.files && input.files[0]) mostrarPreview(input.files[0].name);
        else limparAnexo();
    });
    btnRemover.addEventListener('click', limparAnexo);
    mensagem.addEventListener('input', function () {
        autoResize();
        atualizarAcoes();
    });

    function extensaoAudio(mimeType) {
        var m = (mimeType || '').toLowerCase();
        if (m.indexOf('mp4') !== -1 || m.indexOf('m4a') !== -1 || m.indexOf('aac') !== -1) return 'm4a';
        if (m.indexOf('ogg') !== -1) return 'ogg';
        if (m.indexOf('mpeg') !== -1 || m.indexOf('mp3') !== -1) return 'mp3';
        return 'webm';
    }

    function setArquivoAudio(blob) {
        var tipo = blob.type || 'audio/webm';
        var ext = extensaoAudio(tipo);
        var file = new File([blob], 'audio-' + Date.now() + '.' + ext, { type: tipo });
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
            chunks = [];
            cancelarProximo = false;
            // Safari prefere mp4; Chrome/Firefox webm
            var mime = '';
            if (window.MediaRecorder && MediaRecorder.isTypeSupported) {
                if (MediaRecorder.isTypeSupported('audio/mp4')) mime = 'audio/mp4';
                else if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) mime = 'audio/webm;codecs=opus';
                else if (MediaRecorder.isTypeSupported('audio/webm')) mime = 'audio/webm';
                else if (MediaRecorder.isTypeSupported('audio/ogg')) mime = 'audio/ogg';
            }
            mediaRecorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
            mediaRecorder.ondataavailable = function (ev) {
                if (ev.data && ev.data.size > 0) chunks.push(ev.data);
            };
            mediaRecorder.onstop = function () {
                stream.getTracks().forEach(function (t) { t.stop(); });
                gravando = false;
                gravandoBox.classList.remove('is-on');
                btnMais.disabled = false;
                if (!cancelarProximo && chunks.length) {
                    setArquivoAudio(new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' }));
                } else {
                    limparAnexo();
                }
                pararTimer();
                atualizarAcoes();
            };
            mediaRecorder.start();
            gravando = true;
            gravandoBox.classList.add('is-on');
            btnMais.disabled = true;
            menuAnexo.classList.remove('is-open');
            segundos = 0;
            gravandoTimer.textContent = '0:00';
            timerId = setInterval(function () {
                segundos += 1;
                gravandoTimer.textContent = formatarTempo(segundos);
            }, 1000);
            atualizarAcoes();
        } catch (err) {
            alert('Não foi possível acessar o microfone. Verifique a permissão do navegador.');
        }
    }

    function pararGravacao(cancelar) {
        cancelarProximo = !!cancelar;
        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
    }

    btnMic.addEventListener('click', function () {
        if (!gravando) iniciarGravacao();
    });
    btnCancelarGravacao.addEventListener('click', function () { pararGravacao(true); });
    btnPararGravacao.addEventListener('click', function () { pararGravacao(false); });

    form.addEventListener('submit', function (e) {
        if (gravando) {
            e.preventDefault();
            pararGravacao(false);
            return;
        }
        if (!temConteudo()) e.preventDefault();
    });

    atualizarAcoes();
    autoResize();
})();
</script>
