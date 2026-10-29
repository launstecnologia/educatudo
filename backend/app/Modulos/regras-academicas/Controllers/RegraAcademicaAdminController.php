<?php

namespace App\Modulos\RegrasAcademicas\Controllers;

use App\Modulos\RegrasAcademicas\Services\RegraAcademicaService;
use Throwable;

/**
 * EducaTudo - Cadastro de Regras de Aprovação (admin)
 */


if (!class_exists(__NAMESPACE__ . '\\RegraAcademicaAdminController', false)) {
class RegraAcademicaAdminController extends \App\Controllers\Admin\AdminBaseController
{
    private function service(): RegraAcademicaService
    {
        return new RegraAcademicaService();
    }

    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'visualizar', false)) {
            return;
        }

        $model = $this->service()->model();
        $anoAtual = (int) date('Y');
        $anoInformado = array_key_exists('ano_letivo', $_GET);
        $anoPedido = (int) ($_GET['ano_letivo'] ?? 0);
        if (!$anoInformado) {
            $anoFiltro = $anoAtual;
        } elseif ($anoPedido >= 2000 && $anoPedido <= 2100) {
            $anoFiltro = $anoPedido;
        } else {
            $anoFiltro = 0;
        }
        $filtros = [
            'ano_letivo' => $anoFiltro,
            'curso_id' => (int) ($_GET['curso_id'] ?? 0),
            'serie_id' => (int) ($_GET['serie_id'] ?? 0),
        ];
        $consulta = $filtros;
        if ($anoFiltro <= 0) {
            unset($consulta['ano_letivo']);
        }
        $flash = $this->getFlashMessage();
        $catalogos = $this->catalogos();
        $anosLetivos = $catalogos['anos_letivos'];
        if (!in_array($anoAtual, $anosLetivos, true)) {
            $anosLetivos[] = $anoAtual;
            rsort($anosLetivos);
        }

        $this->viewWithLayout('admin', 'admin/regras-academicas/index', [
            'title' => 'Regras de Aprovação - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'regras-academicas',
            'itens' => $model->schemaPronto() ? $model->getAll(array_filter($consulta)) : [],
            'schema_pronto' => $model->schemaPronto(),
            'filtros' => $filtros,
            'ano_atual' => $anoAtual,
            'cursos' => $catalogos['cursos'],
            'series' => $catalogos['series'],
            'anos_letivos' => $anosLetivos,
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    public function nova(): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'cadastrar', false)) {
            return;
        }
        $this->renderFormulario(null);
    }

    public function editar($id): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'alterar', false)) {
            return;
        }
        $item = $this->service()->model()->findById((int) $id);
        if (!$item) {
            $this->setFlashMessage('Regra de aprovação não encontrada.', 'error');
            $this->redirect('/admin/regras-academicas');
            return;
        }
        $this->renderFormulario($item);
    }

    public function salvar(): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'cadastrar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/regras-academicas/nova');
            return;
        }
        $user = $this->auth->getUser();
        $result = $this->service()->criar($_POST, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''));
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível salvar.', 'error');
            $this->redirect('/admin/regras-academicas/nova');
            return;
        }
        $this->setFlashMessage('Regra de aprovação cadastrada.' . $this->avisoCards((int) ($_POST['ano_letivo'] ?? 0)), 'success');
        $this->redirect('/admin/regras-academicas/' . (int) ($result['id'] ?? 0) . '/editar');
    }

    public function atualizar($id): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'alterar', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/regras-academicas/' . (int) $id . '/editar');
            return;
        }
        $user = $this->auth->getUser();
        $result = $this->service()->atualizar((int) $id, $_POST, (int) ($user['id'] ?? 0), (string) ($user['nome'] ?? ''));
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível atualizar.', 'error');
            $this->redirect('/admin/regras-academicas/' . (int) $id . '/editar');
            return;
        }
        $salvo = $this->service()->model()->findById((int) $id);
        $nomeSalvo = trim((string) ($salvo['nome'] ?? ''));
        $anoSalvo = (int) ($salvo['ano_letivo'] ?? 0);
        $anoTexto = $anoSalvo > 0 ? (string) $anoSalvo : 'qualquer ano';
        $this->setFlashMessage('Regra salva: ' . $nomeSalvo . ' · ano letivo ' . $anoTexto . '.' . $this->avisoCards($anoSalvo), 'success');
        $this->redirect('/admin/regras-academicas/' . (int) $id . '/editar');
    }

    public function excluir($id): void
    {
        if (!$this->enforceAdminPermissionKey('regras_academicas', 'excluir', false)) {
            return;
        }
        if (!$this->verifyCsrfToken($_POST['_token'] ?? '')) {
            $this->setFlashMessage('Token inválido. Tente novamente.', 'error');
            $this->redirect('/admin/regras-academicas');
            return;
        }
        $result = $this->service()->excluir((int) $id);
        if (!$result['success']) {
            $this->setFlashMessage($result['error'] ?? 'Não foi possível excluir.', 'error');
            $this->redirect('/admin/regras-academicas');
            return;
        }
        $this->setFlashMessage('Regra de aprovação excluída.', 'success');
        $this->redirect('/admin/regras-academicas');
    }

    /**
     * @param array<string,mixed>|null $item
     */
    private function renderFormulario(?array $item): void
    {
        $catalogos = $this->catalogos();
        $flash = $this->getFlashMessage();
        $historico = [];
        $model = $this->service()->model();
        if ($item && !empty($item['id'])) {
            $historico = $model->listarHistorico((int) $item['id']);
            $item['materias_ids'] = $model->listarMateriasIds((int) $item['id']);
            $item['series_ids'] = $model->listarSeriesIds((int) $item['id']);
            if ($item['series_ids'] === [] && (int) ($item['serie_id'] ?? 0) > 0) {
                $item['series_ids'] = [(int) $item['serie_id']];
            }
        }
        $this->viewWithLayout('admin', 'admin/regras-academicas/form', [
            'title' => ($item ? 'Editar' : 'Nova') . ' regra de aprovação - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'regras-academicas',
            'item' => $item,
            'historico' => $historico,
            'cursos' => $catalogos['cursos'],
            'series' => $catalogos['series'],
            'anos_letivos' => $catalogos['anos_letivos'],
            'cards_por_ano' => $this->cardsPorAno($this->anosDosCards($catalogos['anos_letivos'], $item)),
            'recuperacao_pode_cadastrar' => $this->podeRecuperacao('cadastrar'),
            'recuperacao_pode_alterar' => $this->podeRecuperacao('alterar'),
            'recuperacao_pode_excluir' => $this->podeRecuperacao('excluir'),
            'boletim_pronto' => $model->boletimPronto(),
            'materias' => $this->materiasDaRegra(),
            'csrf_token' => $this->generateCsrfToken(),
            'flash_status' => $flash['type'] === 'success' ? 'success' : ($flash['message'] ? 'error' : ''),
            'flash_message' => $flash['message'] ?? '',
        ]);
    }

    /**
     * @param list<int> $anos
     * @param array<string,mixed>|null $item
     * @return list<int>
     */
    private function anosDosCards(array $anos, ?array $item): array
    {
        $anoItem = (int) ($item['ano_letivo'] ?? 0);
        $lista = array_map('intval', $anos);
        if ($anoItem > 0 && !in_array($anoItem, $lista, true)) {
            $lista[] = $anoItem;
        }
        return $lista;
    }

    /**
     * @param list<int> $anos
     * @return array<int, array{tipo:string,campo:string,rotulos:list<string>}>
     */
    private function cardsPorAno(array $anos): array
    {
        $cards = [];
        foreach ($anos as $ano) {
            $ano = (int) $ano;
            if ($ano <= 0) {
                continue;
            }
            $info = \App\Core\PeriodoLetivo::doAno($ano);
            $cards[$ano] = [
                'tipo' => (string) ($info['tipo'] ?? 'bimestre'),
                'campo' => (string) ($info['rotulo_campo'] ?? 'Bimestre'),
                'rotulos' => array_values(is_array($info['rotulos'] ?? null) ? $info['rotulos'] : []),
            ];
        }
        return $cards;
    }

    private function avisoCards(int $ano): string
    {
        if ($ano <= 0) {
            return '';
        }
        $info = \App\Core\PeriodoLetivo::doAno($ano);
        $quantidade = (int) ($info['quantidade'] ?? 0);
        $campo = mb_strtolower((string) ($info['rotulo_campo_plural'] ?? 'períodos'));
        if ($quantidade < 1) {
            return '';
        }
        return ' Os ' . $quantidade . ' ' . $campo . ' deste ano já aparecem como cards de avaliação em Eventos Avaliativos.';
    }

    /**
     * @return list<array{id:int,nome:string,eh_rotulo:bool}>
     */
    private function materiasDaRegra(): array
    {
        $saida = [];
        try {
            foreach ((new \App\Dominio\Escola\Models\ComponenteCurricular())->getOficiaisParaMatriz(true) as $componente) {
                $id = (int) ($componente['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $saida[] = [
                    'id' => $id,
                    'nome' => (string) ($componente['nome'] ?? ''),
                    'eh_rotulo' => !empty($componente['eh_rotulo']),
                ];
            }
        } catch (Throwable $e) {
            return [];
        }
        usort($saida, static fn (array $a, array $b): int => strcmp($a['nome'], $b['nome']));
        return $saida;
    }

    private function podeRecuperacao(string $acao): bool
    {
        $user = $this->auth->getUser();
        $permissions = \App\Core\AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user ?? []);
        return \App\Core\AdminPermissionMatrix::can($permissions, 'regras_academicas', $acao);
    }

    /**
     * @return array{cursos:list,series:list,anos_letivos:list}
     */
    private function catalogos(): array
    {
        $classRoom = new \App\Models\Education\ClassRoom();
        $anos = [];
        try {
            foreach ($classRoom->getAnosLetivoNovo() ?: [] as $a) {
                $ano = (int) ($a['ano'] ?? 0);
                if ($ano > 0) {
                    $anos[$ano] = $ano;
                }
            }
        } catch (Throwable $e) {
            // tabela ano_letivo pode não existir
        }
        try {
            foreach ($classRoom->getAnosLetivos() ?: [] as $a) {
                $ano = (int) ($a['ano_letivo'] ?? $a['ano'] ?? 0);
                if ($ano > 0) {
                    $anos[$ano] = $ano;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        if ($anos === []) {
            $anos[(int) date('Y')] = (int) date('Y');
        }
        krsort($anos);

        return [
            'cursos' => $classRoom->getCursosNovo() ?: [],
            'series' => $classRoom->getSeriesNovo() ?: [],
            'anos_letivos' => array_values($anos),
        ];
    }
}
}

if (!class_exists('RegraAcademicaAdminController', false) && class_exists(__NAMESPACE__ . '\\RegraAcademicaAdminController', false)) {
    class_alias(__NAMESPACE__ . '\\RegraAcademicaAdminController', 'RegraAcademicaAdminController');
}
