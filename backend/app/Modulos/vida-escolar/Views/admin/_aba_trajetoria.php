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
?>
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
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-start justify-between gap-3 flex-wrap mb-1">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Trajetória escolar</h3>
            <p class="text-sm text-gray-500 mt-1">Anos internos (esta escola) e externos (transferência). O histórico oficial lê daqui. Clique em <strong>Boletim</strong> para ver as notas daquele ano.</p>
        </div>
        <?php if (!empty($admin_permissions['vida_escolar']['cadastrar'])): ?>
        <button type="button" onclick="veAbrirLancarEscola()"
                class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold shrink-0">
            <i class="fa-solid fa-plus mr-1.5"></i>Lançar Escola
        </button>
        <?php endif; ?>
    </div>
    <?php if ($traj === []): ?>
        <p class="text-sm text-gray-500 mt-4">Nenhum ano registrado. Homologue o boletim ou clique em <strong>Lançar Escola</strong>.</p>
    <?php else: ?>
        <div class="overflow-x-auto border border-gray-200 rounded-lg mt-4">
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
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Notas do ano em curso (outra escola)</h3>
    <p class="text-sm text-gray-500 mb-4">Use isto só se o aluno chegou no meio do ano e já tem bimestres na escola de origem. Esses lançamentos entram no <strong>boletim</strong>. Anos já fechados (5º, 6º, 7º…) vão em <strong>Lançar Escola</strong>, no quadro da trajetória.</p>
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
    <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900 mb-5">
        Histórico de anos anteriores não se preenche aqui.
        <button type="button" class="font-semibold underline underline-offset-2 hover:text-blue-700" onclick="veAbrirLancarEscola()">Abrir Lançar Escola</button>
    </div>
    <form method="post" action="<?= $base ?>/importar" class="space-y-5" id="form-importar-transferencia">
        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
        <input type="hidden" name="anos_qtd" value="0">
        <div class="rounded-xl border border-gray-200 p-4">
            <h4 class="text-sm font-semibold text-gray-900 mb-3">Escola de origem</h4>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Escola <span class="text-red-500">*</span></label>
                    <input name="escola_origem" required placeholder="Nome da escola" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data da transferência</label>
                    <input type="date" name="data_transferencia" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Documento anexado</label>
                    <select name="documento_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="0">Nenhum</option>
                        <?php foreach ($documentos as $d): ?>
                            <?php if (!is_array($d)) { continue; } ?>
                            <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $esc(($d['tipo'] ?? 'documento') . ' #' . (int) ($d['id'] ?? 0)) ?></option>
                        <?php endforeach; ?>
                    </select>
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
        $usouMatrizFicha = $componentesBim !== [];
        if (!$usouMatrizFicha) {
            foreach ($materias as $m) {
                $componentesBim[] = ['id' => (int) ($m['id'] ?? 0), 'nome' => (string) ($m['nome'] ?? '')];
            }
        }
        $nBlocos = count($componentesBim);
        ?>
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h4 class="text-sm font-semibold text-gray-900">Bimestres já cursados</h4>
                <p class="text-xs text-gray-500 mt-0.5"><?= $usouMatrizFicha ? 'Matérias da turma. Preencha só os bimestres que constam no documento da outra escola.' : 'Escolha a matéria e preencha nota e faltas de cada bimestre.' ?></p>
            </div>
            <button type="button" class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50" onclick="veAddBlocoBim()">
                <i class="fa-solid fa-plus mr-1"></i>Adicionar matéria
            </button>
        </div>
        <input type="hidden" name="bim_bloco_qtd" id="ve-bim-bloco-qtd" value="<?= (int) $nBlocos ?>">
        <div id="ve-blocos-bim" class="space-y-3">
            <?php foreach ($componentesBim as $i => $compB): ?>
            <div class="border border-gray-100 rounded-lg p-3 ve-bloco-bim">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
                    <?php if ($usouMatrizFicha && (int) ($compB['id'] ?? 0) > 0): ?>
                        <p class="text-sm font-medium text-gray-900 px-1 py-2"><?= $esc($compB['nome'] ?? '') ?></p>
                        <input type="hidden" name="bim_materia_id[<?= $i ?>]" value="<?= (int) $compB['id'] ?>">
                        <input type="hidden" name="bim_comp[<?= $i ?>]" value="<?= $esc($compB['nome'] ?? '') ?>">
                    <?php else: ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Matéria da turma</label>
                            <select name="bim_materia_id[<?= $i ?>]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="0">Selecione a matéria</option>
                            <?php foreach ($materias as $m): ?>
                                <option value="<?= (int) $m['id'] ?>" <?= (int) ($m['id'] ?? 0) === (int) ($compB['id'] ?? 0) ? 'selected' : '' ?>><?= $esc($m['nome'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome no histórico</label>
                            <input name="bim_comp[<?= $i ?>]" value="<?= $esc($compB['nome'] ?? '') ?>" placeholder="Como aparece no documento" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <?php for ($p = 1; $p <= 4; $p++): ?>
                    <div class="rounded-lg border border-gray-100 bg-slate-50 p-2">
                        <p class="text-xs font-medium text-gray-700 mb-2"><?= $p ?>º bimestre</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Nota</label>
                                <input name="bim_nota[<?= $i ?>][<?= $p ?>]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Faltas</label>
                                <input name="bim_faltas[<?= $i ?>][<?= $p ?>]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white">
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <template id="ve-tpl-bloco-bim">
            <div class="border border-gray-100 rounded-lg p-3 ve-bloco-bim">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Matéria da turma</label>
                        <select name="bim_materia_id[__I__]" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="0">Selecione a matéria</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"><?= $esc($m['nome'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome no histórico</label>
                        <input name="bim_comp[__I__]" placeholder="Como aparece no documento" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <?php for ($p = 1; $p <= 4; $p++): ?>
                    <div class="rounded-lg border border-gray-100 bg-slate-50 p-2">
                        <p class="text-xs font-medium text-gray-700 mb-2"><?= $p ?>º bimestre</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Nota</label>
                                <input name="bim_nota[__I__][<?= $p ?>]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-0.5">Faltas</label>
                                <input name="bim_faltas[__I__][<?= $p ?>]" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm bg-white">
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </template>
        <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Salvar rascunho das notas</button>
    </form>
    <?php endif; ?>
</div>
<script>
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
    var drawer = document.getElementById('veLancarEscolaDrawer');
    if (!drawer || drawer.getAttribute('aria-hidden') === 'true') return;
    veFecharLancarEscola();
});
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
function veAddBlocoBim() {
    var tpl = document.getElementById('ve-tpl-bloco-bim');
    var list = document.getElementById('ve-blocos-bim');
    var qtd = document.getElementById('ve-bim-bloco-qtd');
    if (!tpl || !list || !qtd) return;
    var i = list.querySelectorAll('.ve-bloco-bim').length;
    var html = tpl.innerHTML.replace(/__I__/g, String(i));
    var wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    var node = wrap.firstElementChild;
    if (node) list.appendChild(node);
    qtd.value = String(list.querySelectorAll('.ve-bloco-bim').length);
}
</script>
