<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = $base ?? (URL . '/admin/students/' . (int) ($aluno_id ?? 0) . '/vida-escolar');
$token = (string) ($csrf_token ?? $token ?? '');
$documentos = is_array($documentos ?? $docs_recebidos ?? null) ? ($documentos ?? $docs_recebidos) : [];
?>
<?php if (!empty($admin_permissions['vida_escolar']['cadastrar'])): ?>
<div id="veLancarEscolaBackdrop" class="fixed inset-0 bg-black/40 z-[95] hidden" onclick="veFecharLancarEscola()"></div>
<aside id="veLancarEscolaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-[96] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       role="dialog"
       aria-labelledby="veLancarEscolaTitulo">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <div>
            <h2 id="veLancarEscolaTitulo" class="text-xl font-bold text-gray-900">Lançar Escola</h2>
            <p class="text-sm text-gray-500 mt-0.5">Ano cursado em outra instituição. Linhas em branco nas disciplinas são ignoradas.</p>
        </div>
        <button type="button" onclick="veFecharLancarEscola()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="post" action="<?= $base ?>/ano-externo" class="flex flex-col flex-1 overflow-hidden" id="ve-form-lancar-escola">
        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Escola de origem</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="ve_escola_nome" class="block text-sm font-medium text-gray-700 mb-1">Escola <span class="text-red-500">*</span></label>
                        <input type="text" id="ve_escola_nome" name="escola_nome" required placeholder="Nome da escola de origem"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="ve_municipio" class="block text-sm font-medium text-gray-700 mb-1">Município</label>
                        <input type="text" id="ve_municipio" name="municipio" placeholder="Ex.: Ribeirão Preto"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="ve_uf" class="block text-sm font-medium text-gray-700 mb-1">UF</label>
                        <input type="text" id="ve_uf" name="uf" maxlength="2" placeholder="SP"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white uppercase focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="ve_escola_inep" class="block text-sm font-medium text-gray-700 mb-1">Código INEP</label>
                        <input type="text" id="ve_escola_inep" name="escola_inep" placeholder="8 dígitos"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="ve_documento_id" class="block text-sm font-medium text-gray-700 mb-1">Documento anexado</label>
                        <select id="ve_documento_id" name="documento_id"
                                class="select-reset w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="0">Nenhum</option>
                            <?php foreach ($documentos as $d): ?>
                                <?php if (!is_array($d)) { continue; } ?>
                                <?php
                                $rotuloDoc = trim((string) ($d['tipo'] ?? 'documento'));
                                if (!empty($d['arquivo_nome'])) {
                                    $rotuloDoc .= ' · ' . (string) $d['arquivo_nome'];
                                } elseif (!empty($d['escola_emissora'])) {
                                    $rotuloDoc .= ' · ' . (string) $d['escola_emissora'];
                                } else {
                                    $rotuloDoc .= ' #' . (int) ($d['id'] ?? 0);
                                }
                                ?>
                                <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $esc($rotuloDoc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Ano e resultado</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="ve_ano_letivo" class="block text-sm font-medium text-gray-700 mb-1">Ano letivo <span class="text-red-500">*</span></label>
                        <input type="text" id="ve_ano_letivo" name="ano_letivo" required placeholder="Ex.: 2024" inputmode="numeric"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="ve_serie_ano" class="block text-sm font-medium text-gray-700 mb-1">Série <span class="text-red-500">*</span></label>
                        <input type="text" id="ve_serie_ano" name="serie_ano" required placeholder="Ex.: 7º Ano"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="ve_resultado" class="block text-sm font-medium text-gray-700 mb-1">Resultado</label>
                        <select id="ve_resultado" name="resultado"
                                class="select-reset w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione</option>
                            <option value="Aprovado">Aprovado</option>
                            <option value="Aprovado pelo Conselho">Aprovado pelo Conselho</option>
                            <option value="Transferido">Transferido</option>
                            <option value="Retido">Retido</option>
                            <option value="Cursando">Cursando</option>
                        </select>
                    </div>
                    <div>
                        <label for="ve_carga_total" class="block text-sm font-medium text-gray-700 mb-1">Carga horária total</label>
                        <input type="number" id="ve_carga_total" name="carga_horaria_total" min="0" placeholder="Ex.: 800"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>
            <section>
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Disciplinas e notas</h3>
                    <button type="button" class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50" onclick="veAddCompSimples()">
                        <i class="fa-solid fa-plus mr-1"></i>Adicionar disciplina
                    </button>
                </div>
                <div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 text-xs font-medium text-gray-500">
                        <div class="flex-1 min-w-0">Disciplina</div>
                        <div class="w-24 shrink-0">Nota</div>
                        <div class="w-28 shrink-0">Carga horária</div>
                    </div>
                    <div id="ve-comps-simples" class="p-3 space-y-2">
                        <?php for ($c = 0; $c < 6; $c++): ?>
                        <div class="flex items-center gap-2">
                            <input name="comp_nome[]" placeholder="Ex.: Matemática" class="flex-1 min-w-0 w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <input name="comp_nota[]" placeholder="0,0" class="w-24 shrink-0 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <input name="comp_ch[]" placeholder="h" inputmode="numeric" class="w-28 shrink-0 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="veFecharLancarEscola()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                Adicionar ano
            </button>
        </div>
    </form>
</aside>
<?php endif; ?>
