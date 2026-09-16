<?php
/**
 * Painel de notas: grade detalhada do evento (S1 N/Q, médias, prova, trabalho).
 *
 * @var list<array<string,mixed>> $paineis_notas_eventos
 * @var bool $painel_notas_pode_imprimir
 */
$paineisEventos = is_array($paineis_notas_eventos ?? null) ? $paineis_notas_eventos : [];
if ($paineisEventos === []) {
    return;
}
$podeImprimirPainel = !empty($painel_notas_pode_imprimir);
?>
<div class="space-y-8">
    <?php foreach ($paineisEventos as $idxPainel => $evPainel): ?>
        <?php
        if (!is_array($evPainel) || empty($evPainel['linhas'])) {
            continue;
        }
        $ridPainel = (int) ($evPainel['regra_id'] ?? 0);
        $periodoRef = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($evPainel['periodo_ref'] ?? '')) ?: (string) $idxPainel;
        $domPainel = 'painel-notas-evento-' . ($ridPainel > 0 ? $ridPainel : 'i') . '-' . $periodoRef;
        $tituloPainel = trim((string) ($evPainel['regra_nome'] ?? 'Painel de notas'));
        if ($tituloPainel === '') {
            $tituloPainel = 'Painel de notas';
        }
        ?>
        <section>
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Painel de notas</h2>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($tituloPainel, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php if ($podeImprimirPainel): ?>
                    <button
                        type="button"
                        class="px-3 py-2 bg-primary text-white text-sm rounded-lg hover:opacity-90"
                        data-notas-title="<?= htmlspecialchars($tituloPainel, ENT_QUOTES, 'UTF-8') ?>"
                        onclick="imprimirNotasEvento('<?= htmlspecialchars($domPainel, ENT_QUOTES, 'UTF-8') ?>', this)">
                        Imprimir
                    </button>
                <?php endif; ?>
            </div>
            <div id="<?= htmlspecialchars($domPainel, ENT_QUOTES, 'UTF-8') ?>">
                <?php
                $boletinsGeradosBackup = $boletins_gerados ?? [];
                $boletimPodeExcluirBackup = $boletim_pode_excluir ?? false;
                $boletimAlunoIdBackup = $boletim_aluno_id ?? 0;
                $boletins_gerados = [$evPainel];
                $boletim_pode_excluir = false;
                $boletim_aluno_id = 0;
                require __DIR__ . '/boletins_gerados.php';
                $boletins_gerados = $boletinsGeradosBackup;
                $boletim_pode_excluir = $boletimPodeExcluirBackup;
                $boletim_aluno_id = $boletimAlunoIdBackup;
                ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
