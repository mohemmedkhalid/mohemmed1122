<?php
require_once 'header.php';
$lang = get_lang();
$isEn = ($lang === 'en');
$dir  = $isEn ? 'ltr' : 'rtl';

// =================================================================
// 1. جلب البيانات وتجهيز المصفوفات (المنطق المدمج)
// =================================================================

$married_ids = [];
$wives_counts = [];
$m_result = $mysqli->query("SELECT husband_id, wife_id FROM marriages");
if ($m_result) {
    while ($row = $m_result->fetch_assoc()) {
        $married_ids[] = $row['husband_id'];
        $married_ids[] = $row['wife_id'];
        if(!isset($wives_counts[$row['husband_id']])) $wives_counts[$row['husband_id']] = 0;
        $wives_counts[$row['husband_id']]++;
    }
    $m_result->free();
}
$married_ids = array_flip(array_unique($married_ids));

// جلب البيانات من people2 (حسب الملف الأحدث)
$query = "SELECT id, full_name, full_name_en, father_id, mother_id, birth_date, status, gender, ration_number, notes, is_primary_ancestor FROM people2";
$result = $mysqli->query($query);

$all_people = [];
$people_by_parent = []; 
$children_counts = [];

while ($row = $result->fetch_assoc()) {
    $all_people[$row['id']] = $row;
    $f_id = $row['father_id'] ?: 0;
    $people_by_parent[$f_id][] = $row['id'];
    
    if ($f_id > 0) {
        $children_counts[$f_id] = ($children_counts[$f_id] ?? 0) + 1;
    }
}
$result->free();

function get_full_chain_name($person_id, $people_map) {
    $chain = [];
    $curr_id = $person_id;
    $limit = 3; 
    while ($curr_id && isset($people_map[$curr_id]) && $limit > 0) {
        // استخدم الاسم الإنجليزي إذا كانت اللغة إنجليزية
        $chain[] = get_person_name($people_map[$curr_id]);
        $curr_id = $people_map[$curr_id]['father_id'];
        $limit--;
    }
    return implode(' ', $chain);
}

$display_list = [];
function flatten_tree($parent_id, $people_by_parent, $all_people, &$display_list, $married_ids, $children_counts, $wives_counts) {
    if (isset($people_by_parent[$parent_id])) {
        // ترتيب أبجدي للأبناء
        usort($people_by_parent[$parent_id], function($a, $b) use ($all_people) {
            return strcmp($all_people[$a]['full_name'], $all_people[$b]['full_name']);
        });

        foreach ($people_by_parent[$parent_id] as $child_id) {
            $person = $all_people[$child_id];
            $id = $person['id'];
            
            $display_list[] = [
                'id' => $id,
                'search_name' => get_full_chain_name($child_id, $all_people),
                'birth_year' => ($person['birth_date'] && $person['birth_date'] != '0000-00-00') ? date('Y', strtotime($person['birth_date'])) : '-',
                'status' => strtolower($person['status']),
                'gender' => strtolower($person['gender']),
                'marital' => isset($married_ids[$id]) ? 'married' : 'single',
                'has_children' => (isset($children_counts[$id]) && $children_counts[$id] > 0) ? 'yes' : 'no',
                'poly' => (isset($wives_counts[$id]) && $wives_counts[$id] > 1) ? 'yes' : 'no',
                'ration' => !empty($person['ration_number']) ? 'yes' : 'no',
                'orphan' => (empty($person['father_id'])) ? 'yes' : 'no',
                'ancestor' => $person['is_primary_ancestor'] ? 'yes' : 'no'
            ];
            flatten_tree($child_id, $people_by_parent, $all_people, $display_list, $married_ids, $children_counts, $wives_counts);
        }
    }
}
flatten_tree(0, $people_by_parent, $all_people, $display_list, $married_ids, $children_counts, $wives_counts);
?>

