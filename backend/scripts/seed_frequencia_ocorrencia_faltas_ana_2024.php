<?php
/**
 * Seed de frequência, ocorrências e quantidade de faltas — só Ana Luiza, 2024.
 *
 * Não cria ano, turma, aluna, notas nem boletim. Usa o que já existe
 * na escola de teste (turma 1A Santa Maria, ano 2024).
 *
 * Grava somente:
 *   - faltas por componente (quantidade do boletim Santa Maria)
 *   - chamada no diário (presente / falta) e entrada/saída
 *   - ocorrências liberadas para a família
 *
 * Idempotente (prefixo SM24). Recusa qualquer banco que não seja educatudo_educa.
 *
 * No painel Master esta carga não aparece: o Escolher lista só
 * database/migrations/*importar_dados_*.sql
 *   2026_11_16_importar_dados_santa_maria_ana_2024_frequencia.sql
 *
 * Uso (no container PHP / servidor da escola de teste):
 *   php scripts/seed_frequencia_ocorrencia_faltas_ana_2024.php --confirmar
 *   php scripts/seed_frequencia_ocorrencia_faltas_ana_2024.php --confirmar --dry-run
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';

use App\Core\Database;
use App\Core\PeriodoLetivo;
use App\Modulos\Ocorrencias\Services\OcorrenciaService;
use App\Modulos\VidaEscolar\Models\SchoolAbsence;

const BANCO_TESTE = 'educatudo_educa';
const ANO = 2024;
const PREFIXO = 'SM24';
const TURMA_NOME = '1A Santa Maria';
const ALUNO_NOME = 'Ana Luiza Oliveira De Souza';
const ALUNO_RA = '24254';
const ALUNO_NICK = 'sm24.analuiza';
const AULAS_POR_PERIODO = 6;
const MARCA_AULA = 'SM24 frequência 2024';

/**
 * Quantidade de faltas do boletim, por componente e período.
 *
 * @var array<string, array<int, int>>
 */
const FALTAS = [
    'Língua Portuguesa' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Redação' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Literatura' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
    'Arte' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
    'História 1' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'História 2' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
    'Geografia' => [1 => 0, 2 => 0, 3 => 3, 4 => 0],
    'Sociologia' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Filosofia' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Matemática 1' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
    'Matemática 2' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Física 1' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Física 2' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Química 1' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Química 2' => [1 => 0, 2 => 0, 3 => 4, 4 => 0],
    'Biologia 1' => [1 => 0, 2 => 0, 3 => 5, 4 => 0],
    'Biologia 2' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Língua Inglesa' => [1 => 0, 2 => 0, 3 => 2, 4 => 0],
    'Educação Física' => [1 => 0, 2 => 0, 3 => 1, 4 => 0],
];

/**
 * @var list<array{titulo:string,detalhe:string,nivel:string,categoria:string,data:string,status:string,local:string,materia:?string,retorno:?string}>
 */
