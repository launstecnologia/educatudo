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
                    (SELECT pb2.id FROM provas_blocos pb2
                      WHERE pb2.evento_avaliativo_id = e.id AND pb2.deleted_at IS NULL
                      ORDER BY ' . $this->sqlOrdemBloco('pb2') . ' LIMIT 1) AS primeiro_bloco_id,
                    (SELECT pb3.formato_evento FROM provas_blocos pb3
                      WHERE pb3.evento_avaliativo_id = e.id AND pb3.deleted_at IS NULL
                      ORDER BY ' . $this->sqlOrdemBloco('pb3') . ' LIMIT 1) AS primeiro_formato,
                    (SELECT GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR \', \')
                       FROM provas_blocos pb4
                       INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb4.id
                       INNER JOIN turmas t ON t.id = pbt.turma_id
                      WHERE pb4.evento_avaliativo_id = e.id AND pb4.deleted_at IS NULL) AS turmas_nomes
               FROM eventos_avaliativos e
              WHERE ' . $where . '
              ORDER BY ' . $this->sqlOrdemEvento('e'),
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
              ORDER BY ' . $this->sqlOrdemBloco('pb'),
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
              ORDER BY ' . $this->sqlOrdemBloco(),
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

    public function definirTipoAvaliacao(int $id, int $tipoId): void
    {
        if ($id <= 0 || $tipoId <= 0 || !$this->temColuna('tipo_avaliacao_id')) {
            return;
        }
        $this->db->query(
            'UPDATE eventos_avaliativos
                SET tipo_avaliacao_id = :tipo
              WHERE id = :id AND deleted_at IS NULL',
            ['tipo' => $tipoId, 'id' => $id]
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
        if ($this->temColunaOrdem()) {
            $colunas .= ', ordem';
            $valores .= ', :ordem';
            $params['ordem'] = isset($dados['ordem'])
                ? max(1, (int) $dados['ordem'])
                : $this->proximaOrdemEvento((int) $dados['ano_letivo'], (int) $dados['bimestre']);
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

    /**
     * @return array<string,mixed>|null
     */
    public function buscarSubevento(int $eventoId, int $blocoId): ?array
    {
        if ($eventoId <= 0 || $blocoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, titulo
               FROM provas_blocos
              WHERE id = :id AND evento_avaliativo_id = :evento AND deleted_at IS NULL',
            ['id' => $blocoId, 'evento' => $eventoId]
        );

        return $row ?: null;
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

    public function vincularBloco(int $eventoId, int $blocoId, ?int $ordem = null): void
    {
        $extra = '';
        $params = ['evento_id' => $eventoId, 'bloco_id' => $blocoId];
        if ($this->temColunaOrdemBloco()) {
            $extra = ', ordem = :ordem';
            $params['ordem'] = $ordem !== null && $ordem > 0
                ? $ordem
                : $this->proximaOrdemBloco($eventoId);
        }
        $this->db->query(
            'UPDATE provas_blocos
                SET evento_avaliativo_id = :evento_id' . $extra . '
              WHERE id = :bloco_id AND deleted_at IS NULL',
            $params
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

    /**
     * Contagens de vínculos do evento, sem dados de aluno.
     *
     * @return array{subeventos:int,notas_lancadas:int,provas:int,calendario:int,alunos_recuperacao:int}
     */
    public function contarVinculos(int $eventoId): array
    {
        $saida = [
            'subeventos' => 0,
            'notas_lancadas' => 0,
            'provas' => 0,
            'calendario' => 0,
            'alunos_recuperacao' => 0,
        ];
        $eventoId = (int) $eventoId;
        if ($eventoId <= 0) {
            return $saida;
        }

        try {
            $row = $this->db->fetch(
                'SELECT COUNT(*) AS total
                   FROM provas_blocos
                  WHERE evento_avaliativo_id = :id AND deleted_at IS NULL',
                ['id' => $eventoId]
            );
            $saida['subeventos'] = (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            $saida['subeventos'] = 0;
        }

        if ($this->tabelaExiste('provas_blocos_notas_lancadas')) {
            try {
                $row = $this->db->fetch(
                    'SELECT COUNT(*) AS total
                       FROM provas_blocos_notas_lancadas n
                       INNER JOIN provas_blocos pb ON pb.id = n.bloco_id
                      WHERE pb.evento_avaliativo_id = :id
                        AND n.nota IS NOT NULL',
                    ['id' => $eventoId]
                );
                $saida['notas_lancadas'] = (int) ($row['total'] ?? 0);
            } catch (\Throwable $e) {
                $saida['notas_lancadas'] = 0;
            }
        }

        try {
            $coluna = $this->db->fetch("SHOW COLUMNS FROM calendario_letivo_eventos LIKE 'evento_avaliativo_id'");
            if (!empty($coluna)) {
                $row = $this->db->fetch(
                    'SELECT COUNT(*) AS total FROM calendario_letivo_eventos WHERE evento_avaliativo_id = :id',
                    ['id' => $eventoId]
                );
                $saida['calendario'] = (int) ($row['total'] ?? 0);
            }
        } catch (\Throwable $e) {
            $saida['calendario'] = 0;
        }

        if ($this->tabelaAlunosPronta()) {
            try {
                $row = $this->db->fetch(
                    'SELECT COUNT(DISTINCT aluno_id) AS total FROM eventos_avaliativos_alunos WHERE evento_id = :id',
                    ['id' => $eventoId]
                );
                $saida['alunos_recuperacao'] = (int) ($row['total'] ?? 0);
            } catch (\Throwable $e) {
                $saida['alunos_recuperacao'] = 0;
            }
        }

        // Provas vinculadas ao bloco não têm tabela de ligação estável: fica 0.
        return $saida;
    }

    private function tabelaExiste(string $tabela): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1',
                ['t' => $tabela]
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
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

    public function temColunaOrdem(): bool
    {
        return $this->temColuna('ordem');
    }

    public function temColunaOrdemBloco(): bool
    {
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1',
                ['t' => 'provas_blocos', 'c' => 'ordem']
            );
            return !empty($row['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function sqlOrdemEvento(string $alias = 'e'): string
    {
        $pref = $alias !== '' ? $alias . '.' : '';
        if ($this->temColunaOrdem()) {
            return $pref . 'ordem ASC, ' . $pref . 'id ASC';
        }
        return $pref . 'nome ASC, ' . $pref . 'id DESC';
    }

    public function sqlOrdemBloco(string $alias = ''): string
    {
        $pref = $alias !== '' ? $alias . '.' : '';
        if ($this->temColunaOrdemBloco()) {
            return $pref . 'ordem ASC, ' . $pref . 'semana ASC, ' . $pref . 'data_prova ASC, ' . $pref . 'id ASC';
        }
        return $pref . 'semana ASC, ' . $pref . 'data_prova ASC, ' . $pref . 'id ASC';
    }

    public function proximaOrdemEvento(int $ano, int $bimestre): int
    {
        if (!$this->temColunaOrdem() || $ano <= 0 || $bimestre <= 0) {
            return 1;
        }
        $row = $this->db->fetch(
            'SELECT COALESCE(MAX(ordem), 0) + 1 AS proxima
               FROM eventos_avaliativos
              WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bimestre',
            ['ano' => $ano, 'bimestre' => $bimestre]
        );
        return max(1, (int) ($row['proxima'] ?? 1));
    }

    public function proximaOrdemBloco(int $eventoId): int
    {
        if (!$this->temColunaOrdemBloco() || $eventoId <= 0) {
            return 1;
        }
        $row = $this->db->fetch(
            'SELECT COALESCE(MAX(ordem), 0) + 1 AS proxima
               FROM provas_blocos
              WHERE deleted_at IS NULL AND evento_avaliativo_id = :id',
            ['id' => $eventoId]
        );
        return max(1, (int) ($row['proxima'] ?? 1));
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function moverEvento(int $id, string $direcao): array
    {
        if (!$this->temColunaOrdem()) {
            return ['success' => false, 'error' => 'Rode a migration de ordem dos eventos avaliativos.'];
        }
        $evento = $this->findById($id);
        if ($evento === null) {
            return ['success' => false, 'error' => 'Evento não encontrado.'];
        }
        $ano = (int) ($evento['ano_letivo'] ?? 0);
        $bimestre = (int) ($evento['bimestre'] ?? 0);
        $lista = $this->db->fetchAll(
            'SELECT id, ordem
               FROM eventos_avaliativos
              WHERE deleted_at IS NULL AND ano_letivo = :ano AND bimestre = :bimestre
              ORDER BY ordem ASC, id ASC',
            ['ano' => $ano, 'bimestre' => $bimestre]
        ) ?: [];
        return $this->trocarOrdemNaLista($lista, $id, $direcao, 'eventos_avaliativos');
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function moverSubevento(int $eventoId, int $blocoId, string $direcao): array
    {
        if (!$this->temColunaOrdemBloco()) {
            return ['success' => false, 'error' => 'Rode a migration de ordem dos eventos avaliativos.'];
        }
        if ($this->findById($eventoId) === null) {
            return ['success' => false, 'error' => 'Evento não encontrado.'];
        }
        if ($this->buscarSubevento($eventoId, $blocoId) === null) {
            return ['success' => false, 'error' => 'Subevento não encontrado.'];
        }
        $lista = $this->db->fetchAll(
            'SELECT id, ordem
               FROM provas_blocos
              WHERE deleted_at IS NULL AND evento_avaliativo_id = :evento
              ORDER BY ' . $this->sqlOrdemBloco(),
            ['evento' => $eventoId]
        ) ?: [];
        return $this->trocarOrdemNaLista($lista, $blocoId, $direcao, 'provas_blocos');
    }

    /**
     * @param list<array<string,mixed>> $lista
     * @return array{success:bool,error?:string}
     */
    private function trocarOrdemNaLista(array $lista, int $id, string $direcao, string $tabela): array
    {
        $direcao = strtolower(trim($direcao));
        if ($direcao !== 'cima' && $direcao !== 'baixo') {
            return ['success' => false, 'error' => 'Direção inválida.'];
        }
        $indice = -1;
        foreach ($lista as $i => $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $indice = $i;
                break;
            }
        }
        if ($indice < 0) {
            return ['success' => false, 'error' => 'Item não encontrado na lista.'];
        }
        $vizinho = $direcao === 'cima' ? $indice - 1 : $indice + 1;
        if ($vizinho < 0 || $vizinho >= count($lista)) {
            return ['success' => true];
        }

        $tabelasOk = ['eventos_avaliativos' => true, 'provas_blocos' => true];
        if (!isset($tabelasOk[$tabela])) {
            return ['success' => false, 'error' => 'Não foi possível reposicionar.'];
        }

        $a = $lista[$indice];
        $b = $lista[$vizinho];
        $idA = (int) ($a['id'] ?? 0);
        $idB = (int) ($b['id'] ?? 0);
        $ordemA = (int) ($a['ordem'] ?? 0);
        $ordemB = (int) ($b['ordem'] ?? 0);
        if ($idA <= 0 || $idB <= 0) {
            return ['success' => false, 'error' => 'Não foi possível reposicionar.'];
        }

        try {
            $this->db->beginTransaction();
            // Se empatam na ordem, renumera a lista inteira e troca as posições.
            if ($ordemA === $ordemB || $ordemA <= 0 || $ordemB <= 0) {
                $ids = [];
                foreach ($lista as $item) {
                    $ids[] = (int) ($item['id'] ?? 0);
                }
                $tmp = $ids[$indice];
                $ids[$indice] = $ids[$vizinho];
                $ids[$vizinho] = $tmp;
                foreach ($ids as $pos => $itemId) {
                    if ($itemId <= 0) {
                        continue;
                    }
                    $this->db->query(
                        'UPDATE `' . $tabela . '` SET ordem = :ordem WHERE id = :id',
                        ['ordem' => $pos + 1, 'id' => $itemId]
                    );
                }
                $this->db->commit();
                return ['success' => true];
            }

            $this->db->query(
                'UPDATE `' . $tabela . '` SET ordem = :ordem WHERE id = :id',
                ['ordem' => $ordemB, 'id' => $idA]
            );
            $this->db->query(
                'UPDATE `' . $tabela . '` SET ordem = :ordem WHERE id = :id',
                ['ordem' => $ordemA, 'id' => $idB]
            );
            $this->db->commit();
            return ['success' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return ['success' => false, 'error' => 'Não foi possível reposicionar.'];
        }
    }
}
