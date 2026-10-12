<?php
$linkAula = trim((string) ($aula['link_aula'] ?? ''));
$meetingUrl = trim((string) ($jaas_meeting_url ?? ''));
if ($meetingUrl === '') {
    $meetingUrl = $linkAula;
}
$platformLower = mb_strtolower((string) ($aula['plataforma'] ?? ''), 'UTF-8');
$isJitsi = $platformLower === 'jitsi meet';
$canEmbedJitsi = $isJitsi && $meetingUrl !== '' && filter_var($meetingUrl, FILTER_VALIDATE_URL);
$jitsiApiDomain = '';
$jitsiApiRoom = '';
$jitsiApiJwt = '';
$jitsiExternalApiSrc = '';
if ($canEmbedJitsi) {
    $parts = parse_url($meetingUrl);
    $jitsiApiDomain = (string) ($parts['host'] ?? '');
    $path = trim((string) ($parts['path'] ?? ''), '/');
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $jitsiApiJwt = trim((string) ($query['jwt'] ?? ''));
    $segments = $path !== '' ? explode('/', $path) : [];
    if ($jitsiApiDomain === '8x8.vc' && count($segments) >= 2) {
        $jitsiApiRoom = $segments[0] . '/' . $segments[1];
        $jitsiExternalApiSrc = 'https://8x8.vc/' . rawurlencode($segments[0]) . '/external_api.js';
    } elseif ($jitsiApiDomain !== '' && $path !== '') {
        $jitsiApiRoom = $path;
        $jitsiExternalApiSrc = 'https://' . $jitsiApiDomain . '/external_api.js';
    }
}
$canUseJitsiApi = $jitsiExternalApiSrc !== '' && $jitsiApiRoom !== '';
?>
<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-1"><?= $canEmbedJitsi ? 'Sala e Chat da Aula' : 'Chat da Aula' ?></h1>
        <p class="text-sm text-gray-600">
            <?= htmlspecialchars((string) ($aula['titulo'] ?? '')) ?>
            <?php if (!empty($aula['inicio_em'])): ?>
                • <?= date('d/m/Y H:i', strtotime((string) $aula['inicio_em'])) ?>
            <?php endif; ?>
        </p>
        <div class="mt-3">
            <a href="<?= URL ?>/admin/aulas-online" class="text-blue-600 hover:underline text-sm">← Voltar para aulas</a>
        </div>
    </div>

    <?php if ($canEmbedJitsi): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div class="text-sm font-medium text-gray-800">Sala Jitsi Meet</div>
                <a href="<?= htmlspecialchars($meetingUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-600 hover:underline">
                    Abrir em nova aba
                </a>
            </div>
            <div class="aspect-video w-full overflow-hidden rounded-xl border border-gray-200 bg-black">
                <?php if ($canUseJitsiApi): ?>
                    <div id="jitsiRoom" class="w-full h-full"></div>
                <?php else: ?>
                    <iframe
                        src="<?= htmlspecialchars($meetingUrl) ?>"
                        class="w-full h-full border-0"
                        allow="camera; microphone; display-capture; autoplay; clipboard-write; fullscreen"
                        allowfullscreen
                        title="<?= htmlspecialchars((string) ($aula['titulo'] ?? 'Sala Jitsi Meet')) ?>"
                    ></iframe>
                <?php endif; ?>
            </div>
            <?php if ($canUseJitsiApi): ?>
                <p id="recordingStatus" class="text-xs text-gray-500 mt-2">A gravação será iniciada automaticamente quando você entrar na sala como moderador.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-gray-200 rounded-xl p-4">
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="text-sm font-medium text-gray-800">Mensagens da aula</div>
            <button type="button" id="enableBell" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">
                Ativar sino
            </button>
        </div>
        <div id="chatBox" class="h-[420px] overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50"></div>

        <form id="chatForm" class="mt-3 flex gap-2">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
            <input type="hidden" name="aula_id" value="<?= (int) ($aula['id'] ?? 0) ?>">
            <input type="text" name="mensagem" id="mensagem" maxlength="2000" class="flex-1 border border-gray-300 rounded-lg px-3 py-2" placeholder="Digite uma mensagem para a turma..." required>
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Enviar</button>
        </form>
    </div>
</div>

