<?php

namespace App\Modulos\LeitorGabarito\Services;

use App\Core\Database;
use App\Modulos\Provas\Models\Exam;

/**
 * Folhas impressas, scans e lançamento da nota na prova.
 */
class LeitorGabaritoService
{
    private const POR_PAGINA = 10;
    private const MAX_ARQUIVOS = 15;
    private const MAX_BYTES = 8388608;

    private Database $db;
    private ?DesenhistaFolha $desenhista = null;
    private ?LeitorFolha $leitor = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function desenhista(): DesenhistaFolha
    {
        return $this->desenhista ??= new DesenhistaFolha();
    }

    private function leitor(): LeitorFolha
    {
        return $this->leitor ??= new LeitorFolha();
    }

    public function tabelasProntas(): bool
    {
        if (!$this->db->tableExists('leitor_gabarito_folhas') || !$this->db->tableExists('leitor_gabarito_leituras')) {
            return false;
        }
        $coluna = $this->db->fetch(
            "SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'leitor_gabarito_folhas'
               AND COLUMN_NAME = 'conjunto'"
        );
        return is_array($coluna);
    }

    /**
     * @return array{itens:list<array<string,mixed>>,pagina:int,paginas:int,total:int}
     */
    public function listarProvas(?int $professorId, string $busca, int $pagina): array
    {
        $params = [];
        $sql = "SELECT p.id, p.titulo, DATE(p.data_inicio) AS data_prova, m.nome AS materia_nome, prof.nome AS professor_nome,
                       (SELECT COUNT(*) FROM provas_questoes q
                        WHERE q.prova_id = p.id AND q.tipo IN ('multipla_escolha', 'verdadeiro_falso')) AS questoes_objetivas
                FROM provas p
                LEFT JOIN materias m ON m.id = p.materia_id
                LEFT JOIN professores prof ON prof.id = p.professor_id
                WHERE p.deleted_at IS NULL";
        if ($professorId !== null && $professorId > 0) {
            $sql .= ' AND p.professor_id = :professor_id';
            $params['professor_id'] = $professorId;
        }
        if ($busca !== '') {
            $sql .= " AND (p.titulo LIKE :busca_titulo ESCAPE '\\\\' OR m.nome LIKE :busca_materia ESCAPE '\\\\')";
            $like = '%' . $this->escaparLike($busca) . '%';
            $params['busca_titulo'] = $like;
            $params['busca_materia'] = $like;
        }
        $sql .= ' ORDER BY p.data_inicio DESC, p.id DESC';
        $linhas = $this->db->fetchAll($sql, $params);
        $linhas = array_values(array_filter($linhas, static function ($linha): bool {
            return (int) ($linha['questoes_objetivas'] ?? 0) > 0;
        }));

        $contagens = $this->contagensPorProva();
        foreach ($linhas as &$linha) {
            $id = (int) $linha['id'];
            $linha['folhas'] = $contagens[$id]['folhas'] ?? 0;
            $linha['pendentes'] = $contagens[$id]['pendentes'] ?? 0;
            $linha['confirmadas'] = $contagens[$id]['confirmadas'] ?? 0;
        }
        unset($linha);

        $total = count($linhas);
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, $pagina), $paginas);
        $offset = ($pagina - 1) * self::POR_PAGINA;

        return [
            'itens' => array_slice($linhas, $offset, self::POR_PAGINA),
            'pagina' => $pagina,
            'paginas' => $paginas,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detalharProva(int $provaId, ?int $professorId, int $paginaLeituras): ?array
    {
        $prova = $this->buscarProva($provaId, $professorId);
        if ($prova === null) {
            return null;
        }
        $montagem = $this->montarQuestoes($provaId);
        $podeGerar = $montagem['questoes'] !== [] && count($montagem['questoes']) <= GeometriaFolha::MAX_QUESTOES;
        if (count($montagem['questoes']) > GeometriaFolha::MAX_QUESTOES) {
            $montagem['avisos'][] = 'Esta prova tem mais de ' . GeometriaFolha::MAX_QUESTOES . ' questões objetivas. A folha desta versão cabe até esse número.';
        }

        return [
            'prova' => $prova,
            'turmas' => $this->turmasDaProva($provaId),
            'questoes' => $montagem['questoes'],
            'avisos' => $montagem['avisos'],
            'dissertativas' => $montagem['dissertativas'],
            'pode_gerar' => $podeGerar,
            'leituras' => $this->listarLeituras($provaId, $paginaLeituras, 0),
            'alunos_folha' => $this->opcoesAluno($provaId, 0),
        ];
    }

    /**
     * @return array{novas:int,atualizadas:int,erros:list<string>}
     */
    public function gerarFolhas(int $provaId, ?int $professorId, int $turmaId, bool $substituir): array
    {
        $this->exigirTabelas();
        if ($this->buscarProva($provaId, $professorId) === null) {
            throw new \RuntimeException('Prova não encontrada.');
        }
        $turmas = $this->turmasDaProva($provaId);
        $turmaOk = false;
        foreach ($turmas as $turma) {
            if ((int) $turma['id'] === $turmaId) {
                $turmaOk = true;
                break;
            }
        }
        if (!$turmaOk) {
            throw new \RuntimeException('Escolha uma turma desta prova.');
        }
        $montagem = $this->montarQuestoes($provaId);
        if ($montagem['questoes'] === []) {
            throw new \RuntimeException('Esta prova não tem questão de múltipla escolha.');
        }
        if (count($montagem['questoes']) > GeometriaFolha::MAX_QUESTOES) {
            throw new \RuntimeException('A folha cabe até ' . GeometriaFolha::MAX_QUESTOES . ' questões objetivas.');
        }

        $prova = $this->buscarProva($provaId, $professorId);
        $titulo = (string) ($prova['titulo'] ?? 'Prova');
        $alunos = $this->alunosDaTurma($turmaId);
        if ($alunos === []) {
            throw new \RuntimeException('Não há aluno ativo nesta turma.');
        }

        $novas = 0;
        $atualizadas = 0;
        $erros = [];
        foreach ($alunos as $aluno) {
            try {
                $resultado = $this->gerarUmaFolha($provaId, (int) $aluno['id'], (string) $aluno['nome'], $titulo, $montagem['questoes'], $substituir, 0);
                if ($resultado === 'nova') {
                    $novas++;
                } elseif ($resultado === 'atualizada') {
                    $atualizadas++;
                }
            } catch (\Throwable $e) {
                $erros[] = (string) $aluno['nome'] . ': ' . $this->explicarFalha($e);
            }
        }
        return ['novas' => $novas, 'atualizadas' => $atualizadas, 'erros' => $erros];
    }

    /**
     * @return list<array{id:int,aluno_nome:string,caminho_png:string}>
     */
    public function folhasParaImpressao(int $provaId, ?int $professorId, int $turmaId): array
    {
        if ($this->buscarProva($provaId, $professorId) === null) {
            throw new \RuntimeException('Prova não encontrada.');
        }
        return $this->db->fetchAll(
            'SELECT f.id, a.nome AS aluno_nome, f.caminho_png
             FROM leitor_gabarito_folhas f
             INNER JOIN alunos a ON a.id = f.aluno_id
             WHERE f.prova_id = :prova_id AND f.conjunto = 0 AND a.turma_id = :turma_id
             ORDER BY a.nome',
            ['prova_id' => $provaId, 'turma_id' => $turmaId]
        );
    }

    public function caminhoFolha(int $folhaId, ?int $professorId): string
    {
        $folha = $this->buscarFolha($folhaId);
        if ($folha === null || $this->buscarProva((int) $folha['prova_id'], $professorId) === null) {
            throw new \RuntimeException('Folha não encontrada.');
        }
        return $this->caminhoAbsoluto((string) $folha['caminho_png']);
    }

    /**
     * PNG da folha para embutir no PDF. Se o arquivo sumiu, desenha de novo.
     */
    public function bytesDaFolha(int $folhaId): string
    {
        $folha = $this->buscarFolha($folhaId);
        if ($folha === null) {
            throw new \RuntimeException('Folha não encontrada.');
        }
        $gravado = $this->lerArquivoDaFolha((string) ($folha['caminho_png'] ?? ''));
        if ($gravado !== '') {
            return $gravado;
        }
        $pacote = json_decode((string) ($folha['questoes_json'] ?? ''), true);
        $questoes = is_array($pacote) ? ($pacote['questoes'] ?? null) : null;
        if (!is_array($questoes) || $questoes === []) {
            throw new \RuntimeException('Esta folha não tem questões para desenhar.');
        }
        $aluno = $this->db->fetch(
            'SELECT nome FROM alunos WHERE id = :id',
            ['id' => (int) $folha['aluno_id']]
        );
        $nome = is_array($aluno) ? (string) ($aluno['nome'] ?? '') : '';
        $imagem = $this->desenhista()->criarImagem($questoes, $folhaId, (int) $folha['aluno_id'], $nome, 'Prova impressa');
        ob_start();
        imagepng($imagem);
        $bytes = ob_get_clean();
        if (!is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Não foi possível desenhar a folha.');
        }
        $relativo = $this->slug() . '/folhas/' . $folhaId . '.png';
        try {
            $this->desenhista()->gravarPng($imagem, $this->destinoSeguro($relativo));
            $this->db->update(
                'UPDATE leitor_gabarito_folhas SET caminho_png = :caminho_png WHERE id = :id',
                ['caminho_png' => $relativo, 'id' => $folhaId]
            );
        } catch (\Throwable $e) {
            error_log('folha gabarito: ' . $e->getMessage());
        }
        return $bytes;
    }

    /**
     * @param list<array{name:string,tmp_name:string,error:int,size:int}> $arquivos
     * @return array{lidas:int,revisao:int,erros:int}
     */
    public function receberScans(int $provaId, ?int $professorId, int $usuarioId, array $arquivos, int $conjunto = 0): array
    {
        $this->exigirTabelas();
        if ($this->buscarProva($provaId, $professorId) === null) {
            throw new \RuntimeException('Prova não encontrada.');
        }
        if (count($arquivos) > self::MAX_ARQUIVOS) {
            throw new \RuntimeException('Envie no máximo ' . self::MAX_ARQUIVOS . ' imagens por vez.');
        }
        $ids = [];
        foreach ($arquivos as $arquivo) {
            if ((int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $ids[] = $this->guardarScan($provaId, $usuarioId, $arquivo, $conjunto === 1 ? 1 : 0);
        }
        if ($ids === []) {
            throw new \RuntimeException('Selecione ao menos uma imagem JPG ou PNG.');
        }
        $lidas = 0;
        $revisao = 0;
        $erros = 0;
        foreach ($ids as $id) {
            $this->processarLeitura($id, false);
            $status = $this->statusLeitura($id);
            if ($status === 'confirmada') {
                $lidas++;
            } elseif ($status === 'erro') {
                $erros++;
            } else {
                $revisao++;
            }
        }
        return ['lidas' => $lidas, 'revisao' => $revisao, 'erros' => $erros];
    }

    public function processarFila(int $limite): int
    {
        if (!$this->tabelasProntas()) {
            return 0;
        }
        $limite = max(1, min(20, $limite));
        $this->db->query(
            "UPDATE leitor_gabarito_leituras
             SET status = 'na_fila'
             WHERE status = 'lendo'
               AND criado_em < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
        $ids = $this->db->fetchAll(
            "SELECT id FROM leitor_gabarito_leituras WHERE status = 'na_fila' ORDER BY id ASC LIMIT " . $limite
        );
        $feitos = 0;
        foreach ($ids as $linha) {
            $this->processarLeitura((int) $linha['id'], false);
            $feitos++;
        }
        return $feitos;
    }

    public function processarPendentesDaProva(int $provaId, ?int $professorId, int $limite, int $conjunto = 0): int
    {
        $this->exigirTabelas();
        if ($this->buscarProva($provaId, $professorId) === null) {
            throw new \RuntimeException('Prova não encontrada.');
        }
        $limite = max(1, min(20, $limite));
        $ids = $this->db->fetchAll(
            "SELECT id FROM leitor_gabarito_leituras
             WHERE prova_id = :prova_id AND conjunto = :conjunto AND status = 'na_fila'
             ORDER BY id ASC
             LIMIT " . $limite,
            ['prova_id' => $provaId, 'conjunto' => $conjunto === 1 ? 1 : 0]
        );
        foreach ($ids as $linha) {
            $this->processarLeitura((int) $linha['id'], false);
        }
        return count($ids);
    }

    public function reler(int $leituraId, ?int $professorId): void
    {
        $leitura = $this->buscarLeitura($leituraId, $professorId);
        if ($leitura === null) {
            throw new \RuntimeException('Leitura não encontrada.');
        }
        if (($leitura['status'] ?? '') === 'lendo') {
            throw new \RuntimeException('Esta imagem já está sendo lida.');
        }
        $this->processarLeitura($leituraId, true);
    }

    /**
     * @param array<string, string> $letras questao_id => letra
     * @return list<array{nome:string,nota:float,acertos:int,total:int}>
     */
    public function confirmar(int $leituraId, ?int $professorId, int $alunoId, array $letras): array
    {
        $leitura = $this->buscarLeitura($leituraId, $professorId);
        if ($leitura === null) {
            throw new \RuntimeException('Leitura não encontrada.');
        }
        $marcacoes = json_decode((string) ($leitura['marcacoes_json'] ?? ''), true);
        $questoes = is_array($marcacoes) ? ($marcacoes['questoes'] ?? null) : null;
        if (!is_array($questoes) || $questoes === []) {
            throw new \RuntimeException('Ainda não há marcações. Leia a imagem de novo.');
        }
        if ($alunoId <= 0) {
            $alunoId = (int) ($leitura['aluno_id'] ?? 0);
        }
        $conjunto = (int) ($leitura['conjunto'] ?? 0) === 1 ? 1 : 0;
        $folha = $this->buscarFolhaPorAluno((int) $leitura['prova_id'], $alunoId, $conjunto);
        if ($folha === null) {
            throw new \RuntimeException('Gere a folha deste aluno antes de lançar a nota.');
        }
        return $this->lancar($leitura, $questoes, $alunoId, (int) $folha['id'], $letras);
    }

    public function excluirLeitura(int $leituraId, ?int $professorId): void
    {
        $leitura = $this->buscarLeitura($leituraId, $professorId);
        if ($leitura === null) {
            throw new \RuntimeException('Leitura não encontrada.');
        }
        $caminho = (string) ($leitura['caminho_scan'] ?? '');
        $this->db->delete('DELETE FROM leitor_gabarito_leituras WHERE id = :id', ['id' => $leituraId]);
        if ($caminho !== '') {
            $absoluto = $this->diretorioBase() . '/' . str_replace('\\', '/', $caminho);
            if (is_file($absoluto)) {
                @unlink($absoluto);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarLeitura(int $leituraId, ?int $professorId): ?array
    {
        if (!$this->tabelasProntas()) {
            return null;
        }
        $leitura = $this->db->fetch(
            'SELECT l.*, a.nome AS aluno_nome, p.titulo AS prova_titulo
             FROM leitor_gabarito_leituras l
             INNER JOIN provas p ON p.id = l.prova_id AND p.deleted_at IS NULL
             LEFT JOIN alunos a ON a.id = l.aluno_id
             WHERE l.id = :id',
            ['id' => $leituraId]
        );
        if (!is_array($leitura)) {
            return null;
        }
        if ($this->buscarProva((int) $leitura['prova_id'], $professorId) === null) {
            return null;
        }
        return $leitura;
    }

    public function caminhoScan(int $leituraId, ?int $professorId): string
    {
        $leitura = $this->buscarLeitura($leituraId, $professorId);
        if ($leitura === null) {
            throw new \RuntimeException('Leitura não encontrada.');
        }
        return $this->caminhoAbsoluto((string) $leitura['caminho_scan']);
    }

    /**
     * @return list<array{id:int,nome:string}>
     */
    public function opcoesAluno(int $provaId, int $conjunto = 0): array
    {
        if (!$this->tabelasProntas()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT a.id, a.nome
             FROM leitor_gabarito_folhas f
             INNER JOIN alunos a ON a.id = f.aluno_id
             WHERE f.prova_id = :prova_id AND f.conjunto = :conjunto
             ORDER BY a.nome',
            ['prova_id' => $provaId, 'conjunto' => $conjunto === 1 ? 1 : 0]
        );
    }

    /**
     * @param list<array{questao_id:int,valor:float,alternativas:list<array{letra:string,id:int}>}> $questoes
     */
    private function gerarUmaFolha(int $provaId, int $alunoId, string $nome, string $titulo, array $questoes, bool $substituir, int $conjunto = 0): string
    {
        $pacote = json_encode([
            'versao' => GeometriaFolha::VERSAO,
            'questoes' => $questoes,
        ], JSON_UNESCAPED_UNICODE);
        if ($pacote === false) {
            throw new \RuntimeException('Não foi possível montar o desenho da folha.');
        }

        $conjunto = $conjunto === 1 ? 1 : 0;
        $existente = $this->db->fetch(
            'SELECT id, caminho_png FROM leitor_gabarito_folhas
             WHERE prova_id = :prova_id AND aluno_id = :aluno_id AND conjunto = :conjunto',
            ['prova_id' => $provaId, 'aluno_id' => $alunoId, 'conjunto' => $conjunto]
        );
        $nova = !is_array($existente);
        if (!$nova && !$substituir && (string) $existente['caminho_png'] !== 'pendente') {
            $jaExiste = $this->diretorioBase() . '/' . str_replace('\\', '/', (string) $existente['caminho_png']);
            if (is_file($jaExiste)) {
                return 'existente';
            }
        }

        if ($nova) {
            try {
                $folhaId = (int) $this->db->insert(
                    'INSERT INTO leitor_gabarito_folhas (prova_id, aluno_id, conjunto, questoes_json, caminho_png)
                     VALUES (:prova_id, :aluno_id, :conjunto, :questoes_json, :caminho_png)',
                    [
                        'prova_id' => $provaId,
                        'aluno_id' => $alunoId,
                        'conjunto' => $conjunto,
                        'questoes_json' => $pacote,
                        'caminho_png' => 'pendente',
                    ]
                );
            } catch (\Throwable $e) {
                if (!$this->chaveDuplicada($e)) {
                    throw $e;
                }
                $jaGravada = $this->db->fetch(
                    'SELECT id FROM leitor_gabarito_folhas
                     WHERE prova_id = :prova_id AND aluno_id = :aluno_id AND conjunto = :conjunto
                     LIMIT 1',
                    ['prova_id' => $provaId, 'aluno_id' => $alunoId, 'conjunto' => $conjunto]
                );
                if (!is_array($jaGravada)) {
                    throw new \RuntimeException('Já existe uma folha deste aluno e o banco não deixa gravar a do bloco. Rode a migration do leitor de gabarito.');
                }
                $folhaId = (int) $jaGravada['id'];
                $nova = false;
            }
        } else {
            $folhaId = (int) $existente['id'];
        }

        $relativo = $this->slug() . '/folhas/' . $folhaId . '.png';
        $destino = $this->destinoSeguro($relativo);
        try {
            $imagem = $this->desenhista()->criarImagem($questoes, $folhaId, $alunoId, $nome, $titulo);
            $this->desenhista()->gravarPng($imagem, $destino);
            $this->db->update(
                'UPDATE leitor_gabarito_folhas SET questoes_json = :questoes_json, caminho_png = :caminho_png WHERE id = :id AND conjunto = :conjunto',
                ['questoes_json' => $pacote, 'caminho_png' => $relativo, 'id' => $folhaId, 'conjunto' => $conjunto]
            );
        } catch (\Throwable $e) {
            if ($nova) {
                $this->db->delete('DELETE FROM leitor_gabarito_folhas WHERE id = :id', ['id' => $folhaId]);
            }
            throw $e;
        }
        return $nova ? 'nova' : 'atualizada';
    }

    /**
     * Uma folha por aluno com as matérias do bloco impresso, para marcar tudo no mesmo gabarito.
     *
     * @param list<array<string, mixed>> $provas
     * @return array{novas:int,atualizadas:int,erros:list<string>}
     */
    private function gerarFolhasConjuntas(array $provas, int $turmaId): array
    {
        $ancoraId = (int) ($provas[0]['id'] ?? 0);
        $montagem = $this->questoesDoBloco($provas);
        if ($montagem['questoes'] === []) {
            throw new \RuntimeException('Este bloco não tem questão de múltipla escolha.');
        }
        $linhas = count($montagem['questoes']);
        foreach ($montagem['questoes'] as $questao) {
            if (trim((string) ($questao['rotulo'] ?? '')) !== '') {
                $linhas++;
            }
        }
        if ($linhas > GeometriaFolha::MAX_QUESTOES) {
            throw new \RuntimeException('A folha cabe até ' . GeometriaFolha::MAX_QUESTOES . ' questões objetivas.');
        }

        $alunos = $this->alunosDaTurma($turmaId);
        if ($alunos === []) {
            throw new \RuntimeException('Não há aluno ativo nesta turma.');
        }

        $novas = 0;
        $atualizadas = 0;
        $erros = [];
        foreach ($alunos as $aluno) {
            try {
                $resultado = $this->gerarUmaFolha(
                    $ancoraId,
                    (int) $aluno['id'],
                    (string) $aluno['nome'],
                    'Prova impressa',
                    $montagem['questoes'],
                    true,
                    1
                );
                if ($resultado === 'nova') {
                    $novas++;
                } elseif ($resultado === 'atualizada') {
                    $atualizadas++;
                }
            } catch (\Throwable $e) {
                $erros[] = (string) $aluno['nome'] . ': ' . $this->explicarFalha($e);
            }
        }
        return ['novas' => $novas, 'atualizadas' => $atualizadas, 'erros' => $erros];
    }

    /**
     * Folha única do bloco, com todas as matérias, sem apagar a folha de cada matéria.
     *
     * @return array{novas:int,atualizadas:int,erros:list<string>}
     */
    public function gerarFolhasDoBloco(int $blocoId, int $turmaId): array
    {
        try {
            return $this->executarGeracaoDoBloco($blocoId, $turmaId);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('leitor gabarito: ' . $e->getMessage());
            throw new \RuntimeException($this->explicarFalha($e));
        }
    }

    /**
     * @return array{novas:int,atualizadas:int,erros:list<string>}
     */
    private function executarGeracaoDoBloco(int $blocoId, int $turmaId): array
    {
        $this->exigirTabelas();
        $provas = $this->provasDoBloco($blocoId);
        if ($provas === []) {
            throw new \RuntimeException('Este bloco não tem prova para imprimir.');
        }
        $turmaOk = false;
        foreach ($provas as $prova) {
            foreach ($this->turmasDaProva((int) $prova['id']) as $turma) {
                if ((int) $turma['id'] === $turmaId) {
                    $turmaOk = true;
                    break 2;
                }
            }
        }
        if (!$turmaOk) {
            throw new \RuntimeException('Escolha uma turma deste bloco.');
        }
        return $this->gerarFolhasConjuntas($provas, $turmaId);
    }

    /**
     * @return list<array{id:int,aluno_nome:string,caminho_png:string}>
     */
    public function folhasDoBlocoParaImpressao(int $blocoId, int $turmaId): array
    {
        $provas = $this->provasDoBloco($blocoId);
        if ($provas === []) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT f.id, a.nome AS aluno_nome, f.caminho_png
             FROM leitor_gabarito_folhas f
             INNER JOIN alunos a ON a.id = f.aluno_id
             WHERE f.prova_id = :prova_id AND f.conjunto = 1 AND a.turma_id = :turma_id
             ORDER BY a.nome',
            ['prova_id' => (int) $provas[0]['id'], 'turma_id' => $turmaId]
        );
    }

    /**
     * @return array{bloco:array<string,mixed>,provas:list<array<string,mixed>>,ancora_id:int,turmas:list<array<string,mixed>>,leituras:array{itens:list<array<string,mixed>>,pagina:int,paginas:int,total:int}}|null
     */
    public function detalharBloco(int $blocoId, int $paginaLeituras): ?array
    {
        $bloco = $this->db->fetch(
            'SELECT id, titulo, formato_evento FROM provas_blocos WHERE id = :id',
            ['id' => $blocoId]
        );
        if (!is_array($bloco) || (string) ($bloco['formato_evento'] ?? '') !== 'prova_impressa') {
            return null;
        }
        $provas = $this->provasDoBloco($blocoId);
        if ($provas === []) {
            return null;
        }
        $turmas = [];
        $vistas = [];
        foreach ($provas as $prova) {
            foreach ($this->turmasDaProva((int) $prova['id']) as $turma) {
                $turmaId = (int) $turma['id'];
                if ($turmaId > 0 && !isset($vistas[$turmaId])) {
                    $vistas[$turmaId] = true;
                    $turmas[] = $turma;
                }
            }
        }
        $ancoraId = (int) $provas[0]['id'];
        return [
            'bloco' => $bloco,
            'provas' => $provas,
            'ancora_id' => $ancoraId,
            'turmas' => $turmas,
            'leituras' => $this->listarLeituras($ancoraId, $paginaLeituras, 1),
        ];
    }

    public function blocoImpressoId(int $provaId): int
    {
        $bloco = $this->db->fetch(
            "SELECT b.id
             FROM provas_blocos_vinculo v
             INNER JOIN provas_blocos b ON b.id = v.bloco_id
             WHERE v.prova_id = :prova_id AND b.formato_evento = 'prova_impressa'
             LIMIT 1",
            ['prova_id' => $provaId]
        );
        return is_array($bloco) ? (int) $bloco['id'] : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @return list<array<string, mixed>>
     */
    private function provasDoBloco(int $blocoId): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.titulo, m.nome AS materia_nome
             FROM provas_blocos b
             INNER JOIN provas_blocos_vinculo v ON v.bloco_id = b.id
             INNER JOIN provas p ON p.id = v.prova_id AND p.deleted_at IS NULL
             LEFT JOIN materias m ON m.id = p.materia_id
             WHERE b.id = :bloco_id
               AND b.formato_evento = 'prova_impressa'
               AND (p.status IS NULL OR p.status <> 'reprovada')
             ORDER BY v.ordem ASC, p.id ASC",
            ['bloco_id' => $blocoId]
        );
    }

    /**
     * @param list<array<string, mixed>> $provas
     * @return array{questoes:list<array<string,mixed>>}
     */
    private function questoesDoBloco(array $provas): array
    {
        $questoes = [];
        foreach ($provas as $prova) {
            $montagem = $this->montarQuestoes((int) $prova['id']);
            $rotulo = trim((string) ($prova['materia_nome'] ?? ''));
            if ($rotulo === '') {
                $rotulo = trim((string) ($prova['titulo'] ?? 'Prova'));
            }
            $numero = 1;
            $primeira = true;
            foreach ($montagem['questoes'] as $questao) {
                $questao['prova_id'] = (int) $prova['id'];
                $questao['numero'] = $numero;
                $numero++;
                if ($primeira) {
                    $questao['rotulo'] = $rotulo;
                    $primeira = false;
                }
                $questoes[] = $questao;
            }
        }
        return ['questoes' => $questoes];
    }

    /**
     * @param array<string, mixed> $folha
     * @param list<array<string, mixed>> $questoes
     */
    private function folhaServeProva(array $folha, array $questoes, int $provaId): bool
    {
        if ((int) ($folha['prova_id'] ?? 0) === $provaId) {
            return true;
        }
        foreach ($questoes as $questao) {
            if ((int) ($questao['prova_id'] ?? 0) === $provaId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int} $arquivo
     */
    private function guardarScan(int $provaId, int $usuarioId, array $arquivo, int $conjunto = 0): int
    {
        $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no envio de uma das imagens.');
        }
        $tmp = (string) ($arquivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Arquivo de upload inválido.');
        }
        $tamanho = (int) ($arquivo['size'] ?? 0);
        if ($tamanho <= 0 || $tamanho > self::MAX_BYTES) {
            throw new \RuntimeException('Cada imagem pode ter até 8 MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $extensoes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensoes[$mime])) {
            throw new \RuntimeException('Envie apenas JPG, PNG ou WebP.');
        }
        $relativo = $this->slug() . '/scans/' . $provaId . '_' . bin2hex(random_bytes(8)) . '.' . $extensoes[$mime];
        $destino = $this->destinoSeguro($relativo);
        $dir = dirname($destino);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível guardar o scan.');
        }
        if (!move_uploaded_file($tmp, $destino)) {
            throw new \RuntimeException('Não foi possível guardar o scan.');
        }
        return (int) $this->db->insert(
            "INSERT INTO leitor_gabarito_leituras (prova_id, conjunto, caminho_scan, status, criado_por)
             VALUES (:prova_id, :conjunto, :caminho_scan, 'na_fila', :criado_por)",
            [
                'prova_id' => $provaId,
                'conjunto' => $conjunto,
                'caminho_scan' => $relativo,
                'criado_por' => $usuarioId > 0 ? $usuarioId : null,
            ]
        );
    }

    private function processarLeitura(int $leituraId, bool $forcar): void
    {
        $filtro = $forcar ? "status <> 'lendo'" : "status = 'na_fila'";
        $afetadas = $this->db->update(
            "UPDATE leitor_gabarito_leituras SET status = 'lendo' WHERE id = :id AND {$filtro}",
            ['id' => $leituraId]
        );
        if ($afetadas < 1) {
            return;
        }
        try {
            $leitura = $this->db->fetch(
                'SELECT * FROM leitor_gabarito_leituras WHERE id = :id',
                ['id' => $leituraId]
            );
            if (!is_array($leitura)) {
                return;
            }
            $provaId = (int) $leitura['prova_id'];
            $conjuntoLeitura = (int) ($leitura['conjunto'] ?? 0) === 1 ? 1 : 0;
            $alinhamento = $this->leitor()->preparar($this->caminhoAbsoluto((string) $leitura['caminho_scan']));
            $folha = null;
            if ($alinhamento->codigoOk && $alinhamento->folhaId !== null) {
                $folha = $this->buscarFolha((int) $alinhamento->folhaId);
                $pacote = is_array($folha) ? json_decode((string) $folha['questoes_json'], true) : null;
                $questoesPacote = is_array($pacote) ? ($pacote['questoes'] ?? null) : null;
                $conjuntoFolha = is_array($folha) && (int) ($folha['conjunto'] ?? 0) === 1 ? 1 : 0;
                if (is_array($folha) && $conjuntoFolha !== $conjuntoLeitura) {
                    $mensagem = $conjuntoLeitura === 1
                        ? 'Este gabarito é de uma matéria só. Leia na ação do professor.'
                        : 'Este gabarito é do bloco inteiro. Use Ler gabarito ao lado de Imprimir prova.';
                    $this->gravarResultado($leituraId, 'erro', $mensagem, (int) $folha['id'], (int) $folha['aluno_id'], null);
                    return;
                }
                $serve = $conjuntoLeitura === 1
                    ? $this->folhaServeProva(is_array($folha) ? $folha : [], is_array($questoesPacote) ? $questoesPacote : [], $provaId)
                    : is_array($folha) && (int) $folha['prova_id'] === $provaId;
                if ($folha === null || !$serve) {
                    $this->gravarResultado($leituraId, 'erro', 'O código não é de uma folha desta prova.', null, null, null);
                    return;
                }
                if ($alinhamento->alunoQr !== null && (int) $folha['aluno_id'] !== (int) $alinhamento->alunoQr) {
                    $this->gravarResultado($leituraId, 'revisao', 'O QR Code não confere com o aluno desta folha.', (int) $folha['id'], (int) $folha['aluno_id'], null);
                    return;
                }
                if (!is_array($pacote) || (int) ($pacote['versao'] ?? 0) !== GeometriaFolha::VERSAO || !is_array($questoesPacote)) {
                    $this->gravarResultado($leituraId, 'erro', 'Esta folha foi gerada por uma versão antiga. Imprima de novo.', null, null, null);
                    return;
                }
                $questoes = $questoesPacote;
            } elseif ($conjuntoLeitura === 1) {
                $this->gravarResultado($leituraId, 'revisao', 'Não li o código da folha do bloco. Confira a foto.', null, null, null);
                return;
            } else {
                $questoes = $this->montarQuestoes($provaId)['questoes'];
            }
            $marcacoes = $this->leitor()->lerMarcacoes($alinhamento, $questoes);
            $motivo = $this->motivoRevisao($marcacoes, $folha !== null);
            $folhaId = $folha !== null ? (int) $folha['id'] : null;
            $alunoId = $folha !== null ? (int) $folha['aluno_id'] : null;
            if ($motivo !== null) {
                $this->gravarResultado($leituraId, 'revisao', $motivo, $folhaId, $alunoId, $marcacoes);
                return;
            }
            $this->lancar($leitura, $marcacoes, (int) $alunoId, (int) $folhaId, null);
        } catch (\PDOException $e) {
            error_log('leitor gabarito: ' . $e->getMessage());
            $this->gravarResultado($leituraId, 'erro', 'Não foi possível ler esta imagem.', null, null, null);
        } catch (\RuntimeException $e) {
            $this->gravarResultado($leituraId, 'erro', $e->getMessage(), null, null, null);
        } catch (\Throwable $e) {
            error_log('leitor gabarito: ' . $e->getMessage());
            $this->gravarResultado($leituraId, 'erro', 'Não foi possível ler esta imagem.', null, null, null);
        }
    }

    /**
     * @param list<array<string, mixed>> $marcacoes
     */
    private function motivoRevisao(array $marcacoes, bool $codigoOk): ?string
    {
        if (!$codigoOk) {
            return 'Não li o código da folha. Escolha o aluno e confira as marcações.';
        }
        if ($marcacoes === []) {
            return 'A folha não tem questões para ler.';
        }
        $vazias = 0;
        foreach ($marcacoes as $questao) {
            $situacao = (string) ($questao['situacao'] ?? '');
            if ($situacao === 'dupla') {
                return 'Há questão com duas bolhas. Confira antes de lançar.';
            }
            if ($situacao === 'duvida') {
                return 'Há marcação fraca. Confira antes de lançar.';
            }
            if ($situacao === 'vazia') {
                $vazias++;
            }
        }
        if ($vazias === count($marcacoes)) {
            return 'Nenhuma bolha marcada. Confira a imagem antes de lançar.';
        }
        return null;
    }

    /**
     * @param array<string, mixed> $leitura
     * @param list<array<string, mixed>> $marcacoes
     * @param array<string, string>|null $letras
     * @return list<array{nome:string,nota:float,acertos:int,total:int}>
     */
    private function lancar(array $leitura, array $marcacoes, int $alunoId, int $folhaId, ?array $letras): array
    {
        $provaId = (int) $leitura['prova_id'];
        $mapas = [];
        $provasAfetadas = [];
        $ajustadas = [];
        $marcacoes = $this->completarMaterias($leitura, $marcacoes);
        $this->db->beginTransaction();
        try {
            $contagem = [];
            foreach ($marcacoes as $questao) {
                $provaQuestao = (int) ($questao['prova_id'] ?? 0);
                if ($provaQuestao <= 0) {
                    $provaQuestao = $provaId;
                }
                if (!isset($contagem[$provaQuestao])) {
                    $contagem[$provaQuestao] = ['acertos' => 0, 'total' => 0];
                }
                $contagem[$provaQuestao]['total']++;
                if (!isset($mapas[$provaQuestao])) {
                    $mapas[$provaQuestao] = $this->mapaAlternativas($provaQuestao);
                }
                $mapa = $mapas[$provaQuestao];
                $letra = $this->letraEfetiva($questao, $letras);
                $alternativaId = $this->alternativaDaLetra($questao, $letra);
                $correta = 0;
                if ($alternativaId !== null && isset($mapa[$alternativaId])) {
                    $correta = (int) $mapa[$alternativaId] === 1 ? 1 : 0;
                } else {
                    $alternativaId = null;
                }
                $pontuacao = $correta === 1 ? (float) ($questao['valor'] ?? 0) : 0.0;
                $this->db->query(
                    'INSERT INTO provas_respostas (prova_id, aluno_id, questao_id, alternativa_id, correta, pontuacao)
                     VALUES (:prova_id, :aluno_id, :questao_id, :alternativa_id, :correta, :pontuacao)
                     ON DUPLICATE KEY UPDATE
                        alternativa_id = VALUES(alternativa_id),
                        correta = VALUES(correta),
                        pontuacao = VALUES(pontuacao),
                        resposta_texto = NULL',
                    [
                        'prova_id' => $provaQuestao,
                        'aluno_id' => $alunoId,
                        'questao_id' => (int) $questao['questao_id'],
                        'alternativa_id' => $alternativaId,
                        'correta' => $correta,
                        'pontuacao' => $pontuacao,
                    ]
                );
                $questao['prova_id'] = $provaQuestao;
                $questao['letra'] = $letra !== '' ? $letra : null;
                $questao['alternativa_id'] = $alternativaId;
                $questao['situacao'] = $letra === '' ? 'vazia' : 'marcada';
                $ajustadas[] = $questao;
                $provasAfetadas[$provaQuestao] = true;
                if ($correta === 1) {
                    $contagem[$provaQuestao]['acertos']++;
                }
            }

            $nomes = $this->nomesDasProvas(array_map('intval', array_keys($provasAfetadas)));
            $materias = [];
            $notaDaLeitura = null;
            foreach (array_keys($provasAfetadas) as $provaAfetada) {
                $realizacao = $this->db->fetch(
                    'SELECT id FROM provas_realizacoes WHERE prova_id = :prova_id AND aluno_id = :aluno_id',
                    ['prova_id' => $provaAfetada, 'aluno_id' => $alunoId]
                );
                if (!is_array($realizacao)) {
                    $this->db->insert(
                        "INSERT INTO provas_realizacoes (prova_id, aluno_id, iniciado_em, finalizado_em, nota, status)
                         VALUES (:prova_id, :aluno_id, NOW(), NOW(), 0, 'finalizado')",
                        ['prova_id' => $provaAfetada, 'aluno_id' => $alunoId]
                    );
                } else {
                    $this->db->update(
                        "UPDATE provas_realizacoes SET finalizado_em = NOW(), status = 'finalizado' WHERE id = :id",
                        ['id' => (int) $realizacao['id']]
                    );
                }
                $notaProva = (new Exam())->recalcularNotaAluno((int) $provaAfetada, $alunoId);
                if ((int) $provaAfetada === $provaId) {
                    $notaDaLeitura = $notaProva;
                }
                $materias[] = [
                    'nome' => (string) ($nomes[(int) $provaAfetada] ?? 'Prova'),
                    'nota' => $notaProva,
                    'acertos' => (int) ($contagem[(int) $provaAfetada]['acertos'] ?? 0),
                    'total' => (int) ($contagem[(int) $provaAfetada]['total'] ?? 0),
                ];
            }
            $nota = $notaDaLeitura;
            $mensagem = null;
            if (count($materias) > 1) {
                $nota = null;
                $mensagem = $this->textoNotas($materias);
            } elseif ($materias !== [] && $nota === null) {
                $nota = $materias[0]['nota'];
            }
            $json = json_encode(['questoes' => $ajustadas], JSON_UNESCAPED_UNICODE);
            $this->db->update(
                "UPDATE leitor_gabarito_leituras
                 SET status = 'confirmada', mensagem = :mensagem, folha_id = :folha_id, aluno_id = :aluno_id,
                     marcacoes_json = :marcacoes_json, nota = :nota, lido_em = NOW(), confirmado_em = NOW()
                 WHERE id = :id",
                [
                    'folha_id' => $folhaId,
                    'aluno_id' => $alunoId,
                    'marcacoes_json' => $json === false ? null : $json,
                    'mensagem' => $mensagem,
                    'nota' => $nota,
                    'id' => (int) $leitura['id'],
                ]
            );
            $this->db->commit();
            return $materias;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * @param list<array<string, mixed>>|null $marcacoes
     */
    private function gravarResultado(int $leituraId, string $status, string $mensagem, ?int $folhaId, ?int $alunoId, ?array $marcacoes): void
    {
        $json = null;
        if ($marcacoes !== null) {
            $codificado = json_encode(['questoes' => $marcacoes], JSON_UNESCAPED_UNICODE);
            $json = $codificado === false ? null : $codificado;
        }
        $this->db->update(
            "UPDATE leitor_gabarito_leituras
             SET status = :status, mensagem = :mensagem, folha_id = :folha_id, aluno_id = :aluno_id,
                 marcacoes_json = :marcacoes_json, lido_em = NOW()
             WHERE id = :id",
            [
                'status' => $status,
                'mensagem' => mb_substr($mensagem, 0, 500),
                'folha_id' => $folhaId,
                'aluno_id' => $alunoId,
                'marcacoes_json' => $json,
                'id' => $leituraId,
            ]
        );
    }

    /**
     * @param array<string, mixed> $questao
     * @param array<string, string>|null $letras
     */
    private function letraEfetiva(array $questao, ?array $letras): string
    {
        $chave = (string) ($questao['questao_id'] ?? '');
        if ($letras !== null && array_key_exists($chave, $letras)) {
            return strtoupper(trim((string) $letras[$chave]));
        }
        if (($questao['situacao'] ?? '') === 'marcada' && !empty($questao['letra'])) {
            return strtoupper((string) $questao['letra']);
        }
        return '';
    }

    /**
     * @param array<string, mixed> $questao
     */
    private function alternativaDaLetra(array $questao, string $letra): ?int
    {
        if ($letra === '') {
            return null;
        }
        foreach ($questao['opcoes'] ?? [] as $opcao) {
            if (!is_array($opcao)) {
                continue;
            }
            if (strtoupper((string) ($opcao['letra'] ?? '')) === $letra) {
                return (int) $opcao['alternativa_id'];
            }
        }
        return null;
    }

    /**
     * Questões da leitura agrupadas pela matéria, com o gabarito e a nota que o acerto gera.
     *
     * @param array<string, mixed> $leitura
     * @return list<array{nome:string,prova_id:int,acertos:int,total:int,nota:float,maximo:float,questoes:list<array<string,mixed>>}>
     */
    public function gruposDaRevisao(array $leitura): array
    {
        $marcacoes = json_decode((string) ($leitura['marcacoes_json'] ?? ''), true);
        $questoes = is_array($marcacoes) ? ($marcacoes['questoes'] ?? []) : [];
        if (!is_array($questoes) || $questoes === []) {
            return [];
        }
        $questoes = $this->completarMaterias($leitura, $questoes);
        $idsProva = [];
        $idsQuestao = [];
        foreach ($questoes as $questao) {
            if (!is_array($questao)) {
                continue;
            }
            $provaId = (int) ($questao['prova_id'] ?? 0);
            if ($provaId <= 0) {
                $provaId = (int) ($leitura['prova_id'] ?? 0);
            }
            if ($provaId > 0) {
                $idsProva[$provaId] = $provaId;
            }
            $questaoId = (int) ($questao['questao_id'] ?? 0);
            if ($questaoId > 0) {
                $idsQuestao[$questaoId] = $questaoId;
            }
        }
        $nomes = $this->nomesDasProvas(array_values($idsProva));
        $gabarito = $this->letrasDoGabarito(array_values($idsQuestao));
        $grupos = [];
        $indice = -1;
        $provaAnterior = 0;
        foreach ($questoes as $questao) {
            if (!is_array($questao)) {
                continue;
            }
            $provaId = (int) ($questao['prova_id'] ?? 0);
            if ($provaId <= 0) {
                $provaId = (int) ($leitura['prova_id'] ?? 0);
            }
            $rotulo = trim((string) ($questao['rotulo'] ?? ''));
            $abre = $indice < 0
                || $provaId !== $provaAnterior
                || ($rotulo !== '' && $rotulo !== (string) $grupos[$indice]['nome']);
            if ($abre) {
                $nome = $rotulo !== '' ? $rotulo : (string) ($nomes[$provaId] ?? 'Prova');
                $indice++;
                $grupos[$indice] = [
                    'nome' => $nome,
                    'prova_id' => $provaId,
                    'acertos' => 0,
                    'total' => 0,
                    'nota' => 0.0,
                    'maximo' => 0.0,
                    'questoes' => [],
                ];
                $provaAnterior = $provaId;
            }
            $letra = strtoupper(trim((string) ($questao['letra'] ?? '')));
            $certa = (string) ($gabarito[(int) ($questao['questao_id'] ?? 0)] ?? '');
            $valor = (float) ($questao['valor'] ?? 0);
            $acertou = $certa !== '' && $letra === $certa;
            $questao['gabarito'] = $certa;
            $questao['acertou'] = $acertou;
            $grupos[$indice]['questoes'][] = $questao;
            $grupos[$indice]['total']++;
            $grupos[$indice]['maximo'] += $valor;
            if ($acertou) {
                $grupos[$indice]['acertos']++;
                $grupos[$indice]['nota'] += $valor;
            }
        }
        foreach ($grupos as $i => $grupo) {
            $grupos[$i]['nota'] = round((float) $grupo['nota'], 2);
            $grupos[$i]['maximo'] = round((float) $grupo['maximo'], 2);
        }
        return $grupos;
    }

    /**
     * A folha do bloco guarda a matéria em cada questão. Leituras antigas podem ter perdido isso.
     *
     * @param array<string, mixed> $leitura
     * @param list<array<string, mixed>> $questoes
     * @return list<array<string, mixed>>
     */
    private function completarMaterias(array $leitura, array $questoes): array
    {
        $faltaProva = false;
        foreach ($questoes as $questao) {
            if (is_array($questao) && (int) ($questao['prova_id'] ?? 0) <= 0) {
                $faltaProva = true;
                break;
            }
        }
        if (!$faltaProva) {
            return $questoes;
        }
        $folhaId = (int) ($leitura['folha_id'] ?? 0);
        $folha = $folhaId > 0 ? $this->buscarFolha($folhaId) : null;
        $pacote = is_array($folha) ? json_decode((string) ($folha['questoes_json'] ?? ''), true) : null;
        $origem = is_array($pacote) ? ($pacote['questoes'] ?? null) : null;
        if (!is_array($origem)) {
            return $questoes;
        }
        $mapa = [];
        $rotulo = '';
        foreach ($origem as $item) {
            if (!is_array($item)) {
                continue;
            }
            $nome = trim((string) ($item['rotulo'] ?? ''));
            if ($nome !== '') {
                $rotulo = $nome;
            }
            $questaoId = (int) ($item['questao_id'] ?? 0);
            if ($questaoId <= 0) {
                continue;
            }
            $mapa[$questaoId] = [
                'prova_id' => (int) ($item['prova_id'] ?? 0),
                'rotulo' => $rotulo,
            ];
        }
        foreach ($questoes as $i => $questao) {
            if (!is_array($questao)) {
                continue;
            }
            $info = $mapa[(int) ($questao['questao_id'] ?? 0)] ?? null;
            if ($info === null) {
                continue;
            }
            if ((int) ($questao['prova_id'] ?? 0) <= 0 && $info['prova_id'] > 0) {
                $questoes[$i]['prova_id'] = $info['prova_id'];
            }
            if (trim((string) ($questao['rotulo'] ?? '')) === '' && $info['rotulo'] !== '') {
                $questoes[$i]['rotulo'] = $info['rotulo'];
            }
        }
        return $questoes;
    }

    /**
     * @param list<int> $provaIds
     * @return array<int, string>
     */
    private function nomesDasProvas(array $provaIds): array
    {
        $ids = [];
        foreach ($provaIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $params = [];
        $marcadores = [];
        $i = 0;
        foreach ($ids as $id) {
            $chave = 'p' . $i;
            $marcadores[] = ':' . $chave;
            $params[$chave] = $id;
            $i++;
        }
        $linhas = $this->db->fetchAll(
            'SELECT p.id, COALESCE(NULLIF(TRIM(m.nome), \'\'), NULLIF(TRIM(p.titulo), \'\'), \'Prova\') AS nome
             FROM provas p
             LEFT JOIN materias m ON m.id = p.materia_id
             WHERE p.id IN (' . implode(',', $marcadores) . ')',
            $params
        );
        $nomes = [];
        foreach ($linhas as $linha) {
            $nomes[(int) $linha['id']] = (string) $linha['nome'];
        }
        return $nomes;
    }

    /**
     * Letra impressa na folha (A–E) da alternativa correta.
     *
     * @param list<int> $questaoIds
     * @return array<int, string>
     */
    private function letrasDoGabarito(array $questaoIds): array
    {
        $ids = [];
        foreach ($questaoIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $params = [];
        $marcadores = [];
        $i = 0;
        foreach ($ids as $id) {
            $chave = 'q' . $i;
            $marcadores[] = ':' . $chave;
            $params[$chave] = $id;
            $i++;
        }
        $linhas = $this->db->fetchAll(
            'SELECT questao_id, correta
             FROM provas_alternativas
             WHERE questao_id IN (' . implode(',', $marcadores) . ')
             ORDER BY questao_id ASC, ordem ASC, id ASC',
            $params
        );
        $letras = ['A', 'B', 'C', 'D', 'E'];
        $posicao = [];
        $gabarito = [];
        foreach ($linhas as $linha) {
            $questaoId = (int) $linha['questao_id'];
            $indice = $posicao[$questaoId] ?? 0;
            if ($indice >= 5) {
                continue;
            }
            $posicao[$questaoId] = $indice + 1;
            if ((int) $linha['correta'] === 1 && !isset($gabarito[$questaoId])) {
                $gabarito[$questaoId] = $letras[$indice];
            }
        }
        return $gabarito;
    }

    /**
     * @param list<array{nome:string,nota:float,acertos:int,total:int}> $materias
     */
    private function textoNotas(array $materias): string
    {
        $partes = [];
        foreach ($materias as $materia) {
            $partes[] = $materia['nome'] . ' ' . number_format((float) $materia['nota'], 2, ',', '.')
                . ' (' . (int) $materia['acertos'] . '/' . (int) $materia['total'] . ')';
        }
        return mb_substr(implode(' · ', $partes), 0, 500);
    }

    /**
     * @return array<int, int> alternativa_id => correta
     */
    private function mapaAlternativas(int $provaId): array
    {
        $linhas = $this->db->fetchAll(
            'SELECT a.id, a.correta
             FROM provas_alternativas a
             INNER JOIN provas_questoes q ON q.id = a.questao_id
             WHERE q.prova_id = :prova_id',
            ['prova_id' => $provaId]
        );
        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[(int) $linha['id']] = (int) $linha['correta'];
        }
        return $mapa;
    }

    /**
     * @return array{questoes:list<array<string,mixed>>,avisos:list<string>,dissertativas:int}
     */
    private function montarQuestoes(int $provaId): array
    {
        $linhas = $this->db->fetchAll(
            'SELECT id, tipo, valor, ordem FROM provas_questoes WHERE prova_id = :prova_id ORDER BY ordem ASC, id ASC',
            ['prova_id' => $provaId]
        );
        $alternativas = $this->db->fetchAll(
            'SELECT a.id, a.questao_id, a.correta
             FROM provas_alternativas a
             INNER JOIN provas_questoes q ON q.id = a.questao_id
             WHERE q.prova_id = :prova_id
             ORDER BY a.questao_id ASC, a.ordem ASC, a.id ASC',
            ['prova_id' => $provaId]
        );
        $porQuestao = [];
        foreach ($alternativas as $alt) {
            $porQuestao[(int) $alt['questao_id']][] = $alt;
        }
        $questoes = [];
        $avisos = [];
        $dissertativas = 0;
        $avisouCorte = false;
        $letras = ['A', 'B', 'C', 'D', 'E'];
        foreach ($linhas as $linha) {
            $tipo = (string) ($linha['tipo'] ?? '');
            if ($tipo === 'dissertativa') {
                $dissertativas++;
                continue;
            }
            if ($tipo !== 'multipla_escolha' && $tipo !== 'verdadeiro_falso') {
                continue;
            }
            $alts = $porQuestao[(int) $linha['id']] ?? [];
            if ($alts === []) {
                $avisos[] = 'A questão de ordem ' . (int) $linha['ordem'] . ' não tem alternativas e ficou de fora da folha.';
                continue;
            }
            if (count($alts) > 5 && !$avisouCorte) {
                $avisos[] = 'Questões com mais de cinco alternativas entram só com A até E.';
                $avisouCorte = true;
            }
            $lista = [];
            $temCorreta = false;
            foreach (array_slice($alts, 0, 5) as $i => $alt) {
                if ((int) $alt['correta'] === 1) {
                    $temCorreta = true;
                }
                $lista[] = ['letra' => $letras[$i], 'id' => (int) $alt['id']];
            }
            if (!$temCorreta) {
                $avisos[] = 'A questão ' . (count($questoes) + 1) . ' da folha não tem alternativa correta cadastrada.';
            }
            $questoes[] = [
                'questao_id' => (int) $linha['id'],
                'valor' => (float) $linha['valor'],
                'alternativas' => $lista,
            ];
        }
        if ($dissertativas > 0) {
            $avisos[] = $dissertativas === 1
                ? '1 questão dissertativa não entra na folha.'
                : $dissertativas . ' questões dissertativas não entram na folha.';
        }
        return ['questoes' => $questoes, 'avisos' => $avisos, 'dissertativas' => $dissertativas];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarProva(int $provaId, ?int $professorId): ?array
    {
        $sql = 'SELECT p.id, p.titulo, DATE(p.data_inicio) AS data_prova, p.professor_id, p.valor_total, m.nome AS materia_nome, prof.nome AS professor_nome
                FROM provas p
                LEFT JOIN materias m ON m.id = p.materia_id
                LEFT JOIN professores prof ON prof.id = p.professor_id
                WHERE p.id = :id AND p.deleted_at IS NULL';
        $params = ['id' => $provaId];
        if ($professorId !== null && $professorId > 0) {
            $sql .= ' AND p.professor_id = :professor_id';
            $params['professor_id'] = $professorId;
        }
        $prova = $this->db->fetch($sql, $params);
        return is_array($prova) ? $prova : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function turmasDaProva(int $provaId): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT t.id, t.nome
             FROM turmas t
             WHERE t.id IN (
                 SELECT turma_id FROM provas WHERE id = :prova_id AND turma_id IS NOT NULL
                 UNION
                 SELECT turma_id FROM provas_turmas WHERE prova_id = :prova_id_turmas
             )
             ORDER BY t.nome',
            ['prova_id' => $provaId, 'prova_id_turmas' => $provaId]
        );
    }

    /**
     * @return array{itens:list<array<string,mixed>>,pagina:int,paginas:int,total:int}
     */
    private function listarLeituras(int $provaId, int $pagina, int $conjunto = 0): array
    {
        if (!$this->tabelasProntas()) {
            return ['itens' => [], 'pagina' => 1, 'paginas' => 1, 'total' => 0];
        }
        $conjunto = $conjunto === 1 ? 1 : 0;
        $totalLinha = $this->db->fetch(
            'SELECT COUNT(*) AS total FROM leitor_gabarito_leituras WHERE prova_id = :prova_id AND conjunto = :conjunto',
            ['prova_id' => $provaId, 'conjunto' => $conjunto]
        );
        $total = (int) (is_array($totalLinha) ? $totalLinha['total'] : 0);
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, $pagina), $paginas);
        $offset = ($pagina - 1) * self::POR_PAGINA;
        $itens = $this->db->fetchAll(
            'SELECT l.id, l.status, l.mensagem, l.nota, l.aluno_id, l.criado_em, a.nome AS aluno_nome
             FROM leitor_gabarito_leituras l
             LEFT JOIN alunos a ON a.id = l.aluno_id
             WHERE l.prova_id = :prova_id AND l.conjunto = :conjunto
             ORDER BY l.id DESC
             LIMIT ' . self::POR_PAGINA . ' OFFSET ' . $offset,
            ['prova_id' => $provaId, 'conjunto' => $conjunto]
        );
        return ['itens' => $itens, 'pagina' => $pagina, 'paginas' => $paginas, 'total' => $total];
    }

    /**
     * @return array<int, array{folhas:int,pendentes:int,confirmadas:int}>
     */
    private function contagensPorProva(): array
    {
        if (!$this->tabelasProntas()) {
            return [];
        }
        $mapa = [];
        $folhas = $this->db->fetchAll('SELECT prova_id, COUNT(*) AS total FROM leitor_gabarito_folhas WHERE conjunto = 0 GROUP BY prova_id');
        foreach ($folhas as $linha) {
            $mapa[(int) $linha['prova_id']]['folhas'] = (int) $linha['total'];
        }
        $leituras = $this->db->fetchAll(
            "SELECT prova_id,
                    SUM(status <> 'confirmada') AS pendentes,
                    SUM(status = 'confirmada') AS confirmadas
             FROM leitor_gabarito_leituras
             WHERE conjunto = 0
             GROUP BY prova_id"
        );
        foreach ($leituras as $linha) {
            $id = (int) $linha['prova_id'];
            $mapa[$id]['pendentes'] = (int) $linha['pendentes'];
            $mapa[$id]['confirmadas'] = (int) $linha['confirmadas'];
        }
        return $mapa;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarFolha(int $folhaId): ?array
    {
        $folha = $this->db->fetch(
            'SELECT * FROM leitor_gabarito_folhas WHERE id = :id',
            ['id' => $folhaId]
        );
        return is_array($folha) ? $folha : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarFolhaPorAluno(int $provaId, int $alunoId, int $conjunto = 0): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }
        $folha = $this->db->fetch(
            'SELECT * FROM leitor_gabarito_folhas
             WHERE prova_id = :prova_id AND aluno_id = :aluno_id AND conjunto = :conjunto',
            ['prova_id' => $provaId, 'aluno_id' => $alunoId, 'conjunto' => $conjunto === 1 ? 1 : 0]
        );
        return is_array($folha) ? $folha : null;
    }

    private function statusLeitura(int $leituraId): string
    {
        $linha = $this->db->fetch(
            'SELECT status FROM leitor_gabarito_leituras WHERE id = :id',
            ['id' => $leituraId]
        );
        return is_array($linha) ? (string) $linha['status'] : '';
    }

    private function exigirTabelas(): void
    {
        if (!$this->tabelasProntas()) {
            throw new \RuntimeException('Rode as migrations do leitor de gabarito antes de usar o leitor.');
        }
    }

    private function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alunosDaTurma(int $turmaId): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT id, nome FROM alunos WHERE turma_id = :turma_id AND ativo = 1 ORDER BY nome',
                ['turma_id' => $turmaId]
            );
        } catch (\Throwable $e) {
            if (!preg_match("/Unknown column '[^']*ativo[^']*'/", $e->getMessage())) {
                throw $e;
            }
            error_log('leitor gabarito: ' . $e->getMessage());
            return $this->db->fetchAll(
                'SELECT id, nome FROM alunos WHERE turma_id = :turma_id ORDER BY nome',
                ['turma_id' => $turmaId]
            );
        }
    }

    private function chaveDuplicada(\Throwable $e): bool
    {
        $texto = $e->getMessage();
        return str_contains($texto, '1062') || str_contains($texto, 'Duplicate');
    }

    private function explicarFalha(\Throwable $e): string
    {
        if ($e instanceof \RuntimeException) {
            return $e->getMessage();
        }
        $texto = $e->getMessage();
        if (preg_match("/Unknown column '([^']+)'/", $texto, $coluna)) {
            return 'Não foi possível gerar a folha: falta a coluna ' . $coluna[1] . ' no banco.';
        }
        if (str_contains($texto, 'Base table or view not found')) {
            return 'As tabelas do leitor de gabarito ainda não estão prontas. Rode as migrations no Master.';
        }
        if ($this->chaveDuplicada($e)) {
            return 'Já existe uma folha deste aluno e o banco não deixa gravar outra. Rode a migration do leitor de gabarito.';
        }
        if (!str_contains($texto, 'SQLSTATE') && !preg_match('/password|senha|secret/i', $texto)) {
            return mb_substr(trim($texto), 0, 240);
        }
        return 'Não foi possível concluir. Tente de novo.';
    }

    private function diretorioBase(): string
    {
        return dirname(__DIR__, 4) . '/storage/uploads/leitor-gabarito';
    }

    private function slug(): string
    {
        $slug = defined('TENANT_SLUG') ? (string) TENANT_SLUG : 'escola';
        $limpo = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
        return $limpo !== '' && $limpo !== null ? $limpo : 'escola';
    }

    private function destinoSeguro(string $relativo): string
    {
        $relativo = str_replace('\\', '/', $relativo);
        if ($relativo === '' || str_contains($relativo, '..') || str_starts_with($relativo, '/')) {
            throw new \RuntimeException('Caminho inválido.');
        }
        return $this->diretorioBase() . '/' . $relativo;
    }

    private function lerArquivoDaFolha(string $relativo): string
    {
        if ($relativo === '' || $relativo === 'pendente') {
            return '';
        }
        try {
            $caminho = $this->caminhoAbsoluto($relativo);
        } catch (\Throwable $e) {
            return '';
        }
        $bytes = file_get_contents($caminho);
        return is_string($bytes) ? $bytes : '';
    }

    private function caminhoAbsoluto(string $relativo): string
    {
        $destino = $this->destinoSeguro($relativo);
        if (!is_file($destino)) {
            throw new \RuntimeException('Arquivo da leitura não encontrado.');
        }
        $base = realpath($this->diretorioBase());
        $real = realpath($destino);
        if ($base === false || $real === false || ($real !== $base && !str_starts_with($real, $base . DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Arquivo da leitura não encontrado.');
        }
        return $real;
    }
}
