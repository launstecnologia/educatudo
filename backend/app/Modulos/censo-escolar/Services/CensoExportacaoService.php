<?php

namespace App\Modulos\CensoEscolar\Services;

require_once __DIR__ . '/../Models/CensoEdicao.php';
require_once __DIR__ . '/CensoLayoutService.php';
require_once __DIR__ . '/CensoValidacaoService.php';
require_once __DIR__ . '/CensoMapeadorTxt.php';

use App\Modulos\CensoEscolar\Models\CensoEdicao;

/**
 * Snapshot, prévia e geração do arquivo. TXT oficial só com leiaute da edição.
 */
class CensoExportacaoService
{
    private CensoEdicao $model;
    private CensoLayoutService $layouts;
    private CensoValidacaoService $validacao;

    private CensoMapeadorTxt $mapeador;

    public function __construct(
        ?CensoEdicao $model = null,
        ?CensoLayoutService $layouts = null,
        ?CensoValidacaoService $validacao = null,
        ?CensoMapeadorTxt $mapeador = null
    ) {
        $this->model = $model ?? new CensoEdicao();
        $this->layouts = $layouts ?? new CensoLayoutService();
        $this->validacao = $validacao ?? new CensoValidacaoService($this->model, $this->layouts);
        $this->mapeador = $mapeador ?? new CensoMapeadorTxt($this->model);
    }

    public function previa(array $edicao): array
    {
        $layout = $this->layouts->carregar((int) $edicao['ano'], (string) $edicao['etapa_coleta']);
        $edicaoId = (int) $edicao['id'];
        $contagens = [
            'escola' => $this->model->contarCategoria('censo_complementos_escola', $edicaoId),
            'gestores' => $this->model->contarCategoria('censo_complementos_gestor', $edicaoId),
            'turmas' => $this->model->contarCategoria('censo_complementos_turma', $edicaoId),
            'alunos' => $this->model->contarCategoria('censo_complementos_aluno', $edicaoId),
            'profissionais' => $this->model->contarCategoria('censo_complementos_profissional', $edicaoId),
            'matriculas' => $this->model->contarCategoria('censo_matriculas', $edicaoId),
            'vinculos' => $this->model->contarCategoria('censo_vinculos_profissionais', $edicaoId),
        ];
        $gate = $this->validacao->podeGerarTxt($edicao);
        $anteriores = $this->model->exportacoes($edicaoId);
        $ultima = $anteriores[0] ?? null;
        return [
            'layout' => $layout,
            'contagens' => $contagens,
            'validacao' => $this->model->resumoValidacao($edicaoId),
            'pode_gerar' => $gate,
            'exportacao_anterior' => $ultima,
        ];
    }

