<?php

/**
 * Perfil admin_escola "secretaria": acesso restrito a cadastros acadêmicos,
 * gestão escolar (sem pacotes de crédito) e avaliações.
 */
class AdminSecretariaAccess
{
    public const PROFILE = 'secretaria';

    public static function isSecretaria(?array $user): bool
    {
        return $user !== null
            && ($user['tipo'] ?? '') === 'admin_escola'
            && (string) ($user['perfil_admin'] ?? '') === self::PROFILE;
    }

    /**
     * Perfis admin_escola com acesso a gestão pedagógica (faltas, provas/blocos, etc.).
     *
     * @return list<string>
     */
    public static function perfisAdminEscolaGestaoPedagogica(): array
    {
        return ['dev', 'diretor', 'coordenador', 'secretaria'];
    }

    public static function homePath(): string
    {
        return '/admin/students';
    }

    /**
     * Prefixos de caminho (parse_url PATH) permitidos após /admin/login.
     *
     * @return list<string>
     */
    public static function allowedUriPrefixes(): array
    {
        return [
            '/admin/students',
            '/admin/teachers',
            '/admin/turmas',
            '/admin/ano-letivo',
            '/admin/curso',
            '/admin/serie',
            '/admin/grade-horaria',
            '/admin/subjects',
            '/admin/ocorrencias',
            '/admin/faltas',
            '/admin/diario',
            '/admin/exercises',
            '/admin/provas',
            '/admin/provas-professor',
            '/admin/jornadas',
            '/admin/redacao-professor',
            '/admin/blocos-modelo',
            '/media/',
        ];
    }

    public static function isDeniedPath(string $path): bool
    {
        return str_starts_with($path, '/admin/creditos');
    }

    public static function requestPathIsAllowed(string $requestUri): bool
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        if ($path === '/logout' || str_starts_with($path, '/logout')) {
            return true;
        }
        if (self::isDeniedPath($path)) {
            return false;
        }
        foreach (self::allowedUriPrefixes() as $prefix) {
            if ($prefix !== '' && strncmp($path, $prefix, strlen($prefix)) === 0) {
                return true;
            }
        }

        return false;
    }
}
