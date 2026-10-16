<?php
$boletim = is_array($boletim ?? null) ? $boletim : [];
$periodos = is_array($periodos ?? null) ? $periodos : [];
$eventosModelo = is_array($eventos_modelo ?? null) ? $eventos_modelo : [];
$modelosPorBimestre = is_array($modelos_por_bimestre ?? null) ? $modelos_por_bimestre : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$usouCalendario = !empty($usou_calendario);
$csrf_token = $csrf_token ?? '';
$boletimId = (int) ($boletim['id'] ?? 0);
$temPeriodo = $periodos !== [];
$temEvento = $eventosModelo !== [];
$regime = is_array($regime ?? null) ? $regime : \App\Core\PeriodoLetivo::doAno($anoLetivo);
$rotulo = function_exists('mb_strtolower')
    ? mb_strtolower((string) ($regime['rotulo_campo'] ?? 'período'), 'UTF-8')
    : strtolower((string) ($regime['rotulo_campo'] ?? 'período'));
$rotuloPlural = function_exists('mb_strtolower')
    ? mb_strtolower((string) ($regime['rotulo_campo_plural'] ?? 'períodos'), 'UTF-8')
    : strtolower((string) ($regime['rotulo_campo_plural'] ?? 'períodos'));
$qtdRegime = (int) ($regime['quantidade'] ?? count($periodos));
$primeiro = is_array($primeiro_periodo ?? null) ? $primeiro_periodo : null;
$numeroPrimeiro = (int) ($primeiro['bimestre'] ?? 1);
if ($numeroPrimeiro < 1) {
    $numeroPrimeiro = 1;
}
$nomePrimeiro = trim((string) ($primeiro['nome'] ?? ''));
if ($nomePrimeiro === '') {
    $nomePrimeiro = \App\Core\PeriodoLetivo::rotulo($anoLetivo, $numeroPrimeiro) ?: ('1º ' . $rotulo);
}
$avisosDatas = is_array($avisos_datas ?? null) ? $avisos_datas : [];
$queryAssistente = [
    'boletim_id' => $boletimId,
    'ano_letivo' => $anoLetivo,
    'bimestre' => $numeroPrimeiro,
    'voltar' => 'gerar-avaliacoes',
];
$urlAssistente = URL . '/admin/boletim-configuracao/assistente?' . http_build_query($queryAssistente);
$urlContinuar = $urlAssistente . '&continuar=1';

$page_header_title = 'Gerar avaliações do ano';
$page_header_subtitle = 'Cria as configurações dos ' . $rotuloPlural . ' a partir do calendário letivo (tipo Avaliação) ou dos períodos padrão do ano, limitados ao ano letivo.';
$page_header_back_url = URL . '/admin/boletins';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

