<?php

namespace App\Modulos\CensoEscolar\Services;

require_once __DIR__ . '/../Models/CensoEdicao.php';
require_once __DIR__ . '/CensoNormalizador.php';
require_once __DIR__ . '/CensoEtapaDePara.php';

use App\Modulos\CensoEscolar\Models\CensoEdicao;

/**
 * Monta os registros oficiais 00–60 a partir dos cadastros da edição.
 */
class CensoMapeadorTxt
{
    private CensoEdicao $model;

    public function __construct(?CensoEdicao $model = null)
    {
        $this->model = $model ?? new CensoEdicao();
    }

    /**
     * @return list<array{tipo:string, campos:array<string,string>}>
     */
    public function montar(array $edicao, array $layout): array
    {
        $id = (int) $edicao['id'];
        $ano = (int) $edicao['ano'];
        $completo = ['limite' => 0];
        $escolas = $this->model->listarEntidade('escola', $id, $completo);
        $todasTurmas = $this->model->listarEntidade('turmas', $id, $completo);
        $todosAlunos = $this->model->listarEntidade('alunos', $id, $completo);
        $todasMatriculas = $this->model->listarEntidade('matriculas', $id, $completo);
        $todosGestores = $this->model->listarEntidade('gestores', $id, $completo);
        $todosProfissionais = $this->model->listarEntidade('profissionais', $id, $completo);
        $todosVinculos = $this->model->listarEntidade('vinculos', $id, $completo);
        $qtdEscolas = count($escolas);

        $saida = [];
        foreach ($escolas as $escola) {
            $unidadeId = (int) ($escola['unidade_id'] ?? $edicao['unidade_id'] ?? 0);
            $unidade = $this->model->unidadePorId($unidadeId) ?: [];
            $inep = CensoNormalizador::digits((string) (($unidade['inep'] ?? '') ?: ($escola['codigo_inep'] ?? '')), 8);
            $dadosEscola = $this->json($escola['dados_json'] ?? null);
            $turmas = $this->filtrarUnidade($todasTurmas, $unidadeId, $qtdEscolas, 'turma');
            $gestores = $this->filtrarUnidade($todosGestores, $unidadeId, $qtdEscolas, 'gestor');
            $matriculas = [];
            $turmaIds = [];
            foreach ($turmas as $t) {
                $turmaIds[(int) ($t['turma_id'] ?? 0)] = true;
            }
            foreach ($todasMatriculas as $m) {
                if ((int) ($m['incluir_exportacao'] ?? 1) !== 1) {
                    continue;
                }
                $tid = (int) ($m['turma_id'] ?? 0);
                if ($qtdEscolas > 1 && ($tid <= 0 || !isset($turmaIds[$tid]))) {
                    continue;
                }
                $matriculas[] = $m;
            }
            $alunoIds = [];
            foreach ($matriculas as $m) {
                $alunoIds[(int) ($m['aluno_id'] ?? 0)] = true;
            }
            $alunos = [];
            foreach ($todosAlunos as $a) {
                if (isset($alunoIds[(int) ($a['aluno_id'] ?? 0)])) {
                    $alunos[] = $a;
                }
            }
            $vinculos = [];
            $profIds = [];
            foreach ($todosVinculos as $v) {
                if ((int) ($v['incluir_exportacao'] ?? 1) !== 1) {
                    continue;
                }
                $tid = (int) ($v['turma_id'] ?? 0);
                if ($qtdEscolas > 1 && ($tid <= 0 || !isset($turmaIds[$tid]))) {
                    continue;
                }
                $vinculos[] = $v;
                $profIds[(int) ($v['professor_id'] ?? 0)] = true;
            }
            $profissionais = [];
            foreach ($todosProfissionais as $p) {
                if (isset($profIds[(int) ($p['professor_id'] ?? 0)])) {
                    $profissionais[] = $p;
                }
            }

            $saida[] = ['tipo' => '00', 'campos' => $this->registro00($layout, $edicao, $unidade, $dadosEscola, $inep, $ano)];
            $saida[] = ['tipo' => '10', 'campos' => $this->registro10($layout, $inep, $dadosEscola, count($turmas))];

            foreach ($turmas as $turma) {
                $saida[] = ['tipo' => '20', 'campos' => $this->registro20($layout, $inep, $turma, $unidade)];
            }

            $pessoas = [];
            foreach ($gestores as $g) {
                $codigo = 'G' . (int) $g['id'];
                $pessoas[$codigo] = $this->pessoaGestor($g, $unidade);
            }
            foreach ($profissionais as $p) {
                $prof = $this->model->professorPorId((int) $p['professor_id']) ?? [];
                $codigo = 'P' . (int) $p['professor_id'];
                $pessoas[$codigo] = $this->pessoaProfessor($p, $prof);
            }
            foreach ($alunos as $a) {
                $aluno = $this->model->alunoPorId((int) $a['aluno_id']) ?? [];
                $codigo = 'A' . (int) $a['aluno_id'];
                $pessoas[$codigo] = $this->pessoaAluno($a, $aluno);
            }
            foreach ($pessoas as $codigo => $pessoa) {
                $saida[] = ['tipo' => '30', 'campos' => $this->registro30($layout, $inep, $codigo, $pessoa)];
            }
            foreach ($gestores as $g) {
                $saida[] = ['tipo' => '40', 'campos' => $this->registro40($layout, $inep, 'G' . (int) $g['id'], $g)];
            }
            $situacaoPorProf = [];
            foreach ($profissionais as $p) {
                $dadosP = $this->json($p['dados_json'] ?? null);
                $situacaoPorProf[(int) ($p['professor_id'] ?? 0)] = CensoNormalizador::situacaoFuncional(
                    (string) ($dadosP['situacao_funcional'] ?? '')
                );
            }
            foreach ($vinculos as $v) {
                $saida[] = ['tipo' => '50', 'campos' => $this->registro50($layout, $inep, $v, $situacaoPorProf)];
            }
            foreach ($matriculas as $m) {
                $saida[] = ['tipo' => '60', 'campos' => $this->registro60($layout, $inep, $m)];
            }
        }
        return $saida;
    }

