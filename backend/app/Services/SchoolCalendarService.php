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
    public function salvarTipo(string $nome, string $cor, bool $naoLetivo): array
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
        $efeito = $naoLetivo ? 'nao_letivo' : 'neutro';
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

    public function tipoValido(string $tipo): bool
    {
        return isset($this->tipos()[$tipo]);
    }

    /** @return array<string,mixed>|null */
    public function getAno(int $ano): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $row = $this->db->fetch("SELECT * FROM calendario_letivo WHERE ano = :ano LIMIT 1", ['ano' => $ano]);
        return $row ?: null;
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
        return (int) $this->db->insert(
            "INSERT INTO calendario_letivo (ano, dias_meta, carga_horaria_meta, observacao) VALUES (:ano, :d, :c, :o)",
            ['ano' => $ano, 'd' => $diasMeta, 'c' => $cargaMeta, 'o' => $obs !== '' ? $obs : null]
        );
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
