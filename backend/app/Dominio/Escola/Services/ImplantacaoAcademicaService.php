<?php

namespace App\Dominio\Escola\Services;


use App\Modulos\Boletins\Models\Boletim;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

use App\Core\Database;
use App\Modulos\GruposRegrasNotas\Models\GrupoRegrasNotas;

/**
 * Trilha de implantação acadêmica: lê o estado real do tenant (sem escola_id).
 */
class ImplantacaoAcademicaService
{
    public const GRUPO_ESTRUTURA = 'estrutura';
    public const GRUPO_PESSOAS = 'pessoas';
    public const GRUPO_AVALIA = 'avalia';

    /**
     * @param array<string,bool|int> $estado
     * @return list<array<string,mixed>>
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
            string $grupo,
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
                'grupo' => $grupo,
                'grupo_label' => self::rotuloGrupo($grupo),
                'ok' => $pronto,
                'atual' => false,
                'href' => $href,
                'detalhe' => $pronto ? $detalheOk : $detalheFalta,
                'aceite' => $aceite,
                'erro_comum' => $erro,
                'como_fazer' => $guia['como_fazer'],
                'erros_comuns' => $guia['erros_comuns'],
                'usado_depois_em' => $guia['usado_depois_em'],
                'nota' => $guia['nota'],
                'prints' => $guia['prints'],
            ];
        };

        return [
            $passo(
                'ano_letivo',
                'Ano Letivo',
                self::GRUPO_ESTRUTURA,
                $n('anos_letivos') > 0,
                '/admin/ano-letivo',
                $n('anos_letivos') . ' ano(s) cadastrado(s)',
                'nenhum ano letivo cadastrado',
                'Existe um ano letivo com período definido.',
                'Sem ano letivo, turmas e matrículas não amarram o calendário.'
            ),
            $passo(
                'cursos_series',
                'Cursos e Séries',
                self::GRUPO_ESTRUTURA,
                $ok('cursos_series'),
                '/admin/cursos-series',
                $n('cursos') . ' curso(s) · ' . $n('series') . ' série(s)',
                $n('cursos') > 0
                    ? 'curso cadastrado, mas falta série nos cursos com progressão anual'
                    : 'nenhum curso cadastrado',
                'Há ao menos um curso. Se ele tiver progressão anual, tem série.',
                'Série sem curso: a matriz e a turma não encontram o percurso.'
            ),
            $passo(
                'componentes',
                'Componentes Curriculares',
                self::GRUPO_ESTRUTURA,
                $n('componentes') > 0,
                '/admin/componentes-curriculares',
                $n('componentes') . ' componente(s)',
                'nenhum componente cadastrado',
                'Há componentes ativos para montar a matriz.',
                'Matriz vazia: faltam componentes cadastrados.'
            ),
            $passo(
                'matriz',
                'Matriz Curricular',
                self::GRUPO_ESTRUTURA,
                $n('matrizes') > 0,
                '/admin/matrizes-curriculares',
                $n('matrizes') . ' matriz(es) cadastrada(s)',
                'nenhuma matriz cadastrada',
                'Há ao menos uma matriz com os componentes da série.',
                'Série sem matriz: o boletim e a grade não encontram as disciplinas.'
            ),
            $passo(
                'calendario',
                'Calendário Letivo',
                self::GRUPO_ESTRUTURA,
                $ok('calendario'),
                '/admin/calendario-letivo',
                'Ano letivo com calendário cadastrado',
                'calendário não configurado',
                'O ano letivo tem calendário cadastrado.',
                'Sem calendário, a frequência e o gerador de avaliações não sabem pular feriado.'
            ),
            $passo(
                'professores',
                'Professores',
                self::GRUPO_PESSOAS,
                $n('professores') > 0,
                '/admin/teachers',
                $n('professores') . ' professor(es)',
                'nenhum professor cadastrado',
                'Há ao menos um professor ativo.',
                'Grade sem professor: o diário e o lançamento não abrem.'
            ),
            $passo(
                'salas',
                'Salas / Ambientes',
                self::GRUPO_PESSOAS,
                $n('salas') > 0,
                '/admin/salas',
                $n('salas') . ' sala(s)',
                'nenhuma sala cadastrada',
                'Há salas cadastradas para usar como sala padrão e na grade.',
                'Turma e grade sem sala: a aula fica sem ambiente definido.'
            ),
            $passo(
                'turmas',
                'Turmas',
                self::GRUPO_PESSOAS,
                $n('turmas') > 0,
                '/admin/turmas',
                $n('turmas') . ' turma(s) do ano',
                'nenhuma turma criada neste ano',
                'Existem turmas do ano letivo, com curso, série e matriz.',
                'Sem turma, não há lançamento de notas nem fechamento.'
            ),
            $passo(
                'alunos',
                'Alunos',
                self::GRUPO_PESSOAS,
                $n('alunos_matriculados') > 0,
                '/admin/students',
                $n('alunos_matriculados') . ' aluno(s) matriculado(s)',
                'nenhum aluno matriculado nas turmas',
                'Há alunos matriculados nas turmas — não apenas cadastrados.',
                'Cadastrar e não matricular: o aluno existe, mas não aparece em nota, chamada nem boletim.'
            ),
            $passo(
                'grade',
                'Grade Horária',
                self::GRUPO_PESSOAS,
                $n('aulas_grade') > 0,
                '/admin/grade-horaria',
                $n('aulas_grade') . ' aula(s) na grade',
                'nenhuma grade montada',
                'Há ao menos uma aula cadastrada na grade.',
                'Sem grade, o diário não sabe qual aula lançar.'
            ),
            $passo(
                'regra',
                'Regras de Aprovação',
                self::GRUPO_AVALIA,
                $n('regras_aprovacao') > 0,
                '/admin/regras-academicas',
                $n('regras_aprovacao') . ' regra(s) versionada(s)',
                'nenhuma regra cadastrada',
                'Há uma regra ativa com média mínima e, se usar, frequência.',
                'Sem regra, o fechamento não calcula aprovado/reprovado.'
            ),
            $passo(
                'tipos_nota',
                'Tipos de Nota',
                self::GRUPO_AVALIA,
                $n('tipos_nota') > 0,
                '/admin/provas/tipos-avaliacao',
                $n('tipos_nota') . ' tipo(s) cadastrado(s)',
                'nenhum tipo de nota cadastrado',
                'Há ao menos um tipo de nota ativo.',
                'Avaliação sem tipo: o lançamento não classifica a nota.'
            ),
            $passo(
                'quadro',
                'Quadro de Notas',
                self::GRUPO_AVALIA,
                $n('quadros') > 0,
                '/admin/quadros-notas',
                $n('quadros') . ' quadro(s) ativo(s)',
                'nenhum quadro cadastrado',
                'Existe pelo menos um quadro ativo com colunas (AV1/S1…) ou blocos de disciplinas.',
                'Avaliação sem quadro: a nota é lançada e não cai no boletim.'
            ),
            $passo(
                'modelo',
                'Modelo de Boletim',
                self::GRUPO_AVALIA,
                $n('boletins') > 0,
                '/admin/boletins',
                $n('boletins') . ' modelo(s) cadastrado(s)',
                'nenhum modelo cadastrado',
                'O modelo aponta para uma regra de aprovação e as disciplinas oficiais.',
                'Modelo sem regra: o boletim oficial não fecha a situação.'
            ),
        ];
    }

    public static function rotuloGrupo(string $grupo): string
    {
        return match ($grupo) {
            self::GRUPO_ESTRUTURA => 'Estrutura',
            self::GRUPO_PESSOAS => 'Pessoas e turmas',
            self::GRUPO_AVALIA => 'Como a escola avalia',
            default => $grupo,
        };
    }

    /**
     * @return array{
     *   ano:int,
     *   completos:int,
     *   total:int,
     *   percentual:int,
     *   passos:list<array<string,mixed>>,
     *   grupos:list<array{chave:string,label:string,passos:list<array<string,mixed>>}>,
     *   proximo:?array<string,mixed>
     * }
     */
    public function diagnosticar(int $anoLetivo = 0): array
    {
        $ano = $anoLetivo > 0 ? $anoLetivo : (int) date('Y');
        $estado = [
            'anos_letivos' => $this->contarTabela('ano_letivo'),
            'cursos' => $this->contarTabela('curso'),
            'series' => $this->contarTabela('serie'),
            'cursos_series' => $this->cursosSeriesProntos(),
            'componentes' => $this->contarTabela('materias'),
            'matrizes' => $this->contarTabela('matrizes_curriculares'),
            'professores' => $this->contarProfessoresAtivos(),
            'alunos_matriculados' => $this->contarAlunosMatriculados($ano),
            'turmas' => $this->contarTurmasDoAno($ano),
            'salas' => $this->contarTabela('school_locations'),
            'aulas_grade' => $this->contarAulasGradeDoAno($ano),
            'tipos_nota' => $this->contarTabela('provas_tipos_avaliacao'),
            'quadros' => $this->contarQuadrosAtivos(),
            'regras_aprovacao' => $this->contarRegrasAprovacao(),
            'boletins' => $this->contarBoletins(),
            'calendario' => $this->temCalendario($ano),
        ];
        $passos = self::montarPassos($estado);
        return self::empacotarDiagnostico($passos, $ano);
    }

