#!/usr/bin/env python3
"""Extrai redações, jornadas e provas."""
import sys
import re
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from extrair_modulo import extrair, ROOT, _route_matches_prefix


def collect_routes(files_needles_repls):
    """files_needles_repls: list of (file, prefixes, replacements)"""
    out = []
    for file_rel, prefixes, replacements in files_needles_repls:
        for line in (ROOT / file_rel).read_text().splitlines():
            if not line.strip().startswith("$router->"):
                continue
            if not _route_matches_prefix(line, prefixes):
                continue
            l2 = line
            for old, new in replacements:
                l2 = l2.replace(old, new)
            out.append(l2)
    return out


def controllers_existing(cands):
    return [c for c in cands if (ROOT / "backend/app" / c).exists() and (ROOT / "backend/app" / c).stat().st_size > 400]


# ── Redações ──────────────────────────────────────────────
red_routes = collect_routes(
    [
        (
            "backend/config/routes/admin.php",
            ["/admin/redacao", "/admin/redacoes", "/admin/essays"],
            [
                ("Essays/AdminEssayController@", "Modulos/redacoes/AdminEssayController@"),
                ("Essays/AdminEssayAnalyticsController@", "Modulos/redacoes/AdminEssayAnalyticsController@"),
                ("Admin/AdminEssayController@", "Modulos/redacoes/AdminEssayController@"),
            ],
        ),
        (
            "backend/config/routes/teacher.php",
            ["/professor/redacao", "/professor/redacoes", "/professor/essays"],
            [
                ("Essays/TeacherEssayController@", "Modulos/redacoes/TeacherEssayController@"),
                ("Essays/TeacherRedacaoLivreController@", "Modulos/redacoes/TeacherRedacaoLivreController@"),
            ],
        ),
        (
            "backend/config/routes/student.php",
            ["/redacao", "/redacoes", "/essays"],
            [
                ("Essays/StudentEssayController@", "Modulos/redacoes/StudentEssayController@"),
            ],
        ),
    ]
)
# also catch redacao-configuravel / redacao-professor patterns
for f, prefs in [
    ("backend/config/routes/admin.php", ["/admin/redacao-configuravel", "/admin/redacao-professor"]),
    ("backend/config/routes/teacher.php", ["/professor/redacao-livre", "/professor/redacao-configuravel"]),
]:
    for line in (ROOT / f).read_text().splitlines():
        if line.strip().startswith("$router->") and _route_matches_prefix(line, prefs):
            l2 = line
            for old, new in [
                ("Essays/AdminEssayController@", "Modulos/redacoes/AdminEssayController@"),
                ("Essays/TeacherEssayController@", "Modulos/redacoes/TeacherEssayController@"),
                ("Essays/TeacherRedacaoLivreController@", "Modulos/redacoes/TeacherRedacaoLivreController@"),
                ("Essays/StudentEssayController@", "Modulos/redacoes/StudentEssayController@"),
                ("Essays/AdminEssayAnalyticsController@", "Modulos/redacoes/AdminEssayAnalyticsController@"),
            ]:
                l2 = l2.replace(old, new)
            if l2 not in red_routes:
                red_routes.append(l2)

red_ctrl = controllers_existing(
    [
        "Controllers/Essays/AdminEssayController.php",
        "Controllers/Essays/AdminEssayAnalyticsController.php",
        "Controllers/Essays/TeacherEssayController.php",
        "Controllers/Essays/TeacherRedacaoLivreController.php",
        "Controllers/Essays/StudentEssayController.php",
    ]
)
print("redacoes routes", len(red_routes), "controllers", red_ctrl)

extrair(
    chave="redacoes",
    label="Redações",
    controllers=red_ctrl,
    routes_body="\n".join(red_routes),
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            ["/admin/redacao", "/admin/redacoes", "/admin/essays", "/admin/redacao-configuravel", "/admin/redacao-professor"],
            "// Redações → Modulos/redacoes/routes.php",
        ),
        (
            "backend/config/routes/teacher.php",
            ["/professor/redacao", "/professor/redacoes", "/professor/essays", "/professor/redacao-livre"],
            "// Redações → Modulos/redacoes/routes.php",
        ),
        (
            "backend/config/routes/student.php",
            ["/redacao", "/redacoes", "/essays"],
            "// Redações → Modulos/redacoes/routes.php",
        ),
    ],
)

