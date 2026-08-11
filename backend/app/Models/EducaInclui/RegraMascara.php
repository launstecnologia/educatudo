<?php

if (!class_exists('RegraMascara')) {
class RegraMascara
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return array<string,string> chave_regra => valor_regra
     */
    public function getMapaPorMascara(int $mascaraId): array
    {
        if ($mascaraId <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT chave_regra, valor_regra FROM regras_mascara
             WHERE mascara_id = :id
             ORDER BY precedencia DESC, id ASC",
            ['id' => $mascaraId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['chave_regra']] = (string) ($r['valor_regra'] ?? '');
        }
        return $map;
    }

    /**
     * Substitui todas as regras da máscara pelo conjunto informado.
     *
     * @param array<string,string> $rules chave_regra => valor_regra
     */
    public function substituirPorMascara(int $mascaraId, array $rules): void
    {
        if ($mascaraId <= 0) {
            return;
        }
        $this->db->delete(
            "DELETE FROM regras_mascara WHERE mascara_id = :id",
            ['id' => $mascaraId]
        );
        foreach ($rules as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $this->db->insert(
                "INSERT INTO regras_mascara (mascara_id, chave_regra, valor_regra, created_at)
                 VALUES (:id, :chave_regra, :valor_regra, NOW())",
                [
                    'id' => $mascaraId,
                    'chave_regra' => $key,
                    'valor_regra' => (string) $value,
                ]
            );
        }
    }
}
}
