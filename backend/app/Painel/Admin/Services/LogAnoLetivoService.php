<?php

namespace App\Painel\Admin\Services;

use App\Core\Database;

/**
 * Lê a trilha de cadastro, edição e exclusão de ano letivo em logs_auditoria.
 */
class LogAnoLetivoService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $limite = 100): array
    {
        if ($limite < 1 || !$this->db->tableExists('logs_auditoria')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            "SELECT a.created_at, a.user_id, a.user_role,
                    a.resource_accessed, a.request_payload, u.nome AS usuario_nome
             FROM logs_auditoria a
             LEFT JOIN usuarios u ON u.id = a.user_id AND a.user_role IN ('admin', 'admin_escola')
             WHERE a.resource_accessed LIKE :prefixo
               AND (
                    a.resource_accessed LIKE :cadastro
                    OR a.resource_accessed LIKE :edicao
                    OR a.resource_accessed LIKE :exclusao
               )
             ORDER BY a.created_at DESC
             LIMIT " . (int) min($limite, 100),
            [
                'prefixo' => '%/admin/ano-letivo%',
                'cadastro' => '%/admin/ano-letivo',
                'edicao' => '%/update',
                'exclusao' => '%/delete',
            ]
        );

        $itens = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = $this->montar($row);
            if ($item === null) {
                continue;
            }
            $itens[] = $item;
        }

        return $this->comDiferencas($itens);
    }

    /**
     * Compara cada envio com o anterior do mesmo ano, do mais antigo para o mais novo.
     *
     * @param list<array<string,mixed>> $itens
     * @return list<array<string,mixed>>
     */
    private function comDiferencas(array $itens): array
    {
        $cronologico = array_reverse($itens);
        $porId = [];
        $porAnoSemId = [];
        foreach ($cronologico as $i => $item) {
            $id = $item['ano_letivo_id'] ?? null;
            $ano = (string) ($item['ano'] ?? '');
            $acao = (string) ($item['acao'] ?? '');
            $snapshot = is_array($item['snapshot'] ?? null) ? $item['snapshot'] : [];
            $anterior = null;

            if ($acao === 'Criado') {
                if ($ano !== '' && $snapshot !== []) {
                    $porAnoSemId[$ano] = $snapshot;
                }
            } elseif ($acao === 'Excluído') {
                if ($id !== null && isset($porId[$id])) {
                    $anterior = $porId[$id];
                    $anoDoId = (string) ($anterior['Ano letivo'] ?? '');
                    unset($porId[$id]);
                    if ($anoDoId !== '') {
                        unset($porAnoSemId[$anoDoId]);
                    }
                }
                if ($ano !== '') {
                    unset($porAnoSemId[$ano]);
                }
            } elseif ($id !== null && isset($porId[$id])) {
                $anterior = $porId[$id];
            } elseif ($ano !== '' && isset($porAnoSemId[$ano])) {
                $anterior = $porAnoSemId[$ano];
                unset($porAnoSemId[$ano]);
            }

            if ($acao === 'Alterado' && $id !== null && $snapshot !== []) {
                $anoAnterior = (string) ($anterior['Ano letivo'] ?? '');
                if ($anoAnterior !== '' && $anoAnterior !== $ano) {
                    unset($porAnoSemId[$anoAnterior]);
                }
                $porId[$id] = $snapshot;
            }

            $cronologico[$i]['alteracoes'] = $this->diferencas($snapshot, $anterior, $acao);
            unset($cronologico[$i]['snapshot'], $cronologico[$i]['ano_letivo_id'], $cronologico[$i]['ano']);
        }

        return array_reverse($cronologico);
    }

    /**
     * @param array<string,string> $atual
     * @param array<string,string>|null $anterior
     * @return list<array{campo:string,antes:string,depois:string}>
     */
    private function diferencas(array $atual, ?array $anterior, string $acao): array
    {
        if ($acao === 'Excluído') {
            $ano = (string) ($atual['Ano letivo'] ?? ($anterior['Ano letivo'] ?? ''));
            return [[
                'campo' => 'Ano letivo',
                'antes' => $ano !== '' ? $ano : '—',
                'depois' => 'Excluído',
            ]];
        }
        if ($anterior === null) {
            $linhas = [];
            foreach ($atual as $campo => $valor) {
                $linhas[] = ['campo' => (string) $campo, 'antes' => '', 'depois' => (string) $valor];
            }
            return $linhas;
        }
        $linhas = [];
        $campos = array_unique(array_merge(array_keys($anterior), array_keys($atual)));
        foreach ($campos as $campo) {
            $antes = (string) ($anterior[$campo] ?? '');
            $depois = (string) ($atual[$campo] ?? '');
            if ($antes === $depois) {
                continue;
            }
            $linhas[] = [
                'campo' => (string) $campo,
                'antes' => $antes !== '' ? $antes : '—',
                'depois' => $depois !== '' ? $depois : '—',
            ];
        }
        return $linhas;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function montar(array $row): ?array
    {
        $caminho = parse_url((string) ($row['resource_accessed'] ?? ''), PHP_URL_PATH);
        $caminho = rtrim(is_string($caminho) && $caminho !== '' ? $caminho : (string) ($row['resource_accessed'] ?? ''), '/');
        $anoLetivoId = null;
        if (preg_match('#/admin/ano-letivo/(\d+)/update$#', $caminho, $m)) {
            $acao = 'Alterado';
            $anoLetivoId = (int) $m[1];
        } elseif (preg_match('#/admin/ano-letivo/(\d+)/delete$#', $caminho, $m)) {
            $acao = 'Excluído';
            $anoLetivoId = (int) $m[1];
        } elseif (preg_match('#/admin/ano-letivo$#', $caminho)) {
            $acao = 'Criado';
        } else {
            return null;
        }

        $payload = $this->payload($row['request_payload'] ?? null);
        $nome = trim((string) ($row['usuario_nome'] ?? ''));
        if ($nome === '') {
            $idUsuario = (int) ($row['user_id'] ?? 0);
            $nome = $idUsuario > 0 ? ('Usuário #' . $idUsuario) : 'Não identificado';
        }
        $quando = (string) ($row['created_at'] ?? '');
        $ts = strtotime($quando);

        return [
            'quando' => $ts !== false ? date('d/m/Y H:i', $ts) : $quando,
            'acao' => $acao,
            'usuario' => $nome,
            'ano' => trim((string) ($payload['ano'] ?? '')),
            'ano_letivo_id' => $anoLetivoId,
            'snapshot' => $this->snapshot($payload, $acao),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private function snapshot(array $payload, string $acao): array
    {
        $snapshot = [];
        $ano = trim((string) ($payload['ano'] ?? ''));
        if ($ano !== '') {
            $snapshot['Ano letivo'] = $ano;
        }
        if ($acao === 'Excluído') {
            return $snapshot;
        }

        $inicio = $this->formatarData((string) ($payload['data_inicio'] ?? ''));
        $fim = $this->formatarData((string) ($payload['data_fim'] ?? ''));
        if ($inicio !== '') {
            $snapshot['Início'] = $inicio;
        }
        if ($fim !== '') {
            $snapshot['Término'] = $fim;
        }
        $tipo = strtolower(trim((string) ($payload['periodo_tipo'] ?? '')));
        $organizacao = $this->rotuloOrganizacao($tipo);
        if ($organizacao !== '') {
            $snapshot['Organização'] = $organizacao;
        }
        if (array_key_exists('ativo', $payload) || $acao !== 'Excluído') {
            $snapshot['Status'] = !empty($payload['ativo']) ? 'Ativo' : 'Inativo';
        }
        foreach ($this->linhasPeriodos($payload['periodos'] ?? [], $tipo) as $campo => $valor) {
            $snapshot[$campo] = $valor;
        }
        return $snapshot;
    }

    /**
     * @return array<string,string>
     */
    private function linhasPeriodos(mixed $periodos, string $tipo): array
    {
        if (!is_array($periodos)) {
            return [];
        }
        $linhas = [];
        foreach ($periodos as $numero => $item) {
            if (!is_array($item)) {
                continue;
            }
            $n = (int) $numero;
            if ($n < 1) {
                continue;
            }
            $inicio = $this->formatarData((string) ($item['data_inicio'] ?? ''));
            $fim = $this->formatarData((string) ($item['data_fim'] ?? ''));
            $dias = trim((string) ($item['dias_letivos'] ?? ''));
            $texto = '';
            if ($inicio !== '' || $fim !== '') {
                $texto = ($inicio !== '' ? $inicio : '—') . ' a ' . ($fim !== '' ? $fim : '—');
            }
            if ($dias !== '') {
                $texto .= ($texto !== '' ? ' · ' : '') . $dias . ' dias letivos';
            }
            if ($texto === '') {
                continue;
            }
            $linhas[$this->rotuloPeriodo($tipo, $n)] = $texto;
        }
        return $linhas;
    }

    private function rotuloOrganizacao(string $tipo): string
    {
        return match ($tipo) {
            'bimestre', 'bimestral' => 'Bimestral',
            'trimestre', 'trimestral' => 'Trimestral',
            'semestre', 'semestral' => 'Semestral',
            'etapa_unica', 'etapa', 'anual' => 'Anual',
            default => '',
        };
    }

    private function rotuloPeriodo(string $tipo, int $numero): string
    {
        $tipo = match ($tipo) {
            'trimestral' => 'trimestre',
            'semestral' => 'semestre',
            'anual', 'etapa' => 'etapa_unica',
            'bimestral' => 'bimestre',
            default => $tipo,
        };
        if ($tipo === 'etapa_unica') {
            return 'Anual';
        }
        $nome = match ($tipo) {
            'trimestre' => 'trimestre',
            'semestre' => 'semestre',
            default => 'bimestre',
        };
        return $numero . 'º ' . $nome;
    }

    private function formatarData(string $valor): string
    {
        $valor = substr(trim($valor), 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) {
            return '';
        }
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(mixed $bruto): array
    {
        if (is_string($bruto) && $bruto !== '') {
            $decoded = json_decode($bruto, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($bruto) ? $bruto : [];
    }
}
