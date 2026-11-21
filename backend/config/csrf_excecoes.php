<?php
/**
 * Prefixos de URI isentos do CsrfMiddleware (rotas autenticadas).
 *
 * APIs JWT e webhooks públicos NÃO passam por este middleware (ficam fora do grupo Auth).
 * Liste aqui apenas POSTs autenticados com proteção alternativa ou legado em migração.
 *
 * Comparação: str_starts_with no path normalizado (sem query string).
 */
return [
    // Exemplos / legado — ampliar só com justificativa e ticket.
    // '/professor/api/', // se algum endpoint de monitoramento não enviar _token
];
