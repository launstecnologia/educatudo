<?php

namespace App\Modulos\Lex\Services;

/**
 * Decide curso, série, turma e matrícula sem gravar no banco.
 * Não funde pelo nome. A chave continua sendo o id da LEX.
 */
class LexPlanoAcademico
{
    /**
     * @param array<string,mixed> $academico
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $planoPessoas
     * @return array<string,mixed>
     */
    public static function montar(array $academico, array $estado, array $planoPessoas): array
    {
        $vinculos = is_array($estado['vinculos'] ?? null) ? $estado['vinculos'] : [];
        $alunosImportados = self::idsDoPlano($planoPessoas, 'aluno');
        $professoresImportados = self::idsDoPlano($planoPessoas, 'professor');
        $conflitos = is_array($academico['conflitos'] ?? null) ? $academico['conflitos'] : [];
        $ignorados = is_array($academico['ignorados'] ?? null) ? $academico['ignorados'] : [];
        $bloqueia = !($academico['ok'] ?? false);
        foreach ($conflitos as $conflito) {
            if (!empty($conflito['bloqueia'])) {
                $bloqueia = true;
                break;
            }
        }
        if ($bloqueia && trim((string) ($academico['erro'] ?? '')) !== '') {
            $conflitos[] = self::conflito('', 'academico', (string) $academico['erro'], true);
        }

        $criar = [];
        $atualizar = [];
        $importados = ['curso' => [], 'serie' => [], 'turma' => [], 'matricula' => []];
        $anos = [];
        $reservados = [];

        $cursos = is_array($academico['cursos'] ?? null) ? $academico['cursos'] : [];
        foreach ($cursos as $idLex => $curso) {
            if (!is_array($curso)) {
                continue;
            }
            $idLex = (string) $idLex;
            self::entidade('curso', $idLex, $curso, $vinculos, $criar, $atualizar, $importados);
        }

        $series = is_array($academico['series'] ?? null) ? $academico['series'] : [];
        foreach ($series as $idLex => $serie) {
            if (!is_array($serie)) {
                continue;
            }
            $idLex = (string) $idLex;
            $cursoLex = (string) ($serie['curso_lex'] ?? '');
            if ($cursoLex === '' || empty($importados['curso'][$cursoLex])) {
                $conflitos[] = self::conflito($idLex, 'serie', 'Série sem segmento importado.', true);
                $bloqueia = true;
                continue;
            }
            $nome = (string) ($serie['nome'] ?? '');
            $cursoLocal = (int) ($vinculos['curso'][$cursoLex]['id_local'] ?? 0);
            $idLocal = (int) ($vinculos['serie'][$idLex]['id_local'] ?? 0);
            if (self::serieOcupada($estado, $reservados, $cursoLocal, $cursoLex, $nome, $idLocal)) {
                $conflitos[] = self::conflito($idLex, 'serie', 'Série já existe neste curso.', true);
                $bloqueia = true;
                continue;
            }
            self::reservarSerie($reservados, $cursoLocal, $cursoLex, $nome, $idLocal);
            $acao = [
                'tipo' => 'serie',
                'id_lex' => $idLex,
                'nome' => $nome,
                'curso_lex' => $cursoLex,
            ];
            if ($idLocal > 0) {
                $acao['id_local'] = $idLocal;
                $atualizar[] = $acao;
            } else {
                $criar[] = $acao;
            }
            $importados['serie'][$idLex] = true;
        }

        $turmasPorAluno = [];
        $turmasPorProfessor = [];
        $turmas = is_array($academico['turmas'] ?? null) ? $academico['turmas'] : [];
        foreach ($turmas as $idLex => $turma) {
            if (!is_array($turma)) {
                continue;
            }
            $idLex = (string) $idLex;
            $serieLex = $turma['serie_lex'] ?? null;
            if (is_string($serieLex) && $serieLex !== '' && empty($importados['serie'][$serieLex])) {
                $conflitos[] = self::conflito($idLex, 'turma', 'Turma ligada a uma série que não foi importada.', true);
                $bloqueia = true;
                continue;
            }
            $cursoLex = (string) ($turma['curso_lex'] ?? '');
            if ($cursoLex !== '' && empty($importados['curso'][$cursoLex])) {
                $conflitos[] = self::conflito($idLex, 'turma', 'Turma ligada a um segmento que não foi importado.', true);
                $bloqueia = true;
                continue;
            }

            $ano = (int) ($turma['ano'] ?? 0);
            $anos[$ano] = true;
            $idLocal = (int) ($vinculos['turma'][$idLex]['id_local'] ?? 0);
            $acao = [
                'tipo' => 'turma',
                'id_lex' => $idLex,
                'nome' => (string) ($turma['nome'] ?? ''),
                'ano' => $ano,
                'serie_nome' => (string) ($turma['serie_nome'] ?? 'A definir'),
                'serie_lex' => is_string($serieLex) && $serieLex !== '' ? $serieLex : null,
                'curso_lex' => $cursoLex !== '' ? $cursoLex : null,
                'turno' => $turma['turno'] ?? null,
            ];
            if ($idLocal > 0) {
                $acao['id_local'] = $idLocal;
                $atualizar[] = $acao;
            } else {
                $criar[] = $acao;
            }
            $importados['turma'][$idLex] = true;

            foreach (is_array($turma['alunos'] ?? null) ? $turma['alunos'] : [] as $alunoLex) {
                $alunoLex = (string) $alunoLex;
                if (empty($alunosImportados[$alunoLex])) {
                    $conflitos[] = self::conflito($alunoLex, 'matricula', 'Aluno da turma não foi importado.', true);
                    $bloqueia = true;
                    continue;
                }
                $chave = $alunoLex . '|' . $idLex;
                if (isset($importados['matricula'][$chave])) {
                    continue;
                }
                if (self::matriculaOcupada($estado, $vinculos, $alunoLex, $idLex, $ano, $chave)) {
                    $conflitos[] = self::conflito($chave, 'matricula', 'Já existe matrícula deste aluno nesta turma e ano.', true);
                    $bloqueia = true;
                    continue;
                }
                $matriculaLocal = (int) ($vinculos['matricula'][$chave]['id_local'] ?? 0);
                $item = [
                    'tipo' => 'matricula',
                    'id_lex' => $chave,
                    'aluno_lex' => $alunoLex,
                    'turma_lex' => $idLex,
                    'ano' => $ano,
                ];
                if ($matriculaLocal > 0) {
                    $item['id_local'] = $matriculaLocal;
                    $atualizar[] = $item;
                } else {
                    $criar[] = $item;
                }
                $importados['matricula'][$chave] = true;
                $turmasPorAluno[$alunoLex][] = [
                    'turma_lex' => $idLex,
                    'ano' => $ano,
                    'serie_nome' => (string) ($turma['serie_nome'] ?? 'A definir'),
                ];
            }

            foreach (is_array($turma['professores'] ?? null) ? $turma['professores'] : [] as $professorLex) {
                $professorLex = (string) $professorLex;
                if (empty($professoresImportados[$professorLex])) {
                    $conflitos[] = self::conflito($professorLex, 'professor', 'Professor da turma não foi importado.', false);
                    continue;
                }
                $turmasPorProfessor[$professorLex][$idLex] = true;
            }
        }

        $alunos = [];
        foreach (array_keys($alunosImportados) as $alunoLex) {
            $escolha = self::escolherTurma($turmasPorAluno[$alunoLex] ?? []);
            $alunos[] = [
                'id_lex' => $alunoLex,
                'turma_lex' => $escolha['turma_lex'] ?? null,
                'serie' => $escolha['serie_nome'] ?? null,
            ];
        }

        $professores = [];
        foreach (array_keys($professoresImportados) as $professorLex) {
            $ids = array_keys($turmasPorProfessor[$professorLex] ?? []);
            sort($ids);
            $professores[] = [
                'id_lex' => $professorLex,
                'turmas_lex' => $ids,
            ];
        }

        $ausentes = self::ausentes($vinculos, $importados, ['curso', 'serie', 'turma']);
        $ausentesMatricula = self::ausentes($vinculos, $importados, ['matricula']);
        $podeInativar = (bool) ($academico['ok'] ?? false) && !$bloqueia;

        return [
            'criar' => $criar,
            'atualizar' => $atualizar,
            'anos' => array_keys($anos),
            'alunos' => $alunos,
            'professores' => $professores,
            'inativar' => $podeInativar ? $ausentes : [],
            'inativar_matriculas' => $podeInativar ? $ausentesMatricula : [],
            'inativacao_suspensa' => $podeInativar ? 0 : count($ausentes) + count($ausentesMatricula),
            'conflitos' => $conflitos,
            'ignorados' => $ignorados,
            'pode_inativar' => $podeInativar,
            'data_entrada' => self::dataEntrada((string) ($academico['sync_date'] ?? '')),
            'contagens' => self::contagens(
                $criar,
                $atualizar,
                $podeInativar ? array_merge($ausentes, $ausentesMatricula) : [],
                $conflitos,
                $ignorados
            ),
        ];
    }

