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
