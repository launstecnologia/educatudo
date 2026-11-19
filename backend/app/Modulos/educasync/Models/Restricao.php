<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — restrições do cenário (hard/soft) gravadas em educasync_restricoes.
 */
class Restricao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Grava uma preferência (soft) vinda do assistente. Idempotente: se já existe uma
     * restrição ativa do mesmo tipo/escopo/parâmetros, só atualiza o peso.
     *
     * @param array<string,scalar|null> $parametros
     * @return int id da restrição
     */
    public function gravarSoftDoAssistente(int $cenarioId, string $tipo, string $escopo, array $parametros, int $peso): int
    {
        ksort($parametros);
        $existentes = $this->db->fetchAll(
            "SELECT id, parametros_json FROM educasync_restricoes
             WHERE cenario_id = :cenario_id AND tipo = :tipo AND escopo = :escopo
               AND hard = 0 AND ativa = 1 AND origem = 'assistente'",
            ['cenario_id' => (int) $cenarioId, 'tipo' => $tipo, 'escopo' => $escopo]
        ) ?: [];

        foreach ($existentes as $linha) {
            $atuais = json_decode((string) ($linha['parametros_json'] ?? ''), true);
            if (!is_array($atuais)) {
                continue;
            }
            ksort($atuais);
            if ($atuais === $parametros) {
                $this->db->update(
                    'UPDATE educasync_restricoes SET peso = :peso WHERE id = :id',
                    ['peso' => $peso, 'id' => (int) $linha['id']]
                );
                return (int) $linha['id'];
            }
        }

        return (int) $this->db->insert(
            "INSERT INTO educasync_restricoes (cenario_id, tipo, escopo, parametros_json, hard, peso, origem, ativa)
             VALUES (:cenario_id, :tipo, :escopo, :parametros_json, 0, :peso, 'assistente', 1)",
            [
                'cenario_id' => (int) $cenarioId,
                'tipo' => $tipo,
                'escopo' => $escopo,
                'parametros_json' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
                'peso' => $peso,
            ]
        );
    }
}
