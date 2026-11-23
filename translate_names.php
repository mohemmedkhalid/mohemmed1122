<?php
/**
 * translate_names.php
 * أداة تحويل الأسماء الكردية/العربية → إنجليزية
 * مع مراجعة يدوية قبل الحفظ — للمدير فقط
 */
require_once 'config.php';
if (!isAdmin()) { redirect('index.php'); }

$mysqli->set_charset('utf8mb4');

// ══════════════════════════════════════════════════════════════
//  جدول النقحرة الكردية (Sorani) → إنجليزية
// ══════════════════════════════════════════════════════════════
// ══════════════════════════════════════════════════════════════
//  قاموس الأسماء الكاملة (عربي/كردي → إنجليزي)
//  يُطبَّق على كل كلمة منفردة قبل النقحرة الحرفية
// ══════════════════════════════════════════════════════════════
function getNamesDictionary(): array {
    return [
        // ── أسماء ذكور شائعة ──
        'محمد'    => 'Mohammed',  'محمود'   => 'Mahmoud',
        'أحمد'    => 'Ahmed',     'احمد'    => 'Ahmed',
        'علي'     => 'Ali',       'عمر'     => 'Omar',
        'عبدالله' => 'Abdullah',  'عبد الله'=> 'Abdullah',
        'عبدالرحمن'=>'Abdulrahman','إبراهيم' => 'Ibrahim',
        'ابراهيم' => 'Ibrahim',   'إسماعيل' => 'Ismail',
        'اسماعيل' => 'Ismail',   'يوسف'    => 'Yusuf',
        'يوسوف'   => 'Yusuf',    'خالد'    => 'Khaled',
        'حسن'     => 'Hassan',   'حسين'    => 'Hussein',
        'مصطفى'   => 'Mustafa',  'مصطفا'   => 'Mustafa',
        'كريم'    => 'Kareem',   'كرام'    => 'Karam',
        'ذكي'     => 'Zaki',     'زكي'     => 'Zaki',
        'فيصل'    => 'Faisal',   'فارس'    => 'Fares',
        'طارق'    => 'Tarek',    'ماهر'    => 'Maher',
        'ناصر'    => 'Nasser',   'صالح'    => 'Saleh',
        'صلاح'    => 'Salah',    'سعيد'    => 'Saeed',
        'سالم'    => 'Salem',    'سامي'    => 'Sami',
        'نادر'    => 'Nader',    'نور'     => 'Nour',
        'وليد'    => 'Walid',    'وائل'    => 'Wael',
        'رامي'    => 'Rami',     'رياض'    => 'Riyad',
        'رضا'     => 'Reza',     'زياد'    => 'Ziyad',
        'باسم'    => 'Basem',    'بلال'    => 'Bilal',
        'جمال'    => 'Jamal',    'جواد'    => 'Jawad',
        'هادي'    => 'Hadi',     'هيثم'    => 'Haitham',
        'هشام'    => 'Hisham',   'هاشم'    => 'Hashem',
        'ياسر'    => 'Yasser',   'ياسين'   => 'Yassin',
        'لؤي'     => 'Luay',     'لقمان'   => 'Luqman',
        'منصور'   => 'Mansour',  'مازن'    => 'Mazen',
        'معاذ'    => 'Muaz',     'مراد'    => 'Murad',
        'ميلاد'   => 'Milad',    'مهند'    => 'Mohannad',
        'نزار'    => 'Nizar',    'قاسم'    => 'Qasim',
        'قيس'     => 'Qais',     'عماد'    => 'Imad',
        'عادل'    => 'Adel',     'عاصم'    => 'Asem',
        'أسامة'   => 'Osama',    'اسامة'   => 'Osama',
        'أيمن'    => 'Ayman',    'ايمن'    => 'Ayman',
        'أنس'     => 'Anas',     'انس'     => 'Anas',
        'أمجد'    => 'Amjad',    'امجد'    => 'Amjad',
        'إياد'    => 'Iyad',     'اياد'    => 'Iyad',
        'بشير'    => 'Bashir',   'بشار'    => 'Bashar',
        'ثامر'    => 'Thamer',   'جابر'    => 'Jaber',
        'جاسم'    => 'Jasem',    'حمزة'    => 'Hamza',
        'حازم'    => 'Hazem',    'حاتم'    => 'Hatem',
        'حمد'     => 'Hamad',    'داود'    => 'Daoud',
        'دانيال'  => 'Daniel',   'ربيع'    => 'Rabee',
        'رشيد'    => 'Rashid',   'رشاد'    => 'Rashad',
        'رفيق'    => 'Rafiq',    'زاهر'    => 'Zaher',
        'زكريا'   => 'Zakaria',  'شريف'    => 'Sherif',
        'شهاب'    => 'Shahab',   'صفوان'   => 'Safwan',
        'ضياء'    => 'Diaa',     'عبدالعزيز'=>'Abdulaziz',
        'عبدالكريم'=>'Abdulkareem','عبدالمجيد'=>'Abdulmajid',
        'عثمان'   => 'Othman',   'عزيز'    => 'Aziz',
        'عصام'    => 'Essam',    'فؤاد'    => 'Fuad',
        'فضل'     => 'Fadl',     'قادر'    => 'Qader',
        'كمال'    => 'Kamal',    'لطيف'    => 'Latif',
        'مجيد'    => 'Majid',    'مجدي'    => 'Magdi',
        'محسن'    => 'Mohsen',   'محيي'    => 'Mohye',
        'مخلص'    => 'Mokhles',  'مدحت'    => 'Medhat',
        'مروان'   => 'Marwan',   'مشعل'    => 'Meshal',
        'مصباح'   => 'Misbah',   'نعمان'   => 'Numan',
        'نعيم'    => 'Naeem',    'نضال'    => 'Nedal',
        'هاني'    => 'Hani',     'واثق'    => 'Wathiq',
        'وسام'    => 'Wissam',   'وصفي'    => 'Wasfi',
        // ── أسماء كردية ذكور ──
        'شيركو'   => 'Shirko',   'شورش'    => 'Shorsh',
        'كاوه'    => 'Kawe',     'كاوا'    => 'Kawa',
        'هاوار'   => 'Hawar',    'ريبوار'  => 'Rybar',
        'ريبين'   => 'Rybin',    'زانا'    => 'Zana',
        'زانیار'  => 'Zanyar',   'هيمن'    => 'Himen',
        'هيوا'    => 'Hiwa',     'ئاسو'    => 'Aso',
        'ئاراس'   => 'Aras',     'دلشاد'   => 'Dilshad',
        'فرهاد'   => 'Farhad',   'سردار'   => 'Sardar',
        'بختيار'  => 'Bakhtyar', 'باختيار' => 'Bakhtyar',
        'ئارام'   => 'Aram',     'شاهو'    => 'Shaho',
        'شاناز'   => 'Shanaz',   'سامان'   => 'Saman',
        'بنكين'   => 'Bankin',   'بافل'    => 'Bafel',
        'شلير'    => 'Shaleer',  'ئاكو'    => 'Ako',
        // ── أسماء إناث عربية ──
        'فاطمة'   => 'Fatima',   'فاطمه'   => 'Fatima',
        'عائشة'   => 'Aisha',    'عائشه'   => 'Aisha',
        'مريم'    => 'Mariam',   'زينب'    => 'Zainab',
        'سارة'    => 'Sara',     'سارا'    => 'Sara',
        'نور'     => 'Nour',     'رنا'     => 'Rana',
        'رانيا'   => 'Rania',    'منى'     => 'Mona',
        'هدى'     => 'Huda',     'أميرة'   => 'Amira',
        'اميرة'   => 'Amira',    'لبنى'    => 'Lubna',
        'لمى'     => 'Lama',     'ليلى'    => 'Layla',
        'ليلا'    => 'Layla',    'سلمى'    => 'Salma',
        'سلوى'    => 'Salwa',    'شيرين'   => 'Shirin',
        'شيماء'   => 'Shaymaa',  'صباح'    => 'Sabah',
        'نادية'   => 'Nadia',    'نسرين'   => 'Nasrin',
        'هناء'    => 'Hanaa',    'وفاء'    => 'Wafaa',
        'يسرا'    => 'Yusra',    'أمل'     => 'Amal',
        'امل'     => 'Amal',     'أسماء'   => 'Asmaa',
        'اسماء'   => 'Asmaa',    'إيمان'   => 'Iman',
        'ايمان'   => 'Iman',     'بسمة'    => 'Basma',
        'بسمه'    => 'Basma',    'بتول'    => 'Batul',
        'تهاني'   => 'Tahani',   'جيهان'   => 'Jihan',
        'حنان'    => 'Hanan',    'خديجة'   => 'Khadija',
        'دينا'    => 'Dina',     'رشا'     => 'Rasha',
        'رغد'     => 'Raghad',   'ريم'     => 'Reem',
        'ريهام'   => 'Riham',    'زهرة'    => 'Zahra',
        'زهره'    => 'Zahra',    'سحر'     => 'Sahar',
        'سماح'    => 'Samah',    'سمية'    => 'Sumaya',
        'شهد'     => 'Shahd',    'صفاء'    => 'Safaa',
        'ضحى'     => 'Duha',     'عبير'    => 'Abeer',
        'علا'     => 'Ola',      'غادة'    => 'Ghada',
        'فدوى'    => 'Fidwa',    'فرح'     => 'Farah',
        'لارا'    => 'Lara',     'لور'     => 'Lour',
        'ماجدة'   => 'Majda',    'مها'     => 'Maha',
        'نجلاء'   => 'Najlaa',   'نجوى'    => 'Najwa',
        'نرمين'   => 'Narmin',   'هبة'     => 'Heba',
        'هبه'     => 'Heba',     'هيفاء'   => 'Haifaa',
        // ── ألقاب / صفات شائعة كجزء من الاسم ──
        'عبد'     => 'Abd',      'ابو'     => 'Abu',
        'أبو'     => 'Abu',      'بنت'     => 'Bint',
        'بن'      => 'Bin',      'ابن'     => 'Ibn',
    ];
}

