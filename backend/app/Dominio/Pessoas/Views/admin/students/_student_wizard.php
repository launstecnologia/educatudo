<?php
$modoEdicao = ($modoAluno ?? '') === 'editar';
$student = is_array($student ?? null) ? $student : [];
$ficha = is_array($ficha ?? null) ? $ficha : [];
$units = is_array($units ?? null) ? $units : [];
$alunoId = (int) ($student['id'] ?? 0);
$escw = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';
$nomeValor = (string) ($student['nome_civil'] ?? $student['nome'] ?? '');
$cancelarUrl = $modoEdicao
    ? URL . '/admin/students/' . $alunoId
    : URL . '/admin/students';
$salvarUrl = $modoEdicao
    ? URL . '/admin/students/' . $alunoId
    : URL . '/admin/students';
$titulo = $modoEdicao ? 'Editar aluno' : 'Cadastrar aluno';
$subtitulo = $modoEdicao
    ? 'Atualize as informações do aluno'
    : 'Preencha as informações do aluno';
$rotuloSalvar = $modoEdicao ? 'Salvar alterações' : 'Salvar aluno';
$etapasWizard = [
    1 => ['Dados pessoais', 'Informações principais'],
    2 => ['Endereço e contato', 'Localização e comunicação'],
    3 => ['Família e Censo', 'Filiação e censo'],
    4 => ['Saúde e necessidades', 'Cuidados e emergência'],
    5 => ['Escolar e transporte', 'Deslocamento até a escola'],
    6 => ['Acesso e finalização', 'Login, status e resumo'],
];
?>
<style>
.aluno-wizard-passos { display: flex; gap: 0; overflow-x: auto; padding-bottom: 4px; }
.aluno-wizard-passo { position: relative; flex: 1 0 132px; min-width: 132px; }
.aluno-wizard-passo:not(:last-child)::after {
    content: "";
    position: absolute;
    top: 15px;
    left: calc(28px + 1.25rem);
    right: 8px;
    height: 2px;
    background: #e2e8f0;
}
.aluno-wizard-passo.is-feito:not(:last-child)::after { background: #86efac; }
.aluno-wizard-passo button {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    width: 100%;
    padding: 0;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
}
.aluno-wizard-passo.is-futuro button { cursor: default; }
.aluno-wizard.is-edicao .aluno-wizard-passo.is-futuro button { cursor: pointer; }
.aluno-wizard-bolha {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 999px;
    flex-shrink: 0;
    font-size: 0.8rem;
    font-weight: 700;
    background: #f1f5f9;
    color: #94a3b8;
    border: 2px solid #e2e8f0;
}
.aluno-wizard-passo.is-atual .aluno-wizard-bolha { background: #2563eb; color: #fff; border-color: #2563eb; }
.aluno-wizard-passo.is-feito .aluno-wizard-bolha { background: #ecfdf5; color: #059669; border-color: #6ee7b7; }
.aluno-wizard-passo.is-pendente .aluno-wizard-bolha { background: #fef2f2; color: #dc2626; border-color: #fca5a5; }
.aluno-wizard-passo strong { display: block; font-size: 0.78rem; line-height: 1.2; color: #64748b; }
.aluno-wizard-passo small { display: block; margin-top: 2px; font-size: 0.68rem; color: #94a3b8; }
.aluno-wizard-passo.is-atual strong { color: #1d4ed8; }
.aluno-wizard-passo.is-feito strong { color: #047857; }
.aluno-wizard-passo.is-pendente strong { color: #b91c1c; }
.aluno-wizard-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.25rem 1.35rem;
}
.aluno-wizard-card h3 { font-size: 1rem; font-weight: 650; color: #0f172a; }
.aluno-wizard-nav {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
#wizard-voltar { justify-self: start; }
#wizard-contador { justify-self: center; }
#wizard-avancar, #wizard-salvar { justify-self: end; grid-column: 3; }
#studentForm .wizard-campo-erro { border-color: #dc2626 !important; }
#studentForm .wizard-erro { margin-top: 0.25rem; font-size: 0.75rem; color: #dc2626; }
#studentForm input:not([type="checkbox"]):not([type="hidden"]):not([type="file"]),
#studentForm select { min-height: 42px; }
.aluno-wizard-anel {
    width: 76px;
    height: 76px;
    border-radius: 999px;
    background: conic-gradient(#2563eb calc(var(--p) * 1%), #e2e8f0 0);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.aluno-wizard-anel span {
    width: 58px;
    height: 58px;
    border-radius: 999px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #0f172a;
}
.aluno-wizard-obr li.is-ok { color: #047857; }
.aluno-wizard-obr li.is-ok i { color: #059669; }
</style>

<div class="aluno-wizard <?= $modoEdicao ? 'is-edicao' : '' ?>" id="aluno-wizard">
    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center min-w-0">
            <a href="<?= $escw($cancelarUrl) ?>" class="text-gray-500 hover:text-gray-700 mr-4 shrink-0" aria-label="Voltar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-gray-900"><?= $escw($titulo) ?></h2>
                <p class="text-gray-600 text-sm mt-1"><?= $escw($subtitulo) ?></p>
            </div>
        </div>
        <a href="<?= $escw($cancelarUrl) ?>" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</a>
    </div>

    <?php if ($modoEdicao && !empty($prompt_foto)): ?>
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        Cadastro concluído com sucesso. Envie a foto do aluno na primeira etapa para completar o prontuário.
    </div>
    <?php endif; ?>

    <div class="aluno-wizard-card mb-4">
        <ol class="aluno-wizard-passos" id="wizard-passos">
            <?php foreach ($etapasWizard as $numero => $meta): ?>
            <li class="aluno-wizard-passo <?= $numero === 1 ? 'is-atual' : 'is-futuro' ?>" data-passo="<?= (int) $numero ?>">
                <button type="button" data-ir="<?= (int) $numero ?>">
                    <span class="aluno-wizard-bolha" data-bolha><?= (int) $numero ?></span>
                    <span class="min-w-0">
                        <strong><?= $escw($meta[0]) ?></strong>
                        <small><?= $escw($meta[1]) ?></small>
                    </span>
                </button>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
        <div class="xl:col-span-8 min-w-0">
            <form id="studentForm" novalidate>
                <input type="hidden" name="_token" id="csrf_token" value="<?= $escw($csrf_token ?? '') ?>">
                <?php if ($modoEdicao): ?>
                <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

                <div class="aluno-wizard-etapa space-y-4" data-etapa="1">
                    <div class="aluno-wizard-card">
                        <h3>Foto do aluno</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Uma boa foto ajuda na identificação do aluno em listas, boletins e documentos.</p>
                        <?php if ($modoEdicao && $alunoId > 0): ?>
                            <?php
                            $mode = 'edit';
                            $size = 'lg';
                            $admin_permissions = $admin_permissions ?? [];
                            include __DIR__ . '/_student_photo.php';
                            ?>
                        <?php else: ?>
                        <div class="flex items-center gap-4">
                            <div class="h-20 w-20 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-400 shrink-0">
                                <i class="fa-solid fa-user text-2xl"></i>
                            </div>
                            <p class="text-sm text-gray-600">A foto poderá ser enviada logo depois de salvar o cadastro. JPG, PNG, GIF ou WebP (máx. 2MB).</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="aluno-wizard-card space-y-6">
                        <div>
                            <h3>Identificação</h3>
                            <p class="text-sm text-gray-500 mt-1">Dados principais do aluno.</p>
                        </div>
                        <div>
                            <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome completo <span class="text-red-600">*</span></label>
                            <input type="text" id="nome" name="nome" required value="<?= $escw($nomeValor) ?>"
                                   class="<?= $inputClass ?>" placeholder="Nome civil / de registro">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="codigo_aluno" class="block text-sm font-medium text-gray-700 mb-2">Código do aluno</label>
                                <input type="text" id="codigo_aluno" name="codigo_aluno" value="<?= $escw($student['codigo_aluno'] ?? '') ?>"
                                       class="<?= $inputClass ?>" placeholder="Código interno da escola">
                                <p class="mt-1 text-xs text-gray-500">Código interno da escola.</p>
                            </div>
                            <div>
                                <label for="ra" class="block text-sm font-medium text-gray-700 mb-2">RA</label>
                                <input type="text" id="ra" name="ra" value="<?= $escw($student['ra'] ?? '') ?>"
                                       class="<?= $inputClass ?>" placeholder="Número do RA, sem o dígito">
                                <p class="mt-1 text-xs text-gray-500">Número do registro. O dígito e a UF ficam nos campos ao lado.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="ra_digito" class="block text-sm font-medium text-gray-700 mb-2">Dígito do RA</label>
                                <input type="text" id="ra_digito" name="ra_digito" maxlength="2" value="<?= $escw($student['ra_digito'] ?? '') ?>"
                                       class="<?= $inputClass ?>" placeholder="Ex.: 0 ou X" autocomplete="off">
                                <p class="mt-1 text-xs text-gray-500">Usado na digitação da SED (São Paulo).</p>
                            </div>
                            <div>
                                <label for="ra_uf" class="block text-sm font-medium text-gray-700 mb-2">UF do RA</label>
                                <input type="text" id="ra_uf" name="ra_uf" maxlength="2" value="<?= $escw($student['ra_uf'] ?? '') ?>"
                                       class="<?= $inputClass ?> uppercase" placeholder="SP" autocomplete="off">
                            </div>
                            <div>
                                <label for="cgm" class="block text-sm font-medium text-gray-700 mb-2">CGM</label>
                                <input type="text" id="cgm" name="cgm" maxlength="30" value="<?= $escw($student['cgm'] ?? '') ?>"
                                       class="<?= $inputClass ?>" placeholder="Código no SERE" autocomplete="off">
                                <p class="mt-1 text-xs text-gray-500">Usado na conferência do Paraná. Mantenha os zeros à esquerda.</p>
                            </div>
                        </div>
                        <div>
                            <label for="nome_social" class="block text-sm font-medium text-gray-700 mb-2">Nome social <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <input type="text" id="nome_social" name="nome_social" maxlength="255" value="<?= $escw($student['nome_social'] ?? '') ?>"
                                   class="<?= $inputClass ?>" placeholder="Se preenchido, é o nome usado na plataforma e nos documentos">
                            <p class="mt-1 text-xs text-gray-500">O nome completo acima permanece o de registro (civil).</p>
                        </div>
                        <div class="grid grid-cols-1 <?= !empty($units) ? 'md:grid-cols-3' : 'md:grid-cols-2' ?> gap-6">
                            <div>
                                <label for="sexo" class="block text-sm font-medium text-gray-700 mb-2">Sexo (lista de chamada)</label>
                                <select id="sexo" name="sexo" class="<?= $inputClass ?>">
                                    <option value="">Não informado</option>
                                    <option value="F" <?= ($student['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Feminino</option>
                                    <option value="M" <?= ($student['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="N" <?= ($student['sexo'] ?? '') === 'N' ? 'selected' : '' ?>>Neutro / outro</option>
                                </select>
                            </div>
                            <?php $parteDoc = 'nascimento'; include __DIR__ . '/_student_documento_endereco_fields.php'; ?>
                            <?php if (!empty($units)): ?>
                            <div>
                                <label for="unidade_id" class="block text-sm font-medium text-gray-700 mb-2">Unidade <span class="text-red-600">*</span></label>
                                <select id="unidade_id" name="unidade_id" class="<?= $inputClass ?>">
                                    <option value="">Selecione</option>
                                    <?php foreach ($units as $unit): ?>
                                    <option value="<?= (int) $unit['id'] ?>" <?= (int) ($student['unidade_id'] ?? 0) === (int) $unit['id'] ? 'selected' : '' ?>><?= $escw($unit['nome'] ?? '') ?> (<?= ($unit['tipo'] ?? '') === 'matriz' ? 'Matriz' : 'Filial' ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <?php $parteDoc = 'documentos'; include __DIR__ . '/_student_documento_endereco_fields.php'; ?>
                        </div>
                    </div>

                    <div class="aluno-wizard-card">
                        <h3 class="mb-4">Identificação civil</h3>
                        <?php $parteCivil = 'civil'; include __DIR__ . '/_student_identificacao_civil_fields.php'; ?>
                    </div>

                    <div class="aluno-wizard-card">
                        <h3 class="mb-4">Certidão de nascimento</h3>
                        <?php $parteCivil = 'certidao'; include __DIR__ . '/_student_identificacao_civil_fields.php'; ?>
                    </div>

                    <div class="aluno-wizard-card">
                        <?php $parteCivil = 'estrangeiros'; include __DIR__ . '/_student_identificacao_civil_fields.php'; ?>
                    </div>
                </div>

                <div class="aluno-wizard-etapa hidden space-y-4" data-etapa="2">
                    <div class="aluno-wizard-card">
                        <h3>Endereço</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">A busca de CEP preenche logradouro, bairro, cidade e UF.</p>
                        <?php $parteDoc = 'endereco'; include __DIR__ . '/_student_documento_endereco_fields.php'; ?>
                    </div>
                    <div class="aluno-wizard-card">
                        <h3>Contato</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Telefones e e-mails para comunicação.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php include __DIR__ . '/_student_contato_fields.php'; ?>
                        </div>
                    </div>
                </div>

                <div class="aluno-wizard-etapa hidden space-y-4" data-etapa="3">
                    <div class="aluno-wizard-card">
                        <h3 class="mb-1">Filiação e Censo</h3>
                        <p class="text-sm text-gray-500 mb-4">Dados dos responsáveis e informações do censo escolar.</p>
                        <?php $parteCivil = 'filiacao'; include __DIR__ . '/_student_identificacao_civil_fields.php'; ?>
                    </div>
                </div>

                <div class="aluno-wizard-etapa hidden space-y-4" data-etapa="4">
                    <div class="aluno-wizard-card">
                        <h3>Saúde</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Informações de saúde usadas em emergências e pelo cuidado pedagógico.</p>
                        <?php $parteFicha = 'saude'; include __DIR__ . '/_student_ficha_complementar_fields.php'; ?>
                    </div>
                    <div class="aluno-wizard-card">
                        <h3>Alimentação</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Restrições e observações para a merenda/cantina.</p>
                        <?php $parteFicha = 'alimentacao'; include __DIR__ . '/_student_ficha_complementar_fields.php'; ?>
                    </div>
                </div>

                <div class="aluno-wizard-etapa hidden space-y-4" data-etapa="5">
                    <div class="aluno-wizard-card">
                        <h3>Transporte escolar</h3>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Unidade, RA, código interno e INEP ficam nas etapas anteriores. Aqui entra só o deslocamento até a escola.</p>
                        <?php $parteFicha = 'transporte'; include __DIR__ . '/_student_ficha_complementar_fields.php'; ?>
                    </div>
                </div>

                <div class="aluno-wizard-etapa hidden space-y-4" data-etapa="6">
                    <div class="aluno-wizard-card">
                        <h3 class="mb-4">Observações gerais</h3>
                        <?php $parteFicha = 'observacoes'; include __DIR__ . '/_student_ficha_complementar_fields.php'; ?>
                    </div>

                    <div class="aluno-wizard-card space-y-6">
                        <div>
                            <h3>Acesso ao sistema</h3>
                            <p class="text-sm text-gray-500 mt-1">O aluno entra na plataforma com nickname e senha.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nickname" class="block text-sm font-medium text-gray-700 mb-2">Nickname (login do aluno)</label>
                                <input type="text" id="nickname" name="nickname" autocomplete="username" value="<?= $escw($student['nickname'] ?? '') ?>"
                                       class="<?= $inputClass ?>" placeholder="Ex: joao.silva">
                            </div>
                            <div>
                                <label for="senha" class="block text-sm font-medium text-gray-700 mb-2"><?= $modoEdicao ? 'Nova senha' : 'Senha' ?></label>
                                <input type="password" id="senha" name="senha" autocomplete="new-password" class="<?= $inputClass ?>"
                                       placeholder="<?= $modoEdicao ? 'Deixe em branco para manter a atual' : 'Senha para login' ?>">
                                <?php if ($modoEdicao): ?>
                                <p class="text-xs text-gray-500 mt-1">Se preencher e marcar "Já fez primeiro acesso", o aluno poderá entrar com nickname e esta senha.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$modoEdicao): ?>
                        <p class="text-xs text-gray-500">A matrícula em turma é feita depois, em Ações Rápidas na ficha do aluno. Sem matrícula, o status ficará pendente.</p>
                        <?php endif; ?>
                    </div>

                    <div class="aluno-wizard-card space-y-3">
                        <h3>Status</h3>
                        <div>
                            <?php if ($modoEdicao): ?><input type="hidden" name="ativo" value="0"><?php endif; ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="ativo" value="1" <?= !$modoEdicao || !empty($student['ativo']) ? 'checked' : '' ?>
                                       class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Aluno ativo</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="pagante" value="1" <?= !$modoEdicao || !isset($student['pagante']) || !empty($student['pagante']) ? 'checked' : '' ?>
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Aluno pagante</span>
                            </label>
                            <p class="ml-6 text-xs text-gray-500 mt-1">Indica se a escola paga por este aluno</p>
                        </div>
                        <?php if ($modoEdicao): ?>
                        <div class="pt-2 border-t border-gray-200">
                            <input type="hidden" name="primeiro_acesso" value="1">
                            <label class="flex items-center">
                                <input type="checkbox" name="primeiro_acesso" value="0" <?= (int) ($student['primeiro_acesso'] ?? 1) === 0 ? 'checked' : '' ?>
                                       class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Já fez primeiro acesso</span>
                            </label>
                            <p class="ml-6 text-xs text-gray-500 mt-1">Se marcado, o aluno não verá mais a tela de primeiro acesso e poderá entrar com nickname e senha já definidos.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="aluno-wizard-card">
                        <h3 class="mb-3">Resumo antes de salvar</h3>
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-gray-500">Nome do aluno</dt><dd class="font-medium text-gray-900" data-resumo="nome">—</dd></div>
                            <div><dt class="text-gray-500">Unidade</dt><dd class="font-medium text-gray-900" data-resumo="unidade">—</dd></div>
                            <div><dt class="text-gray-500">Data de nascimento</dt><dd class="font-medium text-gray-900" data-resumo="nascimento">—</dd></div>
                            <div><dt class="text-gray-500">Contato</dt><dd class="font-medium text-gray-900" data-resumo="contato">—</dd></div>
                            <div class="md:col-span-2"><dt class="text-gray-500">Status</dt><dd class="font-medium text-gray-900" data-resumo="status">—</dd></div>
                        </dl>
                    </div>
                </div>

                <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mt-4"></div>
                <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mt-4"></div>

                <div class="aluno-wizard-nav">
                    <button type="button" id="wizard-voltar" class="hidden px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">← Voltar</button>
                    <span class="text-sm text-gray-500 text-center" id="wizard-contador">Etapa 1 de 6</span>
                    <button type="button" id="wizard-avancar" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Salvar e continuar →</button>
                    <button type="submit" id="wizard-salvar" class="hidden btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold"><?= $escw($rotuloSalvar) ?></button>
                </div>
            </form>
        </div>

        <aside class="xl:col-span-4 space-y-4 sticky top-4 self-start">
            <div class="aluno-wizard-card">
                <h3>Resumo do cadastro</h3>
                <p class="text-sm text-gray-500 mt-1 mb-4">Acompanhe o preenchimento das informações.</p>
                <div class="flex items-center gap-4 mb-4">
                    <div class="aluno-wizard-anel" id="wizard-anel" style="--p:0"><span id="wizard-percent">0%</span></div>
                    <div>
                        <p class="font-semibold text-gray-900" id="wizard-percent-titulo">Cadastro incompleto</p>
                        <p class="text-xs text-gray-500 mt-1">Os campos com * precisam estar preenchidos para avançar da primeira etapa.</p>
                    </div>
                </div>
                <ul class="space-y-2 text-sm" id="wizard-contagens"></ul>
            </div>
            <div class="aluno-wizard-card">
                <h3 class="text-red-600 mb-3">Campos obrigatórios</h3>
                <ul class="aluno-wizard-obr space-y-2 text-sm text-gray-700">
                    <li data-obr="nome"><i class="fa-solid fa-asterisk text-red-500 text-[10px] mr-1"></i> Nome completo</li>
                    <li data-obr="data_nasc"><i class="fa-solid fa-asterisk text-red-500 text-[10px] mr-1"></i> Data de nascimento</li>
                    <?php if (!empty($units)): ?>
                    <li data-obr="unidade_id"><i class="fa-solid fa-asterisk text-red-500 text-[10px] mr-1"></i> Unidade</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="aluno-wizard-card">
                <h3 class="mb-3">Informações importantes</h3>
                <ul class="space-y-2 text-sm text-gray-600">
                    <li>Os dados do aluno são usados em documentos oficiais da escola.</li>
                    <li>Alguns campos são obrigatórios para a emissão de documentos.</li>
                    <li>O nickname e a senha serão usados para o acesso do aluno à plataforma.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>

<?php include __DIR__ . '/_student_form_masks.php'; ?>
<script>
(function () {
    var form = document.getElementById('studentForm');
    if (!form) return;
    var modoEdicao = <?= $modoEdicao ? 'true' : 'false' ?>;
    var total = 6;
    var etapa = 1;
    var feitos = {};
    var pendentes = {};
    var enviando = false;
    var nomesEtapa = <?= json_encode(array_column($etapasWizard, 0), JSON_UNESCAPED_UNICODE) ?>;

    var voltarBtn = document.getElementById('wizard-voltar');
    var avancarBtn = document.getElementById('wizard-avancar');
    var salvarBtn = document.getElementById('wizard-salvar');
    var contador = document.getElementById('wizard-contador');
    var erroBox = document.getElementById('errorMessage');
    var okBox = document.getElementById('successMessage');

    function painel(n) {
        return form.querySelector('.aluno-wizard-etapa[data-etapa="' + n + '"]');
    }

    function limparErroCampo(el) {
        if (!el || !el.classList) return;
        el.classList.remove('wizard-campo-erro');
        el.removeAttribute('aria-invalid');
        var msg = el.parentElement && el.parentElement.querySelector('.wizard-erro');
        if (msg) msg.remove();
    }

    function marcarErro(id, mensagem) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('wizard-campo-erro');
        el.setAttribute('aria-invalid', 'true');
        var pai = el.parentElement;
        if (!pai) return;
        var msg = pai.querySelector('.wizard-erro');
        if (!msg) {
            msg = document.createElement('p');
            msg.className = 'wizard-erro';
            pai.appendChild(msg);
        }
        msg.textContent = mensagem;
    }

    function validarEtapa(n, mostrar) {
        if (n !== 1) return true;
        var ok = true;
        var regras = [
            { id: 'nome', msg: 'Informe o nome completo.' },
            { id: 'data_nasc', msg: 'Informe a data de nascimento.' },
            { id: 'unidade_id', msg: 'Selecione uma unidade.' }
        ];
        regras.forEach(function (regra) {
            var el = document.getElementById(regra.id);
            if (!el) return;
            limparErroCampo(el);
            if (String(el.value || '').trim() === '') {
                ok = false;
                if (mostrar) marcarErro(regra.id, regra.msg);
            }
        });
        pendentes[1] = !ok;
        return ok;
    }

    function pintar() {
        document.querySelectorAll('#wizard-passos .aluno-wizard-passo').forEach(function (li) {
            var n = parseInt(li.getAttribute('data-passo'), 10);
            li.classList.remove('is-atual', 'is-feito', 'is-futuro', 'is-pendente');
            if (pendentes[n]) li.classList.add('is-pendente');
            else if (n === etapa) li.classList.add('is-atual');
            else if (feitos[n]) li.classList.add('is-feito');
            else li.classList.add('is-futuro');
            var bolha = li.querySelector('[data-bolha]');
            if (bolha) bolha.textContent = (!pendentes[n] && feitos[n] && n !== etapa) ? '✓' : String(n);
        });
        if (contador) contador.textContent = 'Etapa ' + etapa + ' de ' + total;
        if (voltarBtn) voltarBtn.classList.toggle('hidden', etapa === 1);
        if (avancarBtn) avancarBtn.classList.toggle('hidden', etapa === total);
        if (salvarBtn) salvarBtn.classList.toggle('hidden', etapa !== total);
        for (var i = 1; i <= total; i++) {
            var box = painel(i);
            if (!box) continue;
            box.classList.toggle('hidden', i !== etapa);
        }
    }

    function mostrar(n) {
        etapa = n;
        pintar();
        atualizarResumo();
        var topo = document.getElementById('aluno-wizard');
        if (topo && typeof topo.scrollIntoView === 'function') {
            topo.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    }

    function avancar() {
        if (!validarEtapa(etapa, true)) {
            pintar();
            var primeiro = form.querySelector('.wizard-campo-erro');
            if (primeiro && primeiro.scrollIntoView) primeiro.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        pendentes[etapa] = false;
        feitos[etapa] = true;
        if (etapa < total) mostrar(etapa + 1);
    }

    function voltar() {
        if (etapa > 1) mostrar(etapa - 1);
    }

    function irPara(n) {
        if (n === etapa) return;
        if (!modoEdicao && n > etapa + 1) return;
        if (!modoEdicao && n > etapa) {
            avancar();
            return;
        }
        if (modoEdicao) {
            if (!validarEtapa(etapa, true)) pendentes[etapa] = true;
            else { pendentes[etapa] = false; feitos[etapa] = true; }
        }
        mostrar(n);
    }

    function valorCampo(id) {
        var el = document.getElementById(id);
        if (!el) return '';
        return String(el.value || '').trim();
    }

    function textoResumo(chave, texto) {
        document.querySelectorAll('[data-resumo="' + chave + '"]').forEach(function (el) {
            el.textContent = texto || '—';
        });
    }

    function contatoResumo() {
        return valorCampo('celular') || valorCampo('whatsapp') || valorCampo('telefone') || valorCampo('email') || '—';
    }

    function dataResumo() {
        var bruto = valorCampo('data_nasc');
        var pedacos = bruto.split('-');
        if (pedacos.length === 3) return pedacos[2] + '/' + pedacos[1] + '/' + pedacos[0];
        return bruto || '—';
    }

    function unidadeResumo() {
        var el = document.getElementById('unidade_id');
        if (!el) return '—';
        var opt = el.options[el.selectedIndex];
        if (!opt || !el.value) return '—';
        return opt.textContent.trim();
    }

    function statusResumo() {
        var partes = [];
        var ativo = form.querySelector('input[type="checkbox"][name="ativo"]');
        var pagante = form.querySelector('input[type="checkbox"][name="pagante"]');
        var acesso = form.querySelector('input[type="checkbox"][name="primeiro_acesso"]');
        if (ativo) partes.push(ativo.checked ? 'Ativo' : 'Inativo');
        if (pagante) partes.push(pagante.checked ? 'Pagante' : 'Não pagante');
        if (acesso) partes.push(acesso.checked ? 'Já acessou' : 'Primeiro acesso pendente');
        return partes.join(' · ') || '—';
    }

    function contagemEtapa(n) {
        var box = painel(n);
        if (!box) return { preenchidos: 0, total: 0 };
        var campos = box.querySelectorAll('input[name], select[name], textarea[name]');
        var vistos = {};
        var preenchidos = 0;
        var totalCampos = 0;
        campos.forEach(function (el) {
            if (el.type === 'hidden' || el.type === 'file') return;
            if (el.type === 'checkbox' && vistos[el.name]) return;
            vistos[el.name] = true;
            totalCampos += 1;
            var ok = el.type === 'checkbox' ? el.checked : String(el.value || '').trim() !== '';
            if (ok) preenchidos += 1;
        });
        return { preenchidos: preenchidos, total: totalCampos };
    }

    function atualizarResumo() {
        textoResumo('nome', valorCampo('nome') || '—');
        textoResumo('unidade', unidadeResumo());
        textoResumo('nascimento', dataResumo());
        textoResumo('contato', contatoResumo());
        textoResumo('status', statusResumo());

        var obrigatorios = ['nome', 'data_nasc', 'unidade_id'];
        var feitosObr = 0;
        var totalObr = 0;
        obrigatorios.forEach(function (id) {
            var el = document.getElementById(id);
            var item = document.querySelector('[data-obr="' + id + '"]');
            if (!el) {
                if (item) item.classList.add('hidden');
                return;
            }
            totalObr += 1;
            var ok = String(el.value || '').trim() !== '';
            if (ok) feitosObr += 1;
            if (item) item.classList.toggle('is-ok', ok);
        });
        var pct = totalObr ? Math.round((feitosObr / totalObr) * 100) : 0;
        var anel = document.getElementById('wizard-anel');
        var pctEl = document.getElementById('wizard-percent');
        var titulo = document.getElementById('wizard-percent-titulo');
        if (anel) anel.style.setProperty('--p', String(pct));
        if (pctEl) pctEl.textContent = pct + '%';
        if (titulo) titulo.textContent = pct === 100 ? 'Obrigatórios preenchidos' : 'Cadastro incompleto';

        var lista = document.getElementById('wizard-contagens');
        if (!lista) return;
        lista.innerHTML = '';
        for (var i = 1; i <= total; i++) {
            var c = contagemEtapa(i);
            var li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-3';
            var nome = document.createElement('span');
            nome.textContent = nomesEtapa[i - 1] || ('Etapa ' + i);
            nome.className = i === etapa ? 'text-blue-700 font-medium' : 'text-gray-600';
            var num = document.createElement('span');
            num.className = 'text-xs text-gray-500';
            num.textContent = c.preenchidos + '/' + c.total;
            li.appendChild(nome);
            li.appendChild(num);
            lista.appendChild(li);
        }
    }

    document.querySelectorAll('#wizard-passos [data-ir]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            irPara(parseInt(btn.getAttribute('data-ir'), 10));
        });
    });
    if (voltarBtn) voltarBtn.addEventListener('click', voltar);
    if (avancarBtn) avancarBtn.addEventListener('click', avancar);

    form.addEventListener('input', function (e) {
        limparErroCampo(e.target);
        atualizarResumo();
    });
    form.addEventListener('change', function (e) {
        limparErroCampo(e.target);
        var usa = document.getElementById('usa_transporte_escolar');
        var box = document.getElementById('transporte-campos');
        if (usa && box && e.target === usa) box.classList.toggle('hidden', !usa.checked);
        atualizarResumo();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (etapa < total) {
            avancar();
            return;
        }
        if (!validarEtapa(1, true)) {
            mostrar(1);
            var primeiro = form.querySelector('.wizard-campo-erro');
            if (primeiro && primeiro.scrollIntoView) primeiro.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        if (enviando) return;
        enviando = true;
        if (salvarBtn) salvarBtn.disabled = true;

        var formData = new FormData(form);
        if (typeof window.studentFormNormalizeDocumentoEndereco === 'function') {
            window.studentFormNormalizeDocumentoEndereco(formData);
        }
        erroBox.classList.add('hidden');
        okBox.classList.add('hidden');

        fetch(<?= json_encode($salvarUrl) ?>, { method: 'POST', body: formData })
            .then(function (response) {
                return response.json().then(function (result) {
                    return { ok: response.ok, result: result };
                });
            })
            .then(function (pacote) {
                if (pacote.ok) {
                    okBox.textContent = modoEdicao ? 'Aluno atualizado com sucesso!' : 'Aluno cadastrado com sucesso!';
                    okBox.classList.remove('hidden');
                    var destino = modoEdicao
                        ? <?= json_encode(URL . '/admin/students/' . $alunoId) ?>
                        : (pacote.result && pacote.result.id
                            ? <?= json_encode(URL . '/admin/students/') ?> + pacote.result.id + '/edit?foto=1'
                            : <?= json_encode(URL . '/admin/students') ?>);
                    setTimeout(function () { window.location.href = destino; }, modoEdicao ? 2000 : 1500);
                    return;
                }
                erroBox.textContent = (pacote.result && pacote.result.error) || (modoEdicao ? 'Erro ao atualizar aluno' : 'Erro ao cadastrar aluno');
                erroBox.classList.remove('hidden');
                enviando = false;
                if (salvarBtn) salvarBtn.disabled = false;
            })
            .catch(function () {
                erroBox.textContent = 'Erro de conexão. Tente novamente.';
                erroBox.classList.remove('hidden');
                enviando = false;
                if (salvarBtn) salvarBtn.disabled = false;
            });
    });

    pintar();
    atualizarResumo();
})();
</script>
