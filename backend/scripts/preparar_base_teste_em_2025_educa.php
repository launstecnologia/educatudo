<?php
/**
 * Limpa o Colégio Educa e monta base de teste: EM 2025, 6 turmas (1A–3B),
 * 40 alunos cada, LP com Literatura / Interpretação de texto / Gramática.
 *
 * NÃO gera notas nem provas — só cadastro, matrícula e estrutura acadêmica.
 *
 * Uso (container PHP):
 *   php scripts/preparar_base_teste_em_2025_educa.php
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

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Models/User/Teacher.php';
require_once $basePath . '/app/Models/User/StudentComplementaryRecord.php';
require_once $basePath . '/app/Models/Education/ClassRoom.php';
require_once $basePath . '/app/Models/Education/ComponenteCurricular.php';
require_once $basePath . '/app/Services/MatrizCurricularService.php';
require_once $basePath . '/app/Services/AlunoMovimentacaoService.php';
require_once $basePath . '/app/Modulos/agrupamentos-componentes/Models/AgrupamentoComponente.php';

const TENANT_DB = 'educatudo_educa';
const SENHA = 'Teste@123';
const ANO = 2025;
const ALUNOS_POR_TURMA = 40;
const DATA_MATRICULA = '2025-02-01';

const SERIES = [
    'em1' => ['nome' => '1ª Série EM', 'letra_serie' => '1', 'idade' => 15],
    'em2' => ['nome' => '2ª Série EM', 'letra_serie' => '2', 'idade' => 16],
    'em3' => ['nome' => '3ª Série EM', 'letra_serie' => '3', 'idade' => 17],
];
const LETRAS = ['A', 'B'];

const NOMES_F = [
    'Ana', 'Beatriz', 'Camila', 'Daniela', 'Elisa', 'Fernanda', 'Gabriela', 'Helena',
    'Isabela', 'Júlia', 'Karina', 'Larissa', 'Marina', 'Natália', 'Olivia', 'Patrícia',
    'Rafaela', 'Sofia', 'Talita', 'Vitória', 'Yasmin', 'Amanda', 'Bruna', 'Carolina',
    'Débora', 'Eduarda', 'Flávia', 'Giovana', 'Letícia', 'Manuela', 'Nicole', 'Priscila',
];
const NOMES_M = [
    'Arthur', 'Bruno', 'Caio', 'Diego', 'Eduardo', 'Felipe', 'Gustavo', 'Henrique',
    'Igor', 'João', 'Kaique', 'Lucas', 'Mateus', 'Nicolas', 'Otávio', 'Pedro',
    'Rafael', 'Samuel', 'Thiago', 'Vitor', 'André', 'Bernardo', 'Carlos', 'Daniel',
    'Enzo', 'Fábio', 'Guilherme', 'Hugo', 'Leonardo', 'Marcelo', 'Paulo', 'Rodrigo',
];
const SOBRENOMES = [
    'Almeida', 'Barbosa', 'Cardoso', 'Dias', 'Fernandes', 'Gomes', 'Lima', 'Mendes',
    'Nogueira', 'Oliveira', 'Pereira', 'Rocha', 'Silva', 'Teixeira', 'Vieira', 'Castro',
    'Araujo', 'Batista', 'Campos', 'Duarte', 'Ferreira', 'Moreira', 'Nunes', 'Pinto',
    'Ribeiro', 'Santos', 'Souza', 'Cavalcanti', 'Moraes', 'Carvalho', 'Freitas', 'Azevedo',
    'Correia', 'Machado', 'Monteiro', 'Reis', 'Siqueira', 'Tavares', 'Vargas', 'Xavier',
];
const NOMES_MAE = ['Ana', 'Carla', 'Elisa', 'Gabriela', 'Helena', 'Isabela', 'Karina', 'Marina', 'Olivia', 'Rafaela', 'Talita', 'Yasmin', 'Beatriz', 'Lúcia'];
const NOMES_PAI = ['Bruno', 'Diego', 'Eduardo', 'Felipe', 'Henrique', 'João', 'Lucas', 'Marcos', 'Nicolas', 'Pedro', 'Roberto', 'Samuel', 'Vitor', 'Caio'];
const RUAS = ['Rua das Palmeiras', 'Avenida Paulista', 'Rua Augusta', 'Rua da Consolação', 'Rua Vergueiro', 'Avenida Rebouças', 'Rua Pamplona', 'Rua Bela Cintra', 'Rua Oscar Freire', 'Avenida Brigadeiro Luís Antônio'];
const BAIRROS = ['Centro', 'Bela Vista', 'Consolação', 'Jardins', 'Moema', 'Perdizes', 'Pinheiros', 'Vila Mariana'];
const CORES = ['Parda', 'Branca', 'Parda', 'Branca', 'Parda', 'Preta', 'Branca', 'Parda', 'Amarela', 'Branca'];
const SANGUE = ['A+', 'O+', 'B+', 'A-', 'O+', 'O-', 'AB+', 'A+', 'O+', 'B+'];
const PLANOS = ['Unimed', 'SulAmérica', 'Bradesco Saúde', 'Amil', null, null];

const TABELAS_PROTEGIDAS = [
    'materias', 'professores', 'unidades', 'curso', 'serie', 'cursos',
    'provas_tipos_avaliacao', 'quadros_notas', 'quadros_notas_colunas',
    'regras_academicas', 'regras_academicas_itens',
    'admin', 'admins', 'usuarios', 'admin_usuarios',
    'school_locations', 'school_units',
    'bncc_habilidades', 'bncc_componentes', 'bncc_objetos', 'bncc_unidades_tematicas',
    'ocorrencias_categorias',
    'calendario_letivo', 'calendario_letivo_tipos',
    'matrizes_curriculares', 'matrizes_curriculares_componentes',
    'agrupamentos_componentes', 'agrupamentos_componentes_itens',
    'boletins', 'boletim_modelos',
    'migrations', 'schema_migrations',
    'modulos', 'modulos_escola', 'feature_flags',
    'configuracoes', 'configuracoes_escola', 'school_settings',
    'jornadas', 'jornadas_modulos', 'jornadas_aulas',
];

const CARGA_EM = [
    'Gramática' => 2,
    'Interpretação de texto' => 1,
    'Literatura' => 1,
    'Matemática' => 4,
    'Língua Inglesa' => 2,
    'Arte' => 1,
    'Educação Física' => 2,
    'Biologia' => 2,
    'Física' => 2,
    'Química' => 2,
    'História' => 2,
    'Geografia' => 2,
    'Filosofia' => 1,
    'Sociologia' => 1,
];

const FILHOS_LP = [
    ['nome' => 'Literatura', 'codigo' => 'LP-LIT', 'sigla' => 'LIT', 'ordem' => 12],
    ['nome' => 'Interpretação de texto', 'codigo' => 'LP-INT', 'sigla' => 'INT', 'ordem' => 13],
    ['nome' => 'Gramática', 'codigo' => 'LP-GRA', 'sigla' => 'GRA', 'ordem' => 14],
];

/** Catálogo BNCC do EM pedido para o teste — o restante sai da escola. */
const COMPONENTES_EM = [
    'Língua Portuguesa',
    'Literatura',
    'Interpretação de texto',
    'Gramática',
    'Matemática',
    'Língua Inglesa',
    'Arte',
    'Educação Física',
    'Biologia',
    'Física',
    'Química',
    'História',
    'Geografia',
    'Filosofia',
    'Sociologia',
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

function gerarCpf(int $seed): string
{
    $n = [];
    $x = abs($seed) + 104729;
    for ($i = 0; $i < 9; $i++) {
        $x = abs(($x * 1103515245 + 12345) % 2147483647);
        $n[] = $x % 10;
    }
    if (count(array_unique($n)) === 1) {
        $n[8] = ($n[8] + 3) % 10;
    }
    $soma = 0;
    for ($i = 0, $p = 10; $i < 9; $i++, $p--) {
        $soma += $n[$i] * $p;
    }
    $d1 = ($soma * 10) % 11;
    $n[] = $d1 === 10 ? 0 : $d1;
    $soma = 0;
    for ($i = 0, $p = 11; $i < 10; $i++, $p--) {
        $soma += $n[$i] * $p;
    }
    $d2 = ($soma * 10) % 11;
    $n[] = $d2 === 10 ? 0 : $d2;
    return implode('', $n);
}

function slugify(string $texto): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $s = strtolower((string) ($ascii !== false ? $ascii : $texto));
    $s = preg_replace('/[^a-z0-9]+/', '.', $s) ?? $s;
    return trim($s, '.');
}

