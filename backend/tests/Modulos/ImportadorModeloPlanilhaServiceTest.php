<?php

require_once __DIR__ . '/../../app/Modulos/modelos-documentos/Services/ModeloDocumentoService.php';
require_once __DIR__ . '/../../app/Modulos/modelos-documentos/Services/ImportadorModeloPlanilhaService.php';

use App\Modulos\ModelosDocumentos\Services\ImportadorModeloPlanilhaService;
use PHPUnit\Framework\TestCase;

final class ImportadorModeloPlanilhaServiceTest extends TestCase
{
    public function testFichaBimestralGeraQuatroBimestres(): void
    {
        $html = $this->htmlDaEstrutura((new ImportadorModeloPlanilhaService())->estruturaDoCatalogo('1127a'));

        $this->assertStringContainsString('1º Bimestre', $html);
        $this->assertStringContainsString('4º Bimestre', $html);
        $this->assertStringContainsString('{{nota_arte_4}}', $html);
        $this->assertStringNotContainsString('Trimestre', $html);
        $this->assertStringContainsString('FORMAÇÃO GERAL BÁSICA', $html);
        $this->assertStringContainsString('seed-folha', $html);
    }

    public function testFichaTrimestralContinuaComTresPeriodos(): void
    {
        $html = $this->htmlDaEstrutura((new ImportadorModeloPlanilhaService())->estruturaDoCatalogo('1127'));

        $this->assertStringContainsString('1º Trimestre', $html);
        $this->assertStringContainsString('3º Trimestre', $html);
        $this->assertStringContainsString('{{nota_arte_3}}', $html);
        $this->assertStringNotContainsString('{{nota_arte_4}}', $html);
    }

    public function testPlanilhaBimestralESemestralReconheceAsDuasFolhas(): void
    {
        $arquivo = $this->planilhaBimestral();
        if ($arquivo === null) {
            $this->markTestSkipped('Planilha oficial de teste não encontrada.');
        }

        $analise = (new ImportadorModeloPlanilhaService())->analisarArquivo($arquivo);
        $chaves = array_column($analise, 'chave');

        $this->assertContains('1127a', $chaves);
        $this->assertContains('1127b', $chaves);

        $bimestral = null;
        foreach ($analise as $folha) {
            if (($folha['chave'] ?? '') === '1127a') {
                $bimestral = $this->htmlDaEstrutura($folha['estrutura'] ?? []);
                break;
            }
        }
        $this->assertNotNull($bimestral);
        $this->assertStringContainsString('4º Bimestre', (string) $bimestral);
        $this->assertStringNotContainsString('Trimestre', (string) $bimestral);
        $this->assertStringContainsString('FORMAÇÃO GERAL BÁSICA', (string) $bimestral);
        $this->assertStringContainsString('Guia de Transferência', (string) $bimestral);
        $this->assertStringContainsString('{{escola_nome}}', (string) $bimestral);
        $this->assertStringContainsString('{{aluno_nome}}', (string) $bimestral);
        $this->assertStringContainsString('{{nota_arte_1}}', (string) $bimestral);
        $this->assertStringContainsString('{{logo_html}}', (string) $bimestral);
        $this->assertStringContainsString('{{secretario_nome}}', (string) $bimestral);
        $this->assertStringContainsString('{{diretor_nome}}', (string) $bimestral);
        $this->assertStringContainsString('seed-folha', (string) $bimestral);
        $this->assertStringContainsString('height:', (string) $bimestral);
        $this->assertStringContainsString('"pageBreakBefore":true', (string) $bimestral);
        $this->assertSame(2, substr_count((string) $bimestral, 'seed-folha'));
    }

    /** @param array<string,mixed> $estrutura */
    private function htmlDaEstrutura(array $estrutura): string
    {
        $json = json_encode($estrutura, JSON_UNESCAPED_UNICODE) ?: '';
        return $json;
    }

    private function planilhaBimestral(): ?string
    {
        $local = __DIR__ . '/../fixtures/ficha-medio-nem-ifa-bimestral-semestral.xlsm';
        if (is_file($local)) {
            return $local;
        }
        $downloads = glob($_SERVER['HOME'] . '/Downloads/*NEM IFA*bimestral*.xlsm') ?: [];

        return $downloads[0] ?? null;
    }
}
