<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/feedback.php';
header('Cache-Control: no-store');
$json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function feedback_reply($ok, $status, $text, $extra = []) {
    global $json;
    if ($json) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok'=>$ok, 'message'=>$text], $extra), JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['feedback_notice'] = ['ok'=>$ok, 'message'=>$text];
        if (!$ok && is_string($_POST['message'] ?? null) && strlen($_POST['message']) <= 8000) {
            $_SESSION['feedback_draft'] = $_POST['message'];
        }
        header('Location: index.php#feedback', true, 303);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit(feedback_text('استخدم زر إرسال في الرئيسية.', 'Use the send button on the homepage.'));
}
$keyConfirmed = false;
if (is_string($_POST['access_key'] ?? null) && trim($_POST['access_key']) !== '') {
    try {
        if (!key_gate_valid($_POST['gate_csrf'] ?? null)) throw new DomainException('gate_csrf');
        key_login($mysqli, $_POST['access_key']);
        $keyConfirmed = true;
    } catch (InvalidArgumentException $e) {
        feedback_reply(false, 401, feedback_text('المفتاح غير صحيح. تحقق منه وحاول مجددًا.', 'The key is not valid. Check it and try again.'), ['needs_key'=>true]);
    } catch (DomainException $e) {
        feedback_reply(false, 403, feedback_text('حدّث الصفحة لتأكيد المفتاح. احتفظ بنص ملاحظتك.', 'Refresh the page to confirm your key. Keep your message.'), ['needs_key'=>true]);
    } catch (Throwable $e) {
        error_log('Feedback key confirmation: '.$e->getMessage());
        feedback_reply(false, (int)$e->getCode()===429?429:503, (int)$e->getCode()===429 ? feedback_text('محاولات كثيرة. حاول بعد 15 دقيقة.', 'Too many attempts. Try again in 15 minutes.') : feedback_text('تعذّر التحقق من المفتاح الآن.', 'Key verification is temporarily unavailable.'), ['needs_key'=>true]);
    }
}
if (!isLoggedIn() || (int)$_SESSION['user_id'] < 1) {
    if (!$json) {
        if (is_string($_POST['message'] ?? null) && strlen($_POST['message'])<=8000) $_SESSION['feedback_draft']=$_POST['message'];
        header('Location: access.php?next=index.php%23feedback', true, 303); exit;
    }
    feedback_reply(false, 401, feedback_text('أدخل مفتاحك أدناه لتأكيد إرسال الملاحظة.', 'Enter your key below to confirm sending this message.'), ['needs_key'=>true]);
}
if (!$keyConfirmed && (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['feedback_csrf']) || !hash_equals($_SESSION['feedback_csrf'], $_POST['csrf']))) {
    feedback_reply(false, 403, feedback_text('حدّث الصفحة ثم حاول الإرسال مجددًا.', 'Refresh the page, then try sending again.'));
}
$key = $_POST['submission_key'] ?? '';
if (!is_string($key) || preg_match('/\A[a-f0-9]{64}\z/D', $key) !== 1) {
    feedback_reply(false, 422, feedback_text('حدّث الصفحة ثم حاول الإرسال مجددًا.', 'Refresh the page, then try sending again.'));
}
try {
    $message = feedback_message($_POST['message'] ?? '');
} catch (InvalidArgumentException $e) {
    $text = $e->getMessage() === 'empty'
        ? feedback_text('اكتب ملاحظتك أولًا.', 'Write your feedback first.')
        : feedback_text('الملاحظة يجب ألا تتجاوز 2000 حرف.', 'Feedback must not exceed 2,000 characters.');
    feedback_reply(false, 422, $text);
}
try {
    // Prepare the next key before saving, so a successful insert has a complete response.
    $nextKey = bin2hex(random_bytes(32));
    $id = feedback_save($mysqli, (int)$_SESSION['user_id'], $message, $key);
    feedback_reply(true, 200, feedback_text('تم إرسال ملاحظتك. شكرًا لك.', 'Your feedback was sent. Thank you.'), ['id'=>$id, 'next_key'=>$nextKey, 'csrf'=>$_SESSION['feedback_csrf']]);
} catch (DomainException $e) {
    feedback_reply(false, 409, feedback_text('أُرسلت الملاحظة السابقة. اضغط إرسال لحفظ الملاحظة الجديدة.', 'The previous feedback was sent. Press Send to save this new message.'), ['next_key'=>bin2hex(random_bytes(32))]);
} catch (Throwable $e) {
    error_log('Feedback save: '.$e->getMessage());
    feedback_reply(false, 503, feedback_text('تعذّر حفظ الملاحظة الآن. حاول مجددًا.', 'Your feedback could not be saved right now. Please try again.'));
}
