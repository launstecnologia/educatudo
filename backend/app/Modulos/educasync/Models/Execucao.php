<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — execução do solver (uma por clique em "Gerar").
 */
class Execucao
{
    public const STATUS_ATIVOS = ['pendente', 'executando'];

    public const STATUS = [
        'pendente' => 'Na fila',
        'executando' => 'Otimizando',
        'concluida' => 'Concluída',
        'inviavel' => 'Sem solução',
        'falhou' => 'Falhou',
    ];

    /** Colunas que o Service pode atualizar. */
    private const CAMPOS_ATUALIZAVEIS = [
        'versao_id', 'ai_job_id', 'status', 'solver_status', 'objetivo',
        'best_bound', 'tempo_ms', 'log_resumo',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function criar(int $cenarioId, int $usuarioId, array $parametros): int
    {
        return (int) $this->db->insert(
            "INSERT INTO educasync_execucoes (cenario_id, usuario_id, status, parametros_json)
             VALUES (:cenario_id, :usuario_id, 'pendente', :parametros_json)",
            [
                'cenario_id' => (int) $cenarioId,
                'usuario_id' => $usuarioId > 0 ? (int) $usuarioId : null,
                'parametros_json' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    public function findById(int $id): ?array
    {
        $linha = $this->db->fetch(
            'SELECT * FROM educasync_execucoes WHERE id = :id',
            ['id' => (int) $id]
        );
        return $linha ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorCenario(int $cenarioId, int $limite = 10): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM educasync_execucoes WHERE cenario_id = :cenario_id ORDER BY id DESC LIMIT ' . max(1, min(50, (int) $limite)),
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];
    }

    public function ativaDoCenario(int $cenarioId): ?array
    {
        $linha = $this->db->fetch(
            "SELECT * FROM educasync_execucoes
             WHERE cenario_id = :cenario_id AND status IN ('pendente','executando')
             ORDER BY id DESC LIMIT 1",
            ['cenario_id' => (int) $cenarioId]
        );
        return $linha ?: null;
    }

    /**
     * @param array<string,mixed> $campos apenas chaves de CAMPOS_ATUALIZAVEIS
     */
    public function atualizar(int $id, array $campos): void
    {
        $sets = [];
        $params = ['id' => (int) $id];
        foreach (self::CAMPOS_ATUALIZAVEIS as $coluna) {
            if (!array_key_exists($coluna, $campos)) {
                continue;
            }
            $sets[] = "{$coluna} = :{$coluna}";
            $params[$coluna] = $campos[$coluna];
        }
        if ($sets === []) {
            return;
        }
        $this->db->update(
            'UPDATE educasync_execucoes SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    /**
     * Transição atômica pendente/executando → executando.
     * Retorna false se a execução já terminou (idempotência do worker).
     */
    public function marcarExecutando(int $id): bool
    {
        $afetadas = (int) $this->db->update(
            "UPDATE educasync_execucoes
             SET status = 'executando', log_resumo = NULL
             WHERE id = :id AND status IN ('pendente', 'executando')",
            ['id' => (int) $id]
        );
        return $afetadas > 0;
    }
}
