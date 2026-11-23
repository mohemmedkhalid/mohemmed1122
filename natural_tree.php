<?php
/**
 * natural_tree.php — قسم «الشجرة الطبيعية للعائلة»
 * جذع وأغصان وأوراق تحمل أسماء الأفراد، بعرض لا يتجاوز 2000 بكسل.
 */
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) redirect('access.php');

$en = get_lang() === 'en';
$t = function ($ar, $english) use ($en) { return $en ? $english : $ar; };
$h = function ($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); };

$strings = [
    'title' => $t('شجرة العائلة الطبيعية', 'Natural Family Tree'),
    'loading' => $t('جارٍ رسم الشجرة…', 'Drawing the tree…'),
    'loadError' => $t('تعذّر تحميل بيانات العائلة. تحقق من الاتصال ثم أعد المحاولة.', 'Could not load the family data. Check your connection and try again.'),
    'retry' => $t('إعادة المحاولة', 'Try again'),
    'empty' => $t('لا يوجد أفراد في قاعدة البيانات بعد.', 'There are no people in the database yet.'),
    'people' => $t('فرد', 'people'),
    'generations' => $t('أجيال', 'generations'),
    'generation' => $t('الجيل', 'Generation'),
    'widthOf' => $t('العرض %1 من %2 بكسل', 'width %1 of %2px'),
    'founder' => $t('المؤسس', 'Founder'),
    'alive' => $t('على قيد الحياة', 'Living'),
    'deceased' => $t('متوفى', 'Deceased'),
    'male' => $t('ذكر', 'Male'),
    'female' => $t('أنثى', 'Female'),
    'father' => $t('الأب', 'Father'),
    'mother' => $t('الأم', 'Mother'),
    'spouses' => $t('الزوج / الزوجة', 'Spouse'),
    'children' => $t('الأبناء', 'Children'),
    'descendants' => $t('الذرية', 'Descendants'),
    'birth' => $t('تاريخ الميلاد', 'Born'),
    'lineage' => $t('النسب', 'Lineage'),
    'son' => $t('بن', 'son of'),
    'daughter' => $t('بنت', 'daughter of'),
    'none' => $t('غير مسجل', 'Not recorded'),
    'focus' => $t('تمركز على الشخص', 'Center on person'),
    'classic' => $t('الشجرة التفصيلية', 'Detailed tree'),
    'relation' => $t('صلة القرابة', 'Relationship'),
    'edit' => $t('تعديل البيانات', 'Edit details'),
    'close' => $t('إغلاق', 'Close'),
    'searchLabel' => $t('ابحث عن فرد في الشجرة', 'Find a person in the tree'),
    'searchPlaceholder' => $t('ابحث بالاسم…', 'Search by name…'),
    'noResults' => $t('لا توجد نتائج مطابقة', 'No matching people'),
    'otherTree' => $t('ضمن شجرة أخرى', 'In another tree'),
    'notInTree' => $t('غير مرسوم في الشجرة (مرتبط بالزواج فقط)', 'Not drawn in the tree (linked by marriage only)'),
    'updated' => $t('تم تحديث الشجرة', 'Tree updated'),
    'added' => $t('أضيف', 'added'),
    'removed' => $t('حُذف', 'removed'),
    'changed' => $t('تعديلات في البيانات', 'details changed'),
    'more' => $t('و %d آخرون', 'and %d more'),
    'zoomIn' => $t('تكبير', 'Zoom in'),
    'zoomOut' => $t('تصغير', 'Zoom out'),
    'fit' => $t('عرض الشجرة كاملة', 'Show whole tree'),
    'toFounder' => $t('الذهاب إلى المؤسس', 'Go to founder'),
    'fullscreen' => $t('ملء الشاشة', 'Full screen'),
    'hint' => $t('اسحب للتنقل، وقرّب بإصبعين أو بعجلة الفأرة. اضغط على أي ورقة لعرض التفاصيل.', 'Drag to move, pinch or use the wheel to zoom. Tap any leaf for details.'),
];
?><!DOCTYPE html>
<html lang="<?= $en ? 'en' : 'ar' ?>" dir="<?= $en ? 'ltr' : 'rtl' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c1714">
    <title><?= $h($strings['title']) ?></title>
    <link rel="apple-touch-icon" href="icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/natural-tree.css?v=1">
</head>
<body class="nt-body">
<header class="nt-bar">
    <a class="nt-btn nt-icon" href="index.php" aria-label="<?= $h($t('الرئيسية', 'Home')) ?>" title="<?= $h($t('الرئيسية', 'Home')) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21h-6v-6.5H9V21H3Z"/></svg>
    </a>
    <div class="nt-heading">
        <h1><?= $h($strings['title']) ?></h1>
        <p id="ntSummary" class="nt-summary" aria-live="polite"></p>
    </div>
    <div class="nt-search" role="search">
        <label class="nt-visually-hidden" for="ntSearch"><?= $h($strings['searchLabel']) ?></label>
        <svg class="nt-search-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 3a7 7 0 1 0 4.4 12.4l5.1 5.1 1.4-1.4-5.1-5.1A7 7 0 0 0 10 3Zm0 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Z"/></svg>
        <input id="ntSearch" type="search" autocomplete="off" spellcheck="false" placeholder="<?= $h($strings['searchPlaceholder']) ?>"
               role="combobox" aria-expanded="false" aria-controls="ntResults" aria-autocomplete="list">
        <ul id="ntResults" class="nt-results" role="listbox" hidden></ul>
    </div>
    <div class="nt-bar-actions">
        <button class="nt-btn nt-icon" id="ntSettingsBtn" type="button" aria-expanded="false" aria-controls="ntSettings" title="<?= $h($t('خيارات العرض', 'View options')) ?>" aria-label="<?= $h($t('خيارات العرض', 'View options')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>
        </button>
        <a class="nt-btn nt-lang" href="set_lang.php?lang=<?= $en ? 'ku' : 'en' ?>&amp;back=natural_tree.php"><?= $en ? 'کوردی' : 'English' ?></a>
    </div>
