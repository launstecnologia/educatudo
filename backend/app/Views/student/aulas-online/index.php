<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Aulas Online</h1>
        <p class="text-sm text-gray-600">Acompanhe suas aulas ao vivo, agendadas e encerradas.</p>
    </div>

    <div class="space-y-4">
        <?php if (empty($aulas)): ?>
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <p class="text-sm text-gray-600">Nenhuma aula online disponível no momento.</p>
            </div>
        <?php else: ?>
            <?php foreach ($aulas as $aula): ?>
                <?php
                $inicioTs = !empty($aula['inicio_em']) ? strtotime((string) $aula['inicio_em']) : false;
                $fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
                $nowTs = time();
                $started = $inicioTs !== false && $nowTs >= $inicioTs;
                $ended = $fimTs !== false && $nowTs > $fimTs;
                $isLive = $started && !$ended;
                $hasRecording = trim((string) ($aula['panda_recording_player'] ?? '')) !== ''
                    || trim((string) ($aula['panda_recording_hls'] ?? '')) !== '';
                $statusLabel = $isLive ? 'Aula ao vivo' : ($ended ? ($hasRecording ? 'Gravação disponível' : 'Encerrada') : 'Agendada');
                $statusClass = $isLive
                    ? 'bg-red-100 text-red-700'
                    : ($ended ? ($hasRecording ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600') : 'bg-blue-100 text-blue-700');
                $buttonLabel = $isLive ? 'Aula ao vivo' : ($hasRecording ? 'Assistir gravação' : 'Ver detalhes');
                $descricao = preg_replace('/(?:\R\s*)?\[Live Panda ID:[^\]]+\]\s*/u', '', (string) ($aula['descricao'] ?? ''));
                $descricao = trim((string) $descricao);
                ?>
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars((string) ($aula['titulo'] ?? '')) ?></h2>
                                <span class="text-xs px-2 py-1 rounded-full <?= $statusClass ?>"><?= $statusLabel ?></span>
                                <?php if (!empty($aula['plataforma'])): ?>
                                    <span class="text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                        <?= htmlspecialchars((string) $aula['plataforma']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($descricao !== ''): ?>
                                <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars($descricao)) ?></p>
                            <?php endif; ?>

                            <div class="mt-3 text-sm text-gray-700 space-y-1">
                                <div><strong>Início:</strong> <?= $inicioTs !== false ? date('d/m/Y H:i', $inicioTs) : '-' ?></div>
                                <?php if ($fimTs !== false): ?>
                                    <div><strong>Término:</strong> <?= date('d/m/Y H:i', $fimTs) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex-shrink-0">
                            <a href="<?= URL ?>/aluno/aulas-online/<?= (int) ($aula['id'] ?? 0) ?>"
                               class="inline-flex items-center px-4 py-2 <?= $isLive ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700' ?> text-white rounded-lg">
                                <?= $buttonLabel ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
