<?php
/**
 * auto_fill_names.php
 * ملء أعمدة full_name_en تلقائياً لجميع الأسماء في قاعدة البيانات
 * للمدير فقط
 */
require_once 'config.php';
if (!isAdmin()) { redirect('index.php'); }
$mysqli->set_charset('utf8mb4');

// ══════════════════════════════════════════════════
// دالة النقحرة الكاملة
// ══════════════════════════════════════════════════
function translitKu(string $t): string {
    $t = trim($t);
    if (!$t) return '';

    // ── مركّبة (تُعالج أولاً) ──
    $multi = [
        'وو'=>'u', 'آ'=>'a', 'اً'=>'an',
        'ڵا'=>'la','ڵێ'=>'le','ڵو'=>'lo','ڵی'=>'li',
        'ڕا'=>'ra','ڕێ'=>'re','ڕو'=>'ro','ڕی'=>'ri',
    ];
    // ── أحرف مفردة ──
    $single = [
        'ئ'=>'','ە'=>'e','ێ'=>'e','ۆ'=>'o','ڤ'=>'v','ڵ'=>'ll','ڕ'=>'r',
        'ک'=>'k','گ'=>'g','چ'=>'ch','ژ'=>'zh','ش'=>'sh','خ'=>'kh','غ'=>'gh',
        'ح'=>'h','ث'=>'s','ذ'=>'z','ص'=>'s','ض'=>'d','ط'=>'t','ظ'=>'z',
        'ع'=>'','ة'=>'e','ى'=>'i','ی'=>'i','ي'=>'i',
        'ا'=>'a','أ'=>'a','إ'=>'i','ب'=>'b','پ'=>'p','ت'=>'t','ج'=>'j',
        'د'=>'d','ر'=>'r','ز'=>'z','س'=>'s','ف'=>'f','ق'=>'q','ك'=>'k',
        'ل'=>'l','م'=>'m','ن'=>'n','ه'=>'h','ہ'=>'h','و'=>'w',
        // تشكيل
        'َ'=>'','ُ'=>'','ِ'=>'','ّ'=>'','ْ'=>'','ً'=>'','ٌ'=>'','ٍ'=>'',
        // أرقام عربية
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
        '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ];

    foreach ($multi  as $k=>$v) $t = str_replace($k,$v,$t);
    foreach ($single as $k=>$v) $t = str_replace($k,$v,$t);

    // حذف ما تبقى من أحرف غير لاتينية
    $t = preg_replace('/[^\x20-\x7E]/u', '', $t);

    // كل كلمة: أول حرف كبير
    $words = explode(' ', $t);
    $out   = array();
    foreach ($words as $w) {
        $w = trim($w);
        if ($w !== '') $out[] = ucfirst(strtolower($w));
    }
    return implode(' ', $out);
}

// ══════════════════════════════════════════════════
// معالجة الطلبات
// ══════════════════════════════════════════════════
$result_msg  = '';
$result_type = '';

// ── تطبيق الحفظ (AJAX أو POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // حفظ كل الأسماء المحوّلة (الفارغة فقط)
    if ($action === 'fill_empty') {
        $res   = $mysqli->query("SELECT id, full_name FROM people2 WHERE full_name_en IS NULL OR full_name_en = ''");
        $stmt  = $mysqli->prepare("UPDATE people2 SET full_name_en = ? WHERE id = ?");
        $count = 0; $skip = 0;
        while ($row = $res->fetch_assoc()) {
            $en = translitKu($row['full_name']);
            if ($en === '') { $skip++; continue; }
            $stmt->bind_param('si', $en, $row['id']);
            $stmt->execute();
            $count++;
        }
        $stmt->close();
        $result_msg  = "✅ تم تعبئة $count اسم" . ($skip ? " — ⚠ $skip اسم تعذّر تحويله" : '');
        $result_type = $skip ? 'warn' : 'ok';
    }

    // حفظ كل الأسماء (استبدال حتى الموجودة)
    if ($action === 'fill_all') {
        $res   = $mysqli->query("SELECT id, full_name FROM people2");
        $stmt  = $mysqli->prepare("UPDATE people2 SET full_name_en = ? WHERE id = ?");
        $count = 0; $skip = 0;
        while ($row = $res->fetch_assoc()) {
            $en = translitKu($row['full_name']);
            if ($en === '') { $skip++; continue; }
            $stmt->bind_param('si', $en, $row['id']);
            $stmt->execute();
            $count++;
        }
        $stmt->close();
        $result_msg  = "✅ تم تحديث $count اسم (بما فيها الموجودة)" . ($skip ? " — ⚠ $skip اسم تعذّر" : '');
        $result_type = $skip ? 'warn' : 'ok';
    }

    // حفظ تعديلات يدوية فردية
    if ($action === 'save_manual' && !empty($_POST['names'])) {
        $stmt  = $mysqli->prepare("UPDATE people2 SET full_name_en = ? WHERE id = ?");
        $count = 0;
        foreach ($_POST['names'] as $id => $name) {
            $id   = (int)$id;
            $name = trim($name);
            if ($id <= 0) continue;
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $count++;
        }
        $stmt->close();
        $result_msg  = "✅ تم حفظ $count اسم مُعدَّل يدوياً";
        $result_type = 'ok';
    }

    // مسح الكل
    if ($action === 'clear_all') {
        $mysqli->query("UPDATE people2 SET full_name_en = NULL");
        $result_msg  = '🔄 تم مسح جميع الأسماء الإنجليزية';
        $result_type = 'info';
    }
}

