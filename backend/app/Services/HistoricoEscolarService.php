<?php

namespace App\Services;

use Database;
use HistoricoDocumento;

require_once __DIR__ . '/../Models/Education/HistoricoDocumento.php';
require_once __DIR__ . '/../Models/Education/SchoolUnit.php';
require_once __DIR__ . '/DeclarationService.php';

/**
 * Histórico Escolar oficial (Fundamental/Médio): consolidação, workflow,
 * emissão imutável, assinatura eletrônica simples e validação pública.
 */
class HistoricoEscolarService
{
    /** @var Database */
    private $db;

    /** @var HistoricoDocumento */
    private $model;

    /** @var DeclarationService */
    private $declarations;

    public const RESULTADO_LABELS = [
        'Aprovado' => 'Aprovado',
        'Aprovado_Conselho' => 'Aprovado pelo Conselho de Classe',
        'Retido' => 'Retido',
        'Transferido' => 'Transferido',
        'Evadido' => 'Evadido',
        'Cursando' => 'Cursando',
    ];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->model = new HistoricoDocumento();
        $this->declarations = new DeclarationService($this->db);
    }

    public function schemaPronto(): bool
    {
        return $this->model->tableExists();
    }

    /**
     * Checklist pré-emissão (campos mínimos para documento jurídico).
     *
     * @return array{ok: bool, itens: list<array{chave: string, ok: bool, mensagem: string}>}
     */
    public function checklist(int $alunoId): array
    {
        $aluno = $this->declarations->getAluno($alunoId);
        $unidade = $aluno ? $this->declarations->getUnidadeForAluno($aluno) : null;
        $itens = [];

        $itens[] = $this->checkItem(
            'aluno',
            $aluno !== null,
            'Aluno encontrado no cadastro'
        );
        $itens[] = $this->checkItem(
            'nome',
            $aluno && trim((string) ($aluno['nome'] ?? '')) !== '',
            'Nome completo do aluno'
        );
        $itens[] = $this->checkItem(
            'nascimento',
            $aluno && $this->dataValida($aluno['data_nasc'] ?? null),
            'Data de nascimento'
        );
        $itens[] = $this->checkItem(
            'filiacao',
            $aluno && (
                trim((string) ($aluno['nome_mae'] ?? '')) !== ''
                || trim((string) ($aluno['nome_pai'] ?? '')) !== ''
            ),
            'Filiação (mãe e/ou pai)'
        );
        $itens[] = $this->checkItem(
            'documento',
            $aluno && (
                trim((string) ($aluno['cpf'] ?? '')) !== ''
                || trim((string) ($aluno['rg'] ?? '')) !== ''
                || trim((string) ($aluno['certidao_nascimento'] ?? '')) !== ''
            ),
            'Documento do aluno (CPF, RG ou certidão)'
        );
        $itens[] = $this->checkItem(
            'nacionalidade',
            $aluno && trim((string) ($aluno['nacionalidade'] ?? '')) !== '',
            'Nacionalidade'
        );
        $itens[] = $this->checkItem(
            'unidade',
            $unidade !== null,
            'Unidade escolar vinculada'
        );
        $itens[] = $this->checkItem(
            'inep',
            $unidade && trim((string) ($unidade['inep'] ?? '')) !== '',
            'Código INEP da unidade'
        );
        $itens[] = $this->checkItem(
            'cnpj',
            $unidade && trim((string) ($unidade['cnpj'] ?? '')) !== '',
            'CNPJ da unidade'
        );
        $itens[] = $this->checkItem(
            'diretor',
            $unidade && trim((string) ($unidade['diretor_nome'] ?? '')) !== '',
            'Nome do(a) diretor(a)'
        );
        $itens[] = $this->checkItem(
            'secretario',
            $unidade && trim((string) ($unidade['secretario_nome'] ?? '')) !== '',
            'Nome do(a) secretário(a)'
        );

        $ok = true;
        foreach ($itens as $i) {
            if (!$i['ok']) {
                $ok = false;
                break;
            }
        }

        return ['ok' => $ok, 'itens' => $itens];
    }

    /**
     * @return array{success: bool, id?: int, error?: string}
     */
    public function gerarRascunho(int $alunoId, string $finalidade, ?int $userId, ?string $observacoes = null): array
    {
        if (!$this->schemaPronto()) {
            return ['success' => false, 'error' => 'Migration do histórico escolar ainda não foi aplicada.'];
        }
        $aluno = $this->declarations->getAluno($alunoId);
        if (!$aluno) {
            return ['success' => false, 'error' => 'Aluno não encontrado.'];
        }
        if (!in_array($finalidade, HistoricoDocumento::FINALIDADES, true)) {
            $finalidade = 'Solicitacao';
        }

        $editavel = $this->buscarRascunhoEditavel($alunoId);
        if ($editavel) {
            $id = (int) $editavel['id'];
            $campos = [
                'finalidade' => $finalidade,
                'observacoes_gerais' => $observacoes,
            ];
            // Qualquer reconsolidação após conferência exige nova conferência.
            if (($editavel['status'] ?? '') === 'Conferido') {
                $campos['status'] = 'Rascunho';
                $campos['conferido_em'] = null;
                $campos['conferido_por'] = null;
            }
            $this->model->updateCampos($id, $campos);
            $this->reconsolidarInternos($id, $alunoId);
            return ['success' => true, 'id' => $id];
        }

        $unidade = $this->declarations->getUnidadeForAluno($aluno);
        $id = $this->model->create([
            'aluno_id' => $alunoId,
            'unidade_id' => $unidade ? (int) ($unidade['id'] ?? 0) : null,
            'versao' => $this->model->proximaVersao($alunoId),
            'status' => 'Rascunho',
            'finalidade' => $finalidade,
            'observacoes_gerais' => $observacoes,
        ]);
        $this->reconsolidarInternos($id, $alunoId);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Nova versão a partir de um documento já emitido/assinado (cancela o anterior ao emitir).
     *
     * @return array{success: bool, id?: int, error?: string}
     */
    public function novaVersao(int $historicoId, ?int $userId): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Histórico não encontrado.'];
        }
        if (!in_array($doc['status'], ['Emitido', 'Assinado', 'Entregue', 'Cancelado'], true)) {
            return ['success' => false, 'error' => 'Só é possível criar nova versão a partir de documento já emitido.'];
        }

        $alunoId = (int) $doc['aluno_id'];
        $id = $this->model->create([
            'aluno_id' => $alunoId,
            'unidade_id' => $doc['unidade_id'] ?? null,
            'versao' => $this->model->proximaVersao($alunoId),
            'status' => 'Rascunho',
            'finalidade' => $doc['finalidade'] ?? 'Solicitacao',
            'observacoes_gerais' => $doc['observacoes_gerais'] ?? null,
            'substitui_id' => $historicoId,
        ]);

        // Copia itens externos da versão anterior + reconsolida internos
        foreach ($this->model->listarItens($historicoId) as $item) {
            if (($item['origem'] ?? '') !== 'Externo') {
                continue;
            }
            $this->model->inserirItem([
                'historico_id' => $id,
                'ano_letivo' => $item['ano_letivo'],
                'serie_ano' => $item['serie_ano'],
                'componente' => $item['componente'],
                'resultado_valor' => $item['resultado_valor'],
                'parecer_descritivo' => $item['parecer_descritivo'],
                'carga_horaria' => $item['carga_horaria'],
                'frequencia_percentual' => $item['frequencia_percentual'],
                'origem' => 'Externo',
                'escola_origem' => $item['escola_origem'],
                'ordem' => $item['ordem'],
            ]);
        }
        $this->reconsolidarInternos($id, $alunoId);

        return ['success' => true, 'id' => $id];
    }

    public function conferir(int $historicoId, int $userId): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Histórico não encontrado.'];
        }
        if ($doc['status'] !== 'Rascunho') {
            return ['success' => false, 'error' => 'Somente rascunhos podem ser conferidos.'];
        }
        $itens = $this->model->listarItens($historicoId);
        if ($itens === []) {
            return ['success' => false, 'error' => 'Inclua ao menos um componente (interno ou externo) antes de conferir.'];
        }
        $this->model->updateCampos($historicoId, [
            'status' => 'Conferido',
            'conferido_em' => date('Y-m-d H:i:s'),
            'conferido_por' => $userId,
        ]);
        return ['success' => true];
    }

    public function voltarRascunho(int $historicoId): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Histórico não encontrado.'];
        }
        if ($doc['status'] !== 'Conferido') {
            return ['success' => false, 'error' => 'Só é possível reabrir documentos conferidos.'];
        }
        $this->model->updateCampos($historicoId, [
            'status' => 'Rascunho',
            'conferido_em' => null,
            'conferido_por' => null,
        ]);
        return ['success' => true];
    }

    /**
     * Congela o documento, gera hash e snapshot. PDF é gerado on-demand a partir do snapshot.
     */
    public function emitir(int $historicoId, int $userId): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Histórico não encontrado.'];
        }
        if ($doc['status'] !== 'Conferido') {
            return ['success' => false, 'error' => 'Confera o histórico antes de emitir.'];
        }

        $checklist = $this->checklist((int) $doc['aluno_id']);
        if (!$checklist['ok']) {
            return [
                'success' => false,
                'error' => 'Checklist incompleto. Complete os dados do aluno e da unidade antes de emitir.',
                'checklist' => $checklist,
            ];
        }

        $payload = $this->montarPayload($historicoId);
        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($canonical === false) {
            return ['success' => false, 'error' => 'Falha ao serializar o histórico para emissão.'];
        }
        $hash = hash('sha256', $canonical . '|historico:' . $historicoId);

        $pdo = method_exists($this->db, 'getPdo') ? $this->db->getPdo() : null;
        $usouTx = false;
        try {
            if (method_exists($this->db, 'beginTransaction') && $pdo instanceof \PDO && !$pdo->inTransaction()) {
                $this->db->beginTransaction();
                $usouTx = true;
            }

            $this->model->updateCampos($historicoId, [
                'status' => 'Emitido',
                'hash_validacao' => $hash,
                'snapshot_json' => $canonical,
                'emitido_em' => date('Y-m-d H:i:s'),
                'emitido_por' => $userId,
            ]);

            $substitui = (int) ($doc['substitui_id'] ?? 0);
            if ($substitui > 0) {
                $antigo = $this->model->findById($substitui);
                if ($antigo && $antigo['status'] !== 'Cancelado') {
                    $this->model->updateCampos($substitui, ['status' => 'Cancelado']);
                }
            }

            if ($usouTx && method_exists($this->db, 'commit')) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($usouTx && method_exists($this->db, 'rollback')) {
                $this->db->rollback();
            }
            return ['success' => false, 'error' => 'Falha ao emitir o histórico: ' . $e->getMessage()];
        }

        return ['success' => true, 'hash' => $hash];
    }

    public function assinar(int $historicoId, int $userId, string $userNome, string $cargo, ?string $ip): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Histórico não encontrado.'];
        }
        if (!in_array($doc['status'], ['Emitido', 'Assinado'], true)) {
            return ['success' => false, 'error' => 'Assinatura só é permitida após a emissão.'];
        }
        if (!in_array($cargo, ['Diretor', 'Secretario_Escolar'], true)) {
            return ['success' => false, 'error' => 'Cargo de assinatura inválido.'];
        }

        $unidade = null;
        if (!empty($doc['unidade_id'])) {
            $unidade = (new \SchoolUnit())->findById((int) $doc['unidade_id']) ?: null;
        }
        $registro = null;
        if ($cargo === 'Diretor') {
            $registro = $unidade['diretor_registro'] ?? null;
        } elseif ($cargo === 'Secretario_Escolar') {
            $registro = $unidade['secretario_registro'] ?? null;
        }

        $this->model->registrarAssinatura([
            'historico_id' => $historicoId,
            'usuario_id' => $userId,
            'usuario_nome' => $userNome,
            'cargo' => $cargo,
            'numero_registro' => $registro,
            'tipo' => 'Eletronica_Simples',
            'ip_origem' => $ip,
        ]);

        $assinaturas = $this->model->listarAssinaturas($historicoId);
        $temDiretor = false;
        $temSec = false;
        foreach ($assinaturas as $a) {
            if ($a['cargo'] === 'Diretor') {
                $temDiretor = true;
            }
            if ($a['cargo'] === 'Secretario_Escolar') {
                $temSec = true;
            }
        }
        if ($temDiretor && $temSec && $doc['status'] === 'Emitido') {
            $this->model->updateCampos($historicoId, ['status' => 'Assinado']);
        }

        return ['success' => true, 'status' => $temDiretor && $temSec ? 'Assinado' : $doc['status']];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function adicionarItemExterno(int $historicoId, array $input): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return ['success' => false, 'error' => 'Histórico não encontrado.'];
        }
        if (!in_array($doc['status'], ['Rascunho', 'Conferido'], true)) {
            return ['success' => false, 'error' => 'Documento emitido é imutável. Crie uma nova versão.'];
        }
        if ($doc['status'] === 'Conferido') {
            $this->model->updateCampos($historicoId, ['status' => 'Rascunho']);
        }

        $componente = trim((string) ($input['componente'] ?? ''));
        $ano = trim((string) ($input['ano_letivo'] ?? ''));
        $serie = trim((string) ($input['serie_ano'] ?? ''));
        $escola = trim((string) ($input['escola_origem'] ?? ''));
        if ($componente === '' || $ano === '' || $serie === '' || $escola === '') {
            return ['success' => false, 'error' => 'Informe ano, série, componente e escola de origem.'];
        }

        $freq = $input['frequencia_percentual'] ?? null;
        if ($freq !== null && $freq !== '') {
            $freq = round((float) $freq, 2);
        } else {
            $freq = null;
        }

        $this->model->inserirItem([
            'historico_id' => $historicoId,
            'ano_letivo' => $ano,
            'serie_ano' => $serie,
            'componente' => $componente,
            'resultado_valor' => trim((string) ($input['resultado_valor'] ?? '')) ?: null,
            'carga_horaria' => ($input['carga_horaria'] ?? '') !== '' ? (int) $input['carga_horaria'] : null,
            'frequencia_percentual' => $freq,
            'origem' => 'Externo',
            'escola_origem' => $escola,
            'ordem' => 1000,
        ]);

        $resultadoAno = trim((string) ($input['resultado_anual'] ?? ''));
        if ($resultadoAno !== '' && in_array($resultadoAno, HistoricoDocumento::RESULTADOS, true)) {
            $this->model->upsertResultado([
                'historico_id' => $historicoId,
                'ano_letivo' => $ano,
                'serie_ano' => $serie,
                'resultado' => $resultadoAno,
                'observacao' => 'Estudos realizados em outra instituição: ' . $escola,
            ]);
        }

        return ['success' => true];
    }

    public function excluirItemExterno(int $historicoId, int $itemId): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc || !in_array($doc['status'], ['Rascunho', 'Conferido'], true)) {
            return ['success' => false, 'error' => 'Não é possível excluir itens neste status.'];
        }
        if ($doc['status'] === 'Conferido') {
            $this->model->updateCampos($historicoId, ['status' => 'Rascunho']);
        }
        $this->model->excluirItem($itemId, $historicoId);
        return ['success' => true];
    }

    /**
     * @param array{observacoes_gerais?: ?string, numero_registro_sed?: ?string}|null $extras
     */
    public function atualizarObservacoes(int $historicoId, ?string $obs, ?array $extras = null): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc || !in_array($doc['status'], ['Rascunho', 'Conferido'], true)) {
            return ['success' => false, 'error' => 'Documento imutável.'];
        }
        $campos = ['observacoes_gerais' => $obs];
        if (is_array($extras) && array_key_exists('numero_registro_sed', $extras)) {
            if (!$this->model->colunaExiste('numero_registro_sed')) {
                // Migration ainda não aplicada — não quebrar o salvar de observações.
            } else {
                $sed = mb_substr(trim((string) ($extras['numero_registro_sed'] ?? '')), 0, 80);
                $campos['numero_registro_sed'] = $sed !== '' ? $sed : null;
            }
        }
        if ($doc['status'] === 'Conferido') {
            $campos['status'] = 'Rascunho';
            $campos['conferido_em'] = null;
            $campos['conferido_por'] = null;
        }
        $this->model->updateCampos($historicoId, $campos);
        return ['success' => true];
    }

    public function atualizarResultadoAnual(int $historicoId, string $ano, string $serie, string $resultado, ?string $obs): array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc || !in_array($doc['status'], ['Rascunho', 'Conferido'], true)) {
            return ['success' => false, 'error' => 'Documento imutável.'];
        }
        if (!in_array($resultado, HistoricoDocumento::RESULTADOS, true)) {
            return ['success' => false, 'error' => 'Resultado inválido.'];
        }
        if ($doc['status'] === 'Conferido') {
            $this->model->updateCampos($historicoId, ['status' => 'Rascunho']);
        }
        $this->model->upsertResultado([
            'historico_id' => $historicoId,
            'ano_letivo' => $ano,
            'serie_ano' => $serie,
            'resultado' => $resultado,
            'observacao' => $obs,
        ]);
        return ['success' => true];
    }

    /**
     * Monta dados para o PDF (usa snapshot se emitido).
     *
     * @return array<string, mixed>|null
     */
    public function dadosParaPdf(int $historicoId): ?array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return null;
        }
        if (!empty($doc['snapshot_json']) && in_array($doc['status'], ['Emitido', 'Assinado', 'Entregue', 'Cancelado'], true)) {
            $snap = json_decode((string) $doc['snapshot_json'], true);
            if (is_array($snap)) {
                $snap['documento'] = $doc;
                $snap['assinaturas'] = $this->model->listarAssinaturas($historicoId);
                $snap['validation_url'] = $this->urlValidacao((string) ($doc['hash_validacao'] ?? ''));
                return $snap;
            }
        }
        $payload = $this->montarPayload($historicoId);
        $payload['documento'] = $doc;
        $payload['assinaturas'] = $this->model->listarAssinaturas($historicoId);
        $payload['validation_url'] = $this->urlValidacao((string) ($doc['hash_validacao'] ?? ''));
        return $payload;
    }

    public function urlValidacao(string $hash): string
    {
        if ($hash === '') {
            return '';
        }
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        return $base . '/validar/historico/' . rawurlencode($hash);
    }

    /**
     * Dados públicos para validação (sem notas).
     *
     * @return array{encontrado: bool, valido: bool, status?: string, aluno_nome?: string, escola?: string, emitido_em?: string, versao?: int, hash?: string}
     */
    public function validarPublico(string $hash): array
    {
        $doc = $this->model->findByHash($hash);
        if (!$doc) {
            return ['encontrado' => false, 'valido' => false];
        }
        $status = (string) $doc['status'];
        $valido = in_array($status, ['Emitido', 'Assinado', 'Entregue'], true);
        $escola = '';
        if (!empty($doc['snapshot_json'])) {
            $snap = json_decode((string) $doc['snapshot_json'], true);
            if (is_array($snap)) {
                $escola = (string) ($snap['unidade']['razao_social'] ?? $snap['unidade']['nome'] ?? '');
            }
        }
        if ($escola === '' && !empty($doc['unidade_id'])) {
            $u = (new \SchoolUnit())->findById((int) $doc['unidade_id']);
            $escola = $u ? (string) ($u['razao_social'] ?? $u['nome'] ?? '') : '';
        }

        return [
            'encontrado' => true,
            'valido' => $valido,
            'status' => $status,
            'aluno_nome' => (string) ($doc['aluno_nome'] ?? ''),
            'escola' => $escola,
            'emitido_em' => $doc['emitido_em'] ?? null,
            'versao' => (int) ($doc['versao'] ?? 1),
            'hash' => (string) ($doc['hash_validacao'] ?? $hash),
            'substituido' => $status === 'Cancelado',
        ];
    }

    public function listarPorAluno(int $alunoId): array
    {
        return $this->model->listarPorAluno($alunoId);
    }

    public function findById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function detalhe(int $historicoId): ?array
    {
        $doc = $this->model->findById($historicoId);
        if (!$doc) {
            return null;
        }
        return [
            'documento' => $doc,
            'itens' => $this->model->listarItens($historicoId),
            'resultados' => $this->model->listarResultados($historicoId),
            'assinaturas' => $this->model->listarAssinaturas($historicoId),
            'validation_url' => $this->urlValidacao((string) ($doc['hash_validacao'] ?? '')),
        ];
    }

    private function buscarRascunhoEditavel(int $alunoId): ?array
    {
        foreach ($this->model->listarPorAluno($alunoId) as $doc) {
            if (in_array($doc['status'], ['Rascunho', 'Conferido'], true)) {
                return $doc;
            }
        }
        return null;
    }

    private function reconsolidarInternos(int $historicoId, int $alunoId): void
    {
        $this->model->limparItensInternos($historicoId);
        // Resultadoes anuais derivados de itens internos são recriados; externos preservados via upsert
        $consolidados = $this->consolidarDoBoletim($alunoId);
        $ordem = 0;
        $anosSerie = [];
        foreach ($consolidados['itens'] as $item) {
            $ordem++;
            $item['historico_id'] = $historicoId;
            $item['origem'] = 'Interno';
            $item['ordem'] = $ordem;
            $this->model->inserirItem($item);
            $key = $item['ano_letivo'] . '|' . $item['serie_ano'];
            $anosSerie[$key] = [
                'ano_letivo' => $item['ano_letivo'],
                'serie_ano' => $item['serie_ano'],
                'resultado' => $item['_resultado_ano'] ?? 'Cursando',
            ];
        }
        foreach ($anosSerie as $ar) {
            $this->model->upsertResultado([
                'historico_id' => $historicoId,
                'ano_letivo' => $ar['ano_letivo'],
                'serie_ano' => $ar['serie_ano'],
                'resultado' => $ar['resultado'],
                'observacao' => null,
            ]);
        }
    }

    /**
     * Consolida notas finais por matéria a partir dos boletins gerados.
     *
     * @return array{itens: list<array<string, mixed>>}
     */
    private function consolidarDoBoletim(int $alunoId): array
    {
        $eventos = $this->declarations->getHistorico($alunoId);
        $itens = [];
        $seen = [];

        foreach ($eventos as $ev) {
            $ano = (string) ((int) ($ev['ano_letivo_calc'] ?? $ev['ano_letivo'] ?? 0) ?: date('Y'));
            $serie = trim((string) ($ev['turma_serie'] ?? $ev['serie'] ?? ''));
            if ($serie === '') {
                $serie = 'Série não informada';
            }
            $cols = is_array($ev['colunas'] ?? null) ? $ev['colunas'] : [];
            $linhas = is_array($ev['linhas'] ?? null) ? $ev['linhas'] : [];
            $finalCodes = $this->codigosColunaFinal($cols);
            $notaMin = (float) ($ev['nota_minima_aprovacao'] ?? 6);

            foreach ($linhas as $lin) {
                $materia = trim((string) ($lin['materia_nome'] ?? ''));
                if ($materia === '') {
                    continue;
                }
                $materiaKey = function_exists('mb_strtolower')
                    ? mb_strtolower($materia, 'UTF-8')
                    : strtolower($materia);
                $key = $ano . '|' . $materiaKey;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $notas = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
                $valor = null;
                foreach ($finalCodes as $code) {
                    if (isset($notas[$code]) && $notas[$code] !== '' && $notas[$code] !== null) {
                        $valor = $notas[$code];
                        break;
                    }
                }
                if ($valor === null && isset($lin['media_final']) && $lin['media_final'] !== '' && $lin['media_final'] !== null) {
                    $valor = $lin['media_final'];
                }

                $resultadoAno = 'Cursando';
                if (is_numeric($valor)) {
                    $resultadoAno = ((float) $valor >= $notaMin) ? 'Aprovado' : 'Retido';
                }

                $ch = null;
                if (!empty($lin['materia_id'])) {
                    $ch = $this->cargaHorariaMateria((int) $lin['materia_id']);
                }

                $itens[] = [
                    'ano_letivo' => $ano,
                    'serie_ano' => $serie,
                    'componente' => $materia,
                    'materia_id' => isset($lin['materia_id']) ? (int) $lin['materia_id'] : null,
                    'resultado_valor' => $valor !== null ? (string) $valor : null,
                    'carga_horaria' => $ch,
                    'frequencia_percentual' => null,
                    '_resultado_ano' => $resultadoAno,
                ];
            }
        }

        return ['itens' => $itens];
    }

    /**
     * @param list<array<string, mixed>> $cols
     * @return list<string>
     */
    private function codigosColunaFinal(array $cols): array
    {
        $preferidos = [];
        $medias = [];
        foreach ($cols as $c) {
            $code = (string) ($c['codigo'] ?? '');
            if ($code === '') {
                continue;
            }
            $group = strtolower((string) ($c['layout_group'] ?? ''));
            $type = strtolower((string) ($c['layout_type'] ?? ''));
            $nome = function_exists('mb_strtolower')
                ? mb_strtolower((string) ($c['nome'] ?? ''), 'UTF-8')
                : strtolower((string) ($c['nome'] ?? ''));
            if ($group === 'final' && in_array($type, ['media', 'resultado', ''], true)) {
                $preferidos[] = $code;
            } elseif (strpos($nome, 'final') !== false || strpos($nome, 'média final') !== false || strpos($nome, 'media final') !== false) {
                $preferidos[] = $code;
            } elseif ($type === 'media' || strpos($nome, 'média') !== false || strpos($nome, 'media') !== false) {
                $medias[] = $code;
            }
        }
        return array_values(array_unique(array_merge($preferidos, $medias)));
    }

    private function cargaHorariaMateria(int $materiaId): ?int
    {
        try {
            if ($this->db->fetch("SHOW TABLES LIKE 'plano_curso'") === false) {
                return null;
            }
            $row = $this->db->fetch(
                "SELECT carga_horaria_prevista
                 FROM plano_curso
                 WHERE materia_id = :m
                 ORDER BY id DESC
                 LIMIT 1",
                ['m' => $materiaId]
            );
            if ($row && isset($row['carga_horaria_prevista'])) {
                return (int) $row['carga_horaria_prevista'];
            }
        } catch (\Throwable $e) {
            // opcional
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function montarPayload(int $historicoId): array
    {
        $doc = $this->model->findById($historicoId);
        $alunoId = (int) ($doc['aluno_id'] ?? 0);
        $aluno = $this->declarations->getAluno($alunoId) ?: [];
        $unidade = $this->declarations->getUnidadeForAluno($aluno) ?: [];
        return [
            'aluno' => $aluno,
            'unidade' => $unidade,
            'itens' => $this->model->listarItens($historicoId),
            'resultados' => $this->model->listarResultados($historicoId),
            'observacoes_gerais' => $doc['observacoes_gerais'] ?? null,
            'numero_registro_sed' => $doc['numero_registro_sed'] ?? null,
            'finalidade' => $doc['finalidade'] ?? 'Solicitacao',
            'versao' => (int) ($doc['versao'] ?? 1),
        ];
    }

    /**
     * @return array{chave: string, ok: bool, mensagem: string}
     */
    private function checkItem(string $chave, bool $ok, string $mensagem): array
    {
        return ['chave' => $chave, 'ok' => $ok, 'mensagem' => $mensagem];
    }

    private function dataValida($d): bool
    {
        $d = trim((string) $d);
        if ($d === '' || $d === '0000-00-00') {
            return false;
        }
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $d);
    }
}
