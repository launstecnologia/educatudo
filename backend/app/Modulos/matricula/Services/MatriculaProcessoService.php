<?php

namespace App\Modulos\Matricula\Services;

require_once __DIR__ . '/../Models/MatriculaProcesso.php';
require_once __DIR__ . '/../../../Services/AlunoMovimentacaoService.php';
require_once __DIR__ . '/../../../Models/User/Student.php';

use App\Modulos\Matricula\Models\MatriculaProcesso;
use App\Services\AlunoMovimentacaoService;
use Database;
use Student;

/**
 * Orquestração do processo de matrícula (contrato, assinatura, enturmação).
 * Não materializa cobrança Asaas — apenas escolhe plano e gera contrato.
 */
class MatriculaProcessoService
{
    public const DOCUMENTOS_ASSINATURA = [
        'contrato_matricula' => 'Contrato de matrícula',
        'declaracao_ficha_matricula' => 'Ficha de matrícula',
    ];

    public const PAGANTE_MODOS = [
        'um' => 'Um pagante (100%)',
        'dois_contratos' => 'Dois pagantes (contratos separados)',
        'dois_mesmo' => 'Dois pagantes (mesmo contrato)',
        'tres_contratos' => 'Três pagantes',
    ];

    /** Tipos de contrato configuráveis (espelham categorias financeiras + matrícula). */
    public const TIPOS_CONTRATO = [
        'matricula' => 'Matrícula / Prestação de serviços',
        'mensalidade' => 'Mensalidade',
        'material_didatico' => 'Material didático',
        'uniforme' => 'Uniforme',
        'taxa' => 'Taxa',
        'outros' => 'Outros',
    ];

