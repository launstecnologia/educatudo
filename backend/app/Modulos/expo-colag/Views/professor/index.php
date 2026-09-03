<?php
$indicadores = $indicadores ?? ['ativos' => 0, 'inscricoes_pendentes' => 0];
$projetos = $projetos ?? [];
$pendentes = $pendentes ?? [];
$edicao = $edicao ?? null;
$csrf_token = $csrf_token ?? '';
$modoAdmin = !empty($modo_admin);
$baseUrlExpo = rtrim((string) ($base_url_expo ?? (URL . '/professor/expo-colag')), '/');
$professores = $professores ?? [];
$filtros = $filtros ?? [];
$tituloLista = $modoAdmin ? 'Todos os projetos' : 'Meus projetos';
$totalProjetos = count($projetos);
$statusOpcoes = ['Rascunho', 'Publicado', 'Inscricoes_abertas', 'Em_execucao', 'Entrega', 'Avaliacao', 'Concluido', 'Cancelado'];

$badgeStatus = static function (string $st): string {
    $map = [
        'Rascunho' => 'bg-slate-100 text-slate-700',
        'Publicado' => 'bg-sky-100 text-sky-800',
        'Inscricoes_abertas' => 'bg-emerald-100 text-emerald-800',
        'Em_execucao' => 'bg-violet-100 text-violet-800',
        'Entrega' => 'bg-amber-100 text-amber-800',
        'Avaliacao' => 'bg-orange-100 text-orange-800',
        'Concluido' => 'bg-emerald-100 text-emerald-800',
        'Cancelado' => 'bg-red-100 text-red-800',
    ];
    return $map[$st] ?? 'bg-slate-100 text-slate-700';
};
?>
<div class="mb-6">
    <div class="flex flex-wrap justify-between items-start gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Expo Colag</h2>
            <p class="text-gray-600 text-sm">
                <?= $modoAdmin ? 'Gerencie os projetos da feira de todos os professores.' : 'Crie e acompanhe os projetos da feira.' ?>
                <?php if (!empty($edicao['data_evento'])): ?>
                    Evento em <?= htmlspecialchars(date('d/m/Y', strtotime($edicao['data_evento']))) ?>.
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($modoAdmin && !empty($pode_gerenciar)): ?>
            <a href="<?= URL ?>/admin/expo-colag/programacao"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-calendar-days"></i>
                Programação / stands
            </a>
            <a href="<?= URL ?>/admin/expo-colag/autorizacoes"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-camera"></i>
                Autorizações
            </a>
            <a href="<?= URL ?>/admin/expo-colag/configuracao"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                <i class="fa-solid fa-sliders"></i>
                Configuração
            </a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/criar"
               class="inline-flex items-center px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Criar projeto
            </a>
        </div>
    </div>
</div>

<?php
$authC = $autorizacao_contagens ?? [];
if ($modoAdmin && $authC):
?>
<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-6">
    Autorização de imagem:
    <?= (int) ($authC['Autorizado_total'] ?? 0) ?> total ·
    <?= (int) ($authC['Autorizado_interno'] ?? 0) ?> interno ·
    <?= (int) ($authC['Nao_autorizado'] ?? 0) ?> pendente.
    <a href="<?= URL ?>/admin/expo-colag/autorizacoes" class="font-semibold underline ml-1">Gerenciar</a>
