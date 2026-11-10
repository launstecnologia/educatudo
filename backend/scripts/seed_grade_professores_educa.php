<?php
/**
 * Seed local do Colégio Educa: um professor por matéria em cada segmento,
 * salas de aula, ambientes e a grade de 2026.
 *
 * Ensino médio: 07:00–12:20, aulas de 50 min, intervalo 09:30–09:50.
 *
 * Uso (container PHP):
 *   php scripts/seed_grade_professores_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');
if (!defined('TENANT_SLUG')) {
    define('TENANT_SLUG', 'educa');
}

require_once $basePath . '/vendor/autoload.php';
require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';

use App\Core\Database;

const TENANT_DB = 'educatudo_educa';
const SENHA = 'Teste@123';
const ANO = 2026;

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

/**
 * Monta a grade sem colocar o mesmo professor (uma matéria, um docente) em duas turmas no mesmo horário.
 *
 * @param list<int> $turmaIds
 * @param array<int,int> $carga materia_id => aulas na semana
 * @param list<int> $forcarPeriodos índices de período que não podem ficar vazios
 * @return array<int,list<array{0:int,1:int,2:int}>>|null turma => [dia 0-4, período, materia_id]
 */
function resolverGrade(array $turmaIds, array $carga, int $periodos, array $forcarPeriodos): ?array
{
    $slots = [];
    for ($dia = 0; $dia < 5; $dia++) {
        for ($periodo = 0; $periodo < $periodos; $periodo++) {
            $slots[] = [$dia, $periodo, in_array($periodo, $forcarPeriodos, true)];
        }
    }

    $pesada = 0;
    $aulasPesada = -1;
    foreach ($carga as $mid => $qtd) {
        if ($qtd > $aulasPesada) {
            $aulasPesada = $qtd;
            $pesada = (int) $mid;
        }
    }
    $satura = $aulasPesada * count($turmaIds) === 5 * $periodos;

    for ($tentativa = 0; $tentativa < 8000; $tentativa++) {
        $grade = tentarGrade($turmaIds, $carga, $slots, $periodos, $satura ? $pesada : 0, $aulasPesada);
        if ($grade !== null) {
            return $grade;
        }
    }

    return null;
}

/**
 * @param list<int> $turmaIds
 * @param array<int,int> $carga
 * @param list<array{0:int,1:int,2:bool}> $slots
 * @return array<int,list<array{0:int,1:int,2:int}>>|null
 */
