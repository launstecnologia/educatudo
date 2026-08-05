<?php
/**
 * Domínio de escola (tenant) — geração, validação e verificação HTTPS/SSL.
 *
 * DNS wildcard na Cloudflare é configurado uma vez na infra; este service
 * infere status e verifica HTTPS a partir do Master.
 */
class DominioEscolaService
{
    public const DNS_NAO_CONFIGURADO = 'nao_configurado';
    public const DNS_WILDCARD_OK = 'wildcard_ok';
    public const DNS_FORA_PADRAO = 'fora_padrao';
    public const DNS_ERRO = 'erro';

    public const SSL_NAO_VERIFICADO = 'nao_verificado';
    public const SSL_OK = 'ok';
    public const SSL_PENDENTE = 'pendente';
    public const SSL_ERRO = 'erro';

    public function getTenantBaseDomain(): string
    {
        $base = function_exists('env') ? trim((string) env('TENANT_BASE_DOMAIN', '')) : '';
        if ($base !== '') {
            return strtolower($base);
        }
        return 'localhost';
    }

    public function isWildcardHabilitado(): bool
    {
        if (!function_exists('env')) {
            return false;
        }
        $raw = strtolower(trim((string) env('DOMINIO_WILDCARD_HABILITADO', '')));
        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    public function getMasterDomain(): string
    {
        return function_exists('env') ? strtolower(trim((string) env('MASTER_DOMAIN', ''))) : '';
    }

    public function getSslCertPath(): string
    {
        return function_exists('env') ? trim((string) env('SSL_CERT_PATH', '')) : '';
    }

    /**
     * @return array{tenant_base_domain:string,master_domain:string,wildcard_habilitado:bool}
     */
    public function configuracaoParaView(): array
    {
        return [
            'tenant_base_domain' => $this->getTenantBaseDomain(),
            'master_domain' => $this->getMasterDomain(),
            'wildcard_habilitado' => $this->isWildcardHabilitado(),
        ];
    }

    public function gerarDominioPadrao(string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($slug))) ?? '';
        if ($slug === '') {
            return '';
        }
        $base = $this->getTenantBaseDomain();
        if ($base === 'localhost' || $base === '') {
            return $slug . '.localhost';
        }
        return $slug . '.' . $base;
    }

    public function dominioValido(string $dominio): bool
    {
        $dominio = strtolower(trim($dominio));
        if ($dominio === '' || strlen($dominio) > 253) {
            return false;
        }
        if (strpos($dominio, '://') !== false || strpos($dominio, '/') !== false || strpos($dominio, ' ') !== false) {
            return false;
        }
        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $dominio);
    }

    public function dominioEhMaster(string $dominio): bool
    {
        $master = $this->getMasterDomain();
        if ($master === '') {
            return false;
        }
        return strtolower(trim($dominio)) === $master;
    }

    /**
     * Subdomínios reservados na infra (master, www, api…).
     */
    public function subdominioReservado(string $dominio): bool
    {
        $dominio = strtolower(trim($dominio));
        if ($dominio === '') {
            return false;
        }
        $parts = explode('.', $dominio);
        $label = $parts[0] ?? '';
        $reservados = ['master', 'www', 'api', 'mail', 'smtp', 'ftp', 'cdn', 'static'];
        if (in_array($label, $reservados, true)) {
            return true;
        }
        $base = $this->getTenantBaseDomain();
        if ($base !== 'localhost' && $base !== '' && $dominio === 'master.' . $base) {
            return true;
        }
        return $this->dominioEhMaster($dominio);
    }

    public function dominioDisponivel($db, string $dominio, int $excludeEscolaId = 0): bool
    {
        $dominio = strtolower(trim($dominio));
        if ($dominio === '') {
            return false;
        }
        $params = ['dominio' => $dominio];
        $sql = 'SELECT id FROM escolas WHERE dominio = :dominio';
        if ($excludeEscolaId > 0) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeEscolaId;
        }
        $sql .= ' LIMIT 1';
        $row = $db->fetch($sql, $params);
        return !$row;
    }

    public function dominioCobertoPorWildcard(string $dominio): bool
    {
        $dominio = strtolower(trim($dominio));
        if ($dominio === '' || $this->dominioEhMaster($dominio)) {
            return false;
        }
        $base = $this->getTenantBaseDomain();
        if ($base === 'localhost' || $base === '') {
            return (bool) preg_match('/^[a-z0-9\-]+\.localhost$/', $dominio);
        }
        if (!$this->isWildcardHabilitado()) {
            return false;
        }
        $suffix = '.' . $base;
        if (!str_ends_with($dominio, $suffix)) {
            return false;
        }
        $sub = substr($dominio, 0, -strlen($suffix));
        return $sub !== '' && $sub !== 'master' && preg_match('/^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?$/', $sub);
    }

    public function inferirDnsStatus(string $dominio): string
    {
        $dominio = strtolower(trim($dominio));
        if ($dominio === '') {
            return self::DNS_NAO_CONFIGURADO;
        }
        if ($this->dominioCobertoPorWildcard($dominio)) {
            return self::DNS_WILDCARD_OK;
        }
        $base = $this->getTenantBaseDomain();
        if ($base !== 'localhost' && $base !== '' && str_ends_with($dominio, '.' . $base)) {
            return self::DNS_FORA_PADRAO;
        }
        if (str_ends_with($dominio, '.localhost')) {
            return self::DNS_WILDCARD_OK;
        }
        return self::DNS_FORA_PADRAO;
    }

    /**
     * @return array{ok:bool,ssl_status:string,erro:?string,expira_em:?string,codigo_http:?int}
     */
    public function verificarHttps(string $dominio): array
    {
        $dominio = strtolower(trim($dominio));
        if (!$this->dominioValido($dominio)) {
            return [
                'ok' => false,
                'ssl_status' => self::SSL_ERRO,
                'erro' => 'Domínio inválido.',
                'expira_em' => null,
                'codigo_http' => null,
            ];
        }

        $url = 'https://' . $dominio . '/';
        $codigoHttp = null;
        $erro = null;
        $ok = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'EducaTudo-Master-DominioCheck/1.0',
            ]);
            curl_exec($ch);
            $codigoHttp = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (curl_errno($ch)) {
                $erro = curl_error($ch);
            } elseif ($codigoHttp >= 200 && $codigoHttp < 500) {
                $ok = true;
            } else {
                $erro = 'HTTP ' . $codigoHttp;
            }
            curl_close($ch);
        } else {
            $ctx = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 8,
                    'ignore_errors' => true,
                    'header' => "User-Agent: EducaTudo-Master-DominioCheck/1.0\r\n",
                ],
            ]);
            $headers = @get_headers($url, true, $ctx);
            if ($headers === false) {
                $erro = 'Não foi possível conectar via HTTPS.';
            } else {
                $statusLine = is_array($headers[0] ?? null) ? ($headers[0][0] ?? '') : (string) ($headers[0] ?? '');
                if (preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
                    $codigoHttp = (int) $m[1];
                    $ok = $codigoHttp >= 200 && $codigoHttp < 500;
                    if (!$ok) {
                        $erro = 'HTTP ' . $codigoHttp;
                    }
                } else {
                    $erro = 'Resposta HTTP inválida.';
                }
            }
        }

        $expiraEm = $this->lerExpiracaoCertificado();
        if ($expiraEm === null && $ok) {
            $expiraEm = $this->lerExpiracaoCertificadoRemoto($dominio);
        }

        return [
            'ok' => $ok,
            'ssl_status' => $ok ? self::SSL_OK : self::SSL_ERRO,
            'erro' => $ok ? null : ($erro ?: 'Falha na verificação HTTPS.'),
            'expira_em' => $expiraEm,
            'codigo_http' => $codigoHttp,
        ];
    }

    public function lerExpiracaoCertificado(): ?string
    {
        $path = $this->getSslCertPath();
        if ($path === '' || !is_readable($path)) {
            return null;
        }
        $pem = @file_get_contents($path);
        if ($pem === false || $pem === '') {
            return null;
        }
        $parsed = @openssl_x509_parse($pem);
        if (!is_array($parsed) || empty($parsed['validTo_time_t'])) {
            return null;
        }
        return date('Y-m-d H:i:s', (int) $parsed['validTo_time_t']);
    }

    private function lerExpiracaoCertificadoRemoto(string $dominio): ?string
    {
        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $dominio,
            ],
        ]);
        $client = @stream_socket_client(
            'ssl://' . $dominio . ':443',
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if ($client === false) {
            return null;
        }
        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return null;
        }
        $parsed = @openssl_x509_parse($cert);
        if (!is_array($parsed) || empty($parsed['validTo_time_t'])) {
            return null;
        }
        return date('Y-m-d H:i:s', (int) $parsed['validTo_time_t']);
    }

    /**
     * @return array{success:bool,error?:string,dns_status?:string,ssl_status?:string}
     */
    public function verificarEscola($db, int $escolaId): array
    {
        if ($escolaId <= 0) {
            return ['success' => false, 'error' => 'Escola inválida.'];
        }

        $row = $db->fetch(
            'SELECT id, dominio, slug FROM escolas WHERE id = :id LIMIT 1',
            ['id' => $escolaId]
        );
        if (!$row) {
            return ['success' => false, 'error' => 'Escola não encontrada.'];
        }

        $dominio = trim((string) ($row['dominio'] ?? ''));
        if ($dominio === '') {
            $dominio = $this->gerarDominioPadrao((string) ($row['slug'] ?? ''));
        }
        if ($dominio === '' || !$this->dominioValido($dominio)) {
            return ['success' => false, 'error' => 'Domínio não configurado ou inválido.'];
        }

        $dnsStatus = $this->inferirDnsStatus($dominio);
        $https = $this->verificarHttps($dominio);
        $sslStatus = $https['ok'] ? self::SSL_OK : self::SSL_ERRO;
        if (!$https['ok'] && $dnsStatus === self::DNS_WILDCARD_OK) {
            $sslStatus = self::SSL_PENDENTE;
        }

        $db->query(
            'UPDATE escolas SET
                dns_status = :dns_status,
                ssl_status = :ssl_status,
                ssl_verificado_em = NOW(),
                ssl_expira_em = :ssl_expira_em,
                dominio_ultimo_erro = :erro
             WHERE id = :id',
            [
                'dns_status' => $dnsStatus,
                'ssl_status' => $sslStatus,
                'ssl_expira_em' => $https['expira_em'],
                'erro' => $https['erro'],
                'id' => $escolaId,
            ]
        );

        return [
            'success' => true,
            'dns_status' => $dnsStatus,
            'ssl_status' => $sslStatus,
            'dominio' => $dominio,
            'erro' => $https['erro'],
            'expira_em' => $https['expira_em'],
            'codigo_http' => $https['codigo_http'],
        ];
    }

    /**
     * @return array{processadas:int,ok:int,erros:int}
     */
    public function verificarEscolasPendentes($db, int $limite = 50): array
    {
        $limite = max(1, min(200, $limite));
        $rows = $db->fetchAll(
            "SELECT id FROM escolas
             WHERE ativo = 1
               AND dominio IS NOT NULL
               AND dominio != ''
               AND (
                    ssl_status IS NULL
                    OR ssl_status IN ('nao_verificado', 'pendente', 'erro')
                    OR ssl_expira_em IS NULL
                    OR ssl_expira_em <= DATE_ADD(NOW(), INTERVAL 14 DAY)
               )
             ORDER BY COALESCE(ssl_verificado_em, '1970-01-01') ASC
             LIMIT {$limite}"
        ) ?: [];

        $processadas = 0;
        $ok = 0;
        $erros = 0;
        foreach ($rows as $row) {
            $processadas++;
            $res = $this->verificarEscola($db, (int) $row['id']);
            if (!empty($res['success']) && ($res['ssl_status'] ?? '') === self::SSL_OK) {
                $ok++;
            } else {
                $erros++;
            }
        }

        return ['processadas' => $processadas, 'ok' => $ok, 'erros' => $erros];
    }

    /**
     * @return array{dominio:string,dns_status:string,erro?:string}
     */
    public function normalizarDominioParaSalvar(string $slug, string $dominioInput): array
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($slug))) ?? '';
        $dominio = strtolower(trim($dominioInput));
        if ($dominio === '') {
            $dominio = $this->gerarDominioPadrao($slug);
        }
        if ($dominio === '') {
            return ['dominio' => '', 'dns_status' => self::DNS_NAO_CONFIGURADO, 'erro' => 'Informe o slug para gerar o domínio.'];
        }
        if (!$this->dominioValido($dominio)) {
            return ['dominio' => $dominio, 'dns_status' => self::DNS_ERRO, 'erro' => 'Domínio inválido. Use apenas letras, números, hífen e pontos (ex.: escola.educatudo.com).'];
        }
        if ($this->dominioEhMaster($dominio)) {
            return ['dominio' => $dominio, 'dns_status' => self::DNS_ERRO, 'erro' => 'O domínio não pode ser igual ao MASTER_DOMAIN.'];
        }
        if ($this->subdominioReservado($dominio)) {
            return ['dominio' => $dominio, 'dns_status' => self::DNS_ERRO, 'erro' => 'Este subdomínio é reservado (ex.: master, www, api). Escolha outro slug ou domínio.'];
        }
        return [
            'dominio' => $dominio,
            'dns_status' => $this->inferirDnsStatus($dominio),
        ];
    }

    public static function rotuloDnsStatus(?string $status): string
    {
        return match ($status) {
            self::DNS_WILDCARD_OK => 'DNS OK (wildcard)',
            self::DNS_FORA_PADRAO => 'Fora do padrão wildcard',
            self::DNS_ERRO => 'Erro',
            self::DNS_NAO_CONFIGURADO => 'Não configurado',
            default => 'Desconhecido',
        };
    }

    public static function rotuloSslStatus(?string $status): string
    {
        return match ($status) {
            self::SSL_OK => 'HTTPS OK',
            self::SSL_PENDENTE => 'Pendente (aguardando certificado)',
            self::SSL_ERRO => 'Erro HTTPS',
            self::SSL_NAO_VERIFICADO => 'Não verificado',
            default => 'Desconhecido',
        };
    }
}
