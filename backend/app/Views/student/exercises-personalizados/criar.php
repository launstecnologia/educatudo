<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <a href="<?= URL ?>/exercicios-personalizados" class="text-accent hover:opacity-80 flex items-center mb-4">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Voltar
        </a>
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Criar Exercícios Personalizados</h1>
        <p class="text-lg text-gray-600">Preencha os campos abaixo para gerar seus exercícios com IA</p>
    </div>

    <div class="bg-white rounded-2xl shadow-lg p-8">
        <form id="createExerciseForm" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <!-- Tema -->
            <div>
                <label for="tema" class="block text-sm font-semibold text-gray-700 mb-2">
                    Tema dos Exercícios
                </label>
                <input type="text" id="tema" name="tema" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex: Reforma Protestante, Segunda Guerra Mundial, Células, etc.">
                <p class="mt-1 text-sm text-gray-500">Digite o tema específico que deseja estudar.</p>
            </div>

            <!-- Matéria -->
            <div>
                <label for="materia" class="block text-sm font-semibold text-gray-700 mb-2">
                    Matéria
                </label>
                <select id="materia" name="materia" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione uma matéria</option>
                    <?php foreach ($materias as $materia): ?>
                        <?php $materia_val = is_array($materia) ? $materia['materia'] : $materia; ?>
                        <option value="<?= htmlspecialchars($materia_val) ?>" <?= $materia_val === 'Outros' ? 'style="font-weight: bold;"' : '' ?>><?= htmlspecialchars($materia_val) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Quantidade por dificuldade -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3">Quantidade por dificuldade</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="quantidade_facil" class="block text-sm font-medium text-gray-700 mb-1">Fácil</label>
                        <input type="number" id="quantidade_facil" name="quantidade_facil" min="0" max="30" value="0"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="quantidade_medio" class="block text-sm font-medium text-gray-700 mb-1">Médio</label>
                        <input type="number" id="quantidade_medio" name="quantidade_medio" min="0" max="30" value="0"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="quantidade_dificil" class="block text-sm font-medium text-gray-700 mb-1">Difícil</label>
                        <input type="number" id="quantidade_dificil" name="quantidade_dificil" min="0" max="30" value="0"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-500">Informe quantas questões deseja em cada nível (mínimo total 3 e máximo 30).</p>
            </div>

            <?php
            $aiBtnLabel = 'Gerar Exercícios com IA';
            $aiBtnLoadingLabel = 'Gerando...';
            $aiBtnId = 'btnGerarExercicios';
            include __DIR__ . '/../../components/ai-btn-primary.php';
            ?>
        </form>
    </div>
</div>

<?php
$aiLoadingId = 'generatingModal';
$aiLoadingTitle = 'Gerando Exercícios';
$aiLoadingMessage = 'A IA está criando exercícios personalizados para você...';
$aiLoadingStatus = 'Aguarde enquanto processamos sua solicitação...';
$aiLoadingClosable = false;
include __DIR__ . '/../../components/ai-loading.php';
?>

<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>
<script>
function mostrarErro(mensagem) {
    const erroExistente = document.getElementById('erroModal');
    if (erroExistente) {
        erroExistente.remove();
    }

    const modalErro = document.createElement('div');
    modalErro.id = 'erroModal';
    modalErro.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';

    const card = document.createElement('div');
    card.className = 'bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4';

    const header = document.createElement('div');
    header.className = 'flex items-center justify-between mb-4';

    const title = document.createElement('h3');
    title.className = 'text-2xl font-bold text-red-600';
    title.textContent = 'Erro';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'text-gray-400 hover:text-gray-600';
    closeBtn.setAttribute('aria-label', 'Fechar');
    closeBtn.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
    closeBtn.addEventListener('click', function () { modalErro.remove(); });

    header.appendChild(title);
    header.appendChild(closeBtn);

    const body = document.createElement('div');
    body.className = 'mb-6';
    const msg = document.createElement('p');
    msg.className = 'text-gray-700 whitespace-pre-wrap break-words';
    msg.textContent = String(mensagem || 'Ocorreu um erro.');
    body.appendChild(msg);

    const okBtn = document.createElement('button');
    okBtn.type = 'button';
    okBtn.className = 'w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors font-semibold';
    okBtn.textContent = 'Fechar';
    okBtn.addEventListener('click', function () { modalErro.remove(); });

    card.appendChild(header);
    card.appendChild(body);
    card.appendChild(okBtn);
    modalErro.appendChild(card);
    document.body.appendChild(modalErro);

    modalErro.addEventListener('click', function(e) {
        if (e.target === modalErro) {
            modalErro.remove();
        }
    });
}

document.getElementById('createExerciseForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = document.getElementById('btnGerarExercicios');

    const qtdFacil = parseInt(document.getElementById('quantidade_facil').value || '0', 10);
    const qtdMedio = parseInt(document.getElementById('quantidade_medio').value || '0', 10);
    const qtdDificil = parseInt(document.getElementById('quantidade_dificil').value || '0', 10);
    const total = qtdFacil + qtdMedio + qtdDificil;

    if (total < 3 || total > 30) {
        alert('A soma das quantidades deve estar entre 3 e 30 exercícios.');
        return;
    }

    EducaAiLoading.setButtonLoading(submitBtn, true);
    EducaAiLoading.show({
        id: 'generatingModal',
        status: 'Aguarde enquanto processamos sua solicitação...'
    });

    fetch('<?= URL ?>/exercicios-personalizados/gerar', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Resposta não é JSON. Content-Type:', contentType);
            console.error('Conteúdo recebido:', text);
            throw new Error('Resposta do servidor não é JSON válido. Conteúdo: ' + text.substring(0, 200));
        }

        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Texto recebido completo:', text);

            let erroMsg = 'Erro ao decodificar JSON do servidor.\n\n';
            erroMsg += 'Erro: ' + parseError.message + '\n\n';
            erroMsg += 'Resposta do servidor (primeiros 500 caracteres):\n';
            erroMsg += text.substring(0, 500);

            if (text.length > 500) {
                erroMsg += '\n\n... (resposta truncada, total: ' + text.length + ' caracteres)';
            }

            throw new Error(erroMsg);
        }

        return data;
    })
    .then(data => {
        if (!data.success) {
            EducaAiLoading.hide('generatingModal');
            EducaAiLoading.setButtonLoading(submitBtn, false);
            mostrarErro('Erro: ' + (data.error || 'Não foi possível gerar exercícios'));
            return;
        }

        // Assíncrono de verdade: o job já está na fila (processado pelo cron, que também
        // importa o resultado sozinho — ver CustomExerciseImportService). Não precisa
        // esperar terminar aqui; manda pra "Minhas Listas", onde a lista aparece como
        // "Gerando" e atualiza sozinha quando ficar pronta.
        EducaAiLoading.setStatus('Exercícios sendo gerados! Redirecionando...', 'generatingModal');
        EducaAiLoading.setProgress(100, 'generatingModal');
        setTimeout(() => {
            window.location.href = '<?= URL ?>/exercicios-personalizados/minhas-listas';
        }, 600);
    })
    .catch(error => {
        console.error('Erro completo:', error);
        EducaAiLoading.hide('generatingModal');
        EducaAiLoading.setButtonLoading(submitBtn, false);
        mostrarErro('Erro ao processar solicitação: ' + error.message);
    });
});
</script>
