<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eid = (int) ($edicao['id'] ?? 0);
$linhas = is_array($linhas ?? null) ? $linhas : [];
$editavel = !empty($editavel);
$page_header_title = 'Situação do aluno';
$page_header_subtitle = 'Segunda etapa da coleta. O resultado acadêmico homologado é reaproveitado; o código oficial entra no leiaute da Situação do Aluno.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
include __DIR__ . '/_contexto.php';
$nav_atual = 'situacao';
include __DIR__ . '/_nav.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$opcoes = [
    '' => 'Selecione',
    'aprovado' => 'Aprovado',
    'reprovado' => 'Reprovado',
    'transferido' => 'Transferido',
    'deixou_frequentar' => 'Deixou de frequentar',
    'falecido' => 'Falecido',
    'em_andamento' => 'Curso em andamento',
];
?>
<p class="text-sm text-gray-600 mb-4">
    Cada linha é uma matrícula (o mesmo aluno pode aparecer em mais de uma turma). O TXT da
    <strong>Matrícula Inicial</strong> (registros 00–60) é gerado em Exportações. O arquivo da 2ª etapa
    só é emitido quando o leiaute oficial da Situação do Aluno estiver importado.
</p>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left">Aluno</th>
                <th class="px-4 py-3 text-left">Turma</th>
                <th class="px-4 py-3 text-left">Resultado acadêmico</th>
                <th class="px-4 py-3 text-left">Situação Censo</th>
                <th class="px-4 py-3 text-left">Justificativa</th>
                <th class="px-4 py-3 text-right">Ação</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if ($linhas === []): ?>
            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Sincronize as matrículas para montar a situação do aluno.</td></tr>
            <?php endif; ?>
            <?php foreach ($linhas as $row):
                $codigo = (string) ($row['situacao_codigo'] ?? '');
                if ($codigo === '' && !empty($row['resultado_situacao'])) {
                    $codigo = (string) $row['resultado_situacao'];
                }
            ?>
            <tr>
                <td class="px-4 py-3"><?= $esc($row['aluno_nome'] ?? '') ?></td>
                <td class="px-4 py-3"><?= $esc($row['turma_nome'] ?? '') ?></td>
                <td class="px-4 py-3"><?= $esc($row['resultado_academico'] ?? '—') ?></td>
                <?php if ($editavel): ?>
                <td class="px-4 py-3" colspan="3">
                    <form method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/situacao/<?= (int) $row['id'] ?>" class="flex flex-wrap gap-2 items-center justify-end">
                        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
                        <select name="situacao_codigo" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                            <?php foreach ($opcoes as $k => $label): ?>
                            <option value="<?= $esc($k) ?>" <?= $codigo === $k ? 'selected' : '' ?>><?= $esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input name="justificativa" value="<?= $esc($row['justificativa'] ?? '') ?>" placeholder="Justificativa de divergência" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm min-w-[180px] flex-1">
                        <button class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm">Confirmar</button>
                    </form>
                </td>
                <?php else: ?>
                <td class="px-4 py-3"><?= $esc($opcoes[$codigo] ?? ($codigo !== '' ? $codigo : '—')) ?></td>
                <td class="px-4 py-3"><?= $esc($row['justificativa'] ?? '—') ?></td>
                <td class="px-4 py-3 text-right">—</td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
