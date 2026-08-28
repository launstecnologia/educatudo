<?php
$fonte = (($fonte ?? 'vida_escolar') === 'evento') ? 'evento' : 'vida_escolar';
$relatorio = is_array($relatorio ?? null) ? $relatorio : null;
$anosLetivos = (array) ($anos_letivos ?? []);
if ($anosLetivos === []) {
    $anosLetivos[] = (int) date('Y');
}
$anoSelecionado = (int) ($ano_letivo ?? 0);
if ($anoSelecionado <= 0) {
    $anoSelecionado = (int) $anosLetivos[0];
}
$queryExport = [
    'fonte' => $fonte,
    'evento' => $evento_selecionado ?? '',
    'ano_letivo' => $anoSelecionado,
    'turma_id' => (int) ($turma_id ?? 0),
    'nota_abaixo_de' => $nota_abaixo_de !== null ? str_replace('.', ',', (string) $nota_abaixo_de) : '',
    'materias_exibicao' => $materias_exibicao ?? 'todas',
    'assinatura' => !empty($incluir_assinatura) ? 1 : 0,
];
$formatNota = static function ($value, int $places): string {
    return is_numeric($value) ? number_format((float) $value, $places, ',', '.') : ((string) $value !== '' ? (string) $value : '—');
};
$fonteRelatorio = (string) ($relatorio['fonte'] ?? $fonte);
$zipJob = is_array($zip_job ?? null) ? $zip_job : null;
$zipJobId = (int) ($zipJob['id'] ?? 0);
$zipJobStatus = (string) ($zipJob['status'] ?? '');
$zipGerando = $zipJobId > 0 && in_array($zipJobStatus, ['pending', 'processing'], true);
$zipPronto = $zipJobId > 0 && $zipJobStatus === 'done';
$zipFalhou = $zipJobId > 0 && in_array($zipJobStatus, ['failed', 'error'], true);
$zipInicio = (string) ($zipJob['iniciado_em'] ?? '');
$zipTermino = (string) ($zipJob['finalizado_em'] ?? '');
$zipPedido = (string) ($zipJob['pedido_em'] ?? '');
$zipDuracao = (string) ($zipJob['duracao'] ?? '');
include __DIR__ . '/../_partials/flash_message.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Notas da Coordenação</h1>
    <p class="text-gray-600 mt-1">Escolha o boletim da Vida Escolar ou as notas do evento (provas, trabalhos e médias).</p>
</div>

