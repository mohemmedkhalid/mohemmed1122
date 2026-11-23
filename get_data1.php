<?php
// get_data.php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once 'header.php'; // تأكد أن هذا الملف يحتوي على اتصال قاعدة البيانات $mysqli

$focus_id = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $focus_id = (int)$_GET['id'];
} else {
    // تحديد الشخص الافتراضي (الجد الأكبر أو أي شخص)
    $ancestor_q = $mysqli->query("SELECT id FROM people1 WHERE is_primary_ancestor = 1 LIMIT 1");
    if ($ancestor_q->num_rows > 0) {
        $focus_id = (int)$ancestor_q->fetch_assoc()['id'];
    } else {
        $any_person_result = $mysqli->query("SELECT id FROM people1 ORDER BY id LIMIT 1");
        if($any_person_row = $any_person_result->fetch_assoc()){
            $focus_id = (int)$any_person_row['id'];
        }
    }
}

$nodes = [];
$parent_of_focus = null;

if ($focus_id) {
    $related_ids = [$focus_id];

    // 1. جلب بيانات الشخص المختار لمعرفة الأب والأم والزوجات
    $focus_person_res = $mysqli->query("SELECT father_id, mother_id, gender FROM people1 WHERE id = $focus_id");
    if ($focus_person_row = $focus_person_res->fetch_assoc()) {
        $parent_of_focus = $focus_person_row['father_id'] ? (int)$focus_person_row['father_id'] : null;
        if ($focus_person_row['father_id']) $related_ids[] = (int)$focus_person_row['father_id'];
        if ($focus_person_row['mother_id']) $related_ids[] = (int)$focus_person_row['mother_id'];
        
        // جلب الأزواج/الزوجات
        $spouse_col = ($focus_person_row['gender'] == 'Male') ? 'wife_id' : 'husband_id';
        $id_col = ($focus_person_row['gender'] == 'Male') ? 'husband_id' : 'wife_id';
        $spouses_res = $mysqli->query("SELECT $spouse_col FROM marriages1 WHERE $id_col = $focus_id");
        while ($spouse_row = $spouses_res->fetch_assoc()) {
            $related_ids[] = (int)$spouse_row[$spouse_col];
        }
    }

    // 2. جلب الأبناء
    $children_res = $mysqli->query("SELECT id FROM people1 WHERE father_id = $focus_id OR mother_id = $focus_id ORDER BY birth_date ASC");
    while ($child_row = $children_res->fetch_assoc()) {
        $related_ids[] = (int)$child_row['id'];
    }

    $related_ids = array_unique(array_filter($related_ids));
    
    if (!empty($related_ids)) {
        $ids_string = implode(',', $related_ids);

        // جلب البيانات الأساسية
        $people1_data = [];
        $result = $mysqli->query("SELECT id, full_name, gender, status, birth_date, father_id, mother_id, is_primary_ancestor FROM people1 WHERE id IN ($ids_string)");
        while($row = $result->fetch_assoc()) {
            $people1_data[$row['id']] = $row;
        }

        // جلب العلاقات الزوجية لربط الأسماء
        $marriages1_data = [];
        $marriages1_res = $mysqli->query("SELECT husband_id, wife_id FROM marriages1 WHERE husband_id IN ($ids_string) OR wife_id IN ($ids_string)");
        while($m_row = $marriages1_res->fetch_assoc()) {
            $marriages1_data[] = $m_row;
        }

        // --- التجهيز النهائي للعقد (Nodes) ---
        // نعيد الترتيب حسب related_ids للحفاظ على الترتيب المنطقي
        foreach ($related_ids as $id) {
            if (!isset($people1_data[$id])) continue;
            
            $row = $people1_data[$id];
            $tags = [];
            $tags[] = ($row['gender'] == 'Male') ? 'male' : 'female';
            
            if ($row['id'] == $focus_id) $tags[] = 'focused';
            if ($row['status'] == 'Deceased') $tags[] = 'deceased';
            if ($row['is_primary_ancestor']) $tags[] = 'primary-ancestor';
            
            // تحديد شركاء الحياة (IDs)
            $partners_ids = [];
            foreach ($marriages1_data as $marriage) {
                if ($marriage['husband_id'] == $id && isset($people1_data[$marriage['wife_id']])) {
                    $partners_ids[] = (int)$marriage['wife_id'];
                } elseif ($marriage['wife_id'] == $id && isset($people1_data[$marriage['husband_id']])) {
                    $partners_ids[] = (int)$marriage['husband_id'];
                }
            }

            // === [جديد] جلب أسماء الزوجات/الأزواج للعرض في المودال ===
            $spouses_names_arr = [];
            // نبحث في جدول الزواج عن أسماء الشركاء (حتى لو لم يكونوا ظاهرين في الشجرة الحالية)
            // ملاحظة: لتبسيط الاستعلام، سنبحث عن أسمائهم من الذاكرة إذا كانوا موجودين، أو نقوم باستعلام فرعي سريع إذا تطلب الأمر دقة عالية
            // هنا سنعتمد على أن الأزواج غالبًا يتم تحميلهم مع العائلة، أو سنكتب استعلاماً بسيطاً لجلب اسم الزوج
            $partner_query_col = ($row['gender'] == 'Male') ? 'husband_id' : 'wife_id';
            $partner_target_col = ($row['gender'] == 'Male') ? 'wife_id' : 'husband_id';
            
            $p_res = $mysqli->query("
                SELECT p.full_name 
                FROM marriages1 m 
                JOIN people1 p ON p.id = m.$partner_target_col 
                WHERE m.$partner_query_col = $id
            ");
            while($p_row = $p_res->fetch_assoc()){
                $spouses_names_arr[] = $p_row['full_name'];
            }
            $spouses_str = empty($spouses_names_arr) ? 'نەدیارکرە ' : implode('، ', $spouses_names_arr);


            // === [جديد] حساب عدد الأبناء ===
            $children_count_res = $mysqli->query("SELECT COUNT(*) as cnt FROM people1 WHERE father_id = $id OR mother_id = $id");
            $children_count = $children_count_res->fetch_assoc()['cnt'];

            
            $birth_year = $row['birth_date'] ? date('Y', strtotime($row['birth_date'])) : '';
            // التاريخ الكامل للمودال
            $full_birth_date = $row['birth_date'] ? $row['birth_date'] : 'نە دیارە ';

            $node = [
                'id'            => (int)$row['id'],
                'name'          => $row['full_name'],
                'gender'        => $row['gender'],
                'tags'          => array_unique($tags),
                'birth_year'    => $birth_year,
                'full_date'     => $full_birth_date, // تاريخ الميلاد الكامل
                'status'        => $row['status'],
                'spouses_names' => $spouses_str,     // أسماء الزوجات
                'children_count'=> $children_count   // عدد الأبناء
            ];
            
            if ($row['father_id'] && isset($people1_data[$row['father_id']])) $node['fid'] = (int)$row['father_id'];
            if ($row['mother_id'] && isset($people1_data[$row['mother_id']])) $node['mid'] = (int)$row['mother_id'];
            if (!empty($partners_ids)) $node['pids'] = $partners_ids;
            
            $nodes[] = $node;
        }
    }
}

echo json_encode(['nodes' => $nodes, 'parent_id' => $parent_of_focus]);
$mysqli->close();
?>