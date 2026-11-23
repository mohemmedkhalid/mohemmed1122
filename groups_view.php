<?php
require_once 'header.php';
$lang = get_lang();
$isEn = ($lang === 'en');
$dir  = $isEn ? 'ltr' : 'rtl';

// ============================================================
// كشانەوەی داتای گروپێكی دیاریكراو
// ============================================================
$selected_group_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// كشانەوەی هەموو گروپەكان
$groups = [];
$gres = $mysqli->query("
    SELECT g.*,
        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
        (SELECT COUNT(*) FROM group_leaders WHERE group_id = g.id) as leader_count
    FROM family_groups g
    ORDER BY g.created_at DESC
");
while ($gr = $gres->fetch_assoc()) $groups[] = $gr;
$gres->free();

$group_detail = null;
$group_members_data = [];
$leaders_data = [];

if ($selected_group_id) {
    $res = $mysqli->query("SELECT * FROM family_groups WHERE id = $selected_group_id");
    $group_detail = $res->fetch_assoc();

    if ($group_detail) {
        // كشانەوەی سەرۆكان
        // كشانەوەی سەرۆكان
$lres = $mysqli->query("
    SELECT p.id, p.full_name, p.full_name_en, p.gender, p.status, p.birth_date, p.mother_id,
           f.full_name as father_name, f.full_name_en as father_name_en,
           gf.full_name as grandfather_name, gf.full_name_en as grandfather_name_en
    FROM group_leaders gl
    JOIN people2 p ON p.id = gl.person_id
    LEFT JOIN people2 f ON p.father_id = f.id
    LEFT JOIN people2 gf ON f.father_id = gf.id
    WHERE gl.group_id = $selected_group_id
");
        while ($lr = $lres->fetch_assoc()) $leaders_data[] = $lr;
        $lres->free();
        $leader_ids = array_column($leaders_data, 'id');

        // كشانەوەی ئەندامان — بەبێ سەرۆكان — مناوی سێ‌ قات
       // كشانەوەی ئەندامان — بەبێ سەرۆكان
$mres = $mysqli->query("
    SELECT p.id, p.full_name, p.full_name_en, p.gender, p.birth_date, p.status, p.father_id,
           m.full_name as mother_name, m.full_name_en as mother_name_en,
           f.full_name as father_name, f.full_name_en as father_name_en,
           gf.full_name as grandfather_name, gf.full_name_en as grandfather_name_en
    FROM group_members gm
    JOIN people2 p ON p.id = gm.person_id
    LEFT JOIN people2 m ON m.id = p.mother_id
    LEFT JOIN people2 f ON p.father_id = f.id
    LEFT JOIN people2 gf ON f.father_id = gf.id
    WHERE gm.group_id = $selected_group_id
    ORDER BY p.full_name
");
        while ($mr = $mres->fetch_assoc()) {
            if (!in_array($mr['id'], $leader_ids)) {
                $group_members_data[] = $mr;
            }
        }
        $mres->free();

        $group_detail['leaders'] = $leaders_data;
        $group_detail['members'] = $group_members_data;
    }
}

// هەلبژاردنی ناوی سێ‌ قات (مناو باپ باپكال)
function getTripleName($name) {
    $parts = explode(' ', trim($name));
    $triple = array_slice($parts, 0, 3);
    return implode(' ', $triple);
}
?>
<!DOCTYPE html>
<html lang="<?= $isEn ? 'en' : 'ku' ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= $isEn ? 'Family Groups' : 'گروپەكانی خانەدانی' ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep:    #060810;
            --bg-panel:   #0c1018;
            --bg-card:    rgba(255,255,255,0.038);
            --bg-hover:   rgba(255,255,255,0.072);
            --border:     rgba(255,255,255,0.08);
            --border-hi:  rgba(255,255,255,0.18);
            --text:       #edf2f7;
            --muted:      rgba(237,242,247,0.42);
            --gold:       #f6c941;
            --emerald:    #34d399;
            --rose:       #fb7185;
            --sky:        #38bdf8;
            --violet:     #a78bfa;
            --radius-xl:  22px;
            --radius-lg:  16px;
            --radius-md:  12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100%;
            background: var(--bg-deep);
            color: var(--text);
            font-family: 'Tajawal', sans-serif;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior: none;
        }

        /* ===== خلفية جماليه ===== */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 70% 55% at 15% 5%,  rgba(56,189,248,0.07) 0%, transparent 65%),
                radial-gradient(ellipse 55% 70% at 85% 90%, rgba(167,139,250,0.06) 0%, transparent 65%),
                radial-gradient(ellipse 40% 40% at 50% 50%, rgba(246,201,65,0.025) 0%, transparent 70%);
        }
        /* شبكة نقطية خفية */
        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: radial-gradient(circle, rgba(255,255,255,0.045) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        .page-wrap { position: relative; z-index: 1; min-height: 100vh; padding-bottom: 56px; }

        /* ===== ناو بار ===== */
        .top-nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(6,8,16,0.82);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--border);
            padding: 13px 18px;
            display: flex; align-items: center; gap: 12px;
        }
        .nav-logo {
            font-size: 1.25rem; font-weight: 900; letter-spacing: -0.5px;
            background: linear-gradient(135deg, var(--gold), var(--sky));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .nav-subtitle {
            font-size: 0.7rem; color: var(--muted); font-weight: 400; margin-top: 1px;
        }
        .nav-logo-wrap { flex: 1; }
        .nav-add-btn {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--sky), var(--violet));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1rem; text-decoration: none;
            box-shadow: 0 4px 18px rgba(56,189,248,0.35);
            transition: transform 0.2s, box-shadow 0.2s;
            flex-shrink: 0;
        }
        .nav-add-btn:hover { transform: scale(1.1); box-shadow: 0 6px 24px rgba(56,189,248,0.5); color: white; }

        /* ===== صفحة القائمة ===== */
        .list-page { padding: 20px 16px 0; }

        .hero-banner {
            background: linear-gradient(135deg, rgba(56,189,248,0.12), rgba(167,139,250,0.10), rgba(246,201,65,0.07));
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-xl);
            padding: 24px 20px 20px;
            margin-bottom: 20px;
            text-align: center;
            position: relative; overflow: hidden;
        }
        .hero-banner::before {
            content: '𐎟';
            position: absolute; top: -10px; left: 14px;
            font-size: 5rem; opacity: 0.04; color: white;
        }
        .hero-title {
            font-size: 1.55rem; font-weight: 900; letter-spacing: -0.5px;
            background: linear-gradient(120deg, var(--gold), var(--sky), var(--violet));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; margin-bottom: 6px;
        }
        .hero-desc { font-size: 0.82rem; color: var(--muted); }
        .hero-stats {
            display: flex; justify-content: center; gap: 24px; margin-top: 16px;
        }
        .hero-stat { text-align: center; }
        .hero-stat-num { font-size: 1.4rem; font-weight: 900; color: var(--gold); }
        .hero-stat-lbl { font-size: 0.68rem; color: var(--muted); }

        /* شريط البحث */
        .search-wrap { position: relative; margin-bottom: 14px; }
        .search-icon { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 0.9rem; pointer-events: none; }
        .search-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 12px 44px 12px 18px;
            color: var(--text);
            font-family: 'Tajawal', sans-serif;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .search-input:focus { border-color: rgba(56,189,248,0.45); background: rgba(255,255,255,0.08); }
        .search-input::placeholder { color: var(--muted); }

        /* عداد الجروبات */
        .groups-count { font-size: 0.74rem; color: var(--muted); margin-bottom: 14px; padding: 0 4px; }

        /* ===== بطاقة الجروب ===== */
        .group-card {
            display: flex; align-items: center; gap: 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 16px 16px;
            margin-bottom: 10px;
            text-decoration: none; color: var(--text);
            position: relative; overflow: hidden;
            transition: background 0.22s, border-color 0.22s, transform 0.22s;
        }
        .group-card-accent {
            position: absolute; right: 0; top: 0; bottom: 0; width: 4px;
            border-radius: 0 var(--radius-xl) var(--radius-xl) 0;
        }
        .group-card:active { background: var(--bg-hover); transform: scale(0.99); }

        .group-icon-wrap {
            width: 50px; height: 50px; border-radius: var(--radius-lg);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .group-card-body { flex: 1; min-width: 0; }
        .group-card-name { font-size: 1.05rem; font-weight: 800; margin-bottom: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .group-card-meta { display: flex; gap: 14px; font-size: 0.74rem; color: var(--muted); flex-wrap: wrap; }
        .group-card-meta span { display: flex; align-items: center; gap: 4px; }
        .group-badge {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 3px 11px;
            font-size: 0.75rem; font-weight: 800;
            flex-shrink: 0; white-space: nowrap;
        }
        .group-arrow { color: var(--muted); font-size: 0.82rem; flex-shrink: 0; }

        /* ===== صفحة تفاصيل ===== */
        .detail-page { padding: 0; }

        .detail-topbar {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px;
        }
        .back-btn {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.07);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text); text-decoration: none;
            font-size: 0.85rem; flex-shrink: 0;
            transition: background 0.18s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.14); color: var(--text); }

        /* هيدر الجروب */
        .group-hero {
            margin: 0 16px 18px;
            border-radius: var(--radius-xl);
            padding: 26px 20px;
            position: relative; overflow: hidden;
        }
        .group-hero-bg {
            position: absolute; inset: 0; z-index: 0;
        }
        .group-hero-bg::after {
            content: '';
            position: absolute; inset: 0;
            background: rgba(6,8,16,0.55);
        }
        .group-hero-content { position: relative; z-index: 1; }
        .hero-group-name {
            font-size: 1.8rem; font-weight: 900; color: white;
            text-shadow: 0 3px 16px rgba(0,0,0,0.7);
            margin-bottom: 4px; letter-spacing: -0.5px;
        }
        .hero-group-sub { font-size: 0.78rem; color: rgba(255,255,255,0.6); margin-bottom: 14px; }

        /* قادة */
        .leader-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
        .leader-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 0.78rem; font-weight: 700; color: white;
        }
        .leader-chip i { color: var(--gold); }

        /* إحصائيات مختصرة */
        .stats-row {
            display: flex; gap: 10px;
            padding: 0 16px; margin-bottom: 16px;
        }
        .stat-pill {
            flex: 1; background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 12px 10px; text-align: center;
        }
        .stat-pill-num { font-size: 1.4rem; font-weight: 900; }
        .stat-pill-lbl { font-size: 0.62rem; color: var(--muted); margin-top: 2px; }

        /* شريط البحث في التفاصيل */
        .detail-toolbar { padding: 0 16px; margin-bottom: 14px; }
        .detail-search {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 11px 18px;
            color: var(--text);
            font-family: 'Tajawal', sans-serif;
            font-size: 0.88rem; outline: none;
            transition: border-color 0.2s;
            margin-bottom: 10px;
        }
        .detail-search:focus { border-color: rgba(56,189,248,0.4); }
        .detail-search::placeholder { color: var(--muted); }

        .filter-row { display: flex; gap: 7px; flex-wrap: wrap; }
        .fpill {
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 7px 14px;
            font-size: 0.74rem; color: var(--muted);
            cursor: pointer; font-family: 'Tajawal', sans-serif;
            transition: all 0.18s; white-space: nowrap;
        }
        .fpill.active, .fpill:hover { background: rgba(255,255,255,0.15); color: var(--text); border-color: rgba(255,255,255,0.28); }

        /* ===== بطاقة العضو — عمود واحد ===== */
        .members-list { padding: 0 16px; }

        .member-row {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 15px 15px;
            margin-bottom: 10px;
            position: relative; overflow: hidden;
            transition: background 0.2s, border-color 0.2s;
            display: flex; align-items: flex-start; gap: 13px;
        }
        .member-row:active { background: var(--bg-hover); }
        .member-row-accent {
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }

        .member-avatar {
            width: 52px; height: 52px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
            position: relative;
        }
        .member-avatar .status-dot {
            position: absolute; bottom: -2px; left: -2px;
            width: 12px; height: 12px; border-radius: 50%;
            border: 2px solid var(--bg-panel);
        }

        .member-body { flex: 1; min-width: 0; }

        /* اسم ثلاثي بارز */
        .member-triple-name {
            font-size: 1.02rem; font-weight: 800;
            line-height: 1.3; margin-bottom: 6px;
            color: var(--text);
        }

        .member-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 6px; }
        .mchip {
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(255,255,255,0.06);
            border-radius: 50px; padding: 3px 9px;
            font-size: 0.68rem; color: var(--muted);
        }
        .mchip.birth  { color: var(--gold); background: rgba(246,201,65,0.1); }
        .mchip.mother { color: var(--violet); background: rgba(167,139,250,0.1); }
        .mchip.male   { color: var(--sky);  background: rgba(56,189,248,0.1); }
        .mchip.female { color: var(--rose); background: rgba(251,113,133,0.1); }
        .mchip.alive  { color: var(--emerald); background: rgba(52,211,153,0.1); }
        .mchip.dead   { color: rgba(148,163,184,0.7); }

        /* زر الشجرة */
        .tree-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgb(0, 124, 161);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 6px 13px;
            font-size: 0.73rem; color: var(--muted);
            text-decoration: none;
            transition: all 0.18s; white-space: nowrap;
            font-family: 'Tajawal', sans-serif;
            margin-top: 4px;
            color: white;
        }
        .tree-btn:hover { background: rgba(255,255,255,0.14); color: var(--text); border-color: var(--border-hi); }

        /* حالة فارغة */
        .empty-box {
            text-align: center; padding: 50px 20px; color: var(--muted);
        }
        .empty-box i { font-size: 2.5rem; display: block; margin-bottom: 14px; opacity: 0.3; }
        .empty-box h4 { font-size: 1rem; color: rgba(255,255,255,0.35); margin-bottom: 8px; }
        .empty-box p { font-size: 0.8rem; }

        .hidden { display: none !important; }

        /* ===== تمریر کردن نیو ===== */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .anim { animation: fadeUp 0.32s ease both; }
        .anim-d1 { animation-delay: 0.04s; }
        .anim-d2 { animation-delay: 0.08s; }
        .anim-d3 { animation-delay: 0.12s; }

        /* سكرول */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }
        /* CONTROLS */
