<?php
$students = is_array($students ?? null) ? $students : [];
$events = is_array($events ?? null) ? $events : [];
?>
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Reconhecimento Facial</h1>
            <p class="text-slate-600 mt-1">Cadastro biométrico e registros simples de entrada e saída.</p>
        </div>
        <a href="<?= URL ?>/admin/reconhecimento-facial/totem" class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">
            <i class="fa-solid fa-camera"></i> Abrir totem da portaria
        </a>
    </div>

    <?php if (empty($schema_ready)): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900">Execute a migration <code>066_facial_attendance.sql</code> neste tenant.</div>
    <?php elseif (empty($api_configured)): ?>
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900">Configure <code>FACIAL_API_KEY</code> e <code>FACIAL_API_BASE_URL</code> no .env do servidor.</div>
    <?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5">
                <form method="GET" action="<?= URL ?>/admin/reconhecimento-facial" class="flex gap-3">
                    <input type="search" name="q" value="<?= htmlspecialchars((string) ($query ?? '')) ?>" placeholder="Buscar aluno por nome ou CPF" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-2.5">
                    <button class="rounded-xl bg-slate-900 px-5 py-2.5 font-semibold text-white">Buscar</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-5 py-3">Aluno</th><th class="px-5 py-3">Face</th><th class="px-5 py-3">Último registro</th><th class="px-5 py-3 text-right">Ação</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (empty($students)): ?>
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">Nenhum aluno encontrado.</td></tr>
                    <?php else: foreach ($students as $student):
                        $parts = !empty($student['last_event']) ? explode('|', (string) $student['last_event'], 2) : [];
                    ?>
                        <tr>
                            <td class="px-5 py-4"><div class="font-semibold text-slate-900"><?= htmlspecialchars($student['nome']) ?></div><div class="text-slate-500"><?= htmlspecialchars(trim(($student['class_grade'] ?? '') . ' ' . ($student['class_name'] ?? ''))) ?></div></td>
                            <td class="px-5 py-4">
                                <?php if ((int) ($student['sample_count'] ?? 0) > 0): ?>
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800"><?= (int) $student['sample_count'] ?> amostra(s)</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Não cadastrado</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-slate-600"><?php if ($parts): ?><strong><?= $parts[0] === 'entrada' ? 'Entrada' : 'Saída' ?></strong><br><?= htmlspecialchars($parts[1] ?? '') ?><?php else: ?>—<?php endif; ?></td>
                            <td class="px-5 py-4 text-right"><a href="<?= URL ?>/admin/reconhecimento-facial/alunos/<?= (int) $student['id'] ?>" class="font-semibold text-blue-700 hover:text-blue-900">Cadastrar face</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Movimentações recentes</h2>
            <div class="mt-4 space-y-3">
                <?php if (empty($events)): ?><p class="text-slate-500">Nenhuma movimentação registrada.</p><?php else: foreach ($events as $event): ?>
                    <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full <?= $event['kind'] === 'entrada' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' ?>">
                            <i class="fa-solid <?= $event['kind'] === 'entrada' ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket' ?>"></i>
                        </div>
                        <div class="min-w-0 flex-1"><div class="truncate font-semibold text-slate-900"><?= htmlspecialchars($event['student_name']) ?></div><div class="text-xs text-slate-500"><?= htmlspecialchars($event['class_name'] ?? '') ?></div></div>
                        <div class="text-right text-sm"><div class="font-semibold"><?= $event['kind'] === 'entrada' ? 'Entrada' : 'Saída' ?></div><div class="text-xs text-slate-500"><?= date('d/m H:i', strtotime($event['event_at'])) ?></div></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </div>
</div>
