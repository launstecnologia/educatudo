<?php
/**
 * EducaTudo - Admin Student Profile Service
 *
 * Concentra toda a busca de dados exibidos em admin/students/show.php
 * (extraído de AdminController::mostrarAluno() — extração pura, sem
 * alteração de lógica de negócio).
 */

namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Core/AdminPermissionMatrix.php';
require_once __DIR__ . '/../Core/Logger.php';
require_once __DIR__ . '/../Helpers/StudentPhotoHelper.php';
require_once __DIR__ . '/../Helpers/TurmaLabelHelper.php';
require_once __DIR__ . '/../Models/System/BoletimConfig.php';
require_once __DIR__ . '/AlunoTurmaResolver.php';

class AdminStudentProfileService
{
    private $db;

    /**
     * Controller que fornece helpers compartilhados com outros métodos de
     * AdminController (sqlTurmaLabelFieldsAndJoins, resolverAnoLetivoIdParaTurma,
     * buildProvasMatrizPorBlocoAplicado, syncAlunoStatusMatricula, parseSeriesIdsRaw,
     * boletimObservacaoSafe, coordenacaoPodeEditarBoletim).
     *
     * @var object
     */
    private $controller;

    public function __construct($db, $controller)
    {
        $this->db = $db;
        $this->controller = $controller;
    }

    /**
     * Monta o array de dados completo para a view admin/students/show.
     * Lança Exception se o aluno não for encontrado (o controller decide o redirect).
     */
    public function getStudentProfile(int $id, array $user): array
    {
        $adminPermissions = \AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);

