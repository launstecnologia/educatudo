#!/usr/bin/env python3
from pathlib import Path

APP = Path(__file__).resolve().parents[1] / "backend/app"

MOVES = [
    ("Modulos/educahits", "App\\Painel\\EducaHits\\Controllers", "App\\Modulos\\EducaHits\\Controllers"),
    ("Modulos/educalabs", "App\\Painel\\EducaLabs\\Controllers", "App\\Modulos\\EducaLabs\\Controllers"),
    ("Modulos/external-apps", "App\\Painel\\ExternalApps\\Controllers", "App\\Modulos\\ExternalApps\\Controllers"),
]

MANIFESTS = {
    "educahits": """<?php
return [
    'chave' => 'educahits',
    'label' => 'EducaHits',
    'namespace' => 'App\\\\Modulos\\\\EducaHits',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'educa_hits'],
    'master_form_key' => 'geral_educa_hits',
    'rotas' => ['/hits' => 'educa_hits'],
    'controllers_dir' => __DIR__ . '/Controllers',
];
""",
    "educalabs": """<?php
return [
    'chave' => 'educalabs',
    'label' => 'EducaLabs',
    'namespace' => 'App\\\\Modulos\\\\EducaLabs',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'educalabs'],
    'master_form_key' => 'geral_educalabs',
    'rotas' => ['/educalabs' => 'educalabs'],
    'controllers_dir' => __DIR__ . '/Controllers',
];
""",
    "external-apps": """<?php
return [
    'chave' => 'external-apps',
    'label' => 'Apps Externos',
    'namespace' => 'App\\\\Modulos\\\\ExternalApps',
    'feature_gate' => false,
    'controllers_dir' => __DIR__ . '/Controllers',
];
""",
}


def main() -> None:
    for rel, old_ns, new_ns in MOVES:
        root = APP / rel
        for f in root.rglob("*.php"):
            if f.name == "manifest.php":
                continue
            t = f.read_text(errors="replace")
            if old_ns in t:
                f.write_text(t.replace(old_ns, new_ns))
                print("ns", f.relative_to(APP))
            else:
                print("skip ns", f.relative_to(APP))

    for slug, body in MANIFESTS.items():
        # body uses \\ for PHP single-quoted namespace - write as real backslashes
        content = body.replace("\\\\", "\\")
        path = APP / "Modulos" / slug / "manifest.php"
        path.write_text(content)
        print("manifest", path.relative_to(APP))


if __name__ == "__main__":
    main()