const OCORRENCIAS = [
    [
        'titulo' => 'Atraso na entrada',
        'detalhe' => 'Ana Luiza chegou após o início da primeira aula. A família foi comunicada.',
        'nivel' => 'leve',
        'categoria' => 'atraso',
        'data' => '2024-03-12 07:42:00',
        'status' => 'encerrada',
        'local' => 'Portaria',
        'materia' => null,
        'retorno' => null,
    ],
    [
        'titulo' => 'Conversa durante a explicação',
        'detalhe' => 'Conversou durante a explicação de História e recebeu advertência verbal da coordenação.',
        'nivel' => 'moderado',
        'categoria' => 'comportamento',
        'data' => '2024-05-20 09:15:00',
        'status' => 'em_acompanhamento',
        'local' => 'Sala de aula',
        'materia' => 'História 1',
        'retorno' => '2024-06-03',
    ],
    [
        'titulo' => 'Dificuldade em Matemática',
        'detalhe' => 'A coordenação registrou dificuldade recorrente em Matemática no 3º bimestre e combinou acompanhamento.',
        'nivel' => 'leve',
        'categoria' => 'pedagogica',
        'data' => '2024-08-19 10:00:00',
        'status' => 'aberta',
        'local' => 'Coordenação',
        'materia' => 'Matemática 1',
        'retorno' => '2024-09-02',
    ],
    [
        'titulo' => 'Esqueceu o material',
        'detalhe' => 'Veio sem o material de Educação Física. Orientada a trazer na aula seguinte.',
        'nivel' => 'leve',
        'categoria' => 'material',
        'data' => '2024-09-04 08:10:00',
        'status' => 'encerrada',
        'local' => 'Quadra',
        'materia' => 'Educação Física',
        'retorno' => null,
    ],
    [
        'titulo' => 'Participação em Geografia',
        'detalhe' => 'Destaque na apresentação do trabalho de Geografia. Elogio registrado para a família.',
        'nivel' => 'leve',
        'categoria' => 'elogio',
        'data' => '2024-09-18 11:00:00',
        'status' => 'encerrada',
        'local' => 'Sala de aula',
        'materia' => 'Geografia',
        'retorno' => null,
    ],
];

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class SeedFrequenciaOcorrenciaFaltasAna2024
{
    private SchoolAbsence $faltas;
    private OcorrenciaService $ocorrencias;
    private int $adminId = 0;
    private int $anoId = 0;
    private int $turmaId = 0;
    private int $alunoId = 0;
    private int $professorId = 0;
    private int $periodos = 4;
    /** @var array<string, bool> */
    private array $colunas = [];
    /** @var array<string, int> nome normalizado => id */
    private array $materiaIds = [];

    public function __construct(private $db, private bool $dryRun)
    {
        $this->faltas = new SchoolAbsence();
        $this->ocorrencias = new OcorrenciaService();
    }

    public function executar(): int
    {
        println('== Frequência, ocorrências e faltas · Ana Luiza · ' . ANO . ' ==');
        println('  banco: ' . BANCO_TESTE);
        println('  só esta aluna; ano, turma e boletim não são recriados');
        if ($this->dryRun) {
            println('Modo dry-run (não grava).');
        }

        if (!$this->dryRun) {
            $this->faltas->ensureSchema();
        }
        $this->adminId = $this->resolverAdmin();
        $this->localizarAluna();
        $this->periodos = max(1, (int) (PeriodoLetivo::doAno(ANO)['quantidade'] ?? 4));
        $this->carregarMaterias();
        $this->professorId = $this->resolverProfessor();

        $this->lancarFaltas();
        $this->lancarFrequencia();
        $this->lancarOcorrencias();

        println('');
        println('Pronto.');
        println('  Aluna: ' . ALUNO_NOME . ' (#' . $this->alunoId . ')');
        println('  Turma: ' . TURMA_NOME . ' (#' . $this->turmaId . ')');
        println('  Ano: ' . ANO . ' (#' . $this->anoId . ')');
        println('  Faltas: /admin/faltas');
        println('  Frequência: /admin/frequencia e /admin/presenca');
        println('  Ocorrências: /admin/ocorrencias');
        return 0;
    }

    private function resolverAdmin(): int
    {
        $row = $this->db->fetch(
            "SELECT id FROM usuarios
              WHERE (email LIKE 'admin@%' OR tipo = 'admin_escola') AND COALESCE(ativo, 1) = 1
              ORDER BY id ASC LIMIT 1"
        );
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            fail('Nenhum admin ativo nesta escola.');
        }
        println('  admin_id=' . $id);
        return $id;
    }

    private function localizarAluna(): void
    {
        $params = [
            'ano' => ANO,
            'turma' => TURMA_NOME,
            'nome' => ALUNO_NOME,
            'ra' => ALUNO_RA,
            'nick' => ALUNO_NICK,
        ];
        $filtroAluna = '(a.nome = :nome OR a.ra = :ra OR a.nickname = :nick)';

        $row = null;
        try {
            $row = $this->db->fetch(
                "SELECT a.id AS aluno_id, t.id AS turma_id, al.id AS ano_id
                   FROM matricula m
                   INNER JOIN alunos a ON a.id = m.aluno_id
                   INNER JOIN turmas t ON t.id = m.turma_id
                   INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                  WHERE al.ano = :ano
                    AND t.nome = :turma
                    AND {$filtroAluna}
                    AND m.status IN ('ativa', 'concluido')
                  LIMIT 1",
                $params
            );
        } catch (Throwable $e) {
            $row = null;
        }

        if (!$row) {
            $row = $this->db->fetch(
                "SELECT a.id AS aluno_id, t.id AS turma_id, al.id AS ano_id
                   FROM alunos a
                   INNER JOIN turmas t ON t.id = a.turma_id
                   LEFT JOIN ano_letivo al ON al.id = t.ano_letivo_id
                  WHERE t.nome = :turma
                    AND (al.ano = :ano OR t.ano_letivo = :ano_turma)
                    AND {$filtroAluna}
                  LIMIT 1",
                $params + ['ano_turma' => ANO]
            );
        }

        $this->alunoId = (int) ($row['aluno_id'] ?? 0);
        $this->turmaId = (int) ($row['turma_id'] ?? 0);
        $this->anoId = (int) ($row['ano_id'] ?? 0);
        if ($this->anoId <= 0 && $this->alunoId > 0) {
            $ano = $this->db->fetch('SELECT id FROM ano_letivo WHERE ano = :a LIMIT 1', ['a' => ANO]);
            $this->anoId = (int) ($ano['id'] ?? 0);
        }
        if ($this->alunoId <= 0 || $this->turmaId <= 0 || $this->anoId <= 0) {
            fail(
                'Não encontrei ' . ALUNO_NOME . ' na turma ' . TURMA_NOME
                . ' do ano ' . ANO . ' em ' . BANCO_TESTE . '.'
                . ' O ano, a turma e a aluna precisam já existir.'
            );
        }
        println('  aluna #' . $this->alunoId . ' · turma #' . $this->turmaId . ' · ano #' . $this->anoId);
    }

    private function carregarMaterias(): void
    {
        $rows = $this->db->fetchAll('SELECT id, nome FROM materias') ?: [];
        foreach ($rows as $row) {
            $nome = $this->normalizar((string) ($row['nome'] ?? ''));
            $id = (int) ($row['id'] ?? 0);
            if ($nome !== '' && $id > 0 && !isset($this->materiaIds[$nome])) {
                $this->materiaIds[$nome] = $id;
            }
        }

        $achou = 0;
        foreach (array_keys(FALTAS) as $nome) {
            if (($this->materiaIds[$this->normalizar($nome)] ?? 0) > 0) {
                $achou++;
            }
        }
        println('  componentes do boletim encontrados: ' . $achou . '/' . count(FALTAS));
        if ($achou === 0) {
            println('  aviso: nenhum componente do boletim Santa Maria foi encontrado; faltas e chamada desses nomes serão puladas.');
        }
    }

    private function resolverProfessor(): int
    {
        $row = $this->db->fetch('SELECT id FROM professores ORDER BY id ASC LIMIT 1');
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            println('  aviso: nenhum professor; a chamada do diário será pulada.');
        }
        return $id;
    }

    private function lancarFaltas(): void
    {
        println('  faltas por período…');
        $totalFaltas = 0;
        for ($periodo = 1; $periodo <= $this->periodos; $periodo++) {
            // Mesmo nome do seed do boletim, para não duplicar o evento já ligado ao demonstrativo.
            $nome = sprintf('%s Faltas %dº bimestre %d', PREFIXO, $periodo, ANO);
            $porAluno = [$this->alunoId => []];
            foreach (FALTAS as $materia => $qtdPorPeriodo) {
                $mid = $this->materiaIds[$this->normalizar($materia)] ?? 0;
                if ($mid <= 0) {
                    continue;
                }
                $qtd = $this->faltasNoPeriodo($qtdPorPeriodo, $periodo);
                $porAluno[$this->alunoId][$mid] = (string) max(0, $qtd);
                $totalFaltas += max(0, $qtd);
            }
            if ($porAluno[$this->alunoId] === []) {
                println('  período ' . $periodo . ': nenhum componente para lançar');
                continue;
            }
            if ($this->dryRun) {
                println('  [dry-run] ' . $nome . ' · ' . count($porAluno[$this->alunoId]) . ' componentes');
                continue;
            }
            $eventoId = $this->garantirEventoFaltas($nome, $periodo);
            if ($eventoId <= 0) {
                println('  período ' . $periodo . ': falha ao criar evento de faltas');
                continue;
            }
            $this->faltas->upsertLancamentos($eventoId, $porAluno, [], $this->adminId);
            println('  período ' . $periodo . ': evento #' . $eventoId);
        }
        println('  quantidade de faltas: ' . $totalFaltas . ' (só esta aluna, somando os períodos)');
    }

    /**
     * Se o ano não tiver 4 períodos, as faltas dos períodos seguintes
     * entram no último, para a quantidade do boletim não sumir.
     *
     * @param array<int, int> $qtdPorPeriodo
     */
    private function faltasNoPeriodo(array $qtdPorPeriodo, int $periodo): int
    {
        $qtd = (int) ($qtdPorPeriodo[$periodo] ?? 0);
        if ($periodo === $this->periodos && $this->periodos < 4) {
            for ($extra = $this->periodos + 1; $extra <= 4; $extra++) {
                $qtd += (int) ($qtdPorPeriodo[$extra] ?? 0);
            }
        }
        return max(0, $qtd);
    }

    private function garantirEventoFaltas(string $nome, int $periodo): int
    {
        $exist = $this->db->fetch(
            'SELECT id FROM faltas_eventos WHERE nome = :n AND ano_letivo = :ano AND ativo = 1 LIMIT 1',
            ['n' => $nome, 'ano' => ANO]
        );
        $eventoId = $exist
            ? (int) $exist['id']
            : $this->faltas->createEvento($nome, (string) $periodo, ANO, [$this->turmaId], $this->adminId, [], 'manual');
        if ($eventoId <= 0) {
            return 0;
        }
        $ev = $this->faltas->getEventoById($eventoId);
        $turmas = is_array($ev['turmas_ids'] ?? null) ? $ev['turmas_ids'] : [];
        if (!in_array($this->turmaId, $turmas, true)) {
            $turmas[] = $this->turmaId;
            $this->faltas->updateEvento(
                $eventoId,
                (string) ($ev['nome'] ?? $nome),
                (string) ($ev['bimestre'] ?? (string) $periodo),
                ANO,
                $turmas,
                is_array($ev['materias_ids'] ?? null) ? $ev['materias_ids'] : [],
                (string) ($ev['origem'] ?? 'manual')
            );
        }
        return $eventoId;
    }

    private function lancarFrequencia(): void
    {
        println('  frequência ' . ANO . '…');
        if ($this->professorId <= 0) {
            return;
        }
        $slots = $this->slotsDaTurma();
        if ($slots === []) {
            if ($this->dryRun) {
                println('  [dry-run] a turma não tem grade; criaria os horários e a chamada');
                return;
            }
            $slots = $this->criarGradeMinima();
        }
        if ($slots === []) {
            println('  frequência: sem grade horária para a turma');
            return;
        }

        $porMateria = [];
        foreach ($slots as $slot) {
            $mid = (int) ($slot['materia_id'] ?? 0);
            if ($mid > 0 && !isset($porMateria[$mid])) {
                $porMateria[$mid] = $slot;
            }
        }

        $aulas = 0;
        $chamadas = 0;
        $faltasChamada = 0;
        $datasPresente = [];

        foreach (FALTAS as $materia => $qtdPorPeriodo) {
            $mid = $this->materiaIds[$this->normalizar($materia)] ?? 0;
            $slot = $porMateria[$mid] ?? null;
            if ($mid <= 0 || $slot === null) {
                continue;
            }
            $diaSemana = (int) ($slot['dia_semana'] ?? 0);
            if ($diaSemana < 1 || $diaSemana > 7) {
                continue;
            }
            for ($periodo = 1; $periodo <= $this->periodos; $periodo++) {
                $intervalo = PeriodoLetivo::intervalo(ANO, $periodo);
                $datas = $this->datasNoIntervalo(
                    (string) ($intervalo['inicio'] ?? ''),
                    (string) ($intervalo['fim'] ?? ''),
                    $diaSemana,
                    AULAS_POR_PERIODO
                );
                $faltasNoPeriodo = $this->faltasNoPeriodo($qtdPorPeriodo, $periodo);
                $usadas = 0;
                foreach ($datas as $data) {
                    $situacao = ($usadas < $faltasNoPeriodo) ? 'falta' : 'presente';
                    if ($situacao === 'falta') {
                        $usadas++;
                        $faltasChamada++;
                    } else {
                        $datasPresente[$data] = true;
                    }
                    if ($this->dryRun) {
                        $aulas++;
                        $chamadas++;
                        continue;
                    }
                    $aulaId = $this->garantirAula($slot, $data);
                    if ($aulaId <= 0) {
                        continue;
                    }
                    $aulas++;
                    $this->gravarChamada($aulaId, $situacao);
                    $chamadas++;
                }
            }
        }

        println('  chamada: ' . $chamadas . ' registros, ' . $faltasChamada . ' faltas no diário');
        $this->lancarEntradaSaida(array_keys($datasPresente));
        if ($aulas === 0) {
            println('  frequência: nenhum slot da grade casou com os componentes do boletim');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function slotsDaTurma(): array
    {
        return $this->db->fetchAll(
            'SELECT id, professor_id, turma_id, materia_id, dia_semana, horario_de, horario_ate
               FROM grade_horaria
              WHERE turma_id = :t
              ORDER BY dia_semana, horario_de, id',
            ['t' => $this->turmaId]
        ) ?: [];
    }

    /**
     * Uma aula por semana para cada componente do boletim, só se a turma ainda não tem grade.
     *
     * @return list<array<string, mixed>>
     */
    private function criarGradeMinima(): array
    {
        $horarios = [
            ['07:30:00', '08:20:00'],
            ['08:20:00', '09:10:00'],
            ['09:30:00', '10:20:00'],
            ['10:20:00', '11:10:00'],
        ];
        $criados = [];
        $i = 0;
        foreach (array_keys(FALTAS) as $materia) {
            $mid = $this->materiaIds[$this->normalizar($materia)] ?? 0;
            if ($mid <= 0) {
                continue;
            }
            $dia = ($i % 5) + 1;
            $hora = $horarios[intdiv($i, 5) % count($horarios)];
            $i++;
            $id = (int) $this->db->insert(
                "INSERT INTO grade_horaria
                    (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
                 VALUES
                    (:dia, :de, :ate, :turma, :prof, :mat, 'manha')",
                [
                    'dia' => $dia,
                    'de' => $hora[0],
                    'ate' => $hora[1],
                    'turma' => $this->turmaId,
                    'prof' => $this->professorId,
                    'mat' => $mid,
                ]
            );
            $criados[] = [
                'id' => $id,
                'professor_id' => $this->professorId,
                'turma_id' => $this->turmaId,
                'materia_id' => $mid,
                'dia_semana' => $dia,
                'horario_de' => $hora[0],
                'horario_ate' => $hora[1],
            ];
        }
        println('  grade horária criada: ' . count($criados) . ' horários (a turma não tinha grade)');
        return $criados;
    }

    /**
     * @return list<string>
     */
    private function datasNoIntervalo(string $inicio, string $fim, int $diaSemana, int $limite): array
    {
        if ($inicio === '' || $fim === '' || $limite <= 0) {
            return [];
        }
        $dia = DateTime::createFromFormat('Y-m-d', substr($inicio, 0, 10));
        $end = DateTime::createFromFormat('Y-m-d', substr($fim, 0, 10));
        if (!$dia || !$end || $end < $dia) {
            return [];
        }
        $out = [];
        while ($dia <= $end && count($out) < $limite) {
            if ((int) $dia->format('N') === $diaSemana) {
                $out[] = $dia->format('Y-m-d');
            }
            $dia->modify('+1 day');
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $slot
     */
    private function garantirAula(array $slot, string $data): int
    {
        $gradeId = (int) ($slot['id'] ?? 0);
        if ($gradeId <= 0) {
            return 0;
        }
        $exist = $this->db->fetch(
            'SELECT id FROM diario_aulas WHERE grade_horaria_id = :g AND data_aula = :d LIMIT 1',
            ['g' => $gradeId, 'd' => $data]
        );
        if ($exist) {
            return (int) $exist['id'];
        }
        return (int) $this->db->insert(
            "INSERT INTO diario_aulas
                (grade_horaria_id, professor_id, turma_id, materia_id, data_aula, horario_de, horario_ate,
                 execucao, conteudo_realizado, status, finalizada_at)
             VALUES
                (:grade_id, :professor_id, :turma_id, :materia_id, :data_aula, :horario_de, :horario_ate,
                 'conforme_planejado', :conteudo, 'finalizada', NOW())",
            [
                'grade_id' => $gradeId,
                'professor_id' => (int) ($slot['professor_id'] ?? $this->professorId),
                'turma_id' => $this->turmaId,
                'materia_id' => (int) ($slot['materia_id'] ?? 0),
                'data_aula' => $data,
                'horario_de' => (string) ($slot['horario_de'] ?? '07:30:00'),
                'horario_ate' => (string) ($slot['horario_ate'] ?? '08:20:00'),
                'conteudo' => MARCA_AULA,
            ]
        );
    }

    private function gravarChamada(int $aulaId, string $situacao): void
    {
        $obs = $situacao === 'falta' ? 'Falta do fechamento de 2024.' : null;
        $exist = $this->db->fetch(
            'SELECT id FROM diario_frequencias WHERE diario_aula_id = :a AND aluno_id = :al LIMIT 1',
            ['a' => $aulaId, 'al' => $this->alunoId]
        );
        if ($exist) {
            if ($this->temColuna('diario_frequencias', 'origem')) {
                $this->db->query(
                    'UPDATE diario_frequencias
                        SET situacao = :s, observacao = :o, origem = :origem
                      WHERE id = :id AND aluno_id = :al',
                    [
                        's' => $situacao,
                        'o' => $obs,
                        'origem' => 'importacao',
                        'id' => (int) $exist['id'],
                        'al' => $this->alunoId,
                    ]
                );
            } else {
                $this->db->query(
                    'UPDATE diario_frequencias SET situacao = :s, observacao = :o WHERE id = :id AND aluno_id = :al',
                    ['s' => $situacao, 'o' => $obs, 'id' => (int) $exist['id'], 'al' => $this->alunoId]
                );
            }
            return;
        }
        if ($this->temColuna('diario_frequencias', 'origem')) {
            $this->db->insert(
                'INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao, observacao, origem)
                 VALUES (:aula, :aluno, :s, :o, :origem)',
                [
                    'aula' => $aulaId,
                    'aluno' => $this->alunoId,
                    's' => $situacao,
                    'o' => $obs,
                    'origem' => 'importacao',
                ]
            );
            return;
        }
        $this->db->insert(
            'INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao, observacao)
             VALUES (:aula, :aluno, :s, :o)',
            ['aula' => $aulaId, 'aluno' => $this->alunoId, 's' => $situacao, 'o' => $obs]
        );
    }

    /**
     * @param list<string> $datas
     */
    private function lancarEntradaSaida(array $datas): void
    {
        if (!$this->temTabela('presenca_eventos')) {
            println('  presença: tabela ausente, pulada');
            return;
        }
        if ($this->dryRun) {
            println('  [dry-run] entrada/saída em ' . count($datas) . ' dias');
            return;
        }
        $gravados = 0;
        foreach ($datas as $data) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                continue;
            }
            $gravados += $this->gravarPresenca($data, 'entrada', $data . ' 07:18:00');
            $gravados += $this->gravarPresenca($data, 'saida', $data . ' 12:05:00');
        }
        println('  entrada/saída: ' . $gravados . ' eventos novos');
    }

    private function gravarPresenca(string $data, string $tipo, string $quando): int
    {
        $externo = PREFIXO . '-ana-' . $this->alunoId . '-' . $data . '-' . $tipo;
        $exist = $this->db->fetch(
            'SELECT id FROM presenca_eventos WHERE id_externo = :e LIMIT 1',
            ['e' => $externo]
        );
        if ($exist) {
            return 0;
        }
        $this->db->insert(
            "INSERT INTO presenca_eventos
                (aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, registrado_por, processado_em)
             VALUES
                (:aluno, :tipo, :quando, 'importacao', :externo, :ra, :admin, NOW())",
            [
                'aluno' => $this->alunoId,
                'tipo' => $tipo,
                'quando' => $quando,
                'externo' => $externo,
                'ra' => ALUNO_RA,
                'admin' => $this->adminId,
            ]
        );
        return 1;
    }

    private function lancarOcorrencias(): void
    {
        println('  ocorrências…');
        $this->garantirCategorias();
        $criadas = 0;
        foreach (OCORRENCIAS as $item) {
            $ja = $this->db->fetch(
                'SELECT id FROM alunos_ocorrencias WHERE aluno_id = :a AND titulo = :t LIMIT 1',
                ['a' => $this->alunoId, 't' => $item['titulo']]
            );
            if ($ja) {
                println('  já existe: ' . $item['titulo']);
                continue;
            }
            if ($this->dryRun) {
                println('  [dry-run] ' . $item['titulo']);
                $criadas++;
                continue;
            }
            $categoriaId = $this->categoriaId((string) $item['categoria']);
            $materiaNome = $item['materia'];
            $materiaId = $materiaNome !== null
                ? (int) ($this->materiaIds[$this->normalizar($materiaNome)] ?? 0)
                : 0;
            $res = $this->ocorrencias->criar([
                'aluno_id' => $this->alunoId,
                'titulo' => $item['titulo'],
                'detalhe' => $item['detalhe'],
                'nivel_gravidade' => $item['nivel'],
                'data_ocorrencia' => $item['data'],
                'categoria_id' => $categoriaId,
                'local' => $item['local'],
                'retorno_em' => $item['retorno'] ?? '',
                'enviar_pais' => 1,
                'materia_id' => $materiaId,
            ], $this->adminId, 'admin');
            if (empty($res['success'])) {
                println('  falhou: ' . $item['titulo'] . ' — ' . (string) ($res['error'] ?? ''));
                continue;
            }
            $id = (int) ($res['id'] ?? 0);
            if ($id > 0 && $item['status'] !== 'aberta') {
                $this->ocorrencias->alterarStatus($id, $item['status'], $this->adminId, 'Fechamento do seed de teste 2024');
            }
            if ($id > 0 && $materiaId > 0 && $this->temColuna('alunos_ocorrencias', 'materia_id')) {
                $this->db->query(
                    'UPDATE alunos_ocorrencias SET materia_id = :m WHERE id = :id AND aluno_id = :a',
                    ['m' => $materiaId, 'id' => $id, 'a' => $this->alunoId]
                );
            }
            $criadas++;
            println('  criada #' . $id . ': ' . $item['titulo']);
        }
        println('  ocorrências novas: ' . $criadas);
    }

    private function garantirCategorias(): void
    {
        if (!$this->temTabela('ocorrencias_categorias')) {
            return;
        }
        $catalogo = [
            'pedagogica' => ['Pedagógica', 1],
            'comportamento' => ['Comportamento / convivência', 2],
            'atraso' => ['Atraso', 3],
            'material' => ['Material', 5],
            'elogio' => ['Elogio', 7],
        ];
        foreach ($catalogo as $slug => [$nome, $ordem]) {
            $exist = $this->db->fetch(
                'SELECT id FROM ocorrencias_categorias WHERE slug = :s LIMIT 1',
                ['s' => $slug]
            );
            if ($exist || $this->dryRun) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO ocorrencias_categorias (slug, nome, ordem, ativo) VALUES (:s, :n, :o, 1)',
                ['s' => $slug, 'n' => $nome, 'o' => $ordem]
            );
        }
    }

    private function categoriaId(string $slug): int
    {
        if (!$this->temTabela('ocorrencias_categorias')) {
            return 0;
        }
        $row = $this->db->fetch(
            'SELECT id FROM ocorrencias_categorias WHERE slug = :s AND ativo = 1 LIMIT 1',
            ['s' => $slug]
        );
        return (int) ($row['id'] ?? 0);
    }

    private function normalizar(string $nome): string
    {
        return mb_strtolower(trim($nome));
    }

    private function temTabela(string $tabela): bool
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            ['t' => $tabela]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        $chave = $tabela . '.' . $coluna;
        if (isset($this->colunas[$chave])) {
            return $this->colunas[$chave];
        }
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
            ['t' => $tabela, 'c' => $coluna]
        );
        $this->colunas[$chave] = (int) ($row['c'] ?? 0) > 0;
        return $this->colunas[$chave];
    }
}

