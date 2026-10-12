<?php $sampleCount = (int) ($profile['sample_count'] ?? 0); ?>
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div><a href="<?= URL ?>/admin/reconhecimento-facial" class="text-sm font-semibold text-blue-700">← Voltar</a><h1 class="mt-2 text-2xl font-bold text-slate-900">Cadastro facial</h1><p class="text-slate-600"><?= htmlspecialchars($student['nome']) ?> · <?= htmlspecialchars(trim(($student['class_grade'] ?? '') . ' ' . ($student['class_name'] ?? ''))) ?></p></div>
        <span id="sample-badge" class="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-800"><?= $sampleCount ?> amostra(s)</span>
    </div>

    <?php if (empty($api_configured)): ?><div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900">A API facial ainda não está configurada no servidor.</div><?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
        <section class="overflow-hidden rounded-2xl bg-slate-950 shadow-xl">
            <div class="relative aspect-video">
                <video id="camera" autoplay muted playsinline class="h-full w-full object-cover"></video>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center"><div class="h-[72%] w-[48%] rounded-[45%] border-4 border-white/80 shadow-[0_0_0_999px_rgba(15,23,42,.28)]"></div></div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 text-white"><span id="camera-status">Carregando modelos...</span><div class="flex gap-2"><button id="activate-camera" type="button" disabled class="rounded-xl bg-cyan-600 px-5 py-3 font-bold disabled:opacity-50">Ativar câmera</button><button id="capture" type="button" disabled class="rounded-xl bg-blue-600 px-5 py-3 font-bold disabled:cursor-not-allowed disabled:opacity-50">Capturar amostra</button></div></div>
        </section>

        <aside class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div><h2 class="font-bold text-slate-900">Orientações</h2><ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600"><li>Uma pessoa por vez e boa iluminação.</li><li>Rosto de frente, sem óculos escuros ou máscara.</li><li>Cadastre de 2 a 3 amostras em ângulos levemente diferentes.</li><li>Nenhuma foto será salva no EducaTudo.</li></ul></div>
            <label class="flex items-start gap-3 rounded-xl bg-blue-50 p-4 text-sm text-blue-950"><input id="consent" type="checkbox" class="mt-1 h-4 w-4"><span>Confirmo que o responsável autorizou o tratamento da biometria facial para controle de entrada e saída.</span></label>
            <div id="result" class="hidden rounded-xl p-4 text-sm font-semibold"></div>
            <?php if ($sampleCount > 0): ?><button id="delete-profile" type="button" class="w-full rounded-xl border border-red-300 px-4 py-2.5 font-semibold text-red-700 hover:bg-red-50">Excluir cadastro facial</button><?php endif; ?>
        </aside>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-5">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Registros de entrada e saída</h2>
                <p class="mt-1 text-sm text-slate-500">Últimas leituras faciais deste aluno.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700"><?= count($events ?? []) ?> registro(s)</span>
        </div>
        <?php if (empty($events)): ?>
            <div class="px-6 py-10 text-center">
                <i class="fa-regular fa-clock mb-3 text-3xl text-slate-300"></i>
                <p class="font-semibold text-slate-700">Nenhuma entrada ou saída registrada</p>
                <p class="mt-1 text-sm text-slate-500">Os registros aparecerão aqui depois que o aluno utilizar o leitor facial.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-6 py-3">Movimento</th><th class="px-6 py-3">Data e horário</th><th class="px-6 py-3">Confiança</th><th class="px-6 py-3">Notificação</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($events as $event): ?>
                            <?php $isEntry = ($event['kind'] ?? '') === 'entrada'; ?>
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4"><span class="inline-flex items-center rounded-full px-3 py-1 font-semibold <?= $isEntry ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><i class="fa-solid <?= $isEntry ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket' ?> mr-2"></i><?= $isEntry ? 'Entrada' : 'Saída' ?></span></td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-slate-800"><?= date('d/m/Y \à\s H:i', strtotime((string) $event['event_at'])) ?></td>
                                <td class="whitespace-nowrap px-6 py-4 text-slate-600"><?= $event['confidence'] !== null ? number_format((float) $event['confidence'] * 100, 1, ',', '.') . '%' : '—' ?></td>
                                <td class="whitespace-nowrap px-6 py-4"><span class="font-semibold <?= !empty($event['notified_at']) ? 'text-emerald-700' : 'text-slate-500' ?>"><i class="fa-solid <?= !empty($event['notified_at']) ? 'fa-check-circle' : 'fa-clock' ?> mr-1"></i><?= !empty($event['notified_at']) ? 'Enviada aos pais' : 'Pendente' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script src="<?= URL ?>/public/vendor/face-api/face-api.min.js"></script>
