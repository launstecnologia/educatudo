#!/usr/bin/env python3
"""Extrai AVA (admin + teacher + student se houver)."""
import sys
import re
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from extrair_modulo import extrair, ROOT


def collect(file_rel: str, needle: str, replacements: list[tuple[str, str]]) -> list[str]:
    out = []
    for line in (ROOT / file_rel).read_text().splitlines():
        if not line.strip().startswith("$router->"):
            continue
        if needle not in line:
            continue
        l2 = line
        for old, new in replacements:
            l2 = l2.replace(old, new)
        out.append(l2)
    return out


# Discover controllers referenced as Admin/Ava* or Teacher/Ava* or Student Ava
admin_ava = collect(
    "backend/config/routes/admin.php",
    "/admin/ava",
    [
        ("Admin/AvaCourseAdminController@", "Modulos/ava/AvaCourseAdminController@"),
        ("Admin/AvaDisciplineAdminController@", "Modulos/ava/AvaDisciplineAdminController@"),
        ("Admin/AvaContentAdminController@", "Modulos/ava/AvaContentAdminController@"),
    ],
)
teacher_ava = []
for line in (ROOT / "backend/config/routes/teacher.php").read_text().splitlines():
    if line.strip().startswith("$router->") and ("/ava" in line or "Ava" in line):
        l2 = line
        for old, new in [
            ("Teacher/AvaTeacherController@", "Modulos/ava/AvaTeacherController@"),
            ("Teacher/AvaLiveTeacherController@", "Modulos/ava/AvaLiveTeacherController@"),
            ("Ava/AvaCommentController@", "Modulos/ava/AvaCommentController@"),
        ]:
            l2 = l2.replace(old, new)
        if "Modulos/ava/" in l2:
            teacher_ava.append(l2)

student_ava = []
for line in (ROOT / "backend/config/routes/student.php").read_text().splitlines():
    if line.strip().startswith("$router->") and ("/ava" in line or "Ava" in line or "certificate" in line.lower()):
        l2 = line
        for old, new in [
            ("Student/AvaActivityStudentController@", "Modulos/ava/AvaActivityStudentController@"),
            ("Ava/AvaCommentController@", "Modulos/ava/AvaCommentController@"),
            ("Ava/CertificateValidationController@", "Modulos/ava/CertificateValidationController@"),
        ]:
            l2 = l2.replace(old, new)
        if "Modulos/ava/" in l2:
            student_ava.append(l2)

# public certificate?
public_ava = []
for line in (ROOT / "backend/config/routes/public.php").read_text().splitlines():
    if line.strip().startswith("$router->") and ("Certificate" in line or "/certificado" in line or "/ava" in line):
        l2 = line.replace(
            "Ava/CertificateValidationController@",
            "Modulos/ava/CertificateValidationController@",
        )
        if "Modulos/ava/" in l2:
            public_ava.append(l2)

all_routes = admin_ava + teacher_ava + student_ava + public_ava
print("AVA routes:", len(all_routes))

# Controllers present
cands = [
    "Controllers/Admin/AvaCourseAdminController.php",
    "Controllers/Admin/AvaDisciplineAdminController.php",
    "Controllers/Admin/AvaContentAdminController.php",
    "Controllers/Teacher/AvaTeacherController.php",
    "Controllers/Teacher/AvaLiveTeacherController.php",
    "Controllers/Student/AvaActivityStudentController.php",
    "Controllers/Ava/AvaCommentController.php",
    "Controllers/Ava/CertificateValidationController.php",
]
controllers = [c for c in cands if (ROOT / "backend/app" / c).exists()]
print("AVA controllers:", controllers)

strip = [
    (
        "backend/config/routes/admin.php",
        ["/admin/ava"],
        "// AVA → Modulos/ava/routes.php",
    ),
]
# teacher: strip lines that we moved - use common prefixes
t_prefs = sorted({re.search(r"'([^']+)'", r).group(1).split("{")[0] for r in teacher_ava if re.search(r"'([^']+)'", r)})
s_prefs = sorted({re.search(r"'([^']+)'", r).group(1).split("{")[0] for r in student_ava if re.search(r"'([^']+)'", r)})
if t_prefs:
    # use shorter unique prefixes
    strip.append(
        (
            "backend/config/routes/teacher.php",
            [p for p in t_prefs if p.startswith("/professor")],
            "// AVA → Modulos/ava/routes.php",
        )
    )
if s_prefs:
    strip.append(
        (
            "backend/config/routes/student.php",
            s_prefs,
            "// AVA → Modulos/ava/routes.php",
        )
    )
if public_ava:
    strip.append(
        (
            "backend/config/routes/public.php",
            ["/certificado", "/validar-certificado"],
            "// AVA certificados → Modulos/ava/routes.php",
        )
    )

extrair(
    chave="ava",
    label="AVA",
    controllers=controllers,
    routes_body="\n".join(all_routes),
    strip_specs=strip,
    feature_gate=True,
)
print("LOTE B AVA DONE")
