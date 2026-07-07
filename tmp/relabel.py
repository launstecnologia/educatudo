import json, re
from pathlib import Path
from collections import Counter

graph = json.loads(Path('graphify-out/graph.json').read_text(encoding='utf-8'))

communities_data = {}
for n in graph.get('nodes', []):
    cid = str(n.get('community', ''))
    if cid:
        communities_data.setdefault(cid, []).append(n['id'])

def auto_label(cid, node_ids):
    primary_ids = node_ids[:10]
    class_names = Counter()
    for nid in primary_ids:
        m = re.match(r'app_controllers_(?:\w+_)?(\w+?)(?:controller)?(?:_\w+?controller)?(?:_|$)', nid)
        if m and m.group(1) not in ('admin','teacher','student','master','api','user','education','essays','exams','forum','files'):
            raw = m.group(1)
            if len(raw) > 3:
                class_names[raw] += 1
                continue
        m = re.match(r'app_services_(\w+?)(?:service)?(?:_|$)', nid)
        if m:
            raw = m.group(1)
            if len(raw) > 3:
                class_names[raw] += 1
                continue
        m = re.match(r'app_models_(?:\w+_)?(\w+?)(?:_\1)?(?:_|$)', nid)
        if m:
            raw = m.group(1)
            if len(raw) > 3:
                class_names[raw] += 1
                continue
        m = re.match(r'app_core_(\w+?)(?:_\1)?(?:_|$)', nid)
        if m:
            raw = m.group(1)
            if len(raw) > 3:
                class_names[raw] += 1
                continue
        m = re.match(r'public_static_js_(\w+?)(?:_min)?(?:_|$)', nid)
        if m:
            raw = m.group(1)
            if len(raw) > 2 and raw != 'mathlive':
                class_names['js_' + raw] += 1
                continue
        if nid.startswith('database_migrations_'):
            class_names['sql_migration'] += 1
            continue

    if not class_names:
        return f'Module {cid}'

    top_raw = class_names.most_common(1)[0][0]

    if top_raw.startswith('js_'):
        return 'JS: ' + top_raw[3:].replace('_', ' ').title()
    if top_raw == 'sql_migration':
        return 'SQL Migrations'

    label = top_raw.replace('controller','').replace('service','').replace('model','').replace('helper','').replace('admin','').strip('_')

    subs = {
        'avalive': 'AVA Live',
        'mockexam': 'Mock Exam',
        'parent': 'Parents Portal',
        'teacher': 'Teacher Profile',
        'aijob': 'AI Jobs',
        'devadmin': 'Dev Admin',
        'financeconfig': 'Finance Config',
        'onlineclass': 'Online Classes',
        'reportadmin': 'Reports Admin',
        'exam': 'Exams',
        'findbyaluno': 'Exam Lookup',
        'forum': 'Forum',
        'monitor': 'Room Monitor',
        'coursecategory': 'AVA Course Categories',
        'studentessay': 'Student Essays',
        'visualizarresultado': 'Exam Results View',
        'devlogs': 'Dev Logs',
        'classroom': 'Classrooms',
        'boletimconfig': 'Report Card',
        'finance': 'Finance',
        'teacherexam': 'Teacher Exams',
        'studentresponsibleimport': 'Student Import',
        'educahits': 'EducaHits Music',
        'apostilaia': 'AI Workbooks',
        'creditoscatalogo': 'Credits Catalog',
        'financecontract': 'Finance Contracts',
        'apostila': 'Workbooks',
        'notification': 'Notifications',
        'drive': 'Student Drive',
        'news': 'News Feed',
        'flashcard': 'Flashcards',
        'slide': 'AI Slides',
        'game': 'Games',
        'exercise': 'Exercises',
        'essay': 'Essays',
        'study': 'Study Tools',
        'report': 'Reports',
        'schedule': 'Schedule',
        'chat': 'Chat',
        'migration': 'Migrations',
        'subject': 'Subjects',
        'lessonplan': 'Lesson Plans',
        'simulado': 'Mock Exams (Simulado)',
        'attendance': 'Attendance',
        'grade': 'Grades',
        'user': 'Users',
        'auth': 'Auth',
        'jwt': 'JWT Auth',
        'asaas': 'Asaas Payments',
        'onesignal': 'Push Notifications',
        'evolution': 'WhatsApp',
        'aws': 'AWS Storage',
        'redis': 'Redis Cache',
        'tenant': 'Tenant Resolver',
        'middleware': 'Middleware',
    }
    if label in subs:
        return subs[label]

    spaced = re.sub(r'([a-z])([A-Z])', r'\1 \2', label).replace('_', ' ').strip()
    return spaced.title() if spaced else f'Module {cid}'

labels = json.loads(Path('graphify-out/.graphify_labels.json').read_text(encoding='utf-8'))
manually_named = {str(i) for i in range(30)}

updated = {}
for cid in labels:
    if cid in manually_named:
        updated[cid] = labels[cid]
    else:
        updated[cid] = auto_label(cid, communities_data.get(cid, []))

Path('graphify-out/.graphify_labels.json').write_text(json.dumps(updated, ensure_ascii=False), encoding='utf-8')

by_size = sorted(communities_data.items(), key=lambda x: -len(x[1]))
print('Labels (amostra 30-70):')
for cid, nodes in by_size[29:70]:
    print(f'  {cid} ({len(nodes)} nodes): {updated.get(cid, "?")}')
print(f'\nTotal: {len(updated)} labels')
