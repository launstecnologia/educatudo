<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = $base ?? (URL . '/admin/students/' . (int) ($aluno_id ?? 0) . '/vida-escolar');
$token = (string) ($csrf_token ?? $token ?? '');
$traj = is_array($trajetoria['anos'] ?? null) ? $trajetoria['anos'] : [];
$documentos = is_array($documentos ?? $docs_recebidos ?? null) ? ($documentos ?? $docs_recebidos) : [];
$importacoes = is_array($importacoes ?? null) ? $importacoes : [];
$materias = is_array($materias ?? null) ? $materias : [];
$decodificarPayload = static function (array $imp): array {
    $raw = $imp['payload_json'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $raw = json_decode($raw, true);
    }
    return is_array($raw) ? $raw : [];
};
$rotuloStatus = [
    'em_conferencia' => 'Em conferência',
    'rascunho' => 'Rascunho',
    'validada' => 'Validada',
    'cancelada' => 'Cancelada',
];
$badgeImp = static function (string $st): string {
    return match ($st) {
        'validada' => 'bg-green-100 text-green-800',
        'cancelada' => 'bg-slate-100 text-slate-600',
        default => 'bg-amber-100 text-amber-800',
    };
};
$pendentes = [];
foreach ($importacoes as $imp) {
    if (in_array((string) ($imp['status'] ?? ''), ['em_conferencia', 'rascunho'], true)) {
        $pendentes[] = $imp;
    }
}
$escolaAnterior = trim((string) ($escolaAnterior ?? ($prontuario['escola_anterior'] ?? '')));
$veioDeFora = $escolaAnterior !== '';
if ($escolaAnterior === '') {
    foreach ($traj as $anoVe) {
        if (!is_array($anoVe) || ($anoVe['origem'] ?? '') !== 'externo') {
            continue;
        }
        $veioDeFora = true;
        $nomeVe = trim((string) ($anoVe['escola_nome'] ?? ''));
        if ($nomeVe !== '' && $nomeVe !== 'Esta instituição') {
            $escolaAnterior = $nomeVe;
            break;
        }
    }
}
$podeLerIa = !empty($pode_ler_ia);
$aiJobId = (int) ($ai_job_id ?? 0);
$docsHistorico = [];
foreach ($documentos as $d) {
    if (!is_array($d)) {
        continue;
    }
    if (in_array((string) ($d['tipo'] ?? ''), ['historico', 'ficha_individual', 'declaracao_transferencia'], true)) {
        $docsHistorico[] = $d;
    }
}
?>
<?php if ($aiJobId > 0 && (string) ($veAba ?? $aba ?? 'trajetoria') === 'trajetoria'): ?>
<div id="historicoIaLoading" class="flex items-center gap-3 text-sm text-indigo-800 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3 mb-6">
    <i class="fa-solid fa-spinner fa-spin"></i>
    <span>Lendo o histórico. Quando terminar, o rascunho aparece aqui para conferir.</span>
</div>
<div id="historicoIaErro" class="hidden rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 mb-6"></div>
<?php endif; ?>
<?php if ($veioDeFora && empty($veBannerOrigem)): ?>
<div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 mb-6 text-sm text-violet-950">
    <p class="font-semibold">Veio de outra escola<?= $escolaAnterior !== '' ? ': ' . $esc($escolaAnterior) : '' ?></p>
    <p class="mt-1 text-violet-900">Os anos já concluídos ficam na lista abaixo. O boletim desta escola não recebe essas notas.</p>
