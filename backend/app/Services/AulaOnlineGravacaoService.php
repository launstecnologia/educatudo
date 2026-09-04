<?php

require_once __DIR__ . '/../Models/Education/OnlineClass.php';
require_once __DIR__ . '/JaasMeetService.php';

if (!class_exists('AulaOnlineGravacaoService')) {
class AulaOnlineGravacaoService
{
    private OnlineClass $onlineClass;
    private JaasMeetService $jaasMeet;

    public function __construct(?OnlineClass $onlineClass = null, ?JaasMeetService $jaasMeet = null)
    {
        $this->onlineClass = $onlineClass ?? new OnlineClass();
        $this->jaasMeet = $jaasMeet ?? new JaasMeetService();
    }

    /**
     * Consulta a API pública de gravações do Jitsi e preenche link_gravacao
     * nas aulas da escola cuja sala coincidir.
     *
     * @return array{atualizadas:int,ja_tinham:int,nao_encontradas:int,total_api:int,erro:?string}
     */
    public function sincronizar(?int $aulaId = null): array
    {
        $resultado = [
            'atualizadas' => 0,
            'ja_tinham' => 0,
            'nao_encontradas' => 0,
            'total_api' => 0,
            'erro' => null,
        ];

        try {
            $gravacoes = $this->buscarMelhorPorSala();
        } catch (Throwable $e) {
            $resultado['erro'] = $e->getMessage();
            return $resultado;
        }

        $resultado['total_api'] = count($gravacoes);
        if ($gravacoes === []) {
            return $resultado;
        }

        if ($aulaId !== null && $aulaId > 0) {
            $aula = $this->onlineClass->getById($aulaId);
            $aulas = $aula ? [$aula] : [];
        } else {
            $aulas = $this->onlineClass->listarParaSincronizarGravacao();
        }

        $hostPermitido = $this->hostPermitido();

        foreach ($aulas as $aula) {
            $id = (int) ($aula['id'] ?? 0);
            if ($id <= 0 || !$this->ehJitsi($aula)) {
                continue;
            }

            $salaDoLink = $this->extrairSalaDoLink((string) ($aula['link_aula'] ?? ''));
            $sala = $salaDoLink !== ''
                ? $salaDoLink
                : $this->jaasMeet->nomeSala($id, (string) ($aula['titulo'] ?? ''));
            $gravacao = $sala !== '' ? ($gravacoes[$sala] ?? null) : null;

            if ($gravacao !== null && !$this->urlPermitida((string) ($gravacao['url'] ?? ''), $hostPermitido)) {
                $gravacao = null;
            }
            if ($gravacao !== null && $salaDoLink === '' && !$this->gravacaoNaJanelaDaAula($gravacao, $aula)) {
                $gravacao = null;
            }

            $urlAtual = trim((string) ($aula['link_gravacao'] ?? ''));
            if ($gravacao === null) {
                if ($urlAtual === '') {
                    $resultado['nao_encontradas']++;
                }
                continue;
            }

            if ($urlAtual !== '') {
                $resultado['ja_tinham']++;
                continue;
            }

            $this->onlineClass->updateJitsiRecording($id, (string) $gravacao['url']);
            $resultado['atualizadas']++;
        }

        return $resultado;
    }

    /**
     * @return array<string, array{sala:string,url:string,data:string,tamanho_mb:int}>
     */
    private function buscarMelhorPorSala(): array
    {
        $itens = $this->consultarApi();
        $hostPermitido = $this->hostPermitido();
        $melhor = [];

        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sala = trim((string) ($item['sala'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            $tamanho = (int) ($item['tamanho_mb'] ?? 0);
            $data = trim((string) ($item['data'] ?? ''));

            if ($sala === '' || $tamanho <= 0 || !$this->urlPermitida($url, $hostPermitido)) {
                continue;
            }

            $atual = $melhor[$sala] ?? null;
            if ($atual === null
                || $tamanho > (int) $atual['tamanho_mb']
                || ($tamanho === (int) $atual['tamanho_mb'] && $data > (string) $atual['data'])
            ) {
                $melhor[$sala] = [
                    'sala' => $sala,
                    'url' => $url,
                    'data' => $data,
                    'tamanho_mb' => $tamanho,
                ];
            }
        }

        return $melhor;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultarApi(): array
    {
        $url = $this->jaasMeet->urlApiGravacoes();
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Não foi possível iniciar a consulta às gravações.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $erro !== '') {
            throw new RuntimeException('Falha ao consultar a API de gravações.');
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('A API de gravações retornou HTTP ' . $code . '.');
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('A API de gravações retornou um JSON inválido.');
        }

        return array_values($decoded);
    }

    private function hostPermitido(): string
    {
        return strtolower((string) (parse_url($this->jaasMeet->urlApiGravacoes(), PHP_URL_HOST) ?? ''));
    }

    private function urlPermitida(string $url, string $hostPermitido): bool
    {
        if ($hostPermitido === '' || stripos($url, 'https://') !== 0) {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        return $host === $hostPermitido && strpos($path, '/gravacoes/') === 0;
    }

    private function gravacaoNaJanelaDaAula(array $gravacao, array $aula): bool
    {
        $dataGrav = strtotime((string) ($gravacao['data'] ?? ''));
        $inicio = strtotime((string) ($aula['inicio_em'] ?? ''));
        if ($dataGrav === false || $inicio === false) {
            return false;
        }

        $fim = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
        if ($fim === false) {
            $fim = $inicio;
        }

        $min = $inicio - (12 * 3600);
        $max = $fim + (36 * 3600);

        return $dataGrav >= $min && $dataGrav <= $max;
    }

    private function extrairSalaDoLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }

        $path = (string) (parse_url($link, PHP_URL_PATH) ?? '');
        $segmento = trim(basename($path), '/');
        if ($segmento === '' || strpos($segmento, 'educatudo-') !== 0) {
            return '';
        }

        return rawurldecode($segmento);
    }

    private function ehJitsi(array $aula): bool
    {
        return mb_strtolower((string) ($aula['plataforma'] ?? ''), 'UTF-8') === 'jitsi meet';
    }
}
}
