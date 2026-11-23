<?php
require_once __DIR__.'/config.php';
key_logout($mysqli);
header('Location: access.php', true, 303);
exit;
