<?php
require_once __DIR__ . '/../../Models/Ocorrencia.php';

use App\Modulos\Ocorrencias\Models\Ocorrencia;

$categorias = is_array($categorias ?? null) ? $categorias : [];
$aluno = is_array($aluno_preenchido ?? null) ? $aluno_preenchido : null;
$aula = is_array($aula ?? null) ? $aula : null;
$schemaEstendido = !empty($schema_estendido);
$csrf_token = $csrf_token ?? '';
$voltar_url = (string) ($voltar_url ?? (URL . '/admin/ocorrencias'));
$dataPadrao = date('Y-m-d\TH:i');
if ($aula && !empty($aula['data_aula'])) {
    $hora = substr((string) ($aula['horario_de'] ?? '08:00:00'), 0, 5);
    $dataPadrao = $aula['data_aula'] . 'T' . $hora;
}
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Nova ocorrência</h2>
            <p class="text-gray-600">O registro fica no aluno. Se nascer de uma aula, o Diário só aponta para cá.</p>
        </div>
        <a href="<?= htmlspecialchars($voltar_url) ?>" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php'; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= URL ?>/admin/ocorrencias" id="formOcorrencia">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <?php if ($aula): ?>
            <input type="hidden" name="diario_aula_id" value="<?= (int) $aula['id'] ?>">
            <p class="mb-6 text-sm text-gray-600 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3">
                Vinculada à aula de <?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?>
                · <?= htmlspecialchars((string) ($aula['turma_nome'] ?? '')) ?>
                · <?= date('d/m/Y', strtotime((string) $aula['data_aula'])) ?>
            </p>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data e hora <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="data_ocorrencia" required value="<?= htmlspecialchars($dataPadrao) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <?php if ($schemaEstendido): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Categoria <span class="text-red-500">*</span></label>
                <select name="categoria_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars((string) $cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Título <span class="text-red-500">*</span></label>
            <input type="text" name="titulo" required maxlength="120" placeholder="Ex.: Conflito no intervalo"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Descrição <span class="text-red-500">*</span></label>
            <textarea name="detalhe" rows="4" required placeholder="Fato observado, sem julgamento automático."
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Gravidade <span class="text-red-500">*</span></label>
                <select name="nivel_gravidade" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <?php foreach (Ocorrencia::GRAVIDADES as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Local</label>
                <input type="text" name="local" maxlength="120" placeholder="Pátio, sala, corredor…"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Encaminhamento</label>
            <textarea name="encaminhamento" rows="3" placeholder="O que a escola vai fazer a partir deste fato. Não é punição automática."
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Retorno para conversar</label>
                <input type="date" name="retorno_em" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div class="flex items-center gap-2 pt-8">
                <input type="checkbox" id="enviar_pais" name="enviar_pais" value="1" class="h-4 w-4">
                <label for="enviar_pais" class="text-sm text-gray-700">Disponibilizar no portal do responsável</label>
            </div>
        </div>

        <div class="mb-6 border-t border-gray-100 pt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Alunos envolvidos</h3>
            <?php if ($aluno): ?>
                <input type="hidden" name="alunos[]" value="<?= (int) $aluno['id'] ?>">
                <p class="text-sm text-gray-800"><?= htmlspecialchars((string) $aluno['nome']) ?>
                    <span class="text-gray-500">· <?= htmlspecialchars((string) ($aluno['turma_nome'] ?? '')) ?></span>
                </p>
            <?php else: ?>
                <div class="flex flex-col md:flex-row gap-3 mb-3">
                    <input type="text" id="alunoBusca" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Buscar aluno pelo nome">
                    <button type="button" id="btnBuscarAluno" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Buscar</button>
                </div>
                <div id="alunoResultados" class="space-y-2 mb-4 text-sm"></div>
                <div id="alunoSelecionados" class="space-y-2"></div>
            <?php endif; ?>
        </div>

        <?php
        $form_cancel_url = $voltar_url;
        $form_submit_label = 'Salvar ocorrência';
        include __DIR__ . '/../../../../Views/admin/_partials/form_actions.php';
        ?>
    </form>
</div>

<?php if (!$aluno): ?>
<script>
(function () {
    const resultados = document.getElementById('alunoResultados');
    const selecionados = document.getElementById('alunoSelecionados');
    const escolhidos = {};

    function renderSelecionados() {
        selecionados.innerHTML = '';
        Object.values(escolhidos).forEach(function (a) {
            const wrap = document.createElement('div');
            wrap.className = 'flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2 text-sm';
            const nome = document.createElement('span');
            nome.textContent = a.nome + (a.turma_nome ? ' · ' + a.turma_nome : '');
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'alunos[]';
            hidden.value = a.id;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'text-xs text-red-600';
            btn.textContent = 'Remover';
            btn.addEventListener('click', function () { delete escolhidos[a.id]; renderSelecionados(); });
            wrap.appendChild(nome);
            wrap.appendChild(hidden);
            wrap.appendChild(btn);
            selecionados.appendChild(wrap);
        });
    }

    document.getElementById('btnBuscarAluno').addEventListener('click', async function () {
        const term = document.getElementById('alunoBusca').value.trim();
        if (!term) return;
        const resp = await fetch('<?= URL ?>/admin/ocorrencias/buscar-alunos?term=' + encodeURIComponent(term));
        const data = await resp.json();
        resultados.innerHTML = '';
        (data.alunos || []).forEach(function (a) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'block w-full text-left px-3 py-2 rounded-lg hover:bg-gray-50';
            btn.textContent = a.nome + (a.turma_nome ? ' · ' + a.turma_nome : '');
            btn.addEventListener('click', function () {
                escolhidos[a.id] = a;
                renderSelecionados();
            });
            resultados.appendChild(btn);
        });
        if (!(data.alunos || []).length) {
            resultados.innerHTML = '<p class="text-gray-500">Nenhum aluno encontrado.</p>';
        }
    });
})();
</script>
<?php endif; ?>
