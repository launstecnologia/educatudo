<?php
/**
 * Dev Settings - Custos LLM
 * Contabilidade de tokens e custo por período. Apenas perfil dev.
 */
$report = $report ?? [
    'total_tokens' => 0,
    'total_cost' => 0.0,
    'by_day' => [],
    'by_model' => [],
    'by_usage_type' => [],
    'date_start' => $date_start ?? date('Y-m-d', strtotime('-30 days')),
    'date_end' => $date_end ?? date('Y-m-d')
];
$dateStart = $date_start ?? $report['date_start'];
$dateEnd = $date_end ?? $report['date_end'];
$byUsageType = $report['by_usage_type'] ?? [];
$usageTypeLabels = [
    'exercicios' => 'Exercícios por IA (aluno/jornada)',
    'exercicios_personalizados' => 'Exercícios personalizados (lista)',
    'prova' => 'Prova / questões por IA (professor)',
    'chat' => 'Conversa Chat (Tudinha)',
    'chat_professor' => 'Chat Professor',
    'chat_completion' => 'Chat / Conversa',
    'correcao_redacao' => 'Correção Redação',
    'gerar_tema' => 'Gerar tema redação',
    'general' => 'Outros (geral)',
];
?>
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Custos LLM</h2>
            <p class="text-sm text-gray-600 mt-1">Valor total e quantidade de tokens (pergunta + resposta) por período. Dados vêm do banco (tabela <code>logs_uso_llm</code>): cada chamada à API OpenAI é registrada (exercícios IA, correção redação, chat, provas, etc.).</p>
            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-sm text-amber-800 font-medium mb-1">Importar dados históricos do banco</p>
                <p class="text-xs text-amber-700 mb-2">Conversas do chat professor (<code>professores_ia_mensagens</code>) e mensagens da IA do chat aluno (<code>tudinha_mensagens</code> com is_ia=1) podem ser importadas para calcular custos estimados. Execute a migração <code>20260205_000003_logs_uso_llm_add_source.sql</code> antes.</p>
                <form method="post" action="<?= URL ?>/admin/dev-settings/custos-llm/importar-banco" class="inline" onsubmit="return confirm('Isso vai adicionar registros de custo a partir das conversas e mensagens de IA já salvas no banco. Continuar?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button type="submit" class="px-3 py-1.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-medium">Importar do banco (conversas e mensagens de IA)</button>
                </form>
            </div>
            <div class="mt-3 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                <p class="text-sm text-indigo-800 font-medium mb-1">Processar data específica</p>
                <p class="text-xs text-indigo-700 mb-2">Escolha uma data (ex.: ontem ou hoje). O sistema lê do banco as conversas e mensagens de IA daquela data, estima tokens e custo, e registra ou atualiza em <code>logs_uso_llm</code>. O mesmo que o cron da meia-noite, mas para a data que você quiser.</p>
                <form method="post" action="<?= URL ?>/admin/dev-settings/custos-llm/processar-data" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <label class="flex flex-col">
                        <span class="text-xs text-indigo-700 mb-0.5">Data</span>
                        <input type="date" name="data_processar" value="<?= htmlspecialchars(date('Y-m-d')) ?>" class="border border-indigo-300 rounded-lg px-3 py-1.5 text-sm">
                    </label>
                    <button type="submit" class="btn-primary-custom px-3 py-1.5 rounded-lg text-sm font-medium hover:opacity-90">Processar e registrar/atualizar custos para esta data</button>
                </form>
            </div>
        </div>
        <div class="p-6">
            <!-- Filtros -->
            <form method="get" action="<?= URL ?>/admin/dev-settings/custos-llm" class="flex flex-wrap items-end gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($dateStart) ?>"
                           class="border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($dateEnd) ?>"
                           class="border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                        Aplicar Filtros
                    </button>
                    <a href="<?= URL ?>/admin/dev-settings/custos-llm" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
                        Limpar Filtros
                    </a>
                </div>
            </form>

            <!-- Resumo: Valor total e Total de tokens -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-100">
                    <p class="text-sm font-medium text-indigo-800">Valor total (USD)</p>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">$<?= number_format($report['total_cost'], 4, ',', '') ?></p>
                    <p class="text-xs text-indigo-600 mt-1">Período: <?= htmlspecialchars($report['date_start']) ?> a <?= htmlspecialchars($report['date_end']) ?></p>
                </div>
                <div class="bg-purple-50 rounded-lg p-4 border border-purple-100">
                    <p class="text-sm font-medium text-purple-800">Total de tokens</p>
                    <p class="text-2xl font-bold text-purple-600 mt-1"><?= number_format($report['total_tokens'], 0, ',', '.') ?></p>
                    <p class="text-xs text-purple-600 mt-1">Pergunta + resposta (input + output)</p>
                </div>
            </div>

            <!-- Por tipo de uso (Exercícios IA, Chat, Redação, Prova, etc.) -->
            <?php if (!empty($byUsageType)): ?>
            <div class="mb-6">
                <h3 class="text-md font-semibold text-gray-900 mb-2">Por tipo de uso</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tokens</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Requisições</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Custo (USD)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($byUsageType as $tipo => $data): ?>
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($usageTypeLabels[$tipo] ?? $tipo) ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right"><?= number_format($data['tokens'] ?? 0, 0, ',', '.') ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right"><?= (int)($data['requests'] ?? 0) ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right">$<?= number_format($data['cost'] ?? 0, 4, ',', '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Por modelo (versão do LLM) -->
            <?php if (!empty($report['by_model'])): ?>
            <div class="mb-6">
                <h3 class="text-md font-semibold text-gray-900 mb-2">Por modelo (versão do LLM)</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Modelo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tokens</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Requisições</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Custo (USD)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($report['by_model'] as $model => $data): ?>
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($model) ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right"><?= number_format($data['tokens'] ?? 0, 0, ',', '.') ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right"><?= (int)($data['requests'] ?? 0) ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right">$<?= number_format($data['cost'] ?? 0, 4, ',', '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Por dia (opcional, se houver muitos dias pode colapsar) -->
            <?php if (!empty($report['by_day'])): ?>
            <div>
                <h3 class="text-md font-semibold text-gray-900 mb-2">Por dia</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg max-h-64 overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tokens</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Custo (USD)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach (array_reverse($report['by_day']) as $row): ?>
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($row['date']) ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right"><?= number_format($row['tokens'], 0, ',', '.') ?></td>
                                <td class="px-4 py-2 text-sm text-gray-600 text-right">$<?= number_format($row['cost'], 4, ',', '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($report['by_model']) && $report['total_tokens'] === 0): ?>
            <p class="text-sm text-gray-500 mt-4">Nenhum uso de LLM registrado no período. Execute a migração <code>20260205_000002_create_logs_uso_llm.sql</code> para criar a tabela; a partir daí cada chamada à API (exercícios IA, correção redação, chat, provas, etc.) será gravada no banco e aparecerá aqui. Chat em tempo real (streaming) pode não enviar usage no último chunk e ficar de fora até implementarmos estimativa.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex">
        <a href="<?= URL ?>/admin/dev" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">← Voltar para Dev Settings</a>
    </div>
</div>
