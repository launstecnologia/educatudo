<?php
require_once __DIR__ . '/../../Models/Ocorrencia.php';

use App\Modulos\Ocorrencias\Models\Ocorrencia;

$oc = is_array($ocorrencia ?? null) ? $ocorrencia : [];
$historico = is_array($historico ?? null) ? $historico : [];
$schemaEstendido = !empty($schema_estendido);
$schemaAnexos = !empty($schema_anexos);
$csrf_token = $csrf_token ?? '';
$id = (int) ($oc['id'] ?? 0);
$status = (string) ($oc['status'] ?? '');
$grav = (string) ($oc['nivel_gravidade'] ?? '');
$statusVariant = $status === 'encerrada' ? 'ativo' : ($status === 'em_acompanhamento' ? 'info' : 'pendente');
$gravVariant = $grav === 'grave' ? 'erro' : ($grav === 'moderado' ? 'pendente' : 'neutro');
$acoesHistorico = [
    'criou' => 'Registrou',
    'acompanhou' => 'Passou para acompanhamento',
    'encerrou' => 'Encerrou',
    'liberou_pais' => 'Liberou para os pais',
    'ocultou_pais' => 'Ocultou dos pais',
    'alterou' => 'Alterou',
];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars((string) ($oc['titulo'] ?? 'Ocorrência')) ?></h2>
            <p class="text-gray-600"><?= date('d/m/Y H:i', strtotime((string) ($oc['data_ocorrencia'] ?? 'now'))) ?></p>
        </div>
        <a href="<?= URL ?>/admin/ocorrencias" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex flex-wrap gap-2 mb-4">
        <?php $ui_badge_variant = $gravVariant; $ui_badge_label = Ocorrencia::GRAVIDADES[$grav] ?? $grav; include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
        <?php if ($schemaEstendido): ?>
            <?php $ui_badge_variant = $statusVariant; $ui_badge_label = Ocorrencia::STATUS[$status] ?? $status; include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
        <?php endif; ?>
        <?php $ui_badge_variant = !empty($oc['enviar_pais']) ? 'info' : 'neutro'; $ui_badge_label = !empty($oc['enviar_pais']) ? 'Visível aos pais' : 'Interno'; include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php'; ?>
    </div>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-6">
        <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</dt>
            <dd class="mt-1 text-gray-900"><?= htmlspecialchars((string) ($oc['alunos_nomes'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Turma no fato</dt>
            <dd class="mt-1 text-gray-900"><?= htmlspecialchars((string) ($oc['turma_nome'] ?? '—')) ?></dd>
        </div>
        <?php if ($schemaEstendido): ?>
        <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</dt>
            <dd class="mt-1 text-gray-900"><?= htmlspecialchars((string) ($oc['categoria_nome'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Componente</dt>
            <dd class="mt-1 text-gray-900"><?= htmlspecialchars((string) ($oc['materia_nome'] ?? '—')) ?></dd>
        </div>
        <?php endif; ?>
        <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Local</dt>
            <dd class="mt-1 text-gray-900"><?= htmlspecialchars((string) ($oc['local'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Registrado por</dt>
            <dd class="mt-1 text-gray-900"><?= htmlspecialchars((string) ($oc['criado_por_nome'] ?? '—')) ?></dd>
        </div>
    </dl>
    <?php if (!empty($oc['testemunhas'])): ?>
    <div class="mb-4">
        <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Testemunhas</h3>
        <p class="text-sm text-gray-800 whitespace-pre-line"><?= htmlspecialchars((string) $oc['testemunhas']) ?></p>
    </div>
    <?php endif; ?>
    <div class="mb-4">
        <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Descrição</h3>
        <p class="text-sm text-gray-800 whitespace-pre-line"><?= htmlspecialchars((string) ($oc['detalhe'] ?? '')) ?></p>
    </div>
    <?php if (!empty($oc['diario_aula_id'])): ?>
        <a href="<?= URL ?>/admin/diario/aula?id=<?= (int) $oc['diario_aula_id'] ?>" class="text-sm text-purple-700 hover:underline">Ver aula do Diário ↗</a>
    <?php endif; ?>
</div>

<?php $anexos = is_array($anexos ?? null) ? $anexos : []; ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">Fotos e documentos</h3>
    <?php if (empty($anexos)): ?>
        <p class="text-sm text-gray-500 mb-4">Nenhum anexo ainda.</p>
    <?php else: ?>
        <ul class="space-y-3 mb-4">
            <?php foreach ($anexos as $anexo):
                $mime = (string) ($anexo['mime'] ?? '');
                $isImage = strpos($mime, 'image/') === 0;
                $icon = $isImage ? 'fa-image' : ($mime === 'application/pdf' ? 'fa-file-pdf' : 'fa-file');
                $href = URL . '/admin/ocorrencias/' . $id . '/anexos/' . (int) $anexo['id'];
            ?>
            <li class="flex items-center gap-3">
                <?php if ($isImage): ?>
                    <a href="<?= $href ?>" target="_blank" rel="noopener" class="flex-shrink-0">
                        <img src="<?= $href ?>" alt="" class="h-16 w-16 object-cover rounded-lg border border-gray-200">
                    </a>
                <?php else: ?>
                    <span class="flex-shrink-0 w-16 h-16 rounded-lg border border-gray-200 bg-gray-50 inline-flex items-center justify-center">
                        <i class="fa-solid <?= $icon ?> text-gray-400"></i>
                    </span>
                <?php endif; ?>
                <a href="<?= $href ?>" target="_blank" rel="noopener" class="text-sm text-blue-700 hover:underline">
                    <?= htmlspecialchars((string) ($anexo['nome'] ?? 'arquivo')) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($schemaAnexos): ?>
    <form method="POST" action="<?= URL ?>/admin/ocorrencias/<?= $id ?>/anexos" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="flex-1 w-full">
            <label for="show_anexos" class="block text-sm font-medium text-gray-700 mb-1">Adicionar</label>
            <input type="file" id="show_anexos" name="anexos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,.doc,.docx"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <p class="text-xs text-gray-500 mt-1">JPG, PNG, WebP, GIF, PDF ou Word. Até 8 arquivos, 10 MB cada.</p>
        </div>
        <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Enviar</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($schemaEstendido): ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Acompanhamento</h3>
        <form method="POST" action="<?= URL ?>/admin/ocorrencias/<?= $id ?>/status" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <?php foreach (Ocorrencia::STATUS as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $status === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Motivo da alteração</label>
                <input type="text" name="motivo" maxlength="255" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Opcional">
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Atualizar status</button>
        </form>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Responsável</h3>
        <form method="POST" action="<?= URL ?>/admin/ocorrencias/<?= $id ?>/pais" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="enviar_pais" value="1" class="h-4 w-4" <?= !empty($oc['enviar_pais']) ? 'checked' : '' ?>>
                Disponibilizar no portal do responsável
            </label>
            <?php if (!empty($oc['responsavel_comunicado_em'])): ?>
                <p class="text-xs text-gray-500">Marcado em <?= date('d/m/Y H:i', strtotime((string) $oc['responsavel_comunicado_em'])) ?></p>
            <?php endif; ?>
            <p class="text-xs text-gray-500">Registro interno permanece oculto até esta opção ser ligada. Não envia comunicado automático.</p>
            <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Salvar visibilidade</button>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">Encaminhamento</h3>
    <form method="POST" action="<?= URL ?>/admin/ocorrencias/<?= $id ?>/encaminhamento">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <textarea name="encaminhamento" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-4" placeholder="Próximos passos. Não transforma o fato em punição."><?= htmlspecialchars((string) ($oc['encaminhamento'] ?? '')) ?></textarea>
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Salvar encaminhamento</button>
    </form>
</div>
<?php endif; ?>

<?php if ($historico): ?>
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">Histórico</h3>
    <ul class="space-y-3 text-sm">
        <?php foreach ($historico as $h): ?>
            <li class="border-l-2 border-slate-200 pl-3">
                <p class="text-gray-900"><?= htmlspecialchars($acoesHistorico[$h['acao'] ?? ''] ?? (string) ($h['acao'] ?? '')) ?></p>
                <p class="text-xs text-gray-500">
                    <?= htmlspecialchars((string) ($h['usuario_nome'] ?? '')) ?>
                    · <?= date('d/m/Y H:i', strtotime((string) ($h['created_at'] ?? 'now'))) ?>
                    <?php if (!empty($h['motivo'])): ?> · <?= htmlspecialchars((string) $h['motivo']) ?><?php endif; ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
