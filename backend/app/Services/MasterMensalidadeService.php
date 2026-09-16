<?php
/**
 * Mensalidade SaaS (EducaTudo → escola): valor por usuário pagante no tenant.
 */

if (!class_exists('MasterMensalidadeService')) {

class MasterMensalidadeService
{
    public const CONFIG_KEY = 'valor_por_usuario';

    /**
     * @param array<int, array<string, mixed>> $escolas
     * @return array<int, array<string, mixed>>
     */
    public function coletarResumos(array $escolas, int $filtroEscolaId = 0, string $busca = ''): array
    {
        require_once __DIR__ . '/../Core/MasterTenantConnection.php';

        $linhas = [];
        $buscaNorm = mb_strtolower(trim($busca));

        foreach ($escolas as $escola) {
            $escolaId = (int) ($escola['id'] ?? 0);
            if ($escolaId <= 0) {
                continue;
            }
            if ($filtroEscolaId > 0 && $filtroEscolaId !== $escolaId) {
                continue;
            }

            $nome = (string) ($escola['nome'] ?? '');
            if ($buscaNorm !== '' && mb_stripos(mb_strtolower($nome . ' ' . (string) ($escola['slug'] ?? '')), $buscaNorm) === false) {
                continue;
            }

            $conn = MasterTenantConnection::getPdoAndEscola($escolaId);
            if (!$conn || empty($conn['pdo'])) {
                $linhas[] = $this->resumoVazio($escola, 'Sem conexão com o banco da escola.');
                continue;
            }

            try {
                $linhas[] = $this->resumoDaEscola($conn['pdo'], $escola);
            } catch (Throwable $e) {
                error_log('[MasterMensalidadeService] escola ' . $escolaId . ': ' . $e->getMessage());
                $linhas[] = $this->resumoVazio($escola, 'Não foi possível ler a cobrança desta escola.');
            }
        }

        return $linhas;
    }

    /**
     * @param array<int, array<string, mixed>> $linhas
     * @return array{escolas:int,alunos_pagantes:int,professores_pagantes:int,valor_total:float,sem_valor:int}
     */
    public function agregarTotais(array $linhas): array
    {
        $totais = [
            'escolas' => count($linhas),
            'alunos_pagantes' => 0,
            'professores_pagantes' => 0,
            'valor_total' => 0.0,
            'sem_valor' => 0,
        ];
        foreach ($linhas as $linha) {
            if (!empty($linha['erro'])) {
                continue;
            }
            $totais['alunos_pagantes'] += (int) ($linha['alunos_pagantes'] ?? 0);
            $totais['professores_pagantes'] += (int) ($linha['professores_pagantes'] ?? 0);
            $totais['valor_total'] += (float) ($linha['valor_total'] ?? 0);
            if ((float) ($linha['valor_por_usuario'] ?? 0) <= 0) {
                $totais['sem_valor']++;
            }
        }
        return $totais;
    }

    /**
     * @param array<string, mixed> $escola
     * @return array<string, mixed>
     */
    public function resumoDaEscola(PDO $pdo, array $escola): array
    {
        $valorPorUsuario = $this->lerValorPorUsuario($pdo);
        $alunos = $this->contarUsuarios($pdo, 'alunos');
        $professores = $this->contarUsuarios($pdo, 'professores');
        $totalPagantes = $alunos['pagantes'] + $professores['pagantes'];
        $valorTotal = round($totalPagantes * $valorPorUsuario, 2);
        $ultimaFatura = $this->lerUltimaFatura($pdo);

        return [
            'escola_id' => (int) ($escola['id'] ?? 0),
            'escola_nome' => (string) ($escola['nome'] ?? ''),
            'escola_slug' => (string) ($escola['slug'] ?? ''),
            'erro' => null,
            'valor_por_usuario' => $valorPorUsuario,
            'alunos_ativos' => $alunos['ativos'],
            'alunos_pagantes' => $alunos['pagantes'],
            'professores_ativos' => $professores['ativos'],
            'professores_pagantes' => $professores['pagantes'],
            'total_pagantes' => $totalPagantes,
            'valor_total' => $valorTotal,
            'ultima_fatura' => $ultimaFatura,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historicoDaEscola(PDO $pdo): array
    {
        if (!$this->tabelaExiste($pdo, 'financeiro_valores_mensais')) {
            return [];
        }

        $stmt = $pdo->query(
            "SELECT mes_referencia, total_alunos_pagantes, total_professores_pagantes,
                    total_usuarios_pagantes, valor_por_usuario, valor_total, status,
                    data_vencimento, data_pagamento
             FROM financeiro_valores_mensais
             ORDER BY mes_referencia DESC
             LIMIT 24"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function salvarValorPorUsuario(PDO $pdo, float $valor): void
    {
        if ($valor < 0) {
            throw new InvalidArgumentException('O valor por usuário não pode ser negativo.');
        }
        if (!$this->tabelaExiste($pdo, 'config_layout')) {
            throw new RuntimeException('A tabela de configuração desta escola não está disponível.');
        }

        $valorFmt = number_format($valor, 2, '.', '');
        $stmt = $pdo->prepare(
            "INSERT INTO config_layout (config_key, config_value)
             VALUES (:config_key, :config_value)
             ON DUPLICATE KEY UPDATE
                config_value = VALUES(config_value),
                updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'config_key' => self::CONFIG_KEY,
            'config_value' => $valorFmt,
        ]);
    }

    public function lerValorPorUsuario(PDO $pdo): float
    {
        if (!$this->tabelaExiste($pdo, 'config_layout')) {
            return 0.0;
        }

        $stmt = $pdo->prepare(
            'SELECT config_value FROM config_layout WHERE config_key = :config_key LIMIT 1'
        );
        $stmt->execute(['config_key' => self::CONFIG_KEY]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float) ($row['config_value'] ?? 0);
    }

    /**
     * @return array{ativos:int,pagantes:int}
     */
    private function contarUsuarios(PDO $pdo, string $tabela): array
    {
        if (!in_array($tabela, ['alunos', 'professores'], true) || !$this->tabelaExiste($pdo, $tabela)) {
            return ['ativos' => 0, 'pagantes' => 0];
        }

        $ativos = (int) ($pdo->query("SELECT COUNT(*) FROM `{$tabela}` WHERE ativo = 1")->fetchColumn() ?: 0);
        $pagantes = $ativos;
        if ($this->colunaExiste($pdo, $tabela, 'pagante')) {
            $pagantes = (int) ($pdo->query("SELECT COUNT(*) FROM `{$tabela}` WHERE ativo = 1 AND pagante = 1")->fetchColumn() ?: 0);
        }

        return ['ativos' => $ativos, 'pagantes' => $pagantes];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lerUltimaFatura(PDO $pdo): ?array
    {
        if (!$this->tabelaExiste($pdo, 'financeiro_valores_mensais')) {
            return null;
        }

        $stmt = $pdo->query(
            "SELECT mes_referencia, total_alunos_pagantes, total_professores_pagantes,
                    total_usuarios_pagantes, valor_por_usuario, valor_total, status,
                    data_vencimento, data_pagamento
             FROM financeiro_valores_mensais
             ORDER BY mes_referencia DESC
             LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $escola
     * @return array<string, mixed>
     */
    private function resumoVazio(array $escola, string $erro): array
    {
        return [
            'escola_id' => (int) ($escola['id'] ?? 0),
            'escola_nome' => (string) ($escola['nome'] ?? ''),
            'escola_slug' => (string) ($escola['slug'] ?? ''),
            'erro' => $erro,
            'valor_por_usuario' => 0.0,
            'alunos_ativos' => 0,
            'alunos_pagantes' => 0,
            'professores_ativos' => 0,
            'professores_pagantes' => 0,
            'total_pagantes' => 0,
            'valor_total' => 0.0,
            'ultima_fatura' => null,
        ];
    }

    private function tabelaExiste(PDO $pdo, string $tabela): bool
    {
        $permitidas = ['alunos', 'professores', 'config_layout', 'financeiro_valores_mensais'];
        if (!in_array($tabela, $permitidas, true)) {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :tabela'
        );
        $stmt->execute(['tabela' => $tabela]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function colunaExiste(PDO $pdo, string $tabela, string $coluna): bool
    {
        if (!in_array($tabela, ['alunos', 'professores'], true) || $coluna !== 'pagante') {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :tabela AND column_name = :coluna'
        );
        $stmt->execute(['tabela' => $tabela, 'coluna' => $coluna]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

}
