<?php

namespace App\Modulos\Matricula\Services;

use Database;

/**
 * Conta vagas da turma, reserva no processo e gerencia a fila de espera.
 */
class MatriculaVagaService
{
    public const STATUS_RESERVA = ['aguardando_contrato', 'aguardando_assinatura', 'confirmada'];

    private $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function temColunaVagas(): bool
    {
        return $this->temColuna('turmas', 'vagas');
    }

    /**
     * @return array{vagas:?int,ocupadas:int,reservadas:int,fila:int,restantes:?int,ilimitado:bool,lotada:bool}
     */
    public function resumo(int $turmaId): array
    {
        $vagas = $this->capacidade($turmaId);
        $ocupadas = $this->contarOcupadas($turmaId);
        $reservadas = $this->contarReservadas($turmaId);
        $fila = $this->contarFila($turmaId);
        $ilimitado = $vagas === null;
        $restantes = $ilimitado ? null : max(0, $vagas - $ocupadas - $reservadas);

        return [
            'vagas' => $vagas,
            'ocupadas' => $ocupadas,
            'reservadas' => $reservadas,
            'fila' => $fila,
            'restantes' => $restantes,
            'ilimitado' => $ilimitado,
            'lotada' => !$ilimitado && $restantes <= 0,
        ];
    }

    public function capacidade(int $turmaId): ?int
    {
        if ($turmaId <= 0 || !$this->temColunaVagas()) {
            return null;
        }
        $row = $this->db->fetch('SELECT vagas FROM turmas WHERE id = ? LIMIT 1', [$turmaId]);
        if (!$row) {
            return null;
        }
        $v = $row['vagas'] ?? null;
        if ($v === null || (int) $v <= 0) {
            return null;
        }
        return (int) $v;
    }

