<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eid = (int) ($edicao['id'] ?? 0);
$entidade = (string) ($entidade ?? 'aluno');
$registro = is_array($registro ?? null) ? $registro : [];
$origem = is_array($origem ?? null) ? $origem : [];
$dados = is_array($dados ?? null) ? $dados : [];
$secoes = is_array($secoes ?? null) ? $secoes : [];
$secao = (string) ($secao ?? array_key_first($secoes));
$editavel = !empty($editavel);
$rid = (int) ($registro['id'] ?? 0);
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$val = static function (string $campo, $fallback = '') use ($dados, $origem, $registro) {
    if (isset($dados[$campo]) && $dados[$campo] !== '') {
        return $dados[$campo];
    }
    if (isset($registro[$campo]) && $registro[$campo] !== '' && $registro[$campo] !== null) {
        return $registro[$campo];
    }
    return $origem[$campo] ?? $fallback;
};
$origemCampo = static function (string $campo) use ($dados, $origem): string {
    if (isset($dados[$campo]) && $dados[$campo] !== '') {
        return 'Complemento do Censo';
    }
    if (isset($origem[$campo]) && $origem[$campo] !== '' && $origem[$campo] !== null) {
        return 'Cadastro principal';
    }
    return 'Não informado';
};
$chaves = array_keys($secoes);
$idx = array_search($secao, $chaves, true);
$proxima = ($idx !== false && isset($chaves[$idx + 1])) ? $chaves[$idx + 1] : '';

$page_header_title = 'Complemento do Censo';
$page_header_subtitle = 'Campos exclusivos da coleta. O cadastro operacional não é duplicado.';
ob_start();
?>
<a href="<?= URL ?>/admin/censo/<?= $eid ?>/<?= $entidade === 'gestor' ? 'gestores' : ($entidade === 'turma' ? 'turmas' : ($entidade === 'aluno' ? 'alunos' : ($entidade === 'profissional' ? 'profissionais' : ($entidade === 'matricula' ? 'matriculas' : 'escola')))) ?>" class="text-gray-600 hover:text-gray-900">← Voltar</a>
<?php
$page_header_actions = ob_get_clean();
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
$nav_atual = $entidade === 'gestor' ? 'gestores' : ($entidade === 'turma' ? 'turmas' : ($entidade === 'aluno' ? 'alunos' : ($entidade === 'profissional' ? 'profissionais' : ($entidade === 'matricula' ? 'matriculas' : 'escola'))));
include __DIR__ . '/_nav.php';
?>

