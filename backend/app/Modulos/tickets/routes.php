<?php
/**
 * Tickets de suporte do aluno.
 * A fila do Master continua em config/routes/master.php.
 *
 * @var Router $router
 */

$router->get('/tickets', 'Tickets/TicketController@index');
$router->get('/tickets/criar', 'Tickets/TicketController@criar');
$router->post('/tickets/processar-criar', 'Tickets/TicketController@processarCriar');
$router->get('/tickets/visualizar', 'Tickets/TicketController@visualizar');
$router->post('/tickets/enviar-mensagem', 'Tickets/TicketController@enviarMensagem');
$router->post('/tickets/upload-arquivo', 'Tickets/TicketController@uploadArquivo');
$router->post('/tickets/upload-imagem', 'Tickets/TicketController@uploadImagem');
