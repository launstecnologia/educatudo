<?php

require_once dirname(__DIR__, 2) . '/app/Helpers/StudentFormHelper.php';
require_once dirname(__DIR__, 2) . '/app/Core/ContextoAluno.php';
require_once dirname(__DIR__, 2) . '/app/Core/Database.php';
require_once dirname(__DIR__, 2) . '/app/Core/AuthManager.php';

use App\Core\AuthManager;
use App\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Revalidação de cadastro ativo (SEC — professor/aluno desativado com sessão).
 * Isola cadastroAindaAtivoNoBanco via Reflection (sem depender de lifetime/config).
 */
final class AuthManagerCadastroAtivoTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $sessionBackup = [];

    /** @var Database|null */
    private $dbAnterior = null;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('currentInstance');
        $this->dbAnterior = $prop->getValue();
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
        Database::setCurrentInstance($this->dbAnterior);
    }

    private function avaliarAtivo(int $userId, string $tipo, int $ativoFlag): bool
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE professores (id INTEGER PRIMARY KEY, ativo INTEGER)');
        $pdo->exec('CREATE TABLE alunos (id INTEGER PRIMARY KEY, ativo INTEGER)');
        $pdo->exec("CREATE TABLE usuarios (id INTEGER PRIMARY KEY, tipo TEXT, ativo INTEGER)");
        $pdo->exec('CREATE TABLE monitores (id INTEGER PRIMARY KEY, ativo INTEGER)');
        $pdo->exec('CREATE TABLE responsaveis (id INTEGER PRIMARY KEY, ativo INTEGER)');

        if ($tipo === 'professor') {
            $pdo->exec("INSERT INTO professores (id, ativo) VALUES ({$userId}, {$ativoFlag})");
        } elseif ($tipo === 'aluno') {
            $pdo->exec("INSERT INTO alunos (id, ativo) VALUES ({$userId}, {$ativoFlag})");
        } elseif ($tipo === 'admin') {
            $pdo->exec("INSERT INTO usuarios (id, tipo, ativo) VALUES ({$userId}, 'admin_escola', {$ativoFlag})");
        }

        Database::setCurrentInstance(Database::createFromPdo($pdo));
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_type'] = $tipo;
        $_SESSION['user_name'] = 'Teste';

        $auth = new AuthManager();
        $method = new ReflectionMethod(AuthManager::class, 'cadastroAindaAtivoNoBanco');
        $method->setAccessible(true);
        return (bool) $method->invoke($auth);
    }

    public function testProfessorAtivoPassa(): void
    {
        $this->assertTrue($this->avaliarAtivo(56, 'professor', 1));
    }

    public function testProfessorInativoFalha(): void
    {
        $this->assertFalse($this->avaliarAtivo(56, 'professor', 0));
    }

    public function testAlunoInativoFalha(): void
    {
        $this->assertFalse($this->avaliarAtivo(10, 'aluno', 0));
    }

    public function testAdminInativoFalha(): void
    {
        $this->assertFalse($this->avaliarAtivo(1, 'admin', 0));
    }

    public function testIsLoggedInChamaRevalidacaoDeAtivo(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/app/Core/AuthManager.php');
        $this->assertNotFalse($src);
        $this->assertStringContainsString('cadastroAindaAtivoNoBanco', $src);
        $this->assertMatchesRegularExpression(
            '/function isLoggedIn\(\)[\s\S]*cadastroAindaAtivoNoBanco\(\)/',
            $src
        );
    }
}
