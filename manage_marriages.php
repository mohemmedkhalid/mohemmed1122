<?php
require_once 'header.php';

if (!isAdmin()) {
    $_SESSION['message'] = 'هذه الصفحة للمشرف فقط.';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// ── معالجة AJAX ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];

    // 1. بحث شامل بالاسم الثلاثي مع حالة الزواج
    if ($action === 'search_triple') {
        $q = '%' . $mysqli->real_escape_string(trim(preg_replace('/\s+/u', ' ', preg_replace('/(?:^|\s)(?:بن|ابن|بنت)(?=\s|$)/u', ' ', $_POST['q'] ?? '')))) . '%';
        $gender = in_array($_POST['gender'] ?? '', ['Male','Female'], true) ? $_POST['gender'] : '';
        $gWhere = $gender ? "AND p.gender='$gender'" : '';
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.full_name_en, p.gender, p.status, p.birth_date,
                   f.full_name  AS father_name, f.full_name_en AS father_name_en,
                   gf.full_name AS gf_name, gf.full_name_en AS gf_name_en,
                   ggf.full_name AS ggf_name, ggf.full_name_en AS ggf_name_en,
                   (SELECT COUNT(*) FROM marriages WHERE husband_id=p.id) AS wives_count,
                   (SELECT COUNT(*) FROM marriages WHERE wife_id=p.id)   AS is_married_female,
                   (SELECT COUNT(*) FROM people2 ch WHERE ch.father_id=p.id) AS children_count
            FROM people2 p
            LEFT JOIN people2 f   ON p.father_id  = f.id
            LEFT JOIN people2 gf  ON f.father_id  = gf.id
            LEFT JOIN people2 ggf ON gf.father_id = ggf.id
            WHERE (
                p.full_name LIKE '$q'
                OR CONCAT(p.full_name,' ',IFNULL(f.full_name,'')) LIKE '$q'
                OR CONCAT(p.full_name,' ',IFNULL(f.full_name,''),' ',IFNULL(gf.full_name,'')) LIKE '$q'
            ) $gWhere
            ORDER BY p.full_name LIMIT 80
        ");
        $out = [];
        while ($r = $rows->fetch_assoc()) {
            $r['full_name'] = get_person_name($r);
            if(isset($r['father_name'])) $r['father_name'] = get_person_name(['full_name'=>$r['father_name'], 'full_name_en'=>$r['father_name_en']]);
            if(isset($r['gf_name'])) $r['gf_name'] = get_person_name(['full_name'=>$r['gf_name'], 'full_name_en'=>$r['gf_name_en']]);
            if(isset($r['ggf_name'])) $r['ggf_name'] = get_person_name(['full_name'=>$r['ggf_name'], 'full_name_en'=>$r['ggf_name_en']]);
            $out[] = $r;
        }
        echo json_encode($out); exit;
    }

    // 2. شجرة الأسرة — أب + أجداد + أبناء
    if ($action === 'get_tree') {
        $pid = (int)$_POST['person_id'];

        // بيانات الشخص
        $person = $mysqli->query("
            SELECT p.*, f.full_name AS father_name, f.full_name_en AS father_name_en, f.id AS father_id_val,
                   gf.full_name AS gf_name, gf.full_name_en AS gf_name_en, gf.id AS gf_id,
                   ggf.full_name AS ggf_name, ggf.full_name_en AS ggf_name_en, ggf.id AS ggf_id,
                   m.full_name AS mother_name, m.full_name_en AS mother_name_en, m.id AS mother_id_val
            FROM people2 p
            LEFT JOIN people2 f   ON p.father_id  = f.id
            LEFT JOIN people2 gf  ON f.father_id  = gf.id
            LEFT JOIN people2 ggf ON gf.father_id = ggf.id
            LEFT JOIN people2 m   ON p.mother_id  = m.id
            WHERE p.id=$pid
        ")->fetch_assoc();

        if (!$person) { echo json_encode(null); exit; }
        
        $person['full_name'] = get_person_name($person);
        if(isset($person['father_name'])) $person['father_name'] = get_person_name(['full_name'=>$person['father_name'], 'full_name_en'=>$person['father_name_en']]);
        if(isset($person['gf_name'])) $person['gf_name'] = get_person_name(['full_name'=>$person['gf_name'], 'full_name_en'=>$person['gf_name_en']]);
        if(isset($person['ggf_name'])) $person['ggf_name'] = get_person_name(['full_name'=>$person['ggf_name'], 'full_name_en'=>$person['ggf_name_en']]);
        if(isset($person['mother_name'])) $person['mother_name'] = get_person_name(['full_name'=>$person['mother_name'], 'full_name_en'=>$person['mother_name_en']]);

        // زوجاته / زوجها
        $marriages = [];
        if ($person['gender'] === 'Male') {
            $mRows = $mysqli->query("
                SELECT m.id AS marriage_id, p.id, p.full_name, p.full_name_en, p.status, p.birth_date,
                       f.full_name AS father_name, f.full_name_en AS father_name_en,
                       (SELECT COUNT(*) FROM people2 c WHERE c.father_id=$pid AND c.mother_id=p.id) AS children_count
                FROM marriages m
                INNER JOIN people2 p ON m.wife_id=p.id
                LEFT JOIN  people2 f ON p.father_id=f.id
                WHERE m.husband_id=$pid ORDER BY p.full_name
            ");
            while ($r = $mRows->fetch_assoc()) {
                $r['full_name'] = get_person_name($r);
                if(isset($r['father_name'])) $r['father_name'] = get_person_name(['full_name'=>$r['father_name'], 'full_name_en'=>$r['father_name_en']]);
                $marriages[] = $r;
            }
        } else {
            $h = $mysqli->query("
                SELECT m.id AS marriage_id, p.id, p.full_name, p.full_name_en, p.status,
                       f.full_name AS father_name, f.full_name_en AS father_name_en
                FROM marriages m
                INNER JOIN people2 p ON m.husband_id=p.id
                LEFT JOIN  people2 f ON p.father_id=f.id
                WHERE m.wife_id=$pid LIMIT 1
            ")->fetch_assoc();
            if ($h) {
                $h['full_name'] = get_person_name($h);
                if(isset($h['father_name'])) $h['father_name'] = get_person_name(['full_name'=>$h['father_name'], 'full_name_en'=>$h['father_name_en']]);
                $marriages[] = $h;
            }
        }

        // الأبناء
        $children = [];
        $cRows = $mysqli->query("
            SELECT p.id, p.full_name, p.full_name_en, p.gender, p.status, p.birth_date,
                   mo.full_name AS mother_name, mo.full_name_en AS mother_name_en, mo.id AS mother_id,
                   (SELECT COUNT(*) FROM people2 cc WHERE cc.father_id=p.id) AS has_children,
                   (SELECT COUNT(*) FROM marriages WHERE husband_id=p.id) AS wives_count,
                   (SELECT COUNT(*) FROM marriages WHERE wife_id=p.id) AS is_married_f
            FROM people2 p
            LEFT JOIN people2 mo ON p.mother_id=mo.id
            WHERE p.father_id=$pid
            ORDER BY p.birth_date ASC, p.full_name
        ");
        while ($r = $cRows->fetch_assoc()) {
            $r['full_name'] = get_person_name($r);
            if(isset($r['mother_name'])) $r['mother_name'] = get_person_name(['full_name'=>$r['mother_name'], 'full_name_en'=>$r['mother_name_en']]);
            $children[] = $r;
        }

        // إخوة الشخص
        $siblings = [];
        if ($person['father_id']) {
            $sRows = $mysqli->query("
                SELECT p.id, p.full_name, p.full_name_en, p.gender, p.status
                FROM people2 p
                WHERE p.father_id={$person['father_id']} AND p.id!=$pid
                ORDER BY p.birth_date ASC, p.full_name LIMIT 20
            ");
            while ($r = $sRows->fetch_assoc()) {
                $r['full_name'] = get_person_name($r);
                $siblings[] = $r;
            }
        }

        echo json_encode([
            'person'    => $person,
            'marriages' => $marriages,
            'children'  => $children,
            'siblings'  => $siblings,
        ]); exit;
    }

    // 3. النساء غير المرتبطات
    if ($action === 'available_women') {
        $hid = (int)($_POST['husband_id'] ?? 0);
        $q   = '%' . $mysqli->real_escape_string(trim(preg_replace('/\s+/u', ' ', preg_replace('/(?:^|\s)(?:بن|ابن|بنت)(?=\s|$)/u', ' ', $_POST['q'] ?? '')))) . '%';
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.full_name_en, p.status, p.birth_date,
                   f.full_name AS father_name, f.full_name_en AS father_name_en,
                   gf.full_name AS gf_name, gf.full_name_en AS gf_name_en
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id=f.id
            LEFT JOIN people2 gf ON f.father_id=gf.id
            LEFT JOIN marriages m ON m.wife_id=p.id
            WHERE p.gender='Female'
              AND (p.full_name LIKE '$q'
                   OR CONCAT(p.full_name,' ',IFNULL(f.full_name,'')) LIKE '$q'
                   OR CONCAT(p.full_name,' ',IFNULL(f.full_name,''),' ',IFNULL(gf.full_name,'')) LIKE '$q')
              AND (m.id IS NULL OR m.husband_id=$hid)
            ORDER BY p.full_name LIMIT 500
        ");
        $out = [];
        while ($r = $rows->fetch_assoc()) {
            $r['full_name'] = get_person_name($r);
            if(isset($r['father_name'])) $r['father_name'] = get_person_name(['full_name'=>$r['father_name'], 'full_name_en'=>$r['father_name_en']]);
            if(isset($r['gf_name'])) $r['gf_name'] = get_person_name(['full_name'=>$r['gf_name'], 'full_name_en'=>$r['gf_name_en']]);
            $out[] = $r;
        }
        echo json_encode($out); exit;
    }

    // 4. ربط زواج
    if ($action === 'add_marriage') {
        $hid = (int)$_POST['husband_id'];
        $wid = (int)$_POST['wife_id'];
        if (!$hid || !$wid) { echo json_encode(['ok'=>false,'error'=>'بيانات غير مكتملة']); exit; }
        $exists = $mysqli->query("SELECT id FROM marriages WHERE husband_id=$hid AND wife_id=$wid")->fetch_assoc();
        if ($exists) { echo json_encode(['ok'=>false,'error'=>'هذا الزواج مسجل مسبقاً']); exit; }
        $hasHusband = $mysqli->query("SELECT id FROM marriages WHERE wife_id=$wid")->fetch_assoc();
        if ($hasHusband) { echo json_encode(['ok'=>false,'error'=>'هذه المرأة مرتبطة بزوج آخر']); exit; }
        $ok = $mysqli->query("INSERT INTO marriages (husband_id,wife_id) VALUES ($hid,$wid)");
        echo json_encode(['ok'=>$ok,'id'=>$mysqli->insert_id,'error'=>$mysqli->error]); exit;
    }

    // 5. فك الارتباط
    if ($action === 'delete_marriage') {
        $mid = (int)$_POST['marriage_id'];
        $act = $_POST['children_action'] ?? 'keep';
        $m = $mysqli->query("SELECT husband_id,wife_id FROM marriages WHERE id=$mid")->fetch_assoc();
        if (!$m) { echo json_encode(['ok'=>false,'error'=>'الزواج غير موجود']); exit; }
        if ($act === 'unlink') {
            $mysqli->query("UPDATE people2 SET mother_id=NULL WHERE father_id={$m['husband_id']} AND mother_id={$m['wife_id']}");
        }
        $ok = $mysqli->query("DELETE FROM marriages WHERE id=$mid");
        echo json_encode(['ok'=>$ok,'error'=>$mysqli->error]); exit;
    }

    // 6. إضافة طفل
    if ($action === 'add_child') {
        $name   = $mysqli->real_escape_string(trim($_POST['full_name'] ?? ''));
        $gender = in_array($_POST['gender']??'',['Male','Female']) ? $_POST['gender'] : 'Male';
        $status = in_array($_POST['status']??'',['Alive','Deceased']) ? $_POST['status'] : 'Alive';
        $bdate  = !empty($_POST['birth_date']) ? "'".$mysqli->real_escape_string($_POST['birth_date'])."'" : 'NULL';
        $fid    = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : 'NULL';
        $mid    = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : 'NULL';
        if (!$name) { echo json_encode(['ok'=>false,'error'=>'الاسم مطلوب']); exit; }
        $mysqli->query("INSERT INTO people2 (full_name,gender,status,birth_date,father_id,mother_id) VALUES ('$name','$gender','$status',$bdate,$fid,$mid)");
        echo json_encode(['ok'=>true,'id'=>$mysqli->insert_id,'name'=>$name]); exit;
    }

    // 7. زوجات الأب (لاختيار الأم عند إضافة طفل)
    if ($action === 'wives_for_child') {
        $fid = (int)$_POST['father_id'];
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.full_name_en FROM people2 p
            INNER JOIN marriages m ON p.id=m.wife_id AND m.husband_id=$fid
            ORDER BY p.full_name
        ");
        $out = [];
        while ($r = $rows->fetch_assoc()) {
            $r['full_name'] = get_person_name($r);
            $out[] = $r;
        }
        echo json_encode($out); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown action']); exit;
}

// إحصائيات
$stats = $mysqli->query("
    SELECT
      (SELECT COUNT(*) FROM people2) AS total_people,
      (SELECT COUNT(*) FROM people2 WHERE gender='Male') AS total_males,
      (SELECT COUNT(*) FROM people2 WHERE gender='Female') AS total_females,
      (SELECT COUNT(*) FROM marriages) AS total_marriages,
      (SELECT COUNT(*) FROM people2 WHERE gender='Female'
         AND id NOT IN (SELECT wife_id FROM marriages)) AS available_women
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>شجرة الزواج — بحث وربط</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
/* ═══ RESET & BASE ═══ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#080b10;
  --s1:#0e1218;
  --s2:#141a22;
  --s3:#1b222d;
  --s4:#222b38;
  --border:#1e2a38;
  --border2:#2a3848;
  --accent:#4f8ef7;
  --accent2:#7ab0ff;
  --accentG:#22d3a8;
  --accentR:#f05c6b;
  --accentY:#f0b429;
  --pink:#f472b6;
  --purple:#a78bfa;
  --text:#dde6f0;
  --text2:#7d8fa3;
  --text3:#3d4f63;
  --male:#60a5fa;
  --female:#f472b6;
  --tree-line:#2a3848;
  --r:10px;
  --sh:0 8px 40px rgba(0,0,0,.7);
}
html,body{height:100%;font-family:'Tajawal',sans-serif;background:var(--bg);color:var(--text)}
body{display:flex;flex-direction:column;overflow:hidden;font-size:14px}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:5px}

/* ═══ TOPBAR ═══ */
.topbar{height:52px;background:var(--s1);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 18px;gap:12px;flex-shrink:0;z-index:200}
.brand{font-weight:900;font-size:1rem;color:var(--accent2);display:flex;align-items:center;gap:8px}
.brand i{color:var(--pink)}
.nav-links{display:flex;gap:5px;margin-right:auto}
.nl{background:none;border:1px solid var(--border);color:var(--text2);padding:4px 12px;
  border-radius:7px;cursor:pointer;font-family:inherit;font-size:.78rem;text-decoration:none;
  display:inline-flex;align-items:center;gap:5px;transition:.15s}
.nl:hover{border-color:var(--accent);color:var(--accent2);background:rgba(79,142,247,.07)}
.stat-pill{background:var(--s2);border:1px solid var(--border);border-radius:20px;
  padding:3px 10px;font-size:.73rem;color:var(--text2);display:flex;align-items:center;gap:4px}
.stat-pill b{color:var(--text);font-weight:700}
.stat-pill.women b{color:var(--pink)}
.stat-pill.marriages b{color:var(--accentG)}

/* ═══ MAIN LAYOUT ═══ */
.workspace{display:grid;grid-template-columns:290px 1fr 320px;height:calc(100vh - 52px);overflow:hidden}
.panel{display:flex;flex-direction:column;overflow:hidden;border-left:1px solid var(--border)}
.panel:first-child{border-left:none}
.ph{padding:11px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;
  flex-shrink:0;background:var(--s1)}
.ph h3{font-size:.83rem;font-weight:700;color:var(--text)}
.ph .badge{margin-right:auto;background:rgba(79,142,247,.12);color:var(--accent2);
  border-radius:20px;padding:2px 8px;font-size:.7rem;font-weight:700}
.pb{flex:1;overflow-y:auto;padding:10px}

/* ═══ SEARCH ═══ */
.sw{position:relative;margin-bottom:8px}
.sw i{position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:.78rem;pointer-events:none}
.si{width:100%;background:var(--s2);border:1px solid var(--border);border-radius:8px;
  padding:8px 32px 8px 10px;color:var(--text);font-family:inherit;font-size:.83rem;outline:none;transition:.15s}
.si:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,142,247,.1)}
.si::placeholder{color:var(--text3)}

/* ═══ SEG BUTTONS ═══ */
.seg{display:flex;gap:4px;margin-bottom:8px}
.sb{flex:1;padding:5px;border-radius:7px;border:1px solid var(--border);
  background:none;color:var(--text2);font-family:inherit;font-size:.74rem;cursor:pointer;transition:.14s;font-weight:700}
.sb.on{background:rgba(79,142,247,.12);border-color:var(--accent);color:var(--accent2)}
.sb.f.on{background:rgba(244,114,182,.12);border-color:var(--pink);color:var(--pink)}

/* ═══ PERSON CARD IN LIST ═══ */
.pc{display:flex;align-items:center;gap:8px;padding:7px 9px;border-radius:8px;
  cursor:pointer;transition:.12s;border:1px solid transparent;margin-bottom:3px}
.pc:hover{background:var(--s2);border-color:var(--border)}
.pc.sel{background:rgba(79,142,247,.09);border-color:var(--accent)}
.av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:.8rem;flex-shrink:0}
.av.m{background:rgba(96,165,250,.15);color:var(--male);border:1.5px solid rgba(96,165,250,.3)}
.av.f{background:rgba(244,114,182,.15);color:var(--female);border:1.5px solid rgba(244,114,182,.3)}
.pname{font-size:.82rem;font-weight:700;color:var(--text);line-height:1.2}
.psub{font-size:.69rem;color:var(--text3);margin-top:1px}
.marriage-badge{font-size:.68rem;padding:1px 6px;border-radius:4px;font-weight:700;margin-top:2px;display:inline-block}
.mb-married{background:rgba(34,211,168,.1);color:#6ee7b7;border:1px solid rgba(34,211,168,.2)}
.mb-single{background:rgba(240,92,107,.08);color:#fca5a5;border:1px solid rgba(240,92,107,.18)}
.mb-wives{background:rgba(79,142,247,.1);color:var(--accent2);border:1px solid rgba(79,142,247,.2)}

/* ═══ EMPTY STATE ═══ */
.empty{text-align:center;padding:30px 10px;color:var(--text3)}
.empty i{font-size:2rem;margin-bottom:10px;opacity:.3;display:block}
.empty span{font-size:.8rem}

/* ═══ TABS ═══ */
.ctabs{display:flex;border-bottom:1px solid var(--border);flex-shrink:0;background:var(--s1)}
.ctab{flex:1;padding:10px 6px;background:none;border:none;color:var(--text3);
  font-family:inherit;font-size:.76rem;font-weight:700;cursor:pointer;transition:.14s;
  border-bottom:2px solid transparent;display:flex;align-items:center;justify-content:center;gap:5px}
.ctab:hover{color:var(--text2)}
.ctab.on{color:var(--accent2);border-bottom-color:var(--accent)}
.cpanel{display:none;flex:1;overflow-y:auto;padding:10px}
.cpanel.on{display:block}

/* ═══ TREE SECTION ═══ */
.tree-wrap{padding:6px 2px}

/* Ancestor chain */
.ancestor-chain{display:flex;flex-direction:column;gap:0;margin-bottom:16px}
.anc-node{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;
  background:var(--s2);border:1px solid var(--border);cursor:pointer;transition:.13s;
  margin-bottom:4px;position:relative}
.anc-node:hover{border-color:var(--accent);background:rgba(79,142,247,.07)}
.anc-node::before{content:'';position:absolute;right:16px;top:-4px;width:1px;height:4px;
  background:var(--tree-line)}
.anc-node:first-child::before{display:none}
.anc-level{font-size:.66rem;color:var(--text3);font-weight:700;min-width:32px}
.anc-name{font-size:.82rem;font-weight:700;flex:1}
.anc-sub{font-size:.68rem;color:var(--text3);margin-top:1px}

/* Current person node */
.current-node{background:rgba(79,142,247,.1);border:2px solid var(--accent);border-radius:12px;
  padding:14px 16px;margin:10px 0;position:relative}
.cn-name{font-size:1rem;font-weight:900;color:var(--accent2);margin-bottom:4px}
.cn-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.cn-tag{font-size:.72rem;padding:2px 8px;border-radius:5px;font-weight:700}
.cn-tag.m{background:rgba(96,165,250,.12);color:var(--male)}
.cn-tag.f{background:rgba(244,114,182,.12);color:var(--female)}
.cn-tag.alive{background:rgba(34,211,168,.1);color:#6ee7b7}
.cn-tag.dead{background:rgba(107,114,128,.1);color:#9ca3af}
.cn-tag.married{background:rgba(79,142,247,.12);color:var(--accent2)}
.cn-tag.single{background:rgba(240,92,107,.08);color:#fca5a5}
.cn-actions{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap}

/* Wives row */
.wives-strip{background:var(--s2);border:1px solid var(--border);border-radius:10px;
  padding:10px;margin:8px 0}
.wives-strip-title{font-size:.73rem;color:var(--text3);font-weight:700;margin-bottom:8px;
  display:flex;align-items:center;gap:5px}
.wife-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(244,114,182,.08);
  border:1px solid rgba(244,114,182,.2);border-radius:20px;padding:3px 10px;
  font-size:.76rem;color:var(--pink);cursor:pointer;transition:.13s;margin:2px}
.wife-chip:hover{background:rgba(244,114,182,.15);border-color:var(--pink)}
.wife-chip .unlink-btn{color:rgba(244,114,182,.5);font-size:.65rem;cursor:pointer;
  margin-right:3px;transition:.13s}
.wife-chip:hover .unlink-btn{color:var(--accentR)}

/* Children grid */
.children-section{margin-top:10px}
.children-title{font-size:.73rem;color:var(--text3);font-weight:700;margin-bottom:8px;
  display:flex;align-items:center;gap:5px}
.child-card{display:flex;align-items:center;gap:8px;padding:8px 10px;
  background:var(--s3);border:1px solid var(--border);border-radius:9px;
  cursor:pointer;transition:.13s;margin-bottom:5px}
.child-card:hover{border-color:var(--border2);background:var(--s2)}
.child-card.has-children{border-right:3px solid var(--accentG)}
.cname{font-size:.82rem;font-weight:700}
.csub{font-size:.69rem;color:var(--text3);margin-top:1px}
.cacts{display:flex;gap:4px;margin-right:auto;flex-shrink:0}

/* Siblings */
.siblings-section{margin-top:10px}
.sibling-chip{display:inline-flex;align-items:center;gap:4px;background:var(--s3);
  border:1px solid var(--border);border-radius:6px;padding:3px 8px;
  font-size:.74rem;cursor:pointer;transition:.12s;margin:2px}
.sibling-chip:hover{border-color:var(--border2);background:var(--s2)}
.sibling-chip.m{color:var(--male)}
.sibling-chip.f{color:var(--pink)}

/* ═══ RIGHT PANEL — AVAILABLE WOMEN ═══ */
.section-title{font-size:.72rem;color:var(--text3);font-weight:700;text-transform:uppercase;
  letter-spacing:.5px;padding:8px 0 5px;border-bottom:1px solid var(--border);margin-bottom:8px}
.woman-row{display:flex;align-items:center;gap:7px;padding:7px 8px;border-radius:8px;
  background:var(--s2);border:1px solid var(--border);margin-bottom:4px;transition:.13s}
.woman-row:hover{border-color:var(--pink);background:rgba(244,114,182,.04)}
.wrow-name{flex:1}
.wrow-name .n{font-size:.81rem;font-weight:700;color:var(--text)}
.wrow-name .s{font-size:.68rem;color:var(--text3);margin-top:1px}
.btn-link-w{padding:4px 10px;border-radius:6px;border:1px solid var(--pink);
  background:rgba(244,114,182,.08);color:var(--pink);font-family:inherit;font-size:.73rem;
  font-weight:700;cursor:pointer;transition:.13s;white-space:nowrap}
.btn-link-w:hover{background:var(--pink);color:#fff}
.btn-link-w:disabled{opacity:.4;cursor:not-allowed}

/* ═══ BUTTONS ═══ */
.btn{padding:8px 14px;border-radius:8px;border:none;font-family:inherit;font-size:.8rem;
  font-weight:700;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:6px}
.btn:hover{filter:brightness(1.1);transform:translateY(-1px)}
.btn-p{background:var(--accent);color:#fff}
.btn-g{background:var(--accentG);color:#001a13}
.btn-r{background:var(--accentR);color:#fff}
.btn-o{background:var(--pink);color:#fff}
.btn-ghost{background:var(--s3);border:1px solid var(--border2);color:var(--text2)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent2)}
.btn-sm{padding:5px 9px;font-size:.74rem;border-radius:6px}
.btn-full{width:100%}
.ib{width:27px;height:27px;border-radius:6px;border:1px solid var(--border);
  background:var(--s2);color:var(--text3);cursor:pointer;display:flex;align-items:center;
  justify-content:center;font-size:.72rem;transition:.12s;flex-shrink:0}
.ib:hover{border-color:var(--border2);color:var(--text)}
.ib.del:hover{border-color:var(--accentR);color:var(--accentR);background:rgba(240,92,107,.07)}
.ib.add:hover{border-color:var(--accentG);color:var(--accentG);background:rgba(34,211,168,.07)}
.ib.go:hover{border-color:var(--accent);color:var(--accent2);background:rgba(79,142,247,.07)}

/* ═══ MESSAGES ═══ */
.msg{padding:8px 12px;border-radius:7px;font-size:.8rem;font-weight:600;margin-bottom:8px;
  animation:slideIn .2s ease;display:none}
.msg.ok{background:rgba(34,211,168,.1);border:1px solid rgba(34,211,168,.25);color:#6ee7b7}
.msg.err{background:rgba(240,92,107,.1);border:1px solid rgba(240,92,107,.3);color:#fca5a5}
@keyframes slideIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

/* ═══ MODALS ═══ */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;
  display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);
  animation:fadeIn .15s ease}
.modal-bg.hidden{display:none}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal{background:var(--s1);border:1px solid var(--border2);border-radius:14px;
  padding:22px;min-width:360px;max-width:480px;width:90%;max-height:88vh;overflow-y:auto;
  animation:popIn .18s ease;box-shadow:var(--sh)}
@keyframes popIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-head{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:12px;
  border-bottom:1px solid var(--border)}
.modal-head h4{font-size:.93rem;font-weight:800;color:var(--text);flex:1}
.modal-close{background:none;border:none;color:var(--text3);cursor:pointer;font-size:1.1rem;
  padding:2px 6px;border-radius:5px;transition:.13s;line-height:1}
.modal-close:hover{color:var(--text);background:var(--s3)}
.modal-actions{display:flex;gap:8px;margin-top:14px}
.modal-actions .btn{flex:1}

/* ═══ FORM ═══ */
.fg{margin-bottom:11px}
.fl{font-size:.74rem;color:var(--text2);font-weight:700;margin-bottom:4px;display:block}
.fi{width:100%;background:var(--s2);border:1px solid var(--border);border-radius:8px;
  padding:8px 10px;color:var(--text);font-family:inherit;font-size:.83rem;outline:none;transition:.15s}
.fi:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,142,247,.1)}
.fi::placeholder{color:var(--text3)}
select.fi{appearance:none;
  background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%234a5668' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
  background-repeat:no-repeat;background-position:left 8px center;background-size:1.2em}
select.fi option{background:#141a22}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:9px}

/* ═══ PICKER ═══ */
.picker-wrap{position:relative}
.picker-trigger{display:flex;align-items:center;gap:8px;background:var(--s2);
  border:1px solid var(--border);border-radius:8px;padding:8px 10px;cursor:pointer;transition:.15s}
.picker-trigger:hover,.picker-trigger.open-p{border-color:var(--accent)}
.pv{flex:1;font-size:.82rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pv.empty{color:var(--text3)}
.picker-dropdown{display:none;position:absolute;z-index:600;top:calc(100% + 4px);right:0;left:0;
  background:var(--s1);border:1px solid var(--border2);border-radius:10px;
  box-shadow:0 10px 40px rgba(0,0,0,.6);overflow:hidden}
.picker-dropdown.open{display:block}
.picker-search-in{width:100%;background:var(--s2);border:none;border-bottom:1px solid var(--border);
  padding:9px 12px;color:var(--text);font-family:inherit;font-size:.83rem;outline:none}
.picker-search-in::placeholder{color:var(--text3)}
.picker-list{max-height:200px;overflow-y:auto}
.po{padding:8px 12px;cursor:pointer;font-size:.81rem;color:var(--text);transition:.1s;line-height:1.35}
.po:hover,.po.active{background:rgba(79,142,247,.1);color:var(--accent2)}
.po .sub{font-size:.69rem;color:var(--text3);margin-top:1px}
.po-empty{padding:14px;text-align:center;color:var(--text3);font-size:.78rem}

/* ═══ SPINNER ═══ */
.spin{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);
  border-radius:50%;animation:rot .7s linear infinite;display:inline-block;vertical-align:middle}
@keyframes rot{to{transform:rotate(360deg)}}

/* ═══ CONFIRM OPTS ═══ */
.confirm-opts{display:flex;flex-direction:column;gap:7px;margin:10px 0}
.confirm-opt{display:flex;align-items:flex-start;gap:8px;background:var(--s2);
  border:1px solid var(--border);border-radius:8px;padding:10px;cursor:pointer;transition:.13s}
.confirm-opt:hover{border-color:var(--border2)}
.confirm-opt input{margin-top:2px;flex-shrink:0}
.confirm-opt strong{font-size:.8rem;color:var(--text);display:block}
.confirm-opt small{font-size:.7rem;color:var(--text3)}

/* ═══ BREADCRUMB ═══ */
.breadcrumb{display:flex;align-items:center;gap:3px;flex-wrap:wrap;margin-bottom:10px;
  padding:6px 10px;background:var(--s2);border-radius:8px;border:1px solid var(--border)}
.bc-item{font-size:.73rem;color:var(--text3);cursor:pointer;transition:.12s;
  padding:1px 5px;border-radius:4px}
.bc-item:hover{color:var(--accent2);background:rgba(79,142,247,.08)}
.bc-item.current{color:var(--accent2);font-weight:700;cursor:default}
.bc-item.current:hover{background:none}
.bc-sep{color:var(--text3);font-size:.7rem}

/* ═══ RESPONSIVE ═══ */
@media(max-width:900px){
  .workspace{grid-template-columns:1fr;height:auto;overflow:auto}
  .panel{border-left:none;border-top:1px solid var(--border);min-height:300px}
}
</style>
<link rel="stylesheet" href="assets/family-mobile.css?v=5">
<script src="assets/family-ui.js?v=5"></script>
</head>
<body data-family-page="marriages">

<!-- TOP BAR -->
<div class="topbar">
  <div class="brand"><i class="fas fa-tree"></i> إدارة الزواج والعائلة</div>
  <div class="nav-links">
    <a href="index.php"            class="nl"><i class="fas fa-home"></i> الرئيسية</a>
    <a href="manage_marriages.php" class="nl"><i class="fas fa-ring"></i> إدارة الزواج</a>
    <a href="family_list.php"      class="nl"><i class="fas fa-list"></i> القائمة</a>
    <a href="tree.php"             class="nl"><i class="fas fa-sitemap"></i> الشجرة</a>
  </div>
  <div class="stat-pill"><i class="fas fa-users" style="color:var(--accent2)"></i> <b><?= $stats['total_people'] ?></b> شخص</div>
  <div class="stat-pill marriages"><i class="fas fa-ring"></i> <b><?= $stats['total_marriages'] ?></b> زواج</div>
  <div class="stat-pill women"><i class="fas fa-female"></i> <b><?= $stats['available_women'] ?></b> غير مرتبطة</div>
</div>

<!-- WORKSPACE -->
<div class="workspace">

  <!-- ══════ PANEL 1: البحث ══════ -->
  <div class="panel">
    <div class="ph">
      <i class="fas fa-search" style="color:var(--accent2)"></i>
      <h3>البحث بالاسم الثلاثي</h3>
      <span class="badge" id="searchBadge">—</span>
    </div>
    <div class="pb">
      <div class="seg">
        <button class="sb on" data-g="">الكل</button>
        <button class="sb" data-g="Male">ذكور</button>
        <button class="sb f" data-g="Female">إناث</button>
      </div>
      <div class="sw">
        <i class="fas fa-search"></i>
        <input type="text" class="si" id="mainSearch" placeholder="اسم أو اسم أب أو جد…">
      </div>
      <div id="searchResults">
        <div class="empty"><i class="fas fa-keyboard"></i><span>ابحث عن شخص</span></div>
      </div>
    </div>
  </div>

  <!-- ══════ PANEL 2: الشجرة ══════ -->
  <div class="panel" style="border-left:none">
    <div class="ctabs">
      <button class="ctab on" data-tab="treeTab"><i class="fas fa-sitemap"></i> شجرة العائلة</button>
      <button class="ctab"    data-tab="addMarTab"><i class="fas fa-link"></i> ربط زواج</button>
    </div>

    <!-- TAB: الشجرة -->
    <div class="cpanel on" id="treeTab">
      <div id="treeEmpty" class="empty">
        <i class="fas fa-tree"></i>
        <span>اختر شخصاً من البحث لعرض شجرته العائلية</span>
      </div>
      <div id="treeContent" style="display:none">
        <div class="msg" id="treeMsg"></div>
        <!-- BREADCRUMB NAVIGATION -->
        <div id="treeBreadcrumb" class="breadcrumb" style="display:none"></div>

        <div class="tree-wrap">
          <!-- سلسلة الأجداد -->
          <div id="ancestorChain"></div>
          <!-- الشخص الحالي -->
          <div id="currentNodeWrap"></div>
          <!-- الزوجات -->
          <div id="wivesStripWrap"></div>
          <!-- الأبناء -->
          <div id="childrenSectionWrap"></div>
          <!-- الإخوة -->
          <div id="siblingsSectionWrap"></div>
        </div>
      </div>
    </div>

    <!-- TAB: ربط زواج -->
    <div class="cpanel" id="addMarTab">
      <div style="background:var(--s2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px">
        <div style="font-size:.78rem;font-weight:800;color:var(--accent2);margin-bottom:12px;
          display:flex;align-items:center;gap:7px">
          <i class="fas fa-ring" style="color:var(--pink)"></i> ربط زواج جديد
        </div>
        <div class="msg" id="linkMsg"></div>

        <div class="fg">
          <label class="fl">الزوج (رجل)</label>
          <div class="picker-wrap">
            <div class="picker-trigger" id="lhTrig" onclick="openPicker('lh')">
              <i class="fas fa-male" style="color:var(--male);font-size:.78rem"></i>
              <span class="pv empty" id="lhVal">— اختر الزوج —</span>
              <i class="fas fa-chevron-down" style="font-size:.63rem;color:var(--text3)"></i>
            </div>
            <div class="picker-dropdown" id="lhDrop">
              <input type="text" class="picker-search-in" id="lhSearch"
                     placeholder="ابحث عن الزوج…" oninput="filterPicker('lh',this.value)">
              <div class="picker-list" id="lhList"></div>
            </div>
          </div>
          <input type="hidden" id="lhId">
        </div>

        <div class="fg">
          <label class="fl">الزوجة <span style="color:var(--text3)">(فقط غير المرتبطات)</span></label>
          <div class="picker-wrap">
            <div class="picker-trigger" id="lwTrig" onclick="openPicker('lw')">
              <i class="fas fa-female" style="color:var(--pink);font-size:.78rem"></i>
              <span class="pv empty" id="lwVal">— اختر الزوجة —</span>
              <i class="fas fa-chevron-down" style="font-size:.63rem;color:var(--text3)"></i>
            </div>
            <div class="picker-dropdown" id="lwDrop">
              <input type="text" class="picker-search-in" id="lwSearch"
                     placeholder="ابحث عن الزوجة…" oninput="filterPicker('lw',this.value)">
              <div class="picker-list" id="lwList"></div>
            </div>
          </div>
          <input type="hidden" id="lwId">
        </div>

        <button class="btn btn-p btn-full" onclick="submitLink()">
          <i class="fas fa-link"></i> ربط الزواج
        </button>
      </div>

      <!-- إضافة طفل سريع -->
      <div style="background:var(--s2);border:1px solid var(--border);border-radius:12px;padding:14px">
        <div style="font-size:.78rem;font-weight:800;color:var(--accentG);margin-bottom:12px;
          display:flex;align-items:center;gap:7px">
          <i class="fas fa-baby"></i> إضافة ابن/ابنة
        </div>
        <div class="msg" id="addChildMsg2"></div>
        <div id="quickAddChildForm">
          <div class="fg">
            <label class="fl">الأب</label>
            <input type="text" class="fi" id="qcFatherName" readonly style="opacity:.7;cursor:default"
                   placeholder="اختر شخصاً من الشجرة أولاً…">
            <input type="hidden" id="qcFatherId">
          </div>
          <div class="fg">
            <label class="fl">الاسم الكامل *</label>
            <input type="text" class="fi" id="qcName" placeholder="اسم الابن أو الابنة…">
          </div>
          <div class="row2">
            <div class="fg">
              <label class="fl">الجنس</label>
              <select class="fi" id="qcGender">
                <option value="Male">ذكر 🟦</option>
                <option value="Female">أنثى 🟥</option>
              </select>
            </div>
            <div class="fg">
              <label class="fl">الحالة</label>
              <select class="fi" id="qcStatus">
                <option value="Alive">حي ✅</option>
                <option value="Deceased">متوفى 🕊</option>
              </select>
            </div>
          </div>
          <div class="fg">
            <label class="fl">تاريخ الميلاد</label>
            <input type="date" class="fi" id="qcBdate">
          </div>
          <div class="fg" id="qcMotherGroup" style="display:none">
            <label class="fl">الأم (من زوجات الأب)</label>
            <select class="fi" id="qcMother">
              <option value="">— اختر الأم —</option>
            </select>
          </div>
          <button class="btn btn-g btn-full" onclick="submitQuickChild()">
            <i class="fas fa-plus"></i> إضافة الابن/الابنة
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════ PANEL 3: النساء المتاحات ══════ -->
  <div class="panel">
    <div class="ph">
      <i class="fas fa-female" style="color:var(--pink)"></i>
      <h3>الإناث غير المرتبطات</h3>
      <span class="badge" style="background:rgba(244,114,182,.12);color:var(--pink)" id="womenBadge">
        <?= $stats['available_women'] ?>
      </span>
    </div>
    <div class="pb">
      <div id="rightMsg" class="msg"></div>
      <div class="sw">
        <i class="fas fa-search"></i>
        <input type="text" class="si" id="womenSearch" placeholder="ابحث باسمها أو اسم أبيها…"
               oninput="loadAvailableWomen(this.value)">
      </div>
      <div style="font-size:.71rem;color:var(--text3);margin-bottom:8px;padding:0 2px">
        انقر على <b style="color:var(--pink)">ربط</b> بعد اختيار الزوج من الشجرة
      </div>
      <div id="womenList">
        <div class="spin" style="display:block;margin:16px auto"></div>
      </div>
    </div>
  </div>

</div><!-- /workspace -->

<!-- ══════ MODALS ══════ -->

<!-- Modal: فك الارتباط -->
<div class="modal-bg hidden" id="modalDivorce">
  <div class="modal">
    <div class="modal-head">
      <i class="fas fa-heart-broken" style="color:var(--accentR)"></i>
      <h4>فك الارتباط الزوجي</h4>
      <button class="modal-close" onclick="closeModal('modalDivorce')">✕</button>
    </div>
    <p style="font-size:.82rem;color:var(--text2);margin-bottom:8px">
      ماذا تريد فعله بأبناء <strong id="divorceWifeName" style="color:var(--pink)"></strong> من هذا الزواج؟
    </p>
    <div class="confirm-opts">
      <label class="confirm-opt">
        <input type="radio" name="dvOpt" value="keep" checked>
        <div>
          <strong>✅ الإبقاء على ربط الأبناء</strong>
          <small>يبقى الأبناء مرتبطين بالأم كما هم</small>
        </div>
      </label>
      <label class="confirm-opt">
        <input type="radio" name="dvOpt" value="unlink">
        <div>
          <strong>🔗 فك ربط الأبناء عن الأم</strong>
          <small>يتم إزالة حقل الأم من أبناء هذا الزواج</small>
        </div>
      </label>
    </div>
    <input type="hidden" id="divorceMarId">
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalDivorce')">إلغاء</button>
      <button class="btn btn-r" onclick="submitDivorce()"><i class="fas fa-unlink"></i> تأكيد</button>
    </div>
  </div>
</div>

<script>
// ════════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════════
function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') }
function escJ(s){ return esc(String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/[\r\n]/g,' ')) }

async function post(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k, v ?? ''));
  const r = await fetch('', {method:'POST', body: fd});
  return r.json();
}

function showMsg(id, text, type) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = text;
  el.className = 'msg ' + type;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 4000);
}

function closeModal(id) { document.getElementById(id).classList.add('hidden') }
function openModal(id)  { document.getElementById(id).classList.remove('hidden') }

// ════════════════════════════════════════════════════════════
//  SEARCH — LEFT PANEL
// ════════════════════════════════════════════════════════════
let searchGender = '';
let searchTimer = null;
let currentPersonId = null;
let navHistory = []; // breadcrumb history

document.querySelectorAll('.sb').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.sb').forEach(x => x.classList.remove('on'));
    b.classList.add('on');
    searchGender = b.dataset.g;
    doSearch(document.getElementById('mainSearch').value);
  });
});

document.getElementById('mainSearch').addEventListener('input', e => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => doSearch(e.target.value), 280);
});