#controls {
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 100;
    display: flex;
    gap: 8px;
}
.btn-ctrl {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    width: 42px; height: 42px;
    border-radius: 10px;
    cursor: pointer;
    backdrop-filter: blur(10px);
    font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
.btn-ctrl:hover, .btn-ctrl:active {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.4);
}

/* ZOOM BUTTONS */
#zoom-controls {
    position: fixed;
    bottom: 20px;
    left: 20px;
    z-index: 200;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.zoom-btn {
    width: 36px; height: 36px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px;
    color: white;
    font-size: 18px;
    font-weight: 300;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(10px);
    transition: all 0.15s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
.zoom-btn:hover { background: rgba(255,255,255,0.18); }

/* SEARCH TOGGLE BTN */
#search-toggle-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 46px; height: 46px;
    background: rgba(246,201,14,0.15);
    border: 1px solid rgba(246,201,14,0.4);
    border-radius: 50%;
    color: var(--focused-border-color);
    font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(10px);
    z-index: 201;
    transition: all 0.2s;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
#search-toggle-btn:hover {
    background: rgba(246,201,14,0.3);
    transform: scale(1.05);
}
    </style>
</head>
<body>

<div id="controls">
        <a href="index.php" class="btn-ctrl" title="<?= $isEn ? 'Home' : 'الرئيسية' ?>"><i class="fas fa-home"></i></a>
        <a href="set_lang.php?lang=<?= $isEn ? 'ku' : 'en' ?>&back=groups_view.php"
           class="btn-ctrl" title="<?= $isEn ? 'Switch to Kurdish' : 'Switch to English' ?>" style="font-size:13px;"><?= $isEn ? '🟡' : '🇬🇧' ?></a>
    </div>

