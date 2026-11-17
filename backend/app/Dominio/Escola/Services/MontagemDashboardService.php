<?php

namespace App\Dominio\Escola\Services;

use App\Core\LayoutHelper;

/**
 * Ordem e visibilidade dos blocos dos dashboards.
 * Sem registro salvo, o dashboard continua exatamente como está hoje.
 */
class MontagemDashboardService
{
    /** @var list<string> */
    public const PERFIS = ['admin', 'aluno', 'professor'];

    /** @var array<string, array{itens: list<array{chave:string,nome:string,descricao:string,visivel:bool}>, personalizada: bool}> */
    private static array $memo = [];

    public static function chaveConfig(string $perfil): string
    {
        return 'dashboard_montagem_' . $perfil;
    }

    /**
     * @return list<array{chave:string,nome:string,descricao:string}>
     */
    public static function catalogo(string $perfil): array
    {
        $map = [
            'admin' => [
                ['chave' => 'avisos', 'nome' => 'Alunos online e alertas', 'descricao' => 'Contadores no topo, quando houver alguém online ou alerta novo.'],
                ['chave' => 'aulas_online', 'nome' => 'Aulas online', 'descricao' => 'Aulas ao vivo ou agendadas.'],
                ['chave' => 'kpis', 'nome' => 'Indicadores', 'descricao' => 'Cartões de resumo da escola.'],
                ['chave' => 'evolucao', 'nome' => 'Evolução', 'descricao' => 'Gráfico de evolução do período.'],
                ['chave' => 'desempenho', 'nome' => 'Desempenho', 'descricao' => 'Disciplinas, distribuição de notas e situação dos alunos.'],
                ['chave' => 'frequencia_hoje', 'nome' => 'Frequência de hoje', 'descricao' => 'Chamada do dia por turma.'],
                ['chave' => 'atencao_pedagogica', 'nome' => 'Atenção pedagógica', 'descricao' => 'Alunos que pedem olhar da coordenação.'],
                ['chave' => 'calendario', 'nome' => 'Calendário', 'descricao' => 'Próximos eventos do calendário escolar.'],
                ['chave' => 'ocorrencias', 'nome' => 'Ocorrências', 'descricao' => 'Ocorrências recentes.'],
                ['chave' => 'pendencias', 'nome' => 'Para fazer', 'descricao' => 'Diários, ocorrências e notas pendentes.'],
                ['chave' => 'avaliacoes', 'nome' => 'Avaliações', 'descricao' => 'Eventos de prova do período.'],
                ['chave' => 'conselho', 'nome' => 'Conselho', 'descricao' => 'Situação do conselho de classe.'],
                ['chave' => 'matriculas', 'nome' => 'Matrículas', 'descricao' => 'Movimento de matrículas.'],
            ],
            'aluno' => [
                ['chave' => 'slider', 'nome' => 'Slider', 'descricao' => 'Faixa de imagens. Quando há slides, os números do dia ficam de fora.'],
                ['chave' => 'numeros', 'nome' => 'Números do dia', 'descricao' => 'Exercícios, redações, chat e média. Aparece quando o slider está oculto ou sem imagens.'],
                ['chave' => 'acoes', 'nome' => 'Jornadas, mural e provas', 'descricao' => 'Atalhos de jornadas em aberto, mural, provas e jornada de redação.'],
                ['chave' => 'atalhos', 'nome' => 'Atalhos', 'descricao' => 'Flashcards e mural de recados.'],
                ['chave' => 'aulas_online', 'nome' => 'Aulas online', 'descricao' => 'Aulas ao vivo ou agendadas para o aluno.'],
                ['chave' => 'jornadas_ativas', 'nome' => 'Jornadas ativas', 'descricao' => 'Lista das jornadas da turma.'],
                ['chave' => 'agenda', 'nome' => 'Agenda do mês', 'descricao' => 'Calendário do mês na tela inicial.'],
            ],
            'professor' => [
                ['chave' => 'calendario', 'nome' => 'Calendário escolar', 'descricao' => 'Mês atual do calendário. Aparece no painel do Grupo SEB.'],
                ['chave' => 'aulas_hoje', 'nome' => 'Aulas de hoje', 'descricao' => 'Grade do dia. Aparece no painel do Grupo SEB.'],
                ['chave' => 'desempenho', 'nome' => 'Desempenho das turmas', 'descricao' => 'Média das turmas. Aparece no painel do Grupo SEB.'],
                ['chave' => 'resumo', 'nome' => 'Resumo', 'descricao' => 'Jornadas, mensagens e turmas. Fica de fora no painel do Grupo SEB.'],
                ['chave' => 'jornadas_recentes', 'nome' => 'Jornadas recentes', 'descricao' => 'Últimas jornadas criadas. Fica de fora no painel do Grupo SEB.'],
                ['chave' => 'provas', 'nome' => 'Provas', 'descricao' => 'Eventos de prova recentes.'],
            ],
        ];

        return $map[$perfil] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function padrao(string $perfil): array
    {
        return array_column(self::catalogo($perfil), 'chave');
    }

    /**
     * @return list<array{chave:string,nome:string,descricao:string,visivel:bool}>
     */
    public static function ler(string $perfil): array
    {
        return self::montado($perfil)['itens'];
    }

    public static function visivel(string $perfil, string $chave): bool
    {
        foreach (self::ler($perfil) as $item) {
            if ($item['chave'] === $chave) {
                return $item['visivel'];
            }
        }

        return true;
    }

    public static function ordemPersonalizada(string $perfil): bool
    {
        return self::montado($perfil)['personalizada'];
    }

    public static function posicao(string $perfil, string $chave): int
    {
        $i = 0;
        foreach (self::ler($perfil) as $item) {
            if ($item['chave'] === $chave) {
                return $i;
            }
            $i++;
        }

        return $i;
    }

    /**
     * @return array{itens: list<array{chave:string,nome:string,descricao:string,visivel:bool}>, personalizada: bool}
     */
    private static function montado(string $perfil): array
    {
        if (isset(self::$memo[$perfil])) {
            return self::$memo[$perfil];
        }

        $catalogo = self::catalogo($perfil);
        $porChave = [];
        foreach ($catalogo as $item) {
            $porChave[$item['chave']] = $item;
        }

        $salvos = self::salvos($perfil);
        $ordem = [];
        $visivel = [];
        if ($salvos === null) {
            foreach ($catalogo as $item) {
                $ordem[] = $item['chave'];
                $visivel[$item['chave']] = true;
            }
        } else {
            foreach ($salvos as $linha) {
                if (!is_array($linha)) {
                    continue;
                }
                $chave = (string) ($linha['chave'] ?? '');
                if (!isset($porChave[$chave]) || isset($visivel[$chave])) {
                    continue;
                }
                $ordem[] = $chave;
                $visivel[$chave] = !empty($linha['visivel']);
            }
            foreach ($catalogo as $item) {
                if (!isset($visivel[$item['chave']])) {
                    $ordem[] = $item['chave'];
                    $visivel[$item['chave']] = true;
                }
            }
        }

        $itens = [];
        foreach ($ordem as $chave) {
            $item = $porChave[$chave];
            $item['visivel'] = $visivel[$chave];
            $itens[] = $item;
        }

        self::$memo[$perfil] = [
            'itens' => $itens,
            'personalizada' => $salvos !== null && $ordem !== self::padrao($perfil),
        ];

        return self::$memo[$perfil];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private static function salvos(string $perfil): ?array
    {
        $bruto = LayoutHelper::get(self::chaveConfig($perfil), '');
        if (!is_string($bruto) || trim($bruto) === '') {
            return null;
        }
        $json = json_decode($bruto, true);
        if (!is_array($json)) {
            return null;
        }

        return $json;
    }
}
