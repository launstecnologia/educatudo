<?php

namespace App\Modulos\Lex\Services;

/**
 * Decide criar, atualizar, vincular ou inativar sem gravar no banco.
 */
class LexPlanoUsuarios
{
    /**
     * @param array<string,mixed> $classificacao
     * @param array<string,mixed> $estado
     * @return array<string,mixed>
     */
    public static function montar(array $classificacao, array $estado): array
    {
        $pessoas = is_array($classificacao['pessoas'] ?? null) ? $classificacao['pessoas'] : [];
        $ignorados = is_array($classificacao['ignorados'] ?? null) ? $classificacao['ignorados'] : [];
        $vinculos = is_array($estado['vinculos'] ?? null) ? $estado['vinculos'] : [];

        $criar = [];
        $atualizar = [];
        $conflitos = [];
        $bloqueia = false;
        $importados = [
            'aluno' => [],
            'professor' => [],
            'responsavel' => [],
            'usuario' => [],
        ];

        foreach ($pessoas as $idLex => $pessoa) {
            if (!is_array($pessoa)) {
                continue;
            }
            $idLex = (string) $idLex;
            $nome = trim((string) ($pessoa['nome'] ?? ''));
            if ($nome === '') {
                $conflitos[] = self::conflito($idLex, '', 'Usuário sem nome.', true);
                $bloqueia = true;
                continue;
            }

            $papeis = is_array($pessoa['papeis'] ?? null) ? $pessoa['papeis'] : [];
            if (in_array('sponsor', $papeis, true)) {
                self::pessoa($idLex, 'responsavel', $pessoa, $vinculos, $estado, $criar, $atualizar, $conflitos, $bloqueia, $importados);
            }
            if (in_array('student', $papeis, true)) {
                self::pessoa($idLex, 'aluno', $pessoa, $vinculos, $estado, $criar, $atualizar, $conflitos, $bloqueia, $importados);
            }
            if (in_array('teacher', $papeis, true)) {
                self::pessoa($idLex, 'professor', $pessoa, $vinculos, $estado, $criar, $atualizar, $conflitos, $bloqueia, $importados);
            }
            if (trim((string) ($pessoa['perfil_admin'] ?? '')) !== '') {
                self::pessoa($idLex, 'usuario', $pessoa, $vinculos, $estado, $criar, $atualizar, $conflitos, $bloqueia, $importados);
            }
        }

        $vincular = [];
        foreach ($pessoas as $idLex => $pessoa) {
            if (!is_array($pessoa) || empty($importados['aluno'][(string) $idLex])) {
                continue;
            }
            $sponsors = is_array($pessoa['sponsors'] ?? null) ? $pessoa['sponsors'] : [];
            $visto = [];
            foreach ($sponsors as $sponsorId) {
                $sponsorId = trim((string) $sponsorId);
                if ($sponsorId === '' || isset($visto[$sponsorId])) {
                    continue;
                }
                $visto[$sponsorId] = true;
                if (empty($importados['responsavel'][$sponsorId])) {
                    $conflitos[] = self::conflito((string) $idLex, 'aluno_responsavel', 'Responsável não importado.', true);
                    $bloqueia = true;
                    continue;
                }
                $chave = (string) $idLex . '|' . $sponsorId;
                $vincular[] = [
                    'id_lex' => $chave,
                    'aluno_lex' => (string) $idLex,
                    'responsavel_lex' => $sponsorId,
                    'existe' => isset($vinculos['aluno_responsavel'][$chave]),
                ];
            }
        }

        $ausentes = self::ausentes($vinculos, $importados);
        $ausentesVinculo = self::ausentesVinculo($vinculos, $vincular);
        $podeInativar = (bool) ($classificacao['ok'] ?? false) && !$bloqueia;

        return [
            'criar' => $criar,
            'atualizar' => $atualizar,
            'vincular' => $vincular,
            'inativar' => $podeInativar ? $ausentes : [],
            'inativar_vinculos' => $podeInativar ? $ausentesVinculo : [],
            'inativacao_suspensa' => $podeInativar ? 0 : count($ausentes) + count($ausentesVinculo),
            'conflitos' => $conflitos,
            'ignorados' => $ignorados,
            'pode_inativar' => $podeInativar,
            'contagens' => self::contagens($criar, $atualizar, $vincular, $podeInativar ? $ausentes : [], $conflitos, $ignorados),
        ];
    }

    public static function identificador(string $login, string $idLex): string
    {
        $login = trim($login);
        if ($login !== '' && strlen($login) <= 50 && !preg_match('/\s/', $login)) {
            return $login;
        }
        $limpo = strtolower(str_replace('-', '', $idLex));
        return 'lx' . substr($limpo, 0, 20);
    }

