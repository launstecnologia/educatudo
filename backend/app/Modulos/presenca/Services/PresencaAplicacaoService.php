<?php

require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../../../Models/Education/ClassDiary.php';
require_once __DIR__ . '/../Models/PresencaConfig.php';
require_once __DIR__ . '/../Models/PresencaEvento.php';

/**
 * Cruza entrada/saída do dia com a grade horária e grava diario_frequencias.
 * Não finaliza a aula. Não incrementa faltas_lancamentos (isso é a consolidação).
 */
class PresencaAplicacaoService
{
    public const ORIGEM_ENTRADA_SAIDA = 'entrada_saida';
    public const ORIGEM_AJUSTE = 'ajuste_gestao';
    public const ORIGEM_INTEGRACAO = 'integracao';

    private $db;
    private $diary;
    private $configModel;
    private $eventos;

    public function __construct(
        ?Database $db = null,
        ?ClassDiary $diary = null,
        ?PresencaConfig $config = null,
        ?PresencaEvento $eventos = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->diary = $diary ?? new ClassDiary();
        $this->configModel = $config ?? new PresencaConfig();
        $this->eventos = $eventos ?? new PresencaEvento();
        $this->diary->ensureSchema();
    }

    /**
     * Recalcula a chamada do aluno no dia a partir de todos os eventos de portaria.
     *
     * @return array{aplicadas:int,puladas:int,erro:?string}
     */
    public function aplicarDiaAluno(int $alunoId, string $data, string $origemMarca = self::ORIGEM_ENTRADA_SAIDA): array
    {
        $out = ['aplicadas' => 0, 'puladas' => 0, 'erro' => null];
        if ($alunoId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $out['erro'] = 'Aluno ou data inválidos.';
            return $out;
        }
        $turmaId = $this->turmaDoAlunoNaData($alunoId, $data);
        if ($turmaId <= 0) {
            $out['erro'] = 'Aluno sem turma na data.';
            return $out;
        }
        $grades = $this->gradesDaTurmaNoDia($turmaId, $data);
        if ($grades === []) {
            return $out;
        }

        $config = $this->configModel->obter();
        $eventos = $this->eventos->doAlunoNoDia($alunoId, $data);
        $intervalos = $this->intervalosNoPredio($eventos);
        $tolerancia = (int) $config['tolerancia_atraso_min'];

        foreach ($grades as $grade) {
            $situacao = $this->situacaoDoSlot($grade, $intervalos, $tolerancia);
            if ($situacao === null) {
                $out['puladas']++;
                continue;
            }
            try {
                $aplicou = $this->gravarSlot($grade, $data, $alunoId, $situacao, $origemMarca, (bool) $config['criar_aula_rascunho']);
                if ($aplicou) {
                    $out['aplicadas']++;
                } else {
                    $out['puladas']++;
                }
            } catch (Throwable $e) {
                $out['puladas']++;
                $out['erro'] = $e->getMessage();
            }
        }
        return $out;
    }

    /**
     * Cron: turmas cuja 1ª aula do dia + tolerância de corte já passou,
     * alunos sem entrada → falta em todas as aulas.
     *
     * @return array{turmas:int,alunos:int,aplicadas:int}
     */
    public function processarCorteDoDia(string $data, ?DateTimeImmutable $agora = null): array
    {
        $out = ['turmas' => 0, 'alunos' => 0, 'aplicadas' => 0];
        if (!$this->db->tableExists('presenca_eventos') || !$this->db->tableExists('grade_horaria')) {
            return $out;
        }
        $agora = $agora ?? new DateTimeImmutable('now');
        if ($agora->format('Y-m-d') !== $data) {
            return $out;
        }
        $config = $this->configModel->obter();
        $corteMin = (int) $config['minutos_corte_sem_entrada'];
        $dia = (int) $agora->format('N');
        $agoraMin = ((int) $agora->format('G')) * 60 + (int) $agora->format('i');
        $turmas = $this->db->fetchAll(
            'SELECT turma_id, MIN(horario_de) AS primeira
             FROM grade_horaria WHERE dia_semana = :dia
             GROUP BY turma_id',
            ['dia' => $dia]
        ) ?: [];
        foreach ($turmas as $t) {
            $turmaId = (int) ($t['turma_id'] ?? 0);
            if ($turmaId <= 0) {
                continue;
            }
            $primeira = $this->minutosDeHora((string) ($t['primeira'] ?? '00:00:00'));
            if ($agoraMin < $primeira + $corteMin) {
                continue;
            }
            $out['turmas']++;
            $alunos = $this->eventos->alunosSemEntradaNoDia($turmaId, $data);
            foreach ($alunos as $alunoId) {
                $out['alunos']++;
                $r = $this->marcarFaltaSemEntrada($alunoId, $data);
                $out['aplicadas'] += (int) ($r['aplicadas'] ?? 0);
            }
        }
        return $out;
    }

