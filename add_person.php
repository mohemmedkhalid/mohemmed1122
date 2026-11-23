<?php
require_once 'header.php';

if (!isAdmin()) {
    $_SESSION['message'] = 'هذه الصفحة للمشرف فقط.';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

$message = '';
$message_type = '';
$f_full_name = '';
$f_birth_date = '';
$f_gender = '';
$f_status = 'Alive';
$f_father_id = null;
$f_mother_id = null;

// ── دوال مساعدة ─────────────────────────────────────────────────────────────
function generate_display_name($row) {
    $parts = [$row['full_name']];
    if (!empty($row['p_father_name'])) {
        $parts[] = $row['p_father_name'];
        if (!empty($row['p_grandfather_name'])) $parts[] = $row['p_grandfather_name'];
    }
    if ($row['gender'] == 'Female' && !empty($row['husband_name'])) {
        $info = "زوجة " . $row['husband_name'];
        if (!empty($row['husband_father'])) $info .= " " . $row['husband_father'];
        $parts[] = "($info)";
    }
    return implode(" ", $parts);
}

function fetch_people_full($mysqli, $gender = null) {
    $sql = "SELECT p.id, p.full_name, p.gender, p.status, p.birth_date, p.father_id, p.mother_id,
                   f.full_name AS p_father_name, gf.full_name AS p_grandfather_name,
                   h.full_name AS husband_name, hf.full_name AS husband_father
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id = f.id
            LEFT JOIN people2 gf ON f.father_id = gf.id
            LEFT JOIN marriages m ON p.id = m.wife_id
            LEFT JOIN people2 h  ON m.husband_id = h.id
            LEFT JOIN people2 hf ON h.father_id  = hf.id";
    if ($gender) $sql .= " WHERE p.gender = '$gender'";
    $sql .= " ORDER BY p.full_name";
    return $mysqli->query($sql);
}

// دالة مساعدة لرفع الصور
function uploadPersonImage($fileElement) {
    $targetDir = "uploads/people/"; // المجلد الذي سيتم حفظ الصور فيه
    // إنشاء المجلد إذا لم يكن موجوداً
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($fileElement["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    
    // التحقق من نوع الملف
    $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
    if (in_array($fileType, $allowTypes)) {
        if (move_uploaded_file($fileElement["tmp_name"], $targetFilePath)) {
            return $targetFilePath; // إرجاع مسار الصورة لحفظه في قاعدة البيانات
        }
    }
    return null;
}

// ── معالجة AJAX ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // 1. بحث سريع
    if ($_POST['ajax_action'] === 'search') {
        $q = '%' . $mysqli->real_escape_string(trim($_POST['q'] ?? '')) . '%';
        $gender = $_POST['gender'] ?? '';
        $gWhere = $gender ? "AND p.gender = '$gender'" : '';
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.gender, p.status,
                   f.full_name AS p_father_name, gf.full_name AS p_grandfather_name,
                   h.full_name AS husband_name, hf.full_name AS husband_father
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id = f.id
            LEFT JOIN people2 gf ON f.father_id = gf.id
            LEFT JOIN marriages m ON p.id = m.wife_id
            LEFT JOIN people2 h  ON m.husband_id = h.id
            LEFT JOIN people2 hf ON h.father_id  = hf.id
            WHERE (p.full_name LIKE '$q'
               OR CONCAT(p.full_name,' ',IFNULL(f.full_name,'')) LIKE '$q') $gWhere
            ORDER BY p.full_name LIMIT 40");
        $out = [];
        while ($r = $rows->fetch_assoc()) {
            $label = htmlspecialchars($r['full_name']);
            if ($r['p_father_name']) $label .= ' ← ' . htmlspecialchars($r['p_father_name']);
            if ($r['p_grandfather_name']) $label .= ' ← ' . htmlspecialchars($r['p_grandfather_name']);
            if ($r['gender'] === 'Female' && $r['husband_name']) $label .= ' (زوجة ' . htmlspecialchars($r['husband_name']) . ')';
            $out[] = ['id' => $r['id'], 'label' => $label, 'gender' => $r['gender'], 'status' => $r['status']];
        }
        echo json_encode($out); exit;
    }

    // 2. أبناء الأب
    if ($_POST['ajax_action'] === 'get_children') {
        $pid = (int)$_POST['father_id'];
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.gender, p.status, p.birth_date,
                   m2.full_name AS mother_name
            FROM people2 p
            LEFT JOIN people2 m2 ON p.mother_id = m2.id
            WHERE p.father_id = $pid
            ORDER BY p.full_name");
        $out = [];
        while ($r = $rows->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    // 3. زوجات الأب (للأم)
    if ($_POST['ajax_action'] === 'get_wives') {
        $pid = (int)$_POST['father_id'];
        $rows = $mysqli->query("
            SELECT p.id, p.full_name FROM people2 p
            INNER JOIN marriages m ON p.id = m.wife_id AND m.husband_id = $pid
            ORDER BY p.full_name");
        $out = [];
        while ($r = $rows->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    // 4b. سلسلة العائلة الكاملة (جد → أب → إخوة → أبناء)
    if ($_POST['ajax_action'] === 'get_family_chain') {
        $pid = (int)$_POST['person_id'];
        // الشخص نفسه
        $person = $mysqli->query("SELECT p.*, f.full_name AS f_name, f.id AS f_id,
                                         gf.full_name AS gf_name, gf.id AS gf_id,
                                         m.full_name AS m_name
                                  FROM people2 p
                                  LEFT JOIN people2 f  ON p.father_id = f.id
                                  LEFT JOIN people2 gf ON f.father_id = gf.id
                                  LEFT JOIN people2 m  ON p.mother_id = m.id
                                  WHERE p.id = $pid")->fetch_assoc();
        // إخوة الشخص (أبناء نفس الأب)
        $siblings = [];
        if ($person && $person['father_id']) {
            $fid = (int)$person['father_id'];
            $sr = $mysqli->query("SELECT p.id, p.full_name, p.gender, p.status
                                  FROM people2 p WHERE p.father_id = $fid
                                  ORDER BY p.full_name");
            while ($r = $sr->fetch_assoc()) $siblings[] = $r;
        }
        // أبناء الشخص
        $children = [];
        $cr = $mysqli->query("SELECT p.id, p.full_name, p.gender, p.status, m.full_name AS mother_name
                              FROM people2 p LEFT JOIN people2 m ON p.mother_id = m.id
                              WHERE p.father_id = $pid ORDER BY p.full_name");
        while ($r = $cr->fetch_assoc()) $children[] = $r;
        // أبناء الجد (أعمام الشخص)
        $uncles = [];
        if ($person && $person['gf_id']) {
            $gid = (int)$person['gf_id'];
            $ur = $mysqli->query("SELECT p.id, p.full_name, p.gender, p.status
                                  FROM people2 p WHERE p.father_id = $gid
                                  ORDER BY p.full_name");
            while ($r = $ur->fetch_assoc()) $uncles[] = $r;
        }
        echo json_encode([
            'person'   => $person,
            'siblings' => $siblings,
            'children' => $children,
            'uncles'   => $uncles,
        ]); exit;
    }

    // 4. تفاصيل شخص للتعديل
    if ($_POST['ajax_action'] === 'get_person') {
        $pid = (int)$_POST['person_id'];
        $r = $mysqli->query("SELECT p.*, f.full_name AS f_name, m.full_name AS m_name
                             FROM people2 p
                             LEFT JOIN people2 f ON p.father_id = f.id
                             LEFT JOIN people2 m ON p.mother_id = m.id
                             WHERE p.id = $pid")->fetch_assoc();
        echo json_encode($r); exit;
    }

    // 5. حفظ التعديل
    if ($_POST['ajax_action'] === 'edit_person') {
        $pid = (int)$_POST['person_id'];
        $name = $mysqli->real_escape_string(trim($_POST['full_name']));
        $name_en = $mysqli->real_escape_string(trim($_POST['full_name_en'] ?? ''));
        $gen = in_array($_POST['gender'],['Male','Female']) ? $_POST['gender'] : 'Male';
        $stat = in_array($_POST['status'],['Alive','Deceased']) ? $_POST['status'] : 'Alive';
        $bdate = !empty($_POST['birth_date']) ? "'".$mysqli->real_escape_string($_POST['birth_date'])."'" : 'NULL';
        $fid = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : 'NULL';
        $mid = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : 'NULL';
        $notes = !empty($_POST['notes']) ? "'".$mysqli->real_escape_string($_POST['notes'])."'" : 'NULL';
        $en_sql = $name_en !== '' ? ", full_name_en='$name_en'" : ", full_name_en=NULL";
        
        // معالجة الصورة
        $imgSql = "";
        $imageFile = !empty($_FILES['person_image']['name']) ? $_FILES['person_image'] :
                     (!empty($_FILES['edit_person_image']['name']) ? $_FILES['edit_person_image'] : null);
        if ($imageFile) {
            $uploadedPath = uploadPersonImage($imageFile);
            if ($uploadedPath) { $imgSql = ", image_url='$uploadedPath'"; }
        } elseif (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
            $imgSql = ", image_url=NULL";
        }

        $ok = $mysqli->query("UPDATE people2 SET 
            full_name='$name' $en_sql, gender='$gen', status='$stat', birth_date=$bdate, 
            father_id=$fid, mother_id=$mid, notes=$notes $imgSql 
            WHERE id=$pid");
        echo json_encode(['ok' => $ok, 'error' => $mysqli->error]); exit;
    }

    // 6. إضافة شخص
    if ($_POST['ajax_action'] === 'add_person') {
        $name = $mysqli->real_escape_string(trim($_POST['full_name']));
        $name_en = $mysqli->real_escape_string(trim($_POST['full_name_en'] ?? ''));
        $gen = in_array($_POST['gender'],['Male','Female']) ? $_POST['gender'] : 'Male';
        $stat = in_array($_POST['status'],['Alive','Deceased']) ? $_POST['status'] : 'Alive';
        $bdate = !empty($_POST['birth_date']) ? "'".$mysqli->real_escape_string($_POST['birth_date'])."'" : 'NULL';
        $fid = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : 'NULL';
        $mid = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : 'NULL';
        $notes = !empty($_POST['notes']) ? "'".$mysqli->real_escape_string($_POST['notes'])."'" : 'NULL';
        $name_en_val = $name_en !== '' ? "'$name_en'" : 'NULL';
        
        $image_url = 'NULL';
        if (!empty($_FILES['person_image']['name'])) {
            $uploadedPath = uploadPersonImage($_FILES['person_image']);
            if ($uploadedPath) { $image_url = "'$uploadedPath'"; }
        }

        $sql = "INSERT INTO people2 (full_name, full_name_en, gender, status, birth_date, father_id, mother_id, notes, image_url) 
                VALUES ('$name', $name_en_val, '$gen', '$stat', $bdate, $fid, $mid, $notes, $image_url)";
        $insert_ok = $mysqli->query($sql);
        $new_id = $insert_ok ? $mysqli->insert_id : 0;

        $spouses = isset($_POST['spouse_ids']) ? array_map('intval', (array)$_POST['spouse_ids']) : [];
        foreach ($spouses as $sid) {
            if (!$sid || !$new_id) continue;
            [$hid,$wid] = $gen==='Male' ? [$new_id,$sid] : [$sid,$new_id];
            $mysqli->query("INSERT IGNORE INTO marriages (husband_id,wife_id) VALUES ($hid,$wid)");
        }

        echo json_encode(['ok' => (bool)$insert_ok, 'id' => $new_id, 'name' => $name, 'error' => $insert_ok ? '' : $mysqli->error]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown action']); exit;
}

// ── تحميل بيانات القوائم ──────────────────────────────────────────────────
$all_males   = fetch_people_full($mysqli, 'Male');
$all_females = fetch_people_full($mysqli, 'Female');
$males_opts  = []; while ($r = $all_males->fetch_assoc())   $males_opts[]  = $r;
$females_opts= []; while ($r = $all_females->fetch_assoc()) $females_opts[] = $r;

// تحويل لـ JSON للـ JS
$males_json   = json_encode(array_map(fn($r)=>['id'=>$r['id'],'gender'=>$r['gender'],'status'=>$r['status'],'label'=>generate_display_name($r),'fname'=>$r['p_father_name']??''], $males_opts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$females_json = json_encode(array_map(fn($r)=>['id'=>$r['id'],'gender'=>$r['gender'],'status'=>$r['status'],'label'=>generate_display_name($r),'fname'=>$r['p_father_name']??''], $females_opts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إضافة / تعديل شخص</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════ RESET & BASE ═══════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d1117;
  --surface:#161b22;
  --surface2:#1f2937;
  --border:#30363d;
  --border2:#3d4852;
  --accent:#3b82f6;
  --accent2:#60a5fa;
  --green:#22c55e;
  --red:#ef4444;
  --amber:#f59e0b;
  --text:#e6edf3;
  --text2:#8b949e;
  --text3:#6e7681;
  --male:#60a5fa;
  --female:#f472b6;
  --radius:10px;
  --shadow:0 4px 24px rgba(0,0,0,.5);
}
html,body{height:100%;font-family:'Cairo',sans-serif;background:var(--bg);color:var(--text);font-size:15px}
body{display:flex;flex-direction:column;overflow:hidden}

/* ═══════════════════════════ TOP BAR ════════════════════════════════ */
.topbar{height:52px;background:var(--surface);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 20px;gap:16px;flex-shrink:0;z-index:10}
.topbar-logo{font-weight:900;font-size:1.1rem;color:var(--accent2);letter-spacing:-0.5px}
.topbar-logo span{color:var(--text3)}
.topbar-nav{display:flex;gap:8px;margin-right:auto}
.tbtn{background:transparent;border:1px solid var(--border);color:var(--text2);padding:5px 13px;
  border-radius:7px;cursor:pointer;font-family:inherit;font-size:.82rem;transition:.18s}
.tbtn:hover{border-color:var(--accent);color:var(--accent2);background:rgba(59,130,246,.08)}
.tbtn.active{background:rgba(59,130,246,.15);border-color:var(--accent);color:var(--accent2)}

/* ═══════════════════════════ LAYOUT ════════════════════════════════ */
.workspace{display:grid;grid-template-columns:340px 1fr 320px;height:calc(100vh - 52px);overflow:hidden}

/* ═══════════════════════════ PANEL BASE ════════════════════════════ */
.panel{border-left:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.panel:first-child{border-left:none}
.panel-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0}
.panel-head h3{font-size:.9rem;font-weight:700;color:var(--text)}
.panel-head .badge{margin-right:auto;background:rgba(59,130,246,.15);color:var(--accent2);
  border-radius:20px;padding:2px 9px;font-size:.75rem}
.panel-body{flex:1;overflow-y:auto;padding:12px}
.panel-body::-webkit-scrollbar{width:5px}
.panel-body::-webkit-scrollbar-thumb{background:var(--border2);border-radius:5px}

/* ═══════════════════════════ SEARCH BAR ════════════════════════════ */
.search-wrap{position:relative;margin-bottom:10px}
.search-wrap i{position:absolute;right:11px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:.85rem;pointer-events:none}
.search-input{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;
  padding:8px 34px 8px 10px;color:var(--text);font-family:inherit;font-size:.86rem;outline:none;transition:.18s}
.search-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.search-input::placeholder{color:var(--text3)}

/* ═══════════════════════════ PERSON LIST ════════════════════════════ */
.person-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;
  cursor:pointer;transition:.15s;border:1px solid transparent;margin-bottom:4px}
.person-item:hover{background:var(--surface2);border-color:var(--border)}
.person-item.selected{background:rgba(59,130,246,.12);border-color:var(--accent)}
.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:.8rem;font-weight:700;flex-shrink:0}
.avatar.male{background:rgba(96,165,250,.15);color:var(--male)}
.avatar.female{background:rgba(244,114,182,.15);color:var(--female)}
.pname{font-size:.84rem;font-weight:600;color:var(--text);line-height:1.2}
.psub{font-size:.74rem;color:var(--text3);margin-top:1px}
.pstatus{margin-right:auto;flex-shrink:0}
.dot{width:7px;height:7px;border-radius:50%;display:inline-block}
.dot.alive{background:var(--green)}
.dot.dead{background:var(--text3)}

/* ─── Segment filter ─── */
.seg{display:flex;gap:5px;margin-bottom:10px}
.seg-btn{flex:1;padding:6px;border-radius:7px;border:1px solid var(--border);background:transparent;
  color:var(--text2);font-family:inherit;font-size:.79rem;cursor:pointer;transition:.15s;font-weight:600}
.seg-btn.on{background:rgba(59,130,246,.15);border-color:var(--accent);color:var(--accent2)}

/* ═══════════════════════════ CHILDREN PANEL ════════════════════════ */
.child-card{background:var(--surface2);border:1px solid var(--border);border-radius:8px;
  padding:9px 11px;margin-bottom:6px;display:flex;align-items:center;gap:9px}
.child-card .pname{font-size:.82rem}
.child-card .psub{font-size:.72rem}
.empty-state{text-align:center;padding:30px 10px;color:var(--text3)}
.empty-state i{font-size:2rem;margin-bottom:8px;opacity:.4;display:block}
.empty-state span{font-size:.82rem}

/* ═══════════════════════════ FORM PANEL ════════════════════════════ */
.form-tabs{display:flex;border-bottom:1px solid var(--border);flex-shrink:0}
.ftab{flex:1;padding:12px;text-align:center;cursor:pointer;font-size:.83rem;font-weight:700;
  color:var(--text2);border-bottom:2px solid transparent;transition:.18s;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit}
.ftab.on{color:var(--accent2);border-bottom-color:var(--accent)}

.fpanel{display:none;flex:1;overflow-y:auto;padding:16px}
.fpanel.on{display:block}
.fpanel::-webkit-scrollbar{width:5px}
.fpanel::-webkit-scrollbar-thumb{background:var(--border2);border-radius:5px}

.flabel{font-size:.78rem;color:var(--text2);font-weight:700;margin-bottom:5px;display:block}
.fgroup{margin-bottom:14px}
.finput{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;
  padding:8px 11px;color:var(--text);font-family:inherit;font-size:.86rem;outline:none;transition:.18s}
.finput:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.finput::placeholder{color:var(--text3)}
select.finput{appearance:none;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236e7681' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
  background-repeat:no-repeat;background-position:left 8px center;background-size:1.3em}
select.finput option{background:#161b22}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}

/* father picker inside form */
.picker-wrap{position:relative}
.picker-input{display:flex;align-items:center;gap:8px;background:var(--surface2);border:1px solid var(--border);
  border-radius:8px;padding:7px 11px;cursor:pointer;transition:.18s}
.picker-input:hover,.picker-input.open{border-color:var(--accent)}
.picker-input .pval{flex:1;font-size:.84rem;color:var(--text);overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.picker-input .pval.empty{color:var(--text3)}
.picker-input .clr{color:var(--text3);font-size:.8rem;cursor:pointer;padding:2px 4px}
.picker-dropdown{display:none;position:absolute;z-index:999;top:calc(100% + 4px);right:0;left:0;
  background:var(--surface);border:1px solid var(--border);border-radius:10px;
  box-shadow:0 8px 32px rgba(0,0,0,.5);overflow:hidden}
.picker-dropdown.open{display:block}
.picker-search{width:100%;background:var(--surface2);border:none;border-bottom:1px solid var(--border);
  padding:9px 13px;color:var(--text);font-family:inherit;font-size:.86rem;outline:none}
.picker-search::placeholder{color:var(--text3)}
.picker-list{max-height:220px;overflow-y:auto}
.picker-list::-webkit-scrollbar{width:4px}
.picker-list::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px}
.picker-opt{padding:8px 13px;cursor:pointer;font-size:.83rem;color:var(--text);transition:.12s;line-height:1.3}
.picker-opt:hover,.picker-opt.active{background:rgba(59,130,246,.12);color:var(--accent2)}
.picker-opt .sub{font-size:.72rem;color:var(--text3);margin-top:1px}
.picker-empty{padding:16px;text-align:center;color:var(--text3);font-size:.82rem}

.mother-box{background:rgba(244,114,182,.05);border:1px solid rgba(244,114,182,.2);
  border-radius:8px;padding:10px;margin-bottom:14px}
.mother-box .flabel{color:#f472b6}

.btn-submit{width:100%;padding:11px;border-radius:9px;border:none;background:var(--accent);
  color:#fff;font-family:inherit;font-size:.92rem;font-weight:700;cursor:pointer;transition:.18s;margin-top:4px}
.btn-submit:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-submit.green{background:var(--green)}
.btn-submit.green:hover{background:#16a34a}

.msg{padding:9px 13px;border-radius:8px;font-size:.83rem;font-weight:600;margin-bottom:12px;display:none}
.msg.ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#86efac}
.msg.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5}

/* ═══════════════════════════ EDIT INFO ROW ════════════════════════ */
.info-kv{display:flex;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)}
.info-kv:last-child{border-bottom:none}
.ik{width:90px;font-size:.75rem;color:var(--text3);flex-shrink:0;font-weight:600}
.iv{flex:1;font-size:.85rem;color:var(--text)}
.iv .tag{display:inline-block;padding:2px 8px;border-radius:5px;font-size:.75rem;font-weight:700}
.iv .tag.m{background:rgba(96,165,250,.15);color:var(--male)}
.iv .tag.f{background:rgba(244,114,182,.15);color:var(--female)}
.iv .tag.alive{background:rgba(34,197,94,.12);color:#86efac}
.iv .tag.dead{background:rgba(107,114,128,.12);color:#9ca3af}

/* ═══════════════════════════ SPOUSE ═══════════════════════════════ */
.spouse-row{display:flex;align-items:center;gap:7px;margin-bottom:6px;
  background:var(--surface2);border-radius:7px;padding:6px 9px;font-size:.82rem}
.spouse-row .del{margin-right:auto;color:var(--text3);cursor:pointer;font-size:.8rem}
.spouse-row .del:hover{color:var(--red)}

/* ═══════════════════════════ LOADING ═══════════════════════════════ */
.spinner{width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--accent);
  border-radius:50%;animation:spin .7s linear infinite;display:inline-block;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}

/* ═══════════════════════════ FAMILY TREE NAV ═══════════════════════ */
.ftree-nav{background:var(--surface2);border:1px solid var(--border);border-radius:10px;
  padding:10px;margin-bottom:14px}
.ftree-level{margin-bottom:6px}
.ftree-level-label{font-size:.68rem;font-weight:700;color:var(--text3);letter-spacing:.6px;
  text-transform:uppercase;margin-bottom:5px;padding-right:4px}
.ftree-row{display:flex;flex-wrap:wrap;gap:5px}
.ftree-node{display:flex;align-items:center;gap:5px;padding:5px 9px;border-radius:7px;
  border:1px solid var(--border);background:var(--surface);cursor:pointer;
  font-size:.78rem;color:var(--text2);transition:.15s;max-width:100%}
.ftree-node:hover{border-color:var(--accent);color:var(--accent2);background:rgba(59,130,246,.08)}
.ftree-node.active{border-color:var(--accent);background:rgba(59,130,246,.15);color:var(--accent2);font-weight:700}
.ftree-node.male-node{border-color:rgba(96,165,250,.3)}
.ftree-node.female-node{border-color:rgba(244,114,182,.3)}
.ftree-node .nt{overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:130px}
.ftree-connector{width:100%;height:1px;background:var(--border);margin:4px 0 6px;position:relative}
.ftree-connector::before{content:'▼';position:absolute;left:50%;transform:translateX(-50%);top:-9px;
  font-size:.6rem;color:var(--border2)}
.ftree-section-children{margin-top:8px;border-top:1px dashed var(--border);padding-top:8px}

/* ═══════════════════════════ IMAGE UPLOAD ══════════════════════════ */
.img-upload-area{border:2px dashed var(--border2);border-radius:10px;padding:14px;text-align:center;
  cursor:pointer;transition:.2s;background:var(--surface2);position:relative;margin-bottom:10px}
.img-upload-area:hover{border-color:var(--accent);background:rgba(59,130,246,.05)}
.img-upload-area.has-file{border-color:var(--green);border-style:solid}
.img-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.img-upload-icon{font-size:1.6rem;color:var(--text3);margin-bottom:6px}
.img-upload-label{font-size:.78rem;color:var(--text2);font-weight:600}
.img-upload-hint{font-size:.72rem;color:var(--text3);margin-top:3px}
.img-upload-filename{font-size:.78rem;color:var(--green);font-weight:700;margin-top:4px}

.img-preview-box{border:1px solid var(--border);border-radius:10px;overflow:hidden;
  background:var(--surface2);margin-bottom:8px;display:flex;align-items:center;gap:12px;padding:10px}
.img-preview-box img{width:64px;height:64px;object-fit:cover;border-radius:7px;flex-shrink:0}
.img-preview-info{flex:1;overflow:hidden}
.img-preview-name{font-size:.8rem;font-weight:700;color:var(--text);margin-bottom:2px}
.img-preview-sub{font-size:.72rem;color:var(--text3)}
.img-del-btn{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;
  border-radius:7px;padding:5px 10px;font-size:.76rem;cursor:pointer;font-family:inherit;
  font-weight:700;transition:.15s;flex-shrink:0;white-space:nowrap}
.img-del-btn:hover{background:rgba(239,68,68,.22);border-color:var(--red);color:#fff}
.img-del-btn.confirmed{background:rgba(239,68,68,.3);border-color:var(--red)}

/* ═══════════════════════════ NOTES TEXTAREA ════════════════════════ */
.ftextarea{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:8px;
  padding:9px 11px;color:var(--text);font-family:'Cairo',sans-serif;font-size:.86rem;outline:none;
  transition:.18s;resize:vertical;min-height:80px;line-height:1.6}
.ftextarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.ftextarea::placeholder{color:var(--text3)}

/* ═══════════════════════════ SCROLLBAR GLOBAL ══════════════════════ */
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:6px}

@media(max-width:900px){
  .workspace{grid-template-columns:1fr;height:auto;overflow:auto}
  .panel{border-left:none;border-top:1px solid var(--border);min-height:300px}
}
</style>
<link rel="stylesheet" href="assets/family-mobile.css?v=5">
<script src="assets/family-ui.js?v=5"></script>
</head>
<body data-family-page="add">

<!-- TOP BAR -->
<div class="topbar">
  <span class="topbar-logo">إضافة وتعديل الأشخاص</span>
  <div class="topbar-nav">
    <a href="delete_person.php" class="tbtn">إدارة الأشخاص</a>
    <a href="index.php" class="tbtn"><i class="fas fa-home"></i> الرئيسية</a>
    <a href="family_list.php" class="tbtn"><i class="fas fa-list"></i> القائمة</a>
    <a href="tree.php" class="tbtn"><i class="fas fa-sitemap"></i> الشجرة</a>
  </div>
</div>

<!-- WORKSPACE: 3 columns -->
<div class="workspace">

  <!-- ══════════════════ PANEL 1: بحث الأشخاص ══════════════════════ -->
  <div class="panel" id="searchPanel">
    <div class="panel-head">
      <i class="fas fa-search" style="color:var(--accent2);font-size:.85rem"></i>
      <h3>بحث الأشخاص</h3>
      <span class="badge" id="searchCount">—</span>
    </div>
    <div class="panel-body">
      <div class="seg">
        <button class="seg-btn on" data-g="">الكل</button>
        <button class="seg-btn" data-g="Male">ذكر</button>
        <button class="seg-btn" data-g="Female">أنثى</button>
      </div>
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" class="search-input" id="mainSearch" placeholder="ابحث بالاسم الثلاثي…">
      </div>
      <!-- بحث الاسم الأول فقط -->
      <div class="search-wrap" style="margin-bottom:6px">
        <i class="fas fa-font"></i>
        <input type="text" class="search-input" id="firstNameSearch" placeholder="🔡 الاسم الأول فقط…">
      </div>
      <div id="searchResults"></div>
    </div>
  </div>

  <!-- ══════════════════ PANEL 2: الأبناء + النموذج ════════════════ -->
  <div class="panel">
    <!-- TABS -->
    <div class="form-tabs">
      <button class="ftab on" data-tab="addTab">➕ إضافة شخص</button>
      <button class="ftab" data-tab="editTab">✏️ تعديل</button>
      <button class="ftab" data-tab="childrenTab">👨‍👧 الأبناء <span id="childCount" style="opacity:.6"></span></button>
    </div>

    <!-- TAB: إضافة ─────────────────── -->
    <form id="person_form" enctype="multipart/form-data">
    <div class="fpanel on" id="addTab">
      <div class="msg" id="addMsg"></div>

      <div class="fgroup">
        <label class="flabel">ناڤ (کوردی) *</label>
        <input type="text" class="finput" id="add_name" placeholder="ناڤ بنڤیسە…" dir="rtl">
      </div>

      <div class="fgroup">
        <label class="flabel" style="color:#60a5fa;"><i class="fas fa-language" style="margin-left:4px"></i> English Name</label>
        <input type="text" class="finput" id="add_name_en" placeholder="Full name in English…" dir="ltr" style="font-family:'Inter',sans-serif;text-align:left;">
      </div>

      <div class="row2">
        <div class="fgroup">
          <label class="flabel">الجنس *</label>
          <select class="finput" id="add_gender">
            <option value="">— اختر —</option>
            <option value="Male">ذكر 🟦</option>
            <option value="Female">أنثى 🟥</option>
          </select>
        </div>
        <div class="fgroup">
          <label class="flabel">الحالة</label>
          <select class="finput" id="add_status">
            <option value="Alive">حي ✅</option>
            <option value="Deceased">متوفى 🕊</option>
          </select>
        </div>
      </div>

      <div class="fgroup">
        <label class="flabel">تاريخ الميلاد</label>
        <input type="date" class="finput" id="add_bdate">
      </div>

      <div class="fgroup">
        <label class="flabel">ملاحظات</label>
        <textarea class="ftextarea" id="person_notes" placeholder="أضف ملاحظات عن هذا الشخص…"></textarea>
      </div>

      <div class="fgroup">
        <label class="flabel">صورة الشخص</label>
        <div class="img-upload-area" id="addImgArea" onclick="document.getElementById('person_image').click()">
          <div class="img-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
          <div class="img-upload-label">اضغط لاختيار صورة</div>
          <div class="img-upload-hint">JPG, PNG, GIF · الحجم الأقصى 5MB</div>
          <div class="img-upload-filename" id="addImgName" style="display:none"></div>
          <input type="file" name="person_image" id="person_image" accept="image/*" style="display:none"
                 onchange="previewAddImage(this)">
        </div>
      </div>

      <!-- اختيار الأب -->
      <div class="fgroup">
        <label class="flabel">الأب</label>
        <div class="picker-wrap" id="fatherPickerWrap">
          <div class="picker-input" id="fatherPickerToggle" onclick="togglePicker('father')">
            <i class="fas fa-user" style="color:var(--male);font-size:.8rem"></i>
            <span class="pval empty" id="fatherPickerVal">— اختر الأب —</span>
            <span class="clr" id="fatherPickerClr" onclick="event.stopPropagation();clearPicker('father')" style="display:none">✕</span>
            <i class="fas fa-chevron-down" style="font-size:.7rem;color:var(--text3)"></i>
          </div>
          <div class="picker-dropdown" id="fatherPickerDrop">
            <input type="text" class="picker-search" id="fatherPickerSearch" placeholder="ابحث عن الأب…" oninput="filterPicker('father',this.value)">
            <div class="picker-list" id="fatherPickerList"></div>
          </div>
        </div>
        <input type="hidden" id="add_father_id">
      </div>

      <!-- اختيار الأم (تظهر بعد الأب) -->
      <div class="mother-box" id="motherBox" style="display:none">
        <label class="flabel">الأم (زوجة الأب)</label>
        <select class="finput" id="add_mother_id">
          <option value="">— اختر الأم —</option>
        </select>
      </div>

      <!-- الزوج/الزوجة -->
      <div class="fgroup" id="spouseSection" style="display:none">
        <label class="flabel" id="spouseLbl">الزوج / الزوجة</label>
        <div class="picker-wrap" id="spousePickerWrap">
          <div class="picker-input" id="spousePickerToggle" onclick="togglePicker('spouse')">
            <i class="fas fa-heart" style="color:#f472b6;font-size:.8rem"></i>
            <span class="pval empty" id="spousePickerVal">— اختر —</span>
            <i class="fas fa-chevron-down" style="font-size:.7rem;color:var(--text3)"></i>
          </div>
          <div class="picker-dropdown" id="spousePickerDrop">
            <input type="text" class="picker-search" id="spousePickerSearch" placeholder="ابحث…" oninput="filterPicker('spouse',this.value)">
            <div class="picker-list" id="spousePickerList"></div>
          </div>
        </div>
        <div id="selectedSpouses" style="margin-top:8px"></div>
        <input type="hidden" id="add_spouse_ids">
      </div>

      <button class="btn-submit green" onclick="submitAdd(); return false;">
        <i class="fas fa-plus-circle"></i> إضافة الشخص
      </button>
    </div>

    <!-- TAB: تعديل ─────────────────── -->
    <div class="fpanel" id="editTab">
      <div id="editPlaceholder" class="empty-state">
        <i class="fas fa-mouse-pointer"></i>
        <span>اختر شخصاً من قائمة البحث لتعديل بياناته</span>
      </div>

      <!-- شجرة التنقل العائلي -->
      <div class="ftree-nav" id="familyTreeNav" style="display:none">
        <div class="ftree-level" id="ftLevel_gf" style="display:none">
          <div class="ftree-level-label">🏛️ الجد</div>
          <div class="ftree-row" id="ftRow_uncles"></div>
          <div class="ftree-connector"></div>
        </div>
        <div class="ftree-level" id="ftLevel_father" style="display:none">
          <div class="ftree-level-label">👨 الأب / الإخوة</div>
          <div class="ftree-row" id="ftRow_siblings"></div>
          <div class="ftree-connector"></div>
        </div>
        <div class="ftree-level" id="ftLevel_children" style="display:none">
          <div class="ftree-level-label">👶 الأبناء</div>
          <div class="ftree-row" id="ftRow_children"></div>
        </div>
      </div>

      <div id="editForm" style="display:none">
        <div class="msg" id="editMsg"></div>

        <!-- معلومات الشخص الحالي -->
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:9px;padding:12px;margin-bottom:14px" id="personInfoBox"></div>

        <div class="fgroup">
          <label class="flabel">ناڤ (کوردی) *</label>
          <input type="text" class="finput" id="edit_name" dir="rtl">
        </div>
        <div class="fgroup">
          <label class="flabel" style="color:#60a5fa;"><i class="fas fa-language" style="margin-left:4px"></i> English Name</label>
          <input type="text" class="finput" id="edit_name_en" placeholder="Full name in English…" dir="ltr" style="font-family:'Inter',sans-serif;text-align:left;">
        </div>
        <div class="row2">
          <div class="fgroup">
            <label class="flabel">الجنس</label>
            <select class="finput" id="edit_gender">
              <option value="Male">ذكر 🟦</option>
              <option value="Female">أنثى 🟥</option>
            </select>
          </div>
          <div class="fgroup">
            <label class="flabel">الحالة</label>
            <select class="finput" id="edit_status">
              <option value="Alive">حي ✅</option>
              <option value="Deceased">متوفى 🕊</option>
            </select>
          </div>
        </div>
        <div class="fgroup">
          <label class="flabel">تاريخ الميلاد</label>
          <input type="date" class="finput" id="edit_bdate">
        </div>

        <div class="fgroup">
            <label class="flabel">ملاحظات</label>
            <textarea class="ftextarea" id="edit_person_notes" placeholder="أضف ملاحظات عن هذا الشخص…"></textarea>
        </div>

        <div class="fgroup">
          <label class="flabel">صورة الشخص</label>
          <!-- معاينة الصورة الحالية -->
          <div class="img-preview-box" id="edit_current_image_container" style="display:none">
            <img id="edit_current_image_preview" src="" alt="">
            <div class="img-preview-info">
              <div class="img-preview-name">الصورة الحالية</div>
              <div class="img-preview-sub" id="editImgStatus">محفوظة</div>
            </div>
            <button type="button" class="img-del-btn" id="editDelImgBtn" onclick="toggleDeleteImage()">
              <i class="fas fa-trash-alt"></i> حذف
            </button>
          </div>
          <input type="hidden" id="edit_delete_image" value="0">
          <!-- رفع صورة جديدة -->
          <div class="img-upload-area" id="editImgArea" onclick="document.getElementById('edit_person_image').click()">
            <div class="img-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
            <div class="img-upload-label" id="editImgAreaLabel">اضغط لرفع صورة جديدة</div>
            <div class="img-upload-hint">JPG, PNG, GIF · الحجم الأقصى 5MB</div>
            <div class="img-upload-filename" id="editImgName" style="display:none"></div>
            <input type="file" name="edit_person_image" id="edit_person_image" accept="image/*" style="display:none"
                   onchange="previewEditImage(this)">
          </div>
        </div>

        <div class="fgroup">
          <label class="flabel">الأب</label>
          <div class="picker-wrap" id="editFatherPickerWrap">
            <div class="picker-input" id="editFatherPickerToggle" onclick="togglePicker('editFather')">
              <i class="fas fa-user" style="color:var(--male);font-size:.8rem"></i>
              <span class="pval empty" id="editFatherPickerVal">— اختر الأب —</span>
              <span class="clr" id="editFatherPickerClr" onclick="event.stopPropagation();clearPicker('editFather')" style="display:none">✕</span>
              <i class="fas fa-chevron-down" style="font-size:.7rem;color:var(--text3)"></i>
            </div>
            <div class="picker-dropdown" id="editFatherPickerDrop">
              <input type="text" class="picker-search" id="editFatherPickerSearch" placeholder="ابحث عن الأب…" oninput="filterPicker('editFather',this.value)">
              <div class="picker-list" id="editFatherPickerList"></div>
            </div>
          </div>
          <input type="hidden" id="edit_father_id">
        </div>
        <div class="mother-box" id="editMotherBox" style="display:none">
          <label class="flabel">الأم</label>
          <select class="finput" id="edit_mother_id">
            <option value="">— اختر الأم —</option>
          </select>
        </div>
        <input type="hidden" id="edit_person_id">
        <button class="btn-submit" onclick="submitEdit(); return false;">
          <i class="fas fa-save"></i> حفظ التعديلات
        </button>
      </div>
    </div>
    </form>

    <!-- TAB: الأبناء ─────────────────── -->
    <div class="fpanel" id="childrenTab">
      <div id="childrenContent" class="empty-state">
        <i class="fas fa-users"></i>
        <span>اختر أباً من البحث لعرض أبنائه</span>
      </div>
    </div>

  </div><!-- /panel 2 -->

  <!-- ══════════════════ PANEL 3: تفاصيل الأب المختار ════════════════ -->
  <div class="panel" id="fatherDetailPanel">
    <div class="panel-head">
      <i class="fas fa-sitemap" style="color:var(--accent2);font-size:.85rem"></i>
      <h3 id="fatherDetailTitle">تفاصيل الأب</h3>
    </div>
    <div class="panel-body">
      <div class="empty-state" id="fatherDetailEmpty">
        <i class="fas fa-user-tie"></i>
        <span>اختر أباً لعرض تفاصيله وقائمة أبنائه</span>
      </div>
      <div id="fatherDetailContent" style="display:none">
        <!-- info -->
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:9px;padding:11px;margin-bottom:12px" id="fatherInfo"></div>
        <!-- children quick list -->
        <div style="font-size:.78rem;font-weight:700;color:var(--text3);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px">الأبناء</div>
        <div id="fatherChildren"></div>
      </div>
    </div>
  </div>

</div><!-- /workspace -->

<script>
// ══════════════════════════════════════════════════════════════════
//  DATA
// ══════════════════════════════════════════════════════════════════
const MALES   = <?php echo $males_json; ?>;
const FEMALES = <?php echo $females_json; ?>;
const ALL     = [...MALES, ...FEMALES].sort((a,b)=>a.label.localeCompare(b.label,'ar'));

let selectedGenderFilter = '';
let currentSearchQ = '';
let selectedFatherId = null;
let selectedSpouseIds = [];
let editFatherId = null;
let currentEditPersonId = null;

// ══════════════════════════════════════════════════════════════════
//  SEARCH
// ══════════════════════════════════════════════════════════════════
const mainSearch      = document.getElementById('mainSearch');
const firstNameSearch = document.getElementById('firstNameSearch');
const searchResults   = document.getElementById('searchResults');
const searchCount     = document.getElementById('searchCount');

function runSearch() {
  const q1 = mainSearch.value.trim().toLowerCase();
  const q2 = firstNameSearch.value.trim().toLowerCase();
  // combine both
  const q  = q1 || q2;

  let list = ALL;
  if (selectedGenderFilter) list = list.filter(p => p.gender === selectedGenderFilter);

  if (q) {
    list = list.filter(p => {
      const label = FamilyUI.normalize(p.label);
      const fname = p.label.split(' ')[0].toLowerCase(); // first word
      if (q2 && !q1) return fname.startsWith(q2.toLowerCase());
      return FamilyUI.matches(label, q);
    });
  }

  searchCount.textContent = list.length;
  if (!list.length) {
    searchResults.innerHTML = `<div class="empty-state"><i class="fas fa-search"></i><span>لا توجد نتائج</span></div>`;
    return;
  }
  searchResults.innerHTML = list.slice(0,60).map(p => `
    <div class="person-item" data-id="${p.id}" onclick="selectPerson(${p.id}, '${escJs(p.label)}', '${p.gender}')">
      <div class="avatar ${p.gender==='Male'?'male':'female'}">${p.label[0]}</div>
      <div style="flex:1;overflow:hidden">
        <div class="pname">${escHtml(p.label)}</div>
      </div>
      <div class="pstatus"><span class="dot ${p.status==='Deceased'?'dead':'alive'}"></span></div>
    </div>`).join('');
}

mainSearch.addEventListener('input', runSearch);
firstNameSearch.addEventListener('input', runSearch);

document.querySelectorAll('.seg-btn').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('.seg-btn').forEach(x=>x.classList.remove('on'));
    b.classList.add('on');
    selectedGenderFilter = b.dataset.g;
    runSearch();
  });
});

// initial render
runSearch();

// ══════════════════════════════════════════════════════════════════
//  SELECT PERSON → load edit form + father panel
// ══════════════════════════════════════════════════════════════════
function selectPerson(id, label, gender) {
  // highlight
  document.querySelectorAll('.person-item').forEach(el=>{
    el.classList.toggle('selected', +el.dataset.id === +id);
  });

  // if male → show as father detail
  if (gender === 'Male') loadFatherDetail(id, label);

  // load edit form
  loadEditForm(id);
  switchTab('editTab');
}

// ══════════════════════════════════════════════════════════════════
//  FATHER DETAIL PANEL
// ══════════════════════════════════════════════════════════════════
function loadFatherDetail(fid, fname) {
  document.getElementById('fatherDetailTitle').textContent = fname.split(' ')[0] + ' — الأبناء';
  document.getElementById('fatherDetailEmpty').style.display  = 'none';
  document.getElementById('fatherDetailContent').style.display = 'block';

  // father basic info
  fetch('', {method:'POST', body: new URLSearchParams({ajax_action:'get_person', person_id:fid})})
  .then(r=>r.json()).then(d => {
    document.getElementById('fatherInfo').innerHTML = `
      <div class="info-kv"><span class="ik">الاسم</span><span class="iv"><b>${escHtml(d.full_name)}</b></span></div>
      <div class="info-kv"><span class="ik">الجنس</span><span class="iv"><span class="tag m">ذكر</span></span></div>
      <div class="info-kv"><span class="ik">الحالة</span><span class="iv"><span class="tag ${d.status==='Alive'?'alive':'dead'}">${d.status==='Alive'?'حي':'متوفى'}</span></span></div>
      ${d.birth_date?`<div class="info-kv"><span class="ik">الميلاد</span><span class="iv">${d.birth_date}</span></div>`:''}
      ${d.f_name?`<div class="info-kv"><span class="ik">الأب</span><span class="iv">${escHtml(d.f_name)}</span></div>`:''}
    `;
  });

  // children
  const fc = document.getElementById('fatherChildren');
  fc.innerHTML = '<div class="spinner"></div>';
  fetch('', {method:'POST', body: new URLSearchParams({ajax_action:'get_children', father_id:fid})})
  .then(r=>r.json()).then(kids => {
    document.getElementById('childCount').textContent = kids.length ? `(${kids.length})` : '';
    if (!kids.length) {
      fc.innerHTML = `<div class="empty-state"><i class="fas fa-child"></i><span>لا أبناء مسجلين</span></div>`;
      return;
    }
    fc.innerHTML = kids.map(k=>`
      <div class="child-card" onclick="selectPerson(${k.id},'${escHtml(k.full_name)}','${k.gender}')" style="cursor:pointer">
        <div class="avatar ${k.gender==='Male'?'male':'female'}">${k.full_name[0]}</div>
        <div>
          <div class="pname">${escHtml(k.full_name)}</div>
          <div class="psub">${k.gender==='Male'?'ذكر':'أنثى'}${k.mother_name?' · الأم: '+escHtml(k.mother_name):''}</div>
        </div>
        <span class="dot ${k.status==='Alive'?'alive':'dead'}" style="margin-right:auto"></span>
      </div>`).join('');

    // also update children tab
    document.getElementById('childrenContent').innerHTML = fc.innerHTML;
  });
}

// ══════════════════════════════════════════════════════════════════
//  FAMILY TREE NAVIGATOR
// ══════════════════════════════════════════════════════════════════
function loadFamilyTreeNav(pid) {
  fetch('', {method:'POST', body: new URLSearchParams({ajax_action:'get_family_chain', person_id:pid})})
  .then(r=>r.json()).then(data => {
    const nav = document.getElementById('familyTreeNav');
    nav.style.display = 'block';

    const p = data.person;

    // ── مستوى الجد والأعمام ──
    if (data.uncles && data.uncles.length) {
      document.getElementById('ftLevel_gf').style.display = 'block';
      const label = document.querySelector('#ftLevel_gf .ftree-level-label');
      label.textContent = '🏛️ ' + (p.gf_name ? escHtml(p.gf_name) + ' — أبناؤه (الأعمام)' : 'الجد — أبناؤه');
      document.getElementById('ftRow_uncles').innerHTML = data.uncles.map(u => `
        <div class="ftree-node ${u.gender==='Male'?'male-node':'female-node'} ${+u.id===+(p.father_id||0)?'active':''}"
             onclick="selectPerson(${u.id},'${escJs(u.full_name)}','${u.gender}')" title="${escHtml(u.full_name)}">
          <span style="font-size:.75rem">${u.gender==='Male'?'👨':'👩'}</span>
          <span class="nt">${escHtml(u.full_name)}</span>
          <span class="dot ${u.status==='Alive'?'alive':'dead'}"></span>
        </div>`).join('');
    } else {
      document.getElementById('ftLevel_gf').style.display = 'none';
    }

    // ── مستوى الأب والإخوة ──
    if (data.siblings && data.siblings.length) {
      document.getElementById('ftLevel_father').style.display = 'block';
      const sibLabel = document.querySelector('#ftLevel_father .ftree-level-label');
      sibLabel.textContent = '👨 ' + (p.f_name ? p.f_name + ' — أبناؤه (الإخوة)' : 'الإخوة');
      document.getElementById('ftRow_siblings').innerHTML = data.siblings.map(s => `
        <div class="ftree-node ${s.gender==='Male'?'male-node':'female-node'} ${+s.id===+pid?'active':''}"
             onclick="selectPerson(${s.id},'${escJs(s.full_name)}','${s.gender}')" title="${escHtml(s.full_name)}">
          <span style="font-size:.75rem">${s.gender==='Male'?'👦':'👧'}</span>
          <span class="nt">${escHtml(s.full_name)}</span>
          <span class="dot ${s.status==='Alive'?'alive':'dead'}"></span>
        </div>`).join('');
    } else {
      document.getElementById('ftLevel_father').style.display = 'none';
    }

    // ── مستوى الأبناء ──
    if (data.children && data.children.length) {
      document.getElementById('ftLevel_children').style.display = 'block';
      const childLabel = document.querySelector('#ftLevel_children .ftree-level-label');
      childLabel.textContent = `👶 أبناء ${escHtml(p.full_name)} (${data.children.length})`;
      document.getElementById('ftRow_children').innerHTML = data.children.map(c => `
        <div class="ftree-node ${c.gender==='Male'?'male-node':'female-node'}"
             onclick="selectPerson(${c.id},'${escJs(c.full_name)}','${c.gender}')" title="${escHtml(c.full_name)}">
          <span style="font-size:.75rem">${c.gender==='Male'?'👦':'👧'}</span>
          <span class="nt">${escHtml(c.full_name)}</span>
          <span class="dot ${c.status==='Alive'?'alive':'dead'}"></span>
        </div>`).join('');
    } else {
      document.getElementById('ftLevel_children').style.display = data.children && data.children.length === 0 ? 'none' : 'none';
    }
  });
}

// ══════════════════════════════════════════════════════════════════
//  EDIT FORM
// ══════════════════════════════════════════════════════════════════
function loadEditForm(pid) {
  document.getElementById('editPlaceholder').style.display = 'none';
  document.getElementById('editForm').style.display = 'block';
  currentEditPersonId = pid;

  // تحميل شجرة التنقل العائلي
  loadFamilyTreeNav(pid);

  fetch('', {method:'POST', body: new URLSearchParams({ajax_action:'get_person', person_id:pid})})
  .then(r=>r.json()).then(d => {
    if (!d || !d.id) { document.getElementById('editForm').style.display='none'; document.getElementById('editPlaceholder').style.display='flex'; return; }
    document.getElementById('edit_person_id').value = d.id;
    document.getElementById('edit_name').value      = d.full_name;
    document.getElementById('edit_name_en').value   = d.full_name_en || '';
    document.getElementById('edit_gender').value    = d.gender;
    document.getElementById('edit_status').value    = d.status;
    document.getElementById('edit_bdate').value     = d.birth_date || '';
    document.getElementById('edit_person_notes').value = d.notes || '';
    document.getElementById('edit_delete_image').value = '0';
    document.getElementById('editImgName').style.display = 'none';
    document.getElementById('editImgArea').classList.remove('has-file');
    document.getElementById('editImgAreaLabel').textContent = 'اضغط لرفع صورة جديدة';
    // reset file input
    document.getElementById('edit_person_image').value = '';

    // image loader
    const imgCont = document.getElementById('edit_current_image_container');
    const imgPreview = document.getElementById('edit_current_image_preview');
    if (d.image_url) {
      imgCont.style.display = 'flex';
      imgPreview.src = d.image_url;
      imgPreview.alt = 'صورة ' + d.full_name;
      document.getElementById('editImgStatus').textContent = 'محفوظة';
      document.getElementById('editDelImgBtn').className = 'img-del-btn';
      document.getElementById('editDelImgBtn').innerHTML = '<i class="fas fa-trash-alt"></i> حذف';
    } else {
      imgCont.style.display = 'none';
      imgPreview.src = '';
    }

    // person info box
    document.getElementById('personInfoBox').innerHTML = `
      <div class="info-kv"><span class="ik">ID</span><span class="iv" style="color:var(--text3)">#${d.id}</span></div>
      ${d.f_name?`<div class="info-kv"><span class="ik">الأب الحالي</span><span class="iv">${escHtml(d.f_name)}</span></div>`:''}
      ${d.m_name?`<div class="info-kv"><span class="ik">الأم الحالية</span><span class="iv">${escHtml(d.m_name)}</span></div>`:''}
    `;

    // father picker
    if (d.father_id) {
      setPickerValue('editFather', d.father_id, d.f_name||'');
      editFatherId = d.father_id;
      loadEditMothers(d.father_id, d.mother_id);
    } else {
      clearPicker('editFather');
    }
  });
}

function submitEdit() {
  const pid  = document.getElementById('edit_person_id').value;
  const name = document.getElementById('edit_name').value.trim();
  if (!name) return showMsg('editMsg','الاسم مطلوب','err');

  const form = document.getElementById('person_form');
  const formData = new FormData(form);

  formData.set('ajax_action','edit_person');
  formData.set('person_id', pid);
  formData.set('full_name', name);
  formData.set('full_name_en', document.getElementById('edit_name_en').value.trim());
  formData.set('gender', document.getElementById('edit_gender').value);
  formData.set('status', document.getElementById('edit_status').value);
  formData.set('birth_date', document.getElementById('edit_bdate').value);
  formData.set('father_id', document.getElementById('edit_father_id').value);
  formData.set('mother_id', document.getElementById('edit_mother_id').value || '');
  formData.set('notes', document.getElementById('edit_person_notes').value.trim());
  formData.set('delete_image', document.getElementById('edit_delete_image').value);

  // attach new image file if selected
  const editFileInput = document.getElementById('edit_person_image');
  if (editFileInput && editFileInput.files[0]) {
    formData.set('person_image', editFileInput.files[0]);
  }

  fetch('add_person.php', {method:'POST', body:formData}).then(r=>r.json()).then(d => {
    if (d.ok) {
      showMsg('editMsg','✅ تم حفظ التعديلات بنجاح','ok');
      // update local data
      const item = ALL.find(p=>+p.id===+pid);
      if (item) { item.label = name; }
      runSearch();
    } else {
      showMsg('editMsg','❌ '+d.error,'err');
    }
  });
}

// ══════════════════════════════════════════════════════════════════
//  ADD PERSON
// ══════════════════════════════════════════════════════════════════
function submitAdd() {
  const name   = document.getElementById('add_name').value.trim();
  const gender = document.getElementById('add_gender').value;
  if (!name || !gender) return showMsg('addMsg','الاسم والجنس مطلوبان','err');

  document.getElementById('add_spouse_ids').value = selectedSpouseIds.join(',');
  const form = document.getElementById('person_form');
  const formData = new FormData(form);

  formData.set('ajax_action','add_person');
  formData.set('full_name', name);
  formData.set('full_name_en', document.getElementById('add_name_en').value.trim());
  formData.set('gender', gender);
  formData.set('status', document.getElementById('add_status').value);
  formData.set('birth_date', document.getElementById('add_bdate').value);
  formData.set('father_id', document.getElementById('add_father_id').value || '');
  formData.set('mother_id', document.getElementById('add_mother_id').value || '');
  formData.set('spouse_ids', selectedSpouseIds.join(','));
  formData.set('notes', document.getElementById('person_notes').value.trim());
  // attach image file if selected
  const addFileInput = document.getElementById('person_image');
  if (addFileInput && addFileInput.files[0]) {
    formData.set('person_image', addFileInput.files[0]);
  }

  fetch('add_person.php', {method:'POST', body:formData}).then(r=>r.json()).then(d => {
    if (d.ok) {
      showMsg('addMsg',`✅ تمت إضافة "${d.name}" بنجاح (ID: ${d.id})`,'ok');
      // add to local list
      const obj = {id:d.id, label:name, gender, fname:''};
      ALL.push(obj);
      if (gender==='Male') MALES.push(obj); else FEMALES.push(obj);
      // reset
      document.getElementById('add_name').value = '';
      document.getElementById('add_name_en').value = '';
      document.getElementById('add_bdate').value = '';
      selectedSpouseIds = [];
      document.getElementById('selectedSpouses').innerHTML = '';
      runSearch();
    } else {
      showMsg('addMsg','❌ '+d.error,'err');
    }
  });
}

// ══════════════════════════════════════════════════════════════════
//  MOTHER LOADER (add form)
// ══════════════════════════════════════════════════════════════════
function loadMothers(fatherId) {
  const box = document.getElementById('motherBox');
  const sel = document.getElementById('add_mother_id');
  if (!fatherId) { box.style.display='none'; return; }
  box.style.display = 'block';
  sel.innerHTML = '<option value="">جاري التحميل…</option>';
  fetch('', {method:'POST', body: new URLSearchParams({ajax_action:'get_wives', father_id:fatherId})})
  .then(r=>r.json()).then(wives => {
    if (!wives.length) {
      sel.innerHTML = '<option value="">لا توجد زوجات مسجلة</option>';
      return;
    }
    sel.innerHTML = '<option value="">— اختر الأم —</option>' +
      wives.map(w=>`<option value="${w.id}">${escHtml(w.full_name)}</option>`).join('');
    if (wives.length===1) sel.value = wives[0].id;
  });
}

function loadEditMothers(fatherId, currentMotherId) {
  const box = document.getElementById('editMotherBox');
  const sel = document.getElementById('edit_mother_id');
  if (!fatherId) { box.style.display='none'; return; }
  box.style.display = 'block';
  sel.innerHTML = '<option value="">جاري التحميل…</option>';
  fetch('', {method:'POST', body: new URLSearchParams({ajax_action:'get_wives', father_id:fatherId})})
  .then(r=>r.json()).then(wives => {
    if (!wives.length) { sel.innerHTML='<option value="">لا توجد زوجات</option>'; return; }
    sel.innerHTML = '<option value="">— اختر الأم —</option>' +
      wives.map(w=>`<option value="${w.id}" ${+w.id===+currentMotherId?'selected':''}>${escHtml(w.full_name)}</option>`).join('');
  });
}

// ══════════════════════════════════════════════════════════════════
//  SPOUSE PICKER (add form)
// ══════════════════════════════════════════════════════════════════
document.getElementById('add_gender').addEventListener('change', function() {
  const gender = this.value;
  const sec    = document.getElementById('spouseSection');
  const lbl    = document.getElementById('spouseLbl');
  if (!gender) { sec.style.display='none'; return; }
  sec.style.display = 'block';
  lbl.textContent   = gender==='Male' ? 'الزوجة (يمكن اختيار أكثر من واحدة)' : 'الزوج';
  selectedSpouseIds = [];
  document.getElementById('selectedSpouses').innerHTML = '';
  // populate spouse picker
  const pool = gender==='Male' ? FEMALES : MALES;
  buildPickerList('spouse', pool);
});

function addSpouseById(id, label) {
  if (selectedSpouseIds.includes(id)) return;
  const gender = document.getElementById('add_gender').value;
  if (gender==='Female') { selectedSpouseIds=[id]; }
  else if (!selectedSpouseIds.includes(id)) selectedSpouseIds.push(id);

  const cont = document.getElementById('selectedSpouses');
  if (gender==='Female') cont.innerHTML = '';
  const row = document.createElement('div');
  row.className='spouse-row';
  row.dataset.sid = id;
  row.innerHTML = `<i class="fas fa-heart" style="color:#f472b6;font-size:.75rem"></i>
    <span style="flex:1;font-size:.82rem">${escHtml(label)}</span>
    <span class="del" onclick="removeSpouse(${id}, this)">✕</span>`;
  cont.appendChild(row);
  closePicker('spouse');
  document.getElementById('spousePickerVal').textContent = gender==='Female' ? label : `${selectedSpouseIds.length} زوجة`;
  document.getElementById('spousePickerVal').classList.remove('empty');
}

function removeSpouse(id, el) {
  selectedSpouseIds = selectedSpouseIds.filter(x=>x!==id);
  el.closest('.spouse-row').remove();
  if (!selectedSpouseIds.length) {
    const v = document.getElementById('spousePickerVal');
    v.textContent='— اختر —'; v.classList.add('empty');
  }
}

// ══════════════════════════════════════════════════════════════════
//  GENERIC PICKER (father / editFather / spouse)
// ══════════════════════════════════════════════════════════════════
const pickerDefs = {
  father:     { drop:'fatherPickerDrop',  list:'fatherPickerList',  val:'fatherPickerVal',  clr:'fatherPickerClr',  hid:'add_father_id',  pool: ()=>MALES,   onSelect: (id,lbl)=>{ selectedFatherId=id; loadMothers(id); loadFatherDetailById(id,lbl); } },
  editFather: { drop:'editFatherPickerDrop', list:'editFatherPickerList', val:'editFatherPickerVal', clr:'editFatherPickerClr', hid:'edit_father_id', pool:()=>MALES, onSelect:(id,lbl)=>{ editFatherId=id; loadEditMothers(id,null); } },
  spouse:     { drop:'spousePickerDrop',  list:'spousePickerList',  val:'spousePickerVal',  clr:null,              hid:null,             pool: ()=>[], onSelect:(id,lbl)=>addSpouseById(id,lbl) }
};

function buildPickerList(key, pool, q='') {
  const def  = pickerDefs[key];
  const el   = document.getElementById(def.list);
  const q_lc = q.toLowerCase();
  const items = q ? pool.filter(p=>FamilyUI.matches(p.label, q_lc)) : pool;
  if (!items.length) { el.innerHTML=`<div class="picker-empty">لا توجد نتائج</div>`; return; }
  el.innerHTML = items.slice(0,50).map(p=>`
    <div class="picker-opt" onclick="selectPickerItem('${key}',${p.id},'${escJs(p.label)}')">
      ${escHtml(p.label)}
      ${p.fname?`<div class="sub">← ${escHtml(p.fname)}</div>`:''}
    </div>`).join('');
}

function togglePicker(key) {
  const def  = pickerDefs[key];
  const drop = document.getElementById(def.drop);
  const open = drop.classList.contains('open');
  // close all
  document.querySelectorAll('.picker-dropdown').forEach(d=>d.classList.remove('open'));
  if (!open) {
    drop.classList.add('open');
    buildPickerList(key, def.pool());
    const si = document.getElementById(def.list.replace('List','Search'));
    if (si) { si.value=''; si.focus(); }
  }
}

function closePicker(key) {
  document.getElementById(pickerDefs[key].drop).classList.remove('open');
}

function filterPicker(key, q) {
  buildPickerList(key, pickerDefs[key].pool(), q);
}

function selectPickerItem(key, id, label) {
  const def = pickerDefs[key];
  if (def.hid)  document.getElementById(def.hid).value = id;
  if (def.val) {
    const v = document.getElementById(def.val);
    v.textContent = label; v.classList.remove('empty');
  }
  if (def.clr) document.getElementById(def.clr).style.display='inline';
  closePicker(key);
  def.onSelect(id, label);
}

function setPickerValue(key, id, label) {
  const def = pickerDefs[key];
  if (def.hid) document.getElementById(def.hid).value = id;
  const v = document.getElementById(def.val);
  v.textContent = label || '#'+id; v.classList.remove('empty');
  if (def.clr) document.getElementById(def.clr).style.display='inline';
}

function clearPicker(key) {
  const def = pickerDefs[key];
  if (def.hid) document.getElementById(def.hid).value = '';
  const v = document.getElementById(def.val);
  v.textContent = '— اختر —'; v.classList.add('empty');
  if (def.clr) document.getElementById(def.clr).style.display='none';
  if (key==='father') { document.getElementById('motherBox').style.display='none'; selectedFatherId=null; }
}

// close pickers on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.picker-wrap')) {
    document.querySelectorAll('.picker-dropdown').forEach(d=>d.classList.remove('open'));
  }
});

// ══════════════════════════════════════════════════════════════════
//  LOAD FATHER DETAIL FROM PICKER
// ══════════════════════════════════════════════════════════════════
function loadFatherDetailById(id, label) {
  loadFatherDetail(id, label);
  // Keep the add form open while its father is being selected.
}

// ══════════════════════════════════════════════════════════════════
//  TABS
// ══════════════════════════════════════════════════════════════════
document.querySelectorAll('.ftab').forEach(t => {
  t.addEventListener('click', () => switchTab(t.dataset.tab));
});
function switchTab(id) {
  document.querySelectorAll('.ftab').forEach(t=>t.classList.toggle('on', t.dataset.tab===id));
  document.querySelectorAll('.fpanel').forEach(p=>p.classList.toggle('on', p.id===id));

  // reset scroll أو إعادة الضبط عند تبديل التبويبات
  const activePanel = document.getElementById(id);
  if (activePanel) activePanel.scrollTop = 0;
}

// ══════════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════════
function showMsg(id, txt, type) {
  const el = document.getElementById(id);
  el.textContent = txt; el.className = 'msg '+type; el.style.display='block';
  setTimeout(()=>{el.style.display='none';}, 4000);
}
function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(s) { return escHtml(String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/[\r\n]/g,' ')); }



// ══════════════════════════════════════════════════════════════════
//  IMAGE UPLOAD HELPERS
// ══════════════════════════════════════════════════════════════════
function previewAddImage(input) {
  const area  = document.getElementById('addImgArea');
  const label = document.getElementById('addImgName');
  if (input.files && input.files[0]) {
    area.classList.add('has-file');
    label.textContent = input.files[0].name;
    label.style.display = 'block';
  } else {
    area.classList.remove('has-file');
    label.style.display = 'none';
  }
}

function previewEditImage(input) {
  const area  = document.getElementById('editImgArea');
  const label = document.getElementById('editImgName');
  if (input.files && input.files[0]) {
    area.classList.add('has-file');
    document.getElementById('editImgAreaLabel').textContent = 'تم اختيار صورة جديدة';
    label.textContent = input.files[0].name;
    label.style.display = 'block';
    // cancel any pending delete if user chose new image
    document.getElementById('edit_delete_image').value = '0';
    const btn = document.getElementById('editDelImgBtn');
    if (btn) { btn.className='img-del-btn'; btn.innerHTML='<i class="fas fa-trash-alt"></i> حذف'; }
    document.getElementById('editImgStatus').textContent = 'سيتم استبدالها';
  } else {
    area.classList.remove('has-file');
    document.getElementById('editImgAreaLabel').textContent = 'اضغط لرفع صورة جديدة';
    label.style.display = 'none';
  }
}

function toggleDeleteImage() {
  const hiddenInput = document.getElementById('edit_delete_image');
  const btn         = document.getElementById('editDelImgBtn');
  const status      = document.getElementById('editImgStatus');
  if (hiddenInput.value === '1') {
    // cancel delete
    hiddenInput.value = '0';
    btn.className = 'img-del-btn';
    btn.innerHTML = '<i class="fas fa-trash-alt"></i> حذف';
    status.textContent = 'محفوظة';
  } else {
    // mark for delete
    hiddenInput.value = '1';
    btn.className = 'img-del-btn confirmed';
    btn.innerHTML = '<i class="fas fa-undo"></i> تراجع';
    status.style.color = 'var(--red)';
    status.textContent = 'ستُحذف عند الحفظ';
    // clear any new file selection too
    document.getElementById('edit_person_image').value = '';
    document.getElementById('editImgArea').classList.remove('has-file');
    document.getElementById('editImgName').style.display = 'none';
    document.getElementById('editImgAreaLabel').textContent = 'اضغط لرفع صورة جديدة';
  }
}

    // هذا الكود يقوم بالبحث عن أي Service Worker قديم وحذفه نهائياً
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for(let registration of registrations) {
                registration.unregister().then(function(boolean) {
                    console.log('تم إزالة Service Worker القديم بنجاح!');
                });
            }
        });
    }
</script>
</body>
</html>