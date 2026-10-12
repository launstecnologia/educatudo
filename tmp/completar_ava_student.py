#!/usr/bin/env python3
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from extrair_modulo import move_controller, strip_routes_from_file, ROOT

ns = "Ava"
chave = "ava"

for rel in [
    "Controllers/Student/AvaStudentController.php",
    "Controllers/Student/AvaLiveStudentController.php",
    "Controllers/Student/AvaCertificateStudentController.php",
]:
    src = ROOT / "backend/app" / rel
    if src.exists() and src.stat().st_size > 500:
        move_controller(rel, chave, ns)

extra = """
$router->get('/cursos', 'Modulos/ava/AvaStudentController@index');
$router->get('/cursos/agenda', 'Modulos/ava/AvaStudentController@agenda');
$router->get('/cursos/anexo/{id}', 'Modulos/ava/AvaStudentController@anexo');
$router->post('/api/ava/aula/{id}/progresso-video', 'Modulos/ava/AvaStudentController@saveVideoProgress');
$router->get('/cursos/ao-vivo/{id}', 'Modulos/ava/AvaLiveStudentController@room');
$router->get('/cursos/certificados', 'Modulos/ava/AvaCertificateStudentController@index');
"""
routes = ROOT / "backend/app/Modulos/ava/routes.php"
text = routes.read_text()
if "AvaStudentController@index" not in text:
    routes.write_text(text.rstrip() + "\n" + extra)
    print("appended student hub routes")
else:
    print("student hub already in routes")

# strip remaining from student - careful with /cursos only exact lines for AVA
# Use specific prefixes that are AVA
strip_routes_from_file(
    "backend/config/routes/student.php",
    [
        "'/cursos'",
        "/cursos/agenda",
        "/cursos/anexo/",
        "/api/ava/",
        "/cursos/ao-vivo/",
        "/cursos/certificados",
    ],
    "// AVA aluno → Modulos/ava/routes.php",
)

# Also inject AdminBase/Base uses if needed
from pathlib import Path as P
import re
for p in (ROOT / "backend/app/Modulos/ava/Controllers").glob("*.php"):
    t = p.read_text()
    changed = False
    if "extends AdminBaseController" in t and "use App\\Controllers\\Admin\\AdminBaseController" not in t:
        t = t.replace(
            "namespace App\\Modulos\\Ava\\Controllers;",
            "namespace App\\Modulos\\Ava\\Controllers;\n\nuse App\\Controllers\\Admin\\AdminBaseController;",
            1,
        )
        changed = True
    if "extends BaseController" in t and "use App\\Core\\BaseController" not in t:
        t = t.replace(
            "namespace App\\Modulos\\Ava\\Controllers;",
            "namespace App\\Modulos\\Ava\\Controllers;\n\nuse App\\Core\\AuthManager;\nuse App\\Core\\BaseController;\nuse App\\Core\\Database;",
            1,
        )
        changed = True
    if changed:
        p.write_text(t)
        print("uses fixed", p.name)