// ══════════════════════════════════════════════════
// جلب الأشخاص
// ══════════════════════════════════════════════════
$show   = $_GET['show'] ?? 'empty'; // empty | all | filled
$search = trim($_GET['q'] ?? '');

$where = '1=1';
if ($show === 'empty')  $where .= " AND (full_name_en IS NULL OR full_name_en = '')";
if ($show === 'filled') $where .= " AND full_name_en IS NOT NULL AND full_name_en != ''";
if ($search !== '')     $where .= " AND full_name LIKE '%" . $mysqli->real_escape_string($search) . "%'";

$people = [];
$res = $mysqli->query("SELECT id, full_name, full_name_en FROM people2 WHERE $where ORDER BY id ASC");
while ($r = $res->fetch_assoc()) $people[] = $r;
$res->free();

// إحصائيات
$total  = (int)$mysqli->query("SELECT COUNT(*) FROM people2")->fetch_row()[0];
$filled = (int)$mysqli->query("SELECT COUNT(*) FROM people2 WHERE full_name_en IS NOT NULL AND full_name_en != ''")->fetch_row()[0];
$empty  = $total - $filled;
$pct    = $total > 0 ? round($filled / $total * 100) : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تعبئة الأسماء الإنجليزية</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700;800;900&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#07090f;--card:rgba(255,255,255,.045);--border:rgba(255,255,255,.09);
    --text:#e8edf4;--muted:rgba(232,237,244,.42);
    --gold:#f6c941;--green:#34d399;--sky:#38bdf8;--rose:#fb7185;--violet:#a78bfa;
    --r:14px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:var(--bg);color:var(--text);font-family:'Tajawal',sans-serif}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
 background:radial-gradient(ellipse 80% 50% at 15% 10%,rgba(56,189,248,.08) 0%,transparent 60%),
             radial-gradient(ellipse 60% 70% at 85% 88%,rgba(167,139,250,.07) 0%,transparent 60%)}

.wrap{position:relative;z-index:1;max-width:1060px;margin:0 auto;padding:20px 16px 100px}

/* ── header ── */
.hd{text-align:center;padding:16px 0 28px}
.hd-title{font-size:1.6rem;font-weight:900;
 background:linear-gradient(120deg,var(--gold),var(--sky),var(--violet));
 -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:6px}
.hd-sub{font-size:.82rem;color:var(--muted)}

/* ── progress ── */
.prog{height:10px;background:rgba(255,255,255,.07);border-radius:50px;overflow:hidden;margin-bottom:22px}
.prog-bar{height:100%;border-radius:50px;
 background:linear-gradient(90deg,var(--sky),var(--violet));transition:width .6s ease}

