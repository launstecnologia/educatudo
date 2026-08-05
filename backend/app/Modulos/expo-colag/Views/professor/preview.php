<?php
$projeto = $projeto ?? [];
$relacoes = $relacoes ?? [];
$objetivos = $relacoes['objetivos'] ?? [];
$etapas = $relacoes['etapas'] ?? [];
$rubrica = $relacoes['rubrica'] ?? [];
$habilidades = $relacoes['habilidades'] ?? [];
?>
<div class="mb-6 space-y-6">
    <div class="flex items-center justify-between gap-3">
        <a href="<?= URL ?>/professor/expo-colag/projetos/<?= (int) ($projeto['id'] ?? 0) ?>/editar" class="text-sm text-primary hover:underline">← Voltar ao wizard</a>
        <span class="text-xs px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 font-medium">Pré-visualização como aluno</span>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 space-y-4">
        <?php if (!empty($projeto['capa_url'])): ?>
            <img src="<?= htmlspecialchars($projeto['capa_url']) ?>" alt="" class="w-full max-h-56 object-cover rounded-lg">
        <?php endif; ?>
        <?php if (!empty($projeto['area'])): ?>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500"><?= htmlspecialchars($projeto['area']) ?></p>
        <?php endif; ?>
        <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($projeto['titulo'] ?? '') ?></h2>
        <?php if (!empty($projeto['subtitulo'])): ?>
            <p class="text-gray-600"><?= htmlspecialchars($projeto['subtitulo']) ?></p>
        <?php endif; ?>
        <p class="text-sm text-gray-600">Orientador: <?= htmlspecialchars($projeto['professor_nome'] ?? '—') ?></p>

        <?php if (!empty($projeto['descricao'])): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-1">Proposta</h2>
                <div class="text-gray-700 whitespace-pre-wrap text-sm"><?= htmlspecialchars($projeto['descricao']) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($objetivos): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-1">Objetivos</h2>
                <ul class="list-disc pl-5 text-sm text-gray-700 space-y-1">
                    <?php foreach ($objetivos as $o): ?>
                        <li><?= htmlspecialchars($o['texto'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($habilidades): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($habilidades as $h): ?>
                    <span class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-800 text-xs"><?= htmlspecialchars($h['codigo_habilidade'] ?? '') ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($etapas): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-2">Cronograma</h2>
                <ol class="space-y-2">
                    <?php foreach ($etapas as $et): ?>
                        <li class="text-sm border border-gray-100 rounded-lg px-3 py-2">
                            <span class="font-medium"><?= htmlspecialchars($et['titulo'] ?? '') ?></span>
                            <?php if (!empty($et['data_limite'])): ?>
                                <span class="text-gray-500"> · até <?= htmlspecialchars(date('d/m/Y', strtotime($et['data_limite']))) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>

        <?php if ($rubrica): ?>
            <div>
                <h2 class="text-sm font-semibold text-gray-800 mb-2">Rubrica</h2>
                <ul class="text-sm space-y-1">
                    <?php foreach ($rubrica as $r): ?>
                        <li class="flex justify-between gap-2 border-b border-gray-50 py-1">
                            <span><?= htmlspecialchars($r['criterio'] ?? '') ?></span>
                            <span class="text-gray-500"><?= htmlspecialchars((string) ($r['peso'] ?? 0)) ?>%</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
            CTA “Quero participar” aparece para o aluno após a publicação (S3).
        </p>
    </div>
</div>