<div class="page-wrap">

    <!-- ===== ناو بار ===== -->
    

<?php if ($group_detail): ?>
<!-- ================================================================
     رووپەلی وردەكارییەكانی گروپێك — ئەندامان تاكی تاكی
================================================================ -->
<div class="detail-page">

    <!-- توانی گەڕانەوە -->
    <div class="detail-topbar">
        <a href="groups_view.php" class="back-btn">
            <i class="fas fa-arrow-right"></i>
        </a>
        <div style="flex:1; min-width:0;">
            <div style="font-size:0.7rem; color:var(--muted);"><?= $isEn ? 'Group' : 'گروپ' ?></div>
            <div style="font-size:0.95rem; font-weight:800; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?= htmlspecialchars($group_detail['group_name']) ?>
            </div>
        </div>
       
    </div>

    <?php
    $color = htmlspecialchars($group_detail['group_color']);
    $totalM = count($group_detail['members']);
    $males   = count(array_filter($group_detail['members'], fn($m) => strtolower($m['gender']) === 'male'));
    $females = count(array_filter($group_detail['members'], fn($m) => strtolower($m['gender']) === 'female'));
    $alive   = count(array_filter($group_detail['members'], fn($m) => strtolower($m['status']) === 'alive'));
    ?>

    <!-- هیرۆ هیدر -->
    <div class="group-hero anim">
        <div class="group-hero-bg" style="background: linear-gradient(135deg, <?= $color ?>cc, <?= $color ?>44, rgba(6,8,16,0.9));"></div>
        <div class="group-hero-content">
            <div class="hero-group-name">
                <span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:<?= $color ?>;margin-left:8px;box-shadow:0 0 12px <?= $color ?>;"></span>
                <?= htmlspecialchars($group_detail['group_name']) ?>
            </div>
            <div class="hero-group-sub">
                <i class="fas fa-calendar-alt me-1"></i>
                <?= $isEn ? 'Created:' : 'دروستكرا:' ?> <?= date('Y', strtotime($group_detail['created_at'])) ?>
            </div>
            <!-- سەرۆكان -->
            <?php if (!empty($group_detail['leaders'])): ?>
            <div style="font-size:0.72rem; color:rgba(255,255,255,0.55); margin-bottom:7px;">
                <i class="fas fa-crown me-1" style="color:var(--gold);"></i> <?= $isEn ? 'Group Leaders' : 'سەرۆكانی گروپ' ?>
            </div>
            <div class="leader-chips">
                <?php foreach ($group_detail['leaders'] as $leader): ?>
                <?php 
                    // دمج الاسم الأول مع اسم الأب واسم الجد وبينهما مسافات
                    $lname = get_person_name(['full_name' => $leader['full_name'], 'full_name_en' => $leader['full_name_en']]);
                    $fname = get_person_name(['full_name' => $leader['father_name'], 'full_name_en' => $leader['father_name_en']]);
                    $gname = get_person_name(['full_name' => $leader['grandfather_name'], 'full_name_en' => $leader['grandfather_name_en']]);
                    $leaderTriple = trim($lname . ' ' . $fname . ' ' . $gname);
                ?>
                <div class="leader-chip">
                    <i class="fas fa-crown"></i>
                    <?= htmlspecialchars($leaderTriple) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ئامارەکان -->
    <div class="stats-row anim anim-d1">
        <div class="stat-pill">
            <div class="stat-pill-num" style="color:<?= $color ?>;"><?= $totalM ?></div>
            <div class="stat-pill-lbl"><?= $isEn ? 'Total Members' : 'کۆی ئەندامان' ?></div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-num" style="color:var(--sky);"><?= $males ?></div>
            <div class="stat-pill-lbl"><?= $isEn ? 'Males' : 'نێر' ?></div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-num" style="color:var(--rose);"><?= $females ?></div>
            <div class="stat-pill-lbl"><?= $isEn ? 'Females' : 'مێ' ?></div>
        </div>
        <div class="stat-pill">
            <div class="stat-pill-num" style="color:var(--emerald);"><?= $alive ?></div>
            <div class="stat-pill-lbl"><?= $isEn ? 'Alive' : 'زیندوو' ?></div>
        </div>
    </div>

    <!-- گەڕان و فیلتەر -->
    <div class="detail-toolbar anim anim-d2">
        <input type="text" class="detail-search" id="memberSearch"
               placeholder="<?= $isEn ? '🔍 Search member name...' : '🔍 گەڕانی ناوی ئەندامان...' ?>"
               oninput="filterMembers()">
        <div class="filter-row">
            <button class="fpill active" data-f="all"    onclick="setFilter('all',this)"><?= $isEn ? 'All' : 'هەموو' ?></button>
            <button class="fpill"        data-f="male"   onclick="setFilter('male',this)"><?= $isEn ? 'Male' : 'نێر' ?></button>
            <button class="fpill"        data-f="female" onclick="setFilter('female',this)"><?= $isEn ? 'Female' : 'مێ' ?></button>
            <button class="fpill"        data-f="alive"  onclick="setFilter('alive',this)"><?= $isEn ? 'Alive' : 'زیندوو' ?></button>
            <button class="fpill"        data-f="dead"   onclick="setFilter('dead',this)"><?= $isEn ? 'Deceased' : 'چووە دنیای تر' ?></button>
        </div>
    </div>

    <!-- لیستی ئەندامان -->
    <?php if (empty($group_detail['members'])): ?>
    <div class="empty-box">
        <i class="fas fa-user-slash"></i>
        <h4>هیچ ئەندامێک نییە لەم گروپەدا</h4>
        <p>ئەندامان کوڕ و کچەكانی سەرۆكانن. دڵنیابە لە داتاكان.</p>
    </div>
    <?php else: ?>
    <div class="members-list" id="membersGrid">
        <?php foreach ($group_detail['members'] as $idx => $m): ?>
        <?php
            $isMale   = strtolower($m['gender']) === 'male';
            $isAlive  = strtolower($m['status']) === 'alive';
            $avatarBg = $isMale ? 'rgba(56,189,248,0.15)' : 'rgba(251,113,133,0.15)';
            $avatarIc = $isMale ? '👨' : '👩';
            $birthY   = (!empty($m['birth_date']) && $m['birth_date'] !== '0000-00-00')
                        ? date('d/m/Y', strtotime($m['birth_date'])) : null;
            // دمج الاسم الأول مع اسم الأب واسم الجد وبينهما مسافات
            $lname = get_person_name(['full_name' => $m['full_name'], 'full_name_en' => $m['full_name_en']]);
            $fname = get_person_name(['full_name' => $m['father_name'], 'full_name_en' => $m['father_name_en']]);
            $gname = get_person_name(['full_name' => $m['grandfather_name'], 'full_name_en' => $m['grandfather_name_en']]);
            $tripleName = trim($lname . ' ' . $fname . ' ' . $gname);
            $delay = min($idx * 0.03, 0.25);
        ?>
        <div class="member-row"
             data-gender="<?= strtolower($m['gender']) ?>"
             data-status="<?= strtolower($m['status']) ?>"
             data-name="<?= htmlspecialchars(mb_strtolower($lname)) ?>"
             style="animation: fadeUp 0.3s ease <?= $delay ?>s both;">
            <!-- خەتی ژوری -->
            <div class="member-row-accent" style="background: <?= $color ?>;"></div>

            <!-- ئاوار -->
            <div class="member-avatar" style="background:<?= $avatarBg ?>;">
                <?= $avatarIc ?>
                <div class="status-dot" style="background:<?= $isAlive ? 'var(--emerald)' : 'rgba(148,163,184,0.5)' ?>;"></div>
            </div>

            <!-- بوار -->
            <div class="member-body">
                <!-- ناوی سێ قات -->
                <div class="member-triple-name">
                    
                <?= htmlspecialchars($tripleName) ?>
            
            </div>












                <!-- چیپس -->
                <div class="member-chips">
                    <!-- زایین -->
                    <?php if ($isMale): ?>
                    <span class="mchip male"><i class="fas fa-mars" style="font-size:0.6rem;"></i> <?= $isEn ? 'Male' : 'نێر' ?></span>
                    <?php else: ?>
                    <span class="mchip female"><i class="fas fa-venus" style="font-size:0.6rem;"></i> <?= $isEn ? 'Female' : 'مێ' ?></span>
                    <?php endif; ?>

                    <!-- دۆخ -->
                    <?php if ($isAlive): ?>
                    <span class="mchip alive"><i class="fas fa-circle" style="font-size:0.45rem;"></i> <?= $isEn ? 'Alive' : 'زیندوو' ?></span>
                    <?php else: ?>
                    <span class="mchip dead"><i class="fas fa-moon" style="font-size:0.55rem;"></i> <?= $isEn ? 'Deceased' : 'چووە دنیای تر' ?></span>
                    <?php endif; ?>

                    <!-- بەرواری لەدایكبوون -->
                    <?php if ($birthY): ?>
                    <span class="mchip birth"><i class="fas fa-birthday-cake" style="font-size:0.55rem;"></i> <?= $birthY ?></span>
                    <?php endif; ?>

                    <!-- ناوی دایك -->
                    <?php if (!empty($m['mother_name'])): ?>
                    <?php $mname = get_person_name(['full_name' => $m['mother_name'], 'full_name_en' => $m['mother_name_en']]); ?>
                    <span class="mchip mother"><i class="fas fa-heart" style="font-size:0.55rem;"></i> <?= $isEn ? 'Mother:' : 'دایك:' ?> <?= htmlspecialchars(getTripleName($mname)) ?></span>
                    <?php endif; ?>
                </div>

                <!-- زری شجرة عائلة -->
                <a href="tree.php?id=<?= $m['id'] ?>" class="tree-btn">
                    <i class="fas fa-sitemap"></i><?= $isEn ? 'Family Tree' : 'شجره' ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    let curFilter = 'all';
    function filterMembers() {
        const q = document.getElementById('memberSearch').value.toLowerCase().trim();
        document.querySelectorAll('.member-row').forEach(card => {
            const name   = card.dataset.name;
            const gender = card.dataset.gender;
            const status = card.dataset.status;
            let matchF =
                curFilter === 'all'    ? true :
                curFilter === 'male'   ? gender === 'male' :
                curFilter === 'female' ? gender === 'female' :
                curFilter === 'alive'  ? status === 'alive' :
                curFilter === 'dead'   ? status !== 'alive' : true;
            card.classList.toggle('hidden', !(matchF && (!q || name.includes(q))));
        });
    }
    function setFilter(val, btn) {
        curFilter = val;
        document.querySelectorAll('.fpill').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        filterMembers();
    }
    </script>
    <?php endif; ?>