/* ── stats ── */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:22px}
@media(max-width:560px){.stats{grid-template-columns:repeat(2,1fr)}}
.scard{background:var(--card);border:1px solid var(--border);border-radius:var(--r);
 padding:14px;text-align:center}
.sn{font-size:1.55rem;font-weight:900;line-height:1}
.sl{font-size:.68rem;color:var(--muted);margin-top:4px}

/* ── big action buttons ── */
.action-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px}
@media(max-width:560px){.action-grid{grid-template-columns:1fr}}
.abtn{
 border:none;border-radius:var(--r);padding:18px 16px;cursor:pointer;
 font-family:'Tajawal',sans-serif;font-size:.95rem;font-weight:800;
 display:flex;align-items:center;justify-content:center;gap:10px;
 transition:all .22s;text-align:center;line-height:1.3
}
.abtn:hover{transform:translateY(-3px);filter:brightness(1.1)}
.abtn-primary{
 background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;
 box-shadow:0 6px 24px rgba(14,165,233,.3)
}
.abtn-warning{
 background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;
 box-shadow:0 6px 24px rgba(245,158,11,.3)
}
.abtn-clear{
 background:rgba(251,113,133,.1);color:var(--rose);
 border:1px solid rgba(251,113,133,.3);grid-column:span 2
}
@media(max-width:560px){.abtn-clear{grid-column:span 1}}

/* ── result msg ── */
.rmsg{padding:14px 18px;border-radius:var(--r);margin-bottom:18px;font-weight:700;font-size:.92rem}
.rmsg.ok  {background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:var(--green)}
.rmsg.warn{background:rgba(251,113,133,.12);border:1px solid rgba(251,113,133,.3);color:var(--rose)}
.rmsg.info{background:rgba(56,189,248,.1); border:1px solid rgba(56,189,248,.3); color:var(--sky)}

/* ── filter toolbar ── */
.ftb{
 background:var(--card);border:1px solid var(--border);border-radius:var(--r);
 padding:12px 14px;margin-bottom:14px;
 display:flex;gap:8px;flex-wrap:wrap;align-items:center
}
.ftb input{
 flex:1;min-width:160px;background:rgba(255,255,255,.06);
 border:1px solid var(--border);border-radius:50px;
 padding:8px 16px;color:var(--text);font-family:'Tajawal',sans-serif;
 font-size:.85rem;outline:none;transition:border-color .2s
}
.ftb input:focus{border-color:rgba(56,189,248,.5)}
.ftb input::placeholder{color:var(--muted)}
.ftab{
 padding:7px 14px;border-radius:50px;border:1px solid var(--border);
 background:rgba(255,255,255,.05);color:var(--text);
 font-family:'Tajawal',sans-serif;font-size:.8rem;cursor:pointer;
 text-decoration:none;white-space:nowrap;transition:all .17s
}
.ftab:hover,.ftab.on{background:rgba(255,255,255,.14);color:#fff}
.ftab.on{border-color:var(--sky);color:var(--sky)}

/* ── table ── */
.tbl-wrap{background:var(--card);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;overflow-x:auto}
.tbl{width:100%;border-collapse:collapse;min-width:540px}
.tbl thead tr{background:rgba(0,0,0,.28)}
.tbl th{
 padding:11px 14px;font-size:.72rem;font-weight:700;color:var(--muted);
 text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)
}
.tbl td{padding:9px 14px;border-bottom:1px solid rgba(255,255,255,.035);vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:rgba(255,255,255,.035)}

.col-id{width:50px;text-align:center;color:var(--muted);font-size:.8rem}
.col-ku{font-size:.9rem;font-weight:700}
.col-preview{font-size:.83rem;color:var(--gold);font-family:'Inter',sans-serif;font-style:italic}
.col-en input{
 width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);
 border-radius:8px;padding:7px 10px;color:var(--text);
 font-family:'Inter','Tajawal',sans-serif;font-size:.85rem;outline:none;
 transition:border-color .18s,background .18s
}
.col-en input:focus{border-color:var(--sky);background:rgba(56,189,248,.07)}
.col-en input.has{border-color:rgba(52,211,153,.5);color:var(--green);background:rgba(52,211,153,.04)}
.col-en input.edited{border-color:rgba(246,201,65,.5);color:var(--gold)}

