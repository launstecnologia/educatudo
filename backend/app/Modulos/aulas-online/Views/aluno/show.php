<?php
$linkAula = trim((string) ($aula['link_aula'] ?? ''));
$jaasMeetingUrl = trim((string) ($jaas_meeting_url ?? ''));
$pandaPlayerUrl = trim((string) ($aula['panda_live_player'] ?? ''));
$recordingPlayerUrl = trim((string) ($aula['panda_recording_player'] ?? ''));
$recordingHlsUrl = trim((string) ($aula['panda_recording_hls'] ?? ''));
$jaasRecordingPath = trim((string) ($aula['jaas_recording_path'] ?? ''));
$jaasRecordingUrl = $jaasRecordingPath !== '' ? (URL . '/aluno/aulas-online/gravacao/' . (int) ($aula['id'] ?? 0)) : '';
$jitsiRecordingUrl = trim((string) ($aula['link_gravacao'] ?? ''));
$recordingUrl = $jaasRecordingUrl !== '' ? $jaasRecordingUrl : ($jitsiRecordingUrl !== '' ? $jitsiRecordingUrl : ($recordingPlayerUrl !== '' ? $recordingPlayerUrl : $recordingHlsUrl));
$platformLower = mb_strtolower((string) ($aula['plataforma'] ?? ''), 'UTF-8');
$isPanda = $platformLower === 'panda video live';
$isJitsi = $platformLower === 'jitsi meet';
$playerUrl = $isJitsi && $jaasMeetingUrl !== '' ? $jaasMeetingUrl : ($pandaPlayerUrl !== '' ? $pandaPlayerUrl : $linkAula);
$canEmbedPlayer = ($isPanda || $isJitsi) && $playerUrl !== '' && filter_var($playerUrl, FILTER_VALIDATE_URL);
$canEmbedRecording = $recordingUrl !== '' && filter_var($recordingUrl, FILTER_VALIDATE_URL);
$descricao = preg_replace('/(?:\R\s*)?\[Live Panda ID:[^\]]+\]\s*/u', '', (string) ($aula['descricao'] ?? ''));
$descricao = trim((string) $descricao);
$inicioTs = !empty($aula['inicio_em']) ? strtotime((string) $aula['inicio_em']) : false;
$fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
$nowTs = time();
$started = $inicioTs !== false && $nowTs >= $inicioTs;
$ended = $fimTs !== false && $nowTs > $fimTs;
$isLive = $started && !$ended;
$statusLabel = $isLive ? 'Aula ao vivo' : ($ended ? ($canEmbedRecording ? 'Gravação disponível' : 'Aula encerrada') : 'Aula agendada');
$statusClass = $isLive
    ? 'bg-red-100 text-red-700'
    : ($ended ? ($canEmbedRecording ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600') : 'bg-blue-100 text-blue-700');
?>

<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <a href="<?= URL ?>/aluno/aulas-online" class="text-sm text-blue-600 hover:underline">Voltar para aulas online</a>

        <div class="flex items-start justify-between gap-4 mt-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars((string) ($aula['titulo'] ?? 'Aula online')) ?></h1>
                <?php if ($descricao !== ''): ?>
                    <p class="text-sm text-gray-600 mt-2"><?= nl2br(htmlspecialchars($descricao)) ?></p>
                <?php endif; ?>
            </div>
            <span class="text-xs px-2 py-1 rounded-full <?= $statusClass ?>"><?= $statusLabel ?></span>
        </div>

        <div class="mt-4 text-sm text-gray-700 space-y-1">
            <div><strong>Início:</strong> <?= $inicioTs !== false ? date('d/m/Y H:i', $inicioTs) : '-' ?></div>
            <?php if ($fimTs !== false): ?>
                <div><strong>Término:</strong> <?= date('d/m/Y H:i', $fimTs) ?></div>
            <?php endif; ?>
            <?php if (!empty($aula['plataforma'])): ?>
                <div><strong>Plataforma:</strong> <?= htmlspecialchars((string) $aula['plataforma']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isLive): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <?php if ($canEmbedPlayer): ?>
                <div class="aspect-video w-full overflow-hidden rounded-xl border border-gray-200 bg-black">
                    <iframe
                        src="<?= htmlspecialchars($playerUrl) ?>"
                        class="w-full h-full border-0"
                        allow="camera; microphone; display-capture; accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowfullscreen
                        title="<?= htmlspecialchars((string) ($aula['titulo'] ?? 'Aula online')) ?>"
                    ></iframe>
                </div>
                <a href="<?= htmlspecialchars($playerUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center mt-2 text-sm text-blue-600 hover:underline">
                    <?= $isJitsi ? 'Abrir sala em nova aba' : 'Abrir player em nova aba' ?>
                </a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($linkAula !== '' ? $linkAula : '#') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Entrar na aula
                </a>
            <?php endif; ?>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div class="text-sm font-medium text-gray-800">Chat da aula</div>
                <button type="button" id="enableBell" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100">
                    Ativar sino
                </button>
            </div>
            <div class="h-56 overflow-y-auto border border-gray-200 rounded bg-white p-2 mb-2" id="chat-box-<?= (int) ($aula['id'] ?? 0) ?>"></div>
            <form class="chat-form flex gap-2" data-aula-id="<?= (int) ($aula['id'] ?? 0) ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <input type="text" name="mensagem" maxlength="2000" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Escreva sua dúvida..." required>
                <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Enviar</button>
            </form>
        </div>
    <?php elseif ($canEmbedRecording): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Gravação da aula</h2>
            <div class="aspect-video w-full overflow-hidden rounded-xl border border-gray-200 bg-black">
                <?php if ($recordingPlayerUrl !== ''): ?>
                    <iframe
                        src="<?= htmlspecialchars($recordingPlayerUrl) ?>"
                        class="w-full h-full border-0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowfullscreen
                        title="<?= htmlspecialchars((string) ($aula['titulo'] ?? 'Gravação da aula')) ?>"
                    ></iframe>
                <?php else: ?>
                    <video src="<?= htmlspecialchars($recordingUrl) ?>" class="w-full h-full" controls playsinline></video>
                <?php endif; ?>
            </div>
            <a href="<?= htmlspecialchars($recordingUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center mt-2 text-sm text-blue-600 hover:underline">
                Abrir gravação em nova aba
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <p class="text-sm text-gray-600">
                <?= $ended ? 'Esta aula já foi encerrada. A gravação ainda não está disponível.' : 'Esta aula ainda não começou. O botão de aula ao vivo aparecerá no horário agendado.' ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if ($isLive): ?>
<script>
(function () {
    const forms = Array.from(document.querySelectorAll('.chat-form'));
    const lastByAula = {};
    const enableBell = document.getElementById('enableBell');
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
                body: (msg.user_nome || 'Coordenação') + ': ' + (msg.mensagem || ''),
            });
        } catch (e) {}
    }

    function appendMessage(aulaId, msg, shouldNotify) {
        const box = document.getElementById('chat-box-' + aulaId);
        if (!box) return;
        const dt = new Date((msg.created_at || '').replace(' ', 'T'));
        const hora = isNaN(dt.getTime()) ? '' : dt.toLocaleString('pt-BR');
        const tipo = msg.user_tipo === 'aluno' ? 'Você' : 'Coordenação';
        box.insertAdjacentHTML('beforeend',
            '<div class="mb-2 p-2 border border-gray-100 rounded bg-gray-50">'
            + '<div class="text-xs text-gray-500">' + esc(tipo) + ' • ' + esc(msg.user_nome) + ' • ' + esc(hora) + '</div>'
            + '<div class="text-sm text-gray-800 mt-1">' + esc(msg.mensagem) + '</div>'
            + '</div>'
        );
        lastByAula[aulaId] = Math.max(lastByAula[aulaId] || 0, parseInt(msg.id || 0, 10));
        box.scrollTop = box.scrollHeight;
        if (shouldNotify && msg.user_tipo !== 'aluno') {
            playBell();
            showBrowserNotification(msg);
        }
    }

    async function loadMessages(aulaId) {
        const afterId = lastByAula[aulaId] || 0;
        const r = await fetch('<?= URL ?>/aluno/aulas-online/mensagens?aula_id=' + aulaId + '&after_id=' + afterId);
        const data = await r.json();
        if (!data.success || !Array.isArray(data.messages)) return;
        const shouldNotify = initialLoadDone;
        data.messages.forEach(function (msg) { appendMessage(aulaId, msg, shouldNotify); });
        initialLoadDone = true;
    }

    forms.forEach(function (form) {
        const aulaId = parseInt(form.getAttribute('data-aula-id') || '0', 10);
        if (!aulaId) return;
        lastByAula[aulaId] = 0;
        loadMessages(aulaId);
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const input = form.querySelector('input[name="mensagem"]');
            const fd = new FormData(form);
            fd.append('aula_id', String(aulaId));
            const r = await fetch('<?= URL ?>/aluno/aulas-online/enviar-mensagem', { method: 'POST', body: fd });
            const data = await r.json();
            if (data.success) {
                input.value = '';
                await loadMessages(aulaId);
            }
        });
    });

    if (enableBell) {
        enableBell.addEventListener('click', enableBellNotifications);
    }

    setInterval(function () {
        forms.forEach(function (form) {
            const aulaId = parseInt(form.getAttribute('data-aula-id') || '0', 10);
            if (aulaId) loadMessages(aulaId);
        });
    }, 4000);
})();
</script>
<?php endif; ?>
