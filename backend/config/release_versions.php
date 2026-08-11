<?php

/**
 * Catálogo simples de versões liberáveis por escola (Master > Escola > Módulos).
 * Formato:
 * [
 *   ['value' => '2026.05.01-minha-feature', 'label' => 'Minha Feature', 'commit' => 'abc1234'],
 * ]
 */
return [
    [
        'value' => '2026.07.31-sidebar-override-aluno-v5',
        'label' => 'Aluno - Sidebar padrao Colag (anexo 2): texto branco; highlight so no item ativo (corrige caixas em todos os links)',
        'commit' => '92030eec',
    ],
    [
        'value' => '2026.07.31-sidebar-override-aluno-v4',
        'label' => 'Aluno - Sidebar texto branco forçado (CSS final + inline + JS) padrao Colag; marcador data-sidebar-fix=v4',
        'commit' => '549d959f',
    ],
    [
        'value' => '2026.07.31-sidebar-override-aluno-v3',
        'label' => 'Aluno - Force texto claro na sidebar no layout (marcador data-sidebar-fix=20260731-v3 para validar deploy)',
        'commit' => '004e7fa9',
    ],
    [
        'value' => '2026.07.31-sidebar-texto-claro-color-mix-v2',
        'label' => 'Layout - Corrige sidebar escura (color-mix com transparent virava preto; texto/ícones claros no padrão Colag)',
        'commit' => 'bb17e269',
    ],
    [
        'value' => '2026.07.31-sidebar-contraste-texto-v1',
        'label' => 'Layout - Sidebar com contraste automático (texto claro em fundo colorido; corrige menu escuro ilegível)',
        'commit' => '25fefbd0',
    ],
    [
        'value' => '2026.07.31-aluno-sessao-desliga-htmx-boost-v2',
        'label' => 'Aluno - Desliga HTMX soft-nav no portal (sidebar sempre recarrega; evita misturar alunos no lab)',
        'commit' => '9b50d002',
    ],
    [
        'value' => '2026.07.31-aluno-sessao-htmx-mistura-abas-v1',
        'label' => 'Aluno - Evita misturar alunos no mesmo navegador (HTMX sidebar stale + sync entre abas + SW cache)',
        'commit' => '737e7c52',
    ],
    [
        'value' => '2026.07.21-tickets-mensagem-html-render-v2',
        'label' => 'Suporte/Tickets - Corrige tags HTML cruas (decode de entidades + remove ql-cursor + render Quill)',
        'commit' => '7b51a404',
    ],
    [
        'value' => '2026.07.21-tickets-mensagem-html-render-v1',
        'label' => 'Suporte/Tickets - Renderiza HTML do Quill (sem tags cruas), desfaz double-escape e remove ql-cursor',
        'commit' => '9b771d40',
    ],
    [
        'value' => '2026.06.11-provas-canceladas-alerta-listagem-v1',
        'label' => 'Admin/Provas - Badge "N cancelada(s)" na listagem de blocos, com link direto para a tela de Canceladas do bloco',
        'commit' => 'cb2aa2b',
    ],
    [
        'value' => '2026.06.11-provas-cancelados-validacao-senha-v1',
        'label' => 'Admin/Provas - Botão Cancelados no gerenciar bloco; Validar nota exige senha do coordenador e grava histórico de validações',
        'commit' => '6a62cd1',
    ],
    [
        'value' => '2026.06.11-provas-realizacoes-status-cancelada-v1',
        'label' => 'Aluno/Provas modo seguro - Corrige "Data truncated for column status" (ENUM sem cancelada vira VARCHAR; auto-fix em runtime + migration)',
        'commit' => 'f03d032',
    ],
    [
        'value' => '2026.06.11-provas-cancelamento-via-redirect-v1',
        'label' => 'Aluno/Provas modo seguro - Cancelamento garantido via redirect (?cancelar_bloco grava no PHP, sem depender de AJAX)',
        'commit' => 'f69f49e',
    ],
    [
        'value' => '2026.06.11-provas-minhas-provas-bloqueio-cancelada-v1',
        'label' => 'Aluno/Minhas Provas - Bloqueia Iniciar prova quando cancelada (consulta direta no banco)',
        'commit' => '85470a8',
    ],
    [
        'value' => '2026.06.11-provas-modo-seguro-cancelamento-v5',
        'label' => 'Aluno/Provas modo seguro - Cancelamento no PHP (encerrado=1), UPDATE direto no banco e cancela também pelo iframe',
        'commit' => '7c21e70',
    ],
    [
        'value' => '2026.06.11-provas-modo-seguro-cancelamento-v4',
        'label' => 'Aluno/Provas modo seguro - Corrige cancelamento ignorado (permitirSair bloqueava gravação no banco)',
        'commit' => '998fbf3',
    ],
    [
        'value' => '2026.06.11-provas-modo-seguro-cancelamento-v3',
        'label' => 'Aluno/Provas modo seguro - Cancela no banco ao abrir modal (não só no OK); fecha iframe e atualiza Minhas Provas',
        'commit' => 'ccfcbc6',
    ],
    [
        'value' => '2026.06.10-provas-modo-seguro-cancelamento-v2',
        'label' => 'Aluno/Provas modo seguro - Corrige cancelamento efetivo (grava cancelada no banco, bloqueia acesso e exibe no painel)',
        'commit' => '708a33c',
    ],
    [
        'value' => '2026.06.10-provas-modo-seguro-bloqueio-rigoroso-v1',
        'label' => 'Aluno/Provas modo seguro - Restaura bloqueio rigoroso (sair da aba/tela cheia cancela); mantém Validar nota para coordenador',
        'commit' => '23ace59',
    ],
    [
        'value' => '2026.06.10-redacao-propostas-ordem-numerica-v1',
        'label' => 'Redação/Propostas - Ordena listagem por número no título (01, 02…); professor, aluno e admin',
        'commit' => 'b8b5ee2',
    ],
    [
        'value' => '2026.05.29-provas-modo-seguro-cancelada-v1',
        'label' => 'Aluno/Provas modo seguro - Evita cancelamento da prova ativa; permite finalizar com respostas salvas; coordenador valida nota em canceladas',
        'commit' => 'be09211',
    ],
    [
        'value' => '2026.06.10-professor-jornadas-relatorio-v1',
        'label' => 'Professor/Jornadas - Corrige relatório em /professor/jornadas/relatorio (view teacher/jornadas-relatorio ausente; escopo só jornadas do professor)',
        'commit' => '9b41476',
    ],
    [
        'value' => '2026.05.29-admin-jornadas-relatorio-v1',
        'label' => 'Admin/Jornadas - Corrige relatório em /admin/jornadas/relatorio (view ausente); reutiliza filtros e tabela de JornadasRelatorioService',
        'commit' => 'c0a1919',
    ],
    [
        'value' => '2026.05.29-provas-finalizar-aluno-v1',
        'label' => 'Aluno/Provas - Corrige finalização (HY093/PDO no cálculo de nota, todosFinalizaram, pós-finalização não bloqueante, JSON no Finalizar)',
        'commit' => 'f7d39e6',
    ],
    [
        'value' => '2026.05.13-admin-relatorios-jornadas-v2',
        'label' => 'Admin/Relatórios - Aba Jornadas (v2): filtros dentro da aba + formulário por aba; refinamento (professor, matéria, ano, bimestre, avaliativa, atenção) só em Jornadas; totais/ranking; JornadasRelatorioService',
        'commit' => '8e910bc',
    ],
    [
        'value' => '2026.05.12-admin-relatorios-jornadas-v1',
        'label' => 'Admin/Relatórios - Aba Jornadas (v1): filtros globais acima das abas; totais concluídas/pendentes, ranking e alunos em atenção',
        'commit' => 'ac8754f',
    ],
    [
        'value' => '2026.05.11-jornadas-tudinha-explica-cache-v1',
        'label' => 'Aluno/Jornadas - Tudinha explica exercício: explicação estável (cache DB + temp 0) v1',
        'commit' => '014c7ae',
    ],
    [
        'value' => '2026.05.11-exclusao-botao-jornadas-inicio v3',
        'label' => 'Aluno/Jornadas) v3',
        'commit' => '0b0afb9',
    ],
    [
        'value' => '2026.05.11-jornadas-tudinha-explica-pos-conclusao-v2',
        'label' => 'Aluno/Jornadas - Tudinha explica (modal exercício: alternativas + gpt-4o) v2',
        'commit' => '0d741de',
    ],
    [
        'value' => '2026.05.11-jornadas-tudinha-explica-pos-conclusao-v1',
        'label' => 'Aluno/Jornadas - Tudinha explica após conclusão v1',
        'commit' => '48567ad',
    ],
    [
        'value' => '2026.05.06-aluno-redacao-menu-v1',
        'label' => 'Aluno - Menu Redação/Jornada da Redação v1',
        'commit' => '6a80dec',
    ],
    [
        'value' => '2026.05.04-jornadas-enunciado-sem-duplicacao-v1',
        'label' => 'Jornadas - Evitar enunciado duplicado (execução) v1',
        'commit' => 'b7e3048',
    ],
    [
        'value' => '2026.05.01-boletim-intervalo-datas',
        'label' => 'Boletim - Simulação por intervalo de datas',
        'commit' => '089d824',
    ],
    [
        'value' => '2026.04.30-jornadas-ocultar-titulo-exercicio-v1',
        'label' => 'Jornadas - Ocultar titulo do exercicio v1',
        'commit' => 'd7e28b2',
    ],
    [
        'value' => '2026.04.30-jornadas-ocultar-titulo-v2',
        'label' => 'Jornadas - Ocultar titulo v2',
        'commit' => '4100063',
    ],
    [
        'value' => '2026.04.30-jornadas-ocultar-titulo-v1',
        'label' => 'Jornadas - Ocultar titulo v1',
        'commit' => '3742b8e',
    ],
    [
        'value' => '2026.04.30-jornadas-fix',
        'label' => 'Jornadas/Redação - Ajustes iniciais',
        'commit' => '571dfb1',
    ],
    [
        'value' => '2026.04.30-arquivos-download',
        'label' => 'Arquivos - Upload Office e Download aluno',
        'commit' => '9d4ffe3',
    ],
];
