<?php

require_once __DIR__ . '/../Core/Database.php';

/**
 * Autorização professor ↔ aluno para chat e recursos semelhantes.
 * Isolamento entre escolas fica na conexão PDO do tenant.
 *
 * Vínculo válido quando o aluno está ativo na turma e o professor
 * leciona essa turma via jornadas e/ou JSON professores.turmas.
 * Conversa antiga não basta se o vínculo foi revogado.
 */
class PoliticaAcessoProfessorAluno
{
    /** @var object Objeto com fetch(string $sql, array $params = []): mixed */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function podeAcessar(int $professorId, int $alunoId): bool
    {
        if ($professorId <= 0 || $alunoId <= 0) {
            return false;
        }

        $aluno = $this->db->fetch(
            'SELECT a.id, a.turma_id, a.ativo
             FROM alunos a
             WHERE a.id = :aluno_id
             LIMIT 1',
            ['aluno_id' => $alunoId]
        );
        if (!$aluno || (int) ($aluno['ativo'] ?? 0) !== 1) {
            return false;
        }

        $turmaId = (int) ($aluno['turma_id'] ?? 0);
        if ($turmaId <= 0) {
            return false;
        }

        if (!$this->matriculaPermite($alunoId, $turmaId)) {
            return false;
        }

        if ($this->professorLecionaTurmaPorJornada($professorId, $turmaId)) {
            return true;
        }

        return $this->professorLecionaTurmaPorCadastro($professorId, $turmaId);
    }

    /**
     * Se existir matrícula do aluno, exige ao menos uma ativa na turma atual.
     * Sem registros em matricula, aceita o turma_id do cadastro (legado).
     */
    private function matriculaPermite(int $alunoId, int $turmaId): bool
    {
        if (!$this->tabelaExiste('matricula')) {
            return true;
        }

        $qualquer = $this->db->fetch(
            'SELECT id FROM matricula WHERE aluno_id = :aluno_id LIMIT 1',
            ['aluno_id' => $alunoId]
        );
        if (!$qualquer) {
            return true;
        }

        $ativa = $this->db->fetch(
            "SELECT id FROM matricula
             WHERE aluno_id = :aluno_id
               AND turma_id = :turma_id
               AND status = 'ativa'
             LIMIT 1",
            ['aluno_id' => $alunoId, 'turma_id' => $turmaId]
        );

        return (bool) $ativa;
    }

    private function professorLecionaTurmaPorJornada(int $professorId, int $turmaId): bool
    {
        if (!$this->tabelaExiste('jornadas')) {
            return false;
        }

        $row = $this->db->fetch(
            'SELECT id FROM jornadas
             WHERE professor_id = :professor_id
               AND turma_id = :turma_id
               AND (ativo = 1 OR ativo IS NULL)
             LIMIT 1',
            ['professor_id' => $professorId, 'turma_id' => $turmaId]
        );

        return (bool) $row;
    }

    private function professorLecionaTurmaPorCadastro(int $professorId, int $turmaId): bool
    {
        $professor = $this->db->fetch(
            'SELECT turmas FROM professores WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1',
            ['id' => $professorId]
        );
        if (!$professor) {
            return false;
        }

        $turmas = json_decode((string) ($professor['turmas'] ?? '[]'), true);
        if (!is_array($turmas)) {
            return false;
        }

        foreach ($turmas as $id) {
            if ((int) $id === $turmaId) {
                return true;
            }
        }

        return false;
    }

    /** @var array<string,bool> */
    private $tabelas = [];

    private function tabelaExiste(string $nome): bool
    {
        if (array_key_exists($nome, $this->tabelas)) {
            return $this->tabelas[$nome];
        }

        $nomeSeguro = preg_replace('/[^a-zA-Z0-9_]/', '', $nome);
        if ($nomeSeguro === '' || $nomeSeguro !== $nome) {
            return $this->tabelas[$nome] = false;
        }

        try {
            // Existência: SELECT na tabela (funciona em MySQL e SQLite dos testes).
            $this->db->fetch('SELECT 1 AS ok FROM ' . $nomeSeguro . ' LIMIT 1');
            return $this->tabelas[$nome] = true;
        } catch (\Throwable $e) {
            return $this->tabelas[$nome] = false;
        }
    }
}