    /**
     * @param array<string,mixed> $pessoa
     * @param array<string,mixed> $vinculos
     * @param array<string,mixed> $estado
     * @param list<array<string,mixed>> $criar
     * @param list<array<string,mixed>> $atualizar
     * @param list<array<string,mixed>> $conflitos
     * @param array<string, array<string, bool>> $importados
     */
    private static function pessoa(
        string $idLex,
        string $tipo,
        array $pessoa,
        array $vinculos,
        array &$estado,
        array &$criar,
        array &$atualizar,
        array &$conflitos,
        bool &$bloqueia,
        array &$importados
    ): void {
        if ($tipo === 'usuario' && trim((string) ($pessoa['email'] ?? '')) === '') {
            $conflitos[] = self::conflito($idLex, $tipo, 'Usuário da escola sem e-mail.', true);
            $bloqueia = true;
            return;
        }

        $loginLocal = self::identificador((string) ($pessoa['login'] ?? ''), $idLex);
        $nickname = $tipo === 'aluno' ? $loginLocal : null;

        $existente = $vinculos[$tipo][$idLex] ?? null;
        if (is_array($existente)) {
            $idLocal = (int) ($existente['id_local'] ?? 0);
            if ($tipo === 'usuario') {
                $email = strtolower(trim((string) ($pessoa['email'] ?? '')));
                if ($email !== '' && self::ocupado($estado, 'email_usuario', $email, $idLocal)) {
                    $conflitos[] = self::conflito($idLex, $tipo, 'E-mail já usado por outro usuário da escola.', false);
                    $pessoa['email'] = null;
                }
            }
            $atualizar[] = self::acao($idLex, $tipo, $pessoa, $loginLocal, $nickname, $idLocal);
            $importados[$tipo][$idLex] = true;
            return;
        }

        if ($tipo === 'aluno' && self::ocupado($estado, 'ra', $loginLocal, 0)) {
            $conflitos[] = self::conflito($idLex, $tipo, 'RA já usado por outro aluno.', true);
            $bloqueia = true;
            return;
        }
        if ($tipo === 'professor' && self::ocupado($estado, 'codigo_prof', $loginLocal, 0)) {
            $conflitos[] = self::conflito($idLex, $tipo, 'Código de professor já usado.', true);
            $bloqueia = true;
            return;
        }
        if ($tipo === 'usuario' && self::ocupado($estado, 'email_usuario', strtolower(trim((string) ($pessoa['email'] ?? ''))), 0)) {
            $conflitos[] = self::conflito($idLex, $tipo, 'E-mail já usado por outro usuário da escola.', true);
            $bloqueia = true;
            return;
        }
        if ($tipo === 'aluno' && self::ocupado($estado, 'nickname', $loginLocal, 0)) {
            $conflitos[] = self::conflito($idLex, $tipo, 'Nickname já usado por outro aluno.', true);
            $bloqueia = true;
            return;
        }

        $criar[] = self::acao($idLex, $tipo, $pessoa, $loginLocal, $nickname, null);
        $importados[$tipo][$idLex] = true;
        self::reservar($estado, $tipo, $loginLocal, $nickname, $pessoa);
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $pessoa
     */
    private static function reservar(array &$estado, string $tipo, string $loginLocal, ?string $nickname, array $pessoa): void
    {
        $reserva = -1 * (count($estado['ra'] ?? []) + count($estado['codigo_prof'] ?? []) + count($estado['email_usuario'] ?? []) + count($estado['nickname'] ?? []) + 1);
        $login = strtolower($loginLocal);
        if ($tipo === 'aluno') {
            $estado['ra'][$login] = $reserva;
            if ($nickname !== null && $nickname !== '') {
                $estado['nickname'][strtolower($nickname)] = $reserva;
            }
        }
        if ($tipo === 'professor') {
            $estado['codigo_prof'][$login] = $reserva;
        }
        if ($tipo === 'usuario') {
            $email = strtolower(trim((string) ($pessoa['email'] ?? '')));
            if ($email !== '') {
                $estado['email_usuario'][$email] = $reserva;
            }
        }
    }

    /**
     * @param array<string,mixed> $pessoa
     * @return array<string,mixed>
     */
    private static function acao(
        string $idLex,
        string $tipo,
        array $pessoa,
        string $loginLocal,
        ?string $nickname,
        ?int $idLocal
    ): array {
        return [
            'tipo' => $tipo,
            'id_lex' => $idLex,
            'id_local' => $idLocal,
            'account_id' => $pessoa['account_id'] ?? null,
            'nome' => trim((string) ($pessoa['nome'] ?? '')),
            'email' => ($pessoa['email'] ?? null) !== null ? trim((string) $pessoa['email']) : null,
            'nascimento' => $pessoa['nascimento'] ?? null,
            'cpf' => $pessoa['cpf'] ?? null,
            'login_local' => $loginLocal,
            'nickname' => $nickname,
            'perfil_admin' => $pessoa['perfil_admin'] ?? null,
        ];
    }

    /**
     * @return array{id_lex:string,tipo:string,mensagem:string,bloqueia:bool}
     */
    private static function conflito(string $idLex, string $tipo, string $mensagem, bool $bloqueia): array
    {
        return [
            'id_lex' => $idLex,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'bloqueia' => $bloqueia,
        ];
    }

    /**
     * @param array<string,mixed> $estado
     */
    private static function ocupado(array $estado, string $campo, string $valor, int $idLocal): bool
    {
        $valor = strtolower(trim($valor));
        if ($valor === '') {
            return false;
        }
        $mapa = is_array($estado[$campo] ?? null) ? $estado[$campo] : [];
        foreach ($mapa as $existente => $dono) {
            if (strtolower(trim((string) $existente)) === $valor && (int) $dono !== $idLocal) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $vinculos
     * @param array<string, array<string, bool>> $importados
     * @return list<array{tipo:string,id_lex:string,id_local:int}>
     */
    private static function ausentes(array $vinculos, array $importados): array
    {
        $lista = [];
        foreach (['aluno', 'professor', 'responsavel', 'usuario'] as $tipo) {
            $mapa = is_array($vinculos[$tipo] ?? null) ? $vinculos[$tipo] : [];
            foreach ($mapa as $idLex => $vinculo) {
                if (!is_array($vinculo) || ($vinculo['status'] ?? 'ativo') !== 'ativo') {
                    continue;
                }
                if (!empty($importados[$tipo][(string) $idLex])) {
                    continue;
                }
                $lista[] = [
                    'tipo' => $tipo,
                    'id_lex' => (string) $idLex,
                    'id_local' => (int) ($vinculo['id_local'] ?? 0),
                ];
            }
        }
        return $lista;
    }

    /**
     * @param array<string,mixed> $vinculos
     * @param list<array<string,mixed>> $vincular
     * @return list<array{tipo:string,id_lex:string,id_local:int}>
     */
    private static function ausentesVinculo(array $vinculos, array $vincular): array
    {
        $atuais = [];
        foreach ($vincular as $item) {
            $atuais[(string) ($item['id_lex'] ?? '')] = true;
        }
        $lista = [];
        $mapa = is_array($vinculos['aluno_responsavel'] ?? null) ? $vinculos['aluno_responsavel'] : [];
        foreach ($mapa as $idLex => $vinculo) {
            if (!is_array($vinculo) || ($vinculo['status'] ?? 'ativo') !== 'ativo') {
                continue;
            }
            if (!empty($atuais[(string) $idLex])) {
                continue;
            }
            $lista[] = [
                'tipo' => 'aluno_responsavel',
                'id_lex' => (string) $idLex,
                'id_local' => (int) ($vinculo['id_local'] ?? 0),
            ];
        }
        return $lista;
    }

    /**
     * @param list<array<string,mixed>> $criar
     * @param list<array<string,mixed>> $atualizar
     * @param list<array<string,mixed>> $vincular
     * @param list<array<string,mixed>> $inativar
     * @param list<array<string,mixed>> $conflitos
     * @param list<array<string,mixed>> $ignorados
     * @return array<string,mixed>
     */
    private static function contagens(array $criar, array $atualizar, array $vincular, array $inativar, array $conflitos, array $ignorados): array
    {
        $contagem = [];
        foreach (['aluno', 'professor', 'responsavel', 'usuario'] as $tipo) {
            $contagem[$tipo] = ['criar' => 0, 'atualizar' => 0];
        }
        foreach ($criar as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            if (isset($contagem[$tipo])) {
                $contagem[$tipo]['criar']++;
            }
        }
        foreach ($atualizar as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            if (isset($contagem[$tipo])) {
                $contagem[$tipo]['atualizar']++;
            }
        }
        $contagem['vinculos_responsavel'] = count($vincular);
        $contagem['inativados'] = count($inativar);
        $contagem['conflitos'] = count($conflitos);
        $contagem['ignorados'] = count($ignorados);
        return $contagem;
    }
}
