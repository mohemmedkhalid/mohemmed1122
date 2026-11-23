<?php
// manage_titles.php
require_once 'config.php'; 

// التحقق من صلاحيات المدير
if (!isAdmin()) {
    redirect('login.php'); 
}

$message = '';

// دالة حماية المدخلات مفترضة في config.php
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// معالجة إرسال النموذج (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];
    
    // مفاتيح البطاقات والأزرار
    $card_keys = ['tree_title', 'add_person_title', 'update_person_title', 'delete_person_title',
                  'users_management_title', 'family_list_title', 'dashboard_title'];
    $button_keys = ['btn_clan1_title', 'btn_admin_title'];
    
    $all_keys_to_process = array_merge($card_keys, $button_keys);

    foreach ($all_keys_to_process as $key_name) {
        if (isset($_POST[$key_name]) && isset($_POST[$key_name . '_ku'])) {
            $title_ar = sanitize_input($_POST[$key_name]);
            $title_ku = sanitize_input($_POST[$key_name . '_ku']);

            $updates[] = [
                'key_name' => $key_name,
                'title_ar' => $title_ar,
                'title_ku' => $title_ku
            ];
        }
    }
    
    // تنفيذ التحديثات
    $success_count = 0;
    $error_count = 0;
    
    $stmt = $mysqli->prepare("UPDATE dashboard_titles SET title_ar = ?, title_ku = ? WHERE key_name = ?");
    
    foreach ($updates as $update) {
        $key_name = $update['key_name'];
        $title_ar = $update['title_ar'];
        $title_ku = $update['title_ku'];

        if ($stmt) {
            $stmt->bind_param("sss", $title_ar, $title_ku, $key_name);
            if ($stmt->execute()) {
                $success_count++;
            } else {
                error_log("خطأ في تحديث العنوان $key_name: " . $stmt->error);
                $error_count++;
            }
        } else {
            error_log("خطأ في تهيئة الاستعلام: " . $mysqli->error);
            $error_count++;
            break;
        }
    }

    if ($stmt) {
        $stmt->close();
    }

    if ($error_count === 0 && $success_count > 0) {
        $message = "<div class='alert alert-custom-success'>ناڤێن نوی ب سەرکەفتی هاتنە پاراستن.</div>";
    } elseif ($success_count === 0 && $error_count === 0) {
         $message = "<div class='alert alert-custom-info'>هیچ گوهورینەک نەهاتیە ئەنجامدان.</div>";
    } else {
        $message = "<div class='alert alert-custom-danger'>رویدانەکێ چێبوو! چەند ناڤەک نەهاتنە گوهورین.</div>";
    }
}

// جلب العناوين الحالية
$current_titles = [];
$result = $mysqli->query("SELECT key_name, title_ar, title_ku FROM dashboard_titles");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $current_titles[$row['key_name']] = [
            'ar' => $row['title_ar'],
            'ku' => $row['title_ku']
        ];
    }
    $result->close();
}

// تعريف المجموعات
$button_groups = [
    'دوگمێن سەرەکی   ' => [
        'btn_clan1_title' => '   بنەمالا ئێکێ',
        'btn_admin_title' => ' کردار',
    ],
    'دوگمێن بنەمالا ئێکێ ' => [
        'tree_title' => 'زنجیرا بنەمالێ', 
        'add_person_title' => 'زێدەکرن',
        'update_person_title' => 'گوهرین',
        'delete_person_title' => 'ژێبرن',
    ],

    ' دوگمێن دی' => [
        'users_management_title' => 'بکارهێنەر',
        'family_list_title' => 'لیستە',
        'dashboard_title' => 'راپورت',
    ],
];