// ── تقسيم الاسم الكامل وترجمة كل كلمة ──
function translateFullName(string $name): string {
    $name = trim($name);
    if (empty($name)) return '';
    $dict  = getNamesDictionary();
    // أولاً: جرّب الاسم كاملاً (حالة عبدالله وما شابه)
    if (isset($dict[$name])) return $dict[$name];
    // ثانياً: ترجم كلمة كلمة
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $out   = [];
    foreach ($words as $word) {
        if (isset($dict[$word])) {
            $out[] = $dict[$word];
        } else {
            // لم يُوجد في القاموس → نقحرة حرفية
            $out[] = transliterateWord($word);
        }
    }
    return implode(' ', $out);
}

function transliterate(string $text): string {
    return translateFullName($text);
}

// ── النقحرة الحرفية (للكلمات غير الموجودة في القاموس) ──
function transliterateWord(string $text): string {
    $text = trim($text);
    if (empty($text)) return '';

    // ── جدول الحروف المركّبة (أولًا، قبل المفردة) ──
    $multi = [
        // حروف كردية مركبة
        'ڵا' => 'la', 'ڵێ' => 'le', 'ڵو' => 'lo', 'ڵی' => 'li',
        'ڕا' => 'ra', 'ڕێ' => 're', 'ڕو' => 'ro', 'ڕی' => 'ri',
        // تشكيل عربي شائع
        'آ'  => 'a',
        'اً' => 'an',
        'اً' => 'an',
        // ووو / وو
        'وو' => 'u',
    ];

    // ── جدول الحرف المفرد ──
    $single = [
        // أحرف كردية خاصة
        'ئ'  => '',     // hamza (صامت)
        'ە'  => 'e',
        'ێ'  => 'e',
        'ۆ'  => 'o',
        'ڤ'  => 'v',
        'ڵ'  => 'll',
        'ڕ'  => 'r',
        'ک'  => 'k',
        'گ'  => 'g',
        'چ'  => 'ch',
        'ژ'  => 'zh',
        'ش'  => 'sh',
        'خ'  => 'kh',
        'غ'  => 'gh',
        'ح'  => 'h',
        'ث'  => 's',
        'ذ'  => 'z',
        'ص'  => 's',
        'ض'  => 'd',
        'ط'  => 't',
        'ظ'  => 'z',
        'ع'  => '',
        'ة'  => 'e',
        'ى'  => 'i',
        'ی'  => 'i',
        'ي'  => 'i',
        // حروف عربية عادية
        'ا'  => 'a',
        'أ'  => 'a',
        'إ'  => 'i',
        'ب'  => 'b',
        'پ'  => 'p',
        'ت'  => 't',
        'ج'  => 'j',
        'د'  => 'd',
        'ر'  => 'r',
        'ز'  => 'z',
        'س'  => 's',
        'ف'  => 'f',
        'ق'  => 'q',
        'ك'  => 'k',
        'ل'  => 'l',
        'م'  => 'm',
        'ن'  => 'n',
        'ه'  => 'h',
        'ہ'  => 'h',
        'و'  => 'w',
        // تشكيل (حركات) — نحذفها
        'َ'  => '',
        'ُ'  => '',
        'ِ'  => '',
        'ّ'  => '',
        'ْ'  => '',
        'ً'  => '',
        'ٌ'  => '',
        'ٍ'  => '',
        // أرقام عربية
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
        '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ];

    // تطبيق المركّب أولًا
    foreach ($multi as $k => $v) {
        $text = str_replace($k, $v, $text);
    }
    // ثم المفرد
    foreach ($single as $k => $v) {
        $text = str_replace($k, $v, $text);
    }

    // تنظيف: حذف أحرف غير لاتينية متبقية
    $text = preg_replace('/[^\x20-\x7E]/u', '', $text);
    $text = trim($text);
    if ($text === '') return '';

    // أول حرف كبير
    return ucfirst(strtolower($text));
}

// ══════════════════════════════════════════════════════════════
//  معالجة POST — حفظ الأسماء المُراجَعة
// ══════════════════════════════════════════════════════════════
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── حفظ المراجَع ──
    if ($_POST['action'] === 'save' && !empty($_POST['names'])) {
        $stmt = $mysqli->prepare("UPDATE people2 SET full_name_en = ? WHERE id = ?");
        $updated = 0; $errors = 0;
        foreach ($_POST['names'] as $id => $en_name) {
            $id      = (int)$id;
            $en_name = trim($en_name);
            if ($id <= 0) continue;
            $stmt->bind_param('si', $en_name, $id);
            if ($stmt->execute()) $updated++;
            else                  $errors++;
        }
        $stmt->close();
        $msg      = "✅ تم تحديث $updated اسم بنجاح" . ($errors ? " — ⚠ $errors أخطاء" : '');
        $msg_type = $errors ? 'warn' : 'ok';
    }

    // ── مسح الأسماء الإنجليزية (إعادة ضبط) ──
    if ($_POST['action'] === 'clear_all') {
        $mysqli->query("UPDATE people2 SET full_name_en = NULL");
        $msg = '🔄 تم مسح جميع الأسماء الإنجليزية';
        $msg_type = 'info';
    }
}

