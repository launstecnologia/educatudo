<?php

require_once dirname(__DIR__, 2) . '/app/Policies/PoliticaAcessoProfessorAluno.php';

use PHPUnit\Framework\TestCase;

/**
 * Gray-box unitário da autorização professor↔aluno (SEC-10 IDOR chat).
 * Usa SQLite em memória — sem HTTP e sem tenant real (isolamento PDO fica fora).
 */
final class PoliticaAcessoProfessorAlunoTest extends TestCase
{
    private function banco(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE alunos (id INTEGER PRIMARY KEY, turma_id INTEGER, ativo INTEGER, nome TEXT, ra TEXT)');
        $pdo->exec('CREATE TABLE turmas (id INTEGER PRIMARY KEY, nome TEXT)');
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
            public function fetchAll(string $sql, array $params = [])
            {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        };
    }

    public function testProfessorVinculadoPorTurmasJsonPodeAcessar(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[19,24]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertTrue($politica->podeAcessar(56, 619));
    }

    public function testProfessorVinculadoPorJornadaPodeAcessar(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (10, 5, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (7, '[]', 1)");
        $pdo->exec("INSERT INTO jornadas (id, professor_id, turma_id, ativo) VALUES (1, 7, 5, 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertTrue($politica->podeAcessar(7, 10));
    }

    public function testProfessorNaoVinculadoNaoAcessa(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (99, '[1,2,3]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(99, 619));
    }

    public function testAlunoDeOutraTurmaNaoAcessa(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (20, 99, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");
        $pdo->exec("INSERT INTO jornadas (id, professor_id, turma_id, ativo) VALUES (1, 56, 24, 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(56, 20));
    }

    public function testAlunoDeOutroTenantSimuladoNaoExisteNoBanco(): void
    {
        // Outro tenant = outra conexão PDO; aqui o aluno simplesmente não está neste banco.
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(56, 634));
    }

    public function testConversaAntigaNaoBastaSeVinculoFoiRevogado(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        // Professor perdeu a turma 24
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[19]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(56, 619));
    }

    public function testMatriculaInativaBloqueiaMesmoComTurmaNoCadastro(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");
        $pdo->exec("INSERT INTO matricula (id, aluno_id, turma_id, status) VALUES (1, 619, 24, 'transferido')");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(56, 619));
    }

    public function testMatriculaAtivaPermite(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");
        $pdo->exec("INSERT INTO matricula (id, aluno_id, turma_id, status) VALUES (1, 619, 24, 'ativa')");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertTrue($politica->podeAcessar(56, 619));
    }

    public function testIdsInvalidosNegam(): void
    {
        $politica = new PoliticaAcessoProfessorAluno($this->db($this->banco()));
        $this->assertFalse($politica->podeAcessar(0, 1));
        $this->assertFalse($politica->podeAcessar(1, 0));
        $this->assertFalse($politica->podeAcessar(-1, -1));
    }

    public function testListarAlunosVinculadosRetornaSoDaTurma(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO turmas (id, nome) VALUES (24, '1A'), (99, 'Outra')");
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo, nome, ra) VALUES (619, 24, 1, 'Aluno A', 'RA1')");
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo, nome, ra) VALUES (620, 99, 1, 'Aluno B', 'RA2')");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $lista = $politica->listarAlunosVinculados(56);
        $ids = array_map(static fn ($a) => (int) $a['id'], $lista);

        $this->assertSame([619], $ids);
    }

    public function testProfessorInativoComJornadaAtivaNaoAcessa(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (10, 5, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (7, '[]', 0)");
        $pdo->exec("INSERT INTO jornadas (id, professor_id, turma_id, ativo) VALUES (1, 7, 5, 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(7, 10));
    }

    public function testProfessorInativoComTurmasJsonNaoAcessa(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 0)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(56, 619));
    }

    public function testAlunoInativoNaoAcessa(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (619, 24, 0)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(56, 619));
    }

    public function testListarAlunosVinculadosProfessorInativoRetornaVazio(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO turmas (id, nome) VALUES (24, '1A')");
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo, nome, ra) VALUES (619, 24, 1, 'Aluno A', 'RA1')");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (56, '[24]', 0)");
        $pdo->exec("INSERT INTO jornadas (id, professor_id, turma_id, ativo) VALUES (1, 56, 24, 1)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertSame([], $politica->listarAlunosVinculados(56));
    }
}
