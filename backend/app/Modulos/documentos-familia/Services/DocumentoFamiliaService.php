<?php

namespace App\Modulos\DocumentosFamilia\Services;

use App\Core\Database;

/** Arquivos, ficha de saúde e declarações preenchidos pela família. */
class DocumentoFamiliaService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function pronto(): bool
    {
        return $this->db->tableExists('familia_documentos_arquivos')
            && $this->db->tableExists('familia_fichas_saude')
            && $this->db->tableExists('familia_declaracoes')
            && $this->db->tableExists('familia_declaracao_aceites');
    }

    public function arquivos(int $alunoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, titulo, arquivo, arquivo_nome, tipo_arquivo, tamanho, created_at
             FROM familia_documentos_arquivos
             WHERE aluno_id = :aluno
             ORDER BY id DESC',
            ['aluno' => $alunoId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>|null
     */
    public function guardarArquivo(int $alunoId, int $responsavelId, string $titulo, array $file): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > 10 * 1024 * 1024) {
            return null;
        }
        $mapa = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset($mapa[$mime])) {
            return null;
        }
        $tenant = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : 'default';
        $relative = 'storage/uploads/documentos-familia/' . ($tenant !== '' ? $tenant : 'default') . '/' . $alunoId;
        $absolute = dirname(__DIR__, 4) . '/' . $relative;
        if (!is_dir($absolute) && !@mkdir($absolute, 0775, true) && !is_dir($absolute)) {
            return null;
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $mapa[$mime];
        if (!move_uploaded_file($tmp, $absolute . '/' . $filename)) {
            return null;
        }
        $nome = basename((string) ($file['name'] ?? 'documento'));
        $titulo = trim($titulo);
        if ($titulo === '') {
            $titulo = $nome !== '' ? $nome : 'Documento';
        }
        if (mb_strlen($titulo) > 180) {
            $titulo = mb_substr($titulo, 0, 180);
        }
        $id = (int) $this->db->insert(
            'INSERT INTO familia_documentos_arquivos (aluno_id, responsavel_id, titulo, arquivo, arquivo_nome, tipo_arquivo, tamanho)
             VALUES (:aluno, :responsavel, :titulo, :arquivo, :nome, :mime, :tamanho)',
            [
                'aluno' => $alunoId,
                'responsavel' => $responsavelId,
                'titulo' => $titulo,
                'arquivo' => '/' . $relative . '/' . $filename,
                'nome' => $nome !== '' ? $nome : 'documento',
                'mime' => $mime,
                'tamanho' => $size,
            ]
        );
        $this->avisarAdmins('Documentos da família', 'Novo arquivo enviado: ' . $titulo);

        return ['id' => $id, 'titulo' => $titulo, 'arquivo' => '/' . $relative . '/' . $filename, 'arquivo_nome' => $nome, 'tipo_arquivo' => $mime, 'tamanho' => $size];
    }

    /** @return array{path:string,nome:string,mime:string}|null */
    public function caminhoArquivo(int $arquivoId, ?int $alunoId = null): ?array
    {
        $sql = 'SELECT arquivo, arquivo_nome, tipo_arquivo FROM familia_documentos_arquivos WHERE id = :id';
        $params = ['id' => $arquivoId];
        if ($alunoId !== null) {
            $sql .= ' AND aluno_id = :aluno';
            $params['aluno'] = $alunoId;
        }
        $row = $this->db->fetch($sql, $params);
        if (!is_array($row)) {
            return null;
        }
        $relativo = ltrim(str_replace('\\', '/', (string) ($row['arquivo'] ?? '')), '/');
        if ($relativo === '' || str_contains($relativo, '..') || !str_starts_with($relativo, 'storage/uploads/documentos-familia/')) {
            return null;
        }
        $base = realpath(dirname(__DIR__, 4) . '/storage/uploads/documentos-familia');
        $full = realpath(dirname(__DIR__, 4) . '/' . $relativo);
        if ($base === false || $full === false || !is_file($full) || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }
        $mime = (string) ($row['tipo_arquivo'] ?? '');
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return ['path' => $full, 'nome' => basename((string) ($row['arquivo_nome'] ?? 'documento')), 'mime' => $mime];
    }

    public function ficha(int $alunoId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM familia_fichas_saude WHERE aluno_id = :aluno',
            ['aluno' => $alunoId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,string> $campos
     */
    public function salvarFicha(int $alunoId, int $responsavelId, array $campos): array
    {
        $momento = date('Y-m-d H:i:s');
        $dados = [
            'tipo_sanguineo' => $this->curto($campos['tipo_sanguineo'] ?? '', 8),
            'alergias' => $this->texto($campos['alergias'] ?? ''),
            'medicamentos' => $this->texto($campos['medicamentos'] ?? ''),
            'condicoes' => $this->texto($campos['condicoes'] ?? ''),
            'plano_saude' => $this->curto($campos['plano_saude'] ?? '', 180),
            'emergencia_nome' => $this->curto($campos['emergencia_nome'] ?? '', 180),
            'emergencia_telefone' => $this->curto($campos['emergencia_telefone'] ?? '', 40),
            'observacoes' => $this->texto($campos['observacoes'] ?? ''),
        ];
        $hash = hash('sha256', implode('|', [
            $alunoId,
            $responsavelId,
            $dados['tipo_sanguineo'],
            $dados['alergias'],
            $dados['medicamentos'],
            $dados['condicoes'],
            $dados['plano_saude'],
            $dados['emergencia_nome'],
            $dados['emergencia_telefone'],
            $dados['observacoes'],
            $momento,
        ]));
        $this->db->query(
            'INSERT INTO familia_fichas_saude
                (aluno_id, responsavel_id, tipo_sanguineo, alergias, medicamentos, condicoes, plano_saude, emergencia_nome, emergencia_telefone, observacoes, hash_registro, atualizado_em)
             VALUES
                (:aluno, :responsavel, :tipo, :alergias, :medicamentos, :condicoes, :plano, :emergencia_nome, :emergencia_telefone, :observacoes, :hash, :momento)
             ON DUPLICATE KEY UPDATE
                responsavel_id = VALUES(responsavel_id),
                tipo_sanguineo = VALUES(tipo_sanguineo),
                alergias = VALUES(alergias),
                medicamentos = VALUES(medicamentos),
                condicoes = VALUES(condicoes),
                plano_saude = VALUES(plano_saude),
                emergencia_nome = VALUES(emergencia_nome),
                emergencia_telefone = VALUES(emergencia_telefone),
                observacoes = VALUES(observacoes),
                hash_registro = VALUES(hash_registro),
                atualizado_em = VALUES(atualizado_em)',
            [
                'aluno' => $alunoId,
                'responsavel' => $responsavelId,
                'tipo' => $dados['tipo_sanguineo'] !== '' ? $dados['tipo_sanguineo'] : null,
                'alergias' => $dados['alergias'] !== '' ? $dados['alergias'] : null,
                'medicamentos' => $dados['medicamentos'] !== '' ? $dados['medicamentos'] : null,
                'condicoes' => $dados['condicoes'] !== '' ? $dados['condicoes'] : null,
                'plano' => $dados['plano_saude'] !== '' ? $dados['plano_saude'] : null,
                'emergencia_nome' => $dados['emergencia_nome'] !== '' ? $dados['emergencia_nome'] : null,
                'emergencia_telefone' => $dados['emergencia_telefone'] !== '' ? $dados['emergencia_telefone'] : null,
                'observacoes' => $dados['observacoes'] !== '' ? $dados['observacoes'] : null,
                'hash' => $hash,
                'momento' => $momento,
            ]
        );
        $this->avisarAdmins('Ficha de saúde', 'A família atualizou a ficha de saúde do aluno.');

        return array_merge($dados, ['hash_registro' => $hash, 'atualizado_em' => $momento]);
    }

    public function declaracoesDoAluno(int $alunoId, int $responsavelId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT d.id, d.titulo, d.texto, d.versao, a.texto_hash, a.aceito_em
             FROM familia_declaracoes d
             LEFT JOIN familia_declaracao_aceites a
               ON a.declaracao_id = d.id AND a.aluno_id = :aluno AND a.responsavel_id = :responsavel AND a.versao = d.versao
             WHERE d.ativa = 1
             ORDER BY d.id DESC',
            ['aluno' => $alunoId, 'responsavel' => $responsavelId]
        );

        return is_array($rows) ? $rows : [];
    }

    public function aceitar(int $declaracaoId, int $alunoId, int $responsavelId): ?array
    {
        $declaracao = $this->db->fetch(
            'SELECT id, titulo, texto, versao FROM familia_declaracoes WHERE id = :id AND ativa = 1',
            ['id' => $declaracaoId]
        );
        if (!is_array($declaracao)) {
            return null;
        }
        $versao = (int) $declaracao['versao'];
        $existente = $this->db->fetch(
            'SELECT versao, texto_hash, aceito_em
             FROM familia_declaracao_aceites
             WHERE declaracao_id = :declaracao AND aluno_id = :aluno AND responsavel_id = :responsavel AND versao = :versao',
            ['declaracao' => $declaracaoId, 'aluno' => $alunoId, 'responsavel' => $responsavelId, 'versao' => $versao]
        );
        if (is_array($existente)) {
            return [
                'id' => $declaracaoId,
                'version' => $versao,
                'hash' => (string) $existente['texto_hash'],
                'accepted_at' => (string) $existente['aceito_em'],
            ];
        }
        $momento = date('Y-m-d H:i:s');
        $texto = (string) $declaracao['texto'];
        $hash = hash('sha256', implode('|', [$declaracaoId, $versao, $texto, $alunoId, $responsavelId, $momento]));
        try {
            $this->db->query(
                'INSERT INTO familia_declaracao_aceites (declaracao_id, aluno_id, responsavel_id, versao, texto_hash, aceito_em)
                 VALUES (:declaracao, :aluno, :responsavel, :versao, :hash, :momento)',
                [
                    'declaracao' => $declaracaoId,
                    'aluno' => $alunoId,
                    'responsavel' => $responsavelId,
                    'versao' => $versao,
                    'hash' => $hash,
                    'momento' => $momento,
                ]
            );
        } catch (\Throwable $e) {
            $existente = $this->db->fetch(
                'SELECT versao, texto_hash, aceito_em
                 FROM familia_declaracao_aceites
                 WHERE declaracao_id = :declaracao AND aluno_id = :aluno AND responsavel_id = :responsavel AND versao = :versao',
                ['declaracao' => $declaracaoId, 'aluno' => $alunoId, 'responsavel' => $responsavelId, 'versao' => $versao]
            );
            if (!is_array($existente)) {
                throw $e;
            }

            return [
                'id' => $declaracaoId,
                'version' => $versao,
                'hash' => (string) $existente['texto_hash'],
                'accepted_at' => (string) $existente['aceito_em'],
            ];
        }
        $this->avisarAdmins('Declaração aceita', (string) $declaracao['titulo']);

        return ['id' => $declaracaoId, 'version' => $versao, 'hash' => $hash, 'accepted_at' => $momento];
    }

    public function criarDeclaracao(string $titulo, string $texto): int
    {
        return (int) $this->db->insert(
            'INSERT INTO familia_declaracoes (titulo, texto, versao, ativa) VALUES (:titulo, :texto, 1, 1)',
            ['titulo' => $titulo, 'texto' => $texto]
        );
    }

    public function painel(): array
    {
        $arquivos = $this->db->fetchAll(
            'SELECT d.id, d.titulo, d.arquivo_nome, d.created_at, a.nome AS aluno_nome, r.nome AS responsavel_nome
             FROM familia_documentos_arquivos d
             INNER JOIN alunos a ON a.id = d.aluno_id
             INNER JOIN responsaveis r ON r.id = d.responsavel_id
             ORDER BY d.id DESC LIMIT 100'
        );
        $fichas = $this->db->fetchAll(
            'SELECT f.aluno_id, f.tipo_sanguineo, f.hash_registro, f.atualizado_em, a.nome AS aluno_nome
             FROM familia_fichas_saude f
             INNER JOIN alunos a ON a.id = f.aluno_id
             ORDER BY f.atualizado_em DESC LIMIT 100'
        );
        $declaracoes = $this->db->fetchAll(
            'SELECT id, titulo, versao, ativa, updated_at FROM familia_declaracoes ORDER BY id DESC LIMIT 100'
        );
        $aceites = $this->db->fetchAll(
            'SELECT ac.texto_hash, ac.aceito_em, ac.versao, d.titulo, a.nome AS aluno_nome, r.nome AS responsavel_nome
             FROM familia_declaracao_aceites ac
             INNER JOIN familia_declaracoes d ON d.id = ac.declaracao_id
             INNER JOIN alunos a ON a.id = ac.aluno_id
             INNER JOIN responsaveis r ON r.id = ac.responsavel_id
             ORDER BY ac.aceito_em DESC LIMIT 100'
        );

        return [
            'arquivos' => is_array($arquivos) ? $arquivos : [],
            'fichas' => is_array($fichas) ? $fichas : [],
            'declaracoes' => is_array($declaracoes) ? $declaracoes : [],
            'aceites' => is_array($aceites) ? $aceites : [],
        ];
    }

    private function avisarAdmins(string $titulo, string $conteudo): void
    {
        try {
            $admins = $this->db->fetchAll("SELECT id FROM usuarios WHERE tipo IN ('admin', 'admin_escola') AND ativo = 1");
        } catch (\Throwable $e) {
            try {
                $admins = $this->db->fetchAll("SELECT id FROM usuarios WHERE tipo IN ('admin', 'admin_escola')");
            } catch (\Throwable $e2) {
                return;
            }
        }
        if (!is_array($admins) || $admins === []) {
            return;
        }
        $primeiro = (int) ($admins[0]['id'] ?? 0);
        if ($primeiro <= 0) {
            return;
        }
        $notificacaoId = (int) $this->db->insert(
            "INSERT INTO notificacoes (tipo_conteudo, titulo, conteudo, enviado_por, tipo_enviador, prioridade, data_envio, ativo)
             VALUES ('mensagem', :titulo, :conteudo, :enviado, 'admin', 'alta', NOW(), 1)",
            ['titulo' => $titulo, 'conteudo' => $conteudo, 'enviado' => $primeiro]
        );
        foreach ($admins as $admin) {
            $destino = (int) ($admin['id'] ?? 0);
            if ($destino <= 0) {
                continue;
            }
            $this->db->insert(
                "INSERT INTO notificacoes_destinatarios (notificacao_id, tipo_destinatario, destinatario_id, lida)
                 VALUES (:id, 'usuarios', :destinatario, 0)",
                ['id' => $notificacaoId, 'destinatario' => $destino]
            );
        }
    }

    private function curto(string $valor, int $limite): string
    {
        $valor = trim($valor);
        if (mb_strlen($valor) > $limite) {
            return mb_substr($valor, 0, $limite);
        }

        return $valor;
    }

    private function texto(string $valor): string
    {
        $valor = trim($valor);
        if (mb_strlen($valor) > 4000) {
            return mb_substr($valor, 0, 4000);
        }

        return $valor;
    }
}

if (!class_exists('DocumentoFamiliaService', false) && class_exists(__NAMESPACE__ . '\\DocumentoFamiliaService', false)) {
    class_alias(__NAMESPACE__ . '\\DocumentoFamiliaService', 'DocumentoFamiliaService');
}
