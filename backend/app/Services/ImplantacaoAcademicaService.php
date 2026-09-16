<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Modulos/boletins/Models/Boletim.php';
require_once __DIR__ . '/../Modulos/grupos-regras-notas/Models/GrupoRegrasNotas.php';
require_once __DIR__ . '/../Modulos/regras-academicas/Models/RegraAcademica.php';
require_once __DIR__ . '/SchoolCalendarService.php';

use App\Modulos\Boletins\Models\Boletim;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

/**
 * Trilha de implantação acadêmica: lê o estado real do tenant (sem escola_id).
 */
class ImplantacaoAcademicaService
{
    /**
     * @param array<string,bool|int> $estado
     * @return list<array{chave:string,label:string,ok:bool,href:string,detalhe:string}>
     */
    public static function montarPassos(array $estado): array
    {
        $n = static function (string $k) use ($estado): int {
            return (int) ($estado[$k] ?? 0);
        };
        $ok = static function (string $k) use ($estado): bool {
            return !empty($estado[$k]);
        };
        $passo = static function (
            string $chave,
            string $label,
            bool $pronto,
            string $href,
            string $detalheOk,
            string $detalheFalta,
            string $aceite,
            string $erro
        ): array {
            $guia = self::conteudoGuia($chave);
            return [
                'chave' => $chave,
                'label' => $label,
                'ok' => $pronto,
                'href' => $href,
                'detalhe' => $pronto ? $detalheOk : $detalheFalta,
                'aceite' => $aceite,
                'erro_comum' => $erro,
                'como_fazer' => $guia['como_fazer'],
                'erros_comuns' => $guia['erros_comuns'],
                'usado_depois_em' => $guia['usado_depois_em'],
                'prints' => $guia['prints'],
            ];
        };

        return [
            $passo(
                'ano_letivo',
                'Ano Letivo',
                $n('anos_letivos') > 0,
                '/admin/ano-letivo',
                $n('anos_letivos') . ' ano(s) cadastrado(s)',
                'Cadastre o ano com data de início e fim.',
                'Existe um ano letivo com período definido.',
                'Sem ano letivo, turmas e matrículas não amarram o calendário.'
            ),
            $passo(
                'cursos_series',
                'Cursos e Séries',
                $n('cursos') > 0 && $n('series') > 0,
                '/admin/cursos-series',
                $n('cursos') . ' curso(s) · ' . $n('series') . ' série(s)',
                'Cadastre o curso e aninhe as séries.',
                'Cada curso tem ao menos uma série.',
                'Série sem curso: a matriz e a turma não encontram o percurso.'
            ),
            $passo(
                'componentes',
                'Componentes Curriculares',
                $n('componentes') > 0,
                '/admin/componentes-curriculares',
                $n('componentes') . ' componente(s)',
                'Cadastre as disciplinas oficiais da escola.',
                'Há componentes ativos para montar a matriz.',
                'Matriz vazia: faltam componentes cadastrados.'
            ),
            $passo(
                'matriz',
                'Matriz Curricular',
                $n('matrizes') > 0,
                '/admin/matrizes-curriculares',
                $n('matrizes') . ' matriz(es) cadastrada(s)',
                'Cadastre a matriz da série com os componentes oficiais.',
                'Cada série do ano tem uma matriz com os componentes oficiais.',
                'Série sem matriz: o boletim e a grade não encontram as disciplinas.'
            ),
            $passo(
                'calendario',
                'Calendário Letivo',
                $ok('calendario'),
                '/admin/calendario-letivo',
                'Ano letivo com calendário cadastrado',
                'Cadastre o ano e marque feriados, recessos e avaliações.',
                'O ano letivo tem calendário com dias letivos.',
                'Sem calendário, o gerador de avaliações não sabe pular feriado.'
            ),
            $passo(
                'professores',
                'Professores',
                $n('professores') > 0,
                '/admin/teachers',
                $n('professores') . ' professor(es)',
                'Cadastre os professores que lançam notas e diário.',
                'Há ao menos um professor ativo.',
                'Grade sem professor: o diário e o lançamento não abrem.'
            ),
            $passo(
                'turmas',
                'Turmas',
                $n('turmas') > 0,
                '/admin/turmas',
                $n('turmas') . ' turma(s)',
                'Crie as turmas do ano e vincule série e alunos.',
                'O ano tem turmas ativas.',
                'Sem turma, não há lançamento de notas nem fechamento.'
            ),
            $passo(
                'alunos',
                'Alunos',
                $n('alunos') > 0,
                '/admin/students',
                $n('alunos') . ' aluno(s)',
                'Cadastre ou importe os alunos da escola.',
                'Há alunos cadastrados para matricular nas turmas.',
                'Turma sem aluno: o fechamento lista vazio.'
            ),
            $passo(
                'salas',
                'Salas / Ambientes',
                $n('salas') > 0,
                '/admin/salas',
                $n('salas') . ' sala(s)',
                'Cadastre salas e ambientes (laboratório, quadra, biblioteca).',
                'Há salas cadastradas para usar como sala padrão e na grade.',
                'Grade sem sala: a aula fica sem ambiente definido.'
            ),
            $passo(
                'grade',
                'Grade Horária',
                $n('aulas_grade') > 0,
                '/admin/grade-horaria',
                $n('aulas_grade') . ' aula(s) na grade',
                'Monte dia, horário, componente e professor de cada turma.',
                'Há ao menos uma aula cadastrada na grade.',
                'Sem grade, o diário não sabe qual aula lançar.'
            ),
            $passo(
                'regra',
                'Regras de Aprovação',
                $n('regras_aprovacao') > 0,
                '/admin/regras-academicas',
                $n('regras_aprovacao') . ' regra(s) versionada(s)',
                'Defina média mínima, frequência e recuperação.',
                'Há uma regra ativa com média mínima e, se usar, frequência.',
                'Sem regra, o fechamento não calcula aprovado/reprovado.'
            ),
            $passo(
                'tipos_nota',
                'Tipos de Nota',
                $n('tipos_nota') > 0,
                '/admin/provas/tipos-avaliacao',
                $n('tipos_nota') . ' tipo(s) cadastrado(s)',
                'Cadastre os tipos (prova, trabalho, participação…).',
                'Há ao menos um tipo de nota ativo.',
                'Avaliação sem tipo: o lançamento não classifica a nota.'
            ),
            $passo(
                'quadro',
                'Quadro de Notas',
                $n('quadros') > 0,
                '/admin/quadros-notas',
                $n('quadros') . ' quadro(s) ativo(s)',
                'Cadastre as colunas (S1, AV1…) e, se precisar, os blocos de disciplinas. As provas são criadas em Lançamento de Notas.',
                'Existe pelo menos um quadro ativo com colunas (AV1/S1…) ou blocos de disciplinas A/B.',
                'Avaliação sem quadro: a prévia de colunas fica vazia.'
            ),
            $passo(
                'modelo',
                'Modelo de Boletim',
                $n('boletins') > 0,
                '/admin/boletins',
                $n('boletins') . ' modelo(s) cadastrado(s)',
                'Crie o documento oficial e aponte a regra de aprovação.',
                'O modelo aponta para uma regra de aprovação e um quadro.',
                'Modelo sem regra: o boletim oficial não fecha a situação.'
            ),
        ];
    }