<div class="bg-white rounded-xl shadow-lg p-0 w-full overflow-hidden">
    <div class="flex flex-col md:flex-row">
        <aside class="md:w-60 border-b md:border-b-0 md:border-r border-gray-200 p-4">
            <p class="text-xs uppercase tracking-wide text-gray-400 mb-2">Seções</p>
            <?php foreach ($secoes as $k => $label): ?>
            <a href="?secao=<?= $esc($k) ?>" class="block px-3 py-2 rounded-lg text-sm mb-1 <?= $secao === $k ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-600 hover:bg-gray-50' ?>">
                <?= $esc($label) ?>
            </a>
            <?php endforeach; ?>
        </aside>
        <div class="flex-1 p-6">
            <form method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/<?= $esc($entidade) ?>/<?= $rid ?>">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
                <input type="hidden" name="secao" value="<?= $esc($secao) ?>">
                <fieldset <?= $editavel ? '' : 'disabled' ?>>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <?php if ($entidade === 'escola' && in_array($secao, ['identificacao', 'endereco', 'funcionamento'], true)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código INEP</label>
                            <input name="codigo_inep" value="<?= $esc($val('inep', $val('codigo_inep'))) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-400 mt-1">Origem: <?= $esc($origemCampo('inep')) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Dependência administrativa</label>
                            <input name="dependencia_administrativa" value="<?= $esc($val('dependencia_administrativa')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-400 mt-1">Origem: Cadastro da unidade</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Localização</label>
                            <input name="localizacao" value="<?= $esc($val('localizacao')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Urbana / Rural">
                            <p class="text-xs text-gray-400 mt-1">Origem: Complemento do Censo</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CEP (8 dígitos)</label>
                            <input name="cep" value="<?= $esc($val('cep')) ?>" maxlength="8" inputmode="numeric" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Município (código IBGE, 7 dígitos)</label>
                            <input name="municipio" value="<?= $esc($val('municipio')) ?>" maxlength="7" inputmode="numeric" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Ex.: 3550308">
                            <p class="text-xs text-gray-400 mt-1">Obrigatório no registro 00. Sincronize a edição se o CEP da unidade estiver preenchido.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
                            <input name="endereco" value="<?= $esc($val('endereco')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Situação de funcionamento</label>
                            <input name="situacao_funcionamento" value="<?= $esc($val('situacao_funcionamento')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="1 = Em atividade">
                        </div>
                    <?php elseif ($entidade === 'escola'): ?>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= $esc($secoes[$secao] ?? $secao) ?></label>
                            <textarea name="<?= $esc($secao) ?>" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?= $esc($val($secao)) ?></textarea>
                            <p class="text-xs text-gray-400 mt-1">Campo exclusivo do Censo. Opções oficiais entram com o leiaute da edição.</p>
                        </div>
                    <?php elseif ($entidade === 'turma'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da turma</label>
                            <input value="<?= $esc($origem['nome'] ?? '') ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                            <p class="text-xs text-gray-400 mt-1">Origem: Cadastro da turma</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código INEP da turma</label>
                            <input name="codigo_inep" value="<?= $esc($val('codigo_inep')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Etapa censitária (código INEP)</label>
                            <?php $etapasCenso = is_array($etapas_censo ?? null) ? $etapas_censo : []; $etapaAtual = (string) $val('etapa_codigo'); ?>
                            <?php if ($etapasCenso !== []): ?>
                            <select name="etapa_codigo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                                <option value="">Selecione</option>
                                <?php foreach ($etapasCenso as $et): ?>
                                <option value="<?= $esc($et['codigo'] ?? '') ?>" <?= $etapaAtual === (string) ($et['codigo'] ?? '') ? 'selected' : '' ?>>
                                    <?= $esc(($et['codigo'] ?? '') . ' — ' . ($et['descricao'] ?? '')) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <input name="etapa_codigo" value="<?= $esc($etapaAtual) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <?php endif; ?>
                            <p class="text-xs text-gray-400 mt-1">Série acadêmica “<?= $esc($origem['serie'] ?? '') ?>”. Ensino médio: 25, 26 e 27.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modalidade</label>
                            <input name="modalidade_codigo" value="<?= $esc($val('modalidade_codigo')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    <?php elseif ($entidade === 'aluno'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                            <input value="<?= $esc($origem['nome'] ?? '') ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                            <p class="text-xs text-gray-400 mt-1">Origem: Cadastro do aluno</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código INEP</label>
                            <input name="codigo_inep" value="<?= $esc($origem['codigo_inep'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                            <input name="cpf" value="<?= $esc($origem['cpf'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da mãe</label>
                            <input name="nome_mae" value="<?= $esc($origem['nome_mae'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Transporte escolar</label>
                            <input name="transporte" value="<?= $esc($val('transporte')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-400 mt-1">Origem: Complemento do Censo</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deficiência / recursos</label>
                            <input name="deficiencia" value="<?= $esc($val('deficiencia')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    <?php elseif ($entidade === 'profissional'):
                        $escolaridades = [
                            '' => 'Selecione',
                            '1' => '1 — Não concluiu o ensino fundamental',
                            '2' => '2 — Ensino fundamental',
                            '7' => '7 — Ensino médio',
                            '6' => '6 — Educação superior',
                        ];
                        $vinculosProf = is_array($vinculos_profissional ?? null) ? $vinculos_profissional : [];
                    ?>
                        <?php if (in_array($secao, ['pessoa', 'identificacao'], true) || $secao === ''): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                            <input value="<?= $esc($origem['nome'] ?? $val('nome')) ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                            <p class="text-xs text-gray-400 mt-1">Origem: Cadastro do professor</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                            <input value="<?= $esc($origem['email'] ?? $val('email')) ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código do professor</label>
                            <input value="<?= $esc($origem['codigo_prof'] ?? $val('codigo_prof')) ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                        </div>
                        <?php endif; ?>
                        <?php if ($secao === 'documentos'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código INEP</label>
                            <input name="codigo_inep" value="<?= $esc($val('codigo_inep')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="12 dígitos, se já houver">
                            <p class="text-xs text-gray-400 mt-1">Sem código, use o TXT de identificação. O Educacenso gera o INEP.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                            <input name="cpf" value="<?= $esc($val('cpf')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <?php endif; ?>
                        <?php if ($secao === 'escolaridade'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Escolaridade</label>
                            <select name="escolaridade" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                                <?php foreach ($escolaridades as $k => $label): ?>
                                <option value="<?= $esc($k) ?>" <?= (string) $val('escolaridade') === (string) $k ? 'selected' : '' ?>><?= $esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Formação superior</label>
                            <input name="formacao_superior" value="<?= $esc($val('formacao_superior')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <?php endif; ?>
                        <?php if ($secao === 'vinculo'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Situação funcional</label>
                            <select name="situacao_funcional" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                                <?php foreach (['4' => '4 — Contrato CLT', '1' => '1 — Concursado/efetivo', '2' => '2 — Contrato temporário', '3' => '3 — Contrato terceirizado'] as $k => $label): ?>
                                <option value="<?= $esc($k) ?>" <?= (string) $val('situacao_funcional', '4') === $k ? 'selected' : '' ?>><?= $esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <p class="block text-sm font-medium text-gray-700 mb-2">Turmas e componentes (grade horária)</p>
                            <?php if ($vinculosProf === []): ?>
                            <p class="text-sm text-gray-500">Nenhum vínculo na grade desta edição. Sincronize a edição na visão geral.</p>
                            <?php else: ?>
                            <ul class="text-sm text-gray-700 space-y-1">
                                <?php foreach ($vinculosProf as $vinc): ?>
                                <li><?= $esc($vinc['turma_nome'] ?? 'Turma') ?><?= !empty($vinc['materia_nome']) ? ' · ' . $esc($vinc['materia_nome']) : '' ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($secao === 'conferencia'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Incluir na exportação</label>
                            <select name="incluir_exportacao" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                                <option value="1" <?= !empty($registro['incluir_exportacao']) ? 'selected' : '' ?>>Sim</option>
                                <option value="0" <?= empty($registro['incluir_exportacao']) ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    <?php elseif ($entidade === 'gestor'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                            <input value="<?= $esc($origem['nome'] ?? $registro['nome'] ?? '') ?>" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Código INEP</label>
                            <input name="codigo_inep" value="<?= $esc($val('codigo_inep')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                            <input name="cpf" value="<?= $esc($val('cpf')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Escolaridade / formação</label>
                            <input name="escolaridade" value="<?= $esc($val('escolaridade')) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    <?php else: ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Incluir na exportação</label>
                            <select name="incluir_exportacao" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                                <option value="1" <?= !empty($registro['incluir_exportacao']) ? 'selected' : '' ?>>Sim</option>
                                <option value="0" <?= empty($registro['incluir_exportacao']) ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Motivo da exclusão</label>
                            <input name="motivo_exclusao" value="<?= $esc($registro['motivo_exclusao'] ?? '') ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (in_array($entidade, ['aluno', 'escola'], true)): ?>
                <label class="flex items-center gap-2 text-sm text-gray-700 mb-6">
                    <input type="checkbox" name="atualizar_cadastro_principal" value="1" class="rounded border-gray-300">
                    Também atualizar o cadastro principal
                </label>
                <?php endif; ?>
                </fieldset>
                <div class="flex justify-end gap-3">
                    <a href="<?= URL ?>/admin/censo/<?= $eid ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700">Cancelar</a>
                    <?php if ($editavel): ?>
                    <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium">Salvar</button>
                    <?php if ($proxima !== ''): ?>
                    <button name="proxima_secao" value="<?= $esc($proxima) ?>" class="btn-primary-custom px-4 py-2 rounded-lg font-semibold">Salvar e avançar</button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
