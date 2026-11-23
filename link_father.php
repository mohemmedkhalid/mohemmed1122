<?php
require_once 'config.php';

$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // تحديد ما إذا كان الطلب قادماً من AJAX
    $is_ajax = isset($_POST['ajax']);
    $response = ['status' => 'error', 'message' => 'حدث خطأ غير معروف'];
    
    // --- 1. ربط الأب بالابن ---
    if ($_POST['action'] === 'link') {
        $child_id  = (int)$_POST['child_id'];
        $father_id = (int)$_POST['father_id'];

        if ($child_id && $father_id && $child_id !== $father_id) {
            $stmt = $mysqli->prepare("UPDATE people2 SET father_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $father_id, $child_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '✅ تم الربط بنجاح!'];
            } else {
                $response['message'] = "❌ خطأ: " . $mysqli->error;
            }
            $stmt->close();
        } else {
            $response['message'] = "⚠️ اختار شخصين مختلفين!";
        }
    }

    // --- 2. فك الربط ---
    if ($_POST['action'] === 'unlink') {
        $child_id = (int)$_POST['child_id'];
        if ($child_id) {
            $stmt = $mysqli->prepare("UPDATE people2 SET father_id = NULL WHERE id = ?");
            $stmt->bind_param("i", $child_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '🔓 تم فك الربط!'];
            }
            $stmt->close();
        }
    }

    // --- 3. تعديل الاسم (للابن أو الأب) ---
    if ($_POST['action'] === 'edit_name') {
        $person_id = (int)$_POST['person_id'];
        $new_name  = trim($_POST['new_name']);
        
        if ($person_id && $new_name !== '') {
            $stmt = $mysqli->prepare("UPDATE people2 SET full_name = ? WHERE id = ?");
            $stmt->bind_param("si", $new_name, $person_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '📝 تم تعديل الاسم بنجاح!'];
            } else {
                $response['message'] = "❌ خطأ في تعديل الاسم: " . $mysqli->error;
            }
            $stmt->close();
        }
    }

    // --- 4b. ربط الأم بالابن ---
    if ($_POST['action'] === 'link_mother') {
        $child_id  = (int)$_POST['child_id'];
        $mother_id = (int)$_POST['mother_id'];
        if ($child_id && $mother_id && $child_id !== $mother_id) {
            $stmt = $mysqli->prepare("UPDATE people2 SET mother_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $mother_id, $child_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '✅ تم ربط الأم بنجاح!'];
            } else {
                $response['message'] = "❌ خطأ: " . $mysqli->error;
            }
            $stmt->close();
        } else {
            $response['message'] = "⚠️ اختار شخصين مختلفين!";
        }
    }

    // --- 4c. فك ربط الأم ---
    if ($_POST['action'] === 'unlink_mother') {
        $child_id = (int)$_POST['child_id'];
        if ($child_id) {
            $stmt = $mysqli->prepare("UPDATE people2 SET mother_id = NULL WHERE id = ?");
            $stmt->bind_param("i", $child_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '🔓 تم فك ربط الأم!'];
            }
            $stmt->close();
        }
    }

    // --- 4d. حفظ الملاحظة ---
    if ($_POST['action'] === 'save_note') {
        $person_id = (int)$_POST['person_id'];
        $note      = trim($_POST['note']);
        if ($person_id) {
            // نحاول UPDATE أولاً، إذا لم يوجد العمود نتجاهل الخطأ
            $stmt = $mysqli->prepare("UPDATE people2 SET notes = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $note, $person_id);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => '📝 تم حفظ الملاحظة!'];
                } else {
                    $response['message'] = "❌ خطأ: " . $mysqli->error;
                }
                $stmt->close();
            } else {
                $response['message'] = "❌ عمود الملاحظات غير موجود. يرجى تشغيل: ALTER TABLE people2 ADD COLUMN notes TEXT NULL, ADD COLUMN mother_id INT NULL;";
            }
        }
    }

    // --- 4. تعديل الجنس (جديد) ---
    if ($_POST['action'] === 'edit_gender') {
        $person_id = (int)$_POST['person_id'];
        $new_gender  = trim($_POST['new_gender']);
        
        if ($person_id && in_array($new_gender, ['Male', 'Female'])) {
            $stmt = $mysqli->prepare("UPDATE people2 SET gender = ? WHERE id = ?");
            $stmt->bind_param("si", $new_gender, $person_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => '🚻 تم تعديل الجنس بنجاح!'];
            } else {
                $response['message'] = "❌ خطأ في تعديل الجنس: " . $mysqli->error;
            }
            $stmt->close();
        }
    }

    // إذا كان الطلب AJAX، نطبع الـ JSON ونوقف تنفيذ باقي الصفحة
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// الترتيب: التحقق من طلب الترتيب حسب الـ ID أو الاسم
$sort_by = isset($_GET['sort']) && $_GET['sort'] === 'id' ? 'p.id DESC' : 'p.full_name ASC';

