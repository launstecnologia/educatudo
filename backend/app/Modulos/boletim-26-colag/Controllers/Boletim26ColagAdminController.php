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
            $filtro['nota_campo'],
            false,
            $filtro['bimestres']
        );

        $this->viewWithLayout('admin', 'admin/boletim-26-colag/index', [
            'title' => 'Boletim - colag',
            'current_page' => 'boletim-26-colag',
            'user' => $this->auth->getUser(),
            'csrf_token' => $this->generateCsrfToken(),
            'publicacao' => (new \App\Modulos\Boletim26Colag\Models\Boletim26ColagLeitura())->publicacaoPortal((int) $painel['ano']),
            'painel' => $painel,
        ]);
    }

    public function publicarPortal(): void
    {
        $ano = (int) ($_POST['ano'] ?? 0);
        if (!$this->validateCsrf((string) ($_POST['_token'] ?? '')) || $ano !== (int) date('Y')) {
            http_response_code(403);
            return;
        }
        $publicacao = [];
        foreach (['aluno', 'pais'] as $perfil) {
            $marcadas = (array) ($_POST[$perfil] ?? []);
            $publicacao[$perfil] = array_values(array_filter(['1', '2', '3', '4', 'boletim', 'boletim_3'],
                static fn (string $opcao): bool => in_array($opcao, $marcadas, true)));
        }
        (new \App\Modulos\Boletim26Colag\Models\Boletim26ColagLeitura())->salvarPublicacaoPortal($ano, $publicacao);
        $this->setFlashMessage('Exibição nos portais atualizada.', 'success');
        $this->redirect('/admin/boletim-26-colag?ano=' . $ano);
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
            $filtro['nota_campo'],
            true,
            $filtro['bimestres']
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
            $filtro['nota_campo'],
            true,
            $filtro['bimestres']
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

    public function parcial(): void
    {
        $filtro = $this->filtroDaRequisicao();
        if ((int) $filtro['aluno_id'] <= 0) {
            $this->responderJson(['html' => '', 'linhas' => []]);
            return;
        }
        $painel = $this->servico->montarPainel(
            $filtro['turma_id'],
            $filtro['aluno_id'],
            $filtro['ano'],
            $filtro['serie'],
            $filtro['bimestre'],
            $filtro['nota_minima'],
            true,
            $filtro['nota_campo'],
            true,
            $filtro['bimestres']
        );
        $relatorios = is_array($painel['relatorios'] ?? null) ? $painel['relatorios'] : [];
        $this->responderJson([
            'html' => $this->htmlDosRelatorios($painel, $relatorios),
            'linhas' => is_array($painel['linhas_planilha'] ?? null) ? $painel['linhas_planilha'] : [],
        ]);
    }

    public function situacao(): void
    {
        $corpo = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($corpo)) {
            $corpo = $_POST;
        }
        if (!$this->validateCsrf((string) ($corpo['_token'] ?? ''))) {
            $this->responderJson(['estado' => 'nenhuma', 'em' => '', 'usuario' => ''], 403);
            return;
        }
        $notaBruta = trim(str_replace(',', '.', (string) ($corpo['nota_minima'] ?? '')));
        $notaCampo = (string) ($corpo['nota_campo'] ?? 'resultado');
        $notaCampo = ($notaCampo === 'media_bim' || $notaCampo === 'media_anual') ? $notaCampo : 'resultado';
        $linhas = is_array($corpo['linhas'] ?? null) ? $corpo['linhas'] : [];
        $exibirSituacao = (string) ($corpo['exibir'] ?? 'notas') === 'boletim' ? 'boletim' : 'notas';
        $bimestresSituacao = $this->bimestresDoFiltro($corpo, $exibirSituacao);
        $situacao = $this->servico->situacaoDasLinhas([
            'ano' => (int) ($corpo['ano'] ?? 0),
            'serie' => (int) ($corpo['serie'] ?? 0),
            'turma_id' => (int) ($corpo['turma_id'] ?? 0),
            'aluno_id' => (int) ($corpo['aluno_id'] ?? 0),
            'bimestre' => count($bimestresSituacao) === 1 ? $bimestresSituacao[0] : 0,
            'nota_campo' => $notaCampo,
            'nota_minima' => ($notaBruta !== '' && is_numeric($notaBruta)) ? (float) $notaBruta : null,
        ], $linhas);
        $this->responderJson($situacao);
    }

    /**
     * @param array<string,mixed> $painel
     * @param list<array<string,mixed>> $relatorios
     */
    private function htmlDosRelatorios(array $painel, array $relatorios): string
    {
        if ($relatorios === []) {
            return '';
        }
        ob_start();
        include __DIR__ . '/../Views/admin/_fragmento.php';
        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function responderJson(array $dados, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @return array{ano:int,serie:int,turma_id:int,aluno_id:int,bimestre:int,nota_campo:string,nota_minima:?float,abrir:bool}
     */
    private function filtroDaRequisicao(): array
    {
        $fonte = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $notaBruta = trim(str_replace(',', '.', (string) ($fonte['nota_minima'] ?? '')));
        $exibir = (string) ($fonte['exibir'] ?? 'notas') === 'boletim' ? 'boletim' : 'notas';
        $bimestres = $this->bimestresDoFiltro($fonte, $exibir);

        return [
            'ano' => (int) ($fonte['ano'] ?? 0),
            'serie' => (int) ($fonte['serie'] ?? 0),
            'turma_id' => (int) ($fonte['turma_id'] ?? 0),
            'aluno_id' => (int) ($fonte['aluno_id'] ?? 0),
            'bimestre' => count($bimestres) === 1 ? $bimestres[0] : 0,
            'bimestres' => $bimestres,
            'exibir' => $exibir,
            'nota_campo' => (($campo = (string) ($fonte['nota_campo'] ?? 'resultado')) === 'media_bim' || $campo === 'media_anual') ? $campo : 'resultado',
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
            'bimestres' => $filtro['bimestres'],
            'exibir' => $filtro['exibir'],
            'nota_campo' => $filtro['nota_campo'],
            'nota_minima' => $filtro['nota_minima'] === null ? '' : number_format($filtro['nota_minima'], 1, ',', ''),
            'abrir' => $abrir ? '1' : '0',
        ];
    }

    /**
     * @param array<string, mixed> $fonte
     * @return list<int>
     */
    private function bimestresDoFiltro(array $fonte, string $exibir): array
    {
        if ($exibir !== 'boletim') {
            $um = (int) ($fonte['bimestre'] ?? 0);

            return ($um >= 1 && $um <= 4) ? [$um] : [1, 2, 3, 4];
        }
        $brutos = $fonte['bimestres'] ?? [];
        if (!is_array($brutos)) {
            $brutos = ($brutos === '' || $brutos === null) ? [] : [$brutos];
        }
        $lista = [];
        foreach ($brutos as $item) {
            $numero = (int) $item;
            if ($numero >= 1 && $numero <= 4) {
                $lista[$numero] = $numero;
            }
        }
        ksort($lista);
        $lista = array_values($lista);

        return $lista === [] ? [1, 2, 3, 4] : $lista;
    }

    private function csvNota(mixed $valor): string
    {
        if (!is_numeric($valor)) {
            return '';
        }

        return number_format((float) $valor, 1, ',', '');
    }
}
