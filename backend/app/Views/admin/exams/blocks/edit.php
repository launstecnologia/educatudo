<!-- Header Section -->
<style>
#formBloco select {
    -webkit-appearance: none;
    appearance: none;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem 1rem;
    padding-right: 2.5rem;
    min-height: 42px;
}

#formBloco select::-ms-expand {
    display: none;
}

#formBloco select:focus {
    outline: none;
}
</style>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Editar Bloco de Provas 📚
            </h2>
            <p class="text-gray-600">
                Modifique as informações do bloco de provas
            </p>
        </div>

        <a href="<?= URL ?>/admin/provas" 
           class="text-gray-600 hover:text-gray-900">
            ← Voltar
        </a>
    </div>
</div>

<!-- Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="formBloco" onsubmit="atualizarBloco(event, <?= $bloco['id'] ?>)">
        <!-- Título -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Título do Bloco <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="titulo" 
                   name="titulo" 
                   required
                   value="<?= htmlspecialchars($bloco['titulo']) ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Ex: Prova Bimestral 1º Bimestre">
        </div>

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700">
                    Tipo de Avaliação <span class="text-red-500">*</span>
                </label>
                <a href="<?= URL ?>/admin/provas/tipos-avaliacao" target="_blank" class="text-xs text-purple-700 hover:text-purple-900">Gerenciar tipos</a>
            </div>
            <select id="tipo_avaliacao_id"
                    name="tipo_avaliacao_id"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="">Selecione</option>
                <?php $tipoAvaliacaoSel = (int)($bloco['tipo_avaliacao_id'] ?? 0); ?>
                <?php foreach (($tiposAvaliacao ?? []) as $tipo): ?>
                    <option value="<?= (int)$tipo['id'] ?>"
                            data-chave-quadro="<?= htmlspecialchars((string) ($tipo['chave_quadro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            <?= $tipoAvaliacaoSel === (int)$tipo['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-6" id="campo-semana-evento">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Semana no quadro
            </label>
            <?php $semanaSel = (int) ($bloco['semana'] ?? 0); ?>
            <select id="semana" name="semana" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="">Não se aplica</option>
                <?php for ($s = 1; $s <= 8; $s++): ?>
                    <option value="<?= $s ?>" <?= $semanaSel === $s ? 'selected' : '' ?>>S<?= $s ?></option>
                <?php endfor; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">Para prova semanal, escolha S1 a S8. Bloco A costuma ser S1/S3/S5/S7; Bloco B, S2/S4/S6/S8.</p>
        </div>
        <script>
        (function () {
            var sel = document.getElementById('tipo_avaliacao_id');
            var wrap = document.getElementById('campo-semana-evento');
            var semana = document.getElementById('semana');
            if (!sel || !wrap) return;
            function syncSemana() {
                var opt = sel.options[sel.selectedIndex];
                var chave = (opt && opt.getAttribute('data-chave-quadro')) || '';
                var hide = chave !== '' && chave !== 'semanal';
                wrap.classList.toggle('hidden', hide);
                if (hide && semana) semana.value = '';
            }
            sel.addEventListener('change', syncSemana);
            syncSemana();
        })();
        </script>

        <!-- Descrição -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Descrição
            </label>
            <textarea id="descricao" 
                      name="descricao" 
                      rows="3"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                      placeholder="Descrição opcional do bloco"><?= htmlspecialchars($bloco['descricao'] ?? '') ?></textarea>
        </div>

        <!-- Ano Letivo e Bimestre -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Ano Letivo <span class="text-red-500">*</span>
                </label>
                <input type="number"
                       id="ano_letivo"
                       name="ano_letivo"
                       min="2000"
                       max="2100"
                       required
                       value="<?= htmlspecialchars((string)($bloco['ano_letivo'] ?? date('Y'))) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex: <?= date('Y') ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Bimestre <span class="text-red-500">*</span>
                </label>
                <?php $bimSel = (int) ($bloco['bimestre'] ?? 0); ?>
                <select id="bimestre"
                        name="bimestre"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <option value="1" <?= $bimSel === 1 ? 'selected' : '' ?>>1º Bimestre</option>
                    <option value="2" <?= $bimSel === 2 ? 'selected' : '' ?>>2º Bimestre</option>
                    <option value="3" <?= $bimSel === 3 ? 'selected' : '' ?>>3º Bimestre</option>
                    <option value="4" <?= $bimSel === 4 ? 'selected' : '' ?>>4º Bimestre</option>
                </select>
            </div>
        </div>

        <?php
        $dataProvaVal = !empty($bloco['data_prova']) ? date('Y-m-d', strtotime((string)$bloco['data_prova'])) : '';
        $horaInicioVal = !empty($bloco['hora_inicio']) ? date('H:i', strtotime((string)$bloco['hora_inicio'])) : '';
        $horaFimVal = !empty($bloco['hora_fim']) ? date('H:i', strtotime((string)$bloco['hora_fim'])) : '';
        $prazoProfessorVal = !empty($bloco['prazo_entrega_professor']) ? date('Y-m-d\TH:i', strtotime((string)$bloco['prazo_entrega_professor'])) : '';
        ?>
        <?php
        $turmasSelecionadasIds = [];
        foreach (($bloco['turmas'] ?? []) as $turmaSel) {
            $tidSel = is_array($turmaSel) ? (int) ($turmaSel['id'] ?? 0) : (int) $turmaSel;
            if ($tidSel > 0) {
                $turmasSelecionadasIds[] = $tidSel;
            }
        }
        // Fallback para blocos antigos/legado: quando não existe vínculo em provas_blocos_turmas,
        // usa as turmas vinculadas por professor no bloco.
        if (empty($turmasSelecionadasIds) && !empty($bloco['professores']) && is_array($bloco['professores'])) {
            foreach ($bloco['professores'] as $profSel) {
                foreach (($profSel['turmas'] ?? []) as $turmaProfSel) {
                    $tidProfSel = is_array($turmaProfSel) ? (int) ($turmaProfSel['id'] ?? 0) : (int) $turmaProfSel;
                    if ($tidProfSel > 0) {
                        $turmasSelecionadasIds[] = $tidProfSel;
                    }
                }
            }
        }
        $turmasSelecionadasIds = array_values(array_unique($turmasSelecionadasIds));
        ?>
        <!-- Turmas do Bloco -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Turmas <span class="text-red-500">*</span>
            </label>
            <p class="text-sm text-gray-500 mb-3">Selecione as turmas que participarão deste bloco de provas:</p>
            <div class="border border-gray-200 rounded-lg p-4 max-h-64 overflow-y-auto bg-white">
                <?php if (!empty($turmas)): ?>
                    <?php foreach ($turmas as $turma): ?>
                        <?php $turmaId = (int) ($turma['id'] ?? 0); ?>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="checkbox"
                                   name="turmas[]"
                                   value="<?= $turmaId ?>"
                                   <?= in_array($turmaId, $turmasSelecionadasIds, true) ? 'checked' : '' ?>
                                   class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <div class="ml-2">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($turma['nome']) ?></div>
                                <?php if (!empty($turma['serie'])): ?>
                                    <div class="text-xs text-gray-500">Série: <?= htmlspecialchars($turma['serie']) ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-gray-500">Nenhuma turma disponível</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Professores e Matérias (Múltiplos) -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <label class="block text-sm font-medium text-gray-700">
                    Professores e Matérias <span class="text-red-500">*</span>
                </label>
                <button type="button" 
                        onclick="adicionarProfessor()"
                        class="btn-primary-custom px-4 py-2 text-sm font-semibold rounded-lg transition-colors hover:opacity-90">
                    + Adicionar Professor
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-4">Adicione um ou mais professores com suas matérias:</p>
            
            <div id="professoresContainer" class="space-y-4">
                <!-- Professores serão adicionados aqui via JavaScript -->
            </div>
        </div>

        <!-- Tipo de Prova -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Prova <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mb-2">Pedagógico: original ou substitutiva.</p>
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center">
                    <input type="radio" 
                           name="tipo_prova" 
                           value="original" 
                           <?= ($bloco['tipo_prova'] ?? 'original') === 'original' ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Original</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" 
                           name="tipo_prova" 
                           value="substitutiva"
                           <?= ($bloco['tipo_prova'] ?? '') === 'substitutiva' ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Substitutiva</span>
                </label>
            </div>
        </div>

        <?php $fmtEv = $bloco['formato_evento'] ?? 'online_questoes'; ?>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Evento <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mb-2">Em <strong>lançamento de notas</strong> não há prova com questões: a nota cheia é lançada por professor ou coordenação.</p>
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center">
                    <input type="radio" name="formato_evento" value="online_questoes" <?= $fmtEv !== 'lancamento_nota' ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Prova online</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="formato_evento" value="lancamento_nota" <?= $fmtEv === 'lancamento_nota' ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Lançamento de notas</span>
                </label>
            </div>
        </div>

        <!-- Responsável -->
        <?php
        $cfgNota = (string) ($bloco['configuracao_nota'] ?? 'professor_por_questao');
        $isFmtLanc = $fmtEv === 'lancamento_nota';
        ?>
        <div id="responsavelEventoBox" class="mb-6">
            <label id="labelResponsavelEvento" class="block text-sm font-medium text-gray-700 mb-2">
                <?= $isFmtLanc ? 'Quem lança a nota' : 'Quem elabora a prova' ?> <span class="text-red-500">*</span>
            </label>
            <p id="helpResponsavelEvento" class="text-xs text-gray-500 mb-2">
                <?= $isFmtLanc
                    ? 'Atribua ao professor ou deixe a coordenação lançar a nota cheia.'
                    : 'Atribua ao professor para ele criar as questões, ou a coordenação elabora a prova.' ?>
            </p>
            <?php
            $chkOnlineProf = !$isFmtLanc && ($cfgNota === 'professor_por_questao' || !in_array($cfgNota, ['professor_por_questao', 'coordenacao_calcula'], true));
            $chkOnlineCoord = !$isFmtLanc && $cfgNota === 'coordenacao_calcula';
            $chkLancCoord = $isFmtLanc && ($cfgNota === 'coordenacao_calcula' || !in_array($cfgNota, ['coordenacao_calcula', 'professor_por_questao'], true));
            $chkLancProf = $isFmtLanc && $cfgNota === 'professor_por_questao';
            ?>
            <div id="cfgOnlineOptions" class="flex flex-wrap gap-6 <?= $isFmtLanc ? 'hidden' : '' ?>">
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="professor_por_questao"
                           <?= $chkOnlineProf ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Atribuir professor</span>
                </label>
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="coordenacao_calcula"
                           <?= $chkOnlineCoord ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Coordenação faz a prova</span>
                </label>
            </div>
            <div id="cfgLancamentoOptions" class="flex flex-wrap gap-6 <?= $isFmtLanc ? '' : 'hidden' ?>">
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="professor_por_questao"
                           <?= $chkLancProf ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Atribuir ao professor</span>
                </label>
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="coordenacao_calcula"
                           <?= $chkLancCoord ? 'checked' : '' ?>
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Coordenação lança a nota</span>
                </label>
            </div>
        </div>

        <?php $notaUnicaTodasMaterias = !empty($bloco['nota_unica_todas_materias']); ?>
        <div id="notaUnicaTodasMateriasBox" class="mb-6 p-4 bg-violet-50 border border-violet-200 rounded-lg <?= $isFmtLanc ? '' : 'hidden' ?>">
            <label class="flex items-start cursor-pointer">
                <input type="checkbox"
                       id="nota_unica_todas_materias"
                       name="nota_unica_todas_materias"
                       value="1"
                       <?= $notaUnicaTodasMaterias ? 'checked' : '' ?>
                       class="mt-1 w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                <span class="ml-3">
                    <span class="block text-sm font-medium text-violet-900">Mesma nota em todas as matérias do evento</span>
                    <span class="block text-xs text-violet-800 mt-1">Use para ENAC: ao lançar a nota do aluno em uma matéria, o sistema replica a mesma nota para as demais matérias do evento.</span>
                </span>
            </label>
            <p id="msgCoordenacaoLanca" class="text-xs text-violet-900 mt-2 hidden">
                Coordenação lança notas: você pode usar nota única para aplicar a mesma nota do aluno em todas as matérias.
            </p>
        </div>

        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <?php
            $visAluno = isset($bloco['visivel_no_portal_aluno']) ? (int)$bloco['visivel_no_portal_aluno'] === 1 : true;
            ?>
            <label class="flex items-start cursor-pointer">
                <input type="checkbox"
                       id="visivel_no_portal_aluno"
                       name="visivel_no_portal_aluno"
                       value="1"
                       <?= $visAluno ? 'checked' : '' ?>
                       class="mt-1 w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <span class="ml-3">
                    <span class="block text-sm font-medium text-gray-900">Mostrar este evento no portal do aluno</span>
                    <span class="block text-xs text-gray-600 mt-1">Desmarcado: o aluno não vê em &quot;Minhas provas&quot; nem acessa por link. Útil para avaliações bimestrais só para coordenação/professor.</span>
                </span>
            </label>
        </div>

        <!-- Agenda de Prova (somente para prova online) -->
        <div id="agendamentoDataHoraContainer" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Data da Prova <span class="text-red-500">*</span>
                    </label>
                    <input type="date"
                           id="data_prova"
                           name="data_prova"
                           required
                           value="<?= htmlspecialchars($dataProvaVal) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Horário de Início <span class="text-red-500">*</span>
                    </label>
                    <input type="time"
                           id="hora_inicio"
                           name="hora_inicio"
                           required
                           value="<?= htmlspecialchars($horaInicioVal) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Horário de Término <span class="text-red-500">*</span>
                    </label>
                    <input type="time"
                           id="hora_fim"
                           name="hora_fim"
                           required
                           value="<?= htmlspecialchars($horaFimVal) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>
        </div>

        <!-- Prazo de Entrega do Professor (prova online e lançamento por professor) -->
        <div id="prazoProfessorContainer" class="hidden mb-6">
            <label id="labelPrazoProfessor" class="block text-sm font-medium text-gray-700 mb-2">
                Prazo para Professores Enviarem Provas <span class="text-red-500">*</span>
            </label>
            <input type="datetime-local"
                   id="prazo_entrega_professor"
                   name="prazo_entrega_professor"
                   value="<?= htmlspecialchars($prazoProfessorVal) ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <p class="text-xs text-gray-500 mt-1">Após este prazo, provas não enviadas serão automaticamente marcadas como "Não Enviadas" e travadas</p>
        </div>

        <!-- Botões -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/provas" 
               class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" 
                    class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90">
                Salvar Alterações
            </button>
        </div>
    </form>
