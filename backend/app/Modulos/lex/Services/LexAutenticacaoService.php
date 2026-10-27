<?php

namespace App\Modulos\Lex\Services;

class LexAutenticacaoService
{
    public function __construct(
        private LexClienteContrato $cliente,
        private LexCacheToken $cache,
        private string $diretorioTrava
    ) {
    }

    public static function urlBase(string $ambiente): string
    {
        return $ambiente === 'producao'
            ? 'https://api.lex.education'
            : 'https://homolog-api.lex.education';
    }

    /**
     * @param array{escola_id:int,ambiente:string,client_id:string,client_secret:string} $config
     * @return array{access_token:string,expires_in:int,token_type:string,reutilizado:bool}
     */
    public function obterToken(array $config): array
    {
        $chave = $this->chave((int) $config['escola_id']);
        $trava = $this->travar($chave);
        try {
            $emCache = $this->cache->ler($chave);
            if ($emCache !== null && $emCache['expira_em'] > time() + 60) {
                return [
                    'access_token' => $emCache['token'],
                    'expires_in' => $emCache['expira_em'] - time(),
                    'token_type' => 'Bearer',
                    'reutilizado' => true,
                ];
            }

            $novo = $this->cliente->solicitarToken(
                self::urlBase((string) $config['ambiente']),
                (string) $config['client_id'],
                (string) $config['client_secret']
            );
            $margem = 60;
            $ttl = (int) $novo['expires_in'] > $margem + 30
                ? (int) $novo['expires_in'] - $margem
                : max(30, (int) $novo['expires_in']);
            $this->cache->gravar($chave, $novo['access_token'], time() + $ttl);

            return [
                'access_token' => $novo['access_token'],
                'expires_in' => $ttl,
                'token_type' => $novo['token_type'],
                'reutilizado' => false,
            ];
        } finally {
            $this->liberar($trava);
        }
    }

    public function invalidar(int $escolaId): void
    {
        $this->cache->apagar($this->chave($escolaId));
    }

    private function chave(int $escolaId): string
    {
        return 'lex_jwt_' . $escolaId;
    }

    /**
     * @return resource|null
     */
    private function travar(string $chave)
    {
        if ($this->diretorioTrava === '' || !is_dir($this->diretorioTrava)) {
            if ($this->diretorioTrava !== '' && !@mkdir($this->diretorioTrava, 0755, true) && !is_dir($this->diretorioTrava)) {
                return null;
            }
        }
        $arquivo = rtrim($this->diretorioTrava, '/') . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $chave) . '.lock';
        $fp = @fopen($arquivo, 'c');
        if ($fp === false) {
            return null;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return null;
        }
        return $fp;
    }

    /**
     * @param resource|null $fp
     */
    private function liberar($fp): void
    {
        if (!is_resource($fp)) {
            return;
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
