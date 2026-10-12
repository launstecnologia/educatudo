#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def fix_parent_shim() -> None:
    p = ROOT / "backend/app/Models/User/Parent.php"
    p.write_text(
        """<?php
/** Shim → Dominio/Pessoas/Models/ParentModel.php */
require_once dirname(__DIR__, 2) . '/Dominio/Pessoas/Models/ParentModel.php';
if (!class_exists('App\\\\Models\\\\User\\\\ParentModel', false)) {
    class_alias(\\App\\Dominio\\Pessoas\\Models\\ParentModel::class, 'App\\\\Models\\\\User\\\\ParentModel');
}
if (!class_exists('ParentModel', false)) {
    class_alias(\\App\\Dominio\\Pessoas\\Models\\ParentModel::class, 'ParentModel');
}
""".replace("App\\\\Models\\\\User\\\\ParentModel", "App\\Models\\User\\ParentModel")
    )
    # write cleanly
    p.write_text(
        "<?php\n"
        "/** Shim → Dominio/Pessoas/Models/ParentModel.php */\n"
        "require_once dirname(__DIR__, 2) . '/Dominio/Pessoas/Models/ParentModel.php';\n"
        "if (!class_exists('App\\\\Models\\\\User\\\\ParentModel', false)) {\n"
        "    class_alias(\\App\\Dominio\\Pessoas\\Models\\ParentModel::class, 'App\\\\Models\\\\User\\\\ParentModel');\n"
        "}\n"
        "if (!class_exists('ParentModel', false)) {\n"
        "    class_alias(\\App\\Dominio\\Pessoas\\Models\\ParentModel::class, 'ParentModel');\n"
        "}\n"
    )
    # The above still has wrong escaping in class_exists string. Final:
    p.write_text(
        "<?php\n"
        "/** Shim → Dominio/Pessoas/Models/ParentModel.php */\n"
        "require_once dirname(__DIR__, 2) . '/Dominio/Pessoas/Models/ParentModel.php';\n"
        "if (!class_exists(\\App\\Models\\User\\ParentModel::class, false)) {\n"
        "    class_alias(\\App\\Dominio\\Pessoas\\Models\\ParentModel::class, \\App\\Models\\User\\ParentModel::class);\n"
        "}\n"
        "if (!class_exists('ParentModel', false)) {\n"
        "    class_alias(\\App\\Dominio\\Pessoas\\Models\\ParentModel::class, 'ParentModel');\n"
        "}\n"
    )
    print("Parent shim OK")


def fix_painel_requires() -> None:
    root = ROOT / "backend/app/Painel/Admin/Controllers"
    for p in root.glob("*.php"):
        t = p.read_text()
        orig = t
        for a, b in [
            ("__DIR__ . '/../../Models/", "__DIR__ . '/../../../Models/"),
            ('__DIR__ . "/../../Models/', '__DIR__ . "/../../../Models/'),
            ("__DIR__ . '/../../Services/", "__DIR__ . '/../../../Services/"),
            ("__DIR__ . '/../../Core/", "__DIR__ . '/../../../Core/"),
            ("__DIR__ . '/../../Helpers/", "__DIR__ . '/../../../Helpers/"),
            ("dirname(__DIR__, 2) . '/Models/", "dirname(__DIR__, 3) . '/Models/"),
            ("dirname(__DIR__, 2) . '/Core/", "dirname(__DIR__, 3) . '/Core/"),
            ("dirname(__DIR__, 2) . '/Services/", "dirname(__DIR__, 3) . '/Services/"),
            ("dirname(__DIR__) . '/Models/", "dirname(__DIR__, 3) . '/Models/"),
            ("dirname(__DIR__) . '/../Models/", "dirname(__DIR__, 3) . '/Models/"),
            ("dirname(__DIR__) . '/../Core/", "dirname(__DIR__, 3) . '/Core/"),
            ("dirname(__DIR__) . '/../Services/", "dirname(__DIR__, 3) . '/Services/"),
        ]:
            t = t.replace(a, b)
        if t != orig:
            p.write_text(t)
            print("paths", p.name)


def fix_model_shims() -> None:
    pairs = [
        (
            ROOT / "backend/app/Models/User",
            ROOT / "backend/app/Dominio/Pessoas/Models",
            "Pessoas",
            "User",
        ),
        (
            ROOT / "backend/app/Models/Education",
            ROOT / "backend/app/Dominio/Escola/Models",
            "Escola",
            "Education",
        ),
    ]
    for shim_dir, dest_dir, dominio, old_folder in pairs:
        for dest in dest_dir.glob("*.php"):
            cm = re.search(r"class\s+(\w+)", dest.read_text())
            if not cm:
                continue
            real = cm.group(1)
            # find shim file: usually same name or Parent.php for ParentModel
            candidates = [shim_dir / f"{real}.php", shim_dir / f"{dest.stem}.php"]
            if real == "ParentModel":
                candidates.insert(0, shim_dir / "Parent.php")
            shim = next((c for c in candidates if c.exists()), None)
            if shim is None:
                print("no shim for", dest)
                continue
            rel = dest.relative_to(ROOT / "backend/app").as_posix()
            depth = len(shim.relative_to(ROOT / "backend/app").parts) - 1
            old_fqcn = f"App\\Models\\{old_folder}\\{real}"
            shim.write_text(
                "<?php\n"
                f"/** Shim → {rel} */\n"
                f"require_once dirname(__DIR__, {depth}) . '/{rel}';\n"
                f"if (!class_exists(\\{old_fqcn}::class, false)) {{\n"
                f"    class_alias(\\App\\Dominio\\{dominio}\\Models\\{real}::class, \\{old_fqcn}::class);\n"
                "}\n"
                f"if (!class_exists('{real}', false)) {{\n"
                f"    class_alias(\\App\\Dominio\\{dominio}\\Models\\{real}::class, '{real}');\n"
                "}\n"
            )
            print("shim", shim.name, "->", real)


if __name__ == "__main__":
    fix_parent_shim()
    fix_painel_requires()
    fix_model_shims()
