<?php
/**
 * API mobile v1.
 *
 * Estas rotas ficam isoladas da API legada em /api para permitir evolução do
 * contrato sem alterar os consumidores existentes.
 */

$router->get('/api/v1/status', 'Api/V1/ApiStatusController@index');

// Rotas do totem de reconhecimento facial (dispositivos físicos)
$router->post('/api/v1/facial-devices/pair',                          'Api/V1/FacialDeviceApiController@pair');
$router->get('/api/v1/facial-device/me',                              'Api/V1/FacialDeviceApiController@me');
$router->post('/api/v1/facial-device/logout',                         'Api/V1/FacialDeviceApiController@logout');
$router->get('/api/v1/facial-device/classes',                         'Api/V1/FacialDeviceApiController@classes');
$router->get('/api/v1/facial-device/students',                        'Api/V1/FacialDeviceApiController@students');
$router->get('/api/v1/facial-device/students/{id}',                   'Api/V1/FacialDeviceApiController@student');
$router->post('/api/v1/facial-device/students/{id}/face-samples',     'Api/V1/FacialDeviceApiController@registerSample');
$router->post('/api/v1/facial-device/students/{id}/face-status',      'Api/V1/FacialDeviceApiController@syncFaceStatus');
$router->post('/api/v1/facial-device/students/{id}/samples',          'Api/V1/FacialDeviceApiController@registerSample');
$router->post('/api/v1/facial-device/alunos/{id}/amostras',           'Api/V1/FacialDeviceApiController@registerSample');
$router->post('/api/v1/facial-device/alunos/{id}/face-samples',       'Api/V1/FacialDeviceApiController@registerSample');
$router->delete('/api/v1/facial-device/students/{id}/face-profile',   'Api/V1/FacialDeviceApiController@deleteProfile');
$router->post('/api/v1/facial-device/recognize',                      'Api/V1/FacialDeviceApiController@recognize');
$router->post('/api/v1/facial-device/attendance',                     'Api/V1/FacialDeviceApiController@attendance');
$router->get('/api/v1/facial-device/events',                          'Api/V1/FacialDeviceApiController@events');

$router->post('/api/v1/auth/login', 'Api/V1/MobileAuthController@login');
$router->post('/api/v1/auth/refresh', 'Api/V1/MobileAuthController@refresh');

$router->middleware('MobileApiAuth', function ($router) {
    $router->get('/api/v1/me', 'Api/V1/MobileParentController@me');
    $router->get('/api/v1/students', 'Api/V1/MobileParentController@students');
    $router->get('/api/v1/students/{id}/dashboard', 'Api/V1/MobileAcademicController@dashboard');
    $router->get('/api/v1/students/{id}/exams', 'Api/V1/MobileAcademicController@exams');
    $router->get('/api/v1/students/{id}/journeys', 'Api/V1/MobileAcademicController@journeys');
    $router->get('/api/v1/students/{id}/lesson-plans', 'Api/V1/MobileAcademicController@lessonPlans');
    $router->get('/api/v1/students/{id}/lesson-plans/{planId}', 'Api/V1/MobileAcademicController@lessonPlan');
    $router->get('/api/v1/students/{id}/writing-journeys', 'Api/V1/MobileAcademicController@writingJourneys');
    $router->get('/api/v1/students/{id}/essays', 'Api/V1/MobileAcademicController@essays');
    $router->get('/api/v1/students/{id}/notices', 'Api/V1/MobileAcademicController@notices');
    $router->get('/api/v1/students/{id}/report-card', 'Api/V1/MobileAcademicController@reportCard');
    $router->get('/api/v1/students/{id}/access-events', 'Api/V1/MobileAcademicController@accessEvents');
    $router->get('/api/v1/students/{id}/finance', 'Api/V1/MobileFinanceController@student');
    $router->get('/api/v1/students/{id}/finance/invoices/{source}/{invoiceId}/payment', 'Api/V1/MobileFinanceController@payment');
    $router->get('/api/v1/students/{id}/school-communications', 'Api/V1/MobileSchoolCommunicationController@index');
    $router->get('/api/v1/students/{id}/school-communications/{communicationId}', 'Api/V1/MobileSchoolCommunicationController@show');
    $router->post('/api/v1/students/{id}/school-communications/{communicationId}/read', 'Api/V1/MobileSchoolCommunicationController@read');
    $router->post('/api/v1/students/{id}/school-communications/{communicationId}/replies', 'Api/V1/MobileSchoolCommunicationController@reply');
    $router->get('/api/v1/students/{id}/calendar-events', 'Api/V1/MobileSchoolCommunicationController@calendar');
    $router->post('/api/v1/students/{id}/calendar-events/{eventId}/read', 'Api/V1/MobileSchoolCommunicationController@calendarRead');
    $router->get('/api/v1/notifications', 'Api/V1/MobileNotificationController@index');
    $router->post('/api/v1/notifications/{id}/read', 'Api/V1/MobileNotificationController@read');
    $router->post('/api/v1/auth/logout', 'Api/V1/MobileAuthController@logout');
    $router->put('/api/v1/devices/{deviceId}', 'Api/V1/MobileDeviceController@upsert');
    $router->delete('/api/v1/devices/{deviceId}', 'Api/V1/MobileDeviceController@delete');
});
