<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
        <div class="student-card min-w-0">
            <div class="student-card-header flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-user text-blue-600"></i>
                </div>
                <h3 class="text-base font-semibold text-slate-900">Informações Pessoais</h3>
            </div>
            <div class="student-card-body">
                <div class="student-info-columns">
                    <div class="student-info-col">
                        <div class="student-info-col-title">Identificação</div>
                        <div class="student-info-fields">
                            <div>
                                <span class="student-field-label">Nome Completo</span>
                                <p class="student-field-value"><?= safe_htmlspecialchars($student['nome_exibicao'] ?? $student['nome'] ?? '', '') ?></p>
                            </div>
                            <?php if (\StudentFormHelper::temNomeSocial($student)): ?>
                            <div>
                                <span class="student-field-label">Nome civil</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars(\StudentFormHelper::nomeCivil($student), '') ?></p>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="student-field-label">RA / Código</span>
                                <p class="student-field-value"><?= safe_htmlspecialchars($student['ra'] ?? '', '') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Nickname</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nickname'] ?? null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">CPF / CIN</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($cpfDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">RG</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($rgDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Sexo</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($sexoLabel) ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Data de nascimento</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($dataNascDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Telefone</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($telefoneDisplay ?: null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Celular</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($celularDisplay ?: null, 'Não informado') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="student-info-col">
                        <div class="student-info-col-title">Acesso ao Sistema</div>
                        <div class="student-info-fields">
                            <div>
                                <span class="student-field-label">Login (nickname)</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nickname'] ?? null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Email</span>
                                <p class="student-field-value font-normal break-all"><?= safe_htmlspecialchars($student['email'] ?? null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Primeiro acesso</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $jaFezPrimeiroAcesso ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= $jaFezPrimeiroAcesso ? 'Já realizado' : 'Pendente' ?>
                                </span>
                            </div>
                            <div>
                                <span class="student-field-label">Status</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $statusLoginClass ?>">
                                    <?= safe_htmlspecialchars($statusLoginLabel) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="student-info-col">
                        <div class="student-info-col-title">Vínculo Escolar</div>
                        <div class="student-info-fields">
                            <div>
                                <span class="student-field-label">Turma</span>
                                <p class="student-field-value font-normal"><?= $matriculaEncerrada ? 'Encerrada' : ($matriculaPendente ? 'Pendente' : safe_htmlspecialchars($turmaDisplay)) ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Matrícula</span>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $matriculaBadgeClass ?>">
                                    <?= safe_htmlspecialchars($statusMatriculaLabel) ?>
                                </span>
                            </div>
                            <?php if (!empty($student['serie'])): ?>
                            <div>
                                <span class="student-field-label">Série</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['serie']) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($student['numero_chamada'])): ?>
                            <div>
                                <span class="student-field-label">Nº na lista de chamada</span>
                                <p class="student-field-value font-normal"><?= (int) $student['numero_chamada'] ?></p>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="student-field-label">Responsável principal</span>
                                <p class="student-field-value font-normal leading-snug"><?= safe_htmlspecialchars($student['responsavel_nome'] ?? null, 'Sem responsável') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="student-info-address">
                    <div class="student-info-col-title">Endereço</div>
                    <div class="student-info-address-grid">
                        <div class="student-address-full">
                            <span class="student-field-label">Logradouro</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoLogradouro ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Número</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoNumero ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Complemento</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoComplemento ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Bairro</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoBairro ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Cidade</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoCidade ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">UF</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoUf ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">CEP</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoCep ?: null, 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
                <?php
                $certidaoPartes = array_filter([
                    !empty($student['certidao_livro'] ?? '') ? 'Livro ' . $student['certidao_livro'] : '',
                    !empty($student['certidao_folha'] ?? '') ? 'Folha ' . $student['certidao_folha'] : '',
                    !empty($student['certidao_termo'] ?? '') ? 'Termo ' . $student['certidao_termo'] : '',
                ]);
                $certidaoResumo = trim((string) ($student['certidao_nascimento'] ?? ''));
                if (!empty($certidaoPartes)) {
                    $certidaoResumo = trim($certidaoResumo . ' (' . implode(', ', $certidaoPartes) . ')');
                }
                ?>
                <div class="student-info-address">
                    <div class="student-info-col-title">Documentação civil</div>
                    <div class="student-info-address-grid">
                        <div>
                            <span class="student-field-label">Nome da mãe</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nome_mae'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Nome do pai</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nome_pai'] ?? null, 'Não informado') ?></p>
                        </div>
                            <div>
                                <span class="student-field-label">Código INEP (Censo)</span>
                                <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['codigo_inep'] ?? null, 'Não informado') ?></p>
                            </div>
                            <div>
                                <span class="student-field-label">Nacionalidade</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nacionalidade'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Naturalidade</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['naturalidade'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">UF nascimento</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['uf_nascimento'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Cor / Raça</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['cor_raca'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Órgão emissor RG</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(trim(($student['orgao_emissor'] ?? '') . ' ' . ($student['uf_rg'] ?? '')) ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">NIS</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($student['nis'] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Zona</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(ucfirst((string) ($student['zona'] ?? '')) ?: null, 'Não informada') ?></p>
                        </div>
                        <div class="student-address-full">
                            <span class="student-field-label">Certidão de nascimento</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($certidaoResumo ?: null, 'Não informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">WhatsApp</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(\StudentFormHelper::formatTelefoneDisplay($student['whatsapp'] ?? '') ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">E-mail secundário</span>
                            <p class="student-field-value font-normal break-all"><?= safe_htmlspecialchars($student['email_secundario'] ?? null, 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
