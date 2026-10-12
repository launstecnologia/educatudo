<?php
/**
 * Módulo de Treino de Conversação em Inglês
 * - Falar → transcrição (Whisper) → correção + resposta (LLM) → áudio da resposta (ElevenLabs)
 */
?>
<!-- Header -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Treino de Conversação em Inglês</h1>
            <p class="text-gray-600 mt-2">Fale em inglês, receba correção e continue a conversa. Foco em speaking e listening.</p>
        </div>
    </div>
</div>

<!-- Área de Chat -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-gray-900 mb-1">Conversação</h2>
        <p class="text-sm text-gray-600">Pressione o microfone, fale em inglês e ouça a resposta da Jude.</p>
    </div>

    <!-- Mensagens -->
    <div id="conversationArea" class="bg-gray-50 rounded-lg p-4 mb-4 min-h-[360px] max-h-[480px] overflow-y-auto">
        <div id="messagesContainer" class="space-y-4">
            <div class="flex items-start space-x-3">
                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-sm font-bold">Jude</span>
                </div>
                <div class="flex-1 bg-indigo-100 rounded-lg p-3">
                    <p class="text-gray-800">Hello! I'm Jude, your English conversation teacher. Press the microphone and say something in English. I'll correct you and we'll keep talking. Speak in English only.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão de Microfone com estados -->
    <div class="flex flex-col items-center gap-3">
        <div class="flex items-center gap-4">
            <button type="button" id="recordButton" data-state="idle"
                    class="flex items-center justify-center w-20 h-20 rounded-full transition-all duration-200 shadow-lg focus:outline-none focus:ring-4 focus:ring-indigo-300"
                    title="Clique para falar em inglês">
                <svg id="iconIdle" class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"></path>
                    <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"></path>
                </svg>
                <svg id="iconRecording" class="w-10 h-10 text-white hidden" fill="currentColor" viewBox="0 0 24 24">
                    <rect x="6" y="6" width="12" height="12" rx="2"/>
                </svg>
                <svg id="iconProcessing" class="w-10 h-10 text-white hidden animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg id="iconSpeaking" class="w-10 h-10 text-white hidden" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path>
                </svg>
            </button>
            <div id="micStatus" class="text-sm font-medium text-gray-600 min-w-[140px]">Clique para falar</div>
        </div>
        <p id="recordingTime" class="text-xs text-gray-500 hidden">00:00</p>
    </div>

    <!-- Opção: digitar (fallback) -->
    <div class="mt-4 pt-4 border-t border-gray-200">
        <p class="text-xs text-gray-500 mb-2">Ou digite em inglês:</p>
        <div class="flex gap-2">
            <input type="text" id="textInput" placeholder="Digite em inglês..."
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm">
            <button type="button" id="sendTextButton" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors">
                Enviar
            </button>
        </div>
    </div>
</div>

