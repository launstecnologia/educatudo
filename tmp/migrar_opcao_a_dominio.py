#!/usr/bin/env python3
"""
Opção A: move Controllers/Services de Painel/Admin → Dominio/{Pessoas,Escola}.
Painel fica só shell. Shims nos caminhos antigos (sem duplicar código).
"""
from __future__ import annotations

import json
import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"

PESSOAS_CTRL = [
    "StudentAdminController.php",
    "TeacherAdminController.php",
    "MonitorAdminController.php",
]
ESCOLA_CTRL = [
    "AnoLetivoController.php",
    "BnccController.php",
    "ClassController.php",
    "ComponenteCurricularAdminController.php",
    "CursoController.php",
    "GradeHorariaController.php",
    "MatrizCurricularAdminController.php",
    "SalaAdminController.php",
    "SchoolUnitsAdminController.php",
    "SerieController.php",
]

PESSOAS_SVC = [
    "AdminStudentProfileService.php",
    "AlunoMovimentacaoService.php",
    "AlunoTurmaResolver.php",
]
ESCOLA_SVC = [
    "AnoLetivoService.php",
    "ComponenteCurricularService.php",
    "BnccService.php",
]


def rewrite_ns(content: str, new_ns: str) -> str:
    if re.search(r"namespace\s+[\w\\]+;", content):
        return re.sub(
            r"namespace\s+[\w\\]+;",
            lambda _m: "namespace " + new_ns + ";",
            content,
            count=1,
        )
    return re.sub(
        r"^<\?php\s*",
        lambda _m: "<?php\n\nnamespace " + new_ns + ";\n\n",
        content,
        count=1,
    )


def ensure_uses(content: str, uses: list[str]) -> str:
    for u in uses:
        if u in content:
            continue
        content = re.sub(
            r"(namespace\s+[\w\\]+;\s*)",
            r"\1\n" + u + "\n",
            content,
            count=1,
        )
    return content


def write_shim(path: Path, require_rel_from_app: str, new_fqcn: str, old_fqcns: list[str], depth: int) -> None:
    aliases = []
    for old in old_fqcns:
        aliases.append(
            f"if (!class_exists(\\{old}::class, false)) {{\n"
            f"    class_alias(\\{new_fqcn}::class, \\{old}::class);\n"
            f"}}\n"
        )
    short = new_fqcn.rsplit("\\", 1)[-1]
    aliases.append(
        f"if (!class_exists('{short}', false)) {{\n"
        f"    class_alias(\\{new_fqcn}::class, '{short}');\n"
        f"}}\n"
    )
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        "<?php\n"
        f"/** Shim → {require_rel_from_app} (sem duplicar código). */\n"
        f"require_once dirname(__DIR__, {depth}) . '/{require_rel_from_app}';\n"
        + "".join(aliases)
    )


def move_controller(filename: str, dominio: str) -> None:
    src_painel = APP / "Painel/Admin/Controllers" / filename
    if not src_painel.exists() or src_painel.stat().st_size < 400:
        # already moved?
        dst_check = APP / f"Dominio/{dominio}/Controllers" / filename
        if dst_check.exists():
            print("already", filename)
            return
        print("skip missing", filename)
        return

    short = filename.replace(".php", "")
    new_ns = f"App\\Dominio\\{dominio}\\Controllers"
    new_fqcn = f"{new_ns}\\{short}"
    rel_dst = f"Dominio/{dominio}/Controllers/{filename}"
    dst = APP / rel_dst
    dst.parent.mkdir(parents=True, exist_ok=True)

    content = src_painel.read_text()
    # capture old namespace for shim
    m = re.search(r"namespace\s+([\w\\]+);", content)
    old_ns = m.group(1) if m else "App\\Painel\\Admin\\Controllers"
    content = rewrite_ns(content, new_ns)

    # fix relative requires: from Dominio/X/Controllers → app is 3 levels
    content = content.replace("dirname(__DIR__, 3)", "dirname(__DIR__, 3)")  # already 3 from Painel
    content = content.replace("__DIR__ . '/../../../", "__DIR__ . '/../../../")

    if "extends AdminBaseController" in content and "use App\\Controllers\\Admin\\AdminBaseController" not in content:
        content = ensure_uses(content, ["use App\\Controllers\\Admin\\AdminBaseController;"])
    if "extends BaseController" in content and "use App\\Core\\BaseController" not in content:
        content = ensure_uses(
            content,
            [
                "use App\\Core\\AuthManager;",
                "use App\\Core\\BaseController;",
                "use App\\Core\\Database;",
            ],
        )

    # AnoLetivo service use
    if short == "AnoLetivoController" and "use App\\Services\\AnoLetivoService" in content:
        content = content.replace(
            "use App\\Services\\AnoLetivoService;",
            "use App\\Dominio\\Escola\\Services\\AnoLetivoService;",
        )

    dst.write_text(content)

    # Painel path becomes shim → Dominio
    write_shim(
        src_painel,
        rel_dst,
        new_fqcn,
        [f"{old_ns}\\{short}", f"App\\Painel\\Admin\\Controllers\\{short}"],
        depth=3,  # Painel/Admin/Controllers → app = 3? 
        # dirname(__DIR__, 1)=Admin, 2=Painel, 3=app → depth 3 from Controllers file's __DIR__
        # wait: file is at Painel/Admin/Controllers/X.php, __DIR__=.../Controllers
        # dirname(__DIR__, 1)=Admin, 2=Painel, 3=app. Yes depth=3
    )
    # Fix: write_shim uses dirname(__DIR__, depth) from the shim file location
    # For Controllers/Admin/X.php shim: __DIR__=Controllers/Admin, depth 2 → app
    # Update Controllers/Admin or Controllers/Education shims if they point to Painel

    # Update legacy Controllers shims to point to Dominio directly
    for legacy in [
        APP / "Controllers/Admin" / filename,
        APP / "Controllers/Education" / filename,
    ]:
        if not legacy.exists():
            continue
        # depth from Controllers/Admin = 2, Controllers/Education = 2
        write_shim(
            legacy,
            rel_dst,
            new_fqcn,
            [
                f"App\\Controllers\\Admin\\{short}",
                f"App\\Controllers\\Education\\{short}",
                f"App\\Painel\\Admin\\Controllers\\{short}",
            ],
            depth=2,
        )

    print(f"CTRL {filename} → Dominio/{dominio}")


