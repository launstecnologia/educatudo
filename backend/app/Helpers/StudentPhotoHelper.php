<?php

/**
 * Resolve exibição de foto do aluno no admin (alunos.foto_url + fallback BLOB legado).
 */
class StudentPhotoHelper
{
    /**
     * Iniciais a partir do nome (máx. 2 caracteres).
     */
    public static function initialsFromName(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || count($parts) === 0) {
            return strtoupper(mb_substr($nome, 0, 2));
        }

        if (count($parts) === 1) {
            return strtoupper(mb_substr($parts[0], 0, 2));
        }

        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }

    /**
     * Normaliza foto_url para exibição; ignora valores inválidos (ex.: email colado por engano).
     */
    public static function resolveDisplayUrl(array $aluno): ?string
    {
        $raw = trim((string) ($aluno['foto_url'] ?? ''));
        if ($raw === '' || !self::isPlausiblePhotoReference($raw)) {
            return null;
        }

        require_once __DIR__ . '/AvatarUrlHelper.php';

        return AvatarUrlHelper::normalizeAdminAvatarUrl($raw);
    }

    /**
     * Converte coluna FotoAluno (BLOB/base64) em data URI para exibição no detalhe.
     */
    public static function resolveDataUriFromBlob(?string $raw): ?string
    {
        if ($raw === null || !is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $maxBytes = 10 * 1024 * 1024;

        if (stripos($raw, 'data:image/') === 0) {
            return $raw;
        }

        $binary = null;
        $decoded = base64_decode($raw, true);
        if ($decoded !== false && $decoded !== '' && strlen($decoded) <= $maxBytes) {
            $binary = $decoded;
        } elseif (strlen($raw) <= $maxBytes) {
            $binary = $raw;
        }

        if (!is_string($binary) || $binary === '') {
            return null;
        }

        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = @finfo_buffer($finfo, $binary);
                @finfo_close($finfo);
                if (is_string($detected) && strpos($detected, 'image/') === 0) {
                    $mime = $detected;
                }
            }
        }

        if (!$mime && function_exists('getimagesizefromstring')) {
            $imgInfo = @getimagesizefromstring($binary);
            if (is_array($imgInfo) && !empty($imgInfo['mime']) && strpos($imgInfo['mime'], 'image/') === 0) {
                $mime = $imgInfo['mime'];
            }
        }

        if (!$mime) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    /**
     * Resolve URL de exibição para listagem/edição (sem BLOB).
     */
    public static function resolveForList(array $aluno): array
    {
        $url = self::resolveDisplayUrl($aluno);

        return [
            'foto_display_url' => $url,
            'foto_initials' => self::initialsFromName(self::nomeParaIniciais($aluno)),
        ];
    }

    /**
     * Resolve foto para ficha detalhada (inclui fallback FotoAluno BLOB).
     */
    public static function resolveForShow(array $aluno): array
    {
        $url = self::resolveDisplayUrl($aluno);

        if ($url === null && !empty($aluno['FotoAluno'])) {
            $url = self::resolveDataUriFromBlob(is_string($aluno['FotoAluno']) ? $aluno['FotoAluno'] : null);
        }

        return [
            'foto_display_url' => $url,
            'foto_initials' => self::initialsFromName(self::nomeParaIniciais($aluno)),
        ];
    }

    /**
     * Enriquece array de aluno com campos de foto para views.
     */
    public static function enrichStudent(array $aluno, bool $includeBlob = false): array
    {
        require_once __DIR__ . '/StudentFormHelper.php';
        $aluno = \StudentFormHelper::aplicarNomeExibicao($aluno, false);
        $resolved = $includeBlob ? self::resolveForShow($aluno) : self::resolveForList($aluno);
        $aluno['foto_display_url'] = $resolved['foto_display_url'];
        $aluno['foto_initials'] = $resolved['foto_initials'];

        return $aluno;
    }

    /** @param array<string, mixed> $aluno */
    private static function nomeParaIniciais(array $aluno): string
    {
        require_once __DIR__ . '/StudentFormHelper.php';
        $nome = \StudentFormHelper::nomeExibicao($aluno);

        return $nome !== '' ? $nome : (string) ($aluno['nome'] ?? '');
    }

    private static function isPlausiblePhotoReference(string $raw): bool
    {
        if (stripos($raw, '/media/serve') !== false) {
            return true;
        }

        if (preg_match('#^https?://#i', $raw)) {
            $path = parse_url($raw, PHP_URL_PATH);
            if (is_string($path) && stripos($path, '/media/serve') !== false) {
                return true;
            }
            if (preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $path ?? '')) {
                return true;
            }

            return false;
        }

        if (strpos($raw, '/uploads/') !== false) {
            return true;
        }

        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (strpos($raw, '@') !== false && stripos($raw, '/media/serve') === false) {
            return false;
        }

        return preg_match('/\.(jpe?g|png|gif|webp)$/i', $raw) === 1;
    }
}
