<?php

namespace App\Modulos\AgrupamentosComponentes\Services;

require_once __DIR__ . '/../Models/AgrupamentoComponente.php';
require_once __DIR__ . '/../../../Models/Education/ComponenteCurricular.php';

use App\Modulos\AgrupamentosComponentes\Models\AgrupamentoComponente;
use ComponenteCurricular;

/**
 * CRUD da regra de nota da área (média/soma). Os filhos vêm do componente pai.
 */
class AgrupamentoComponenteService
{
    private AgrupamentoComponente $model;
    private ComponenteCurricular $componentes;

    public function __construct()
    {
        $this->model = new AgrupamentoComponente();
        $this->componentes = new ComponenteCurricular();
    }

    public function model(): AgrupamentoComponente
    {
        return $this->model;
    }

    /**
     * @return array{success:bool,id?:int,error?:string}
     */
    public function salvar(array $input, ?int $id = null): array
    {
        if (!$this->model->tabelasProntas()) {
            return ['success' => false, 'error' => 'Rode a migration 2026_09_02_agrupamentos_componentes.sql no painel Master.'];
        }

        $data = $this->normalizar($input);
        $rotuloId = (int) ($data['materia_rotulo_id'] ?? 0);
        if ($rotuloId <= 0) {
            return ['success' => false, 'error' => 'Escolha a área (componente pai) cadastrada em Componentes Curriculares.'];
        }

        $pai = $this->componentes->findById($rotuloId);
        if (!is_array($pai) || (int) ($pai['pai_id'] ?? 0) > 0) {
            return ['success' => false, 'error' => 'Área inválida.'];
        }

        $ids = $this->componentes->listarFilhosIds($rotuloId);
        if ($data['nome'] === '') {
            $data['nome'] = trim((string) ($pai['nome'] ?? ''));
        }

        $erro = $this->validar($data, $ids, $id);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($id !== null && $id > 0) {
            if ($this->model->findById($id) === null) {
                return ['success' => false, 'error' => 'Agrupamento não encontrado.'];
            }
            $this->model->atualizar($id, $data, $ids);
            return ['success' => true, 'id' => $id];
        }

        $novoId = $this->model->criar($data, $ids);
        return ['success' => true, 'id' => $novoId];
    }

    /**
     * @return array{success:bool,error?:string}
     */
    public function excluir(int $id): array
    {
        if ($this->model->findById($id) === null) {
            return ['success' => false, 'error' => 'Agrupamento não encontrado.'];
        }
        $this->model->excluir($id);
        return ['success' => true];
    }

    /**
     * Payload estável para wizard/editor de boletim. Filhos sempre os atuais do pai.
     *
     * @return list<array{id:int,nome:string,modo:string,aplicar_em:string,divisor:?float,materia_rotulo_id:?int,materias_ids:list<int>}>
     */
    public function listarParaBoletim(): array
    {
        $out = [];
        foreach ($this->model->listarCompletos(true) as $row) {
            $pronto = $this->expandirParaBoletim($row);
            if ($pronto === null) {
                continue;
            }
            $out[] = $pronto;
        }
        return $out;
    }

    /**
     * @return array{id:int,nome:string,modo:string,aplicar_em:string,divisor:?float,materia_rotulo_id:?int,materias_ids:list<int>}|null
     */
    public function carregarParaBoletim(int $id): ?array
    {
        $row = $this->model->carregarCompleto($id);
        if ($row === null) {
            return null;
        }
        return $this->expandirParaBoletim($row);
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,nome:string,modo:string,aplicar_em:string,divisor:?float,materia_rotulo_id:?int,materias_ids:list<int>}|null
     */
    private function expandirParaBoletim(array $row): ?array
    {
        $rotulo = (int) ($row['materia_rotulo_id'] ?? 0);
        $ids = is_array($row['materias_ids'] ?? null) ? array_values(array_map('intval', $row['materias_ids'])) : [];
        if ($rotulo > 0) {
            $ids = $this->componentes->listarFilhosIds($rotulo);
        }
        $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
        if (count($ids) < 2) {
            return null;
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'nome' => (string) ($row['nome'] ?? ''),
            'modo' => ((string) ($row['modo'] ?? 'media')) === 'soma' ? 'soma' : 'media',
            'aplicar_em' => ((string) ($row['aplicar_em'] ?? 'boletim')) === 'ambos' ? 'ambos' : 'boletim',
            'divisor' => isset($row['divisor']) && $row['divisor'] !== null && $row['divisor'] !== ''
                ? (float) $row['divisor']
                : null,
            'materia_rotulo_id' => $rotulo > 0 ? $rotulo : null,
            'materias_ids' => $ids,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizar(array $input): array
    {
        $divisorRaw = trim((string) ($input['divisor'] ?? ''));
        return [
            'nome' => trim((string) ($input['nome'] ?? '')),
            'modo' => strtolower(trim((string) ($input['modo'] ?? 'media'))),
            'aplicar_em' => strtolower(trim((string) ($input['aplicar_em'] ?? 'boletim'))),
            'divisor' => $divisorRaw === '' ? null : str_replace(',', '.', $divisorRaw),
            'materia_rotulo_id' => (int) ($input['materia_rotulo_id'] ?? 0),
            'ativo' => array_key_exists('ativo', $input) ? (!empty($input['ativo']) ? 1 : 0) : 1,
        ];
    }

    /**
     * @param list<int> $ids
     */
    private function validar(array $data, array $ids, ?int $idAtual = null): ?string
    {
        if ($data['nome'] === '') {
            return 'Nome é obrigatório.';
        }
        if (mb_strlen($data['nome']) > 150) {
            return 'Nome deve ter no máximo 150 caracteres.';
        }
        if (count($ids) < 2) {
            return 'Essa área precisa de pelo menos dois desdobramentos com nota em Componentes Curriculares.';
        }
        $rotulo = (int) ($data['materia_rotulo_id'] ?? 0);
        foreach ($this->model->listar() as $row) {
            $outroId = (int) ($row['id'] ?? 0);
            if ($idAtual !== null && $outroId === $idAtual) {
                continue;
            }
            if ((int) ($row['materia_rotulo_id'] ?? 0) === $rotulo) {
                return 'Já existe uma regra de nota para essa área.';
            }
        }
        return null;
    }
}
