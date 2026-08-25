<?php
$layout_config = $layout_config ?? [];
$escola_id = $escola_id ?? 0;
$csrf_token = $csrf_token ?? '';

$creditosHabilitado = ($layout_config['creditos_habilitado'] ?? '0') === '1';
$creditosLiberarEscolaComprar = ($layout_config['creditos_liberar_escola_comprar'] ?? '0') === '1';
$creditosExibirMenuCarteira = ($layout_config['creditos_exibir_menu_carteira'] ?? '1') === '1';
$creditosExibirMenuComprar = ($layout_config['creditos_exibir_menu_comprar'] ?? '1') === '1';
$creditosModoPoolEscola = ($layout_config['creditos_modo_pool_escola'] ?? '0') === '1';
$creditosAlunoPodeComprar = ($layout_config['creditos_aluno_pode_comprar'] ?? '0') === '1';
require_once __DIR__ . '/../../../../Core/CreditosDecimalHelper.php';
$creditosMensalAluno = \CreditosDecimalHelper::fromScalar($layout_config['creditos_mensal_aluno'] ?? 0, 0.0);
$creditosMensalProfessor = \CreditosDecimalHelper::fromScalar($layout_config['creditos_mensal_professor'] ?? 0, 0.0);
$creditosMensalAdmin = \CreditosDecimalHelper::fromScalar($layout_config['creditos_mensal_admin'] ?? 0, 0.0);
$creditosMensalEscola = \CreditosDecimalHelper::fromScalar($layout_config['creditos_mensal_escola'] ?? 0, 0.0);

$renderFlagToggle = static function (string $name, bool $enabled, bool $locked = false): void {
    ?>
    <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden text-xs font-medium creditos-flag-toggle shrink-0 <?= $locked ? 'opacity-60' : '' ?>">
        <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= $enabled ? '1' : '0' ?>" class="creditos-flag-value" <?= $locked ? 'data-locked="1"' : '' ?>>
        <button type="button" data-value="1" <?= $locked ? 'disabled' : '' ?> class="creditos-flag-btn px-3 py-1.5 <?= $enabled ? 'bg-blue-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' ?> <?= $locked ? 'cursor-not-allowed' : '' ?>">Habilitado</button>
        <button type="button" data-value="0" <?= $locked ? 'disabled' : '' ?> class="creditos-flag-btn px-3 py-1.5 border-l border-slate-300 <?= !$enabled ? 'bg-gray-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' ?> <?= $locked ? 'cursor-not-allowed' : '' ?>">Desativado</button>
    </div>
    <?php
};