async function doSearch(q) {
  const el = document.getElementById('searchResults');
  const badge = document.getElementById('searchBadge');
  if (!q.trim()) {
    el.innerHTML = `<div class="empty"><i class="fas fa-keyboard"></i><span>ابحث عن شخص</span></div>`;
    badge.textContent = '—'; return;
  }
  el.innerHTML = `<div style="text-align:center;padding:16px"><span class="spin"></span></div>`;
  const data = await post({ ajax_action:'search_triple', q, gender:searchGender });
  badge.textContent = data.length;
  if (!data.length) {
    el.innerHTML = `<div class="empty"><i class="fas fa-search"></i><span>لا توجد نتائج</span></div>`;
    return;
  }
  el.innerHTML = data.map(p => {
    const isMale = p.gender === 'Male';
    const triple = [p.full_name, p.father_name, p.gf_name].filter(Boolean).join(' ← ');
    let badge2 = '';
    if (isMale && p.wives_count > 0)
      badge2 = `<span class="marriage-badge mb-wives"><i class="fas fa-ring"></i> ${p.wives_count} زوجة</span>`;
    else if (!isMale && p.is_married_female > 0)
      badge2 = `<span class="marriage-badge mb-married"><i class="fas fa-ring"></i> متزوجة</span>`;
    else
      badge2 = `<span class="marriage-badge mb-single">${isMale ? 'أعزب' : 'غير مرتبطة'}</span>`;
    return `<div class="pc ${p.id==currentPersonId?'sel':''}" onclick="selectPerson(${p.id})">
      <div class="av ${isMale?'m':'f'}">${esc(p.full_name)[0]}</div>
      <div style="flex:1;min-width:0">
        <div class="pname">${esc(p.full_name)}</div>
        <div class="psub">${esc(triple)}</div>
        <div>${badge2}</div>
      </div>
      <button class="ib go" title="انتقال إلى الشجرة" onclick="event.stopPropagation();selectPerson(${p.id})">
        <i class="fas fa-chevron-left"></i>
      </button>
    </div>`;
  }).join('');
}

