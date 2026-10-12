#!/usr/bin/env python3
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from extrair_modulo import extrair

extrair(
    chave="mural",
    label="Mural de Recados",
    controllers=[
        "Controllers/Admin/AdminMuralController.php",
        "Controllers/Teacher/TeacherMuralController.php",
    ],
    routes_body="""
// Admin
$router->get('/admin/mural-recados', 'Modulos/mural/AdminMuralController@index');
$router->get('/admin/mural-recados/criar', 'Modulos/mural/AdminMuralController@criar');
$router->post('/admin/mural-recados/salvar', 'Modulos/mural/AdminMuralController@salvar');
$router->get('/admin/mural-recados/{id}/dados', 'Modulos/mural/AdminMuralController@dados');
$router->get('/admin/mural-recados/editar', 'Modulos/mural/AdminMuralController@editar');
$router->post('/admin/mural-recados/atualizar', 'Modulos/mural/AdminMuralController@atualizar');
$router->post('/admin/mural-recados/excluir', 'Modulos/mural/AdminMuralController@excluir');

// Professor
$router->get('/professor/mural-recados', 'Modulos/mural/TeacherMuralController@index');
$router->get('/professor/mural-recados/criar', 'Modulos/mural/TeacherMuralController@criar');
$router->post('/professor/mural-recados/salvar', 'Modulos/mural/TeacherMuralController@salvar');
$router->get('/professor/mural-recados/editar', 'Modulos/mural/TeacherMuralController@editar');
$router->post('/professor/mural-recados/atualizar', 'Modulos/mural/TeacherMuralController@atualizar');
$router->post('/professor/mural-recados/excluir', 'Modulos/mural/TeacherMuralController@excluir');
""",
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            ["/admin/mural-recados"],
            "// Mural → Modulos/mural/routes.php",
        ),
        (
            "backend/config/routes/teacher.php",
            ["/professor/mural-recados"],
            "// Mural → Modulos/mural/routes.php",
        ),
    ],
)
print("DONE mural")
