<?php
require_once 'header.php';

$lang  = get_lang();
$isEn  = ($lang === 'en');
$dir   = $isEn ? 'ltr' : 'rtl';

/* ════════════════════════════════════════════════
   AJAX — return JSON for search/filter
════════════════════════════════════════════════ */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $gender_filter = '';
    if (!empty($_GET['gender']) && in_array($_GET['gender'], ['Male','Female'])) {
        $gender_filter = "WHERE gender = '" . $mysqli->real_escape_string($_GET['gender']) . "'";
    }

    $q = "
        SELECT
            SUBSTRING_INDEX(full_name, ' ', 1)         AS first_name,
            COUNT(*)                                    AS total_count,
            SUM(gender = 'Male')                        AS male_count,
            SUM(gender = 'Female')                      AS female_count,
            SUM(status = 'Alive')                       AS alive_count,
            SUM(status = 'Deceased')                    AS deceased_count
        FROM people2
        $gender_filter
        GROUP BY first_name
        ORDER BY total_count DESC, first_name ASC
    ";
    $res  = $mysqli->query($q);
    $rows = [];
    $rank = 1;
    while ($row = $res->fetch_assoc()) {
        $row['rank'] = $rank++;
        $rows[]      = $row;
    }
    echo json_encode(['data' => $rows, 'total' => count($rows)]);
    $mysqli->close();
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'people_list' && isset($_GET['name'])) {
    header('Content-Type: application/json; charset=utf-8');
    $name_param = $mysqli->real_escape_string($_GET['name']);

    $gender_filter = '';
    if (!empty($_GET['gender']) && in_array($_GET['gender'], ['Male','Female'])) {
        $gender_filter = "AND p.gender = '" . $mysqli->real_escape_string($_GET['gender']) . "'";
    }

    $q = "
        SELECT
            p.id,
            p.full_name AS person_name,
            p.gender,
            p.status,
            f.full_name AS father_name,
            gf.full_name AS grandfather_name
        FROM people2 p
        LEFT JOIN people2 f ON f.id = p.father_id
        LEFT JOIN people2 gf ON gf.id = f.father_id
        WHERE SUBSTRING_INDEX(p.full_name, ' ', 1) = '$name_param'
        $gender_filter
        ORDER BY p.id ASC
    ";
    $res = $mysqli->query($q);
    $people = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $people[] = $row;
        }
    }
    echo json_encode(['data' => $people]);
    $mysqli->close();
    exit;
}

/* ════════════════════════════════════════════════
   PHP — MAIN STATS (for initial SSR summary cards)
════════════════════════════════════════════════ */
$total_people  = (int)$mysqli->query("SELECT COUNT(*) AS c FROM people2")->fetch_assoc()['c'];
$total_males   = (int)$mysqli->query("SELECT COUNT(*) AS c FROM people2 WHERE gender='Male'")->fetch_assoc()['c'];
$total_females = (int)$mysqli->query("SELECT COUNT(*) AS c FROM people2 WHERE gender='Female'")->fetch_assoc()['c'];
$total_alive   = (int)$mysqli->query("SELECT COUNT(*) AS c FROM people2 WHERE status='Alive'")->fetch_assoc()['c'];
$total_deceased= (int)$mysqli->query("SELECT COUNT(*) AS c FROM people2 WHERE status='Deceased'")->fetch_assoc()['c'];

// Unique first names count
$unique_names = (int)$mysqli->query("SELECT COUNT(DISTINCT SUBSTRING_INDEX(full_name,' ',1)) AS c FROM people2")->fetch_assoc()['c'];