    public function contarOcupadas(int $turmaId): int
    {
        if ($turmaId <= 0 || !$this->temTabela('matricula')) {
            return 0;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM matricula
             WHERE turma_id = ? AND status = 'ativa' AND data_saida IS NULL",
            [$turmaId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function contarReservadas(int $turmaId): int
    {
        if ($turmaId <= 0 || !$this->temTabela('matricula_processos')) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count(self::STATUS_RESERVA), '?'));
        $params = array_merge([$turmaId], self::STATUS_RESERVA);
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM matricula_processos
             WHERE turma_id = ? AND status IN ($ph)",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    public function contarFila(int $turmaId): int
    {
        if ($turmaId <= 0 || !$this->temTabela('matricula_processos')) {
            return 0;
        }
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM matricula_processos
             WHERE turma_id = ? AND status = 'lista_espera'",
            [$turmaId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarFila(int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT id, aluno_nome, resp_nome, resp_telefone, fila_posicao, entrou_fila_em, created_at, status
             FROM matricula_processos
             WHERE turma_id = ? AND status = 'lista_espera'
             ORDER BY COALESCE(fila_posicao, 999999) ASC, created_at ASC",
            [$turmaId]
        ) ?: [];
    }

    /**
     * Decide se o processo ocupa vaga ou entra na fila. Chamar dentro de transação com lock na turma.
     *
     * @return array{destino:string,mensagem:string,resumo:array}
     */
    public function decidirDestino(int $turmaId): array
    {
        $this->travarTurma($turmaId);
        $resumo = $this->resumo($turmaId);
        if (!empty($resumo['lotada'])) {
            return [
                'destino' => 'lista_espera',
                'mensagem' => 'Turma lotada. Processo enviado para a lista de espera.',
                'resumo' => $resumo,
            ];
        }
        return [
            'destino' => 'enturmar',
            'mensagem' => 'Há vaga na turma.',
            'resumo' => $resumo,
        ];
    }

    public function colocarNaFila(int $enrollmentId, int $turmaId, ?array $user = null): int
    {
        $proxima = 1;
        $max = $this->db->fetch(
            "SELECT MAX(fila_posicao) AS m FROM matricula_processos
             WHERE turma_id = ? AND status = 'lista_espera'",
            [$turmaId]
        );
        if ($max && (int) ($max['m'] ?? 0) > 0) {
            $proxima = (int) $max['m'] + 1;
        }

        $statusDe = $this->statusAtual($enrollmentId);
        $sets = ['status = ?'];
        $params = ['lista_espera'];
        if ($this->temColuna('matricula_processos', 'fila_posicao')) {
            $sets[] = 'fila_posicao = ?';
            $params[] = $proxima;
        }
        if ($this->temColuna('matricula_processos', 'entrou_fila_em')) {
            $sets[] = 'entrou_fila_em = NOW()';
        }
        $params[] = $enrollmentId;
        $this->db->update(
            'UPDATE matricula_processos SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );

        $this->auditar($enrollmentId, $statusDe, 'lista_espera', $user, 'entrada_fila');
        $this->reordenarFila($turmaId);
        return $proxima;
    }

    public function reordenarFila(int $turmaId): void
    {
        if (!$this->temColuna('matricula_processos', 'fila_posicao')) {
            return;
        }
        $rows = $this->db->fetchAll(
            "SELECT id FROM matricula_processos
             WHERE turma_id = ? AND status = 'lista_espera'
             ORDER BY COALESCE(fila_posicao, 999999) ASC, created_at ASC",
            [$turmaId]
        ) ?: [];
        $pos = 1;
        foreach ($rows as $row) {
            $this->db->update(
                'UPDATE matricula_processos SET fila_posicao = ? WHERE id = ?',
                [$pos, (int) $row['id']]
            );
            $pos++;
        }
    }

    /**
     * Oferece vaga ao primeiro da fila (ou ao id informado).
     */
    public function oferecerVaga(int $turmaId, ?int $enrollmentId = null, ?array $user = null): ?array
    {
        if ($turmaId <= 0) {
            throw new \InvalidArgumentException('Turma inválida.');
        }

        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }
        try {
            $this->travarTurma($turmaId);
            $resumo = $this->resumo($turmaId);
            if (!empty($resumo['lotada'])) {
                throw new \InvalidArgumentException('Não há vaga disponível nesta turma.');
            }

            if ($enrollmentId === null) {
                $primeiro = $this->db->fetch(
                    "SELECT id FROM matricula_processos
                     WHERE turma_id = ? AND status = 'lista_espera'
                     ORDER BY COALESCE(fila_posicao, 999999) ASC, created_at ASC
                     LIMIT 1",
                    [$turmaId]
                );
                if (!$primeiro) {
                    if ($ownTx) {
                        $this->db->commit();
                    }
                    return null;
                }
                $enrollmentId = (int) $primeiro['id'];
            }

            $statusDe = $this->statusAtual($enrollmentId);
            $sets = ["status = 'aguardando_contrato'"];
            $params = [];
            if ($this->temColuna('matricula_processos', 'fila_posicao')) {
                $sets[] = 'fila_posicao = NULL';
            }
            if ($this->temColuna('matricula_processos', 'reserva_ate')) {
                $sets[] = 'reserva_ate = DATE_ADD(NOW(), INTERVAL 48 HOUR)';
            }
            $params[] = $enrollmentId;
            $afetados = $this->db->update(
                'UPDATE matricula_processos SET ' . implode(', ', $sets) . '
                 WHERE id = ? AND status = ?',
                array_merge($params, ['lista_espera'])
            );
            if ((int) $afetados <= 0) {
                throw new \InvalidArgumentException('Só é possível oferecer vaga a um processo na lista de espera.');
            }
            $this->auditar($enrollmentId, $statusDe, 'aguardando_contrato', $user, 'oferecer_vaga');
            $this->reordenarFila($turmaId);
            if ($ownTx) {
                $this->db->commit();
            }
            return $this->db->fetch('SELECT * FROM matricula_processos WHERE id = ?', [$enrollmentId]) ?: null;
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function aoLiberarVaga(int $turmaId, ?array $user = null): ?array
    {
        if ($turmaId <= 0) {
            return null;
        }
        $resumo = $this->resumo($turmaId);
        if (!empty($resumo['lotada']) || (int) ($resumo['fila'] ?? 0) <= 0) {
            return null;
        }
        try {
            return $this->oferecerVaga($turmaId, null, $user);
        } catch (\Throwable $e) {
            error_log('[MatriculaVagaService] aoLiberarVaga: ' . $e->getMessage());
            return null;
        }
    }

    public function travaTurma(int $turmaId): void
    {
        $this->travarTurma($turmaId);
    }

    private function travarTurma(int $turmaId): void
    {
        if ($turmaId <= 0) {
            return;
        }
        $this->db->fetch('SELECT id FROM turmas WHERE id = ? FOR UPDATE', [$turmaId]);
    }

    private function statusAtual(int $enrollmentId): ?string
    {
        $atual = $this->db->fetch('SELECT status FROM matricula_processos WHERE id = ?', [$enrollmentId]);
        $status = trim((string) ($atual['status'] ?? ''));
        return $status !== '' ? $status : null;
    }

    private function auditar(int $enrollmentId, ?string $de, string $para, ?array $user, string $acao): void
    {
        if (!$this->temTabela('matricula_processos_auditorias')) {
            return;
        }
        $this->db->insert(
            'INSERT INTO matricula_processos_auditorias
             (enrollment_id, status_de, status_para, acao, usuario_id, usuario_nome, ip)
             VALUES (?,?,?,?,?,?,?)',
            [
                $enrollmentId,
                $de,
                $para,
                $acao,
                $user['id'] ?? null,
                $user['nome'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    private function temTabela(string $nome): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $nome)) {
            return false;
        }
        try {
            return (bool) $this->db->fetch('SHOW TABLES LIKE ?', [$nome]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela) || !preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return false;
        }
        try {
            return (bool) $this->db->fetch('SHOW COLUMNS FROM `' . $tabela . '` LIKE ?', [$coluna]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