<form method="GET" action="<?= URL ?>/admin/reports/boletim-coordenacao" id="form-boletim-coordenacao" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 md:p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-4 items-start">
        <label class="block xl:col-span-4">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Exibir</span>
            <span class="relative block">
            <select name="fonte" id="fonte-boletim-coord" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 pr-10 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
                <option value="vida_escolar" <?= $fonte === 'vida_escolar' ? 'selected' : '' ?>>Boletim da Vida Escolar</option>
                <option value="evento" <?= $fonte === 'evento' ? 'selected' : '' ?>>Notas do evento (provas, trabalhos, médias)</option>
            </select>
            </span>
        </label>
        <label class="block xl:col-span-4 campo-fonte campo-fonte-vida_escolar <?= $fonte === 'vida_escolar' ? '' : 'hidden' ?>">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Ano letivo</span>
            <span class="relative block">
            <select name="ano_letivo" id="ano-letivo-boletim-coord" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 pr-10 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100" <?= $fonte === 'vida_escolar' ? 'required' : '' ?>>
                <?php foreach ($anosLetivos as $ano): ?>
                    <option value="<?= (int) $ano ?>" <?= $anoSelecionado === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                <?php endforeach; ?>
            </select>
            </span>
        </label>
        <label class="block xl:col-span-4 campo-fonte campo-fonte-evento <?= $fonte === 'evento' ? '' : 'hidden' ?>">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Evento de notas</span>
            <span class="relative block">
            <select name="evento" id="evento-boletim-coord" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 pr-10 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100" <?= $fonte === 'evento' ? 'required' : '' ?>>
                <option value="">Selecione...</option>
                <?php foreach ((array) ($eventos ?? []) as $evento):
                    $value = (int) $evento['regra_id'] . ':' . base64_encode((string) $evento['periodo_ref']); ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= ($evento_selecionado ?? '') === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($evento['nome_exibicao'] ?? $evento['nome'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            </span>
            <?php if (empty($eventos)): ?>
                <span class="block text-xs text-gray-500 mt-1">Nenhum evento gerado. Em Eventos de Notas, gere o lote para ver provas, trabalhos e médias.</span>
            <?php endif; ?>
        </label>
        <label class="block xl:col-span-2">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Turma</span>
            <span class="relative block">
            <select name="turma_id" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 pr-10 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
                <option value="0">Todas as turmas</option>
                <?php foreach ((array) ($turmas ?? []) as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>" <?= (int) ($turma_id ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            </span>
        </label>
        <label class="block xl:col-span-2">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Média final abaixo de</span>
            <input type="text" name="nota_abaixo_de" inputmode="decimal" placeholder="Ex.: 7 ou 6,5" value="<?= htmlspecialchars($nota_abaixo_de !== null ? str_replace('.', ',', (string) $nota_abaixo_de) : '') ?>" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
            <span class="block text-xs text-gray-500 mt-1">Exibe alunos com ao menos uma matéria abaixo da nota.</span>
        </label>
        <label class="block xl:col-span-4">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Exibir matérias</span>
            <span class="relative block">
            <select name="materias_exibicao" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 pr-10 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
                <option value="todas" <?= ($materias_exibicao ?? 'todas') === 'todas' ? 'selected' : '' ?>>Todas as matérias do aluno</option>
                <option value="abaixo" <?= ($materias_exibicao ?? 'todas') === 'abaixo' ? 'selected' : '' ?>>Somente matérias abaixo do corte</option>
            </select>
            </span>
        </label>
    </div>
    <label class="inline-flex items-center gap-2.5 mt-5 text-sm text-gray-700 cursor-pointer">
        <input type="checkbox" name="assinatura" value="1" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= !empty($incluir_assinatura) ? 'checked' : '' ?>>
        Incluir campo de assinatura ao lado do nome do aluno (vale na conferência e no Excel)
    </label>
    <div class="mt-5 flex flex-wrap gap-3">
        <button type="submit" name="executar" value="1" class="btn-primary-custom px-5 py-2.5 rounded-xl font-semibold shadow-sm hover:opacity-90 transition-opacity"><i class="fa-solid fa-chart-column mr-2"></i>Gerar relatório</button>
        <a href="<?= URL ?>/admin/reports/boletim-coordenacao" class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">Limpar</a>
    </div>
</form>

<?php if ($zipGerando || $zipPronto || $zipFalhou): ?>
    <div id="zip-boletins-banner" class="rounded-lg p-4 mb-6 <?= $zipFalhou ? 'bg-red-50 border border-red-200 text-red-800' : ($zipPronto ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-indigo-50 border border-indigo-200 text-indigo-900') ?>">
        <?php if ($zipGerando): ?>
            <p class="font-semibold"><i class="fa-solid fa-spinner fa-spin mr-2"></i><span id="zip-boletins-texto">Gerando os boletins em segundo plano. Um PDF por aluno, depois o ZIP. Com a escola inteira pode levar vários minutos — deixe esta página aberta.</span></p>
        <?php elseif ($zipPronto): ?>
            <p class="font-semibold"><i class="fa-solid fa-circle-check mr-2"></i>ZIP pronto<?= (int) ($zipJob['emitidos'] ?? 0) > 0 ? ': ' . (int) $zipJob['emitidos'] . ' boletim(ns)' : '' ?><?= (int) ($zipJob['falhas'] ?? 0) > 0 ? ' · ' . (int) $zipJob['falhas'] . ' falha(s)' : '' ?>.</p>
            <a href="<?= URL ?>/admin/reports/boletim-coordenacao/zip/<?= $zipJobId ?>" class="inline-flex items-center mt-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 font-semibold"><i class="fa-solid fa-download mr-2"></i>Baixar ZIP</a>
        <?php else: ?>
            <p class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-2"></i>Não foi possível gerar o ZIP<?= ($zipJob['erro'] ?? '') !== '' ? ': ' . htmlspecialchars((string) $zipJob['erro']) : '.' ?></p>
        <?php endif; ?>
        <p id="zip-boletins-horarios" class="text-sm mt-2 <?= $zipFalhou ? 'text-red-700' : ($zipPronto ? 'text-emerald-800' : 'text-indigo-800') ?>">
            <?php if ($zipInicio !== ''): ?>
                Início: <?= htmlspecialchars($zipInicio) ?>
                <?php if ($zipTermino !== ''): ?> · Término: <?= htmlspecialchars($zipTermino) ?><?php else: ?> · Término: em andamento<?php endif; ?>
                <?php if ($zipDuracao !== ''): ?> · Duração: <?= htmlspecialchars($zipDuracao) ?><?php endif; ?>
            <?php elseif ($zipPedido !== ''): ?>
                Pedido às <?= htmlspecialchars($zipPedido) ?> · Aguardando início da geração
            <?php else: ?>
                Horários da geração aparecem assim que o processamento começar.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<?php if (!empty($executar) && $relatorio): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div><strong><?= (int) $relatorio['total_alunos'] ?> alunos</strong> <span class="text-gray-500">· <?= (int) $relatorio['total_linhas'] ?> registros de matérias<?php if ($relatorio['nota_abaixo_de'] !== null): ?> · média final abaixo de <?= htmlspecialchars(number_format((float) $relatorio['nota_abaixo_de'], 1, ',', '.')) ?><?php endif; ?><?php if (($relatorio['materias_exibicao'] ?? 'todas') === 'abaixo'): ?> · somente matérias abaixo do corte<?php endif; ?><?php if ($fonteRelatorio === 'vida_escolar' && !empty($relatorio['alunos_com_ficha'])): ?> · <?= (int) $relatorio['alunos_com_ficha'] ?> com ficha na Vida Escolar<?php endif; ?></span></div>
        <div class="flex gap-2">
            <?php if ($fonteRelatorio === 'vida_escolar'): ?>
                <?php if ((int) ($relatorio['alunos_com_ficha'] ?? 0) > 0 && !$zipGerando): ?>
                <a href="<?= URL ?>/admin/reports/boletim-coordenacao/exportar?<?= htmlspecialchars(http_build_query($queryExport + ['formato' => 'pdf'])) ?>" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700"><i class="fa-solid fa-file-zipper mr-2"></i>Baixar boletins (ZIP)</a>
                <?php elseif ($zipGerando): ?>
                <span class="px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed" title="Aguarde o ZIP atual terminar"><i class="fa-solid fa-file-zipper mr-2"></i>Gerando ZIP...</span>
                <?php else: ?>
                <span class="px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed" title="Nenhum aluno com ficha na Vida Escolar"><i class="fa-solid fa-file-zipper mr-2"></i>Baixar boletins (ZIP)</span>
                <?php endif; ?>
            <?php elseif (!empty($relatorio['alunos'])): ?>
                <a href="<?= URL ?>/admin/reports/boletim-coordenacao/exportar?<?= htmlspecialchars(http_build_query($queryExport + ['formato' => 'pdf'])) ?>" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700"><i class="fa-solid fa-file-pdf mr-2"></i>Exportar PDF</a>
            <?php endif; ?>
            <a href="<?= URL ?>/admin/reports/boletim-coordenacao/exportar?<?= htmlspecialchars(http_build_query($queryExport + ['formato' => 'excel'])) ?>" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"><i class="fa-solid fa-file-excel mr-2"></i>Exportar Excel</a>
        </div>
    </div>
    <?php if ($fonteRelatorio === 'vida_escolar' && (int) ($relatorio['alunos_com_ficha'] ?? 0) > 0): ?>
        <p class="text-sm text-gray-500 mb-4">Um PDF por aluno, gerado em segundo plano e empacotado em ZIP. Com a escola inteira pode levar vários minutos.</p>
    <?php endif; ?>
    <?php if ($fonteRelatorio === 'vida_escolar' && (int) ($relatorio['alunos_sem_ficha'] ?? 0) > 0 && !empty($relatorio['alunos'])): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 mb-4"><?= (int) $relatorio['alunos_sem_ficha'] ?> aluno(s) desta lista ainda não têm ficha na Vida Escolar no ano <?= (int) ($relatorio['ano_letivo'] ?? 0) ?> e ficam de fora do ZIP.</div>
    <?php endif; ?>
    <?php if (empty($relatorio['alunos'])): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4"><?= $fonteRelatorio === 'vida_escolar' ? 'Nenhuma ficha da Vida Escolar encontrada para os filtros selecionados.' : 'Nenhum evento gerado encontrado para os filtros selecionados.' ?></div>
    <?php endif; ?>
    <?php foreach ((array) $relatorio['alunos'] as $aluno): ?>
        <?php $observacaoAluno = trim((string) ($aluno['observacao'] ?? '')); ?>
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center gap-x-6 gap-y-2">
                <strong class="text-gray-900"><?= htmlspecialchars((string) $aluno['nome']) ?></strong>
                <?php if (!empty($incluir_assinatura)): ?><span class="text-sm text-gray-600">Assinatura: <span class="inline-block w-52 border-b border-gray-500"></span></span><?php endif; ?>
                <span class="text-sm text-gray-500">Turma: <?= htmlspecialchars((string) $aluno['turma']) ?></span>
                <?php if ((string) $aluno['ra'] !== ''): ?><span class="text-sm text-gray-500">RA: <?= htmlspecialchars((string) $aluno['ra']) ?></span><?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Matéria</th><?php foreach ($relatorio['columns'] as $column): ?><th class="px-4 py-2 text-center whitespace-nowrap"><?= htmlspecialchars($column['label']) ?></th><?php endforeach; ?></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach ((array) $aluno['materias'] as $materia): ?><tr><td class="px-4 py-2"><?= htmlspecialchars((string) $materia['nome']) ?></td><?php foreach ($relatorio['columns'] as $column): ?><td class="px-4 py-2 text-center font-medium"><?= htmlspecialchars($formatNota($materia['notas'][$column['codigo']] ?? null, (int) $relatorio['decimal_places'])) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="coord-observation border-t border-gray-200 bg-slate-50/70 px-5 py-4"
                 data-endpoint="<?= URL ?>/admin/students/<?= (int) $aluno['id'] ?>/boletim/observacao"
                 data-csrf="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-note-sticky text-gray-400"></i>
                        <h4 class="text-sm font-semibold text-gray-800">Observação da coordenação</h4>
                    </div>
                    <?php if (!empty($pode_editar_observacao)): ?>
                    <button type="button" class="coord-observation-edit text-sm font-semibold hover:opacity-75" style="color: var(--button-primary-color)">
                        <?= $observacaoAluno !== '' ? 'Editar' : 'Adicionar observação' ?>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="coord-observation-view">
                    <p class="coord-observation-text text-sm text-gray-700 whitespace-pre-wrap break-words <?= $observacaoAluno === '' ? 'italic text-gray-400' : '' ?>"><?= htmlspecialchars($observacaoAluno !== '' ? $observacaoAluno : 'Nenhuma observação registrada.', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php if (!empty($pode_editar_observacao)): ?>
                <div class="coord-observation-form hidden mt-3">
                    <textarea rows="4" maxlength="5000" class="coord-observation-textarea w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-purple-100" placeholder="Escreva uma observação que ficará no boletim e no PDF…"><?= htmlspecialchars($observacaoAluno, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <button type="button" class="coord-observation-save btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">Salvar observação</button>
                        <button type="button" class="coord-observation-cancel px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    </div>
                </div>
                <span class="coord-observation-status block mt-2 text-xs text-gray-500"></span>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<script>
(function () {
    var fonteSelect = document.getElementById('fonte-boletim-coord');
    var eventoSelect = document.getElementById('evento-boletim-coord');
    var anoSelect = document.getElementById('ano-letivo-boletim-coord');
    function aplicarFonte() {
        var fonte = fonteSelect ? fonteSelect.value : 'vida_escolar';
        document.querySelectorAll('.campo-fonte').forEach(function (el) {
            el.classList.toggle('hidden', !el.classList.contains('campo-fonte-' + fonte));
        });
        if (eventoSelect) {
            eventoSelect.required = fonte === 'evento';
        }
        if (anoSelect) {
            anoSelect.required = fonte === 'vida_escolar';
        }
    }
    if (fonteSelect) {
        fonteSelect.addEventListener('change', aplicarFonte);
        aplicarFonte();
    }
})();
</script>
<?php if (!empty($pode_editar_observacao)): ?>
<script>
(function () {
    document.querySelectorAll('.coord-observation').forEach(function (block) {
        var editButton = block.querySelector('.coord-observation-edit');
        var view = block.querySelector('.coord-observation-view');
        var formWrap = block.querySelector('.coord-observation-form');
        var text = block.querySelector('.coord-observation-text');
        var textarea = block.querySelector('.coord-observation-textarea');
        var saveButton = block.querySelector('.coord-observation-save');
        var cancelButton = block.querySelector('.coord-observation-cancel');
        var status = block.querySelector('.coord-observation-status');
        if (!editButton || !view || !formWrap || !textarea) return;

        var saved = textarea.value || '';
        function renderView() {
            var hasContent = saved.trim() !== '';
            text.textContent = hasContent ? saved : 'Nenhuma observação registrada.';
            text.classList.toggle('italic', !hasContent);
            text.classList.toggle('text-gray-400', !hasContent);
            editButton.textContent = hasContent ? 'Editar' : 'Adicionar observação';
            view.classList.remove('hidden');
            formWrap.classList.add('hidden');
        }
        editButton.addEventListener('click', function () {
            textarea.value = saved;
            view.classList.add('hidden');
            formWrap.classList.remove('hidden');
            status.textContent = '';
            textarea.focus();
        });
        cancelButton.addEventListener('click', renderView);
        saveButton.addEventListener('click', function () {
            saveButton.disabled = true;
            status.textContent = 'Salvando...';
            status.className = 'coord-observation-status text-xs text-gray-500';
            var payload = new FormData();
            payload.append('_token', block.dataset.csrf || '');
            payload.append('conteudo', textarea.value || '');
            fetch(block.dataset.endpoint || '', {
                method: 'POST',
                body: payload,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) throw new Error(data.error || 'Erro ao salvar observação');
                    return data;
                });
            }).then(function (data) {
                saved = data.conteudo || '';
                textarea.value = saved;
                renderView();
                status.textContent = 'Observação salva.';
                status.className = 'coord-observation-status text-xs text-emerald-600';
            }).catch(function (error) {
                status.textContent = error.message || 'Erro ao salvar observação.';
                status.className = 'coord-observation-status text-xs text-red-600';
            }).finally(function () {
                saveButton.disabled = false;
            });
        });
    });
})();
</script>
<?php endif; ?>
<?php if ($zipGerando): ?>
<?php include dirname(__DIR__, 2) . '/components/ai-job-poller.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var jobId = <?= (int) $zipJobId ?>;
    var downloadUrl = <?= json_encode(rtrim((string) URL, '/') . '/admin/reports/boletim-coordenacao/zip/' . (int) $zipJobId) ?>;
    var texto = document.getElementById('zip-boletins-texto');
    var banner = document.getElementById('zip-boletins-banner');
    var horariosEl = document.getElementById('zip-boletins-horarios');

    function formatarHorarioZip(valor) {
        if (!valor) return '';
        var s = String(valor).replace('T', ' ').replace(/\.\d+.*/, '');
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        if (!m) return s;
        return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] + ':' + m[6];
    }

    function formatarDuracaoZip(inicio, fim) {
        var a = Date.parse(String(inicio || '').replace(' ', 'T'));
        var b = Date.parse(String(fim || '').replace(' ', 'T'));
        if (!a || !b || b < a) return '';
        var seg = Math.round((b - a) / 1000);
        if (seg < 60) return seg + 's';
        var min = Math.floor(seg / 60);
        var resto = seg % 60;
        if (min < 60) return resto ? (min + ' min ' + resto + 's') : (min + ' min');
        var h = Math.floor(min / 60);
        min = min % 60;
        return min ? (h + 'h ' + min + ' min') : (h + 'h');
    }

    function textoHorarios(inicio, termino, pedido, emAndamento) {
        var ini = formatarHorarioZip(inicio);
        var fim = formatarHorarioZip(termino);
        var ped = formatarHorarioZip(pedido);
        if (ini) {
            var t = 'Início: ' + ini + (fim ? ' · Término: ' + fim : (emAndamento ? ' · Término: em andamento' : ''));
            var dur = formatarDuracaoZip(inicio, termino);
            if (dur) t += ' · Duração: ' + dur;
            return t;
        }
        if (ped) return 'Pedido às ' + ped + ' · Aguardando início da geração';
        return 'Horários da geração aparecem assim que o processamento começar.';
    }

    function atualizarHorarios(data, emAndamento) {
        if (!horariosEl) return;
        data = data || {};
        var result = data.result || {};
        horariosEl.textContent = textoHorarios(
            data.iniciado_em || result.iniciado_em || '',
            data.finalizado_em || result.finalizado_em || data.completed_at || '',
            data.created_at || '',
            emAndamento
        );
    }

    new AIJobPoller(jobId, {
        interval: 4000,
        statusUrl: <?= json_encode(rtrim((string) URL, '/') . '/admin/ai-job/{id}/status') ?>,
        onDone: function (result) {
            var emitidos = result && result.emitidos ? result.emitidos : 0;
            var falhas = result && result.falhas ? result.falhas : 0;
            var linhaHorarios = textoHorarios(
                result && result.iniciado_em,
                result && result.finalizado_em,
                '',
                false
            );
            if (banner) {
                banner.className = 'rounded-lg p-4 mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800';
                banner.innerHTML = '<p class="font-semibold"><i class="fa-solid fa-circle-check mr-2"></i>ZIP pronto'
                    + (emitidos ? ': ' + emitidos + ' boletim(ns)' : '')
                    + (falhas ? ' · ' + falhas + ' falha(s)' : '')
                    + '.</p>'
                    + '<p class="text-sm mt-2 text-emerald-800">' + linhaHorarios + '</p>'
                    + '<a href="' + downloadUrl + '" class="inline-flex items-center mt-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 font-semibold"><i class="fa-solid fa-download mr-2"></i>Baixar ZIP</a>';
            }
            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = downloadUrl;
            document.body.appendChild(iframe);
        },
        onFailed: function (msg) {
            if (banner) {
                banner.className = 'rounded-lg p-4 mb-6 bg-red-50 border border-red-200 text-red-800';
            }
            if (texto) {
                texto.textContent = 'Não foi possível gerar o ZIP: ' + (msg || 'erro desconhecido');
            }
            if (horariosEl) {
                horariosEl.className = 'text-sm mt-2 text-red-700';
            }
        },
        onProgress: function (status, data) {
            atualizarHorarios(data, true);
            if (!texto) return;
            if (status === 'pending') {
                texto.textContent = 'Na fila: a geração começa em instantes.';
            } else if (status === 'processing') {
                texto.textContent = 'Gerando os boletins em segundo plano. Um PDF por aluno, depois o ZIP. Com a escola inteira pode levar vários minutos — deixe esta página aberta.';
            }
        }
    });
});
</script>
<?php endif; ?>
