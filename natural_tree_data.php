<?php
/**
 * natural_tree_data.php — بيانات «الشجرة الطبيعية» بصيغة JSON.
 *   GET natural_tree_data.php        ← كل الأشخاص وروابط الزواج + بصمة البيانات
 *   GET natural_tree_data.php?sig=1  ← البصمة فقط (فحص خفيف لمعرفة الإضافة أو الحذف)
 * لا يغيّر أي بيانات، ويتطلب تسجيل الدخول.
 */
require_once __DIR__ . '/config.php';
if (ob_get_level() > 0) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'authentication_required']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
$lang = get_lang();
$admin = isAdmin();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

function nt_signature($db, string $lang): string {
    $people = $db->query("SELECT COUNT(*) AS c, COALESCE(MAX(id), 0) AS m,
        COALESCE(SUM(CRC32(CONCAT_WS('|', id, IFNULL(father_id, 0), IFNULL(mother_id, 0), IFNULL(full_name, ''),
            IFNULL(full_name_en, ''), IFNULL(gender, ''), IFNULL(status, ''), IFNULL(birth_date, ''),
            IFNULL(is_primary_ancestor, 0), IFNULL(image_url, '')))), 0) AS h FROM people2");
    $marriages = $db->query("SELECT COUNT(*) AS c, COALESCE(SUM(CRC32(CONCAT_WS('|', husband_id, wife_id))), 0) AS h FROM marriages");
    if (!$people || !$marriages) throw new RuntimeException('signature query failed');
    $a = $people->fetch_assoc();
    $b = $marriages->fetch_assoc();
    return sha1(implode(':', [$a['c'], $a['m'], $a['h'], $b['c'], $b['h'], $lang]));
}

function nt_year($date): ?int {
    if (!is_string($date) || $date === '' || strpos($date, '0000') === 0) return null;
    $year = (int) substr($date, 0, 4);
    return $year > 0 ? $year : null;
}

try {
    $signature = nt_signature($mysqli, $lang);
    if (isset($_GET['sig'])) {
        echo json_encode(['sig' => $signature]);
        exit;
    }

    $result = $mysqli->query('SELECT id, full_name, full_name_en, gender, status, birth_date, father_id, mother_id, is_primary_ancestor, image_url FROM people2 ORDER BY id');
    $marriageResult = $mysqli->query('SELECT husband_id, wife_id FROM marriages ORDER BY id');
    if (!$result || !$marriageResult) throw new RuntimeException('Could not read family data');

    $people = [];
    while ($row = $result->fetch_assoc()) {
        $name = get_person_name($row);
        $alt = $lang === 'en' ? (string) $row['full_name'] : (string) ($row['full_name_en'] ?? '');
        $birth = (is_string($row['birth_date']) && strpos($row['birth_date'], '0000') !== 0) ? $row['birth_date'] : null;
        $image = trim((string) ($row['image_url'] ?? ''));
        if ($image !== '' && !preg_match('~^(uploads/[A-Za-z0-9_./-]+|https?://\S+)$~', $image)) $image = '';
        $people[] = [
            'id' => (int) $row['id'],
            'name' => $name !== '' ? $name : (string) $row['full_name'],
            'alt' => $alt !== $name ? $alt : '',
            'gender' => $row['gender'] === 'Female' ? 'Female' : 'Male',
            'status' => $row['status'] === 'Deceased' ? 'Deceased' : 'Alive',
            'birth_date' => $birth,
            'birth_year' => nt_year($birth),
            'father_id' => empty($row['father_id']) ? null : (int) $row['father_id'],
            'mother_id' => empty($row['mother_id']) ? null : (int) $row['mother_id'],
            'is_primary_ancestor' => empty($row['is_primary_ancestor']) ? 0 : 1,
            'image_url' => $image !== '' ? $image : null,
        ];
    }
    $marriages = [];
    while ($m = $marriageResult->fetch_assoc()) {
        $marriages[] = [(int) $m['husband_id'], (int) $m['wife_id']];
    }
    $result->free();
    $marriageResult->free();

    echo json_encode([
        'sig' => $signature,
        'lang' => $lang,
        'admin' => $admin,
        'people' => $people,
        'marriages' => $marriages,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Natural tree data: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'data_unavailable']);
}
