<?php
/**
 * Componente de foto do aluno (preview + upload opcional).
 *
 * Variáveis esperadas:
 * - $student (array)
 * - $csrf_token (string)
 * - $mode: 'edit' | 'readonly' | 'hero' | 'compact'
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
        }
        $canUpload = \App\Core\AdminPermissionMatrix::can($admin_permissions, 'alunos', 'alterar');
    } else {
        $canUpload = true;
    }
}

$fotoUrl = $student['foto_display_url'] ?? null;
$initials = htmlspecialchars($student['foto_initials'] ?? '?');
$nome = htmlspecialchars($student['nome_exibicao'] ?? $student['nome'] ?? 'Aluno');
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
$fileInputId = 'studentPhotoFile_' . $studentId . '_' . $mode;
$uploadEnabled = $canUpload && $studentId > 0;
$circleClass = $sc['box'] . ' rounded-full flex items-center justify-center overflow-hidden relative '
    . ($mode === 'hero'
        ? $sc['ring'] . ' ring-white/40 shadow-lg bg-white/20'
        : ($mode === 'compact' ? 'border border-violet-100 bg-violet-100' : 'border border-gray-200 bg-slate-200'));
$initialsClass = $sc['text'] . ' font-semibold '
    . ($mode === 'hero' ? 'text-white' : ($mode === 'compact' ? 'text-violet-700' : 'text-slate-600'));
?>

<div class="flex items-center gap-5 <?= $mode === 'hero' ? 'flex-shrink-0 flex-col items-center text-center' : '' ?>">
    <div class="flex-shrink-0 relative <?= $mode === 'hero' ? 'flex flex-col items-center' : '' ?>">
        <?php if ($uploadEnabled): ?>
        <label for="<?= $fileInputId ?>" class="block cursor-pointer" title="Enviar foto do aluno">
        <?php endif; ?>
        <div id="<?= $previewId ?>_wrap" class="<?= $circleClass ?>">
            <?php if (!empty($fotoUrl)): ?>
                <img id="<?= $previewId ?>"
                     class="<?= $sc['box'] ?> rounded-full object-cover absolute inset-0"
                     style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:9999px;"
                     src="<?= htmlspecialchars($fotoUrl) ?>"
                     alt="Foto de <?= $nome ?>"
                     onerror="this.classList.add('hidden'); document.getElementById('<?= $initialsId ?>')?.classList.remove('hidden');">
                <span id="<?= $initialsId ?>" class="hidden <?= $initialsClass ?>"><?= $initials ?></span>
            <?php else: ?>
                <span id="<?= $initialsId ?>" class="<?= $initialsClass ?>"><?= $initials ?></span>
            <?php endif; ?>
        </div>
        <?php if ($uploadEnabled): ?>
        </label>
        <?php endif; ?>
        <?php if ($mode === 'hero' && $uploadEnabled): ?>
        <label for="<?= $fileInputId ?>"
               class="mt-3 inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-white/15 text-white border border-white/30 hover:bg-white/25 transition-colors cursor-pointer">
            <i class="fa-solid fa-camera mr-1.5 text-[10px]"></i>
            Enviar foto
        </label>
        <?php endif; ?>
        <?php if ($mode === 'compact' && $uploadEnabled): ?>
        <label for="<?= $fileInputId ?>"
               class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-white border border-gray-200 text-slate-600 shadow-sm hover:bg-slate-50 cursor-pointer flex items-center justify-center"
               aria-label="Enviar foto do aluno">
            <i class="fa-solid fa-camera text-[10px]"></i>
        </label>
        <?php endif; ?>
    </div>

    <?php if ($mode !== 'hero' && $mode !== 'compact' && $uploadEnabled): ?>
    <div>
        <label for="<?= $fileInputId ?>"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors cursor-pointer">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Alterar foto
        </label>
        <p class="text-sm text-gray-500 mt-2">JPG, PNG, GIF ou WebP (máx. 2MB)</p>
    </div>
    <?php endif; ?>
</div>
<?php if ($uploadEnabled): ?>
<input type="file"
       id="<?= $fileInputId ?>"
       form="student-photo-fora"
       class="sr-only"
       accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp"
       onchange="enviarFotoAluno(this, <?= $studentId ?>, '<?= $previewId ?>', '<?= $initialsId ?>')">
<?php endif; ?>

<?php if ($uploadEnabled && !defined('STUDENT_PHOTO_UPLOAD_JS')): ?>
<?php define('STUDENT_PHOTO_UPLOAD_JS', true); ?>
<script>
function aplicarPreviewFotoAluno(previewId, initialsId, src) {
    const wrap = document.getElementById(previewId + '_wrap');
    let img = document.getElementById(previewId);
    const initials = document.getElementById(initialsId);

    if (!img && wrap) {
        img = document.createElement('img');
        img.id = previewId;
        img.alt = 'Foto do aluno';
        img.className = 'absolute inset-0 w-full h-full rounded-full object-cover';
        wrap.appendChild(img);
    }

    if (img) {
        img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:9999px;';
        img.classList.remove('hidden');
        img.src = src;
    }
    if (initials) {
        initials.classList.add('hidden');
    }
    return img;
}

function enviarFotoAluno(input, studentId, previewId, initialsId) {
    const file = input.files && input.files[0];
    input.value = '';
    if (!file) {
        return;
    }
    if (!file.size) {
        alert('Não foi possível ler a foto. Se ela está no iCloud, baixe o arquivo e tente de novo.');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('Arquivo muito grande. Máximo 2MB.');
        return;
    }

    const nome = (file.name || '').toLowerCase();
    if (nome.endsWith('.heic') || nome.endsWith('.heif') || file.type === 'image/heic' || file.type === 'image/heif') {
        alert('Fotos HEIC do iPhone não são aceitas. Exporte a imagem como JPG ou PNG.');
        return;
    }

    const token = document.getElementById('csrf_token')?.value || <?= json_encode((string) ($csrf_token ?? '')) ?>;
    if (!token) {
        alert('Sessão expirada. Recarregue a página e tente novamente.');
        return;
    }

    const imgAtual = document.getElementById(previewId);
    const srcAnterior = imgAtual && !imgAtual.classList.contains('hidden') ? (imgAtual.getAttribute('src') || '') : '';
    const localUrl = URL.createObjectURL(file);
    const img = aplicarPreviewFotoAluno(previewId, initialsId, localUrl);

    const formData = new FormData();
    formData.append('_token', token);
    formData.append('foto', file);

    fetch(<?= json_encode(rtrim((string) (defined('URL') ? URL : ''), '/') . '/admin/students/') ?> + studentId + '/foto', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (response) {
        return response.text().then(function (text) {
            var data = null;
            try { data = JSON.parse(text); } catch (e) { data = null; }
            return { ok: response.ok, data: data };
        });
    })
    .then(function (res) {
        if (res.ok && res.data && res.data.success && res.data.url) {
            if (img) {
                img.onerror = function () {
                    img.onerror = null;
                    img.src = localUrl;
                    alert('A foto foi salva, mas não apareceu neste endereço. Atualize a página.');
                };
                img.src = res.data.url;
            }
            return;
        }
        if (srcAnterior && img) {
            img.onerror = null;
            img.src = srcAnterior;
        } else if (img) {
            img.classList.add('hidden');
            document.getElementById(initialsId)?.classList.remove('hidden');
        }
        URL.revokeObjectURL(localUrl);
        alert('Erro: ' + ((res.data && res.data.error) || 'Falha no upload da foto'));
    })
    .catch(function () {
        if (srcAnterior && img) {
            img.src = srcAnterior;
        } else if (img) {
            img.classList.add('hidden');
            document.getElementById(initialsId)?.classList.remove('hidden');
        }
        URL.revokeObjectURL(localUrl);
        alert('Erro de conexão ao enviar a foto.');
    });
}
</script>
<?php endif; ?>
