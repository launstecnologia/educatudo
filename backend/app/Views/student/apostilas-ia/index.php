<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Meu Material</h1>
    <p class="text-gray-600 mt-1">Apostilas enviadas pela sua escola, com assistente de IA para tirar dúvidas sobre o conteúdo.</p>
</div>

<?php if (!empty($flash['message'])): ?>
    <div class="mb-4 rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>

<?php
$materiasDisponiveis = [];
foreach (($items ?? []) as $itemMateria) {
    $nome = trim((string)($itemMateria['disciplina_nome'] ?? ''));
    if ($nome !== '') {
        $materiasDisponiveis[$nome] = $nome;
    }
}
sort($materiasDisponiveis);
?>

<?php if (!empty($materiasDisponiveis)): ?>
<div class="mb-4 flex items-center gap-2">
    <label for="filtroMateria" class="text-sm text-gray-600">Matéria:</label>
    <select id="filtroMateria" class="text-sm border border-gray-300 rounded-lg p-2">
        <option value="">Todas</option>
        <?php foreach ($materiasDisponiveis as $nomeMateria): ?>
            <option value="<?= htmlspecialchars($nomeMateria) ?>"><?= htmlspecialchars($nomeMateria) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5" id="gradeMateriais">
    <?php if (empty($items)): ?>
        <div class="col-span-full bg-white rounded-xl shadow border border-gray-200 p-6 text-center text-gray-500">
            Nenhum material disponível para a sua turma ainda.
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <?php
            $status = (string)($item['status'] ?? 'pendente');
            $badgeClasses = [
                'pendente' => 'bg-gray-100 text-gray-700',
                'processando' => 'bg-blue-100 text-blue-700',
                'pronto' => 'bg-green-100 text-green-700',
                'erro' => 'bg-red-100 text-red-700',
            ];
            $badgeLabels = [
                'pendente' => 'Pendente',
                'processando' => 'Processando',
                'pronto' => 'Pronto',
                'erro' => 'Erro',
            ];
            $pronta = $status === 'pronto';
            $isLegado = !empty($item['is_legado']);
            $itemId = (int)$item['id'];
            $capaUrl = URL . '/aluno/apostilas-ia/' . $itemId . '/capa';
            $pdfUrl = URL . '/aluno/apostilas-ia/' . $itemId . '/pdf';
            $assistenteUrl = URL . '/aluno/apostilas-ia/' . $itemId;
            $disciplinaNome = trim((string)($item['disciplina_nome'] ?? ''));
            ?>
            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden flex flex-col" data-materia="<?= htmlspecialchars($disciplinaNome) ?>">
                <div class="relative bg-gray-100 aspect-[3/4] flex items-center justify-center">
                    <img src="<?= htmlspecialchars($capaUrl) ?>" alt="Capa de <?= htmlspecialchars((string)$item['titulo']) ?>"
                         class="w-full h-full object-cover" loading="lazy"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="hidden w-full h-full items-center justify-center text-gray-400 text-5xl absolute inset-0">📘</div>
                    <span class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-medium <?= $badgeClasses[$status] ?? 'bg-gray-100 text-gray-700' ?>">
                        <?= htmlspecialchars($badgeLabels[$status] ?? ucfirst($status)) ?>
                    </span>
                </div>
                <div class="p-4 flex flex-col flex-1">
                    <h3 class="font-semibold text-gray-900 leading-snug text-sm"><?= htmlspecialchars((string)$item['titulo']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1">
                        <?= !empty($item['disciplina_nome']) ? htmlspecialchars($item['disciplina_nome']) : '—' ?>
                    </p>
                    <div class="mt-3 flex flex-col gap-2">
                        <?php if ($pronta): ?>
                            <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener noreferrer"
                               class="inline-block w-full text-center bg-gray-100 text-gray-800 px-3 py-2 rounded-lg hover:bg-gray-200 text-sm font-medium">
                                📖 Ver Apostila
                            </a>
                            <?php if (!$isLegado): ?>
                                <a href="<?= htmlspecialchars($assistenteUrl) ?>"
                                   class="inline-block w-full text-center bg-indigo-600 text-white px-3 py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium">
                                    🤖 Assistente
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="inline-block w-full text-center bg-gray-100 text-gray-500 px-3 py-2 rounded-lg text-sm font-medium cursor-not-allowed">
                                Ainda processando
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($materiasDisponiveis)): ?>
<script>
    document.getElementById('filtroMateria').addEventListener('change', function () {
        var valor = this.value;
        document.querySelectorAll('#gradeMateriais > [data-materia]').forEach(function (card) {
            card.style.display = (valor === '' || card.dataset.materia === valor) ? '' : 'none';
        });
    });
</script>
<?php endif; ?>