def move_service(filename: str, dominio: str) -> None:
    src = APP / "Services" / filename
    if not src.exists():
        print("svc skip", filename)
        return
    if src.stat().st_size < 300 and "Shim" in src.read_text()[:80]:
        print("svc already shim", filename)
        return

    short = filename.replace(".php", "")
    new_ns = f"App\\Dominio\\{dominio}\\Services"
    new_fqcn = f"{new_ns}\\{short}"
    rel_dst = f"Dominio/{dominio}/Services/{filename}"
    dst = APP / rel_dst
    dst.parent.mkdir(parents=True, exist_ok=True)

    content = src.read_text()
    m = re.search(r"namespace\s+([\w\\]+);", content)
    old_ns = m.group(1) if m else "App\\Services"
    content = rewrite_ns(content, new_ns)
    dst.write_text(content)

    write_shim(
        src,
        rel_dst,
        new_fqcn,
        [f"{old_ns}\\{short}", f"App\\Services\\{short}"],
        depth=1,  # Services/X.php → __DIR__=Services, depth 1 = app
    )
    print(f"SVC {filename} → Dominio/{dominio}/Services")


def patch_composer() -> None:
    cp = ROOT / "backend/composer.json"
    data = json.loads(cp.read_text())
    psr4 = data.setdefault("autoload", {}).setdefault("psr-4", {})
    psr4["App\\Dominio\\Pessoas\\"] = "app/Dominio/Pessoas/"
    psr4["App\\Dominio\\Escola\\"] = "app/Dominio/Escola/"
    psr4["App\\Painel\\"] = "app/Painel/"
    cp.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")
    print("composer OK")


def write_docs() -> None:
    (APP / "Dominio/README.md").write_text(
        """# Domínio (sempre ligado)

Negócio essencial da escola — **não** é feature opcional.

```
Dominio/
  Pessoas/   Controllers, Models, Services  (aluno, professor, responsável, monitor…)
  Escola/    Controllers, Models, Services  (ano letivo, curso, série, turma, sala, BNCC…)
```

Regra: sem código duplicado. Caminhos antigos (`Controllers/Admin`, `Services/`, `Models/User`)
são só **shims** (`require` + `class_alias`).
"""
    )
    (APP / "Painel/README.md").write_text(
        """# Painel (shell por perfil)

Casca de cada tipo de acesso — layout/dashboard/home do perfil.
**Não** colocar CRUD de domínio nem features de módulo aqui.

```
Painel/
  Admin/ Professor/ Aluno/ Pais/ Master/ Monitor/
    Controllers/   # só shell (dashboard, hubs de menu)
    Views/         # opcional; layouts globais ficam em Views/layouts/
```

- Negócio essencial → `Dominio/`
- Feature opcional → `Modulos/`
- Layout HTML chrome → `Views/layouts/`
"""
    )


def main() -> None:
    write_docs()
    for f in PESSOAS_CTRL:
        move_controller(f, "Pessoas")
    for f in ESCOLA_CTRL:
        move_controller(f, "Escola")
    for f in PESSOAS_SVC:
        move_service(f, "Pessoas")
    for f in ESCOLA_SVC:
        move_service(f, "Escola")
    patch_composer()

    # Fix AnoLetivoController use after service move (file already written)
    ano = APP / "Dominio/Escola/Controllers/AnoLetivoController.php"
    if ano.exists():
        t = ano.read_text()
        t2 = t.replace(
            "use App\\Services\\AnoLetivoService;",
            "use App\\Dominio\\Escola\\Services\\AnoLetivoService;",
        )
        t2 = t2.replace(
            "use App\\Dominio\\Escola\\Services\\AnoLetivoService;",
            "use App\\Dominio\\Escola\\Services\\AnoLetivoService;",
        )
        if "use App\\Dominio\\Escola\\Services\\AnoLetivoService" not in t2 and "AnoLetivoService" in t2:
            t2 = ensure_uses(t2, ["use App\\Dominio\\Escola\\Services\\AnoLetivoService;"])
        ano.write_text(t2)
        print("AnoLetivo uses OK")

    print("DONE lote Dominio")


if __name__ == "__main__":
    main()
