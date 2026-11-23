<?php
require_once 'config.php';
ob_start();
require_once 'header.php';
$home_notice = ob_get_clean();
require_once __DIR__.'/includes/feedback.php';
$feedbackToken = feedback_csrf();
$feedbackKey = bin2hex(random_bytes(32));
$feedbackGate = key_gate_csrf();
$feedbackNotice = $_SESSION['feedback_notice'] ?? null;
$feedbackDraft = $_SESSION['feedback_draft'] ?? '';
unset($_SESSION['feedback_notice'], $_SESSION['feedback_draft']);

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $length = strlen($needle);
        if (!$length) { return true; }
        return substr($haystack, -$length) === $needle;
    }
}

if (!function_exists('escape')) {
    function escape($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

$dashboard_titles = [];
if (isset($mysqli)) { 
    $result = $mysqli->query("SELECT key_name, title_ku, title_en FROM dashboard_titles");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dashboard_titles[$row['key_name']] = [
                'ku' => $row['title_ku'],
                'en' => $row['title_en']
            ];
        }
        $result->close();
    }
}

$current_lang = get_lang();

function get_title($key, $default, $default_en = '') {
    global $dashboard_titles, $current_lang;
    $esc_func = function_exists('escape') ? 'escape' : 'htmlspecialchars';
    
    $key_name_with_suffix = str_ends_with($key, '_title') ? $key : $key . '_title';

    if (isset($dashboard_titles[$key_name_with_suffix])) {
        if ($current_lang === 'en' && !empty($dashboard_titles[$key_name_with_suffix]['en'])) {
            return $esc_func($dashboard_titles[$key_name_with_suffix]['en']);
        }
        if (!empty($dashboard_titles[$key_name_with_suffix]['ku'])) return $esc_func($dashboard_titles[$key_name_with_suffix]['ku']);
    }
    
    if ($current_lang === 'en' && !empty($default_en)) {
        return $default_en;
    }
    return $default;
}

