<?php

namespace App\Modulos\Provas\Services;

use App\Core\Database;
use App\Core\LayoutHelper;
use App\Modulos\Provas\Models\Exam;

/**
 * Monta o layout da prova impressa: cabeçalho, questões já lançadas e paginação.
 */
class LayoutProvaImpressaService
{
    private Database $db;
    private Exam $provaModel;
    private ?bool $colunaPronta = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->provaModel = new Exam();
    }

    public function colunaPronta(): bool
    {
        if ($this->colunaPronta !== null) {
            return $this->colunaPronta;
        }
        try {
            $coluna = $this->db->fetch(
                "SELECT COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'provas_blocos'
                   AND COLUMN_NAME = :coluna",
                ['coluna' => 'layout_impressao']
            );
            $this->colunaPronta = is_array($coluna);
        } catch (\Throwable $e) {
            $this->colunaPronta = false;
        }
        return $this->colunaPronta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function carregar(int $blocoId): ?array
    {
        if ($blocoId <= 0) {
            return null;
        }
        $colunaLayout = $this->colunaPronta() ? ', layout_impressao' : '';
        $bloco = $this->db->fetch(
            'SELECT id, titulo, data_prova, hora_inicio, hora_fim, formato_evento, configuracao_nota' . $colunaLayout . '
             FROM provas_blocos
             WHERE id = :id AND deleted_at IS NULL',
            ['id' => $blocoId]
        );
        if (!is_array($bloco) || (string) ($bloco['formato_evento'] ?? '') !== 'prova_impressa') {
            return null;
        }

        $layout = $this->layoutDoBloco($bloco);
        $materias = $this->materiasDoBloco($blocoId, $layout);

        return [
            'bloco' => $bloco,
            'layout' => $layout,
            'materias' => $materias,
            'logo_url' => $this->urlPublica((string) LayoutHelper::getDocumentLogoUrl()),
            'nome_escola' => (string) LayoutHelper::getSystemTitle(),
            'data_texto' => $this->dataTexto((string) ($bloco['data_prova'] ?? '')),
            'horario_texto' => $this->horarioTexto((string) ($bloco['hora_inicio'] ?? ''), (string) ($bloco['hora_fim'] ?? '')),
            'coluna_pronta' => $this->colunaPronta(),
            'escola_constroi' => (string) ($bloco['configuracao_nota'] ?? '') === 'coordenacao_calcula',
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public function salvar(int $blocoId, array $post): void
    {
        $pacote = $this->carregar($blocoId);
        if ($pacote === null) {
            throw new \RuntimeException('Prova impressa não encontrada.');
        }
        if (!$this->colunaPronta()) {
            throw new \RuntimeException('Falta a migration do layout da prova impressa. Rode 2026_11_03_layout_prova_impressa no Master.');
        }

        $layout = $this->layoutDoPost($post, $pacote);
        $json = json_encode($layout, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Não foi possível guardar o layout.');
        }
        $this->db->query(
            'UPDATE provas_blocos SET layout_impressao = :layout WHERE id = :id',
            ['layout' => $json, 'id' => $blocoId]
        );
    }

    /**
     * @param array<string, mixed> $bloco
     * @return array<string, mixed>
     */
    private function layoutDoBloco(array $bloco): array
    {
        $titulo = trim((string) ($bloco['titulo'] ?? 'Prova impressa'));
        $base = $this->padrao($titulo);
        $bruto = $bloco['layout_impressao'] ?? null;
        if (!is_string($bruto) || trim($bruto) === '') {
            return $base;
        }
        $salvo = json_decode($bruto, true);
        if (!is_array($salvo)) {
            return $base;
        }
        return $this->normalizar($salvo, $titulo);
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $pacote
     * @return array<string, mixed>
     */
    private function layoutDoPost(array $post, array $pacote): array
    {
        $tituloBloco = trim((string) ($pacote['bloco']['titulo'] ?? 'Prova impressa'));
        $layout = $this->normalizar($post, $tituloBloco);
        $conhecidas = [];
        foreach ($pacote['materias'] as $materia) {
            $chaveMateria = (int) ($materia['prova_id'] ?? 0);
            if ($chaveMateria <= 0) {
                continue;
            }
            foreach ($materia['questoes'] as $questao) {
                $qid = (int) ($questao['id'] ?? 0);
                if ($qid > 0) {
                    $conhecidas[$chaveMateria][$qid] = true;
                }
            }
        }
        $postMaterias = is_array($post['materias'] ?? null) ? $post['materias'] : [];
        $saida = [];
        foreach ($conhecidas as $mid => $ids) {
            $enviada = is_array($postMaterias[$mid] ?? null) ? $postMaterias[$mid] : [];
            $ordem = $this->idsNaLista($enviada['ordem'] ?? [], $ids);
            foreach (array_keys($ids) as $qid) {
                if (!in_array($qid, $ordem, true)) {
                    $ordem[] = $qid;
                }
            }
            $incluir = $this->idsNaLista($enviada['incluir'] ?? [], $ids);
            $quebras = $this->idsNaLista($enviada['quebra'] ?? [], $ids);
            $imagens = [];
            $imgsPost = is_array($enviada['imagem'] ?? null) ? $enviada['imagem'] : [];
            foreach ($imgsPost as $qid => $px) {
                $qid = (int) $qid;
                if (!isset($ids[$qid])) {
                    continue;
                }
                $imagens[$qid] = $this->faixa((int) $px, 60, 480, (int) $layout['altura_imagem']);
            }
            $saida[(string) $mid] = [
                'ordem' => $ordem,
                'incluir' => $incluir,
                'quebras' => $quebras,
                'imagens' => $imagens,
            ];
        }
        $layout['materias'] = $saida;
        return $layout;
    }

    /**
     * @param array<string, mixed> $origem
     * @return array<string, mixed>
     */
    private function normalizar(array $origem, string $tituloBloco): array
    {
        $base = $this->padrao($tituloBloco);
        $bool = static function (array $origem, string $chave, bool $padrao): bool {
            if (!array_key_exists($chave, $origem)) {
                return $padrao;
            }
            $valor = $origem[$chave];
            return $valor === true || $valor === 1 || $valor === '1' || $valor === 'on';
        };
        $texto = static function (array $origem, string $chave, int $max): string {
            $valor = trim(strip_tags((string) ($origem[$chave] ?? '')));
            if (function_exists('mb_substr')) {
                return mb_substr($valor, 0, $max);
            }
            return substr($valor, 0, $max);
        };

        $base['mostrar_logo'] = $bool($origem, 'mostrar_logo', true);
        $base['mostrar_nome_escola'] = $bool($origem, 'mostrar_nome_escola', true);
        $base['mostrar_data'] = $bool($origem, 'mostrar_data', true);
        $base['mostrar_horario'] = $bool($origem, 'mostrar_horario', true);
        $base['campo_nome'] = $bool($origem, 'campo_nome', true);
        $base['campo_turma'] = $bool($origem, 'campo_turma', true);
        $base['campo_numero'] = $bool($origem, 'campo_numero', true);
        $base['mostrar_pontos'] = $bool($origem, 'mostrar_pontos', false);
        $base['mostrar_professor'] = $bool($origem, 'mostrar_professor', true);
        $base['quebrar_disciplina'] = $bool($origem, 'quebrar_disciplina', true);
        $base['numero_pagina'] = $bool($origem, 'numero_pagina', true);
        $base['altura_logo'] = $this->faixa((int) ($origem['altura_logo'] ?? 56), 28, 140, 56);
        $posicaoLogo = (string) ($origem['posicao_logo'] ?? 'centro');
        $base['posicao_logo'] = in_array($posicaoLogo, ['esquerda', 'centro', 'direita'], true) ? $posicaoLogo : 'centro';
        $base['altura_imagem'] = $this->faixa((int) ($origem['altura_imagem'] ?? 180), 60, 480, 180);
        $base['tamanho_fonte'] = $this->faixa((int) ($origem['tamanho_fonte'] ?? 12), 10, 16, 12);
        $base['margem'] = $this->faixa((int) ($origem['margem'] ?? 14), 8, 24, 14);
        $base['espaco_questao'] = array_key_exists('espaco_questao', $origem)
            ? max(4, min(48, (int) $origem['espaco_questao']))
            : 12;
        $base['espaco_alternativa'] = array_key_exists('espaco_alternativa', $origem)
            ? max(0, min(24, (int) $origem['espaco_alternativa']))
            : 4;
        $titulo = $texto($origem, 'titulo', 180);
        $base['titulo'] = $titulo !== '' ? $titulo : $tituloBloco;
        $base['subtitulo'] = $texto($origem, 'subtitulo', 180);
        $base['instrucoes'] = $texto($origem, 'instrucoes', 800);
        $base['texto_extra'] = $texto($origem, 'texto_extra', 800);
        $lugarExtra = (string) ($origem['texto_extra_lugar'] ?? 'cabecalho');
        $base['texto_extra_lugar'] = in_array($lugarExtra, ['cabecalho', 'final'], true) ? $lugarExtra : 'cabecalho';
        $base['rodape'] = $texto($origem, 'rodape', 120);
        $materias = is_array($origem['materias'] ?? null) ? $origem['materias'] : [];
        $base['materias'] = $materias;
        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function padrao(string $titulo): array
    {
        return [
            'mostrar_logo' => true,
            'altura_logo' => 56,
            'posicao_logo' => 'centro',
            'mostrar_nome_escola' => true,
            'titulo' => $titulo !== '' ? $titulo : 'Prova impressa',
            'subtitulo' => '',
            'mostrar_data' => true,
            'mostrar_horario' => true,
            'instrucoes' => '',
            'campo_nome' => true,
            'campo_turma' => true,
            'campo_numero' => true,
            'mostrar_pontos' => false,
            'mostrar_professor' => true,
            'quebrar_disciplina' => true,
            'numero_pagina' => true,
            'margem' => 14,
            'tamanho_fonte' => 12,
            'espaco_questao' => 12,
            'espaco_alternativa' => 4,
            'altura_imagem' => 180,
            'texto_extra' => '',
            'texto_extra_lugar' => 'cabecalho',
            'rodape' => '',
            'materias' => [],
        ];
    }

    /**
     * @param array<string, mixed> $layout
     * @return list<array<string, mixed>>
     */
    private function materiasDoBloco(int $blocoId, array $layout): array
    {
        $provas = $this->db->fetchAll(
            "SELECT p.id, p.titulo, p.materia_id, pr.nome AS professor_nome, m.nome AS materia_nome
             FROM provas_blocos_vinculo v
             INNER JOIN provas p ON p.id = v.prova_id AND p.deleted_at IS NULL
             LEFT JOIN professores pr ON pr.id = p.professor_id
             LEFT JOIN materias m ON m.id = p.materia_id
             WHERE v.bloco_id = :bloco_id
               AND (p.status IS NULL OR p.status <> 'reprovada')
             ORDER BY v.ordem ASC, m.nome ASC, p.id ASC",
            ['bloco_id' => $blocoId]
        ) ?: [];

        $idsProva = [];
        foreach ($provas as $prova) {
            $idsProva[] = (int) ($prova['id'] ?? 0);
        }
        $alternativas = $this->alternativasDasProvas($idsProva);
        $salvas = is_array($layout['materias'] ?? null) ? $layout['materias'] : [];
        $alturaPadrao = (int) ($layout['altura_imagem'] ?? 180);
        $saida = [];

        foreach ($provas as $prova) {
            $provaId = (int) ($prova['id'] ?? 0);
            $mid = (int) ($prova['materia_id'] ?? 0);
            $questoes = $provaId > 0 ? $this->provaModel->getQuestoes($provaId) : [];
            $cfgBruto = $salvas[(string) $provaId] ?? ($salvas[$provaId] ?? null);
            $temCfg = is_array($cfgBruto);
            $cfg = $temCfg ? $cfgBruto : [];
            $incluir = $temCfg ? array_flip($this->listaInts($cfg['incluir'] ?? [])) : null;
            $vistas = $temCfg ? array_flip($this->listaInts($cfg['ordem'] ?? [])) : [];
            $quebras = array_flip($this->listaInts($cfg['quebras'] ?? []));
            $imagens = is_array($cfg['imagens'] ?? null) ? $cfg['imagens'] : [];
            $numero = 0;
            $linhas = [];
            foreach ($questoes as $questao) {
                $qid = (int) ($questao['id'] ?? 0);
                if ($qid <= 0) {
                    continue;
                }
                $alts = $alternativas[$qid] ?? [];
                $tipo = (string) ($questao['tipo'] ?? '');
                $objetiva = ($tipo === 'multipla_escolha' || $tipo === 'verdadeiro_falso') && $alts !== [];
                if ($objetiva) {
                    $numero++;
                }
                $imagem = trim((string) ($questao['imagem_url'] ?? ''));
                $enunciado = (string) ($questao['enunciado'] ?? '');
                $linhas[] = [
                    'id' => $qid,
                    'numero' => $objetiva ? $numero : null,
                    'tipo' => $tipo,
                    'objetiva' => $objetiva,
                    'valor' => (float) ($questao['valor'] ?? 0),
                    'enunciado_html' => LayoutHelper::renderEnunciadoProva($enunciado),
                    'resumo' => $this->resumo($enunciado),
                    'tem_imagem' => $imagem !== '' || stripos($enunciado, '<img') !== false,
                    'imagem_url' => $this->urlPublica($imagem),
                    'alternativas' => $this->alternativasHtml($alts),
                    'incluida' => $incluir === null || !isset($vistas[$qid]) || isset($incluir[$qid]),
                    'quebra' => isset($quebras[$qid]),
                    'altura_imagem' => $this->faixa((int) ($imagens[$qid] ?? $imagens[(string) $qid] ?? $alturaPadrao), 60, 480, $alturaPadrao),
                ];
            }
            $linhas = $this->ordenar($linhas, $this->listaInts($cfg['ordem'] ?? []));
            $saida[] = [
                'materia_id' => $mid,
                'materia_nome' => trim((string) ($prova['materia_nome'] ?? '')) !== ''
                    ? (string) $prova['materia_nome']
                    : (string) ($prova['titulo'] ?? 'Disciplina'),
                'professor_nome' => trim((string) ($prova['professor_nome'] ?? '')),
                'prova_id' => $provaId,
                'questoes' => $linhas,
            ];
        }
        return $saida;
    }

    /**
     * @param list<int> $provaIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function alternativasDasProvas(array $provaIds): array
    {
        $provaIds = array_values(array_filter(array_map('intval', $provaIds), static fn (int $id): bool => $id > 0));
        if ($provaIds === []) {
            return [];
        }
        $params = [];
        $marcas = [];
        foreach ($provaIds as $i => $id) {
            $chave = 'p' . $i;
            $marcas[] = ':' . $chave;
            $params[$chave] = $id;
        }
        $linhas = $this->db->fetchAll(
            'SELECT a.id, a.questao_id, a.texto, a.ordem
             FROM provas_alternativas a
             INNER JOIN provas_questoes q ON q.id = a.questao_id
             WHERE q.prova_id IN (' . implode(', ', $marcas) . ')
             ORDER BY a.questao_id ASC, a.ordem ASC, a.id ASC',
            $params
        ) ?: [];
        $porQuestao = [];
        foreach ($linhas as $linha) {
            $porQuestao[(int) ($linha['questao_id'] ?? 0)][] = $linha;
        }
        return $porQuestao;
    }

    /**
     * @param list<array<string, mixed>> $alts
     * @return list<array{letra: string, html: string}>
     */
    private function alternativasHtml(array $alts): array
    {
        $letras = ['A', 'B', 'C', 'D', 'E', 'F'];
        $saida = [];
        foreach (array_slice($alts, 0, 6) as $i => $alt) {
            $saida[] = [
                'letra' => $letras[$i] ?? '',
                'html' => LayoutHelper::renderEnunciadoProva((string) ($alt['texto'] ?? '')),
            ];
        }
        return $saida;
    }

    /**
     * @param list<array<string, mixed>> $linhas
     * @param list<int> $ordem
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $linhas, array $ordem): array
    {
        if ($ordem === []) {
            return $linhas;
        }
        $porId = [];
        foreach ($linhas as $linha) {
            $porId[(int) $linha['id']] = $linha;
        }
        $saida = [];
        foreach ($ordem as $id) {
            if (isset($porId[$id])) {
                $saida[] = $porId[$id];
                unset($porId[$id]);
            }
        }
        foreach ($porId as $linha) {
            $saida[] = $linha;
        }
        return $saida;
    }

    /**
     * @param mixed $lista
     * @param array<int, bool> $validos
     * @return list<int>
     */
    private function idsNaLista($lista, array $validos): array
    {
        $saida = [];
        foreach ($this->listaInts($lista) as $id) {
            if (isset($validos[$id]) && !in_array($id, $saida, true)) {
                $saida[] = $id;
            }
        }
        return $saida;
    }

    /**
     * @param mixed $lista
     * @return list<int>
     */
    private function listaInts($lista): array
    {
        if (!is_array($lista)) {
            return [];
        }
        $saida = [];
        foreach ($lista as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $saida[] = $id;
            }
        }
        return $saida;
    }

    private function faixa(int $valor, int $min, int $max, int $padrao): int
    {
        if ($valor <= 0) {
            return $padrao;
        }
        return max($min, min($max, $valor));
    }

    private function resumo(string $html): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if ($texto === '') {
            return 'Questão sem enunciado';
        }
        if (function_exists('mb_strlen') && mb_strlen($texto) > 140) {
            return mb_substr($texto, 0, 137) . '…';
        }
        if (strlen($texto) > 140) {
            return substr($texto, 0, 137) . '…';
        }
        return $texto;
    }

    private function urlPublica(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, 'data:') || preg_match('#^(https?:)?//#i', $url) === 1) {
            return $url;
        }
        $base = defined('URL') ? rtrim((string) URL, '/') : '';
        return $base . '/' . ltrim($url, '/');
    }

    private function dataTexto(string $data): string
    {
        $ts = strtotime($data);
        return $ts ? date('d/m/Y', $ts) : '';
    }

    private function horarioTexto(string $inicio, string $fim): string
    {
        $ini = strtotime($inicio);
        $end = strtotime($fim);
        if (!$ini && !$end) {
            return '';
        }
        $a = $ini ? date('H:i', $ini) : '';
        $b = $end ? date('H:i', $end) : '';
        if ($a !== '' && $b !== '') {
            return $a . ' às ' . $b;
        }
        return $a !== '' ? $a : $b;
    }
}
