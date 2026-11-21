<?php

require_once dirname(__DIR__, 2) . '/app/Policies/PoliticaAcessoProfessorAluno.php';

use PHPUnit\Framework\TestCase;

/**
 * Complemento SEC-10: IDOR horizontal, professor sem vínculo e inativo.
 * Dados 100% fictícios em SQLite — sem conversas reais.
 */
final class Sec10ChatIdorComplementoTest extends TestCase
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
        $pdo->exec('CREATE TABLE chat_professores_alunos (id INTEGER PRIMARY KEY, aluno_id INTEGER, professor_id INTEGER)');
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

    public function testAlunoAComProfessorVinculadoPermite(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (1001, 10, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (2001, '[10]', 1)");
        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertTrue($politica->podeAcessar(2001, 1001));
    }

    public function testAlunoBComOutroProfessorNaoVazaParaProfessorDeA(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (1001, 10, 1)");
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (1002, 20, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (2001, '[10]', 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (2002, '[20]', 1)");
        // Conversa antiga não autoriza
        $pdo->exec("INSERT INTO chat_professores_alunos (id, aluno_id, professor_id) VALUES (1, 1002, 2001)");

        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertTrue($politica->podeAcessar(2001, 1001));
        $this->assertFalse($politica->podeAcessar(2001, 1002));
        $this->assertFalse($politica->podeAcessar(2002, 1001));
    }

    public function testProfessorSemVinculoComAlunoANega(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (1001, 10, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (2099, '[99]', 1)");
        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(2099, 1001));
    }

    public function testProfessorInativoNegaMesmoComChatExistente(): void
    {
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO alunos (id, turma_id, ativo) VALUES (1001, 10, 1)");
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (2001, '[10]', 0)");
        $pdo->exec("INSERT INTO jornadas (id, professor_id, turma_id, ativo) VALUES (1, 2001, 10, 1)");
        $pdo->exec("INSERT INTO chat_professores_alunos (id, aluno_id, professor_id) VALUES (1, 1001, 2001)");
        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(2001, 1001));
    }

    public function testOutroTenantSimuladoAlunoAusenteNega(): void
    {
        // Escola distinta = outra conexão PDO; aluno 634 não existe neste banco.
        $pdo = $this->banco();
        $pdo->exec("INSERT INTO professores (id, turmas, ativo) VALUES (2001, '[10]', 1)");
        $politica = new PoliticaAcessoProfessorAluno($this->db($pdo));
        $this->assertFalse($politica->podeAcessar(2001, 634));
    }

    public function testChatControllerExigeAtivoNaSessaoEPolitica(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/app/Painel/Integrations/Controllers/ChatController.php');
        $this->assertNotFalse($src);
        $this->assertStringContainsString('AND ativo = 1', $src);
        $this->assertStringContainsString('professorPodeAluno', $src);
        $this->assertStringContainsString('professorDaSessao', $src);
        $this->assertStringContainsString('alunoDaSessao', $src);
        $this->assertStringContainsString('verifyCsrfToken', $src);
    }
}