<!-- Histórico de conversas -->
<?php if (!empty($historico)): ?>
<div class="bg-white rounded-xl shadow-lg p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Conversas anteriores</h2>
    <div class="space-y-2">
        <?php foreach ($historico as $conv): ?>
        <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors cursor-pointer" onclick="carregarConversa(<?= (int)$conv['id'] ?>)">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900">Conversa #<?= (int)$conv['id'] ?></p>
                    <p class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?></p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    const URL_BASE = '<?= URL ?>';
    const CSRF = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>';
    const STUDENT_AVATAR_URL = <?= !empty($avatar_url) ? json_encode($avatar_url) : 'null' ?>;
    const STUDENT_NAME = <?= isset($user['nome']) ? json_encode($user['nome']) : '""' ?>;

    let mediaRecorder;
    let audioChunks = [];
    let recordingTimer = null;
    let recordingSeconds = 0;
    let currentConversaId = null;

    const recordButton = document.getElementById('recordButton');
    const iconIdle = document.getElementById('iconIdle');
    const iconRecording = document.getElementById('iconRecording');
    const iconProcessing = document.getElementById('iconProcessing');
    const iconSpeaking = document.getElementById('iconSpeaking');
    const micStatus = document.getElementById('micStatus');
    const recordingTimeEl = document.getElementById('recordingTime');
    const messagesContainer = document.getElementById('messagesContainer');
    const conversationArea = document.getElementById('conversationArea');
    const textInput = document.getElementById('textInput');
    const sendTextButton = document.getElementById('sendTextButton');

    function setMicState(state) {
        recordButton.dataset.state = state;
        recordButton.disabled = (state === 'processing' || state === 'speaking');
        [iconIdle, iconRecording, iconProcessing, iconSpeaking].forEach(el => el.classList.add('hidden'));
        recordingTimeEl.classList.add('hidden');
        const states = {
            idle: () => {
                iconIdle.classList.remove('hidden');
                recordButton.className = 'flex items-center justify-center w-20 h-20 rounded-full transition-all duration-200 shadow-lg focus:outline-none focus:ring-4 focus:ring-indigo-300 bg-red-500 hover:bg-red-600';
                micStatus.textContent = 'Clique para falar';
            },
            recording: () => {
                iconRecording.classList.remove('hidden');
                recordButton.className = 'flex items-center justify-center w-20 h-20 rounded-full transition-all duration-200 shadow-lg bg-red-600 animate-pulse';
                micStatus.textContent = 'Gravando...';
                recordingTimeEl.classList.remove('hidden');
            },
            processing: () => {
                iconProcessing.classList.remove('hidden');
                recordButton.className = 'flex items-center justify-center w-20 h-20 rounded-full transition-all duration-200 shadow-lg bg-yellow-500';
                micStatus.textContent = 'Processando...';
            },
            speaking: () => {
                iconSpeaking.classList.remove('hidden');
                recordButton.className = 'flex items-center justify-center w-20 h-20 rounded-full transition-all duration-200 shadow-lg bg-indigo-500';
                micStatus.textContent = 'Ouvindo resposta...';
            }
        };
        if (states[state]) states[state]();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function appendMessage(role, payload, audioBase64, autoPlayTTS) {
        if (typeof autoPlayTTS === 'undefined') autoPlayTTS = false;
        let textToSpeak = '';
        const div = document.createElement('div');
        div.className = 'flex items-start space-x-3';
        if (role === 'user') {
            const safeAvatarUrl = STUDENT_AVATAR_URL ? String(STUDENT_AVATAR_URL).replace(/"/g, '&quot;') : '';
            const avatarHtml = STUDENT_AVATAR_URL
                ? `<img src="${safeAvatarUrl}" alt="Você" class="w-8 h-8 rounded-full object-cover flex-shrink-0">`
                : `<div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0"><span class="text-white text-xs font-bold">${STUDENT_NAME ? escapeHtml(STUDENT_NAME.charAt(0).toUpperCase()) : 'V'}</span></div>`;
            div.innerHTML = `
                <div class="flex-1"></div>
                <div class="flex-1 max-w-[85%] bg-green-100 rounded-lg p-3">
                    <p class="text-gray-800">${escapeHtml(payload)}</p>
                </div>
                ${avatarHtml}
            `;
        } else {
            const r = typeof payload === 'object' ? payload : { natural_response: payload, original_sentence: '', corrected_sentence: '', tip: '', tts_script: '' };
            let bloco = '';
            if (r.original_sentence) bloco += `<p class="text-sm mt-1"><span class="text-gray-500">You said:</span> ${escapeHtml(r.original_sentence)}</p>`;
            if (r.corrected_sentence) bloco += `<p class="text-sm mt-1"><span class="text-green-700 font-medium">Corrected:</span> ${escapeHtml(r.corrected_sentence)}</p>`;
            if (r.tip) bloco += `<p class="text-sm mt-1 text-amber-700">💡 ${escapeHtml(r.tip)}</p>`;
            bloco += `<p class="text-gray-800 mt-2">${escapeHtml(r.natural_response)}</p>`;
            const audioId = 'audio_' + Date.now();
            const ttsBtnId = 'tts_' + Date.now();
            const translateBtnId = 'tr_' + Date.now();
            const translateBoxId = 'trbox_' + Date.now();
            textToSpeak = (r.tts_script && r.tts_script.trim()) ? r.tts_script.trim() : (r.natural_response || '');
            let audioHtml = '';
            if (audioBase64 && (r.natural_response || r.tts_script)) {
                audioHtml = `
                    <audio id="${audioId}" class="mt-2 w-full max-w-md" controls>
                        <source src="data:audio/mpeg;base64,${audioBase64}" type="audio/mpeg">
                    </audio>
                `;
            } else if (textToSpeak && typeof speechSynthesis !== 'undefined') {
                audioHtml = `<button type="button" id="${ttsBtnId}" class="mt-2 inline-flex items-center gap-1 px-2 py-1 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">🔊 Listen</button>`;
            }
            const translateBtnHtml = `<button type="button" id="${translateBtnId}" class="mt-2 inline-flex items-center gap-1 px-2 py-1 rounded bg-slate-600 text-white text-sm hover:bg-slate-700">🇧🇷 Traduzir para português</button><div id="${translateBoxId}" class="mt-2 hidden p-2 bg-slate-200 rounded text-gray-800 text-sm border border-slate-300"></div>`;
            div.innerHTML = `
                <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-white text-xs font-bold">Jude</span>
                </div>
                <div class="flex-1 max-w-[85%] bg-indigo-100 rounded-lg p-3">
                    ${bloco}
                    ${audioHtml}
                    ${translateBtnHtml}
                </div>
            `;
            div.querySelector('.flex-1').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            const translateBtn = div.querySelector('[id="' + translateBtnId + '"]');
            const translateBox = div.querySelector('[id="' + translateBoxId + '"]');
            if (translateBtn && translateBox) {
                const fullText = [r.original_sentence && ('You said: ' + r.original_sentence), r.corrected_sentence && ('Corrected: ' + r.corrected_sentence), r.tip && ('Tip: ' + r.tip), r.natural_response].filter(Boolean).join('\n\n');
                translateBtn.addEventListener('click', async () => {
                    if (translateBox.dataset.loaded === '1') {
                        translateBox.classList.toggle('hidden');
                        return;
                    }
                    translateBtn.disabled = true;
                    translateBtn.textContent = 'Traduzindo...';
                    try {
                        const fd = new FormData();
                        fd.append('texto', fullText);
                        fd.append('_token', CSRF);
                        const res = await fetch(URL_BASE + '/ingles/traduzir', { method: 'POST', body: fd });
                        const data = await res.json().catch(() => ({}));
                        if (data.success && data.traducao) {
                            translateBox.textContent = data.traducao;
                            translateBox.classList.remove('hidden');
                            translateBox.dataset.loaded = '1';
                        } else {
                            translateBox.textContent = 'Não foi possível traduzir. Tente de novo.';
                            translateBox.classList.remove('hidden');
                        }
                    } catch (e) {
                        translateBox.textContent = 'Erro ao traduzir.';
                        translateBox.classList.remove('hidden');
                    }
                    translateBtn.disabled = false;
                    translateBtn.textContent = '🇧🇷 Traduzir para português';
                });
            }
        }
        messagesContainer.appendChild(div);
        conversationArea.scrollTop = conversationArea.scrollHeight;

        const audioEl = div.querySelector('audio');
        if (audioEl) {
            setMicState('speaking');
            audioEl.play().catch(() => setMicState('idle'));
            audioEl.onended = () => setMicState('idle');
        } else {
            const ttsBtn = div.querySelector('[id^="tts_"]');
            if (ttsBtn && textToSpeak) {
                ttsBtn.addEventListener('click', () => playTTS(textToSpeak));
                if (autoPlayTTS) {
                    setMicState('speaking');
                    playTTS(textToSpeak, () => setMicState('idle'));
                } else {
                    setMicState('idle');
                }
            } else {
                setMicState('idle');
            }
        }
    }

    function playTTS(text, onEnd) {
        if (!text || typeof speechSynthesis === 'undefined') { if (onEnd) onEnd(); return; }
        speechSynthesis.cancel();
        const u = new SpeechSynthesisUtterance(text);
        u.lang = 'en-US';
        u.rate = 0.95;
        const voices = speechSynthesis.getVoices();
        const en = voices.find(v => v.lang.startsWith('en')); if (en) u.voice = en;
        u.onend = () => { if (onEnd) onEnd(); };
        u.onerror = () => { if (onEnd) onEnd(); };
        if (voices.length === 0) {
            speechSynthesis.onvoiceschanged = () => { speechSynthesis.speak(u); };
            return;
        }
        speechSynthesis.speak(u);
    }

    recordButton.addEventListener('click', async () => {
        const state = recordButton.dataset.state;
        if (state === 'recording') {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
            setMicState('idle');
            if (recordingTimer) { clearInterval(recordingTimer); recordingTimer = null; }
            return;
        }
        if (state !== 'idle') return;

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = e => { if (e.data.size) audioChunks.push(e.data); };
            mediaRecorder.onstop = async () => {
                stream.getTracks().forEach(t => t.stop());
                setMicState('processing');
                const blob = new Blob(audioChunks, { type: 'audio/webm' });
                const fd = new FormData();
                fd.append('audio', blob, 'recording.webm');
                fd.append('_token', CSRF);
                try {
                    const res = await fetch(URL_BASE + '/ingles/transcrever-audio', { method: 'POST', body: fd });
                    const raw = await res.text();
                    let data = {};
                    try { data = raw ? JSON.parse(raw) : {}; } catch (e) {}
                    if (!res.ok) {
                        throw new Error(data.error || (res.status === 404 ? 'Serviço de transcrição não encontrado (404). Verifique a URL do sistema.' : 'Erro ' + res.status + ' ao transcrever.'));
                    }
                    if (!data.success) throw new Error(data.error || 'Erro ao transcrever');
                    let texto = (data.texto || '').trim();
                    if (!texto) {
                        setMicState('idle');
                        appendMessage('assistant', { natural_response: 'I didn\'t catch that. Please try again and speak in English.' }, null);
                        return;
                    }
                    await sendAndShow(texto);
                } catch (err) {
                    setMicState('idle');
                    showErrorMessage(err);
                    console.error(err);
                }
            };
            mediaRecorder.start();
            setMicState('recording');
            recordingSeconds = 0;
            recordingTimeEl.textContent = '00:00';
            recordingTimer = setInterval(() => {
                recordingSeconds++;
                const m = Math.floor(recordingSeconds / 60);
                const s = recordingSeconds % 60;
                recordingTimeEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            }, 1000);
        } catch (err) {
            console.error(err);
            alert('Não foi possível acessar o microfone. Verifique as permissões.');
        }
    });

    function showErrorMessage(err) {
        let msg = (err && err.message) ? String(err.message) : 'Sorry, something went wrong. Please try again.';
        if (msg.indexOf('OPENAI_API_KEY') !== -1 || msg.indexOf('API key') !== -1 || msg.indexOf('não configurada') !== -1) {
            msg = 'The conversation service is not configured yet. Please ask your teacher or administrator to set up the API keys (OpenAI and ElevenLabs) in the system settings.';
        } else if (msg.indexOf('ElevenLabs') !== -1) {
            msg = 'Voice service is not configured. You can keep chatting; only the written answer will be shown. If you need voice, ask the administrator to set the ElevenLabs API key.';
        } else if (msg.length > 200) {
            msg = msg.substring(0, 200) + '…';
        }
        appendMessage('assistant', { natural_response: msg }, null);
    }

    async function sendAndShow(texto) {
        appendMessage('user', texto);
        const fd = new FormData();
        fd.append('texto', texto);
        fd.append('conversa_id', currentConversaId || '');
        fd.append('_token', CSRF);
        const res = await fetch(URL_BASE + '/ingles/conversar', { method: 'POST', body: fd });
        const data = await res.json().catch(() => ({}));
        if (!data.success) throw new Error(data.error || 'Erro ao conversar');
        if (data.conversa_id) currentConversaId = data.conversa_id;
        appendMessage('assistant', data.resposta, data.audio, true);
        if (!data.audio && !(data.resposta && data.resposta.natural_response && typeof speechSynthesis !== 'undefined')) setMicState('idle');
    }

    sendTextButton.addEventListener('click', () => {
        const texto = textInput.value.trim();
        if (!texto) return;
        textInput.value = '';
        setMicState('processing');
        sendAndShow(texto).catch(err => {
            setMicState('idle');
            showErrorMessage(err);
        });
    });
    textInput.addEventListener('keypress', e => { if (e.key === 'Enter') sendTextButton.click(); });

    async function carregarConversa(conversaId) {
        currentConversaId = conversaId;
        messagesContainer.innerHTML = '';
        setMicState('idle');
        try {
            const res = await fetch(URL_BASE + '/ingles/conversa/' + conversaId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.success || !data.mensagens || data.mensagens.length === 0) {
                appendMessage('assistant', { natural_response: 'Let\'s continue our conversation! Press the microphone and speak in English.' }, null);
                return;
            }
            data.mensagens.forEach(function(msg) {
                if (msg.role === 'user') {
                    appendMessage('user', msg.conteudo || '');
                } else {
                    let payload = { natural_response: msg.conteudo || '', original_sentence: '', corrected_sentence: '', tip: '' };
                    try {
                        const parsed = JSON.parse(msg.conteudo || '{}');
                        if (parsed && typeof parsed.natural_response !== 'undefined') payload = parsed;
                    } catch (e) {}
                    appendMessage('assistant', payload, null);
                }
            });
            conversationArea.scrollTop = conversationArea.scrollHeight;
        } catch (err) {
            console.error(err);
            appendMessage('assistant', { natural_response: 'Could not load this conversation. Please try again.' }, null);
        }
    }

    setMicState('idle');
})();
</script>