</div>

<script>
const professores = <?= json_encode($professores ?? []) ?>;
const materias = <?= json_encode($materias ?? []) ?>;
const turmas = <?= json_encode($turmas ?? []) ?>;
const blocoProfessores = <?= json_encode($bloco['professores'] ?? []) ?>;
let professorCounter = 0;

function adicionarProfessor(professorData = null) {
    professorCounter++;
    const container = document.getElementById('professoresContainer');
    
    const professorDiv = document.createElement('div');
    professorDiv.className = 'border border-gray-300 rounded-lg p-4 bg-gray-50';
    professorDiv.id = `professor_${professorCounter}`;
    
    const profId = professorData ? (professorData.professor_id ?? '') : '';
    const matId = professorData ? (professorData.materia_id ?? '') : '';
    const materiaNome = professorData ? (professorData.materia_nome || '') : '';
    const quantidadeQuestoes = professorData
        ? (Number.isFinite(parseInt(professorData.quantidade_questoes, 10))
            ? Math.max(0, parseInt(professorData.quantidade_questoes, 10))
            : 5)
        : 5;
    const turmasProfessorSelecionadas = professorData && Array.isArray(professorData.turmas)
        ? professorData.turmas.map(t => parseInt((typeof t === 'object' && t !== null) ? (t.id || 0) : t, 10)).filter(id => id > 0)
        : [];
    
    professorDiv.innerHTML = `
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-semibold text-gray-700">Professor ${professorCounter}</h4>
            <button type="button" 
                    onclick="removerProfessor(${professorCounter})"
                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                ✕ Remover
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Professor <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][professor_id]" 
                        required
                        onchange="carregarMateriasProfessor(${professorCounter})"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione o professor</option>
                    ${professores.map(p => `
                        <option value="${p.id}"
                                data-materias='${JSON.stringify(Array.isArray(p.materias) ? p.materias : [])}'
                                ${Number(p.id) === Number(profId) ? 'selected' : ''}>
                            ${p.nome}
                        </option>
                    `).join('')}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Matéria <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][materia_id]" 
                        id="materia_${professorCounter}"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione primeiro o professor</option>
                </select>
            </div>
            <div class="campo-numero-questoes">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Quantidade de questões <span class="text-red-500">*</span>
                </label>
                <input type="number" name="professores[${professorCounter}][quantidade_questoes]" 
                       min="1" max="99" value="${quantidadeQuestoes}"
                       title="Número de questões que o professor deve criar para esta prova."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex: 5">
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between gap-3 mb-2">
                <label class="block text-sm font-medium text-gray-700">
                    Turmas deste professor <span class="text-red-500">*</span>
                </label>
                <button type="button"
                        onclick="marcarTurmasProfessor(${professorCounter})"
                        class="text-xs font-medium text-purple-700 hover:text-purple-900">
                    Marcar turmas do evento
                </button>
            </div>
            <div class="turmas-professor-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 border border-gray-200 rounded-lg bg-white p-3">
                ${turmasProfessorHtml(professorCounter, turmasProfessorSelecionadas)}
            </div>
            <p class="text-xs text-gray-500 mt-2">Selecione apenas as turmas em que este professor deve criar prova ou lançar nota.</p>
        </div>
    `;
    
    container.appendChild(professorDiv);
    atualizarCamposPassoProfessores();

    // Se há dados do professor do bloco, preenche matéria após o DOM estar pronto
    if (professorData && profId) {
        const currentCounter = professorCounter;
        const matIdStr = (matId != null && matId !== '' && String(matId) !== 'null') ? String(matId) : '';
        setTimeout(() => {
            carregarMateriasProfessor(currentCounter);
            const materiaSelect = document.getElementById(`materia_${currentCounter}`);
            if (materiaSelect && matIdStr) {
                const jaTemOpcao = Array.from(materiaSelect.options).some(opt => opt.value === matIdStr);
                if (!jaTemOpcao) {
                    const nome = materiaNome || (materias.find(m => Number(m.id) === Number(matId)) || {}).nome || 'Matéria';
                    const opt = document.createElement('option');
                    opt.value = matIdStr;
                    opt.textContent = nome;
                    materiaSelect.appendChild(opt);
                }
                materiaSelect.value = matIdStr;
            }
        }, 0);
    }
}

