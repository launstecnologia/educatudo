<?php

namespace App\Dominio\Escola\Services;

use App\Models\Education\ComponenteCurricular;

/**
 * EducaTudo - Service de Componentes Curriculares
 * Valida e monta os dados de criação/edição de um componente curricular.
 */
class ComponenteCurricularService
{
    private ComponenteCurricular $componenteCurricular;

    public function __construct()
    {
        $this->componenteCurricular = new ComponenteCurricular();
    }

    /**
     * Valida e cria um componente curricular.
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criar(array $input): array
    {
        $data = $this->normalizar($input);

        $erro = $this->validar($data);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->componenteCurricular->nameExists($data['nome'])) {
            return ['success' => false, 'error' => 'Componente Curricular já cadastrado'];
        }

        if ($this->componenteCurricular->codigoExists($data['codigo'])) {
            return ['success' => false, 'error' => 'Código do componente curricular já cadastrado'];
        }

        $id = $this->componenteCurricular->create($data);
        if (!empty($data['pai_id'])) {
            $this->componenteCurricular->marcarPaiComoRotulo((int) $data['pai_id']);
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * Valida e atualiza um componente curricular existente.
     * @return array{success: bool, error?: string}
     */
    public function atualizar(int $id, array $input): array
    {
        if (!$this->componenteCurricular->exists($id)) {
            return ['success' => false, 'error' => 'Componente curricular não encontrado'];
        }

        $data = $this->normalizar($input);

        $erro = $this->validar($data, $id);
        if ($erro !== null) {
            return ['success' => false, 'error' => $erro];
        }

        if ($this->componenteCurricular->nameExists($data['nome'], $id)) {
            return ['success' => false, 'error' => 'Componente Curricular já cadastrado'];
        }

        if ($this->componenteCurricular->codigoExists($data['codigo'], $id)) {
            return ['success' => false, 'error' => 'Código do componente curricular já cadastrado'];
        }

        $this->componenteCurricular->update($id, $data);
        if (!empty($data['pai_id'])) {
            $this->componenteCurricular->marcarPaiComoRotulo((int) $data['pai_id']);
        }

        return ['success' => true];
    }

    /**
     * Normaliza o input bruto (ex.: $_POST) para o formato esperado pelo Model.
     */
    private function normalizar(array $input): array
    {
        return [
            'nome' => trim((string) ($input['nome'] ?? '')),
            'codigo' => strtoupper(trim((string) ($input['codigo'] ?? ''))),
            'sigla' => strtoupper(trim((string) ($input['sigla'] ?? ''))),
            'area_conhecimento' => trim((string) ($input['area_conhecimento'] ?? '')),
            'tipo' => trim((string) ($input['tipo'] ?? '')),
            'etapa_infantil' => $this->normalizarEtapa($input['etapa_infantil'] ?? ''),
            'etapa_fund_i' => $this->normalizarEtapa($input['etapa_fund_i'] ?? ''),
            'etapa_fund_ii' => $this->normalizarEtapa($input['etapa_fund_ii'] ?? ''),
            'etapa_medio' => $this->normalizarEtapa($input['etapa_medio'] ?? ''),
            'descricao' => trim((string) ($input['descricao'] ?? '')),
            'cor' => trim((string) ($input['cor'] ?? '')),
            'ordem' => (int) ($input['ordem'] ?? 0),
            'permite_avaliacao' => !empty($input['permite_avaliacao']) ? 1 : 0,
            'permite_frequencia' => !empty($input['permite_frequencia']) ? 1 : 0,
            'permite_plano_aula' => !empty($input['permite_plano_aula']) ? 1 : 0,
            'permite_diario' => !empty($input['permite_diario']) ? 1 : 0,
            'ativo' => !empty($input['ativo']) ? 1 : 0,
            'pai_id' => (int) ($input['pai_id'] ?? 0),
        ];
    }

    /**
     * @return string|null Mensagem de erro, ou null se válido.
     */
    private function validar(array $data, ?int $idAtual = null): ?string
    {
        if ($data['nome'] === '') {
            return 'Componente Curricular é obrigatório';
        }
        if (mb_strlen($data['nome']) > 100) {
            return 'Componente Curricular deve ter no máximo 100 caracteres';
        }

        if ($data['codigo'] === '') {
            return 'Código é obrigatório';
        }
        if (mb_strlen($data['codigo']) > 20) {
            return 'Código deve ter no máximo 20 caracteres';
        }

        if (mb_strlen($data['sigla']) > 10) {
            return 'Sigla deve ter no máximo 10 caracteres';
        }

        if (!isset(ComponenteCurricular::AREAS_CONHECIMENTO[$data['area_conhecimento']])) {
            return 'Área do Conhecimento inválida';
        }

        if (!isset(ComponenteCurricular::TIPOS[$data['tipo']])) {
            return 'Tipo/categoria inválido';
        }

        if ($data['cor'] !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['cor'])) {
            return 'Cor inválida — use o formato hexadecimal (#RRGGBB)';
        }

