<?php
$payload = is_array($payload ?? null) ? $payload : [];
$aluno = is_array($payload['aluno'] ?? null) ? $payload['aluno'] : [];
$turma = is_array($payload['turma'] ?? null) ? $payload['turma'] : [];
$unidade = is_array($payload['unidade'] ?? null) ? $payload['unidade'] : [];
$componentes = is_array($payload['componentes_ficha'] ?? null) && $payload['componentes_ficha'] !== []
    ? $payload['componentes_ficha']
    : (is_array($payload['componentes'] ?? null) ? $payload['componentes'] : []);
$avaliado = is_array($payload['avaliado'] ?? null) ? $payload['avaliado'] : [];
$freq = is_array($payload['frequencia'] ?? null) ? $payload['frequencia'] : [];
$periodo = is_array($payload['periodo'] ?? null) ? $payload['periodo'] : [];
$alunoId = (int) ($aluno_id ?? ($aluno['id'] ?? 0));
$turmaId = (int) ($turma_id ?? ($payload['turma_id'] ?? 0));
$anoLetivo = (int) ($ano_letivo ?? ($periodo['ano_letivo'] ?? date('Y')));
$periodoTipo = (string) ($periodo_tipo ?? ($periodo['tipo'] ?? 'ano'));
$periodoNumero = (int) ($periodo_numero ?? ($periodo['numero'] ?? 0));
$qs = http_build_query([
    'turma_id' => $turmaId,
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
]);
$homologado = !empty($payload['_homologado']) || (($payload['status'] ?? '') === 'homologado');
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtNota = static function ($v): string {
    return is_numeric($v) ? number_format((float) $v, 1, ',', '.') : '—';
};
$fmtData = static function ($v) use ($esc): string {
    $s = trim((string) $v);
    if ($s === '' || $s === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($s);
    return $ts ? $esc(date('d/m/Y', $ts)) : $esc($s);
};
$freqGeral = isset($freq['percentual']) && is_numeric($freq['percentual'])
    ? number_format((float) $freq['percentual'], 1, ',', '.') . '%'
    : '—';
$faltasGerais = isset($freq['faltas']) ? (int) $freq['faltas'] : null;
$escolaNome = (string) ($unidade['razao_social'] ?? $unidade['nome'] ?? '');
$escolaDocs = trim(implode(' · ', array_filter([
    !empty($unidade['cnpj']) ? 'CNPJ ' . $unidade['cnpj'] : '',
    !empty($unidade['inep']) ? 'INEP ' . $unidade['inep'] : '',
])));
$escolaEnd = (string) ($unidade['endereco_completo'] ?? $unidade['endereco'] ?? '');

$page_header_title = 'Ficha individual';
$page_header_subtitle = (string) ($aluno['nome'] ?? 'Aluno') . ' · ' . $anoLetivo;
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>?<?= htmlspecialchars($qs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Voltar</a>
<a href="<?= URL ?>/admin/resultados-finais/aluno/<?= $alunoId ?>/ficha/pdf?<?= htmlspecialchars($qs) ?>"
   target="_blank" rel="noopener"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90">
    <i class="fa-solid fa-file-pdf mr-2"></i> Emitir PDF
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<?php if (!$homologado): ?>
<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    Documento em prévia — o resultado ainda não foi homologado. O PDF registra a emissão, mas os números podem mudar até o fechamento.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Escola</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div><span class="text-gray-500">Nome</span><div class="font-medium"><?= $esc($escolaNome !== '' ? $escolaNome : '—') ?></div></div>
        <div><span class="text-gray-500">CNPJ / INEP</span><div class="font-medium"><?= $esc($escolaDocs !== '' ? $escolaDocs : '—') ?></div></div>
        <div class="md:col-span-2"><span class="text-gray-500">Unidade</span><div class="font-medium"><?= $esc($unidade['nome'] ?? $unidade['nome_fantasia'] ?? '—') ?></div></div>
        <div class="md:col-span-2"><span class="text-gray-500">Endereço</span><div class="font-medium"><?= $esc($escolaEnd !== '' ? $escolaEnd : '—') ?></div></div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Aluno e matrícula</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div><span class="text-gray-500">Nome</span><div class="font-medium"><?= $esc($aluno['nome'] ?? '') ?></div></div>
        <div><span class="text-gray-500">Matrícula / RA</span><div class="font-medium"><?= $esc($aluno['ra'] ?? $aluno['codigo_aluno'] ?? '—') ?></div></div>
        <div><span class="text-gray-500">Nascimento</span><div class="font-medium"><?= $fmtData($aluno['data_nasc'] ?? '') ?></div></div>
        <div><span class="text-gray-500">Ano letivo</span><div class="font-medium"><?= $anoLetivo ?></div></div>
        <div><span class="text-gray-500">Curso / etapa</span><div class="font-medium"><?= $esc($turma['curso_nome'] ?? '—') ?></div></div>
        <div><span class="text-gray-500">Série / ano</span><div class="font-medium"><?= $esc($turma['serie_nome'] ?? $turma['serie'] ?? '—') ?></div></div>
        <div><span class="text-gray-500">Turma</span><div class="font-medium"><?= $esc($turma['nome'] ?? '—') ?></div></div>
        <div><span class="text-gray-500">Turno</span><div class="font-medium"><?= $esc($turma['turno_label'] ?? $turma['turno'] ?? '—') ?></div></div>
        <div><span class="text-gray-500">Situação da matrícula</span><div class="font-medium"><?= $esc($payload['situacao_matricula_label'] ?? '—') ?></div></div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Resultado final</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div>
            <span class="text-gray-500">Situação</span>
            <div class="font-medium"><?= $esc($avaliado['rotulo'] ?? $payload['rotulo'] ?? '—') ?></div>
        </div>
        <div>
            <span class="text-gray-500">Frequência geral</span>
            <div class="font-medium"><?= $esc($freqGeral) ?><?php if ($faltasGerais !== null): ?> <span class="text-gray-500 font-normal">(<?= (int) $faltasGerais ?> faltas)</span><?php endif; ?></div>
        </div>
        <div>
            <span class="text-gray-500">Média geral</span>
            <div class="font-medium"><?= $fmtNota($avaliado['media_final'] ?? null) ?></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Componentes curriculares</h3>
    <p class="text-sm text-gray-500 mb-4">Notas dos 4 bimestres, recuperação, carga horária prevista/cumprida (aulas) e frequência por componente.</p>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">CH prev.</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">CH cump.</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">1º</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">2º</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">3º</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">4º</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rec.</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Média</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Faltas</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Freq.</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($componentes === []): ?>
                <tr><td colspan="12" class="px-4 py-8 text-center text-gray-500">Nenhum componente neste período.</td></tr>
                <?php else: foreach ($componentes as $c):
                    $freqComp = isset($c['frequencia_percentual']) && is_numeric($c['frequencia_percentual'])
                        ? number_format((float) $c['frequencia_percentual'], 1, ',', '.') . '%'
                        : '—';
                ?>
                <tr>
                    <td class="px-3 py-2 font-medium text-gray-900"><?= $esc($c['materia_nome'] ?? '') ?></td>
                    <td class="px-3 py-2"><?= $esc($c['carga_prevista'] ?? $c['carga_horaria'] ?? '—') ?></td>
                    <td class="px-3 py-2"><?= $esc($c['carga_cumprida'] ?? '—') ?></td>
                    <td class="px-3 py-2"><?= $fmtNota($c['b1'] ?? null) ?></td>
                    <td class="px-3 py-2"><?= $fmtNota($c['b2'] ?? null) ?></td>
                    <td class="px-3 py-2"><?= $fmtNota($c['b3'] ?? null) ?></td>
                    <td class="px-3 py-2"><?= $fmtNota($c['b4'] ?? null) ?></td>
                    <td class="px-3 py-2"><?= $fmtNota($c['recuperacao'] ?? null) ?></td>
                    <td class="px-3 py-2 font-medium"><?= $fmtNota($c['media_final'] ?? $c['media'] ?? null) ?></td>
                    <td class="px-3 py-2"><?= $esc($c['faltas'] ?? '—') ?></td>
                    <td class="px-3 py-2"><?= $esc($freqComp) ?></td>
                    <td class="px-3 py-2"><?= $esc($c['rotulo'] ?? '—') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Observações</h3>
    <p class="text-sm text-gray-800 whitespace-pre-line"><?= trim((string) ($payload['observacoes'] ?? '')) !== '' ? $esc($payload['observacoes']) : '—' ?></p>
</div>
