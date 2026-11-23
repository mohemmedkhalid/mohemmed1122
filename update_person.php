<?php
require_once 'header.php';

if (!isAdmin()) {
    $_SESSION['message'] = 'لیستا ئامادەکاریا بۆ بەکارهێنەرێت تەنیا بۆ ئەدمین.';
    $_SESSION['message_type'] = 'error';
    redirect('index.php');
}


$message = '';
$message_type = '';
$person_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$person_data = null;

// --- التعامل مع إرسال نموذج التحديث ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $id_to_update = (int)$_POST['id'];
    $full_name = trim($_POST['full_name']);
    $full_name_en = trim($_POST['full_name_en'] ?? '');
    $gender = $_POST['gender'];
    $status = $_POST['status'];
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : NULL;
    $father_id = !empty($_POST['father_id']) ? (int)$_POST['father_id'] : NULL;
    $mother_id = !empty($_POST['mother_id']) ? (int)$_POST['mother_id'] : NULL;
    $new_spouse_ids = isset($_POST['spouse_ids']) ? array_map('intval', $_POST['spouse_ids']) : [];

    $mysqli->begin_transaction();
    try {
        // تحديث البيانات الأساسية للشخص
        $stmt = $mysqli->prepare("UPDATE people2 SET full_name = ?, full_name_en = ?, status = ?, birth_date = ?, father_id = ?, mother_id = ? WHERE id = ?");
        $stmt->bind_param("ssssiis", $full_name, $full_name_en_val, $status, $birth_date, $father_id, $mother_id, $id_to_update);
        $full_name_en_val = $full_name_en !== '' ? $full_name_en : null;
        $stmt->execute();
        $stmt->close();

        // --- منطق تحديث الزيجات ---
        if ($gender == 'Male') {
            $husband_id = $id_to_update;
            $mysqli->query("DELETE FROM marriages WHERE husband_id = $husband_id");
            if (!empty($new_spouse_ids)) {
                $marriage_stmt = $mysqli->prepare("INSERT INTO marriages (husband_id, wife_id) VALUES (?, ?)");
                foreach ($new_spouse_ids as $wife_id) {
                    $marriage_stmt->bind_param("ii", $husband_id, $wife_id);
                    $marriage_stmt->execute();
                }
                $marriage_stmt->close();
            }
        } else { // Female
            $wife_id = $id_to_update;
            $mysqli->query("DELETE FROM marriages WHERE wife_id = $wife_id");
            if (!empty($new_spouse_ids)) {
                $husband_id = (int)$new_spouse_ids[0];
                $marriage_stmt = $mysqli->prepare("INSERT INTO marriages (husband_id, wife_id) VALUES (?, ?)");
                $marriage_stmt->bind_param("ii", $husband_id, $wife_id);
                $marriage_stmt->execute();
                $marriage_stmt->close();
            }
        }
        
        $mysqli->commit();
        $message = "   \"$full_name\" هاتە گوهرین!";
        $message_type = 'success';
        $person_id = $id_to_update; 

    } catch (Exception $e) {
        $mysqli->rollback();
        $message = "خطأ في التحديث: " . $e->getMessage();
        $message_type = 'danger';
    }
}


