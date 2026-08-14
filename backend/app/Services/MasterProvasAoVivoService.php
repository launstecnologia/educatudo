<?php
/**
 * Painel somente leitura: andamento de provas no banco do tenant.
 * Não altera realização, bloco nem acesso do aluno.
 */

class MasterProvasAoVivoService
{
    private ?string $erroConsulta = null;

    /**
     * @return array{blocos: array<int, array<string, mixed>>, bloco: ?array<string, mixed>, materias: array<int, array<string, mixed>>, resumo: array<string, int>, alunos: array<int, array<string, mixed>>, erro: ?string}
     */
    public function montarPainel(PDO $pdo, int $blocoIdSelecionado = 0): array
    {
        $vazio = [
            'blocos' => [],
            'bloco' => null,
            'materias' => [],
            'resumo' => [
                'em_prova' => 0,
                'concluiu_alguma' => 0,
                'concluiu_todas' => 0,
                'canceladas' => 0,
                'nao_comecou' => 0,
                'com_atividade' => 0,
            ],
            'alunos' => [],
            'erro' => null,
        ];

        $blocos = $this->listarBlocos($pdo);
        if ($this->erroConsulta !== null) {
            $vazio['erro'] = $this->erroConsulta;
            return $vazio;
        }
        if ($blocos === []) {
            return $vazio;
        }

        $blocoId = $blocoIdSelecionado;
        $ids = [];
        foreach ($blocos as $b) {
            $ids[(int) $b['id']] = true;
        }
        if ($blocoId <= 0 || !isset($ids[$blocoId])) {
            $blocoId = (int) $blocos[0]['id'];
        }

        $bloco = null;
        foreach ($blocos as $b) {
            if ((int) $b['id'] === $blocoId) {
                $bloco = $b;
                break;
            }
        }
        if ($bloco === null) {
            return $vazio;
        }

        $materias = $this->listarMaterias($pdo, $blocoId);
        $alunos = $this->listarAlunos($pdo, $blocoId);
        $resumo = $this->montarResumo($alunos, count($materias));

        return [
            'blocos' => $blocos,
            'bloco' => $bloco,
            'materias' => $materias,
            'resumo' => $resumo,
            'alunos' => $alunos,
            'erro' => $this->erroConsulta,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarBlocos(PDO $pdo): array
    {
        $sql = "SELECT pb.id, pb.titulo, pb.data_prova, pb.hora_inicio, pb.hora_fim, pb.status, pb.liberado,
                       (SELECT COUNT(*) FROM provas_blocos_vinculo pbp WHERE pbp.bloco_id = pb.id) AS total_materias
                FROM provas_blocos pb
                WHERE pb.deleted_at IS NULL
                  AND (
                    pb.data_prova BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                    OR (pb.liberado = 1 AND pb.data_prova >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
                    OR pb.id IN (
                        SELECT DISTINCT pbp.bloco_id
                        FROM provas_realizacoes pr
                        INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id
                        WHERE pr.status = 'iniciado'
                           OR pr.iniciado_em >= DATE_SUB(NOW(), INTERVAL 12 HOUR)
                    )
                  )
                ORDER BY
                    CASE WHEN pb.data_prova = CURDATE() THEN 0 ELSE 1 END,
                    CASE WHEN pb.liberado = 1 THEN 0 ELSE 1 END,
                    pb.data_prova DESC,
                    pb.hora_inicio DESC
                LIMIT 30";
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('MasterProvasAoVivoService::listarBlocos: ' . $e->getMessage());
            $this->erroConsulta = 'Não foi possível ler os blocos de prova desta escola.';
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarMaterias(PDO $pdo, int $blocoId): array
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT p.id, p.titulo, m.nome AS materia_nome, pbp.ordem
                 FROM provas_blocos_vinculo pbp
                 INNER JOIN provas p ON p.id = pbp.prova_id AND p.deleted_at IS NULL
                 LEFT JOIN materias m ON m.id = p.materia_id
                 WHERE pbp.bloco_id = :bloco_id
                 ORDER BY pbp.ordem ASC, m.nome ASC, p.titulo ASC"
            );
            $stmt->execute(['bloco_id' => $blocoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('MasterProvasAoVivoService::listarMaterias: ' . $e->getMessage());
            $this->erroConsulta = 'Não foi possível ler as matérias deste bloco.';
            return [];
        }
    }

    /**
     * @return list<int>
     */
    private function listarTurmaIds(PDO $pdo, int $blocoId): array
    {
        $ids = [];
        try {
            $stmt = $pdo->prepare('SELECT turma_id FROM provas_blocos WHERE id = :id AND turma_id IS NOT NULL');
            $stmt->execute(['id' => $blocoId]);
            $turmaDireta = (int) $stmt->fetchColumn();
            if ($turmaDireta > 0) {
                $ids[$turmaDireta] = true;
            }
        } catch (PDOException $e) {
            error_log('MasterProvasAoVivoService::listarTurmaIds bloco: ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->prepare('SELECT turma_id FROM provas_blocos_turmas WHERE bloco_id = :id');
            $stmt->execute(['id' => $blocoId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tid) {
                $tid = (int) $tid;
                if ($tid > 0) {
                    $ids[$tid] = true;
                }
            }
        } catch (PDOException $e) {
            error_log('MasterProvasAoVivoService::listarTurmaIds turmas: ' . $e->getMessage());
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT pbpt.turma_id
                 FROM provas_blocos_professores pbp
                 INNER JOIN provas_blocos_professores_turmas pbpt ON pbpt.bloco_professor_id = pbp.id
                 WHERE pbp.bloco_id = :id'
            );
            $stmt->execute(['id' => $blocoId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tid) {
                $tid = (int) $tid;
                if ($tid > 0) {
                    $ids[$tid] = true;
                }
            }
        } catch (PDOException $e) {
            // Tenant antigo pode não ter provas_blocos_professores_turmas.
        }

        return array_keys($ids);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function alunoBase(int $alunoId, string $nome, string $ra, string $turma): array
    {
        return [
            'aluno_id' => $alunoId,
            'aluno_nome' => $nome,
            'aluno_ra' => $ra,
            'turma_nome' => $turma,
            'por_materia' => [],
            'em_prova' => false,
            'tem_cancelada' => false,
            'materias_ok' => 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarAlunos(PDO $pdo, int $blocoId): array
    {
        $porAluno = [];

        $turmaIds = $this->listarTurmaIds($pdo, $blocoId);
        if ($turmaIds !== []) {
            $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
            try {
                $stmt = $pdo->prepare(
                    "SELECT a.id AS aluno_id, a.nome AS aluno_nome, a.ra AS aluno_ra, t.nome AS turma_nome
                     FROM alunos a
                     LEFT JOIN turmas t ON t.id = a.turma_id
                     WHERE a.ativo = 1 AND a.turma_id IN ($placeholders)
                     ORDER BY a.nome ASC"
                );
                $stmt->execute(array_map('intval', $turmaIds));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $alunoId = (int) $row['aluno_id'];
                    $porAluno[$alunoId] = $this->alunoBase(
                        $alunoId,
                        (string) ($row['aluno_nome'] ?? ''),
                        (string) ($row['aluno_ra'] ?? ''),
                        (string) ($row['turma_nome'] ?? '')
                    );
                }
            } catch (PDOException $e) {
                error_log('MasterProvasAoVivoService::listarAlunos turmas: ' . $e->getMessage());
            $this->erroConsulta = 'Não foi possível ler os alunos das turmas deste bloco.';
            }
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT pr.aluno_id, pr.prova_id, pr.status, pr.iniciado_em, pr.finalizado_em,
                        a.nome AS aluno_nome, a.ra AS aluno_ra, t.nome AS turma_nome
                 FROM provas_realizacoes pr
                 INNER JOIN provas_blocos_vinculo pbp ON pbp.prova_id = pr.prova_id AND pbp.bloco_id = :bloco_id
                 INNER JOIN alunos a ON a.id = pr.aluno_id
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 ORDER BY a.nome ASC"
            );
            $stmt->execute(['bloco_id' => $blocoId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $alunoId = (int) $row['aluno_id'];
                if (!isset($porAluno[$alunoId])) {
                    $porAluno[$alunoId] = $this->alunoBase(
                        $alunoId,
                        (string) ($row['aluno_nome'] ?? ''),
                        (string) ($row['aluno_ra'] ?? ''),
                        (string) ($row['turma_nome'] ?? '')
                    );
                }
                $provaId = (int) $row['prova_id'];
                $status = (string) ($row['status'] ?? '');
                $porAluno[$alunoId]['por_materia'][$provaId] = [
                    'status' => $status,
                    'iniciado_em' => $row['iniciado_em'] ?? null,
                    'finalizado_em' => $row['finalizado_em'] ?? null,
                ];
                if ($status === 'iniciado') {
                    $porAluno[$alunoId]['em_prova'] = true;
                }
                if ($status === 'cancelada') {
                    $porAluno[$alunoId]['tem_cancelada'] = true;
                }
                if ($status === 'finalizado') {
                    $porAluno[$alunoId]['materias_ok']++;
                }
            }
        } catch (PDOException $e) {
            error_log('MasterProvasAoVivoService::listarAlunos realizacoes: ' . $e->getMessage());
            $this->erroConsulta = 'Não foi possível ler as realizações de prova deste bloco.';
        }

        $lista = array_values($porAluno);
        usort($lista, static function ($a, $b) {
            $rank = static function (array $aluno): int {
                if (!empty($aluno['em_prova'])) {
                    return 0;
                }
                if (!empty($aluno['tem_cancelada'])) {
                    return 1;
                }
                if ((int) ($aluno['materias_ok'] ?? 0) > 0) {
                    return 2;
                }
                if (($aluno['por_materia'] ?? []) === []) {
                    return 4;
                }
                return 3;
            };
            $ra = $rank($a);
            $rb = $rank($b);
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            return strcasecmp((string) $a['aluno_nome'], (string) $b['aluno_nome']);
        });

        return $lista;
    }

    /**
     * @param list<array<string, mixed>> $alunos
     * @return array<string, int>
     */
    private function montarResumo(array $alunos, int $totalMaterias): array
    {
        $resumo = [
            'em_prova' => 0,
            'concluiu_alguma' => 0,
            'concluiu_todas' => 0,
            'canceladas' => 0,
            'nao_comecou' => 0,
            'com_atividade' => 0,
        ];
        foreach ($alunos as $aluno) {
            $temAtividade = ($aluno['por_materia'] ?? []) !== [];
            if ($temAtividade) {
                $resumo['com_atividade']++;
            } else {
                $resumo['nao_comecou']++;
            }
            if (!empty($aluno['em_prova'])) {
                $resumo['em_prova']++;
            }
            $ok = (int) ($aluno['materias_ok'] ?? 0);
            if ($ok > 0) {
                $resumo['concluiu_alguma']++;
            }
            if ($totalMaterias > 0 && $ok >= $totalMaterias) {
                $resumo['concluiu_todas']++;
            }
            if (!empty($aluno['tem_cancelada'])) {
                $resumo['canceladas']++;
            }
        }
        return $resumo;
    }
}
