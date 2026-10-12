#!/usr/bin/env python3
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from extrair_modulo import move_controller, ROOT

# Move TeacherExamController into provas if still large
src = ROOT / "backend/app/Controllers/Teacher/TeacherExamController.php"
if src.exists() and src.stat().st_size > 400:
    move_controller("Controllers/Teacher/TeacherExamController.php", "provas", "Provas")

REPLACEMENTS = [
    ("Essays/TeacherEssayController@", "Modulos/redacoes/TeacherEssayController@"),
    ("Essays/StudentEssayController@", "Modulos/redacoes/StudentEssayController@"),
    ("Essays/TeacherRedacaoLivreController@", "Modulos/redacoes/TeacherRedacaoLivreController@"),
    ("Essays/AdminEssayController@", "Modulos/redacoes/AdminEssayController@"),
    ("Exams/ExamController@", "Modulos/provas/ExamController@"),
    ("Exams/MockExamController@", "Modulos/provas/MockExamController@"),
    ("Teacher/TeacherExamController@", "Modulos/provas/TeacherExamController@"),
    ("Education/JourneyController@", "Modulos/jornadas/JourneyController@"),
    ("Teacher/TeacherJourneyController@", "Modulos/jornadas/TeacherJourneyController@"),
]

# path prefixes that belong to modules (safe matching)
BELONGS = {
    "redacoes": [
        "/professor/redacao-configuravel",
        "/redacoes-configuraveis",
        "/jornada-redacao",
        "/professor/redacao-livre",
    ],
    "provas": [
        "/professor/provas-bimestral",
        "/professor/provas-professor",
        "/admin/provas-professor",
        "/aluno/provas",
        "/simulados",
    ],
    "jornadas": [
        # only leftover journey lines if any with old handlers
    ],
}


def path_of(line: str) -> str | None:
    m = re.search(r"\$router->\w+\(\s*'([^']+)'", line)
    return m.group(1) if m else None


def belongs(path: str, prefs: list[str]) -> bool:
    for p in prefs:
        if path == p or path.startswith(p + "/") or path.startswith(p):
            # avoid /admin/students...provas
            if p == "/aluno/provas" and not path.startswith("/aluno/provas"):
                continue
            return True
    return False


for route_file in [
    "backend/config/routes/teacher.php",
    "backend/config/routes/student.php",
    "backend/config/routes/admin.php",
]:
    p = ROOT / route_file
    lines = p.read_text().splitlines(keepends=True)
    out = []
    bucket = {"redacoes": [], "provas": [], "jornadas": []}
    for line in lines:
        if not line.strip().startswith("$router->"):
            out.append(line)
            continue
        path = path_of(line)
        if not path:
            out.append(line)
            continue
        target = None
        for chave, prefs in BELONGS.items():
            if belongs(path, prefs):
                target = chave
                break
        if not target:
            # still rewrite if handler is Essays/Exams leftover anywhere matching path keywords
            out.append(line)
            continue
        l2 = line
        for old, new in REPLACEMENTS:
            l2 = l2.replace(old, new)
        if "Modulos/" not in l2:
            # leave if still points to User/StudentController etc
            if "User/StudentController@" in l2 or "User/TeacherController@" in l2:
                out.append(line)
                continue
            out.append(line)
            continue
        bucket[target].append(l2.rstrip("\n"))
    p.write_text("".join(out))
    for chave, routes in bucket.items():
        if not routes:
            continue
        rp = ROOT / f"backend/app/Modulos/{chave}/routes.php"
        existing = rp.read_text()
        add = [r for r in routes if r not in existing]
        if add:
            rp.write_text(existing.rstrip() + "\n" + "\n".join(add) + "\n")
        print(f"{route_file}: {len(routes)} -> {chave} (+{len(add)} new)")

# AdminBase uses
for mod, ns in [
    ("redacoes", "Redacoes"),
    ("jornadas", "Jornadas"),
    ("provas", "Provas"),
]:
    d = ROOT / "backend/app/Modulos" / mod / "Controllers"
    for f in d.glob("*.php"):
        t = f.read_text()
        if "extends AdminBaseController" in t and "use App\\Controllers\\Admin\\AdminBaseController" not in t:
            t = re.sub(
                r"(namespace App\\Modulos\\\w+\\Controllers;)",
                r"\1\n\nuse App\\Controllers\\Admin\\AdminBaseController;",
                t,
                count=1,
            )
            f.write_text(t)
            print("use AdminBase", f.name)