# ── Jornadas ──────────────────────────────────────────────
jor_routes = collect_routes(
    [
        (
            "backend/config/routes/admin.php",
            ["/admin/jornadas", "/admin/journeys"],
            [
                ("Education/AdminJourneyController@", "Modulos/jornadas/AdminJourneyController@"),
                ("Education/JourneyController@", "Modulos/jornadas/JourneyController@"),
                ("Admin/AdminJourneyController@", "Modulos/jornadas/AdminJourneyController@"),
            ],
        ),
        (
            "backend/config/routes/teacher.php",
            ["/professor/jornadas", "/professor/journeys"],
            [
                ("Education/JourneyController@", "Modulos/jornadas/JourneyController@"),
                ("Teacher/TeacherJourneyController@", "Modulos/jornadas/TeacherJourneyController@"),
            ],
        ),
        (
            "backend/config/routes/student.php",
            ["/jornadas", "/journeys"],
            [
                ("Education/JourneyController@", "Modulos/jornadas/JourneyController@"),
                ("Student/StudentJourneyController@", "Modulos/jornadas/StudentJourneyController@"),
            ],
        ),
    ]
)
jor_ctrl = controllers_existing(
    [
        "Controllers/Education/AdminJourneyController.php",
        "Controllers/Education/JourneyController.php",
        "Controllers/Teacher/TeacherJourneyController.php",
        "Controllers/Student/StudentJourneyController.php",
    ]
)
print("jornadas routes", len(jor_routes), "controllers", jor_ctrl)
extrair(
    chave="jornadas",
    label="Jornadas",
    controllers=jor_ctrl,
    routes_body="\n".join(jor_routes),
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            ["/admin/jornadas", "/admin/journeys"],
            "// Jornadas → Modulos/jornadas/routes.php",
        ),
        (
            "backend/config/routes/teacher.php",
            ["/professor/jornadas", "/professor/journeys"],
            "// Jornadas → Modulos/jornadas/routes.php",
        ),
        (
            "backend/config/routes/student.php",
            ["/jornadas", "/journeys"],
            "// Jornadas → Modulos/jornadas/routes.php",
        ),
    ],
)

