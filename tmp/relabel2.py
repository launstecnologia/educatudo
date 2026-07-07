import json, re
from pathlib import Path
from collections import Counter

graph = json.loads(Path('graphify-out/graph.json').read_text(encoding='utf-8'))

# Build community -> list of node labels (human-readable)
communities_labels = {}
communities_ids = {}
for n in graph.get('nodes', []):
    cid = str(n.get('community', ''))
    if cid:
        lbl = n.get('label', '') or ''
        nid = n.get('id', '') or ''
        communities_labels.setdefault(cid, []).append(lbl)
        communities_ids.setdefault(cid, []).append(nid)

def clean_class_name(name):
    """Convert class name like 'TeacherJourneyController' -> 'Teacher Journey'"""
    name = re.sub(r'Controller$|Service$|Model$|Helper$|Manager$|Repository$', '', name)
    # Split camelCase
    spaced = re.sub(r'([a-z])([A-Z])', r'\1 \2', name)
    spaced = re.sub(r'([A-Z]+)([A-Z][a-z])', r'\1 \2', spaced)
    return spaced.strip()

def infer_community_label(cid, node_labels, node_ids):
    """Infer a human-readable label from the node labels and IDs in a community."""

    # Count class/entity names
    class_counter = Counter()
    for lbl in node_labels[:30]:
        if not lbl:
            continue
        # If it's a method name (contains lowercase then uppercase), take the class part
        # Try to identify the primary class by finding repeated prefix
        parts = lbl.split('.')
        if len(parts) > 1:
            class_counter[parts[0].strip()] += 1
        else:
            # Single word label - count it if it looks like a class
            if lbl[0].isupper() and len(lbl) > 3:
                class_counter[lbl] += 1

    # Find the most common base class
    top_classes = class_counter.most_common(5)

    if top_classes:
        # Pick the most common one that's not too generic
        skip = {'self', 'Database', 'BaseController', 'LayoutHelper', 'Logger', '__construct', 'e()', 'construct'}
        for cls, cnt in top_classes:
            if cls not in skip and len(cls) > 3:
                label = clean_class_name(cls)
                if label:
                    return label

    # Fallback: extract from node IDs
    for nid in node_ids[:5]:
        # Look for controller/service pattern
        m = re.search(r'_([\w]+(?:controller|service|model|helper))(?:_|$)', nid)
        if m:
            raw = m.group(1)
            raw = re.sub(r'controller$|service$|model$|helper$', '', raw, flags=re.I)
            # Convert snake_case to Title Case
            return raw.replace('_', ' ').title()

    # Last resort
    if node_labels:
        first = node_labels[0]
        if first:
            return clean_class_name(first)[:30]

    return f'Module {cid}'

# Hardcoded labels for top 30 (from previous step)
manual = {
    '0': 'MathLive Minified (Functions)',
    '1': 'MathLive Core Library',
    '2': 'MathLive DOM Operations',
    '3': 'Master SaaS Panel',
    '4': 'Core Infrastructure',
    '5': 'AI / OpenAI Services',
    '6': 'API Controllers & Master Auth',
    '7': 'MathLive UI Keyboard',
    '8': 'External Apps Integration',
    '9': 'MathLive Parsing & Context',
    '10': 'MathLive Layout Engine',
    '11': 'School Calendar & Mobile API',
    '12': 'Authentication Flow',
    '13': 'Student Context Builder',
    '14': 'Essay Management (Teacher)',
    '15': 'Teacher Journey Module',
    '16': 'EducaLabs AI Workspace',
    '17': 'Student Journey & Learning',
    '18': 'Student Profile & Wallet',
    '19': 'Admin Finance & Billing',
    '20': 'Admin Permission Profiles',
    '21': 'Admin Journey Management',
    '22': 'Master Schools CRUD',
    '23': 'Admin Essay Management',
    '24': 'Exam Models',
    '25': 'Report Card Config',
    '26': 'Academic Health Dashboard',
    '27': 'MathLive Style & Atoms',
    '28': 'Student Admin Panel',
    '29': 'API Notifications & Swagger',
}

updated = {}
for cid in communities_labels:
    if cid in manual:
        updated[cid] = manual[cid]
    else:
        updated[cid] = infer_community_label(
            cid,
            communities_labels[cid],
            communities_ids.get(cid, [])
        )

Path('graphify-out/.graphify_labels.json').write_text(
    json.dumps(updated, ensure_ascii=False), encoding='utf-8'
)

by_size = sorted(communities_labels.items(), key=lambda x: -len(x[1]))
print('Sample labels:')
for cid, lbls in by_size[:60]:
    print(f'  {cid} ({len(lbls):3d} nodes): {updated.get(cid, "?")}')
print(f'\nTotal: {len(updated)} labels saved')
