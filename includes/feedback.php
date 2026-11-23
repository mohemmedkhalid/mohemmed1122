<?php
// Shared feedback storage. Authentication is enforced by each entry point.
function feedback_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function feedback_text($arabic, $english) {
    return get_lang() === 'en' ? $english : $arabic;
}

function feedback_csrf() {
    if (empty($_SESSION['feedback_csrf'])) {
        $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['feedback_csrf'];
}

function feedback_ensure_table($db) {
    // Existing installations need no CREATE privilege once the table exists.
    try {
        $result = $db->query('SELECT id FROM site_feedback LIMIT 0');
        if ($result !== false) { $result->free(); return; }
        if ($db->errno !== 1146) throw new RuntimeException('Feedback table unavailable.');
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1146) throw $e;
    }
    $created = $db->query("CREATE TABLE IF NOT EXISTS site_feedback (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY feedback_submission (user_id, submission_key),
        KEY feedback_created (created_at, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!$created) throw new RuntimeException('Could not create feedback table.');
}

function feedback_message($raw) {
    if (!is_string($raw) || strlen($raw) > 8000 || preg_match('//u', $raw) !== 1) {
        throw new InvalidArgumentException('length');
    }
    $message = str_replace(["\r\n", "\r"], "\n", $raw);
    $message = preg_replace('/\A[\s\p{Z}\x{200B}\x{FEFF}]+|[\s\p{Z}\x{200B}\x{FEFF}]+\z/u', '', $message);
    if ($message === '') throw new InvalidArgumentException('empty');
    if (preg_match_all('/./us', $message) > 2000 || strpos($message, "\0") !== false) {
        throw new InvalidArgumentException('length');
    }
    return $message;
}

function feedback_save($db, $userId, $message, $key) {
    feedback_ensure_table($db);
    $stmt = $db->prepare('INSERT INTO site_feedback (user_id, message, submission_key, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())');
    if (!$stmt) throw new RuntimeException('Could not prepare feedback insert.');
    $stmt->bind_param('iss', $userId, $message, $key);
    try {
        if ($stmt->execute()) return (int)$db->insert_id;
        if ($stmt->errno !== 1062) throw new RuntimeException('Could not save feedback.');
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1062) throw $e;
    } finally {
        $stmt->close();
    }
    // A retry after a lost response must not create a second message.
    $stmt = $db->prepare('SELECT id, message FROM site_feedback WHERE user_id = ? AND submission_key = ?');
    if (!$stmt) throw new RuntimeException('Could not prepare feedback retry.');
    $stmt->bind_param('is', $userId, $key);
    if (!$stmt->execute()) throw new RuntimeException('Could not inspect feedback retry.');
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new RuntimeException('Feedback retry was not found.');
    if ($row['message'] !== $message) throw new DomainException('submission_conflict');
    return (int)$row['id'];
}