function tentarGrade(array $turmaIds, array $carga, array $slots, int $periodos, int $fixarMateria, int $aulasFixas): ?array
{
    $rest = [];
    $celulas = [];
    foreach ($turmaIds as $tid) {
        $rest[$tid] = $carga;
        $celulas[$tid] = [];
    }

    if ($fixarMateria > 0 && $aulasFixas <= 5 && count($turmaIds) <= $periodos) {
        $ocupado = [];
        foreach ($turmaIds as $i => $tid) {
            for ($k = 0; $k < $aulasFixas; $k++) {
                $periodo = ($i + $k) % $periodos;
                $chave = $k . '-' . $periodo;
                if (isset($ocupado[$chave]) || ($rest[$tid][$fixarMateria] ?? 0) <= 0) {
                    return null;
                }
                $ocupado[$chave] = true;
                $celulas[$tid][$chave] = $fixarMateria;
                $rest[$tid][$fixarMateria]--;
            }
        }
    }

    $faltamCelulas = [];
    foreach ($turmaIds as $tid) {
        $faltamCelulas[$tid] = count($slots);
    }

    // Uma folga por dia, nunca no primeiro nem no último tempo: o dia segue das 7h ao fim.
    $folga = [];
    $umaPorDia = array_sum($carga) === 5 * ($periodos - 1) && $periodos >= 4;
    if ($umaPorDia) {
        $miolo = $periodos - 2;
        foreach ($turmaIds as $i => $tid) {
            for ($dia = 0; $dia < 5; $dia++) {
                $folga[$tid][$dia . '-' . (1 + (($i + $dia) % $miolo))] = true;
            }
        }
    }

    foreach ($slots as $slot) {
        [$dia, $periodo, $forcado] = $slot;
        $chave = $dia . '-' . $periodo;
        $usados = [];
        foreach ($turmaIds as $tid) {
            if (isset($celulas[$tid][$chave])) {
                $usados[$celulas[$tid][$chave]] = true;
            }
        }
        $ordem = $turmaIds;
        shuffle($ordem);
        usort($ordem, static function (int $a, int $b) use ($rest): int {
            return array_sum($rest[$b]) <=> array_sum($rest[$a]);
        });

        foreach ($ordem as $tid) {
            $faltamCelulas[$tid]--;
            if (isset($celulas[$tid][$chave]) || isset($folga[$tid][$chave])) {
                continue;
            }
            $candidatas = [];
            foreach ($rest[$tid] as $mid => $qtd) {
                if ($qtd > 0 && !isset($usados[$mid])) {
                    $candidatas[] = (int) $mid;
                }
            }
            $obrigatorio = $umaPorDia || $forcado || array_sum($rest[$tid]) >= $faltamCelulas[$tid] + 1;
            if ($candidatas === []) {
                if ($obrigatorio) {
                    return null;
                }
                continue;
            }
            usort($candidatas, static function (int $a, int $b) use ($rest, $tid): int {
                return $rest[$tid][$b] <=> $rest[$tid][$a];
            });
            if (!$obrigatorio && random_int(0, 2) === 0) {
                continue;
            }
            $faixa = array_slice($candidatas, 0, min(4, count($candidatas)));
            $escolha = $faixa[array_rand($faixa)];
            $rest[$tid][$escolha]--;
            $usados[$escolha] = true;
            $celulas[$tid][$chave] = $escolha;
        }
    }

    $umaFolgaPorDia = array_sum($carga) === 5 * ($periodos - 1);
    $grade = [];
    foreach ($turmaIds as $tid) {
        if (array_sum($rest[$tid]) > 0) {
            return null;
        }
        $porDia = array_fill(0, 5, 0);
        $linhas = [];
        foreach ($celulas[$tid] as $chave => $mid) {
            [$dia, $periodo] = array_map('intval', explode('-', $chave));
            $porDia[$dia]++;
            $linhas[] = [$dia, $periodo, $mid];
        }
        if ($umaFolgaPorDia) {
            foreach ($porDia as $aulasNoDia) {
                if ($aulasNoDia !== $periodos - 1) {
                    return null;
                }
            }
        }
        $grade[$tid] = $linhas;
    }

    return $grade;
}

final class SeedGradeProfessoresEduca
{
    /** @var list<string> */
    private array $nomes = [
        'Marcos Tavares', 'Lúcia Prado', 'Hugo Sampaio', 'Natália Correia', 'Érica Fontes',
        'Camila Rocha', 'André Pacheco', 'Renata Blanco', 'Paulo Vieira', 'Beatriz Nogueira',
        'Helena Duarte', 'Clara Mendes', 'Diego Martins', 'Carla Nunes', 'Rafael Queiroz',
        'Patrícia Lemos', 'Bruno Farias', 'Juliana Pires', 'Thiago Moura', 'Aline Barros',
        'Fernando Cruz', 'Vanessa Lopes', 'Gustavo Reis', 'Marina Teixeira', 'Leandro Campos',
        'Sofia Aragão', 'Igor Bentes', 'Larissa Cunha', 'Otávio Dias', 'Priscila Esteves',
        'Caio Furtado', 'Bianca Galvão', 'Henrique Ivo', 'Débora Klein', 'Vitor Lacerda',
        'Amanda Nunes', 'Samuel Oliveira', 'Tatiane Pires', 'Wesley Ramos', 'Elisa Siqueira',
    ];

    /** @var list<string> */
    private array $sobrenomesExtra = [
        'Almeida', 'Barbosa', 'Cardoso', 'Dantas', 'Freitas', 'Gomes', 'Holanda', 'Jesus',
        'Klein', 'Lima', 'Machado', 'Nascimento', 'Oliveira', 'Pereira', 'Queiroz', 'Ribeiro',
    ];

    public function __construct(private Database $db)
    {
    }

