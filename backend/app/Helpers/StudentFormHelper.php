<?php

class StudentFormHelper
{
    public static function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    public static function rgNormalized(?string $value): string
    {
        return strtoupper(preg_replace('/[^0-9X]/', '', (string) $value));
    }

    public static function formatCpfDisplay(?string $cpf): string
    {
        $d = self::digitsOnly($cpf);
        if (strlen($d) !== 11) {
            return trim((string) $cpf);
        }

        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    public static function formatCepDisplay(?string $cep): string
    {
        $d = self::digitsOnly($cep);
        if (strlen($d) !== 8) {
            return trim((string) $cep);
        }

        return substr($d, 0, 5) . '-' . substr($d, 5, 3);
    }

    public static function formatRgDisplay(?string $rg): string
    {
        $raw = self::rgNormalized($rg);
        if ($raw === '') {
            return '';
        }
        if (strlen($raw) <= 2) {
            return $raw;
        }
        if (strlen($raw) <= 5) {
            return substr($raw, 0, 2) . '.' . substr($raw, 2);
        }
        if (strlen($raw) <= 8) {
            return substr($raw, 0, 2) . '.' . substr($raw, 2, 3) . '.' . substr($raw, 5);
        }

        return substr($raw, 0, 2) . '.' . substr($raw, 2, 3) . '.' . substr($raw, 5, 3) . '-' . substr($raw, 8);
    }

    public static function formatTelefoneDisplay(?string $value): string
    {
        $d = self::digitsOnly($value);
        if ($d === '') {
            return '';
        }
        if (strlen($d) === 11) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
        }
        if (strlen($d) === 10) {
            return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
        }

        return trim((string) $value);
    }

    public static function formatDataNascInput($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || str_starts_with($value, '0000-')) {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return '';
    }

    public static function normalizarDataNasc($value): ?string
    {
        $formatted = self::formatDataNascInput($value);

        return $formatted !== '' ? $formatted : null;
    }

    /** @return array<string, string|null> */
    public static function extractContatoFromPost(array $post): array
    {
        $telefone = self::digitsOnly($post['telefone'] ?? '');
        $celular = self::digitsOnly($post['celular'] ?? '');
        $whatsapp = self::digitsOnly($post['whatsapp'] ?? '');
        $email = strtolower(trim((string) ($post['email'] ?? '')));
        $emailSecundario = strtolower(trim((string) ($post['email_secundario'] ?? '')));

        return [
            'telefone' => $telefone !== '' ? $telefone : null,
            'celular' => $celular !== '' ? $celular : null,
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'email' => $email !== '' ? $email : null,
            'email_secundario' => $emailSecundario !== '' ? $emailSecundario : null,
        ];
    }

    /** @return array<string, string|null> */
    public static function extractIdentificacaoCivilFromPost(array $post): array
    {
        $ufNasc = strtoupper(substr(trim((string) ($post['uf_nascimento'] ?? '')), 0, 2));
        $ufRg = strtoupper(substr(trim((string) ($post['uf_rg'] ?? '')), 0, 2));

        return [
            'nome_mae' => self::nullableTrim($post['nome_mae'] ?? null),
            'nome_pai' => self::nullableTrim($post['nome_pai'] ?? null),
            'codigo_inep' => self::nullableTrim($post['codigo_inep'] ?? null),
            'nome_social' => self::nullableTrim($post['nome_social'] ?? null),
            'nacionalidade' => self::nullableTrim($post['nacionalidade'] ?? null),
            'naturalidade' => self::nullableTrim($post['naturalidade'] ?? null),
            'uf_nascimento' => $ufNasc !== '' ? $ufNasc : null,
            'cor_raca' => self::nullableTrim($post['cor_raca'] ?? null),
            'orgao_emissor' => self::nullableTrim($post['orgao_emissor'] ?? null),
            'uf_rg' => $ufRg !== '' ? $ufRg : null,
            'certidao_nascimento' => self::nullableTrim($post['certidao_nascimento'] ?? null),
            'certidao_livro' => self::nullableTrim($post['certidao_livro'] ?? null),
            'certidao_folha' => self::nullableTrim($post['certidao_folha'] ?? null),
            'certidao_termo' => self::nullableTrim($post['certidao_termo'] ?? null),
            'nis' => self::digitsOnly($post['nis'] ?? '') !== '' ? self::digitsOnly($post['nis'] ?? '') : null,
            'passaporte' => self::nullableTrim($post['passaporte'] ?? null),
            'rne' => self::nullableTrim($post['rne'] ?? null),
            'zona' => self::nullableTrim($post['zona'] ?? null),
            'pais' => self::nullableTrim($post['pais'] ?? null),
        ];
    }

