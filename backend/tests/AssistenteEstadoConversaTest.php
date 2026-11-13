<?php

require_once dirname(__DIR__) . '/app/Modulos/provas/Services/Assistente/AssistenteResolvedorAluno.php';
require_once dirname(__DIR__) . '/app/Modulos/provas/Services/Assistente/AssistenteEstadoConversa.php';

use App\Modulos\Provas\Services\Assistente\AssistenteEstadoConversa as Estado;
use PHPUnit\Framework\TestCase;

class AssistenteEstadoConversaTest extends TestCase
{
    public function testNormalizarEstadoVazio(): void
    {
        $e = Estado::normalizar([]);
        $this->assertNull($e['aluno_ativo']);
        $this->assertNull($e['fluxo']);
        $this->assertSame([], $e['rascunho']);
        $this->assertNull($e['pendente']);
    }

    public function testRascunhoAcumulaSemPerderCampos(): void
    {
        $e = Estado::normalizar([]);
        $e = Estado::atualizarRascunhoOcorrencia($e, ['categoria' => 'Comportamento']);
        $e = Estado::atualizarRascunhoOcorrencia($e, ['detalhe' => 'Conversou durante a prova.', 'categoria' => null]);
        $e = Estado::atualizarRascunhoOcorrencia($e, ['nivel_gravidade' => 'médio']);
        $this->assertSame('Comportamento', $e['rascunho']['categoria']);
        $this->assertSame('Conversou durante a prova.', $e['rascunho']['detalhe']);
        $this->assertSame('moderado', $e['rascunho']['nivel_gravidade']);
        $this->assertSame(['mais_envolvidos', 'enviar_pais'], Estado::camposFaltando($e['rascunho']));
    }

    public function testPlaceholderNaoEntraNoRascunho(): void
    {
        $e = Estado::atualizarRascunhoOcorrencia(Estado::normalizar([]), ['detalhe' => '...']);
        $this->assertContains('detalhe', Estado::camposFaltando($e['rascunho']));
    }

    public function testMaisEnvolvidosPedeNomes(): void
    {
        $e = Estado::atualizarRascunhoOcorrencia(Estado::normalizar([]), ['mais_envolvidos' => true]);
        $this->assertContains('envolvidos', Estado::camposFaltando($e['rascunho']));
        $e = Estado::atualizarRascunhoOcorrencia($e, ['envolvidos' => ['João']]);
        $this->assertNotContains('envolvidos', Estado::camposFaltando($e['rascunho']));
        $this->assertNotContains('mais_envolvidos', Estado::camposFaltando($e['rascunho']));
    }

    public function testNaoHaEnvolvidosLimpaLista(): void
    {
        $e = Estado::atualizarRascunhoOcorrencia(Estado::normalizar([]), ['envolvidos' => ['João']]);
        $e = Estado::atualizarRascunhoOcorrencia($e, ['mais_envolvidos' => false]);
        $this->assertFalse($e['rascunho']['mais_envolvidos']);
        $this->assertSame([], $e['rascunho']['envolvidos']);
    }

    public function testEnviarPaisFalsoContaComoRespondido(): void
    {
        $e = Estado::atualizarRascunhoOcorrencia(Estado::normalizar([]), ['enviar_pais' => false]);
        $this->assertNotContains('enviar_pais', Estado::camposFaltando($e['rascunho']));
    }

    public function testRascunhoCompleto(): void
    {
        $e = Estado::atualizarRascunhoOcorrencia(Estado::normalizar([]), [
            'categoria' => 'Comportamento',
            'detalhe' => 'Empurrou o colega no intervalo.',
            'nivel_gravidade' => 'grave',
            'mais_envolvidos' => false,
            'enviar_pais' => true,
        ]);
        $this->assertSame([], Estado::camposFaltando($e['rascunho']));
    }

    public function testLimparFluxoApagaRascunho(): void
    {
        $e = Estado::atualizarRascunhoOcorrencia(Estado::normalizar([]), ['categoria' => 'X']);
        $e = Estado::limparFluxo($e);
        $this->assertNull($e['fluxo']);
        $this->assertSame([], $e['rascunho']);
    }

    public function testTrocarAlunoAtivoZeraReferencias(): void
    {
        $e = Estado::definirAlunoAtivo(Estado::normalizar([]), ['id' => 1, 'nome' => 'Ana']);
        $e = Estado::registrarReferencias($e, 'listar_provas_aluno', [
            'ok' => true,
            'provas' => [['prova_id' => 10, 'titulo' => 'Mat 1']],
        ]);
        $this->assertSame(10, $e['referencias']['provas'][0]['prova_id']);
        $e = Estado::definirAlunoAtivo($e, ['id' => 2, 'nome' => 'Maria']);
        $this->assertSame([], $e['referencias']);
        $this->assertSame('Maria', $e['aluno_ativo']['nome']);
    }

    public function testPromptMostraFaltaAluno(): void
    {
        $e = Estado::atualizarRascunhoOcorrencia(Estado::normalizar([]), ['categoria' => 'X']);
        $txt = Estado::paraPrompt($e);
        $this->assertStringContainsString('"falta":["aluno","detalhe"', $txt);
    }

    public function testPendenteSobreviveANormalizacao(): void
    {
        $e = Estado::definirPendente(Estado::normalizar([]), 'faltas_aluno', ['ano_letivo' => 2024]);
        $e = Estado::normalizar(json_decode((string) json_encode($e), true));
        $this->assertSame('faltas_aluno', $e['pendente']['tool']);
        $this->assertSame(2024, $e['pendente']['args']['ano_letivo']);
    }
}
