<?php
require_once 'header.php';

// =================================================================
// --- 1. جلب البيانات والإحصائيات من قاعدة البيانات ---
// =================================================================

// 1. الإحصائيات العامة
$total_count = $mysqli->query("SELECT COUNT(*) FROM people2")->fetch_row()[0] ?? 0;

$status_result = $mysqli->query("SELECT status, COUNT(*) as count FROM people2 GROUP BY status");
$status_counts = ['Alive' => 0, 'Deceased' => 0];
while ($row = $status_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

$gender_result = $mysqli->query("SELECT gender, COUNT(*) as count FROM people2 GROUP BY gender");
$gender_counts = ['Male' => 0, 'Female' => 0];
while ($row = $gender_result->fetch_assoc()) {
    $gender_counts[$row['gender']] = $row['count'];
}

// 2. إحصائيات الزواج والذرية
// عقود الزواج (عدد الصفوف في جدول الزواج)
$marriages_count = $mysqli->query("SELECT COUNT(*) FROM marriages")->fetch_row()[0] ?? 0;

// عدد الأبناء والبنات (الذين لديهم أب مسجل)
$sons_count = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE gender = 'Male' AND father_id IS NOT NULL")->fetch_row()[0] ?? 0;
$daughters_count = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE gender = 'Female' AND father_id IS NOT NULL")->fetch_row()[0] ?? 0;

// الذكور المتزوجين (الموجودين كـ husband_id)
$married_men_count = $mysqli->query("SELECT COUNT(DISTINCT husband_id) FROM marriages")->fetch_row()[0] ?? 0;
// الذكور غير المتزوجين (إجمالي الذكور - المتزوجين)
// ملاحظة: هذا تقدير، الأدق هو فحص من هم فوق سن معين، لكن سنعتمد على العدد الكلي
$unmarried_men_count = max(0, $gender_counts['Male'] - $married_men_count);

// 3. إحصائيات هيكلية (الأجداد والفروع)
$ancestors_count = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE is_primary_ancestor = 1")->fetch_row()[0] ?? 0;

// 4. إحصائيات إضافية متقدمة
// الأب الأكثر ذرية (أكثر شخص تكرر كـ father_id)
$most_children_stmt = $mysqli->query("
    SELECT p.full_name, COUNT(c.id) as child_count 
    FROM people2 p 
    JOIN people2 c ON p.id = c.father_id 
    GROUP BY p.id 
    ORDER BY child_count DESC 
    LIMIT 1
");
$top_father = $most_children_stmt->fetch_assoc();
$top_father_name = $top_father ? $top_father['full_name'] : 'غير متاح';
$top_father_count = $top_father ? $top_father['child_count'] : 0;

// نسبة توفر تواريخ الميلاد (لقياس جودة البيانات)
$dates_count = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE birth_date IS NOT NULL")->fetch_row()[0] ?? 0;
$dates_percentage = $total_count > 0 ? round(($dates_count / $total_count) * 100, 1) : 0;

// --- حساب الأجيال (العمق) ---
$all_people2_query = $mysqli->query("SELECT id, father_id FROM people2");
$people2_map = [];
$children_map = [];
while ($row = $all_people2_query->fetch_assoc()) {
    $people2_map[$row['id']] = $row;
    if ($row['father_id']) {
        $children_map[$row['father_id']][] = $row['id'];
    }
}

// حساب متوسط الأبناء للأباء فقط
$fathers_count = count($children_map);
$total_children_linked = $sons_count + $daughters_count;
$avg_children = $fathers_count > 0 ? round($total_children_linked / $fathers_count, 1) : 0;

// حساب عمق الشجرة
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

// نبدأ الحساب من الأشخاص الذين ليس لديهم أب مسجل (الجذور)
foreach ($people2_map as $id => $person) {
    if (!$person['father_id']) {
        calculate_depth($id, 1, $children_map);
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
<meta name="theme-color" content="#000000">
<link rel="apple-touch-icon" href="icon-192.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المعلومات - إحصائيات العائلة</title>
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
            
            /* ألوان الإحصائيات */
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
            background-color: var(--primary-bg);
            /* تدرج لوني خفيف لتعزيز العمق */
            background: linear-gradient(135deg, rgb(131, 155, 177) 0%, rgb(100, 125, 147) 100%);
            color: var(--text-white);
            min-height: 100vh;
            padding-top: 20px;
            padding-bottom: 40px;
            overflow-x: hidden;
        }

        /* تنسيق الشبكة لتناسب الهاتف بعمودين */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 أعمدة للشاشات الكبيرة */
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 576px) {
            .stats-grid { 
                grid-template-columns: repeat(2, 1fr); /* إجبار عمودين في الهاتف */
                gap: 10px; /* تقليل المسافة في الهاتف */
            }
        }

        /* تنسيق البطاقات */
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.25);
        }

        /* جعل البطاقة مضغوطة للهاتف */
        @media (max-width: 576px) {
            .stat-card {
                padding: 15px;
                min-height: 110px;
            }
            .stat-card h3 {
                font-size: 0.85rem;
                margin-bottom: 5px;
            }
            .stat-card .number {
                font-size: 1.5rem !important;
            }
            .stat-card .icon-bg {
                font-size: 2.5rem !important;
                opacity: 0.1;
                bottom: -5px;
                left: -5px;
            }
        }

        .stat-card h3 {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            margin: 0 0 10px 0;
            z-index: 2;
        }

        .stat-card .number {
            font-size: 2.2rem;
            font-weight: bold;
            color: #fff;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card .icon-bg {
            position: absolute;
            bottom: -10px;
            left: 10px;
            font-size: 4rem;
            opacity: 0.15;
            z-index: 1;
            transition: all 0.3s;
        }

        .stat-card:hover .icon-bg {
            transform: scale(1.1) rotate(5deg);
            opacity: 0.25;
        }

        /* ألوان مخصصة للحواف العلوية للبطاقات */
        .border-top-blue { border-top: 4px solid var(--color-total); }
        .border-top-green { border-top: 4px solid var(--color-alive); }
        .border-top-gray { border-top: 4px solid var(--color-dead); }
        .border-top-sky { border-top: 4px solid var(--color-male); }
        .border-top-pink { border-top: 4px solid var(--color-female); }
        .border-top-gold { border-top: 4px solid var(--color-gold); }
        .border-top-orange { border-top: 4px solid var(--color-orange); }

        /* حاوية الرسوم البيانية */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr; /* عمود واحد للرسوم البيانية في الهاتف */
            }
        }

        .chart-box {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .chart-title {
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            font-weight: bold;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .btn-home {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            padding: 5px 15px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-home:hover {
            background: rgba(255,255,255,0.4);
            color: white;
        }
        .navbar {
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid var(--border-glass);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
    
 <div style="position: fixed; top: 40px; right: 30px; z-index: 100;">
        <a href="index.php" class="btn-ctrl"><i class="fas fa-home"></i></a>
       
    </div>
    
    
    <div class="container" style="margin-top: 70px;">
        <div class="page-header">
            <h2><i class="fas fa-chart-pie"></i> ئامار و زانیاریێن گشتی</h2>
            <p style="opacity: 0.8;">پوختەیا داتایێن تۆمارکری د سیستەمی دا</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card border-top-blue">
                <h3>هەموو کەس</h3>
                <div class="number count-up" data-target="<?php echo $total_count; ?>">0</div>
                <i class="fas fa-users icon-bg"></i>
            </div>

            <div class="stat-card border-top-green">
                <h3>ل ژیانێ</h3>
                <div class="number count-up" data-target="<?php echo $status_counts['Alive']; ?>">0</div>
                <i class="fas fa-heart icon-bg"></i>
            </div>

            <div class="stat-card border-top-gray">
                <h3>وەغەرکری</h3>
                <div class="number count-up" data-target="<?php echo $status_counts['Deceased']; ?>">0</div>
                <i class="fas fa-user-slash icon-bg"></i>
            </div>

            <div class="stat-card border-top-sky">
                <h3>نێر</h3>
                <div class="number count-up" data-target="<?php echo $gender_counts['Male']; ?>">0</div>
                <i class="fas fa-mars icon-bg"></i>
            </div>

            <div class="stat-card border-top-pink">
                <h3>مێ</h3>
                <div class="number count-up" data-target="<?php echo $gender_counts['Female']; ?>">0</div>
                <i class="fas fa-venus icon-bg"></i>
            </div>

            <div class="stat-card border-top-gold">
                <h3>خێزان (هەڤژینی)</h3>
                <div class="number count-up" data-target="<?php echo $marriages_count; ?>">0</div>
                <i class="fas fa-rings-wedding icon-bg"></i>
            </div>

            <div class="stat-card border-top-orange">
                <h3>باب و باپیر</h3>
                <div class="number count-up" data-target="<?php echo $ancestors_count; ?>">0</div>
                <i class="fas fa-tree icon-bg"></i>
            </div>

            <div class="stat-card border-top-blue">
                <h3>تێکڕای زارۆکا</h3>
                <div class="number"><?php echo $avg_children; ?></div>
                <i class="fas fa-chart-line icon-bg"></i>
            </div>

             <div class="stat-card border-top-gold">
                <h3>نەوە (جيل)</h3>
                <div class="number"><?php echo $max_depth; ?></div>
                <i class="fas fa-layer-group icon-bg"></i>
            </div>

            <div class="stat-card border-top-gray">
                <h3>زەڵامێن سەڵت</h3>
                <div class="number count-up" data-target="<?php echo $unmarried_men_count; ?>">0</div>
                <i class="fas fa-user icon-bg"></i>
            </div>
            
            <div class="stat-card border-top-green">
                <h3>داتایێ ژدایکبوونێ</h3>
                <div class="number" dir="ltr">%<?php echo $dates_percentage; ?></div>
                <i class="fas fa-calendar-check icon-bg"></i>
            </div>

             <div class="stat-card border-top-orange" style="grid-column: span 1;">
                <h3 style="font-size: 0.9rem;">بابێ زۆرترین زارۆک</h3>
                <div style="font-size: 1.1rem; font-weight:bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $top_father_name; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.8;">(<?php echo $top_father_count; ?> زارۆک)</div>
                <i class="fas fa-crown icon-bg"></i>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-box">
                <div class="chart-title">رێژەیا ژیان و مرنێ</div>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title">رێژەیا ڕەگەزی (نێر و مێ)</div>
                <canvas id="genderChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // تحريك الأرقام
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

            // إعدادات الخطوط للرسم البياني
            Chart.defaults.color = '#fff';
            Chart.defaults.font.family = 'CustomFont, Tajawal';

            // رسم بياني للحالة
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: ['ل ژیانێ', 'وەغەرکری'],
                    datasets: [{
                        data: [<?php echo $status_counts['Alive']; ?>, <?php echo $status_counts['Deceased']; ?>],
                        backgroundColor: ['rgba(46, 204, 113, 0.7)', 'rgba(149, 165, 166, 0.7)'],
                        borderColor: ['#2ecc71', '#95a5a6'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });

            // رسم بياني للجنس
            new Chart(document.getElementById('genderChart'), {
                type: 'pie',
                data: {
                    labels: ['نێر', 'مێ'],
                    datasets: [{
                        data: [<?php echo $gender_counts['Male']; ?>, <?php echo $gender_counts['Female']; ?>],
                        backgroundColor: ['rgba(52, 152, 219, 0.7)', 'rgba(233, 30, 99, 0.7)'],
                        borderColor: ['#3498db', '#e91e63'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
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