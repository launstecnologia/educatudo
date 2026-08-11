<?php require __DIR__ . '/_styles.php'; ?>

<header class="mb-8 pb-6 border-b border-gray-200">
    <a href="<?= URL ?>/admin/dev" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Configurações Avançadas</a>
    <div class="flex items-center gap-3 mt-2">
        <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-fuchsia-100 text-fuchsia-600 text-2xl">🤖</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Inteligência Artificial</h1>
            <p class="text-sm text-gray-500 mt-0.5">Prompts usados pela IA e custos de uso (tokens/modelo)</p>
        </div>
    </div>
</header>

<?php
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'info';
if ($flash_message !== ''):
    $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>
<div class="mb-6 p-4 rounded-lg border <?= $bg ?>">
    <?= htmlspecialchars($flash_message) ?>
</div>
<?php endif; ?>

<div class="space-y-6 max-w-5xl">

    <div class="dev-card">
        <div class="dev-card-header">
            <p class="text-sm text-gray-600 mb-4">Valor total e tokens (pergunta + resposta) por período, por modelo.</p>
        </div>
        <div class="dev-card-body" style="padding-top: 0;">
            <a href="<?= URL ?>/admin/dev-settings/custos-llm" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm">
                Abrir Custos LLM
            </a>
        </div>
    </div>

    <div class="dev-card">
        <div class="dev-card-header flex items-center justify-between">
            <span>Prompts de IA</span>
            <span class="text-xs font-normal text-gray-400 font-medium">Edite e salve ao final da página</span>
        </div>
        <div class="dev-card-body">
            <p class="text-sm text-gray-500 mb-6">Configure os prompts utilizados pela IA para redações, chat e outras funcionalidades.</p>
            <form id="prompts-redacao-form" method="post" action="<?= URL ?>/admin/dev/prompts-redacao/save" class="space-y-8">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <?php
                $promptTabs = [
                    'prompt_tudinha_chat' => [
                        'label' => 'Chat Aluno',
                        'icon'  => '💬',
                        'hint'  => 'Prompt da Tudinha no chat do aluno. Se vazio, usa o padrão.',
                        'rows'  => 16,
                    ],
                    'prompt_tema' => [
                        'label' => 'Tema Redação',
                        'icon'  => '📝',
                        'hint'  => 'Usado quando alunos solicitam temas. Variável: {themeRequest}.',
                        'rows'  => 12,
                    ],
                    'prompt_correcao' => [
                        'label' => 'Correção Redação',
                        'icon'  => '✅',
                        'hint'  => 'Critérios do ENEM. Variáveis: {titulo}, {conteudo}.',
                        'rows'  => 12,
                    ],
                    'prompt_ocr' => [
                        'label' => 'OCR',
                        'icon'  => '📷',
                        'hint'  => 'Transcrever imagens de redação enviadas pelos alunos.',
                        'rows'  => 6,
                    ],
                    'prompt_prova' => [
                        'label' => 'Prova IA',
                        'icon'  => '📋',
                        'hint'  => 'Prompt base da prova. Variáveis: {tema}, {materia}, {serie}, {quantidade_questoes}, {nivel_dificuldade}, {tipo_questao}, {contexto}.',
                        'rows'  => 14,
                    ],
                    'prompt_prova_imagens' => [
                        'label' => 'Imagens Prova',
                        'icon'  => '🖼️',
                        'hint'  => 'Seção de imagens injetada quando "Gerar com imagens" está ativo. Se vazio, usa padrão do sistema.',
                        'rows'  => 10,
                    ],
                    'prompt_exercicios_jornada' => [
                        'label' => 'Exercícios Jornada',
                        'icon'  => '🎯',
                        'hint'  => 'Variáveis: {quantidade}, {tipoDescricao}, {contextoCompleto}, {tema}, {materia}, {nivel_dificuldade}, {tipo_exercicio}, {quantidade_exercicios}, {contexto}.',
                        'rows'  => 14,
                    ],
                    'prompt_exercicios_personalizados' => [
                        'label' => 'Exercícios Personalizados',
                        'icon'  => '📚',
                        'hint'  => 'Usado em "Minhas Listas" (aluno). Variáveis: {tema}, {materia}, {quantidade_questoes}, {nivel_dificuldade}, {contexto}.',
                        'rows'  => 14,
                    ],
                ];
                $promptValues = [];
                $tabsId = 'colag-prompts';
                include __DIR__ . '/../../components/prompt-tabs.php';
                ?>

                <div class="flex justify-end pt-6 mt-6 border-t border-gray-200">
                    <button type="submit" class="btn-primary-custom px-6 py-3 rounded-lg transition-colors font-medium shadow-sm hover:opacity-90">
                        Salvar todos os prompts
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
(function() {
    fetch('<?= URL ?>/admin/dev/prompts-redacao')
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                var keys = ['prompt_tema', 'prompt_correcao', 'prompt_ocr', 'prompt_prova',
                            'prompt_prova_imagens', 'prompt_exercicios_jornada',
                            'prompt_exercicios_personalizados', 'prompt_tudinha_chat'];
                keys.forEach(function(k) {
                    if (data[k] && data[k].trim() !== '') {
                        var el = document.getElementById('colag-prompts-textarea-' + k);
                        if (el) el.value = data[k];
                    }
                });
            }
        })
        .catch(() => console.log('Erro ao carregar prompts'));
})();

(function() {
    const form = document.getElementById('prompts-redacao-form');
    if (!form) return;
    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const keys = data.saved_keys ? data.saved_keys.join(', ') : '';
                alert('✅ ' + data.message + (keys ? '\nChaves salvas: ' + keys : ''));
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar prompts');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Falha na conexão ao salvar prompts');
        });
    });
})();
</script>