// ══════════════════════════════════════════════════════════════
//  جلب الأشخاص
// ══════════════════════════════════════════════════════════════
$filter  = $_GET['filter'] ?? 'all';   // all | empty | filled
$search  = trim($_GET['q'] ?? '');

$where = "1=1";
if ($filter === 'empty')  $where .= " AND (full_name_en IS NULL OR full_name_en = '')";
if ($filter === 'filled') $where .= " AND full_name_en IS NOT NULL AND full_name_en != ''";
if ($search !== '')       $where .= " AND full_name LIKE '%" . $mysqli->real_escape_string($search) . "%'";

$people = [];
$res = $mysqli->query("SELECT id, full_name, full_name_en FROM people2 WHERE $where ORDER BY id ASC");
while ($r = $res->fetch_assoc()) {
    $people[] = $r;
}
$res->free();

// إحصائيات
$total  = $mysqli->query("SELECT COUNT(*) FROM people2")->fetch_row()[0];
$filled = $mysqli->query("SELECT COUNT(*) FROM people2 WHERE full_name_en IS NOT NULL AND full_name_en != ''")->fetch_row()[0];
$empty  = $total - $filled;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تحويل الأسماء إلى الإنجليزية</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.rtl.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #07090f; --panel: #0d1117;
    --card: rgba(255,255,255,.04); --border: rgba(255,255,255,.09);
    --text: #e8edf4; --muted: rgba(232,237,244,.42);
    --gold: #f6c941; --green: #34d399; --sky: #38bdf8;
    --rose: #fb7185; --violet: #a78bfa;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{min-height:100%;background:var(--bg);color:var(--text);font-family:'Tajawal',sans-serif;}
