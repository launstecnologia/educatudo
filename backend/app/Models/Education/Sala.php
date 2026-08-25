<?php
/**
 * EducaTudo - Modelo de Sala / Ambiente
 * Gerencia operações de banco de dados para salas e ambientes da escola
 * (sala de aula, laboratório, quadra, biblioteca etc.), usados como Sala
 * Padrão da Turma e pelo módulo de Patrimônio.
 *
 * Nota: a tabela física continua se chamando `school_locations` — já
 * existia (usada por `PatrimonyAdminController`) antes deste cadastro
 * próprio; o rename para "Sala/Ambiente" ficou só na camada de aplicação
 * (mesmo padrão de `ComponenteCurricular`/`materias`).
 */

class Sala
{
    private $db;

    /** Catálogo fixo de tipos (valor salvo => rótulo exibido) */
    public const TIPOS = [
        'sala' => 'Sala de Aula',
        'laboratorio' => 'Laboratório',
        'quadra' => 'Quadra',
        'biblioteca' => 'Biblioteca',
        'secretaria' => 'Secretaria',
        'cantina' => 'Cantina',
        'deposito' => 'Depósito',
        'outro' => 'Outro',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(bool $apenasAtivas = false)
    {
        $sql = "SELECT * FROM school_locations";
        if ($apenasAtivas) {
            $sql .= " WHERE ativo = 1";
        }
        $sql .= " ORDER BY nome ASC";

        return $this->db->fetchAll($sql);
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT * FROM school_locations WHERE id = :id",
            ['id' => (int) $id]
        );
    }

    public function create(array $data)
    {
        $sql = "INSERT INTO school_locations
                    (codigo, nome, tipo, capacidade, bloco, andar, responsavel_nome, ativo)
                VALUES
                    (:codigo, :nome, :tipo, :capacidade, :bloco, :andar, :responsavel_nome, :ativo)";

        return $this->db->insert($sql, $this->paramsFromData($data));
    }

    public function update($id, array $data)
    {
        $sql = "UPDATE school_locations SET
                    codigo = :codigo,
                    nome = :nome,
                    tipo = :tipo,
                    capacidade = :capacidade,
                    bloco = :bloco,
                    andar = :andar,
                    responsavel_nome = :responsavel_nome,
                    ativo = :ativo
                WHERE id = :id";

        $params = $this->paramsFromData($data);
        $params['id'] = (int) $id;

        return $this->db->update($sql, $params);
    }

    public function delete($id)
    {
        return $this->db->delete("DELETE FROM school_locations WHERE id = :id", ['id' => (int) $id]);
    }

    public function exists($id)
    {
        $result = $this->db->fetch("SELECT id FROM school_locations WHERE id = :id", ['id' => (int) $id]);
        return $result !== false;
    }

    public function codigoExists($codigo, $excludeId = null)
    {
        if ($codigo === null || $codigo === '') {
            return false;
        }
        $sql = "SELECT id FROM school_locations WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = (int) $excludeId;
        }

        $result = $this->db->fetch($sql, $params);
        return $result !== false;
    }

    /**
     * Conta registros dependentes que referenciam esta sala/ambiente —
     * usado pelo Service pra bloquear exclusão. Cobre a Turma (Sala Padrão)
     * e as tabelas de Patrimônio/Almoxarifado que já usavam
     * `school_locations` antes deste cadastro (`PatrimonyAdminController`,
     * `InventoryAdminController`) — nenhuma delas tem `FOREIGN KEY` real
     * pra `school_locations` (só índice), então sem essa checagem a
     * exclusão deixaria `location_id` órfão silenciosamente.
     */
    public function countVinculos(int $id): int
    {
        $total = 0;

        $consultas = [
            ['tabela' => 'turmas', 'coluna' => 'sala_padrao_id'],
            ['tabela' => 'patrimony_assets', 'coluna' => 'location_id'],
            ['tabela' => 'patrimony_movements', 'coluna' => 'location_origem_id'],
            ['tabela' => 'patrimony_movements', 'coluna' => 'location_destino_id'],
            ['tabela' => 'patrimony_inventory_checks', 'coluna' => 'location_id'],
            ['tabela' => 'inventory_warehouses', 'coluna' => 'location_id'],
        ];

        foreach ($consultas as $c) {
            try {
                $result = $this->db->fetch(
                    "SELECT COUNT(*) as total FROM {$c['tabela']} WHERE {$c['coluna']} = :id",
                    ['id' => $id]
                );
                $total += (int) ($result['total'] ?? 0);
            } catch (\Throwable $e) {
                // Tabela/coluna pode não existir em instâncias antigas — ignora nesse caso.
            }
        }

        return $total;
    }

    private function paramsFromData(array $data): array
    {
        return [
            'codigo' => $data['codigo'] !== '' ? $data['codigo'] : null,
            'nome' => $data['nome'],
            'tipo' => $data['tipo'],
            'capacidade' => $data['capacidade'] !== '' ? (int) $data['capacidade'] : null,
            'bloco' => $data['bloco'] !== '' ? $data['bloco'] : null,
            'andar' => $data['andar'] !== '' ? $data['andar'] : null,
            'responsavel_nome' => $data['responsavel_nome'] !== '' ? $data['responsavel_nome'] : null,
            'ativo' => (int) $data['ativo'],
        ];
    }
}
