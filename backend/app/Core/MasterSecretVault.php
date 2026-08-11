<?php
/**
 * Criptografia simples para secrets no Master (API keys).
 * Usa AES-256-CBC; chave derivada de MASTER_ENCRYPTION_KEY ou APP_KEY no .env.
 */
class MasterSecretVault
{
    private const CIPHER = 'AES-256-CBC';

    /** Marcador de senha de banco de tenant já cifrada (permite detectar valores legados em texto puro). */
    private const DB_PREFIX = 'enc:v1:';

    public static function getKey(): string
    {
        static $cachedKey = null;
        if ($cachedKey !== null) {
            return $cachedKey;
        }

        $raw = self::resolveRawKey();

        if ($raw === '') {
            if (self::isProduction()) {
                throw new \RuntimeException(
                    'MASTER_ENCRYPTION_KEY não configurada. ' .
                    'Defina esta variável de ambiente antes de iniciar em produção.'
                );
            }
            // Em desenvolvimento: chave fraca aceitável, mas loga aviso
            error_log('[MasterSecretVault] AVISO: usando chave de desenvolvimento. Nunca use em produção.');
            $raw = 'educatudo-dev-change-me';
        }

        $cachedKey = hash('sha256', $raw, true);
        return $cachedKey;
    }

    /**
     * Resolve a chave crua (MASTER_ENCRYPTION_KEY ou APP_KEY) de forma independente
     * da ordem de carregamento. Importante: o bootstrap multi-tenant decifra a senha
     * do tenant ANTES de config/app.php definir env(), então não podemos depender só
     * de env() — lemos o .env diretamente, do mesmo jeito que a migração leu.
     */
    private static function resolveRawKey(): string
    {
        // 1) env() (disponível quando config/app.php já foi carregado — é o caminho da migração CLI).
        if (function_exists('env')) {
            $v = (string) env('MASTER_ENCRYPTION_KEY', '');
            if ($v === '') {
                $v = (string) env('APP_KEY', '');
            }
            if ($v !== '') {
                return $v;
            }
        }

        // 2) .env diretamente (mesma leitura de config/app.php: trim, sem remover aspas),
        //    garantindo o MESMO valor que a migração usou, mesmo no bootstrap do tenant.
        $fromFile = self::readKeyFromEnvFile();
        if ($fromFile !== '') {
            return $fromFile;
        }

        // 3) Variáveis de ambiente reais (deploys sem arquivo .env).
        foreach (['MASTER_ENCRYPTION_KEY', 'APP_KEY'] as $name) {
            $v = getenv($name);
            if ($v === false || $v === '') {
                $v = $_ENV[$name] ?? $_SERVER[$name] ?? '';
            }
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * Lê MASTER_ENCRYPTION_KEY (ou APP_KEY) direto do arquivo .env, replicando o
     * parsing de config/app.php (split no primeiro '=', trim, sem remover aspas).
     */
    private static function readKeyFromEnvFile(): string
    {
        $candidates = [];
        if (defined('ENV_FILE_PATH')) {
            $candidates[] = ENV_FILE_PATH;
        }
        $candidates[] = __DIR__ . '/../../.env';
        $candidates[] = __DIR__ . '/../../../.env';

        foreach ($candidates as $path) {
            if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            $found = ['MASTER_ENCRYPTION_KEY' => '', 'APP_KEY' => ''];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                if ($name === 'MASTER_ENCRYPTION_KEY' || $name === 'APP_KEY') {
                    $found[$name] = trim($value);
                }
            }
            if ($found['MASTER_ENCRYPTION_KEY'] !== '') {
                return $found['MASTER_ENCRYPTION_KEY'];
            }
            if ($found['APP_KEY'] !== '') {
                return $found['APP_KEY'];
            }
        }

        return '';
    }

    private static function isProduction(): bool
    {
        if (function_exists('env') && env('APP_ENV') === 'production') {
            return true;
        }
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            return true;
        }
        return false;
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        $iv = random_bytes(16);
        $enc = openssl_encrypt($plain, self::CIPHER, self::getKey(), OPENSSL_RAW_DATA, $iv);
        if ($enc === false) {
            return null;
        }
        return base64_encode($iv . $enc);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $bin = base64_decode($stored, true);
        if ($bin === false || strlen($bin) < 17) {
            return null;
        }
        $iv = substr($bin, 0, 16);
        $ct = substr($bin, 16);
        $plain = openssl_decrypt($ct, self::CIPHER, self::getKey(), OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : null;
    }

    /**
     * Verifica se um valor já está cifrado no formato de senha de banco de tenant.
     */
    public static function isEncryptedDbPassword(?string $stored): bool
    {
        $stored = (string) $stored;
        return strncmp($stored, self::DB_PREFIX, strlen(self::DB_PREFIX)) === 0;
    }

    /**
     * Cifra a senha do banco de um tenant para gravar em config_escolas_banco.senha_criptografada.
     * Mantém string vazia/null como está (escola sem senha própria).
     * Se o valor já vier cifrado, não cifra de novo (idempotente).
     */
    public static function encryptDbPassword(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }
        if (self::isEncryptedDbPassword($plain)) {
            return $plain;
        }
        $enc = self::encrypt($plain);
        return $enc === null ? null : self::DB_PREFIX . $enc;
    }

    /**
     * Decifra a senha do banco de um tenant lida de config_escolas_banco.senha_criptografada.
     * Retrocompatível: valores sem o marcador são tratados como legado em texto puro
     * e devolvidos como estão (permite migração gradual sem quebrar conexões).
     */
    public static function decryptDbPassword(?string $stored): string
    {
        $stored = (string) $stored;
        if ($stored === '') {
            return '';
        }
        if (!self::isEncryptedDbPassword($stored)) {
            return $stored;
        }
        $plain = self::decrypt(substr($stored, strlen(self::DB_PREFIX)));
        return $plain ?? '';
    }
}