function removerProfessor(id) {
    const professorDiv = document.getElementById(`professor_${id}`);
    if (professorDiv) {
        professorDiv.remove();
    }
}

function carregarMateriasProfessor(professorIndex) {
    const professorSelect = document.querySelector(`select[name="professores[${professorIndex}][professor_id]"]`);
    const materiaSelect = document.getElementById(`materia_${professorIndex}`);
    
    if (!professorSelect || !materiaSelect) return;
    
    const selectedOption = professorSelect.options[professorSelect.selectedIndex];
    materiaSelect.innerHTML = '<option value="">Selecione a matéria</option>';
    
    if (!selectedOption.value) {
        return;
    }
    
    const materiasJson = selectedOption.getAttribute('data-materias');
    if (!materiasJson) {
        return;
    }
    
    try {
        const materiasProfessor = JSON.parse(materiasJson);
        if (!Array.isArray(materiasProfessor) || materiasProfessor.length === 0) return;

        let materiasFiltradas;
        if (typeof materiasProfessor[0] === 'object' && materiasProfessor[0] !== null) {
            // Array de objetos: extrai IDs
            const ids = materiasProfessor.map(m => Number(m.id ?? m.materia_id)).filter(id => id > 0);
            materiasFiltradas = materias.filter(m => ids.includes(Number(m.id)));
        } else {
            // Array de IDs numéricos/strings ou nomes
            const porId = typeof materiasProfessor[0] === 'number' || /^\d+$/.test(String(materiasProfessor[0]));
            materiasFiltradas = porId
                ? materias.filter(m => materiasProfessor.includes(Number(m.id)) || materiasProfessor.includes(String(m.id)))
                : materias.filter(m => materiasProfessor.includes(m.nome));
        }

        materiasFiltradas.forEach(materia => {
            const option = document.createElement('option');
            option.value = materia.id;
            option.textContent = materia.nome;
            materiaSelect.appendChild(option);
        });
    } catch (e) {
        console.error('Erro ao carregar matérias:', e);
    }
}

