<?php

namespace App\Services;

use App\Models\Finance\FinancePlan;
use App\Models\Finance\FinanceContractItem;
use App\Models\Finance\FinanceConfig;
use Database;

class FinancePlanService
{
    private FinancePlan $planModel;
    private FinanceContractItem $itemModel;
    private FinanceConfig $configModel;

    public function __construct(?Database $db = null)
    {
        $this->planModel  = new FinancePlan($db);
        $this->itemModel  = new FinanceContractItem($db);
        $this->configModel = new FinanceConfig($db);
    }

    public function all(?int $anoLetivoId = null, bool $apenasAtivos = true): array
    {
        $plans = $this->planModel->all($anoLetivoId, $apenasAtivos);
        foreach ($plans as &$plan) {
            $plan['items']      = $this->planModel->getItems((int)$plan['id']);
            $plan['total_plan'] = array_sum(array_column($plan['items'], 'valor_base'));
        }
        return $plans;
    }

    public function findById(int $id): ?array
    {
        $plan = $this->planModel->findById($id);
        if (!$plan) return null;
        $plan['items']      = $this->planModel->getItems($id);
        $plan['total_plan'] = array_sum(array_column($plan['items'], 'valor_base'));
        return $plan;
    }

    public function create(array $data): int
    {
        return $this->planModel->create($data);
    }

    public function addItem(int $planId, array $data): int
    {
        $data['unidade_id'] = (int)($data['unidade_id'] ?? 0) ?: null;
        return $this->planModel->addItem($planId, $data);
    }

    public function removeItem(int $itemId): void
    {
        $this->planModel->removeItem($itemId);
    }

    public function toggle(int $planId): void
    {
        $this->planModel->toggle($planId);
    }

    public function delete(int $planId): void
    {
        $this->planModel->delete($planId);
    }

    /**
     * Clona planos de um ano letivo para outro, aplicando reajuste percentual nos valores.
     *
     * @return array{clonados:int,pulados:int}
     */
    public function clonarParaAno(int $anoOrigemId, int $anoDestinoId, float $pctReajuste = 0.0): array
    {
        if ($anoOrigemId <= 0 || $anoDestinoId <= 0 || $anoOrigemId === $anoDestinoId) {
            throw new \InvalidArgumentException('Informe anos letivos de origem e destino diferentes.');
        }
        $db = Database::getInstance();
        $origem = $db->fetch('SELECT id, ano FROM ano_letivo WHERE id = ?', [$anoOrigemId]);
        $destino = $db->fetch('SELECT id, ano FROM ano_letivo WHERE id = ?', [$anoDestinoId]);
        if (!$origem || !$destino) {
            throw new \InvalidArgumentException('Ano letivo inválido.');
        }

        $temOrigemCol = (bool) $db->fetch('SHOW COLUMNS FROM finance_plans LIKE ?', ['plano_origem_id']);
        $planos = $this->planModel->all($anoOrigemId, false);
        $fator = 1 + ($pctReajuste / 100);
        $clonados = 0;
        $pulados = 0;

        foreach ($planos as $plano) {
            $origemId = (int) $plano['id'];
            if ($temOrigemCol) {
                $ja = $db->fetch(
                    'SELECT id FROM finance_plans WHERE plano_origem_id = ? AND ano_letivo_id = ? LIMIT 1',
                    [$origemId, $anoDestinoId]
                );
                if ($ja) {
                    $pulados++;
                    continue;
                }
            }

            $nome = trim((string) ($plano['nome'] ?? 'Plano'));
            $anoDest = (string) ($destino['ano'] ?? '');
            if ($anoDest !== '' && !str_contains($nome, $anoDest)) {
                $nome = $nome . ' ' . $anoDest;
            }

            if ($temOrigemCol) {
                $db->insert(
                    'INSERT INTO finance_plans (nome, descricao, ano_letivo_id, serie_id, plano_origem_id, ativo)
                     VALUES (?,?,?,?,?,?)',
                    [
                        $nome,
                        $plano['descricao'] ?? null,
                        $anoDestinoId,
                        (int) ($plano['serie_id'] ?? 0) ?: null,
                        $origemId,
                        1,
                    ]
                );
            } else {
                $novoIdTmp = $this->planModel->create([
                    'nome' => $nome,
                    'descricao' => $plano['descricao'] ?? null,
                    'ano_letivo_id' => $anoDestinoId,
                    'serie_id' => (int) ($plano['serie_id'] ?? 0) ?: null,
                    'ativo' => 1,
                ]);
                $this->copiarItens($origemId, $novoIdTmp, $fator);
                $clonados++;
                continue;
            }
            $novoId = (int) $db->lastInsertId();
            $this->copiarItens($origemId, $novoId, $fator);
            $clonados++;
        }

        return ['clonados' => $clonados, 'pulados' => $pulados];
    }

    private function copiarItens(int $planoOrigemId, int $planoDestinoId, float $fator): void
    {
        $itens = $this->planModel->getItems($planoOrigemId);
        foreach ($itens as $item) {
            $valor = round((float) ($item['valor_base'] ?? 0) * $fator, 2);
            $this->planModel->addItem($planoDestinoId, [
                'categoria' => $item['categoria'] ?? 'mensalidade',
                'descricao' => $item['descricao'] ?? '',
                'valor_base' => $valor,
                'num_parcelas' => (int) ($item['num_parcelas'] ?? 1),
                'mes_inicio' => (int) ($item['mes_inicio'] ?? 1),
                'mes_fim' => $item['mes_fim'] ?? null,
                'dia_vencimento' => $item['dia_vencimento'] ?? null,
                'fornecedor_externo' => $item['fornecedor_externo'] ?? 0,
                'nome_instituicao' => $item['nome_instituicao'] ?? null,
                'unidade_id' => $item['unidade_id'] ?? null,
                'ordem' => (int) ($item['ordem'] ?? 0),
            ]);
        }
    }

    /**
     * Gera os finance_contract_items copiando os itens do plano para o contrato.
     * Aplica desconto percentual se fornecido.
     */
    public function aplicarPlanoNoContrato(int $planId, int $contractId, float $pctDesconto = 0.0): void
    {
        $items = $this->planModel->getItems($planId);
        $cfg   = $this->configModel->get();
        $diaVencPadrao = (int)($cfg['dia_vencimento_padrao'] ?? 10);

        foreach ($items as $item) {
            $valorBase   = (float)$item['valor_base'];
            $desconto    = $pctDesconto > 0 ? round($valorBase * $pctDesconto / 100, 2) : 0.0;

            $this->itemModel->create($contractId, [
                'plan_item_id'      => $item['id'],
                'categoria'         => $item['categoria'],
                'descricao'         => $item['descricao'],
                'valor_unitario'    => $valorBase,
                'quantidade'        => 1,
                'valor_desconto'    => $desconto,
                'num_parcelas'      => $item['num_parcelas'],
                'mes_inicio'        => $item['mes_inicio'],
                'mes_fim'           => $item['mes_fim'],
                'dia_vencimento'    => $item['dia_vencimento'] ?? $diaVencPadrao,
                'fornecedor_externo'=> $item['fornecedor_externo'],
                'nome_instituicao'  => $item['nome_instituicao'],
            ]);
        }
    }
}