// التحقق من وجود أعمدة mother_id و notes
$has_mother_col = false;
$has_notes_col  = false;
$col_check = $mysqli->query("SHOW COLUMNS FROM people2 LIKE 'mother_id'");
if ($col_check && $col_check->num_rows > 0) $has_mother_col = true;
$col_check2 = $mysqli->query("SHOW COLUMNS FROM people2 LIKE 'notes'");
if ($col_check2 && $col_check2->num_rows > 0) $has_notes_col = true;

// بناء SQL ديناميكياً بحسب الأعمدة المتاحة
$mother_select = $has_mother_col ? ", p.mother_id, m.full_name AS mother_name, m.full_name_en AS mother_name_en" : ", NULL AS mother_id, NULL AS mother_name, NULL AS mother_name_en";
$mother_join   = $has_mother_col ? "LEFT JOIN people2 m ON p.mother_id = m.id" : "";
$notes_select  = $has_notes_col  ? ", p.notes" : ", NULL AS notes";

$sql = "
    SELECT 
        p.id,
        p.full_name,
        p.full_name_en,
        p.gender,
        p.father_id,
        f.full_name  AS father_name,
        f.full_name_en AS father_name_en,
        gf.full_name AS grandfather_name,
        gf.full_name_en AS grandfather_name_en
        $mother_select
        $notes_select
    FROM people2 p
    LEFT JOIN people2 f  ON p.father_id = f.id
    LEFT JOIN people2 gf ON f.father_id = gf.id
    $mother_join
    ORDER BY $sort_by
";
$res = $mysqli->query($sql);
$all_people = [];
while ($row = $res->fetch_assoc()) {
    $all_people[$row['id']] = $row;
}

// بناء خريطة الأبناء: لكل أب، اجمع أبناءه
$children_map = []; // father_id => [child_names...]
foreach ($all_people as $p) {
    if (!is_null($p['father_id'])) {
        $children_map[$p['father_id']][] = get_person_name($p);
    }
}

// قائمة الإناث للأم
$mothers_list = array_filter($all_people, fn($p) => $p['gender'] === 'Female');

// دالة بناء الاسم في القائمة المنسدلة
function display_name_for_select($p) {
    $name = get_person_name($p);
    $chain = [];
    if (!empty($p['father_name'])) {
        $chain[] = get_person_name(['full_name' => $p['father_name'], 'full_name_en' => $p['father_name_en']]);
        if (!empty($p['grandfather_name'])) {
            $chain[] = get_person_name(['full_name' => $p['grandfather_name'], 'full_name_en' => $p['grandfather_name_en']]);
        }
    }
    
    if (!empty($chain)) {
        return $name . ' ← ' . implode(' ← ', $chain);
    } else {
        return $name . '  [#' . $p['id'] . '] ⚠';
    }
}

// الفلتر والبحث
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'no_father';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

$children_list = [];
foreach ($all_people as $p) {
    if ($filter === 'no_father' && !is_null($p['father_id'])) continue;
    if ($search) {
        $name = get_person_name($p);
        $father = get_person_name(['full_name' => $p['father_name'], 'full_name_en' => $p['father_name_en']]);
        $grandfather = get_person_name(['full_name' => $p['grandfather_name'], 'full_name_en' => $p['grandfather_name_en']]);
        $hay = $name . ($father ?? '') . ($grandfather ?? '');
        if (mb_strpos($hay, $search) === false) continue;
    }
    $children_list[] = $p;
}