    /**
     * @return list<array<string,string>>
     */
    public function identificacao(array $edicao): array
    {
        $linhas = [];
        foreach ($this->model->listarEntidade('alunos', (int) $edicao['id'], ['limite' => 0]) as $a) {
            $aluno = $this->model->alunoPorId((int) $a['aluno_id']) ?? [];
            $inepPessoa = CensoNormalizador::digits((string) ($aluno['codigo_inep'] ?? ''), 12);
            if ($inepPessoa !== '') {
                continue;
            }
            $dados = $this->json($a['dados_json'] ?? null);
            $linhas[] = [
                'c1' => CensoNormalizador::cortar('A' . (int) $a['aluno_id'], 20),
                'c2' => CensoNormalizador::digits((string) (($aluno['cpf'] ?? '') ?: ($dados['cpf'] ?? '')), 11),
                'c3' => '',
                'c4' => CensoNormalizador::nome((string) ($aluno['nome'] ?? '')),
                'c5' => CensoNormalizador::data((string) (($aluno['data_nasc'] ?? '') ?: ($dados['data_nasc'] ?? ''))),
                'c6' => CensoNormalizador::nome((string) (($aluno['nome_mae'] ?? '') ?: ($dados['nome_mae'] ?? ''))),
                'c7' => CensoNormalizador::nome((string) (($aluno['nome_pai'] ?? '') ?: ($dados['nome_pai'] ?? ''))),
                'c8' => CensoNormalizador::digits((string) (($aluno['municipio_nascimento'] ?? '') ?: ($dados['municipio_nascimento'] ?? '')), 7),
                'c9' => '',
            ];
        }
        return $linhas;
    }