function getFormatoEvento() {
    return document.querySelector('input[name="formato_evento"]:checked')?.value || 'online_questoes';
}

function atribuirAoProfessor() {
    return (inputConfiguracaoNotaNoFormatoAtual()?.value || '') === 'professor_por_questao';
}

function exigeNumeroQuestoes() {
    return getFormatoEvento() === 'online_questoes' && atribuirAoProfessor();
}

function atualizarCamposPassoProfessores() {
    const showQtd = exigeNumeroQuestoes();
    document.querySelectorAll('.campo-numero-questoes').forEach(el => {
        el.classList.toggle('hidden', !showQtd);
        const input = el.querySelector('input');
        if (input) {
            input.required = showQtd;
            if (!showQtd) {
                input.removeAttribute('required');
            }
        }
    });
}

function inputConfiguracaoNotaNoFormatoAtual() {
    const formatoSel = document.querySelector('input[name="formato_evento"]:checked')?.value || 'online_questoes';
    const onlineBox = document.getElementById('cfgOnlineOptions');
    const lancBox = document.getElementById('cfgLancamentoOptions');
    const scope = formatoSel === 'lancamento_nota' ? lancBox : onlineBox;
    const inScope = scope?.querySelector('input[name="configuracao_nota"]:checked');
    if (inScope) {
        return inScope;
    }
    return document.querySelector('input[name="configuracao_nota"]:checked');
}

