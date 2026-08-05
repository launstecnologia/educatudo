<?php
$layout_config = $layout_config ?? [];
$escola_id = $escola_id ?? 0;

$promptTabs = [
    'prompt_tudinha_chat' => [
        'label' => 'Chat Aluno',
        'icon'  => '💬',
        'hint'  => 'Prompt da Tudinha no chat do aluno.',
        'rows'  => 10,
    ],
    'prompt_tema' => [
        'label' => 'Tema Redação',
        'icon'  => '📝',
        'hint'  => 'Variáveis: {themeRequest}.',
        'rows'  => 10,
    ],
    'prompt_correcao' => [
        'label' => 'Correção Redação',
        'icon'  => '✅',
        'hint'  => 'Prompt para corrigir redações dos alunos.',
        'rows'  => 10,
    ],
    'prompt_ocr' => [
        'label' => 'OCR',
        'icon'  => '📷',
        'hint'  => 'Prompt para transcrever imagens de redação (usado quando só GPT-4o Vision é usado, sem Google Vision).',
        'rows'  => 6,
    ],
    'prompt_ocr_formatacao' => [
        'label' => 'OCR Formatação',
        'icon'  => '📐',
        'hint'  => 'Prompt de sistema que organiza o texto bruto do OCR em redação (parágrafos, recuos). Usado após o Google Vision. Se vazio, usa o padrão do sistema.',
        'rows'  => 10,
    ],
    'prompt_ocr_vision_system' => [
        'label' => 'OCR Vision (system)',
        'icon'  => '🔤',
        'hint'  => 'Prompt de sistema quando a transcrição é feita só com GPT-4o Vision (sem Google Vision). Define regras de literalidade, [PALAVRA ILEGÍVEL] e formatação. Se vazio, usa o padrão do sistema.',
        'rows'  => 14,
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
        'rows'  => 12,
    ],
    'prompt_exercicios_personalizados' => [
        'label' => 'Exercícios Personalizados',
        'icon'  => '📚',
        'hint'  => 'Variáveis: {tema}, {materia}, {quantidade_questoes}, {nivel_dificuldade}, {contexto}.',
        'rows'  => 12,
    ],
];

$promptValues = $layout_config;
$tabsId = 'master-detail-prompts';
$csrf_token = $csrf_token ?? '';
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-2">Prompts de IA</h3>
    <p class="text-sm text-slate-600 mb-4">Textos usados pelos modelos de IA. Selecione a aba para editar cada prompt.</p>

    <form method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/prompts">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php include __DIR__ . '/../../../components/prompt-tabs.php'; ?>

        <div class="mt-6">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar</button>
        </div>
    </form>
</div>