</div><!-- /detail-page -->

<?php else: ?>
<!-- ================================================================
     رووپەلی هەموو گروپەكان — لیستی ستونی یەك
================================================================ -->
<div class="list-page">

    <!-- هیرۆ -->
    <div class="hero-banner anim">
        <div class="hero-title">🏡 <?= $isEn ? 'Groups' : 'گوروپ' ?></div>
        <div class="hero-desc"><?= $isEn ? 'Select a group to view its members' : 'گروپێك هەڵبژێرە بۆ بینینی ئەنداما' ?></div>
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="hero-stat-num"><?= count($groups) ?></div>
                <div class="hero-stat-lbl"><?= $isEn ? 'Groups' : 'گروپ' ?></div>
            </div>
            <div class="hero-stat">
                <div class="hero-stat-num"><?= array_sum(array_column($groups, 'member_count')) ?></div>
                <div class="hero-stat-lbl"><?= $isEn ? 'Total Members' : 'کۆی ئەندامان' ?></div>
            </div>
        </div>
    </div>

    <?php if (empty($groups)): ?>
    <div class="empty-box">
        <i class="fas fa-layer-group"></i>
        <h4>هیچ گروپێک نییە هێشتا</h4>
        
    </div>
    <?php else: ?>

    <!-- گەڕان -->
    <div class="search-wrap anim anim-d1">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="groupSearch" class="search-input"
               placeholder="<?= $isEn ? 'Search group...' : 'گەڕانی گروپ...' ?>"
               oninput="filterGroups()">
    </div>

    <div class="groups-count anim anim-d1">
        <?= count($groups) ?> <?= $isEn ? 'groups' : 'گوروپ' ?>
    </div>

    <!-- لیستی گروپەكان -->
    <div id="groupsList">
    <?php foreach ($groups as $idx => $g): ?>
    <?php
        $c = htmlspecialchars($g['group_color']);
        $actualM = max(0, intval($g['member_count']) - intval($g['leader_count']));
        $delay = min($idx * 0.05, 0.4);
    ?>
    <a href="groups_view.php?id=<?= $g['id'] ?>"
       class="group-card"
       data-name="<?= strtolower(htmlspecialchars($g['group_name'])) ?>"
       style="animation: fadeUp 0.32s ease <?= $delay ?>s both;">
        <div class="group-card-accent" style="background:<?= $c ?>;"></div>
        <div class="group-icon-wrap" style="background:<?= $c ?>18;">
            <i class="fas fa-users" style="color:<?= $c ?>;"></i>
        </div>
        <div class="group-card-body">
            <div class="group-card-name" style="color:<?= $c ?>;"><?= htmlspecialchars($g['group_name']) ?></div>
            <div class="group-card-meta">
                <span><i class="fas fa-users" style="color:rgba(255,255,255,0.28);"></i> <?= $actualM ?> <?= $isEn ? 'members' : 'ئەندام' ?></span>
              
                <span><i class="fas fa-calendar-alt" style="color:rgba(255,255,255,0.2);"></i> <?= date('Y', strtotime($g['created_at'])) ?></span>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <div class="group-badge" style="color:<?= $c ?>; border-color:<?= $c ?>33;"><?= $actualM ?></div>
            <i class="fas fa-chevron-left group-arrow"></i>
        </div>
    </a>
    <?php endforeach; ?>
    </div>

    <script>
    function filterGroups() {
        const q = document.getElementById('groupSearch').value.toLowerCase().trim();
        document.querySelectorAll('.group-card').forEach(item => {
            item.classList.toggle('hidden', q && !item.dataset.name.includes(q));
        });
    }
    </script>

    <?php endif; ?>
</div><!-- /list-page -->
<?php endif; ?>

</div><!-- /page-wrap -->
</body>
</html>