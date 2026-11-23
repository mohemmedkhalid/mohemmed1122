<?php
/**
 * set_lang.php — تغيير اللغة مباشرة والعودة للصفحة الحالية
 * يعمل للمستخدمين المسجّلين وغير المسجّلين
 */
require_once 'config.php';

// اللغة المطلوبة
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'en') ? 'en' : 'ku';
$_SESSION['lang'] = $lang;

// الصفحة التي نرجع إليها
$back = isset($_GET['back']) ? trim($_GET['back']) : '';

// أمان: فقط روابط داخلية (اسم ملف PHP فقط)
if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $back)) {
    $back = isLoggedIn() ? 'index.php' : 'login.php';
}

header("Location: $back");
exit;