function generate_display_name($row) {
    $base_name = htmlspecialchars($row['full_name']);

    // بناء سلسلة نسب الشخص (للذكور والإناث غير المتزوجات)
    $lineage_string = '';
    if ($row['gender'] == 'Male' || ($row['gender'] == 'Female' && empty($row['husband_name']))) {
        $lineage_parts = [];
        if (!empty($row['p_father_name'])) {
            $lineage_parts[] = htmlspecialchars($row['p_father_name']);
            if (!empty($row['p_grandfather_name'])) {
                $lineage_parts[] = htmlspecialchars($row['p_grandfather_name']);
                if (!empty($row['p_great_grandfather_name'])) {
                    $lineage_parts[] = htmlspecialchars($row['p_great_grandfather_name']);
                }
            }
        }
        if (!empty($lineage_parts)) {
            $mysqliector = ($row['gender'] == 'Male') ? '' : '';
            $lineage_string = $mysqliector . ' ' . implode("  ", $lineage_parts);
        }
    }

    // بناء سلسلة نسب الزوج (للإناث المتزوجات)
    $husband_string = '';
    if ($row['gender'] == 'Female' && !empty($row['husband_name'])) {
        $husband_lineage_parts = [];
        $husband_lineage_parts[] = htmlspecialchars($row['husband_name']);
        if (!empty($row['husband_father_name'])) {
            $husband_lineage_parts[] = htmlspecialchars($row['husband_father_name']);
            if (!empty($row['husband_grandfather_name'])) {
                $husband_lineage_parts[] = htmlspecialchars($row['husband_grandfather_name']);
            }
        }
        $husband_string = "(زوجة " . implode("  ", $husband_lineage_parts) . ")";
    }

    // تجميع الاسم النهائي
    $final_display = $base_name;
    if (!empty($lineage_string)) {
        $final_display .= ' ' . $lineage_string;
    }
    if (!empty($husband_string)) {
        $final_display .= ' ' . $husband_string;
    }

    return $final_display;
}

