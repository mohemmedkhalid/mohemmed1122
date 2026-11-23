<?php
// get_data.php - نسخة محسّنة ومُصلَحة بالكامل
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

session_start();
require_once 'config.php';

// التحقق من أن المستخدم قد سجل دخوله
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
session_write_close();
// ---- دالة مساعدة لتنظيف المدخلات ----
function safe_int($val) {
    return ($val !== null && $val !== '') ? (int)$val : null;
}

// ============================================================
// طلب جلب كل الأشخاص للبحث
// ============================================================
if (isset($_GET['all']) && $_GET['all'] == '1') {
    $all_nodes = [];

    $stmt = $mysqli->prepare("SELECT id, full_name, full_name_en, gender, status, birth_date, father_id, mother_id, image_url, notes FROM people2 ORDER BY full_name");
    if (!$stmt) {
        echo json_encode(['nodes' => [], 'error' => 'Query failed: ' . $mysqli->error]);
        exit;
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $all_nodes[] = [
            'id'        => (int)$row['id'],
            'name'      => get_person_name($row),
            'full_name' => $row['full_name'],
            'full_name_en' => $row['full_name_en'],
            'gender'    => $row['gender'],
            'status'    => $row['status'],
            'birth_date'=> $row['birth_date'],
            'father_id' => safe_int($row['father_id']),
            'mother_id' => safe_int($row['mother_id']),
            'image_url' => $row['image_url'],
            'notes'     => $row['notes'],
        ];
    }
    $stmt->close();

    echo json_encode(['nodes' => $all_nodes]);
    $mysqli->close();
    exit;
}

// ============================================================
// تحديد الشخص المحوري (focus_id)
// ============================================================
$focus_id = null;

if (isset($_GET['id']) && $_GET['id'] !== '') {
    $focus_id = (int)$_GET['id'];
} else {
    // ابحث عن الجد الأول
    $r = $mysqli->query("SELECT id FROM people2 WHERE is_primary_ancestor = 1 LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $focus_id = (int)$r->fetch_assoc()['id'];
    } else {
        $r2 = $mysqli->query("SELECT id FROM people2 ORDER BY id LIMIT 1");
        if ($r2 && ($row2 = $r2->fetch_assoc())) {
            $focus_id = (int)$row2['id'];
        }
    }
}

if (!$focus_id) {
    echo json_encode(['nodes' => [], 'parent_id' => null, 'error' => 'لا يوجد أشخاص في قاعدة البيانات']);
    $mysqli->close();
    exit;
}

// ============================================================
// جمع المعرّفات ذات الصلة
// ============================================================
$related_ids    = [$focus_id];
$parent_of_focus = null;

// بيانات الشخص المحوري
$stmt = $mysqli->prepare("SELECT father_id, mother_id, gender FROM people2 WHERE id = ?");
$stmt->bind_param("i", $focus_id);
$stmt->execute();
$res = $stmt->get_result();
$focus_row = $res->fetch_assoc();
$stmt->close();

if ($focus_row) {
    $parent_of_focus = safe_int($focus_row['father_id']);
    if ($focus_row['father_id']) $related_ids[] = (int)$focus_row['father_id'];
    if ($focus_row['mother_id']) $related_ids[] = (int)$focus_row['mother_id'];

    // الأزواج
    $spouse_col = ($focus_row['gender'] === 'Male') ? 'wife_id'    : 'husband_id';
    $id_col     = ($focus_row['gender'] === 'Male') ? 'husband_id' : 'wife_id';
    $stmt2 = $mysqli->prepare("SELECT $spouse_col FROM marriages WHERE $id_col = ?");
    $stmt2->bind_param("i", $focus_id);
    $stmt2->execute();
    $sres = $stmt2->get_result();
    while ($sr = $sres->fetch_assoc()) {
        if ($sr[$spouse_col]) $related_ids[] = (int)$sr[$spouse_col];
    }
    $stmt2->close();
}

// الأبناء
$stmt3 = $mysqli->prepare("SELECT id FROM people2 WHERE father_id = ? OR mother_id = ? ORDER BY birth_date ASC");
$stmt3->bind_param("ii", $focus_id, $focus_id);
$stmt3->execute();
$cres = $stmt3->get_result();
while ($cr = $cres->fetch_assoc()) {
    $related_ids[] = (int)$cr['id'];
}
$stmt3->close();

$related_ids = array_values(array_unique(array_filter($related_ids)));

if (empty($related_ids)) {
    echo json_encode(['nodes' => [], 'parent_id' => null]);
    $mysqli->close();
    exit;
}

// ============================================================
// جلب البيانات الأساسية لجميع الأشخاص ذوي الصلة
// ============================================================
$ids_string = implode(',', $related_ids);

