<?php
/**
 * Mesma lista e o mesmo Demonstrativo de Notas da coordenação.
 * Só eventos de notas (exibir_em=notas). O boletim oficial não entra aqui.
 *
 * @var list<array<string,mixed>> $boletins_gerados_notas
 * @var array<string,mixed>|null $aluno
 * @var array<string,mixed>|null $filho
 */
$boletins_gerados_notas = is_array($boletins_gerados_notas ?? null) ? $boletins_gerados_notas : [];
$alunoIdPortal = (int) ($aluno_id_notas ?? 0);
if ($alunoIdPortal <= 0 && is_array($aluno ?? null)) {
    $alunoIdPortal = (int) ($aluno['id'] ?? 0);
}
if ($alunoIdPortal <= 0 && is_array($filho ?? null)) {
    $alunoIdPortal = (int) ($filho['id'] ?? 0);
}
$student = ['id' => $alunoIdPortal];
$aluno_id = $alunoIdPortal;
$notas_mensagem_vazia = 'Nenhuma nota disponível.';
$boletim_eventos_notas = [];
$vistosPortalNotas = [];
foreach ($boletins_gerados_notas as $evPortal) {
    if (!is_array($evPortal)) {
        continue;
    }
    if (strtolower(trim((string) ($evPortal['exibir_em'] ?? 'notas'))) !== 'notas') {
        continue;
    }
    $ridPortal = (int) ($evPortal['regra_id'] ?? 0);
    if ($ridPortal <= 0) {
        continue;
    }
    $chavePortal = $ridPortal . ':' . (int) ($evPortal['ano_letivo'] ?? 0) . ':' . (int) ($evPortal['bimestre'] ?? 0);
    if (isset($vistosPortalNotas[$chavePortal])) {
        continue;
    }
    $vistosPortalNotas[$chavePortal] = true;
    $boletim_eventos_notas[] = [
        'id' => $ridPortal,
        'nome' => (string) ($evPortal['regra_nome'] ?? ''),
        'codigo' => (string) ($evPortal['regra_codigo'] ?? ''),
        'updated_at' => (string) ($evPortal['updated_at'] ?? ''),
        'default_data_inicio' => (string) ($evPortal['data_inicio'] ?? ''),
        'default_data_fim' => (string) ($evPortal['data_fim'] ?? ''),
        'bimestre' => $evPortal['bimestre'] ?? null,
        'ano_letivo' => $evPortal['ano_letivo'] ?? null,
    ];
}
$boletins_gerados_notas_por_regra = [];
require __DIR__ . '/../admin/students/_secao_notas_eventos.php';