    /**
     * Une o plano de pessoas e o acadêmico. Conflito em qualquer um suspende toda inativação.
     *
     * @param array<string,mixed> $pessoas
     * @param array<string,mixed> $academico
     * @return array<string,mixed>
     */
    public static function combinar(array $pessoas, array $academico): array
    {
        $ausentesPessoas = count($pessoas['inativar'] ?? [])
            + count($pessoas['inativar_vinculos'] ?? [])
            + (int) ($pessoas['inativacao_suspensa'] ?? 0);
        $ausentesAcademico = count($academico['inativar'] ?? [])
            + count($academico['inativar_matriculas'] ?? [])
            + (int) ($academico['inativacao_suspensa'] ?? 0);
        $pode = (bool) ($pessoas['pode_inativar'] ?? false) && (bool) ($academico['pode_inativar'] ?? false);
        if (!$pode) {
            $pessoas['inativar'] = [];
            $pessoas['inativar_vinculos'] = [];
            $academico['inativar'] = [];
            $academico['inativar_matriculas'] = [];
            $pessoas['inativacao_suspensa'] = $ausentesPessoas + $ausentesAcademico;
            $pessoas['pode_inativar'] = false;
        } else {
            $pessoas['inativacao_suspensa'] = 0;
        }

        $contagens = is_array($pessoas['contagens'] ?? null) ? $pessoas['contagens'] : [];
        $contagensAcademico = is_array($academico['contagens'] ?? null) ? $academico['contagens'] : [];
        foreach (['curso', 'serie', 'turma', 'matricula'] as $tipo) {
            $contagens[$tipo] = $contagensAcademico[$tipo] ?? ['criar' => 0, 'atualizar' => 0];
        }
        $contagens['inativados'] = count($pessoas['inativar'])
            + count($pessoas['inativar_vinculos'])
            + count($academico['inativar'])
            + count($academico['inativar_matriculas']);
        $contagens['conflitos'] = count($pessoas['conflitos'] ?? []) + count($academico['conflitos'] ?? []);
        $contagens['ignorados'] = count($pessoas['ignorados'] ?? []) + count($academico['ignorados'] ?? []);
        $pessoas['contagens'] = $contagens;
        $pessoas['conflitos'] = array_merge(
            is_array($pessoas['conflitos'] ?? null) ? $pessoas['conflitos'] : [],
            is_array($academico['conflitos'] ?? null) ? $academico['conflitos'] : []
        );
        $pessoas['ignorados'] = array_merge(
            is_array($pessoas['ignorados'] ?? null) ? $pessoas['ignorados'] : [],
            is_array($academico['ignorados'] ?? null) ? $academico['ignorados'] : []
        );
        $pessoas['academico'] = $academico;
        return $pessoas;
    }

