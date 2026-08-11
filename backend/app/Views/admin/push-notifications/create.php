<?php
/**
 * View: Admin - Enviar Notificação Push
 */
$title = $title ?? 'Enviar Notificação Push';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <a href="<?= URL ?>/admin/notificacoes-push" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="mb-4 p-4 rounded-lg <?= ($flash_type ?? '') === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($onesignal_configured) && !$onesignal_configured): ?>
        <div class="mb-4 p-4 rounded-lg bg-amber-100 text-amber-800">
            Configure ONESIGNAL_APP_ID e ONESIGNAL_REST_API_KEY no .env para enviar.
        </div>
    <?php endif; ?>

    <?php if (isset($fcm_configured) && !$fcm_configured): ?>
        <div class="mb-4 p-4 rounded-lg bg-amber-100 text-amber-800">
            Para o aplicativo Android, configure FIREBASE_PROJECT_ID e FIREBASE_SERVICE_ACCOUNT_PATH no .env.
        </div>
    <?php endif; ?>

    <form action="<?= URL ?>/admin/notificacoes-push/enviar" method="POST" class="bg-white rounded-lg shadow-lg p-8 max-w-2xl">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <div class="space-y-6">
            <div>
                <label for="titulo" class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                <input type="text" id="titulo" name="titulo" required maxlength="255"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: Lembrete de atividade">
            </div>
            <div>
                <label for="mensagem" class="block text-sm font-medium text-gray-700 mb-1">Mensagem *</label>
                <textarea id="mensagem" name="mensagem" required rows="4"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Texto da notificação"></textarea>
            </div>
            <div>
                <label for="url" class="block text-sm font-medium text-gray-700 mb-1">URL (ao clicar)</label>
                <input type="text" id="url" name="url" maxlength="500"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Ex: /dashboard ou https://...">
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700 mb-2">Canais *</span>
                <div class="flex flex-wrap gap-5">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="canais[]" value="onesignal" <?= !empty($onesignal_configured) ? 'checked' : '' ?>>
                        Web/PWA (OneSignal)
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="canais[]" value="fcm" <?= !empty($fcm_configured) ? 'checked' : '' ?>>
                        Aplicativo Android (Firebase)
                    </label>
                </div>
            </div>
            <div>
                <label for="tipo_destino" class="block text-sm font-medium text-gray-700 mb-1">Enviar para *</label>
                <select id="tipo_destino" name="tipo_destino" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        onchange="toggleDestinoSelect()">
                    <option value="todos">Todos (pais, alunos, professores)</option>
                    <option value="pais">Pais</option>
                    <option value="alunos">Alunos</option>
                    <option value="professores">Professores</option>
                    <option value="turma">Turma específica</option>
                    <option value="responsavel">Responsável específico (app)</option>
                    <option value="usuario">Usuário específico</option>
                </select>
            </div>
            <div id="wrap_responsavel" style="display: none;">
                <label for="destino_id_responsavel" class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                <select id="destino_id_responsavel" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Selecione</option>
                    <?php if (!empty($responsaveis)): foreach ($responsaveis as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nome'] . (!empty($r['email']) ? ' - ' . $r['email'] : '')) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div id="wrap_turma" style="display: none;">
                <label for="destino_id_turma" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                <select id="destino_id_turma" name="destino_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Selecione</option>
                    <?php if (!empty($turmas)): foreach ($turmas as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome'] . (isset($t['serie']) ? ' (' . $t['serie'] . ')' : '')) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div id="wrap_usuario" style="display: none;">
                <label for="destino_id_usuario" class="block text-sm font-medium text-gray-700 mb-1">Usuário</label>
                <select id="destino_id_usuario" name="destino_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Selecione</option>
                    <?php if (!empty($usuarios)): foreach ($usuarios as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nome'] . ' (' . ($u['tipo'] ?? '') . ')') ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="pt-4">
                <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg font-medium hover:opacity-90">
                    Enviar Notificação
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function toggleDestinoSelect() {
    var tipo = document.getElementById('tipo_destino').value;
    var wrapTurma = document.getElementById('wrap_turma');
    var wrapUsuario = document.getElementById('wrap_usuario');
    var wrapResponsavel = document.getElementById('wrap_responsavel');
    var selTurma = document.getElementById('destino_id_turma');
    var selUsuario = document.getElementById('destino_id_usuario');
    var selResponsavel = document.getElementById('destino_id_responsavel');
    wrapTurma.style.display = tipo === 'turma' ? 'block' : 'none';
    wrapUsuario.style.display = tipo === 'usuario' ? 'block' : 'none';
    wrapResponsavel.style.display = tipo === 'responsavel' ? 'block' : 'none';
    if (tipo !== 'turma') selTurma.removeAttribute('name');
    else selTurma.setAttribute('name', 'destino_id');
    if (tipo !== 'usuario') selUsuario.removeAttribute('name');
    else selUsuario.setAttribute('name', 'destino_id');
    if (tipo !== 'responsavel') selResponsavel.removeAttribute('name');
    else selResponsavel.setAttribute('name', 'destino_id');
}
toggleDestinoSelect();
</script>
