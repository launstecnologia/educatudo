<?php

namespace App\Modulos\ModelosDocumentos\Services;


use App\Core\Database;
/**
 * Dados reais (turma, aluno, componentes e notas) para a folha do editor.
 * O HTML do modelo continua com {{tokens}}; só a demonstração substitui.
 */
class DemonstracaoDocumentoService
{
    /** @var array<string, true> */
    private const TABELAS = [
        'turmas' => true,
        'serie' => true,
        'curso' => true,
        'ano_letivo' => true,
        'matricula' => true,
        'alunos' => true,
        'materias' => true,
        'matrizes_curriculares_componentes' => true,
        'unidades' => true,
        'boletim_resultados_gerados' => true,
        'boletim_regras' => true,
        'boletins' => true,
    ];

    /** @var array<string, bool> */
    private array $colunas = [];

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function pacote(int $turmaId, int $alunoId, string $chave, int $boletimId = 0): array
    {
        $chave = $this->chaveGrade($chave);
        $turmas = $this->listarTurmas();
        $alunos = $turmaId > 0 ? $this->listarAlunos($turmaId) : [];
        $anoLista = 0;
        if ($turmaId > 0) {
            $turmaLista = $this->turmaPorId($turmaId);
            $anoLista = is_array($turmaLista) ? (int) ($turmaLista['ano_letivo'] ?? 0) : 0;
        }
        $out = [
            'ok' => true,
            'turmas' => $turmas,
            'alunos' => $alunos,
            'boletins' => $this->listarBoletinsAtivos($anoLista > 0 ? $anoLista : null),
            'boletim_id' => $boletimId > 0 ? $boletimId : null,
            'html_quadro' => '',
            'componentes' => [],
            'periodos' => $this->periodosPublicos($chave),
            'vars' => null,
            'resumo' => '',
        ];
        if ($turmaId <= 0 || $alunoId <= 0) {
            return $out;
        }
        $turma = $this->turmaPorId($turmaId);
        $aluno = $this->alunoDaTurma($alunoId, $turmaId);
        if ($turma === null || $aluno === null) {
            $out['resumo'] = 'Aluno não encontrado nesta turma.';
            return $out;
        }
        $ano = (int) ($aluno['ano_letivo'] ?? 0);
        if ($ano <= 0) {
            $ano = (int) ($turma['ano_letivo'] ?? 0);
        }
        if ($ano > 0) {
            $out['boletins'] = $this->listarBoletinsAtivos($ano);
        }
        $linhas = $this->linhasNotas($alunoId, $turmaId, $ano, (int) ($turma['matriz_curricular_id'] ?? 0));
        $atalhos = $this->atalhosDeComponentes($linhas, $chave);
        $vars = ModeloDocumentoService::varsExemplo();
        $vars = $this->sobreporEscola($vars);
        $vars = $this->sobreporAlunoTurma($vars, $aluno, $turma);
        $vars['_grade_fonte'] = ['componentes' => $linhas];
        $vars = GradeSoltaService::completar($this->gradeDaDemonstracao($chave, $atalhos), $vars);
        unset($vars['_grade_fonte']);
        $vars['componentes_serie_html'] = $this->htmlComponentes($linhas);
        $quadroBoletim = $boletimId > 0 ? $this->htmlQuadroDoBoletim($alunoId, $boletimId) : '';
        if ($quadroBoletim !== '') {
            $vars['quadro_notas_html'] = $quadroBoletim;
            $out['html_quadro'] = $quadroBoletim;
            $out['boletim_id'] = $boletimId;
        } else {
            $vars['quadro_notas_html'] = ModeloDocumentoService::htmlQuadroDeLinhasBimestrais($linhas);
            if ($boletimId > 0) {
                $out['resumo'] = 'Boletim sem quadro de impressão montado. Construa o boletim no assistente e conclua.';
            }
        }
        $vars['historico_html'] = $this->htmlHistoricoDoAno($linhas, $ano, $vars);
        $out['linhas'] = $linhas;
        $out['vars'] = $this->variaveisTexto($vars);
        $out['componentes'] = $atalhos;
        if (($out['resumo'] ?? '') === '') {
            $out['resumo'] = trim((string) ($aluno['nome_exibir'] ?? '')) . ' · ' . $this->rotuloTurma($turma);
        }
        return $out;
    }

