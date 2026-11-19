<?php

use App\Infra\Services\AcessoArquivoPrivado;
use PHPUnit\Framework\TestCase;

final class AcessoArquivoPrivadoTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testPastaDesconhecidaNaoEPublica(): void
    {
        $this->assertSame('negado', AcessoArquivoPrivado::pastaDireta('uploads/seg-nao-listada/a.png'));
    }

    public function testLogoETemaContinuamPublicos(): void
    {
        $this->assertSame('publico', AcessoArquivoPrivado::pastaDireta('uploads/layout/logo.png'));
        $this->assertSame('publico', AcessoArquivoPrivado::pastaDireta('uploads/essays/proposals/tema.png'));
        $this->assertSame('publico', AcessoArquivoPrivado::pastaDireta('uploads/redacoes_temas/capa.png'));
    }

    public function testExercicioEQuestaoSeguemParaOController(): void
    {
        $this->assertSame('rota', AcessoArquivoPrivado::pastaDireta('uploads/jornadas/exercicios/a.png'));
        $this->assertSame('rota', AcessoArquivoPrivado::pastaDireta('uploads/provas/questoes/a.png'));
    }

    public function testDocumentoVideoEApostilaExigemSessao(): void
    {
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/documentos/a.pdf'));
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/videos/a.mp4'));
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/apostilas/a.pdf'));
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/redacoes/aluno/1/a.png'));
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/notifications/aviso.png'));
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/minicursos/capa/a.png'));
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/expo-colag/escola/capa.png'));
        $this->assertSame('sem_sessao', AcessoArquivoPrivado::pastaDireta('uploads/ingles/audio.webm'));
    }

    public function testConteudoDaEscolaSemSessaoNaoLibera(): void
    {
        $this->assertFalse(AcessoArquivoPrivado::podeLer('jornadas_exercicios', 'teacher/4/exercicio.png'));
        $this->assertFalse(AcessoArquivoPrivado::podeLer('provas_questoes', 'teacher/4/questao.png'));
        $this->assertFalse(AcessoArquivoPrivado::podeLer('notifications', 'aviso.png'));
        $this->assertFalse(AcessoArquivoPrivado::podeLer('tipo_que_nao_existe', 'a.png'));
    }
}