final class PrepararBaseTesteEm2025Educa
{
    private $db;
    private Teacher $teachers;
    private ClassRoom $turmasModel;
    private ComponenteCurricular $componentes;
    private MatrizCurricularService $matrizes;
    private \App\Services\AlunoMovimentacaoService $movimentacao;
    private StudentComplementaryRecord $ficha;
    private \App\Modulos\AgrupamentosComponentes\Models\AgrupamentoComponente $agrupamentos;

    private string $hash;
    private int $cursoEmId = 0;
    private int $unidadeEmId = 0;
    private int $anoLetivoId = 0;
    private int $lpId = 0;
    /** @var array<string,int> */
    private array $seriesIds = [];
    /** @var array<string,int> */
    private array $matrizPorSerie = [];
    /** @var array<string,int> */
    private array $salasIds = [];
    /** @var array<string,int> "em1:A" => turma_id */
    private array $turmaIds = [];
    /** @var array<string,array<string,mixed>> */
    private array $materias = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->teachers = new Teacher();
        $this->turmasModel = new ClassRoom();
        $this->componentes = new ComponenteCurricular();
        $this->matrizes = new MatrizCurricularService();
        $this->movimentacao = new \App\Services\AlunoMovimentacaoService();
        $this->ficha = new StudentComplementaryRecord();
        $this->agrupamentos = new \App\Modulos\AgrupamentosComponentes\Models\AgrupamentoComponente();
        $this->hash = password_hash(SENHA, PASSWORD_DEFAULT);
    }

    public function executar(): int
    {
        println('== Base de teste EM 2025 — Colégio Educa ==');
        $this->limparOperacional();
        $this->garantirAnoLetivo();
        $this->garantirCalendarioBimestres();
        $this->carregarCursoSeriesUnidade();
        $this->montarLinguaPortuguesa();
        $this->atualizarMatrizesEm();
        $this->garantirAgrupamentoLp();
        $this->manterSoComponentesEm();
        $this->manterSoCursoEm();
        $this->carregarSalas();
        $this->criarTurmas();
        $this->reapontarProfessores();
        $this->criarAlunos();
        $this->imprimirResumo();
        return 0;
    }

    private function limparOperacional(): void
    {
        println('  limpando lançamentos, frequência, boletins, alunos e turmas…');
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $excetoAlunosTurmas = array_merge(TABELAS_PROTEGIDAS, ['alunos', 'turmas']);
        foreach (['aluno_id', 'student_id', 'id_aluno'] as $coluna) {
            foreach ($this->tabelasComColuna($coluna) as $tabela) {
                if (in_array($tabela, $excetoAlunosTurmas, true)) {
                    continue;
                }
                $this->esvaziarTabela($tabela);
            }
        }
        foreach (['turma_id', 'id_turma'] as $coluna) {
            foreach ($this->tabelasComColuna($coluna) as $tabela) {
                if (in_array($tabela, $excetoAlunosTurmas, true)) {
                    continue;
                }
                $this->esvaziarTabela($tabela);
            }
        }

        foreach (['provas_blocos', 'provas_blocos_quadros_notas', 'provas_blocos_grupos_regras', 'diario_aulas', 'diario_frequencias', 'diario_fechamentos', 'grade_horaria', 'fechamento_periodo', 'conselho_sessoes', 'faltas_eventos', 'presenca_eventos', 'boletim_fichas', 'boletim_regras', 'boletim_componentes', 'boletim_resultados_gerados', 'boletim_geracoes', 'boletim_log_geracoes', 'boletim_notas_manuais', 'boletim_alunos_travados', 'matricula'] as $tabela) {
            if ($this->tabelaExiste($tabela)) {
                $this->esvaziarTabela($tabela);
            }
        }

        $this->esvaziarCarteiraAlunos();
        $this->esvaziarTabela('alunos');
        $this->esvaziarTabela('turmas');
        if ($this->tabelaExiste('responsaveis')) {
            $this->esvaziarTabela('responsaveis');
        }
        if ($this->tabelaExiste('alunos_responsaveis')) {
            $this->esvaziarTabela('alunos_responsaveis');
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        println('  operacional apagado');
    }

    /** @return list<string> */
    private function tabelasComColuna(string $coluna): array
    {
        $rows = $this->db->fetchAll(
            "SELECT c.TABLE_NAME AS t
             FROM information_schema.COLUMNS c
             INNER JOIN information_schema.TABLES t
               ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
             WHERE c.TABLE_SCHEMA = DATABASE()
               AND c.COLUMN_NAME = :c
               AND t.TABLE_TYPE = 'BASE TABLE'",
            ['c' => $coluna]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $nome = (string) ($row['t'] ?? '');
            if (preg_match('/^[a-z0-9_]+$/i', $nome)) {
                $out[] = $nome;
            }
        }
        return $out;
    }

    private function tabelaExiste(string $tabela): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela)) {
            return false;
        }
        return $this->db->fetch('SHOW TABLES LIKE ?', [$tabela]) !== false;
    }

    private function esvaziarTabela(string $tabela): void
    {
        if (!$this->tabelaExiste($tabela)) {
            return;
        }
        try {
            $this->db->query('TRUNCATE TABLE `' . $tabela . '`');
        } catch (Throwable $e) {
            try {
                $this->db->query('DELETE FROM `' . $tabela . '`');
            } catch (Throwable $e2) {
                println('  aviso: não esvaziou ' . $tabela . ': ' . $e2->getMessage());
            }
        }
    }

    private function esvaziarCarteiraAlunos(): void
    {
        if ($this->tabelaExiste('carteira_movimentacoes')) {
            try {
                $this->db->query("DELETE FROM carteira_movimentacoes WHERE user_type = 'aluno'");
            } catch (Throwable $e) {
                // ignore
            }
        }
        if ($this->tabelaExiste('carteira_usuarios')) {
            try {
                $this->db->query("DELETE FROM carteira_usuarios WHERE user_type = 'aluno'");
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    private function garantirAnoLetivo(): void
    {
        $this->db->query('UPDATE ano_letivo SET ativo = 0');
        $row = $this->db->fetch('SELECT * FROM ano_letivo WHERE ano = :a LIMIT 1', ['a' => ANO]);
        if (!$row) {
            $this->anoLetivoId = (int) $this->db->insert(
                'INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo) VALUES (:ano, :ini, :fim, 1)',
                ['ano' => ANO, 'ini' => ANO . '-02-01', 'fim' => ANO . '-12-15']
            );
        } else {
            $this->anoLetivoId = (int) $row['id'];
            $this->db->query(
                'UPDATE ano_letivo SET data_inicio = :ini, data_fim = :fim, ativo = 1 WHERE id = :id',
                ['ini' => ANO . '-02-01', 'fim' => ANO . '-12-15', 'id' => $this->anoLetivoId]
            );
        }
        try {
            $this->db->query('DELETE FROM ano_letivo WHERE ano <> :a', ['a' => ANO]);
        } catch (Throwable $e) {
            println('  aviso: outros anos letivos ficaram inativos (FK): ' . $e->getMessage());
        }
        println('  ano letivo ' . ANO . ' ativo (id=' . $this->anoLetivoId . ')');
    }

    private function garantirCalendarioBimestres(): void
    {
        if (!$this->tabelaExiste('calendario_letivo')) {
            return;
        }
        $exist = $this->db->fetch('SELECT id FROM calendario_letivo WHERE ano = :a LIMIT 1', ['a' => ANO]);
        $calId = $exist ? (int) $exist['id'] : (int) $this->db->insert(
            'INSERT INTO calendario_letivo (ano, dias_meta, carga_horaria_meta, observacao) VALUES (:a, 200, 800, :o)',
            ['a' => ANO, 'o' => 'Calendário EM 2025 — base de teste']
        );
        $bimestres = [
            [ANO . '-01-01', ANO . '-03-31', '1º Bimestre'],
            [ANO . '-04-01', ANO . '-06-30', '2º Bimestre'],
            [ANO . '-07-01', ANO . '-09-30', '3º Bimestre'],
            [ANO . '-10-01', ANO . '-12-31', '4º Bimestre'],
        ];
        foreach ($bimestres as $ev) {
            $ja = $this->db->fetch(
                'SELECT id FROM calendario_letivo_eventos WHERE calendario_id = :c AND descricao = :d LIMIT 1',
                ['c' => $calId, 'd' => $ev[2]]
            );
            if ($ja) {
                $this->db->query(
                    'UPDATE calendario_letivo_eventos SET data_inicio = :ini, data_fim = :fim, tipo = :tipo WHERE id = :id',
                    ['ini' => $ev[0], 'fim' => $ev[1], 'tipo' => 'evento', 'id' => (int) $ja['id']]
                );
                continue;
            }
            $this->db->insert(
                'INSERT INTO calendario_letivo_eventos
                    (calendario_id, data_inicio, data_fim, tipo, descricao, visivel_aluno, visivel_professor, visivel_pais)
                 VALUES (:c, :ini, :fim, :tipo, :desc, 1, 1, 1)',
                ['c' => $calId, 'ini' => $ev[0], 'fim' => $ev[1], 'tipo' => 'evento', 'desc' => $ev[2]]
            );
        }
        println('  calendário 2025 com 4 bimestres');
    }

    private function carregarCursoSeriesUnidade(): void
    {
        $curso = $this->db->fetch("SELECT id FROM curso WHERE nome LIKE :n LIMIT 1", ['n' => '%Médio%']);
        if (!$curso) {
            fail('Curso Ensino Médio não encontrado. Rode init_colegio_educa.php.');
        }
        $this->cursoEmId = (int) $curso['id'];
        foreach (SERIES as $chave => $meta) {
            $row = $this->db->fetch(
                'SELECT id FROM serie WHERE curso_id = :c AND nome = :n LIMIT 1',
                ['c' => $this->cursoEmId, 'n' => $meta['nome']]
            );
            if (!$row) {
                fail('Série não encontrada: ' . $meta['nome']);
            }
            $this->seriesIds[$chave] = (int) $row['id'];
        }
        $em = $this->db->fetch("SELECT id FROM unidades WHERE nome LIKE :q ORDER BY id ASC LIMIT 1", ['q' => '%Médio%']);
        $qualquer = $this->db->fetch('SELECT id FROM unidades ORDER BY id ASC LIMIT 1');
        $this->unidadeEmId = (int) ($em['id'] ?? ($qualquer['id'] ?? 0));
    }

    private function montarLinguaPortuguesa(): void
    {
        $lp = $this->db->fetch("SELECT * FROM materias WHERE nome = :n LIMIT 1", ['n' => 'Língua Portuguesa']);
        if (!$lp) {
            fail('Componente Língua Portuguesa não encontrado.');
        }
        $this->lpId = (int) $lp['id'];
        $this->componentes->update($this->lpId, $this->dadosComponente($lp, [
            'pai_id' => null,
            'permite_avaliacao' => 0,
            'ativo' => 1,
        ]));

        foreach (FILHOS_LP as $filho) {
            $row = $this->db->fetch('SELECT * FROM materias WHERE nome = :n LIMIT 1', ['n' => $filho['nome']]);
            $base = $row ?: $lp;
            $payload = $this->dadosComponente($base, [
                'nome' => $filho['nome'],
                'codigo' => $row['codigo'] ?? $filho['codigo'],
                'sigla' => $row['sigla'] ?? $filho['sigla'],
                'pai_id' => $this->lpId,
                'tipo' => 'formacao_geral',
                'area_conhecimento' => 'linguagens',
                'etapa_medio' => 'obrigatoria',
                'permite_avaliacao' => 1,
                'permite_frequencia' => 1,
                'permite_plano_aula' => 1,
                'permite_diario' => 1,
                'ativo' => 1,
                'ordem' => $filho['ordem'],
                'descricao' => 'Desdobramento de Língua Portuguesa — ' . $filho['nome'],
            ]);
            if ($row) {
                $this->componentes->update((int) $row['id'], $payload);
            } else {
                $this->componentes->create($payload);
            }
        }
        $this->componentes->marcarPaiComoRotulo($this->lpId);

        $this->materias = [];
        foreach ($this->db->fetchAll('SELECT * FROM materias') ?: [] as $row) {
            $this->materias[(string) $row['nome']] = $row;
        }
        println('  LP (id=' . $this->lpId . ') com Literatura, Interpretação de texto e Gramática');
    }

    /**
     * @param array<string,mixed> $src
     * @param array<string,mixed> $override
     */
    private function dadosComponente(array $src, array $override = []): array
    {
        $src = array_merge($src, $override);
        $etapas = ['nao_aplica', 'obrigatoria', 'oferta'];
        $normEtapa = static function ($valor) use ($etapas): string {
            $s = (string) $valor;
            return in_array($s, $etapas, true) ? $s : 'nao_aplica';
        };
        $tipos = ['formacao_geral', 'lingua_adicional', 'eletiva', 'itinerario_formativo', 'extracurricular', 'complementar', 'outra'];
        $tipo = (string) ($src['tipo'] ?? 'formacao_geral');
        if (!in_array($tipo, $tipos, true)) {
            $tipo = 'formacao_geral';
        }
        $areas = ['linguagens', 'matematica', 'ciencias_natureza', 'ciencias_humanas', 'ensino_religioso', 'interdisciplinar', 'tecnologia', 'computacao', 'arte', 'outra'];
        $area = (string) ($src['area_conhecimento'] ?? 'linguagens');
        if (!in_array($area, $areas, true)) {
            $area = 'linguagens';
        }
        return [
            'nome' => (string) $src['nome'],
            'codigo' => (string) ($src['codigo'] ?? ''),
            'sigla' => (string) ($src['sigla'] ?? ''),
            'area_conhecimento' => $area,
            'tipo' => $tipo,
            'etapa_infantil' => $normEtapa($src['etapa_infantil'] ?? 'nao_aplica'),
            'etapa_fund_i' => $normEtapa($src['etapa_fund_i'] ?? 'nao_aplica'),
            'etapa_fund_ii' => $normEtapa($src['etapa_fund_ii'] ?? 'nao_aplica'),
            'etapa_medio' => $normEtapa($src['etapa_medio'] ?? 'obrigatoria'),
            'descricao' => (string) ($src['descricao'] ?? ''),
            'cor' => (string) ($src['cor'] ?? ''),
            'ordem' => (int) ($src['ordem'] ?? 0),
            'permite_avaliacao' => (int) ($src['permite_avaliacao'] ?? 1),
            'permite_frequencia' => (int) ($src['permite_frequencia'] ?? 1),
            'permite_plano_aula' => (int) ($src['permite_plano_aula'] ?? 1),
            'permite_diario' => (int) ($src['permite_diario'] ?? 1),
            'ativo' => (int) ($src['ativo'] ?? 1),
            'pai_id' => !empty($src['pai_id']) ? (int) $src['pai_id'] : null,
        ];
    }

    private function atualizarMatrizesEm(): void
    {
        $componentes = [];
        $ordem = 1;
        foreach (CARGA_EM as $nome => $aulas) {
            if (!isset($this->materias[$nome])) {
                fail('Componente ausente no catálogo: ' . $nome);
            }
            $componentes[] = [
                'materia_id' => (int) $this->materias[$nome]['id'],
                'aulas_semana' => $aulas,
                'obrigatorio' => 1,
                'ordem_boletim' => $ordem,
                'ordem_historico' => $ordem,
            ];
            $ordem++;
        }

        foreach (SERIES as $chave => $meta) {
            $codigo = 'EDUCA-' . strtoupper($chave);
            $exist = $this->db->fetch('SELECT id FROM matrizes_curriculares WHERE codigo = :c LIMIT 1', ['c' => $codigo]);
            $payload = [
                'nome' => 'Matriz ' . $meta['nome'] . ' — Educa',
                'codigo' => $codigo,
                'curso_id' => $this->cursoEmId,
                'serie_id' => $this->seriesIds[$chave],
                'modalidade' => 'presencial',
                'turno' => 'manha',
                'carga_horaria_anual_prevista' => '1200',
                'dias_letivos_previstos' => '200',
                'duracao_padrao_aula_minutos' => 50,
                'base_legal' => 'BNCC / LDB 9.394/96',
                'observacoes' => 'Matriz EM 2025 — LP desdobrada',
                'ativo' => 1,
                'componentes' => $componentes,
            ];
            if ($exist) {
                $res = $this->matrizes->atualizar((int) $exist['id'], $payload);
                if (empty($res['success'])) {
                    fail('Matriz ' . $chave . ': ' . ($res['error'] ?? 'erro ao atualizar'));
                }
                $this->matrizPorSerie[$chave] = (int) $exist['id'];
            } else {
                $res = $this->matrizes->criar($payload);
                if (empty($res['success'])) {
                    fail('Matriz ' . $chave . ': ' . ($res['error'] ?? 'erro ao criar'));
                }
                $this->matrizPorSerie[$chave] = (int) $res['id'];
            }
        }
        println('  matrizes EM1–EM3 com filhos da LP (sem o pai na grade)');
    }

    private function garantirAgrupamentoLp(): void
    {
        if (!$this->agrupamentos->tabelasProntas()) {
            println('  aviso: agrupamentos_componentes indisponível');
            return;
        }
        $filhos = [];
        foreach (FILHOS_LP as $filho) {
            $filhos[] = (int) $this->materias[$filho['nome']]['id'];
        }
        $exist = $this->db->fetch(
            'SELECT id FROM agrupamentos_componentes WHERE materia_rotulo_id = :id LIMIT 1',
            ['id' => $this->lpId]
        );
        $data = [
            'nome' => 'Língua Portuguesa',
            'modo' => 'media',
            'aplicar_em' => 'ambos',
            'divisor' => null,
            'materia_rotulo_id' => $this->lpId,
            'ativo' => 1,
        ];
        if ($exist) {
            $this->agrupamentos->atualizar((int) $exist['id'], $data, $filhos);
        } else {
            $this->agrupamentos->criar($data, $filhos);
        }
        println('  agrupamento de boletim: Língua Portuguesa');
    }

    public function executarSomenteCatalogo(): int
    {
        println('== Catálogo EM 2025 — só componentes do Ensino Médio ==');
        $this->carregarCursoSeriesUnidade();
        $this->montarLinguaPortuguesa();
        $this->atualizarMatrizesEm();
        $this->garantirAgrupamentoLp();
        $this->manterSoComponentesEm();
        $this->manterSoCursoEm();
        $this->limparMateriasDosProfessores();
        return 0;
    }

    private function idsComponentesEm(): array
    {
        $ids = [];
        foreach (COMPONENTES_EM as $nome) {
            $row = $this->db->fetch('SELECT id FROM materias WHERE nome = :n LIMIT 1', ['n' => $nome]);
            if (!$row) {
                fail('Componente obrigatório do EM ausente: ' . $nome);
            }
            $ids[] = (int) $row['id'];
        }
        return array_values(array_unique($ids));
    }

    private function manterSoComponentesEm(): void
    {
        $manter = $this->idsComponentesEm();
        $ph = implode(',', array_fill(0, count($manter), '?'));

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        if ($this->tabelaExiste('matrizes_curriculares')) {
            $outras = $this->db->fetchAll(
                "SELECT id FROM matrizes_curriculares
                 WHERE curso_id <> :em OR codigo NOT LIKE 'EDUCA-EM%'",
                ['em' => $this->cursoEmId]
            ) ?: [];
            foreach ($outras as $row) {
                $mid = (int) $row['id'];
                $this->db->query(
                    'DELETE FROM matrizes_curriculares_componentes WHERE matriz_id = :id',
                    ['id' => $mid]
                );
                $this->db->query('DELETE FROM matrizes_curriculares WHERE id = :id', ['id' => $mid]);
            }
            if ($outras !== []) {
                println('  matrizes fora do EM removidas: ' . count($outras));
            }
        }
        if ($this->tabelaExiste('agrupamentos_componentes')) {
            $outros = $this->db->fetchAll(
                'SELECT id FROM agrupamentos_componentes WHERE materia_rotulo_id IS NULL OR materia_rotulo_id <> :lp',
                ['lp' => $this->lpId]
            ) ?: [];
            foreach ($outros as $row) {
                $aid = (int) $row['id'];
                $this->db->query(
                    'DELETE FROM agrupamentos_componentes_itens WHERE agrupamento_id = :id',
                    ['id' => $aid]
                );
                $this->db->query('DELETE FROM agrupamentos_componentes WHERE id = :id', ['id' => $aid]);
            }
        }
        $extras = $this->db->fetchAll(
            "SELECT id, nome FROM materias WHERE id NOT IN ({$ph})",
            $manter
        ) ?: [];
        foreach ($extras as $row) {
            $this->db->query('DELETE FROM materias WHERE id = :id', ['id' => (int) $row['id']]);
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        $this->materias = [];
        foreach ($this->db->fetchAll('SELECT * FROM materias') ?: [] as $row) {
            $this->materias[(string) $row['nome']] = $row;
        }
        println('  catálogo: ' . count($this->materias) . ' componentes do EM (removidos ' . count($extras) . ')');
    }

    private function manterSoCursoEm(): void
    {
        if ($this->cursoEmId <= 0) {
            return;
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        if ($this->tabelaExiste('regras_academicas')) {
            $this->db->query(
                'DELETE FROM regras_academicas WHERE curso_id <> :em',
                ['em' => $this->cursoEmId]
            );
        }
        if ($this->tabelaExiste('serie')) {
            $this->db->query(
                'DELETE FROM serie WHERE curso_id <> :em',
                ['em' => $this->cursoEmId]
            );
        }
        if ($this->tabelaExiste('curso')) {
            $this->db->query(
                'DELETE FROM curso WHERE id <> :em',
                ['em' => $this->cursoEmId]
            );
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        $restantes = $this->db->fetchAll(
            'SELECT codigo, nome FROM matrizes_curriculares ORDER BY codigo'
        ) ?: [];
        $nomes = [];
        foreach ($restantes as $row) {
            $nomes[] = (string) $row['codigo'];
        }
        println('  matrizes restantes: ' . ($nomes === [] ? '(nenhuma)' : implode(', ', $nomes)));
    }

    private function limparMateriasDosProfessores(): void
    {
        $permitidosNome = array_fill_keys(COMPONENTES_EM, true);
        $permitidosId = [];
        foreach (COMPONENTES_EM as $nome) {
            if (isset($this->materias[$nome])) {
                $permitidosId[(int) $this->materias[$nome]['id']] = true;
            }
        }
        $profs = $this->db->fetchAll('SELECT * FROM professores WHERE ativo = 1') ?: [];
        foreach ($profs as $prof) {
            $materias = $this->decodeLista($prof['materias'] ?? null);
            $filtradas = [];
            foreach ($materias as $item) {
                if (is_numeric($item) && isset($permitidosId[(int) $item])) {
                    $filtradas[] = (int) $item;
                    continue;
                }
                if (is_string($item) && isset($permitidosNome[$item])) {
                    $filtradas[] = $item;
                }
            }
            $this->teachers->update((int) $prof['id'], [
                'nome' => (string) $prof['nome'],
                'email' => $prof['email'],
                'codigo_prof' => $prof['codigo_prof'],
                'materias' => $filtradas,
                'turmas' => $this->decodeLista($prof['turmas'] ?? null),
                'ativo' => 1,
                'pagante' => (int) ($prof['pagante'] ?? 1),
            ]);
        }
        println('  matérias dos professores alinhadas ao catálogo EM');
    }

    private function carregarSalas(): void
    {
        if (!$this->tabelaExiste('school_locations')) {
            return;
        }
        $rows = $this->db->fetchAll('SELECT id, codigo FROM school_locations WHERE ativo = 1 ORDER BY id ASC LIMIT 8') ?: [];
        foreach ($rows as $row) {
            $this->salasIds[(string) $row['codigo']] = (int) $row['id'];
        }
    }

    private function criarTurmas(): void
    {
        $salaLista = array_values($this->salasIds);
        $i = 0;
        foreach (SERIES as $chave => $meta) {
            foreach (LETRAS as $letra) {
                $nome = $meta['letra_serie'] . $letra;
                $salaId = $salaLista[$i % max(1, count($salaLista))] ?? 0;
                $id = (int) $this->turmasModel->create([
                    'nome' => $nome,
                    'ano_letivo' => ANO,
                    'ano_letivo_id' => $this->anoLetivoId,
                    'serie' => $meta['nome'],
                    'ativo' => 1,
                    'curso_novo_id' => $this->cursoEmId,
                    'serie_id' => $this->seriesIds[$chave],
                    'matriz_curricular_id' => $this->matrizPorSerie[$chave],
                    'turno' => 'manha',
                    'sala_padrao_id' => $salaId,
                    'vagas' => ALUNOS_POR_TURMA,
                    'observacoes' => 'Base de teste EM 2025',
                ]);
                $this->turmaIds[$chave . ':' . $letra] = $id;
                $i++;
            }
        }
        println('  turmas: ' . implode(', ', array_keys($this->turmaIds)));
    }

    private function reapontarProfessores(): void
    {
        $profs = $this->db->fetchAll('SELECT * FROM professores WHERE ativo = 1') ?: [];
        $filhosNomes = array_column(FILHOS_LP, 'nome');
        $filhosIds = [];
        foreach (FILHOS_LP as $filho) {
            $filhosIds[] = (int) $this->materias[$filho['nome']]['id'];
        }
        $atualizados = 0;
        foreach ($profs as $prof) {
            $id = (int) $prof['id'];
            $email = strtolower(trim((string) ($prof['email'] ?? '')));
            $materias = $this->decodeLista($prof['materias'] ?? null);
            $ehLing = $this->professorTemComponente($materias, 'Língua Portuguesa')
                || str_starts_with($email, 'ling.');
            if ($ehLing) {
                $materias = $this->mesclarMateriasProfessor($materias, $filhosNomes, $filhosIds);
            }
            $turmas = [];
            if (preg_match('/\.([123])([ab])@educa\.local$/', $email, $m)) {
                $chave = 'em' . $m[1] . ':' . strtoupper($m[2]);
                if (isset($this->turmaIds[$chave])) {
                    $turmas = [$this->turmaIds[$chave]];
                }
            }
            $this->teachers->update($id, [
                'nome' => (string) $prof['nome'],
                'email' => $prof['email'],
                'codigo_prof' => $prof['codigo_prof'],
                'materias' => $materias,
                'turmas' => $turmas,
                'ativo' => 1,
                'pagante' => (int) ($prof['pagante'] ?? 1),
            ]);
            $atualizados++;
        }
        println('  professores reapontados: ' . $atualizados);
    }

    /** @return list<mixed> */
    private function decodeLista($raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        $decoded = json_decode($s, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @param list<mixed> $materias */
    private function professorTemComponente(array $materias, string $nome): bool
    {
        $alvoId = (int) ($this->materias[$nome]['id'] ?? 0);
        foreach ($materias as $item) {
            if (is_numeric($item) && (int) $item === $alvoId) {
                return true;
            }
            if (is_string($item) && strcasecmp($item, $nome) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<mixed> $materias
     * @param list<string> $nomes
     * @param list<int> $ids
     * @return list<mixed>
     */
    private function mesclarMateriasProfessor(array $materias, array $nomes, array $ids): array
    {
        $usaId = false;
        foreach ($materias as $item) {
            if (is_numeric($item)) {
                $usaId = true;
                break;
            }
        }
        $extra = $usaId ? $ids : $nomes;
        foreach ($extra as $item) {
            $ja = false;
            foreach ($materias as $atual) {
                if ((string) $atual === (string) $item) {
                    $ja = true;
                    break;
                }
            }
            if (!$ja) {
                $materias[] = $item;
            }
        }
        return $materias;
    }

    private function criarAlunos(): void
    {
        $temSexo = $this->temColuna('alunos', 'sexo');
        $temStatus = $this->temColuna('alunos', 'status');
        $temUnidade = $this->temColuna('alunos', 'unidade_id');
        $temPassword = $this->temColuna('alunos', 'password');
        $temPrimeiro = $this->temColuna('alunos', 'primeiro_acesso');
        $temCodigo = $this->temColuna('alunos', 'codigo_aluno');
        $slotGlobal = 0;
        $usados = [];

        foreach (SERIES as $chave => $meta) {
            foreach (LETRAS as $letra) {
                $turmaId = $this->turmaIds[$chave . ':' . $letra];
                for ($slot = 1; $slot <= ALUNOS_POR_TURMA; $slot++) {
                    $pessoa = $this->montarPessoa($slotGlobal, $usados);
                    $usados[$pessoa['nome']] = true;
                    $ra = sprintf('2025%s%s%02d', $meta['letra_serie'], $letra, $slot);
                    $nick = $pessoa['slug'] . '.' . strtolower($meta['letra_serie'] . $letra) . sprintf('%02d', $slot);
                    $nascAno = ANO - $meta['idade'];
                    $cols = [
                        'nome', 'nickname', 'email', 'senha_hash', 'ra', 'turma_id', 'serie',
                        'data_nasc', 'ativo', 'pagante',
                    ];
                    $params = [
                        'nome' => $pessoa['nome'],
                        'nickname' => $nick,
                        'email' => $nick . '@educa.local',
                        'senha_hash' => $this->hash,
                        'ra' => $ra,
                        'turma_id' => $turmaId,
                        'serie' => $meta['nome'],
                        'data_nasc' => sprintf('%04d-%02d-%02d', $nascAno, (($slot % 12) + 1), min(28, max(1, $slot))),
                        'ativo' => 1,
                        'pagante' => 1,
                    ];
                    if ($temSexo) {
                        $cols[] = 'sexo';
                        $params['sexo'] = $pessoa['sexo'];
                    }
                    if ($temStatus) {
                        $cols[] = 'status';
                        $params['status'] = 'ACTIVE';
                    }
                    if ($temPassword) {
                        $cols[] = 'password';
                        $params['password'] = '';
                    }
                    if ($temPrimeiro) {
                        $cols[] = 'primeiro_acesso';
                        $params['primeiro_acesso'] = 0;
                    }
                    if ($temUnidade && $this->unidadeEmId > 0) {
                        $cols[] = 'unidade_id';
                        $params['unidade_id'] = $this->unidadeEmId;
                    }
                    if ($temCodigo) {
                        $cols[] = 'codigo_aluno';
                        $params['codigo_aluno'] = $ra;
                    }
                    $ph = implode(', ', array_map(static fn ($c) => ':' . $c, $cols));
                    $alunoId = (int) $this->db->insert(
                        'INSERT INTO alunos (' . implode(', ', $cols) . ') VALUES (' . $ph . ')',
                        $params
                    );
                    $this->movimentacao->vincularAlunoTurma($alunoId, $turmaId, $this->anoLetivoId, true, DATA_MATRICULA);
                    $this->completarCadastro($alunoId, $nick, $pessoa['nome'], $params['data_nasc'], $slotGlobal);
                    $slotGlobal++;
                }
                println('  ' . $meta['letra_serie'] . $letra . ': 40 alunos');
            }
        }
    }

    /**
     * @param array<string,bool> $usados
     * @return array{nome:string,slug:string,sexo:string}
     */
    private function montarPessoa(int $idx, array $usados): array
    {
        $fem = $idx % 2 === 0;
        $nomes = $fem ? NOMES_F : NOMES_M;
        $sexo = $fem ? 'F' : 'M';
        for ($t = 0; $t < 200; $t++) {
            $n = $nomes[($idx + $t) % count($nomes)];
            $s1 = SOBRENOMES[($idx + $t * 3) % count(SOBRENOMES)];
            $s2 = SOBRENOMES[($idx * 7 + $t * 5 + 11) % count(SOBRENOMES)];
            if ($s1 === $s2) {
                $s2 = SOBRENOMES[($idx + $t + 19) % count(SOBRENOMES)];
            }
            $nome = $n . ' ' . $s1 . ' ' . $s2;
            if (!isset($usados[$nome])) {
                return ['nome' => $nome, 'slug' => slugify($n . '.' . $s1), 'sexo' => $sexo];
            }
        }
        return ['nome' => 'Aluno Teste ' . $idx, 'slug' => 'aluno.teste.' . $idx, 'sexo' => $sexo];
    }

    private function completarCadastro(int $id, string $nick, string $nome, string $nasc, int $idx): void
    {
        $partes = preg_split('/\s+/', $nome) ?: [];
        $sobrenome = (string) (end($partes) ?: 'Silva');
        $semPai = ($idx % 23 === 0);
        $nomeMae = NOMES_MAE[$idx % count(NOMES_MAE)] . ' ' . $sobrenome;
        $nomePai = $semPai ? null : (NOMES_PAI[$idx % count(NOMES_PAI)] . ' ' . $sobrenome);
        $rua = RUAS[$idx % count(RUAS)];
        $bairro = BAIRROS[$idx % count(BAIRROS)];
        $numero = (string) (10 + ($idx % 890));
        $cep = sprintf('01%03d%03d', ($idx % 400) + 100, ($idx % 900) + 10);
        $foneMae = '11' . sprintf('9%08d', 70000000 + $id);
        $fonePai = '11' . sprintf('9%08d', 80000000 + $id);
        $foneAluno = '11' . sprintf('9%08d', 90000000 + $id);
        $sets = [
            'cpf' => gerarCpf($id * 17 + 101),
            'rg' => sprintf('%02d.%03d.%03d-%d', ($id % 90) + 10, ($id * 3) % 1000, ($id * 7) % 1000, $id % 10),
            'telefone' => '1130001' . sprintf('%03d', $id % 1000),
            'celular' => $foneAluno,
            'logradouro' => $rua,
            'numero' => $numero,
            'bairro' => $bairro,
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'cep' => $cep,
            'nome_mae' => $nomeMae,
            'nome_pai' => $nomePai,
            'nacionalidade' => 'Brasileira',
            'naturalidade' => 'São Paulo',
            'id' => $id,
        ];
        $sql = "UPDATE alunos SET
            cpf = :cpf, rg = :rg, telefone = :telefone, celular = :celular,
            logradouro = :logradouro, numero = :numero, bairro = :bairro,
            cidade = :cidade, uf = :uf, cep = :cep, nome_mae = :nome_mae, nome_pai = :nome_pai,
            nacionalidade = :nacionalidade, naturalidade = :naturalidade";
        if ($this->temColuna('alunos', 'whatsapp')) {
            $sql .= ', whatsapp = :whatsapp';
            $sets['whatsapp'] = $foneAluno;
        }
        if ($this->temColuna('alunos', 'email_secundario')) {
            $sql .= ', email_secundario = :email_sec';
            $sets['email_sec'] = $nick . '.familia@educa.local';
        }
        if ($this->temColuna('alunos', 'codigo_inep')) {
            $sql .= ', codigo_inep = :codigo_inep';
            $sets['codigo_inep'] = '3518' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
        }
        if ($this->temColuna('alunos', 'uf_nascimento')) {
            $sql .= ', uf_nascimento = :uf_nascimento';
            $sets['uf_nascimento'] = 'SP';
        }
        if ($this->temColuna('alunos', 'cor_raca')) {
            $sql .= ', cor_raca = :cor_raca';
            $sets['cor_raca'] = CORES[$idx % count(CORES)];
        }
        if ($this->temColuna('alunos', 'orgao_emissor')) {
            $sql .= ', orgao_emissor = :orgao_emissor, uf_rg = :uf_rg';
            $sets['orgao_emissor'] = 'SSP';
            $sets['uf_rg'] = 'SP';
        }
        if ($this->temColuna('alunos', 'certidao_nascimento')) {
            $cert = '1234567202501' . str_pad((string) $id, 19, '0', STR_PAD_LEFT);
            $sql .= ', certidao_nascimento = :certidao, certidao_livro = :livro, certidao_folha = :folha, certidao_termo = :termo';
            $sets['certidao'] = substr($cert, 0, 32);
            $sets['livro'] = sprintf('%03d', ($id % 90) + 1);
            $sets['folha'] = sprintf('%03d', ($id % 200) + 1);
            $sets['termo'] = sprintf('%05d', $id);
        }
        if ($this->temColuna('alunos', 'nis')) {
            $sql .= ', nis = :nis';
            $sets['nis'] = str_pad((string) (10000000000 + $id * 17), 11, '0', STR_PAD_LEFT);
        }
        if ($this->temColuna('alunos', 'zona')) {
            $sql .= ', zona = :zona, pais = :pais';
            $sets['zona'] = ($idx % 31 === 0) ? 'rural' : 'urbana';
            $sets['pais'] = 'Brasil';
        }
        if ($this->temColuna('alunos', 'complemento')) {
            $sql .= ', complemento = :complemento';
            $sets['complemento'] = ($idx % 5 === 0) ? 'Apto ' . (10 + ($idx % 40)) : null;
        }
        $this->db->query($sql . ' WHERE id = :id', $sets);

        if ($this->tabelaExiste('responsaveis')) {
            $maeId = $this->upsertResponsavel([
                'email' => 'mae.' . $nick . '@educa.local',
                'nome' => $nomeMae,
                'cpf' => gerarCpf($id * 31 + 3),
                'rg' => sprintf('%02d.%03d.%03d-%d', 20, ($id * 5) % 1000, ($id * 11) % 1000, 1),
                'telefone' => $foneMae,
                'celular' => $foneMae,
                'data_nascimento' => sprintf('%04d-04-%02d', (int) substr($nasc, 0, 4) - 28, min(28, ($id % 27) + 1)),
                'endereco' => $rua,
                'numero' => $numero,
                'complemento' => ($idx % 5 === 0) ? 'Apto ' . (10 + ($idx % 40)) : null,
                'bairro' => $bairro,
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cep' => substr($cep, 0, 5) . '-' . substr($cep, 5),
            ]);
            $this->vincularResponsavel($id, $maeId, 'mae', true);
            if ($this->temColuna('alunos', 'responsavel_id')) {
                $this->db->query('UPDATE alunos SET responsavel_id = :r WHERE id = :id', ['r' => $maeId, 'id' => $id]);
            }
            if ($nomePai !== null) {
                $paiId = $this->upsertResponsavel([
                    'email' => 'pai.' . $nick . '@educa.local',
                    'nome' => $nomePai,
                    'cpf' => gerarCpf($id * 31 + 7),
                    'rg' => sprintf('%02d.%03d.%03d-%d', 30, ($id * 9) % 1000, ($id * 13) % 1000, 2),
                    'telefone' => $fonePai,
                    'celular' => $fonePai,
                    'data_nascimento' => sprintf('%04d-08-%02d', (int) substr($nasc, 0, 4) - 30, min(28, ($id % 26) + 1)),
                    'endereco' => $rua,
                    'numero' => $numero,
                    'complemento' => ($idx % 5 === 0) ? 'Apto ' . (10 + ($idx % 40)) : null,
                    'bairro' => $bairro,
                    'cidade' => 'São Paulo',
                    'uf' => 'SP',
                    'cep' => substr($cep, 0, 5) . '-' . substr($cep, 5),
                ]);
                $this->vincularResponsavel($id, $paiId, 'pai', false);
            }
        }

        $usaVan = ($idx % 17 === 0);
        $this->ficha->upsert($id, [
            'tipo_sanguineo' => SANGUE[$idx % count(SANGUE)],
            'plano_saude' => PLANOS[$idx % count(PLANOS)],
            'plano_saude_numero' => PLANOS[$idx % count(PLANOS)] ? ('EDU' . str_pad((string) $id, 8, '0', STR_PAD_LEFT)) : null,
            'hospital_referencia' => ($idx % 4 === 0) ? 'Hospital das Clínicas' : 'Santa Casa de São Paulo',
            'alergias' => ($idx % 11 === 0) ? 'Dipirona' : (($idx % 29 === 0) ? 'Picada de inseto' : null),
            'medicamentos_uso' => ($idx % 19 === 0) ? 'Bombinha de asma (salbutamol) se crise' : null,
            'condicoes_cronicas' => ($idx % 19 === 0) ? 'Asma leve' : null,
            'deficiencias_obs' => ($idx % 41 === 0) ? 'Usa óculos para miopia' : null,
            'contato_emergencia_nome' => $nomeMae,
            'contato_emergencia_telefone' => $foneMae,
            'contato_emergencia_parentesco' => 'Mãe',
            'restricoes_alimentares' => ($idx % 13 === 0) ? 'Intolerância à lactose' : null,
            'alimentacao_obs' => ($idx % 13 === 0) ? 'Preferir merenda sem leite.' : null,
            'usa_transporte_escolar' => $usaVan ? 1 : 0,
            'transporte_tipo' => $usaVan ? 'escolar' : 'proprio',
            'transporte_rota' => $usaVan ? 'Linha EM — manhã' : null,
            'transporte_ponto' => $usaVan ? ($rua . ', ' . $numero) : null,
            'transporte_responsavel' => $usaVan ? 'Van Educa — Sr. Paulo' : null,
            'transporte_telefone' => $usaVan ? '11960001000' : null,
            'observacoes_gerais' => null,
        ]);
    }

    /** @param array<string,mixed> $dados */
    private function upsertResponsavel(array $dados): int
    {
        $exist = $this->db->fetch('SELECT id FROM responsaveis WHERE email = :e LIMIT 1', ['e' => $dados['email']]);
        $params = [
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf'],
            'telefone' => $dados['telefone'],
            'celular' => $dados['celular'],
            'rg' => $dados['rg'],
            'data_nascimento' => $dados['data_nascimento'],
            'endereco' => $dados['endereco'],
            'numero' => $dados['numero'],
            'complemento' => $dados['complemento'],
            'bairro' => $dados['bairro'],
            'cidade' => $dados['cidade'],
            'uf' => $dados['uf'],
            'cep' => $dados['cep'],
        ];
        if ($exist) {
            $this->db->query(
                "UPDATE responsaveis SET
                    nome = :nome, cpf = :cpf, telefone = :telefone, celular = :celular, rg = :rg,
                    data_nascimento = :data_nascimento, endereco = :endereco, numero = :numero,
                    complemento = :complemento, bairro = :bairro, cidade = :cidade, uf = :uf, cep = :cep, ativo = 1
                 WHERE id = :id",
                $params + ['id' => (int) $exist['id']]
            );
            return (int) $exist['id'];
        }
        return (int) $this->db->insert(
            "INSERT INTO responsaveis
                (nome, email, senha_hash, cpf, telefone, celular, rg, data_nascimento,
                 endereco, numero, complemento, bairro, cidade, uf, cep, ativo, password)
             VALUES
                (:nome, :email, :senha_hash, :cpf, :telefone, :celular, :rg, :data_nascimento,
                 :endereco, :numero, :complemento, :bairro, :cidade, :uf, :cep, 1, '')",
            $params + ['email' => $dados['email'], 'senha_hash' => $this->hash]
        );
    }

    private function vincularResponsavel(int $alunoId, int $respId, string $tipo, bool $financeiro): void
    {
        $flags = [
            'a' => $alunoId,
            'p' => $respId,
            'tv' => $tipo,
            'parentesco' => $tipo === 'mae' ? 'Mãe' : 'Pai',
            'fin' => $financeiro ? 1 : 0,
            'retirar' => 1,
            'boletos' => $financeiro ? 1 : 0,
            'boletim' => 1,
            'notif' => 1,
            'ped' => $financeiro ? 1 : 0,
            'assina' => $financeiro ? 1 : 0,
        ];
        $this->db->insert(
            "INSERT INTO alunos_responsaveis
                (aluno_id, responsavel_id, tipo_vinculo, parentesco, is_financeiro, ativo,
                 pode_retirar, recebe_boletos, recebe_boletim, recebe_notificacoes,
                 responsavel_pedagogico, assina_documentos)
             VALUES
                (:a, :p, :tv, :parentesco, :fin, 1, :retirar, :boletos, :boletim, :notif, :ped, :assina)",
            $flags
        );
    }

    private function imprimirResumo(): void
    {
        $alunos = (int) ($this->db->fetch('SELECT COUNT(*) n FROM alunos')['n'] ?? 0);
        $turmas = (int) ($this->db->fetch('SELECT COUNT(*) n FROM turmas')['n'] ?? 0);
        $mat = (int) ($this->db->fetch("SELECT COUNT(*) n FROM matricula WHERE status = 'ativa'")['n'] ?? 0);
        $exemplo = $this->db->fetch('SELECT nome, nickname, ra, email FROM alunos ORDER BY id ASC LIMIT 1');
        println('');
        println('Resumo:');
        println('  turmas: ' . $turmas);
        println('  alunos: ' . $alunos);
        println('  matrículas ativas: ' . $mat);
        if ($exemplo) {
            println('  aluno exemplo: ' . $exemplo['nome'] . ' / ' . $exemplo['nickname'] . ' / RA ' . $exemplo['ra']);
            println('  login aluno: ' . $exemplo['email'] . '  senha: ' . SENHA);
        }
        println('  login admin: admin@educa.local  senha: ' . SENHA);
        println('  URLs: http://educa.localhost/admin  ·  http://educa.localhost/');
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tabela) || !preg_match('/^[a-z0-9_]+$/i', $coluna)) {
            return false;
        }
        try {
            return $this->db->fetch('SHOW COLUMNS FROM `' . $tabela . '` LIKE ?', [$coluna]) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

$appEnv = strtolower((string) env('APP_ENV', 'production'));
if (!in_array($appEnv, ['development', 'dev', 'local'], true)) {
    fail('Abortado: APP_ENV não é development/local.');
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
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
    $runner = new PrepararBaseTesteEm2025Educa(Database::getInstance());
    $somenteCatalogo = in_array('--somente-componentes', $argv ?? [], true);
    exit($somenteCatalogo ? $runner->executarSomenteCatalogo() : $runner->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
