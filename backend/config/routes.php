<?php
/**
 * EducaTudo - Definição de Rotas
 * Router é passado pelo App.php como $router.
 * Rotas organizadas por perfil em config/routes/*.php
 */

require __DIR__ . '/routes/master.php';
require __DIR__ . '/routes/public.php';
require __DIR__ . '/routes/api_v1.php';

$router->middleware('Auth', function ($router) {
    require __DIR__ . '/routes/student.php';
    require __DIR__ . '/routes/monitor.php';
    require __DIR__ . '/routes/teacher.php';
    require __DIR__ . '/routes/admin.php';
    require __DIR__ . '/routes/parents.php';
});
