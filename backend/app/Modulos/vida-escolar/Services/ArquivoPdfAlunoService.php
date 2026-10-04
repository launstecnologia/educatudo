<?php

namespace App\Modulos\VidaEscolar\Services;

require_once __DIR__ . '/../../../Services/MediaStorageService.php';

use Database;
use MediaStorageService;

/**
 * Guarda o PDF gerado para o aluno no storage da escola (S3 quando configurado)
 * e registra a emissão para a aba Histórico / emissões.
 */
class ArquivoPdfAlunoService
{
    public const TIPO_ARQUIVO = 'pdfs_aluno';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function tabelaPronta(): bool
    {
        try {
            return (bool) $this->db->fetch("SHOW TABLES LIKE 'aluno_pdfs_emitidos'");
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Não interrompe a emissão se o arquivo não puder ser gravado.
     *
     * @param array<string,mixed>|null $config
     */
    public function guardar(
        int $alunoId,
        string $tipo,
        string $titulo,
        string $binario,
        string $nomeArquivo,
        ?int $usuarioId,
        string $usuarioNome,
        ?array $config
    ): void {
        if ($alunoId <= 0 || $binario === '' || !$this->tabelaPronta()) {
            return;
        }
        $titulo = trim($titulo) !== '' ? trim($titulo) : 'Documento';
        $tipo = preg_replace('/[^a-z0-9_]/i', '', $tipo) ?: 'documento';
        $nomeArquivo = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $nomeArquivo) ?: 'documento.pdf';
        if (!str_ends_with(strtolower($nomeArquivo), '.pdf')) {
            $nomeArquivo .= '.pdf';
        }
        $chave = $alunoId . '/' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $tmp = tempnam(sys_get_temp_dir(), 'pdfalu');
        if ($tmp === false) {
            error_log('Arquivo PDF aluno: não foi possível criar temporário.');
            return;
        }
        try {
            if (file_put_contents($tmp, $binario) === false) {
                error_log('Arquivo PDF aluno: falha ao gravar temporário.');
                return;
            }
            $media = new MediaStorageService($this->config($config));
            if (!$media->put(self::TIPO_ARQUIVO, $chave, $tmp, 'application/pdf')) {
                error_log('Arquivo PDF aluno: falha ao enviar ao storage.');
                return;
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
        try {
            $this->db->insert(
                'INSERT INTO aluno_pdfs_emitidos
                    (aluno_id, tipo, titulo, arquivo_key, arquivo_nome, emitido_por, emitido_nome)
                 VALUES
                    (:aluno_id, :tipo, :titulo, :arquivo_key, :arquivo_nome, :emitido_por, :emitido_nome)',
                [
                    'aluno_id' => $alunoId,
                    'tipo' => substr($tipo, 0, 40),
                    'titulo' => substr($titulo, 0, 180),
                    'arquivo_key' => $chave,
                    'arquivo_nome' => substr($nomeArquivo, 0, 255),
                    'emitido_por' => $usuarioId,
                    'emitido_nome' => $usuarioNome !== '' ? substr($usuarioNome, 0, 180) : null,
                ]
            );
        } catch (\Throwable $e) {
            error_log('Arquivo PDF aluno: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array{id:int,titulo:string,quando:string,quem:string,origem:string}>
     */
    public function listar(int $alunoId): array
    {
        if ($alunoId <= 0) {
            return [];
        }
        $itens = [];
        if ($this->tabelaPronta()) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT id, titulo, emitido_nome, created_at
                     FROM aluno_pdfs_emitidos
                     WHERE aluno_id = :id
                     ORDER BY created_at DESC, id DESC
                     LIMIT 80',
                    ['id' => $alunoId]
                ) ?: [];
            } catch (\Throwable $e) {
                $rows = [];
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $itens[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'titulo' => (string) ($row['titulo'] ?? 'Documento'),
                    'quando' => (string) ($row['created_at'] ?? ''),
                    'quem' => (string) ($row['emitido_nome'] ?? ''),
                    'origem' => 'aluno',
                ];
            }
        }
        $itens = array_merge($itens, $this->listarModelos($alunoId));
        usort($itens, static function (array $a, array $b): int {
            return strcmp((string) $b['quando'], (string) $a['quando']);
        });

        return array_slice($itens, 0, 80);
    }

    /**
     * @return array{nome:string,conteudo:string}|null
     */
    public function baixar(int $alunoId, int $pdfId, ?array $config): ?array
    {
        if ($alunoId <= 0 || $pdfId <= 0 || !$this->tabelaPronta()) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT arquivo_key, arquivo_nome
             FROM aluno_pdfs_emitidos
             WHERE id = :id AND aluno_id = :aluno
             LIMIT 1',
            ['id' => $pdfId, 'aluno' => $alunoId]
        );
        if (!is_array($row)) {
            return null;
        }
        $chave = (string) ($row['arquivo_key'] ?? '');
        $prefixo = $alunoId . '/';
        if ($chave === '' || str_contains($chave, '..') || !str_starts_with($chave, $prefixo)) {
            return null;
        }
        $conteudo = (new MediaStorageService($this->config($config)))->getContents(self::TIPO_ARQUIVO, $chave);
        if (!is_string($conteudo) || $conteudo === '') {
            return null;
        }
        $nome = preg_replace('/[\r\n\t"\\\\]/', '', (string) ($row['arquivo_nome'] ?? 'documento.pdf')) ?: 'documento.pdf';

        return ['nome' => $nome, 'conteudo' => $conteudo];
    }

    /**
     * @return list<array{id:int,titulo:string,quando:string,quem:string,origem:string}>
     */
    private function listarModelos(int $alunoId): array
    {
        try {
            if (!$this->db->fetch("SHOW TABLES LIKE 'documentos_emissoes'")) {
                return [];
            }
            $rows = $this->db->fetchAll(
                'SELECT id, nome, versao, emitido_nome, created_at
                 FROM documentos_emissoes
                 WHERE aluno_id = :id
                 ORDER BY created_at DESC, id DESC
                 LIMIT 40',
                ['id' => $alunoId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $itens = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? 'Documento'));
            $versao = (int) ($row['versao'] ?? 0);
            $itens[] = [
                'id' => (int) ($row['id'] ?? 0),
                'titulo' => $versao > 0 ? $nome . ' v' . $versao : $nome,
                'quando' => (string) ($row['created_at'] ?? ''),
                'quem' => (string) ($row['emitido_nome'] ?? ''),
                'origem' => 'modelo',
            ];
        }

        return $itens;
    }

    /**
     * @param array<string,mixed>|null $config
     * @return array<string,mixed>
     */
    private function config(?array $config): array
    {
        if (is_array($config) && $config !== []) {
            return $config;
        }
        static $carregado = null;
        if (is_array($carregado)) {
            return $carregado;
        }
        $path = dirname(__DIR__, 4) . '/config/app.php';
        if (!is_file($path)) {
            return $carregado = [];
        }
        $loaded = require $path;

        return $carregado = (is_array($loaded) ? $loaded : []);
    }
}
