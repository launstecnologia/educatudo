<?php

namespace App\Dominio\Escola\Services;


use App\Core\Database;
use App\Modulos\Comunicacao\Services\SchoolCommunicationService;

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
        'reuniao' => [
            'slug' => 'reuniao', 'nome' => 'Reunião',
            'cor' => '#0f766e', 'cor_fundo' => '#ccfbf1',
            'efeito' => 'neutro', 'sistema' => 1, 'ordem' => 7,
        ],
        'passeio' => [
            'slug' => 'passeio', 'nome' => 'Passeio',
            'cor' => '#c2410c', 'cor_fundo' => '#ffedd5',
            'efeito' => 'neutro', 'sistema' => 1, 'ordem' => 8,
        ],
        'apresentacao' => [
            'slug' => 'apresentacao', 'nome' => 'Apresentação',
            'cor' => '#9d174d', 'cor_fundo' => '#fce7f3',
            'efeito' => 'neutro', 'sistema' => 1, 'ordem' => 9,
        ],
    ];

    private const MAX_TIPOS_CUSTOM = 30;

    /** @var array<string,array<string,mixed>>|null */
    private $tiposCache = null;

    /** @var bool|null */
    private $tiposTabelaCache = null;

    /** @var array<string,bool> */
    private $colunasCache = [];

    /** @var array<int,string>|null */
    private $coresCursoCache = null;

    /** @var array<int,int>|null */
    private $cursoPorSerieCache = null;

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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            return $geral ? $this->comAbrangencia($geral) : null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM calendario_letivo WHERE ano = :ano ORDER BY id ASC LIMIT 1",
            ['ano' => $ano]
        );
        return $row ? $this->comAbrangencia($row) : null;
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
        } catch (\Throwable $e) {
            try {
                $row = $this->db->fetch(
                    "SELECT serie_id, curso_novo_id FROM turmas WHERE id = :id LIMIT 1",
                    ['id' => $turmaId]
                );
            } catch (\Throwable $e2) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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

    public function excluirCalendario(int $id, int $autorId = 0): bool
    {
        if ($id <= 0 || !$this->tableExists()) {
            return false;
        }
        if ($this->vinculoAvisoDisponivel()) {
            $avisos = $this->db->fetchAll(
                "SELECT evento_escolar_id
                 FROM calendario_letivo_eventos
                 WHERE calendario_id = :id AND evento_escolar_id IS NOT NULL",
                ['id' => $id]
            ) ?: [];
            foreach ($avisos as $aviso) {
                $this->cancelarAvisoEscolar((int) ($aviso['evento_escolar_id'] ?? 0), $autorId);
            }
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
        $tom = $this->corDaAbrangencia($row['curso_ids'], $row['serie_ids']);
        $row['cor'] = $tom['cor'];
        $row['cor_texto'] = $tom['cor_texto'];
        return $row;
    }

    /**
     * @param list<int> $cursoIds
     * @param list<int> $serieIds
     * @return array{cor:string,cor_texto:string}
     */
    private function corDaAbrangencia(array $cursoIds, array $serieIds): array
    {
        $vazio = ['cor' => '', 'cor_texto' => ''];
        $ids = [];
        foreach ($cursoIds as $cursoId) {
            $cursoId = (int) $cursoId;
            if ($cursoId > 0) {
                $ids[$cursoId] = $cursoId;
            }
        }
        if ($serieIds !== []) {
            $porSerie = $this->cursoPorSerie();
            foreach ($serieIds as $serieId) {
                $cursoId = (int) ($porSerie[(int) $serieId] ?? 0);
                if ($cursoId > 0) {
                    $ids[$cursoId] = $cursoId;
                }
            }
        }
        $cores = $this->coresCurso();
        $escolhida = '';
        foreach ($ids as $id) {
            $cor = $cores[$id] ?? '';
            if ($cor === '') {
                continue;
            }
            if ($escolhida === '') {
                $escolhida = $cor;
                continue;
            }
            if ($escolhida !== $cor) {
                return $vazio;
            }
        }
        if ($escolhida === '') {
            return $vazio;
        }

        return ['cor' => $escolhida, 'cor_texto' => self::textoSobreCor($escolhida)];
    }

    /** @return array<int,string> */
    private function coresCurso(): array
    {
        if ($this->coresCursoCache !== null) {
            return $this->coresCursoCache;
        }
        $this->coresCursoCache = [];
        if (!$this->tabelaExiste('curso') || !$this->colunaExiste('curso', 'cor')) {
            return $this->coresCursoCache;
        }
        try {
            $rows = $this->db->fetchAll('SELECT id, cor FROM curso') ?: [];
        } catch (\Throwable $e) {
            return $this->coresCursoCache;
        }
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $cor = self::hexCor((string) ($row['cor'] ?? ''));
            if ($id > 0 && $cor !== '') {
                $this->coresCursoCache[$id] = $cor;
            }
        }

        return $this->coresCursoCache;
    }

    /** @return array<int,int> */
    private function cursoPorSerie(): array
    {
        if ($this->cursoPorSerieCache !== null) {
            return $this->cursoPorSerieCache;
        }
        $this->cursoPorSerieCache = [];
        if (!$this->tabelaExiste('serie')) {
            return $this->cursoPorSerieCache;
        }
        try {
            $rows = $this->db->fetchAll('SELECT id, curso_id FROM serie') ?: [];
        } catch (\Throwable $e) {
            return $this->cursoPorSerieCache;
        }
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $cursoId = (int) ($row['curso_id'] ?? 0);
            if ($id > 0 && $cursoId > 0) {
                $this->cursoPorSerieCache[$id] = $cursoId;
            }
        }

        return $this->cursoPorSerieCache;
    }

    public static function hexCor(string $cor): string
    {
        $cor = strtolower(trim($cor));
        return preg_match('/^#[0-9a-f]{6}$/', $cor) ? $cor : '';
    }

    public static function textoSobreCor(string $hex): string
    {
        $hex = ltrim(self::hexCor($hex), '#');
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }
        $canal = static function (string $par): float {
            $c = hexdec($par) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luma = (0.2126 * $canal(substr($hex, 0, 2)))
            + (0.7152 * $canal(substr($hex, 2, 2)))
            + (0.0722 * $canal(substr($hex, 4, 2)));

        return $luma > 0.55 ? '#111827' : '#ffffff';
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
            } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        if (!$this->vinculoAvisoDisponivel()) {
            $rows = $this->db->fetchAll(
                "SELECT * FROM calendario_letivo_eventos WHERE calendario_id = :id ORDER BY data_inicio ASC",
                ['id' => $calendarioId]
            ) ?: [];
            return $this->anexarAvisos($rows, false);
        }
        $rows = $this->db->fetchAll(
            "SELECT e.*,
                    s.id AS aviso_id,
                    s.status AS aviso_status,
                    s.prioridade AS aviso_prioridade,
                    s.publico AS aviso_publico,
                    s.dia_inteiro AS aviso_dia_inteiro,
                    DATE_FORMAT(s.inicio_em, '%H:%i') AS aviso_hora_inicio,
                    DATE_FORMAT(s.fim_em, '%H:%i') AS aviso_hora_fim
             FROM calendario_letivo_eventos e
             LEFT JOIN school_calendar_events s ON s.id = e.evento_escolar_id
             WHERE e.calendario_id = :id
             ORDER BY e.data_inicio ASC",
            ['id' => $calendarioId]
        ) ?: [];
        return $this->anexarAvisos($rows, true);
    }

    /**
     * Datas do evento: dia existente, ano de 2000 a 2100 e fim igual ou posterior ao início.
     * Fim vazio vale o mesmo dia. Data impossível não é convertida para outra.
     *
     * @return array{ok:bool,inicio:string,fim:string,erros:array<string,string>}
     */
    public static function validarDatasEvento(string $inicioRaw, string $fimRaw): array
    {
        $erros = [];
        $inicio = self::interpretarDataEvento($inicioRaw, $erros, 'data_inicio');
        if (trim($fimRaw) === '') {
            $fim = $inicio;
        } else {
            $fim = self::interpretarDataEvento($fimRaw, $erros, 'data_fim');
        }
        if ($inicio !== null && $fim !== null && $fim < $inicio && !isset($erros['data_fim'])) {
            $erros['data_fim'] = 'A data final deve ser igual ou posterior à data inicial.';
        }
        $aceito = $erros === [] && $inicio !== null && $fim !== null;

        return [
            'ok' => $aceito,
            'inicio' => $aceito ? $inicio : '',
            'fim' => $aceito ? $fim : '',
            'erros' => $erros,
        ];
    }

    /**
     * @param array<string,string> $erros
     */
    private static function interpretarDataEvento(string $raw, array &$erros, string $campo): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            $erros[$campo] = 'Informe uma data válida.';
            return null;
        }
        $dt = \DateTime::createFromFormat('!Y-m-d', $raw);
        $falhas = \DateTime::getLastErrors();
        $temFalha = $dt === false
            || (is_array($falhas) && (((int) ($falhas['warning_count'] ?? 0)) > 0 || ((int) ($falhas['error_count'] ?? 0)) > 0))
            || $dt->format('Y-m-d') !== $raw;
        if ($temFalha) {
            $erros[$campo] = 'Informe uma data válida.';
            return null;
        }
        $ano = (int) $dt->format('Y');
        if ($ano < 2000 || $ano > 2100) {
            $erros[$campo] = 'O ano deve estar entre 2000 e 2100.';
            return null;
        }

        return $raw;
    }

    private function datasEventoGravaveis(string $inicio, string $fim): bool
    {
        $checagem = self::validarDatasEvento($inicio, $fim);

        return $checagem['ok'] === true
            && $checagem['inicio'] === $inicio
            && $checagem['fim'] === $fim;
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
    ): int {
        if ($calendarioId <= 0 || !$this->tableExists() || !$this->datasEventoGravaveis($inicio, $fim)) {
            return 0;
        }
        if (!$this->tipoValido($tipo)) {
            $tipo = 'feriado';
        }
        $id = (int) $this->db->insert(
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

        return $id;
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
        if ($eventoId <= 0 || $calendarioId <= 0 || !$this->tableExists() || !$this->datasEventoGravaveis($inicio, $fim)) {
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

    public function excluirEvento(int $eventoId, int $autorId = 0): void
    {
        if ($eventoId <= 0 || !$this->tableExists()) {
            return;
        }
        if ($this->vinculoAvisoDisponivel()) {
            $row = $this->db->fetch(
                "SELECT evento_escolar_id FROM calendario_letivo_eventos WHERE id = :id LIMIT 1",
                ['id' => $eventoId]
            );
            $escolarId = (int) ($row['evento_escolar_id'] ?? 0);
            if ($escolarId > 0) {
                $this->cancelarAvisoEscolar($escolarId, $autorId);
            }
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
        } catch (\Throwable $e) {
            $cache = false;
        }
        return $cache;
    }

    public function vinculoAvisoDisponivel(): bool
    {
        return $this->tableExists()
            && $this->tabelaEscolarExiste()
            && $this->colunaExiste('calendario_letivo_eventos', 'evento_escolar_id');
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function turmasParaAviso(): array
    {
        try {
            $rows = $this->db->fetchAll('SELECT id, nome FROM turmas ORDER BY nome') ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['id' => (int) ($row['id'] ?? 0), 'nome' => (string) ($row['nome'] ?? '')];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string,turma_nome:string}>
     */
    public function alunosParaAviso(): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT a.id, a.nome, t.nome AS turma_nome
                 FROM alunos a
                 LEFT JOIN turmas t ON t.id = a.turma_id
                 WHERE a.ativo = 1
                 ORDER BY a.nome'
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'turma_nome' => (string) ($row['turma_nome'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Lê hora, prioridade e público do formulário do calendário letivo.
     *
     * @param array<string,mixed> $post
     * @return array{ok:bool,erros:array<string,string>,aviso:array<string,mixed>}
     */
    public function interpretarAvisoFamilia(array $post, bool $permitido): array
    {
        $vazio = [
            'publicar' => false,
            'hora_inicio' => '',
            'hora_fim' => '',
            'dia_inteiro' => true,
            'prioridade' => 'normal',
            'publico' => 'todos',
            'turmas' => [],
            'alunos' => [],
        ];
        if (!$permitido || empty($post['publicar_calendario_escolar'])) {
            return ['ok' => true, 'erros' => [], 'aviso' => $vazio];
        }
        $erros = [];
        $publico = (string) ($post['aviso_publico'] ?? 'todos');
        if (!in_array($publico, ['todos', 'turmas', 'alunos'], true)) {
            $publico = 'todos';
        }
        $turmas = $this->idsPost($post['turmas'] ?? []);
        $alunos = $this->idsPost($post['alunos'] ?? []);
        if ($publico === 'turmas' && $turmas === []) {
            $erros['aviso'] = 'Selecione ao menos uma turma para avisar a família.';
        }
        if ($publico === 'alunos' && $alunos === []) {
            $erros['aviso'] = 'Selecione ao menos um aluno para avisar a família.';
        }
        $diaInteiro = !empty($post['aviso_dia_inteiro']);
        $horaInicio = trim((string) ($post['aviso_hora_inicio'] ?? ''));
        $horaFim = trim((string) ($post['aviso_hora_fim'] ?? ''));
        if ($diaInteiro) {
            $horaInicio = '';
            $horaFim = '';
        } elseif (!$this->horaValida($horaInicio)) {
            $erros['hora_inicio'] = 'Informe o horário de início ou marque o dia inteiro.';
        } elseif ($horaFim !== '' && !$this->horaValida($horaFim)) {
            $erros['hora_inicio'] = 'Informe um horário de fim válido.';
        }
        $prioridade = (string) ($post['aviso_prioridade'] ?? 'normal');
        if (!in_array($prioridade, ['normal', 'importante', 'urgente'], true)) {
            $prioridade = 'normal';
        }
        if ($erros !== []) {
            return ['ok' => false, 'erros' => $erros, 'aviso' => $vazio];
        }

        return [
            'ok' => true,
            'erros' => [],
            'aviso' => [
                'publicar' => true,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
                'dia_inteiro' => $diaInteiro,
                'prioridade' => $prioridade,
                'publico' => $publico,
                'turmas' => $publico === 'turmas' ? $turmas : [],
                'alunos' => $publico === 'alunos' ? $alunos : [],
            ],
        ];
    }

    /**
     * Cria, atualiza ou cancela o aviso da família ligado ao evento letivo.
     *
     * @param array<string,mixed> $aviso
     * @return array{ok:bool,id:int,acao:string}
     */
    public function sincronizarAvisoFamilia(int $eventoLetivoId, array $aviso): array
    {
        $publicar = !empty($aviso['publicar']);
        $ignorado = ['ok' => true, 'id' => 0, 'acao' => 'ignorado'];
        if ($eventoLetivoId <= 0 || !$this->tabelaEscolarExiste()) {
            return $publicar ? ['ok' => false, 'id' => 0, 'acao' => 'erro'] : $ignorado;
        }
        if (!$this->vinculoAvisoDisponivel()) {
            if (!$publicar || !empty($aviso['atualizando'])) {
                return $publicar
                    ? ['ok' => true, 'id' => 0, 'acao' => 'sem_vinculo']
                    : $ignorado;
            }
            $aviso['turmas'] = $this->idsExistentes('turmas', (array) ($aviso['turmas'] ?? []));
            $aviso['alunos'] = $this->idsExistentes('alunos', (array) ($aviso['alunos'] ?? []));
            if (($aviso['publico'] ?? '') === 'turmas' && $aviso['turmas'] === []) {
                return ['ok' => false, 'id' => 0, 'acao' => 'erro'];
            }
            if (($aviso['publico'] ?? '') === 'alunos' && $aviso['alunos'] === []) {
                return ['ok' => false, 'id' => 0, 'acao' => 'erro'];
            }
            $id = $this->inserirAvisoEscolar($aviso, (int) ($aviso['autor_id'] ?? 0));
            if ($id <= 0) {
                return ['ok' => false, 'id' => 0, 'acao' => 'erro'];
            }
            $this->gravarPublicoAviso($id, (array) $aviso['turmas'], (array) $aviso['alunos']);
            $enviou = $this->notificarAvisoEscolar($id, $aviso, 'Novo evento: ');
            return ['ok' => true, 'id' => $id, 'acao' => 'criado', 'notificou' => $enviou];
        }

        $letivo = $this->db->fetch(
            "SELECT evento_escolar_id FROM calendario_letivo_eventos WHERE id = :id LIMIT 1",
            ['id' => $eventoLetivoId]
        );
        if (!$letivo) {
            return ['ok' => false, 'id' => 0, 'acao' => 'erro'];
        }
        $escolarId = (int) ($letivo['evento_escolar_id'] ?? 0);
        $autorId = (int) ($aviso['autor_id'] ?? 0);
        if (!$publicar) {
            if ($escolarId > 0) {
                $cancelamento = $this->cancelarAvisoEscolar($escolarId, $autorId);
                if ($cancelamento['cancelou']) {
                    return [
                        'ok' => true,
                        'id' => $escolarId,
                        'acao' => 'cancelado',
                        'notificou' => $cancelamento['notificou'],
                    ];
                }
            }
            return $ignorado;
        }

        $turmas = $this->idsExistentes('turmas', (array) ($aviso['turmas'] ?? []));
        $alunos = $this->idsExistentes('alunos', (array) ($aviso['alunos'] ?? []));
        $publico = (string) ($aviso['publico'] ?? 'todos');
        if ($publico === 'turmas' && $turmas === []) {
            return ['ok' => false, 'id' => 0, 'acao' => 'erro'];
        }
        if ($publico === 'alunos' && $alunos === []) {
            return ['ok' => false, 'id' => 0, 'acao' => 'erro'];
        }
        $aviso['turmas'] = $publico === 'turmas' ? $turmas : [];
        $aviso['alunos'] = $publico === 'alunos' ? $alunos : [];
        $aviso['publico'] = in_array($publico, ['todos', 'turmas', 'alunos'], true) ? $publico : 'todos';

        $atual = $escolarId > 0
            ? $this->db->fetch('SELECT * FROM school_calendar_events WHERE id = :id LIMIT 1', ['id' => $escolarId])
            : null;
        if (!$atual) {
            $orfao = $this->buscarAvisoOrfao($aviso);
            if ($orfao) {
                $escolarId = (int) $orfao['id'];
                $this->db->update(
                    'UPDATE calendario_letivo_eventos SET evento_escolar_id = :escolar WHERE id = :id',
                    ['escolar' => $escolarId, 'id' => $eventoLetivoId]
                );
                $atual = $orfao;
            }
        }
        if (!$atual) {
            $novoId = $this->inserirAvisoEscolar($aviso, $autorId);
            if ($novoId <= 0) {
                return ['ok' => false, 'id' => 0, 'acao' => 'erro'];
            }
            $this->gravarPublicoAviso($novoId, (array) $aviso['turmas'], (array) $aviso['alunos']);
            $this->db->update(
                'UPDATE calendario_letivo_eventos SET evento_escolar_id = :escolar WHERE id = :id',
                ['escolar' => $novoId, 'id' => $eventoLetivoId]
            );
            $enviou = $this->notificarAvisoEscolar($novoId, $aviso, 'Novo evento: ');
            return ['ok' => true, 'id' => $novoId, 'acao' => 'criado', 'notificou' => $enviou];
        }

        $dados = $this->dadosAvisoEscolar($aviso);
        $turmasAtuais = $this->idsDoAviso('school_calendar_event_classes', 'turma_id', $escolarId);
        $alunosAtuais = $this->idsDoAviso('school_calendar_event_students', 'aluno_id', $escolarId);
        $republicar = (string) ($atual['status'] ?? '') !== 'publicado';
        if (!$republicar && !$this->avisoMudou($atual, $dados, $turmasAtuais, $alunosAtuais, (array) $aviso['turmas'], (array) $aviso['alunos'])) {
            return ['ok' => true, 'id' => $escolarId, 'acao' => 'inalterado'];
        }
        try {
            $this->db->update(
                "UPDATE school_calendar_events
                    SET titulo = :title, descricao = :description, categoria = :category, prioridade = :priority,
                        local = :location, inicio_em = :starts, fim_em = :ends, dia_inteiro = :all_day,
                        publico = :audience, status = 'publicado', published_at = COALESCE(published_at, NOW())
                  WHERE id = :id",
                $dados + ['id' => $escolarId]
            );
        } catch (\Throwable $e) {
            error_log('Calendário letivo: falha ao atualizar aviso da família #' . $escolarId . ': ' . $e->getMessage());
            return ['ok' => false, 'id' => $escolarId, 'acao' => 'erro'];
        }
        $this->gravarPublicoAviso($escolarId, (array) $aviso['turmas'], (array) $aviso['alunos']);
        $enviou = $this->notificarAvisoEscolar($escolarId, $aviso, 'Evento atualizado: ');
        return ['ok' => true, 'id' => $escolarId, 'acao' => 'atualizado', 'notificou' => $enviou];
    }

    /**
     * Aviso já publicado com o mesmo título e o mesmo dia, ainda sem vínculo.
     *
     * @param array<string,mixed> $aviso
     * @return array<string,mixed>|null
     */
    private function buscarAvisoOrfao(array $aviso): ?array
    {
        $titulo = mb_substr(trim((string) ($aviso['titulo'] ?? '')), 0, 255);
        $inicio = (string) ($aviso['inicio'] ?? '');
        if ($titulo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT s.*
             FROM school_calendar_events s
             WHERE s.titulo = :titulo
               AND DATE(s.inicio_em) = :inicio
               AND s.status = 'publicado'
               AND NOT EXISTS (
                   SELECT 1 FROM calendario_letivo_eventos l WHERE l.evento_escolar_id = s.id
               )
             ORDER BY s.id DESC
             LIMIT 1",
            ['titulo' => $titulo, 'inicio' => $inicio]
        );
        return is_array($row) && !empty($row) ? $row : null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function anexarAvisos(array $rows, bool $comVinculo): array
    {
        $ids = [];
        foreach ($rows as &$row) {
            $row['aviso_id'] = (int) ($row['aviso_id'] ?? 0);
            $row['aviso_status'] = (string) ($row['aviso_status'] ?? '');
            $row['aviso_prioridade'] = (string) ($row['aviso_prioridade'] ?? 'normal');
            $row['aviso_publico'] = (string) ($row['aviso_publico'] ?? 'todos');
            $row['aviso_dia_inteiro'] = (int) ($row['aviso_dia_inteiro'] ?? 1);
            if (!$comVinculo || $row['aviso_id'] <= 0 || $row['aviso_dia_inteiro'] === 1) {
                $row['aviso_hora_inicio'] = '';
                $row['aviso_hora_fim'] = '';
            } else {
                $row['aviso_hora_inicio'] = (string) ($row['aviso_hora_inicio'] ?? '');
                $row['aviso_hora_fim'] = (string) ($row['aviso_hora_fim'] ?? '');
                if ($row['aviso_hora_fim'] === '00:00') {
                    $row['aviso_hora_fim'] = '';
                }
            }
            $row['aviso_turmas'] = [];
            $row['aviso_alunos'] = [];
            if ($row['aviso_id'] > 0) {
                $ids[$row['aviso_id']] = $row['aviso_id'];
            }
        }
        unset($row);
        if (!$comVinculo || $ids === []) {
            return $rows;
        }
        $turmas = [];
        $alunos = [];
        [$in, $params] = $this->inNomeado('av', array_values($ids));
        foreach ($this->db->fetchAll(
            "SELECT event_id, turma_id FROM school_calendar_event_classes WHERE event_id IN ($in)",
            $params
        ) ?: [] as $item) {
            $turmas[(int) $item['event_id']][] = (int) $item['turma_id'];
        }
        foreach ($this->db->fetchAll(
            "SELECT event_id, aluno_id FROM school_calendar_event_students WHERE event_id IN ($in)",
            $params
        ) ?: [] as $item) {
            $alunos[(int) $item['event_id']][] = (int) $item['aluno_id'];
        }
        foreach ($rows as &$row) {
            $id = (int) $row['aviso_id'];
            $row['aviso_turmas'] = $turmas[$id] ?? [];
            $row['aviso_alunos'] = $alunos[$id] ?? [];
        }
        unset($row);
        return $rows;
    }

    /**
     * @param array<string,mixed> $aviso
     */
    private function inserirAvisoEscolar(array $aviso, int $autorId): int
    {
        $dados = $this->dadosAvisoEscolar($aviso);
        try {
            return (int) $this->db->insert(
                "INSERT INTO school_calendar_events
                    (titulo, descricao, categoria, prioridade, local, inicio_em, fim_em, dia_inteiro, publico, status, criado_por, published_at)
                 VALUES (:title, :description, :category, :priority, :location, :starts, :ends, :all_day, :audience, 'publicado', :author, NOW())",
                $dados + ['author' => max(0, $autorId)]
            );
        } catch (\Throwable $e) {
            error_log('Calendário letivo: falha ao publicar aviso da família: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $aviso
     * @return array<string,mixed>
     */
    private function dadosAvisoEscolar(array $aviso): array
    {
        $titulo = mb_substr(trim((string) ($aviso['titulo'] ?? '')), 0, 255);
        $inicio = (string) ($aviso['inicio'] ?? '');
        $fim = (string) ($aviso['fim'] ?? '');
        if ($fim === '' || $fim < $inicio) {
            $fim = $inicio;
        }
        $diaInteiro = !empty($aviso['dia_inteiro']) || ((string) ($aviso['hora_inicio'] ?? '') === '' && (string) ($aviso['hora_fim'] ?? '') === '');
        $horaInicio = $this->horaValida((string) ($aviso['hora_inicio'] ?? '')) ? (string) $aviso['hora_inicio'] : '00:00';
        $horaFim = $this->horaValida((string) ($aviso['hora_fim'] ?? '')) ? (string) $aviso['hora_fim'] : '';
        $inicioEm = $diaInteiro ? $inicio . ' 00:00:00' : $inicio . ' ' . $horaInicio . ':00';
        $fimEm = $diaInteiro ? $fim . ' 23:59:59' : ($horaFim !== '' ? $fim . ' ' . $horaFim . ':00' : null);
        $local = trim((string) ($aviso['local'] ?? ''));
        $link = trim((string) ($aviso['link'] ?? ''));
        $descricao = $titulo;
        if ($link !== '') {
            $descricao .= "\n" . $link;
        }
        $prioridade = (string) ($aviso['prioridade'] ?? 'normal');
        if (!in_array($prioridade, ['normal', 'importante', 'urgente'], true)) {
            $prioridade = 'normal';
        }
        $publico = (string) ($aviso['publico'] ?? 'todos');
        if (!in_array($publico, ['todos', 'turmas', 'alunos'], true)) {
            $publico = 'todos';
        }

        return [
            'title' => $titulo,
            'description' => mb_substr($descricao, 0, 5000),
            'category' => $this->categoriaDoTipo((string) ($aviso['tipo'] ?? '')),
            'priority' => $prioridade,
            'location' => $local !== '' ? mb_substr($local, 0, 255) : null,
            'starts' => $inicioEm,
            'ends' => $fimEm,
            'all_day' => $diaInteiro ? 1 : 0,
            'audience' => $publico,
        ];
    }

    private function categoriaDoTipo(string $tipo): string
    {
        $mapa = [
            'feriado' => 'feriado',
            'avaliacao' => 'prova',
            'reuniao' => 'reunião',
            'passeio' => 'passeio',
            'apresentacao' => 'apresentação',
        ];
        return $mapa[$tipo] ?? 'evento';
    }

    /**
     * @param list<int> $turmas
     * @param list<int> $alunos
     */
    private function gravarPublicoAviso(int $eventoId, array $turmas, array $alunos): void
    {
        $this->db->query('DELETE FROM school_calendar_event_classes WHERE event_id = :id', ['id' => $eventoId]);
        $this->db->query('DELETE FROM school_calendar_event_students WHERE event_id = :id', ['id' => $eventoId]);
        foreach ($turmas as $turmaId) {
            $this->db->insert(
                'INSERT INTO school_calendar_event_classes (event_id, turma_id) VALUES (:id, :turma)',
                ['id' => $eventoId, 'turma' => (int) $turmaId]
            );
        }
        foreach ($alunos as $alunoId) {
            $this->db->insert(
                'INSERT INTO school_calendar_event_students (event_id, aluno_id) VALUES (:id, :aluno)',
                ['id' => $eventoId, 'aluno' => (int) $alunoId]
            );
        }
    }

    /**
     * @return array{cancelou:bool,notificou:bool}
     */
    private function cancelarAvisoEscolar(int $escolarId, int $autorId): array
    {
        $nada = ['cancelou' => false, 'notificou' => true];
        if ($escolarId <= 0 || !$this->tabelaEscolarExiste()) {
            return $nada;
        }
        $atual = $this->db->fetch(
            'SELECT id, titulo, inicio_em, publico, status FROM school_calendar_events WHERE id = :id LIMIT 1',
            ['id' => $escolarId]
        );
        if (!$atual || (string) ($atual['status'] ?? '') === 'cancelado') {
            return $nada;
        }
        $this->db->update(
            "UPDATE school_calendar_events SET status = 'cancelado' WHERE id = :id",
            ['id' => $escolarId]
        );
        $turmas = $this->idsDoAviso('school_calendar_event_classes', 'turma_id', $escolarId);
        $alunos = $this->idsDoAviso('school_calendar_event_students', 'aluno_id', $escolarId);
        $aviso = [
            'titulo' => (string) ($atual['titulo'] ?? ''),
            'inicio' => substr((string) ($atual['inicio_em'] ?? ''), 0, 10),
            'hora_inicio' => strlen((string) ($atual['inicio_em'] ?? '')) >= 16 ? substr((string) $atual['inicio_em'], 11, 5) : '',
            'dia_inteiro' => false,
            'publico' => (string) ($atual['publico'] ?? 'todos'),
            'turmas' => $turmas,
            'alunos' => $alunos,
            'autor_id' => $autorId,
        ];
        $enviou = $this->notificarAvisoEscolar($escolarId, $aviso, 'Evento cancelado: ');
        return ['cancelou' => true, 'notificou' => $enviou];
    }

    /**
     * @param array<string,mixed> $aviso
     */
    private function notificarAvisoEscolar(int $eventoId, array $aviso, string $prefixo): bool
    {
        try {
            $comunicacao = new SchoolCommunicationService($this->db);
            $pais = $comunicacao->parentIds(
                (string) ($aviso['publico'] ?? 'todos'),
                array_map('intval', (array) ($aviso['turmas'] ?? [])),
                array_map('intval', (array) ($aviso['alunos'] ?? []))
            );
            if ($pais === []) {
                return true;
            }
            $inicio = (string) ($aviso['inicio'] ?? '');
            $quando = \DateTime::createFromFormat('Y-m-d', $inicio);
            $dataFmt = $quando ? $quando->format('d/m/Y') : $inicio;
            $hora = (string) ($aviso['hora_inicio'] ?? '');
            if ($hora !== '' && empty($aviso['dia_inteiro'])) {
                $dataFmt .= ' ' . $hora;
            }
            $titulo = (string) ($aviso['titulo'] ?? '');
            $comunicacao->push(
                $pais,
                $prefixo . $titulo,
                $prefixo === 'Evento cancelado: ' ? 'Consulte o calendário escolar.' : $dataFmt,
                '/calendar-events/' . $eventoId,
                ['type' => 'calendar_event', 'event_id' => (string) $eventoId],
                (int) ($aviso['autor_id'] ?? 0)
            );
            return true;
        } catch (\Throwable $e) {
            error_log('Calendário letivo: falha ao notificar responsáveis do aviso #' . $eventoId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string,mixed> $atual
     * @param array<string,mixed> $dados
     * @param list<int> $turmasAtuais
     * @param list<int> $alunosAtuais
     * @param list<int> $turmas
     * @param list<int> $alunos
     */
    private function avisoMudou(array $atual, array $dados, array $turmasAtuais, array $alunosAtuais, array $turmas, array $alunos): bool
    {
        $pares = [
            'titulo' => 'title',
            'descricao' => 'description',
            'categoria' => 'category',
            'prioridade' => 'priority',
            'local' => 'location',
            'publico' => 'audience',
        ];
        foreach ($pares as $coluna => $chave) {
            if (trim((string) ($atual[$coluna] ?? '')) !== trim((string) ($dados[$chave] ?? ''))) {
                return true;
            }
        }
        if ((int) ($atual['dia_inteiro'] ?? 0) !== (int) ($dados['all_day'] ?? 0)) {
            return true;
        }
        if ($this->normalizarDataHora($atual['inicio_em'] ?? null) !== $this->normalizarDataHora($dados['starts'] ?? null)) {
            return true;
        }
        if ($this->normalizarDataHora($atual['fim_em'] ?? null) !== $this->normalizarDataHora($dados['ends'] ?? null)) {
            return true;
        }
        sort($turmasAtuais);
        sort($alunosAtuais);
        sort($turmas);
        sort($alunos);
        return $turmasAtuais !== $turmas || $alunosAtuais !== $alunos;
    }

    private function normalizarDataHora(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        $time = strtotime($texto);
        return $time === false ? $texto : date('Y-m-d H:i:s', $time);
    }

    /**
     * @return list<int>
     */
    private function idsPost(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param list<mixed> $ids
     * @return list<int>
     */
    private function idsExistentes(string $tabela, array $ids): array
    {
        if (!in_array($tabela, ['turmas', 'alunos'], true)) {
            return [];
        }
        $ids = $this->idsPost($ids);
        if ($ids === []) {
            return [];
        }
        [$in, $params] = $this->inNomeado('id', $ids);
        $rows = $this->db->fetchAll("SELECT id FROM {$tabela} WHERE id IN ($in)", $params) ?: [];
        return array_values(array_map('intval', array_column($rows, 'id')));
    }

    /**
     * @return list<int>
     */
    private function idsDoAviso(string $tabela, string $coluna, int $eventoId): array
    {
        if (!in_array($tabela, ['school_calendar_event_classes', 'school_calendar_event_students'], true)) {
            return [];
        }
        if (!in_array($coluna, ['turma_id', 'aluno_id'], true)) {
            return [];
        }
        $rows = $this->db->fetchAll(
            "SELECT {$coluna} AS alvo FROM {$tabela} WHERE event_id = :id",
            ['id' => $eventoId]
        ) ?: [];
        return array_values(array_map('intval', array_column($rows, 'alvo')));
    }

    private function horaValida(string $hora): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1;
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
        $inicio = new \DateTime($ano . '-01-01');
        $fim = new \DateTime($ano . '-12-31');

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
                $ini = new \DateTime((string) $ev['data_inicio']);
                $f = new \DateTime((string) $ev['data_fim']);
            } catch (\Throwable $e) {
                continue;
            }
            if ($f < $ini) {
                continue;
            }
            $anoIni = (int) $ini->format('Y');
            $anoFim = (int) $f->format('Y');
            if ($anoIni < 2000 || $anoFim < 2000 || $anoIni > 2100 || $anoFim > 2100 || $ini->diff($f)->days > 370) {
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
                $ini = new \DateTime((string) $ev['data_inicio']);
                $f = new \DateTime((string) $ev['data_fim']);
            } catch (\Throwable $e) {
                continue;
            }
            if ($f < $ini) {
                continue;
            }
            $anoIni = (int) $ini->format('Y');
            $anoFim = (int) $f->format('Y');
            if ($anoIni < 2000 || $anoFim < 2000 || $anoIni > 2100 || $anoFim > 2100 || $ini->diff($f)->days > 370) {
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
            $d = new \DateTime($ymd);
        } catch (\Throwable $e) {
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
            $d = new \DateTime($ymd);
        } catch (\Throwable $e) {
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

if (!class_exists('SchoolCalendarService', false) && class_exists(__NAMESPACE__ . '\\SchoolCalendarService', false)) {
    class_alias(__NAMESPACE__ . '\\SchoolCalendarService', 'SchoolCalendarService');
}