</div>
<?php endif; ?>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs text-gray-500">Projetos ativos</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['ativos'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs text-gray-500">Inscrições pendentes</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['inscricoes_pendentes'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs text-gray-500">Tarefas atrasadas</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['tarefas_atrasadas'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <p class="text-xs text-gray-500">Entregas a avaliar</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= (int) ($indicadores['entregas_avaliar'] ?? 0) ?></p>
    </div>
</div>

<?php if (!empty($pendentes)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl shadow-sm mb-6 overflow-hidden">
    <div class="px-5 py-3 border-b border-amber-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-amber-900">Inscrições aguardando aprovação</h3>
        <span class="text-xs text-amber-700"><?= count($pendentes) ?></span>
    </div>
    <ul class="divide-y divide-amber-100">
        <?php foreach ($pendentes as $pend): ?>
        <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
            <div>
                <span class="font-medium text-gray-900"><?= htmlspecialchars($pend['aluno_nome'] ?? '') ?></span>
                <span class="text-gray-500"> → <?= htmlspecialchars($pend['projeto_titulo'] ?? '') ?></span>
            </div>
            <div class="flex items-center gap-3">
                <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $pend['projeto_id'] ?>/inscricoes/decidir">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="inscricao_id" value="<?= (int) $pend['id'] ?>">
                    <input type="hidden" name="decisao" value="aprovar">
                    <input type="hidden" name="voltar" value="index">
                    <button type="submit" class="text-emerald-700 font-medium hover:underline">Aprovar</button>
                </form>
                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $pend['projeto_id'] ?>/acompanhar?aba=participantes" class="text-accent font-medium hover:underline">Ver</a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-2">
        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($tituloLista) ?></h3>
        <span class="text-sm text-gray-500"><?= (int) $totalProjetos ?> itens</span>
    </div>

    <?php if ($modoAdmin): ?>
    <form method="get" action="<?= htmlspecialchars($baseUrlExpo) ?>" class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="search" name="q" value="<?= htmlspecialchars($filtros['q'] ?? '') ?>" placeholder="Pesquisar por título, área ou professor"
               class="md:col-span-2 w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
            <option value="">Todos os status</option>
            <?php foreach ($statusOpcoes as $statusFiltro): ?>
                <option value="<?= htmlspecialchars($statusFiltro) ?>" <?= ($filtros['status'] ?? '') === $statusFiltro ? 'selected' : '' ?>>
                    <?= htmlspecialchars(str_replace('_', ' ', $statusFiltro)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="professor_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-white text-sm">
            <option value="">Todos os professores</option>
            <?php foreach ($professores as $prof): ?>
                <option value="<?= (int) $prof['id'] ?>" <?= (int) ($filtros['professor_id'] ?? 0) === (int) $prof['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($prof['nome'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="md:col-span-4 flex flex-wrap gap-2">
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium">Filtrar</button>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>" class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-50">Limpar</a>
        </div>
    </form>
    <?php endif; ?>

    <?php if (empty($projetos)): ?>
        <div class="text-center py-12 px-5">
            <p class="text-gray-500 mb-3">Nenhum projeto ainda.</p>
            <a href="<?= htmlspecialchars($baseUrlExpo) ?>/criar" class="inline-flex items-center px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-semibold hover:opacity-90">
                Criar o primeiro projeto
            </a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                        <?php if ($modoAdmin): ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vagas</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($projetos as $p): ?>
                        <?php $st = (string) ($p['status'] ?? ''); ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($p['titulo'] ?? '') ?></td>
                            <?php if ($modoAdmin): ?>
                            <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($p['professor_nome'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeStatus($st) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $st)) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= (int) ($p['vagas_totais'] ?? 0) ?></td>
                            <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/acompanhar"
                                   class="btn-primary-custom inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-90"><?= $modoAdmin ? 'Painel' : 'Meu painel' ?></a>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/materiais-pdf"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-300 text-gray-800 bg-white hover:bg-gray-50 ml-1">PDF materiais</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/editar" class="text-accent hover:underline">Editar</a>
                                <span class="text-gray-300 mx-1">·</span>
                                <a href="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/preview" class="text-gray-600 hover:underline">Preview</a>
                                <?php if ($st !== 'Concluido'): ?>
                                <span class="text-gray-300 mx-1">·</span>
                                <form method="post" action="<?= htmlspecialchars($baseUrlExpo) ?>/projetos/<?= (int) $p['id'] ?>/excluir" class="inline"
                                      onsubmit="return confirm('Excluir este projeto? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <button type="submit" class="text-red-600 font-medium hover:underline">Excluir</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