body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
 background:radial-gradient(ellipse 70% 55% at 10% 5%,rgba(56,189,248,.07) 0%,transparent 65%),
             radial-gradient(ellipse 55% 70% at 90% 90%,rgba(167,139,250,.06) 0%,transparent 65%);}
.wrap{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:20px 14px 60px;}

/* header */
.pg-head{padding:18px 0 24px;text-align:center;}
.pg-title{font-size:1.55rem;font-weight:900;
 background:linear-gradient(120deg,var(--gold),var(--sky),var(--violet));
 -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.pg-sub{font-size:.83rem;color:var(--muted);margin-top:4px;}

/* stats bar */
.stats-row{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.stat-pill{flex:1;min-width:130px;background:var(--card);border:1px solid var(--border);
 border-radius:14px;padding:12px 16px;text-align:center;}
.stat-n{font-size:1.5rem;font-weight:900;}
.stat-l{font-size:.7rem;color:var(--muted);}

/* toolbar */
.toolbar{background:var(--card);border:1px solid var(--border);border-radius:14px;
 padding:14px 16px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.fg{background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;
 padding:9px 14px;color:var(--text);font-family:'Tajawal',sans-serif;font-size:.88rem;
 outline:none;transition:border-color .2s;}
.fg:focus{border-color:rgba(56,189,248,.5);}
.fg::placeholder{color:var(--muted);}

.pill-btn{padding:9px 16px;border-radius:10px;border:1px solid var(--border);
 background:rgba(255,255,255,.05);color:var(--text);font-family:'Tajawal',sans-serif;
 font-size:.82rem;cursor:pointer;transition:all .18s;text-decoration:none;white-space:nowrap;}
.pill-btn:hover,.pill-btn.on{background:rgba(255,255,255,.15);color:#fff;border-color:var(--border);}
.pill-btn.on{border-color:var(--sky);color:var(--sky);}

/* actions */
.actions-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.btn-auto{padding:11px 20px;border-radius:12px;border:none;cursor:pointer;
 font-family:'Tajawal',sans-serif;font-size:.88rem;font-weight:700;
 display:flex;align-items:center;gap:7px;transition:all .22s;}
.btn-auto:hover{transform:translateY(-2px);}
.btn-fill{background:linear-gradient(135deg,var(--sky),var(--violet));color:#fff;
 box-shadow:0 4px 16px rgba(56,189,248,.28);}
.btn-save{background:linear-gradient(135deg,var(--green),#059669);color:#fff;
 box-shadow:0 4px 16px rgba(52,211,153,.28);}
.btn-clear{background:rgba(251,113,133,.15);color:var(--rose);border:1px solid rgba(251,113,133,.3);}
.btn-back{background:rgba(255,255,255,.07);color:var(--text);border:1px solid var(--border);}

/* message */
.msg{padding:12px 18px;border-radius:12px;margin-bottom:16px;font-weight:700;font-size:.9rem;}
.msg.ok  {background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.35);color:var(--green);}
.msg.warn{background:rgba(251,113,133,.15);border:1px solid rgba(251,113,133,.35);color:var(--rose);}
.msg.info{background:rgba(56,189,248,.12);border:1px solid rgba(56,189,248,.3);color:var(--sky);}

/* table */
.tbl-wrap{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;overflow-x:auto;}
.tbl{width:100%;border-collapse:collapse;min-width:600px;}
.tbl thead tr{background:rgba(0,0,0,.3);}
.tbl th{padding:12px 14px;font-size:.75rem;font-weight:700;color:var(--muted);
 text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);}
.tbl td{padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:rgba(255,255,255,.04);}

.td-id{color:var(--muted);font-size:.8rem;width:52px;text-align:center;}
.td-ku{font-size:.9rem;font-weight:700;color:var(--text);}
.td-en input{
 width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);
 border-radius:8px;padding:7px 10px;color:var(--text);
 font-family:'Tajawal','Segoe UI',sans-serif;font-size:.88rem;outline:none;
 transition:border-color .18s,background .18s;
}
.td-en input:focus{border-color:var(--sky);background:rgba(56,189,248,.07);}
.td-en input.auto{border-color:rgba(246,201,65,.35);color:var(--gold);}
.td-en input.manual{border-color:rgba(52,211,153,.4);color:var(--green);}
.td-en input.saved{border-color:rgba(52,211,153,.6);color:var(--green);background:rgba(52,211,153,.05);}

.badge-auto{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.65rem;
 font-weight:700;background:rgba(246,201,65,.15);color:var(--gold);}
.badge-saved{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.65rem;
 font-weight:700;background:rgba(52,211,153,.15);color:var(--green);}

/* progress bar */
.prog-wrap{background:rgba(255,255,255,.06);border-radius:50px;height:8px;margin-bottom:20px;overflow:hidden;}
.prog-bar{height:100%;border-radius:50px;background:linear-gradient(90deg,var(--sky),var(--violet));
 transition:width .5s ease;}

/* sticky save */
.sticky-save{
 position:fixed;bottom:0;left:0;right:0;z-index:200;
 background:rgba(7,9,15,.92);backdrop-filter:blur(14px);
 border-top:1px solid var(--border);
 padding:12px 20px;display:flex;justify-content:center;align-items:center;gap:14px;
}
.sticky-count{font-size:.85rem;color:var(--muted);}
.sticky-count b{color:var(--gold);}

::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:4px;}
</style>
</head>
<body>
<div class="wrap">

<!-- Header -->
<div class="pg-head">
    <div class="pg-title">🔤 تحويل الأسماء إلى الإنجليزية</div>
    <div class="pg-sub">مراجعة وتحرير الأسماء قبل الحفظ في قاعدة البيانات</div>
</div>

<!-- Progress -->
<?php $pct = $total > 0 ? round($filled/$total*100) : 0; ?>
<div class="prog-wrap" title="<?= $filled ?> / <?= $total ?>">
    <div class="prog-bar" style="width:<?= $pct ?>%"></div>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-pill">
        <div class="stat-n" style="color:var(--sky)"><?= $total ?></div>
        <div class="stat-l">إجمالي الأشخاص</div>
    </div>
    <div class="stat-pill">
        <div class="stat-n" style="color:var(--green)"><?= $filled ?></div>
        <div class="stat-l">لديهم اسم إنجليزي</div>
    </div>
    <div class="stat-pill">
        <div class="stat-n" style="color:var(--gold)"><?= $empty ?></div>
        <div class="stat-l">بدون اسم إنجليزي</div>
    </div>
    <div class="stat-pill">
        <div class="stat-n" style="color:var(--violet)"><?= $pct ?>%</div>
        <div class="stat-l">نسبة الإنجاز</div>
    </div>
</div>

<?php if ($msg): ?>
<div class="msg <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Toolbar: فلاتر + بحث -->
<div class="toolbar">
    <form method="GET" style="display:contents;">
        <input type="text" name="q" class="fg" placeholder="🔍 بحث بالاسم الكردي..." value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:180px;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <button type="submit" class="pill-btn">بحث</button>
    </form>
    <a href="?filter=all&q=<?= urlencode($search) ?>" class="pill-btn <?= $filter==='all'?'on':'' ?>">الكل (<?= $total ?>)</a>
    <a href="?filter=empty&q=<?= urlencode($search) ?>" class="pill-btn <?= $filter==='empty'?'on':'' ?>">⚠ ناقص (<?= $empty ?>)</a>
    <a href="?filter=filled&q=<?= urlencode($search) ?>" class="pill-btn <?= $filter==='filled'?'on':'' ?>">✅ مكتمل (<?= $filled ?>)</a>
    <a href="index.php" class="pill-btn"><i class="fas fa-home"></i></a>
</div>

<!-- Actions -->
<div class="actions-bar">
    <button class="btn-auto btn-fill" onclick="autoFillAll()">
        <i class="fas fa-wand-magic-sparkles"></i> ملء تلقائي لكل الفارغ
    </button>
    <button class="btn-auto btn-fill" onclick="autoFillVisible()" style="background:linear-gradient(135deg,var(--violet),#7c3aed)">
        <i class="fas fa-eye"></i> ملء تلقائي للمعروض فقط
    </button>
    <form method="POST" onsubmit="return confirm('هل أنت متأكد من مسح جميع الأسماء الإنجليزية؟')">
        <input type="hidden" name="action" value="clear_all">
        <button class="btn-auto btn-clear" type="submit">
            <i class="fas fa-eraser"></i> مسح الكل
        </button>
    </form>
</div>

<!-- Table Form -->
<form method="POST" id="mainForm">
<input type="hidden" name="action" value="save">

<div class="tbl-wrap">
<table class="tbl">
    <thead>
        <tr>
            <th class="td-id">#</th>
            <th>الاسم الكردي / العربي</th>
            <th>الاسم الإنجليزي <small style="opacity:.5">(قابل للتحرير)</small></th>
            <th style="width:90px; text-align:center">الحالة</th>
        </tr>
    </thead>
    <tbody id="tbody">
    <?php foreach ($people as $idx => $p):
        $has_en = !empty($p['full_name_en']);
        $auto   = transliterate($p['full_name']);
        $val    = $has_en ? $p['full_name_en'] : '';
        $cls    = $has_en ? 'saved' : '';
    ?>
    <tr data-id="<?= $p['id'] ?>" data-ku="<?= htmlspecialchars($p['full_name']) ?>" data-auto="<?= htmlspecialchars($auto) ?>">
        <td class="td-id"><?= $p['id'] ?></td>
        <td class="td-ku"><?= htmlspecialchars($p['full_name']) ?></td>
        <td class="td-en">
            <input type="text"
                   name="names[<?= $p['id'] ?>]"
                   value="<?= htmlspecialchars($val) ?>"
                   class="en-input <?= $cls ?>"
                   placeholder="اضغط ✨ أو اكتب يدوياً..."
                   oninput="markManual(this)">
        </td>
        <td style="text-align:center">
            <?php if ($has_en): ?>
                <span class="badge-saved">✅ محفوظ</span>
            <?php else: ?>
                <button type="button" class="pill-btn" style="padding:4px 10px;font-size:.72rem"
                        onclick="autoFillRow(this)" title="ملء تلقائي">✨</button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Sticky Save Bar -->
<div class="sticky-save">
    <span class="sticky-count">
        <b id="changed-count">0</b> اسم تم تعديله — جاهز للحفظ
    </span>
    <button type="submit" class="btn-auto btn-save" onclick="return confirmSave()">
        <i class="fas fa-floppy-disk"></i> حفظ في قاعدة البيانات
    </button>
    <button type="button" class="btn-auto btn-back" onclick="location.href='index.php'">
        <i class="fas fa-home"></i> الرئيسية
    </button>
</div>

</form>

</div><!-- /wrap -->

<script>
// ── قاموس الأسماء الكاملة (JS) ──
const NAMES_DICT = {
    // ذكور عربي
    'محمد':'Mohammed','محمود':'Mahmoud','أحمد':'Ahmed','احمد':'Ahmed',
    'علي':'Ali','عمر':'Omar','عبدالله':'Abdullah','عبد الله':'Abdullah',
    'عبدالرحمن':'Abdulrahman','إبراهيم':'Ibrahim','ابراهيم':'Ibrahim',
    'إسماعيل':'Ismail','اسماعيل':'Ismail','يوسف':'Yusuf','يوسوف':'Yusuf',
    'خالد':'Khaled','حسن':'Hassan','حسين':'Hussein','مصطفى':'Mustafa',
    'مصطفا':'Mustafa','كريم':'Kareem','ذكي':'Zaki','زكي':'Zaki',
    'فيصل':'Faisal','فارس':'Fares','طارق':'Tarek','ماهر':'Maher',
    'ناصر':'Nasser','صالح':'Saleh','صلاح':'Salah','سعيد':'Saeed',
    'سالم':'Salem','سامي':'Sami','نادر':'Nader','وليد':'Walid',
    'وائل':'Wael','رامي':'Rami','رياض':'Riyad','رضا':'Reza',
    'زياد':'Ziyad','باسم':'Basem','بلال':'Bilal','جمال':'Jamal',
    'جواد':'Jawad','هادي':'Hadi','هيثم':'Haitham','هشام':'Hisham',
    'هاشم':'Hashem','ياسر':'Yasser','ياسين':'Yassin','لؤي':'Luay',
    'لقمان':'Luqman','منصور':'Mansour','مازن':'Mazen','معاذ':'Muaz',
    'مراد':'Murad','ميلاد':'Milad','مهند':'Mohannad','نزار':'Nizar',
    'قاسم':'Qasim','قيس':'Qais','عماد':'Imad','عادل':'Adel',
    'أسامة':'Osama','اسامة':'Osama','أيمن':'Ayman','ايمن':'Ayman',
    'أنس':'Anas','انس':'Anas','أمجد':'Amjad','امجد':'Amjad',
    'حمزة':'Hamza','حازم':'Hazem','حاتم':'Hatem','حمد':'Hamad',
    'داود':'Daoud','رشيد':'Rashid','رشاد':'Rashad','زكريا':'Zakaria',
    'شريف':'Sherif','عثمان':'Othman','عزيز':'Aziz','كمال':'Kamal',
    'مجيد':'Majid','مروان':'Marwan','هاني':'Hani','وسام':'Wissam',
    // كردي ذكور
    'شيركو':'Shirko','شورش':'Shorsh','كاوه':'Kawe','كاوا':'Kawa',
    'هاوار':'Hawar','ريبوار':'Rybar','ريبين':'Rybin','زانا':'Zana',
    'زانیار':'Zanyar','هيمن':'Himen','هيوا':'Hiwa','ئاسو':'Aso',
    'ئاراس':'Aras','دلشاد':'Dilshad','فرهاد':'Farhad','سردار':'Sardar',
    'بختيار':'Bakhtyar','ئارام':'Aram','شاهو':'Shaho','سامان':'Saman',
    'بافل':'Bafel','ئاكو':'Ako',
    // إناث عربي
    'فاطمة':'Fatima','فاطمه':'Fatima','عائشة':'Aisha','عائشه':'Aisha',
    'مريم':'Mariam','زينب':'Zainab','سارة':'Sara','سارا':'Sara',
    'نور':'Nour','رنا':'Rana','رانيا':'Rania','منى':'Mona',
    'هدى':'Huda','أميرة':'Amira','اميرة':'Amira','لبنى':'Lubna',
    'ليلى':'Layla','ليلا':'Layla','سلمى':'Salma','سلوى':'Salwa',
    'شيرين':'Shirin','شيماء':'Shaymaa','صباح':'Sabah','نادية':'Nadia',
    'نسرين':'Nasrin','هناء':'Hanaa','وفاء':'Wafaa','يسرا':'Yusra',
    'أمل':'Amal','امل':'Amal','أسماء':'Asmaa','اسماء':'Asmaa',
    'إيمان':'Iman','ايمان':'Iman','بسمة':'Basma','بسمه':'Basma',
    'حنان':'Hanan','خديجة':'Khadija','دينا':'Dina','رشا':'Rasha',
    'ريم':'Reem','زهرة':'Zahra','زهره':'Zahra','سحر':'Sahar',
    'سماح':'Samah','شهد':'Shahd','فرح':'Farah','لارا':'Lara',
    'مها':'Maha','نجوى':'Najwa','هبة':'Heba','هبه':'Heba',
    // ألقاب
    'عبد':'Abd','ابو':'Abu','أبو':'Abu','بن':'Bin','ابن':'Ibn',
};

// ── بيانات النقحرة (نفس جدول PHP لكن في JS للمعاينة الفورية) ──
const MULTI = {
    'وو':'u', 'آ':'a', 'اً':'an',
    'ڵا':'la','ڵێ':'le','ڵو':'lo','ڵی':'li',
    'ڕا':'ra','ڕێ':'re','ڕو':'ro','ڕی':'ri',
};
const SINGLE = {
    'ئ':'','ە':'e','ێ':'e','ۆ':'o','ڤ':'v','ڵ':'ll','ڕ':'r',
    'ک':'k','گ':'g','چ':'ch','ژ':'zh','ش':'sh','خ':'kh','غ':'gh',
    'ح':'h','ث':'s','ذ':'z','ص':'s','ض':'d','ط':'t','ظ':'z','ع':'','ة':'e',
    'ى':'i','ی':'i','ي':'i','ا':'a','أ':'a','إ':'i','ب':'b','پ':'p','ت':'t',
    'ج':'j','د':'d','ر':'r','ز':'z','س':'s','ف':'f','ق':'q','ك':'k','ل':'l',
    'م':'m','ن':'n','ه':'h','ہ':'h','و':'w',
    'َ':'','ُ':'','ِ':'','ّ':'','ْ':'','ً':'','ٌ':'','ٍ':'',
    '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9',
};

function jsTransliterateSingle(text) {
    text = text.trim();
    for (const [k,v] of Object.entries(MULTI))  text = text.split(k).join(v);
    for (const [k,v] of Object.entries(SINGLE)) text = text.split(k).join(v);
    text = text.replace(/[^\x20-\x7E]/g, '').trim();
    return text ? text.charAt(0).toUpperCase() + text.slice(1).toLowerCase() : '';
}

function jsTransliterate(text) {
    text = text.trim();
    if (!text) return '';
    // جرّب الاسم كاملاً أولاً
    if (NAMES_DICT[text]) return NAMES_DICT[text];
    // ثم كلمة كلمة
    return text.split(/\s+/).filter(Boolean).map(word =>
        NAMES_DICT[word] ? NAMES_DICT[word] : jsTransliterateSingle(word)
    ).join(' ');
}

// ── ملء تلقائي لصف واحد ──
function autoFillRow(btn) {
    const row   = btn.closest('tr');
    const input = row.querySelector('.en-input');
    const ku    = row.dataset.ku;
    const auto  = jsTransliterate(ku);
    input.value = auto;
    input.classList.remove('saved','manual');
    input.classList.add('auto');
    trackChange(input);
}

// ── ملء تلقائي لكل الفارغ في الجدول ──
function autoFillAll() {
    document.querySelectorAll('#tbody tr').forEach(row => {
        const input = row.querySelector('.en-input');
        if (!input.value.trim()) {
            input.value = jsTransliterate(row.dataset.ku);
            input.classList.add('auto');
            trackChange(input);
        }
    });
    updateChangedCount();
    alert('✅ تم الملء التلقائي! راجع الأسماء ثم اضغط حفظ.');
}

// ── ملء تلقائي للمعروض فقط ──
function autoFillVisible() {
    document.querySelectorAll('#tbody tr:not([style*="display:none"])').forEach(row => {
        const input = row.querySelector('.en-input');
        if (!input.value.trim()) {
            input.value = jsTransliterate(row.dataset.ku);
            input.classList.add('auto');
            trackChange(input);
        }
    });
    updateChangedCount();
}

// ── تمييز التعديل اليدوي ──
function markManual(input) {
    input.classList.remove('auto','saved');
    input.classList.add('manual');
    trackChange(input);
}

// ── تتبع التغييرات ──
const changedIds = new Set();
function trackChange(input) {
    const id = input.closest('tr').dataset.id;
    if (input.value.trim()) changedIds.add(id);
    else changedIds.delete(id);
    updateChangedCount();
}
function updateChangedCount() {
    document.getElementById('changed-count').textContent = changedIds.size;
}

function confirmSave() {
    const n = changedIds.size;
    if (n === 0) { alert('⚠ لم تقم بأي تعديل بعد!'); return false; }
    return confirm(`هل تريد حفظ ${n} اسم في قاعدة البيانات؟\nهذا الإجراء سيحدّث الأسماء الإنجليزية فوراً.`);
}

// ── تتبع التغييرات عند التحميل ──
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.en-input').forEach(inp => {
        inp.addEventListener('input', () => markManual(inp));
        if (inp.value.trim()) changedIds.add(inp.closest('tr').dataset.id);
    });
    updateChangedCount();
});
</script>
</body>
</html>
