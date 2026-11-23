<?php
require_once __DIR__.'/config.php';
$next = key_next($_GET['next'] ?? ($_SESSION['key_next'] ?? 'index.php'));
if (isLoggedIn()) redirect($next);
$gate = key_gate_csrf();
$error = $_SESSION['key_error'] ?? ($_SESSION['error'] ?? '');
unset($_SESSION['key_error'], $_SESSION['error']);
if (!empty($GLOBALS['key_auth_unavailable'])) $error = key_text('پشتڕاستکرنا کلیلێ نوکە بەردەست نینە. هیڤیە جارەکا دی هەول بدە.', 'Key verification is temporarily unavailable. Please try again.');
$en = get_lang() === 'en';
?>
<!doctype html><html lang="<?= $en?'en':'ku' ?>" dir="<?= $en?'ltr':'rtl' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= key_text('چوونا ژوور ب کلیلێ','Enter with your key') ?></title><link rel="stylesheet" href="assets/key-access.css?v=1"></head><body>
<header class="key-shell key-header"><a href="index.php" class="key-brand"><img src="icon-192.png" alt=""> <?= $en?'Jotiari Family':'بنەمالا جوتیاری' ?></a><a class="key-button" href="set_lang.php?lang=<?= $en?'ku':'en' ?>&amp;back=access.php"><?= $en?'کوردی':'English' ?></a></header>
<main class="key-shell key-gate"><section class="key-card"><div class="key-emblem"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2a7 7 0 1 0 6.7 9H16v3h3v3h3V8h-8.3A7 7 0 0 0 7 2Zm0 4a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z"/></svg></div><h1><?= key_text('چوونا ژوور ب کلیلێ','Enter with your key') ?></h1><p><?= key_text('بتنێ کلیلا خۆ یا تایبەت بنڤیسە. چ ناڤێن بکارهێنەری یان چێکرنا هەژمارێ پێدڤی ناکەت.', 'Enter only your personal key. No username or registration is needed.') ?></p>
<?php if ($error): ?><div class="key-notice error" role="alert"><?= key_h($error) ?></div><?php endif; ?>
<form method="post" action="check_login.php"><input type="hidden" name="gate_csrf" value="<?= key_h($gate) ?>"><input type="hidden" name="next" value="<?= key_h($next) ?>"><label for="accessKey" class="key-label"><?= key_text('کۆدێ چوونا ژوور','Access code') ?></label><div class="key-field"><input class="key-input" id="accessKey" name="access_code" type="password" required maxlength="255" autocomplete="current-password" inputmode="numeric" autocapitalize="none" spellcheck="false" dir="ltr" placeholder="<?= key_text('کۆدێ خۆ بنڤیسە','Enter your code') ?>"><button class="key-button key-toggle" type="button" id="showKey" aria-pressed="false"><?= key_text('نیشاندان','Show') ?></button></div><button class="key-button primary" type="submit"><?= key_text('چوونا ژوور','Enter') ?></button></form><p class="key-small"><?= key_text('ئەڤ ئامیرە دێ چوونا تە یا ژوور بۆ ماوێ ٣٠ ڕۆژان پارێزیت، هەتا کو تو دەردکەڤی یان کلیلا تە دهێتە گوهرین.', 'This device remembers you for 30 days, until you sign out or your key changes.') ?></p></section></main>
<script>document.getElementById('showKey').addEventListener('click',function(){const input=document.getElementById('accessKey'),show=input.type==='password';input.type=show?'text':'password';this.setAttribute('aria-pressed',String(show));this.textContent=show?<?= json_encode(key_text('ڤەشارتن','Hide')) ?>:<?= json_encode(key_text('نیشاندان','Show')) ?>;});</script></body></html>
