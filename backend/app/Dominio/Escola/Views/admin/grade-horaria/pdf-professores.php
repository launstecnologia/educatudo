<?php
$gradesProfessores = is_array($grades_professores ?? null) ? $grades_professores : [];
$filtrosBase = is_array($filtros ?? null) ? $filtros : [];
$geradoEm = (string) ($gerado_em ?? date('d/m/Y H:i'));
$fonte = '8px';
$pad = '3px 4px';
$larguraHora = '52px';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { margin: 0; padding: 0; color: #111827; font-size: <?= $fonte ?>; }
        .folha { page-break-before: always; }
        .folha:first-child { page-break-before: auto; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { color: #4b5563; font-size: 8px; margin: 0 0 8px; }
        table.grade { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grade th, table.grade td {
            border: 1px solid #d1d5db;
            padding: <?= $pad ?>;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.grade th {
            background: #f3f4f6;
            text-align: center;
            font-size: 8px;
            text-transform: uppercase;
            color: #374151;
        }
        table.grade .hora {
            width: <?= $larguraHora ?>;
            text-align: left;
            background: #f9fafb;
            font-weight: bold;
            font-size: 8px;
        }
        .hora span { display: block; color: #6b7280; font-weight: normal; }
        .aula {
            border: 1px solid #e5e7eb;
            border-radius: 3px;
            padding: 2px 4px;
            margin: 0 0 3px;
            page-break-inside: avoid;
        }
        .aula strong { display: block; font-size: 8px; line-height: 1.25; }
        .aula small { display: block; font-size: 7px; line-height: 1.2; }
        .vazio { color: #9ca3af; text-align: center; font-size: 7px; }
        .intervalo {
            text-align: center;
            background: #f1f5f9;
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .resumo { margin-top: 8px; font-size: 8px; color: #374151; }
        .resumo span { margin-right: 12px; }
        .foot { margin-top: 6px; font-size: 7px; color: #9ca3af; }
    </style>
</head>
<body>
<?php if ($gradesProfessores === []): ?>
    <h1>Grade dos professores</h1>
    <p class="meta">Gerado em <?= htmlspecialchars($geradoEm) ?></p>
    <p class="vazio">Nenhum professor com aulas nesta seleção.</p>
<?php else: ?>
    <?php foreach ($gradesProfessores as $gradeProfessor):
        $itens = is_array($gradeProfessor['itens'] ?? null) ? $gradeProfessor['itens'] : [];
        $filtros = $filtrosBase;
        $filtros['professor_id'] = (int) ($gradeProfessor['id'] ?? 0);
        $filtros['turma_id'] = 0;
        $filtros['visao'] = 'semana';
        $filtros['dia'] = 0;
        include __DIR__ . '/_preparar.php';

        $nomeProfessor = trim((string) ($gradeProfessor['nome'] ?? ''));
        if ($nomeProfessor === '') {
            $nomeProfessor = 'Professor';
        }
        $turmasNomes = [];
        foreach ($itens as $itemProfessor) {
            $nomeTurmaItem = trim((string) ($itemProfessor['turma_nome'] ?? ''));
            if ($nomeTurmaItem !== '') {
                $turmasNomes[$nomeTurmaItem] = true;
            }
        }
        $contexto = [];
        foreach ($partesFiltro as $parteFiltro) {
            if (strpos($parteFiltro, 'Professor:') === 0) {
                continue;
            }
            $contexto[] = $parteFiltro;
        }
    ?>
    <div class="folha">
        <h1><?= htmlspecialchars($nomeProfessor) ?></h1>
        <p class="meta">
            Grade horária · turma e sala em cada horário
            <?php if ($contexto !== []): ?>
                · <?= htmlspecialchars(implode(' · ', $contexto)) ?>
            <?php endif; ?>
            · <?= (int) $resumo['aulas'] ?> aula(s)
            · <?= count($turmasNomes) ?> turma(s)
            <?php if ($resumo['salas'] > 0): ?>
                · <?= (int) $resumo['salas'] ?> sala(s)
            <?php endif; ?>
            · Gerado em <?= htmlspecialchars($geradoEm) ?>
        </p>

        <?php if ($linhasGrade === []): ?>
            <p class="vazio">Nenhuma aula nesta semana.</p>
        <?php else: ?>
            <table class="grade">
                <thead>
                    <tr>
                        <th class="hora">Horário</th>
                        <?php foreach ($diasVisiveis as $nomeDia): ?>
                            <th><?= htmlspecialchars($nomeDia) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linhasGrade as $linha): ?>
                        <?php if (($linha['tipo'] ?? '') === 'intervalo'): ?>
                            <tr>
                                <td colspan="<?= 1 + $colunasDias ?>" class="intervalo">
                                    Intervalo <?= htmlspecialchars($linha['de']) ?>–<?= htmlspecialchars($linha['ate']) ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="hora">
                                    <?= htmlspecialchars($linha['de']) ?>
                                    <span><?= htmlspecialchars($linha['ate']) ?></span>
                                </td>
                                <?php foreach (array_keys($diasVisiveis) as $diaNum): ?>
                                    <?php $aulasCelula = $linha['aulas'][$diaNum] ?? []; ?>
                                    <td>
                                        <?php if ($aulasCelula === []): ?>
                                            <div class="vazio">Livre</div>
                                        <?php else: ?>
                                            <?php foreach ($aulasCelula as $aula):
                                                $cor = $coresPorMateria[(int) ($aula['materia_id'] ?? 0)] ?? $paletaCores[0];
                                                $salaAula = trim((string) ($aula['sala_nome'] ?? ''));
                                                $turmaAula = trim((string) ($aula['turma_nome'] ?? ''));
                                            ?>
                                                <div class="aula" style="background:<?= htmlspecialchars($cor['hex_bg']) ?>;color:<?= htmlspecialchars($cor['hex_text']) ?>;border-color:<?= htmlspecialchars($cor['hex_border']) ?>;">
                                                    <strong><?= htmlspecialchars($turmaAula !== '' ? $turmaAula : 'Turma') ?></strong>
                                                    <?php if ($salaAula !== ''): ?>
                                                        <small><?= htmlspecialchars($salaAula) ?></small>
                                                    <?php endif; ?>
                                                    <small><?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="resumo">
            <span><strong>Aulas:</strong> <?= (int) $resumo['aulas'] ?></span>
            <span><strong>Turmas:</strong> <?= count($turmasNomes) ?></span>
            <span><strong>Componentes:</strong> <?= (int) $resumo['componentes'] ?></span>
            <?php if ($resumo['salas'] > 0): ?>
                <span><strong>Salas:</strong> <?= (int) $resumo['salas'] ?></span>
            <?php endif; ?>
        </div>
        <p class="foot">Documento gerado pelo EducaTudo.</p>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
