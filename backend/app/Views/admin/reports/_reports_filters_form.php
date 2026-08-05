<?php
/**
 * Formulário GET de filtros dos relatórios admin.
 * @var string $reports_filter_tab jornadas|redacao
 * @var bool $reports_filter_jornadas_extended Se true, exibe filtros específicos de jornadas; senão, preserva via hidden.
 */
$ext = !empty($reports_filter_jornadas_extended);
$titulo = 'Filtros do relatório de Jornadas';
$jrHiddenKeys = ['jr_ano_letivo', 'jr_bimestre', 'jr_turma_ano_letivo', 'jr_professor_id', 'jr_materia_id', 'jr_jornada_id', 'jr_avaliativo', 'jr_tempo_ordem'];
$formAction = $reports_filter_form_action ?? (URL . '/admin/reports');
$clearUrl = $reports_filter_clear_url ?? (URL . '/admin/reports');
?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6 reports-filter-wrap">
    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?= htmlspecialchars($titulo) ?></h3>
    <form method="GET" action="<?= htmlspecialchars($formAction) ?>" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <input type="hidden" name="executar" value="1">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Relatório</label>
            <select name="tipo" class="reports-filter-tipo w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="geral" <?= ($filtros['tipo'] ?? '') === 'geral' ? 'selected' : '' ?>>Geral</option>
                <option value="turma" <?= ($filtros['tipo'] ?? '') === 'turma' ? 'selected' : '' ?>>Por Turma</option>
                <option value="usuario" <?= ($filtros['tipo'] ?? '') === 'usuario' ? 'selected' : '' ?>>Por Usuário</option>
            </select>
        </div>

        <div class="reports-filter-turma-wrap" style="<?= ($filtros['tipo'] ?? '') === 'turma' ? '' : 'display:none' ?>">
            <label class="block text-sm font-medium text-gray-700 mb-2">Selecionar Turma</label>
            <select name="turma_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="">Todas as turmas</option>
                <?php foreach ($turmas as $turma): ?>
                    <option value="<?= $turma['id'] ?>" <?= ($filtros['turma_id'] ?? '') == $turma['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($turma['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="reports-filter-usuario-wrap" style="<?= ($filtros['tipo'] ?? '') === 'usuario' ? '' : 'display:none' ?>">
            <label class="block text-sm font-medium text-gray-700 mb-2">Nome do aluno</label>
            <input type="text" name="aluno_nome" list="alunos-sugestoes" value="<?= htmlspecialchars($filtros['aluno_nome'] ?? '') ?>"
                   placeholder="Digite o nome do aluno"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <datalist id="alunos-sugestoes">
                <?php foreach ($alunos as $aluno): ?>
                    <option value="<?= htmlspecialchars($aluno['nome']) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($filtros['data_fim'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Limite por página</label>
            <?php
            $limitAtual = (int) ($filtros['limit'] ?? 25);
            $limitOptions = [10, 25, 50, 100, 200, 500];
            if ($limitAtual > 0 && !in_array($limitAtual, $limitOptions, true)) {
                $limitOptions[] = $limitAtual;
            }
            sort($limitOptions);
            ?>
            <select name="limit" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <?php foreach ($limitOptions as $opt): ?>
                    <option value="<?= (int) $opt ?>" <?= $limitAtual === (int) $opt ? 'selected' : '' ?>><?= (int) $opt ?> por página</option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" name="page" value="1">

        <?php if ($ext): ?>
            <div class="col-span-full border-t border-gray-200 pt-4 mt-2">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Refino por jornada</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ano letivo (jornada / turma base)</label>
                        <select name="jr_ano_letivo" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Todos</option>
                            <?php foreach ($anos_turmas_rel ?? [] as $ar): ?>
                                <option value="<?= (int)($ar['ano_letivo'] ?? 0) ?>" <?= (string)($filtros['jr_ano_letivo'] ?? '') === (string)($ar['ano_letivo'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($ar['ano_letivo'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bimestre (jornada)</label>
                        <select name="jr_bimestre" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Todos</option>
                            <?php foreach ([1, 2, 3, 4] as $bi): ?>
                                <option value="<?= $bi ?>" <?= (string)($filtros['jr_bimestre'] ?? '') === (string)$bi ? 'selected' : '' ?>><?= $bi ?>º</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ano letivo da turma (matriz)</label>
                        <select name="jr_turma_ano_letivo" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Todos</option>
                            <?php foreach ($anos_turmas_rel ?? [] as $ar): ?>
                                <option value="<?= (int)($ar['ano_letivo'] ?? 0) ?>" <?= (string)($filtros['jr_turma_ano_letivo'] ?? '') === (string)($ar['ano_letivo'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($ar['ano_letivo'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (empty($reports_filter_hide_professor)): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Professor</label>
                        <select name="jr_professor_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Todos</option>
                            <?php foreach ($professores_jornadas_rel ?? [] as $pr): ?>
                                <option value="<?= (int)$pr['id'] ?>" <?= (string)($filtros['jr_professor_id'] ?? '') === (string)$pr['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pr['nome'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Matéria (jornada)</label>
                        <select name="jr_materia_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Todas</option>
                            <?php foreach ($materias_jornadas_rel ?? [] as $mat): ?>
                                <option value="<?= (int)$mat['id'] ?>" <?= (string)($filtros['jr_materia_id'] ?? '') === (string)$mat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mat['nome'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jornada específica</label>
                        <select name="jr_jornada_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">Todas (limite de recência no escopo — ver documentação)</option>
                            <?php foreach ($jornadas_select_rel ?? [] as $jo): ?>
                                <option value="<?= (int)$jo['id'] ?>" <?= (string)($filtros['jr_jornada_id'] ?? '') === (string)$jo['id'] ? 'selected' : '' ?>>
                                    #<?= (int)$jo['id'] ?> — <?= htmlspecialchars($jo['titulo'] ?? '') ?> (<?= htmlspecialchars($jo['turma_nome'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Avaliativa</label>
                        <select name="jr_avaliativo" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="" <?= ($filtros['jr_avaliativo'] ?? '') === '' ? 'selected' : '' ?>>Todas</option>
                            <option value="1" <?= (string)($filtros['jr_avaliativo'] ?? '') === '1' ? 'selected' : '' ?>>Sim</option>
                            <option value="0" <?= (string)($filtros['jr_avaliativo'] ?? '') === '0' ? 'selected' : '' ?>>Não</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tempo de execução (jornadas)</label>
                        <select name="jr_tempo_ordem" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent" title="Soma do tempo registrado (tempo_gasto) nas jornadas do escopo">
                            <option value="" <?= ($filtros['jr_tempo_ordem'] ?? '') === '' ? 'selected' : '' ?>>Ordenação padrão (taxa de conclusão)</option>
                            <option value="rapido" <?= ($filtros['jr_tempo_ordem'] ?? '') === 'rapido' ? 'selected' : '' ?>>Quem foi mais rápido (menos tempo total)</option>
                            <option value="lento" <?= ($filtros['jr_tempo_ordem'] ?? '') === 'lento' ? 'selected' : '' ?>>Quem demorou mais (mais tempo total)</option>
                        </select>
                    </div>
                    <div class="reports-filter-modo-materia-wrap" style="<?= ($filtros['tipo'] ?? '') === 'usuario' ? '' : 'display:none' ?>">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Questões por matéria (ao pesquisar aluno)</label>
                        <select name="jr_modo_materia" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="total" <?= ($filtros['jr_modo_materia'] ?? 'total') === 'total' ? 'selected' : '' ?>>Somar tudo</option>
                            <option value="por_materia" <?= ($filtros['jr_modo_materia'] ?? '') === 'por_materia' ? 'selected' : '' ?>>Trazer por matéria</option>
                        </select>
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="jr_somente_atencao" value="1" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= !empty($filtros['jr_somente_atencao']) ? 'checked' : '' ?>>
                            Listar só alunos em atenção
                        </label>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($jrHiddenKeys as $jk): ?>
                <?php if (($filtros[$jk] ?? '') !== '' && ($filtros[$jk] ?? '') !== null): ?>
                    <input type="hidden" name="<?= htmlspecialchars($jk) ?>" value="<?= htmlspecialchars((string)$filtros[$jk]) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!empty($filtros['aluno_nome'])): ?>
                <input type="hidden" name="aluno_nome" value="<?= htmlspecialchars((string) $filtros['aluno_nome']) ?>">
            <?php endif; ?>
            <?php if (!empty($filtros['jr_modo_materia'])): ?>
                <input type="hidden" name="jr_modo_materia" value="<?= htmlspecialchars((string) $filtros['jr_modo_materia']) ?>">
            <?php endif; ?>
            <?php if (!empty($filtros['jr_somente_atencao'])): ?>
                <input type="hidden" name="jr_somente_atencao" value="1">
            <?php endif; ?>
        <?php endif; ?>

        <div class="col-span-full flex flex-wrap gap-4 mt-4">
            <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                🔍 Aplicar filtros
            </button>
            <a href="<?= htmlspecialchars($clearUrl) ?>" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors inline-flex items-center">
                🗑️ Limpar tudo
            </a>
        </div>
    </form>
</div>
