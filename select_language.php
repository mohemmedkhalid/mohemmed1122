<?php
require_once 'config.php';

// ─── المستخدم مسجّل دخوله ───
if (isLoggedIn()) {
    // إعادة تعيين اللغة (قادم من زر التبديل)
    if (isset($_GET['reset'])) {
        unset($_SESSION['lang']);
        // استمر لعرض كارت اختيار اللغة
    }
    // حفظ اللغة المختارة والعودة
    elseif (isset($_POST['lang'])) {
        $lang = in_array($_POST['lang'], ['ku', 'en']) ? $_POST['lang'] : 'ku';
        $_SESSION['lang'] = $lang;
        // العودة للصفحة السابقة أو الرئيسية
        $back = isset($_POST['back']) ? $_POST['back'] : 'index.php';
        // أمان: لا نسمح بروابط خارجية
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php(\?.*)?$/', $back)) {
            $back = 'index.php';
        }
        redirect($back);
    }
    // لا يوجد reset ولا post → الرئيسية مباشرة
    elseif (isset($_SESSION['lang'])) {
        redirect('index.php');
    }
    // لا توجد لغة محفوظة → اعرض كارت الاختيار
}
// ─── المستخدم غير مسجّل ───
else {
    if (isset($_GET['reset'])) {
        unset($_SESSION['lang']);
    }
    if (isset($_POST['lang'])) {
        $lang = in_array($_POST['lang'], ['ku', 'en']) ? $_POST['lang'] : 'ku';
        $_SESSION['lang'] = $lang;
        redirect('login.php');
    }
    if (isset($_SESSION['lang'])) {
        redirect('login.php');
    }
}

