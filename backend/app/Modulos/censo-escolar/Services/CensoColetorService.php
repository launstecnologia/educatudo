<?php

namespace App\Modulos\CensoEscolar\Services;

require_once __DIR__ . '/../Models/CensoEdicao.php';
require_once __DIR__ . '/CensoEtapaDePara.php';
require_once __DIR__ . '/CensoNormalizador.php';

use App\Modulos\CensoEscolar\Models\CensoEdicao;

/**
 * Sincroniza a edição com os cadastros acadêmicos, sem duplicar pessoa.
 * Copia para o complemento só o que ainda não foi preenchido na edição.
 */
class CensoColetorService
{
    private CensoEdicao $model;

    public function __construct(?CensoEdicao $model = null)
    {
        $this->model = $model ?? new CensoEdicao();
    }

    /**
     * @return array{success:bool, error?:string, totais?:array}
     */
    public function sincronizar(array $edicao): array
    {
        if (!$this->model->schemaPronto()) {
            return ['success' => false, 'error' => 'Execute a migration do Censo Escolar antes de sincronizar.'];
        }
        $edicaoId = (int) $edicao['id'];
        $ano = (int) $edicao['ano'];
        $unidadeId = (int) ($edicao['unidade_id'] ?? 0);
        if (in_array((string) ($edicao['status'] ?? ''), CensoEdicao::STATUS_BLOQUEADOS, true)) {
            return ['success' => false, 'error' => 'A edição está fechada. Reabra com justificativa para sincronizar.'];
        }

        $unidades = $unidadeId > 0
            ? array_values(array_filter([$this->model->unidadePorId($unidadeId)]))
            : $this->model->unidadesAtivas();
        foreach ($unidades as $i => $u) {
            $cep = (string) ($u['cep'] ?? '');
            $municipio = CensoNormalizador::digits((string) ($u['codigo_ibge'] ?? $u['cidade'] ?? ''), 7);
            if (strlen($municipio) !== 7) {
                $municipio = $this->municipioPorCep($cep);
            }
            $unidades[$i]['_municipio_ibge'] = $municipio;
        }

        $this->model->begin();
        try {
            $escola = $this->sincronizarEscola($edicaoId, $unidades);
            $gestores = $this->sincronizarGestores($edicaoId, $unidades);
            $turmas = $this->sincronizarTurmas($edicaoId, $ano, $unidadeId);
            $alunos = $this->sincronizarAlunos($edicaoId, $ano, $unidadeId, $turmas['mapa']);
            $profissionais = $this->sincronizarProfissionais($edicaoId, $ano, $unidadeId);
            $vinculos = $this->sincronizarVinculos($edicaoId, $ano, $unidadeId, $turmas['mapa']);
            $this->model->commit();
        } catch (\Throwable $e) {
            $this->model->rollback();
            return ['success' => false, 'error' => 'Falha ao sincronizar: ' . $e->getMessage()];
        }

        return [
            'success' => true,
            'totais' => [
                'escola' => $escola,
                'gestores' => $gestores,
                'turmas' => $turmas['total'],
                'alunos' => $alunos,
                'profissionais' => $profissionais,
                'vinculos' => $vinculos,
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $unidades
     */
    private function sincronizarEscola(int $edicaoId, array $unidades): int
    {
        $n = 0;
        foreach ($unidades as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $chaves = ['edicao_id' => $edicaoId, 'unidade_id' => $uid];
            $existente = $this->model->buscarPorChaves('censo_complementos_escola', $chaves);
            $this->model->upsertComplemento('censo_complementos_escola', $chaves, [
                'dados_json' => $this->mesclarJson($existente['dados_json'] ?? null, $this->dadosUnidade($u)),
            ]);
            $n++;
        }
        if ($n === 0) {
            $this->model->upsertComplemento(
                'censo_complementos_escola',
                ['edicao_id' => $edicaoId, 'unidade_id' => 0],
                []
            );
            return 1;
        }
        return $n;
    }

    /**
     * @param list<array<string,mixed>> $unidades
     */
    private function sincronizarGestores(int $edicaoId, array $unidades): int
    {
        $n = 0;
        foreach ($unidades as $u) {
            foreach (['diretor_nome' => 'diretor', 'secretario_nome' => 'secretario'] as $campo => $cargo) {
                $nome = trim((string) ($u[$campo] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $chaves = ['edicao_id' => $edicaoId, 'nome' => $nome, 'cargo_codigo' => $cargo];
                $existente = $this->model->buscarPorChaves('censo_complementos_gestor', $chaves);
                $this->model->upsertComplemento('censo_complementos_gestor', $chaves, [
                    'dados_json' => $this->mesclarJson($existente['dados_json'] ?? null, [
                        'cargo' => $cargo,
                        'origem' => 'cadastro_unidade',
                        'unidade_id' => (int) ($u['id'] ?? 0),
                        'unidade_nome' => (string) ($u['nome'] ?? ''),
                    ]),
                ]);
                $n++;
            }
        }
        return $n;
    }

    /**
     * @return array{total:int, mapa: array<int,int>}
     */
    private function sincronizarTurmas(int $edicaoId, int $ano, int $unidadeId): array
    {
        $turmas = $this->model->turmasDoAno($ano, $unidadeId);
        $mapa = [];
        foreach ($turmas as $t) {
            $chaves = ['edicao_id' => $edicaoId, 'turma_id' => (int) $t['id']];
            $existente = $this->model->buscarPorChaves('censo_complementos_turma', $chaves);
            $uidTurma = $unidadeId > 0 ? $unidadeId : $this->model->unidadeIdDaTurma((int) $t['id']);
            $sugestao = CensoEtapaDePara::sugerir($t);
            $dados = $this->mesclarJson($existente['dados_json'] ?? null, [
                'nome' => (string) ($t['nome'] ?? ''),
                'serie_academica' => (string) ($t['serie'] ?? ''),
                'ano_letivo' => (int) ($t['ano_letivo'] ?? $ano),
                'unidade_id' => $uidTurma,
                'etapa_sugerida' => $sugestao['descricao'],
                'etapa_de_para_interno' => $sugestao['codigo'],
                'etapa_agregada' => $sugestao['agregada'] ?? '',
            ]);
            $upd = ['dados_json' => $dados];
            $etapaAtual = trim((string) ($existente['etapa_codigo'] ?? ''));
            if (($etapaAtual === '' || CensoEtapaDePara::ehInterno($etapaAtual)) && $sugestao['codigo'] !== '') {
                $upd['etapa_codigo'] = $sugestao['codigo'];
            }
            if (trim((string) ($existente['modalidade_codigo'] ?? '')) === '' && ($sugestao['modalidade'] ?? '') !== '') {
                $upd['modalidade_codigo'] = $sugestao['modalidade'];
            }
            $id = $this->model->upsertComplemento('censo_complementos_turma', $chaves, $upd);
            $mapa[(int) $t['id']] = $id;
        }
        return ['total' => count($turmas), 'mapa' => $mapa];
    }

    /**
     * @param array<int,int> $mapaTurmas
     */
    private function sincronizarAlunos(int $edicaoId, int $ano, int $unidadeId, array $mapaTurmas): int
    {
        $alunos = $this->model->alunosDaEdicao($ano, $unidadeId);
        foreach ($alunos as $a) {
            $alunoId = (int) $a['id'];
            $chaves = ['edicao_id' => $edicaoId, 'aluno_id' => $alunoId];
            $existente = $this->model->buscarPorChaves('censo_complementos_aluno', $chaves);
            $this->model->upsertComplemento('censo_complementos_aluno', $chaves, [
                'dados_json' => $this->mesclarJson($existente['dados_json'] ?? null, $this->dadosAluno($a)),
            ]);
            $turmaId = (int) ($a['turma_id'] ?? 0);
            if ($turmaId > 0) {
                $chavesMat = ['edicao_id' => $edicaoId, 'aluno_id' => $alunoId, 'turma_id' => $turmaId];
                $existenteMat = $this->model->buscarPorChaves('censo_matriculas', $chavesMat);
                $updMat = ['censo_turma_id' => $mapaTurmas[$turmaId] ?? null];
                if (!$existenteMat) {
                    $updMat['incluir_exportacao'] = 1;
                }
                $this->model->upsertComplemento('censo_matriculas', $chavesMat, $updMat);
            }
        }
        return count($alunos);
    }

    private function sincronizarProfissionais(int $edicaoId, int $ano, int $unidadeId): int
    {
        $lista = $this->model->profissionaisDaEdicao($ano, $unidadeId);
        foreach ($lista as $p) {
            $chaves = ['edicao_id' => $edicaoId, 'professor_id' => (int) $p['id']];
            $existente = $this->model->buscarPorChaves('censo_complementos_profissional', $chaves);
            $cpf = $this->valorOpcional($p, 'cpf');
            $inep = $this->valorOpcional($p, 'codigo_inep');
            $escolaridade = $this->valorOpcional($p, 'escolaridade');
            $formacao = $this->valorOpcional($p, 'formacao');
            $origem = [
                'nome' => CensoNormalizador::nomeExibicao((string) ($p['nome'] ?? '')),
                'email' => (string) ($p['email'] ?? ''),
                'codigo_prof' => (string) ($p['codigo_prof'] ?? ''),
                'formacao_superior' => $formacao,
            ];
            if ($escolaridade !== '') {
                $origem['escolaridade'] = $escolaridade;
            }
            $dados = $this->mesclarJson($existente['dados_json'] ?? null, $origem);
            $decod = json_decode($dados, true);
            $decod = is_array($decod) ? $decod : [];
            $decod['nome'] = $origem['nome'];
            $decod['escolaridade'] = CensoNormalizador::escolaridadeInep((string) ($decod['escolaridade'] ?? '')) ?: '6';
            $decod['situacao_funcional'] = CensoNormalizador::situacaoFuncional((string) ($decod['situacao_funcional'] ?? ''));
            $nomeOriginal = trim((string) ($p['nome'] ?? ''));
            if ($origem['nome'] !== '' && $origem['nome'] !== $nomeOriginal) {
                $this->model->atualizarNomeProfessor((int) $p['id'], $origem['nome']);
            }
            $upd = ['dados_json' => json_encode($decod, JSON_UNESCAPED_UNICODE) ?: '{}'];
            if (trim((string) ($existente['cpf'] ?? '')) === '' && $cpf !== '') {
                $upd['cpf'] = preg_replace('/\D+/', '', $cpf);
            }
            if (trim((string) ($existente['codigo_inep'] ?? '')) === '' && $inep !== '') {
                $upd['codigo_inep'] = $inep;
            }
            $this->model->upsertComplemento('censo_complementos_profissional', $chaves, $upd);
        }
        return count($lista);
    }

    /**
     * @param array<int,int> $mapaTurmas
     */
    private function sincronizarVinculos(int $edicaoId, int $ano, int $unidadeId, array $mapaTurmas): int
    {
        $vinculos = $this->model->vinculosGrade($ano, $unidadeId);
        foreach ($vinculos as $v) {
            $turmaId = (int) $v['turma_id'];
            $chavesVinculo = [
                'edicao_id' => $edicaoId,
                'professor_id' => (int) $v['professor_id'],
                'turma_id' => $turmaId,
                'materia_id' => (int) ($v['materia_id'] ?? 0),
            ];
            $existenteVinculo = $this->model->buscarPorChaves('censo_vinculos_profissionais', $chavesVinculo);
            $updVinculo = [
                'censo_turma_id' => $mapaTurmas[$turmaId] ?? null,
                'dados_json' => $this->mesclarJson($existenteVinculo['dados_json'] ?? null, [
                    'materia_nome' => (string) ($v['materia_nome'] ?? ''),
                    'materia_codigo' => (string) ($v['materia_codigo'] ?? ''),
                ]),
            ];
            if (!$existenteVinculo) {
                $updVinculo['incluir_exportacao'] = 1;
            }
            $this->model->upsertComplemento('censo_vinculos_profissionais', $chavesVinculo, $updVinculo);
        }
        return count($vinculos);
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosUnidade(array $u): array
    {
        $cep = (string) ($u['cep'] ?? '');
        $municipio = CensoNormalizador::digits((string) (
            ($u['_municipio_ibge'] ?? '')
            ?: ($u['codigo_ibge'] ?? '')
            ?: ($u['cidade'] ?? '')
        ), 7);
        return [
            'inep' => (string) ($u['inep'] ?? ''),
            'codigo_inep' => (string) ($u['inep'] ?? ''),
            'dependencia_administrativa' => (string) ($u['dependencia_administrativa'] ?? ''),
            'cnpj' => (string) ($u['cnpj'] ?? ''),
            'endereco' => (string) ($u['endereco'] ?? ''),
            'numero' => (string) ($u['numero'] ?? ''),
            'complemento' => (string) ($u['complemento'] ?? ''),
            'bairro' => (string) ($u['bairro'] ?? ''),
            'cidade' => (string) ($u['cidade'] ?? ''),
            'municipio' => $municipio,
            'uf' => (string) ($u['uf'] ?? ''),
            'cep' => $cep,
            'telefone' => (string) ($u['telefone'] ?? ''),
            'email' => (string) ($u['email'] ?? ''),
            'diretor_nome' => (string) ($u['diretor_nome'] ?? ''),
            'secretario_nome' => (string) ($u['secretario_nome'] ?? ''),
            'nome' => (string) ($u['nome'] ?? ''),
        ];
    }

    private function municipioPorCep(string $cep): string
    {
        $cep = preg_replace('/\D+/', '', $cep) ?? '';
        if (strlen($cep) !== 8) {
            return '';
        }
        $ctx = stream_context_create([
            'http' => ['timeout' => 3],
            'https' => ['timeout' => 3],
        ]);
        $raw = @file_get_contents('https://viacep.com.br/ws/' . $cep . '/json/', false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        $dados = json_decode($raw, true);
        if (!is_array($dados) || !empty($dados['erro'])) {
            return '';
        }
        $ibge = preg_replace('/\D+/', '', (string) ($dados['ibge'] ?? '')) ?? '';
        return strlen($ibge) === 7 ? $ibge : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosAluno(array $a): array
    {
        return [
            'nome' => (string) ($a['nome'] ?? ''),
            'codigo_inep' => (string) ($a['codigo_inep'] ?? ''),
            'cpf' => (string) ($a['cpf'] ?? ''),
            'data_nasc' => (string) ($a['data_nasc'] ?? ''),
            'sexo' => (string) ($a['sexo'] ?? ''),
            'nome_mae' => (string) ($a['nome_mae'] ?? ''),
            'nome_pai' => (string) ($a['nome_pai'] ?? ''),
            'cor_raca' => (string) ($a['cor_raca'] ?? ''),
            'nacionalidade' => (string) ($a['nacionalidade'] ?? ''),
            'naturalidade' => (string) ($a['naturalidade'] ?? ''),
            'uf_nascimento' => (string) ($a['uf_nascimento'] ?? ''),
            'nome_social' => (string) ($a['nome_social'] ?? ''),
            'logradouro' => (string) ($a['logradouro'] ?? ''),
            'numero' => (string) ($a['numero'] ?? ''),
            'bairro' => (string) ($a['bairro'] ?? ''),
            'cidade' => (string) ($a['cidade'] ?? ''),
            'uf' => (string) ($a['uf'] ?? ''),
            'cep' => (string) ($a['cep'] ?? ''),
            'zona' => (string) ($a['zona'] ?? ''),
            'turma_nome' => (string) ($a['turma_nome'] ?? ''),
            'turma_serie' => (string) ($a['turma_serie'] ?? ''),
            'unidade_id' => (int) ($a['unidade_id'] ?? 0),
        ];
    }

    private function mesclarJson($existente, array $origem): string
    {
        $atual = [];
        if (is_array($existente)) {
            $atual = $existente;
        } elseif (is_string($existente) && $existente !== '') {
            $decoded = json_decode($existente, true);
            $atual = is_array($decoded) ? $decoded : [];
        }
        foreach ($origem as $chave => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }
            if (!isset($atual[$chave]) || $atual[$chave] === '' || $atual[$chave] === null) {
                $atual[$chave] = $valor;
            }
        }
        return json_encode($atual, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function valorOpcional(array $row, string $coluna): string
    {
        return trim((string) ($row[$coluna] ?? ''));
    }
}
