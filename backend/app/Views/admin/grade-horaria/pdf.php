<?php
include __DIR__ . '/_preparar.php';
$geradoEm = (string) ($gerado_em ?? date('d/m/Y H:i'));
$paisagem = !empty($pdfPaisagem);
$fonte = $paisagem ? '8px' : '9px';
$pad = $paisagem ? '3px 4px' : '5px 6px';
$larguraHora = $paisagem ? '52px' : '70px';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { margin: 0; padding: 0; color: #111827; font-size: <?= $fonte ?>; }
        h1 { font-size: <?= $paisagem ? '14px' : '16px' ?>; margin: 0 0 2px; }
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
        table.lista { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.lista th, table.lista td {
            border: 1px solid #d1d5db;
            padding: <?= $pad ?>;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-size: <?= $fonte ?>;
        }
        table.lista th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; color: #374151; }
    </style>
</head>
<body>
    <h1>Grade Horária de Aulas — <?= htmlspecialchars($rotuloToolbar) ?></h1>
    <p class="meta">
        <?= htmlspecialchars($rotuloFiltrosTxt) ?>
        · <?= (int) $resumo['aulas'] ?> aula(s)
        · Gerado em <?= htmlspecialchars($geradoEm) ?>
    </p>

    <?php if ($visao === 'lista'): ?>
        <table class="lista">
            <thead>
                <tr>
                    <th style="width:12%">Dia</th>
                    <th style="width:14%">Horário</th>
                    <th>Componente</th>
                    <th>Professor</th>
                    <?php if ($mostrarTurmaNoCard): ?><th style="width:12%">Turma</th><?php endif; ?>
                    <?php if ($resumo['salas'] > 0): ?><th style="width:12%">Sala</th><?php endif; ?>
                    <th style="width:10%">Período</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($itens === []): ?>
                    <tr><td colspan="7" class="vazio">Nenhuma aula encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($diasCurtos[(int) ($item['dia_semana'] ?? 0)] ?? '') ?></td>
                            <td><?= htmlspecialchars(substr((string) ($item['horario_de'] ?? ''), 0, 5)) ?>–<?= htmlspecialchars(substr((string) ($item['horario_ate'] ?? ''), 0, 5)) ?></td>
                            <td><?= htmlspecialchars((string) ($item['materia_nome'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($item['professor_nome'] ?? '')) ?></td>
                            <?php if ($mostrarTurmaNoCard): ?>
                                <td><?= htmlspecialchars((string) ($item['turma_nome'] ?? '')) ?></td>
                            <?php endif; ?>
                            <?php if ($resumo['salas'] > 0): ?>
                                <td><?= htmlspecialchars((string) ($item['sala_nome'] ?? '—')) ?></td>
                            <?php endif; ?>
                            <td><?= (($item['periodo'] ?? '') === 'tarde') ? 'Tarde' : 'Manhã' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($linhasGrade === []): ?>
        <p class="vazio">Nenhuma aula nesta visão.</p>
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
                                        <div class="vazio">—</div>
                                    <?php else: ?>
                                        <?php foreach ($aulasCelula as $aula):
                                            $cor = $coresPorMateria[(int) ($aula['materia_id'] ?? 0)] ?? $paletaCores[0];
                                            $salaAula = trim((string) ($aula['sala_nome'] ?? ''));
                                        ?>
                                            <div class="aula" style="background:<?= htmlspecialchars($cor['hex_bg']) ?>;color:<?= htmlspecialchars($cor['hex_text']) ?>;border-color:<?= htmlspecialchars($cor['hex_border']) ?>;">
                                                <strong><?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?></strong>
                                                <small><?= htmlspecialchars($nomeProfessorCurto((string) ($aula['professor_nome'] ?? ''))) ?></small>
                                                <?php if ($mostrarTurmaNoCard): ?>
                                                    <small><?= htmlspecialchars((string) ($aula['turma_nome'] ?? '')) ?></small>
                                                <?php endif; ?>
                                                <?php if ($salaAula !== ''): ?>
                                                    <small><?= htmlspecialchars($salaAula) ?></small>
                                                <?php endif; ?>
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
        <span><strong>Componentes:</strong> <?= (int) $resumo['componentes'] ?></span>
        <span><strong>Professores:</strong> <?= (int) $resumo['professores'] ?></span>
        <?php if ($resumo['salas'] > 0): ?>
            <span><strong>Salas:</strong> <?= (int) $resumo['salas'] ?></span>
        <?php endif; ?>
    </div>
    <p class="foot">Documento gerado pelo EducaTudo.</p>
</body>
</html>
