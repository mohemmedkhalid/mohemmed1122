<?php
require_once __DIR__.'/user-identity.php';
// Key-only access with a revocable device credential independent of PHP sessions.
function key_path() {
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return ($path === '/' || $path === '.') ? '/' : rtrim($path, '/').'/';
}
function key_secure() {
    return (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}
function key_cookie($name, $value, $expires) {
    setcookie($name, $value, ['expires'=>$expires, 'path'=>key_path(), 'secure'=>key_secure(), 'httponly'=>true, 'samesite'=>'Lax']);
    if ($expires > time()) $_COOKIE[$name] = $value; else unset($_COOKIE[$name]);
}
function key_session_settings() {
    if (session_status() !== PHP_SESSION_NONE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime'=>2592000, 'path'=>key_path(), 'secure'=>key_secure(), 'httponly'=>true, 'samesite'=>'Lax']);
}
function key_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function key_text($ar, $en) { return get_lang() === 'en' ? $en : $ar; }
function key_query($db, $sql, $types = '', $values = []) {
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Key access query could not be prepared.');
    if ($types !== '') $stmt->bind_param($types, ...$values);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Key access query failed.'); }
    return $stmt;
}
function key_rows($db, $sql, $types = '', $values = []) {
    $stmt = key_query($db, $sql, $types, $values);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); return $rows;
}
function key_tables($db) {
    static $ready = false;
    if ($ready) return;
    $tables = [
        'site_access_tokens'=>"CREATE TABLE IF NOT EXISTS site_access_tokens (
            selector CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            validator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            key_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            csrf_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            expires_at DATETIME NOT NULL,
            KEY token_user (user_id), KEY token_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'site_key_attempts'=>"CREATE TABLE IF NOT EXISTS site_key_attempts (
            bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
            attempts INT UNSIGNED NOT NULL DEFAULT 0, started_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($tables as $table=>$sql) {
        try {
            $result = $db->query('SELECT 1 FROM '.$table.' LIMIT 0');
            if ($result) { $result->free(); continue; }
            if ($db->errno !== 1146) throw new RuntimeException('Key access table unavailable.');
        } catch (mysqli_sql_exception $e) { if ((int)$e->getCode() !== 1146) throw $e; }
        if (!$db->query($sql)) throw new RuntimeException('Could not create key access table.');
    }
    $ready = true;
}
function key_clear_identity() {
    foreach (['user_id','role','access_code','key_device','key_fingerprint','key_csrf','feedback_csrf','people_csrf'] as $name) unset($_SESSION[$name]);
}
function key_set_identity($user, $selector, $csrf) {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['access_code'] = $user['access_code'];
    $_SESSION['key_fingerprint'] = hash('sha256', $user['access_code']);
    $_SESSION['key_device'] = $selector;
    $_SESSION['key_csrf'] = $csrf;
    $_SESSION['feedback_csrf'] = $csrf;
}
function key_device_parts() {
    $value = $_COOKIE['jotiari_device'] ?? '';
    if (!is_string($value) || preg_match('/\A([a-f0-9]{24})\.([a-f0-9]{64})\z/D', $value, $matches) !== 1) return null;
    return [$matches[1], $matches[2]];
}
function key_issue_device($db, $user, $csrf = null) {
    key_tables($db);
    $selector = bin2hex(random_bytes(12)); $validator = bin2hex(random_bytes(32));
    $csrf = is_string($csrf) && preg_match('/\A[a-f0-9]{64}\z/D', $csrf) ? $csrf : bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator); $fingerprint = hash('sha256', $user['access_code']);
    key_query($db, 'INSERT INTO site_access_tokens (selector, validator_hash, user_id, key_fingerprint, csrf_key, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))', 'ssiss', [$selector,$hash,(int)$user['id'],$fingerprint,$csrf])->close();
    key_cookie('jotiari_device', $selector.'.'.$validator, time()+2592000);
    key_set_identity($user, $selector, $csrf);
}
function key_restore($db) {
    $parts = key_device_parts();
    $hadDevice = isset($_SESSION['key_device']);
    try {
        key_users_ready($db);
        if ($parts) {
            key_tables($db);
            $rows = key_rows($db, 'SELECT t.validator_hash, t.key_fingerprint, t.csrf_key, u.id, u.access_code, u.role FROM site_access_tokens t JOIN users u ON u.id=t.user_id WHERE t.selector=? AND t.expires_at>UTC_TIMESTAMP()', 's', [$parts[0]]);
            $user = $rows[0] ?? null;
            if ($user && (int)$user['id'] > 0 && in_array($user['role'], ['admin','user'], true)
                && hash_equals($user['validator_hash'], hash('sha256',$parts[1]))
                && hash_equals($user['key_fingerprint'], hash('sha256',$user['access_code']))) {
                if ((int)($_SESSION['user_id'] ?? 0) !== (int)$user['id']) { key_clear_identity(); session_regenerate_id(true); }
                key_set_identity($user, $parts[0], $user['csrf_key']); return;
            }
            key_cookie('jotiari_device', '', time()-3600);
            key_clear_identity(); return;
        }
        if ($hadDevice) { key_clear_identity(); return; }
        // Keep valid existing sessions working during the update.
        if ((int)($_SESSION['user_id'] ?? 0) > 0) {
            $rows = key_rows($db, 'SELECT id, access_code, role FROM users WHERE id=?', 'i', [(int)$_SESSION['user_id']]);
            $user = $rows[0] ?? null;
            if ($user && in_array($user['role'], ['admin','user'], true)
                && (!isset($_SESSION['access_code']) || hash_equals($user['access_code'], (string)$_SESSION['access_code']))) {
                key_issue_device($db, $user, $_SESSION['feedback_csrf'] ?? null); return;
            }
        }
        key_clear_identity();
    } catch (Throwable $e) {
        error_log('Key access restore: '.$e->getMessage()); key_clear_identity();
        $GLOBALS['key_auth_unavailable'] = true;
    }
}
function key_gate_csrf() {
    $token = $_COOKIE['jotiari_gate'] ?? '';
    if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
        $token = bin2hex(random_bytes(32)); key_cookie('jotiari_gate', $token, time()+2592000);
    }
    return $token;
}
function key_gate_valid($token) {
    $cookie = $_COOKIE['jotiari_gate'] ?? null;
    return is_string($token) && is_string($cookie) && strlen($cookie) === 64 && hash_equals($cookie, $token);
}
function key_admin_csrf_valid($token) {
    return is_string($token) && isset($_SESSION['key_csrf']) && hash_equals($_SESSION['key_csrf'], $token);
}
function key_next($value) {
    return is_string($value) && in_array($value, ['index.php','index.php#feedback','admin_feedback.php','users_management.php'], true) ? $value : 'index.php';
}
function key_login($db, $raw) {
    if (!is_string($raw) || trim($raw) === '' || strlen($raw) > 255) throw new InvalidArgumentException('invalid_key');
    $code = trim($raw);
    key_users_ready($db); key_tables($db);
    $bucket = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown').'|'.key_path());
    key_query($db, 'DELETE FROM site_key_attempts WHERE started_at<DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)')->close();
    $attempt = key_rows($db, 'SELECT attempts FROM site_key_attempts WHERE bucket=?', 's', [$bucket]);
    if ((int)($attempt[0]['attempts'] ?? 0) >= 10) throw new RuntimeException('rate_limit', 429);
    key_query($db, 'INSERT INTO site_key_attempts (bucket, attempts, started_at) VALUES (?, 1, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=attempts+1', 's', [$bucket])->close();
    $rows = key_rows($db, 'SELECT id, access_code, role FROM users WHERE BINARY access_code=BINARY ? LIMIT 2', 's', [$code]);
    if (!$rows) {
        $normalized = strtr($code, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
        if ($normalized !== $code) { $code = $normalized; $rows = key_rows($db, 'SELECT id, access_code, role FROM users WHERE BINARY access_code=BINARY ? LIMIT 2', 's', [$code]); }
    }
    if (count($rows) !== 1 || (int)$rows[0]['id'] < 1 || !in_array($rows[0]['role'], ['user','admin'], true) || !hash_equals($rows[0]['access_code'], $code)) throw new InvalidArgumentException('invalid_key');
    $old = key_device_parts(); key_clear_identity(); session_regenerate_id(true);
    key_issue_device($db, $rows[0]);
    if ($old) key_query($db, 'DELETE FROM site_access_tokens WHERE selector=? AND validator_hash=?', 'ss', [$old[0],hash('sha256',$old[1])])->close();
    key_query($db, 'DELETE FROM site_key_attempts WHERE bucket=?', 's', [$bucket])->close();
    key_query($db, 'DELETE FROM site_access_tokens WHERE expires_at<=UTC_TIMESTAMP()')->close();
}
function key_logout($db) {
    $parts = key_device_parts();
    if ($parts) {
        try { key_query($db, 'DELETE FROM site_access_tokens WHERE selector=? AND validator_hash=?', 'ss', [$parts[0],hash('sha256',$parts[1])])->close(); }
        catch (Throwable $e) { error_log('Key logout: '.$e->getMessage()); }
    }
    key_cookie('jotiari_device','',time()-3600); key_cookie('jotiari_gate','',time()-3600);
    $_SESSION = []; session_destroy(); key_cookie(session_name(), '', time()-3600);
}
