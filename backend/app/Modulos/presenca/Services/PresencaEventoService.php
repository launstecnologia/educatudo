<?php

require_once __DIR__ . '/../../../Core/Database.php';
require_once __DIR__ . '/../Models/PresencaEvento.php';
require_once __DIR__ . '/../Models/PresencaIdentificador.php';
require_once __DIR__ . '/../Models/PresencaIntegracao.php';
require_once __DIR__ . '/PresencaAplicacaoService.php';
require_once __DIR__ . '/PresencaConsolidacaoService.php';

class PresencaEventoService
{
    private $db;
    private $eventos;
    private $identificadores;
    private $aplicacao;
    private $consolidacao;

    public function __construct(
        ?Database $db = null,
        ?PresencaEvento $eventos = null,
        ?PresencaIdentificador $identificadores = null,
        ?PresencaAplicacaoService $aplicacao = null,
        ?PresencaConsolidacaoService $consolidacao = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->eventos = $eventos ?? new PresencaEvento();
        $this->identificadores = $identificadores ?? new PresencaIdentificador();
        $this->aplicacao = $aplicacao ?? new PresencaAplicacaoService($this->db);
        $this->consolidacao = $consolidacao ?? new PresencaConsolidacaoService($this->db);
    }

    /**
     * @param array{
     *   aluno_id?:int,
     *   tipo:string,
     *   ocorrido_em:string,
     *   origem:string,
     *   id_externo:string,
     *   integracao_id?:int,
     *   identificador_bruto?:?string,
     *   registrado_por?:?int,
     *   mapeamento?:?string
     * } $dados
     * @return array{evento_id:int,duplicado:bool,aluno_id:?int,aplicacao:array<string,mixed>}
     */
    public function registrar(array $dados): array
    {
        if (!$this->eventos->tabelasProntas()) {
            throw new RuntimeException('Rode a migration 2026_08_22_gestao_presenca.sql no Master.');
        }
        $alunoId = (int) ($dados['aluno_id'] ?? 0);
        $identificador = trim((string) ($dados['identificador_bruto'] ?? ''));
        $mapa = (string) ($dados['mapeamento'] ?? 'ra');
        if ($alunoId <= 0 && $identificador !== '') {
            $alunoId = $this->resolverAluno($identificador, $mapa) ?? 0;
        }
        $insert = $this->eventos->inserir([
            'aluno_id' => $alunoId,
            'tipo' => $dados['tipo'],
            'ocorrido_em' => $dados['ocorrido_em'],
            'origem' => $dados['origem'],
            'integracao_id' => $dados['integracao_id'] ?? null,
            'id_externo' => $dados['id_externo'],
            'identificador_bruto' => $identificador !== '' ? $identificador : null,
            'registrado_por' => $dados['registrado_por'] ?? null,
        ]);
        $eventoId = (int) $insert['id'];
        if ($insert['duplicado']) {
            return [
                'evento_id' => $eventoId,
                'duplicado' => true,
                'aluno_id' => $alunoId > 0 ? $alunoId : null,
                'aplicacao' => ['aplicadas' => 0, 'puladas' => 0, 'erro' => null],
            ];
        }
        $aplicacao = ['aplicadas' => 0, 'puladas' => 0, 'erro' => null];
        if ($alunoId <= 0) {
            $this->eventos->marcarProcessado($eventoId, 'Aluno não identificado.');
            return [
                'evento_id' => $eventoId,
                'duplicado' => false,
                'aluno_id' => null,
                'aplicacao' => $aplicacao,
            ];
        }
        $origemMarca = ((string) ($dados['origem'] ?? '')) === 'manual_secretaria'
            ? PresencaAplicacaoService::ORIGEM_AJUSTE
            : PresencaAplicacaoService::ORIGEM_ENTRADA_SAIDA;
        $data = substr((string) $dados['ocorrido_em'], 0, 10);
        try {
            $aplicacao = $this->aplicacao->aplicarDiaAluno($alunoId, $data, $origemMarca);
            $this->eventos->marcarProcessado($eventoId, $aplicacao['erro']);
            $this->consolidacao->consolidarAlunoNaData($alunoId, $data);
        } catch (Throwable $e) {
            $this->eventos->marcarProcessado($eventoId, $e->getMessage());
            $aplicacao['erro'] = $e->getMessage();
        }
        return [
            'evento_id' => $eventoId,
            'duplicado' => false,
            'aluno_id' => $alunoId,
            'aplicacao' => $aplicacao,
        ];
    }

    public function resolverAluno(string $valor, string $mapeamento): ?int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        $doCracha = $this->identificadores->findAlunoId('cartao', $valor)
            ?? $this->identificadores->findAlunoId('externo', $valor);
        if ($doCracha) {
            return $doCracha;
        }
        if ($mapeamento === 'aluno_id' && ctype_digit($valor)) {
            $row = $this->db->fetch('SELECT id FROM alunos WHERE id = :id AND ativo = 1 LIMIT 1', ['id' => (int) $valor]);
            return $row ? (int) $row['id'] : null;
        }
        if ($mapeamento === 'codigo_aluno') {
            $row = $this->db->fetch(
                'SELECT id FROM alunos WHERE codigo_aluno = :v AND ativo = 1 LIMIT 1',
                ['v' => $valor]
            );
            return $row ? (int) $row['id'] : null;
        }
        $row = $this->db->fetch(
            'SELECT id FROM alunos WHERE (ra = :v OR codigo_aluno = :v2) AND ativo = 1 LIMIT 1',
            ['v' => $valor, 'v2' => $valor]
        );
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Ponte do reconhecimento facial: não quebra o fluxo se o módulo/migration não existir.
     */
    public static function fromFacial(int $alunoId, string $tipo, DateTimeImmutable $quando, string $providerPresenceId, ?int $userId): void
    {
        try {
            if ($alunoId <= 0 || $providerPresenceId === '') {
                return;
            }
            if (!in_array($tipo, ['entrada', 'saida'], true)) {
                return;
            }
            if (!class_exists('LayoutHelper', false)) {
                require_once dirname(__DIR__, 3) . '/Core/LayoutHelper.php';
            }
            if (class_exists('LayoutHelper', false) && !LayoutHelper::isModuleEnabled('presenca')) {
                return;
            }
            $model = new PresencaEvento();
            if (!$model->tabelasProntas()) {
                return;
            }
            (new self())->registrar([
                'aluno_id' => $alunoId,
                'tipo' => $tipo,
                'ocorrido_em' => $quando->format('Y-m-d H:i:s'),
                'origem' => 'facial',
                'id_externo' => 'facial:' . $providerPresenceId,
                'registrado_por' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('PresencaEventoService::fromFacial: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array{id:int,nome:string,ra:?string,turma_nome:?string}>
     */
    public function buscarAlunos(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $like = '%' . $q . '%';
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.nome, a.ra, t.nome AS turma_nome
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.ativo = 1 AND (a.nome LIKE :q OR a.ra LIKE :q2 OR a.codigo_aluno LIKE :q3)
             ORDER BY a.nome ASC
             LIMIT {$limit}",
            ['q' => $like, 'q2' => $like, 'q3' => $like]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'nome' => (string) $r['nome'],
                'ra' => $r['ra'] ?? null,
                'turma_nome' => $r['turma_nome'] ?? null,
            ];
        }
        return $out;
    }
}
