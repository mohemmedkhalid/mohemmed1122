<?php
/**
 * debug_check.php — ملف تشخيص مؤقت
 * احذفه بعد حل المشكلة!
 */
// عرض كل الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// لا نشغّل session هنا لتجنب تعارض
echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:monospace;background:#111;color:#0f0;padding:20px}
h2{color:#ff0;border-bottom:1px solid #444;padding-bottom:6px}
.ok{color:#0f0}.err{color:#f44}.warn{color:#fa0}
table{border-collapse:collapse;margin:10px 0}
td,th{padding:6px 12px;border:1px solid #333;text-align:left}
th{background:#222;color:#ff0}
</style></head><body>';

echo '<h2>🔍 Server Diagnostic Report</h2>';
echo '<p style="color:#888">Delete this file after fixing!</p>';

// ── PHP Version ──
echo '<h2>PHP Version</h2>';
$v = PHP_VERSION;
$ok = version_compare($v, '7.4.0', '>=');
echo '<p class="' . ($ok?'ok':'err') . '">PHP ' . $v . ($ok?' ✅ (7.4+ OK)':' ❌ Need 7.4 or higher') . '</p>';

// ── PHP Extensions ──
echo '<h2>Required Extensions</h2><table>';
echo '<tr><th>Extension</th><th>Status</th></tr>';
$exts = ['mysqli','pdo','pdo_mysql','json','session','mbstring','pcre'];
foreach ($exts as $ext) {
    $loaded = extension_loaded($ext);
    echo '<tr><td>' . $ext . '</td><td class="' . ($loaded?'ok':'err') . '">' . ($loaded?'✅ Loaded':'❌ MISSING') . '</td></tr>';
}
echo '</table>';

// ── Database Connection ──
echo '<h2>Database Connection</h2>';
require_once 'config.php';
if ($mysqli && !$mysqli->connect_error) {
    echo '<p class="ok">✅ Connected to database: <b>' . $mysqli->host_info . '</b></p>';
    // التحقق من وجود الجدول
    $r = $mysqli->query("SHOW TABLES LIKE 'people2'");
    if ($r && $r->num_rows > 0) {
        $cnt = $mysqli->query("SELECT COUNT(*) FROM people2")->fetch_row()[0];
        echo '<p class="ok">✅ Table people2 exists — ' . $cnt . ' records</p>';
        // التحقق من عمود full_name_en
        $cols = $mysqli->query("SHOW COLUMNS FROM people2 LIKE 'full_name_en'");
        if ($cols && $cols->num_rows > 0) {
            echo '<p class="ok">✅ Column full_name_en exists</p>';
        } else {
            echo '<p class="err">❌ Column full_name_en MISSING — run ALTER below!</p>';
        }
    } else {
        echo '<p class="err">❌ Table people2 NOT FOUND</p>';
    }
} else {
    echo '<p class="err">❌ DB Connection Failed: ' . ($mysqli ? $mysqli->connect_error : 'Unknown') . '</p>';
}

// ── Session ──
echo '<h2>Session Status</h2>';
if (session_status() === PHP_SESSION_ACTIVE) {
    echo '<p class="ok">✅ Session active</p>';
    echo '<pre style="color:#aaa">' . print_r($_SESSION, true) . '</pre>';
} else {
    echo '<p class="warn">⚠ Session not started yet</p>';
}

// ── File Permissions ──
echo '<h2>Key Files</h2><table>';
echo '<tr><th>File</th><th>Exists</th><th>Readable</th></tr>';
$files = ['config.php','header.php','login.php','index.php','select_language.php',
          'set_lang.php','check_login.php','logout.php'];
foreach ($files as $f) {
    $ex = file_exists($f);
    $rd = $ex && is_readable($f);
    echo '<tr><td>'.$f.'</td><td class="'.($ex?'ok':'err').'">' .($ex?'✅':'❌'). '</td>
          <td class="'.($rd?'ok':'err').'">' .($rd?'✅':'❌'). '</td></tr>';
}
echo '</table>';

// ── PHP Error Log ──
echo '<h2>PHP Error Log Path</h2>';
$log = ini_get('error_log');
echo '<p>' . ($log ?: 'Not configured') . '</p>';

// ── Headers Already Sent Check ──
echo '<h2>Headers Status</h2>';
if (headers_sent($file, $line)) {
    echo '<p class="err">❌ Headers already sent in <b>' . $file . '</b> line <b>' . $line . '</b></p>';
} else {
    echo '<p class="ok">✅ Headers not sent yet — no output-before-header issue</p>';
}

// ── check_login.php ──
echo '<h2>check_login.php</h2>';
if (file_exists('check_login.php')) {
    echo '<p class="ok">✅ File exists</p>';
} else {
    echo '<p class="err">❌ MISSING — login form will fail!</p>';
}

// ── ALTER TABLE (if full_name_en missing) ──
echo '<h2>Fix: Add full_name_en Column (if missing)</h2>';
if ($mysqli && !$mysqli->connect_error) {
    $cols = $mysqli->query("SHOW COLUMNS FROM people2 LIKE 'full_name_en'");
    if ($cols && $cols->num_rows === 0) {
        echo '<form method="POST"><button name="add_col" type="submit" 
              style="background:#f59e0b;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-size:1rem">
              ➕ Add full_name_en Column Now</button></form>';
        if (isset($_POST['add_col'])) {
            $mysqli->query("ALTER TABLE people2 ADD COLUMN full_name_en VARCHAR(255) DEFAULT NULL AFTER full_name");
            if (!$mysqli->error) echo '<p class="ok">✅ Column added!</p>';
            else echo '<p class="err">❌ Error: ' . $mysqli->error . '</p>';
        }
    } else {
        echo '<p class="ok">✅ Column already exists — no action needed</p>';
    }
}

echo '<br><hr style="border-color:#333"><p style="color:#555">⚠ Delete debug_check.php from your server after fixing!</p>';
echo '</body></html>';
