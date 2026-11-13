<?php

namespace App\Modulos\Provas\Services\Assistente;

/**
 * Catálogo de tools do Assistente (motor v2) em JSON Schema strict.
 * O aluno entra sempre por aluno_nome / ra / aluno_da_conversa e é
 * resolvido no servidor (AssistenteResolvedorAluno) — o modelo nunca passa aluno_id.
 */
class AssistenteFerramentas
{
    /** Tools cujo alvo é um aluno (passam pelo resolvedor antes de executar). */
    public const TOOLS_ALUNO = [
        'listar_provas_aluno', 'detalhar_prova_aluno', 'resumo_provas_aluno',
        'listar_jornadas_aluno', 'detalhar_jornada_aluno', 'resumo_jornadas_aluno',
        'perfil_aluno_360', 'boletim_aluno', 'notas_evento_aluno', 'nota_evento_sigla',
        'faltas_aluno', 'ocorrencias_aluno', 'propor_entrada_saida', 'propor_chamada_aula',
    ];

    public const TOOLS_PROPOSTA = ['propor_criar_ocorrencia', 'propor_entrada_saida', 'propor_chamada_aula'];

    /** @return list<array<string,mixed>> */
    public static function definicoes(): array
    {
        $aluno = self::paramsAluno();
        $anoLetivo = ['ano_letivo' => self::inteiro('Ano letivo (ex.: 2024). null = ano atual.')];
        $turma = ['turma_nome' => self::texto('Nome da turma como o usuário escreveu (ex.: "2 Ano B", "1A").')];
        $professor = [
            'professor_nome' => self::texto('Nome do professor.'),
        ];

        return [
            self::tool('buscar_alunos', 'Localiza alunos por nome ou RA. Use só quando o usuário quer saber QUEM é o aluno, a turma ou o RA dele — para dados (boletim, faltas, ocorrências…) chame direto a tool do dado.', [
                'termo' => self::texto('Nome (ou parte) do aluno.'),
                'ra' => self::texto('RA/matrícula — código com dígitos. Nunca um nome.'),
                'turma' => self::texto('Turma para filtrar, se citada.'),
            ]),
            self::tool('listar_materias', 'Lista as matérias de provas cadastradas na escola.', []),
            self::tool('listar_tipos_avaliacao', 'Lista os tipos de avaliação (prova semanal, bimestral…).', []),
            self::tool('listar_provas_aluno', 'LEITURA. Lista as provas online feitas por um aluno (notas, acertos). "Última prova" = a mais recente desta lista.', $aluno + [
                'materia_nome' => self::texto('Matéria para filtrar.'),
                'bimestre' => self::inteiro('Bimestre 1–4.'),
                'tipo_avaliacao_nome' => self::texto('Tipo de avaliação (semanal, bimestral…).'),
            ]),
            self::tool('detalhar_prova_aluno', 'LEITURA. Questões de UMA prova do aluno (o que errou/acertou). Use prova_id das referências do estado quando houver.', $aluno + [
                'prova_id' => self::inteiro('Id da prova vindo das referências do estado. null se não souber.'),
                'titulo' => self::texto('Título/parte do título da prova, se o usuário citou.'),
                'materia_nome' => self::texto('Matéria da prova, se citada.'),
                'somente_erros' => self::booleano('true para mostrar só as questões erradas.'),
            ]),
            self::tool('resumo_provas_aluno', 'LEITURA. Consolidado de provas online do aluno (acertos/erros por matéria, bimestre, tipo).', $aluno + [
                'materia_nome' => self::texto('Matéria para filtrar.'),
                'bimestre' => self::inteiro('Bimestre 1–4.'),
            ]),
            self::tool('listar_materias_jornadas', 'Lista as matérias das jornadas.', []),
            self::tool('listar_jornadas_aluno', 'LEITURA. Lista as jornadas (trilhas) de um aluno.', $aluno + [
                'status_aluno' => self::enum(['concluida', 'em_andamento', 'nao_iniciada'], 'Filtro de status do aluno na jornada.'),
                'materia_nome' => self::texto('Matéria para filtrar.'),
            ]),
            self::tool('detalhar_jornada_aluno', 'LEITURA. Detalhe de UMA jornada do aluno (exercícios). Use jornada_id das referências do estado.', $aluno + [
                'jornada_id' => self::inteiro('Id da jornada vindo das referências do estado.'),
                'somente_erros' => self::booleano('true para só exercícios errados.'),
            ]),
            self::tool('resumo_jornadas_aluno', 'LEITURA. Consolidado das jornadas de um aluno (concluídas, em andamento, % médio).', $aluno),
            self::tool('buscar_professores', 'Localiza professores pelo nome.', [
                'termo' => self::texto('Nome do professor.'),
            ]),
            self::tool('listar_turmas_professor', 'Turmas em que um professor leciona.', $professor),
            self::tool('listar_provas_professor', 'Provas criadas por um professor.', $professor + $turma),
            self::tool('resumo_provas_professor', 'Consolidado das provas de um professor (acertos por matéria/turma).', $professor + $turma),
            self::tool('detalhar_prova_professor', 'Detalhe por aluno de uma prova do professor.', $professor + [
                'prova_id' => self::inteiro('Id da prova (referências do estado).'),
            ]),
            self::tool('ranking_erros_prova_professor', 'Questões mais erradas de uma prova de um professor. Sem prova_id a tool devolve as provas candidatas.', $professor + $turma + [
                'prova_id' => self::inteiro('Id da prova (referências do estado).'),
                'materia' => self::texto('Matéria da prova.'),
            ]),
            self::tool('saude_turmas_professor', 'Saúde (alunos críticos/atenção) das turmas de um professor.', $professor + $turma),
            self::tool('resumo_jornadas_professor', 'Consolidado das jornadas dos alunos de um professor.', $professor + $turma),
            self::tool('saude_turma', 'Saúde de UMA turma: KPIs de alunos crítico/atenção pelo rendimento.', $turma + $anoLetivo),
            self::tool('resumo_provas_turma', 'Totais de acertos/erros de provas de uma turma por matéria.', $turma),
            self::tool('saude_academica', 'Alertas CADASTRAIS da escola (sem matrícula no ano, sem chamada). Não é rendimento — para rendimento use saude_turma.', $turma + [
                'ano' => self::inteiro('Ano letivo.'),
            ]),
            self::tool('diario_pendencias', 'Diários de classe atrasados/pendentes, por turma ou professor.', $turma + $professor + [
                'situacao' => self::enum(['atraso', 'atencao'], 'Filtro de situação.'),
            ]),
            self::tool('grade_horaria_consulta', 'Grade horária / aulas do dia de uma turma ou professor. Não é chamada.', $turma + $professor + [
                'data' => self::texto('Data ISO AAAA-MM-DD. "hoje" = data de hoje do contexto.'),
            ]),
            self::tool('buscar_blocos', 'Busca blocos de prova (simulados) por título ou turma.', $turma + [
                'titulo' => self::texto('Título do bloco.'),
            ]),
            self::tool('resultados_bloco', 'Resultados de um bloco de prova.', [
                'bloco_id' => self::inteiro('Id do bloco (referências do estado).'),
                'titulo' => self::texto('Título do bloco.'),
            ]),
            self::tool('perfil_aluno_360', 'LEITURA. Visão geral do aluno (indicadores, atividade, pendências).', $aluno + $anoLetivo),
            self::tool('boletim_aluno', 'LEITURA. Boletim completo (quadro anual de notas por bimestre, com PDF).', $aluno + $anoLetivo),
            self::tool('notas_evento_aluno', 'LEITURA. UMA nota de matéria no boletim, ou coluna do boletim (PP, PU, MU, CH, PE, MS, MA, REC, PF).', $aluno + $anoLetivo + [
                'materia' => self::texto('Matéria exata (ex.: "Matemática 1").'),
                'coluna' => self::texto('Coluna do boletim (PP, PU, MU, CH, PE, MS, MA, REC, PF).'),
                'bimestre' => self::inteiro('Bimestre 1–4.'),
            ]),
            self::tool('listar_eventos_avaliativos', 'Catálogo de Eventos Avaliativos (siglas) desta escola.', $anoLetivo + [
                'termo' => self::texto('Filtro por nome/sigla.'),
            ]),
            self::tool('nota_evento_sigla', 'LEITURA. Nota do aluno num Evento Avaliativo cadastrado (sigla do catálogo, ex.: PB). NÃO use para colunas PP/PU/MU/CH/PE.', $aluno + $anoLetivo + [
                'sigla' => self::texto('Sigla do evento conforme o catálogo.'),
                'materia' => self::texto('Matéria.'),
                'bimestre' => self::inteiro('Bimestre 1–4.'),
            ]),
            self::tool('faltas_aluno', 'LEITURA. Faltas, frequência e presença de um aluno. Faltas NÃO são ocorrências.', $aluno + $anoLetivo),
            self::tool('ocorrencias_aluno', 'LEITURA. Lista ocorrências disciplinares JÁ registradas de um aluno ("ver/quais/tem ocorrências"). Nunca cria nada.', $aluno + $anoLetivo + [
                'status' => self::enum(['aberta', 'em_acompanhamento', 'encerrada'], 'Filtro de status. null = todas.'),
            ]),
            self::tool('ocorrencias_turma', 'LEITURA. Ocorrências registradas de uma turma.', $turma + [
                'status' => self::enum(['aberta', 'em_acompanhamento', 'encerrada', 'todas'], 'Filtro de status. null = abertas.'),
                'gravidade' => self::enum(['leve', 'moderado', 'grave'], 'Filtro de gravidade.'),
            ]),
            self::tool(
                'propor_criar_ocorrencia',
                'ESCRITA. Use quando o usuário quer CRIAR/REGISTRAR uma ocorrência. Chame já no primeiro pedido, preenchendo só o que o usuário disse (o resto null); o servidor guarda o rascunho e diz o que falta. Chame de novo a cada resposta do usuário durante a coleta. Nunca deduza gravidade nem notificação.',
                $aluno + [
                    'categoria' => self::texto('Categoria da ocorrência pelo nome que o usuário disse (ex.: "Comportamento").'),
                    'titulo' => self::texto('Título curto. null se o usuário não deu.'),
                    'detalhe' => self::texto('Descrição real do fato, com as palavras do usuário. null se ainda não descreveu.'),
                    'nivel_gravidade' => self::enum(['leve', 'moderado', 'grave'], 'Só se o usuário disse o nível ("médio" = moderado).'),
                    'mais_envolvidos' => self::booleano('true/false só quando o usuário respondeu se há mais alunos envolvidos.'),
                    'envolvidos' => self::listaTexto('Nomes dos outros alunos envolvidos, se houver.'),
                    'enviar_pais' => self::booleano('true/false só quando o usuário respondeu se notifica os responsáveis.'),
                ]
            ),
            self::tool('propor_entrada_saida', 'ESCRITA. Prepara registro de ENTRADA ou SAÍDA (portaria) de um aluno para o usuário confirmar.', $aluno + [
                'tipo' => self::enum(['entrada', 'saida'], 'Entrada ou saída.'),
                'ocorrido_em' => self::texto('Data/hora "AAAA-MM-DD HH:MM" (aceite 7h55). null = agora.'),
            ]),
            self::tool('propor_chamada_aula', 'ESCRITA. Prepara marcação de PRESENTE/FALTA na chamada do diário para o usuário confirmar.', $aluno + [
                'situacao' => self::enum(['presente', 'falta'], 'Presença ou falta.'),
                'data' => self::texto('Data da aula (BR ou ISO). null = hoje.'),
                'materia' => self::texto('Matéria da aula.'),
            ]),
        ];
    }