$people_data = [];
$result = $mysqli->query(
    "SELECT id, full_name, full_name_en, gender, status, birth_date, father_id, mother_id,
            is_primary_ancestor, image_url, notes
     FROM people2 WHERE id IN ($ids_string)"
);
if (!$result) {
    echo json_encode(['nodes' => [], 'parent_id' => null, 'error' => 'DB error: ' . $mysqli->error]);
    $mysqli->close();
    exit;
}
while ($row = $result->fetch_assoc()) {
    $people_data[$row['id']] = $row;
}

// العلاقات الزوجية
$marriages_data = [];
$mres = $mysqli->query(
    "SELECT husband_id, wife_id FROM marriages
     WHERE husband_id IN ($ids_string) OR wife_id IN ($ids_string)"
);
if ($mres) {
    while ($mr = $mres->fetch_assoc()) {
        $marriages_data[] = $mr;
    }
}

// ============================================================
// بناء عقد الشجرة (Nodes)
// ============================================================
$nodes = [];

foreach ($related_ids as $id) {
    if (!isset($people_data[$id])) continue;

    $row  = $people_data[$id];
    $tags = [];
    $tags[] = ($row['gender'] === 'Male') ? 'male' : 'female';
    if ($row['id'] == $focus_id)           $tags[] = 'focused';
    if ($row['status'] === 'Deceased')     $tags[] = 'deceased';
    if (!empty($row['is_primary_ancestor'])) $tags[] = 'primary-ancestor';

    // شركاء الحياة (IDs الموجودة في الشجرة الحالية)
    $partners_ids = [];
    foreach ($marriages_data as $m) {
        if ((int)$m['husband_id'] === $id && isset($people_data[$m['wife_id']])) {
            $partners_ids[] = (int)$m['wife_id'];
        } elseif ((int)$m['wife_id'] === $id && isset($people_data[$m['husband_id']])) {
            $partners_ids[] = (int)$m['husband_id'];
        }
    }

    // أسماء جميع الأزواج (حتى غير الموجودين في الشجرة)
    $partner_query_col  = ($row['gender'] === 'Male') ? 'husband_id' : 'wife_id';
    $partner_target_col = ($row['gender'] === 'Male') ? 'wife_id'    : 'husband_id';

    $sp_stmt = $mysqli->prepare(
        "SELECT p.full_name, p.full_name_en
         FROM marriages m
         JOIN people2 p ON p.id = m.$partner_target_col
         WHERE m.$partner_query_col = ?"
    );
    $sp_stmt->bind_param("i", $id);
    $sp_stmt->execute();
    $sp_res = $sp_stmt->get_result();
    $spouses_names = [];
    while ($sp_row = $sp_res->fetch_assoc()) {
        $spouses_names[] = get_person_name($sp_row);
    }
    $sp_stmt->close();

    $current_lang = get_lang();
    $unknown_spouses = $current_lang === 'en' ? 'Unknown' : 'نەدیارکرە';
    $unknown_date = $current_lang === 'en' ? 'Unknown' : 'نەدیارە';

    $spouses_str = empty($spouses_names) ? $unknown_spouses : implode('، ', $spouses_names);

    // عدد الأبناء
    $cc_stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM people2 WHERE father_id = ? OR mother_id = ?");
    $cc_stmt->bind_param("ii", $id, $id);
    $cc_stmt->execute();
    $children_count = (int)$cc_stmt->get_result()->fetch_assoc()['cnt'];
    $cc_stmt->close();

    // تواريخ
    $birth_year      = $row['birth_date'] ? date('Y', strtotime($row['birth_date'])) : '';
    $full_birth_date = $row['birth_date'] ?: $unknown_date;

    // بناء العقدة
    $node = [
        'id'             => (int)$row['id'],
        'name'           => get_person_name($row),
        'gender'         => $row['gender'],
        'tags'           => array_values(array_unique($tags)),
        'birth_year'     => $birth_year,
        'full_date'      => $full_birth_date,
        'status'         => $row['status'] ?: 'Alive',
        'spouses_names'  => $spouses_str,
        'children_count' => $children_count,
        'image_url'      => $row['image_url'] ?? null,
        'notes'          => $row['notes']     ?? null,
    ];

    // روابط الأب/الأم (فقط إذا كانوا موجودين في الشجرة)
    if (!empty($row['father_id']) && isset($people_data[$row['father_id']]))
        $node['fid'] = (int)$row['father_id'];
    if (!empty($row['mother_id']) && isset($people_data[$row['mother_id']]))
        $node['mid'] = (int)$row['mother_id'];
    if (!empty($partners_ids))
        $node['pids'] = $partners_ids;

    $nodes[] = $node;
}

// ============================================================
// الإخراج
// ============================================================
echo json_encode([
    'nodes'     => $nodes,
    'parent_id' => $parent_of_focus,
]);

$mysqli->close();
?>