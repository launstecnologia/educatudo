<?php

require_once __DIR__ . '/../../../Core/Database.php';

class PresencaConfig
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        return $this->db->tableExists('presenca_config')
            && $this->db->tableExists('presenca_eventos');
    }

    /**
     * @return array{
     *   tolerancia_atraso_min:int,
     *   minutos_corte_sem_entrada:int,
     *   criar_aula_rascunho:bool,
     *   consolidar_boletim:bool,
     *   data_corte:?string
     * }
     */
    public function obter(): array
    {
        $padrao = [
            'tolerancia_atraso_min' => 10,
            'minutos_corte_sem_entrada' => 30,
            'criar_aula_rascunho' => true,
            'consolidar_boletim' => false,
            'data_corte' => null,
        ];
        if (!$this->db->tableExists('presenca_config')) {
            return $padrao;
        }
        $row = $this->db->fetch('SELECT * FROM presenca_config WHERE id = 1');
        if (!$row) {
            $this->db->insert('INSERT INTO presenca_config (id) VALUES (1)');
            return $padrao;
        }
        $corte = trim((string) ($row['data_corte'] ?? ''));
        return [
            'tolerancia_atraso_min' => max(0, min(180, (int) ($row['tolerancia_atraso_min'] ?? 10))),
            'minutos_corte_sem_entrada' => max(0, min(240, (int) ($row['minutos_corte_sem_entrada'] ?? 30))),
            'criar_aula_rascunho' => (int) ($row['criar_aula_rascunho'] ?? 1) === 1,
            'consolidar_boletim' => (int) ($row['consolidar_boletim'] ?? 0) === 1,
            'data_corte' => $corte !== '' ? $corte : null,
        ];
    }

    public function salvar(array $post): void
    {
        if (!$this->db->tableExists('presenca_config')) {
            throw new RuntimeException('Rode a migration 2026_08_22_gestao_presenca.sql no Master.');
        }
        $existe = $this->db->fetch('SELECT id FROM presenca_config WHERE id = 1');
        if (!$existe) {
            $this->db->insert('INSERT INTO presenca_config (id) VALUES (1)');
        }
        $corte = trim((string) ($post['data_corte'] ?? ''));
        $this->db->update(
            'UPDATE presenca_config
             SET tolerancia_atraso_min = :tol,
                 minutos_corte_sem_entrada = :corte_min,
                 criar_aula_rascunho = :criar,
                 consolidar_boletim = :consol,
                 data_corte = :data_corte
             WHERE id = 1',
            [
                'tol' => max(0, min(180, (int) ($post['tolerancia_atraso_min'] ?? 10))),
                'corte_min' => max(0, min(240, (int) ($post['minutos_corte_sem_entrada'] ?? 30))),
                'criar' => !empty($post['criar_aula_rascunho']) ? 1 : 0,
                'consol' => !empty($post['consolidar_boletim']) ? 1 : 0,
                'data_corte' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte) ? $corte : null,
            ]
        );
    }
}
