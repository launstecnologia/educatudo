<?php

namespace App\Modulos\EducaSync\Services;

/**
 * EducaSync — cliente HTTP do microserviço Python (solver de grade horária).
 *
 * Mesmo padrão do ApostilaIaClientService: cURL, timeout explícito e exceção em
 * erro de conexão, status não-2xx ou resposta que não seja JSON.
 * Env: EDUCASYNC_SOLVER_URL (padrão http://educasync-solver:8091) e
 * EDUCASYNC_SOLVER_INTERNAL_KEY (header X-Internal-Api-Key).
 */
class EducaSyncSolverCliente
{
    public const STATUS_VALIDOS = ['OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'UNKNOWN', 'MODEL_INVALID'];

    private string $baseUrl;
    private string $chaveInterna;

    public function __construct(?string $baseUrl = null, ?string $chaveInterna = null)
    {
        $this->baseUrl = rtrim((string) ($baseUrl ?? $this->variavelDeAmbiente('EDUCASYNC_SOLVER_URL', 'http://educasync-solver:8091')), '/');
        $this->chaveInterna = (string) ($chaveInterna ?? $this->variavelDeAmbiente('EDUCASYNC_SOLVER_INTERNAL_KEY', ''));
    }

    /** O worker do AIJobService roda em CLI, onde o helper env() pode não ter sido carregado. */
    private function variavelDeAmbiente(string $nome, string $padrao): string
    {
        $valor = function_exists('env') ? env($nome, null) : null;
        if ($valor === null || $valor === '') {
            $valor = getenv($nome);
        }
        return ($valor === false || $valor === null || $valor === '') ? $padrao : (string) $valor;
    }

    /**
     * Envia o cenário ao solver e devolve a resposta já normalizada.
     *
     * @param array<string,mixed> $payload contrato do EducaSyncContextoService
     * @return array{status:string, alocacoes:list<array<string,mixed>>, metricas:array<string,mixed>, objetivo:?float, best_bound:?float, tempo_ms:int}
     * @throws EducaSyncSolverException
     */
    public function otimizar(array $payload, int $maxTimeS): array
    {
        // O solver pode usar todo o max_time_s; sobra folga para montar e devolver a resposta.
        $resposta = $this->requisitar('POST', '/solve', $payload, max(30, $maxTimeS + 30));
        return $this->normalizarResposta($resposta);
    }

    /**
     * @return array<string,mixed>
     * @throws EducaSyncSolverException
     */
    public function saude(): array
    {
        return $this->requisitar('GET', '/health', null, 5);
    }

    /**
     * @param array<string,mixed> $resposta
     * @return array{status:string, alocacoes:list<array<string,mixed>>, metricas:array<string,mixed>, objetivo:?float, best_bound:?float, tempo_ms:int}
     */
    private function normalizarResposta(array $resposta): array
    {
        $status = strtoupper((string) ($resposta['status'] ?? ''));
        if (!in_array($status, self::STATUS_VALIDOS, true)) {
            throw new EducaSyncSolverException('Resposta do solver sem status válido.');
        }

        $alocacoes = [];
        foreach ((array) ($resposta['alocacoes'] ?? []) as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $demandaId = (int) ($linha['demanda_id'] ?? 0);
            $slotId = (int) ($linha['slot_id'] ?? 0);
            if ($demandaId <= 0 || $slotId <= 0) {
                continue;
            }
            $alocacoes[] = [
                'demanda_id' => $demandaId,
                'slot_id' => $slotId,
                'professor_id' => !empty($linha['professor_id']) ? (int) $linha['professor_id'] : null,
                'sala_id' => !empty($linha['sala_id']) ? (int) $linha['sala_id'] : null,
            ];
        }

        $metricas = is_array($resposta['metricas'] ?? null) ? $resposta['metricas'] : [];
        $tempoMs = (int) ($resposta['tempo_ms'] ?? ($metricas['tempo_ms'] ?? 0));

        return [
            'status' => $status,
            'alocacoes' => $alocacoes,
            'metricas' => $metricas,
            'objetivo' => isset($resposta['objetivo']) && is_numeric($resposta['objetivo']) ? (float) $resposta['objetivo'] : null,
            'best_bound' => isset($resposta['best_bound']) && is_numeric($resposta['best_bound']) ? (float) $resposta['best_bound'] : null,
            'tempo_ms' => max(0, $tempoMs),
        ];
    }

    /**
     * @param array<string,mixed>|null $corpo
     * @return array<string,mixed>
     * @throws EducaSyncSolverException
     */
    private function requisitar(string $metodo, string $caminho, ?array $corpo, int $timeoutSegundos): array
    {
        $url = $this->baseUrl . '/' . ltrim($caminho, '/');

        $ch = curl_init($url);
        if ($ch === false) {
            throw new EducaSyncSolverException("Falha ao inicializar cURL para {$url}");
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($metodo),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Internal-Api-Key: ' . $this->chaveInterna,
            ],
            CURLOPT_TIMEOUT => $timeoutSegundos,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($corpo !== null) {
            $json = json_encode($corpo, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                curl_close($ch);
                throw new EducaSyncSolverException('Falha ao serializar o cenário para o solver.');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $resposta = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($resposta === false || $curlErrno !== 0) {
            throw new EducaSyncSolverException(
                "Falha de conexão com o solver do EducaSync ({$metodo} {$caminho}): {$curlError}",
                $httpCode
            );
        }

        $decodificada = json_decode((string) $resposta, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $detalhe = is_array($decodificada)
                ? json_encode($decodificada, JSON_UNESCAPED_UNICODE)
                : substr((string) $resposta, 0, 500);
            throw new EducaSyncSolverException(
                "Solver do EducaSync retornou HTTP {$httpCode} em {$metodo} {$caminho}: {$detalhe}",
                $httpCode
            );
        }

        if (!is_array($decodificada)) {
            throw new EducaSyncSolverException(
                "Resposta inválida (não-JSON) do solver do EducaSync em {$metodo} {$caminho}",
                $httpCode
            );
        }

        return $decodificada;
    }
}