</header>

<section id="ntSettings" class="nt-pop" hidden aria-label="<?= $h($t('خيارات العرض', 'View options')) ?>">
    <label class="nt-field" id="ntRootField" hidden>
        <span><?= $h($t('بداية الشجرة', 'Tree starts from')) ?></span>
        <select id="ntRoot"></select>
    </label>
    <label class="nt-field">
        <span><?= $h($t('حجم البطاقات', 'Card size')) ?></span>
        <select id="ntDensity">
            <option value="tree"><?= $h($t('شكل شجرة متناسق (موصى به)', 'Balanced tree shape (recommended)')) ?></option>
            <option value="balanced"><?= $h($t('بطاقات أكبر وشجرة أطول', 'Larger cards, taller tree')) ?></option>
            <option value="large"><?= $h($t('أكبر بطاقات ممكنة', 'Largest possible cards')) ?></option>
        </select>
    </label>
    <label class="nt-check"><input type="checkbox" id="ntYears" checked> <span><?= $h($t('إظهار سنة الميلاد', 'Show birth year')) ?></span></label>
    <label class="nt-check"><input type="checkbox" id="ntFoliage" checked> <span><?= $h($t('إظهار ظل الأوراق خلف الأغصان', 'Show foliage glow')) ?></span></label>
    <div class="nt-legend" aria-label="<?= $h($t('دليل الألوان', 'Colour key')) ?>">
        <span><i class="sw sw-alive"></i><?= $h($strings['alive']) ?></span>
        <span><i class="sw sw-dead"></i><?= $h($strings['deceased']) ?></span>
        <span><i class="sw sw-male"></i><?= $h($strings['male']) ?></span>
        <span><i class="sw sw-female"></i><?= $h($strings['female']) ?></span>
        <span><i class="sw sw-root"></i><?= $h($strings['founder']) ?></span>
    </div>
    <p class="nt-note"><?= $h($t('يُعاد توزيع الشجرة تلقائياً عند إضافة أو حذف أي فرد، ويبقى عرضها ضمن 2000 بكسل.', 'The tree re-arranges itself automatically when people are added or removed, and always stays within 2000px wide.')) ?></p>
</section>

<main id="ntViewport" class="nt-viewport" tabindex="0" aria-label="<?= $h($strings['title']) ?>" aria-describedby="ntHint">
    <div id="ntWorld" class="nt-world">
        <canvas id="ntCanopy" class="nt-canopy" aria-hidden="true"></canvas>
        <svg id="ntWood" class="nt-wood" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"></svg>
        <div id="ntLeaves" class="nt-leaves"></div>
    </div>
    <p id="ntHint" class="nt-hint"><?= $h($strings['hint']) ?></p>
</main>

<div class="nt-zoom" role="toolbar" aria-label="<?= $h($t('التكبير والتنقل', 'Zoom and navigation')) ?>">
    <button type="button" class="nt-btn nt-icon" data-act="in" title="<?= $h($strings['zoomIn']) ?>" aria-label="<?= $h($strings['zoomIn']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
    <button type="button" class="nt-btn nt-icon" data-act="out" title="<?= $h($strings['zoomOut']) ?>" aria-label="<?= $h($strings['zoomOut']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg></button>
    <button type="button" class="nt-btn nt-icon" data-act="fit" title="<?= $h($strings['fit']) ?>" aria-label="<?= $h($strings['fit']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/></svg></button>
    <button type="button" class="nt-btn nt-icon" data-act="root" title="<?= $h($strings['toFounder']) ?>" aria-label="<?= $h($strings['toFounder']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21v-7M12 14c-4 0-6-2.5-6-5.5S8.5 3 12 3s6 2.5 6 5.5-2 5.5-6 5.5ZM8 21h8"/></svg></button>
    <button type="button" class="nt-btn nt-icon" data-act="full" title="<?= $h($strings['fullscreen']) ?>" aria-label="<?= $h($strings['fullscreen']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M21 16v5h-5M3 16v5h5"/></svg></button>
</div>

<aside id="ntPanel" class="nt-panel" hidden aria-labelledby="ntPanelName">
    <button type="button" class="nt-btn nt-icon nt-panel-close" id="ntPanelClose" aria-label="<?= $h($strings['close']) ?>" title="<?= $h($strings['close']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    <div id="ntPanelBody"></div>
</aside>

<div id="ntToast" class="nt-toast" role="status" aria-live="polite" hidden></div>
<div id="ntLoading" class="nt-loading" role="status"><span class="nt-spinner" aria-hidden="true"></span><span id="ntLoadingText"><?= $h($strings['loading']) ?></span></div>
<noscript><p class="nt-noscript"><?= $h($t('يتطلب عرض الشجرة تفعيل JavaScript.', 'JavaScript is required to draw the tree.')) ?></p></noscript>

<script>
window.NT_CONFIG = <?= json_encode([
    'lang' => $en ? 'en' : 'ar',
    'admin' => isAdmin(),
    'maxWidth' => 2000,
    'dataUrl' => 'natural_tree_data.php',
    'text' => $strings,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="assets/natural-tree-layout.js?v=1"></script>
<script src="assets/natural-tree.js?v=1"></script>
</body>
</html>
