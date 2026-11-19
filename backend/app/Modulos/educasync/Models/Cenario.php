<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — cenário (conjunto de slots + demandas a otimizar).
 */
class Cenario
{
    /** grade_horaria só aceita manha/tarde — noite fica fora do MVP. */
    public const PERIODOS = [
        'manha' => 'Manhã',
        'tarde' => 'Tarde',
    ];

    public const STATUS = [
        'rascunho' => 'Rascunho',
        'ativo' => 'Ativo',
        'arquivado' => 'Arquivado',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function schemaPronto(): bool
    {
        return (bool) $this->db->tableExists('educasync_cenarios')
            && (bool) $this->db->tableExists('educasync_execucoes');
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(): array
    {
        return $this->db->fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM educasync_slots s WHERE s.cenario_id = c.id) AS total_slots,
                    (SELECT COUNT(*) FROM educasync_demandas d WHERE d.cenario_id = c.id) AS total_demandas,
                    (SELECT COUNT(*) FROM educasync_versoes v WHERE v.cenario_id = c.id) AS total_versoes
             FROM educasync_cenarios c
             WHERE c.status <> 'arquivado'
             ORDER BY c.created_at DESC, c.id DESC"
        ) ?: [];
    }

    public function findById(int $id): ?array
    {
        $linha = $this->db->fetch(
            'SELECT * FROM educasync_cenarios WHERE id = :id',
            ['id' => (int) $id]
        );
        return $linha ?: null;
    }

    /**
     * @param array{nome:string, ano_letivo:int, periodo:string, max_time_s:int, pesos_json:string, created_by:int} $dados
     */
    public function criar(array $dados): int
    {
        return (int) $this->db->insert(
            "INSERT INTO educasync_cenarios (nome, ano_letivo, periodo, status, pesos_json, max_time_s, created_by)
             VALUES (:nome, :ano_letivo, :periodo, 'rascunho', :pesos_json, :max_time_s, :created_by)",
            [
                'nome' => (string) $dados['nome'],
                'ano_letivo' => (int) $dados['ano_letivo'],
                'periodo' => (string) $dados['periodo'],
                'pesos_json' => (string) $dados['pesos_json'],
                'max_time_s' => (int) $dados['max_time_s'],
                'created_by' => (int) $dados['created_by'],
            ]
        );
    }

    public function atualizarStatus(int $id, string $status): void
    {
        $this->db->update(
            'UPDATE educasync_cenarios SET status = :status WHERE id = :id',
            ['status' => $status, 'id' => (int) $id]
        );
    }

    public function atualizarAnoLetivo(int $id, int $anoLetivo): void
    {
        $this->db->update(
            'UPDATE educasync_cenarios SET ano_letivo = :ano_letivo WHERE id = :id',
            ['ano_letivo' => (int) $anoLetivo, 'id' => (int) $id]
        );
    }

    /**
     * @param array<string,int|float> $pesos
     */
    public function atualizarPesos(int $id, array $pesos): void
    {
        $this->db->update(
            'UPDATE educasync_cenarios SET pesos_json = :pesos_json WHERE id = :id',
            ['pesos_json' => json_encode($pesos, JSON_UNESCAPED_UNICODE), 'id' => (int) $id]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function turmasAtivas(int $anoLetivo): array
    {
        return $this->db->fetchAll(
            "SELECT t.id, t.nome,
                    COALESCE(NULLIF(c.nome, ''), 'Outros') AS curso_nome,
                    COALESCE(NULLIF(s.nome, ''), NULLIF(t.serie, ''), 'Sem série') AS serie_nome,
                    COALESCE(t.curso_novo_id, t.curso_id, 0) AS curso_id,
                    COALESCE(t.serie_id, 0) AS serie_id
             FROM turmas t
             LEFT JOIN curso c ON c.id = COALESCE(t.curso_novo_id, t.curso_id)
             LEFT JOIN serie s ON s.id = t.serie_id
             WHERE t.ativo = 1 AND t.ano_letivo = :ano
             ORDER BY curso_nome ASC, serie_nome ASC, t.nome ASC",
            ['ano' => (int) $anoLetivo]
        ) ?: [];
    }

    /**
     * Turmas ativas do ano com a matriz curricular vinculada (null quando não há).
     *
     * @return list<array<string,mixed>>
     */
    public function turmasAtivasComMatriz(int $anoLetivo): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome, matriz_curricular_id FROM turmas WHERE ativo = 1 AND ano_letivo = :ano ORDER BY nome ASC',
            ['ano' => (int) $anoLetivo]
        ) ?: [];
    }

