<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Education/ClassDiary.php';
require_once __DIR__ . '/../Models/Education/SchoolAbsence.php';

/**
 * EducaTudo - ClassDiaryService
 *
 * Fachada de negócio do Diário de Classe usada pelo acompanhamento e pelos
 * relatórios da coordenação/secretaria. Consolida acompanhamento, pendências,
 * indicadores (semáforo) e o detalhe de uma aula a partir do Model ClassDiary.
 */
class ClassDiaryService
{
    /** @var ClassDiary */
    private $diary;

    /** @var SchoolAbsence */
    private $absence;

    /** Rótulos legíveis de situação dos indicadores. */
    public const SITUACAO_LABELS = [
        'em_dia' => 'Em dia',
        'atencao' => 'Atenção',
        'atraso' => 'Em atraso',
    ];

    public function __construct(?ClassDiary $diary = null)
    {
        $this->diary = $diary ?? new ClassDiary();
        $this->diary->ensureSchema();
        $this->absence = new SchoolAbsence();
    }

    /**
     * Abre (e cria se necessário) uma aula para lançamento de chamada pela coordenação,
     * a partir de uma linha pendente da grade horária (grade_id + data).
     *
     * @return array{aula:array<string,mixed>,alunos:list<array<string,mixed>>,frequencias:array<int,array<string,mixed>>}
     */
    public function abrirParaLancamento(int $gradeId, string $data): array
    {
        $grade = $this->diary->getGrade($gradeId);
        if (!$grade || (int) date('N', strtotime($data)) !== (int) $grade['dia_semana']) {
            throw new RuntimeException('Aula não encontrada na grade para essa data.');
        }
        $plano = $this->diary->findPlanoParaAula((int) $grade['professor_id'], (int) $grade['turma_id'], (int) $grade['materia_id'], $data);
        $aula = $this->diary->getOrCreateAula($grade, $data, $plano ? (int) $plano['id'] : null);
        return $this->montarLancamento((int) $aula['id']);
    }

    /**
     * Abre uma aula já existente (registrada ou em rascunho) para edição pela coordenação.
     *
     * @return array{aula:array<string,mixed>,alunos:list<array<string,mixed>>,frequencias:array<int,array<string,mixed>>}
     */
    public function abrirAulaExistente(int $aulaId): array
    {
        return $this->montarLancamento($aulaId);
    }

    /**
     * @return array{aula:array<string,mixed>,alunos:list<array<string,mixed>>,frequencias:array<int,array<string,mixed>>}
     */
    private function montarLancamento(int $aulaId): array
    {
        $aula = $this->diary->getAula($aulaId);
        if (!$aula) {
            throw new RuntimeException('Aula não encontrada.');
        }
        $ano = (int) ($aula['ano_letivo'] ?? date('Y', strtotime($aula['data_aula'])));
        $alunos = $this->absence->listAlunosByTurmas([(int) $aula['turma_id']], $ano);
        return [
            'aula' => $aula,
            'alunos' => $alunos,
            'frequencias' => $this->diary->frequenciasMap($aulaId),
        ];
    }

    /**
     * Salva a chamada/conteúdo de uma aula lançada pela coordenação, restringindo
     * as frequências aos alunos efetivamente matriculados na turma da aula.
     *
     * @param array<int|string,mixed> $frequenciasPost
     */
    public function salvarLancamento(int $aulaId, string $execucao, string $conteudo, string $observacoes, array $frequenciasPost, bool $finalizar): void
    {
        $aula = $this->diary->getAula($aulaId);
        if (!$aula) {
            throw new RuntimeException('Aula não encontrada.');
        }
        $alunos = $this->absence->listAlunosByTurmas([(int) $aula['turma_id']], (int) date('Y', strtotime($aula['data_aula'])));
        $permitidos = array_fill_keys(array_map(static fn($a) => (int) $a['id'], $alunos), true);
        $frequencias = [];
        foreach ($frequenciasPost as $alunoId => $payload) {
            if (isset($permitidos[(int) $alunoId])) {
                $frequencias[(int) $alunoId] = $payload;
            }
        }
        $this->diary->salvar($aulaId, $execucao, $conteudo, $observacoes, $frequencias, $finalizar);
    }

