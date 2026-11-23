<?php
require_once 'header.php';
$lang = get_lang();
$isEn = ($lang === 'en');
$dir  = $isEn ? 'ltr' : 'rtl';

if (method_exists($mysqli, 'set_charset')) { @$mysqli->set_charset('utf8mb4'); }

// ============================================================
// ① إنشاء الجداول تلقائيًا (تُنفَّذ مرة واحدة فقط عمليًا)
// ============================================================
$mysqli->query("
    CREATE TABLE IF NOT EXISTS trip_groups (
        id         VARCHAR(40)  NOT NULL,
        name       VARCHAR(150) NOT NULL,
        color      VARCHAR(12)  NOT NULL DEFAULT '#38bdf8',
        created_at DATETIME     NOT NULL,
        updated_at DATETIME     NULL DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$mysqli->query("
    CREATE TABLE IF NOT EXISTS trip_group_members (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        group_id   VARCHAR(40)  NOT NULL,
        person_id  INT          NOT NULL,
        sort_order INT          NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_person (person_id),          -- الشخص في مجموعة واحدة فقط
        KEY idx_group (group_id),
        CONSTRAINT fk_tgm_group FOREIGN KEY (group_id)
            REFERENCES trip_groups(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ─── إضافة عمود التأكيد تلقائيًا إن لم يكن موجودًا ───
$colChk = $mysqli->query("SHOW COLUMNS FROM trip_group_members LIKE 'is_checked'");
if ($colChk && $colChk->num_rows === 0) {
    $mysqli->query("ALTER TABLE trip_group_members
                    ADD COLUMN is_checked TINYINT(1) NOT NULL DEFAULT 0");
}
if ($colChk) $colChk->free();

// ============================================================
// ② واجهة API (JSON) — كل عمليات الحفظ/التعديل/الحذف
// ============================================================
if (isset($_GET['api'])) {
    // تنظيف أي مخرجات سابقة حتى تبقى الاستجابة JSON نقية
    while (ob_get_level() > 0) { @ob_end_clean(); }
    if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8'); }

    $action = $_POST['action'] ?? '';
    $data   = json_decode($_POST['data'] ?? '{}', true);
    if (!is_array($data)) $data = [];

    $out = function ($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; };
    $fail = function ($msg) use ($out) { $out(['ok' => false, 'error' => $msg]); };

    // ---- جلب كل المجموعات مع أعضائها ----
    $fetchAll = function () use ($mysqli) {
        $groups = [];
        $r = $mysqli->query("SELECT id, name, color, created_at, updated_at
                             FROM trip_groups ORDER BY created_at DESC");
        while ($g = $r->fetch_assoc()) {
            $groups[$g['id']] = [
                'id'      => $g['id'],
                'name'    => $g['name'],
                'color'   => $g['color'],
                'created' => str_replace(' ', 'T', $g['created_at']),
                'updated' => $g['updated_at'] ? str_replace(' ', 'T', $g['updated_at']) : null,
                'members' => [],
                'checked' => [],
            ];
        }
        $r->free();

        if ($groups) {
            $r2 = $mysqli->query("SELECT group_id, person_id, is_checked FROM trip_group_members
                                  ORDER BY group_id, sort_order ASC, id ASC");
            while ($m = $r2->fetch_assoc()) {
                if (isset($groups[$m['group_id']])) {
                    $groups[$m['group_id']]['members'][] = (int)$m['person_id'];
                    if ((int)$m['is_checked'] === 1) {
                        $groups[$m['group_id']]['checked'][] = (int)$m['person_id'];
                    }
                }
            }
            $r2->free();
        }
        return array_values($groups);
    };

    switch ($action) {

        // ─── قراءة كل المجموعات ───
        case 'list':
            $out(['ok' => true, 'groups' => $fetchAll()]);
            break;

        // ─── إنشاء أو تعديل مجموعة ───
        case 'save':
            $id      = trim((string)($data['id'] ?? ''));
            $name    = trim((string)($data['name'] ?? ''));
            $color   = trim((string)($data['color'] ?? '#38bdf8'));
            $members = $data['members'] ?? [];

            if ($name === '')                       $fail('EMPTY_NAME');
            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#38bdf8';
            if (!is_array($members) || !count($members)) $fail('EMPTY_MEMBERS');

            $members = array_values(array_unique(array_map('intval', $members)));
            $name    = mb_substr($name, 0, 150);
            $now     = date('Y-m-d H:i:s');

            $isNew = true;
            if ($id !== '') {
                $st = $mysqli->prepare("SELECT id FROM trip_groups WHERE id = ?");
                $st->bind_param('s', $id);
                $st->execute();
                $isNew = !$st->get_result()->fetch_assoc();
                $st->close();
            } else {
                $id = 'g_' . time() . '_' . bin2hex(random_bytes(3));
            }

            $mysqli->begin_transaction();
            try {
                if ($isNew) {
                    $st = $mysqli->prepare("INSERT INTO trip_groups (id, name, color, created_at)
                                            VALUES (?, ?, ?, ?)");
                    $st->bind_param('ssss', $id, $name, $color, $now);
                } else {
                    $st = $mysqli->prepare("UPDATE trip_groups
                                            SET name = ?, color = ?, updated_at = ? WHERE id = ?");
                    $st->bind_param('ssss', $name, $color, $now, $id);
                }
                $st->execute();
                $st->close();

                // حفظ حالات التأكيد الحالية قبل إعادة بناء الأعضاء
                $prevChecked = [];
                $st = $mysqli->prepare("SELECT person_id FROM trip_group_members
                                        WHERE group_id = ? AND is_checked = 1");
                $st->bind_param('s', $id);
                $st->execute();
                $rc = $st->get_result();
                while ($row = $rc->fetch_assoc()) { $prevChecked[] = (int)$row['person_id']; }
                $st->close();

                // إعادة بناء الأعضاء
                $st = $mysqli->prepare("DELETE FROM trip_group_members WHERE group_id = ?");
                $st->bind_param('s', $id);
                $st->execute();
                $st->close();

                $st = $mysqli->prepare("INSERT INTO trip_group_members
                                        (group_id, person_id, sort_order, is_checked)
                                        VALUES (?, ?, ?, ?)");
                foreach ($members as $i => $pid) {
                    $chk = in_array($pid, $prevChecked, true) ? 1 : 0;
                    $st->bind_param('siii', $id, $pid, $i, $chk);
                    if (!$st->execute()) {
                        throw new Exception('INSERT_MEMBER_FAILED');
                    }
                }
                $st->close();

                $mysqli->commit();
            } catch (Throwable $e) {
                $mysqli->rollback();
                // 1062 = تعارض: الشخص موجود في مجموعة أخرى
                $fail($mysqli->errno == 1062 ? 'DUPLICATE_MEMBER' : 'DB_ERROR');
            }

            $out(['ok' => true, 'id' => $id, 'groups' => $fetchAll()]);
            break;

        // ─── حذف مجموعة كاملة ───
        case 'delete':
            $id = trim((string)($data['id'] ?? ''));
            if ($id === '') $fail('BAD_ID');
            $st = $mysqli->prepare("DELETE FROM trip_groups WHERE id = ?");
            $st->bind_param('s', $id);
            $st->execute();
            $st->close();
            $out(['ok' => true, 'groups' => $fetchAll()]);
            break;

        // ─── حذف شخص واحد من مجموعة ───
        case 'remove_member':
            $id  = trim((string)($data['id'] ?? ''));
            $pid = (int)($data['person_id'] ?? 0);
            if ($id === '' || !$pid) $fail('BAD_INPUT');
            $st = $mysqli->prepare("DELETE FROM trip_group_members
                                    WHERE group_id = ? AND person_id = ?");
            $st->bind_param('si', $id, $pid);
            $st->execute();
            $st->close();

            $st = $mysqli->prepare("UPDATE trip_groups SET updated_at = ? WHERE id = ?");
            $now = date('Y-m-d H:i:s');
            $st->bind_param('ss', $now, $id);
            $st->execute();
            $st->close();
            $out(['ok' => true, 'groups' => $fetchAll()]);
            break;

        // ─── تغيير حالة الـ checkbox لشخص واحد ───
        case 'toggle_check':
            $id  = trim((string)($data['id'] ?? ''));
            $pid = (int)($data['person_id'] ?? 0);
            $val = !empty($data['checked']) ? 1 : 0;
            if ($id === '' || !$pid) $fail('BAD_INPUT');

            $st = $mysqli->prepare("UPDATE trip_group_members SET is_checked = ?
                                    WHERE group_id = ? AND person_id = ?");
            $st->bind_param('isi', $val, $id, $pid);
            $st->execute();
            $st->close();

            $now = date('Y-m-d H:i:s');
            $st = $mysqli->prepare("UPDATE trip_groups SET updated_at = ? WHERE id = ?");
            $st->bind_param('ss', $now, $id);
            $st->execute();
            $st->close();
            $out(['ok' => true, 'groups' => $fetchAll()]);
            break;

        // ─── تحديد / إلغاء تحديد كل أعضاء المجموعة ───
        case 'check_all':
            $id  = trim((string)($data['id'] ?? ''));
            $val = !empty($data['checked']) ? 1 : 0;
            if ($id === '') $fail('BAD_ID');

            $st = $mysqli->prepare("UPDATE trip_group_members SET is_checked = ? WHERE group_id = ?");
            $st->bind_param('is', $val, $id);
            $st->execute();
            $st->close();

            $now = date('Y-m-d H:i:s');
            $st = $mysqli->prepare("UPDATE trip_groups SET updated_at = ? WHERE id = ?");
            $st->bind_param('ss', $now, $id);
            $st->execute();
            $st->close();
            $out(['ok' => true, 'groups' => $fetchAll()]);
            break;

        // ─── استيراد المجموعات القديمة من ذاكرة المتصفح ───
        case 'import':
            $list = $data['groups'] ?? [];
            if (!is_array($list) || !count($list)) $fail('EMPTY');
            $done = 0;
            foreach ($list as $g) {
                $name = trim((string)($g['name'] ?? ''));
                $mem  = $g['members'] ?? [];
                if ($name === '' || !is_array($mem) || !count($mem)) continue;

                $gid   = 'g_' . time() . '_' . bin2hex(random_bytes(3));
                $color = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($g['color'] ?? ''))
                         ? $g['color'] : '#38bdf8';
                $created = !empty($g['created'])
                           ? date('Y-m-d H:i:s', strtotime($g['created'])) : date('Y-m-d H:i:s');

                $mysqli->begin_transaction();
                try {
                    $st = $mysqli->prepare("INSERT INTO trip_groups (id, name, color, created_at)
                                            VALUES (?, ?, ?, ?)");
                    $st->bind_param('ssss', $gid, $name, $color, $created);
                    $st->execute();
                    $st->close();

                    $st = $mysqli->prepare("INSERT INTO trip_group_members
                                            (group_id, person_id, sort_order) VALUES (?, ?, ?)");
                    $i = 0;
                    foreach (array_unique(array_map('intval', $mem)) as $pid) {
                        $st->bind_param('sii', $gid, $pid, $i);
                        $st->execute();
                        $i++;
                    }
                    $st->close();
                    $mysqli->commit();
                    $done++;
                } catch (Throwable $e) {
                    $mysqli->rollback();   // تجاهل المجموعة المتعارضة وأكمل
                }
            }
            $out(['ok' => true, 'imported' => $done, 'groups' => $fetchAll()]);
            break;

        default:
            $fail('UNKNOWN_ACTION');
    }
}

// ============================================================
// جلب الأشخاص: ذكور أحياء فقط مع اسم الأب والجد
// ============================================================
$res = $mysqli->query("
    SELECT 
        p.id,
        p.full_name,        p.full_name_en,
        p.birth_date,
        f.full_name  AS father_name,    f.full_name_en  AS father_name_en,
        gf.full_name AS gf_name,        gf.full_name_en AS gf_name_en
    FROM people2 p
    LEFT JOIN people2 f  ON f.id  = p.father_id
    LEFT JOIN people2 gf ON gf.id = f.father_id
    WHERE p.gender = 'Male' AND p.status = 'Alive'
    ORDER BY p.birth_date ASC
");

$all_people = [];
while ($row = $res->fetch_assoc()) {
    // الاسم الأول فقط من كل شخص في السلسلة
    $n1 = firstWord(($isEn && !empty($row['full_name_en']))    ? $row['full_name_en']    : $row['full_name']);
    $n2 = firstWord(($isEn && !empty($row['father_name_en'])) ? $row['father_name_en'] : $row['father_name']);
    $n3 = firstWord(($isEn && !empty($row['gf_name_en']))     ? $row['gf_name_en']     : $row['gf_name']);

    $triple = trim($n1 . ' ' . $n2 . ' ' . $n3);

    $age = null;
    if (!empty($row['birth_date']) && $row['birth_date'] !== '0000-00-00') {
        $age = (int)(new DateTime())->diff(new DateTime($row['birth_date']))->y;
    }
    $birthFull = (!empty($row['birth_date']) && $row['birth_date'] !== '0000-00-00')
                 ? date('Y', strtotime($row['birth_date'])) : null;

    $all_people[] = [
        'id'        => (int)$row['id'],
        'triple'    => $triple,
        'age'       => $age,
        'birthYear' => $birthFull,
    ];
}
$res->free();

function firstWord($str) {
    if (!$str) return '';
    $parts = explode(' ', trim($str));
    return $parts[0];
}
?>
<!DOCTYPE html>
<html lang="<?= $isEn ? 'en' : 'ku' ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title><?= $isEn ? 'Trip Groups' : 'گروپ - سفرة' ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
    --bg:      #07090f;
    --panel:   #0d1117;
    --card:    rgba(255,255,255,0.04);
    --hover:   rgba(255,255,255,0.08);
    --border:  rgba(255,255,255,0.09);
    --bhi:     rgba(255,255,255,0.22);
    --text:    #e8edf4;
    --muted:   rgba(232,237,244,0.42);
    --gold:    #f6c941;
    --green:   #34d399;
    --sky:     #38bdf8;
    --rose:    #fb7185;
    --violet:  #a78bfa;
    --r20:     18px;
    --r12:     11px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    min-height: 100%; background: var(--bg);
    color: var(--text); font-family: 'Tajawal', sans-serif;
    overscroll-behavior: none;
}
body::before {
    content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
    background:
        radial-gradient(ellipse 70% 55% at 10% 5%,  rgba(56,189,248,.07) 0%, transparent 65%),
        radial-gradient(ellipse 55% 70% at 90% 90%, rgba(167,139,250,.06) 0%, transparent 65%);
}
.page { position:relative; z-index:1; min-height:100vh; }

/* ─── CONTROLS ─── */
#ctrl {
    position:fixed; top:14px;
    <?= $isEn ? 'left' : 'right' ?>: 14px;
    z-index:300; display:flex; gap:8px;
}
.cbtn {
    width:40px; height:40px; border-radius:10px;
    background:rgba(255,255,255,0.07); border:1px solid var(--bhi);
    color:#fff; display:flex; align-items:center; justify-content:center;
    text-decoration:none; font-size:15px; cursor:pointer;
    backdrop-filter:blur(10px); transition:background .2s;
}
.cbtn:hover { background:rgba(255,255,255,0.18); color:#fff; }

/* ─── SCREEN SWITCHER ─── */
.screen { display:none; }
.screen.show { display:block; }

/* ══════════════════════════════
   SCREEN 1 — GROUPS LIST
══════════════════════════════ */
.s-list { max-width:680px; margin:0 auto; padding:70px 14px 30px; }

.hero {
    background:linear-gradient(135deg, rgba(56,189,248,.13), rgba(167,139,250,.10), rgba(246,201,65,.07));
    border:1px solid rgba(255,255,255,.1); border-radius:var(--r20);
    padding:26px 20px 22px; margin-bottom:20px; text-align:center;
}
.hero-title {
    font-size:1.55rem; font-weight:900;
    background:linear-gradient(120deg, var(--gold), var(--sky), var(--violet));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent;
    background-clip:text; margin-bottom:6px;
}
.hero-sub { font-size:.82rem; color:var(--muted); }
.hero-nums { display:flex; justify-content:center; gap:28px; margin-top:14px; }
.hnum-v { font-size:1.35rem; font-weight:900; color:var(--gold); }
.hnum-l { font-size:.67rem; color:var(--muted); }

/* زر إنشاء */
.btn-create {
    width:100%; padding:16px; border:none; border-radius:var(--r20);
    background:linear-gradient(135deg,var(--sky),var(--violet));
    color:#fff; font-size:1rem; font-weight:800;
    font-family:'Tajawal',sans-serif; cursor:pointer; margin-bottom:18px;
    display:flex; align-items:center; justify-content:center; gap:10px;
    box-shadow:0 6px 22px rgba(56,189,248,.28); transition:all .25s;
}
.btn-create:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(56,189,248,.42); }

/* كرت المجموعة */
.gc {
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--r20); padding:15px 16px; margin-bottom:10px;
    display:flex; align-items:center; gap:13px;
    cursor:pointer; transition:all .2s; position:relative; overflow:hidden;
}
.gc:hover { background:var(--hover); transform:translateY(-2px); }
.gc-bar { position:absolute; <?= $isEn?'left':'right' ?>:0; top:0; bottom:0; width:4px; border-radius:0; }
.gc-ico { width:48px; height:48px; border-radius:var(--r12); flex-shrink:0;
          display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.gc-body { flex:1; min-width:0; }
.gc-name { font-size:1.02rem; font-weight:800; margin-bottom:4px;
           white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.gc-meta { font-size:.73rem; color:var(--muted); display:flex; gap:12px; }
.gc-actions { display:flex; gap:6px; flex-shrink:0; }
.ga {
    width:34px; height:34px; border-radius:9px; border:none;
    cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:.85rem;
    transition:transform .18s;
}
.ga:hover { transform:scale(1.12); }
.ga-print { background:rgba(52,211,153,.15); color:var(--green); }
.ga-edit  { background:rgba(56,189,248,.15);  color:var(--sky); }
.ga-del   { background:rgba(251,113,133,.13); color:var(--rose); }

.empty { text-align:center; padding:60px 20px; color:var(--muted); }
.empty i { font-size:3rem; display:block; margin-bottom:16px; opacity:.22; }
.empty h4 { font-size:1rem; color:rgba(255,255,255,.28); margin-bottom:8px; }

/* ══════════════════════════════
   SCREEN 2 — CREATE GROUP
══════════════════════════════ */
.s-create { max-width:680px; margin:0 auto; padding:70px 14px 30px; }

.top-bar { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
.back-btn {
    width:38px; height:38px; border-radius:50%;
    background:rgba(255,255,255,.07); border:1px solid var(--border);
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; color:var(--text); font-size:.9rem; flex-shrink:0;
    transition:background .18s;
}
.back-btn:hover { background:rgba(255,255,255,.15); }
.top-title { font-size:1rem; font-weight:800; }
.top-sub   { font-size:.7rem; color:var(--muted); }

/* حقل الاسم */
.name-box {
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--r20); padding:18px; margin-bottom:14px;
}
.box-lbl { font-size:.75rem; font-weight:700; color:var(--muted);
           text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
.fg {
    width:100%; background:rgba(255,255,255,.06);
    border:1px solid var(--border); border-radius:var(--r12);
    padding:13px 16px; color:var(--text);
    font-family:'Tajawal',sans-serif; font-size:.95rem;
    outline:none; transition:border-color .2s;
}
.fg:focus { border-color:rgba(56,189,248,.5); }
.fg::placeholder { color:var(--muted); }

/* ألوان */
.colors { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
.cdot {
    width:28px; height:28px; border-radius:50%; cursor:pointer;
    border:3px solid transparent; transition:transform .18s, border-color .18s;
}
.cdot.on { border-color:#fff; transform:scale(1.25); }

/* شريط الخيارات فوق القائمة */
.list-toolbar {
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; margin-bottom:10px; flex-wrap:wrap;
}
.sel-info { font-size:.8rem; color:var(--gold); font-weight:700; }
.btn-selall {
    font-size:.76rem; color:var(--sky); background:none; border:none;
    cursor:pointer; font-family:'Tajawal',sans-serif; padding:0;
    transition:color .18s;
}
.btn-selall:hover { color:var(--violet); }

/* البحث */
.p-search {
    width:100%; background:rgba(255,255,255,.05);
    border:1px solid var(--border); border-radius:50px;
    padding:11px 18px; color:var(--text);
    font-family:'Tajawal',sans-serif; font-size:.88rem;
    outline:none; margin-bottom:10px; transition:border-color .2s;
}
.p-search:focus { border-color:rgba(56,189,248,.4); }
.p-search::placeholder { color:var(--muted); }

/* ─── قائمة الأشخاص ─── */
.p-list {
    border:1px solid var(--border); border-radius:var(--r20);
    overflow:hidden; overflow-y:auto;
    max-height: calc(100vh - 370px);
    min-height: 220px;
}
/* رأس الأعمدة */
.p-head {
    display:grid;
    grid-template-columns: 36px 1fr 60px 64px;
    gap:0;
    padding:9px 14px;
    background:rgba(0,0,0,.25);
    border-bottom:1px solid var(--border);
    font-size:.68rem; font-weight:700; color:var(--muted);
    text-transform:uppercase; letter-spacing:.5px;
}
/* صف الشخص */
.p-row {
    display:grid;
    grid-template-columns: 36px 1fr 60px 64px;
    gap:0;
    padding:11px 14px;
    border-bottom:1px solid rgba(255,255,255,.04);
    align-items:center; cursor:pointer;
    transition:background .14s;
    user-select:none;
}
.p-row:last-child { border-bottom:none; }
.p-row:hover   { background:var(--hover); }
.p-row.checked { background:rgba(56,189,248,.07); }
.p-row.hidden  { display:none !important; }

/* checkbox custom */
.p-cb {
    width:20px; height:20px; border-radius:6px;
    border:2px solid var(--bhi); background:transparent;
    display:flex; align-items:center; justify-content:center;
    transition:all .17s; flex-shrink:0;
}
.p-row.checked .p-cb { background:var(--sky); border-color:var(--sky); }
.p-cb-icon { color:#fff; font-size:.6rem; opacity:0; transition:opacity .15s; }
.p-row.checked .p-cb-icon { opacity:1; }

.p-name { font-size:.9rem; font-weight:700; padding:0 8px; }
.p-age  { font-size:.78rem; color:var(--muted); text-align:center; }
.p-year { font-size:.72rem; color:var(--gold); text-align:center; }

/* زر الحفظ */
.btn-save {
    width:100%; padding:16px; border:none; border-radius:var(--r20);
    background:linear-gradient(135deg, var(--green), #059669);
    color:#fff; font-size:1rem; font-weight:800;
    font-family:'Tajawal',sans-serif; cursor:pointer; margin-top:14px;
    display:flex; align-items:center; justify-content:center; gap:9px;
    box-shadow:0 6px 22px rgba(52,211,153,.28); transition:all .25s;
}
.btn-save:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(52,211,153,.42); }
.btn-save:disabled { opacity:.4; cursor:not-allowed; transform:none; }

/* ══════════════════════════════
   SCREEN 3 — DETAIL
══════════════════════════════ */
.s-detail { max-width:680px; margin:0 auto; padding:70px 14px 30px; }

.d-hero {
    border-radius:var(--r20); padding:24px 20px; margin-bottom:16px;
    position:relative; overflow:hidden;
}
.d-hero-inner { position:relative; z-index:1; }
.d-name { font-size:1.75rem; font-weight:900; color:#fff; margin-bottom:4px; }
.d-sub  { font-size:.78rem; color:rgba(255,255,255,.55); margin-bottom:14px; }
.d-stats { display:flex; gap:12px; }
.d-stat { background:rgba(255,255,255,.12); border-radius:var(--r12); padding:8px 14px; text-align:center; }
.d-stat-n { font-size:1.1rem; font-weight:900; }
.d-stat-l { font-size:.62rem; color:rgba(255,255,255,.6); }

.btn-print {
    width:100%; padding:13px; border-radius:var(--r20);
    background:rgba(246,201,65,.12); border:1px solid rgba(246,201,65,.35);
    color:var(--gold); font-size:.9rem; font-weight:700;
    font-family:'Tajawal',sans-serif; cursor:pointer; margin-bottom:16px;
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:all .2s;
}
.btn-print:hover { background:rgba(246,201,65,.22); transform:translateY(-2px); }

/* شريط أزرار التفاصيل */
.d-actions { display:flex; gap:8px; margin-bottom:16px; }
.d-actions .btn-print { margin-bottom:0; flex:1; }
.btn-editg {
    flex:1; padding:13px; border-radius:var(--r20);
    background:rgba(56,189,248,.12); border:1px solid rgba(56,189,248,.35);
    color:var(--sky); font-size:.9rem; font-weight:700;
    font-family:'Tajawal',sans-serif; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:all .2s;
}
.btn-editg:hover { background:rgba(56,189,248,.22); transform:translateY(-2px); }

/* زر إضافة أعضاء سريع */
.btn-addm {
    width:100%; padding:12px; border-radius:var(--r20);
    background:rgba(52,211,153,.10); border:1px dashed rgba(52,211,153,.45);
    color:var(--green); font-size:.85rem; font-weight:700;
    font-family:'Tajawal',sans-serif; cursor:pointer; margin-top:8px;
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:all .2s;
}
.btn-addm:hover { background:rgba(52,211,153,.2); }

/* ─── checkbox العضو داخل المجموعة ─── */
.d-member.done { background:rgba(52,211,153,.09); border-color:rgba(52,211,153,.28); }
.d-member.done .d-mname { color:var(--green); }
.d-chk {
    width:26px; height:26px; border-radius:8px; flex-shrink:0;
    border:2px solid var(--bhi); background:transparent;
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .17s;
}
.d-chk:hover { border-color:var(--green); }
.d-chk i { color:#fff; font-size:.72rem; opacity:0; transition:opacity .15s; }
.d-member.done .d-chk   { background:var(--green); border-color:var(--green); }
.d-member.done .d-chk i { opacity:1; }
.d-chk.saving { opacity:.45; pointer-events:none; }

/* شريط التحديد فوق قائمة الأعضاء */
.chk-bar {
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; margin-bottom:10px; padding:0 4px; flex-wrap:wrap;
}
.chk-stat { font-size:.82rem; font-weight:800; color:var(--green); }
.chk-btns { display:flex; gap:8px; }
.chk-btn {
    font-size:.74rem; padding:6px 12px; border-radius:20px;
    background:rgba(255,255,255,.06); border:1px solid var(--border);
    color:var(--text); font-family:'Tajawal',sans-serif; cursor:pointer;
    transition:background .18s;
}
.chk-btn:hover { background:rgba(255,255,255,.16); }

/* زر حذف عضو من داخل التفاصيل */
.d-rm {
    width:30px; height:30px; border-radius:8px; border:none; flex-shrink:0;
    background:rgba(251,113,133,.13); color:var(--rose);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    font-size:.78rem; transition:transform .18s, background .18s;
}
.d-rm:hover { background:rgba(251,113,133,.28); transform:scale(1.12); }

/* أعضاء التفاصيل */
.d-member {
    background:var(--card); border:1px solid var(--border);
    border-radius:var(--r20); padding:13px 16px; margin-bottom:8px;
    display:flex; align-items:center; gap:12px; position:relative; overflow:hidden;
}
.d-bar { position:absolute; top:0; left:0; right:0; height:3px; border-radius:var(--r20) var(--r20) 0 0; }
.d-num { font-size:.7rem; color:var(--muted); font-weight:700; min-width:22px; text-align:center; }
.d-info { flex:1; }
.d-mname { font-size:.93rem; font-weight:800; margin-bottom:2px; }
.d-mmeta { font-size:.72rem; color:var(--muted); }

/* ══════════════════════════════
   PRINT
══════════════════════════════ */
@media print {
    body, html { background:#fff !important; color:#000 !important; }
    body::before { display:none !important; }
    #ctrl, .no-print { display:none !important; }
    .print-pg {
        page-break-after: always;
        padding: 18mm 14mm;
        font-family: 'Tajawal', Arial, sans-serif;
    }
    .print-pg:last-child { page-break-after: avoid; }
    .ph { border-bottom:3px solid #222; padding-bottom:12px; margin-bottom:18px;
          display:flex; justify-content:space-between; align-items:flex-end; }
    .ph-name { font-size:22pt; font-weight:900; }
    .ph-meta { font-size:10pt; color:#555; }
    .ph-date { font-size:9pt; color:#888; text-align:right; }
    .pt { width:100%; border-collapse:collapse; }
    .pt th { background:#f0f0f0; padding:8px 10px; font-size:10.5pt; border:1px solid #ccc;
             text-align:<?= $isEn?'left':'right' ?>; }
    .pt td { padding:8px 10px; font-size:10pt; border:1px solid #ddd; }
    .pt tr:nth-child(even) td { background:#fafafa; }
    .pf { margin-top:18px; font-size:9pt; color:#aaa; text-align:center;
          border-top:1px solid #ddd; padding-top:8px; }
    @page { size:A4 portrait; margin:0; }
}

/* Scrollbar */
::-webkit-scrollbar { width:4px; }
::-webkit-scrollbar-thumb { background:rgba(255,255,255,.12); border-radius:4px; }

/* Toast */
.toast-pop {
    position:fixed; bottom:22px; left:50%; transform:translateX(-50%) translateY(60px);
    background:rgba(52,211,153,.92); color:#fff; padding:11px 24px;
    border-radius:50px; font-size:.85rem; font-weight:700; z-index:999;
    transition:transform .3s, opacity .3s; opacity:0; pointer-events:none;
}
.toast-pop.up { transform:translateX(-50%) translateY(0); opacity:1; }

/* Age badge */
.age-badge {
    display:inline-block; padding:2px 8px; border-radius:20px;
    font-size:.68rem; font-weight:700; background:rgba(56,189,248,.12); color:var(--sky);
}

@keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
.anim { animation:fadeUp .28s ease both; }
</style>
</head>
<body>
<div class="page">

<!-- CONTROLS -->
<div id="ctrl">
    <button class="cbtn" onclick="goHome()" title="<?= $isEn?'Home':'الرئيسية' ?>"><i class="fas fa-home"></i></button>
    <a href="set_lang.php?lang=<?= $isEn?'ku':'en' ?>&back=trip_groups.php"
       class="cbtn" title="<?= $isEn?'Switch to Kurdish':'Switch to English' ?>" style="font-size:12px;"><?= $isEn?'🟡':'🇬🇧' ?></a>
</div>

<!-- ══════════ SCREEN 1: LIST ══════════ -->
<div id="s-list" class="screen show">
<div class="s-list">
    <div class="hero anim">
        <div class="hero-title">🚌 <?= $isEn?'Trip Groups':'گروپ - سفرة العائلة' ?></div>
        <div class="hero-sub"><?= $isEn?'Alive males only · Saved in the database':'ذكور أحياء فقط · محفوظ في قاعدة البيانات' ?></div>
        <div class="hero-nums">
            <div class="hnum">
                <div class="hnum-v" id="hg-count">0</div>
                <div class="hnum-l"><?= $isEn?'Groups':'گروپ' ?></div>
            </div>
            <div class="hnum">
                <div class="hnum-v"><?= count($all_people) ?></div>
                <div class="hnum-l"><?= $isEn?'Alive Males':'ذكور أحياء' ?></div>
            </div>
        </div>
    </div>

    <button class="btn-create" onclick="showCreate()">
        <i class="fas fa-plus-circle"></i>
        <?= $isEn?'Create New Group':'إنشاء مجموعة جديدة' ?>
    </button>

    <div id="groups-wrap"></div>
    <div class="empty" id="empty-state" style="display:none">
        <i class="fas fa-route"></i>
        <h4><?= $isEn?'No groups yet':'لا توجد مجموعات بعد' ?></h4>
        <p><?= $isEn?'Click the button above to create one':'اضغط الزر أعلاه لإنشاء مجموعة' ?></p>
    </div>
</div>
</div>

<!-- ══════════ SCREEN 2: CREATE ══════════ -->
<div id="s-create" class="screen">
<div class="s-create">

    <!-- رأس الصفحة -->
    <div class="top-bar no-print">
        <button class="back-btn" onclick="backFromCreate()">
            <i class="fas fa-arrow-<?= $isEn?'left':'right' ?>"></i>
        </button>
        <div>
            <div class="top-sub" id="c-sub"><?= $isEn?'Step 1 of 1':'خطوة واحدة' ?></div>
            <div class="top-title" id="c-title"><?= $isEn?'Create Trip Group':'إنشاء مجموعة سفرة' ?></div>
        </div>
    </div>

    <!-- اسم المجموعة -->
    <div class="name-box anim">
        <div class="box-lbl"><?= $isEn?'① Group Name':'① اسم المجموعة' ?></div>
        <input type="text" id="g-name" class="fg"
               placeholder="<?= $isEn?'e.g. Elders, Youth, Bus 1...':'مثال: الكبار، الشباب، الحافلة ١...' ?>">
        <div class="colors" id="color-picker"></div>
    </div>

    <!-- قائمة الأشخاص -->
    <div class="name-box anim" style="margin-bottom:4px; padding-bottom:12px;">
        <div class="box-lbl" style="margin-bottom:10px;">
            <?= $isEn?'② Select Members (sorted by age)':'② اختر الأعضاء (مرتب حسب العمر)' ?>
            <span id="excl-note" style="color:var(--rose); font-size:.7rem; margin-<?= $isEn?'left':'right' ?>:8px;"></span>
        </div>

        <input type="text" class="p-search" id="p-search"
               placeholder="<?= $isEn?'🔍 Search by name...':'🔍 ابحث بالاسم...' ?>"
               oninput="filterPeople()">

        <div class="list-toolbar">
            <span class="sel-info" id="sel-count">0 <?= $isEn?'selected':'محدد' ?></span>
            <button class="btn-selall" onclick="selectAll()"><?= $isEn?'Select All Visible':'تحديد الكل' ?></button>
        </div>

        <!-- رأس الجدول -->
        <div class="p-head">
            <div></div>
            <div><?= $isEn?'Name (Triple)':'الاسم الثلاثي' ?></div>
            <div style="text-align:center"><?= $isEn?'Age':'العمر' ?></div>
            <div style="text-align:center"><?= $isEn?'Year':'سنة م' ?></div>
        </div>

        <!-- صفوف الأشخاص -->
        <div class="p-list" id="p-list"></div>
    </div>

    <button class="btn-save no-print" id="save-btn" onclick="saveGroup()">
        <i class="fas fa-floppy-disk"></i>
        <span id="save-lbl"><?= $isEn?'Save Group':'حفظ المجموعة' ?></span>
    </button>
</div>
</div>

<!-- ══════════ SCREEN 3: DETAIL ══════════ -->
<div id="s-detail" class="screen">
<div class="s-detail">
    <div class="top-bar no-print">
        <button class="back-btn" onclick="showList()">
            <i class="fas fa-arrow-<?= $isEn?'left':'right' ?>"></i>
        </button>
        <div id="d-header"></div>
    </div>

    <div id="d-hero" class="d-hero anim"></div>

    <div class="d-actions no-print">
        <button class="btn-editg" onclick="editCurrent()">
            <i class="fas fa-user-pen"></i>
            <?= $isEn?'Edit Group':'تعديل المجموعة' ?>
        </button>
        <button class="btn-print" onclick="printCurrent()">
            <i class="fas fa-print"></i>
            <?= $isEn?'Print A4':'طباعة A4' ?>
        </button>
    </div>

    <div class="chk-bar no-print" id="chk-bar"></div>

    <div id="d-members"></div>

    <button class="btn-addm no-print" onclick="editCurrent()">
        <i class="fas fa-user-plus"></i>
        <?= $isEn?'Add / Remove Members':'إضافة أو حذف أشخاص' ?>
    </button>
</div>
</div>

<!-- Print area -->
<div id="print-area" style="display:none"></div>

<!-- Toast -->
<div class="toast-pop" id="toast"></div>

</div><!-- /page -->

<script>
/* ═══════════════════════════════════════
   DATA
═══════════════════════════════════════ */
const PEOPLE  = <?= json_encode($all_people, JSON_UNESCAPED_UNICODE) ?>;
const IS_EN   = <?= $isEn ? 'true' : 'false' ?>;
const COLORS  = ['#38bdf8','#a78bfa','#f6c941','#34d399','#fb7185',
                 '#f97316','#22d3ee','#e879f9','#84cc16','#fbbf24'];
const LS_KEY  = 'trip_v3';
const TODAY   = new Date().toLocaleDateString(IS_EN ? 'en-GB' : 'ar-IQ');

let selColor  = COLORS[0];
let selectedSet = new Set();
let currentGid  = null;
let editingGid  = null;   // null = إنشاء جديد | id = وضع التعديل

/* ═══════════════════════════════════════
   DATABASE LAYER  (بدل localStorage)
═══════════════════════════════════════ */
let GROUPS = [];          // نسخة محلية من بيانات قاعدة البيانات
let busy   = false;

async function api(action, payload) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('data', JSON.stringify(payload || {}));
    const res  = await fetch('trip_groups.php?api=1', { method:'POST', body: fd });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'DB_ERROR');
    if (json.groups) GROUPS = json.groups;
    return json;
}

function errMsg(code) {
    const M = {
        EMPTY_NAME:       IS_EN?'Enter a group name':'أدخل اسم المجموعة',
        EMPTY_MEMBERS:    IS_EN?'Select at least one member':'اختر عضوًا واحدًا على الأقل',
        DUPLICATE_MEMBER: IS_EN?'A selected person is already in another group':'أحد الأشخاص موجود في مجموعة أخرى',
    };
    return M[code] || (IS_EN?'Database error':'خطأ في قاعدة البيانات');
}

/* تُستخدم في باقي الكود كما كانت */
function loadGroups() { return GROUPS; }

/* ─── Helper ─── */
function esc(s) {
    return String(s||'')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function toast(msg, err) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = err ? 'rgba(251,113,133,.92)' : 'rgba(52,211,153,.92)';
    t.classList.add('up');
    setTimeout(()=>t.classList.remove('up'), 2600);
}

/* ═══════════════════════════════════════
   SCREEN SWITCHING
═══════════════════════════════════════ */
function showScreen(id) {
    document.querySelectorAll('.screen').forEach(s=>s.classList.remove('show'));
    document.getElementById('s-'+id).classList.add('show');
    window.scrollTo(0,0);
}
function showList()   { editingGid = null; renderList(); showScreen('list'); }
function showCreate() { resetCreate(null); showScreen('create'); }
function showEdit(id) { resetCreate(id);   showScreen('create'); }
function goHome()     { location.href = 'index.php'; }

/* الرجوع من شاشة الإنشاء/التعديل */
function backFromCreate() {
    if (editingGid) { const g = editingGid; editingGid = null; showDetail(g); }
    else showList();
}

/* ═══════════════════════════════════════
   SCREEN 1 — GROUPS LIST
═══════════════════════════════════════ */
function renderList() {
    const groups = loadGroups();
    document.getElementById('hg-count').textContent = groups.length;
    const wrap  = document.getElementById('groups-wrap');
    const empty = document.getElementById('empty-state');

    if (!groups.length) { wrap.innerHTML=''; empty.style.display='block'; return; }
    empty.style.display = 'none';

    wrap.innerHTML = groups.map((g,i) => {
        const c = g.color || COLORS[0];
        const cnt = g.members.length;
        const done = (g.checked || []).length;
        const date = (g.created||'').split('T')[0];
        return `
        <div class="gc anim" onclick="showDetail('${g.id}')" style="animation-delay:${i*.04}s">
            <div class="gc-bar" style="background:${c}"></div>
            <div class="gc-ico" style="background:${c}18; color:${c}">🏕️</div>
            <div class="gc-body">
                <div class="gc-name" style="color:${c}">${esc(g.name)}</div>
                <div class="gc-meta">
                    <span><i class="fas fa-users" style="opacity:.45"></i> ${cnt} ${IS_EN?'members':'عضو'}</span>
                    <span style="color:var(--green)"><i class="fas fa-circle-check"></i> ${done}/${cnt}</span>
                    <span><i class="fas fa-calendar-alt" style="opacity:.35"></i> ${date}</span>
                </div>
            </div>
            <div class="gc-actions" onclick="event.stopPropagation()">
                <button class="ga ga-edit"  onclick="showEdit('${g.id}')"  title="${IS_EN?'Edit':'تعديل'}"><i class="fas fa-pen"></i></button>
                <button class="ga ga-print" onclick="printById('${g.id}')" title="${IS_EN?'Print':'طباعة'}"><i class="fas fa-print"></i></button>
                <button class="ga ga-del"   onclick="deleteGroup('${g.id}')" title="${IS_EN?'Delete':'حذف'}"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    }).join('');
}

async function deleteGroup(id) {
    if (busy) return;
    if (!confirm(IS_EN?'Delete this group?':'هل تريد حذف هذه المجموعة؟')) return;
    busy = true;
    try {
        await api('delete', { id });
        renderList();
        toast(IS_EN?'Deleted':'تم الحذف');
    } catch (e) {
        toast('⚠ ' + errMsg(e.message), true);
    }
    busy = false;
}

/* ═══════════════════════════════════════
   SCREEN 2 — CREATE
═══════════════════════════════════════ */
/* ─── الأشخاص المستخدمين في مجموعات أخرى (نتجاهل المجموعة الجاري تعديلها) ─── */
function getUsedIds(exceptGid) {
    const used = new Set();
    loadGroups().forEach(g => {
        if (exceptGid && g.id === exceptGid) return;   // أعضاء المجموعة الحالية تبقى متاحة
        g.members.forEach(id => used.add(id));
    });
    return used;
}

/* ─── الأشخاص المتاحين (مستبعدًا من سبق اختياره في مجموعة أخرى) ─── */
function getAvailablePeople() {
    const used = getUsedIds(editingGid);
    return PEOPLE.filter(p => !used.has(p.id));
}

/* gid = null → إنشاء جديد | gid = id → تعديل مجموعة موجودة */
function resetCreate(gid) {
    editingGid = gid || null;
    const g = editingGid ? loadGroups().find(x => x.id === editingGid) : null;
    if (editingGid && !g) { editingGid = null; }

    selectedSet = new Set(g ? g.members : []);
    document.getElementById('g-name').value = g ? g.name : '';
    selColor = g ? (g.color || COLORS[0]) : COLORS[0];

    // عناوين الشاشة
    document.getElementById('c-title').textContent = editingGid
        ? (IS_EN ? 'Edit Trip Group' : 'تعديل مجموعة السفرة')
        : (IS_EN ? 'Create Trip Group' : 'إنشاء مجموعة سفرة');
    document.getElementById('c-sub').textContent = editingGid
        ? (IS_EN ? 'Add or remove members' : 'إضافة أو حذف أشخاص')
        : (IS_EN ? 'Step 1 of 1' : 'خطوة واحدة');

    // build color picker
    const cp = document.getElementById('color-picker');
    cp.innerHTML = '';
    COLORS.forEach(c => {
        const d = document.createElement('div');
        d.className = 'cdot' + (c === selColor ? ' on' : '');
        d.style.background = c;
        d.onclick = () => {
            document.querySelectorAll('.cdot').forEach(x=>x.classList.remove('on'));
            d.classList.add('on');
            selColor = c;
        };
        cp.appendChild(d);
    });

    // excluded note
    const usedCnt = getUsedIds(editingGid).size;
    const note = document.getElementById('excl-note');
    note.textContent = usedCnt > 0
        ? (IS_EN ? `(${usedCnt} already assigned excluded)` : `(${usedCnt} محدد مسبقًا مستبعد)`)
        : '';

    document.getElementById('p-search').value = '';
    renderPeopleList();
    updateSaveBtn();
}

/* ─── رسم قائمة الأشخاص ─── */
function renderPeopleList() {
    const avail = getAvailablePeople();
    const q = document.getElementById('p-search').value.trim().toLowerCase();

    const html = avail.map(p => {
        const visible = !q || p.triple.toLowerCase().includes(q);
        const chk = selectedSet.has(p.id);
        const ageStr = p.age !== null ? p.age + (IS_EN?' y':' س') : '—';
        return `
        <div class="p-row${chk?' checked':''}${visible?'':' hidden'}"
             data-id="${p.id}" onclick="togglePerson(${p.id})">
            <div class="p-cb"><i class="fas fa-check p-cb-icon"></i></div>
            <div class="p-name">${esc(p.triple)}</div>
            <div class="p-age">${ageStr}</div>
            <div class="p-year">${p.birthYear||'—'}</div>
        </div>`;
    }).join('');

    document.getElementById('p-list').innerHTML = html ||
        `<div style="text-align:center;padding:30px;color:var(--muted);font-size:.85rem">
            ${IS_EN?'No persons found':'لا يوجد أشخاص'}
        </div>`;

    updateSelCount();
}

function togglePerson(id) {
    if (selectedSet.has(id)) selectedSet.delete(id);
    else selectedSet.add(id);
    const row = document.querySelector(`.p-row[data-id="${id}"]`);
    if (row) row.classList.toggle('checked', selectedSet.has(id));
    updateSelCount();
    updateSaveBtn();
}

function filterPeople() { renderPeopleList(); }

function selectAll() {
    const visible = [...document.querySelectorAll('.p-row:not(.hidden)')];
    const allSel  = visible.every(r => selectedSet.has(+r.dataset.id));
    visible.forEach(r => {
        const id = +r.dataset.id;
        if (allSel) { selectedSet.delete(id); r.classList.remove('checked'); }
        else         { selectedSet.add(id);    r.classList.add('checked');    }
    });
    updateSelCount();
    updateSaveBtn();
}

function updateSelCount() {
    document.getElementById('sel-count').textContent =
        selectedSet.size + ' ' + (IS_EN ? 'selected' : 'محدد');
}

function updateSaveBtn() {
    const btn = document.getElementById('save-btn');
    const lbl = document.getElementById('save-lbl');
    const base = editingGid
        ? (IS_EN ? 'Save Changes' : 'حفظ التعديلات')
        : (IS_EN ? 'Save Group'   : 'حفظ المجموعة');
    btn.disabled = selectedSet.size === 0;
    lbl.textContent = selectedSet.size > 0 ? `${base} (${selectedSet.size})` : base;
}

async function saveGroup() {
    if (busy) return;
    const name = document.getElementById('g-name').value.trim();
    if (!name) { toast(IS_EN?'⚠ Enter a group name!':'⚠ أدخل اسم المجموعة!', true); return; }
    if (!selectedSet.size) { toast(IS_EN?'⚠ Select at least one member!':'⚠ اختر عضوًا واحدًا على الأقل!', true); return; }

    // ترتيب حسب العمر (الأكبر أولًا)
    const sorted = PEOPLE
        .filter(p => selectedSet.has(p.id))
        .sort((a,b) => (b.age||0) - (a.age||0))
        .map(p => p.id);

    busy = true;
    const btn = document.getElementById('save-btn');
    const old = document.getElementById('save-lbl').textContent;
    btn.disabled = true;
    document.getElementById('save-lbl').textContent = IS_EN ? 'Saving...' : 'جارٍ الحفظ...';

    try {
        const r = await api('save', {
            id: editingGid || '',
            name, color: selColor, members: sorted
        });
        const wasEdit = !!editingGid;
        editingGid = null;
        toast(wasEdit ? (IS_EN?'✅ Changes saved!':'✅ تم حفظ التعديلات!')
                      : (IS_EN?'✅ Group saved!':'✅ تم حفظ المجموعة!'));
        busy = false;
        if (wasEdit) showDetail(r.id); else showList();
        return;
    } catch (e) {
        toast('⚠ ' + errMsg(e.message), true);
        document.getElementById('save-lbl').textContent = old;
        btn.disabled = false;
        busy = false;
    }
}

/* ═══════════════════════════════════════
   SCREEN 3 — DETAIL
═══════════════════════════════════════ */
function showDetail(id) {
    currentGid = id;
    const g = loadGroups().find(x=>x.id===id);
    if (!g) return;

    const c = g.color || COLORS[0];
    const mdata = g.members.map(mid => PEOPLE.find(p=>p.id===mid)).filter(Boolean);
    const chkSet = new Set(g.checked || []);
    const doneN  = mdata.filter(p => chkSet.has(p.id)).length;

    document.getElementById('d-header').innerHTML =
        `<div style="font-size:.7rem;color:var(--muted)">${IS_EN?'Group':'مجموعة'}</div>
         <div style="font-size:1rem;font-weight:800;color:${c}">${esc(g.name)}</div>`;

    document.getElementById('d-hero').style.background =
        `linear-gradient(135deg,${c}cc,${c}22,rgba(7,9,15,.9))`;

    document.getElementById('d-hero').innerHTML = `
        <div class="d-hero-inner">
            <div class="d-name">${esc(g.name)}</div>
            <div class="d-sub">
                <i class="fas fa-calendar-alt"></i> ${IS_EN?'Created:':'تاريخ الإنشاء:'} ${(g.created||'').split('T')[0]}
                ${g.updated ? ` · <i class="fas fa-pen"></i> ${IS_EN?'Updated:':'آخر تعديل:'} ${g.updated.split('T')[0]}` : ''}
            </div>
            <div class="d-stats">
                <div class="d-stat"><div class="d-stat-n" style="color:${c}">${mdata.length}</div><div class="d-stat-l">${IS_EN?'Members':'عضو'}</div></div>
                <div class="d-stat"><div class="d-stat-n" style="color:var(--green)">${doneN}</div><div class="d-stat-l">${IS_EN?'Confirmed':'مؤكد'}</div></div>
                <div class="d-stat"><div class="d-stat-n" style="color:var(--rose)">${mdata.length-doneN}</div><div class="d-stat-l">${IS_EN?'Remaining':'متبقي'}</div></div>
            </div>
        </div>`;

    // شريط التحديد
    document.getElementById('chk-bar').innerHTML = mdata.length ? `
        <span class="chk-stat">
            <i class="fas fa-circle-check"></i> ${doneN} / ${mdata.length} ${IS_EN?'confirmed':'مؤكد'}
        </span>
        <span class="chk-btns">
            <button class="chk-btn" onclick="checkAll(true)">${IS_EN?'Check all':'تحديد الكل'}</button>
            <button class="chk-btn" onclick="checkAll(false)">${IS_EN?'Uncheck all':'إلغاء الكل'}</button>
        </span>` : '';

    document.getElementById('d-members').innerHTML = mdata.map((p,i) => {
        const ageStr = p.age !== null ? p.age + (IS_EN?' yrs':' سنة') : '';
        const yStr   = p.birthYear ? `📅 ${p.birthYear}` : '';
        const done   = chkSet.has(p.id);
        return `
        <div class="d-member anim${done?' done':''}" data-pid="${p.id}" style="animation-delay:${Math.min(i*.025,.4)}s">
            <div class="d-bar" style="background:${c}"></div>
            <div class="d-chk no-print" onclick="toggleCheck(${p.id})"
                 title="${IS_EN?'Confirm':'تأكيد'}"><i class="fas fa-check"></i></div>
            <div class="d-num">${i+1}</div>
            <div class="d-info">
                <div class="d-mname">${esc(p.triple)}</div>
                <div class="d-mmeta">${ageStr}${yStr?' · '+yStr:''}</div>
            </div>
            <button class="d-rm no-print" onclick="removeMember(${p.id})"
                    title="${IS_EN?'Remove from group':'حذف من المجموعة'}">
                <i class="fas fa-xmark"></i>
            </button>
        </div>`;
    }).join('') || `<div style="text-align:center;padding:26px;color:var(--muted);font-size:.85rem">
            ${IS_EN?'No members in this group':'لا يوجد أعضاء في هذه المجموعة'}
        </div>`;

    showScreen('detail');
}

/* ─── تغيير حالة checkbox لشخص وحفظها في قاعدة البيانات ─── */
async function toggleCheck(pid) {
    if (!currentGid) return;
    const g = loadGroups().find(x => x.id === currentGid);
    if (!g) return;

    const row = document.querySelector(`.d-member[data-pid="${pid}"]`);
    const box = row ? row.querySelector('.d-chk') : null;
    const nowChecked = !(g.checked || []).includes(pid);

    // تحديث فوري للواجهة ثم الحفظ في قاعدة البيانات
    if (row) row.classList.toggle('done', nowChecked);
    if (box) box.classList.add('saving');

    try {
        await api('toggle_check', { id: currentGid, person_id: pid, checked: nowChecked ? 1 : 0 });
        showDetail(currentGid);
    } catch (e) {
        if (row) row.classList.toggle('done', !nowChecked);
        if (box) box.classList.remove('saving');
        toast('⚠ ' + errMsg(e.message), true);
    }
}

/* ─── تحديد أو إلغاء تحديد الكل ─── */
async function checkAll(val) {
    if (busy || !currentGid) return;
    busy = true;
    try {
        await api('check_all', { id: currentGid, checked: val ? 1 : 0 });
        showDetail(currentGid);
    } catch (e) {
        toast('⚠ ' + errMsg(e.message), true);
    }
    busy = false;
}

/* ─── فتح شاشة التعديل للمجموعة المفتوحة حاليًا ─── */
function editCurrent() {
    if (currentGid) showEdit(currentGid);
}

/* ─── حذف شخص واحد من المجموعة مباشرة ─── */
async function removeMember(pid) {
    if (busy || !currentGid) return;

    const p  = PEOPLE.find(x => x.id === pid);
    const nm = p ? p.triple : '';
    if (!confirm(IS_EN ? `Remove ${nm} from this group?` : `حذف ${nm} من هذه المجموعة؟`)) return;

    busy = true;
    try {
        await api('remove_member', { id: currentGid, person_id: pid });
        toast(IS_EN?'Member removed':'تم حذف الشخص');
        showDetail(currentGid);
    } catch (e) {
        toast('⚠ ' + errMsg(e.message), true);
    }
    busy = false;
}

/* ═══════════════════════════════════════
   PRINT
═══════════════════════════════════════ */
function printCurrent() { printById(currentGid); }

function printById(id) {
    const g = loadGroups().find(x=>x.id===id);
    if (!g) return;
    const c = g.color || '#333';
    const mdata = g.members.map(mid => PEOPLE.find(p=>p.id===mid)).filter(Boolean);
    const chkSet = new Set(g.checked || []);
    const doneN  = mdata.filter(p => chkSet.has(p.id)).length;

    const rows = mdata.map((p,i) => {
        const age  = p.age !== null ? p.age + (IS_EN?' yrs':' سنة') : '—';
        const year = p.birthYear || '—';
        const box  = chkSet.has(p.id) ? '☑' : '☐';
        return `<tr>
            <td style="text-align:center;font-weight:700;width:36px">${i+1}</td>
            <td style="text-align:center;width:40px;font-size:13pt">${box}</td>
            <td style="font-weight:700">${esc(p.triple)}</td>
            <td style="text-align:center;width:70px">${age}</td>
            <td style="text-align:center;width:70px">${year}</td>
        </tr>`;
    }).join('');

    const html = `
    <div class="print-pg">
        <div class="ph">
            <div>
                <div class="ph-name" style="color:${c}">${esc(g.name)}</div>
                <div class="ph-meta">${IS_EN?'Family Trip Group':'مجموعة سفرة العائلة'} · ${mdata.length} ${IS_EN?'members':'عضو'} · ${doneN} ${IS_EN?'confirmed':'مؤكد'}</div>
            </div>
            <div class="ph-date">${IS_EN?'Date:':'التاريخ:'} ${TODAY}</div>
        </div>
        <table class="pt">
            <thead><tr>
                <th>#</th>
                <th style="text-align:center">${IS_EN?'✔':'تأكيد'}</th>
                <th>${IS_EN?'Full Name (Triple)':'الاسم الثلاثي'}</th>
                <th style="text-align:center">${IS_EN?'Age':'العمر'}</th>
                <th style="text-align:center">${IS_EN?'Birth Year':'سنة الميلاد'}</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
        <div class="pf">${IS_EN?'Family Trip Groups System':'نظام مجموعات السفرة العائلية'} · ${esc(g.name)}</div>
    </div>`;

    const pa = document.getElementById('print-area');
    pa.innerHTML = html;
    pa.style.display = 'block';
    document.querySelectorAll('.page > *:not(#print-area)').forEach(el=>el.style.display='none');
    window.print();
    document.querySelectorAll('.page > *:not(#print-area)').forEach(el=>el.style.display='');
    pa.style.display = 'none';
}

/* ═══════════════════════════════════════
   INIT
═══════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', async () => {
    const wrap = document.getElementById('groups-wrap');
    wrap.innerHTML = `<div style="text-align:center;padding:40px;color:var(--muted);font-size:.85rem">
        <i class="fas fa-circle-notch fa-spin"></i>
        ${IS_EN?'Loading from database...':'جارٍ التحميل من قاعدة البيانات...'}
    </div>`;

    try {
        await api('list');
        await migrateOldData();
        renderList();
    } catch (e) {
        wrap.innerHTML = `<div style="text-align:center;padding:40px;color:var(--rose);font-size:.85rem">
            ⚠ ${IS_EN?'Could not connect to database':'تعذّر الاتصال بقاعدة البيانات'}
        </div>`;
    }
});

/* ─── نقل المجموعات القديمة من ذاكرة المتصفح إلى قاعدة البيانات (مرة واحدة) ─── */
async function migrateOldData() {
    if (localStorage.getItem(LS_KEY + '_migrated')) return;
    let old = [];
    try { old = JSON.parse(localStorage.getItem(LS_KEY) || '[]'); } catch { return; }
    if (!old.length) { localStorage.setItem(LS_KEY + '_migrated', '1'); return; }

    const msg = IS_EN
        ? `Found ${old.length} group(s) saved in this browser. Move them to the database?`
        : `تم العثور على ${old.length} مجموعة محفوظة في هذا المتصفح. هل تريد نقلها إلى قاعدة البيانات؟`;
    if (!confirm(msg)) { localStorage.setItem(LS_KEY + '_migrated', '1'); return; }

    try {
        const r = await api('import', { groups: old });
        localStorage.setItem(LS_KEY + '_migrated', '1');
        toast((IS_EN?'Imported ':'تم استيراد ') + r.imported + (IS_EN?' group(s)':' مجموعة'));
    } catch (e) {
        toast('⚠ ' + errMsg(e.message), true);
    }
}
</script>
</body>
</html>
