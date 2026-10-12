#!/usr/bin/env python3
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from extrair_modulo import extrair, ROOT


def rewrite_routes(file_rel: str, path_substr: str, replacements: list[tuple[str, str]]) -> list[str]:
    out = []
    for line in (ROOT / file_rel).read_text().splitlines():
        if not line.strip().startswith("$router->"):
            continue
        if path_substr not in line:
            continue
        l2 = line
        for old, new in replacements:
            l2 = l2.replace(old, new)
        out.append(l2)
    return out


# 1) Aulas online
aulas = rewrite_routes(
    "backend/config/routes/admin.php",
    "/admin/aulas-online",
    [("Admin/OnlineClassController@", "Modulos/aulas-online/OnlineClassController@")],
)
extrair(
    chave="aulas-online",
    label="Aulas Online",
    controllers=["Controllers/Admin/OnlineClassController.php"],
    routes_body="\n".join(aulas),
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            ["/admin/aulas-online"],
            "// Aulas online → Modulos/aulas-online/routes.php",
        )
    ],
)

# 2) Minicursos
mini = rewrite_routes(
    "backend/config/routes/admin.php",
    "/admin/minicursos",
    [("Minicursos/AdminMinicursoController@", "Modulos/minicursos/AdminMinicursoController@")],
)
# student
for line in (ROOT / "backend/config/routes/student.php").read_text().splitlines():
    if line.strip().startswith("$router->") and "minicurso" in line.lower():
        mini.append(
            line.replace(
                "Minicursos/StudentMinicursoController@",
                "Modulos/minicursos/StudentMinicursoController@",
            )
        )
extrair(
    chave="minicursos",
    label="Minicursos",
    controllers=[
        "Controllers/Minicursos/AdminMinicursoController.php",
        "Controllers/Minicursos/StudentMinicursoController.php",
    ],
    routes_body="\n".join(mini),
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            ["/admin/minicursos"],
            "// Minicursos → Modulos/minicursos/routes.php",
        ),
        (
            "backend/config/routes/student.php",
            ["/minicurso", "/minicursos"],
            "// Minicursos → Modulos/minicursos/routes.php",
        ),
    ],
)

# 3) Exercícios (admin)
ex = rewrite_routes(
    "backend/config/routes/admin.php",
    "/admin/exercises",
    [("Admin/ExerciseAdminController@", "Modulos/exercicios/ExerciseAdminController@")],
)
extrair(
    chave="exercicios",
    label="Exercícios",
    controllers=["Controllers/Admin/ExerciseAdminController.php"],
    routes_body="\n".join(ex),
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            ["/admin/exercises"],
            "// Exercícios → Modulos/exercicios/routes.php",
        )
    ],
)

# 4) Apostilas + Apostilas IA
apost = rewrite_routes(
    "backend/config/routes/admin.php",
    "/admin/apostilas",
    [
        ("Apostilas/AdminApostilaController@", "Modulos/apostilas/AdminApostilaController@"),
        ("Admin/ApostilaIaAdminController@", "Modulos/apostilas/ApostilaIaAdminController@"),
    ],
)
# teacher/student apostilas
for rf, replacements in [
    (
        "backend/config/routes/teacher.php",
        [
            ("Apostilas/TeacherApostilaController@", "Modulos/apostilas/TeacherApostilaController@"),
            ("Teacher/TeacherApostilaIaController@", "Modulos/apostilas/TeacherApostilaIaController@"),
        ],
    ),
    (
        "backend/config/routes/student.php",
        [
            ("Apostilas/StudentApostilaController@", "Modulos/apostilas/StudentApostilaController@"),
        ],
    ),
]:
    for line in (ROOT / rf).read_text().splitlines():
        if not line.strip().startswith("$router->"):
            continue
        if "apostila" not in line.lower():
            continue
        l2 = line
        for old, new in replacements:
            l2 = l2.replace(old, new)
        apost.append(l2)

ctrls = [
    "Controllers/Apostilas/AdminApostilaController.php",
    "Controllers/Apostilas/TeacherApostilaController.php",
    "Controllers/Apostilas/StudentApostilaController.php",
    "Controllers/Admin/ApostilaIaAdminController.php",
]
teacher_ia = ROOT / "backend/app/Controllers/Teacher/TeacherApostilaIaController.php"
if teacher_ia.exists():
    ctrls.append("Controllers/Teacher/TeacherApostilaIaController.php")

extrair(
    chave="apostilas",
    label="Apostilas",
    controllers=ctrls,
    routes_body="\n".join(apost),
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            ["/admin/apostilas"],
            "// Apostilas → Modulos/apostilas/routes.php",
        ),
        (
            "backend/config/routes/teacher.php",
            ["/professor/apostilas"],
            "// Apostilas → Modulos/apostilas/routes.php",
        ),
        (
            "backend/config/routes/student.php",
            ["/apostilas"],
            "// Apostilas → Modulos/apostilas/routes.php",
        ),
    ],
)

print("LOTE A DONE")