<script>
(function () {
    const aulaId = <?= (int) ($aula['id'] ?? 0) ?>;
    const chatBox = document.getElementById('chatBox');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('mensagem');
    const enableBell = document.getElementById('enableBell');
    let lastId = 0;
    let audioCtx = null;
    let bellEnabled = false;
    let initialLoadDone = false;

    function esc(str) {
        return String(str || '').replace(/[&<>\"']/g, function (m) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'})[m];
        });
    }

    async function enableBellNotifications() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx && !audioCtx) {
                audioCtx = new AudioCtx();
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                await audioCtx.resume();
            }
            if ('Notification' in window && Notification.permission === 'default') {
                await Notification.requestPermission();
            }
            bellEnabled = true;
            if (enableBell) {
                enableBell.textContent = 'Sino ativado';
                enableBell.className = 'px-3 py-1.5 text-xs font-medium rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800';
            }
            playBell();
        } catch (e) {
            bellEnabled = true;
        }
    }

    function playBell() {
        if (!bellEnabled) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!audioCtx && AudioCtx) audioCtx = new AudioCtx();
            if (!audioCtx) return;
            const now = audioCtx.currentTime;
            [880, 1320].forEach(function (freq, idx) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, now + (idx * 0.12));
                gain.gain.setValueAtTime(0.0001, now + (idx * 0.12));
                gain.gain.exponentialRampToValueAtTime(0.18, now + (idx * 0.12) + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + (idx * 0.12) + 0.28);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(now + (idx * 0.12));
                osc.stop(now + (idx * 0.12) + 0.3);
            });
        } catch (e) {}
    }

    function showBrowserNotification(msg) {
        if (!bellEnabled || !('Notification' in window) || Notification.permission !== 'granted') return;
        if (document.visibilityState === 'visible') return;
        try {
            new Notification('Nova mensagem na aula', {
                body: (msg.user_nome || 'Aluno') + ': ' + (msg.mensagem || ''),
            });
        } catch (e) {}
    }

    function appendMessage(msg, shouldNotify) {
        const dt = new Date(msg.created_at.replace(' ', 'T'));
        const hora = isNaN(dt.getTime()) ? '' : dt.toLocaleString('pt-BR');
        const tipo = msg.user_tipo === 'aluno' ? 'Aluno' : 'Coordenação';
        const html = '<div class="mb-2 p-2 bg-white border border-gray-200 rounded">'
            + '<div class="text-xs text-gray-500">' + esc(tipo) + ' • ' + esc(msg.user_nome) + ' • ' + esc(hora) + '</div>'
            + '<div class="text-sm text-gray-800 mt-1">' + esc(msg.mensagem) + '</div>'
            + '</div>';
        chatBox.insertAdjacentHTML('beforeend', html);
        lastId = Math.max(lastId, parseInt(msg.id || 0, 10));
        if (shouldNotify && msg.user_tipo === 'aluno') {
            playBell();
            showBrowserNotification(msg);
        }
    }

    async function loadMessages() {
        const r = await fetch('<?= URL ?>/admin/aulas-online/chat/mensagens?aula_id=' + aulaId + '&after_id=' + lastId);
        const data = await r.json();
        if (!data.success || !Array.isArray(data.messages)) return;
        const shouldNotify = initialLoadDone;
        data.messages.forEach(function (msg) { appendMessage(msg, shouldNotify); });
        if (data.messages.length > 0) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
        initialLoadDone = true;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(form);
        const r = await fetch('<?= URL ?>/admin/aulas-online/chat/enviar', {
            method: 'POST',
            body: fd
        });
        const data = await r.json();
        if (data.success) {
            input.value = '';
            await loadMessages();
        }
    });

    if (enableBell) {
        enableBell.addEventListener('click', enableBellNotifications);
    }

    loadMessages();
    setInterval(loadMessages, 3000);
})();
</script>

<?php if ($canUseJitsiApi): ?>
<script src="<?= htmlspecialchars($jitsiExternalApiSrc) ?>"></script>
<script>
(function () {
    const parentNode = document.getElementById('jitsiRoom');
    const recordingStatus = document.getElementById('recordingStatus');
    if (!parentNode || typeof JitsiMeetExternalAPI === 'undefined') return;

    const api = new JitsiMeetExternalAPI(<?= json_encode($jitsiApiDomain) ?>, {
        roomName: <?= json_encode($jitsiApiRoom) ?>,
        parentNode: parentNode,
        width: '100%',
        height: '100%',
        jwt: <?= json_encode($jitsiApiJwt) ?>,
        configOverwrite: {
            prejoinConfig: { enabled: false },
            disableDeepLinking: true
        },
        interfaceConfigOverwrite: {
            SHOW_JITSI_WATERMARK: false
        },
        userInfo: {
            displayName: <?= json_encode((string) ($user['nome'] ?? 'Coordenação')) ?>,
            email: <?= json_encode((string) ($user['email'] ?? '')) ?>
        }
    });

    let recordingRequested = false;
    function markStatus(text, cls) {
        if (!recordingStatus) return;
        recordingStatus.textContent = text;
        recordingStatus.className = cls || 'text-xs text-gray-500 mt-2';
    }

    api.addListener('videoConferenceJoined', function () {
        if (recordingRequested) return;
        recordingRequested = true;
        setTimeout(function () {
            try {
                api.executeCommand('startRecording', {
                    mode: 'file',
                    shouldShare: true,
                    extraMetadata: {
                        aula_online_id: String(<?= (int) ($aula['id'] ?? 0) ?>)
                    }
                });
                markStatus('Gravação solicitada automaticamente. Ao encerrar, a JaaS enviará o arquivo para a plataforma.', 'text-xs text-emerald-700 mt-2');
            } catch (e) {
                markStatus('Não foi possível iniciar a gravação automaticamente. Use o menu da sala para iniciar manualmente.', 'text-xs text-red-600 mt-2');
            }
        }, 3500);
    });

    api.addListener('recordingStatusChanged', function (event) {
        if (event && event.on) {
            markStatus('Gravação em andamento.', 'text-xs text-red-700 mt-2');
        }
    });
})();
</script>
<?php endif; ?>
