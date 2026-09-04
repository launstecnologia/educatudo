<?php

if (!class_exists('OnlineClass')) {
class OnlineClass
{
    private $db;
    private static $columnCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listTurmas(): array
    {
        return $this->db->fetchAll("SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome");
    }

    public function listForAdmin(int $limit = 300): array
    {
        $limit = max(1, min(1000, $limit));
        $rows = $this->db->fetchAll(
            "SELECT ao.*, u.nome AS criado_por_nome
             FROM aulas_online ao
             LEFT JOIN usuarios u ON u.id = ao.criado_por
             WHERE ao.ativo = 1
             ORDER BY ao.inicio_em DESC
             LIMIT " . (int) $limit
        );

        if ($rows === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(static function ($r) {
            return (int) ($r['id'] ?? 0);
        }, $rows)));

        $mapTurmas = $this->mapTurmasByAulaIds($ids);
        foreach ($rows as &$r) {
            $rid = (int) ($r['id'] ?? 0);
            $r['turmas'] = $mapTurmas[$rid] ?? [];
        }

        return $rows;
    }

    /**
     * Aulas ao vivo agora ou que ainda vão começar (não encerradas).
     * Usado no dashboard do admin para entrar rapidamente na sala.
     */
    public function listLiveAndUpcomingForAdmin(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return $this->db->fetchAll(
            "SELECT id, titulo, plataforma, link_aula, inicio_em, fim_em
             FROM aulas_online
             WHERE ativo = 1
               AND (fim_em IS NULL OR fim_em >= NOW())
             ORDER BY inicio_em ASC
             LIMIT " . (int) $limit
        );
    }

    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->fetch(
            "SELECT ao.*, u.nome AS criado_por_nome
             FROM aulas_online ao
             LEFT JOIN usuarios u ON u.id = ao.criado_por
             WHERE ao.id = :id AND ao.ativo = 1",
            ['id' => $id]
        );

        if (!$row) {
            return null;
        }

        $row['turmas'] = $this->mapTurmasByAulaIds([$id])[$id] ?? [];
        return $row;
    }

    public function create(array $data, int $createdBy): int
    {
        $gerarPanda = (int) ($data['gerar_panda'] ?? 0);
        $statusIntegracao = $gerarPanda === 1 ? 'pendente' : 'nao_solicitada';

        if ($this->hasColumn('aulas_online', 'gerar_panda') && $this->hasColumn('aulas_online', 'panda_integracao_status')) {
            $id = (int) $this->db->insert(
                "INSERT INTO aulas_online
                (titulo, descricao, plataforma, link_aula, inicio_em, fim_em, enviar_para_todos, publicado, gerar_panda, panda_integracao_status, ativo, criado_por)
                 VALUES
                (:titulo, :descricao, :plataforma, :link_aula, :inicio_em, :fim_em, :enviar_para_todos, :publicado, :gerar_panda, :panda_integracao_status, 1, :criado_por)",
                [
                    'titulo' => $data['titulo'],
                    'descricao' => $data['descricao'],
                    'plataforma' => $data['plataforma'],
                    'link_aula' => $data['link_aula'],
                    'inicio_em' => $data['inicio_em'],
                    'fim_em' => $data['fim_em'],
                    'enviar_para_todos' => $data['enviar_para_todos'],
                    'publicado' => $data['publicado'],
                    'gerar_panda' => $gerarPanda,
                    'panda_integracao_status' => $statusIntegracao,
                    'criado_por' => $createdBy,
                ]
            );
        } else {
            $id = (int) $this->db->insert(
                "INSERT INTO aulas_online
                (titulo, descricao, plataforma, link_aula, inicio_em, fim_em, enviar_para_todos, publicado, ativo, criado_por)
                 VALUES
                (:titulo, :descricao, :plataforma, :link_aula, :inicio_em, :fim_em, :enviar_para_todos, :publicado, 1, :criado_por)",
                [
                    'titulo' => $data['titulo'],
                    'descricao' => $data['descricao'],
                    'plataforma' => $data['plataforma'],
                    'link_aula' => $data['link_aula'],
                    'inicio_em' => $data['inicio_em'],
                    'fim_em' => $data['fim_em'],
                    'enviar_para_todos' => $data['enviar_para_todos'],
                    'publicado' => $data['publicado'],
                    'criado_por' => $createdBy,
                ]
            );
        }

        $this->syncTurmas($id, $data['turmas_ids'] ?? [], (int) ($data['enviar_para_todos'] ?? 0));
        return $id;
    }

    public function createWeeklyRecurrences(int $baseId, array $baseData, int $createdBy, int $weeks): array
    {
        $createdIds = [];
        if ($baseId <= 0 || $weeks <= 1) {
            return $createdIds;
        }

        $inicioBaseTs = strtotime((string) ($baseData['inicio_em'] ?? ''));
        if ($inicioBaseTs === false) {
            return $createdIds;
        }

        $fimBaseTs = !empty($baseData['fim_em']) ? strtotime((string) $baseData['fim_em']) : false;
        $hasFim = $fimBaseTs !== false;

        for ($i = 1; $i < $weeks; $i++) {
            $copy = $baseData;
            $copy['inicio_em'] = date('Y-m-d H:i:s', strtotime('+' . (7 * $i) . ' days', $inicioBaseTs));
            $copy['fim_em'] = $hasFim ? date('Y-m-d H:i:s', strtotime('+' . (7 * $i) . ' days', (int) $fimBaseTs)) : null;
            $copy['descricao'] = (string) ($baseData['descricao'] ?? '');
            if (strpos($copy['descricao'], '[Recorrência #') === false) {
                $copy['descricao'] = trim($copy['descricao']);
                $copy['descricao'] .= ($copy['descricao'] !== '' ? "\n\n" : '') . '[Recorrência #' . ($i + 1) . ']';
            }
            $createdIds[] = $this->create($copy, $createdBy);
        }

        return $createdIds;
    }

    public function update(int $id, array $data): void
    {
        $hasGravacao = $this->hasColumn('aulas_online', 'link_gravacao');
        $gravacaoSql = $hasGravacao ? ', link_gravacao = :link_gravacao' : '';

        if ($this->hasColumn('aulas_online', 'gerar_panda') && $this->hasColumn('aulas_online', 'panda_integracao_status')) {
            $params = [
                'id' => $id,
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'],
                'plataforma' => $data['plataforma'],
                'link_aula' => $data['link_aula'],
                'inicio_em' => $data['inicio_em'],
                'fim_em' => $data['fim_em'],
                'enviar_para_todos' => $data['enviar_para_todos'],
                'publicado' => $data['publicado'],
                'gerar_panda' => (int) ($data['gerar_panda'] ?? 0),
                'gerar_panda_status_off' => (int) ($data['gerar_panda'] ?? 0),
                'gerar_panda_status_on' => (int) ($data['gerar_panda'] ?? 0),
                'link_aula_status' => $data['link_aula'],
            ];
            if ($hasGravacao) {
                $params['link_gravacao'] = $data['link_gravacao'] ?? null;
            }
            $this->db->query(
                "UPDATE aulas_online SET
                    titulo = :titulo,
                    descricao = :descricao,
                    plataforma = :plataforma,
                    link_aula = :link_aula,
                    inicio_em = :inicio_em,
                    fim_em = :fim_em,
                    enviar_para_todos = :enviar_para_todos,
                    publicado = :publicado,
                    gerar_panda = :gerar_panda,
                    panda_integracao_status = CASE
                        WHEN :gerar_panda_status_off = 0 THEN 'nao_solicitada'
                        WHEN :gerar_panda_status_on = 1 AND (IFNULL(:link_aula_status, '') = '' OR panda_integracao_status IN ('erro', 'pendente')) THEN 'pendente'
                        ELSE panda_integracao_status
                    END
                    {$gravacaoSql},
                    updated_at = NOW()
                 WHERE id = :id AND ativo = 1",
                $params
            );
        } else {
            $params = [
                'id' => $id,
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'],
                'plataforma' => $data['plataforma'],
                'link_aula' => $data['link_aula'],
                'inicio_em' => $data['inicio_em'],
                'fim_em' => $data['fim_em'],
                'enviar_para_todos' => $data['enviar_para_todos'],
                'publicado' => $data['publicado'],
            ];
            if ($hasGravacao) {
                $params['link_gravacao'] = $data['link_gravacao'] ?? null;
            }
            $this->db->query(
                "UPDATE aulas_online SET
                    titulo = :titulo,
                    descricao = :descricao,
                    plataforma = :plataforma,
                    link_aula = :link_aula,
                    inicio_em = :inicio_em,
                    fim_em = :fim_em,
                    enviar_para_todos = :enviar_para_todos,
                    publicado = :publicado
                    {$gravacaoSql},
                    updated_at = NOW()
                 WHERE id = :id AND ativo = 1",
                $params
            );
        }

        $this->syncTurmas($id, $data['turmas_ids'] ?? [], (int) ($data['enviar_para_todos'] ?? 0));
    }

    public function softDelete(int $id): void
    {
        $this->db->query("UPDATE aulas_online SET ativo = 0, updated_at = NOW() WHERE id = :id", ['id' => $id]);
    }

    public function updateMeetingLink(int $id, string $url): void
    {
        if ($id <= 0 || trim($url) === '') {
            return;
        }

        $this->db->query(
            "UPDATE aulas_online SET link_aula = :url, updated_at = NOW() WHERE id = :id AND ativo = 1",
            ['id' => $id, 'url' => $url]
        );
    }

    public function markPandaIntegrationPending(int $id): void
    {
        if (
            !$this->hasColumn('aulas_online', 'panda_integracao_status') ||
            !$this->hasColumn('aulas_online', 'panda_integracao_tentativas')
        ) {
            return;
        }
        $this->db->query(
            "UPDATE aulas_online
             SET panda_integracao_status = 'pendente',
                 panda_integracao_erro = NULL,
                 panda_integracao_tentativas = COALESCE(panda_integracao_tentativas, 0) + 1,
                 panda_integracao_ultima_tentativa_em = NOW(),
                 updated_at = NOW()
             WHERE id = :id AND ativo = 1",
            ['id' => $id]
        );
    }

    public function updatePandaIntegrationSuccess(int $id, string $url, string $liveId, array $raw, ?string $descricao = null): void
    {
        if (!$this->hasColumn('aulas_online', 'panda_integracao_status')) {
            $this->db->query(
                "UPDATE aulas_online SET link_aula = :url, updated_at = NOW() WHERE id = :id AND ativo = 1",
                ['id' => $id, 'url' => $url]
            );
            return;
        }

        $livePlayer = isset($raw['live_player']) ? (string) $raw['live_player'] : '';
        $liveHls = isset($raw['live_hls']) ? (string) $raw['live_hls'] : '';
        $rtmp = isset($raw['rtmp']) ? (string) $raw['rtmp'] : '';
        $streamKey = isset($raw['stream_key']) ? (string) $raw['stream_key'] : '';
        $streamKeyId = isset($raw['stream_key_id']) ? (string) $raw['stream_key_id'] : '';

        if (
            $this->hasColumn('aulas_online', 'panda_live_id') &&
            $this->hasColumn('aulas_online', 'panda_stream_key_id') &&
            $this->hasColumn('aulas_online', 'panda_live_player') &&
            $this->hasColumn('aulas_online', 'panda_live_hls') &&
            $this->hasColumn('aulas_online', 'panda_rtmp') &&
            $this->hasColumn('aulas_online', 'panda_stream_key') &&
            $this->hasColumn('aulas_online', 'panda_integracao_erro')
        ) {
            $this->db->query(
                "UPDATE aulas_online
                 SET link_aula = :url,
                     descricao = COALESCE(:descricao, descricao),
                     panda_live_id = :live_id,
                     panda_stream_key_id = :stream_key_id,
                     panda_live_player = :live_player,
                     panda_live_hls = :live_hls,
                     panda_rtmp = :rtmp,
                     panda_stream_key = :stream_key,
                     panda_integracao_status = 'sucesso',
                     panda_integracao_erro = NULL,
                     updated_at = NOW()
                 WHERE id = :id AND ativo = 1",
                [
                    'id' => $id,
                    'url' => $url,
                    'descricao' => $descricao,
                    'live_id' => $liveId !== '' ? $liveId : null,
                    'stream_key_id' => $streamKeyId !== '' ? $streamKeyId : null,
                    'live_player' => $livePlayer !== '' ? $livePlayer : null,
                    'live_hls' => $liveHls !== '' ? $liveHls : null,
                    'rtmp' => $rtmp !== '' ? $rtmp : null,
                    'stream_key' => $streamKey !== '' ? $streamKey : null,
                ]
            );
            return;
        }

        $this->db->query(
            "UPDATE aulas_online
             SET link_aula = :url,
                 descricao = COALESCE(:descricao, descricao),
                 panda_integracao_status = 'sucesso',
                 updated_at = NOW()
             WHERE id = :id AND ativo = 1",
            [
                'id' => $id,
                'url' => $url,
                'descricao' => $descricao,
            ]
        );
    }

    public function copyPandaDataFromClass(int $sourceId, int $targetId): void
    {
        $source = $this->getById($sourceId);
        if (!$source) {
            return;
        }
        $this->updatePandaIntegrationSuccess(
            $targetId,
            (string) ($source['link_aula'] ?? ''),
            (string) ($source['panda_live_id'] ?? ''),
            [
                'live_player' => (string) ($source['panda_live_player'] ?? ''),
                'live_hls' => (string) ($source['panda_live_hls'] ?? ''),
                'rtmp' => (string) ($source['panda_rtmp'] ?? ''),
                'stream_key' => (string) ($source['panda_stream_key'] ?? ''),
                'stream_key_id' => (string) ($source['panda_stream_key_id'] ?? ''),
            ]
        );
    }

    public function updatePandaRecording(int $id, array $recording): void
    {
        if (
            !$this->hasColumn('aulas_online', 'panda_recording_video_id') ||
            !$this->hasColumn('aulas_online', 'panda_recording_player') ||
            !$this->hasColumn('aulas_online', 'panda_recording_hls') ||
            !$this->hasColumn('aulas_online', 'panda_recording_synced_at')
        ) {
            return;
        }

        $videoId = trim((string) ($recording['video_id'] ?? ''));
        $player = trim((string) ($recording['player_url'] ?? ''));
        $hls = trim((string) ($recording['hls_url'] ?? ''));

        if ($videoId === '' && $player === '' && $hls === '') {
            return;
        }

        $this->db->query(
            "UPDATE aulas_online
             SET panda_recording_video_id = COALESCE(:video_id, panda_recording_video_id),
                 panda_recording_player = COALESCE(:player, panda_recording_player),
                 panda_recording_hls = COALESCE(:hls, panda_recording_hls),
                 panda_recording_synced_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id AND ativo = 1",
            [
                'id' => $id,
                'video_id' => $videoId !== '' ? $videoId : null,
                'player' => $player !== '' ? $player : null,
                'hls' => $hls !== '' ? $hls : null,
            ]
        );
    }

    public function updateJaasRecording(int $id, array $recording): void
    {
        if (
            !$this->hasColumn('aulas_online', 'jaas_recording_url') ||
            !$this->hasColumn('aulas_online', 'jaas_recording_path') ||
            !$this->hasColumn('aulas_online', 'jaas_recording_session_id') ||
            !$this->hasColumn('aulas_online', 'jaas_recording_uploaded_at') ||
            !$this->hasColumn('aulas_online', 'jaas_recording_webhook_raw')
        ) {
            return;
        }

        $url = trim((string) ($recording['url'] ?? ''));
        $path = trim((string) ($recording['path'] ?? ''));
        $sessionId = trim((string) ($recording['session_id'] ?? ''));
        $raw = $recording['raw'] ?? [];
        $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $this->db->query(
            "UPDATE aulas_online
             SET jaas_recording_url = COALESCE(:url, jaas_recording_url),
                 jaas_recording_path = COALESCE(:path, jaas_recording_path),
                 jaas_recording_session_id = COALESCE(:session_id, jaas_recording_session_id),
                 jaas_recording_uploaded_at = NOW(),
                 jaas_recording_webhook_raw = COALESCE(:raw_json, jaas_recording_webhook_raw),
                 updated_at = NOW()
             WHERE id = :id AND ativo = 1",
            [
                'id' => $id,
                'url' => $url !== '' ? $url : null,
                'path' => $path !== '' ? $path : null,
                'session_id' => $sessionId !== '' ? $sessionId : null,
                'raw_json' => $rawJson !== false ? $rawJson : null,
            ]
        );
    }

    public function extractAulaIdFromJaasPayload(array $payload): int
    {
        $candidates = [
            (string) ($payload['fqn'] ?? ''),
            (string) ($payload['meetingFqn'] ?? ''),
            (string) ($payload['roomAddress'] ?? ''),
        ];

        if (isset($payload['data']) && is_array($payload['data'])) {
            $candidates[] = (string) ($payload['data']['conference'] ?? '');
            $candidates[] = (string) ($payload['data']['roomAddress'] ?? '');
        }

        foreach ($candidates as $value) {
            if (preg_match('/educatudo-aula-(\d+)/', $value, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    public function findAulaBySalaName(string $sala): int
    {
        $sala = trim($sala);
        if ($sala === '') {
            return 0;
        }

        if (preg_match('/educatudo-aula-(\d+)/', $sala, $m)) {
            $id = (int) $m[1];
            $aula = $id > 0 ? $this->getById($id) : null;
            if ($aula) {
                $path = (string) (parse_url((string) ($aula['link_aula'] ?? ''), PHP_URL_PATH) ?? '');
                $segmento = rawurldecode(trim(basename($path), '/'));
                if ($segmento === $sala) {
                    return $id;
                }
            }
        }

        $rows = $this->db->fetchAll(
            "SELECT id, link_aula FROM aulas_online
             WHERE ativo = 1 AND link_aula LIKE :sala
             ORDER BY inicio_em DESC
             LIMIT 50",
            ['sala' => '%' . str_replace(['%', '_'], ['\\%', '\\_'], $sala) . '%']
        ) ?: [];

        foreach ($rows as $row) {
            $path = (string) (parse_url((string) ($row['link_aula'] ?? ''), PHP_URL_PATH) ?? '');
            $segmento = rawurldecode(trim(basename($path), '/'));
            if ($segmento === $sala) {
                return (int) ($row['id'] ?? 0);
            }
        }

        return 0;
    }

    public function listarParaSincronizarGravacao(): array
    {
        return $this->db->fetchAll(
            "SELECT id, titulo, plataforma, link_aula, link_gravacao, inicio_em, fim_em
             FROM aulas_online
             WHERE ativo = 1
             ORDER BY inicio_em DESC
             LIMIT 1000"
        ) ?: [];
    }

    public function updateJitsiRecording(int $id, string $url): void
    {
        if (!$this->hasColumn('aulas_online', 'link_gravacao')) {
            return;
        }

        $this->db->query(
            "UPDATE aulas_online SET link_gravacao = :url, updated_at = NOW() WHERE id = :id AND ativo = 1",
            ['url' => $url, 'id' => $id]
        );
    }

    public function listArquivos(int $aulaId): array
    {
        if (!$this->tableExists('aulas_online_arquivos')) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT * FROM aulas_online_arquivos WHERE aula_id = :aula_id ORDER BY created_at ASC",
            ['aula_id' => $aulaId]
        ) ?: [];
    }

    public function addArquivo(int $aulaId, array $data): int
    {
        $this->db->query(
            "INSERT INTO aulas_online_arquivos (aula_id, nome_original, caminho, mime_type, tamanho, criado_por)
             VALUES (:aula_id, :nome_original, :caminho, :mime_type, :tamanho, :criado_por)",
            [
                'aula_id'      => $aulaId,
                'nome_original' => $data['nome_original'],
                'caminho'      => $data['caminho'],
                'mime_type'    => $data['mime_type'] ?? null,
                'tamanho'      => $data['tamanho'] ?? null,
                'criado_por'   => $data['criado_por'] ?? null,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function getArquivo(int $id): ?array
    {
        if (!$this->tableExists('aulas_online_arquivos')) {
            return null;
        }
        $row = $this->db->fetch(
            "SELECT * FROM aulas_online_arquivos WHERE id = :id",
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function deleteArquivo(int $id): void
    {
        $this->db->query(
            "DELETE FROM aulas_online_arquivos WHERE id = :id",
            ['id' => $id]
        );
    }

    private function tableExists(string $table): bool
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t",
            ['t' => $table]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    public function findLatestPandaSuccessClassId(?int $exceptId = null): int
    {
        $where = "ativo = 1 AND IFNULL(link_aula, '') <> ''";
        $params = [];
        if ($this->hasColumn('aulas_online', 'panda_integracao_status')) {
            $where .= " AND panda_integracao_status = 'sucesso'";
        }
        if ($exceptId !== null && $exceptId > 0) {
            $where .= " AND id <> :except_id";
            $params['except_id'] = $exceptId;
        }

        $row = $this->db->fetch(
            "SELECT id
             FROM aulas_online
             WHERE {$where}
             ORDER BY id DESC
             LIMIT 1",
            $params
        );

        return (int) ($row['id'] ?? 0);
    }

    public function updatePandaIntegrationError(int $id, string $error): void
    {
        if (
            !$this->hasColumn('aulas_online', 'panda_integracao_status') ||
            !$this->hasColumn('aulas_online', 'panda_integracao_erro')
        ) {
            return;
        }

        $msg = trim($error);
        if ($msg === '') {
            $msg = 'Erro desconhecido na integração Panda Video.';
        }
        if (mb_strlen($msg, 'UTF-8') > 1900) {
            $msg = mb_substr($msg, 0, 1900, 'UTF-8') . '...';
        }

        $this->db->query(
            "UPDATE aulas_online
             SET panda_integracao_status = 'erro',
                 panda_integracao_erro = :erro,
                 updated_at = NOW()
             WHERE id = :id AND ativo = 1",
            [
                'id' => $id,
                'erro' => $msg,
            ]
        );
    }

    public function listForAluno(int $alunoId, int $turmaId): array
    {
        if ($alunoId <= 0 || $turmaId <= 0) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT ao.*,
                    EXISTS (
                        SELECT 1 FROM aulas_online_turmas aot
                        WHERE aot.aula_online_id = ao.id AND aot.turma_id = :turma_id
                    ) AS turma_match
             FROM aulas_online ao
             WHERE ao.ativo = 1
               AND ao.publicado = 1
               AND (
                    ao.enviar_para_todos = 1
                    OR EXISTS (
                        SELECT 1 FROM aulas_online_turmas aot
                        WHERE aot.aula_online_id = ao.id AND aot.turma_id = :turma_id2
                    )
               )
             ORDER BY ao.inicio_em DESC",
            [
                'turma_id' => $turmaId,
                'turma_id2' => $turmaId,
            ]
        );

        return $rows;
    }

    /**
     * Aulas ao vivo agora ou agendadas visíveis para o aluno (turma dele
     * ou enviadas para todos). Usado no dashboard do aluno.
     */
    public function listLiveAndUpcomingForAluno(int $alunoId, int $turmaId, int $limit = 5): array
    {
        if ($alunoId <= 0 || $turmaId <= 0) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        return $this->db->fetchAll(
            "SELECT ao.id, ao.titulo, ao.plataforma, ao.inicio_em, ao.fim_em
             FROM aulas_online ao
             WHERE ao.ativo = 1
               AND ao.publicado = 1
               AND (ao.fim_em IS NULL OR ao.fim_em >= NOW())
               AND (
                    ao.enviar_para_todos = 1
                    OR EXISTS (
                        SELECT 1 FROM aulas_online_turmas aot
                        WHERE aot.aula_online_id = ao.id AND aot.turma_id = :turma_id
                    )
               )
             ORDER BY ao.inicio_em ASC
             LIMIT " . (int) $limit,
            ['turma_id' => $turmaId]
        );
    }

    public function alunoCanAccessAula(int $aulaId, int $turmaId): bool
    {
        if ($aulaId <= 0 || $turmaId <= 0) {
            return false;
        }

        $row = $this->db->fetch(
            "SELECT ao.id
             FROM aulas_online ao
             WHERE ao.id = :id
               AND ao.ativo = 1
               AND ao.publicado = 1
               AND (
                   ao.enviar_para_todos = 1
                   OR EXISTS (
                        SELECT 1 FROM aulas_online_turmas aot
                        WHERE aot.aula_online_id = ao.id AND aot.turma_id = :turma_id
                   )
               )
             LIMIT 1",
            ['id' => $aulaId, 'turma_id' => $turmaId]
        );

        return !empty($row);
    }

    /**
     * Verifica acesso à aula para qualquer uma das turmas do aluno.
     * @param int[] $turmaIds
     */
    public function alunoCanAccessAulaInAnyTurma(int $aulaId, array $turmaIds): bool
    {
        if ($aulaId <= 0 || empty($turmaIds)) {
            return false;
        }
        foreach ($turmaIds as $tid) {
            if ($this->alunoCanAccessAula($aulaId, (int) $tid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lista todas as aulas visíveis para o aluno em qualquer uma de suas turmas.
     * @param int[] $turmaIds
     */
    public function listForAlunoAllTurmas(int $alunoId, array $turmaIds): array
    {
        if ($alunoId <= 0 || empty($turmaIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
        $params = array_values($turmaIds);

        return $this->db->fetchAll(
            "SELECT DISTINCT ao.*
             FROM aulas_online ao
             WHERE ao.ativo = 1
               AND ao.publicado = 1
               AND (
                    ao.enviar_para_todos = 1
                    OR EXISTS (
                        SELECT 1 FROM aulas_online_turmas aot
                        WHERE aot.aula_online_id = ao.id AND aot.turma_id IN ({$placeholders})
                    )
               )
             ORDER BY ao.inicio_em DESC",
            $params
        );
    }

    public function listChatMessages(int $aulaId, int $afterId = 0, int $limit = 100): array
    {
        $aulaId = max(0, $aulaId);
        $afterId = max(0, $afterId);
        $limit = max(1, min(300, $limit));

        return $this->db->fetchAll(
            "SELECT m.id, m.aula_online_id, m.user_id, m.user_tipo, m.user_nome, m.mensagem, m.created_at
             FROM aulas_online_chat_messages m
             WHERE m.ativo = 1
               AND m.aula_online_id = :aula_id
               AND m.id > :after_id
             ORDER BY m.id ASC
             LIMIT {$limit}",
            ['aula_id' => $aulaId, 'after_id' => $afterId]
        );
    }

    public function addChatMessage(int $aulaId, int $userId, string $userTipo, string $userNome, string $mensagem): int
    {
        return (int) $this->db->insert(
            "INSERT INTO aulas_online_chat_messages
            (aula_online_id, user_id, user_tipo, user_nome, mensagem, ativo)
            VALUES
            (:aula_online_id, :user_id, :user_tipo, :user_nome, :mensagem, 1)",
            [
                'aula_online_id' => $aulaId,
                'user_id' => $userId,
                'user_tipo' => mb_substr(trim($userTipo), 0, 30, 'UTF-8'),
                'user_nome' => mb_substr(trim($userNome), 0, 140, 'UTF-8'),
                'mensagem' => mb_substr(trim($mensagem), 0, 2000, 'UTF-8'),
            ]
        );
    }

    private function syncTurmas(int $aulaId, array $turmasIds, int $enviarParaTodos): void
    {
        $this->db->query("DELETE FROM aulas_online_turmas WHERE aula_online_id = :id", ['id' => $aulaId]);

        if ($enviarParaTodos === 1) {
            return;
        }

        $turmasIds = array_values(array_unique(array_filter(array_map('intval', $turmasIds), static function ($v) {
            return $v > 0;
        })));

        foreach ($turmasIds as $tid) {
            $this->db->insert(
                "INSERT INTO aulas_online_turmas (aula_online_id, turma_id) VALUES (:aid, :tid)",
                ['aid' => $aulaId, 'tid' => $tid]
            );
        }
    }

    private function mapTurmasByAulaIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT aot.aula_online_id, t.id AS turma_id, t.nome AS turma_nome
             FROM aulas_online_turmas aot
             INNER JOIN turmas t ON t.id = aot.turma_id
             WHERE aot.aula_online_id IN ($placeholders)
             ORDER BY t.nome",
            $ids
        );

        $map = [];
        foreach ($rows as $r) {
            $aid = (int) ($r['aula_online_id'] ?? 0);
            if (!isset($map[$aid])) {
                $map[$aid] = [];
            }
            $map[$aid][] = [
                'id' => (int) ($r['turma_id'] ?? 0),
                'nome' => (string) ($r['turma_nome'] ?? ''),
            ];
        }

        return $map;
    }

    private function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, self::$columnCache)) {
            return self::$columnCache[$cacheKey];
        }

        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS total
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name",
                [
                    'table_name' => $table,
                    'column_name' => $column,
                ]
            );
            $exists = ((int) ($row['total'] ?? 0)) > 0;
            self::$columnCache[$cacheKey] = $exists;
            return $exists;
        } catch (Throwable $e) {
            self::$columnCache[$cacheKey] = false;
            return false;
        }
    }
}
}