$uso = 'Uso: php scripts/seed_frequencia_ocorrencia_faltas_ana_2024.php --confirmar [--dry-run]';
$dryRun = false;
$confirmar = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if ($arg === '--confirmar') {
        $confirmar = true;
        continue;
    }
    if (str_starts_with($arg, '--db=')) {
        $informado = preg_replace('/[^a-zA-Z0-9_]/', '', substr($arg, 5)) ?: '';
        if ($informado !== BANCO_TESTE) {
            fail('Este seed só roda no banco ' . BANCO_TESTE . '.');
        }
        continue;
    }
    fail('Argumento inválido: ' . $arg . PHP_EOL . $uso);
}

if (!$confirmar) {
    fail(
        'Por segurança, confirme com --confirmar.' . PHP_EOL
        . 'Grava frequência, ocorrências e faltas de ' . ALUNO_NOME
        . ' somente em ' . BANCO_TESTE . '.' . PHP_EOL
        . $uso
    );
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');

try {
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . BANCO_TESTE . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    Database::setCurrentInstance(Database::createFromPdo($pdo, [
        'host' => $host,
        'port' => $port,
        'name' => BANCO_TESTE,
        'user' => $dbUser,
        'pass' => $dbPass,
    ]));
} catch (Throwable $e) {
    fail('Não conectou em ' . BANCO_TESTE . '. ' . $e->getMessage());
}

exit((new SeedFrequenciaOcorrenciaFaltasAna2024(Database::getInstance(), $dryRun))->executar());