    /** @return list<string> */
    public static function corRacaOpcoes(): array
    {
        return ['Branca', 'Preta', 'Parda', 'Amarela', 'Indígena', 'Não declarada'];
    }

    /** @return array<string, string|null> */
    public static function extractDocumentoEnderecoFromPost(array $post): array
    {
        $cpf = self::digitsOnly($post['cpf'] ?? '');
        $rg = self::rgNormalized($post['rg'] ?? '');
        $cep = self::digitsOnly($post['cep'] ?? '');
        $uf = strtoupper(substr(trim((string) ($post['uf'] ?? '')), 0, 2));

        return [
            'cpf' => $cpf !== '' ? $cpf : null,
            'rg' => $rg !== '' ? $rg : null,
            'data_nasc' => self::normalizarDataNasc($post['data_nasc'] ?? null),
            'logradouro' => self::nullableTrim($post['logradouro'] ?? null),
            'numero' => self::nullableTrim($post['numero'] ?? null),
            'complemento' => self::nullableTrim($post['complemento'] ?? null),
            'bairro' => self::nullableTrim($post['bairro'] ?? null),
            'cidade' => self::nullableTrim($post['cidade'] ?? null),
            'uf' => $uf !== '' ? $uf : null,
            'cep' => $cep !== '' ? $cep : null,
        ];
    }

    private static function nullableTrim($value): ?string
    {
        $v = trim((string) $value);

        return $v !== '' ? $v : null;
    }

    /** Nome de registro (civil). */
    public static function nomeCivil(array $aluno): string
    {
        $civil = trim((string) ($aluno['nome_civil'] ?? ''));
        if ($civil !== '') {
            return $civil;
        }

        return trim((string) ($aluno['nome'] ?? ''));
    }

    public static function nomeSocial(array $aluno): string
    {
        return trim((string) ($aluno['nome_social'] ?? ''));
    }

    public static function temNomeSocial(array $aluno): bool
    {
        return self::nomeSocial($aluno) !== '';
    }

    /** Nome usado na plataforma: social se houver, senão o civil. */
    public static function nomeExibicao(array $aluno): string
    {
        $social = self::nomeSocial($aluno);

        return $social !== '' ? $social : self::nomeCivil($aluno);
    }

    /**
     * Preenche nome_civil e nome_exibicao. Com $substituirNome, troca `nome` pelo social
     * (listagens/sessão). Nunca usar no formulário de edição — lá `nome` é o civil.
     *
     * @param array<string, mixed> $aluno
     * @return array<string, mixed>
     */
    public static function aplicarNomeExibicao(array $aluno, bool $substituirNome = false): array
    {
        if (!array_key_exists('nome_civil', $aluno) || trim((string) $aluno['nome_civil']) === '') {
            $aluno['nome_civil'] = trim((string) ($aluno['nome'] ?? ''));
        }
        $aluno['nome_exibicao'] = self::nomeExibicao($aluno);
        if ($substituirNome && self::temNomeSocial($aluno)) {
            $aluno['nome'] = $aluno['nome_exibicao'];
        }

        return $aluno;
    }

    /**
     * Expressão SQL do nome de exibição (social ou civil).
     * $alias só aceita identificador simples (ex.: a).
     */
    public static function sqlNomeExibicao(string $alias = 'a'): string
    {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'a';
        }

        return "COALESCE(NULLIF(TRIM({$a}.nome_social), ''), {$a}.nome)";
    }

    /** Texto para documentos oficiais: social como principal + civil ao lado, se houver. */
    public static function nomeOficialLinha(array $aluno): string
    {
        $exibicao = self::nomeExibicao($aluno);
        $civil = self::nomeCivil($aluno);
        if (self::temNomeSocial($aluno) && $civil !== '' && strcasecmp($exibicao, $civil) !== 0) {
            return $exibicao . ' (nome civil: ' . $civil . ')';
        }

        return $exibicao;
    }

    /**
     * HTML para cabeçalho de boletim/histórico: social em destaque e civil abaixo.
     *
     * @param callable(string):string $esc
     */
    public static function nomeOficialHtml(array $aluno, callable $esc): string
    {
        $exibicao = self::nomeExibicao($aluno);
        $civil = self::nomeCivil($aluno);
        $html = '<strong>' . $esc($exibicao !== '' ? $exibicao : '—') . '</strong>';
        if (self::temNomeSocial($aluno) && $civil !== '' && strcasecmp($exibicao, $civil) !== 0) {
            $html .= '<br><span style="font-size:0.85em;font-weight:normal;">Nome civil: ' . $esc($civil) . '</span>';
        }

        return $html;
    }

    /** @return list<string> */
    public static function ufsBrasil(): array
    {
        return ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
    }
}
