<?php
/**
 * URLs e tokens do EducaHits (produto externo educahits.educatudo.com).
 *
 * Documentação do fluxo atual: docs/EDUCAHITS_INTEGRACAO_EXTERNA.md
 * (documentacao_educahits_edutudo.pdf = módulo legado embutido no EducaTudo.)
 */
class EducaHitsConfig
{
    private const DEFAULT_PORTAL_LOGIN = 'https://educahits.educatudo.com/login?token=EDUCAHIST2026';

    private const DEFAULT_RECEIVE_SONG = 'https://uqibjsxselysgtmtuhcj.supabase.co/functions/v1/receive-song';

    public static function portalLoginUrl(): string
    {
        $u = function_exists('env') ? trim((string) env('EDUCAHITS_PORTAL_LOGIN_URL', '')) : '';

        return $u !== '' ? $u : self::DEFAULT_PORTAL_LOGIN;
    }

    public static function portalRequestUrl(): string
    {
        $u = function_exists('env') ? trim((string) env('EDUCAHITS_PORTAL_REQUEST_URL', '')) : '';

        return $u !== '' ? $u : self::portalLoginUrl();
    }

    public static function masterDashboardUrl(): string
    {
        return function_exists('env') ? trim((string) env('EDUCAHITS_MASTER_DASHBOARD_URL', '')) : '';
    }

    public static function receiveSongUrl(): string
    {
        $u = function_exists('env') ? trim((string) env('EDUCAHITS_RECEIVE_SONG_URL', '')) : '';

        return $u !== '' ? $u : self::DEFAULT_RECEIVE_SONG;
    }

    /**
     * Valor exato do header Authorization: Bearer … na entrega e APIs relacionadas.
     * Deve coincidir com o secret ADMIN_API_KEY da Edge Function receive-song (não confundir com ?token= do portal).
     */
    public static function receiveBearerToken(): string
    {
        $admin = function_exists('env') ? trim((string) env('EDUCAHITS_ADMIN_API_KEY', '')) : '';
        if ($admin !== '') {
            return $admin;
        }

        return function_exists('env') ? trim((string) env('EDUCAHITS_RECEIVE_API_TOKEN', '')) : '';
    }

    /** @deprecated Preferir receiveBearerToken(); mantido como alias para compatibilidade com .env antigo. */
    public static function receiveApiToken(): string
    {
        return self::receiveBearerToken();
    }

    /**
     * Header apikey nas URLs supabase.co (gateway). Se vazio no .env, reutiliza o Bearer (comportamento antigo).
     * Quando o Bearer é só ADMIN_API_KEY, defina EDUCAHITS_SUPABASE_ANON_KEY com a anon key do projeto.
     */
    public static function receiveSupabaseGatewayApiKey(): string
    {
        $anon = function_exists('env') ? trim((string) env('EDUCAHITS_SUPABASE_ANON_KEY', '')) : '';

        return $anon !== '' ? $anon : self::receiveBearerToken();
    }

    /** Timeout total do cURL no upload (segundos). Default alto para ficheiros grandes. */
    public static function receiveCurlTimeoutSeconds(): int
    {
        $raw = function_exists('env') ? trim((string) env('EDUCAHITS_RECEIVE_CURL_TIMEOUT', '')) : '';
        $sec = $raw !== '' ? (int) $raw : 600;

        return max(60, min($sec, 3600));
    }

    /**
     * Tentativas em caso de 502/504/524 (gateway Supabase) ou falha de rede recuperável.
     * Default 3 = até 3 envios com espera entre eles (cold start / timeout transitório).
     */
    public static function receiveMaxAttempts(): int
    {
        $n = function_exists('env') ? (int) env('EDUCAHITS_RECEIVE_MAX_ATTEMPTS', 3) : 3;

        return max(1, min($n, 8));
    }

    /**
     * Novo modo recomendado: 2 etapas (POST JSON + PUT direto em URL assinada).
     * Default = ligado (1). Defina 0 para forçar o multipart legado.
     */
    public static function preferDirectUpload(): bool
    {
        $raw = function_exists('env') ? trim((string) env('EDUCAHITS_PREFER_DIRECT_UPLOAD', '1')) : '1';

        return $raw !== '0';
    }

    public static function masterRequestsApiUrl(): string
    {
        return function_exists('env') ? trim((string) env('EDUCAHITS_MASTER_REQUESTS_API', '')) : '';
    }

    public static function masterSongsApiUrl(): string
    {
        return function_exists('env') ? trim((string) env('EDUCAHITS_MASTER_SONGS_API', '')) : '';
    }

    public static function deleteSongApiUrl(): string
    {
        return function_exists('env') ? trim((string) env('EDUCAHITS_DELETE_SONG_API', '')) : '';
    }

    public static function showSolicitarSubLink(): bool
    {
        if (function_exists('env') && (string) env('EDUCAHITS_SHOW_REQUEST_LINK', '') === '1') {
            return true;
        }

        return strpos(strtolower($_SERVER['HTTP_HOST'] ?? ''), 'demo.educatudo.com') !== false;
    }
}
