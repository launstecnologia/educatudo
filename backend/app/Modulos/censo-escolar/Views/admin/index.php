<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$painel = is_array($painel ?? null) ? $painel : [];
$cards = is_array($painel['cards'] ?? null) ? $painel['cards'] : [];
$validacao = is_array($painel['validacao'] ?? null) ? $painel['validacao'] : [];
$layout = is_array($painel['layout'] ?? null) ? $painel['layout'] : [];
$podeGerar = is_array($painel['pode_gerar'] ?? null) ? $painel['pode_gerar'] : ['ok' => false, 'motivo' => ''];
$csrf_token = $csrf_token ?? '';
$eid = (int) ($edicao['id'] ?? 0);

$page_header_title = 'Censo Escolar';
$page_header_subtitle = 'Reaproveita os cadastros da escola e solicita só o que falta para o Educacenso.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
include __DIR__ . '/_contexto.php';

$nav_atual = 'visao';
if ($eid > 0) {
    include __DIR__ . '/_nav.php';
}
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<?php if (empty($layout['oficial'])): ?>
<div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 text-sm">
    O leiaute oficial do INEP desta edição ainda não foi importado. O sistema valida a completude dos cadastros,
    mas o arquivo TXT de migração permanece bloqueado até o documento oficial ser registrado.
    <?php if (!empty($layout['fonte_oficial'])): ?>
        <a class="underline font-medium" href="<?= $esc($layout['fonte_oficial']) ?>" target="_blank" rel="noopener">Portal do Censo Escolar</a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-green-900 text-sm">
    Leiaute oficial da Matrícula Inicial carregado (<?= $esc($layout['versao'] ?? 'v3') ?>).
    <?php if (!empty($layout['aplicado_de']) && (int) $layout['aplicado_de'] !== (int) ($edicao['ano'] ?? 0)): ?>
        Estrutura de <?= (int) $layout['aplicado_de'] ?> aplicada à edição <?= (int) ($edicao['ano'] ?? 0) ?>.
    <?php endif; ?>
    Sincronize, corrija as pendências e gere o TXT em Exportações.
    <?php if (!empty($podeGerar['ok'])): ?>
        Pronto para gerar.
    <?php elseif (!empty($podeGerar['motivo'])): ?>
        <?= $esc($podeGerar['motivo']) ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($eid > 0): ?>
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
    <?php foreach ($cards as $card):
        $pct = (int) ($card['percentual'] ?? 0);
        $href = URL . '/admin/censo/' . $eid . '/' . $esc($card['chave']);
    ?>
    <a href="<?= $href ?>" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-gray-300 transition-colors">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-800"><?= $esc($card['label']) ?></p>
            <p class="text-sm font-semibold text-gray-900"><?= $pct ?>%</p>
        </div>
        <div class="h-1.5 bg-gray-100 rounded-full mb-2 overflow-hidden">
            <div class="h-full bg-primary rounded-full" style="width: <?= max(0, min(100, $pct)) ?>%"></div>
        </div>
        <p class="text-xs text-gray-500">
            <?= (int) ($card['prontos'] ?? 0) ?>/<?= (int) ($card['total'] ?? 0) ?> prontos
            · <?= (int) ($card['pendencias'] ?? 0) ?> pendência(s)
        </p>
    </a>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">Resumo de validação</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>Erros impeditivos: <strong><?= (int) ($validacao['erros'] ?? 0) ?></strong></div>
        <div>Alertas: <strong><?= (int) ($validacao['alertas'] ?? 0) ?></strong></div>
        <div>Divergências: <strong><?= (int) ($validacao['divergencias'] ?? 0) ?></strong></div>
        <div>Conferidos/justificados: <strong><?= (int) (($validacao['conferidas'] ?? 0) + ($validacao['justificadas'] ?? 0)) ?></strong></div>
    </div>
</div>

<div class="flex flex-wrap gap-3">
    <form method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/sincronizar">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Sincronizar cadastros</button>
    </form>
    <form method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/validar">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Validar dados</button>
    </form>
    <a href="<?= URL ?>/admin/censo/<?= $eid ?>/pendencias" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Visualizar pendências</a>
    <a href="<?= URL ?>/admin/censo/<?= $eid ?>/previa" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Gerar prévia</a>
    <span title="<?= $esc($podeGerar['motivo'] ?? '') ?>">
        <button type="button" <?= empty($podeGerar['ok']) ? 'disabled' : '' ?>
                onclick="document.getElementById('form-gerar-txt').submit()"
                class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
            Gerar TXT
        </button>
    </span>
    <form id="form-gerar-txt" method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/exportacoes" class="hidden">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
    </form>
    <?php if (($edicao['status'] ?? '') !== 'fechado'): ?>
    <form method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/fechar" onsubmit="return confirm('Fechar a edição? Os dados declarados ficarão bloqueados.');">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Fechar edição</button>
    </form>
    <?php endif; ?>
    <a href="<?= URL ?>/admin/students/export-censo" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">CSV auxiliar</a>
</div>
<?php endif; ?>
