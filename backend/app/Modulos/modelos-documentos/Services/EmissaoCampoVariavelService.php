<?php

namespace App\Modulos\ModelosDocumentos\Services;

require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/ModeloDocumentoService.php';
require_once __DIR__ . '/../../../Services/DeclarationService.php';

use App\Services\DeclarationService;
use Database;

/**
 * Emite um modelo com campos preenchidos na hora (um aluno ou a turma inteira).
 */
class EmissaoCampoVariavelService
{
    private Database $db;
    private ModeloDocumentoService $modelos;
    private DeclarationService $declaracoes;
    /** @var array{aluno_id:int,titulo:string,nome:string,config:array<string,mixed>|null,usuario_id:?int,usuario_nome:string}|null */
    private ?array $arquivoAlunoPdf = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->modelos = new ModeloDocumentoService($this->db);
        $this->declaracoes = new DeclarationService($this->db);
    }

    /**
     * @param array<string,mixed> $valores
     * @param array<string,mixed>|null $config
     * @param array<string,mixed>|null $user
     */
    public function enviarAluno(int $alunoId, int $modeloId, array $valores, ?array $config, ?array $user): void
    {
        $modelo = $this->modeloEmitivel($modeloId);
        $aluno = $this->declaracoes->getAluno($alunoId);
        if (!$aluno) {
            throw new \InvalidArgumentException('Aluno não encontrado.');
        }
        $html = $this->htmlDoAluno($modelo, $aluno, $valores, $config, $user);
        $slug = $this->slug((string) ($aluno['nome'] ?? 'aluno'), $alunoId);
        $nome = preg_replace('/[^a-z0-9_-]+/i', '_', (string) ($modelo['codigo'] ?? 'documento')) ?: 'documento';
        $arquivo = $nome . '_' . $slug . '_' . date('Ymd_His') . '.pdf';
        $this->arquivoAlunoPdf = [
            'aluno_id' => $alunoId,
            'titulo' => (string) ($modelo['nome'] ?? 'Documento'),
            'nome' => $arquivo,
            'config' => $config,
            'usuario_id' => (int) ($user['id'] ?? 0) ?: null,
            'usuario_nome' => (string) ($user['nome'] ?? ''),
        ];
        $this->enviarPdf($html, $arquivo, $modelo);
    }

    /**
     * @param array<string,mixed> $valores
     * @param array<string,mixed>|null $config
     * @param array<string,mixed>|null $user
     */
    public function enviarLote(int $modeloId, int $turmaId, array $valores, ?array $config, ?array $user): void
    {
        $modelo = $this->modeloEmitivel($modeloId);
        if ($turmaId <= 0) {
            throw new \InvalidArgumentException('Escolha a turma.');
        }
        $ids = $this->idsAlunosDaTurma($turmaId);
        if ($ids === []) {
            throw new \InvalidArgumentException('Esta turma não tem alunos com matrícula ativa.');
        }
        if (count($ids) > 120) {
            throw new \InvalidArgumentException('A turma tem mais de 120 alunos. Divida a emissão.');
        }
        $this->modelos->mesclarCamposVariaveis([], $this->modelos->camposDoModelo($modelo), $valores);
        $partes = [];
        foreach ($ids as $alunoId) {
            $aluno = $this->declaracoes->getAluno($alunoId);
            if (!$aluno) {
                continue;
            }
            $partes[] = $this->htmlDoAluno($modelo, $aluno, $valores, $config, $user);
        }
        if ($partes === []) {
            throw new \InvalidArgumentException('Nenhum aluno desta turma pôde ser emitido.');
        }
        $nome = preg_replace('/[^a-z0-9_-]+/i', '_', (string) ($modelo['codigo'] ?? 'documento')) ?: 'documento';
        $this->enviarPdf($this->juntarHtml($partes), $nome . '_lote_' . date('Ymd_His') . '.pdf', $modelo);
    }

    /**
     * Monta o PDF e devolve o binário, sem enviar a resposta HTTP.
     *
     * @param list<int> $alunoIds
     * @param array<string,mixed> $valores
     * @param array<string,mixed>|null $config
     * @param array<string,mixed>|null $user
     * @return array{binario:string,modelo:array<string,mixed>,total:int}
     */
    public function montarPdf(int $modeloId, array $alunoIds, array $valores, ?array $config, ?array $user): array
    {
        $modelo = $this->modeloAtivo($modeloId);
        $ids = [];
        foreach ($alunoIds as $alunoId) {
            $id = (int) $alunoId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            throw new \InvalidArgumentException('Nenhum aluno neste filtro.');
        }
        if (count($ids) > 120) {
            throw new \InvalidArgumentException('O filtro tem mais de 120 alunos. Escolha uma turma ou um aluno.');
        }
        $campos = $this->modelos->camposDoModelo($modelo);
        if ($campos !== []) {
            $this->modelos->mesclarCamposVariaveis([], $campos, $valores);
        }
        $partes = [];
        foreach ($ids as $alunoId) {
            $aluno = $this->declaracoes->getAluno($alunoId);
            if (!$aluno) {
                continue;
            }
            $partes[] = $this->htmlDoAluno($modelo, $aluno, $valores, $config, $user);
        }
        if ($partes === []) {
            throw new \InvalidArgumentException('Nenhum aluno deste filtro pôde ser emitido.');
        }
        $html = count($partes) === 1 ? $partes[0] : $this->juntarHtml($partes);
        return [
            'binario' => $this->binarioPdf($html, $modelo),
            'modelo' => $modelo,
            'total' => count($partes),
        ];
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function listarTurmas(): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC LIMIT 300'
            ) ?: [];
        } catch (\Throwable $e) {
            try {
                $rows = $this->db->fetchAll('SELECT id, nome FROM turmas ORDER BY nome ASC LIMIT 300') ?: [];
            } catch (\Throwable $e2) {
                return [];
            }
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = ['id' => $id, 'nome' => (string) ($row['nome'] ?? ('Turma ' . $id))];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $modelo
     * @param array<string,mixed> $aluno
     * @param array<string,mixed> $valores
     * @param array<string,mixed>|null $config
     * @param array<string,mixed>|null $user
     */
    private function htmlDoAluno(array $modelo, array $aluno, array $valores, ?array $config, ?array $user): string
    {
        $alunoId = (int) ($aluno['id'] ?? 0);
        $unidade = $this->declaracoes->getUnidadeForAluno($aluno);
        $campos = $this->modelos->camposDoModelo($modelo);
        $this->modelos->mesclarCamposVariaveis([], $campos, $valores);
        $userId = isset($user['id']) ? (int) $user['id'] : null;
        $numero = $this->declaracoes->registrarEmissao(
            $alunoId,
            isset($unidade['id']) ? (int) $unidade['id'] : null,
            'modelo',
            [
                'modelo_id' => (int) ($modelo['id'] ?? 0),
                'codigo' => (string) ($modelo['codigo'] ?? ''),
                'campos' => $valores,
            ],
            $userId,
            isset($user['nome']) ? (string) $user['nome'] : null
        );
        $viewData = [
            'tipo' => 'modelo',
            'titulo' => (string) ($modelo['nome'] ?? 'Documento'),
            'dados' => [
                'aluno' => $aluno,
                'unidade' => $unidade ?: [],
                'matricula' => $this->declaracoes->getMatriculaAtiva($alunoId),
                'responsaveis' => $this->declaracoes->getResponsaveis($alunoId),
            ],
            'logo_data' => '',
            'numero' => $numero,
            'ano' => (int) date('Y'),
            'gerado_em' => date('d/m/Y'),
            'cidade_data' => $this->cidadeData(is_array($unidade) ? $unidade : null),
        ];
        $vars = $this->modelos->varsFromDeclaracao($viewData);
        $vars = $this->modelos->mesclarCamposVariaveis($vars, $campos, $valores);
        return $this->modelos->renderHtml($modelo, $vars, 'auto', $config);
    }

    /**
     * @return array<string,mixed>
     */
    private function modeloEmitivel(int $modeloId): array
    {
        $modelo = $this->modeloAtivo($modeloId);
        if ($this->modelos->camposDoModelo($modelo) === []) {
            throw new \InvalidArgumentException('Este modelo não tem campos da emissão.');
        }
        return $modelo;
    }

    /**
     * @return array<string,mixed>
     */
    private function modeloAtivo(int $modeloId): array
    {
        $modelo = $this->modelos->findById($modeloId);
        if (!$modelo || (int) ($modelo['ativo'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('Modelo não encontrado.');
        }
        if (trim((string) ($modelo['corpo_html'] ?? '')) === '' && !$this->modelos->modeloTemEstruturaVisual($modelo)) {
            throw new \InvalidArgumentException('Este modelo ainda não tem conteúdo.');
        }
        return $modelo;
    }

    /**
     * @return list<int>
     */
    private function idsAlunosDaTurma(int $turmaId): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT a.id
                 FROM matricula m
                 INNER JOIN alunos a ON a.id = m.aluno_id
                 WHERE m.turma_id = :turma AND m.status = 'ativa'
                 ORDER BY a.nome ASC
                 LIMIT 121",
                ['turma' => $turmaId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * @param list<string> $documentos
     */
    private function juntarHtml(array $documentos): string
    {
        if (count($documentos) === 1) {
            return $documentos[0];
        }
        $css = '';
        if (preg_match('/<style>(.*?)<\/style>/is', $documentos[0], $estilo) === 1) {
            $css = str_replace('body.folha-impressa', '.folha-impressa', $estilo[1]);
        }
        $partes = [];
        foreach ($documentos as $html) {
            $classe = 'folha-lote';
            if (preg_match('/<body([^>]*)>/i', $html, $abertura) === 1
                && preg_match('/class="([^"]*)"/', $abertura[1], $cls) === 1) {
                $extra = trim($cls[1]);
                if ($extra !== '') {
                    $classe .= ' ' . $extra;
                }
            }
            $corpo = $html;
            if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $miolo) === 1) {
                $corpo = $miolo[1];
            }
            $partes[] = '<div class="' . htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') . '">' . $corpo . '</div>';
        }
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><style>'
            . $css
            . "\n.folha-lote{page-break-after:always;break-after:page;}\n"
            . ".folha-lote:last-child{page-break-after:auto;break-after:auto;}\n"
            . '</style></head><body>' . implode("\n", $partes) . '</body></html>';
    }

    /**
     * @param array<string,mixed> $modelo
     */
    /**
     * @param array<string,mixed> $modelo
     */
    public function binarioPdf(string $html, array $modelo): string
    {
        $orientation = $this->modelos->orientacaoDompdf($modelo);
        $paper = $this->modelos->papelDompdf($modelo);
        $orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';
        $paper = strtoupper($paper) === 'A5' ? 'A5' : 'A4';
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $chroot = defined('BASE_PATH') ? (BASE_PATH . '/storage') : null;
        if (is_string($chroot) && is_dir($chroot)) {
            $options->setChroot($chroot);
        }
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        return $dompdf->output();
    }

    /**
     * @param array<string,mixed> $modelo
     */
    private function enviarPdf(string $html, string $filename, array $modelo): void
    {
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        try {
            $binario = $this->binarioPdf($html, $modelo);
            $this->arquivarPdfDoAluno($binario, $filename);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $binario;
            exit;
        } finally {
            ini_set('display_errors', (string) $oldDisplayErrors);
        }
    }

    /**
     * @param array<string,mixed>|null $unidade
     */
    private function cidadeData(?array $unidade): string
    {
        $cidade = trim((string) ($unidade['cidade'] ?? ''));
        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $data = (int) date('j') . ' de ' . ($meses[(int) date('n')] ?? '') . ' de ' . date('Y');
        return ($cidade !== '' ? $cidade . ', ' : '') . $data;
    }

    private function slug(string $nome, int $alunoId): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $nome);
        $slug = trim((string) $slug, '_-');
        return $slug !== '' ? $slug : ('aluno_' . $alunoId);
    }

    private function arquivarPdfDoAluno(string $bin, string $filename): void
    {
        $meta = $this->arquivoAlunoPdf;
        $this->arquivoAlunoPdf = null;
        if (!is_array($meta) || $bin === '') {
            return;
        }
        try {
            require_once __DIR__ . '/../../vida-escolar/Services/ArquivoPdfAlunoService.php';
            (new \App\Modulos\VidaEscolar\Services\ArquivoPdfAlunoService($this->db))->guardar(
                (int) ($meta['aluno_id'] ?? 0),
                'modelo',
                (string) ($meta['titulo'] ?? 'Documento'),
                $bin,
                $filename,
                $meta['usuario_id'] ?? null,
                (string) ($meta['usuario_nome'] ?? ''),
                is_array($meta['config'] ?? null) ? $meta['config'] : null
            );
        } catch (\Throwable $e) {
            error_log('Modelo do aluno arquivar PDF: ' . $e->getMessage());
        }
    }
}
