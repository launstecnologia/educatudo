<?php

require_once __DIR__ . '/../Core/Database.php';

/**
 * EducaTudo - SchoolCalendarService
 * Calendário letivo: metas anuais (mínimo legal de 200 dias / 800 horas — LDB
 * art. 24) e cálculo de dias letivos a partir dos dias úteis do ano descontando
 * feriados/recessos/suspensões e somando reposições.
 */
class SchoolCalendarService
{
    /** @var Database */
    private $db;

    /** @var array<int,array{nao_letivos:array<string,true>,reposicoes:array<string,true>}> */
    private $mapaEfeitosCache = [];

    public const TIPOS_SISTEMA = [
        'feriado' => [
            'slug' => 'feriado', 'nome' => 'Feriado',
            'cor' => '#991b1b', 'cor_fundo' => '#fee2e2',
            'efeito' => 'nao_letivo', 'sistema' => 1, 'ordem' => 1,
        ],
        'recesso' => [
            'slug' => 'recesso', 'nome' => 'Recesso',
            'cor' => '#92400e', 'cor_fundo' => '#fef3c7',
            'efeito' => 'nao_letivo', 'sistema' => 1, 'ordem' => 2,
        ],
        'reposicao' => [
            'slug' => 'reposicao', 'nome' => 'Reposição',
            'cor' => '#166534', 'cor_fundo' => '#dcfce7',
            'efeito' => 'reposicao', 'sistema' => 1, 'ordem' => 3,
        ],
        'evento' => [
            'slug' => 'evento', 'nome' => 'Evento',
            'cor' => '#1e40af', 'cor_fundo' => '#dbeafe',
            'efeito' => 'neutro', 'sistema' => 1, 'ordem' => 4,
        ],
        'suspensao' => [
            'slug' => 'suspensao', 'nome' => 'Suspensão',
            'cor' => '#374151', 'cor_fundo' => '#f3f4f6',
            'efeito' => 'nao_letivo', 'sistema' => 1, 'ordem' => 5,
        ],
        'avaliacao' => [
            'slug' => 'avaliacao', 'nome' => 'Avaliação',
            'cor' => '#5b21b6', 'cor_fundo' => '#ede9fe',
            'efeito' => 'neutro', 'sistema' => 1, 'ordem' => 6,
        ],
    ];

    private const MAX_TIPOS_CUSTOM = 30;

    /** @var array<string,array<string,mixed>>|null */
    private $tiposCache = null;

    /** @var bool|null */
    private $tiposTabelaCache = null;

    /** @var array<string,bool> */
    private $colunasCache = [];

    /** @var array<string,bool> */
    private $tabelasCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function tableExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'calendario_letivo'");
            $cache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    public function tabelaTiposExiste(): bool
    {
        if ($this->tiposTabelaCache !== null) {
            return $this->tiposTabelaCache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'calendario_letivo_tipos'");
            $this->tiposTabelaCache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $this->tiposTabelaCache = false;
        }
        return $this->tiposTabelaCache;
    }

