#!/usr/bin/env python3
"""
Cria app/Dominio/{Pessoas,Escola} e app/Painel/{Admin,...} com moves + shims.
Views permanecem em app/Views (resolver legado); Controllers de domínio vão ao Painel/Dominio.
"""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def slug_ns(parts: str) -> str:
    return parts


def move_php(
    rel_src: str,
    rel_dst: str,
    new_namespace: str,
    alias_fqcn: str | None = None,
) -> None:
    src = ROOT / "backend/app" / rel_src
    dst = ROOT / "backend/app" / rel_dst
    if not src.exists():
        print("skip missing", rel_src)
        return
    if src.stat().st_size < 400 and "Shim" in src.read_text()[:120]:
        print("skip shim", rel_src)
        return
    dst.parent.mkdir(parents=True, exist_ok=True)
    content = src.read_text()
    if re.search(r"namespace\s+[\w\\]+;", content):
        content2 = re.sub(
            r"namespace\s+[\w\\]+;",
            lambda _m: "namespace " + new_namespace + ";",
            content,
            count=1,
        )
    else:
        content2 = re.sub(
            r"^<\?php\s*",
            lambda _m: "<?php\n\nnamespace " + new_namespace + ";\n\n",
            content,
            count=1,
        )
    dst.write_text(content2)

    short = Path(rel_src).stem
    old_fqcn = alias_fqcn or ("App\\" + rel_src.replace("Controllers/", "Controllers\\").replace("Models/", "Models\\").replace("/", "\\").replace(".php", ""))
    # Better: derive from original namespace if present
    m = re.search(r"namespace\s+([\w\\]+);", content)
    if m and alias_fqcn is None:
        old_fqcn = m.group(1) + "\\" + short

    # depth from src dir to app/
    depth = len(Path(rel_src).parts) - 1
    rel_to_dst = Path(rel_dst).as_posix()
    shim = (
        "<?php\n"
        f"/** Shim → {rel_to_dst} */\n"
        f"require_once dirname(__DIR__, {depth}) . '/{rel_to_dst}';\n"
        f"if (!class_exists('{old_fqcn}', false)) {{\n"
        f"    class_alias(\\{new_namespace}\\{short}::class, '{old_fqcn}');\n"
        "}\n"
        # also global short name if used
        f"if (!class_exists('{short}', false)) {{\n"
        f"    class_alias(\\{new_namespace}\\{short}::class, '{short}');\n"
        "}\n"
    )
    src.write_text(shim)
    print(f"Dominio/Painel: {rel_src} -> {rel_dst}")


def patch_composer() -> None:
    cp = ROOT / "backend/composer.json"
    data = json.loads(cp.read_text())
    psr4 = data.setdefault("autoload", {}).setdefault("psr-4", {})
    psr4["App\\Dominio\\Pessoas\\"] = "app/Dominio/Pessoas/"
    psr4["App\\Dominio\\Escola\\"] = "app/Dominio/Escola/"
    psr4["App\\Painel\\"] = "app/Painel/"
    cp.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")
    print("composer Dominio/Painel OK")


def patch_view_resolver() -> None:
    p = ROOT / "backend/app/Core/BaseController.php"
    t = p.read_text()
    needle = "        $legacy = __DIR__ . '/../Views/' . $view . '.php';\n        if (is_file($legacy)) {\n            return $legacy;\n        }"
    insert = """        $legacy = __DIR__ . '/../Views/' . $view . '.php';
        if (is_file($legacy)) {
            return $legacy;
        }

        // Painel/<perfil>/Views/... (ex.: admin/students/index → Painel/Admin/Views/students/index.php)
        if (preg_match('#^(aluno|professor|student|teacher|admin|master|parents|monitor)/(.+)$#', $view, $pm)) {
            $perfil = $pm[1];
            $map = [
                'student' => 'Aluno',
                'aluno' => 'Aluno',
                'teacher' => 'Professor',
                'professor' => 'Professor',
                'admin' => 'Admin',
                'master' => 'Master',
                'parents' => 'Pais',
                'monitor' => 'Monitor',
            ];
            $pasta = $map[$perfil] ?? null;
            if ($pasta !== null) {
                $painelView = __DIR__ . '/../Painel/' . $pasta . '/Views/' . $pm[2] . '.php';
                if (is_file($painelView)) {
                    return $painelView;
                }
            }
        }"""
    if "Painel/" in t and "painelView" in t:
        print("view resolver already patched")
        return
    if needle not in t:
        print("WARNING: view resolver needle not found")
        return
    p.write_text(t.replace(needle, insert, 1))
    print("view resolver patched for Painel")