    /**
     * @return array{success:bool, error?:string, id?:int, caminho?:string}
     */
    public function gerarTxt(array $edicao, int $usuarioId): array
    {
        $gate = $this->validacao->podeGerarTxt($edicao);
        if (empty($gate['ok'])) {
            return ['success' => false, 'error' => $gate['motivo'] ?? 'Não é possível gerar o TXT.'];
        }
        $this->validacao->validar($edicao);
        $atual = $this->model->findById((int) $edicao['id']);
        if ($atual) {
            $edicao = $atual;
        }
        $gate = $this->validacao->podeGerarTxt($edicao);
        if (empty($gate['ok'])) {
            return ['success' => false, 'error' => $gate['motivo'] ?? 'Não é possível gerar o TXT.'];
        }
        $layout = $gate['layout'];
        $registros = $this->mapeador->montar($edicao, $layout);
        $linhas = [];
        foreach ($registros as $item) {
            $linhas[] = $this->layouts->serializarRegistro($layout, (string) $item['tipo'], $item['campos']);
        }
        $linhas[] = (string) ($layout['registro_final'] ?? '99|');
        $quebra = (string) ($layout['quebra_linha'] ?? "\r\n");
        $conteudo = implode($quebra, $linhas);
        if ($conteudo !== '') {
            $conteudo .= $quebra;
        }
        $codificacao = (string) ($layout['codificacao'] ?? 'UTF-8');
        if (strtoupper($codificacao) !== 'UTF-8') {
            $convertido = @iconv('UTF-8', $codificacao . '//IGNORE', $conteudo);
            if (is_string($convertido)) {
                $conteudo = $convertido;
            }
        }

        $versaoSnap = $this->model->proximaVersaoSnapshot((int) $edicao['id']);
        $jsonSnap = json_encode($registros, JSON_UNESCAPED_UNICODE);
        $hashSnap = hash('sha256', (string) $jsonSnap);
        $snapshotId = $this->model->salvarSnapshot([
            'edicao_id' => (int) $edicao['id'],
            'versao' => $versaoSnap,
            'dados_json' => $jsonSnap,
            'hash' => $hashSnap,
            'criado_por' => $usuarioId,
        ]);

        $dir = $this->diretorioArquivos((int) $edicao['id']);
        if ($dir === '') {
            return ['success' => false, 'error' => 'Não foi possível criar a pasta de arquivos do Censo.'];
        }
        $versao = $this->model->proximaVersaoExportacao((int) $edicao['id']);
        $ano = (int) $edicao['ano'];
        $nome = sprintf('mi%d_v%d.txt', $ano, $versao);
        if (strlen($nome) > 20) {
            $nome = sprintf('mi%d.txt', $ano);
        }
        $relativo = $this->caminhoRelativo((int) $edicao['id'], $nome);
        $absoluto = $dir . '/' . $nome;
        if (file_put_contents($absoluto, $conteudo) === false) {
            return ['success' => false, 'error' => 'Falha ao gravar o arquivo TXT.'];
        }
        $hash = hash_file('sha256', $absoluto);
        $tipos = array_values(array_unique(array_map(static fn ($i) => (string) $i['tipo'], $registros)));
        $id = $this->model->salvarExportacao([
            'edicao_id' => (int) $edicao['id'],
            'snapshot_id' => $snapshotId,
            'layout_id' => $edicao['layout_id'] ?? null,
            'versao' => $versao,
            'tipo' => 'migracao',
            'arquivo' => $relativo,
            'nome_original' => $nome,
            'hash_sha256' => $hash ?: '',
            'tamanho_bytes' => (int) filesize($absoluto),
            'total_linhas' => count($linhas),
            'resumo_json' => json_encode(['tipos' => $tipos, 'linhas' => count($linhas)], JSON_UNESCAPED_UNICODE),
            'status' => 'gerado',
            'gerado_por' => $usuarioId,
        ]);

        $ids = $this->mapeador->identificacao($edicao);
        if ($ids !== []) {
            $linhasId = [];
            foreach ($ids as $pessoa) {
                $partes = [];
                for ($i = 1; $i <= 9; $i++) {
                    $partes[] = str_replace('|', ' ', (string) ($pessoa['c' . $i] ?? ''));
                }
                $linhasId[] = implode('|', $partes);
            }
            $conteudoId = implode($quebra, $linhasId) . $quebra;
            if (strtoupper($codificacao) !== 'UTF-8') {
                $convId = @iconv('UTF-8', $codificacao . '//IGNORE', $conteudoId);
                if (is_string($convId)) {
                    $conteudoId = $convId;
                }
            }
            $nomeId = sprintf('id%d_v%d.txt', $ano, $versao);
            file_put_contents($dir . '/' . $nomeId, $conteudoId);
            $this->model->salvarExportacao([
                'edicao_id' => (int) $edicao['id'],
                'snapshot_id' => $snapshotId,
                'layout_id' => $edicao['layout_id'] ?? null,
                'versao' => $versao,
                'tipo' => 'identificacao',
                'arquivo' => $this->caminhoRelativo((int) $edicao['id'], $nomeId),
                'nome_original' => $nomeId,
                'hash_sha256' => hash_file('sha256', $dir . '/' . $nomeId) ?: '',
                'tamanho_bytes' => (int) filesize($dir . '/' . $nomeId),
                'total_linhas' => count($linhasId),
                'resumo_json' => json_encode(['tipo' => 'identificacao', 'linhas' => count($linhasId)], JSON_UNESCAPED_UNICODE),
                'status' => 'gerado',
                'gerado_por' => $usuarioId,
            ]);
        }

        $this->model->atualizar((int) $edicao['id'], ['status' => 'arquivo_gerado']);
        $this->model->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => $usuarioId,
            'acao' => 'gerar_txt',
            'entidade_tipo' => 'exportacao',
            'entidade_id' => $id,
            'dados_novos_json' => json_encode(['arquivo' => $nome, 'hash' => $hash, 'linhas' => count($linhas)], JSON_UNESCAPED_UNICODE),
            'ip' => $this->ip(),
        ]);
        return ['success' => true, 'id' => $id, 'caminho' => $relativo];
    }

    /**
     * @return array{success:bool, error?:string, id?:int, resumo?:array}
     */
    public function importarRetorno(array $edicao, array $arquivo, int $usuarioId, int $exportacaoId = 0): array
    {
        if (in_array((string) ($edicao['status'] ?? ''), CensoEdicao::STATUS_BLOQUEADOS, true)) {
            return ['success' => false, 'error' => 'A edição está fechada.'];
        }
        if ($exportacaoId > 0 && !$this->model->findExportacao($exportacaoId, (int) $edicao['id'])) {
            return ['success' => false, 'error' => 'A exportação informada não pertence a esta edição.'];
        }
        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        $nomeOrig = basename((string) ($arquivo['name'] ?? 'retorno.txt'));
        $nomeOrig = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nomeOrig) ?: 'retorno.txt';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['success' => false, 'error' => 'Arquivo de retorno inválido.'];
        }
        $tamanho = (int) ($arquivo['size'] ?? 0);
        if ($tamanho <= 0 || $tamanho > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'O arquivo de retorno deve ter no máximo 5 MB.'];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $mimesOk = ['text/plain', 'text/csv', 'application/octet-stream', 'text/xml', 'application/xml', 'application/zip'];
        if (!in_array($mime, $mimesOk, true)) {
            return ['success' => false, 'error' => 'Tipo de arquivo não aceito para retorno do Educacenso.'];
        }
        $dir = $this->diretorioArquivos((int) $edicao['id']);
        if ($dir === '') {
            return ['success' => false, 'error' => 'Não foi possível gravar o retorno.'];
        }
        $nome = 'retorno_' . date('Ymd_His') . '_' . $nomeOrig;
        $absoluto = $dir . '/' . $nome;
        if (!move_uploaded_file($tmp, $absoluto)) {
            return ['success' => false, 'error' => 'Falha ao armazenar o arquivo de retorno.'];
        }
        $conteudo = '';
        if ($tamanho <= 1024 * 1024) {
            $conteudo = (string) file_get_contents($absoluto);
        }
        $linhas = preg_split("/\r\n|\n|\r/", $conteudo) ?: [];
        $linhas = array_values(array_filter($linhas, static fn ($l) => trim((string) $l) !== ''));
        $resumo = [
            'linhas' => count($linhas),
            'mime' => $mime,
            'aplicacao' => 'pendente_confirmacao',
            'aviso' => 'Nenhum cadastro foi alterado. Confira o resumo antes de aplicar códigos INEP.',
        ];
        $id = $this->model->salvarRetorno([
            'edicao_id' => (int) $edicao['id'],
            'exportacao_id' => $exportacaoId > 0 ? $exportacaoId : null,
            'arquivo' => $this->caminhoRelativo((int) $edicao['id'], $nome),
            'nome_original' => $nomeOrig,
            'tipo' => 'retorno',
            'hash_sha256' => hash_file('sha256', $absoluto) ?: null,
            'resumo_json' => json_encode($resumo, JSON_UNESCAPED_UNICODE),
            'importado_por' => $usuarioId,
        ]);
        $this->model->atualizar((int) $edicao['id'], ['status' => 'com_pendencias_de_retorno']);
        $this->model->registrarAuditoria([
            'edicao_id' => (int) $edicao['id'],
            'usuario_id' => $usuarioId,
            'acao' => 'importar_retorno',
            'entidade_tipo' => 'retorno',
            'entidade_id' => $id,
            'dados_novos_json' => json_encode($resumo, JSON_UNESCAPED_UNICODE),
            'ip' => $this->ip(),
        ]);
        return ['success' => true, 'id' => $id, 'resumo' => $resumo];
    }

    public function caminhoAbsoluto(string $relativo): ?string
    {
        $relativo = str_replace('\\', '/', $relativo);
        if (str_contains($relativo, '..') || !str_starts_with($relativo, 'storage/files/')) {
            return null;
        }
        $base = dirname(__DIR__, 4);
        $abs = $base . '/' . $relativo;
        $real = realpath($abs);
        $root = realpath($base . '/storage/files');
        if ($real === false || $root === false || !str_starts_with($real, $root)) {
            return null;
        }
        return $real;
    }

    private function coletarDto(array $edicao): array
    {
        $id = (int) $edicao['id'];
        return [
            'escola' => $this->model->listarEntidade('escola', $id),
            'gestores' => $this->model->listarEntidade('gestores', $id),
            'turmas' => $this->model->listarEntidade('turmas', $id),
            'alunos' => $this->model->listarEntidade('alunos', $id),
            'profissionais' => $this->model->listarEntidade('profissionais', $id),
            'matriculas' => $this->model->listarEntidade('matriculas', $id),
            'vinculos' => $this->model->listarEntidade('vinculos', $id),
        ];
    }

    private function diretorioArquivos(int $edicaoId): string
    {
        $slug = defined('TENANT_SLUG') && TENANT_SLUG !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '', TENANT_SLUG) : 'default';
        $base = dirname(__DIR__, 4) . '/storage/files/' . $slug . '/censo/' . $edicaoId;
        if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
            return '';
        }
        return $base;
    }

    private function caminhoRelativo(int $edicaoId, string $nome): string
    {
        $slug = defined('TENANT_SLUG') && TENANT_SLUG !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '', TENANT_SLUG) : 'default';
        return 'storage/files/' . $slug . '/censo/' . $edicaoId . '/' . $nome;
    }

    private function ip(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? substr($ip, 0, 45) : null;
    }
}
