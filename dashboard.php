<?php
require_once 'header.php';

// =================================================================
// --- جلب البيانات والإحصائيات من قاعدة البيانات (الكود المدمج) ---
// =================================================================

// 1. الإحصائيات العامة (العدد، الحالات، الجنس)
$total_count = $mysqli->query("SELECT COUNT(*) FROM people2")->fetch_row()[0] ?? 0;

$status_counts = ['Alive' => 0, 'Deceased' => 0];
$status_result = $mysqli->query("SELECT status, COUNT(*) as count FROM people2 GROUP BY status");
while ($row = $status_result->fetch_assoc()) { $status_counts[$row['status']] = $row['count']; }

$gender_counts = ['Male' => 0, 'Female' => 0];
$gender_result = $mysqli->query("SELECT gender, COUNT(*) as count FROM people2 GROUP BY gender");
while ($row = $gender_result->fetch_assoc()) { $gender_counts[$row['gender']] = $row['count']; }

// 2. إحصائيات الزواج والذرية
$marriages_count = $mysqli->query("SELECT COUNT(*) FROM marriages")->fetch_row()[0] ?? 0;
// حساب تعدد الزوجات (الرجال الذين تكرروا أكثر من مرة كزوج)
$polygamous_men = $mysqli->query("SELECT COUNT(*) FROM (SELECT husband_id FROM marriages GROUP BY husband_id HAVING COUNT(*) > 1) as t")->fetch_row()[0] ?? 0;

// 3. إحصائيات هيكلية (الأجداد، الأيتام)
$ancestors_count = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE is_primary_ancestor = 1")->fetch_row()[0] ?? 0;
// فقط الإناث الأيتам بدون أب (father_id NULL) وليس بالضرورة بدون أم
$orphans_count = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE gender = 'Female' AND father_id IS NULL AND is_primary_ancestor = 0")->fetch_row()[0] ?? 0;

// 3.1. حساب عدد الزوجات في العائلة بناءً على الفرق المطلوب
$wives_count = max(0, ($marriages_count - $orphans_count));

// 4. إحصائيات إضافية (مواليد هذا العام، متوسط العمر، البطاقة التموينية)
$ration_count = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE ration_number IS NOT NULL AND ration_number != ''")->fetch_row()[0] ?? 0;
$current_year = date('Y');
$births_this_year = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE YEAR(birth_date) = '$current_year'")->fetch_row()[0] ?? 0;

$avg_age_query = $mysqli->query("SELECT AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) FROM people2 WHERE status = 'Alive' AND birth_date IS NOT NULL AND birth_date != '0000-00-00'")->fetch_row()[0] ?? 0;
$avg_age = round($avg_age_query);

// 5. الأب والأم الأكثر ذرية
$top_father = $mysqli->query("SELECT p.full_name, p.full_name_en, COUNT(c.id) as child_count FROM people2 p JOIN people2 c ON p.id = c.father_id GROUP BY p.id ORDER BY child_count DESC LIMIT 1")->fetch_assoc();
$top_mother = $mysqli->query("SELECT p.full_name, p.full_name_en, COUNT(c.id) as child_count FROM people2 p JOIN people2 c ON p.id = c.mother_id GROUP BY p.id ORDER BY child_count DESC LIMIT 1")->fetch_assoc();

// 6. حساب الأجيال (العمق)
$all_people2_query = $mysqli->query("SELECT id, father_id FROM people2");
$people2_map = [];
$children_map = [];
while ($row = $all_people2_query->fetch_assoc()) {
    $people2_map[$row['id']] = $row;
    if ($row['father_id']) {
        $children_map[$row['father_id']][] = $row['id'];
    }
}

$max_depth = 0;
function calculate_depth($id, $current_depth, &$children_map) {
    global $max_depth;
    if ($current_depth > $max_depth) $max_depth = $current_depth;
    if (isset($children_map[$id])) {
        foreach ($children_map[$id] as $child_id) {
            calculate_depth($child_id, $current_depth + 1, $children_map);
        }
    }
}

