<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if (!isset($_SESSION['user_id'])) {
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
try {
    require_once __DIR__ . '/config.php';
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $peopleResult = $mysqli->query('SELECT id, full_name, full_name_en, gender, father_id, mother_id, image_url FROM people2 ORDER BY full_name, id');
    $marriageResult = $mysqli->query('SELECT husband_id, wife_id FROM marriages ORDER BY id');
    if (!$peopleResult || !$marriageResult) throw new RuntimeException('Could not read family links');
    $people = [];
    while ($person = $peopleResult->fetch_assoc()) {
        $person['id'] = (int) $person['id'];
        $person['father_id'] = empty($person['father_id']) ? null : (int) $person['father_id'];
        $person['mother_id'] = empty($person['mother_id']) ? null : (int) $person['mother_id'];
        $people[] = $person;
    }
    $marriages = [];
    while ($marriage = $marriageResult->fetch_assoc()) {
        $marriages[] = ['husband_id' => (int) $marriage['husband_id'], 'wife_id' => (int) $marriage['wife_id']];
    }
    $peopleResult->free();
    $marriageResult->free();
    echo json_encode(['people' => $people, 'marriages' => $marriages, 'viewer_id' => (string) $_SESSION['user_id']], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Relationship data: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'data_unavailable']);
}
