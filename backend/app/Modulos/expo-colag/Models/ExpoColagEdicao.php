<?php
/**
 * Edição da Expo Colag (evento / feira).
 */

class ExpoColagEdicao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM expo_colag_edicoes WHERE id = :id',
            ['id' => $id]
        );
        return $row ?: null;
    }

    /** Edição ativa mais recente (Planejamento / Publicada / Em_andamento). */
    public function findAtiva(): ?array
    {
        $row = $this->db->fetch(
            "SELECT * FROM expo_colag_edicoes
             WHERE status IN ('Planejamento','Publicada','Em_andamento')
             ORDER BY id DESC
             LIMIT 1"
        );
        return $row ?: null;
    }

    public function listarTodas(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM expo_colag_edicoes ORDER BY id DESC'
        ) ?: [];
    }

    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO expo_colag_edicoes
                (nome, edicao, tema, data_evento, hora_inicio, hora_fim, local, mapa_url, config, status,
                 voto_publico_ativo, checkin_ativo)
             VALUES
                (:nome, :edicao, :tema, :data_evento, :hora_inicio, :hora_fim, :local, :mapa_url, :config, :status,
                 :voto_publico_ativo, :checkin_ativo)',
            [
                'nome' => $data['nome'] ?? 'Expo Colag',
                'edicao' => $data['edicao'] ?? '2026',
                'tema' => $data['tema'] ?? null,
                'data_evento' => $data['data_evento'] ?? null,
                'hora_inicio' => $data['hora_inicio'] ?? null,
                'hora_fim' => $data['hora_fim'] ?? null,
                'local' => $data['local'] ?? null,
                'mapa_url' => $data['mapa_url'] ?? null,
                'config' => $data['config'] ?? null,
                'status' => $data['status'] ?? 'Planejamento',
                'voto_publico_ativo' => (int) ($data['voto_publico_ativo'] ?? 1),
                'checkin_ativo' => (int) ($data['checkin_ativo'] ?? 1),
            ]
        );
    }

    public function update(int $id, array $data): bool
    {
        $campos = [];
        $params = ['id' => $id];
        $permitidos = [
            'nome', 'edicao', 'tema', 'data_evento', 'hora_inicio', 'hora_fim',
            'local', 'mapa_url', 'config', 'status', 'programacao_publica_em',
            'voto_publico_ativo', 'votacao_inicio', 'votacao_fim', 'checkin_ativo',
        ];
        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $data)) {
                continue;
            }
            $campos[] = "`{$campo}` = :{$campo}";
            $params[$campo] = $data[$campo];
        }
        if ($campos === []) {
            return false;
        }
        return (bool) $this->db->query(
            'UPDATE expo_colag_edicoes SET ' . implode(', ', $campos) . ' WHERE id = :id',
            $params
        );
    }
}
