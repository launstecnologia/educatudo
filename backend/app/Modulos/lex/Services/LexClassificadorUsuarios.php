<?php

namespace App\Modulos\Lex\Services;

/**
 * Lê o Sync Full e separa as pessoas da escola configurada.
 * O papel sai dos grupos desta escola. Turma, série e matrícula ficam no classificador acadêmico.
 */
class LexClassificadorUsuarios
{
    private const PAPEL_ADMIN = [
        'manager' => 'diretor',
        'coordinator' => 'coordenador',
        'secretary' => 'secretaria',
    ];

    private const PAPEIS_UTEIS = ['student', 'teacher', 'sponsor', 'manager', 'coordinator', 'secretary'];

    private const IGNORADOS = ['assistant', 'operator'];

    /**
     * @param array<string,mixed> $resposta
     * @return array{
     *   ok: bool,
     *   erro: ?string,
     *   sync_id: ?string,
     *   sync_date: ?string,
     *   pessoas: array<string, array<string,mixed>>,
     *   ignorados: list<array{id_lex:string,mensagem:string}>
     * }
     */
    public static function classificar(
        array $resposta,
        string $tenantId,
        string $escolaLexId,
        string $externalId
    ): array {
        $vazio = [
            'ok' => false,
            'erro' => null,
            'sync_id' => null,
            'sync_date' => null,
            'pessoas' => [],
            'ignorados' => [],
        ];

        $header = is_array($resposta['header'] ?? null) ? $resposta['header'] : [];
        $payload = is_array($resposta['payload'] ?? null) ? $resposta['payload'] : null;
        $syncId = trim((string) ($header['syncId'] ?? ''));
        if ($syncId === '' || $payload === null) {
            $vazio['erro'] = 'O snapshot da LEX não tem o formato esperado.';
            return $vazio;
        }

        $tenantPayload = trim((string) ($payload['tenantId'] ?? ''));
        if ($tenantId === '' || strcasecmp($tenantPayload, $tenantId) !== 0) {
            $vazio['erro'] = 'O tenant do snapshot não é o tenant configurado.';
            $vazio['sync_id'] = $syncId;
            return $vazio;
        }

        $escolas = is_array($payload['schools'] ?? null) ? $payload['schools'] : [];
        $escola = self::escolaUnica($escolas, $escolaLexId, $externalId);
        if (isset($escola['erro'])) {
            $vazio['erro'] = $escola['erro'];
            $vazio['sync_id'] = $syncId;
            return $vazio;
        }

        $perfis = self::mapaPerfis(is_array($payload['profiles'] ?? null) ? $payload['profiles'] : []);
        $usuarios = self::mapaUsuarios(is_array($payload['users'] ?? null) ? $payload['users'] : []);
        $papeisPorUsuario = self::papeisDosGrupos(
            is_array($escola['escola']['groups'] ?? null) ? $escola['escola']['groups'] : [],
            $perfis
        );

        foreach ($papeisPorUsuario as $id => $papeis) {
            if (empty($papeis['student']) || !isset($usuarios[$id])) {
                continue;
            }
            foreach ($usuarios[$id]['sponsors'] as $sponsorId) {
                if (isset($usuarios[$sponsorId])) {
                    $papeisPorUsuario[$sponsorId]['sponsor'] = true;
                }
            }
        }

        $pessoas = [];
        $ignorados = [];
        foreach ($usuarios as $id => $usuario) {
            if (!isset($papeisPorUsuario[$id])) {
                continue;
            }
            $papeisBrutos = array_keys($papeisPorUsuario[$id]);
            $uteis = [];
            $temIgnorado = false;
            foreach ($papeisBrutos as $papel) {
                if (in_array($papel, self::PAPEIS_UTEIS, true)) {
                    $uteis[] = $papel;
                } elseif (in_array($papel, self::IGNORADOS, true)) {
                    $temIgnorado = true;
                }
            }

            $uteis = array_values(array_unique($uteis));
            if ($uteis === []) {
                $ignorados[] = [
                    'id_lex' => $id,
                    'mensagem' => $temIgnorado
                        ? 'Perfil não importado nesta etapa.'
                        : 'Usuário sem papel de aluno, professor, responsável ou usuário da escola.',
                ];
                continue;
            }

            $perfilAdmin = null;
            foreach (['manager', 'coordinator', 'secretary'] as $papelAdmin) {
                if (in_array($papelAdmin, $uteis, true)) {
                    $perfilAdmin = self::PAPEL_ADMIN[$papelAdmin];
                    break;
                }
            }

            $pessoas[$id] = [
                'id' => $id,
                'account_id' => $usuario['account_id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'nascimento' => $usuario['nascimento'],
                'cpf' => $usuario['cpf'],
                'login' => $usuario['login'],
                'papeis' => $uteis,
                'perfil_admin' => $perfilAdmin,
                'sponsors' => $usuario['sponsors'],
            ];
        }

        return [
            'ok' => true,
            'erro' => null,
            'sync_id' => $syncId,
            'sync_date' => trim((string) ($header['syncDate'] ?? '')) ?: null,
            'pessoas' => $pessoas,
            'ignorados' => $ignorados,
        ];
    }

    /**
     * @param list<mixed> $escolas
     * @return array{escola: array<string,mixed>}|array{erro: string}
     */
    private static function escolaUnica(array $escolas, string $escolaLexId, string $externalId): array
    {
        $escolaLexId = trim($escolaLexId);
        $externalId = trim($externalId);
        if ($escolaLexId === '' && $externalId === '') {
            return ['erro' => 'Configure o id da escola na LEX ou o externalId.'];
        }

        $achadas = [];
        foreach ($escolas as $escola) {
            if (!is_array($escola)) {
                continue;
            }
            $id = trim((string) ($escola['id'] ?? ''));
            $externo = trim((string) ($escola['externalId'] ?? $escola['external_id'] ?? ''));
            $bateId = $escolaLexId !== '' && strcasecmp($id, $escolaLexId) === 0;
            $bateExterno = $externalId !== '' && strcasecmp($externo, $externalId) === 0;
            if ($bateId || $bateExterno) {
                $achadas[] = $escola;
            }
        }

        if (count($achadas) !== 1) {
            return ['erro' => count($achadas) === 0
                ? 'A escola configurada não veio no snapshot da LEX.'
                : 'Mais de uma escola da LEX corresponde à configuração.'];
        }

        return ['escola' => $achadas[0]];
    }

    /**
     * @param list<mixed> $perfis
     * @return array<string, string>
     */
    private static function mapaPerfis(array $perfis): array
    {
        $mapa = [];
        foreach ($perfis as $perfil) {
            if (!is_array($perfil)) {
                continue;
            }
            $id = trim((string) ($perfil['id'] ?? ''));
            $tipo = strtolower(trim((string) ($perfil['type'] ?? '')));
            if ($id !== '' && $tipo !== '') {
                $mapa[$id] = $tipo;
            }
        }
        return $mapa;
    }

    /**
     * @param list<mixed> $usuarios
     * @return array<string, array<string,mixed>>
     */
    private static function mapaUsuarios(array $usuarios): array
    {
        $mapa = [];
        foreach ($usuarios as $usuario) {
            if (!is_array($usuario)) {
                continue;
            }
            $id = trim((string) ($usuario['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $sponsors = [];
            $bruto = $usuario['sponsorsId'] ?? $usuario['sponsors_id'] ?? [];
            if (is_array($bruto)) {
                foreach ($bruto as $sponsorId) {
                    $sponsorId = trim((string) $sponsorId);
                    if ($sponsorId !== '') {
                        $sponsors[] = $sponsorId;
                    }
                }
            }
            $mapa[$id] = [
                'account_id' => trim((string) ($usuario['accountId'] ?? $usuario['account_id'] ?? '')) ?: null,
                'nome' => trim((string) ($usuario['name'] ?? '')),
                'email' => trim((string) ($usuario['email'] ?? '')) ?: null,
                'nascimento' => self::data((string) ($usuario['birthDate'] ?? '')),
                'cpf' => self::cpf((string) ($usuario['documentType'] ?? ''), (string) ($usuario['document'] ?? '')),
                'login' => trim((string) ($usuario['login'] ?? '')),
                'sponsors' => array_values(array_unique($sponsors)),
            ];
        }
        return $mapa;
    }

    /**
     * @param list<mixed> $grupos
     * @param array<string, string> $perfis
     * @return array<string, array<string, bool>>
     */
    private static function papeisDosGrupos(array $grupos, array $perfis): array
    {
        $papeis = [];
        foreach ($grupos as $grupo) {
            if (!is_array($grupo) || !is_array($grupo['users'] ?? null)) {
                continue;
            }
            foreach ($grupo['users'] as $vinculo) {
                if (!is_array($vinculo)) {
                    continue;
                }
                $userId = trim((string) ($vinculo['userId'] ?? $vinculo['user_id'] ?? ''));
                $profileId = trim((string) ($vinculo['profileId'] ?? $vinculo['profile_id'] ?? ''));
                $tipo = $perfis[$profileId] ?? '';
                if ($userId === '' || $tipo === '') {
                    continue;
                }
                $papeis[$userId][$tipo] = true;
            }
        }
        return $papeis;
    }

    private static function data(string $valor): ?string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', trim($valor), $m)) {
            return $m[1];
        }
        return null;
    }

    private static function cpf(string $tipo, string $documento): ?string
    {
        if (strtolower(trim($tipo)) !== 'cpf') {
            return null;
        }
        $digitos = preg_replace('/\D+/', '', $documento) ?? '';
        return strlen($digitos) === 11 ? $digitos : null;
    }
}