</div>
<?php endif; ?>
<?php if ($pendentes !== []): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-violet-100">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Rascunho da leitura com IA</h3>
    <p class="text-sm text-gray-500 mb-4">Confira os anos e as notas extraídos. Só entram na trajetória oficial depois de <strong>Validar</strong>.</p>
    <?php foreach ($pendentes as $imp): ?>
        <?php
        $payload = $decodificarPayload($imp);
        $anosIa = is_array($payload['anos_anteriores'] ?? null) ? $payload['anos_anteriores'] : [];
        $bimsIa = is_array($payload['bimestres_atuais'] ?? null) ? $payload['bimestres_atuais'] : [];
        $st = (string) ($imp['status'] ?? '');
        ?>
        <div class="border border-gray-100 rounded-xl p-4 mb-4 last:mb-0">
            <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900"><?= $esc($imp['escola_origem'] ?? 'Escola de origem') ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <?= $esc($imp['municipio'] ?? '') ?><?= !empty($imp['uf']) ? ' / ' . $esc($imp['uf']) : '' ?>
                        <?php if (!empty($imp['data_transferencia'])): ?>
                            · transferência <?= $esc(date('d/m/Y', strtotime((string) $imp['data_transferencia']))) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeImp($st) ?>"><?= $esc($rotuloStatus[$st] ?? $st) ?></span>
            </div>
            <?php if ($anosIa === [] && $bimsIa === []): ?>
                <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">A leitura não encontrou anos ou notas. Abra o PDF na aba Histórico / emissões, clique de novo em <strong>Ler com IA</strong> ou use <strong>Lançar Escola</strong>.</p>
            <?php endif; ?>
            <?php if ($anosIa !== []): ?>
                <p class="text-xs font-medium text-gray-700 mb-2">Anos anteriores</p>
                <ul class="space-y-2 mb-3">
                    <?php foreach ($anosIa as $anoIa): ?>
                        <?php if (!is_array($anoIa)) { continue; } ?>
                        <li class="text-sm text-gray-800">
                            <span class="font-medium"><?= $esc($anoIa['ano_letivo'] ?? '') ?></span>
                            · <?= $esc($anoIa['serie_ano'] ?? $anoIa['serie'] ?? '') ?>
                            · <?= $esc($anoIa['resultado'] ?? '—') ?>
                            <?php $comps = is_array($anoIa['componentes'] ?? null) ? $anoIa['componentes'] : []; ?>
                            <?php if ($comps !== []): ?>
                                <span class="block text-xs text-gray-600 mt-1">
                                    <?php foreach ($comps as $c): ?>
                                        <?php if (!is_array($c)) { continue; } ?>
                                        <span class="inline-block mr-3 mb-1"><?= $esc($c['componente_original'] ?? $c['componente'] ?? '') ?>: <strong><?= $esc($c['nota_original'] ?? $c['nota'] ?? '—') ?></strong></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($bimsIa !== []): ?>
                <p class="text-xs font-medium text-gray-700 mb-2">Bimestres do ano atual</p>
                <p class="text-xs text-gray-600 mb-3">
                    <?php foreach ($bimsIa as $b): ?>
                        <?php if (!is_array($b)) { continue; } ?>
                        <span class="inline-block mr-3 mb-1"><?= $esc($b['componente'] ?? $b['componente_original'] ?? '') ?> · <?= (int) ($b['periodo_numero'] ?? $b['bimestre'] ?? 0) ?>º bim: <strong><?= $esc($b['nota'] ?? '—') ?></strong></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <?php if ($st !== 'validada' && $st !== 'cancelada'): ?>
            <form method="post" action="<?= $base ?>/importar/<?= (int) $imp['id'] ?>/validar" onsubmit="return confirm('Validar e gravar no boletim/histórico?');">
                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold" <?= ($anosIa === [] && $bimsIa === []) ? 'disabled title="Sem anos ou notas para validar"' : '' ?>>Validar leitura</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($traj !== []): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Anos registrados</h3>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ano</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Série</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Escola</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Origem</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <?php foreach ($traj as $idxAno => $ano):
                    $comps = is_array($ano['componentes'] ?? null) ? $ano['componentes'] : [];
                    $painelId = 've-boletim-ano-' . (int) ($ano['id'] ?? $idxAno);
                ?>
                <tbody class="divide-y divide-gray-100">
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-900"><?= $esc($ano['ano_letivo'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= $esc($ano['serie_ano'] ?? '') ?></td>
                        <td class="px-3 py-2"><?= $esc($ano['escola_nome'] ?? '—') ?></td>
                        <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full <?= ($ano['origem'] ?? '') === 'externo' ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-700' ?>"><?= ($ano['origem'] ?? '') === 'externo' ? 'Outra escola' : 'Esta escola' ?></span></td>
                        <td class="px-3 py-2"><?= $esc($ano['resultado'] ?? '—') ?></td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <?php if ($comps === []): ?>
                                <span class="text-xs text-gray-400">Sem notas</span>
                            <?php else: ?>
                                <button type="button"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        data-ve-boletim-toggle="<?= $esc($painelId) ?>"
                                        aria-expanded="false"
                                        aria-controls="<?= $esc($painelId) ?>"
                                        onclick="veToggleBoletimAno(this)">
                                    <i class="fa-solid fa-table mr-1.5 text-gray-400"></i>
                                    Boletim
                                    <i class="fa-solid fa-chevron-down ml-1.5 text-[10px] text-gray-400 ve-boletim-chevron"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($comps !== []): ?>
                    <tr id="<?= $esc($painelId) ?>" class="hidden bg-slate-50">
                        <td colspan="6" class="px-3 py-3">
                            <p class="text-xs font-medium text-gray-600 mb-2">Boletim · <?= $esc($ano['serie_ano'] ?? '') ?> · <?= $esc($ano['ano_letivo'] ?? '') ?></p>
                            <table class="min-w-full text-xs bg-white border border-gray-200 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-1.5 text-left font-medium text-gray-500">Disciplina</th>
                                        <th class="px-3 py-1.5 text-center font-medium text-gray-500 w-24">Nota</th>
                                        <th class="px-3 py-1.5 text-center font-medium text-gray-500 w-24">Carga horária</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($comps as $c):
                                        $nota = $c['nota_convertida'] ?? $c['nota_original'] ?? '';
                                        if (is_numeric($nota)) {
                                            $nota = number_format((float) $nota, 1, ',', '');
                                        }
                                        $ch = $c['carga_horaria'] ?? '';
                                    ?>
                                    <tr>
                                        <td class="px-3 py-1.5 text-gray-800"><?= $esc($c['componente_original'] ?? '') ?></td>
                                        <td class="px-3 py-1.5 text-center font-semibold text-gray-900"><?= $nota !== '' && $nota !== null ? $esc((string) $nota) : '—' ?></td>
                                        <td class="px-3 py-1.5 text-center text-gray-600"><?= $ch !== '' && $ch !== null ? $esc((string) $ch) . ' h' : '—' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <?php endforeach; ?>
            </table>
        </div>
</div>
<?php endif; ?>

