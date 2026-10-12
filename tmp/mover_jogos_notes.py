#!/usr/bin/env python3
from pathlib import Path
import shutil

APP = Path(__file__).resolve().parents[1] / "backend/app"

MOVES = [
    # (painel_dir, modulo_slug, old_ns, new_ns, pascal)
    ("Games", "jogos", "App\\Painel\\Games\\Controllers", "App\\Modulos\\Jogos\\Controllers", "Jogos"),
    ("Notes", "notes", "App\\Painel\\Notes\\Controllers", "App\\Modulos\\Notes\\Controllers", "Notes"),
]

MANIFESTS = {
    "jogos": """<?php
return [
    'chave' => 'jogos',
    'label' => 'Jogos',
    'namespace' => 'App\\Modulos\\Jogos',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'jogos'],
    'master_form_key' => 'geral_jogos',
    'rotas' => [
        '/jogos' => 'jogos',
        '/jogo-milhao' => 'jogos',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
];
""",
    "notes": """<?php
return [
    'chave' => 'notes',
    'label' => 'Notes',
    'namespace' => 'App\\Modulos\\Notes',
    'feature_gate' => true,
    'feature_keys' => ['aluno' => 'notes'],
    'master_form_key' => 'geral_notes',
    'rotas' => [
        '/notes' => 'notes',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
];
""",
}


def main() -> None:
    for painel_dir, slug, old_ns, new_ns, _pascal in MOVES:
        src = APP / "Painel" / painel_dir / "Controllers"
        dst = APP / "Modulos" / slug / "Controllers"
        dst.mkdir(parents=True, exist_ok=True)
        if not src.exists():
            print("missing", src)
            continue
        for f in src.glob("*.php"):
            target = dst / f.name
            text = f.read_text(errors="replace").replace(old_ns, new_ns)
            target.write_text(text)
            print("MOVE", f.name, "→", f"Modulos/{slug}/Controllers")
        shutil.rmtree(APP / "Painel" / painel_dir)
        print("rm Painel/" + painel_dir)

    for slug, body in MANIFESTS.items():
        path = APP / "Modulos" / slug / "manifest.php"
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body)
        print("manifest", path.relative_to(APP))


if __name__ == "__main__":
    main()
