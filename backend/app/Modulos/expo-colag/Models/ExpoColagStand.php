<?php
/**
 * Stands e QR público da Expo Colag (S4).
 */

class ExpoColagStand
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM expo_colag_stands WHERE id = :id', ['id' => $id]);
        return $row ?: null;
    }

    public function findByProjeto(int $projetoId): ?array
    {
        $row = $this->db->fetch(
            'SELECT s.*, se.nome AS setor_nome
             FROM expo_colag_stands s
             LEFT JOIN expo_colag_setores se ON se.id = s.setor_id
             WHERE s.projeto_id = :projeto_id',
            ['projeto_id' => $projetoId]
        );
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
        if (strlen($token) !== 32) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT s.*, se.nome AS setor_nome, p.titulo, p.subtitulo, p.area, p.produto_esperado,
                    p.capa_url AS projeto_capa_url, p.status AS projeto_status, p.ativo AS projeto_ativo,
                    p.descricao, pr.nome AS professor_nome
             FROM expo_colag_stands s
             INNER JOIN expo_colag_projetos p ON p.id = s.projeto_id
             LEFT JOIN expo_colag_setores se ON se.id = s.setor_id
             LEFT JOIN professores pr ON pr.id = p.professor_id
             WHERE s.qr_token = :token',
            ['token' => $token]
        );
        return $row ?: null;
    }

    public function listarPorEdicao(int $edicaoId): array
    {
        return $this->db->fetchAll(
            'SELECT s.*, p.titulo AS projeto_titulo, se.nome AS setor_nome
             FROM expo_colag_stands s
             INNER JOIN expo_colag_projetos p ON p.id = s.projeto_id
             LEFT JOIN expo_colag_setores se ON se.id = s.setor_id
             WHERE s.edicao_id = :edicao_id
             ORDER BY se.ordem ASC, s.numero ASC, p.titulo ASC',
            ['edicao_id' => $edicaoId]
        ) ?: [];
    }

    public function criarOuObter(int $edicaoId, int $projetoId, array $extra = []): array
    {
        $existente = $this->findByProjeto($projetoId);
        if ($existente) {
            return $existente;
        }
        $token = bin2hex(random_bytes(16));
        $id = (int) $this->db->insert(
            'INSERT INTO expo_colag_stands
                (edicao_id, projeto_id, setor_id, numero, qr_token, horario_apresentacao, resumo_publico, capa_url)
             VALUES
                (:edicao_id, :projeto_id, :setor_id, :numero, :qr_token, :horario, :resumo, :capa)',
            [
                'edicao_id' => $edicaoId,
                'projeto_id' => $projetoId,
                'setor_id' => !empty($extra['setor_id']) ? (int) $extra['setor_id'] : null,
                'numero' => trim((string) ($extra['numero'] ?? '')) ?: null,
                'qr_token' => $token,
                'horario' => $extra['horario_apresentacao'] ?? null,
                'resumo' => trim((string) ($extra['resumo_publico'] ?? '')) ?: null,
                'capa' => trim((string) ($extra['capa_url'] ?? '')) ?: null,
            ]
        );
        return $this->findById($id) ?? ['id' => $id, 'qr_token' => $token, 'projeto_id' => $projetoId];
    }

    public function atualizar(int $id, array $data): void
    {
        $this->db->query(
            'UPDATE expo_colag_stands
             SET setor_id = :setor_id, numero = :numero,
                 horario_apresentacao = :horario, resumo_publico = :resumo,
                 capa_url = :capa, ativo = :ativo
             WHERE id = :id',
            [
                'id' => $id,
                'setor_id' => !empty($data['setor_id']) ? (int) $data['setor_id'] : null,
                'numero' => trim((string) ($data['numero'] ?? '')) ?: null,
                'horario' => $data['horario_apresentacao'] ?? null,
                'resumo' => trim((string) ($data['resumo_publico'] ?? '')) ?: null,
                'capa' => trim((string) ($data['capa_url'] ?? '')) ?: null,
                'ativo' => isset($data['ativo']) ? (!empty($data['ativo']) ? 1 : 0) : 1,
            ]
        );
    }

    public function regenerarToken(int $id): string
    {
        $token = bin2hex(random_bytes(16));
        $this->db->query(
            'UPDATE expo_colag_stands SET qr_token = :token WHERE id = :id',
            ['token' => $token, 'id' => $id]
        );
        return $token;
    }

    /** Primeiros nomes dos alunos aprovados (sem sobrenome). */
    public function primeirosNomesAprovados(int $projetoId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT a.nome
             FROM expo_colag_inscricoes i
             INNER JOIN alunos a ON a.id = i.aluno_id
             WHERE i.projeto_id = :projeto_id AND i.status = 'Aprovada'
             ORDER BY a.nome ASC",
            ['projeto_id' => $projetoId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $nome = trim((string) ($r['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $partes = preg_split('/\s+/', $nome) ?: [];
            $primeiro = $partes[0] ?? '';
            if ($primeiro !== '') {
                $out[] = $primeiro;
            }
        }
        return $out;
    }
}
