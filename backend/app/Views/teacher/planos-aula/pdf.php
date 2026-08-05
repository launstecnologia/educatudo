<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plano de Aula - <?= htmlspecialchars($plano['titulo']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px 80px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header-logo {
            max-width: 250px;
            max-height: 100px;
            margin-bottom: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .header h1 {
            font-size: 24pt;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .header p {
            font-size: 11pt;
            color: #666;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 16pt;
            font-weight: bold;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .info-item {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            font-size: 10pt;
            color: #666;
            padding: 8px 15px 8px 0;
            width: 30%;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            font-size: 11pt;
            padding: 8px 0;
            vertical-align: top;
        }
        .content-box {
            background-color: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 11pt;
            line-height: 1.5;
        }
        .resources-list {
            margin-top: 10px;
        }
        .resource-tag {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 15px;
            font-size: 10pt;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .container {
                max-width: 100%;
                padding: 20px;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if (!empty($logo_horizontal_url)): ?>
                <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="header-logo">
            <?php elseif (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo" class="header-logo">
            <?php endif; ?>
            <h1>PLANO DE AULA</h1>
            <p><?= htmlspecialchars($system_title ?? 'EducaTudo') ?> - Sistema de Gestão Educacional</p>
        </div>

        <!-- Informações Básicas -->
        <div class="section">
            <div class="section-title">Informações Básicas</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Título</div>
                    <div class="info-value"><?= htmlspecialchars($plano['titulo'] ?? '') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data da Aula</div>
                    <div class="info-value">
                        <?php
                        $datas = [];
                        if (!empty($plano['data_aula'])) {
                            $datasJson = json_decode($plano['data_aula'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($datasJson)) {
                                $datas = $datasJson;
                            } else {
                                $datas = [$plano['data_aula']];
                            }
                        }
                        ?>
                        <?php if (!empty($datas)): ?>
                            <?php foreach ($datas as $index => $dataItem): ?>
                                <?= date('d/m/Y', strtotime($dataItem)) ?><?= $index < count($datas) - 1 ? ', ' : '' ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Matéria</div>
                    <div class="info-value"><?= htmlspecialchars($plano['materia_nome'] ?? '') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Turma</div>
                    <div class="info-value"><?= htmlspecialchars($plano['turma_nome'] ?? '') ?></div>
                </div>
                <?php if (!empty($plano['professor_nome'])): ?>
                <div class="info-item">
                    <div class="info-label">Professor</div>
                    <div class="info-value"><?= htmlspecialchars($plano['professor_nome']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano['ano_disciplina'])): ?>
                <div class="info-item">
                    <div class="info-label">Ano/Disciplina</div>
                    <?php $anoDisciplina = preg_replace('/\s*\(\+\d+\)/', '', $plano['ano_disciplina']); ?>
                    <div class="info-value"><?= htmlspecialchars($anoDisciplina) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano['dias_aula'])): ?>
                <div class="info-item">
                    <div class="info-label">Dias da Aula</div>
                    <div class="info-value"><?= htmlspecialchars($plano['dias_aula']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estrutura do Conteúdo -->
        <div class="section">
            <div class="section-title">Estrutura do Conteúdo</div>
            <div class="info-grid">
                <?php if (!empty($plano['modulo'])): ?>
                <div class="info-item">
                    <div class="info-label">Módulo</div>
                    <div class="info-value"><?= htmlspecialchars($plano['modulo']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano['aula_num'])): ?>
                <div class="info-item">
                    <div class="info-label">Aula Nº</div>
                    <div class="info-value"><?= htmlspecialchars($plano['aula_num']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano['paginas'])): ?>
                <div class="info-item">
                    <div class="info-label">Páginas</div>
                    <div class="info-value"><?= htmlspecialchars($plano['paginas']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($plano['conteudo'])): ?>
            <div style="margin-top: 15px;">
                <div class="info-label" style="display: block; margin-bottom: 5px;">Conteúdo</div>
                <div class="content-box"><?= htmlspecialchars($plano['conteudo']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($plano['conteudo_lista'])): ?>
            <div style="margin-top: 15px;">
                <div class="info-label" style="display: block; margin-bottom: 5px;">Lista de Conteúdos</div>
                <div class="content-box"><?= htmlspecialchars($plano['conteudo_lista']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Objetivos -->
        <?php if (!empty($plano['objetivos']) || !empty($plano['objetivos_lista'])): ?>
        <div class="section">
            <div class="section-title">Objetivos</div>
            <?php if (!empty($plano['objetivos'])): ?>
            <div style="margin-bottom: 15px;">
                <div class="info-label" style="display: block; margin-bottom: 5px;">O Aluno deverá ser capaz de:</div>
                <div class="content-box"><?= htmlspecialchars($plano['objetivos']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($plano['objetivos_lista'])): ?>
            <div>
                <div class="info-label" style="display: block; margin-bottom: 5px;">Lista de Objetivos Específicos</div>
                <div class="content-box"><?= htmlspecialchars($plano['objetivos_lista']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Recursos -->
        <?php if (!empty($plano['recursos']) || !empty($plano['recursos_lista'])): ?>
        <div class="section">
            <div class="section-title">Recursos</div>
            <?php if (!empty($plano['recursos'])): ?>
            <div style="margin-bottom: 15px;">
                <div class="info-label" style="display: block; margin-bottom: 5px;">Ferramentas utilizadas para que os objetivos sejam atingidos:</div>
                <div class="content-box"><?= htmlspecialchars($plano['recursos']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($plano['recursos_lista'])): ?>
            <div>
                <div class="info-label" style="display: block; margin-bottom: 5px;">Recursos Selecionados</div>
                <div class="resources-list">
                    <?php 
                    $recursos = is_array($plano['recursos_lista']) ? $plano['recursos_lista'] : json_decode($plano['recursos_lista'], true);
                    if ($recursos && is_array($recursos)): 
                        foreach ($recursos as $recurso): ?>
                            <span class="resource-tag"><?= htmlspecialchars($recurso) ?></span>
                        <?php endforeach; 
                    else: ?>
                        <div class="content-box"><?= htmlspecialchars($plano['recursos_lista']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Avaliação -->
        <?php if (!empty($plano['avaliacao']) || !empty($plano['avaliacao_apostila']) || !empty($plano['avaliacao_conteudo']) || !empty($plano['avaliacao_paginas'])): ?>
        <div class="section">
            <div class="section-title">Avaliação</div>
            <?php if (!empty($plano['avaliacao'])): ?>
            <div style="margin-bottom: 15px;">
                <div class="info-label" style="display: block; margin-bottom: 5px;">Como será avaliado</div>
                <div class="content-box"><?= htmlspecialchars($plano['avaliacao']) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-grid">
                <?php if (!empty($plano['avaliacao_apostila'])): ?>
                <div class="info-item">
                    <div class="info-label">Apostila da Avaliação</div>
                    <div class="info-value"><?= htmlspecialchars($plano['avaliacao_apostila']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano['avaliacao_conteudo'])): ?>
                <div class="info-item">
                    <div class="info-label">Conteúdo da Avaliação</div>
                    <div class="info-value"><?= htmlspecialchars($plano['avaliacao_conteudo']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($plano['avaliacao_paginas'])): ?>
                <div class="info-item">
                    <div class="info-label">Páginas da Avaliação</div>
                    <div class="info-value"><?= htmlspecialchars($plano['avaliacao_paginas']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Aulas da Tarde (Oficinas) -->
        <?php if (!empty($plano['aulas_tarde_oficinas'])): ?>
        <?php require_once __DIR__ . '/../../../Helpers/LessonPlanAfternoonHelper.php'; ?>
        <div class="section">
            <div class="section-title">Aulas da Tarde (Oficinas de Aprendizagem / Salas de Estudo)</div>
            <div class="content-box"><?= nl2br(htmlspecialchars(LessonPlanAfternoonHelper::renderPlainText($plano['aulas_tarde_oficinas']))) ?></div>
        </div>
        <?php endif; ?>

        <!-- Observações -->
        <?php if (!empty($plano['observacoes'])): ?>
        <div class="section">
            <div class="section-title">Observações</div>
            <div class="content-box"><?= htmlspecialchars($plano['observacoes']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>Documento gerado em <?= date('d/m/Y H:i') ?> - <?= htmlspecialchars($system_title ?? 'EducaTudo') ?></p>
            <p>Criado em: <?= date('d/m/Y H:i', strtotime($plano['created_at'])) ?> | 
               Última atualização: <?= date('d/m/Y H:i', strtotime($plano['updated_at'])) ?></p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