function fetch_people2_data($mysqli, $gender_filter = null, $exclude_id = 0) {
    // تم تحديث الاستعلام لجلب الاسم الرباعي
    $sql = "SELECT 
                p.id, 
                p.full_name, 
                p.gender, 
                f.full_name AS p_father_name, 
                gf.full_name AS p_grandfather_name,
                ggf.full_name AS p_great_grandfather_name,
                h.full_name AS husband_name,
                hf.full_name AS husband_father_name,
                hgf.full_name AS husband_grandfather_name
            FROM people2 p
            LEFT JOIN people2 f ON p.father_id = f.id
            LEFT JOIN people2 gf ON f.father_id = gf.id
            LEFT JOIN people2 ggf ON gf.father_id = ggf.id
            LEFT JOIN marriages m ON p.id = m.wife_id
            LEFT JOIN people2 h ON m.husband_id = h.id
            LEFT JOIN people2 hf ON h.father_id = hf.id
            LEFT JOIN people2 hgf ON hf.father_id = hgf.id";
    
    $where = [];
    $params = [];
    $types = '';

    if ($gender_filter) {
        $where[] = "p.gender = ?";
        $params[] = $gender_filter;
        $types .= 's';
    }
    if ($exclude_id) {
        $where[] = "p.id != ?";
        $params[] = $exclude_id;
        $types .= 'i';
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.full_name";
    
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

$all_people2_result = fetch_people2_data($mysqli);

if ($person_id) {
    $stmt = $mysqli->prepare("SELECT * FROM people2 WHERE id = ?");
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $person_data = $result->fetch_assoc();
        $person_data['spouses'] = [];
        $spouse_col = ($person_data['gender'] == 'Male') ? 'wife_id' : 'husband_id';
        $id_col = ($person_data['gender'] == 'Male') ? 'husband_id' : 'wife_id';
        
        $res = $mysqli->query("SELECT $spouse_col FROM marriages WHERE $id_col = $person_id");
        while($row = $res->fetch_assoc()) {
            $person_data['spouses'][] = $row[$spouse_col];
        }

        $fathers_result = fetch_people2_data($mysqli, 'Male', $person_id);
        
        $current_spouses_ids_str = !empty($person_data['spouses']) ? implode(',', $person_data['spouses']) : '0';

        if ($person_data['gender'] == 'Male') {
             $spouse_sql = "SELECT p.id, p.full_name, p.gender, f.full_name as p_father_name, gf.full_name as p_grandfather_name, ggf.full_name as p_great_grandfather_name, h.full_name as husband_name, hf.full_name as husband_father_name, hgf.full_name as husband_grandfather_name FROM people2 p LEFT JOIN people2 f ON p.father_id = f.id LEFT JOIN people2 gf ON f.father_id = gf.id LEFT JOIN people2 ggf ON gf.father_id = ggf.id LEFT JOIN marriages m ON p.id = m.wife_id LEFT JOIN people2 h ON m.husband_id = h.id LEFT JOIN people2 hf ON h.father_id = hf.id LEFT JOIN people2 hgf ON hf.father_id = hgf.id WHERE p.gender = 'Female' AND (p.id NOT IN (SELECT wife_id FROM marriages) OR p.id IN ($current_spouses_ids_str)) GROUP BY p.id ORDER BY p.full_name";
        } else {
             $spouse_sql = "SELECT p.id, p.full_name, p.gender, f.full_name as p_father_name, gf.full_name as p_grandfather_name, ggf.full_name as p_great_grandfather_name, h.full_name as husband_name, hf.full_name as husband_father_name, hgf.full_name as husband_grandfather_name FROM people2 p LEFT JOIN people2 f ON p.father_id = f.id LEFT JOIN people2 gf ON f.father_id = gf.id LEFT JOIN people2 ggf ON gf.father_id = ggf.id LEFT JOIN marriages m ON p.id = m.wife_id LEFT JOIN people2 h ON m.husband_id = h.id LEFT JOIN people2 hf ON h.father_id = hf.id LEFT JOIN people2 hgf ON hf.father_id = hgf.id WHERE p.gender = 'Male' AND (p.id NOT IN (SELECT husband_id FROM marriages) OR p.id IN ($current_spouses_ids_str)) GROUP BY p.id ORDER BY p.full_name";
        }
        $available_spouses_result = $mysqli->query($spouse_sql);

    } else {
        $message = "ناڤێ دیار کری نینە   .";
        $message_type = 'danger';
        $person_id = 0;
    }
    $stmt->close();
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گوهرینا زانیاریا  </title>
<meta name="theme-color" content="#000000">
<link rel="apple-touch-icon" href="icon-192.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
    <style>
          :root {
            --bg-color:rgb(131 155 177);
    --orb1-color: #a78bfa; /* لون أوركيد فاتح */
    --orb2-color: #67e8f9; /* لون سماوي فاتح */
            --card-bg: rgba(255, 255, 255, 0); --card-border: rgb(255, 255, 255);
            --shadow-dark: rgba(0, 0, 0, 0.2); --text-color: #f1f5f9;
            --input-bg: rgba(42, 55, 80, 0.7); --primary-color: #5eb374ff;
            --hover-color: #28d945ff; --danger-color: #e74c3c; --muted-color:rgb(255, 255, 255);
        }
        body {
            background-color: var(--bg-color); font-family: 'Tajawal', sans-serif;
            color: var(--text-color); margin: 0; padding: 2.5rem 1rem;
            display: flex; align-items: center; justify-content: center; min-height: 100vh;
        }
        .main-container { max-width: 850px; width: 100%; margin: 0 auto; }
        .form-card {
            background: var(--card-bg);
            border-radius: 20px; padding: clamp(1.5rem, 5vw, 2.5rem);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 8px 40px 0 var(--shadow-dark);
        }
        .form-header { text-align: center; margin-bottom: 2.2rem; }
        .form-header .icon { font-size: 3.2rem; color: #ffffff; }
        .form-header h2 { margin-top: 1rem; font-weight: 700; font-size: 1.75rem; }
        .form-section { margin-bottom: 2rem; }
        .form-section-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--card-border); border-bottom: 2px solid var(--card-border); padding-bottom: 0.75rem; }
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-label { font-weight: 700; margin-bottom: 0.6rem; display: block; }
        .form-control, .choices__inner, select {
            background-color: var(--input-bg); 
            color: var(--text-color); border-radius: 10px; padding: 0.8rem 1rem;
            transition: all 0.3s ease; width: 100%; box-sizing: border-box;
            min-height: 50px;
        }
        select:not([multiple]) {
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: left 0.5rem center; background-repeat: no-repeat;
            background-size: 1.5em 1.5em; padding-right: 1rem; padding-left: 2.5rem;
        }
        select option { background-color: #1e293b; color: var(--text-color); }
        .form-control:focus, .choices.is-focused .choices__inner, select:focus {
            background-color: transparent; border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.3); outline: none;
        }
        .submit-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--hover-color));
            border: none; padding: 1rem; font-weight: bold; width: 100%; border-radius: 10px;
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
            color: white; font-size: 1.1rem; cursor: pointer; margin-top: 1.5rem;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4); }
        .nav-links { text-align: center; margin-bottom: 1.5rem; display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }
        .nav-link-custom { color: var(--muted-color); font-weight: bold; text-decoration: none; transition: all 0.3s ease; padding: 0.75rem 1.25rem; background: rgba(255,255,255,0.1); border-radius: 10px; border: 1px solid var(--card-border); }
        .nav-link-custom:hover { color: white; background: rgba(255,255,255,0.2); transform: translateY(-2px); border-color: var(--primary-color); }
        .alert { border-radius: 10px; backdrop-filter: blur(10px);  color: white; padding: 1rem; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background-color: rgba(46, 204, 113, 0.25); border-color: rgba(46, 204, 113, 0.6); }
        .alert-danger { background-color: rgba(231, 76, 60, 0.25); border-color: rgba(231, 76, 60, 0.6); }
        .row { display: flex; flex-wrap: wrap; margin: 0 -0.75rem; }
        .col { flex: 1; padding: 0 0.75rem; min-width: 200px; }
        
        /* ====== Choices.js Custom Styling -- START ====== */
        .choices__list--dropdown, .choices__list[aria-expanded] {
            z-index: 100; margin-top: 8px;
            border-radius: 12px; background-color: rgba(20, 28, 46, 0.95);
            -webkit-backdrop-filter: blur(15px); backdrop-filter: blur(15px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); padding: 0.5rem 0;
            transition: all 0.2s ease-out;
        }
        .choices__input {
            background-color: transparent !important; color: var(--text-color);
            font-size: 1rem; padding: 0.5rem 1rem; margin-bottom: 0.5rem;
            border: none; border-bottom: 1px solid var(--card-border);
        }
        .choices__item--choice {
            color: var(--text-color); padding: 12px 20px; transition: all 0.2s ease;
            border-radius: 8px; margin: 0 0.5rem;
          
        }
        .choices__item--choice:last-child { border-bottom: none; }
        .choices__list--dropdown .choices__item--selectable.is-highlighted {
            background-color: var(--primary-color); color: #0f172a;
            transform: translateX(5px); font-weight: bold;
        }
        .choices__list--dropdown .choices__item--no-choice {
            padding: 1rem; color: var(--muted-color);
        }
        .choices__inner {
            display: flex; align-items: center; padding: 0;
        }
        .choices[data-type*="select-multiple"] .choices__inner, .choices[data-type*="text"] .choices__inner { 
            padding: 0.35rem 0.65rem; 
        }
        .choices__list--multiple .choices__item {
            background: linear-gradient(45deg, var(--primary-color), var(--hover-color));
            border: none; border-radius: 8px; font-weight: bold; color: white;
        }
        .choices__item.is-highlighted .choices__button, .choices__button {
            filter: invert(1) brightness(2); transition: all 0.2s ease;
        }
        .choices__button:hover { transform: scale(1.2) rotate(90deg); }
        .choices__list--dropdown::-webkit-scrollbar { width: 8px; }
        .choices__list--dropdown::-webkit-scrollbar-track { background: transparent; }
        .choices__list--dropdown::-webkit-scrollbar-thumb {
            background-color: var(--primary-color); border-radius: 10px;
            background-clip: content-box;
        }
        /* ====== Choices.js Custom Styling -- END ====== */

        @media (max-width: 768px) {
            body { padding: 1.5rem 1rem; }
            .row { flex-direction: column; gap: 0; margin: 0; }
            .col { padding: 0; margin-bottom: 1.5rem;}
            .form-group { margin-bottom: 0; }
            .nav-links { flex-direction: column; gap: 0.5rem; }
        }
          .btn-ctrl {
            background: rgba(255,255,255,0.1);
            color: white; padding: 10px 15px; border-radius: 8px; cursor: pointer;
            backdrop-filter: blur(5px);
            transition: background 0.3s ease;
        ;
        }
        .btn-ctrl:hover { background: rgba(255,255,255,0.3); }

    </style>