    /**
     * @param array<string,mixed> $unidade
     * @param array<string,mixed> $dados
     * @return array<string,string>
     */
    private function registro00(array $layout, array $edicao, array $unidade, array $dados, string $inep, int $ano): array
    {
        $r = $this->vazio($layout, '00');
        $r['c1'] = '00';
        $r['c2'] = $inep;
        $sit = trim((string) ($dados['situacao_funcionamento'] ?? '1'));
        $r['c3'] = in_array($sit, ['1', '2', '3'], true) ? $sit : '1';
        $r['c4'] = CensoNormalizador::data((string) ($dados['data_inicio_ano_letivo'] ?? '')) ?: ('01/02/' . $ano);
        $r['c5'] = CensoNormalizador::data((string) ($dados['data_fim_ano_letivo'] ?? '')) ?: ('20/12/' . $ano);
        $r['c6'] = CensoNormalizador::alfanumerico((string) ($unidade['nome'] ?? $dados['nome'] ?? 'ESCOLA'), 100);
        $r['c7'] = CensoNormalizador::digits((string) ($unidade['cep'] ?? $dados['cep'] ?? ''), 8);
        $mun = CensoNormalizador::digits((string) ($dados['municipio'] ?? $unidade['codigo_ibge'] ?? $unidade['cidade'] ?? ''), 7);
        $r['c8'] = strlen($mun) === 7 ? $mun : '';
        $r['c9'] = CensoNormalizador::digits((string) ($dados['distrito'] ?? '01'), 2) ?: '01';
        $r['c10'] = CensoNormalizador::alfanumerico((string) ($unidade['endereco'] ?? $dados['endereco'] ?? ''), 100);
        $r['c11'] = CensoNormalizador::alfanumerico((string) ($unidade['numero'] ?? $dados['numero'] ?? ''), 10);
        $r['c12'] = CensoNormalizador::alfanumerico((string) ($unidade['complemento'] ?? $dados['complemento'] ?? ''), 20);
        $r['c13'] = CensoNormalizador::alfanumerico((string) ($unidade['bairro'] ?? $dados['bairro'] ?? ''), 50);
        $tel = CensoNormalizador::digits((string) ($unidade['telefone'] ?? $dados['telefone'] ?? ''));
        if (strlen($tel) >= 10) {
            $r['c14'] = substr($tel, 0, 2);
            $r['c15'] = substr($tel, 2, 9);
        }
        $r['c17'] = CensoNormalizador::cortar(strtolower(trim((string) ($unidade['email'] ?? $dados['email'] ?? ''))), 100);
        $zona = strtolower((string) ($dados['localizacao'] ?? ''));
        $r['c19'] = str_contains($zona, 'rural') ? '2' : '1';
        $r['c20'] = (string) ($dados['localizacao_diferenciada'] ?? '7');
        $r['c21'] = CensoNormalizador::dependencia((string) ($unidade['dependencia_administrativa'] ?? $dados['dependencia_administrativa'] ?? 'privada'));
        if ($r['c21'] === '4') {
            $r['c26'] = '1';
            $r['c32'] = (string) ($dados['categoria_privada'] ?? '1');
            $cnpj = CensoNormalizador::digits((string) ($unidade['cnpj'] ?? $dados['cnpj'] ?? ''), 14);
            $r['c48'] = $cnpj;
            $r['c47'] = $cnpj;
        }
        $r['c49'] = (string) ($dados['regulamentacao'] ?? '1');
        return $r;
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,string>
     */
    private function registro10(array $layout, string $inep, array $dados, int $qtdTurmas): array
    {
        $r = $this->vazio($layout, '10');
        $r['c1'] = '10';
        $r['c2'] = $inep;
        foreach ($this->infraPadrao($qtdTurmas) as $chave => $valor) {
            $r[$chave] = $valor;
        }
        foreach ($dados as $chave => $valor) {
            if (preg_match('/^c\d+$/', (string) $chave) && $valor !== '' && $valor !== null) {
                $r[$chave] = (string) $valor;
            }
        }
        return $r;
    }

    /**
     * @param array<string,mixed> $turma
     * @param array<string,mixed> $unidade
     * @return array<string,string>
     */
    private function registro20(array $layout, string $inep, array $turma, array $unidade): array
    {
        $r = $this->vazio($layout, '20');
        $dados = $this->json($turma['dados_json'] ?? null);
        $sugestao = CensoEtapaDePara::sugerir([
            'serie' => (string) ($turma['serie'] ?? $dados['serie_academica'] ?? ''),
            'nome' => (string) ($turma['nome'] ?? ''),
        ]);
        $etapa = trim((string) ($turma['etapa_codigo'] ?? '')) ?: $sugestao['codigo'];
        if (CensoEtapaDePara::ehInterno($etapa)) {
            $etapa = $sugestao['codigo'];
        }
        $r['c1'] = '20';
        $r['c2'] = $inep;
        $r['c3'] = 'T' . (int) ($turma['turma_id'] ?? 0);
        $r['c4'] = CensoNormalizador::digits((string) ($turma['codigo_inep'] ?? $dados['codigo_inep'] ?? ''));
        $r['c5'] = CensoNormalizador::alfanumerico((string) ($turma['nome'] ?? $dados['nome'] ?? 'TURMA'), 80);
        $r['c6'] = (string) ($dados['mediacao'] ?? '1');
        $horario = $this->horario($dados);
        foreach (['c8', 'c9', 'c10', 'c11', 'c12'] as $dia) {
            $r[$dia] = $horario;
        }
        $r['c14'] = (string) ($dados['tipo_atendimento'] ?? '6');
        $r['c21'] = '0';
        $r['c23'] = (string) ($dados['etapa_agregada'] ?? $sugestao['agregada'] ?? '');
        $r['c24'] = $etapa;
        if (in_array($etapa, ['25', '26', '27', '28', '29'], true)) {
            $r['c23'] = $r['c23'] !== '' ? $r['c23'] : '304';
            $r['c30'] = '1';
        }
        $r['c28'] = '1';
        $r['c29'] = '0';
        $r['c66'] = '0';
        return $r;
    }

    /**
     * @param array<string,mixed> $pessoa
     * @return array<string,string>
     */
    private function registro30(array $layout, string $inep, string $codigo, array $pessoa): array
    {
        $r = $this->vazio($layout, '30');
        $mae = CensoNormalizador::nome((string) ($pessoa['nome_mae'] ?? ''));
        $pai = CensoNormalizador::nome((string) ($pessoa['nome_pai'] ?? ''));
        $r['c1'] = '30';
        $r['c2'] = $inep;
        $r['c3'] = CensoNormalizador::cortar($codigo, 20);
        $r['c4'] = CensoNormalizador::digits((string) ($pessoa['codigo_inep'] ?? ''), 12);
        $r['c5'] = CensoNormalizador::digits((string) ($pessoa['cpf'] ?? ''), 11);
        $r['c6'] = CensoNormalizador::nome((string) ($pessoa['nome'] ?? ''));
        $r['c7'] = CensoNormalizador::data((string) ($pessoa['data_nasc'] ?? ''));
        $r['c8'] = ($mae !== '' || $pai !== '') ? '1' : '0';
        $r['c9'] = $mae;
        $r['c10'] = $pai;
        $r['c11'] = CensoNormalizador::sexo($pessoa['sexo'] ?? '');
        $r['c12'] = CensoNormalizador::corRaca($pessoa['cor_raca'] ?? '');
        $r['c14'] = '1';
        $r['c15'] = '76';
        $mun = CensoNormalizador::digits((string) ($pessoa['municipio_nascimento'] ?? ''), 7);
        $r['c16'] = strlen($mun) === 7 ? $mun : '';
        $r['c17'] = '0';
        $cep = CensoNormalizador::digits((string) ($pessoa['cep'] ?? ''), 8);
        $r['c52'] = $cep;
        $escolaridade = CensoNormalizador::escolaridadeInep((string) ($pessoa['escolaridade'] ?? ''));
        if ($escolaridade !== '') {
            $r['c56'] = $escolaridade;
        }
        return $r;
    }

    /**
     * @param array<string,mixed> $gestor
     * @return array<string,string>
     */
    private function registro40(array $layout, string $inep, string $codigo, array $gestor): array
    {
        $r = $this->vazio($layout, '40');
        $cargo = strtolower((string) ($gestor['cargo_codigo'] ?? 'diretor'));
        $r['c1'] = '40';
        $r['c2'] = $inep;
        $r['c3'] = CensoNormalizador::cortar($codigo, 20);
        $r['c5'] = $cargo === 'diretor' ? '1' : '2';
        $r['c6'] = '1';
        $r['c7'] = '4';
        return $r;
    }

    /**
     * @param array<string,mixed> $vinculo
     * @param array<int,string> $situacaoPorProf
     * @return array<string,string>
     */
    private function registro50(array $layout, string $inep, array $vinculo, array $situacaoPorProf = []): array
    {
        $r = $this->vazio($layout, '50');
        $dados = $this->json($vinculo['dados_json'] ?? null);
        $profId = (int) ($vinculo['professor_id'] ?? 0);
        $r['c1'] = '50';
        $r['c2'] = $inep;
        $r['c3'] = 'P' . $profId;
        $r['c4'] = CensoNormalizador::digits((string) ($vinculo['codigo_inep'] ?? ''), 12);
        $r['c5'] = 'T' . (int) ($vinculo['turma_id'] ?? 0);
        $r['c7'] = '1';
        $r['c8'] = CensoNormalizador::situacaoFuncional(
            (string) ($situacaoPorProf[$profId] ?? $dados['situacao_funcional'] ?? '4')
        );
        $r['c9'] = $this->codigoComponente((string) (
            ($vinculo['materia_nome'] ?? '')
            ?: ($dados['materia_nome'] ?? '')
            ?: ($vinculo['materia_codigo'] ?? '')
            ?: ($dados['materia_codigo'] ?? '')
        ));
        return $r;
    }

    /**
     * @param array<string,mixed> $matricula
     * @return array<string,string>
     */
    private function registro60(array $layout, string $inep, array $matricula): array
    {
        $r = $this->vazio($layout, '60');
        $r['c1'] = '60';
        $r['c2'] = $inep;
        $r['c3'] = 'A' . (int) ($matricula['aluno_id'] ?? 0);
        $r['c4'] = CensoNormalizador::digits((string) ($matricula['codigo_inep'] ?? ''), 12);
        $r['c5'] = 'T' . (int) ($matricula['turma_id'] ?? 0);
        $r['c21'] = '1';
        $r['c22'] = '0';
        return $r;
    }

    /**
     * @return array<string,string>
     */
    private function infraPadrao(int $qtdTurmas): array
    {
        $r = [];
        $r['c3'] = '1';
        for ($i = 4; $i <= 8; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c9'] = '1';
        $r['c10'] = '0';
        $r['c17'] = '1';
        $r['c18'] = '1';
        for ($i = 19; $i <= 23; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c24'] = '1';
        $r['c25'] = '0';
        $r['c26'] = '0';
        $r['c27'] = '0';
        $r['c28'] = '1';
        $r['c29'] = '0';
        $r['c30'] = '0';
        $r['c31'] = '0';
        $r['c32'] = '1';
        for ($i = 33; $i <= 36; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c37'] = '0';
        $r['c38'] = '0';
        $r['c39'] = '0';
        $r['c40'] = '1';
        for ($i = 41; $i <= 79; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c44'] = '1';
        $r['c50'] = '1';
        $r['c72'] = '1';
        $r['c74'] = '1';
        $r['c76'] = '1';
        $r['c80'] = '0';
        for ($i = 81; $i <= 89; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c90'] = '1';
        $r['c91'] = (string) max(1, $qtdTurmas);
        $r['c92'] = '0';
        $r['c93'] = '0';
        $r['c94'] = '0';
        $r['c95'] = '0';
        for ($i = 96; $i <= 101; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c102'] = '1';
        $r['c111'] = '1';
        $r['c112'] = '1';
        $r['c113'] = '0';
        $r['c114'] = '0';
        $r['c115'] = '0';
        $r['c117'] = '1';
        $r['c118'] = '1';
        $r['c138'] = '1';
        $r['c139'] = '0';
        for ($i = 140; $i <= 158; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c159'] = '1';
        $r['c160'] = '2';
        $r['c164'] = '0';
        $r['c171'] = '0';
        $r['c172'] = '0';
        $r['c173'] = '0';
        for ($i = 174; $i <= 178; $i++) {
            $r['c' . $i] = '0';
        }
        $r['c179'] = '1';
        $r['c180'] = '0';
        $r['c181'] = '0';
        $r['c187'] = '1';
        return $r;
    }

    /**
     * @param list<array<string,mixed>> $lista
     * @return list<array<string,mixed>>
     */
    private function filtrarUnidade(array $lista, int $unidadeId, int $qtdEscolas, string $tipo): array
    {
        if ($qtdEscolas <= 1 || $unidadeId <= 0) {
            return $lista;
        }
        $out = [];
        foreach ($lista as $row) {
            if ($this->registroDaUnidade($row, $unidadeId, $tipo)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function registroDaUnidade(array $row, int $unidadeId, string $tipo): bool
    {
        $dados = $this->json($row['dados_json'] ?? null);
        $uid = (int) ($dados['unidade_id'] ?? $row['aluno_unidade_id'] ?? $row['unidade_id'] ?? 0);
        if ($uid <= 0 && ($tipo === 'aluno' || $tipo === 'matricula')) {
            $aluno = $this->model->alunoPorId((int) ($row['aluno_id'] ?? 0));
            $uid = (int) ($aluno['unidade_id'] ?? 0);
        }
        if ($uid <= 0 && $tipo === 'turma') {
            $uid = $this->model->unidadeIdDaTurma((int) ($row['turma_id'] ?? 0));
        }
        return $uid === $unidadeId;
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function horario(array $dados): string
    {
        $h = trim((string) ($dados['horario'] ?? ''));
        if (preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}$/', $h)) {
            return $h;
        }
        $ini = trim((string) ($dados['horario_inicio'] ?? ''));
        if (preg_match('/^\d{2}:\d{2}$/', $ini)) {
            $horas = (int) ($dados['duracao'] ?? 5);
            $iniTs = strtotime('2000-01-01 ' . $ini);
            if ($iniTs !== false) {
                return $ini . '-' . date('H:i', $iniTs + max(1, $horas) * 3600);
            }
        }
        return '07:00-12:00';
    }

    private function codigoComponente(string $nome): string
    {
        $n = CensoNormalizador::ascii(mb_strtolower($nome, 'UTF-8'));
        $mapa = [
            'quimica' => '1', 'física' => '2', 'fisica' => '2', 'matematica' => '3', 'matemática' => '3',
            'biologia' => '4', 'ciencias' => '5', 'ciências' => '5',
            'portugues' => '6', 'português' => '6', 'lingua portuguesa' => '6',
            'ingles' => '7', 'inglês' => '7', 'espanhol' => '8',
            'arte' => '10', 'artes' => '10', 'educacao fisica' => '11', 'educação física' => '11',
            'historia' => '12', 'história' => '12', 'geografia' => '13',
            'filosofia' => '14', 'informatica' => '16', 'libras' => '23',
            'ensino religioso' => '26', 'sociologia' => '29', 'projeto de vida' => '33',
        ];
        foreach ($mapa as $chave => $cod) {
            if ($chave !== '' && str_contains($n, $chave)) {
                return $cod;
            }
        }
        return '99';
    }

    /**
     * @return array<string,string>
     */
    private function pessoaGestor(array $g, array $unidade): array
    {
        $dados = $this->json($g['dados_json'] ?? null);
        return [
            'nome' => (string) ($g['nome'] ?? ''),
            'cpf' => (string) ($g['cpf'] ?? $dados['cpf'] ?? ''),
            'codigo_inep' => (string) ($g['codigo_inep'] ?? ''),
            'data_nasc' => (string) ($dados['data_nasc'] ?? ''),
            'sexo' => (string) ($dados['sexo'] ?? ''),
            'cor_raca' => (string) ($dados['cor_raca'] ?? ''),
            'cep' => (string) ($unidade['cep'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @param array<string,mixed> $prof
     * @return array<string,string>
     */
    private function pessoaProfessor(array $p, array $prof): array
    {
        $dados = $this->json($p['dados_json'] ?? null);
        return [
            'nome' => CensoNormalizador::nomeExibicao((string) ($prof['nome'] ?? $dados['nome'] ?? '')),
            'cpf' => (string) ($p['cpf'] ?? $prof['cpf'] ?? $dados['cpf'] ?? ''),
            'codigo_inep' => (string) ($p['codigo_inep'] ?? $dados['codigo_inep'] ?? ''),
            'data_nasc' => (string) ($dados['data_nasc'] ?? $prof['data_nasc'] ?? ''),
            'sexo' => (string) ($dados['sexo'] ?? $prof['sexo'] ?? ''),
            'cor_raca' => (string) ($dados['cor_raca'] ?? ''),
            'cep' => (string) ($dados['cep'] ?? ''),
            'escolaridade' => CensoNormalizador::escolaridadeInep((string) ($dados['escolaridade'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $aluno
     * @return array<string,string>
     */
    private function pessoaAluno(array $a, array $aluno): array
    {
        $dados = $this->json($a['dados_json'] ?? null);
        return [
            'nome' => (string) ($aluno['nome'] ?? ''),
            'cpf' => (string) (($aluno['cpf'] ?? '') ?: ($dados['cpf'] ?? '')),
            'codigo_inep' => (string) (($aluno['codigo_inep'] ?? '') ?: ($dados['codigo_inep'] ?? '')),
            'data_nasc' => (string) (($aluno['data_nasc'] ?? '') ?: ($dados['data_nasc'] ?? '')),
            'nome_mae' => (string) (($aluno['nome_mae'] ?? '') ?: ($dados['nome_mae'] ?? '')),
            'nome_pai' => (string) (($aluno['nome_pai'] ?? '') ?: ($dados['nome_pai'] ?? '')),
            'sexo' => (string) (($aluno['sexo'] ?? '') ?: ($dados['sexo'] ?? '')),
            'cor_raca' => (string) (($aluno['cor_raca'] ?? '') ?: ($dados['cor_raca'] ?? '')),
            'cep' => (string) (($aluno['cep'] ?? '') ?: ($dados['cep'] ?? '')),
            'municipio_nascimento' => (string) ($dados['municipio_nascimento'] ?? ''),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function vazio(array $layout, string $tipo): array
    {
        $out = [];
        foreach ($layout['registros'][$tipo]['campos'] ?? [] as $campo) {
            $out[(string) ($campo['chave'] ?? '')] = '';
        }
        return $out;
    }

    private function json($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
