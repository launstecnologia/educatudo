<?php

namespace App\Modulos\Mural\Services;

use App\Core\Database;

/**
 * Publica o recado do mural no sino e registra a confirmação de leitura.
 */
class MuralRecadoService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function publicarNoSino(int $recadoId): void
    {
        if ($recadoId <= 0) {
            return;
        }
        try {
            $recado = $this->db->fetch('SELECT * FROM mural_recados WHERE id = :id', ['id' => $recadoId]);
            if (!$recado) {
                return;
            }
            $destinatarios = $this->destinatarios($recado);
            $conteudo = trim((string) ($recado['conteudo'] ?? ''));
            if ($conteudo === '') {
                $conteudo = (string) $recado['titulo'];
            }
            $expira = ((string) ($recado['data_sai_mural'] ?? '')) . ' 23:59:59';
            $perfil = ($recado['autor_tipo'] ?? '') === 'professor' ? 'professor' : 'coordenador';
            $tipoEnviador = ($recado['autor_tipo'] ?? '') === 'professor' ? 'professor' : 'admin';

            $existente = $this->db->fetch(
                'SELECT id FROM notificacoes WHERE mural_recado_id = :id LIMIT 1',
                ['id' => $recadoId]
            );
            if ($existente) {
                $notificacaoId = (int) $existente['id'];
                $this->db->query(
                    'UPDATE notificacoes
                     SET titulo = :titulo, conteudo = :conteudo, data_expiracao = :expira, ativo = 1
                     WHERE id = :id',
                    [
                        'titulo' => $recado['titulo'],
                        'conteudo' => $conteudo,
                        'expira' => $expira,
                        'id' => $notificacaoId,
                    ]
                );
                $this->db->query('DELETE FROM notificacoes_destinatarios WHERE notificacao_id = :id', ['id' => $notificacaoId]);
            } else {
                $notificacaoId = (int) $this->db->insert(
                    "INSERT INTO notificacoes
                        (tipo_conteudo, tipos_conteudo, titulo, conteudo, enviado_por, tipo_enviador, perfil_enviador, prioridade, data_envio, data_expiracao, ativo, mural_recado_id)
                     VALUES
                        ('texto', 'texto', :titulo, :conteudo, :enviado, :tipo, :perfil, 'normal', NOW(), :expira, 1, :mural)",
                    [
                        'titulo' => $recado['titulo'],
                        'conteudo' => $conteudo,
                        'enviado' => (int) $recado['autor_id'],
                        'tipo' => $tipoEnviador,
                        'perfil' => $perfil,
                        'expira' => $expira,
                        'mural' => $recadoId,
                    ]
                );
            }
            foreach ($destinatarios as $destino) {
                $this->db->insert(
                    'INSERT INTO notificacoes_destinatarios (notificacao_id, tipo_destinatario, destinatario_id) VALUES (:nid, :tipo, :did)',
                    ['nid' => $notificacaoId, 'tipo' => $destino['tipo'], 'did' => $destino['id']]
                );
            }
        } catch (\Throwable $e) {
            error_log('Mural sino: ' . $e->getMessage());
        }
    }

    public function alunoPodeVerRecado(int $recadoId, int $alunoId): bool
    {
        return $this->alunoPodeVer($recadoId, $alunoId);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function usuarioPodeVerAnexo(array $user, int $recadoId): bool
    {
        $tipo = (string) ($user['tipo'] ?? '');
        $userId = (int) ($user['id'] ?? 0);
        if ($recadoId <= 0 || $userId <= 0) {
            return false;
        }
        if ($tipo === 'admin') {
            return true;
        }
        if ($tipo === 'professor') {
            $row = $this->db->fetch(
                "SELECT id FROM mural_recados WHERE id = :id AND autor_tipo = 'professor' AND autor_id = :autor",
                ['id' => $recadoId, 'autor' => $userId]
            );
            return !empty($row);
        }
        if ($tipo === 'aluno') {
            return $this->alunoPodeVer($recadoId, $userId);
        }
        return false;
    }

    /**
     * @param array<int, int> $recadoIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function listarAnexosDosRecados(array $recadoIds): array
    {
        $recadoIds = array_values(array_unique(array_filter(array_map('intval', $recadoIds))));
        if ($recadoIds === []) {
            return [];
        }
        $marcas = [];
        $params = [];
        foreach ($recadoIds as $i => $recadoId) {
            $chave = 'r' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $recadoId;
        }
        $rows = $this->db->fetchAll(
            'SELECT id, mural_recado_id, arquivo_nome, tipo_arquivo, tamanho
             FROM mural_recados_anexos
             WHERE mural_recado_id IN (' . implode(',', $marcas) . ')
             ORDER BY id',
            $params
        );
        $porRecado = [];
        foreach ($rows as $row) {
            $porRecado[(int) $row['mural_recado_id']][] = $row;
        }
        return $porRecado;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarAnexos(int $recadoId): array
    {
        return $this->listarAnexosDosRecados([$recadoId])[$recadoId] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarAnexo(int $anexoId): ?array
    {
        if ($anexoId <= 0) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, mural_recado_id, arquivo, arquivo_nome, tipo_arquivo
             FROM mural_recados_anexos WHERE id = :id',
            ['id' => $anexoId]
        );
        return $row ?: null;
    }

    /**
     * @param array<int, int> $ids
     */
    public function removerAnexos(int $recadoId, array $ids): void
    {
        if ($recadoId <= 0) {
            return;
        }
        foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $anexoId) {
            $row = $this->db->fetch(
                'SELECT id, arquivo FROM mural_recados_anexos WHERE id = :id AND mural_recado_id = :rid',
                ['id' => $anexoId, 'rid' => $recadoId]
            );
            if (!$row) {
                continue;
            }
            $path = $this->caminhoFisicoAnexo((string) $row['arquivo']);
            if ($path !== null) {
                @unlink($path);
            }
            $this->db->query(
                'DELETE FROM mural_recados_anexos WHERE id = :id AND mural_recado_id = :rid',
                ['id' => $anexoId, 'rid' => $recadoId]
            );
        }
    }

    /**
     * @param array<string, mixed> $files
     * @return array{salvos: int, erros: array<int, string>}
     */
    public function salvarAnexos(int $recadoId, array $files): array
    {
        $vazio = ['salvos' => 0, 'erros' => []];
        if ($recadoId <= 0 || !isset($files['name']) || !is_array($files['name'])) {
            return $vazio;
        }
        $enviados = 0;
        foreach ($files['name'] as $i => $nome) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $enviados++;
            }
        }
        if ($enviados === 0) {
            return $vazio;
        }
        $ja = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS total FROM mural_recados_anexos WHERE mural_recado_id = :id',
            ['id' => $recadoId]
        )['total'] ?? 0);
        if ($ja + $enviados > 5) {
            return ['salvos' => 0, 'erros' => ['Cada recado aceita no máximo 5 anexos.']];
        }
        $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : '';
        if ($slug === '') {
            return ['salvos' => 0, 'erros' => ['Não foi possível guardar o anexo.']];
        }
        $dir = dirname(__DIR__, 4) . '/storage/uploads/' . $slug . '/mural/' . $recadoId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['salvos' => 0, 'erros' => ['Não foi possível guardar o anexo.']];
        }
        $permitidos = [
            'image/jpeg' => ['jpg', 'image/jpeg'],
            'image/jpg' => ['jpg', 'image/jpeg'],
            'image/pjpeg' => ['jpg', 'image/jpeg'],
            'image/png' => ['png', 'image/png'],
            'image/x-png' => ['png', 'image/png'],
            'image/webp' => ['webp', 'image/webp'],
            'image/gif' => ['gif', 'image/gif'],
            'application/pdf' => ['pdf', 'application/pdf'],
        ];
        $salvos = 0;
        $erros = [];
        foreach ($files['name'] as $i => $original) {
            $erroUpload = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($erroUpload === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nomeOriginal = basename((string) $original);
            if ($nomeOriginal === '') {
                $nomeOriginal = 'arquivo';
            }
            if ($erroUpload !== UPLOAD_ERR_OK) {
                $erros[] = 'Não foi possível enviar ' . $nomeOriginal . '.';
                continue;
            }
            $tmp = (string) ($files['tmp_name'][$i] ?? '');
            $tamanho = (int) ($files['size'][$i] ?? 0);
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                $erros[] = 'Não foi possível enviar ' . $nomeOriginal . '.';
                continue;
            }
            if ($tamanho <= 0 || $tamanho > 8 * 1024 * 1024) {
                $erros[] = $nomeOriginal . ' passa de 8 MB.';
                continue;
            }
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if (!isset($permitidos[$mime])) {
                $erros[] = $nomeOriginal . ' precisa ser imagem (JPG, PNG, WEBP ou GIF) ou PDF.';
                continue;
            }
            [$extensao, $mimeGravado] = $permitidos[$mime];
            $filename = bin2hex(random_bytes(16)) . '.' . $extensao;
            if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
                $erros[] = 'Não foi possível guardar ' . $nomeOriginal . '.';
                continue;
            }
            $this->db->insert(
                'INSERT INTO mural_recados_anexos (mural_recado_id, arquivo, arquivo_nome, tipo_arquivo, tamanho)
                 VALUES (:rid, :arquivo, :nome, :mime, :tamanho)',
                [
                    'rid' => $recadoId,
                    'arquivo' => $slug . '/mural/' . $recadoId . '/' . $filename,
                    'nome' => $nomeOriginal,
                    'mime' => $mimeGravado,
                    'tamanho' => $tamanho,
                ]
            );
            $salvos++;
        }
        return ['salvos' => $salvos, 'erros' => $erros];
    }

    public function apagarArquivosDoRecado(int $recadoId): void
    {
        if ($recadoId <= 0) {
            return;
        }
        $rows = $this->db->fetchAll(
            'SELECT arquivo FROM mural_recados_anexos WHERE mural_recado_id = :id',
            ['id' => $recadoId]
        );
        foreach ($rows as $row) {
            $path = $this->caminhoFisicoAnexo((string) ($row['arquivo'] ?? ''));
            if ($path !== null) {
                @unlink($path);
            }
        }
    }

    public function caminhoFisicoAnexo(string $caminho): ?string
    {
        $caminho = str_replace('\\', '/', $caminho);
        $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : '';
        if ($slug === '' || $caminho === '' || strpos($caminho, '..') !== false || strpos($caminho, $slug . '/mural/') !== 0) {
            return null;
        }
        $base = realpath(dirname(__DIR__, 4) . '/storage/uploads');
        $full = realpath(dirname(__DIR__, 4) . '/storage/uploads/' . $caminho);
        $prefix = $base !== false ? rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
        if ($base === false || $full === false || $prefix === '' || strpos($full, $prefix) !== 0 || !is_file($full)) {
            return null;
        }
        return $full;
    }

    public function marcarVistoAluno(int $recadoId, int $alunoId): void
    {
        if ($recadoId <= 0 || $alunoId <= 0 || !$this->alunoPodeVer($recadoId, $alunoId)) {
            return;
        }
        $this->db->query(
            'INSERT IGNORE INTO mural_recados_vistos (mural_recado_id, aluno_id) VALUES (:rid, :aid)',
            ['rid' => $recadoId, 'aid' => $alunoId]
        );
        $this->marcarSinoLido($recadoId, $alunoId, 'alunos');
    }

    public function marcarVistoProfessor(int $recadoId, int $professorId): void
    {
        if ($recadoId <= 0 || $professorId <= 0 || !$this->professorPodeVer($recadoId, $professorId)) {
            return;
        }
        $this->db->query(
            'INSERT IGNORE INTO mural_recados_vistos (mural_recado_id, professor_id) VALUES (:rid, :pid)',
            ['rid' => $recadoId, 'pid' => $professorId]
        );
        $this->marcarSinoLido($recadoId, $professorId, 'professores');
    }

    /**
     * @param array<int, int> $turmaIds
     * @return array<int, array<string, mixed>>
     */
    public function listarRecebidosProfessor(int $professorId, array $turmaIds): array
    {
        $turmaIds = array_values(array_unique(array_filter(array_map('intval', $turmaIds))));
        $turmaSql = '';
        $params = ['pid' => $professorId, 'pid_visto' => $professorId];
        if ($turmaIds !== []) {
            $marcas = [];
            foreach ($turmaIds as $i => $tid) {
                $chave = 't' . $i;
                $marcas[] = ':' . $chave;
                $params[$chave] = $tid;
            }
            $turmaSql = ' OR (r.professor_id IS NULL AND r.enviar_para_todos = 0 AND EXISTS (
                SELECT 1 FROM mural_recados_turmas rt
                WHERE rt.mural_recado_id = r.id AND rt.turma_id IN (' . implode(',', $marcas) . ')
            ))';
        }
        return $this->db->fetchAll(
            "SELECT r.id, r.titulo, r.conteudo, r.data_publicacao, r.enviar_para_todos,
                    CASE WHEN v.id IS NULL THEN 0 ELSE 1 END AS ja_visto
             FROM mural_recados r
             LEFT JOIN mural_recados_vistos v
               ON v.mural_recado_id = r.id AND v.professor_id = :pid_visto
             WHERE r.publico = 'professores'
               AND CURDATE() <= r.data_sai_mural
               AND (
                    r.professor_id = :pid
                    OR (r.professor_id IS NULL AND r.enviar_para_todos = 1)
                    {$turmaSql}
               )
             ORDER BY r.data_publicacao DESC
             LIMIT 50",
            $params
        );
    }

    private function alunoPodeVer(int $recadoId, int $alunoId): bool
    {
        $row = $this->db->fetch(
            "SELECT r.id
             FROM mural_recados r
             INNER JOIN alunos a ON a.id = :aluno AND a.ativo = 1
             WHERE r.id = :rid
               AND COALESCE(r.publico, 'alunos') = 'alunos'
               AND CURDATE() <= r.data_sai_mural
               AND (
                    r.aluno_id = a.id
                    OR (
                        r.aluno_id IS NULL
                        AND (
                            r.enviar_para_todos = 1
                            OR EXISTS (
                                SELECT 1 FROM mural_recados_turmas rt
                                WHERE rt.mural_recado_id = r.id AND rt.turma_id = a.turma_id
                            )
                        )
                    )
               )
             LIMIT 1",
            ['aluno' => $alunoId, 'rid' => $recadoId]
        );
        return !empty($row);
    }

    private function professorPodeVer(int $recadoId, int $professorId): bool
    {
        $recado = $this->db->fetch(
            "SELECT id, professor_id, enviar_para_todos
             FROM mural_recados
             WHERE id = :id AND publico = 'professores' AND CURDATE() <= data_sai_mural",
            ['id' => $recadoId]
        );
        if (!$recado) {
            return false;
        }
        if ((int) ($recado['professor_id'] ?? 0) === $professorId) {
            return true;
        }
        if (!empty($recado['enviar_para_todos']) && empty($recado['professor_id'])) {
            $ativo = $this->db->fetch('SELECT id FROM professores WHERE id = :id AND ativo = 1', ['id' => $professorId]);
            return !empty($ativo);
        }
        $professor = $this->db->fetch('SELECT turmas FROM professores WHERE id = :id AND ativo = 1', ['id' => $professorId]);
        if (!$professor) {
            return false;
        }
        $turmasProfessor = json_decode((string) ($professor['turmas'] ?? '[]'), true);
        if (!is_array($turmasProfessor)) {
            return false;
        }
        $alvo = array_flip(array_map('intval', $turmasProfessor));
        foreach ($this->turmasDoRecado($recadoId) as $turmaId) {
            if (isset($alvo[$turmaId])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $recado
     * @return array<int, array{tipo: string, id: int}>
     */
    private function destinatarios(array $recado): array
    {
        $publico = ($recado['publico'] ?? 'alunos') === 'professores' ? 'professores' : 'alunos';
        $recadoId = (int) $recado['id'];
        if ($publico === 'professores') {
            if (!empty($recado['professor_id'])) {
                return [['tipo' => 'professores', 'id' => (int) $recado['professor_id']]];
            }
            if (!empty($recado['enviar_para_todos'])) {
                $rows = $this->db->fetchAll('SELECT id FROM professores WHERE ativo = 1');
                return array_map(static fn ($row) => ['tipo' => 'professores', 'id' => (int) $row['id']], $rows);
            }
            $turmas = $this->turmasDoRecado($recadoId);
            return array_map(
                static fn ($id) => ['tipo' => 'professores', 'id' => $id],
                $this->professoresDasTurmas($turmas)
            );
        }
        if (!empty($recado['aluno_id'])) {
            return [['tipo' => 'alunos', 'id' => (int) $recado['aluno_id']]];
        }
        if (!empty($recado['enviar_para_todos'])) {
            $rows = $this->db->fetchAll('SELECT id FROM alunos WHERE ativo = 1');
            return array_map(static fn ($row) => ['tipo' => 'alunos', 'id' => (int) $row['id']], $rows);
        }
        $turmas = $this->turmasDoRecado($recadoId);
        if ($turmas === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($turmas), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id FROM alunos WHERE ativo = 1 AND turma_id IN ({$marcas})",
            $turmas
        );
        return array_map(static fn ($row) => ['tipo' => 'alunos', 'id' => (int) $row['id']], $rows);
    }

    /**
     * @return array<int, int>
     */
    private function turmasDoRecado(int $recadoId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT turma_id FROM mural_recados_turmas WHERE mural_recado_id = :id',
            ['id' => $recadoId]
        );
        return array_values(array_unique(array_map('intval', array_column($rows, 'turma_id'))));
    }

    /**
     * @param array<int, int> $turmaIds
     * @return array<int, int>
     */
    private function professoresDasTurmas(array $turmaIds): array
    {
        if ($turmaIds === []) {
            return [];
        }
        $alvo = array_flip($turmaIds);
        $rows = $this->db->fetchAll('SELECT id, turmas FROM professores WHERE ativo = 1');
        $ids = [];
        foreach ($rows as $row) {
            $turmas = json_decode((string) ($row['turmas'] ?? '[]'), true);
            if (!is_array($turmas)) {
                continue;
            }
            foreach ($turmas as $tid) {
                if (isset($alvo[(int) $tid])) {
                    $ids[] = (int) $row['id'];
                    break;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    private function marcarSinoLido(int $recadoId, int $pessoaId, string $tipo): void
    {
        try {
            $this->db->query(
                "UPDATE notificacoes_destinatarios nd
                 INNER JOIN notificacoes n ON n.id = nd.notificacao_id
                 SET nd.lida = 1, nd.lida_em = COALESCE(nd.lida_em, NOW())
                 WHERE n.mural_recado_id = :rid
                   AND nd.tipo_destinatario = :tipo
                   AND nd.destinatario_id = :pid",
                ['rid' => $recadoId, 'tipo' => $tipo, 'pid' => $pessoaId]
            );
        } catch (\Throwable $e) {
            error_log('Mural leitura sino: ' . $e->getMessage());
        }
    }
}