function ajustarOpcoesConfiguracaoNotaPorFormato() {
    const formatoSel = getFormatoEvento();
    const labelResp = document.getElementById('labelResponsavelEvento');
    const helpResp = document.getElementById('helpResponsavelEvento');
    const onlineBox = document.getElementById('cfgOnlineOptions');
    const lancBox = document.getElementById('cfgLancamentoOptions');
    const notaUnicaBox = document.getElementById('notaUnicaTodasMateriasBox');
    const msgCoord = document.getElementById('msgCoordenacaoLanca');
    const dataHoraBox = document.getElementById('agendamentoDataHoraContainer');
    const prazoBox = document.getElementById('prazoProfessorContainer');
    const dataProvaInput = document.getElementById('data_prova');
    const horaInicioInput = document.getElementById('hora_inicio');
    const horaFimInput = document.getElementById('hora_fim');
    const prazoInput = document.getElementById('prazo_entrega_professor');

    const cfgAnterior = document.querySelector('input[name="configuracao_nota"]:checked')?.value || '';

    if (formatoSel === 'lancamento_nota') {
        onlineBox.classList.add('hidden');
        lancBox.classList.remove('hidden');
        if (labelResp) labelResp.innerHTML = 'Quem lança a nota <span class="text-red-500">*</span>';
        if (helpResp) helpResp.textContent = 'Atribua ao professor ou deixe a coordenação lançar a nota cheia.';
        let cfgChecked = lancBox.querySelector('input[name="configuracao_nota"]:checked')?.value || '';
        if (!['coordenacao_calcula', 'professor_por_questao'].includes(cfgChecked)) {
            const prefer = ['coordenacao_calcula', 'professor_por_questao'].includes(cfgAnterior)
                ? cfgAnterior
                : 'professor_por_questao';
            const def = lancBox.querySelector(`input[name="configuracao_nota"][value="${prefer}"]`);
            if (def) def.checked = true;
        }
        onlineBox.querySelectorAll('input[name="configuracao_nota"]').forEach(el => { el.checked = false; });
    } else {
        onlineBox.classList.remove('hidden');
        lancBox.classList.add('hidden');
        if (labelResp) labelResp.innerHTML = 'Quem elabora a prova <span class="text-red-500">*</span>';
        if (helpResp) helpResp.textContent = 'Atribua ao professor para ele criar as questões, ou a coordenação elabora a prova.';
        notaUnicaBox.classList.add('hidden');
        if (msgCoord) msgCoord.classList.add('hidden');
        const notaUnicaInput = document.getElementById('nota_unica_todas_materias');
        if (notaUnicaInput) notaUnicaInput.checked = false;
        let cfgChecked = onlineBox.querySelector('input[name="configuracao_nota"]:checked')?.value || '';
        if (!['professor_por_questao', 'coordenacao_calcula'].includes(cfgChecked)) {
            const prefer = ['coordenacao_calcula', 'professor_por_questao'].includes(cfgAnterior)
                ? cfgAnterior
                : 'professor_por_questao';
            const def = onlineBox.querySelector(`input[name="configuracao_nota"][value="${prefer}"]`);
            if (def) def.checked = true;
        }
        lancBox.querySelectorAll('input[name="configuracao_nota"]').forEach(el => { el.checked = false; });
    }

    const cfgFinal = inputConfiguracaoNotaNoFormatoAtual()?.value || '';
    const showDataHora = (formatoSel === 'online_questoes');
    const showPrazo = cfgFinal === 'professor_por_questao';
    const showNotaUnica = (formatoSel === 'lancamento_nota' && cfgFinal === 'coordenacao_calcula');
    if (notaUnicaBox) notaUnicaBox.classList.toggle('hidden', !showNotaUnica);
    const notaUnicaInput = document.getElementById('nota_unica_todas_materias');
    if (notaUnicaInput && !showNotaUnica) notaUnicaInput.checked = false;
    if (dataHoraBox) dataHoraBox.classList.toggle('hidden', !showDataHora);
    if (prazoBox) prazoBox.classList.toggle('hidden', !showPrazo);
    if (dataProvaInput) dataProvaInput.required = showDataHora;
    if (horaInicioInput) horaInicioInput.required = showDataHora;
    if (horaFimInput) horaFimInput.required = showDataHora;
    if (prazoInput) prazoInput.required = showPrazo;
    if (msgCoord) msgCoord.classList.toggle('hidden', !showNotaUnica);
    const prazoLabel = document.getElementById('labelPrazoProfessor');
    if (prazoLabel) {
        prazoLabel.innerHTML = formatoSel === 'lancamento_nota'
            ? 'Prazo para o professor lançar as notas <span class="text-red-500">*</span>'
            : 'Prazo para Professores Enviarem Provas <span class="text-red-500">*</span>';
    }
    atualizarCamposPassoProfessores();
}