</head>
<body>

<div class="main-container">
    <div style="position: fixed; top: 40px; right: 30px; z-index: 100;">
        <a href="index.php" class="btn-ctrl"><i class="fas fa-home"></i></a>
       
    </div>
    
    
    <div class="form-card">
        <div class="form-header">
            <div class="icon"><i class="fas fa-user-edit"></i></div>
          
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($person_id && $person_data): ?>
            <form method="post" action="update_person.php?id=<?php echo $person_id; ?>">
                <input type="hidden" name="id" value="<?php echo $person_id; ?>">
                <input type="hidden" name="gender" value="<?php echo htmlspecialchars($person_data['gender']); ?>">

                <div class="form-section">
                   
                    <div class="form-group">
                        <label for="full_name" class="form-label">ناڤ (کوردی)</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($person_data['full_name']); ?>" required dir="rtl">
                    </div>
                    <div class="form-group">
                        <label for="full_name_en" class="form-label" style="color:#67e8f9;"><i class="fas fa-language" style="margin-left:4px;"></i> English Name</label>
                        <input type="text" name="full_name_en" id="full_name_en" class="form-control" value="<?php echo htmlspecialchars($person_data['full_name_en'] ?? ''); ?>" placeholder="Full name in English..." dir="ltr" style="text-align:left;font-family:'Inter',sans-serif;">
                    </div>
                    <div class="row">
                        <div class="col">
                             <div class="form-group">
                                <label for="gender_display" class="form-label">رەگەز</label>
                                <input type="text" id="gender_display" class="form-control" value="<?php echo ($person_data['gender'] == 'Male') ? 'نێر' : 'مێ'; ?>" readonly style="background-color: rgba(42, 55, 80, 0.4);">
                                <small style="color:var(--muted-color); font-size: 0.8rem;">تو نەشێ رەگەزی بگوهرێ .</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="status" class="form-label">دوخ</label>
                                 <select name="status" id="status" class="form-control" required>
                                    <option value="Alive" <?php if($person_data['status'] == 'Alive') echo 'selected'; ?>>د ژیانێ دا</option>
                                    <option value="Deceased" <?php if($person_data['status'] == 'Deceased') echo 'selected'; ?>>مریە</option>
                                </select>
                            </div>
                        </div>
                    </div>
                     <div class="form-group">
                        <label for="birth_date" class="form-label">بەروارا ژدایک بونێ</label>
                        <input type="date" name="birth_date" id="birth_date" class="form-control" value="<?php echo htmlspecialchars($person_data['birth_date']); ?>">
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">پەیوەندیا خێزانێ  </h3>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="father_select" class="form-label">باب</label>
                                <select name="father_id" id="father_select">
                                    <option value="">-- دیار نەکریە  --</option>
                                    <?php mysqli_data_seek($fathers_result, 0); while($row = $fathers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>" <?php if($person_data['father_id'] == $row['id']) echo 'selected'; ?>>
                                            <?php echo generate_display_name($row); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group" style="display: none;">
                                <label for="mother_select" class="form-label">الأم</label>
                                <select name="mother_id" id="mother_select" disabled>
                                    <option value="">-- دیارکرنا بابی--</option>
                                </select>
                            </div>
                        </div>
                    </div>
                     <div class="form-group" id="spouses_group">
                        <label for="spouse_select" class="form-label"><?php echo ($person_data['gender'] == 'Male') ? 'الزوجات' : 'الزوج'; ?></label>
                        <select name="spouse_ids[]" id="spouse_select" multiple>
                            <?php if (isset($available_spouses_result)) { mysqli_data_seek($available_spouses_result, 0); while($row = $available_spouses_result->fetch_assoc()): ?>
                                 <option value="<?php echo $row['id']; ?>" <?php if(in_array($row['id'], $person_data['spouses'])) echo 'selected'; ?>>
                                    <?php echo generate_display_name($row); ?>
                                 </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="update" class="submit-btn"><i class="fas fa-save me-2"></i> گوهرین </button>
            </form>
        <?php else: ?>
            <form method="get" action="update_person.php">
                <div class="form-group">
                    <label for="person_select" class="form-label">دیارکرن وی کەسی یێ تە بڤێت بگوهری   </label>
                    <select name="id" id="person_select" required>
                        <option value="">-- لێگەریان  --</option>
                        <?php mysqli_data_seek($all_people2_result, 0); while($row = $all_people2_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>"><?php echo generate_display_name($row); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <br>
                <button type="submit" class="submit-btn"><i class="fas fa-edit me-2"></i> گوهرینا زانیاریێ کەسی  </button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const choicesConfig = (placeholder, removeItemBtn = false) => ({
        searchEnabled: true, removeItemButton: removeItemBtn, placeholder: true,
        placeholderValue: placeholder, noResultsText: 'هیچ ئەنجامێ نەدۆزرایەوە',
        noChoicesText: 'لا يوجد المزيد من الخيارات', itemSelectText: 'اضغط للاختيار',
        searchPlaceholderValue: 'لێگەریان ...', shouldSort: false
    });

    <?php if ($person_id && $person_data): ?>
    // Logic for the update form
    const fatherSelectEl = document.getElementById('father_select');
    const motherSelectEl = document.getElementById('mother_select');

    const fatherSelect = new Choices(fatherSelectEl, choicesConfig('-- لێگەیان بابی   --'));
    const motherSelect = new Choices(motherSelectEl, choicesConfig('--    --'));
    
    function updateMotherOptions(fatherId, selectedMotherId = null) {
        motherSelect.clearStore();
        if (!fatherId) {
            motherSelectEl.disabled = true;
            motherSelect.setChoices([{ value: '', label: '-- دیار کرنا بابی--', selected: true, disabled: true }]);
            return;
        }
        
        fetch(`get_wives.php?father_id=${fatherId}`)
            .then(response => response.json())
            .then(wives => {
                let motherOptions = [{ value: '', label: '-- دیارنەکریە  --'}];
                if (wives.length > 0) {
                    wives.forEach(wife => {
                        motherOptions.push({
                            value: wife.id,
                            label: wife.full_name
                        });
                    });
                     motherSelectEl.disabled = false;
                     motherSelect.setChoices(motherOptions, 'value', 'label', true).then(() => {
                        if (selectedMotherId) {
                            motherSelect.setValue([selectedMotherId]);
                        }
                     });
                } else {
                     motherSelectEl.disabled = true;
                     motherSelect.setChoices([{ value: '', label: 'هذا الأب ليس لديه زوجات', disabled: true, selected: true }], 'value', 'label', true);
                }
            }).catch(error => {
                console.error('Error fetching wives:', error);
                motherSelect.setChoices([{ value: '', label: 'خطأ في جلب الزوجات', selected: true, disabled: true }], 'value', 'label', true);
            });
    }

    fatherSelectEl.addEventListener('change', function() {
        updateMotherOptions(fatherSelect.getValue(true), null);
    });

    const initialFatherId = '<?php echo $person_data["father_id"] ?? ""; ?>';
    const initialMotherId = '<?php echo $person_data["mother_id"] ?? ""; ?>';
    if(initialFatherId){
        updateMotherOptions(initialFatherId, initialMotherId);
    }

    const spouseSelectEl = document.getElementById('spouse_select');
    const isMale = <?php echo json_encode($person_data['gender'] == 'Male'); ?>;
    if (!isMale) {
        spouseSelectEl.removeAttribute('multiple');
    }
    new Choices(spouseSelectEl, choicesConfig(isMale ? '-- اختر زوجة أو أكثر --' : '-- اختر الزوج --', true));

    <?php else: ?>
    // Logic for the selection form
    const personSelect = document.getElementById('person_select');
    if (personSelect) {
        new Choices(personSelect, choicesConfig('-- لێگەریان    --'));
    }
    <?php endif; ?>
});
  
</script>
</body>
</html>