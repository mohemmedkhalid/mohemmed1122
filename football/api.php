<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

// معلومات الاتصال بقاعدة البيانات موجودة في config.php
require __DIR__ . '/config.php';

function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
    respond(["status" => "error", "message" => "خطأ في الاتصال بقاعدة البيانات"]);
}

// ---------- 1. إنشاء الجداول وتحديثها تلقائياً ----------
const SCHEMA_VERSION = 2;

function columnExists($conn, $table, $col) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->bind_param("ss", $table, $col);
    $stmt->execute();
    $c = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c > 0;
}

function ensureColumn($conn, $table, $col, $definition) {
    if (!columnExists($conn, $table, $col)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
    }
}

function getSetting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT v FROM settings WHERE k = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['v'] : $default;
}

function setSetting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    $stmt->close();
}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(64) NOT NULL PRIMARY KEY,
        v LONGTEXT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    if ((int)getSetting($conn, 'schema_version', 0) < SCHEMA_VERSION) {
        $conn->query("CREATE TABLE IF NOT EXISTS players (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            level INT NOT NULL DEFAULT 4,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $conn->query("CREATE TABLE IF NOT EXISTS matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_a JSON NOT NULL,
            team_b JSON NOT NULL,
            score_a INT NOT NULL DEFAULT 0,
            score_b INT NOT NULL DEFAULT 0,
            goals JSON NOT NULL,
            played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // أعمدة جديدة للاعبي الفريق الأساسي
        ensureColumn($conn, 'players', 'number', "INT NULL DEFAULT NULL");
        ensureColumn($conn, 'players', 'position', "VARCHAR(10) NOT NULL DEFAULT ''");
        ensureColumn($conn, 'players', 'present', "TINYINT(1) NOT NULL DEFAULT 1");
        // تفاصيل المباراة الجديدة (أسماء الفرق، التشكيلة، الدقائق، التبديلات...)
        ensureColumn($conn, 'matches', 'details', "LONGTEXT NULL");

        // إدخال اللاعبين الافتراضيين إذا كان الجدول فارغاً
        $total = (int)$conn->query("SELECT COUNT(*) AS total FROM players")->fetch_assoc()['total'];
        if ($total === 0) {
            $defaultPlayers = ['عه بو', 'اسماعيل', 'حبيب', 'ايوب', 'دڵشاد', 'محمد طاهر', 'بيلال', 'حيدر', 'ئه يهان',
                               'سكڤان', 'مشير', 'حجى عه بو', 'گوهدار', 'على', 'محمد', 'ابوبكر', 'روژبير', 'هه ڤال'];
            $stmt = $conn->prepare("INSERT INTO players (name) VALUES (?)");
            foreach ($defaultPlayers as $name) {
                $stmt->bind_param("s", $name);
                $stmt->execute();
            }
            $stmt->close();
        }

        setSetting($conn, 'schema_version', (string)SCHEMA_VERSION);
    }
} catch (Throwable $e) {
    respond(["status" => "error", "message" => "خطأ في تجهيز قاعدة البيانات"]);
}

// ---------- 2. قراءة الطلب ----------
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];

const POSITIONS = ['', 'GK', 'DEF', 'MID', 'FWD'];
const SETTING_KEYS = ['team_name', 'captain_id', 'format', 'duration', 'lineup_7', 'lineup_8'];

function cleanName($v) {
    return mb_substr(trim((string)$v), 0, 100);
}

function cleanNumber($v) {
    if ($v === null || $v === '') return null;
    $n = (int)$v;
    return ($n >= 0 && $n <= 999) ? $n : null;
}

function cleanPosition($v) {
    $v = (string)$v;
    return in_array($v, POSITIONS, true) ? $v : '';
}

function decodeJson($v) {
    if ($v === null) return null;
    $d = json_decode($v, true);
    return $d === null ? null : $d;
}

