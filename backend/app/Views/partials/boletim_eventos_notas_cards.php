<?php
/**
 * Lista os eventos de boletim (exibir_em = notas) como cards com botão "Abrir"
 * (somente visualização, sem impressão). Espera $boletins_gerados_notas (array
 * de eventos já gerados, no mesmo formato usado por partials/boletins_gerados.php).
 */
$boletinsGeradosNotasCards = is_array($boletins_gerados_notas ?? null) ? $boletins_gerados_notas : [];
?>
<?php if (!empty($boletinsGeradosNotasCards)): ?>
<div class="space-y-3">
    <?php $bnIdx = 0; ?>
    <?php foreach ($boletinsGeradosNotasCards as $evNota): ?>
        <?php
        if (empty($evNota['linhas'])) {
            continue;
        }
        $bnIdx++;
        $evNotaNome = (string) ($evNota['regra_nome'] ?? 'Evento de notas');
        $evNotaAtualizado = !empty($evNota['updated_at']) ? date('d/m/Y H:i', strtotime((string) $evNota['updated_at'])) : '-';
        $evNotaBimestre = isset($evNota['bimestre']) && $evNota['bimestre'] !== null ? (int) $evNota['bimestre'] : 0;
        $evNotaAno = isset($evNota['ano_letivo']) && $evNota['ano_letivo'] !== null ? (int) $evNota['ano_letivo'] : 0;
        ?>
        <div class="border border-gray-200 rounded-lg p-4 bg-white flex items-center justify-between gap-3">
            <div>
                <div class="text-base font-semibold text-gray-900"><?= htmlspecialchars($evNotaNome, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-sm text-gray-600">
                    Bimestre: <?= $evNotaBimestre > 0 ? ($evNotaBimestre . 'º') : 'N/A' ?>
                    | Ano: <?= $evNotaAno > 0 ? $evNotaAno : 'N/A' ?>
                    | Atualizado: <?= htmlspecialchars($evNotaAtualizado, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <button type="button"
                    class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 shrink-0"
                    onclick="<?= htmlspecialchars("abrirModalConteudoVisualizar('content-bn-evento-{$bnIdx}', " . json_encode($evNotaNome, JSON_UNESCAPED_UNICODE) . ')', ENT_QUOTES, 'UTF-8') ?>">
                Abrir
            </button>
        </div>
        <div id="content-bn-evento-<?= $bnIdx ?>" class="hidden">
            <?php
            $boletinsGeradosBackup = $boletins_gerados ?? [];
            $boletins_gerados = [$evNota];
            require __DIR__ . '/boletins_gerados.php';
            $boletins_gerados = $boletinsGeradosBackup;
            ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/modal_abrir_conteudo_visualizar.php'; ?>
