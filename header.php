<?php
require_once 'config.php';

// التحقق من أن المستخدم قد سجل دخوله
if (!isLoggedIn()) {
    redirect('login.php');
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info'; // Default to info if not set
    echo '<div class="message ' . escape($message_type) . '">' . escape($message) . '</div>';
    // حذف الرسالة بعد عرضها
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>