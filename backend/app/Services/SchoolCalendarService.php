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
        $tipos = ['feriado', 'recesso', 'reposicao', 'evento', 'suspensao', 'avaliacao'];
        if (!in_array($tipo, $tipos, true)) {
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

        $naoLetivos = [];
        $reposicoes = [];
        foreach ($eventos as $ev) {
            $tipo = (string) ($ev['tipo'] ?? '');
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
                if (in_array($tipo, ['feriado', 'recesso', 'suspensao'], true) && $n <= 5) {
                    $naoLetivos[$key] = true;
                } elseif ($tipo === 'reposicao' && $n >= 6) {
                    $reposicoes[$key] = true;
                }
            }
        }

        $total = $uteis - count($naoLetivos) + count($reposicoes);
        return max(0, $total);
    }
}
