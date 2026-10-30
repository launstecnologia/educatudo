<?php

namespace App\Dominio\Escola\Services;


use App\Modulos\Boletins\Models\Boletim;
use App\Modulos\RegrasAcademicas\Models\RegraAcademica;

use App\Core\Database;

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
                'intro' => (string) ($guia['intro'] ?? ''),
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
                $n('regras_aprovacao') . ' regra(s) cadastrada(s)',
                'nenhuma regra cadastrada',
                'Há uma regra com média, escala e, se a escola usar, recuperação.',
                'Sem regra, o evento avaliativo e o boletim não sabem o que é aprovação.'
            ),
            $passo(
                'eventos',
                'Eventos Avaliativos',
                self::GRUPO_AVALIA,
                $n('eventos') > 0,
                '/admin/eventos-avaliativos',
                $n('eventos') . ' evento(s) cadastrado(s)',
                'nenhum evento avaliativo',
                'Há ao menos um evento no ano, com turma e disciplina.',
                'Sem evento, não há onde lançar a nota do período.'
            ),
            $passo(
                'demonstrativo',
                'Demonstrativo de Notas',
                self::GRUPO_AVALIA,
                $n('boletins') > 0,
                '/admin/boletins',
                $n('boletins') . ' demonstrativo(s) cadastrado(s)',
                'nenhum demonstrativo cadastrado',
                'O demonstrativo aponta a regra e as disciplinas do período.',
                'Sem demonstrativo, a conta do período não aparece para a coordenação.'
            ),
            $passo(
                'boletim',
                'Boletim',
                self::GRUPO_AVALIA,
                $n('boletim_layouts') > 0,
                '/admin/eventos-avaliativos/boletim',
                ($n('boletim_layouts') === 1 ? '1 boletim cadastrado' : $n('boletim_layouts') . ' boletins cadastrados'),
                'nenhum boletim cadastrado',
                'Há um layout de boletim no ano, com as faixas da vida escolar.',
                'Sem o boletim, a ficha e o portal não têm o documento oficial.'
            ),
            $passo(
                'relatorio',
                'Relatório completo',
                self::GRUPO_AVALIA,
                $n('boletins') > 0 || $n('boletim_layouts') > 0,
                '/admin/reports/boletim-coordenacao?fonte=vida_escolar',
                ($n('boletim_layouts') > 0 ? 'boletim pronto para emitir' : 'falta o boletim')
                    . ($n('boletins') > 0 ? ' · demonstrativo pronto' : ' · falta o demonstrativo'),
                'ainda não há documento para emitir',
                'O relatório emite o boletim oficial ou o demonstrativo já cadastrado.',
                'Sem demonstrativo e sem boletim, o relatório sai vazio.'
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
            'regras_aprovacao' => $this->contarRegrasAprovacao(),
            'eventos' => $this->contarEventosAvaliativos(),
            'boletins' => $this->contarBoletins(),
            'boletim_layouts' => $this->contarBoletimLayouts(),
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
            'eventos' => 0,
            'boletins' => 0,
            'boletim_layouts' => 0,
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

    private function contarEventosAvaliativos(): int
    {
        if (!$this->tabelaExiste('eventos_avaliativos')) {
            return 0;
        }
        try {
            if ($this->colunaExiste('eventos_avaliativos', 'deleted_at')) {
                $row = Database::getInstance()->fetch(
                    'SELECT COUNT(*) AS n FROM eventos_avaliativos WHERE deleted_at IS NULL'
                );
                return (int) ($row['n'] ?? 0);
            }
            return $this->contarTabela('eventos_avaliativos');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function contarBoletimLayouts(): int
    {
        if (!$this->tabelaExiste('boletim_layouts')) {
            return 0;
        }
        try {
            $row = Database::getInstance()->fetch('SELECT COUNT(*) AS n FROM boletim_layouts');
            return (int) ($row['n'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function diretorioPrintsGuia(): ?string
    {
        $raiz = dirname(__DIR__, 4);
        $candidatos = [
            $raiz . '/public/assets/guia-onboarding',
            $raiz . '/storage/guia-onboarding',
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
                $print = self::marcarPrint($print, $dir);
            }
            unset($print);
            $passo['prints'] = $prints;
            $como = is_array($passo['como_fazer'] ?? null) ? $passo['como_fazer'] : [];
            foreach ($como as &$item) {
                if (!is_array($item) || !is_array($item['print'] ?? null)) {
                    continue;
                }
                $item['print'] = self::marcarPrint($item['print'], $dir);
            }
            unset($item);
            $passo['como_fazer'] = $como;
        }
        unset($passo);
        return $passos;
    }

    /**
     * @param array<string,mixed> $print
     * @return array<string,mixed>
     */
    private static function marcarPrint(array $print, ?string $dir): array
    {
        $arquivo = (string) ($print['arquivo'] ?? '');
        $caminho = ($dir !== null && $arquivo !== '') ? $dir . '/' . $arquivo : '';
        $print['existe'] = $caminho !== '' && is_file($caminho);
        $print['url'] = '/assets/guia-onboarding/' . rawurlencode($arquivo);
        if ($print['existe']) {
            $print['url'] .= '?v=' . filemtime($caminho);
        }
        return $print;
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
        $comPrint = static function (string $texto, string $arquivo, string $rotulo): array {
            return ['texto' => $texto, 'print' => ['arquivo' => $arquivo, 'rotulo' => $rotulo]];
        };
        $todos = [
            'ano_letivo' => [
                'como_fazer' => [
                    'No menu, abra Acadêmico → Estrutura → Ano Letivo e clique em Novo ano letivo.',
                    'Informe o ano (ex.: 2026), a data de início e a data de fim do calendário escolar.',
                    'Escolha como o ano se divide: bimestre, trimestre, semestre ou etapa única. Essa escolha cria os períodos. Não existe outra tela para cadastrar o 1º trimestre.',
                    'Marque Ano letivo ativo e salve. Sem o ativo, as outras telas não assumem este ano como o corrente.',
                    'Confira na listagem se o ano aparece com a divisão certa. Prova, evento avaliativo e boletim usam esses períodos o ano inteiro.',
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
                    'Abra Cursos e Séries e clique em Novo curso. O nome precisa ser o oficial, porque ele sai no histórico (ex.: Ensino Médio, Ensino Fundamental — Anos Finais).',
                    'Se o curso tem progressão anual, deixe “Possui série” marcado. Desmarque só em curso extra, sem série (música, robótica).',
                    'Dentro do curso, cadastre cada série ou ano escolar (1º ano, 6º ano, 1ª série). Uma série sem curso não entra na matriz nem na turma.',
                    'Repita para cada etapa da escola. Fundamental e Médio são cursos diferentes, mesmo no mesmo ano letivo.',
                    'Na listagem, confira se cada curso regular mostra as séries dele. A cor do curso, se a escola usar, ajuda a identificar a etapa no calendário.',
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
                    'Abra Componentes Curriculares e clique em Novo componente. Cadastre uma disciplina por vez: nome oficial e sigla. A sigla é o que aparece na grade e no boletim.',
                    'Informe a área do conhecimento. Ela entra no censo e organiza a lista.',
                    'Deixe Permite avaliação marcado em toda disciplina que recebe nota. Desmarcado, ela some do boletim e do lançamento.',
                    'Se a escola agrupa disciplinas numa área (ex.: Língua Portuguesa com redação e gramática), cadastre a área como componente pai e as disciplinas como filhas.',
                    'Salve e volte à listagem. O componente existe, mas ainda não entra no boletim: isso só acontece quando ele for incluído na matriz da série, no passo seguinte.',
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
                    'Abra Matriz Curricular e crie uma matriz para cada série. A matriz não é por ano letivo: ela vale até alguém editar.',
                    'Inclua só os componentes oficiais daquela série. Componente cadastrado e deixado de fora daqui não aparece no boletim.',
                    'Em cada componente, informe a carga horária daquele vínculo. A carga é da série com a disciplina, não da disciplina sozinha. Duas séries podem ter cargas diferentes.',
                    'Some a carga e confira o total. É o número de que a escola presta contas.',
                    'Guarde o nome da matriz. No cadastro da turma, você aponta esta matriz para a turma herdar as disciplinas.',
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
                    'Abra Calendário Letivo e escolha o ano. Defina a meta de dias letivos.',
                    'Cadastre feriados, recessos e férias. Nenhum deles conta como dia letivo. Se o evento não é aula, marque para descontar dos dias letivos.',
                    'Cadastre reposições. Elas somam dia letivo.',
                    'Confira a contagem no final da tela. Esquecer feriado deixa a conta maior que a real e a frequência sai errada.',
                    'As provas dos eventos avaliativos também aparecem no dia, com horário por turma. O calendário mostra o que já foi publicado no evento. Ele não cria a prova.',
                ],
                'erros_comuns' => [
                    'Esquecer feriados: a contagem fica maior que a real e a frequência sai errada.',
                    'Cadastrar evento que não é aula sem marcar “Descontar dos dias letivos”.',
                ],
                'usado_depois_em' => 'Frequência, diário e fechamento.',
                'nota' => 'No perfil Secretaria o calendário pode não aparecer no menu. No painel, abra por esta trilha.',
                'prints' => [
                    ['arquivo' => '016-calendario-letivo.jpg', 'rotulo' => 'Calendário'],
                    ['arquivo' => '017-calendario-letivo-novo-configurar.jpg', 'rotulo' => 'Configurar ano'],
                ],
            ],
            'professores' => [
                'como_fazer' => [
                    'Abra Professores e clique em Novo professor. Preencha nome completo e o e-mail institucional. Esse e-mail é o acesso dele.',
                    'Vincule os componentes que ele leciona. Isso organiza o cadastro. A grade, na hora de montar o horário, ainda lista todo professor ativo.',
                    'Deixe o professor ativo e salve. Inativo, ele some da grade e do lançamento.',
                    'Confira o e-mail antes de sair. Erro de digitação só aparece no dia em que ele tenta entrar.',
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
                    'Abra Salas / Ambientes antes de criar a turma. O cadastro da turma pede uma sala padrão.',
                    'Clique em Nova sala. Informe nome, capacidade, bloco e andar. Use um nome que a coordenação reconheça na grade (ex.: Sala 12, Lab. Ciências).',
                    'Cadastre também os ambientes que não são sala de aula: laboratório, quadra, biblioteca.',
                    'Evite dois ambientes com o mesmo nome. Na grade, ninguém distingue qual é qual.',
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
                    'Abra Turmas e clique em Nova turma. Escolha o ano letivo, o curso, a série e o turno. Sem o ano, a turma fica fora do ciclo que você está montando.',
                    'Aponte a matriz curricular da série. É ela que define quais disciplinas a turma tem. Turma criada antes da matriz nasce sem esse vínculo.',
                    'Escolha a sala padrão. Professor e horário não entram aqui: isso é a Grade Horária, no passo seguinte.',
                    'Dê um nome curto que a escola usa no dia a dia (1A, 6ºB). Salve e confira na listagem se curso, série e ano estão certos.',
                    'Repita para cada turma do ano. Duas turmas da mesma série (1A e 1B) compartilham a matriz e têm salas diferentes.',
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
                    'Abra Alunos e cadastre nome completo, data de nascimento e código. O login é nickname e senha, nesta mesma tela. O código (ou o RA) é o que a planilha de notas usa depois.',
                    'Salvar o cadastro não coloca o aluno na turma. Abra a ficha e, em Ações rápidas, matricule na turma do ano. Sem matrícula ativa, ele não entra em nota, chamada nem boletim.',
                    'Na ficha, vincule o responsável. Sem responsável não há comunicação nem app da família.',
                    'Para muitos alunos de uma vez, use Cursos e Séries → Ações → Importar alunos. A coluna de turma precisa ser um código que já existe. Turma em branco ou código errado deixa o aluno de fora.',
                    'Aluno que entra no meio do ano: informe a data de matrícula correta. Ela não é a mesma coisa que o ano letivo.',
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
                    'Abra Grade Horária, selecione a turma e clique em Nova aula.',
                    'Para cada aula: dia da semana, horário de início, horário de fim, componente e professor. A lista de disciplinas mostra os componentes avaliáveis. A de professores mostra os ativos.',
                    'Repita até cobrir a semana. Confira à mão se a soma fecha com a carga da matriz. O sistema ainda não bloqueia o mesmo professor em duas turmas no mesmo horário.',
                    'Se uma disciplina da série não aparece, o problema está na matriz, não nesta tela.',
                    'Gere o PDF e confira com a coordenação antes de liberar o diário. Sem grade, o diário não sabe qual aula lançar e a frequência não tem onde se apoiar.',
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
                    $comPrint('No menu, abra Como a escola avalia → Regras de Aprovação → Nova regra de aprovação.', '081-regras-nova.jpg', 'Nova regra'),
                    $comPrint('Dê o nome que a coordenação reconhece e deixe Regra ativa marcado. Em Vale para, escolha o ano letivo, o ciclo (curso) e a série. Campo em branco vale para todos. Os cards de avaliação já aparecem embaixo: cada trimestre ou bimestre do ano vira um card. São esses períodos que você abre em Eventos Avaliativos.', '082-regras-identificacao.jpg', 'Nome, ano e cards'),
                    $comPrint('Em Critérios de Aprovação, preencha como o aluno é aprovado. Escala de Avaliação diz como a nota é registrada. No select: Numérica (0 a 10), Numérica (0 a 100), Conceitual (A, B, C, D), Conceitual (A+, A, B, C...) e Conceitual (Descritiva). Na escala numérica aparece a Média Mínima para Aprovação: a nota a partir da qual o aluno passa, e ela precisa caber na escala. Na conceitual, no lugar da média, aparece o conceito mínimo: aprova quem ficar nele ou em um melhor. Frequência Mínima Exigida (%) é o percentual de presença, por exemplo 75. Regra de Arredondamento tem Arredondamento convencional e Faixa .00 / .50 / próximo inteiro, que encaixa a nota em ,00, ,50 ou no inteiro seguinte. Casas Decimais da Nota tem Nenhuma (6), 1 casa (6,5) e 2 casas (6,50): é como a nota aparece depois de calculada. Exigir Frequência Mínima para Aprovação, marcado, exige média e frequência; desmarcado, a frequência não entra na aprovação. Avaliação Baseada Apenas na Frequência, marcado, aprova só pela presença, sem exigir média.', '085-regras-criterios.jpg', 'Critérios de aprovação'),
                    $comPrint('Em Demonstrativo e boletim, defina o documento e quem o vê. Modelo tem duas opções. Modelo oficial é a matriz regular: entra no histórico e na vida escolar. Modelo extra é curso complementar, como música ou robótica, e fica de fora do histórico oficial. Disponibilidade: desmarcado, a pessoa não vê este demonstrativo nem este boletim. Disponível para aluno libera a consulta do estudante. Disponível para famílias libera a consulta de pais. Disciplinas são as áreas oficiais que entram nesta regra. Marque só as que valem para este curso. O que ficar desmarcado não aparece no demonstrativo nem no boletim. Os desdobramentos de cada área vêm do cadastro de Componentes Curriculares, não se cadastram aqui.', '087-regras-modelo.jpg', 'Demonstrativo e boletim'),
                    $comPrint('Recuperação desta regra lista as recuperações desta regra. Nova recuperação abre o cadastro. Cada linha mostra o nome, quando acontece, quem faz (quem ficou abaixo da nota e se quem já passou também pode fazer para aumentar), a nota máxima e o efeito da nota. O lápis edita. A lixeira exclui.', '089-regras-recuperacao-lista.jpg', 'Lista de recuperações'),
                    $comPrint('No painel, Nome é como a coordenação chama a prova, por exemplo Rec Trimestral. Quando diz em que momento ela acontece. No select: A cada Período Letivo (o período ganha o card da avaliação e, ao lado, o card da recuperação), A cada dois ciclos (uma prova vale para um par de períodos) e Rec Final (uma prova só, no fim do ano, com card próprio). Se escolher A cada dois ciclos, aparece A nota substitui: Os dois ciclos do par, Cada ciclo do par, Só o primeiro ciclo ou Só o último ciclo. O que a nota faz diz como a recuperação entra na média. No select: Substitui se for maior, Substitui a menor nota, (Média + REC) / 2, Soma na pontuação e Fórmula própria. Em Fórmula própria aparece a área para montar a conta com Média e REC. Faz quem ficou abaixo de é o corte: quem ficou sob essa nota fica de recuperação. Nota máxima da REC é o teto da prova. Quem não ficou de recuperação também pode fazer, marcado, deixa quem já passou fazer se quiser, para aumentar a nota.', '089-regras-recuperacao.jpg', 'Campos da recuperação'),
                    $comPrint('Salve. A regra fica versionada por ano: alterar 2026 não reescreve 2025. Demonstrativo e boletim só apontam para esta regra. Eles não pedem a média de novo.', '090-regras-salvar.jpg', 'Salvar'),
                ],
                'erros_comuns' => [
                    'Escala diferente da nota que o professor lança: a média sai fora do padrão.',
                    'Regra sem curso quando a escola tem etapas diferentes. Ensino Médio e Fundamental não precisam da mesma média.',
                    'Mexer na regra de 2026 esperando alterar 2025. Cada ano tem a sua.',
                ],
                'usado_depois_em' => 'Eventos avaliativos, demonstrativo, boletim e o relatório.',
                'intro' => 'A regra de aprovação é o combinado da escola para aquele ano e aquele curso. Ela diz a média mínima, a frequência exigida, a escala da nota e se existe recuperação. Prova, demonstrativo e boletim usam esta regra. Eles não pedem a média de novo.',
                'nota' => 'A regra é o contrato da aprovação. Evento, demonstrativo e boletim só apontam para ela.',
                'prints' => [],
            ],
            'eventos' => [
                'como_fazer' => [
                    $comPrint('Abra Como a escola avalia → Eventos Avaliativos. A lista mostra a regra de nota, o curso e o ano. Clique em Abrir na regra que você acabou de criar.', '091-eventos-lista.jpg', 'Abrir a regra'),
                    $comPrint('A próxima tela é o mapa do ano: um card por período (1º trimestre, 2º, 3º) e, embaixo, os cards de recuperação. Clique no período em que a prova vai acontecer.', '092-eventos-mapa.jpg', 'Mapa do ano'),
                    $comPrint('Crie o evento: nome, sigla curta (ex.: PS para prova semanal, PB para prova bimestral), turmas, datas e disciplinas. Sem disciplina, não há aba para lançar a nota.', '095-eventos-form.jpg', 'Cadastro do evento'),
                    $comPrint('Salvar um rascunho publica o evento. No mapa, a borda do período fica amarela enquanto está em rascunho e verde quando está publicado. Período vazio continua com a borda neutra.', '094-eventos-publicado.jpg', 'Período publicado'),
                    $comPrint('O evento principal não lança nota. A nota entra no subevento. No menu Ações do subevento, abra Lançar notas.', '093-eventos-lista-periodo.jpg', 'Subeventos'),
                    $comPrint('A tela de lançamento separa uma aba por disciplina. A primeira já vem selecionada. Digite a nota de cada aluno, de 0 a 10, e salve. Nota já salva fica bloqueada até você desbloquear com a senha de acesso.', '096-eventos-abas.jpg', 'Abas por disciplina'),
                    $comPrint('Para lançar em lote, clique em Importar planilha. Baixe o modelo: ele já vem com o código do aluno, a disciplina e a turma. Preencha a coluna nota. Código é o código do aluno ou o RA. Célula vazia é ignorada. Código ou disciplina que não existem são pulados.', '097-eventos-planilha.jpg', 'Importar planilha'),
                    $comPrint('A planilha só substitui nota já lançada se você marcar Substituir e a edição estiver desbloqueada com a senha. Sem isso, a nota que já está na tela permanece.', '098-eventos-substituir.jpg', 'Substituir nota'),
                    $comPrint('A recuperação não é uma prova do período. Ela tem o card vermelho próprio. A nota dela não entra sozinha na média do bimestre: quem define isso é a regra de aprovação.', '099-eventos-recuperacao.jpg', 'Card da recuperação'),
                ],
                'erros_comuns' => [
                    'Evento sem disciplina: não há aba para lançar a nota.',
                    'Planilha com código que não é o do aluno. O arquivo modelo já vem preenchido.',
                    'Recuperação lançada como prova do período. A recuperação tem o evento próprio e não entra sozinha na média do bimestre.',
                ],
                'usado_depois_em' => 'Demonstrativo de notas, boletim e calendário letivo.',
                'nota' => 'A borda do período fica amarela enquanto o evento está em rascunho e verde quando está publicado.',
                'prints' => [],
            ],
            'demonstrativo' => [
                'como_fazer' => [
                    $comPrint('Abra Como a escola avalia → Demonstrativo de notas. Esta tela é a conta detalhada do período. Ela não é o documento oficial da vida escolar.', '100-demonstrativo-lista.jpg', 'Lista de demonstrativos'),
                    $comPrint('Clique em Novo modelo. Escolha a regra de aprovação e salve. Disciplinas, público (aluno e família) e se é oficial ou extra saem dessa regra. Não se marca de novo aqui.', '100-demonstrativo-lista.jpg', 'Novo modelo'),
                    $comPrint('Volte e abra a aba Períodos e fórmulas. Cada período do ano aparece como um evento. Abra o período e monte as colunas que a escola usa (prova semanal, prova bimestral, trabalho) e a fórmula da média daquele período.', '101-demonstrativo-periodos.jpg', 'Períodos e fórmulas'),
                    $comPrint('Escreva a fórmula com parênteses. (m1 + m2 + m3) / 3 divide a soma. m1 + m2 + m3 / 3 divide só o último número e a média sai errada.', '101-demonstrativo-periodos.jpg', 'Fórmula do período'),
                    $comPrint('A coluna lê a nota lançada no evento avaliativo. Sem lançamento, a coluna fica vazia. O demonstrativo não inventa média.', '096-eventos-abas.jpg', 'Nota ainda não lançada'),
                    $comPrint('Confira a conta de um aluno, na mão, antes de liberar para a família. Quem vê o demonstrativo foi marcado na regra, em Disponível para aluno e Disponível para famílias.', '087-regras-modelo.jpg', 'Quem pode ver'),
                ],
                'erros_comuns' => [
                    'Fórmula com a divisão só no último termo. (m1 + m2 + m3) / 3 não é a mesma conta que m1 + m2 + m3 / 3.',
                    'Demonstrativo sem as disciplinas da regra: a matéria fica de fora da conta.',
                    'Tratar o demonstrativo como boletim oficial. Ele mostra a conta do período; o documento da vida escolar é o boletim.',
                ],
                'usado_depois_em' => 'Consulta da coordenação, do aluno e da família, e o relatório do demonstrativo.',
                'nota' => 'O demonstrativo lê as notas dos eventos avaliativos. Sem evento lançado, a coluna fica vazia.',
                'prints' => [],
            ],
            'boletim' => [
                'como_fazer' => [
                    $comPrint('Abra Como a escola avalia → Boletim. Este é o documento oficial. O demonstrativo continua existindo ao lado, com a conta do período.', '102-boletim-lista.jpg', 'Lista de boletins'),
                    $comPrint('Clique em Novo boletim, escolha o ano e a regra de aprovação. As disciplinas e o público vêm da regra. Cada turma do ano usa um documento. Dois boletins oficiais para a mesma turma no mesmo ano se atropelam.', '102-boletim-lista.jpg', 'Novo boletim'),
                    $comPrint('Abra o layout. São três etapas: Linhas, Fórmula e Revisão.', '103-boletim-layout.jpg', 'Etapas do layout'),
                    $comPrint('Em Linhas, cada faixa é uma linha do quadro (1º trimestre, 2º, 3º e Final). Clique na coluna — Média, Faltas, Recuperação, Situação — para colocá-la na faixa marcada. O × devolve a coluna para a lista. Faixa sem a média daquele período imprime traço.', '103-boletim-layout.jpg', 'Linhas do quadro'),
                    $comPrint('Em Fórmula, monte a média final arrastando as médias dos períodos e os sinais. Em branco, o sistema usa a média simples dos períodos. Use parênteses na divisão, do mesmo jeito que no demonstrativo.', '103-boletim-layout.jpg', 'Fórmula da média final'),
                    $comPrint('Em Revisão, o quadro mostra números de exemplo. Confira se cada período tem a coluna de média e se a faixa Final está completa. Salve.', '103-boletim-layout.jpg', 'Revisão'),
                    $comPrint('A ficha do aluno, o portal e o relatório do boletim leem este layout. Sem ele, o documento oficial não tem onde se desenhar.', '102-boletim-lista.jpg', 'Boletim pronto'),
                ],
                'erros_comuns' => [
                    'Layout sem a média de um período: o boletim imprime traço naquela coluna.',
                    'Fórmula da média final com a divisão só no último termo.',
                    'Dois boletins oficiais para a mesma turma no mesmo ano. Cada turma tem um documento.',
                ],
                'usado_depois_em' => 'Ficha do aluno, portal e o relatório do boletim.',
                'nota' => 'O boletim é o documento oficial. O demonstrativo continua sendo a conta detalhada do período.',
                'prints' => [],
            ],
            'relatorio' => [
                'como_fazer' => [
                    $comPrint('Abra Como a escola avalia → Relatórios. A tela é só de emissão: imprime o que o evento, o demonstrativo e o boletim já calcularam.', '104-relatorio-boletim.jpg', 'Tela do relatório'),
                    $comPrint('Em Exibir, escolha Boletim ou Demonstrativo de notas. São dois documentos. O boletim é o oficial. O demonstrativo é a conta detalhada do período e usa a versão vigente.', '105-relatorio-demonstrativo.jpg', 'Boletim ou demonstrativo'),
                    $comPrint('Escolha o ano letivo e o período. Todos junta o ano. Um trimestre mostra só aquele período.', '104-relatorio-boletim.jpg', 'Ano e período'),
                    $comPrint('Filtre curso, turma ou um aluno (nome, RA ou código). Nota abaixo de restringe quem ficou sob o corte. Em Matérias, dá para ver todas ou só as que estão abaixo desse corte.', '104-relatorio-boletim.jpg', 'Filtros'),
                    $comPrint('Se a entrega precisa de assinatura, marque Incluir campo de assinatura ao lado do nome.', '104-relatorio-boletim.jpg', 'Assinatura'),
                    $comPrint('Clique em Gerar relatório e confira um aluno conhecido antes de entregar para a família. Período sem nota lançada sai em branco. Turma sem layout de boletim não tem documento oficial para emitir.', '104-relatorio-boletim.jpg', 'Gerar relatório'),
                ],
                'erros_comuns' => [
                    'Emitir o boletim quando a coordenação queria a conta detalhada. São duas fontes na mesma tela.',
                    'Período sem nota lançada: a coluna sai em branco. O relatório não inventa média.',
                    'Turma sem boletim vinculado: o documento oficial não tem layout para ela.',
                ],
                'usado_depois_em' => 'Entrega para a família, conselho e arquivo da coordenação.',
                'nota' => 'O relatório não cadastra nota. Ele imprime o que o evento, o demonstrativo e o boletim já calcularam.',
                'prints' => [],
            ],
        ];
        $guia = $todos[$chave] ?? $vazio;
        $guia['nota'] = (string) ($guia['nota'] ?? '');
        $guia['intro'] = (string) ($guia['intro'] ?? '');
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
