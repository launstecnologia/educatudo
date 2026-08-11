<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Ava/LiveClass.php';
require_once __DIR__ . '/JaasMeetService.php';
require_once __DIR__ . '/PandaVideoLiveService.php';

/**
 * EducaTudo - AvaLiveClassService
 *
 * Aulas ao vivo do AVA reaproveitando os serviços existentes de vídeo
 * (JaaS/Jitsi e Panda Video Live), com namespace de sala próprio do AVA.
 */
class AvaLiveClassService
{
    /** Namespace de sala Jitsi do AVA (evita colisão com aulas_online). */
    private const ROOM_KIND = 'avalive';

    private LiveClass $model;

    public function __construct()
    {
        $this->model = new LiveClass();
    }

    public function model(): LiveClass
    {
        return $this->model;
    }

    /**
     * Cria a aula ao vivo. Para Panda, tenta criar a live remota.
     * @param array<string,mixed> $data
     * @return array{id:int,warning:string}
     */
    public function create(array $data): array
    {
        $id = $this->model->save($data);
        $warning = '';

        if (($data['plataforma'] ?? '') === 'panda') {
            try {
                $panda = new PandaVideoLiveService();
                if ($panda->isConfigured()) {
                    $res = $panda->createLive([
                        'titulo' => (string) ($data['titulo'] ?? 'Aula ao vivo'),
                        'inicio_em_iso' => $this->toIso($data['inicio_em'] ?? null),
                    ]);
                    $this->model->setPandaLive($id, (string) ($res['id_externo'] ?? ''), (string) ($res['url'] ?? ''));
                } else {
                    $warning = 'Aula criada, mas a integração Panda Video não está configurada. Configure ou informe um link externo.';
                }
            } catch (Throwable $e) {
                $warning = 'Aula criada, mas houve erro ao criar a live no Panda: ' . $e->getMessage();
            }
        }

        return ['id' => $id, 'warning' => $warning];
    }

    /**
     * URL de entrada na sala conforme a plataforma.
     * @param array<string,mixed> $live
     * @param array<string,mixed> $user  ['id','nome','email']
     */
    public function joinUrl(array $live, array $user, bool $moderator = false): string
    {
        $plataforma = (string) ($live['plataforma'] ?? 'jitsi');
        if ($plataforma === 'panda') {
            return (string) ($live['panda_live_player'] ?? '');
        }
        if ($plataforma === 'externo') {
            return (string) ($live['link_externo'] ?? '');
        }
        // jitsi
        $jaas = new JaasMeetService();
        return $jaas->meetingUrl(
            (int) ($live['id'] ?? 0),
            (string) ($live['titulo'] ?? ''),
            $user,
            $moderator,
            self::ROOM_KIND
        );
    }

    /** Para Jitsi aberto (sem JWT) o embed é possível; para JaaS/externo abrimos em nova aba. */
    public function canEmbed(array $live): bool
    {
        return (string) ($live['plataforma'] ?? 'jitsi') === 'jitsi';
    }

    /** URL da gravação disponível (manual, ou Panda VOD). */
    public function recordingUrl(array $live): string
    {
        if (!empty($live['gravacao_url'])) {
            return (string) $live['gravacao_url'];
        }
        if (!empty($live['panda_recording_player'])) {
            return (string) $live['panda_recording_player'];
        }
        if (!empty($live['panda_recording_hls'])) {
            return (string) $live['panda_recording_hls'];
        }
        return '';
    }

    public function hasRecording(array $live): bool
    {
        return $this->recordingUrl($live) !== '';
    }

    /**
     * Sincroniza a gravação do Panda após o encerramento (pull lazy).
     * Retorna o $live atualizado.
     * @param array<string,mixed> $live
     * @return array<string,mixed>
     */
    public function syncPandaRecordingIfNeeded(array $live): array
    {
        $id = (int) ($live['id'] ?? 0);
        if ($id <= 0) {
            return $live;
        }
        if (($live['plataforma'] ?? '') !== 'panda' || empty($live['panda_live_id'])) {
            return $live;
        }
        if (!empty($live['panda_recording_player']) || !empty($live['panda_recording_hls'])) {
            return $live;
        }
        // Só após o fim previsto (ou se já marcada encerrada).
        $terminou = ($live['status'] ?? '') === 'encerrada'
            || (!empty($live['fim_em']) && strtotime((string) $live['fim_em']) < time());
        if (!$terminou) {
            return $live;
        }
        try {
            $panda = new PandaVideoLiveService();
            if (!$panda->isConfigured()) {
                return $live;
            }
            $rec = $panda->fetchLiveRecording((string) $live['panda_live_id']);
            $player = (string) ($rec['player_url'] ?? '');
            $hls = (string) ($rec['hls_url'] ?? '');
            if ($player !== '' || $hls !== '') {
                $this->model->setPandaRecording($id, $player, $hls);
                $live['panda_recording_player'] = $player;
                $live['panda_recording_hls'] = $hls;
            }
        } catch (Throwable $e) {
            // best-effort
        }
        return $live;
    }

    /** Estado computado por horário para exibição (não persiste). */
    public function computedState(array $live): string
    {
        $status = (string) ($live['status'] ?? 'agendada');
        if (in_array($status, ['cancelada', 'encerrada'], true)) {
            return $status;
        }
        $agora = time();
        $inicio = !empty($live['inicio_em']) ? strtotime((string) $live['inicio_em']) : null;
        $fim = !empty($live['fim_em']) ? strtotime((string) $live['fim_em']) : null;
        if ($fim !== null && $fim < $agora) {
            return 'encerrada';
        }
        if ($inicio !== null && $inicio <= $agora && ($fim === null || $fim >= $agora)) {
            return 'ao_vivo';
        }
        return 'agendada';
    }

    private function toIso($value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return gmdate('Y-m-d\TH:i:s');
        }
        $ts = strtotime($value);
        return $ts ? date('c', $ts) : gmdate('Y-m-d\TH:i:s');
    }
}
