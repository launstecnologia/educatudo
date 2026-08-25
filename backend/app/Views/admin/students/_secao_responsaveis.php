<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
        <div id="section-responsaveis-vinculados" class="student-card min-w-0" data-perm-key="responsaveis_vinculados" data-perm-action="visualizar">
            <div class="student-card-header">
                <h3 class="text-base font-semibold text-slate-900">Responsáveis Vinculados</h3>
            </div>
            <div class="student-card-body pt-4">
                <?php if (empty($responsaveis_aluno)): ?>
                    <p class="text-sm text-gray-500">Nenhum responsável vinculado.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($responsaveis_aluno as $resp): ?>
                            <?php
                            $respNome = (string)($resp['nome'] ?? '');
                            $respEmail = (string)($resp['email'] ?? '');
                            $respTelefone = (string)($resp['telefone'] ?? '');
                            $respCpf = (string)($resp['cpf'] ?? '');
                            $respAtivo = (int)($resp['ativo'] ?? 1) === 1;
                            $respFinanceiro = (int)($resp['is_financeiro'] ?? 0) === 1;
                            $respIniciais = responsavel_iniciais($respNome);
                            ?>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 py-3 border-b border-slate-100 last:border-0">
                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                    <?= safe_htmlspecialchars($respIniciais) ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900 text-sm leading-snug"><?= safe_htmlspecialchars($respNome, '-') ?></p>
                                    <p class="text-xs text-slate-500 mt-1 break-words"><?= safe_htmlspecialchars($respCpf, 'CPF não informado') ?></p>
                                    <p class="text-xs text-slate-500 break-all"><?= safe_htmlspecialchars($respEmail, 'Sem email') ?><?php if ($respTelefone !== ''): ?> · <?= safe_htmlspecialchars($respTelefone) ?><?php endif; ?></p>
                                </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 sm:flex-shrink-0 sm:pl-0 pl-[52px]">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $respAtivo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $respAtivo ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                    <?php if ($respFinanceiro): ?>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-violet-100 text-violet-800">Financeiro</span>
                                    <?php endif; ?>
                                    <?php if (!empty($resp['parentesco'])): ?>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-700"><?= safe_htmlspecialchars($resp['parentesco']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($resp['pode_retirar'])): ?>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Pode retirar</span>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="px-2.5 py-1 text-xs font-medium text-blue-600 hover:text-blue-800"
                                        data-responsavel="<?= htmlspecialchars(json_encode([
                                            'aluno_id' => (int)($student['id'] ?? 0),
                                            'responsavel_id' => (int)($resp['id'] ?? 0),
                                            'nome' => $respNome,
                                            'email' => $respEmail,
                                            'telefone' => $respTelefone,
                                            'cpf' => $respCpf,
                                            'rg' => (string)($resp['rg'] ?? ''),
                                            'celular' => (string)($resp['celular'] ?? ''),
                                            'data_nascimento' => (string)($resp['data_nascimento'] ?? ''),
                                            'endereco' => (string)($resp['endereco'] ?? ''),
                                            'numero' => (string)($resp['numero'] ?? ''),
                                            'complemento' => (string)($resp['complemento'] ?? ''),
                                            'bairro' => (string)($resp['bairro'] ?? ''),
                                            'cidade' => (string)($resp['cidade'] ?? ''),
                                            'uf' => (string)($resp['uf'] ?? ''),
                                            'cep' => (string)($resp['cep'] ?? ''),
                                            'observacoes' => (string)($resp['observacoes'] ?? ''),
                                            'is_financeiro' => $respFinanceiro ? 1 : 0,
                                            'ativo' => $respAtivo ? 1 : 0,
                                            'parentesco' => (string)($resp['parentesco'] ?? ''),
                                            'profissao' => (string)($resp['profissao'] ?? ''),
                                            'empresa' => (string)($resp['empresa'] ?? ''),
                                            'pode_retirar' => (int)($resp['pode_retirar'] ?? 0),
                                            'recebe_boletos' => (int)($resp['recebe_boletos'] ?? 0),
                                            'recebe_boletim' => (int)($resp['recebe_boletim'] ?? 0),
                                            'recebe_notificacoes' => (int)($resp['recebe_notificacoes'] ?? 0),
                                            'responsavel_pedagogico' => (int)($resp['responsavel_pedagogico'] ?? 0),
                                            'guarda_judicial' => (int)($resp['guarda_judicial'] ?? 0),
                                            'assina_documentos' => (int)($resp['assina_documentos'] ?? 0)
                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="abrirModalEditarResponsavel(JSON.parse(this.dataset.responsavel))">
                                        Editar
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="mt-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="abrirModalCadastrarPai(<?= (int)($student['id'] ?? 0) ?>)" data-perm-key="acao_rapida_cadastrar_responsavel" data-perm-action="cadastrar" class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                        Cadastrar responsável
                    </button>
                </div>
            </div>
        </div>