.badge-ok  {display:inline-block;padding:3px 9px;border-radius:20px;font-size:.65rem;font-weight:700;background:rgba(52,211,153,.15);color:var(--green)}
.badge-miss{display:inline-block;padding:3px 9px;border-radius:20px;font-size:.65rem;font-weight:700;background:rgba(251,113,133,.13);color:var(--rose)}

/* ── sticky save ── */
.sticky{
 position:fixed;bottom:0;left:0;right:0;z-index:300;
 background:rgba(7,9,15,.94);backdrop-filter:blur(16px);
 border-top:1px solid var(--border);
 padding:12px 20px;display:flex;justify-content:center;align-items:center;gap:14px;flex-wrap:wrap
}
.sticky-lbl{font-size:.84rem;color:var(--muted)}
.sticky-lbl b{color:var(--gold)}
.sbtn{
 border:none;border-radius:10px;padding:10px 22px;cursor:pointer;
 font-family:'Tajawal',sans-serif;font-size:.88rem;font-weight:700;
 display:flex;align-items:center;gap:7px;transition:all .2s
}
.sbtn-save{background:linear-gradient(135deg,var(--green),#059669);color:#fff;box-shadow:0 4px 16px rgba(52,211,153,.25)}
.sbtn-home{background:rgba(255,255,255,.07);color:var(--text);border:1px solid var(--border)}
.sbtn:hover{transform:translateY(-2px)}

::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:4px}
</style>
</head>
<body>
<div class="wrap">

<!-- HEADER -->
<div class="hd">
    <div class="hd-title">🔤 تعبئة الأسماء الإنجليزية</div>
    <div class="hd-sub">تحويل تلقائي للأسماء الكردية/العربية → إنجليزية وحفظها في قاعدة البيانات</div>
</div>

<!-- PROGRESS -->
<div class="prog">
    <div class="prog-bar" style="width:<?= $pct ?>%"></div>
</div>

<!-- STATS -->
<div class="stats">
    <div class="scard">
        <div class="sn" style="color:var(--sky)"><?= $total ?></div>
        <div class="sl">إجمالي الأشخاص</div>
    </div>
    <div class="scard">
        <div class="sn" style="color:var(--green)"><?= $filled ?></div>
        <div class="sl">✅ لديهم اسم إنجليزي</div>
    </div>
    <div class="scard">
        <div class="sn" style="color:var(--rose)"><?= $empty ?></div>
        <div class="sl">⚠ بدون اسم إنجليزي</div>
    </div>
    <div class="scard">
        <div class="sn" style="color:var(--violet)"><?= $pct ?>%</div>
        <div class="sl">نسبة الإنجاز</div>
    </div>
</div>

<?php if ($result_msg): ?>
<div class="rmsg <?= $result_type ?>"><?= htmlspecialchars($result_msg) ?></div>
<?php endif; ?>

<!-- ACTION BUTTONS -->
<div class="action-grid">
    <!-- ملء الفارغ فقط -->
    <form method="POST">
        <input type="hidden" name="action" value="fill_empty">
        <button type="submit" class="abtn abtn-primary" style="width:100%"
                onclick="return confirm('سيتم تعبئة <?= $empty ?> اسم فارغ تلقائياً. هل تريد المتابعة؟')">
            <i class="fas fa-wand-magic-sparkles" style="font-size:1.2rem"></i>
            <div>
                <div>تعبئة الأسماء الفارغة</div>
                <div style="font-size:.75rem;font-weight:500;opacity:.8"><?= $empty ?> اسم بدون إنجليزية</div>
            </div>
        </button>
    </form>

    <!-- تحديث الكل (استبدال) -->
    <form method="POST">
        <input type="hidden" name="action" value="fill_all">
        <button type="submit" class="abtn abtn-warning" style="width:100%"
                onclick="return confirm('سيتم إعادة تعبئة جميع الـ <?= $total ?> اسم (بما فيها الموجودة). هل أنت متأكد؟')">
            <i class="fas fa-rotate" style="font-size:1.2rem"></i>
            <div>
                <div>إعادة تعبئة الكل</div>
                <div style="font-size:.75rem;font-weight:500;opacity:.8">يستبدل <?= $total ?> اسم</div>
            </div>
        </button>
    </form>

    <!-- مسح الكل -->
    <form method="POST">
        <input type="hidden" name="action" value="clear_all">
        <button type="submit" class="abtn abtn-clear"
                onclick="return confirm('تحذير: سيتم مسح جميع الأسماء الإنجليزية من قاعدة البيانات!')">
            <i class="fas fa-eraser"></i> مسح جميع الأسماء الإنجليزية
        </button>
    </form>
</div>

<!-- FILTER & SEARCH -->
<div class="ftb">
    <form method="GET" style="display:contents">
        <input type="text" name="q" placeholder="🔍 بحث بالاسم الكردي..." value="<?= htmlspecialchars($search) ?>">
        <input type="hidden" name="show" value="<?= htmlspecialchars($show) ?>">
        <button type="submit" class="ftab">بحث</button>
    </form>
    <a href="?show=empty&q=<?= urlencode($search) ?>"  class="ftab <?= $show==='empty' ?'on':'' ?>">⚠ فارغ (<?= $empty ?>)</a>
    <a href="?show=filled&q=<?= urlencode($search) ?>" class="ftab <?= $show==='filled'?'on':'' ?>">✅ مكتمل (<?= $filled ?>)</a>
    <a href="?show=all&q=<?= urlencode($search) ?>"    class="ftab <?= $show==='all'   ?'on':'' ?>">الكل (<?= $total ?>)</a>
    <a href="index.php" class="ftab"><i class="fas fa-home"></i></a>
</div>

<!-- TABLE -->
<form method="POST" id="editForm">
<input type="hidden" name="action" value="save_manual">

<div class="tbl-wrap">
<table class="tbl">
    <thead>
        <tr>
            <th class="col-id">#</th>
            <th>الاسم الكردي</th>
            <th>معاينة التحويل التلقائي</th>
            <th>الاسم الإنجليزي <small style="opacity:.5">(قابل للتعديل)</small></th>
            <th style="width:80px;text-align:center">حالة</th>
        </tr>
    </thead>
    <tbody>
    <?php
    foreach ($people as $p):
        $has   = !empty($p['full_name_en']);
        $auto  = translitKu($p['full_name']);
        $saved = $has ? $p['full_name_en'] : '';
    ?>
    <tr>
        <td class="col-id"><?= $p['id'] ?></td>
        <td class="col-ku"><?= htmlspecialchars($p['full_name']) ?></td>
        <td class="col-preview"><?= htmlspecialchars($auto ?: '—') ?></td>
        <td class="col-en">
            <input type="text"
                   name="names[<?= $p['id'] ?>]"
                   value="<?= htmlspecialchars($saved) ?>"
                   class="<?= $has ? 'has' : '' ?>"
                   placeholder="<?= htmlspecialchars($auto) ?>"
                   oninput="this.classList.add('edited'); countEdits()">
        </td>
        <td style="text-align:center">
            <?php if ($has): ?>
                <span class="badge-ok">✅</span>
            <?php else: ?>
                <span class="badge-miss">فارغ</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- STICKY SAVE -->
<div class="sticky">
    <span class="sticky-lbl">تعديلات يدوية: <b id="edit-count">0</b></span>
    <button type="submit" class="sbtn sbtn-save" onclick="return checkEdits()">
        <i class="fas fa-floppy-disk"></i> حفظ التعديلات اليدوية
    </button>
    <button type="button" class="sbtn sbtn-home" onclick="location.href='index.php'">
        <i class="fas fa-home"></i>
    </button>
</div>
</form>

</div><!-- /wrap -->

<script>
let editCount = 0;
function countEdits() {
    editCount = document.querySelectorAll('.edited').length;
    document.getElementById('edit-count').textContent = editCount;
}
function checkEdits() {
    if (editCount === 0) {
        alert('⚠ لم تقم بأي تعديل يدوي! استخدم أزرار التعبئة التلقائية أعلاه لحفظ كل الأسماء.');
        return false;
    }
    return confirm(`حفظ ${editCount} تعديل يدوي في قاعدة البيانات؟`);
}
</script>
</body>
</html>
