<?php
require_once __DIR__ . '/../../Models/ClassDiary.php';

use App\Modulos\Diario\Models\ClassDiary;
$execucaoAtual = (string) ($aula['execucao'] ?? 'conforme_planejado');
$statusAtual = (string) ($aula['status'] ?? 'rascunho');
$situacoes = ['presente' => 'Presente', 'falta' => 'Falta', 'falta_justificada' => 'Justificada', 'atraso' => 'Atraso', 'saida_antecipada' => 'Saída antecipada'];
$planos = is_array($planos ?? null) ? $planos : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$tipoAtual = ClassDiary::tipoAulaValido((string) ($aula['tipo_aula'] ?? 'regular'));
$planoAtual = (int) ($aula['plano_aula_id'] ?? 0);
$eventoAtual = (int) ($aula['evento_bloco_id'] ?? 0);
?>
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
<style>
    .diario-select {
        -webkit-appearance: none; appearance: none; display: block; min-height: 46px;
        padding: 0 44px 0 14px; border: 1px solid #cbd5e1; border-radius: 10px;
        background-color: #fff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='m6 8 4 4 4-4' stroke='%23475569' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 13px center; background-size: 20px 20px;
        color: #172033; font-family: inherit; font-size: 16px; font-weight: 500; line-height: 1.25;
        cursor: pointer; box-shadow: 0 1px 2px rgba(15,23,42,.04);
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .diario-select:hover { border-color: #94a3b8; background-color: #f8fafc; }
    .diario-select:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.16); }
    .diario-select--situacao { width: 180px; max-width: 100%; }
    .diario-select--execucao { width: 100%; min-height: 48px; }
    @media (max-width: 640px) { .diario-select--situacao { width: 160px; } }

    /* Quill customizações */
    .ql-container.ql-snow { border-color: #d1d5db; border-radius: 0 0 8px 8px; font-size: 15px; min-height: 140px; }
    .ql-toolbar.ql-snow { border-color: #d1d5db; border-radius: 8px 8px 0 0; background: #f9fafb; }
    .ql-editor { min-height: 120px; }
    .ql-editor.ql-blank::before { color: #9ca3af; font-style: normal; }
</style>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <a href="<?= URL ?>/admin/diario" class="text-indigo-700 hover:underline text-sm">← Voltar ao acompanhamento</a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($aula['materia_nome']) ?> — <?= htmlspecialchars($aula['turma_nome']) ?></h1>
            <p class="text-gray-600 mt-1">
                <?= date('d/m/Y', strtotime($aula['data_aula'])) ?> · <?= substr($aula['horario_de'], 0, 5) ?>–<?= substr($aula['horario_ate'], 0, 5) ?>
                · Professor(a): <?= htmlspecialchars($aula['professor_nome'] ?? '—') ?>
            </p>
        </div>
        <span class="px-3 py-1.5 rounded-full text-sm font-semibold <?= $statusAtual === 'finalizada' ? 'bg-green-100 text-green-800' : ($statusAtual === 'cancelada' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') ?>">
            <?= htmlspecialchars(['rascunho'=>'Rascunho','finalizada'=>'Finalizada','cancelada'=>'Não realizada'][$statusAtual] ?? $statusAtual) ?>
        </span>
    </div>

    <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm rounded-lg p-3">
        Você está lançando esta chamada pela coordenação. O registro fica salvo como se tivesse sido feito pelo(a) professor(a) responsável.
    </div>

    <form method="post" action="<?= URL ?>/admin/diario/salvar" id="formChamada" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="aula_id" value="<?= (int) $aula['id'] ?>">

        <section class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-gray-900">Plano de aula</h2>
            <p class="text-sm text-gray-500">O plano continua no módulo de Planos de Aula. Aqui você só relaciona o que já existe.</p>
            <div>
                <label for="plano_aula_id" class="block text-sm font-semibold text-gray-700 mb-2">Plano relacionado</label>
                <select name="plano_aula_id" id="plano_aula_id" class="diario-select diario-select--execucao">
                    <option value="0">Nenhum plano vinculado</option>
                    <?php foreach ($planos as $plano): ?>
                        <option value="<?= (int) $plano['id'] ?>" <?= $planoAtual === (int) $plano['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $plano['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($planoAtual > 0 && !empty($aula['plano_titulo'])): ?>
                <div class="text-gray-700 whitespace-pre-line"><?= htmlspecialchars(trim(strip_tags((string) ($aula['plano_conteudo'] ?? '')))) ?: '' ?></div>
            <?php elseif ($planos === []): ?>
                <p class="text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">Nenhum plano cadastrado para esta turma e componente. A chamada ainda pode ser realizada normalmente.</p>
            <?php endif; ?>
        </section>

        <section class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <div class="p-5 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Lista de chamada</h2>
                    <p class="text-sm text-gray-500">Todos começam como presentes; marque somente as exceções.</p>
                </div>
                <button type="button" onclick="marcarTodosPresentes()" class="px-3 py-2 text-sm border border-green-300 text-green-800 rounded-lg hover:bg-green-50">Marcar todos presentes</button>
            </div>
            <?php if (empty($alunos)): ?>
                <p class="p-6 text-gray-500">Nenhum aluno ativo encontrado nesta turma.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500 w-12">Nº</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Aluno</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Situação</th>
                                <th class="px-4 py-3 text-left text-xs uppercase text-gray-500 w-40">Observação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        <?php foreach ($alunos as $aluno):
                            $aid  = (int) $aluno['id'];
                            $freq = $frequencias[$aid] ?? [];
                            $sit  = $freq['situacao'] ?? 'presente';
                            $obs  = (string) ($freq['observacao'] ?? '');
                        ?>
                            <tr>
                                <td class="px-4 py-3 text-gray-500"><?= isset($aluno['numero_chamada']) ? (int) $aluno['numero_chamada'] : '—' ?></td>
                                <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($aluno['nome']) ?></td>
                                <td class="px-4 py-3">
                                    <select name="frequencias[<?= $aid ?>][situacao]" class="diario-select diario-select--situacao situacao-aluno" aria-label="Situação de <?= htmlspecialchars($aluno['nome']) ?>">
                                        <?php foreach ($situacoes as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $sit === $value ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <!-- Hidden textarea para submit -->
                                    <textarea name="frequencias[<?= $aid ?>][observacao]" id="obsHidden_<?= $aid ?>" class="hidden"><?= htmlspecialchars($obs) ?></textarea>
                                    <button type="button"
                                        onclick="abrirObs(<?= $aid ?>, <?= json_encode(htmlspecialchars($aluno['nome'])) ?>, <?= json_encode($obs) ?>)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors <?= $obs !== '' ? 'border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100' : 'border-gray-300 text-gray-500 bg-white hover:bg-gray-50' ?>">
                                        <i class="fa-solid <?= $obs !== '' ? 'fa-pen-to-square' : 'fa-plus' ?> text-xs"></i>
                                        <?= $obs !== '' ? 'Ver obs.' : 'Anotar' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-gray-900">Execução da aula</h2>
            <div>
                <label for="tipo_aula" class="block text-sm font-semibold text-gray-700 mb-2">Tipo da aula</label>
                <select name="tipo_aula" id="tipo_aula" class="diario-select diario-select--execucao">
                    <?php foreach (ClassDiary::TIPOS_AULA as $valor => $label): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $tipoAtual === $valor ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="evento_bloco_id" class="block text-sm font-semibold text-gray-700 mb-2">Evento de prova/nota relacionado</label>
                <select name="evento_bloco_id" id="evento_bloco_id" class="diario-select diario-select--execucao">
                    <option value="0">Nenhum (a nota continua no evento)</option>
                    <?php foreach ($eventos as $evento): ?>
                        <option value="<?= (int) $evento['id'] ?>" <?= $eventoAtual === (int) $evento['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $evento['titulo']) ?>
                            · <?= date('d/m/Y', strtotime((string) $evento['data_prova'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Só registra que esta aula foi a aplicação. Lançar nota permanece no evento.</p>
            </div>
            <div>
                <label for="execucao" class="block text-sm font-semibold text-gray-700 mb-2">O que aconteceu?</label>
                <select name="execucao" id="execucao" class="diario-select diario-select--execucao">
                    <option value="conforme_planejado" <?= $execucaoAtual === 'conforme_planejado' ? 'selected' : '' ?>>Ministrado conforme planejado</option>
                    <option value="parcial" <?= $execucaoAtual === 'parcial' ? 'selected' : '' ?>>Ministrado parcialmente</option>
                    <option value="alterado" <?= $execucaoAtual === 'alterado' ? 'selected' : '' ?>>Conteúdo alterado</option>
                    <option value="nao_realizada" <?= $execucaoAtual === 'nao_realizada' ? 'selected' : '' ?>>Aula não realizada</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Conteúdo realizado</label>
                <div id="editorConteudo" style="min-height:120px"><?= rich_text_render($aula['conteudo_realizado'] ?? '') ?></div>
                <textarea name="conteudo_realizado" id="hiddenConteudo" class="hidden"><?= htmlspecialchars($aula['conteudo_realizado'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Observações da aula</label>
                <div id="editorObservacoes" style="min-height:100px"><?= $aula['observacoes'] ?? '' ?></div>
                <textarea name="observacoes" id="hiddenObservacoes" class="hidden"><?= htmlspecialchars($aula['observacoes'] ?? '') ?></textarea>
            </div>
        </section>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pb-8">
            <button type="submit" name="acao" value="rascunho" class="px-5 py-2.5 border border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50">Salvar rascunho</button>
            <button type="submit" name="acao" value="finalizar" onclick="return confirm('Finalizar esta chamada? Os dados continuarão registrados no histórico.')" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700">Finalizar chamada</button>
        </div>
    </form>
</div>

<!-- Modal: Observação do aluno -->
<div id="modalObs" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Observação do aluno</p>
                <h3 class="text-lg font-bold text-gray-900" id="modalObsNome"></h3>
            </div>
            <button onclick="fecharObs()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div id="editorObsAluno" style="min-height:220px"></div>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100">
            <button type="button" onclick="fecharObs()" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" onclick="salvarObs()" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700"><i class="fa-solid fa-check mr-2"></i>Confirmar</button>
        </div>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var toolbarSimples = [['bold','italic','underline'],['blockquote'],[ {'list':'ordered'},{'list':'bullet'}],['clean']];
var toolbarCompleto = [['bold','italic','underline','strike'],['blockquote'],
    [{'list':'ordered'},{'list':'bullet'}],[{'color':[]},{'background':[]}],['clean']];

// Editor: conteúdo realizado
var quillConteudo = new Quill('#editorConteudo', {
    theme: 'snow', modules: { toolbar: toolbarSimples },
    placeholder: 'Preencha somente se houve alteração ou execução parcial.'
});

// Editor: observações da aula
var quillObservacoes = new Quill('#editorObservacoes', {
    theme: 'snow', modules: { toolbar: toolbarSimples },
    placeholder: 'Observações gerais sobre a aula...'
});

// Sincroniza Quill → hidden inputs antes do submit
document.getElementById('formChamada').addEventListener('submit', function() {
    document.getElementById('hiddenConteudo').value    = quillConteudo.root.innerHTML;
    document.getElementById('hiddenObservacoes').value = quillObservacoes.root.innerHTML;
    sincronizarTodasObs();
});

// ── Modal de observação por aluno ────────────────────────────────────────────
var quillObs   = null;
var obsAlunoId = null;

function abrirObs(aid, nome, conteudo) {
    obsAlunoId = aid;
    document.getElementById('modalObsNome').textContent = nome;
    document.getElementById('modalObs').classList.remove('hidden');

    if (!quillObs) {
        quillObs = new Quill('#editorObsAluno', {
            theme: 'snow', modules: { toolbar: toolbarCompleto },
            placeholder: 'Anote observações específicas deste aluno nesta aula...'
        });
    }
    // Carrega conteúdo existente
    if (conteudo && conteudo.trim() !== '') {
        quillObs.root.innerHTML = conteudo;
    } else {
        quillObs.setContents([]);
    }
}

function salvarObs() {
    if (!quillObs || obsAlunoId === null) return;
    var html = quillObs.root.innerHTML;
    // Remove markup vazio
    if (html === '<p><br></p>') html = '';
    document.getElementById('obsHidden_' + obsAlunoId).value = html;
    // Atualiza visual do botão
    var btn = document.querySelector('[onclick*="abrirObs(' + obsAlunoId + ',"]');
    if (btn) {
        if (html !== '') {
            btn.className = btn.className.replace('border-gray-300 text-gray-500 bg-white hover:bg-gray-50', 'border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100');
            btn.innerHTML = '<i class="fa-solid fa-pen-to-square text-xs"></i> Ver obs.';
        } else {
            btn.className = btn.className.replace('border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100', 'border-gray-300 text-gray-500 bg-white hover:bg-gray-50');
            btn.innerHTML = '<i class="fa-solid fa-plus text-xs"></i> Anotar';
        }
    }
    fecharObs();
}

function fecharObs() {
    document.getElementById('modalObs').classList.add('hidden');
}

function sincronizarTodasObs() {
    // Garante que a obs aberta no modal seja salva caso o usuário submeta sem fechar
    if (quillObs && obsAlunoId !== null) {
        var html = quillObs.root.innerHTML;
        if (html === '<p><br></p>') html = '';
        document.getElementById('obsHidden_' + obsAlunoId).value = html;
    }
}

function marcarTodosPresentes() {
    document.querySelectorAll('.situacao-aluno').forEach(function(el){ el.value = 'presente'; });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharObs();
});
document.getElementById('modalObs').addEventListener('click', function(e) {
    if (e.target === this) fecharObs();
});
</script>