$fathers_list = array_filter($all_people, fn($p) => $p['gender'] === 'Male');
$total = count($all_people);
$no_father_count = count(array_filter($all_people, fn($p) => is_null($p['father_id'])));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ربط الأب بالابن</title>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    color: #e0e0e0;
    padding: 20px;
}
h1 { text-align:center; font-size:1.75rem; margin-bottom:6px; color:#64b5f6; }
.subtitle { text-align:center; font-size:0.88rem; color:#78909c; margin-bottom:18px; }
.top-nav { text-align:center; margin-bottom:20px; }
.top-nav a {
    display:inline-block; background:rgba(255,255,255,0.07); color:#aaa;
    text-decoration:none; padding:7px 15px; border-radius:8px;
    border:1px solid rgba(255,255,255,0.12); margin:0 4px; font-size:0.87rem; transition:0.2s;
}
.top-nav a:hover { background:rgba(255,255,255,0.13); color:#fff; }
.alert { padding:11px 18px; border-radius:10px; margin:0 auto 16px; font-weight:bold; text-align:center; max-width:1100px; }
.alert.success { background:rgba(76,175,80,0.18); border:1px solid #4caf50; color:#a5d6a7; }
.alert.error   { background:rgba(244,67,54,0.18); border:1px solid #f44336; color:#ef9a9a; }
.alert.warning { background:rgba(255,193,7,0.18);  border:1px solid #ffc107; color:#ffe082; }

.info-box {
    max-width:1100px; margin:0 auto 16px;
    background:rgba(255,193,7,0.07); border:1px solid rgba(255,193,7,0.22);
    border-radius:12px; padding:13px 17px; font-size:0.88rem; color:#ffe082; line-height:1.9;
}
.info-box strong { color:#ffd54f; }

.quick-form {
    background:rgba(100,181,246,0.07); border:1px solid rgba(100,181,246,0.2);
    border-radius:14px; padding:17px 19px; margin:0 auto 17px; max-width:1100px;
}
.quick-form h2 { font-size:1rem; color:#64b5f6; margin-bottom:13px; }
.form-row { display:flex; gap:11px; align-items:flex-end; flex-wrap:wrap; }
.form-group { flex:1; min-width:250px; }
.form-group label { display:block; font-size:0.8rem; color:#90a4ae; margin-bottom:5px; }

input[type="text"] {
    width:100%; background:rgba(15,22,44,0.88);
    border:1px solid rgba(255,255,255,0.17); border-radius:8px;
    color:#e0e0e0; padding:8px 10px; font-size:0.88rem; outline:none; transition:border-color 0.2s;
}
input[type="text"]:focus { border-color:#64b5f6; }

.select2-container--default .select2-selection--single {
    background-color: rgba(15,22,44,0.88) !important;
    border: 1px solid rgba(255,255,255,0.17) !important;
    border-radius: 8px !important;
    height: 36px !important;
    color: #e0e0e0 !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #e0e0e0 !important;
    line-height: 34px !important;
}
.select2-dropdown {
    background-color: #16213e !important;
    border: 1px solid #64b5f6 !important;
    color: #e0e0e0;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #1565c0 !important;
}
.select2-search input { background-color: #0f3460 !important; color: white !important; border-radius:4px; }

.btn { padding:8px 19px; border:none; border-radius:8px; font-size:0.9rem; font-weight:bold; cursor:pointer; transition:0.2s; white-space:nowrap; height:36px; }
.btn-link { background:#1565c0; color:#fff; }
.btn-link:hover { background:#1976d2; transform:translateY(-1px); }
.btn-unlink { background:rgba(244,67,54,0.16); color:#ef9a9a; border:1px solid rgba(244,67,54,0.3); font-size:0.77rem; padding:4px 10px; border-radius:6px; cursor:pointer; }
.btn-unlink:hover { background:rgba(244,67,54,0.28); }
.btn-edit { background:none; border:none; cursor:pointer; font-size:0.9rem; margin-right:5px; opacity:0.7; transition:0.2s; }
.btn-edit:hover { opacity:1; transform:scale(1.1); }

.panel { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.09); border-radius:14px; padding:17px 19px;  margin:0 auto; }
.panel-header { display:flex; align-items:center; flex-wrap:wrap; gap:9px; margin-bottom:13px; }
.panel-header h2 { font-size:1rem; color:#90caf9; }
.count-badge { background:rgba(100,181,246,0.17); color:#64b5f6; border-radius:20px; padding:2px 9px; font-size:0.77rem; }
.warn-badge  { background:rgba(255,193,7,0.17);  color:#ffe082; border-radius:20px; padding:2px 9px; font-size:0.77rem; }
.search-filter { display:flex; gap:8px; margin-bottom:13px; flex-wrap:wrap; align-items:center; }
.search-input { flex:1; min-width:130px; padding:7px 10px; font-size:0.86rem; }
.btn-filter { background:rgba(255,255,255,0.07); color:#aaa; border:1px solid rgba(255,255,255,0.12); padding:6px 13px; border-radius:7px; cursor:pointer; font-size:0.82rem; transition:0.2s; }
.btn-filter.active { background:#1565c0; color:#fff; border-color:#1976d2; }

.table-wrap { overflow-x:auto; max-height: 600px; } 
table { width:100%; border-collapse:collapse; font-size:0.86rem; }
thead th { 
    background:#16213e; padding:10px 9px; text-align:right; color:#90caf9; font-weight:bold; 
    border-bottom:2px solid rgba(255,255,255,0.1); white-space:nowrap; 
    position: sticky; top: 0; z-index: 10; 
}
tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); transition:background 0.12s; }
tbody tr:hover { background:rgba(255,255,255,0.08); }
tbody td { padding:7px 9px; vertical-align:middle; }
.badge { display:inline-block; font-size:0.7rem; border-radius:5px; padding:2px 7px; white-space:nowrap; }
.badge-linked   { background:rgba(76,175,80,0.14); color:#a5d6a7; border:1px solid rgba(76,175,80,0.22); }
.badge-unlinked { background:rgba(255,193,7,0.14); color:#ffe082; border:1px solid rgba(255,193,7,0.22); }
.id-badge { display:inline-block; font-size:0.67rem; background:rgba(255,255,255,0.05); color:#546e7a; border-radius:4px; padding:1px 5px; margin-right:3px; }
.chain { font-size:0.77rem; margin-top:2px; display: flex; align-items: center; gap: 3px;}
.chain .me { color:#80cbc4; }
.chain .sep { color:#37474f; }
.chain .fa { color:#a5d6a7; }
.chain .gfa { color:#78909c; }
.inline-form { display:flex; gap:5px; align-items:center; }
.inline-form select { min-width:200px; }
.inline-btn { background:#1565c0; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:0.77rem; cursor:pointer; white-space:nowrap; transition:0.15s; }
.inline-btn:hover { background:#1976d2; }

/* أبناء تحت الاسم */
.children-list { margin-top:5px; display:flex; flex-wrap:wrap; gap:4px; }
.child-tag { background:rgba(100,181,246,0.1); border:1px solid rgba(100,181,246,0.2); color:#90caf9; border-radius:4px; padding:2px 7px; font-size:0.7rem; white-space:nowrap; }
.child-toggle { background:none; border:none; color:#546e7a; font-size:0.7rem; cursor:pointer; text-decoration:underline; margin-top:3px; display:block; }
.child-toggle:hover { color:#90caf9; }

/* عمود الأم */
.mother-cell { font-size:0.8rem; }
.mother-link { color:#f48fb1; }
.btn-mother-unlink { background:rgba(244,67,54,0.12); color:#ef9a9a; border:1px solid rgba(244,67,54,0.25); font-size:0.7rem; padding:2px 7px; border-radius:5px; cursor:pointer; }
.btn-mother-unlink:hover { background:rgba(244,67,54,0.22); }

/* عمود الملاحظات */
.note-text { font-size:0.78rem; color:#ffe082; max-width:150px; word-break:break-word; }
.note-empty { color:#37474f; font-style:italic; font-size:0.75rem; }
.btn-note { background:none; border:none; cursor:pointer; font-size:0.85rem; opacity:0.65; transition:0.2s; }
.btn-note:hover { opacity:1; }
</style>
</head>
<body>
<div id="toast" style="display:none; position:fixed; bottom:20px; right:20px; padding:12px 25px; border-radius:8px; color:#fff; font-weight:bold; z-index:9999; box-shadow: 0 4px 6px rgba(0,0,0,0.3);"></div>
<h1>🔗 ربط الأب بالابن</h1>
<p class="subtitle">إجمالي: <?php echo $total; ?> | بدون أب: <strong style="color:#ffe082"><?php echo $no_father_count; ?></strong></p>

<div class="top-nav">
    <a href="index.php">🏠 الرئيسية</a>
    <a href="family_list.php">📋 القائمة</a>
    <a href="tree.php">🌳 الشجرة</a>
</div>

<div class="info-box">
    <strong>💡 تلميحات مفيدة:</strong><br>
    ✏️ يمكنك <strong>تعديل الاسم</strong> بالضغط على (✏️) بجانب اسم الشخص أو اسم الأب.<br>
    🔄 يمكنك <strong>تعديل الجنس</strong> بالضغط على (🔄) بجانب الجنس الحالي.<br>
    👩 يمكنك <strong>ربط الأم</strong> من عمود "الأم" في الجدول.<br>
    👨‍👧 يمكنك <strong>إظهار الأبناء</strong> بالضغط على "▼ إظهار" تحت اسم كل شخص.<br>
    📝 يمكنك إضافة <strong>ملاحظة</strong> لأي شخص من عمود الملاحظات.<br>
    🔍 يمكنك <strong>البحث</strong> داخل القوائم المنسدلة بالاسم، أو اسم الأب، أو الـ ID.
    <?php if (!$has_mother_col || !$has_notes_col): ?>
    <br><br>
    <span style="color:#ef9a9a;">⚠️ <strong>تنبيه:</strong> لتفعيل ميزة الأم والملاحظات، شغّل هذا الاستعلام في قاعدة البيانات:</span><br>
    <code style="background:rgba(0,0,0,0.3);padding:4px 8px;border-radius:5px;font-size:0.82rem;display:inline-block;margin-top:5px;">
        ALTER TABLE people2 ADD COLUMN mother_id INT NULL, ADD COLUMN notes TEXT NULL;
    </code>
    <?php endif; ?>
</div>

<div class="quick-form">
    <h2>⚡ ربط سريع — اختار الابن والأب واضغط ربط</h2>
    <form method="post">
        <input type="hidden" name="action" value="link">
        <div class="form-row">
            <div class="form-group">
                <label>👤 الابن / البنت</label>
                <select name="child_id" class="searchable-select" required>
                    <option value="">-- ابحث واختار الشخص --</option>
                    <?php foreach ($all_people as $p):
                        $label = display_name_for_select($p);
                    ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>👨 الأب</label>
                <select name="father_id" class="searchable-select" required>
                    <option value="">-- ابحث واختار الأب --</option>
                    <?php foreach ($fathers_list as $p):
                        $label = display_name_for_select($p);
                    ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-link">🔗 ربط</button>
            </div>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>📋 قائمة الأشخاص</h2>
        <span class="count-badge"><?php echo count($children_list); ?> نتيجة</span>
        <?php if ($filter==='no_father'): ?>
        <span class="warn-badge">⚠ بدون أب فقط</span>
        <?php endif; ?>
    </div>

    <form method="get">
        <div class="search-filter">
            <input type="text" name="search" class="search-input"
                   placeholder="🔍 ابحث بالاسم أو اسم الأب..."
                   value="<?php echo htmlspecialchars($search); ?>">
            
            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
            <input type="hidden" name="sort" value="<?php echo $current_sort; ?>" id="sort_input">

            <button type="button" onclick="window.location.href='?filter=no_father&sort=<?php echo $current_sort; ?>&search=<?php echo urlencode($search); ?>'" 
                    class="btn-filter <?php echo $filter==='no_father'?'active':''; ?>">
                ⚠️ بدون أب
            </button>
            <button type="button" onclick="window.location.href='?filter=all&sort=<?php echo $current_sort; ?>&search=<?php echo urlencode($search); ?>'" 
                    class="btn-filter <?php echo $filter==='all'?'active':''; ?>">
                👥 الكل
            </button>

            <span style="color:#546e7a; margin: 0 5px;">|</span>

            <button type="button" onclick="window.location.href='?filter=<?php echo $filter; ?>&sort=id&search=<?php echo urlencode($search); ?>'" 
                    class="btn-filter <?php echo $current_sort==='id'?'active':''; ?>">
                ⬇️ الأحدث (ID)
            </button>
            <button type="button" onclick="window.location.href='?filter=<?php echo $filter; ?>&sort=name&search=<?php echo urlencode($search); ?>'" 
                    class="btn-filter <?php echo $current_sort==='name'?'active':''; ?>">
                🔠 أبجدياً
            </button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الشخص <span style="color:#78909c;font-size:0.75rem;">(#ID)</span></th>
                    <th>السلسلة الحالية عبر الربط</th>
                    <th>الأبناء</th>
                    <th>الأم</th>
                    <th>الملاحظات</th>
                    <th>الحالة</th>
                    <th>ربط بأب جديد</th>
                    <th>فك</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($children_list)): ?>
                <tr><td colspan="9" style="text-align:center;padding:25px;color:#546e7a;">لا يوجد نتائج</td></tr>
            <?php else: ?>
            <?php $i=1; foreach ($children_list as $p): ?>
            <tr>
                <td style="color:#37474f;font-size:0.77rem;"><?php echo $i++; ?></td>

                <td>
                    <?php $pname = get_person_name($p); ?>
                    <strong><?php echo htmlspecialchars($pname); ?></strong>
                    <button class="btn-edit" title="تعديل الاسم" onclick="editName(<?php echo $p['id']; ?>, '<?php echo addslashes(htmlspecialchars($pname)); ?>', this)">✏️</button>                    
                    <span class="id-badge">#<?php echo $p['id']; ?></span><br>
                    
                    <span style="font-size:0.74rem;color:#546e7a;" id="gender-text-<?php echo $p['id']; ?>">
                        <?php echo $p['gender']==='Male'?'🟦 ذكر' : '🟥 أنثى'; ?>
                    </span>
                    <button class="btn-edit" style="font-size:0.75rem;" title="تغيير الجنس" onclick="editGender(<?php echo $p['id']; ?>, '<?php echo $p['gender']; ?>', this)">🔄</button>
                </td>

                <td>
                    <?php if (!empty($p['father_name'])): ?>
                    <div class="chain">
                        <?php 
                           $pname = get_person_name($p);
                           $fname = get_person_name(['full_name' => $p['father_name'], 'full_name_en' => $p['father_name_en']]);
                           $gname = get_person_name(['full_name' => $p['grandfather_name'], 'full_name_en' => $p['grandfather_name_en']]);
                        ?>
                        <span class="me"><?php echo htmlspecialchars($pname); ?></span>
                        <span class="sep"> ← </span>
                        
                        <span class="fa"><?php echo htmlspecialchars($fname); ?></span>
                        <button class="btn-edit" style="font-size:0.75rem;" title="تعديل اسم الأب في قاعدة البيانات" onclick="editName(<?php echo $p['father_id']; ?>, '<?php echo addslashes(htmlspecialchars($fname)); ?>', this)">✏️</button>

                        <?php if (!empty($p['grandfather_name'])): ?>
                        <span class="sep"> ← </span>
                        <span class="gfa"><?php echo htmlspecialchars($gname); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:#37474f;font-style:italic;font-size:0.8rem;">لا يوجد ربط</span>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if ($p['father_id']): ?>
                        <span class="badge badge-linked">✓ مربوط</span>
                    <?php else: ?>
                        <span class="badge badge-unlinked">⚠ بدون أب</span>
                    <?php endif; ?>
                </td>

                <!-- عمود الأبناء -->
                <td>
                    <?php 
                    $kids = $children_map[$p['id']] ?? [];
                    if (!empty($kids)):
                        $total_kids = count($kids);
                    ?>
                    <span style="font-size:0.72rem;color:#546e7a;"><?php echo $total_kids; ?> أبناء</span>
                    <button class="child-toggle" onclick="toggleChildren(this)">▼ إظهار</button>
                    <div class="children-list" style="display:none;">
                        <?php foreach($kids as $kname): ?>
                        <span class="child-tag"><?php echo htmlspecialchars($kname); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span class="note-empty">—</span>
                    <?php endif; ?>
                </td>

                <!-- عمود الأم -->
                <td class="mother-cell">
                    <?php if (!empty($p['mother_name'])): ?>
                    <?php $mname = get_person_name(['full_name' => $p['mother_name'], 'full_name_en' => $p['mother_name_en']]); ?>
                    <span class="mother-link">👩 <?php echo htmlspecialchars($mname); ?></span><br>
                    <button class="btn-mother-unlink" onclick="unlinkMother(<?php echo $p['id']; ?>, this)">فك</button>
                    <?php else: ?>
                    <form class="inline-form mother-form" style="flex-direction:column;gap:3px;">
                        <input type="hidden" name="action" value="link_mother">
                        <input type="hidden" name="child_id" value="<?php echo $p['id']; ?>">
                        <select name="mother_id" class="searchable-select-mother" style="min-width:140px;" data-pid="<?php echo $p['id']; ?>">
                            <option value="">-- اختري الأم --</option>
                            <?php foreach ($all_people as $f):
                                if ($f['id']===$p['id']) continue;
                            ?>
                            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars(get_person_name($f)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="inline-btn" style="background:#ad1457;" onclick="linkMother(this)">ربط</button>
                    </form>
                    <?php endif; ?>
                </td>

                <!-- عمود الملاحظات -->
                <td>
                    <span class="note-text" id="note-<?php echo $p['id']; ?>">
                        <?php echo !empty($p['notes']) ? htmlspecialchars($p['notes']) : '<span class="note-empty">—</span>'; ?>
                    </span>
                    <button class="btn-note" onclick="editNote(<?php echo $p['id']; ?>, this)" title="تعديل الملاحظة">📝</button>
                </td>

                <td>
                    <?php if ($p['father_id']): ?>
                        <span class="badge badge-linked">✓ مربوط</span>
                    <?php else: ?>
                        <span class="badge badge-unlinked">⚠ بدون أب</span>
                    <?php endif; ?>
                </td>

                <td>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="link">
                        <input type="hidden" name="child_id" value="<?php echo $p['id']; ?>">
                        <select name="father_id" class="searchable-select" required>
                            <option value="">-- ابحث عن الأب --</option>
                            <?php foreach ($fathers_list as $f):
                                if ($f['id']===$p['id']) continue;
                                $flabel = display_name_for_select($f);
                            ?>
                            <option value="<?php echo $f['id']; ?>" <?php echo ($p['father_id']==$f['id'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($flabel); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="inline-btn">ربط</button>
                    </form>
                </td>

                <td>
                    <?php if ($p['father_id']): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="unlink">
                        <input type="hidden" name="child_id" value="<?php echo $p['id']; ?>">
                        <?php $pname = get_person_name($p); ?>
                        <button type="submit" class="btn-unlink"
                                onclick="return confirm('هل أنت متأكد من فك ربط <?php echo addslashes($pname); ?>؟')">
                            فك
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<p style="text-align:center;margin-top:14px;color:#2d3748;font-size:0.77rem;">
    إجمالي: <?php echo $total; ?> | بدون أب: <?php echo $no_father_count; ?>
</p>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.searchable-select').select2({
        dir: "rtl",
        language: { noResults: function () { return "لم يتم العثور على نتائج"; } }
    });

    // --- دالة إظهار الإشعارات ---
    function showToast(msg, type) {
        let bgColor = type === 'success' ? '#4caf50' : '#f44336';
        $('#toast').text(msg).css('background-color', bgColor).fadeIn(200).delay(2500).fadeOut(200);
    }

    // --- 1. التعامل مع نماذج الربط ---
    $('form:has(input[value="link"])').on('submit', function(e) {
        e.preventDefault();
        let form = $(this);
        let formData = form.serialize() + '&ajax=1';

        $.post('link_father.php', formData, function(res) {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                let tr = form.closest('tr');
                if(tr.length > 0) {
                    tr.find('.badge-unlinked').removeClass('badge-unlinked').addClass('badge-linked').text('✓ مربوط');
                }
            } else {
                showToast(res.message, 'error');
            }
        }, 'json');
    });

    // --- 2. التعامل مع نماذج فك الربط ---
    $('form:has(input[value="unlink"])').on('submit', function(e) {
        e.preventDefault();
        if (!confirm('هل أنت متأكد من فك الربط؟')) return;
        
        let form = $(this);
        let formData = form.serialize() + '&ajax=1';

        $.post('link_father.php', formData, function(res) {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                let tr = form.closest('tr');
                tr.find('.badge-linked').removeClass('badge-linked').addClass('badge-unlinked').text('⚠ بدون أب');
                tr.find('.chain').html('<span style="color:#37474f;font-style:italic;font-size:0.8rem;">لا يوجد ربط</span>');
                form.fadeOut(300, function(){ $(this).remove(); });
            } else {
                showToast(res.message, 'error');
            }
        }, 'json');
    });

    // إتاحة الوصول لـ showToast عالمياً لاستخدامها في الدوال الخارجية
    window.showToast = showToast;
});

// --- 3. دالة تعديل الاسم (تدعم الابن والأب) ---
function editName(id, currentName, btnElem) {
    let newName = prompt("تعديل الاسم:", currentName);
    
    if (newName !== null && newName.trim() !== "" && newName.trim() !== currentName) {
        $.post('link_father.php', {
            action: 'edit_name',
            person_id: id,
            new_name: newName.trim(),
            ajax: 1
        }, function(res) {
            if(res.status === 'success') {
                // البحث عن العنصر الذي يحمل الاسم (سواء كان <strong> للابن أو <span class="fa"> للأب)
                let targetElem = $(btnElem).siblings('strong').length ? $(btnElem).siblings('strong') : $(btnElem).siblings('.fa');
                targetElem.text(newName.trim());
                
                $(btnElem).attr('onclick', `editName(${id}, '${newName.trim()}', this)`);
                window.showToast(res.message, 'success');
            } else {
                window.showToast(res.message, 'error');
            }
        }, 'json').fail(function() {
            window.showToast("حدث خطأ في الاتصال بالسيرفر", 'error');
        });
    }
}

// --- 5. إظهار/إخفاء الأبناء ---
function toggleChildren(btn) {
    let list = btn.nextElementSibling;
    if (list.style.display === 'none') {
        list.style.display = 'flex';
        btn.textContent = '▲ إخفاء';
    } else {
        list.style.display = 'none';
        btn.textContent = '▼ إظهار';
    }
}

// --- 6. ربط الأم ---
function linkMother(btn) {
    let form = $(btn).closest('form');
    let child_id = form.find('input[name="child_id"]').val();
    let mother_id = form.find('select[name="mother_id"]').val();
    if (!mother_id) { window.showToast('اختار الأم أولاً', 'error'); return; }

    $.post('link_father.php', {
        action: 'link_mother',
        child_id: child_id,
        mother_id: mother_id,
        ajax: 1
    }, function(res) {
        if (res.status === 'success') {
            window.showToast(res.message, 'success');
            let td = form.closest('td');
            let motherName = form.find('select option:selected').text();
            td.html('<span class="mother-link">👩 ' + motherName + '</span><br><button class="btn-mother-unlink" onclick="unlinkMother(' + child_id + ', this)">فك</button>');
        } else {
            window.showToast(res.message, 'error');
        }
    }, 'json');
}

// --- 7. فك ربط الأم ---
function unlinkMother(child_id, btn) {
    if (!confirm('فك ربط الأم؟')) return;
    $.post('link_father.php', {
        action: 'unlink_mother',
        child_id: child_id,
        ajax: 1
    }, function(res) {
        if (res.status === 'success') {
            window.showToast(res.message, 'success');
            let td = $(btn).closest('td');
            td.html('<span class="note-empty">—</span>');
        } else {
            window.showToast(res.message, 'error');
        }
    }, 'json');
}

// --- 8. تعديل الملاحظة ---
function editNote(id, btnElem) {
    let currentNote = $('#note-' + id).text().trim();
    if (currentNote === '—') currentNote = '';
    let newNote = prompt('الملاحظة:', currentNote);
    if (newNote === null) return;
    $.post('link_father.php', {
        action: 'save_note',
        person_id: id,
        note: newNote.trim(),
        ajax: 1
    }, function(res) {
        if (res.status === 'success') {
            let display = newNote.trim() !== '' ? newNote.trim() : '<span class="note-empty">—</span>';
            $('#note-' + id).html(display);
            window.showToast(res.message, 'success');
        } else {
            window.showToast(res.message, 'error');
        }
    }, 'json');
}

function editGender(id, currentGender, btnElem) {
    let newGender = currentGender === 'Male' ? 'Female' : 'Male';

    $.post('link_father.php', {
        action: 'edit_gender',
        person_id: id,
        new_gender: newGender,
        ajax: 1
    }, function(res) {
        if (res.status === 'success') {
            let genderText = newGender === 'Male' ? '🟦 ذكر' : '🟥 أنثى';
            // تحديث النص في الواجهة
            $(btnElem).siblings('span[id^="gender-text-"]').text(genderText);
            // تحديث دالة الأونكليك للضغطة القادمة
            $(btnElem).attr('onclick', `editGender(${id}, '${newGender}', this)`);
            window.showToast(res.message, 'success');
        } else {
            window.showToast(res.message, 'error');
        }
    }, 'json').fail(function() {
        window.showToast("حدث خطأ في الاتصال بالسيرفر", 'error');
    });
}
</script>

</body>
</html>