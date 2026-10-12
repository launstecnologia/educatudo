#!/usr/bin/env python3
"""Fecha migração do módulo financeiro: admin.php + composer + manifest."""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]


def strip_admin_routes() -> None:
    p = ROOT / "backend/config/routes/admin.php"
    lines = p.read_text().splitlines(keepends=True)
    out = []
    i = 0
    comment_markers = (
        "Busca de alunos",
        "Planos",
        "Extrato",
        "Lista e gestão",
        "Cobranças",
        "Fluxo de Caixa",
        "Contas a Pagar",
        "Relatórios contábeis",
        "Configurações",
        "Renegociação",
    )
    while i < len(lines):
        line = lines[i]
        if "Admin/FinanceAdminController@salvarFinanceiroConfig" in line:
            out.append("// Dev financeiro → Modulos/financeiro/routes.php\n")
            i += 1
            continue
        if line.startswith("// ── Financeiro Escolar"):
            out.append("// Financeiro escolar → Modulos/financeiro/routes.php\n")
            i += 1
            while i < len(lines):
                l = lines[i]
                if l.strip().startswith("$router->") and (
                    "/admin/finance" in l
                    or "/admin/alunos/search" in l
                    or "/admin/alunos/{alunoId}/responsaveis" in l
                ):
                    i += 1
                    continue
                if l.strip().startswith("//") and any(x in l for x in comment_markers):
                    i += 1
                    continue
                if l.strip() == "":
                    nxt = lines[i + 1] if i + 1 < len(lines) else ""
                    if (
                        "/admin/finance" in nxt
                        or "/admin/alunos/search" in nxt
                        or "/admin/alunos/{alunoId}/responsaveis" in nxt
                        or (nxt.strip().startswith("//") and any(x in nxt for x in comment_markers))
                    ):
                        i += 1
                        continue
                    break
                break
            continue
        out.append(line)
        i += 1
    p.write_text("".join(out))
    t = p.read_text()
    print("finance routes left:", t.count("/admin/finance"))
    print("FinanceController left:", t.count("FinanceController"))
    print("hub financeiro-escolar:", t.count("financeiro-escolar"))


def patch_composer() -> None:
    cp = ROOT / "backend/composer.json"
    data = json.loads(cp.read_text())
    psr4 = data.setdefault("autoload", {}).setdefault("psr-4", {})
    psr4["App\\Modulos\\Financeiro\\"] = "app/Modulos/financeiro/"
    cp.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")
    print("composer Financeiro OK")


def patch_manifest() -> None:
    man = ROOT / "backend/app/Modulos/financeiro/manifest.php"
    man.write_text(
        """<?php
/**
 * Manifest do módulo Financeiro escolar.
 */
return [
    'chave' => 'financeiro',
    'label' => 'Financeiro',
    'namespace' => 'App\\\\Modulos\\\\Financeiro',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'financeiro',
    ],
    'master_feature_keys' => ['financeiro'],
    'master_form_key' => 'geral_financeiro',
    'feature_defaults' => [
        'financeiro' => '1',
    ],
    'feature_gate' => false,
    'rotas' => [
        '/admin/finance' => 'financeiro',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
"""
    )
    # fix double escaping - write properly
    man.write_text(
        """<?php
/**
 * Manifest do módulo Financeiro escolar.
 */
return [
    'chave' => 'financeiro',
    'label' => 'Financeiro',
    'namespace' => 'App\\Modulos\\Financeiro',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'financeiro',
    ],
    'master_feature_keys' => ['financeiro'],
    'master_form_key' => 'geral_financeiro',
    'feature_defaults' => [
        'financeiro' => '1',
    ],
    'feature_gate' => false,
    'rotas' => [
        '/admin/finance' => 'financeiro',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
"""
    )
    print("manifest OK")


if __name__ == "__main__":
    strip_admin_routes()
    patch_composer()
    patch_manifest()