$inputClass = 'w-full min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white';
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-4 sm:p-6">
    <div class="flex items-center gap-2 mb-2">
        <h3 class="text-lg font-semibold text-slate-900">Sistema de TudiCoins</h3>
        <button type="button" id="tudicoins-info-abrir"
                class="inline-flex items-center justify-center w-7 h-7 rounded-full text-blue-600 hover:bg-blue-50 hover:text-blue-700 transition-colors"
                aria-label="Como funcionam os TudiCoins" title="Como funcionam os TudiCoins">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </button>
    </div>
    <p class="text-sm text-slate-600 mb-6">
        Ative os TudiCoins nesta escola, escolha quem paga o uso da IA e defina a cota mensal.
        Clique no ícone de informação para ver o guia completo.
    </p>

    <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/creditos" id="form-creditos-flags">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="space-y-6">
            <div class="border border-slate-200 rounded-lg overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-sm font-semibold text-slate-800">Flags</div>
                <div class="px-4 divide-y divide-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700">Habilitar TudiCoins para esta escola</p>
                        </div>
                        <?php $renderFlagToggle('creditos_habilitado', $creditosHabilitado); ?>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700">Escola paga o consumo</p>
                            <p class="text-xs text-slate-500 mt-0.5">Quando ligado, o uso de alunos, professores e usuários admin sai do saldo da escola. Use “Minha Carteira” abaixo se quiser esconder o saldo do aluno.</p>
                        </div>
                        <?php $renderFlagToggle('creditos_modo_pool_escola', $creditosModoPoolEscola); ?>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700">Escola pode comprar TudiCoins</p>
                            <p class="text-xs text-slate-500 mt-0.5">Permite a escola comprar pacotes no painel administrativo. O saldo vai para a carteira da escola.</p>
                        </div>
                        <?php $renderFlagToggle('creditos_liberar_escola_comprar', $creditosLiberarEscolaComprar); ?>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700">Aluno pode comprar TudiCoins</p>
                            <p class="text-xs text-slate-500 mt-0.5">Permite o aluno comprar pacotes no EducaShop. O saldo vai para a carteira dele.</p>
                        </div>
                        <?php $renderFlagToggle('creditos_aluno_pode_comprar', $creditosAlunoPodeComprar); ?>
                    </div>
                </div>
            </div>

            <div class="border border-slate-200 rounded-lg overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-sm font-semibold text-slate-800">Exibir no app do aluno</div>
                <div class="px-4 py-2 border-b border-slate-100">
                    <p class="text-xs text-slate-600">Com TudiCoins ligados, escolha se o aluno vê o saldo. A loja EducaShop aparece sozinha se o aluno puder comprar.</p>
                </div>
                <div class="px-4 divide-y divide-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700">Minha Carteira (e saldo no topo)</p>
                            <p class="text-xs text-slate-500 mt-0.5">Desativado = o aluno não vê carteira nem saldo (útil quando a escola paga tudo).</p>
                        </div>
                        <?php $renderFlagToggle('creditos_exibir_menu_carteira', $creditosExibirMenuCarteira); ?>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700">EducaShop (comprar TudiCoins)</p>
                            <p class="text-xs text-slate-500 mt-0.5" id="educashop-flag-hint">
                                <?php if ($creditosAlunoPodeComprar): ?>
                                    Liberado automaticamente porque o aluno pode comprar pacotes.
                                <?php else: ?>
                                    Aparece se a escola liberou a compra no admin ou se o aluno pode comprar sozinho.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php $renderFlagToggle('creditos_exibir_menu_comprar', $creditosAlunoPodeComprar ? true : $creditosExibirMenuComprar, $creditosAlunoPodeComprar); ?>
                    </div>
                </div>
            </div>

            <div class="border border-slate-200 rounded-lg overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-sm font-semibold text-slate-800">Cota mensal</div>
                <div class="px-4 py-4">
                    <p class="text-xs text-slate-600 mb-4">
                        Quantidade creditada todo mês (renovação automática ou pelo botão Renovar).
                        TudiCoins comprados não são apagados. Conversão: 1 TudiCoin = R$ 0,20.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                        <?php
                        $camposCota = [
                            ['id' => 'creditos_mensal_aluno', 'label' => 'Aluno', 'valor' => $creditosMensalAluno, 'hint' => ''],
                            ['id' => 'creditos_mensal_professor', 'label' => 'Professor', 'valor' => $creditosMensalProfessor, 'hint' => ''],
                            ['id' => 'creditos_mensal_admin', 'label' => 'Usuário admin', 'valor' => $creditosMensalAdmin, 'hint' => 'Cota por administrador da escola.'],
                            ['id' => 'creditos_mensal_escola', 'label' => 'Escola', 'valor' => $creditosMensalEscola, 'hint' => 'Saldo da escola (quando ela paga o consumo).'],
                        ];
                        foreach ($camposCota as $campo):
                        ?>
                        <div class="min-w-0">
                            <label for="<?= $campo['id'] ?>" class="block text-sm font-medium text-slate-700 mb-1"><?= htmlspecialchars($campo['label']) ?></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-sm font-medium text-slate-500 pointer-events-none"><?= \CreditosDecimalHelper::PREFIXO ?></span>
                                <input type="text" id="<?= $campo['id'] ?>" name="<?= $campo['id'] ?>"
                                       value="<?= htmlspecialchars(\CreditosDecimalHelper::formatNumero($campo['valor'], 2)) ?>"
                                       inputmode="decimal" autocomplete="off"
                                       data-tudicoins-money
                                       class="<?= $inputClass ?> pl-12 tabular-nums">
                            </div>
                            <p class="text-xs text-slate-500 mt-1" data-tudicoins-brl-for="<?= $campo['id'] ?>">
                                ≈ <?= htmlspecialchars(\CreditosDecimalHelper::formatReaisFromTudicoins($campo['valor'])) ?>
                            </p>
                            <?php if ($campo['hint'] !== ''): ?>
                            <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($campo['hint']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                <button type="submit" form="form-creditos-renovar"
                        class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 font-medium text-sm w-full sm:w-auto"
                        onclick="return confirm('Isso renova a cota mensal de alunos, professores, usuários admin e da escola (conforme os valores salvos). TudiCoins comprados continuam no saldo. Deseja continuar?');">
                    Renovar todos
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm w-full sm:w-auto">Salvar</button>
            </div>
        </div>
    </form>

    <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/creditos/renovar" id="form-creditos-renovar" class="hidden">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    </form>

    <hr class="my-8 border-slate-200">

    <?php
    $catalogo_tabelas = $catalogo_tabelas ?? [];
    $catalogo_pacotes = $catalogo_pacotes ?? [];
    $vinculo_tabela_id = $vinculo_tabela_id ?? null;
    $vinculo_pacote_ids = $vinculo_pacote_ids ?? [];
    $catalogo_disponivel = !empty($catalogo_disponivel);
    $temVinculosAtuais = $vinculo_pacote_ids !== [];
    ?>

    <div id="catalogo-vinculos">
        <div class="border border-slate-200 rounded-lg overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-800">Preços e pacotes desta escola</h3>
                <p class="text-xs text-slate-600 mt-1">
                    Escolha a tabela de preço e os pacotes disponíveis.
                    Cadastro geral em
                    <a href="<?= URL ?>/master/creditos-catalogo/tabelas" class="text-blue-600 hover:underline font-medium">Precificação TudiCoins</a>.
                </p>
            </div>

            <?php if (!$catalogo_disponivel): ?>
                <div class="px-4 py-3 text-sm text-slate-600">
                    Catálogo Master indisponível. Rode a migration <code class="text-xs">051_creditos_catalogos_master.sql</code> no banco master e recarregue.
                </div>
            <?php else: ?>
            <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/creditos/catalogo-vinculos" id="form-catalogo-vinculos" class="px-4 py-4 space-y-5">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="confirm_clear_vinculos" id="confirm_clear_vinculos" value="0">

                <div>
                    <label for="tabela_custo_id" class="block text-sm font-medium text-slate-700 mb-1">Tabela de preço (opcional)</label>
                    <select id="tabela_custo_id" name="tabela_custo_id" class="<?= $inputClass ?> max-w-md">
                        <option value="">— Sem tabela de preço —</option>
                        <?php foreach ($catalogo_tabelas as $tab): ?>
                            <?php $tabId = (int) ($tab['id'] ?? 0); ?>
                            <option value="<?= $tabId ?>" <?= ($vinculo_tabela_id !== null && (int) $vinculo_tabela_id === $tabId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tab['nome'] ?? '') ?><?= empty($tab['ativo']) ? ' (inativa)' : '' ?><?= !empty($tab['padrao']) ? ' ★ padrão' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Define quanto cada ação de IA custa em TudiCoins nesta escola.</p>
                </div>

                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p class="text-sm font-semibold text-slate-800">Pacotes avulsos (catálogo)</p>
                        <a href="<?= URL ?>/master/creditos-catalogo/pacotes" class="text-xs text-blue-600 hover:underline">Gerenciar pacotes</a>
                    </div>
                    <?php if (empty($catalogo_pacotes)): ?>
                        <p class="text-sm text-slate-500">Nenhum pacote no catálogo.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($catalogo_pacotes as $cpk): ?>
                                <?php
                                $cpkId = (int) ($cpk['id'] ?? 0);
                                $marcadoPk = in_array($cpkId, $vinculo_pacote_ids, true);
                                $ativoPk = !empty($cpk['ativo']);
                                ?>
                                <label class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm <?= $ativoPk ? '' : 'opacity-60' ?>">
                                    <input type="checkbox" name="pacotes_catalogo[]" value="<?= $cpkId ?>" <?= $marcadoPk ? 'checked' : '' ?> class="rounded border-slate-300 mt-0.5 js-vinculo-pacote shrink-0" <?= (!$ativoPk && !$marcadoPk) ? 'disabled' : '' ?>>
                                    <span class="min-w-0">
                                        <span class="font-medium text-slate-800"><?= htmlspecialchars($cpk['nome'] ?? '') ?></span>
                                        <span class="block text-xs text-slate-500">
                                            <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromScalar($cpk['creditos'] ?? 0, 0.0))) ?> ·
                                            R$ <?= number_format(((int) ($cpk['valor_centavos'] ?? 0)) / 100, 2, ',', '.') ?>
                                            <?= $ativoPk ? '' : ' · inativo' ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">
                        Salvar vínculos e sincronizar
                    </button>
                    <p class="text-xs text-slate-500">Desmarcar um pacote desativa a cópia sincronizada na escola.</p>
                </div>
            </form>
            <script>
            (function() {
                var form = document.getElementById('form-catalogo-vinculos');
                if (!form) return;
                var tinhaVinculos = <?= $temVinculosAtuais ? 'true' : 'false' ?>;
                form.addEventListener('submit', function(e) {
                    var pacotes = form.querySelectorAll('.js-vinculo-pacote:checked');
                    var clearInput = document.getElementById('confirm_clear_vinculos');
                    if (clearInput) clearInput.value = '0';
                    if (tinhaVinculos && pacotes.length === 0) {
                        var ok = window.confirm('Nenhum pacote marcado. Isso remove os vínculos de pacotes desta escola e desativa as cópias sincronizadas. Continuar?');
                        if (!ok) {
                            e.preventDefault();
                            return;
                        }
                        if (clearInput) clearInput.value = '1';
                    }
                });
            })();
            </script>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        function setToggleVisual(group, value) {
            group.querySelector('.creditos-flag-value').value = value;
            group.querySelectorAll('.creditos-flag-btn').forEach(function(b) {
                var active = b.getAttribute('data-value') === value;
                b.classList.toggle('bg-blue-600', active && value === '1');
                b.classList.toggle('bg-gray-600', active && value === '0');
                b.classList.toggle('text-white', active);
                b.classList.toggle('bg-white', !active);
                b.classList.toggle('text-slate-500', !active);
                b.classList.toggle('hover:bg-slate-50', !active);
            });
        }

        function syncEducaShopFromAlunoCompra() {
            var alunoGroup = null;
            var shopGroup = null;
            document.querySelectorAll('.creditos-flag-toggle').forEach(function(group) {
                var input = group.querySelector('.creditos-flag-value');
                if (!input) return;
                if (input.name === 'creditos_aluno_pode_comprar') alunoGroup = group;
                if (input.name === 'creditos_exibir_menu_comprar') shopGroup = group;
            });
            if (!alunoGroup || !shopGroup) return;
            var alunoOn = alunoGroup.querySelector('.creditos-flag-value').value === '1';
            var shopInput = shopGroup.querySelector('.creditos-flag-value');
            var hint = document.getElementById('educashop-flag-hint');
            shopGroup.querySelectorAll('.creditos-flag-btn').forEach(function(b) {
                b.disabled = alunoOn;
            });
            shopGroup.classList.toggle('opacity-60', alunoOn);
            if (alunoOn) {
                setToggleVisual(shopGroup, '1');
                shopInput.setAttribute('data-locked', '1');
                if (hint) hint.textContent = 'Liberado automaticamente porque o aluno pode comprar pacotes.';
            } else {
                shopInput.removeAttribute('data-locked');
                if (hint) hint.textContent = 'Aparece se a escola liberou compra no admin ou se o aluno pode comprar sozinho.';
            }
        }

        document.querySelectorAll('.creditos-flag-toggle').forEach(function(group) {
            group.querySelectorAll('.creditos-flag-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.disabled) return;
                    setToggleVisual(group, btn.getAttribute('data-value'));
                    var input = group.querySelector('.creditos-flag-value');
                    if (input && input.name === 'creditos_aluno_pode_comprar') {
                        syncEducaShopFromAlunoCompra();
                    }
                });
            });
        });
        syncEducaShopFromAlunoCompra();
    })();
    </script>

    <script>
    (function() {
        var BRL = <?= json_encode(\CreditosDecimalHelper::BRL_POR_UNIDADE) ?>;

        function parseBr(str) {
            var s = String(str || '').replace(/[^\d,.-]/g, '').trim();
            if (s.indexOf(',') >= 0) {
                s = s.replace(/\./g, '').replace(',', '.');
            }
            var n = parseFloat(s);
            return isNaN(n) || n < 0 ? 0 : n;
        }

        function formatBr(n) {
            return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatReais(n) {
            return 'R$ ' + (n * BRL).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function updateBrl(input) {
            var el = document.querySelector('[data-tudicoins-brl-for="' + input.id + '"]');
            if (el) el.textContent = '≈ ' + formatReais(parseBr(input.value));
        }

        document.querySelectorAll('[data-tudicoins-money]').forEach(function(input) {
            input.addEventListener('blur', function() {
                input.value = formatBr(parseBr(input.value));
                updateBrl(input);
            });
            input.addEventListener('input', function() {
                updateBrl(input);
            });
        });

        var formFlags = document.getElementById('form-creditos-flags');
        if (formFlags) {
            formFlags.addEventListener('submit', function() {
                document.querySelectorAll('[data-tudicoins-money]').forEach(function(input) {
                    input.value = formatBr(parseBr(input.value));
                });
            });
        }
    })();
    </script>
</div>

<div id="tudicoins-info-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4" aria-hidden="true">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="tudicoins-info-titulo">
        <div class="sticky top-0 bg-white border-b border-slate-100 px-5 py-4 flex items-start justify-between gap-3">
            <div>
                <h4 id="tudicoins-info-titulo" class="text-lg font-semibold text-slate-900">Como funcionam os TudiCoins</h4>
                <p class="text-sm text-slate-500 mt-0.5">Guia rápido para configurar esta escola</p>
            </div>
            <button type="button" id="tudicoins-info-fechar" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="px-5 py-4 space-y-5 text-sm text-slate-700 leading-relaxed">
            <section>
                <h5 class="font-semibold text-slate-900 mb-1">O que são TudiCoins?</h5>
                <p>São créditos usados quando alguém usa recursos de inteligência artificial na plataforma — por exemplo chat, exercícios gerados, flashcards ou correção de redação.</p>
            </section>
            <section>
                <h5 class="font-semibold text-slate-900 mb-1">Ligar ou desligar</h5>
                <p>Com TudiCoins <strong>desligados</strong>, as funções que dependem só de IA ficam indisponíveis. Em áreas mistas (como Jornada), somem apenas os botões de IA. Com TudiCoins <strong>ligados</strong>, o uso passa a consumir saldo conforme a tabela de preço desta escola.</p>
            </section>
            <section>
                <h5 class="font-semibold text-slate-900 mb-1">Quem paga o consumo?</h5>
                <ul class="list-disc list-inside space-y-1.5 text-slate-600">
                    <li><strong>Escola paga o consumo</strong> — o saldo sai da carteira da escola. Ideal quando a instituição cobre a IA para todos.</li>
                    <li><strong>Desligado</strong> — cada aluno, professor ou usuário admin gasta do próprio saldo.</li>
                </ul>
            </section>
            <section>
                <h5 class="font-semibold text-slate-900 mb-1">Compras</h5>
                <ul class="list-disc list-inside space-y-1.5 text-slate-600">
                    <li><strong>Escola pode comprar</strong> — a administração da escola compra pacotes; o crédito vai para a carteira da escola.</li>
                    <li><strong>Aluno pode comprar</strong> — o aluno compra no EducaShop; o crédito vai para a carteira dele.</li>
                </ul>
            </section>
            <section>
                <h5 class="font-semibold text-slate-900 mb-1">O que o aluno vê</h5>
                <ul class="list-disc list-inside space-y-1.5 text-slate-600">
                    <li><strong>Minha Carteira</strong> — mostra saldo e extrato. Desative se a escola paga tudo e você não quer que o aluno veja números.</li>
                    <li><strong>EducaShop</strong> — loja de pacotes. Se o aluno puder comprar, a loja aparece automaticamente.</li>
                </ul>
            </section>
            <section>
                <h5 class="font-semibold text-slate-900 mb-1">Cota mensal</h5>
                <p>É a quantidade creditada todo mês para alunos, professores, usuários admin e para a própria instituição. Ao renovar, só a cota mensal é refeita — TudiCoins comprados continuam no saldo.</p>
            </section>
            <section>
                <h5 class="font-semibold text-slate-900 mb-1">Preços e pacotes</h5>
                <p>A <strong>tabela de preço</strong> diz quanto cada ação de IA custa. Os <strong>pacotes</strong> são as opções de compra disponíveis nesta escola. Cadastre e edite no menu Precificação TudiCoins e depois vincule aqui.</p>
            </section>
        </div>
        <div class="sticky bottom-0 bg-white border-t border-slate-100 px-5 py-3 flex justify-end">
            <button type="button" id="tudicoins-info-ok" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Entendi</button>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('tudicoins-info-modal');
    var abrir = document.getElementById('tudicoins-info-abrir');
    if (!modal || !abrir) return;

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    abrir.addEventListener('click', openModal);
    document.getElementById('tudicoins-info-fechar')?.addEventListener('click', closeModal);
    document.getElementById('tudicoins-info-ok')?.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
})();
</script>
