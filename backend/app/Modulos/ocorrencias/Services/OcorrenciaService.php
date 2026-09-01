<?php

namespace App\Modulos\Ocorrencias\Services;

require_once __DIR__ . '/../Models/Ocorrencia.php';
require_once __DIR__ . '/../../../Models/Education/ClassDiary.php';

use App\Modulos\Ocorrencias\Models\Ocorrencia;
use ClassDiary;
use Database;

/**
 * EducaTudo - Service de Ocorrência do aluno
 * Único ponto de criação/acompanhamento. Professor e admin gravam no mesmo registro.
 */
class OcorrenciaService
{
    private Ocorrencia $ocorrencia;
    private ClassDiary $diario;

    public function __construct(?Ocorrencia $ocorrencia = null, ?ClassDiary $diario = null)
    {
        $this->ocorrencia = $ocorrencia ?? new Ocorrencia();
        $this->diario = $diario ?? new ClassDiary();
    }

    public function model(): Ocorrencia
    {
        return $this->ocorrencia;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criar(array $input, int $usuarioId, string $perfil = 'admin', int $professorId = 0, array $files = []): array
    {
        $alunos = $input['alunos'] ?? [];
        if (!is_array($alunos)) {
            $alunos = [];
        }
        if ((int) ($input['aluno_id'] ?? 0) > 0) {
            $alunos[] = (int) $input['aluno_id'];
        }

        $diarioAulaId = (int) ($input['diario_aula_id'] ?? 0);
        $aula = null;

        if ($perfil === 'professor') {
            if ($professorId <= 0 || $diarioAulaId <= 0) {
                return ['success' => false, 'error' => 'Ocorrência do professor precisa nascer de uma aula'];
            }
            $aula = $this->aulaDoProfessor($diarioAulaId, $professorId);
            if (!$aula) {
                return ['success' => false, 'error' => 'Aula do diário não encontrada'];
            }
            $alunoUnico = (int) ($input['aluno_id'] ?? 0);
            $alunos = $alunoUnico > 0 ? [$alunoUnico] : [];
        }

        $alunos = array_values(array_unique(array_map('intval', $alunos)));
        $alunos = array_values(array_filter($alunos, static fn($id) => $id > 0));
        if ($alunos === []) {
            return ['success' => false, 'error' => 'Selecione pelo menos um aluno'];
        }

        $titulo = trim((string) ($input['titulo'] ?? ''));
        $detalhe = trim((string) ($input['detalhe'] ?? ''));
        $nivel = trim((string) ($input['nivel_gravidade'] ?? ''));
        $dataOcorrencia = trim((string) ($input['data_ocorrencia'] ?? ''));

        if ($titulo === '' || $detalhe === '' || $nivel === '' || $dataOcorrencia === '') {
            return ['success' => false, 'error' => 'Preencha data, título, detalhe e gravidade'];
        }
        if (!isset(Ocorrencia::GRAVIDADES[$nivel])) {
            return ['success' => false, 'error' => 'Nível de gravidade inválido'];
        }

        $ts = strtotime($dataOcorrencia);
        if ($ts === false) {
            return ['success' => false, 'error' => 'Data da ocorrência inválida'];
        }

        $categoriaId = (int) ($input['categoria_id'] ?? 0);
        if ($this->ocorrencia->schemaEstendido()) {
            if ($categoriaId <= 0 || !$this->ocorrencia->categoriaExiste($categoriaId)) {
                return ['success' => false, 'error' => 'Selecione uma categoria'];
            }
        } else {
            $categoriaId = 0;
        }

        if ($aula === null && $diarioAulaId > 0) {
            $aula = $this->diario->getAula($diarioAulaId);
            if (!$aula) {
                return ['success' => false, 'error' => 'Aula do diário não encontrada'];
            }
        }

        foreach ($alunos as $alunoId) {
            $dados = $this->ocorrencia->dadosAluno($alunoId);
            if (!$dados) {
                return ['success' => false, 'error' => 'Aluno não encontrado'];
            }
            if ($aula && (int) ($dados['turma_id'] ?? 0) !== (int) $aula['turma_id']) {
                return ['success' => false, 'error' => 'O aluno não pertence à turma desta aula'];
            }
        }

        $alunoPrincipal = $this->ocorrencia->dadosAluno($alunos[0]);
        if (!$alunoPrincipal) {
            return ['success' => false, 'error' => 'Aluno não encontrado'];
        }

        $enviarPais = $perfil === 'admin' && !empty($input['enviar_pais']) ? 1 : 0;
        $retorno = trim((string) ($input['retorno_em'] ?? ''));
        $retornoSql = null;
        if ($retorno !== '') {
            $rt = strtotime($retorno);
            if ($rt !== false) {
                $retornoSql = date('Y-m-d', $rt);
            }
        }

        $turmaId = $aula ? (int) $aula['turma_id'] : (int) ($alunoPrincipal['turma_id'] ?? 0);
        $materiaId = $aula ? (int) $aula['materia_id'] : 0;
        $anoLetivoId = (int) ($alunoPrincipal['ano_letivo_id'] ?? 0);
        if ($turmaId > 0 && $anoLetivoId <= 0) {
            $anoLetivoId = $this->anoLetivoIdDaTurma($turmaId);
        }

        try {
            $id = $this->ocorrencia->create([
                'aluno_id' => $alunos[0],
                'categoria_id' => $categoriaId > 0 ? $categoriaId : null,
                'data_ocorrencia' => date('Y-m-d H:i:s', $ts),
                'titulo' => mb_substr($titulo, 0, 120),
                'detalhe' => $detalhe,
                'nivel_gravidade' => $nivel,
                'status' => 'aberta',
                'turma_id' => $turmaId > 0 ? $turmaId : null,
                'ano_letivo_id' => $anoLetivoId > 0 ? $anoLetivoId : null,
                'diario_aula_id' => $diarioAulaId > 0 ? $diarioAulaId : null,
                'materia_id' => $materiaId > 0 ? $materiaId : null,
                'local' => mb_substr(trim((string) ($input['local'] ?? '')), 0, 120) ?: null,
                'encaminhamento' => trim((string) ($input['encaminhamento'] ?? '')) ?: null,
                'testemunhas' => trim((string) ($input['testemunhas'] ?? '')) ?: null,
                'retorno_em' => $retornoSql,
                'enviar_pais' => $enviarPais,
                'responsavel_comunicado_em' => $enviarPais ? date('Y-m-d H:i:s') : null,
                'criado_por' => $usuarioId,
            ], $alunos);
            $this->ocorrencia->registrarHistorico($id, $usuarioId, 'criou', null);
        } catch (Throwable $e) {
            error_log('OcorrenciaService: falha ao criar — ' . $e->getMessage());
            return ['success' => false, 'error' => 'Não foi possível salvar a ocorrência'];
        }

        $anexosSalvos = 0;
        try {
            $anexosSalvos = $this->salvarAnexos($id, $files);
        } catch (Throwable $e) {
            error_log('OcorrenciaService: falha ao anexar arquivos — ' . $e->getMessage());
        }

        return ['success' => true, 'id' => $id, 'anexos' => $anexosSalvos];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function alterarStatus(int $id, string $status, int $usuarioId, ?string $motivo = null): array
    {
        if (!isset(Ocorrencia::STATUS[$status])) {
            return ['success' => false, 'error' => 'Status inválido'];
        }
        $atual = $this->ocorrencia->findById($id);
        if (!$atual) {
            return ['success' => false, 'error' => 'Ocorrência não encontrada'];
        }
        $this->ocorrencia->atualizarStatus($id, $status, $usuarioId);
        $this->ocorrencia->registrarHistorico($id, $usuarioId, $status === 'encerrada' ? 'encerrou' : 'acompanhou', $motivo);
        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function definirVisibilidadePais(int $id, bool $enviarPais, int $usuarioId): array
    {
        $atual = $this->ocorrencia->findById($id);
        if (!$atual) {
            return ['success' => false, 'error' => 'Ocorrência não encontrada'];
        }
        $this->ocorrencia->atualizarComunicacaoPais($id, $enviarPais);
        $this->ocorrencia->registrarHistorico(
            $id,
            $usuarioId,
            $enviarPais ? 'liberou_pais' : 'ocultou_pais',
            null
        );
        return ['success' => true];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function salvarEncaminhamento(int $id, string $encaminhamento, int $usuarioId): array
    {
        $atual = $this->ocorrencia->findById($id);
        if (!$atual) {
            return ['success' => false, 'error' => 'Ocorrência não encontrada'];
        }
        $this->ocorrencia->atualizarEncaminhamento($id, trim($encaminhamento));
        $this->ocorrencia->registrarHistorico($id, $usuarioId, 'alterou', 'Atualizou encaminhamento');
        return ['success' => true];
    }

    /**
     * @return array{success: bool, id?: int, error?: string}
     */
    public function criarCategoria(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'error' => 'Informe o nome da categoria'];
        }
        if (!$this->ocorrencia->schemaEstendido()) {
            return ['success' => false, 'error' => 'Execute a migration de ocorrências antes de cadastrar categorias'];
        }
        $id = $this->ocorrencia->criarCategoria(mb_substr($nome, 0, 80));
        return ['success' => true, 'id' => $id];
    }

    /**
     * @param array<string,mixed> $files Campo $_FILES['anexos']
     * @return int Quantidade de arquivos gravados
     */
    public function salvarAnexos(int $ocorrenciaId, array $files): int
    {
        if ($ocorrenciaId <= 0 || empty($files['name']) || !$this->ocorrencia->schemaAnexos()) {
            return 0;
        }
        $names = $files['name'];
        if (!is_array($names)) {
            $names = [$names];
            $files = [
                'name' => $names,
                'tmp_name' => (array) ($files['tmp_name'] ?? []),
                'error' => (array) ($files['error'] ?? []),
                'size' => (array) ($files['size'] ?? []),
                'type' => (array) ($files['type'] ?? []),
            ];
        }
        $count = count($names);
        if ($count > 8) {
            $count = 8;
        }
        $gravados = 0;
        for ($i = 0; $i < $count; $i++) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string) ($files['tmp_name'][$i] ?? '');
            $nome = basename((string) ($names[$i] ?? 'arquivo'));
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            $gravado = $this->gravarArquivoAnexo($ocorrenciaId, $tmp, $nome, (int) ($files['size'][$i] ?? 0));
            if ($gravado) {
                $this->ocorrencia->inserirAnexo(
                    $ocorrenciaId,
                    $gravado['nome'],
                    $gravado['caminho'],
                    $gravado['mime'],
                    $gravado['tamanho']
                );
                $gravados++;
            }
        }
        return $gravados;
    }

