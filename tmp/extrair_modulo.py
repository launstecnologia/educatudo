#!/usr/bin/env python3
"""
Extrai controllers para app/Modulos/<chave>/ com namespace, shims, routes e PSR-4.

Uso programático: importar e chamar extrair(...).
"""
from __future__ import annotations

from pathlib import Path
import json
import re
from typing import Iterable

ROOT = Path(__file__).resolve().parents[1]


def _slug_to_ns(chave: str) -> str:
    parts = re.split(r"[-_]", chave)
    return "".join(p[:1].upper() + p[1:] for p in parts if p)


def ensure_manifest(chave: str, label: str, ns: str, feature_gate: bool = True) -> None:
    base = ROOT / "backend/app/Modulos" / chave
    base.mkdir(parents=True, exist_ok=True)
    (base / "Controllers").mkdir(exist_ok=True)
    man = base / "manifest.php"
    if man.exists():
        return
    man.write_text(
        "<?php\n"
        f"/**\n * Manifest do módulo {label}.\n */\n"
        "return [\n"
        f"    'chave' => '{chave}',\n"
        f"    'label' => '{label}',\n"
        f"    'namespace' => 'App\\\\Modulos\\\\{ns}',\n"
        f"    'feature_gate' => {'true' if feature_gate else 'false'},\n"
        "    'controllers_dir' => __DIR__ . '/Controllers',\n"
        "    'views_dir' => __DIR__ . '/Views',\n"
        "];\n"
    )


def move_controller(
    rel_src: str,
    chave: str,
    ns: str,
    *,
    global_alias: str | None = None,
) -> str:
    """Move controller para o módulo. rel_src ex: Controllers/Admin/Foo.php. Retorna class short name."""
    src = ROOT / "backend/app" / rel_src
    name = src.name
    short = name.replace(".php", "")
    dst = ROOT / "backend/app/Modulos" / chave / "Controllers" / name
    dst.parent.mkdir(parents=True, exist_ok=True)

    if src.is_file() and src.stat().st_size < 500 and "Shim" in src.read_text()[:200]:
        print(f"skip already shim: {rel_src}")
        return short

    if dst.exists() and (not src.exists() or "Modulos/" in str(src.resolve())):
        print(f"already in module: {name}")
        return short

    content = src.read_text()
    target_ns = f"App\\Modulos\\{ns}\\Controllers"

    if re.search(r"namespace\s+App\\Modulos\\", content):
        content2 = content
    elif re.search(r"namespace\s+[\w\\]+;", content):
        content2 = re.sub(
            r"namespace\s+[\w\\]+;",
            lambda _m: "namespace " + target_ns + ";",
            content,
            count=1,
        )
    else:
        content2 = re.sub(
            r"^<\?php\s*",
            lambda _m: "<?php\n\nnamespace " + target_ns + ";\n\n",
            content,
            count=1,
        )

    # Ensure class_alias for global name if file used class_exists guard with global
    # Garante imports comuns após mudança de namespace.
    inject_bits = []
    if "extends AdminBaseController" in content2 and "use App\\Controllers\\Admin\\AdminBaseController" not in content2:
        inject_bits.append("use App\\Controllers\\Admin\\AdminBaseController;")
    if "extends BaseController" in content2 and "use App\\Core\\BaseController" not in content2:
        inject_bits.extend(
            [
                "use App\\Core\\AuthManager;",
                "use App\\Core\\BaseController;",
                "use App\\Core\\Database;",
            ]
        )
    if inject_bits:
        inject = "\n".join(inject_bits) + "\n\n"
        content2 = content2.replace(
            f"namespace {target_ns};\n",
            f"namespace {target_ns};\n\n{inject}",
            1,
        )

    dst.write_text(content2)

    alias_global = global_alias or short
    # depth from Controllers/Admin = 2 to app/
    # shim path: dirname(__DIR__, 2) from Controllers/Admin or Controllers/Teacher
    parts = Path(rel_src).parts  # Controllers, Admin, Foo.php
    depth = len(parts) - 1  # directories under app/
    shim = (
        "<?php\n"
        f"/**\n * Shim — vive em app/Modulos/{chave}/.\n */\n"
        f"require_once dirname(__DIR__, {depth}) . '/Modulos/{chave}/Controllers/{name}';\n"
        f"if (!class_exists('{alias_global}', false)) {{\n"
        f"    class_alias(\\App\\Modulos\\{ns}\\Controllers\\{short}::class, '{alias_global}');\n"
        "}\n"
    )
    src.write_text(shim)
    print(f"moved {rel_src} -> Modulos/{chave}/Controllers/{name}")
    return short


def write_routes(chave: str, routes_php_body: str) -> None:
    path = ROOT / "backend/app/Modulos" / chave / "routes.php"
    path.write_text(
        "<?php\n"
        f"/**\n * Rotas do módulo {chave}.\n *\n * @var Router $router\n */\n\n"
        + routes_php_body.strip()
        + "\n"
    )
    print(f"routes written: {path.relative_to(ROOT)}")


def _route_matches_prefix(line: str, prefixes: Iterable[str]) -> bool:
    """Casa path da rota com prefixo sem pegar irmão (/admin/ava ≠ /admin/avatares)."""
    m = re.search(r"\$router->\w+\(\s*'([^']+)'", line)
    if not m:
        m = re.search(r'\$router->\w+\(\s*"([^"]+)"', line)
    if not m:
        return False
    path = m.group(1)
    for pref in prefixes:
        pref = pref.strip("'\"")
        if path == pref or path.startswith(pref.rstrip("/") + "/"):
            return True
    return False


def strip_routes_from_file(route_file: str, path_prefixes: Iterable[str], comment: str) -> None:
    p = ROOT / route_file
    lines = p.read_text().splitlines(keepends=True)
    prefixes = tuple(path_prefixes)
    out = []
    wrote = False
    i = 0
    while i < len(lines):
        line = lines[i]
        is_route = line.strip().startswith("$router->") and _route_matches_prefix(line, prefixes)
        if is_route:
            if not wrote:
                if out and out[-1].strip().startswith("//"):
                    out[-1] = comment + "\n"
                else:
                    out.append(comment + "\n")
                wrote = True
            i += 1
            continue
        out.append(line)
        i += 1
    p.write_text("".join(out))
    left = sum(
        1
        for l in p.read_text().splitlines()
        if l.strip().startswith("$router->") and any(pref in l for pref in prefixes)
    )
    print(f"{route_file}: routes left for {prefixes}: {left}")


def add_psr4(ns: str, chave: str) -> None:
    cp = ROOT / "backend/composer.json"
    data = json.loads(cp.read_text())
    psr4 = data.setdefault("autoload", {}).setdefault("psr-4", {})
    psr4[f"App\\Modulos\\{ns}\\"] = f"app/Modulos/{chave}/"
    cp.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")
    print(f"PSR-4 App\\Modulos\\{ns}\\ OK")


def extrair(
    chave: str,
    label: str,
    controllers: list[str],
    routes_body: str,
    strip_specs: list[tuple[str, list[str], str]],
    *,
    feature_gate: bool = True,
) -> None:
    ns = _slug_to_ns(chave)
    ensure_manifest(chave, label, ns, feature_gate=feature_gate)
    for rel in controllers:
        move_controller(rel, chave, ns)
    write_routes(chave, routes_body)
    for route_file, prefixes, comment in strip_specs:
        strip_routes_from_file(route_file, prefixes, comment)
    add_psr4(ns, chave)