        try {
            $turmaLabelSql = $this->controller->sqlTurmaLabelFieldsAndJoins();
            $turnoSelect = $this->temColuna('turmas', 'turno') ? ', t.turno as turma_turno' : '';
            $aluno = $this->db->fetch(
                "SELECT a.*, t.nome as turma_nome, t.serie_id as turma_serie_id{$turnoSelect},
                        {$turmaLabelSql['select']},
                        COALESCE(
                            (SELECT GROUP_CONCAT(DISTINCT r2.nome ORDER BY r2.nome SEPARATOR ', ')
                             FROM alunos_responsaveis ar2
                             INNER JOIN responsaveis r2 ON r2.id = ar2.responsavel_id
                             WHERE ar2.aluno_id = a.id AND ar2.ativo = 1),
                            p.nome
                        ) as responsavel_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON a.turma_id = t.id
                 {$turmaLabelSql['joins']}
                 LEFT JOIN responsaveis p ON a.responsavel_id = p.id
                 WHERE a.id = :id",
                ['id' => $id]
            );

            if (!$aluno) {
                throw new \RuntimeException('Aluno não encontrado', 404);
            }

            // Garantir que todos os campos essenciais existam
            $aluno['nome'] = $aluno['nome'] ?? '';
            $aluno['ra'] = $aluno['ra'] ?? '';
            $aluno['email'] = $aluno['email'] ?? null;
            $aluno['turma_nome'] = $aluno['turma_nome'] ?? null;
            $aluno['responsavel_nome'] = $aluno['responsavel_nome'] ?? null;
            $aluno['ativo'] = isset($aluno['ativo']) ? (int)$aluno['ativo'] : 1;

            $aluno = \StudentPhotoHelper::enrichStudent($aluno, true);
            $aluno['turma_display'] = \TurmaLabelHelper::formatListLabel($aluno);
            if (empty($aluno['serie']) && !empty($aluno['serie_nome'])) {
                $aluno['serie'] = $aluno['serie_nome'];
            } elseif (empty($aluno['serie']) && !empty($aluno['turma_serie'])) {
                $aluno['serie'] = $aluno['turma_serie'];
            }

            $aluno['numero_chamada'] = null;
            if ($this->temTabela('alunos_turma_chamada') && !empty($aluno['turma_id'])) {
                $anoLetivoIdChamada = $this->controller->resolverAnoLetivoIdParaTurma((int) $aluno['turma_id']);
                if ($anoLetivoIdChamada > 0) {
                    $chamadaRow = $this->db->fetch(
                        'SELECT numero_chamada FROM alunos_turma_chamada
                         WHERE aluno_id = :aluno_id AND turma_id = :turma_id AND ano_letivo_id = :ano_id',
                        [
                            'aluno_id' => $id,
                            'turma_id' => (int) $aluno['turma_id'],
                            'ano_id' => $anoLetivoIdChamada,
                        ]
                    );
                    if ($chamadaRow && isset($chamadaRow['numero_chamada'])) {
                        $aluno['numero_chamada'] = (int) $chamadaRow['numero_chamada'];
                    }
                }
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Logger::error("Erro ao buscar aluno ID {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id], 'database');
            throw new \RuntimeException('Erro ao buscar aluno', 0, $e);
        }

        // Responsáveis vinculados ao aluno (modelo N:N)
        $responsaveisAluno = [];
        try {
            $extraVinculo = $this->temColuna('alunos_responsaveis', 'parentesco')
                ? ', ar.parentesco, ar.profissao, ar.empresa, ar.pode_retirar, ar.recebe_boletos, ar.recebe_boletim, ar.recebe_notificacoes, ar.responsavel_pedagogico, ar.guarda_judicial, ar.assina_documentos'
                : '';
            $extraResponsavel = '';
            foreach (['rg', 'celular', 'data_nascimento', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'observacoes'] as $campoResp) {
                if ($this->temColuna('responsaveis', $campoResp)) {
                    $extraResponsavel .= ", r.{$campoResp}";
                }
            }
            $responsaveisAluno = $this->db->fetchAll(
                "SELECT r.id, r.nome, r.email, r.telefone, r.cpf, r.ativo{$extraResponsavel},
                        ar.is_financeiro, ar.tipo_vinculo{$extraVinculo}
                 FROM alunos_responsaveis ar
                 INNER JOIN responsaveis r ON r.id = ar.responsavel_id
                 WHERE ar.aluno_id = :aluno_id AND ar.ativo = 1
                 ORDER BY ar.is_financeiro DESC, r.nome ASC",
                ['aluno_id' => $id]
            );
        } catch (\Exception $e) {
            $responsaveisAluno = [];
        }

        // Ficha complementar (saúde, alimentação, transporte)
        $fichaComplementar = [];
        try {
            require_once __DIR__ . '/../Models/User/StudentComplementaryRecord.php';
            $fichaComplementar = (new \StudentComplementaryRecord())->getByAluno((int) $id);
        } catch (\Throwable $e) {
            $fichaComplementar = [];
        }

        // Documentos / checklist de entrega
        $documentosAluno = [];
        try {
            require_once __DIR__ . '/../Models/User/StudentDocument.php';
            $documentosAluno = (new \StudentDocument())->getByAluno((int) $id);
        } catch (\Throwable $e) {
            $documentosAluno = [];
        }

        // Duas últimas ações sensíveis para o resumo da ficha; o histórico completo
        // continua na página própria /admin/students/{id}/auditoria.
        $auditLogs = $this->getAuditTrail($id, 2);

        // ========== RELATÓRIO/ESTATÍSTICAS ==========
        try {
            $stats = [
                'jornadas_concluidas' => 0,
                'exercicios_resolvidos' => 0,
                'exercicios_ia_resolvidos' => 0,
                'redacoes_total' => 0,
                'redacoes_corrigidas' => 0,
                'conversas_total' => 0,
                'interacoes_chat' => 0,
                'media_exercicios' => 0,
                'media_redacoes' => 0,
                'total_flashcards' => 0,
                'mural_recados_total' => 0,
                'mural_recados_vistos' => 0,
            ];

            // Perf: as 7 consultas abaixo (jornadas, exercícios, redações x3, conversas
            // x2) batem em tabelas-core que sempre existem; juntamos em 1 única
            // ida ao banco via subqueries escalares em vez de 7 round-trips
            // sequenciais — cada um custa ~5-15ms de latência de rede até o
            // servidor de banco, que é remoto (não é localhost).
            $statsCoreOk = false;
            try {
                $statsCore = $this->db->fetch(
                    "SELECT
                        (SELECT COUNT(DISTINCT jornada_id) FROM jornadas_progresso_alunos WHERE aluno_id = :id1 AND status = 'concluido') AS jornadas_concluidas,
                        (SELECT COUNT(DISTINCT h.id) FROM exercicios_historico h WHERE h.aluno_id = :id2) AS exercicios_resolvidos,
                        (SELECT AVG(percentual_acerto) FROM exercicios_historico WHERE aluno_id = :id3) AS media_exercicios,
                        (SELECT COUNT(*) FROM redacoes WHERE aluno_id = :id4) AS redacoes_total,
                        (SELECT COUNT(*) FROM redacoes WHERE aluno_id = :id5 AND (corrigida_em IS NOT NULL OR correcao IS NOT NULL OR feedback_ia IS NOT NULL OR nota IS NOT NULL OR nota_final IS NOT NULL)) AS redacoes_corrigidas,
                        (SELECT AVG(COALESCE(nota, nota_final)) FROM redacoes WHERE aluno_id = :id6 AND (nota IS NOT NULL OR nota_final IS NOT NULL)) AS media_redacoes,
                        (SELECT COUNT(*) FROM tudinha_conversas WHERE aluno_id = :id7 AND excluida = 0) AS conversas_total,
                        (SELECT COUNT(*) FROM tudinha_mensagens mc INNER JOIN tudinha_conversas cc ON mc.conversa_id = cc.id WHERE cc.aluno_id = :id8 AND cc.excluida = 0) AS interacoes_chat",
                    ['id1' => $id, 'id2' => $id, 'id3' => $id, 'id4' => $id, 'id5' => $id, 'id6' => $id, 'id7' => $id, 'id8' => $id]
                );
                if ($statsCore) {
                    $stats['jornadas_concluidas'] = $statsCore['jornadas_concluidas'] ?? 0;
                    $stats['exercicios_resolvidos'] = $statsCore['exercicios_resolvidos'] ?? 0;
                    $stats['media_exercicios'] = $statsCore['media_exercicios'] ?? 0;
                    $stats['redacoes_total'] = $statsCore['redacoes_total'] ?? 0;
                    $stats['redacoes_corrigidas'] = $statsCore['redacoes_corrigidas'] ?? 0;
                    $stats['media_redacoes'] = $statsCore['media_redacoes'] ?? 0;
                    $stats['conversas_total'] = $statsCore['conversas_total'] ?? 0;
                    $stats['interacoes_chat'] = $statsCore['interacoes_chat'] ?? 0;
                    $statsCoreOk = true;
                }
            } catch (\Exception $e) {
                \Logger::databaseError("Erro ao buscar stats combinadas (fallback para queries individuais) aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
            }

            if (!$statsCoreOk) {
                try {
                    $result = $this->db->fetch("SELECT COUNT(DISTINCT jornada_id) as total FROM jornadas_progresso_alunos WHERE aluno_id = :id AND status = 'concluido'", ['id' => $id]);
                    $stats['jornadas_concluidas'] = $result['total'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar jornadas_concluidas para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }

                try {
                    $result = $this->db->fetch("SELECT COUNT(DISTINCT h.id) as total FROM exercicios_historico h WHERE h.aluno_id = :id", ['id' => $id]);
                    $stats['exercicios_resolvidos'] = $result['total'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar exercicios_resolvidos para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }

                try {
                    $result = $this->db->fetch("SELECT COUNT(*) as total FROM redacoes WHERE aluno_id = :id", ['id' => $id]);
                    $stats['redacoes_total'] = $result['total'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar redacoes_total para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }

                try {
                    $result = $this->db->fetch("SELECT COUNT(*) as total FROM redacoes WHERE aluno_id = :id AND (corrigida_em IS NOT NULL OR correcao IS NOT NULL OR feedback_ia IS NOT NULL OR nota IS NOT NULL OR nota_final IS NOT NULL)", ['id' => $id]);
                    $stats['redacoes_corrigidas'] = $result['total'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar redacoes_corrigidas para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }

                try {
                    $result = $this->db->fetch("SELECT COUNT(*) as total FROM tudinha_conversas WHERE aluno_id = :id AND excluida = 0", ['id' => $id]);
                    $stats['conversas_total'] = $result['total'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar conversas_total para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }

                try {
                    $result = $this->db->fetch("SELECT COUNT(*) as total FROM tudinha_mensagens mc INNER JOIN tudinha_conversas cc ON mc.conversa_id = cc.id WHERE cc.aluno_id = :id AND cc.excluida = 0", ['id' => $id]);
                    $stats['interacoes_chat'] = $result['total'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar interacoes_chat para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }

                try {
                    $result = $this->db->fetch("SELECT AVG(percentual_acerto) as media FROM exercicios_historico WHERE aluno_id = :id", ['id' => $id]);
                    $stats['media_exercicios'] = $result['media'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar media_exercicios para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }

                try {
                    $result = $this->db->fetch("SELECT AVG(COALESCE(nota, nota_final)) as media FROM redacoes WHERE aluno_id = :id AND (nota IS NOT NULL OR nota_final IS NOT NULL)", ['id' => $id]);
                    $stats['media_redacoes'] = $result['media'] ?? 0;
                } catch (\Exception $e) {
                    \Logger::databaseError("Erro ao buscar media_redacoes para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
                }
            }

            try {
                $result = $this->db->fetch("SELECT COUNT(DISTINCT sep.id) as total FROM listas_personalizadas_sessoes sep WHERE sep.aluno_id = :id AND sep.status = 'finalizado'", ['id' => $id]);
                $stats['exercicios_ia_resolvidos'] = $result['total'] ?? 0;
            } catch (\Exception $e) {
                \Logger::databaseError("Erro ao buscar exercicios_ia_resolvidos para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
            }

            try {
                $result = $this->db->fetch("SELECT COUNT(*) as total FROM flashcard_explicacoes WHERE aluno_id = :id", ['id' => $id]);
                $stats['total_flashcards'] = $result['total'] ?? 0;
            } catch (\Exception $e) {
                $stats['total_flashcards'] = 0;
            }

            $turma_id = isset($aluno['turma_id']) ? (int)$aluno['turma_id'] : 0;
            try {
                $sqlTotal = "SELECT COUNT(*) as total FROM mural_recados r
                    WHERE (r.enviar_para_todos = 1" . ($turma_id > 0 ? " OR EXISTS (SELECT 1 FROM mural_recados_turmas rt WHERE rt.mural_recado_id = r.id AND rt.turma_id = :turma_id)" : "") . ")
                    AND (CURDATE() <= r.data_sai_mural)";
                $paramsTotal = $turma_id > 0 ? ['turma_id' => $turma_id] : [];
                $result = $this->db->fetch($sqlTotal, $paramsTotal);
                $stats['mural_recados_total'] = $result['total'] ?? 0;
            } catch (\Exception $e) {
                $stats['mural_recados_total'] = 0;
            }
            try {
                $result = $this->db->fetch("SELECT COUNT(*) as total FROM mural_recados_vistos WHERE aluno_id = :id", ['id' => $id]);
                $stats['mural_recados_vistos'] = $result['total'] ?? 0;
            } catch (\Exception $e) {
                $stats['mural_recados_vistos'] = 0;
            }
        } catch (\Exception $e) {
            \Logger::databaseError("Erro geral ao buscar estatísticas para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
            $stats = [
                'jornadas_concluidas' => 0,
                'exercicios_resolvidos' => 0,
                'exercicios_ia_resolvidos' => 0,
                'redacoes_total' => 0,
                'redacoes_corrigidas' => 0,
                'conversas_total' => 0,
                'interacoes_chat' => 0,
                'media_exercicios' => 0,
                'media_redacoes' => 0,
                'total_flashcards' => 0,
                'mural_recados_total' => 0,
                'mural_recados_vistos' => 0,
            ];
        }

        // ========== CONVERSAS/INTERAÇÕES TUDINHA ==========
        // Perf: a listagem detalhada de conversas/mensagens da Tudinha não é
        // renderizada em admin/students/show.php (dados mortos, como as abas de
        // exercícios removidas acima) e era a consulta mais pesada da tela:
        // 5 subqueries correlacionadas × 50 conversas + fetch de todas as
        // mensagens. Os contadores exibidos na tela vêm do bloco de stats.
        // A "Análise da Tudinha" usa endpoint próprio (analise-tudinha).
        $conversas = [];
        $conversasDetalhadas = [];

        // ========== EXERCÍCIOS BANCO DE DADOS ==========
        // Perf: "Exercícios do Banco de Dados" e "Exercícios IA" (content-exercicios-bd /
        // content-exercicios-ia) não têm nenhum botão de aba que os abra em
        // admin/students/show.php — são abas mortas/inacessíveis hoje. Evita 3 consultas
        // (com subqueries cada) que nunca chegam a ser exibidas. Se a aba for reativada
        // no futuro, basta restaurar estas consultas.
        $exerciciosBD = [];
        $exerciciosIA = [];
        $listasPersonalizadasAluno = [];

        // ========== REDAÇÕES ==========
        try {
            $redacoes = $this->db->fetchAll(
                "SELECT r.*,
                        CASE
                            WHEN r.corrigida_em IS NOT NULL OR r.correcao IS NOT NULL OR r.feedback_ia IS NOT NULL OR r.nota IS NOT NULL OR r.nota_final IS NOT NULL THEN 'Corrigida'
                            ELSE 'Pendente'
                        END as status_descricao
                 FROM redacoes r
                 WHERE r.aluno_id = :id
                 ORDER BY r.created_at DESC
                 LIMIT 50",
                ['id' => $id]
            );
        } catch (\Exception $e) {
            \Logger::databaseError("Erro ao buscar redacoes para aluno {$id}: " . $e->getMessage(), ['exception' => $e, 'aluno_id' => $id]);
            $redacoes = [];
        }

        // Garantir que todos os arrays estão definidos
        $conversas = $conversas ?? [];
        $conversasDetalhadas = $conversasDetalhadas ?? [];
        $exerciciosBD = $exerciciosBD ?? [];
        $exerciciosIA = $exerciciosIA ?? [];
        $redacoes = $redacoes ?? [];
        $stats = $stats ?? [
            'jornadas_concluidas' => 0,
            'exercicios_resolvidos' => 0,
            'exercicios_ia_resolvidos' => 0,
            'redacoes_total' => 0,
            'redacoes_corrigidas' => 0,
            'conversas_total' => 0,
            'interacoes_chat' => 0,
            'media_exercicios' => 0,
            'media_redacoes' => 0,
        ];

        // Histórico de turmas do aluno
        try {
            $historico_turmas = $this->db->fetchAll(
                "SELECT h.ano_letivo, h.data_inicio, h.data_fim, t.nome as turma_nome, t.tipo_ensino
                 FROM alunos_turmas_historico h
                 LEFT JOIN turmas t ON t.id = h.turma_id
                 WHERE h.aluno_id = :aluno_id
                 ORDER BY h.data_inicio DESC",
                ['aluno_id' => $id]
            );
        } catch (\Exception $e) {
            $historico_turmas = [];
        }

        $ocorrenciasAluno = [];
        try {
            $ocorrenciasAluno = $this->db->fetchAll(
                "SELECT o.*, MAX(u.nome) as criado_por_nome, MAX(cat.nome) as categoria_nome
                 FROM alunos_ocorrencias o
                 LEFT JOIN alunos_ocorrencias_itens oi ON oi.ocorrencia_id = o.id
                 LEFT JOIN usuarios u ON u.id = o.criado_por
                 LEFT JOIN ocorrencias_categorias cat ON cat.id = o.categoria_id
                 WHERE o.aluno_id = :aluno_id OR oi.aluno_id = :aluno_id2
                 GROUP BY o.id
                 ORDER BY o.data_ocorrencia DESC, o.created_at DESC
                 LIMIT 100",
                ['aluno_id' => $id, 'aluno_id2' => $id]
            );
        } catch (\Exception $e) {
            try {
                $ocorrenciasAluno = $this->db->fetchAll(
                    "SELECT o.*, MAX(u.nome) as criado_por_nome
                     FROM alunos_ocorrencias o
                     LEFT JOIN alunos_ocorrencias_itens oi ON oi.ocorrencia_id = o.id
                     LEFT JOIN usuarios u ON u.id = o.criado_por
                     WHERE o.aluno_id = :aluno_id OR oi.aluno_id = :aluno_id2
                     GROUP BY o.id
                     ORDER BY o.data_ocorrencia DESC, o.created_at DESC
                     LIMIT 100",
                    ['aluno_id' => $id, 'aluno_id2' => $id]
                );
            } catch (\Exception $e2) {
                $ocorrenciasAluno = [];
            }
        }

        // Jornadas feitas (concluídas) pelo aluno
        $jornadas_feitas = [];
        try {
            $jornadas_feitas = $this->db->fetchAll(
                "SELECT j.id, j.titulo, MAX(jpa.data_conclusao) as data_conclusao
                 FROM jornadas_progresso_alunos jpa
                 INNER JOIN jornadas j ON j.id = jpa.jornada_id
                 WHERE jpa.aluno_id = :id AND jpa.status = 'concluido'
                 GROUP BY j.id, j.titulo
                 ORDER BY data_conclusao DESC
                 LIMIT 100",
                ['id' => $id]
            );
        } catch (\Exception $e) {
            $jornadas_feitas = [];
        }

        // Perf: a aba "Provas" (provas_realizadas + provas_matriz_blocos) é a mais
        // pesada da tela (várias SHOW COLUMNS + query grande + montagem de matriz) e
        // só é exibida se o admin clicar nela. Passa a ser carregada via AJAX sob
        // demanda (ver getProvasTabData()) — aqui só fica o placeholder vazio que o
        // JS substitui no primeiro clique na aba.
        $provas_realizadas = [];
        $provas_matriz_blocos = ['tabelas' => [], 'tem_dados' => false];

        // Histórico de acesso: mesmo banco do "Histórico de logins" (Dev Settings) — tabela alunos_sessoes_acesso
        $historico_acesso = [];
        try {
            $historico_acesso = $this->db->fetchAll(
                "SELECT s.login_at as created_at
                 FROM alunos_sessoes_acesso s
                 WHERE s.aluno_id = :id
                 ORDER BY s.login_at DESC
                 LIMIT 50",
                ['id' => $id]
            );
        } catch (\Exception $e) {
            $historico_acesso = [];
        }

        // Matrículas (tabela matricula - estrutura normalizada): múltiplas turmas/cursos por aluno
        $matriculas = [];
        $matriculas_schema_ready = false;
        $turmas_para_matricula = [];
        $anos_letivo_para_matricula = [];
        try {
            if ($this->temTabela('matricula')) {
                $matriculas_schema_ready = true;
                $matriculas = $this->db->fetchAll(
                    "SELECT m.*, t.nome AS turma_nome, al.ano AS ano_letivo_ano,
                            COALESCE(c.tipo, 'regular') AS curso_tipo
                     FROM matricula m
                     LEFT JOIN turmas t ON t.id = m.turma_id
                     LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id
                     LEFT JOIN curso c ON c.id = t.curso_novo_id
                     WHERE m.aluno_id = :aluno_id
                     ORDER BY m.data_entrada DESC, m.id DESC",
                    ['aluno_id' => $id]
                );
                $turmas_para_matricula = $this->db->fetchAll(
                    "SELECT t.id, t.nome, COALESCE(c.tipo, 'regular') AS curso_tipo
                     FROM turmas t
                     LEFT JOIN curso c ON c.id = t.curso_novo_id
                     WHERE t.ativo = 1
                     ORDER BY t.nome ASC"
                );
                $anos_letivo_para_matricula = $this->db->fetchAll("SELECT id, ano FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC");
            }
        } catch (\Exception $e) {
            $matriculas = [];
        }

        if ($matriculas_schema_ready) {
            $this->controller->syncAlunoStatusMatricula((int) $id);
            $statusRow = $this->db->fetch('SELECT ativo, status FROM alunos WHERE id = :id', ['id' => $id]);
            if ($statusRow) {
                $aluno['ativo'] = $statusRow['ativo'];
                $aluno['status'] = $statusRow['status'];
            }
        }

        $matricula_divergente_cadastro = false;
        $matriculas_paralelas = [];
        if ($matriculas_schema_ready) {
            $turmaResolver = new \App\Services\AlunoTurmaResolver();
            $matricula_divergente_cadastro = $turmaResolver->detectarDivergenciaCadastro((int) $id);
            $matriculas_paralelas = $turmaResolver->listarMatriculasParalelas((int) $id);
            foreach ($matriculas as &$matRow) {
                $matRow['vinculo_rotulo'] = $turmaResolver->rotuloVinculoMatricula(
                    (int) $id,
                    (int) ($matRow['turma_id'] ?? 0),
                    (string) ($matRow['curso_tipo'] ?? 'regular')
                );
            }
            unset($matRow);
        }

        $boletimEventosNotas = [];
        $boletimEventosBoletim = [];
        $boletinsGeradosCoordenacao = [];
        $boletinsGeradosNotasPorRegra = [];
        try {
            $boletimCfg = new \BoletimConfig();
            // Auto-migração do boletim (~6 CREATE TABLE + ~30 checks em INFORMATION_SCHEMA)
            // só é necessária se o schema base ainda não existe. Na tela de leitura,
            // evitamos rodar isso a cada acesso quando as tabelas já estão presentes.
            if (!$this->temTabela('boletim_resultados_gerados') || !$this->temTabela('boletim_regras')) {
                $boletimCfg->ensureSchema();
            }
            $seriesAlunoIds = [];
            $seriesAlunoOrdinais = [];
            $turmasAlunoIds = [];
            $turmaPrincipalId = (int) ($aluno['turma_id'] ?? 0);
            if ($turmaPrincipalId > 0) {
                $turmasAlunoIds[$turmaPrincipalId] = true;
            }
            $serieAlunoId = (int) ($aluno['turma_serie_id'] ?? 0);
            if ($serieAlunoId > 0) {
                $seriesAlunoIds[$serieAlunoId] = true;
            }
            try {
                if ($matriculas_schema_ready) {
                    $rowsSeriesAtivas = $this->db->fetchAll(
                        "SELECT DISTINCT t.id AS turma_id, t.serie_id, s.nome AS serie_nome
                         FROM matricula m
                         INNER JOIN turmas t ON t.id = m.turma_id
                         LEFT JOIN serie s ON s.id = t.serie_id
                         WHERE m.aluno_id = :aluno_id
                           AND m.status = 'ativa'
                           AND (m.data_saida IS NULL)
                           AND t.serie_id IS NOT NULL",
                        ['aluno_id' => $id]
                    );
                    foreach ($rowsSeriesAtivas as $rs) {
                        $tid = (int) ($rs['turma_id'] ?? 0);
                        if ($tid > 0) {
                            $turmasAlunoIds[$tid] = true;
                        }
                        $sid = (int) ($rs['serie_id'] ?? 0);
                        if ($sid > 0) {
                            $seriesAlunoIds[$sid] = true;
                        }
                        $sNome = trim((string) ($rs['serie_nome'] ?? ''));
                        if ($sNome !== '' && preg_match('/\d+/', $sNome, $mOrd)) {
                            $ord = (int) ($mOrd[0] ?? 0);
                            if ($ord > 0) {
                                $seriesAlunoOrdinais[$ord] = true;
                            }
                        }
                    }
                }
            } catch (\Throwable $eSeries) {
                // fallback pela série da turma principal
            }
            $seriesAlunoIds = array_values(array_keys($seriesAlunoIds));
            $seriesAlunoOrdinais = array_values(array_keys($seriesAlunoOrdinais));
            $turmasAlunoIds = array_values(array_keys($turmasAlunoIds));
            // Regra solicitada: mostrar eventos apenas quando existir tabela gerada para este aluno_id.
            // Ou seja, fonte única = boletim_resultados_gerados desse aluno.
            $geradosCoordenacao = $boletimCfg->getGeneratedBoletinsByAluno((int) $id, 'coordenacao', null);
            $seenNotas = [];
            $seenBoletim = [];
            foreach ($geradosCoordenacao as $ev) {
                $rid = (int) ($ev['regra_id'] ?? 0);
                if ($rid <= 0) {
                    continue;
                }
                $item = [
                    'id' => $rid,
                    'nome' => (string) ($ev['regra_nome'] ?? ''),
                    'codigo' => (string) ($ev['regra_codigo'] ?? ''),
                    'updated_at' => (string) ($ev['updated_at'] ?? ''),
                    'componentes_qtd' => 0,
                    'default_data_inicio' => (string) ($ev['data_inicio'] ?? ''),
                    'default_data_fim' => (string) ($ev['data_fim'] ?? ''),
                    'bimestre' => $ev['bimestre'] ?? null,
                    'ano_letivo' => $ev['ano_letivo'] ?? null,
                ];
                $exibir = strtolower(trim((string) ($ev['exibir_em'] ?? 'boletim')));
                if ($exibir === 'notas') {
                    if (isset($seenNotas[$rid])) {
                        continue;
                    }
                    $seenNotas[$rid] = true;
                    $boletimEventosNotas[] = $item;
                    $boletinsGeradosNotasPorRegra[$rid] = $ev;
                } else {
                    if (isset($seenBoletim[$rid])) {
                        continue;
                    }
                    $seenBoletim[$rid] = true;
                    $boletimEventosBoletim[] = $item;
                    $boletinsGeradosCoordenacao[] = $ev;
                }
            }

            // Fallback de UX: se ainda não houver tabela gerada para este aluno,
            // mantém os eventos configurados visíveis para coordenação na lista
            // para permitir ação/diagnóstico ("sem tabela gerada...").
            if (empty($boletimEventosNotas)) {
                // Em vez de listRulesCatalog(500) + getRuleById() por regra (N+1 de até
                // ~1000 queries), busca as regras candidatas em 1 query e as contagens
                // de componentes em 1 query agrupada.
                $rowsCatalogo = [];
                try {
                    $rowsCatalogo = $this->db->fetchAll(
                        "SELECT id, nome, codigo, updated_at, default_data_inicio, default_data_fim,
                                bimestre, ano_letivo, series_ids, turmas_ids
                         FROM boletim_regras
                         WHERE ativo = 1
                           AND vis_coordenacao = 1
                           AND exibir_em = 'notas'
                           AND codigo IS NOT NULL AND codigo <> ''
                         ORDER BY updated_at DESC, id DESC
                         LIMIT 500"
                    ) ?: [];
                } catch (\Throwable $eCat) {
                    $rowsCatalogo = [];
                }

                $componentesPorRegra = [];
                if (!empty($rowsCatalogo)) {
                    try {
                        $idsRegras = array_map(static fn ($r) => (int) $r['id'], $rowsCatalogo);
                        $phRegras = implode(',', array_fill(0, count($idsRegras), '?'));
                        $rowsComp = $this->db->fetchAll(
                            "SELECT regra_id, COUNT(*) AS total
                             FROM boletim_componentes
                             WHERE ativo = 1 AND regra_id IN ({$phRegras})
                             GROUP BY regra_id",
                            $idsRegras
                        ) ?: [];
                        foreach ($rowsComp as $rc) {
                            $componentesPorRegra[(int) $rc['regra_id']] = (int) $rc['total'];
                        }
                    } catch (\Throwable $eComp) {
                        $componentesPorRegra = [];
                    }
                }

                foreach ($rowsCatalogo as $full) {
                    $rid = (int) ($full['id'] ?? 0);
                    if ($rid <= 0 || isset($seenNotas[$rid])) {
                        continue;
                    }
                    $turmasRegra = $this->controller->parseSeriesIdsRaw($full['turmas_ids'] ?? null);
                    if (!empty($turmasRegra)) {
                        if (empty($turmasAlunoIds) || count(array_intersect($turmasRegra, $turmasAlunoIds)) === 0) {
                            continue;
                        }
                    } else {
                        $seriesRegra = $this->controller->parseSeriesIdsRaw($full['series_ids'] ?? null);
                        if (!empty($seriesRegra)) {
                            $batePorId = !empty($seriesAlunoIds) && count(array_intersect($seriesRegra, $seriesAlunoIds)) > 0;
                            $batePorOrdinal = !empty($seriesAlunoOrdinais) && count(array_intersect($seriesRegra, $seriesAlunoOrdinais)) > 0;
                            if (!$batePorId && !$batePorOrdinal) {
                                continue;
                            }
                        }
                    }
                    $seenNotas[$rid] = true;
                    $boletimEventosNotas[] = [
                        'id' => $rid,
                        'nome' => (string) ($full['nome'] ?? ''),
                        'codigo' => (string) ($full['codigo'] ?? ''),
                        'updated_at' => (string) ($full['updated_at'] ?? ''),
                        'componentes_qtd' => $componentesPorRegra[$rid] ?? 0,
                        'default_data_inicio' => (string) ($full['default_data_inicio'] ?? ''),
                        'default_data_fim' => (string) ($full['default_data_fim'] ?? ''),
                        'bimestre' => isset($full['bimestre']) && $full['bimestre'] !== null ? (int) $full['bimestre'] : null,
                        'ano_letivo' => isset($full['ano_letivo']) && $full['ano_letivo'] !== null ? (int) $full['ano_letivo'] : null,
                    ];
                }
            }

            // Fallback final de segurança: evita tela vazia por qualquer inconsistência
            // de parse/filtro de série; mostra eventos "notas" visíveis para coordenação
            // respeitando a(s) série(s) ativa(s) do aluno.
            if (empty($boletimEventosNotas)) {
                $rowsNotas = $this->db->fetchAll(
                    "SELECT id, nome, codigo, updated_at, default_data_inicio, default_data_fim, series_ids, turmas_ids, bimestre, ano_letivo
                     FROM boletim_regras
                     WHERE ativo = 1
                       AND vis_coordenacao = 1
                       AND exibir_em = 'notas'
                     ORDER BY updated_at DESC, id DESC
                     LIMIT 200"
                ) ?: [];
                foreach ($rowsNotas as $rn) {
                    $rid = (int) ($rn['id'] ?? 0);
                    if ($rid <= 0 || isset($seenNotas[$rid])) {
                        continue;
                    }
                    $turmasRegra = $this->controller->parseSeriesIdsRaw($rn['turmas_ids'] ?? null);
                    if (!empty($turmasRegra)) {
                        if (empty($turmasAlunoIds) || count(array_intersect($turmasRegra, $turmasAlunoIds)) === 0) {
                            continue;
                        }
                    } else {
                        $seriesRegra = $this->controller->parseSeriesIdsRaw($rn['series_ids'] ?? null);
                        if (!empty($seriesRegra)) {
                            $batePorId = !empty($seriesAlunoIds) && count(array_intersect($seriesRegra, $seriesAlunoIds)) > 0;
                            $batePorOrdinal = !empty($seriesAlunoOrdinais) && count(array_intersect($seriesRegra, $seriesAlunoOrdinais)) > 0;
                            if (!$batePorId && !$batePorOrdinal) {
                                continue;
                            }
                        }
                    }
                    $seenNotas[$rid] = true;
                    $boletimEventosNotas[] = [
                        'id' => $rid,
                        'nome' => (string) ($rn['nome'] ?? ''),
                        'codigo' => (string) ($rn['codigo'] ?? ''),
                        'updated_at' => (string) ($rn['updated_at'] ?? ''),
                        'componentes_qtd' => 0,
                        'default_data_inicio' => (string) ($rn['default_data_inicio'] ?? ''),
                        'default_data_fim' => (string) ($rn['default_data_fim'] ?? ''),
                        'bimestre' => isset($rn['bimestre']) && $rn['bimestre'] !== null ? (int) $rn['bimestre'] : null,
                        'ano_letivo' => isset($rn['ano_letivo']) && $rn['ano_letivo'] !== null ? (int) $rn['ano_letivo'] : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $boletimEventosNotas = [];
            $boletimEventosBoletim = [];
            $boletinsGeradosCoordenacao = [];
            $boletinsGeradosNotasPorRegra = [];
        }

        return [
            'student' => $aluno,
            'stats' => $stats,
            'matriculas' => $matriculas,
            'matriculas_schema_ready' => $matriculas_schema_ready,
            'matricula_divergente_cadastro' => $matricula_divergente_cadastro,
            'matriculas_paralelas' => $matriculas_paralelas ?? [],
            'turmas_para_matricula' => $turmas_para_matricula,
            'anos_letivo_para_matricula' => $anos_letivo_para_matricula,
            'responsaveis_aluno' => $responsaveisAluno,
            'ficha_complementar' => $fichaComplementar,
            'documentos_aluno' => $documentosAluno,
            'audit_logs' => $auditLogs,
            'conversas' => $conversas,
            'conversas_detalhadas' => $conversasDetalhadas,
            'exercicios_bd' => $exerciciosBD,
            'exercicios_ia' => $exerciciosIA,
            'listas_personalizadas_aluno' => $listasPersonalizadasAluno ?? [],
            'redacoes' => $redacoes,
            'historico_turmas' => $historico_turmas,
            'ocorrencias' => $ocorrenciasAluno,
            'jornadas_feitas' => $jornadas_feitas,
            'provas_realizadas' => $provas_realizadas,
            'provas_matriz_blocos' => $provas_matriz_blocos,
            'historico_acesso' => $historico_acesso,
            'boletim_eventos_notas' => $boletimEventosNotas,
            'boletim_eventos_boletim' => $boletimEventosBoletim,
            'boletins_gerados' => $boletinsGeradosCoordenacao,
            'boletins_gerados_notas_por_regra' => $boletinsGeradosNotasPorRegra,
            'boletim_observacao' => $this->controller->boletimObservacaoSafe((int) $id),
            'boletim_pode_excluir' => $this->controller->coordenacaoPodeEditarBoletim($user),
            'admin_permissions' => $adminPermissions,
            'user' => $user,
        ];
    }

    /**
     * Dados da aba "Provas" (provas_realizadas + provas_matriz_blocos), carregados
     * sob demanda via AJAX quando o admin clica na aba (ver StudentAdminController::
     * provasTabFragment). Extraído de getStudentProfile() sem alterar a lógica.
     */
    public function getProvasTabData(int $id): array
    {
        $provas_realizadas = [];
        try {
            $hasProvasMateriaId = $this->temColuna('provas', 'materia_id');
            $hasRealizacoesMateria = $this->temColuna('provas_realizacoes', 'materia');
            $hasRealizacoesDisciplina = $this->temColuna('provas_realizacoes', 'disciplina');
            $hasRealizacoesAreaConhecimento = $this->temColuna('provas_realizacoes', 'area_conhecimento');
            $hasQuestoesTotal = $this->temColuna('provas_realizacoes', 'questoes_total');
            $hasTotalQuestoes = $this->temColuna('provas_realizacoes', 'total_questoes');
            $hasQtdQuestoes = $this->temColuna('provas_realizacoes', 'qtd_questoes');
            $hasQuestoesCorretas = $this->temColuna('provas_realizacoes', 'questoes_corretas');
            $hasAcertos = $this->temColuna('provas_realizacoes', 'acertos');
            $hasQtdAcertos = $this->temColuna('provas_realizacoes', 'qtd_acertos');
            $hasProvasRespostasCorreta = $this->temColuna('provas_respostas', 'correta');

            $materiaParts = [];
            if ($hasProvasMateriaId) { $materiaParts[] = "NULLIF(TRIM(COALESCE(m.nome, '')), '')"; }
            if ($hasRealizacoesMateria) { $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.materia, '')), '')"; }
            if ($hasRealizacoesDisciplina) { $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.disciplina, '')), '')"; }
            if ($hasRealizacoesAreaConhecimento) { $materiaParts[] = "NULLIF(TRIM(COALESCE(pr.area_conhecimento, '')), '')"; }
            $materiaExpr = !empty($materiaParts) ? "COALESCE(" . implode(", ", $materiaParts) . ")" : "NULL";

            $qtdParts = [];
            if ($hasQuestoesTotal) { $qtdParts[] = "pr.questoes_total"; }
            if ($hasTotalQuestoes) { $qtdParts[] = "pr.total_questoes"; }
            if ($hasQtdQuestoes) { $qtdParts[] = "pr.qtd_questoes"; }
            $qtdParts[] = "(SELECT COUNT(*) FROM provas_questoes pq WHERE pq.prova_id = pr.prova_id)";
            $qtdExpr = !empty($qtdParts) ? "COALESCE(" . implode(", ", $qtdParts) . ", 0)" : "0";

            $acertosParts = [];
            if ($hasQuestoesCorretas) { $acertosParts[] = "pr.questoes_corretas"; }
            if ($hasAcertos) { $acertosParts[] = "pr.acertos"; }
            if ($hasQtdAcertos) { $acertosParts[] = "pr.qtd_acertos"; }
            if ($hasProvasRespostasCorreta) { $acertosParts[] = "(SELECT COUNT(*) FROM provas_respostas prs WHERE prs.prova_id = pr.prova_id AND prs.aluno_id = pr.aluno_id AND prs.correta = 1)"; }
            $acertosExpr = !empty($acertosParts) ? "COALESCE(" . implode(", ", $acertosParts) . ", 0)" : "0";

            $sqlProvasAlunoComBloco =
                "SELECT pr.id, pr.prova_id, pr.nota, pr.iniciado_em, pr.status, p.titulo as prova_titulo,
                        {$materiaExpr} as prova_materia,
                        {$qtdExpr} as prova_total_questoes,
                        {$acertosExpr} as prova_acertos,
                        pb.id as bloco_id,
                        pb.bloco_modelo_id as bloco_modelo_id,
                        pbm.nome as bloco_modelo_nome,
                        pb.data_prova as bloco_data_prova,
                        pb.titulo as bloco_titulo,
                        pb.bimestre as bloco_bimestre,
                        pb.ano_letivo as bloco_ano_letivo
                 FROM provas_realizacoes pr
                 INNER JOIN provas p ON p.id = pr.prova_id
                 LEFT JOIN materias m ON m.id = p.materia_id
                 LEFT JOIN (
                    SELECT prova_id, MAX(bloco_id) AS bloco_id
                    FROM provas_blocos_vinculo
                    GROUP BY prova_id
                 ) pbv ON pbv.prova_id = p.id
                 LEFT JOIN provas_blocos pb ON pb.id = pbv.bloco_id
                 LEFT JOIN provas_blocos_modelos pbm ON pbm.id = pb.bloco_modelo_id
                 WHERE pr.aluno_id = :id
                 ORDER BY pr.iniciado_em DESC
                 LIMIT 100";
            $sqlProvasAlunoSemBloco =
                "SELECT pr.id, pr.prova_id, pr.nota, pr.iniciado_em, pr.status, p.titulo as prova_titulo,
                        {$materiaExpr} as prova_materia,
                        {$qtdExpr} as prova_total_questoes,
                        {$acertosExpr} as prova_acertos,
                        NULL as bloco_id,
                        NULL as bloco_modelo_id,
                        NULL as bloco_modelo_nome,
                        NULL as bloco_data_prova,
                        NULL as bloco_titulo,
                        NULL as bloco_bimestre,
                        NULL as bloco_ano_letivo
                 FROM provas_realizacoes pr
                 INNER JOIN provas p ON p.id = pr.prova_id
                 LEFT JOIN materias m ON m.id = p.materia_id
                 WHERE pr.aluno_id = :id
                 ORDER BY pr.iniciado_em DESC
                 LIMIT 100";
            try {
                $provas_realizadas = $this->db->fetchAll($sqlProvasAlunoComBloco, ['id' => $id]);
            } catch (\Exception $eBloco) {
                $provas_realizadas = $this->db->fetchAll($sqlProvasAlunoSemBloco, ['id' => $id]);
            }
        } catch (\Exception $e) {
            $provas_realizadas = [];
        }

        $provas_matriz_blocos = ['tabelas' => [], 'tem_dados' => false];
        try {
            $provas_matriz_blocos = $this->controller->buildProvasMatrizPorBlocoAplicado($provas_realizadas);
        } catch (\Throwable $t) {
            $provas_matriz_blocos = ['tabelas' => [], 'tem_dados' => false];
        }

        return [
            'provas_realizadas' => $provas_realizadas,
            'provas_matriz_blocos' => $provas_matriz_blocos,
        ];
    }

    /**
     * Verifica se uma tabela existe (cache por request) para evitar
     * repetir SHOW TABLES a cada carregamento da tela.
     *
     * IMPORTANTE: `static` em PHP-FPM persiste por toda a vida do worker, não
     * por request — e o mesmo worker atende requests de tenants diferentes
     * (um único pool de PHP-FPM serve todos os domínios). Sem incluir o
     * tenant na chave, o resultado de UMA escola "vazava" pra próxima escola
     * atendida pelo mesmo worker (ex.: botão "Matrícula" desaparecendo sem
     * motivo aparente, dependendo de qual escola foi atendida antes).
     */
    private function temTabela(string $tabela): bool
    {
        static $cache = [];
        $chave = $this->tenantCacheKey() . ':' . $tabela;
        if (array_key_exists($chave, $cache)) {
            return $cache[$chave];
        }
        try {
            $row = $this->db->fetch("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$tabela]);
            $cache[$chave] = $row !== false && !empty($row);
        } catch (\Throwable $e) {
            $cache[$chave] = false;
        }
        return $cache[$chave];
    }

    /**
     * Chave de tenant pra isolar caches `static` entre escolas no mesmo worker
     * de PHP-FPM (ver comentário em temTabela()).
     */
    private function tenantCacheKey(): string
    {
        return defined('TENANT_ID') ? ('t' . TENANT_ID) : 'no_tenant';
    }

    /**
     * Retorna as colunas existentes de uma tabela (cache por request).
     * Substitui dezenas de SHOW COLUMNS por uma única consulta por tabela.
     *
     * @return array<string, bool>
     */
    private function colunasDe(string $tabela): array
    {
        static $cache = [];
        $chave = $this->tenantCacheKey() . ':' . $tabela;
        if (isset($cache[$chave])) {
            return $cache[$chave];
        }
        $cols = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t",
                ['t' => $tabela]
            );
            foreach ($rows as $r) {
                $cols[(string) $r['COLUMN_NAME']] = true;
            }
        } catch (\Throwable $e) {
            $cols = [];
        }
        $cache[$chave] = $cols;
        return $cols;
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        $cols = $this->colunasDe($tabela);
        return isset($cols[$coluna]);
    }

    /**
     * Busca as últimas ações sensíveis registradas em logs_auditoria
     * referentes a este aluno (criação/edição, responsáveis, documentos,
     * declarações). Best-effort: retorna [] em qualquer falha.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAuditTrail(int $alunoId, int $limit = 30): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        try {
            if (!$this->temTabela('logs_auditoria')) {
                return [];
            }
            $prefixo = '/admin/students/' . $alunoId;
            $rows = $this->db->fetchAll(
                "SELECT action, user_id, user_role, ip_address, request_payload, created_at
                 FROM logs_auditoria
                 WHERE resource_accessed = :exato OR resource_accessed LIKE :prefixo
                 ORDER BY created_at DESC
                 LIMIT {$limit}",
                ['exato' => $prefixo, 'prefixo' => $prefixo . '/%']
            );
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