# ── Provas ────────────────────────────────────────────────
prov_routes = collect_routes(
    [
        (
            "backend/config/routes/admin.php",
            ["/admin/provas", "/admin/exams", "/admin/blocos", "/admin/exam"],
            [
                ("Exams/ExamController@", "Modulos/provas/ExamController@"),
                ("Exams/ExamBlockController@", "Modulos/provas/ExamBlockController@"),
                ("Exams/ExamBlockModelController@", "Modulos/provas/ExamBlockModelController@"),
                ("Exams/ExamEvaluationTypeController@", "Modulos/provas/ExamEvaluationTypeController@"),
                ("Exams/MockExamController@", "Modulos/provas/MockExamController@"),
                ("Exams/ServeExamQuestionImageController@", "Modulos/provas/ServeExamQuestionImageController@"),
                ("Admin/ProvasAlunoAssistenteController@", "Modulos/provas/ProvasAlunoAssistenteController@"),
                ("Admin/ProvasAlunoMcpController@", "Modulos/provas/ProvasAlunoMcpController@"),
                ("Admin/ProvasProfessorMcpController@", "Modulos/provas/ProvasProfessorMcpController@"),
            ],
        ),
        (
            "backend/config/routes/teacher.php",
            ["/professor/provas", "/professor/exams", "/professor/blocos"],
            [
                ("Exams/ExamController@", "Modulos/provas/ExamController@"),
                ("Teacher/TeacherExamController@", "Modulos/provas/TeacherExamController@"),
            ],
        ),
        (
            "backend/config/routes/student.php",
            ["/provas", "/exams"],
            [
                ("Exams/ExamController@", "Modulos/provas/ExamController@"),
                ("Student/StudentExamController@", "Modulos/provas/StudentExamController@"),
            ],
        ),
    ]
)
# widen admin provas discovery: any line with provas/ that maps to Exams/ or Provas
for line in (ROOT / "backend/config/routes/admin.php").read_text().splitlines():
    if not line.strip().startswith("$router->"):
        continue
    if not any(x in line for x in ["/admin/provas", "/admin/blocos-prova", "/admin/exam-blocks", "ProvasAluno", "ProvasProfessor", "MockExam", "ExamBlock", "ExamEvaluation"]):
        continue
    l2 = line
    for old, new in [
        ("Exams/ExamController@", "Modulos/provas/ExamController@"),
        ("Exams/ExamBlockController@", "Modulos/provas/ExamBlockController@"),
        ("Exams/ExamBlockModelController@", "Modulos/provas/ExamBlockModelController@"),
        ("Exams/ExamEvaluationTypeController@", "Modulos/provas/ExamEvaluationTypeController@"),
        ("Exams/MockExamController@", "Modulos/provas/MockExamController@"),
        ("Exams/ServeExamQuestionImageController@", "Modulos/provas/ServeExamQuestionImageController@"),
        ("Admin/ProvasAlunoAssistenteController@", "Modulos/provas/ProvasAlunoAssistenteController@"),
        ("Admin/ProvasAlunoMcpController@", "Modulos/provas/ProvasAlunoMcpController@"),
        ("Admin/ProvasProfessorMcpController@", "Modulos/provas/ProvasProfessorMcpController@"),
    ]:
        l2 = l2.replace(old, new)
    if "Modulos/provas/" in l2 and l2 not in prov_routes:
        prov_routes.append(l2)

prov_ctrl = controllers_existing(
    [
        "Controllers/Exams/ExamController.php",
        "Controllers/Exams/ExamBlockController.php",
        "Controllers/Exams/ExamBlockModelController.php",
        "Controllers/Exams/ExamEvaluationTypeController.php",
        "Controllers/Exams/MockExamController.php",
        "Controllers/Exams/ServeExamQuestionImageController.php",
        "Controllers/Admin/ProvasAlunoAssistenteController.php",
        "Controllers/Admin/ProvasAlunoMcpController.php",
        "Controllers/Admin/ProvasProfessorMcpController.php",
    ]
)
print("provas routes", len(prov_routes), "controllers", len(prov_ctrl), prov_ctrl)

# build strip prefixes from collected route paths
def prefixes_from(routes, startswith):
    prefs = set()
    for r in routes:
        m = re.search(r"'([^']+)'", r)
        if not m:
            continue
        path = m.group(1)
        if not path.startswith(startswith):
            continue
        # keep first 2-3 segments
        parts = path.strip("/").split("/")
        if len(parts) >= 2:
            prefs.add("/" + "/".join(parts[:2]))
        else:
            prefs.add("/" + parts[0])
    return sorted(prefs)

extrair(
    chave="provas",
    label="Provas",
    controllers=prov_ctrl,
    routes_body="\n".join(prov_routes),
    strip_specs=[
        (
            "backend/config/routes/admin.php",
            prefixes_from(prov_routes, "/admin")
            or ["/admin/provas", "/admin/blocos"],
            "// Provas → Modulos/provas/routes.php",
        ),
        (
            "backend/config/routes/teacher.php",
            prefixes_from(prov_routes, "/professor") or ["/professor/provas"],
            "// Provas → Modulos/provas/routes.php",
        ),
        (
            "backend/config/routes/student.php",
            prefixes_from(prov_routes, "/provas") + prefixes_from(prov_routes, "/exams")
            or ["/provas"],
            "// Provas → Modulos/provas/routes.php",
        ),
    ],
)

print("LOTE C DONE")
