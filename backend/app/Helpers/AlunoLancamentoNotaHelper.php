<?php
/**
 * Regras de listagem no lançamento/relatório de notas.
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

    /**
     * @param list<array<string,mixed>> $linhas
     * @return list<array<string,mixed>>
     */
    public static function filtrarAlunosTeste(array $linhas, string $campoPreferido = 'nome'): array
    {
        $out = [];
        foreach ($linhas as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nome = (string) ($row[$campoPreferido] ?? $row['aluno_nome'] ?? $row['nome'] ?? '');
            if (self::ehAlunoTesteOcultar($nome)) {
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
}
