<?php
/**
 * Avaliação de exercícios da jornada: dissertativa fica neutra até o professor atribuir nota.
 */
class JornadaExercicioAvaliacao
{
    public const STATUS_NAO_RESPONDIDO = 'nao_respondido';
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_ACERTO = 'acerto';
    public const STATUS_ERRO = 'erro';

    public const CORRECAO_PENDENTE = 'pendente';
    public const CORRECAO_CORRIGIDA = 'corrigida';

    public static function ehDissertativa($tipo): bool
    {
        return strtolower(trim((string) $tipo)) === 'dissertativa';
    }

    public static function corrigidaPeloProfessor($respostaJson): bool
    {
        $decoded = self::decodificarResposta($respostaJson);
        $status = strtolower(trim((string) ($decoded['correcao_status'] ?? '')));

        return $status === self::CORRECAO_CORRIGIDA;
    }

    /**
     * Dissertativa respondida ainda sem correção do professor.
     * Legado: pontuação 0 sem flag de correção também é pendente.
     * Dissertativa já pontuada (> 0) sem flag continua como acerto.
     */
    public static function pendenteCorrecao($tipo, $pontuacao, $respostaJson, bool $respondido): bool
    {
        if (!$respondido || !self::ehDissertativa($tipo)) {
            return false;
        }
        if (self::corrigidaPeloProfessor($respostaJson)) {
            return false;
        }
        $nota = $pontuacao === null || $pontuacao === '' ? null : (float) $pontuacao;

        return $nota === null || $nota <= 0;
    }

    /**
     * @return 'nao_respondido'|'pendente'|'acerto'|'erro'
     */
    public static function classificar($tipo, $pontuacao, $respostaJson, bool $respondido): string
    {
        if (!$respondido) {
            return self::STATUS_NAO_RESPONDIDO;
        }
        if (self::pendenteCorrecao($tipo, $pontuacao, $respostaJson, true)) {
            return self::STATUS_PENDENTE;
        }
        $nota = (float) ($pontuacao ?? 0);

        return $nota > 0 ? self::STATUS_ACERTO : self::STATUS_ERRO;
    }

