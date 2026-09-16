<?php
/**
 * Gates de Avaliação / geração oficial (sem I/O — testável sem banco).
 */
class FechamentoGates
{
    public static function mensagemAvaliacaoSemModelo(): string
    {
        return 'Selecione em qual modelo esta avaliação entra. Cadastre o modelo em Acadêmico → Modelo de Boletim.';
    }

    public static function mensagemAvaliacaoSemQuadro(): string
    {
        return 'Selecione o Quadro de Notas antes de salvar a avaliação. O campo fica em Configurar Notas → Identidade. Se a lista estiver vazia, cadastre o molde em Acadêmico → Quadro de Notas.';
    }

    /**
     * @return list<string>
     */
    public static function errosSalvarAvaliacao(int $boletimId, int $quadroId): array
    {
        $erros = [];
        if ($boletimId <= 0) {
            $erros[] = self::mensagemAvaliacaoSemModelo();
        }
        if ($quadroId <= 0) {
            $erros[] = self::mensagemAvaliacaoSemQuadro();
        }
        return $erros;
    }

    /**
     * @return array{ok:bool,bloqueios:list<string>}
     */
    public static function diagnosticarGeracao(
        int $boletimId,
        int $quadroId,
        int $regraAprovacaoId,
        bool $modeloEncontrado = true,
        bool $quadroAtivo = true
    ): array {
        $bloqueios = [];
        if ($boletimId <= 0) {
            $bloqueios[] = 'Vincule a avaliação a um Modelo de Boletim antes de gerar.';
        } elseif (!$modeloEncontrado) {
            $bloqueios[] = 'O Modelo de Boletim vinculado não foi encontrado.';
        } elseif ($regraAprovacaoId <= 0) {
            $bloqueios[] = 'Vincule uma Regra de Aprovação ao Modelo de Boletim antes do fechamento.';
        }

        if ($quadroId <= 0) {
            $bloqueios[] = 'Selecione o Quadro de Notas da avaliação antes de gerar.';
        } elseif (!$quadroAtivo) {
            $bloqueios[] = 'O Quadro de Notas vinculado está inválido ou inativo.';
        }

        return [
            'ok' => $bloqueios === [],
            'bloqueios' => $bloqueios,
        ];
    }

    public static function mensagemChamadasPendentes(int $quantidade): string
    {
        $quantidade = max(0, $quantidade);
        return 'Há ' . $quantidade . ' chamada(s) vencida(s) sem diário. A coordenação só homologa com o diário em dia.';
    }

    /**
     * Situações de processo — não entram no snapshot oficial.
     *
     * @return list<string>
     */
    public static function situacoesProcessuais(): array
    {
        return ['recuperacao', 'exame_final', 'resultado_pendente', 'em_andamento'];
    }

    public static function situacaoPermiteHomologar(string $situacao): bool
    {
        $situacao = strtolower(trim($situacao));
        if ($situacao === '') {
            return false;
        }
        return !in_array($situacao, self::situacoesProcessuais(), true);
    }

    public static function mensagemResultadoNaoOficial(): string
    {
        return 'Homologação só fecha aluno com resultado definitivo: aprovado ou reprovado (ou situação especial já resolvida). Recuperação e exame final precisam ser concluídos antes.';
    }

    public static function mensagemAlunosEmRecuperacao(int $quantidade): string
    {
        $quantidade = max(0, $quantidade);
        $n = $quantidade === 1 ? '1 aluno' : $quantidade . ' alunos';
        return 'Há ' . $n . ' em recuperação ou exame final. O período só homologa quando cada um estiver aprovado ou reprovado.';
    }

    /**
     * Conferir turma não inicia fechamento. Iniciar só com pauta sem pendência crítica.
     *
     * @param array<string,mixed> $resumo
     * @return list<string>
     */
    public static function errosIniciarFechamento(array $resumo): array
    {
        $erros = [];
        $total = (int) ($resumo['total'] ?? 0);
        $chamadas = (int) ($resumo['chamadas_pendentes'] ?? 0);
        $pend = (int) ($resumo['pendencias'] ?? 0);
        $rec = (int) ($resumo['recuperacao'] ?? 0);
        if ($total <= 0) {
            $erros[] = 'Nenhum aluno nesta turma.';
        }
        if ($chamadas > 0) {
            $erros[] = self::mensagemChamadasPendentes($chamadas);
        }
        if ($rec > 0) {
            $erros[] = self::mensagemAlunosEmRecuperacao($rec);
        } elseif ($pend > 0) {
            $erros[] = 'Há ' . $pend . ' pendência(s) crítica(s). Resolva antes de iniciar o fechamento.';
        }
        return $erros;
    }

    public static function rotuloPendenciaProcessual(string $situacao): string
    {
        return match (strtolower(trim($situacao))) {
            'exame_final' => 'Exame final pendente',
            'resultado_pendente' => 'Resultado pendente',
            default => 'Recuperação em andamento',
        };
    }
}