<?php
$veOrigemInicial = (string) ($_GET['ve_origem'] ?? '') === 'meio' ? 'meio' : 'historico';
$vePodeVerLog = is_array($user ?? null) && (string) ($user['perfil_admin'] ?? '') === 'dev';
?>
<?php if (!empty($admin_permissions['vida_escolar']['cadastrar'])): ?>
<div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm mb-4">
        <div class="flex flex-wrap gap-2" role="tablist">
            <button type="button" class="ve-origem-pill px-3 py-1.5 rounded-full <?= $veOrigemInicial === 'historico' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>" data-ve-origem="historico" onclick="veAbaOrigem('historico')">Histórico de outra escola</button>
            <button type="button" class="ve-origem-pill px-3 py-1.5 rounded-full <?= $veOrigemInicial === 'meio' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>" data-ve-origem="meio" onclick="veAbaOrigem('meio')">Chegou no meio do ano</button>
        </div>
        <?php if ($vePodeVerLog): ?>
        <button type="button" onclick="veAbrirLogMeioAno()" class="inline-flex items-center px-3 py-1.5 rounded-full border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-clock-rotate-left mr-1.5 text-xs"></i>Log
        </button>
        <?php endif; ?>
    </div>

    <div data-ve-origem-painel="historico" class="<?= $veOrigemInicial === 'historico' ? '' : 'hidden' ?>">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <p class="text-sm text-gray-500 flex-1 min-w-[16rem]">Anos já concluídos. Anexe o PDF para a leitura ou digite o ano. O ano desta escola entra sozinho na homologação do boletim.</p>
            <button type="button" onclick="veAbrirLancarEscola()"
                    class="shrink-0 inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-800 hover:bg-gray-50">
                <i class="fa-solid fa-plus mr-1.5"></i>Digitar um ano
            </button>
        </div>
        <?php
        $docsBusca = [];
        if (!class_exists('StudentDocument', false)) {
            $pathDocAluno = dirname(__DIR__, 4) . '/Models/User/StudentDocument.php';
            if (is_file($pathDocAluno)) {
                require_once $pathDocAluno;
            }
        }
        $rotulosDoc = class_exists('StudentDocument', false) ? StudentDocument::checklist() : [];
        $ordemDoc = ['historico_escolar' => 0, 'declaracao_transferencia' => 1];
        foreach (is_array($documentos_aluno ?? null) ? $documentos_aluno : [] as $docFicha) {
            if (!is_array($docFicha) || trim((string) ($docFicha['arquivo_key'] ?? '')) === '') {
                continue;
            }
            $nomeArq = (string) ($docFicha['arquivo_nome'] ?? '');
            $extArq = strtolower((string) pathinfo($nomeArq, PATHINFO_EXTENSION));
            if (!in_array($extArq, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }
            $tipoFicha = (string) ($docFicha['tipo'] ?? '');
            if (!isset($ordemDoc[$tipoFicha])) {
                continue;
            }
            $rotulo = (string) ($rotulosDoc[$tipoFicha] ?? $tipoFicha);
            $docsBusca[] = [
                'ordem' => $ordemDoc[$tipoFicha] ?? 5,
                'rotulo' => $rotulo,
                'arquivo' => $nomeArq !== '' ? $nomeArq : 'Arquivo',
                'origem' => 'ficha',
                'id' => (int) ($docFicha['id'] ?? 0),
            ];
        }
        foreach ($docsHistorico as $docVe) {
            if (!is_array($docVe) || trim((string) ($docVe['arquivo_key'] ?? '')) === '') {
                continue;
            }
            $nomeArq = (string) ($docVe['arquivo_nome'] ?? '');
            $docsBusca[] = [
                'ordem' => 3,
                'rotulo' => 'Já recebido na trajetória',
                'arquivo' => $nomeArq !== '' ? $nomeArq : (string) ($docVe['escola_emissora'] ?? 'Documento'),
                'origem' => 'vida',
                'id' => (int) ($docVe['id'] ?? 0),
            ];
        }
        usort($docsBusca, static function (array $a, array $b): int {
            $cmp = ($a['ordem'] <=> $b['ordem']);
            return $cmp !== 0 ? $cmp : strcasecmp((string) $a['arquivo'], (string) $b['arquivo']);
        });
        ?>
        <div class="rounded-xl border border-gray-200 p-4 mb-4">
            <h4 class="text-sm font-semibold text-gray-900">Documento já na ficha</h4>
            <p class="text-sm text-gray-500 mt-1 mb-3">Se a coordenação já anexou o histórico em Documentos, busque o arquivo aqui. Não precisa enviar de novo.</p>
            <?php if ($docsBusca === []): ?>
            <p class="text-sm text-gray-500">Nenhum histórico escolar ou declaração de transferência na aba Documentos deste aluno.</p>
            <?php else: ?>
            <input type="search" id="ve-busca-doc" placeholder="Buscar por histórico, transferência ou nome do arquivo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white mb-3">
            <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg text-sm">
                <?php foreach ($docsBusca as $docBusca):
                    $textoBusca = mb_strtolower($docBusca['rotulo'] . ' ' . $docBusca['arquivo'], 'UTF-8');
                    $acaoLer = $docBusca['origem'] === 'ficha'
                        ? $base . '/documento-ficha/' . (int) $docBusca['id'] . '/ler'
                        : $base . '/documento/' . (int) $docBusca['id'] . '/ler';
                ?>
                <li class="flex items-center justify-between gap-3 px-3 py-2" data-ve-doc-item data-ve-doc-busca="<?= $esc($textoBusca) ?>">
                    <span class="min-w-0">
                        <span class="block font-medium text-gray-900 truncate"><?= $esc($docBusca['rotulo']) ?></span>
                        <span class="block text-xs text-gray-500 truncate"><?= $esc($docBusca['arquivo']) ?></span>
                    </span>
                    <?php if ($podeLerIa && (int) $docBusca['id'] > 0): ?>
                    <form method="post" action="<?= $esc($acaoLer) ?>" class="shrink-0">
                        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                        <?php if ($docBusca['origem'] === 'ficha' && $escolaAnterior !== ''): ?>
                        <input type="hidden" name="escola_emissora" value="<?= $esc($escolaAnterior) ?>">
                        <?php endif; ?>
                        <button class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-semibold text-gray-800 hover:bg-gray-50">Ler</button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <p id="ve-busca-doc-vazio" class="hidden text-sm text-gray-500 mt-2">Nenhum documento com esse nome.</p>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= $base ?>/documento" enctype="multipart/form-data" class="rounded-xl border border-gray-200 p-4 space-y-3">
            <input type="hidden" name="_token" value="<?= $esc($token) ?>">
            <input type="hidden" name="tipo" value="historico">
            <?php if ($podeLerIa): ?>
            <input type="hidden" name="ler_agora" value="1">
            <?php endif; ?>
            <div>
                <h4 class="text-sm font-semibold text-gray-900">Enviar um arquivo novo</h4>
                <p class="text-sm text-gray-500 mt-1 mb-3">Só se o histórico ainda não estiver na ficha.</p>
                <label id="ve-historico-drop" for="ve-historico-arquivo"
                       class="flex flex-col items-center justify-center w-full border border-dashed border-gray-300 rounded-lg bg-gray-50 px-3 py-6 text-center cursor-pointer hover:bg-gray-100">
                    <input type="file" name="arquivo" id="ve-historico-arquivo" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only">
                    <span class="text-sm text-gray-700">Arraste o arquivo aqui</span>
                    <span id="ve-historico-arquivo-nome" class="text-xs text-gray-500 mt-1">ou clique para escolher · PDF ou imagem</span>
                </label>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[16rem]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Escola</label>
                    <input name="escola_emissora" value="<?= $esc($escolaAnterior) ?>" placeholder="Nome da escola de origem" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                </div>
                <button class="btn-primary-custom shrink-0 px-4 py-2 rounded-lg text-sm font-semibold"><?= $podeLerIa ? 'Anexar e ler' : 'Anexar PDF' ?></button>
            </div>
        </form>
    </div>

    <div data-ve-origem-painel="meio" class="<?= $veOrigemInicial === 'meio' ? '' : 'hidden' ?>">
    <?php
    $anoTurma = 0;
    if (is_array($aluno ?? null)) {
        $anoTurma = (int) ($aluno['turma_ano_calendario'] ?? 0);
        if ($anoTurma < 2000 || $anoTurma > 2100) {
            $anoTurma = (int) ($aluno['turma_ano_letivo'] ?? 0);
        }
    }
    $anoPeriodo = $anoTurma;
    $fichaMeio = is_array(($quadro ?? [])['ficha'] ?? null) ? $quadro['ficha'] : [];
    $anoFicha = (int) ($fichaMeio['ano_letivo'] ?? 0);
    if ($anoFicha >= 2000 && $anoFicha <= 2100) {
        $anoPeriodo = $anoFicha;
    }
    if ($anoPeriodo < 2000 || $anoPeriodo > 2100) {
        $anoPeriodo = (int) date('Y');
    }
    if (!class_exists('PeriodoLetivo', false)) {
        require_once dirname(__DIR__, 4) . '/Core/PeriodoLetivo.php';
    }
    $infoPeriodo = PeriodoLetivo::doAno($anoPeriodo);
    $periodosMeio = is_array($infoPeriodo['rotulos'] ?? null) && $infoPeriodo['rotulos'] !== []
        ? $infoPeriodo['rotulos']
        : [1 => '1º Bimestre', 2 => '2º Bimestre', 3 => '3º Bimestre', 4 => '4º Bimestre'];
    $nomePeriodos = mb_strtolower((string) ($infoPeriodo['rotulo_campo_plural'] ?? 'períodos'));
    $tipoPeriodo = (string) ($infoPeriodo['tipo'] ?? 'bimestre');
    $rotuloDivisao = (string) (PeriodoLetivo::TIPOS[$tipoPeriodo] ?? 'Bimestral (4 períodos)');
    ?>
    <p class="text-sm text-gray-500 mb-4">Só se a outra escola já lançou <?= $esc($nomePeriodos) ?> de <?= (int) $anoPeriodo ?>. A nota digitada entra no boletim e esse período sai da aba Notas, porque veio de outra escola. Anos fechados ficam na outra aba.</p>
    <p class="text-xs text-gray-500 mb-4">Ano letivo <?= (int) $anoPeriodo ?> · <?= $esc($rotuloDivisao) ?>. As colunas seguem essa divisão (bimestre, trimestre, semestre ou etapa única), a mesma do boletim.</p>
    <?php
    $outrasImp = [];
    foreach ($importacoes as $imp) {
        if (!in_array((string) ($imp['status'] ?? ''), ['em_conferencia', 'rascunho'], true)) {
            $outrasImp[] = $imp;
        }
    }
    ?>
    <?php if ($outrasImp !== []): ?>
        <ul class="text-sm mb-4 space-y-2">
            <?php foreach ($outrasImp as $imp): ?>
                <?php $st = (string) ($imp['status'] ?? ''); ?>
                <li class="flex items-center justify-between gap-3 border border-gray-100 rounded-lg px-3 py-2">
                    <span><?= $esc($imp['escola_origem'] ?? '—') ?> · <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeImp($st) ?>"><?= $esc($rotuloStatus[$st] ?? $st) ?></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!empty($admin_permissions['vida_escolar']['cadastrar'])): ?>
    <?php
    $dataTransfMeio = '';
    foreach ($importacoes as $impData) {
        $rawData = trim((string) ($impData['data_transferencia'] ?? ''));
        if ($rawData !== '') {
            $dataTransfMeio = substr($rawData, 0, 10);
            break;
        }
    }
    ?>
    <form method="post" action="<?= $base ?>/importar" class="space-y-5" id="form-importar-transferencia">
        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
        <input type="hidden" name="anos_qtd" value="0">
        <input type="hidden" name="aplicar_boletim" value="1">
        <input type="hidden" name="ficha_id" value="<?= (int) ($fichaMeio['id'] ?? 0) ?>">
        <input type="hidden" name="ano_letivo" value="<?= (int) $anoPeriodo ?>">
        <input type="hidden" name="senha_confirmacao" id="ve-senha-meio" value="">
        <div class="rounded-xl border border-gray-200 p-4">
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Escola de origem</h4>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Escola <span class="text-red-500">*</span></label>
                    <input name="escola_origem" required value="<?= $esc($escolaAnterior) ?>" placeholder="Nome da escola" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white" style="width:100%;box-sizing:border-box;">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data da transferência</label>
                        <input type="date" name="data_transferencia" value="<?= $esc($dataTransfMeio) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white" style="width:100%;box-sizing:border-box;">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Documento anexado</label>
                        <select name="documento_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white" style="width:100%;box-sizing:border-box;">
                            <option value="0">Nenhum</option>
                            <?php foreach ($documentos as $d): ?>
                                <?php if (!is_array($d)) { continue; } ?>
                                <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $esc(($d['tipo'] ?? 'documento') . ' #' . (int) ($d['id'] ?? 0)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $componentesBim = [];
        foreach (is_array(($quadro ?? [])['grid'] ?? null) ? $quadro['grid'] : [] as $rowG) {
            $linha = is_array($rowG['linha'] ?? null) ? $rowG['linha'] : [];
            $nomeLinha = trim((string) ($linha['componente_nome'] ?? ''));
            $mid = (int) ($linha['materia_id'] ?? 0);
            if ($nomeLinha === '' && $mid <= 0) {
                continue;
            }
            $componentesBim[] = ['id' => $mid, 'nome' => $nomeLinha !== '' ? $nomeLinha : ('Matéria #' . $mid)];
        }
        if ($componentesBim === []) {
            foreach (is_array($componentes_turma ?? null) ? $componentes_turma : [] as $compTurma) {
                if (!is_array($compTurma)) {
                    continue;
                }
                $nomeLinha = trim((string) ($compTurma['componente_nome'] ?? ''));
                $mid = (int) ($compTurma['materia_id'] ?? 0);
                if ($nomeLinha === '' && $mid <= 0) {
                    continue;
                }
                $componentesBim[] = ['id' => $mid, 'nome' => $nomeLinha !== '' ? $nomeLinha : ('Matéria #' . $mid)];
            }
        }
        $nBlocos = count($componentesBim);
        $celBim = 'w-16 border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-center bg-white';
        $meioPorId = [];
        $meioPorNome = [];
        $guardarMeio = static function (int $mid, string $nome, int $periodo, string $nota, string $faltas) use (&$meioPorId, &$meioPorNome): void {
            if ($periodo < 1 || $periodo > 4 || ($nota === '' && $faltas === '')) {
                return;
            }
            $cel = ['nota' => $nota, 'faltas' => $faltas];
            if ($mid > 0 && !isset($meioPorId[$mid][$periodo])) {
                $meioPorId[$mid][$periodo] = $cel;
            }
            $nomeKey = mb_strtolower(trim($nome));
            if ($nomeKey !== '' && !isset($meioPorNome[$nomeKey][$periodo])) {
                $meioPorNome[$nomeKey][$periodo] = $cel;
            }
        };
        foreach ($importacoes as $impMeio) {
            if (!is_array($impMeio) || (string) ($impMeio['status'] ?? '') === 'cancelada') {
                continue;
            }
            $payloadMeio = $decodificarPayload($impMeio);
            foreach (is_array($payloadMeio['bimestres_atuais'] ?? null) ? $payloadMeio['bimestres_atuais'] : [] as $bMeio) {
                if (!is_array($bMeio)) {
                    continue;
                }
                $guardarMeio(
                    (int) ($bMeio['materia_id'] ?? 0),
                    (string) ($bMeio['componente'] ?? $bMeio['componente_original'] ?? ''),
                    (int) ($bMeio['periodo_numero'] ?? $bMeio['bimestre'] ?? 0),
                    trim((string) ($bMeio['nota'] ?? '')),
                    trim((string) ($bMeio['faltas'] ?? ''))
                );
            }
        }
        foreach (is_array(($quadro ?? [])['grid'] ?? null) ? $quadro['grid'] : [] as $rowMeio) {
            $linhaMeio = is_array($rowMeio['linha'] ?? null) ? $rowMeio['linha'] : [];
            $midMeio = (int) ($linhaMeio['materia_id'] ?? 0);
            $nomeMeio = (string) ($linhaMeio['componente_nome'] ?? '');
            foreach (is_array($rowMeio['celulas'] ?? null) ? $rowMeio['celulas'] : [] as $pMeio => $cMeio) {
                if (!is_array($cMeio) || (string) ($cMeio['origem'] ?? '') !== 'externa') {
                    continue;
                }
                $notaMeio = $cMeio['nota'] ?? '';
                if (is_numeric($notaMeio)) {
                    $notaMeio = number_format((float) $notaMeio, 1, ',', '');
                }
                $faltasMeio = ($cMeio['faltas'] ?? '') !== '' && $cMeio['faltas'] !== null ? (string) (int) $cMeio['faltas'] : '';
                $periodoMeio = (int) $pMeio;
                if ($midMeio > 0) {
                    $meioPorId[$midMeio][$periodoMeio] = ['nota' => (string) $notaMeio, 'faltas' => $faltasMeio];
                }
                $nomeKeyMeio = mb_strtolower(trim($nomeMeio));
                if ($nomeKeyMeio !== '') {
                    $meioPorNome[$nomeKeyMeio][$periodoMeio] = ['nota' => (string) $notaMeio, 'faltas' => $faltasMeio];
                }
            }
        }
        ?>
        <div class="flex items-center justify-between gap-3 mb-3">
            <p class="text-sm text-gray-600"><?= $nBlocos > 0 ? 'Componentes da turma. Preencha só os ' . $esc($nomePeriodos) . ' que constam no documento.' : 'A turma ainda não tem componentes. Adicione os que constam no documento.' ?></p>
            <button type="button" class="shrink-0 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50" onclick="veAddBlocoBim()">
                <i class="fa-solid fa-plus mr-1"></i>Adicionar componente
            </button>
        </div>
        <input type="hidden" name="bim_bloco_qtd" id="ve-bim-bloco-qtd" value="<?= (int) $nBlocos ?>">
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nome no documento</th>
                        <?php foreach ($periodosMeio as $rotuloP): ?>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-700 normal-case" colspan="2"><?= $esc($rotuloP) ?></th>
                        <?php endforeach; ?>
                        <th class="px-2 py-2 w-10"></th>
                    </tr>
                    <tr class="border-t border-gray-100">
                        <th colspan="2"></th>
                        <?php foreach ($periodosMeio as $rotuloP): ?>
                        <th class="px-2 py-1 text-center text-[11px] font-medium text-gray-400">Nota</th>
                        <th class="px-2 py-1 text-center text-[11px] font-medium text-gray-400">Faltas</th>
                        <?php endforeach; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="ve-blocos-bim" class="divide-y divide-gray-100">
                    <?php foreach ($componentesBim as $i => $compB): ?>
                    <tr class="ve-bloco-bim">
                        <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap"><?= $esc($compB['nome'] ?? '') ?></td>
                        <td class="px-3 py-2">
                            <input type="hidden" name="bim_materia_id[<?= $i ?>]" value="<?= (int) ($compB['id'] ?? 0) ?>">
                            <input name="bim_comp[<?= $i ?>]" value="<?= $esc($compB['nome'] ?? '') ?>" class="w-44 border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white">
                        </td>
                        <?php foreach ($periodosMeio as $p => $rotuloP): ?>
                        <?php
                        $salvoMeio = $meioPorId[(int) ($compB['id'] ?? 0)][(int) $p]
                            ?? $meioPorNome[mb_strtolower((string) ($compB['nome'] ?? ''))][(int) $p]
                            ?? null;
                        ?>
                        <td class="px-2 py-2 text-center"><input name="bim_nota[<?= $i ?>][<?= (int) $p ?>]" value="<?= $esc(is_array($salvoMeio) ? ($salvoMeio['nota'] ?? '') : '') ?>" inputmode="decimal" class="<?= $celBim ?>"></td>
                        <td class="px-2 py-2 text-center"><input name="bim_faltas[<?= $i ?>][<?= (int) $p ?>]" value="<?= $esc(is_array($salvoMeio) ? ($salvoMeio['faltas'] ?? '') : '') ?>" inputmode="numeric" class="<?= $celBim ?>"></td>
                        <?php endforeach; ?>
                        <td class="px-2 py-2 text-center">
                            <button type="button" class="text-gray-400 hover:text-red-600" onclick="veRemoverLinhaBim(this)" aria-label="Remover componente"><i class="fa-solid fa-xmark"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <template id="ve-tpl-bloco-bim">
            <tr class="ve-bloco-bim">
                <td class="px-3 py-2">
                    <select name="bim_materia_id[__I__]" class="w-44 border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white" onchange="veNomeDoComponente(this)">
                        <option value="0">Componente</option>
                        <?php foreach ($materias as $m): ?>
                        <option value="<?= (int) ($m['id'] ?? 0) ?>"><?= $esc($m['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="px-3 py-2">
                    <input name="bim_comp[__I__]" placeholder="Como está no documento" class="w-44 border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white">
                </td>
                <?php foreach ($periodosMeio as $p => $rotuloP): ?>
                <td class="px-2 py-2 text-center"><input name="bim_nota[__I__][<?= (int) $p ?>]" inputmode="decimal" class="<?= $celBim ?>"></td>
                <td class="px-2 py-2 text-center"><input name="bim_faltas[__I__][<?= (int) $p ?>]" inputmode="numeric" class="<?= $celBim ?>"></td>
                <?php endforeach; ?>
                <td class="px-2 py-2 text-center">
                    <button type="button" class="text-gray-400 hover:text-red-600" onclick="veRemoverLinhaBim(this)" aria-label="Remover componente"><i class="fa-solid fa-xmark"></i></button>
                </td>
            </tr>
        </template>
        <button class="btn-primary-custom mt-4 px-4 py-2 rounded-lg text-sm font-semibold">Salvar notas no boletim</button>
    </form>
    <div id="veModalSenhaMeio" class="fixed inset-0 z-[100] hidden" aria-modal="true" role="dialog">
        <div class="absolute inset-0 bg-black/50" onclick="veFecharSenhaMeio()"></div>
        <div class="relative mx-auto mt-24 w-full max-w-md bg-white rounded-xl shadow-xl p-6">
            <h3 class="text-lg font-semibold text-gray-900">Confirmar senha</h3>
            <p class="text-sm text-gray-600 mt-1">Digite a senha da sua conta para lançar essas notas no boletim. O registro fica no log, com quem lançou e o horário.</p>
            <div class="mt-4">
                <label for="veSenhaMeioInput" class="block text-sm font-medium text-gray-700 mb-1">Senha de acesso</label>
                <input type="password" id="veSenhaMeioInput" autocomplete="current-password"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();veConfirmarSenhaMeio();}">
                <p id="veSenhaMeioErro" class="hidden text-xs text-red-600 mt-2"></p>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" onclick="veFecharSenhaMeio()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm">Cancelar</button>
                <button type="button" onclick="veConfirmarSenhaMeio()" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Salvar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>
</div>
<?php if ($vePodeVerLog): ?>
<?php
$logsMeioAno = is_array(($quadro['log_meio_ano'] ?? null)) ? $quadro['log_meio_ano'] : [];
?>
<div id="veLogMeioBackdrop" class="fixed inset-0 bg-black/40 z-[97] hidden" onclick="veFecharLogMeioAno()"></div>
<aside id="veLogMeioDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-[98] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       role="dialog"
       aria-labelledby="veLogMeioTitulo">
    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
        <div>
            <h2 id="veLogMeioTitulo" class="text-xl font-bold text-gray-900">Log do meio do ano</h2>
            <p class="text-sm text-gray-500 mt-0.5">Quem lançou as notas da outra escola, o horário e o que entrou no boletim.</p>
        </div>
        <button type="button" onclick="veFecharLogMeioAno()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
        <?php if ($logsMeioAno === []): ?>
            <p class="text-sm text-gray-500">Nenhum lançamento registrado.</p>
        <?php else: ?>
            <?php foreach ($logsMeioAno as $logMeio): ?>
                <?php
                if (!is_array($logMeio)) {
                    continue;
                }
                $detMeio = json_decode((string) ($logMeio['valor_novo'] ?? ''), true);
                $detMeio = is_array($detMeio) ? $detMeio : [];
                $itensMeio = is_array($detMeio['itens'] ?? null) ? $detMeio['itens'] : [];
                $quandoMeio = !empty($logMeio['created_at']) ? date('d/m/Y H:i', strtotime((string) $logMeio['created_at'])) : '—';
                ?>
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-900"><?= $esc($logMeio['usuario_nome'] ?? 'Usuário') ?></p>
                        <p class="text-xs text-gray-500 tabular-nums"><?= $esc($quandoMeio) ?></p>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        <?= $esc($logMeio['usuario_perfil'] ?? '') ?>
                        <?php if (!empty($detMeio['escola'])): ?>
                            · <?= $esc((string) $detMeio['escola']) ?>
                        <?php endif; ?>
                        <?php if (!empty($detMeio['ano_letivo'])): ?>
                            · <?= (int) $detMeio['ano_letivo'] ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($itensMeio !== []): ?>
                    <ul class="mt-3 space-y-1 text-sm text-gray-800">
                        <?php foreach ($itensMeio as $itemMeio): ?>
                            <?php if (!is_array($itemMeio)) { continue; } ?>
                            <?php $pItem = (int) ($itemMeio['periodo'] ?? 0); ?>
                            <li>
                                <span class="font-medium"><?= $esc($itemMeio['componente'] ?? 'Componente') ?></span>
                                · <?= $esc((string) ($periodosMeio[$pItem] ?? ($pItem . 'º'))) ?>
                                · nota <?= $esc(($itemMeio['nota'] ?? '') !== '' ? (string) $itemMeio['nota'] : '—') ?>
                                · faltas <?= $esc(($itemMeio['faltas'] ?? '') !== '' && $itemMeio['faltas'] !== null ? (string) $itemMeio['faltas'] : '—') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</aside>
<?php endif; ?>
<?php endif; ?>
<script>
(function () {
    var zona = document.getElementById('ve-historico-drop');
    var input = document.getElementById('ve-historico-arquivo');
    var nome = document.getElementById('ve-historico-arquivo-nome');
    if (!zona || !input) return;
    function aceita(file) {
        if (!file) return false;
        var n = (file.name || '').toLowerCase();
        return /\.(pdf|jpe?g|png|webp)$/.test(n);
    }
    var aplicando = false;
    function aplicar(file, jaNoInput) {
        if (!aceita(file)) {
            if (nome && !(input.files && input.files[0])) {
                nome.textContent = 'Use PDF ou imagem (jpg, png, webp).';
            }
            return;
        }
        if (!jaNoInput) {
            aplicando = true;
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            aplicando = false;
        }
        if (nome) nome.textContent = file.name;
    }
    input.addEventListener('change', function () {
        if (aplicando) return;
        if (input.files && input.files[0]) aplicar(input.files[0], true);
    });
    ['dragenter', 'dragover'].forEach(function (ev) {
        zona.addEventListener(ev, function (e) {
            e.preventDefault();
            zona.style.borderColor = '#2563eb';
            zona.style.background = '#eff6ff';
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        zona.addEventListener(ev, function (e) {
            e.preventDefault();
            zona.style.borderColor = '';
            zona.style.background = '';
        });
    });
    zona.addEventListener('drop', function (e) {
        var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        aplicar(file);
    });
})();
(function () {
    var busca = document.getElementById('ve-busca-doc');
    if (!busca) return;
    busca.addEventListener('input', function () {
        var q = (busca.value || '').toLocaleLowerCase().trim();
        var algum = false;
        document.querySelectorAll('[data-ve-doc-item]').forEach(function (li) {
            var hay = (li.getAttribute('data-ve-doc-busca') || '').toLocaleLowerCase();
            var ok = q === '' || hay.indexOf(q) !== -1;
            li.classList.toggle('hidden', !ok);
            if (ok) algum = true;
        });
        var vazio = document.getElementById('ve-busca-doc-vazio');
        if (vazio) vazio.classList.toggle('hidden', algum || q === '');
    });
})();
function veAbrirLancarEscola() {
    var drawer = document.getElementById('veLancarEscolaDrawer');
    var backdrop = document.getElementById('veLancarEscolaBackdrop');
    if (!drawer || !backdrop) return;
    backdrop.classList.remove('hidden');
    requestAnimationFrame(function () {
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
    });
    document.body.style.overflow = 'hidden';
    var primeiro = document.getElementById('ve_escola_nome');
    if (primeiro) setTimeout(function () { primeiro.focus(); }, 280);
}
function veFecharLancarEscola() {
    var drawer = document.getElementById('veLancarEscolaDrawer');
    var backdrop = document.getElementById('veLancarEscolaBackdrop');
    if (drawer) {
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
    }
    if (backdrop) backdrop.classList.add('hidden');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var modalSenha = document.getElementById('veModalSenhaMeio');
    if (modalSenha && !modalSenha.classList.contains('hidden')) {
        veFecharSenhaMeio();
        return;
    }
    var logDrawer = document.getElementById('veLogMeioDrawer');
    if (logDrawer && logDrawer.getAttribute('aria-hidden') === 'false') {
        veFecharLogMeioAno();
        return;
    }
    var drawer = document.getElementById('veLancarEscolaDrawer');
    if (!drawer || drawer.getAttribute('aria-hidden') === 'true') return;
    veFecharLancarEscola();
});
function veAbrirLogMeioAno() {
    var drawer = document.getElementById('veLogMeioDrawer');
    var backdrop = document.getElementById('veLogMeioBackdrop');
    if (!drawer || !backdrop) return;
    backdrop.classList.remove('hidden');
    requestAnimationFrame(function () {
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
    });
    document.body.style.overflow = 'hidden';
}
function veFecharLogMeioAno() {
    var drawer = document.getElementById('veLogMeioDrawer');
    var backdrop = document.getElementById('veLogMeioBackdrop');
    if (drawer) {
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
    }
    if (backdrop) backdrop.classList.add('hidden');
    document.body.style.overflow = '';
}
function veFecharSenhaMeio() {
    var modal = document.getElementById('veModalSenhaMeio');
    if (modal) modal.classList.add('hidden');
}
function veConfirmarSenhaMeio() {
    var form = document.getElementById('form-importar-transferencia');
    var input = document.getElementById('veSenhaMeioInput');
    var hidden = document.getElementById('ve-senha-meio');
    var erro = document.getElementById('veSenhaMeioErro');
    var senha = input ? (input.value || '').trim() : '';
    if (senha === '') {
        if (erro) {
            erro.textContent = 'Informe sua senha.';
            erro.classList.remove('hidden');
        }
        return;
    }
    if (hidden) hidden.value = senha;
    if (input) input.value = '';
    if (form) {
        form.setAttribute('data-ve-senha-ok', '1');
        form.submit();
    }
}
(function () {
    var form = document.getElementById('form-importar-transferencia');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (form.getAttribute('data-ve-senha-ok') === '1') return;
        e.preventDefault();
        var tem = false;
        form.querySelectorAll('input[name^="bim_nota"], input[name^="bim_faltas"]').forEach(function (inp) {
            if ((inp.value || '').trim() !== '') tem = true;
        });
        if (!tem) {
            window.alert('Informe ao menos uma nota ou falta de um período.');
            return;
        }
        var modal = document.getElementById('veModalSenhaMeio');
        var erro = document.getElementById('veSenhaMeioErro');
        var input = document.getElementById('veSenhaMeioInput');
        if (erro) {
            erro.textContent = '';
            erro.classList.add('hidden');
        }
        if (input) input.value = '';
        if (modal) {
            modal.classList.remove('hidden');
            setTimeout(function () { if (input) input.focus(); }, 50);
        }
    });
})();
function veToggleBoletimAno(btn) {
    var id = btn.getAttribute('data-ve-boletim-toggle');
    var painel = id ? document.getElementById(id) : null;
    if (!painel) return;
    var aberto = !painel.classList.contains('hidden');
    painel.classList.toggle('hidden', aberto);
    btn.setAttribute('aria-expanded', aberto ? 'false' : 'true');
    var chev = btn.querySelector('.ve-boletim-chevron');
    if (chev) {
        chev.classList.toggle('fa-chevron-down', aberto);
        chev.classList.toggle('fa-chevron-up', !aberto);
    }
}
function veAddCompSimples() {
    var wrap = document.getElementById('ve-comps-simples');
    if (!wrap) return;
    var row = document.createElement('div');
    row.className = 'flex items-center gap-2';
    row.innerHTML = '<input name="comp_nome[]" placeholder="Ex.: Matemática" class="flex-1 min-w-0 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">'
        + '<input name="comp_nota[]" placeholder="0,0" class="w-24 shrink-0 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">'
        + '<input name="comp_ch[]" placeholder="h" inputmode="numeric" class="w-28 shrink-0 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">';
    wrap.appendChild(row);
}
function veAbaOrigem(nome) {
    document.querySelectorAll('[data-ve-origem-painel]').forEach(function (painel) {
        painel.classList.toggle('hidden', painel.getAttribute('data-ve-origem-painel') !== nome);
    });
    document.querySelectorAll('.ve-origem-pill').forEach(function (btn) {
        var ativa = btn.getAttribute('data-ve-origem') === nome;
        btn.classList.toggle('bg-primary', ativa);
        btn.classList.toggle('text-white', ativa);
        btn.classList.toggle('bg-gray-100', !ativa);
        btn.classList.toggle('text-gray-700', !ativa);
        btn.classList.toggle('hover:bg-gray-200', !ativa);
    });
}
function veNomeDoComponente(sel) {
    var row = sel.closest('tr');
    if (!row) return;
    var nome = row.querySelector('input[name^="bim_comp"]');
    var texto = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    if (!nome || sel.value === '0') return;
    if (nome.value === '' || nome.value === nome.getAttribute('data-ve-auto')) {
        nome.value = texto;
        nome.setAttribute('data-ve-auto', texto);
    }
}
function veReindexarBim() {
    var list = document.getElementById('ve-blocos-bim');
    var qtd = document.getElementById('ve-bim-bloco-qtd');
    if (!list || !qtd) return;
    list.querySelectorAll('.ve-bloco-bim').forEach(function (row, i) {
        row.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/\[(?:\d+|__I__)\]/, '[' + i + ']');
        });
    });
    qtd.value = String(list.querySelectorAll('.ve-bloco-bim').length);
}
function veRemoverLinhaBim(btn) {
    var row = btn.closest('tr');
    if (row) row.remove();
    veReindexarBim();
}
function veAddBlocoBim() {
    var tpl = document.getElementById('ve-tpl-bloco-bim');
    var list = document.getElementById('ve-blocos-bim');
    if (!tpl || !list) return;
    var i = list.querySelectorAll('.ve-bloco-bim').length;
    var html = tpl.innerHTML.replace(/__I__/g, String(i));
    var wrap = document.createElement('tbody');
    wrap.innerHTML = html.trim();
    var node = wrap.querySelector('tr');
    if (node) list.appendChild(node);
    veReindexarBim();
}
</script>
