<?php
/**
 * Regras de listagem no lançamento/relatório de notas e telas acadêmicas.
 *
 * - Inativo (ativo=0 / status INACTIVE) sem matrícula transferida: não aparece.
 * - Transferido (mesmo que o cadastro tenha sido inativado): aparece com "TR " no nome,
 *   em cinza e sem edição de nota.
 */
class AlunoLancamentoNotaHelper
{
    public static function normalizarNome(string $nome): string
    {
        $n = trim(mb_strtoupper($nome, 'UTF-8'));
        if ($n === '') {
            return '';
        }
        if (class_exists('Normalizer', false)) {
            $decomp = \Normalizer::normalize($n, \Normalizer::FORM_D);
            if (is_string($decomp) && $decomp !== '') {
                $n = $decomp;
            }
            $n = preg_replace('/\p{Mn}/u', '', $n) ?? $n;
        } else {
            $n = strtr($n, [
                'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
                'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
                'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
                'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
                'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
                'Ç' => 'C', 'Ñ' => 'N',
            ]);
        }
        $n = preg_replace('/\s+/u', ' ', $n) ?? $n;

        return trim($n);
    }

    public static function ehAlunoTesteOcultar(?string $nome): bool
    {
        $n = self::normalizarNome((string) $nome);
        if ($n === '') {
            return false;
        }
        // Cadastros de teste: "João Silva Teste Geral", "João Silva 2 Ano", "Joao silva 3 Ano"...
        return (bool) preg_match('/^JOAO SILVA(?:\s+TESTE\b|\s+\d+\s*ANO\b)/u', $n);
    }

    public static function ehTransferido(array $aluno): bool
    {
        if (!empty($aluno['transferido'])) {
            return true;
        }
        $statusMatricula = strtolower(trim((string) ($aluno['matricula_status'] ?? $aluno['status_matricula'] ?? '')));
        return $statusMatricula === 'transferido';
    }

    /**
     * Inativo no cadastro (ativo=0 ou status INACTIVE), salvo se for transferido
     * — transferência costuma marcar o aluno como INACTIVE sem matrícula ativa.
     */
    public static function ehInativoNaoTransferido(array $aluno): bool
    {
        if (self::ehTransferido($aluno)) {
            return false;
        }
        if (array_key_exists('ativo', $aluno) && $aluno['ativo'] !== null && $aluno['ativo'] !== '') {
            if ((int) $aluno['ativo'] === 0) {
                return true;
            }
        }
        $status = strtoupper(trim((string) ($aluno['status'] ?? $aluno['aluno_status'] ?? '')));
        return $status === 'INACTIVE';
    }

    public static function deveExibirAluno(array $aluno, string $campoNome = 'nome'): bool
    {
        $nome = (string) ($aluno[$campoNome] ?? $aluno['aluno_nome'] ?? $aluno['nome'] ?? '');
        if (self::ehAlunoTesteOcultar($nome)) {
            return false;
        }
        if (self::ehInativoNaoTransferido($aluno)) {
            return false;
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    public static function filtrarAlunosTeste(array $linhas, string $campoPreferido = 'nome'): array
    {
        return self::filtrarAlunosExibicao($linhas, $campoPreferido);
    }

    /**
     * Remove alunos de teste e inativos que não são transferidos.
     *
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    public static function filtrarAlunosExibicao(array $linhas, string $campoPreferido = 'nome'): array
    {
        $out = [];
        foreach ($linhas as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!self::deveExibirAluno($row, $campoPreferido)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    public static function rotuloNomeComTransferencia(string $nome, bool $transferido): string
    {
        $nome = trim($nome);
        if ($nome === '' || !$transferido) {
            return $nome;
        }
        if (preg_match('/^TR\b/iu', $nome)) {
            return $nome;
        }

        return 'TR ' . $nome;
    }

    /**
     * SQL fragment: ativo OU (inativo mas com matrícula transferida na turma).
     * Alias do aluno = $aliasAluno; alias da turma = $aliasTurma (coluna id).
     */
    public static function sqlCondicaoAlunoVisivel(string $aliasAluno = 'a', string $aliasTurma = 't', bool $temMatricula = true): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasAluno) ?: 'a';
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $aliasTurma) ?: 't';
        if (!$temMatricula) {
            return "({$a}.ativo = 1 OR {$a}.ativo IS NULL)";
        }

        return "(
            {$a}.ativo = 1 OR {$a}.ativo IS NULL
            OR EXISTS (
                SELECT 1 FROM matricula mx
                WHERE mx.aluno_id = {$a}.id
                  AND mx.turma_id = {$t}.id
                  AND mx.status = 'transferido'
            )
        )";
    }
}
