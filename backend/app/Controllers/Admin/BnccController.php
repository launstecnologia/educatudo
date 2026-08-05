<?php

require_once __DIR__ . '/AdminBaseController.php';
require_once __DIR__ . '/../../Models/Bncc/BnccSkill.php';
require_once __DIR__ . '/../../Services/BnccService.php';

/**
 * EducaTudo - BnccController
 * Catálogo de habilidades da BNCC, importação e visão de cobertura curricular.
 */
if (!class_exists('BnccController')) {
class BnccController extends AdminBaseController
{
    public function index(): void
    {
        if (!$this->enforceAdminPermissionKey('bncc', 'visualizar', false)) {
            return;
        }
        $model = new BnccSkill();
        $service = new BnccService($this->db);
        $busca = trim((string) ($_GET['busca'] ?? ''));
        $componente = trim((string) ($_GET['componente'] ?? ''));
        $flash = $this->getFlashMessage();
        $this->viewWithLayout('admin', 'admin/bncc/index', [
            'title' => 'BNCC - EducaTudo',
            'user' => $this->auth->getUser(),
            'current_page' => 'bncc',
            'flash_message' => $flash['message'],
            'flash_type' => $flash['type'],
            'habilidades' => $model->listar($busca, $componente),
            'componentes' => $model->componentes(),
            'total' => $model->total(),
            'busca' => $busca,
            'componente' => $componente,
            'cobertura' => $service->coberturaGeral(),
            'cobertura_componente' => $service->coberturaPorComponente(),
            'pendentes' => $service->pendentes(50),
            'schema_pronto' => $model->tableExists(),
            'csrf_token' => $this->generateCsrfToken(),
        ]);
    }

    public function importar(): void
    {
        if (!$this->enforceAdminPermissionKey('bncc', 'cadastrar', false)) {
            return;
        }
        if (!$this->validateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
            $this->setFlashMessage('Sessão expirada. Tente novamente.', 'error');
            $this->redirect('/admin/bncc');
            return;
        }
        $habilidades = [];
        if (!empty($_FILES['arquivo']['tmp_name']) && is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
            $conteudo = (string) file_get_contents($_FILES['arquivo']['tmp_name']);
            $habilidades = $this->parseImport($conteudo, (string) ($_FILES['arquivo']['name'] ?? ''));
        }
        if (!$habilidades) {
            $this->setFlashMessage('Nenhuma habilidade válida encontrada no arquivo (use JSON ou CSV).', 'error');
            $this->redirect('/admin/bncc');
            return;
        }
        $n = (new BnccSkill())->importar($habilidades);
        $this->setFlashMessage("$n habilidade(s) importada(s)/atualizada(s).", 'success');
        $this->redirect('/admin/bncc');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseImport(string $conteudo, string $nome): array
    {
        $conteudo = trim($conteudo);
        if ($conteudo === '') {
            return [];
        }
        $ext = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
        if ($ext === 'json' || (isset($conteudo[0]) && ($conteudo[0] === '[' || $conteudo[0] === '{'))) {
            $dec = json_decode($conteudo, true);
            if (is_array($dec)) {
                return isset($dec['codigo']) ? [$dec] : array_values(array_filter($dec, 'is_array'));
            }
            return [];
        }
        // CSV: codigo;descricao;etapa;componente;ano_serie;unidade_tematica;objeto_conhecimento
        $linhas = preg_split('/\r\n|\r|\n/', $conteudo) ?: [];
        $out = [];
        $cabecalho = null;
        foreach ($linhas as $i => $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $sep = strpos($linha, ';') !== false ? ';' : ',';
            $cols = str_getcsv($linha, $sep);
            if ($i === 0 && stripos($linha, 'codigo') !== false) {
                $cabecalho = array_map(static fn($c) => strtolower(trim($c)), $cols);
                continue;
            }
            if ($cabecalho) {
                $row = [];
                foreach ($cabecalho as $idx => $key) {
                    $row[$key] = $cols[$idx] ?? '';
                }
                $out[] = $row;
            } else {
                $out[] = [
                    'codigo' => $cols[0] ?? '',
                    'descricao' => $cols[1] ?? '',
                    'etapa' => $cols[2] ?? '',
                    'componente' => $cols[3] ?? '',
                    'ano_serie' => $cols[4] ?? '',
                    'unidade_tematica' => $cols[5] ?? '',
                    'objeto_conhecimento' => $cols[6] ?? '',
                ];
            }
        }
        return $out;
    }
}
}