def write_readmes() -> None:
    (ROOT / "backend/app/Dominio/Pessoas").mkdir(parents=True, exist_ok=True)
    (ROOT / "backend/app/Dominio/Escola").mkdir(parents=True, exist_ok=True)
    for perfil in ["Admin", "Professor", "Aluno", "Pais", "Master", "Monitor"]:
        (ROOT / "backend/app/Painel" / perfil / "Controllers").mkdir(parents=True, exist_ok=True)
        (ROOT / "backend/app/Painel" / perfil / "Views").mkdir(parents=True, exist_ok=True)
    (ROOT / "backend/app/Dominio/README.md").write_text(
        "# Dominio\n\n"
        "- `Pessoas/` — Aluno, Professor, Responsável, Usuário, Monitor\n"
        "- `Escola/` — estrutura escolar (ano letivo, curso, série, unidade, sala, componente, matriz, grade, BNCC…)\n"
        "\nShims nos caminhos antigos (`Models/User`, `Models/Education`) mantêm compatibilidade.\n"
    )
    (ROOT / "backend/app/Painel/README.md").write_text(
        "# Painel\n\nControllers (e futuramente Views) por perfil: Admin, Professor, Aluno, Pais, Master, Monitor.\n"
        "Módulos opcionais continuam em `app/Modulos/`.\n"
        "Layouts globais permanecem em `app/Views/layouts/`.\n"
    )


def main() -> None:
    write_readmes()
    patch_view_resolver()

    # Pessoas models
    for name, file in [
        ("Student", "Models/User/Student.php"),
        ("Teacher", "Models/User/Teacher.php"),
        ("ParentModel", "Models/User/Parent.php"),  # class may be ParentModel
        ("Monitor", "Models/User/Monitor.php"),
        ("StudentDocument", "Models/User/StudentDocument.php"),
        ("StudentComplementaryRecord", "Models/User/StudentComplementaryRecord.php"),
    ]:
        src = ROOT / "backend/app" / file
        if not src.exists():
            continue
        # detect real class name
        content = src.read_text()
        cm = re.search(r"class\s+(\w+)", content)
        short = cm.group(1) if cm else name
        dst = f"Dominio/Pessoas/Models/{short}.php"
        # old ns
        nm = re.search(r"namespace\s+([\w\\]+);", content)
        old_ns = nm.group(1) if nm else "App\\Models\\User"
        move_php(file, dst, "App\\Dominio\\Pessoas\\Models", alias_fqcn=f"{old_ns}\\{short}")

    # Escola models (estrutura)
    escola_models = [
        "Models/Education/SchoolUnit.php",
        "Models/Education/Sala.php",
        "Models/Education/ComponenteCurricular.php",
        "Models/Education/MatrizCurricular.php",
        "Models/Education/ClassRoom.php",
        "Models/Education/CoursePlan.php",
    ]
    for file in escola_models:
        src = ROOT / "backend/app" / file
        if not src.exists() or src.stat().st_size < 400:
            continue
        content = src.read_text()
        cm = re.search(r"class\s+(\w+)", content)
        short = cm.group(1) if cm else Path(file).stem
        nm = re.search(r"namespace\s+([\w\\]+);", content)
        old_ns = nm.group(1) if nm else "App\\Models\\Education"
        move_php(
            file,
            f"Dominio/Escola/Models/{short}.php",
            "App\\Dominio\\Escola\\Models",
            alias_fqcn=f"{old_ns}\\{short}",
        )

    # Painel Admin — controllers de domínio (cadastros sempre-on)
    painel_admin = [
        ("Controllers/Admin/StudentAdminController.php", "StudentAdminController"),
        ("Controllers/Admin/TeacherAdminController.php", "TeacherAdminController"),
        ("Controllers/Admin/MonitorAdminController.php", "MonitorAdminController"),
        ("Controllers/Admin/SchoolUnitsAdminController.php", "SchoolUnitsAdminController"),
        ("Controllers/Admin/SalaAdminController.php", "SalaAdminController"),
        ("Controllers/Admin/ComponenteCurricularAdminController.php", "ComponenteCurricularAdminController"),
        ("Controllers/Admin/MatrizCurricularAdminController.php", "MatrizCurricularAdminController"),
        ("Controllers/Admin/BnccController.php", "BnccController"),
        ("Controllers/Education/AnoLetivoController.php", "AnoLetivoController"),
        ("Controllers/Education/CursoController.php", "CursoController"),
        ("Controllers/Education/SerieController.php", "SerieController"),
        ("Controllers/Education/GradeHorariaController.php", "GradeHorariaController"),
        ("Controllers/Education/ClassController.php", "ClassController"),
    ]
    for file, short in painel_admin:
        src = ROOT / "backend/app" / file
        if not src.exists() or src.stat().st_size < 400:
            continue
        content = src.read_text()
        nm = re.search(r"namespace\s+([\w\\]+);", content)
        old_ns = nm.group(1) if nm else (
            "App\\Controllers\\Admin" if "/Admin/" in file else "App\\Controllers\\Education"
        )
        # detect class name
        cm = re.search(r"class\s+(\w+)", content)
        real = cm.group(1) if cm else short
        move_php(
            file,
            f"Painel/Admin/Controllers/{real}.php",
            "App\\Painel\\Admin\\Controllers",
            alias_fqcn=f"{old_ns}\\{real}",
        )

    patch_composer()
    print("DONE Dominio/Painel scaffold")


if __name__ == "__main__":
    main()
