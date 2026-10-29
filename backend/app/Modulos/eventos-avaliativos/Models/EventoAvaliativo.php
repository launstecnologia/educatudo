<?php

namespace App\Modulos\EventosAvaliativos\Models;

use App\Core\Database;

class EventoAvaliativo
{
    /** Período fora dos bimestres/trimestres. Cabe em TINYINT e não coincide com 1–4. */
    public const PERIODO_RECUPERACAO_FINAL = 90;

    public const ROTULO_RECUPERACAO_FINAL = 'Recuperação final';

    private $db;

    public static function ehRecuperacaoFinal(int $bimestre): bool
    {
        return $bimestre === self::PERIODO_RECUPERACAO_FINAL;
    }

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tabelasProntas(): bool
    {
        try {
            $tabela = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1',
                ['t' => 'eventos_avaliativos']
            );
            $coluna = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1',
                ['t' => 'provas_blocos', 'c' => 'evento_avaliativo_id']
            );
            return !empty($tabela['ok']) && !empty($coluna['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $ano = 0, int $bimestre = 0): array
    {
        $where = 'e.deleted_at IS NULL';
        $params = [];
        if ($ano > 0) {
            $where .= ' AND e.ano_letivo = :ano';
            $params['ano'] = $ano;
        }
        if ($bimestre > 0) {
            $where .= ' AND e.bimestre = :bimestre';
            $params['bimestre'] = $bimestre;
        }

        $eventos = $this->db->fetchAll(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM provas_blocos pb
                      WHERE pb.evento_avaliativo_id = e.id AND pb.deleted_at IS NULL) AS total_subeventos,
                    (SELECT MIN(pb2.id) FROM provas_blocos pb2
                      WHERE pb2.evento_avaliativo_id = e.id AND pb2.deleted_at IS NULL) AS primeiro_bloco_id,
                    (SELECT pb3.formato_evento FROM provas_blocos pb3
                      WHERE pb3.evento_avaliativo_id = e.id AND pb3.deleted_at IS NULL
                      ORDER BY pb3.id ASC LIMIT 1) AS primeiro_formato,
                    (SELECT GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR \', \')
                       FROM provas_blocos pb4
                       INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb4.id
                       INNER JOIN turmas t ON t.id = pbt.turma_id
                      WHERE pb4.evento_avaliativo_id = e.id AND pb4.deleted_at IS NULL) AS turmas_nomes
               FROM eventos_avaliativos e
              WHERE ' . $where . '
              ORDER BY e.nome ASC, e.id DESC',
            $params
        ) ?: [];
        if ($eventos === []) {
            return [];
        }

        $ids = [];
        foreach ($eventos as $evento) {
            $id = (int) ($evento['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $porEvento = $this->subeventosPorEventos(array_values($ids));
        foreach ($eventos as $i => $evento) {
            $eventos[$i]['subeventos'] = $porEvento[(int) ($evento['id'] ?? 0)] ?? [];
        }

        return $eventos;
    }

    /**
     * @param list<int> $eventoIds
     * @return array<int,list<array<string,mixed>>>
     */
    private function subeventosPorEventos(array $eventoIds): array
    {
        $eventoIds = array_values(array_unique(array_filter(array_map('intval', $eventoIds), static fn (int $id): bool => $id > 0)));
        if ($eventoIds === []) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach ($eventoIds as $i => $id) {
            $chave = 'ev' . $i;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $id;
        }
        $rows = $this->db->fetchAll(
            'SELECT pb.id, pb.evento_avaliativo_id, pb.titulo, pb.data_prova, pb.formato_evento,
                    (SELECT GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR \', \')
                       FROM provas_blocos_turmas pbt
                       INNER JOIN turmas t ON t.id = pbt.turma_id
                      WHERE pbt.bloco_id = pb.id) AS turmas_nomes
               FROM provas_blocos pb
              WHERE pb.deleted_at IS NULL
                AND pb.evento_avaliativo_id IN (' . implode(', ', $placeholders) . ')
              ORDER BY pb.semana ASC, pb.data_prova ASC, pb.id ASC',
            $params
        ) ?: [];
        $porEvento = [];
        foreach ($rows as $row) {
            $eventoId = (int) ($row['evento_avaliativo_id'] ?? 0);
            if ($eventoId <= 0) {
                continue;
            }
            $porEvento[$eventoId][] = $row;
        }

        return $porEvento;
    }

    /**
     * @return array<int,int>
     */
    public function contarPorAno(int $ano): array
    {
        if ($ano <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT bimestre, COUNT(*) AS total
               FROM eventos_avaliativos
              WHERE deleted_at IS NULL AND ano_letivo = :ano
              GROUP BY bimestre',
            ['ano' => $ano]
        ) ?: [];
        $totais = [];
        foreach ($rows as $row) {
            $totais[(int) ($row['bimestre'] ?? 0)] = (int) ($row['total'] ?? 0);
        }
        return $totais;
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT * FROM eventos_avaliativos WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarSubeventos(int $eventoId): array
    {
        return $this->db->fetchAll(
            'SELECT id, titulo, data_prova, status, semana, formato_evento
               FROM provas_blocos
              WHERE evento_avaliativo_id = :id AND deleted_at IS NULL
              ORDER BY semana ASC, data_prova ASC, id ASC',
            ['id' => $eventoId]
        ) ?: [];
    }

    /**
     * @param array<string,mixed> $dados
     */
    public function temColunaLiberarNota(): bool
    {
        return $this->temColuna('liberar_nota_aluno');
    }

    public function temColunaSigla(): bool
    {
        return $this->temColuna('sigla');
    }

    public function temColunaRascunho(): bool
    {
        return $this->temColuna('rascunho');
    }

    /**
     * @return array<int,int>
     */
    public function contarRascunhosPorAno(int $ano): array
    {
        if ($ano <= 0 || !$this->temColunaRascunho()) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT bimestre, COUNT(*) AS total
               FROM eventos_avaliativos
              WHERE deleted_at IS NULL AND rascunho = 1 AND ano_letivo = :ano
              GROUP BY bimestre',
            ['ano' => $ano]
        ) ?: [];
        $totais = [];
        foreach ($rows as $row) {
            $totais[(int) ($row['bimestre'] ?? 0)] = (int) ($row['total'] ?? 0);
        }
        return $totais;
    }

    public function publicar(int $id): void
    {
        if ($id <= 0 || !$this->temColunaRascunho()) {
            return;
        }
        $this->db->query(
            'UPDATE eventos_avaliativos SET rascunho = 0 WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    private function temColuna(string $coluna): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1',
                ['t' => 'eventos_avaliativos', 'c' => $coluna]
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function criar(array $dados): int
    {
        $colunas = 'nome, descricao, ano_letivo, bimestre, tipo_avaliacao_id, grupo_regras_notas_id, ativo, criado_por';
        $valores = ':nome, :descricao, :ano_letivo, :bimestre, :tipo_avaliacao_id, :grupo_regras_notas_id, 1, :criado_por';
        $params = [
            'nome' => (string) $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'ano_letivo' => (int) $dados['ano_letivo'],
            'bimestre' => (int) $dados['bimestre'],
            'tipo_avaliacao_id' => (int) $dados['tipo_avaliacao_id'],
            'grupo_regras_notas_id' => (int) $dados['grupo_regras_notas_id'],
            'criado_por' => !empty($dados['criado_por']) ? (int) $dados['criado_por'] : null,
        ];
        if ($this->temColunaLiberarNota()) {
            $colunas .= ', liberar_nota_aluno';
            $valores .= ', :liberar_nota_aluno';
            $params['liberar_nota_aluno'] = !empty($dados['liberar_nota_aluno']) ? 1 : 0;
        }
        if ($this->temColunaSigla()) {
            $colunas .= ', sigla';
            $valores .= ', :sigla';
            $params['sigla'] = $this->siglaGravavel($dados['sigla'] ?? null);
        }
        if ($this->temColunaRascunho()) {
            $colunas .= ', rascunho';
            $valores .= ', :rascunho';
            $params['rascunho'] = !empty($dados['rascunho']) ? 1 : 0;
        }
        return (int) $this->db->insert(
            'INSERT INTO eventos_avaliativos (' . $colunas . ') VALUES (' . $valores . ')',
            $params
        );
    }

    /**
     * @param array<string,mixed> $dados
     */
    public function atualizar(int $id, array $dados): void
    {
        $extra = '';
        $params = [
            'id' => $id,
            'nome' => (string) $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'ano_letivo' => (int) $dados['ano_letivo'],
            'bimestre' => (int) $dados['bimestre'],
        ];
        if ($this->temColunaLiberarNota()) {
            $extra .= ', liberar_nota_aluno = :liberar_nota_aluno';
            $params['liberar_nota_aluno'] = !empty($dados['liberar_nota_aluno']) ? 1 : 0;
        }
        if ($this->temColunaSigla()) {
            $extra .= ', sigla = :sigla';
            $params['sigla'] = $this->siglaGravavel($dados['sigla'] ?? null);
        }
        $this->db->query(
            'UPDATE eventos_avaliativos
                SET nome = :nome,
                    descricao = :descricao,
                    ano_letivo = :ano_letivo,
                    bimestre = :bimestre' . $extra . '
              WHERE id = :id AND deleted_at IS NULL',
            $params
        );
    }

    /**
     * @param mixed $sigla
     */
    private function siglaGravavel($sigla): ?string
    {
        $sigla = strtoupper(trim((string) $sigla));
        if ($sigla === '' || !preg_match('/^[A-Z0-9]{1,12}$/', $sigla)) {
            return null;
        }
        return $sigla;
    }

    public function blocoTemNota(int $blocoId): bool
    {
        if ($blocoId <= 0) {
            return false;
        }
        try {
            $row = $this->db->fetch(
                'SELECT COUNT(*) AS total
                   FROM provas_blocos_notas_lancadas
                  WHERE bloco_id = :id
                    AND nota IS NOT NULL',
                ['id' => $blocoId]
            );
            return (int) ($row['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function vincularBloco(int $eventoId, int $blocoId): void
    {
        $this->db->query(
            'UPDATE provas_blocos
                SET evento_avaliativo_id = :evento_id
              WHERE id = :bloco_id AND deleted_at IS NULL',
            ['evento_id' => $eventoId, 'bloco_id' => $blocoId]
        );
    }

    public function temNotaLancada(int $eventoId): bool
    {
        try {
            $tabela = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1',
                ['t' => 'provas_blocos_notas_lancadas']
            );
            if (empty($tabela['ok'])) {
                return false;
            }
            $row = $this->db->fetch(
                'SELECT COUNT(*) AS total
                   FROM provas_blocos_notas_lancadas n
                   INNER JOIN provas_blocos pb ON pb.id = n.bloco_id
                  WHERE pb.evento_avaliativo_id = :id
                    AND n.nota IS NOT NULL',
                ['id' => $eventoId]
            );
            return (int) ($row['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function tabelaAlunosPronta(): bool
    {
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'eventos_avaliativos_alunos'");
            return $row !== false && $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function colunaMateriaPronta(): bool
    {
        if (!$this->tabelaAlunosPronta()) {
            return false;
        }
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM eventos_avaliativos_alunos LIKE 'materia_id'");
            return $row !== false && $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array{aluno_id:int,materia_id:int}>
     */
    public function alunosDoEvento(int $eventoId): array
    {
        if ($eventoId <= 0 || !$this->tabelaAlunosPronta()) {
            return [];
        }
        $materia = $this->colunaMateriaPronta() ? 'materia_id' : 'NULL AS materia_id';
        $rows = $this->db->fetchAll(
            'SELECT aluno_id, ' . $materia . ' FROM eventos_avaliativos_alunos WHERE evento_id = :id ORDER BY aluno_id',
            ['id' => $eventoId]
        );
        $pares = [];
        foreach ($rows ?: [] as $row) {
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            $pares[] = [
                'aluno_id' => $alunoId,
                'materia_id' => (int) ($row['materia_id'] ?? 0),
            ];
        }
        return $pares;
    }

    /**
     * Sem linhas no evento, a turma inteira vale.
     * Com linhas, só os alunos daquela matéria. Lista vazia com restringe=true não libera ninguém.
     *
     * @return array{restringe:bool,alunos:list<int>}
     */
    public function convocadosDoBloco(int $blocoId, int $materiaId = 0): array
    {
        if ($blocoId <= 0 || !$this->tabelaAlunosPronta()) {
            return ['restringe' => false, 'alunos' => []];
        }
        $temMateria = $this->colunaMateriaPronta();
        $rows = $this->db->fetchAll(
            'SELECT a.aluno_id, ' . ($temMateria ? 'a.materia_id' : 'NULL AS materia_id') . '
               FROM eventos_avaliativos_alunos a
               INNER JOIN provas_blocos pb ON pb.evento_avaliativo_id = a.evento_id
              WHERE pb.id = :id',
            ['id' => $blocoId]
        );
        if (!$rows) {
            return ['restringe' => false, 'alunos' => []];
        }
        $ids = [];
        foreach ($rows as $row) {
            $alunoId = (int) ($row['aluno_id'] ?? 0);
            $mat = (int) ($row['materia_id'] ?? 0);
            if ($alunoId <= 0) {
                continue;
            }
            if (!$temMateria || $mat <= 0 || $materiaId <= 0 || $mat === $materiaId) {
                $ids[$alunoId] = $alunoId;
            }
        }
        return ['restringe' => true, 'alunos' => array_values($ids)];
    }

    /**
     * @param list<array{0:int,1:int}> $pares
     */
    public function substituirPares(int $eventoId, array $pares): void
    {
        if ($eventoId <= 0 || !$this->colunaMateriaPronta()) {
            return;
        }
        $this->db->beginTransaction();
        try {
            $this->db->query(
                'DELETE FROM eventos_avaliativos_alunos WHERE evento_id = :id',
                ['id' => $eventoId]
            );
            $vistos = [];
            foreach ($pares as $par) {
                $alunoId = (int) ($par[0] ?? 0);
                $materiaId = (int) ($par[1] ?? 0);
                $chave = $alunoId . ':' . $materiaId;
                if ($alunoId <= 0 || $materiaId <= 0 || isset($vistos[$chave])) {
                    continue;
                }
                $vistos[$chave] = true;
                $this->db->query(
                    'INSERT INTO eventos_avaliativos_alunos (evento_id, aluno_id, materia_id) VALUES (:evento, :aluno, :materia)',
                    ['evento' => $eventoId, 'aluno' => $alunoId, 'materia' => $materiaId]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    public function limparAlunos(int $eventoId): void
    {
        if ($eventoId <= 0 || !$this->tabelaAlunosPronta()) {
            return;
        }
        $this->db->query(
            'DELETE FROM eventos_avaliativos_alunos WHERE evento_id = :id',
            ['id' => $eventoId]
        );
    }

    public function marcarExcluido(int $id): void
    {
        $this->db->query(
            'UPDATE eventos_avaliativos
                SET deleted_at = NOW(), ativo = 0
              WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }
}