<!DOCTYPE html>
<html lang="<?= $isEn ? 'en' : 'ku' ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEn ? 'Family List & Statistics' : 'إحصائيات العائلة الذكية' ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table>:not(caption)>*>* {
    padding: .5rem .5rem;
    color:#ffffff; 
    background-color: linear-gradient(135deg, #3a5251, #000000, #57565e); 
    border-bottom-width: var(--bs-border-width);
    box-shadow: inset 0 0 0 9999px rgb(49, 65, 73);
}
        :root {
            --glass-bg: rgba(255, 255, 255, 0.12);
            --glass-border: rgba(255, 255, 255, 0.2);
            --accent-color: #3498db;
        }

        body {
 background: linear-gradient(135deg, #3a5251, #000000, #57565e);  
           color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-bottom: 50px;
        }

        .header-section {
           margin-top: 70px;
            padding-bottom: 30px;
            padding-left: 15px;
            padding-right: 15px;
            text-align: center;
        }

        /* حاوية الفلاتر (الأيقونات) */
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 12px;
            margin-bottom: 30px;
            padding: 0 10px;
        }

        .stat-btn {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px 5px;
            color: white;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .stat-btn i { font-size: 1.5rem; }
        .stat-btn span { font-size: 0.75rem; font-weight: bold; }
        .stat-btn.active {
            background: rgba(255, 255, 255, 0.3);
            border-color: #fff;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        /* البحث وأزرار الترتيب */
        .search-container {
            margin: 0 15px 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .search-container {
                flex-direction: row;
                align-items: center;
            }
        }

        .search-input {
            flex-grow: 1;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            padding: 15px 25px;
            color: white;
            outline: none;
            backdrop-filter: blur(5px);
        }

        .sort-btn {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: white;
            border-radius: 50px;
            padding: 10px 20px;
            backdrop-filter: blur(5px);
            transition: all 0.3s;
            cursor: pointer;
        }

        .sort-btn:hover, .sort-btn.active-sort {
            background: rgba(255, 255, 255, 0.3);
            border-color: #fff;
        }

        /* الجدول والموبايل */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            margin: 0 10px;
            overflow: hidden;
        }

        .table { color: white; margin-bottom: 0; }
        .table thead { background: rgba(0,0,0,0.2); }
        .table td { vertical-align: middle; border-color: rgba(255,255,255,0.05); padding: 12px; }

        .btn-tree {
            background: #27ae5f00;
            color: white;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .highlight { background: rgba(241, 196, 15, 0.5); border-radius: 4px; }
        .hidden { display: none !important; }
        
        /* ألوان الأيقونات */
        .ic-male { color: #409eff; }
        .ic-female { color: #f56c6c; }
        .ic-dead { color: #909399; }
        .ic-marry { color: #e6a23c; }
        .ic-child { color: #67c23a; }
        .ic-head { color: #00bcd4; }
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
        <a href="index.php" class="btn-ctrl" title="الرئيسية"><i class="fas fa-home"></i></a>
        <a href="tree.php" class="btn-ctrl" title="زنجيرە بنەمالێ"><i class="fas fa-sitemap"></i></a>
        <a href="family_list.php" class="btn-ctrl" title="إعادة تحميل"><i class="fas fa-sync-alt"></i></a>
        <a href="set_lang.php?lang=<?= $isEn ? 'ku' : 'en' ?>&back=family_list.php"
           class="btn-ctrl" title="<?= $isEn ? 'Switch to Kurdish' : 'Switch to English' ?>" style="font-size:13px;"><?= $isEn ? '🟡' : '🇬🇧' ?></a>
        <button id="fullscreen-btn" class="btn-ctrl" title="شاشة كاملة"><i class="fas fa-expand"></i></button>
    </div>
<div class="container-fluid">
    <div class="header-section">
        <h2 class="fw-bold"><i class="fas fa-chart-pie me-2"></i><?= $isEn ? 'Family List & Statistics' : 'لیستا خێزانان و ئامار' ?></h2>
        <p class="opacity-75"><?= $isEn ? 'Smart search and filter within the family' : 'گەڕان و فلتەرکرنا ژیرانە دناڤ خێزانێ دا' ?></p>
    </div>
    

    <div class="filter-grid">
        <div class="stat-btn active" data-filter="all" onclick="filterData('all', this)">
            <i class="fas fa-users"></i>
            <span><?= $isEn ? 'All' : 'هەمی' ?> <b class="text-warning me-1">(<span class="count-val">0</span>)</b></span>
        </div>
        <div class="stat-btn" data-filter="head-family" onclick="filterData('head-family', this)">
            <i class="fas fa-user-tie ic-head"></i>
            <span><?= $isEn ? 'Family Heads' : 'رئيس عائلة' ?> <b class="text-warning me-1">(<span class="count-val">0</span>)</b></span>
        </div>
        <div class="stat-btn" data-filter="male-alive" onclick="filterData('male-alive', this)">
            <i class="fas fa-mars ic-male"></i>
            <span><?= $isEn ? 'Males (Alive)' : 'زەلام (ساخ)' ?> <b class="text-warning me-1">(<span class="count-val">0</span>)</b></span>
        </div>
        <div class="stat-btn" data-filter="female-alive" onclick="filterData('female-alive', this)">
            <i class="fas fa-venus ic-female"></i>
            <span><?= $isEn ? 'Females (Alive)' : 'ژن (ساخ)' ?> <b class="text-warning me-1">(<span class="count-val">0</span>)</b></span>
        </div>
        <div class="stat-btn" data-filter="deceased" onclick="filterData('deceased', this)">
            <i class="fas fa-monument ic-dead"></i>
            <span><?= $isEn ? 'Deceased' : 'وەغەرکری' ?> <b class="text-warning me-1">(<span class="count-val">0</span>)</b></span>
        </div>
        <div class="stat-btn" data-filter="single-male" onclick="filterData('single-male', this)">
            <i class="fas fa-user-tag"></i>
            <span><?= $isEn ? 'Unmarried Males' : 'گەنج' ?> <b class="text-warning me-1">(<span class="count-val">0</span>)</b></span>
        </div>
        <div class="stat-btn" data-filter="poly" onclick="filterData('poly', this)">
            <i class="fas fa-layer-group ic-marry"></i>
            <span><?= $isEn ? 'Polygamy' : 'تعدد الزوجات' ?> <b class="text-warning me-1">(<span class="count-val">0</span>)</b></span>
        </div>
    </div>

    <div class="search-container">
        <input type="text" id="mainSearch" class="search-input" placeholder="<?= $isEn ? 'Type a name to search...' : 'ناڤێ سیانی بنڤێسە بۆ گەڕانێ...' ?>" onkeyup="applyAllFilters()">
        
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn sort-btn active-sort" onclick="sortData('tree', this)">
                <i class="fas fa-sitemap me-1"></i> <?= $isEn ? 'Tree Order' : 'ترتيب الشجرة' ?>
            </button>
            <button class="btn sort-btn" onclick="sortData('name', this)">
                <i class="fas fa-sort-alpha-down me-1"></i> <?= $isEn ? 'Alphabetical' : 'ترتيب أبجدي' ?>
            </button>
        </div>
    </div>

    <div class="glass-card">
        <div class="table-responsive">
            <table class="table table-hover" id="familyTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th><?= $isEn ? 'Full Name' : 'ناڤێ سیانی' ?></th>
                        <th><?= $isEn ? 'Birth' : 'بون' ?></th>
                        <th class="text-center"><?= $isEn ? 'Action' : 'کردار' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $count = 1; foreach($display_list as $p): ?>
                    <tr class="person-row" 
                        data-tree-order="<?= $count ?>"
                        data-name="<?= htmlspecialchars($p['search_name']) ?>"
                        data-gender="<?= $p['gender'] ?>"
                        data-status="<?= $p['status'] ?>"
                        data-marital="<?= $p['marital'] ?>"
                        data-children="<?= $p['has_children'] ?>"
                        data-poly="<?= $p['poly'] ?>"
                        data-orphan="<?= $p['orphan'] ?>"
                        data-ration="<?= $p['ration'] ?>"
                        data-ancestor="<?= $p['ancestor'] ?>">
                        
                        <td class="row-num"><?= $count++ ?></td>
                        <td>
                            <div class="fw-bold name-txt"><?= $p['search_name'] ?></div>
                            <div class="small opacity-50">
                                <?php if($p['gender'] == 'male'): ?> <i class="fas fa-mars text-info"></i> <?= $p['status'] == 'alive' ? ($isEn ? 'Alive' : 'ساخ') : ($isEn ? 'Deceased' : 'وەغەرکری') ?>
                                <?php else: ?> <i class="fas fa-venus text-danger"></i> <?= $p['status'] == 'alive' ? ($isEn ? 'Alive' : 'ساخ') : ($isEn ? 'Deceased' : 'وەغەرکری') ?> <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="badge bg-dark bg-opacity-25"><?= $p['birth_year'] ?></span></td>
                        <td class="text-center">
                            <a href="tree.php?id=<?= $p['id'] ?>" class="btn-tree">
                                <i class="fas fa-sitemap me-1"></i> 
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let currentFilter = 'all';

    // دالة حساب الأعداد وعرضها في الأزرار
    function updateFilterCounts() {
        let rows = document.querySelectorAll('.person-row');
        let counts = {
            'all': 0, 'head-family': 0, 'male-alive': 0, 'female-alive': 0,
            'deceased': 0, 'single-male': 0, 'poly': 0
        };

        rows.forEach(row => {
            let gender = row.getAttribute('data-gender');
            let status = row.getAttribute('data-status');
            let marital = row.getAttribute('data-marital');
            let poly = row.getAttribute('data-poly');

            counts['all']++;
            // رئيس العائلة: ذكر ومتزوج
            if (gender === 'male' && marital === 'married') counts['head-family']++;
            if (gender === 'male' && status === 'alive') counts['male-alive']++;
            if (gender === 'female' && status === 'alive') counts['female-alive']++;
            if (status === 'deceased') counts['deceased']++;
            if (gender === 'male' && marital === 'single' && status === 'alive') counts['single-male']++;
            if (poly === 'yes') counts['poly']++;
        });

        // تحديث الأرقام داخل الأزرار
        document.querySelectorAll('.stat-btn').forEach(btn => {
            let filterType = btn.getAttribute('data-filter');
            let countSpan = btn.querySelector('.count-val');
            if (countSpan && counts[filterType] !== undefined) {
                countSpan.innerText = counts[filterType];
            }
        });
    }

    // تشغيل دالة الحساب عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', updateFilterCounts);

    function filterData(type, btn) {
        document.querySelectorAll('.stat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = type;
        applyAllFilters();
    }

    function sortData(type, btn) {
        document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active-sort'));
        btn.classList.add('active-sort');

        let tbody = document.querySelector('#familyTable tbody');
        let rows = Array.from(tbody.querySelectorAll('.person-row'));

        rows.sort((a, b) => {
            if (type === 'name') {
                return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'), 'ar');
            } else {
                return parseInt(a.getAttribute('data-tree-order')) - parseInt(b.getAttribute('data-tree-order'));
            }
        });

        rows.forEach(row => tbody.appendChild(row));
        applyAllFilters();
    }

    function applyAllFilters() {
        let search = document.getElementById('mainSearch').value.toLowerCase();
        let rows = document.querySelectorAll('.person-row');
        let visibleCount = 1;

        rows.forEach(row => {
            let name = row.getAttribute('data-name').toLowerCase();
            let gender = row.getAttribute('data-gender');
            let status = row.getAttribute('data-status');
            let marital = row.getAttribute('data-marital');
            let children = row.getAttribute('data-children');
            let poly = row.getAttribute('data-poly');
            let orphan = row.getAttribute('data-orphan');
            let ration = row.getAttribute('data-ration');
            let ancestor = row.getAttribute('data-ancestor');

            let matchSearch = name.includes(search);
            let matchStat = false;

            switch(currentFilter) {
                case 'all': matchStat = true; break;
                // تم تعديل الشرط هنا: رئيس عائلة (ذكر ومتزوج)
                case 'head-family': matchStat = (gender === 'male' && marital === 'married'); break; 
                case 'male-alive': matchStat = (gender === 'male' && status === 'alive'); break;
                case 'female-alive': matchStat = (gender === 'female' && status === 'alive'); break;
                case 'deceased': matchStat = (status === 'deceased'); break;
                case 'single-male': matchStat = (gender === 'male' && marital === 'single' && status === 'alive'); break;
                case 'poly': matchStat = (poly === 'yes'); break;
                case 'has-children': matchStat = (children === 'yes'); break;
                case 'orphan': matchStat = (orphan === 'yes'); break;
                case 'ration': matchStat = (ration === 'yes'); break;
                case 'ancestor': matchStat = (ancestor === 'yes'); break;
            }

            if(matchSearch && matchStat) {
                row.classList.remove('hidden');
                row.querySelector('.row-num').innerText = visibleCount++;
                
                let nameDiv = row.querySelector('.name-txt');
                if(search.length > 0) {
                    let regex = new RegExp(`(${search})`, "gi");
                    nameDiv.innerHTML = row.getAttribute('data-name').replace(regex, "<span class='highlight'>$1</span>");
                } else {
                    nameDiv.innerHTML = row.getAttribute('data-name');
                }
            } else {
                row.classList.add('hidden');
            }
        });
    }
</script>

</body>
</html>