    /** @return list<string> */
    public static function nomes(): array
    {
        return array_map(static fn(array $d) => (string) $d['function']['name'], self::definicoes());
    }

    public static function promptSistema(string $catalogos): string
    {
        return <<<PROMPT
Você é a Tudinha, assistente da coordenação/direção escolar da EducaTudo.
Responda em português do Brasil.

Como trabalhar:
1. Para qualquer dado (notas, boletim, faltas, ocorrências, provas, jornadas, grade, diário, turmas, professores), CHAME a tool adequada. Nunca invente nem chute números, nomes ou datas.
2. Leia o pedido inteiro e escolha a tool pela descrição. Consultar/ver/quais/tem = LEITURA. Criar/registrar/lançar/abrir = ESCRITA (propor_*).
3. Aluno: passe aluno_nome exatamente como o usuário escreveu, ou ra quando ele der um código com dígitos. Se o usuário se referir ao aluno já em foco ("ela", "dele", "essa aluna", "deste aluno") ou não citar ninguém num pedido sobre aluno, use aluno_da_conversa=true. Se ele citar OUTRO nome, use esse nome — nunca troque pelo aluno da conversa.
4. Nunca peça id numérico ao usuário. Turma, ano e bimestre vão nos campos próprios, nunca dentro de aluno_nome.
5. Se o usuário mudar de assunto no meio de uma coleta, atenda o novo pedido.
6. Ações de escrita só preparam um rascunho; quem grava é o botão Confirmar. Nunca diga que gravou.
7. Depois de receber o resultado: responda em 1–3 frases, EXATAMENTE o que foi perguntado, citando o nome e a turma do aluno, com o valor principal em **negrito**. A interface mostra tabelas e PDF abaixo — não repita listas longas.
8. Se a tool devolver erro ou aviso, repasse-o com clareza. Não diga "não encontrei" se vieram candidatos.
9. Para conversa sem dado (saudação, dúvida de uso), responda curto sem tool.
10. Nunca mostre ids internos, JSON nem nomes de tools ao usuário.

{$catalogos}
PROMPT;
    }

