<?php

namespace App\Modulos\Carteirinha\Services;

use App\Helpers\AvatarUrlHelper;
use App\Modulos\Carteirinha\Models\Carteirinha;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class CarteirinhaService
{
    private Carteirinha $model;

    /** @var bool|null */
    private static $escolaUsaCache = null;

    public function __construct(?Carteirinha $model = null)
    {
        $this->model = $model ?? new Carteirinha();
    }

    public static function escolaUsa(): bool
    {
        if (self::$escolaUsaCache !== null) {
            return self::$escolaUsaCache;
        }
        try {
            if (!\App\Core\LayoutHelper::isModuleEnabled('carteirinha')) {
                self::$escolaUsaCache = false;
                return false;
            }
            self::$escolaUsaCache = (new Carteirinha())->habilitada();
        } catch (\Throwable $e) {
            self::$escolaUsaCache = false;
        }
        return self::$escolaUsaCache;
    }

    public static function limparCache(): void
    {
        self::$escolaUsaCache = null;
    }

    public function tabelasProntas(): bool
    {
        return $this->model->tabelasProntas();
    }

    public function habilitada(): bool
    {
        return $this->model->habilitada();
    }

    public function definirHabilitada(bool $ligada): void
    {
        $this->model->definirHabilitada($ligada);
        self::limparCache();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function turmas(): array
    {
        return $this->model->turmas();
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int,pagina:int,total_paginas:int}
     */
    public function listar(int $turmaId, string $busca, int $pagina): array
    {
        $porPagina = 20;
        $lista = $this->model->listarAlunos($turmaId, $busca, $pagina, $porPagina);
        $rows = [];
        foreach ($lista['rows'] as $row) {
            $rows[] = $this->linhaLista($row);
        }
        $total = (int) $lista['total'];
        return [
            'rows' => $rows,
            'total' => $total,
            'pagina' => max(1, $pagina),
            'total_paginas' => max(1, (int) ceil($total / $porPagina)),
        ];
    }

    public function emitir(int $alunoId, int $usuarioId, bool $substituirValida = true): int
    {
        $this->exigirUso();
        $aluno = $this->model->buscarAluno($alunoId);
        if (!$aluno || (int) ($aluno['ativo'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('Aluno não encontrado ou inativo.');
        }
        $vigente = $this->model->vigenteDoAluno($alunoId);
        if ($vigente && !$substituirValida && $this->situacao($vigente) === 'valida') {
            return (int) $vigente['id'];
        }
        return $this->gravarEmissao($aluno, $usuarioId, 'Substituída por nova emissão');
    }

    /**
     * @return array{emitidas:int,puladas:int}
     */
    public function emitirTurma(int $turmaId, int $usuarioId): array
    {
        $this->exigirUso();
        if (!$this->model->turmaExiste($turmaId)) {
            throw new \InvalidArgumentException('Turma não encontrada.');
        }
        $emitidas = 0;
        $puladas = 0;
        foreach ($this->model->idsAtivosDaTurma($turmaId) as $alunoId) {
            $antes = $this->model->vigenteDoAluno($alunoId);
            $id = $this->emitir($alunoId, $usuarioId, false);
            if ($antes && (int) ($antes['id'] ?? 0) === $id) {
                $puladas++;
                continue;
            }
            $emitidas++;
        }
        return ['emitidas' => $emitidas, 'puladas' => $puladas];
    }

    public function cancelar(int $id): void
    {
        $this->exigirUso();
        if ($id <= 0 || !$this->model->cancelarPorId($id, 'Cancelada pela secretaria')) {
            throw new \InvalidArgumentException('Carteirinha não encontrada.');
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function cartaoDoAluno(int $alunoId): ?array
    {
        $row = $this->model->vigenteDoAluno($alunoId);
        if (!$row) {
            return null;
        }
        return $this->apresentar($row);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function paraImpressao(int $alunoId, int $turmaId): array
    {
        $this->exigirUso();
        $rows = [];
        if ($alunoId > 0) {
            $row = $this->model->vigenteDoAluno($alunoId);
            if ($row) {
                $rows[] = $row;
            }
        } elseif ($turmaId > 0) {
            $rows = $this->model->emitidasDaTurma($turmaId);
        }
        $cartoes = [];
        foreach ($rows as $row) {
            $cartoes[] = $this->apresentar($row);
        }
        return $cartoes;
    }

    /**
     * @return array<string,mixed>
     */
    public function validarPublico(string $token): array
    {
        $escola = $this->nomeEscola();
        $vazio = [
            'uso' => false,
            'encontrado' => false,
            'valido' => false,
            'motivo' => 'desligada',
            'escola' => $escola,
            'logo' => $this->urlLogoDocumento(),
            'aluno_nome' => '',
            'turma' => '',
            'ra' => '',
            'foto' => '',
            'validade' => '',
            'codigo' => '',
            'hash' => '',
            'emitida_em' => '',
            'versao' => 0,
        ];
        if (!\App\Core\LayoutHelper::isModuleEnabled('carteirinha') || !$this->model->tabelasProntas()) {
            $vazio['motivo'] = 'indisponivel';
            return $vazio;
        }
        if (!$this->model->habilitada()) {
            return $vazio;
        }
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $vazio['uso'] = true;
            $vazio['motivo'] = 'inexistente';
            return $vazio;
        }
        $doc = $this->model->findByToken($token);
        if (!$doc) {
            $vazio['uso'] = true;
            $vazio['motivo'] = 'inexistente';
            return $vazio;
        }
        $cartao = $this->apresentar($doc);
        $valido = ($cartao['situacao'] ?? '') === 'valida';
        return [
            'uso' => true,
            'encontrado' => true,
            'valido' => $valido,
            'motivo' => (string) ($cartao['situacao'] ?? 'cancelada'),
            'escola' => $escola,
            'logo' => $this->urlLogoDocumento(),
            'aluno_nome' => (string) ($cartao['nome'] ?? ''),
            'turma' => (string) ($cartao['turma'] ?? ''),
            'ra' => (string) ($cartao['ra'] ?? ''),
            'foto' => (string) ($cartao['foto'] ?? ''),
            'validade' => (string) ($cartao['validade'] ?? ''),
            'codigo' => (string) ($cartao['codigo'] ?? ''),
            'hash' => $token,
            'emitida_em' => (string) ($cartao['emitida_em'] ?? ''),
            'versao' => (int) ($cartao['versao'] ?? 1),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function apresentar(array $row): array
    {
        $situacao = $this->situacao($row);
        $token = (string) ($row['token'] ?? '');
        $url = $this->urlValidacao($token);
        return [
            'id' => (int) ($row['id'] ?? $row['carteirinha_id'] ?? 0),
            'aluno_id' => (int) ($row['aluno_id'] ?? 0),
            'nome' => (string) ($row['nome_exibicao'] ?? ''),
            'ra' => (string) ($row['ra'] ?? ''),
            'turma' => (string) ($row['turma_nome'] ?? ''),
            'foto' => $this->urlFoto((string) ($row['foto_url'] ?? '')),
            'escola' => $this->nomeEscola(),
            'logo' => $this->urlLogo(),
            'codigo' => $this->codigoCurto($token),
            'validade' => $this->formatarData((string) ($row['valida_ate'] ?? '')),
            'valida_ate' => (string) ($row['valida_ate'] ?? ''),
            'emitida_em' => $this->formatarData((string) ($row['emitida_em'] ?? '')),
            'versao' => (int) ($row['versao'] ?? 1),
            'situacao' => $situacao,
            'qr' => $url !== '' ? $this->qrDataUri($url) : '',
            'url' => $url,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function linhaLista(array $row): array
    {
        $nomeSocial = trim((string) ($row['nome_social'] ?? ''));
        $nome = $nomeSocial !== '' ? $nomeSocial : (string) ($row['nome'] ?? '');
        $temCartao = (int) ($row['carteirinha_id'] ?? 0) > 0;
        $situacao = 'sem_emissao';
        if ($temCartao) {
            $situacao = $this->situacao([
                'status' => (string) ($row['status'] ?? 'emitida'),
                'valida_ate' => (string) ($row['valida_ate'] ?? ''),
                'aluno_ativo' => (int) ($row['ativo'] ?? 1),
            ]);
        }
        return [
            'id' => (int) $row['id'],
            'nome' => $nome,
            'ra' => $this->raDoAluno($row),
            'turma' => $this->turmaDoAluno($row),
            'foto' => $this->urlFoto((string) ($row['foto_url'] ?? '')),
            'carteirinha_id' => (int) ($row['carteirinha_id'] ?? 0),
            'situacao' => $situacao,
            'validade' => $temCartao ? $this->formatarData((string) ($row['valida_ate'] ?? '')) : '',
            'versao' => (int) ($row['versao'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     */
    private function gravarEmissao(array $aluno, int $usuarioId, string $motivoAnterior): int
    {
        $alunoId = (int) $aluno['id'];
        $ano = $this->model->anoLetivoAtivo();
        $validaAte = $this->validadeDoAno($ano);
        $token = bin2hex(random_bytes(16));
        $this->model->begin();
        try {
            $this->model->cancelarVigentes($alunoId, $motivoAnterior);
            $id = $this->model->inserir([
                'aluno_id' => $alunoId,
                'ano_letivo_id' => $ano['id'] ?? null,
                'token' => $token,
                'nome_exibicao' => $this->nomeExibicao($aluno),
                'ra' => $this->raDoAluno($aluno),
                'turma_nome' => $this->turmaDoAluno($aluno),
                'foto_url' => trim((string) ($aluno['foto_url'] ?? '')) ?: null,
                'valida_ate' => $validaAte,
                'emitida_por' => $usuarioId > 0 ? $usuarioId : null,
                'versao' => $this->model->proximaVersao($alunoId),
            ]);
            $this->model->commit();
            return $id;
        } catch (\Throwable $e) {
            try {
                $this->model->rollback();
            } catch (\Throwable $rollback) {
            }
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private function situacao(array $row): string
    {
        if ((string) ($row['status'] ?? '') !== 'emitida') {
            return 'cancelada';
        }
        if (isset($row['aluno_ativo']) && (int) $row['aluno_ativo'] !== 1) {
            return 'inativa';
        }
        $ate = (string) ($row['valida_ate'] ?? '');
        if ($ate !== '' && $ate < date('Y-m-d')) {
            return 'vencida';
        }
        return 'valida';
    }

    /**
     * @param array{id?:int,ano?:int,data_fim?:?string}|null $ano
     */
    private function validadeDoAno(?array $ano): string
    {
        $fim = trim((string) ($ano['data_fim'] ?? ''));
        if ($fim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fim)) {
            return $fim;
        }
        $civil = (int) ($ano['ano'] ?? 0);
        if ($civil < 2000) {
            $civil = (int) date('Y');
        }
        return $civil . '-12-31';
    }

    /**
     * @param array<string,mixed> $aluno
     */
    private function nomeExibicao(array $aluno): string
    {
        $social = trim((string) ($aluno['nome_social'] ?? ''));
        if ($social !== '') {
            return $social;
        }
        $nome = trim((string) ($aluno['nome'] ?? ''));
        return $nome !== '' ? $nome : 'Aluno';
    }

    /**
     * @param array<string,mixed> $aluno
     */
    private function raDoAluno(array $aluno): string
    {
        $ra = trim((string) ($aluno['ra'] ?? ''));
        if ($ra !== '') {
            return $ra;
        }
        return trim((string) ($aluno['codigo_aluno'] ?? ''));
    }

    /**
     * @param array<string,mixed> $aluno
     */
    private function turmaDoAluno(array $aluno): string
    {
        $nome = trim((string) ($aluno['turma_nome'] ?? ''));
        $serie = trim((string) ($aluno['turma_serie'] ?? ''));
        if ($nome !== '' && $serie !== '' && $serie !== $nome) {
            return $nome . ' · ' . $serie;
        }
        return $nome !== '' ? $nome : $serie;
    }

    private function urlValidacao(string $token): string
    {
        if ($token === '') {
            return '';
        }
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        return $base . '/validar/carteirinha/' . rawurlencode($token);
    }

    private function codigoCurto(string $token): string
    {
        $token = strtoupper(substr($token, 0, 8));
        if (strlen($token) < 8) {
            return $token;
        }
        return substr($token, 0, 4) . '-' . substr($token, 4, 4);
    }

    private function formatarData(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        $ts = strtotime($valor);
        return $ts ? date('d/m/Y', $ts) : '';
    }

    private function nomeEscola(): string
    {
        $nome = '';
        if (class_exists(\App\Core\LayoutHelper::class)) {
            $nome = trim((string) \App\Core\LayoutHelper::getSystemTitle());
        }
        return $nome !== '' ? $nome : 'EducaTudo';
    }

    private function urlLogo(): string
    {
        if (!class_exists(\App\Core\LayoutHelper::class)) {
            return '';
        }
        return trim((string) \App\Core\LayoutHelper::getLogoUrl());
    }

    private function urlLogoDocumento(): string
    {
        if (!class_exists(\App\Core\LayoutHelper::class)) {
            return '';
        }
        $url = trim((string) \App\Core\LayoutHelper::getDocumentLogoUrl());
        return $url !== '' ? $url : $this->urlLogo();
    }

    private function urlFoto(string $caminho): string
    {
        $caminho = trim($caminho);
        if ($caminho === '') {
            return '';
        }
        try {
            return (string) (AvatarUrlHelper::normalizeAdminAvatarUrl($caminho) ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function qrDataUri(string $url): string
    {
        try {
            $opcoes = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel' => QRCode::ECC_M,
                'scale' => 4,
                'outputBase64' => true,
            ]);
            $svg = (new QRCode($opcoes))->render($url);
            if (!is_string($svg) || !str_starts_with($svg, 'data:image/')) {
                return '';
            }
            return $svg;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function exigirUso(): void
    {
        if (!$this->model->tabelasProntas()) {
            throw new \RuntimeException('Rode a migration 2026_11_03_carteirinha.sql no Master.');
        }
        if (!$this->model->habilitada()) {
            throw new \InvalidArgumentException('A carteirinha está desligada nesta escola.');
        }
    }
}
