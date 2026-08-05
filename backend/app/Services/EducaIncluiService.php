<?php

require_once __DIR__ . '/../Helpers/CatalogoRegraMascara.php';
require_once __DIR__ . '/../Models/EducaInclui/MascaraAluno.php';
require_once __DIR__ . '/../Models/EducaInclui/RegraMascara.php';
require_once __DIR__ . '/../Models/EducaInclui/LaudoAluno.php';
require_once __DIR__ . '/../Models/EducaInclui/VersaoAdaptadaLog.php';

if (!class_exists('EducaIncluiService')) {
/**
 * EducaInclui — regras de negócio da Máscara de Acessibilidade + LGPD do laudo.
 */
class EducaIncluiService
{
    private const ALLOWED_MIME = ['application/pdf', 'image/png', 'image/jpeg'];
    private const MAX_FILE_BYTES = 15 * 1024 * 1024; // 15MB

    /** Ação de auditoria usada para a aprovação reforçada de máscaras significativas. */
    public const ACTION_REINFORCED_APPROVAL = 'aprovacao_reforcada';
    /** Nº mínimo de aprovadores distintos para ativar máscara significativa. */
    public const REINFORCED_APPROVALS_REQUIRED = 1;

    private $accommodations;
    private $rules;
    private $documents;
    private $log;

    public function __construct()
    {
        $this->accommodations = new MascaraAluno();
        $this->rules = new RegraMascara();
        $this->documents = new LaudoAluno();
        $this->log = new VersaoAdaptadaLog();
    }

    /**
     * Cria ou atualiza a máscara do aluno e regrava as regras.
     *
     * @return array{id:int, errors:array<string,string>}
     */
    public function saveMask(array $post, int $userId): array
    {
        $errors = [];
        $alunoId = (int) ($post['aluno_id'] ?? 0);
        if ($alunoId <= 0) {
            $errors['aluno_id'] = 'Aluno inválido.';
            return ['id' => 0, 'errors' => $errors];
        }

        $mascaraId = (int) ($post['mascara_id'] ?? 0);
        $rules = $this->buildRulesFromPost($post);

        // Regra significativa força o tipo (dispara aprovação reforçada na ativação).
        $tipo = in_array(($post['tipo_adaptacao'] ?? 'significativa'), ['acesso', 'significativa'], true)
            ? $post['tipo_adaptacao'] : 'significativa';
        if (CatalogoRegraMascara::hasSignificativaRule($rules)) {
            $tipo = 'significativa';
        }

        $data = [
            'aluno_id' => $alunoId,
            'tipo_adaptacao' => $tipo,
            'data_inicio' => $this->normalizeDate($post['data_inicio'] ?? ''),
            'data_fim' => $this->normalizeDate($post['data_fim'] ?? ''),
            'base_legal' => trim((string) ($post['base_legal'] ?? '')),
            'ref_consentimento' => trim((string) ($post['ref_consentimento'] ?? '')),
            'observacoes' => trim((string) ($post['observacoes'] ?? '')),
        ];

        if ($mascaraId > 0) {
            $this->accommodations->update($mascaraId, $data);
        } else {
            $data['status'] = 'rascunho';
            $mascaraId = $this->accommodations->create($data, $userId);
        }

        $this->rules->substituirPorMascara($mascaraId, $rules);

        $this->log->record('mascara_salva', [
            'mascara_id' => $mascaraId,
            'aluno_id' => $alunoId,
            'user_id' => $userId,
            'details' => ['rules' => array_keys($rules)],
        ]);

        return ['id' => $mascaraId, 'errors' => $errors];
    }

    /**
     * Ativa a máscara (aprovação da coordenação/AEE). Exige base legal e consentimento.
     *
     * @return array{ok:bool, error:string}
     */
    public function activate(int $mascaraId, int $userId): array
    {
        $acc = $this->accommodations->getById($mascaraId);
        if (!$acc) {
            return ['ok' => false, 'error' => 'Máscara não encontrada.'];
        }
        if (trim((string) ($acc['base_legal'] ?? '')) === '' || trim((string) ($acc['ref_consentimento'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'Para ativar, informe a base legal e a referência de consentimento (LGPD).'];
        }
        // Adaptação significativa exige aprovação reforçada (aprovadores distintos).
        if (($acc['tipo_adaptacao'] ?? 'acesso') === 'significativa') {
            $aprovadores = $this->log->countDistinctApprovers($mascaraId, self::ACTION_REINFORCED_APPROVAL);
            if ($aprovadores < self::REINFORCED_APPROVALS_REQUIRED) {
                $req = self::REINFORCED_APPROVALS_REQUIRED;
                return [
                    'ok' => false,
                    'error' => 'Adaptação significativa exige ' . $req
                        . ($req > 1 ? ' aprovações reforçadas distintas' : ' aprovação reforçada')
                        . ' (atual: ' . $aprovadores . ').',
                ];
            }
        }
        $this->accommodations->setStatus($mascaraId, 'ativa', $userId);
        $this->log->record('mascara_ativada', [
            'mascara_id' => $mascaraId,
            'aluno_id' => (int) $acc['aluno_id'],
            'user_id' => $userId,
        ]);
        return ['ok' => true, 'error' => ''];
    }

    /**
     * Registra uma aprovação reforçada (para máscaras significativas).
     *
     * @return array{ok:bool, error:string, count:int}
     */
    public function registerReinforcedApproval(int $mascaraId, int $userId): array
    {
        $acc = $this->accommodations->getById($mascaraId);
        if (!$acc) {
            return ['ok' => false, 'error' => 'Máscara não encontrada.', 'count' => 0];
        }
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Usuário inválido.', 'count' => 0];
        }
        if ($this->log->userHasApproved($mascaraId, self::ACTION_REINFORCED_APPROVAL, $userId)) {
            $count = $this->log->countDistinctApprovers($mascaraId, self::ACTION_REINFORCED_APPROVAL);
            return ['ok' => false, 'error' => 'Você já registrou sua aprovação.', 'count' => $count];
        }
        $this->log->record(self::ACTION_REINFORCED_APPROVAL, [
            'mascara_id' => $mascaraId,
            'aluno_id' => (int) $acc['aluno_id'],
            'user_id' => $userId,
        ]);
        $count = $this->log->countDistinctApprovers($mascaraId, self::ACTION_REINFORCED_APPROVAL);
        return ['ok' => true, 'error' => '', 'count' => $count];
    }

    public function setStatus(int $mascaraId, string $status, int $userId): void
    {
        if (!in_array($status, ['rascunho', 'suspensa', 'encerrada'], true)) {
            return;
        }
        $acc = $this->accommodations->getById($mascaraId);
        if (!$acc) {
            return;
        }
        $this->accommodations->setStatus($mascaraId, $status);
        $this->log->record('mascara_status_' . $status, [
            'mascara_id' => $mascaraId,
            'aluno_id' => (int) $acc['aluno_id'],
            'user_id' => $userId,
        ]);
    }

    public function isDocEncryptionAvailable(): bool
    {
        return $this->rawEncryptionKey() !== '';
    }

    /**
     * Faz upload do laudo criptografado (AES-256-GCM).
     *
     * @param array $file item de $_FILES
     * @return array{ok:bool, error:string}
     */
    public function uploadLaudo(int $mascaraId, array $file, int $userId): array
    {
        $acc = $this->accommodations->getById($mascaraId);
        if (!$acc) {
            return ['ok' => false, 'error' => 'Máscara não encontrada.'];
        }
        if (!$this->isDocEncryptionAvailable()) {
            return ['ok' => false, 'error' => 'Upload de laudo desabilitado: configure INCLUSAO_DOC_ENCRYPTION_KEY no .env.'];
        }
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Falha no envio do arquivo.'];
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_FILE_BYTES) {
            return ['ok' => false, 'error' => 'Arquivo vazio ou maior que 15MB.'];
        }

        $mime = $this->detectMime($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'Tipo de arquivo não permitido. Envie PDF, PNG ou JPG.'];
        }

        $plain = file_get_contents($file['tmp_name']);
        if ($plain === false) {
            return ['ok' => false, 'error' => 'Não foi possível ler o arquivo.'];
        }

        $hash = hash('sha256', $plain);
        $blob = $this->encrypt($plain);
        if ($blob === null) {
            return ['ok' => false, 'error' => 'Falha ao criptografar o laudo.'];
        }

        $dir = $this->storageDir($mascaraId);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Não foi possível preparar o armazenamento.'];
        }
        // Defesa em profundidade: bloqueia acesso direto via web (Apache).
        $htaccess = $this->storageBase() . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
        $filename = bin2hex(random_bytes(16)) . '.enc';
        $fullPath = $dir . '/' . $filename;
        if (file_put_contents($fullPath, $blob, LOCK_EX) === false) {
            return ['ok' => false, 'error' => 'Falha ao salvar o laudo.'];
        }
        @chmod($fullPath, 0600);

        $this->documents->create([
            'mascara_id' => $mascaraId,
            'caminho_arquivo' => $this->relativePath($fullPath),
            'hash_arquivo' => $hash,
            'tipo_mime' => $mime,
            'nome_original' => substr((string) ($file['name'] ?? 'laudo'), 0, 200),
            'algoritmo_cripto' => 'AES-256-GCM',
            'enviado_por' => $userId,
        ]);

        $this->log->record('laudo_upload', [
            'mascara_id' => $mascaraId,
            'aluno_id' => (int) $acc['aluno_id'],
            'user_id' => $userId,
            'details' => ['mime' => $mime, 'hash' => $hash],
        ]);

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Descriptografa um documento para download. Retorna conteúdo binário ou null.
     */
    public function decryptDocument(array $doc): ?string
    {
        $path = $this->absolutePath((string) ($doc['caminho_arquivo'] ?? ''));
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $blob = file_get_contents($path);
        if ($blob === false) {
            return null;
        }
        return $this->decrypt($blob);
    }

    // ---------------------------------------------------------------------

    /**
     * @return array<string,string>
     */
    private function buildRulesFromPost(array $post): array
    {
        $rules = [];
        $catalog = CatalogoRegraMascara::rules();
        $input = is_array($post['rules'] ?? null) ? $post['rules'] : [];

        foreach ($catalog as $key => $def) {
            $type = $def['input'] ?? 'toggle';
            $raw = $input[$key] ?? null;

            if ($type === 'toggle') {
                $on = in_array(strtolower((string) $raw), ['1', 'true', 'on', 'sim', 'yes'], true);
                if ($on) {
                    $rules[$key] = '1';
                }
                continue;
            }
            if ($type === 'number') {
                $val = (float) str_replace(',', '.', (string) $raw);
                if ($val > 0) {
                    $rules[$key] = (string) (int) round($val);
                }
                continue;
            }
            if ($type === 'select') {
                $options = $def['options'] ?? [];
                $defaults = [
                    (string) ($def['default'] ?? ''),
                    CatalogoRegraMascara::FONT_NORMAL,
                    CatalogoRegraMascara::FAMILY_PADRAO,
                    CatalogoRegraMascara::VISUAL_FONT_NORMAL,
                    CatalogoRegraMascara::VISUAL_CONTRAST_DEFAULT,
                    CatalogoRegraMascara::VISUAL_FAMILY_DEFAULT,
                    CatalogoRegraMascara::VISUAL_SPACING_NORMAL,
                    CatalogoRegraMascara::VISUAL_BUTTON_NORMAL,
                    CatalogoRegraMascara::VISUAL_READ_NORMAL,
                ];
                $defaults = array_values(array_unique(array_filter($defaults, static function ($v) {
                    return $v !== '';
                })));
                if (is_string($raw) && $raw !== '' && isset($options[$raw]) && !in_array($raw, $defaults, true)) {
                    $rules[$key] = $raw;
                }
                continue;
            }
            if ($type === 'text') {
                $val = trim((string) $raw);
                if ($val !== '') {
                    $rules[$key] = mb_substr($val, 0, 255);
                }
                continue;
            }
        }
        return $rules;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    private function detectMime(string $tmpPath): string
    {
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                return $mime;
            }
        }
        return (string) mime_content_type($tmpPath);
    }

    private function rawEncryptionKey(): string
    {
        $key = getenv('INCLUSAO_DOC_ENCRYPTION_KEY');
        if (($key === false || $key === '') && isset($_ENV['INCLUSAO_DOC_ENCRYPTION_KEY'])) {
            $key = $_ENV['INCLUSAO_DOC_ENCRYPTION_KEY'];
        }
        // Fallback: o app carrega o .env em $GLOBALS['env'] (helper env()),
        // sem popular getenv()/$_ENV. Sem isto a chave do .env nunca seria lida.
        if (($key === false || $key === '') && function_exists('env')) {
            $key = env('INCLUSAO_DOC_ENCRYPTION_KEY', '');
        }
        if (($key === false || $key === '') && isset($GLOBALS['env']['INCLUSAO_DOC_ENCRYPTION_KEY'])) {
            $key = $GLOBALS['env']['INCLUSAO_DOC_ENCRYPTION_KEY'];
        }
        return trim((string) $key);
    }

    private function encryptionKey(): string
    {
        // Normaliza para 32 bytes (AES-256).
        return hash('sha256', $this->rawEncryptionKey(), true);
    }

    private function encrypt(string $plain): ?string
    {
        if (!function_exists('openssl_encrypt')) {
            return null;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return null;
        }
        return $iv . $tag . $cipher;
    }

    private function decrypt(string $blob): ?string
    {
        if (!function_exists('openssl_decrypt') || strlen($blob) <= 28) {
            return null;
        }
        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $cipher = substr($blob, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    private function storageBase(): string
    {
        $slug = defined('TENANT_SLUG') && TENANT_SLUG !== '' ? (string) TENANT_SLUG : 'default';
        return __DIR__ . '/../../storage/inclusao/laudos/' . $slug;
    }

    private function storageDir(int $mascaraId): string
    {
        return $this->storageBase() . '/' . $mascaraId;
    }

    private function relativePath(string $absolute): string
    {
        $root = realpath(__DIR__ . '/../../storage');
        if ($root !== false && strpos($absolute, $root) === 0) {
            return 'storage' . substr($absolute, strlen($root));
        }
        return $absolute;
    }

    private function absolutePath(string $relative): string
    {
        if ($relative === '') {
            return '';
        }
        if ($relative[0] === '/' && is_file($relative)) {
            return $relative;
        }
        // relativo a partir de "storage/..."
        $base = __DIR__ . '/../../';
        return $base . ltrim($relative, '/');
    }
}
}