// ════════════════════════════════════════════════════════════
//  TREE — SELECT & RENDER
// ════════════════════════════════════════════════════════════
async function selectPerson(pid, addToHistory=true) {
  currentPersonId = pid;
  // tab switch
  document.querySelectorAll('.ctab').forEach(t => t.classList.toggle('on', t.dataset.tab==='treeTab'));
  document.querySelectorAll('.cpanel').forEach(p => p.classList.toggle('on', p.id==='treeTab'));

  // highlight in search list
  document.querySelectorAll('.pc').forEach(c => c.classList.remove('sel'));
  document.querySelectorAll('.pc').forEach(c => {
    if (c.getAttribute('onclick')?.includes(`(${pid})`)) c.classList.add('sel');
  });

  document.getElementById('treeEmpty').style.display = 'none';
  document.getElementById('treeContent').style.display = 'block';
  document.getElementById('ancestorChain').innerHTML =
  document.getElementById('currentNodeWrap').innerHTML =
  document.getElementById('wivesStripWrap').innerHTML =
  document.getElementById('childrenSectionWrap').innerHTML =
  document.getElementById('siblingsSectionWrap').innerHTML =
    '<div style="text-align:center;padding:12px"><span class="spin"></span></div>';

  if (addToHistory) {
    // add to nav history
    if (!navHistory.find(x=>x.id===pid)) {
      // trim to last 6
      if (navHistory.length >= 6) navHistory.shift();
    } else {
      // already in history, truncate after it
      const idx = navHistory.findIndex(x=>x.id===pid);
      navHistory = navHistory.slice(0,idx+1);
    }
  }

  const tree = await post({ ajax_action:'get_tree', person_id:pid });
  if (!tree) { showMsg('treeMsg','خطأ في جلب البيانات','err'); return; }

  // update history entry name
  const existingIdx = navHistory.findIndex(x=>x.id===pid);
  if (existingIdx >= 0) {
    navHistory[existingIdx].name = tree.person.full_name;
  } else if (addToHistory) {
    navHistory.push({id:pid, name:tree.person.full_name});
  }

  renderBreadcrumb();
  renderTree(tree);

  // update quick-add father
  document.getElementById('qcFatherName').value = tree.person.full_name;
  document.getElementById('qcFatherId').value = pid;
  loadQcMothers(pid);
}

