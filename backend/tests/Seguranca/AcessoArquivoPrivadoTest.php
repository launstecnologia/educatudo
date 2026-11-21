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

    /** SEC-05: dono do caminho aluno/{id}/ lê; outro aluno não. */
    public function testAlunoLeSomenteProprioCaminhoChat(): void
    {
        $_SESSION = ['user_id' => 618, 'user_type' => 'aluno'];
        $this->assertTrue(AcessoArquivoPrivado::podeLer('chat', 'aluno/618/sec05_privado_a.txt'));

        $_SESSION = ['user_id' => 619, 'user_type' => 'aluno'];
        $this->assertFalse(AcessoArquivoPrivado::podeLer('chat', 'aluno/618/sec05_privado_a.txt'));
    }

    /** SEC-05: professor não lê chat privado só pelo path (so_dono / sem vínculo de registro). */
    public function testProfessorNaoLeChatPrivadoPorPath(): void
    {
        $_SESSION = ['user_id' => 56, 'user_type' => 'professor'];
        $this->assertFalse(AcessoArquivoPrivado::podeLer('chat', 'aluno/618/sec05_privado_a.txt'));
    }

    /** SEC-05: admin da escola passa no gate de arquivo privado (isolamento de tenant é na sessão/host). */
    public function testAdminMesmaEscolaPodeLerArquivoPrivado(): void
    {
        $_SESSION = ['user_id' => 28, 'user_type' => 'admin_escola'];
        $this->assertTrue(AcessoArquivoPrivado::podeLer('chat', 'aluno/618/sec05_privado_a.txt'));
    }
}