// Most common name
$top_name_row = $mysqli->query("
    SELECT SUBSTRING_INDEX(full_name,' ',1) AS nm, COUNT(*) AS cnt
    FROM people2 GROUP BY nm ORDER BY cnt DESC LIMIT 1
")->fetch_assoc();
$top_name       = $top_name_row['nm']   ?? '—';
$top_name_count = $top_name_row['cnt']  ?? 0;

// Rarest names (count=1)
$rare_count = (int)$mysqli->query("
    SELECT COUNT(*) AS c FROM (
        SELECT SUBSTRING_INDEX(full_name,' ',1) AS nm
        FROM people2 GROUP BY nm HAVING COUNT(*)=1
    ) t
")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="<?= $isEn ? 'en' : 'ku' ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#070c12">
<title><?= $isEn ? 'Name Statistics – Family System' : 'ئامارێن ناڤان – سیستەمێ بنەمالێ' ?></title>
<meta name="description" content="<?= $isEn ? 'Unique name frequency analysis for the family database.' : 'شیکرنا دوبارەبوونا ناڤێن جودا بۆ داتابەیسێ بنەمالێ.' ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
/* ══ RESET & ROOT ══════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:        #070c12;
  --surface:   #0d1420;
  --surface2:  #131c2e;
  --surface3:  #1a2540;
  --border:    rgba(99,179,255,.12);
  --border2:   rgba(99,179,255,.22);
  --accent:    #3b82f6;
  --accent2:   #60a5fa;
  --gold:      #f59e0b;
  --gold2:     #fcd34d;
  --green:     #22c55e;
  --green2:    #4ade80;
  --red:       #ef4444;
  --red2:      #f87171;
  --purple:    #8b5cf6;
  --purple2:   #a78bfa;
  --text:      #e2e8f0;
  --text2:     #94a3b8;
  --text3:     #64748b;
  --radius:    12px;
  --shadow:    0 4px 24px rgba(0,0,0,.5);
  --shadow-lg: 0 8px 40px rgba(0,0,0,.6);
}

   @font-face {
            font-family: 'CustomFont';
            src: url('./Speda.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
html,body{
  min-height:100%;
  font-family:'CustomFont','Inter','Tajawal',sans-serif;
  background:var(--bg);
  color:var(--text);
  font-size:15px;
  line-height:1.5;
}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--surface)}
::-webkit-scrollbar-thumb{background:rgba(99,179,255,.25);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:rgba(99,179,255,.4)}

/* ══ PAGE LAYOUT ══════════════════════════════════════════ */
.page-wrap{
  max-width:1200px; margin:0 auto;
  padding:24px 16px 60px;
}

/* ══ TOP BAR ══════════════════════════════════════════════ */
.topbar{
  height:52px;
  background:rgba(13,20,32,.96);
  border-bottom:1px solid var(--border);
  backdrop-filter:blur(20px);
  display:flex;align-items:center;padding:0 20px;gap:14px;
  position:sticky;top:0;z-index:200;
  box-shadow:0 2px 20px rgba(0,0,0,.4);
}
.topbar-logo{
  font-size:1rem;font-weight:800;
  background:linear-gradient(135deg,var(--accent2),var(--purple2));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
  white-space:nowrap;
}
.topbar-sep{flex:1}
.topbar-nav{display:flex;gap:6px;}
.nav-link{
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 12px;border-radius:8px;
  border:1px solid var(--border);
  background:rgba(255,255,255,.03);
  color:var(--text2);font-size:12px;font-weight:500;
  text-decoration:none;transition:all .2s;white-space:nowrap;
}
.nav-link:hover{background:rgba(99,179,255,.1);border-color:var(--border2);color:var(--accent2);}
.nav-link i{font-size:11px;}

/* ══ PAGE HEADER ══════════════════════════════════════════ */
.page-header{
  padding:32px 0 24px;
  display:flex;align-items:flex-start;justify-content:space-between;
  gap:16px;flex-wrap:wrap;
}
.page-title{
  font-size:1.75rem;font-weight:800;
  background:linear-gradient(135deg,#fff 0%,var(--accent2) 60%,var(--purple2) 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
  margin-bottom:6px;line-height:1.2;
}
.page-sub{font-size:13px;color:var(--text3);}

/* ══ SUMMARY CARDS ═════════════════════════════════════════ */
.summary-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
  gap:12px;
  margin-bottom:28px;
}
.sum-card{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:16px 18px;
  position:relative;overflow:hidden;
  transition:transform .2s,box-shadow .2s;
}
.sum-card:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
.sum-card-icon{
  width:38px;height:38px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  font-size:16px;margin-bottom:10px;
}
.sum-card-val{
  font-size:1.6rem;font-weight:800;
  line-height:1;margin-bottom:4px;
}
.sum-card-label{font-size:11px;color:var(--text3);font-weight:500;}

/* Color variants */
.sc-blue .sum-card-icon{background:rgba(59,130,246,.15);color:var(--accent2);}
.sc-blue .sum-card-val{color:var(--accent2);}
.sc-blue{border-color:rgba(59,130,246,.2);}
.sc-gold .sum-card-icon{background:rgba(245,158,11,.15);color:var(--gold2);}
.sc-gold .sum-card-val{color:var(--gold2);}
.sc-gold{border-color:rgba(245,158,11,.2);}
.sc-purple .sum-card-icon{background:rgba(139,92,246,.15);color:var(--purple2);}
.sc-purple .sum-card-val{color:var(--purple2);}
.sc-purple{border-color:rgba(139,92,246,.2);}
.sc-teal .sum-card-icon{background:rgba(20,184,166,.15);color:#2dd4bf;}
.sc-teal .sum-card-val{color:#2dd4bf;}
.sc-teal{border-color:rgba(20,184,166,.2);}
.sc-red .sum-card-icon{background:rgba(239,68,68,.15);color:var(--red2);}
.sc-red .sum-card-val{color:var(--red2);}
.sc-red{border-color:rgba(239,68,68,.2);}
.sc-green .sum-card-icon{background:rgba(34,197,94,.15);color:var(--green2);}
.sc-green .sum-card-val{color:var(--green2);}
.sc-green{border-color:rgba(34,197,94,.2);}

/* ══ TOP NAMES BAR CHART (visual) ═════════════════════════ */
.chart-section{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:20px 22px;
  margin-bottom:24px;
}
.section-title{
  font-size:13px;font-weight:700;color:var(--text2);
  display:flex;align-items:center;gap:8px;
  margin-bottom:16px;
  text-transform:uppercase;letter-spacing:.8px;
}
.section-title i{color:var(--accent);}
.bar-list{display:flex;flex-direction:column;gap:8px;}
.bar-item{display:flex;align-items:center;gap:10px;}
.bar-rank{
  width:24px;font-size:10px;font-weight:700;
  color:var(--text3);text-align:center;flex-shrink:0;
}
.bar-name{
  width:110px;font-size:12px;font-weight:600;
  color:var(--text);flex-shrink:0;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.bar-track{flex:1;height:22px;background:rgba(255,255,255,.04);border-radius:4px;overflow:hidden;position:relative;}
.bar-fill{
  height:100%;border-radius:4px;
  display:flex;align-items:center;padding-left:8px;
  transition:width 1s cubic-bezier(.4,0,.2,1);
  min-width:2px;
}
.bar-fill-text{font-size:10px;font-weight:700;color:rgba(255,255,255,.9);white-space:nowrap;}
.bar-count{
  width:38px;text-align:right;font-size:12px;font-weight:700;
  color:var(--text2);flex-shrink:0;
}
.bar-pct{
  width:36px;text-align:right;font-size:10px;
  color:var(--text3);flex-shrink:0;
}

/* ══ CONTROLS BAR ══════════════════════════════════════════ */
.controls-bar{
  display:flex;align-items:center;gap:10px;
  margin-bottom:16px;flex-wrap:wrap;
}
.search-box{
  flex:1;min-width:180px;
  position:relative;
}
.search-box input{
  width:100%;padding:9px 12px 9px 36px;
  background:var(--surface2);
  border:1px solid var(--border);
  border-radius:9px;
  color:var(--text);font-size:13px;
  font-family:'CustomFont','Inter','Tajawal',sans-serif;
  outline:none;transition:border .2s;
}
.search-box input:focus{border-color:var(--border2);}
.search-box input::placeholder{color:var(--text3);}
.search-box i{
  position:absolute;left:11px;top:50%;transform:translateY(-50%);
  color:var(--text3);font-size:13px;pointer-events:none;
}
html[dir="rtl"] .search-box i{left:auto;right:11px;}
html[dir="rtl"] .search-box input{padding:9px 36px 9px 12px;}

.filter-btn{
  padding:9px 14px;border-radius:9px;
  border:1px solid var(--border);
  background:var(--surface2);
  color:var(--text2);font-size:12px;font-weight:600;
  cursor:pointer;transition:all .2s;
  display:flex;align-items:center;gap:6px;
  white-space:nowrap;
}
.filter-btn:hover,.filter-btn.active{
  background:rgba(59,130,246,.15);
  border-color:rgba(59,130,246,.35);
  color:var(--accent2);
}
.filter-btn.active-male{background:rgba(59,130,246,.15);border-color:rgba(59,130,246,.35);color:var(--accent2);}
.filter-btn.active-female{background:rgba(236,72,153,.15);border-color:rgba(236,72,153,.35);color:#f0abcb;}

.count-badge{
  margin-left:auto;
  font-size:11px;color:var(--text3);
  background:var(--surface2);border:1px solid var(--border);
  border-radius:20px;padding:4px 12px;
  white-space:nowrap;
}

/* ══ TABLE ══════════════════════════════════════════════════ */
.table-wrap{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
}
.tbl{width:100%;border-collapse:collapse;}
.tbl thead tr{
  background:var(--surface2);
  border-bottom:1px solid var(--border);
}
.tbl thead th{
  padding:12px 14px;
  font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.9px;
  color:var(--text3);
  white-space:nowrap;cursor:pointer;
  user-select:none;transition:color .2s;
}
.tbl thead th:hover{color:var(--accent2);}
.tbl thead th.sorted-asc::after{content:' ↑';color:var(--accent2);}
.tbl thead th.sorted-desc::after{content:' ↓';color:var(--accent2);}

.tbl tbody tr{
  border-bottom:1px solid rgba(255,255,255,.04);
  transition:background .15s;
}
.tbl tbody tr:last-child{border-bottom:none;}
.tbl tbody tr:hover{background:rgba(255,255,255,.03);}

.tbl td{padding:11px 14px;font-size:13px;vertical-align:middle;}

/* Rank cell */
.td-rank{
  font-size:11px;font-weight:700;
  color:var(--text3);width:42px;text-align:center;
}
.rank-medal{
  display:inline-flex;align-items:center;justify-content:center;
  width:26px;height:26px;border-radius:50%;
  font-size:13px;
}
.medal-1{background:rgba(245,158,11,.2);color:#fcd34d;}
.medal-2{background:rgba(148,163,184,.15);color:#cbd5e1;}
.medal-3{background:rgba(180,100,60,.15);color:#d98b6a;}

/* Name cell */
.td-name{
  font-size:14px;font-weight:700;color:var(--text);
  max-width:160px;
}
.name-arabic{
  font-family:'CustomFont','Tajawal',sans-serif;
  font-size:14px;
}

/* Progress bar inside table */
.td-bar{width:160px;min-width:100px;}
.mini-bar{
  height:6px;background:rgba(255,255,255,.06);
  border-radius:3px;overflow:hidden;
}
.mini-fill{
  height:100%;border-radius:3px;
  background:linear-gradient(90deg,var(--accent),var(--purple));
  transition:width 1s cubic-bezier(.4,0,.2,1);
}

/* Count chips */
.count-total{
  display:inline-flex;align-items:center;gap:5px;
  font-size:14px;font-weight:800;color:var(--text);
}
.sub-counts{
  display:flex;gap:6px;margin-top:3px;flex-wrap:wrap;
}
.sub-chip{
  font-size:9px;padding:1px 6px;border-radius:10px;
  font-weight:600;white-space:nowrap;
}
.chip-male{background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.2);}
.chip-female{background:rgba(236,72,153,.12);color:#f9a8d4;border:1px solid rgba(236,72,153,.2);}
.chip-alive{background:rgba(34,197,94,.12);color:#86efac;border:1px solid rgba(34,197,94,.18);}
.chip-dead{background:rgba(100,116,139,.12);color:#94a3b8;border:1px solid rgba(100,116,139,.18);}

/* Pct */
.td-pct{
  font-size:12px;color:var(--text3);font-weight:500;
  font-family:'CustomFont','Inter',monospace;white-space:nowrap;
}

/* View Button */
.view-btn{
  padding:6px 12px;border-radius:8px;
  background:rgba(99,179,255,.1);
  border:1px solid rgba(99,179,255,.2);
  color:var(--accent2);font-size:11px;font-weight:700;
  cursor:pointer;transition:all .2s;
  display:flex;align-items:center;gap:6px;
  white-space:nowrap;
}
.view-btn:hover{
  background:rgba(99,179,255,.2);
  transform:translateY(-1px);
}

/* ══ MODAL ═════════════════════════════════════════════════ */
#modal-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);
  z-index:1000;display:none;align-items:center;justify-content:center;
  padding:20px;
}
#modal-content{
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius);width:100%;max-width:600px;
  max-height:85vh;display:flex;flex-direction:column;
  box-shadow:var(--shadow-lg);
}
.modal-hdr{
  padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.modal-title{
  font-size:16px;font-weight:700;color:var(--text);
  display:flex;align-items:center;gap:8px;
}
.modal-title span{color:var(--accent2);}
.close-modal{
  width:32px;height:32px;border-radius:8px;
  background:rgba(255,255,255,.05);border:none;
  color:var(--text2);font-size:16px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all .2s;
}
.close-modal:hover{background:var(--red);color:#fff;}
.modal-body{
  padding:16px 20px;overflow-y:auto;flex:1;
}
.people-list{
  display:flex;flex-direction:column;gap:8px;
}
.person-card{
  background:var(--surface2);border:1px solid var(--border);
  border-radius:8px;padding:12px;
  display:flex;align-items:center;gap:12px;
}
.person-icon{
  width:40px;height:40px;border-radius:50%;
  background:rgba(255,255,255,.05);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;flex-shrink:0;
}
.person-icon.male{color:var(--accent2);background:rgba(59,130,246,.15);}
.person-icon.female{color:#f9a8d4;background:rgba(236,72,153,.15);}

.person-info{flex:1;}
.p-name{font-size:14px;font-weight:700;color:var(--text);font-family:'CustomFont','Tajawal',sans-serif;margin-bottom:2px;}
.p-lineage{font-size:11px;color:var(--text2);font-family:'CustomFont','Tajawal',sans-serif;display:flex;align-items:center;gap:6px;}
.p-lineage i{font-size:9px;color:var(--accent);}

.p-status{
  font-size:10px;padding:2px 8px;border-radius:12px;font-weight:600;white-space:nowrap;
}
.p-status.alive{background:rgba(34,197,94,.15);color:var(--green2);}
.p-status.dead{background:rgba(100,116,139,.2);color:var(--text2);}

/* ══ LOADER SKELETON ════════════════════════════════════════ */
.skeleton{
  background:linear-gradient(90deg,var(--surface2) 25%,var(--surface3) 50%,var(--surface2) 75%);
  background-size:200% 100%;
  animation:shimmer 1.4s infinite;
  border-radius:4px;
}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.sk-row td{padding:12px 14px;}
.sk-block{height:12px;border-radius:4px;}

/* ══ EMPTY STATE ═══════════════════════════════════════════ */
.empty-state{
  padding:60px 20px;text-align:center;
  color:var(--text3);
}
.empty-icon{font-size:40px;margin-bottom:12px;opacity:.4;}
.empty-text{font-size:13px;}

/* ══ RESPONSIVE ════════════════════════════════════════════ */
@media(max-width:850px){
  .summary-grid{grid-template-columns:repeat(3,1fr);}
}
@media(max-width:768px){
  .page-wrap{padding:16px 12px 60px;}
  .page-title{font-size:1.4rem;}
  .td-bar,.td-pct,.tbl thead th:nth-child(4),.tbl thead th:nth-child(5){display:none;}
  .table-wrap{overflow-x:auto;}
  .tbl td, .tbl th {padding:10px 8px;}
  .summary-grid{grid-template-columns:repeat(2,1fr);gap:10px;}
  .sum-card{padding:14px 12px;}
  .sum-card-val{font-size:1.4rem;}
  .bar-name{width:80px;}
  .topbar-logo{font-size:0.9rem;}
  .nav-link{padding:5px 8px;font-size:11px;}
}
@media(max-width:480px){
  .page-title{font-size:1.25rem;}
  .page-header{padding:20px 0 16px;}
  .summary-grid{grid-template-columns:repeat(2,1fr);gap:8px;}
  .sum-card-val{font-size:1.2rem;}
  .sum-card-icon{width:32px;height:32px;font-size:14px;margin-bottom:6px;}
  .sum-card-label{font-size:10px;}

  .controls-bar{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:8px;
    align-items:stretch;
  }
  .search-box{grid-column:1 / -1;}
  .filter-btn{
    padding:8px;
    font-size:11px;
    justify-content:center;
    width:100%;
  }
  .count-badge{
    grid-column:1 / -1;
    margin:0;
    text-align:center;
  }

  .td-name{max-width:110px;font-size:13px;white-space:normal;word-break:break-word;}
  .view-btn{padding:5px 8px;font-size:10px;}
  .count-total span:first-child{font-size:15px;}
  
  .modal-hdr{flex-direction:column;align-items:flex-start;gap:12px;}
  .close-modal{position:absolute;top:12px;left:12px;}
  html[dir="rtl"] .close-modal{left:12px;right:auto;}
  
  .person-card{padding:10px;gap:10px;}
  .person-icon{width:36px;height:36px;font-size:16px;}
  .p-name{font-size:13px;}
  .p-lineage{font-size:10px;flex-wrap:wrap;}
}
</style>
</head>
<body>

<!-- ── TOP BAR ─────────────────────────────────────────── -->
<div class="topbar">
  <div class="topbar-logo">
    <i class="fas fa-chart-bar" style="font-size:1.1rem;margin-<?= $isEn?'right':'left' ?>:8px;color:var(--accent2)"></i>
    <?= $isEn ? 'Name Statistics' : 'ئامارێن ناڤان' ?>
  </div>
  <div class="topbar-sep"></div>
  <nav class="topbar-nav">
    <a href="index.php"   class="nav-link"><i class="fas fa-home"></i><?= $isEn?'Home':'سەرەکی' ?></a>
    <a href="tree.php"    class="nav-link"><i class="fas fa-sitemap"></i><?= $isEn?'Tree':'دارا بنەمالێ' ?></a>
    <a href="family_list.php" class="nav-link"><i class="fas fa-list"></i><?= $isEn?'List':'لیستە' ?></a>
  </nav>
</div>

<div class="page-wrap">

  <!-- ── PAGE HEADER ──────────────────────────────────── -->
  <div class="page-header">
    <div class="page-title-block">
      <h1 class="page-title">
        <?= $isEn ? '🔤 Name Frequency Statistics' : '🔤 ئامارێن دوبارەبوونا ناڤان' ?>
      </h1>
      <p class="page-sub">
        <?= $isEn
          ? 'All unique first names, sorted by frequency — highest to lowest.'
          : 'هەمی ناڤێن ئێکێ یێن جودا، برێزبەندی ل گورەی دوبارەبوونێ — ژ یێ پتر بۆ یێ کێمتر.' ?>
      </p>
    </div>
  </div>

  <!-- ── SUMMARY CARDS ─────────────────────────────────── -->
  <div class="summary-grid">
    <div class="sum-card sc-blue">
      <div class="sum-card-icon"><i class="fas fa-users"></i></div>
      <div class="sum-card-val"><?= number_format($total_people) ?></div>
      <div class="sum-card-label"><?= $isEn?'Total Members':'هەمی ئەندام' ?></div>
    </div>
    <div class="sum-card sc-gold">
      <div class="sum-card-icon"><i class="fas fa-font"></i></div>
      <div class="sum-card-val"><?= number_format($unique_names) ?></div>
      <div class="sum-card-label"><?= $isEn?'Unique Names':'ناڤێن جودا' ?></div>
    </div>
    <div class="sum-card sc-purple">
      <div class="sum-card-icon"><i class="fas fa-trophy"></i></div>
      <div class="sum-card-val"><?= $top_name_count ?></div>
      <div class="sum-card-label"><?= $isEn?"Most: $top_name":"پترترین: $top_name" ?></div>
    </div>
    <div class="sum-card sc-teal">
      <div class="sum-card-icon"><i class="fas fa-mars"></i></div>
      <div class="sum-card-val"><?= number_format($total_males) ?></div>
      <div class="sum-card-label"><?= $isEn?'Males':'نێر' ?></div>
    </div>
    <div class="sum-card sc-red">
      <div class="sum-card-icon"><i class="fas fa-venus"></i></div>
      <div class="sum-card-val"><?= number_format($total_females) ?></div>
      <div class="sum-card-label"><?= $isEn?'Females':'مێ' ?></div>
    </div>
    <div class="sum-card sc-green">
      <div class="sum-card-icon"><i class="fas fa-star"></i></div>
      <div class="sum-card-val"><?= number_format($rare_count) ?></div>
      <div class="sum-card-label"><?= $isEn?'Names used once':'ناڤێن ئێک جار هاتین' ?></div>
    </div>
  </div>

  <!-- ── TOP 15 BAR CHART ──────────────────────────────── -->
  <div class="chart-section">
    <div class="section-title">
      <i class="fas fa-chart-bar"></i>
      <?= $isEn ? 'Top 15 Most Common Names' : '١٥ ناڤێن پترترین هاتین' ?>
    </div>
    <div class="bar-list" id="bar-chart">
      <!-- filled by JS -->
      <?php for($i=0;$i<8;$i++): ?>
      <div class="bar-item">
        <div class="bar-rank">–</div>
        <div class="bar-name"><div class="skeleton sk-block" style="width:80px"></div></div>
        <div class="bar-track"><div class="bar-fill skeleton" style="width:60%"></div></div>
        <div class="bar-count"><div class="skeleton sk-block" style="width:28px"></div></div>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- ── CONTROLS ──────────────────────────────────────── -->
  <div class="controls-bar">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" id="search-input"
        placeholder="<?= $isEn?'Search name...':'ل ناڤی بگەڕێ...' ?>"
        autocomplete="off">
    </div>
    <button class="filter-btn" id="f-all" onclick="setFilter('')">
      <i class="fas fa-globe"></i><?= $isEn?'All':'هەمی' ?>
    </button>
    <button class="filter-btn" id="f-male" onclick="setFilter('Male')">
      <i class="fas fa-mars"></i><?= $isEn?'Males':'نێر' ?>
    </button>
    <button class="filter-btn" id="f-female" onclick="setFilter('Female')">
      <i class="fas fa-venus"></i><?= $isEn?'Females':'مێ' ?>
    </button>
    <div class="count-badge" id="count-badge">
      <?= $isEn?"Showing $unique_names names":"$unique_names ناڤێن جودا نیشان ددەت" ?>
    </div>
  </div>

  <!-- ── TABLE ─────────────────────────────────────────── -->
  <div class="table-wrap">
    <table class="tbl" id="names-table">
      <thead>
        <tr>
          <th style="text-align:center" onclick="sortBy('rank')">#</th>
          <th onclick="sortBy('first_name')"><?= $isEn?'Name':'ناڤ' ?></th>
          <th onclick="sortBy('total_count')"><?= $isEn?'Count & Details':'هژمار و هوردەکاری' ?></th>
          <th class="td-bar" onclick="sortBy('total_count')"><?= $isEn?'Frequency':'ڕێژەیا دوبارەبوونێ' ?></th>
          <th class="td-pct" onclick="sortBy('total_count')"><?= $isEn?'% of Total':'% ژ هەمیێ' ?></th>
          <th style="text-align:center"><?= $isEn?'Actions':'کریار' ?></th>
        </tr>
      </thead>
      <tbody id="table-body">
        <!-- skeleton rows -->
        <?php for($i=0;$i<12;$i++): ?>
        <tr class="sk-row">
          <td><div class="skeleton sk-block" style="width:24px;height:24px;border-radius:50%;margin:auto"></div></td>
          <td><div class="skeleton sk-block" style="width:<?= rand(60,120) ?>px"></div></td>
          <td>
            <div class="skeleton sk-block" style="width:40px;margin-bottom:6px"></div>
            <div class="skeleton sk-block" style="width:90px;height:8px"></div>
          </td>
          <td><div class="skeleton sk-block" style="width:100%;height:6px"></div></td>
          <td><div class="skeleton sk-block" style="width:32px"></div></td>
          <td><div class="skeleton sk-block" style="width:80px;height:24px;border-radius:8px"></div></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

</div><!-- /page-wrap -->

<!-- ── SCROLL TOP ──────────────────────────────────────── -->
<button id="scroll-top" title="Scroll to top"><i class="fas fa-arrow-up"></i></button>

<!-- ── MODAL ───────────────────────────────────────────── -->
<div id="modal-overlay">
  <div id="modal-content">
    <div class="modal-hdr">
      <div class="modal-title">
        <i class="fas fa-users" style="color:var(--accent2)"></i>
        <span id="m-title-name"></span>
        <small style="color:var(--text3);font-size:12px;margin-<?= $isEn?'left':'right' ?>:8px" id="m-title-count"></small>
      </div>
      <button class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="people-list" id="modal-people-list">
        <!-- filled via AJAX -->
      </div>
    </div>
  </div>
</div>

<script>
'use strict';
/* ════════════════════════════════════════
   STATE
════════════════════════════════════════ */
const LANG = '<?= $isEn ? "en" : "ku" ?>';
const TOTAL_PEOPLE = <?= $total_people ?>;

let allData      = [];
let filteredData = [];
let currentFilter = '';
let sortKey       = 'rank';
let sortDir       = 'asc';
let searchQ       = '';

/* ════════════════════════════════════════
   FETCH DATA
════════════════════════════════════════ */
async function fetchData(gender = '') {
  try {
    const url = `name_stats.php?ajax=1` + (gender ? `&gender=${gender}` : '');
    const res  = await fetch(url);
    const json = await res.json();
    return json.data || [];
  } catch(e) {
    console.error(e);
    return [];
  }
}

async function loadAll() {
  allData = await fetchData('');
  applyFilter();
  buildBarChart();
}

/* ════════════════════════════════════════
   FILTER + SORT + SEARCH
════════════════════════════════════════ */
function setFilter(gender) {
  currentFilter = gender;
  ['f-all','f-male','f-female'].forEach(id => {
    document.getElementById(id).className = 'filter-btn';
  });
  if (gender === '')       document.getElementById('f-all').classList.add('active');
  if (gender === 'Male')   document.getElementById('f-male').classList.add('active-male');
  if (gender === 'Female') document.getElementById('f-female').classList.add('active-female');
  applyFilter();
}

function applyFilter() {
  let data = allData;
  if (searchQ) {
    data = data.filter(r => r.first_name.toLowerCase().includes(searchQ));
  }
  data = [...data].sort((a,b) => {
    let va = a[sortKey], vb = b[sortKey];
    if (sortKey === 'first_name') {
      va = va || ''; vb = vb || '';
      return sortDir === 'asc' ? va.localeCompare(vb,'ar') : vb.localeCompare(va,'ar');
    }
    va = Number(va); vb = Number(vb);
    return sortDir === 'asc' ? va - vb : vb - va;
  });

  filteredData = data;
  filteredData.forEach((r,i) => r._displayRank = i + 1);

  const badge = document.getElementById('count-badge');
  badge.textContent = LANG === 'en'
    ? `Showing ${data.length} unique names`
    : `${data.length} ناڤێن جودا نیشان ددەت`;

  renderTable();
}

function sortBy(key) {
  if (sortKey === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
  else { sortKey = key; sortDir = key === 'first_name' ? 'asc' : 'desc'; }

  document.querySelectorAll('.tbl thead th').forEach(th => th.classList.remove('sorted-asc','sorted-desc'));
  const keyMap = {rank:0, first_name:1, total_count:2};
  const colIdx = keyMap[key];
  if (colIdx !== undefined) {
    const ths = document.querySelectorAll('.tbl thead th');
    if (ths[colIdx]) ths[colIdx].classList.add(sortDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
  }
  applyFilter();
}

/* ════════════════════════════════════════
   BAR CHART (top 15)
════════════════════════════════════════ */
const GRAD_COLORS = [
  ['#3b82f6','#8b5cf6'],['#06b6d4','#3b82f6'],['#8b5cf6','#ec4899'],
  ['#f59e0b','#ef4444'],['#22c55e','#06b6d4'],['#f97316','#f59e0b'],
  ['#ec4899','#8b5cf6'],['#14b8a6','#22c55e'],['#a78bfa','#c084fc'],
  ['#60a5fa','#34d399'],['#fbbf24','#f472b6'],['#4ade80','#22d3ee'],
  ['#f87171','#fb923c'],['#c084fc','#e879f9'],['#38bdf8','#818cf8'],
];

function buildBarChart() {
  const top15  = allData.slice(0, 15);
  const maxCnt = top15.length ? top15[0].total_count : 1;
  const chart  = document.getElementById('bar-chart');
  chart.innerHTML = '';

  top15.forEach((row, i) => {
    const pct  = Math.round((row.total_count / maxCnt) * 100);
    const [c1,c2] = GRAD_COLORS[i % GRAD_COLORS.length];
    const div  = document.createElement('div');
    div.className = 'bar-item';
    div.innerHTML = `
      <div class="bar-rank" style="color:${i<3?GRAD_COLORS[i][0]:''}">${i+1}</div>
      <div class="bar-name" title="${row.first_name}">${row.first_name}</div>
      <div class="bar-track">
        <div class="bar-fill" style="width:0%;background:linear-gradient(90deg,${c1},${c2})">
          <span class="bar-fill-text">${row.total_count}</span>
        </div>
      </div>
      <div class="bar-count" style="color:${c1}">${row.total_count}</div>
      <div class="bar-pct">${((row.total_count/TOTAL_PEOPLE)*100).toFixed(1)}%</div>
    `;
    chart.appendChild(div);
    requestAnimationFrame(() => {
      setTimeout(() => { div.querySelector('.bar-fill').style.width = pct + '%'; }, 80 + i * 40);
    });
  });
}

/* ════════════════════════════════════════
   RENDER TABLE
════════════════════════════════════════ */
function renderTable() {
  const tbody  = document.getElementById('table-body');
  const maxCnt = filteredData.length ? Math.max(...filteredData.map(r=>Number(r.total_count))) : 1;

  if (!filteredData.length) {
    tbody.innerHTML = `
      <tr><td colspan="6">
        <div class="empty-state">
          <div class="empty-icon">🔍</div>
          <div class="empty-text">${LANG==='en'?'No names found matching your search.':'چ ناڤ نەهاتن دیتن.'}</div>
        </div>
      </td></tr>`;
    return;
  }

  const html = filteredData.map((row, i) => {
    const rank       = row._displayRank || (i+1);
    const pct        = Math.round((Number(row.total_count) / maxCnt) * 100);
    const pctOfTotal = ((Number(row.total_count) / TOTAL_PEOPLE) * 100).toFixed(1);
    const [c1,c2]    = GRAD_COLORS[i % GRAD_COLORS.length];

    let rankHtml;
    if      (rank===1) rankHtml = `<span class="rank-medal medal-1">🥇</span>`;
    else if (rank===2) rankHtml = `<span class="rank-medal medal-2">🥈</span>`;
    else if (rank===3) rankHtml = `<span class="rank-medal medal-3">🥉</span>`;
    else               rankHtml = `<span style="color:var(--text3);font-size:11px;font-weight:700">${rank}</span>`;

    const maleChip    = Number(row.male_count)    > 0 ? `<span class="sub-chip chip-male">♂ ${row.male_count}</span>` : '';
    const femaleChip  = Number(row.female_count)  > 0 ? `<span class="sub-chip chip-female">♀ ${row.female_count}</span>` : '';
    const aliveChip   = Number(row.alive_count)   > 0 ? `<span class="sub-chip chip-alive">● ${LANG==='en'?'Alive':'ساخ'} ${row.alive_count}</span>` : '';
    const deadChip    = Number(row.deceased_count)> 0 ? `<span class="sub-chip chip-dead">○ ${LANG==='en'?'Late':'مری'} ${row.deceased_count}</span>` : '';

    return `
    <tr>
      <td class="td-rank">${rankHtml}</td>
      <td>
        <div class="td-name name-arabic" style="color:${c1}">${row.first_name}</div>
      </td>
      <td>
        <div class="count-total">
          <span style="font-size:18px;color:${c1}">${row.total_count}</span>
          <span style="font-size:10px;color:var(--text3)">${LANG==='en'?'times':'جاران'}</span>
        </div>
        <div class="sub-counts">
          ${maleChip}${femaleChip}${aliveChip}${deadChip}
        </div>
      </td>
      <td class="td-bar">
        <div class="mini-bar">
          <div class="mini-fill" style="width:${pct}%;background:linear-gradient(90deg,${c1},${c2})"></div>
        </div>
      </td>
      <td class="td-pct">${pctOfTotal}%</td>
      <td style="text-align:center">
        <button class="view-btn" onclick="openModal('${row.first_name}', ${row.total_count})">
          <i class="fas fa-list-ul"></i> ${LANG==='en'?'View List':'دیتنا لیستێ'}
        </button>
      </td>
    </tr>`;
  }).join('');

  tbody.innerHTML = html;
}

/* ════════════════════════════════════════
   MODAL & VIEW PEOPLE
════════════════════════════════════════ */
async function openModal(firstName, count) {
  const overlay = document.getElementById('modal-overlay');
  const title = document.getElementById('m-title-name');
  const subtitle = document.getElementById('m-title-count');
  const list = document.getElementById('modal-people-list');

  title.textContent = firstName;
  subtitle.textContent = `(${count} ${LANG==='en'?'people':'کەس'})`;
  list.innerHTML = `
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--accent2)"></i>
    </div>
  `;
  overlay.style.display = 'flex';

  try {
    let url = `name_stats.php?ajax=people_list&name=${encodeURIComponent(firstName)}`;
    if (currentFilter) url += `&gender=${currentFilter}`;

    const res = await fetch(url);
    const json = await res.json();
    const data = json.data || [];

    if (!data.length) {
      list.innerHTML = `<div class="empty-state">${LANG==='en'?'No records found.':'چ تۆمار نەهاتن دیتن.'}</div>`;
      return;
    }

    list.innerHTML = data.map(p => {
      const isMale = p.gender === 'Male';
      const isAlive = p.status === 'Alive';
      const icon = isMale ? 'fa-male' : 'fa-female';
      const iconClass = isMale ? 'male' : 'female';
      const statusText = isAlive ? (LANG==='en'?'Alive':'ساخ') : (LANG==='en'?'Late':'مری');
      const statusClass = isAlive ? 'alive' : 'dead';

      // Lineage (Father & Grandfather)
      let lineageHTML = '';
      if (p.father_name || p.grandfather_name) {
         let parts = [];
         if (p.father_name) parts.push(LANG==='en' ? `Son of ${p.father_name}` : `کورێ ${p.father_name}`);
         if (p.grandfather_name) parts.push(LANG==='en' ? `Grandson of ${p.grandfather_name}` : `نەڤیێ ${p.grandfather_name}`);
         lineageHTML = `<div class="p-lineage"><i class="fas fa-link"></i> ${parts.join(' — ')}</div>`;
      } else {
         lineageHTML = `<div class="p-lineage"><i class="fas fa-link"></i> ${LANG==='en'?'Root ancestor / No links':'باپیرێ مەزن / چ گرێدان نینن'}</div>`;
      }

      return `
        <div class="person-card">
          <div class="person-icon ${iconClass}"><i class="fas ${icon}"></i></div>
          <div class="person-info">
            <div class="p-name">${p.person_name}</div>
            ${lineageHTML}
          </div>
          <div class="p-status ${statusClass}">${statusText}</div>
        </div>
      `;
    }).join('');

  } catch(e) {
    console.error(e);
    list.innerHTML = `<div class="empty-state" style="color:var(--red2)">Error loading data</div>`;
  }
}

function closeModal() {
  document.getElementById('modal-overlay').style.display = 'none';
}
// click outside to close
document.getElementById('modal-overlay').addEventListener('click', e => {
  if (e.target === document.getElementById('modal-overlay')) closeModal();
});

/* ════════════════════════════════════════
   SEARCH DEBOUNCE
════════════════════════════════════════ */
let srchTimer;
document.getElementById('search-input').addEventListener('input', function() {
  clearTimeout(srchTimer);
  srchTimer = setTimeout(() => {
    searchQ = this.value.trim().toLowerCase();
    applyFilter();
  }, 250);
});

/* ════════════════════════════════════════
   SCROLL TO TOP
════════════════════════════════════════ */
const scrollBtn = document.getElementById('scroll-top');
window.addEventListener('scroll', () => {
  scrollBtn.style.display = window.scrollY > 300 ? 'flex' : 'none';
});
scrollBtn.onclick = () => window.scrollTo({top:0,behavior:'smooth'});

/* ════════════════════════════════════════
   INIT
════════════════════════════════════════ */
document.getElementById('f-all').classList.add('active');
document.querySelectorAll('.tbl thead th')[2].classList.add('sorted-desc');
loadAll();
</script>
</body>
</html>