    /**
     * @return array{ano:int,completos:int,total:int,percentual:int,passos:list<array<string,mixed>>}
     */
    public function diagnosticar(int $anoLetivo = 0): array
    {
        $ano = $anoLetivo > 0 ? $anoLetivo : (int) date('Y');
        $estado = [
            'anos_letivos' => $this->contarTabela('ano_letivo'),
            'cursos' => $this->contarTabela('curso'),
            'series' => $this->contarTabela('serie'),
            'componentes' => $this->contarTabela('materias'),
            'matrizes' => $this->contarTabela('matrizes_curriculares'),
            'professores' => $this->contarTabela('professores'),
            'alunos' => $this->contarTabela('alunos'),
            'turmas' => $this->contarTabela('turmas'),
            'salas' => $this->contarTabela('school_locations'),
            'aulas_grade' => $this->contarTabela('grade_horaria'),
            'tipos_nota' => $this->contarTabela('provas_tipos_avaliacao'),
            'quadros' => $this->contarQuadrosAtivos(),
            'regras_aprovacao' => $this->contarRegrasAprovacao(),
            'boletins' => $this->contarBoletins(),
            'calendario' => $this->temCalendario($ano),
        ];
        $passos = self::montarPassos($estado);
        $passos = self::anexarPrints($passos);
        $completos = 0;
        foreach ($passos as $p) {
            if (!empty($p['ok'])) {
                $completos++;
            }
        }
        $total = count($passos);
        return [
            'ano' => $ano,
            'completos' => $completos,
            'total' => $total,
            'percentual' => $total > 0 ? (int) round(($completos / $total) * 100) : 0,
            'passos' => $passos,
        ];
    }

