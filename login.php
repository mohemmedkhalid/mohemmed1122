<?php
require_once __DIR__.'/config.php';
$next = key_next($_GET['next'] ?? 'index.php');
redirect(isLoggedIn() ? $next : 'access.php?next='.rawurlencode($next));
