<?php

namespace App\Painel\Admin\Services;

use App\Core\Database;

/**
 * Lê em logs_auditoria quem criou, alterou ou excluiu registros
 * de curso, série, componente, matriz e calendário letivo.
 */
class TrilhaCadastroService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(string $modulo, int $limite = 100): array
    {
        $fontes = $this->definicao($modulo);
        if ($fontes === [] || $limite < 1 || !$this->db->tableExists('logs_auditoria')) {
            return [];
        }

        $itens = [];
        foreach ($fontes as $fonte) {
            $rows = $this->buscar(
                $fonte['likes'],
                $fonte['excluir'] ?? [],
                min(150, max($limite, 80))
            );
            foreach ($rows as $row) {
                $item = $this->montar($row, $fonte['regras']);
                if ($item !== null) {
                    $itens[] = $item;
                }
            }
        }

        usort($itens, static function (array $a, array $b): int {
            return strcmp((string) $b['instante'], (string) $a['instante']);
        });

        $comDiff = $this->comDiferencas($itens);

        return array_slice($comDiff, 0, min($limite, 100));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function definicao(string $modulo): array
    {
        return match ($modulo) {
            'cursos_series' => $this->cursosSeries(),
            'componentes' => $this->componentes(),
            'matrizes' => $this->matrizes(),
            'calendario' => $this->calendario(),
            'professores' => $this->professores(),
            'salas' => $this->salas(),
            'turmas' => $this->turmas(),
            'alunos' => $this->alunos(),
            'grade' => $this->grade(),
            default => [],
        };
    }

    /**
     * @param list<string> $likes
     * @param list<string> $excluir
     * @return list<array<string,mixed>>
     */
    private function buscar(array $likes, array $excluir, int $limite): array
    {
        if ($likes === []) {
            return [];
        }

        $partes = [];
        $params = [];
        foreach (array_values($likes) as $i => $like) {
            $chave = 'like' . $i;
            $partes[] = 'a.resource_accessed LIKE :' . $chave;
            $params[$chave] = $like;
        }
        $sqlExc = '';
        foreach (array_values($excluir) as $i => $like) {
            $chave = 'exc' . $i;
            $sqlExc .= ' AND a.resource_accessed NOT LIKE :' . $chave;
            $params[$chave] = $like;
        }
        $sqlExc .= " AND NOT (
                (a.action LIKE :acao_view OR a.action LIKE :acao_edit OR a.action LIKE :acao_access)
                AND (a.request_payload IS NULL OR a.request_payload IN ('[]', '{}', 'null', ''))
            )";
        $params['acao_view'] = 'VIEW%';
        $params['acao_edit'] = 'EDIT%';
        $params['acao_access'] = 'ACCESS%';

        $rows = $this->db->fetchAll(
            "SELECT a.created_at, a.user_id, a.user_role, a.action,
                    a.resource_accessed, a.request_payload, u.nome AS usuario_nome
             FROM logs_auditoria a
             LEFT JOIN usuarios u ON u.id = a.user_id AND a.user_role IN ('admin', 'admin_escola')
             WHERE (" . implode(' OR ', $partes) . ")
             {$sqlExc}
             ORDER BY a.created_at DESC
             LIMIT " . (int) $limite,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $regras
     * @return array<string,mixed>|null
     */
    private function montar(array $row, array $regras): ?array
    {
        $caminho = $this->caminho((string) ($row['resource_accessed'] ?? ''));
        $payload = $this->payload($row['request_payload'] ?? null);
        $regra = null;
        $id = '';
        foreach ($regras as $candidata) {
            $regex = (string) ($candidata['regex'] ?? '');
            if ($regex === '' || !preg_match($regex, $caminho, $m)) {
                continue;
            }
            $regra = $candidata;
            if (!empty($candidata['id_grupo']) && isset($m[(int) $candidata['id_grupo']])) {
                $id = (string) $m[(int) $candidata['id_grupo']];
            }
            break;
        }
        if ($regra === null) {
            return null;
        }

        $acao = (string) ($regra['acao'] ?? '');
        if ($acao === 'metodo') {
            $acao = $this->acaoPeloMetodo($payload, (string) ($row['action'] ?? ''));
            if ($acao === '' ) {
                $acao = (string) ($regra['senao'] ?? '');
            }
            if ($acao === '') {
                return null;
            }
        } elseif ($acao === 'pelo_id') {
            $campoId = (string) ($regra['campo_id'] ?? 'id');
            $idPayload = (int) ($payload[$campoId] ?? 0);
            $acao = $idPayload > 0 ? 'Alterado' : 'Criado';
            if ($id === '' && $idPayload > 0) {
                $id = (string) $idPayload;
            }
        }

        if ($id === '') {
            $campoId = (string) ($regra['id_payload'] ?? '');
            if ($campoId !== '' && (string) ($payload[$campoId] ?? '') !== '') {
                $id = (string) $payload[$campoId];
            }
        }
        if (($regra['id_slug_de'] ?? '') === 'nome') {
            $id = $this->slugificar((string) ($payload['nome'] ?? ''));
        }

        $limpar = null;
        if (!empty($regra['limpar_campo'])) {
            $idsLimpar = $payload[$regra['limpar_campo']] ?? [];
            if (!is_array($idsLimpar)) {
                $idsLimpar = [$idsLimpar];
            }
            $limpar = [
                'chave' => (string) ($regra['limpar_chave'] ?? ''),
                'ids' => array_values(array_filter(array_map('strval', $idsLimpar), static fn (string $v): bool => $v !== '')),
            ];
        }

        $snapshot = $this->snapshot(
            $payload,
            is_array($regra['rotulos'] ?? null) ? $regra['rotulos'] : [],
            is_array($regra['booleanos'] ?? null) ? $regra['booleanos'] : [],
            is_array($regra['datas'] ?? null) ? $regra['datas'] : [],
            is_array($regra['mapas'] ?? null) ? $regra['mapas'] : [],
            is_array($regra['listas'] ?? null) ? $regra['listas'] : [],
            $acao !== 'Excluído'
        );
        if ($snapshot === [] && $acao !== 'Excluído' && !empty($regra['nota'])) {
            $snapshot[(string) $regra['nota']] = (string) ($regra['nota_valor'] ?? 'Alterado');
        }

        $quando = $this->quando((string) ($row['created_at'] ?? ''));

        return [
            'instante' => (string) ($row['created_at'] ?? ''),
            'quando' => $quando,
            'acao' => $acao,
            'usuario' => trim((string) ($row['usuario_nome'] ?? '')) !== ''
                ? (string) $row['usuario_nome']
                : 'Não identificado',
            'registro' => $this->registro((string) ($regra['recurso'] ?? 'Registro'), $payload, $id),
            'chave' => (string) ($regra['chave'] ?? 'registro'),
            'registro_id' => $id,
            'snapshot' => $snapshot,
            'limpar' => $limpar,
            'nao_parear' => !empty($regra['nao_parear']),
        ];
    }

    /**
     * @param list<array<string,mixed>> $itens Mais novos primeiro.
     * @return list<array<string,mixed>>
     */
    private function comDiferencas(array $itens): array
    {
        $cronologico = array_reverse($itens);
        $porId = [];
        foreach ($cronologico as $i => $item) {
            $chave = (string) ($item['chave'] ?? '');
            $id = (string) ($item['registro_id'] ?? '');
            $acao = (string) ($item['acao'] ?? '');
            $snapshot = is_array($item['snapshot'] ?? null) ? $item['snapshot'] : [];
            $marca = $chave . ':' . $id;
            $anterior = null;
            $parear = empty($item['nao_parear']);

            if ($parear && $acao === 'Criado') {
                $anterior = null;
            } elseif ($parear && $id !== '' && isset($porId[$marca])) {
                $anterior = $porId[$marca];
            }

            if (!$parear) {
                // Ação pontual (status, importação): não substitui o snapshot do cadastro.
            } elseif ($acao === 'Excluído') {
                unset($porId[$marca]);
                $limpar = is_array($item['limpar'] ?? null) ? $item['limpar'] : null;
                if ($limpar !== null && $limpar['chave'] !== '') {
                    foreach ($limpar['ids'] as $idLimpar) {
                        unset($porId[$limpar['chave'] . ':' . $idLimpar]);
                    }
                }
            } elseif ($id !== '' && $acao !== 'Criado') {
                $porId[$marca] = $snapshot;
            } elseif ($acao === 'Criado' && $id !== '') {
                $porId[$marca] = $snapshot;
            }

            $cronologico[$i]['alteracoes'] = $this->diferencas($snapshot, $anterior, $acao);
        }

        $saida = [];
        foreach (array_reverse($cronologico) as $item) {
            $saida[] = [
                'quando' => $item['quando'],
                'acao' => $item['acao'],
                'usuario' => $item['usuario'],
                'registro' => $item['registro'],
                'alteracoes' => $item['alteracoes'],
            ];
        }

        return $saida;
    }

    /**
     * @param array<string,string> $snapshot
     * @param array<string,string>|null $anterior
     * @return list<array{campo:string,antes:string,depois:string}>
     */
    private function diferencas(array $snapshot, ?array $anterior, string $acao): array
    {
        if ($acao === 'Excluído' && $snapshot === []) {
            $antes = '—';
            if (is_array($anterior)) {
                foreach ($anterior as $valor) {
                    if ($valor !== '' && $valor !== '—') {
                        $antes = $valor;
                        break;
                    }
                }
            }

            return [[
                'campo' => 'Registro',
                'antes' => $antes,
                'depois' => 'Excluído',
            ]];
        }

        $campos = array_keys($snapshot);
        if (is_array($anterior)) {
            foreach (array_keys($anterior) as $campo) {
                if (!in_array($campo, $campos, true)) {
                    $campos[] = $campo;
                }
            }
        }

        $saida = [];
        foreach ($campos as $campo) {
            $depois = (string) ($snapshot[$campo] ?? '—');
            $antes = is_array($anterior) ? (string) ($anterior[$campo] ?? '—') : '';
            if (is_array($anterior) && $antes === $depois) {
                continue;
            }
            $saida[] = [
                'campo' => (string) $campo,
                'antes' => $antes,
                'depois' => $depois,
            ];
        }

        if ($acao === 'Excluído' && $saida === []) {
            $saida[] = ['campo' => 'Registro', 'antes' => '—', 'depois' => 'Excluído'];
        }

        return $saida;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $rotulos
     * @param list<string> $booleanos
     * @param list<string> $datas
     * @param array<string,array<string,string>> $mapas
     * @param list<string> $listas
     * @return array<string,string>
     */
    private function snapshot(
        array $payload,
        array $rotulos,
        array $booleanos,
        array $datas,
        array $mapas,
        array $listas,
        bool $preencherBooleanos
    ): array {
        $saida = [];
        foreach ($rotulos as $campo => $rotulo) {
            $presente = array_key_exists($campo, $payload);
            if (!$presente && !($preencherBooleanos && in_array($campo, $booleanos, true))) {
                continue;
            }
            $valor = $presente ? $payload[$campo] : '0';
            if (preg_match('/senha|password|token|csrf/i', (string) $campo)) {
                continue;
            }
            if ($campo === 'componentes' && is_array($valor)) {
                $saida[$rotulo] = $this->formatarComponentes($valor);
                continue;
            }
            if ($campo === 'itens' && is_array($valor)) {
                $saida[$rotulo] = count($valor) . ' aulas';
                continue;
            }
            if (in_array($campo, $listas, true) && is_array($valor)) {
                $saida[$rotulo] = $this->formatarLista($valor);
                continue;
            }
            if ($campo === 'ativo') {
                $saida[$rotulo] = $this->simNao($valor) === 'Sim' ? 'Ativo' : 'Inativo';
                continue;
            }
            if ($campo === 'pai_id') {
                $idPai = (int) (is_array($valor) ? 0 : $valor);
                $saida[$rotulo] = $idPai > 0 ? (string) $idPai : '—';
                continue;
            }
            if (in_array($campo, $booleanos, true)) {
                $saida[$rotulo] = $this->simNao($valor);
                continue;
            }
            if (isset($mapas[$campo]) && !is_array($valor)) {
                $chave = (string) $valor;
                $saida[$rotulo] = $mapas[$campo][$chave] ?? ($chave !== '' ? $chave : '—');
                continue;
            }
            if (in_array($campo, $datas, true) && !is_array($valor)) {
                $saida[$rotulo] = $this->formatarData((string) $valor);
                continue;
            }
            $saida[$rotulo] = is_array($valor) ? $this->formatarLista($valor) : $this->texto($valor);
        }

        return $saida;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function registro(string $recurso, array $payload, string $id): string
    {
        $titulo = '';
        foreach (['nome', 'descricao', 'codigo', 'slug'] as $campo) {
            $valor = trim((string) ($payload[$campo] ?? ''));
            if ($valor !== '') {
                $titulo = $valor;
                break;
            }
        }
        if ($titulo === '' && isset($payload['ano']) && (string) $payload['ano'] !== '') {
            $titulo = (string) $payload['ano'];
        }
        if (mb_strlen($titulo) > 80) {
            $titulo = mb_substr($titulo, 0, 77) . '...';
        }
        if ($titulo !== '') {
            return $recurso . ' · ' . $titulo;
        }
        if ($id !== '') {
            return $recurso . ' #' . $id;
        }

        return $recurso;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function acaoPeloMetodo(array $payload, string $action): string
    {
        $metodo = strtoupper((string) ($payload['_method'] ?? ''));
        $action = strtoupper($action);
        if ($metodo === 'DELETE' || str_starts_with($action, 'DELETE')) {
            return 'Excluído';
        }
        if ($metodo === 'PUT' || str_starts_with($action, 'UPDATE')) {
            return 'Alterado';
        }

        return '';
    }

    /**
     * @param mixed $bruto
     * @return array<string,mixed>
     */
    private function payload($bruto): array
    {
        if (is_array($bruto)) {
            return $bruto;
        }
        if (!is_string($bruto) || $bruto === '') {
            return [];
        }
        $dados = json_decode($bruto, true);

        return is_array($dados) ? $dados : [];
    }

    private function caminho(string $resource): string
    {
        $path = parse_url($resource, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $resource;
        }
        $path = rawurldecode($path);
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    private function quando(string $createdAt): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $createdAt);
        if ($dt instanceof \DateTime) {
            return $dt->format('d/m/Y H:i');
        }

        return $createdAt;
    }

    /**
     * @param array<int|string,mixed> $lista
     */
    private function formatarComponentes(array $lista): string
    {
        $linhas = [];
        foreach ($lista as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (int) ($item['materia_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $aulas = trim((string) ($item['aulas_semana'] ?? ''));
            $obrig = $this->simNao($item['obrigatorio'] ?? '0') === 'Sim' ? 'obrigatório' : 'optativo';
            $ordem = trim((string) ($item['ordem_boletim'] ?? ''));
            $linhas[] = '#' . $id . ' · ' . ($aulas !== '' ? $aulas : '0') . ' aulas/sem · ' . $obrig
                . ($ordem !== '' ? ' · ordem ' . $ordem : '');
        }
        sort($linhas);

        return $linhas === [] ? '—' : implode('; ', $linhas);
    }

    /**
     * @param array<int|string,mixed> $lista
     */
    private function formatarLista(array $lista): string
    {
        $planos = [];
        foreach ($lista as $valor) {
            if (is_array($valor)) {
                continue;
            }
            $texto = trim((string) $valor);
            if ($texto !== '') {
                $planos[] = $texto;
            }
        }

        return $planos === [] ? '—' : implode(', ', $planos);
    }

    private function formatarData(string $valor): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $this->texto($valor);
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $valor);

        return $dt instanceof \DateTime ? $dt->format('d/m/Y') : $this->texto($valor);
    }

    /**
     * @param mixed $valor
     */
    private function simNao($valor): string
    {
        if (is_array($valor)) {
            return $valor === [] ? 'Não' : 'Sim';
        }
        $texto = strtolower(trim((string) $valor));

        return in_array($texto, ['1', 'on', 'true', 'sim', 'yes'], true) ? 'Sim' : 'Não';
    }

    /**
     * @param mixed $valor
     */
    private function texto($valor): string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? '—' : $texto;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function professores(): array
    {
        $rotulos = [
            'nome' => 'Nome',
            'email' => 'E-mail',
            'materias' => 'Componentes',
            'turmas' => 'Turmas',
            'ativo' => 'Status',
            'pagante' => 'Pagante',
        ];
        $booleanos = ['ativo', 'pagante'];
        $listas = ['materias', 'turmas'];
        $edicao = $this->regra('#/admin/teachers/(\d+)$#', 'metodo', 'professor', 'Professor', $rotulos, $booleanos, [], [], 1, $listas);
        $status = $this->regra('#/admin/teachers/(\d+)/toggle-status$#', 'Alterado', 'professor-status', 'Professor', [], [], [], [], 1);
        $status['nao_parear'] = true;
        $status['nota'] = 'Status';
        $pagante = $this->regra('#/admin/teachers/(\d+)/toggle-pagante$#', 'Alterado', 'professor-pagante', 'Professor', [], [], [], [], 1);
        $pagante['nao_parear'] = true;
        $pagante['nota'] = 'Pagante';

        return [[
            'likes' => ['%/admin/teachers', '%/admin/teachers/%'],
            'excluir' => ['%/export%', '%/import%', '%/dados%', '%/create%', '%/edit%', '%/documentos%'],
            'regras' => [
                $this->regra('#/admin/teachers$#', 'Criado', 'professor', 'Professor', $rotulos, $booleanos, [], [], null, $listas),
                $edicao,
                $this->regra('#/admin/teachers/(\d+)/delete$#', 'Excluído', 'professor', 'Professor', ['nome' => 'Nome'], [], [], [], 1),
                $status,
                $pagante,
            ],
        ]];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function salas(): array
    {
        $rotulos = [
            'nome' => 'Nome',
            'codigo' => 'Código',
            'tipo' => 'Tipo',
            'bloco' => 'Bloco',
            'andar' => 'Andar',
            'capacidade' => 'Capacidade',
            'responsavel_nome' => 'Responsável',
            'ativo' => 'Status',
        ];
        $mapas = ['tipo' => [
            'sala' => 'Sala de Aula',
            'laboratorio' => 'Laboratório',
            'quadra' => 'Quadra',
            'biblioteca' => 'Biblioteca',
            'secretaria' => 'Secretaria',
            'cantina' => 'Cantina',
            'deposito' => 'Depósito',
            'outro' => 'Outro',
        ]];

        return [[
            'likes' => ['%/admin/salas', '%/admin/salas/%'],
            'excluir' => ['%/dados%'],
            'regras' => [
                $this->regra('#/admin/salas$#', 'Criado', 'sala', 'Sala', $rotulos, ['ativo'], [], $mapas),
                $this->regra('#/admin/salas/(\d+)$#', 'metodo', 'sala', 'Sala', $rotulos, ['ativo'], [], $mapas, 1),
            ],
        ]];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function turmas(): array
    {
        $rotulos = [
            'nome' => 'Nome',
            'ano_letivo_id' => 'Ano letivo',
            'curso_novo_id' => 'Curso',
            'curso_id' => 'Curso',
            'serie_id' => 'Série',
            'serie' => 'Série',
            'matriz_curricular_id' => 'Matriz curricular',
            'turno' => 'Turno',
            'sala_padrao_id' => 'Sala',
            'vagas' => 'Vagas',
            'ativo' => 'Status',
            'observacoes' => 'Observações',
        ];
        $mapas = ['turno' => [
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
        ]];
        $edicao = $this->regra('#/admin/turmas/(\d+)$#', 'metodo', 'turma', 'Turma', $rotulos, ['ativo'], [], $mapas, 1);
        $edicao['senao'] = 'Alterado';
        $status = $this->regra('#/admin/turmas/(\d+)/toggle-status$#', 'Alterado', 'turma-status', 'Turma', [], [], [], [], 1);
        $status['nao_parear'] = true;
        $status['nota'] = 'Status';
        $vinculo = $this->regra('#/admin/turmas/(\d+)/vincular-aluno$#', 'Alterado', 'turma-aluno', 'Turma', [
            'aluno_id' => 'Aluno',
            'student_id' => 'Aluno',
        ], [], [], [], 1);
        $vinculo['nao_parear'] = true;

        return [[
            'likes' => ['%/admin/turmas', '%/admin/turmas/%'],
            'excluir' => ['%/dados%', '%/buscar-alunos%', '%/lista-chamada%', '%/export%', '%/by-ano%', '%/by-serie%'],
            'regras' => [
                $this->regra('#/admin/turmas$#', 'Criado', 'turma', 'Turma', $rotulos, ['ativo'], [], $mapas),
                $edicao,
                $this->regraLote('#/admin/turmas/bulk-delete$#', 'turma-lote', 'Turmas', 'turma', 'turma_ids', 'Turmas'),
                $status,
                $vinculo,
            ],
        ]];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function alunos(): array
    {
        $rotulos = [
            'nome' => 'Nome',
            'nome_social' => 'Nome social',
            'codigo_aluno' => 'Código',
            'ra' => 'RA',
            'ra_digito' => 'Dígito do RA',
            'ra_uf' => 'UF do RA',
            'cgm' => 'CGM',
            'sexo' => 'Sexo',
            'unidade_id' => 'Unidade',
            'nickname' => 'Usuário',
            'ativo' => 'Status',
            'pagante' => 'Pagante',
            'primeiro_acesso' => 'Primeiro acesso',
            'data_nasc' => 'Nascimento',
            'cpf' => 'CPF',
            'rg' => 'RG',
            'orgao_emissor' => 'Órgão emissor',
            'uf_rg' => 'UF do RG',
            'cep' => 'CEP',
            'logradouro' => 'Logradouro',
            'numero' => 'Número',
            'complemento' => 'Complemento',
            'bairro' => 'Bairro',
            'cidade' => 'Cidade',
            'uf' => 'UF',
            'telefone' => 'Telefone',
            'celular' => 'Celular',
            'whatsapp' => 'WhatsApp',
            'email' => 'E-mail',
            'email_secundario' => 'E-mail secundário',
            'nome_mae' => 'Nome da mãe',
            'nome_pai' => 'Nome do pai',
            'codigo_inep' => 'Código INEP',
            'nacionalidade' => 'Nacionalidade',
            'cor_raca' => 'Cor/raça',
            'uf_nascimento' => 'UF de nascimento',
            'naturalidade' => 'Naturalidade',
            'nis' => 'NIS',
            'zona' => 'Zona',
            'certidao_nascimento' => 'Certidão',
            'certidao_livro' => 'Livro',
            'certidao_folha' => 'Folha',
            'certidao_termo' => 'Termo',
            'pais' => 'País',
            'passaporte' => 'Passaporte',
            'rne' => 'RNE',
            'tipo_sanguineo' => 'Tipo sanguíneo',
            'plano_saude' => 'Plano de saúde',
            'plano_saude_numero' => 'Número do plano',
            'hospital_referencia' => 'Hospital de referência',
            'alergias' => 'Alergias',
            'medicamentos_uso' => 'Medicamentos',
            'condicoes_cronicas' => 'Condições crônicas',
            'deficiencias_obs' => 'Deficiências',
            'contato_emergencia_nome' => 'Contato de emergência',
            'contato_emergencia_telefone' => 'Telefone de emergência',
            'contato_emergencia_parentesco' => 'Parentesco',
            'restricoes_alimentares' => 'Restrições alimentares',
            'alimentacao_obs' => 'Alimentação',
            'usa_transporte_escolar' => 'Transporte escolar',
            'transporte_tipo' => 'Tipo de transporte',
            'transporte_rota' => 'Rota',
            'transporte_ponto' => 'Ponto',
            'transporte_responsavel' => 'Responsável do transporte',
            'transporte_telefone' => 'Telefone do transporte',
            'observacoes_gerais' => 'Observações',
            'turma_id' => 'Turma',
        ];
        $booleanos = ['ativo', 'pagante', 'usa_transporte_escolar'];
        $datas = ['data_nasc'];
        $mapas = [
            'sexo' => ['F' => 'Feminino', 'M' => 'Masculino', 'N' => 'Neutro / outro'],
            'primeiro_acesso' => ['1' => 'Sim', '0' => 'Não'],
        ];
        $edicao = $this->regra('#/admin/students/(\d+)$#', 'metodo', 'aluno', 'Aluno', $rotulos, $booleanos, $datas, $mapas, 1);
        $ativar = $this->regra('#/admin/students/(\d+)/activate$#', 'Alterado', 'aluno-status', 'Aluno', [], [], [], [], 1);
        $ativar['nao_parear'] = true;
        $ativar['nota'] = 'Status';
        $ativar['nota_valor'] = 'Ativo';
        $inativar = $this->regra('#/admin/students/(\d+)/inactivate$#', 'Alterado', 'aluno-status', 'Aluno', [], [], [], [], 1);
        $inativar['nao_parear'] = true;
        $inativar['nota'] = 'Status';
        $inativar['nota_valor'] = 'Inativo';
        $pagante = $this->regra('#/admin/students/(\d+)/toggle-pagante$#', 'Alterado', 'aluno-pagante', 'Aluno', [], [], [], [], 1);
        $pagante['nao_parear'] = true;
        $pagante['nota'] = 'Pagante';
        $matricula = $this->regra('#/admin/students/(\d+)/matricula$#', 'Alterado', 'aluno-matricula', 'Matrícula', [
            'turma_id' => 'Turma',
        ], [], [], [], 1);
        $matricula['nao_parear'] = true;
        $encerrar = $this->regra('#/admin/students/(\d+)/matricula/(\d+)/encerrar$#', 'Alterado', 'aluno-matricula', 'Matrícula', [], [], [], [], 1);
        $encerrar['nao_parear'] = true;
        $encerrar['nota'] = 'Matrícula';
        $encerrar['nota_valor'] = 'Encerrada';

        return [[
            'likes' => ['%/admin/students', '%/admin/students/%'],
            'excluir' => [
                '%/boletim%', '%/historico%', '%/documentos%', '%/declaracoes%', '%/foto%',
                '%/password%', '%/analise%', '%/acessar%', '%/export%', '%/template%',
                '%/upload%', '%/import%', '%/transfer%', '%/remanejamento%', '%/exercicio%',
                '%/create%', '%/edit%', '%/tab/%', '%/pdf%', '%/auditoria%',
            ],
            'regras' => [
                $this->regra('#/admin/students$#', 'Criado', 'aluno', 'Aluno', $rotulos, $booleanos, $datas, $mapas),
                $edicao,
                $this->regra('#/admin/students/(\d+)/excluir$#', 'Excluído', 'aluno', 'Aluno', ['nome' => 'Nome'], [], [], [], 1),
                $ativar,
                $inativar,
                $pagante,
                $matricula,
                $encerrar,
            ],
        ]];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function grade(): array
    {
        $rotulos = [
            'dia_semana' => 'Dia',
            'periodo' => 'Período',
            'horario_de' => 'Horário de',
            'horario_ate' => 'Horário até',
            'turma_id' => 'Turma',
            'professor_id' => 'Professor',
            'materia_id' => 'Componente',
        ];
        $mapas = [
            'dia_semana' => [
                '1' => 'Segunda-feira',
                '2' => 'Terça-feira',
                '3' => 'Quarta-feira',
                '4' => 'Quinta-feira',
                '5' => 'Sexta-feira',
                '6' => 'Sábado',
                '7' => 'Domingo',
            ],
            'periodo' => ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'],
        ];
        $edicao = $this->regra('#/admin/grade-horaria/(\d+)$#', 'metodo', 'aula', 'Aula', $rotulos, [], [], $mapas, 1);
        $edicao['senao'] = 'Alterado';
        $importacao = $this->regra('#/admin/grade-horaria/salvar-importacao-ia$#', 'Criado', 'aula-importacao', 'Importação', [
            'itens' => 'Aulas',
        ]);
        $importacao['nao_parear'] = true;
        $importacao['nota'] = 'Aulas';
        $importacao['nota_valor'] = 'Importadas';

        return [[
            'likes' => ['%/admin/grade-horaria', '%/admin/grade-horaria/%'],
            'excluir' => ['%/pdf%', '%/modelo%', '%/processar-%', '%/dados%'],
            'regras' => [
                $this->regra('#/admin/grade-horaria$#', 'Criado', 'aula', 'Aula', $rotulos, [], [], $mapas),
                $edicao,
                $importacao,
            ],
        ]];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function cursosSeries(): array
    {
        $curso = [
            'nome' => 'Curso',
            'tipo' => 'Tipo',
            'possui_serie' => 'Possui série',
            'cor' => 'Cor',
            'ativo' => 'Status',
        ];
        $cursoBool = ['possui_serie', 'ativo'];
        $cursoMapa = ['tipo' => ['regular' => 'Regular', 'extra' => 'Extra']];
        $serie = [
            'curso_id' => 'Curso',
            'nome' => 'Série / ano escolar',
            'ativo' => 'Status',
        ];

        return [
            [
                'likes' => ['%/admin/curso', '%/admin/curso/%'],
                'excluir' => ['%/reordenar%', '%/importar%', '%/modelo-csv%', '%/dados%'],
                'regras' => [
                    $this->regra('#/admin/curso$#', 'Criado', 'curso', 'Curso', $curso, $cursoBool, [], $cursoMapa),
                    $this->regra('#/admin/curso/(\d+)/update$#', 'Alterado', 'curso', 'Curso', $curso, $cursoBool, [], $cursoMapa, 1),
                    $this->regra('#/admin/curso/(\d+)/delete$#', 'Excluído', 'curso', 'Curso', ['nome' => 'Curso'], [], [], [], 1),
                    $this->regraLote('#/admin/curso/bulk-delete$#', 'curso-lote', 'Cursos', 'curso', 'curso_ids', 'Cursos'),
                ],
            ],
            [
                'likes' => ['%/admin/serie', '%/admin/serie/%'],
                'excluir' => ['%/reordenar%', '%/dados%'],
                'regras' => [
                    $this->regra('#/admin/serie$#', 'Criado', 'serie', 'Série', $serie, ['ativo']),
                    $this->regra('#/admin/serie/(\d+)/update$#', 'Alterado', 'serie', 'Série', $serie, ['ativo'], [], [], 1),
                    $this->regra('#/admin/serie/(\d+)/delete$#', 'Excluído', 'serie', 'Série', ['nome' => 'Série'], [], [], [], 1),
                    $this->regraLote('#/admin/serie/bulk-delete$#', 'serie-lote', 'Séries', 'serie', 'serie_ids', 'Séries'),
                ],
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function componentes(): array
    {
        $rotulos = [
            'nome' => 'Nome',
            'codigo' => 'Código',
            'sigla' => 'Sigla',
            'pai_id' => 'Unidade curricular',
            'area_conhecimento' => 'Área do conhecimento',
            'tipo' => 'Tipo',
            'cor' => 'Cor',
            'ordem' => 'Ordem',
            'descricao' => 'Descrição',
            'etapa_infantil' => 'Educação Infantil',
            'etapa_fund_i' => 'Ensino Fundamental I',
            'etapa_fund_ii' => 'Ensino Fundamental II',
            'etapa_medio' => 'Ensino Médio',
            'permite_avaliacao' => 'Permite avaliação',
            'permite_frequencia' => 'Permite frequência',
            'permite_plano_aula' => 'Permite plano de aula',
            'permite_diario' => 'Permite diário',
            'ativo' => 'Status',
        ];
        $booleanos = ['permite_avaliacao', 'permite_frequencia', 'permite_plano_aula', 'permite_diario', 'ativo'];
        $mapas = [
            'area_conhecimento' => [
                'linguagens' => 'Linguagens e suas Tecnologias',
                'matematica' => 'Matemática e suas Tecnologias',
                'ciencias_natureza' => 'Ciências da Natureza e suas Tecnologias',
                'ciencias_humanas' => 'Ciências Humanas e Sociais Aplicadas',
                'ensino_religioso' => 'Ensino Religioso',
                'interdisciplinar' => 'Interdisciplinar',
                'tecnologia' => 'Tecnologia',
                'computacao' => 'Computação',
                'arte' => 'Linguagens/Arte',
                'outra' => 'Outra',
            ],
            'tipo' => [
                'formacao_geral' => 'Formação Geral',
                'lingua_adicional' => 'Língua Adicional',
                'eletiva' => 'Eletiva',
                'itinerario_formativo' => 'Itinerário Formativo',
                'extracurricular' => 'Extracurricular',
                'complementar' => 'Complementar',
                'outra' => 'Outra',
            ],
            'etapa_infantil' => ['nao_aplica' => 'Não se aplica', 'obrigatoria' => 'Obrigatório', 'oferta' => 'Eletivo'],
            'etapa_fund_i' => ['nao_aplica' => 'Não se aplica', 'obrigatoria' => 'Obrigatório', 'oferta' => 'Eletivo'],
            'etapa_fund_ii' => ['nao_aplica' => 'Não se aplica', 'obrigatoria' => 'Obrigatório', 'oferta' => 'Eletivo'],
            'etapa_medio' => ['nao_aplica' => 'Não se aplica', 'obrigatoria' => 'Obrigatório', 'oferta' => 'Eletivo'],
        ];

        return [[
            'likes' => ['%/admin/componentes-curriculares', '%/admin/componentes-curriculares/%'],
            'excluir' => ['%/dados%'],
            'regras' => [
                $this->regra('#/admin/componentes-curriculares$#', 'Criado', 'componente', 'Componente curricular', $rotulos, $booleanos, [], $mapas),
                $this->regra('#/admin/componentes-curriculares/(\d+)$#', 'metodo', 'componente', 'Componente curricular', $rotulos, $booleanos, [], $mapas, 1),
            ],
        ]];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function matrizes(): array
    {
        $rotulos = [
            'nome' => 'Nome',
            'codigo' => 'Código',
            'curso_id' => 'Curso',
            'serie_id' => 'Série',
            'modalidade' => 'Modalidade',
            'turno' => 'Turno',
            'duracao_padrao_aula_minutos' => 'Duração da aula (min)',
            'dias_letivos_previstos' => 'Dias letivos previstos',
            'carga_horaria_anual_prevista' => 'Carga horária anual',
            'base_legal' => 'Base legal',
            'ativo' => 'Status',
            'observacoes' => 'Observações',
            'componentes' => 'Componentes',
        ];

        return [[
            'likes' => ['%/admin/matrizes-curriculares', '%/admin/matrizes-curriculares/%'],
            'excluir' => ['%/dados%'],
            'regras' => [
                $this->regra('#/admin/matrizes-curriculares$#', 'Criado', 'matriz', 'Matriz curricular', $rotulos, ['ativo']),
                $this->regra('#/admin/matrizes-curriculares/(\d+)/update$#', 'Alterado', 'matriz', 'Matriz curricular', $rotulos, ['ativo'], [], [], 1),
                $this->regra('#/admin/matrizes-curriculares/(\d+)/delete$#', 'Excluído', 'matriz', 'Matriz curricular', ['nome' => 'Nome'], [], [], [], 1),
            ],
        ]];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function calendario(): array
    {
        $efeito = [
            'nao_letivo' => 'Não letivo',
            'reposicao' => 'Reposição',
            'neutro' => 'Neutro',
        ];
        $tipos = [
            'feriado' => 'Feriado',
            'recesso' => 'Recesso',
            'reposicao' => 'Reposição',
            'evento' => 'Evento',
            'suspensao' => 'Suspensão',
            'avaliacao' => 'Avaliação',
            'reuniao' => 'Reunião',
            'passeio' => 'Passeio',
            'apresentacao' => 'Apresentação',
        ];
        $ano = [
            'nome' => 'Nome',
            'ano' => 'Ano',
            'dias_meta' => 'Dias letivos',
            'carga_horaria_meta' => 'Carga horária',
            'observacao' => 'Observação',
            'curso_ids' => 'Cursos',
            'serie_ids' => 'Séries',
        ];
        $evento = [
            'descricao' => 'Descrição',
            'tipo' => 'Tipo',
            'data_inicio' => 'Início',
            'data_fim' => 'Término',
            'local_evento' => 'Local',
            'link_reuniao' => 'Link',
            'visivel_aluno' => 'Visível ao aluno',
            'visivel_professor' => 'Visível ao professor',
            'visivel_pais' => 'Visível aos pais',
            'publicar_calendario_escolar' => 'Publicar no calendário escolar',
            'aviso_prioridade' => 'Prioridade do aviso',
            'aviso_publico' => 'Público do aviso',
            'aviso_dia_inteiro' => 'Dia inteiro',
            'aviso_hora_inicio' => 'Hora de início',
            'aviso_hora_fim' => 'Hora de término',
        ];
        $eventoBool = ['visivel_aluno', 'visivel_professor', 'visivel_pais', 'publicar_calendario_escolar', 'aviso_dia_inteiro'];
        $eventoDatas = ['data_inicio', 'data_fim'];
        $eventoMapa = ['tipo' => $tipos];

        return [[
            'likes' => ['%/admin/calendario-letivo/%'],
            'excluir' => [],
            'regras' => [
                $this->regra('#/admin/calendario-letivo/salvar-ano$#', 'pelo_id', 'calendario', 'Calendário', $ano, [], [], [], null, ['curso_ids', 'serie_ids'], 'calendario_id'),
                $this->regra('#/admin/calendario-letivo/excluir$#', 'Excluído', 'calendario', 'Calendário', ['ano' => 'Ano'], [], [], [], null, [], 'calendario_id'),
                $this->regra('#/admin/calendario-letivo/salvar-evento$#', 'pelo_id', 'evento', 'Evento', $evento, $eventoBool, $eventoDatas, $eventoMapa, null, [], 'id'),
                $this->regra('#/admin/calendario-letivo/excluir-evento$#', 'Excluído', 'evento', 'Evento', ['ano' => 'Ano'], [], [], [], null, [], 'id'),
                $this->regraTipo(),
                $this->regra('#/admin/calendario-letivo/excluir-tipo$#', 'Excluído', 'tipo', 'Tipo de evento', ['slug' => 'Tipo'], [], [], [], null, [], 'slug'),
                $this->regra('#/admin/calendario-letivo/efeito-tipo$#', 'Alterado', 'tipo', 'Tipo de evento', [
                    'slug' => 'Tipo',
                    'efeito' => 'Efeito',
                ], [], [], ['efeito' => $efeito], null, [], 'slug'),
            ],
        ]];
    }

    /**
     * Mesma regra de SchoolCalendarService::slugificar(), para a criação
     * do tipo usar a mesma chave que a alteração e a exclusão (o slug).
     */
    private function slugificar(string $nome): string
    {
        $s = mb_strtolower(trim($nome), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
        $s = trim($s, '_');
        if ($s === '') {
            $s = 'tipo';
        }

        return mb_substr($s, 0, 50);
    }

    /**
     * @return array<string,mixed>
     */
    private function regraTipo(): array
    {
        $regra = $this->regra('#/admin/calendario-letivo/salvar-tipo$#', 'Criado', 'tipo', 'Tipo de evento', [
            'nome' => 'Nome',
            'cor' => 'Cor',
            'efeito' => 'Efeito',
        ], [], [], [
            'efeito' => [
                'nao_letivo' => 'Não letivo',
                'reposicao' => 'Reposição',
                'neutro' => 'Neutro',
            ],
        ]);
        $regra['id_slug_de'] = 'nome';

        return $regra;
    }

    /**
     * @return array<string,mixed>
     */
    private function regraLote(
        string $regex,
        string $chave,
        string $recurso,
        string $chaveRegistro,
        string $campoIds,
        string $rotuloIds
    ): array {
        $regra = $this->regra($regex, 'Excluído', $chave, $recurso, [
            $campoIds => $rotuloIds,
        ], [], [], [], null, [$campoIds]);
        $regra['limpar_chave'] = $chaveRegistro;
        $regra['limpar_campo'] = $campoIds;

        return $regra;
    }

    /**
     * @param array<string,string> $rotulos
     * @param list<string> $booleanos
     * @param list<string> $datas
     * @param array<string,array<string,string>> $mapas
     * @param list<string> $listas
     * @return array<string,mixed>
     */
    private function regra(
        string $regex,
        string $acao,
        string $chave,
        string $recurso,
        array $rotulos,
        array $booleanos = [],
        array $datas = [],
        array $mapas = [],
        ?int $idGrupo = null,
        array $listas = [],
        string $campoId = ''
    ): array {
        $regra = [
            'regex' => $regex,
            'acao' => $acao,
            'chave' => $chave,
            'recurso' => $recurso,
            'rotulos' => $rotulos,
            'booleanos' => $booleanos,
            'datas' => $datas,
            'mapas' => $mapas,
            'listas' => $listas,
        ];
        if ($idGrupo !== null) {
            $regra['id_grupo'] = $idGrupo;
        }
        if ($acao === 'pelo_id' && $campoId !== '') {
            $regra['campo_id'] = $campoId;
        }
        if ($campoId !== '' && $acao !== 'pelo_id') {
            $regra['id_payload'] = $campoId;
        }

        return $regra;
    }
}
