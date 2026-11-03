<?php

namespace App\Modulos\LeitorGabarito\Controllers;

use App\Modulos\LeitorGabarito\Services\CodigoCapturaCelular;
use App\Modulos\LeitorGabarito\Services\LeitorGabaritoService;

/**
 * Telas compartilhadas do admin e do professor.
 *
 * @property \App\Core\AuthManager $auth
 */
trait AcoesLeitorGabarito
{
    abstract protected function contexto(): array;

    abstract protected function exigirAcesso(): void;

    public function index(): void
    {
        $this->exigirAcesso();
        $servico = $this->servico();
        $busca = trim((string) ($_GET['q'] ?? ''));
        $pagina = (int) ($_GET['pagina'] ?? 1);
        $lista = $servico->listarProvas($this->professorId(), $busca, $pagina);
        $this->render('index', [
            'title' => 'Leitor de gabarito',
            'busca' => $busca,
            'lista' => $lista,
            'tabelas_prontas' => $servico->tabelasProntas(),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function prova($id): void
    {
        $this->exigirAcesso();
        $provaId = (int) $id;
        $detalhe = $this->servico()->detalharProva($provaId, $this->professorId(), (int) ($_GET['lp'] ?? 1));
        if ($detalhe === null) {
            $this->setFlashMessage('Prova não encontrada.', 'error');
            $this->redirect($this->contexto()['base']);
            return;
        }
        $this->render('prova', [
            'title' => 'Leitor de gabarito',
            'detalhe' => $detalhe,
            'flash' => $this->getFlashMessage(),
            'tabelas_prontas' => $this->servico()->tabelasProntas(),
            'captura_qr' => CodigoCapturaCelular::imagem($provaId, (int) ($this->professorId() ?? 0)),
        ]);
    }

    public function gerar($id): void
    {
        $this->exigirAcesso();
        $provaId = (int) $id;
        if (!$this->csrfOk()) {
            $this->redirect($this->contexto()['base'] . '/provas/' . $provaId);
            return;
        }
        try {
            $resultado = $this->servico()->gerarFolhas(
                $provaId,
                $this->professorId(),
                (int) ($_POST['turma_id'] ?? 0),
                !empty($_POST['substituir'])
            );
            $partes = [];
            if ($resultado['novas'] > 0) {
                $partes[] = $resultado['novas'] . ' folhas novas';
            }
            if ($resultado['atualizadas'] > 0) {
                $partes[] = $resultado['atualizadas'] . ' folhas refeitas';
            }
            if ($partes === [] && $resultado['erros'] === []) {
                $partes[] = 'As folhas desta turma já estavam prontas';
            }
            $tipo = $resultado['erros'] === [] ? 'success' : 'warning';
            $mensagem = implode(', ', $partes) . '.';
            if ($resultado['erros'] !== []) {
                $mensagem .= ' Falhas: ' . implode(' ', array_slice($resultado['erros'], 0, 3));
            }
            $this->setFlashMessage($mensagem, $tipo);
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($this->contexto()['base'] . '/provas/' . $provaId);
    }

    public function bloco($id): void
    {
        $this->exigirAcesso();
        $blocoId = (int) $id;
        $detalhe = $this->servico()->detalharBloco($blocoId, (int) ($_GET['lp'] ?? 1));
        if ($detalhe === null) {
            $this->setFlashMessage('Bloco de prova impressa não encontrado.', 'error');
            $this->redirect($this->contexto()['base']);
            return;
        }
        $ancoraId = (int) $detalhe['ancora_id'];
        $this->render('bloco', [
            'title' => 'Ler gabarito do bloco',
            'detalhe' => $detalhe,
            'flash' => $this->getFlashMessage(),
            'tabelas_prontas' => $this->servico()->tabelasProntas(),
            'captura_qr' => CodigoCapturaCelular::imagem($ancoraId, (int) ($this->professorId() ?? 0), 1),
        ]);
    }

    public function gerarBloco($id): void
    {
        $this->exigirAcesso();
        $blocoId = (int) $id;
        if (!$this->csrfOk()) {
            $this->redirect($this->contexto()['base'] . '/blocos/' . $blocoId);
            return;
        }
        try {
            $resultado = $this->servico()->gerarFolhasDoBloco($blocoId, (int) ($_POST['turma_id'] ?? 0));
            $partes = [];
            if ($resultado['novas'] > 0) {
                $partes[] = $resultado['novas'] . ' folhas novas';
            }
            if ($resultado['atualizadas'] > 0) {
                $partes[] = $resultado['atualizadas'] . ' folhas refeitas';
            }
            if ($partes === [] && $resultado['erros'] === []) {
                $partes[] = 'As folhas do bloco já estavam prontas';
            }
            $mensagem = implode(', ', $partes) . '.';
            if ($resultado['erros'] !== []) {
                $mensagem .= ' Falhas: ' . implode(' ', array_slice($resultado['erros'], 0, 3));
            }
            $this->setFlashMessage($mensagem, $resultado['erros'] === [] ? 'success' : 'warning');
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($this->contexto()['base'] . '/blocos/' . $blocoId);
    }

    public function enviarBloco($id): void
    {
        $this->exigirAcesso();
        $blocoId = (int) $id;
        $volta = $this->contexto()['base'] . '/blocos/' . $blocoId;
        if (!$this->csrfOk()) {
            $this->redirect($volta);
            return;
        }
        $detalhe = $this->servico()->detalharBloco($blocoId, 1);
        if ($detalhe === null) {
            $this->setFlashMessage('Bloco de prova impressa não encontrado.', 'error');
            $this->redirect($this->contexto()['base']);
            return;
        }
        @set_time_limit(180);
        try {
            $arquivos = $this->normalizarArquivos($_FILES['scans'] ?? null);
            $resultado = $this->servico()->receberScans(
                (int) $detalhe['ancora_id'],
                $this->professorId(),
                (int) ($this->auth->getUser()['id'] ?? 0),
                $arquivos,
                1
            );
            $mensagem = $resultado['lidas'] . ' lançadas, ' . $resultado['revisao'] . ' para revisão, ' . $resultado['erros'] . ' com erro.';
            $tipo = ($resultado['erros'] > 0 || $resultado['revisao'] > 0) ? 'warning' : 'success';
            $this->setFlashMessage($mensagem, $tipo);
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($volta);
    }

    public function processarBloco($id): void
    {
        $this->exigirAcesso();
        $blocoId = (int) $id;
        $volta = $this->contexto()['base'] . '/blocos/' . $blocoId;
        if (!$this->csrfOk()) {
            $this->redirect($volta);
            return;
        }
        $detalhe = $this->servico()->detalharBloco($blocoId, 1);
        if ($detalhe === null) {
            $this->setFlashMessage('Bloco de prova impressa não encontrado.', 'error');
            $this->redirect($this->contexto()['base']);
            return;
        }
        @set_time_limit(180);
        try {
            $feitos = $this->servico()->processarPendentesDaProva((int) $detalhe['ancora_id'], $this->professorId(), 8, 1);
            $this->setFlashMessage($feitos > 0 ? $feitos . ' imagens lidas.' : 'Não há imagem na fila.', $feitos > 0 ? 'success' : 'info');
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($volta);
    }

    public function imprimir($id): void
    {
        $this->exigirAcesso();
        $provaId = (int) $id;
        $turmaId = (int) ($_GET['turma_id'] ?? $_POST['turma_id'] ?? 0);
        try {
            $servico = $this->servico();
            $detalhe = $servico->detalharProva($provaId, $this->professorId(), 1);
            if ($detalhe === null) {
                throw new \RuntimeException('Prova não encontrada.');
            }
            $folhas = $servico->folhasParaImpressao($provaId, $this->professorId(), $turmaId);
            $this->view($this->contexto()['view_perfil'] . '/leitor-gabarito/imprimir', [
                'prova' => $detalhe['prova'],
                'folhas' => $folhas,
                'base' => $this->contexto()['base'],
            ]);
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
            $this->redirect($this->contexto()['base'] . '/provas/' . $provaId);
        }
    }

    public function enviar($id): void
    {
        $this->exigirAcesso();
        $provaId = (int) $id;
        if (!$this->csrfOk()) {
            $this->redirect($this->contexto()['base'] . '/provas/' . $provaId);
            return;
        }
        @set_time_limit(180);
        try {
            $arquivos = $this->normalizarArquivos($_FILES['scans'] ?? null);
            $resultado = $this->servico()->receberScans($provaId, $this->professorId(), (int) ($this->auth->getUser()['id'] ?? 0), $arquivos);
            $mensagem = $resultado['lidas'] . ' lançadas, ' . $resultado['revisao'] . ' para revisão, ' . $resultado['erros'] . ' com erro.';
            $tipo = ($resultado['erros'] > 0 || $resultado['revisao'] > 0) ? 'warning' : 'success';
            $this->setFlashMessage($mensagem, $tipo);
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($this->contexto()['base'] . '/provas/' . $provaId);
    }

    public function processar($id): void
    {
        $this->exigirAcesso();
        $provaId = (int) $id;
        if (!$this->csrfOk()) {
            $this->redirect($this->contexto()['base'] . '/provas/' . $provaId);
            return;
        }
        @set_time_limit(180);
        try {
            $feitos = $this->servico()->processarPendentesDaProva($provaId, $this->professorId(), 8);
            $this->setFlashMessage($feitos > 0 ? $feitos . ' imagens lidas.' : 'Não há imagem na fila.', $feitos > 0 ? 'success' : 'info');
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($this->contexto()['base'] . '/provas/' . $provaId);
    }

    public function pngFolha($id): void
    {
        $this->exigirAcesso();
        try {
            $caminho = $this->servico()->caminhoFolha((int) $id, $this->professorId());
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo 'Folha não encontrada.';
            return;
        }
        $this->enviarArquivo($caminho, 'image/png');
    }

    public function revisao($id): void
    {
        $this->exigirAcesso();
        $leitura = $this->servico()->buscarLeitura((int) $id, $this->professorId());
        if ($leitura === null) {
            $this->setFlashMessage('Leitura não encontrada.', 'error');
            $this->redirect($this->contexto()['base']);
            return;
        }
        $marcacoes = json_decode((string) ($leitura['marcacoes_json'] ?? ''), true);
        $this->render('revisao', [
            'title' => 'Revisar leitura',
            'leitura' => $leitura,
            'questoes' => is_array($marcacoes) ? ($marcacoes['questoes'] ?? []) : [],
            'alunos' => $this->servico()->opcoesAluno((int) $leitura['prova_id'], (int) ($leitura['conjunto'] ?? 0)),
            'flash' => $this->getFlashMessage(),
        ]);
    }

    public function imagem($id): void
    {
        $this->exigirAcesso();
        try {
            $caminho = $this->servico()->caminhoScan((int) $id, $this->professorId());
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo 'Imagem não encontrada.';
            return;
        }
        $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
        $tipos = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $this->enviarArquivo($caminho, $tipos[$ext] ?? 'application/octet-stream');
    }

    public function confirmar($id): void
    {
        $this->exigirAcesso();
        $leituraId = (int) $id;
        $leitura = $this->servico()->buscarLeitura($leituraId, $this->professorId());
        $volta = $this->urlDepoisDaLeitura($leitura);
        if (!$this->csrfOk()) {
            $this->redirect($this->contexto()['base'] . '/leituras/' . $leituraId);
            return;
        }
        try {
            $letras = $_POST['letra'] ?? [];
            if (!is_array($letras)) {
                $letras = [];
            }
            $nota = $this->servico()->confirmar(
                $leituraId,
                $this->professorId(),
                (int) ($_POST['aluno_id'] ?? 0),
                $letras
            );
            $this->setFlashMessage('Nota lançada: ' . number_format($nota, 2, ',', '.'), 'success');
            $this->redirect($volta);
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
            $this->redirect($this->contexto()['base'] . '/leituras/' . $leituraId);
        }
    }

    public function reler($id): void
    {
        $this->exigirAcesso();
        $leituraId = (int) $id;
        if (!$this->csrfOk()) {
            $this->redirect($this->contexto()['base'] . '/leituras/' . $leituraId);
            return;
        }
        @set_time_limit(60);
        try {
            $this->servico()->reler($leituraId, $this->professorId());
            $this->setFlashMessage('Imagem lida de novo.', 'success');
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($this->contexto()['base'] . '/leituras/' . $leituraId);
    }

    public function excluir($id): void
    {
        $this->exigirAcesso();
        $leituraId = (int) $id;
        $leitura = $this->servico()->buscarLeitura($leituraId, $this->professorId());
        $volta = $this->urlDepoisDaLeitura($leitura);
        if (!$this->csrfOk()) {
            $this->redirect($volta);
            return;
        }
        try {
            $this->servico()->excluirLeitura($leituraId, $this->professorId());
            $this->setFlashMessage('Leitura excluída. A nota já lançada na prova permanece.', 'success');
        } catch (\Throwable $e) {
            $this->setFlashMessage($this->mensagemSegura($e), 'error');
        }
        $this->redirect($volta);
    }

    /**
     * @param array<string, mixed>|null $leitura
     */
    private function urlDepoisDaLeitura(?array $leitura): string
    {
        if ($leitura === null) {
            return $this->contexto()['base'];
        }
        if ((int) ($leitura['conjunto'] ?? 0) === 1) {
            $blocoId = $this->servico()->blocoImpressoId((int) $leitura['prova_id']);
            if ($blocoId > 0) {
                return $this->contexto()['base'] . '/blocos/' . $blocoId;
            }
        }
        return $this->contexto()['base'] . '/provas/' . (int) $leitura['prova_id'];
    }

    private function mensagemSegura(\Throwable $e): string
    {
        if ($e instanceof \PDOException || !$e instanceof \RuntimeException) {
            error_log('leitor gabarito: ' . $e->getMessage());
            return 'Não foi possível concluir. Tente de novo.';
        }
        return $e->getMessage();
    }

    private function servico(): LeitorGabaritoService
    {
        return new LeitorGabaritoService();
    }

    private function professorId(): ?int
    {
        $id = (int) ($this->contexto()['professor_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function csrfOk(): bool
    {
        if ($this->verifyCsrfToken($_POST['_token'] ?? '')) {
            return true;
        }
        $this->setFlashMessage('Token inválido. Recarregue a página e tente de novo.', 'error');
        return false;
    }

    /**
     * @param array<string, mixed>|null $arquivo
     * @return list<array{name:string,tmp_name:string,error:int,size:int}>
     */
    private function normalizarArquivos($arquivo): array
    {
        if (!is_array($arquivo) || !isset($arquivo['name'])) {
            return [];
        }
        if (!is_array($arquivo['name'])) {
            return [[
                'name' => (string) $arquivo['name'],
                'tmp_name' => (string) ($arquivo['tmp_name'] ?? ''),
                'error' => (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($arquivo['size'] ?? 0),
            ]];
        }
        $lista = [];
        foreach ($arquivo['name'] as $i => $nome) {
            $lista[] = [
                'name' => (string) $nome,
                'tmp_name' => (string) ($arquivo['tmp_name'][$i] ?? ''),
                'error' => (int) ($arquivo['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($arquivo['size'][$i] ?? 0),
            ];
        }
        return $lista;
    }

    private function render(string $view, array $dados): void
    {
        $ctx = $this->contexto();
        $dados['user'] = $this->auth->getUser();
        $dados['current_page'] = 'leitor_gabarito';
        $dados['base'] = $ctx['base'];
        $dados['csrf_token'] = $this->generateCsrfToken();
        $dados['title'] = ($dados['title'] ?? 'Leitor de gabarito') . ' - ' . $ctx['titulo_sufixo'];
        $this->viewWithLayout($ctx['layout'], $ctx['view_perfil'] . '/leitor-gabarito/' . $view, $dados);
    }

    private function enviarArquivo(string $caminho, string $mime): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($caminho));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        readfile($caminho);
        exit;
    }
}
