<?php
$config = $config ?? null;
$erro_tabela = $erro_tabela ?? null;
$historico = $historico ?? ['sincronizacao' => null, 'eventos' => [], 'erro' => null];
$job = $job ?? null;
$escola_id = (int) ($escola_id ?? 0);
$csrf_token = $csrf_token ?? '';
$sync = $historico['sincronizacao'] ?? null;
$contagens = is_array($sync['contagens'] ?? null) ? $sync['contagens'] : [];
$modo = (string) ($sync['modo'] ?? '');
?>

<div class="space-y-6">
    <div>
        <h3 class="text-lg font-semibold text-slate-800">Integração LEX</h3>
        <p class="text-sm text-slate-600 mt-1">O primeiro cadastro vem da LEX: pessoas, cursos, séries, turmas e matrículas. Cada escola EducaTudo usa a própria escola LEX. Disciplina não entra nesta etapa. O login pela LEX fica para depois: quem for importado não entra com senha local.</p>
    </div>

    <?php if ($erro_tabela): ?>
        <div class="px-4 py-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm"><?= htmlspecialchars($erro_tabela) ?></div>
    <?php else: ?>
        <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/lex" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="ativo" value="1" <?= !empty($config['ativo']) ? 'checked' : '' ?> class="rounded border-slate-300">
                Integração ativa
            </label>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="lex-ambiente">Ambiente</label>
                <select id="lex-ambiente" name="ambiente" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="homologacao" <?= (($config['ambiente'] ?? 'homologacao') === 'homologacao') ? 'selected' : '' ?>>Homologação</option>
                    <option value="producao" <?= (($config['ambiente'] ?? '') === 'producao') ? 'selected' : '' ?>>Produção</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="lex-client">Client ID</label>
                <input id="lex-client" name="client_id" value="<?= htmlspecialchars((string) ($config['client_id'] ?? '')) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" autocomplete="off">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="lex-secret">Client secret</label>
                <input id="lex-secret" type="password" name="client_secret" value="" placeholder="<?= !empty($config['tem_segredo']) ? 'Já configurado. Preencha só para trocar.' : 'Obrigatório para ativar' ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" autocomplete="new-password">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="lex-tenant">Tenant ID da LEX</label>
                <input id="lex-tenant" name="tenant_id" value="<?= htmlspecialchars((string) ($config['tenant_id'] ?? '')) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="lex-escola">ID da escola na LEX</label>
                <input id="lex-escola" name="escola_lex_id" value="<?= htmlspecialchars((string) ($config['escola_lex_id'] ?? '')) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="lex-external">External ID da escola</label>
                <input id="lex-external" name="external_id" value="<?= htmlspecialchars((string) ($config['external_id'] ?? '')) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar</button>
        </form>

        <div class="flex flex-wrap gap-2">
            <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/lex/testar">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Testar conexão</button>
            </form>
            <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/lex/simular">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Simular</button>
            </form>
            <form method="post" action="<?= URL ?>/master/escolas/<?= $escola_id ?>/lex/sincronizar">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800">Sincronizar</button>
            </form>
        </div>

        <?php if (!empty($historico['erro'])): ?>
            <div class="px-4 py-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm"><?= htmlspecialchars((string) $historico['erro']) ?></div>
        <?php endif; ?>

        <?php if ($job): ?>
            <p class="text-sm text-slate-600">Último job: <?= htmlspecialchars((string) ($job['status'] ?? '')) ?><?php if (!empty($job['mensagem_erro'])): ?> — <?= htmlspecialchars((string) $job['mensagem_erro']) ?><?php endif; ?></p>
        <?php endif; ?>

        <?php if ($sync): ?>
            <div class="border border-slate-200 rounded-lg p-4 text-sm text-slate-700">
                <p class="font-medium text-slate-900"><?= $modo === 'simulacao' ? 'Última simulação' : 'Última sincronização' ?> — <?= htmlspecialchars((string) ($sync['status'] ?? '')) ?></p>
                <p class="mt-1 text-slate-500"><?= htmlspecialchars((string) ($sync['mensagem'] ?? '')) ?></p>
                <ul class="mt-3 grid grid-cols-2 gap-2">
                    <?php foreach (['aluno' => 'Alunos', 'professor' => 'Professores', 'responsavel' => 'Responsáveis', 'usuario' => 'Usuários', 'curso' => 'Cursos', 'serie' => 'Séries', 'turma' => 'Turmas', 'matricula' => 'Matrículas'] as $chave => $rotulo):
                        $item = $contagens[$chave] ?? ['criar' => 0, 'atualizar' => 0];
                    ?>
                        <li><?= htmlspecialchars($rotulo) ?>: <?= (int) ($item['criar'] ?? 0) ?> novos, <?= (int) ($item['atualizar'] ?? 0) ?> atualizados</li>
                    <?php endforeach; ?>
                    <li>Vínculos de responsável: <?= (int) ($contagens['vinculos_responsavel'] ?? 0) ?></li>
                    <li>Ignorados: <?= (int) ($contagens['ignorados'] ?? 0) ?></li>
                    <li>Conflitos: <?= (int) ($contagens['conflitos'] ?? 0) ?></li>
                    <li><?= $modo === 'simulacao' ? 'Seriam inativados' : 'Inativados' ?>: <?= (int) ($contagens['inativados'] ?? 0) ?></li>
                </ul>
            </div>
            <?php if (!empty($historico['eventos'])): ?>
                <ul class="text-sm divide-y divide-slate-100 border border-slate-200 rounded-lg">
                    <?php foreach ($historico['eventos'] as $evento): ?>
                        <li class="px-3 py-2">
                            <span class="font-medium"><?= htmlspecialchars((string) ($evento['nivel'] ?? '')) ?></span>
                            <?= htmlspecialchars((string) ($evento['mensagem'] ?? '')) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
