<?php
/**
 * Componente para listar alunos com suas atividades
 */
if (!isset($alunos_chat)) {
    $alunos_chat = [];
}
if (!isset($alunos_exercicios)) {
    $alunos_exercicios = [];
}
if (!isset($alunos_redacoes)) {
    $alunos_redacoes = [];
}
?>

<!-- Chat Students List -->
<?php if (!empty($alunos_chat)): ?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
            💬 Alunos com Interações no Chat
        </h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conversas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mensagens</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($alunos_chat as $aluno): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 font-medium"><?= strtoupper(substr($aluno['nome'], 0, 1)) ?></span>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($aluno['nome']) ?></div>
                                    <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($aluno['ra']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($aluno['turma_nome'] ?? 'Sem turma') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= number_format($aluno['total_conversas']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= number_format($aluno['total_mensagens']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="verConversas(<?= $aluno['id'] ?>, '<?= htmlspecialchars($aluno['nome']) ?>')" 
                                    class="text-blue-600 hover:text-blue-900">
                                Ver Conversas
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Exercises Students List -->
<?php if (!empty($alunos_exercicios)): ?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
            📝 Alunos que Fizeram Exercícios
        </h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exercícios</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Média Acerto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($alunos_exercicios as $aluno): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-green-600 font-medium"><?= strtoupper(substr($aluno['nome'], 0, 1)) ?></span>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($aluno['nome']) ?></div>
                                    <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($aluno['ra']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($aluno['turma_nome'] ?? 'Sem turma') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= number_format($aluno['total_exercicios']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= number_format($aluno['media_acerto'], 1) ?>%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="verExercicios(<?= $aluno['id'] ?>, '<?= htmlspecialchars($aluno['nome']) ?>')" 
                                    class="text-green-600 hover:text-green-900">
                                Ver Detalhes
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Essays Students List -->
<?php if (!empty($alunos_redacoes)): ?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
            ✍️ Alunos que Fizeram Redações
        </h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Corrigidas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Média</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($alunos_redacoes as $aluno): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-purple-100 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 font-medium"><?= strtoupper(substr($aluno['nome'], 0, 1)) ?></span>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($aluno['nome']) ?></div>
                                    <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($aluno['ra']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($aluno['turma_nome'] ?? 'Sem turma') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= number_format($aluno['total_redacoes']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= number_format($aluno['redacoes_corrigidas']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= number_format($aluno['media_notas'] ?? 0, 1) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="verRedacoes(<?= $aluno['id'] ?>, '<?= htmlspecialchars($aluno['nome']) ?>')" 
                                    class="text-purple-600 hover:text-purple-900">
                                Ver Redações
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900" id="modalTitle">Detalhes</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="modalContent" class="text-gray-600">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function verConversas(alunoId, alunoNome) {
    document.getElementById('modalTitle').textContent = 'Conversas de ' + alunoNome;
    document.getElementById('modalContent').innerHTML = '<p>Carregando...</p>';
    document.getElementById('detailModal').classList.remove('hidden');
    
    fetch('/admin/reports/api/conversas?aluno_id=' + alunoId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('modalContent').innerHTML = '<p class="text-red-600">' + data.error + '</p>';
                return;
            }
            
            let html = '<div class="max-h-96 overflow-y-auto">';
            if (data.conversas && data.conversas.length > 0) {
                data.conversas.forEach(function(conversa, index) {
                    html += '<div class="mb-4 p-4 border rounded-lg hover:bg-gray-50 cursor-pointer" onclick="verMensagens(' + conversa.id + ', \'' + conversa.titulo + '\')">';
                    html += '<h4 class="font-semibold text-gray-900">' + conversa.titulo + '</h4>';
                    html += '<p class="text-sm text-gray-500">' + conversa.total_mensagens + ' mensagens</p>';
                    html += '<p class="text-xs text-gray-400">Última atividade: ' + new Date(conversa.ultima_atividade).toLocaleString('pt-BR') + '</p>';
                    html += '</div>';
                });
            } else {
                html += '<p>Nenhuma conversa encontrada.</p>';
            }
            html += '</div>';
            document.getElementById('modalContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('modalContent').innerHTML = '<p class="text-red-600">Erro ao carregar dados: ' + error + '</p>';
        });
}

function verMensagens(conversaId, titulo) {
    document.getElementById('modalTitle').textContent = 'Mensagens: ' + titulo;
    document.getElementById('modalContent').innerHTML = '<p>Carregando...</p>';
    
    fetch('/admin/reports/api/mensagens?conversa_id=' + conversaId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('modalContent').innerHTML = '<p class="text-red-600">' + data.error + '</p>';
                return;
            }
            
            let html = '<div class="max-h-96 overflow-y-auto">';
            if (data.mensagens && data.mensagens.length > 0) {
                data.mensagens.forEach(function(mensagem, index) {
                    if (mensagem.is_ia == 1) {
                        html += '<div class="mb-3 p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500">';
                        html += '<p class="text-xs text-gray-500 mb-1"><strong>IA:</strong> ' + new Date(mensagem.created_at).toLocaleString('pt-BR') + '</p>';
                        html += '<p class="text-gray-800">' + mensagem.mensagem + '</p>';
                        html += '</div>';
                    } else {
                        html += '<div class="mb-3 p-3 bg-gray-50 rounded-lg border-l-4 border-gray-400">';
                        html += '<p class="text-xs text-gray-500 mb-1"><strong>' + mensagem.aluno_nome + ':</strong> ' + new Date(mensagem.created_at).toLocaleString('pt-BR') + '</p>';
                        html += '<p class="text-gray-800">' + mensagem.mensagem + '</p>';
                        html += '</div>';
                    }
                });
            } else {
                html += '<p>Nenhuma mensagem encontrada.</p>';
            }
            html += '</div>';
            document.getElementById('modalContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('modalContent').innerHTML = '<p class="text-red-600">Erro ao carregar mensagens: ' + error + '</p>';
        });
}

function verExercicios(alunoId, alunoNome) {
    document.getElementById('modalTitle').textContent = 'Exercícios de ' + alunoNome;
    document.getElementById('modalContent').innerHTML = '<p>Carregando...</p>';
    document.getElementById('detailModal').classList.remove('hidden');
    
    fetch('/admin/reports/api/exercicios?aluno_id=' + alunoId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('modalContent').innerHTML = '<p class="text-red-600">' + data.error + '</p>';
                return;
            }
            
            let html = '<div class="overflow-x-auto max-h-96">';
            if (data.exercicios && data.exercicios.length > 0) {
                html += '<table class="min-w-full divide-y divide-gray-200">';
                html += '<thead class="bg-gray-50 sticky top-0"><tr>';
                html += '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Exercício</th>';
                html += '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>';
                html += '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Taxa Acerto</th>';
                html += '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>';
                html += '</tr></thead><tbody>';
                data.exercicios.forEach(function(exercicio) {
                    html += '<tr class="hover:bg-gray-50">';
                    html += '<td class="px-4 py-2 text-sm">' + (exercicio.titulo || 'Sem título') + '</td>';
                    html += '<td class="px-4 py-2 text-sm text-gray-500">' + (exercicio.materia || '-') + '</td>';
                    html += '<td class="px-4 py-2 text-sm"><span class="px-2 py-1 rounded ' + (exercicio.percentual_acerto >= 70 ? 'bg-green-100 text-green-800' : exercicio.percentual_acerto >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') + '">' + parseFloat(exercicio.percentual_acerto).toFixed(1) + '%</span></td>';
                    html += '<td class="px-4 py-2 text-sm text-gray-500">' + new Date(exercicio.data_fim).toLocaleDateString('pt-BR') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<p>Nenhum exercício encontrado.</p>';
            }
            html += '</div>';
            document.getElementById('modalContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('modalContent').innerHTML = '<p class="text-red-600">Erro ao carregar dados: ' + error + '</p>';
        });
}

function verRedacoes(alunoId, alunoNome) {
    document.getElementById('modalTitle').textContent = 'Redações de ' + alunoNome;
    document.getElementById('modalContent').innerHTML = '<p>Carregando...</p>';
    document.getElementById('detailModal').classList.remove('hidden');
    
    fetch('/admin/reports/api/redacoes?aluno_id=' + alunoId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('modalContent').innerHTML = '<p class="text-red-600">' + data.error + '</p>';
                return;
            }
            
            let html = '<div class="max-h-96 overflow-y-auto space-y-4">';
            if (data.redacoes && data.redacoes.length > 0) {
                data.redacoes.forEach(function(redacao) {
                    const corrigida = redacao.nota_final !== null || redacao.feedback_ia !== null;
                    
                    html += '<div class="border rounded-lg p-4 hover:bg-gray-50">';
                    html += '<div class="flex justify-between items-start mb-3">';
                    html += '<div>';
                    html += '<h4 class="font-semibold text-gray-900 text-lg">' + (redacao.titulo || 'Sem título') + '</h4>';
                    html += '<p class="text-sm text-gray-600">Tema: ' + (redacao.tema_titulo || redacao.tema || 'Sem tema') + '</p>';
                    html += '</div>';
                    
                    if (corrigida) {
                        html += '<span class="px-3 py-1 rounded bg-green-100 text-green-800 text-sm font-medium">✓ Corrigida</span>';
                    } else {
                        html += '<span class="px-3 py-1 rounded bg-yellow-100 text-yellow-800 text-sm font-medium">⏳ Aguardando</span>';
                    }
                    html += '</div>';
                    
                    // Mostrar notas das competências se existirem
                    if (redacao.competencia_1 !== null) {
                        html += '<div class="mt-3 mb-3 p-3 bg-blue-50 rounded-lg">';
                        html += '<p class="font-semibold text-sm text-gray-700 mb-2">📊 Notas das Competências:</p>';
                        html += '<div class="grid grid-cols-5 gap-2">';
                        html += '<div class="text-center"><p class="text-xs text-gray-600">Comp. 1</p><p class="font-bold text-blue-600">' + (redacao.competencia_1 || 0) + '</p></div>';
                        html += '<div class="text-center"><p class="text-xs text-gray-600">Comp. 2</p><p class="font-bold text-blue-600">' + (redacao.competencia_2 || 0) + '</p></div>';
                        html += '<div class="text-center"><p class="text-xs text-gray-600">Comp. 3</p><p class="font-bold text-blue-600">' + (redacao.competencia_3 || 0) + '</p></div>';
                        html += '<div class="text-center"><p class="text-xs text-gray-600">Comp. 4</p><p class="font-bold text-blue-600">' + (redacao.competencia_4 || 0) + '</p></div>';
                        html += '<div class="text-center"><p class="text-xs text-gray-600">Comp. 5</p><p class="font-bold text-blue-600">' + (redacao.competencia_5 || 0) + '</p></div>';
                        html += '</div>';
                        if (redacao.nota_final) {
                            html += '<p class="text-sm mt-2 text-center"><span class="font-bold text-lg text-blue-700">Nota Final: ' + redacao.nota_final + '</span></p>';
                        }
                        html += '</div>';
                    }
                    
                    // Mostrar feedback detalhado se existir
                    if (redacao.feedback_detalhado && corrigida) {
                        html += '<div class="mt-3 p-3 bg-gray-50 rounded-lg border-l-4 border-blue-500">';
                        html += '<p class="font-semibold text-sm text-gray-700 mb-2">📝 Correção Detalhada:</p>';
                        
                        // Mostrar cada competência se existir no feedback detalhado
                        if (redacao.feedback_detalhado.competencia_1) {
                            html += '<div class="mb-2 p-2 bg-white rounded border">';
                            html += '<p class="text-xs font-bold text-blue-600 mb-1">Competência 1 - Domínio da norma padrão</p>';
                            html += '<p class="text-xs text-gray-700">Nota: ' + (redacao.feedback_detalhado.competencia_1.nota || redacao.competencia_1) + '/200</p>';
                            if (redacao.feedback_detalhado.competencia_1.explicacao) {
                                html += '<p class="text-xs text-gray-600 mt-1">' + redacao.feedback_detalhado.competencia_1.explicacao + '</p>';
                            }
                            html += '</div>';
                        }
                        
                        if (redacao.feedback_detalhado.competencia_2) {
                            html += '<div class="mb-2 p-2 bg-white rounded border">';
                            html += '<p class="text-xs font-bold text-blue-600 mb-1">Competência 2 - Compreensão da proposta</p>';
                            html += '<p class="text-xs text-gray-700">Nota: ' + (redacao.feedback_detalhado.competencia_2.nota || redacao.competencia_2) + '/200</p>';
                            if (redacao.feedback_detalhado.competencia_2.explicacao) {
                                html += '<p class="text-xs text-gray-600 mt-1">' + redacao.feedback_detalhado.competencia_2.explicacao + '</p>';
                            }
                            html += '</div>';
                        }
                        
                        if (redacao.feedback_detalhado.competencia_3) {
                            html += '<div class="mb-2 p-2 bg-white rounded border">';
                            html += '<p class="text-xs font-bold text-blue-600 mb-1">Competência 3 - Seleção de argumentos</p>';
                            html += '<p class="text-xs text-gray-700">Nota: ' + (redacao.feedback_detalhado.competencia_3.nota || redacao.competencia_3) + '/200</p>';
                            if (redacao.feedback_detalhado.competencia_3.explicacao) {
                                html += '<p class="text-xs text-gray-600 mt-1">' + redacao.feedback_detalhado.competencia_3.explicacao + '</p>';
                            }
                            html += '</div>';
                        }
                        
                        if (redacao.feedback_detalhado.competencia_4) {
                            html += '<div class="mb-2 p-2 bg-white rounded border">';
                            html += '<p class="text-xs font-bold text-blue-600 mb-1">Competência 4 - Estrutura textual</p>';
                            html += '<p class="text-xs text-gray-700">Nota: ' + (redacao.feedback_detalhado.competencia_4.nota || redacao.competencia_4) + '/200</p>';
                            if (redacao.feedback_detalhado.competencia_4.explicacao) {
                                html += '<p class="text-xs text-gray-600 mt-1">' + redacao.feedback_detalhado.competencia_4.explicacao + '</p>';
                            }
                            html += '</div>';
                        }
                        
                        if (redacao.feedback_detalhado.competencia_5) {
                            html += '<div class="mb-2 p-2 bg-white rounded border">';
                            html += '<p class="text-xs font-bold text-blue-600 mb-1">Competência 5 - Proposta de intervenção</p>';
                            html += '<p class="text-xs text-gray-700">Nota: ' + (redacao.feedback_detalhado.competencia_5.nota || redacao.competencia_5) + '/200</p>';
                            if (redacao.feedback_detalhado.competencia_5.explicacao) {
                                html += '<p class="text-xs text-gray-600 mt-1">' + redacao.feedback_detalhado.competencia_5.explicacao + '</p>';
                            }
                            html += '</div>';
                        }
                        
                        // Mostrar comentários gerais se existirem
                        if (redacao.feedback_detalhado.comentarios_gerais) {
                            html += '<div class="mt-3 p-2 bg-yellow-50 rounded border-l-2 border-yellow-400">';
                            html += '<p class="text-xs font-semibold text-yellow-800 mb-1">💬 Comentários Gerais:</p>';
                            html += '<p class="text-xs text-gray-700">' + redacao.feedback_detalhado.comentarios_gerais + '</p>';
                            html += '</div>';
                        }
                        
                        html += '</div>';
                    }
                    
                    // Mostrar texto da redação (primeiros 300 caracteres)
                    if (redacao.texto || redacao.conteudo) {
                        html += '<div class="mt-3 p-3 bg-white rounded border">';
                        html += '<p class="text-xs font-semibold text-gray-700 mb-2">📄 Redação:</p>';
                        const texto = redacao.texto || redacao.conteudo || '';
                        html += '<p class="text-xs text-gray-600 max-h-32 overflow-y-auto">' + texto.substring(0, 500) + (texto.length > 500 ? '...' : '') + '</p>';
                        html += '</div>';
                    }
                    
                    html += '<p class="text-xs text-gray-400 mt-2">📅 Criada em: ' + new Date(redacao.created_at).toLocaleString('pt-BR') + '</p>';
                    
                    if (redacao.corrigida_em) {
                        html += '<p class="text-xs text-green-600 mt-1">✓ Corrigida em: ' + new Date(redacao.corrigida_em).toLocaleString('pt-BR') + '</p>';
                    }
                    
                    html += '</div>';
                });
            } else {
                html += '<p>Nenhuma redação encontrada.</p>';
            }
            html += '</div>';
            document.getElementById('modalContent').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('modalContent').innerHTML = '<p class="text-red-600">Erro ao carregar dados: ' + error + '</p>';
        });
}

function closeModal() {
    document.getElementById('detailModal').classList.add('hidden');
}
</script>

