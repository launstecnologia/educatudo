<?php
$student = is_array($student ?? null) ? $student : [];
$detalhe = is_array($detalhe ?? null) ? $detalhe : [];
$doc = is_array($detalhe['documento'] ?? null) ? $detalhe['documento'] : [];
$itens = is_array($detalhe['itens'] ?? null) ? $detalhe['itens'] : [];
$resultados = is_array($detalhe['resultados'] ?? null) ? $detalhe['resultados'] : [];
$assinaturas = is_array($detalhe['assinaturas'] ?? null) ? $detalhe['assinaturas'] : [];
$labels = is_array($resultado_labels ?? null) ? $resultado_labels : [];
$checklist = is_array($checklist ?? null) ? $checklist : ['ok' => false, 'itens' => []];
$alunoId = (int) ($student['id'] ?? 0);
$hid = (int) ($doc['id'] ?? 0);
$status = (string) ($doc['status'] ?? '');
$editavel = in_array($status, ['Rascunho', 'Conferido'], true);
$token = htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8');
$base = URL . '/admin/students/' . $alunoId . '/historico-escolar/' . $hid;
$cargosAssinados = array_column($assinaturas, 'cargo');
?>
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
        <div>
            <a href="<?= URL ?>/admin/students/<?= $alunoId ?>/historico-escolar" class="text-sm text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-arrow-left mr-1"></i> Versões do histórico
            </a>
            <h1 class="text-2xl font-bold text-slate-900 mt-1">
                Histórico Escolar · v<?= (int) ($doc['versao'] ?? 1) ?>
            </h1>
            <p class="text-sm text-slate-600">
                <?= htmlspecialchars((string) ($student['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                · <span class="font-medium"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                · <?= htmlspecialchars((string) ($doc['finalidade'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= $base ?>/pdf" target="_blank" rel="noopener"
               class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">
                <i class="fa-solid fa-file-pdf text-rose-500 mr-2"></i> Ver PDF
            </a>
            <?php if (!empty($detalhe['validation_url'])): ?>
                <a href="<?= htmlspecialchars((string) $detalhe['validation_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"
                   class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">
                    <i class="fa-solid fa-qrcode mr-2"></i> Validação pública
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="rounded-lg px-4 py-3 text-sm <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' ?>">
            <?= htmlspecialchars((string) $flash_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex flex-wrap gap-2">
        <?php if ($status === 'Rascunho'): ?>
            <form method="post" action="<?= $base ?>/conferir">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Conferir</button>
            </form>
        <?php endif; ?>
        <?php if ($status === 'Conferido'): ?>
            <form method="post" action="<?= $base ?>/voltar-rascunho">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <button class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 bg-white">Reabrir rascunho</button>
            </form>
            <form method="post" action="<?= $base ?>/emitir" onsubmit="return confirm('Emitir congela o documento. Continuar?');">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold" <?= empty($checklist['ok']) ? 'disabled title="Complete o checklist"' : '' ?>>
                    Emitir (imutável)
                </button>
            </form>
        <?php endif; ?>
        <?php if (in_array($status, ['Emitido', 'Assinado'], true)): ?>
            <?php if (!in_array('Secretario_Escolar', $cargosAssinados, true)): ?>
                <form method="post" action="<?= $base ?>/assinar">
                    <input type="hidden" name="_token" value="<?= $token ?>">
                    <input type="hidden" name="cargo" value="Secretario_Escolar">
                    <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700">Assinar como Secretário(a)</button>
                </form>
            <?php endif; ?>
            <?php if (!in_array('Diretor', $cargosAssinados, true)): ?>
                <form method="post" action="<?= $base ?>/assinar">
                    <input type="hidden" name="_token" value="<?= $token ?>">
                    <input type="hidden" name="cargo" value="Diretor">
                    <button class="px-4 py-2 rounded-lg text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700">Assinar como Diretor(a)</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= $base ?>/nova-versao">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <button class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 bg-white">Nova versão (correção)</button>
            </form>
        <?php endif; ?>
        <?php if ($status === 'Cancelado'): ?>
            <form method="post" action="<?= $base ?>/nova-versao">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Gerar nova versão</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($assinaturas !== []): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <h2 class="text-sm font-semibold text-slate-800 mb-2">Assinaturas</h2>
            <ul class="text-sm space-y-1">
                <?php foreach ($assinaturas as $a): ?>
                    <li class="text-slate-700">
                        <strong><?= htmlspecialchars((string) ($a['cargo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        — <?= htmlspecialchars((string) ($a['usuario_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        em <?= !empty($a['assinado_em']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $a['assinado_em'])), ENT_QUOTES, 'UTF-8') : '—' ?>
                        <?php if (!empty($a['ip_origem'])): ?>
                            <span class="text-slate-400 text-xs">(IP <?= htmlspecialchars((string) $a['ip_origem'], ENT_QUOTES, 'UTF-8') ?>)</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800">Componentes curriculares</h2>
            <span class="text-xs text-slate-500"><?= count($itens) ?> item(ns)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-left">
                    <tr>
                        <th class="px-3 py-2">Ano</th>
                        <th class="px-3 py-2">Série</th>
                        <th class="px-3 py-2">Componente</th>
                        <th class="px-3 py-2">Nota</th>
                        <th class="px-3 py-2">CH</th>
                        <th class="px-3 py-2">Freq.%</th>
                        <th class="px-3 py-2">Origem</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($itens as $it): ?>
                        <tr>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($it['ano_letivo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($it['serie_ano'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2 font-medium text-slate-800"><?= htmlspecialchars((string) ($it['componente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars((string) ($it['resultado_valor'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-3 py-2"><?= isset($it['carga_horaria']) && $it['carga_horaria'] !== null ? (int) $it['carga_horaria'] : '—' ?></td>
                            <td class="px-3 py-2"><?= isset($it['frequencia_percentual']) && $it['frequencia_percentual'] !== null ? htmlspecialchars((string) $it['frequencia_percentual'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td class="px-3 py-2">
                                <span class="text-xs px-2 py-0.5 rounded-full <?= ($it['origem'] ?? '') === 'Externo' ? 'bg-violet-100 text-violet-800' : 'bg-slate-100 text-slate-700' ?>">
                                    <?= htmlspecialchars((string) ($it['origem'] ?? 'Interno'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!empty($it['escola_origem'])): ?>
                                    <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) $it['escola_origem'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <?php if ($editavel && ($it['origem'] ?? '') === 'Externo'): ?>
                                    <form method="post" action="<?= $base ?>/itens/<?= (int) $it['id'] ?>/excluir" onsubmit="return confirm('Excluir item externo?');">
                                        <input type="hidden" name="_token" value="<?= $token ?>">
                                        <button class="text-red-600 hover:text-red-800 text-xs font-medium">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($itens === []): ?>
                        <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">Nenhum item. Gere o rascunho a partir dos boletins ou lance estudos externos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($resultados !== []): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Resultados anuais</h2>
            <div class="space-y-3">
                <?php foreach ($resultados as $r): ?>
                    <?php if ($editavel): ?>
                        <form method="post" action="<?= $base ?>/resultado" class="grid sm:grid-cols-4 gap-2 items-end border border-slate-100 rounded-lg p-3">
                            <input type="hidden" name="_token" value="<?= $token ?>">
                            <input type="hidden" name="ano_letivo" value="<?= htmlspecialchars((string) $r['ano_letivo'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="serie_ano" value="<?= htmlspecialchars((string) $r['serie_ano'], ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <div class="text-xs text-slate-500">Ano / Série</div>
                                <div class="text-sm font-medium"><?= htmlspecialchars($r['ano_letivo'] . ' · ' . $r['serie_ano'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-600 mb-1">Resultado</label>
                                <select name="resultado" class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-sm bg-white">
                                    <?php foreach ($labels as $k => $lab): ?>
                                        <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= ($r['resultado'] ?? '') === $k ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-600 mb-1">Observação</label>
                                <input type="text" name="observacao" value="<?= htmlspecialchars((string) ($r['observacao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                       class="w-full border border-slate-300 rounded-lg px-2 py-1.5 text-sm">
                            </div>
                            <button class="px-3 py-2 rounded-lg text-sm font-medium border border-slate-300 bg-white hover:bg-slate-50">Salvar</button>
                        </form>
                    <?php else: ?>
                        <div class="text-sm text-slate-700">
                            <strong><?= htmlspecialchars($r['ano_letivo'] . ' · ' . $r['serie_ano'], ENT_QUOTES, 'UTF-8') ?></strong>
                            — <?= htmlspecialchars($labels[$r['resultado']] ?? $r['resultado'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($r['observacao'])): ?>
                                <span class="text-slate-500">(<?= htmlspecialchars((string) $r['observacao'], ENT_QUOTES, 'UTF-8') ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($editavel): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Lançar estudo em outra instituição</h2>
            <form method="post" action="<?= $base ?>/itens-externos" class="grid sm:grid-cols-3 gap-3">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Ano letivo *</label>
                    <input name="ano_letivo" required placeholder="2023" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Série/Ano *</label>
                    <input name="serie_ano" required placeholder="8º Ano" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Componente *</label>
                    <input name="componente" required placeholder="Matemática" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Nota/Conceito</label>
                    <input name="resultado_valor" placeholder="8.5" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Carga horária</label>
                    <input name="carga_horaria" type="number" min="0" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Frequência %</label>
                    <input name="frequencia_percentual" type="number" step="0.1" min="0" max="100" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Escola de origem *</label>
                    <input name="escola_origem" required placeholder="Escola Municipal ..." class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Resultado anual (opcional)</label>
                    <select name="resultado_anual" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">—</option>
                        <?php foreach ($labels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Adicionar item externo</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Observações e registro (modelo SP)</h2>
            <form method="post" action="<?= $base ?>/observacoes" class="space-y-3">
                <input type="hidden" name="_token" value="<?= $token ?>">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Nº registro SED / GDAE (concluintes)</label>
                    <input type="text" name="numero_registro_sed" maxlength="80"
                           value="<?= htmlspecialchars((string) ($doc['numero_registro_sed'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="Opcional — publicado na SED quando houver">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Observações gerais</label>
                    <textarea name="observacoes_gerais" rows="3" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                              placeholder="Dependência, reclassificação, adaptação curricular (sem laudo)... A escala 0–10 já é impressa automaticamente no PDF."><?= htmlspecialchars((string) ($doc['observacoes_gerais'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <button class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 bg-white hover:bg-slate-50">Salvar</button>
            </form>
        </div>
    <?php elseif (!empty($doc['observacoes_gerais']) || !empty($doc['numero_registro_sed'])): ?>
        <div class="bg-white rounded-xl border border-slate-200 p-5 text-sm space-y-2">
            <?php if (!empty($doc['numero_registro_sed'])): ?>
                <div><strong>Nº registro SED / GDAE:</strong>
                    <?= htmlspecialchars((string) $doc['numero_registro_sed'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($doc['observacoes_gerais'])): ?>
                <div><strong>Observações gerais:</strong>
                    <?= htmlspecialchars((string) $doc['observacoes_gerais'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
