<?php
$ficha = is_array($ficha ?? null) ? $ficha : [];
$escf = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tipoSang = trim((string) ($ficha['tipo_sanguineo'] ?? ''));
$tiposSanguineos = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$transporteTipo = trim((string) ($ficha['transporte_tipo'] ?? ''));
$usaTransporte = !empty($ficha['usa_transporte_escolar']);
$inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';
?>
<div class="p-6 space-y-6">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">Saúde</h3>
        <p class="text-sm text-gray-500">Informações de saúde usadas em emergências e pelo cuidado pedagógico.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="tipo_sanguineo" class="block text-sm font-medium text-gray-700 mb-2">Tipo sanguíneo</label>
            <select id="tipo_sanguineo" name="tipo_sanguineo" class="<?= $inputClass ?>">
                <option value="">Não informado</option>
                <?php foreach ($tiposSanguineos as $ts): ?>
                <option value="<?= $escf($ts) ?>" <?= $tipoSang === $ts ? 'selected' : '' ?>><?= $escf($ts) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="plano_saude" class="block text-sm font-medium text-gray-700 mb-2">Plano de saúde</label>
            <input type="text" id="plano_saude" name="plano_saude" maxlength="120" class="<?= $inputClass ?>"
                   value="<?= $escf($ficha['plano_saude'] ?? '') ?>" placeholder="Operadora do plano">
        </div>
        <div>
            <label for="plano_saude_numero" class="block text-sm font-medium text-gray-700 mb-2">Nº da carteirinha</label>
            <input type="text" id="plano_saude_numero" name="plano_saude_numero" maxlength="60" class="<?= $inputClass ?>"
                   value="<?= $escf($ficha['plano_saude_numero'] ?? '') ?>">
        </div>
        <div>
            <label for="hospital_referencia" class="block text-sm font-medium text-gray-700 mb-2">Hospital de referência</label>
            <input type="text" id="hospital_referencia" name="hospital_referencia" maxlength="160" class="<?= $inputClass ?>"
                   value="<?= $escf($ficha['hospital_referencia'] ?? '') ?>">
        </div>
        <div class="md:col-span-2">
            <label for="alergias" class="block text-sm font-medium text-gray-700 mb-2">Alergias</label>
            <textarea id="alergias" name="alergias" rows="2" class="<?= $inputClass ?>"
                      placeholder="Medicamentos, alimentos, picadas..."><?= $escf($ficha['alergias'] ?? '') ?></textarea>
        </div>
        <div class="md:col-span-2">
            <label for="medicamentos_uso" class="block text-sm font-medium text-gray-700 mb-2">Medicamentos de uso contínuo</label>
            <textarea id="medicamentos_uso" name="medicamentos_uso" rows="2" class="<?= $inputClass ?>"
                      placeholder="Nome, dose e horário"><?= $escf($ficha['medicamentos_uso'] ?? '') ?></textarea>
        </div>
        <div class="md:col-span-2">
            <label for="condicoes_cronicas" class="block text-sm font-medium text-gray-700 mb-2">Condições crônicas / cuidados especiais</label>
            <textarea id="condicoes_cronicas" name="condicoes_cronicas" rows="2" class="<?= $inputClass ?>"
                      placeholder="Asma, diabetes, epilepsia..."><?= $escf($ficha['condicoes_cronicas'] ?? '') ?></textarea>
        </div>
        <div class="md:col-span-2">
            <label for="deficiencias_obs" class="block text-sm font-medium text-gray-700 mb-2">Acessibilidade / deficiência (observação)</label>
            <textarea id="deficiencias_obs" name="deficiencias_obs" rows="2" class="<?= $inputClass ?>"
                      placeholder="Resumo. O plano de acessibilidade (AEE) é gerenciado no EducaInclui."><?= $escf($ficha['deficiencias_obs'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="pt-2">
        <h4 class="text-base font-semibold text-gray-900 mb-4">Contato de emergência</h4>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="contato_emergencia_nome" class="block text-sm font-medium text-gray-700 mb-2">Nome</label>
                <input type="text" id="contato_emergencia_nome" name="contato_emergencia_nome" maxlength="160" class="<?= $inputClass ?>"
                       value="<?= $escf($ficha['contato_emergencia_nome'] ?? '') ?>">
            </div>
            <div>
                <label for="contato_emergencia_telefone" class="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
                <input type="text" id="contato_emergencia_telefone" name="contato_emergencia_telefone" inputmode="tel" maxlength="16"
                       class="<?= $inputClass ?> js-mask-celular"
                       value="<?= $escf($ficha['contato_emergencia_telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
            </div>
            <div>
                <label for="contato_emergencia_parentesco" class="block text-sm font-medium text-gray-700 mb-2">Parentesco</label>
                <input type="text" id="contato_emergencia_parentesco" name="contato_emergencia_parentesco" maxlength="40" class="<?= $inputClass ?>"
                       value="<?= $escf($ficha['contato_emergencia_parentesco'] ?? '') ?>">
            </div>
        </div>
    </div>
</div>

<div class="p-6 space-y-6">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">Alimentação</h3>
        <p class="text-sm text-gray-500">Restrições e observações para a merenda/cantina.</p>
    </div>
    <div class="grid grid-cols-1 gap-6">
        <div>
            <label for="restricoes_alimentares" class="block text-sm font-medium text-gray-700 mb-2">Restrições alimentares</label>
            <textarea id="restricoes_alimentares" name="restricoes_alimentares" rows="2" class="<?= $inputClass ?>"
                      placeholder="Intolerâncias, dietas, religião..."><?= $escf($ficha['restricoes_alimentares'] ?? '') ?></textarea>
        </div>
        <div>
            <label for="alimentacao_obs" class="block text-sm font-medium text-gray-700 mb-2">Observações de alimentação</label>
            <textarea id="alimentacao_obs" name="alimentacao_obs" rows="2" class="<?= $inputClass ?>"><?= $escf($ficha['alimentacao_obs'] ?? '') ?></textarea>
        </div>
    </div>
</div>

<div class="p-6 space-y-6">
    <div>
        <h3 class="text-lg font-semibold text-gray-900">Transporte escolar</h3>
        <p class="text-sm text-gray-500">Dados de deslocamento do aluno até a escola.</p>
    </div>
    <div>
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="usa_transporte_escolar" value="0">
            <input type="checkbox" id="usa_transporte_escolar" name="usa_transporte_escolar" value="1" <?= $usaTransporte ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200 focus:ring-opacity-50">
            <span class="text-sm text-gray-700">Utiliza transporte escolar</span>
        </label>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="transporte_tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
            <select id="transporte_tipo" name="transporte_tipo" class="<?= $inputClass ?>">
                <option value="">Não informado</option>
                <option value="escolar" <?= $transporteTipo === 'escolar' ? 'selected' : '' ?>>Van/Ônibus escolar</option>
                <option value="publico" <?= $transporteTipo === 'publico' ? 'selected' : '' ?>>Transporte público</option>
                <option value="proprio" <?= $transporteTipo === 'proprio' ? 'selected' : '' ?>>Próprio / familiar</option>
                <option value="a_pe" <?= $transporteTipo === 'a_pe' ? 'selected' : '' ?>>A pé / bicicleta</option>
            </select>
        </div>
        <div>
            <label for="transporte_rota" class="block text-sm font-medium text-gray-700 mb-2">Rota / linha</label>
            <input type="text" id="transporte_rota" name="transporte_rota" maxlength="120" class="<?= $inputClass ?>"
                   value="<?= $escf($ficha['transporte_rota'] ?? '') ?>">
        </div>
        <div class="md:col-span-2">
            <label for="transporte_ponto" class="block text-sm font-medium text-gray-700 mb-2">Ponto de embarque / referência</label>
            <input type="text" id="transporte_ponto" name="transporte_ponto" maxlength="160" class="<?= $inputClass ?>"
                   value="<?= $escf($ficha['transporte_ponto'] ?? '') ?>">
        </div>
        <div>
            <label for="transporte_responsavel" class="block text-sm font-medium text-gray-700 mb-2">Responsável / motorista</label>
            <input type="text" id="transporte_responsavel" name="transporte_responsavel" maxlength="160" class="<?= $inputClass ?>"
                   value="<?= $escf($ficha['transporte_responsavel'] ?? '') ?>">
        </div>
        <div>
            <label for="transporte_telefone" class="block text-sm font-medium text-gray-700 mb-2">Telefone do transporte</label>
            <input type="text" id="transporte_telefone" name="transporte_telefone" inputmode="tel" maxlength="16"
                   class="<?= $inputClass ?> js-mask-celular"
                   value="<?= $escf($ficha['transporte_telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
        </div>
    </div>
</div>

<div class="p-6 space-y-6">
    <h3 class="text-lg font-semibold text-gray-900">Observações gerais</h3>
    <div>
        <textarea id="observacoes_gerais" name="observacoes_gerais" rows="3" class="<?= $inputClass ?>"
                  placeholder="Outras informações relevantes sobre o aluno"><?= $escf($ficha['observacoes_gerais'] ?? '') ?></textarea>
    </div>
</div>