function renderBreadcrumb() {
  const el = document.getElementById('treeBreadcrumb');
  if (navHistory.length <= 1) { el.style.display='none'; return; }
  el.style.display = 'flex';
  el.innerHTML = navHistory.map((h,i) => {
    const isLast = i === navHistory.length-1;
    return `${i>0?'<span class="bc-sep"><i class="fas fa-chevron-left" style="font-size:.6rem"></i></span>':''}
      <span class="bc-item ${isLast?'current':''}" ${!isLast?`onclick="navTo(${h.id})"`:''}>${esc(h.name)}</span>`;
  }).join('');
}

function navTo(pid) {
  const idx = navHistory.findIndex(x=>x.id===pid);
  if (idx >= 0) navHistory = navHistory.slice(0,idx+1);
  selectPerson(pid, false);
}

function renderTree(tree) {
  const p = tree.person;
  const isMale = p.gender === 'Male';

  // ── Ancestor chain ──
  const ancestors = [];
  if (p.ggf_name && p.ggf_id) ancestors.push({id:p.ggf_id, name:p.ggf_name, level:'جد الجد'});
  if (p.gf_name  && p.gf_id)  ancestors.push({id:p.gf_id,  name:p.gf_name,  level:'الجد'});
  if (p.father_name && p.father_id_val) ancestors.push({id:p.father_id_val, name:p.father_name, level:'الأب'});

  document.getElementById('ancestorChain').innerHTML = ancestors.length ? `
    <div class="ancestor-chain">
      ${ancestors.map((a,i) => `
        <div class="anc-node" onclick="selectPerson(${a.id})" style="margin-right:${i*14}px">
          <div class="anc-level">${esc(a.level)}</div>
          <div>
            <div class="anc-name" style="color:var(--male)">${esc(a.name)}</div>
            <div class="anc-sub">انقر للانتقال</div>
          </div>
          <i class="fas fa-arrow-left" style="color:var(--text3);font-size:.7rem"></i>
        </div>`).join('')}
    </div>` : '';

  // ── Current node ──
  const marStatus = isMale
    ? (tree.marriages.length ? `${tree.marriages.length} زوجة` : 'أعزب')
    : (tree.marriages.length ? 'متزوجة' : 'غير مرتبطة');
  const marClass = (isMale && tree.marriages.length) || (!isMale && tree.marriages.length) ? 'married' : 'single';

  document.getElementById('currentNodeWrap').innerHTML = `
    <div class="current-node">
      <div style="display:flex;align-items:center;gap:10px">
        <div class="av ${isMale?'m':'f'}" style="width:44px;height:44px;font-size:1rem">
          ${esc(p.full_name)[0]}
        </div>
        <div style="flex:1">
          <div class="cn-name">${esc(p.full_name)}</div>
          ${p.father_name ? `<div style="font-size:.75rem;color:var(--text2)">← ${esc(p.father_name)}${p.gf_name?' ← '+esc(p.gf_name):''}</div>` : ''}
        </div>
      </div>
      <div class="cn-meta">
        <span class="cn-tag ${isMale?'m':'f'}">${isMale?'♂ ذكر':'♀ أنثى'}</span>
        <span class="cn-tag ${p.status==='Alive'?'alive':'dead'}">${p.status==='Alive'?'✅ حي':'🕊 متوفى'}</span>
        <span class="cn-tag ${marClass}">${esc(marStatus)}</span>
        ${p.birth_date ? `<span class="cn-tag" style="background:var(--s3);color:var(--text2)"><i class="fas fa-birthday-cake"></i> ${p.birth_date}</span>` : ''}
        ${tree.children.length ? `<span class="cn-tag" style="background:rgba(34,211,168,.1);color:#6ee7b7">${tree.children.length} أبناء</span>` : ''}
      </div>
      ${isMale ? `<div class="cn-actions">
        <button class="btn btn-o btn-sm" onclick="quickLinkWife(${p.id},'${escJ(p.full_name)}')">
          <i class="fas fa-link"></i> إضافة زوجة
        </button>
        <button class="btn btn-g btn-sm" onclick="switchToAddChild(${p.id},'${escJ(p.full_name)}')">
          <i class="fas fa-baby"></i> إضافة ابن/ابنة
        </button>
      </div>` : (tree.marriages.length===0 ? `<div class="cn-actions">
        <button class="btn btn-p btn-sm" onclick="quickLinkHusband(${p.id},'${escJ(p.full_name)}')">
          <i class="fas fa-link"></i> ربط بزوج
        </button>
      </div>` : '')}
    </div>`;

  // ── Wives strip ──
  if (isMale && tree.marriages.length) {
    document.getElementById('wivesStripWrap').innerHTML = `
      <div class="wives-strip">
        <div class="wives-strip-title">
          <i class="fas fa-female" style="color:var(--pink)"></i>
          الزوجات (${tree.marriages.length})
        </div>
        <div>
          ${tree.marriages.map(w => `
            <span class="wife-chip" onclick="selectPerson(${w.id})" title="${esc(w.father_name?w.full_name+' بنت '+w.father_name:w.full_name)}">
              <i class="fas fa-female" style="font-size:.7rem"></i>
              ${esc(w.full_name)}
              ${w.children_count > 0 ? `<span style="font-size:.65rem;color:rgba(244,114,182,.6)">(${w.children_count})</span>` : ''}
              <span class="unlink-btn" onclick="event.stopPropagation();showDivorce(${w.marriage_id},'${escJ(w.full_name)}')">✕</span>
            </span>`).join('')}
        </div>
      </div>`;
  } else if (!isMale && tree.marriages.length) {
    const h = tree.marriages[0];
    document.getElementById('wivesStripWrap').innerHTML = `
      <div class="wives-strip">
        <div class="wives-strip-title">
          <i class="fas fa-male" style="color:var(--male)"></i> الزوج
        </div>
        <div style="display:flex;align-items:center;gap:8px;padding:4px 0">
          <div class="av m" style="width:30px;height:30px;font-size:.75rem">${esc(h.full_name)[0]}</div>
          <div style="flex:1">
            <div style="font-size:.83rem;font-weight:700;color:var(--male);cursor:pointer"
                 onclick="selectPerson(${h.id})">${esc(h.full_name)}</div>
            ${h.father_name ? `<div style="font-size:.7rem;color:var(--text3)">← ${esc(h.father_name)}</div>` : ''}
          </div>
          <button class="ib del" title="فك الارتباط" onclick="showDivorce(${h.marriage_id},'${escJ(h.full_name)}')">
            <i class="fas fa-unlink"></i>
          </button>
        </div>
      </div>`;
  } else {
    document.getElementById('wivesStripWrap').innerHTML = '';
  }

  // ── Children ──
  if (tree.children.length) {
    document.getElementById('childrenSectionWrap').innerHTML = `
      <div class="children-section">
        <div class="children-title">
          <i class="fas fa-baby" style="color:var(--accentG)"></i>
          الأبناء (${tree.children.length})
        </div>
        ${tree.children.map(c => {
          const cm = c.gender==='Male';
          const hasTree = parseInt(c.has_children)>0 || parseInt(c.wives_count)>0 || parseInt(c.is_married_f)>0;
          return `<div class="child-card ${hasTree?'has-children':''}" onclick="selectPerson(${c.id})">
            <div class="av ${cm?'m':'f'}" style="width:30px;height:30px;font-size:.74rem">${esc(c.full_name)[0]}</div>
            <div style="flex:1;min-width:0">
              <div class="cname ${cm?'':''}">
                ${esc(c.full_name)}
                ${c.status==='Deceased'?'<span style="font-size:.68rem;color:var(--text3)"> 🕊</span>':''}
              </div>
              <div class="csub">
                ${c.mother_name?'الأم: '+esc(c.mother_name):''}
                ${c.birth_date?` · ${c.birth_date}`:''}
              </div>
            </div>
            <div class="cacts">
              ${hasTree?`<span style="font-size:.68rem;color:var(--accentG)" title="له شجرة"><i class="fas fa-sitemap"></i></span>`:''}
              ${cm&&parseInt(c.wives_count)>0?`<span class="marriage-badge mb-wives" style="font-size:.65rem">${c.wives_count}♀</span>`:''}
              ${!cm&&parseInt(c.is_married_f)>0?`<span class="marriage-badge mb-married" style="font-size:.65rem">متزوجة</span>`:''}
              <button class="ib go" style="width:22px;height:22px" title="انتقال لشجرته">
                <i class="fas fa-chevron-left" style="font-size:.65rem"></i>
              </button>
            </div>
          </div>`;
        }).join('')}
      </div>`;
  } else {
    document.getElementById('childrenSectionWrap').innerHTML = `
      <div style="text-align:center;padding:12px;color:var(--text3);font-size:.78rem">
        <i class="fas fa-baby" style="opacity:.2;display:block;font-size:1.4rem;margin-bottom:4px"></i>
        لا أبناء مسجلون
      </div>`;
  }

  // ── Siblings ──
  if (tree.siblings.length) {
    document.getElementById('siblingsSectionWrap').innerHTML = `
      <div class="siblings-section">
        <div style="font-size:.7rem;color:var(--text3);font-weight:700;margin-bottom:6px;
          display:flex;align-items:center;gap:5px">
          <i class="fas fa-users"></i> الإخوة والأخوات (${tree.siblings.length})
        </div>
        <div>
          ${tree.siblings.map(s => `
            <span class="sibling-chip ${s.gender==='Male'?'m':'f'}" onclick="selectPerson(${s.id})">
              ${s.gender==='Male'?'♂':'♀'} ${esc(s.full_name)}
              ${s.status==='Deceased'?'<i class="fas fa-times" style="font-size:.6rem;opacity:.5"></i>':''}
            </span>`).join('')}
        </div>
      </div>`;
  } else {
    document.getElementById('siblingsSectionWrap').innerHTML = '';
  }
}