    /** @return array<string,array<string,mixed>> */
    private static function paramsAluno(): array
    {
        return [
            'aluno_nome' => self::texto('Nome do aluno exatamente como o usuário escreveu (ex.: "Maria", "Ana Luiza"). null se usou pronome.'),
            'ra' => self::texto('RA/matrícula: código que contém dígitos (ex.: "24254", "SM24254"). NUNCA um nome.'),
            'aluno_da_conversa' => self::booleano('true quando o pedido se refere ao aluno já em foco na conversa (pronome ou sem nome).'),
            'turma' => self::texto('Turma, se o usuário citou junto do aluno.'),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $props
     * @return array<string,mixed>
     */
    private static function tool(string $nome, string $descricao, array $props): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $nome,
                'description' => $descricao,
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $props === [] ? new \stdClass() : $props,
                    'required' => array_keys($props),
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function texto(string $d): array
    {
        return ['type' => ['string', 'null'], 'description' => $d];
    }

    /** @return array<string,mixed> */
    private static function inteiro(string $d): array
    {
        return ['type' => ['integer', 'null'], 'description' => $d];
    }

    /** @return array<string,mixed> */
    private static function booleano(string $d): array
    {
        return ['type' => ['boolean', 'null'], 'description' => $d];
    }

    /**
     * @param list<string> $valores
     * @return array<string,mixed>
     */
    private static function enum(array $valores, string $d): array
    {
        return ['type' => ['string', 'null'], 'enum' => array_merge($valores, [null]), 'description' => $d];
    }

    /** @return array<string,mixed> */
    private static function listaTexto(string $d): array
    {
        return ['type' => ['array', 'null'], 'items' => ['type' => 'string'], 'description' => $d];
    }
}