// Carrega professores existentes ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    if (blocoProfessores && blocoProfessores.length > 0) {
        blocoProfessores.forEach(prof => {
            adicionarProfessor(prof);
        });
    } else {
        // Se não há professores, adiciona um vazio
        adicionarProfessor();
    }
    manterAgendaNoFinalDoFormulario();
    ajustarOpcoesConfiguracaoNotaPorFormato();
    document.querySelectorAll('input[name="turmas[]"]').forEach(el => {
        el.addEventListener('change', sincronizarTurmasProfessoresComBloco);
    });
    document.querySelectorAll('input[name="formato_evento"]').forEach(el => {
        el.addEventListener('change', ajustarOpcoesConfiguracaoNotaPorFormato);
    });
    document.querySelectorAll('input[name="configuracao_nota"]').forEach(el => {
        el.addEventListener('change', ajustarOpcoesConfiguracaoNotaPorFormato);
    });
    garantirPrazoProfessorPreenchido();
});

function getTurmasBlocoSelecionadas() {
    return Array.from(document.querySelectorAll('input[name="turmas[]"]:checked'))
        .map(cb => parseInt(cb.value, 10))
        .filter(id => id > 0);
}

function turmasProfessorHtml(professorIndex, selecionadas = null) {
    const turmasBloco = getTurmasBlocoSelecionadas();
    const turmasSelecionadas = Array.isArray(selecionadas)
        ? selecionadas.map(id => parseInt(id, 10))
        : turmasBloco;
    const selecionadasSet = new Set(turmasSelecionadas);

    if (!Array.isArray(turmas) || turmas.length === 0) {
        return '<p class="text-sm text-gray-500">Nenhuma turma disponível</p>';
    }

    const turmasDoEvento = turmas.filter(t => turmasBloco.includes(parseInt(t.id, 10)));

    if (turmasDoEvento.length === 0) {
        return '<p class="text-sm text-gray-500 col-span-full">Selecione as turmas do evento para liberar as turmas deste professor.</p>';
    }

    return turmasDoEvento.map(t => {
        const turmaId = parseInt(t.id, 10);
        const checked = selecionadasSet.has(turmaId) ? 'checked' : '';
        const serie = t.serie ? `<span class="block text-xs text-gray-500">Série: ${t.serie}</span>` : '';
        return `
            <label class="flex items-start gap-2 p-2 rounded border border-gray-100 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox"
                       name="professores[${professorIndex}][turmas][]"
                       value="${turmaId}"
                       data-turma-id="${turmaId}"
                       class="turma-professor-checkbox mt-1 w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-800">${t.nome}</span>
                    ${serie}
                </span>
            </label>
        `.replace('value="' + turmaId + '"', 'value="' + turmaId + '" ' + checked);
    }).join('');
}