// ════════════════════════════════════════════════════════════
//  QUICK LINK FROM TREE
// ════════════════════════════════════════════════════════════
function quickLinkWife(hid, hname) {
  // switch to addMarTab, pre-fill husband
  document.querySelectorAll('.ctab').forEach(t => t.classList.toggle('on', t.dataset.tab==='addMarTab'));
  document.querySelectorAll('.cpanel').forEach(p => p.classList.toggle('on', p.id==='addMarTab'));
  document.getElementById('lhId').value = hid;
  document.getElementById('lhVal').textContent = hname;
  document.getElementById('lhVal').classList.remove('empty');
  document.getElementById('lwId').value = '';
  document.getElementById('lwVal').textContent = '— اختر الزوجة —';
  document.getElementById('lwVal').classList.add('empty');
  loadAvailableWomen('');
}

function quickLinkHusband(wid, wname) {
  document.querySelectorAll('.ctab').forEach(t => t.classList.toggle('on', t.dataset.tab==='addMarTab'));
  document.querySelectorAll('.cpanel').forEach(p => p.classList.toggle('on', p.id==='addMarTab'));
  document.getElementById('lwId').value = wid;
  document.getElementById('lwVal').textContent = wname;
  document.getElementById('lwVal').classList.remove('empty');
}