    /**
     * Mapa slug => tipo (sistema + personalizados), na ordem de exibição.
     *
     * @return array<string,array{id?:int,slug:string,nome:string,cor:string,cor_fundo:string,efeito:string,sistema:int,ordem:int}>
     */
    public function tipos(): array
    {
        if ($this->tiposCache !== null) {
            return $this->tiposCache;
        }
        if (!$this->tabelaTiposExiste()) {
            $this->tiposCache = self::TIPOS_SISTEMA;
            return $this->tiposCache;
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT id, slug, nome, cor, cor_fundo, efeito, sistema, ordem
                 FROM calendario_letivo_tipos
                 ORDER BY ordem ASC, id ASC"
            ) ?: [];
        } catch (Throwable $e) {
            $this->tiposCache = self::TIPOS_SISTEMA;
            return $this->tiposCache;
        }
        $out = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $cor = $this->normalizarHex((string) ($row['cor'] ?? ''));
            $fundo = $this->normalizarHex((string) ($row['cor_fundo'] ?? ''));
            if ($cor === '') {
                $cor = '#374151';
            }
            if ($fundo === '') {
                $fundo = $this->clarearHex($cor);
            }
            $out[$slug] = [
                'id' => (int) ($row['id'] ?? 0),
                'slug' => $slug,
                'nome' => (string) ($row['nome'] ?? $slug),
                'cor' => $cor,
                'cor_fundo' => $fundo,
                'efeito' => (string) ($row['efeito'] ?? 'neutro'),
                'sistema' => (int) ($row['sistema'] ?? 0),
                'ordem' => (int) ($row['ordem'] ?? 100),
            ];
        }
        foreach (self::TIPOS_SISTEMA as $slug => $def) {
            if (!isset($out[$slug])) {
                $out[$slug] = $def;
            }
        }
        $this->tiposCache = $out;
        return $out;
    }

    /**
     * @return array{labels:array<string,string>,bg:array<string,string>,text:array<string,string>,tipos:array<string,array<string,mixed>>}
     */
    public function visuaisTipos(): array
    {
        $tipos = $this->tipos();
        $labels = [];
        $bg = [];
        $text = [];
        foreach ($tipos as $slug => $t) {
            $labels[$slug] = (string) $t['nome'];
            $bg[$slug] = (string) $t['cor_fundo'];
            $text[$slug] = (string) $t['cor'];
        }
        return ['labels' => $labels, 'bg' => $bg, 'text' => $text, 'tipos' => $tipos];
    }

    /** @return list<string> */
    public function slugsComEfeito(string $efeito): array
    {
        $out = [];
        foreach ($this->tipos() as $slug => $t) {
            if (($t['efeito'] ?? '') === $efeito) {
                $out[] = $slug;
            }
        }
        return $out;
    }

    /**
     * @return array{ok:bool,erro?:string,tipo?:array<string,mixed>}
     */
    public function salvarTipo(string $nome, string $cor, string $efeito): array
    {
        if (!$this->tabelaTiposExiste()) {
            return ['ok' => false, 'erro' => 'Rode a migration 2026_09_01_calendario_letivo_tipos.sql no painel Master.'];
        }
        $nome = trim($nome);
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
            return ['ok' => false, 'erro' => 'Informe um nome com 2 a 80 caracteres.'];
        }
        if (preg_match('/[<>]/u', $nome)) {
            return ['ok' => false, 'erro' => 'O nome não pode conter os caracteres < ou >.'];
        }
        $cor = $this->normalizarHex($cor);
        if ($cor === '') {
            return ['ok' => false, 'erro' => 'Escolha uma cor válida.'];
        }
        $custom = 0;
        foreach ($this->tipos() as $t) {
            if ((int) ($t['sistema'] ?? 0) === 0) {
                $custom++;
            }
        }
        if ($custom >= self::MAX_TIPOS_CUSTOM) {
            return ['ok' => false, 'erro' => 'Limite de tipos personalizados atingido.'];
        }
        $slug = $this->slugificar($nome);
        $existentes = $this->tipos();
        $base = $slug;
        $n = 2;
        while (isset($existentes[$slug])) {
            $slug = $base . '_' . $n;
            $n++;
            if ($n > 50) {
                return ['ok' => false, 'erro' => 'Já existe um tipo com esse nome.'];
            }
        }
        $fundo = $this->clarearHex($cor);
        if (!in_array($efeito, ['neutro', 'nao_letivo', 'reposicao'], true)) {
            $efeito = 'neutro';
        }
        try {
            $id = (int) $this->db->insert(
                "INSERT INTO calendario_letivo_tipos (slug, nome, cor, cor_fundo, efeito, sistema, ordem)
                 VALUES (:slug, :nome, :cor, :fundo, :efeito, 0, :ordem)",
                [
                    'slug' => $slug,
                    'nome' => mb_substr($nome, 0, 80),
                    'cor' => $cor,
                    'fundo' => $fundo,
                    'efeito' => $efeito,
                    'ordem' => 100 + $custom,
                ]
            );
        } catch (Throwable $e) {
            error_log('Calendário letivo: falha ao salvar tipo: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível salvar o tipo.'];
        }
        if ($id <= 0) {
            return ['ok' => false, 'erro' => 'Não foi possível salvar o tipo.'];
        }
        $this->tiposCache = null;
        return [
            'ok' => true,
            'tipo' => [
                'id' => $id,
                'slug' => $slug,
                'nome' => mb_substr($nome, 0, 80),
                'cor' => $cor,
                'cor_fundo' => $fundo,
                'efeito' => $efeito,
                'sistema' => 0,
                'ordem' => 100 + $custom,
            ],
        ];
    }

    /**
     * @return array{ok:bool,erro?:string}
     */
    public function excluirTipo(string $slug): array
    {
        if (!$this->tabelaTiposExiste()) {
            return ['ok' => false, 'erro' => 'Cadastro de tipos ainda não está disponível.'];
        }
        $slug = trim($slug);
        $tipos = $this->tipos();
        if (!isset($tipos[$slug])) {
            return ['ok' => false, 'erro' => 'Tipo não encontrado.'];
        }
        if ((int) ($tipos[$slug]['sistema'] ?? 0) === 1) {
            return ['ok' => false, 'erro' => 'Tipos padrão não podem ser removidos.'];
        }
        $uso = $this->db->fetch(
            "SELECT COUNT(*) AS n FROM calendario_letivo_eventos WHERE tipo = :slug",
            ['slug' => $slug]
        );
        if ((int) ($uso['n'] ?? 0) > 0) {
            return ['ok' => false, 'erro' => 'Há eventos usando este tipo. Remova-os antes.'];
        }
        try {
            $this->db->query("DELETE FROM calendario_letivo_tipos WHERE slug = :slug AND sistema = 0", ['slug' => $slug]);
        } catch (Throwable $e) {
            error_log('Calendário letivo: falha ao excluir tipo: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível remover o tipo.'];
        }
        $this->tiposCache = null;
        return ['ok' => true];
    }

    /**
     * Altera o efeito de um tipo criado pela escola.
     * reposicao faz sábado e domingo daquele tipo entrarem nos dias letivos.
     *
     * @return array{ok:bool,erro?:string,efeito?:string}
     */
    public function atualizarEfeitoTipo(string $slug, string $efeito): array
    {
        if (!$this->tabelaTiposExiste()) {
            return ['ok' => false, 'erro' => 'Cadastro de tipos ainda não está disponível.'];
        }
        if (!in_array($efeito, ['neutro', 'nao_letivo', 'reposicao'], true)) {
            return ['ok' => false, 'erro' => 'Efeito inválido.'];
        }
        $slug = trim($slug);
        $tipos = $this->tipos();
        if (!isset($tipos[$slug])) {
            return ['ok' => false, 'erro' => 'Tipo não encontrado.'];
        }
        if ((int) ($tipos[$slug]['sistema'] ?? 0) === 1) {
            return ['ok' => false, 'erro' => 'Tipos padrão não podem ser alterados.'];
        }
        try {
            $this->db->query(
                "UPDATE calendario_letivo_tipos SET efeito = :efeito WHERE slug = :slug AND sistema = 0",
                ['efeito' => $efeito, 'slug' => $slug]
            );
        } catch (Throwable $e) {
            error_log('Calendário letivo: falha ao atualizar efeito do tipo: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível atualizar o tipo.'];
        }
        $this->tiposCache = null;
        $this->mapaEfeitosCache = [];
        return ['ok' => true, 'efeito' => $efeito];
    }

    public function tipoValido(string $tipo): bool
    {
        return isset($this->tipos()[$tipo]);
    }

    public function variosDisponivel(): bool
    {
        return $this->tableExists()
            && $this->colunaExiste('calendario_letivo', 'nome')
            && $this->tabelaExiste('calendario_letivo_vinculos');
    }

    /** @return array<string,mixed>|null */
    public function getAno(int $ano): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        if ($this->variosDisponivel()) {
            $geral = $this->db->fetch(
                "SELECT c.* FROM calendario_letivo c
                 WHERE c.ano = :ano
                   AND NOT EXISTS (
                       SELECT 1 FROM calendario_letivo_vinculos v WHERE v.calendario_id = c.id
                   )
                 ORDER BY c.id ASC
                 LIMIT 1",
                ['ano' => $ano]
            );
            return $geral ?: null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM calendario_letivo WHERE ano = :ano ORDER BY id ASC LIMIT 1",
            ['ano' => $ano]
        );
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function getPorId(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists()) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM calendario_letivo WHERE id = :id LIMIT 1", ['id' => $id]);
        if (!$row) {
            return null;
        }
        return $this->comAbrangencia($row);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarDoAno(int $ano): array
    {
        if (!$this->tableExists() || $ano <= 0) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT * FROM calendario_letivo WHERE ano = :ano ORDER BY id ASC",
            ['ano' => $ano]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->comAbrangencia($row);
        }
        return $out;
    }

    /**
     * Calendário da série, senão do curso, senão o geral da escola.
     *
     * @return array<string,mixed>|null
     */
    public function resolver(int $ano, int $serieId, int $cursoId): ?array
    {
        $lista = $this->listarDoAno($ano);
        if ($lista === []) {
            return null;
        }
        $porSerie = null;
        $porCurso = null;
        $geral = null;
        foreach ($lista as $cal) {
            $cursos = $cal['curso_ids'] ?? [];
            $series = $cal['serie_ids'] ?? [];
            if ($cursos === [] && $series === []) {
                if ($geral === null) {
                    $geral = $cal;
                }
                continue;
            }
            if ($serieId > 0 && in_array($serieId, $series, true) && $porSerie === null) {
                $porSerie = $cal;
            }
            if ($cursoId > 0 && in_array($cursoId, $cursos, true) && $porCurso === null) {
                $porCurso = $cal;
            }
        }
        return $porSerie ?? $porCurso ?? $geral;
    }

    /**
     * @return array{serie_id:int,curso_id:int}
     */
    public function escopoDaTurma(int $turmaId): array
    {
        $vazio = ['serie_id' => 0, 'curso_id' => 0];
        if ($turmaId <= 0) {
            return $vazio;
        }
        try {
            $row = $this->db->fetch(
                "SELECT t.serie_id, t.curso_novo_id, s.curso_id AS serie_curso_id
                 FROM turmas t
                 LEFT JOIN serie s ON s.id = t.serie_id
                 WHERE t.id = :id
                 LIMIT 1",
                ['id' => $turmaId]
            );
        } catch (Throwable $e) {
            try {
                $row = $this->db->fetch(
                    "SELECT serie_id, curso_novo_id FROM turmas WHERE id = :id LIMIT 1",
                    ['id' => $turmaId]
                );
            } catch (Throwable $e2) {
                return $vazio;
            }
        }
        if (!$row) {
            return $vazio;
        }
        $serieId = (int) ($row['serie_id'] ?? 0);
        $cursoId = (int) ($row['curso_novo_id'] ?? 0);
        if ($cursoId <= 0) {
            $cursoId = (int) ($row['serie_curso_id'] ?? 0);
        }
        return ['serie_id' => $serieId, 'curso_id' => $cursoId];
    }

    /**
     * @return list<array{serie_id:int,curso_id:int}>
     */
    public function escoposDoProfessor(int $professorId): array
    {
        if ($professorId <= 0) {
            return [];
        }
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT t.serie_id, COALESCE(NULLIF(t.curso_novo_id, 0), s.curso_id) AS curso_id
                 FROM grade_horaria gh
                 INNER JOIN turmas t ON t.id = gh.turma_id
                 LEFT JOIN serie s ON s.id = t.serie_id
                 WHERE gh.professor_id = :id",
                ['id' => $professorId]
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $serieId = (int) ($row['serie_id'] ?? 0);
            $cursoId = (int) ($row['curso_id'] ?? 0);
            if ($serieId <= 0 && $cursoId <= 0) {
                continue;
            }
            $out[] = ['serie_id' => $serieId, 'curso_id' => $cursoId];
        }
        return $out;
    }

    /**
     * @param list<array{serie_id:int,curso_id:int}> $escopos
     * @return array<string,mixed>|null
     */
    public function resolverEscopos(int $ano, array $escopos): ?array
    {
        foreach ($escopos as $escopo) {
            $cal = $this->resolver($ano, (int) ($escopo['serie_id'] ?? 0), (int) ($escopo['curso_id'] ?? 0));
            if ($cal && (($cal['serie_ids'] ?? []) !== [] || ($cal['curso_ids'] ?? []) !== [])) {
                return $cal;
            }
        }
        return $this->resolver($ano, 0, 0);
    }

    /**
     * Calendário comum às séries. Se cada série cair em um calendário diferente, fica o geral.
     *
     * @param list<int> $serieIds
     * @return array<string,mixed>|null
     */
    public function resolverParaSeries(int $ano, array $serieIds): ?array
    {
        $serieIds = $this->idsPositivos($serieIds);
        if ($serieIds === []) {
            return $this->getAno($ano);
        }
        $escolhido = null;
        foreach ($serieIds as $serieId) {
            $cal = $this->resolver($ano, $serieId, $this->cursoDaSerie($serieId));
            if (!$cal) {
                return null;
            }
            if ($escolhido !== null && (int) $escolhido['id'] !== (int) $cal['id']) {
                return $this->getAno($ano);
            }
            $escolhido = $cal;
        }
        return $escolhido ?? $this->getAno($ano);
    }

    private function cursoDaSerie(int $serieId): int
    {
        if ($serieId <= 0 || !$this->tabelaExiste('serie')) {
            return 0;
        }
        try {
            $row = $this->db->fetch(
                "SELECT curso_id FROM serie WHERE id = :id LIMIT 1",
                ['id' => $serieId]
            );
        } catch (Throwable $e) {
            return 0;
        }
        return (int) ($row['curso_id'] ?? 0);
    }

    /**
     * Cursos ativos com as séries de cada um, para o cadastro do calendário.
     *
     * @return list<array{id:int,nome:string,series:list<array{id:int,nome:string}>}>
     */
    public function cursosComSeries(): array
    {
        if (!$this->tabelaExiste('curso') || !$this->tabelaExiste('serie')) {
            return [];
        }
        try {
            $cursos = $this->db->fetchAll(
                "SELECT id, nome FROM curso WHERE ativo = 1 ORDER BY ordem ASC, nome ASC"
            ) ?: [];
            $series = $this->db->fetchAll(
                "SELECT id, curso_id, nome FROM serie WHERE ativo = 1 ORDER BY ordem ASC, nome ASC"
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
        $porCurso = [];
        foreach ($series as $serie) {
            $cursoId = (int) ($serie['curso_id'] ?? 0);
            $porCurso[$cursoId][] = [
                'id' => (int) ($serie['id'] ?? 0),
                'nome' => (string) ($serie['nome'] ?? ''),
            ];
        }
        $out = [];
        foreach ($cursos as $curso) {
            $id = (int) ($curso['id'] ?? 0);
            $out[] = [
                'id' => $id,
                'nome' => (string) ($curso['nome'] ?? ''),
                'series' => $porCurso[$id] ?? [],
            ];
        }
        return $out;
    }

    /**
     * @param list<int> $cursoIds
     * @param list<int> $serieIds
     * @return array{ok:bool,erro?:string,id?:int}
     */
    public function salvarCalendario(
        int $id,
        int $ano,
        string $nome,
        int $diasMeta,
        int $cargaMeta,
        string $obs,
        array $cursoIds,
        array $serieIds
    ): array {
        if (!$this->variosDisponivel() || $ano <= 0) {
            return ['ok' => false, 'erro' => 'Rode a migration 2026_10_01_calendario_letivo_varios.sql no painel Master.'];
        }
        $nome = trim($nome);
        if ($nome === '' || mb_strlen($nome) > 120) {
            return ['ok' => false, 'erro' => 'Informe o nome do calendário (até 120 caracteres).'];
        }
        $cursoIds = $this->idsPositivos($cursoIds);
        $serieIds = $this->idsPositivos($serieIds);
        $serieIds = $this->seriesForaDosCursos($serieIds, $cursoIds);
        $conflito = $this->vinculoOcupado($ano, $id, $cursoIds, $serieIds);
        if ($conflito !== '') {
            return ['ok' => false, 'erro' => $conflito];
        }
        $dup = $this->db->fetch(
            "SELECT id FROM calendario_letivo WHERE ano = :ano AND nome = :nome AND id <> :id LIMIT 1",
            ['ano' => $ano, 'nome' => $nome, 'id' => $id]
        );
        if ($dup) {
            return ['ok' => false, 'erro' => 'Já existe um calendário com esse nome em ' . $ano . '.'];
        }
        $obsValor = $obs !== '' ? mb_substr($obs, 0, 255) : null;
        if ($id > 0) {
            $atual = $this->db->fetch(
                "SELECT id FROM calendario_letivo WHERE id = :id AND ano = :ano LIMIT 1",
                ['id' => $id, 'ano' => $ano]
            );
            if (!$atual) {
                return ['ok' => false, 'erro' => 'Calendário não encontrado neste ano.'];
            }
            $this->db->update(
                "UPDATE calendario_letivo
                 SET nome = :nome, dias_meta = :d, carga_horaria_meta = :c, observacao = :o, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id",
                ['nome' => $nome, 'd' => $diasMeta, 'c' => $cargaMeta, 'o' => $obsValor, 'id' => $id]
            );
        } else {
            $id = (int) $this->db->insert(
                "INSERT INTO calendario_letivo (ano, nome, dias_meta, carga_horaria_meta, observacao)
                 VALUES (:ano, :nome, :d, :c, :o)",
                ['ano' => $ano, 'nome' => $nome, 'd' => $diasMeta, 'c' => $cargaMeta, 'o' => $obsValor]
            );
        }
        if ($id <= 0) {
            return ['ok' => false, 'erro' => 'Não foi possível salvar o calendário.'];
        }
        $this->db->query("DELETE FROM calendario_letivo_vinculos WHERE calendario_id = :id", ['id' => $id]);
        foreach ($cursoIds as $cursoId) {
            $this->db->insert(
                "INSERT INTO calendario_letivo_vinculos (calendario_id, curso_id, serie_id) VALUES (:c, :curso, NULL)",
                ['c' => $id, 'curso' => $cursoId]
            );
        }
        foreach ($serieIds as $serieId) {
            $this->db->insert(
                "INSERT INTO calendario_letivo_vinculos (calendario_id, curso_id, serie_id) VALUES (:c, NULL, :serie)",
                ['c' => $id, 'serie' => $serieId]
            );
        }
        return ['ok' => true, 'id' => $id];
    }

    public function excluirCalendario(int $id): bool
    {
        if ($id <= 0 || !$this->tableExists()) {
            return false;
        }
        $this->db->query("DELETE FROM calendario_letivo WHERE id = :id", ['id' => $id]);
        return true;
    }

    public function salvarAno(int $ano, int $diasMeta, int $cargaMeta, string $obs = ''): int
    {
        if (!$this->tableExists() || $ano <= 0) {
            return 0;
        }
        $existente = $this->getAno($ano);
        if ($existente) {
            $this->db->update(
                "UPDATE calendario_letivo SET dias_meta = :d, carga_horaria_meta = :c, observacao = :o, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
                ['d' => $diasMeta, 'c' => $cargaMeta, 'o' => $obs !== '' ? $obs : null, 'id' => (int) $existente['id']]
            );
            return (int) $existente['id'];
        }
        if ($this->colunaExiste('calendario_letivo', 'nome')) {
            return (int) $this->db->insert(
                "INSERT INTO calendario_letivo (ano, nome, dias_meta, carga_horaria_meta, observacao) VALUES (:ano, 'Geral', :d, :c, :o)",
                ['ano' => $ano, 'd' => $diasMeta, 'c' => $cargaMeta, 'o' => $obs !== '' ? $obs : null]
            );
        }
        return (int) $this->db->insert(
            "INSERT INTO calendario_letivo (ano, dias_meta, carga_horaria_meta, observacao) VALUES (:ano, :d, :c, :o)",
            ['ano' => $ano, 'd' => $diasMeta, 'c' => $cargaMeta, 'o' => $obs !== '' ? $obs : null]
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function comAbrangencia(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $cursoIds = [];
        $serieIds = [];
        if ($id > 0 && $this->variosDisponivel()) {
            $vinculos = $this->db->fetchAll(
                "SELECT curso_id, serie_id FROM calendario_letivo_vinculos WHERE calendario_id = :id",
                ['id' => $id]
            ) ?: [];
            foreach ($vinculos as $vinculo) {
                $serieId = (int) ($vinculo['serie_id'] ?? 0);
                $cursoId = (int) ($vinculo['curso_id'] ?? 0);
                if ($serieId > 0) {
                    $serieIds[] = $serieId;
                } elseif ($cursoId > 0) {
                    $cursoIds[] = $cursoId;
                }
            }
        }
        $row['curso_ids'] = array_values(array_unique($cursoIds));
        $row['serie_ids'] = array_values(array_unique($serieIds));
        $row['nome'] = trim((string) ($row['nome'] ?? 'Geral'));
        if ($row['nome'] === '') {
            $row['nome'] = 'Geral';
        }
        $row['rotulo'] = $this->rotuloAbrangencia($row['curso_ids'], $row['serie_ids']);
        return $row;
    }

    /**
     * @param list<int> $cursoIds
     * @param list<int> $serieIds
     */
    private function rotuloAbrangencia(array $cursoIds, array $serieIds): string
    {
        if ($cursoIds === [] && $serieIds === []) {
            return 'Toda a escola';
        }
        $nomes = [];
        if ($cursoIds !== [] && $this->tabelaExiste('curso')) {
            [$in, $params] = $this->inNomeado('curso', $cursoIds);
            try {
                $rows = $this->db->fetchAll(
                    "SELECT nome FROM curso WHERE id IN ($in) ORDER BY ordem ASC, nome ASC",
                    $params
                ) ?: [];
                foreach ($rows as $row) {
                    $nomes[] = (string) ($row['nome'] ?? '');
                }
            } catch (Throwable $e) {
                $nomes = [];
            }
        }
        if ($serieIds !== [] && $this->tabelaExiste('serie')) {
            [$in, $params] = $this->inNomeado('serie', $serieIds);
            try {
                $rows = $this->db->fetchAll(
                    "SELECT nome FROM serie WHERE id IN ($in) ORDER BY ordem ASC, nome ASC",
                    $params
                ) ?: [];
                foreach ($rows as $row) {
                    $nomes[] = (string) ($row['nome'] ?? '');
                }
            } catch (Throwable $e) {
                // série indisponível
            }
        }
        $nomes = array_values(array_filter($nomes, static fn ($n) => $n !== ''));
        return $nomes === [] ? 'Toda a escola' : implode(' · ', $nomes);
    }

    /**
     * @param list<int|string> $ids
     * @return list<int>
     */
    private function idsPositivos(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }

    /**
     * Série ou curso já ligado a outro calendário do mesmo ano.
     *
     * @param list<int> $cursoIds
     * @param list<int> $serieIds
     */
    private function vinculoOcupado(int $ano, int $calendarioId, array $cursoIds, array $serieIds): string
    {
        $temSerie = $this->tabelaExiste('serie');
        foreach ($cursoIds as $cursoId) {
            $row = $this->db->fetch(
                "SELECT c.nome
                 FROM calendario_letivo_vinculos v
                 INNER JOIN calendario_letivo c ON c.id = v.calendario_id
                 WHERE c.ano = :ano AND c.id <> :id AND v.curso_id = :curso AND v.serie_id IS NULL
                 LIMIT 1",
                ['ano' => $ano, 'id' => $calendarioId, 'curso' => $cursoId]
            );
            if ($row) {
                return 'Esse curso já está no calendário ' . (string) ($row['nome'] ?? '') . '.';
            }
            if (!$temSerie) {
                continue;
            }
            $serieOcupada = $this->db->fetch(
                "SELECT c.nome
                 FROM calendario_letivo_vinculos v
                 INNER JOIN calendario_letivo c ON c.id = v.calendario_id
                 INNER JOIN serie s ON s.id = v.serie_id
                 WHERE c.ano = :ano AND c.id <> :id AND s.curso_id = :curso
                 LIMIT 1",
                ['ano' => $ano, 'id' => $calendarioId, 'curso' => $cursoId]
            );
            if ($serieOcupada) {
                return 'Uma série desse curso já está no calendário ' . (string) ($serieOcupada['nome'] ?? '') . '.';
            }
        }
        foreach ($serieIds as $serieId) {
            $row = $this->db->fetch(
                "SELECT c.nome
                 FROM calendario_letivo_vinculos v
                 INNER JOIN calendario_letivo c ON c.id = v.calendario_id
                 WHERE c.ano = :ano AND c.id <> :id AND v.serie_id = :serie
                 LIMIT 1",
                ['ano' => $ano, 'id' => $calendarioId, 'serie' => $serieId]
            );
            if ($row) {
                return 'Essa série já está no calendário ' . (string) ($row['nome'] ?? '') . '.';
            }
            if (!$temSerie) {
                continue;
            }
            $cursoOcupado = $this->db->fetch(
                "SELECT c.nome
                 FROM calendario_letivo_vinculos v
                 INNER JOIN calendario_letivo c ON c.id = v.calendario_id
                 INNER JOIN serie s ON s.curso_id = v.curso_id AND s.id = :serie
                 WHERE c.ano = :ano AND c.id <> :id AND v.serie_id IS NULL
                 LIMIT 1",
                ['ano' => $ano, 'id' => $calendarioId, 'serie' => $serieId]
            );
            if ($cursoOcupado) {
                return 'O curso dessa série já está no calendário ' . (string) ($cursoOcupado['nome'] ?? '') . '.';
            }
        }
        return '';
    }

    /**
     * Série já coberta pelo curso inteiro não precisa de vínculo próprio.
     *
     * @param list<int> $serieIds
     * @param list<int> $cursoIds
     * @return list<int>
     */
    private function seriesForaDosCursos(array $serieIds, array $cursoIds): array
    {
        if ($serieIds === [] || $cursoIds === [] || !$this->tabelaExiste('serie')) {
            return $serieIds;
        }
        [$in, $params] = $this->inNomeado('serie', $serieIds);
        try {
            $rows = $this->db->fetchAll(
                "SELECT id, curso_id FROM serie WHERE id IN ($in)",
                $params
            ) ?: [];
        } catch (Throwable $e) {
            return $serieIds;
        }
        $cursoSet = array_fill_keys($cursoIds, true);
        $out = [];
        foreach ($rows as $row) {
            $serieId = (int) ($row['id'] ?? 0);
            $cursoId = (int) ($row['curso_id'] ?? 0);
            if ($serieId > 0 && !isset($cursoSet[$cursoId])) {
                $out[] = $serieId;
            }
        }
        return $out;
    }

    /**
     * @param list<int> $ids
     * @return array{0:string,1:array<string,int>}
     */
    private function inNomeado(string $prefixo, array $ids): array
    {
        $trechos = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $chave = $prefixo . $i;
            $trechos[] = ':' . $chave;
            $params[$chave] = (int) $id;
        }
        return [implode(', ', $trechos), $params];
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        $chave = $tabela . '.' . $coluna;
        if (array_key_exists($chave, $this->colunasCache)) {
            return $this->colunasCache[$chave];
        }
        try {
            $row = $this->db->fetch(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = :coluna
                 LIMIT 1",
                ['tabela' => $tabela, 'coluna' => $coluna]
            );
            $this->colunasCache[$chave] = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $this->colunasCache[$chave] = false;
        }
        return $this->colunasCache[$chave];
    }

    private function tabelaExiste(string $tabela): bool
    {
        if (array_key_exists($tabela, $this->tabelasCache)) {
            return $this->tabelasCache[$tabela];
        }
        try {
            $row = $this->db->fetch(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela
                 LIMIT 1",
                ['tabela' => $tabela]
            );
            $this->tabelasCache[$tabela] = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $this->tabelasCache[$tabela] = false;
        }
        return $this->tabelasCache[$tabela];
    }

    /** @return list<array<string,mixed>> */
    public function eventos(int $calendarioId): array
    {
        if ($calendarioId <= 0 || !$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM calendario_letivo_eventos WHERE calendario_id = :id ORDER BY data_inicio ASC",
            ['id' => $calendarioId]
        ) ?: [];
    }

    public function salvarEvento(
        int $calendarioId,
        string $inicio,
        string $fim,
        string $tipo,
        string $descricao,
        string $linkReuniao = '',
        string $localEvento = '',
        int $visivelAluno = 0,
        int $visivelProfessor = 0,
        int $visivelPais = 0
    ): void {
        if ($calendarioId <= 0 || !$this->tableExists()) {
            return;
        }
        if (!$this->tipoValido($tipo)) {
            $tipo = 'feriado';
        }
        if ($fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        $this->db->insert(
            "INSERT INTO calendario_letivo_eventos
                (calendario_id, data_inicio, data_fim, tipo, descricao, link_reuniao, local_evento, visivel_aluno, visivel_professor, visivel_pais)
             VALUES (:c, :i, :f, :t, :d, :lr, :le, :va, :vp, :vpais)",
            [
                'c'     => $calendarioId,
                'i'     => $inicio,
                'f'     => $fim,
                't'     => $tipo,
                'd'     => mb_substr($descricao, 0, 255),
                'lr'    => $linkReuniao !== '' ? mb_substr($linkReuniao, 0, 500) : null,
                'le'    => $localEvento !== '' ? mb_substr($localEvento, 0, 255) : null,
                'va'    => $visivelAluno ? 1 : 0,
                'vp'    => $visivelProfessor ? 1 : 0,
                'vpais' => $visivelPais ? 1 : 0,
            ]
        );
    }

    public function atualizarEvento(
        int $eventoId,
        int $calendarioId,
        string $inicio,
        string $fim,
        string $tipo,
        string $descricao,
        string $linkReuniao = '',
        string $localEvento = '',
        int $visivelAluno = 0,
        int $visivelProfessor = 0,
        int $visivelPais = 0
    ): bool {
        if ($eventoId <= 0 || $calendarioId <= 0 || !$this->tableExists()) {
            return false;
        }
        $existente = $this->db->fetch(
            "SELECT id FROM calendario_letivo_eventos WHERE id = :id AND calendario_id = :c LIMIT 1",
            ['id' => $eventoId, 'c' => $calendarioId]
        );
        if (!$existente) {
            return false;
        }
        if (!$this->tipoValido($tipo)) {
            $tipo = 'feriado';
        }
        if ($fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }
        $this->db->update(
            "UPDATE calendario_letivo_eventos
                SET data_inicio = :i, data_fim = :f, tipo = :t, descricao = :d,
                    link_reuniao = :lr, local_evento = :le,
                    visivel_aluno = :va, visivel_professor = :vp, visivel_pais = :vpais
              WHERE id = :id AND calendario_id = :c",
            [
                'id'    => $eventoId,
                'c'     => $calendarioId,
                'i'     => $inicio,
                'f'     => $fim,
                't'     => $tipo,
                'd'     => mb_substr($descricao, 0, 255),
                'lr'    => $linkReuniao !== '' ? mb_substr($linkReuniao, 0, 500) : null,
                'le'    => $localEvento !== '' ? mb_substr($localEvento, 0, 255) : null,
                'va'    => $visivelAluno ? 1 : 0,
                'vp'    => $visivelProfessor ? 1 : 0,
                'vpais' => $visivelPais ? 1 : 0,
            ]
        );
        return true;
    }

    public function excluirEvento(int $eventoId): void
    {
        if ($eventoId <= 0 || !$this->tableExists()) {
            return;
        }
        $this->db->query("DELETE FROM calendario_letivo_eventos WHERE id = :id", ['id' => $eventoId]);
    }

    public function tabelaEscolarExiste(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'school_calendar_events'");
            $cache = $row !== false && !empty($row);
        } catch (Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    /**
     * Replica o evento letivo no calendário escolar (app da família) e notifica os responsáveis.
     *
     * @return int ID do evento escolar criado, ou 0 se não foi possível publicar
     */
    public function publicarNoCalendarioEscolar(
        string $titulo,
        string $inicio,
        string $fim,
        string $tipo,
        string $local = '',
        int $autorId = 0
    ): int {
        if (!$this->tabelaEscolarExiste() || $titulo === '' || $inicio === '') {
            return 0;
        }
        $mapaCategoria = [
            'feriado'   => 'feriado',
            'avaliacao' => 'prova',
            'recesso'   => 'evento',
            'reposicao' => 'evento',
            'evento'    => 'evento',
            'suspensao' => 'evento',
        ];
        $categoria = $mapaCategoria[$tipo] ?? 'evento';
        $prioridade = in_array($tipo, ['feriado', 'suspensao'], true) ? 'importante' : 'normal';
        if ($fim === '' || $fim < $inicio) {
            $fim = $inicio;
        }
        $inicioEm = $inicio . ' 00:00:00';
        $fimEm = $fim . ' 23:59:59';
        try {
            $id = (int) $this->db->insert(
                "INSERT INTO school_calendar_events
                    (titulo, descricao, categoria, prioridade, local, inicio_em, fim_em, dia_inteiro, publico, status, criado_por, published_at)
                 VALUES (:title, :description, :category, :priority, :location, :starts, :ends, 1, 'todos', 'publicado', :author, NOW())",
                [
                    'title'       => mb_substr($titulo, 0, 255),
                    'description' => $titulo,
                    'category'    => $categoria,
                    'priority'    => $prioridade,
                    'location'    => $local !== '' ? mb_substr($local, 0, 255) : null,
                    'starts'      => $inicioEm,
                    'ends'        => $fimEm,
                    'author'      => max(0, $autorId),
                ]
            );
        } catch (Throwable $e) {
            error_log('Calendário letivo: falha ao publicar no calendário escolar: ' . $e->getMessage());
            return 0;
        }
        if ($id <= 0) {
            return 0;
        }
        $this->notificarResponsaveisCalendarioEscolar($id, $titulo, $inicio, $autorId);
        return $id;
    }

    private function notificarResponsaveisCalendarioEscolar(int $eventoId, string $titulo, string $inicio, int $autorId): void
    {
        try {
            require_once __DIR__ . '/SchoolCommunicationService.php';
            $comunicacao = new SchoolCommunicationService($this->db);
            $pais = $comunicacao->parentIds('todos');
            if ($pais === []) {
                return;
            }
            $quando = DateTime::createFromFormat('Y-m-d', $inicio);
            $dataFmt = $quando ? $quando->format('d/m/Y') : $inicio;
            $comunicacao->push(
                $pais,
                'Novo evento: ' . $titulo,
                $dataFmt,
                '/calendar-events/' . $eventoId,
                ['type' => 'calendar_event', 'event_id' => (string) $eventoId],
                $autorId
            );
        } catch (Throwable $e) {
            error_log('Calendário letivo: falha ao notificar responsáveis do evento escolar #' . $eventoId . ': ' . $e->getMessage());
        }
    }

    /**
     * Situação do ano vigente (ou null se não configurado).
     *
     * @return array{ano:int,dias_meta:int,carga_meta:int,dias_letivos:int,percentual:?float}|null
     */
    public function statusAnoVigente(): ?array
    {
        $ano = (int) date('Y');
        $cfg = $this->getAno($ano);
        if (!$cfg) {
            return null;
        }
        return $this->status((int) $cfg['id'], $ano, (int) $cfg['dias_meta'], (int) $cfg['carga_horaria_meta']);
    }

    /**
     * @return array{ano:int,dias_meta:int,carga_meta:int,dias_letivos:int,percentual:?float}
     */
    public function status(int $calendarioId, int $ano, int $diasMeta, int $cargaMeta): array
    {
        $dias = $this->diasLetivosCalculados($ano, $this->eventos($calendarioId));
        return [
            'ano' => $ano,
            'dias_meta' => $diasMeta,
            'carga_meta' => $cargaMeta,
            'dias_letivos' => $dias,
            'percentual' => $diasMeta > 0 ? min(100.0, round(($dias / $diasMeta) * 100, 1)) : null,
        ];
    }

    /**
     * Dias letivos = dias úteis (seg–sex) do ano − feriados/recessos/suspensões em
     * dias úteis + reposições em fins de semana.
     *
     * @param list<array<string,mixed>> $eventos
     */
    public function diasLetivosCalculados(int $ano, array $eventos): int
    {
        $inicio = new DateTime($ano . '-01-01');
        $fim = new DateTime($ano . '-12-31');

        $uteis = 0;
        for ($d = clone $inicio; $d <= $fim; $d->modify('+1 day')) {
            $n = (int) $d->format('N');
            if ($n <= 5) {
                $uteis++;
            }
        }

        $mapa = $this->tipos();
        $naoLetivos = [];
        $reposicoes = [];
        foreach ($eventos as $ev) {
            $tipo = (string) ($ev['tipo'] ?? '');
            $efeito = (string) ($mapa[$tipo]['efeito'] ?? '');
            if ($efeito === '' && in_array($tipo, ['feriado', 'recesso', 'suspensao'], true)) {
                $efeito = 'nao_letivo';
            } elseif ($efeito === '' && $tipo === 'reposicao') {
                $efeito = 'reposicao';
            }
            try {
                $ini = new DateTime((string) $ev['data_inicio']);
                $f = new DateTime((string) $ev['data_fim']);
            } catch (Throwable $e) {
                continue;
            }
            if ($f < $ini) {
                continue;
            }
            for ($d = clone $ini; $d <= $f; $d->modify('+1 day')) {
                if ((int) $d->format('Y') !== $ano) {
                    continue;
                }
                $key = $d->format('Y-m-d');
                $n = (int) $d->format('N');
                if ($efeito === 'nao_letivo' && $n <= 5) {
                    $naoLetivos[$key] = true;
                } elseif ($efeito === 'reposicao' && $n >= 6) {
                    $reposicoes[$key] = true;
                }
            }
        }

        $total = $uteis - count($naoLetivos) + count($reposicoes);
        return max(0, $total);
    }

    /**
     * @return array{nao_letivos:array<string,true>,reposicoes:array<string,true>}
     */
    public function mapaEfeitosDoAno(int $ano): array
    {
        if (isset($this->mapaEfeitosCache[$ano])) {
            return $this->mapaEfeitosCache[$ano];
        }
        $cal = $this->getAno($ano);
        $eventos = is_array($cal) ? $this->eventos((int) ($cal['id'] ?? 0)) : [];
        $mapa = $this->tipos();
        $naoLetivos = [];
        $reposicoes = [];
        foreach ($eventos as $ev) {
            $tipo = (string) ($ev['tipo'] ?? '');
            $efeito = (string) ($mapa[$tipo]['efeito'] ?? '');
            if ($efeito === '' && in_array($tipo, ['feriado', 'recesso', 'suspensao'], true)) {
                $efeito = 'nao_letivo';
            } elseif ($efeito === '' && $tipo === 'reposicao') {
                $efeito = 'reposicao';
            }
            try {
                $ini = new DateTime((string) $ev['data_inicio']);
                $f = new DateTime((string) $ev['data_fim']);
            } catch (Throwable $e) {
                continue;
            }
            if ($f < $ini) {
                continue;
            }
            for ($d = clone $ini; $d <= $f; $d->modify('+1 day')) {
                if ((int) $d->format('Y') !== $ano) {
                    continue;
                }
                $key = $d->format('Y-m-d');
                if ($efeito === 'nao_letivo') {
                    $naoLetivos[$key] = true;
                } elseif ($efeito === 'reposicao') {
                    $reposicoes[$key] = true;
                }
            }
        }
        $this->mapaEfeitosCache[$ano] = ['nao_letivos' => $naoLetivos, 'reposicoes' => $reposicoes];
        return $this->mapaEfeitosCache[$ano];
    }

    public function ehDiaLetivo(string $ymd, int $ano): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return false;
        }
        try {
            $d = new DateTime($ymd);
        } catch (Throwable $e) {
            return false;
        }
        $mapa = $this->mapaEfeitosDoAno($ano);
        $n = (int) $d->format('N');
        if ($n <= 5) {
            return empty($mapa['nao_letivos'][$ymd]);
        }
        return !empty($mapa['reposicoes'][$ymd]);
    }

    public function proximoDiaLetivo(string $ymd, int $ano, int $maxDias = 45): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return $ymd;
        }
        try {
            $d = new DateTime($ymd);
        } catch (Throwable $e) {
            return $ymd;
        }
        $maxDias = max(1, min(90, $maxDias));
        for ($i = 0; $i < $maxDias; $i++) {
            $key = $d->format('Y-m-d');
            if ($this->ehDiaLetivo($key, $ano)) {
                return $key;
            }
            $d->modify('+1 day');
        }
        while ((int) $d->format('N') > 5) {
            $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    private function slugificar(string $nome): string
    {
        $s = mb_strtolower(trim($nome), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
        $s = trim($s, '_');
        if ($s === '') {
            $s = 'tipo';
        }
        return mb_substr($s, 0, 50);
    }

    private function normalizarHex(string $hex): string
    {
        $hex = trim($hex);
        if (preg_match('/^#([0-9A-Fa-f]{6})$/', $hex, $m)) {
            return '#' . strtolower($m[1]);
        }
        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $hex, $m)) {
            $r = $m[1][0];
            $g = $m[1][1];
            $b = $m[1][2];
            return '#' . strtolower($r . $r . $g . $g . $b . $b);
        }
        return '';
    }

    private function clarearHex(string $hex): string
    {
        $hex = $this->normalizarHex($hex);
        if ($hex === '') {
            return '#f3f4f6';
        }
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        $mix = 0.82;
        $r = (int) round($r + (255 - $r) * $mix);
        $g = (int) round($g + (255 - $g) * $mix);
        $b = (int) round($b + (255 - $b) * $mix);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
