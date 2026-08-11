<?php
/**
 * Componente de foto do aluno (preview + upload opcional).
 *
 * Variáveis esperadas:
 * - $student (array)
 * - $csrf_token (string)
 * - $mode: 'edit' | 'readonly' | 'hero'
 * - $admin_permissions (array, opcional)
 * - $size: 'sm' | 'md' | 'lg' (opcional, default md)
 */
$student = $student ?? [];
$mode = $mode ?? 'edit';
$size = $size ?? 'md';
$admin_permissions = $admin_permissions ?? [];

$canUpload = false;
if ($mode !== 'readonly') {
    if (!empty($admin_permissions)) {
        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../../../Core/AdminPermissionMatrix.php';
        }
        $canUpload = AdminPermissionMatrix::can($admin_permissions, 'alunos', 'alterar');
    } else {
        $canUpload = true;
    }
}

$fotoUrl = $student['foto_display_url'] ?? null;
$initials = htmlspecialchars($student['foto_initials'] ?? '?');
$nome = htmlspecialchars($student['nome'] ?? 'Aluno');
$studentId = (int) ($student['id'] ?? 0);

$sizeClasses = [
    'sm' => ['box' => 'h-10 w-10', 'text' => 'text-sm', 'ring' => 'ring-2'],
    'md' => ['box' => 'h-20 w-20', 'text' => 'text-2xl', 'ring' => 'ring-4'],
    'lg' => ['box' => 'h-24 w-24', 'text' => 'text-3xl', 'ring' => 'ring-4'],
    'xl' => ['box' => 'h-28 w-28', 'text' => 'text-4xl', 'ring' => 'ring-4'],
];
$sc = $sizeClasses[$size] ?? $sizeClasses['md'];
$previewId = 'studentPhotoPreview_' . $studentId . '_' . $mode;
$initialsId = 'studentPhotoInitials_' . $studentId . '_' . $mode;
?>

<div class="flex items-center gap-5 <?= $mode === 'hero' ? 'flex-shrink-0 flex-col items-center text-center' : '' ?>">
    <div class="flex-shrink-0 <?= $mode === 'hero' ? 'flex flex-col items-center' : '' ?>">
        <div id="<?= $previewId ?>_wrap"
             class="<?= $sc['box'] ?> rounded-full bg-white/20 flex items-center justify-center overflow-hidden relative <?= $mode === 'hero' ? $sc['ring'] . ' ring-white/40 shadow-lg' : 'border border-gray-200 bg-slate-200' ?>">
            <?php if (!empty($fotoUrl)): ?>
                <img id="<?= $previewId ?>"
                     class="<?= $sc['box'] ?> rounded-full object-cover absolute inset-0"
                     src="<?= htmlspecialchars($fotoUrl) ?>"
                     alt="Foto de <?= $nome ?>"
                     onerror="this.classList.add('hidden'); document.getElementById('<?= $initialsId ?>')?.classList.remove('hidden');">
                <span id="<?= $initialsId ?>" class="hidden <?= $sc['text'] ?> font-semibold <?= $mode === 'hero' ? 'text-white' : 'text-slate-600' ?>"><?= $initials ?></span>
            <?php else: ?>
                <span id="<?= $initialsId ?>" class="<?= $sc['text'] ?> font-semibold <?= $mode === 'hero' ? 'text-white' : 'text-slate-600' ?>"><?= $initials ?></span>
            <?php endif; ?>
        </div>
        <?php if ($mode === 'hero' && $canUpload && $studentId > 0): ?>
        <button type="button"
                onclick="uploadStudentPhoto(<?= $studentId ?>, '<?= $previewId ?>', '<?= $initialsId ?>')"
                class="mt-3 inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-white/15 text-white border border-white/30 hover:bg-white/25 transition-colors">
            <i class="fa-solid fa-camera mr-1.5 text-[10px]"></i>
            Enviar foto
        </button>
        <?php endif; ?>
    </div>

    <?php if ($mode !== 'hero' && $canUpload && $studentId > 0): ?>
    <div>
        <button type="button"
                onclick="uploadStudentPhoto(<?= $studentId ?>, '<?= $previewId ?>', '<?= $initialsId ?>')"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Alterar foto
        </button>
        <p class="text-sm text-gray-500 mt-2">JPG, PNG, GIF ou WebP (máx. 2MB)</p>
    </div>
    <?php endif; ?>
</div>

<?php if ($canUpload && $studentId > 0 && !defined('STUDENT_PHOTO_UPLOAD_JS')): ?>
<?php define('STUDENT_PHOTO_UPLOAD_JS', true); ?>
<script>
function uploadStudentPhoto(studentId, previewId, initialsId) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/gif,image/webp';

    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('_token', document.getElementById('csrf_token')?.value || '<?= htmlspecialchars($csrf_token ?? '') ?>');
        formData.append('foto', file);

        fetch(`<?= URL ?>/admin/students/${studentId}/foto`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.url) {
                const wrap = document.getElementById(previewId + '_wrap');
                let img = document.getElementById(previewId);
                const initials = document.getElementById(initialsId);

                if (!img && wrap) {
                    img = document.createElement('img');
                    img.id = previewId;
                    img.className = 'absolute inset-0 w-full h-full rounded-full object-cover';
                    img.alt = 'Foto do aluno';
                    wrap.appendChild(img);
                }

                if (img) {
                    img.src = data.url;
                    img.classList.remove('hidden');
                    img.onerror = function() {
                        this.classList.add('hidden');
                        initials?.classList.remove('hidden');
                    };
                }
                if (initials) {
                    initials.classList.add('hidden');
                }
            } else {
                alert('Erro: ' + (data.error || 'Falha no upload'));
            }
        })
        .catch(() => alert('Erro de conexão'));
    };

    input.click();
}
</script>
<?php endif; ?>