    /**
     * Marca falta em todas as aulas do dia (cron: passou o corte e não houve entrada).
     *
     * @return array{aplicadas:int,puladas:int}
     */
    public function marcarFaltaSemEntrada(int $alunoId, string $data): array
    {
        $out = ['aplicadas' => 0, 'puladas' => 0];
        $entrada = $this->db->fetch(
            "SELECT id FROM presenca_eventos
             WHERE aluno_id = :aluno_id AND tipo = 'entrada' AND DATE(ocorrido_em) = :data LIMIT 1",
            ['aluno_id' => $alunoId, 'data' => $data]
        );
        if ($entrada) {
            return $out;
        }
        $turmaId = $this->turmaDoAlunoNaData($alunoId, $data);
        if ($turmaId <= 0) {
            return $out;
        }
        $config = $this->configModel->obter();
        foreach ($this->gradesDaTurmaNoDia($turmaId, $data) as $grade) {
            try {
                if ($this->gravarSlot($grade, $data, $alunoId, 'falta', self::ORIGEM_ENTRADA_SAIDA, (bool) $config['criar_aula_rascunho'])) {
                    $out['aplicadas']++;
                } else {
                    $out['puladas']++;
                }
            } catch (Throwable $e) {
                $out['puladas']++;
            }
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $eventos
     * @return list<array{inicio:int,fim:int}>
     */
    private function intervalosNoPredio(array $eventos): array
    {
        $dentro = false;
        $inicio = 0;
        $out = [];
        $primeiro = true;
        foreach ($eventos as $ev) {
            $tipo = (string) ($ev['tipo'] ?? '');
            $min = $this->minutosDeDatetime((string) ($ev['ocorrido_em'] ?? ''));
            if ($primeiro && $tipo === 'saida' && !$dentro) {
                $out[] = ['inicio' => 0, 'fim' => $min];
                $primeiro = false;
                continue;
            }
            $primeiro = false;
            if ($tipo === 'entrada' && !$dentro) {
                $inicio = $min;
                $dentro = true;
            } elseif ($tipo === 'saida' && $dentro) {
                $out[] = ['inicio' => $inicio, 'fim' => max($inicio, $min)];
                $dentro = false;
            }
        }
        if ($dentro) {
            $out[] = ['inicio' => $inicio, 'fim' => 24 * 60];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $grade
     * @param list<array{inicio:int,fim:int}> $intervalos
     */
    private function situacaoDoSlot(array $grade, array $intervalos, int $tolerancia): ?string
    {
        $de = $this->minutosDeHora((string) ($grade['horario_de'] ?? '00:00:00'));
        $ate = $this->minutosDeHora((string) ($grade['horario_ate'] ?? '00:00:00'));
        if ($ate <= $de) {
            $ate = $de + 50;
        }
        $melhor = null;
        foreach ($intervalos as $iv) {
            $oIni = max($de, $iv['inicio']);
            $oFim = min($ate, $iv['fim']);
            if ($oIni >= $oFim) {
                continue;
            }
            if ($iv['inicio'] > $de + $tolerancia) {
                $sit = 'atraso';
            } elseif ($iv['fim'] < $ate) {
                $sit = 'saida_antecipada';
            } else {
                $sit = 'presente';
            }
            $melhor = $this->preferirSituacao($melhor, $sit);
        }
        return $melhor ?? 'falta';
    }

    private function preferirSituacao(?string $atual, string $novo): string
    {
        $ordem = ['falta' => 0, 'atraso' => 1, 'saida_antecipada' => 2, 'presente' => 3];
        if ($atual === null) {
            return $novo;
        }
        return ($ordem[$novo] ?? 0) > ($ordem[$atual] ?? 0) ? $novo : $atual;
    }

    /**
     * @param array<string,mixed> $grade
     */
    private function gravarSlot(
        array $grade,
        string $data,
        int $alunoId,
        string $situacao,
        string $origemMarca,
        bool $criarAula
    ): bool {
        $fechamento = $this->diary->fechamentoDaData(
            (int) $grade['turma_id'],
            (int) $grade['materia_id'],
            (int) $grade['professor_id'],
            $data
        );
        if ($fechamento && (string) ($fechamento['status'] ?? '') === 'fechado') {
            return false;
        }

        $aula = $this->db->fetch(
            'SELECT id FROM diario_aulas WHERE grade_horaria_id = :gid AND data_aula = :data LIMIT 1',
            ['gid' => (int) $grade['id'], 'data' => $data]
        );
        if (!$aula) {
            if (!$criarAula) {
                return false;
            }
            $criada = $this->diary->getOrCreateAula($grade, $data, null);
            $aulaId = (int) ($criada['id'] ?? 0);
        } else {
            $aulaId = (int) $aula['id'];
        }
        if ($aulaId <= 0) {
            return false;
        }

        $temOrigem = $this->colunaExiste('diario_frequencias', 'origem');
        $atual = $this->db->fetch(
            'SELECT situacao' . ($temOrigem ? ', origem' : '') . '
             FROM diario_frequencias WHERE diario_aula_id = :aula AND aluno_id = :aluno LIMIT 1',
            ['aula' => $aulaId, 'aluno' => $alunoId]
        );
        if ($atual && !$this->podeSobrescrever((string) ($atual['situacao'] ?? ''), (string) ($atual['origem'] ?? ''), $origemMarca)) {
            return false;
        }

        $permitidas = ['presente', 'falta', 'falta_justificada', 'atraso', 'saida_antecipada'];
        if (!in_array($situacao, $permitidas, true)) {
            $situacao = 'falta';
        }

        if ($temOrigem) {
            $this->db->query(
                'INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao, origem)
                 VALUES (:aula, :aluno, :sit, :origem)
                 ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), origem = VALUES(origem), updated_at = CURRENT_TIMESTAMP',
                ['aula' => $aulaId, 'aluno' => $alunoId, 'sit' => $situacao, 'origem' => $origemMarca]
            );
        } else {
            $this->db->query(
                'INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao)
                 VALUES (:aula, :aluno, :sit)
                 ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), updated_at = CURRENT_TIMESTAMP',
                ['aula' => $aulaId, 'aluno' => $alunoId, 'sit' => $situacao]
            );
        }
        return true;
    }

    private function podeSobrescrever(string $situacaoAtual, string $origemAtual, string $origemNova): bool
    {
        if ($situacaoAtual === 'falta_justificada') {
            return false;
        }
        if ($origemAtual === 'manual_diario' && $origemNova !== self::ORIGEM_AJUSTE) {
            return false;
        }
        if ($origemAtual === self::ORIGEM_AJUSTE && $origemNova !== self::ORIGEM_AJUSTE) {
            return false;
        }
        return true;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function gradesDaTurmaNoDia(int $turmaId, string $data): array
    {
        $dia = (int) date('N', strtotime($data));
        return $this->db->fetchAll(
            'SELECT gh.*
             FROM grade_horaria gh
             WHERE gh.turma_id = :turma_id AND gh.dia_semana = :dia
             ORDER BY gh.horario_de ASC, gh.id ASC',
            ['turma_id' => $turmaId, 'dia' => $dia]
        ) ?: [];
    }

    public function turmaDoAlunoNaData(int $alunoId, string $data): int
    {
        if ($this->db->tableExists('matricula')) {
            $row = $this->db->fetch(
                "SELECT turma_id FROM matricula
                 WHERE aluno_id = :aluno_id
                   AND data_entrada <= :data
                   AND (data_saida IS NULL OR data_saida >= :data)
                 ORDER BY id DESC LIMIT 1",
                ['aluno_id' => $alunoId, 'data' => $data]
            );
            $tid = (int) ($row['turma_id'] ?? 0);
            if ($tid > 0) {
                return $tid;
            }
        }
        $aluno = $this->db->fetch('SELECT turma_id FROM alunos WHERE id = :id LIMIT 1', ['id' => $alunoId]);
        return (int) ($aluno['turma_id'] ?? 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function aulasDoAlunoNoDia(int $alunoId, string $data): array
    {
        $turmaId = $this->turmaDoAlunoNaData($alunoId, $data);
        if ($turmaId <= 0) {
            return [];
        }
        $grades = $this->gradesDaTurmaNoDia($turmaId, $data);
        $temOrigem = $this->colunaExiste('diario_frequencias', 'origem');
        $out = [];
        foreach ($grades as $g) {
            $freq = $this->db->fetch(
                'SELECT df.situacao' . ($temOrigem ? ', df.origem' : '') . '
                 FROM diario_aulas da
                 INNER JOIN diario_frequencias df ON df.diario_aula_id = da.id AND df.aluno_id = :aluno
                 WHERE da.grade_horaria_id = :gid AND da.data_aula = :data LIMIT 1',
                ['aluno' => $alunoId, 'gid' => (int) $g['id'], 'data' => $data]
            );
            $materia = $this->db->fetch('SELECT nome FROM materias WHERE id = :id', ['id' => (int) $g['materia_id']]);
            $out[] = [
                'horario_de' => (string) $g['horario_de'],
                'horario_ate' => (string) $g['horario_ate'],
                'materia_nome' => (string) ($materia['nome'] ?? ''),
                'situacao' => $freq['situacao'] ?? null,
                'origem' => $freq['origem'] ?? null,
            ];
        }
        return $out;
    }

    private function minutosDeHora(string $hora): int
    {
        $p = explode(':', $hora);
        return ((int) ($p[0] ?? 0)) * 60 + ((int) ($p[1] ?? 0));
    }

    private function minutosDeDatetime(string $dt): int
    {
        $ts = strtotime($dt);
        if ($ts === false) {
            return 0;
        }
        return ((int) date('G', $ts)) * 60 + (int) date('i', $ts);
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        static $cache = [];
        $key = $tabela . '.' . $coluna;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $row = $this->db->fetch(
            'SELECT 1 AS ok FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1',
            ['t' => $tabela, 'c' => $coluna]
        );
        $cache[$key] = (bool) $row;
        return $cache[$key];
    }
}