$origemResumo = $usouCalendario ? 'Datas do calendário letivo' : ('Períodos padrão (' . $qtdRegime . ' ' . $rotuloPlural . ')');
?>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <div class="mb-6">
        <p class="text-sm text-gray-700">
            Modelo: <strong><?= htmlspecialchars((string) ($boletim['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            · Ano <?= $anoLetivo ?>
            · <?= htmlspecialchars($origemResumo, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php if ($temEvento): ?>
        <p class="text-sm text-gray-700 mt-2">
            Escolha os períodos que devem receber uma cópia da configuração existente.
        </p>
        <p class="text-xs text-gray-500 mt-1">
            Períodos já configurados ficam desmarcados. Ao substituir, a configuração vigente é desativada e o histórico de notas deixa de ficar vigente. O histórico permanece gravado.
        </p>
        <?php endif; ?>
    </div>

    <?php foreach ($avisosDatas as $avisoData): ?>
        <?php $avisoData = trim((string) $avisoData); ?>
        <?php if ($avisoData === '') { continue; } ?>
        <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-slate-800 text-sm mb-4">
            <?= htmlspecialchars($avisoData, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$temEvento): ?>
        <div class="p-5 rounded-xl border border-amber-200 bg-amber-50 mb-6">
            <h3 class="text-base font-semibold text-amber-950">Configure o primeiro <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="text-sm text-amber-950 mt-2">
                Este modelo ainda não tem uma avaliação configurada. Defina os tipos de nota e a fórmula do primeiro período. Depois, você poderá aproveitar essa configuração nos demais períodos do ano.
            </p>
            <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <a href="<?= htmlspecialchars($urlAssistente, ENT_QUOTES, 'UTF-8') ?>"
                   id="btn-configurar-primeiro"
                   data-url-continuar="<?= htmlspecialchars($urlContinuar, ENT_QUOTES, 'UTF-8') ?>"
                   data-boletim-id="<?= $boletimId ?>"
                   data-ano="<?= $anoLetivo ?>"
                   data-bimestre="<?= $numeroPrimeiro ?>"
                   class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                    Configurar primeiro <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <a href="<?= htmlspecialchars($urlContinuar, ENT_QUOTES, 'UTF-8') ?>"
                   id="btn-continuar-rascunho"
                   class="hidden inline-flex items-center justify-center px-4 py-2.5 border border-amber-300 rounded-lg text-sm font-semibold text-amber-950 bg-white hover:bg-amber-100">
                    Continuar a configuração existente
                </a>
            </div>
            <p id="txt-continuar-rascunho" class="hidden text-sm text-amber-950 mt-3">
                Há uma configuração de <?= htmlspecialchars($nomePrimeiro, ENT_QUOTES, 'UTF-8') ?> iniciada neste navegador e ainda não salva. Continuar abre esse rascunho. Configurar de novo não grava outro evento até você salvar.
            </p>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= URL ?>/admin/boletins/<?= $boletimId ?>/gerar-avaliacoes" class="space-y-4" id="form-gerar-avaliacoes"
          data-rotulo="<?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>"
          data-tem-evento="<?= $temEvento ? '1' : '0' ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">

        <div class="overflow-x-auto mb-2">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-16">Criar</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Início</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fim</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origem</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Configuração de origem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if ($periodos === []): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Nenhum período calculado para este ano.</td>
                    </tr>
                    <?php else: foreach ($periodos as $p):
                        $bim = (int) ($p['bimestre'] ?? 0);
                        $jaExiste = !empty($p['ja_existe']);
                        $boletinsGerados = (int) ($p['boletins_gerados'] ?? 0);
                        $modeloSel = (int) ($modelosPorBimestre[$bim] ?? 0);
                        $rowId = 'bim-gerar-' . $bim;
                        $checkedPadrao = $temEvento && !$jaExiste;
                        $recortado = !empty($p['recortado']);
                        $nomePeriodo = (string) ($p['nome'] ?? '');
                    ?>
                    <tr class="<?= $jaExiste ? 'bg-gray-50/40' : '' ?>" data-ja-existe="<?= $jaExiste ? '1' : '0' ?>">
                        <td class="px-4 py-3">
                            <input
                                type="checkbox"
                                name="bimestres[]"
                                value="<?= $bim ?>"
                                id="<?= htmlspecialchars($rowId, ENT_QUOTES, 'UTF-8') ?>"
                                class="js-bim-gerar rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                data-existe="<?= $jaExiste ? '1' : '0' ?>"
                                aria-label="Incluir <?= htmlspecialchars($nomePeriodo, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $checkedPadrao ? 'checked' : '' ?>
                                <?= $temEvento ? '' : 'disabled' ?>
                            >
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            <?php if ($temEvento): ?>
                                <label for="<?= htmlspecialchars($rowId, ENT_QUOTES, 'UTF-8') ?>" class="cursor-pointer">
                                    <?= htmlspecialchars($nomePeriodo, ENT_QUOTES, 'UTF-8') ?>
                                </label>
                            <?php else: ?>
                                <?= htmlspecialchars($nomePeriodo, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['fim'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            <?= (($p['origem'] ?? '') === 'calendario') ? 'Calendário' : 'Padrão' ?>
                            <?php if ($recortado): ?>
                                <p class="text-xs text-gray-500 mt-1">Limitado ao ano letivo</p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (!$temEvento): ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-900 border border-amber-200">Aguardando configuração</span>
                            <?php elseif ($jaExiste): ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 js-status-existe">Já configurado</span>
                                <span class="hidden inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-900 js-status-substituir">Será substituído</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 js-status-criar">Será criado</span>
                                <span class="hidden inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 js-status-pular">Ainda não configurado</span>
                            <?php endif; ?>
                            <?php if ($boletinsGerados > 0): ?>
                                <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-800 border border-indigo-200">Possui boletins gerados</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 min-w-[18rem]">
                            <?php if (!$temEvento): ?>
                                <span class="text-sm text-gray-600">Aguardando a configuração do primeiro <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <label class="sr-only" for="modelo-bim-<?= $bim ?>">Configuração de origem de <?= htmlspecialchars($nomePeriodo, ENT_QUOTES, 'UTF-8') ?></label>
                                <select
                                    name="modelo_por_bimestre[<?= $bim ?>]"
                                    id="modelo-bim-<?= $bim ?>"
                                    class="js-modelo-select w-full max-w-xl px-3 py-2 border border-gray-300 rounded-lg bg-white text-sm"
                                    data-bim="<?= $bim ?>"
                                    <?= $checkedPadrao ? 'required' : 'disabled' ?>
                                >
                                    <?php foreach ($eventosModelo as $ev):
                                        $evId = (int) ($ev['id'] ?? 0);
                                        $label = (string) ($ev['nome_exibicao'] ?? $ev['nome'] ?? ('#' . $evId));
                                    ?>
                                        <option value="<?= $evId ?>" <?= $modeloSel === $evId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($jaExiste): ?>
                                    <p class="text-xs text-gray-600 mt-1 js-hint-substituir hidden">A configuração vigente deste período será desativada. O histórico de notas deixa de ficar vigente.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="space-y-2">
            <p id="explicacao-criar-periodos" class="text-sm text-gray-700">
                Esta etapa cria as configurações das avaliações de cada período. Os boletins dos alunos serão gerados depois, com as notas lançadas.
            </p>
            <p id="motivo-criar-periodos" class="text-sm text-gray-600 <?= ($temEvento && $temPeriodo) ? 'hidden' : '' ?>">
                <?php if (!$temEvento): ?>
                    A criação fica indisponível até existir a configuração do primeiro <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>.
                <?php elseif (!$temPeriodo): ?>
                    Não há períodos para criar neste ano.
                <?php endif; ?>
            </p>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <button
                    type="submit"
                    id="btn-gerar-avaliacoes"
                    class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                    aria-describedby="explicacao-criar-periodos motivo-criar-periodos"
                    <?= ($temEvento && $temPeriodo) ? '' : 'disabled' ?>
                >
                    Criar períodos selecionados
                </button>
                <a href="<?= URL ?>/admin/boletins" class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">Cancelar</a>
            </div>
        </div>
    </form>
</div>
<script>
(function () {
    var btnPrimeiro = document.getElementById('btn-configurar-primeiro');
    var btnContinuar = document.getElementById('btn-continuar-rascunho');
    var txtContinuar = document.getElementById('txt-continuar-rascunho');
    if (btnPrimeiro && btnContinuar) {
        try {
            var bruto = sessionStorage.getItem('boletim_assistente_rascunho');
            var rascunho = bruto ? JSON.parse(bruto) : null;
            var compativel = rascunho && typeof rascunho === 'object'
                && Number(rascunho.boletim_id || 0) === Number(btnPrimeiro.getAttribute('data-boletim-id') || 0)
                && Number(rascunho.ano_letivo || 0) === Number(btnPrimeiro.getAttribute('data-ano') || 0)
                && Number(rascunho.bimestre || 0) === Number(btnPrimeiro.getAttribute('data-bimestre') || 0)
                && Array.isArray(rascunho.componentes) && rascunho.componentes.length > 0;
            if (compativel && document.querySelector('.bg-green-100')) {
                sessionStorage.removeItem('boletim_assistente_rascunho');
                compativel = false;
            }
            if (compativel) {
                btnContinuar.classList.remove('hidden');
                if (txtContinuar) txtContinuar.classList.remove('hidden');
                btnPrimeiro.addEventListener('click', function (ev) {
                    if (!window.confirm('Já existe uma configuração iniciada deste período neste navegador. Começar de novo descarta esse rascunho da tela. A configuração salva, se houver, não é apagada.')) {
                        ev.preventDefault();
                        return;
                    }
                    sessionStorage.removeItem('boletim_assistente_rascunho');
                });
            }
        } catch (e) {}
    }

    var form = document.getElementById('form-gerar-avaliacoes');
    if (!form || form.getAttribute('data-tem-evento') !== '1') return;
    var btn = document.getElementById('btn-gerar-avaliacoes');
    var motivo = document.getElementById('motivo-criar-periodos');
    var rotulo = form.getAttribute('data-rotulo') || 'período';

    function syncRow(cb) {
        var tr = cb.closest('tr');
        if (!tr) return;
        var on = !!cb.checked;
        var existe = cb.getAttribute('data-existe') === '1';
        var criar = tr.querySelector('.js-status-criar');
        var pular = tr.querySelector('.js-status-pular');
        var existeEl = tr.querySelector('.js-status-existe');
        var subst = tr.querySelector('.js-status-substituir');
        var hint = tr.querySelector('.js-hint-substituir');
        var sel = tr.querySelector('.js-modelo-select');
        if (existe) {
            if (existeEl) existeEl.classList.toggle('hidden', on);
            if (subst) subst.classList.toggle('hidden', !on);
            if (hint) hint.classList.toggle('hidden', !on);
        } else {
            if (criar) criar.classList.toggle('hidden', !on);
            if (pular) pular.classList.toggle('hidden', on);
        }
        if (sel) {
            sel.disabled = !on;
            sel.required = on;
        }
    }

    function syncAll() {
        var checks = form.querySelectorAll('.js-bim-gerar');
        var novos = 0;
        var subst = 0;
        checks.forEach(function (cb) {
            syncRow(cb);
            if (!cb.checked) return;
            if (cb.getAttribute('data-existe') === '1') subst += 1;
            else novos += 1;
        });
        var any = (novos + subst) > 0;
        if (btn) {
            btn.disabled = !any;
            if (novos > 0 && subst > 0) btn.textContent = 'Criar e substituir períodos selecionados';
            else if (subst > 0) btn.textContent = 'Substituir períodos selecionados';
            else btn.textContent = 'Criar períodos selecionados';
        }
        if (motivo) {
            motivo.classList.toggle('hidden', any);
            if (!any) {
                motivo.textContent = 'Marque ao menos um ' + rotulo + ' para criar a configuração.';
            }
        }
    }

    form.querySelectorAll('.js-bim-gerar').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (cb.checked && cb.getAttribute('data-existe') === '1') {
                var ok = window.confirm('Substituir a configuração vigente deste ' + rotulo + '? O evento atual é desativado e deixa de valer. O histórico de notas permanece gravado, mas não fica vigente.');
                if (!ok) {
                    cb.checked = false;
                }
            }
            syncAll();
        });
    });

    form.addEventListener('submit', function (ev) {
        var subst = form.querySelectorAll('.js-bim-gerar[data-existe="1"]:checked');
        if (subst.length > 0) {
            var ok = window.confirm('Há ' + subst.length + ' período(s) marcado(s) para substituição. A configuração vigente de cada um será desativada e o histórico deixa de ficar vigente. Confirma?');
            if (!ok) {
                ev.preventDefault();
            }
        }
    });

    syncAll();
})();
</script>
