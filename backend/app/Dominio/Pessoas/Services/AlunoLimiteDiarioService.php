<?php

namespace App\Dominio\Pessoas\Services;

use App\Core\Database;

/**
 * Limites diários de ações do aluno (alunos_acoes_diarias + config_layout).
 * Independente de TudiCoins / ledger financeiro.
 *
 * Preserva dualidade legada:
 * - dashboard: LayoutHelper::getConfigValue com defaults
 * - gate de ação: LayoutHelper::get('limit_'.$tipo, '0') — 0 = sem limite
 */
class AlunoLimiteDiarioService
{
    public const ACAO_CHAT = 'chat_interacoes';
    public const ACAO_GERAR_TEMA = 'gerar_tema_redacao';
    public const ACAO_CORRIGIR = 'corrigir_redacao';
    public const ACAO_EXERCICIOS = 'exercicios';
    public const ACAO_SIMULADOS = 'simulados';

    private $db;
    /** @var callable(string, string):string|null override de config (testes) */
    private $lerConfigDashboard;
    /** @var callable(string, string):string|null override de config de gate (testes) */
    private $lerConfigGate;

    public function __construct($db = null, ?callable $lerConfigDashboard = null, ?callable $lerConfigGate = null)
    {
        $this->db = $db ?: Database::getInstance();
        $this->lerConfigDashboard = $lerConfigDashboard;
        $this->lerConfigGate = $lerConfigGate;
    }

    /**
     * @return array<string, array{usado:int,limite:int|string}>
     */
    public function listarParaDashboard(int $alunoId): array
    {
        $uso = $this->contarUsoDiarioAgrupado($alunoId);
        return [
            'chat' => [
                'usado' => (int) ($uso[self::ACAO_CHAT] ?? 0),
                'limite' => $this->configDashboard('limit_chat_interacoes', '10'),
            ],
            'tema' => [
                'usado' => (int) ($uso[self::ACAO_GERAR_TEMA] ?? 0),
                'limite' => $this->configDashboard('limit_gerar_tema_redacao', '3'),
            ],
            'correcao' => [
                'usado' => (int) ($uso[self::ACAO_CORRIGIR] ?? 0),
                'limite' => $this->configDashboard('limit_corrigir_redacao', '5'),
            ],
            'exercicios' => [
                'usado' => (int) ($uso[self::ACAO_EXERCICIOS] ?? 0),
                'limite' => $this->configDashboard('limit_exercicios', '10'),
            ],
            'simulados' => [
                'usado' => (int) ($uso[self::ACAO_SIMULADOS] ?? 0),
                'limite' => $this->configDashboard('limit_simulados', '2'),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function contarUsoDiarioAgrupado(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $inicio = date('Y-m-d') . ' 00:00:00';
        $fim = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $rows = $this->db->fetchAll(
            "SELECT acao, COUNT(*) AS total
             FROM alunos_acoes_diarias
             WHERE aluno_id = :aluno_id
               AND created_at >= :inicio
               AND created_at < :fim
             GROUP BY acao",
            ['aluno_id' => $alunoId, 'inicio' => $inicio, 'fim' => $fim]
        ) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[(string) ($row['acao'] ?? '')] = (int) ($row['total'] ?? 0);
        }
        return $out;
    }

    public function getLimiteDiario(string $tipo): int
    {
        return (int) $this->configGate('limit_' . $tipo, '0');
    }

    public function contarUsoDiario(int $alunoId, string $tipo): int
    {
        if ($alunoId <= 0 || $tipo === '') {
            return 0;
        }
        $hoje = date('Y-m-d');
        $row = $this->db->fetch(
            "SELECT COUNT(*) as total
             FROM alunos_acoes_diarias
             WHERE aluno_id = :aluno_id
             AND acao = :acao
             AND DATE(created_at) = :hoje",
            ['aluno_id' => $alunoId, 'acao' => $tipo, 'hoje' => $hoje]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array{pode:bool,mensagem?:string,restantes?:int}
     */
    public function podeExecutarAcao(int $alunoId, string $tipo): array
    {
        $limite = $this->getLimiteDiario($tipo);
        if ($limite === 0) {
            return ['pode' => true];
        }
        $uso = $this->contarUsoDiario($alunoId, $tipo);
        if ($uso >= $limite) {
            $mensagens = [
                self::ACAO_CHAT => "Você atingiu o limite diário de $limite interações de chat. Tente novamente amanhã.",
                self::ACAO_GERAR_TEMA => "Você atingiu o limite diário de $limite gerações de tema de redação. Tente novamente amanhã.",
                self::ACAO_CORRIGIR => "Você atingiu o limite diário de $limite correções de redação. Tente novamente amanhã.",
                self::ACAO_EXERCICIOS => "Você atingiu o limite diário de $limite exercícios. Tente novamente amanhã.",
                self::ACAO_SIMULADOS => "Você atingiu o limite diário de $limite simulados. Tente novamente amanhã.",
            ];
            return [
                'pode' => false,
                'mensagem' => $mensagens[$tipo] ?? "Você atingiu o limite diário de $limite desta ação.",
            ];
        }
        return ['pode' => true, 'restantes' => $limite - $uso];
    }

    public function registrarAcao(int $alunoId, string $acao): void
    {
        if ($alunoId <= 0 || $acao === '') {
            return;
        }
        $this->db->insert(
            "INSERT INTO alunos_acoes_diarias (aluno_id, acao, created_at)
             VALUES (:aluno_id, :acao, NOW())",
            ['aluno_id' => $alunoId, 'acao' => $acao]
        );
    }

    private function configDashboard(string $key, string $default): string
    {
        if ($this->lerConfigDashboard !== null) {
            return (string) ($this->lerConfigDashboard)($key, $default);
        }
        if (class_exists(\LayoutHelper::class) && method_exists(\LayoutHelper::class, 'getConfigValue')) {
            return (string) \LayoutHelper::getConfigValue($key, $default);
        }
        return $default;
    }

    private function configGate(string $key, string $default): string
    {
        if ($this->lerConfigGate !== null) {
            return (string) ($this->lerConfigGate)($key, $default);
        }
        if (class_exists(\LayoutHelper::class)) {
            return (string) \LayoutHelper::get($key, $default);
        }
        return $default;
    }
}