function switchToAddChild(fid, fname) {
  document.querySelectorAll('.ctab').forEach(t => t.classList.toggle('on', t.dataset.tab==='addMarTab'));
  document.querySelectorAll('.cpanel').forEach(p => p.classList.toggle('on', p.id==='addMarTab'));
  document.getElementById('qcFatherId').value = fid;
  document.getElementById('qcFatherName').value = fname;
  loadQcMothers(fid);
}

// ════════════════════════════════════════════════════════════
//  DIVORCE
// ════════════════════════════════════════════════════════════
function showDivorce(mid, wname) {
  document.getElementById('divorceMarId').value = mid;
  document.getElementById('divorceWifeName').textContent = wname;
  openModal('modalDivorce');
}

async function submitDivorce() {
  const mid = document.getElementById('divorceMarId').value;
  const act = document.querySelector('input[name="dvOpt"]:checked').value;
  const d = await post({ajax_action:'delete_marriage', marriage_id:mid, children_action:act});
  if (d.ok) {
    closeModal('modalDivorce');
    if (currentPersonId) selectPerson(currentPersonId, false);
    loadAvailableWomen(document.getElementById('womenSearch').value);
  } else {
    alert('خطأ: ' + d.error);
  }
}

// ════════════════════════════════════════════════════════════
//  AVAILABLE WOMEN — RIGHT PANEL
// ════════════════════════════════════════════════════════════
async function loadAvailableWomen(q) {
  const el = document.getElementById('womenList');
  const hid = document.getElementById('lhId').value || 0;
  el.innerHTML = `<div style="text-align:center;padding:12px"><span class="spin"></span></div>`;
  const data = await post({ajax_action:'available_women', q, husband_id:hid});
  document.getElementById('womenBadge').textContent = data.length;
  if (!data.length) {
    el.innerHTML = `<div class="empty"><i class="fas fa-female"></i><span>لا توجد نتائج</span></div>`;
    return;
  }
  el.innerHTML = data.map(w => {
    const triple = [w.full_name, w.father_name, w.gf_name].filter(Boolean).join(' ← ');
    return `<div class="woman-row">
      <div class="av f" style="width:30px;height:30px;font-size:.72rem">${esc(w.full_name)[0]}</div>
      <div class="wrow-name">
        <div class="n">${esc(w.full_name)}</div>
        <div class="s">${esc(triple)}</div>
      </div>
      <button class="btn-link-w" onclick="linkWomanFromPanel(${w.id},'${escJ(w.full_name)}')">
        <i class="fas fa-link"></i> ربط
      </button>
    </div>`;
  }).join('');
}

