<?php

if (!class_exists('PandaVideoLiveService')) {
class PandaVideoLiveService
{
    private string $baseUrl;
    private string $apiKey;
    private string $createEndpoint;
    private string $authHeader;
    private string $authPrefix;
    private string $streamKeyIdFixed;

    public function __construct()
    {
        $this->baseUrl = rtrim($this->resolveValue('panda_video_api_url', 'PANDA_VIDEO_API_URL', 'https://api-v2.pandavideo.com.br'), '/');
        $this->apiKey = trim($this->resolveValue('panda_video_api_key', 'PANDA_VIDEO_API_KEY', ''));
        $this->createEndpoint = '/' . ltrim($this->resolveValue('panda_video_live_create_endpoint', 'PANDA_VIDEO_LIVE_CREATE_ENDPOINT', '/live'), '/');
        $this->authHeader = trim($this->resolveValue('panda_video_auth_header', 'PANDA_VIDEO_AUTH_HEADER', 'Authorization'));
        $this->authPrefix = $this->resolveValue('panda_video_auth_prefix', 'PANDA_VIDEO_AUTH_PREFIX', '');
        $this->streamKeyIdFixed = trim($this->resolveValue('panda_video_stream_key_id', 'PANDA_VIDEO_STREAM_KEY_ID', ''));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '' && $this->authHeader !== '';
    }

    public function createLive(array $event): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('Integração Panda Video não configurada (.env).');
        }

        $streamKeyId = $this->resolveStreamKeyId();
        if ($streamKeyId === '') {
            throw new Exception('Nenhuma stream key disponível na conta Panda Video.');
        }

        $basePayload = [
            'title' => (string) ($event['titulo'] ?? 'Aula Online'),
            'scheduled_at' => $this->toPandaUtc((string) ($event['inicio_em_iso'] ?? '')),
        ];

        $variants = [
            $basePayload + ['stream_key_id' => $streamKeyId],
            $basePayload + ['stream_key' => $streamKeyId],
        ];

        $lastError = null;
        $raw = '';
        foreach ($variants as $payload) {
            try {
                $raw = $this->request('POST', $this->normalizeCreateEndpoint($this->createEndpoint), $payload);
                $lastError = null;
                break;
            } catch (Exception $e) {
                $lastError = $e;
            }
        }
        if ($lastError instanceof Exception) {
            throw $lastError;
        }

        $response = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($response)) {
            throw new Exception('Resposta inválida da API Panda Video.');
        }

        $link = $this->extractPlaybackUrl($response);
        $liveId = $this->extractId($response);
        if ($link === '' && $liveId !== '') {
            $details = $this->fetchLiveDetails($liveId);
            if (is_array($details)) {
                $link = $this->extractPlaybackUrl($details);
                if ($link === '') {
                    // Alguns retornos encapsulam os dados novamente.
                    $link = $this->extractPlaybackUrl(['data' => $details]);
                }
                if ($link !== '') {
                    $response['live_details'] = $details;
                }
            }
        }
        if ($link === '') {
            throw new Exception('Panda Video criou a live, mas não retornou URL de acesso.');
        }

        return [
            'url' => $link,
            'id_externo' => $liveId,
            'raw' => $response,
        ];
    }

    public function fetchLiveRecording(string $liveId): array
    {
        $liveId = trim($liveId);
        if ($liveId === '') {
            return [];
        }

        $details = $this->fetchLiveDetails($liveId);
        if (!is_array($details)) {
            return [];
        }

        $recording = $this->extractRecordingData($details);
        $videoId = (string) ($recording['video_id'] ?? '');
        if ($videoId !== '') {
            $video = $this->fetchVideoDetails($videoId);
            if (is_array($video)) {
                $recording['player_url'] = (string) ($recording['player_url'] ?? '');
                $recording['hls_url'] = (string) ($recording['hls_url'] ?? '');
                if ($recording['player_url'] === '') {
                    $recording['player_url'] = $this->extractPlaybackUrl($video);
                }
                if ($recording['hls_url'] === '') {
                    $recording['hls_url'] = $this->extractHlsUrl($video);
                }
                $recording['raw_video'] = $video;
            }
        }

        if (
            (string) ($recording['video_id'] ?? '') === '' &&
            (string) ($recording['player_url'] ?? '') === '' &&
            (string) ($recording['hls_url'] ?? '') === ''
        ) {
            return [];
        }

        $recording['raw_live'] = $details;
        return $recording;
    }

    private function request(string $method, string $endpoint, array $body): string
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Falha ao iniciar conexão com Panda Video.');
        }

        $authValue = $this->authPrefix !== ''
            ? rtrim($this->authPrefix) . ' ' . $this->apiKey
            : $this->apiKey;

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            $this->authHeader . ': ' . $authValue,
        ];

        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{}';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new Exception('Erro de conexão com Panda Video: ' . $err);
        }

        if (!is_string($resp) || $resp === '') {
            throw new Exception('Resposta vazia da API Panda Video (HTTP ' . $code . ').');
        }

        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($resp, true);
            $msg = is_array($decoded)
                ? (string) ($decoded['error'] ?? $decoded['message'] ?? 'Erro na API Panda Video')
                : 'Erro na API Panda Video';
            $details = '';
            if (is_array($decoded)) {
                $details = ' | resposta=' . json_encode($decoded, JSON_UNESCAPED_UNICODE);
            } else {
                $details = ' | resposta=' . substr($resp, 0, 600);
            }
            throw new Exception($msg . ' (HTTP ' . $code . ')' . $details);
        }

        return $resp;
    }

    private function requestNoBody(string $method, string $endpoint): string
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Falha ao iniciar conexão com Panda Video.');
        }

        $authValue = $this->authPrefix !== ''
            ? rtrim($this->authPrefix) . ' ' . $this->apiKey
            : $this->apiKey;

        $headers = [
            'Accept: application/json',
            $this->authHeader . ': ' . $authValue,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new Exception('Erro de conexão com Panda Video: ' . $err);
        }
        if (!is_string($resp) || $resp === '') {
            throw new Exception('Resposta vazia da API Panda Video (HTTP ' . $code . ').');
        }
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($resp, true);
            $msg = is_array($decoded)
                ? (string) ($decoded['error'] ?? $decoded['message'] ?? 'Erro na API Panda Video')
                : 'Erro na API Panda Video';
            throw new Exception($msg . ' (HTTP ' . $code . ')');
        }
        return $resp;
    }

    private function extractPlaybackUrl(array $data): string
    {
        $keys = [
            'live_player', 'live_hls',
            'player_url', 'playerUrl',
            'playback_url', 'playbackUrl',
            'url', 'live_url', 'liveUrl',
            'hls_url', 'hlsUrl',
            'embed_url', 'embedUrl',
            'stream_url', 'streamUrl',
            'share_url', 'shareUrl',
            'watch_url', 'watchUrl',
        ];
        foreach ($keys as $k) {
            if (!empty($data[$k]) && filter_var($data[$k], FILTER_VALIDATE_URL)) {
                return (string) $data[$k];
            }
        }

        // Formatos aninhados comuns: playback.url, player.url, links.player, urls.embed, etc.
        $nestedCandidates = [
            ['playback', 'url'],
            ['player', 'url'],
            ['stream', 'url'],
            ['links', 'player'],
            ['links', 'playback'],
            ['urls', 'player'],
            ['urls', 'playback'],
            ['urls', 'embed'],
        ];
        foreach ($nestedCandidates as $path) {
            $v = $this->getNestedValue($data, $path);
            if (is_string($v) && filter_var($v, FILTER_VALIDATE_URL)) {
                return $v;
            }
        }

        $nestedKeys = ['data', 'live', 'result'];
        foreach ($nestedKeys as $nk) {
            if (isset($data[$nk]) && is_array($data[$nk])) {
                $nested = $this->extractPlaybackUrl($data[$nk]);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    private function extractHlsUrl(array $data): string
    {
        $keys = ['live_hls', 'hls_url', 'hlsUrl', 'hls', 'video_hls', 'videoHls'];
        foreach ($keys as $k) {
            if (!empty($data[$k]) && filter_var($data[$k], FILTER_VALIDATE_URL)) {
                return (string) $data[$k];
            }
        }

        foreach (['data', 'live', 'result', 'video', 'vod', 'recording'] as $nk) {
            if (isset($data[$nk]) && is_array($data[$nk])) {
                $nested = $this->extractHlsUrl($data[$nk]);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    private function extractRecordingData(array $data): array
    {
        $recording = [
            'video_id' => '',
            'player_url' => '',
            'hls_url' => '',
        ];

        $containers = [];
        foreach (['vod', 'video', 'recording', 'recorded_video', 'generated_vod', 'record'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $containers[] = $data[$key];
            }
        }
        foreach (['data', 'live', 'result'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $nested = $this->extractRecordingData($data[$key]);
                if (!empty($nested['video_id']) || !empty($nested['player_url']) || !empty($nested['hls_url'])) {
                    return $nested;
                }
            }
        }

        foreach (['vod_id', 'video_id', 'recording_video_id', 'recorded_video_id', 'generated_vod_id'] as $key) {
            if (!empty($data[$key]) && !is_array($data[$key])) {
                $recording['video_id'] = (string) $data[$key];
                break;
            }
        }

        foreach ($containers as $container) {
            foreach (['vod_id', 'video_id', 'recording_video_id', 'recorded_video_id', 'generated_vod_id', 'id'] as $key) {
                if (!empty($container[$key]) && !is_array($container[$key])) {
                    $recording['video_id'] = (string) $container[$key];
                    break 2;
                }
            }
        }

        foreach (['vod_player', 'vod_url', 'recording_player', 'recording_url', 'recorded_video_url', 'generated_vod_url'] as $key) {
            if (!empty($data[$key]) && filter_var($data[$key], FILTER_VALIDATE_URL)) {
                $recording['player_url'] = (string) $data[$key];
                break;
            }
        }

        foreach ($containers as $container) {
            $player = $this->extractPlaybackUrl($container);
            if ($player !== '') {
                $recording['player_url'] = $player;
                break;
            }
        }

        foreach (['vod_hls', 'recording_hls', 'recorded_video_hls', 'generated_vod_hls'] as $key) {
            if (!empty($data[$key]) && filter_var($data[$key], FILTER_VALIDATE_URL)) {
                $recording['hls_url'] = (string) $data[$key];
                break;
            }
        }

        foreach ($containers as $container) {
            $hls = $this->extractHlsUrl($container);
            if ($hls !== '') {
                $recording['hls_url'] = $hls;
                break;
            }
        }

        return $recording;
    }

    private function extractId(array $data): string
    {
        $keys = ['id', 'live_id', 'uuid'];
        foreach ($keys as $k) {
            if (!empty($data[$k])) {
                return (string) $data[$k];
            }
        }

        foreach (['data', 'live', 'result'] as $nk) {
            if (isset($data[$nk]) && is_array($data[$nk])) {
                $id = $this->extractId($data[$nk]);
                if ($id !== '') {
                    return $id;
                }
            }
        }

        return '';
    }

    private function resolveValue(string $devKey, string $envKey, string $default): string
    {
        $devValue = $this->resolveFromDevSettings($devKey);
        if ($devValue !== '') {
            return $devValue;
        }

        $envValue = trim((string) (function_exists('env') ? env($envKey, '') : ''));
        if ($envValue !== '') {
            return $envValue;
        }

        return $default;
    }

    private function resolveFromDevSettings(string $key): string
    {
        try {
            if (!class_exists('DevSetting')) {
                require_once __DIR__ . '/../Models/System/DevSetting.php';
            }
            $model = new DevSetting();
            return trim((string) ($model->get($key) ?? ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    private function normalizeCreateEndpoint(string $endpoint): string
    {
        $ep = '/' . ltrim(trim($endpoint), '/');
        if ($ep === '/live') {
            return '/lives/';
        }
        return $ep;
    }

    private function resolveStreamKeyId(): string
    {
        if ($this->streamKeyIdFixed !== '') {
            return $this->streamKeyIdFixed;
        }

        $raw = $this->requestNoBody('GET', '/live_stream_key/');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }

        $list = [];
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $list = $decoded['data'];
        } elseif (isset($decoded[0])) {
            $list = $decoded;
        }

        foreach ($list as $item) {
            if (is_string($item) && $item !== '') {
                return $item;
            }
            if (!is_array($item)) {
                continue;
            }
            if (!empty($item['id'])) {
                return (string) $item['id'];
            }
            if (!empty($item['stream_key_id'])) {
                return (string) $item['stream_key_id'];
            }
            if (!empty($item['uuid'])) {
                return (string) $item['uuid'];
            }
            if (!empty($item['live_stream_key'])) {
                return (string) $item['live_stream_key'];
            }
            if (!empty($item['stream_key'])) {
                return (string) $item['stream_key'];
            }
            if (!empty($item['key'])) {
                return (string) $item['key'];
            }
        }
        return '';
    }

    private function toPandaUtc(string $iso): string
    {
        $ts = strtotime($iso);
        if ($ts === false) {
            return gmdate('Y-m-d\\TH:i:s');
        }
        return gmdate('Y-m-d\\TH:i:s', $ts);
    }

    private function fetchLiveDetails(string $liveId): ?array
    {
        if ($liveId === '') {
            return null;
        }
        try {
            $raw = $this->requestNoBody('GET', '/lives/' . rawurlencode($liveId));
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function fetchVideoDetails(string $videoId): ?array
    {
        if ($videoId === '') {
            return null;
        }
        try {
            $raw = $this->requestNoBody('GET', '/videos/' . rawurlencode($videoId));
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string> $path
     * @return mixed
     */
    private function getNestedValue(array $data, array $path)
    {
        $cur = $data;
        foreach ($path as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return null;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }
}
}
