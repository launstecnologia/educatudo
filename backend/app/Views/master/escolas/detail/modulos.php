<?php
$layout_config = $layout_config ?? [];
$modulos_catalogo = $modulos_catalogo ?? [];
$release_channels = $release_channels ?? ['stable', 'canary'];
$release_catalog = $release_catalog ?? [];
$escola_id = $escola_id ?? 0;
$release_channel = strtolower((string)($layout_config['release_channel'] ?? 'stable'));
if (!in_array($release_channel, $release_channels, true)) {
    $release_channel = 'stable';
}
$release_version = (string)($layout_config['release_version'] ?? '');
$release_flags = (string)($layout_config['release_flags'] ?? '');
$csrf_token = $csrf_token ?? '';
require_once __DIR__ . '/../../../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../../../Core/ModuloCatalogo.php';
$tudicoinsOn = ($layout_config['creditos_habilitado'] ?? '0') === '1';
$modulosExigemTudiCoins = array_fill_keys(\CreditosModuleRegistry::getFeatureModulesQueExigemTudiCoins(), true);

$renderModToggle = function (string $key, string $cur, bool $lockedOff = false) {
    $enabled = !$lockedOff && $cur !== '0';
    $lockAttr = $lockedOff ? 'data-tudicoins-locked="1"' : '';
    ?>
    <div class="inline-flex rounded-lg border border-slate-300 overflow-hidden text-xs font-medium mod-toggle <?= $lockedOff ? 'opacity-60' : '' ?>" <?= $lockAttr ?>>
        <input type="hidden" name="modules[<?= htmlspecialchars($key) ?>]" value="<?= $enabled ? '1' : '0' ?>" class="mod-toggle-value">
        <button type="button" data-value="1" <?= $lockedOff ? 'disabled' : '' ?> class="mod-toggle-btn px-3 py-1.5 <?= $enabled ? 'bg-blue-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' ?> <?= $lockedOff ? 'cursor-not-allowed' : '' ?>">Habilitado</button>
        <button type="button" data-value="0" class="mod-toggle-btn px-3 py-1.5 border-l border-slate-300 <?= !$enabled ? 'bg-gray-600 text-white' : 'bg-white text-slate-500 hover:bg-slate-50' ?>">Desativado</button>
    </div>
    <?php
};
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-2">Módulos do sistema</h3>
    <p class="text-sm text-slate-600 mb-4">Desligar um módulo remove o acesso de aluno, professor, pais e admin — inclusive pela URL.</p>
    <?php if (!$tudicoinsOn): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        TudiCoins está desligado nesta escola. Módulos 100% IA (FlashCard, Tudinha, Exercícios por IA, Slides, Agente de IA…) ficam bloqueados — habilite em <a class="underline font-medium" href="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/creditos">TudiCoins</a>.
    </div>
    <?php endif; ?>

    <form method="post" id="form-modulos" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/modulos">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="confirm_senha" id="modulos-confirm-senha" value="">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button type="button" class="js-mod-batch px-3 py-1.5 rounded-lg text-sm bg-green-100 text-green-800 hover:bg-green-200" data-value="1">Habilitar tudo</button>
            <button type="button" class="js-mod-batch px-3 py-1.5 rounded-lg text-sm bg-amber-100 text-amber-800 hover:bg-amber-200" data-value="0">Desativar tudo</button>
            <input type="search" id="modulos-busca" placeholder="Buscar módulo…" class="ml-auto rounded-lg border border-slate-300 text-sm px-3 py-1.5 w-full md:w-64 focus:ring-blue-500 focus:border-blue-500" autocomplete="off">
        </div>

        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            <strong>Cadastro de alunos:</strong> use <em>Cadastro simples de aluno</em> para escolas que só precisam liberar acesso (ex.: só recursos de IA).
            Ative <em>Processo de Matrícula</em> quando a secretaria for operar contrato, assinatura e enturmação.
        </div>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-6">
            <div class="px-2 divide-y divide-gray-100" id="modulos-lista">
                <?php foreach ($modulos_catalogo as $mod): ?>
                <?php
                    $chave = (string) ($mod['chave'] ?? '');
                    $nome = (string) ($mod['nome'] ?? $chave);
                    $aliases = is_array($mod['aliases'] ?? null) ? $mod['aliases'] : [];
                    $busca = mb_strtolower(trim($nome . ' ' . implode(' ', array_map('strval', $aliases))));
                    $featureKeys = is_array($mod['feature_keys'] ?? null) ? $mod['feature_keys'] : [];
                    $firstKey = (string) ($featureKeys[0] ?? '');
                    $defaultMod = $firstKey !== '' ? ModuloCatalogo::valorPadrao($mod) : '1';
                    $cur = $firstKey !== '' ? ($layout_config['module_' . $firstKey] ?? $defaultMod) : $defaultMod;
                    $locked = false;
                    if (!$tudicoinsOn) {
                        foreach ($featureKeys as $bk) {
                            if (isset($modulosExigemTudiCoins[(string) $bk])) {
                                $locked = true;
                                break;
                            }
                        }
                    }
                ?>
                <div class="flex items-center justify-between gap-4 py-2.5 px-2 js-modulo-row" data-nome="<?= htmlspecialchars($busca) ?>">
                    <span class="text-sm text-slate-700">
                        <?= htmlspecialchars($nome) ?>
                        <?php if ($locked): ?><span class="text-xs text-amber-700 ml-1">(exige TudiCoins)</span><?php endif; ?>
                    </span>
                    <?php $renderModToggle($chave, $cur, $locked); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="border border-slate-200 rounded-lg overflow-hidden mb-6">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-sm font-semibold text-slate-800">Release por escola (canário)</div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Canal</label>
                    <select name="release_channel" class="rounded border-slate-300 text-sm w-full focus:ring-blue-500 focus:border-blue-500">
                        <option value="stable" <?= $release_channel === 'stable' ? 'selected' : '' ?>>stable (padrão)</option>
                        <option value="canary" <?= $release_channel === 'canary' ? 'selected' : '' ?>>canary (piloto)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Versão alvo</label>
                    <select name="release_version_select" class="rounded border-slate-300 text-sm w-full focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione uma versão</option>
                        <?php
                        $hasCurrentInCatalog = false;
                        foreach ($release_catalog as $rv):
                            $val = (string)($rv['value'] ?? '');
                            if ($val === '') continue;
                            if ($val === $release_version) $hasCurrentInCatalog = true;
                            $label = (string)($rv['label'] ?? $val);
                            $commit = trim((string)($rv['commit'] ?? ''));
                            $txt = $commit !== '' ? ($label . ' (' . $commit . ')') : $label;
                        ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $val === $release_version ? 'selected' : '' ?>><?= htmlspecialchars($txt) ?></option>
                        <?php endforeach; ?>
                        <?php if ($release_version !== '' && !$hasCurrentInCatalog): ?>
                            <option value="<?= htmlspecialchars($release_version) ?>" selected><?= htmlspecialchars($release_version . ' (salva)') ?></option>
                        <?php endif; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Escolha a versão pelo nome/commit e salve.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Flags (csv)</label>
                    <input type="text" name="release_flags" value="<?= htmlspecialchars($release_flags) ?>" placeholder="jornada_alunos_v2,redacao_status_sync" class="rounded border-slate-300 text-sm w-full focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-slate-700 mb-1">Versão manual (opcional)</label>
                    <input type="text" name="release_version_manual" value="" placeholder="Opcional: sobrescrever versão escolhida" class="rounded border-slate-300 text-sm w-full focus:ring-blue-500 focus:border-blue-500">
                </div>
                <p class="md:col-span-3 text-xs text-slate-500">Use este bloco para liberar funcionalidades em apenas uma escola, sem afetar as demais. Catálogo em <code>config/release_versions.php</code>.</p>
            </div>
        </div>

        <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar</button>
    </form>