function marcarTurmasProfessor(professorIndex) {
    const selecionadas = new Set(getTurmasBlocoSelecionadas());
    document.querySelectorAll(`#professor_${professorIndex} .turma-professor-checkbox`).forEach(cb => {
        const turmaId = parseInt(cb.dataset.turmaId || cb.value, 10);
        cb.checked = selecionadas.has(turmaId);
    });
}

function sincronizarTurmasProfessoresComBloco() {
    const selecionadas = new Set(getTurmasBlocoSelecionadas());
    document.querySelectorAll('[id^="professor_"]').forEach(div => {
        const professorIndex = parseInt(div.id.replace('professor_', ''), 10);
        const checks = Array.from(div.querySelectorAll('.turma-professor-checkbox'));
        const turmasVisiveisAntes = new Set(checks.map(cb => parseInt(cb.dataset.turmaId || cb.value, 10)));
        const turmasMarcadas = checks
            .filter(cb => cb.checked)
            .map(cb => parseInt(cb.dataset.turmaId || cb.value, 10))
            .filter(id => selecionadas.has(id));

        selecionadas.forEach(id => {
            if (!turmasVisiveisAntes.has(id)) {
                turmasMarcadas.push(id);
            }
        });

        const grid = div.querySelector('.turmas-professor-grid');
        if (grid && professorIndex > 0) {
            grid.innerHTML = turmasProfessorHtml(professorIndex, checks.length > 0 ? turmasMarcadas : null);
        }
    });
}

function manterAgendaNoFinalDoFormulario() {
    const form = document.getElementById('formBloco');
    if (!form) return;
    const botoes = form.querySelector('.flex.justify-end.space-x-4');
    const dataHoraBox = document.getElementById('agendamentoDataHoraContainer');
    const prazoBox = document.getElementById('prazoProfessorContainer');
    if (!botoes || !dataHoraBox || !prazoBox) return;
    form.insertBefore(dataHoraBox, botoes);
    form.insertBefore(prazoBox, botoes);
}

function garantirPrazoProfessorPreenchido() {
    const prazoInput = document.getElementById('prazo_entrega_professor');
    if (!prazoInput || (prazoInput.value && prazoInput.value.trim() !== '')) {
        return;
    }
    const dataProva = document.getElementById('data_prova')?.value || '';
    const horaFim = document.getElementById('hora_fim')?.value || '';
    const horaInicio = document.getElementById('hora_inicio')?.value || '';
    const hora = horaFim || horaInicio;
    if (dataProva && hora) {
        prazoInput.value = `${dataProva}T${hora}`;
    }
}