async function linkWomanFromPanel(wid, wname) {
  const hid = document.getElementById('lhId').value;
  if (!hid) {
    showMsg('rightMsg','⚠️ اختر الزوج أولاً من الشجرة أو تبويب "ربط زواج"','err'); return;
  }
  const hname = document.getElementById('lhVal').textContent;
  if (!confirm(`ربط "${wname}" بـ "${hname}"؟`)) return;
  const d = await post({ajax_action:'add_marriage', husband_id:hid, wife_id:wid});
  if (d.ok) {
    showMsg('rightMsg', `✅ تم ربط ${wname} بنجاح`, 'ok');
    loadAvailableWomen(document.getElementById('womenSearch').value);
    if (currentPersonId) selectPerson(currentPersonId, false);
  } else {
    showMsg('rightMsg', '❌ ' + d.error, 'err');
  }
}

// ════════════════════════════════════════════════════════════
//  PICKERS (Link Marriage Tab)
// ════════════════════════════════════════════════════════════
const pickerPools = {lh:[], lw:[]};
const pickerSearchIds = {lh:0,lw:0};

async function openPicker(key) {
  document.querySelectorAll('.picker-dropdown').forEach(d => d.classList.remove('open'));
  document.getElementById(key+'Drop').classList.add('open');
  document.getElementById(key+'Search').value = '';
  document.getElementById(key+'Search').focus();
  pickerPools[key] = [];
  document.getElementById(key+'List').innerHTML = '<div class="po-empty">جارٍ تحميل الأسماء…</div>';
  if (key === 'lh') {
    const data = await post({ajax_action:'search_triple', q:'', gender:'Male'});
    pickerPools.lh = data;
    filterPicker('lh', document.getElementById('lhSearch').value);
  } else {
    const hid = document.getElementById('lhId').value || 0;
    const data = await post({ajax_action:'available_women', q:'', husband_id:hid});
    pickerPools.lw = data;
    filterPicker('lw', document.getElementById('lwSearch').value);
  }
}

