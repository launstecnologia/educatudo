<?php
/**
 * Seed local da Vida Escolar no Colégio Educa.
 *
 * Cria um aluno de exemplo completo (cadastro civil + boletim homologado +
 * trajetória + histórico de transferência emitido) e também preenche fichas
 * de alunos já existentes para clicar no admin / aluno / pais.
 *
 * Uso (container PHP):
 *   php scripts/seed_vida_escolar_educa.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

ini_set('memory_limit', '512M');
set_time_limit(0);

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);
define('ENV_FILE_PATH', $basePath . '/.env');
if (!defined('TENANT_SLUG')) {
    define('TENANT_SLUG', 'educa');
}

require_once $basePath . '/config/app.php';
require_once $basePath . '/app/Core/Database.php';
require_once $basePath . '/app/Modulos/vida-escolar/Services/VidaEscolarService.php';
require_once $basePath . '/app/Services/HistoricoEscolarService.php';
require_once $basePath . '/app/Services/AlunoMovimentacaoService.php';

const TENANT_DB = 'educatudo_educa';
const MIGRATION_FILE = '2026_08_25_vida_escolar.sql';
const MIGRATION_HISTORICO = '2026_07_15_historico_escolar_oficial.sql';
const ESCOLA_ORIGEM = 'EMEF Professor Monteiro Lobato';
const NICK_HISTORICO = 've.hist01';
const SENHA_EXEMPLO = 'Teste@123';

function println(string $msg): void
{
    echo $msg . PHP_EOL;
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . PHP_EOL);
    exit($code);
}

final class SeedVidaEscolarEduca
{
    private $db;
    private \App\Modulos\VidaEscolar\Services\VidaEscolarService $vida;
    private int $adminId = 0;
    /** @var array<string,mixed> */
    private array $adminUser = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->vida = new \App\Modulos\VidaEscolar\Services\VidaEscolarService();
    }

    public function executar(): int
    {
        println('== Seed Vida Escolar — Colégio Educa ==');
        $this->garantirSchema();
        $this->garantirSchemaHistorico();
        $this->resolverAdmin();

        $ano = $this->anoAtual();
        println('Ano letivo das turmas ativas: ' . $ano);

        $saidas = [];
        $saidas[] = $this->popularAlunoHistoricoCompleto($ano);

        $regular = $this->escolherAlunoRegular($ano);
        $transf = $this->escolherAlunoTransferencia($ano, (int) ($regular['id'] ?? 0));
        if ($regular) {
            $saidas[] = $this->popularRegular($regular, $ano);
        }
        if ($transf) {
            $saidas[] = $this->popularTransferencia($transf, $ano);
        }

        println('');
        println('======== ONDE VER ========');
        println('Escola:  http://educa.localhost');
        println('Admin:   http://educa.localhost/admin   (admin@educa.local / Teste@123)');
        println('Menu:    Gestão Escolar → Vida Escolar');
        println('Lista:   http://educa.localhost/admin/vida-escolar');
        println('');
        foreach ($saidas as $s) {
            println($s['titulo']);
            println('  Ficha:  ' . $s['url_admin']);
            println('  Aluno:  nickname ' . $s['nickname'] . '  senha Teste@123  → http://educa.localhost/boletim');
            if ($s['email_pai'] !== '') {
                println('  Pais:   ' . $s['email_pai'] . ' / Teste@123 → http://educa.localhost/pais');
            }
            println('  ' . $s['o_que_ver']);
            println('');
        }
        println('Secretaria também entra em: secretaria@educa.local / Teste@123');
        return 0;
    }

    private function garantirSchema(): void
    {
        if ($this->vida->model()->schemaPronto()) {
            println('Schema boletim_fichas já existe.');
            return;
        }
        $path = BASE_PATH . '/database/migrations/' . MIGRATION_FILE;
        if (!is_file($path)) {
            fail('Migration não encontrada: ' . $path);
        }
        println('Aplicando ' . MIGRATION_FILE . ' no tenant local...');
        $sql = (string) file_get_contents($path);
        $pdo = $this->db->getPdo();
        $pdo->exec($sql);
        if (!$this->vida->model()->schemaPronto()) {
            fail('Schema não ficou pronto após aplicar a migration.');
        }
        try {
            $this->db->query(
                "INSERT INTO migrations_log (migration_file, status, mensagem_erro)
                 VALUES (:file, 'sucesso', NULL)",
                ['file' => MIGRATION_FILE]
            );
        } catch (\Throwable $e) {
            // log opcional
        }
        println('Schema aplicado.');
    }

    private function garantirSchemaHistorico(): void
    {
        $hist = new \App\Services\HistoricoEscolarService($this->db);
        if ($hist->schemaPronto()) {
            println('Schema historico_documentos já existe.');
            return;
        }
        $path = BASE_PATH . '/database/migrations/' . MIGRATION_HISTORICO;
        if (!is_file($path)) {
            println('AVISO: migration do histórico oficial não encontrada; emissão pode falhar.');
            return;
        }
        println('Aplicando ' . MIGRATION_HISTORICO . ' no tenant local...');
        $pdo = $this->db->getPdo();
        $pdo->exec((string) file_get_contents($path));
        if (!$hist->schemaPronto()) {
            println('AVISO: histórico oficial ainda não ficou pronto.');
            return;
        }
        println('Schema do histórico oficial aplicado.');
    }

    private function resolverAdmin(): void
    {
        $row = $this->db->fetch(
            "SELECT id, nome, tipo FROM usuarios
             WHERE email IN ('admin@educa.local', 'admin@educa.localhost')
             LIMIT 1"
        );
        if (!$row) {
            $row = $this->db->fetch('SELECT id, nome, tipo FROM usuarios ORDER BY id ASC LIMIT 1');
        }
        $this->adminId = (int) ($row['id'] ?? 1);
        $this->adminUser = [
            'id' => $this->adminId,
            'nome' => (string) ($row['nome'] ?? 'Admin Colégio Educa'),
            'tipo' => (string) ($row['tipo'] ?? 'admin'),
        ];
    }

    private function anoAtual(): int
    {
        $anos = $this->vida->model()->anosLetivosTurmas();
        return (int) ($anos[0] ?? date('Y'));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function escolherAlunoRegular(int $ano): ?array
    {
        $preferidos = ['ed266a01', 'ed256a01', 'ed246a01', 'ed239a01', 'ed236a01'];
        foreach ($preferidos as $nick) {
            $row = $this->buscarAlunoPorNick($nick, $ano);
            if ($row && !str_contains((string) $row['nome'], '(transf')) {
                return $row;
            }
        }
        return $this->db->fetch(
            "SELECT a.id, a.nome, a.nickname, a.email, a.turma_id, t.nome AS turma_nome,
                    t.serie AS turma_serie, t.ano_letivo AS turma_ano_letivo
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1 AND t.ano_letivo = :ano AND t.ativo = 1
               AND a.nome NOT LIKE '%transf%'
               AND a.nickname <> :nick
             ORDER BY t.nome ASC, a.nome ASC
             LIMIT 1",
            ['ano' => $ano, 'nick' => NICK_HISTORICO]
        ) ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function escolherAlunoTransferencia(int $ano, int $excetoId): ?array
    {
        $row = $this->db->fetch(
            "SELECT a.id, a.nome, a.nickname, a.email, a.turma_id, t.nome AS turma_nome,
                    t.serie AS turma_serie, t.ano_letivo AS turma_ano_letivo
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1 AND t.ano_letivo = :ano AND t.ativo = 1
               AND a.nome LIKE '%transf%'
               AND a.id <> :exceto
             ORDER BY t.nome ASC, a.nome ASC
             LIMIT 1",
            ['ano' => $ano, 'exceto' => $excetoId]
        );
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buscarAlunoPorNick(string $nick, int $ano): ?array
    {
        $row = $this->db->fetch(
            "SELECT a.id, a.nome, a.nickname, a.email, a.turma_id, t.nome AS turma_nome,
                    t.serie AS turma_serie, t.ano_letivo AS turma_ano_letivo
             FROM alunos a
             INNER JOIN turmas t ON t.id = a.turma_id
             WHERE a.nickname = :n AND a.ativo = 1 AND t.ano_letivo = :ano
             LIMIT 1",
            ['n' => $nick, 'ano' => $ano]
        );
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $aluno
     * @return array<string,string>
     */
    private function popularRegular(array $aluno, int $ano): array
    {
        $alunoId = (int) $aluno['id'];
        $turmaId = (int) $aluno['turma_id'];
        println('');
        println('→ Regular: ' . $aluno['nome'] . ' (#' . $alunoId . ', ' . ($aluno['turma_nome'] ?? '') . ')');

        $ok = $this->vida->garantirFicha($alunoId, $turmaId, $ano, $this->adminId);
        if (empty($ok['success'])) {
            fail('Ficha regular: ' . ($ok['error'] ?? 'falhou'));
        }
        $fichaId = (int) $ok['id'];
        $this->preencherBimestres($fichaId, [1, 2], 'calculada', 6.8, 8.9, 1);
        $this->vida->fecharBimestre($fichaId, 1, $this->adminUser);

        return [
            'titulo' => '1) Aluno regular (boletim do ano em curso)',
            'nickname' => (string) ($aluno['nickname'] ?? ''),
            'url_admin' => 'http://educa.localhost/admin/students/' . $alunoId . '/vida-escolar',
            'email_pai' => $this->emailPai((string) ($aluno['nickname'] ?? '')),
            'o_que_ver' => 'B1 fechado com notas calculadas, B2 aberto, B3/B4 vazios. Dá para fechar, reabrir e homologar.',
        ];
    }

    /**
     * @param array<string,mixed> $aluno
     * @return array<string,string>
     */
    private function popularTransferencia(array $aluno, int $ano): array
    {
        $alunoId = (int) $aluno['id'];
        $turmaId = (int) $aluno['turma_id'];
        println('');
        println('→ Transferência: ' . $aluno['nome'] . ' (#' . $alunoId . ', ' . ($aluno['turma_nome'] ?? '') . ')');

        $ok = $this->vida->garantirFicha($alunoId, $turmaId, $ano, $this->adminId);
        if (empty($ok['success'])) {
            fail('Ficha transferência: ' . ($ok['error'] ?? 'falhou'));
        }
        $fichaId = (int) $ok['id'];

        $docId = $this->vida->model()->criarDocumento([
            'aluno_id' => $alunoId,
            'tipo' => 'historico',
            'escola_emissora' => ESCOLA_ORIGEM,
            'data_emissao' => ($ano - 1) . '-12-15',
            'observacao' => 'Seed local — histórico da escola de origem (sem arquivo).',
            'enviado_por' => $this->adminId,
            'status' => 'validado',
        ]);

        $seriesAnteriores = [
            [(string) ($ano - 3), '6º Ano', 'Aprovado'],
            [(string) ($ano - 2), '7º Ano', 'Aprovado'],
            [(string) ($ano - 1), '8º Ano', 'Aprovado'],
        ];
        foreach ($seriesAnteriores as [$anoLetivo, $serie, $resultado]) {
            $resAno = $this->vida->adicionarAnoExterno($alunoId, [
                'ano_letivo' => $anoLetivo,
                'serie_ano' => $serie,
                'escola_nome' => ESCOLA_ORIGEM,
                'municipio' => 'Ribeirão Preto',
                'uf' => 'SP',
                'resultado' => $resultado,
                'documento_id' => $docId,
                'componentes' => [
                    ['componente_original' => 'Língua Portuguesa', 'nota' => '8,0'],
                    ['componente_original' => 'Matemática', 'nota' => '7,5'],
                    ['componente_original' => 'História', 'nota' => '8,5'],
                    ['componente_original' => 'Geografia', 'nota' => '7,8'],
                    ['componente_original' => 'Ciências', 'nota' => '8,2'],
                    ['componente_original' => 'Educação Física', 'nota' => '9,0'],
                    ['componente_original' => 'Arte', 'nota' => '8,8'],
                    ['componente_original' => 'Inglês', 'nota' => '7,2'],
                ],
            ], $this->adminUser);
            if (empty($resAno['success'])) {
                println('  ' . $anoLetivo . ' ' . $serie . ': ' . ($resAno['error'] ?? 'já existia'));
            }
        }

        $this->preencherBimestres($fichaId, [1], 'externa', 7.0, 8.5, 2, ESCOLA_ORIGEM, $docId);
        $this->preencherBimestres($fichaId, [2], 'calculada', 6.5, 8.2, 1);

        $impId = $this->vida->model()->criarImportacao([
            'aluno_id' => $alunoId,
            'documento_id' => $docId,
            'escola_origem' => ESCOLA_ORIGEM,
            'municipio' => 'Ribeirão Preto',
            'uf' => 'SP',
            'data_transferencia' => $ano . '-03-10',
            'status' => 'validada',
            'payload_json' => json_encode(['seed' => true], JSON_UNESCAPED_UNICODE),
            'criado_por' => $this->adminId,
        ]);
        $this->vida->model()->atualizarImportacao($impId, [
            'status' => 'validada',
            'validada_por' => $this->adminId,
            'validada_em' => date('Y-m-d H:i:s'),
            'resumo_json' => json_encode(['anos_anteriores' => 3, 'celulas_externas' => 1], JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'titulo' => '2) Aluno transferido (histórico vivo + B1 externo)',
            'nickname' => (string) ($aluno['nickname'] ?? ''),
            'url_admin' => 'http://educa.localhost/admin/students/' . $alunoId . '/vida-escolar',
            'email_pai' => $this->emailPai((string) ($aluno['nickname'] ?? '')),
            'o_que_ver' => 'Anos 2023–2025 da ' . ESCOLA_ORIGEM . ' no histórico vivo. B1 marcado como externa (¹). Documento e importação validados.',
        ];
    }

    private function preencherBimestres(
        int $fichaId,
        array $bimestres,
        string $origem,
        float $notaMin,
        float $notaMax,
        int $faltasBase,
        ?string $escolaOrigem = null,
        int $documentoId = 0
    ): void {
        $linhas = $this->vida->model()->listarLinhas($fichaId);
        if ($linhas === []) {
            println('  AVISO: ficha sem componentes (turma sem matriz/grade).');
            return;
        }
        $i = 0;
        foreach ($linhas as $linha) {
            $i++;
            foreach ($bimestres as $bim) {
                $cel = $this->vida->model()->findCelulaLinhaPeriodo((int) $linha['id'], (int) $bim);
                if (!$cel) {
                    continue;
                }
                $span = max(0.4, $notaMax - $notaMin);
                $nota = round($notaMin + fmod(($i * 0.37) + ((int) $bim * 0.21), $span), 1);
                $this->vida->model()->atualizarCelula((int) $cel['id'], [
                    'nota' => $nota,
                    'faltas' => $faltasBase + ($i % 3),
                    'origem' => $origem,
                    'status' => $origem === 'externa' ? 'fechada' : 'aberta',
                    'escola_origem' => $escolaOrigem,
                    'documento_id' => $documentoId > 0 ? $documentoId : null,
                    'nota_original' => $origem === 'externa' ? (string) $nota : null,
                ]);
                $this->vida->model()->registrarAuditoria([
                    'ficha_id' => $fichaId,
                    'celula_id' => (int) $cel['id'],
                    'acao' => $origem === 'externa' ? 'importar_externa' : 'seed_calculo',
                    'valor_novo' => json_encode(['nota' => $nota, 'origem' => $origem], JSON_UNESCAPED_UNICODE),
                    'usuario_id' => $this->adminId,
                    'usuario_nome' => $this->adminUser['nome'],
                    'usuario_perfil' => $this->adminUser['tipo'],
                ]);
            }
        }
        $this->recalcularFinais($fichaId);
        println('  Preenchidos bimestre(s) ' . implode(',', $bimestres) . ' (' . $origem . ') em ' . count($linhas) . ' componente(s).');
    }

    private function recalcularFinais(int $fichaId): void
    {
        foreach ($this->vida->model()->listarLinhas($fichaId) as $linha) {
            $notas = [];
            foreach ([1, 2, 3, 4] as $p) {
                $c = $this->vida->model()->findCelulaLinhaPeriodo((int) $linha['id'], $p);
                if ($c && is_numeric($c['nota'] ?? null)) {
                    $notas[] = (float) $c['nota'];
                }
            }
            $final = $this->vida->model()->findCelulaLinhaPeriodo((int) $linha['id'], 0);
            if (!$final) {
                continue;
            }
            $media = $notas === [] ? null : round(array_sum($notas) / count($notas), 2);
            $this->vida->model()->atualizarCelula((int) $final['id'], [
                'nota' => $media,
                'origem' => $media === null ? 'vazia' : 'calculada',
            ]);
        }
    }

    private function emailPai(string $nickname): string
    {
        if ($nickname === '') {
            return '';
        }
        $email = 'pai.' . $nickname . '@educa.local';
        $existe = $this->db->fetch('SELECT id FROM responsaveis WHERE email = :e LIMIT 1', ['e' => $email]);
        return $existe ? $email : '';
    }

    /**
     * Aluno de exemplo com cadastro civil, boletim homologado e histórico de transferência.
     *
     * @return array<string,string>
     */
    private function popularAlunoHistoricoCompleto(int $ano): array
    {
        println('');
        println('→ Aluno completo para emitir histórico de transferência');

        $turma = $this->escolherTurmaComMatriz($ano);
        if (!$turma) {
            fail('Nenhuma turma ativa com matriz em ' . $ano . '. Rode popular_colegio_educa.php antes.');
        }
        $aluno = $this->garantirAlunoExemplo($turma, $ano);
        $alunoId = (int) $aluno['id'];
        $turmaId = (int) $aluno['turma_id'];
        $this->completarCadastroCivil($alunoId);
        $this->completarUnidade((int) ($aluno['unidade_id'] ?? 0));
        $this->vincularMatricula($alunoId, $turmaId, $ano);
        $this->garantirResponsavel($alunoId, (string) ($aluno['nome'] ?? 'Helena Cristina Monteiro'));

        $ok = $this->vida->garantirFicha($alunoId, $turmaId, $ano, $this->adminId);
        if (empty($ok['success'])) {
            fail('Ficha do aluno exemplo: ' . ($ok['error'] ?? 'falhou'));
        }
        $fichaId = (int) $ok['id'];

        $docId = $this->garantirDocumentoOrigem($alunoId, $ano);
        $this->lancarTrajetoriaExterna($alunoId, $ano, $docId);
        $this->lancarAnoInternoAnterior($alunoId, $ano);

        $this->preencherBimestres($fichaId, [1, 2, 3, 4], 'calculada', 6.8, 9.2, 1);
        foreach ([1, 2, 3, 4] as $bim) {
            $this->vida->fecharBimestre($fichaId, $bim, $this->adminUser);
        }
        $hom = $this->vida->homologarFicha($fichaId, $this->adminUser);
        if (empty($hom['success'])) {
            println('  Homologação: ' . ($hom['error'] ?? 'já homologada'));
        } else {
            println('  Boletim ' . $ano . ' homologado.');
        }
        $this->marcarAnoAtualTransferido($alunoId, $ano);

        $histUrl = $this->gerarHistoricoTransferencia($alunoId);
        $urlFicha = 'http://educa.localhost/admin/students/' . $alunoId . '?tab=vida-escolar';

        return [
            'titulo' => '0) Aluno exemplo — histórico de transferência',
            'nickname' => NICK_HISTORICO,
            'url_admin' => $urlFicha,
            'email_pai' => $this->emailPai(NICK_HISTORICO),
            'o_que_ver' => 'Cadastro civil completo, 4 bimestres homologados, trajetória 5º–8º (EMEF) + 8º interno + ' . $ano . ' nesta escola. Histórico: ' . $histUrl,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function escolherTurmaComMatriz(int $ano): ?array
    {
        $row = $this->db->fetch(
            "SELECT t.id, t.nome, t.serie, t.ano_letivo, t.matriz_curricular_id
             FROM turmas t
             WHERE t.ativo = 1 AND t.ano_letivo = :ano
               AND t.matriz_curricular_id IS NOT NULL AND t.matriz_curricular_id > 0
               AND (t.serie LIKE '%9%' OR t.nome LIKE '%9º%' OR t.nome LIKE '%9o%')
             ORDER BY t.nome ASC
             LIMIT 1",
            ['ano' => $ano]
        );
        if ($row) {
            return $row;
        }
        return $this->db->fetch(
            "SELECT t.id, t.nome, t.serie, t.ano_letivo, t.matriz_curricular_id
             FROM turmas t
             WHERE t.ativo = 1 AND t.ano_letivo = :ano
               AND t.matriz_curricular_id IS NOT NULL AND t.matriz_curricular_id > 0
             ORDER BY t.nome ASC
             LIMIT 1",
            ['ano' => $ano]
        ) ?: null;
    }

    /**
     * @param array<string,mixed> $turma
     * @return array<string,mixed>
     */
    private function garantirAlunoExemplo(array $turma, int $ano): array
    {
        $exist = $this->db->fetch(
            'SELECT * FROM alunos WHERE nickname = :n LIMIT 1',
            ['n' => NICK_HISTORICO]
        );
        $hash = password_hash(SENHA_EXEMPLO, PASSWORD_DEFAULT);
        $unidadeId = (int) ($this->db->fetch(
            'SELECT unidade_id FROM alunos WHERE turma_id = :t AND unidade_id IS NOT NULL LIMIT 1',
            ['t' => (int) $turma['id']]
        )['unidade_id'] ?? 0);
        if ($unidadeId <= 0) {
            $u = $this->db->fetch('SELECT id FROM unidades WHERE ativo = 1 ORDER BY id ASC LIMIT 1');
            $unidadeId = (int) ($u['id'] ?? 0);
        }
        $nasc = ($ano - 15) . '-04-18';
        $nome = 'Helena Cristina Monteiro';
        if ($exist) {
            $this->db->query(
                'UPDATE alunos SET nome = :nome, email = :email, senha_hash = :hash, turma_id = :turma,
                        serie = :serie, unidade_id = :uid, data_nasc = :nasc, ativo = 1, status = :st
                 WHERE id = :id',
                [
                    'nome' => $nome,
                    'email' => NICK_HISTORICO . '@educa.local',
                    'hash' => $hash,
                    'turma' => (int) $turma['id'],
                    'serie' => (string) ($turma['serie'] ?? '9º Ano'),
                    'uid' => $unidadeId > 0 ? $unidadeId : null,
                    'nasc' => $nasc,
                    'st' => 'ACTIVE',
                    'id' => (int) $exist['id'],
                ]
            );
            $row = $this->db->fetch('SELECT * FROM alunos WHERE id = :id', ['id' => (int) $exist['id']]);
            println('  Aluno já existia: #' . (int) $exist['id'] . ' ' . $nome);
            return is_array($row) ? $row : $exist;
        }
        $id = (int) $this->db->insert(
            "INSERT INTO alunos
                (nome, nickname, email, senha_hash, ra, turma_id, serie, unidade_id, data_nasc, ativo, pagante, status, password, primeiro_acesso)
             VALUES
                (:nome, :nick, :email, :hash, :ra, :turma, :serie, :uid, :nasc, 1, 1, 'ACTIVE', '', 0)",
            [
                'nome' => $nome,
                'nick' => NICK_HISTORICO,
                'email' => NICK_HISTORICO . '@educa.local',
                'hash' => $hash,
                'ra' => 'VEHIST01',
                'turma' => (int) $turma['id'],
                'serie' => (string) ($turma['serie'] ?? '9º Ano'),
                'uid' => $unidadeId > 0 ? $unidadeId : null,
                'nasc' => $nasc,
            ]
        );
        println('  Criado aluno #' . $id . ' ' . $nome . ' na turma ' . ($turma['nome'] ?? ''));
        $row = $this->db->fetch('SELECT * FROM alunos WHERE id = :id', ['id' => $id]);
        return is_array($row) ? $row : ['id' => $id, 'turma_id' => (int) $turma['id'], 'unidade_id' => $unidadeId, 'nome' => $nome];
    }

    private function completarCadastroCivil(int $alunoId): void
    {
        $sobrenome = 'Monteiro';
        $params = [
            'id' => $alunoId,
            'cpf' => '390.533.447-05',
            'rg' => '48.221.903-1',
            'telefone' => '1633332200',
            'celular' => '16991234001',
            'logradouro' => 'Rua das Palmeiras',
            'numero' => '245',
            'bairro' => 'Jardim América',
            'cidade' => 'Ribeirão Preto',
            'uf' => 'SP',
            'cep' => '14020140',
            'nome_mae' => 'Ana Paula ' . $sobrenome,
            'nome_pai' => 'Carlos Eduardo ' . $sobrenome,
            'nacionalidade' => 'Brasileira',
            'naturalidade' => 'Ribeirão Preto',
            'uf_nascimento' => 'SP',
            'cor_raca' => 'branca',
            'orgao_emissor' => 'SSP',
            'uf_rg' => 'SP',
            'certidao' => '12345671201801000000000000001',
            'livro' => '012',
            'folha' => '087',
            'termo' => '4512',
            'nis' => '12345678901',
            'zona' => 'urbana',
            'pais' => 'Brasil',
            'whatsapp' => '16991234001',
        ];
        try {
            $this->db->query(
                "UPDATE alunos SET
                    cpf = :cpf, rg = :rg, telefone = :telefone, celular = :celular, whatsapp = :whatsapp,
                    logradouro = :logradouro, numero = :numero, bairro = :bairro, cidade = :cidade,
                    uf = :uf, cep = :cep, nome_mae = :nome_mae, nome_pai = :nome_pai,
                    nacionalidade = :nacionalidade, naturalidade = :naturalidade,
                    uf_nascimento = :uf_nascimento, cor_raca = :cor_raca,
                    orgao_emissor = :orgao_emissor, uf_rg = :uf_rg,
                    certidao_nascimento = :certidao, certidao_livro = :livro,
                    certidao_folha = :folha, certidao_termo = :termo,
                    nis = :nis, zona = :zona, pais = :pais
                 WHERE id = :id",
                $params
            );
            println('  Cadastro civil preenchido (CPF, filiação, endereço, certidão).');
        } catch (\Throwable $e) {
            println('  AVISO cadastro civil: ' . $e->getMessage());
        }
    }

    private function completarUnidade(int $unidadeId): void
    {
        if ($unidadeId <= 0) {
            $u = $this->db->fetch('SELECT id FROM unidades ORDER BY id ASC LIMIT 1');
            $unidadeId = (int) ($u['id'] ?? 0);
        }
        if ($unidadeId <= 0) {
            println('  AVISO: nenhuma unidade para completar INEP/CNPJ/diretor.');
            return;
        }
        try {
            $this->db->query(
                "UPDATE unidades SET
                    inep = COALESCE(NULLIF(inep, ''), '35012345'),
                    cnpj = COALESCE(NULLIF(cnpj, ''), '12.345.678/0001-90'),
                    diretor_nome = COALESCE(NULLIF(diretor_nome, ''), 'Marina Alves Ferreira'),
                    secretario_nome = COALESCE(NULLIF(secretario_nome, ''), 'Paulo Henrique Costa'),
                    diretor_registro = COALESCE(NULLIF(diretor_registro, ''), 'RG 12.345-6'),
                    secretario_registro = COALESCE(NULLIF(secretario_registro, ''), 'RG 98.765-4'),
                    razao_social = COALESCE(NULLIF(razao_social, ''), 'Colégio Educa Ensino Ltda')
                 WHERE id = :id",
                ['id' => $unidadeId]
            );
            println('  Unidade #' . $unidadeId . ' com INEP, CNPJ, diretor e secretário.');
        } catch (\Throwable $e) {
            println('  AVISO unidade: ' . $e->getMessage());
        }
    }

    private function garantirResponsavel(int $alunoId, string $nomeAluno): void
    {
        $email = 'pai.' . NICK_HISTORICO . '@educa.local';
        $exist = $this->db->fetch('SELECT id FROM responsaveis WHERE email = :e LIMIT 1', ['e' => $email]);
        $hash = password_hash(SENHA_EXEMPLO, PASSWORD_DEFAULT);
        if ($exist) {
            $pid = (int) $exist['id'];
        } else {
            $pid = (int) $this->db->insert(
                'INSERT INTO responsaveis (nome, email, senha_hash, cpf, telefone, ativo, password)
                 VALUES (:nome, :email, :senha_hash, :cpf, :telefone, 1, :password)',
                [
                    'nome' => 'Ana Paula Monteiro',
                    'email' => $email,
                    'senha_hash' => $hash,
                    'cpf' => '529.982.247-25',
                    'telefone' => '16991234001',
                    'password' => '',
                ]
            );
        }
        $ja = $this->db->fetch(
            'SELECT id FROM alunos_responsaveis WHERE aluno_id = :a AND responsavel_id = :p LIMIT 1',
            ['a' => $alunoId, 'p' => $pid]
        );
        if (!$ja) {
            $this->db->insert(
                'INSERT INTO alunos_responsaveis (aluno_id, responsavel_id, tipo_vinculo, is_financeiro, ativo)
                 VALUES (:a, :p, :tv, 1, 1)',
                ['a' => $alunoId, 'p' => $pid, 'tv' => 'mae']
            );
        }
        println('  Responsável: ' . $email . ' / ' . SENHA_EXEMPLO . ' (mãe de ' . $nomeAluno . ')');
    }

    private function vincularMatricula(int $alunoId, int $turmaId, int $ano): void
    {
        $anoRow = $this->db->fetch('SELECT id FROM ano_letivo WHERE ano = :ano ORDER BY id DESC LIMIT 1', ['ano' => $ano]);
        $anoId = (int) ($anoRow['id'] ?? 0);
        if ($anoId <= 0) {
            return;
        }
        try {
            $mov = new \App\Services\AlunoMovimentacaoService();
            $mov->vincularAlunoTurma($alunoId, $turmaId, $anoId, true, $ano . '-02-03');
            println('  Matrícula vinculada à turma.');
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'já possui') === false) {
                println('  AVISO matrícula: ' . $e->getMessage());
            }
        }
    }

    private function garantirDocumentoOrigem(int $alunoId, int $ano): int
    {
        $exist = $this->db->fetch(
            "SELECT id FROM vida_escolar_documentos
             WHERE aluno_id = :id AND tipo = 'historico' AND escola_emissora = :esc
             ORDER BY id DESC LIMIT 1",
            ['id' => $alunoId, 'esc' => ESCOLA_ORIGEM]
        );
        if ($exist) {
            return (int) $exist['id'];
        }
        return $this->vida->model()->criarDocumento([
            'aluno_id' => $alunoId,
            'tipo' => 'historico',
            'escola_emissora' => ESCOLA_ORIGEM,
            'data_emissao' => ($ano - 1) . '-12-12',
            'observacao' => 'Histórico da escola de origem (seed completo).',
            'enviado_por' => $this->adminId,
            'status' => 'validado',
        ]);
    }

    private function lancarTrajetoriaExterna(int $alunoId, int $ano, int $docId): void
    {
        $series = [
            [(string) ($ano - 4), '5º Ano'],
            [(string) ($ano - 3), '6º Ano'],
            [(string) ($ano - 2), '7º Ano'],
        ];
        foreach ($series as $i => [$anoLetivo, $serie]) {
            $ja = false;
            foreach ($this->vida->model()->listarAnosEscolarizacao($alunoId) as $a) {
                if ((string) $a['ano_letivo'] === $anoLetivo && ($a['origem'] ?? '') === 'externo') {
                    $ja = true;
                    break;
                }
            }
            if ($ja) {
                continue;
            }
            $res = $this->vida->adicionarAnoExterno($alunoId, [
                'ano_letivo' => $anoLetivo,
                'serie_ano' => $serie,
                'escola_nome' => ESCOLA_ORIGEM,
                'escola_inep' => '35098765',
                'municipio' => 'Ribeirão Preto',
                'uf' => 'SP',
                'resultado' => 'Aprovado',
                'carga_horaria_total' => 800,
                'documento_id' => $docId,
                'componentes' => $this->componentesExemplo($i + 1),
            ], $this->adminUser);
            println('  ' . $anoLetivo . ' ' . $serie . ' (externo): ' . (!empty($res['success']) ? 'ok' : ($res['error'] ?? 'falhou')));
        }
    }

    private function lancarAnoInternoAnterior(int $alunoId, int $ano): void
    {
        $anoLetivo = (string) ($ano - 1);
        foreach ($this->vida->model()->listarAnosEscolarizacao($alunoId) as $a) {
            if ((string) $a['ano_letivo'] === $anoLetivo && ($a['origem'] ?? '') === 'interno') {
                return;
            }
        }
        $escola = 'Colégio Educa';
        try {
            $cfg = $this->db->fetch("SELECT valor FROM configuracoes WHERE chave IN ('school_name','nome_escola') LIMIT 1");
            if (is_array($cfg) && trim((string) ($cfg['valor'] ?? '')) !== '') {
                $escola = (string) $cfg['valor'];
            }
        } catch (\Throwable $e) {
        }
        $anoId = $this->vida->model()->criarAnoEscolarizacao([
            'aluno_id' => $alunoId,
            'ano_letivo' => $anoLetivo,
            'serie_ano' => '8º Ano',
            'origem' => 'interno',
            'escola_nome' => $escola,
            'resultado' => 'Aprovado',
            'carga_horaria_total' => 800,
        ]);
        $ordem = 0;
        foreach ($this->componentesExemplo(8) as $comp) {
            $ordem++;
            $this->vida->model()->criarComponenteEscolarizacao([
                'ano_id' => $anoId,
                'componente_original' => $comp['componente_original'],
                'nota_original' => $comp['nota'],
                'nota_convertida' => str_replace(',', '.', $comp['nota']),
                'carga_horaria' => $comp['carga_horaria'],
                'ordem' => $ordem,
            ]);
        }
        println('  ' . $anoLetivo . ' 8º Ano (interno nesta escola): ok');
    }

    /**
     * @return list<array{componente_original:string,nota:string,carga_horaria:int}>
     */
    private function componentesExemplo(int $semente): array
    {
        $base = [
            ['Língua Portuguesa', 160],
            ['Matemática', 160],
            ['História', 80],
            ['Geografia', 80],
            ['Ciências', 80],
            ['Língua Inglesa', 80],
            ['Arte', 40],
            ['Educação Física', 80],
            ['Ensino Religioso', 40],
        ];
        $out = [];
        $i = 0;
        foreach ($base as [$nome, $ch]) {
            $i++;
            $nota = round(6.8 + fmod(($semente * 0.41) + ($i * 0.27), 2.6), 1);
            $out[] = [
                'componente_original' => $nome,
                'nota' => number_format($nota, 1, ',', ''),
                'carga_horaria' => $ch,
            ];
        }
        return $out;
    }

    private function marcarAnoAtualTransferido(int $alunoId, int $ano): void
    {
        try {
            $this->db->query(
                "UPDATE escolarizacao_anos
                 SET resultado = 'Transferido'
                 WHERE aluno_id = :id AND origem = 'interno' AND ano_letivo = :ano",
                ['id' => $alunoId, 'ano' => (string) $ano]
            );
        } catch (\Throwable $e) {
        }
    }

    private function gerarHistoricoTransferencia(int $alunoId): string
    {
        $hist = new \App\Services\HistoricoEscolarService($this->db);
        if (!$hist->schemaPronto()) {
            return '(schema do histórico ausente)';
        }
        foreach ($hist->listarPorAluno($alunoId) as $doc) {
            $st = (string) ($doc['status'] ?? '');
            if (in_array($st, ['Emitido', 'Assinado', 'Entregue'], true)
                && ($doc['finalidade'] ?? '') === 'Transferencia') {
                println('  Histórico de transferência já emitido (#' . (int) $doc['id'] . ', ' . $st . ').');
                return 'http://educa.localhost/admin/students/' . $alunoId . '/historico-escolar/' . (int) $doc['id'];
            }
        }
        $obs = 'Documento de transferência gerado pelo seed de exemplo. Conferir dados da unidade e assinar no admin.';
        $res = $hist->gerarRascunho($alunoId, 'Transferencia', $this->adminId, $obs);
        if (empty($res['success'])) {
            println('  Histórico rascunho: ' . ($res['error'] ?? 'falhou'));
            return '(falhou gerar rascunho)';
        }
        $hid = (int) ($res['id'] ?? 0);
        $conf = $hist->conferir($hid, $this->adminId);
        if (empty($conf['success'])) {
            println('  Conferir histórico: ' . ($conf['error'] ?? 'falhou'));
        }
        $emi = $hist->emitir($hid, $this->adminId);
        if (empty($emi['success'])) {
            println('  Emitir histórico: ' . ($emi['error'] ?? 'falhou'));
            if (!empty($emi['checklist']['itens'])) {
                foreach ($emi['checklist']['itens'] as $item) {
                    if (empty($item['ok'])) {
                        println('    pendente: ' . ($item['mensagem'] ?? ''));
                    }
                }
            }
        } else {
            println('  Histórico de transferência emitido (#' . $hid . ').');
            $hist->assinar($hid, $this->adminId, (string) $this->adminUser['nome'], 'Diretor', '127.0.0.1');
            $hist->assinar($hid, $this->adminId, (string) $this->adminUser['nome'], 'Secretario_Escolar', '127.0.0.1');
            println('  Assinado por diretor e secretário.');
        }
        return 'http://educa.localhost/admin/students/' . $alunoId . '/historico-escolar/' . $hid;
    }
}

$host = (string) env('DB_HOST', 'mysql');
$port = (int) env('DB_PORT', 3306);
$dbUser = (string) env('DB_USER', 'root');
$dbPass = (string) env('DB_PASS', 'root');
if (!in_array($host, ['mysql', 'localhost', '127.0.0.1', '::1'], true)) {
    fail("Abortado: DB_HOST={$host} não parece local.");
}

try {
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . TENANT_DB . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
    Database::setCurrentInstance(Database::createFromPdo($pdo, [
        'host' => $host,
        'port' => $port,
        'name' => TENANT_DB,
        'user' => $dbUser,
        'pass' => $dbPass,
    ]));
    exit((new SeedVidaEscolarEduca(Database::getInstance()))->executar());
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    exit(1);
}