</div>

<div id="modulos-senha-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm" role="dialog" aria-modal="true" aria-labelledby="modulos-senha-titulo">
        <h4 id="modulos-senha-titulo" class="text-base font-semibold text-slate-900 mb-1">Confirme sua senha</h4>
        <p class="text-sm text-slate-500 mb-4">Digite a senha do seu login no Painel Master (a mesma de <span class="font-medium text-slate-700">/master</span>), não a senha da escola.</p>
        <input type="password" id="modulos-senha-input" autocomplete="current-password"
               class="w-full px-3 py-2 border border-slate-300 rounded-lg mb-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Senha do Admin Master">
        <p id="modulos-senha-erro" class="text-xs text-red-600 mb-3 hidden">Digite sua senha para continuar.</p>
        <div class="flex gap-2 justify-end mt-2">
            <button type="button" id="modulos-senha-cancelar" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 rounded-lg">Cancelar</button>
            <button type="button" id="modulos-senha-confirmar" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Confirmar e salvar</button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.mod-toggle').forEach(function(group) {
    group.querySelectorAll('.mod-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var value = btn.getAttribute('data-value');
            group.querySelector('.mod-toggle-value').value = value;
            group.querySelectorAll('.mod-toggle-btn').forEach(function(b) {
                var active = b.getAttribute('data-value') === value;
                b.classList.toggle('bg-blue-600', active && value === '1');
                b.classList.toggle('bg-gray-600', active && value === '0');
                b.classList.toggle('text-white', active);
                b.classList.toggle('bg-white', !active);
                b.classList.toggle('text-slate-500', !active);
                b.classList.toggle('hover:bg-slate-50', !active);
            });
        });
    });
});

document.querySelectorAll('.js-mod-batch').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var val = this.getAttribute('data-value');
        document.querySelectorAll('.mod-toggle').forEach(function(group) {
            group.querySelector('.mod-toggle-btn[data-value="' + val + '"]').click();
        });
    });
});

(function() {
    var busca = document.getElementById('modulos-busca');
    if (!busca) return;
    busca.addEventListener('input', function() {
        var q = (busca.value || '').toLowerCase().trim();
        document.querySelectorAll('.js-modulo-row').forEach(function(row) {
            var nome = row.getAttribute('data-nome') || '';
            row.classList.toggle('hidden', q !== '' && nome.indexOf(q) === -1);
        });
    });
})();

(function() {
    var form = document.getElementById('form-modulos');
    var modal = document.getElementById('modulos-senha-modal');
    var senhaInput = document.getElementById('modulos-senha-input');
    var senhaErro = document.getElementById('modulos-senha-erro');
    var confirmField = document.getElementById('modulos-confirm-senha');
    var confirmado = false;
    if (!form || !modal) return;

    // fixed dentro do offcanvas (transform) quebra o posicionamento — sobe para o body
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    function abrirModal() {
        senhaInput.value = '';
        senhaErro.classList.add('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        setTimeout(function() { senhaInput.focus(); }, 50);
    }

    function fecharModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    form.addEventListener('submit', function(e) {
        if (!confirmado) {
            e.preventDefault();
            abrirModal();
        }
    });

    document.getElementById('modulos-senha-cancelar').addEventListener('click', fecharModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) fecharModal();
    });

    document.getElementById('modulos-senha-confirmar').addEventListener('click', function() {
        if (senhaInput.value === '') {
            senhaErro.classList.remove('hidden');
            senhaInput.focus();
            return;
        }
        confirmField.value = senhaInput.value;
        confirmado = true;
        fecharModal();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    senhaInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('modulos-senha-confirmar').click();
        }
    });
})();
</script>
