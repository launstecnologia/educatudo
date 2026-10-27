<?php

namespace App\Modulos\Lex\Services;

/**
 * Lê o Sync Full e separa curso, série, turma e matrícula da escola configurada.
 * Segmento vira curso, grade vira série, período vira o turno da turma.
 * Disciplinas não são gravadas: subjects vazio é um snapshot válido.
 */
class LexClassificadorAcademico
{
    /**
     * @param array<string,mixed> $resposta
     * @return array<string,mixed>
     */
    public static function classificar(
        array $resposta,
        string $tenantId,
        string $escolaLexId,
        string $externalId
    ): array {
        $vazio = self::vazio(false);
        $header = is_array($resposta['header'] ?? null) ? $resposta['header'] : [];
        $payload = is_array($resposta['payload'] ?? null) ? $resposta['payload'] : null;
        $syncId = trim((string) ($header['syncId'] ?? ''));
        $syncDate = trim((string) ($header['syncDate'] ?? '')) ?: null;
        if ($syncId === '' || $payload === null) {
            $vazio['erro'] = 'O snapshot da LEX não tem o formato esperado.';
            return $vazio;
        }

        $tenantPayload = trim((string) ($payload['tenantId'] ?? ''));
        if ($tenantId === '' || strcasecmp($tenantPayload, $tenantId) !== 0) {
            $vazio['erro'] = 'O tenant do snapshot não é o tenant configurado.';
            $vazio['sync_id'] = $syncId;
            $vazio['sync_date'] = $syncDate;
            return $vazio;
        }

        $escolas = is_array($payload['schools'] ?? null) ? $payload['schools'] : [];
        $escola = self::escolaUnica($escolas, $escolaLexId, $externalId);
        if (isset($escola['erro'])) {
            $vazio['erro'] = $escola['erro'];
            $vazio['sync_id'] = $syncId;
            $vazio['sync_date'] = $syncDate;
            return $vazio;
        }

        $perfis = self::mapaPerfis(is_array($payload['profiles'] ?? null) ? $payload['profiles'] : []);
        $mapa = self::achatar(is_array($escola['escola']['structures'] ?? null) ? $escola['escola']['structures'] : [], null);
        $cursos = [];
        $series = [];
        $conflitos = [];
        $seriesRecusadas = [];

        foreach ($mapa as $id => $no) {
            if ($no['tipo'] !== 'segment') {
                continue;
            }
            if ($no['nome'] === '') {
                $conflitos[] = self::conflito($id, 'curso', 'Segmento sem nome.', true);
                continue;
            }
            $cursos[$id] = ['id' => $id, 'nome' => self::limitar($no['nome'], 100)];
        }

        foreach ($mapa as $id => $no) {
            if ($no['tipo'] !== 'grade') {
                continue;
            }
            if ($no['nome'] === '') {
                $conflitos[] = self::conflito($id, 'serie', 'Série sem nome.', true);
                $seriesRecusadas[$id] = true;
                continue;
            }
            $cursoLex = self::ancestral($mapa, $id, 'segment');
            if ($cursoLex === null || !isset($cursos[$cursoLex])) {
                $conflitos[] = self::conflito($id, 'serie', 'Série sem segmento.', true);
                $seriesRecusadas[$id] = true;
                continue;
            }
            $series[$id] = [
                'id' => $id,
                'nome' => self::limitar($no['nome'], 50),
                'curso_lex' => $cursoLex,
            ];
        }

        $turmas = [];
        $ignorados = [];
        $grupos = is_array($escola['escola']['groups'] ?? null) ? $escola['escola']['groups'] : [];
        foreach ($grupos as $grupo) {
            if (!is_array($grupo)) {
                continue;
            }
            $id = trim((string) ($grupo['id'] ?? ''));
            $tipo = strtolower(trim((string) ($grupo['type'] ?? '')));
            if ($id === '') {
                $conflitos[] = self::conflito('', 'turma', 'Turma sem identificador.', true);
                continue;
            }
            if ($tipo !== 'class') {
                $ignorados[] = ['id_lex' => $id, 'mensagem' => 'Grupo não importado nesta etapa.'];
                continue;
            }

            $nome = trim((string) ($grupo['name'] ?? ''));
            $ano = self::ano($grupo['schoolYear'] ?? $grupo['school_year'] ?? null);
            if ($nome === '') {
                $conflitos[] = self::conflito($id, 'turma', 'Turma sem nome.', true);
                continue;
            }
            if ($ano === null) {
                $conflitos[] = self::conflito($id, 'turma', 'Turma sem ano letivo.', true);
                continue;
            }

            $ligacoes = self::idsEstruturas($grupo['structures'] ?? $grupo['structureIds'] ?? []);
            $grades = [];
            $segmentos = [];
            $turno = null;
            foreach ($ligacoes as $estruturaId) {
                $no = $mapa[$estruturaId] ?? null;
                if ($no === null) {
                    continue;
                }
                if ($no['tipo'] === 'grade') {
                    $grades[] = $estruturaId;
                } elseif ($no['tipo'] === 'segment') {
                    $segmentos[] = $estruturaId;
                } elseif ($no['tipo'] === 'period' && $turno === null && $no['nome'] !== '') {
                    $turno = self::limitar($no['nome'], 20);
                }
            }
            $grades = array_values(array_unique($grades));
            if (count($grades) > 1) {
                $conflitos[] = self::conflito($id, 'turma', 'Turma ligada a mais de uma série.', true);
                continue;
            }

            $serieLex = $grades[0] ?? null;
            $cursoLex = null;
            $serieNome = 'A definir';
            if ($serieLex !== null) {
                if (isset($seriesRecusadas[$serieLex]) || !isset($series[$serieLex])) {
                    $conflitos[] = self::conflito($id, 'turma', 'Turma ligada a uma série que não foi importada.', true);
                    continue;
                }
                $cursoLex = $series[$serieLex]['curso_lex'];
                $serieNome = $series[$serieLex]['nome'];
            } elseif (count($segmentos) === 1 && isset($cursos[$segmentos[0]])) {
                $cursoLex = $segmentos[0];
                $serieNome = $cursos[$cursoLex]['nome'];
            }

            $alunos = [];
            $professores = [];
            $usuarios = is_array($grupo['users'] ?? null) ? $grupo['users'] : [];
            foreach ($usuarios as $vinculo) {
                if (!is_array($vinculo)) {
                    continue;
                }
                $userId = trim((string) ($vinculo['userId'] ?? $vinculo['user_id'] ?? ''));
                $profileId = trim((string) ($vinculo['profileId'] ?? $vinculo['profile_id'] ?? ''));
                $papel = $perfis[$profileId] ?? '';
                if ($userId === '' || $papel === '') {
                    continue;
                }
                if ($papel === 'student') {
                    $alunos[$userId] = true;
                } elseif ($papel === 'teacher') {
                    $professores[$userId] = true;
                }
            }

            $turmas[$id] = [
                'id' => $id,
                'nome' => self::limitar($nome, 100),
                'ano' => $ano,
                'serie_lex' => $serieLex,
                'curso_lex' => $cursoLex,
                'serie_nome' => $serieNome,
                'turno' => $turno,
                'alunos' => array_keys($alunos),
                'professores' => array_keys($professores),
            ];
        }

        return [
            'ok' => true,
            'erro' => null,
            'sync_id' => $syncId,
            'sync_date' => $syncDate,
            'cursos' => $cursos,
            'series' => $series,
            'turmas' => $turmas,
            'conflitos' => $conflitos,
            'ignorados' => $ignorados,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function vazio(bool $ok): array
    {
        return [
            'ok' => $ok,
            'erro' => null,
            'sync_id' => null,
            'sync_date' => null,
            'cursos' => [],
            'series' => [],
            'turmas' => [],
            'conflitos' => [],
            'ignorados' => [],
        ];
    }

    /**
     * @param list<mixed> $escolas
     * @return array{escola: array<string,mixed>}|array{erro: string}
     */
    private static function escolaUnica(array $escolas, string $escolaLexId, string $externalId): array
    {
        $escolaLexId = trim($escolaLexId);
        $externalId = trim($externalId);
        if ($escolaLexId === '' && $externalId === '') {
            return ['erro' => 'Configure o id da escola na LEX ou o externalId.'];
        }

        $achadas = [];
        foreach ($escolas as $escola) {
            if (!is_array($escola)) {
                continue;
            }
            $id = trim((string) ($escola['id'] ?? ''));
            $externo = trim((string) ($escola['externalId'] ?? $escola['external_id'] ?? ''));
            $bateId = $escolaLexId !== '' && strcasecmp($id, $escolaLexId) === 0;
            $bateExterno = $externalId !== '' && strcasecmp($externo, $externalId) === 0;
            if ($bateId || $bateExterno) {
                $achadas[] = $escola;
            }
        }

        if (count($achadas) !== 1) {
            return ['erro' => count($achadas) === 0
                ? 'A escola configurada não veio no snapshot da LEX.'
                : 'Mais de uma escola da LEX corresponde à configuração.'];
        }

        return ['escola' => $achadas[0]];
    }

    /**
     * @param list<mixed> $nos
     * @return array<string, array{id:string,tipo:string,nome:string,pai:?string}>
     */
    private static function achatar(array $nos, ?string $pai): array
    {
        $saida = [];
        foreach ($nos as $no) {
            if (!is_array($no)) {
                continue;
            }
            $id = trim((string) ($no['id'] ?? ''));
            $atual = $pai;
            if ($id !== '') {
                $saida[$id] = [
                    'id' => $id,
                    'tipo' => strtolower(trim((string) ($no['type'] ?? ''))),
                    'nome' => trim((string) ($no['name'] ?? '')),
                    'pai' => $pai,
                ];
                $atual = $id;
            }
            $filhos = is_array($no['structures'] ?? null) ? $no['structures'] : [];
            foreach (self::achatar($filhos, $atual) as $filhoId => $filho) {
                $saida[$filhoId] = $filho;
            }
        }
        return $saida;
    }

    /**
     * @param array<string, array{id:string,tipo:string,nome:string,pai:?string}> $mapa
     */
    private static function ancestral(array $mapa, string $id, string $tipo): ?string
    {
        $visto = [];
        $atual = $mapa[$id]['pai'] ?? null;
        while (is_string($atual) && $atual !== '' && !isset($visto[$atual])) {
            $visto[$atual] = true;
            if (($mapa[$atual]['tipo'] ?? '') === $tipo) {
                return $atual;
            }
            $atual = $mapa[$atual]['pai'] ?? null;
        }
        return null;
    }

    /**
     * @param mixed $bruto
     * @return list<string>
     */
    private static function idsEstruturas($bruto): array
    {
        if (!is_array($bruto)) {
            return [];
        }
        $ids = [];
        foreach ($bruto as $item) {
            if (is_string($item) || is_int($item)) {
                $id = trim((string) $item);
            } elseif (is_array($item)) {
                $id = trim((string) ($item['id'] ?? ''));
            } else {
                continue;
            }
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param list<mixed> $perfis
     * @return array<string, string>
     */
    private static function mapaPerfis(array $perfis): array
    {
        $mapa = [];
        foreach ($perfis as $perfil) {
            if (!is_array($perfil)) {
                continue;
            }
            $id = trim((string) ($perfil['id'] ?? ''));
            $tipo = strtolower(trim((string) ($perfil['type'] ?? '')));
            if ($id !== '' && $tipo !== '') {
                $mapa[$id] = $tipo;
            }
        }
        return $mapa;
    }

    /**
     * @param mixed $valor
     */
    private static function ano($valor): ?int
    {
        if (is_int($valor) || (is_string($valor) && preg_match('/^\d{4}$/', trim($valor)) === 1)) {
            $ano = (int) $valor;
            if ($ano >= 1990 && $ano <= 2100) {
                return $ano;
            }
        }
        return null;
    }

    private static function limitar(string $texto, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $max);
        }
        return substr($texto, 0, $max);
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
}
