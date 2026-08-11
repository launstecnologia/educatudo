<?php
/**
 * Service — lógica de negócio do módulo Arquivos.
 */

require_once __DIR__ . '/../Models/ModuloArquivo.php';
require_once __DIR__ . '/../Models/ModuloArquivoAnexo.php';
require_once __DIR__ . '/../Models/ModuloArquivoVideo.php';
require_once __DIR__ . '/../Models/ModuloArquivoPasta.php';

if (!class_exists('ArquivosService')) {
class ArquivosService
{
    private $db;
    private ModuloArquivo $arquivoModel;
    private ModuloArquivoAnexo $anexoModel;
    private ModuloArquivoVideo $videoModel;
    private ModuloArquivoPasta $pastaModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->arquivoModel = new ModuloArquivo();
        $this->anexoModel = new ModuloArquivoAnexo();
        $this->videoModel = new ModuloArquivoVideo();
        $this->pastaModel = new ModuloArquivoPasta();
    }

    public function arquivos(): ModuloArquivo
    {
        return $this->arquivoModel;
    }

    public function anexos(): ModuloArquivoAnexo
    {
        return $this->anexoModel;
    }

    public function videos(): ModuloArquivoVideo
    {
        return $this->videoModel;
    }

    public function pastas(): ModuloArquivoPasta
    {
        return $this->pastaModel;
    }

    public function temColunaRecuperacao(): bool
    {
        return (bool) $this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'modulos_arquivos'
               AND COLUMN_NAME = 'recuperacao'"
        );
    }

    public function temColunaPasta(): bool
    {
        return (bool) $this->db->fetch(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'modulos_arquivos'
               AND COLUMN_NAME = 'pasta_id'"
        );
    }

    /**
     * Lista publicações visíveis ao aluno (com filtros, pastas e paginação).
     *
     * @return array{lista: array, pastas: array, pasta_atual: ?array, total: int, materias: array, professores: array, page: int, per_page: int, total_pages: int}
     */
    public function listarParaAluno(int $turmaId, int $alunoId, array $filtros, bool $somenteRecuperacao): array
    {
        $filtroMateria = isset($filtros['materia_id']) ? (int) $filtros['materia_id'] : null;
        $filtroProfessor = isset($filtros['professor_id']) ? (int) $filtros['professor_id'] : null;
        $filtroTitulo = isset($filtros['titulo']) ? trim((string) $filtros['titulo']) : '';
        $filtroPasta = array_key_exists('pasta_id', $filtros) && $filtros['pasta_id'] !== null && $filtros['pasta_id'] !== ''
            ? (int) $filtros['pasta_id']
            : null;
        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $visibilityCond = ModuloArquivo::sqlVisibilidadeAluno();
        $paramsList = ['turma_id' => $turmaId, 'turma_id2' => $turmaId, 'aluno_id' => $alunoId];
        $where = [$visibilityCond];
        $params = $paramsList;

        $hasRecuperacaoCol = $this->temColunaRecuperacao();
        if ($hasRecuperacaoCol) {
            if ($somenteRecuperacao) {
                $where[] = 'ma.recuperacao = 1';
            } else {
                $where[] = '(ma.recuperacao = 0 OR ma.recuperacao IS NULL)';
            }
        } elseif ($somenteRecuperacao) {
            $where[] = '1 = 0';
        }

        $pastaAtual = null;
        $hasPastaCol = $this->temColunaPasta();
        $hasPastaTable = $hasPastaCol && $this->pastaModel->tabelaExiste();

        if ($hasPastaCol) {
            if ($filtroPasta !== null && $filtroPasta > 0) {
                $where[] = 'ma.pasta_id = :pasta_id';
                $params['pasta_id'] = $filtroPasta;
                if ($hasPastaTable) {
                    $pastaAtual = $this->pastaModel->findById($filtroPasta);
                }
            } elseif ($filtroPasta === null && !$filtroMateria && !$filtroProfessor && $filtroTitulo === '') {
                $where[] = 'ma.pasta_id IS NULL';
            }
        }

        if ($filtroMateria > 0) {
            $where[] = 'ma.materia_id = :materia_id';
            $params['materia_id'] = $filtroMateria;
        }
        if ($filtroProfessor > 0) {
            $where[] = 'ma.professor_id = :professor_id';
            $params['professor_id'] = $filtroProfessor;
        }
        if ($filtroTitulo !== '') {
            $where[] = '(ma.titulo LIKE :titulo OR ma.descricao LIKE :titulo2)';
            $params['titulo'] = '%' . $filtroTitulo . '%';
            $params['titulo2'] = '%' . $filtroTitulo . '%';
        }
        $whereSql = implode(' AND ', $where);

        $pastas = [];
        if ($hasPastaTable && $filtroPasta === null && !$filtroMateria && !$filtroProfessor && $filtroTitulo === '') {
            $pastaWhere = $visibilityCond;
            $pastaParams = $paramsList;
            if ($hasRecuperacaoCol) {
                if ($somenteRecuperacao) {
                    $pastaWhere .= ' AND ma.recuperacao = 1';
                } else {
                    $pastaWhere .= ' AND (ma.recuperacao = 0 OR ma.recuperacao IS NULL)';
                }
            } elseif ($somenteRecuperacao) {
                $pastaWhere .= ' AND 1 = 0';
            }
            $pastas = $this->db->fetchAll(
                "SELECT p.id, p.nome, p.cor,
                        COUNT(ma.id) as total_arquivos
                 FROM modulos_arquivos_pastas p
                 INNER JOIN modulos_arquivos ma ON ma.pasta_id = p.id
                 WHERE {$pastaWhere}
                 GROUP BY p.id, p.nome, p.cor
                 ORDER BY p.ordem ASC, p.nome ASC",
                $pastaParams
            ) ?: [];
        }

        $total = (int) ($this->db->fetch(
            "SELECT COUNT(*) as c FROM modulos_arquivos ma WHERE {$whereSql}",
            $params
        )['c'] ?? 0);

        $lista = $this->db->fetchAll(
            "SELECT ma.id, ma.titulo, ma.descricao, ma.created_at, m.nome as materia_nome,
             p.nome as professor_nome,
             (SELECT COUNT(*) FROM modulos_arquivos_anexos WHERE modulo_arquivo_id = ma.id) as total_anexos
             FROM modulos_arquivos ma
             LEFT JOIN materias m ON ma.materia_id = m.id
             LEFT JOIN professores p ON ma.professor_id = p.id
             WHERE {$whereSql}
             ORDER BY ma.created_at DESC
             LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        ) ?: [];

        $materiasWhere = $visibilityCond;
        $professoresWhere = $visibilityCond;
        $filtroParams = $paramsList;
        if ($hasRecuperacaoCol) {
            if ($somenteRecuperacao) {
                $materiasWhere .= ' AND ma.recuperacao = 1';
                $professoresWhere .= ' AND ma.recuperacao = 1';
            } else {
                $materiasWhere .= ' AND (ma.recuperacao = 0 OR ma.recuperacao IS NULL)';
                $professoresWhere .= ' AND (ma.recuperacao = 0 OR ma.recuperacao IS NULL)';
            }
        } elseif ($somenteRecuperacao) {
            $materiasWhere .= ' AND 1 = 0';
            $professoresWhere .= ' AND 1 = 0';
        }

        $materias = $this->db->fetchAll(
            "SELECT DISTINCT m.id, m.nome FROM materias m
             INNER JOIN modulos_arquivos ma ON ma.materia_id = m.id
             WHERE {$materiasWhere} ORDER BY m.nome",
            $filtroParams
        ) ?: [];
        $professores = $this->db->fetchAll(
            "SELECT DISTINCT p.id, p.nome FROM professores p
             INNER JOIN modulos_arquivos ma ON ma.professor_id = p.id
             WHERE {$professoresWhere} ORDER BY p.nome",
            $filtroParams
        ) ?: [];

        return [
            'lista' => $lista,
            'pastas' => $pastas,
            'pasta_atual' => $pastaAtual,
            'total' => $total,
            'materias' => $materias,
            'professores' => $professores,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            'filtro_pasta_id' => $filtroPasta,
            'filtro_materia_id' => $filtroMateria,
            'filtro_professor_id' => $filtroProfessor,
            'filtro_titulo' => $filtroTitulo,
        ];
    }

    public static function urlParaEmbed(string $url): string
    {
        $url = trim($url);
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return $url;
    }

    public static function anexoPathToMediaKey(string $caminho): string
    {
        $path = ltrim(str_replace('\\', '/', $caminho), '/');
        if (strpos($path, 'public/uploads/arquivos/') === 0) {
            return substr($path, strlen('public/uploads/arquivos/'));
        }
        if (strpos($path, 'arquivos/') === 0) {
            return substr($path, strlen('arquivos/'));
        }
        return basename($path);
    }

    public function videosComEmbed(int $moduloArquivoId): array
    {
        $videos = $this->videoModel->listByModuloArquivo($moduloArquivoId);
        foreach ($videos as &$v) {
            $v['embed_url'] = self::urlParaEmbed((string) $v['url']);
        }
        unset($v);
        return $videos;
    }

    private const EXTENSOES_PROFESSOR = [
        'pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'mp4', 'webm', 'ogg',
        'mp3', 'wav',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'csv',
    ];

    private const EXTENSOES_ADMIN = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif',
        'mp4', 'webm', 'ogg', 'mp3', 'wav',
    ];

    public static function mimePorExtensao(string $ext): string
    {
        $mimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain', 'csv' => 'text/csv',
        ];
        $ext = strtolower($ext);
        return $mimes[$ext] ?? 'application/octet-stream';
    }

    public static function detectMimeType(string $tmpPath, string $fallback = ''): string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpPath);
            if (!empty($mime)) {
                return (string) $mime;
            }
        }
        if (class_exists('finfo')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $tmpPath);
                @finfo_close($finfo);
                if (!empty($mime)) {
                    return (string) $mime;
                }
            }
        }
        return $fallback !== '' ? $fallback : 'application/octet-stream';
    }

    public function getProfessor(int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM professores WHERE id = :id', ['id' => $userId]) ?: null;
    }

    public function getTurmasProfessor(array $professor): array
    {
        $turmasIds = json_decode($professor['turmas'] ?? '[]', true);
        if (empty($turmasIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($turmasIds), '?'));
        return $this->db->fetchAll(
            "SELECT * FROM turmas WHERE id IN ($placeholders) AND ativo = 1 ORDER BY nome",
            $turmasIds
        ) ?: [];
    }

    public function getMateriasProfessor(array $professor): array
    {
        $nomes = json_decode($professor['materias'] ?? '[]', true);
        if (empty($nomes)) {
            return $this->db->fetchAll('SELECT * FROM materias ORDER BY nome') ?: [];
        }
        $placeholders = implode(',', array_fill(0, count($nomes), '?'));
        return $this->db->fetchAll(
            "SELECT * FROM materias WHERE nome IN ($placeholders) ORDER BY nome",
            $nomes
        ) ?: [];
    }

    public function getTurmasAtivas(): array
    {
        return $this->db->fetchAll('SELECT id, nome FROM turmas WHERE ativo = 1 ORDER BY nome ASC') ?: [];
    }

    public function getMateriasAtivas(): array
    {
        return $this->db->fetchAll('SELECT id, nome FROM materias ORDER BY nome ASC') ?: [];
    }

    public function getProfessoresAtivos(): array
    {
        return $this->db->fetchAll(
            'SELECT id, nome, email FROM professores WHERE ativo = 1 ORDER BY nome ASC'
        ) ?: [];
    }

    /**
     * @return array{lista: array, pastas: array, pasta_atual: ?array, pasta_atual_id: ?int}
     */
    public function listarParaProfessor(int $professorId, ?int $pastaId): array
    {
        $pastas = $this->pastaModel->listProfessor($professorId);
        $pastaAtual = null;
        if ($pastaId !== null) {
            foreach ($pastas as $p) {
                if ((int) $p['id'] === $pastaId) {
                    $pastaAtual = $p;
                    break;
                }
            }
        }
        $lista = $this->arquivoModel->listForProfessor($professorId, $pastaId);
        return [
            'lista' => $lista,
            'pastas' => $pastas,
            'pasta_atual' => $pastaAtual,
            'pasta_atual_id' => $pastaId,
        ];
    }

    public function pastasDoProfessor(int $professorId): array
    {
        return $this->pastaModel->listProfessor($professorId);
    }

    public function pastasAdmin(?int $parentId): array
    {
        return $this->pastaModel->listAdmin($parentId);
    }

    public function breadcrumbAdmin(array $pastaAtual): array
    {
        return $this->pastaModel->breadcrumbAdmin($pastaAtual);
    }

    public function temColunaParentPasta(): bool
    {
        return $this->pastaModel->temColunaParent();
    }

    public function criarPastaProfessor(int $professorId, string $nome, string $cor): array
    {
        $id = $this->pastaModel->createProfessor($nome, $cor, $professorId);
        return ['id' => $id, 'nome' => $nome, 'cor' => $cor];
    }

    public function renomearPastaProfessor(int $professorId, int $id, string $nome): bool
    {
        if (!$this->pastaModel->findByIdDoProfessor($id, $professorId)) {
            return false;
        }
        $this->pastaModel->rename($id, $nome);
        return true;
    }

    public function excluirPastaProfessor(int $professorId, int $id): bool
    {
        if (!$this->pastaModel->findByIdDoProfessor($id, $professorId)) {
            return false;
        }
        $this->arquivoModel->clearPasta($id, $professorId);
        $this->pastaModel->delete($id);
        return true;
    }

    public function moverArquivoParaPastaProfessor(int $professorId, int $arquivoId, ?int $pastaId): ?string
    {
        if (!$this->arquivoModel->findByIdDoProfessor($arquivoId, $professorId)) {
            return 'Arquivo não encontrado';
        }
        if ($pastaId !== null && !$this->pastaModel->findByIdDoProfessor($pastaId, $professorId)) {
            return 'Pasta não encontrada';
        }
        $this->arquivoModel->updatePasta($arquivoId, $pastaId);
        return null;
    }

    public function criarPastaAdmin(string $nome, string $cor, ?int $parentId): array
    {
        if ($parentId !== null && !$this->pastaModel->findByIdAdmin($parentId)) {
            $parentId = null;
        }
        $id = $this->pastaModel->createAdmin($nome, $cor, $parentId);
        return ['id' => $id, 'nome' => $nome, 'cor' => $cor, 'parent_id' => $parentId];
    }

    public function renomearPastaAdmin(int $id, string $nome): bool
    {
        if (!$this->pastaModel->findByIdAdmin($id)) {
            return false;
        }
        $this->pastaModel->rename($id, $nome);
        return true;
    }

    public function excluirPastaAdmin(int $id): bool
    {
        if (!$this->pastaModel->findByIdAdmin($id)) {
            return false;
        }
        $this->arquivoModel->clearPasta($id);
        $this->pastaModel->delete($id);
        return true;
    }

    public function moverArquivoParaPastaAdmin(int $arquivoId, ?int $pastaId): ?string
    {
        if (!$this->arquivoModel->findById($arquivoId)) {
            return 'Arquivo não encontrado';
        }
        if ($pastaId !== null && !$this->pastaModel->findByIdAdmin($pastaId)) {
            return 'Pasta não encontrada';
        }
        $this->arquivoModel->updatePasta($arquivoId, $pastaId);
        return null;
    }

    /**
     * @return array{ok: bool, error?: string, turma_ids?: array, aluno_id?: ?int}
     */
    public function resolverTurmasPublicacaoProfessor(array $professor, array $post): array
    {
        $turmasPermitidas = array_map('intval', json_decode($professor['turmas'] ?? '[]', true) ?: []);
        $alunoId = isset($post['aluno_id']) && (int) $post['aluno_id'] > 0 ? (int) $post['aluno_id'] : null;

        if ($alunoId) {
            $aluno = $this->db->fetch(
                'SELECT id, turma_id FROM alunos WHERE id = :id AND ativo = 1',
                ['id' => $alunoId]
            );
            if (!$aluno || !in_array((int) $aluno['turma_id'], $turmasPermitidas, true)) {
                return ['ok' => false, 'error' => 'Aluno não encontrado ou não pertence às suas turmas.'];
            }
            return ['ok' => true, 'turma_ids' => [(int) $aluno['turma_id']], 'aluno_id' => $alunoId];
        }

        $turmaIdsRaw = $post['turma_ids'] ?? [];
        if (!is_array($turmaIdsRaw)) {
            $turmaIdsRaw = [];
        }
        $turmaIds = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));
        if (isset($post['turma_id']) && (int) $post['turma_id'] > 0 && empty($turmaIds)) {
            $turmaIds = [(int) $post['turma_id']];
        }
        if (empty($turmaIds)) {
            return ['ok' => false, 'error' => 'Selecione ao menos uma turma ou um aluno específico.'];
        }
        foreach ($turmaIds as $tid) {
            if (!in_array($tid, $turmasPermitidas, true)) {
                return ['ok' => false, 'error' => 'Uma das turmas selecionadas não é permitida.'];
            }
        }
        return ['ok' => true, 'turma_ids' => $turmaIds, 'aluno_id' => null];
    }

    public function validarMateriaProfessor(array $professor, int $materiaId): bool
    {
        if ($materiaId <= 0) {
            return false;
        }
        $materias = $this->getMateriasProfessor($professor);
        foreach ($materias as $m) {
            if ((int) $m['id'] === $materiaId) {
                return true;
            }
        }
        return false;
    }

    public function validarPastaAdmin(?int $pastaId): ?int
    {
        if ($pastaId === null || $pastaId <= 0) {
            return null;
        }
        if (!$this->pastaModel->findByIdAdmin($pastaId)) {
            return null;
        }
        return $pastaId;
    }

    public function validarPastaProfessor(int $professorId, ?int $pastaId): ?int
    {
        if ($pastaId === null || $pastaId <= 0) {
            return null;
        }
        if (!$this->pastaModel->findByIdDoProfessor($pastaId, $professorId)) {
            return null;
        }
        return $pastaId;
    }

    /**
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function criarPublicacaoProfessor(int $professorId, array $post, array $files, array $config): array
    {
        $professor = $this->getProfessor($professorId);
        if (!$professor) {
            return ['ok' => false, 'error' => 'Professor não encontrado'];
        }

        $turmas = $this->resolverTurmasPublicacaoProfessor($professor, $post);
        if (!$turmas['ok']) {
            return ['ok' => false, 'error' => $turmas['error']];
        }

        $materiaId = (int) ($post['materia_id'] ?? 0);
        $titulo = trim((string) ($post['titulo'] ?? ''));
        $descricao = trim((string) ($post['descricao'] ?? ''));
        if (!$materiaId || $titulo === '' || !$this->validarMateriaProfessor($professor, $materiaId)) {
            return ['ok' => false, 'error' => 'Preencha disciplina e título.'];
        }

        $pastaId = $this->validarPastaProfessor(
            $professorId,
            isset($post['pasta_id']) && (int) $post['pasta_id'] > 0 ? (int) $post['pasta_id'] : null
        );

        $moduloId = $this->arquivoModel->create([
            'turma_id' => $turmas['turma_ids'][0],
            'materia_id' => $materiaId,
            'professor_id' => $professorId,
            'aluno_id' => $turmas['aluno_id'],
            'pasta_id' => $pastaId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'recuperacao' => !empty($post['recuperacao']),
        ]);
        $this->arquivoModel->syncTurmas($moduloId, $turmas['turma_ids']);
        $this->processarUploads($moduloId, $files, $config, self::EXTENSOES_PROFESSOR);
        $this->processarVideoUrls($moduloId, (string) ($post['video_urls'] ?? ''));

        return ['ok' => true, 'id' => $moduloId];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function atualizarPublicacaoProfessor(int $professorId, int $id, array $post, array $files, array $config): array
    {
        if (!$this->arquivoModel->findByIdDoProfessor($id, $professorId)) {
            return ['ok' => false, 'error' => 'Arquivo não encontrado.'];
        }

        $professor = $this->getProfessor($professorId);
        if (!$professor) {
            return ['ok' => false, 'error' => 'Professor não encontrado'];
        }

        $turmas = $this->resolverTurmasPublicacaoProfessor($professor, $post);
        if (!$turmas['ok']) {
            return ['ok' => false, 'error' => $turmas['error']];
        }

        $materiaId = (int) ($post['materia_id'] ?? 0);
        $titulo = trim((string) ($post['titulo'] ?? ''));
        $descricao = trim((string) ($post['descricao'] ?? ''));
        if (!$materiaId || $titulo === '' || !$this->validarMateriaProfessor($professor, $materiaId)) {
            return ['ok' => false, 'error' => 'Preencha disciplina e título.'];
        }

        $this->arquivoModel->update($id, [
            'turma_id' => $turmas['turma_ids'][0],
            'materia_id' => $materiaId,
            'aluno_id' => $turmas['aluno_id'],
            'titulo' => $titulo,
            'descricao' => $descricao,
            'recuperacao' => !empty($post['recuperacao']),
        ]);
        $this->arquivoModel->syncTurmas($id, $turmas['turma_ids']);

        $anexosManter = array_map('intval', array_filter($post['anexos_manter'] ?? [], 'strlen'));
        $this->removerAnexosNaoMantidos($id, $anexosManter, $config);
        $this->processarUploads($id, $files, $config, self::EXTENSOES_PROFESSOR);
        $this->videoModel->deleteByModuloArquivo($id);
        $this->processarVideoUrls($id, (string) ($post['video_urls'] ?? ''));

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function excluirPublicacaoProfessor(int $professorId, int $id, array $config): array
    {
        if (!$this->arquivoModel->findByIdDoProfessor($id, $professorId)) {
            return ['ok' => false, 'error' => 'Arquivo não encontrado'];
        }
        $this->removerAnexosFisicos($id, $config);
        $this->videoModel->deleteByModuloArquivo($id);
        $this->arquivoModel->delete($id);
        return ['ok' => true];
    }

    public function dadosEdicaoProfessor(int $professorId, int $id): ?array
    {
        $item = $this->arquivoModel->findByIdDoProfessor($id, $professorId);
        if (!$item) {
            return null;
        }
        $alunoAtual = null;
        if (!empty($item['aluno_id'])) {
            $alunoAtual = $this->db->fetch(
                'SELECT id, nome, turma_id FROM alunos WHERE id = :id',
                ['id' => $item['aluno_id']]
            );
        }
        return [
            'item' => $item,
            'anexos' => $this->anexoModel->listByModuloArquivo($id),
            'videos' => $this->videoModel->listByModuloArquivo($id),
            'item_turma_ids' => $this->arquivoModel->listTurmaIdsWithFallback($id),
            'aluno_atual' => $alunoAtual,
        ];
    }

    public function previewProfessor(int $professorId, int $id): ?array
    {
        $pub = $this->arquivoModel->findByIdDoProfessorComNomes($id, $professorId);
        if (!$pub) {
            return null;
        }
        return [
            'pub' => $pub,
            'anexos' => $this->anexoModel->listByModuloArquivo($id),
            'videos' => $this->videosComEmbed($id),
        ];
    }

    public function alunosPorTurma(array $professor, int $turmaId): array
    {
        if ($turmaId <= 0) {
            return [];
        }
        $turmasPermitidas = array_map('intval', json_decode($professor['turmas'] ?? '[]', true) ?: []);
        if (!in_array($turmaId, $turmasPermitidas, true)) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT id, nome FROM alunos WHERE turma_id = :tid AND ativo = 1 ORDER BY nome',
            ['tid' => $turmaId]
        ) ?: [];
    }

    public function processarVideoUrls(int $moduloArquivoId, string $raw): void
    {
        $raw = trim($raw);
        if ($raw === '') {
            return;
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ordem = 0;
        foreach ($lines as $line) {
            $url = trim($line);
            if ($url === '' || !preg_match('#^https?://#i', $url) || strlen($url) > 1024) {
                continue;
            }
            $ordem++;
            $this->videoModel->create($moduloArquivoId, $url, $ordem);
        }
    }

    public function processarUploads(int $moduloArquivoId, array $files, array $config, array $extensoesPermitidas): void
    {
        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $media = new MediaStorageService($config);
        $ordemBase = count($this->anexoModel->listByModuloArquivo($moduloArquivoId));
        $ordem = $ordemBase;

        foreach (['anexos', 'anexo'] as $field) {
            if (!isset($files[$field])) {
                continue;
            }
            $fileData = $files[$field];
            $multiple = is_array($fileData['name']);
            $names = $multiple ? $fileData['name'] : [$fileData['name']];
            $tmpNames = $multiple ? $fileData['tmp_name'] : [$fileData['tmp_name']];
            $errors = $multiple ? $fileData['error'] : [$fileData['error']];
            $sizes = $multiple ? $fileData['size'] : [$fileData['size']];

            for ($i = 0; $i < count($names); $i++) {
                if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($names[$i])) {
                    continue;
                }
                $ext = strtolower(pathinfo((string) $names[$i], PATHINFO_EXTENSION));
                if (!in_array($ext, $extensoesPermitidas, true)) {
                    continue;
                }
                $nomeSalvo = 'arq_' . $moduloArquivoId . '_' . time() . '_' . $i . '.' . $ext;
                $caminhoRel = 'arquivos/' . $nomeSalvo;
                $contentType = self::detectMimeType((string) $tmpNames[$i]);
                if ($media->put('arquivos', $nomeSalvo, $tmpNames[$i], $contentType)) {
                    $ordem++;
                    $this->anexoModel->create([
                        'modulo_arquivo_id' => $moduloArquivoId,
                        'caminho' => $caminhoRel,
                        'nome_original' => $names[$i],
                        'extensao' => $ext,
                        'tamanho' => (int) ($sizes[$i] ?? 0),
                        'ordem' => $ordem,
                    ]);
                } else {
                    error_log('ArquivosService: falha ao salvar anexo em storage (arquivos/' . $nomeSalvo . ')');
                }
            }
        }
    }

    public function removerAnexosFisicos(int $moduloArquivoId, array $config): void
    {
        $anexos = $this->anexoModel->listByModuloArquivo($moduloArquivoId);
        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $media = new MediaStorageService($config);
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../../');
        foreach ($anexos as $a) {
            $key = self::anexoPathToMediaKey((string) $a['caminho']);
            if ($key !== '') {
                $media->delete('arquivos', $key);
            }
            $full = $basePath . ltrim((string) $a['caminho'], '/');
            if (file_exists($full) && is_writable($full)) {
                @unlink($full);
            }
            $this->anexoModel->delete((int) $a['id']);
        }
    }

    public function removerAnexosNaoMantidos(int $moduloArquivoId, array $idsManter, array $config): void
    {
        $anexosAtuais = $this->anexoModel->listIdsCaminhosByModuloArquivo($moduloArquivoId);
        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $media = new MediaStorageService($config);
        $basePath = defined('ROOT_PATH') ? ROOT_PATH . '/' : (__DIR__ . '/../../../../');
        foreach ($anexosAtuais as $a) {
            if (in_array((int) $a['id'], $idsManter, true)) {
                continue;
            }
            $key = self::anexoPathToMediaKey((string) $a['caminho']);
            if ($key !== '') {
                $media->delete('arquivos', $key);
            }
            $full = $basePath . ltrim((string) $a['caminho'], '/');
            if (file_exists($full) && is_writable($full)) {
                @unlink($full);
            }
            $this->anexoModel->delete((int) $a['id']);
        }
    }

    /**
     * @return array{items: array, pagination: array, pastas: array, pasta_atual: ?array, breadcrumb: array, todas_pastas: array, has_parent_col: bool}
     */
    public function listarAdmin(array $filtros, ?int $pastaAtualId): array
    {
        $hasParentCol = $this->pastaModel->temColunaParent();
        $pastas = $this->pastaModel->listAdmin($pastaAtualId);
        $pastaAtual = null;
        if ($pastaAtualId !== null) {
            $pastaAtual = $this->pastaModel->findAdminCompleta($pastaAtualId);
        }
        $breadcrumb = $pastaAtual ? $this->pastaModel->breadcrumbAdmin($pastaAtual) : [];

        $temFiltroAtivo = !empty($filtros['materia_id'])
            || !empty($filtros['professor_id'])
            || !empty($filtros['turma_id'])
            || !empty($filtros['assunto']);

        $filtrosListagem = $filtros;
        if ($pastaAtualId !== null) {
            $filtrosListagem['pasta_id'] = $pastaAtualId;
        } elseif (!$temFiltroAtivo) {
            $filtrosListagem['pasta_id'] = 'null';
        }

        $perPage = 15;
        $page = max(1, (int) ($filtros['page'] ?? 1));
        $result = $this->arquivoModel->listAdmin($filtrosListagem, $perPage, $page);

        return [
            'items' => $result['items'],
            'pagination' => [
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'total_pages' => $result['total_pages'],
            ],
            'pastas' => $pastas,
            'pasta_atual' => $pastaAtual,
            'breadcrumb' => $breadcrumb,
            'todas_pastas' => $this->pastaModel->listAllAdmin(),
            'has_parent_col' => $hasParentCol,
        ];
    }

    public static function buildFilterQuery(array $filtros, array $extra = []): string
    {
        $params = array_merge([
            'materia_id' => $filtros['materia_id'] ?? 0,
            'professor_id' => $filtros['professor_id'] ?? 0,
            'turma_id' => $filtros['turma_id'] ?? 0,
            'assunto' => $filtros['assunto'] ?? '',
            'data_de' => $filtros['data_de'] ?? '',
            'data_ate' => $filtros['data_ate'] ?? '',
            'page' => $filtros['page'] ?? 1,
        ], $extra);
        $query = array_filter($params, static function ($value) {
            return $value !== '' && $value !== 0 && $value !== null;
        });
        return empty($query) ? '' : ('?' . http_build_query($query));
    }

    public static function sanitizeDescricao(string $descricao): string
    {
        return \App\Utils\HtmlSanitizer::clean($descricao);
    }

    /**
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function criarAdmin(array $post, array $file, array $config): array
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Selecione um arquivo válido para enviar.'];
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $titulo = trim((string) ($post['titulo'] ?? ''));
        $descricao = self::sanitizeDescricao(trim((string) ($post['descricao'] ?? '')));
        $turmaIdsRaw = $post['turma_ids'] ?? [];
        if (!is_array($turmaIdsRaw)) {
            $turmaIdsRaw = [];
        }
        $turmaIds = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));

        if ($titulo === '') {
            return ['ok' => false, 'error' => 'O título do arquivo é obrigatório.'];
        }
        if ($originalName === '' || $tmpPath === '' || !in_array($ext, self::EXTENSOES_ADMIN, true)) {
            return ['ok' => false, 'error' => 'Formato não permitido para arquivo.'];
        }
        if (empty($turmaIds)) {
            return ['ok' => false, 'error' => 'Selecione pelo menos uma turma.'];
        }

        require_once __DIR__ . '/../../../Services/MediaStorageService.php';
        $media = new MediaStorageService($config);
        $safeKey = 'arquivo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $mime = self::detectMimeType($tmpPath, (string) ($file['type'] ?? ''));
        if (!$media->put('arquivos', $safeKey, $tmpPath, $mime)) {
            return ['ok' => false, 'error' => 'Falha ao salvar arquivo no storage.'];
        }

        $pastaId = $this->validarPastaAdmin(
            isset($post['pasta_id']) && (int) $post['pasta_id'] > 0 ? (int) $post['pasta_id'] : null
        );
        $materiaId = (int) ($post['materia_id'] ?? 0);
        $professorId = (int) ($post['professor_id'] ?? 0);

        $moduloId = $this->arquivoModel->create([
            'turma_id' => $turmaIds[0],
            'materia_id' => $materiaId > 0 ? $materiaId : null,
            'professor_id' => $professorId > 0 ? $professorId : null,
            'aluno_id' => null,
            'pasta_id' => $pastaId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'recuperacao' => !empty($post['recuperacao']),
        ]);
        $this->arquivoModel->syncTurmas($moduloId, $turmaIds);
        $this->anexoModel->create([
            'modulo_arquivo_id' => $moduloId,
            'caminho' => 'arquivos/' . $safeKey,
            'nome_original' => $originalName,
            'extensao' => $ext,
            'tamanho' => $size,
            'ordem' => 1,
        ]);

        return ['ok' => true, 'id' => $moduloId];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function atualizarAdmin(int $id, array $post): array
    {
        if ($id <= 0 || !$this->arquivoModel->findById($id)) {
            return ['ok' => false, 'error' => 'Arquivo não encontrado.'];
        }

        $titulo = trim((string) ($post['titulo'] ?? ''));
        $descricao = self::sanitizeDescricao(trim((string) ($post['descricao'] ?? '')));
        $turmaIdsRaw = $post['turma_ids'] ?? [];
        if (!is_array($turmaIdsRaw)) {
            $turmaIdsRaw = [];
        }
        $turmaIds = array_values(array_unique(array_map('intval', array_filter($turmaIdsRaw))));

        if ($titulo === '' || empty($turmaIds)) {
            return ['ok' => false, 'error' => 'Dados inválidos para atualização.'];
        }

        $this->arquivoModel->updateAdmin($id, [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'materia_id' => (int) ($post['materia_id'] ?? 0),
            'professor_id' => (int) ($post['professor_id'] ?? 0),
            'turma_id' => $turmaIds[0],
            'recuperacao' => !empty($post['recuperacao']),
        ]);
        $this->arquivoModel->syncTurmas($id, $turmaIds);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function excluirAdmin(int $id, array $config): array
    {
        if ($id <= 0 || !$this->arquivoModel->findById($id)) {
            return ['ok' => false, 'error' => 'Arquivo inválido.'];
        }
        $this->removerAnexosFisicos($id, $config);
        $this->arquivoModel->delete($id);
        return ['ok' => true];
    }

    public function getItemAdmin(int $id): ?array
    {
        return $this->arquivoModel->findByIdComNomes($id);
    }

    public function getTurmaIdsAdmin(int $id): array
    {
        return $this->arquivoModel->listTurmaIdsWithFallback($id);
    }

    public function getAnexosAdmin(int $id): array
    {
        return $this->anexoModel->listByModuloArquivo($id);
    }

    public function professorPodeVerAnexo(int $anexoId, int $professorId): ?array
    {
        $anexo = $this->anexoModel->findById($anexoId);
        if (!$anexo) {
            return null;
        }
        if (!$this->arquivoModel->professorPodeVer((int) $anexo['modulo_arquivo_id'], $professorId)) {
            return null;
        }
        return $anexo;
    }
}
}