?>
<!DOCTYPE html>
<html lang="<?= get_lang() === 'en' ? 'en' : 'ku' ?>" dir="<?= get_lang() === 'en' ? 'ltr' : 'rtl' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $current_lang === 'en' ? 'Jotiari Family System' : 'بنەمالا جوتیاری' ?></title>
    
    <meta name="theme-color" content="#000000">
    <link rel="apple-touch-icon" href="icon-192.png">
    
    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            --bg-color: #5a6e74;
            --text-color: #ffffff;
            --card-bg: rgba(30, 30, 30, 0.6);
        }

        @font-face {
            font-family: 'CustomFont';
            src: url('./Speda.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        
        body {
            background: linear-gradient(135deg, #3a5251, #000000, #57565e);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-bottom: 0;
        }

        .navbar-custom {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 15px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .brand-text {
            font-weight: 800;
            color: var(--text-color);
            font-size: 1.2rem;
            letter-spacing: 0.5px;
        }

        .tabs-container {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 10;
            margin-top: 28px;
        }

        .tabs-wrapper {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 6px;
            border-radius: 50px;
            display: flex;
            gap: 5px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .tab-btn {
            border: none;
            background: transparent;
            color: var(--text-color);
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.7;
        }

        .tab-btn:hover {
            opacity: 1;
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            background: var(--card-bg); 
            color: #fff;
            opacity: 1;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .dashboard-grid-custom {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px; 
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        @media (min-width: 992px) {
            .dashboard-grid-custom {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 30px;
                padding: 0 40px;
            }
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid rgba(255, 255, 255, 0.25);
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 24px;
            height: 100%;
            min-height: 160px; 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05);
            overflow: hidden;
            position: relative;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: 0.5s;
        }

        .glass-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px 0 rgba(0, 0, 0, 0.15);
            border-top: 1px solid rgba(255, 255, 255, 0.5);
        }

        .glass-card:hover::before {
            left: 100%;
        }

        .glass-card a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 25px;
            text-decoration: none;
            color: var(--text-color);
        }

        .icon-box {
            font-size: 2rem;
            margin-bottom: 18px;
            width: 65px;
            height: 65px;
            border-radius: 20px; 
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease;
            position: relative;
        }
        
        .glass-card:hover .icon-box {
            transform: scale(1.1) rotate(5deg);
            background: rgba(255, 255, 255, 0.15);
        }

        .glass-card h4 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.5;
            opacity: 0.9;
            text-align: center;
        }

        .text-gradient-success { color: #00ff6a; text-shadow: 0 0 15px rgba(46, 204, 113, 0.3); }
        .text-gradient-warning { color: #f1c40f; text-shadow: 0 0 15px rgba(241, 196, 15, 0.3); }
        .text-gradient-danger { color: #e74c3c; text-shadow: 0 0 15px rgba(231, 76, 60, 0.3); }
        .text-gradient-primary { color: #5ebfff; text-shadow: 0 0 15px rgba(52, 152, 219, 0.3); }
        .text-gradient-info { color: rgb(0, 255, 251); text-shadow: 0 0 15px rgba(0, 206, 201, 0.3); }
        .text-gradient-light { color: #dfe6e9; text-shadow: 0 0 15px rgba(223, 230, 233, 0.3); }

        .section-content {
            display: none;
        }
        
        .section-content.active-section {
            display: grid;
            animation: slideUpFade 0.5s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
     
        footer {
            position: relative;
            width: 100%;
            margin: 40px 0 0;
            padding: 16px 20px;
            font-size: 0.85rem;
            opacity: 0.6;
            background: linear-gradient(0deg, var(--bg-color) 40%, transparent);
            z-index: 100;
        }

        body { min-height: 100svh; overflow-x: visible; }
        [hidden] { display: none !important; }
        button, input { font: inherit; }
        button, a { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
        button { cursor: pointer; }
        button:focus-visible, a:focus-visible, input:focus-visible { outline: 2px solid #8bd8bd; outline-offset: 4px; }
        .d-flex { display: flex; }
        .align-items-center { align-items: center; }
        .justify-content-between { justify-content: space-between; }
        .justify-content-center { justify-content: center; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .text-center { text-align: center; }
        .text-white { color: #fff; }
        .mb-0 { margin-bottom: 0; }
        .mb-3 { margin-bottom: 16px; }
        .mt-3 { margin-top: 16px; }
        .navbar-custom { position: sticky; top: 0; flex-shrink: 0; }
        .navbar-custom > .container { gap: 12px; flex-wrap: wrap; }
        .navbar-brand { min-width: 0; margin: 0; white-space: normal; }
        .navbar-brand img { margin: 0; flex-shrink: 0; }
        .brand-text { overflow-wrap: anywhere; line-height: 1.6; }
        .nav-actions { flex-shrink: 0; margin-inline-start: auto; }
        .language-button { min-height: 44px; }
        .logout-button { background: #dc3545; color: #fff; border-radius: 50%; width: 44px; height: 44px; text-decoration: none; }
        .logout-button:hover { background: #bb2d3b; }
        .home-main { flex: 1; }
        .home-welcome { font-size: 1.1rem; line-height: 1.7; }
        .tabs-container { margin-bottom: 22px; }
        .tabs-wrapper { max-width: 100%; }
        .tab-btn { min-height: 44px; justify-content: center; }
        .dashboard-grid-custom { width: 100%; }
        .glass-card { min-width: 0; }
        .glass-card::before { pointer-events: none; }
        .glass-card a { position: relative; }
        .glass-card h4 { max-width: 100%; overflow-wrap: anywhere; }
        .fas { display: inline-flex; align-items: center; justify-content: center; font-style: normal; }
        .home-icon { display: block; height: 1em; width: 1em; fill: currentColor; flex-shrink: 0; }
        .feedback-wrap { width: min(100%, 540px); margin: 0 auto 28px; scroll-margin-top: 130px; }
        .feedback-wrap label { display: block; font-size: .85rem; opacity: .9; margin-bottom: 8px; }
        .feedback-compose { display: flex; gap: 8px; align-items: flex-end; padding: 7px; border-radius: 22px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.05); }
        .feedback-compose textarea { flex: 1; min-width: 0; width: 100%; min-height: 46px; max-height: 144px; padding: 10px 8px; border: 0; border-radius: 14px; background: transparent; color: #fff; font: inherit; font-size: 16px; line-height: 26px; resize: none; }
        .feedback-compose textarea::placeholder { color: #c8d1d1; }
        .feedback-send { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 46px; padding: 10px 13px; border: 0; border-radius: 16px; background: #8bd8bd; color: #17382c; font: inherit; font-size: 14px; flex-shrink: 0; }
        .feedback-send svg { width: 18px; height: 18px; fill: currentColor; }
        .feedback-send:disabled { opacity: .65; cursor: wait; }
        .feedback-meta { display: flex; gap: 12px; justify-content: space-between; margin-top: 7px; font-size: 12px; color: #c0d5d1; }
        .feedback-status { margin: 0; overflow-wrap: anywhere; }
        .feedback-status[data-state=error] { color: #ffc9c4; }
        .feedback-status[data-state=success] { color: #a3e9cd; }
        .feedback-count { flex-shrink: 0; font-family: Arial, sans-serif; }
        .feedback-compose textarea:focus-visible { outline: 2px solid #8bd8bd; outline-offset: 0; }
        .feedback-access { margin-top: 12px; }
        .feedback-access input { width: 100%; min-height: 46px; padding: 10px 13px; border: 1px solid #ffffff55; border-radius: 14px; background: #ffffff09; color: #fff; font: inherit; font-size: 16px; }
        .message { margin: 12px 20px; padding: 12px; border-radius: 12px; background: rgba(255,255,255,.1); overflow-wrap: anywhere; }
        footer { opacity: .85; z-index: 1; line-height: 1.7; overflow-wrap: anywhere; }
        @media (max-width: 575px) {
            .container { padding-inline: 16px; }
            .navbar-custom { padding: 12px 0; margin-bottom: 16px; }
            .brand-text { font-size: 1rem; }
            .navbar-brand { gap: 8px; }
            .navbar-brand img { height: 34px !important; }
            .tabs-container { margin-top: 22px; }
            .tab-btn { padding: 10px 16px; font-size: .88rem; }
            .dashboard-grid-custom { gap: 12px; padding: 0; }
            .glass-card { border-radius: 20px; min-height: 155px; }
            .glass-card a { padding: 20px 12px; }
            .glass-card h4 { font-size: .9rem; line-height: 1.7; }
            .icon-box { width: 60px; height: 60px; margin-bottom: 14px; }
            .feedback-wrap { margin-bottom: 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
        }
    </style>
</head>
<body>
    <!-- Original icon paths: Font Awesome Free 6.4.0 by @fontawesome.
         https://fontawesome.com | Icons licensed under CC BY 4.0:
         https://creativecommons.org/licenses/by/4.0/ -->
    <?= $home_notice ?>

    <div class="background-orbs" aria-hidden="true">
        <div class="orb orb1"></div>
        <div class="orb orb2"></div>
    </div>

    <nav class="navbar navbar-custom sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
                <img src="icon-512.png" alt="" style="height: 40px;">
                <span class="brand-text"><?= $current_lang === 'en' ? 'Jotiari Family' : 'بنەمالا جوتیاری' ?></span>
            </a>
            <div class="nav-actions d-flex align-items-center gap-2">
                <?php
                $next_lang  = ($current_lang === 'en') ? 'ku' : 'en';
                $lang_label = ($current_lang === 'en') ? 'کوردی' : 'English';
                $lang_url   = "set_lang.php?lang={$next_lang}&back=index.php";
                ?>
                <a class="language-button" href="<?= escape($lang_url) ?>"
                   style="background:rgba(255,255,255,0.1);
                          border:1px solid rgba(255,255,255,0.2);
                          color:white; border-radius:20px;
                          font-size:0.82rem; padding:6px 14px;
                          text-decoration:none; display:inline-flex;
                          align-items:center; gap:5px;
                          transition:background 0.2s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.22)'"
                   onmouseout="this.style.background='rgba(255,255,255,0.10)'">
                    <?= $lang_label ?>
                </a>
                <a href="logout.php" class="logout-button d-flex align-items-center justify-content-center" aria-label="<?= $current_lang === 'en' ? 'Sign out' : 'دەرکەڤە' ?>">
                    <i class="fas fa-power-off" aria-hidden="true"><svg class="home-icon" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M288 32c0-17.7-14.3-32-32-32s-32 14.3-32 32V256c0 17.7 14.3 32 32 32s32-14.3 32-32V32zM143.5 120.6c13.6-11.3 15.4-31.5 4.1-45.1s-31.5-15.4-45.1-4.1C49.7 115.4 16 181.8 16 256c0 132.5 107.5 240 240 240s240-107.5 240-240c0-74.2-33.8-140.6-86.6-184.6c-13.6-11.3-33.8-9.4-45.1 4.1s-9.4 33.8 4.1 45.1c38.9 32.3 63.5 81 63.5 135.4c0 97.2-78.8 176-176 176s-176-78.8-176-176c0-54.4 24.7-103.1 63.5-135.4z"/></svg></i>
                </a>
            </div>
        </div>
    </nav>

    <main class="container home-main mt-3">
        <div class="text-center mb-3 fade-in">
            <h1 class="home-welcome" style="color: var(--text-color); font-weight: 300; letter-spacing: 1px; opacity: 0.8;"><?= $current_lang === 'en' ? 'Welcome to the System' : 'بخێرهاتن بۆ سیستەمی' ?></h1>
        </div>

        <div class="tabs-container">
            <div class="tabs-wrapper" role="tablist" aria-label="<?= $current_lang === 'en' ? 'Sections' : 'پشک' ?>">
                <button class="tab-btn active" id="clan1Tab" role="tab" aria-selected="true" aria-controls="clan1" tabindex="0" onclick="switchSection('clan1', this)">
                    <i class="fas fa-users-line" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M211.2 96a64 64 0 1 0 -128 0 64 64 0 1 0 128 0zM32 256c0 17.7 14.3 32 32 32h85.6c10.1-39.4 38.6-71.5 75.8-86.6c-9.7-6-21.2-9.4-33.4-9.4H96c-35.3 0-64 28.7-64 64zm461.6 32H576c17.7 0 32-14.3 32-32c0-35.3-28.7-64-64-64H448c-11.7 0-22.7 3.1-32.1 8.6c38.1 14.8 67.4 47.3 77.7 87.4zM391.2 226.4c-6.9-1.6-14.2-2.4-21.6-2.4h-96c-8.5 0-16.7 1.1-24.5 3.1c-30.8 8.1-55.6 31.1-66.1 60.9c-3.5 10-5.5 20.8-5.5 32c0 17.7 14.3 32 32 32h224c17.7 0 32-14.3 32-32c0-11.2-1.9-22-5.5-32c-10.8-30.7-36.8-54.2-68.9-61.6zM563.2 96a64 64 0 1 0 -128 0 64 64 0 1 0 128 0zM321.6 192a80 80 0 1 0 0-160 80 80 0 1 0 0 160zM32 416c-17.7 0-32 14.3-32 32s14.3 32 32 32H608c17.7 0 32-14.3 32-32s-14.3-32-32-32H32z"/></svg></i> <span><?= get_title('btn_clan1_title', 'بنەمالێ', 'Family') ?></span>
                </button>
              
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <button class="tab-btn" id="adminTab" role="tab" aria-selected="false" aria-controls="admin" tabindex="-1" onclick="switchSection('admin', this)">
                    <i class="fas fa-user-shield" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c1.8 0 3.5-.2 5.3-.5c-76.3-55.1-99.8-141-103.1-200.2c-16.1-4.8-33.1-7.3-50.7-7.3H178.3zm308.8-78.3l-120 48C358 277.4 352 286.2 352 296c0 63.3 25.9 168.8 134.8 214.2c5.9 2.5 12.6 2.5 18.5 0C614.1 464.8 640 359.3 640 296c0-9.8-6-18.6-15.1-22.3l-120-48c-5.7-2.3-12.1-2.3-17.8 0zM591.4 312c-3.9 50.7-27.2 116.7-95.4 149.7V273.8L591.4 312z"/></svg></i><span><?= $current_lang === 'en' ? 'Administration' : 'ڕێکخستن' ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <form class="feedback-wrap" id="feedback" method="post" action="send_feedback.php" lang="<?= $current_lang === 'en' ? 'en' : 'ku' ?>">
            <label for="feedbackMessage"><?= feedback_text('فرێکرنا تێبینیێن هەوە', 'Send feedback') ?></label>
            <div class="feedback-compose">
                <textarea id="feedbackMessage" name="message" rows="1" maxlength="2000" required placeholder="<?= feedback_text('تێبینیا خۆ ل ڤێرێ بنڤیسە…', 'Write your feedback…') ?>" aria-describedby="feedbackStatus feedbackCount"><?= feedback_h($feedbackDraft) ?></textarea>
                <button class="feedback-send" type="submit" id="feedbackSend"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.7 2.2a.5.5 0 0 0-.7.6L5 11l10 1-10 1-3 8.2a.5.5 0 0 0 .7.6l19-9.3a.55.55 0 0 0 0-1Z"/></svg><span><?= feedback_text('فرێکرن', 'Send') ?></span></button>
            </div>
            <input type="hidden" name="csrf" value="<?= feedback_h($feedbackToken) ?>">
            <input type="hidden" name="gate_csrf" value="<?= feedback_h($feedbackGate) ?>">
            <input type="hidden" name="submission_key" id="feedbackKey" value="<?= feedback_h($feedbackKey) ?>">
            <div class="feedback-access" id="feedbackAccess" hidden><label for="feedbackAccessKey"><?= feedback_text('کلیلا چوونا ژوور', 'Access key') ?></label><input id="feedbackAccessKey" name="access_key" type="password" maxlength="255" autocomplete="current-password" autocapitalize="none" spellcheck="false" dir="ltr" placeholder="<?= feedback_text('کلیلا خۆ بنڤیسە پاشی فرێکرن لێبدە', 'Enter your key, then press Send') ?>"></div>
            <div class="feedback-meta"><p class="feedback-status" id="feedbackStatus" role="status" aria-live="polite" data-state="<?= $feedbackNotice ? ($feedbackNotice['ok'] ? 'success' : 'error') : '' ?>"><?= feedback_h($feedbackNotice['message'] ?? '') ?></p><span id="feedbackCount" class="feedback-count" dir="ltr">0 / 2000</span></div>
        </form>

        <div id="clan1" role="tabpanel" aria-labelledby="clan1Tab" class="dashboard-grid-custom section-content active-section">
            <div class="glass-card">
                <a href="relationship.php">
                    <div class="icon-box"><i class="fas fa-route" style="color:#8bd8bd;filter:drop-shadow(0 0 8px rgba(139,216,189,0.35))" aria-hidden="true"><svg class="home-icon" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M512 96c0 50.2-59.1 125.1-84.6 155c-3.8 4.4-9.4 6.1-14.5 5H320c-17.7 0-32 14.3-32 32s14.3 32 32 32h96c53 0 96 43 96 96s-43 96-96 96H139.6c8.7-9.9 19.3-22.6 30-36.8c6.3-8.4 12.8-17.6 19-27.2H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320c-53 0-96-43-96-96s43-96 96-96h39.8c-21-31.5-39.8-67.7-39.8-96c0-53 43-96 96-96s96 43 96 96zM117.1 489.1c-3.8 4.3-7.2 8.1-10.1 11.3l-1.8 2-.2-.2c-6 4.6-14.6 4-20-1.8C59.8 473 0 402.5 0 352c0-53 43-96 96-96s96 43 96 96c0 30-21.1 67-43.5 97.9c-10.7 14.7-21.7 28-30.8 38.5l-.6 .7zM128 352a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zM416 128a32 32 0 1 0 0-64 32 32 0 1 0 0 64z"/></svg></i></div>
                    <h4><?= get_title('relationship_title', 'پەیوەندییا خزماتیێ', 'Family Connection') ?></h4>
                </a>
            </div>
            <div class="glass-card">
                <a href="tree.php">
                    <div class="icon-box"><i class="fas fa-network-wired text-gradient-light" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M256 64H384v64H256V64zM240 0c-26.5 0-48 21.5-48 48v96c0 26.5 21.5 48 48 48h48v32H32c-17.7 0-32 14.3-32 32s14.3 32 32 32h96v32H80c-26.5 0-48 21.5-48 48v96c0 26.5 21.5 48 48 48H240c26.5 0 48-21.5 48-48V368c0-26.5-21.5-48-48-48H192V288H448v32H400c-26.5 0-48 21.5-48 48v96c0 26.5 21.5 48 48 48H560c26.5 0 48-21.5 48-48V368c0-26.5-21.5-48-48-48H512V288h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H352V192h48c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48H240zM96 448V384H224v64H96zm320-64H544v64H416V384z"/></svg></i></div>
                    <h4><?= get_title('tree_title', 'زنجیرا بنەمالێ', 'Family Tree') ?></h4>
                </a>
            </div>
            <div class="glass-card">
                <a href="natural_tree.php">
                    <div class="icon-box"><i class="fas fa-tree" style="color:#7fd17f;filter:drop-shadow(0 0 8px rgba(127,209,127,0.45))" aria-hidden="true"><svg class="home-icon" viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 2c-2.6 0-4.6 1.9-4.9 4.3A4.5 4.5 0 0 0 4 10.6a4.5 4.5 0 0 0 3.2 4.3A4 4 0 0 0 11 18v4h2v-4a4 4 0 0 0 3.8-3.1A4.5 4.5 0 0 0 20 10.6a4.5 4.5 0 0 0-3.1-4.3C16.6 3.9 14.6 2 12 2Zm-1 10.6-2.2-1.7 1.2-1.6 1 .8V8h2v3.1l1-.8 1.2 1.6-2.2 1.7V16h-2Z"/></svg></i></div>
                    <h4><?= get_title('natural_tree_title', 'دارا سروشتی یا بنەمالێ', 'Natural Family Tree') ?></h4>
                </a>
            </div>
          
            <div class="glass-card">
                <a href="name_stats.php">
                    <div class="icon-box"><i class="fas fa-chart-bar" style="color:#60a5fa;filter:drop-shadow(0 0 8px rgba(96,165,250,0.5))" aria-hidden="true"><svg class="home-icon" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M32 32c17.7 0 32 14.3 32 32V400c0 8.8 7.2 16 16 16H480c17.7 0 32 14.3 32 32s-14.3 32-32 32H80c-44.2 0-80-35.8-80-80V64C0 46.3 14.3 32 32 32zm96 96c0-17.7 14.3-32 32-32l192 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-192 0c-17.7 0-32-14.3-32-32zm32 64H288c17.7 0 32 14.3 32 32s-14.3 32-32 32H160c-17.7 0-32-14.3-32-32s14.3-32 32-32zm0 96H416c17.7 0 32 14.3 32 32s-14.3 32-32 32H160c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg></i></div>
                    <h4><?= get_title('name_stats_title', 'ئامارێت ناڤان', 'Name Statistics') ?></h4>
                </a>
            </div>
             <div class="glass-card">
                    <a href="family_list.php">
                        <div class="icon-box"><i class="fas fa-clipboard-list text-gradient-primary" aria-hidden="true"><svg class="home-icon" viewBox="0 0 384 512" focusable="false" aria-hidden="true"><path d="M192 0c-41.8 0-77.4 26.7-90.5 64H64C28.7 64 0 92.7 0 128V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H282.5C269.4 26.7 233.8 0 192 0zm0 64a32 32 0 1 1 0 64 32 32 0 1 1 0-64zM72 272a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm104-16H304c8.8 0 16 7.2 16 16s-7.2 16-16 16H176c-8.8 0-16-7.2-16-16s7.2-16 16-16zM72 368a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zm88 0c0-8.8 7.2-16 16-16H304c8.8 0 16 7.2 16 16s-7.2 16-16 16H176c-8.8 0-16-7.2-16-16z"/></svg></i></div>
                        <h4><?= get_title('family_list_title', 'لیستە', 'Family List') ?></h4>
                    </a>
                </div>
                 <div class="glass-card">
                    <a href="dashboard.php">
                        <div class="icon-box"><i class="fas fa-chart-pie text-gradient-info" aria-hidden="true"><svg class="home-icon" viewBox="0 0 576 512" focusable="false" aria-hidden="true"><path d="M304 240V16.6c0-9 7-16.6 16-16.6C443.7 0 544 100.3 544 224c0 9-7.6 16-16.6 16H304zM32 272C32 150.7 122.1 50.3 239 34.3c9.2-1.3 17 6.1 17 15.4V288L412.5 444.5c6.7 6.7 6.2 17.7-1.5 23.1C371.8 495.6 323.8 512 272 512C139.5 512 32 404.6 32 272zm526.4 16c9.3 0 16.6 7.8 15.4 17c-7.7 55.9-34.6 105.6-73.9 142.3c-6 5.6-15.4 5.2-21.2-.7L320 288H558.4z"/></svg></i></div>
                        <h4><?= get_title('dashboard_title', 'راپۆرت', 'Reports') ?></h4>
                    </a>
                </div>

                <div class="glass-card">
                    <a href="groups_view.php">
                        <div class="icon-box"><i class="fas fa-users-viewfinder text-gradient-warning" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M48 48h88c13.3 0 24-10.7 24-24s-10.7-24-24-24H32C14.3 0 0 14.3 0 32V136c0 13.3 10.7 24 24 24s24-10.7 24-24V48zM175.8 224a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm-26.5 32C119.9 256 96 279.9 96 309.3c0 14.7 11.9 26.7 26.7 26.7h56.1c8-34.1 32.8-61.7 65.2-73.6c-7.5-4.1-16.2-6.4-25.3-6.4H149.3zm368 80c14.7 0 26.7-11.9 26.7-26.7c0-29.5-23.9-53.3-53.3-53.3H421.3c-9.2 0-17.8 2.3-25.3 6.4c32.4 11.9 57.2 39.5 65.2 73.6h56.1zm-89.4 0c-8.6-24.3-29.9-42.6-55.9-47c-3.9-.7-7.9-1-12-1H280c-4.1 0-8.1 .3-12 1c-26 4.4-47.3 22.7-55.9 47c-2.7 7.5-4.1 15.6-4.1 24c0 13.3 10.7 24 24 24H408c13.3 0 24-10.7 24-24c0-8.4-1.4-16.5-4.1-24zM464 224a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm-80-32a64 64 0 1 0 -128 0 64 64 0 1 0 128 0zM504 48h88v88c0 13.3 10.7 24 24 24s24-10.7 24-24V32c0-17.7-14.3-32-32-32H504c-13.3 0-24 10.7-24 24s10.7 24 24 24zM48 464V376c0-13.3-10.7-24-24-24s-24 10.7-24 24V480c0 17.7 14.3 32 32 32H136c13.3 0 24-10.7 24-24s-10.7-24-24-24H48zm456 0c-13.3 0-24 10.7-24 24s10.7 24 24 24H608c17.7 0 32-14.3 32-32V376c0-13.3-10.7-24-24-24s-24 10.7-24 24v88H504z"/></svg></i></div>
                        <h4><?= get_title('groups_view_title', 'گروپ', 'Groups') ?></h4>
                    </a>
                </div>

            <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <div class="glass-card">
                    <a href="add_person.php">
                        <div class="icon-box"><i class="fas fa-user-plus text-gradient-success" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM504 312V248H440c-13.3 0-24-10.7-24-24s10.7-24 24-24h64V136c0-13.3 10.7-24 24-24s24 10.7 24 24v64h64c13.3 0 24 10.7 24 24s-10.7 24-24 24H552v64c0 13.3-10.7 24-24 24s-24-10.7-24-24z"/></svg></i></div>
                        <h4><?= get_title('add_person_title', 'زێدەکرن', 'Add Person') ?></h4>
                    </a>
                </div>
                <div class="glass-card">
                    <a href="groups_manage.php">
                        <div class="icon-box"><i class="fas fa-users-cog text-gradient-light" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M144 160A80 80 0 1 0 144 0a80 80 0 1 0 0 160zm368 0A80 80 0 1 0 512 0a80 80 0 1 0 0 160zM0 298.7C0 310.4 9.6 320 21.3 320H234.7c.2 0 .4 0 .7 0c-26.6-23.5-43.3-57.8-43.3-96c0-7.6 .7-15 1.9-22.3c-13.6-6.3-28.7-9.7-44.6-9.7H106.7C47.8 192 0 239.8 0 298.7zM320 320c24 0 45.9-8.8 62.7-23.3c2.5-3.7 5.2-7.3 8-10.7c2.7-3.3 5.7-6.1 9-8.3C410 262.3 416 243.9 416 224c0-53-43-96-96-96s-96 43-96 96s43 96 96 96zm65.4 60.2c-10.3-5.9-18.1-16.2-20.8-28.2H261.3C187.7 352 128 411.7 128 485.3c0 14.7 11.9 26.7 26.7 26.7H455.2c-2.1-5.2-3.2-10.9-3.2-16.4v-3c-1.3-.7-2.7-1.5-4-2.3l-2.6 1.5c-16.8 9.7-40.5 8-54.7-9.7c-4.5-5.6-8.6-11.5-12.4-17.6l-.1-.2-.1-.2-2.4-4.1-.1-.2-.1-.2c-3.4-6.2-6.4-12.6-9-19.3c-8.2-21.2 2.2-42.6 19-52.3l2.7-1.5c0-.8 0-1.5 0-2.3s0-1.5 0-2.3l-2.7-1.5zM533.3 192H490.7c-15.9 0-31 3.5-44.6 9.7c1.3 7.2 1.9 14.7 1.9 22.3c0 17.4-3.5 33.9-9.7 49c2.5 .9 4.9 2 7.1 3.3l2.6 1.5c1.3-.8 2.6-1.6 4-2.3v-3c0-19.4 13.3-39.1 35.8-42.6c7.9-1.2 16-1.9 24.2-1.9s16.3 .6 24.2 1.9c22.5 3.5 35.8 23.2 35.8 42.6v3c1.3 .7 2.7 1.5 4 2.3l2.6-1.5c16.8-9.7 40.5-8 54.7 9.7c2.3 2.8 4.5 5.8 6.6 8.7c-2.1-57.1-49-102.7-106.6-102.7zm91.3 163.9c6.3-3.6 9.5-11.1 6.8-18c-2.1-5.5-4.6-10.8-7.4-15.9l-2.3-4c-3.1-5.1-6.5-9.9-10.2-14.5c-4.6-5.7-12.7-6.7-19-3L574.4 311c-8.9-7.6-19.1-13.6-30.4-17.6v-21c0-7.3-4.9-13.8-12.1-14.9c-6.5-1-13.1-1.5-19.9-1.5s-13.4 .5-19.9 1.5c-7.2 1.1-12.1 7.6-12.1 14.9v21c-11.2 4-21.5 10-30.4 17.6l-18.2-10.5c-6.3-3.6-14.4-2.6-19 3c-3.7 4.6-7.1 9.5-10.2 14.6l-2.3 3.9c-2.8 5.1-5.3 10.4-7.4 15.9c-2.6 6.8 .5 14.3 6.8 17.9l18.2 10.5c-1 5.7-1.6 11.6-1.6 17.6s.6 11.9 1.6 17.5l-18.2 10.5c-6.3 3.6-9.5 11.1-6.8 17.9c2.1 5.5 4.6 10.7 7.4 15.8l2.4 4.1c3 5.1 6.4 9.9 10.1 14.5c4.6 5.7 12.7 6.7 19 3L449.6 457c8.9 7.6 19.2 13.6 30.4 17.6v21c0 7.3 4.9 13.8 12.1 14.9c6.5 1 13.1 1.5 19.9 1.5s13.4-.5 19.9-1.5c7.2-1.1 12.1-7.6 12.1-14.9v-21c11.2-4 21.5-10 30.4-17.6l18.2 10.5c6.3 3.6 14.4 2.6 19-3c3.7-4.6 7.1-9.4 10.1-14.5l2.4-4.2c2.8-5.1 5.3-10.3 7.4-15.8c2.6-6.8-.5-14.3-6.8-17.9l-18.2-10.5c1-5.7 1.6-11.6 1.6-17.5s-.6-11.9-1.6-17.6l18.2-10.5zM472 384a40 40 0 1 1 80 0 40 40 0 1 1 -80 0z"/></svg></i></div>
                        <h4><?= get_title('groups_manage_title', 'ئیدارەکرنا گروپا', 'Manage Groups') ?></h4>
                    </a>
                </div>
                 <div class="glass-card">
                    <a href="trip_groups.php">
                        <div class="icon-box"><i class="fas fa-route text-gradient-success" aria-hidden="true"><svg class="home-icon" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M512 96c0 50.2-59.1 125.1-84.6 155c-3.8 4.4-9.4 6.1-14.5 5H320c-17.7 0-32 14.3-32 32s14.3 32 32 32h96c53 0 96 43 96 96s-43 96-96 96H139.6c8.7-9.9 19.3-22.6 30-36.8c6.3-8.4 12.8-17.6 19-27.2H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320c-53 0-96-43-96-96s43-96 96-96h39.8c-21-31.5-39.8-67.7-39.8-96c0-53 43-96 96-96s96 43 96 96zM117.1 489.1c-3.8 4.3-7.2 8.1-10.1 11.3l-1.8 2-.2-.2c-6 4.6-14.6 4-20-1.8C59.8 473 0 402.5 0 352c0-53 43-96 96-96s96 43 96 96c0 30-21.1 67-43.5 97.9c-10.7 14.7-21.7 28-30.8 38.5l-.6 .7zM128 352a32 32 0 1 0 -64 0 32 32 0 1 0 64 0zM416 128a32 32 0 1 0 0-64 32 32 0 1 0 0 64z"/></svg></i></div>
                        <h4><?= get_title('trip_groups_title', 'گروپ - سەیران', 'Trip Groups') ?></h4>
                    </a>
                </div>
                <div class="glass-card">
                    <a href="link_father.php">
                        <div class="icon-box"><i class="fas fa-link text-gradient-primary" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M579.8 267.7c56.5-56.5 56.5-148 0-204.5c-50-50-128.8-56.5-186.3-15.4l-1.6 1.1c-14.4 10.3-17.7 30.3-7.4 44.6s30.3 17.7 44.6 7.4l1.6-1.1c32.1-22.9 76-19.3 103.8 8.6c31.5 31.5 31.5 82.5 0 114L422.3 334.8c-31.5 31.5-82.5 31.5-114 0c-27.9-27.9-31.5-71.8-8.6-103.8l1.1-1.6c10.3-14.4 6.9-34.4-7.4-44.6s-34.4-6.9-44.6 7.4l-1.1 1.6C206.5 251.2 213 330 263 380c56.5 56.5 148 56.5 204.5 0L579.8 267.7zM60.2 244.3c-56.5 56.5-56.5 148 0 204.5c50 50 128.8 56.5 186.3 15.4l1.6-1.1c14.4-10.3 17.7-30.3 7.4-44.6s-30.3-17.7-44.6-7.4l-1.6 1.1c-32.1 22.9-76 19.3-103.8-8.6C74 372 74 321 105.5 289.5L217.7 177.2c31.5-31.5 82.5-31.5 114 0c27.9 27.9 31.5 71.8 8.6 103.9l-1.1 1.6c-10.3 14.4-6.9 34.4 7.4 44.6s34.4 6.9 44.6-7.4l1.1-1.6C433.5 260.8 427 182 377 132c-56.5-56.5-148-56.5-204.5 0L60.2 244.3z"/></svg></i></div>
                        <h4><?= get_title('link_father_title', 'بەستنا بابی', 'Link Father') ?></h4>
                    </a>
                </div>
                <div class="glass-card">
                    <a href="manage_marriages.php">
                        <div class="icon-box"><i class="fas fa-ring text-gradient-info" aria-hidden="true"><svg class="home-icon" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M64 208c0 7.8 4.4 18.7 17.1 30.3C126.5 214.1 188.9 200 256 200s129.5 14.1 174.9 38.3C443.6 226.7 448 215.8 448 208c0-12.3-10.8-32-47.9-50.6C364.9 139.8 314 128 256 128s-108.9 11.8-144.1 29.4C74.8 176 64 195.7 64 208zm192 40c-47 0-89.3 7.6-122.9 19.7C166.3 280.2 208.8 288 256 288s89.7-7.8 122.9-20.3C345.3 255.6 303 248 256 248zM0 208c0-49.6 39.4-85.8 83.3-107.8C129.1 77.3 190.3 64 256 64s126.9 13.3 172.7 36.2c43.9 22 83.3 58.2 83.3 107.8v96c0 49.6-39.4 85.8-83.3 107.8C382.9 434.7 321.7 448 256 448s-126.9-13.3-172.7-36.2C39.4 389.8 0 353.6 0 304V208z"/></svg></i></div>
                        <h4><?= get_title('manage_marriages_title', 'ڕێکخستنا هەڤژینیێ', 'Manage Marriages') ?></h4>
                    </a>
                </div>
                 <div class="glass-card">
                    <a href="set_mother.php">
                        <div class="icon-box"><i class="fas fa-female text-gradient-warning" aria-hidden="true"><svg class="home-icon" viewBox="0 0 320 512" focusable="false" aria-hidden="true"><path d="M160 0a48 48 0 1 1 0 96 48 48 0 1 1 0-96zM88 384H70.2c-10.9 0-18.6-10.7-15.2-21.1L93.3 248.1 59.4 304.5c-9.1 15.1-28.8 20-43.9 10.9s-20-28.8-10.9-43.9l53.6-89.2c20.3-33.7 56.7-54.3 96-54.3h11.6c39.3 0 75.7 20.6 96 54.3l53.6 89.2c9.1 15.1 4.2 34.8-10.9 43.9s-34.8 4.2-43.9-10.9l-33.9-56.3L265 362.9c3.5 10.4-4.3 21.1-15.2 21.1H232v96c0 17.7-14.3 32-32 32s-32-14.3-32-32V384H152v96c0 17.7-14.3 32-32 32s-32-14.3-32-32V384z"/></svg></i></div>
                        <h4><?= get_title('set_mother_title', 'بەستنا دایكێ', 'Link Mother') ?></h4>
                    </a>
                </div>
                <div class="glass-card">
                    <a href="family_list_tree.php">
                        <div class="icon-box"><i class="fas fa-tree text-gradient-success" aria-hidden="true"><svg class="home-icon" viewBox="0 0 448 512" focusable="false" aria-hidden="true"><path d="M210.6 5.9L62 169.4c-3.9 4.2-6 9.8-6 15.5C56 197.7 66.3 208 79.1 208H104L30.6 281.4c-4.2 4.2-6.6 10-6.6 16C24 309.9 34.1 320 46.6 320H80L5.4 409.5C1.9 413.7 0 419 0 424.5c0 13 10.5 23.5 23.5 23.5H192v32c0 17.7 14.3 32 32 32s32-14.3 32-32V448H424.5c13 0 23.5-10.5 23.5-23.5c0-5.5-1.9-10.8-5.4-15L368 320h33.4c12.5 0 22.6-10.1 22.6-22.6c0-6-2.4-11.8-6.6-16L344 208h24.9c12.7 0 23.1-10.3 23.1-23.1c0-5.7-2.1-11.3-6-15.5L237.4 5.9C234 2.1 229.1 0 224 0s-10 2.1-13.4 5.9z"/></svg></i></div>
                        <h4><?= get_title('family_list_tree_title', 'لیستا زنجیرا بنەمالێ', 'Family Records') ?></h4>
                    </a>
                </div>

                <div class="glass-card">
                    <a href="add_person.php?tab=edit">
                        <div class="icon-box"><i class="fas fa-user-pen text-gradient-warning" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H322.8c-3.1-8.8-3.7-18.4-1.4-27.8l15-60.1c2.8-11.3 8.6-21.5 16.8-29.7l40.3-40.3c-32.1-31-75.7-50.1-123.9-50.1H178.3zm435.5-68.3c-15.6-15.6-40.9-15.6-56.6 0l-29.4 29.4 71 71 29.4-29.4c15.6-15.6 15.6-40.9 0-56.6l-14.4-14.4zM375.9 417c-4.1 4.1-7 9.2-8.4 14.9l-15 60.1c-1.4 5.5 .2 11.2 4.2 15.2s9.7 5.6 15.2 4.2l60.1-15c5.6-1.4 10.8-4.3 14.9-8.4L576.1 358.7l-71-71L375.9 417z"/></svg></i></div>
                        <h4><?= get_title('update_person_title', 'گوهرین', 'Edit Person') ?></h4>
                    </a>
                </div>
                
                <div class="glass-card">
                    <a href="delete_person.php">
                        <div class="icon-box"><i class="fas fa-user-xmark text-gradient-danger" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3zM471 143c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/></svg></i></div>
                        <h4><?= get_title('delete_person_title', 'ڕێکخستن و ژێبرنا کەسان', 'Manage and Delete People') ?></h4>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (function_exists('isAdmin') && isAdmin()): ?>
        <div id="admin" role="tabpanel" aria-labelledby="adminTab" hidden class="dashboard-grid-custom section-content">
            <div class="glass-card">
                <a href="admin_feedback.php">
                    <div class="icon-box" style="color:#8bd8bd"><svg class="home-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 2h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H9l-6 4v-4a2 2 0 0 1-1-2V4a2 2 0 0 1 2-2Zm2 5v2h12V7H6Zm0 4v2h9v-2H6Z"/></svg></div>
                    <h4><?= get_title('feedback_title', 'تێبینیێن بکارهێنەران', 'User Feedback') ?></h4>
                </a>
            </div>
            <div class="glass-card">
                <a href="users_management.php">
                    <div class="icon-box"><i class="fas fa-users-gear text-gradient-light" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M144 160A80 80 0 1 0 144 0a80 80 0 1 0 0 160zm368 0A80 80 0 1 0 512 0a80 80 0 1 0 0 160zM0 298.7C0 310.4 9.6 320 21.3 320H234.7c.2 0 .4 0 .7 0c-26.6-23.5-43.3-57.8-43.3-96c0-7.6 .7-15 1.9-22.3c-13.6-6.3-28.7-9.7-44.6-9.7H106.7C47.8 192 0 239.8 0 298.7zM320 320c24 0 45.9-8.8 62.7-23.3c2.5-3.7 5.2-7.3 8-10.7c2.7-3.3 5.7-6.1 9-8.3C410 262.3 416 243.9 416 224c0-53-43-96-96-96s-96 43-96 96s43 96 96 96zm65.4 60.2c-10.3-5.9-18.1-16.2-20.8-28.2H261.3C187.7 352 128 411.7 128 485.3c0 14.7 11.9 26.7 26.7 26.7H455.2c-2.1-5.2-3.2-10.9-3.2-16.4v-3c-1.3-.7-2.7-1.5-4-2.3l-2.6 1.5c-16.8 9.7-40.5 8-54.7-9.7c-4.5-5.6-8.6-11.5-12.4-17.6l-.1-.2-.1-.2-2.4-4.1-.1-.2-.1-.2c-3.4-6.2-6.4-12.6-9-19.3c-8.2-21.2 2.2-42.6 19-52.3l2.7-1.5c0-.8 0-1.5 0-2.3s0-1.5 0-2.3l-2.7-1.5zM533.3 192H490.7c-15.9 0-31 3.5-44.6 9.7c1.3 7.2 1.9 14.7 1.9 22.3c0 17.4-3.5 33.9-9.7 49c2.5 .9 4.9 2 7.1 3.3l2.6 1.5c1.3-.8 2.6-1.6 4-2.3v-3c0-19.4 13.3-39.1 35.8-42.6c7.9-1.2 16-1.9 24.2-1.9s16.3 .6 24.2 1.9c22.5 3.5 35.8 23.2 35.8 42.6v3c1.3 .7 2.7 1.5 4 2.3l2.6-1.5c16.8-9.7 40.5-8 54.7 9.7c2.3 2.8 4.5 5.8 6.6 8.7c-2.1-57.1-49-102.7-106.6-102.7zm91.3 163.9c6.3-3.6 9.5-11.1 6.8-18c-2.1-5.5-4.6-10.8-7.4-15.9l-2.3-4c-3.1-5.1-6.5-9.9-10.2-14.5c-4.6-5.7-12.7-6.7-19-3L574.4 311c-8.9-7.6-19.1-13.6-30.4-17.6v-21c0-7.3-4.9-13.8-12.1-14.9c-6.5-1-13.1-1.5-19.9-1.5s-13.4 .5-19.9 1.5c-7.2 1.1-12.1 7.6-12.1 14.9v21c-11.2 4-21.5 10-30.4 17.6l-18.2-10.5c-6.3-3.6-14.4-2.6-19 3c-3.7 4.6-7.1 9.5-10.2 14.6l-2.3 3.9c-2.8 5.1-5.3 10.4-7.4 15.9c-2.6 6.8 .5 14.3 6.8 17.9l18.2 10.5c-1 5.7-1.6 11.6-1.6 17.6s.6 11.9 1.6 17.5l-18.2 10.5c-6.3 3.6-9.5 11.1-6.8 17.9c2.1 5.5 4.6 10.7 7.4 15.8l2.4 4.1c3 5.1 6.4 9.9 10.1 14.5c4.6 5.7 12.7 6.7 19 3L449.6 457c8.9 7.6 19.2 13.6 30.4 17.6v21c0 7.3 4.9 13.8 12.1 14.9c6.5 1 13.1 1.5 19.9 1.5s13.4-.5 19.9-1.5c7.2-1.1 12.1-7.6 12.1-14.9v-21c11.2-4 21.5-10 30.4-17.6l18.2 10.5c6.3 3.6 14.4 2.6 19-3c3.7-4.6 7.1-9.4 10.1-14.5l2.4-4.2c2.8-5.1 5.3-10.3 7.4-15.8c2.6-6.8-.5-14.3-6.8-17.9l-18.2-10.5c1-5.7 1.6-11.6 1.6-17.5s-.6-11.9-1.6-17.6l18.2-10.5zM472 384a40 40 0 1 1 80 0 40 40 0 1 1 -80 0z"/></svg></i></div>
                    <h4><?= get_title('users_management_title', 'کلیلێن چوونا ژوور', 'Access Keys') ?></h4>
                </a>
            </div>
            <div class="glass-card">
                <a href="manage_titles.php">
                    <div class="icon-box"><i class="fas fa-screwdriver-wrench text-gradient-warning" aria-hidden="true"><svg class="home-icon" viewBox="0 0 512 512" focusable="false" aria-hidden="true"><path d="M78.6 5C69.1-2.4 55.6-1.5 47 7L7 47c-8.5 8.5-9.4 22-2.1 31.6l80 104c4.5 5.9 11.6 9.4 19 9.4h54.1l109 109c-14.7 29-10 65.4 14.3 89.6l112 112c12.5 12.5 32.8 12.5 45.3 0l64-64c12.5-12.5 12.5-32.8 0-45.3l-112-112c-24.2-24.2-60.6-29-89.6-14.3l-109-109V104c0-7.5-3.5-14.5-9.4-19L78.6 5zM19.9 396.1C7.2 408.8 0 426.1 0 444.1C0 481.6 30.4 512 67.9 512c18 0 35.3-7.2 48-19.9L233.7 374.3c-7.8-20.9-9-43.6-3.6-65.1l-61.7-61.7L19.9 396.1zM512 144c0-10.5-1.1-20.7-3.2-30.5c-2.4-11.2-16.1-14.1-24.2-6l-63.9 63.9c-3 3-7.1 4.7-11.3 4.7H352c-8.8 0-16-7.2-16-16V102.6c0-4.2 1.7-8.3 4.7-11.3l63.9-63.9c8.1-8.1 5.2-21.8-6-24.2C388.7 1.1 378.5 0 368 0C288.5 0 224 64.5 224 144l0 .8 85.3 85.3c36-9.1 75.8 .5 104 28.7L429 274.5c49-23 83-72.8 83-130.5zM56 432a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z"/></svg></i></div>
                    <h4><?= get_title('manage_titles_title', 'ئیدارەکرنا ناڤان', 'Manage Titles') ?></h4>
                </a>
            </div>
            <div class="glass-card">
                <a href="translate_names.php">
                    <div class="icon-box"><i class="fas fa-language text-gradient-info" aria-hidden="true"><svg class="home-icon" viewBox="0 0 640 512" focusable="false" aria-hidden="true"><path d="M0 128C0 92.7 28.7 64 64 64H256h48 16H576c35.3 0 64 28.7 64 64V384c0 35.3-28.7 64-64 64H320 304 256 64c-35.3 0-64-28.7-64-64V128zm320 0V384H576V128H320zM178.3 175.9c-3.2-7.2-10.4-11.9-18.3-11.9s-15.1 4.7-18.3 11.9l-64 144c-4.5 10.1 .1 21.9 10.2 26.4s21.9-.1 26.4-10.2l8.9-20.1h73.6l8.9 20.1c4.5 10.1 16.3 14.6 26.4 10.2s14.6-16.3 10.2-26.4l-64-144zM160 233.2L179 276H141l19-42.8zM448 164c11 0 20 9 20 20v4h44 16c11 0 20 9 20 20s-9 20-20 20h-2l-1.6 4.5c-8.9 24.4-22.4 46.6-39.6 65.4c.9 .6 1.8 1.1 2.7 1.6l18.9 11.3c9.5 5.7 12.5 18 6.9 27.4s-18 12.5-27.4 6.9l-18.9-11.3c-4.5-2.7-8.8-5.5-13.1-8.5c-10.6 7.5-21.9 14-34 19.4l-3.6 1.6c-10.1 4.5-21.9-.1-26.4-10.2s.1-21.9 10.2-26.4l3.6-1.6c6.4-2.9 12.6-6.1 18.5-9.8l-12.2-12.2c-7.8-7.8-7.8-20.5 0-28.3s20.5-7.8 28.3 0l14.6 14.6 .5 .5c12.4-13.1 22.5-28.3 29.8-45H448 376c-11 0-20-9-20-20s9-20 20-20h52v-4c0-11 9-20 20-20z"/></svg></i></div>
                    <h4><?= get_title('translate_names_title', 'ناڤ ب ئینگلیزی', 'English Names') ?></h4>
                </a>
            </div>
            <div class="glass-card">
                <a href="auto_fill_names.php">
                    <div class="icon-box"><i class="fas fa-wand-magic-sparkles text-gradient-success" aria-hidden="true"><svg class="home-icon" viewBox="0 0 576 512" focusable="false" aria-hidden="true"><path d="M234.7 42.7L197 56.8c-3 1.1-5 4-5 7.2s2 6.1 5 7.2l37.7 14.1L248.8 123c1.1 3 4 5 7.2 5s6.1-2 7.2-5l14.1-37.7L315 71.2c3-1.1 5-4 5-7.2s-2-6.1-5-7.2L277.3 42.7 263.2 5c-1.1-3-4-5-7.2-5s-6.1 2-7.2 5L234.7 42.7zM46.1 395.4c-18.7 18.7-18.7 49.1 0 67.9l34.6 34.6c18.7 18.7 49.1 18.7 67.9 0L529.9 116.5c18.7-18.7 18.7-49.1 0-67.9L495.3 14.1c-18.7-18.7-49.1-18.7-67.9 0L46.1 395.4zM484.6 82.6l-105 105-23.3-23.3 105-105 23.3 23.3zM7.5 117.2C3 118.9 0 123.2 0 128s3 9.1 7.5 10.8L64 160l21.2 56.5c1.7 4.5 6 7.5 10.8 7.5s9.1-3 10.8-7.5L128 160l56.5-21.2c4.5-1.7 7.5-6 7.5-10.8s-3-9.1-7.5-10.8L128 96 106.8 39.5C105.1 35 100.8 32 96 32s-9.1 3-10.8 7.5L64 96 7.5 117.2zm352 256c-4.5 1.7-7.5 6-7.5 10.8s3 9.1 7.5 10.8L416 416l21.2 56.5c1.7 4.5 6 7.5 10.8 7.5s9.1-3 10.8-7.5L480 416l56.5-21.2c4.5-1.7 7.5-6 7.5-10.8s-3-9.1-7.5-10.8L480 352l-21.2-56.5c-1.7-4.5-6-7.5-10.8-7.5s-9.1 3-10.8 7.5L416 352l-56.5 21.2z"/></svg></i></div>
                    <h4><?= get_title('auto_fill_names_title', 'تژیكرنا ناڤان', 'Fill Names') ?></h4>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <footer class="text-center text-white">
        <p class="mb-0">mohemmed khalid &copy; 2025 | <?= $current_lang === 'en' ? 'Jotiari Family System' : 'بنەمالا جوتیاری' ?>   </p>
    </footer>

    <script>
        const sectionTabs = [...document.querySelectorAll('.tab-btn')];
        function switchSection(sectionId, btn) {
            const activeSection = document.getElementById(sectionId);
            if (!activeSection || !sectionTabs.includes(btn)) return;
            document.querySelectorAll('.section-content').forEach(section => {
                section.hidden = section !== activeSection;
                section.classList.toggle('active-section', section === activeSection);
            });
            sectionTabs.forEach(tab => {
                tab.classList.toggle('active', tab === btn);
                tab.setAttribute('aria-selected', String(tab === btn));
                tab.tabIndex = tab === btn ? 0 : -1;
            });
        }
        sectionTabs.forEach((tab, index) => tab.addEventListener('keydown', event => {
            let next;
            const rtl = document.documentElement.dir === 'rtl';
            if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = sectionTabs.length - 1;
            else if (event.key === 'ArrowRight') next = (index + (rtl ? -1 : 1) + sectionTabs.length) % sectionTabs.length;
            else if (event.key === 'ArrowLeft') next = (index + (rtl ? 1 : -1) + sectionTabs.length) % sectionTabs.length;
            else return;
            event.preventDefault();
            const target = sectionTabs[next];
            switchSection(target.getAttribute('aria-controls'), target);
            target.focus();
        }));
        const feedbackForm = document.getElementById('feedback');
        const feedbackMessage = document.getElementById('feedbackMessage');
        const feedbackButton = document.getElementById('feedbackSend');
        const feedbackStatus = document.getElementById('feedbackStatus');
        const feedbackKey = document.getElementById('feedbackKey');
        const feedbackLabels = <?= json_encode([
            'sending'=>feedback_text('یێ دهێتە فرێکرن…', 'Sending…'),
            'empty'=>feedback_text('دەستپێکێ تێبینیا خۆ بنڤیسە.', 'Write your feedback first.'),
            'error'=>feedback_text('فرێکرن پشتڕاست نەبوو. تێبینیا تە یا مای، جارەکا دی هەول بدە.', 'Sending could not be confirmed. Your message is still here. Please try again.')
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        function resizeFeedback() {
            feedbackMessage.style.height = 'auto';
            feedbackMessage.style.height = Math.min(144, feedbackMessage.scrollHeight) + 'px';
            document.getElementById('feedbackCount').textContent = feedbackMessage.value.length + ' / 2000';
        }
        feedbackMessage.addEventListener('input', resizeFeedback);
        resizeFeedback();
        if (window.fetch && window.AbortController) feedbackForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (feedbackButton.disabled) return;
            if (!feedbackMessage.value.trim()) {
                feedbackStatus.textContent = feedbackLabels.empty;
                feedbackStatus.dataset.state = 'error';
                feedbackMessage.focus();
                return;
            }
            const body = new FormData(feedbackForm);
            feedbackButton.disabled = true;
            feedbackMessage.readOnly = true;
            feedbackForm.setAttribute('aria-busy', 'true');
            feedbackStatus.dataset.state = '';
            feedbackStatus.textContent = feedbackLabels.sending;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(feedbackForm.action, {method:'POST', body, headers:{Accept:'application/json'}, credentials:'same-origin', signal:controller.signal});
                const result = await response.json();
                if (typeof result.message !== 'string') throw new Error('Invalid response');
                if (typeof result.next_key === 'string' && /^[a-f0-9]{64}$/.test(result.next_key)) feedbackKey.value = result.next_key;
                if (typeof result.csrf === 'string' && /^[a-f0-9]{64}$/.test(result.csrf)) feedbackForm.elements.csrf.value = result.csrf;
                const sent = response.ok && result.ok === true;
                feedbackStatus.textContent = result.message;
                feedbackStatus.dataset.state = sent ? 'success' : 'error';
                if (result.needs_key === true) {
                    document.getElementById('feedbackAccess').hidden = false;
                    document.getElementById('feedbackAccessKey').focus();
                }
                if (sent) {
                    feedbackMessage.value = '';
                    document.getElementById('feedbackAccessKey').value = '';
                    document.getElementById('feedbackAccess').hidden = true;
                    resizeFeedback();
                }
            } catch (error) {
                feedbackStatus.dataset.state = 'error';
                feedbackStatus.textContent = feedbackLabels.error;
            } finally {
                clearTimeout(timeout);
                feedbackButton.disabled = false;
                feedbackMessage.readOnly = false;
                feedbackForm.removeAttribute('aria-busy');
            }
        });
    </script>
</body>
</html>