    public function executar(): int
    {
        $grupos = $this->definirGrupos();
        $resolvidas = [];
        foreach ($grupos as $chave => $grupo) {
            println('Resolvendo grade ' . $grupo['rotulo'] . ' (' . count($grupo['turmas']) . ' turmas)...');
            $grade = resolverGrade(
                array_column($grupo['turmas'], 'id'),
                $grupo['carga_qtd'],
                count($grupo['horarios']),
                $grupo['forcar']
            );
            if ($grade === null) {
                fail('Não foi possível montar a grade de ' . $grupo['rotulo'] . ' sem choque de professor.');
            }
            $resolvidas[$chave] = $grade;
            println('  ok');
        }

        $this->db->beginTransaction();
        try {
            $salas = $this->criarSalasEAmbientes($grupos);
            $professores = 0;
            $aulas = 0;
            foreach ($grupos as $chave => $grupo) {
                $docentes = $this->criarProfessores($chave, $grupo);
                $professores += count($docentes);
                $aulas += $this->gravarGrade($grupo, $resolvidas[$chave], $docentes, $salas);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }

        $this->conferir();
        println('');
        println('Professores: ' . $professores);
        println('Aulas na grade: ' . $aulas);
        println('Senha dos professores: ' . SENHA);
        println('Ensino médio: 07:00–09:30, intervalo até 09:50, depois até 12:20.');

        return 0;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function definirGrupos(): array
    {
        $turmas = $this->db->fetchAll(
            'SELECT id, nome, serie_id FROM turmas WHERE ano_letivo = :ano AND ativo = 1 ORDER BY serie_id, nome',
            ['ano' => ANO]
        ) ?: [];
        if ($turmas === []) {
            fail('Nenhuma turma ativa em ' . ANO . '.');
        }

        $horariosEm = [
            ['07:00:00', '07:50:00'],
            ['07:50:00', '08:40:00'],
            ['08:40:00', '09:30:00'],
            ['09:50:00', '10:40:00'],
            ['10:40:00', '11:30:00'],
            ['11:30:00', '12:20:00'],
        ];
        $horariosCinco = [
            ['07:00:00', '07:50:00'],
            ['07:50:00', '08:40:00'],
            ['08:40:00', '09:30:00'],
            ['09:50:00', '10:40:00'],
            ['10:40:00', '11:30:00'],
        ];
        $horariosSeisCurtos = [
            ['07:00:00', '07:45:00'],
            ['07:45:00', '08:30:00'],
            ['08:30:00', '09:15:00'],
            ['09:35:00', '10:20:00'],
            ['10:20:00', '11:05:00'],
            ['11:05:00', '11:50:00'],
        ];

        $definicoes = [
            'em' => ['rotulo' => 'Ensino Médio', 'series' => [5, 6, 7], 'horarios' => $horariosEm, 'forcar' => [0, 5], 'bloco' => 'A'],
            'af67' => ['rotulo' => '6º e 7º ano', 'series' => [14, 15], 'horarios' => $horariosCinco, 'forcar' => [], 'bloco' => 'B'],
            'af89' => ['rotulo' => '8º e 9º ano', 'series' => [16, 17], 'horarios' => $horariosCinco, 'forcar' => [], 'bloco' => 'B'],
            'ai13' => ['rotulo' => '1º ao 3º ano', 'series' => [9, 10, 11], 'horarios' => $horariosSeisCurtos, 'forcar' => [], 'bloco' => 'C'],
            'ai45' => ['rotulo' => '4º e 5º ano', 'series' => [12, 13], 'horarios' => $horariosCinco, 'forcar' => [], 'bloco' => 'C'],
        ];

        $porSerie = [];
        foreach ($turmas as $turma) {
            $porSerie[(int) $turma['serie_id']][] = [
                'id' => (int) $turma['id'],
                'nome' => (string) $turma['nome'],
            ];
        }

        $grupos = [];
        foreach ($definicoes as $chave => $def) {
            $lista = [];
            foreach ($def['series'] as $serieId) {
                foreach ($porSerie[$serieId] ?? [] as $turma) {
                    $lista[] = $turma + ['serie_id' => $serieId];
                }
            }
            if ($lista === []) {
                fail('Sem turmas para ' . $def['rotulo'] . '.');
            }
            $carga = $this->cargaDaSerie((int) $def['series'][0]);
            foreach (array_slice($def['series'], 1) as $serieId) {
                $outra = $this->cargaDaSerie((int) $serieId);
                if ($outra !== $carga) {
                    fail('A carga de ' . $def['rotulo'] . ' não é a mesma em todas as séries. A grade única desse grupo não fecha.');
                }
            }
            $qtd = [];
            $nomes = [];
            foreach ($carga as $mid => $meta) {
                $qtd[$mid] = $meta['aulas'];
                $nomes[$mid] = $meta['nome'];
            }
            $grupos[$chave] = [
                'rotulo' => $def['rotulo'],
                'horarios' => $def['horarios'],
                'forcar' => $def['forcar'],
                'bloco' => $def['bloco'],
                'turmas' => $lista,
                'carga_qtd' => $qtd,
                'materias' => $nomes,
            ];
        }

        return $grupos;
    }

    /**
     * @return array<int,array{nome:string,aulas:int}>
     */
    private function cargaDaSerie(int $serieId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.materia_id, m.nome, c.aulas_semana
             FROM matrizes_curriculares mc
             JOIN matrizes_curriculares_componentes c ON c.matriz_id = mc.id
             JOIN materias m ON m.id = c.materia_id
             WHERE mc.serie_id = :serie
             ORDER BY c.materia_id',
            ['serie' => $serieId]
        ) ?: [];
        if ($rows === []) {
            fail('Série ' . $serieId . ' está sem matriz curricular.');
        }
        $carga = [];
        foreach ($rows as $row) {
            $carga[(int) $row['materia_id']] = [
                'nome' => (string) $row['nome'],
                'aulas' => (int) $row['aulas_semana'],
            ];
        }
        return $carga;
    }

    /**
     * @param array<string,array<string,mixed>> $grupos
     * @return array<int,int> turma_id => sala_id
     */
    private function criarSalasEAmbientes(array $grupos): array
    {
        $mapa = [];
        $n = 1;
        foreach ($grupos as $grupo) {
            foreach ($grupo['turmas'] as $turma) {
                $codigo = sprintf('EDUCA-SALA-%02d', $n);
                $salaId = $this->upsertLocal([
                    'codigo' => $codigo,
                    'nome' => 'Sala ' . $turma['nome'],
                    'tipo' => 'sala',
                    'capacidade' => 35,
                    'bloco' => $grupo['bloco'],
                    'andar' => '1',
                    'responsavel_nome' => 'Coordenação',
                ]);
                $this->db->query(
                    'UPDATE turmas SET sala_padrao_id = :sala WHERE id = :id',
                    ['sala' => $salaId, 'id' => $turma['id']]
                );
                $mapa[$turma['id']] = $salaId;
                $n++;
            }
        }

        $ambientes = [
            ['EDUCA-LAB-CIEN', 'Laboratório de Ciências', 'laboratorio', 30, 'D'],
            ['EDUCA-LAB-INFO', 'Laboratório de Informática', 'laboratorio', 30, 'D'],
            ['EDUCA-QUADRA', 'Quadra poliesportiva', 'quadra', 80, 'D'],
            ['EDUCA-BIBLIO', 'Biblioteca', 'biblioteca', 40, 'D'],
            ['EDUCA-AUDITORIO', 'Auditório', 'outro', 120, 'D'],
        ];
        foreach ($ambientes as $amb) {
            $this->upsertLocal([
                'codigo' => $amb[0],
                'nome' => $amb[1],
                'tipo' => $amb[2],
                'capacidade' => $amb[3],
                'bloco' => $amb[4],
                'andar' => 'Térreo',
                'responsavel_nome' => 'Coordenação',
            ]);
        }
        println('Salas de aula: ' . count($mapa) . ' · ambientes: ' . count($ambientes));

        return $mapa;
    }

    /**
     * @param array{codigo:string,nome:string,tipo:string,capacidade:int,bloco:string,andar:string,responsavel_nome:string} $dados
     */
    private function upsertLocal(array $dados): int
    {
        $exist = $this->db->fetch(
            'SELECT id FROM school_locations WHERE codigo = :c LIMIT 1',
            ['c' => $dados['codigo']]
        );
        if ($exist) {
            $this->db->query(
                'UPDATE school_locations
                 SET nome = :nome, tipo = :tipo, capacidade = :cap, bloco = :bloco, andar = :andar,
                     responsavel_nome = :resp, ativo = 1
                 WHERE id = :id',
                [
                    'nome' => $dados['nome'],
                    'tipo' => $dados['tipo'],
                    'cap' => $dados['capacidade'],
                    'bloco' => $dados['bloco'],
                    'andar' => $dados['andar'],
                    'resp' => $dados['responsavel_nome'],
                    'id' => (int) $exist['id'],
                ]
            );
            return (int) $exist['id'];
        }

        return (int) $this->db->insert(
            'INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, responsavel_nome, ativo)
             VALUES (:codigo, :nome, :tipo, :cap, :bloco, :andar, :resp, 1)',
            [
                'codigo' => $dados['codigo'],
                'nome' => $dados['nome'],
                'tipo' => $dados['tipo'],
                'cap' => $dados['capacidade'],
                'bloco' => $dados['bloco'],
                'andar' => $dados['andar'],
                'resp' => $dados['responsavel_nome'],
            ]
        );
    }

    /**
     * @param array<string,mixed> $grupo
     * @return array<int,int> materia_id => professor_id
     */
    private function criarProfessores(string $chave, array $grupo): array
    {
        $turmaIds = array_column($grupo['turmas'], 'id');
        $hash = password_hash(SENHA, PASSWORD_DEFAULT);
        $mapa = [];
        $i = 0;
        foreach ($grupo['materias'] as $mid => $nomeMateria) {
            $slug = $this->slugMateria((string) $nomeMateria);
            $codigo = strtoupper($chave) . '-' . $slug;
            $email = strtolower($chave) . '.' . strtolower($slug) . '@educa.local';
            $nome = $this->nomeProfessor($chave, (string) $nomeMateria, $i);
            $i++;
            $materiasJson = json_encode([$nomeMateria], JSON_UNESCAPED_UNICODE);
            $turmasJson = json_encode(array_map('intval', $turmaIds));

            $exist = $this->db->fetch('SELECT id FROM professores WHERE codigo_prof = :c LIMIT 1', ['c' => $codigo]);
            if ($exist) {
                $id = (int) $exist['id'];
                $this->db->query(
                    'UPDATE professores
                     SET nome = :nome, email = :email, materias = :materias, turmas = :turmas, ativo = 1, pagante = 1
                     WHERE id = :id',
                    [
                        'nome' => $nome,
                        'email' => $email,
                        'materias' => $materiasJson,
                        'turmas' => $turmasJson,
                        'id' => $id,
                    ]
                );
            } else {
                $id = (int) $this->db->insert(
                    'INSERT INTO professores (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante, password)
                     VALUES (:nome, :email, :senha, :codigo, :materias, :turmas, 1, 1, :password)',
                    [
                        'nome' => $nome,
                        'email' => $email,
                        'senha' => $hash,
                        'codigo' => $codigo,
                        'materias' => $materiasJson,
                        'turmas' => $turmasJson,
                        'password' => '',
                    ]
                );
            }
            $mapa[(int) $mid] = $id;
        }
        println($grupo['rotulo'] . ': ' . count($mapa) . ' professores');

        return $mapa;
    }

    private function slugMateria(string $nome): string
    {
        $fixos = [
            'Gramática' => 'GRAM',
            'Interpretação de texto' => 'INT',
            'Literatura' => 'LIT',
            'Matemática' => 'MAT',
            'Língua Inglesa' => 'ING',
            'Arte' => 'ART',
            'Educação Física' => 'EDF',
            'Biologia' => 'BIO',
            'Física' => 'FIS',
            'Química' => 'QUI',
            'História' => 'HIS',
            'Geografia' => 'GEO',
            'Filosofia' => 'FIL',
            'Sociologia' => 'SOC',
            'Língua Portuguesa' => 'POR',
        ];
        if (isset($fixos[$nome])) {
            return $fixos[$nome];
        }
        $base = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: $nome);
        $base = preg_replace('/[^A-Z]/', '', $base) ?: 'MAT';
        return substr($base, 0, 8);
    }

