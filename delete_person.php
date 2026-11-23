<?php
require_once __DIR__.'/config.php';
if (!isLoggedIn()) redirect('login.php');
if (!isAdmin()) { http_response_code(403); exit('هذه الصفحة للمشرف فقط.'); }
require_once __DIR__.'/includes/person-admin.php';
if (empty($_SESSION['people_csrf'])) $_SESSION['people_csrf'] = bin2hex(random_bytes(32));
$message = $_SESSION['people_notice'] ?? ''; unset($_SESSION['people_notice']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['people_csrf'], $_POST['csrf'])) {
        http_response_code(403); $error = 'انتهت صلاحية الطلب. حدّث الصفحة وحاول مجددًا.';
    } else {
        $id = filter_var($_POST['person_id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        try {
            if (!$id) throw new InvalidArgumentException('invalid_person');
            family_delete_one($mysqli, $id);
            $_SESSION['people_notice'] = 'حُذف الشخص المحدد فقط. بقي الأبناء والأحفاد وسجلاتهم محفوظة.';
            redirect('delete_person.php');
        } catch (Throwable $e) { error_log('Delete one person: '.$e->getMessage()); $error = 'لم يتم الحذف. راجع الشخص المحدد ثم حاول مجددًا.'; }
    }
}
$people = family_admin_people($mysqli);
$children = []; $childCounts = [];
foreach ($people as $id => $p) {
    $parent = isset($people[(int)$p['father_id']]) ? (int)$p['father_id'] : (isset($people[(int)$p['mother_id']]) ? (int)$p['mother_id'] : 0);
    if ($parent === $id) $parent = 0;
    $children[$parent][] = $id;
    foreach (array_unique([(int)$p['father_id'], (int)$p['mother_id']]) as $parentId) if ($parentId) $childCounts[$parentId] = ($childCounts[$parentId] ?? 0) + 1;
}
$ordered = []; $visited = [];
// Iterative traversal includes disconnected records and malformed cycles once.
foreach (array_merge($children[0] ?? [], array_keys($people)) as $root) {
    if (isset($visited[$root])) continue;
    $stack = [[$root, 0]];
    while ($stack) {
        [$id, $depth] = array_pop($stack);
        if (isset($visited[$id])) continue;
        $visited[$id] = true; $ordered[] = [$id, $depth];
        foreach (array_reverse($children[$id] ?? []) as $child) $stack[] = [$child, $depth + 1];
    }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>إدارة الأشخاص</title><link rel="stylesheet" href="assets/family-mobile.css?v=5"><script src="assets/family-ui.js?v=5"></script></head>
<body class="people-admin" data-family-page="people">
<header class="app-header"><a class="app-brand" href="index.php"><i class="fas fa-tree"></i> بنەمالا جوتیاری</a><a class="app-button" href="index.php"><i class="fas fa-home"></i> الرئيسية</a></header>
<main class="admin-shell"><div class="page-intro"><div><span class="eyebrow">الأشخاص والعائلة</span><h1>إدارة الأشخاص</h1><p>ابحث بالاسم الثلاثي، ثم افتح معلومات الشخص أو عدّلها.</p></div><a href="add_person.php" class="app-button primary"><i class="fas fa-user-plus"></i> إضافة شخص</a></div>
<?php if ($message): ?><p class="app-notice success" role="status"><?= family_h($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="app-notice danger" role="alert"><?= family_h($error) ?></p><?php endif; ?>
<section class="people-tools"><label class="wide">البحث عن شخص<input id="peopleSearch" type="search" placeholder="محمد بن خالد بن حسن، أو رقم الشخص" autocomplete="off"></label><label>الجنس<select id="peopleGender"><option value="">الجميع</option><option value="Male">ذكر</option><option value="Female">أنثى</option></select></label><span class="people-count" id="peopleCount" role="status"><?= count($people) ?> شخص</span></section>
<p class="table-help">الترتيب بحسب العائلة، ويظهر الأب أو الأم قبل الأبناء. زر الحذف يخص الشخص المحدد فقط.</p>
<div class="people-table-wrap"><table class="people-table"><thead><tr><th>الاسم الثلاثي</th><th>الأب والأم</th><th>الحالة</th><th>الأبناء</th><th>الإجراءات</th></tr></thead><tbody id="peopleRows">
<?php foreach ($ordered as [$id, $depth]): $p=$people[$id]; $name=family_triple($p,$people); $father=$people[(int)$p['father_id']]??null; $mother=$people[(int)$p['mother_id']]??null; ?>
<tr data-person="<?= $id ?>" data-gender="<?= family_h($p['gender']) ?>" data-search="<?= family_h($name.' '.($p['full_name_en']??'').' '.($mother?family_triple($mother,$people):'').' '.$id) ?>">
<td data-label="الشخص"><div class="tree-person" style="--level:<?= min(6,$depth) ?>"><span class="person-avatar <?= $p['gender']==='Female'?'female':'' ?>"><?= $p['gender']==='Female'?'♀':'♂' ?></span><div><strong><?= family_h($name) ?></strong><small>رقم <?= $id ?></small></div></div></td>
<td data-label="الأب والأم"><div class="parent-lines"><span>الأب: <?= family_h($father?family_triple($father,$people):'غير محدد') ?></span><span>الأم: <?= family_h($mother?family_triple($mother,$people):'غير محددة') ?></span></div></td>
<td data-label="الحالة"><span class="status-tag"><?= $p['status']==='Deceased'?'متوفى':'حي' ?></span></td><td data-label="الأبناء"><?= $childCounts[$id]??0 ?></td>
<td data-label="الإجراءات"><div class="row-actions"><a class="app-button" href="tree.php?id=<?= $id ?>" aria-label="شجرة <?= family_h($name) ?>"><i class="fas fa-tree"></i> الشجرة</a><a class="app-button" href="add_person.php?edit=<?= $id ?>&amp;return=delete_person.php"><i class="fas fa-pen"></i> تعديل</a><button class="app-button destructive" type="button" data-delete="<?= $id ?>" data-name="<?= family_h($name) ?>" data-children="<?= $childCounts[$id]??0 ?>"><i class="fas fa-trash"></i> حذف</button></div></td></tr>
<?php endforeach; ?>
</tbody></table><p id="peopleEmpty" class="app-empty" hidden>لا توجد أسماء تطابق البحث.</p></div>
</main>
<dialog id="deleteDialog" class="delete-dialog" aria-labelledby="deleteTitle"><form method="post" action="delete_person.php"><h2 id="deleteTitle">حذف هذا الشخص فقط؟</h2><p id="deleteName" class="delete-name"></p><p>سيُحذف سجل هذا الشخص وروابط زواجه وعضوياته. تبقى سجلات الأبناء والأحفاد، وتصبح صلة الأب أو الأم المحذوفة غير محددة.</p><p id="deleteChildren"></p><input type="hidden" name="csrf" value="<?= family_h($_SESSION['people_csrf']) ?>"><input type="hidden" name="person_id" id="deleteId"><div class="dialog-actions"><button type="button" class="app-button" id="cancelDelete">إلغاء</button><button type="submit" class="app-button destructive">حذف الشخص المحدد</button></div></form></dialog>
</body></html>
