<?php
$auditRows = is_array($audit_logs ?? null) ? $audit_logs : [];
$student = is_array($student ?? null) ? $student : [];

$auditLabels = [
    'CREATE_STUDENT' => ['Cadastro do aluno', 'fa-user-plus', 'text-green-600 bg-green-50'],
    'UPDATE_STUDENT' => ['Edição do cadastro', 'fa-user-pen', 'text-blue-600 bg-blue-50'],
    'DELETE_STUDENT' => ['Exclusão do aluno', 'fa-user-xmark', 'text-red-600 bg-red-50'],
    'LINK_GUARDIAN' => ['Responsável vinculado', 'fa-link', 'text-indigo-600 bg-indigo-50'],
    'UPDATE_GUARDIAN' => ['Responsável atualizado', 'fa-user-shield', 'text-indigo-600 bg-indigo-50'],
    'SAVE_STUDENT_DOCUMENT' => ['Documento salvo', 'fa-file-circle-plus', 'text-indigo-600 bg-indigo-50'],
    'DELETE_STUDENT_DOCUMENT' => ['Documento removido', 'fa-file-circle-xmark', 'text-red-600 bg-red-50'],
    'DOWNLOAD_STUDENT_DOCUMENT' => ['Download de documento', 'fa-file-arrow-down', 'text-slate-600 bg-slate-100'],
    'GENERATE_DECLARATION' => ['Declaração/documento emitido', 'fa-file-pdf', 'text-rose-600 bg-rose-50'],
    'VIEW_ADMIN' => ['Visualização do perfil', 'fa-eye', 'text-slate-600 bg-slate-100'],
];
?>
<div class="max-w-4xl mx-auto p-6">
    <div class="mb-6">
        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>" class="text-sm text-indigo-600 hover:underline">&larr; Voltar para o aluno</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Histórico de auditoria</h1>
        <p class="text-gray-600 mt-1">
            Últimas ações sensíveis registradas para <strong><?= htmlspecialchars((string) ($student['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <?php if (empty($auditRows)): ?>
            <div class="text-center py-12 text-gray-500">Nenhuma ação registrada ainda.</div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($auditRows as $log):
                    $code = (string) ($log['action'] ?? '');
                    [$lblAud, $iconAud, $clsAud] = $auditLabels[$code] ?? [$code, 'fa-circle-info', 'text-slate-600 bg-slate-100'];
                    $quando = !empty($log['created_at']) ? date('d/m/Y H:i', strtotime((string) $log['created_at'])) : '';
                    $papel = trim((string) ($log['user_role'] ?? ''));
                    $ip = trim((string) ($log['ip_address'] ?? ''));
                ?>
                <li class="flex items-start gap-3 py-3">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 <?= $clsAud ?>">
                        <i class="fa-solid <?= $iconAud ?> text-xs"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($lblAud, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            <?= htmlspecialchars($quando, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($papel !== ''): ?> · <?= htmlspecialchars($papel, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                            <?php if ($ip !== ''): ?> · IP <?= htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                        </p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
