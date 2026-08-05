<?php /** @var array $config @var string $csrf_token */ ?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Configurações Financeiras</h2>
            <p class="text-sm text-gray-600">Regras de cobrança, encargos e comunicação.</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
    <form method="POST" action="<?= URL ?>/admin/finance/settings" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Encargos por Atraso</h3>
                <p class="mt-1 text-sm text-gray-500">Valores aplicados quando o pagamento não ocorre até o vencimento.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="multa_atraso" class="block text-sm font-medium text-gray-700 mb-2">Multa por Atraso (%)</label>
                    <input type="number" id="multa_atraso" name="multa_atraso" step="0.01" min="0" max="100"
                           value="<?= $config['multa_atraso'] ?? 2.00 ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="mt-1 text-xs text-gray-500">Aplicada uma vez quando vencer</p>
                </div>
                <div>
                    <label for="juros_mensal" class="block text-sm font-medium text-gray-700 mb-2">Juros Mensal (%)</label>
                    <input type="number" id="juros_mensal" name="juros_mensal" step="0.01" min="0" max="100"
                           value="<?= $config['juros_mensal'] ?? 1.00 ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="mt-1 text-xs text-gray-500">Aplicado por mês de atraso</p>
                </div>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Desconto Pontualidade</h3>
                <p class="mt-1 text-sm text-gray-500">Desconto concedido a quem pagar antes do prazo.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="desconto_pontualidade_pct" class="block text-sm font-medium text-gray-700 mb-2">% de Desconto por Pontualidade</label>
                    <input type="number" id="desconto_pontualidade_pct" name="desconto_pontualidade_pct" step="0.01" min="0" max="100"
                           value="<?= $config['desconto_pontualidade_pct'] ?? 0.00 ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="mt-1 text-xs text-gray-500">% de desconto para quem pagar até o dia configurado</p>
                </div>
                <div>
                    <label for="desconto_pontualidade_dia" class="block text-sm font-medium text-gray-700 mb-2">Pagar até o dia X do mês</label>
                    <input type="number" id="desconto_pontualidade_dia" name="desconto_pontualidade_dia" min="1" max="31"
                           value="<?= $config['desconto_pontualidade_dia'] ?? 5 ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="mt-1 text-xs text-gray-500">Pagar até este dia do mês para obter o desconto</p>
                </div>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Vencimento Padrão</h3>
                <p class="mt-1 text-sm text-gray-500">Configurações de prazo e carência para novos contratos.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="dia_vencimento_padrao" class="block text-sm font-medium text-gray-700 mb-2">Dia de Vencimento Padrão</label>
                    <input type="number" id="dia_vencimento_padrao" name="dia_vencimento_padrao" min="1" max="31"
                           value="<?= $config['dia_vencimento_padrao'] ?? 10 ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="dias_carencia" class="block text-sm font-medium text-gray-700 mb-2">Dias de Carência</label>
                    <input type="number" id="dias_carencia" name="dias_carencia" min="0" max="30"
                           value="<?= $config['dias_carencia'] ?? 0 ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="mt-1 text-xs text-gray-500">Dias após vencimento antes de cobrar encargos</p>
                </div>
            </div>
        </section>

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Boleto e E-mail</h3>
                <p class="mt-1 text-sm text-gray-500">Informações exibidas em documentos e comunicações enviadas ao responsável.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nome_escola_boleto" class="block text-sm font-medium text-gray-700 mb-2">Nome da Escola no Boleto</label>
                    <input type="text" id="nome_escola_boleto" name="nome_escola_boleto"
                           placeholder="Nome exibido no boleto simulado"
                           value="<?= htmlspecialchars((string)($config['nome_escola_boleto'] ?? '')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="email_remetente" class="block text-sm font-medium text-gray-700 mb-2">E-mail Remetente</label>
                    <input type="email" id="email_remetente" name="email_remetente"
                           placeholder="financeiro@escola.com"
                           value="<?= htmlspecialchars((string)($config['email_remetente'] ?? '')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="gerar_debito_auto" id="gerar_debito_auto" value="1"
                               <?= !empty($config['gerar_debito_auto']) ? 'checked' : '' ?>
                               class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">Gerar débito automático</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Gerar débito no extrato na data de vencimento automaticamente.</span>
                        </span>
                    </label>
                </div>
            </div>
        </section>

        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="<?= URL ?>/admin/finance"
                   class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-check mr-2"></i>
                    Salvar Configurações
                </button>
            </div>
        </div>
    </form>
</div>
