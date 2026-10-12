<?php require __DIR__ . '/_styles.php'; ?>

<header class="mb-8 pb-6 border-b border-gray-200">
    <a href="<?= URL ?>/admin/dev" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Configurações Avançadas</a>
    <div class="flex items-center gap-3 mt-2">
        <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-gray-100 text-gray-600 text-2xl">🎥</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Conteúdo</h1>
            <p class="text-sm text-gray-500 mt-0.5">Tutoriais em vídeo exibidos no menu do professor</p>
        </div>
    </div>
</header>

<?php
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'info';
if ($flash_message !== ''):
    $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>
<div class="mb-6 p-4 rounded-lg border <?= $bg ?>">
    <?= htmlspecialchars($flash_message) ?>
</div>
<?php endif; ?>

<div class="max-w-5xl">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Gerenciar Tutoriais</h3>
                <p class="text-gray-500 text-sm mt-1">Adicione vídeos tutoriais do YouTube que serão exibidos no menu do professor.</p>
            </div>
            <button onclick="abrirModalTutorial()" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                ➕ Adicionar Tutorial
            </button>
        </div>
        <div class="p-6">
            <div id="tutoriais-list" class="space-y-4">
                <!-- Lista será carregada via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let tutoriais = [];

    function carregarTutoriais() {
        fetch('<?= URL ?>/admin/dev/tutoriais')
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    tutoriais = data.tutoriais || [];
                    renderizarTutoriais();
                }
            })
            .catch(err => {
                console.error('Erro ao carregar tutoriais:', err);
            });
    }

    function renderizarTutoriais() {
        const container = document.getElementById('tutoriais-list');
        if (!container) return;

        if (tutoriais.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-8">Nenhum tutorial cadastrado. Clique em "Adicionar Tutorial" para começar.</p>';
            return;
        }

        container.innerHTML = tutoriais.map(tutorial => {
            const titulo = escapeHtml(tutorial.titulo);
            const descricao = tutorial.descricao ? escapeHtml(tutorial.descricao) : '';
            const link = escapeHtml(tutorial.link_youtube);
            return `
            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 mb-1">${titulo}</h4>
                        ${descricao ? `<p class="text-sm text-gray-600 mb-2">${descricao}</p>` : ''}
                        <a href="${link}" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-800">
                            ${link}
                        </a>
                        <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                            <span>Ordem: ${tutorial.ordem}</span>
                            <span class="${tutorial.ativo ? 'text-green-600' : 'text-red-600'}">
                                ${tutorial.ativo ? '✓ Ativo' : '✗ Inativo'}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2 ml-4">
                        <button onclick="window.editarTutorial(${tutorial.id})" class="btn-primary-custom px-3 py-1 rounded text-sm hover:opacity-90">
                            ✏️ Editar
                        </button>
                        <button onclick="window.deletarTutorial(${tutorial.id})" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                            🗑️ Deletar
                        </button>
                    </div>
                </div>
            </div>
        `;
        }).join('');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window.abrirModalTutorial = function(tutorialId = null) {
        const tutorial = tutorialId ? tutoriais.find(t => t.id === tutorialId) : null;

        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-xl font-bold mb-4">${tutorial ? 'Editar' : 'Adicionar'} Tutorial</h3>
                <form id="tutorial-form" class="space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    ${tutorial ? `<input type="hidden" name="id" value="${tutorial.id}">` : ''}

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                        <input type="text" name="titulo" value="${tutorial ? escapeHtml(tutorial.titulo) : ''}" required
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                        <textarea name="descricao" rows="3"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">${tutorial ? escapeHtml(tutorial.descricao || '') : ''}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Link do YouTube *</label>
                        <input type="url" name="link_youtube" value="${tutorial ? escapeHtml(tutorial.link_youtube) : ''}" required
                               placeholder="https://www.youtube.com/watch?v=..."
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">Cole o link completo do vídeo do YouTube</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Ordem</label>
                            <input type="number" name="ordem" value="${tutorial ? tutorial.ordem : 0}" min="0"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="ativo" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="1" ${tutorial && tutorial.ativo ? 'selected' : ''}>Ativo</option>
                                <option value="0" ${tutorial && !tutorial.ativo ? 'selected' : ''}>Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="button" onclick="fecharModalTutorial()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        `;

        document.body.appendChild(modal);

        document.getElementById('tutorial-form').addEventListener('submit', function(e) {
            e.preventDefault();
            salvarTutorial(new FormData(this));
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                fecharModalTutorial();
            }
        });
    };

    window.fecharModalTutorial = function() {
        const modal = document.querySelector('.fixed.inset-0.bg-black');
        if (modal) {
            modal.remove();
        }
    };

    window.editarTutorial = function(id) {
        abrirModalTutorial(id);
    };

    window.deletarTutorial = function(id) {
        if (!confirm('Tem certeza que deseja deletar este tutorial?')) return;

        const formData = new FormData();
        formData.append('_token', '<?= htmlspecialchars($csrf_token) ?>');
        formData.append('id', id);

        fetch('<?= URL ?>/admin/dev/tutoriais/delete', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ Tutorial deletado com sucesso');
                carregarTutoriais();
            } else {
                alert('❌ Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Erro ao deletar tutorial');
        });
    };

    function salvarTutorial(formData) {
        fetch('<?= URL ?>/admin/dev/tutoriais/save', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ ' + data.message);
                fecharModalTutorial();
                carregarTutoriais();
            } else {
                alert('❌ Erro: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Erro ao salvar tutorial');
        });
    }

    carregarTutoriais();
})();
</script>
