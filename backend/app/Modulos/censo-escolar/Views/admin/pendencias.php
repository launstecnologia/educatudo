<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eid = (int) ($edicao['id'] ?? 0);
$pendencias = is_array($pendencias ?? null) ? $pendencias : [];
$resumo = is_array($resumo ?? null) ? $resumo : [];
$editavel = !empty($editavel);
$page_header_title = 'Pendências e validação';
$page_header_subtitle = 'Erros impedem o TXT até o cadastro ser corrigido. Alertas de código INEP da pessoa não bloqueiam (o Educacenso gera o código).';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
include __DIR__ . '/_contexto.php';
$nav_atual = 'pendencias';
include __DIR__ . '/_nav.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cor = [
    'erro' => 'bg-red-100 text-red-800',
    'alerta' => 'bg-amber-100 text-amber-800',
    'divergencia' => 'bg-blue-100 text-blue-800',
];
$hrefEntidade = static function (array $p, int $eid): string {
    $tipo = (string) ($p['entidade_tipo'] ?? '');
    $id = (int) ($p['entidade_id'] ?? 0);
    $map = ['escola' => 'escola', 'gestor' => 'gestor', 'turma' => 'turma', 'aluno' => 'aluno', 'profissional' => 'profissional', 'matricula' => 'matricula'];
    if (!isset($map[$tipo]) || $id <= 0) {
        return URL . '/admin/censo/' . $eid . '/pendencias';
    }
    return URL . '/admin/censo/' . $eid . '/' . $map[$tipo] . '/' . $id;
};
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm">Erros: <strong><?= (int) ($resumo['erros'] ?? 0) ?></strong></div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm">Alertas: <strong><?= (int) ($resumo['alertas'] ?? 0) ?></strong></div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm">Divergências: <strong><?= (int) ($resumo['divergencias'] ?? 0) ?></strong></div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-sm">Resolvidas: <strong><?= (int) (($resumo['conferidas'] ?? 0) + ($resumo['justificadas'] ?? 0)) ?></strong></div>
</div>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left">Severidade</th>
                <th class="px-4 py-3 text-left">Registro</th>
                <th class="px-4 py-3 text-left">Mensagem</th>
                <th class="px-4 py-3 text-left">Orientação</th>
                <th class="px-4 py-3 text-left">Situação</th>
                <th class="px-4 py-3 text-right">Ação</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if ($pendencias === []): ?>
            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Nenhuma pendência. Execute a validação na visão geral.</td></tr>
            <?php endif; ?>
            <?php foreach ($pendencias as $p): ?>
            <tr>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $cor[$p['severidade'] ?? ''] ?? 'bg-gray-100' ?>"><?= $esc($p['severidade'] ?? '') ?></span></td>
                <td class="px-4 py-3"><?= $esc($p['entidade_tipo'] ?? '') ?> #<?= (int) ($p['entidade_id'] ?? 0) ?></td>
                <td class="px-4 py-3"><?= $esc($p['mensagem'] ?? '') ?></td>
                <td class="px-4 py-3 text-gray-500"><?= $esc($p['orientacao'] ?? '') ?></td>
                <td class="px-4 py-3"><?= $esc($p['status'] ?? '') ?></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a class="text-indigo-600 text-sm font-medium mr-3" href="<?= $esc($hrefEntidade($p, $eid)) ?>">Corrigir</a>
                    <?php if ($editavel && ($p['status'] ?? '') === 'aberta' && ($p['severidade'] ?? '') !== 'erro'): ?>
                    <form method="POST" action="<?= URL ?>/admin/censo/<?= $eid ?>/pendencias/<?= (int) $p['id'] ?>/conferir" class="inline">
                        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
                        <input type="hidden" name="justificativa" value="">
                        <button class="text-sm text-gray-600 font-medium">Conferir</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