// دالة مساعدة
function get_saved_title($key, $default_ku, $default_ar, $lang, $titles) {
    if (isset($titles[$key])) {
        $esc_func = function_exists('escape') ? 'escape' : 'htmlspecialchars';
        return $esc_func($titles[$key][($lang === 'ar' ? 'ar' : 'ku')]);
    }
    return $lang === 'ar' ? $default_ar : $default_ku;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ئیدارەکرنا ناڤێن لوحة التحكم</title>
<meta name="theme-color" content="#000000">
<link rel="apple-touch-icon" href="icon-192.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* تخصيصات إضافية لتناسب التصميم الزجاجي */
        body {
            /* يتم التحكم بالخلفية عبر style.css ولكن نؤكد هنا */
            background-color: var(--bg-color);
            color: var(--text-color);
        }

    

        .group-header {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            margin-top: 30px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: var(--primary-color);
            text-shadow: 0 0 10px rgba(255,255,255,0.3);
        }

        .title-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        /* تنسيق حقول الإدخال لتكون زجاجية */
        .form-control {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: white !important;
            border-radius: 10px;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15) !important;
            box-shadow: 0 0 15px var(--orb1-color);
            border-color: var(--primary-color) !important;
        }

        .form-label {
            color: var(--text-color);
            opacity: 0.9;
        }
        
        /* ألوان النصوص المساعدة */
        .text-primary-custom {
           color: #d1e7dd;
            font-weight: bold;
        }

        /* تخصيص التنبيهات */
        .alert-custom-success {
            background: rgba(25, 135, 84, 0.2);
            border: 1px solid rgba(25, 135, 84, 0.5);
            color: #d1e7dd;
            backdrop-filter: blur(5px);
        }
        .alert-custom-info {
            background: rgba(13, 202, 240, 0.2);
            border: 1px solid rgba(13, 202, 240, 0.5);
            color: #cff4fc;
             backdrop-filter: blur(5px);
        }
        .alert-custom-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #f8d7da;
             backdrop-filter: blur(5px);
        }

        /* زر العودة العلوي */
        .btn-ctrl {
            background: rgba(255, 255, 255, 0.1);
            color: white; 
            padding: 12px 18px; 
            border-radius: 12px; 
            cursor: pointer;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-ctrl:hover { 
            background: var(--primary-color);
            color: var(--bg-color);
            transform: translateY(-3px);
            box-shadow: 0 0 15px rgba(255,255,255,0.5);
        }

        /* أزرار الإجراءات السفلية */
        .action-btn {
            padding: 12px 30px;
            border-radius: 12px;
            border: none;
            font-weight: bold;
            transition: var(--transition);
        }
        .btn-save {
            background: var(--primary-color);
            color: var(--bg-color);
            box-shadow: 0 0 15px rgba(255,255,255,0.3);
        }
        .btn-save:hover {
            background: white;
            transform: scale(1.05);
            box-shadow: 0 0 25px rgba(255,255,255,0.6);
        }
        .btn-back {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

    </style>
</head>
<body>

    <div class="background-orbs">
        <div class="orb orb1"></div>
        <div class="orb orb2"></div>
    </div>

    <div style="position: fixed; top: 20px; right: 20px; z-index: 100;">
        <a href="index.php" class="btn-ctrl"><i class="fas fa-home"></i></a>
    </div>

    <div class="main-container container">
        
        <header class="main-header text-center">
            <h1>گوهورینا ناڤێن دوگما</h1>
   
        </header>

        <?= $message ?>

        <form method="POST" action="manage_titles.php" class="glass-form">
            <?php foreach ($button_groups as $group_name => $titles_array): ?>
                
                <h4 class="group-header"><i class="fas fa-layer-group me-2"></i> <?= htmlspecialchars($group_name) ?></h4>
                
                <div class="p-2">
                    <?php foreach ($titles_array as $key => $default_name): ?>
                        <div class="row mb-3 title-row">
                            <h6 class="mb-3 text-primary-custom">
                                <i class="fas fa-tag me-2"></i> <?= htmlspecialchars($default_name) ?> 
                                <span style="font-size: 0.8rem; opacity: 0.6; font-weight: normal;">(<?= htmlspecialchars($key) ?>)</span>
                            </h6>
                            
                            <div style=" display: none;" class="col-md-6 mb-3">
                                <label for="<?= $key ?>" class="form-label">ناڤێ عەرەبی (AR):</label>
                                <input type="text" class="form-control" id="<?= $key ?>" name="<?= $key ?>" 
                                       value="<?= get_saved_title($key, $default_name, $default_name, 'ar', $current_titles) ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="<?= $key ?>_ku" class="form-label">ناڤێ کوردی (KU):</label>
                                <input type="text" class="form-control" id="<?= $key ?>_ku" name="<?= $key ?>_ku" 
                                       value="<?= get_saved_title($key, $default_name, $default_name, 'ku', $current_titles) ?>" required>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="text-center mt-5">
                <button type="submit" class="action-btn btn-save me-2"><i class="fas fa-save me-2"></i>تومارکرن</button>
                <a href="index.php" style="text-decoration: none;" class="action-btn btn-back"><i class="fas fa-arrow-right me-2"></i>گەرانەڤە</a>
            </div>
        </form>
        
        <div style="height: 50px;"></div>
    </div>
<script>
      
</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>