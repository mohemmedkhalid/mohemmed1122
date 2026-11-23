<?php
require_once __DIR__ . '/config.php';
if (!isLoggedIn()) {
    redirect('login.php');
}
$defaultLang = isset($_GET['language']) && is_string($_GET['language']) && in_array($_GET['language'], ['ar', 'ku'], true)
    ? $_GET['language'] : (isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar' ? 'ar' : 'ku');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
?>
<!DOCTYPE html>
<html lang="<?= $defaultLang ?>" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>هژمارتنا خزمایەتیێ و هەمی رێکێن گەهشتنێ</title>
    <link rel="stylesheet" href="assets/relationship.css?v=3">

</head>
<body>



<div class="rel-container">
    <div class="rel-glass-panel">
        <!-- أزرار تبديل اللغة: كوردی بادینی / عربي -->
        <div class="rel-top-bar">
            <a href="index.php" id="uiHome" class="rel-home-link">⌂</a>
            <span id="relationshipLoadState" class="rel-load-state" role="status"></span>
            <button type="button" class="lang-switch-btn active" data-lang="ku">کوردی (بادینی)</button>
            <button type="button" class="lang-switch-btn" data-lang="ar">عربي</button>
        </div>

        <div class="rel-header">
            <h1 id="uiPageTitle">🔗 هژمارتنا خزمایەتیێ و هەمی رێکێن گەهشتنێ</h1>
            <p id="uiPageSubtitle">دوو کەسان هەلبژێرە بۆ دیتنا هەمی رێکێن پەیوەندیێ دناڤبەرا وان دا (ب رێکا زەلامان، ژنان، یان هەڤژینیێ)</p>
        </div>

        <div class="rel-controls-grid">
            <!-- الشخص الأول (بحث منسدل بالاسم الثلاثي) -->
            <div class="rel-field-group">
                <label id="uiPerson1Label" for="searchPerson1">کەسێ ئێکێ:</label>
                <div class="rel-search-wrapper">
                    <input type="hidden" id="person1Id" value="">
                    <input type="text" id="searchPerson1" class="rel-input" disabled autocomplete="off" placeholder="🔍 ل ناڤێ سێ قۆلی بگەڕێ ()...">
                    <button type="button" id="clearPerson1" class="rel-clear-btn" title="پاقژکرن">✕</button>
                    <div id="dropdownPerson1" class="rel-dropdown-menu"></div>
                </div>
            </div>

            <!-- زر التبديل -->
            <div>
                <button type="button" id="swapPeopleBtn" class="rel-swap-btn" disabled title="گوهۆرینا هەردوو کەسان">⇄</button>
            </div>

            <!-- الشخص الثاني (بحث منسدل بالاسم الثلاثي) -->
            <div class="rel-field-group">
                <label id="uiPerson2Label" for="searchPerson2">کەسێ دووێ (بەرامبەر):</label>
                <div class="rel-search-wrapper">
                    <input type="hidden" id="person2Id" value="">
                    <input type="text" id="searchPerson2" class="rel-input" disabled autocomplete="off" placeholder="🔍 ل ناڤێ سێ قۆلی بگەڕێ ()...">
                    <button type="button" id="clearPerson2" class="rel-clear-btn" title="پاقژکرن">✕</button>
                    <div id="dropdownPerson2" class="rel-dropdown-menu"></div>
                </div>
            </div>
        </div>

        <div class="rel-scope-row" id="pathDepthControls">
            <label for="maxPathDepth" id="uiDepthLabel"></label>
            <input id="maxPathDepth" class="rel-depth-input" type="number" min="1" max="634" value="8" inputmode="numeric">
            <span id="uiDepthHint"></span>
        </div>
        <div class="rel-mode-row">
            <div class="rel-field-group">
                <label id="uiModeLabel" for="relationshipMode">شێوازێ لێگەڕیان و نیشاندانا رێکێن گەهشتنێ:</label>
                <select id="relationshipMode" class="rel-select">
                    <option id="optModeAll" value="all">🌟 هەمی خيارێن گەهشتنێ (زەلام + ژن + هەڤژین و خزمایەتی)</option>
                    <option id="optModeParents" value="parents" selected>👨‍👩‍👧‍👦 باب و دایک (رێکا باب و باپیران + رێکا دایک و داپیران پێکڤە)</option>
                    <option id="optModeMales" value="males">👨 رێکا زەلامان بتنێ (زنجیرا باب و باپیران بتنێ)</option>
                    <option id="optModeFemales" value="females"></option>
                    <option id="optModeSpouses" value="spouses">💍 رێکا هەڤژینیێ بتنێ (ژن و مێر)</option>
                </select>
            </div>

            <button type="button" id="calculateBtn" class="rel-calc-btn" disabled>🔍 نیشاندانا هەمی رێکێن گەهشتنێ</button>
        </div>
    </div>

    <p id="relationshipMessage" class="rel-message" role="alert" hidden></p>
    <!-- قسم النتائج -->
    <div id="relationshipResults" class="rel-glass-panel" style="display: none;">
        <div id="relationshipStatsBar"></div>
        <div id="ancestorControls" class="rel-ancestor-controls" hidden></div>
        <div class="rel-search-progress"><p id="pathSearchStatus" role="status"></p><button id="loadMorePaths" class="rel-tab-btn" type="button" hidden></button></div>
        <div id="pathAnimationControls" class="rel-animation-toolbar" hidden>
            <button type="button" id="toggleAnimation" class="rel-tab-btn"></button>
            <button type="button" id="replayAnimation" class="rel-tab-btn"></button>
            <label><span id="uiSpeedLabel"></span><select id="animationSpeed"><option value="0.5">0.5×</option><option value="1" selected>1×</option><option value="2">2×</option></select></label>
            <label><span id="uiViewLabel"></span><select id="routeView"><option value="tree" id="optViewTree"></option><option value="chain" id="optViewChain"></option></select></label>
        </div>
        <div id="pathsTabsContainer" class="rel-tabs-bar"></div>
        <div id="pathsViewport"></div>
    </div>
</div>

<script>
    window.DEFAULT_LANG = <?php echo json_encode($defaultLang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.RELATIONSHIP_USER_ID = <?php echo json_encode((string) $_SESSION['user_id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="assets/relationship-core.js?v=3"></script>
<script src="assets/relationship-state.js?v=4"></script>
<script src="assets/relationship.js?v=4"></script>
</body>
</html>