    /**
     * Guia da trilha sem ler o banco — para a página pública (sem login).
     *
     * @return array<string,mixed>
     */
    public function guiaPublico(): array
    {
        $ano = (int) date('Y');
        $estado = [
            'anos_letivos' => 0,
            'cursos' => 0,
            'series' => 0,
            'cursos_series' => 0,
            'componentes' => 0,
            'matrizes' => 0,
            'calendario' => 0,
            'professores' => 0,
            'salas' => 0,
            'turmas' => 0,
            'alunos_matriculados' => 0,
            'aulas_grade' => 0,
            'regras_aprovacao' => 0,
            'tipos_nota' => 0,
            'quadros' => 0,
            'boletins' => 0,
        ];
        return self::empacotarDiagnostico(self::montarPassos($estado), $ano);
    }

    /**
     * @param list<array<string,mixed>> $passos
     * @return array<string,mixed>
     */
    private static function empacotarDiagnostico(array $passos, int $ano): array
    {
        $passos = self::anexarPrints($passos);
        $completos = 0;
        $proximo = null;
        foreach ($passos as &$p) {
            if (!empty($p['ok'])) {
                $completos++;
                continue;
            }
            if ($proximo === null) {
                $p['atual'] = true;
                $proximo = $p;
            }
        }
        unset($p);
        $total = count($passos);
        $grupos = [];
        foreach ($passos as $passo) {
            $chaveGrupo = (string) ($passo['grupo'] ?? '');
            if (!isset($grupos[$chaveGrupo])) {
                $grupos[$chaveGrupo] = [
                    'chave' => $chaveGrupo,
                    'label' => (string) ($passo['grupo_label'] ?? self::rotuloGrupo($chaveGrupo)),
                    'passos' => [],
                ];
            }
            $grupos[$chaveGrupo]['passos'][] = $passo;
        }

        return [
            'ano' => $ano,
            'completos' => $completos,
            'total' => $total,
            'percentual' => $total > 0 ? (int) round(($completos / $total) * 100) : 0,
            'passos' => $passos,
            'grupos' => array_values($grupos),
            'proximo' => $proximo,
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
            'matricula' => true,
        ];
        if (!isset($permitidas[$tabela])) {
            return 0;
        }
        if (!$this->tabelaExiste($tabela)) {
            return 0;
        }
        try {
            $row = Database::getInstance()->fetch('SELECT COUNT(*) AS n FROM `' . $tabela . '`');
            return (int) ($row['n'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function tabelaExiste(string $tabela): bool
    {
        try {
            $ok = Database::getInstance()->fetch(
                'SELECT 1 AS ok FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
                ['t' => $tabela]
            );
            return !empty($ok['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function colunaExiste(string $tabela, string $coluna): bool
    {
        try {
            $ok = Database::getInstance()->fetch(
                'SELECT 1 AS ok FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1',
                ['t' => $tabela, 'c' => $coluna]
            );
            return !empty($ok['ok']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function cursosSeriesProntos(): bool
    {
        if ($this->contarTabela('curso') <= 0) {
            return false;
        }
        if ($this->contarTabela('serie') > 0) {
            return true;
        }
        if (!$this->colunaExiste('curso', 'possui_serie')) {
            return false;
        }
        try {
            $row = Database::getInstance()->fetch(
                'SELECT COUNT(*) AS n FROM curso WHERE possui_serie = 0'
            );
            return (int) ($row['n'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function contarTurmasDoAno(int $ano): int
    {
        if (!$this->tabelaExiste('turmas')) {
            return 0;
        }
        try {
            if ($this->colunaExiste('turmas', 'ano_letivo')) {
                $row = Database::getInstance()->fetch(
                    'SELECT COUNT(*) AS n FROM turmas WHERE ano_letivo = :ano',
                    ['ano' => $ano]
                );
                return (int) ($row['n'] ?? 0);
            }
            if ($this->tabelaExiste('ano_letivo') && $this->colunaExiste('turmas', 'ano_letivo_id')) {
                $row = Database::getInstance()->fetch(
                    'SELECT COUNT(*) AS n
                     FROM turmas t
                     INNER JOIN ano_letivo a ON a.id = t.ano_letivo_id
                     WHERE a.ano = :ano',
                    ['ano' => $ano]
                );
                return (int) ($row['n'] ?? 0);
            }
        } catch (\Throwable $e) {
            return $this->contarTabela('turmas');
        }
        return $this->contarTabela('turmas');
    }

    private function contarAulasGradeDoAno(int $ano): int
    {
        if (!$this->tabelaExiste('grade_horaria')) {
            return 0;
        }
        if (!$this->tabelaExiste('turmas') || !$this->colunaExiste('grade_horaria', 'turma_id')) {
            return $this->contarTabela('grade_horaria');
        }
        try {
            if ($this->colunaExiste('turmas', 'ano_letivo')) {
                $row = Database::getInstance()->fetch(
                    'SELECT COUNT(*) AS n
                     FROM grade_horaria g
                     INNER JOIN turmas t ON t.id = g.turma_id
                     WHERE t.ano_letivo = :ano',
                    ['ano' => $ano]
                );
                return (int) ($row['n'] ?? 0);
            }
            if ($this->tabelaExiste('ano_letivo') && $this->colunaExiste('turmas', 'ano_letivo_id')) {
                $row = Database::getInstance()->fetch(
                    'SELECT COUNT(*) AS n
                     FROM grade_horaria g
                     INNER JOIN turmas t ON t.id = g.turma_id
                     INNER JOIN ano_letivo a ON a.id = t.ano_letivo_id
                     WHERE a.ano = :ano',
                    ['ano' => $ano]
                );
                return (int) ($row['n'] ?? 0);
            }
        } catch (\Throwable $e) {
            return $this->contarTabela('grade_horaria');
        }
        return $this->contarTabela('grade_horaria');
    }

    private function contarProfessoresAtivos(): int
    {
        if (!$this->tabelaExiste('professores')) {
            return 0;
        }
        if (!$this->colunaExiste('professores', 'ativo')) {
            return $this->contarTabela('professores');
        }
        try {
            $row = Database::getInstance()->fetch(
                'SELECT COUNT(*) AS n FROM professores WHERE ativo = 1'
            );
            return (int) ($row['n'] ?? 0);
        } catch (\Throwable $e) {
            return $this->contarTabela('professores');
        }
    }

    private function contarAlunosMatriculados(int $ano): int
    {
        if (!$this->tabelaExiste('matricula')) {
            return 0;
        }
        try {
            if ($this->tabelaExiste('ano_letivo') && $this->colunaExiste('matricula', 'ano_letivo_id')) {
                $row = Database::getInstance()->fetch(
                    'SELECT COUNT(DISTINCT m.aluno_id) AS n
                     FROM matricula m
                     INNER JOIN ano_letivo a ON a.id = m.ano_letivo_id
                     WHERE m.status = :status AND a.ano = :ano',
                    ['status' => 'ativa', 'ano' => $ano]
                );
                return (int) ($row['n'] ?? 0);
            }
            $row = Database::getInstance()->fetch(
                'SELECT COUNT(DISTINCT aluno_id) AS n FROM matricula WHERE status = :status',
                ['status' => 'ativa']
            );
            return (int) ($row['n'] ?? 0);
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function diretorioPrintsGuia(): ?string
    {
        $candidatos = [
            dirname(__DIR__, 2) . '/public/assets/guia-onboarding',
            dirname(__DIR__, 2) . '/storage/guia-onboarding',
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
     * @return array{como_fazer:list<string>,erros_comuns:list<string>,usado_depois_em:string,nota:string,prints:list<array{arquivo:string,rotulo:string}>}
     */
    private static function conteudoGuia(string $chave): array
    {
        $vazio = [
            'como_fazer' => [],
            'erros_comuns' => [],
            'usado_depois_em' => '',
            'nota' => '',
            'prints' => [],
        ];
        $todos = [
            'ano_letivo' => [
                'como_fazer' => [
                    'Clique em Novo Ano Letivo.',
                    'Informe o ano, a data de início e a data de fim.',
                    'Escolha a divisão do ano: bimestre, trimestre, semestre ou etapa única.',
                    'Marque Ano letivo ativo e salve. Os períodos nascem dessa divisão — não há cadastro separado.',
                ],
                'erros_comuns' => [
                    'Esquecer de marcar “ativo” — o ano existe, mas as outras telas não o assumem como corrente.',
                    'Errar a divisão do ano. Prova, boletim e fechamento usam essa configuração; mudar depois mexe em tudo que já foi lançado.',
                ],
                'usado_depois_em' => 'Turmas, matrículas, notas, boletim e fechamento.',
                'nota' => '',
                'prints' => [
                    ['arquivo' => '003-ano-letivo.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '004-ano-letivo-novo-novo-ano-letivo.jpg', 'rotulo' => 'Novo ano letivo'],
                ],
            ],
            'cursos_series' => [
                'como_fazer' => [
                    'Cadastre o curso (ex.: \Ensino Fundamental — Anos Finais).',
                    'Dentro dele, cadastre as séries que ele oferece.',
                    'Desmarque “Possui série” só para cursos sem progressão anual (curso extra).',
                ],
                'erros_comuns' => [
                    'Usar nome diferente do oficial. Esse nome sai no histórico escolar.',
                    'Criar curso regular e esquecer as séries — a matriz não tem onde se apoiar.',
                ],
                'usado_depois_em' => 'Matriz curricular, turmas e regras de aprovação.',
                'nota' => '',
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
                    'Se a escola junta disciplinas numa área (ex.: Língua Portuguesa), cadastre a área como componente pai e as disciplinas como filhas.',
                ],
                'erros_comuns' => [
                    'Cadastrar a disciplina e não incluir na matriz (passo seguinte). Ela existe, mas não entra no boletim.',
                    'Desmarcar “Permite avaliação” sem querer — a disciplina some do boletim.',
                    'Nome diferente do que a escola usa oficialmente. Depois sai errado no documento.',
                ],
                'usado_depois_em' => 'Matriz, grade horária, diário, quadro de notas e boletim.',
                'nota' => '',
                'prints' => [
                    ['arquivo' => '011-componentes.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '012-componentes-novo-novo-componente-curricular.jpg', 'rotulo' => 'Novo componente'],
                ],
            ],
            'matriz' => [
                'como_fazer' => [
                    'Crie a matriz para uma série (a matriz não é por ano letivo — vale até alguém editar).',
                    'Inclua os componentes oficiais daquela série com a carga horária de cada um.',
                    'Confira a carga total — é o número do qual a escola presta contas.',
                    'Na turma, aponte esta matriz. É assim que a turma herda as disciplinas.',
                ],
                'erros_comuns' => [
                    'Componente cadastrado e não incluído aqui. É a causa nº 1 de “a disciplina não aparece no boletim”.',
                    'Repetir a mesma carga em séries diferentes sem conferir: a carga pertence ao vínculo série + disciplina, não à disciplina.',
                ],
                'usado_depois_em' => 'Turmas, grade horária, diário, boletim e histórico.',
                'nota' => '',
                'prints' => [
                    ['arquivo' => '013-matrizes.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '014-matrizes-novo-nova-matriz.jpg', 'rotulo' => 'Nova matriz'],
                ],
            ],
            'calendario' => [
                'como_fazer' => [
                    'Defina a meta de dias letivos do ano.',
                    'Cadastre feriados, recessos e férias — nenhum deles conta como dia letivo.',
                    'Cadastre reposições, que somam dias letivos.',
                    'Confira a contagem final.',
                ],
                'erros_comuns' => [
                    'Esquecer feriados: a contagem fica maior que a real e a frequência sai errada.',
                    'Cadastrar evento que não é aula sem marcar “Descontar dos dias letivos”.',
                ],
                'usado_depois_em' => 'Frequência, diário e fechamento.',
                'nota' => 'No perfil Secretaria este item não aparece no menu lateral. Use o botão Abrir desta trilha.',
                'prints' => [
                    ['arquivo' => '016-calendario-letivo.jpg', 'rotulo' => 'Calendário'],
                    ['arquivo' => '017-calendario-letivo-novo-configurar.jpg', 'rotulo' => 'Configurar ano'],
                ],
            ],
            'professores' => [
                'como_fazer' => [
                    'Cadastre nome completo e e-mail institucional — é o acesso dele.',
                    'Vincule os componentes que ele leciona (ajuda na organização; a grade ainda lista todo professor ativo).',
                    'Confira que está ativo.',
                ],
                'erros_comuns' => [
                    'E-mail digitado errado: ninguém descobre até o dia do treinamento.',
                    'Deixar o professor inativo: aí sim ele some da grade.',
                ],
                'usado_depois_em' => 'Grade horária, diário e lançamento de notas.',
                'nota' => '',
                'prints' => [
                    ['arquivo' => '021-professores.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '022-professores-novo-novo-professor.jpg', 'rotulo' => 'Novo professor'],
                ],
            ],
            'salas' => [
                'como_fazer' => [
                    'Cadastre as salas com nome, capacidade, bloco e andar.',
                    'Cadastre também ambientes especiais: laboratório, quadra, biblioteca.',
                ],
                'erros_comuns' => [
                    'Nomes duplicados entre salas — depois ninguém sabe qual é qual na grade.',
                ],
                'usado_depois_em' => 'Sala padrão da turma e montagem da grade horária.',
                'nota' => 'A sala vem antes da turma porque o cadastro da turma pede a sala padrão.',
                'prints' => [
                    ['arquivo' => '032-salas.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '033-salas-novo-nova-sala.jpg', 'rotulo' => 'Nova sala'],
                ],
            ],
            'turmas' => [
                'como_fazer' => [
                    'Crie a turma com ano letivo, curso, série e turno.',
                    'Aponte a matriz curricular da série — é ela que define as disciplinas da turma.',
                    'Defina a sala padrão.',
                    'Professores e horários entram no passo da Grade Horária, não nesta tela.',
                ],
                'erros_comuns' => [
                    'Criar turma antes da matriz: ela nasce sem o vínculo das disciplinas.',
                    'Esquecer o ano letivo: a turma fica fora do ano que a trilha está montando.',
                ],
                'usado_depois_em' => 'Matrícula, grade horária, diário, notas e fechamento.',
                'nota' => 'A turma vem antes do aluno porque não dá para matricular em turma que não existe.',
                'prints' => [
                    ['arquivo' => '026-turmas.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '027-turmas-novo-nova-turma.jpg', 'rotulo' => 'Nova turma'],
                ],
            ],
            'alunos' => [
                'como_fazer' => [
                    'Cadastre o aluno com nome completo, data de nascimento e código. O login dele é nickname e senha, nesta mesma tela.',
                    'Depois, na ficha, vincule o responsável — sem ele não há comunicação nem app da família.',
                    'Matricule o aluno na turma em Ações rápidas na ficha. Cadastrar e matricular são dois atos.',
                    'Para volume, use Cursos e Séries → Ações → Importar alunos.',
                ],
                'erros_comuns' => [
                    'Cadastrar e não matricular. O aluno existe, mas não aparece em nota, chamada nem boletim.',
                    'Importar com a coluna de turma em branco ou com código que não existe.',
                    'Aluno que entra depois do início do ano sem a data de matrícula correta.',
                ],
                'usado_depois_em' => 'Tudo que é por aluno — nota, chamada, boletim e documentos.',
                'nota' => 'Este passo só fica pronto quando há matrícula ativa no ano, não só cadastro.',
                'prints' => [
                    ['arquivo' => '024-alunos.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '025-alunos-admin-students-create.jpg', 'rotulo' => 'Cadastrar aluno'],
                ],
            ],
            'grade' => [
                'como_fazer' => [
                    'Selecione a turma.',
                    'Para cada aula: dia da semana, horário de início e fim, componente e professor.',
                    'Gere o PDF e confira.',
                    'A lista de disciplinas mostra os componentes avaliáveis; a de professores, todos os ativos. Confira à mão se fecha com a matriz.',
                ],
                'erros_comuns' => [
                    'Mesmo professor em duas turmas no mesmo horário — o sistema ainda não bloqueia isso.',
                    'Grade que não fecha com a carga da matriz — confira a soma.',
                    'Disciplina da série que não está na matriz: o problema está no passo da Matriz Curricular.',
                ],
                'usado_depois_em' => 'Diário de classe, frequência e consulta do professor e do aluno.',
                'nota' => '',
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
                    'Defina arredondamento e casas decimais. Vincule ao ano letivo correto.',
                ],
                'erros_comuns' => [
                    'Assumir que o padrão do sistema é a regra da escola. Confira, não presuma.',
                    'Não vincular ao ano letivo correto. As regras são versionadas por ano — mexer em 2026 não altera 2025, e isso é proposital.',
                ],
                'usado_depois_em' => 'Cálculo da média, resultado final, boletim e fechamento.',
                'nota' => 'É este passo que faz o boletim sair certo. Se algo vier errado depois, o problema quase sempre está aqui — não na tela de notas.',
                'prints' => [
                    ['arquivo' => '035-regras.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '036-regras-novo-nova-regra.jpg', 'rotulo' => 'Nova regra'],
                ],
            ],
            'tipos_nota' => [
                'como_fazer' => [
                    'Cadastre cada natureza de nota que a escola aplica: prova bimestral, trabalho, simulado.',
                    'Escolha se é online (prova na plataforma) ou offline (a escola informa a nota).',
                    'Escolha evento único (informa uma vez no período) ou múltiplos (várias vezes, consolidadas depois).',
                    'Defina a escala e como fecha a nota final.',
                ],
                'erros_comuns' => [
                    'Escala diferente da que a escola usa: a nota entra fora do padrão do boletim.',
                    'Esquecer de definir o que acontece quando dois professores lançam o mesmo componente.',
                ],
                'usado_depois_em' => 'Quadro de notas, lançamento e boletim.',
                'nota' => '',
                'prints' => [
                    ['arquivo' => '040-tipos-nota.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '041-tipos-nota-novo-novo-tipo.jpg', 'rotulo' => 'Novo tipo'],
                ],
            ],
            'quadro' => [
                'como_fazer' => [
                    'Dê um nome ao quadro (ex.: “Provas semanais 2026”).',
                    'Crie as colunas — onde cada nota vai cair: \S1, S2, AV1.',
                    'Se a escola divide as disciplinas em blocos, crie os blocos e vincule as disciplinas.',
                    'Na hora de criar o evento em Lançamento de Notas, aponte este quadro e a coluna.',
                ],
                'erros_comuns' => [
                    'Criar bloco sem vincular disciplina: o quadro fica montado e não recebe nota.',
                    'Montar colunas que não correspondem ao que a escola aplica de verdade.',
                ],
                'usado_depois_em' => 'Lançamento de notas. As colunas do boletim nascem daqui, pelo evento — não pelo cadastro do modelo.',
                'nota' => 'Se todas as disciplinas usam as mesmas colunas, você não precisa criar bloco nenhum.',
                'prints' => [
                    ['arquivo' => '044-quadro-notas.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '045-quadro-notas-novo-novo-quadro.jpg', 'rotulo' => 'Novo quadro'],
                    ['arquivo' => '046-quadro-notas-wizard-2.jpg', 'rotulo' => 'Colunas'],
                ],
            ],
            'modelo' => [
                'como_fazer' => [
                    'Crie o modelo e escolha se é oficial (vai para o histórico) ou extra (curso complementar, não entra no histórico).',
                    'Vincule a regra de aprovação que ele usa — média, frequência e arredondamento saem dela.',
                    'Marque as disciplinas (áreas oficiais) e, se quiser, as séries ou turmas.',
                    'Defina quem vê: aluno, família.',
                    'Use Simular boletim e confira contra a conta feita à mão para um aluno.',
                ],
                'erros_comuns' => [
                    'Publicar sem simular. A simulação existe justamente para ver o resultado antes de valer.',
                    'Confundir modelo oficial com extra — o extra não entra no histórico.',
                    'Procurar o campo de quadro nesta tela: ele não existe aqui. O quadro entra no evento de Lançamento de Notas.',
                ],
                'usado_depois_em' => 'Emissão de boletins, fechamento e histórico escolar.',
                'nota' => 'O quadro de notas não se escolhe neste cadastro. Sem o quadro apontado no evento, a nota não chega no boletim.',
                'prints' => [
                    ['arquivo' => '056-modelo-boletim.jpg', 'rotulo' => 'Listagem'],
                    ['arquivo' => '057-modelo-boletim-novo-novo-modelo.jpg', 'rotulo' => 'Novo modelo'],
                    ['arquivo' => '060-modelo-boletim-acao-simular-boletim.jpg', 'rotulo' => 'Simular boletim'],
                ],
            ],
        ];
        $guia = $todos[$chave] ?? $vazio;
        $guia['nota'] = (string) ($guia['nota'] ?? '');
        return $guia;
    }

    private function temCalendario(int $ano): bool
    {
        try {
            $cal = new SchoolCalendarService();
            return is_array($cal->getAno($ano));
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!class_exists('ImplantacaoAcademicaService', false) && class_exists(__NAMESPACE__ . '\\ImplantacaoAcademicaService', false)) {
    class_alias(__NAMESPACE__ . '\\ImplantacaoAcademicaService', 'ImplantacaoAcademicaService');
}
