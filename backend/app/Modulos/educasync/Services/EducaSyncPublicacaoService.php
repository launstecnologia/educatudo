<?php

namespace App\Modulos\EducaSync\Services;

use App\Core\Database;
use App\Modulos\EducaSync\Models\Alocacao;
use App\Modulos\EducaSync\Models\Cenario;
use App\Modulos\EducaSync\Models\Versao;

/**
 * EducaSync — publica uma versão em grade_horaria.
 *
 * Modo padrão "substituir": as aulas das turmas presentes na versão passam a ser
 * exatamente as da versão. Aulas que já existem idênticas (mesma turma, matéria,
 * professor, dia e horário) são mantidas com o mesmo id, para não soltar o vínculo
 * com o diário de classe. O restante é removido/inserido numa única transação.
 *
 * sala_id não vai para grade_horaria (a tabela não tem a coluna).
 */
class EducaSyncPublicacaoService
{
    public const MODO_SUBSTITUIR = 'substituir';

    private $db;
    private Cenario $cenarios;
    private Versao $versoes;
    private Alocacao $alocacoes;

    public function __construct(?Cenario $cenarios = null, ?Versao $versoes = null, ?Alocacao $alocacoes = null)
    {
        $this->db = Database::getInstance();
        $this->cenarios = $cenarios ?? new Cenario();
        $this->versoes = $versoes ?? new Versao();
        $this->alocacoes = $alocacoes ?? new Alocacao();
    }

    /**
     * Resumo do que a publicação faria, sem gravar nada.
     *
     * @return array{success:bool, error?:string, diff?:array<string,mixed>}
     */
    public function simular(int $cenarioId, int $versaoId): array
    {
        $plano = $this->planejar($cenarioId, $versaoId);
        if (empty($plano['success'])) {
            return $plano;
        }
        return ['success' => true, 'diff' => $plano['diff']];
    }

