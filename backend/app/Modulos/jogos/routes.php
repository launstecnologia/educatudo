<?php
/**
 * Jogos do aluno.
 *
 * @var Router $router
 */

$router->get('/jogos/access', 'Games/GameController@access');
$router->get('/jogos', 'Games/GameController@index');
$router->get('/jogos/xadrez', 'Games/GameController@xadrez');
$router->get('/jogos/damas', 'Games/GameController@damas');
$router->get('/jogos/milhao', 'Games/GameController@milhao');
$router->get('/jogo-milhao', 'Games/MillionGameController@index');
$router->get('/jogo-milhao/jogar', 'Games/MillionGameController@jogar');
$router->post('/jogo-milhao/iniciar', 'Games/MillionGameController@iniciarPartida');
$router->post('/jogo-milhao/continuar', 'Games/MillionGameController@continuarPartida');
$router->post('/jogo-milhao/responder', 'Games/MillionGameController@responderPergunta');
$router->post('/jogo-milhao/ajuda', 'Games/MillionGameController@usarAjuda');
$router->post('/jogo-milhao/abandonar', 'Games/MillionGameController@abandonar');
$router->post('/jogo-milhao/heartbeat', 'Games/MillionGameController@heartbeat');
$router->post('/jogo-milhao/verificar-partida', 'Games/MillionGameController@verificarPartida');
$router->post('/jogo-milhao/limpar-orfas', 'Games/MillionGameController@limparOrfas');
