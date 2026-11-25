<?php

namespace App\Modulos\Boletim26Colag\Controllers;

require_once __DIR__ . '/../Models/Boletim26ColagLeitura.php';
require_once __DIR__ . '/../Services/Boletim26ColagService.php';

use App\Core\AuthManager;
use App\Core\BaseController;
use App\Modulos\Boletim26Colag\Services\Boletim26ColagService;

/**
 * Tela do demonstrativo fixo do Colag. Escola que não é o Colag volta ao painel.
 */
class Boletim26ColagAdminController extends BaseController
{
    private AuthManager $auth;

    private Boletim26ColagService $servico;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthManager();
        $this->servico = new Boletim26ColagService();
        if (!$this->auth->isLoggedIn()) {
            $this->redirect('/');
            exit;
        }
        $user = $this->auth->getUser();
        if (!$user || !in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
            $this->redirect('/admin/dashboard');
            exit;
        }
        if (!Boletim26ColagService::ativo()) {
            $this->redirect('/admin/dashboard');
            exit;
        }
    }

    public function index(): void
    {
        $filtro = $this->filtroDaRequisicao();
        $painel = $this->servico->montarPainel(
            $filtro['turma_id'],
            $filtro['aluno_id'],
            $filtro['ano'],
            $filtro['serie'],
            $filtro['bimestre'],
            $filtro['nota_minima'],
            $filtro['abrir'],
            $filtro['nota_campo']
        );

        $this->viewWithLayout('admin', 'admin/boletim-26-colag/index', [
            'title' => 'Boletim - colag',
            'current_page' => 'boletim-26-colag',
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'painel' => $painel,
        ]);
    }

    public function homologar(): void
    {
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? ''))) {
            $this->redirect('/admin/boletim-26-colag');
        }
        $filtro = $this->filtroDaRequisicao();
        $filtro['abrir'] = true;
        $painel = $this->servico->montarPainel(
            $filtro['turma_id'],
            $filtro['aluno_id'],
            $filtro['ano'],
            $filtro['serie'],
            $filtro['bimestre'],
            $filtro['nota_minima'],
            true,
            $filtro['nota_campo']
        );
        $usuario = $this->auth->getUser();
        $this->servico->homologar(
            [
                'ano' => (int) ($painel['ano'] ?? $filtro['ano']),
                'serie' => (int) ($painel['serie'] ?? 0),
                'turma_id' => (int) ($painel['turma_id'] ?? 0),
                'aluno_id' => (int) ($painel['aluno_id'] ?? 0),
                'bimestre' => (int) ($painel['bimestre'] ?? 0),
                'nota_campo' => (string) ($painel['nota_campo'] ?? 'resultado'),
                'nota_minima' => $painel['nota_minima'] ?? null,
            ],
            is_array($painel['relatorios'] ?? null) ? $painel['relatorios'] : [],
            (int) ($usuario['id'] ?? 0),
            (string) ($usuario['nome'] ?? '')
        );
        $this->redirect('/admin/boletim-26-colag?' . http_build_query($this->queryDoFiltro($filtro, true)));
    }

    public function exportar(): void
    {
        $filtro = $this->filtroDaRequisicao();
        $painel = $this->servico->montarPainel(
            $filtro['turma_id'],
            $filtro['aluno_id'],
            $filtro['ano'],
            $filtro['serie'],
            $filtro['bimestre'],
            $filtro['nota_minima'],
            true,
            $filtro['nota_campo']
        );
        $linhas = is_array($painel['linhas_planilha'] ?? null) ? $painel['linhas_planilha'] : [];
        $nome = 'boletim-colag-' . (int) ($painel['ano'] ?? date('Y')) . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $saida = fopen('php://output', 'w');
        if ($saida === false) {
            return;
        }
        fprintf($saida, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($saida, ['Aluno', 'Turma', 'Bimestre', 'Bloco', 'Matéria', 'Méd. Sem', 'P. Bim', 'Jor. Aluno', 'Média', 'ENAC', 'Méd. Bim', 'Rec. Sem', 'Res. Final', 'Faltas'], ';');
        foreach ($linhas as $linha) {
            fputcsv($saida, [
                $linha['aluno'] ?? '',
                $linha['turma'] ?? '',
                $linha['bimestre'] ?? '',
                $linha['bloco'] ?? '',
                $linha['materia'] ?? '',
                $this->csvNota($linha['media_sem'] ?? null),
                $this->csvNota($linha['prova_bim'] ?? null),
                $this->csvNota($linha['jornada'] ?? null),
                $this->csvNota($linha['media'] ?? null),
                $this->csvNota($linha['enac'] ?? null),
                $this->csvNota($linha['media_bim'] ?? null),
                $this->csvNota($linha['rec'] ?? null),
                $this->csvNota($linha['resultado'] ?? null),
                $linha['faltas'] ?? '',
            ], ';');
        }
        fclose($saida);
        exit;
    }

    /**
     * @return array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float,abrir:bool}
     */
    private function filtroDaRequisicao(): array
    {
        $fonte = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $notaBruta = trim(str_replace(',', '.', (string) ($fonte['nota_minima'] ?? '')));

        return [
            'ano' => (int) ($fonte['ano'] ?? 0),
            'serie' => (int) ($fonte['serie'] ?? 0),
            'turma_id' => (int) ($fonte['turma_id'] ?? 0),
            'aluno_id' => (int) ($fonte['aluno_id'] ?? 0),
            'bimestre' => (int) ($fonte['bimestre'] ?? 0),
            'nota_campo' => (string) ($fonte['nota_campo'] ?? 'resultado') === 'media_bim' ? 'media_bim' : 'resultado',
            'nota_minima' => ($notaBruta !== '' && is_numeric($notaBruta)) ? (float) $notaBruta : null,
            'abrir' => (string) ($fonte['abrir'] ?? '') === '1',
        ];
    }

    /**
     * @param array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float,abrir:bool} $filtro
     * @return array<string, scalar>
     */
    private function queryDoFiltro(array $filtro, bool $abrir): array
    {
        return [
            'ano' => $filtro['ano'],
            'serie' => $filtro['serie'],
            'turma_id' => $filtro['turma_id'],
            'aluno_id' => $filtro['aluno_id'],
            'bimestre' => $filtro['bimestre'],
            'nota_campo' => $filtro['nota_campo'],
            'nota_minima' => $filtro['nota_minima'] === null ? '' : number_format($filtro['nota_minima'], 1, ',', ''),
            'abrir' => $abrir ? '1' : '0',
        ];
    }

    private function csvNota(mixed $valor): string
    {
        if (!is_numeric($valor)) {
            return '';
        }

        return number_format((float) $valor, 1, ',', '');
    }
}