    private function nomeProfessor(string $grupo, string $materia, int $indice): string
    {
        $fixosEm = [
            'Matemática' => 'Marcos Tavares',
            'Gramática' => 'Helena Duarte',
            'Interpretação de texto' => 'Clara Mendes',
            'Literatura' => 'Beatriz Nogueira',
            'Língua Inglesa' => 'Camila Rocha',
            'Arte' => 'Lúcia Prado',
            'Educação Física' => 'Érica Fontes',
            'Biologia' => 'Natália Correia',
            'Física' => 'André Pacheco',
            'Química' => 'Renata Blanco',
            'História' => 'Hugo Sampaio',
            'Geografia' => 'Paulo Vieira',
            'Filosofia' => 'Diego Martins',
            'Sociologia' => 'Carla Nunes',
        ];
        if ($grupo === 'em' && isset($fixosEm[$materia])) {
            return $fixosEm[$materia];
        }
        $n = $indice + (abs(crc32($grupo)) % 17);
        $nome = $this->nomes[$n % count($this->nomes)];
        $extra = $this->sobrenomesExtra[($n * 3) % count($this->sobrenomesExtra)];
        if ($grupo !== 'em') {
            return $nome . ' ' . $extra;
        }
        return $nome;
    }

    /**
     * @param array<string,mixed> $grupo
     * @param array<int,list<array{0:int,1:int,2:int}>> $grade
     * @param array<int,int> $docentes
     * @param array<int,int> $salas
     */
    private function gravarGrade(array $grupo, array $grade, array $docentes, array $salas): int
    {
        $ids = array_column($grupo['turmas'], 'id');
        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $chave = 't' . $i;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $id;
        }
        $this->db->query(
            'DELETE FROM grade_horaria WHERE turma_id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        $aulas = 0;
        $horarios = $grupo['horarios'];
        foreach ($grade as $tid => $linhas) {
            if (!isset($salas[$tid])) {
                fail('Turma ' . $tid . ' ficou sem sala.');
            }
            foreach ($linhas as $linha) {
                [$dia, $periodo, $mid] = $linha;
                $this->db->insert(
                    "INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
                     VALUES (:dia, :de, :ate, :turma, :prof, :mat, 'manha')",
                    [
                        'dia' => $dia + 1,
                        'de' => $horarios[$periodo][0],
                        'ate' => $horarios[$periodo][1],
                        'turma' => $tid,
                        'prof' => $docentes[$mid],
                        'mat' => $mid,
                    ]
                );
                $aulas++;
            }
        }

        return $aulas;
    }