    private function contarTabela(string $tabela): int
    {
        $permitidas = [
            'ano_letivo' => true,
            'curso' => true,
            'serie' => true,
            'materias' => true,
            'matrizes_curriculares' => true,
            'professores' => true,
            'alunos' => true,
            'turmas' => true,
            'school_locations' => true,
            'grade_horaria' => true,
            'provas_tipos_avaliacao' => true,
        ];
        if (!isset($permitidas[$tabela])) {
            return 0;
        }
        $db = Database::getInstance();
        try {
            $ok = $db->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                ['t' => $tabela]
            );
            if (empty($ok['ok'])) {
                return 0;
            }
            $row = $db->fetch('SELECT COUNT(*) AS n FROM `' . $tabela . '`');
            return (int) ($row['n'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function contarQuadrosAtivos(): int
    {
        try {
            $model = new GrupoRegrasNotas();
            if (!$model->tabelasProntas()) {
                return 0;
            }
            return count($model->listar(true));
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function contarRegrasAprovacao(): int
    {
        try {
            $model = new RegraAcademica();
            if (!$model->schemaPronto()) {
                return 0;
            }
            return count($model->getAll(['ativo' => 1]));
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function contarBoletins(): int
    {
        try {
            $model = new Boletim();
            if (!$model->tabelasProntas()) {
                return 0;
            }
            return count($model->listar(true));
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function diretorioPrintsGuia(): ?string
    {
        $candidatos = [
            dirname(__DIR__, 2) . '/public/assets/guia-onboarding',
            dirname(__DIR__, 2) . '/storage/guia-onboarding',
            dirname(__DIR__, 3) . '/docs/guia-admin-telas/imagens',
        ];
        foreach ($candidatos as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }
        return null;
    }

    /**
     * @param list<array<string,mixed>> $passos
     * @return list<array<string,mixed>>
     */
    private static function anexarPrints(array $passos): array
    {
        $dir = self::diretorioPrintsGuia();
        foreach ($passos as &$passo) {
            $prints = is_array($passo['prints'] ?? null) ? $passo['prints'] : [];
            foreach ($prints as &$print) {
                $arquivo = (string) ($print['arquivo'] ?? '');
                $print['existe'] = $dir !== null && $arquivo !== '' && is_file($dir . '/' . $arquivo);
                $print['url'] = '/assets/guia-onboarding/' . rawurlencode($arquivo);
            }
            unset($print);
            $passo['prints'] = $prints;
        }
        unset($passo);
        return $passos;
    }

    /**
     * @return array{como_fazer:list<string>,erros_comuns:list<string>,usado_depois_em:string,prints:list<array{arquivo:string,rotulo:string}>}
     */
    private static function conteudoGuia(string $chave): array
    {
        $vazio = [
            'como_fazer' => [],
            'erros_comuns' => [],
            'usado_depois_em' => '',
            'prints' => [],
        ];
        $todos = [
            'ano_letivo' => [
                'como_fazer' => [
                    'Clique em Novo Ano Letivo.',
                    'Informe o ano, a data de início e a data de fim.',
                    'Escolha a divisão do ano: bimestre, trimestre ou semestre.',
                    'Marque Ano letivo ativo e salve.',
                ],
                'erros_comuns' => [
                    'Esquecer de marcar “ativo” — o ano existe, mas as outras telas não o assumem.',
                    'Errar a divisão do ano. Prova, boletim e fechamento usam essa configuração; mudar depois mexe em tudo que já foi lançado.',
                ],
                'usado_depois_em' => 'Absolutamente tudo — turmas, matrículas, notas, boletim, fechamento.',
                'prints' => [
                    ['arquivo' => '003-ano-letivo.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '004-ano-letivo-novo-novo-ano-letivo.jpg', 'rotulo' => 'Novo ano letivo'],
                ],
            ],
            'cursos_series' => [
                'como_fazer' => [
                    'Cadastre o curso (ex.: Ensino Fundamental — Anos Finais).',
                    'Dentro dele, cadastre as séries (8º ano, 9º ano).',
                    'Desmarque “Possui série” só para cursos sem progressão anual.',
                ],
                'erros_comuns' => [
                    'Criar série sem vincular ao curso — ela aparece depois em listas e ninguém sabe de onde veio.',
                    'Usar nome diferente do oficial. Esse nome sai no histórico.',
                ],
                'usado_depois_em' => 'Matriz curricular, turmas, regras de aprovação.',
                'prints' => [
                    ['arquivo' => '006-cursos-series.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '007-cursos-series-novo-novo-curso.jpg', 'rotulo' => 'Novo curso'],
                    ['arquivo' => '008-cursos-series-novo-nova-serie.jpg', 'rotulo' => 'Nova série'],
                ],
            ],
            'componentes' => [
                'como_fazer' => [
                    'Cadastre cada disciplina com nome e sigla — a sigla é o que aparece na grade e no boletim.',
                    'Informe a área do conhecimento (usada no censo).',
                    'Confira Permite avaliação: marcado para disciplina que recebe nota.',
                ],
                'erros_comuns' => [
                    'Nome diferente do que a escola usa oficialmente — depois sai errado no documento.',
                    'Desmarcar “Permite avaliação” sem querer — a disciplina some do boletim.',
                    'Cadastrar a disciplina e não incluir na matriz (passo seguinte). Ela existe, mas não aparece em lugar nenhum.',
                ],
                'usado_depois_em' => 'Matriz, grade horária, diário, quadro de notas, boletim.',
                'prints' => [
                    ['arquivo' => '011-componentes.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '012-componentes-novo-novo-componente-curricular.jpg', 'rotulo' => 'Novo componente'],
                ],
            ],
            'matriz' => [
                'como_fazer' => [
                    'Crie a matriz para uma série, dentro do ano letivo.',
                    'Inclua os componentes daquela série com a carga horária de cada um.',
                    'Confira a carga total — é o número que a escola presta contas.',
                ],
                'erros_comuns' => [
                    'Componente cadastrado e não incluído aqui. É a causa nº 1 de “a disciplina não aparece no boletim”.',
                    'Repetir a mesma carga para séries diferentes sem conferir. A carga pertence ao vínculo série+disciplina, não à disciplina.',
                ],
                'usado_depois_em' => 'Turmas, grade horária, diário, boletim, histórico.',
                'prints' => [
                    ['arquivo' => '013-matrizes.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '014-matrizes-novo-nova-matriz.jpg', 'rotulo' => 'Nova matriz'],
                ],
            ],
            'calendario' => [
                'como_fazer' => [
                    'Defina a meta de dias letivos do ano.',
                    'Cadastre feriados, recessos e férias.',
                    'Cadastre reposições e eventos.',
                    'Confira a contagem final de dias letivos.',
                ],
                'erros_comuns' => [
                    'Esquecer feriados — a contagem fica maior que a real e a frequência sai errada.',
                    'Não marcar “Descontar dos dias letivos” nos tipos de dia que não são aula.',
                ],
                'usado_depois_em' => 'Frequência, diário, fechamento.',
                'prints' => [
                    ['arquivo' => '016-calendario-letivo.jpg', 'rotulo' => 'Calendário'],
                    ['arquivo' => '017-calendario-letivo-novo-configurar.jpg', 'rotulo' => 'Configurar ano'],
                ],
            ],
            'professores' => [
                'como_fazer' => [
                    'Cadastre nome completo e e-mail institucional — o e-mail é o acesso dele.',
                    'Vincule os componentes que ele leciona.',
                    'Confira que está ativo.',
                ],
                'erros_comuns' => [
                    'E-mail digitado errado — ninguém descobre até o dia do treinamento.',
                    'Professor sem componente vinculado não aparece para seleção na grade.',
                ],
                'usado_depois_em' => 'Turmas, grade horária, diário, lançamento de notas.',
                'prints' => [
                    ['arquivo' => '021-professores.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '022-professores-novo-novo-professor.jpg', 'rotulo' => 'Novo professor'],
                ],
            ],
            'turmas' => [
                'como_fazer' => [
                    'Crie a turma com ano letivo, curso, série e turno.',
                    'Defina a sala padrão e a capacidade.',
                    'Confira se as disciplinas da matriz apareceram para a turma.',
                    'Vincule os professores às disciplinas.',
                ],
                'erros_comuns' => [
                    'Criar turma antes da matriz — a turma nasce sem disciplina, e depois nota e diário não têm onde ser lançados.',
                    'Deixar disciplina sem professor.',
                ],
                'usado_depois_em' => 'Matrícula, grade, diário, notas, fechamento.',
                'prints' => [
                    ['arquivo' => '026-turmas.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '027-turmas-novo-nova-turma.jpg', 'rotulo' => 'Nova turma'],
                ],
            ],
            'alunos' => [
                'como_fazer' => [
                    'Cadastre o aluno com nome completo, data de nascimento e código/matrícula.',
                    'Vincule o responsável — sem ele não há comunicação nem app da família.',
                    'Matricule o aluno na turma. Cadastrar e matricular são dois atos diferentes.',
                    'Para volume, use a importação (Cursos e Séries → Ações → Importar alunos).',
                ],
                'erros_comuns' => [
                    'Cadastrar e não matricular. O aluno existe mas não aparece em nota, chamada nem boletim. É o erro mais frequente de quem está começando.',
                    'Importar com a coluna de turma em branco ou com código inexistente.',
                    'Aluno que entra depois do início do ano sem data de matrícula correta — a frequência dele passa a ser calculada sobre o ano inteiro.',
                ],
                'usado_depois_em' => 'Tudo que é por aluno — nota, chamada, boletim, documentos.',
                'prints' => [
                    ['arquivo' => '024-alunos.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '025-alunos-admin-students-create.jpg', 'rotulo' => 'Cadastrar aluno'],
                ],
            ],
            'salas' => [
                'como_fazer' => [
                    'Cadastre as salas com nome, capacidade e localização (bloco, andar).',
                    'Cadastre também ambientes especiais — laboratório, quadra, biblioteca.',
                ],
                'erros_comuns' => [
                    'Capacidade em branco — o cadastro fica incompleto e a sala perde referência de lotação.',
                    'Nomes duplicados entre salas.',
                ],
                'usado_depois_em' => 'Turmas (sala padrão) e grade horária.',
                'prints' => [
                    ['arquivo' => '032-salas.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '033-salas-novo-nova-sala.jpg', 'rotulo' => 'Nova sala'],
                ],
            ],
            'grade' => [
                'como_fazer' => [
                    'Selecione a turma.',
                    'Para cada aula: dia da semana, horário de início e fim, componente e professor.',
                    'Gere o PDF e confira.',
                ],
                'erros_comuns' => [
                    'Mesmo professor em duas turmas no mesmo horário.',
                    'Grade que não fecha com a carga da matriz — confira a soma.',
                    'Disciplina que não está na matriz da série não aparece para seleção. Se faltar alguma, o problema está no passo da matriz.',
                ],
                'usado_depois_em' => 'Diário de classe, frequência, consulta do professor e do aluno.',
                'prints' => [
                    ['arquivo' => '030-grade-horaria.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '031-grade-horaria-novo-nova-aula.jpg', 'rotulo' => 'Nova aula'],
                ],
            ],
            'regra' => [
                'como_fazer' => [
                    'Defina a média mínima e a frequência mínima.',
                    'Marque se a frequência também reprova.',
                    'Configure o tipo de recuperação e como ela entra na média.',
                    'Defina arredondamento e casas decimais.',
                ],
                'erros_comuns' => [
                    'Assumir que o padrão do sistema é a regra da escola. Confira, não presuma.',
                    'Não vincular ao ano letivo correto. As regras são versionadas por ano — mexer em 2026 não altera 2025, e isso é proposital.',
                ],
                'usado_depois_em' => 'Cálculo de média, resultado final, boletim, fechamento. Se o boletim sair errado, o problema quase sempre está aqui — não na tela de notas.',
                'prints' => [
                    ['arquivo' => '035-regras.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '036-regras-novo-nova-regra.jpg', 'rotulo' => 'Nova regra'],
                ],
            ],
            'tipos_nota' => [
                'como_fazer' => [
                    'Cadastre cada natureza de nota que a escola aplica (prova bimestral, trabalho, simulado).',
                    'Escolha se é online (prova na plataforma) ou offline (a escola informa a nota).',
                    'Escolha evento único (uma vez no período) ou múltiplos (várias vezes, consolidadas).',
                    'Defina a escala e como fecha a nota final.',
                ],
                'erros_comuns' => [
                    'Escala diferente da que a escola usa — a nota entra fora do padrão do boletim.',
                    'Esquecer de configurar o que acontece quando dois professores lançam o mesmo componente.',
                ],
                'usado_depois_em' => 'Quadro de notas, lançamento, boletim.',
                'prints' => [
                    ['arquivo' => '040-tipos-nota.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '041-tipos-nota-novo-novo-tipo.jpg', 'rotulo' => 'Novo tipo'],
                ],
            ],
            'quadro' => [
                'como_fazer' => [
                    'Dê um nome ao quadro (ex.: “Provas semanais 2026”).',
                    'Crie as colunas — onde cada nota vai cair (S1, S2, AV1…).',
                    'Se a escola divide as disciplinas em blocos, crie os blocos e vincule as disciplinas.',
                    'Vincule cada bloco às suas colunas.',
                ],
                'erros_comuns' => [
                    'Criar bloco sem vincular disciplina — o quadro fica montado e não recebe nota.',
                    'Montar colunas que não correspondem ao que a escola aplica de verdade.',
                ],
                'usado_depois_em' => 'Lançamento de notas e geração automática das colunas do boletim. Se todas as disciplinas usam as mesmas colunas, você não precisa criar bloco nenhum.',
                'prints' => [
                    ['arquivo' => '044-quadro-notas.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '045-quadro-notas-novo-novo-quadro.jpg', 'rotulo' => 'Novo quadro'],
                    ['arquivo' => '046-quadro-notas-wizard-2.jpg', 'rotulo' => 'Colunas'],
                ],
            ],
            'modelo' => [
                'como_fazer' => [
                    'Crie o modelo e escolha se é oficial (vai para o histórico) ou extra (curso complementar).',
                    'Vincule a regra de aprovação que ele usa.',
                    'Escolha o quadro de notas — as colunas se montam sozinhas a partir dele.',
                    'Defina quem vê: aluno, família.',
                    'Use Simular boletim e confira contra a conta feita à mão para um aluno.',
                ],
                'erros_comuns' => [
                    'Publicar sem simular. A simulação existe justamente para ver o resultado antes de valer.',
                    'Confundir modelo oficial com extra — o extra não entra no histórico.',
                ],
                'usado_depois_em' => 'Emissão de boletins, fechamento, histórico escolar.',
                'prints' => [
                    ['arquivo' => '056-modelo-boletim.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '057-modelo-boletim-novo-novo-modelo.jpg', 'rotulo' => 'Novo modelo'],
                    ['arquivo' => '060-modelo-boletim-acao-simular-boletim.jpg', 'rotulo' => 'Simular boletim'],
                ],
            ],
        ];
        return $todos[$chave] ?? $vazio;
    }

    private function temCalendario(int $ano): bool
    {
        try {
            $cal = new SchoolCalendarService();
            return is_array($cal->getAno($ano));
        } catch (Throwable $e) {
            return false;
        }
    }
}
