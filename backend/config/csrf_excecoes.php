<?php
/**
 * Prefixos de URI isentos do CsrfMiddleware (rotas autenticadas).
 *
 * APIs JWT e webhooks públicos NÃO passam por este middleware (ficam fora do grupo Auth).
 * Liste aqui apenas POSTs autenticados com proteção alternativa ou legado em migração.
 *
 * Comparação: str_starts_with no path normalizado (sem query string).
 *
 * Isenções abaixo: sendBeacon/beforeunload não permitem header X-CSRF-TOKEN.
 * Proteção alternativa = sessão Auth + ownership no controller.
 */
return [
    // Único POST sob este prefixo: cancelar-seguro (sendBeacon ao fechar aba)
    '/aluno/provas/bloco/',

    // Telemetria de prova via sendBeacon (JSON sem headers custom)
    '/aluno/provas/log-evento',

    // Abandono de jogo ao fechar aba (sendBeacon)
    '/jogo-milhao/abandonar',
];
