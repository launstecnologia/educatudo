<div class="mb-8">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Redações — <?= htmlspecialchars($jornada['titulo'] ?? 'Jornada') ?>
            </h2>
            <p class="text-gray-600">Temas desta jornada e o andamento da sua redação.</p>
        </div>
        <a href="<?= URL ?>/jornadas/<?= (int) ($jornada['id'] ?? 0) ?>"
           class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-sm font-medium">
            ← Voltar à jornada
        </a>
    </div>
</div>

<?php if (empty($redacoes_jornada)): ?>
    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
        <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma redação nesta jornada</h3>
        <p class="text-gray-600">O professor ainda não publicou um tema.</p>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($redacoes_jornada as $tema): ?>
            <?php
            $minha = $tema['minha_redacao'] ?? null;
            $status = is_array($minha) ? (string) ($minha['status'] ?? '') : '';
            $redacaoId = is_array($minha) ? (int) ($minha['redacao_id'] ?? 0) : 0;
            $podeVer = $redacaoId > 0 && in_array($status, ['entregue', 'corrigida_ia', 'corrigida_professor'], true);
            $rotulo = [
                'rascunho' => 'Rascunho',
                'entregue' => 'Entregue',
                'corrigida_ia' => 'Corrigida',
                'corrigida_professor' => 'Corrigida',
                'retornada' => 'Devolvida para reescrever',
            ][$status] ?? ($status !== '' ? $status : 'Não iniciada');
            ?>
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <?= htmlspecialchars($tema['tema_sugerido'] ?? 'Tema') ?>
                        </h3>
                        <?php if (!empty($tema['descricao_tema'])): ?>
                            <p class="text-gray-600 mt-2"><?= nl2br(htmlspecialchars($tema['descricao_tema'])) ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-500 mt-3"><?= htmlspecialchars($rotulo) ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($podeVer): ?>
                            <a href="<?= URL ?>/jornadas/<?= (int) $jornada['id'] ?>/redacao/<?= $redacaoId ?>"
                               class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                                Ver correção
                            </a>
                        <?php endif; ?>
                        <?php if (!$podeVer): ?>
                            <a href="<?= URL ?>/jornadas/redacao/<?= (int) ($tema['id'] ?? 0) ?>/escrever"
                               class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
                                <?= $status === '' ? 'Escrever' : 'Continuar' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