try {
    switch ($action) {
        case 'get_all':
            $players = [];
            $resP = $conn->query("SELECT id, name, number, position, present FROM players ORDER BY name ASC");
            while ($row = $resP->fetch_assoc()) {
                $players[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'number' => $row['number'] === null ? null : (int)$row['number'],
                    'position' => $row['position'],
                    'present' => (int)$row['present'] === 1,
                ];
            }

            $matches = [];
            $resM = $conn->query("SELECT * FROM matches ORDER BY played_at DESC, id DESC");
            while ($row = $resM->fetch_assoc()) {
                $matches[] = [
                    'id' => (int)$row['id'],
                    'score_a' => (int)$row['score_a'],
                    'score_b' => (int)$row['score_b'],
                    'team_a' => decodeJson($row['team_a']) ?? [],
                    'team_b' => decodeJson($row['team_b']) ?? [],
                    'goals' => decodeJson($row['goals']) ?? [],
                    'details' => decodeJson($row['details'] ?? null),
                    'played_at' => $row['played_at'],
                ];
            }

            $settings = [];
            $resS = $conn->query("SELECT k, v FROM settings");
            while ($row = $resS->fetch_assoc()) {
                if (in_array($row['k'], SETTING_KEYS, true)) $settings[$row['k']] = $row['v'];
            }

            respond(["status" => "success", "players" => $players, "matches" => $matches, "settings" => (object)$settings]);

        case 'add_player':
            $name = cleanName($input['name'] ?? '');
            if ($name === '') respond(["status" => "error", "message" => "الاسم فارغ"]);
            $number = cleanNumber($input['number'] ?? null);
            $position = cleanPosition($input['position'] ?? '');
            $stmt = $conn->prepare("INSERT INTO players (name, number, position, present) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("sis", $name, $number, $position);
            $stmt->execute();
            respond(["status" => "success", "id" => $conn->insert_id]);

        case 'edit_player':
            $id = (int)($input['id'] ?? 0);
            $name = cleanName($input['name'] ?? '');
            if ($id <= 0 || $name === '') respond(["status" => "error", "message" => "بيانات غير صحيحة"]);
            $number = cleanNumber($input['number'] ?? null);
            $position = cleanPosition($input['position'] ?? '');
            $stmt = $conn->prepare("UPDATE players SET name = ?, number = ?, position = ? WHERE id = ?");
            $stmt->bind_param("sisi", $name, $number, $position, $id);
            $stmt->execute();
            respond(["status" => "success"]);

        case 'delete_player':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) respond(["status" => "error", "message" => "بيانات غير صحيحة"]);
            $stmt = $conn->prepare("DELETE FROM players WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ((string)getSetting($conn, 'captain_id', '') === (string)$id) setSetting($conn, 'captain_id', '');
            respond(["status" => "success"]);

        case 'set_present':
            $id = (int)($input['id'] ?? 0);
            $present = !empty($input['present']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE players SET present = ? WHERE id = ?");
            $stmt->bind_param("ii", $present, $id);
            $stmt->execute();
            respond(["status" => "success"]);

        case 'set_all_present':
            $present = !empty($input['present']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE players SET present = ?");
            $stmt->bind_param("i", $present);
            $stmt->execute();
            respond(["status" => "success"]);

        case 'save_setting':
            $key = (string)($input['key'] ?? '');
            if (!in_array($key, SETTING_KEYS, true)) respond(["status" => "error", "message" => "إعداد غير معروف"]);
            $value = $input['value'] ?? '';
            if (!is_string($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            setSetting($conn, $key, mb_substr($value, 0, 20000));
            respond(["status" => "success"]);

        case 'save_match':
            $teamA = json_encode($input['teamA'] ?? [], JSON_UNESCAPED_UNICODE);
            $teamB = json_encode($input['teamB'] ?? [], JSON_UNESCAPED_UNICODE);
            $scoreA = (int)($input['scoreA'] ?? 0);
            $scoreB = (int)($input['scoreB'] ?? 0);
            $goals = json_encode($input['goals'] ?? [], JSON_UNESCAPED_UNICODE);
            $details = json_encode($input['details'] ?? new stdClass(), JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("INSERT INTO matches (team_a, team_b, score_a, score_b, goals, details) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiss", $teamA, $teamB, $scoreA, $scoreB, $goals, $details);
            $stmt->execute();
            respond(["status" => "success", "id" => $conn->insert_id]);

        case 'delete_match':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) respond(["status" => "error", "message" => "بيانات غير صحيحة"]);
            $stmt = $conn->prepare("DELETE FROM matches WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            respond(["status" => "success"]);

        default:
            respond(["status" => "error", "message" => "عملية غير معروفة"]);
    }
} catch (Throwable $e) {
    respond(["status" => "error", "message" => "خطأ في قاعدة البيانات"]);
}
