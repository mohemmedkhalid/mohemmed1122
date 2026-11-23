<?php
require_once 'header.php';

if (!isAdmin()) {
    $_SESSION['message'] = 'هذه الصفحة للمشرف فقط.';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// ── دوال مساعدة ──────────────────────────────────────────────────────────────
function generate_display_name3($row) {
    $parts = [htmlspecialchars($row['full_name'])];
    if (!empty($row['p_father_name'])) {
        $parts[] = htmlspecialchars($row['p_father_name']);
        if (!empty($row['p_grandfather_name'])) $parts[] = htmlspecialchars($row['p_grandfather_name']);
    }
    return implode(" ", $parts);
}

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // بحث سريع
    if ($_POST['ajax_action'] === 'search') {
        $q   = '%' . $mysqli->real_escape_string(trim(preg_replace('/\s+/u', ' ', preg_replace('/(?:^|\s)(?:بن|ابن|بنت)(?=\s|$)/u', ' ', $_POST['q'] ?? '')))) . '%';
        $gen = in_array($_POST['gender'] ?? '', ['Male','Female'], true) ? $_POST['gender'] : '';
        $gw  = $gen ? "AND p.gender='$gen'" : '';
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.gender, p.status,
                   f.full_name AS p_father_name, gf.full_name AS p_grandfather_name,
                   f.id AS father_id
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id  = f.id
            LEFT JOIN people2 gf ON f.father_id  = gf.id
            WHERE (p.full_name LIKE '$q'
               OR CONCAT(p.full_name,' ',IFNULL(f.full_name,'')) LIKE '$q' OR CONCAT(p.full_name,' ',IFNULL(f.full_name,''),' ',IFNULL(gf.full_name,'')) LIKE '$q') $gw
            ORDER BY f.full_name, p.full_name LIMIT 80");
        $out = [];
        while ($r = $rows->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    // زوجات الأب
    if ($_POST['ajax_action'] === 'get_wives') {
        $fid  = (int)$_POST['father_id'];
        $rows = $mysqli->query("
            SELECT w.id, w.full_name,
                   wf.full_name AS w_father_name,
                   wgf.full_name AS w_grandfather_name
            FROM people2 w
            INNER JOIN marriages m ON w.id = m.wife_id AND m.husband_id = $fid
            LEFT JOIN people2 wf  ON w.father_id  = wf.id
            LEFT JOIN people2 wgf ON wf.father_id = wgf.id
            ORDER BY w.full_name");
        $out = [];
        while ($r = $rows->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    // حفظ أم الشخص
    if ($_POST['ajax_action'] === 'set_mother') {
        $pid = (int)$_POST['person_id'];
        $mid = (int)$_POST['mother_id'];
        $ok  = $mysqli->query("UPDATE people2 SET mother_id=$mid WHERE id=$pid");
        echo json_encode(['ok' => $ok, 'error' => $mysqli->error]); exit;
    }

    // تفاصيل الأب الكاملة (قائمة أبنائه مع أمهاتهم)
    if ($_POST['ajax_action'] === 'get_father_detail') {
        $fid = (int)$_POST['father_id'];
        $father = $mysqli->query("
            SELECT p.id, p.full_name, p.gender, p.status, p.birth_date,
                   gf.full_name AS grandfather_name
            FROM people2 p
            LEFT JOIN people2 gf ON p.father_id = gf.id
            WHERE p.id = $fid")->fetch_assoc();

        $wives = $mysqli->query("
            SELECT w.id, w.full_name,
                   wf.full_name AS w_father_name,
                   wgf.full_name AS w_grandfather_name
            FROM people2 w
            INNER JOIN marriages m ON w.id = m.wife_id AND m.husband_id = $fid
            LEFT JOIN people2 wf  ON w.father_id  = wf.id
            LEFT JOIN people2 wgf ON wf.father_id = wgf.id
            ORDER BY w.full_name");
        $wives_arr = [];
        while ($r = $wives->fetch_assoc()) $wives_arr[] = $r;

        $children = $mysqli->query("
            SELECT p.id, p.full_name, p.gender, p.status,
                   m.id AS mother_id, m.full_name AS mother_name,
                   mf.full_name AS mother_father_name
            FROM people2 p
            LEFT JOIN people2 m  ON p.mother_id = m.id
            LEFT JOIN people2 mf ON m.father_id = mf.id
            WHERE p.father_id = $fid
            ORDER BY m.full_name, p.full_name");
        $children_arr = [];
        while ($r = $children->fetch_assoc()) $children_arr[] = $r;

        echo json_encode([
            'father'   => $father,
            'wives'    => $wives_arr,
            'children' => $children_arr,
        ]); exit;
    }

    // قائمة الكل لبناء الشجرة
    if ($_POST['ajax_action'] === 'get_tree') {
        $gen = in_array($_POST['gender'] ?? '', ['Male','Female'], true) ? $_POST['gender'] : '';
        $gw  = $gen ? "WHERE p.gender='$gen'" : '';
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.gender, p.status,
                   p.father_id, p.mother_id,
                   f.full_name  AS p_father_name,
                   gf.full_name AS p_grandfather_name,
                   m.full_name  AS mother_name
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id  = f.id
            LEFT JOIN people2 gf ON f.father_id  = gf.id
            LEFT JOIN people2 m  ON p.mother_id  = m.id
            $gw
            ORDER BY gf.full_name, f.full_name, p.full_name");
        $out = [];
        while ($r = $rows->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    // كل الأمهات (إناث)
    if ($_POST['ajax_action'] === 'get_all_females') {
        $rows = $mysqli->query("
            SELECT p.id, p.full_name,
                   f.full_name AS p_father_name,
                   gf.full_name AS p_grandfather_name
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id  = f.id
            LEFT JOIN people2 gf ON f.father_id  = gf.id
            WHERE p.gender='Female'
            ORDER BY p.full_name");
        $out = [];
        while ($r = $rows->fetch_assoc()) $out[] = $r;
        echo json_encode($out); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown']); exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>قائمة شجرة العائلة</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0d1117;--surface:#161b22;--surface2:#1f2937;
  --border:#30363d;--border2:#3d4852;
  --accent:#3b82f6;--accent2:#60a5fa;
  --green:#22c55e;--red:#ef4444;--amber:#f59e0b;
  --text:#e6edf3;--text2:#8b949e;--text3:#6e7681;
  --male:#60a5fa;--female:#f472b6;
  --radius:10px;
}
html,body{height:100%;font-family:'Cairo',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
body{display:flex;flex-direction:column;overflow:hidden}

/* ── TOP BAR ── */
.topbar{height:52px;background:var(--surface);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 18px;gap:14px;flex-shrink:0;z-index:10}
.topbar-logo{font-weight:900;font-size:1.05rem;color:var(--accent2)}
.topbar-logo span{color:var(--text3)}
.topbar-nav{display:flex;gap:7px;margin-right:auto}
.tbtn{background:transparent;border:1px solid var(--border);color:var(--text2);padding:5px 12px;
  border-radius:7px;cursor:pointer;font-family:inherit;font-size:.8rem;transition:.15s;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
.tbtn:hover,.tbtn.active{border-color:var(--accent);color:var(--accent2);background:rgba(59,130,246,.08)}

/* ── LAYOUT ── */
.workspace{display:grid;grid-template-columns:280px 1fr 320px;height:calc(100vh - 52px);overflow:hidden}
.panel{border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.panel:last-child{border-right:none;border-left:1px solid var(--border)}

/* ── PANEL HEAD ── */
.panel-head{padding:12px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px;flex-shrink:0;background:var(--surface)}
.panel-head h3{font-size:.9rem;font-weight:700;color:var(--text);flex:1}
.badge{background:rgba(59,130,246,.15);color:var(--accent2);font-size:.72rem;font-weight:700;
  padding:2px 8px;border-radius:20px;border:1px solid rgba(59,130,246,.2)}
.panel-body{flex:1;overflow-y:auto;padding:12px}
.panel-body::-webkit-scrollbar{width:5px}
.panel-body::-webkit-scrollbar-thumb{background:var(--border2);border-radius:5px}

/* ── SEARCH ── */
.search-wrap{display:flex;align-items:center;gap:8px;background:var(--surface2);
  border:1px solid var(--border);border-radius:8px;padding:7px 11px;margin-bottom:8px}
.search-wrap i{color:var(--text3);font-size:.82rem;flex-shrink:0}
.search-input{background:none;border:none;outline:none;color:var(--text);font-family:inherit;
  font-size:.84rem;width:100%}
.search-input::placeholder{color:var(--text3)}

/* ── SEG ── */
.seg{display:flex;gap:5px;margin-bottom:9px}
.seg-btn{flex:1;padding:6px;border-radius:7px;border:1px solid var(--border);
  background:none;color:var(--text2);font-family:inherit;font-size:.76rem;font-weight:700;cursor:pointer;transition:.15s}
.seg-btn.on{background:rgba(59,130,246,.15);border-color:var(--accent);color:var(--accent2)}

/* ── PERSON CARD ── */
.person-card{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:8px;
  border:1px solid transparent;margin-bottom:5px;cursor:pointer;transition:.15s;
  background:var(--surface2)}
.person-card:hover{border-color:var(--accent);background:rgba(59,130,246,.05)}
.person-card.selected{border-color:var(--accent);background:rgba(59,130,246,.1)}
.pcard-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:.85rem;flex-shrink:0}
.pcard-icon.male{background:rgba(96,165,250,.15);color:var(--male)}
.pcard-icon.female{background:rgba(244,114,182,.15);color:var(--female)}
.pcard-body{flex:1;overflow:hidden}
.pcard-name{font-size:.85rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pcard-sub{font-size:.72rem;color:var(--text3);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tag{display:inline-block;padding:1px 7px;border-radius:5px;font-size:.68rem;font-weight:700}
.tag.alive{background:rgba(34,197,94,.12);color:#86efac}
.tag.dead{background:rgba(107,114,128,.12);color:#9ca3af}

/* ── TREE TABLE ── */
.tree-wrap{width:100%;overflow:auto}
.tree-table{width:100%;border-collapse:collapse;font-size:.82rem}
.tree-table th{background:var(--surface2);color:var(--text3);font-size:.72rem;font-weight:700;
  padding:8px 10px;text-align:right;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:2;white-space:nowrap}
.tree-table td{padding:7px 10px;border-bottom:1px solid rgba(48,54,61,.5);vertical-align:middle}
.tree-table tr:hover td{background:rgba(59,130,246,.04)}
.tree-table tr.group-header td{background:var(--surface);color:var(--text2);font-weight:700;font-size:.78rem;padding:5px 10px}

.name3{font-weight:700;color:var(--text)}
.name-father{color:var(--text2);font-size:.78rem}
.name-gf{color:var(--text3);font-size:.72rem}

/* ── MOTHER CELL ── */
.mother-cell{display:flex;align-items:center;gap:6px}
.mother-set{font-size:.78rem;color:#86efac;font-weight:700;max-width:130px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.mother-empty{font-size:.76rem;color:var(--text3);font-style:italic}
.btn-set-mother{background:none;border:1px solid var(--border2);color:var(--text3);border-radius:6px;
  padding:3px 8px;font-size:.72rem;cursor:pointer;font-family:inherit;transition:.12s;white-space:nowrap}
.btn-set-mother:hover{border-color:var(--accent);color:var(--accent2)}

/* ── FATHER DETAIL (right panel) ── */
.detail-section{margin-bottom:14px}
.detail-title{font-size:.72rem;font-weight:700;color:var(--text3);text-transform:uppercase;
  letter-spacing:.5px;margin-bottom:7px;display:flex;align-items:center;gap:6px}
.detail-title i{font-size:.75rem}
.wife-card{background:var(--surface2);border:1px solid var(--border);border-radius:8px;
  padding:8px 10px;margin-bottom:5px;display:flex;align-items:center;gap:8px}
.wife-name{font-size:.82rem;font-weight:700;color:var(--female)}
.wife-sub{font-size:.72rem;color:var(--text3)}
.child-row{display:flex;align-items:center;gap:7px;padding:5px 8px;border-radius:6px;
  border:1px solid transparent;margin-bottom:4px;transition:.12s;cursor:pointer}
.child-row:hover{border-color:var(--border2);background:var(--surface2)}
.child-name{font-size:.82rem;font-weight:700;flex:1}
.child-name.male{color:var(--male)}
.child-name.female{color:var(--female)}
.child-mother{font-size:.7rem;color:var(--text3)}

.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;
  height:200px;color:var(--text3);gap:10px;font-size:.85rem}
.empty-state i{font-size:2rem;opacity:.3}

.info-row{display:flex;gap:6px;padding:5px 0;border-bottom:1px solid rgba(48,54,61,.5);align-items:center}
.info-row:last-child{border-bottom:none}
.ik{width:75px;font-size:.72rem;color:var(--text3);font-weight:700;flex-shrink:0}
.iv{font-size:.8rem;color:var(--text);flex:1}

/* ── MODAL (اختيار الأم) ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;
  display:flex;align-items:center;justify-content:center;padding:20px;display:none}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:14px;
  width:100%;max-width:500px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;
  box-shadow:0 20px 60px rgba(0,0,0,.6)}
.modal-head{padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0}
.modal-head h3{flex:1;font-size:1rem;font-weight:700}
.modal-close{background:none;border:none;color:var(--text3);font-size:1.2rem;cursor:pointer;padding:4px}
.modal-close:hover{color:var(--red)}
.modal-body{flex:1;overflow-y:auto;padding:14px}
.modal-body::-webkit-scrollbar{width:5px}
.modal-body::-webkit-scrollbar-thumb{background:var(--border2);border-radius:5px}
.modal-footer{padding:12px 18px;border-top:1px solid var(--border);display:flex;gap:9px;flex-shrink:0}

.person-target-info{background:var(--surface2);border:1px solid var(--border);border-radius:8px;
  padding:10px;margin-bottom:12px;font-size:.83rem}
.person-target-name{font-weight:700;color:var(--accent2);margin-bottom:3px}
.person-target-sub{font-size:.75rem;color:var(--text3)}

.wives-list-label{font-size:.75rem;font-weight:700;color:var(--female);margin-bottom:7px;display:block}
.wife-option{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:8px;
  border:1px solid var(--border);background:var(--surface2);margin-bottom:5px;cursor:pointer;transition:.13s}
.wife-option:hover{border-color:var(--female);background:rgba(244,114,182,.05)}
.wife-option.selected{border-color:var(--female);background:rgba(244,114,182,.1)}
.wife-option input[type=radio]{accent-color:var(--female)}
.wife-option-name{font-size:.83rem;font-weight:700;color:var(--text)}
.wife-option-sub{font-size:.72rem;color:var(--text3)}

.search-other-label{font-size:.75rem;font-weight:700;color:var(--text3);margin:12px 0 6px;display:block}
.other-result{display:flex;align-items:center;gap:8px;padding:6px 9px;border-radius:7px;
  border:1px solid transparent;cursor:pointer;transition:.12s;font-size:.8rem}
.other-result:hover{border-color:var(--border2);background:var(--surface2)}
.other-result.selected{border-color:var(--female);background:rgba(244,114,182,.08);color:var(--female)}

.btn{padding:9px 18px;border-radius:8px;border:none;font-family:inherit;font-size:.85rem;
  font-weight:700;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:var(--accent);color:#fff}
.btn-primary:hover{background:var(--accent2)}
.btn-secondary{background:var(--surface2);border:1px solid var(--border);color:var(--text2)}
.btn-secondary:hover{border-color:var(--border2);color:var(--text)}
.btn-green{background:var(--green);color:#fff}
.btn-green:hover{background:#16a34a}

.msg-inline{padding:7px 11px;border-radius:7px;font-size:.78rem;font-weight:600;display:none;margin-bottom:8px}
.msg-inline.ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#86efac}
.msg-inline.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5}

.spinner{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);
  border-radius:50%;animation:spin .7s linear infinite;display:inline-block;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}

.load-state{text-align:center;padding:30px;color:var(--text3);font-size:.85rem}

@media(max-width:900px){
  .workspace{grid-template-columns:1fr;height:auto;overflow:auto}
  .panel{border-right:none;border-top:1px solid var(--border);min-height:350px}
  .panel:last-child{border-left:none}
}
</style>
<link rel="stylesheet" href="assets/family-mobile.css?v=5">
<script src="assets/family-ui.js?v=5"></script>
</head>
<body data-family-page="list">

<!-- TOP BAR -->
<div class="topbar">
  <span class="topbar-logo">قائمة شجرة العائلة</span>
  <div class="topbar-nav">
    <a href="index.php" class="tbtn"><i class="fas fa-home"></i> الرئيسية</a>
    <a href="add_person.php" class="tbtn"><i class="fas fa-user-plus"></i> إضافة شخص</a>
    <a href="set_mother.php" class="tbtn active"><i class="fas fa-female"></i> تحديد الأم</a>
    <a href="tree.php" class="tbtn"><i class="fas fa-sitemap"></i> الشجرة</a>
  </div>
</div>

<div class="workspace">

  <!-- ══════ عمود 1: البحث والقائمة ══════ -->
  <div class="panel">
    <div class="panel-head">
      <i class="fas fa-search" style="color:var(--accent2)"></i>
      <h3>البحث السريع</h3>
      <span class="badge" id="listCount">—</span>
    </div>
    <div class="panel-body">
      <div class="seg" id="genderSeg">
        <button class="seg-btn on" data-g="">الكل</button>
        <button class="seg-btn" data-g="Male">ذكور</button>
        <button class="seg-btn" data-g="Female">إناث</button>
      </div>
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" class="search-input" id="quickSearch" placeholder="ابحث بالاسم أو اسم الأب…">
      </div>
      <div id="searchResultsList"><div class="load-state"><span class="spinner"></span> جاري التحميل…</div></div>
    </div>
  </div>

  <!-- ══════ عمود 2: جدول الشجرة ══════ -->
  <div class="panel">
    <div class="panel-head">
      <i class="fas fa-list-ul" style="color:var(--accent2)"></i>
      <h3 id="tableTitle">قائمة الأشخاص - شجرة العائلة</h3>
    </div>
    <div class="panel-body" style="padding:0">
      <div class="tree-wrap">
        <table class="tree-table">
          <thead>
            <tr>
              <th style="width:36px">#</th>
              <th>الاسم الثلاثي</th>
              <th>الجنس</th>
              <th>الحالة</th>
              <th>الأم</th>
              <th style="width:80px">تحديد الأم</th>
            </tr>
          </thead>
          <tbody id="treeTableBody">
            <tr><td colspan="6" class="load-state"><span class="spinner"></span> جاري تحميل البيانات…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══════ عمود 3: تفاصيل الأب ══════ -->
  <div class="panel">
    <div class="panel-head">
      <i class="fas fa-user-tie" style="color:var(--accent2)"></i>
      <h3 id="fatherPanelTitle">تفاصيل الأب</h3>
    </div>
    <div class="panel-body">
      <div class="empty-state" id="fatherDetailEmpty">
        <i class="fas fa-mouse-pointer"></i>
        <span>اختر شخصاً أو أباً لعرض التفاصيل</span>
      </div>
      <div id="fatherDetailContent" style="display:none"></div>
    </div>
  </div>

</div><!-- /workspace -->

<!-- ══════ MODAL: اختيار الأم ══════ -->
<div class="modal-overlay" id="motherModal">
  <div class="modal">
    <div class="modal-head">
      <i class="fas fa-female" style="color:var(--female)"></i>
      <h3>تحديد أم الشخص</h3>
      <button class="modal-close" onclick="closeMotherModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="msg-inline" id="modalMsg"></div>
      <div id="modalPersonInfo"></div>
      <div id="modalWivesList"></div>
      <span class="search-other-label">أو بحث في كل النساء:</span>
      <div class="search-wrap" style="margin-bottom:8px">
        <i class="fas fa-search"></i>
        <input type="text" class="search-input" id="modalSearch" placeholder="ابحث باسم الأم…">
      </div>
      <div id="modalOtherResults"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-green" onclick="saveMotherSelection()">
        <i class="fas fa-save"></i> حفظ
      </button>
      <button class="btn btn-secondary" onclick="closeMotherModal()">إلغاء</button>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════════
let currentGender    = '';
let currentPersonId  = null;   // الشخص المختار لتعديل أمه
let selectedMotherId = null;   // الأم المختارة في المودال
let allFemales       = [];     // كل الإناث للبحث
let searchDebounce   = null;

// ══════════════════════════════════════════════════════════════
//  AJAX
// ══════════════════════════════════════════════════════════════
async function ajax(data) {
  const fd = new FormData();
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch(location.href, { method:'POST', body:fd });
  return r.json();
}

// ══════════════════════════════════════════════════════════════
//  INIT
// ══════════════════════════════════════════════════════════════
(async function init() {
  // حمّل الشجرة الكاملة
  loadTree('');
  // حمّل كل الإناث للبحث في المودال
  const females = await ajax({ ajax_action:'get_all_females' });
  allFemales = females;
  // بحث أولي
  doSearch('');
})();

// ══════════════════════════════════════════════════════════════
//  GENDER SEG
// ══════════════════════════════════════════════════════════════
document.querySelectorAll('#genderSeg .seg-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#genderSeg .seg-btn').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    currentGender = btn.dataset.g;
    loadTree(currentGender);
    doSearch(document.getElementById('quickSearch').value);
  });
});

// ══════════════════════════════════════════════════════════════
//  SEARCH (عمود 1)
// ══════════════════════════════════════════════════════════════
document.getElementById('quickSearch').addEventListener('input', e => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => doSearch(e.target.value), 250);
});

async function doSearch(q) {
  const res = await ajax({ ajax_action:'search', q, gender:currentGender });
  const el  = document.getElementById('searchResultsList');
  document.getElementById('listCount').textContent = res.length;
  if (!res.length) { el.innerHTML=`<div class="empty-state" style="height:120px"><i class="fas fa-search"></i><span>لا توجد نتائج</span></div>`; return; }
  el.innerHTML = res.map(p => {
    const n2 = p.p_father_name  ? ` <span style="color:var(--text3)">← ${esc(p.p_father_name)}</span>`  : '';
    const n3 = p.p_grandfather_name ? ` <span style="color:var(--text3);opacity:.7">← ${esc(p.p_grandfather_name)}</span>` : '';
    const gCls = p.gender==='Male' ? 'male' : 'female';
    const gIcon = p.gender==='Male' ? 'fa-male' : 'fa-female';
    return `<div class="person-card" onclick="onCardClick(${p.id}, ${p.father_id||'null'}, event)">
      <div class="pcard-icon ${gCls}"><i class="fas ${gIcon}"></i></div>
      <div class="pcard-body">
        <div class="pcard-name">${esc(p.full_name)}${n2}${n3}</div>
        <div class="pcard-sub">${p.gender==='Male'?'ذكر':'أنثى'} · <span class="tag ${p.status==='Alive'?'alive':'dead'}">${p.status==='Alive'?'حي':'متوفى'}</span></div>
      </div>
    </div>`;
  }).join('');
}

function onCardClick(personId, fatherId, clickEvent) {
  // هايلايت الكارد
  document.querySelectorAll('.person-card').forEach(c => c.classList.remove('selected'));
  if (clickEvent) clickEvent.currentTarget.classList.add('selected');
  // scroll الجدول إلى الصف
  const row = document.querySelector(`#treeTableBody tr[data-pid="${personId}"]`);
  if (row) {
    row.scrollIntoView({ behavior:'smooth', block:'center' });
    row.style.outline = '2px solid var(--accent)';
    setTimeout(() => row.style.outline = '', 2000);
  }
  // عرض تفاصيل الأب
  if (fatherId) loadFatherDetail(fatherId);
}

// ══════════════════════════════════════════════════════════════
//  TREE TABLE (عمود 2)
// ══════════════════════════════════════════════════════════════
async function loadTree(gender) {
  const tbody = document.getElementById('treeTableBody');
  tbody.innerHTML = `<tr><td colspan="6" class="load-state"><span class="spinner"></span></td></tr>`;
  const data = await ajax({ ajax_action:'get_tree', gender });
  renderTree(data);
}

function renderTree(data) {
  const tbody = document.getElementById('treeTableBody');
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="load-state">لا توجد بيانات</td></tr>`;
    return;
  }
  let rows = '';
  let rowNum = 0;
  let lastGfFatherGroup = '';

  data.forEach(p => {
    // رأس مجموعة (الجد / الأب)
    const groupKey = (p.p_grandfather_name||'—') + '|' + (p.p_father_name||'—');
    if (groupKey !== lastGfFatherGroup) {
      lastGfFatherGroup = groupKey;
      const gfLabel = p.p_grandfather_name ? `<span style="color:var(--text3)">← ${esc(p.p_grandfather_name)}</span>` : '';
      const fLabel  = p.p_father_name ? esc(p.p_father_name) : '<span style="color:var(--text3)">بدون أب</span>';
      rows += `<tr class="group-header"><td colspan="6"><i class="fas fa-sitemap" style="color:var(--accent2);margin-left:5px;font-size:.7rem"></i>${fLabel} ${gfLabel}</td></tr>`;
    }
    rowNum++;
    const gCls  = p.gender==='Male' ? 'male' : 'female';
    const gIcon = p.gender==='Male' ? 'fa-male' : 'fa-female';
    const stTag = `<span class="tag ${p.status==='Alive'?'alive':'dead'}">${p.status==='Alive'?'حي':'متوفى'}</span>`;
    const motherCell = p.mother_name
      ? `<span class="mother-set" title="${esc(p.mother_name)}">${esc(p.mother_name)}</span>`
      : `<span class="mother-empty">غير محدد</span>`;

    rows += `<tr data-pid="${p.id}" data-fid="${p.father_id||''}">
      <td style="color:var(--text3);font-size:.72rem">${rowNum}</td>
      <td>
        <div style="display:flex;align-items:center;gap:6px">
          <i class="fas ${gIcon}" style="color:var(--${gCls});font-size:.75rem;flex-shrink:0"></i>
          <div>
            <div class="name3">${esc(p.full_name)}</div>
            ${p.p_father_name ? `<div class="name-father">${esc(p.p_father_name)}${p.p_grandfather_name ? ' ' + esc(p.p_grandfather_name) : ''}</div>` : ''}
          </div>
        </div>
      </td>
      <td><span style="color:var(--${gCls});font-size:.75rem">${p.gender==='Male'?'ذكر':'أنثى'}</span></td>
      <td>${stTag}</td>
      <td class="mother-cell" id="mcell-${p.id}">${motherCell}</td>
      <td>
        <button class="btn-set-mother" onclick="openMotherModal(${p.id}, ${p.father_id||'null'})">
          <i class="fas fa-edit"></i> تحديد
        </button>
      </td>
    </tr>`;
  });
  tbody.innerHTML = rows;
}

// ══════════════════════════════════════════════════════════════
//  FATHER DETAIL (عمود 3)
// ══════════════════════════════════════════════════════════════
async function loadFatherDetail(fatherId) {
  const empty   = document.getElementById('fatherDetailEmpty');
  const content = document.getElementById('fatherDetailContent');
  empty.style.display   = 'none';
  content.style.display = 'block';
  content.innerHTML = `<div class="load-state"><span class="spinner"></span> جاري التحميل…</div>`;

  const d = await ajax({ ajax_action:'get_father_detail', father_id: fatherId });
  if (!d.father) { content.innerHTML = `<div class="empty-state"><i class="fas fa-question"></i><span>لم يتم العثور على بيانات</span></div>`; return; }

  document.getElementById('fatherPanelTitle').textContent = d.father.full_name;

  // معلومات الأب
  let html = `<div class="detail-section">
    <div class="detail-title"><i class="fas fa-user-tie"></i> معلومات الأب</div>
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px">
      <div class="info-row"><span class="ik">الاسم</span><span class="iv">${esc(d.father.full_name)}</span></div>
      <div class="info-row"><span class="ik">الحالة</span><span class="iv"><span class="tag ${d.father.status==='Alive'?'alive':'dead'}">${d.father.status==='Alive'?'حي':'متوفى'}</span></span></div>
      ${d.father.grandfather_name ? `<div class="info-row"><span class="ik">الجد</span><span class="iv">${esc(d.father.grandfather_name)}</span></div>` : ''}
    </div>
  </div>`;

  // الزوجات
  html += `<div class="detail-section">
    <div class="detail-title"><i class="fas fa-female" style="color:var(--female)"></i> الزوجات (${d.wives.length})</div>`;
  if (!d.wives.length) {
    html += `<div style="color:var(--text3);font-size:.78rem;padding:5px">لا توجد زوجات مسجلة</div>`;
  } else {
    d.wives.forEach(w => {
      const n2 = w.w_father_name ? ` ${esc(w.w_father_name)}` : '';
      const n3 = w.w_grandfather_name ? ` ${esc(w.w_grandfather_name)}` : '';
      html += `<div class="wife-card">
        <i class="fas fa-female" style="color:var(--female);font-size:.9rem"></i>
        <div>
          <div class="wife-name">${esc(w.full_name)}</div>
          ${(n2||n3) ? `<div class="wife-sub">${n2}${n3}</div>` : ''}
        </div>
      </div>`;
    });
  }
  html += `</div>`;

  // الأبناء
  html += `<div class="detail-section">
    <div class="detail-title"><i class="fas fa-users"></i> الأبناء (${d.children.length})</div>`;
  if (!d.children.length) {
    html += `<div style="color:var(--text3);font-size:.78rem;padding:5px">لا يوجد أبناء مسجلون</div>`;
  } else {
    d.children.forEach(c => {
      const gCls  = c.gender==='Male' ? 'male' : 'female';
      const gIcon = c.gender==='Male' ? 'fa-male' : 'fa-female';
      const mName = c.mother_name
        ? `<div class="child-mother"><i class="fas fa-female" style="font-size:.65rem"></i> ${esc(c.mother_name)}</div>`
        : `<div class="child-mother" style="color:var(--amber);font-size:.7rem">⚠ الأم غير محددة</div>`;
      html += `<div class="child-row" onclick="openMotherModal(${c.id}, ${fatherId})">
        <i class="fas ${gIcon}" style="color:var(--${gCls});font-size:.8rem;flex-shrink:0"></i>
        <div style="flex:1;overflow:hidden">
          <div class="child-name ${gCls}">${esc(c.full_name)}</div>
          ${mName}
        </div>
        <button class="btn-set-mother" style="font-size:.68rem;padding:2px 6px" title="تحديد الأم">
          <i class="fas fa-edit"></i>
        </button>
      </div>`;
    });
  }
  html += `</div>`;

  content.innerHTML = html;
}

// ══════════════════════════════════════════════════════════════
//  MOTHER MODAL
// ══════════════════════════════════════════════════════════════
let modalFatherId  = null;
let modalPersonRow = null;

async function openMotherModal(personId, fatherId) {
  currentPersonId  = personId;
  selectedMotherId = null;
  modalFatherId    = fatherId;

  document.getElementById('motherModal').classList.add('open');
  document.getElementById('modalMsg').style.display = 'none';
  document.getElementById('modalSearch').value = '';
  document.getElementById('modalOtherResults').innerHTML = '';

  // معلومات الشخص
  const row = document.querySelector(`#treeTableBody tr[data-pid="${personId}"]`);
  let personName = personId;
  if (row) {
    const nameEl = row.querySelector('.name3');
    personName = nameEl ? nameEl.textContent : personId;
  }
  document.getElementById('modalPersonInfo').innerHTML = `
    <div class="person-target-info">
      <div class="person-target-name"><i class="fas fa-user" style="color:var(--accent2);margin-left:5px"></i>${esc(String(personName))}</div>
      <div class="person-target-sub">تحديد الأم لهذا الشخص</div>
    </div>`;

  // زوجات الأب
  const wivesEl = document.getElementById('modalWivesList');
  if (fatherId) {
    wivesEl.innerHTML = `<span class="wives-list-label"><i class="fas fa-female"></i> زوجات الأب (اختر الأم):</span>
      <div class="load-state" style="height:80px"><span class="spinner"></span></div>`;
    const wives = await ajax({ ajax_action:'get_wives', father_id: fatherId });
    if (!wives.length) {
      wivesEl.innerHTML = `<span class="wives-list-label"><i class="fas fa-female"></i> زوجات الأب:</span>
        <div style="color:var(--text3);font-size:.78rem;padding:8px">لا توجد زوجات مسجلة لهذا الأب</div>`;
    } else {
      let html = `<span class="wives-list-label"><i class="fas fa-female"></i> زوجات الأب (${wives.length}):</span>`;
      wives.forEach(w => {
        const n2 = w.w_father_name ? esc(w.w_father_name) : '';
        const n3 = w.w_grandfather_name ? esc(w.w_grandfather_name) : '';
        html += `<div class="wife-option" id="wopt-${w.id}" onclick="selectMother(${w.id}, this)">
          <input type="radio" name="mother_choice" value="${w.id}">
          <div>
            <div class="wife-option-name">${esc(w.full_name)}</div>
            ${(n2||n3) ? `<div class="wife-option-sub">${n2} ${n3}</div>` : ''}
          </div>
        </div>`;
      });
      wivesEl.innerHTML = html;
    }
  } else {
    wivesEl.innerHTML = `<div style="color:var(--text3);font-size:.78rem;padding:5px">هذا الشخص بدون أب مسجل</div>`;
  }
}

function selectMother(id, el) {
  selectedMotherId = id;
  document.querySelectorAll('.wife-option').forEach(o => o.classList.remove('selected'));
  document.querySelectorAll('.other-result').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input[type=radio]').checked = true;
}

function selectOtherMother(id, el) {
  selectedMotherId = id;
  document.querySelectorAll('.wife-option').forEach(o => o.classList.remove('selected'));
  document.querySelectorAll('.other-result').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
}

// بحث في كل الإناث
document.getElementById('modalSearch').addEventListener('input', function() {
  const q   = this.value.trim().toLowerCase();
  const el  = document.getElementById('modalOtherResults');
  if (!q) { el.innerHTML = ''; return; }
  const res = allFemales.filter(f =>
    f.full_name.toLowerCase().includes(q) ||
    (f.p_father_name && f.p_father_name.toLowerCase().includes(q))
  ).slice(0, 30);
  if (!res.length) { el.innerHTML = `<div style="color:var(--text3);font-size:.78rem;padding:5px">لا توجد نتائج</div>`; return; }
  el.innerHTML = res.map(f => {
    const n2 = f.p_father_name ? ` ← ${esc(f.p_father_name)}` : '';
    return `<div class="other-result" onclick="selectOtherMother(${f.id}, this)">
      <i class="fas fa-female" style="color:var(--female);font-size:.8rem"></i>
      <span>${esc(f.full_name)}${n2}</span>
    </div>`;
  }).join('');
});

async function saveMotherSelection() {
  if (!selectedMotherId) {
    showModalMsg('يرجى اختيار الأم أولاً', 'err'); return;
  }
  const r = await ajax({ ajax_action:'set_mother', person_id:currentPersonId, mother_id:selectedMotherId });
  if (r.ok) {
    showModalMsg('تم حفظ الأم بنجاح ✔', 'ok');
    // تحديث الخلية في الجدول
    const nameEl = allFemales.find(f => f.id == selectedMotherId);
    if (nameEl) {
      const mcell = document.getElementById(`mcell-${currentPersonId}`);
      if (mcell) mcell.innerHTML = `<span class="mother-set">${esc(nameEl.full_name)}</span>`;
    }
    setTimeout(closeMotherModal, 1200);
  } else {
    showModalMsg('خطأ في الحفظ: ' + (r.error||''), 'err');
  }
}

function showModalMsg(txt, type) {
  const el = document.getElementById('modalMsg');
  el.textContent = txt; el.className = `msg-inline ${type}`; el.style.display = 'block';
}

function closeMotherModal() {
  document.getElementById('motherModal').classList.remove('open');
  currentPersonId = null; selectedMotherId = null;
}

// إغلاق المودال عند النقر خارجه
document.getElementById('motherModal').addEventListener('click', function(e) {
  if (e.target === this) closeMotherModal();
});

// ══════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>