<?php
require_once 'header.php';

// ============================================================
// معالجة الطلبات AJAX
// ============================================================

// 1. جلب أبناء وأحفاد شخص معين
if (isset($_GET['action']) && $_GET['action'] === 'get_descendants') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = intval($_GET['person_id']);

    $all = [];
    $res = $mysqli->query("
        SELECT 
            p.id, 
            CONCAT_WS(' ', p.full_name, f.full_name, g.full_name) AS full_name, 
            p.father_id, 
            p.gender, 
            p.status,
            p.ration_number
        FROM people2 p
        LEFT JOIN people2 f ON p.father_id = f.id
        LEFT JOIN people2 g ON f.father_id = g.id
    ");
    while ($r = $res->fetch_assoc()) {
        $all[$r['id']] = $r;
    }
    $res->free();

    function get_all_descendants($pid, $all) {
        $result = [];
        $queue = [$pid];
        while (!empty($queue)) {
            $curr = array_shift($queue);
            foreach ($all as $p) {
                if ($p['father_id'] == $curr) {
                    if ($p['gender'] === 'Male') {
                        $queue[] = $p['id'];
                        $result[] = $p;
                    }
                }
            }
        }
        return $result;
    }

    $descendants = get_all_descendants($pid, $all);
    echo json_encode($descendants, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. البحث عن شخص
if (isset($_GET['action']) && $_GET['action'] === 'search_person') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    $isCode = preg_match('/^\d+$/', $q);
    
    if ($isCode) {
        $code = intval($q);
        $query = "
            SELECT 
                p.id, 
                CONCAT_WS(' ', p.full_name, f.full_name, g.full_name) AS full_name,
                p.full_name AS first_name,
                f.full_name AS father_name,
                g.full_name AS grandfather_name,
                p.gender, 
                p.status,
                p.ration_number
            FROM people2 p
            LEFT JOIN people2 f ON p.father_id = f.id
            LEFT JOIN people2 g ON f.father_id = g.id
            WHERE p.ration_number = $code
            LIMIT 20
        ";
    } else {
        $escaped = $mysqli->real_escape_string($q);
        $like = '%' . $escaped . '%';
        $query = "
            SELECT 
                p.id, 
                CONCAT_WS(' ', p.full_name, f.full_name, g.full_name) AS full_name,
                p.full_name AS first_name,
                f.full_name AS father_name,
                g.full_name AS grandfather_name,
                p.gender, 
                p.status,
                p.ration_number
            FROM people2 p
            LEFT JOIN people2 f ON p.father_id = f.id
            LEFT JOIN people2 g ON f.father_id = g.id
            WHERE p.full_name LIKE '$like' 
               OR CONCAT_WS(' ', p.full_name, f.full_name, g.full_name) LIKE '$like'
            LIMIT 20
        ";
    }
    
    $res = $mysqli->query($query);
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. جلب أعضاء جروب للتعديل (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'get_group_data') {
    header('Content-Type: application/json; charset=utf-8');
    $gid = intval($_GET['group_id']);

    $gRes = $mysqli->query("SELECT * FROM family_groups WHERE id = $gid");
    $group = $gRes->fetch_assoc();
    if (!$group) { echo json_encode(['error' => 'not found']); exit; }

    // القادة
    $lRes = $mysqli->query("
        SELECT p.id, CONCAT_WS(' ', p.full_name, f.full_name, g.full_name) AS full_name, p.gender, p.status, p.ration_number
        FROM group_leaders gl
        JOIN people2 p ON p.id = gl.person_id
        LEFT JOIN people2 f ON p.father_id = f.id
        LEFT JOIN people2 g ON f.father_id = g.id
        WHERE gl.group_id = $gid
    ");
    $leaders = [];
    while ($r = $lRes->fetch_assoc()) $leaders[] = $r;

    // الأعضاء
    $mRes = $mysqli->query("
        SELECT p.id, CONCAT_WS(' ', p.full_name, f.full_name, g.full_name) AS full_name, p.gender, p.status, p.ration_number
        FROM group_members gm
        JOIN people2 p ON p.id = gm.person_id
        LEFT JOIN people2 f ON p.father_id = f.id
        LEFT JOIN people2 g ON f.father_id = g.id
        WHERE gm.group_id = $gid
    ");
    $members = [];
    while ($r = $mRes->fetch_assoc()) $members[] = $r;

    echo json_encode([
        'group' => $group,
        'leaders' => $leaders,
        'members' => $members
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. حفظ جروب جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_group'])) {
    $name    = $mysqli->real_escape_string(trim($_POST['group_name']));
    $color   = $mysqli->real_escape_string($_POST['group_color']);
    $leaders = json_decode($_POST['leaders'], true) ?? [];
    $members = json_decode($_POST['members'], true) ?? [];

    if ($name && !empty($leaders)) {
        $mysqli->query("INSERT INTO family_groups (group_name, group_color) VALUES ('$name', '$color')");
        $gid = $mysqli->insert_id;

        foreach ($leaders as $lid) {
            $lid = intval($lid);
            $mysqli->query("INSERT IGNORE INTO group_leaders (group_id, person_id) VALUES ($gid, $lid)");
        }
        // القائد يُحسب كعضو + باقي الأعضاء
        $allPersonIds = array_unique(array_merge($leaders, $members));
        foreach ($allPersonIds as $mid) {
            $mid = intval($mid);
            $mysqli->query("INSERT IGNORE INTO group_members (group_id, person_id) VALUES ($gid, $mid)");
        }
        $success = 'تم إنشاء الجروب بنجاح!';
    } else {
        $error = 'يرجى إدخال اسم الجروب واختيار قائد واحد على الأقل.';
    }
}

// 5. تعديل جروب موجود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_group'])) {
    $gid     = intval($_POST['edit_group_id']);
    $name    = $mysqli->real_escape_string(trim($_POST['edit_group_name']));
    $color   = $mysqli->real_escape_string($_POST['edit_group_color']);
    $leaders = json_decode($_POST['edit_leaders'], true) ?? [];
    $members = json_decode($_POST['edit_members'], true) ?? [];

    if ($name) {
        $mysqli->query("UPDATE family_groups SET group_name='$name', group_color='$color' WHERE id=$gid");
        $mysqli->query("DELETE FROM group_leaders WHERE group_id=$gid");
        $mysqli->query("DELETE FROM group_members WHERE group_id=$gid");

        foreach ($leaders as $lid) {
            $lid = intval($lid);
            $mysqli->query("INSERT IGNORE INTO group_leaders (group_id, person_id) VALUES ($gid, $lid)");
        }
        // القائد يُحسب كعضو
        $allPersonIds = array_unique(array_merge($leaders, $members));
        foreach ($allPersonIds as $mid) {
            $mid = intval($mid);
            $mysqli->query("INSERT IGNORE INTO group_members (group_id, person_id) VALUES ($gid, $mid)");
        }
        $success = 'تم تعديل الجروب بنجاح!';
    } else {
        $error = 'يرجى إدخال اسم الجروب.';
    }
}

// 6. حذف جروب
if (isset($_GET['delete_group'])) {
    $gid = intval($_GET['delete_group']);
    $mysqli->query("DELETE FROM family_groups WHERE id = $gid");
    header('Location: groups_manage.php');
    exit;
}

// جلب قائمة الجروبات
$groups = [];
$gres = $mysqli->query("SELECT g.*, 
    (SELECT COUNT(*) FROM group_leaders WHERE group_id = g.id) as leader_count,
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
    FROM family_groups g ORDER BY g.created_at DESC");
while ($gr = $gres->fetch_assoc()) $groups[] = $gr;
$gres->free();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الجروبات العائلية</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap');

        :root {
            --bg-deep:    #080c12;
            --bg-panel:   #0e1420;
            --bg-card:    rgba(255,255,255,0.042);
            --border:     rgba(255,255,255,0.09);
            --accent:     #38bdf8;
            --accent-rgb: 56,189,248;
            --gold:       #fbbf24;
            --gold-rgb:   251,191,36;
            --emerald:    #34d399;
            --rose:       #fb7185;
            --purple:     #a78bfa;
            --text:       #e2e8f0;
            --text-muted: rgba(226,232,240,0.45);
            --shadow:     0 8px 32px rgba(0,0,0,0.5);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg-deep);
            min-height: 100vh;
            color: var(--text);
            font-family: 'Tajawal', sans-serif;
            padding-bottom: 80px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(56,189,248,0.06) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 80%, rgba(167,139,250,0.05) 0%, transparent 60%),
                radial-gradient(ellipse 50% 50% at 50% 50%, rgba(251,191,36,0.03) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        .page-wrapper { position: relative; z-index: 1; }

        .main-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 28px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 90px 32px 40px;
        }

        .left-panel { min-width: 0; }
        .right-panel { 
            position: sticky;
            top: 90px;
            height: fit-content;
        }

        @media (max-width: 1100px) {
            .main-layout { grid-template-columns: 1fr; padding: 80px 16px 40px; }
            .right-panel { position: static; }
        }

        .page-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 2.4rem;
            font-weight: 900;
            background: linear-gradient(120deg, var(--accent), var(--purple), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .page-header p { color: var(--text-muted); font-size: 0.95rem; margin-top: 6px; }

        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .section-title .icon-wrap {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: rgba(var(--accent-rgb), 0.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            color: var(--accent);
            flex-shrink: 0;
        }

        /* Tabs */
        .tabs-bar {
            display: flex;
            gap: 6px;
            margin-bottom: 24px;
            background: rgba(255,255,255,0.04);
            border-radius: 14px;
            padding: 5px;
        }
        .tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            background: none;
            color: var(--text-muted);
            font-family: 'Tajawal', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .tab-btn.active {
            background: rgba(var(--accent-rgb), 0.15);
            color: var(--accent);
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Search */
        .search-wrapper { position: relative; display: flex; gap: 0; }
        .search-type-toggle {
            display: flex;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 12px 0 0 12px;
            border-left: none;
            overflow: hidden;
        }
        .search-type-btn {
            padding: 0 14px;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: none;
            white-space: nowrap;
            font-family: 'Tajawal', sans-serif;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .search-type-btn.active { background: rgba(var(--accent-rgb), 0.15); color: var(--accent); }
        .search-type-divider { width: 1px; background: var(--border); align-self: stretch; margin: 8px 0; }
        .search-box {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 0 12px 12px 0;
            padding: 12px 16px;
            color: var(--text);
            font-size: 0.95rem;
            font-family: 'Tajawal', sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-box:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.12); }
        .search-box::placeholder { color: var(--text-muted); }

        .search-results {
            position: absolute;
            top: calc(100% + 6px);
            left: 0; right: 0;
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            max-height: 280px;
            overflow-y: auto;
            display: none;
            z-index: 100;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }
        .search-item {
            padding: 11px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.15s;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 0.9rem;
        }
        .search-item:last-child { border-bottom: none; }
        .search-item:hover { background: rgba(var(--accent-rgb), 0.08); }
        .search-item.selected { opacity: 0.5; cursor: default; }
        .search-item .name-col { flex: 1; }
        .search-item .name-main { font-weight: 600; }
        .search-item .name-code { font-size: 0.75rem; color: var(--text-muted); font-family: monospace; }

        /* Tags */
        .tags-area {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
            min-height: 38px;
        }
        .tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 50px;
            padding: 5px 14px 5px 10px;
            font-size: 0.83rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .tag-leader { background: rgba(var(--gold-rgb), 0.12); border: 1px solid rgba(var(--gold-rgb), 0.3); color: var(--gold); }
        .tag-remove {
            width: 18px; height: 18px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.5);
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .tag-remove:hover { background: rgba(251,113,113,0.25); color: var(--rose); }

        /* Members section */
        .members-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .members-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--emerald);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .count-badge {
            background: rgba(52,211,153,0.15);
            color: var(--emerald);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .members-note {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            max-height: 320px;
            overflow-y: auto;
            padding: 4px;
        }
        .member-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 9px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.83rem;
            transition: all 0.2s;
            position: relative;
        }
        .member-card:hover { border-color: rgba(var(--accent-rgb), 0.3); background: rgba(var(--accent-rgb), 0.04); }
        .member-card.is-leader { border-color: rgba(var(--gold-rgb), 0.3); background: rgba(var(--gold-rgb), 0.05); }
        .member-card .mc-name {
            flex: 1;
            font-weight: 500;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .member-card .mc-remove {
            width: 22px; height: 22px;
            border-radius: 6px;
            background: rgba(255,255,255,0.06);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 0.65rem;
            color: rgba(255,255,255,0.4);
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .member-card .mc-remove:hover { background: rgba(251,113,113,0.2); color: var(--rose); }
        .member-card .mc-leader-badge {
            font-size: 0.6rem;
            background: rgba(var(--gold-rgb), 0.2);
            color: var(--gold);
            border-radius: 4px;
            padding: 1px 5px;
        }

        /* Add member manually button */
        .add-member-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(52,211,153,0.08);
            border: 1px dashed rgba(52,211,153,0.3);
            border-radius: 10px;
            padding: 8px 14px;
            color: var(--emerald);
            font-family: 'Tajawal', sans-serif;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .add-member-btn:hover { background: rgba(52,211,153,0.15); border-color: rgba(52,211,153,0.5); }

        /* Group name input */
        .group-name-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 18px;
            color: var(--text);
            font-size: 1.05rem;
            font-weight: 700;
            font-family: 'Tajawal', sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .group-name-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.12); }
        .group-name-input::placeholder { color: var(--text-muted); font-weight: 400; }

        /* Colors */
        .color-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        input[type="color"] {
            width: 48px; height: 48px;
            border: 2px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            background: none;
            padding: 2px;
        }
        .color-swatches { display: flex; gap: 8px; flex-wrap: wrap; }
        .swatch {
            width: 28px; height: 28px;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.15s, border-color 0.15s;
        }
        .swatch:hover { transform: scale(1.15); }
        .swatch.active { border-color: white; transform: scale(1.15); }

        /* Preview */
        .group-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 14px;
        }
        .preview-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; transition: background 0.3s; }
        .preview-name { font-weight: 700; font-size: 0.95rem; transition: color 0.3s; }
        .preview-counts { margin-right: auto; font-size: 0.78rem; color: var(--text-muted); }

        /* Save button */
        .btn-save {
            width: 100%;
            background: linear-gradient(135deg, var(--accent), #0ea5e9);
            border: none;
            border-radius: 14px;
            padding: 15px;
            color: #050a10;
            font-size: 1rem;
            font-weight: 800;
            font-family: 'Tajawal', sans-serif;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
        }
        .btn-save:hover { opacity: 0.92; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(var(--accent-rgb), 0.35); }
        .btn-save.edit-mode { background: linear-gradient(135deg, var(--purple), #7c3aed); }

        /* Steps bar */
        .steps-bar { display: flex; gap: 6px; margin-bottom: 24px; }
        .step-item { flex: 1; height: 4px; border-radius: 4px; background: rgba(255,255,255,0.1); transition: background 0.3s; }
        .step-item.done { background: var(--emerald); }
        .step-item.active { background: var(--accent); }

        /* Field label */
        .field-label {
            font-size: 0.83rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .field-label i { color: var(--accent); font-size: 0.8rem; }

        /* Groups list */
        .groups-list { display: flex; flex-direction: column; gap: 12px; }
        .group-row {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .group-row:hover { background: rgba(255,255,255,0.055); transform: translateX(-2px); }
        .group-row-dot {
            width: 40px; height: 40px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .group-row-info { flex: 1; min-width: 0; }
        .group-row-name {
            font-weight: 800;
            font-size: 0.95rem;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .group-row-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; display: flex; gap: 12px; }
        .group-row-meta span { display: flex; align-items: center; gap: 5px; }
        .group-row-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .btn-action {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            color: var(--text);
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: 'Tajawal', sans-serif;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-action:hover { background: rgba(255,255,255,0.13); color: white; }
        .btn-action.danger { border-color: rgba(251,113,113,0.3); }
        .btn-action.danger:hover { background: rgba(251,113,113,0.15); color: var(--rose); }
        .btn-action.edit { border-color: rgba(var(--purple),0.3); }
        .btn-action.edit:hover { background: rgba(167,139,250,0.15); color: var(--purple); }

        /* Alerts */
        .alert-box {
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }
        .alert-success { background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.3); color: var(--emerald); }
        .alert-error   { background: rgba(251,113,113,0.1); border: 1px solid rgba(251,113,113,0.3); color: var(--rose); }

        /* Empty state */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.4; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 5px; }

        .no-members-msg { text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 20px; }
        .divider { height: 1px; background: var(--border); margin: 20px 0; }

        .hint-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            color: rgba(251,191,36,0.7);
            background: rgba(251,191,36,0.06);
            border: 1px solid rgba(251,191,36,0.15);
            border-radius: 10px;
            padding: 8px 12px;
            margin-bottom: 10px;
        }

        /* Edit mode indicator */
        .edit-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(167,139,250,0.08);
            border: 1px solid rgba(167,139,250,0.2);
            border-radius: 12px;
            padding: 10px 16px;
            margin-bottom: 18px;
            font-size: 0.85rem;
            color: var(--purple);
            font-weight: 600;
        }

        /* Add member search (for members panel) */
        .member-add-search {
            position: relative;
            margin-top: 12px;
        }
        .member-add-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px dashed rgba(52,211,153,0.3);
            border-radius: 10px;
            padding: 10px 16px;
            color: var(--text);
            font-size: 0.88rem;
            font-family: 'Tajawal', sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .member-add-input:focus { border-color: var(--emerald); }
        .member-add-input::placeholder { color: rgba(52,211,153,0.4); }
    </style>
</head>
<body>
<div class="page-wrapper">
<div class="main-layout">

    <!-- ===== العمود الأيسر: الفورم ===== -->
    <div class="left-panel">

        <div class="page-header">
            <h1><i class="fas fa-layer-group me-2"></i>إدارة الجروبات العائلية</h1>
            <p>إنشاء وتعديل الجروبات وأعضائها</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="alert-box alert-success"><i class="fas fa-check-circle"></i><?= $success ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        <div class="alert-box alert-error"><i class="fas fa-exclamation-circle"></i><?= $error ?></div>
        <?php endif; ?>

        <div class="glass-card">
            <!-- تبويبات -->
            <div class="tabs-bar">
                <button class="tab-btn active" id="tabNewBtn" onclick="switchTab('new')">
                    <i class="fas fa-plus-circle"></i> إنشاء جروب جديد
                </button>
                <button class="tab-btn" id="tabEditBtn" onclick="switchTab('edit')">
                    <i class="fas fa-edit"></i> تعديل جروب
                </button>
            </div>

            <!-- ===== تبويب إنشاء جروب ===== -->
            <div class="tab-panel active" id="panelNew">
                <div class="steps-bar">
                    <div class="step-item" id="s1"></div>
                    <div class="step-item" id="s2"></div>
                    <div class="step-item" id="s3"></div>
                    <div class="step-item" id="s4"></div>
                </div>

                <form method="POST" id="groupForm" onsubmit="return prepareSubmit()">
                    <input type="hidden" name="save_group" value="1">
                    <input type="hidden" name="leaders" id="leadersInput" value="[]">
                    <input type="hidden" name="members" id="membersInput" value="[]">

                    <!-- اسم الجروب -->
                    <div style="margin-bottom:22px;">
                        <div class="field-label"><i class="fas fa-tag"></i> اسم الجروب</div>
                        <input type="text" name="group_name" id="groupNameInput" class="group-name-input"
                               placeholder="مثال: عائلة أحمد، جروب الإخوة..." required
                               oninput="onNameChange(this.value)">
                    </div>

                    <!-- القائد -->
                    <div style="margin-bottom:22px;">
                        <div class="field-label"><i class="fas fa-crown"></i> القائد / القادة</div>
                        <div class="search-wrapper" style="position:relative;">
                            <div class="search-type-toggle">
                                <button type="button" class="search-type-btn active" id="btnName" onclick="setMode('name','new')">
                                    <i class="fas fa-user"></i> اسم
                                </button>
                                <div class="search-type-divider"></div>
                                <button type="button" class="search-type-btn" id="btnCode" onclick="setMode('code','new')">
                                    <i class="fas fa-barcode"></i> كود
                                </button>
                            </div>
                            <input type="text" id="leaderSearch" class="search-box"
                                   placeholder="ابحث بالاسم الثلاثي..."
                                   autocomplete="off">
                            <div class="search-results" id="leaderResults"></div>
                        </div>
                        <div class="tags-area" id="leadersDisplay">
                            <small style="color:var(--text-muted); font-size:0.8rem;">لم يتم اختيار قائد بعد</small>
                        </div>
                    </div>

                    <!-- الأعضاء -->
                    <div style="margin-bottom:22px;">
                        <div class="members-header">
                            <div class="members-label">
                                <i class="fas fa-users"></i> الأعضاء
                                <span class="count-badge" id="memberCountBadge">0</span>
                            </div>
                            <button type="button" onclick="clearAllMembers('new')" 
                                    style="background:none;border:none;color:var(--rose);font-size:0.78rem;cursor:pointer;font-family:'Tajawal',sans-serif;display:none;"
                                    id="clearAllBtn">
                                <i class="fas fa-trash-alt me-1"></i> حذف الكل
                            </button>
                        </div>

                        <div class="hint-row" id="leaderCountHint" style="display:none;">
                            <i class="fas fa-crown"></i>
                            القائد مُضاف تلقائياً ضمن الأعضاء ويُحسب كعضو
                        </div>

                        <div class="members-note">
                            <i class="fas fa-info-circle" style="color:var(--accent);"></i>
                            أبناء وأحفاد القائد الذكور + يمكنك إضافة أعضاء يدوياً
                        </div>

                        <div class="members-grid" id="membersList">
                            <div class="no-members-msg">
                                <i class="fas fa-arrow-up" style="display:block;margin-bottom:6px;opacity:0.4;"></i>
                                اختر قائداً لظهور الأعضاء
                            </div>
                        </div>

                        <!-- إضافة عضو يدوياً -->
                        <div class="member-add-search">
                            <div class="search-wrapper" style="position:relative;">
                                <input type="text" id="manualMemberSearch" class="member-add-input"
                                       placeholder="+ إضافة عضو يدوياً (بحث بالاسم أو الكود)..."
                                       autocomplete="off">
                                <div class="search-results" id="manualMemberResults"></div>
                            </div>
                        </div>
                    </div>

                    <!-- لون الجروب -->
                    <div style="margin-bottom:24px;">
                        <div class="field-label"><i class="fas fa-palette"></i> لون الجروب</div>
                        <div class="color-row">
                            <input type="color" name="group_color" id="colorPicker" value="#38bdf8"
                                   oninput="applyColor(this.value,'new')">
                            <div class="color-swatches">
                                <?php
                                $swatches = ['#38bdf8','#a78bfa','#34d399','#fbbf24','#fb7185','#f97316','#06b6d4','#84cc16','#e879f9','#f43f5e'];
                                foreach ($swatches as $i => $c):
                                ?>
                                <div class="swatch <?= $i===0?'active':'' ?>" 
                                     style="background:<?= $c ?>;" 
                                     data-color="<?= $c ?>"
                                     onclick="applyColor('<?= $c ?>','new')"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="group-preview">
                            <div class="preview-dot" id="previewDot" style="background:#38bdf8;"></div>
                            <div class="preview-name" id="previewName" style="color:#38bdf8;">اسم الجروب</div>
                            <div class="preview-counts" id="previewCounts">0 قائد · 0 عضو</div>
                        </div>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> إنشاء الجروب وحفظ الأعضاء
                    </button>
                </form>
            </div>

            <!-- ===== تبويب تعديل جروب ===== -->
            <div class="tab-panel" id="panelEdit">
                <div id="editSelectHint" style="text-align:center; padding:30px; color:var(--text-muted);">
                    <i class="fas fa-hand-pointer" style="font-size:2rem; display:block; margin-bottom:12px; opacity:0.4;"></i>
                    اختر جروباً من القائمة على اليمين للبدء بالتعديل
                </div>

                <div id="editFormWrapper" style="display:none;">
                    <div class="edit-indicator">
                        <i class="fas fa-edit"></i>
                        <span id="editGroupLabel">تعديل الجروب</span>
                        <button type="button" onclick="cancelEdit()" style="margin-right:auto; background:none; border:none; color:var(--rose); cursor:pointer; font-family:'Tajawal',sans-serif; font-size:0.78rem;">
                            <i class="fas fa-times me-1"></i> إلغاء
                        </button>
                    </div>

                    <form method="POST" id="editGroupForm" onsubmit="return prepareEditSubmit()">
                        <input type="hidden" name="edit_group" value="1">
                        <input type="hidden" name="edit_group_id" id="editGroupId">
                        <input type="hidden" name="edit_leaders" id="editLeadersInput" value="[]">
                        <input type="hidden" name="edit_members" id="editMembersInput" value="[]">

                        <!-- اسم الجروب -->
                        <div style="margin-bottom:22px;">
                            <div class="field-label"><i class="fas fa-tag"></i> اسم الجروب</div>
                            <input type="text" name="edit_group_name" id="editGroupNameInput" class="group-name-input"
                                   placeholder="اسم الجروب..." required
                                   oninput="onEditNameChange(this.value)">
                        </div>

                        <!-- القادة -->
                        <div style="margin-bottom:22px;">
                            <div class="field-label"><i class="fas fa-crown"></i> القادة</div>
                            <div class="search-wrapper" style="position:relative;">
                                <div class="search-type-toggle">
                                    <button type="button" class="search-type-btn active" id="editBtnName" onclick="setMode('name','edit')">
                                        <i class="fas fa-user"></i> اسم
                                    </button>
                                    <div class="search-type-divider"></div>
                                    <button type="button" class="search-type-btn" id="editBtnCode" onclick="setMode('code','edit')">
                                        <i class="fas fa-barcode"></i> كود
                                    </button>
                                </div>
                                <input type="text" id="editLeaderSearch" class="search-box"
                                       placeholder="إضافة قائد..."
                                       autocomplete="off">
                                <div class="search-results" id="editLeaderResults"></div>
                            </div>
                            <div class="tags-area" id="editLeadersDisplay">
                                <small style="color:var(--text-muted); font-size:0.8rem;">لا يوجد قادة</small>
                            </div>
                        </div>

                        <!-- الأعضاء -->
                        <div style="margin-bottom:22px;">
                            <div class="members-header">
                                <div class="members-label">
                                    <i class="fas fa-users"></i> الأعضاء
                                    <span class="count-badge" id="editMemberCountBadge">0</span>
                                </div>
                                <button type="button" onclick="clearAllMembers('edit')"
                                        style="background:none;border:none;color:var(--rose);font-size:0.78rem;cursor:pointer;font-family:'Tajawal',sans-serif;display:none;"
                                        id="editClearAllBtn">
                                    <i class="fas fa-trash-alt me-1"></i> حذف الكل
                                </button>
                            </div>

                            <div class="hint-row">
                                <i class="fas fa-info-circle"></i>
                                القائد يُحسب كعضو · يمكنك حذف أي عضو أو إضافة أعضاء جدد
                            </div>

                            <div class="members-grid" id="editMembersList">
                                <div class="no-members-msg">جاري التحميل...</div>
                            </div>

                            <!-- إضافة عضو يدوياً في التعديل -->
                            <div class="member-add-search">
                                <div class="search-wrapper" style="position:relative;">
                                    <input type="text" id="editManualMemberSearch" class="member-add-input"
                                           placeholder="+ إضافة عضو يدوياً..."
                                           autocomplete="off">
                                    <div class="search-results" id="editManualMemberResults"></div>
                                </div>
                            </div>
                        </div>

                        <!-- لون الجروب -->
                        <div style="margin-bottom:24px;">
                            <div class="field-label"><i class="fas fa-palette"></i> لون الجروب</div>
                            <div class="color-row">
                                <input type="color" name="edit_group_color" id="editColorPicker" value="#38bdf8"
                                       oninput="applyColor(this.value,'edit')">
                                <div class="color-swatches" id="editColorSwatches">
                                    <?php foreach ($swatches as $i => $c): ?>
                                    <div class="swatch <?= $i===0?'active':'' ?>"
                                         style="background:<?= $c ?>;"
                                         data-color="<?= $c ?>"
                                         onclick="applyColor('<?= $c ?>','edit')"></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="group-preview">
                                <div class="preview-dot" id="editPreviewDot" style="background:#38bdf8;"></div>
                                <div class="preview-name" id="editPreviewName" style="color:#38bdf8;">اسم الجروب</div>
                                <div class="preview-counts" id="editPreviewCounts">0 قائد · 0 عضو</div>
                            </div>
                        </div>

                        <button type="submit" class="btn-save edit-mode">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div><!-- /left-panel -->

    <!-- ===== العمود الأيمن: قائمة الجروبات ===== -->
    <div class="right-panel">
        <div class="glass-card" style="margin-top:70px;">
            <div class="section-title">
                <div class="icon-wrap"><i class="fas fa-list"></i></div>
                الجروبات الموجودة
                <span style="margin-right:auto; background:rgba(255,255,255,0.08); border-radius:20px; padding:2px 10px; font-size:0.78rem; color:var(--text-muted);">
                    <?= count($groups) ?>
                </span>
            </div>

            <div style="margin-bottom:12px;">
                <a href="groups_view.php" class="btn-action" style="width:100%;justify-content:center;">
                    <i class="fas fa-eye"></i> عرض كل الجروبات
                </a>
            </div>

            <?php if (empty($groups)): ?>
            <div class="empty-state">
                <i class="fas fa-layer-group d-block"></i>
                لا توجد جروبات بعد
            </div>
            <?php else: ?>
            <div class="groups-list">
                <?php foreach ($groups as $g): ?>
                <div class="group-row" style="border-right: 4px solid <?= htmlspecialchars($g['group_color']) ?>33;">
                    <div class="group-row-dot" style="background:<?= htmlspecialchars($g['group_color']) ?>18;">
                        <i class="fas fa-users" style="color:<?= htmlspecialchars($g['group_color']) ?>;"></i>
                    </div>
                    <div class="group-row-info">
                        <div class="group-row-name" style="color:<?= htmlspecialchars($g['group_color']) ?>;">
                            <?= htmlspecialchars($g['group_name']) ?>
                        </div>
                        <div class="group-row-meta">
                            <span><i class="fas fa-crown" style="color:var(--gold);"></i> <?= $g['leader_count'] ?> قائد</span>
                            <span><i class="fas fa-users" style="color:var(--accent);"></i> <?= $g['member_count'] ?> عضو</span>
                        </div>
                    </div>
                    <div class="group-row-actions">
                        <a href="groups_view.php?id=<?= $g['id'] ?>" class="btn-action" title="عرض">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button type="button" class="btn-action edit" title="تعديل"
                                onclick="loadGroupForEdit(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['group_name'])) ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="groups_manage.php?delete_group=<?= $g['id'] ?>"
                           class="btn-action danger"
                           onclick="return confirm('هل تريد حذف هذا الجروب؟')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /right-panel -->

</div><!-- /main-layout -->
</div><!-- /page-wrapper -->

<script>
// ============================================================
// State
// ============================================================
let selectedLeaders = [];
let allMembers = {};
let searchMode = 'name'; // for new form
let editSearchMode = 'name'; // for edit form

let editSelectedLeaders = [];
let editAllMembers = {};

// ============================================================
// Tab switching
// ============================================================
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    if (tab === 'new') {
        document.getElementById('tabNewBtn').classList.add('active');
        document.getElementById('panelNew').classList.add('active');
    } else {
        document.getElementById('tabEditBtn').classList.add('active');
        document.getElementById('panelEdit').classList.add('active');
    }
}

// ============================================================
// Search mode toggle
// ============================================================
function setMode(mode, panel) {
    if (panel === 'new') {
        searchMode = mode;
        document.getElementById('btnName').classList.toggle('active', mode==='name');
        document.getElementById('btnCode').classList.toggle('active', mode==='code');
        const box = document.getElementById('leaderSearch');
        box.placeholder = mode==='name' ? 'ابحث بالاسم الثلاثي...' : 'ادخل رقم الكود...';
        box.value = '';
        document.getElementById('leaderResults').style.display = 'none';
    } else {
        editSearchMode = mode;
        document.getElementById('editBtnName').classList.toggle('active', mode==='name');
        document.getElementById('editBtnCode').classList.toggle('active', mode==='code');
        const box = document.getElementById('editLeaderSearch');
        box.placeholder = mode==='name' ? 'ابحث بالاسم...' : 'ادخل رقم الكود...';
        box.value = '';
        document.getElementById('editLeaderResults').style.display = 'none';
    }
}

// ============================================================
// Generic search function
// ============================================================
async function performSearch(q) {
    if (q.length < 2) return [];
    const res = await fetch(`groups_manage.php?action=search_person&q=${encodeURIComponent(q)}`);
    return await res.json();
}

function renderSearchResults(results, container, onSelect, currentMembers) {
    if (!results.length) { container.innerHTML = '<div class="search-item" style="color:var(--text-muted);">لا توجد نتائج</div>'; container.style.display='block'; return; }
    container.innerHTML = results.map(p => {
        const gIcon = p.gender === 'Male' ? '♂' : '♀';
        const alreadyIn = currentMembers[p.id] !== undefined;
        return `<div class="search-item ${alreadyIn?'selected':''}" onclick="${alreadyIn?'':onSelect+'('+p.id+',\''+p.full_name.replace(/'/g,"\\'")+'\')'}" >
            <div class="name-col">
                <div class="name-main">${gIcon} ${p.full_name}</div>
                ${p.ration_number ? '<div class="name-code">كود: '+p.ration_number+'</div>' : ''}
            </div>
            ${alreadyIn ? '<span style="font-size:0.72rem;color:var(--emerald);">مُضاف</span>' : ''}
        </div>`;
    }).join('');
    container.style.display = 'block';
}

// ============================================================
// NEW FORM: Leader search
// ============================================================
let leaderSearchTimer;
document.getElementById('leaderSearch').addEventListener('input', function() {
    clearTimeout(leaderSearchTimer);
    const q = this.value.trim();
    if (!q) { document.getElementById('leaderResults').style.display='none'; return; }
    leaderSearchTimer = setTimeout(async () => {
        const results = await performSearch(q);
        renderSearchResults(results, document.getElementById('leaderResults'), 'addLeader', allMembers);
    }, 280);
});
document.getElementById('leaderSearch').addEventListener('blur', () => {
    setTimeout(() => { document.getElementById('leaderResults').style.display='none'; }, 200);
});

async function addLeader(id, name) {
    if (selectedLeaders.find(l => l.id == id)) return;
    const res = await performSearch(id.toString());
    const person = res.find ? res : null;
    // fetch person gender
    let gender = 'Male';
    try {
        const r = await fetch(`groups_manage.php?action=search_person&q=${id}`);
        const data = await r.json();
        if (data[0]) gender = data[0].gender;
    } catch(e) {}
    
    selectedLeaders.push({id, name, gender});
    document.getElementById('leaderSearch').value = '';
    document.getElementById('leaderResults').style.display = 'none';
    allMembers[id] = { id, full_name: name, gender, status: 'Alive', isLeader: true };
    renderLeaders(); renderMembers(); updateSteps();
    fetchDescendants(id, allMembers, () => renderMembers());
}

function removeLeader(id) {
    selectedLeaders = selectedLeaders.filter(l => l.id != id);
    delete allMembers[id];
    allMembers = {};
    selectedLeaders.forEach(l => {
        allMembers[l.id] = { id: l.id, full_name: l.name, gender: l.gender, status: 'Alive', isLeader: true };
    });
    renderLeaders(); renderMembers(); updateSteps();
    Promise.all(selectedLeaders.map(l => new Promise(resolve => {
        fetchDescendants(l.id, allMembers, () => { renderMembers(); resolve(); });
    })));
}

// ============================================================
// NEW FORM: Manual member search
// ============================================================
let manualTimer;
document.getElementById('manualMemberSearch').addEventListener('input', function() {
    clearTimeout(manualTimer);
    const q = this.value.trim();
    if (!q) { document.getElementById('manualMemberResults').style.display='none'; return; }
    manualTimer = setTimeout(async () => {
        const results = await performSearch(q);
        renderSearchResults(results, document.getElementById('manualMemberResults'), 'addManualMember', allMembers);
    }, 280);
});
document.getElementById('manualMemberSearch').addEventListener('blur', () => {
    setTimeout(() => { document.getElementById('manualMemberResults').style.display='none'; }, 200);
});

function addManualMember(id, name) {
    if (allMembers[id]) return;
    allMembers[id] = { id, full_name: name, gender: 'Male', status: 'Alive', isLeader: false, isManual: true };
    document.getElementById('manualMemberSearch').value = '';
    document.getElementById('manualMemberResults').style.display = 'none';
    renderMembers();
}

// ============================================================
// Fetch descendants
// ============================================================
async function fetchDescendants(pid, membersObj, callback) {
    try {
        const res = await fetch(`groups_manage.php?action=get_descendants&person_id=${pid}`);
        const desc = await res.json();
        desc.forEach(p => {
            if (!membersObj[p.id]) membersObj[p.id] = { ...p, isLeader: false };
        });
        callback();
    } catch(e) {}
}

// ============================================================
// Render leaders (new form)
// ============================================================
function renderLeaders() {
    const c = document.getElementById('leadersDisplay');
    if (!selectedLeaders.length) {
        c.innerHTML = '<small style="color:var(--text-muted);font-size:0.8rem;">لم يتم اختيار قائد بعد</small>';
        return;
    }
    c.innerHTML = selectedLeaders.map(l =>
        `<div class="tag tag-leader">
            <i class="fas fa-crown" style="font-size:0.75rem;"></i> ${l.name}
            <div class="tag-remove" onclick="removeLeader(${l.id})"><i class="fas fa-times"></i></div>
        </div>`
    ).join('');
}

// ============================================================
// Render members (new form)
// ============================================================
function renderMembers() {
    const members = Object.values(allMembers);
    const badge   = document.getElementById('memberCountBadge');
    const list    = document.getElementById('membersList');
    const hint    = document.getElementById('leaderCountHint');
    const clearBtn = document.getElementById('clearAllBtn');
    const nonLeaderCount = members.filter(m => !m.isLeader).length;

    badge.textContent = members.length;
    hint.style.display = selectedLeaders.length ? 'flex' : 'none';
    clearBtn.style.display = nonLeaderCount > 0 ? 'block' : 'none';
    updatePreview();

    if (!members.length) {
        list.innerHTML = '<div class="no-members-msg"><i class="fas fa-arrow-up" style="display:block;margin-bottom:6px;opacity:0.4;"></i>اختر قائداً لظهور الأعضاء</div>';
        return;
    }
    const sorted = [...members].sort((a,b) => (b.isLeader?1:0)-(a.isLeader?1:0));
    list.innerHTML = sorted.map(p => buildMemberCard(p, 'removeMember')).join('');
}

function buildMemberCard(p, removeFn) {
    const gIcon = p.gender === 'Male'
        ? '<i class="fas fa-mars" style="color:var(--accent);font-size:0.75rem;"></i>'
        : '<i class="fas fa-venus" style="color:var(--rose);font-size:0.75rem;"></i>';
    const leaderBadge = p.isLeader ? '<span class="mc-leader-badge"><i class="fas fa-crown"></i></span>' : '';
    const deadBadge = p.status === 'Deceased' ? '<span style="font-size:0.65rem;color:var(--text-muted);">متوفى</span>' : '';
    return `<div class="member-card ${p.isLeader?'is-leader':''}">
        ${gIcon}
        <div class="mc-name" title="${p.full_name}">${p.full_name}</div>
        ${leaderBadge}${deadBadge}
        <div class="mc-remove" onclick="${removeFn}(${p.id})" title="${p.isLeader?'حذف القائد':'حذف العضو'}">
            <i class="fas fa-times"></i>
        </div>
    </div>`;
}

function removeMember(id) {
    if (allMembers[id] && allMembers[id].isLeader) { removeLeader(id); return; }
    delete allMembers[id];
    renderMembers();
}

function clearAllMembers(panel) {
    if (panel === 'new') {
        Object.keys(allMembers).forEach(id => { if (!allMembers[id].isLeader) delete allMembers[id]; });
        renderMembers();
    } else {
        Object.keys(editAllMembers).forEach(id => { if (!editAllMembers[id].isLeader) delete editAllMembers[id]; });
        renderEditMembers();
    }
}

// ============================================================
// Preview & color (new form)
// ============================================================
function updatePreview() {
    const name = document.getElementById('groupNameInput').value || 'اسم الجروب';
    const color = document.getElementById('colorPicker').value;
    document.getElementById('previewName').textContent = name;
    document.getElementById('previewDot').style.background = color;
    document.getElementById('previewName').style.color = color;
    document.getElementById('previewCounts').textContent = `${selectedLeaders.length} قائد · ${Object.keys(allMembers).length} عضو`;
}

function applyColor(hex, panel) {
    if (panel === 'new') {
        document.getElementById('colorPicker').value = hex;
        document.getElementById('previewDot').style.background = hex;
        document.getElementById('previewName').style.color = hex;
        document.querySelectorAll('#panelNew .swatch').forEach(s => s.classList.toggle('active', s.dataset.color===hex));
    } else {
        document.getElementById('editColorPicker').value = hex;
        document.getElementById('editPreviewDot').style.background = hex;
        document.getElementById('editPreviewName').style.color = hex;
        document.querySelectorAll('#editColorSwatches .swatch').forEach(s => s.classList.toggle('active', s.dataset.color===hex));
    }
}

function onNameChange(val) {
    document.getElementById('previewName').textContent = val || 'اسم الجروب';
    updateSteps();
}

function updateSteps() {
    const hasName = document.getElementById('groupNameInput').value.trim().length > 0;
    const hasLeader = selectedLeaders.length > 0;
    const states = [
        hasName ? 'done' : 'active',
        hasName ? (hasLeader ? 'done' : 'active') : '',
        hasLeader ? 'done' : '',
        (hasName && hasLeader) ? 'active' : ''
    ];
    ['s1','s2','s3','s4'].forEach((id,i) => {
        document.getElementById(id).className = 'step-item ' + (states[i] || '');
    });
}

function prepareSubmit() {
    if (!selectedLeaders.length) { alert('يرجى اختيار قائد واحد على الأقل'); return false; }
    document.getElementById('leadersInput').value = JSON.stringify(selectedLeaders.map(l => l.id));
    document.getElementById('membersInput').value = JSON.stringify(Object.keys(allMembers).map(Number));
    return true;
}

// ============================================================
// EDIT FORM: Load group data
// ============================================================
async function loadGroupForEdit(gid, gname) {
    switchTab('edit');
    document.getElementById('editSelectHint').style.display = 'none';
    document.getElementById('editFormWrapper').style.display = 'block';
    document.getElementById('editGroupLabel').textContent = 'تعديل: ' + gname;
    document.getElementById('editGroupId').value = gid;
    document.getElementById('editMembersList').innerHTML = '<div class="no-members-msg">جاري التحميل...</div>';

    try {
        const res = await fetch(`groups_manage.php?action=get_group_data&group_id=${gid}`);
        const data = await res.json();
        
        // Set name and color
        document.getElementById('editGroupNameInput').value = data.group.group_name;
        const color = data.group.group_color || '#38bdf8';
        applyColor(color, 'edit');
        onEditNameChange(data.group.group_name);

        // Build leaders
        editSelectedLeaders = data.leaders.map(l => ({ id: parseInt(l.id), name: l.full_name, gender: l.gender }));
        
        // Build members map
        editAllMembers = {};
        const leaderIds = data.leaders.map(l => parseInt(l.id));
        data.members.forEach(m => {
            editAllMembers[m.id] = { ...m, id: parseInt(m.id), isLeader: leaderIds.includes(parseInt(m.id)) };
        });
        // Make sure leaders are in members
        data.leaders.forEach(l => {
            if (!editAllMembers[l.id]) {
                editAllMembers[l.id] = { ...l, id: parseInt(l.id), isLeader: true };
            } else {
                editAllMembers[l.id].isLeader = true;
            }
        });

        renderEditLeaders();
        renderEditMembers();
    } catch(e) {
        document.getElementById('editMembersList').innerHTML = '<div class="no-members-msg">خطأ في التحميل</div>';
    }
}

function cancelEdit() {
    document.getElementById('editSelectHint').style.display = 'block';
    document.getElementById('editFormWrapper').style.display = 'none';
    editSelectedLeaders = [];
    editAllMembers = {};
}

// ============================================================
// EDIT FORM: Leader search
// ============================================================
let editLeaderSearchTimer;
document.getElementById('editLeaderSearch').addEventListener('input', function() {
    clearTimeout(editLeaderSearchTimer);
    const q = this.value.trim();
    if (!q) { document.getElementById('editLeaderResults').style.display='none'; return; }
    editLeaderSearchTimer = setTimeout(async () => {
        const results = await performSearch(q);
        renderSearchResults(results, document.getElementById('editLeaderResults'), 'addEditLeader', editAllMembers);
    }, 280);
});
document.getElementById('editLeaderSearch').addEventListener('blur', () => {
    setTimeout(() => { document.getElementById('editLeaderResults').style.display='none'; }, 200);
});

async function addEditLeader(id, name) {
    if (editSelectedLeaders.find(l => l.id == id)) return;
    let gender = 'Male';
    try {
        const r = await fetch(`groups_manage.php?action=search_person&q=${id}`);
        const data = await r.json();
        if (data[0]) gender = data[0].gender;
    } catch(e) {}
    
    editSelectedLeaders.push({id: parseInt(id), name, gender});
    document.getElementById('editLeaderSearch').value = '';
    document.getElementById('editLeaderResults').style.display = 'none';
    if (!editAllMembers[id]) {
        editAllMembers[id] = { id: parseInt(id), full_name: name, gender, status: 'Alive', isLeader: true };
    } else {
        editAllMembers[id].isLeader = true;
    }
    renderEditLeaders();
    renderEditMembers();
    fetchDescendants(id, editAllMembers, () => renderEditMembers());
}

function removeEditLeader(id) {
    editSelectedLeaders = editSelectedLeaders.filter(l => l.id != id);
    if (editAllMembers[id]) editAllMembers[id].isLeader = false;
    renderEditLeaders();
    renderEditMembers();
}

// ============================================================
// EDIT FORM: Manual member search
// ============================================================
let editManualTimer;
document.getElementById('editManualMemberSearch').addEventListener('input', function() {
    clearTimeout(editManualTimer);
    const q = this.value.trim();
    if (!q) { document.getElementById('editManualMemberResults').style.display='none'; return; }
    editManualTimer = setTimeout(async () => {
        const results = await performSearch(q);
        renderSearchResults(results, document.getElementById('editManualMemberResults'), 'addEditManualMember', editAllMembers);
    }, 280);
});
document.getElementById('editManualMemberSearch').addEventListener('blur', () => {
    setTimeout(() => { document.getElementById('editManualMemberResults').style.display='none'; }, 200);
});

function addEditManualMember(id, name) {
    if (editAllMembers[id]) return;
    editAllMembers[id] = { id: parseInt(id), full_name: name, gender: 'Male', status: 'Alive', isLeader: false, isManual: true };
    document.getElementById('editManualMemberSearch').value = '';
    document.getElementById('editManualMemberResults').style.display = 'none';
    renderEditMembers();
}

// ============================================================
// EDIT FORM: Render
// ============================================================
function renderEditLeaders() {
    const c = document.getElementById('editLeadersDisplay');
    if (!editSelectedLeaders.length) {
        c.innerHTML = '<small style="color:var(--text-muted); font-size:0.8rem;">لا يوجد قادة</small>';
        return;
    }
    c.innerHTML = editSelectedLeaders.map(l =>
        `<div class="tag tag-leader">
            <i class="fas fa-crown" style="font-size:0.75rem;"></i> ${l.name}
            <div class="tag-remove" onclick="removeEditLeader(${l.id})"><i class="fas fa-times"></i></div>
        </div>`
    ).join('');
}

function renderEditMembers() {
    const members = Object.values(editAllMembers);
    const badge    = document.getElementById('editMemberCountBadge');
    const list     = document.getElementById('editMembersList');
    const clearBtn = document.getElementById('editClearAllBtn');
    const nonLeaderCount = members.filter(m => !m.isLeader).length;

    badge.textContent = members.length;
    clearBtn.style.display = nonLeaderCount > 0 ? 'block' : 'none';
    updateEditPreview();

    if (!members.length) {
        list.innerHTML = '<div class="no-members-msg">لا يوجد أعضاء</div>';
        return;
    }
    const sorted = [...members].sort((a,b) => (b.isLeader?1:0)-(a.isLeader?1:0));
    list.innerHTML = sorted.map(p => buildMemberCard(p, 'removeEditMember')).join('');
}

function removeEditMember(id) {
    if (editAllMembers[id] && editAllMembers[id].isLeader) {
        if (confirm('هذا الشخص قائد. هل تريد إزالته كقائد وعضو؟')) {
            removeEditLeader(id);
            delete editAllMembers[id];
            renderEditLeaders();
            renderEditMembers();
        }
        return;
    }
    delete editAllMembers[id];
    renderEditMembers();
}

function updateEditPreview() {
    const name = document.getElementById('editGroupNameInput').value || 'اسم الجروب';
    const color = document.getElementById('editColorPicker').value;
    document.getElementById('editPreviewName').textContent = name;
    document.getElementById('editPreviewDot').style.background = color;
    document.getElementById('editPreviewName').style.color = color;
    document.getElementById('editPreviewCounts').textContent = `${editSelectedLeaders.length} قائد · ${Object.keys(editAllMembers).length} عضو`;
}

function onEditNameChange(val) {
    document.getElementById('editPreviewName').textContent = val || 'اسم الجروب';
    updateEditPreview();
}

function prepareEditSubmit() {
    document.getElementById('editLeadersInput').value = JSON.stringify(editSelectedLeaders.map(l => l.id));
    document.getElementById('editMembersInput').value = JSON.stringify(Object.keys(editAllMembers).map(Number));
    return true;
}

// ============================================================
// Init
// ============================================================
updateSteps();
</script>

</body>
</html>