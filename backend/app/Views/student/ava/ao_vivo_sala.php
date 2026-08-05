<?php
$aula = $aula ?? [];
$estado = (string) ($estado ?? 'agendada');
$joinUrl = (string) ($join_url ?? '');
$podeEmbed = !empty($pode_embed);
$gravacaoUrl = (string) ($gravacao_url ?? '');
$disciplinaId = (int) ($aula['disciplina_id'] ?? 0);
require_once __DIR__ . '/../../../Helpers/RichTextHelper.php';
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/cursos/disciplina/<?= $disciplinaId ?>/ao-vivo" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string) $aula['titulo']) ?></h2>
            <?php if (!empty($aula['inicio_em'])): ?><p class="text-sm text-gray-600"><i class="fa-regular fa-clock mr-1"></i><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $aula['inicio_em']))) ?></p><?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($aula['descricao'])): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 text-sm">
    <?php rich_text((string) $aula['descricao']); ?>
</div>
<?php endif; ?>

<?php if ($estado === 'ao_vivo'): ?>
    <?php if ($podeEmbed && $joinUrl !== ''): ?>
    <div class="bg-black rounded-xl overflow-hidden shadow-sm mb-4">
        <div class="aspect-video"><iframe class="w-full h-full" src="<?= htmlspecialchars($joinUrl) ?>" allow="camera; microphone; fullscreen; display-capture; autoplay" allowfullscreen></iframe></div>
    </div>
    <p class="text-xs text-gray-500 mb-6">Problemas para visualizar? <a href="<?= htmlspecialchars($joinUrl) ?>" target="_blank" rel="noopener" class="text-green-700 hover:underline">Abrir em nova aba</a>.</p>
    <?php elseif ($joinUrl !== ''): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center mb-6">
        <p class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 mb-4"><span class="w-1.5 h-1.5 bg-red-600 rounded-full mr-1 animate-pulse"></span>Ao vivo agora</p>
        <p class="text-gray-700 mb-4">A transmissão está acontecendo. Clique abaixo para entrar.</p>
        <a href="<?= htmlspecialchars($joinUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700"><i class="fa-solid fa-video mr-2"></i> Entrar na aula ao vivo</a>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center mb-6 text-gray-500">Sala indisponível no momento.</div>
    <?php endif; ?>
<?php elseif ($estado === 'agendada'): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center mb-6">
        <i class="fa-regular fa-clock text-3xl text-blue-400 mb-3"></i>
        <p class="text-gray-700">Esta aula ainda não começou.<?php if (!empty($aula['inicio_em'])): ?> Prevista para <strong><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $aula['inicio_em']))) ?></strong>.<?php endif; ?></p>
    </div>
<?php endif; ?>

<?php if ($gravacaoUrl !== ''): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fa-solid fa-film mr-2 text-gray-400"></i>Gravação</h3>
    <a href="<?= htmlspecialchars($gravacaoUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700"><i class="fa-solid fa-play mr-2"></i> Assistir gravação</a>
</div>
<?php elseif ($estado === 'encerrada'): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-500">Esta aula foi encerrada. A gravação ainda não está disponível.</div>
<?php endif; ?>
