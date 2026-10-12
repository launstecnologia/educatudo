<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><div><a href="<?= URL ?>/admin/reconhecimento-facial" class="text-sm font-semibold text-blue-700">← Painel facial</a><h1 class="mt-1 text-2xl font-bold text-slate-900">Totem de entrada e saída</h1><p class="text-slate-600">Posicione o aluno e confirme uma leitura por passagem.</p></div><div class="rounded-xl bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-900">Intervalo de segurança: 60 segundos</div></div>
    <?php if (empty($schema_ready) || empty($api_configured)): ?><div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900">Módulo indisponível: confira a migration e a configuração da API.</div><?php endif; ?>
    <div class="grid gap-6 xl:grid-cols-[1.3fr_.7fr]">
        <section class="overflow-hidden rounded-3xl bg-slate-950 shadow-2xl">
            <div class="relative aspect-video"><video id="camera" autoplay muted playsinline class="h-full w-full object-cover"></video><div class="pointer-events-none absolute inset-0 flex items-center justify-center"><div class="h-[75%] w-[46%] rounded-[45%] border-4 border-cyan-300 shadow-[0_0_0_999px_rgba(2,6,23,.25)]"></div></div></div>
            <div class="p-5"><button id="activate-camera" type="button" disabled class="mb-3 w-full rounded-2xl bg-cyan-600 px-6 py-4 text-lg font-bold text-white disabled:opacity-50">Ativar câmera</button><button id="scan" type="button" disabled class="btn-primary-custom w-full rounded-2xl px-6 py-4 text-lg font-bold disabled:opacity-50">Registrar entrada / saída</button><p id="status" class="mt-3 text-center text-sm text-slate-300">Carregando reconhecimento...</p></div>
        </section>
        <aside class="space-y-5">
            <div id="result" class="rounded-2xl border border-slate-200 bg-white p-7 text-center shadow-sm"><div id="result-icon" class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-slate-100 text-3xl text-slate-500"><i class="fa-solid fa-face-viewfinder"></i></div><h2 id="result-title" class="mt-4 text-xl font-bold text-slate-900">Aguardando leitura</h2><p id="result-message" class="mt-2 text-slate-600">O resultado aparecerá aqui.</p></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-bold text-slate-900">Últimos registros</h3><div id="history" class="mt-3 space-y-2"><?php foreach (($events ?? []) as $event): ?><div class="flex justify-between rounded-lg bg-slate-50 p-3 text-sm"><span class="font-semibold"><?= htmlspecialchars($event['student_name']) ?></span><span><?= $event['kind'] === 'entrada' ? 'Entrada' : 'Saída' ?> · <?= date('H:i', strtotime($event['event_at'])) ?></span></div><?php endforeach; ?></div></div>
        </aside>
    </div>
</div>
<script src="<?= URL ?>/public/vendor/face-api/face-api.min.js"></script>
<script>
(function () {
    var video = document.getElementById('camera'), scan = document.getElementById('scan'), activate = document.getElementById('activate-camera'), status = document.getElementById('status');
    var title = document.getElementById('result-title'), message = document.getElementById('result-message'), icon = document.getElementById('result-icon'), history = document.getElementById('history');
    var modelPath = <?= json_encode(URL . '/public/vendor/face-api/models') ?>, csrf = <?= json_encode($csrf_token) ?>;
    function result(state, heading, text) {
        var styles = state === 'entrada' ? ['bg-emerald-100 text-emerald-700', 'fa-arrow-right-to-bracket'] : state === 'saida' ? ['bg-blue-100 text-blue-700', 'fa-arrow-right-from-bracket'] : state === 'error' ? ['bg-red-100 text-red-700', 'fa-circle-xmark'] : ['bg-amber-100 text-amber-700', 'fa-face-frown'];
        icon.className = 'mx-auto flex h-20 w-20 items-center justify-center rounded-full text-3xl ' + styles[0]; icon.innerHTML = '<i class="fa-solid ' + styles[1] + '"></i>'; title.textContent = heading; message.textContent = text;
    }
    function beep(ok) { try { var C = window.AudioContext || window.webkitAudioContext, c = new C(), o = c.createOscillator(), g = c.createGain(); o.frequency.value = ok ? 880 : 220; g.gain.value = .08; o.connect(g); g.connect(c.destination); o.start(); o.stop(c.currentTime + .18); } catch (_) {} }
    async function init() {
        try {
            await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(modelPath), faceapi.nets.faceLandmark68Net.loadFromUri(modelPath), faceapi.nets.faceRecognitionNet.loadFromUri(modelPath)]);
            status.textContent = 'Modelos carregados. Clique em Ativar câmera.'; activate.disabled = false;
        } catch (e) { console.error(e); status.textContent = 'Modelos faciais indisponíveis'; result('error', 'Falha ao carregar reconhecimento', 'Atualize a página ou contate o suporte.'); }
    }
    activate.addEventListener('click', async function () {
        activate.disabled = true; status.textContent = 'Solicitando autorização da câmera...';
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error('Use um navegador compatível em uma conexão HTTPS.');
            video.srcObject = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 1280}}, audio: false});
            await video.play(); status.textContent = 'Câmera pronta'; scan.disabled = false; activate.classList.add('hidden');
        } catch (e) {
            console.error(e); activate.disabled = false; status.textContent = 'Câmera indisponível';
            var msg = e.name === 'NotAllowedError' ? 'Clique no cadeado do navegador e permita o uso da câmera.' : e.name === 'NotFoundError' ? 'Nenhuma câmera foi encontrada.' : (e.message || 'Não foi possível acessar a câmera.');
            result('error', 'Não foi possível abrir a câmera', msg);
        }
    });
    scan.addEventListener('click', async function () {
        scan.disabled = true; status.textContent = 'Identificando...'; result('wait', 'Aguarde', 'Mantenha o rosto centralizado.');
        try {
            var detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({inputSize: 416, scoreThreshold: .55})).withFaceLandmarks().withFaceDescriptor();
            if (!detection) throw new Error('Nenhum rosto detectado.');
            var response = await fetch(<?= json_encode(URL . '/admin/reconhecimento-facial/reconhecer') ?>, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'}, body: JSON.stringify({_token: csrf, descriptor: Array.from(detection.descriptor)})});
            var data = await response.json(); if (!response.ok) throw new Error(data.error || 'Falha no reconhecimento.');
            if (!data.match) { result('unknown', 'Rosto não reconhecido', data.message || 'Confira o cadastro facial.'); beep(false); }
            else if (!data.registered) { result('unknown', data.student_name || 'Leitura ignorada', data.message || 'Aguarde o intervalo de segurança.'); beep(false); }
            else {
                var label = data.kind === 'entrada' ? 'Entrada registrada' : 'Saída registrada';
                result(data.kind, label, data.student_name + ' · ' + data.time); beep(true);
                var row = document.createElement('div'); row.className = 'flex justify-between rounded-lg bg-slate-50 p-3 text-sm'; row.innerHTML = '<span class="font-semibold"></span><span></span>'; row.children[0].textContent = data.student_name; row.children[1].textContent = (data.kind === 'entrada' ? 'Entrada' : 'Saída') + ' · ' + data.time; history.prepend(row);
            }
        } catch (e) { result('error', 'Não foi possível registrar', e.message || 'Tente novamente.'); beep(false); }
        status.textContent = 'Pronto para a próxima leitura'; setTimeout(function () { scan.disabled = false; }, 3500);
    });
    init();
})();
</script>