// الصفحة المرجعية للرجوع إليها بعد الاختيار
$back_page = isset($_SERVER['HTTP_REFERER']) ? basename(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH)) : 'index.php';
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php$/', $back_page)) $back_page = 'index.php';
$is_logged = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>زمان / Language</title>
    <meta name="theme-color" content="#000000">
    <link rel="apple-touch-icon" href="icon-192.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        @font-face {
            font-family: 'CustomFont';
            src: url('./Speda.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        :root {
            --neon-blue: #c5f8fb;
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.15);
            --card-hover: rgba(255, 255, 255, 0.14);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'CustomFont', 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            position: relative;
        }

        /* خلفية متحركة */
        .bg-orbs {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: float 8s ease-in-out infinite;
        }
        .orb1 {
            width: 350px; height: 350px;
            background: radial-gradient(circle, rgba(197,248,251,0.15), transparent 70%);
            top: -100px; right: -100px;
            animation-delay: 0s;
        }
        .orb2 {
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(100,160,255,0.12), transparent 70%);
            bottom: -80px; left: -80px;
            animation-delay: -4s;
        }
        .orb3 {
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(255,200,100,0.08), transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -2s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-20px) scale(1.05); }
        }

        .container {
            position: relative;
            z-index: 10;
            width: 90%;
            max-width: 460px;
            text-align: center;
        }

        /* شعار / أيقونة */
        .logo-wrap {
            margin-bottom: 35px;
            animation: slideDown 0.7s ease-out;
        }
        .logo-icon {
            width: 80px; height: 80px;
            border-radius: 22px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(20px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .logo-icon img {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }
        .logo-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }
        .logo-sub {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            margin-top: 5px;
        }

        /* بطاقة الاختيار */
        .lang-card {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            padding: 40px 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.1);
            animation: zoomIn 0.6s ease-out;
        }

        .lang-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: rgba(255,255,255,0.9);
            margin-bottom: 8px;
        }
        .lang-title .ku { font-family: 'CustomFont', sans-serif; }
        .lang-title .en { font-family: 'Inter', sans-serif; font-size: 0.95rem; color: rgba(255,255,255,0.55); }

        .divider {
            height: 1px;
            background: var(--glass-border);
            margin: 20px 0 28px;
        }

        /* بطاقتا اللغة */
        .lang-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 10px;
        }

        .lang-btn {
            position: relative;
            background: rgba(255,255,255,0.05);
            border: 1.5px solid rgba(255,255,255,0.12);
            border-radius: 18px;
            padding: 28px 18px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            overflow: hidden;
            text-decoration: none;
            color: white;
        }

        .lang-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 18px;
        }

        .lang-btn.ku-btn::before {
            background: linear-gradient(135deg, rgba(197,248,251,0.15), rgba(100,200,255,0.08));
        }
        .lang-btn.en-btn::before {
            background: linear-gradient(135deg, rgba(255,200,100,0.15), rgba(255,150,50,0.08));
        }

        .lang-btn:hover {
            transform: translateY(-6px) scale(1.03);
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 12px 35px rgba(0,0,0,0.3);
        }
        .lang-btn:hover::before { opacity: 1; }

        .lang-btn:active { transform: scale(0.97); }

        .flag-emoji {
            font-size: 2.8rem;
            line-height: 1;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            transition: transform 0.3s ease;
        }
        .lang-btn:hover .flag-emoji { transform: scale(1.15); }

        .lang-name-primary {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
        }
        .lang-name-secondary {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            font-weight: 500;
        }

        .ku-btn .lang-name-primary { font-family: 'CustomFont', sans-serif; }
        .en-btn .lang-name-primary { font-family: 'Inter', sans-serif; }

        .lang-badge {
            position: absolute;
            top: 10px;
            font-size: 0.62rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        /* RTL: right for ku, left for en */
        .ku-btn .lang-badge {
            right: 10px;
            background: rgba(197,248,251,0.2);
            color: #c5f8fb;
            border: 1px solid rgba(197,248,251,0.3);
        }
        .en-btn .lang-badge {
            left: 10px;
            background: rgba(255,200,100,0.2);
            color: #ffc864;
            border: 1px solid rgba(255,200,100,0.3);
        }

        .footer-hint {
            margin-top: 24px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.3);
        }

        @keyframes zoomIn {
            from { transform: scale(0.88); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* تأثير بريق خفيف على البطاقة */
        .lang-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            border-radius: 28px 28px 0 0;
        }
        .lang-card { position: relative; }

        @media (max-width: 380px) {
            .lang-options { grid-template-columns: 1fr; }
            .lang-btn { flex-direction: row; padding: 18px 20px; gap: 16px; }
        }
    </style>
</head>
<body>

<div class="bg-orbs">
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    <div class="orb orb3"></div>
</div>

<div class="container">
    <!-- شعار -->
    <div class="logo-wrap">
        <div class="logo-icon">
            <img src="icon-512.png" alt="Logo">
        </div>
        <div class="logo-title">بنەمالا جوتیاری</div>
        <div class="logo-sub">Jotiari Family System</div>
    </div>

    <!-- بطاقة اختيار اللغة -->
    <div class="lang-card">
        <div class="lang-title">
            <div class="ku">زمانێ خۆ هەلبژێرە</div>
            <div class="en">Choose your language</div>
        </div>
        <div class="divider"></div>

        <div class="lang-options">
            <!-- كوردي -->
            <form method="POST" style="display:contents;">
                <input type="hidden" name="lang" value="ku">
                <?php if ($is_logged): ?>
                <input type="hidden" name="back" value="<?= htmlspecialchars($back_page) ?>">
                <?php endif; ?>
                <button type="submit" class="lang-btn ku-btn">
                    <span class="lang-badge">پێشبازی</span>
                    <span class="flag-emoji">🟡</span>
                    <div>
                        <div class="lang-name-primary">کوردی</div>
                        <div class="lang-name-secondary">Kurdî</div>
                    </div>
                </button>
            </form>

            <!-- إنجليزي -->
            <form method="POST" style="display:contents;">
                <input type="hidden" name="lang" value="en">
                <?php if ($is_logged): ?>
                <input type="hidden" name="back" value="<?= htmlspecialchars($back_page) ?>">
                <?php endif; ?>
                <button type="submit" class="lang-btn en-btn">
                    <span class="lang-badge">Default</span>
                    <span class="flag-emoji">🇬🇧</span>
                    <div>
                        <div class="lang-name-primary">English</div>
                        <div class="lang-name-secondary">إنگلیزی</div>
                    </div>
                </button>
            </form>
        </div>

        <?php if ($is_logged): ?>
        <div style="margin-top:20px; text-align:center;">
            <a href="<?= htmlspecialchars($back_page) ?>"
               style="color:rgba(255,255,255,0.4); font-size:0.8rem; text-decoration:none;
                      display:inline-flex; align-items:center; gap:6px;
                      transition:color 0.2s;"
               onmouseover="this.style.color='rgba(255,255,255,0.8)'"
               onmouseout="this.style.color='rgba(255,255,255,0.4)'">
                <i class="fas fa-times-circle"></i>
                Cancel / بەدەرچوو
            </a>
        </div>
        <?php endif; ?>

        <div class="footer-hint">
            كوردی • زمانا سەرەكی &nbsp;|&nbsp; English • Secondary Language
        </div>
    </div>
</div>

<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            for (let r of registrations) r.unregister();
        });
    }
</script>
</body>
</html>