function atualizarBloco(event, blocoId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Coleta turmas gerais do bloco
    const turmasIds = [];
    form.querySelectorAll('input[name="turmas[]"]:checked').forEach(checkbox => {
        turmasIds.push(parseInt(checkbox.value, 10));
    });
    if (turmasIds.length === 0) {
        alert('Selecione pelo menos uma turma para o bloco');
        return;
    }
    const turmasBlocoSet = new Set(turmasIds);

    // Coleta professores com suas matérias
    const professores = [];
    const professorDivs = document.querySelectorAll('[id^="professor_"]');
    let professoresInvalidos = false;
    let turmasProfessorInvalidas = false;
    
    if (professorDivs.length === 0) {
        alert('Adicione pelo menos um professor');
        return;
    }
    
    professorDivs.forEach(div => {
        const professorId = div.querySelector('select[name*="[professor_id]"]')?.value;
        const materiaId = div.querySelector('select[name*="[materia_id]"]')?.value;
        if (!professorId || !materiaId) {
            professoresInvalidos = true;
            return;
        }
        const qtdQuestoesInput = div.querySelector('input[name*="[quantidade_questoes]"]');
        const quantidadeQuestoes = exigeNumeroQuestoes()
            ? (qtdQuestoesInput ? (parseInt(qtdQuestoesInput.value, 10) || 0) : 0)
            : 0;
        if (exigeNumeroQuestoes() && quantidadeQuestoes < 1) {
            professoresInvalidos = true;
            return;
        }
        const turmasProfessor = Array.from(div.querySelectorAll('.turma-professor-checkbox:checked'))
            .map(cb => parseInt(cb.value, 10))
            .filter(id => id > 0);
        if (turmasProfessor.length === 0) {
            turmasProfessorInvalidas = true;
            return;
        }
        
        professores.push({
            professor_id: parseInt(professorId),
            materia_id: parseInt(materiaId),
            quantidade_questoes: exigeNumeroQuestoes() ? Math.max(1, quantidadeQuestoes) : 0,
            turmas: turmasProfessor
        });
    });
    if (professoresInvalidos) {
        alert(exigeNumeroQuestoes()
            ? 'Preencha professor, matéria e quantidade de questões para todos os professores adicionados'
            : 'Preencha professor e matéria para todos os professores adicionados');
        return;
    }
    if (turmasProfessorInvalidas) {
        alert('Selecione pelo menos uma turma para cada professor');
        return;
    }
    if (professores.some(prof => prof.turmas.some(turmaId => !turmasBlocoSet.has(turmaId)))) {
        alert('As turmas de cada professor precisam estar dentro das turmas selecionadas para o evento');
        return;
    }
    
    const formatoEvInput = form.querySelector('input[name="formato_evento"]:checked');
    const tipoProvaInput = form.querySelector('input[name="tipo_prova"]:checked');
    const configNotaInput = inputConfiguracaoNotaNoFormatoAtual();
    const bimestreEl = document.getElementById('bimestre');
    const bimestreVal = (bimestreEl && bimestreEl.value) ? parseInt(bimestreEl.value, 10) : (formData.get('bimestre') ? parseInt(formData.get('bimestre'), 10) : null);

    const data = {
        titulo: formData.get('titulo'),
        descricao: formData.get('descricao') || null,
        ano_letivo: formData.get('ano_letivo') ? parseInt(formData.get('ano_letivo'), 10) : null,
        bimestre: bimestreVal,
        tipo_avaliacao_id: formData.get('tipo_avaliacao_id') ? parseInt(formData.get('tipo_avaliacao_id'), 10) : null,
        semana: formData.get('semana') ? parseInt(formData.get('semana'), 10) : null,
        turmas: turmasIds,
        professores: professores,
        data_prova: formData.get('data_prova') || null,
        hora_inicio: formData.get('hora_inicio') || null,
        hora_fim: formData.get('hora_fim') || null,
        prazo_entrega_professor: formData.get('prazo_entrega_professor') || null,
        tipo_prova: tipoProvaInput ? tipoProvaInput.value : (formData.get('tipo_prova') || 'original'),
        formato_evento: formatoEvInput ? formatoEvInput.value : (formData.get('formato_evento') || 'online_questoes'),
        configuracao_nota: configNotaInput ? configNotaInput.value : formData.get('configuracao_nota'),
        liberar_gabarito: 'imediatamente',
        liberado: <?= (int)($bloco['liberado'] ?? 0) ?>,
        visivel_no_portal_aluno: document.getElementById('visivel_no_portal_aluno')?.checked ? 1 : 0,
        nota_unica_todas_materias: document.getElementById('nota_unica_todas_materias')?.checked ? 1 : 0
    };
    
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= URL ?>/admin/provas';
        } else {
            let msg = data.error || 'Erro desconhecido';
            if (data.missing_columns && data.missing_columns.length) {
                msg += '\n\nColunas faltando no banco: ' + data.missing_columns.join(', ');
            }
            if (data.errors && typeof data.errors === 'object') {
                const detalhes = Object.values(data.errors)
                    .map(v => String(v || '').trim())
                    .filter(v => v !== '');
                if (detalhes.length > 0) {
                    msg += '\n\n' + detalhes.join('\n');
                }
            }
            alert(msg);
            if (data.errors) {
                console.error('Erros:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar bloco');
    });
}
</script>