    /**
     * @param array<string,mixed> $planoPessoas
     * @return array<string, bool>
     */
    private static function idsDoPlano(array $planoPessoas, string $tipo): array
    {
        $ids = [];
        $acoes = array_merge(
            is_array($planoPessoas['criar'] ?? null) ? $planoPessoas['criar'] : [],
            is_array($planoPessoas['atualizar'] ?? null) ? $planoPessoas['atualizar'] : []
        );
        foreach ($acoes as $acao) {
            if (!is_array($acao) || (string) ($acao['tipo'] ?? '') !== $tipo) {
                continue;
            }
            $id = trim((string) ($acao['id_lex'] ?? ''));
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        return $ids;
    }

    /**
     * @param array<string,mixed> $dados
     * @param array<string,mixed> $vinculos
     * @param list<array<string,mixed>> $criar
     * @param list<array<string,mixed>> $atualizar
     * @param array<string, array<string, bool>> $importados
     */
    private static function entidade(
        string $tipo,
        string $idLex,
        array $dados,
        array $vinculos,
        array &$criar,
        array &$atualizar,
        array &$importados
    ): void {
        $idLocal = (int) ($vinculos[$tipo][$idLex]['id_local'] ?? 0);
        $acao = ['tipo' => $tipo, 'id_lex' => $idLex, 'nome' => (string) ($dados['nome'] ?? '')];
        if ($idLocal > 0) {
            $acao['id_local'] = $idLocal;
            $atualizar[] = $acao;
        } else {
            $criar[] = $acao;
        }
        $importados[$tipo][$idLex] = true;
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<string, int> $reservados
     */
    private static function serieOcupada(
        array $estado,
        array $reservados,
        int $cursoLocal,
        string $cursoLex,
        string $nome,
        int $idLocal
    ): bool {
        $nome = self::normalizar($nome);
        if ($nome === '') {
            return false;
        }
        if ($cursoLocal > 0) {
            $chave = $cursoLocal . '|' . $nome;
            $mapa = is_array($estado['serie_nomes'] ?? null) ? $estado['serie_nomes'] : [];
            $dono = (int) ($mapa[$chave] ?? 0);
            if ($dono > 0 && $dono !== $idLocal) {
                return true;
            }
        }
        $reserva = 'lex:' . $cursoLex . '|' . $nome;
        $reservado = (int) ($reservados[$reserva] ?? 0);
        return $reservado !== 0 && $reservado !== ($idLocal > 0 ? $idLocal : 0);
    }

    /**
     * @param array<string, int> $reservados
     */
    private static function reservarSerie(array &$reservados, int $cursoLocal, string $cursoLex, string $nome, int $id): void
    {
        if ($id < 1) {
            $id = -1 - count($reservados);
        }
        $reservados['lex:' . $cursoLex . '|' . self::normalizar($nome)] = $id;
        if ($cursoLocal > 0) {
            $reservados[$cursoLocal . '|' . self::normalizar($nome)] = $id;
        }
    }

    /**
     * @param array<string,mixed> $estado
     * @param array<string,mixed> $vinculos
     */
    private static function matriculaOcupada(
        array $estado,
        array $vinculos,
        string $alunoLex,
        string $turmaLex,
        int $ano,
        string $chaveLex
    ): bool {
        $alunoId = (int) ($vinculos['aluno'][$alunoLex]['id_local'] ?? 0);
        $turmaId = (int) ($vinculos['turma'][$turmaLex]['id_local'] ?? 0);
        $anoId = (int) (is_array($estado['anos'] ?? null) ? ($estado['anos'][$ano] ?? 0) : 0);
        if ($alunoId < 1 || $turmaId < 1 || $anoId < 1) {
            return false;
        }
        $mapa = is_array($estado['matriculas'] ?? null) ? $estado['matriculas'] : [];
        $existente = (int) ($mapa[$alunoId . '|' . $turmaId . '|' . $anoId] ?? 0);
        if ($existente < 1) {
            return false;
        }
        $nossa = (int) ($vinculos['matricula'][$chaveLex]['id_local'] ?? 0);
        return $nossa !== $existente;
    }

    /**
     * @param list<array{turma_lex:string,ano:int,serie_nome:string}> $turmas
     * @return array{turma_lex:?string,serie_nome:?string}
     */
    private static function escolherTurma(array $turmas): array
    {
        if ($turmas === []) {
            return ['turma_lex' => null, 'serie_nome' => null];
        }
        usort($turmas, static function (array $a, array $b): int {
            if ($a['ano'] !== $b['ano']) {
                return $b['ano'] <=> $a['ano'];
            }
            return strcmp($a['turma_lex'], $b['turma_lex']);
        });
        return [
            'turma_lex' => $turmas[0]['turma_lex'],
            'serie_nome' => $turmas[0]['serie_nome'],
        ];
    }

    /**
     * @param array<string,mixed> $vinculos
     * @param array<string, array<string, bool>> $importados
     * @param list<string> $tipos
     * @return list<array{tipo:string,id_lex:string,id_local:int}>
     */
    private static function ausentes(array $vinculos, array $importados, array $tipos): array
    {
        $lista = [];
        foreach ($tipos as $tipo) {
            $mapa = is_array($vinculos[$tipo] ?? null) ? $vinculos[$tipo] : [];
            foreach ($mapa as $idLex => $vinculo) {
                if (!is_array($vinculo) || ($vinculo['status'] ?? 'ativo') !== 'ativo') {
                    continue;
                }
                if (!empty($importados[$tipo][(string) $idLex])) {
                    continue;
                }
                $lista[] = [
                    'tipo' => $tipo,
                    'id_lex' => (string) $idLex,
                    'id_local' => (int) ($vinculo['id_local'] ?? 0),
                ];
            }
        }
        return $lista;
    }

    private static function dataEntrada(string $syncDate): string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', trim($syncDate), $m)) {
            return $m[1];
        }
        return date('Y-m-d');
    }

