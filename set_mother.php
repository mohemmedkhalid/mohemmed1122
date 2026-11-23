<?php
require_once 'header.php';

if (!isAdmin()) {
    $_SESSION['message'] = 'هذه الصفحة للمشرف فقط.';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // تحميل الأشخاص الذين بدون أم (أو كلهم)
    if ($_POST['ajax_action'] === 'get_persons') {
        $filter = $_POST['filter'] ?? 'no_mother'; // no_mother | all
        $gw = $filter === 'no_mother' ? "WHERE p.mother_id IS NULL" : "WHERE 1=1";
        $gender = $_POST['gender'] ?? '';
        if ($gender) $gw .= " AND p.gender='$gender'";

        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.full_name_en, p.gender, p.status,
                   p.father_id, p.mother_id,
                   f.full_name  AS father_name, f.full_name_en AS father_name_en,
                   gf.full_name AS grandfather_name, gf.full_name_en AS grandfather_name_en,
                   m.full_name  AS mother_name, m.full_name_en AS mother_name_en
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id = f.id
            LEFT JOIN people2 gf ON f.father_id = gf.id
            LEFT JOIN people2 m  ON p.mother_id = m.id
            $gw
            ORDER BY gf.full_name, f.full_name, p.full_name");
        $out = [];
        while ($r = $rows->fetch_assoc()) {
            $r['full_name'] = get_person_name($r);
            if(isset($r['father_name'])) $r['father_name'] = get_person_name(['full_name'=>$r['father_name'], 'full_name_en'=>$r['father_name_en']]);
            if(isset($r['grandfather_name'])) $r['grandfather_name'] = get_person_name(['full_name'=>$r['grandfather_name'], 'full_name_en'=>$r['grandfather_name_en']]);
            if(isset($r['mother_name'])) $r['mother_name'] = get_person_name(['full_name'=>$r['mother_name'], 'full_name_en'=>$r['mother_name_en']]);
            $out[] = $r;
        }
        echo json_encode($out); exit;
    }

    // زوجات أب معين
    if ($_POST['ajax_action'] === 'get_wives') {
        $fid  = (int)$_POST['father_id'];
        $rows = $mysqli->query("
            SELECT w.id, w.full_name, w.full_name_en,
                   wf.full_name AS w_father, wf.full_name_en AS w_father_en, wgf.full_name AS w_grandfather, wgf.full_name_en AS w_grandfather_en
            FROM people2 w
            INNER JOIN marriages m ON w.id = m.wife_id AND m.husband_id = $fid
            LEFT JOIN people2 wf  ON w.father_id  = wf.id
            LEFT JOIN people2 wgf ON wf.father_id = wgf.id
            ORDER BY w.full_name");
        $out = [];
        while ($r = $rows->fetch_assoc()) {
            $r['full_name'] = get_person_name($r);
            if(isset($r['w_father'])) $r['w_father'] = get_person_name(['full_name'=>$r['w_father'], 'full_name_en'=>$r['w_father_en']]);
            if(isset($r['w_grandfather'])) $r['w_grandfather'] = get_person_name(['full_name'=>$r['w_grandfather'], 'full_name_en'=>$r['w_grandfather_en']]);
            $out[] = $r;
        }
        echo json_encode($out); exit;
    }

    // كل الإناث للبحث
    if ($_POST['ajax_action'] === 'get_females') {
        $q  = '%' . $mysqli->real_escape_string(trim($_POST['q'] ?? '')) . '%';
        $rows = $mysqli->query("
            SELECT p.id, p.full_name, p.full_name_en,
                   f.full_name AS p_father, f.full_name_en AS p_father_en, gf.full_name AS p_grandfather, gf.full_name_en AS p_grandfather_en
            FROM people2 p
            LEFT JOIN people2 f  ON p.father_id = f.id
            LEFT JOIN people2 gf ON f.father_id = gf.id
            WHERE p.gender='Female'
              AND (p.full_name LIKE '$q' OR f.full_name LIKE '$q')
            ORDER BY p.full_name LIMIT 50");
        $out = [];
        while ($r = $rows->fetch_assoc()) {
            $r['full_name'] = get_person_name($r);
            if(isset($r['p_father'])) $r['p_father'] = get_person_name(['full_name'=>$r['p_father'], 'full_name_en'=>$r['p_father_en']]);
            if(isset($r['p_grandfather'])) $r['p_grandfather'] = get_person_name(['full_name'=>$r['p_grandfather'], 'full_name_en'=>$r['p_grandfather_en']]);
            $out[] = $r;
        }
        echo json_encode($out); exit;
    }

    // حفظ الأم
    if ($_POST['ajax_action'] === 'set_mother') {
        $pid = (int)$_POST['person_id'];
        $mid = (int)$_POST['mother_id'];
        if (!$pid || !$mid) { echo json_encode(['ok'=>false,'error'=>'بيانات ناقصة']); exit; }
        $ok  = $mysqli->query("UPDATE people2 SET mother_id=$mid WHERE id=$pid");
        // احضر اسم الأم
        $mrow = $mysqli->query("SELECT full_name, full_name_en FROM people2 WHERE id=$mid")->fetch_assoc();
        echo json_encode(['ok'=>$ok, 'mother_name'=>get_person_name($mrow), 'error'=>$mysqli->error]); exit;
    }

    // إحصاء
    if ($_POST['ajax_action'] === 'stats') {
        $total    = $mysqli->query("SELECT COUNT(*) c FROM people2")->fetch_assoc()['c'];
        $no_mom   = $mysqli->query("SELECT COUNT(*) c FROM people2 WHERE mother_id IS NULL")->fetch_assoc()['c'];
        $has_mom  = $total - $no_mom;
        echo json_encode(['total'=>$total,'no_mother'=>$no_mom,'has_mother'=>$has_mom]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'unknown']); exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تحديد الأم - شجرة العائلة</title>
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
}
html,body{height:100%;font-family:'Cairo',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
body{display:flex;flex-direction:column;overflow:hidden}

/* ── TOPBAR ── */
.topbar{height:52px;background:var(--surface);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 18px;gap:12px;flex-shrink:0;z-index:20}
.topbar-logo{font-weight:900;font-size:1.05rem;color:var(--female)}
.topbar-logo span{color:var(--text3)}
.topbar-nav{display:flex;gap:7px;margin-right:auto}
.tbtn{background:transparent;border:1px solid var(--border);color:var(--text2);padding:5px 12px;
  border-radius:7px;cursor:pointer;font-family:inherit;font-size:.8rem;transition:.15s;text-decoration:none;
  display:inline-flex;align-items:center;gap:5px}
.tbtn:hover,.tbtn.active{border-color:var(--accent);color:var(--accent2);background:rgba(59,130,246,.08)}

/* ── STATS BAR ── */
.stats-bar{background:var(--surface);border-bottom:1px solid var(--border);padding:10px 18px;
  display:flex;align-items:center;gap:16px;flex-shrink:0}
.stat-item{display:flex;align-items:center;gap:7px;font-size:.82rem}
.stat-num{font-weight:900;font-size:1.1rem}
.stat-num.blue{color:var(--accent2)}
.stat-num.green{color:var(--green)}
.stat-num.amber{color:var(--amber)}
.stat-label{color:var(--text3)}
.progress-wrap{flex:1;background:var(--surface2);border-radius:20px;height:6px;overflow:hidden;max-width:200px}
.progress-bar{height:100%;background:linear-gradient(90deg,var(--green),var(--accent));border-radius:20px;transition:.4s}

/* ── MAIN LAYOUT ── */
.workspace{display:grid;grid-template-columns:360px 1fr;height:calc(100vh - 100px);overflow:hidden}

/* ── PANEL ── */
.panel{border-left:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.panel:first-child{border-left:none}
.panel-head{padding:12px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;
  gap:9px;flex-shrink:0;background:var(--surface)}
.panel-head h3{font-size:.9rem;font-weight:700;flex:1}
.badge{background:rgba(59,130,246,.15);color:var(--accent2);font-size:.72rem;font-weight:700;
  padding:2px 8px;border-radius:20px;border:1px solid rgba(59,130,246,.2)}
.badge.amber{background:rgba(245,158,11,.12);color:var(--amber);border-color:rgba(245,158,11,.2)}
.panel-body{flex:1;overflow-y:auto;padding:12px}
.panel-body::-webkit-scrollbar{width:5px}
.panel-body::-webkit-scrollbar-thumb{background:var(--border2);border-radius:5px}

/* ── FILTERS ── */
.filter-row{display:flex;gap:7px;margin-bottom:9px;flex-wrap:wrap}
.filter-btn{padding:5px 12px;border-radius:7px;border:1px solid var(--border);
  background:none;color:var(--text2);font-family:inherit;font-size:.76rem;font-weight:700;cursor:pointer;transition:.13s}
.filter-btn.on{border-color:var(--accent);background:rgba(59,130,246,.12);color:var(--accent2)}
.filter-btn.amber-on{border-color:var(--amber);background:rgba(245,158,11,.1);color:var(--amber)}

.search-wrap{display:flex;align-items:center;gap:8px;background:var(--surface2);
  border:1px solid var(--border);border-radius:8px;padding:7px 11px;margin-bottom:10px}
.search-wrap i{color:var(--text3);font-size:.82rem;flex-shrink:0}
.search-input{background:none;border:none;outline:none;color:var(--text);font-family:inherit;font-size:.84rem;width:100%}
.search-input::placeholder{color:var(--text3)}

/* ── PERSON CARDS (لیست يسار) ── */
.person-card{padding:10px 12px;border-radius:9px;border:1px solid var(--border);margin-bottom:5px;
  cursor:pointer;transition:.14s;background:var(--surface2);display:flex;align-items:center;gap:10px}
.person-card:hover{border-color:var(--border2);background:rgba(255,255,255,.02)}
.person-card.selected{border-color:var(--accent);background:rgba(59,130,246,.08)}
.person-card.done{border-color:rgba(34,197,94,.25);background:rgba(34,197,94,.04)}
.pcard-icon{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.pcard-icon.male{background:rgba(96,165,250,.15);color:var(--male)}
.pcard-icon.female{background:rgba(244,114,182,.15);color:var(--female)}
.pcard-body{flex:1;overflow:hidden}
.pcard-name{font-size:.84rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pcard-sub{font-size:.71rem;color:var(--text3);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pcard-mother{font-size:.71rem;margin-top:2px}
.pcard-mother.set{color:#86efac}
.pcard-mother.unset{color:var(--amber)}

/* ── RIGHT PANEL: اختيار الأم ── */
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;
  height:250px;color:var(--text3);gap:12px;font-size:.85rem;text-align:center}
.empty-state i{font-size:2.5rem;opacity:.25}

.target-box{background:var(--surface2);border:1px solid var(--accent);border-radius:10px;
  padding:13px 14px;margin-bottom:16px}
.target-label{font-size:.72rem;font-weight:700;color:var(--text3);margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.target-name{font-size:1.05rem;font-weight:900;color:var(--accent2)}
.target-sub{font-size:.75rem;color:var(--text3);margin-top:3px}

.section-label{font-size:.74rem;font-weight:700;color:var(--female);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.section-label i{font-size:.8rem}

.wife-option{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;
  border:1px solid var(--border);background:var(--surface2);margin-bottom:6px;cursor:pointer;transition:.14s}
.wife-option:hover{border-color:var(--female);background:rgba(244,114,182,.06)}
.wife-option.selected{border-color:var(--female);background:rgba(244,114,182,.12)}
.wife-option .circle{width:30px;height:30px;border-radius:50%;background:rgba(244,114,182,.15);
  color:var(--female);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0}
.wife-option.selected .circle{background:var(--female);color:#fff}
.wife-option-name{font-size:.84rem;font-weight:700;color:var(--text)}
.wife-option-sub{font-size:.71rem;color:var(--text3)}

.divider{border:none;border-top:1px dashed var(--border);margin:14px 0}

.other-result{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:7px;
  border:1px solid transparent;cursor:pointer;transition:.12s;font-size:.8rem}
.other-result:hover{border-color:var(--border);background:var(--surface2)}
.other-result.selected{border-color:var(--female);background:rgba(244,114,182,.1);color:var(--female)}
.other-result i{font-size:.8rem;color:var(--female);flex-shrink:0}

.save-area{padding:14px;border-top:1px solid var(--border);background:var(--surface);flex-shrink:0}
.btn-save{width:100%;padding:12px;border-radius:10px;border:none;background:var(--green);
  color:#fff;font-family:inherit;font-size:.95rem;font-weight:900;cursor:pointer;transition:.15s;
  display:flex;align-items:center;justify-content:center;gap:8px}
.btn-save:hover{background:#16a34a;transform:translateY(-1px)}
.btn-save:disabled{background:var(--surface2);color:var(--text3);cursor:not-allowed;transform:none}

.msg{padding:8px 12px;border-radius:8px;font-size:.8rem;font-weight:700;display:none;margin-bottom:10px}
.msg.ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#86efac;display:block}
.msg.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;display:block}

.spinner{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);
  border-radius:50%;animation:spin .7s linear infinite;display:inline-block;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}

.tag{display:inline-block;padding:1px 7px;border-radius:5px;font-size:.68rem;font-weight:700}
.tag.alive{background:rgba(34,197,94,.12);color:#86efac}
.tag.dead{background:rgba(107,114,128,.12);color:#9ca3af}

.no-results{color:var(--text3);font-size:.8rem;text-align:center;padding:16px}
.load-state{text-align:center;padding:24px;color:var(--text3);font-size:.83rem}

/* ── RIGHT PANEL LAYOUT ── */
.right-panel{display:flex;flex-direction:column;height:100%}
.right-scroll{flex:1;overflow-y:auto;padding:14px}
.right-scroll::-webkit-scrollbar{width:5px}
.right-scroll::-webkit-scrollbar-thumb{background:var(--border2);border-radius:5px}

@media(max-width:800px){
  .workspace{grid-template-columns:1fr;height:auto;overflow:visible}
  .panel{border-left:none;border-top:1px solid var(--border)}
  body{overflow:auto}
}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <span class="topbar-logo"><i class="fas fa-female" style="margin-left:5px"></i>تحديد<span>الأم</span></span>
  <div class="topbar-nav">
    <a href="index.php" class="tbtn"><i class="fas fa-home"></i> الرئيسية</a>
    <a href="add_person.php" class="tbtn"><i class="fas fa-user-plus"></i> إضافة</a>
    <a href="family_list_tree.php" class="tbtn"><i class="fas fa-list"></i> القائمة</a>
    <a href="tree.php" class="tbtn"><i class="fas fa-sitemap"></i> الشجرة</a>
  </div>
</div>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stat-item">
    <span class="stat-num blue" id="statTotal">—</span>
    <span class="stat-label">إجمالي الأشخاص</span>
  </div>
  <div class="stat-item">
    <span class="stat-num green" id="statHas">—</span>
    <span class="stat-label">لديهم أم محددة</span>
  </div>
  <div class="stat-item">
    <span class="stat-num amber" id="statNo">—</span>
    <span class="stat-label">بدون أم</span>
  </div>
  <div class="progress-wrap">
    <div class="progress-bar" id="progressBar" style="width:0%"></div>
  </div>
  <span style="font-size:.75rem;color:var(--text3)" id="progressPct">0%</span>
</div>

<div class="workspace">

  <!-- ══ عمود 1: قائمة الأشخاص ══ -->
  <div class="panel">
    <div class="panel-head">
      <i class="fas fa-users" style="color:var(--accent2)"></i>
      <h3>الأشخاص</h3>
      <span class="badge amber" id="pendingBadge">—</span>
    </div>
    <div class="panel-body">
      <!-- فلاتر -->
      <div class="filter-row">
        <button class="filter-btn amber-on" data-filter="no_mother" onclick="setFilter('no_mother',this)">⚠ بدون أم</button>
        <button class="filter-btn" data-filter="all" onclick="setFilter('all',this)">الكل</button>
      </div>
      <div class="filter-row">
        <button class="filter-btn on" data-gender="" onclick="setGender('',this)">الكل</button>
        <button class="filter-btn" data-gender="Male" onclick="setGender('Male',this)">ذكور</button>
        <button class="filter-btn" data-gender="Female" onclick="setGender('Female',this)">إناث</button>
      </div>
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" class="search-input" id="listSearch" placeholder="ابحث بالاسم أو اسم الأب…">
      </div>
      <div id="personsList"><div class="load-state"><span class="spinner"></span></div></div>
    </div>
  </div>

  <!-- ══ عمود 2: اختيار الأم ══ -->
  <div class="panel">
    <div class="panel-head">
      <i class="fas fa-female" style="color:var(--female)"></i>
      <h3>اختيار الأم</h3>
      <span class="badge" id="selectedBadge" style="display:none">تم الاختيار ✔</span>
    </div>
    <div class="right-panel">
      <div class="right-scroll" id="rightScroll">
        <div class="empty-state" id="emptyState">
          <i class="fas fa-hand-pointer"></i>
          <span>اختر شخصاً من القائمة على اليمين<br>لتحديد أمه</span>
        </div>
        <div id="selectionArea" style="display:none">
          <div class="msg" id="saveMsg"></div>
          <div class="target-box" id="targetBox"></div>

          <div class="section-label"><i class="fas fa-female"></i> زوجات الأب (اختر الأم)</div>
          <div id="wivesList"><div class="load-state"><span class="spinner"></span></div></div>

          <hr class="divider">
          <div class="section-label"><i class="fas fa-search"></i> بحث في كل النساء</div>
          <div class="search-wrap" style="margin-bottom:8px">
            <i class="fas fa-search"></i>
            <input type="text" class="search-input" id="femaleSearch" placeholder="ابحث باسم الأم…">
          </div>
          <div id="femaleResults"></div>
        </div>
      </div>
      <div class="save-area">
        <div id="saveMsg2" class="msg"></div>
        <button class="btn-save" id="saveBtn" disabled onclick="saveMother()">
          <i class="fas fa-save"></i> حفظ الأم
        </button>
      </div>
    </div>
  </div>

</div>

<script>
// ══════════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════════
let currentFilter   = 'no_mother';
let currentGender   = '';
let currentSearch   = '';
let allPersons      = [];      // كل الأشخاص المحملة
let selectedPerson  = null;    // الشخص المختار
let selectedMother  = null;    // الأم المختارة { id, name }
let searchDebounce  = null;
let femaleDebounce  = null;

// ══════════════════════════════════════════════════════════════
//  INIT
// ══════════════════════════════════════════════════════════════
(async function init() {
  await Promise.all([loadStats(), loadPersons()]);
})();

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
//  STATS
// ══════════════════════════════════════════════════════════════
async function loadStats() {
  const s = await ajax({ ajax_action:'stats' });
  document.getElementById('statTotal').textContent = s.total;
  document.getElementById('statHas').textContent   = s.has_mother;
  document.getElementById('statNo').textContent    = s.no_mother;
  const pct = s.total ? Math.round(s.has_mother / s.total * 100) : 0;
  document.getElementById('progressBar').style.width = pct + '%';
  document.getElementById('progressPct').textContent = pct + '%';
}

// ══════════════════════════════════════════════════════════════
//  PERSONS LIST
// ══════════════════════════════════════════════════════════════
async function loadPersons() {
  document.getElementById('personsList').innerHTML = `<div class="load-state"><span class="spinner"></span></div>`;
  allPersons = await ajax({ ajax_action:'get_persons', filter:currentFilter, gender:currentGender });
  renderPersons();
}

function renderPersons() {
  const el  = document.getElementById('personsList');
  const q   = currentSearch.toLowerCase();
  const filtered = q
    ? allPersons.filter(p =>
        p.full_name.toLowerCase().includes(q) ||
        (p.father_name && p.father_name.toLowerCase().includes(q)) ||
        (p.grandfather_name && p.grandfather_name.toLowerCase().includes(q))
      )
    : allPersons;

  document.getElementById('pendingBadge').textContent = filtered.length;

  if (!filtered.length) {
    el.innerHTML = `<div class="no-results"><i class="fas fa-check-circle" style="color:var(--green);font-size:1.5rem;display:block;margin-bottom:8px"></i>${currentFilter==='no_mother'?'ممتاز! لا يوجد أشخاص بدون أم':'لا توجد نتائج'}</div>`;
    return;
  }

  el.innerHTML = filtered.map(p => {
    const gCls  = p.gender==='Male' ? 'male' : 'female';
    const gIcon = p.gender==='Male' ? 'fa-male' : 'fa-female';
    const n2 = p.father_name      ? ` ← ${esc(p.father_name)}`      : '';
    const n3 = p.grandfather_name ? ` ← ${esc(p.grandfather_name)}` : '';
    const motherInfo = p.mother_name
      ? `<div class="pcard-mother set"><i class="fas fa-check-circle" style="font-size:.7rem"></i> ${esc(p.mother_name)}</div>`
      : `<div class="pcard-mother unset"><i class="fas fa-exclamation-circle" style="font-size:.7rem"></i> الأم غير محددة</div>`;
    const isSelected = selectedPerson && selectedPerson.id == p.id;
    const isDone = p.mother_id;
    return `<div class="person-card ${isSelected?'selected':''} ${isDone&&!isSelected?'done':''}"
      id="pcard-${p.id}" onclick="selectPerson(${p.id})">
      <div class="pcard-icon ${gCls}"><i class="fas ${gIcon}"></i></div>
      <div class="pcard-body">
        <div class="pcard-name">${esc(p.full_name)}</div>
        <div class="pcard-sub">${n2}${n3} · <span class="tag ${p.status==='Alive'?'alive':'dead'}">${p.status==='Alive'?'حي':'متوفى'}</span></div>
        ${motherInfo}
      </div>
    </div>`;
  }).join('');
}

// ══════════════════════════════════════════════════════════════
//  SELECT PERSON
// ══════════════════════════════════════════════════════════════
async function selectPerson(pid) {
  selectedPerson = allPersons.find(p => p.id == pid);
  selectedMother = null;
  updateSaveBtn();

  // هايلايت
  document.querySelectorAll('.person-card').forEach(c => c.classList.remove('selected'));
  const card = document.getElementById(`pcard-${pid}`);
  if (card) { card.classList.add('selected'); card.scrollIntoView({ block:'nearest' }); }

  // إخفاء empty
  document.getElementById('emptyState').style.display    = 'none';
  document.getElementById('selectionArea').style.display = 'block';
  document.getElementById('saveMsg').style.display = 'none';
  document.getElementById('saveMsg2').style.display = 'none';
  document.getElementById('selectedBadge').style.display = 'none';
  document.getElementById('femaleSearch').value = '';
  document.getElementById('femaleResults').innerHTML = '';

  // معلومات الشخص المختار
  const n2 = selectedPerson.father_name      ? ` ← ${esc(selectedPerson.father_name)}`      : '';
  const n3 = selectedPerson.grandfather_name ? ` ← ${esc(selectedPerson.grandfather_name)}` : '';
  const gLabel = selectedPerson.gender === 'Male' ? 'ذكر' : 'أنثى';
  document.getElementById('targetBox').innerHTML = `
    <div class="target-label">الشخص المختار لتحديد أمه</div>
    <div class="target-name">${esc(selectedPerson.full_name)}</div>
    <div class="target-sub">${gLabel}${n2}${n3}</div>
    ${selectedPerson.mother_name ? `<div style="margin-top:5px;font-size:.74rem;color:#86efac"><i class="fas fa-check-circle"></i> الأم الحالية: ${esc(selectedPerson.mother_name)}</div>` : ''}`;

  // تحميل زوجات الأب
  const wivesEl = document.getElementById('wivesList');
  if (selectedPerson.father_id) {
    wivesEl.innerHTML = `<div class="load-state"><span class="spinner"></span></div>`;
    const wives = await ajax({ ajax_action:'get_wives', father_id: selectedPerson.father_id });
    if (!wives.length) {
      wivesEl.innerHTML = `<div style="color:var(--text3);font-size:.78rem;padding:8px 0">لا توجد زوجات مسجلة لأب هذا الشخص</div>`;
    } else {
      wivesEl.innerHTML = wives.map(w => {
        const s2 = w.w_father ? esc(w.w_father) : '';
        const s3 = w.w_grandfather ? esc(w.w_grandfather) : '';
        const isCurrentMother = selectedPerson.mother_id == w.id;
        return `<div class="wife-option ${isCurrentMother?'selected':''}" id="wopt-${w.id}" onclick="chooseWife(${w.id},'${escJs(w.full_name)}')">
          <div class="circle"><i class="fas fa-female"></i></div>
          <div style="flex:1;overflow:hidden">
            <div class="wife-option-name">${esc(w.full_name)}</div>
            ${(s2||s3) ? `<div class="wife-option-sub">${s2} ${s3}</div>` : ''}
          </div>
          ${isCurrentMother ? `<i class="fas fa-check-circle" style="color:var(--green);flex-shrink:0"></i>` : ''}
        </div>`;
      }).join('');
      // إذا كانت الأم الحالية في القائمة، اخترها تلقائياً
      if (selectedPerson.mother_id) {
        const w = wives.find(x => x.id == selectedPerson.mother_id);
        if (w) { selectedMother = { id:w.id, name:w.full_name }; updateSaveBtn(); }
      }
    }
  } else {
    wivesEl.innerHTML = `<div style="color:var(--text3);font-size:.78rem;padding:8px 0">هذا الشخص ليس له أب مسجل</div>`;
  }

  // scroll يمين للأعلى
  document.getElementById('rightScroll').scrollTop = 0;
}

// ══════════════════════════════════════════════════════════════
//  CHOOSE MOTHER
// ══════════════════════════════════════════════════════════════
function chooseWife(id, name) {
  selectedMother = { id, name };
  clearFemaleSelection();
  document.querySelectorAll('.wife-option').forEach(o => o.classList.remove('selected'));
  const el = document.getElementById(`wopt-${id}`);
  if (el) {
    el.classList.add('selected');
    el.querySelector('.circle').innerHTML = '<i class="fas fa-check"></i>';
  }
  document.getElementById('selectedBadge').style.display = 'inline-block';
  updateSaveBtn();
}

function chooseOther(id, name, el) {
  selectedMother = { id, name };
  document.querySelectorAll('.wife-option').forEach(o => o.classList.remove('selected'));
  document.querySelectorAll('.other-result').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('selectedBadge').style.display = 'inline-block';
  updateSaveBtn();
}

function clearFemaleSelection() {
  document.querySelectorAll('.other-result').forEach(o => o.classList.remove('selected'));
}

// بحث في كل الإناث
document.getElementById('femaleSearch').addEventListener('input', function() {
  clearTimeout(femaleDebounce);
  femaleDebounce = setTimeout(() => searchFemales(this.value), 280);
});

async function searchFemales(q) {
  const el = document.getElementById('femaleResults');
  if (!q.trim()) { el.innerHTML = ''; return; }
  el.innerHTML = `<div class="load-state"><span class="spinner"></span></div>`;
  const res = await ajax({ ajax_action:'get_females', q: q.trim() });
  if (!res.length) { el.innerHTML = `<div class="no-results">لا توجد نتائج</div>`; return; }
  el.innerHTML = res.map(f => {
    const n2 = f.p_father ? ` ← ${esc(f.p_father)}` : '';
    const isSelected = selectedMother && selectedMother.id == f.id;
    return `<div class="other-result ${isSelected?'selected':''}" onclick="chooseOther(${f.id},'${escJs(f.full_name)}',this)">
      <i class="fas fa-female"></i>
      <span>${esc(f.full_name)}${n2}</span>
    </div>`;
  }).join('');
}

// ══════════════════════════════════════════════════════════════
//  SAVE
// ══════════════════════════════════════════════════════════════
function updateSaveBtn() {
  const btn = document.getElementById('saveBtn');
  btn.disabled = !(selectedPerson && selectedMother);
}

async function saveMother() {
  if (!selectedPerson || !selectedMother) return;
  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.innerHTML = `<span class="spinner"></span> جاري الحفظ…`;

  const r = await ajax({
    ajax_action: 'set_mother',
    person_id:   selectedPerson.id,
    mother_id:   selectedMother.id,
  });

  if (r.ok) {
    showMsg('saveMsg2', `✔ تم تعيين "${esc(r.mother_name)}" أماً لـ "${esc(selectedPerson.full_name)}"`, 'ok');
    // تحديث الكارد في القائمة
    selectedPerson.mother_id   = selectedMother.id;
    selectedPerson.mother_name = r.mother_name;
    const idx = allPersons.findIndex(p => p.id == selectedPerson.id);
    if (idx !== -1) { allPersons[idx].mother_id = selectedMother.id; allPersons[idx].mother_name = r.mother_name; }
    renderPersons();
    // تحديث الكارد المختار
    highlightCurrentCard();
    // تحديث الإحصاء
    loadStats();
    btn.innerHTML = `<i class="fas fa-save"></i> حفظ الأم`;
    // إذا كان الفلتر "بدون أم" يزيل الكارد من القائمة تلقائياً
  } else {
    showMsg('saveMsg2', `✗ خطأ في الحفظ: ${r.error||''}`, 'err');
    btn.innerHTML = `<i class="fas fa-save"></i> حفظ الأم`;
    btn.disabled  = false;
  }
}

function highlightCurrentCard() {
  if (!selectedPerson) return;
  document.querySelectorAll('.person-card').forEach(c => c.classList.remove('selected'));
  const card = document.getElementById(`pcard-${selectedPerson.id}`);
  if (card) card.classList.add('selected');
}

function showMsg(id, txt, type) {
  const el = document.getElementById(id);
  el.textContent = txt; el.className = `msg ${type}`;
  setTimeout(() => el.className = 'msg', 4000);
}

// ══════════════════════════════════════════════════════════════
//  FILTERS
// ══════════════════════════════════════════════════════════════
function setFilter(val, btn) {
  currentFilter = val;
  document.querySelectorAll('[data-filter]').forEach(b => { b.classList.remove('on','amber-on'); });
  btn.classList.add(val==='no_mother' ? 'amber-on' : 'on');
  loadPersons();
}

function setGender(val, btn) {
  currentGender = val;
  document.querySelectorAll('[data-gender]').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  loadPersons();
}

// بحث في القائمة
document.getElementById('listSearch').addEventListener('input', function() {
  clearTimeout(searchDebounce);
  currentSearch = this.value;
  searchDebounce = setTimeout(renderPersons, 220);
});

// ══════════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════════
function esc(s)   { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(s) { return String(s).replace(/'/g,"\\'").replace(/\\/g,'\\\\'); }
</script>
</body>
</html>