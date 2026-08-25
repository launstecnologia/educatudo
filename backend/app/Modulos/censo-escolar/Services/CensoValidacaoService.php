<?php

namespace App\Modulos\CensoEscolar\Services;

require_once __DIR__ . '/../Models/CensoEdicao.php';
require_once __DIR__ . '/CensoLayoutService.php';
require_once __DIR__ . '/CensoEtapaDePara.php';
require_once __DIR__ . '/CensoNormalizador.php';

use App\Modulos\CensoEscolar\Models\CensoEdicao;

/**
 * Motor de validação estrutural. Regras oficiais de campo/posição vêm do leiaute da edição,
 * nunca de exemplos. Alertas abertos bloqueiam o TXT até conferência ou justificativa.
 */
class CensoValidacaoService
{
    private CensoEdicao $model;
    private CensoLayoutService $layouts;

    public function __construct(?CensoEdicao $model = null, ?CensoLayoutService $layouts = null)
    {
        $this->model = $model ?? new CensoEdicao();
        $this->layouts = $layouts ?? new CensoLayoutService();
    }

    /**
     * @return array{success:bool, error?:string, resumo?:array, itens?:list<array>}
     */
    public function validar(array $edicao): array
    {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Execute a migration do Censo Escolar.'];
        }
        $edicaoId = (int) $edicao['id'];
        $layout = $this->layouts->carregar((int) $edicao['ano'], (string) $edicao['etapa_coleta']);
        $itens = [];

        if (empty($layout['pronto_para_txt'])) {
            $itens[] = $this->item('edicao', $edicaoId, 'layout_nao_oficial', 'erro', 'versao_layout',
                'O leiaute oficial desta edição ainda não foi importado.',
                'Obtenha o leiaute e as tabelas auxiliares no portal do INEP e registre-os na configuração da edição.');
        }

        $oficial = !empty($layout['oficial']);
        $escolas = $this->model->listarEntidade('escola', $edicaoId);
        foreach ($escolas as $escola) {
            $unidade = $this->model->unidadePorId((int) ($escola['unidade_id'] ?? $edicao['unidade_id'] ?? 0)) ?? [];
            $dadosEscola = $this->json($escola['dados_json'] ?? null);
            $inep = trim((string) (
                ($unidade['inep'] ?? '')
                ?: ($escola['codigo_inep'] ?? '')
                ?: ($dadosEscola['inep'] ?? '')
                ?: ($dadosEscola['codigo_inep'] ?? '')
            ));
            $inepDigits = CensoNormalizador::digits($inep, 8);
            if (strlen($inepDigits) !== 8) {
                $itens[] = $this->item('escola', (int) $escola['id'], 'escola_sem_inep', 'erro', 'inep',
                    'A unidade escolar precisa do código INEP com 8 dígitos.',
                    'Informe o código INEP no cadastro da unidade ou no complemento Escola.');
            }
            $cep = CensoNormalizador::digits((string) (($dadosEscola['cep'] ?? '') ?: ($unidade['cep'] ?? '')), 8);
            if (strlen($cep) !== 8) {
                $itens[] = $this->item('escola', (int) $escola['id'], 'escola_sem_cep', 'erro', 'cep',
                    'A escola precisa do CEP com 8 dígitos.',
                    'Preencha o CEP no cadastro da unidade.');
            }
            $mun = CensoNormalizador::digits((string) ($dadosEscola['municipio'] ?? $unidade['cidade'] ?? ''), 7);
            if (strlen($mun) !== 7) {
                $itens[] = $this->item('escola', (int) $escola['id'], 'escola_sem_municipio', 'erro', 'municipio',
                    'A escola precisa do código IBGE do município (7 dígitos).',
                    'Informe o município (IBGE) no complemento Escola. Se o CEP estiver preenchido, sincronize a edição para tentar preencher automaticamente.');
            }
            $endereco = trim((string) (($unidade['endereco'] ?? '') ?: ($dadosEscola['endereco'] ?? '')));
            if ($endereco === '') {
                $itens[] = $this->item('escola', (int) $escola['id'], 'escola_sem_endereco', 'erro', 'endereco',
                    'A escola está sem endereço.',
                    'Preencha o logradouro no cadastro da unidade.');
            }
            $okEscola = strlen($inepDigits) === 8 && strlen($cep) === 8 && strlen($mun) === 7 && $endereco !== '';
            $this->model->upsertComplemento(
                'censo_complementos_escola',
                ['id' => (int) $escola['id']],
                ['status_validacao' => $okEscola ? 'pronto' : 'com_erro']
            );
        }