    /**
     * @return array{nome:string,caminho:string,mime:string,tamanho:int}|null
     */
    private function gravarArquivoAnexo(int $ocorrenciaId, string $tmp, string $nomeOriginal, int $tamanho): ?array
    {
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];
        if ($tamanho <= 0 || $tamanho > 10 * 1024 * 1024) {
            return null;
        }
        if (!function_exists('finfo_open')) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!isset($allowed[$mime])) {
            return null;
        }
        $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : '';
        if ($slug === '') {
            return null;
        }
        $dir = dirname(__DIR__, 4) . '/storage/uploads/' . $slug . '/ocorrencias/' . $ocorrenciaId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        $filename = $slug . '_' . uniqid('', true) . '.' . $allowed[$mime];
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            return null;
        }
        return [
            'nome' => $nomeOriginal !== '' ? $nomeOriginal : $filename,
            'caminho' => $slug . '/ocorrencias/' . $ocorrenciaId . '/' . $filename,
            'mime' => $mime,
            'tamanho' => $tamanho,
        ];
    }

    public function caminhoFisicoAnexo(string $caminho): ?string
    {
        $caminho = str_replace('\\', '/', $caminho);
        $slug = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_SLUG) : '';
        if ($slug === '' || $caminho === '' || strpos($caminho, '..') !== false) {
            return null;
        }
        if (strpos($caminho, $slug . '/') !== 0) {
            return null;
        }
        $base = realpath(dirname(__DIR__, 4) . '/storage/uploads');
        $full = realpath(dirname(__DIR__, 4) . '/storage/uploads/' . $caminho);
        $prefix = $base !== false ? rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
        if ($base === false || $full === false || $prefix === '' || strpos($full, $prefix) !== 0) {
            return null;
        }
        return is_file($full) ? $full : null;
    }

    public function aulaDoProfessor(int $aulaId, int $professorId): ?array
    {
        $aula = $this->diario->getAula($aulaId);
        if (!$aula || (int) $aula['professor_id'] !== $professorId) {
            return null;
        }
        return $aula;
    }

    private function anoLetivoIdDaTurma(int $turmaId): int
    {
        $db = Database::getInstance();
        try {
            $row = $db->fetch(
                "SELECT ano_letivo_id, ano_letivo FROM turmas WHERE id = :id LIMIT 1",
                ['id' => $turmaId]
            );
        } catch (Throwable $e) {
            $row = $db->fetch("SELECT ano_letivo FROM turmas WHERE id = :id LIMIT 1", ['id' => $turmaId]);
        }
        if (!$row) {
            return 0;
        }
        $id = (int) ($row['ano_letivo_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
        $ano = (int) ($row['ano_letivo'] ?? 0);
        if ($ano <= 0) {
            return 0;
        }
        try {
            $al = $db->fetch("SELECT id FROM ano_letivo WHERE ano = :ano LIMIT 1", ['ano' => $ano]);
            return (int) ($al['id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}
