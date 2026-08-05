<?php
$atividade = $atividade ?? [];
$entrega = $entrega ?? null;
$arquivos = $arquivos ?? [];
$podeEnviar = !empty($pode_enviar);
$rubricaResultado = $rubrica_resultado ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$atividadeId = (int) ($atividade['id'] ?? 0);
$disciplinaId = (int) ($atividade['disciplina_id'] ?? 0);
$tipo = (string) ($atividade['tipo_entrega'] ?? 'arquivo');
$notaMax = (float) ($atividade['nota_maxima'] ?? 10);
$status = (string) ($entrega['status'] ?? '');
$aceitaArquivo = in_array($tipo, ['arquivo', 'multiplo'], true);
$aceitaTexto = in_array($tipo, ['texto', 'multiplo'], true);
$aceitaLink = $tipo === 'link';
$avaliada = $status === 'avaliada';
require_once __DIR__ . '/../../components/wysiwyg.php';
require_once __DIR__ . '/../../../Helpers/RichTextHelper.php';
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/cursos/disciplina/<?= $disciplinaId ?>/atividades" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string) $atividade['titulo']) ?></h2>
            <?php if (!empty($atividade['data_entrega'])): ?><p class="text-sm text-gray-600"><i class="fa-regular fa-clock mr-1"></i>Prazo: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $atividade['data_entrega']))) ?></p><?php endif; ?>
        </div>
    </div>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Enunciado -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Enunciado</h3>
            <?php if (!empty($atividade['descricao'])): ?><p class="text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars((string) $atividade['descricao']) ?></p><?php endif; ?>
            <?php if (trim((string) ($atividade['instrucoes'] ?? '')) !== ''): ?>
                <div class="text-sm"><?php rich_text((string) $atividade['instrucoes']); ?></div>
            <?php else: ?>
                <div class="text-sm text-gray-500">Sem instruções adicionais.</div>
            <?php endif; ?>
        </div>

        <!-- Feedback (se avaliada) -->
        <?php if ($avaliada): ?>
        <div class="bg-green-50 rounded-xl border border-green-200 p-6">
            <h3 class="text-lg font-semibold text-green-800 mb-2"><i class="fa-solid fa-circle-check mr-2"></i>Avaliada</h3>
            <p class="text-2xl font-bold text-green-700 mb-3"><?= number_format((float) ($entrega['nota'] ?? 0), 2, ',', '') ?> <span class="text-base font-medium text-green-600">/ <?= number_format($notaMax, 2, ',', '') ?></span></p>
            <?php if (!empty($entrega['feedback'])): ?><div class="text-sm bg-white rounded-lg p-3 border border-green-100"><?php rich_text((string) $entrega['feedback']); ?></div><?php endif; ?>
            <?php if (!empty($rubricaResultado)): ?>
                <div class="mt-3 space-y-1">
                    <?php foreach ($rubricaResultado as $r): ?>
                    <div class="flex items-center justify-between text-xs text-gray-600 bg-white rounded px-3 py-1.5 border border-green-100">
                        <span><?= htmlspecialchars((string) ($r['titulo'] ?? '')) ?></span>
                        <span class="font-semibold"><?= number_format((float) ($r['pontuacao'] ?? 0), 2, ',', '') ?> / <?= number_format((float) ($r['pontuacao_max'] ?? 0), 2, ',', '') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Formulário de envio -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Sua entrega</h3>
            <?php if (!$podeEnviar && !$avaliada): ?>
                <p class="text-sm text-red-600 mb-4"><i class="fa-solid fa-lock mr-1"></i> O prazo de entrega está encerrado.</p>
            <?php endif; ?>

            <?php if (!empty($arquivos)): ?>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Arquivos enviados</p>
                    <ul class="divide-y divide-gray-100 border border-gray-100 rounded-lg">
                        <?php foreach ($arquivos as $f): ?>
                        <li class="py-2 px-3 flex items-center justify-between">
                            <a href="<?= URL ?>/cursos/entrega/arquivo/<?= (int) $f['id'] ?>" target="_blank" rel="noopener" class="text-sm text-gray-700 hover:text-green-700"><i class="fa-solid fa-paperclip text-gray-400 mr-2"></i><?= htmlspecialchars((string) ($f['nome'] ?? 'Arquivo')) ?></a>
                            <?php if (!$avaliada): ?>
                            <form method="post" action="<?= URL ?>/cursos/entrega/arquivo/<?= (int) $f['id'] ?>/excluir" onsubmit="return confirm('Remover arquivo?');">
                                <input type="hidden" name="_token" value="<?= $csrf ?>">
                                <button type="submit" class="text-gray-300 hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($podeEnviar): ?>
            <form method="post" action="<?= URL ?>/cursos/atividade/<?= $atividadeId ?>/enviar" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <?php if ($aceitaTexto): ?>
                <div><?php wysiwyg_field(['name' => 'texto', 'label' => 'Texto / Resposta', 'value' => $entrega['texto'] ?? '', 'rows' => 6, 'placeholder' => 'Escreva sua resposta...']); ?></div>
                <?php endif; ?>
                <?php if ($aceitaLink): ?>
                <div>
                    <label for="link" class="block text-sm font-medium text-gray-700 mb-2">Link / URL</label>
                    <input type="url" id="link" name="link" value="<?= htmlspecialchars((string) ($entrega['link'] ?? '')) ?>" placeholder="https://..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <?php endif; ?>
                <?php if ($aceitaArquivo): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Arquivos <span class="text-xs text-gray-400">(até <?= (int) ($atividade['max_arquivos'] ?? 5) ?>, <?= (int) ($atividade['tamanho_max_mb'] ?? 20) ?>MB cada)</span></label>
                    <input type="file" name="arquivos[]" multiple class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-green-50 file:text-green-700 file:font-medium hover:file:bg-green-100">
                </div>
                <?php endif; ?>
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 shadow-sm"><i class="fa-solid fa-paper-plane mr-2"></i> Enviar atividade</button>
                </div>
            </form>
            <?php elseif ($avaliada): ?>
                <p class="text-sm text-gray-500">Esta atividade já foi avaliada.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Detalhes</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Nota máxima</dt><dd class="font-medium text-gray-800"><?= number_format($notaMax, 2, ',', '') ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Peso</dt><dd class="font-medium text-gray-800"><?= number_format((float) ($atividade['peso'] ?? 1), 2, ',', '') ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Formato</dt><dd class="font-medium text-gray-800"><?= htmlspecialchars((string) ($atividade['tipo_entrega'] ?? '')) ?></dd></div>
                <?php if (!empty($entrega['atrasada'])): ?><div class="flex justify-between"><dt class="text-gray-500">Entrega</dt><dd class="font-medium text-amber-600">Atrasada</dd></div><?php endif; ?>
            </dl>
        </div>
    </div>
</div>