        $gestores = $this->model->listarEntidade('gestores', $edicaoId);
        if ($gestores === []) {
            $itens[] = $this->item('gestor', 0, 'gestor_ausente', 'erro', 'nome',
                'A edição não possui gestor escolar.',
                'Informe o diretor no cadastro da unidade e sincronize novamente.');
        }
        foreach ($gestores as $g) {
            $ok = trim((string) ($g['nome'] ?? '')) !== '';
            $this->atualizarStatus('censo_complementos_gestor', (int) $g['id'], $ok ? 'pronto' : 'com_erro');
        }

        $turmas = $this->model->listarEntidade('turmas', $edicaoId);
        $usouDeParaInterno = false;
        foreach ($turmas as $t) {
            $etapa = trim((string) ($t['etapa_codigo'] ?? ''));
            $dadosTurma = $this->json($t['dados_json'] ?? null);
            $serie = trim((string) (($t['serie'] ?? '') ?: ($dadosTurma['serie_academica'] ?? '')));
            $sugestaoInterna = trim((string) ($dadosTurma['etapa_de_para_interno'] ?? ''));
            if ($sugestaoInterna !== '') {
                $usouDeParaInterno = true;
            }
            if ($oficial && $etapa !== '' && CensoEtapaDePara::ehInterno($etapa)) {
                $itens[] = $this->item('turma', (int) $t['id'], 'turma_etapa_interna', 'erro', 'etapa_codigo',
                    'Turma "' . ($t['nome'] ?? '') . '" usa etapa interna "' . $etapa . '", não código oficial do INEP.',
                    'Substitua pelo código da tabela auxiliar do leiaute desta edição.');
                $this->atualizarStatus('censo_complementos_turma', (int) $t['id'], 'com_erro');
                continue;
            }
            if ($etapa === '') {
                $severidade = $oficial ? 'erro' : 'alerta';
                $itens[] = $this->item('turma', (int) $t['id'], 'turma_sem_etapa', $severidade, 'etapa_codigo',
                    'Turma "' . ($t['nome'] ?? '') . '" sem etapa/modalidade censitária oficial.',
                    'Mapeie a série acadêmica para o código oficial quando o leiaute da edição estiver disponível.');
                $statusTurma = $oficial ? 'com_erro' : ($serie !== '' ? 'pronto' : 'com_alerta');
                $this->atualizarStatus('censo_complementos_turma', (int) $t['id'], $statusTurma);
            } else {
                $this->atualizarStatus('censo_complementos_turma', (int) $t['id'], 'pronto');
            }
        }
        if ($usouDeParaInterno && !$oficial) {
            $itens[] = $this->item(
                'edicao',
                $edicaoId,
                'turma_etapa_interna',
                'alerta',
                'etapa_codigo',
                'Há sugestão interna de etapa nas turmas. Não são códigos oficiais do INEP.',
                'Quando o leiaute oficial da edição for importado, preencha a etapa com o código da tabela auxiliar.'
            );
        }

        $alunos = $this->model->listarEntidade('alunos', $edicaoId);
        foreach ($alunos as $a) {
            $aluno = $this->model->alunoPorId((int) $a['aluno_id']);
            if (!$aluno) {
                continue;
            }
            $dadosAluno = $this->json($a['dados_json'] ?? null);
            $status = 'pronto';
            $nome = (string) ($aluno['nome'] ?? $a['nome'] ?? '');
            $mae = trim((string) (($aluno['nome_mae'] ?? '') ?: ($dadosAluno['nome_mae'] ?? '')));
            if ($mae === '') {
                $itens[] = $this->item('aluno', (int) $a['id'], 'aluno_sem_filiacao', 'erro', 'nome_mae',
                    $nome . ': sem filiação (nome da mãe).',
                    'Preencha o nome da mãe no cadastro do aluno.');
                $status = 'com_erro';
            }
            $nasc = trim((string) (($aluno['data_nasc'] ?? '') ?: ($dadosAluno['data_nasc'] ?? '')));
            if ($nasc === '' || $nasc === '0000-00-00') {
                $itens[] = $this->item('aluno', (int) $a['id'], 'aluno_sem_nascimento', 'erro', 'data_nasc',
                    $nome . ': sem data de nascimento.',
                    'Informe a data de nascimento no cadastro do aluno.');
                $status = 'com_erro';
            }
            $inepAluno = trim((string) (($aluno['codigo_inep'] ?? '') ?: ($dadosAluno['codigo_inep'] ?? '')));
            if ($inepAluno === '') {
                $itens[] = $this->item('aluno', (int) $a['id'], 'aluno_sem_inep', 'alerta', 'codigo_inep',
                    $nome . ': sem código INEP.',
                    'Informe o código ou use o fluxo de identificação de pessoas.');
                if ($oficial && $status === 'pronto') {
                    $status = 'com_alerta';
                }
            }
            $cpfAluno = trim((string) (($aluno['cpf'] ?? '') ?: ($dadosAluno['cpf'] ?? '')));
            if ($cpfAluno === '') {
                $itens[] = $this->item('aluno', (int) $a['id'], 'aluno_sem_cpf', 'alerta', 'cpf',
                    $nome . ': sem CPF.',
                    'Informe o CPF ou justifique a ausência após conferir o leiaute da edição.');
                if ($oficial && $status === 'pronto') {
                    $status = 'com_alerta';
                }
            }
            $this->atualizarStatus('censo_complementos_aluno', (int) $a['id'], $status);
        }

