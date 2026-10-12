#!/usr/bin/env python3
"""Corrige parse errors em routes quebradas por strip de closures."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def fix_admin() -> None:
    p = ROOT / "backend/config/routes/admin.php"
    text = p.read_text()
    broken = """// Gestão de Jornadas (antiga - redireciona para rotas novas)
    header('Location: ' . URL . '/admin/jornadas');
    exit;
});
    header('Location: ' . URL . '/admin/jornadas/criar');
    exit;
});
// Compatibilidade: redireciona URLs antigas em inglês para as novas em português
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});"""
    fixed = """// Gestão de Jornadas (antiga - redireciona para rotas novas) → também em Modulos/jornadas/routes.php
$router->get('/admin/journeys', function() {
    header('Location: ' . URL . '/admin/jornadas');
    exit;
});
$router->get('/admin/journeys/create', function() {
    header('Location: ' . URL . '/admin/jornadas/criar');
    exit;
});
$router->get('/admin/journeys/{id}', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});
$router->get('/admin/journeys/{id}/edit', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});"""
    if broken in text:
        text = text.replace(broken, fixed)
        print("admin journeys closures restored")
    else:
        # try regex cleanup of orphan headers
        text2, n = re.subn(
            r"// Gestão de Jornadas \(antiga[^\n]*\n(?:\s*header\([^\n]+\n\s*exit;\n\}\);\n?)+",
            fixed + "\n",
            text,
            count=1,
        )
        if n:
            text = text2
            print("admin journeys closures restored via regex")
        else:
            print("WARNING: admin journeys block not found as expected")

    # orphan provas redirect leftovers
    text2, n = re.subn(
        r"// Redireciona /admin/provas/blocos[^\n]*\n\s*header\([^\n]+\n\s*exit;\n\}\);\n?",
        "// redirect /admin/provas/blocos → Modulos/provas/routes.php\n",
        text,
        count=1,
    )
    if n:
        text = text2
        print("admin provas orphan cleaned")

    p.write_text(text)


def fix_jornadas_routes() -> None:
    p = ROOT / "backend/app/Modulos/jornadas/routes.php"
    lines = p.read_text().splitlines(keepends=True)
    out = []
    i = 0
    while i < len(lines):
        line = lines[i]
        # broken one-liners: $router->get('/admin/journeys', function() {
        if "function()" in line or "function($id)" in line:
            # skip incomplete closure openers — full closures added below once
            i += 1
            continue
        out.append(line)
        i += 1
    body = "".join(out)
    closures = """
// Compat EN → PT
$router->get('/admin/journeys', function() {
    header('Location: ' . URL . '/admin/jornadas');
    exit;
});
$router->get('/admin/journeys/create', function() {
    header('Location: ' . URL . '/admin/jornadas/criar');
    exit;
});
$router->get('/admin/journeys/{id}', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});
$router->get('/admin/journeys/{id}/edit', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});
"""
    if "/admin/journeys'" not in body and '/admin/journeys"' not in body:
        body = body.rstrip() + "\n" + closures
    p.write_text(body)
    print("jornadas routes fixed")


def fix_provas_routes() -> None:
    p = ROOT / "backend/app/Modulos/provas/routes.php"
    text = p.read_text()
    # Fix broken: $router->get('/admin/provas/blocos', function() {
    # followed by real routes — replace opener with proper redirect closure
    text2 = re.sub(
        r"\$router->get\('/admin/provas/blocos', function\(\) \{\n",
        (
            "$router->get('/admin/provas/blocos', function() {\n"
            "    header('Location: ' . URL . '/admin/provas');\n"
            "    exit;\n"
            "});\n"
            # NOTE: real blocos CRUD is under same prefix — in HEAD the redirect
            # may conflict. Keep redirect only if ExamBlock list moved elsewhere.
            # Actually HEAD had redirect AND criar routes — router matches exact?
            # Looking at broken file, criar is separate path so redirect only exact /blocos.
        ),
        text,
        count=1,
    )
    # Also rewrite Teacher/TeacherExamController leftovers
    text2 = text2.replace(
        "Teacher/TeacherExamController@",
        "Modulos/provas/TeacherExamController@",
    )
    p.write_text(text2)
    print("provas routes fixed")


if __name__ == "__main__":
    fix_admin()
    fix_jornadas_routes()
    fix_provas_routes()