    private function conferir(): void
    {
        $choqueProf = $this->db->fetch(
            'SELECT professor_id, dia_semana, horario_de, COUNT(*) c
             FROM grade_horaria
             GROUP BY professor_id, dia_semana, horario_de
             HAVING c > 1
             LIMIT 1'
        );
        if ($choqueProf) {
            fail('Professor em duas turmas no mesmo horário: ' . json_encode($choqueProf));
        }

        $choqueTurma = $this->db->fetch(
            'SELECT turma_id, dia_semana, horario_de, COUNT(*) c
             FROM grade_horaria
             GROUP BY turma_id, dia_semana, horario_de
             HAVING c > 1
             LIMIT 1'
        );
        if ($choqueTurma) {
            fail('Turma com duas aulas no mesmo horário.');
        }

        $extremos = $this->db->fetchAll(
            "SELECT t.nome,
                    SUM(g.horario_de = '07:00:00') AS inicios,
                    SUM(g.horario_ate = '12:20:00') AS fins
             FROM turmas t
             JOIN grade_horaria g ON g.turma_id = t.id
             WHERE t.ano_letivo = :ano AND t.serie_id IN (5, 6, 7)
             GROUP BY t.id, t.nome",
            ['ano' => ANO]
        ) ?: [];
        if (count($extremos) !== 6) {
            fail('A grade do ensino médio não cobriu as 6 turmas.');
        }
        foreach ($extremos as $row) {
            if ((int) $row['inicios'] < 5 || (int) $row['fins'] < 5) {
                fail($row['nome'] . ' não começa 07:00 ou não termina 12:20 em todos os dias.');
            }
        }

        $intervalo = $this->db->fetch(
            "SELECT COUNT(*) c FROM grade_horaria g
             JOIN turmas t ON t.id = g.turma_id
             WHERE t.ano_letivo = :ano AND t.serie_id IN (5, 6, 7)
               AND g.horario_de < '09:50:00' AND g.horario_ate > '09:30:00'",
            ['ano' => ANO]
        );
        if ((int) ($intervalo['c'] ?? 0) > 0) {
            fail('Há aula do ensino médio em cima do intervalo das 09:30.');
        }

        $cargaErrada = $this->db->fetch(
            'SELECT t.nome, m.nome materia, c.aulas_semana, COUNT(g.id) AS aulas
             FROM turmas t
             JOIN matrizes_curriculares mc ON mc.serie_id = t.serie_id
             JOIN matrizes_curriculares_componentes c ON c.matriz_id = mc.id
             JOIN materias m ON m.id = c.materia_id
             LEFT JOIN grade_horaria g ON g.turma_id = t.id AND g.materia_id = c.materia_id
             WHERE t.ano_letivo = :ano AND t.ativo = 1
             GROUP BY t.id, t.nome, m.nome, c.aulas_semana
             HAVING aulas <> c.aulas_semana
             LIMIT 1',
            ['ano' => ANO]
        );
        if ($cargaErrada) {
            fail('Carga diferente da matriz: ' . json_encode($cargaErrada, JSON_UNESCAPED_UNICODE));
        }
    }
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail('Abortado: DB_HOST=' . $host . ' não parece local.');
}

try {
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    Database::setCurrentInstance(Database::createFromPdo($pdo, [
        'host' => $host,
        'port' => $port,
        'name' => TENANT_DB,
        'user' => $dbUser,
        'pass' => $dbPass,
    ]));
    exit((new SeedGradeProfessoresEduca(Database::getInstance()))->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
