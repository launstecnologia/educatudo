#!/usr/bin/env python3
"""Corrige routes do AVA (remove falsos positivos avatares/avaliacoes) e restaura no admin.php."""
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
AVA_ROUTES = ROOT / "backend/app/Modulos/ava/routes.php"
ADMIN = ROOT / "backend/config/routes/admin.php"


def main() -> None:
    lines = AVA_ROUTES.read_text().splitlines(keepends=True)
    keep = []
    for line in lines:
        if not line.strip().startswith("$router->"):
            keep.append(line)
            continue
        # só rotas AVA reais
        if re.search(r"'/admin/ava(?:/|')", line) or "Modulos/ava/" in line:
            if "/admin/avatares" in line or "/admin/avaliacoes" in line:
                continue
            keep.append(line)
            continue
        # teacher/student/public already rewritten with Modulos/ava
        if "Modulos/ava/" in line:
            keep.append(line)
    AVA_ROUTES.write_text("".join(keep))
    print("ava routes cleaned, count router:", sum(1 for l in keep if l.strip().startswith("$router->")))

    # Restaurar avatares + avaliacoes do HEAD se sumiram do admin
    admin = ADMIN.read_text()
    head = subprocess.check_output(
        ["git", "show", "HEAD:backend/config/routes/admin.php"], text=True
    )
    need_restore = []
    for line in head.splitlines():
        if not line.strip().startswith("$router->"):
            continue
        if "/admin/avatares-alunos" in line or line.strip().startswith(
            "$router->get('/admin/avaliacoes'"
        ):
            need_restore.append(line)

    missing = [l for l in need_restore if l not in admin]
    if missing:
        # inserir antes do comentário AVA ou no fim das rotas admin iniciais
        marker = "// AVA → Modulos/ava/routes.php"
        block = (
            "// Avatares dos alunos\n"
            + "\n".join(l for l in missing if "avatares" in l)
            + "\n"
            + "// Hub avaliações\n"
            + "\n".join(l for l in missing if "avaliacoes" in l)
            + "\n"
        )
        if marker in admin:
            admin = admin.replace(marker, block + marker, 1)
        else:
            admin = block + admin
        ADMIN.write_text(admin)
        print("restored", len(missing), "routes to admin.php")
    else:
        print("admin already has avatares/avaliacoes or nothing to restore")


if __name__ == "__main__":
    main()
