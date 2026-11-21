<?php

require_once dirname(__DIR__, 2) . '/app/Policies/PoliticaAcessoProfessorAluno.php';

use PHPUnit\Framework\TestCase;

/**
 * Regressão: reset de senha / acesso professor→aluno exige o mesmo vínculo do chat (SEC-10).
 * TeacherController::updateStudentPassword usa PoliticaAcessoProfessorAluno::podeAcessar.
 */
final class ProfessorAlunoOwnershipResetSenhaTest extends TestCase
{
    private function banco(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE alunos (id INTEGER PRIMARY KEY, turma_id INTEGER, ativo INTEGER)');
        $pdo->exec('CREATE TABLE professores (id INTEGER PRIMARY KEY, turmas TEXT, ativo INTEGER)');
        $pdo->exec('CREATE TABLE jornadas (id INTEGER PRIMARY KEY, professor_id INTEGER, turma_id INTEGER, ativo INTEGER)');
        $pdo->exec('CREATE TABLE matricula (id INTEGER PRIMARY KEY, aluno_id INTEGER, turma_id INTEGER, status TEXT)');
        return $pdo;
    }

    private function db(PDO $pdo): object
    {
        return new class ($pdo) {
            private PDO $pdo;
            public function __construct(PDO $pdo)
            {
                $this->pdo = $pdo;
            }
            public function fetch(string $sql, array $params = [])
            {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row === false ? null : $row;
            }
        };
    }

    public function testResetSenhaNegadoSemVinculo(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (61, '[]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse(
            $politica->podeAcessar(61, 619),
            'Professor sem turma/jornada não pode resetar senha do aluno'
        );
    }

    public function testResetSenhaPermitidoComTurmaNoCadastro(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertTrue($politica->podeAcessar(56, 619));
    }

    public function testResetSenhaPermitidoComJornada(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (10, 5, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (7, '[]', 1)");
        $pdo->exec("INSERT INTO jornadas (id, professor_id, turma_id, ativo) VALUES (1, 7, 5, 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertTrue($politica->podeAcessar(7, 10));
    }

    public function testResetSenhaNegadoAlunoOutraTurma(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (20, 99, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(56, 20));
    }
}
