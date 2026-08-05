<?php

namespace App\Services;

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/ListaChamadaService.php';
require_once __DIR__ . '/StudentStatusService.php';

class AlunoMovimentacaoService
{
    private $db;
    private $listaChamada;

    public function __construct()
    {
        $this->db = \Database::getInstance();
        $this->listaChamada = new ListaChamadaService();
    }

    /**
     * Remanejamento interno: troca de turma mantendo aluno ativo.
     *
     * @return array{transferidos: int, ignorados: int}
     */
    public function remanejarEmLote(int $turmaOrigemId, int $turmaDestinoId, array $alunoIds): array
    {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static fn ($id) => $id > 0)));
        if ($turmaOrigemId <= 0 || $turmaDestinoId <= 0 || $turmaOrigemId === $turmaDestinoId || empty($alunoIds)) {
            throw new \Exception('Dados inválidos para remanejamento');
        }

        $placeholders = implode(',', array_fill(0, count($alunoIds), '?'));
        $validIds = $this->db->fetchAll(
            "SELECT id FROM alunos WHERE turma_id = ? AND id IN ($placeholders) AND ativo = 1",
            array_merge([$turmaOrigemId], $alunoIds)
        );
        $validIds = array_map('intval', array_column($validIds ?: [], 'id'));
        if (empty($validIds)) {
            throw new \Exception('Nenhum aluno válido para remanejamento');
        }

        $pdo = $this->db->getPdo();
        $pdo->beginTransaction();
        try {
            $ph = implode(',', array_fill(0, count($validIds), '?'));
            $this->db->query(
                "UPDATE alunos SET turma_id = ? WHERE id IN ($ph)",
                array_merge([$turmaDestinoId], $validIds)
            );

            $this->db->query(
                "UPDATE alunos_turmas_historico SET data_fim = CURDATE()
                 WHERE aluno_id IN ($ph) AND data_fim IS NULL AND turma_id = ?",
                array_merge($validIds, [$turmaOrigemId])
            );

            $anoLetivo = date('Y');
            foreach ($validIds as $alunoId) {
                $this->db->query(
                    "INSERT INTO alunos_turmas_historico (aluno_id, turma_id, ano_letivo, data_inicio)
                     VALUES (:aluno_id, :turma_id, :ano_letivo, CURDATE())",
                    ['aluno_id' => $alunoId, 'turma_id' => $turmaDestinoId, 'ano_letivo' => $anoLetivo]
                );
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        foreach ($validIds as $alunoId) {
            $this->reconcileMatricula($alunoId, $turmaDestinoId, $turmaOrigemId);
            $this->sincronizarListaChamadaRemanejamento($alunoId, $turmaOrigemId, $turmaDestinoId);
        }

        return [
            'transferidos' => count($validIds),
            'ignorados' => count($alunoIds) - count($validIds),
        ];
    }

    /**
     * Transferência escolar (saída): inativa aluno e marca TR na lista.
     *
     * @return array{processados: int, ignorados: int}
     */
    public function transferenciaEscolarEmLote(int $turmaOrigemId, array $alunoIds, array $payload, array $currentUser, bool $removerTurma = true): array
    {
        $alunoIds = array_values(array_unique(array_filter(array_map('intval', $alunoIds), static fn ($id) => $id > 0)));
        if ($turmaOrigemId <= 0 || empty($alunoIds)) {
            throw new \Exception('Selecione turma e alunos para transferência escolar');
        }

        $placeholders = implode(',', array_fill(0, count($alunoIds), '?'));
        $validIds = $this->db->fetchAll(
            "SELECT id FROM alunos WHERE turma_id = ? AND id IN ($placeholders) AND ativo = 1",
            array_merge([$turmaOrigemId], $alunoIds)
        );
        $validIds = array_map('intval', array_column($validIds ?: [], 'id'));
        if (empty($validIds)) {
            throw new \Exception('Nenhum aluno ativo encontrado na turma');
        }

        $service = new StudentStatusService();
        $payloadBase = array_merge($payload, [
            'reason' => 'TRANSFERENCIA',
            'confirm' => $payload['confirm'] ?? '1',
        ]);
        $service->verifyReauthForUser($currentUser['id'] ?? null, $payloadBase);

        $first = true;
        foreach ($validIds as $alunoId) {
            $itemPayload = $payloadBase;
            if (!$first) {
                $itemPayload['_skip_reauth'] = 1;
            }
            $first = false;
            $service->inactivate($alunoId, $itemPayload, $currentUser);
            if ($removerTurma) {
                $this->db->query('UPDATE alunos SET turma_id = NULL WHERE id = :id', ['id' => $alunoId]);
                $this->db->query(
                    "UPDATE alunos_turmas_historico SET data_fim = CURDATE()
                     WHERE aluno_id = :id AND turma_id = :t AND data_fim IS NULL",
                    ['id' => $alunoId, 't' => $turmaOrigemId]
                );
            }
        }

        return [
            'processados' => count($validIds),
            'ignorados' => count($alunoIds) - count($validIds),
        ];
    }

    public function sincronizarListaChamadaRemanejamento(int $alunoId, int $turmaOrigemId, int $turmaDestinoId): void
    {
        if (!$this->listaChamada->tabelaExiste()) {
            return;
        }
        $this->listaChamada->moverRemanejamento($alunoId, $turmaOrigemId, $turmaDestinoId);
    }

    /**
     * Vincula aluno a uma turma (matrícula + histórico). Opcionalmente define turma principal.
     *
     * @throws \Exception
     */
    public function vincularAlunoTurma(
        int $alunoId,
        int $turmaId,
        int $anoLetivoId,
        bool $definirPrincipal = false,
        ?string $dataEntrada = null
    ): void {
        if ($alunoId <= 0 || $turmaId <= 0 || $anoLetivoId <= 0) {
            throw new \Exception('Dados inválidos para vincular aluno à turma.');
        }

        $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
        if ($hasMatricula === false) {
            throw new \Exception('Estrutura de matrículas não disponível. Execute as migrations 022-027.');
        }

        $aluno = $this->db->fetch('SELECT id, turma_id FROM alunos WHERE id = :id', ['id' => $alunoId]);
        if (!$aluno) {
            throw new \Exception('Aluno não encontrado.');
        }

        $turma = $this->db->fetch('SELECT id FROM turmas WHERE id = :id AND ativo = 1', ['id' => $turmaId]);
        if (!$turma) {
            throw new \Exception('Turma inválida ou inativa.');
        }

        $anoRow = $this->db->fetch('SELECT ano FROM ano_letivo WHERE id = :id', ['id' => $anoLetivoId]);
        if (!$anoRow) {
            throw new \Exception('Ano letivo inválido.');
        }

        $dataEntrada = ($dataEntrada !== null && trim($dataEntrada) !== '') ? trim($dataEntrada) : date('Y-m-d');
        $turmaAtualId = (int) ($aluno['turma_id'] ?? 0);
        if ($turmaAtualId <= 0) {
            $definirPrincipal = true;
        }

        $existe = $this->db->fetch(
            "SELECT id, status, data_saida FROM matricula
             WHERE aluno_id = :aluno_id AND turma_id = :turma_id AND ano_letivo_id = :ano_letivo_id",
            ['aluno_id' => $alunoId, 'turma_id' => $turmaId, 'ano_letivo_id' => $anoLetivoId]
        );
        if ($existe) {
            $matriculaAtiva = ($existe['status'] ?? '') === 'ativa' && ($existe['data_saida'] ?? null) === null;
            if ($matriculaAtiva) {
                throw new \Exception('Este aluno já possui matrícula nesta turma e ano letivo.');
            }
            $this->db->query(
                "UPDATE matricula SET data_entrada = :data_entrada, data_saida = NULL, status = 'ativa', updated_at = NOW()
                 WHERE id = :id",
                ['data_entrada' => $dataEntrada, 'id' => $existe['id']]
            );
        } else {
            $this->db->insert(
                "INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status) VALUES (:aluno_id, :turma_id, :ano_letivo_id, :data_entrada, 'ativa')",
                [
                    'aluno_id' => $alunoId,
                    'turma_id' => $turmaId,
                    'ano_letivo_id' => $anoLetivoId,
                    'data_entrada' => $dataEntrada,
                ]
            );
        }

        $anoLetivoAno = (string) ($anoRow['ano'] ?? date('Y'));
        $jaHistorico = $this->db->fetch(
            'SELECT id FROM alunos_turmas_historico WHERE aluno_id = :aid AND turma_id = :tid AND (ano_letivo = :ano OR ano_letivo IS NULL)',
            ['aid' => $alunoId, 'tid' => $turmaId, 'ano' => $anoLetivoAno]
        );
        if (!$jaHistorico) {
            $this->db->query(
                'INSERT INTO alunos_turmas_historico (aluno_id, turma_id, ano_letivo, data_inicio) VALUES (:aluno_id, :turma_id, :ano_letivo, :data_inicio)',
                [
                    'aluno_id' => $alunoId,
                    'turma_id' => $turmaId,
                    'ano_letivo' => $anoLetivoAno,
                    'data_inicio' => $dataEntrada,
                ]
            );
        }

        if (!$definirPrincipal) {
            // Turma paralela/extra: também recebe número na lista de chamada da própria turma.
            $this->listaChamada->atribuirProximoNumero($alunoId, $turmaId, $anoLetivoId, $dataEntrada);

            return;
        }

        if ($turmaAtualId === $turmaId) {
            $this->listaChamada->atribuirProximoNumero($alunoId, $turmaId, $anoLetivoId, $dataEntrada);

            return;
        }

        $turmaAntigaId = $turmaAtualId > 0 ? $turmaAtualId : 0;
        $this->db->query(
            'UPDATE alunos SET turma_id = :tid WHERE id = :aid',
            ['tid' => $turmaId, 'aid' => $alunoId]
        );

        if ($turmaAntigaId > 0) {
            $this->db->query(
                'UPDATE alunos_turmas_historico SET data_fim = CURDATE()
                 WHERE aluno_id = :aid AND turma_id = :tid AND data_fim IS NULL',
                ['aid' => $alunoId, 'tid' => $turmaAntigaId]
            );
            $this->reconcileMatricula($alunoId, $turmaId, $turmaAntigaId);
            $this->sincronizarListaChamadaRemanejamento($alunoId, $turmaAntigaId, $turmaId);
        } else {
            $this->listaChamada->atribuirProximoNumero($alunoId, $turmaId, $anoLetivoId, $dataEntrada);
        }
    }

    private function reconcileMatricula(int $alunoId, int $turmaNovaId, int $turmaAntigaId): void
    {
        try {
            $hasMatricula = $this->db->fetch("SHOW TABLES LIKE 'matricula'");
            if ($hasMatricula === false) {
                return;
            }
            $anoParaNova = 0;
            $ativas = $this->db->fetchAll(
                "SELECT id, turma_id, ano_letivo_id FROM matricula
                 WHERE aluno_id = :aid AND status = 'ativa' AND data_saida IS NULL",
                ['aid' => $alunoId]
            ) ?: [];
            foreach ($ativas as $row) {
                if ((int) $row['turma_id'] === $turmaAntigaId) {
                    if ($anoParaNova <= 0) {
                        $anoParaNova = (int) $row['ano_letivo_id'];
                    }
                    $this->db->query(
                        "UPDATE matricula SET data_saida = CURDATE(), status = 'transferido', updated_at = NOW() WHERE id = :id",
                        ['id' => $row['id']]
                    );
                }
            }
            $temAtivaNova = $this->db->fetch(
                "SELECT id FROM matricula WHERE aluno_id = :aid AND turma_id = :tid AND status = 'ativa'
                   AND data_saida IS NULL LIMIT 1",
                ['aid' => $alunoId, 'tid' => $turmaNovaId]
            );
            if ($temAtivaNova === false) {
                if ($anoParaNova <= 0) {
                    $anoParaNova = $this->listaChamada->resolverAnoLetivoIdParaTurma($turmaNovaId);
                }
                if ($anoParaNova > 0) {
                    $ex = $this->db->fetch(
                        'SELECT id FROM matricula WHERE aluno_id = :a AND turma_id = :t AND ano_letivo_id = :y',
                        ['a' => $alunoId, 't' => $turmaNovaId, 'y' => $anoParaNova]
                    );
                    if ($ex) {
                        $this->db->query(
                            "UPDATE matricula SET status = 'ativa', data_entrada = CURDATE(), data_saida = NULL, updated_at = NOW() WHERE id = :id",
                            ['id' => $ex['id']]
                        );
                    } else {
                        $this->db->insert(
                            "INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status) VALUES (:a, :t, :y, CURDATE(), 'ativa')",
                            ['a' => $alunoId, 't' => $turmaNovaId, 'y' => $anoParaNova]
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('AlunoMovimentacaoService reconcileMatricula: ' . $e->getMessage());
        }
    }
}