    /**
     * @return array{success:bool, error?:string, diff?:array<string,mixed>}
     */
    public function publicar(int $cenarioId, int $versaoId, int $usuarioId, string $modo = self::MODO_SUBSTITUIR): array
    {
        if ($modo !== self::MODO_SUBSTITUIR) {
            return ['success' => false, 'error' => 'Modo de publicação não suportado.'];
        }

        $plano = $this->planejar($cenarioId, $versaoId);
        if (empty($plano['success'])) {
            return $plano;
        }

        $this->db->beginTransaction();
        try {
            foreach (array_chunk($plano['remover_ids'], 200) as $lote) {
                $params = [];
                $marcas = [];
                foreach (array_values($lote) as $i => $id) {
                    $marcas[] = ':id_' . $i;
                    $params['id_' . $i] = (int) $id;
                }
                $this->db->delete('DELETE FROM grade_horaria WHERE id IN (' . implode(', ', $marcas) . ')', $params);
            }

            foreach ($plano['inserir'] as $aula) {
                $this->db->insert(
                    'INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
                     VALUES (:dia_semana, :horario_de, :horario_ate, :turma_id, :professor_id, :materia_id, :periodo)',
                    [
                        'dia_semana' => (int) $aula['dia_semana'],
                        'horario_de' => (string) $aula['horario_de'],
                        'horario_ate' => (string) $aula['horario_ate'],
                        'turma_id' => (int) $aula['turma_id'],
                        'professor_id' => (int) $aula['professor_id'],
                        'materia_id' => (int) $aula['materia_id'],
                        'periodo' => (string) $aula['periodo'],
                    ]
                );
            }

            $this->versoes->despublicarAnteriores($cenarioId, $versaoId);
            $this->versoes->atualizarStatus($versaoId, 'publicada');
            $this->versoes->registrarPublicacao($versaoId, $usuarioId, $modo, $plano['diff']);
            if ((string) ($plano['cenario']['status'] ?? '') === 'rascunho') {
                $this->cenarios->atualizarStatus($cenarioId, 'ativo');
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            error_log('EducaSyncPublicacaoService::publicar: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível publicar a grade. Nada foi alterado.'];
        }

        return ['success' => true, 'diff' => $plano['diff']];
    }

    /**
     * Calcula o que mudaria em grade_horaria. Barra a publicação se faltar professor
     * ou se um professor ficar em duas turmas ao mesmo tempo contra aulas fora do cenário.
     *
     * @return array<string,mixed>
     */
    private function planejar(int $cenarioId, int $versaoId): array
    {
        $cenario = $this->cenarios->findById($cenarioId);
        $versao = $this->versoes->findById($versaoId);
        if (!$cenario || !$versao || (int) $versao['cenario_id'] !== $cenarioId) {
            return ['success' => false, 'error' => 'Versão não encontrada neste cenário.'];
        }
        if ((string) $versao['status'] === 'descartada') {
            return ['success' => false, 'error' => 'Versão descartada não pode ser publicada.'];
        }

        $periodoCenario = (string) ($cenario['periodo'] ?? '');
        if (!isset(Cenario::PERIODOS[$periodoCenario])) {
            return [
                'success' => false,
                'error' => 'Este cenário usa um período que a Grade Horária ainda não aceita. Use manhã ou tarde.',
            ];
        }

        $alocacoes = $this->alocacoes->listarPorVersao($versaoId);
        if ($alocacoes === []) {
            return ['success' => false, 'error' => 'A versão não tem aulas alocadas.'];
        }

        $periodo = $periodoCenario;
        $desejadas = [];
        $turmaIds = [];
        $ocupacaoTurma = [];
        $ocupacaoProfessor = [];
        foreach ($alocacoes as $a) {
            if (empty($a['professor_id'])) {
                return ['success' => false, 'error' => 'Há aulas sem professor na versão. Defina o professor das demandas e gere novamente.'];
            }
            $aula = [
                'dia_semana' => (int) $a['dia_semana'],
                'horario_de' => $this->hora((string) $a['horario_de']),
                'horario_ate' => $this->hora((string) $a['horario_ate']),
                'turma_id' => (int) $a['turma_id'],
                'professor_id' => (int) $a['professor_id'],
                'materia_id' => (int) $a['materia_id'],
                'periodo' => $periodo,
            ];
            $chave = $this->chave($aula);
            if (isset($desejadas[$chave])) {
                return ['success' => false, 'error' => 'A versão tem aulas duplicadas. Gere novamente.'];
            }

            $chaveTurma = (int) $aula['turma_id'] . '|' . (int) $aula['dia_semana'] . '|' . $aula['horario_de'];
            if (isset($ocupacaoTurma[$chaveTurma])) {
                return ['success' => false, 'error' => 'A versão coloca a mesma turma em duas aulas no mesmo horário.'];
            }
            $ocupacaoTurma[$chaveTurma] = true;

            $chaveProfessor = (int) $aula['professor_id'] . '|' . (int) $aula['dia_semana'] . '|' . $aula['horario_de'];
            if (isset($ocupacaoProfessor[$chaveProfessor])) {
                return ['success' => false, 'error' => 'A versão coloca o mesmo professor em duas aulas no mesmo horário.'];
            }
            $ocupacaoProfessor[$chaveProfessor] = true;

            $desejadas[$chave] = $aula;
            $turmaIds[(int) $a['turma_id']] = true;
        }
        $turmaIds = array_keys($turmaIds);

        $existentes = $this->aulasDasTurmas($turmaIds);
        $existentesPorChave = [];
        $removerIds = [];
        foreach ($existentes as $e) {
            $chave = $this->chave($e);
            if (isset($desejadas[$chave]) && !isset($existentesPorChave[$chave])) {
                $existentesPorChave[$chave] = (int) $e['id'];
                continue;
            }
            $removerIds[] = (int) $e['id'];
        }
        $inserir = array_values(array_diff_key($desejadas, $existentesPorChave));

        $anoLetivo = (int) ($cenario['ano_letivo'] ?? 0);
        $conflitos = $this->conflitosDeProfessor($desejadas, $turmaIds, $anoLetivo);
        if ($conflitos !== []) {
            $rotulos = array_map(static function (array $c): string {
                return (string) ($c['rotulo'] ?? '');
            }, $conflitos);
            $rotulos = array_values(array_filter($rotulos));
            $sugestoes = $this->sugerirResolucaoConflitos($conflitos, $anoLetivo);

            return [
                'success' => false,
                'error' => 'Conflito com aulas de outras turmas na grade atual: ' . implode('; ', array_slice($rotulos, 0, 5))
                    . (count($rotulos) > 5 ? ' (e mais ' . (count($rotulos) - 5) . ')' : '') . '.',
                'conflitos' => $conflitos,
                'sugestoes' => $sugestoes,
            ];
        }

        return [
            'success' => true,
            'cenario' => $cenario,
            'remover_ids' => $removerIds,
            'inserir' => $inserir,
            'diff' => [
                'turmas' => count($turmaIds),
                'turma_ids' => $turmaIds,
                'mantidas' => count($existentesPorChave),
                'removidas' => count($removerIds),
                'inseridas' => count($inserir),
            ],
        ];
    }

    /**
     * @param list<int> $turmaIds
     * @return list<array<string,mixed>>
     */
    private function aulasDasTurmas(array $turmaIds): array
    {
        if ($turmaIds === []) {
            return [];
        }
        [$marcas, $params] = $this->listaNomeada('t', $turmaIds);
        return $this->db->fetchAll(
            'SELECT id, dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id
             FROM grade_horaria
             WHERE turma_id IN (' . $marcas . ')',
            $params
        ) ?: [];
    }

    /**
     * Professor da versão ocupado, no mesmo dia e horário, em turma do MESMO ano letivo
     * que NÃO será substituída. Grades de outros anos (ex.: 2026 vs 2027) não bloqueiam.
     *
     * @param array<string,array<string,mixed>> $desejadas
     * @param list<int> $turmaIds
     * @return list<array{
     *   professor_id:int,
     *   professor_nome:string,
     *   turma_id:int,
     *   dia_semana:int,
     *   horario_de:string,
     *   horario_ate:string,
     *   turma_externa:string,
     *   ano_letivo_externo:int,
     *   rotulo:string
     * }>
     */
    private function conflitosDeProfessor(array $desejadas, array $turmaIds, int $anoLetivo): array
    {
        $professorIds = [];
        foreach ($desejadas as $a) {
            $professorIds[(int) $a['professor_id']] = true;
        }
        if ($professorIds === [] || $turmaIds === [] || $anoLetivo <= 0) {
            return [];
        }

        [$marcasP, $paramsP] = $this->listaNomeada('p', array_keys($professorIds));
        [$marcasT, $paramsT] = $this->listaNomeada('t', $turmaIds);
        $params = array_merge($paramsP, $paramsT, ['ano_letivo' => $anoLetivo]);
        $fora = $this->db->fetchAll(
            'SELECT g.professor_id, g.dia_semana, g.horario_de, g.horario_ate,
                    t.nome AS turma_nome, t.ano_letivo AS turma_ano, p.nome AS professor_nome
             FROM grade_horaria g
             INNER JOIN turmas t ON t.id = g.turma_id AND t.ano_letivo = :ano_letivo
             LEFT JOIN professores p ON p.id = g.professor_id
             WHERE g.professor_id IN (' . $marcasP . ') AND g.turma_id NOT IN (' . $marcasT . ')',
            $params
        ) ?: [];

        $porProfessorDia = [];
        foreach ($fora as $f) {
            $porProfessorDia[(int) $f['professor_id'] . '|' . (int) $f['dia_semana']][] = $f;
        }

        $conflitos = [];
        $vistos = [];
        foreach ($desejadas as $a) {
            $chaveBusca = (int) $a['professor_id'] . '|' . (int) $a['dia_semana'];
            foreach ($porProfessorDia[$chaveBusca] ?? [] as $f) {
                $sobrepoe = $this->hora((string) $f['horario_de']) < $a['horario_ate']
                    && $this->hora((string) $f['horario_ate']) > $a['horario_de'];
                if (!$sobrepoe) {
                    continue;
                }
                $turmaExterna = (string) ($f['turma_nome'] ?? '?');
                $anoExterno = (int) ($f['turma_ano'] ?? $anoLetivo);
                $chave = (int) $a['professor_id'] . '|' . (int) $a['turma_id'] . '|'
                    . (int) $a['dia_semana'] . '|' . substr($a['horario_de'], 0, 5) . '|' . $turmaExterna;
                if (isset($vistos[$chave])) {
                    continue;
                }
                $vistos[$chave] = true;
                $professorNome = (string) ($f['professor_nome'] ?? 'Professor');
                $hora = substr($a['horario_de'], 0, 5);
                $conflitos[] = [
                    'professor_id' => (int) $a['professor_id'],
                    'professor_nome' => $professorNome,
                    'turma_id' => (int) $a['turma_id'],
                    'dia_semana' => (int) $a['dia_semana'],
                    'horario_de' => $hora,
                    'horario_ate' => substr($a['horario_ate'], 0, 5),
                    'turma_externa' => $turmaExterna,
                    'ano_letivo_externo' => $anoExterno,
                    'rotulo' => $professorNome . ' já tem aula na turma ' . $turmaExterna
                        . ' (' . $anoExterno . ') às ' . $hora . ' (dia ' . (int) $a['dia_semana'] . ')',
                ];
            }
        }
        return $conflitos;
    }

    /**
     * Sugestões práticas para a coordenação resolver o bloqueio de publicação.
     *
     * @param list<array<string,mixed>> $conflitos
     * @return list<string>
     */
    private function sugerirResolucaoConflitos(array $conflitos, int $anoLetivo): array
    {
        $porProfessor = [];
        $turmasExternas = [];
        foreach ($conflitos as $c) {
            $pid = (int) ($c['professor_id'] ?? 0);
            $nome = (string) ($c['professor_nome'] ?? 'Professor');
            if ($pid > 0) {
                $porProfessor[$pid] = ['nome' => $nome, 'qtd' => ($porProfessor[$pid]['qtd'] ?? 0) + 1];
            }
            $turma = trim((string) ($c['turma_externa'] ?? ''));
            if ($turma !== '') {
                $turmasExternas[$turma] = true;
            }
        }

        $sugestoes = [];
        $sugestoes[] = 'Esses choques são com a Grade Horária já publicada de outras turmas do ano '
            . $anoLetivo . ' (fora deste cenário). A publicação só substitui as turmas desta versão.';

        if (count($porProfessor) >= 3) {
            $sugestoes[] = 'Vários professores estão sobrecarregados no mesmo horário em turmas diferentes: '
                . 'considere contratar docentes extras ou redistribuir a carga (ex.: dividir Itinerários / Matemática entre 2 professores).';
        } elseif (count($porProfessor) === 1) {
            $unico = reset($porProfessor);
            $sugestoes[] = ($unico['nome'] ?? 'Este professor') . ' concentra '
                . (int) ($unico['qtd'] ?? 0)
                . ' conflito(s). Avalie transferir parte das demandas para outro professor da mesma matéria ou contratar reforço.';
        } else {
            $top = $porProfessor;
            uasort($top, static fn(array $a, array $b): int => ($b['qtd'] ?? 0) <=> ($a['qtd'] ?? 0));
            $nomes = [];
            foreach (array_slice($top, 0, 3, true) as $p) {
                $nomes[] = ($p['nome'] ?? '?') . ' (' . (int) ($p['qtd'] ?? 0) . ')';
            }
            if ($nomes !== []) {
                $sugestoes[] = 'Professores com mais conflitos: ' . implode(', ', $nomes)
                    . '. Redistribua aulas ou contrate reforço nessas disciplinas.';
            }
        }

        if ($turmasExternas !== []) {
            $lista = implode(', ', array_slice(array_keys($turmasExternas), 0, 6));
            $sugestoes[] = 'Revise a grade publicada das turmas ' . $lista
                . (count($turmasExternas) > 6 ? '…' : '')
                . ': se forem turmas antigas/erradas do ano ' . $anoLetivo
                . ', limpe ou ajuste essa grade antes de publicar esta versão.';
        }

        $sugestoes[] = 'Depois de redistribuir professores ou limpar a grade conflitante, use “Gerar grade” de novo (ou reotimize) e tente publicar.';

        return $sugestoes;
    }

    /**
     * @param list<int> $ids
     * @return array{0:string, 1:array<string,int>}
     */
    private function listaNomeada(string $prefixo, array $ids): array
    {
        $marcas = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $marcas[] = ':' . $prefixo . '_' . $i;
            $params[$prefixo . '_' . $i] = (int) $id;
        }
        return [implode(', ', $marcas), $params];
    }

    /**
     * @param array<string,mixed> $aula
     */
    private function chave(array $aula): string
    {
        return implode('|', [
            (int) $aula['turma_id'],
            (int) $aula['materia_id'],
            (int) $aula['professor_id'],
            (int) $aula['dia_semana'],
            $this->hora((string) $aula['horario_de']),
            $this->hora((string) $aula['horario_ate']),
        ]);
    }

    private function hora(string $valor): string
    {
        $valor = trim($valor);
        return strlen($valor) === 5 ? $valor . ':00' : substr($valor, 0, 8);
    }
}
