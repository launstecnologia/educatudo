<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eid = (int) ($edicao['id'] ?? 0);
$cur = (string) ($nav_atual ?? '');
$itens = [
    ['chave' => 'visao', 'label' => 'Visão geral', 'href' => URL . '/admin/censo?edicao_id=' . $eid],
    ['chave' => 'escola', 'label' => 'Escola', 'href' => URL . '/admin/censo/' . $eid . '/escola'],
    ['chave' => 'gestores', 'label' => 'Gestores', 'href' => URL . '/admin/censo/' . $eid . '/gestores'],
    ['chave' => 'turmas', 'label' => 'Turmas', 'href' => URL . '/admin/censo/' . $eid . '/turmas'],
    ['chave' => 'alunos', 'label' => 'Alunos', 'href' => URL . '/admin/censo/' . $eid . '/alunos'],
    ['chave' => 'profissionais', 'label' => 'Profissionais', 'href' => URL . '/admin/censo/' . $eid . '/profissionais'],
    ['chave' => 'matriculas', 'label' => 'Matrículas', 'href' => URL . '/admin/censo/' . $eid . '/matriculas'],
    ['chave' => 'vinculos', 'label' => 'Vínculos', 'href' => URL . '/admin/censo/' . $eid . '/vinculos'],
    ['chave' => 'situacao', 'label' => 'Situação do aluno', 'href' => URL . '/admin/censo/' . $eid . '/situacao'],
    ['chave' => 'pendencias', 'label' => 'Pendências', 'href' => URL . '/admin/censo/' . $eid . '/pendencias'],
    ['chave' => 'exportacoes', 'label' => 'Exportações', 'href' => URL . '/admin/censo/' . $eid . '/exportacoes'],
    ['chave' => 'retornos', 'label' => 'Retornos', 'href' => URL . '/admin/censo/' . $eid . '/retornos'],
    ['chave' => 'config', 'label' => 'Configuração', 'href' => URL . '/admin/censo/' . $eid . '/config'],
];
if ($eid <= 0) {
    return;
}
?>
<nav class="flex flex-wrap gap-1 mb-6 bg-white rounded-xl border border-gray-200 p-2">
    <?php foreach ($itens as $item): ?>
    <a href="<?= htmlspecialchars($item['href']) ?>"
       class="px-3 py-1.5 rounded-lg text-sm <?= $cur === $item['chave'] ? 'bg-primary text-white font-semibold' : 'text-gray-600 hover:bg-gray-50' ?>">
        <?= htmlspecialchars($item['label']) ?>
    </a>
    <?php endforeach; ?>
</nav>
