<?php
/**
 * Entrada legada — docroot deve apontar para public/.
 * Este arquivo existe apenas para bloquear execução acidental na raiz do projeto.
 */
http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
echo "403 Forbidden — configure o docroot do servidor para a pasta public/\n";
exit;
