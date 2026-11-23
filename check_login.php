<?php
require_once __DIR__.'/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('access.php');
$next = key_next($_POST['next'] ?? 'index.php');
$_SESSION['key_next'] = $next;
try {
    if (!key_gate_valid($_POST['gate_csrf'] ?? null)) throw new DomainException('gate_csrf');
    key_login($mysqli, $_POST['access_code'] ?? '');
    unset($_SESSION['key_next'], $_SESSION['key_error']);
    header('Location: '.$next, true, 303); exit;
} catch (InvalidArgumentException $e) {
    $_SESSION['key_error'] = key_text('المفتاح غير صحيح. تحقق منه وحاول مجددًا.', 'The key is not valid. Check it and try again.');
} catch (DomainException $e) {
    $_SESSION['key_error'] = key_text('حدّث صفحة المفتاح ثم حاول مجددًا.', 'Refresh the key page and try again.');
} catch (Throwable $e) {
    if ((int)$e->getCode() === 429) $_SESSION['key_error'] = key_text('محاولات كثيرة. حاول بعد 15 دقيقة.', 'Too many attempts. Try again in 15 minutes.');
    else { error_log('Key entry: '.$e->getMessage()); $_SESSION['key_error'] = key_text('تعذّر التحقق من المفتاح الآن. حاول مجددًا.', 'Key verification is temporarily unavailable. Please try again.'); }
}
header('Location: access.php?next='.rawurlencode($next), true, 303);
