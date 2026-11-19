<?php

namespace App\Modulos\EducaSync\Models;

use App\Core\Database;

/**
 * EducaSync — demanda (turma + matéria + professor + aulas por semana).
 */
class Demanda
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listarPorCenario(int $cenarioId): array
    {
        return $this->db->fetchAll(
            'SELECT d.id, d.cenario_id, d.turma_id, d.materia_id, d.professor_id, d.aulas_semana, d.sala_id, d.origem,
                    t.nome AS turma_nome, m.nome AS materia_nome, p.nome AS professor_nome
             FROM educasync_demandas d
             LEFT JOIN turmas t ON t.id = d.turma_id
             LEFT JOIN materias m ON m.id = d.materia_id
             LEFT JOIN professores p ON p.id = d.professor_id
             WHERE d.cenario_id = :cenario_id
             ORDER BY t.nome ASC, m.nome ASC',
            ['cenario_id' => (int) $cenarioId]
        ) ?: [];
    }

    public function contar(int $cenarioId): int
    {
        $linha = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM educasync_demandas WHERE cenario_id = :cenario_id',
            ['cenario_id' => (int) $cenarioId]
        );
        return (int) ($linha['total'] ?? 0);
    }

    /**
     * Inclui ou atualiza a demanda da dupla turma + matéria no cenário.
     */
    public function salvar(int $cenarioId, int $turmaId, int $materiaId, ?int $professorId, int $aulasSemana, ?int $salaId, string $origem = 'manual'): void
    {
        $this->db->query(
            'INSERT INTO educasync_demandas (cenario_id, turma_id, materia_id, professor_id, aulas_semana, sala_id, origem)
             VALUES (:cenario_id, :turma_id, :materia_id, :professor_id, :aulas_semana, :sala_id, :origem)
             ON DUPLICATE KEY UPDATE professor_id = VALUES(professor_id), aulas_semana = VALUES(aulas_semana),
                                     sala_id = VALUES(sala_id), origem = VALUES(origem)',
            [
                'cenario_id' => (int) $cenarioId,
                'turma_id' => (int) $turmaId,
                'materia_id' => (int) $materiaId,
                'professor_id' => $professorId !== null && $professorId > 0 ? (int) $professorId : null,
                'aulas_semana' => (int) $aulasSemana,
                'sala_id' => $salaId !== null && $salaId > 0 ? (int) $salaId : null,
                'origem' => $origem === 'matriz' ? 'matriz' : 'manual',
            ]
        );
    }

    /**
     * Sugere o professor da demanda:
     * 1) vínculo do cadastro (professores.materias × matéria da matriz);
     * 2) se vários, prefere quem tem a turma em professores.turmas;
     * 3) entre os restantes, o mais frequente na grade publicada da turma;
     * 4) se ninguém no cadastro, cai no histórico da grade.
     *
     * @param list<int> $materiaIds
     */
    public function professorSugerido(int $turmaId, array $materiaIds): ?int
    {
        $materiaIds = array_values(array_unique(array_filter(array_map('intval', $materiaIds))));
        if ($materiaIds === []) {
            return null;
        }

        $candidatos = $this->professoresDoCadastroPorMaterias($materiaIds);
        if ($candidatos === []) {
            return $this->professorMaisFrequente($turmaId, $materiaIds);
        }

        $naTurma = $this->filtrarCandidatosDaTurma($candidatos, $turmaId);
        $pool = $naTurma !== [] ? $naTurma : $candidatos;
        if (count($pool) === 1) {
            return $pool[0];
        }

        return $this->professorMaisFrequente($turmaId, $materiaIds, $pool) ?? $pool[0];
    }

    /**
     * Mantém só professores cujo JSON `turmas` inclui a turma (vazio = sem filtro).
     *
     * @param list<int> $professorIds
     * @return list<int>
     */
    private function filtrarCandidatosDaTurma(array $professorIds, int $turmaId): array
    {
        $turmaId = (int) $turmaId;
        $ids = array_values(array_unique(array_filter(array_map('intval', $professorIds))));
        if ($turmaId <= 0 || $ids === []) {
            return [];
        }

        $params = [];
        $marcadores = [];
        foreach ($ids as $i => $professorId) {
            $marcadores[] = ':prof_' . $i;
            $params['prof_' . $i] = $professorId;
        }
        $linhas = $this->db->fetchAll(
            'SELECT id, turmas FROM professores WHERE id IN (' . implode(', ', $marcadores) . ')',
            $params
        ) ?: [];

        $naTurma = [];
        foreach ($linhas as $linha) {
            $lista = json_decode((string) ($linha['turmas'] ?? '[]'), true);
            if (!is_array($lista) || $lista === []) {
                continue;
            }
            foreach ($lista as $item) {
                if ((int) $item === $turmaId) {
                    $naTurma[] = (int) $linha['id'];
                    break;
                }
            }
        }
        return $naTurma;
    }

    /**
     * Professores ativos cujo cadastro lista a(s) matéria(s) (por id ou nome).
     *
     * @param list<int> $materiaIds
     * @return list<int>
     */
    public function professoresDoCadastroPorMaterias(array $materiaIds): array
    {
        $materiaIds = array_values(array_unique(array_filter(array_map('intval', $materiaIds))));
        if ($materiaIds === []) {
            return [];
        }

        $params = [];
        $marcadores = [];
        foreach ($materiaIds as $i => $materiaId) {
            $marcadores[] = ':materia_' . $i;
            $params['materia_' . $i] = $materiaId;
        }
        $linhas = $this->db->fetchAll(
            'SELECT id, nome FROM materias WHERE id IN (' . implode(', ', $marcadores) . ')',
            $params
        ) ?: [];

        $idsOk = [];
        $nomesOk = [];
        foreach ($linhas as $linha) {
            $id = (int) ($linha['id'] ?? 0);
            $nome = $this->normalizarRotulo((string) ($linha['nome'] ?? ''));
            if ($id > 0) {
                $idsOk[$id] = true;
            }
            if ($nome !== '') {
                $nomesOk[$nome] = true;
            }
        }
        if ($idsOk === [] && $nomesOk === []) {
            return [];
        }

        $professores = $this->db->fetchAll(
            'SELECT id, materias FROM professores WHERE ativo = 1 ORDER BY id ASC'
        ) ?: [];

        $encontrados = [];
        foreach ($professores as $professor) {
            $lista = json_decode((string) ($professor['materias'] ?? '[]'), true);
            if (!is_array($lista) || $lista === []) {
                continue;
            }
            foreach ($lista as $item) {
                $rotulo = trim((string) $item);
                if ($rotulo === '') {
                    continue;
                }
                if (ctype_digit($rotulo) && isset($idsOk[(int) $rotulo])) {
                    $encontrados[] = (int) $professor['id'];
                    break;
                }
                if (isset($nomesOk[$this->normalizarRotulo($rotulo)])) {
                    $encontrados[] = (int) $professor['id'];
                    break;
                }
            }
        }

        return array_values(array_unique($encontrados));
    }

    /**
     * Professor que mais aparece na grade_horaria publicada para a turma e a(s) matéria(s).
     * Só considera professores ativos; desempate pelo menor id (resultado estável).
     *
     * @param list<int> $materiaIds
     * @param list<int>|null $somenteEntre restringe a estes professores (cadastro)
     */
    public function professorMaisFrequente(int $turmaId, array $materiaIds, ?array $somenteEntre = null): ?int
    {
        $params = ['turma_id' => (int) $turmaId];
        $marcadores = [];
        foreach (array_values(array_unique(array_map('intval', $materiaIds))) as $i => $materiaId) {
            if ($materiaId <= 0) {
                continue;
            }
            $marcadores[] = ':materia_' . $i;
            $params['materia_' . $i] = $materiaId;
        }
        if ($marcadores === []) {
            return null;
        }

        $filtroProf = '';
        if ($somenteEntre !== null) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $somenteEntre))));
            if ($ids === []) {
                return null;
            }
            $marcProf = [];
            foreach ($ids as $i => $professorId) {
                $marcProf[] = ':prof_' . $i;
                $params['prof_' . $i] = $professorId;
            }
            $filtroProf = ' AND g.professor_id IN (' . implode(', ', $marcProf) . ')';
        }

        $linha = $this->db->fetch(
            'SELECT g.professor_id, COUNT(*) AS total
             FROM grade_horaria g
             INNER JOIN professores p ON p.id = g.professor_id AND p.ativo = 1
             WHERE g.turma_id = :turma_id AND g.materia_id IN (' . implode(', ', $marcadores) . ')' . $filtroProf . '
             GROUP BY g.professor_id
             ORDER BY total DESC, g.professor_id ASC
             LIMIT 1',
            $params
        );
        return $linha ? (int) $linha['professor_id'] : null;
    }

    private function normalizarRotulo(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        if ($valor === '') {
            return '';
        }
        if (class_exists('Normalizer')) {
            $valor = \Normalizer::normalize($valor, \Normalizer::FORM_D) ?: $valor;
            $valor = preg_replace('/\p{Mn}/u', '', $valor) ?? $valor;
        }
        return $valor;
    }

    public function excluir(int $cenarioId, int $demandaId): int
    {
        return (int) $this->db->delete(
            'DELETE FROM educasync_demandas WHERE id = :id AND cenario_id = :cenario_id',
            ['id' => (int) $demandaId, 'cenario_id' => (int) $cenarioId]
        );
    }

    public function excluirTodas(int $cenarioId): int
    {
        return (int) $this->db->delete(
            'DELETE FROM educasync_demandas WHERE cenario_id = :cenario_id',
            ['cenario_id' => (int) $cenarioId]
        );
    }
}