    /**
     * @param mixed $respostaExistente JSON atual ou null
     */
    public static function montarRespostaJson(string $texto, string $correcaoStatus, $respostaExistente = null): string
    {
        $payload = self::decodificarResposta($respostaExistente);
        $payload['resposta'] = $texto;
        $payload['correcao_status'] = $correcaoStatus;

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodificarResposta($respostaJson): array
    {
        if (!is_string($respostaJson) || $respostaJson === '') {
            return [];
        }
        $decoded = json_decode($respostaJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function extrairTextoResposta($respostaJson): string
    {
        $decoded = self::decodificarResposta($respostaJson);
        if (array_key_exists('resposta', $decoded)) {
            return (string) $decoded['resposta'];
        }

        return is_string($respostaJson) ? $respostaJson : '';
    }

    /**
     * @param list<array<string, mixed>> $exercicios
     * @return array{total:int,respondidos:int,acertos:int,erros:int,pendentes:int,nota_total:float,percentual:float}
     */
    public static function agregarEstatisticas(array $exercicios): array
    {
        $total = count($exercicios);
        $respondidos = 0;
        $acertos = 0;
        $erros = 0;
        $pendentes = 0;
        $notaTotal = 0.0;

        foreach ($exercicios as $ex) {
            $resposta = $ex['resposta_aluno'] ?? '';
            $respondido = $resposta !== null && $resposta !== '';
            if (!$respondido) {
                continue;
            }
            $respondidos++;
            $status = self::classificar(
                $ex['tipo'] ?? '',
                $ex['pontuacao_aluno'] ?? null,
                $resposta,
                true
            );
            if ($status === self::STATUS_ACERTO) {
                $acertos++;
                $notaTotal += (float) ($ex['pontuacao_aluno'] ?? 0);
            } elseif ($status === self::STATUS_PENDENTE) {
                $pendentes++;
            } else {
                $erros++;
            }
        }

        return [
            'total' => $total,
            'respondidos' => $respondidos,
            'acertos' => $acertos,
            'erros' => $erros,
            'pendentes' => $pendentes,
            'nota_total' => $notaTotal,
            'percentual' => $total > 0 ? round(($acertos / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Persiste a nota do professor e marca a dissertativa como corrigida.
     *
     * @param mixed $db Database
     */
    public static function aplicarNotaProfessor($db, int $exercicioId, int $alunoId, int $jornadaId, float $pontuacao): int
    {
        $row = $db->fetch(
            "SELECT id, resposta
             FROM jornadas_progresso_alunos
             WHERE aluno_id = :aluno_id
               AND jornada_id = :jornada_id
               AND (
                    (exercicio_modulo_id = :exercicio_modulo_id AND atividade_tipo = 'exercicio_modulo')
                    OR (exercicio_id = :exercicio_id AND atividade_tipo = 'exercicio')
               )
             ORDER BY CASE WHEN atividade_tipo = 'exercicio_modulo' THEN 0 ELSE 1 END, id DESC
             LIMIT 1",
            [
                'aluno_id' => $alunoId,
                'jornada_id' => $jornadaId,
                'exercicio_modulo_id' => $exercicioId,
                'exercicio_id' => $exercicioId,
            ]
        );
        if (!$row) {
            return 0;
        }

        $texto = self::extrairTextoResposta($row['resposta'] ?? '');
        $novoJson = self::montarRespostaJson($texto, self::CORRECAO_CORRIGIDA, $row['resposta'] ?? null);
        $db->update(
            "UPDATE jornadas_progresso_alunos
             SET pontuacao = :pontuacao, resposta = :resposta, updated_at = NOW()
             WHERE id = :id",
            [
                'pontuacao' => $pontuacao,
                'resposta' => $novoJson,
                'id' => (int) $row['id'],
            ]
        );

        return 1;
    }

    /**
     * Expressão SQL (identificadores confiáveis): 1 se dissertativa ainda aguarda correção.
     */
    public static function sqlPendente(string $colTipo, string $colPontuacao, string $colResposta): string
    {
        return "({$colTipo} = 'dissertativa'"
            . " AND (JSON_VALID({$colResposta}) = 0 OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$colResposta}, '$.correcao_status')), '') <> 'corrigida')"
            . " AND ({$colPontuacao} IS NULL OR {$colPontuacao} <= 0))";
    }

    public static function sqlCaseAcerto(string $colTipo, string $colPontuacao, string $colResposta): string
    {
        $pendente = self::sqlPendente($colTipo, $colPontuacao, $colResposta);

        return "CASE WHEN {$pendente} THEN 0 WHEN {$colPontuacao} > 0 THEN 1 ELSE 0 END";
    }

    public static function sqlCaseErro(string $colTipo, string $colPontuacao, string $colResposta): string
    {
        $pendente = self::sqlPendente($colTipo, $colPontuacao, $colResposta);

        return "CASE WHEN {$pendente} THEN 0 WHEN {$colPontuacao} > 0 THEN 0 ELSE 1 END";
    }

    /** NULL quando pendente, para AVG ignorar na taxa. */
    public static function sqlCaseAcertoCorrigido(string $colTipo, string $colPontuacao, string $colResposta): string
    {
        $pendente = self::sqlPendente($colTipo, $colPontuacao, $colResposta);

        return "CASE WHEN {$pendente} THEN NULL WHEN {$colPontuacao} > 0 THEN 1 ELSE 0 END";
    }

    public static function sqlCaseErroCorrigido(string $colTipo, string $colPontuacao, string $colResposta): string
    {
        $pendente = self::sqlPendente($colTipo, $colPontuacao, $colResposta);

        return "CASE WHEN {$pendente} THEN NULL WHEN {$colPontuacao} > 0 THEN 0 ELSE 1 END";
    }
}
