<?php
require_once __DIR__.'/config.php';
if (!isLoggedIn()) redirect('access.php?next=users_management.php');
if (!isAdmin()) { http_response_code(403); exit('هذه الصفحة للمشرف فقط.'); }
$notice = $_SESSION['keys_notice'] ?? ''; unset($_SESSION['keys_notice']);
$error = ''; $users = []; $currentId = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!key_admin_csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(403); $error = key_text('حدّث الصفحة ثم حاول مجددًا.', 'Refresh the page and try again.');
    } else {
        $transaction = false;
        try {
            key_tables($mysqli);
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            $id = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
            $code = is_string($_POST['access_code'] ?? null) ? trim($_POST['access_code']) : '';
            if (!in_array($action, ['add','edit','delete'], true)) throw new DomainException('invalid');
            if ($action !== 'delete') {
                if (!in_array($role, ['admin','user'], true)) throw new DomainException('invalid');
                if ($action === 'add' && $code === '') $code = key_new_personal_code($mysqli);
                if ($code === '' || strlen($code)>key_code_limit($mysqli) || preg_match('/[\x00-\x1f\x7f]/', $code)) throw new DomainException('invalid');
            }
            $mysqli->begin_transaction(); $transaction = true;
            // Serializes key creation/editing and protects the last administrator.
            $locked = key_rows($mysqli, 'SELECT id, access_code, role FROM users ORDER BY id FOR UPDATE');
            $target = null; $adminCount = 0;
            foreach ($locked as $user) { if ((int)$user['id'] === (int)$id) $target = $user; if ($user['role']==='admin') $adminCount++; }
            if ($action !== 'add' && !$target) throw new DomainException('missing');
            if ($action === 'delete') {
                if ((int)$id === $currentId || $target['role'] === 'admin') throw new DomainException('protected');
                key_query($mysqli, 'DELETE FROM site_access_tokens WHERE user_id=?', 'i', [(int)$id])->close();
                key_query($mysqli, 'DELETE FROM users WHERE id=?', 'i', [(int)$id])->close();
            } else {
                $duplicate = key_rows($mysqli, 'SELECT id FROM users WHERE access_code=? AND id<>?', 'si', [$code, $action==='edit'?(int)$id:0]);
                if ($duplicate) throw new DomainException('duplicate');
                if ($action === 'add') {
                    key_insert_user($mysqli, $code, $role);
                } else {
                    if ($target['role']==='admin' && $role!=='admin' && $adminCount<=1) throw new DomainException('last_admin');
                    key_query($mysqli, 'UPDATE users SET access_code=?, role=? WHERE id=?', 'ssi', [$code,$role,(int)$id])->close();
                    if (!hash_equals($target['access_code'],$code)) key_query($mysqli, 'DELETE FROM site_access_tokens WHERE user_id=?', 'i', [(int)$id])->close();
                }
            }
            $mysqli->commit(); $transaction = false;
            if ($action==='edit' && (int)$id===$currentId) {
                if (!hash_equals($target['access_code'],$code)) key_issue_device($mysqli, ['id'=>$id,'access_code'=>$code,'role'=>$role]);
                else $_SESSION['role']=$role;
            }
            $_SESSION['keys_notice'] = key_text('تم حفظ التغيير.', 'Changes saved.');
            header('Location: '.(isAdmin()?'users_management.php':'index.php'), true, 303); exit;
        } catch (DomainException $e) {
            if ($transaction) $mysqli->rollback();
            $messages = [
                'duplicate'=>key_text('هذا المفتاح مستخدم لشخص آخر. اختر مفتاحًا مختلفًا.', 'This key belongs to another user. Choose a different key.'),
                'last_admin'=>key_text('يجب إبقاء مفتاح مشرف واحد على الأقل.', 'At least one administrator key must remain.'),
                'protected'=>key_text('لا يمكن حذف مفتاحك أو مفتاح مشرف.', 'Your own key and administrator keys cannot be deleted.'),
                'missing'=>key_text('المستخدم غير موجود.', 'The user no longer exists.')
            ];
            $error = $messages[$e->getMessage()] ?? key_text('تحقق من المفتاح والصلاحية ثم حاول مجددًا.', 'Check the key and role, then try again.');
        } catch (Throwable $e) {
            if ($transaction) $mysqli->rollback();
            error_log('Key management: '.$e->getMessage());
            $error = key_text('تعذّر حفظ التغيير. حاول مجددًا.', 'Changes could not be saved. Please try again.');
        }
    }
}
try { $users = key_rows($mysqli, 'SELECT id, access_code, role FROM users ORDER BY id DESC'); }
catch (Throwable $e) { error_log('Key list: '.$e->getMessage()); $error = key_text('تعذّر عرض المفاتيح.', 'Keys could not be loaded.'); }
$en = get_lang()==='en';
?>
<!doctype html><html lang="<?= $en?'en':'ar' ?>" dir="<?= $en?'ltr':'rtl' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= key_text('مفاتيح الدخول','Access keys') ?></title><link rel="stylesheet" href="assets/key-access.css?v=1"></head><body>
<header class="key-shell key-header"><a class="key-brand" href="index.php"><img src="icon-192.png" alt=""> <?= $en?'Jotiari Family':'بنەمالا جوتیاری' ?></a><a class="key-button" href="index.php"><?= key_text('الرئيسية','Home') ?></a></header>
<main class="key-shell"><div class="key-intro"><h1><?= key_text('مفاتيح الدخول','Access keys') ?></h1><p><?= key_text('مفتاح مستقل لكل مستخدم. مفتاح المشرف يفتح أدوات الإدارة.', 'One personal key per user. Administrator keys open management tools.') ?></p></div>
<?php if ($notice): ?><p class="key-notice" role="status"><?= key_h($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="key-notice error" role="alert"><?= key_h($error) ?></p><?php endif; ?>
<form class="key-create" method="post" action="users_management.php"><h2><?= key_text('إضافة مفتاح لشخص','Add a personal key') ?></h2><input type="hidden" name="csrf" value="<?= key_h($_SESSION['key_csrf']) ?>"><input type="hidden" name="action" value="add"><label><span class="key-label"><?= key_text('المفتاح، اتركه فارغًا لإنشائه تلقائيًا','Key, leave blank to generate') ?></span><input class="key-input key-code" name="access_code" maxlength="255" autocomplete="off" dir="ltr" spellcheck="false"></label><label><span class="key-label"><?= key_text('الصلاحية','Role') ?></span><select class="key-input" name="role"><option value="user"><?= key_text('مستخدم','User') ?></option><option value="admin"><?= key_text('مشرف','Administrator') ?></option></select></label><button class="key-button primary" type="submit"><?= key_text('إنشاء المفتاح','Create key') ?></button></form>
<p class="key-small"><?= key_text('عند تغيير مفتاح، يتوقف المفتاح السابق ودخوله المحفوظ على الأجهزة الأخرى.', 'Changing a key invalidates the old key and its saved access on other devices.') ?></p><p class="key-copy-status" id="copyStatus" role="status"></p>
<section class="key-users">
<?php foreach ($users as $user): $id=(int)$user['id']; ?>
<article class="key-user" data-user-id="<?= $id ?>"><div class="key-user-header"><strong><?= key_text('المستخدم','User') ?> #<?= $id ?><?= $id===$currentId?key_text('، أنت',' (you)'):'' ?></strong><span class="key-small"><?= $user['role']==='admin'?key_text('مشرف','Administrator'):key_text('مستخدم','User') ?></span></div>
<form class="key-edit" method="post" action="users_management.php"><input type="hidden" name="csrf" value="<?= key_h($_SESSION['key_csrf']) ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="user_id" value="<?= $id ?>"><label><span class="key-label"><?= key_text('المفتاح','Key') ?></span><div class="key-field"><input id="key<?= $id ?>" class="key-input key-code" name="access_code" type="password" dir="ltr" required maxlength="255" autocomplete="off" value="<?= key_h($user['access_code']) ?>"><button class="key-button" type="button" data-reveal="key<?= $id ?>" aria-pressed="false"><?= key_text('إظهار','Show') ?></button></div></label><label><span class="key-label"><?= key_text('الصلاحية','Role') ?></span><select class="key-input" name="role"><option value="user" <?= $user['role']==='user'?'selected':'' ?>><?= key_text('مستخدم','User') ?></option><option value="admin" <?= $user['role']==='admin'?'selected':'' ?>><?= key_text('مشرف','Administrator') ?></option></select></label><button class="key-button primary" type="submit"><?= key_text('حفظ','Save') ?></button></form>
<div class="key-actions"><button class="key-button" type="button" data-copy="key<?= $id ?>"><?= key_text('نسخ المفتاح','Copy key') ?></button><?php if ($id!==$currentId && $user['role']!=='admin'): ?><form class="key-delete" method="post" action="users_management.php"><input type="hidden" name="csrf" value="<?= key_h($_SESSION['key_csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $id ?>"><button class="key-button danger" type="submit"><?= key_text('حذف المفتاح','Delete key') ?></button></form><?php endif; ?></div></article>
<?php endforeach; ?></section></main>
<script>
document.querySelectorAll('[data-reveal]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById(button.dataset.reveal),show=input.type==='password';input.type=show?'text':'password';button.setAttribute('aria-pressed',String(show));button.textContent=show?<?= json_encode(key_text('إخفاء','Hide')) ?>:<?= json_encode(key_text('إظهار','Show')) ?>;}));
document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{const input=document.getElementById(button.dataset.copy),status=document.getElementById('copyStatus');try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(input.value)}else{const copy=document.createElement('textarea');copy.value=input.value;copy.style.position='fixed';copy.style.opacity='0';document.body.append(copy);copy.select();const copied=document.execCommand('copy');copy.remove();if(!copied)throw new Error('copy')}status.textContent=<?= json_encode(key_text('تم نسخ المفتاح.','Key copied.')) ?>}catch(error){input.type='text';input.focus();input.select();status.textContent=<?= json_encode(key_text('حدد المفتاح وانسخه.','Select and copy the key.')) ?>}}));
document.querySelectorAll('.key-delete').forEach(form=>form.addEventListener('submit',event=>{if(!confirm(<?= json_encode(key_text('حذف هذا المفتاح وإلغاء دخوله المحفوظ؟','Delete this key and revoke its saved access?')) ?>))event.preventDefault()}));
</script></body></html>