    /** Tabelas pesquisáveis por nome (nome da tabela nunca vem de entrada externa). */
    private const TABELAS_BUSCA = ['professores', 'materias', 'turmas'];

    /**
     * Busca ativos por nome: coincidência exata primeiro; se não houver, por trecho.
     *
     * @param 'professores'|'materias'|'turmas' $tabela
     * @return list<array{id:int, nome:string}>
     */
    public function buscarPorNome(string $tabela, string $termo, ?int $anoLetivo = null, int $limite = 6): array
    {
        if (!in_array($tabela, self::TABELAS_BUSCA, true)) {
            throw new \InvalidArgumentException('Tabela de busca inválida.');
        }
        $termo = trim($termo);
        if ($termo === '') {
            return [];
        }

        $filtroAno = $tabela === 'turmas' && $anoLetivo !== null ? ' AND ano_letivo = :ano' : '';
        $params = ['exato' => $termo];
        if ($filtroAno !== '') {
            $params['ano'] = (int) $anoLetivo;
        }
        $sqlBase = "SELECT id, nome FROM {$tabela} WHERE ativo = 1{$filtroAno}";
        $limite = max(1, min(20, $limite));

        $linhas = $this->db->fetchAll($sqlBase . ' AND nome = :exato ORDER BY nome ASC LIMIT ' . $limite, $params) ?: [];
        if ($linhas === []) {
            $params['trecho'] = '%' . addcslashes($termo, '%_\\') . '%';
            unset($params['exato']);
            $linhas = $this->db->fetchAll($sqlBase . ' AND nome LIKE :trecho ORDER BY nome ASC LIMIT ' . $limite, $params) ?: [];
        }

        $out = [];
        foreach ($linhas as $l) {
            $out[] = ['id' => (int) $l['id'], 'nome' => (string) $l['nome']];
        }
        return $out;
    }

    /**
     * Nome do registro ativo, ou null se não existir/estiver inativo.
     *
     * @param 'professores'|'materias'|'turmas' $tabela
     */
    public function nomeDoAtivo(string $tabela, int $id): ?string
    {
        if (!in_array($tabela, self::TABELAS_BUSCA, true) || $id <= 0) {
            return null;
        }
        $linha = $this->db->fetch("SELECT nome FROM {$tabela} WHERE id = :id AND ativo = 1", ['id' => $id]);
        return $linha ? (string) $linha['nome'] : null;
    }

