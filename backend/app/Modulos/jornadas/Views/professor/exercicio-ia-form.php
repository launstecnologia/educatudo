<?php
// Usar o layout do professor
$data = [
    'title' => 'Gerar Exercícios com IA - EducaTudo',
    'user' => $user,
    'current_page' => 'journeys',
    'jornada' => $jornada,
    'materias' => $materias,
    'aulas' => $aulas,
    'jornada_id' => $jornada_id,
    'csrf_token' => $csrf_token
];
$this->viewWithLayout('professor', 'teacher/journeys/exercicio-ia-form-content', $data);
?>