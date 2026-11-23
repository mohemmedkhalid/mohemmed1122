<?php
// تضمين ملف إعدادات الاتصال بقاعدة البيانات
require_once 'config.php';

// اسم الملف الذي سيتم تحميله
$filename = "people_data_" . date('Y-m-d') . ".csv";

// ضبط الهيدرز (Headers) لفرض تحميل الملف
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// فتح مؤشر للكتابة في مخرج PHP مباشرة
// هذا يمنع الحاجة إلى إنشاء ملف على الخادم أولاً
$output = fopen('php://output', 'w');

// إضافة BOM (Byte Order Mark) لضمان أن Excel يفتح الملف بترميز UTF-8 بشكل صحيح (خاصة للغة العربية)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// الحصول على أسماء الأعمدة من الجدول وكتابتها كسطر أول في الملف
$query_columns = $mysqli->query("SHOW COLUMNS FROM people");
$headers = array();
while($row = $query_columns->fetch_assoc()){
    $headers[] = $row['Field'];
}
fputcsv($output, $headers);

// الاستعلام لجلب جميع البيانات من جدول 'people'
$query_data = $mysqli->query("SELECT * FROM people ORDER BY id ASC");

// التحقق من وجود بيانات
if ($query_data->num_rows > 0) {
    // المرور على كل صف من البيانات وكتابته في ملف CSV
    while ($row = $query_data->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

// إغلاق مؤشر الكتابة
fclose($output);
// إغلاق اتصال قاعدة البيانات
$mysqli->close();
exit();

?>