    public function professorAtivo(int $professorId): bool
    {
        return (bool) $this->db->fetch(
            'SELECT id FROM professores WHERE id = :id AND ativo = 1',
            ['id' => (int) $professorId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function materiasAtivas(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome FROM materias WHERE ativo = 1 ORDER BY nome ASC'
        ) ?: [];
    }

    /**
     * Professores ativos com rótulo "Matéria — Nome" (e código se houver homônimo).
     *
     * @param list<int> $somenteIds se informado, restringe a estes professores
     * @return list<array{id:int,nome:string,materias:?string,materias_lista:list<string>,rotulo:string,codigo_prof?:string}>
     */
    public function professoresAtivos(array $somenteIds = []): array
    {
        $somenteIds = array_values(array_unique(array_filter(array_map('intval', $somenteIds))));
        $sql = 'SELECT id, nome, materias, codigo_prof, turmas FROM professores WHERE ativo = 1';
        $params = [];
        if ($somenteIds !== []) {
            $marcadores = [];
            foreach ($somenteIds as $i => $id) {
                $marcadores[] = ':p' . $i;
                $params['p' . $i] = $id;
            }
            $sql .= ' AND id IN (' . implode(', ', $marcadores) . ')';
        }
        $sql .= ' ORDER BY nome ASC';
        $rows = $this->db->fetchAll($sql, $params) ?: [];

        return $this->montarRotulosProfessores($rows);
    }

    /**
     * Professores vinculados às turmas do cenário (JSON turmas) e/ou já usados nas demandas.
     *
     * @param list<int> $turmaIds
     * @param list<int> $professorIdsExtra ids extras (ex.: demandas do cenário)
     * @return list<array{id:int,nome:string,materias:?string,materias_lista:list<string>,rotulo:string,codigo_prof?:string}>
     */
    public function professoresDasTurmas(array $turmaIds, array $professorIdsExtra = []): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        $extra = array_values(array_unique(array_filter(array_map('intval', $professorIdsExtra))));
        if ($turmaIds === [] && $extra === []) {
            return [];
        }

        $turmaSet = [];
        foreach ($turmaIds as $tid) {
            $turmaSet[$tid] = true;
        }

        $ids = [];
        foreach ($extra as $pid) {
            $ids[$pid] = true;
        }

        if ($turmaIds !== []) {
            $rows = $this->db->fetchAll(
                'SELECT id, turmas FROM professores WHERE ativo = 1'
            ) ?: [];
            foreach ($rows as $row) {
                $lista = json_decode((string) ($row['turmas'] ?? '[]'), true);
                if (!is_array($lista)) {
                    continue;
                }
                foreach ($lista as $item) {
                    $tid = (int) $item;
                    if ($tid > 0 && isset($turmaSet[$tid])) {
                        $ids[(int) $row['id']] = true;
                        break;
                    }
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        return $this->professoresAtivos(array_keys($ids));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{id:int,nome:string,materias:?string,materias_lista:list<string>,rotulo:string,codigo_prof?:string}>
     */
    private function montarRotulosProfessores(array $rows): array
    {
        $preparados = [];
        $contagemRotulo = [];
        foreach ($rows as $row) {
            $lista = json_decode((string) ($row['materias'] ?? '[]'), true);
            $materiasLista = [];
            if (is_array($lista)) {
                foreach ($lista as $item) {
                    $texto = trim((string) $item);
                    if ($texto !== '' && !ctype_digit($texto)) {
                        $materiasLista[] = $texto;
                    }
                }
            }
            $materiasLista = array_values(array_unique($materiasLista));
            $nome = trim((string) ($row['nome'] ?? ''));
            $base = $materiasLista !== []
                ? implode(', ', $materiasLista) . ' — ' . $nome
                : $nome;
            $chave = mb_strtolower($base);
            $contagemRotulo[$chave] = ($contagemRotulo[$chave] ?? 0) + 1;
            $preparados[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => $nome,
                'materias' => $row['materias'] ?? null,
                'materias_lista' => $materiasLista,
                'codigo_prof' => trim((string) ($row['codigo_prof'] ?? '')),
                'rotulo_base' => $base,
                'rotulo_chave' => $chave,
            ];
        }

        $out = [];
        foreach ($preparados as $p) {
            $rotulo = $p['rotulo_base'];
            if (($contagemRotulo[$p['rotulo_chave']] ?? 0) > 1 && $p['codigo_prof'] !== '') {
                $rotulo .= ' · ' . $p['codigo_prof'];
            }
            unset($p['rotulo_base'], $p['rotulo_chave']);
            $p['rotulo'] = $rotulo;
            $out[] = $p;
        }

        usort($out, static function (array $a, array $b): int {
            return strcmp(mb_strtolower($a['rotulo']), mb_strtolower($b['rotulo']));
        });

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function salasAtivas(): array
    {
        if (!$this->db->tableExists('school_locations')) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT id, nome FROM school_locations WHERE ativo = 1 AND tipo IN ('sala','laboratorio') ORDER BY nome ASC"
        ) ?: [];
    }
}
