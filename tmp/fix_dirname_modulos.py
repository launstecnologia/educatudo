#!/usr/bin/env python3
from pathlib import Path

files = [
    "backend/app/Modulos/boletins/Controllers/BoletimAssistenteController.php",
    "backend/app/Modulos/boletins/Controllers/BoletimConfigController.php",
    "backend/app/Modulos/provas/Controllers/ExamBlockController.php",
    "backend/app/Modulos/provas/Controllers/TeacherExamController.php",
]
root = Path(__file__).resolve().parents[1]
for f in files:
    p = root / f
    t = p.read_text()
    t2 = t
    for old, new in [
        ("dirname(__DIR__, 2) . '/Models/", "dirname(__DIR__, 3) . '/Models/"),
        ("dirname(__DIR__, 2) . '/Modulos/", "dirname(__DIR__, 3) . '/Modulos/"),
        ("dirname(__DIR__, 2) . '/Helpers/", "dirname(__DIR__, 3) . '/Helpers/"),
        ("dirname(__DIR__, 2) . '/Core/", "dirname(__DIR__, 3) . '/Core/"),
        ('dirname(__DIR__, 2) . "/Models/', 'dirname(__DIR__, 3) . "/Models/'),
        ('dirname(__DIR__, 2) . "/Modulos/', 'dirname(__DIR__, 3) . "/Modulos/'),
        ('dirname(__DIR__, 2) . "/Core/', 'dirname(__DIR__, 3) . "/Core/'),
    ]:
        t2 = t2.replace(old, new)
    if t2 != t:
        p.write_text(t2)
        print("fixed", f)
    else:
        print("no change", f)
