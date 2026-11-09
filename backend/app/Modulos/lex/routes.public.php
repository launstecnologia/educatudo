<?php
/**
 * Entrada, retorno e saída do login LEX. Sem sessão prévia.
 *
 * @var Router $router
 */

$router->get('/lex/entrar', 'Modulos/lex/LexLoginController@entrar');
$router->get('/lex/retorno', 'Modulos/lex/LexLoginController@retorno');
$router->get('/lex/saida', 'Modulos/lex/LexLoginController@saida');
