<?php

use PHPUnit\Framework\TestCase;

/**
 * Etapa 5.3 — ownership de jornadas no portal do aluno (turma + turmas_selecionadas).
 * Caracteriza JourneyController::jornadaPermiteTurma / jornadaPermiteAluno via Reflection.
 */
final class JornadaAlunoOwnershipTest extends TestCase
{
    private object $ctrl;
    /** @var \ReflectionMethod */
    private $permiteTurma;
    /** @var \ReflectionMethod */
    private $permiteAluno;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Modulos/jornadas/Controllers/JourneyController.php';
        if (!class_exists('JourneyController', false) && !class_exists(\App\Modulos\Jornadas\Controllers\JourneyController::class)) {
            $this->markTestSkipped('JourneyController não carregável neste ambiente');
        }
        $class = class_exists(\App\Modulos\Jornadas\Controllers\JourneyController::class)
            ? \App\Modulos\Jornadas\Controllers\JourneyController::class
            : 'JourneyController';

        // Evita AuthManager no construtor: instancia sem __construct quando possível
        try {
            $ref = new \ReflectionClass($class);
            $this->ctrl = $ref->newInstanceWithoutConstructor();
            $this->permiteTurma = $ref->getMethod('jornadaPermiteTurma');
            $this->permiteAluno = $ref->getMethod('jornadaPermiteAluno');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Reflection JourneyController: ' . $e->getMessage());
        }
    }

    public function testPermiteTurmaPrincipal(): void
    {
        $ok = $this->permiteTurma->invoke($this->ctrl, ['turma_id' => 24, 'estrutura' => '{}'], 24);
        $this->assertTrue($ok);
    }

    public function testNegaTurmaDiferenteSemSelecao(): void
    {
        $ok = $this->permiteTurma->invoke($this->ctrl, ['turma_id' => 10, 'estrutura' => '{}'], 24);
        $this->assertFalse($ok);
    }

    public function testPermiteTurmaEmEstruturaSelecionada(): void
    {
        $estrutura = json_encode(['turmas_selecionadas' => [10, 24, 30]]);
        $ok = $this->permiteTurma->invoke(
            $this->ctrl,
            ['turma_id' => 1, 'estrutura' => $estrutura],
            24
        );
        $this->assertTrue($ok);
    }

    public function testNegaAlunoIdZero(): void
    {
        $ok = $this->permiteAluno->invoke(
            $this->ctrl,
            ['turma_id' => 24, 'estrutura' => '{}'],
            0,
            [24]
        );
        $this->assertFalse($ok);
    }

    public function testPermiteAlunoComTurmaVinculada(): void
    {
        $ok = $this->permiteAluno->invoke(
            $this->ctrl,
            ['turma_id' => 24, 'estrutura' => '{}'],
            100,
            [24]
        );
        $this->assertTrue($ok);
    }

    public function testNegaAlunoDeOutraTurma(): void
    {
        $ok = $this->permiteAluno->invoke(
            $this->ctrl,
            ['turma_id' => 24, 'estrutura' => '{}'],
            100,
            [99]
        );
        $this->assertFalse($ok);
    }

    public function testShowChecaJornadaPermiteAluno(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/app/Modulos/jornadas/Controllers/JourneyController.php'
        );
        $this->assertNotFalse($src);
        $pos = strpos($src, 'public function show($id)');
        $this->assertNotFalse($pos);
        // show() é longo (preview professor + fetch); ownership vem após o SELECT.
        $trecho = substr($src, $pos, 12000);
        $this->assertStringContainsString('jornadaPermiteAluno', $trecho);
    }
}