    /**
     * Mesmos Modelos de Boletim da tela Acadêmico → Boletim (ativos, não excluídos).
     * Prioriza o ano da turma, mas lista todos para o select não ficar vazio.
     *
     * @return list<array{id:int,nome:string,rotulo:string,ano_letivo:?int,finalidade:string}>
     */
    public function listarBoletinsAtivos(?int $anoLetivo = null): array
    {
        $rows = [];
        try {
            if (class_exists(\App\Modulos\Boletins\Models\Boletim::class)) {
                $rows = (new \App\Modulos\Boletins\Models\Boletim())->listar(true) ?: [];
            }
        } catch (\Throwable $e) {
            $rows = [];
        }
        if ($rows === [] && $this->tabelaExiste('boletins')) {
            $sql = 'SELECT id, nome, ano_letivo, finalidade
                    FROM boletins
                    WHERE ativo = 1';
            if ($this->temColuna('boletins', 'excluido_em')) {
                $sql .= ' AND excluido_em IS NULL';
            }
            $sql .= ' ORDER BY ano_letivo DESC, finalidade ASC, nome ASC, id DESC LIMIT 100';
            try {
                $rows = $this->db->fetchAll($sql) ?: [];
            } catch (\Throwable $e) {
                return [];
            }
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($id <= 0 || $nome === '') {
                continue;
            }
            $ano = isset($row['ano_letivo']) && $row['ano_letivo'] !== null && $row['ano_letivo'] !== ''
                ? (int) $row['ano_letivo']
                : null;
            $finalidade = (string) ($row['finalidade'] ?? 'oficial');
            $rotulo = $nome;
            if ($ano !== null && $ano > 0) {
                $rotulo .= ' · ' . $ano;
            }
            if ($finalidade === 'complementar') {
                $rotulo .= ' · extra';
            }
            $out[] = [
                'id' => $id,
                'nome' => $nome,
                'rotulo' => $rotulo,
                'ano_letivo' => $ano,
                'finalidade' => $finalidade,
                '_prio' => ($anoLetivo !== null && $anoLetivo > 0 && $ano === $anoLetivo) ? 0 : 1,
            ];
        }
        usort($out, static function (array $a, array $b): int {
            $cmp = ($a['_prio'] ?? 1) <=> ($b['_prio'] ?? 1);
            if ($cmp !== 0) {
                return $cmp;
            }
            $anoA = (int) ($a['ano_letivo'] ?? 0);
            $anoB = (int) ($b['ano_letivo'] ?? 0);
            if ($anoA !== $anoB) {
                return $anoB <=> $anoA;
            }
            return strcmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? ''));
        });
        foreach ($out as &$item) {
            unset($item['_prio']);
        }
        unset($item);

        return $out;
    }

    private function htmlQuadroDoBoletim(int $alunoId, int $boletimId): string
    {
        if ($alunoId <= 0 || $boletimId <= 0) {
            return '';
        }
        try {
            if (!class_exists(\App\Modulos\VidaEscolar\Services\ProntuarioVidaEscolarService::class)) {
                return '';
            }
            $html = (new \App\Modulos\VidaEscolar\Services\ProntuarioVidaEscolarService())
                ->htmlQuadroPorBoletim($alunoId, $boletimId);
            return is_string($html) ? trim($html) : '';
        } catch (\Throwable $e) {
            error_log('DemonstracaoDocumento quadro boletim: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * @return list<array{id:int,rotulo:string}>
     */
    private function listarTurmas(): array
    {
        if (!$this->tabelaExiste('turmas')) {
            return [];
        }
        $temSerieId = $this->temColuna('turmas', 'serie_id') && $this->tabelaExiste('serie');
        $temCurso = $this->temColuna('turmas', 'curso_novo_id') && $this->tabelaExiste('curso');
        $temMatriz = $this->temColuna('turmas', 'matriz_curricular_id');
        $temAno = $this->temColuna('turmas', 'ano_letivo_id') && $this->tabelaExiste('ano_letivo');
        $temSerieTexto = $this->temColuna('turmas', 'serie');
        $temAtivo = $this->temColuna('turmas', 'ativo');

        $sql = 'SELECT t.id, t.nome';
        if ($temSerieTexto) {
            $sql .= ', t.serie AS serie_texto';
        }
        if ($temSerieId) {
            $sql .= ', s.nome AS serie_nome';
        }
        if ($temCurso) {
            $sql .= ', c.nome AS curso_nome';
        }
        if ($temMatriz) {
            $sql .= ', t.matriz_curricular_id';
        }
        if ($temAno) {
            $sql .= ', al.ano AS ano_letivo';
        }
        $sql .= ' FROM turmas t';
        if ($temSerieId) {
            $sql .= ' LEFT JOIN serie s ON s.id = t.serie_id';
        }
        if ($temCurso) {
            $sql .= ' LEFT JOIN curso c ON c.id = t.curso_novo_id';
        }
        if ($temAno) {
            $sql .= ' LEFT JOIN ano_letivo al ON al.id = t.ano_letivo_id';
        }
        if ($temAtivo) {
            $sql .= ' WHERE t.ativo = 1';
        }
        $sql .= $temAno
            ? ' ORDER BY al.ano DESC, t.nome ASC LIMIT 300'
            : ' ORDER BY t.nome ASC LIMIT 300';

        try {
            $rows = $this->db->fetchAll($sql) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = ['id' => $id, 'rotulo' => $this->rotuloTurma($row)];
        }
        return $out;
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    private function listarAlunos(int $turmaId): array
    {
        if ($turmaId <= 0 || !$this->tabelaExiste('matricula') || !$this->tabelaExiste('alunos')) {
            return [];
        }
        $sql = 'SELECT a.id, a.nome';
        if ($this->temColuna('alunos', 'nome_social')) {
            $sql .= ', a.nome_social';
        }
        $sql .= ' FROM matricula m INNER JOIN alunos a ON a.id = m.aluno_id WHERE m.turma_id = :turma';
        if ($this->temColuna('matricula', 'status')) {
            $sql .= " AND m.status IN ('ativa', 'concluido', 'transferido')";
        }
        $sql .= ' ORDER BY a.nome ASC LIMIT 400';
        try {
            $rows = $this->db->fetchAll($sql, ['turma' => $turmaId]) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        $vistos = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $social = trim((string) ($row['nome_social'] ?? ''));
            $nome = $social !== '' ? $social : trim((string) ($row['nome'] ?? ''));
            $out[] = ['id' => $id, 'nome' => $nome !== '' ? $nome : 'Aluno ' . $id];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function turmaPorId(int $turmaId): ?array
    {
        $rows = $this->listarTurmasDetalhe($turmaId);
        $turma = $rows[0] ?? null;
        return is_array($turma) ? $turma : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarTurmasDetalhe(int $turmaId): array
    {
        if (!$this->tabelaExiste('turmas')) {
            return [];
        }
        $temSerieId = $this->temColuna('turmas', 'serie_id') && $this->tabelaExiste('serie');
        $temCurso = $this->temColuna('turmas', 'curso_novo_id') && $this->tabelaExiste('curso');
        $temMatriz = $this->temColuna('turmas', 'matriz_curricular_id');
        $temAno = $this->temColuna('turmas', 'ano_letivo_id') && $this->tabelaExiste('ano_letivo');
        $temSerieTexto = $this->temColuna('turmas', 'serie');
        $temTurno = $this->temColuna('turmas', 'turno');

        $sql = 'SELECT t.id, t.nome';
        if ($temSerieTexto) {
            $sql .= ', t.serie AS serie_texto';
        }
        if ($temSerieId) {
            $sql .= ', s.nome AS serie_nome';
        }
        if ($temCurso) {
            $sql .= ', c.nome AS curso_nome';
        }
        if ($temMatriz) {
            $sql .= ', t.matriz_curricular_id';
        }
        if ($temAno) {
            $sql .= ', al.ano AS ano_letivo';
        }
        if ($temTurno) {
            $sql .= ', t.turno';
        }
        $sql .= ' FROM turmas t';
        if ($temSerieId) {
            $sql .= ' LEFT JOIN serie s ON s.id = t.serie_id';
        }
        if ($temCurso) {
            $sql .= ' LEFT JOIN curso c ON c.id = t.curso_novo_id';
        }
        if ($temAno) {
            $sql .= ' LEFT JOIN ano_letivo al ON al.id = t.ano_letivo_id';
        }
        $sql .= ' WHERE t.id = :id LIMIT 1';
        try {
            $row = $this->db->fetch($sql, ['id' => $turmaId]);
            return is_array($row) ? [$row] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function alunoDaTurma(int $alunoId, int $turmaId): ?array
    {
        if (!$this->tabelaExiste('matricula') || !$this->tabelaExiste('alunos')) {
            return null;
        }
        $cols = ['a.id', 'a.nome'];
        foreach (['nome_social', 'cpf', 'rg', 'data_nasc', 'email', 'ra', 'codigo_aluno', 'sexo', 'celular', 'telefone'] as $col) {
            if ($this->temColuna('alunos', $col)) {
                $cols[] = 'a.' . $col;
            }
        }
        foreach (['numero_chamada', 'status', 'data_entrada', 'data_saida'] as $col) {
            if ($this->temColuna('matricula', $col)) {
                $cols[] = 'm.' . $col;
            }
        }
        $joinAno = '';
        if ($this->temColuna('matricula', 'ano_letivo_id') && $this->tabelaExiste('ano_letivo')) {
            $cols[] = 'al.ano AS ano_letivo';
            $joinAno = ' LEFT JOIN ano_letivo al ON al.id = m.ano_letivo_id';
        }
        $sql = 'SELECT ' . implode(', ', $cols)
            . ' FROM matricula m INNER JOIN alunos a ON a.id = m.aluno_id'
            . $joinAno
            . ' WHERE m.aluno_id = :aluno AND m.turma_id = :turma LIMIT 1';
        try {
            $row = $this->db->fetch($sql, ['aluno' => $alunoId, 'turma' => $turmaId]);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($row)) {
            return null;
        }
        $social = trim((string) ($row['nome_social'] ?? ''));
        $row['nome_exibir'] = $social !== '' ? $social : trim((string) ($row['nome'] ?? ''));
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linhasNotas(int $alunoId, int $turmaId, int $ano, int $matrizId): array
    {
        $porId = [];
        foreach ($this->componentesDaMatriz($matrizId) as $comp) {
            $mid = (int) ($comp['materia_id'] ?? 0);
            $nome = trim((string) ($comp['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $chave = $mid > 0 ? 'id:' . $mid : 'nome:' . $this->normalizar($nome);
            $porId[$chave] = [
                'materia_id' => $mid,
                'nome' => $nome,
                'b1' => null,
                'b2' => null,
                'b3' => null,
                'b4' => null,
                'media_final' => null,
            ];
        }
        if ($ano <= 0) {
            $ano = (int) date('Y');
        }
        foreach ($this->notasBimestrais($alunoId, $turmaId, $ano) as $nota) {
            $mid = (int) ($nota['materia_id'] ?? 0);
            $nome = trim((string) ($nota['materia_nome'] ?? ''));
            $bim = (int) ($nota['bimestre'] ?? 0);
            if ($bim < 1 || $bim > 4 || $nome === '') {
                continue;
            }
            $chave = $mid > 0 && isset($porId['id:' . $mid]) ? 'id:' . $mid : 'nome:' . $this->normalizar($nome);
            if (!isset($porId[$chave])) {
                $porId[$chave] = [
                    'materia_id' => $mid,
                    'nome' => $nome,
                    'b1' => null,
                    'b2' => null,
                    'b3' => null,
                    'b4' => null,
                    'media_final' => null,
                ];
            }
            $valor = $this->numero($nota['media_final'] ?? null);
            if ($valor === null && isset($nota['notas_json'])) {
                $json = json_decode((string) $nota['notas_json'], true);
                if (is_array($json)) {
                    foreach (['media_final', 'media_bim', 'media'] as $chaveMedia) {
                        if (isset($json[$chaveMedia]) && is_numeric($json[$chaveMedia])) {
                            $valor = (float) $json[$chaveMedia];
                            break;
                        }
                    }
                }
            }
            if ($valor !== null && $porId[$chave]['b' . $bim] === null) {
                $porId[$chave]['b' . $bim] = $valor;
            }
        }
        $linhas = [];
        foreach ($porId as $linha) {
            $vals = [];
            for ($b = 1; $b <= 4; $b++) {
                if (is_numeric($linha['b' . $b] ?? null)) {
                    $vals[] = (float) $linha['b' . $b];
                }
            }
            $linha['media_final'] = $vals !== [] ? array_sum($vals) / count($vals) : null;
            $linhas[] = $linha;
        }
        return $linhas;
    }

    /**
     * @return list<array{materia_id:int,nome:string}>
     */
    private function componentesDaMatriz(int $matrizId): array
    {
        if ($matrizId <= 0 || !$this->tabelaExiste('matrizes_curriculares_componentes') || !$this->tabelaExiste('materias')) {
            return [];
        }
        $ordem = $this->temColuna('matrizes_curriculares_componentes', 'ordem_boletim')
            ? 'c.ordem_boletim ASC, m.nome ASC'
            : 'm.nome ASC';
        try {
            $rows = $this->db->fetchAll(
                'SELECT c.materia_id, m.nome
                 FROM matrizes_curriculares_componentes c
                 INNER JOIN materias m ON m.id = c.materia_id
                 WHERE c.matriz_id = :id
                 ORDER BY ' . $ordem,
                ['id' => $matrizId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'materia_id' => (int) ($row['materia_id'] ?? 0),
                'nome' => trim((string) ($row['nome'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notasBimestrais(int $alunoId, int $turmaId, int $ano): array
    {
        if (!$this->tabelaExiste('boletim_resultados_gerados') || !$this->tabelaExiste('boletim_regras')) {
            return [];
        }
        $filtros = [
            'g.aluno_id = :aluno',
            'r.ano_letivo = :ano',
            'r.bimestre BETWEEN 1 AND 4',
        ];
        if ($this->temColuna('boletim_resultados_gerados', 'preview')) {
            $filtros[] = 'g.preview = 0';
        }
        if ($this->temColuna('boletim_resultados_gerados', 'vigente')) {
            $filtros[] = 'g.vigente = 1';
        }
        $colunas = 'g.materia_id, g.materia_nome, g.media_final, r.bimestre';
        if ($this->temColuna('boletim_resultados_gerados', 'notas_json')) {
            $colunas .= ', g.notas_json';
        }
        $sql = 'SELECT ' . $colunas . '
                FROM boletim_resultados_gerados g
                INNER JOIN boletim_regras r ON r.id = g.regra_id
                WHERE ' . implode(' AND ', $filtros);
        $params = ['aluno' => $alunoId, 'ano' => $ano];
        if ($this->tabelaExiste('matricula') && $this->temColuna('matricula', 'ano_letivo_id') && $this->tabelaExiste('ano_letivo')) {
            $sql .= ' AND EXISTS (
                        SELECT 1 FROM matricula m
                        INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
                        WHERE m.aluno_id = :aluno_mat AND m.turma_id = :turma AND al.ano = :ano_mat
                      )';
            $params['aluno_mat'] = $alunoId;
            $params['turma'] = $turmaId;
            $params['ano_mat'] = $ano;
        }
        $sql .= ' ORDER BY r.bimestre ASC, g.id ASC';
        try {
            return $this->db->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $aluno
     * @param array<string, mixed> $turma
     * @return array<string, mixed>
     */
    private function sobreporAlunoTurma(array $vars, array $aluno, array $turma): array
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nome = trim((string) ($aluno['nome_exibir'] ?? ''));
        $civil = trim((string) ($aluno['nome'] ?? ''));
        $serie = $this->serieDaTurma($turma);
        $turmaNome = trim((string) ($turma['nome'] ?? ''));
        $curso = trim((string) ($turma['curso_nome'] ?? ''));
        $ano = trim((string) ($turma['ano_letivo'] ?? ''));
        $nasc = $this->dataBr((string) ($aluno['data_nasc'] ?? ''));
        $cpf = trim((string) ($aluno['cpf'] ?? ''));
        $tel = trim((string) ($aluno['celular'] ?? $aluno['telefone'] ?? ''));
        $vars['aluno_nome'] = $esc($nome !== '' ? $nome : '—');
        $vars['aluno_nome_civil'] = $esc($civil !== '' ? $civil : '—');
        $vars['aluno_cpf'] = $esc($cpf !== '' ? $cpf : '—');
        $vars['aluno_rg'] = $esc(trim((string) ($aluno['rg'] ?? '')) ?: '—');
        $vars['aluno_cpf_frase'] = $cpf !== '' ? ', inscrito(a) no CPF sob o nº ' . $esc($cpf) : '';
        $vars['aluno_data_nasc'] = $esc($nasc);
        $vars['aluno_nasc_frase'] = $nasc !== '—' ? ', nascido(a) em ' . $esc($nasc) : '';
        $vars['aluno_email'] = $esc(trim((string) ($aluno['email'] ?? '')) ?: '—');
        $vars['aluno_telefone'] = $esc($tel !== '' ? $tel : '—');
        $vars['aluno_codigo'] = $esc(trim((string) ($aluno['codigo_aluno'] ?? '')) ?: '—');
        $vars['aluno_ra'] = $esc(trim((string) ($aluno['ra'] ?? '')) ?: '—');
        $vars['aluno_sexo'] = $esc($this->rotuloSexo((string) ($aluno['sexo'] ?? '')));
        $vars['turma_nome'] = $esc($turmaNome !== '' ? $turmaNome : '—');
        $vars['turma_frase'] = $turmaNome !== ''
            ? ' na turma <span class="destaque">' . $esc($turmaNome) . '</span>'
                . ($serie !== '' ? ' (' . $esc($serie) . ')' : '')
            : '';
        $vars['serie'] = $esc($serie !== '' ? $serie : '—');
        $vars['organizacao'] = $esc($serie !== '' ? $serie : '—');
        $vars['curso_nome'] = $esc($curso !== '' ? $curso : '—');
        $vars['etapa'] = $esc($curso !== '' ? $curso : ($serie !== '' ? $serie : '—'));
        $vars['ano_letivo'] = $esc($ano !== '' ? $ano : '—');
        $vars['ano'] = $esc($ano !== '' ? $ano : '—');
        $vars['numero_chamada'] = $esc(trim((string) ($aluno['numero_chamada'] ?? '')) ?: '—');
        $vars['turno'] = $esc($this->rotuloTurno((string) ($turma['turno'] ?? '')));
        $status = (string) ($aluno['status'] ?? '');
        $vars['situacao_matricula'] = $esc(match ($status) {
            'ativa' => 'Matrícula ativa',
            'concluido' => 'Concluído',
            'transferido' => 'Transferido',
            default => $status !== '' ? $status : '—',
        });
        $vars['data_entrada'] = $esc($this->dataBr((string) ($aluno['data_entrada'] ?? '')));
        $vars['data_saida'] = $esc($this->dataBr((string) ($aluno['data_saida'] ?? '')));
        return $vars;
    }

    /**
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    private function sobreporEscola(array $vars): array
    {
        if (!$this->tabelaExiste('unidades')) {
            return $vars;
        }
        $campos = [];
        foreach (['nome', 'nome_fantasia', 'razao_social', 'cnpj', 'telefone', 'cidade', 'municipio', 'endereco', 'logradouro', 'numero', 'bairro', 'nre'] as $col) {
            if ($this->temColuna('unidades', $col)) {
                $campos[] = $col;
            }
        }
        if ($campos === []) {
            return $vars;
        }
        $ordem = $this->temColuna('unidades', 'ativo') ? ' WHERE ativo = 1 ORDER BY id ASC' : ' ORDER BY id ASC';
        try {
            $row = $this->db->fetch('SELECT ' . implode(', ', $campos) . ' FROM unidades' . $ordem . ' LIMIT 1');
        } catch (\Throwable $e) {
            return $vars;
        }
        if (!is_array($row)) {
            return $vars;
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nome = trim((string) ($row['nome_fantasia'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($row['nome'] ?? ''));
        }
        if ($nome !== '') {
            $vars['escola_nome'] = $esc($nome);
        }
        $cnpj = trim((string) ($row['cnpj'] ?? ''));
        if ($cnpj !== '') {
            $vars['escola_cnpj'] = $esc('CNPJ: ' . $cnpj);
            $vars['escola_cnpj_numero'] = $esc($cnpj);
            $vars['cnpj_layout'] = $esc($cnpj);
        }
        $end = trim(implode(', ', array_filter([
            trim((string) ($row['logradouro'] ?? $row['endereco'] ?? '')),
            trim((string) ($row['numero'] ?? '')),
            trim((string) ($row['bairro'] ?? '')),
            trim((string) ($row['cidade'] ?? $row['municipio'] ?? '')),
        ], static fn ($v) => $v !== '')));
        if ($end !== '') {
            $vars['escola_endereco'] = $esc($end);
        }
        $tel = trim((string) ($row['telefone'] ?? ''));
        if ($tel !== '') {
            $vars['escola_telefone'] = $esc($tel);
        }
        $cidade = trim((string) ($row['cidade'] ?? $row['municipio'] ?? ''));
        if ($cidade !== '') {
            $vars['escola_municipio'] = $esc($cidade);
        }
        $nre = trim((string) ($row['nre'] ?? ''));
        if ($nre !== '') {
            $vars['escola_nre'] = $esc($nre);
        }
        $razao = trim((string) ($row['razao_social'] ?? ''));
        if ($razao !== '') {
            $vars['razao_social'] = $esc($razao);
            $vars['entidade_mantenedora'] = $esc($razao);
        }
        return $vars;
    }

    /**
     * @param list<array<string, mixed>> $linhas
     */
    public static function htmlListaComponentes(array $linhas): string
    {
        if ($linhas === []) {
            return '<p>Nenhum componente curricular nesta série.</p>';
        }
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<table class="dados"><tr><td class="label">Componente curricular</td></tr>';
        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }
            $nome = trim((string) ($linha['nome'] ?? $linha['materia_nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $html .= '<tr><td>' . $esc($nome) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param list<array<string, mixed>> $linhas
     */
    private function htmlComponentes(array $linhas): string
    {
        return self::htmlListaComponentes($linhas);
    }

    /**
     * Trajetória de um ano, nas mesmas colunas do histórico de exemplo.
     * Os textos de $vars já vêm escapados.
     *
     * @param list<array<string, mixed>> $linhas
     * @param array<string, mixed> $vars
     */
    private function htmlHistoricoDoAno(array $linhas, int $ano, array $vars): string
    {
        $html = '<table class="dados"><thead><tr>'
            . '<th>Ano</th><th>Série</th><th>Estabelecimento</th><th>Município</th><th>Resultado</th><th>CH</th>'
            . '</tr></thead><tbody>';
        if ($linhas === []) {
            $html .= '<tr><td colspan="6">Sem componentes lançados neste ano.</td></tr>';
        } else {
            $anoTxt = $ano > 0 ? (string) $ano : '—';
            $html .= '<tr><td>' . htmlspecialchars($anoTxt, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . (string) ($vars['serie'] ?? '—') . '</td>'
                . '<td>' . (string) ($vars['escola_nome'] ?? '—') . '</td>'
                . '<td>' . (string) ($vars['escola_municipio'] ?? '—') . '</td>'
                . '<td>Em curso</td><td>—</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    public static function chaveGradeDoCodigo(string $codigo): string
    {
        $codigo = strtolower($codigo);
        if (str_contains($codigo, '1127a')) {
            return '1127a';
        }
        if (str_contains($codigo, '1127b')) {
            return '1127b';
        }
        if (str_contains($codigo, '1128')) {
            return '1128';
        }

        return '1127';
    }

    /**
     * @param list<array<string, mixed>> $linhas
     * @param list<array<string, mixed>> $periodos
     */
    private function htmlQuadroSimples(array $linhas, array $periodos): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<table class="dados quadro-notas"><thead><tr><th>Componente</th>';
        foreach ($periodos as $periodo) {
            $html .= '<th>' . $esc($periodo['rotulo'] ?? '') . '</th>';
        }
        $html .= '<th>Média</th></tr></thead><tbody>';
        if ($linhas === []) {
            $html .= '<tr><td colspan="' . (2 + count($periodos)) . '">Sem componentes nesta série.</td></tr>';
        }
        foreach ($linhas as $linha) {
            $html .= '<tr><td>' . $esc($linha['nome'] ?? '') . '</td>';
            $medias = [];
            foreach ($periodos as $periodo) {
                $vals = [];
                foreach (($periodo['bimestres'] ?? []) as $b) {
                    $k = 'b' . (int) $b;
                    if (is_numeric($linha[$k] ?? null)) {
                        $vals[] = (float) $linha[$k];
                    }
                }
                if ($vals === []) {
                    $html .= '<td>—</td>';
                    continue;
                }
                $media = array_sum($vals) / count($vals);
                $medias[] = $media;
                $html .= '<td>' . $esc($this->formatarNota($media)) . '</td>';
            }
            $final = $medias !== [] ? array_sum($medias) / count($medias) : null;
            $html .= '<td>' . $esc($final === null ? '—' : $this->formatarNota($final)) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    /**
     * A grade fixa não conhece disciplina fora do catálogo. O alias é o nome,
     * o mesmo slug que o boletim editável usa em {{nota_slug_n}}.
     *
     * @param list<array<string, mixed>> $atalhos
     * @return array<string, mixed>
     */
    private function gradeDaDemonstracao(string $chave, array $atalhos): array
    {
        $grade = GradeSoltaService::mapa($chave);
        $componentes = is_array($grade['componentes'] ?? null) ? $grade['componentes'] : [];
        foreach ($atalhos as $atalho) {
            if (!is_array($atalho)) {
                continue;
            }
            $slug = (string) ($atalho['slug'] ?? '');
            $nome = trim((string) ($atalho['nome'] ?? ''));
            if ($slug === '' || $nome === '') {
                continue;
            }
            $achou = false;
            foreach ($componentes as $i => $comp) {
                if (!is_array($comp) || (string) ($comp['slug'] ?? '') !== $slug) {
                    continue;
                }
                $aliases = is_array($comp['aliases'] ?? null) ? $comp['aliases'] : [];
                $aliases[] = $nome;
                $comp['aliases'] = array_values(array_unique($aliases));
                $componentes[$i] = $comp;
                $achou = true;
                break;
            }
            if ($achou) {
                continue;
            }
            $componentes[] = [
                'slug' => $slug,
                'nome' => $nome,
                'grupo' => '',
                'vaga' => false,
                'aliases' => [$nome],
            ];
        }
        $grade['componentes'] = $componentes;

        return $grade;
    }

    /**
     * @return list<array{n:int,rotulo:string,bimestres:list<int>}>
     */
    private function periodosPublicos(string $chave): array
    {
        $out = [];
        foreach (GradeSoltaService::periodos($chave) as $periodo) {
            if (!is_array($periodo)) {
                continue;
            }
            $bims = [];
            foreach ($periodo['bimestres'] ?? [] as $b) {
                $n = (int) $b;
                if ($n >= 1 && $n <= 4) {
                    $bims[] = $n;
                }
            }
            $out[] = [
                'n' => (int) ($periodo['n'] ?? 0),
                'rotulo' => (string) ($periodo['rotulo'] ?? ''),
                'bimestres' => $bims,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $linhas
     * @return list<array{nome:string,slug:string,sigla:string,token:string,notas:list<string>,faltas:list<string>,media:string}>
     */
    private function atalhosDeComponentes(array $linhas, string $chave): array
    {
        $out = [];
        $usadas = [];
        $siglas = [];
        $periodos = GradeSoltaService::periodos($chave);
        foreach ($linhas as $linha) {
            $nome = trim((string) ($linha['nome'] ?? ''));
            $slug = $this->slugLivre($nome, $usadas);
            if ($nome === '' || $slug === '') {
                continue;
            }
            $usadas[$slug] = true;
            $sigla = $this->siglaLivre($nome, $siglas);
            $siglas[$sigla] = true;
            $notas = [];
            $faltas = [];
            foreach ($periodos as $periodo) {
                $n = (int) ($periodo['n'] ?? 0);
                if ($n <= 0) {
                    continue;
                }
                $notas[] = 'nota_' . $slug . '_' . $n;
                $faltas[] = 'falta_' . $slug . '_' . $n;
            }
            $out[] = [
                'nome' => $nome,
                'slug' => $slug,
                'sigla' => $sigla,
                'token' => $notas[0] ?? ('nota_' . $slug . '_1'),
                'notas' => $notas,
                'faltas' => $faltas,
                'media' => 'media_final_' . $slug,
            ];
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param array<string, true> $usadas
     */
    private function slugLivre(string $nome, array $usadas): string
    {
        $slug = $this->slugPeloNome($nome);
        if ($slug !== '' && !isset($usadas[$slug])) {
            return $slug;
        }
        $base = str_replace(' ', '_', $this->normalizar($nome));
        $base = trim((string) (preg_replace('/[^a-z0-9_]+/', '', $base) ?? ''), '_');
        $base = substr($base, 0, 28);
        if ($base === '') {
            $base = 'comp';
        }
        $slug = $base;
        $n = 2;
        while (isset($usadas[$slug])) {
            $slug = $base . '_' . $n;
            $n++;
            if ($n > 50) {
                return '';
            }
        }

        return $slug;
    }

    /**
     * @param array<string, true> $usadas
     */
    private function siglaLivre(string $nome, array $usadas): string
    {
        $ignorar = ['de' => true, 'da' => true, 'do' => true, 'das' => true, 'dos' => true, 'e' => true];
        $letras = [];
        foreach (preg_split('/\s+/', trim($nome)) ?: [] as $parte) {
            $limpa = $this->normalizar((string) $parte);
            if ($limpa === '' || isset($ignorar[$limpa])) {
                continue;
            }
            $letras[] = strtoupper(substr($limpa, 0, 1));
        }
        if (count($letras) >= 2) {
            $sigla = implode('', $letras);
        } else {
            $uma = strtoupper((string) (preg_replace('/[^a-z]/', '', $this->normalizar($nome)) ?? ''));
            $sigla = substr($uma, 0, 3);
        }
        if ($sigla === '') {
            $sigla = 'DISC';
        }
        $sigla = substr($sigla, 0, 8);
        $base = $sigla;
        $n = 2;
        while (isset($usadas[$sigla])) {
            $sigla = substr($base, 0, 6) . $n;
            $n++;
        }

        return $sigla;
    }

    private function slugPeloNome(string $nome): string
    {
        $alvo = $this->normalizar($nome);
        if ($alvo === '') {
            return '';
        }
        foreach (GradeSoltaService::componentes() as $comp) {
            if (!empty($comp['vaga'])) {
                continue;
            }
            if ($this->normalizar((string) ($comp['nome'] ?? '')) === $alvo) {
                return (string) $comp['slug'];
            }
            foreach ($comp['aliases'] ?? [] as $alias) {
                if ($this->normalizar((string) $alias) === $alvo) {
                    return (string) $comp['slug'];
                }
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $turma
     */
    private function rotuloTurma(array $turma): string
    {
        $partes = array_filter([
            trim((string) ($turma['ano_letivo'] ?? '')),
            $this->serieDaTurma($turma),
            trim((string) ($turma['nome'] ?? '')),
        ], static fn ($v) => $v !== '');
        return $partes !== [] ? implode(' · ', $partes) : 'Turma';
    }

    /**
     * @param array<string, mixed> $turma
     */
    private function serieDaTurma(array $turma): string
    {
        $serie = trim((string) ($turma['serie_nome'] ?? ''));
        if ($serie === '') {
            $serie = trim((string) ($turma['serie_texto'] ?? ''));
        }
        return $serie;
    }

    /**
     * @param array<string, mixed> $vars
     * @return array<string, string>
     */
    private function variaveisTexto(array $vars): array
    {
        $out = [];
        foreach ($vars as $chave => $valor) {
            if (!is_string($chave) || str_starts_with($chave, '_')) {
                continue;
            }
            if (is_array($valor) || is_object($valor)) {
                continue;
            }
            $out[$chave] = (string) $valor;
        }
        return $out;
    }

    private function chaveGrade(string $chave): string
    {
        $chave = strtolower(trim($chave));
        return in_array($chave, ['1127', '1127a', '1127b', '1128'], true) ? $chave : '1127';
    }

    private function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        $texto = str_replace(',', '.', $texto);
        return is_numeric($texto) ? (float) $texto : null;
    }

    private function formatarNota(float $valor): string
    {
        return number_format($valor, 1, ',', '.');
    }

    private function dataBr(string $data): string
    {
        $data = trim(substr($data, 0, 10));
        if ($data === '' || $data === '0000-00-00') {
            return '—';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $data);
        return $dt ? $dt->format('d/m/Y') : '—';
    }

    private function rotuloSexo(string $sexo): string
    {
        return match (strtoupper(trim($sexo))) {
            'M', 'MASCULINO' => 'Masculino',
            'F', 'FEMININO' => 'Feminino',
            default => $sexo !== '' ? $sexo : '—',
        };
    }

    private function rotuloTurno(string $turno): string
    {
        return match (strtolower(trim($turno))) {
            'matutino', 'manha', 'manhã' => 'Matutino',
            'vespertino', 'tarde' => 'Vespertino',
            'noturno', 'noite' => 'Noturno',
            'integral' => 'Integral',
            default => $turno !== '' ? $turno : '—',
        };
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);
        $texto = preg_replace('/[^a-z0-9 ]+/', ' ', $texto) ?? $texto;
        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }

    private function tabelaExiste(string $tabela): bool
    {
        if (!isset(self::TABELAS[$tabela])) {
            return false;
        }
        $cacheKey = 'tabela:' . $tabela;
        if (array_key_exists($cacheKey, $this->colunas)) {
            return $this->colunas[$cacheKey];
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
                [$tabela]
            );
            $this->colunas[$cacheKey] = !empty($row['ok']);
        } catch (\Throwable $e) {
            $this->colunas[$cacheKey] = false;
        }
        return $this->colunas[$cacheKey];
    }

    private function temColuna(string $tabela, string $coluna): bool
    {
        if (!isset(self::TABELAS[$tabela]) || !preg_match('/^[a-z0-9_]+$/', $coluna)) {
            return false;
        }
        $cacheKey = $tabela . '.' . $coluna;
        if (array_key_exists($cacheKey, $this->colunas)) {
            return $this->colunas[$cacheKey];
        }
        try {
            $row = $this->db->fetch(
                'SELECT 1 AS ok FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
                [$tabela, $coluna]
            );
            $this->colunas[$cacheKey] = !empty($row['ok']);
        } catch (\Throwable $e) {
            $this->colunas[$cacheKey] = false;
        }
        return $this->colunas[$cacheKey];
    }
}

if (!class_exists('DemonstracaoDocumentoService', false) && class_exists(__NAMESPACE__ . '\\DemonstracaoDocumentoService', false)) {
    class_alias(__NAMESPACE__ . '\\DemonstracaoDocumentoService', 'DemonstracaoDocumentoService');
}