<script>
(function () {
    var video = document.getElementById('camera');
    var status = document.getElementById('camera-status');
    var capture = document.getElementById('capture');
    var activate = document.getElementById('activate-camera');
    var result = document.getElementById('result');
    var consent = document.getElementById('consent');
    var modelPath = <?= json_encode(URL . '/public/vendor/face-api/models') ?>;
    var csrf = <?= json_encode($csrf_token) ?>;
    var sampleEndpoint = window.location.pathname.replace(/\/+$/, '') + '/amostras';
    var profileEndpoint = window.location.pathname.replace(/\/+$/, '');
    function show(message, ok) {
        result.className = 'rounded-xl p-4 text-sm font-semibold ' + (ok ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800');
        result.textContent = message; result.classList.remove('hidden');
    }
    async function requestJson(endpoint, options) {
        options = options || {};
        var requestOptions = Object.assign({
            credentials: 'same-origin',
            cache: 'no-store'
        }, options);
        requestOptions.headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }, options.headers || {});
        var response = await fetch(endpoint, requestOptions);
        var raw = await response.text();
        var data = null;
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch (parseError) {
            console.error('Resposta inválida do cadastro facial', {
                status: response.status,
                contentType: response.headers.get('content-type'),
                body: raw.slice(0, 500)
            });
            if (response.redirected || response.status === 401 || response.status === 403) {
                throw new Error('Sua sessão expirou ou não possui permissão. Atualize a página e entre novamente.');
            }
            throw new Error('O servidor não conseguiu processar a amostra (HTTP ' + response.status + ').');
        }
        if (!response.ok || !data.success) {
            throw new Error(data.error || data.message || ('Falha no servidor (HTTP ' + response.status + ').'));
        }
        return data;
    }
    async function init() {
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelPath)
            ]);
            status.textContent = 'Modelos carregados. Clique em Ativar câmera.'; activate.disabled = false;
        } catch (e) { console.error(e); status.textContent = 'Falha ao carregar modelos faciais'; show('Os arquivos do reconhecimento não foram carregados. Atualize a página ou contate o suporte.', false); }
    }
    activate.addEventListener('click', async function () {
        activate.disabled = true; status.textContent = 'Solicitando autorização da câmera...';
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error('Este navegador não oferece acesso à câmera. Use Chrome, Edge ou Safari em HTTPS.');
            var stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 1280}}, audio: false});
            video.srcObject = stream; await video.play(); status.textContent = 'Câmera pronta'; capture.disabled = false; activate.classList.add('hidden');
        } catch (e) {
            console.error(e); activate.disabled = false; status.textContent = 'Não foi possível abrir a câmera';
            var msg = e.name === 'NotAllowedError' ? 'A câmera foi bloqueada. Clique no cadeado ao lado do endereço, permita a câmera e tente novamente.' : e.name === 'NotFoundError' ? 'Nenhuma câmera foi encontrada neste dispositivo.' : (e.message || 'Não foi possível acessar a câmera.');
            show(msg, false);
        }
    });
    capture.addEventListener('click', async function () {
        if (!consent.checked) { show('Confirme o consentimento antes de cadastrar.', false); return; }
        capture.disabled = true; status.textContent = 'Detectando rosto...';
        var detection;
        try {
            detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({inputSize: 416, scoreThreshold: 0.55})).withFaceLandmarks().withFaceDescriptor();
            if (!detection) throw new Error('Nenhum rosto detectado. Centralize o rosto e tente novamente.');
        } catch (e) {
            console.error('Falha na detecção facial', e);
            show(e.message || 'Não foi possível ler o rosto. Ajuste a posição e tente novamente.', false);
            status.textContent = 'Tente novamente'; capture.disabled = false; return;
        }
        status.textContent = 'Enviando amostra...';
        try {
            var form = new URLSearchParams();
            form.set('_token', csrf);
            form.set('consent', '1');
            form.set('descriptor', Array.from(detection.descriptor, function (value) { return Number(value).toFixed(8); }).join(','));
            var data = await requestJson(sampleEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: form.toString()
            });
            document.getElementById('sample-badge').textContent = data.sample_count + ' amostra(s)'; show(data.message, true); status.textContent = 'Amostra cadastrada';
        } catch (e) { console.error('Falha ao enviar amostra facial', e); show(e.message || 'Não foi possível cadastrar.', false); status.textContent = 'Tente novamente'; }
        capture.disabled = false;
    });
    var del = document.getElementById('delete-profile');
    if (del) del.addEventListener('click', async function () {
        if (!confirm('Excluir todas as amostras faciais deste aluno?')) return;
        try {
            await requestJson(profileEndpoint, {method: 'DELETE', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({_token: csrf})});
            location.href = <?= json_encode(URL . '/admin/reconhecimento-facial') ?>;
        } catch (e) { show(e.message || 'Falha ao excluir.', false); }
    });
    init();
})();
</script>
