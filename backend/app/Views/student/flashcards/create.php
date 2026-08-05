<?php
$error = $error ?? null;
$success_message = $success_message ?? null;
$topic = $topic ?? '';
$grade = $grade ?? ($grade_suggested ?? '');
$quantity = isset($quantity) ? (int) $quantity : 5;
$csrf_token = $csrf_token ?? '';
$grade_suggested = $grade_suggested ?? '';
$iaName = LayoutHelper::getIaName();
?>
<div class="container mx-auto px-4 py-6 max-w-5xl w-full">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="<?= URL ?>/flashcards" class="text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1 mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Meus baralhos
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Criar Flashcards</h1>
            <p class="text-gray-600 mt-1">Digite o tema e/ou <strong>cole prints do conteúdo</strong> abaixo. O OCR lê o texto das imagens e envia tudo junto ao prompt da <?= htmlspecialchars($iaName) ?> para gerar flashcards mais ricos. A série vem da sua turma.</p>
        </div>
        <a href="<?= URL ?>/flashcards" class="text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1 self-start">
            Ver histórico
        </a>
    </div>

    <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg border border-red-300"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg border border-green-300"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
        <form method="POST" action="<?= URL ?>/flashcards/generate" class="space-y-5" id="form-flashcards" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div>
                <label for="topic" class="block text-sm font-semibold text-gray-700 mb-2">Tema</label>
                <input type="text" id="topic" name="topic"
                       value="<?= htmlspecialchars($topic) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="Ex: Segunda Guerra Mundial, Funções de 1º grau">
            </div>

            <!-- Área: colar prints para OCR → envia junto ao prompt e enriquece o conteúdo -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Cole prints do conteúdo (OCR)</label>
                <p class="text-xs text-gray-500 mb-2">Cole prints com <strong>Ctrl+V</strong> ou envie várias imagens. O OCR (Google) lê o texto e envia <strong>junto ao prompt</strong> para a <?= htmlspecialchars($iaName) ?> gerar cards com base nesse conteúdo. Combine com o tema acima para ficar mais rico.</p>
                <div id="ocr-drop-zone" class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer min-h-[120px]">
                    <input type="file" id="ocr-files" name="images[]" accept="image/*" multiple class="hidden">
                    <p class="text-gray-600 mb-1">Clique para escolher imagens ou <strong>cole aqui</strong> (Ctrl+V)</p>
                    <p class="text-sm text-gray-500">Várias imagens permitidas — o texto de todas é lido e enviado junto ao prompt</p>
                </div>
                <div id="ocr-previews" class="mt-3 flex flex-wrap gap-2"></div>
                <div id="ocr-base64-container"></div>
            </div>

            <div>
                <label for="grade" class="block text-sm font-semibold text-gray-700 mb-2">Série (automática)</label>
                <input type="text" id="grade" name="grade"
                       value="<?= htmlspecialchars($grade) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50"
                       placeholder="<?= htmlspecialchars($grade_suggested) ? '' : 'Sua turma será usada' ?>">
                <p class="text-xs text-gray-500 mt-1">Preenchida automaticamente pela sua turma. Você pode editar se quiser.</p>
            </div>

            <div>
                <label for="quantity" class="block text-sm font-semibold text-gray-700 mb-2">Quantidade de cards</label>
                <input type="number" id="quantity" name="quantity" min="1" max="30" value="<?= $quantity ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <p class="text-xs text-gray-500 mt-1">Entre 1 e 30.</p>
            </div>

            <div class="pt-2 relative">
                <?php
                $aiBtnLabel = 'Gerar flashcards';
                $aiBtnLoadingLabel = 'Gerando…';
                $aiBtnId = 'btn-submit';
                $aiBtnClass = 'w-full py-3 px-4 font-semibold rounded-lg min-h-[48px]';
                include __DIR__ . '/../../components/ai-btn-primary.php';
                ?>
            </div>
        </form>
    </div>
</div>

<?php
$aiLoadingSkipMarkup = true;
include __DIR__ . '/../../components/ai-loading.php';
?>
<script>
(function() {
    var dropZone = document.getElementById('ocr-drop-zone');
    var fileInput = document.getElementById('ocr-files');
    var previews = document.getElementById('ocr-previews');
    var base64Container = document.getElementById('ocr-base64-container');
    var form = document.getElementById('form-flashcards');
    var topicInput = document.getElementById('topic');
    var pastedImages = [];

    function addImage(fileOrDataUrl) {
        if (typeof fileOrDataUrl === 'string') {
            pastedImages.push(fileOrDataUrl);
        } else if (fileOrDataUrl && fileOrDataUrl.type && fileOrDataUrl.type.indexOf('image/') === 0) {
            var fr = new FileReader();
            fr.onload = function() {
                pastedImages.push(fr.result);
                renderPreviews();
                syncBase64Inputs();
            };
            fr.readAsDataURL(fileOrDataUrl);
        }
    }

    function renderPreviews() {
        previews.innerHTML = '';
        pastedImages.forEach(function(dataUrl, i) {
            var wrap = document.createElement('div');
            wrap.className = 'relative inline-block';
            var img = document.createElement('img');
            img.src = dataUrl;
            img.className = 'w-16 h-16 object-cover rounded border border-gray-300';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs leading-none flex items-center justify-center';
            btn.textContent = '×';
            btn.onclick = function() {
                pastedImages.splice(i, 1);
                renderPreviews();
                syncBase64Inputs();
            };
            wrap.appendChild(img);
            wrap.appendChild(btn);
            previews.appendChild(wrap);
        });
    }

    function syncBase64Inputs() {
        base64Container.innerHTML = '';
        pastedImages.forEach(function(dataUrl) {
            var base64 = dataUrl.indexOf('base64,') >= 0 ? dataUrl.split('base64,')[1] : dataUrl;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'images_base64[]';
            input.value = base64;
            base64Container.appendChild(input);
        });
    }

    dropZone.addEventListener('click', function() { fileInput.click(); });
    dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('border-green-400', 'bg-green-50'); });
    dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('border-green-400', 'bg-green-50'); });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('border-green-400', 'bg-green-50');
        var files = e.dataTransfer.files;
        for (var i = 0; i < files.length; i++) {
            if (files[i].type.indexOf('image/') === 0) addImage(files[i]);
        }
    });

    document.addEventListener('paste', function(e) {
        if (!e.clipboardData || !e.clipboardData.items) return;
        for (var i = 0; i < e.clipboardData.items.length; i++) {
            var item = e.clipboardData.items[i];
            if (item.type.indexOf('image/') === 0) {
                e.preventDefault();
                addImage(item.getAsFile());
                break;
            }
        }
    });

    fileInput.addEventListener('change', function() {
        var files = fileInput.files;
        for (var i = 0; i < files.length; i++) {
            if (files[i].type.indexOf('image/') === 0) addImage(files[i]);
        }
        fileInput.value = '';
    });

    form.addEventListener('submit', function() {
        syncBase64Inputs();
        if (pastedImages.length > 0) {
            document.getElementById('topic').removeAttribute('required');
        }
        EducaAiLoading.setButtonLoading(document.getElementById('btn-submit'), true);
    });
})();
</script>