foreach ($people2_map as $id => $person) {
    if (!$person['father_id']) {
        calculate_depth($id, 1, $children_map);
    }
}
?>
<?php
$lang = get_lang();
$isEn = ($lang === 'en');
$dir  = $isEn ? 'ltr' : 'rtl';
?>
<!DOCTYPE html>
<html lang="<?= $isEn ? 'en' : 'ku' ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="theme-color" content="#000000">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEn ? 'Dashboard - Family Statistics' : 'لوحة المعلومات - إحصائيات العائلة' ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-bg: rgb(131, 155, 177);
            --glass-bg: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.2);
            --card-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.15);
            --text-white: #ffffff;
            
            --color-total: #4a90e2;
            --color-male: #5dade2;
            --color-female: #e91e63;
            --color-alive: #2ecc71;
            --color-dead: #95a5a6;
            --color-gold: #f1c40f;
            --color-orange: #e67e22;
        }

        @font-face {
            font-family: 'CustomFont';
            src: url('./Speda.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            font-family: 'CustomFont', 'Tajawal', Arial, sans-serif;
 background:linear-gradient(135deg, #3a5251, #000000, #57565e);            color: var(--text-white);
            min-height: 100vh;
            padding-top: 20px;
            padding-bottom: 40px;
            overflow-x: hidden;
        }

        /* 1. شكل البطاقات الرئيسية */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px;
            margin-bottom: 25px;
        }

        @media (max-width: 992px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; } }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.25); }
        .stat-card h3 { font-size: 1rem; color: rgba(255, 255, 255, 0.9); margin-bottom: 10px; z-index: 2; position: relative; }
        .stat-card .number { font-size: 2.2rem; font-weight: bold; color: #fff; z-index: 2; position: relative; }
        .stat-card .icon-bg { position: absolute; bottom: -10px; left: 10px; font-size: 4rem; opacity: 0.15; z-index: 1; transition: all 0.3s; }
        .stat-card:hover .icon-bg { transform: scale(1.1) rotate(5deg); opacity: 0.25; }

        .border-top-blue { border-top: 4px solid var(--color-total); }
        .border-top-green { border-top: 4px solid var(--color-alive); }
        .border-top-gray { border-top: 4px solid var(--color-dead); }
        .border-top-sky { border-top: 4px solid var(--color-male); }
        .border-top-pink { border-top: 4px solid var(--color-female); }
        .border-top-gold { border-top: 4px solid var(--color-gold); }

        /* 2. شكل البطاقات العريضة (للأب والأم الأكثر ذرية) */
        .wide-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05));
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .wide-card:hover { transform: scale(1.02); }
        .wide-card .icon-wrap {
            width: 70px; height: 70px;
            border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            font-size: 2rem;
            background: rgba(255,255,255,0.2);
        }

        /* 3. شكل الإحصائيات الأفقية المصغرة */
        .mini-stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .mini-stat {
            background: rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-right: 4px solid var(--glass-border);
            backdrop-filter: blur(5px);
        }
        .mini-stat.br-blue { border-right-color: #3498db; }
        .mini-stat.br-green { border-right-color: #2ecc71; }
        .mini-stat.br-orange { border-right-color: #e67e22; }
        .mini-stat.br-red { border-right-color: #e74c3c; }
        .mini-stat.br-purple { border-right-color: #9b59b6; }
        
        .mini-stat .m-title { font-size: 0.9rem; opacity: 0.8; }
        .mini-stat .m-value { font-size: 1.4rem; font-weight: bold; }

        /* حاوية الرسوم البيانية */
        .charts-container {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;
        }
        @media (max-width: 768px) { .charts-container { grid-template-columns: 1fr; } }
        .chart-box {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 16px; padding: 20px;
            box-shadow: var(--card-shadow);
        }
        .chart-title { text-align: center; margin-bottom: 15px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        
        .page-header { text-align: center; margin-bottom: 30px; }
        .btn-ctrl { background: rgba(255,255,255,0.1); color: white; padding: 10px 15px; border-radius: 8px; cursor: pointer; backdrop-filter: blur(5px); transition: background 0.3s; }
        .btn-ctrl:hover { background: rgba(255,255,255,0.3); color: white; }
/* CONTROLS */
#controls {
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 100;
    display: flex;
    gap: 8px;
}
.btn-ctrl {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    width: 42px; height: 42px;
    border-radius: 10px;
    cursor: pointer;
    backdrop-filter: blur(10px);
    font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
.btn-ctrl:hover, .btn-ctrl:active {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.4);
}

/* ZOOM BUTTONS */
#zoom-controls {
    position: fixed;
    bottom: 20px;
    left: 20px;
    z-index: 200;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.zoom-btn {
    width: 36px; height: 36px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px;
    color: white;
    font-size: 18px;
    font-weight: 300;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(10px);
    transition: all 0.15s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
.zoom-btn:hover { background: rgba(255,255,255,0.18); }

/* SEARCH TOGGLE BTN */
#search-toggle-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 46px; height: 46px;
    background: rgba(246,201,14,0.15);
    border: 1px solid rgba(246,201,14,0.4);
    border-radius: 50%;
    color: var(--focused-border-color);
    font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    backdrop-filter: blur(10px);
    z-index: 201;
    transition: all 0.2s;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
#search-toggle-btn:hover {
    background: rgba(246,201,14,0.3);
    transform: scale(1.05);
}


    </style>
</head>
<body>
    <div id="controls">
        <a href="index.php" class="btn-ctrl" title="<?= $isEn ? 'Home' : 'الرئيسية' ?>"><i class="fas fa-home"></i></a>
        <a href="set_lang.php?lang=<?= $isEn ? 'ku' : 'en' ?>&back=dashboard.php"
           class="btn-ctrl" title="<?= $isEn ? 'Switch to Kurdish' : 'Switch to English' ?>" style="font-size:13px;"><?= $isEn ? '🟡' : '🇬🇧' ?></a>
    </div>
    
    <div class="container" style="margin-top: 50px;">
        <div class="page-header">
            <h2><i class="fas fa-chart-pie me-2"></i><?= $isEn ? 'Statistics & General Info' : 'ئامار و زانیاریێن گشتی' ?></h2>
            <p style="opacity: 0.8;"><?= $isEn ? 'A smart summary of all family records' : 'پوختەیا داتایێن تۆمارکری د سیستەمی دا ب شێوەیەکێ پێشکەفتی' ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card border-top-blue">
                <h3><?= $isEn ? 'Total Members' : 'هەموو کەس (الإجمالي)' ?></h3>
                <div class="number count-up" data-target="<?php echo $total_count; ?>">0</div>
                <i class="fas fa-users icon-bg"></i>
            </div>
            <div class="stat-card border-top-green">
                <h3><?= $isEn ? 'Alive' : 'ل ژیانێ (الأحياء)' ?></h3>
                <div class="number count-up" data-target="<?php echo $status_counts['Alive']; ?>">0</div>
                <i class="fas fa-heart icon-bg"></i>
            </div>
            <div class="stat-card border-top-gray">
                <h3><?= $isEn ? 'Deceased' : 'وەغەرکری (المتوفين)' ?></h3>
                <div class="number count-up" data-target="<?php echo $status_counts['Deceased']; ?>">0</div>
                <i class="fas fa-user-slash icon-bg"></i>
            </div>
            <div class="stat-card border-top-sky">
                <h3><?= $isEn ? 'Males' : 'نێر (الذكور)' ?></h3>
                <div class="number count-up" data-target="<?php echo $gender_counts['Male']; ?>">0</div>
                <i class="fas fa-mars icon-bg"></i>
            </div>
            <div class="stat-card border-top-pink">
                <h3><?= $isEn ? 'Females' : 'مێ (الإناث)' ?></h3>
                <div class="number count-up" data-target="<?php echo $gender_counts['Female']; ?>">0</div>
                <i class="fas fa-venus icon-bg"></i>
            </div>
            <div class="stat-card border-top-gold">
                <h3><?= $isEn ? 'Marriages' : 'خێزان (عقود الزواج)' ?></h3>
                <div class="number count-up" data-target="<?php echo $marriages_count; ?>">0</div>
                <i class="fas fa-rings-wedding icon-bg"></i>
            </div>
            
            <div class="stat-card border-top-blue">
                <h3><?= $isEn ? 'Ancestors' : 'باب و باپیر (الأجداد)' ?></h3>
                <div class="number count-up" data-target="<?php echo $ancestors_count; ?>">0</div>
                <i class="fas fa-tree icon-bg"></i>
            </div>
            <div class="stat-card border-top-gold">
                <h3><?= $isEn ? 'Generations' : 'نەوە (عدد الأجيال)' ?></h3>
                <div class="number"><?php echo $max_depth; ?></div>
                <i class="fas fa-layer-group icon-bg"></i>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="wide-card">
                    <div class="icon-wrap text-warning"><i class="fas fa-crown"></i></div>
                    <div>
                        <h5 class="mb-1" style="opacity: 0.8;"><?= $isEn ? 'Most Children (Father)' : 'بابێ زۆرترین زارۆک (الأكثر ذرية)' ?></h5>
                        <h3 class="mb-0 fw-bold"><?= isset($top_father) ? get_person_name($top_father) : ($isEn ? 'N/A' : 'غير متاح') ?></h3>
                        <div class="mt-1 text-warning fw-bold"><i class="fas fa-child me-1"></i> <?= $top_father['child_count'] ?? 0 ?> <?= $isEn ? 'children' : 'زارۆک' ?></div>
                    </div>
                </div>
            </div>
           
            </div>
        </div>

        <div class="mini-stats-bar">
            <div class="mini-stat br-blue">
                <div>
                    <div class="m-title"><?= $isEn ? 'Average Age (Alive)' : 'تێکڕای ژیێ ساخان' ?></div>
                    <small><?= $isEn ? '' : 'متوسط العمر' ?></small>
                </div>
                <div class="m-value"><?= $avg_age ?> <span style="font-size:0.8rem; font-weight:normal;"><?= $isEn ? 'yrs' : 'سال' ?></span></div>
            </div>
            <div class="mini-stat br-green">
                <div>
                    <div class="m-title"><?= $isEn ? 'Births This Year' : 'مواليد هذا العام' ?></div>
                    <small><?= $isEn ? '' : 'ژدایکبوویێن ڤێ سالێ' ?></small>
                </div>
                <div class="m-value count-up" data-target="<?= $births_this_year ?>">0</div>
            </div>
            <div class="mini-stat br-purple">
                <div>
                    <div class="m-title"><?= $isEn ? 'Polygamous Men' : 'تعدد الزوجات' ?></div>
                    <small><?= $isEn ? '' : 'فرەژنی' ?></small>
                </div>
                <div class="m-value count-up" data-target="<?= $polygamous_men ?>">0</div>
            </div>
            <div class="mini-stat br-red">
                <div>
                    <div class="m-title"><?= $isEn ? 'Wives from Outside Family' : 'هەڤژینێن ژدەرڤەی بنەمالێ' ?></div>
                    <small></small>
                </div>
                <div class="m-value count-up" data-target="<?= $orphans_count ?>">0</div>
            </div>

<div class="mini-stat br-red">
                <div>
                    <div class="m-title"><?= $isEn ? 'Wives from Within Family' : 'هەڤژینێن ناڤ بنەمالێ دا' ?></div>
                    <small></small>
                </div>
                <div class="m-value count-up" data-target="<?php echo $wives_count; ?>">0</div>
            </div>

            <div class="mini-stat br-orange">
                <div>
                    <div class="m-title"><?= $isEn ? 'Ration Cards' : 'کۆبۆنا خوارنێ' ?></div>
                    <small><?= $isEn ? '' : 'بطاقة تموينية' ?></small>
                </div>
                <div class="m-value count-up" data-target="<?= $ration_count ?>">0</div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-box">
                <div class="chart-title"><?= $isEn ? 'Alive vs Deceased' : 'رێژەیا ژیان و مرنێ' ?></div>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title"><?= $isEn ? 'Gender Ratio (Male / Female)' : 'رێژەیا ڕەگەزی (نێر و مێ)' ?></div>
                <canvas id="genderChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // تفعيل الأنيميشن الخاص بالعد التصاعدي للأرقام
            const counters = document.querySelectorAll('.count-up');
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const duration = 1500; 
                const increment = target / (duration / 16); 
                
                let current = 0;
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.innerText = Math.ceil(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.innerText = target;
                    }
                };
                updateCounter();
            });

            // إعدادات الخطوط والألوان للرسم البياني
            Chart.defaults.color = '#fff';
            Chart.defaults.font.family = 'CustomFont, Tajawal';

            // رسم بياني للحالة
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: [<?= $isEn ? "'Alive', 'Deceased'" : "'ل ژیانێ (أحياء)', 'وەغەرکری (متوفين)'" ?>],
                    datasets: [{
                        data: [<?php echo $status_counts['Alive']; ?>, <?php echo $status_counts['Deceased']; ?>],
                        backgroundColor: ['rgba(46, 204, 113, 0.8)', 'rgba(149, 165, 166, 0.8)'],
                        borderColor: ['#2ecc71', '#95a5a6'],
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });

            // رسم بياني للجنس
            new Chart(document.getElementById('genderChart'), {
                type: 'pie',
                data: {
                    labels: [<?= $isEn ? "'Males', 'Females'" : "'نێر (ذكور)', 'مێ (إناث)'" ?>],
                    datasets: [{
                        data: [<?php echo $gender_counts['Male']; ?>, <?php echo $gender_counts['Female']; ?>],
                        backgroundColor: ['rgba(52, 152, 219, 0.8)', 'rgba(233, 30, 99, 0.8)'],
                        borderColor: ['#3498db', '#e91e63'],
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        });
    </script>
</body>
</html>
<?php
if (isset($mysqli)) {
    $mysqli->close();
}
?>