    private $db;
    private MatriculaProcesso $model;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->model = new MatriculaProcesso($this->db);
    }

    public function getModel(): MatriculaProcesso
    {
        return $this->model;
    }

    public static function transicoesPermitidas(): array
    {
        return [
            'rascunho' => ['aguardando_contrato', 'aguardando_assinatura', 'confirmada', 'enturmada', 'lista_espera', 'abandonada', 'cancelada'],
            'aguardando_contrato' => ['aguardando_assinatura', 'rascunho', 'confirmada', 'enturmada', 'lista_espera', 'abandonada', 'cancelada'],
            'aguardando_assinatura' => ['aguardando_contrato', 'aguardando_assinatura', 'confirmada', 'enturmada', 'abandonada', 'cancelada'],
            'confirmada' => ['enturmada', 'cancelada'],
            'enturmada' => ['cancelada'],
            'lista_espera' => ['rascunho', 'aguardando_contrato', 'aguardando_assinatura', 'cancelada', 'abandonada'],
            'abandonada' => ['rascunho', 'cancelada'],
            'cancelada' => ['rascunho'],
        ];
    }

    public function podeTransicionar(string $de, string $para): bool
    {
        $mapa = self::transicoesPermitidas();
        $de = strtolower(trim($de));
        $para = strtolower(trim($para));
        if ($de === $para) {
            return true;
        }
        return in_array($para, $mapa[$de] ?? [], true);
    }

    public function prefillFromAluno(int $alunoId): array
    {
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome AS turma_nome, t.serie AS turma_serie, t.id AS turma_id_atual
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = ?",
            [$alunoId]
        );
        if (!$aluno) {
            return [];
        }

        $resp = $this->db->fetch(
            "SELECT r.nome, r.cpf, r.email, r.telefone, ar.tipo_vinculo
             FROM alunos_responsaveis ar
             INNER JOIN responsaveis r ON r.id = ar.responsavel_id
             WHERE ar.aluno_id = ? AND ar.is_financeiro = 1 AND ar.ativo = 1
             LIMIT 1",
            [$alunoId]
        ) ?: $this->db->fetch(
            "SELECT r.nome, r.cpf, r.email, r.telefone, ar.tipo_vinculo
             FROM alunos_responsaveis ar
             INNER JOIN responsaveis r ON r.id = ar.responsavel_id
             WHERE ar.aluno_id = ? AND ar.ativo = 1
             ORDER BY ar.id ASC LIMIT 1",
            [$alunoId]
        ) ?: [];

        $anosNaEscola = (int) ($this->db->fetch(
            'SELECT COUNT(DISTINCT ano_letivo_id) AS total FROM matricula WHERE aluno_id = ?',
            [$alunoId]
        )['total'] ?? 1);

        return [
            'aluno_id' => $alunoId,
            'aluno_nome' => $aluno['nome'] ?? '',
            'aluno_cpf' => $aluno['cpf'] ?? '',
            'aluno_rg' => $aluno['rg'] ?? null,
            'aluno_data_nasc' => $aluno['data_nasc'] ?? null,
            'aluno_genero' => $aluno['genero'] ?? $aluno['sexo'] ?? null,
            'aluno_email' => $aluno['email'] ?? null,
            'aluno_telefone' => $aluno['telefone'] ?? $aluno['celular'] ?? null,
            'aluno_endereco' => trim(implode(', ', array_filter([
                $aluno['logradouro'] ?? '',
                $aluno['numero'] ?? '',
                $aluno['bairro'] ?? '',
                $aluno['cidade'] ?? '',
                $aluno['uf'] ?? '',
            ]))) ?: null,
            'resp_nome' => $resp['nome'] ?? '',
            'resp_cpf' => $resp['cpf'] ?? '',
            'resp_email' => $resp['email'] ?? '',
            'resp_telefone' => $resp['telefone'] ?? '',
            'resp_parentesco' => $resp['tipo_vinculo'] ?? '',
            'turma_id_atual' => $aluno['turma_id_atual'] ?? null,
            'turma_nome' => $aluno['turma_nome'] ?? '',
            'anos_na_escola' => $anosNaEscola,
        ];
    }

    public function generateContratoToken(int $enrollmentId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->model->update($enrollmentId, ['contrato_token' => $token]);
        return $token;
    }

    /** Parentesco aceito na captação pública. */
    public const CAPTACAO_PARENTESCOS = ['pai', 'mae', 'avo', 'tio', 'responsavel', 'outro'];

    /**
     * Captação pública de interesse (site) — cria processo em rascunho.
     *
     * @param array<string,mixed> $input
     * @throws \InvalidArgumentException
     */
    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files  $_FILES
     */
    public function criarCaptacaoInteresse(array $input, array $files = []): int
    {
        $alunoNome = mb_substr(trim((string) ($input['aluno_nome'] ?? '')), 0, 255);
        if ($alunoNome === '') {
            throw new \InvalidArgumentException('Informe o nome do aluno.');
        }

        $dataNasc = trim((string) ($input['aluno_data_nasc'] ?? ''));
        if ($dataNasc === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNasc)) {
            throw new \InvalidArgumentException('Informe a data de nascimento do aluno.');
        }

        $alunoCpf = preg_replace('/\D+/', '', (string) ($input['aluno_cpf'] ?? '')) ?? '';
        if (strlen($alunoCpf) !== 11) {
            throw new \InvalidArgumentException('Informe o CPF do aluno com 11 dígitos.');
        }

        $anoLetivoId = (int) ($input['ano_letivo_id'] ?? 0);
        if ($anoLetivoId <= 0) {
            throw new \InvalidArgumentException('Selecione o ano letivo.');
        }
        $ano = $this->db->fetch('SELECT id FROM ano_letivo WHERE id = ? LIMIT 1', [$anoLetivoId]);
        if (!$ano) {
            throw new \InvalidArgumentException('Ano letivo inválido.');
        }

        $turmaId = (int) ($input['turma_id'] ?? 0);
        if ($turmaId > 0) {
            $turma = $this->db->fetch('SELECT id FROM turmas WHERE id = ? AND ativo = 1 LIMIT 1', [$turmaId]);
            if (!$turma) {
                throw new \InvalidArgumentException('Turma inválida.');
            }
        }

        $alunoEmail = mb_substr(trim((string) ($input['aluno_email'] ?? '')), 0, 255);
        if ($alunoEmail !== '' && !filter_var($alunoEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail do aluno inválido.');
        }

        $endParts = [
            'aluno_endereco' => mb_substr(trim((string) ($input['aluno_endereco'] ?? '')), 0, 255),
            'aluno_end_numero' => mb_substr(trim((string) ($input['aluno_end_numero'] ?? '')), 0, 30),
            'aluno_end_complemento' => mb_substr(trim((string) ($input['aluno_end_complemento'] ?? '')), 0, 80),
            'aluno_end_bairro' => mb_substr(trim((string) ($input['aluno_end_bairro'] ?? '')), 0, 120),
            'aluno_end_cidade' => mb_substr(trim((string) ($input['aluno_end_cidade'] ?? '')), 0, 120),
            'aluno_end_uf' => strtoupper(mb_substr(trim((string) ($input['aluno_end_uf'] ?? '')), 0, 2)),
            'aluno_end_cep' => preg_replace('/\D+/', '', (string) ($input['aluno_end_cep'] ?? '')) ?? '',
        ];
        $alunoEndereco = $endParts['aluno_endereco'];
        $montado = $this->montarEnderecoAluno($endParts);
        if ($alunoEndereco === '' && $montado !== '') {
            $alunoEndereco = $montado;
        }

        $responsaveis = $this->extrairResponsaveisDoPost($input);
        if ($responsaveis === []) {
            throw new \InvalidArgumentException('Informe ao menos um responsável.');
        }
        if (count($responsaveis) > 8) {
            $responsaveis = array_slice($responsaveis, 0, 8);
        }
        foreach ($responsaveis as $r) {
            $emailExtra = trim((string) ($r['email'] ?? ''));
            if ($emailExtra !== '' && !filter_var($emailExtra, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('E-mail do responsável inválido.');
            }
        }
        $primario = $responsaveis[0];
        foreach ($responsaveis as $r) {
            if (!empty($r['is_pedagogico'])) {
                $primario = $r;
                break;
            }
        }
        $respNome = mb_substr(trim((string) ($primario['nome'] ?? '')), 0, 255);
        $respTelefone = mb_substr(trim((string) ($primario['telefone'] ?? '')), 0, 30);
        $respEmail = mb_substr(trim((string) ($primario['email'] ?? '')), 0, 255);
        if ($respNome === '') {
            throw new \InvalidArgumentException('Informe o nome do responsável.');
        }
        if ($respTelefone === '' && $respEmail === '') {
            throw new \InvalidArgumentException('Informe telefone ou e-mail do responsável.');
        }
        if ($respEmail !== '' && !filter_var($respEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('E-mail do responsável inválido.');
        }

        $observacoes = trim((string) ($input['observacoes'] ?? ''));
        if ($observacoes !== '') {
            $observacoes = mb_substr($observacoes, 0, 2000);
        }

        $this->validarUploadsCaptacao($files['arquivo'] ?? []);

        $id = $this->model->create([
            'tipo' => 'nova',
            'status' => 'rascunho',
            'origem' => 'site',
            'aluno_nome' => $alunoNome,
            'aluno_data_nasc' => $dataNasc,
            'aluno_cpf' => $alunoCpf,
            'aluno_rg' => mb_substr(trim((string) ($input['aluno_rg'] ?? '')), 0, 30) ?: null,
            'aluno_email' => $alunoEmail !== '' ? $alunoEmail : null,
            'aluno_telefone' => mb_substr(trim((string) ($input['aluno_telefone'] ?? '')), 0, 30) ?: null,
            'aluno_escola_anterior' => mb_substr(trim((string) ($input['aluno_escola_anterior'] ?? '')), 0, 255) ?: null,
            'aluno_endereco' => $alunoEndereco !== '' ? $alunoEndereco : null,
            'aluno_end_numero' => $endParts['aluno_end_numero'] !== '' ? $endParts['aluno_end_numero'] : null,
            'aluno_end_complemento' => $endParts['aluno_end_complemento'] !== '' ? $endParts['aluno_end_complemento'] : null,
            'aluno_end_bairro' => $endParts['aluno_end_bairro'] !== '' ? $endParts['aluno_end_bairro'] : null,
            'aluno_end_cidade' => $endParts['aluno_end_cidade'] !== '' ? $endParts['aluno_end_cidade'] : null,
            'aluno_end_uf' => $endParts['aluno_end_uf'] !== '' ? $endParts['aluno_end_uf'] : null,
            'aluno_end_cep' => $endParts['aluno_end_cep'] !== '' ? $endParts['aluno_end_cep'] : null,
            'resp_nome' => $respNome,
            'resp_cpf' => preg_replace('/\D+/', '', (string) ($primario['cpf'] ?? $primario['documento'] ?? '')) ?: null,
            'resp_telefone' => $respTelefone !== '' ? $respTelefone : null,
            'resp_email' => $respEmail !== '' ? $respEmail : null,
            'resp_parentesco' => mb_substr(trim((string) ($primario['tipo_vinculo'] ?? '')), 0, 80) ?: null,
            'resp_endereco' => trim((string) ($primario['endereco'] ?? '')) ?: null,
            'ano_letivo_id' => $anoLetivoId,
            'turma_id' => $turmaId > 0 ? $turmaId : null,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'criado_por' => null,
        ]);

        $this->gravarResponsaveisCaptacao($id, $responsaveis);
        $this->anexarDocumentosCaptacao($id, $files['arquivo'] ?? [], $input['tipo_documento'] ?? []);

        return $id;
    }

    /**
     * @param array<string,mixed> $filesField
     */
    private function validarUploadsCaptacao(array $filesField): void
    {
        if ($filesField === []) {
            return;
        }
        $names = $filesField['name'] ?? [];
        $tmps = $filesField['tmp_name'] ?? [];
        $sizes = $filesField['size'] ?? [];
        $errors = $filesField['error'] ?? [];
        if (!is_array($names)) {
            $names = [$names];
            $tmps = [$tmps];
            $sizes = [$sizes];
            $errors = [$errors];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $count = 0;
        foreach ($names as $i => $_nome) {
            if ((int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string) ($tmps[$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            $count++;
            if ($count > 8) {
                throw new \InvalidArgumentException('Envie no máximo 8 documentos.');
            }
            if ((int) ($sizes[$i] ?? 0) > 10 * 1024 * 1024) {
                throw new \InvalidArgumentException('Cada documento deve ter no máximo 10MB.');
            }
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, $allowed, true)) {
                throw new \InvalidArgumentException('Documento inválido. Envie PDF, JPG ou PNG.');
            }
        }
    }

    /**
     * Captação pública não pede rateio: o primeiro responsável fica como acadêmico/financeiro (100%).
     *
     * @param list<array<string,mixed>> $responsaveis
     */
    private function gravarResponsaveisCaptacao(int $enrollmentId, array $responsaveis): void
    {
        if (!$this->model->temTabelaResponsaveis() || $responsaveis === []) {
            return;
        }
        foreach ($responsaveis as $i => &$r) {
            $r['is_pedagogico'] = $i === 0 ? 1 : (int) !empty($r['is_pedagogico']);
            $r['is_financeiro'] = $i === 0 ? 1 : 0;
            $r['percentual'] = $i === 0 ? 100.0 : null;
        }
        unset($r);
        $this->model->substituirResponsaveis($enrollmentId, $responsaveis);
    }

    /**
     * @param array<string,mixed> $filesField  $_FILES['arquivo']
     * @param mixed $tipos
     */
    public function anexarDocumentosCaptacao(int $enrollmentId, array $filesField, $tipos): int
    {
        if (!$this->model->temTabelaDocumentos() || $filesField === []) {
            return 0;
        }
        $tiposOk = ['rg', 'cpf', 'comprovante_residencia', 'historico', 'certidao', 'outro'];
        $tiposLista = is_array($tipos) ? array_values($tipos) : [];

        $names = $filesField['name'] ?? [];
        $tmps = $filesField['tmp_name'] ?? [];
        $sizes = $filesField['size'] ?? [];
        $errors = $filesField['error'] ?? [];
        if (!is_array($names)) {
            $names = [$names];
            $tmps = [$tmps];
            $sizes = [$sizes];
            $errors = [$errors];
        }

        $tenant = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '_', (string) TENANT_SLUG) : 'escola';
        $relDir = 'storage/enrollments/' . $tenant . '/docs/';
        $absDir = dirname(__DIR__, 4) . '/' . $relDir;
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Não foi possível criar a pasta de documentos.');
        }

        $pendentes = [];
        $maxArquivos = 8;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        foreach ($names as $i => $nomeOriginal) {
            if (count($pendentes) >= $maxArquivos) {
                break;
            }
            if ((int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = (string) ($tmps[$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            if ((int) ($sizes[$i] ?? 0) > 10 * 1024 * 1024) {
                throw new \InvalidArgumentException('Cada documento deve ter no máximo 10MB.');
            }
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, $allowed, true)) {
                throw new \InvalidArgumentException('Documento inválido. Envie PDF, JPG ou PNG.');
            }
            $tipo = trim((string) ($tiposLista[$i] ?? 'outro'));
            if (!in_array($tipo, $tiposOk, true)) {
                $tipo = 'outro';
            }
            $pendentes[] = [
                'tmp' => $tmp,
                'nome' => (string) $nomeOriginal,
                'mime' => $mime,
                'tamanho' => (int) ($sizes[$i] ?? 0),
                'tipo' => $tipo,
                'i' => (int) $i,
            ];
        }

        $gravados = 0;
        foreach ($pendentes as $item) {
            $ext = match ($item['mime']) {
                'application/pdf' => 'pdf',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            $filename = 'doc_' . $enrollmentId . '_' . time() . '_' . $item['i'] . '.' . $ext;
            if (!move_uploaded_file($item['tmp'], $absDir . $filename)) {
                throw new \RuntimeException('Falha ao salvar documento.');
            }
            $this->model->adicionarDocumento($enrollmentId, [
                'tipo' => $item['tipo'],
                'nome_original' => mb_substr($item['nome'], 0, 255),
                'path' => $relDir . $filename,
                'mime' => $item['mime'],
                'tamanho' => $item['tamanho'],
                'criado_por' => null,
            ]);
            $gravados++;
        }

        return $gravados;
    }

    public function extrairCobrancasDoPost(array $post): array
    {
        $tiposOk = ['mensalidade', 'matricula', 'material_didatico', 'uniforme', 'taxa', 'outros'];
        $out = [];
        $raw = $post['cobrancas'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $tipo = (string) ($row['tipo'] ?? '');
                if (!in_array($tipo, $tiposOk, true)) {
                    continue;
                }
                $planId = (int) ($row['plan_id'] ?? 0);
                if ($planId <= 0) {
                    continue;
                }
                $out[] = [
                    'tipo' => $tipo,
                    'plan_id' => $planId,
                    'desconto_rule_ids' => $this->parseDescontoRuleIds($row['desconto_rule_ids'] ?? []),
                ];
            }
        }

        if ($out === []) {
            $planId = (int) ($post['finance_plan_id'] ?? $post['finance_plan_id_mensalidade'] ?? 0);
            if ($planId > 0) {
                $out[] = [
                    'tipo' => 'mensalidade',
                    'plan_id' => $planId,
                    'desconto_rule_ids' => $this->parseDescontoRuleIds($post['desconto_rule_ids'] ?? []),
                ];
            }
        }

        // Máx. 1 por tipo
        $byTipo = [];
        foreach ($out as $cob) {
            $byTipo[$cob['tipo']] = $cob;
        }
        return array_values($byTipo);
    }

    /** @param mixed $raw */
    private function parseDescontoRuleIds($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    public function produtosDasCobrancas(array $cobrancas): array
    {
        $produtos = [];
        foreach ($cobrancas as $cob) {
            $planId = (int) ($cob['plan_id'] ?? 0);
            $tipo = (string) ($cob['tipo'] ?? 'mensalidade');
            $plan = null;
            $itens = [];
            if ($planId > 0) {
                try {
                    $plan = $this->db->fetch(
                        'SELECT id, nome FROM finance_plans WHERE id = ? AND ativo = 1',
                        [$planId]
                    );
                    // finance_plan_items não tem coluna status neste schema
                    $itens = $this->db->fetchAll(
                        'SELECT * FROM finance_plan_items WHERE plan_id = ? ORDER BY ordem ASC, id ASC',
                        [$planId]
                    ) ?: [];
                } catch (\Throwable $e) {
                    error_log('[MatriculaProcessoService::produtosDasCobrancas] ' . $e->getMessage());
                    $plan = null;
                    $itens = [];
                }
            }

            $valor = 0.0;
            $parcelas = 1;
            foreach ($itens as $item) {
                if (($item['categoria'] ?? '') === $tipo || $tipo === 'mensalidade') {
                    $valor = (float) ($item['valor_base'] ?? $item['valor_total'] ?? 0);
                    $parcelas = max(1, (int) ($item['num_parcelas'] ?? 1));
                    if (($item['categoria'] ?? '') === $tipo) {
                        break;
                    }
                }
            }

            $produtos[] = [
                'tipo' => $tipo,
                'descricao' => $plan['nome'] ?? ucfirst(str_replace('_', ' ', $tipo)),
                'valor_base' => $valor,
                'num_parcelas' => $parcelas,
                'incluir' => 1,
                'status' => 'pendente',
            ];
        }
        return $produtos;
    }

    /**
     * @param array<string,mixed> $post
     * @return list<array<string,mixed>>
     */
    public function extrairResponsaveisDoPost(array $post): array
    {
        $responsaveis = [];
        if (!empty($post['responsaveis']) && is_array($post['responsaveis'])) {
            foreach ($post['responsaveis'] as $r) {
                if (!is_array($r) || trim((string) ($r['nome'] ?? '')) === '') {
                    continue;
                }
                $logradouro = trim((string) ($r['endereco'] ?? $r['logradouro'] ?? ''));
                $endParts = [
                    'aluno_endereco' => $logradouro,
                    'aluno_end_numero' => trim((string) ($r['end_numero'] ?? $r['numero'] ?? '')),
                    'aluno_end_complemento' => trim((string) ($r['end_complemento'] ?? $r['complemento'] ?? '')),
                    'aluno_end_bairro' => trim((string) ($r['end_bairro'] ?? $r['bairro'] ?? '')),
                    'aluno_end_cidade' => trim((string) ($r['end_cidade'] ?? $r['cidade'] ?? '')),
                    'aluno_end_uf' => strtoupper(trim((string) ($r['end_uf'] ?? $r['uf'] ?? ''))),
                    'aluno_end_cep' => trim((string) ($r['end_cep'] ?? $r['cep'] ?? '')),
                ];
                $enderecoMontado = $this->montarEnderecoAluno($endParts);
                if ($logradouro === '' && $enderecoMontado !== '') {
                    // endereço legado único quando só veio CEP estruturado
                    $logradouro = $enderecoMontado;
                }

                $responsaveis[] = [
                    'nome' => trim((string) $r['nome']),
                    'documento' => trim((string) ($r['cpf'] ?? $r['documento'] ?? '')) ?: null,
                    'cpf' => trim((string) ($r['cpf'] ?? $r['documento'] ?? '')) ?: null,
                    'rg' => trim((string) ($r['rg'] ?? '')) ?: null,
                    'data_nascimento' => trim((string) ($r['data_nascimento'] ?? '')) ?: null,
                    'estado_civil' => trim((string) ($r['estado_civil'] ?? '')) ?: null,
                    'profissao' => trim((string) ($r['profissao'] ?? '')) ?: null,
                    'empresa' => trim((string) ($r['empresa'] ?? '')) ?: null,
                    'email' => trim((string) ($r['email'] ?? '')) ?: null,
                    'telefone' => trim((string) ($r['telefone'] ?? '')) ?: null,
                    'tipo_vinculo' => trim((string) ($r['tipo_vinculo'] ?? $r['parentesco'] ?? '')) ?: null,
                    'endereco' => $logradouro !== '' ? $logradouro : ($enderecoMontado !== '' ? $enderecoMontado : null),
                    'end_cep' => $endParts['aluno_end_cep'] !== '' ? $endParts['aluno_end_cep'] : null,
                    'end_numero' => $endParts['aluno_end_numero'] !== '' ? $endParts['aluno_end_numero'] : null,
                    'end_complemento' => $endParts['aluno_end_complemento'] !== '' ? $endParts['aluno_end_complemento'] : null,
                    'end_bairro' => $endParts['aluno_end_bairro'] !== '' ? $endParts['aluno_end_bairro'] : null,
                    'end_cidade' => $endParts['aluno_end_cidade'] !== '' ? $endParts['aluno_end_cidade'] : null,
                    'end_uf' => $endParts['aluno_end_uf'] !== '' ? substr($endParts['aluno_end_uf'], 0, 2) : null,
                    'is_financeiro' => !empty($r['is_financeiro']) ? 1 : 0,
                    'is_pedagogico' => !empty($r['is_pedagogico']) ? 1 : 0,
                    'percentual' => isset($r['percentual']) && $r['percentual'] !== ''
                        ? (float) $r['percentual']
                        : null,
                ];
            }
        }
        if ($responsaveis === [] && trim((string) ($post['resp_nome'] ?? '')) !== '') {
            $responsaveis[] = [
                'nome' => trim((string) $post['resp_nome']),
                'documento' => $post['resp_cpf'] ?? null,
                'cpf' => $post['resp_cpf'] ?? null,
                'email' => $post['resp_email'] ?? null,
                'telefone' => $post['resp_telefone'] ?? null,
                'tipo_vinculo' => $post['resp_parentesco'] ?? null,
                'endereco' => $post['resp_endereco'] ?? null,
                'is_financeiro' => 1,
                'is_pedagogico' => 1,
                'percentual' => 100,
            ];
        }
        return $responsaveis;
    }

    public function sincronizarResponsaveisEProdutos(int $enrollmentId, array $post): array
    {
        $cobrancas = $this->extrairCobrancasDoPost($post);
        $produtos = $this->produtosDasCobrancas($cobrancas);
        $responsaveis = $this->extrairResponsaveisDoPost($post);

        $somaPct = 0.0;
        $temFinanceiro = false;
        foreach ($responsaveis as $r) {
            if (!empty($r['is_financeiro'])) {
                $temFinanceiro = true;
                $somaPct += (float) ($r['percentual'] ?? 0);
            }
        }
        if ($temFinanceiro && abs($somaPct - 100.0) > 1.0) {
            error_log(sprintf(
                '[MatriculaProcessoService] rateio financeiros enrollment #%d soma=%.2f (esperado ~100)',
                $enrollmentId,
                $somaPct
            ));
        }

        $planMen = 0;
        foreach ($cobrancas as $cob) {
            if (($cob['tipo'] ?? '') === 'mensalidade') {
                $planMen = (int) ($cob['plan_id'] ?? 0);
            }
        }
        if ($planMen <= 0 && $cobrancas !== []) {
            $planMen = (int) ($cobrancas[0]['plan_id'] ?? 0);
        }

        $upd = [];
        if ($this->model->temColuna('finance_cobrancas')) {
            $upd['finance_cobrancas'] = $cobrancas !== []
                ? json_encode(array_values($cobrancas), JSON_UNESCAPED_UNICODE)
                : null;
        }
        if ($this->model->temColuna('finance_plan_id')) {
            $upd['finance_plan_id'] = $planMen > 0 ? $planMen : null;
        }
        if ($this->model->temColuna('documento_assinatura_codigo') && array_key_exists('documento_assinatura_codigo', $post)) {
            $doc = trim((string) $post['documento_assinatura_codigo']);
            $upd['documento_assinatura_codigo'] = ($doc !== '' && $doc !== '_padrao') ? $doc : null;
        }
        if ($upd !== []) {
            $this->model->update($enrollmentId, $upd);
        }

        if ($this->model->temTabelaResponsaveis()) {
            $this->model->substituirResponsaveis($enrollmentId, $responsaveis);
        }
        if ($this->model->temTabelaProdutos()) {
            $this->model->substituirProdutos($enrollmentId, $produtos);
        }

        return [
            'finance_plan_id' => $planMen > 0 ? $planMen : null,
            'finance_cobrancas' => $cobrancas,
        ];
    }

    public function listarPlanosFinanceiros(?int $anoLetivoId = null): array
    {
        try {
            $sql = 'SELECT id, nome, ano_letivo_id, ativo FROM finance_plans WHERE ativo = 1';
            $params = [];
            if ($anoLetivoId && $anoLetivoId > 0) {
                $sql .= ' AND (ano_letivo_id = ? OR ano_letivo_id IS NULL)';
                $params[] = $anoLetivoId;
            }
            $sql .= ' ORDER BY nome';
            return $this->db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public function listarRegrasDesconto(): array
    {
        try {
            $exists = $this->db->fetch(
                "SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_discount_rules' LIMIT 1"
            );
            if (empty($exists['ok'])) {
                return [];
            }
            return $this->db->fetchAll(
                'SELECT id, nome, tipo, calculo, valor, acumulavel, requer_aprovacao
                 FROM finance_discount_rules WHERE ativo = 1 ORDER BY tipo, nome'
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{plano: array<string,mixed>|null, itens: list<array<string,mixed>>}
     */
    public function listarItensPlano(int $planId): array
    {
        if ($planId <= 0) {
            return ['plano' => null, 'itens' => []];
        }
        try {
            $plano = $this->db->fetch(
                'SELECT id, nome, ano_letivo_id, ativo FROM finance_plans WHERE id = ?',
                [$planId]
            ) ?: null;
            if (!$plano) {
                return ['plano' => null, 'itens' => []];
            }

            // Schema real de finance_plan_items não tem coluna status em todos os tenants.
            $itens = $this->db->fetchAll(
                "SELECT id, plan_id, categoria, descricao, valor_base, num_parcelas,
                        mes_inicio, mes_fim, dia_vencimento, ordem
                 FROM finance_plan_items
                 WHERE plan_id = ?
                 ORDER BY ordem ASC, id ASC",
                [$planId]
            ) ?: [];

            $labels = [
                'mensalidade' => 'Mensalidade',
                'matricula' => 'Matrícula',
                'material_didatico' => 'Material didático',
                'uniforme' => 'Uniforme',
                'taxa' => 'Taxa',
                'outros' => 'Outros',
            ];
            foreach ($itens as &$it) {
                $cat = (string) ($it['categoria'] ?? '');
                $it['categoria_label'] = $labels[$cat] ?? ($cat !== '' ? $cat : 'Item');
                $it['valor_base'] = (float) ($it['valor_base'] ?? 0);
                $it['num_parcelas'] = (int) ($it['num_parcelas'] ?? 1);
                $np = max(1, $it['num_parcelas']);
                $it['valor_parcela'] = $np > 0 ? round($it['valor_base'] / $np, 2) : $it['valor_base'];
            }
            unset($it);

            return ['plano' => $plano, 'itens' => $itens];
        } catch (\Throwable $e) {
            error_log('[MatriculaProcessoService::listarItensPlano] ' . $e->getMessage());
            return ['plano' => null, 'itens' => []];
        }
    }

    /** @param array<string,mixed> $data */
    public function montarEnderecoAluno(array $data): string
    {
        $parts = [];
        $logradouro = trim((string) ($data['aluno_endereco'] ?? ''));
        $numero = trim((string) ($data['aluno_end_numero'] ?? ''));
        $complemento = trim((string) ($data['aluno_end_complemento'] ?? ''));
        $bairro = trim((string) ($data['aluno_end_bairro'] ?? ''));
        $cidade = trim((string) ($data['aluno_end_cidade'] ?? ''));
        $uf = trim((string) ($data['aluno_end_uf'] ?? ''));
        $cep = trim((string) ($data['aluno_end_cep'] ?? ''));

        if ($logradouro !== '') {
            $line = $logradouro;
            if ($numero !== '') {
                $line .= ', ' . $numero;
            }
            if ($complemento !== '') {
                $line .= ' (' . $complemento . ')';
            }
            $parts[] = $line;
        } elseif ($numero !== '') {
            $parts[] = 'nº ' . $numero . ($complemento !== '' ? ' (' . $complemento . ')' : '');
        }
        if ($bairro !== '') {
            $parts[] = $bairro;
        }
        if ($cidade !== '' || $uf !== '') {
            $parts[] = trim($cidade . ($cidade !== '' && $uf !== '' ? '/' : '') . $uf);
        }
        if ($cep !== '') {
            $parts[] = 'CEP ' . $cep;
        }
        return implode(' — ', $parts);
    }

    /** @param array<string,mixed> $enrollment */
    public function buildEmailLink(array $enrollment, string $baseUrl): string
    {
        $to = trim((string) ($enrollment['resp_email'] ?? ''));
        if ($to === '') {
            return '';
        }
        $token = (string) ($enrollment['contrato_token'] ?? '');
        $url = $token !== ''
            ? rtrim($baseUrl, '/') . '/matricula/contrato/' . $token
            : rtrim($baseUrl, '/') . '/admin/enrollment/' . (int) ($enrollment['id'] ?? 0);
        $aluno = (string) ($enrollment['aluno_nome'] ?? 'aluno(a)');
        $subject = 'Contrato de matrícula — ' . $aluno;
        $body = "Olá,\n\n"
            . "Segue o link para revisão e assinatura do contrato de matrícula de {$aluno}:\n\n"
            . "{$url}\n\n"
            . "Atenciosamente,\nEducaTudo";
        return 'mailto:' . rawurlencode($to)
            . '?subject=' . rawurlencode($subject)
            . '&body=' . rawurlencode($body);
    }

    public function validarContratoFinanceiroAntesAssinatura(array $enrollment): array
    {
        $cfg = $this->getConfigAssinaturaEscola();
        if (empty($cfg['contrato_com_valores'])) {
            return ['ok' => true];
        }
        $planId = (int) ($enrollment['finance_plan_id'] ?? 0);
        $cobrancas = $enrollment['finance_cobrancas'] ?? null;
        if (is_string($cobrancas)) {
            $cobrancas = json_decode($cobrancas, true);
        }
        if ($planId <= 0 && is_array($cobrancas)) {
            foreach ($cobrancas as $c) {
                if ((int) ($c['plan_id'] ?? 0) > 0) {
                    $planId = (int) $c['plan_id'];
                    break;
                }
            }
        }
        if ($planId <= 0) {
            return [
                'ok' => false,
                'mensagem' => 'Selecione um plano financeiro no processo antes de gerar o contrato.',
            ];
        }
        return ['ok' => true];
    }

    public function getConfigAssinaturaEscola(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT config_key, config_value FROM config_layout
             WHERE config_key LIKE 'enrollment_%' OR config_key LIKE 'zapsign_%'"
        ) ?: [];
        $cfg = [];
        foreach ($rows as $r) {
            $cfg[$r['config_key']] = $r['config_value'];
        }
        return [
            'documento_codigo' => $cfg['enrollment_documento_assinatura'] ?? 'contrato_matricula',
            'pagante_modo' => $cfg['enrollment_pagante_modo'] ?? 'um',
            'pagante1_pct' => (float) ($cfg['enrollment_pagante1_pct'] ?? 100),
            'pagante2_pct' => (float) ($cfg['enrollment_pagante2_pct'] ?? 0),
            'pagante3_pct' => (float) ($cfg['enrollment_pagante3_pct'] ?? 0),
            'contrato_com_valores' => ($cfg['enrollment_contrato_com_valores'] ?? '0') === '1',
            'assinar_contrato' => ($cfg['enrollment_assinar_contrato'] ?? '1') === '1',
            'assinar_ficha' => ($cfg['enrollment_assinar_ficha'] ?? '0') === '1',
        ];
    }

    public function salvarConfigAssinaturaEscola(array $input): void
    {
        $map = [
            'enrollment_documento_assinatura' => (string) ($input['documento_codigo'] ?? 'contrato_matricula'),
            'enrollment_pagante_modo' => (string) ($input['pagante_modo'] ?? 'um'),
            'enrollment_pagante1_pct' => (string) ((float) ($input['pagante1_pct'] ?? 100)),
            'enrollment_pagante2_pct' => (string) ((float) ($input['pagante2_pct'] ?? 0)),
            'enrollment_pagante3_pct' => (string) ((float) ($input['pagante3_pct'] ?? 0)),
            'enrollment_contrato_com_valores' => !empty($input['contrato_com_valores']) ? '1' : '0',
            'enrollment_assinar_contrato' => !empty($input['assinar_contrato']) ? '1' : '0',
            'enrollment_assinar_ficha' => !empty($input['assinar_ficha']) ? '1' : '0',
        ];
        foreach ($map as $key => $value) {
            $this->db->query(
                'INSERT INTO config_layout (config_key, config_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)',
                [$key, $value]
            );
        }
        $this->invalidarCacheConfig();
    }

    public function schemaContratoRegrasReady(): bool
    {
        try {
            return (bool) $this->db->fetch("SHOW TABLES LIKE 'matricula_contrato_regras'");
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public function listarRegrasContrato(bool $somenteAtivas = false): array
    {
        if (!$this->schemaContratoRegrasReady()) {
            return [];
        }
        $sql = 'SELECT * FROM matricula_contrato_regras';
        if ($somenteAtivas) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY ordem ASC, id ASC';
        try {
            return $this->db->fetchAll($sql) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Substitui o conjunto de regras a partir do formulário de configuração.
     *
     * @param list<array<string,mixed>> $regras
     * @param list<string> $codigosModeloPermitidos
     */
    public function salvarRegrasContrato(array $regras, array $codigosModeloPermitidos = []): void
    {
        if (!$this->schemaContratoRegrasReady()) {
            throw new \RuntimeException('Migration de regras de contrato não aplicada.');
        }

        $tiposValidos = array_keys(self::TIPOS_CONTRATO);
        $normalizadas = [];
        $tiposUsados = [];
        $ordem = 1;
        foreach ($regras as $r) {
            if (!is_array($r)) {
                continue;
            }
            $nome = trim((string) ($r['nome'] ?? ''));
            $tipo = strtolower(trim((string) ($r['tipo'] ?? '')));
            $codigo = trim((string) ($r['modelo_documento_codigo'] ?? ''));
            if ($nome === '' || $tipo === '' || $codigo === '') {
                continue;
            }
            if (!in_array($tipo, $tiposValidos, true)) {
                continue;
            }
            if (isset($tiposUsados[$tipo])) {
                continue; // 1 regra por tipo
            }
            if ($codigosModeloPermitidos !== [] && !in_array($codigo, $codigosModeloPermitidos, true)) {
                continue;
            }
            $tiposUsados[$tipo] = true;
            $normalizadas[] = [
                'nome' => mb_substr($nome, 0, 160),
                'tipo' => $tipo,
                'modelo_documento_codigo' => mb_substr($codigo, 0, 80),
                'ativo' => !empty($r['ativo']) ? 1 : 0,
                'enviar_zapsign' => !empty($r['enviar_zapsign']) ? 1 : 0,
                'ordem' => $ordem++,
            ];
        }

        if ($normalizadas === []) {
            throw new \InvalidArgumentException('Informe ao menos uma regra de contrato válida.');
        }

        $this->db->beginTransaction();
        try {
            $tiposMantidos = [];
            foreach ($normalizadas as $row) {
                $tiposMantidos[] = $row['tipo'];
                $existente = $this->db->fetch(
                    'SELECT id FROM matricula_contrato_regras WHERE tipo = ? LIMIT 1',
                    [$row['tipo']]
                );
                if ($existente) {
                    $this->db->query(
                        'UPDATE matricula_contrato_regras SET
                            nome = ?, modelo_documento_codigo = ?, ativo = ?,
                            enviar_zapsign = ?, ordem = ?, updated_at = NOW()
                         WHERE id = ?',
                        [
                            $row['nome'],
                            $row['modelo_documento_codigo'],
                            $row['ativo'],
                            $row['enviar_zapsign'],
                            $row['ordem'],
                            (int) $existente['id'],
                        ]
                    );
                } else {
                    $this->db->query(
                        'INSERT INTO matricula_contrato_regras
                            (nome, tipo, modelo_documento_codigo, ativo, enviar_zapsign, ordem)
                         VALUES (?, ?, ?, ?, ?, ?)',
                        [
                            $row['nome'],
                            $row['tipo'],
                            $row['modelo_documento_codigo'],
                            $row['ativo'],
                            $row['enviar_zapsign'],
                            $row['ordem'],
                        ]
                    );
                }
            }
            // Remove tipos que saíram do formulário
            if ($tiposMantidos !== []) {
                $ph = implode(',', array_fill(0, count($tiposMantidos), '?'));
                $this->db->query(
                    "DELETE FROM matricula_contrato_regras WHERE tipo NOT IN ($ph)",
                    $tiposMantidos
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Regras ativas + instância gerada do processo (se houver).
     *
     * @return list<array<string,mixed>>
     */
    public function listarContratosDoProcesso(int $enrollmentId): array
    {
        $regras = $this->listarRegrasContrato(true);
        if ($regras === []) {
            // Fallback: documento único da config antiga
            $cfg = $this->getConfigAssinaturaEscola();
            $regras = [[
                'id' => 0,
                'nome' => self::DOCUMENTOS_ASSINATURA[$cfg['documento_codigo']] ?? 'Contrato de matrícula',
                'tipo' => 'matricula',
                'modelo_documento_codigo' => $cfg['documento_codigo'] ?? 'contrato_matricula',
                'ativo' => 1,
                'enviar_zapsign' => 1,
                'ordem' => 1,
            ]];
        }

        $instancias = [];
        if ($this->schemaContratoRegrasReady()) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT * FROM matricula_processos_contratos WHERE enrollment_id = ?',
                    [$enrollmentId]
                ) ?: [];
                foreach ($rows as $row) {
                    $instancias[(string) ($row['tipo'] ?? '')] = $row;
                }
            } catch (\Throwable $e) {
                $instancias = [];
            }
        }

        $out = [];
        foreach ($regras as $regra) {
            $tipo = (string) ($regra['tipo'] ?? 'matricula');
            $inst = $instancias[$tipo] ?? null;
            $out[] = array_merge($regra, [
                'instancia' => $inst,
                'pdf_path' => $inst['pdf_path'] ?? null,
                'zapsign_status' => $inst['zapsign_status'] ?? null,
                'zapsign_sign_url' => $inst['zapsign_sign_url'] ?? null,
                'zapsign_doc_token' => $inst['zapsign_doc_token'] ?? null,
                'assinado_em' => $inst['assinado_em'] ?? null,
                'status_contrato' => $inst['status'] ?? 'pendente',
            ]);
        }
        return $out;
    }

    /**
     * Gera PDF (e opcionalmente envia ZapSign) para uma regra específica.
     *
     * @return array{ok:bool,message:string,pdf_path?:string,contrato_id?:int}
     */
    public function gerarContratoPorRegra(array $enrollment, array $escola, int $regraId, bool $enviarZapSign = false): array
    {
        $regras = $this->listarRegrasContrato(false);
        $regra = null;
        foreach ($regras as $r) {
            if ((int) ($r['id'] ?? 0) === $regraId) {
                $regra = $r;
                break;
            }
        }
        if (!$regra) {
            return ['ok' => false, 'message' => 'Regra de contrato não encontrada.'];
        }
        if (empty($regra['ativo'])) {
            return ['ok' => false, 'message' => 'Esta regra está inativa na configuração.'];
        }
        if (!empty($enrollment['assinado_em']) && ($regra['tipo'] ?? '') === 'matricula') {
            return ['ok' => false, 'message' => 'Contrato principal já assinado — não é possível regerar.'];
        }
        $st = (string) ($enrollment['status'] ?? '');
        if (in_array($st, ['confirmada', 'enturmada', 'cancelada'], true) && ($regra['tipo'] ?? '') === 'matricula') {
            return ['ok' => false, 'message' => 'Não é possível regerar o contrato principal neste status.'];
        }

        $tipo = (string) ($regra['tipo'] ?? 'matricula');
        $codigo = (string) ($regra['modelo_documento_codigo'] ?? '');
        if ($codigo === '') {
            return ['ok' => false, 'message' => 'Regra sem modelo de documento.'];
        }

        $pdfPath = $this->gerarContratoPDF($enrollment, $escola, null, $codigo, $tipo);

        $contratoId = $this->upsertProcessoContrato((int) $enrollment['id'], $regra, $pdfPath);

        // Compatibilidade com trilha pública / campos legados do processo
        if ($tipo === 'matricula') {
            $this->model->update((int) $enrollment['id'], [
                'contrato_pdf_path' => $pdfPath,
                'documento_assinatura_codigo' => $codigo,
            ]);
        }

        // Produto: grava modelo vinculado
        try {
            $this->db->query(
                'UPDATE matricula_processos_produtos
                 SET modelo_documento_codigo = ?
                 WHERE enrollment_id = ? AND tipo = ?',
                [$codigo, (int) $enrollment['id'], $tipo]
            );
        } catch (\Throwable $e) {
            // ok
        }

        $msg = ($regra['nome'] ?? 'Contrato') . ' gerado.';
        if ($enviarZapSign) {
            if (empty($regra['enviar_zapsign'])) {
                $msg .= ' (ZapSign desabilitado nesta regra.)';
            } else {
                require_once __DIR__ . '/ZapSignService.php';
                $zs = new ZapSignService($this->db);
                if ($zs->estaAtivo()) {
                    $zsResult = $zs->enviarContratoProcesso(
                        $enrollment,
                        $pdfPath,
                        $contratoId,
                        (string) ($regra['nome'] ?? 'Contrato')
                    );
                    if (!empty($zsResult['ok'])) {
                        $msg .= ' Enviado à ZapSign.';
                    } else {
                        $msg .= ' (ZapSign: ' . mb_substr((string) ($zsResult['message'] ?? 'falha'), 0, 120) . ')';
                    }
                } else {
                    $msg .= ' (ZapSign inativo ou sem token.)';
                }
            }
        }

        return [
            'ok' => true,
            'message' => $msg,
            'pdf_path' => $pdfPath,
            'contrato_id' => $contratoId,
        ];
    }

    /**
     * @param array<string,mixed> $regra
     */
    private function upsertProcessoContrato(int $enrollmentId, array $regra, string $pdfPath): int
    {
        if (!$this->schemaContratoRegrasReady()) {
            return 0;
        }
        $tipo = (string) ($regra['tipo'] ?? 'matricula');
        $existente = $this->db->fetch(
            'SELECT id FROM matricula_processos_contratos WHERE enrollment_id = ? AND tipo = ? LIMIT 1',
            [$enrollmentId, $tipo]
        );
        $token = bin2hex(random_bytes(16));
        $absPdf = $this->resolverPathPdfAbsoluto($pdfPath);
        $hash = ($absPdf !== null && is_file($absPdf)) ? (hash_file('sha256', $absPdf) ?: null) : null;

        if ($existente) {
            $id = (int) $existente['id'];
            $this->db->query(
                'UPDATE matricula_processos_contratos SET
                    regra_id = ?, nome = ?, modelo_documento_codigo = ?,
                    pdf_path = ?, contrato_token = COALESCE(contrato_token, ?),
                    contrato_hash = ?, status = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    ((int) ($regra['id'] ?? 0) > 0) ? (int) $regra['id'] : null,
                    (string) ($regra['nome'] ?? 'Contrato'),
                    (string) ($regra['modelo_documento_codigo'] ?? ''),
                    $pdfPath,
                    $token,
                    $hash,
                    'gerado',
                    $id,
                ]
            );
            return $id;
        }

        $this->db->query(
            'INSERT INTO matricula_processos_contratos
                (enrollment_id, regra_id, tipo, nome, modelo_documento_codigo,
                 pdf_path, contrato_token, contrato_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $enrollmentId,
                ((int) ($regra['id'] ?? 0) > 0) ? (int) $regra['id'] : null,
                $tipo,
                (string) ($regra['nome'] ?? 'Contrato'),
                (string) ($regra['modelo_documento_codigo'] ?? ''),
                $pdfPath,
                $token,
                $hash,
                'gerado',
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    private function resolverPathPdfAbsoluto(string $relativeOrAbsolute): ?string
    {
        $path = trim(str_replace('\\', '/', $relativeOrAbsolute));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        if ($path[0] === '/' && is_file($path)) {
            $real = realpath($path);
            $storage = realpath((defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/storage');
            if ($real && $storage && str_starts_with($real, $storage)) {
                return $real;
            }
            return null;
        }
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $candidates = [
            $base . '/' . ltrim($path, '/'),
            $base . '/storage/' . ltrim($path, '/'),
        ];
        if (str_starts_with($path, 'storage/')) {
            $candidates[] = $base . '/' . $path;
        }
        foreach ($candidates as $c) {
            if (!is_file($c)) {
                continue;
            }
            $real = realpath($c);
            $storage = realpath($base . '/storage');
            if ($real && $storage && str_starts_with($real, $storage)) {
                return $real;
            }
        }
        return null;
    }

    public function resolverConfigAssinatura(array $enrollment): array
    {
        $cfg = $this->getConfigAssinaturaEscola();
        $codigo = $enrollment['documento_assinatura_codigo'] ?? $cfg['documento_codigo'];
        $modo = (string) ($cfg['pagante_modo'] ?? 'um');
        return [
            'documento_codigo' => $codigo,
            'documento_rotulo' => self::DOCUMENTOS_ASSINATURA[$codigo] ?? $codigo ?: 'Contrato',
            'contrato_com_valores' => $cfg['contrato_com_valores'],
            'pagante_modo' => $modo,
            'pagante_modo_rotulo' => self::PAGANTE_MODOS[$modo] ?? $modo,
            'pagante1_pct' => (float) ($cfg['pagante1_pct'] ?? 100),
            'pagante2_pct' => (float) ($cfg['pagante2_pct'] ?? 0),
            'pagante3_pct' => (float) ($cfg['pagante3_pct'] ?? 0),
        ];
    }

    /** @var array<string,mixed>|null */
    private $ultimoModeloContrato = null;

    /**
     * HTML do contrato para página pública / ZapSign (modelo editável ou fallback).
     *
     * @param array<string,mixed> $enrollment
     * @param array<string,mixed> $escola
     * @param array<string,mixed>|null $config
     */
    public function htmlContratoParaAssinatura(array $enrollment, array $escola, ?array $config = null): ?string
    {
        [$contrato, $itens] = $this->carregarFinanceiroParaDocumento($enrollment);

        $html = $this->renderContratoFromModelo($enrollment, $escola, $contrato, $itens, $config);
        if ($html === null || $html === '') {
            $html = $this->renderContratoHtml($enrollment, $escola, $contrato, $itens);
        }
        if ($html === null || $html === '') {
            return null;
        }
        // Trilha pública: só o body, sem <style> do PDF (mitiga CSS injection).
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $html = trim($m[1]);
        }
        return $this->sanitizarHtmlContratoPublico($html);
    }

    public function gerarContratoPDF(
        array $enrollment,
        array $escola,
        ?array $config = null,
        ?string $codigoModelo = null,
        ?string $tipoProduto = null
    ): string {
        $prevErrorReporting = error_reporting(E_ERROR | E_WARNING | E_PARSE);
        $prevDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');

        [$contrato, $itens] = $this->carregarFinanceiroParaDocumento($enrollment, $tipoProduto);

        $this->ultimoModeloContrato = null;
        $html = $this->renderContratoFromModelo(
            $enrollment,
            $escola,
            $contrato,
            $itens,
            $config,
            $codigoModelo
        );
        $orientacao = 'portrait';
        if ($html === null) {
            $html = $this->renderContratoHtml($enrollment, $escola, $contrato, $itens);
        } elseif ($this->ultimoModeloContrato) {
            $svcPath = dirname(__DIR__, 2) . '/modelos-documentos/Services/ModeloDocumentoService.php';
            if (is_file($svcPath)) {
                require_once $svcPath;
                $orientacao = (new \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService($this->db))
                    ->orientacaoDompdf($this->ultimoModeloContrato);
            }
        }

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        // Remote off: evita SSRF via logo_url; logo só renderiza se data-URI/local.
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $chroot = defined('BASE_PATH') ? (BASE_PATH . '/storage') : (dirname(__DIR__, 4) . '/storage');
        if (is_dir($chroot)) {
            $options->setChroot($chroot);
        }

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientacao === 'landscape' ? 'landscape' : 'portrait');
        $dompdf->render();

        $slug = preg_replace('/[^a-z0-9]/i', '_', substr((string) ($enrollment['aluno_nome'] ?? 'aluno'), 0, 30));
        $tenant = defined('TENANT_SLUG') ? preg_replace('/[^a-z0-9_-]/i', '_', TENANT_SLUG) : 'escola';
        $tipoSlug = preg_replace('/[^a-z0-9_]+/i', '_', (string) ($tipoProduto ?: 'matricula'));
        $dir = dirname(__DIR__, 4) . '/storage/enrollments/' . $tenant . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'contrato_' . $tipoSlug . '_' . $enrollment['id'] . '_' . $slug . '_' . date('Ymd_His') . '.pdf';
        $path = $dir . $filename;
        $pdfBin = $dompdf->output();
        file_put_contents($path, $pdfBin);
        $hash = hash('sha256', $pdfBin);
        $relative = 'storage/enrollments/' . $tenant . '/' . $filename;

        // Só o contrato principal (matrícula) atualiza campos legados do processo.
        if ($tipoProduto === null || $tipoProduto === '' || $tipoProduto === 'matricula') {
            $upd = [
                'contrato_pdf_path' => $relative,
                'contrato_hash' => $hash,
            ];
            $stAtual = (string) ($enrollment['status'] ?? '');
            if (!in_array($stAtual, ['confirmada', 'enturmada', 'cancelada'], true)
                && empty($enrollment['assinado_em'])) {
                $upd['status'] = 'aguardando_assinatura';
            }
            $this->model->update((int) $enrollment['id'], $upd);
        }

        error_reporting($prevErrorReporting);
        ini_set('display_errors', $prevDisplayErrors);

        return $relative;
    }

    /**
     * @return array{0: array<string,mixed>, 1: list<array<string,mixed>>}
     */
    private function carregarFinanceiroParaDocumento(array $enrollment, ?string $tipoFiltro = null): array
    {
        $itensPlano = [];
        $planId = (int) ($enrollment['finance_plan_id'] ?? 0);
        $cobrancas = $enrollment['finance_cobrancas'] ?? null;
        if (is_string($cobrancas)) {
            $cobrancas = json_decode($cobrancas, true);
        }
        if ($planId <= 0 && is_array($cobrancas)) {
            foreach ($cobrancas as $c) {
                $cTipo = (string) ($c['tipo'] ?? '');
                if ($tipoFiltro && $cTipo !== '' && $cTipo !== $tipoFiltro && $tipoFiltro !== 'matricula') {
                    continue;
                }
                if ((int) ($c['plan_id'] ?? 0) > 0) {
                    $planId = (int) $c['plan_id'];
                    break;
                }
            }
            // Se filtro específico não achou, tenta qualquer plano
            if ($planId <= 0) {
                foreach ($cobrancas as $c) {
                    if ((int) ($c['plan_id'] ?? 0) > 0) {
                        $planId = (int) $c['plan_id'];
                        break;
                    }
                }
            }
        }
        $plano = [];
        if ($planId > 0) {
            try {
                $plano = $this->db->fetch('SELECT * FROM finance_plans WHERE id = ?', [$planId]) ?: [];
                $itensPlano = $this->db->fetchAll(
                    "SELECT * FROM finance_plan_items WHERE plan_id = ? ORDER BY ordem ASC, id ASC",
                    [$planId]
                ) ?: [];
            } catch (\Throwable $e) {
                $plano = [];
                $itensPlano = [];
            }
        }

        $contrato = [];
        try {
            $contrato = $this->db->fetch(
                "SELECT fc.*, r.nome AS responsavel_nome_full, r.cpf AS responsavel_cpf_full
                 FROM finance_contracts fc
                 LEFT JOIN responsaveis r ON r.id = fc.responsavel_id
                 WHERE fc.enrollment_id = ? OR (fc.aluno_id = ? AND fc.ano_letivo_id = ?)
                 ORDER BY fc.id DESC LIMIT 1",
                [$enrollment['id'], $enrollment['aluno_id'] ?? 0, $enrollment['ano_letivo_id'] ?? 0]
            ) ?: [];
        } catch (\Throwable $e) {
            $contrato = [];
        }

        $itens = [];
        if (!empty($contrato['id'])) {
            try {
                $itens = $this->db->fetchAll(
                    "SELECT * FROM finance_contract_items WHERE contract_id = ? AND status = 'ativo' ORDER BY id",
                    [$contrato['id']]
                ) ?: [];
            } catch (\Throwable $e) {
                $itens = [];
            }
        } elseif ($itensPlano !== []) {
            foreach ($itensPlano as $item) {
                $itens[] = [
                    'categoria' => $item['categoria'] ?? 'mensalidade',
                    'descricao' => $item['descricao'] ?? ($plano['nome'] ?? 'Plano'),
                    'valor_total' => $item['valor_base'] ?? $item['valor_total'] ?? 0,
                    'valor_liquido' => $item['valor_base'] ?? $item['valor_total'] ?? 0,
                    'num_parcelas' => $item['num_parcelas'] ?? 1,
                ];
            }
            $contrato = [
                'valor_bruto' => array_sum(array_column($itens, 'valor_total')),
                'valor_desconto' => 0,
                'valor_liquido' => array_sum(array_column($itens, 'valor_liquido')),
                'dia_vencimento' => $plano['dia_vencimento'] ?? '—',
                'num_parcelas' => $itens[0]['num_parcelas'] ?? 0,
            ];
        }

        // Filtra itens pelo tipo do contrato (ex.: só material didático)
        if ($tipoFiltro && $tipoFiltro !== '' && $tipoFiltro !== 'matricula' && $itens !== []) {
            $filtrados = array_values(array_filter(
                $itens,
                static fn ($it) => (string) ($it['categoria'] ?? $it['tipo'] ?? '') === $tipoFiltro
            ));
            if ($filtrados !== []) {
                $itens = $filtrados;
                $contrato['valor_bruto'] = array_sum(array_map(
                    static fn ($it) => (float) ($it['valor_total'] ?? $it['valor_bruto'] ?? 0),
                    $itens
                ));
                $contrato['valor_liquido'] = array_sum(array_map(
                    static fn ($it) => (float) ($it['valor_liquido'] ?? $it['valor_total'] ?? 0),
                    $itens
                ));
                $contrato['valor_desconto'] = max(0, (float) $contrato['valor_bruto'] - (float) $contrato['valor_liquido']);
                $contrato['num_parcelas'] = (int) ($itens[0]['num_parcelas'] ?? $contrato['num_parcelas'] ?? 0);
            }
        }

        return [$contrato, $itens];
    }

    /**
     * Tenta gerar HTML a partir do modelo configurado. Null = usa HTML legado.
     *
     * @param array<string,mixed> $e
     * @param array<string,mixed> $escola
     * @param array<string,mixed> $contrato
     * @param list<array<string,mixed>> $itens
     * @param array<string,mixed>|null $config
     */
    private function renderContratoFromModelo(
        array $e,
        array $escola,
        array $contrato = [],
        array $itens = [],
        ?array $config = null,
        ?string $codigoModeloOverride = null
    ): ?string {
        try {
            $svcPath = dirname(__DIR__, 2) . '/modelos-documentos/Services/ModeloDocumentoService.php';
            if (!is_file($svcPath)) {
                return null;
            }
            require_once $svcPath;
            $svc = new \App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService($this->db);
            if (!$svc->schemaReady()) {
                return null;
            }

            $cfgAssinatura = $this->resolverConfigAssinatura($e);
            $codigoModelo = $codigoModeloOverride !== null && $codigoModeloOverride !== ''
                ? $codigoModeloOverride
                : (string) ($cfgAssinatura['documento_codigo'] ?? 'contrato_matricula');
            $modelo = $svc->findByCodigo($codigoModelo);
            if (!$modelo && $codigoModelo !== 'contrato_matricula') {
                $modelo = $svc->findByCodigo('contrato_matricula');
            }
            if (!$modelo) {
                return null;
            }
            $this->ultimoModeloContrato = $modelo;

            $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $fmtDt = static function ($d) {
                if (!$d || $d === '0000-00-00') {
                    return '—';
                }
                $dt = \DateTime::createFromFormat('Y-m-d', substr((string) $d, 0, 10));
                return $dt ? $dt->format('d/m/Y') : '—';
            };
            $brl = static function ($v) {
                if ($v === null || $v === '') {
                    return '';
                }
                return 'R$ ' . number_format((float) $v, 2, ',', '.');
            };
            $tipoLabel = ['nova' => 'Matrícula', 'rematricula' => 'Rematrícula', 'transferencia' => 'Transferência'];
            $cnpj = trim((string) ($escola['cnpj'] ?? ''));
            $endEscola = trim(($escola['endereco'] ?? '') . ' ' . ($escola['cidade'] ?? '') . ' ' . ($escola['uf'] ?? ''));
            $cidadeEscola = trim((string) ($escola['cidade'] ?? ''));
            $meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
            $cidadeData = ($cidadeEscola !== '' ? $cidadeEscola . ', ' : '')
                . (int) date('d') . ' de ' . ($meses[(int) date('n')] ?? '') . ' de ' . date('Y');

            $valorBruto = (float) ($contrato['valor_bruto'] ?? 0);
            $valorDesc = (float) ($contrato['valor_desconto'] ?? 0);
            $valorLiq = (float) ($contrato['valor_liquido'] ?? 0);
            $numParcelas = (int) ($contrato['num_parcelas'] ?? 0);
            $valorParcela = $numParcelas > 0 ? ($valorBruto / $numParcelas) : 0;
            $valorParcelaLiq = $numParcelas > 0 ? ($valorLiq / $numParcelas) : 0;
            $descParcela = $numParcelas > 0 ? ($valorDesc / $numParcelas) : 0;

            $itemValor = static function (?array $it): ?float {
                if ($it === null) {
                    return null;
                }
                foreach (['valor_total', 'valor_unitario', 'valor_bruto'] as $k) {
                    if (isset($it[$k]) && $it[$k] !== '' && $it[$k] !== null) {
                        return (float) $it[$k];
                    }
                }
                return null;
            };
            $itemLiquido = static function (?array $it) use ($itemValor): ?float {
                if ($it === null) {
                    return null;
                }
                if (isset($it['valor_liquido']) && $it['valor_liquido'] !== '' && $it['valor_liquido'] !== null) {
                    return (float) $it['valor_liquido'];
                }
                return $itemValor($it);
            };

            $primeira = null;
            $mensalidade = null;
            $descontos = [];
            foreach ($itens as $it) {
                $cat = (string) ($it['categoria'] ?? $it['tipo'] ?? '');
                if ($primeira === null && in_array($cat, ['matricula', 'primeira_parcela', 'taxa_matricula'], true)) {
                    $primeira = $it;
                }
                if ($mensalidade === null && $cat === 'mensalidade') {
                    $mensalidade = $it;
                }
                if (!empty($it['desconto_nome']) || ((float) ($it['valor_desconto'] ?? 0) > 0 && !empty($it['descricao']))) {
                    $descontos[] = $it;
                }
            }

            $mensalTotal = $itemValor($mensalidade);
            $mensalLiq = $itemLiquido($mensalidade);
            $mensalNp = max(1, (int) ($mensalidade['num_parcelas'] ?? 1));
            $parcelaMensalBruta = $mensalTotal !== null ? ($mensalTotal / $mensalNp) : null;
            $parcelaMensalLiq = $mensalLiq !== null ? ($mensalLiq / $mensalNp) : null;
            $parcelaMensalDesc = ($mensalidade && $parcelaMensalBruta !== null && $parcelaMensalLiq !== null)
                ? max(0, $parcelaMensalBruta - $parcelaMensalLiq)
                : null;

            if ($valorBruto <= 0 && $itens !== []) {
                $somaItens = 0.0;
                $somaLiqItens = 0.0;
                foreach ($itens as $it) {
                    $somaItens += (float) ($itemValor($it) ?? 0);
                    $somaLiqItens += (float) ($itemLiquido($it) ?? 0);
                }
                if ($somaItens > 0) {
                    $valorBruto = $somaItens;
                    $valorLiq = $somaLiqItens > 0 ? $somaLiqItens : $somaItens;
                    $valorDesc = max(0, $valorBruto - $valorLiq);
                    if ($numParcelas > 0) {
                        $valorParcela = $valorBruto / $numParcelas;
                        $valorParcelaLiq = $valorLiq / $numParcelas;
                        $descParcela = $valorDesc / $numParcelas;
                    }
                }
            }

            $fmtPct = static function (float $n) use ($esc): string {
                return $esc(rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',') . '%');
            };

            $listaResp = $this->model->listarResponsaveis((int) ($e['id'] ?? 0));
            $resp1 = $listaResp[0] ?? null;
            $resp2 = $listaResp[1] ?? null;
            $pagantes = array_values(array_filter(
                $listaResp,
                static fn ($r) => !empty($r['is_financeiro'])
            ));
            if ($pagantes === [] && $listaResp !== []) {
                $pagantes = [array_merge($listaResp[0], ['percentual' => $listaResp[0]['percentual'] ?? 100])];
            }
            // 3º contratante / resp financeiro distinto dos 2 primeiros
            $respFin = null;
            foreach ($listaResp as $r) {
                if (!empty($r['is_financeiro'])) {
                    $isFirstTwo = ($resp1 && (int) ($r['id'] ?? 0) === (int) ($resp1['id'] ?? -1))
                        || ($resp2 && (int) ($r['id'] ?? 0) === (int) ($resp2['id'] ?? -1));
                    if (!$isFirstTwo) {
                        $respFin = $r;
                        break;
                    }
                }
            }
            if ($respFin === null) {
                $respFin = $pagantes[0] ?? $resp1;
            }

            $pickEnd = static function (?array $r, string $field, string $fallback = '') use ($e): string {
                if ($r && isset($r[$field]) && trim((string) $r[$field]) !== '') {
                    return (string) $r[$field];
                }
                return $fallback;
            };

            $paganteNome = static function (int $i) use ($pagantes, $e): string {
                if (isset($pagantes[$i])) {
                    return (string) ($pagantes[$i]['nome'] ?? '');
                }
                if ($i === 0) {
                    return (string) ($e['resp_nome'] ?? '');
                }
                return (string) ($e['resp' . ($i + 1) . '_nome'] ?? '');
            };
            $paganteCpf = static function (int $i) use ($pagantes, $e): string {
                if (isset($pagantes[$i])) {
                    return (string) ($pagantes[$i]['documento'] ?? $pagantes[$i]['cpf'] ?? '');
                }
                if ($i === 0) {
                    return (string) ($e['resp_cpf'] ?? '');
                }
                return (string) ($e['resp' . ($i + 1) . '_cpf'] ?? '');
            };
            $pagantePct = static function (int $i) use ($pagantes, $cfgAssinatura): float {
                if (isset($pagantes[$i]) && $pagantes[$i]['percentual'] !== null && $pagantes[$i]['percentual'] !== '') {
                    return (float) $pagantes[$i]['percentual'];
                }
                $keys = ['pagante1_pct', 'pagante2_pct', 'pagante3_pct'];
                return (float) ($cfgAssinatura[$keys[$i] ?? 'pagante1_pct'] ?? ($i === 0 ? 100 : 0));
            };

            $rateioPartes = [];
            foreach ($pagantes as $i => $p) {
                $pct = $pagantePct($i);
                $rateioPartes[] = trim((string) ($p['nome'] ?? 'Pagante')) . ' (' . rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',') . '%)';
            }
            $rateioTexto = $rateioPartes !== [] ? implode('; ', $rateioPartes) : '';

            $vars = [
                'escola_nome' => $esc($escola['nome'] ?? (defined('TENANT_SLUG') ? TENANT_SLUG : 'Escola')),
                'escola_cnpj' => $cnpj !== '' ? $esc(' · CNPJ: ' . $cnpj) : '',
                'escola_cnpj_numero' => $esc($cnpj),
                'escola_endereco' => $esc($endEscola),
                'escola_origem' => $esc($e['escola_origem'] ?? ''),
                'logo_html' => $this->logoHtmlEscola($escola),
                'aluno_nome' => $esc($e['aluno_nome'] ?? ''),
                'aluno_cpf' => $esc($e['aluno_cpf'] ?? '—'),
                'aluno_rg' => $esc($e['aluno_rg'] ?? ''),
                'aluno_data_nasc' => $esc($fmtDt($e['aluno_data_nasc'] ?? null)),
                'aluno_email' => $esc($e['aluno_email'] ?? '—'),
                'aluno_telefone' => $esc($e['aluno_telefone'] ?? '—'),
                'aluno_endereco' => $esc($e['aluno_endereco'] ?? ''),
                'aluno_cidade' => $esc($e['aluno_end_cidade'] ?? $e['aluno_cidade'] ?? ''),
                'aluno_codigo' => $esc($e['aluno_codigo'] ?? $e['ra'] ?? ''),
                'matricula_numero' => $esc($e['matricula_numero'] ?? $e['id'] ?? ''),
                'curso_nome' => $esc($e['curso_nome'] ?? $e['turma_serie'] ?? ''),
                'resp_nome' => $esc($resp1['nome'] ?? $e['resp_nome'] ?? ''),
                'resp_cpf' => $esc($resp1['documento'] ?? $resp1['cpf'] ?? $e['resp_cpf'] ?? '—'),
                'resp_rg' => $esc($resp1['rg'] ?? $e['resp_rg'] ?? ''),
                'resp_email' => $esc($resp1['email'] ?? $e['resp_email'] ?? '—'),
                'resp_telefone' => $esc($resp1['telefone'] ?? $e['resp_telefone'] ?? '—'),
                'resp_celular' => $esc($resp1['telefone'] ?? $e['resp_celular'] ?? $e['resp_telefone'] ?? ''),
                'resp_parentesco' => $esc($resp1['tipo_vinculo'] ?? $e['resp_parentesco'] ?? '—'),
                'resp_endereco' => $esc($pickEnd($resp1, 'endereco', (string) ($e['resp_endereco'] ?? '—'))),
                'resp_bairro' => $esc($pickEnd($resp1, 'end_bairro', (string) ($e['resp_bairro'] ?? ''))),
                'resp_cep' => $esc($pickEnd($resp1, 'end_cep', (string) ($e['resp_cep'] ?? ''))),
                'resp_cidade' => $esc($pickEnd($resp1, 'end_cidade', (string) ($e['resp_cidade'] ?? ''))),
                'resp_estado_civil' => $esc($resp1['estado_civil'] ?? $e['resp_estado_civil'] ?? ''),
                'resp2_nome' => $esc($resp2['nome'] ?? $e['resp2_nome'] ?? ''),
                'resp2_cpf' => $esc($resp2['documento'] ?? $resp2['cpf'] ?? $e['resp2_cpf'] ?? ''),
                'resp2_rg' => $esc($resp2['rg'] ?? $e['resp2_rg'] ?? ''),
                'resp2_email' => $esc($resp2['email'] ?? $e['resp2_email'] ?? ''),
                'resp2_telefone' => $esc($resp2['telefone'] ?? $e['resp2_telefone'] ?? ''),
                'resp2_celular' => $esc($resp2['telefone'] ?? $e['resp2_celular'] ?? ''),
                'resp2_parentesco' => $esc($resp2['tipo_vinculo'] ?? $e['resp2_parentesco'] ?? ''),
                'resp2_endereco' => $esc($pickEnd($resp2, 'endereco', (string) ($e['resp2_endereco'] ?? ''))),
                'resp2_bairro' => $esc($pickEnd($resp2, 'end_bairro', (string) ($e['resp2_bairro'] ?? ''))),
                'resp2_cep' => $esc($pickEnd($resp2, 'end_cep', (string) ($e['resp2_cep'] ?? ''))),
                'resp2_cidade' => $esc($pickEnd($resp2, 'end_cidade', (string) ($e['resp2_cidade'] ?? ''))),
                'resp2_estado_civil' => $esc($resp2['estado_civil'] ?? $e['resp2_estado_civil'] ?? ''),
                'resp_fin_nome' => $esc((string) ($respFin['nome'] ?? $paganteNome(0) ?: ($e['resp_fin_nome'] ?? $e['resp_nome'] ?? ''))),
                'resp_fin_cpf' => $esc((string) ($respFin['documento'] ?? $respFin['cpf'] ?? $paganteCpf(0) ?: ($e['resp_fin_cpf'] ?? $e['resp_cpf'] ?? ''))),
                'resp_fin_rg' => $esc((string) ($respFin['rg'] ?? $e['resp_fin_rg'] ?? '')),
                'resp_fin_email' => $esc((string) ($respFin['email'] ?? $e['resp_fin_email'] ?? $e['resp_email'] ?? '')),
                'resp_fin_telefone' => $esc((string) ($respFin['telefone'] ?? $e['resp_fin_telefone'] ?? $e['resp_telefone'] ?? '')),
                'resp_fin_celular' => $esc((string) ($respFin['telefone'] ?? $e['resp_fin_celular'] ?? '')),
                'resp_fin_endereco' => $esc($pickEnd($respFin, 'endereco', (string) ($e['resp_fin_endereco'] ?? $e['resp_endereco'] ?? ''))),
                'resp_fin_bairro' => $esc($pickEnd($respFin, 'end_bairro', (string) ($e['resp_fin_bairro'] ?? ''))),
                'resp_fin_cep' => $esc($pickEnd($respFin, 'end_cep', (string) ($e['resp_fin_cep'] ?? ''))),
                'resp_fin_cidade' => $esc($pickEnd($respFin, 'end_cidade', (string) ($e['resp_fin_cidade'] ?? ''))),
                'pagante1_nome' => $esc($paganteNome(0)),
                'pagante1_cpf' => $esc($paganteCpf(0)),
                'pagante1_percentual' => $fmtPct($pagantePct(0)),
                'pagante2_nome' => $esc($paganteNome(1)),
                'pagante2_cpf' => $esc($paganteCpf(1)),
                'pagante2_percentual' => $fmtPct($pagantePct(1)),
                'pagante3_nome' => $esc($paganteNome(2)),
                'pagante3_cpf' => $esc($paganteCpf(2)),
                'pagante3_percentual' => $fmtPct($pagantePct(2)),
                'rateio_pagantes' => $esc($rateioTexto),
                'pagante_modo' => $esc($cfgAssinatura['pagante_modo_rotulo'] ?? ''),
                'documento_assinatura' => $esc($cfgAssinatura['documento_rotulo'] ?? ''),
                'turma_nome' => $esc(($e['turma_nome'] ?? '—') . (!empty($e['turma_serie']) ? ' — ' . $e['turma_serie'] : '')),
                'serie' => $esc($e['turma_serie'] ?? ''),
                'ano_letivo' => $esc($e['ano_letivo_nome'] ?? date('Y')),
                'tipo_matricula' => $esc($tipoLabel[$e['tipo'] ?? 'nova'] ?? 'Matrícula'),
                'data_hoje' => $esc(date('d/m/Y')),
                'cidade_data' => $esc($cidadeData),
                'data_rematricula' => $esc($fmtDt($e['created_at'] ?? $e['data_solicitacao'] ?? null)),
                'valor_anuidade' => $esc($brl($valorBruto ?: null)),
                'valor_parcela' => $esc($brl($parcelaMensalBruta ?? ($valorParcela ?: null))),
                'valor_liquido_parcela' => $esc($brl($parcelaMensalLiq ?? ($valorParcelaLiq ?: null))),
                'desconto_parcela' => $esc($brl($parcelaMensalDesc ?? ($descParcela ?: null))),
                'valor_mensalidades_liq' => $esc($brl($valorLiq ?: null)),
                'num_parcelas' => $esc((string) ($numParcelas ?: '')),
                'valor_primeira_parcela' => $esc($brl($itemValor($primeira))),
                'desconto_primeira' => $esc($brl(
                    ($primeira !== null && isset($primeira['valor_desconto']))
                        ? (float) $primeira['valor_desconto']
                        : null
                )),
                'desconto_primeira_obs' => $esc((string) ($primeira['descricao'] ?? '')),
                'valor_liquido_primeira' => $esc($brl($itemLiquido($primeira))),
                'qtd_parcelas_primeira' => $esc((string) (($primeira['num_parcelas'] ?? null) !== null ? $primeira['num_parcelas'] : '1')),
                'observacoes' => !empty($e['observacoes'])
                    ? '<p><strong>Observações:</strong> ' . nl2br($esc($e['observacoes'])) . '</p>'
                    : '',
            ];

            for ($i = 1; $i <= 4; $i++) {
                $d = $descontos[$i - 1] ?? null;
                $vars['desc' . $i . '_nome'] = $esc($d['desconto_nome'] ?? $d['descricao'] ?? '');
                $vars['desc' . $i . '_valor'] = $esc($d ? $brl($d['valor_desconto'] ?? $d['valor'] ?? null) : '');
            }

            return $svc->renderHtml($modelo, $vars, 'simples', $config);
        } catch (\Throwable $ex) {
            return null;
        }
    }

    private function logoHtmlEscola(array $escola): string
    {
        $src = $this->resolverSrcLogoSeguro(trim((string) ($escola['logo_url'] ?? '')));
        if ($src === null) {
            return '';
        }
        $esc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars((string) ($escola['nome'] ?? 'Logo'), ENT_QUOTES, 'UTF-8');
        return '<img src="' . $esc . '" alt="' . $alt . '" style="max-height:70px;max-width:220px;width:auto;height:auto;">';
    }

    /**
     * Aceita data:image/* ou arquivo sob BASE_PATH/storage (sem ..).
     * Converte arquivo local em data-URI (Dompdf com remote off + trilha pública).
     * URLs http(s) são omitidas (SSRF / remote off).
     */
    private function resolverSrcLogoSeguro(string $url): ?string
    {
        if ($url === '') {
            return null;
        }
        $lower = strtolower($url);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'file:') || str_starts_with($lower, 'vbscript:')) {
            return null;
        }
        if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
            return null;
        }
        if (str_starts_with($lower, 'data:')) {
            if (!preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $url)) {
                return null;
            }
            return $url;
        }

        $rel = $url;
        if (str_starts_with($rel, '/')) {
            $pos = strpos($rel, '/storage/');
            if ($pos === false) {
                return null;
            }
            $rel = ltrim(substr($rel, $pos + 1), '/');
        }
        if (!str_starts_with($rel, 'storage/')) {
            return null;
        }
        if (str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $storageRoot = realpath($base . '/storage');
        $full = realpath($base . '/' . $rel);
        if ($storageRoot === false || $full === false || !is_file($full) || !is_readable($full)) {
            return null;
        }
        if (!str_starts_with($full, $storageRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }
        if (filesize($full) > 2 * 1024 * 1024) {
            return null;
        }
        $mime = 'image/png';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $full);
                finfo_close($finfo);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    $mime = $detected;
                }
            }
        }
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return null;
        }
        $bin = file_get_contents($full);
        if ($bin === false || $bin === '') {
            return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    /**
     * HTML do contrato na trilha pública (allowlist — sem script/iframe/on*).
     */
    private function sanitizarHtmlContratoPublico(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;

        if (class_exists(\HTMLPurifier::class, false) || class_exists('HTMLPurifier')) {
            try {
                $config = \HTMLPurifier_Config::createDefault();
                $cacheDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4)) . '/storage/cache/htmlpurifier';
                if (!is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0775, true);
                }
                if (is_dir($cacheDir) && is_writable($cacheDir)) {
                    $config->set('Cache.SerializerPath', $cacheDir);
                } else {
                    $config->set('Cache.DefinitionImpl', null);
                }
                $config->set(
                    'HTML.Allowed',
                    'p[style|class],br,b,strong,i,em,u,s,ul,ol,li,a[href|title],'
                    . 'h1[style|class],h2[style|class],h3[style|class],h4[style|class],'
                    . 'blockquote,span[style|class],div[style|class],'
                    . 'table[style|class|border|cellpadding|cellspacing|width],thead,tbody,tfoot,'
                    . 'tr[style|class],th[style|class|colspan|rowspan],td[style|class|colspan|rowspan],'
                    . 'img[src|alt|style|width|height|class]'
                );
                $config->set('CSS.AllowedProperties', [
                    'color', 'background-color', 'text-align', 'font-weight', 'font-style',
                    'text-decoration', 'font-size', 'margin', 'padding', 'border',
                    'border-collapse', 'width', 'height', 'max-width', 'max-height',
                ]);
                $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'data' => true, 'mailto' => true]);
                $config->set('URI.SafeDataURI', true);
                $config->set('HTML.DefinitionID', 'educatudo-contrato-publico-v1');
                $config->set('HTML.DefinitionRev', 1);
                $purifier = new \HTMLPurifier($config);
                return $purifier->purify($html);
            } catch (\Throwable $e) {
                error_log('[MatriculaProcessoService] purify contrato: ' . $e->getMessage());
            }
        }

        $allowed = '<p><br><b><strong><i><em><u><s><ul><ol><li><a><h1><h2><h3><h4><blockquote><span><div><table><thead><tbody><tfoot><tr><th><td><img>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|vbscript)\s*:[^"\']*\2/i', '$1=$2#$2', $clean) ?? $clean;
        return $clean;
    }

    private function renderContratoHtml(array $e, array $escola, array $contrato = [], array $itens = []): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $brl = static fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $fmtDt = static function ($d) {
            if (!$d || $d === '0000-00-00') {
                return '—';
            }
            $dt = \DateTime::createFromFormat('Y-m-d', substr((string) $d, 0, 10));
            return $dt ? $dt->format('d/m/Y') : '—';
        };
        $tipoLabel = ['nova' => 'Matrícula', 'rematricula' => 'Rematrícula', 'transferencia' => 'Transferência'];
        $catLabel = [
            'mensalidade' => 'Mensalidade', 'matricula' => 'Matrícula',
            'material_didatico' => 'Material Didático', 'uniforme' => 'Uniforme',
            'taxa' => 'Taxa', 'outros' => 'Outros',
        ];
        $tipo = $tipoLabel[$e['tipo'] ?? 'nova'] ?? 'Matrícula';
        $nomeEscola = $escola['nome'] ?? (defined('TENANT_SLUG') ? TENANT_SLUG : 'Escola');
        $cnpj = $escola['cnpj'] ?? '';
        $endEscola = trim(($escola['endereco'] ?? '') . ' ' . ($escola['cidade'] ?? '') . ' ' . ($escola['uf'] ?? ''));
        $hoje = date('d/m/Y');
        $ano = $e['ano_letivo_nome'] ?? date('Y');
        $turma = $e['turma_nome'] ?? '—';

        $valorDesc = (float) ($contrato['valor_desconto'] ?? 0);
        $valorLiq = (float) ($contrato['valor_liquido'] ?? 0);
        $diaVenc = $contrato['dia_vencimento'] ?? '—';
        $numParcelas = $contrato['num_parcelas'] ?? 0;

        $itensHtml = '';
        foreach ($itens as $item) {
            $cat = $catLabel[$item['categoria'] ?? ''] ?? ($item['categoria'] ?? '');
            $parc = ((int) ($item['num_parcelas'] ?? 1) > 1)
                ? $item['num_parcelas'] . 'x de ' . $brl(((float) $item['valor_liquido']) / max(1, (int) $item['num_parcelas']))
                : 'À vista';
            $itensHtml .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td><td style="text-align:center">%s</td></tr>',
                $esc($cat),
                $esc($item['descricao'] ?? ''),
                $brl($item['valor_total'] ?? 0),
                $brl($item['valor_liquido'] ?? 0),
                $esc($parc)
            );
        }
        $tabelaFinanceira = '';
        if ($itensHtml !== '') {
            $descontoRow = $valorDesc > 0
                ? "<tr><td colspan='3' style='text-align:right;font-style:italic;color:#666;'>Desconto total</td><td style='text-align:right;color:#16a34a;'>- {$brl($valorDesc)}</td><td></td></tr>"
                : '';
            $tabelaFinanceira = <<<FIN
<div class="section-title">3. Condições Financeiras (plano escolhido)</div>
<table class="dados-fin">
  <thead><tr><th>Categoria</th><th>Descrição</th><th style="text-align:right">Valor Bruto</th><th style="text-align:right">Valor Líquido</th><th style="text-align:center">Forma</th></tr></thead>
  <tbody>
    {$itensHtml}
    {$descontoRow}
    <tr class="total-row"><td colspan="3" style="text-align:right">TOTAL</td><td style="text-align:right">{$brl($valorLiq)}</td><td></td></tr>
  </tbody>
</table>
<p style="font-size:9pt;margin-top:6px;">Vencimento: dia <strong>{$esc($diaVenc)}</strong> &nbsp;|&nbsp; {$esc($numParcelas)} parcela(s). Cobrança não é emitida automaticamente nesta etapa.</p>
FIN;
        }

        $logoHtml = $this->logoHtmlEscola($escola);
        $cnpjHtml = $cnpj ? "<br>CNPJ: {$esc($cnpj)}" : '';
        $endHtml = $endEscola ? "<br>{$esc($endEscola)}" : '';
        $obsClausula = $this->renderClausulasObservacoes((string) ($e['observacoes'] ?? ''));
        $secClausulas = $tabelaFinanceira ? '4' : '3';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="UTF-8"><style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 30px 40px; }
.header { border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 20px; }
.escola-nome { font-size: 14pt; font-weight: bold; color: #1d4ed8; }
.section-title { font-size: 10pt; font-weight: bold; color: #fff; background: #1d4ed8; padding: 4px 8px; margin: 16px 0 8px; }
table.dados { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
table.dados td { padding: 5px 8px; border: 1px solid #d1d5db; font-size: 9.5pt; }
table.dados td.label { background: #f3f4f6; font-weight: bold; width: 38%; }
table.dados-fin { width: 100%; border-collapse: collapse; font-size: 9pt; }
table.dados-fin th { background: #1e3a5f; color: #fff; padding: 5px 8px; }
table.dados-fin td { padding: 5px 8px; border: 1px solid #d1d5db; }
.clausula { margin: 10px 0; text-align: justify; font-size: 9.5pt; line-height: 1.5; }
.assinatura-wrap { margin-top: 36px; border-top: 1px solid #9ca3af; padding-top: 12px; }
.assinatura-cols { display: table; width: 100%; }
.assinatura-col { display: table-cell; width: 50%; text-align: center; padding: 0 20px; }
.sig-line { border-top: 1px solid #333; margin: 32px auto 6px; width: 80%; }
.rodape { margin-top: 24px; font-size: 7.5pt; color: #9ca3af; text-align: center; }
</style></head><body>
<div class="header">
  {$logoHtml}
  <div class="escola-nome">{$esc($nomeEscola)}</div>
  <div style="font-size:8pt;color:#555;">{$cnpjHtml}{$endHtml}</div>
  <h1 style="font-size:13pt;color:#1d4ed8;margin-top:8px;">Contrato de {$esc($tipo)}</h1>
  <p style="font-size:8pt;color:#777;">Ano Letivo {$esc($ano)} | Emitido em {$hoje}</p>
</div>
<div class="section-title">1. Dados do Aluno</div>
<table class="dados">
  <tr><td class="label">Nome completo</td><td>{$esc($e['aluno_nome'])}</td><td class="label">Turma</td><td>{$esc($turma)}</td></tr>
  <tr><td class="label">CPF</td><td>{$esc($e['aluno_cpf'] ?? '—')}</td><td class="label">Nascimento</td><td>{$fmtDt($e['aluno_data_nasc'] ?? '')}</td></tr>
  <tr><td class="label">E-mail</td><td>{$esc($e['aluno_email'] ?? '—')}</td><td class="label">Telefone</td><td>{$esc($e['aluno_telefone'] ?? '—')}</td></tr>
</table>
<div class="section-title">2. Responsável Legal</div>
<table class="dados">
  <tr><td class="label">Nome</td><td>{$esc($e['resp_nome'])}</td><td class="label">Parentesco</td><td>{$esc($e['resp_parentesco'] ?? '—')}</td></tr>
  <tr><td class="label">CPF</td><td>{$esc($e['resp_cpf'] ?? '—')}</td><td class="label">Telefone</td><td>{$esc($e['resp_telefone'] ?? '—')}</td></tr>
  <tr><td class="label">E-mail</td><td colspan="3">{$esc($e['resp_email'] ?? '—')}</td></tr>
  <tr><td class="label">Endereço</td><td colspan="3">{$esc($e['resp_endereco'] ?? '—')}</td></tr>
</table>
{$tabelaFinanceira}
<div class="section-title">{$secClausulas}. Cláusulas Contratuais</div>
<div class="clausula"><strong>Objeto</strong> O presente contrato tem por objeto a {$esc($tipo)} do(a) aluno(a) para o ano letivo {$esc($ano)}, turma {$esc($turma)}.</div>
<div class="clausula"><strong>Obrigações da Instituição</strong> Oferecer ensino de qualidade, infraestrutura adequada e comunicação transparente com a família.</div>
<div class="clausula"><strong>Obrigações do Responsável</strong> Manter dados atualizados, honrar obrigações financeiras e acompanhar o desenvolvimento do(a) aluno(a).</div>
<div class="clausula"><strong>Vigência</strong> Válido para o ano letivo {$esc($ano)}. Rescisão com aviso prévio de 30 dias.</div>
{$obsClausula}
<div class="assinatura-wrap"><div class="assinatura-cols">
  <div class="assinatura-col"><div class="sig-line"></div><div>{$esc($nomeEscola)}<br>Representante Legal</div></div>
  <div class="assinatura-col"><div class="sig-line"></div><div>{$esc($e['resp_nome'])}<br>Responsável</div></div>
</div></div>
<div class="rodape">Documento gerado pelo EducaTudo em {$hoje}. Hash de verificação gravado na assinatura.</div>
</body></html>
HTML;
    }

    private function renderClausulasObservacoes(string $obs): string
    {
        if (trim($obs) === '') {
            return '';
        }
        return '<div class="clausula"><strong>Observações</strong>' . nl2br(htmlspecialchars($obs, ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    public function atualizarDadosTrilha(array $enrollment): array
    {
        $id = (int) ($enrollment['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Processo inválido.');
        }
        if (trim((string) ($enrollment['aluno_nome'] ?? '')) === '' || trim((string) ($enrollment['resp_nome'] ?? '')) === '') {
            throw new \InvalidArgumentException('Nome do aluno e do responsável são obrigatórios. Contate a secretaria.');
        }
        $dados = ['dados_confirmados_em' => date('Y-m-d H:i:s')];
        $this->model->update($id, $dados);
        $this->model->transition($id, (string) ($enrollment['status'] ?? 'aguardando_assinatura'), null, 'trilha_dados_confirmados');
        return $this->model->findById($id) ?: array_merge($enrollment, $dados);
    }

    public function etapaTrilha(array $enrollment, ?string $forcar = null): string
    {
        $status = (string) ($enrollment['status'] ?? '');
        if (in_array($status, ['confirmada', 'enturmada'], true)) {
            return 'assinado';
        }
        if ($forcar === 'dados' || $forcar === 'contrato') {
            if ($forcar === 'contrato' && empty($enrollment['dados_confirmados_em'])) {
                return 'dados';
            }
            return $forcar;
        }
        if (empty($enrollment['dados_confirmados_em']) && $this->model->temColuna('dados_confirmados_em')) {
            return 'dados';
        }
        return 'contrato';
    }

    public function registrarAssinatura(array $enrollment, string $ip, string $nomeAssinante): void
    {
        $id = (int) $enrollment['id'];
        $hash = hash('sha256', ($enrollment['contrato_hash'] ?? '') . $ip . date('c'));
        $upd = [
            'status' => 'confirmada',
            'assinado_em' => date('Y-m-d H:i:s'),
            'assinante_ip' => $ip,
            'assinante_nome' => $nomeAssinante,
            'contrato_hash' => $hash,
        ];
        if ($this->model->temColuna('pagamento_status')) {
            // Sem emissão de boleto nesta fase — marca como dispensado/aguardando manual
            $upd['pagamento_status'] = 'dispensado';
        }
        $this->model->update($id, $upd);
        $this->model->transition($id, 'confirmada', null, 'assinatura_responsavel');
    }

    public function camposFaltandoParaEfetivar(array $enrollment): array
    {
        $faltando = [];
        if ((int) ($enrollment['ano_letivo_id'] ?? 0) <= 0) {
            $faltando[] = 'Ano letivo';
        }
        if ((int) ($enrollment['turma_id'] ?? 0) <= 0) {
            $faltando[] = 'Turma';
        }
        if (trim((string) ($enrollment['aluno_nome'] ?? '')) === '') {
            $faltando[] = 'Aluno: nome';
        }
        $alunoCpf = preg_replace('/\D+/', '', (string) ($enrollment['aluno_cpf'] ?? '')) ?? '';
        if (strlen($alunoCpf) !== 11) {
            $faltando[] = 'Aluno: CPF';
        }
        $nasc = trim((string) ($enrollment['aluno_data_nasc'] ?? ''));
        if ($nasc === '' || $nasc === '0000-00-00') {
            $faltando[] = 'Aluno: data de nascimento';
        }
        if (trim((string) ($enrollment['resp_nome'] ?? '')) === '') {
            $faltando[] = 'Responsável: nome';
        }
        return $faltando;
    }

    public function enturmarProcesso(int $enrollmentId, ?array $user = null, ?string $origemStatus = null): array
    {
        $enrollment = $this->model->findById($enrollmentId);
        if (!$enrollment) {
            throw new \InvalidArgumentException('Processo de matrícula não encontrado.');
        }

        $turmaId = (int) ($enrollment['turma_id'] ?? 0);
        $anoLetivoId = (int) ($enrollment['ano_letivo_id'] ?? 0);
        if ($turmaId <= 0 || $anoLetivoId <= 0) {
            throw new \InvalidArgumentException('Defina turma e ano letivo no processo antes de enturmar.');
        }

        if (($enrollment['status'] ?? '') === 'enturmada' && (int) ($enrollment['aluno_id'] ?? 0) > 0) {
            return [
                'ok' => true,
                'aluno_id' => (int) $enrollment['aluno_id'],
                'status' => 'enturmada',
                'mensagem' => 'Processo já enturmado. Ficha: /admin/students/' . (int) $enrollment['aluno_id'],
                'criado_aluno' => false,
            ];
        }

        $faltando = $this->camposFaltandoParaEfetivar($enrollment);
        if ($faltando !== []) {
            throw new \InvalidArgumentException(
                'Preencha o cadastro completo antes de efetivar. Faltando: ' . implode('; ', $faltando) . '.'
            );
        }

        $criadoAluno = false;
        $alunoId = $this->garantirAlunoDoProcesso($enrollment, $criadoAluno);
        $this->vincularResponsavelDoProcesso($alunoId, $enrollment, ($enrollment['tipo'] ?? '') === 'nova' || $criadoAluno);

        $mov = new AlunoMovimentacaoService();
        try {
            $mov->vincularAlunoTurma($alunoId, $turmaId, $anoLetivoId, true, date('Y-m-d'));
        } catch (\Throwable $e) {
            if (!str_contains(mb_strtolower($e->getMessage()), 'já possui matrícula')
                && !str_contains(mb_strtolower($e->getMessage()), 'ja possui matricula')) {
                throw $e;
            }
        }

        $this->model->update($enrollmentId, ['aluno_id' => $alunoId]);
        $acao = $origemStatus === 'confirmada' ? 'confirmacao_e_enturmacao' : 'enturmacao_automatica';
        $this->model->transition($enrollmentId, 'enturmada', $user, $acao);

        return [
            'ok' => true,
            'aluno_id' => $alunoId,
            'status' => 'enturmada',
            'mensagem' => ($criadoAluno ? 'Aluno cadastrado e enturmado. ' : 'Vínculo acadêmico criado. ')
                . 'Ficha: /admin/students/' . $alunoId,
            'criado_aluno' => $criadoAluno,
        ];
    }

    private function garantirAlunoDoProcesso(array $enrollment, bool &$criado): int
    {
        $alunoId = (int) ($enrollment['aluno_id'] ?? 0);
        if ($alunoId > 0) {
            $existe = $this->db->fetch('SELECT id FROM alunos WHERE id = ? LIMIT 1', [$alunoId]);
            if (!$existe) {
                throw new \RuntimeException('Aluno #' . $alunoId . ' do processo não existe mais.');
            }
            $criado = false;
            return $alunoId;
        }

        $cpfDigits = preg_replace('/\D+/', '', (string) ($enrollment['aluno_cpf'] ?? '')) ?? '';
        if (strlen($cpfDigits) === 11) {
            $porCpf = $this->db->fetch(
                "SELECT id FROM alunos
                 WHERE REPLACE(REPLACE(REPLACE(IFNULL(cpf,''), '.', ''), '-', ''), ' ', '') = ?
                 LIMIT 1",
                [$cpfDigits]
            );
            if ($porCpf) {
                $criado = false;
                return (int) $porCpf['id'];
            }
        }

        $nome = trim((string) ($enrollment['aluno_nome'] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Processo sem aluno vinculado e sem nome para cadastro.');
        }

        $ra = 'A' . date('ymdHis') . random_int(10, 99);
        $student = new Student();
        $alunoId = (int) $student->create([
            'nome' => $nome,
            'email' => $enrollment['aluno_email'] ?? null,
            'telefone' => $enrollment['aluno_telefone'] ?? null,
            'cpf' => $enrollment['aluno_cpf'] ?? null,
            'data_nasc' => $enrollment['aluno_data_nasc'] ?? null,
            'ra' => $ra,
            'codigo_aluno' => $ra,
            'turma_id' => null,
            'serie' => 'ND',
            'senha' => bin2hex(random_bytes(8)),
            'ativo' => 1,
            'pagante' => 1,
        ]);
        if ($alunoId <= 0) {
            throw new \RuntimeException('Falha ao cadastrar aluno a partir do processo.');
        }
        $criado = true;
        return $alunoId;
    }

    private function vincularResponsavelDoProcesso(int $alunoId, array $enrollment, bool $obrigatorio = false): void
    {
        $nome = trim((string) ($enrollment['resp_nome'] ?? ''));
        if ($alunoId <= 0 || $nome === '') {
            if ($obrigatorio) {
                throw new \InvalidArgumentException('Informe o responsável no processo antes de enturmar.');
            }
            return;
        }

        try {
            $respId = $this->resolverOuCriarResponsavel($enrollment, $nome);
            if ($respId <= 0) {
                throw new \RuntimeException('Não foi possível criar/localizar o responsável.');
            }

            $existe = $this->db->fetch(
                'SELECT id FROM alunos_responsaveis WHERE aluno_id = ? AND responsavel_id = ? AND ativo = 1 LIMIT 1',
                [$alunoId, $respId]
            );
            if (!$existe) {
                $this->db->insert(
                    'INSERT INTO alunos_responsaveis
                     (aluno_id, responsavel_id, tipo_vinculo, is_financeiro, ativo, created_at, updated_at)
                     VALUES (?, ?, ?, 1, 1, NOW(), NOW())',
                    [$alunoId, $respId, $enrollment['resp_parentesco'] ?? 'responsavel']
                );
            }

            try {
                if ($this->db->fetch("SHOW COLUMNS FROM alunos LIKE 'responsavel_id'")) {
                    $this->db->query(
                        'UPDATE alunos SET responsavel_id = COALESCE(responsavel_id, ?) WHERE id = ?',
                        [$respId, $alunoId]
                    );
                }
            } catch (\Throwable $e) {
                // ignore
            }
        } catch (\Throwable $e) {
            if ($obrigatorio) {
                throw $e;
            }
            error_log('[MatriculaProcessoService] vínculo responsável: ' . $e->getMessage());
        }
    }

    private function resolverOuCriarResponsavel(array $enrollment, string $nome): int
    {
        $cpfDigits = preg_replace('/\D+/', '', (string) ($enrollment['resp_cpf'] ?? '')) ?? '';
        if (strlen($cpfDigits) === 11) {
            $exist = $this->db->fetch(
                "SELECT id FROM responsaveis
                 WHERE REPLACE(REPLACE(REPLACE(IFNULL(cpf,''), '.', ''), '-', ''), ' ', '') = ?
                 LIMIT 1",
                [$cpfDigits]
            );
            if ($exist) {
                return (int) $exist['id'];
            }
        }

        $senhaHash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $cols = ['nome', 'senha_hash', 'ativo'];
        $params = [$nome, $senhaHash, 1];
        foreach (['cpf' => $enrollment['resp_cpf'] ?? null, 'email' => $enrollment['resp_email'] ?? null, 'telefone' => $enrollment['resp_telefone'] ?? null] as $col => $val) {
            if ($this->db->fetch("SHOW COLUMNS FROM responsaveis LIKE '{$col}'")) {
                $cols[] = $col;
                $params[] = $val;
            }
        }
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $this->db->insert(
            'INSERT INTO responsaveis (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')',
            $params
        );
        return (int) $this->db->lastInsertId();
    }

    public function buildWhatsAppLink(array $enrollment, string $baseUrl): string
    {
        $token = $enrollment['contrato_token'] ?? '';
        $url = rtrim($baseUrl, '/') . '/matricula/contrato/' . $token;
        $msg = "Olá, {$enrollment['resp_nome']}!\n\n"
            . "A matrícula de *{$enrollment['aluno_nome']}* está pronta para assinatura.\n\n"
            . "Clique no link para revisar e assinar o contrato:\n{$url}\n\n"
            . '_EducaTudo_';
        $phone = preg_replace('/\D/', '', $enrollment['resp_telefone'] ?? '');
        if ($phone && !str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
    }

    public function getEscola(): array
    {
        $rows = $this->db->fetchAll('SELECT config_key, config_value FROM config_layout') ?: [];
        $cfg = [];
        foreach ($rows as $r) {
            $cfg[$r['config_key']] = $r['config_value'];
        }
        return [
            'nome' => $cfg['nome_escola'] ?? $cfg['system_title'] ?? 'Escola',
            'cnpj' => $cfg['cnpj'] ?? '',
            'endereco' => $cfg['endereco'] ?? '',
            'cidade' => $cfg['cidade'] ?? '',
            'uf' => $cfg['uf'] ?? '',
            'telefone' => $cfg['telefone'] ?? '',
            'email' => $cfg['email_escola'] ?? '',
            'logo_url' => $cfg['logo_url'] ?? '',
        ];
    }

    public function getAnosLetivos(): array
    {
        return $this->db->fetchAll('SELECT id, ano, ativo FROM ano_letivo ORDER BY ano DESC') ?: [];
    }

    public function getTurmas(?int $anoLetivoId = null): array
    {
        $sql = 'SELECT t.id, t.nome, t.serie FROM turmas t WHERE t.ativo = 1';
        $params = [];
        if ($anoLetivoId) {
            $sql .= ' AND t.ano_letivo_id = ?';
            $params[] = $anoLetivoId;
        }
        $sql .= ' ORDER BY t.serie, t.nome';
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function listarAlunosPorTurma(int $turmaId): array
    {
        return $this->db->fetchAll(
            'SELECT a.id, a.nome, a.cpf, a.email, a.telefone, a.turma_id
             FROM alunos a
             WHERE a.turma_id = ? AND a.ativo = 1
             ORDER BY a.nome',
            [$turmaId]
        ) ?: [];
    }

    private function invalidarCacheConfig(): void
    {
        try {
            if (class_exists('RedisCache', false)) {
                if (defined('TENANT_ID')) {
                    \RedisCache::delete('config_layout_' . TENANT_ID);
                }
                \RedisCache::delete('config_layout_single');
                \RedisCache::delete('config_layout');
            }
        } catch (\Throwable $e) {
            // ok
        }
    }
}