        $matriculas = $this->model->listarEntidade('matriculas', $edicaoId);
        foreach ($matriculas as $m) {
            if ((int) ($m['turma_id'] ?? 0) <= 0) {
                $itens[] = $this->item('matricula', (int) $m['id'], 'matricula_sem_turma', 'erro', 'turma_id',
                    ($m['nome'] ?? 'Aluno') . ': matrícula sem turma.',
                    'Vincule o aluno a uma turma do ano da edição.');
                $this->atualizarStatus('censo_matriculas', (int) $m['id'], 'com_erro');
            } else {
                $this->atualizarStatus('censo_matriculas', (int) $m['id'], 'pronto');
            }
        }

        $profissionais = $this->model->listarEntidade('profissionais', $edicaoId);
        foreach ($profissionais as $p) {
            $dados = $this->json($p['dados_json'] ?? null);
            $escolaridade = trim((string) ($dados['escolaridade'] ?? ''));
            $status = 'pronto';
            if ($escolaridade === '') {
                $itens[] = $this->item('profissional', (int) $p['id'], 'profissional_sem_formacao', 'alerta', 'escolaridade',
                    CensoNormalizador::nomeExibicao((string) ($p['nome'] ?? 'Profissional')) . ': sem formação cadastrada.',
                    'Preencha a escolaridade no complemento do Censo.');
                if ($oficial) {
                    $status = 'com_alerta';
                }
            }
            $this->atualizarStatus('censo_complementos_profissional', (int) $p['id'], $status);
        }

        $this->model->substituirValidacoes($edicaoId, $itens);
        $resumo = $this->model->resumoValidacao($edicaoId);

        $novoStatus = (string) ($edicao['status'] ?? 'em_preenchimento');
        if ($resumo['erros'] > 0) {
            $novoStatus = 'em_validacao';
        } elseif ($resumo['alertas'] > 0 || $resumo['divergencias'] > 0) {
            $novoStatus = 'em_validacao';
        } else {
            $novoStatus = 'pronto_para_exportar';
        }
        if (!in_array((string) $edicao['status'], CensoEdicao::STATUS_BLOQUEADOS, true)) {
            $this->model->atualizar($edicaoId, [
                'status' => $novoStatus,
                'ultima_validacao_em' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['success' => true, 'resumo' => $resumo, 'itens' => $itens];
    }

    public function podeGerarTxt(array $edicao): array
    {
        $etapa = (string) ($edicao['etapa_coleta'] ?? '');
        $layout = $this->layouts->carregar((int) $edicao['ano'], $etapa);
        if ($etapa === 'situacao_aluno' && empty($layout['pronto_para_txt'])) {
            return [
                'ok' => false,
                'motivo' => 'O TXT da 2ª etapa (Situação do Aluno) ainda não tem leiaute oficial. Abra a edição Matrícula Inicial para gerar o arquivo de importação (registros 00–60).',
            ];
        }
        if (empty($layout['pronto_para_txt'])) {
            return [
                'ok' => false,
                'motivo' => 'O leiaute oficial do INEP desta edição ainda não foi importado. O TXT não pode ser gerado com base em exemplos.',
            ];
        }
        $resumo = $this->model->resumoValidacao((int) $edicao['id']);
        if ($resumo['erros'] > 0) {
            return [
                'ok' => false,
                'motivo' => 'Existem ' . $resumo['erros'] . ' erro(s) impeditivo(s). Corrija-os em Pendências antes de gerar o TXT.',
            ];
        }
        if (in_array((string) ($edicao['status'] ?? ''), CensoEdicao::STATUS_BLOQUEADOS, true)) {
            return ['ok' => false, 'motivo' => 'A edição está fechada.'];
        }
        return ['ok' => true, 'motivo' => '', 'layout' => $layout];
    }

    private function atualizarStatus(string $tabela, int $id, string $status): void
    {
        $this->model->upsertComplemento($tabela, ['id' => $id], ['status_validacao' => $status]);
    }

    private function item(
        string $tipo,
        int $id,
        string $codigo,
        string $severidade,
        string $campo,
        string $mensagem,
        string $orientacao
    ): array {
        return [
            'entidade_tipo' => $tipo,
            'entidade_id' => $id,
            'regra_codigo' => $codigo,
            'severidade' => $severidade,
            'campo' => $campo,
            'mensagem' => $mensagem,
            'orientacao' => $orientacao,
        ];
    }

    private function json($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
