#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"

# Infra: Services/ era app/Services (2 níveis até backend); Infra/Services precisa de 3
infra = APP / "Infra/Services"
for f in infra.rglob("*.php"):
    t = f.read_text(errors="replace")
    orig = t
    t = t.replace("__DIR__ . '/../../vendor/", "__DIR__ . '/../../../vendor/")
    t = t.replace('__DIR__ . "/../../vendor/', '__DIR__ . "/../../../vendor/')
    t = t.replace("dirname(__DIR__, 2) . '/storage/", "dirname(__DIR__, 3) . '/storage/")
    t = t.replace("dirname(__DIR__, 2) . '/config/", "dirname(__DIR__, 3) . '/config/")
    t = t.replace('dirname(__DIR__, 2) . "/storage/', 'dirname(__DIR__, 3) . "/storage/')
    t = t.replace('dirname(__DIR__, 2) . "/config/', 'dirname(__DIR__, 3) . "/config/')
    if f.name == "FirebaseMessagingService.php":
        t = t.replace(
            "dirname(__DIR__, 2) . '/' . ltrim",
            "dirname(__DIR__, 3) . '/' . ltrim",
        )
    if t != orig:
        f.write_text(t)
        print("infra", f.relative_to(APP))


def patch(path: str, old: str, new: str) -> None:
    p = APP / path if not path.startswith("/") else Path(path)
    if not p.exists():
        # try relative from ROOT
        p = ROOT / path
    if not p.exists():
        print("missing", path)
        return
    t = p.read_text(errors="replace")
    if old not in t:
        print("no-match", path, old[:60])
        return
    p.write_text(t.replace(old, new))
    print("patched", path)


# Hard paths → autoload
patch(
    "backend/app/Painel/Admin/Controllers/DevAdminController.php",
    "$path = __DIR__ . '/../../Services/EvolutionApiService.php';",
    "// EvolutionApiService via Compat autoload\n            $path = null;",
)
patch(
    "backend/app/Modulos/provas/Models/ExamBlockManualGrade.php",
    "$path = __DIR__ . '/../../Services/TipoNotaRegraService.php';",
    "$path = null; // TipoNotaRegraService via Compat",
)
patch(
    "backend/app/Modulos/boletins/Controllers/BoletimConfigController.php",
    "$svcPath = __DIR__ . '/../../Services/TipoNotaRegraService.php';",
    "$svcPath = null; // TipoNotaRegraService via Compat",
)

# Views / controllers that file_exists + require CreditosService / BoletimConfig
for rel in [
    "backend/app/Views/layouts/admin.php",
    "backend/app/Views/layouts/student.php",
    "backend/app/Views/layouts/professor.php",
]:
    p = ROOT / rel
    t = p.read_text(errors="replace")
    old = "__DIR__ . '/../../Services/CreditosService.php'"
    if old in t:
        # replace file_exists(path) patterns carefully — use class_exists
        t2 = t.replace(
            old,
            "null /* CreditosService via Compat */",
        )
        p.write_text(t2)
        print("patched credits path", rel)

for rel in [
    "backend/app/Painel/User/Controllers/StudentController.php",
    "backend/app/Painel/User/Controllers/ParentController.php",
]:
    p = ROOT / rel
    t = p.read_text(errors="replace")
    old = "__DIR__ . '/../../Models/System/BoletimConfig.php'"
    if old in t:
        p.write_text(t.replace(old, "null /* BoletimConfig via Compat */"))
        print("patched boletim path", rel)

# Grep remaining
print("--- remaining Services/ path refs ---")
for f in APP.rglob("*.php"):
    if "Compat" in str(f):
        continue
    try:
        text = f.read_text(errors="replace")
    except Exception:
        continue
    for i, line in enumerate(text.splitlines(), 1):
        if "/Services/" in line and ("require" in line or "file_exists" in line or "$path" in line or "$svc" in line.lower()):
            if "Modulos/" in line or "Dominio/" in line or "Infra/" in line or "Painel/" in line:
                continue
            if "app/Services" in line or "'/../../Services" in line or '"/../../Services' in line or "/Services/" in line:
                print(f"{f.relative_to(APP)}:{i}: {line.strip()[:140]}")