    /**
     * Aulas registradas no período (com totais de faltas), mescladas com as
     * pendentes (previstas na grade e ainda sem registro), conforme o filtro.
     *
     * @return list<array<string,mixed>>
     */
    public function acompanhamento(string $inicio, string $fim, int $professorId = 0, string $status = '', int $turmaId = 0): array
    {
        $registradas = $status === 'pendente' ? [] : $this->diary->acompanhamento($inicio, $fim, $professorId, $status, $turmaId);
        $pendentes = ($status === '' || $status === 'pendente') ? $this->diary->aulasPendentes($inicio, $fim, $professorId, $turmaId) : [];
        $aulas = array_merge($pendentes, $registradas);
        usort($aulas, static fn($a, $b) => strcmp(
            ($b['data_aula'] ?? '') . ($b['horario_de'] ?? ''),
            ($a['data_aula'] ?? '') . ($a['horario_de'] ?? '')
        ));
        return $aulas;
    }

    /**
     * Indicadores por professor × turma × matéria com semáforo de situação.
     *
     * @return list<array<string,mixed>>
     */
    public function indicadores(string $inicio, string $fim, int $professorId = 0): array
    {
        return $this->diary->indicadores($inicio, $fim, $professorId);
    }

    /**
     * Resumo dos indicadores para cartões (contagem por situação + cobertura média
     * e horas ministradas vs previstas).
     *
     * @param list<array<string,mixed>> $indicadores
     * @return array<string,mixed>
     */
    public function resumoIndicadores(array $indicadores): array
    {
        $resumo = [
            'total' => count($indicadores),
            'em_dia' => 0,
            'atencao' => 0,
            'atraso' => 0,
            'minutos_previstos' => 0,
            'minutos_ministrados' => 0,
        ];
        $somaPct = 0.0;
        $countPct = 0;
        foreach ($indicadores as $i) {
            $sit = (string) ($i['situacao'] ?? 'em_dia');
            if (isset($resumo[$sit])) {
                $resumo[$sit]++;
            }
            $resumo['minutos_previstos'] += (int) ($i['minutos_previstos'] ?? 0);
            $resumo['minutos_ministrados'] += (int) ($i['minutos_ministrados'] ?? 0);
            if ($i['percentual'] !== null) {
                $somaPct += (float) $i['percentual'];
                $countPct++;
            }
        }
        $resumo['cobertura_media'] = $countPct > 0 ? round($somaPct / $countPct, 1) : null;
        $resumo['horas_previstas'] = round($resumo['minutos_previstos'] / 60, 1);
        $resumo['horas_ministradas'] = round($resumo['minutos_ministrados'] / 60, 1);
        return $resumo;
    }

    /**
     * Detalhe completo de uma aula (dados + plano + chamada com nomes).
     *
     * @return array<string,mixed>|null
     */
    public function detalheAula(int $aulaId): ?array
    {
        $aula = $this->diary->getAula($aulaId);
        if (!$aula) {
            return null;
        }
        $aula['frequencias'] = $this->diary->frequenciasDetalhadas($aulaId, (int) $aula['turma_id']);
        $aula['resumo_frequencia'] = $this->contarFrequencias($aula['frequencias']);
        return $aula;
    }

    /**
     * @param list<array<string,mixed>> $frequencias
     * @return array<string,int>
     */
    private function contarFrequencias(array $frequencias): array
    {
        $cont = ['presente' => 0, 'falta' => 0, 'falta_justificada' => 0, 'atraso' => 0, 'sem_registro' => 0];
        foreach ($frequencias as $f) {
            $s = (string) ($f['situacao'] ?? '');
            if ($s === '' || !isset($cont[$s])) {
                $cont['sem_registro']++;
                continue;
            }
            $cont[$s]++;
        }
        return $cont;
    }

    /**
     * Lista de professores ativos (para filtros das telas).
     *
     * @return list<array<string,mixed>>
     */
    public function professoresAtivos(): array
    {
        return Database::getInstance()->fetchAll("SELECT id, nome FROM professores WHERE ativo = 1 ORDER BY nome") ?: [];
    }

    public function turmasAtivas(): array
    {
        return Database::getInstance()->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome") ?: [];
    }

    public static function situacaoLabel(string $situacao): string
    {
        return self::SITUACAO_LABELS[$situacao] ?? $situacao;
    }
}
