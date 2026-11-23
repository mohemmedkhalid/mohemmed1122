<?php
require_once __DIR__.'/config.php';
if (!isLoggedIn()) redirect('login.php');
header('Cache-Control: no-store');
if (!isAdmin()) { http_response_code(403); exit('هذه الصفحة للمشرف فقط.'); }
require_once __DIR__.'/includes/feedback.php';
$english = get_lang() === 'en';
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (strlen($q) > 800 || preg_match('//u', $q) !== 1) $q = '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 1;
$pageSize = 30;
$rows = []; $total = 0; $pages = 1; $error = '';
try {
    feedback_ensure_table($mysqli);
    $where = $q === '' ? '' : ' WHERE LOCATE(?, message) > 0 OR CAST(user_id AS CHAR) = ?';
    $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM site_feedback'.$where);
    if (!$stmt) throw new RuntimeException('Could not prepare feedback count.');
    if ($q !== '') $stmt->bind_param('ss', $q, $q);
    if (!$stmt->execute()) throw new RuntimeException('Could not count feedback.');
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    $pages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $pages); $offset = ($page - 1) * $pageSize;
    $stmt = $mysqli->prepare('SELECT id, user_id, message, created_at FROM site_feedback'.$where.' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
    if (!$stmt) throw new RuntimeException('Could not prepare feedback list.');
    if ($q === '') $stmt->bind_param('ii', $pageSize, $offset);
    else $stmt->bind_param('ssii', $q, $q, $pageSize, $offset);
    if (!$stmt->execute()) throw new RuntimeException('Could not read feedback.');
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $e) {
    error_log('Feedback list: '.$e->getMessage());
    http_response_code(503);
    $error = feedback_text('تعذّر تحميل الملاحظات. حاول مجددًا.', 'Feedback could not be loaded. Please try again.');
}
function feedback_page_url($page, $q) {
    return 'admin_feedback.php?'.http_build_query(['page'=>$page, 'q'=>$q]);
}
?>
<!doctype html>
<html lang="<?= $english ? 'en' : 'ar' ?>" dir="<?= $english ? 'ltr' : 'rtl' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= feedback_text('ملاحظات المستخدمين', 'User feedback') ?></title>
    <style>
        @font-face{font-family:Speda;src:url('Speda.ttf') format('truetype');font-display:swap}
        *{box-sizing:border-box}body{margin:0;min-height:100svh;background:linear-gradient(135deg,#3a5251,#000,#57565e);color:#fff;font-family:Speda,Tahoma,Arial,sans-serif;line-height:1.7}
        a{color:inherit;text-decoration:none}button,input{font:inherit}button,a{touch-action:manipulation}button:focus-visible,a:focus-visible,input:focus-visible{outline:2px solid #8bd8bd;outline-offset:4px}
        .notes-shell{width:min(100% - 32px,960px);margin:auto}.notes-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:24px 0;border-bottom:1px solid #ffffff24;flex-wrap:wrap}
        .notes-brand{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:bold}.notes-brand img{width:36px;height:36px}.notes-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:1px solid #ffffff38;border-radius:14px;padding:8px 16px;background:#ffffff0d;color:#fff;cursor:pointer}
        .notes-intro{margin:32px 0 24px}.notes-intro h1{font-size:26px;margin:0 0 8px}.notes-intro p{color:#c0d5d1;font-size:14px;margin:0}
        .notes-search{display:flex;align-items:center;gap:10px;margin-bottom:16px}.notes-search label{flex:1;min-width:0}.notes-search span{display:block;font-size:13px;margin-bottom:7px;color:#d2dddb}.notes-search input{display:block;width:100%;min-height:46px;border:1px solid #ffffff40;border-radius:16px;background:#ffffff0b;color:#fff;padding:10px 14px;font-size:16px}.notes-search button{align-self:end;background:#8bd8bd;color:#132b23;border-color:#8bd8bd}
        .notes-count{font-size:13px;color:#c0d5d1;margin:16px 0}.notes-list{display:grid;gap:16px}.note{min-width:0;padding:22px;border-radius:22px;background:#1e1e1e99;border:1px solid #ffffff29;backdrop-filter:blur(16px)}.note-meta{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:12px;color:#b8cdc8}.note-sender{font-size:14px;color:#8bd8bd}.note-body{margin:16px 0 0;white-space:pre-wrap;overflow-wrap:anywhere;unicode-bidi:plaintext;font-size:16px;line-height:1.9}.note-id{color:#b8cdc8;font:12px Arial,sans-serif}.note time{font-size:12px}
        .notes-empty,.notes-error{padding:28px 18px;border:1px solid #ffffff26;border-radius:18px;background:#ffffff09;text-align:center}.notes-error{color:#ffd3ce}.notes-pager{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;padding:30px 0}.notes-pager span{font-size:13px;color:#c0d5d1}.notes-list{margin-bottom:24px}
        @media(max-width:575px){.notes-header{padding:16px 0}.notes-brand{font-size:15px}.notes-button{padding:8px 12px;font-size:13px}.notes-intro{margin:24px 0}.notes-intro h1{font-size:23px}.note{padding:17px}.note-body{font-size:15px}}
    </style>
</head>
<body>
<header class="notes-shell notes-header"><a href="index.php" class="notes-brand"><img src="icon-192.png" alt=""> <?= $english ? 'Jotiari Family' : 'بنەمالا جوتیاری' ?></a><a class="notes-button" href="index.php"><?= feedback_text('الرئيسية', 'Home') ?></a></header>
<main class="notes-shell">
    <div class="notes-intro"><h1><?= feedback_text('ملاحظات المستخدمين', 'User feedback') ?></h1><p><?= feedback_text('الرسائل المرسلة من الرئيسية، الأحدث أولًا.', 'Messages sent from the homepage, newest first.') ?></p></div>
    <form class="notes-search" method="get" action="admin_feedback.php"><label for="feedbackSearch"><span><?= feedback_text('بحث في الملاحظات أو برقم المستخدم', 'Search feedback or user ID') ?></span><input id="feedbackSearch" name="q" type="search" value="<?= feedback_h($q) ?>" maxlength="200" placeholder="<?= feedback_text('اكتب كلمة أو رقم مستخدم', 'Enter a word or user ID') ?>"></label><button class="notes-button" type="submit"><?= feedback_text('بحث', 'Search') ?></button><?php if ($q !== ''): ?><a class="notes-button" style="align-self:end" href="admin_feedback.php"><?= feedback_text('الكل', 'All') ?></a><?php endif; ?></form>
    <?php if ($error): ?><p class="notes-error" role="alert"><?= feedback_h($error) ?></p><?php else: ?>
    <p class="notes-count"><?= feedback_text('عدد الملاحظات', 'Feedback count') ?>: <?= $total ?></p>
    <?php if (!$rows): ?><p class="notes-empty"><?= $q === '' ? feedback_text('لا توجد ملاحظات حتى الآن.', 'No feedback yet.') : feedback_text('لا توجد ملاحظات تطابق البحث.', 'No matching feedback.') ?></p><?php endif; ?>
    <section class="notes-list" aria-label="<?= feedback_text('الملاحظات', 'Feedback') ?>">
    <?php foreach ($rows as $row): ?>
        <article class="note" data-feedback-id="<?= (int)$row['id'] ?>">
            <div class="note-meta"><strong class="note-sender"><?= feedback_text('المستخدم', 'User') ?> #<?= (int)$row['user_id'] ?></strong><span class="note-id">#<?= (int)$row['id'] ?></span><time datetime="<?= feedback_h(str_replace(' ', 'T', $row['created_at']).'Z') ?>"><?= feedback_h($row['created_at']) ?> UTC</time></div>
            <p class="note-body" dir="auto"><?= feedback_h($row['message']) ?></p>
        </article>
    <?php endforeach; ?>
    </section>
    <?php if ($pages > 1): ?><nav class="notes-pager" aria-label="<?= feedback_text('صفحات الملاحظات', 'Feedback pages') ?>"><?php if ($page > 1): ?><a class="notes-button" rel="prev" href="<?= feedback_h(feedback_page_url($page-1, $q)) ?>"><?= feedback_text('السابق', 'Previous') ?></a><?php endif; ?><span><?= $page ?> / <?= $pages ?></span><?php if ($page < $pages): ?><a class="notes-button" rel="next" href="<?= feedback_h(feedback_page_url($page+1, $q)) ?>"><?= feedback_text('التالي', 'Next') ?></a><?php endif; ?></nav><?php endif; ?>
    <?php endif; ?>
</main>
<script>
document.querySelectorAll('time[datetime]').forEach(time => {
    const date = new Date(time.dateTime);
    if (!Number.isNaN(date.getTime())) {
        time.title = time.textContent;
        time.textContent = date.toLocaleString(document.documentElement.lang === 'en' ? 'en-GB' : 'ar-IQ', {year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
    }
});
</script>
</body>
</html>