async function filterPicker(key, q) {
  const generation = ++pickerSearchIds[key];
  if (!q.trim()) { buildPickerList(key, pickerPools[key]); return; }
  document.getElementById(key+'List').innerHTML = '<div class="po-empty">جارٍ البحث…</div>';
  try {
    const query = key==='lh' ? {ajax_action:'search_triple',q,gender:'Male'} : {ajax_action:'available_women',q,husband_id:document.getElementById('lhId').value||0};
    const data = await post(query);
    if (generation === pickerSearchIds[key]) buildPickerList(key, data);
  } catch (_) { if (generation === pickerSearchIds[key]) document.getElementById(key+'List').innerHTML = '<div class="po-empty">تعذّر البحث. حاول مجددًا.</div>'; }
}

function buildPickerList(key, pool) {
  const el = document.getElementById(key+'List');
  if (!pool.length) { el.innerHTML = `<div class="po-empty">لا توجد نتائج</div>`; return; }
  el.innerHTML = pool.map(p => {
    const triple = [p.full_name, p.father_name, p.gf_name].filter(Boolean).join(' ← ');
    return `<div class="po" onclick="selectPicker('${key}',${p.id},'${escJ(p.full_name)}')">
      ${esc(p.full_name)}
      <div class="sub">${esc(triple)}</div>
      ${p.wives_count>0?`<div class="sub" style="color:var(--pink)">${p.wives_count} زوجة حالياً</div>`:''}
    </div>`;
  }).join('');
}

function selectPicker(key, id, name) {
  document.getElementById(key+'Id').value = id;
  document.getElementById(key+'Val').textContent = name;
  document.getElementById(key+'Val').classList.remove('empty');
  document.getElementById(key+'Drop').classList.remove('open');
  if (key === 'lh') {
    // reload wives list with this husband
    loadAvailableWomen(document.getElementById('womenSearch').value);
  }
}

async function submitLink() {
  const hid = document.getElementById('lhId').value;
  const wid = document.getElementById('lwId').value;
  if (!hid || !wid) { showMsg('linkMsg','⚠️ يجب اختيار الزوج والزوجة','err'); return; }
  const d = await post({ajax_action:'add_marriage', husband_id:hid, wife_id:wid});
  if (d.ok) {
    showMsg('linkMsg','✅ تم ربط الزواج بنجاح','ok');
    document.getElementById('lwId').value='';
    document.getElementById('lwVal').textContent='— اختر الزوجة —';
    document.getElementById('lwVal').classList.add('empty');
    if (currentPersonId) selectPerson(currentPersonId, false);
    loadAvailableWomen(document.getElementById('womenSearch').value);
  } else {
    showMsg('linkMsg','❌ '+d.error,'err');
  }
}

// ════════════════════════════════════════════════════════════
//  QUICK ADD CHILD
// ════════════════════════════════════════════════════════════
async function loadQcMothers(fid) {
  const grp = document.getElementById('qcMotherGroup');
  const sel = document.getElementById('qcMother');
  if (!fid) { grp.style.display='none'; return; }
  const data = await post({ajax_action:'wives_for_child', father_id:fid});
  if (data.length) {
    grp.style.display = 'block';
    sel.innerHTML = `<option value="">— اختر الأم (اختياري) —</option>` +
      data.map(w => `<option value="${w.id}">${esc(w.full_name)}</option>`).join('');
  } else {
    grp.style.display = 'none';
  }
}

async function submitQuickChild() {
  const fid  = document.getElementById('qcFatherId').value;
  const name = document.getElementById('qcName').value.trim();
  if (!fid)  { showMsg('addChildMsg2','⚠️ اختر الأب أولاً من الشجرة','err'); return; }
  if (!name) { showMsg('addChildMsg2','⚠️ الاسم مطلوب','err'); return; }
  const d = await post({
    ajax_action : 'add_child',
    full_name   : name,
    gender      : document.getElementById('qcGender').value,
    status      : document.getElementById('qcStatus').value,
    birth_date  : document.getElementById('qcBdate').value,
    father_id   : fid,
    mother_id   : document.getElementById('qcMother').value,
  });
  if (d.ok) {
    showMsg('addChildMsg2','✅ تم إضافة '+name+' بنجاح','ok');
    document.getElementById('qcName').value = '';
    document.getElementById('qcBdate').value = '';
    if (currentPersonId) selectPerson(currentPersonId, false);
  } else {
    showMsg('addChildMsg2','❌ '+d.error,'err');
  }
}

// ════════════════════════════════════════════════════════════
//  TABS
// ════════════════════════════════════════════════════════════
document.querySelectorAll('.ctab').forEach(t => {
  t.addEventListener('click', () => {
    document.querySelectorAll('.ctab').forEach(x => x.classList.toggle('on', x===t));
    document.querySelectorAll('.cpanel').forEach(p => p.classList.toggle('on', p.id===t.dataset.tab));
  });
});

// ════════════════════════════════════════════════════════════
//  CLOSE PICKERS ON OUTSIDE CLICK
// ════════════════════════════════════════════════════════════
document.addEventListener('click', e => {
  if (!e.target.closest('.picker-wrap')) {
    document.querySelectorAll('.picker-dropdown').forEach(d => d.classList.remove('open'));
  }
});

// ════════════════════════════════════════════════════════════
//  INIT
// ════════════════════════════════════════════════════════════
loadAvailableWomen('');
</script>
</body>
</html>