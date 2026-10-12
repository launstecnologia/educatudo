<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
    <?php
    $fc = is_array($ficha_complementar ?? null) ? $ficha_complementar : [];
    $fcVal = static function ($key) use ($fc) {
        $v = trim((string) ($fc[$key] ?? ''));
        return $v !== '' ? $v : null;
    };
    $transporteTipos = ['escolar' => 'Van/Ônibus escolar', 'publico' => 'Transporte público', 'proprio' => 'Próprio / familiar', 'a_pe' => 'A pé / bicicleta'];
    $usaTransporteFc = !empty($fc['usa_transporte_escolar']);
    $transporteTipoFc = (string) ($fc['transporte_tipo'] ?? '');
    ?>
    <div class="student-card min-w-0 mb-6">
        <div class="student-card-header flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-notes-medical text-rose-500"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-slate-900">Ficha complementar</h3>
                <p class="text-sm text-slate-500 mt-0.5">Saúde, alimentação e transporte</p>
            </div>
        </div>
        <div class="student-card-body">
            <div class="student-info-columns">
                <div class="student-info-col">
                    <div class="student-info-col-title">Saúde</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Tipo sanguíneo</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('tipo_sanguineo'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Plano de saúde</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars(trim(($fcVal('plano_saude') ?? '') . ' ' . ($fcVal('plano_saude_numero') ? '— ' . $fcVal('plano_saude_numero') : '')) ?: null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Hospital de referência</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('hospital_referencia'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Alergias</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('alergias'), 'Nenhuma informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Medicamentos de uso contínuo</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('medicamentos_uso'), 'Nenhum informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Condições crônicas</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('condicoes_cronicas'), 'Nenhuma informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Acessibilidade / deficiência</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('deficiencias_obs'), 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
                <div class="student-info-col">
                    <div class="student-info-col-title">Contato de emergência</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Nome</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('contato_emergencia_nome'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Telefone</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('contato_emergencia_telefone'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Parentesco</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('contato_emergencia_parentesco'), 'Não informado') ?></p>
                        </div>
                    </div>
                    <div class="student-info-col-title mt-4">Alimentação</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Restrições alimentares</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('restricoes_alimentares'), 'Nenhuma informada') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Observações</span>
                            <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('alimentacao_obs'), 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
                <div class="student-info-col">
                    <div class="student-info-col-title">Transporte escolar</div>
                    <div class="student-info-fields">
                        <div>
                            <span class="student-field-label">Utiliza transporte escolar</span>
                            <p class="student-field-value font-normal"><?= $usaTransporteFc ? 'Sim' : 'Não' ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Tipo</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($transporteTipos[$transporteTipoFc] ?? null, 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Rota / linha</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_rota'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Ponto / referência</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_ponto'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Responsável / motorista</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_responsavel'), 'Não informado') ?></p>
                        </div>
                        <div>
                            <span class="student-field-label">Telefone do transporte</span>
                            <p class="student-field-value font-normal"><?= safe_htmlspecialchars($fcVal('transporte_telefone'), 'Não informado') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($fcVal('observacoes_gerais')): ?>
            <div class="student-info-address">
                <div class="student-info-col-title">Observações gerais</div>
                <p class="student-field-value font-normal whitespace-pre-line"><?= safe_htmlspecialchars($fcVal('observacoes_gerais')) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
