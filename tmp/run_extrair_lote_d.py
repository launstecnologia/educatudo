#!/usr/bin/env python3
"""Extrai fórum, flashcards e notificações; fecha leftovers de ocorrências."""
import sys
import re
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from extrair_modulo import extrair, ROOT, move_controller, strip_routes_from_file, add_psr4, write_routes, _route_matches_prefix


def collect(file_rel, prefixes, replacements):
    out = []
    for line in (ROOT / file_rel).read_text().splitlines():
        if not line.strip().startswith("$router->"):
            continue
        if not _route_matches_prefix(line, prefixes):
            continue
        l2 = line
        for old, new in replacements:
            l2 = l2.replace(old, new)
        out.append(l2)
    return out


# ── Fórum ──
forum_routes = []
for f, prefs in [
    ("backend/config/routes/student.php", ["/forum"]),
    ("backend/config/routes/admin.php", ["/admin/forum"]),
    ("backend/config/routes/teacher.php", ["/professor/forum"]),
]:
    forum_routes += collect(
        f,
        prefs,
        [
            ("Forum/ForumController@", "Modulos/forum/ForumController@"),
            ("Forum/ForumModerationController@", "Modulos/forum/ForumModerationController@"),
        ],
    )
ctrls = [
    c
    for c in [
        "Controllers/Forum/ForumController.php",
        "Controllers/Forum/ForumModerationController.php",
    ]
    if (ROOT / "backend/app" / c).exists()
]
print("forum", len(forum_routes), ctrls)
if ctrls and forum_routes:
    extrair(
        chave="forum",
        label="Fórum",
        controllers=ctrls,
        routes_body="\n".join(forum_routes),
        strip_specs=[
            ("backend/config/routes/student.php", ["/forum"], "// Fórum → Modulos/forum/routes.php"),
            ("backend/config/routes/admin.php", ["/admin/forum"], "// Fórum → Modulos/forum/routes.php"),
            ("backend/config/routes/teacher.php", ["/professor/forum"], "// Fórum → Modulos/forum/routes.php"),
        ],
    )

# ── Flashcards ──
fc_routes = collect(
    "backend/config/routes/student.php",
    ["/flashcards"],
    [("Study/FlashcardController@", "Modulos/flashcards/FlashcardController@")],
)
# teacher flashcards?
for f in ["backend/config/routes/teacher.php", "backend/config/routes/admin.php"]:
    fc_routes += collect(
        f,
        ["/flashcards", "/admin/flashcards", "/professor/flashcards"],
        [
            ("Study/FlashcardController@", "Modulos/flashcards/FlashcardController@"),
            ("Admin/FlashcardAdminController@", "Modulos/flashcards/FlashcardAdminController@"),
        ],
    )
fc_ctrl = [
    c
    for c in [
        "Controllers/Study/FlashcardController.php",
        "Controllers/Admin/FlashcardAdminController.php",
    ]
    if (ROOT / "backend/app" / c).exists()
]
print("flashcards", len(fc_routes), fc_ctrl)
if fc_ctrl and fc_routes:
    extrair(
        chave="flashcards",
        label="Flashcards",
        controllers=fc_ctrl,
        routes_body="\n".join(fc_routes),
        strip_specs=[
            ("backend/config/routes/student.php", ["/flashcards"], "// Flashcards → Modulos/flashcards/routes.php"),
        ],
    )

# ── Notificações ──
# routes mostly in parents.php (odd but current)
notif_routes = []
for f in [
    "backend/config/routes/parents.php",
    "backend/config/routes/admin.php",
    "backend/config/routes/teacher.php",
    "backend/config/routes/student.php",
    "backend/config/routes/public.php",
]:
    for line in (ROOT / f).read_text().splitlines():
        if not line.strip().startswith("$router->"):
            continue
        if not any(
            x in line
            for x in [
                "/admin/notifications",
                "/admin/notificacoes",
                "/admin/notificacoes-push",
                "Notifications/",
                "Noticias/Notificacoes",
            ]
        ):
            continue
        # skip parents personal page and push tracking API (core/api)
        if "User/ParentController@notificacoes" in line:
            continue
        if "Api/PushTrackingController@" in line or "Api/OneSignalTagsController@" in line:
            continue
        l2 = line
        for old, new in [
            ("Notifications/NotificationController@", "Modulos/notificacoes/NotificationController@"),
            ("Notifications/PushNotificationController@", "Modulos/notificacoes/PushNotificationController@"),
            ("Noticias/NotificacoesApiController@", "Modulos/notificacoes/NotificacoesApiController@"),
        ]:
            l2 = l2.replace(old, new)
        if "Modulos/notificacoes/" in l2:
            notif_routes.append(l2)

notif_ctrl = [
    c
    for c in [
        "Controllers/Notifications/NotificationController.php",
        "Controllers/Notifications/PushNotificationController.php",
        "Controllers/Noticias/NotificacoesApiController.php",
    ]
    if (ROOT / "backend/app" / c).exists()
]
print("notificacoes", len(notif_routes), notif_ctrl)
if notif_ctrl and notif_routes:
    extrair(
        chave="notificacoes",
        label="Notificações",
        controllers=notif_ctrl,
        routes_body="\n".join(notif_routes),
        strip_specs=[
            (
                "backend/config/routes/parents.php",
                ["/admin/notifications", "/admin/notificacoes-push"],
                "// Notificações → Modulos/notificacoes/routes.php",
            ),
            (
                "backend/config/routes/public.php",
                ["/api/notificacoes"],
                "// Notificações API → Modulos/notificacoes/routes.php",
            ),
        ],
        feature_gate=False,
    )

# ── Ocorrências leftovers → append to existing module routes ──
ocor_routes = []
admin = (ROOT / "backend/config/routes/admin.php").read_text().splitlines()
for line in admin:
    if not line.strip().startswith("$router->"):
        continue
    if "OccurrenceAdminController@" in line or "OcorrenciaAdminController@" in line:
        l2 = line.replace(
            "Admin/OccurrenceAdminController@",
            "Modulos/ocorrencias/OccurrenceAdminController@",
        ).replace(
            "Admin/OcorrenciaAdminController@",
            "Modulos/ocorrencias/OcorrenciaAdminController@",
        )
        ocor_routes.append(l2)

# move OccurrenceAdminController if still large
src = ROOT / "backend/app/Controllers/Admin/OccurrenceAdminController.php"
if src.exists() and src.stat().st_size > 400:
    # check if already in module
    dst = ROOT / "backend/app/Modulos/ocorrencias/Controllers/OccurrenceAdminController.php"
    if not dst.exists():
        move_controller("Controllers/Admin/OccurrenceAdminController.php", "ocorrencias", "Ocorrencias")

if ocor_routes:
    rp = ROOT / "backend/app/Modulos/ocorrencias/routes.php"
    ex = rp.read_text()
    add = []
    for r in ocor_routes:
        # ensure Modulos path
        if "Modulos/ocorrencias/" not in r:
            continue
        if r not in ex:
            add.append(r)
    if add:
        rp.write_text(ex.rstrip() + "\n" + "\n".join(add) + "\n")
        print("ocorrencias +", len(add))
    strip_routes_from_file(
        "backend/config/routes/admin.php",
        [
            "/admin/monitoramento/alertas",
            "/admin/ocorrencias/transcrever-audio",
            "/admin/ocorrencias/auto-preencher",
            "/admin/tentativas-login",
        ],
        "// Ocorrências extras → Modulos/ocorrencias/routes.php",
    )

print("LOTE D DONE")