    private static function normalizar(string $valor): string
    {
        $valor = trim($valor);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($valor);
        }
        return strtolower($valor);
    }

    /**
     * @return array{id_lex:string,tipo:string,mensagem:string,bloqueia:bool}
     */
    private static function conflito(string $idLex, string $tipo, string $mensagem, bool $bloqueia): array
    {
        return [
            'id_lex' => $idLex,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'bloqueia' => $bloqueia,
        ];
    }

    /**
     * @param list<array<string,mixed>> $criar
     * @param list<array<string,mixed>> $atualizar
     * @param list<array<string,mixed>> $inativar
     * @param list<array<string,mixed>> $conflitos
     * @param list<array<string,mixed>> $ignorados
     * @return array<string,mixed>
     */
    private static function contagens(array $criar, array $atualizar, array $inativar, array $conflitos, array $ignorados): array
    {
        $contagem = [];
        foreach (['curso', 'serie', 'turma', 'matricula'] as $tipo) {
            $contagem[$tipo] = ['criar' => 0, 'atualizar' => 0];
        }
        foreach ($criar as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            if (isset($contagem[$tipo])) {
                $contagem[$tipo]['criar']++;
            }
        }
        foreach ($atualizar as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            if (isset($contagem[$tipo])) {
                $contagem[$tipo]['atualizar']++;
            }
        }
        $contagem['inativados'] = count($inativar);
        $contagem['conflitos'] = count($conflitos);
        $contagem['ignorados'] = count($ignorados);
        return $contagem;
    }
}