        $paiId = (int) ($data['pai_id'] ?? 0);
        if ($paiId > 0) {
            if (!$this->componenteCurricular->exists($paiId)) {
                return 'Unidade Curricular inválida';
            }
            $pai = $this->componenteCurricular->findById($paiId);
            if (is_array($pai) && (int) ($pai['pai_id'] ?? 0) > 0) {
                return 'A unidade curricular precisa ser um componente raiz (um nível só)';
            }
            $filhosDoAtual = $this->componenteCurricular->idsComFilhos();
            $idAtual = (int) ($idAtual ?? 0);
            if ($idAtual > 0 && $paiId === $idAtual) {
                return 'Um componente não pode ser pai de si mesmo';
            }
            if ($idAtual > 0 && isset($filhosDoAtual[$idAtual])) {
                return 'Quem já é unidade curricular (tem desdobramentos) não pode vincular a outra';
            }
        }

        return null;
    }

    /**
     * Normaliza o valor de uma etapa de ensino, caindo em "não se aplica"
     * quando vazio ou fora do catálogo — nunca deixa o form travar por causa
     * dessa parte opcional do cadastro.
     */
    private function normalizarEtapa($valor): string
    {
        $valor = trim((string) $valor);
        return isset(ComponenteCurricular::ETAPAS[$valor]) ? $valor : 'nao_aplica';
    }

    /**
     * Restringe a árvore da listagem pelos filtros da tela (nome, sigla, área e ciclo).
     *
     * @param list<array<string, mixed>> $itens
     * @param array<string, mixed> $entrada
     * @return array{itens: list<array<string, mixed>>, filtros: array{nome: string, sigla: string, area: string, etapa: string}}
     */
    public function aplicarFiltroListagem(array $itens, array $entrada): array
    {
        $filtros = [
            'nome' => $this->textoFiltro($entrada['nome'] ?? '', 100),
            'sigla' => $this->textoFiltro($entrada['sigla'] ?? '', 20),
            'area' => trim((string) ($entrada['area'] ?? '')),
            'etapa' => trim((string) ($entrada['etapa'] ?? '')),
        ];
        if (!isset(ComponenteCurricular::AREAS_CONHECIMENTO[$filtros['area']])) {
            $filtros['area'] = '';
        }
        $ciclos = ['etapa_infantil', 'etapa_fund_i', 'etapa_fund_ii', 'etapa_medio'];
        if (!in_array($filtros['etapa'], $ciclos, true)) {
            $filtros['etapa'] = '';
        }

        if ($filtros['nome'] === '' && $filtros['sigla'] === '' && $filtros['area'] === '' && $filtros['etapa'] === '') {
            return ['itens' => $itens, 'filtros' => $filtros];
        }

        $saida = [];
        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($filtros['nome'] !== '' && mb_stripos((string) ($item['nome'] ?? ''), $filtros['nome']) === false) {
                continue;
            }
            if ($filtros['sigla'] !== '' && mb_stripos((string) ($item['sigla'] ?? ''), $filtros['sigla']) === false) {
                continue;
            }
            if ($filtros['area'] !== '' && (string) ($item['area_conhecimento'] ?? '') !== $filtros['area']) {
                continue;
            }
            if ($filtros['etapa'] !== '') {
                $valorEtapa = (string) ($item[$filtros['etapa']] ?? 'nao_aplica');
                if ($valorEtapa === '' || $valorEtapa === 'nao_aplica') {
                    continue;
                }
            }
            $saida[] = $item;
        }

        return ['itens' => $saida, 'filtros' => $filtros];
    }

    private function textoFiltro($valor, int $maximo): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }
        if (mb_strlen($valor) > $maximo) {
            $valor = mb_substr($valor, 0, $maximo);
        }
        return $valor;
    }
}

if (!class_exists('ComponenteCurricularService', false) && class_exists(__NAMESPACE__ . '\\ComponenteCurricularService', false)) {
    class_alias(__NAMESPACE__ . '\\ComponenteCurricularService', 'ComponenteCurricularService');
}
